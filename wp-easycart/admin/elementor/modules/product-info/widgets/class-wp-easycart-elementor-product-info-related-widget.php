<?php
/**
 * Related Products widget ( wp_easycart_related_products, 6.0.2 ).
 *
 * Products to show beside this one: its featured products ( product editor, Featured Products ), then the most viewed products
 * from its categories, drawn with the product card the store uses ( the [ec_product] card and its settings ), in the columns
 * the merchant picks per device. Replaces wp_easycart_product_details_featured_products, which showed only the four featured
 * products.
 *
 * Round 11: the order ( also which category products fill the places ), leaving out products that are out of stock, a carousel
 * ( the store's own product slider, load_ec_product() layout_mode slider ), spacing, image height and fit ( the store widget's
 * ec-img-mode- classes ), and the cards' name, price and button styling ( !important, as the cards' own stylesheet ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Related_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Related Products.
	 */
	class WP_EasyCart_Elementor_Product_Info_Related_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'related-products';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_related_products';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Related Products', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-related';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'related', 'related products', 'upsells', 'cross-sells', 'you may also like', 'featured products', 'products' );
		}

		/**
		 * Card parts.
		 *
		 * @return array
		 */
		private function card_parts() {
			return array(
				'title'     => __( 'Title', 'wp-easycart' ),
				'price'     => __( 'Price', 'wp-easycart' ),
				'rating'    => __( 'Rating', 'wp-easycart' ),
				'cart'      => __( 'Add to cart button', 'wp-easycart' ),
				'category'  => __( 'Categories', 'wp-easycart' ),
				'desc'      => __( 'Short description', 'wp-easycart' ),
				'quickview' => __( 'Quick view', 'wp-easycart' ),
			);
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			$this->start_controls_section(
				'section_related',
				array(
					'label' => __( 'Products', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'source',
				array(
					'label'       => __( 'Show', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'auto',
					'options'     => array(
						'auto'     => __( 'Featured products, then the same categories', 'wp-easycart' ),
						'featured' => __( 'Featured products only', 'wp-easycart' ),
						'category' => __( 'Products from the same categories', 'wp-easycart' ),
					),
					'description' => __( 'Featured products are the ones picked in the product editor, under Featured Products.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'count',
				array(
					'label'   => __( 'Number of products', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::NUMBER,
					'default' => 4,
					'min'     => 1,
					'max'     => 12,
				)
			);
			$this->add_control(
				'orderby',
				array(
					'label'       => __( 'Order', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'default',
					'options'     => array(
						'default'    => __( 'Featured first, then the most viewed', 'wp-easycart' ),
						'newest'     => __( 'Newest', 'wp-easycart' ),
						'price_asc'  => __( 'Price: low to high', 'wp-easycart' ),
						'price_desc' => __( 'Price: high to low', 'wp-easycart' ),
						'views'      => __( 'Most viewed', 'wp-easycart' ),
						'random'     => __( 'Random', 'wp-easycart' ),
					),
					'description' => __( 'Also decides which products from the same categories fill the places left.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'in_stock',
				array(
					'label'       => __( 'Leave out products that are out of stock', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Products you can backorder still show.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'layout',
				array(
					'label'     => __( 'Show as', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'grid',
					'separator' => 'before',
					'options'   => array(
						'grid'     => __( 'Grid', 'wp-easycart' ),
						'carousel' => __( 'Carousel', 'wp-easycart' ),
					),
				)
			);
			$this->add_responsive_control(
				'columns',
				array(
					'label'          => __( 'Columns', 'wp-easycart' ),
					'type'           => \Elementor\Controls_Manager::SELECT,
					'default'        => '4',
					'tablet_default' => '3',
					'mobile_default' => '2',
					'options'        => array(
						'1' => '1',
						'2' => '2',
						'3' => '3',
						'4' => '4',
						'5' => '5',
						'6' => '6',
					),
				)
			);
			$this->add_control(
				'card_parts',
				array(
					'label'       => __( 'Show on each product', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'multiple'    => true,
					'label_block' => true,
					'default'     => array( 'title', 'price', 'rating', 'cart' ),
					'options'     => $this->card_parts(),
				)
			);
			$this->pi_note( 'card_note', esc_html__( 'The products use your store’s product card, so they match your store pages.', 'wp-easycart' ) . ' ' . $this->pi_settings_link( 'design', __( 'Settings › Design', 'wp-easycart' ) ) );
			$this->end_controls_section();

			$this->start_controls_section(
				'section_carousel',
				array(
					'label'     => __( 'Carousel', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
					'condition' => array( 'layout' => 'carousel' ),
				)
			);
			$this->pi_note( 'carousel_note', esc_html__( 'The store’s own product slider; Columns sets how many products show at once on each device.', 'wp-easycart' ) );
			$this->add_control(
				'carousel_arrows',
				array(
					'label'   => __( 'Arrows', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'carousel_dots',
				array(
					'label'   => __( 'Dots', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => '',
				)
			);
			$this->add_control(
				'carousel_loop',
				array(
					'label'   => __( 'Start again after the last', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => '',
				)
			);
			$this->add_control(
				'carousel_autoplay',
				array(
					'label'       => __( 'Move on by itself', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Shoppers can still move it themselves.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'carousel_autoplay_time',
				array(
					'label'     => __( 'Seconds per move', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 6,
					'min'       => 2,
					'max'       => 30,
					'condition' => array( 'carousel_autoplay' => 'yes' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_heading',
				array(
					'label' => __( 'Heading', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_heading',
				array(
					'label'   => __( 'Heading', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'heading',
				array(
					'label'       => __( 'Heading text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Product_Info::text( 'product_details_related_products', __( 'Related Products', 'wp-easycart' ), 'product_details' ),
					'condition'   => array( 'show_heading' => 'yes' ),
				)
			);
			$this->add_control(
				'heading_tag',
				array(
					'label'     => __( 'HTML tag', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'h2',
					'options'   => array(
						'h2'  => 'H2',
						'h3'  => 'H3',
						'h4'  => 'H4',
						'h5'  => 'H5',
						'h6'  => 'H6',
						'div' => 'div',
						'p'   => 'p',
					),
					'condition' => array( 'show_heading' => 'yes' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_heading_style',
				array(
					'label'     => __( 'Heading', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'show_heading' => 'yes' ),
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-related__heading', 'text', 'heading_align' );
			$this->pi_text_controls( 'heading', '{{WRAPPER}} .wpec-pi-related__heading', 'primary' );
			$this->add_responsive_control(
				'heading_spacing',
				array(
					'label'      => __( 'Space below', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-related__heading' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			$this->register_card_style_controls();
		}

		/**
		 * Style tab: spacing, images and the store's product cards ( round 11 ). The cards' own stylesheet marks most of its
		 * colors and type !important, so these do too.
		 */
		private function register_card_style_controls() {
			$scope = '{{WRAPPER}} .wpec-pi-related';

			$this->start_controls_section(
				'section_grid_style',
				array(
					'label' => __( 'Products', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'spacing',
				array(
					'label'       => __( 'Space between products', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'description' => __( 'Across and, in a grid, down. Leave empty for 20px.', 'wp-easycart' ),
				)
			);
			$this->add_responsive_control(
				'row_gap',
				array(
					'label'      => __( 'Extra space between rows', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array( $scope . ' .ec_productlist_ul > .ec_product_li' => 'margin-bottom: {{SIZE}}{{UNIT}} !important;' ),
					'condition'  => array( 'layout!' => 'carousel' ),
				)
			);
			$this->add_control(
				'image_mode',
				array(
					'label'        => __( 'Image height', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => '',
					'separator'    => 'before',
					'options'      => array(
						''        => __( 'As in your store settings', 'wp-easycart' ),
						'fixed'   => __( 'The same for every product', 'wp-easycart' ),
						'dynamic' => __( 'Each image’s own height', 'wp-easycart' ),
					),
					'prefix_class' => 'ec-img-mode-',
				)
			);
			$this->add_responsive_control(
				'image_height',
				array(
					'label'      => __( 'Height', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 50,
							'max' => 800,
						),
					),
					'condition'  => array( 'image_mode' => 'fixed' ),
					'selectors'  => array(
						'{{WRAPPER}} .ec_image_container_none, {{WRAPPER}} .ec_image_container_none > div, {{WRAPPER}} .ec_image_container_border, {{WRAPPER}} .ec_image_container_border > div, {{WRAPPER}} .ec_image_container_shadow, {{WRAPPER}} .ec_image_container_shadow > div' => 'min-height: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
						'{{WRAPPER}} .ec_product_image_container, {{WRAPPER}} .ec_product_image_1, {{WRAPPER}} .ec_product_image_2' => 'width: 100% !important; height: 100% !important;',
					),
				)
			);
			$this->add_control(
				'image_fit',
				array(
					'label'     => __( 'Fit', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '',
					'options'   => array(
						''        => __( 'Fill the space ( crop )', 'wp-easycart' ),
						'contain' => __( 'Show the whole image', 'wp-easycart' ),
					),
					'condition' => array( 'image_mode' => 'fixed' ),
					'selectors' => array(
						'{{WRAPPER}} .ec_image_container_none img, {{WRAPPER}} .ec_image_container_border img, {{WRAPPER}} .ec_image_container_shadow img, {{WRAPPER}} .ec_product_image_container > img, {{WRAPPER}} .ec_product_image_1 > img, {{WRAPPER}} .ec_product_image_2 > img, {{WRAPPER}} .ec_flipbook > img' => 'object-fit: {{VALUE}} !important; width: 100% !important; height: 100% !important;',
					),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_card_style',
				array(
					'label' => __( 'Product cards', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$title = $scope . ' [class^="ec_product_title"], ' . $scope . ' [class^="ec_product_title"] a';
			$this->add_control(
				'card_title_heading',
				array(
					'label' => __( 'Name', 'wp-easycart' ),
					'type'  => \Elementor\Controls_Manager::HEADING,
				)
			);
			$this->add_control(
				'card_title_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $title => 'color: {{VALUE}} !important;' ),
				)
			);
			$this->add_control(
				'card_title_hover_color',
				array(
					'label'     => __( 'Hover color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $scope . ' [class^="ec_product_title"] a:hover, ' . $scope . ' [class^="ec_product_title"] a:focus' => 'color: {{VALUE}} !important;' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'           => 'card_title_typography',
					'selector'       => $scope . ' [class^="ec_product_title"]',
					'fields_options' => $this->important_typography(),
				)
			);
			$this->add_control(
				'card_price_heading',
				array(
					'label'     => __( 'Price', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'card_price_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $scope . ' [class^="ec_price_type"], ' . $scope . ' .ec_price' => 'color: {{VALUE}} !important;' ),
				)
			);
			$this->add_control(
				'card_list_price_color',
				array(
					'label'     => __( 'Price before a sale', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $scope . ' [class^="ec_list_price_type"], ' . $scope . ' .ec_list_price' => 'color: {{VALUE}} !important;' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'           => 'card_price_typography',
					'selector'       => $scope . ' [class^="ec_price_type"]',
					'fields_options' => $this->important_typography(),
				)
			);
			$this->add_control(
				'card_button_heading',
				array(
					'label'     => __( 'Add to cart button', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$button = $scope . ' .ec_product_addtocart';
			$this->start_controls_tabs( 'card_button_tabs' );
			$this->start_controls_tab( 'card_button_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->add_control(
				'card_button_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $button . ', ' . $button . ' a, ' . $button . ' input' => 'color: {{VALUE}} !important;' ),
				)
			);
			$this->add_control(
				'card_button_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $button => 'background-color: {{VALUE}} !important;' ),
				)
			);
			$this->add_control(
				'card_button_border_color',
				array(
					'label'     => __( 'Border color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $button => 'border-color: {{VALUE}} !important;' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'card_button_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->add_control(
				'card_button_hover_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $button . ':hover, ' . $button . ':hover a, ' . $button . ':hover input' => 'color: {{VALUE}} !important;' ),
				)
			);
			$this->add_control(
				'card_button_hover_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $button . ':hover' => 'background-color: {{VALUE}} !important;' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_responsive_control(
				'card_button_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'separator'  => 'before',
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( $button => 'border-radius: {{SIZE}}{{UNIT}} !important;' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'           => 'card_button_typography',
					'selector'       => $button . ', ' . $button . ' a, ' . $button . ' input',
					'fields_options' => $this->important_typography(),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Typography fields that print !important ( the store's product cards set their type !important ).
		 *
		 * @return array fields_options.
		 */
		private function important_typography() {
			return array(
				'font_family'    => array( 'selectors' => array( '{{SELECTOR}}' => 'font-family: "{{VALUE}}", sans-serif !important;' ) ),
				'font_size'      => array( 'selectors' => array( '{{SELECTOR}}' => 'font-size: {{SIZE}}{{UNIT}} !important;' ) ),
				'font_weight'    => array( 'selectors' => array( '{{SELECTOR}}' => 'font-weight: {{VALUE}} !important;' ) ),
				'text_transform' => array( 'selectors' => array( '{{SELECTOR}}' => 'text-transform: {{VALUE}} !important;' ) ),
				'line_height'    => array( 'selectors' => array( '{{SELECTOR}}' => 'line-height: {{SIZE}}{{UNIT}} !important;' ) ),
				'letter_spacing' => array( 'selectors' => array( '{{SELECTOR}}' => 'letter-spacing: {{SIZE}}{{UNIT}} !important;' ) ),
			);
		}

		/**
		 * A column count from a responsive setting.
		 *
		 * @param array  $settings Settings.
		 * @param string $key      Setting.
		 * @param int    $fallback When empty.
		 * @return int
		 */
		private function columns( $settings, $key, $fallback ) {
			$value = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
			return ( $value >= 1 && $value <= 6 ) ? $value : $fallback;
		}

		/**
		 * Output.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->pi_product( $settings );
			if ( ! $product || ! function_exists( 'load_ec_product' ) ) {
				return;
			}
			$source  = ( isset( $settings['source'] ) && in_array( $settings['source'], array( 'auto', 'featured', 'category' ), true ) ) ? $settings['source'] : 'auto';
			$count   = isset( $settings['count'] ) ? max( 1, min( 12, (int) $settings['count'] ) ) : 4;
			$orders  = WP_EasyCart_Product_Info::related_orders();
			$orderby = ( isset( $settings['orderby'] ) && isset( $orders[ $settings['orderby'] ] ) ) ? $settings['orderby'] : 'default';
			$ids     = WP_EasyCart_Product_Info::related_ids(
				$product,
				$source,
				$count,
				array(
					'orderby'  => $orderby,
					'in_stock' => WP_EasyCart_Product_Info::on( $settings, 'in_stock' ),
				)
			);
			if ( ! $ids ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( 'Nothing to show beside %s.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see nothing here. Pick Featured Products in the product editor, or put the product in a category with other products.', 'wp-easycart' ) );
				return;
			}
			$parts = ( isset( $settings['card_parts'] ) && is_array( $settings['card_parts'] ) ) ? array_values( array_intersect( $settings['card_parts'], array_keys( $this->card_parts() ) ) ) : array( 'title', 'price', 'rating', 'cart' );
			if ( ! $parts ) {
				$parts = array( 'title' );
			}
			$desktop = $this->columns( $settings, 'columns', 4 );
			$tablet  = $this->columns( $settings, 'columns_tablet', min( 3, $desktop ) );
			$mobile  = $this->columns( $settings, 'columns_mobile', min( 2, $tablet ) );
			if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				/* Prices, stock and the add to cart forms follow the shopper ( role prices, promotions ). */
				wp_easycart_product_details_no_cache();
			}
			$atts = array(
				'is_elementor'            => 1,
				'productid'               => implode( ',', $ids ),
				'per_page'                => count( $ids ),
				'columns'                 => $desktop,
				'cols_desktop'            => $desktop,
				'cols_tablet'             => $tablet,
				'cols_mobile'             => $mobile,
				'cols_mobile_small'       => $mobile,
				'product_visible_options' => implode( ',', $parts ),
			);
			if ( '' !== $orders[ $orderby ]['orderby'] ) {
				$atts['orderby'] = $orders[ $orderby ]['orderby'];
				$atts['order']   = $orders[ $orderby ]['order'];
			}
			if ( isset( $settings['spacing']['size'] ) && '' !== (string) $settings['spacing']['size'] ) {
				$atts['spacing'] = max( 0, min( 40, (int) $settings['spacing']['size'] ) );
			}
			if ( isset( $settings['layout'] ) && 'carousel' === $settings['layout'] ) {
				$arrows = WP_EasyCart_Product_Info::on( $settings, 'carousel_arrows' ) ? 1 : 0;
				$dots   = WP_EasyCart_Product_Info::on( $settings, 'carousel_dots' ) ? 1 : 0;
				$atts   = array_merge(
					$atts,
					array(
						'layout_mode'           => 'slider',
						'slider_nav'            => $arrows,
						'slider_nav_tablet'     => $arrows,
						'slider_nav_mobile'     => $arrows,
						'slider_nav_show'       => 1,
						'slider_dot'            => $dots,
						'slider_dot_tablet'     => $dots,
						'slider_dot_mobile'     => $dots,
						'slider_loop'           => WP_EasyCart_Product_Info::on( $settings, 'carousel_loop' ) ? 1 : 0,
						'slider_auto_play'      => WP_EasyCart_Product_Info::on( $settings, 'carousel_autoplay' ) ? 1 : 0,
						'slider_auto_play_time' => 1000 * ( isset( $settings['carousel_autoplay_time'] ) ? max( 2, min( 30, (int) $settings['carousel_autoplay_time'] ) ) : 6 ),
					)
				);
			}
			$cards = load_ec_product( $atts );
			if ( '' === trim( (string) $cards ) ) {
				$this->ec_editor_notice( __( 'These products are not available to show right now.', 'wp-easycart' ), __( 'Visitors see nothing here while they are inactive or hidden from the store.', 'wp-easycart' ) );
				return;
			}
			echo '<div class="wpec-el wpec-pi-related">';
			if ( WP_EasyCart_Product_Info::on( $settings, 'show_heading' ) ) {
				$tag = ( isset( $settings['heading_tag'] ) && in_array( $settings['heading_tag'], array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' ), true ) ) ? $settings['heading_tag'] : 'h2';
				echo '<' . tag_escape( $tag ) . ' class="wpec-pi-related__heading">' . esc_html( WP_EasyCart_Product_Info::label( $settings, 'heading', WP_EasyCart_Product_Info::text( 'product_details_related_products', __( 'Related Products', 'wp-easycart' ), 'product_details' ) ) ) . '</' . tag_escape( $tag ) . '>';
			}
			echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the store's own product cards ( load_ec_product() escapes each part ).
			echo '</div>';
		}
	}

endif;
