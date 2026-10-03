<?php
/**
 * Product Short Description widget ( wp_easycart_product_short_description, 6.0.2 ).
 *
 * The product's short description ( the summary beside the price ). Prints nothing when the product has none. Replaces
 * wp_easycart_product_details_short_description.
 *
 * Round 11: a word limit ( plain text ending in "…" ), plus the Description widget's Read more, text and box controls.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-description-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Short_Description_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Description_Widget' ) ) :

	/**
	 * Product Short Description.
	 */
	class WP_EasyCart_Elementor_Product_Info_Short_Description_Widget extends WP_EasyCart_Elementor_Product_Info_Description_Widget {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-short-description';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_short_description';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Short Description', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-description';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'product', 'short description', 'excerpt', 'summary', 'description' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();
			$this->register_content_controls( true );
			$this->register_text_style( 'wpec-pi-short-description' );
		}

		/**
		 * Output.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->pi_product( $settings, false );
			if ( ! $product ) {
				return;
			}
			$html = WP_EasyCart_Product_Info::short_description_html( $product );
			if ( '' === $html ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( '%s has no short description yet.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see nothing here until you add one in the product editor.', 'wp-easycart' ) );
				return;
			}
			$limit = isset( $settings['word_limit'] ) ? max( 0, (int) $settings['word_limit'] ) : 0;
			if ( $limit > 0 ) {
				/* Plain text, cut at a word ( wp_trim_words() adds the “…” only when it cut something ). */
				$html = esc_html( wp_trim_words( html_entity_decode( wp_strip_all_tags( str_replace( array( '<br />', '<br>' ), ' ', $html ) ), ENT_QUOTES, 'UTF-8' ), $limit, '…' ) );
			}
			$this->print_text( $settings, 'wpec-pi-short-description', $html );
		}
	}

endif;
