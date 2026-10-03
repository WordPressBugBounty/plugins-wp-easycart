<?php
/**
 * Elementor Pro Theme Builder: "Single Product ( WP EasyCart )" template type ( 6.0.2 ).
 *
 * Loaded only on elementor/documents/register when Elementor Pro's Single_Base exists. Previews a WP EasyCart product by
 * default and starts with the condition WP EasyCart › Products. The type name wp-easycart-single-product is stored in
 * every template of this type: never rename it.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Theme_Product_Document' ) && class_exists( '\ElementorPro\Modules\ThemeBuilder\Documents\Single_Base' ) ) :

	/**
	 * Single Product ( WP EasyCart ).
	 */
	class WP_EasyCart_Elementor_Theme_Product_Document extends \ElementorPro\Modules\ThemeBuilder\Documents\Single_Base {

		/**
		 * Properties: a single template whose default condition is WP EasyCart › Products.
		 *
		 * @return array
		 */
		public static function get_properties() {
			$properties                   = parent::get_properties();
			$properties['location']       = 'single';
			$properties['condition_type'] = 'wp_easycart_product';
			return $properties;
		}

		/**
		 * Type ( stored: never rename ).
		 *
		 * @return string
		 */
		public static function get_type() {
			return WP_EasyCart_Elementor_Templates::THEME_PRODUCT_TYPE;
		}

		/**
		 * Type name ( older Elementor versions ask the instance ).
		 *
		 * @return string
		 */
		public function get_name() {
			return static::get_type();
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public static function get_title() {
			return esc_html__( 'Single Product ( WP EasyCart )', 'wp-easycart' );
		}

		/**
		 * Plural title.
		 *
		 * @return string
		 */
		public static function get_plural_title() {
			return esc_html__( 'Single Products ( WP EasyCart )', 'wp-easycart' );
		}

		/**
		 * Site editor icon.
		 *
		 * @return string
		 */
		protected static function get_site_editor_icon() {
			return 'eicon-single-product';
		}

		/**
		 * Site editor help.
		 *
		 * @return array
		 */
		protected static function get_site_editor_tooltip_data() {
			return array(
				'title'     => esc_html__( 'What is a WP EasyCart single product template?', 'wp-easycart' ),
				'content'   => esc_html__( 'It designs your WP EasyCart product pages. Add the WP EasyCart product widgets and choose which products use it in the display conditions.', 'wp-easycart' ),
				'tip'       => esc_html__( 'Without Elementor Pro you can do the same in Settings › Elementor › Product pages.', 'wp-easycart' ),
				'docs'      => WP_EasyCart_Elementor::help_url( 'single-product', 'wp-easycart-single-product' ),
				'video_url' => '',
			);
		}

		/**
		 * The WP EasyCart product widgets first in the panel.
		 *
		 * @return array
		 */
		protected static function get_editor_panel_categories() {
			$categories = parent::get_editor_panel_categories();
			$first      = array();
			foreach ( array( 'wp-easycart-product', 'wp-easycart-shop' ) as $slug ) {
				if ( isset( $categories[ $slug ] ) ) {
					$first[ $slug ]           = $categories[ $slug ];
					$first[ $slug ]['active'] = true;
					unset( $categories[ $slug ] );
				}
			}
			return $first + $categories;
		}

		/**
		 * Preview: a WP EasyCart product ( store items are ec_store posts ), the first active one by default.
		 */
		protected function register_controls() {
			parent::register_controls();

			$this->update_control(
				'preview_type',
				array(
					'type'    => \Elementor\Controls_Manager::HIDDEN,
					'default' => 'single/ec_store',
				)
			);
			global $wpdb;
			$post_id = (int) $wpdb->get_var( "SELECT p.post_id FROM ec_product p INNER JOIN {$wpdb->posts} wp ON wp.ID = p.post_id WHERE p.activate_in_store = 1 AND wp.post_status = 'publish' ORDER BY p.product_id ASC LIMIT 1" );
			if ( $post_id ) {
				$this->update_control(
					'preview_id',
					array(
						'default' => $post_id,
					)
				);
			}
		}

		/**
		 * Remote library category.
		 *
		 * @return array
		 */
		protected function get_remote_library_config() {
			$config             = parent::get_remote_library_config();
			$config['category'] = 'single product';
			return $config;
		}
	}

endif;
