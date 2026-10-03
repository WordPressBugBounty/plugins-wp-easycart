<?php
/**
 * Product Search ( wp_easycart_product_search, 6.0.2 ): a search box with live product suggestions.
 *
 * An ARIA 1.2 combobox ( arrow keys, Enter, Escape, screen reader announcements ) whose suggestions show a thumbnail and
 * the price, with "View all results" as the last choice. Suggestions come from a public, read-only endpoint
 * ( wp_easycart_el_search ) with no nonce in the page, so a cached header still searches. The form lands on the store page
 * ( or this page, or any address ) and keeps that address's own query ( plain permalinks' page_id, WPML's lang ).
 *
 * Replaces the Search widget from before 6.0.2 ( wp_easycart_search ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Search_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Product search box.
	 */
	class WP_EasyCart_Elementor_Shop_Search_Widget extends WP_EasyCart_Elementor_Widget_Base {

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'shop';

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-search';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_search';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Search', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-site-search';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'search', 'product search', 'search box', 'live search', 'autocomplete', 'find', 'woocommerce' );
		}

		/**
		 * Styles.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_values( array_unique( array_merge( parent::get_style_depends(), array( 'wpec-el-search' ) ) ) );
		}

		/**
		 * Scripts.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_values( array_unique( array_merge( parent::get_script_depends(), array( 'wpec-el-search' ) ) ) );
		}

		/**
		 * Saved into post_content.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return '[ec_search]';
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->start_controls_section(
				'ec_section_search',
				array(
					'label' => __( 'Search', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_placeholder',
				array(
					'label'       => __( 'Placeholder', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Elementor_Shop::text( 'shop_search_placeholder', 'Search products…' ),
					'label_block' => true,
				)
			);
			$this->add_control(
				'ec_button_display',
				array(
					'label'   => __( 'Button', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'icon',
					'options' => array(
						'icon' => __( 'Icon', 'wp-easycart' ),
						'text' => __( 'Text', 'wp-easycart' ),
						'both' => __( 'Icon and text', 'wp-easycart' ),
						'none' => __( 'No button ( Enter searches )', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_button_text',
				array(
					'label'       => __( 'Button text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Elementor_Shop::text( 'shop_search_button', 'Search' ),
					'condition'   => array( 'ec_button_display' => array( 'text', 'both' ) ),
				)
			);
			$this->add_control(
				'ec_button_icon',
				array(
					'label'       => __( 'Button icon', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::ICONS,
					'default'     => array(
						'value'   => '',
						'library' => '',
					),
					'skin'        => 'inline',
					'description' => __( 'Empty uses the magnifying glass.', 'wp-easycart' ),
					'condition'   => array( 'ec_button_display' => array( 'icon', 'both' ) ),
				)
			);
			$this->add_control(
				'ec_button_icon_position',
				array(
					'label'                => __( 'Button icon position', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'default'              => '',
					'options'              => array(
						'before' => array(
							'title' => __( 'Before the text', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-left',
						),
						'after'  => array(
							'title' => __( 'After the text', 'wp-easycart' ),
							'icon'  => 'eicon-h-align-right',
						),
					),
					'selectors_dictionary' => array(
						'before' => 'order: 0;',
						'after'  => 'order: 2;',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-search__button .wpec-search__icon' => '{{VALUE}}' ),
					'condition'            => array( 'ec_button_display' => 'both' ),
				)
			);
			$this->add_control(
				'ec_field_icon',
				array(
					'label'     => __( 'Icon in the box', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'auto',
					'options'   => array(
						'auto' => __( 'When the button has no icon', 'wp-easycart' ),
						'show' => __( 'Always', 'wp-easycart' ),
						'hide' => __( 'Never', 'wp-easycart' ),
					),
					'separator' => 'before',
				)
			);
			$this->add_control(
				'ec_field_icon_choice',
				array(
					'label'       => __( 'Box icon', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::ICONS,
					'default'     => array(
						'value'   => '',
						'library' => '',
					),
					'skin'        => 'inline',
					'description' => __( 'Empty uses the magnifying glass.', 'wp-easycart' ),
					'condition'   => array( 'ec_field_icon!' => 'hide' ),
				)
			);
			$this->add_control(
				'ec_field_icon_position',
				array(
					'label'                => __( 'Box icon side', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'default'              => '',
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
						'start' => 'order: 0;',
						'end'   => 'order: 1; margin-inline: 0 var( --wpec-search-icon-gap, 12px );',
					),
					'selectors'            => array(
						'{{WRAPPER}} .wpec-search__field > .wpec-search__icon' => '{{VALUE}}',
						'{{WRAPPER}} .wpec-search__field > .wpec-search__button' => 'order: 2;',
					),
					'condition'            => array( 'ec_field_icon!' => 'hide' ),
				)
			);
			$this->add_control(
				'ec_show_label',
				array(
					'label'       => __( 'Show the label', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Off, the label is still read by screen readers.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_results_page',
				array(
					'label'     => __( 'Results show on', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'store',
					'options'   => array(
						'store'   => __( 'The store page', 'wp-easycart' ),
						'current' => __( 'This page ( with a Shop widget )', 'wp-easycart' ),
						'url'     => __( 'Another address', 'wp-easycart' ),
					),
					'separator' => 'before',
				)
			);
			$this->add_control(
				'ec_results_url',
				array(
					'label'     => __( 'Address', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::URL,
					'options'   => false,
					'condition' => array( 'ec_results_page' => 'url' ),
				)
			);
			$this->add_control(
				'ec_suggestions',
				array(
					'label'     => __( 'Suggestions while typing', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'store',
					'options'   => array(
						'store' => __( 'As set for the store', 'wp-easycart' ),
						'yes'   => __( 'Show', 'wp-easycart' ),
						'no'    => __( 'Hide', 'wp-easycart' ),
					),
					'separator' => 'before',
				)
			);
			$suggest = array( 'ec_suggestions!' => 'no' );
			$this->add_control(
				'ec_min_chars',
				array(
					'label'     => __( 'Letters typed before suggesting', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 2,
					'min'       => 1,
					'max'       => 5,
					'condition' => $suggest,
				)
			);
			$this->add_control(
				'ec_max_suggestions',
				array(
					'label'     => __( 'Most suggestions', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 6,
					'min'       => 1,
					'max'       => 10,
					'condition' => $suggest,
				)
			);
			$this->add_control(
				'ec_show_thumbnails',
				array(
					'label'     => __( 'Product images', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'condition' => $suggest,
				)
			);
			$this->add_control(
				'ec_show_prices',
				array(
					'label'     => __( 'Prices', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'condition' => $suggest,
				)
			);
			$this->add_control(
				'ec_show_view_all',
				array(
					'label'     => __( '“View all results” link', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'condition' => $suggest,
				)
			);
			$this->add_control(
				'ec_search_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					/* translators: %s: link to Settings › Products › Search. */
					'raw'             => sprintf( esc_html__( 'What a search matches ( names, model numbers, descriptions ) is set in %s.', 'wp-easycart' ), '<a href="' . esc_url( WP_EasyCart_Elementor_Shop::settings_url( 'products', 'ec_option_search_title' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Settings › Products › Search', 'wp-easycart' ) . '</a>' ),
					'content_classes' => 'elementor-descriptor',
					'separator'       => 'before',
				)
			);
			$this->end_controls_section();

			$this->ec_register_search_style_controls();
		}

		/**
		 * Style sections.
		 */
		protected function ec_register_search_style_controls() {
			$style = \Elementor\Controls_Manager::TAB_STYLE;
			$font  = function ( $name, $selector, $which ) {
				$args = array(
					'name'     => $name,
					'selector' => $selector,
				);
				if ( class_exists( '\Elementor\Core\Kits\Documents\Tabs\Global_Typography' ) ) {
					$args['global'] = array( 'default' => constant( '\Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_' . strtoupper( $which ) ) );
				}
				$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), $args );
			};

			$this->start_controls_section(
				'ec_style_field',
				array(
					'label' => __( 'Search box', 'wp-easycart' ),
					'tab'   => $style,
				)
			);
			$font( 'field_typography', '{{WRAPPER}} .wpec-search__field .wpec-search__input', 'text' );
			$this->add_responsive_control(
				'field_height',
				array(
					'label'      => __( 'Height', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 32,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-search' => '--wpec-search-height: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'field_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-search__field .wpec-search__input' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'field_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-search__field' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'field_border',
					'selector' => '{{WRAPPER}} .wpec-search__field',
				)
			);
			$this->add_responsive_control(
				'field_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-search__field' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'field_padding',
				array(
					'label'      => __( 'Text padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-search__field .wpec-search__input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'field_placeholder_color',
				array(
					'label'     => __( 'Placeholder colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-search__field .wpec-search__input::placeholder' => 'color: {{VALUE}}; opacity: 1;' ),
				)
			);
			$this->add_control(
				'field_focus_color',
				array(
					'label'     => __( 'Border colour while typing', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-search__field:focus-within' => 'border-color: {{VALUE}}; box-shadow: 0 0 0 1px {{VALUE}};' ),
				)
			);
			$this->add_control(
				'field_clear',
				array(
					'label'        => __( 'Clear button ( × )', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => '',
					'options'      => array(
						''       => __( 'The browser’s own', 'wp-easycart' ),
						'custom' => __( 'Styled here', 'wp-easycart' ),
						'hide'   => __( 'Hidden', 'wp-easycart' ),
					),
					'description'  => __( 'Chrome, Edge and Safari show it once something is typed; Firefox has none.', 'wp-easycart' ),
					'prefix_class' => 'wpec-search-clear-',
					'separator'    => 'before',
				)
			);
			$this->add_control(
				'field_clear_color',
				array(
					'label'     => __( 'Clear button colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-search-clear-color: {{VALUE}};' ),
					'condition' => array( 'field_clear' => 'custom' ),
				)
			);
			$this->add_control(
				'field_clear_size',
				array(
					'label'      => __( 'Clear button size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 8,
							'max' => 28,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-search-clear-size: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'field_clear' => 'custom' ),
				)
			);
			$this->add_control(
				'field_icon_heading',
				array(
					'label'     => __( 'Box icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'ec_field_icon!' => 'hide' ),
				)
			);
			$this->add_control(
				'field_icon_color',
				array(
					'label'     => __( 'Colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-search__field > .wpec-search__icon' => 'color: {{VALUE}};' ),
					'condition' => array( 'ec_field_icon!' => 'hide' ),
				)
			);
			$this->add_responsive_control(
				'field_icon_size',
				array(
					'label'      => __( 'Size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 10,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-search__field > .wpec-search__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_field_icon!' => 'hide' ),
				)
			);
			$this->add_responsive_control(
				'field_icon_gap',
				array(
					'label'      => __( 'Space from the edge', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-search' => '--wpec-search-icon-gap: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_field_icon!' => 'hide' ),
				)
			);
			$this->add_control(
				'label_color',
				array(
					'label'     => __( 'Label colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-search__label' => 'color: {{VALUE}};' ),
					'condition' => array( 'ec_show_label' => 'yes' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_search_button',
				array(
					'label'     => __( 'Button', 'wp-easycart' ),
					'tab'       => $style,
					'condition' => array( 'ec_button_display!' => 'none' ),
				)
			);
			$font( 'search_button_typography', '{{WRAPPER}} .wpec-search__button', 'accent' );
			$this->start_controls_tabs( 'search_button_tabs' );
			$this->start_controls_tab( 'search_button_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->add_control(
				'search_button_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-button-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'search_button_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-button-bg: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'search_button_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->add_control(
				'search_button_hover_color',
				array(
					'label'     => __( 'Text colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-button-hover-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'search_button_hover_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-button-hover-bg: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_responsive_control(
				'search_button_width',
				array(
					'label'      => __( 'Width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 24,
							'max' => 200,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-search__field .wpec-search__button' => 'min-width: {{SIZE}}{{UNIT}};' ),
					'separator'  => 'before',
				)
			);
			$this->add_responsive_control(
				'search_button_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-search__field .wpec-search__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'search_button_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-search__field .wpec-search__button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'search_button_icon_size',
				array(
					'label'      => __( 'Icon size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 10,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-search__button .wpec-search__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_button_display' => array( 'icon', 'both' ) ),
				)
			);
			$this->add_responsive_control(
				'search_button_icon_gap',
				array(
					'label'      => __( 'Space between icon and text', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-search__button' => 'gap: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'ec_button_display' => 'both' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_suggestions',
				array(
					'label'     => __( 'Suggestions', 'wp-easycart' ),
					'tab'       => $style,
					'condition' => array( 'ec_suggestions!' => 'no' ),
				)
			);
			$this->add_control(
				'panel_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-search__panel' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'panel_active_background',
				array(
					'label'     => __( 'Chosen suggestion background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-search__option.is-active, {{WRAPPER}} .wpec-search__option:hover' => 'background-color: {{VALUE}};' ),
				)
			);
			$font( 'suggestion_typography', '{{WRAPPER}} .wpec-search__option-title', 'text' );
			$this->add_control(
				'suggestion_color',
				array(
					'label'     => __( 'Name colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-search__option-title' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'suggestion_price_color',
				array(
					'label'     => __( 'Price colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-price-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'panel_shadow',
					'selector' => '{{WRAPPER}} .wpec-search__panel',
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Where the form goes ( not escaped ).
		 *
		 * @param array $settings Widget settings.
		 * @return string
		 */
		protected function ec_results_url( $settings ) {
			$page = isset( $settings['ec_results_page'] ) ? $settings['ec_results_page'] : 'store';
			$url  = '';
			if ( 'url' === $page && ! empty( $settings['ec_results_url']['url'] ) ) {
				$url = (string) $settings['ec_results_url']['url'];
			} elseif ( 'current' === $page ) {
				$url = WP_EasyCart_Elementor_Shop::current_url( array( 'ec_search', 'pagenum' ) );
			} else {
				$url = WP_EasyCart_Elementor_Shop::store_page_url();
			}
			return ( '' !== $url ) ? $url : home_url( '/' );
		}

		/**
		 * Draws the widget.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$id       = 'wpec-search-' . $this->get_id();
			$action   = $this->ec_results_url( $settings );
			$mode     = isset( $settings['ec_suggestions'] ) ? $settings['ec_suggestions'] : 'store';
			$suggest  = ( 'yes' === $mode ) || ( 'store' === $mode && get_option( 'ec_option_use_live_search', 1 ) );
			$button   = isset( $settings['ec_button_display'] ) ? $settings['ec_button_display'] : 'icon';
			$label    = WP_EasyCart_Elementor_Shop::text( 'shop_search_label', 'Search products' );
			$holder   = ( isset( $settings['ec_placeholder'] ) && '' !== trim( $settings['ec_placeholder'] ) ) ? trim( $settings['ec_placeholder'] ) : WP_EasyCart_Elementor_Shop::text( 'shop_search_placeholder', 'Search products…' );
			$text     = ( isset( $settings['ec_button_text'] ) && '' !== trim( $settings['ec_button_text'] ) ) ? trim( $settings['ec_button_text'] ) : WP_EasyCart_Elementor_Shop::text( 'shop_search_button', 'Search' );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search text shown back in the box.
			$value = isset( $_GET['ec_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ec_search'] ) ) : '';
			$icon  = '<svg class="wpec-search__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

			$config = array(
				'min'    => WP_EasyCart_Elementor_Shop_Query::clamp( isset( $settings['ec_min_chars'] ) ? $settings['ec_min_chars'] : 2, 1, 5, 2 ),
				'max'    => WP_EasyCart_Elementor_Shop_Query::clamp( isset( $settings['ec_max_suggestions'] ) ? $settings['ec_max_suggestions'] : 6, 1, 10, 6 ),
				'images' => ( ! isset( $settings['ec_show_thumbnails'] ) || 'yes' === $settings['ec_show_thumbnails'] ) ? 1 : 0,
				'prices' => ( ! isset( $settings['ec_show_prices'] ) || 'yes' === $settings['ec_show_prices'] ) ? 1 : 0,
				'all'    => ( ! isset( $settings['ec_show_view_all'] ) || 'yes' === $settings['ec_show_view_all'] ) ? 1 : 0,
			);

			echo '<div class="wpec-el wpec-search wpec-search--button-' . esc_attr( $button ) . '"' . ( $suggest ? ' data-wpec-search="' . esc_attr( wp_json_encode( $config ) ) . '"' : '' ) . '>';
			echo '<form class="wpec-search__form" role="search" method="get" action="' . esc_url( $action ) . '">';
			if ( function_exists( 'wp_easycart_print_form_query_inputs' ) ) {
				wp_easycart_print_form_query_inputs( $action, array( 'ec_search', 'pagenum' ) );
			}
			$show_label = isset( $settings['ec_show_label'] ) && 'yes' === $settings['ec_show_label'];
			echo '<label class="wpec-search__label' . ( $show_label ? '' : ' wpec-sr-only' ) . '" for="' . esc_attr( $id ) . '-input">' . esc_html( $label ) . '</label>';
			echo '<div class="wpec-search__field">';
			$field_icon = isset( $settings['ec_field_icon'] ) ? $settings['ec_field_icon'] : 'auto';
			if ( 'show' === $field_icon || ( 'auto' === $field_icon && ( 'none' === $button || 'text' === $button ) ) ) {
				echo $this->ec_icon_html( $icon, isset( $settings['ec_field_icon_choice'] ) ? $settings['ec_field_icon_choice'] : array(), 'field' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed inline icon or Elementor's icon markup.
			}
			echo '<input type="search" class="wpec-search__input" id="' . esc_attr( $id ) . '-input" name="ec_search" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $holder ) . '" autocomplete="off" enterkeyhint="search"';
			if ( $suggest ) {
				echo ' role="combobox" aria-autocomplete="list" aria-expanded="false" aria-haspopup="listbox" aria-controls="' . esc_attr( $id ) . '-list"';
			}
			echo ' />';
			if ( 'none' !== $button ) {
				echo '<button type="submit" class="wpec-search__button wpec-button">';
				if ( 'icon' === $button || 'both' === $button ) {
					echo $this->ec_icon_html( $icon, isset( $settings['ec_button_icon'] ) ? $settings['ec_button_icon'] : array(), 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed inline icon or Elementor's icon markup.
				}
				echo '<span class="' . ( 'icon' === $button ? 'wpec-sr-only' : 'wpec-search__button-text' ) . '">' . esc_html( $text ) . '</span></button>';
			}
			echo '</div>';
			if ( $suggest ) {
				echo '<div class="wpec-search__panel" id="' . esc_attr( $id ) . '-panel" hidden><ul class="wpec-search__list" id="' . esc_attr( $id ) . '-list" role="listbox" aria-label="' . esc_attr( WP_EasyCart_Elementor_Shop::text( 'shop_search_suggestions', 'Product suggestions' ) ) . '"></ul></div>';
				echo '<div class="wpec-sr-only" aria-live="polite" data-wpec-search-status></div>';
			}
			echo '</form></div>';
		}

		/**
		 * An icon for the box or the button: the one chosen in the panel, else the built-in magnifying glass.
		 *
		 * @since 6.0.2
		 * @param string $built_in Built-in SVG ( class wpec-search__icon ).
		 * @param mixed  $chosen   ICONS control value.
		 * @param string $where    field | button.
		 * @return string
		 */
		protected function ec_icon_html( $built_in, $chosen, $where ) {
			if ( is_array( $chosen ) && ! empty( $chosen['value'] ) && class_exists( '\Elementor\Icons_Manager' ) ) {
				ob_start();
				\Elementor\Icons_Manager::render_icon( $chosen, array( 'aria-hidden' => 'true' ) );
				$html = trim( (string) ob_get_clean() );
				if ( '' !== $html ) {
					return '<span class="wpec-search__icon wpec-search__icon--custom wpec-search__icon--' . esc_attr( $where ) . '" aria-hidden="true">' . $html . '</span>';
				}
			}
			return str_replace( 'class="wpec-search__icon"', 'class="wpec-search__icon wpec-search__icon--' . esc_attr( $where ) . '"', $built_in );
		}
	}

endif;
