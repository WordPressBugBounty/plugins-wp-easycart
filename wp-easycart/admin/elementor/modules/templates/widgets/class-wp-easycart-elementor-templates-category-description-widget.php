<?php
/**
 * Category Description widget ( wp_easycart_category_description, 6.0.2 ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-templates-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates_Category_Description_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Templates_Widget' ) ) :

	/**
	 * The category's description.
	 */
	class WP_EasyCart_Elementor_Templates_Category_Description_Widget extends WP_EasyCart_Elementor_Templates_Widget {

		/**
		 * Name ( stored in pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_category_description';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Category Description', 'wp-easycart' );
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
			return array( 'category', 'category description', 'archive description', 'product category', 'text', 'description' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_category_controls();
			$this->ec_text_style_section( 'ec_text_style', __( 'Text', 'wp-easycart' ), '.wpec-el-category-description', $this->ec_global_color( 'text' ), $this->ec_global_typography( 'text' ), true );
		}

		/**
		 * Content controls in the Category section.
		 */
		protected function ec_register_content_controls() {
			$this->ec_align_control( '.wpec-el-category-description', true );
		}

		/**
		 * Output.
		 */
		protected function render() {
			if ( $this->ec_store_closed() ) {
				return;
			}
			$settings = $this->get_settings_for_display();
			$category = $this->ec_category_row( $settings );
			if ( ! $category ) {
				$this->ec_editor_notice( __( 'No category to show', 'wp-easycart' ), __( 'Add a category in WP EasyCart ( Products › Categories ). On category pages this shows the category\'s description.', 'wp-easycart' ) );
				return;
			}
			$text = trim( $this->ec_text( isset( $category->short_description ) ? $category->short_description : '' ) );
			if ( '' === $text ) {
				/* translators: %s: category name. */
				$this->ec_editor_notice( sprintf( __( '“%s” has no description', 'wp-easycart' ), $this->ec_text( $category->category_name ) ), __( 'Write one in the category editor ( Products › Categories ). Visitors see nothing here until then.', 'wp-easycart' ) );
				return;
			}
			$html = function_exists( 'wp_easycart_escape_html' ) ? wp_easycart_escape_html( $text ) : $text;
			echo '<div class="wpec-el wpec-el-category-description">' . wp_kses_post( wpautop( $html ) ) . '</div>';
		}
	}

endif;
