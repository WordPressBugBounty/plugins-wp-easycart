<?php
/**
 * Downloads ( 6.0.2 ): every download the customer bought on a paid order, with its limit and expiry, and a button that
 * starts it ( the same download link as the order page, so limits, expiry and option rules apply ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Downloads_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Downloads widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Downloads_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_downloads';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Downloads', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-download-bold';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'downloads', 'digital', 'files', 'my downloads', 'my account', 'woocommerce' );
		}

		/**
		 * Style sections.
		 *
		 * @return array
		 */
		protected function ec_style_parts() {
			return array( 'box', 'headings', 'text', 'buttons', 'links', 'accent' );
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Downloads', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->ec_title_control();
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Style section of the download list.
		 */
		protected function ec_widget_style_controls() {
			$this->ec_downloads_style_controls();
		}

		/**
		 * Draws the downloads ( nothing on a store that sells none ).
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			if ( ! $context['editor'] && ! WP_EasyCart_Elementor_Account::sells_downloads() ) {
				return false;
			}
			WP_EasyCart_Elementor_Account_Views::downloads( $page, ! isset( $context['settings']['show_title'] ) || 'yes' === $context['settings']['show_title'] );
			return true;
		}
	}

endif;
