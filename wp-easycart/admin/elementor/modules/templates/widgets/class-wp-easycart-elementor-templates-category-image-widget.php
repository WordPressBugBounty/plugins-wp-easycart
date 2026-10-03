<?php
/**
 * Category Image widget ( wp_easycart_category_image, 6.0.2 ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-templates-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates_Category_Image_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Templates_Widget' ) ) :

	/**
	 * The category's image or its featured banner.
	 */
	class WP_EasyCart_Elementor_Templates_Category_Image_Widget extends WP_EasyCart_Elementor_Templates_Widget {

		/**
		 * Name ( stored in pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_category_image';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Category Image', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-image';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'category', 'category image', 'banner', 'category banner', 'archive image', 'image' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_category_controls();
			$this->ec_image_style_section( '.wpec-el-category-image img' );
		}

		/**
		 * Content controls in the Category section.
		 */
		protected function ec_register_content_controls() {
			$this->add_control(
				'ec_image_source',
				array(
					'label'       => __( 'Image', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'image',
					'options'     => array(
						'image'  => __( 'Category image', 'wp-easycart' ),
						'banner' => __( 'Featured banner', 'wp-easycart' ),
					),
					'description' => __( 'Both are set in the category editor ( Products › Categories › Images ). The banner is the wide one.', 'wp-easycart' ),
					'separator'   => 'before',
				)
			);
			$this->add_control(
				'ec_image_size',
				array(
					'label'   => __( 'Image size', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'large',
					'options' => $this->ec_image_sizes(),
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
			$this->ec_align_control( '.wpec-el-category-image' );
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
				$this->ec_editor_notice( __( 'No category to show', 'wp-easycart' ), __( 'Add a category in WP EasyCart ( Products › Categories ). On category pages this shows the category\'s image.', 'wp-easycart' ) );
				return;
			}
			$name   = $this->ec_text( $category->category_name );
			$banner = ( isset( $settings['ec_image_source'] ) && 'banner' === $settings['ec_image_source'] );
			if ( $banner ) {
				$attachment_id = ! empty( $category->post_id ) ? (int) get_post_thumbnail_id( (int) $category->post_id ) : 0;
				$html          = $attachment_id ? $this->ec_image_html( $attachment_id, '', $this->ec_image_size( $settings ), $name, 'wpec-el-category-image-img' ) : '';
			} else {
				$url  = wp_easycart_elementor_templates_category_image_url( isset( $category->image ) ? $category->image : '' );
				$html = $this->ec_image_html( 0, $url, $this->ec_image_size( $settings ), $name, 'wpec-el-category-image-img' );
			}
			if ( '' === $html ) {
				/* translators: %s: category name. */
				$this->ec_editor_notice( sprintf( $banner ? __( '“%s” has no featured banner', 'wp-easycart' ) : __( '“%s” has no category image', 'wp-easycart' ), $name ), __( 'Add one in the category editor ( Products › Categories › Images ). Visitors see nothing here until then.', 'wp-easycart' ) );
				return;
			}
			$link = ( isset( $settings['ec_link'] ) && 'yes' === $settings['ec_link'] ) ? wp_easycart_elementor_templates_link( 'category', $category ) : '';
			echo '<div class="wpec-el wpec-el-category-image">';
			if ( '' !== $link ) {
				echo '<a href="' . esc_url( $link ) . '">' . $html . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ec_image_html(): wp_get_attachment_image() output, or an img built with esc_url() / esc_attr().
			} else {
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ec_image_html(): wp_get_attachment_image() output, or an img built with esc_url() / esc_attr().
			}
			echo '</div>';
		}
	}

endif;
