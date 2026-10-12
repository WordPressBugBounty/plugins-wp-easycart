<?php
/**
 * WP EasyCart — the EU withdrawal button in the admin ( 6.0.4 ).
 *
 * The store's side of wp_easycart_withdrawals:
 *
 *  - Settings › Checkout › Withdrawal button ( declared in admin/template/settings/checkout.php ): the page picker and its Create
 *    the page action, the sentence for the store's terms with a Copy button, and the statements whose order number and email
 *    matched no order ( Attach to an order, Dismiss: AJAX ecv2_withdrawal_attach / ecv2_withdrawal_dismiss, guard
 *    ecv2_withdrawal_guard() ). Script admin/js/withdrawals-v2.js.
 *  - The product editor's Product Behaviors switch "No right of withdrawal" ( ec_product.no_withdrawal ).
 *  - The order screen: a yellow notice for each statement not handled yet ( Mark as handled: AJAX ecv2_order_withdrawal_handled,
 *    guard ecv2_order_screen_guard(); Refund with WP EasyCart PRO ), and WP EasyCart PRO's timeline entries for
 *    order-withdrawal-requested / -handled.
 *  - The orders list: a Withdrawals tile while any wait, and a Withdrawals filter.
 *  - A notice on EasyCart screens when the 6.0.4 update switched the button on ( an EU or EEA store ), and Store Status rows.
 *
 * @since 6.0.4
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_withdrawal_guard' ) ) {
	/**
	 * AJAX guard for the withdrawal settings' actions: order managers only, and the section's nonce.
	 *
	 * @since 6.0.4
	 */
	function ecv2_withdrawal_guard() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) && ! current_user_can( 'wpec_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to change orders.', 'wp-easycart' ) ), 403 );
		}
		if ( ! check_ajax_referer( 'wp-easycart-ecv2-withdrawals', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Reload it and try again.', 'wp-easycart' ) ), 403 );
		}
	}
}

