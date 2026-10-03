<?php
/**
 * What the Products, Product Carousel and Shop widgets share ( 6.0.2 ): the product controls, the card controls, the card
 * style sections and the pieces they draw around the cards ( heading, page numbers ).
 *
 * Control IDs here are stored in saved pages: never rename one or change its default.
 *
 * Colours that the store-wide WP EasyCart variables cover ( price, sale price, regular price, badges, buttons ) are controls
 * without a default that set those variables on the widget, so Site Settings › WP EasyCart applies until a widget overrides
 * it and the stylesheet falls back to the kit's global colours. Typography defaults to the kit's global fonts.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Listing_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Shared controls and drawing for the product list widgets.
	 */
	abstract class WP_EasyCart_Elementor_Shop_Listing_Widget extends WP_EasyCart_Elementor_Widget_Base {

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'shop';

		/**
		 * Styles.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_values( array_unique( array_merge( parent::get_style_depends(), array( 'wpec-el-shop' ) ) ) );
		}

		/**
		 * Scripts.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_values( array_unique( array_merge( parent::get_script_depends(), array( 'wpec-el-shop' ) ) ) );
		}

		/**
		 * A kit typography default, when this Elementor has global fonts.
		 *
		 * @param string $which primary | secondary | text | accent.
		 * @return array
		 */
		protected function ec_global_font( $which ) {
			$class = '\Elementor\Core\Kits\Documents\Tabs\Global_Typography';
			if ( ! class_exists( $class ) ) {
				return array();
			}
			$constants = array(
				'primary'   => 'TYPOGRAPHY_PRIMARY',
				'secondary' => 'TYPOGRAPHY_SECONDARY',
				'text'      => 'TYPOGRAPHY_TEXT',
				'accent'    => 'TYPOGRAPHY_ACCENT',
			);
			return array( 'default' => constant( $class . '::' . $constants[ $which ] ) );
		}

		/**
		 * A typography group control ( kit font as its default ).
		 *
		 * @param string $name     Control name.
		 * @param string $selector CSS selector.
		 * @param string $font     primary | secondary | text | accent.
		 */
		protected function ec_typography( $name, $selector, $font = 'text' ) {
			$args   = array(
				'name'     => $name,
				'selector' => $selector,
			);
			$global = $this->ec_global_font( $font );
			if ( ! empty( $global ) ) {
				$args['global'] = $global;
			}
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), $args );
		}

		/**
		 * A colour control that sets a CSS variable on the widget.
		 *
		 * @param string $name     Control name.
		 * @param string $label    Label.
		 * @param string $variable CSS custom property.
		 */
		protected function ec_color_var( $name, $label, $variable ) {
			$this->add_control(
				$name,
				array(
					'label'     => $label,
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}}' => $variable . ': {{VALUE}};',
					),
				)
			);
		}

		/**
		 * The "Products" section: what the list shows.
		 *
		 * @param array $args 'limit' => default number of products.
		 */
		protected function ec_register_query_controls( $args = array() ) {
			$args = wp_parse_args( $args, array( 'limit' => 8 ) );
			$this->start_controls_section(
				'ec_section_query',
				array(
					'label' => __( 'Products', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_source',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'all',
					'options' => array(
						'all'                  => __( 'All products', 'wp-easycart' ),
						'featured'             => __( 'Featured products', 'wp-easycart' ),
						'on_sale'              => __( 'Products on sale', 'wp-easycart' ),
						'newest'               => __( 'Newest products', 'wp-easycart' ),
						'best_sellers'         => __( 'Best sellers', 'wp-easycart' ),
						'pick'                 => __( 'Products I choose', 'wp-easycart' ),
						'category'             => __( 'Products in categories I choose', 'wp-easycart' ),
						'current_category'     => __( 'Products in the category of the page it is on', 'wp-easycart' ),
						'manufacturer'         => __( 'Products by manufacturers I choose', 'wp-easycart' ),
						'current_manufacturer' => __( 'Products by the manufacturer of the page it is on', 'wp-easycart' ),
						'related'              => __( 'Products related to the product of the page it is on', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_product_ids',
				array(
					'label'       => __( 'Products', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product',
					'label_block' => true,
					'multiple'    => true,
					'description' => __( 'They show in the order you add them, unless you choose another order below.', 'wp-easycart' ),
					'condition'   => array( 'ec_source' => 'pick' ),
				)
			);
			$this->add_control(
				'ec_category_ids',
				array(
					'label'       => __( 'Categories', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_cat',
					'label_block' => true,
					'multiple'    => true,
					'condition'   => array( 'ec_source' => 'category' ),
				)
			);
			$this->add_control(
				'ec_manufacturer_ids',
				array(
					'label'       => __( 'Manufacturers', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_brand',
					'label_block' => true,
					'multiple'    => true,
					'condition'   => array( 'ec_source' => 'manufacturer' ),
				)
			);
			$this->add_control(
				'ec_source_note_category',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'On category pages and category templates this shows that category. On a product page it shows the product’s first category, leaving that product out. While you edit a page that is not a category, a sample category is shown.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_source' => 'current_category' ),
				)
			);
			$this->add_control(
				'ec_source_note_manufacturer',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'On manufacturer pages this shows that manufacturer. On a product page it shows more from the product’s manufacturer, leaving that product out.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_source' => 'current_manufacturer' ),
				)
			);
			$this->add_control(
				'ec_source_note_related',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The product’s own featured products first, then other products from its categories. Use it on product pages and product templates.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_source' => 'related' ),
				)
			);
			$this->add_control(
				'ec_source_note_best_sellers',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The products sold most in paid orders, refunds taken off. The list is worked out again every few hours.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_source' => 'best_sellers' ),
				)
			);
			$this->add_control(
				'ec_in_stock',
				array(
					'label'     => __( 'Only products in stock', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => '',
					'condition' => array( 'ec_source!' => 'pick' ),
				)
			);
			$this->add_control(
				'ec_orderby',
				array(
					'label'       => __( 'Order', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'options'     => array(
						''           => __( 'Default', 'wp-easycart' ),
						'store'      => __( 'Store order', 'wp-easycart' ),
						'title'      => __( 'Name, A to Z', 'wp-easycart' ),
						'title_desc' => __( 'Name, Z to A', 'wp-easycart' ),
						'price'      => __( 'Price, low to high', 'wp-easycart' ),
						'price_desc' => __( 'Price, high to low', 'wp-easycart' ),
						'newest'     => __( 'Newest first', 'wp-easycart' ),
						'oldest'     => __( 'Oldest first', 'wp-easycart' ),
						'rating'     => __( 'Best rated', 'wp-easycart' ),
						'popular'    => __( 'Most viewed', 'wp-easycart' ),
						'random'     => __( 'Random', 'wp-easycart' ),
					),
					'description' => __( 'Default keeps your chosen products in your order, best sellers by sales and newest by date; everything else follows the store’s sort order ( Settings › Products › Product lists ).', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_limit',
				array(
					'label'   => __( 'Number of products', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::NUMBER,
					'default' => (int) $args['limit'],
					'min'     => 1,
					'max'     => WP_EasyCart_Elementor_Shop_Query::MAX_PER_PAGE,
					'step'    => 1,
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Columns and gaps ( inside an open section ).
		 *
		 * @param string $label    Label of the columns control.
		 * @param array  $defaults Desktop, tablet and mobile columns.
		 * @param bool   $redraw   Redraw the widget in the editor when they change ( 6.0.2: the carousel, whose script works the
		 *                         slide widths out once; a grid follows the CSS variables by itself ).
		 */
		protected function ec_register_columns_controls( $label = '', $defaults = array( 4, 3, 2 ), $redraw = false ) {
			$columns = array();
			for ( $i = 1; $i <= 6; $i++ ) {
				$columns[ (string) $i ] = (string) $i;
			}
			$this->add_responsive_control(
				'ec_columns',
				array(
					'label'          => ( '' !== $label ) ? $label : __( 'Columns', 'wp-easycart' ),
					'type'           => \Elementor\Controls_Manager::SELECT,
					'default'        => (string) $defaults[0],
					'tablet_default' => (string) $defaults[1],
					'mobile_default' => (string) $defaults[2],
					'options'        => $columns,
					'selectors'      => array(
						'{{WRAPPER}} .wpec-products' => '--wpec-columns: {{VALUE}};',
					),
					'render_type'    => $redraw ? 'template' : 'ui',
				)
			);
			$this->add_responsive_control(
				'ec_column_gap',
				array(
					'label'       => __( 'Space between products', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px' ),
					'range'       => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'default'     => array(
						'unit' => 'px',
						'size' => 24,
					),
					'selectors'   => array(
						'{{WRAPPER}} .wpec-products' => '--wpec-col-gap: {{SIZE}}{{UNIT}};',
					),
					'render_type' => $redraw ? 'template' : 'ui',
				)
			);
		}

		/**
		 * Space between rows ( grids only, inside an open section ).
		 */
		protected function ec_register_row_gap_control() {
			$this->add_responsive_control(
				'ec_row_gap',
				array(
					'label'      => __( 'Space between rows', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 120,
						),
					),
					'default'    => array(
						'unit' => 'px',
						'size' => 32,
					),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-products' => '--wpec-row-gap: {{SIZE}}{{UNIT}};',
					),
				)
			);
		}

		/**
		 * An optional heading above the products ( inside an open section ).
		 */
		protected function ec_register_heading_controls() {
			$this->add_control(
				'ec_heading',
				array(
					'label'       => __( 'Heading', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Leave empty for no heading', 'wp-easycart' ),
					'label_block' => true,
					'separator'   => 'before',
					'dynamic'     => array( 'active' => true ),
				)
			);
			$this->add_control(
				'ec_heading_tag',
				array(
					'label'     => __( 'Heading HTML tag', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'h2',
					'options'   => array(
						'h2'  => 'H2',
						'h3'  => 'H3',
						'h4'  => 'H4',
						'div' => 'div',
					),
					'condition' => array( 'ec_heading!' => '' ),
				)
			);
			$this->add_control(
				'ec_heading_link',
				array(
					'label'     => __( 'Heading link', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::URL,
					'dynamic'   => array( 'active' => true ),
					'condition' => array( 'ec_heading!' => '' ),
				)
			);
			$this->add_control(
				'ec_heading_desc',
				array(
					'label'     => __( 'Text under the heading', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::TEXTAREA,
					'default'   => '',
					'rows'      => 2,
					'condition' => array( 'ec_heading!' => '' ),
				)
			);
		}

		/**
		 * The "Product card" section: which parts each card shows.
		 */
		protected function ec_register_card_controls() {
			$this->start_controls_section(
				'ec_section_card',
				array(
					'label' => __( 'Product card', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$switch = function ( $id, $label, $initial = 'yes', $condition = array() ) {
				$args = array(
					'label'   => $label,
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => $initial,
				);
				if ( ! empty( $condition ) ) {
					$args['condition'] = $condition;
				}
				$this->add_control( $id, $args );
			};
			$switch( 'ec_show_image', __( 'Image', 'wp-easycart' ) );
			$switch( 'ec_show_hover_image', __( 'Second image on hover', 'wp-easycart' ), 'yes', array( 'ec_show_image' => 'yes' ) );
			$this->add_control(
				'ec_image_size',
				array(
					'label'       => __( 'Image size', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'medium_large',
					'options'     => WP_EasyCart_Elementor_Shop::image_size_options(),
					'description' => __( 'The largest file a card loads. Phones and small columns get smaller files by themselves.', 'wp-easycart' ),
					'condition'   => array( 'ec_show_image' => 'yes' ),
				)
			);
			$switch( 'ec_show_quick_view', __( 'Quick view', 'wp-easycart' ), 'yes', array( 'ec_show_image' => 'yes' ) );
			$switch( 'ec_show_badges', __( 'Badges ( sale, sold out, product tag )', 'wp-easycart' ), 'yes', array( 'ec_show_image' => 'yes' ) );
			$this->add_control(
				'ec_sale_badge',
				array(
					'label'     => __( 'Sale badge shows', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'text',
					'options'   => array(
						'text'    => __( 'Sale', 'wp-easycart' ),
						'percent' => __( 'The discount, e.g. -20%', 'wp-easycart' ),
					),
					'condition' => array(
						'ec_show_image'  => 'yes',
						'ec_show_badges' => 'yes',
					),
				)
			);
			$switch( 'ec_show_category', __( 'Category', 'wp-easycart' ), '' );
			$switch( 'ec_show_title', __( 'Name', 'wp-easycart' ) );
			$this->add_control(
				'ec_title_tag',
				array(
					'label'     => __( 'Name HTML tag', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'h3',
					'options'   => array(
						'h2'  => 'H2',
						'h3'  => 'H3',
						'h4'  => 'H4',
						'h5'  => 'H5',
						'h6'  => 'H6',
						'p'   => 'p',
						'div' => 'div',
					),
					'condition' => array( 'ec_show_title' => 'yes' ),
				)
			);
			$switch( 'ec_show_rating', __( 'Rating', 'wp-easycart' ) );
			$switch( 'ec_show_price', __( 'Price', 'wp-easycart' ) );
			$switch( 'ec_show_swatches', __( 'Colour swatches', 'wp-easycart' ) );
			$switch( 'ec_show_excerpt', __( 'Short description', 'wp-easycart' ), '' );
			$switch( 'ec_show_button', __( 'Add to cart button', 'wp-easycart' ) );
			$this->add_responsive_control(
				'ec_align',
				array(
					'label'                => __( 'Alignment', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array(
						'left'   => array(
							'title' => __( 'Left', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center' => array(
							'title' => __( 'Center', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-center',
						),
						'right'  => array(
							'title' => __( 'Right', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-right',
						),
					),
					'selectors_dictionary' => array(
						'left'   => 'start',
						'center' => 'center',
						'right'  => 'end',
					),
					'selectors'            => array(
						'{{WRAPPER}} .wpec-products' => '--wpec-card-align: {{VALUE}};',
					),
				)
			);
			$this->add_control(
				'ec_card_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					/* translators: %s: link to Settings › Products. */
					'raw'             => sprintf( esc_html__( 'Products with options link to their page to choose them; other products are added without leaving the page. Store-wide rules ( catalog mode, going to the cart after an add, quick view on phones ) are in %s.', 'wp-easycart' ), '<a href="' . esc_url( WP_EasyCart_Elementor_Shop::settings_url( 'products' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Settings › Products', 'wp-easycart' ) . '</a>' ),
					'content_classes' => 'elementor-descriptor',
					'separator'       => 'before',
				)
			);
			$this->end_controls_section();
		}

		/**
		 * The card's Style sections.
		 */
		protected function ec_register_card_style_controls() {
			$style = \Elementor\Controls_Manager::TAB_STYLE;

			$this->start_controls_section(
				'ec_style_card',
				array(
					'label' => __( 'Card', 'wp-easycart' ),
					'tab'   => $style,
				)
			);
			$this->add_control(
				'card_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-card' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'card_border',
					'selector' => '{{WRAPPER}} .wpec-card',
				)
			);
			$this->add_responsive_control(
				'card_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-card'        => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
						/* The image sits in the card's top corners ( Style › Image › Corner radius still wins ). */
						'{{WRAPPER}} .wpec-card__media' => 'border-top-left-radius: {{TOP}}{{UNIT}}; border-top-right-radius: {{RIGHT}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'card_padding',
				array(
					'label'      => __( 'Text padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-card__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->start_controls_tabs( 'card_tabs' );
			$this->start_controls_tab(
				'card_tab_normal',
				array( 'label' => __( 'Normal', 'wp-easycart' ) )
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'card_shadow',
					'selector' => '{{WRAPPER}} .wpec-card',
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab(
				'card_tab_hover',
				array( 'label' => __( 'Hover', 'wp-easycart' ) )
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'card_hover_shadow',
					'selector' => '{{WRAPPER}} .wpec-card:hover',
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_image',
				array(
					'label'     => __( 'Image', 'wp-easycart' ),
					'tab'       => $style,
					'condition' => array( 'ec_show_image' => 'yes' ),
				)
			);
			$this->add_control(
				'image_ratio',
				array(
					'label'     => __( 'Shape', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '1 / 1',
					'options'   => array(
						'1 / 1'  => __( 'Square', 'wp-easycart' ),
						'4 / 5'  => __( 'Portrait 4:5', 'wp-easycart' ),
						'3 / 4'  => __( 'Portrait 3:4', 'wp-easycart' ),
						'2 / 3'  => __( 'Portrait 2:3', 'wp-easycart' ),
						'4 / 3'  => __( 'Landscape 4:3', 'wp-easycart' ),
						'16 / 9' => __( 'Wide 16:9', 'wp-easycart' ),
						'auto'   => __( 'As uploaded', 'wp-easycart' ),
					),
					'selectors' => array( '{{WRAPPER}}' => '--wpec-image-ratio: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'image_fit',
				array(
					'label'     => __( 'Fit', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'cover',
					'options'   => array(
						'cover'   => __( 'Fill the shape ( crop )', 'wp-easycart' ),
						'contain' => __( 'Show the whole image', 'wp-easycart' ),
					),
					'selectors' => array( '{{WRAPPER}}' => '--wpec-image-fit: {{VALUE}};' ),
					'condition' => array( 'image_ratio!' => 'auto' ),
				)
			);
			$this->add_control(
				'image_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-card__media' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'image_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-card__media' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_title',
				array(
					'label'     => __( 'Name', 'wp-easycart' ),
					'tab'       => $style,
					'condition' => array( 'ec_show_title' => 'yes' ),
				)
			);
			$this->ec_typography( 'title_typography', '{{WRAPPER}} .wpec-card__title', 'primary' );
			$this->start_controls_tabs( 'title_tabs' );
			$this->start_controls_tab( 'title_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->add_control(
				'title_color',
				array(
					'label'     => __( 'Colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-card__title a' => 'color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'title_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->add_control(
				'title_hover_color',
				array(
					'label'     => __( 'Colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-card__title a:hover, {{WRAPPER}} .wpec-card__title a:focus-visible' => 'color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_responsive_control(
				'title_spacing',
				array(
					'label'      => __( 'Space below', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-card__title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
					'separator'  => 'before',
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_price',
				array(
					'label'     => __( 'Price', 'wp-easycart' ),
					'tab'       => $style,
					'condition' => array( 'ec_show_price' => 'yes' ),
				)
			);
			$this->ec_typography( 'price_typography', '{{WRAPPER}} .wpec-card__price', 'text' );
			$this->ec_color_var( 'price_color', __( 'Price colour', 'wp-easycart' ), '--wpec-price-color' );
			$this->ec_color_var( 'sale_price_color', __( 'Sale price colour', 'wp-easycart' ), '--wpec-sale-price-color' );
			$this->ec_color_var( 'regular_price_color', __( 'Regular price colour ( crossed out )', 'wp-easycart' ), '--wpec-regular-price-color' );
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_rating',
				array(
					'label'     => __( 'Rating', 'wp-easycart' ),
					'tab'       => $style,
					'condition' => array( 'ec_show_rating' => 'yes' ),
				)
			);
			$this->ec_color_var( 'star_color', __( 'Star colour', 'wp-easycart' ), '--wpec-star-color' );
			$this->ec_color_var( 'star_empty_color', __( 'Empty star colour', 'wp-easycart' ), '--wpec-star-empty-color' );
			$this->add_control(
				'star_size',
				array(
					'label'      => __( 'Star size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 8,
							'max' => 32,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-star-size: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_badges',
				array(
					'label'     => __( 'Badges', 'wp-easycart' ),
					'tab'       => $style,
					'condition' => array(
						'ec_show_image'  => 'yes',
						'ec_show_badges' => 'yes',
					),
				)
			);
			$this->ec_color_var( 'badge_background', __( 'Background colour', 'wp-easycart' ), '--wpec-badge-bg' );
			$this->ec_color_var( 'badge_color', __( 'Text colour', 'wp-easycart' ), '--wpec-badge-color' );
			$this->ec_color_var( 'badge_sale_background', __( 'Sale background colour', 'wp-easycart' ), '--wpec-badge-sale-bg' );
			$this->ec_color_var( 'badge_sale_color', __( 'Sale text colour', 'wp-easycart' ), '--wpec-badge-sale-color' );
			$this->ec_color_var( 'badge_sold_out_background', __( 'Sold out background colour', 'wp-easycart' ), '--wpec-badge-sold-out-bg' );
			$this->ec_color_var( 'badge_sold_out_color', __( 'Sold out text colour', 'wp-easycart' ), '--wpec-badge-sold-out-color' );
			$this->ec_typography( 'badge_typography', '{{WRAPPER}} .wpec-badge', 'accent' );
			$this->add_responsive_control(
				'badge_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-card__badges .wpec-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'badge_position',
				array(
					'label'                => __( 'Corner', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array(
						'start' => array(
							'title' => __( 'Left', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-left',
						),
						'end'   => array(
							'title' => __( 'Right', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'selectors_dictionary' => array(
						'start' => 'inset-inline-start: var( --wpec-badge-offset, 10px ); inset-inline-end: auto; justify-content: flex-start;',
						'end'   => 'inset-inline-start: auto; inset-inline-end: var( --wpec-badge-offset, 10px ); justify-content: flex-end;',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-card__badges' => '{{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				'badge_offset',
				array(
					'label'      => __( 'Distance from the corner', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-badge-offset: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'badge_gap',
				array(
					'label'      => __( 'Space between badges', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 20,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-card__badges' => 'gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'badge_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-badge' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'badge_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'A product’s own tag keeps the colours chosen for it in the product editor.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_swatches',
				array(
					'label'     => __( 'Colour swatches', 'wp-easycart' ),
					'tab'       => $style,
					'condition' => array( 'ec_show_swatches' => 'yes' ),
				)
			);
			$this->add_control(
				'swatch_size',
				array(
					'label'      => __( 'Size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 12,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-swatch-size: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'swatch_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-card__swatches .wpec-swatch img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_button',
				array(
					'label' => __( 'Buttons', 'wp-easycart' ),
					'tab'   => $style,
				)
			);
			$this->ec_typography( 'button_typography', '{{WRAPPER}} .wpec-button', 'accent' );
			$this->start_controls_tabs( 'button_tabs' );
			$this->start_controls_tab( 'button_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->ec_color_var( 'button_color', __( 'Text colour', 'wp-easycart' ), '--wpec-button-color' );
			$this->ec_color_var( 'button_background', __( 'Background colour', 'wp-easycart' ), '--wpec-button-bg' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'button_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->ec_color_var( 'button_hover_color', __( 'Text colour', 'wp-easycart' ), '--wpec-button-hover-color' );
			$this->ec_color_var( 'button_hover_background', __( 'Background colour', 'wp-easycart' ), '--wpec-button-hover-bg' );
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'      => 'button_border',
					'selector'  => '{{WRAPPER}} .wpec-button',
					'separator' => 'before',
				)
			);
			$this->add_responsive_control(
				'button_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'button_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'button_full_width',
				array(
					'label'        => __( 'Full width', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
					'prefix_class' => 'wpec-button-full-',
				)
			);
			$this->add_control(
				'quick_view_heading',
				array(
					'label'     => __( 'Quick view', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array(
						'ec_show_image'      => 'yes',
						'ec_show_quick_view' => 'yes',
					),
				)
			);
			$this->add_control(
				'quick_view_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-card__quick-view' => 'color: {{VALUE}};' ),
					'condition' => array(
						'ec_show_image'      => 'yes',
						'ec_show_quick_view' => 'yes',
					),
				)
			);
			$this->add_control(
				'quick_view_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-card__quick-view' => 'background-color: {{VALUE}};' ),
					'condition' => array(
						'ec_show_image'      => 'yes',
						'ec_show_quick_view' => 'yes',
					),
				)
			);
			$this->end_controls_section();

			$this->ec_register_quick_view_style_controls();
		}

		/**
		 * Style section for the quick view window. The window opens inside the widget ( shop.js ), so {{WRAPPER}} reaches it;
		 * its buttons follow the Buttons section.
		 *
		 * @since 6.0.2
		 */
		protected function ec_register_quick_view_style_controls() {
			$dialog = '{{WRAPPER}} .wpec-qv-dialog';
			$this->start_controls_section(
				'ec_style_quick_view_popup',
				array(
					'label'     => __( 'Quick view window', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'ec_show_image'      => 'yes',
						'ec_show_quick_view' => 'yes',
					),
				)
			);
			$this->add_responsive_control(
				'qv_width',
				array(
					'label'      => __( 'Width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'vw' ),
					'range'      => array(
						'px' => array(
							'min' => 320,
							'max' => 1400,
						),
						'vw' => array(
							'min' => 30,
							'max' => 100,
						),
					),
					'selectors'  => array( $dialog => 'width: min( {{SIZE}}{{UNIT}}, calc( 100vw - 32px ) );' ),
				)
			);
			$this->add_control(
				'qv_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $dialog => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'qv_text_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $dialog => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'qv_backdrop',
				array(
					'label'     => __( 'Page cover colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $dialog . '::backdrop' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'qv_border',
					'selector' => $dialog,
				)
			);
			$this->add_responsive_control(
				'qv_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( $dialog => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'qv_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-qv-dialog__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'qv_shadow',
					'selector' => $dialog,
				)
			);
			$this->add_responsive_control(
				'qv_gap',
				array(
					'label'      => __( 'Space between image and details', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-qv' => 'gap: {{SIZE}}{{UNIT}};' ),
				)
			);

			$this->add_control(
				'qv_close_heading',
				array(
					'label'     => __( 'Close button', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'qv_close_color',
				array(
					'label'     => __( 'Colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-qv-dialog__close' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'qv_close_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-qv-dialog__close' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'qv_close_size',
				array(
					'label'      => __( 'Size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 14,
							'max' => 48,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-qv-dialog__close' => 'font-size: {{SIZE}}{{UNIT}};' ),
				)
			);

			$this->add_control(
				'qv_image_heading',
				array(
					'label'     => __( 'Image', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_responsive_control(
				'qv_image_width',
				array(
					'label'      => __( 'Image column width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( '%' ),
					'range'      => array(
						'%' => array(
							'min' => 25,
							'max' => 70,
						),
					),
					'selectors'  => array( $dialog => '--wpec-qv-media: {{SIZE}}%;' ),
				)
			);
			$this->add_control(
				'qv_image_ratio',
				array(
					'label'     => __( 'Shape', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '',
					'options'   => array(
						''       => __( 'Square', 'wp-easycart' ),
						'4 / 5'  => __( 'Portrait 4:5', 'wp-easycart' ),
						'3 / 4'  => __( 'Portrait 3:4', 'wp-easycart' ),
						'4 / 3'  => __( 'Landscape 4:3', 'wp-easycart' ),
						'16 / 9' => __( 'Wide 16:9', 'wp-easycart' ),
						'auto'   => __( 'As uploaded', 'wp-easycart' ),
					),
					'selectors' => array( '{{WRAPPER}} .wpec-qv__media' => 'aspect-ratio: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'qv_image_fit',
				array(
					'label'     => __( 'Fit', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '',
					'options'   => array(
						''      => __( 'Show the whole image', 'wp-easycart' ),
						'cover' => __( 'Fill the shape ( crop )', 'wp-easycart' ),
					),
					'selectors' => array( '{{WRAPPER}} .wpec-qv .wpec-qv__image' => 'object-fit: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'qv_image_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-qv__media' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'qv_image_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-qv__media' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);

			$this->add_control(
				'qv_text_heading',
				array(
					'label'     => __( 'Details', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_typography( 'qv_title_typography', '{{WRAPPER}} .wpec-qv__title', 'primary' );
			$this->add_control(
				'qv_title_color',
				array(
					'label'     => __( 'Name colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-qv__title, {{WRAPPER}} .wpec-qv__title a' => 'color: {{VALUE}};' ),
				)
			);
			$this->ec_typography( 'qv_price_typography', '{{WRAPPER}} .wpec-qv__price', 'text' );
			$this->add_control(
				'qv_price_color',
				array(
					'label'     => __( 'Price colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $dialog => '--wpec-price-color: {{VALUE}};' ),
				)
			);
			$this->ec_typography( 'qv_excerpt_typography', '{{WRAPPER}} .wpec-qv__excerpt', 'text' );
			$this->add_control(
				'qv_excerpt_color',
				array(
					'label'     => __( 'Description colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-qv__excerpt' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'qv_details_color',
				array(
					'label'     => __( '“View full details” link colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-qv__details' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'qv_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The window’s Add to cart button follows the Buttons section.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Style section for the optional heading.
		 */
		protected function ec_register_heading_style_controls() {
			$this->start_controls_section(
				'ec_style_heading',
				array(
					'label'     => __( 'Heading', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_heading!' => '' ),
				)
			);
			$this->ec_typography( 'heading_typography', '{{WRAPPER}} .wpec-products__heading', 'primary' );
			$this->add_control(
				'heading_color',
				array(
					'label'     => __( 'Colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-products__heading, {{WRAPPER}} .wpec-products__heading a' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'heading_desc_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-products__desc' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'heading_align',
				array(
					'label'     => __( 'Alignment', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'options'   => array(
						'left'   => array(
							'title' => __( 'Left', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center' => array(
							'title' => __( 'Center', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-center',
						),
						'right'  => array(
							'title' => __( 'Right', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-right',
						),
					),
					'selectors' => array( '{{WRAPPER}} .wpec-products__header' => 'text-align: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'heading_spacing',
				array(
					'label'      => __( 'Space below', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-products__header' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Style section for page numbers and "Load more".
		 *
		 * @param array $condition Section condition.
		 */
		protected function ec_register_pagination_style_controls( $condition = array() ) {
			$args = array(
				'label' => __( 'Page numbers', 'wp-easycart' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			);
			if ( ! empty( $condition ) ) {
				$args['condition'] = $condition;
			}
			$this->start_controls_section( 'ec_style_pagination', $args );
			$this->ec_typography( 'pagination_typography', '{{WRAPPER}} .wpec-pagination', 'text' );
			$link    = '{{WRAPPER}} .wpec-pagination a';
			$hover   = '{{WRAPPER}} .wpec-pagination a:hover, {{WRAPPER}} .wpec-pagination a:focus-visible';
			$current = '{{WRAPPER}} .wpec-pagination [aria-current]';
			$color   = function ( $name, $label, $selector, $property ) {
				$this->add_control(
					$name,
					array(
						'label'     => $label,
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $selector => $property . ': {{VALUE}};' ),
					)
				);
			};
			$this->start_controls_tabs( 'pagination_tabs' );
			$this->start_controls_tab( 'pagination_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$color( 'pagination_color', __( 'Colour', 'wp-easycart' ), $link, 'color' );
			$color( 'pagination_background', __( 'Background colour', 'wp-easycart' ), $link, 'background-color' );
			$color( 'pagination_border_color', __( 'Border colour', 'wp-easycart' ), $link, 'border-color' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'pagination_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$color( 'pagination_hover_color', __( 'Colour', 'wp-easycart' ), $hover, 'color' );
			$color( 'pagination_hover_background', __( 'Background colour', 'wp-easycart' ), $hover, 'background-color' );
			$color( 'pagination_hover_border_color', __( 'Border colour', 'wp-easycart' ), $hover, 'border-color' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'pagination_tab_current', array( 'label' => __( 'Current', 'wp-easycart' ) ) );
			$this->add_control(
				'pagination_active_color',
				array(
					'label'     => __( 'Current page colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $current => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'pagination_active_background',
				array(
					'label'     => __( 'Current page background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $current => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ),
				)
			);
			$color( 'pagination_active_border_color', __( 'Current page border colour', 'wp-easycart' ), $current, 'border-color' );
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_responsive_control(
				'pagination_border_width',
				array(
					'label'      => __( 'Border width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 6,
						),
					),
					'selectors'  => array( $link . ', ' . $current => 'border-width: {{SIZE}}{{UNIT}};' ),
					'separator'  => 'before',
				)
			);
			$this->add_responsive_control(
				'pagination_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( $link . ', ' . $current => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'pagination_size',
				array(
					'label'      => __( 'Button size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 24,
							'max' => 72,
						),
					),
					'selectors'  => array( $link . ', ' . $current => 'min-width: {{SIZE}}{{UNIT}}; min-height: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'pagination_gap',
				array(
					'label'      => __( 'Space between', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pagination' => 'gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'pagination_spacing',
				array(
					'label'      => __( 'Space above', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 100,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pagination, {{WRAPPER}} .wpec-products__more' => 'margin-top: {{SIZE}}{{UNIT}};' ),
				)
			);
			$color( 'pagination_dots_color', __( 'Dots ( … ) colour', 'wp-easycart' ), '{{WRAPPER}} .wpec-pagination__gap', 'color' );
			$this->add_control(
				'pagination_align',
				array(
					'label'     => __( 'Alignment', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'options'   => array(
						'flex-start' => array(
							'title' => __( 'Left', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center'     => array(
							'title' => __( 'Center', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-center',
						),
						'flex-end'   => array(
							'title' => __( 'Right', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-right',
						),
					),
					'selectors' => array( '{{WRAPPER}} .wpec-pagination, {{WRAPPER}} .wpec-products__more' => 'justify-content: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * The heading above the products.
		 *
		 * @param array $settings Widget settings.
		 */
		protected function ec_render_heading( $settings ) {
			$heading = isset( $settings['ec_heading'] ) ? trim( (string) $settings['ec_heading'] ) : '';
			if ( '' === $heading ) {
				return;
			}
			$tag = ( isset( $settings['ec_heading_tag'] ) && in_array( $settings['ec_heading_tag'], array( 'h2', 'h3', 'h4', 'div' ), true ) ) ? $settings['ec_heading_tag'] : 'h2';
			echo '<div class="wpec-products__header">';
			echo '<' . $tag . ' class="wpec-products__heading">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is one of a fixed list.
			$url = ( isset( $settings['ec_heading_link']['url'] ) ) ? trim( (string) $settings['ec_heading_link']['url'] ) : '';
			if ( '' !== $url ) {
				$new_tab  = ! empty( $settings['ec_heading_link']['is_external'] );
				$nofollow = ! empty( $settings['ec_heading_link']['nofollow'] );
				$rel      = trim( ( $new_tab ? 'noopener' : '' ) . ( $nofollow ? ' nofollow' : '' ) );
				echo '<a href="' . esc_url( $url ) . '"' . ( $new_tab ? ' target="_blank"' : '' ) . ( '' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '' ) . '>' . esc_html( $heading ) . '</a>';
			} else {
				echo esc_html( $heading );
			}
			echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is one of a fixed list.
			$desc = isset( $settings['ec_heading_desc'] ) ? trim( (string) $settings['ec_heading_desc'] ) : '';
			if ( '' !== $desc ) {
				echo '<p class="wpec-products__desc">' . nl2br( esc_html( $desc ) ) . '</p>';
			}
			echo '</div>';
		}

		/**
		 * Page numbers.
		 *
		 * @param int      $current Current page.
		 * @param int      $pages   Number of pages.
		 * @param callable $url     Page number => URL.
		 */
		public static function ec_render_pagination( $current, $pages, $url ) {
			if ( $pages < 2 ) {
				return;
			}
			$current = max( 1, min( $pages, (int) $current ) );
			$numbers = array( 1, $pages );
			for ( $i = $current - 2; $i <= $current + 2; $i++ ) {
				if ( $i > 1 && $i < $pages ) {
					$numbers[] = $i;
				}
			}
			$numbers = array_unique( $numbers );
			sort( $numbers );
			echo '<nav class="wpec-pagination" aria-label="' . esc_attr( WP_EasyCart_Elementor_Shop::text( 'shop_page_nav', 'Product pages' ) ) . '">';
			if ( $current > 1 ) {
				echo '<a class="wpec-pagination__prev" href="' . esc_url( call_user_func( $url, $current - 1 ) ) . '" rel="prev"><span aria-hidden="true">&lsaquo;</span><span class="wpec-sr-only">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_previous', 'Previous page' ) ) . '</span></a>';
			}
			$last = 0;
			foreach ( $numbers as $number ) {
				if ( $last && $number > $last + 1 ) {
					echo '<span class="wpec-pagination__gap" aria-hidden="true">&hellip;</span>';
				}
				$label = WP_EasyCart_Elementor_Shop::fill( WP_EasyCart_Elementor_Shop::text( 'shop_page', 'Page [page]' ), array( 'page' => $number ) );
				if ( $number === $current ) {
					echo '<span class="wpec-pagination__page" aria-current="page"><span class="wpec-sr-only">' . esc_html( $label ) . '</span><span aria-hidden="true">' . esc_html( $number ) . '</span></span>';
				} else {
					echo '<a class="wpec-pagination__page" href="' . esc_url( call_user_func( $url, $number ) ) . '"><span class="wpec-sr-only">' . esc_html( $label ) . '</span><span aria-hidden="true">' . esc_html( $number ) . '</span></a>';
				}
				$last = $number;
			}
			if ( $current < $pages ) {
				echo '<a class="wpec-pagination__next" href="' . esc_url( call_user_func( $url, $current + 1 ) ) . '" rel="next"><span class="wpec-sr-only">' . esc_html( WP_EasyCart_Elementor_Shop::text( 'shop_next', 'Next page' ) ) . '</span><span aria-hidden="true">&rsaquo;</span></a>';
			}
			echo '</nav>';
		}

		/**
		 * A polite live region for add-to-cart and "Load more" announcements.
		 */
		protected function ec_render_live_region() {
			echo '<div class="wpec-sr-only" aria-live="polite" data-wpec-live></div>';
		}

		/**
		 * An editor-only line above the widget ( a sample is shown ), or nothing for visitors.
		 *
		 * @param string $note Note.
		 */
		protected function ec_editor_hint( $note ) {
			if ( '' !== $note && $this->ec_is_editor() ) {
				echo '<p class="wpec-el-hint" role="note">' . esc_html( $note ) . '</p>';
			}
		}
	}

endif;
