<?php
/**
 * Manufacturer Logo widget ( wp_easycart_manufacturer_logo, 6.0.2 ): the manufacturer page's featured image.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-templates-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates_Manufacturer_Logo_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Templates_Widget' ) ) :

	/**
	 * The manufacturer's logo.
	 */
	class WP_EasyCart_Elementor_Templates_Manufacturer_Logo_Widget extends WP_EasyCart_Elementor_Templates_Widget {

		/**
		 * Name ( stored in pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_manufacturer_logo';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Manufacturer Logo', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-site-logo';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'manufacturer', 'brand', 'logo', 'brand logo', 'manufacturer logo', 'image' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_manufacturer_controls();
			$this->ec_image_style_section( '.wpec-el-manufacturer-logo img' );
		}

		/**
		 * Content controls in the Manufacturer section.
		 */
		protected function ec_register_content_controls() {
			$this->add_control(
				'ec_image_size',
				array(
					'label'     => __( 'Image size', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'medium',
					'options'   => $this->ec_image_sizes(),
					'separator' => 'before',
				)
			);
			$this->add_control(
				'ec_link',
				array(
					'label'        => __( 'Link to the manufacturer page', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => '',
					'return_value' => 'yes',
				)
			);
			$this->ec_align_control( '.wpec-el-manufacturer-logo' );
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
				$this->ec_editor_notice( __( 'No manufacturer to show', 'wp-easycart' ), __( 'Add a manufacturer in WP EasyCart ( Products › Manufacturers ). On manufacturer and product pages this shows its logo.', 'wp-easycart' ) );
				return;
			}
			$name          = $this->ec_text( $manufacturer->name );
			$attachment_id = ! empty( $manufacturer->post_id ) ? (int) get_post_thumbnail_id( (int) $manufacturer->post_id ) : 0;
			$html          = $attachment_id ? $this->ec_image_html( $attachment_id, '', $this->ec_image_size( $settings, 'medium' ), $name, 'wpec-el-manufacturer-logo-img' ) : '';
			if ( '' === $html ) {
				/* translators: %s: manufacturer name. */
				$this->ec_editor_notice( sprintf( __( '“%s” has no logo', 'wp-easycart' ), $name ), __( 'Add the logo as the manufacturer\'s featured image ( Products › Manufacturers ). Visitors see nothing here until then.', 'wp-easycart' ) );
				return;
			}
			$link = ( isset( $settings['ec_link'] ) && 'yes' === $settings['ec_link'] ) ? wp_easycart_elementor_templates_link( 'manufacturer', $manufacturer ) : '';
			echo '<div class="wpec-el wpec-el-manufacturer-logo">';
			if ( '' !== $link ) {
				echo '<a href="' . esc_url( $link ) . '">' . $html . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ec_image_html(): wp_get_attachment_image() output, or an img built with esc_url() / esc_attr().
			} else {
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ec_image_html(): wp_get_attachment_image() output, or an img built with esc_url() / esc_attr().
			}
			echo '</div>';
		}
	}

endif;
