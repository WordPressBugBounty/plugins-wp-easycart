<?php
/**
 * Manufacturer Description widget ( wp_easycart_manufacturer_description, 6.0.2 ): the manufacturer page's excerpt.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-templates-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates_Manufacturer_Description_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Templates_Widget' ) ) :

	/**
	 * The manufacturer's description.
	 */
	class WP_EasyCart_Elementor_Templates_Manufacturer_Description_Widget extends WP_EasyCart_Elementor_Templates_Widget {

		/**
		 * Name ( stored in pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_manufacturer_description';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Manufacturer Description', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-text-area';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'manufacturer', 'brand', 'manufacturer description', 'brand description', 'text', 'description' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_manufacturer_controls();
			$this->ec_text_style_section( 'ec_text_style', __( 'Text', 'wp-easycart' ), '.wpec-el-manufacturer-description', $this->ec_global_color( 'text' ), $this->ec_global_typography( 'text' ), true );
		}

		/**
		 * Content controls in the Manufacturer section.
		 */
		protected function ec_register_content_controls() {
			$this->ec_align_control( '.wpec-el-manufacturer-description', true );
		}

		/**
		 * Output.
		 */
		protected function render() {
			if ( $this->ec_store_closed() ) {
				return;
			}
			$settings     = $this->get_settings_for_display();
			$manufacturer = $this->ec_manufacturer_row( $settings );
			if ( ! $manufacturer ) {
				$this->ec_editor_notice( __( 'No manufacturer to show', 'wp-easycart' ), __( 'Add a manufacturer in WP EasyCart ( Products › Manufacturers ). On manufacturer and product pages this shows its description.', 'wp-easycart' ) );
				return;
			}
			$post = ! empty( $manufacturer->post_id ) ? get_post( (int) $manufacturer->post_id ) : null;
			$text = ( $post instanceof WP_Post ) ? trim( $this->ec_text( $post->post_excerpt ) ) : '';
			if ( '' === $text ) {
				/* translators: %s: manufacturer name. */
				$this->ec_editor_notice( sprintf( __( '“%s” has no description', 'wp-easycart' ), $this->ec_text( $manufacturer->name ) ), __( 'Write one as the manufacturer\'s search excerpt ( Products › Manufacturers ). Visitors see nothing here until then.', 'wp-easycart' ) );
				return;
			}
			echo '<div class="wpec-el wpec-el-manufacturer-description">' . wp_kses_post( wpautop( $text ) ) . '</div>';
		}
	}

endif;
