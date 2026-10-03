<?php
/**
 * Product Tabs widget ( wp_easycart_product_tabs, 6.0.2 ).
 *
 * Description, Specifications and Reviews in tabs the merchant reorders, renames and hides, plus their own tabs ( text or a
 * saved Elementor template ) and the tabs extensions add ( WP EasyCart Tabs ). Horizontal, vertical or an accordion, chosen per
 * device ( an accordion on phones by default ). Keyboard and screen readers follow the ARIA tabs pattern, as the product
 * page's tabs do ( product-info.js ). With "Follow each product's settings" on ( the default ) Specifications and Reviews show
 * only where the product has them switched on, and a tab with nothing in it is left out. Replaces
 * wp_easycart_product_details_tabs.
 *
 * WP EasyCart Tabs 3 ( round 11 ): with "Use the WP EasyCart Tabs design" on ( shown while that extension draws the product
 * pages' tab area, on by default ) the widget hands its tabs to the extension the way the product templates do
 * ( wp_easycart_product_tabs_takeover(), wp_easycart_product_tabs_panel(), wp_easycart_product_tabs_capture_gaps(),
 * wp_easycart_product_tabs_area() ): the extension's style, phone style, density, surface, accent, radius, open and remember
 * settings and its block tabs apply, as on the product pages. Description, Specifications and Reviews go in as the built-in
 * panels ( the widget's names and show switches kept; the reviews panel keeps its ec_details_customer_reviews_tab_* class ),
 * the widget's own text and template tabs as classic tab / panel pairs the extension adopts. Their order follows the
 * extension's tab list. Off, or without the extension, the widget draws its own tabs as before.
 *
 * Round 11 design controls: icons per tab ( before, after or above the name ), accordion open / closed icons and where they
 * sit, the indicator's thickness and side, underline / boxed / pill tabs, tab borders and corners, the tab list's
 * background, accordion spacing, "start all closed" and "allow several open", a panel shadow and a fade or slide as a panel
 * opens.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Tabs_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Product Tabs.
	 */
	class WP_EasyCart_Elementor_Product_Info_Tabs_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-tabs';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_tabs';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Tabs', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-tabs';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'product', 'tabs', 'product tabs', 'data tabs', 'accordion', 'description', 'specifications', 'reviews' );
		}

		/**
		 * The module stylesheet and, with WP EasyCart Tabs, its stylesheets, so Elementor loads them in the head ( and in the
		 * editor's preview ) instead of the extension printing them beside the tabs. Never reads the settings: Elementor also
		 * asks the widget type, which has none.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_values( array_unique( array_merge( parent::get_style_depends(), WP_EasyCart_Product_Info::tabs_asset_handles( 'style' ) ) ) );
		}

		/**
		 * Script: tabs and accordion; and WP EasyCart Tabs' script when it is active ( its tab area, block tabs, size guide link ).
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_values( array_unique( array_merge( $this->pi_script_depends(), WP_EasyCart_Product_Info::tabs_asset_handles( 'script' ) ) ) );
		}

		/**
		 * Saved Elementor templates a tab can show ( only built in the editor; the storefront never needs the list ).
		 *
		 * @return array
		 */
		private function template_options() {
			$options = array( '' => __( '— Choose a template —', 'wp-easycart' ) );
			if ( ! is_admin() ) {
				return $options;
			}
			$ids = get_posts(
				array(
					'post_type'        => 'elementor_library',
					'post_status'      => 'publish',
					'numberposts'      => 100,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'fields'           => 'ids',
					'suppress_filters' => false,
					'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- editor only, at most 100 templates.
						array(
							'key'     => '_elementor_template_type',
							'value'   => array( 'section', 'container', 'page', 'widget' ),
							'compare' => 'IN',
						),
					),
				)
			);
			foreach ( (array) $ids as $id ) {
				$title          = get_the_title( $id );
				$options[ $id ] = ( '' !== $title ) ? $title : '#' . $id;
			}
			return $options;
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			/* The widget's own layout and look apply only while it draws its own tabs. */
			$own = array( 'tabs_design!' => 'yes' );

			$this->start_controls_section(
				'section_tabs',
				array(
					'label' => __( 'Tabs', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$repeater = new \Elementor\Repeater();
			$repeater->add_control(
				'tab_type',
				array(
					'label'   => __( 'Shows', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'text',
					'options' => array(
						'description'    => __( 'The product’s description', 'wp-easycart' ),
						'specifications' => __( 'The product’s specifications', 'wp-easycart' ),
						'reviews'        => __( 'Reviews and the review form', 'wp-easycart' ),
						'text'           => __( 'Text I write', 'wp-easycart' ),
						'template'       => __( 'A saved template', 'wp-easycart' ),
					),
				)
			);
			$repeater->add_control(
				'tab_title',
				array(
					'label'       => __( 'Tab name', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Leave empty for the usual name', 'wp-easycart' ),
					'label_block' => true,
					'ai'          => array( 'active' => false ),
				)
			);
			$repeater->add_control(
				'tab_icon',
				array(
					'label'       => __( 'Icon', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::ICONS,
					'default'     => array(
						'value'   => '',
						'library' => '',
					),
					'skin'        => 'inline',
					'description' => __( 'Shows beside the tab name ( Layout › Icon position ).', 'wp-easycart' ),
				)
			);
			$repeater->add_control(
				'tab_content',
				array(
					'label'     => __( 'Text', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::WYSIWYG,
					'default'   => '',
					'condition' => array( 'tab_type' => 'text' ),
				)
			);
			$repeater->add_control(
				'tab_template',
				array(
					'label'       => __( 'Template', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'options'     => $this->template_options(),
					'description' => __( 'Save a section as a template ( right-click it › Save as template ), then pick it here.', 'wp-easycart' ),
					'condition'   => array( 'tab_type' => 'template' ),
				)
			);
			$repeater->add_control(
				'tab_show',
				array(
					'label'   => __( 'Show this tab', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'tabs',
				array(
					'label'       => __( 'Tabs', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::REPEATER,
					'fields'      => $repeater->get_controls(),
					'default'     => array(
						array(
							'tab_type' => 'description',
							'tab_show' => 'yes',
						),
						array(
							'tab_type' => 'specifications',
							'tab_show' => 'yes',
						),
						array(
							'tab_type' => 'reviews',
							'tab_show' => 'yes',
						),
					),
					'title_field' => '<# var wpecPiNames = { description: "' . esc_js( __( 'Description', 'wp-easycart' ) ) . '", specifications: "' . esc_js( __( 'Specifications', 'wp-easycart' ) ) . '", reviews: "' . esc_js( __( 'Reviews', 'wp-easycart' ) ) . '", text: "' . esc_js( __( 'Text', 'wp-easycart' ) ) . '", template: "' . esc_js( __( 'Template', 'wp-easycart' ) ) . '" }; #>{{{ tab_title ? tab_title : wpecPiNames[ tab_type ] }}}<# if ( "yes" !== tab_show ) { #> ( ' . esc_js( __( 'hidden', 'wp-easycart' ) ) . ' )<# } #>',
					'description' => __( 'Drag to reorder. Switch a tab off to hide it; add your own tabs with Add Item.', 'wp-easycart' ),
				)
			);
			$this->register_tabs_design_controls();
			$this->pi_follow_control( __( 'Hide Specifications and Reviews on products where they are switched off in the product editor, and leave out any tab with nothing in it.', 'wp-easycart' ) );
			$this->add_control(
				'reviews_count',
				array(
					'label'   => __( 'Number of reviews in the Reviews tab name', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'extension_tabs',
				array(
					'label'       => __( 'Tabs from extensions', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Adds the tabs extensions such as WP EasyCart Tabs give each product.', 'wp-easycart' ),
					'condition'   => $own,
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_layout',
				array(
					'label'     => __( 'Layout', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
					'condition' => $own,
				)
			);
			$this->add_responsive_control(
				'layout',
				array(
					'label'                => __( 'Show as', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => 'horizontal',
					'mobile_default'       => 'accordion',
					'options'              => array(
						'horizontal' => __( 'Tabs in a row', 'wp-easycart' ),
						'vertical'   => __( 'Tabs down the side', 'wp-easycart' ),
						'accordion'  => __( 'Accordion', 'wp-easycart' ),
					),
					/* Round 11: the indicator's thickness is a variable ( Style › Tabs › Indicator thickness ), 2px as before. */
					'selectors_dictionary' => array(
						'horizontal' => '--wpec-pi-tabs-mode: tabs; --wpec-pi-tabs-dir: column; --wpec-pi-tabs-list-display: flex; --wpec-pi-tabs-list-dir: row; --wpec-pi-tabs-list-width: auto; --wpec-pi-tabs-acc-display: none; --wpec-pi-tabs-ind-b: var(--wpec-pi-ind-w, 2px); --wpec-pi-tabs-ind-s: 0px; --wpec-pi-tabs-list-bb: 1px; --wpec-pi-tabs-list-be: 0px;',
						'vertical'   => '--wpec-pi-tabs-mode: tabs; --wpec-pi-tabs-dir: row; --wpec-pi-tabs-list-display: flex; --wpec-pi-tabs-list-dir: column; --wpec-pi-tabs-list-width: var(--wpec-pi-tabs-side-width, 220px); --wpec-pi-tabs-acc-display: none; --wpec-pi-tabs-ind-b: 0px; --wpec-pi-tabs-ind-s: var(--wpec-pi-ind-w, 2px); --wpec-pi-tabs-list-bb: 0px; --wpec-pi-tabs-list-be: 1px;',
						'accordion'  => '--wpec-pi-tabs-mode: accordion; --wpec-pi-tabs-dir: column; --wpec-pi-tabs-list-display: none; --wpec-pi-tabs-list-dir: column; --wpec-pi-tabs-list-width: auto; --wpec-pi-tabs-acc-display: flex;',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-pi-tabs' => '{{VALUE}}' ),
					'description'          => __( 'Choose per device with the device icon; phones get an accordion unless you change it.', 'wp-easycart' ),
				)
			);
			$this->add_responsive_control(
				'side_width',
				array(
					'label'       => __( 'Tab column width', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px', '%' ),
					'range'       => array(
						'px' => array(
							'min' => 100,
							'max' => 500,
						),
						'%'  => array(
							'min' => 10,
							'max' => 60,
						),
					),
					'selectors'   => array( '{{WRAPPER}} .wpec-pi-tabs' => '--wpec-pi-tabs-side-width: {{SIZE}}{{UNIT}};' ),
					'description' => __( 'For tabs down the side.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'tab_style',
				array(
					'label'       => __( 'Tab style', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'underline',
					'separator'   => 'before',
					'options'     => array(
						'underline' => __( 'Underlined', 'wp-easycart' ),
						'boxed'     => __( 'Boxed', 'wp-easycart' ),
						'pills'     => __( 'Pills', 'wp-easycart' ),
					),
					'description' => __( 'For tabs in a row or down the side; an accordion keeps its rows.', 'wp-easycart' ),
				)
			);
			$this->add_responsive_control(
				'icon_position',
				array(
					'label'                => __( 'Icon position', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array(
						'start' => array(
							'title' => __( 'Before the name', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-left',
						),
						'top'   => array(
							'title' => __( 'Above the name', 'wp-easycart' ),
							'icon'  => 'eicon-v-align-top',
						),
						'end'   => array(
							'title' => __( 'After the name', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'selectors_dictionary' => array(
						'start' => '--wpec-pi-tab-icon-order: 0; --wpec-pi-tab-dir: row;',
						'top'   => '--wpec-pi-tab-icon-order: 0; --wpec-pi-tab-dir: column;',
						'end'   => '--wpec-pi-tab-icon-order: 2; --wpec-pi-tab-dir: row;',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-pi-tabs' => '{{VALUE}}' ),
					'description'          => __( 'For tabs with an icon ( Tabs › each tab › Icon ).', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'accordion_heading',
				array(
					'label'     => __( 'Accordion', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'accordion_start',
				array(
					'label'   => __( 'When the page opens', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'first',
					'options' => array(
						'first'  => __( 'The first one is open', 'wp-easycart' ),
						'closed' => __( 'All are closed', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'accordion_multiple',
				array(
					'label'       => __( 'Allow several open', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Off, opening one closes the others.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'acc_icon',
				array(
					'label'       => __( 'Icon', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::ICONS,
					'default'     => array(
						'value'   => '',
						'library' => '',
					),
					'skin'        => 'inline',
					'description' => __( 'Leave empty for the arrow. It turns over as a row opens, unless you pick an icon for open rows too.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'acc_icon_active',
				array(
					'label'   => __( 'Icon while open', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::ICONS,
					'default' => array(
						'value'   => '',
						'library' => '',
					),
					'skin'    => 'inline',
				)
			);
			$this->add_control(
				'acc_icon_position',
				array(
					'label'                => __( 'Icon side', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'default'              => 'end',
					'toggle'               => false,
					'options'              => array(
						'start' => array(
							'title' => __( 'Start', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-left',
						),
						'end'   => array(
							'title' => __( 'End', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'selectors_dictionary' => array(
						'start' => '--wpec-pi-acc-marker-order: -1;',
						'end'   => '--wpec-pi-acc-marker-order: 3;',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-pi-tabs' => '{{VALUE}}' ),
				)
			);
			$this->add_control(
				'panel_animation',
				array(
					'label'       => __( 'As a tab opens', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'separator'   => 'before',
					'options'     => array(
						''      => __( 'Show at once', 'wp-easycart' ),
						'fade'  => __( 'Fade in', 'wp-easycart' ),
						'slide' => __( 'Slide up', 'wp-easycart' ),
					),
					'description' => __( 'Shoppers who ask their device for less motion always get it at once.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->register_style_controls();
		}

		/**
		 * "Use the WP EasyCart Tabs design" and its note. While WP EasyCart Tabs cannot draw the tab area ( not active, or its
		 * style is Classic ) the setting stays as a hidden empty value, so the widget's own layout and style show.
		 */
		private function register_tabs_design_controls() {
			if ( ! WP_EasyCart_Product_Info::tabs_design_available() ) {
				$this->add_control(
					'tabs_design',
					array(
						'type'    => \Elementor\Controls_Manager::HIDDEN,
						'default' => '',
					)
				);
				return;
			}
			$this->add_control(
				'tabs_design',
				array(
					'label'       => __( 'Use the WP EasyCart Tabs design', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'separator'   => 'before',
					'description' => __( 'Draws these tabs as your product pages do, in the style chosen for WP EasyCart Tabs, with each product’s tabs from that extension.', 'wp-easycart' ),
				)
			);
			$this->pi_note(
				'tabs_design_note',
				esc_html__( 'The look, the phone style and which tab opens come from WP EasyCart Tabs, so this widget’s Layout and Style settings are hidden. Tab order follows each product’s tab list; your own tabs here come after it.', 'wp-easycart' ) . ' <a href="' . esc_url( WP_EasyCart_Product_Info::tabs_settings_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Change the tab design', 'wp-easycart' ) . '</a>',
				array( 'condition' => array( 'tabs_design' => 'yes' ) )
			);
		}

		/**
		 * Style tab.
		 */
		protected function register_style_controls() {
			$own = array( 'tabs_design!' => 'yes' );
			/* Round 11: selectors outweigh the module's tab and accordion rules ( .wpec-pi-tabs .wpec-pi-tabs__tab ). */
			$tab_selector = '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__tab, {{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__acc';

			$this->start_controls_section(
				'section_tabs_style',
				array(
					'label'     => __( 'Tabs', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => $own,
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-tabs__list', 'flex', 'tabs_align', true );
			$this->add_responsive_control(
				'tabs_gap',
				array(
					'label'      => __( 'Space between tabs', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 60,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-tabs__list' => 'gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'tab_typography',
					'global'   => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT ),
					'selector' => $tab_selector,
				)
			);
			$this->add_responsive_control(
				'tab_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( $tab_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->start_controls_tabs( 'tab_colors' );
			$this->start_controls_tab( 'tab_colors_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->add_control(
				'tab_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ),
					'selectors' => array( $tab_selector => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'tab_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $tab_selector => 'background-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'tab_colors_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->add_control(
				'tab_hover_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__tab:hover, {{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__acc:hover' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'tab_hover_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__tab:hover, {{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__acc:hover' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'tab_hover_border_color',
				array(
					'label'     => __( 'Border color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__tab:hover:not(.is-active)' => 'border-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'tab_colors_active', array( 'label' => __( 'Open', 'wp-easycart' ) ) );
			$this->add_control(
				'tab_active_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY ),
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__tab.is-active, {{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__acc[aria-expanded="true"]' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'tab_active_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__tab.is-active, {{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__acc[aria-expanded="true"]' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'tab_indicator_color',
				array(
					'label'     => __( 'Underline color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ),
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs' => '--wpec-pi-tabs-accent: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_control(
				'tabs_border_color',
				array(
					'label'     => __( 'Divider color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => 'before',
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs' => '--wpec-pi-tabs-border: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'indicator_heading',
				array(
					'label'     => __( 'Indicator', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_responsive_control(
				'indicator_position',
				array(
					'label'                => __( 'Where', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => '',
					'options'              => array(
						''      => __( 'Below in a row, beside down the side', 'wp-easycart' ),
						'below' => __( 'Below the name', 'wp-easycart' ),
						'above' => __( 'Above the name', 'wp-easycart' ),
						'side'  => __( 'Beside the name', 'wp-easycart' ),
						'none'  => __( 'None', 'wp-easycart' ),
					),
					'selectors_dictionary' => array(
						'below' => '--wpec-pi-ind-below: var(--wpec-pi-ind-w, 2px); --wpec-pi-ind-above: 0px; --wpec-pi-ind-side: 0px;',
						'above' => '--wpec-pi-ind-below: 0px; --wpec-pi-ind-above: var(--wpec-pi-ind-w, 2px); --wpec-pi-ind-side: 0px;',
						'side'  => '--wpec-pi-ind-below: 0px; --wpec-pi-ind-above: 0px; --wpec-pi-ind-side: var(--wpec-pi-ind-w, 2px);',
						'none'  => '--wpec-pi-ind-below: 0px; --wpec-pi-ind-above: 0px; --wpec-pi-ind-side: 0px;',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-pi-tabs' => '{{VALUE}}' ),
					'description'          => __( 'For underlined tabs; the open tab’s line in the Underline color.', 'wp-easycart' ),
				)
			);
			$this->pi_size_control( 'indicator_width', __( 'Thickness', 'wp-easycart' ), '{{WRAPPER}} .wpec-pi-tabs', '--wpec-pi-ind-w', 10, array( 'size_units' => array( 'px' ) ) );
			$this->add_control(
				'tab_box_heading',
				array(
					'label'     => __( 'Each tab', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'tab_border',
					'selector' => '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__tab',
				)
			);
			$this->add_responsive_control(
				'tab_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__tab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'tab_list_heading',
				array(
					'label'     => __( 'Tab row', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'tab_list_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__list' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'tab_list_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__list' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'tab_list_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__list' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'tab_icon_heading',
				array(
					'label'     => __( 'Tab icons', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_size_control( 'tab_icon_size', __( 'Size', 'wp-easycart' ), '{{WRAPPER}} .wpec-pi-tabs', '--wpec-pi-tab-icon', 60 );
			$this->pi_size_control( 'tab_icon_spacing', __( 'Space beside the name', 'wp-easycart' ), '{{WRAPPER}} .wpec-pi-tabs', '--wpec-pi-tab-icon-gap', 40 );
			$this->add_control(
				'tab_transition',
				array(
					'label'       => __( 'Color change time ( ms )', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'separator'   => 'before',
					'range'       => array(
						'px' => array(
							'min'  => 0,
							'max'  => 1000,
							'step' => 50,
						),
					),
					'selectors'   => array( '{{WRAPPER}} .wpec-pi-tabs' => '--wpec-pi-tabs-transition: {{SIZE}}ms;' ),
					'description' => __( 'How long a tab takes to change color as the pointer moves over it or it opens.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_accordion_style',
				array(
					'label'     => __( 'Accordion', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => $own,
				)
			);
			$this->pi_note( 'accordion_style_note', esc_html__( 'Where the tabs show as an accordion ( Layout › Show as ). Colors, text and padding come from Tabs.', 'wp-easycart' ) );
			$this->pi_size_control( 'acc_gap', __( 'Space between rows', 'wp-easycart' ), '{{WRAPPER}} .wpec-pi-tabs', '--wpec-pi-acc-gap', 60, array( 'size_units' => array( 'px', 'em' ) ) );
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'acc_border',
					'selector' => '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__acc',
				)
			);
			$this->add_responsive_control(
				'acc_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-tabs .wpec-pi-tabs__acc' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'acc_icon_heading',
				array(
					'label'     => __( 'Icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_size_control( 'acc_icon_size', __( 'Size', 'wp-easycart' ), '{{WRAPPER}} .wpec-pi-tabs', '--wpec-pi-acc-icon', 48 );
			$this->add_control(
				'acc_icon_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs' => '--wpec-pi-acc-icon-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'acc_icon_open_color',
				array(
					'label'     => __( 'Color while open', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs' => '--wpec-pi-acc-icon-open-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_panel_style',
				array(
					'label'     => __( 'Tab content', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => $own,
				)
			);
			$this->pi_text_controls( 'panel', '{{WRAPPER}} .wpec-pi-tabs__panel' );
			$this->add_control(
				'panel_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs__panel' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'panel_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-tabs__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'panel_border',
					'selector' => '{{WRAPPER}} .wpec-pi-tabs__panel',
				)
			);
			$this->add_responsive_control(
				'panel_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-tabs__panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'panel_shadow',
					'selector' => '{{WRAPPER}} .wpec-pi-tabs__panel',
				)
			);
			$this->add_control(
				'panel_animation_duration',
				array(
					'label'     => __( 'Fade or slide time ( ms )', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'range'     => array(
						'px' => array(
							'min'  => 100,
							'max'  => 1500,
							'step' => 50,
						),
					),
					'selectors' => array( '{{WRAPPER}} .wpec-pi-tabs' => '--wpec-pi-tabs-anim: {{SIZE}}ms;' ),
					'condition' => array( 'panel_animation!' => '' ),
				)
			);
			$this->end_controls_section();

			if ( WP_EasyCart_Product_Info::tabs_design_available() ) {
				$this->start_controls_section(
					'section_tabs_design_style',
					array(
						'label'     => __( 'WP EasyCart Tabs design', 'wp-easycart' ),
						'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
						'condition' => array( 'tabs_design' => 'yes' ),
					)
				);
				$this->pi_note(
					'tabs_design_style_note',
					esc_html__( 'These tabs follow the design chosen for WP EasyCart Tabs ( style, phone style, spacing, surface, accent color and corners ), like your product pages.', 'wp-easycart' ) . ' <a href="' . esc_url( WP_EasyCart_Product_Info::tabs_settings_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Change the tab design', 'wp-easycart' ) . '</a>'
				);
				$this->end_controls_section();
			}
		}

		/**
		 * The tabs to show: key, slug, title, base_title ( no count ), renamed, count, type, icon, html.
		 *
		 * @param array      $settings Settings.
		 * @param ec_product $product  Product.
		 * @param int        $rand     Block number.
		 * @return array
		 */
		private function tab_items( $settings, $product, $rand ) {
			$items  = array();
			$follow = WP_EasyCart_Product_Info::on( $settings, 'follow_product' );
			$rows   = ( isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ) ? $settings['tabs'] : array();
			$used   = array();
			foreach ( $rows as $index => $row ) {
				if ( ! is_array( $row ) || ! WP_EasyCart_Product_Info::on( $row, 'tab_show' ) ) {
					continue;
				}
				$type    = ( isset( $row['tab_type'] ) && in_array( $row['tab_type'], array( 'description', 'specifications', 'reviews', 'text', 'template' ), true ) ) ? $row['tab_type'] : 'text';
				$title   = isset( $row['tab_title'] ) ? trim( (string) $row['tab_title'] ) : '';
				$renamed = ( '' !== $title );
				$count   = null;
				$html    = '';
				$slug    = $type;
				switch ( $type ) {
					case 'description':
						$html  = WP_EasyCart_Product_Info::description_html( $product );
						$title = ( '' !== $title ) ? $title : WP_EasyCart_Product_Info::text( 'product_details_description', __( 'Description', 'wp-easycart' ), 'product_details' );
						if ( '' !== $html ) {
							$html = '<div class="wpec-pi-description">' . $html . '</div>';
						}
						break;
					case 'specifications':
						if ( $follow && empty( $product->use_specifications ) ) {
							break;
						}
						$html  = WP_EasyCart_Product_Info::specifications_html( $product );
						$title = ( '' !== $title ) ? $title : WP_EasyCart_Product_Info::text( 'product_details_specifications', __( 'Specifications', 'wp-easycart' ), 'product_details' );
						if ( '' !== $html ) {
							$html = '<div class="wpec-pi-specifications">' . $html . '</div>';
						}
						break;
					case 'reviews':
						if ( $follow && empty( $product->use_customer_reviews ) ) {
							break;
						}
						ob_start();
						WP_EasyCart_Product_Info::reviews( $product, array( 'rand' => $rand ) );
						$html  = (string) ob_get_clean();
						$title = ( '' !== $title ) ? $title : WP_EasyCart_Product_Info::text( 'product_details_customer_reviews', __( 'Customer Reviews', 'wp-easycart' ), 'product_details' );
						if ( WP_EasyCart_Product_Info::on( $settings, 'reviews_count' ) ) {
							$rating = WP_EasyCart_Product_Info::rating( $product );
							$count  = (int) $rating['count'];
						}
						$slug = 'reviews';
						break;
					case 'template':
						$html = isset( $row['tab_template'] ) ? WP_EasyCart_Product_Info::elementor_template( (int) $row['tab_template'], $product ) : '';
						break;
					default:
						$html = isset( $row['tab_content'] ) ? trim( (string) $this->parse_text_editor( (string) $row['tab_content'] ) ) : '';
						break;
				}
				if ( '' === trim( wp_strip_all_tags( $html, true ) ) && false === strpos( $html, '<img' ) && false === strpos( $html, '<iframe' ) && false === strpos( $html, '<video' ) ) {
					continue;
				}
				if ( '' === $title ) {
					$title = __( 'More', 'wp-easycart' );
				}
				if ( in_array( $type, array( 'text', 'template' ), true ) ) {
					$slug = sanitize_title( $title );
				}
				$key = sanitize_key( ( isset( $row['_id'] ) && '' !== (string) $row['_id'] ) ? 't' . $row['_id'] : 't' . $index );
				if ( isset( $used[ $key ] ) ) {
					$key .= '-' . $index;
				}
				$used[ $key ] = true;
				$items[]      = array(
					'key'        => $key,
					'type'       => $type,
					'slug'       => ( '' !== $slug ) ? $slug : $key,
					'title'      => ( null !== $count ) ? $title . ' (' . number_format_i18n( $count ) . ')' : $title,
					'base_title' => $title,
					'renamed'    => $renamed,
					'count'      => $count,
					'icon'       => isset( $row['tab_icon'] ) ? $row['tab_icon'] : null,
					'html'       => $html,
				);
			}
			return $items;
		}

		/**
		 * Output.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->pi_product( $settings, false );
			if ( ! $product ) {
				return;
			}
			$rand  = WP_EasyCart_Product_Info::rand();
			$pid   = (int) $product->product_id;
			$uid   = 'wpec-pi-tabs-' . $pid . '-' . $rand;
			$items = $this->tab_items( $settings, $product, $rand );
			foreach ( $items as $item ) {
				if ( 'reviews' === $item['type'] && function_exists( 'wp_easycart_product_details_no_cache' ) ) {
					wp_easycart_product_details_no_cache();
				}
			}

			if ( WP_EasyCart_Product_Info::on( $settings, 'tabs_design' ) && WP_EasyCart_Product_Info::tabs_design_available() && $this->render_tabs_design( $settings, $product, $rand, $items ) ) {
				return;
			}

			$gaps  = array(
				'description'    => '',
				'specifications' => '',
				'reviews'        => '',
				'end'            => '',
			);
			$extra = '';
			/* A setting hidden by "Use the WP EasyCart Tabs design" reads as unset: the default, on. */
			if ( ! isset( $settings['extension_tabs'] ) || WP_EasyCart_Product_Info::on( $settings, 'extension_tabs' ) ) {
				$gaps['description']    = WP_EasyCart_Product_Info::extension_tab_hook( 'wpeasycart_pre_description_tab', $pid, $rand );
				$gaps['specifications'] = WP_EasyCart_Product_Info::extension_tab_hook( 'wpeasycart_pre_specifications_tab', $pid, $rand );
				$gaps['reviews']        = WP_EasyCart_Product_Info::extension_tab_hook( 'wpeasycart_pre_customer_reviews_tab', $pid, $rand );
				$gaps['end']            = WP_EasyCart_Product_Info::extension_tab_hook( 'wpeasycart_addon_product_details_tab', $pid, $rand );
				$extra                  = WP_EasyCart_Product_Info::extension_panel_hook( $pid, $rand );
			}
			$has_extension = ( '' !== trim( implode( '', $gaps ) ) );
			if ( ! $items && ! $has_extension ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( 'No tab has anything to show for %s.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see nothing here. Add a description, specifications or your own tab, or turn off “Follow each product’s settings”.', 'wp-easycart' ) );
				return;
			}

			/*
			 * An extension tab set to open first ( WP EasyCart Tabs: its tab carries ec_active, and it answers the product page's
			 * wpeasycart_description_initally_active filter with '' ) opens instead of this widget's first tab, as on the product
			 * page, with its own panel the only one showing before the script runs.
			 */
			$ext_first = $has_extension
				&& preg_match( '/<li\b[^>]*\bclass\s*=\s*["\'][^"\']*(?<![\w-])ec_active(?![\w-])/i', implode( '', $gaps ) )
				&& '' === trim( (string) apply_filters( 'wpeasycart_description_initally_active', 'ec_active', $pid ) );

			echo '<div class="' . esc_attr( $this->root_classes( $settings, $ext_first ) ) . '" id="' . esc_attr( $uid ) . '" data-wpec-pi-tabs="1" data-product-id="' . esc_attr( $pid ) . '" data-rand-id="' . esc_attr( $rand ) . '"' . $this->root_data( $settings ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- root_data() is fixed markup.
			echo '<ul class="wpec-pi-tabs__list" role="tablist">';
			$first = ! $ext_first;
			foreach ( $items as $item ) {
				if ( isset( $gaps[ $item['type'] ] ) && '' !== $gaps[ $item['type'] ] ) {
					echo $gaps[ $item['type'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- extension tab markup from the product page's own tab hooks.
					$gaps[ $item['type'] ] = '';
				}
				$tab_id   = $uid . '-tab-' . $item['key'];
				$panel_id = $uid . '-panel-' . $item['key'];
				$classes  = 'wpec-pi-tabs__tab' . ( $first ? ' is-active' : '' );
				if ( 'reviews' === $item['type'] ) {
					/* The product page's review-request link ( ec_reviews_native.js ) opens this tab by these classes. */
					$classes .= ' ec_details_tab_' . $pid . '_' . $rand . ' ec_customer_reviews';
				}
				$icon  = $this->pi_icon( $item['icon'], 'wpec-pi-tabs__icon' );
				$label = ( '' !== $icon ) ? $icon . '<span class="wpec-pi-tabs__label">' . esc_html( $item['title'] ) . '</span>' : esc_html( $item['title'] );
				if ( '' !== $icon ) {
					$classes .= ' wpec-pi-tabs__tab--icon';
				}
				echo '<li class="' . esc_attr( $classes ) . '" id="' . esc_attr( $tab_id ) . '" role="tab" aria-controls="' . esc_attr( $panel_id ) . '" aria-selected="' . ( $first ? 'true' : 'false' ) . '" tabindex="' . ( $first ? '0' : '-1' ) . '" data-tab-id="' . esc_attr( $item['key'] ) . '" data-tab-slug="' . esc_attr( $item['slug'] ) . '">' . $label . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $label is escaped above ( Icons_Manager markup ).
				$first = false;
			}
			foreach ( $gaps as $gap ) {
				if ( '' !== $gap ) {
					echo $gap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- extension tab markup from the product page's own tab hooks.
				}
			}
			echo '</ul>';
			echo $this->accordion_icons( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager markup in fixed wrappers.

			echo '<div class="wpec-pi-tabs__panels">';
			$first = ! $ext_first;
			foreach ( $items as $item ) {
				$tab_id   = $uid . '-tab-' . $item['key'];
				$panel_id = $uid . '-panel-' . $item['key'];
				$classes  = 'wpec-pi-tabs__panel wpec-pi-tabs__panel--' . $item['type'] . ( $first ? ' is-open' : '' );
				if ( 'reviews' === $item['type'] ) {
					$classes .= ' ec_details_customer_reviews_tab_' . $pid . '_' . $rand;
				}
				echo '<div class="' . esc_attr( $classes ) . '" id="' . esc_attr( $panel_id ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tab_id ) . '" tabindex="0" data-tab-id="' . esc_attr( $item['key'] ) . '">';
				echo $item['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product content through the store's HTML filter, the escaped reviews block, the merchant's own text ( Elementor's text editor ) or a saved Elementor template.
				echo '</div>';
				$first = false;
			}
			if ( '' !== $extra ) {
				echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- extension panels from the product page's own panel hook.
			}
			echo '</div>';
			echo '</div>';
		}

		/**
		 * The root's classes: style, accordion icons, panel animation.
		 *
		 * @param array $settings  Settings.
		 * @param bool  $ext_first An extension tab opens first.
		 * @return string
		 */
		private function root_classes( $settings, $ext_first ) {
			$classes = 'wpec-el wpec-pi-tabs' . ( $ext_first ? ' wpec-pi-tabs--ext-first' : '' );
			$style   = isset( $settings['tab_style'] ) ? (string) $settings['tab_style'] : '';
			if ( in_array( $style, array( 'boxed', 'pills' ), true ) ) {
				$classes .= ' wpec-pi-tabs--' . $style;
			}
			$closed = WP_EasyCart_Product_Info::icon_html( isset( $settings['acc_icon'] ) ? $settings['acc_icon'] : null );
			$open   = WP_EasyCart_Product_Info::icon_html( isset( $settings['acc_icon_active'] ) ? $settings['acc_icon_active'] : null );
			if ( '' !== $closed || '' !== $open ) {
				$classes .= ' wpec-pi-tabs--acc-icons' . ( ( '' !== $closed && '' !== $open ) ? ' wpec-pi-tabs--acc-swap' : '' );
			}
			$animation = isset( $settings['panel_animation'] ) ? (string) $settings['panel_animation'] : '';
			if ( in_array( $animation, array( 'fade', 'slide' ), true ) ) {
				$classes .= ' wpec-pi-tabs--anim-' . $animation;
			}
			return $classes;
		}

		/**
		 * The root's data attributes for the accordion ( start closed, one open at a time ).
		 *
		 * @param array $settings Settings.
		 * @return string Fixed markup.
		 */
		private function root_data( $settings ) {
			$out = '';
			if ( isset( $settings['accordion_start'] ) && 'closed' === $settings['accordion_start'] ) {
				$out .= ' data-acc-start="closed"';
			}
			if ( isset( $settings['accordion_multiple'] ) && ! WP_EasyCart_Product_Info::on( $settings, 'accordion_multiple' ) ) {
				$out .= ' data-acc-single="1"';
			}
			return $out;
		}

		/**
		 * The accordion's own icons, for the script to copy into each row's button ( '' for the arrow ).
		 *
		 * @param array $settings Settings.
		 * @return string HTML.
		 */
		private function accordion_icons( $settings ) {
			$closed = WP_EasyCart_Product_Info::icon_html( isset( $settings['acc_icon'] ) ? $settings['acc_icon'] : null );
			$open   = WP_EasyCart_Product_Info::icon_html( isset( $settings['acc_icon_active'] ) ? $settings['acc_icon_active'] : null );
			if ( '' === $closed && '' === $open ) {
				return '';
			}
			$out = '<span class="wpec-pi-tabs__acc-icons" hidden>';
			if ( '' !== $closed ) {
				$out .= '<span class="wpec-pi-tabs__acc-icon wpec-pi-tabs__acc-icon--closed" aria-hidden="true">' . $closed . '</span>';
			}
			if ( '' !== $open ) {
				$out .= '<span class="wpec-pi-tabs__acc-icon wpec-pi-tabs__acc-icon--open" aria-hidden="true">' . $open . '</span>';
			}
			return $out . '</span>';
		}

		/**
		 * Hand the tabs to WP EasyCart Tabs ( "Use the WP EasyCart Tabs design" ), the way the product templates do. Returns
		 * false when the extension declines this product ( the widget then draws its own tabs ).
		 *
		 * The widget's Description, Specifications and Reviews go in as the built-in panels ( WP EasyCart Tabs 3.1.2 keeps the widget's order, older 3.1 releases the product's tab list, decides
		 * where they sit; the widget's names and count switch are kept ), its own text and template tabs as classic tab / panel
		 * pairs ( li.ec_details_tab[data-tab-id] + div.ec_details_<id>_tab ) ahead of the other extensions' tabs, which the
		 * extension adopts as tabs of the area. The panels carry the product page's panel classes, so WP EasyCart's classic
		 * tab list still works if nothing draws the area ( wp_easycart_product_tabs_classic() ).
		 *
		 * @param array      $settings Settings.
		 * @param ec_product $product  Product.
		 * @param int        $rand     Block number.
		 * @param array      $items    tab_items().
		 * @return bool
		 */
		private function render_tabs_design( $settings, $product, $rand, $items ) {
			$pid   = (int) $product->product_id;
			$types = array();
			$order = array();
			foreach ( $items as $item ) {
				$types[ $item['type'] ] = true;
				$order[]                = in_array( $item['type'], array( 'description', 'specifications', 'reviews' ), true ) ? $item['type'] : 'el-' . $item['key'];
			}

			/*
			 * elementor and order ( the widget's tabs in its own order: built-in keys, el-<key> for its own ); WP EasyCart Tabs
			 * 3.1.2 puts the area in this order ( older 3.1 releases use each product's tab list ).
			 */
			$atts = array(
				'show_description'      => isset( $types['description'] ),
				'show_specifications'   => isset( $types['specifications'] ),
				'show_customer_reviews' => isset( $types['reviews'] ),
				'elementor'             => true,
				'order'                 => $order,
			);
			if ( ! wp_easycart_product_tabs_takeover( $product, $rand, $atts ) ) {
				return false;
			}
			$classes    = array(
				'description'    => 'wpec-pi-tabs-builtin ec_details_description_tab_' . $pid . '_' . $rand,
				'specifications' => 'wpec-pi-tabs-builtin ec_details_specifications_tab_' . $pid . '_' . $rand,
				'reviews'        => 'wpec-pi-tabs-builtin ec_details_customer_reviews_tab_' . $pid . '_' . $rand,
			);
			$panels     = array();
			$own_tabs   = '';
			$own_panels = '';
			foreach ( $items as $item ) {
				if ( isset( $classes[ $item['type'] ] ) ) {
					if ( isset( $panels[ $item['type'] ] ) ) {
						continue;
					}
					$panel = wp_easycart_product_tabs_panel( $item['type'], '<div class="' . esc_attr( $classes[ $item['type'] ] ) . '">' . $item['html'] . '</div>', $product );
					if ( ! is_array( $panel ) ) {
						continue;
					}
					if ( $item['renamed'] || ! isset( $panel['title'] ) || '' === trim( wp_strip_all_tags( (string) $panel['title'] ) ) ) {
						$panel['title'] = $item['base_title'];
					}
					if ( 'reviews' === $item['type'] ) {
						$panel['count'] = $item['count'];
					}
					$panels[ $item['type'] ] = $panel;
					continue;
				}
				$dom         = 'el-' . $item['key'];
				$own_tabs   .= '<li class="ec_details_tab wpec-pi-tabs-own" data-tab-id="' . esc_attr( $dom ) . '" data-tab-slug="' . esc_attr( $item['slug'] ) . '">' . esc_html( $item['title'] ) . '</li>';
				$own_panels .= '<div class="ec_details_' . esc_attr( $dom ) . '_tab wpec-pi-tabs-own-panel wpec-pi-tabs-own-panel--' . esc_attr( $item['type'] ) . '" style="display:none;">' . $item['html'] . '</div>';
			}
			$panels['addon_tabs'] = $own_tabs . wp_easycart_product_tabs_capture_gaps( $pid, $rand );

			WP_EasyCart_Product_Info::hold_own_panels( $pid, $rand, $own_panels );
			add_action( 'wpeasycart_addon_product_details_tab_content', array( 'WP_EasyCart_Product_Info', 'print_own_panels' ), 1, 2 );
			ob_start();
			wp_easycart_product_tabs_area( $product, $rand, $panels, $atts );
			$html = (string) ob_get_clean();
			remove_action( 'wpeasycart_addon_product_details_tab_content', array( 'WP_EasyCart_Product_Info', 'print_own_panels' ), 1 );
			WP_EasyCart_Product_Info::hold_own_panels( $pid, $rand, '' );

			$visible = trim( wp_strip_all_tags( (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1>|<link\b[^>]*>#is', '', $html ), true ) );
			if ( '' === $visible && ! preg_match( '/<(img|iframe|video|svg)\b/i', $html ) ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( 'No tab has anything to show for %s.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see nothing here. Add a description, specifications, your own tab or a WP EasyCart Tabs tab.', 'wp-easycart' ) );
				return true;
			}
			echo '<div class="wpec-el wpec-pi-tabs-area ec_details_extra_area ec_details_extra_area_' . esc_attr( $pid . '_' . $rand ) . ' ec_details_extra_area_custom" data-product-id="' . esc_attr( $pid ) . '" data-rand-id="' . esc_attr( $rand ) . '">';
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP EasyCart Tabs' tab area ( escaped by the extension ) around the panels built above.
			echo '</div>';
			return true;
		}
	}

endif;
