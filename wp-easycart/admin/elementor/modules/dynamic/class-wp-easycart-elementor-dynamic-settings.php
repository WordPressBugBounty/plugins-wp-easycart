<?php
/**
 * Settings › Elementor: how to use store data in Elementor ( 6.0.2 ).
 *
 * Two help sections added through wp_easycart_settings_page_elementor ( no settings to save ): "Store data in any widget"
 * ( dynamic tags, the add to cart link, Site Settings › WP EasyCart ) and "With Elementor Pro" ( Loop Grid Query IDs, the
 * newsletter form action, display conditions ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Settings' ) ) :

	/**
	 * The module's Settings › Elementor sections.
	 */
	final class WP_EasyCart_Elementor_Dynamic_Settings {

		/**
		 * Hooks ( once ).
		 */
		public static function init() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			add_filter( 'wp_easycart_settings_page_elementor', array( __CLASS__, 'sections' ) );
		}

		/**
		 * Adds the sections.
		 *
		 * @param array $page Settings › Elementor declaration.
		 * @return array
		 */
		public static function sections( $page ) {
			if ( ! is_array( $page ) ) {
				return $page;
			}
			if ( ! isset( $page['sections'] ) || ! is_array( $page['sections'] ) ) {
				$page['sections'] = array();
			}
			$page['sections']['dynamic-data'] = array(
				'title'    => __( 'Store data in any widget', 'wp-easycart' ),
				'hint'     => __( 'Prices, stock, images, add to cart links and the cart, in any Elementor widget.', 'wp-easycart' ),
				'icon'     => 'layers',
				'keywords' => array( 'dynamic', 'tags', 'dynamic tags', 'add to cart url', 'button', 'site settings', 'colors', 'price', 'stock' ),
				'fields'   => array(),
				'render'   => array( __CLASS__, 'render_data' ),
			);
			$page['sections']['dynamic-pro']  = array(
				'title'    => __( 'Product loops, forms and conditions', 'wp-easycart' ),
				'hint'     => __( 'Loop Grid product cards, a newsletter form action and display conditions (with Elementor Pro).', 'wp-easycart' ),
				'icon'     => 'grid',
				'keywords' => array( 'loop grid', 'loop carousel', 'query id', 'wpec_products', 'wpec_featured', 'form', 'newsletter', 'display conditions', 'elementor pro' ),
				'fields'   => array(),
				'render'   => array( __CLASS__, 'render_pro' ),
			);
			return $page;
		}

		/**
		 * "Store data in any widget".
		 */
		public static function render_data() {
			echo '<p>' . esc_html__( 'In any widget setting with the dynamic tags icon (a heading, text, image, button link, gallery ...), open the icon and pick from the WP EasyCart group:', 'wp-easycart' ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Product', 'wp-easycart' ) . '</strong> ' . esc_html__( 'Product title, Product price (what the shopper pays, regular, sale or lowest price), Product regular price, Product sale price, Product SKU, Product stock, Product short description, Product description, Product image, Product gallery, Product URL, Add to cart URL, Product rating, Review count, Product category, Product category URL, Product manufacturer, Product manufacturer URL.', 'wp-easycart' ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Cart and customer', 'wp-easycart' ) . '</strong> ' . esc_html__( 'Cart count, Cart subtotal, Customer first name.', 'wp-easycart' ) . '</p>';
			echo '<p>' . esc_html__( 'Product tags show the product of the page they are on: a product page, a product template or a Loop Grid card. Choose "A product I choose" in the tag to show another one. Price and stock tags follow the product page (sale prices, customer prices, VAT and your language file).', 'wp-easycart' ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Add to cart from any button', 'wp-easycart' ) . '</strong> ' . esc_html__( 'Set a Button, Call to Action or Price Table link to the Add to cart URL tag. It adds the product and opens the cart, or keeps the shopper on the page with a short note. Products with options to choose open their product page instead.', 'wp-easycart' ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Store colours for every widget', 'wp-easycart' ) . '</strong> ' . esc_html__( 'Elementor › Site Settings › WP EasyCart sets price, sale, badge and button colours, button and price typography, corners and spacing for all WP EasyCart widgets at once. They start from your Global Colors.', 'wp-easycart' ) . '</p>';
			echo '<p>' . esc_html__( 'On a site with a page cache, show the cart count in your header with the Cart count tag (it updates itself) or the Menu Cart widget. Pages with the Customer first name tag, or an element shown by a WP EasyCart display condition ( cart, customer, product ), are never cached.', 'wp-easycart' ) . '</p>';
		}

		/**
		 * "Product loops, forms and conditions".
		 */
		public static function render_pro() {
			if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
				echo '<p>' . esc_html__( 'These need Elementor Pro.', 'wp-easycart' ) . '</p>';
			}
			echo '<p><strong>' . esc_html__( 'Product cards with Loop Grid', 'wp-easycart' ) . '</strong> ' . esc_html__( 'Design a Loop Item with the WP EasyCart tags or widgets, add a Loop Grid or Loop Carousel, set Query › Source to Store Items and type one of these in Query ID:', 'wp-easycart' ) . '</p>';
			if ( class_exists( 'WP_EasyCart_Elementor_Dynamic_Loop' ) ) {
				echo '<table class="widefat striped"><tbody>';
				foreach ( WP_EasyCart_Elementor_Dynamic_Loop::query_ids() as $query_id => $label ) {
					echo '<tr><td><code>' . esc_html( $query_id ) . '</code></td><td>' . esc_html( $label ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
			echo '<p>' . esc_html__( 'Categories and manufacturers never show up as cards, and neither do inactive products or products for another customer role.', 'wp-easycart' ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Newsletter sign-up from any form', 'wp-easycart' ) . '</strong> ' . esc_html__( 'In a Form widget, add "Add to WP EasyCart newsletter" to Actions After Submit and an Acceptance field (for example "Send me news and offers", not ticked in advance and not required). Only visitors who tick it are added to Marketing › Subscribers, with "Elementor form" as the source.', 'wp-easycart' ) . ' ' . esc_html__( 'Add a Honeypot or reCAPTCHA field to the form too, so bots cannot sign up made-up addresses.', 'wp-easycart' ) . '</p>';
			$range = class_exists( 'WP_EasyCart_Elementor_Dynamic' ) && method_exists( 'WP_EasyCart_Elementor_Dynamic', 'conditions_range' ) ? WP_EasyCart_Elementor_Dynamic::conditions_range() : array( '3.19', '4.x' );
			/* translators: 1: first Elementor Pro version, 2: last Elementor Pro version ( for example 3.19 and 4.x ). */
			echo '<p><strong>' . esc_html__( 'Display conditions', 'wp-easycart' ) . '</strong> ' . esc_html( sprintf( __( 'With Elementor Pro %1$s to %2$s, any element\'s Advanced › Display Conditions has a WP EasyCart group: Cart (is empty, has items), Customer (is signed in) and Product (is on sale, is in stock).', 'wp-easycart' ), $range[0], $range[1] ) );
			if ( defined( 'ELEMENTOR_PRO_VERSION' ) && class_exists( 'WP_EasyCart_Elementor_Dynamic' ) && method_exists( 'WP_EasyCart_Elementor_Dynamic', 'conditions_status' ) ) {
				$status = WP_EasyCart_Elementor_Dynamic::conditions_status();
				if ( 'on' !== $status['state'] ) {
					echo ' <strong>' . esc_html( $status['detail'] ) . '</strong>';
				}
			}
			echo '</p>';
		}
	}

endif;
