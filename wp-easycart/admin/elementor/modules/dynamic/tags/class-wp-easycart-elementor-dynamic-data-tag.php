<?php
/**
 * Base for the WP EasyCart data tags ( 6.0.2 ): links, images, galleries and numbers.
 *
 * Loaded inside elementor/dynamic_tags/register only.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Data_Tag' ) && class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) :

	/**
	 * Data tag base.
	 */
	abstract class WP_EasyCart_Elementor_Dynamic_Data_Tag extends \Elementor\Core\DynamicTags\Data_Tag {

		use WP_EasyCart_Elementor_Dynamic_Tag_Trait;
	}

endif;
