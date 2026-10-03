<?php
/**
 * Checkout field answers on an order ( 6.0.2 ).
 *
 * WP EasyCart PRO adds the store's own questions to both checkouts ( Settings › Checkout fields ) and keeps each answer
 * with the order in ec_order_field, with the field's label, type and "show on" choices as they were when the order was
 * placed. This is the part WP EasyCart ships itself, so the answers keep showing wherever the order goes, with or without
 * PRO: the order screen, the receipt and shipped emails, the packing slip, the invoice PDF and printed receipt, My Account,
 * the thank-you page, the order export and the WordPress personal data tools. It also moves a checkout session's answers
 * ( ec_tempcart_field ) with the session when it is renamed, and clears out old ones.
 *
 * Nothing here asks a question or saves an answer: that is WP EasyCart PRO.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_fields' ) ) :

	/**
	 * Answers kept on orders, and where they show.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_order_fields {

		/** Days a checkout session's answers are kept ( the session itself lasts 18 days at most ). */
		const SESSION_DAYS = 18;

		/**
		 * Answers read in this request, by order.
		 *
		 * @var array
		 */
		private static $cache = array();

		/** Register hooks. */
		public static function init() {
			add_action( 'wpeasycart_session_rotated', array( __CLASS__, 'session_rotated' ), 10, 2 );
			add_action( 'wpeasycart_success_page_content_middle', array( __CLASS__, 'success_page' ), 5, 2 );
			add_action( 'wpeasycart_order_details_after_basic_info', array( __CLASS__, 'account_rows' ), 20 );
			add_action( 'wp_easycart_admin_order_details_after_customer_notes', array( __CLASS__, 'admin_card' ) );
			add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
			add_action( 'wp_easycart_checkout_fields_order_updated', array( __CLASS__, 'fill_invoice_answers' ) );
		}

		/**
		 * Where an answer can show, besides the order screen and the export ( which always have every answer ). A field's
		 * choices are kept on each order as a comma list of these keys ( ec_order_field.show_on ).
		 *
		 * @return array key => label
		 */
		public static function surfaces() {
			return array(
				'thank_you'     => __( 'Thank-you page', 'wp-easycart' ),
				'receipt'       => __( 'Order receipt email', 'wp-easycart' ),
				'shipped'       => __( 'Order shipped email', 'wp-easycart' ),
				'account'       => __( 'My Account order', 'wp-easycart' ),
				'invoice'       => __( 'Invoice PDF and printed receipt', 'wp-easycart' ),
				'receipt_admin' => __( 'Admin receipt email', 'wp-easycart' ),
				'packing_slip'  => __( 'Packing slip', 'wp-easycart' ),
			);
		}

		/**
		 * Is ec_order_field there ( the 6.0.2 database upgrade has run )? Read once per request.
		 *
		 * @return bool
		 */
		public static function ready() {
			global $wpdb;
			static $ready = null;
			if ( null === $ready ) {
				$ready = ( 'ec_order_field' === $wpdb->get_var( "SHOW TABLES LIKE 'ec_order_field'" ) );
			}
			return $ready;
		}

		/**
		 * Is ec_tempcart_field there? Read once per request.
		 *
		 * @return bool
		 */
		public static function sessions_ready() {
			global $wpdb;
			static $ready = null;
			if ( null === $ready ) {
				$ready = ( 'ec_tempcart_field' === $wpdb->get_var( "SHOW TABLES LIKE 'ec_tempcart_field'" ) );
			}
			return $ready;
		}

		/**
		 * Every answer kept on an order, in the order the checkout asked them.
		 *
		 * @param int  $order_id Order.
		 * @param bool $refresh  Read again ( after a change in this request ).
		 * @return object[] ec_order_field rows.
		 */
		public static function for_order( $order_id, $refresh = false ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() ) {
				return array();
			}
			if ( $refresh || ! isset( self::$cache[ $order_id ] ) ) {
				self::$cache[ $order_id ] = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_order_field WHERE order_id = %d ORDER BY sort_order ASC, order_field_id ASC', $order_id ) );
			}
			return self::$cache[ $order_id ];
		}

		/**
		 * Forget what was read ( after answers were written in this request ).
		 *
		 * @param int $order_id Order, or 0 for every order.
		 */
		public static function forget( $order_id = 0 ) {
			if ( (int) $order_id > 0 ) {
				unset( self::$cache[ (int) $order_id ] );
			} else {
				self::$cache = array();
			}
		}

		/**
		 * The answers to show in one place.
		 *
		 * @param int|object $source  Order id, an order ( any object with order_id ), or a wp_easycart_document ( an issued
		 *                            invoice's snapshot is used when it has the answers ).
		 * @param string     $surface A key of surfaces(), or 'admin' / 'export' for every answer.
		 * @return array[] Each: id, key, label, value ( plain text ), type, status ( '' or 'pending' ), personal, show_on.
		 */
		public static function rows( $source, $surface ) {
			$stored   = null;
			$order_id = 0;
			if ( $source instanceof wp_easycart_document ) {
				$order_id = ( isset( $source->order ) && is_object( $source->order ) && isset( $source->order->order_id ) ) ? (int) $source->order->order_id : 0;
				if ( is_array( $source->snapshot ) && isset( $source->snapshot['checkout_fields'] ) ) {
					$stored = (array) $source->snapshot['checkout_fields'];
				}
			} elseif ( is_object( $source ) && isset( $source->order_id ) ) {
				$order_id = (int) $source->order_id;
			} else {
				$order_id = (int) $source;
			}
			if ( null === $stored ) {
				$stored = self::for_order( $order_id );
			}
			$every = in_array( $surface, array( 'admin', 'export' ), true );
			$rows  = array();
			foreach ( $stored as $field ) {
				$field   = (object) $field;
				$show_on = array_values( array_filter( array_map( 'trim', explode( ',', isset( $field->show_on ) ? (string) $field->show_on : '' ) ) ) );
				if ( ! $every && ! in_array( $surface, $show_on, true ) ) {
					continue;
				}
				$status = isset( $field->field_status ) ? (string) $field->field_status : '';
				$value  = ( 'pending' === $status ) ? '' : self::answer_text( isset( $field->field_type ) ? $field->field_type : '', isset( $field->field_value ) ? $field->field_value : '', isset( $field->display_value ) ? $field->display_value : '' );
				if ( ! $every && ( 'pending' === $status || '' === trim( $value ) ) ) {
					continue;
				}
				$rows[] = array(
					'id'       => isset( $field->order_field_id ) ? (int) $field->order_field_id : 0,
					'key'      => isset( $field->field_key ) ? (string) $field->field_key : '',
					'label'    => isset( $field->field_label ) ? self::translate( $field->field_label ) : '',
					'value'    => $value,
					'type'     => isset( $field->field_type ) ? (string) $field->field_type : '',
					'status'   => $status,
					'personal' => ! empty( $field->is_personal ),
					'show_on'  => $show_on,
				);
			}
			/**
			 * The checkout field answers shown in one place.
			 *
			 * @since 6.0.2
			 * @param array[]    $rows     See rows().
			 * @param int        $order_id Order.
			 * @param string     $surface  Where they show.
			 * @param int|object $source   What was asked for.
			 */
			return (array) apply_filters( 'wp_easycart_order_field_rows', $rows, $order_id, $surface, $source );
		}

		/**
		 * A merchant's wording in the language of this request ( 6.0.2 ). WP EasyCart PRO keeps a field's label and its
		 * choices on the order with their language tags ( [EN]…[/EN][FR]…[/FR], as product text ), so the order screen reads
		 * in the merchant's language and the customer's email in the customer's. A text with no part in this language shows
		 * its first part; answers kept before 6.0.2 hold plain text and read as they are.
		 *
		 * @param string $text Text.
		 * @return string Plain text.
		 */
		public static function translate( $text ) {
			$text = (string) $text;
			if ( ! preg_match( '/\[[a-zA-Z]{2}\]/', $text ) ) {
				return $text;
			}
			if ( function_exists( 'wp_easycart_language' ) ) {
				$text = html_entity_decode( wp_strip_all_tags( (string) wp_easycart_language()->convert_text( $text ) ), ENT_QUOTES, 'UTF-8' );
			}
			if ( preg_match( '/\[([a-zA-Z]{2})\](.*?)\[\/\1\]/s', $text, $part ) ) {
				return trim( $part[2] );
			}
			return trim( $text );
		}

		/**
		 * An answer as it reads in this request's language ( 6.0.2 ). A checkbox, a consent and a date read from the answer
		 * itself; a choice from its label, kept with its language tags ( several choices as a JSON list ); anything else is
		 * the shopper's own text.
		 *
		 * @param string $type    Field type.
		 * @param string $value   The answer ( ec_order_field.field_value ).
		 * @param string $display Its reading as kept ( ec_order_field.display_value ).
		 * @return string Plain text.
		 */
		public static function answer_text( $type, $value, $display ) {
			$type    = (string) $type;
			$value   = (string) $value;
			$display = (string) $display;
			$erased  = function_exists( 'wp_privacy_anonymize_data' ) ? wp_privacy_anonymize_data( 'text' ) : '[deleted]';
			if ( '' !== $display && $erased === $display ) {
				return $display; /* Tools › Erase Personal Data */
			}
			switch ( $type ) {
				case 'checkbox':
					return ( '1' === $value ) ? self::phrase( 'yes', __( 'Yes', 'wp-easycart' ) ) : self::phrase( 'no', __( 'No', 'wp-easycart' ) );
				case 'consent':
					return ( '' !== $value && '0' !== $value ) ? self::phrase( 'accepted', __( 'Accepted', 'wp-easycart' ) ) : '';
				case 'date':
					if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
						return date_i18n( get_option( 'date_format' ), strtotime( $value . ' 12:00:00' ) );
					}
					break;
				case 'select':
				case 'radio':
					return self::translate( '' !== $display ? $display : $value );
				case 'checkboxes':
					$list = ( '' !== $display && '[' === $display[0] ) ? json_decode( $display, true ) : null;
					if ( is_array( $list ) ) {
						return implode( ', ', array_filter( array_map( array( __CLASS__, 'translate' ), array_map( 'strval', $list ) ), 'strlen' ) );
					}
					return self::translate( $display );
			}
			return ( '' !== $display ) ? $display : $value;
		}

		/**
		 * A remembered answer as it reads ( 6.0.2: the account's answers keep the raw answer, a choice's value or a JSON list ).
		 *
		 * @param string $type     Field type.
		 * @param mixed  $settings The field's settings ( ec_checkout_field.settings, JSON or array ).
		 * @param string $value    The answer.
		 * @return string Plain text.
		 */
		public static function remembered_text( $type, $settings, $value ) {
			$type     = (string) $type;
			$value    = (string) $value;
			$settings = is_array( $settings ) ? $settings : json_decode( (string) $settings, true );
			$labels   = array();
			if ( is_array( $settings ) && isset( $settings['options'] ) && is_array( $settings['options'] ) ) {
				foreach ( $settings['options'] as $option ) {
					if ( is_array( $option ) && isset( $option['label'] ) ) {
						$option_value            = ( isset( $option['value'] ) && '' !== trim( (string) $option['value'] ) ) ? (string) $option['value'] : (string) $option['label'];
						$labels[ $option_value ] = trim( html_entity_decode( wp_strip_all_tags( (string) $option['label'] ), ENT_QUOTES, 'UTF-8' ) );
					}
				}
			}
			$display = '';
			if ( in_array( $type, array( 'select', 'radio' ), true ) ) {
				$display = isset( $labels[ $value ] ) ? $labels[ $value ] : $value;
			} elseif ( 'checkboxes' === $type ) {
				$chosen  = json_decode( $value, true );
				$display = array();
				foreach ( is_array( $chosen ) ? $chosen : array() as $one ) {
					$display[] = isset( $labels[ (string) $one ] ) ? $labels[ (string) $one ] : (string) $one;
				}
				$display = wp_json_encode( $display );
			}
			return self::answer_text( $type, $value, $display );
		}

		/**
		 * A checkout phrase ( Settings › Languages › Checkout Fields ).
		 *
		 * @param string $key      Phrase.
		 * @param string $fallback English.
		 * @return string Plain text.
		 */
		private static function phrase( $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'checkout_fields', $key ) : '';
			return ( null === $text || '' === trim( (string) $text ) ) ? $fallback : trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) );
		}

		/**
		 * The heading above the answers ( Settings › Languages › Checkout Fields ).
		 *
		 * @return string Safe HTML.
		 */
		public static function title() {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'checkout_fields', 'details_title' ) : '';
			return ( null === $text || '' === trim( (string) $text ) ) ? esc_html__( 'Additional details', 'wp-easycart' ) : wp_kses_post( $text );
		}

		/**
		 * An answer as HTML ( line breaks kept ).
		 *
		 * @param string $value Plain text.
		 * @return string
		 */
		public static function value_html( $value ) {
			return nl2br( esc_html( (string) $value ) );
		}

		/**
		 * The answers as a section of an email-design document ( receipt, shipped email, packing slip, invoice PDF, printed
		 * receipt ): a label and a card with one answer under the other.
		 *
		 * @param int|object $source  See rows().
		 * @param string     $surface Where they show.
		 */
		public static function print_email_section( $source, $surface ) {
			if ( ! class_exists( 'wp_easycart_email_design' ) ) {
				return;
			}
			$rows = self::rows( $source, $surface );
			if ( ! $rows ) {
				return;
			}
			$ed    = 'wp_easycart_email_design';
			$label = $ed::css( 'label' );
			$text  = $ed::css( 'text' );
			$ed::section_start();
			$ed::label( self::title() );
			$ed::card_start( array( 'padding' => '10px 16px' ) );
			foreach ( $rows as $i => $row ) {
				echo '<div style="' . esc_attr( $label . 'margin:' . ( $i ? '10px' : '0' ) . ' 0 2px 0;' ) . '">' . esc_html( $row['label'] ) . '</div>';
				echo '<div dir="auto" style="' . esc_attr( $text . 'margin:0;word-wrap:break-word;overflow-wrap:anywhere;' ) . '">' . self::value_html( $row['value'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value_html() escapes.
			}
			$ed::card_end();
			$ed::section_end();
		}

		/**
		 * The answers for an issued invoice's snapshot ( frozen with the invoice, as the order's lines are ).
		 *
		 * @param int $order_id Order.
		 * @return object[]
		 */
		public static function snapshot_rows( $order_id ) {
			$out = array();
			foreach ( self::for_order( (int) $order_id, true ) as $field ) {
				$out[] = (object) array(
					'order_field_id' => (int) $field->order_field_id,
					'field_key'      => (string) $field->field_key,
					'field_type'     => (string) $field->field_type,
					'field_label'    => (string) $field->field_label,
					'field_value'    => (string) $field->field_value,
					'display_value'  => (string) $field->display_value,
					'show_on'        => (string) $field->show_on,
					'field_status'   => (string) $field->field_status,
					'is_personal'    => (int) $field->is_personal,
				);
			}
			return $out;
		}

		/**
		 * Action wp_easycart_checkout_fields_order_updated ( 6.0.2 ): an issued invoice or credit note keeps the answers it was
		 * issued with, but an answer that was still to come then ( asked after payment ) is added to it when it arrives, so
		 * the Invoice PDF has it. Answers it already had are never rewritten.
		 *
		 * @param int $order_id Order.
		 * @return int Invoices changed.
		 */
		public static function fill_invoice_answers( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! self::ready() || ! class_exists( 'wp_easycart_documents' ) || ! method_exists( 'wp_easycart_documents', 'invoices_ready' ) || ! wp_easycart_documents::invoices_ready() ) {
				return 0;
			}
			$answered = array();
			foreach ( self::for_order( $order_id, true ) as $row ) {
				if ( '' === (string) $row->field_status && '' !== (string) $row->field_value ) {
					$answered[ (string) $row->field_key ] = $row;
				}
			}
			if ( ! $answered ) {
				return 0;
			}
			$changed_count = 0;
			$invoices      = (array) $wpdb->get_results( $wpdb->prepare( "SELECT invoice_id, snapshot FROM ec_invoice WHERE order_id = %d AND snapshot LIKE %s", $order_id, '%' . $wpdb->esc_like( 'checkout_fields' ) . '%' ) );
			foreach ( $invoices as $invoice ) {
				/* Decoded as objects, so the rest of the snapshot is written back exactly as it was ( {} stays {} ). */
				$snapshot = json_decode( (string) $invoice->snapshot );
				if ( ! is_object( $snapshot ) || empty( $snapshot->checkout_fields ) || ! is_array( $snapshot->checkout_fields ) ) {
					continue;
				}
				$changed = false;
				foreach ( $snapshot->checkout_fields as $field ) {
					if ( ! is_object( $field ) || ! isset( $field->field_key, $field->field_status ) || 'pending' !== (string) $field->field_status || ! isset( $answered[ (string) $field->field_key ] ) ) {
						continue;
					}
					$row                  = $answered[ (string) $field->field_key ];
					$field->field_value   = (string) $row->field_value;
					$field->display_value = (string) $row->display_value;
					$field->field_status  = '';
					$changed              = true;
				}
				if ( ! $changed ) {
					continue;
				}
				$json = wp_json_encode( $snapshot );
				if ( $json && false !== $wpdb->update( 'ec_invoice', array( 'snapshot' => $json ), array( 'invoice_id' => (int) $invoice->invoice_id ), array( '%s' ), array( '%d' ) ) ) {
					++$changed_count;
				}
			}
			return $changed_count;
		}

		/*
		------------------------------------------------------------------ */
		/*
		Storefront                                                          */
		/* ------------------------------------------------------------------ */

		/**
		 * Action wpeasycart_success_page_content_middle: the answers on the thank-you page.
		 *
		 * @param int    $order_id Order.
		 * @param object $order    ec_orderdisplay.
		 */
		public static function success_page( $order_id, $order = null ) {
			$rows = self::rows( (int) $order_id, 'thank_you' );
			if ( ! $rows ) {
				return;
			}
			echo '<div class="ec_cart_success_checkout_fields"><div class="ec_cart_header">' . self::title() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- title() escapes.
			foreach ( $rows as $row ) {
				echo '<div class="ec_cart_input_row ec_cart_success_checkout_field"><strong>' . esc_html( $row['label'] ) . ':</strong> ' . self::value_html( $row['value'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value_html() escapes.
			}
			echo '</div>';
		}

		/**
		 * Action wpeasycart_order_details_after_basic_info: the answers on the order's page in My Account.
		 *
		 * @param object $order ec_orderdisplay.
		 */
		public static function account_rows( $order ) {
			if ( ! is_object( $order ) || empty( $order->order_id ) ) {
				return;
			}
			foreach ( self::rows( (int) $order->order_id, 'account' ) as $row ) {
				echo '<div class="ec_cart_input_row ec_account_checkout_field"><strong>' . esc_html( $row['label'] ) . ':</strong> ' . self::value_html( $row['value'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value_html() escapes.
			}
		}

		/*
		------------------------------------------------------------------ */
		/*
		Admin                                                               */
		/* ------------------------------------------------------------------ */

		/**
		 * Action wp_easycart_admin_order_details_after_customer_notes: the Checkout fields card on the order screen. It shows
		 * when the order has answers ( or when WP EasyCart PRO asks for it, to add answers ).
		 *
		 * @param object $order Order row.
		 */
		public static function admin_card( $order ) {
			if ( ! is_object( $order ) || empty( $order->order_id ) ) {
				return;
			}
			$rows = self::rows( (int) $order->order_id, 'admin' );
			/**
			 * Show the Checkout fields card on an order without answers.
			 *
			 * @since 6.0.2
			 * @param bool   $show  Show it.
			 * @param object $order Order row.
			 */
			if ( ! $rows && ! apply_filters( 'wp_easycart_admin_order_fields_card_always', false, $order ) ) {
				return;
			}
			?>
			<div class="ecdv2-card ecodv2-card-checkout-fields" id="ecodv2_checkout_fields_card" data-order-id="<?php echo (int) $order->order_id; ?>">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php esc_html_e( 'Checkout fields', 'wp-easycart' ); ?></h3>
					<span class="ecdv2-card-hint"><?php esc_html_e( 'Answered at checkout', 'wp-easycart' ); ?></span>
					<div class="ecdv2-card-header-actions"><?php do_action( 'wp_easycart_admin_order_fields_card_actions', $order, $rows ); ?></div>
				</div>
				<div class="ecdv2-card-body">
					<dl class="ecodv2-fields" id="ecodv2_checkout_fields">
						<?php foreach ( $rows as $row ) : ?>
						<div class="ecodv2-field" data-key="<?php echo esc_attr( $row['key'] ); ?>" data-id="<?php echo (int) $row['id']; ?>">
							<dt><?php echo esc_html( $row['label'] ); ?></dt>
							<?php if ( 'pending' === $row['status'] && '' === trim( $row['value'] ) ) : ?>
							<dd class="ecodv2-field-pending"><?php esc_html_e( 'Waiting for the customer', 'wp-easycart' ); ?></dd>
							<?php elseif ( 'consent' === $row['type'] && '' === trim( $row['value'] ) ) : /* 6.0.2: as the editor says it, rather than a dash */ ?>
							<dd class="ecodv2-field-empty"><?php esc_html_e( 'Not accepted', 'wp-easycart' ); ?></dd>
							<?php elseif ( '' === trim( $row['value'] ) ) : ?>
							<dd class="ecodv2-field-empty">&mdash;</dd>
							<?php else : ?>
							<dd><?php echo self::value_html( $row['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value_html() escapes. ?></dd>
							<?php endif; ?>
						</div>
						<?php endforeach; ?>
					</dl>
					<?php if ( ! $rows ) : ?>
					<p class="ecodv2-fields-none"><?php esc_html_e( 'No answers on this order.', 'wp-easycart' ); ?></p>
					<?php endif; ?>
					<?php do_action( 'wp_easycart_admin_order_fields_card_end', $order, $rows ); ?>
				</div>
			</div>
			<?php
		}

		/**
		 * CSV export columns: one per checkout field that any order has an answer for.
		 *
		 * @return array field key => column name
		 */
		public static function export_columns() {
			global $wpdb;
			if ( ! self::ready() ) {
				return array();
			}
			$out = array();
			foreach ( (array) $wpdb->get_results( 'SELECT field_key, MIN( sort_order ) AS first_sort FROM ec_order_field WHERE orderdetail_id = 0 GROUP BY field_key ORDER BY first_sort ASC, field_key ASC' ) as $row ) {
				$out[ (string) $row->field_key ] = 'field_' . (string) $row->field_key;
			}
			/**
			 * The order export's checkout field columns.
			 *
			 * @since 6.0.2
			 * @param array $out field key => column name.
			 */
			return (array) apply_filters( 'wp_easycart_order_field_export_columns', $out );
		}

		/**
		 * CSV export values for a chunk of orders.
		 *
		 * @param int[] $order_ids Orders.
		 * @param array $columns   From export_columns().
		 * @return array order id => array( column => value )
		 */
		public static function export_values( $order_ids, $columns ) {
			global $wpdb;
			$order_ids = array_values( array_filter( array_map( 'intval', (array) $order_ids ) ) );
			if ( ! $order_ids || ! $columns || ! self::ready() ) {
				return array();
			}
			$out  = array();
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT order_id, field_key, field_type, field_status, field_value, display_value FROM ec_order_field WHERE orderdetail_id = 0 AND order_id IN ( ' . implode( ', ', array_fill( 0, count( $order_ids ), '%d' ) ) . ' )', $order_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- one %d placeholder per order id.
			foreach ( (array) $rows as $row ) {
				if ( ! isset( $columns[ $row->field_key ] ) ) {
					continue;
				}
				$value = ( 'pending' === (string) $row->field_status ) ? '' : self::answer_text( $row->field_type, $row->field_value, $row->display_value );
				$out[ (int) $row->order_id ][ $columns[ $row->field_key ] ] = self::csv_safe( $value );
			}
			return $out;
		}

		/**
		 * A shopper's answer, safe to open in a spreadsheet ( a leading = + - @ would run as a formula ).
		 *
		 * @param string $value Answer.
		 * @return string
		 */
		public static function csv_safe( $value ) {
			$value = (string) $value;
			if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				$value = "'" . $value;
			}
			return $value;
		}

		/*
		------------------------------------------------------------------ */
		/*
		Checkout sessions                                                   */
		/* ------------------------------------------------------------------ */

		/**
		 * Action wpeasycart_session_rotated: the answers stay with the renamed session.
		 *
		 * @param string $old_id Old session id.
		 * @param string $new_id New session id.
		 */
		public static function session_rotated( $old_id, $new_id ) {
			global $wpdb;
			if ( '' === (string) $old_id || '' === (string) $new_id || ! self::sessions_ready() ) {
				return;
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_tempcart_field SET session_id = %s WHERE session_id = %s', (string) $new_id, (string) $old_id ) );
		}

		/** Remove answers of checkout sessions that ended long ago ( at most once a day ). */
		public static function maybe_cleanup() {
			global $wpdb;
			if ( get_transient( 'wpec_checkout_fields_cleanup' ) || ! self::sessions_ready() ) {
				return;
			}
			set_transient( 'wpec_checkout_fields_cleanup', 1, DAY_IN_SECONDS );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_tempcart_field WHERE modified_date IS NULL OR modified_date < %s', gmdate( 'Y-m-d H:i:s', time() - ( self::SESSION_DAYS * DAY_IN_SECONDS ) ) ) );
		}

		/*
		------------------------------------------------------------------ */
		/*
		Personal data ( Tools › Export / Erase Personal Data )              */
		/* ------------------------------------------------------------------ */

		/**
		 * Filter wp_privacy_personal_data_exporters.
		 *
		 * @param array $exporters Exporters.
		 * @return array
		 */
		public static function register_exporter( $exporters ) {
			$exporters['wp-easycart-checkout-fields'] = array(
				'exporter_friendly_name' => __( 'WP EasyCart checkout fields', 'wp-easycart' ),
				'callback'               => array( __CLASS__, 'export_personal_data' ),
			);
			return $exporters;
		}

		/**
		 * Filter wp_privacy_personal_data_erasers.
		 *
		 * @param array $erasers Erasers.
		 * @return array
		 */
		public static function register_eraser( $erasers ) {
			$erasers['wp-easycart-checkout-fields'] = array(
				'eraser_friendly_name' => __( 'WP EasyCart checkout fields', 'wp-easycart' ),
				'callback'             => array( __CLASS__, 'erase_personal_data' ),
			);
			return $erasers;
		}

		/**
		 * The answers on a person's orders, and the answers remembered on their account.
		 *
		 * @param string $email Email address.
		 * @param int    $page  Page ( 50 orders each ).
		 * @return array
		 */
		public static function export_personal_data( $email, $page = 1 ) {
			global $wpdb;
			$data = array();
			$per  = 50;
			$page = max( 1, (int) $page );
			if ( ! self::ready() || ! is_email( $email ) ) {
				return array(
					'data' => $data,
					'done' => true,
				);
			}
			$orders = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT ec_order_field.order_id FROM ec_order_field INNER JOIN ec_order ON ec_order.order_id = ec_order_field.order_id WHERE ec_order.user_email = %s ORDER BY ec_order_field.order_id ASC LIMIT %d OFFSET %d', $email, $per, ( $page - 1 ) * $per ) );
			foreach ( $orders as $order_id ) {
				$items = array(
					array(
						'name'  => __( 'Order number', 'wp-easycart' ),
						'value' => (int) $order_id,
					),
				);
				foreach ( self::rows( (int) $order_id, 'export' ) as $row ) {
					if ( '' !== trim( $row['value'] ) ) {
						$items[] = array(
							'name'  => $row['label'],
							'value' => $row['value'],
						);
					}
				}
				if ( count( $items ) > 1 ) {
					$data[] = array(
						'group_id'    => 'wp-easycart-checkout-fields',
						'group_label' => __( 'Checkout field answers', 'wp-easycart' ),
						'item_id'     => 'wpec-order-' . (int) $order_id,
						'data'        => $items,
					);
				}
			}
			if ( 1 === $page ) {
				$remembered = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ec_user_field.field_key, ec_user_field.field_value, ec_checkout_field.label, ec_checkout_field.field_type, ec_checkout_field.settings FROM ec_user_field INNER JOIN ec_user ON ec_user.user_id = ec_user_field.user_id LEFT JOIN ec_checkout_field ON ec_checkout_field.field_key = ec_user_field.field_key WHERE ec_user.email = %s', $email ) );
				$items      = array();
				foreach ( $remembered as $row ) {
					/* 6.0.2: as the customer reads it ( a choice's label, Yes / No, a date ), not the stored value. */
					$items[] = array(
						'name'  => ( null !== $row->label && '' !== (string) $row->label ) ? self::translate( html_entity_decode( wp_strip_all_tags( (string) $row->label ), ENT_QUOTES, 'UTF-8' ) ) : (string) $row->field_key,
						'value' => self::remembered_text( (string) $row->field_type, $row->settings, (string) $row->field_value ),
					);
				}
				if ( $items ) {
					$data[] = array(
						'group_id'    => 'wp-easycart-checkout-fields-remembered',
						'group_label' => __( 'Remembered checkout answers', 'wp-easycart' ),
						'item_id'     => 'wpec-remembered-answers',
						'data'        => $items,
					);
				}
			}
			return array(
				'data' => $data,
				'done' => count( $orders ) < $per,
			);
		}

		/**
		 * Remove a person's answers to fields marked as personal data ( 6.0.2: in their issued invoices too ), their
		 * remembered answers, and any answers left in their checkout sessions. Other answers stay with the orders, as the
		 * orders do.
		 *
		 * @param string $email Email address.
		 * @param int    $page  Page ( one pass ).
		 * @return array
		 */
		public static function erase_personal_data( $email, $page = 1 ) {
			global $wpdb;
			$removed = 0;
			if ( self::ready() && is_email( $email ) ) {
				$deleted  = function_exists( 'wp_privacy_anonymize_data' ) ? wp_privacy_anonymize_data( 'text' ) : '[deleted]';
				$removed += (int) $wpdb->query( $wpdb->prepare( "UPDATE ec_order_field INNER JOIN ec_order ON ec_order.order_id = ec_order_field.order_id SET ec_order_field.field_value = '', ec_order_field.display_value = %s WHERE ec_order.user_email = %s AND ec_order_field.is_personal = 1 AND ec_order_field.display_value != %s", $deleted, $email, $deleted ) );
				$removed += self::erase_from_invoices( $email, $deleted );
				$removed += (int) $wpdb->query( $wpdb->prepare( 'DELETE ec_user_field FROM ec_user_field INNER JOIN ec_user ON ec_user.user_id = ec_user_field.user_id WHERE ec_user.email = %s', $email ) );
				if ( self::sessions_ready() ) {
					$removed += (int) $wpdb->query( $wpdb->prepare( 'DELETE ec_tempcart_field FROM ec_tempcart_field INNER JOIN ec_tempcart_data ON ec_tempcart_data.session_id = ec_tempcart_field.session_id WHERE ec_tempcart_data.email = %s', $email ) );
				}
				self::forget();
			}
			return array(
				'items_removed'  => $removed > 0,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		/**
		 * 6.0.2: issued invoices and credit notes keep a copy of the order's answers ( ec_invoice.snapshot, so later edits
		 * never rewrite them ), and the Invoice PDF prints that copy. Answers to fields marked as personal data are blanked
		 * there too, the same way as on the order; everything else in the invoice stays as issued.
		 *
		 * @since 6.0.2
		 * @param string $email   Email address.
		 * @param string $deleted What an erased answer shows.
		 * @return int Invoices changed.
		 */
		private static function erase_from_invoices( $email, $deleted ) {
			global $wpdb;
			if ( ! class_exists( 'wp_easycart_documents' ) || ! method_exists( 'wp_easycart_documents', 'invoices_ready' ) || ! wp_easycart_documents::invoices_ready() ) {
				return 0;
			}
			$changed_count = 0;
			$invoices      = (array) $wpdb->get_results( $wpdb->prepare( "SELECT ec_invoice.invoice_id, ec_invoice.snapshot FROM ec_invoice INNER JOIN ec_order ON ec_order.order_id = ec_invoice.order_id WHERE ec_order.user_email = %s AND ec_invoice.snapshot LIKE %s", $email, '%' . $wpdb->esc_like( 'checkout_fields' ) . '%' ) );
			foreach ( $invoices as $invoice ) {
				/* Decoded as objects, so the rest of the snapshot is written back exactly as it was ( {} stays {} ). */
				$snapshot = json_decode( (string) $invoice->snapshot );
				if ( ! is_object( $snapshot ) || empty( $snapshot->checkout_fields ) || ! is_array( $snapshot->checkout_fields ) ) {
					continue;
				}
				$changed = false;
				foreach ( $snapshot->checkout_fields as $field ) {
					if ( is_object( $field ) && ! empty( $field->is_personal ) && ( ( isset( $field->field_value ) && '' !== (string) $field->field_value ) || ! isset( $field->display_value ) || $deleted !== (string) $field->display_value ) ) {
						$field->field_value   = '';
						$field->display_value = $deleted;
						$changed              = true;
					}
				}
				if ( ! $changed ) {
					continue;
				}
				$json = wp_json_encode( $snapshot );
				if ( $json && false !== $wpdb->update( 'ec_invoice', array( 'snapshot' => $json ), array( 'invoice_id' => (int) $invoice->invoice_id ), array( '%s' ), array( '%d' ) ) ) {
					++$changed_count;
				}
			}
			return $changed_count;
		}
	}

	wp_easycart_order_fields::init();

endif;
