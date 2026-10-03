<?php
/**
 * Manufacturer Title widget ( wp_easycart_manufacturer_title, 6.0.2 ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-templates-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates_Manufacturer_Title_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Templates_Widget' ) ) :

	/**
	 * The manufacturer's name as a heading.
	 */
	class WP_EasyCart_Elementor_Templates_Manufacturer_Title_Widget extends WP_EasyCart_Elementor_Templates_Widget {

		/**
		 * Name ( stored in pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_manufacturer_title';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Manufacturer Title', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-heading';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'manufacturer', 'brand', 'brand name', 'manufacturer title', 'heading', 'title' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_manufacturer_controls();
			$this->ec_text_style_section( 'ec_title_style', __( 'Title', 'wp-easycart' ), '.wpec-el-manufacturer-title', $this->ec_global_color( 'primary' ), $this->ec_global_typography( 'primary' ), true );
		}

		/**
		 * Content controls in the Manufacturer section.
		 */
		protected function ec_register_content_controls() {
			$this->add_control(
				'ec_tag',
				array(
					'label'     => __( 'HTML tag', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'h2',
					'options'   => $this->ec_tag_options(),
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
			$this->ec_align_control( '.wpec-el-manufacturer-title', true );
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
				$this->ec_editor_notice( __( 'No manufacturer to show', 'wp-easycart' ), __( 'Add a manufacturer in WP EasyCart ( Products › Manufacturers ). On manufacturer and product pages this shows its name.', 'wp-easycart' ) );
				return;
			}
			$name = $this->ec_text( $manufacturer->name );
			$tag  = $this->ec_tag( $settings );
			$link = ( isset( $settings['ec_link'] ) && 'yes' === $settings['ec_link'] ) ? wp_easycart_elementor_templates_link( 'manufacturer', $manufacturer ) : '';
			echo '<' . esc_attr( $tag ) . ' class="wpec-el wpec-el-manufacturer-title">';
			if ( '' !== $link ) {
				echo '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>';
			} else {
				echo esc_html( $name );
			}
			echo '</' . esc_attr( $tag ) . '>';
		}
	}

endif;
