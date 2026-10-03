<?php
/**
 * Gift orders wherever an order shows ( 6.0.2 ).
 *
 * WP EasyCart PRO asks at checkout whether an order is a gift ( Settings › Documents › Gift orders ) and keeps the answer
 * on the order ( ec_order is_gift, gift_message, gift_recipient_email; the same columns on the checkout session ). This is
 * the part WP EasyCart ships itself, so a gift order keeps showing as one wherever it goes, with or without PRO: the order
 * confirmation page, the order in My Account, the receipt and shipped emails ( the profile's Gift details switch ) and the
 * order screen ( a Gift chip in the header and a Gift order card ). It also draws the gift summary WP EasyCart PRO shows at
 * checkout, before Place order, so the shopper sees what is about to happen.
 *
 * Nothing here asks the question or saves an answer: that is WP EasyCart PRO. The message is plain text everywhere
 * ( escaped, line breaks kept by CSS or nl2br() ).
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_order_gift' ) ) :

	/**
	 * Gift details of an order or of the checkout session, and where they show.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_order_gift {

		/** Order log entry WP EasyCart PRO writes when the gift receipt goes to the recipient. */
		const RECEIPT_LOG = 'order-gift-receipt-email';

		/**
		 * The storefront styles went out in this request.
		 *
		 * @var bool
		 */
		private static $styled = false;

		/** Register hooks. */
		public static function init() {
			add_action( 'wpeasycart_success_page_content_middle', array( __CLASS__, 'success_page' ), 4, 2 );
			/* 6.0.2: under the items in My Account ( at the top of the right column it pushed "Your order" out of line with
			   "Order information" ). The hook is in every copy of the template since 5.8.13. */
			add_action( 'wpeasycart_order_detials_order_notes_after', array( __CLASS__, 'account_card_after_items' ), 5 );
			add_action( 'wp_easycart_ecv2_order_details_header_chips', array( __CLASS__, 'admin_chip' ), 5 );
			/* 6.0.2: at the top of the order screen, above Fulfillment, where packing starts ( it sat halfway down the right
			   column, easy to miss; owner bug round 4, item 10 ). */
			add_action( 'wp_easycart_ecv2_order_details_main_top', array( __CLASS__, 'admin_card' ), 5 );
		}

		/* ------------------------------------------------------------------ */

		/**
		 * Can orders carry gift details ( the 6.0.2 database upgrade has run )?
		 *
		 * @return bool
		 */
		public static function ready() {
			return class_exists( 'wp_easycart_documents' ) && method_exists( 'wp_easycart_documents', 'order_extras' ) && wp_easycart_documents::order_columns_ready();
		}

		/**
		 * An order's gift details.
		 *
		 * @param int|object $order Order id, or an order row / ec_orderdisplay ( anything with order_id ).
		 * @return object|null order_id, message, recipient; null when the order is not a gift.
		 */
		public static function for_order( $order ) {
			if ( ! self::ready() ) {
				return null;
			}
			$order_id = is_object( $order ) ? ( isset( $order->order_id ) ? (int) $order->order_id : 0 ) : (int) $order;
			if ( $order_id <= 0 ) {
				return null;
			}
			/* An ec_orderdisplay is not an ec_order row: read the columns by id. */
			$extras = wp_easycart_documents::order_extras( ( is_object( $order ) && property_exists( $order, 'is_gift' ) ) ? $order : $order_id );
			if ( ! $extras->is_gift ) {
				return null;
			}
			return self::make( $extras->gift_message, $extras->gift_recipient_email, $order_id );
		}

		/**
		 * The checkout session's gift answer ( what WP EasyCart PRO saved while the shopper checks out ).
		 *
		 * @return object|null message, recipient; null when the shopper has not chosen gift.
		 */
		public static function for_session() {
			$cd = ( isset( $GLOBALS['ec_cart_data'] ) && is_object( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) && is_object( $GLOBALS['ec_cart_data']->cart_data ) ) ? $GLOBALS['ec_cart_data']->cart_data : null;
			if ( ! $cd || ! property_exists( $cd, 'is_gift' ) || empty( $cd->is_gift ) ) {
				return null;
			}
			return self::make( isset( $cd->gift_message ) ? $cd->gift_message : '', isset( $cd->gift_recipient_email ) ? $cd->gift_recipient_email : '', 0 );
		}

		/**
		 * One set of gift details.
		 *
		 * @param mixed $message   Gift message.
		 * @param mixed $recipient Recipient's email.
		 * @param int   $order_id  Order ( 0: the checkout session ).
		 * @return object
		 */
		private static function make( $message, $recipient, $order_id ) {
			$recipient = trim( (string) $recipient );
			return (object) array(
				'order_id'  => (int) $order_id,
				'message'   => trim( (string) $message ),
				'recipient' => ( '' !== $recipient && function_exists( 'is_email' ) && is_email( $recipient ) ) ? $recipient : '',
			);
		}

		/**
		 * Does the packing slip leave the prices out? For an order, the profile its slip uses ( document rules and the gift
		 * profile included ); before the order exists, the profile Settings › Documents › Gift orders picks for gift orders.
		 *
		 * @param int $order_id Order, or 0 for a gift order that is still at checkout.
		 * @return bool
		 */
		public static function slip_hides_prices( $order_id = 0 ) {
			if ( ! class_exists( 'wp_easycart_documents' ) || ! wp_easycart_documents::is_type( 'packing_slip' ) ) {
				return false;
			}
			if ( (int) $order_id > 0 ) {
				$fields = wp_easycart_documents::resolve( 'packing_slip', '', array(), (int) $order_id );
			} else {
				$fields = wp_easycart_documents::resolve( 'packing_slip', sanitize_key( (string) get_option( 'ec_option_gift_packing_slip_profile', 'gift' ) ) );
			}
			return ! wp_easycart_documents::show( $fields, 'prices', 'packing_slip' );
		}

		/**
		 * The name of the packing slip profile an order's slip uses.
		 *
		 * @param int $order_id Order.
		 * @return string
		 */
		public static function slip_profile_name( $order_id ) {
			if ( ! class_exists( 'wp_easycart_documents' ) || ! wp_easycart_documents::is_type( 'packing_slip' ) ) {
				return '';
			}
			$fields = wp_easycart_documents::resolve( 'packing_slip', '', array(), (int) $order_id );
			$list   = wp_easycart_documents::profile_list( 'packing_slip' );
			$id     = isset( $fields['_profile'] ) ? (string) $fields['_profile'] : '';
			return isset( $list[ $id ]['name'] ) ? (string) $list[ $id ]['name'] : '';
		}

		/**
		 * When the gift receipt last went to the recipient ( WP EasyCart PRO logs each one ).
		 *
		 * @param int $order_id Order.
		 * @return string 'Y-m-d H:i:s', or '' when it has not been sent.
		 */
		public static function receipt_sent( $order_id ) {
			global $wpdb;
			if ( (int) $order_id <= 0 ) {
				return '';
			}
			return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT order_log_timestamp FROM ec_order_log WHERE order_id = %d AND order_log_key = %s ORDER BY order_log_id DESC LIMIT 1', (int) $order_id, self::RECEIPT_LOG ) );
		}

		/**
		 * Are gift receipts emailed to the recipient when an order ships ( Settings › Documents › Gift orders )?
		 *
		 * @return bool
		 */
		public static function receipts_on() {
			return (bool) get_option( 'ec_option_gift_purchases', 0 ) && (bool) get_option( 'ec_option_gift_receipt_recipient', 0 );
		}

		/**
		 * A phrase from Settings › Languages › Order Documents, or the fallback.
		 *
		 * @param string $key      Phrase.
		 * @param string $fallback English.
		 * @return string Safe HTML.
		 */
		public static function text( $key, $fallback ) {
			return ( class_exists( 'wp_easycart_documents' ) && method_exists( 'wp_easycart_documents', 'text' ) ) ? wp_easycart_documents::text( $key, $fallback ) : esc_html( $fallback );
		}

		/* ------------------------------------------------------------------ */

		/**
		 * The gift icon ( line drawing in the text colour ).
		 *
		 * @return string SVG.
		 */
		public static function icon() {
			return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8"/><path d="M12 8v13"/><path d="M12 8c-1.6-3.6-5.6-4-5.6-1.5S10.2 8 12 8z"/><path d="M12 8c1.6-3.6 5.6-4 5.6-1.5S13.8 8 12 8z"/></svg>';
		}

		/**
		 * The summary's styles, once per request. They go out with the markup, so a store's own copy of ec-store.css ( and
		 * a section the one-page checkout draws again ) still gets them. Colours: a warm gift-wrap tone that reads on any
		 * light theme; the dark theme option ( ec_option_use_dark_bg ) gets a darker set through .wpec-gift-dark.
		 *
		 * @return string
		 */
		public static function styles() {
			if ( self::$styled ) {
				return '';
			}
			self::$styled = true;

			$css = '.wpec-gift-summary{--wpec-gift-bg:#fff7e0;--wpec-gift-border:#f1cf73;--wpec-gift-ink:#6b4200;--wpec-gift-accent:#b45309;--wpec-gift-icon:#fde58a;--wpec-gift-quote:rgba(255,255,255,.75);float:left;clear:both;width:100%;box-sizing:border-box;margin:0 0 20px;padding:16px 18px;border:1px solid var(--wpec-gift-border);border-radius:12px;background:var(--wpec-gift-bg) linear-gradient(135deg,rgba(255,255,255,.6),rgba(255,255,255,0) 55%);color:var(--wpec-text-color,#222);font-family:var(--wpec-font-main,inherit);font-size:.95rem;line-height:1.5;text-align:start}'
				. '.wpec-gift-summary[hidden]{display:none!important}'
				. '.wpec-gift-summary.wpec-gift-dark{--wpec-gift-bg:rgba(251,191,36,.1);--wpec-gift-border:rgba(251,191,36,.4);--wpec-gift-ink:#fcd34d;--wpec-gift-accent:#fbbf24;--wpec-gift-icon:rgba(251,191,36,.2);--wpec-gift-quote:rgba(255,255,255,.06);background-image:none}'
				. '.wpec-gift-summary .wpec-gift-summary-head{display:flex;align-items:center;gap:12px}'
				. '.wpec-gift-summary .wpec-gift-summary-icon{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;background:var(--wpec-gift-icon);color:var(--wpec-gift-accent)}'
				. '.wpec-gift-summary .wpec-gift-summary-icon svg{display:block;width:22px;height:22px}'
				. '.wpec-gift-summary .wpec-gift-summary-titles{flex:1 1 auto;min-width:0}'
				. '.wpec-gift-summary .wpec-gift-summary-title{display:block;font-weight:700;font-size:1.05em;line-height:1.35;color:var(--wpec-gift-ink)}'
				. '.wpec-gift-summary .wpec-gift-summary-sub{display:block;margin-top:1px;font-size:.92em;opacity:.85}'
				. '.wpec-gift-summary a.wpec-gift-summary-edit{flex:0 0 auto;align-self:flex-start;padding:2px 0;font-size:.92em;font-weight:600;color:var(--wpec-gift-accent);text-decoration:underline;text-underline-offset:2px}'
				. '.wpec-gift-summary .wpec-gift-summary-message{margin:14px 0 0;padding:12px 14px;border-inline-start:3px solid var(--wpec-gift-accent);border-radius:8px;background:var(--wpec-gift-quote)}'
				. '.wpec-gift-summary .wpec-gift-summary-label{display:block;margin:0 0 2px;font-size:.78em;font-weight:700;letter-spacing:.05em;text-transform:uppercase;opacity:.75}'
				. '.wpec-gift-summary .wpec-gift-summary-message p{margin:0;font-family:Georgia,"Times New Roman",serif;font-style:italic;font-size:1.05em;line-height:1.5;white-space:pre-line;overflow-wrap:anywhere}'
				. '.wpec-gift-summary .wpec-gift-summary-recipient{margin:12px 0 0;font-size:.92em;overflow-wrap:anywhere}'
				. '.wpec-gift-summary .wpec-gift-summary-recipient strong{font-weight:600}'
				. '.wpec-gift-summary .wpec-gift-summary-note{display:block;opacity:.8}'
				. '.wpec-gift-summary .wpec-gift-summary-message[hidden],.wpec-gift-summary .wpec-gift-summary-recipient[hidden]{display:none!important}'
				. '.wpec-gift-summary.wpec-gift-summary-compact{display:flex;align-items:center;gap:10px;margin:14px 0 0;padding:10px 12px;border-radius:10px}'
				. '.wpec-gift-summary.wpec-gift-summary-compact .wpec-gift-summary-icon{width:30px;height:30px}'
				. '.wpec-gift-summary.wpec-gift-summary-compact .wpec-gift-summary-icon svg{width:17px;height:17px}'
				. '.wpec-gift-summary.wpec-gift-summary-compact .wpec-gift-summary-title{font-size:.95em}'
				/* 6.0.2: room around the card on each page ( it sat flush against the success box and the cart review ). */
				. '.wpec-gift-summary.wpec-gift-summary-success{margin:20px 0}'
				. '.wpec-gift-summary.wpec-gift-summary-review{margin:12px 0 20px}'
				. '.wpec-gift-summary.wpec-gift-summary-account{margin:20px 0 0}';
			return '<style>' . $css . '</style>';
		}

		/**
		 * The gift summary: that the order is a gift, what that means for the box, the message and who gets the gift receipt.
		 *
		 * @param object|null $gift From for_order() / for_session(), or null ( only with live: drawn hidden ).
		 * @param array       $args {
		 *     @type string    $context    One of review ( checkout ), success, account, compact ( the order summary beside the
		 *                                 one-page checkout ).
		 *     @type bool      $live       WP EasyCart PRO's checkout script keeps it in step with the gift options on the page.
		 *     @type string    $edit_url   Change link, or '' for none.
		 *     @type string    $edit_step  One-page checkout step the Change link opens ( information ), with edit_nonce.
		 *     @type string    $edit_nonce Nonce for that step.
		 *     @type bool|null $prices     The slip leaves the prices out ( null: work it out ).
		 * }
		 * @return string
		 */
		public static function summary_html( $gift, $args = array() ) {
			$args = array_merge(
				array(
					'context'    => 'review',
					'live'       => false,
					'edit_url'   => '',
					'edit_step'  => '',
					'edit_nonce' => '',
					'prices'     => null,
				),
				is_array( $args ) ? $args : array()
			);
			$live = ! empty( $args['live'] );
			if ( ! $gift && ! $live ) {
				return '';
			}
			$context   = sanitize_key( (string) $args['context'] );
			$message   = $gift ? (string) $gift->message : '';
			$recipient = $gift ? (string) $gift->recipient : '';
			$order_id  = $gift ? (int) $gift->order_id : 0;
			$classes   = 'wpec-gift-summary wpec-gift-summary-' . $context . ( get_option( 'ec_option_use_dark_bg' ) ? ' wpec-gift-dark' : '' );
			$attrs     = ' class="' . esc_attr( $classes ) . '"' . ( $live ? ' data-wpec-gift-summary="' . esc_attr( $context ) . '"' : '' ) . ( $gift ? '' : ' hidden' );
			$html      = self::styles();
			if ( 'compact' === $context ) {
				return $html . '<div' . $attrs . '><span class="wpec-gift-summary-icon">' . self::icon() . '</span><span class="wpec-gift-summary-title">' . self::text( 'gift_summary_short', __( 'Sending as a gift', 'wp-easycart' ) ) . '</span></div>';
			}
			$prices = ( null === $args['prices'] ) ? self::slip_hides_prices( $order_id ) : (bool) $args['prices'];
			$title  = self::text( 'gift_summary_title', __( 'This order is a gift', 'wp-easycart' ) );

			$html .= '<div' . $attrs . ' role="group" aria-label="' . esc_attr( wp_strip_all_tags( $title ) ) . '">';
			$html .= '<div class="wpec-gift-summary-head"><span class="wpec-gift-summary-icon">' . self::icon() . '</span><div class="wpec-gift-summary-titles">';
			$html .= '<strong class="wpec-gift-summary-title">' . $title . '</strong>';
			if ( $prices ) {
				$html .= '<span class="wpec-gift-summary-sub">' . self::text( 'gift_summary_prices', __( 'No prices go in the box.', 'wp-easycart' ) ) . '</span>';
			}
			$html .= '</div>';
			if ( '' !== (string) $args['edit_url'] ) {
				$html .= '<a class="wpec-gift-summary-edit" href="' . esc_url( (string) $args['edit_url'] ) . '"'
					. ( '' !== (string) $args['edit_step'] ? ' data-wpec-gift-edit="' . esc_attr( sanitize_key( (string) $args['edit_step'] ) ) . '" data-nonce="' . esc_attr( (string) $args['edit_nonce'] ) . '"' : ' data-wpec-gift-edit=""' )
					. '>' . self::text( 'gift_summary_edit', __( 'Change', 'wp-easycart' ) ) . '</a>';
			}
			$html .= '</div>';

			$html .= '<div class="wpec-gift-summary-message" data-wpec-gift-row="message"' . ( '' === $message ? ' hidden' : '' ) . '>';
			$html .= '<span class="wpec-gift-summary-label">' . self::text( 'gift_message_label', __( 'Gift message', 'wp-easycart' ) ) . '</span>';
			$html .= '<p data-wpec-gift-message>' . esc_html( $message ) . '</p></div>';

			$sent = ( $order_id > 0 && '' !== $recipient ) ? self::receipt_sent( $order_id ) : '';
			if ( '' !== $sent ) {
				/* translators: %s: date the gift receipt was emailed. */
				$note = sprintf( wp_strip_all_tags( self::text( 'gift_summary_recipient_sent', __( 'Emailed on %s.', 'wp-easycart' ) ) ), date_i18n( get_option( 'date_format' ), strtotime( $sent ) ) );
				$note = esc_html( $note );
			} else {
				$note = self::receipts_on() ? self::text( 'gift_summary_recipient_note', __( 'Emailed when the order ships, without prices.', 'wp-easycart' ) ) : '';
			}
			$html .= '<p class="wpec-gift-summary-recipient" data-wpec-gift-row="recipient"' . ( '' === $recipient ? ' hidden' : '' ) . '>';
			$html .= self::text( 'gift_summary_recipient', __( 'Gift receipt to', 'wp-easycart' ) ) . ' <strong data-wpec-gift-recipient>' . esc_html( $recipient ) . '</strong>';
			if ( '' !== $note ) {
				$html .= '<span class="wpec-gift-summary-note">' . $note . '</span>';
			}
			$html .= '</p></div>';
			return $html;
		}

		/**
		 * Action wpeasycart_success_page_content_middle: the gift summary on the order confirmation page.
		 *
		 * @param int    $order_id Order.
		 * @param object $order    ec_orderdisplay.
		 */
		public static function success_page( $order_id, $order = null ) {
			echo self::summary_html( self::for_order( (int) $order_id ), array( 'context' => 'success' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- summary_html() escapes every value.
		}

		/**
		 * Action wpeasycart_account_order_details_right_top: the gift summary above the items of the order in My Account.
		 *
		 * @param int    $order_id Order.
		 * @param object $order    ec_orderdisplay.
		 */
		public static function account_card( $order_id, $order = null ) {
			echo self::summary_html( self::for_order( (int) $order_id ), array( 'context' => 'account' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- summary_html() escapes every value.
		}

		/**
		 * Action wpeasycart_order_detials_order_notes_after: the gift summary under the items of the order in My Account.
		 *
		 * @since 6.0.2
		 * @param object $order ec_orderdisplay.
		 */
		public static function account_card_after_items( $order ) {
			if ( is_object( $order ) && ! empty( $order->order_id ) ) {
				self::account_card( (int) $order->order_id, $order );
			}
		}

		/* ------------------------------------------------------------------ */

		/**
		 * The gift section of the receipt and shipped emails ( the templates ask the profile's Gift details switch first ).
		 * Email clients drop SVG and most CSS, so this is a table with inline styles and the gift emoji.
		 *
		 * @param int   $order_id Order.
		 * @param array $args     context: receipt | shipped.
		 */
		public static function print_email_section( $order_id, $args = array() ) {
			$gift = self::for_order( (int) $order_id );
			if ( ! $gift || ! class_exists( 'wp_easycart_email_design' ) ) {
				return;
			}
			$ed      = 'wp_easycart_email_design';
			$c       = $ed::ctx();
			$context = ( is_array( $args ) && isset( $args['context'] ) ) ? (string) $args['context'] : 'receipt';
			$font    = 'font-family:' . $c['font'] . ';';
			$html    = '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#fff7e0;border:1px solid #f1cf73;border-radius:10px;border-collapse:separate;"><tr><td align="' . esc_attr( $c['start'] ) . '" style="padding:16px 18px;' . esc_attr( $ed::css( 'text' ) ) . '">';
			$html   .= '<table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr>';
			$html   .= '<td valign="middle" style="padding-' . esc_attr( $c['end'] ) . ':12px;"><div style="width:36px;height:36px;border-radius:18px;background-color:#fde58a;text-align:center;line-height:36px;font-size:18px;">&#127873;</div></td>';
			$html   .= '<td valign="middle" style="' . esc_attr( $font ) . '"><div style="font-size:15px;line-height:1.35;font-weight:700;color:#6b4200;">' . self::text( 'gift_summary_title', __( 'This order is a gift', 'wp-easycart' ) ) . '</div>';
			if ( self::slip_hides_prices( (int) $order_id ) ) {
				$html .= '<div style="font-size:13px;line-height:1.5;color:#4b5563;">' . self::text( 'gift_summary_prices', __( 'No prices go in the box.', 'wp-easycart' ) ) . '</div>';
			}
			$html .= '</td></tr></table>';
			if ( '' !== $gift->message ) {
				$html .= '<div style="margin:14px 0 0 0;padding:12px 14px;background-color:#ffffff;border-' . esc_attr( $c['start'] ) . ':3px solid #b45309;border-radius:6px;">';
				$html .= '<div style="' . esc_attr( $ed::css( 'label' ) ) . 'margin:0 0 4px 0;">' . self::text( 'gift_message_label', __( 'Gift message', 'wp-easycart' ) ) . '</div>';
				$html .= '<div style="font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:15px;line-height:1.5;color:#1f2937;">' . nl2br( esc_html( $gift->message ) ) . '</div></div>';
			}
			if ( '' !== $gift->recipient ) {
				$sent = self::receipt_sent( (int) $order_id );
				$note = '';
				if ( '' !== $sent ) {
					/* translators: %s: date the gift receipt was emailed. */
					$note = esc_html( sprintf( wp_strip_all_tags( self::text( 'gift_summary_recipient_sent', __( 'Emailed on %s.', 'wp-easycart' ) ) ), date_i18n( get_option( 'date_format' ), strtotime( $sent ) ) ) );
				} elseif ( 'shipped' !== $context && self::receipts_on() ) {
					$note = self::text( 'gift_summary_recipient_note', __( 'Emailed when the order ships, without prices.', 'wp-easycart' ) );
				}
				$html .= '<div class="ec-email-nolink" style="margin:12px 0 0 0;' . esc_attr( $font ) . 'font-size:13px;line-height:1.5;color:#374151;">' . self::text( 'gift_summary_recipient', __( 'Gift receipt to', 'wp-easycart' ) ) . ' <strong style="color:#111827;">' . esc_html( $gift->recipient ) . '</strong>' . ( '' !== $note ? '<br /><span style="color:#6b7280;">' . $note . '</span>' : '' ) . '</div>';
			}
			$html .= '</td></tr></table>';
			$ed::block( $html );
		}

		/* ------------------------------------------------------------------ */

		/**
		 * The order screen's Gift chip, or ''.
		 *
		 * @param int|object $order Order.
		 * @return string
		 */
		public static function admin_chip_html( $order ) {
			if ( ! self::for_order( $order ) ) {
				return '';
			}
			return '<span class="ecodv2-gift-chip" id="ecodv2_gift_chip">' . self::icon() . esc_html__( 'Gift', 'wp-easycart' ) . '</span>';
		}

		/**
		 * Action wp_easycart_ecv2_order_details_header_chips: a Gift chip beside the order's other chips.
		 *
		 * @param object $order Order row.
		 */
		public static function admin_chip( $order ) {
			echo self::admin_chip_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin_chip_html() escapes.
		}

		/**
		 * The Gift order card, inside a wrapper that is always there ( WP EasyCart PRO fills it again after the gift details
		 * change on the order screen ).
		 *
		 * @param int $order_id Order.
		 * @return string
		 */
		public static function admin_card_html( $order_id ) {
			$order_id = (int) $order_id;
			$gift     = self::for_order( $order_id );
			$html     = '<div class="ecodv2-gift-card-wrap" id="ecodv2_gift_card_wrap">';
			if ( ! $gift ) {
				return $html . '</div>';
			}
			/**
			 * Can the gift details be changed on the order screen ( WP EasyCart PRO's Invoice & PO card )?
			 *
			 * @since 6.0.2
			 * @param bool $editable Editable.
			 * @param int  $order_id Order.
			 */
			$editable = (bool) apply_filters( 'wp_easycart_admin_order_gift_editable', false, $order_id );
			$html    .= '<div class="ecdv2-card ecodv2-card-gift" id="ecodv2_gift_card">';
			$html    .= '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><span class="ecodv2-gift-mark">' . self::icon() . '</span>' . esc_html__( 'Gift order', 'wp-easycart' ) . '</h3>';
			if ( $editable ) {
				$html .= '<div class="ecdv2-card-header-actions"><button type="button" class="ecv2-btn ecv2-btn-sm ecodv2-gift-edit">' . esc_html__( 'Edit', 'wp-easycart' ) . '</button></div>';
			}
			$html .= '</div><div class="ecdv2-card-body">';
			if ( '' !== $gift->message ) {
				$html .= '<blockquote class="ecodv2-gift-message">' . esc_html( $gift->message ) . '</blockquote>';
			} else {
				$html .= '<p class="ecodv2-gift-none">' . esc_html__( 'No gift message.', 'wp-easycart' ) . '</p>';
			}
			$html .= '<dl class="ecodv2-gift-rows">';
			$slip  = self::slip_profile_name( $order_id );
			if ( '' !== $slip ) {
				$html .= '<div class="ecodv2-gift-row"><dt>' . esc_html__( 'Packing slip', 'wp-easycart' ) . '</dt><dd>' . esc_html( $slip ) . ( self::slip_hides_prices( $order_id ) ? ' <span class="ecodv2-gift-status">' . esc_html__( 'No prices', 'wp-easycart' ) . '</span>' : ' <span class="ecodv2-gift-status is-warn">' . esc_html__( 'Shows prices', 'wp-easycart' ) . '</span>' ) . '</dd></div>';
			}
			$html .= '<div class="ecodv2-gift-row"><dt>' . esc_html__( 'Gift receipt', 'wp-easycart' ) . '</dt><dd>';
			if ( '' === $gift->recipient ) {
				$html .= '<span class="ecodv2-gift-muted">' . esc_html__( 'No recipient email', 'wp-easycart' ) . '</span>';
			} else {
				$sent  = self::receipt_sent( $order_id );
				$html .= '<span class="ecodv2-gift-email">' . esc_html( $gift->recipient ) . '</span> ';
				if ( '' !== $sent ) {
					/* translators: %s: date the gift receipt was emailed. */
					$html .= '<span class="ecodv2-gift-status is-sent">' . esc_html( sprintf( __( 'Sent %s', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), strtotime( $sent ) ) ) ) . '</span>';
				} elseif ( self::receipts_on() ) {
					$html .= '<span class="ecodv2-gift-status">' . esc_html__( 'Sends when the order ships', 'wp-easycart' ) . '</span>';
				} else {
					$html .= '<span class="ecodv2-gift-status">' . esc_html__( 'Not sent automatically', 'wp-easycart' ) . '</span>';
				}
			}
			$html .= '</dd></div></dl></div></div></div>';
			return $html;
		}

		/**
		 * Action wp_easycart_ecv2_order_details_main_top: the Gift order card, at the top of the order screen above Fulfillment.
		 *
		 * @param object $order Order row.
		 */
		public static function admin_card( $order ) {
			if ( ! is_object( $order ) || empty( $order->order_id ) ) {
				return;
			}
			echo self::admin_card_html( (int) $order->order_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin_card_html() escapes every value.
		}
	}

	wp_easycart_order_gift::init();

endif;
