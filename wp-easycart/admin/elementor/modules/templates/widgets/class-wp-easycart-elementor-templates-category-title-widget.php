<?php
/**
 * Category Title widget ( wp_easycart_category_title, 6.0.2 ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-templates-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates_Category_Title_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Templates_Widget' ) ) :

	/**
	 * The category's name as a heading.
	 */
	class WP_EasyCart_Elementor_Templates_Category_Title_Widget extends WP_EasyCart_Elementor_Templates_Widget {

		/**
		 * Name ( stored in pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_category_title';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Category Title', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-archive-title';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'category', 'category title', 'archive title', 'product category', 'heading', 'title' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_category_controls();
			$this->ec_text_style_section( 'ec_title_style', __( 'Title', 'wp-easycart' ), '.wpec-el-category-title', $this->ec_global_color( 'primary' ), $this->ec_global_typography( 'primary' ), true );
		}

		/**
		 * Content controls in the Category section.
		 */
		protected function ec_register_content_controls() {
			$this->add_control(
				'ec_tag',
				array(
					'label'     => __( 'HTML tag', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'h1',
					'options'   => $this->ec_tag_options(),
					'separator' => 'before',
				)
			);
			$this->add_control(
				'ec_link',
				array(
					'label'        => __( 'Link to the category page', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => '',
					'return_value' => 'yes',
				)
			);
			$this->ec_align_control( '.wpec-el-category-title', true );
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
				$this->ec_editor_notice( __( 'No category to show', 'wp-easycart' ), __( 'Add a category in WP EasyCart ( Products › Categories ). On category pages this shows the category\'s name.', 'wp-easycart' ) );
				return;
			}
			$name = $this->ec_text( $category->category_name );
			$tag  = $this->ec_tag( $settings );
			$link = ( isset( $settings['ec_link'] ) && 'yes' === $settings['ec_link'] ) ? wp_easycart_elementor_templates_link( 'category', $category ) : '';
			echo '<' . esc_attr( $tag ) . ' class="wpec-el wpec-el-category-title">';
			if ( '' !== $link ) {
				echo '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>';
			} else {
				echo esc_html( $name );
			}
			echo '</' . esc_attr( $tag ) . '>';
		}
	}

endif;
