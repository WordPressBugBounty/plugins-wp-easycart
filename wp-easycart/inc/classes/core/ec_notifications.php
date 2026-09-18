<?php
/**
 * WP EasyCart — admin stock notifications.
 *
 * Sends the low stock and out of stock emails to the store's admin addresses for
 * both product-level stock ( ec_product.stock_quantity ) and per-variant stock
 * ( ec_optionitemquantity.quantity, one to five option items per row ).
 *
 * 6.0.0:
 *   - Variant alerts now resolve the option combination by its option item IDs.
 *     The old lookup joined ec_optionitem once and asked that single row to carry
 *     every option name at the same time, so any product with two or more option
 *     levels ( Color + Size ) never matched and never sent an email.
 *   - An alert is sent once per crossing instead of on every order that leaves an
 *     item low: the last level sent per product / variant is remembered in the
 *     ec_stock_alert_state option and cleared when stock climbs back above the
 *     threshold. This applies to product-level alerts as well.
 *   - Admin stock edits ( the inventory On hand popover, bulk updates, PRO
 *     adjustments, approving an order ) are covered through
 *     'wpeasycart_inventory_stock_changed'; restocks refresh the state without
 *     mailing.
 *   - The threshold comes from wp_easycart_low_stock_threshold() ( inc/classes/core/ec_stock.php ),
 *     the one number shared with the Inventory and Products screens and the PRO
 *     digest: variant reorder point, then product reorder point, then the
 *     store-wide setting.
 *   - Emails go through ec_email::send() so they appear in the email log / queue.
 *
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ec_notifications {

	/**
	 * Option holding the last alert level sent per item, so a low stock email is
	 * sent once per crossing. Keys are 'p<product_id>' and 'v<optionitemquantity_id>',
	 * values are 'low' or 'out'. Only items currently at or below their threshold
	 * are kept, and the option is never autoloaded.
	 *
	 * @since 6.0.0
	 */
	const ALERT_STATE_OPTION = 'ec_stock_alert_state';

	/** Safety valve: the state resets rather than growing without bound. @since 6.0.0 */
	const ALERT_STATE_MAX = 1000;

	private $wpdb;

	/** Lazily loaded copy of the alert state option. @since 6.0.0 */
	private $alert_state = null;

	function __construct( ){
		global $wpdb;
		$this->wpdb =& $wpdb;
		if( get_option( 'ec_option_send_low_stock_emails' ) || get_option( 'ec_option_send_out_of_stock_emails' ) ){
			add_action( 'wpeasycart_order_paid', array( $this, 'check_stock_levels' ), 10, 1 );
			add_action( 'wpeasycart_inventory_stock_changed', array( $this, 'check_stock_change' ), 10, 1 );
			add_action( 'wp_easycart_updated_optionitem_quantity_value', array( $this, 'check_optionitem_restock' ), 20, 7 );
			add_action( 'wp_easycart_updated_product_stock', array( $this, 'check_product_restock' ), 20, 2 );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Entry points                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Every line of a paid order, in one query: the product row, and — when the
	 * product tracks stock per option combination — the matching
	 * ec_optionitemquantity row joined on the five option item IDs the line was
	 * bought with. Stock has already been taken out by the time this runs.
	 *
	 * @param int $order_id Order that was paid.
	 */
	function check_stock_levels( $order_id ){
		$order_id = (int) $order_id;
		if ( $order_id <= 0 ) {
			return;
		}

		$product_reorder = $this->has_reorder_point() ? 'p.reorder_point' : '-1';
		$variant_reorder = $this->has_reorder_point() ? 'q.reorder_point' : '-1';

		$sql = 'SELECT p.product_id, p.title, p.show_stock_quantity, p.use_optionitem_quantity_tracking, p.stock_quantity,
					' . $product_reorder . ' AS product_reorder_point,
					d.optionitem_name_1, d.optionitem_name_2, d.optionitem_name_3, d.optionitem_name_4, d.optionitem_name_5,
					q.optionitemquantity_id, q.quantity AS optionitem_quantity, q.is_enabled AS optionitem_is_enabled,
					q.is_stock_tracking_enabled AS optionitem_is_stock_tracking_enabled,
					' . $variant_reorder . ' AS optionitem_reorder_point
				FROM ec_orderdetail d
				INNER JOIN ec_product p ON p.product_id = d.product_id
				LEFT JOIN ec_optionitemquantity q ON q.product_id = d.product_id
					AND q.optionitem_id_1 = d.optionitem_id_1
					AND q.optionitem_id_2 = d.optionitem_id_2
					AND q.optionitem_id_3 = d.optionitem_id_3
					AND q.optionitem_id_4 = d.optionitem_id_4
					AND q.optionitem_id_5 = d.optionitem_id_5
				WHERE d.order_id = %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $product_reorder / $variant_reorder are literal column names or '-1' chosen in code, never input.

		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is the literal above.

		if ( ! is_array( $rows ) ) {
			return;
		}
		foreach ( $rows as $row ) {
			$this->evaluate_stock_row( $row, true );
		}
	}

	/**
	 * Any stock write made through wp_easycart_admin_inventory::apply_stock_change():
	 * the On hand popover, bulk updates, PRO adjustments and approving an order.
	 * Emails only go out when the change took stock away.
	 *
	 * @since 6.0.0
	 * @param array $change See wp_easycart_admin_inventory::apply_stock_change().
	 */
	public function check_stock_change( $change ){
		if ( ! is_array( $change ) || empty( $change['product_id'] ) ) {
			return;
		}

		/* Bulk syncs would mail one alert per row; the PRO low stock digest covers those. */
		$skip_sources = apply_filters( 'wp_easycart_stock_alert_skip_sources', array( 'import', 'square' ) );
		$source       = isset( $change['source'] ) ? (string) $change['source'] : '';
		if ( is_array( $skip_sources ) && in_array( $source, $skip_sources, true ) ) {
			return;
		}

		$allow_email = ( isset( $change['delta'] ) && (int) $change['delta'] < 0 );
		$oiq_id      = isset( $change['optionitemquantity_id'] ) ? (int) $change['optionitemquantity_id'] : 0;

		if ( $oiq_id > 0 ) {
			$this->check_optionitem_stock( $oiq_id, $allow_email );
		} else {
			$this->check_product_stock( (int) $change['product_id'], $allow_email );
		}
	}

	/**
	 * Refunds and other restocks run through ec_db::update_quantity_value() with a
	 * negative quantity. Re-read the row so the remembered alert level follows the
	 * stock back up; a restock never mails.
	 *
	 * @since 6.0.0
	 * @param int $quantity Quantity removed ( negative when stock is being returned ).
	 */
	public function check_optionitem_restock( $quantity, $product_id, $optionitem_id_1, $optionitem_id_2, $optionitem_id_3, $optionitem_id_4, $optionitem_id_5 ){
		if ( (int) $quantity >= 0 ) {
			/* A sale: check_stock_levels() evaluates the whole order in one query. */
			return;
		}
		$row = $this->get_optionitem_row(
			'q.product_id = %d AND q.optionitem_id_1 = %d AND q.optionitem_id_2 = %d AND q.optionitem_id_3 = %d AND q.optionitem_id_4 = %d AND q.optionitem_id_5 = %d',
			array( (int) $product_id, (int) $optionitem_id_1, (int) $optionitem_id_2, (int) $optionitem_id_3, (int) $optionitem_id_4, (int) $optionitem_id_5 )
		);
		if ( $row ) {
			$this->evaluate_stock_row( $row, false );
		}
	}

	/**
	 * Same idea for product-level stock returned by a refund.
	 *
	 * @since 6.0.0
	 * @param int $product_id Product.
	 * @param int $quantity   Quantity removed ( negative when stock is being returned ).
	 */
	public function check_product_restock( $product_id, $quantity ){
		if ( (int) $quantity >= 0 ) {
			return;
		}
		$this->check_product_stock( (int) $product_id, false );
	}

	/**
	 * Evaluate one option combination by its ec_optionitemquantity row ID.
	 *
	 * @since 6.0.0
	 * @param int  $optionitemquantity_id Variant row.
	 * @param bool $allow_email           False to refresh the remembered level silently.
	 */
	public function check_optionitem_stock( $optionitemquantity_id, $allow_email = true ){
		$optionitemquantity_id = (int) $optionitemquantity_id;
		if ( $optionitemquantity_id <= 0 ) {
			return;
		}
		$row = $this->get_optionitem_row( 'q.optionitemquantity_id = %d', array( $optionitemquantity_id ) );
		if ( $row ) {
			$this->evaluate_stock_row( $row, $allow_email );
		}
	}

	/**
	 * Evaluate a product's own stock quantity.
	 *
	 * @since 6.0.0
	 * @param int  $product_id  Product.
	 * @param bool $allow_email False to refresh the remembered level silently.
	 */
	public function check_product_stock( $product_id, $allow_email = true ){
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return;
		}

		$product_reorder = $this->has_reorder_point() ? 'p.reorder_point' : '-1';
		$sql = 'SELECT p.product_id, p.title, p.show_stock_quantity, p.use_optionitem_quantity_tracking, p.stock_quantity,
					' . $product_reorder . ' AS product_reorder_point,
					0 AS optionitemquantity_id
				FROM ec_product p
				WHERE p.product_id = %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $product_reorder is a literal column name or '-1' chosen in code.

		$row = $this->wpdb->get_row( $this->wpdb->prepare( $sql, $product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is the literal above.
		if ( $row ) {
			$this->evaluate_stock_row( $row, $allow_email );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Evaluation                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Decide what a row's quantity means and, when the level changed, mail the
	 * matching alert. Variant rows win over product stock: a product that tracks
	 * per-option quantities has a rolled-up total that would double up.
	 *
	 * @since 6.0.0
	 * @param object $row         Product row, optionally carrying the joined variant columns.
	 * @param bool   $allow_email Whether this change may produce an email.
	 */
	private function evaluate_stock_row( $row, $allow_email ){
		if ( ! is_object( $row ) ) {
			return;
		}

		if ( ! empty( $row->use_optionitem_quantity_tracking ) ) {
			if ( empty( $row->optionitemquantity_id ) ) {
				return;
			}
			/* An untracked or disabled combination is unlimited: nothing to warn about. */
			if ( isset( $row->optionitem_is_enabled ) && ! $row->optionitem_is_enabled ) {
				return;
			}
			if ( isset( $row->optionitem_is_stock_tracking_enabled ) && ! $row->optionitem_is_stock_tracking_enabled ) {
				return;
			}

			$quantity  = (int) $row->optionitem_quantity;
			$threshold = wp_easycart_low_stock_threshold( $row );
			$this->maybe_send_alert( 'v' . (int) $row->optionitemquantity_id, $this->stock_level( $quantity, $threshold ), $allow_email, true, $row, $quantity );

		} else if ( ! empty( $row->show_stock_quantity ) ) {
			$quantity  = (int) $row->stock_quantity;
			/* Product-level stock never takes a variant's reorder point, even on a row that carries one. */
			$threshold = wp_easycart_low_stock_threshold( array( 'product_id' => (int) $row->product_id, 'product_reorder_point' => isset( $row->product_reorder_point ) ? (int) $row->product_reorder_point : -1 ) );
			$this->maybe_send_alert( 'p' . (int) $row->product_id, $this->stock_level( $quantity, $threshold ), $allow_email, false, $row, $quantity );
		}
	}

	/**
	 * 'out' at zero or below, 'low' at or under the threshold, otherwise 'ok'.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	private function stock_level( $quantity, $threshold ){
		if ( $quantity <= 0 ) {
			return 'out';
		}
		if ( $threshold > 0 && $quantity <= $threshold ) {
			return 'low';
		}
		return 'ok';
	}

	/**
	 * Remember the level and, when it just got worse, send one email.
	 *
	 * Stock that stays low across several orders does not mail again, and stock
	 * that is partly restocked ( out -> low ) only lowers the remembered level.
	 * The remembered level is what de-duplicates, so a variant that appears on two
	 * lines of the same order is only mailed once.
	 *
	 * @since 6.0.0
	 * @param string $key         'p<product_id>' or 'v<optionitemquantity_id>'.
	 * @param string $level       ok | low | out.
	 * @param bool   $allow_email Whether this change may produce an email.
	 * @param bool   $is_variant  Which pair of templates to use.
	 * @param object $row         Row passed to the template as $product.
	 * @param int    $quantity    Quantity the template reports.
	 */
	private function maybe_send_alert( $key, $level, $allow_email, $is_variant, $row, $quantity ){
		$state    = $this->get_alert_state();
		$previous = isset( $state[ $key ] ) ? $state[ $key ] : 'ok';
		if ( $previous === $level ) {
			return;
		}
		$this->set_alert_state( $key, $level );

		if ( ! $allow_email ) {
			return;
		}

		$send = '';
		if ( 'out' === $level && get_option( 'ec_option_send_out_of_stock_emails' ) ) {
			$send = 'out';
		} else if ( 'low' === $level && 'ok' === $previous && get_option( 'ec_option_send_low_stock_emails' ) ) {
			$send = 'low';
		}
		if ( '' === $send ) {
			return;
		}

		if ( $is_variant ) {
			if ( 'out' === $send ) {
				$this->send_optionitem_out_of_stock_email_admin( $row, $quantity );
			} else {
				$this->send_optionitem_low_stock_email_admin( $row, $quantity );
			}
		} else {
			if ( 'out' === $send ) {
				$this->send_out_of_stock_email_admin( $row );
			} else {
				$this->send_low_stock_email_admin( $row );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Alert state                                                         */
	/* ------------------------------------------------------------------ */

	private function get_alert_state( ){
		if ( null === $this->alert_state ) {
			$state             = get_option( self::ALERT_STATE_OPTION, array() );
			$this->alert_state = is_array( $state ) ? $state : array();
		}
		return $this->alert_state;
	}

	private function set_alert_state( $key, $level ){
		$state = $this->get_alert_state();
		if ( 'ok' === $level ) {
			if ( ! isset( $state[ $key ] ) ) {
				return;
			}
			unset( $state[ $key ] );
		} else {
			if ( isset( $state[ $key ] ) && $state[ $key ] === $level ) {
				return;
			}
			if ( count( $state ) >= self::ALERT_STATE_MAX ) {
				$state = array();
			}
			$state[ $key ] = $level;
		}
		$this->alert_state = $state;
		update_option( self::ALERT_STATE_OPTION, $state, false );
	}

	/* ------------------------------------------------------------------ */
	/* Queries                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * One variant row with its product and its option item names.
	 *
	 * @since 6.0.0
	 * @param string $where  WHERE clause with %d placeholders.
	 * @param array  $values Values for the placeholders.
	 * @return object|null
	 */
	private function get_optionitem_row( $where, $values ){
		$variant_reorder = $this->has_reorder_point() ? 'q.reorder_point' : '-1';
		$product_reorder = $this->has_reorder_point() ? 'p.reorder_point' : '-1';

		$sql = 'SELECT p.product_id, p.title, p.show_stock_quantity, p.use_optionitem_quantity_tracking, p.stock_quantity,
					' . $product_reorder . ' AS product_reorder_point,
					q.optionitemquantity_id, q.quantity AS optionitem_quantity, q.is_enabled AS optionitem_is_enabled,
					q.is_stock_tracking_enabled AS optionitem_is_stock_tracking_enabled,
					' . $variant_reorder . ' AS optionitem_reorder_point,
					oi1.optionitem_name AS optionitem_name_1, oi2.optionitem_name AS optionitem_name_2,
					oi3.optionitem_name AS optionitem_name_3, oi4.optionitem_name AS optionitem_name_4,
					oi5.optionitem_name AS optionitem_name_5
				FROM ec_optionitemquantity q
				INNER JOIN ec_product p ON p.product_id = q.product_id
				LEFT JOIN ec_optionitem oi1 ON oi1.optionitem_id = q.optionitem_id_1
				LEFT JOIN ec_optionitem oi2 ON oi2.optionitem_id = q.optionitem_id_2
				LEFT JOIN ec_optionitem oi3 ON oi3.optionitem_id = q.optionitem_id_3
				LEFT JOIN ec_optionitem oi4 ON oi4.optionitem_id = q.optionitem_id_4
				LEFT JOIN ec_optionitem oi5 ON oi5.optionitem_id = q.optionitem_id_5
				WHERE ' . $where . ' LIMIT 1'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is a literal clause from this class and only carries placeholders; the reorder columns are literal names.

		return $this->wpdb->get_row( $this->wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is the literal above.
	}

	/**
	 * The reorder_point columns arrive with the 6.0.0 database upgrade; alerts must
	 * not throw SQL errors on a store that has not run it yet.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	private function has_reorder_point( ){
		static $has = null;
		if ( null !== $has ) {
			return $has;
		}
		if ( (int) get_option( 'ec_option_db_new_version' ) >= 105 ) {
			$has = true;
			return $has;
		}
		$has = ( (bool) $this->wpdb->get_results( "SHOW COLUMNS FROM ec_optionitemquantity LIKE 'reorder_point'" )
			&& (bool) $this->wpdb->get_results( "SHOW COLUMNS FROM ec_product LIKE 'reorder_point'" ) );
		return $has;
	}

	/* ------------------------------------------------------------------ */
	/* Email                                                               */
	/* ------------------------------------------------------------------ */

	/** Admin addresses these alerts go to — the same ones every other admin copy uses. @since 6.0.0 */
	private function alert_recipients( ){
		return apply_filters( 'wp_easycart_stock_alert_recipients', stripslashes( (string) get_option( 'ec_option_bcc_email_addresses' ) ) );
	}

	/**
	 * Render one of the stock templates, layout override first.
	 *
	 * @since 6.0.0
	 * @param string $template File name in design/layout/<layout>/.
	 * @param object $product  Row the template reads as $product.
	 * @param int    $option_item_stock_quantity Quantity for the option templates.
	 * @return string
	 */
	private function render_stock_email( $template, $product, $option_item_stock_quantity = 0 ){
		$email_logo_url = get_option( 'ec_option_email_logo' );

		$storepageid = get_option( 'ec_option_storepage' );
		if ( function_exists( 'icl_object_id' ) ) {
			$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
		}
		$store_page = get_permalink( $storepageid );
		if ( class_exists( "WordPressHTTPS" ) && isset( $_SERVER['HTTPS'] ) ) {
			$https_class = new WordPressHTTPS();
			$store_page = $https_class->makeUrlHttps( $store_page );
		}

		if ( substr_count( $store_page, '?' ) ) {
			$permalink_divider = "&";
		} else {
			$permalink_divider = "?";
		}

		$paths = array(
			EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/' . $template,
			EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/' . $template,
		);
		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				ob_start();
				include $path;
				return ob_get_clean();
			}
		}
		return '';
	}

	/**
	 * Deliver through ec_email so the alert is logged, queued and retried like every
	 * other store email; falls back to the historic wp_mail / plugin mailer split.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	private function send_stock_email( $subject, $message, $type ){
		$to = $this->alert_recipients();
		if ( '' === trim( (string) $to ) || '' === trim( (string) $message ) ) {
			return false;
		}

		if ( class_exists( 'ec_email' ) ) {
			return (bool) ec_email::send( $to, $subject, $message, array( 'type' => $type, 'channel' => 'order' ) );
		}

		$from = stripslashes( get_option( 'ec_option_order_from_email' ) );
		if ( get_option( 'ec_option_use_wp_mail' ) ) {
			$headers   = array();
			$headers[] = "MIME-Version: 1.0";
			$headers[] = "Content-Type: text/html; charset=utf-8";
			$headers[] = "From: " . $from;
			$headers[] = "Reply-To: " . $from;
			$headers[] = "X-Mailer: PHP/" . phpversion();
			return (bool) wp_mail( $to, $subject, $message, implode( "\r\n", $headers ) );
		}

		if ( ! class_exists( 'wpeasycart_mailer' ) ) {
			return false;
		}
		$mailer = new wpeasycart_mailer( );
		return ( false === $mailer->send_order_email( $to, $subject, $message ) );
	}

	public function send_low_stock_email_admin( $product ){
		$message = $this->render_stock_email( 'ec_low_stock_email.php', $product );
		return $this->send_stock_email( __( 'Low Stock Notification', 'wp-easycart' ), $message, 'low_stock' );
	}

	public function send_out_of_stock_email_admin( $product ){
		$message = $this->render_stock_email( 'ec_out_of_stock_email.php', $product );
		return $this->send_stock_email( __( 'Out of Stock Notification', 'wp-easycart' ), $message, 'out_of_stock' );
	}

	public function send_optionitem_low_stock_email_admin( $product, $option_item_stock_quantity ){
		$message = $this->render_stock_email( 'ec_optionitem_low_stock_email.php', $product, $option_item_stock_quantity );
		return $this->send_stock_email( __( 'Low Stock Notification', 'wp-easycart' ), $message, 'low_stock' );
	}

	public function send_optionitem_out_of_stock_email_admin( $product, $option_item_stock_quantity ){
		$message = $this->render_stock_email( 'ec_optionitem_out_of_stock_email.php', $product, $option_item_stock_quantity );
		return $this->send_stock_email( __( 'Out of Stock Notification', 'wp-easycart' ), $message, 'out_of_stock' );
	}
}

if ( ! function_exists( 'wp_easycart_notifications' ) ) {
	/**
	 * The stock notifier, for code that writes a quantity outside the paths that
	 * already broadcast a stock change. Guard the call with function_exists() so the
	 * PRO plugin never fatals against an older free plugin.
	 *
	 * @since 6.0.0
	 * @return ec_notifications
	 */
	function wp_easycart_notifications( ) {
		if ( ! isset( $GLOBALS['ec_notifications'] ) || ! ( $GLOBALS['ec_notifications'] instanceof ec_notifications ) ) {
			$GLOBALS['ec_notifications'] = new ec_notifications( );
		}
		return $GLOBALS['ec_notifications'];
	}
}
