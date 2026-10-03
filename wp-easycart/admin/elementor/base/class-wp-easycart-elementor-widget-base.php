<?php
/**
 * Base class for the 6.0.2 WP EasyCart Elementor widgets.
 *
 * Only loaded when Elementor registers widgets ( it extends \Elementor\Widget_Base ). The 25 widgets from before 6.0.2 do
 * not extend it and never will: their names, control IDs and defaults are stored in saved pages.
 *
 * What every new widget gets from here:
 * - categories ( static $ec_category: product | shop | checkout | account ), keywords, a help link;
 * - is_dynamic_content() true: Elementor's element cache must never keep prices, stock, carts or accounts;
 * - has_widget_inner_wrapper() false ( optimized markup, one wrapper less );
 * - render_plain_content() '' by default ( Elementor saves it into post_content on every editor save ). A widget whose
 *   output equals a shortcode returns that shortcode from plain_content_shortcode();
 * - product resolution through wp_easycart_elementor_context() ( ec_register_product_controls() + ec_product() );
 * - editor-only notices ( ec_editor_notice() ) instead of empty space or "Product not found";
 * - the PRO lock ( static $ec_pro_feature ): a widget whose implementation lives in WP EasyCart PRO. Without it the
 *   editor shows the widget locked with an Upgrade ( or Update, for an older PRO ) link and the storefront prints nothing.
 *   PRO registers its own class with the same get_name() after FREE's; Elementor keeps the last one registered.
 *
 * Contract for module JS ( implemented by the foundation in ec-store.js; modules never edit ec-store.js ):
 * - jQuery( document ).on( 'wpeasycart_product_state', function( e, state ) {} ), state = { product_id, rand_id, price,
 *   price_display, list_price_display, stock, stock_display, sku, image, options } after any option change;
 * - jQuery( document ).on( 'wpeasycart_cart_changed', function( e, cart ) {} ), cart = { count, subtotal_display, items };
 *   window.wpeasycart_refresh_cart_widgets() asks the server ( uncached ) and fires it;
 * - window.wpeasycart_init( container ) ( re )binds galleries, swatches, sliders and quick view inside a container; the
 *   foundation calls it for every EasyCart widget Elementor draws ( frontend/element_ready ).
 * Details ( 6.0.2 foundation ): state.product_id is a number, rand_id a string, price a number or null, stock a number or
 * null ( not counted ), options [ { name, value } ]; display values are plain text. cart.items = [ { title, quantity,
 * price_display, link, image, cartitem_id, product_id } ], plain text ( insert with .text() ). A widget that listens to
 * wpeasycart_cart_changed puts data-wpec-cart-widget on its root, so the page asks for the cart once it is ready.
 *
 * Also from here ( 6.0.2 foundation ): get_style_depends() ( wpeasycart_css, wpeasycart-elementor ) and get_script_depends()
 * ( wpeasycart_js ), merged with array_merge( parent::…(), … ); ec_no_product_notice() ( the editor note for "no product",
 * which names Settings › Products › Who can view the store when that hides it ). Shared CSS ( wpeasycart-elementor.css ):
 * the --wpec-* defaults, .wpec-el ( root class: focus ring, reduced motion ), .wpec-el-button, .wpec-el-price,
 * .wpec-el-price-sale, .wpec-el-price-regular, .wpec-el-sr-only, .wpec-el-notice, .wpec-el-locked.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) && class_exists( '\Elementor\Widget_Base' ) ) :

	/**
	 * Base for the 6.0.2 WP EasyCart widgets.
	 */
	abstract class WP_EasyCart_Elementor_Widget_Base extends \Elementor\Widget_Base {

		/**
		 * Panel category: product | shop | checkout | account ( see WP_EasyCart_Elementor::categories() ).
		 *
		 * @var string
		 */
		protected static $ec_category = 'product';

		/**
		 * WP EasyCart PRO feature this widget needs ( a key of the 'elementor' upsell catalog ), or '' for FREE widgets.
		 *
		 * @var string
		 */
		protected static $ec_pro_feature = '';

		/**
		 * Help topic: the guide section WP_EasyCart_Elementor::help_url() links for this widget ( '' = the overview guide ).
		 *
		 * @var string
		 */
		protected static $ec_help = '';

		/**
		 * Panel categories.
		 *
		 * @return array
		 */
		public function get_categories() {
			return array( 'wp-easycart-' . static::$ec_category );
		}

		/**
		 * Search keywords; widgets add their own through ec_keywords().
		 *
		 * @return array
		 */
		public function get_keywords() {
			return array_merge( array( 'easycart', 'wp easycart', 'shop', 'store' ), $this->ec_keywords() );
		}

		/**
		 * Widget-specific keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array();
		}

		/**
		 * "Need help?" link in the panel.
		 *
		 * @return string
		 */
		public function get_custom_help_url() {
			if ( class_exists( 'WP_EasyCart_Elementor' ) && method_exists( 'WP_EasyCart_Elementor', 'help_url' ) ) {
				return WP_EasyCart_Elementor::help_url( static::$ec_help, $this->get_name() );
			}
			return (string) apply_filters( 'wp_easycart_elementor_help_url', 'https://docs.wpeasycart.com/docs/how-to-guides/elementor-connect-and-widgets-overview/', $this->get_name() );
		}

		/**
		 * Styles every 6.0.2 widget loads ( 6.0.2 ): the store stylesheet and the shared widget stylesheet ( the --wpec-*
		 * variables, editor notes, focus styles ). A widget adds its own with array_merge( parent::get_style_depends(), … ).
		 * Registered on every page by WP_EasyCart_Elementor::register_assets(); never depends on the request. The store
		 * stylesheet only while EasyCart loads it ( wp_easycart_load_css_scripts, wp_easycart_elementor_store_assets() ).
		 *
		 * @return array
		 */
		public function get_style_depends() {
			$store = function_exists( 'wp_easycart_elementor_store_assets' ) ? wp_easycart_elementor_store_assets( 'css', array( 'wpeasycart_css' ) ) : array( 'wpeasycart_css' );
			return array_merge( $store, array( 'wpeasycart-elementor' ) );
		}

		/**
		 * Scripts every 6.0.2 widget loads ( 6.0.2 ): the store script ( wpeasycart_init(), the product state and cart events ),
		 * while EasyCart loads it ( wp_easycart_load_js_scripts ). A widget adds its own with array_merge(
		 * parent::get_script_depends(), … ); its own script must cope without the store script ( typeof checks ).
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return function_exists( 'wp_easycart_elementor_store_assets' ) ? wp_easycart_elementor_store_assets( 'js', array( 'wpeasycart_js' ) ) : array( 'wpeasycart_js' );
		}

		/**
		 * The editor note for a widget with no product to show ( 6.0.2 ): the store closed to this person by Settings ›
		 * Products › Who can view the store, or no product found. Visitors see nothing.
		 *
		 * @param string $what Optional plain text naming what is missing ( default: a product ).
		 */
		protected function ec_no_product_notice( $what = '' ) {
			if ( function_exists( 'wp_easycart_store_is_restricted' ) && wp_easycart_store_is_restricted() ) {
				$this->ec_editor_notice(
					__( 'Only some customer roles can view your store.', 'wp-easycart' ),
					__( 'Visitors outside them see nothing here. Change it in WP EasyCart › Settings › Products › Who can view the store.', 'wp-easycart' )
				);
				return;
			}
			$this->ec_editor_notice(
				'' !== $what ? $what : __( 'No product to show here.', 'wp-easycart' ),
				__( 'Pick a product in the widget, or a preview product in the page settings ( WP EasyCart preview ). Visitors on a page without a product see nothing.', 'wp-easycart' )
			);
		}

		/**
		 * Never cached by Elementor's element cache.
		 *
		 * @return bool
		 */
		protected function is_dynamic_content(): bool {
			return true;
		}

		/**
		 * Optimized markup ( Elementor 3.26+ ): no inner .elementor-widget-container.
		 *
		 * @return bool
		 */
		public function has_widget_inner_wrapper(): bool {
			return false;
		}

		/**
		 * What Elementor saves into post_content for this widget: the equivalent shortcode, or nothing.
		 */
		public function render_plain_content() {
			$shortcode = $this->plain_content_shortcode();
			if ( '' !== $shortcode ) {
				echo $shortcode; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a shortcode built from sanitized settings.
			}
		}

		/**
		 * The shortcode this widget is equivalent to ( '' when there is none ).
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return '';
		}

		/**
		 * Whether this request draws the editor or its preview.
		 *
		 * @return bool
		 */
		protected function ec_is_editor() {
			return function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor();
		}

		/**
		 * Whether the widget draws a card of a post loop ( Elementor Pro's Loop Grid or Loop Carousel ) rather than the page's
		 * own content: the loop switches the global post to the card's product, so it no longer matches the page viewed.
		 * Product widgets send no view events and print no structured data in a card ( a 24-card list is not 24 product
		 * views, and Google wants product markup on the product's own page, not one block per card ).
		 *
		 * @since 6.0.2
		 *
		 * @return bool
		 */
		public static function ec_is_loop_card() {
			return (int) get_the_ID() !== (int) get_queried_object_id();
		}

		/**
		 * State of this widget's PRO feature: 'active', 'update' ( an older WP EasyCart PRO is active ) or 'locked'.
		 * FREE widgets are always 'active'.
		 *
		 * @return string
		 */
		protected function ec_pro_state() {
			if ( '' === static::$ec_pro_feature ) {
				return 'active';
			}
			return wp_easycart_elementor_pro_feature( static::$ec_pro_feature );
		}

		/**
		 * Standard "Product" controls: this page's product, or a picked one. Call inside register_controls().
		 *
		 * @param array $args 'section' => bool ( open a Content section, default true ), 'label' => section label.
		 */
		protected function ec_register_product_controls( $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'section' => true,
					'label'   => __( 'Product', 'wp-easycart' ),
				)
			);
			if ( $args['section'] ) {
				$this->start_controls_section(
					'ec_product_section',
					array(
						'label' => $args['label'],
						'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
					)
				);
			}
			$this->add_control(
				'ec_product_source',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'current',
					'options' => array(
						'current' => __( 'The product of the page it is on', 'wp-easycart' ),
						'pick'    => __( 'A product I choose', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_product_id',
				array(
					'label'       => __( 'Product', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product',
					'label_block' => true,
					'multiple'    => false,
					'condition'   => array( 'ec_product_source' => 'pick' ),
				)
			);
			$this->add_control(
				'ec_product_source_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'On product pages and product templates this shows that product. While you edit a page that is not a product, a sample product is shown.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_product_source' => 'current' ),
				)
			);
			if ( $args['section'] ) {
				$this->end_controls_section();
			}
		}

		/**
		 * The product for these settings ( see WP_EasyCart_Elementor_Context::product() ).
		 *
		 * @param array $settings Widget settings ( get_settings_for_display() ).
		 * @param bool  $details  Build as a details page.
		 * @return ec_product|null
		 */
		protected function ec_product( $settings, $details = true ) {
			$source = ( isset( $settings['ec_product_source'] ) && 'pick' === $settings['ec_product_source'] ) ? 'pick' : 'current';
			$id     = isset( $settings['ec_product_id'] ) ? $settings['ec_product_id'] : 0;
			if ( is_array( $id ) ) {
				$id = reset( $id );
			}
			return wp_easycart_elementor_context()->product(
				array(
					'source'     => $source,
					'product_id' => (int) $id,
					'details'    => $details,
				)
			);
		}

		/**
		 * An editor-only message in place of the widget ( visitors see nothing ).
		 *
		 * @param string $message Plain text.
		 * @param string $action  Optional plain-text hint on what to do.
		 */
		protected function ec_editor_notice( $message, $action = '' ) {
			if ( ! $this->ec_is_editor() ) {
				return;
			}
			echo '<div class="wpec-el-notice" role="note"><strong>' . esc_html( $message ) . '</strong>';
			if ( '' !== $action ) {
				echo '<span>' . esc_html( $action ) . '</span>';
			}
			echo '</div>';
		}

		/**
		 * Renders the locked state of a PRO widget in the editor ( nothing on the storefront ). Returns true when the widget
		 * must not render.
		 *
		 * @return bool
		 */
		protected function ec_render_pro_lock() {
			$state = $this->ec_pro_state();
			if ( 'active' === $state ) {
				return false;
			}
			if ( $this->ec_is_editor() ) {
				$is_update = ( 'update' === $state );
				$url       = $is_update ? admin_url( 'plugins.php' ) : wp_easycart_elementor_upgrade_url( static::$ec_pro_feature );
				echo '<div class="wpec-el-notice wpec-el-locked" role="note"><strong>' . esc_html( $this->get_title() ) . '</strong><span>';
				if ( $is_update ) {
					esc_html_e( 'Update WP EasyCart PRO to use this widget.', 'wp-easycart' );
				} else {
					echo esc_html( wp_easycart_elementor_requires_text() );
				}
				echo '</span><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . ( $is_update ? esc_html__( 'Update', 'wp-easycart' ) : esc_html__( 'Upgrade', 'wp-easycart' ) ) . '</a></div>';
			}
			return true;
		}
	}

endif;
