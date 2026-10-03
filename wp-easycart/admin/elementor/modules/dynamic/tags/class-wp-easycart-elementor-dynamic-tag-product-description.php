<?php
/**
 * Dynamic tag "Product description" ( 6.0.2 ): formatted as on the product page, or as plain text with a word limit ( for a
 * card or a teaser ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Description' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * The description.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Description extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-description';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product description', 'wp-easycart' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'format',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'formatted',
					'options' => array(
						'formatted' => __( 'As on the product page', 'wp-easycart' ),
						'plain'     => __( 'Plain text', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'words',
				array(
					'label'       => __( 'Word limit', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => '',
					'min'         => 0,
					'step'        => 1,
					'description' => __( 'Empty shows the whole description as plain text. A limit always gives plain text.', 'wp-easycart' ),
					'condition'   => array( 'format' => 'plain' ),
				)
			);
			$this->ec_add_product_controls();
		}

		/**
		 * Prints the description.
		 */
		public function render() {
			$product = $this->ec_product();
			if ( ! $product || '' === trim( (string) $product->description ) ) {
				return;
			}
			if ( 'plain' !== $this->ec_setting( 'format', 'formatted' ) ) {
				if ( method_exists( $product, 'display_product_description' ) ) {
					ob_start();
					$product->display_product_description();
					echo wp_kses_post( (string) ob_get_clean() );
				} else {
					echo wp_kses_post( wpautop( stripslashes( (string) $product->description ) ) );
				}
				return;
			}
			$text  = WP_EasyCart_Elementor_Dynamic::plain( $product->description );
			$words = absint( $this->ec_setting( 'words', '0' ) );
			if ( $words > 0 ) {
				$text = wp_trim_words( $text, $words );
			}
			echo esc_html( $text );
		}
	}

endif;
