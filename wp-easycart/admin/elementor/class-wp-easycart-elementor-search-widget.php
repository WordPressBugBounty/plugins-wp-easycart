<?php
/**
 * WP EasyCart Search Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Search_Widget
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Scheme_Color;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Utils;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

/**
 * WP EasyCart Search Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Search_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Search_Widget extends \Elementor\Widget_Base {

	/**
	 * Get search widget name.
	 */
	public function get_name() {
		return 'wp_easycart_search';
	}

	/**
	 * Get search widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Search', 'wp-easycart' );
	}

	/**
	 * Get search widget icon.
	 */
	public function get_icon() {
		return 'eicon-search';
	}

	/**
	 * Get search widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get search widget keywords.
	 */
	public function get_keywords() {
		return array( 'search', 'wp-easycart' );
	}

	/**
	 * Enqueue search widget scripts and styles.
	 */
	public function get_script_depends() {
		$scripts = array( 'isotope-pkgd', 'jquery-hoverIntent' );
		if ( ( isset( $_REQUEST['action'] ) && 'elementor' == $_REQUEST['action'] ) || isset( $_REQUEST['elementor-preview'] ) ) {
			$scripts[] = 'wpeasycart_js';
		}
		return $scripts;
	}

	/**
	 * Setup search widget controls.
	 */
	protected function _register_controls() {

		// =====================================================================
		// CONTENT TAB — Search Options
		// =====================================================================
		$pages = get_pages();
		$pages_select = array();
		foreach ( $pages as $page ) {
			$pages_select[ $page->ID ] = $page->post_title;
		}

		$this->start_controls_section(
			'section_search_options',
			array(
				'label' => esc_attr__( 'Search Options', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'label',
			array(
				'label'       => esc_attr__( 'Button Label', 'wp-easycart' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Search',
				'placeholder' => esc_attr__( 'Button Label', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'placeholder_text',
			array(
				'label'       => esc_attr__( 'Placeholder Text', 'wp-easycart' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'Search products...',
				'placeholder' => esc_attr__( 'Enter placeholder text', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'postid',
			array(
				'label'   => esc_attr__( 'Search Page', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => (int) get_option( 'ec_option_storepage' ),
				'options' => $pages_select,
			)
		);

		$this->add_control(
			'enable_live_search',
			array(
				'label'       => esc_attr__( 'Live Search Suggestions', 'wp-easycart' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'global',
				'options'     => array(
					'global'  => esc_attr__( 'Use Global Setting', 'wp-easycart' ),
					'enable'  => esc_attr__( 'Enable', 'wp-easycart' ),
					'disable' => esc_attr__( 'Disable', 'wp-easycart' ),
				),
			)
		);

		$this->add_control(
			'live_search_max_results',
			array(
				'label'     => esc_attr__( 'Max Suggestions', 'wp-easycart' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 8,
				'min'       => 1,
				'max'       => 25,
				'step'      => 1,
				'condition' => array(
					'enable_live_search!' => 'disable',
				),
			)
		);

		$this->end_controls_section();

		// =====================================================================
		// CONTENT TAB — Layout Options
		// =====================================================================
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_attr__( 'Layout', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_responsive_control(
			'search_layout',
			array(
				'label'   => esc_attr__( 'Form Layout', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'inline',
				'options' => array(
					'inline'     => esc_attr__( 'Inline (Side by Side)', 'wp-easycart' ),
					'stacked'    => esc_attr__( 'Stacked (Input over Button)', 'wp-easycart' ),
					'fullwidth'  => esc_attr__( 'Full Width Button Below', 'wp-easycart' ),
				),
			)
		);

		$this->add_responsive_control(
			'search_gap',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Gap Between Input & Button', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 0,
				),
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 50,
					),
					'em' => array(
						'step' => 0.1,
						'min'  => 0,
						'max'  => 5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_form' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'inline_button_width',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Button Width', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 120,
				),
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 50,
						'max'  => 500,
					),
					'%' => array(
						'step' => 1,
						'min'  => 10,
						'max'  => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_button' => 'width: {{SIZE}}{{UNIT}}; flex-shrink: 0;',
				),
				'condition'  => array(
					'search_layout' => 'inline',
				),
			)
		);

		$this->end_controls_section();

		// =====================================================================
		// CONTENT TAB — Icon Options
		// =====================================================================
		$this->start_controls_section(
			'section_icon',
			array(
				'label' => esc_attr__( 'Search Icon', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_search_icon',
			array(
				'type'    => Controls_Manager::SWITCHER,
				'label'   => esc_attr__( 'Show Search Icon in Input', 'wp-easycart' ),
				'default' => 'no',
			)
		);

		$this->add_control(
			'search_icon',
			array(
				'type'        => Controls_Manager::ICONS,
				'label'       => esc_attr__( 'Input Icon', 'wp-easycart' ),
				'default'     => array(
					'value'   => 'fas fa-search',
					'library' => 'fa-solid',
				),
				'recommended' => array(
					'fa-solid' => array(
						'search',
						'magnifying-glass',
					),
				),
				'condition'   => array(
					'show_search_icon' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'search_icon_size',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Icon Size', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 14,
				),
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 8,
						'max'  => 40,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_input_icon' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .ec_search_ele_input_icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'show_search_icon' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'search_icon_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Icon Color', 'wp-easycart' ),
				'default'   => '#999999',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_input_icon' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ec_search_ele_input_icon svg' => 'fill: {{VALUE}};',
				),
				'condition' => array(
					'show_search_icon' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_button_icon',
			array(
				'type'      => Controls_Manager::SWITCHER,
				'label'     => esc_attr__( 'Show Icon in Button', 'wp-easycart' ),
				'default'   => 'no',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'button_icon',
			array(
				'type'        => Controls_Manager::ICONS,
				'label'       => esc_attr__( 'Button Icon', 'wp-easycart' ),
				'default'     => array(
					'value'   => 'fas fa-search',
					'library' => 'fa-solid',
				),
				'condition'   => array(
					'show_button_icon' => 'yes',
				),
			)
		);

		$this->add_control(
			'button_icon_position',
			array(
				'label'     => esc_attr__( 'Button Icon Position', 'wp-easycart' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'before',
				'options'   => array(
					'before'   => esc_attr__( 'Before Text', 'wp-easycart' ),
					'after'    => esc_attr__( 'After Text', 'wp-easycart' ),
					'only'     => esc_attr__( 'Icon Only (No Text)', 'wp-easycart' ),
				),
				'condition' => array(
					'show_button_icon' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'button_icon_size',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Button Icon Size', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 14,
				),
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 8,
						'max'  => 40,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_button i' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .ec_search_ele_button svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'show_button_icon' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'button_icon_spacing',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Icon Spacing', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 8,
				),
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 30,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_button' => 'gap: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'show_button_icon' => 'yes',
					'button_icon_position!' => 'only',
				),
			)
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE TAB — Form Container
		// =====================================================================
		$this->start_controls_section(
			'style_section_form',
			array(
				'label' => esc_attr__( 'Form Container', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'form_background',
				'types'          => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'none',
						'label'   => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_form',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'form_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'none',
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_form',
			)
		);

		$this->add_responsive_control(
			'form_border_radius',
			array(
				'label'      => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 0,
					'right'    => 0,
					'bottom'   => 0,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'form_box_shadow',
				'selector' => '{{WRAPPER}} .ec_search_ele_form',
			)
		);

		$this->add_responsive_control(
			'form_padding',
			array(
				'label'      => esc_attr__( 'Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 0,
					'right'    => 0,
					'bottom'   => 0,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'form_margin',
			array(
				'label'      => esc_attr__( 'Margin', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 0,
					'right'    => 0,
					'bottom'   => 0,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_form' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE TAB — Input Field
		// =====================================================================
		$this->start_controls_section(
			'style_section_input',
			array(
				'label' => esc_attr__( 'Input Field', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'input_typography',
				'label'          => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector'       => '{{WRAPPER}} .ec_search_ele_input',
				'fields_options' => array(
					'typography'  => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
					),
					'font_size'   => array(
						'default' => array(
							'size' => 14,
							'unit' => 'px',
						),
					),
				),
			)
		);

		$this->start_controls_tabs( 'input_style_tabs' );

		// Input — Normal State
		$this->start_controls_tab(
			'input_style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'input_text_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default'   => '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_input' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'input_placeholder_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Placeholder Color', 'wp-easycart' ),
				'default'   => '#999999',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_input::placeholder' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ec_search_ele_input:-ms-input-placeholder' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'input_background',
				'types'          => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label'   => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color'      => array(
						'default' => '#FFFFFF',
						'label'   => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_input_wrap',
			)
		);

		$this->end_controls_tab();

		// Input — Focus State
		$this->start_controls_tab(
			'input_style_focus_tab',
			array(
				'label' => esc_attr__( 'Focus', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'input_text_color_focus',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_input:focus' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'input_background_focus',
				'types'          => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'none',
						'label'   => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_input_wrap:focus-within',
			)
		);

		$this->add_responsive_control(
			'input_border_color_focus',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_input_wrap:focus-within' => 'border-color: {{VALUE}} !important;',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'input_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width'  => array(
						'default' => array(
							'top'      => 1,
							'right'    => 1,
							'bottom'   => 1,
							'left'     => 1,
							'unit'     => 'px',
							'isLinked' => true,
						),
					),
					'color'  => array(
						'default' => '#CCCCCC',
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_input_wrap',
				'separator'      => 'before',
			)
		);

		$this->add_responsive_control(
			'input_border_radius',
			array(
				'label'      => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 0,
					'right'    => 0,
					'bottom'   => 0,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_input_wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'input_box_shadow',
				'selector' => '{{WRAPPER}} .ec_search_ele_input_wrap',
			)
		);

		$this->add_responsive_control(
			'input_height',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Height', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 40,
				),
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 20,
						'max'  => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_input_wrap' => 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .ec_search_ele_input' => 'height: 100%;',
				),
			)
		);

		$this->add_responsive_control(
			'input_padding',
			array(
				'label'      => esc_attr__( 'Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 0,
					'right'    => 10,
					'bottom'   => 0,
					'left'     => 10,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE TAB — Button
		// =====================================================================
		$this->start_controls_section(
			'style_section_button',
			array(
				'label' => esc_attr__( 'Button', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'button_typography',
				'label'          => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector'       => '{{WRAPPER}} .ec_search_ele_button',
				'fields_options' => array(
					'typography'  => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
					),
					'font_size'   => array(
						'default' => array(
							'size' => 14,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => 'bold',
					),
				),
			)
		);

		$this->start_controls_tabs( 'button_style_tabs' );

		// Button — Normal State
		$this->start_controls_tab(
			'button_style_normal_tab',
			array(
				'label' => esc_attr__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'button_text_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default'   => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_button' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ec_search_ele_button svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'button_background',
				'types'          => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label'   => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color'      => array(
						'default' => ( get_option( 'ec_option_details_main_color' ) != '' ) ? get_option( 'ec_option_details_main_color' ) : '#333333',
						'label'   => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_button',
			)
		);

		$this->end_controls_tab();

		// Button — Hover State
		$this->start_controls_tab(
			'button_style_hover_tab',
			array(
				'label' => esc_attr__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_responsive_control(
			'button_text_color_hover',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default'   => '#FFFFFF',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_button:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ec_search_ele_button:hover svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'button_background_hover',
				'types'          => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label'   => esc_attr__( 'Background Type', 'wp-easycart' ),
					),
					'color'      => array(
						'default' => ( get_option( 'ec_option_details_second_color' ) != '' ) ? get_option( 'ec_option_details_second_color' ) : '#111111',
						'label'   => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_button:hover',
			)
		);

		$this->add_responsive_control(
			'button_border_color_hover',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Border Color', 'wp-easycart' ),
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_button:hover' => 'border-color: {{VALUE}} !important;',
				),
				'condition' => array(
					'button_border_border!' => 'none',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'button_transition',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Hover Transition (ms)', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 300,
				),
				'range'      => array(
					'px' => array(
						'step' => 50,
						'min'  => 0,
						'max'  => 1000,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_button' => 'transition: all {{SIZE}}ms ease;',
				),
				'separator'  => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'button_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'none',
					),
					'width'  => array(
						'default' => array(
							'top'      => 0,
							'right'    => 0,
							'bottom'   => 0,
							'left'     => 0,
							'unit'     => 'px',
							'isLinked' => true,
						),
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_button',
			)
		);

		$this->add_responsive_control(
			'button_border_radius',
			array(
				'label'      => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 0,
					'right'    => 0,
					'bottom'   => 0,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'button_box_shadow',
				'selector' => '{{WRAPPER}} .ec_search_ele_button',
			)
		);

		$this->add_responsive_control(
			'button_height',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Height', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 40,
				),
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 20,
						'max'  => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_button' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => esc_attr__( 'Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 0,
					'right'    => 20,
					'bottom'   => 0,
					'left'     => 20,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_margin',
			array(
				'label'      => esc_attr__( 'Margin', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 0,
					'right'    => 0,
					'bottom'   => 0,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		// =====================================================================
		// STYLE TAB — Live Search Dropdown
		// =====================================================================
		$this->start_controls_section(
			'style_section_dropdown',
			array(
				'label'     => esc_attr__( 'Live Search Dropdown', 'wp-easycart' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_live_search!' => 'disable',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'dropdown_typography',
				'label'          => esc_attr__( 'Typography', 'wp-easycart' ),
				'selector'       => '{{WRAPPER}} .ec_search_ele_dropdown_item',
				'fields_options' => array(
					'typography'  => array(
						'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
					),
					'font_size'   => array(
						'default' => array(
							'size' => 14,
							'unit' => 'px',
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_text_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Text Color', 'wp-easycart' ),
				'default'   => '#333333',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_dropdown_item' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_hover_bg_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Item Hover Background', 'wp-easycart' ),
				'default'   => '#F5F5F5',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_dropdown_item:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_hover_text_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Item Hover Text Color', 'wp-easycart' ),
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_dropdown_item:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_icon_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Suggestion Icon Color', 'wp-easycart' ),
				'default'   => '#AAAAAA',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_dropdown_item_icon' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ec_search_ele_dropdown_item_icon svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_icon_size',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Suggestion Icon Size', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 12,
				),
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 8,
						'max'  => 30,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_dropdown_item_icon' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .ec_search_ele_dropdown_item_icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'dropdown_background',
				'types'          => array( 'classic', 'gradient' ),
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
						'label'   => esc_attr__( 'Dropdown Background', 'wp-easycart' ),
					),
					'color'      => array(
						'default' => '#FFFFFF',
						'label'   => esc_attr__( 'Background Color', 'wp-easycart' ),
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_dropdown',
				'separator'      => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'dropdown_border',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width'  => array(
						'default' => array(
							'top'      => 1,
							'right'    => 1,
							'bottom'   => 1,
							'left'     => 1,
							'unit'     => 'px',
							'isLinked' => true,
						),
					),
					'color'  => array(
						'default' => '#E0E0E0',
					),
				),
				'selector'       => '{{WRAPPER}} .ec_search_ele_dropdown',
			)
		);

		$this->add_responsive_control(
			'dropdown_border_radius',
			array(
				'label'      => esc_attr__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'default'    => array(
					'top'      => 0,
					'right'    => 0,
					'bottom'   => 4,
					'left'     => 4,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_dropdown' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'dropdown_box_shadow',
				'selector' => '{{WRAPPER}} .ec_search_ele_dropdown',
				'fields_options' => array(
					'box_shadow' => array(
						'default' => array(
							'horizontal' => 0,
							'vertical'   => 4,
							'blur'       => 12,
							'spread'     => 0,
							'color'      => 'rgba(0,0,0,0.1)',
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_item_padding',
			array(
				'label'      => esc_attr__( 'Item Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 10,
					'right'    => 15,
					'bottom'   => 10,
					'left'     => 15,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_dropdown_item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_padding',
			array(
				'label'      => esc_attr__( 'Dropdown Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 5,
					'right'    => 0,
					'bottom'   => 5,
					'left'     => 0,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_dropdown' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_max_height',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Max Height', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 300,
				),
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'step' => 10,
						'min'  => 100,
						'max'  => 600,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_dropdown' => 'max-height: {{SIZE}}{{UNIT}}; overflow-y: auto;',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_offset',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Top Offset', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 0,
				),
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => -10,
						'max'  => 20,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_dropdown' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'dropdown_separator_heading',
			array(
				'label'     => esc_attr__( 'Item Separator', 'wp-easycart' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'dropdown_separator_style',
			array(
				'label'   => esc_attr__( 'Separator Style', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'solid',
				'options' => array(
					'none'   => esc_attr__( 'None', 'wp-easycart' ),
					'solid'  => esc_attr__( 'Solid', 'wp-easycart' ),
					'dashed' => esc_attr__( 'Dashed', 'wp-easycart' ),
					'dotted' => esc_attr__( 'Dotted', 'wp-easycart' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_dropdown_item + .ec_search_ele_dropdown_item' => 'border-top-style: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_separator_width',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_attr__( 'Separator Width', 'wp-easycart' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 1,
				),
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'step' => 1,
						'min'  => 0,
						'max'  => 5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ec_search_ele_dropdown_item + .ec_search_ele_dropdown_item' => 'border-top-width: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'dropdown_separator_style!' => 'none',
				),
			)
		);

		$this->add_responsive_control(
			'dropdown_separator_color',
			array(
				'type'      => Controls_Manager::COLOR,
				'label'     => esc_attr__( 'Separator Color', 'wp-easycart' ),
				'default'   => '#EEEEEE',
				'selectors' => array(
					'{{WRAPPER}} .ec_search_ele_dropdown_item + .ec_search_ele_dropdown_item' => 'border-top-color: {{VALUE}};',
				),
				'condition' => array(
					'dropdown_separator_style!' => 'none',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render search widget output in the editor.
	 */
	protected function render() {
		$atts = $this->get_settings_for_display();
		include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-search-widget.php' );
	}
}