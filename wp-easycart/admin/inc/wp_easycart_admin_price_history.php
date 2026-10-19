<?php
/**
 * WP EasyCart admin — the product editor's "Lowest price in the last 30 days" card ( 6.0.5 ).
 *
 * Under the Price card in Pricing, while the store shows the figure ( wp_easycart_price_history::enabled() ): what shoppers see under
 * the reduced price, and, for a reduction that began before WP EasyCart kept price history ( no earlier price on record ), a form to
 * enter the lowest price charged in the 30 days before it and the day it began ( wp_easycart_price_history::set_prior(), AJAX
 * ecv2_product_prior_price ). The card saves on its own: it records history, not a product field, so it never waits for Save.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_price_history' ) ) :

	/**
	 * The product editor's price history card.
	 */
	class wp_easycart_admin_price_history {

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_ajax_ecv2_product_prior_price', array( __CLASS__, 'ajax_save' ) );
		}

		/**
		 * Whether the card shows: the store shows the figure and keeps a price history.
		 *
		 * @return bool
		 */
		public static function available() {
			return class_exists( 'wp_easycart_price_history' ) && method_exists( 'wp_easycart_price_history', 'set_prior' ) && wp_easycart_price_history::ready() && wp_easycart_price_history::enabled();
		}

		/**
		 * The product's figure as shoppers see it.
		 *
		 * @param int $product_id Product.
		 * @return array reduced, prior ( float|null ), text, start ( Y-m-d, site time: when the current price began ), price, list;
		 *               6.0.5: offer ( the price a running offer takes it to, or false ), offer_text ( the note while it runs: the lowest
		 *               price of the 30 days before today, as the product page counts an offer ).
		 */
		public static function state( $product_id ) {
			global $wpdb;
			$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, manufacturer_id, price, list_price FROM ec_product WHERE product_id = %d', (int) $product_id ) );
			$price   = $row ? (float) $row->price : 0;
			$list    = $row ? (float) $row->list_price : 0;
			$reduced = $row && $list > $price && $price > 0;
			$prior   = $reduced ? wp_easycart_price_history::prior_price( (int) $product_id ) : null;
			$latest  = wp_easycart_price_history::latest( (int) $product_id );
			$start   = $latest ? get_date_from_gmt( (string) $latest->recorded_at, 'Y-m-d' ) : current_time( 'Y-m-d' );
			$offer   = $row ? self::offer_price( $row ) : false;
			$now     = ( false !== $offer ) ? wp_easycart_price_history::prior_price( (int) $product_id, true ) : null;
			return array(
				'reduced'    => $reduced,
				'prior'      => $prior,
				'text'       => ( null === $prior ) ? '' : wp_easycart_price_history::note_text( $prior ),
				'start'      => $start,
				'price'      => $price,
				'list'       => $list,
				'offer'      => $offer,
				'offer_text' => ( null === $now ) ? '' : wp_easycart_price_history::note_text( $now ),
			);
		}

		/**
		 * The price a running offer takes the product to on its product page ( WP EasyCart PRO's Offers preview, as the page asks it:
		 * the price and the compare-at price ), or false. Offers for some customers only ( a role ) follow the person viewing.
		 *
		 * @since 6.0.5
		 * @param object $row ec_product row ( product_id, manufacturer_id, price, list_price ).
		 * @return float|false
		 */
		public static function offer_price( $row ) {
			$price = (float) $row->price;
			if ( $price <= 0 || ! function_exists( 'wp_easycart_offers_active' ) || ! wp_easycart_offers_active() || ! class_exists( 'ec_offer_display' ) || ! method_exists( 'ec_offer_display', 'get_product_price_preview' ) ) {
				return false;
			}
			$preview = ec_offer_display::get_product_price_preview( (int) $row->product_id, (int) $row->manufacturer_id, $price, (float) $row->list_price );
			return ( false !== $preview && is_numeric( $preview ) && (float) $preview < $price - 0.000001 ) ? (float) $preview : false;
		}

		/**
		 * The card's body ( redrawn after a save ).
		 *
		 * @param int $product_id Product.
		 * @return string HTML.
		 */
		public static function body_html( $product_id ) {
			$state = self::state( $product_id );
			$html  = '';
			/* 6.0.5 ( Matt ): while an offer lowers the price, the product page strikes the price before the offer and counts the 30
			   days back from today ( wp_easycart_price_history::strike_before_offer() ), so the figure differs from the sale's own. */
			if ( false !== $state['offer'] ) {
				$money = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( $state['offer'], false ) : number_format_i18n( $state['offer'], 2 );
				$html .= '<p class="ecdv2-prior-status ecdv2-prior-offer">';
				/* translators: %s: the price an offer takes the product to */
				$html .= esc_html( sprintf( __( 'An offer lowers this price to %s now. While it runs, shoppers see the price before the offer crossed out and, under it:', 'wp-easycart' ), html_entity_decode( wp_strip_all_tags( $money ), ENT_QUOTES, 'UTF-8' ) ) );
				$html .= ' <b>' . esc_html( $state['offer_text'] ) . '</b></p>';
				if ( ! $state['reduced'] ) {
					return $html;
				}
			}
			if ( ! $state['reduced'] ) {
				$html .= '<p class="ecdv2-field-desc ecdv2-prior-status">' . esc_html__( 'When the price is lower than the compare-at price, shoppers see the lowest price you charged in the 30 days before the reduction. This product is not reduced now.', 'wp-easycart' ) . '</p>';
				return $html;
			}
			if ( null !== $state['prior'] ) {
				$html .= '<p class="ecdv2-prior-status">' . ( ( false !== $state['offer'] ) ? esc_html__( 'When the offer ends, shoppers see under the price:', 'wp-easycart' ) : esc_html__( 'Shoppers see under the price:', 'wp-easycart' ) ) . ' <b>' . esc_html( $state['text'] ) . '</b> <button type="button" class="button-link ecdv2-prior-toggle" aria-expanded="false" aria-controls="ecdv2_prior_form">' . esc_html__( 'Change it', 'wp-easycart' ) . '</button></p>';
			} else {
				$html .= wp_easycart_admin::notice_html(
					'warning',
					__( 'No price from before this reduction is on record, so shoppers see no lowest-price note.', 'wp-easycart' ),
					array(
						'detail'      => __( 'The reduction began before WP EasyCart kept a price history. Enter the lowest price you charged in the 30 days before it began.', 'wp-easycart' ),
						'dismissible' => false,
						'class'       => 'ecdv2-prior-missing',
					)
				);
			}
			$html .= '<div class="ecdv2-grid ecdv2-prior-form" id="ecdv2_prior_form"' . ( null !== $state['prior'] ? ' hidden' : '' ) . '>';
			$html .= '<div class="ecdv2-field"><label class="ecdv2-label" for="ecdv2_prior_price_value">' . esc_html__( 'Lowest price in the 30 days before', 'wp-easycart' ) . '</label><input type="number" id="ecdv2_prior_price_value" min="0" step="0.01" inputmode="decimal" value="' . esc_attr( null !== $state['prior'] ? (string) $state['prior'] : ( $state['list'] > 0 ? (string) $state['list'] : '' ) ) . '" /></div>';
			$html .= '<div class="ecdv2-field"><label class="ecdv2-label" for="ecdv2_prior_price_start">' . esc_html__( 'The reduction began on', 'wp-easycart' ) . '</label><input type="date" id="ecdv2_prior_price_start" max="' . esc_attr( current_time( 'Y-m-d' ) ) . '" value="' . esc_attr( $state['start'] ) . '" /></div>';
			$html .= '<div class="ecdv2-field ecdv2-field-full ecdv2-prior-actions"><button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ecdv2_prior_price_save">' . esc_html__( 'Save to price history', 'wp-easycart' ) . '</button><span class="ecdv2-field-desc ecdv2-prior-msg" role="status" aria-live="polite"></span></div>';
			$html .= '</div>';
			return $html;
		}

		/**
		 * The card, under the Price card ( product-details-v2.php ).
		 *
		 * @param object $product The product.
		 */
		public static function print_card( $product ) {
			if ( ! self::available() || ! is_object( $product ) || empty( $product->product_id ) ) {
				return;
			}
			echo '<div class="ecdv2-card ecdv2-prior-card" id="ecdv2_prior_price_card" data-ecdv2-nodirty data-product-id="' . esc_attr( (int) $product->product_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-product-editor' ) ) . '" data-saving="' . esc_attr__( 'Saving…', 'wp-easycart' ) . '" data-failed="' . esc_attr__( 'Could not save. Try again.', 'wp-easycart' ) . '">';
			echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Lowest price in the last 30 days', 'wp-easycart' ) . '</h3><span class="ecdv2-card-hint">' . esc_html__( 'Shown under a reduced price, as EU price rules ask', 'wp-easycart' ) . '</span></div>';
			echo '<div class="ecdv2-card-body" id="ecdv2_prior_price_body" aria-live="polite">' . self::body_html( (int) $product->product_id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- body_html() escapes every part.
			echo '</div>';
		}

		/**
		 * AJAX ecv2_product_prior_price: product_id, price, start ( Y-m-d, site time ). Answers the card's body.
		 */
		public static function ajax_save() {
			ecv2_product_editor_guard();
			$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_product_editor_guard() checked it.
			$price      = isset( $_POST['price'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['price'] ) ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_product_editor_guard() checked it.
			$day        = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_product_editor_guard() checked it.
			if ( $product_id <= 0 || ! self::available() ) {
				wp_send_json_error( array( 'message' => __( 'This product cannot keep a price history yet.', 'wp-easycart' ) ) );
			}
			if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
				wp_send_json_error( array( 'message' => __( 'Enter the day the reduction began.', 'wp-easycart' ) ) );
			}
			$start = strtotime( get_gmt_from_date( $day . ' 00:00:00' ) . ' UTC' );
			if ( current_time( 'Y-m-d' ) === $day ) {
				$start = time();
			}
			$result = wp_easycart_price_history::set_prior( $product_id, $price, (int) $start );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			if ( class_exists( 'ec_db' ) && method_exists( 'ec_db', 'product_cache_changed' ) ) {
				ec_db::product_cache_changed();
			}
			wp_send_json_success(
				array(
					'html'    => self::body_html( $product_id ),
					'message' => __( 'Saved to the price history.', 'wp-easycart' ),
				)
			);
		}
	}

	wp_easycart_admin_price_history::init();

endif;