if ( ! class_exists( 'wp_easycart_admin_withdrawals' ) ) :

	/**
	 * The withdrawal button's admin.
	 *
	 * @since 6.0.4
	 */
	final class wp_easycart_admin_withdrawals {

		/** The admin-post action of the update notice's Got it. */
		const NOTICE_ACTION = 'wp_easycart_withdrawal_notice';

		/** The orders list's health filter value. */
		const HEALTH = 'withdrawals';

		/** Register hooks. */
		public static function init() {
			add_filter( 'wp_easycart_admin_product_details_general_options_fields_list', array( __CLASS__, 'product_field' ), 10, 2 );
			add_action( 'wpeasycart_admin_product_details_general_saved', array( __CLASS__, 'product_save' ) );
			add_filter( 'wp_easycart_ecv2_order_flags', array( __CLASS__, 'order_flags' ), 20, 2 );
			add_action( 'wp_ajax_ecv2_order_withdrawal_handled', array( __CLASS__, 'ajax_handled' ) );
			add_filter( 'wp_easycart_admin_order_history_entry', array( __CLASS__, 'history_entry' ), 5, 3 );
			add_filter( 'wp_easycart_admin_order_timeline_labels', array( __CLASS__, 'timeline_labels' ), 5, 1 );
			add_filter( 'wp_easycart_admin_order_list_filters', array( __CLASS__, 'list_filter' ) );
			add_filter( 'wp_easycart_ecv2_order_health_stats', array( __CLASS__, 'health_tile' ), 10, 1 );
			add_filter( 'wp_easycart_ecv2_order_health_filter_where', array( __CLASS__, 'health_where' ), 10, 2 );
			add_action( 'wp_easycart_admin_messages', array( __CLASS__, 'switched_on_notice' ), 16 );
			add_action( 'admin_post_' . self::NOTICE_ACTION, array( __CLASS__, 'dismiss_notice' ) );
			add_action( 'wp_ajax_ecv2_withdrawal_attach', array( __CLASS__, 'ajax_attach' ) );
			add_action( 'wp_ajax_ecv2_withdrawal_dismiss', array( __CLASS__, 'ajax_dismiss' ) );
		}

		/**
		 * The engine is loaded and its database update has run.
		 *
		 * @return bool
		 */
		private static function engine() {
			return class_exists( 'wp_easycart_withdrawals' ) && wp_easycart_withdrawals::ready();
		}

		// Settings › Checkout › Withdrawal button.

		/**
		 * The switch as the storefront reads it ( the first read writes its starting value: on for an EU or EEA store ).
		 *
		 * @return string '1' | '0'
		 */
		public static function current_enabled() {
			if ( ! class_exists( 'wp_easycart_withdrawals' ) ) {
				return '0';
			}
			wp_easycart_withdrawals::seed_setting();
			return '1' === (string) get_option( wp_easycart_withdrawals::OPT_ENABLED, '0' ) ? '1' : '0';
		}

		/**
		 * The switch was saved ( its on_save ): the update's notice has done its job.
		 */
		public static function enabled_saved() {
			delete_option( 'ec_option_withdrawal_switched_on' );
		}

		/**
		 * Email addresses typed for the store's copy: the valid ones, joined by commas.
		 *
		 * @param mixed $raw Typed value.
		 * @return string
		 */
		public static function sanitize_emails( $raw ) {
			$list = array();
			foreach ( preg_split( '/[,;\s]+/', (string) $raw ) as $part ) {
				$part = sanitize_email( trim( $part ) );
				if ( is_email( $part ) ) {
					$list[] = $part;
				}
			}
			return implode( ', ', array_unique( $list ) );
		}

		/**
		 * The pages the withdrawal page can be ( published pages ).
		 *
		 * @return array id => title
		 */
		public static function page_options() {
			$options = array( '0' => __( 'None: your account page shows the form', 'wp-easycart' ) );
			foreach ( get_pages(
				array(
					'post_status' => 'publish',
					'number'      => 500,
					'sort_column' => 'post_title',
				)
			) as $page ) {
				$title                         = trim( wp_strip_all_tags( get_the_title( $page ) ) );
				$options[ (string) $page->ID ] = ( '' !== $title ? $title : '#' . $page->ID ) . ( has_shortcode( (string) $page->post_content, 'ec_withdrawal' ) ? ' ✓' : '' );
			}
			return $options;
		}

		/**
		 * The section's Create the page action: a published page with the withdrawal form, titled with the button's words in the
		 * store's language ( a menu item takes that as its label ), chosen as the withdrawal page.
		 *
		 * @return array|WP_Error
		 */
		public static function create_page() {
			if ( ! current_user_can( 'publish_pages' ) ) {
				return new WP_Error( 'cap', __( 'You do not have permission to publish pages.', 'wp-easycart' ) );
			}
			if ( ! class_exists( 'wp_easycart_withdrawals' ) ) {
				return new WP_Error( 'engine', __( 'Update WP EasyCart’s database first ( Settings › Store Status ).', 'wp-easycart' ) );
			}
			$existing = wp_easycart_withdrawals::page_id();
			if ( $existing > 0 ) {
				return array(
					/* translators: %s: page title. */
					'message' => sprintf( __( 'Your withdrawal page is “%s”.', 'wp-easycart' ), get_the_title( $existing ) ),
					'fields'  => array( wp_easycart_withdrawals::OPT_PAGE => (string) $existing ),
				);
			}
			$id = wp_insert_post(
				array(
					'post_title'   => wp_easycart_withdrawals::text( 'withdraw_button' ),
					'post_content' => "<!-- wp:shortcode -->\n[ec_withdrawal]\n<!-- /wp:shortcode -->",
					'post_status'  => 'publish',
					'post_type'    => 'page',
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			update_option( wp_easycart_withdrawals::OPT_PAGE, (int) $id );
			return array(
				/* translators: %s: page title. */
				'message' => sprintf( __( 'Created “%s”. Add it to your menu or footer too.', 'wp-easycart' ), get_the_title( $id ) ),
				'fields'  => array( wp_easycart_withdrawals::OPT_PAGE => (string) (int) $id ),
			);
		}

		/** The sentence for the store's withdrawal instructions ( Annex I(A) of the Directive ), with a Copy button. */
		public static function render_terms() {
			if ( ! class_exists( 'wp_easycart_withdrawals' ) ) {
				return;
			}
			/* translators: %s: the withdrawal page's address. */
			$text = sprintf( __( 'You can also exercise your right of withdrawal online at %s. If you use this online feature, we will send you an acknowledgement of receipt of the withdrawal on a durable medium (e.g. by email), including its content and the date and time of its submission, without undue delay.', 'wp-easycart' ), wp_easycart_withdrawals::page_url() );
			echo '<div class="ecwd-terms">';
			echo '<p class="ecst-row-desc"><strong>' . esc_html__( 'For your terms and withdrawal instructions', 'wp-easycart' ) . '</strong> ' . esc_html__( 'The model withdrawal instructions now include this sentence. Add it to your terms.', 'wp-easycart' ) . '</p>';
			echo '<blockquote class="ecwd-terms-text" id="ecwd-terms-text">' . esc_html( $text ) . '</blockquote>';
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecwd-copy="ecwd-terms-text">' . esc_html__( 'Copy', 'wp-easycart' ) . '</button> <span class="ecwd-copied" role="status" hidden>' . esc_html__( 'Copied', 'wp-easycart' ) . '</span>';
			echo '</div>';
		}

		/** The statements whose order number and email matched no order ( Attach to an order, Dismiss ). */
		public static function render_unmatched() {
			if ( ! self::engine() ) {
				return;
			}
			$rows = wp_easycart_withdrawals::unmatched( 50 );
			if ( ! $rows ) {
				return;
			}
			echo '<div class="ecwd-unmatched" id="ecwd-unmatched">';
			echo '<p class="ecst-row-desc"><strong>' . esc_html__( 'Statements that matched no order', 'wp-easycart' ) . '</strong> ' . esc_html__( 'The order number and email typed match no order, often a typing mistake. The shopper got a receipt. Find the order and attach the statement to it, or dismiss it.', 'wp-easycart' ) . '</p>';
			echo '<div class="ecwd-table-wrap"><table class="ecwd-table"><thead><tr><th>' . esc_html__( 'Received', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Order typed', 'wp-easycart' ) . '</th><th>' . esc_html__( 'From', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Note and reason', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Order number', 'wp-easycart' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$id    = (int) $row->withdrawal_id;
				$notes = trim( (string) $row->note . ( '' !== trim( (string) $row->reason ) ? "\n" . (string) $row->reason : '' ) );
				echo '<tr data-ecwd-row="' . esc_attr( (string) $id ) . '">';
				echo '<td>' . esc_html( wp_easycart_withdrawals::when( strtotime( (string) $row->submitted_at . ' UTC' ) ) ) . '</td>';
				echo '<td>' . esc_html( (string) $row->order_ref ) . '</td>';
				echo '<td>' . esc_html( (string) $row->customer_name ) . '<br><small>' . esc_html( (string) $row->customer_email ) . '</small></td>';
				echo '<td>' . nl2br( esc_html( $notes ) ) . '</td>';
				echo '<td class="ecwd-actions"><label class="screen-reader-text" for="ecwd-order-' . esc_attr( (string) $id ) . '">' . esc_html__( 'Order number', 'wp-easycart' ) . '</label><input type="text" inputmode="numeric" class="ecwd-order" id="ecwd-order-' . esc_attr( (string) $id ) . '" size="7" /> ';
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-ecwd-attach="' . esc_attr( (string) $id ) . '">' . esc_html__( 'Attach', 'wp-easycart' ) . '</button> ';
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecwd-dismiss="' . esc_attr( (string) $id ) . '">' . esc_html__( 'Dismiss', 'wp-easycart' ) . '</button>';
				echo '<span class="ecwd-msg" role="status"></span></td>';
				echo '</tr>';
			}
			echo '</tbody></table></div></div>';
		}

		/** The section's script and styles ( Settings › Checkout ). */
		public static function enqueue() {
			$base = plugins_url( '/admin/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
			wp_enqueue_script( 'wp_easycart_admin_withdrawals_v2', $base . 'js/withdrawals-v2.js', array(), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wp_easycart_admin_withdrawals_v2',
				'ecwd_vars',
				array(
					'ajax'  => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'wp-easycart-ecv2-withdrawals' ),
					'text'  => array(
						'attached'  => __( 'Attached to the order.', 'wp-easycart' ),
						'dismissed' => __( 'Dismissed.', 'wp-easycart' ),
						'no_order'  => __( 'Type the order number first.', 'wp-easycart' ),
						'failed'    => __( 'That did not work. Reload the page and try again.', 'wp-easycart' ),
					),
				)
			);
			wp_add_inline_style( 'wp_easycart_admin_settings_page_v2_css', '.ecwd-terms-text{margin:8px 0;padding:10px 14px;border-left:3px solid var(--ec-brand,#2271b1);background:rgba(0,0,0,.03);font-size:13px;line-height:1.5}.ecwd-table-wrap{overflow-x:auto}.ecwd-table{width:100%;border-collapse:collapse;font-size:13px}.ecwd-table th,.ecwd-table td{text-align:left;vertical-align:top;padding:8px 10px;border-bottom:1px solid #e5e7eb}.ecwd-actions{white-space:nowrap}.ecwd-msg{display:block;font-size:12px;margin-top:4px}' );
		}

		/** AJAX ecv2_withdrawal_attach: put a statement that matched no order on the order typed. */
		public static function ajax_attach() {
			ecv2_withdrawal_guard();
			if ( ! self::engine() ) {
				wp_send_json_error( array( 'message' => __( 'Update WP EasyCart’s database first ( Settings › Store Status ).', 'wp-easycart' ) ) );
			}
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ecv2_withdrawal_guard() above.
			$id       = isset( $_POST['withdrawal_id'] ) ? absint( $_POST['withdrawal_id'] ) : 0;
			$order_id = isset( $_POST['order_id'] ) ? absint( preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) ) ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$record = wp_easycart_withdrawals::attach( $id, $order_id );
			if ( is_wp_error( $record ) ) {
				wp_send_json_error( array( 'message' => $record->get_error_message() ) );
			}
			wp_send_json_success(
				array(
					/* translators: %d: order number. */
					'message' => sprintf( __( 'Attached to order %d.', 'wp-easycart' ), (int) $record->order_id ),
					'url'     => admin_url( 'admin.php?page=wp-easycart-orders&subpage=orders&ec_admin_form_action=edit&order_id=' . (int) $record->order_id ),
				)
			);
		}

		/** AJAX ecv2_withdrawal_dismiss: dismiss a statement that matched no order. */
		public static function ajax_dismiss() {
			ecv2_withdrawal_guard();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_withdrawal_guard() above.
			$id = isset( $_POST['withdrawal_id'] ) ? absint( $_POST['withdrawal_id'] ) : 0;
			if ( ! self::engine() || ! wp_easycart_withdrawals::dismiss( $id ) ) {
				wp_send_json_error( array( 'message' => __( 'This statement is already on an order or was dismissed.', 'wp-easycart' ) ) );
			}
			wp_send_json_success( array( 'message' => __( 'Dismissed.', 'wp-easycart' ) ) );
		}

		// Products.

		/**
		 * Filter wp_easycart_admin_product_details_general_options_fields_list: the "No right of withdrawal" switch in Product Behaviors.
		 *
		 * @param array  $fields  Fields.
		 * @param object $product Product.
		 * @return array
		 */
		public static function product_field( $fields, $product = null ) {
			if ( ! self::engine() ) {
				return $fields;
			}
			$fields[] = array(
				'name'            => 'no_withdrawal',
				'type'            => 'checkbox',
				'label'           => __( 'No right of withdrawal ( EU )', 'wp-easycart' ),
				'description'     => __( 'For products the law exempts from the 14-day right of withdrawal: made to order or personalised, goods that spoil quickly, sealed hygiene items once opened, sealed audio, video or software once opened, newspapers and magazines, and dated services such as events. The withdrawal button leaves these items out.', 'wp-easycart' ),
				'required'        => false,
				'validation_type' => 'checkbox',
				'visible'         => true,
				'value'           => ( is_object( $product ) && isset( $product->no_withdrawal ) ) ? (int) $product->no_withdrawal : 0,
			);
			return $fields;
		}

		/**
		 * Action wpeasycart_admin_product_details_general_saved: save the switch ( the editor posts it with Product Behaviors ).
		 *
		 * @param int $product_id Product.
		 */
		public static function product_save( $product_id ) {
			global $wpdb;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the general options save checked the product editor's nonce before this action.
			if ( ! self::engine() || ! isset( $_POST['no_withdrawal'] ) ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- as above.
			$value = absint( $_POST['no_withdrawal'] ) ? 1 : 0;
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET no_withdrawal = %d WHERE product_id = %d', $value, (int) $product_id ) );
		}

		// The order screen.

		/**
		 * How much of the order a statement covers, in words.
		 *
		 * @param object $record Statement.
		 * @return string
		 */
		private static function items_text( $record ) {
			$what = wp_easycart_withdrawals::decode_lines( $record->withdrawn_lines );
			if ( $what['whole'] ) {
				return __( 'The whole order', 'wp-easycart' );
			}
			$units = array_sum( $what['lines'] );
			/* translators: %d: number of items. */
			return sprintf( _n( '%d item', '%d items', $units, 'wp-easycart' ), $units );
		}

		/**
		 * Whether a statement arrived within the period, in words.
		 *
		 * @param object $record Statement.
		 * @return string
		 */
		private static function in_time_text( $record ) {
			if ( null === $record->in_time ) {
				return '';
			}
			if ( 1 === (int) $record->in_time ) {
				if ( '' !== (string) $record->deadline_at ) {
					/* translators: %s: date. */
					return sprintf( __( 'within the withdrawal period ( until %s )', 'wp-easycart' ), wp_easycart_withdrawals::day( strtotime( (string) $record->deadline_at . ' UTC' ) ) );
				}
				return __( 'within the withdrawal period ( it has not started yet )', 'wp-easycart' );
			}
			return __( 'with no withdrawal period open as the order stands: check it', 'wp-easycart' );
		}

		/**
		 * Filter wp_easycart_ecv2_order_flags: a notice for each statement the store has not handled yet.
		 *
		 * @param array  $flags Notices.
		 * @param object $order Order row.
		 * @return array
		 */
		public static function order_flags( $flags, $order ) {
			if ( ! self::engine() || ! is_object( $order ) || empty( $order->order_id ) ) {
				return $flags;
			}
			foreach ( wp_easycart_withdrawals::for_order( (int) $order->order_id, 'received' ) as $record ) {
				$parts   = array();
				$parts[] = wp_easycart_withdrawals::when( strtotime( (string) $record->submitted_at . ' UTC' ) );
				$parts[] = self::items_text( $record );
				$in_time = self::in_time_text( $record );
				if ( '' !== $in_time ) {
					$parts[] = $in_time;
				}
				$text = implode( ' · ', $parts ) . '. ';
				/* translators: 1: name, 2: email. */
				$text .= sprintf( __( 'From %1$s ( %2$s ).', 'wp-easycart' ), (string) $record->customer_name, (string) $record->customer_email );
				foreach ( wp_easycart_withdrawals::summary_rows( $record, false ) as $row ) {
					if ( in_array( $row['label'], array( wp_easycart_withdrawals::text( 'review_items' ), wp_easycart_withdrawals::text( 'review_reason' ), wp_easycart_withdrawals::text( 'review_note' ) ), true ) && wp_easycart_withdrawals::text( 'review_whole' ) !== $row['value'] ) {
						$text .= ' ' . $row['label'] . ': ' . str_replace( "\n", ', ', $row['value'] ) . '.';
					}
				}
				$text   .= ' ' . __( 'Nothing on the order changed: arrange the return and refund what is due, then mark it handled.', 'wp-easycart' );
				$actions = array(
					array(
						'label'   => __( 'Mark as handled', 'wp-easycart' ),
						'onclick' => 'ecodv2_withdrawal_handled( this, ' . (int) $record->withdrawal_id . ' ); return false;',
						'primary' => true,
					),
				);
				if ( class_exists( 'wp_easycart_admin_details_orders_pro_v2' ) ) {
					$actions[] = array(
						'label'   => __( 'Refund', 'wp-easycart' ),
						'onclick' => 'ecodv2_withdrawal_refund(); return false;',
					);
				}
				if ( isset( $order->subscription_id ) && (int) $order->subscription_id > 0 ) {
					$actions[] = array(
						'label'   => __( 'Open the subscription', 'wp-easycart' ),
						'onclick' => 'window.location.href = ' . wp_json_encode( admin_url( 'admin.php?page=wp-easycart-orders&subpage=subscriptions&subscription_id=' . (int) $order->subscription_id . '&ec_admin_form_action=edit' ) ) . '; return false;',
					);
				}
				$flags[] = array(
					'kind'    => 'withdrawal',
					'title'   => __( 'Withdrawal received', 'wp-easycart' ),
					'text'    => $text,
					'tone'    => 'yellow',
					'icon'    => 'undo',
					'review'  => false,
					'actions' => $actions,
				);
			}
			return $flags;
		}

		/** AJAX ecv2_order_withdrawal_handled: the notice's Mark as handled. Answers the order screen's refresh data. */
		public static function ajax_handled() {
			ecv2_order_screen_guard();
			global $wpdb;
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ecv2_order_screen_guard() above.
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$id       = isset( $_POST['withdrawal_id'] ) ? absint( $_POST['withdrawal_id'] ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$record = self::engine() ? wp_easycart_withdrawals::get( $id ) : null;
			if ( ! $record || (int) $record->order_id !== $order_id ) {
				wp_send_json_error( array( 'message' => __( 'This withdrawal is not on this order. Reload the page.', 'wp-easycart' ) ) );
			}
			$changed          = wp_easycart_withdrawals::mark_handled( $id, get_current_user_id() );
			$order            = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			$reply            = ( $order && class_exists( 'wp_easycart_admin_order_screen' ) ) ? wp_easycart_admin_order_screen::refresh_reply( $order ) : array();
			$reply['message'] = $changed ? __( 'Marked as handled.', 'wp-easycart' ) : __( 'Nothing to change: it was already handled.', 'wp-easycart' );
			$reply['changed'] = $changed;
			wp_send_json_success( $reply );
		}

		/**
		 * Filter wp_easycart_admin_order_history_entry ( WP EasyCart PRO's order timeline ).
		 *
		 * @param array|null $entry Entry from another listener.
		 * @param string     $key   Log key.
		 * @param array      $meta  Log meta.
		 * @return array|null
		 */
		public static function history_entry( $entry, $key = '', $meta = array() ) {
			if ( null !== $entry || ! class_exists( 'wp_easycart_withdrawals' ) || ! in_array( (string) $key, array( wp_easycart_withdrawals::LOG_REQUESTED, wp_easycart_withdrawals::LOG_HANDLED ), true ) ) {
				return $entry;
			}
			$meta  = is_array( $meta ) ? $meta : array();
			$parts = array();
			if ( wp_easycart_withdrawals::LOG_REQUESTED === $key ) {
				$title = __( 'Withdrawal received', 'wp-easycart' );
				if ( isset( $meta['items'] ) ) {
					/* translators: %d: number of items. */
					$parts[] = esc_html( 'all' === $meta['items'] ? __( 'The whole order', 'wp-easycart' ) : sprintf( _n( '%d item', '%d items', (int) $meta['items'], 'wp-easycart' ), (int) $meta['items'] ) );
				}
				if ( isset( $meta['in_time'] ) && '' !== (string) $meta['in_time'] ) {
					$parts[] = esc_html( '1' === (string) $meta['in_time'] ? __( 'Within the withdrawal period', 'wp-easycart' ) : __( 'No withdrawal period open as the order stood', 'wp-easycart' ) );
				}
				if ( isset( $meta['name'] ) && '' !== trim( (string) $meta['name'] ) ) {
					/* translators: %s: customer name. */
					$parts[] = esc_html( sprintf( __( 'From %s', 'wp-easycart' ), (string) $meta['name'] ) );
				}
				$sources = array(
					'account' => __( 'From the customer’s account', 'wp-easycart' ),
					'link'    => __( 'From the order’s link', 'wp-easycart' ),
					'page'    => __( 'Typed on the withdrawal page', 'wp-easycart' ),
					'admin'   => __( 'Added by the store', 'wp-easycart' ),
				);
				if ( isset( $meta['source'], $sources[ $meta['source'] ] ) ) {
					$parts[] = esc_html( $sources[ $meta['source'] ] );
				}
			} else {
				$title = __( 'Withdrawal marked handled', 'wp-easycart' );
				$user  = ( isset( $meta['user_id'] ) && (int) $meta['user_id'] > 0 ) ? get_userdata( (int) $meta['user_id'] ) : false;
				if ( $user ) {
					/* translators: %s: staff member. */
					$parts[] = esc_html( sprintf( __( 'By %s', 'wp-easycart' ), $user->display_name ) );
				}
			}
			return array(
				'title'    => $title,
				'subtitle' => implode( ' · ', $parts ),
				'icon'     => 'dashicons-undo',
				'category' => 'notes',
			);
		}

		/**
		 * Filter wp_easycart_admin_order_timeline_labels ( the orders list's row timeline in WP EasyCart PRO ).
		 *
		 * @param array $labels key => label.
		 * @return array
		 */
		public static function timeline_labels( $labels ) {
			$labels = is_array( $labels ) ? $labels : array();
			if ( class_exists( 'wp_easycart_withdrawals' ) ) {
				$labels[ wp_easycart_withdrawals::LOG_REQUESTED ] = __( 'Withdrawal received', 'wp-easycart' );
				$labels[ wp_easycart_withdrawals::LOG_HANDLED ]   = __( 'Withdrawal marked handled', 'wp-easycart' );
			}
			return $labels;
		}

		// The orders list.

		/**
		 * Filter wp_easycart_admin_order_list_filters: Withdrawals ( waiting, or any ), once the button is on or a statement exists.
		 *
		 * @param array $filters Filters.
		 * @return array
		 */
		public static function list_filter( $filters ) {
			if ( ! self::engine() || ( ! wp_easycart_withdrawals::enabled() && ! wp_easycart_withdrawals::has_any() ) ) {
				return $filters;
			}
			$filters[] = array(
				'data'  => array(
					(object) array(
						'value' => 'open',
						'label' => __( 'Waiting', 'wp-easycart' ),
						'icon'  => 'clock',
					),
					(object) array(
						'value' => 'all',
						'label' => __( 'Any', 'wp-easycart' ),
						'icon'  => 'list',
					),
				),
				'label' => __( 'Withdrawals', 'wp-easycart' ),
				'type'  => 'pills',
				'where' => "EXISTS ( SELECT 1 FROM ec_order_withdrawal AS wdf WHERE wdf.order_id = ec_order.order_id AND wdf.withdrawal_status != 'dismissed' AND ( %s = 'all' OR wdf.withdrawal_status = 'received' ) )",
			);
			return $filters;
		}

		/**
		 * Filter wp_easycart_ecv2_order_health_stats: a Withdrawals tile while statements wait.
		 *
		 * @param array $stats Tiles.
		 * @return array
		 */
		public static function health_tile( $stats ) {
			$count = self::engine() ? wp_easycart_withdrawals::open_count() : 0;
			if ( $count > 0 ) {
				$stats[] = array(
					'label'        => __( 'Withdrawals', 'wp-easycart' ),
					'value'        => $count,
					'filter_value' => self::HEALTH,
					'color'        => 'amber',
					'group'        => 'flow',
				);
			}
			return $stats;
		}

		/**
		 * Filter wp_easycart_ecv2_order_health_filter_where: the tile's orders.
		 *
		 * @param string $where Clause from another listener.
		 * @param string $key   Health filter.
		 * @return string
		 */
		public static function health_where( $where, $key = '' ) {
			if ( self::HEALTH !== $key || ! self::engine() ) {
				return $where;
			}
			return "EXISTS ( SELECT 1 FROM ec_order_withdrawal AS wdh WHERE wdh.order_id = ec_order.order_id AND wdh.withdrawal_status = 'received' )";
		}

		// The update notice and Store Status.

		/** On EasyCart screens, once: the 6.0.4 update switched the button on for this EU or EEA store. */
		public static function switched_on_notice() {
			if ( '1' !== (string) get_option( 'ec_option_withdrawal_switched_on', '' ) || ! current_user_can( 'manage_options' ) || ! class_exists( 'wp_easycart_withdrawals' ) || ! wp_easycart_withdrawals::enabled() ) {
				return;
			}
			$done   = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::NOTICE_ACTION ), self::NOTICE_ACTION );
			$notice = wp_easycart_admin::notice_html(
				'info',
				__( 'The EU withdrawal button is on', 'wp-easycart' ),
				array(
					'detail'      => __( 'Since 19 June 2026, shoppers in the EU must be able to withdraw from an order online. Your store is in the EU or the EEA, so this update switched on the “Withdraw from contract here” button: on the order confirmation page, in My Account, in the receipt and shipped emails and at the foot of every page. Check the settings, create the withdrawal page and add its sentence to your terms.', 'wp-easycart' ),
					'dismissible' => false,
					'actions'     => array(
						array(
							'label'   => __( 'Review the settings', 'wp-easycart' ),
							'url'     => admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout#withdrawal' ),
							'primary' => true,
						),
						array(
							'label' => __( 'Got it', 'wp-easycart' ),
							'url'   => $done,
						),
					),
				)
			);
			echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes every part.
		}

		/** The admin-post handler of the notice's Got it. */
		public static function dismiss_notice() {
			if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NOTICE_ACTION ) ) {
				wp_die( esc_html__( 'This link has expired. Go back and try again.', 'wp-easycart' ) );
			}
			delete_option( 'ec_option_withdrawal_switched_on' );
			$back = wp_get_referer();
			wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout' ) );
			exit;
		}

		/** Settings › Store Status rows. */
		public static function store_status_rows() {
			if ( ! class_exists( 'wp_easycart_withdrawals' ) ) {
				return;
			}
			$url  = admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout#withdrawal' );
			$rows = array();
			if ( ! wp_easycart_withdrawals::ready() ) {
				$rows[] = array( 'warning', __( 'The withdrawal button needs the 6.0.4 database update. Reload this page to run it.', 'wp-easycart' ) );
			} elseif ( wp_easycart_withdrawals::enabled() ) {
				if ( wp_easycart_withdrawals::page_id() > 0 ) {
					$rows[] = array( 'success', __( 'The EU withdrawal button is on and has its own page.', 'wp-easycart' ) );
				} else {
					$rows[] = array( 'warning', __( 'The EU withdrawal button is on, but it has no page of its own: the account page shows the form. Create the withdrawal page so shoppers can find it from your menu or footer.', 'wp-easycart' ) );
				}
				$open = wp_easycart_withdrawals::open_count();
				if ( $open > 0 ) {
					/* translators: %d: number of orders. */
					$rows[] = array( 'warning', sprintf( _n( '%d order has a withdrawal waiting to be handled.', '%d orders have a withdrawal waiting to be handled.', $open, 'wp-easycart' ), $open ) );
				}
			} elseif ( wp_easycart_withdrawals::store_in_eu() ) {
				$rows[] = array( 'warning', __( 'Your store is in the EU or the EEA, but the EU withdrawal button is switched off. EU law has required it since 19 June 2026.', 'wp-easycart' ) );
			}
			foreach ( $rows as $row ) {
				$icon = ( 'success' === $row[0] ) ? 'dashicons-yes' : 'dashicons-warning';
				echo '<div class="ec_status_' . esc_attr( $row[0] ) . '"><div class="dashicons-before ' . esc_attr( $icon ) . '"></div><span class="ec_status_label">' . esc_html( $row[1] ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Settings › Checkout', 'wp-easycart' ) . '</a></span></div>';
			}
		}
	}

	wp_easycart_admin_withdrawals::init();

endif;
