<?php
/**
 * Base for the WP EasyCart text tags ( 6.0.2 ): Elementor's Before, After and Fallback settings come with it, so a tag with
 * nothing to show ( a price the store hides, no sale ) prints the merchant's fallback text.
 *
 * Loaded inside elementor/dynamic_tags/register only.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Text_Tag' ) && class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) :

	/**
	 * Text tag base.
	 */
	abstract class WP_EasyCart_Elementor_Dynamic_Text_Tag extends \Elementor\Core\DynamicTags\Tag {

		use WP_EasyCart_Elementor_Dynamic_Tag_Trait;

		/**
		 * Categories.
		 *
		 * @return array
		 */
		public function get_categories() {
			return array( 'text' );
		}

		/**
		 * Prints the value as text.
		 */
		public function render() {
			$value = (string) $this->ec_text();
			if ( '' !== $value ) {
				echo esc_html( $value );
			}
		}

		/**
		 * The value as plain text ( '' = nothing to show ).
		 *
		 * @return string
		 */
		protected function ec_text() {
			return '';
		}
	}

endif;
