<?php
/**
 * Dynamic tag "Product category" ( 6.0.2 ): the product's first category, or all of them.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Product_Category' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) ) :

	/**
	 * Category names.
	 */
	class WP_EasyCart_Elementor_Dynamic_Tag_Product_Category extends WP_EasyCart_Elementor_Dynamic_Text_Tag {

		/**
		 * Name ( stored in saved pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp-easycart-product-category';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product category', 'wp-easycart' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->add_control(
				'show',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'first',
					'options' => array(
						'first' => __( 'The first category', 'wp-easycart' ),
						'all'   => __( 'All categories', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'separator',
				array(
					'label'     => __( 'Between categories', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::TEXT,
					'default'   => ', ',
					'condition' => array( 'show' => 'all' ),
				)
			);
			$this->ec_add_product_controls();
		}

		/**
		 * Value.
		 *
		 * @return string
		 */
		protected function ec_text() {
			$product = $this->ec_product();
			if ( ! $product ) {
				return '';
			}
			$names = array();
			foreach ( WP_EasyCart_Elementor_Dynamic::categories( $product ) as $category ) {
				if ( '' !== $category->category_name ) {
					$names[] = $category->category_name;
				}
			}
			if ( ! $names ) {
				return '';
			}
			if ( 'all' !== $this->ec_setting( 'show', 'first' ) ) {
				return $names[0];
			}
			$separator = $this->get_settings( 'separator' );
			return implode( is_string( $separator ) ? $separator : ', ', $names );
		}
	}

endif;
