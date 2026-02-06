<?php
/**
 * WP EasyCart Account Dashboard Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Account_Dashboard_Widget
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Scheme_Color;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use ELementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Background;
use Elementor\Utils;
use Elementor\Wp_Easycart_Controls_Manager;

/**
 * WP EasyCart Account Dashboard Widget for Elementor
 *
 * @category Class
 * @package  Wp_Easycart_Elementor_Account_Dashboard_Widget
 * @author   WP EasyCart
 */
class Wp_Easycart_Elementor_Account_Dashboard_Widget extends \Elementor\Widget_Base {

	/**
	 * Get store widget name.
	 */
	public function get_name() {
		return 'wp_easycart_account_dashboard';
	}

	/**
	 * Get store widget title.
	 */
	public function get_title() {
		return esc_attr__( 'WP EasyCart Account Dashboard', 'wp-easycart' );
	}

	/**
	 * Get store widget icon.
	 */
	public function get_icon() {
		return 'eicon-ehp-forms';
	}

	/**
	 * Get store widget categories.
	 */
	public function get_categories() {
		return array( 'wp-easycart-elements' );
	}

	/**
	 * Get store widget keywords.
	 */
	public function get_keywords() {
		return array( 'shop', 'wp-easycart' );
	}

	/**
	 * Enqueue store widget scripts and styles.
	 */
	public function get_script_depends() {
		$scripts = array( 'isotope-pkgd', 'jquery-hoverIntent' );
		if ( ( isset( $_REQUEST['action'] ) && 'elementor' == $_REQUEST['action'] ) || isset( $_REQUEST['elementor-preview'] ) ) {
			$scripts[] = 'wpeasycart_js';
		}
		return $scripts;
	}

	/**
	 * Setup store widget controls.
	 */
	protected function _register_controls() {
		global $wpdb;
		$orderstatuses = $wpdb->get_results( 'SELECT ec_orderstatus.status_id, ec_orderstatus.order_status FROM ec_orderstatus ORDER BY status_id ASC' );
		$order_status_options = array();
		foreach ( $orderstatuses as $order_status ) {
			$order_status_options[ $order_status->status_id ] = esc_html( $order_status->order_status );
		}

		$this->start_controls_section(
			'section_content_form_fields',
			array(
				'label' => esc_html__( 'Dashboard Options', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		
		$this->add_control(
			'dashboard_type',
			array(
				'label'   => esc_html__( 'Dashboard Element Type', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'messages' => esc_html__( 'Success/Error Messages', 'wp-easycart' ),
					'recent-orders' => esc_html__( 'Orders', 'wp-easycart' ),
					'subscriptions' => esc_html__( 'Subscriptions', 'wp-easycart' ),
					'downloads' => esc_html__( 'Downloads', 'wp-easycart' ),
					'email' => esc_html__( 'Primary Email', 'wp-easycart' ),
					'billing' => esc_html__( 'Billing Address', 'wp-easycart' ),
					'shipping' => esc_html__( 'Shipping Address', 'wp-easycart' ),
				),
				'default'      => 'recent-orders',
				'toggle'       => false,
				'prefix_class' => 'wp-easycart-label-type-',
				'separator'    => 'after',
				'render_type'  => 'template',
			)
		);

		$this->add_control(
			'track_shipment_button_text',
			array(
				'label'   => esc_html__( 'Track Shipment Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Track Shipment', 'wp-easycart' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'buy_again_button_text',
			array(
				'label'   => esc_html__( 'Bug Again Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_buy_item_again' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'view_orders_button_text',
			array(
				'label'   => esc_html__( 'View Orders Button Text', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_all_orders_linke' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'no_orders_text',
			array(
				'label'   => esc_html__( 'No Orders Message', 'wp-easycart' ),
				'type'    => Controls_Manager::TEXT,
				'default' => wp_easycart_language( )->get_text( "account_dashboard", "account_dashboard_recent_orders_none" ),
				'dynamic' => array(
					'active' => true,
				),
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_responsive_control(
			'order_group_row_gap',
			array(
				'label' => esc_html__( 'Gap Between Orders', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 100
					)
				),
				'default' => array(
					'unit' => 'px',
					'size' => 10
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-header-row:not(:first-child)' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'enable_pagination',
			array(
				'label' => esc_html__( 'Enable Pagination', 'wp-easycart' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off' => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default' => '',
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'enable_view_all',
			array(
				'label' => esc_html__( 'Enable View All Button', 'wp-easycart' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off' => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'max_orders',
			array(
				'label' => esc_html__( 'Max Orders', 'wp-easycart' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 100,
				'step' => 1,
				'default' => 5,
				'description' => esc_html__( 'Enter the maximum number of orders to show.', 'wp-easycart' ),
				'condition'   => array(
					'enable_pagination' => '',
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'orders_per_page',
			array(
				'label' => esc_html__( 'Orders Per Page', 'wp-easycart' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 100,
				'step' => 1,
				'default' => 5,
				'condition'   => array(
					'enable_pagination' => 'yes',
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_order_status_columns',
			array(
				'label' => esc_html__( 'Order Filters', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_CONTENT,
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'orderstatus_ids',
			array(
				'label'       => esc_html__( 'Not Yet Shipped Order Statuses', 'wp-easycart' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $order_status_options,
				'label_block' => true,
				'description' => esc_html__( 'Filter orders by order status id or leave blank to show the most recent orders.', 'wp-easycart' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_content_columns',
			array(
				'label' => esc_html__( 'Order Columns', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_CONTENT,
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$dynamic_value_options = array(
			'order_id'       => esc_html__( 'Order ID', 'wp-easycart' ),
			'order_date'     => esc_html__( 'Order Date', 'wp-easycart' ),
			'order_status'   => esc_html__( 'Order Status', 'wp-easycart' ),
			'grand_total'    => esc_html__( 'Grand Total', 'wp-easycart' ),
			'shipping_name'  => esc_html__( 'Shipping Name', 'wp-easycart' ),
			'billing_name'   => esc_html__( 'Billing Name', 'wp-easycart' ),
			'shipping_method'=> esc_html__( 'Shipping Method', 'wp-easycart' ),
			'email'          => esc_html__( 'Customer Email', 'wp-easycart' ),
			'coupon_code'    => esc_html__( 'Coupon Code Used', 'wp-easycart' ),
			'payment_details'=> esc_html__( 'Payment Method', 'wp-easycart' ),
			'actions'        => esc_html__( 'View/Print Links', 'wp-easycart' ),
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'header_heading',
			array(
				'label' => esc_html__( 'Column Header', 'wp-easycart' ),
				'type' => Controls_Manager::HEADING,
			)
		);

		$repeater->add_control(
			'column_label',
			array(
				'label' => esc_html__( 'Column Label', 'wp-easycart' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Column', 'wp-easycart' ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$repeater->add_control(
			'header_content_type',
			array(
				'label' => esc_html__( 'Header Content Type', 'wp-easycart' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'dynamic',
				'options' => array(
					'dynamic'   => esc_html__( 'Dynamic Value', 'wp-easycart' ),
					'free_text' => esc_html__( 'Free Text / Static', 'wp-easycart' ),
				),
			)
		);

		$repeater->add_control(
			'header_free_text',
			array(
				'label' => esc_html__( 'Header Text', 'wp-easycart' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Column Header', 'wp-easycart' ),
				'dynamic' => array( 'active' => true ),
				'condition' => array(
					'header_content_type' => 'free_text',
				),
			)
		);

		$repeater->add_control(
			'header_dynamic_value',
			array(
				'label' => esc_html__( 'Value', 'wp-easycart' ),
				'type' => Controls_Manager::SELECT,
				'options' => $dynamic_value_options,
				'condition' => array(
					'header_content_type' => 'dynamic',
				),
			)
		);

		$repeater->add_control(
			'column_content_type',
			array(
				'label' => esc_html__( 'Content Type', 'wp-easycart' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'dynamic',
				'options' => array(
					'dynamic'   => esc_html__( 'Dynamic Value', 'wp-easycart' ),
					'free_text' => esc_html__( 'Free Text / Static', 'wp-easycart' ),
				),
			)
		);

		$repeater->add_control(
			'column_free_text',
			array(
				'label' => esc_html__( 'Text', 'wp-easycart' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Static Text', 'wp-easycart' ),
				'dynamic' => array(
					'active' => true,
				),
				'condition' => array(
					'column_content_type' => 'free_text',
				),
			)
		);

		$repeater->add_control(
			'column_dynamic_value',
			array(
				'label' => esc_html__( 'Value', 'wp-easycart' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'order_id',
				'options' => $dynamic_value_options,
				'condition' => array(
					'column_content_type' => 'dynamic',
				),
			)
		);

		$repeater->add_responsive_control(
			'column_alignment',
			array(
				'label' => esc_html__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'default' => 'left',
				'selectors' => array(
					'{{WRAPPER}} {{CURRENT_ITEM}}' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'order_columns_repeater',
			array(
				'label' => esc_html__( 'Define Columns', 'wp-easycart' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => array(
					array(
						'column_label' => esc_html__( 'Status', 'wp-easycart' ),
						'header_content_type' => 'dynamic',
						'header_free_text' => 'order_status',
						'row_content_type' => 'dynamic',
						'row_dynamic_value' => 'order_date',
					),
					array(
						'column_label' => esc_html__( 'Total', 'wp-easycart' ),
						'header_content_type' => 'free_text',
						'header_free_text' => esc_html__( 'TOTAL', 'wp-easycart' ),
						'row_content_type' => 'dynamic',
						'row_dynamic_value' => 'grand_total',
					),
					array(
						'column_label' => esc_html__( 'Shipping', 'wp-easycart' ),
						'header_content_type' => 'free_text',
						'header_free_text' => esc_html__( 'SHIP TO', 'wp-easycart' ),
						'row_content_type' => 'dynamic',
						'row_dynamic_value' => 'shipping_name',
					),
					array(
						'column_label' => esc_html__( 'Order #', 'wp-easycart' ),
						'header_content_type' => 'dynamic',
						'header_free_text' => 'order_id',
						'row_content_type' => 'dynamic',
						'row_dynamic_value' => 'actions',
					),
				),
				'title_field' => '{{{ column_label }}}',
			)
		);

		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_content_order_items',
			array(
				'label' => esc_html__( 'Order Item Options', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'show_order_item_image',
			array(
				'label'        => esc_html__( 'Show Item Image', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'wp-easycart' ),
				'label_off'    => esc_html__( 'Hide', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_order_item_details',
			array(
				'label'        => esc_html__( 'Show Item Details', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'wp-easycart' ),
				'label_off'    => esc_html__( 'Hide', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_order_item_buttons',
			array(
				'label'        => esc_html__( 'Show Item Buttons', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'wp-easycart' ),
				'label_off'    => esc_html__( 'Hide', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$order_item_repeater = new Repeater();
		$order_item_repeater->add_control(
			'oir_orderstatus_ids',
			array(
				'label'       => esc_html__( 'Apply to These Order Statuses', 'wp-easycart' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $order_status_options,
				'label_block' => true,
				'description' => esc_html__( 'Show this for the order statuses selected.', 'wp-easycart' ),
			)
		);

		$order_item_repeater->add_control(
			'indicator_icon',
			array(
				'label'   => esc_html__( 'Icon', 'wp-easycart' ),
				'type'    => Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'fas fa-check-circle',
					'library' => 'fa-solid',
				),
			)
		);
		
		$order_item_repeater->add_control(
			'indicator_icon_color',
			array(
				'label' => esc_html__( 'Icon Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .order-item-status-indicator{{CURRENT_ITEM}} i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .order-item-status-indicator{{CURRENT_ITEM}} svg' => 'fill: {{VALUE}};',
				),
			)
		);

		$order_item_repeater->add_responsive_control(
			'indicator_icon_size',
			array(
				'label' => esc_html__( 'Icon Size', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range' => array(
					'px' => array( 'min' => 6, 'max' => 100 ),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 16,
				),
				'selectors' => array(
					'{{WRAPPER}} .order-item-status-indicator{{CURRENT_ITEM}} i' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .order-item-status-indicator{{CURRENT_ITEM}} svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$order_item_repeater->add_control(
			'indicator_label',
			array(
				'label'       => esc_html__( 'Label', 'wp-easycart' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Status Label', 'wp-easycart' ),
				'placeholder' => esc_html__( 'e.g., Shipped', 'wp-easycart' ),
				'dynamic'     => array( 'active' => true ),
			)
		);

		$order_item_repeater->add_control(
			'indicator_show_label',
			array(
				'label'        => esc_html__( 'Display Label', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$order_item_repeater->add_control(
			'indicator_label_color',
			array(
				'label' => esc_html__( 'Label Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} {{CURRENT_ITEM}} .indicator-label' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'indicator_show_label' => 'yes',
				),
			)
		);

		$order_item_repeater->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'indicator_label_typography',
				'selector' => '{{WRAPPER}} {{CURRENT_ITEM}} .indicator-label',
				'condition' => array(
					'indicator_show_label' => 'yes',
				),
			)
		);

		$order_item_repeater->add_control(
			'indicator_link',
			array(
				'label'       => esc_html__( 'Link (Optional)', 'wp-easycart' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => 'https://your-link.com',
			)
		);

		$order_item_repeater->add_control(
			'indicator_layout',
			array(
				'label' => esc_html__( 'Icon & Label Layout', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'inline' => array(
						'title' => esc_html__( 'Inline', 'wp-easycart' ),
						'icon' => 'eicon-ellipsis-h',
					),
					'stacked' => array(
						'title' => esc_html__( 'Stacked', 'wp-easycart' ),
						'icon' => 'eicon-ellipsis-v',
					),
				),
				'default' => 'inline',
				'prefix_class' => 'indicator-layout-%s',
				'dynamic'     => array( 'active' => true ),
				'separator' => 'before',
				'render_type'  => 'template'
			)
		);

		$order_item_repeater->add_responsive_control(
			'indicator_alignment',
			array(
				'label' => esc_html__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'default' => 'left',
				'selectors' => array(
					'{{WRAPPER}} .order-item-status-indicator{{CURRENT_ITEM}}' => 'text-align: {{VALUE}};',
					'{{WRAPPER}} .order-item-status-indicator{{CURRENT_ITEM}}' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$order_item_repeater->add_responsive_control(
			'indicator_vertical_align',
			array(
				'label' => esc_html__( 'Vertical Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'flex-start' => array(
						'title' => esc_html__( 'Top', 'wp-easycart' ),
						'icon' => 'eicon-v-align-top',
					),
					'center' => array(
						'title' => esc_html__( 'Middle', 'wp-easycart' ),
						'icon' => 'eicon-v-align-middle',
					),
					'flex-end' => array(
						'title' => esc_html__( 'Bottom', 'wp-easycart' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'default' => 'center',
				'selectors' => array(
					'{{WRAPPER}} .order-item-status-indicator{{CURRENT_ITEM}}' => 'align-items: {{VALUE}};',
				),
				'condition' => array(
					'indicator_layout' => 'inline',
				),
			)
		);

		$order_item_repeater->add_responsive_control(
			'indicator_gap',
			array(
				'label' => esc_html__( 'Gap Between Icon/Label', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 100
					)
				),
				'default' => array(
					'unit' => 'px',
					'size' => 0
				),
				'selectors' => array(
					'{{WRAPPER}} .order-item-status-indicator{{CURRENT_ITEM}}' => 'gap: {{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'indicator_layout' => 'inline',
				),
			)
		);

		$order_item_repeater->add_control(
			'indicator_location',
			array(
				'label'   => esc_html__( 'Location', 'wp-easycart' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'before_title',
				'options' => array(
					'before_image'     => esc_html__( 'Above Product Image', 'wp-easycart' ),
					'after_image'     => esc_html__( 'Under Product Image', 'wp-easycart' ),
					'before_title'     => esc_html__( 'Above Product Title', 'wp-easycart' ),
					'after_title'     => esc_html__( 'Under Product Title', 'wp-easycart' ),
					'after_details'   => esc_html__( 'Under Product Details', 'wp-easycart' ),
					'after_price'     => esc_html__( 'Under Price', 'wp-easycart' ),
					'button_list_start' => esc_html__( 'Above Buttons', 'wp-easycart' ),
					'button_list_end'   => esc_html__( 'Under Buttons', 'wp-easycart' ),
				),
			)
		);

		$order_item_repeater->add_control(
			'first_item_only',
			array(
				'label'        => esc_html__( 'Show on First Order Item Only', 'wp-easycart' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-easycart' ),
				'label_off'    => esc_html__( 'No', 'wp-easycart' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$order_item_repeater->add_responsive_control(
			'indicator_margin',
			array(
				'label'      => esc_html__( 'Margin', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .order-item-status-indicator{{CURRENT_ITEM}}' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);


		$this->add_control(
			'status_indicators_repeater',
			array(
				'label'       => esc_html__( 'Status Indicators', 'wp-easycart' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $order_item_repeater->get_controls(),
				'default'     => array(),
				'title_field' => '{{{ indicator_label }}}',
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_style_list',
			array(
				'label' => esc_html__( 'Header Section Styling', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_responsive_control(
			'row_padding',
			array(
				'label'      => esc_html__( 'Row Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => array(
					'top'    => '10',
					'right'  => '10',
					'bottom' => '10',
					'left'   => '10',
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .wp-easycart-orders-header-row' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'column_padding',
			array(
				'label'      => esc_html__( 'Column Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => array(
					'top'    => '10',
					'right'  => '15',
					'bottom' => '10',
					'left'   => '15',
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .wp-easycart-orders-column' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_list_style' );

		$this->start_controls_tab(
			'tab_style_header',
			array(
				'label' => esc_html__( 'Column Label', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'header_color',
			array(
				'label'     => esc_html__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-column-header' => 'color: {{VALUE}};',
					'{{WRAPPER}} .wp-easycart-orders-column-header a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'header_typography',
				'selector' => '{{WRAPPER}} .wp-easycart-orders-column-header',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_style_rows',
			array(
				'label' => esc_html__( 'Column Content', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'column_content_color',
			array(
				'label'     => esc_html__( 'Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-column-content' => 'color: {{VALUE}};',
					'{{WRAPPER}} .wp-easycart-orders-column-content a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'column_content_typography',
				'selector' => '{{WRAPPER}} .wp-easycart-orders-column-content',
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();
		
		$this->add_control(
			'link_styles_heading',
			array(
				'label'     => esc_html__( 'Link Styling', 'wp-easycart' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->start_controls_tabs( 'tabs_link_style' );

		$this->start_controls_tab(
			'tab_link_normal',
			array(
				'label' => esc_html__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'link_color',
			array(
				'label'     => esc_html__( 'Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-column-header a' => 'color: {{VALUE}};',
					'{{WRAPPER}} .wp-easycart-orders-column-content a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();


		$this->start_controls_tab(
			'tab_link_hover',
			array(
				'label' => esc_html__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'link_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-column-header a:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .wp-easycart-orders-column-content a:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'link_hover_transition',
			array(
				'label'   => esc_html__( 'Transition Duration', 'wp-easycart' ),
				'type'    => Controls_Manager::SLIDER,
				'range'   => array(
					's' => array(
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-column-header a' => 'transition: color {{SIZE}}s ease;',
					'{{WRAPPER}} .wp-easycart-orders-column-content a' => 'transition: color {{SIZE}}s ease;',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();
		
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_address_popup',
			array(
				'label' => esc_html__( 'Address Popup Styling', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'popup_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_dashboard_order_info_link > span' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'popup_padding',
			array(
				'label'      => esc_html__( 'Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ec_account_dashboard_order_info_link > span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'popup_border',
				'selector' => '{{WRAPPER}} .ec_account_dashboard_order_info_link > span',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'popup_box_shadow',
				'selector' => '{{WRAPPER}} .ec_account_dashboard_order_info_link > span',
			)
		);

		$this->add_control(
			'popup_text_styles_heading',
			array(
				'label'     => esc_html__( 'Text Styling', 'wp-easycart' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'popup_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_dashboard_order_info_link > span' => 'color: {{VALUE}};',
					'{{WRAPPER}} .ec_account_dashboard_order_info_link > span > strong' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'popup_text_typography',
				'selector' => '{{WRAPPER}} .ec_account_dashboard_order_info_link > span',
				'selector' => '{{WRAPPER}} .ec_account_dashboard_order_info_link > span > strong',
			)
		);

		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_style_order_item',
			array(
				'label' => esc_html__( 'Order Item Styling', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_control(
			'layout_heading',
			array(
				'label' => esc_html__( 'Layout & Spacing', 'wp-easycart' ),
				'type' => Controls_Manager::HEADING,
			)
		);

		$this->add_responsive_control(
			'row_vertical_alignment',
			array(
				'label' => esc_html__( 'Vertical Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'flex-start' => array(
						'title' => esc_html__( 'Top', 'wp-easycart' ),
						'icon' => 'eicon-v-align-top',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-v-align-middle',
					),
					'flex-end' => array(
						'title' => esc_html__( 'Bottom', 'wp-easycart' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'default' => 'center',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-order-item-row' => 'align-items: {{VALUE}};',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'order_item_row_gap',
			array(
				'label' => esc_html__( 'Gap Between Rows', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 100
					)
				),
				'default' => array(
					'unit' => 'px',
					'size' => 0
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-order-item-row:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'order_item_details_row_gap',
			array(
				'label' => esc_html__( 'Gap Between Item Details Info', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 100
					)
				),
				'default' => array(
					'unit' => 'px',
					'size' => 5
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-order-item-details' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'order_item_button_row_gap',
			array(
				'label' => esc_html__( 'Gap Between Button Rows', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 100
					)
				),
				'default' => array(
					'unit' => 'px',
					'size' => 5
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-order-item-buttons' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'image_column_width',
			array(
				'label' => esc_html__( 'Image Column Width', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range' => array(
					'px' => array( 'min' => 50, 'max' => 500 ),
					'%' => array( 'min' => 10, 'max' => 50 ),
				),
				'default' => array( 'unit' => 'px', 'size' => 100 ),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-order-item-image' => 'flex-basis: {{SIZE}}{{UNIT}};',
				),
				'condition' => array( 'show_order_item_image' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'buttons_column_width',
			array(
				'label' => esc_html__( 'Buttons Column Width', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range' => array(
					'px' => array( 'min' => 50, 'max' => 500 ),
					'%' => array( 'min' => 10, 'max' => 50 ),
				),
				'default' => array( 'unit' => 'px', 'size' => 150 ),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-order-item-buttons' => 'flex-basis: {{SIZE}}{{UNIT}};',
				),
				'condition' => array( 'show_order_item_buttons' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'columns_gap',
			array(
				'label' => esc_html__( 'Gap Between Columns', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'default' => array( 'unit' => 'px', 'size' => 20 ),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-order-item-row' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'details_heading',
			array(
				'label' => esc_html__( 'Product Details', 'wp-easycart' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'show_order_item_details' => 'yes' ),
			)
		);

		$this->add_control(
			'title_style_heading',
			array(
				'label' => esc_html__( 'Product Title', 'wp-easycart' ),
				'type' => Controls_Manager::HEADING,
				'condition' => array( 'show_order_item_details' => 'yes' ),
			)
		);

		$this->add_control(
			'product_title_color',
			array(
				'label' => esc_html__( 'Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_order_item_title' => 'color: {{VALUE}};'
				),
				'condition' => array(
					'show_order_item_details' => 'yes'
				)
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'product_title_typography',
				'selector' => '{{WRAPPER}} .ec_account_order_item_title',
				'condition' => array(
					'show_order_item_details' => 'yes'
				)
			)
		);

		$this->add_control(
			'details_style_heading',
			array(
				'label' => esc_html__( 'Other Details', 'wp-easycart' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'show_order_item_details' => 'yes'
				),
			)
		);
		$this->add_control(
			'product_details_color',
			array(
				'label' => esc_html__( 'Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-order-item-details' => 'color: {{VALUE}};'
				),
				'condition' => array(
					'show_order_item_details' => 'yes'
				)
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'product_details_typography',
				'selector' => '{{WRAPPER}} .wp-easycart-order-item-details',
				'condition' => array(
					'show_order_item_details' => 'yes'
				)
			)
		);

		$this->add_control(
			'price_style_heading',
			array(
				'label' => esc_html__( 'Price', 'wp-easycart' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'show_order_item_details' => 'yes'
				),
			)
		);
		$this->add_control(
			'product_price_color',
			array(
				'label' => esc_html__( 'Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_order_item_price' => 'color: {{VALUE}};'
				),
				'condition' => array(
					'show_order_item_details' => 'yes'
				)
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'product_price_typography',
				'selector' => '{{WRAPPER}} .ec_account_order_item_price',
				'condition' => array(
					'show_order_item_details' => 'yes'
				)
			)
		);

		$this->add_control(
			'button_style_heading',
			array(
				'label' => esc_html__( 'Button Styling', 'wp-easycart' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'show_order_item_buttons' => 'yes'
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'button_typography',
				'selector' => '{{WRAPPER}} .ec_account_order_item_buy_button',
				'condition' => array(
					'show_order_item_buttons' => 'yes'
				)
			)
		);

		$this->start_controls_tabs(
			'order_item_button_style',
			array(
				'condition' => array(
					'show_order_item_buttons' => 'yes'
				)
			)
		);

		$this->start_controls_tab(
			'order_item_button_normal',
			array(
				'label' => esc_html__( 'Normal', 'wp-easycart' )
			)
		);

		$this->add_control(
			'order_item_button_text_color',
			array(
				'label' => esc_html__( 'Text Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_order_item_buy_button' => 'color: {{VALUE}};'
				)
			)
		);

		$this->add_control(
			'order_item_button_bg_color',
			array(
				'label' => esc_html__( 'Background Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_order_item_buy_button' => 'background-color: {{VALUE}};'
				)
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'order_item_button_hover',
			array(
				'label' => esc_html__( 'Hover', 'wp-easycart' )
			)
		);

		$this->add_control(
			'order_item_button_hover_text_color',
			array(
				'label' => esc_html__( 'Text Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_order_item_buy_button:hover' => 'color: {{VALUE}};'
				)
			)
		);

		$this->add_control(
			'order_item_button_hover_bg_color',
			array(
				'label' => esc_html__( 'Background Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_order_item_buy_button:hover' => 'background-color: {{VALUE}};'
				)
			)
		);

		$this->end_controls_tab();
		
		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'order_item_button_border',
				'selector' => '{{WRAPPER}} .ec_account_order_item_buy_button',
				'separator' => 'before',
				'condition' => array(
					'show_order_item_buttons' => 'yes'
				)
			)
		);

		$this->add_responsive_control(
			'order_item_button_border_radius',
			array(
				'label' => esc_html__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_account_order_item_buy_button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'
				),
				'condition' => array(
					'show_order_item_buttons' => 'yes'
				)
			)
		);

		$this->add_responsive_control(
			'order_item_button_padding',
			array(
				'label' => esc_html__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_account_order_item_buy_button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'
				),
				'condition' => array(
					'show_order_item_buttons' => 'yes'
				)
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_empty_message',
			array(
				'label' => esc_html__( 'No Orders Found Styling', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_responsive_control(
			'empty_message_alignment',
			array(
				'label'     => esc_html__( 'Alignment', 'wp-easycart' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-no-orders-found' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'empty_message_color',
			array(
				'label'     => esc_html__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-no-orders-found' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'empty_message_typography',
				'selector' => '{{WRAPPER}} .wp-easycart-no-orders-found',
			)
		);

		$this->add_responsive_control(
			'empty_message_padding',
			array(
				'label'      => esc_html__( 'Padding', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wp-easycart-no-orders-found' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'empty_message_margin',
			array(
				'label'      => esc_html__( 'Margin', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wp-easycart-no-orders-found' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_pagination_style',
			array(
				'label' => esc_html__( 'Pagination', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'enable_pagination' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'pagination_align',
			array(
				'label' => esc_html__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'flex-start' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'flex-end' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'default' => 'center',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'pagination_typography',
				'label' => esc_html__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav',
			)
		);

		$this->add_control(
			'pagination_spacing_heading',
			array(
				'label' => esc_html__( 'Spacing & Sizing', 'wp-easycart' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'pagination_wrapper_spacing',
			array(
				'label' => esc_html__( 'Spacing From Orders', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 20,
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'pagination_space_between',
			array(
				'label' => esc_html__( 'Space Between', 'wp-easycart' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range' => array(
					'px' => array(
						'min' => 0,
						'max' => 30,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 3,
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav, {{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-pagination-ellipsis' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'pagination_padding',
			array(
				'label' => esc_html__( 'Button Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default' => array(
					'top' => '8',
					'right' => '12',
					'bottom' => '8',
					'left' => '12',
					'unit' => 'px',
					'isLinked' => false,
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'pagination_style_tabs' );

		$this->start_controls_tab(
			'pagination_style_normal_tab',
			array(
				'label' => esc_html__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'pagination_color_normal',
			array(
				'label' => esc_html__( 'Text Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#007bff',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav, {{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-pagination-ellipsis' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'pagination_bg_color_normal',
			array(
				'label' => esc_html__( 'Background Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'pagination_border_normal',
				'label' => esc_html__( 'Border', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => '1',
							'right' => '1',
							'bottom' => '1',
							'left' => '1',
							'isLinked' => true,
						),
					),
					'color' => array(
						'default' => '#dee2e6',
					),
				),
			)
		);

		$this->add_responsive_control(
			'pagination_border_radius_normal',
			array(
				'label' => esc_html__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default' => array(
					'top' => '4',
					'right' => '4',
					'bottom' => '4',
					'left' => '4',
					'unit' => 'px',
					'isLinked' => true,
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'pagination_shadow_normal',
				'label' => esc_html__( 'Box Shadow', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'pagination_style_hover_tab',
			array(
				'label' => esc_html__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'pagination_color_hover',
			array(
				'label' => esc_html__( 'Text Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#0056b3',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'pagination_bg_color_hover',
			array(
				'label' => esc_html__( 'Background Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#e9ecef',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'pagination_border_color_hover',
			array(
				'label' => esc_html__( 'Border Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#dee2e6',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav:hover' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'pagination_shadow_hover',
				'label' => esc_html__( 'Box Shadow', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav:hover',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'pagination_style_active_tab',
			array(
				'label' => esc_html__( 'Active', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'pagination_color_active',
			array(
				'label' => esc_html__( 'Text Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav.active' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'pagination_bg_color_active',
			array(
				'label' => esc_html__( 'Background Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#007bff',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav.active' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'pagination_border_color_active',
			array(
				'label' => esc_html__( 'Border Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#007bff',
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav.active' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name' => 'pagination_shadow_active',
				'label' => esc_html__( 'Box Shadow', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .wp-easycart-orders-pagination .wp-easycart-orders-page-nav.active',
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_button',
			array(
				'label' => esc_html__( 'View All Button', 'wp-easycart' ),
				'tab'   => Controls_Manager::TAB_STYLE,
				'condition'   => array(
					'dashboard_type' => array( 'recent-orders' ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .wp-easycart-orders-all',
			)
		);

		$this->start_controls_tabs( 'view_all_button_style' );

		$this->start_controls_tab(
			'view_all_button_normal',
			array(
				'label' => esc_html__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-all' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-all' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .wp-easycart-orders-all',
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'view_all_button_hover',
			array(
				'label' => esc_html__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'view_all_button_hover_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-all:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'view_all_button_hover_bg_color',
			array(
				'label'     => esc_html__( 'Background Color', 'wp-easycart' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-all:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'button_hover_border',
				'selector' => '{{WRAPPER}} .wp-easycart-orders-all:hover',
			)
		);

		$this->add_control(
			'view_all_button_hover_transition',
			array(
				'label'   => esc_html__( 'Transition Duration', 'wp-easycart' ),
				'type'    => Controls_Manager::SLIDER,
				'range'   => array(
					's' => array(
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-all' => 'transition-duration: {{SIZE}}s;',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'view_all_button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'separator'  => 'before',
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .wp-easycart-orders-all' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'view_all_button_padding',
			array(
				'label'      => esc_html__( 'Padding (Size)', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wp-easycart-orders-all' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'view_all_button_margin',
			array(
				'label'      => esc_html__( 'Margin', 'wp-easycart' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .wp-easycart-orders-all' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'view_all_button_alignment',
			array(
				'label'   => esc_html__( 'Alignment', 'wp-easycart' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Full Width', 'wp-easycart' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'default' => 'justify',
				'selectors_dictionary' => array(
					'left'    => 'flex-start',
					'center'  => 'center',
					'right'   => 'flex-end',
					'justify' => 'stretch',
				),
				'selectors' => array(
					'{{WRAPPER}} .wp-easycart-orders-all-row' => 'display:flex; justify-content: {{VALUE}}; width:100%;',
					'{{WRAPPER}} .wp-easycart-orders-all' => 'align-self: {{VALUE}}',
					'{{WRAPPER}} .wp-easycart-orders-all' => 'text-align: center'
				),
				'prefix_class' => 'wp-easycart-elementor-button-align-%s',
			)
		);
		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_success_box_style',
			array(
				'label' => esc_html__( 'Success Box Styling', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition'   => array(
					'dashboard_type' => array( 'messages' ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'success_box_background',
				'label' => esc_html__( 'Background', 'wp-easycart' ),
				'types' => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ec_account_success',
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
					),
					'color' => array(
						'default' => '#f0fff4',
					),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'success_box_border',
				'label' => esc_html__( 'Border', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_account_success',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => '1',
							'right' => '1',
							'bottom' => '1',
							'left' => '1',
							'isLinked' => true,
						),
					),
					'color' => array(
						'default' => '#4CAF50',
					),
				),
			)
		);

		$this->add_responsive_control(
			'success_box_padding',
			array(
				'label' => esc_html__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_account_success' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default' => array(
					'top' => '15',
					'right' => '20',
					'bottom' => '15',
					'left' => '20',
					'unit' => 'px',
					'isLinked' => false,
				),
			)
		);

		$this->add_responsive_control(
			'success_box_border_radius',
			array(
				'label' => esc_html__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_account_success' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default' => array(
					'top' => '4',
					'right' => '4',
					'bottom' => '4',
					'left' => '4',
					'unit' => 'px',
					'isLinked' => true,
				),
			)
		);

		$this->add_control(
			'success_box_text_color',
			array(
				'label' => esc_html__( 'Text Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#000000',
				'selectors' => array(
					'{{WRAPPER}} .ec_account_success > div' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'success_box_text_typography',
				'selector' => '{{WRAPPER}} .ec_account_success > div',
			)
		);

		$this->add_responsive_control(
			'success_box_text_align',
			array(
				'label' => esc_html__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'default' => 'center',
				'selectors' => array(
					'{{WRAPPER}} .ec_account_success > div' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
		
		$this->start_controls_section(
			'section_error_box_style',
			array(
				'label' => esc_html__( 'Error Box Styling', 'wp-easycart' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition'   => array(
					'dashboard_type' => array( 'messages' ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name' => 'error_box_background',
				'label' => esc_html__( 'Background', 'wp-easycart' ),
				'types' => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .ec_account_error',
				'fields_options' => array(
					'background' => array(
						'default' => 'classic',
					),
					'color' => array(
						'default' => '#fff5f5',
					),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name' => 'error_box_border',
				'label' => esc_html__( 'Border', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_account_error',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width' => array(
						'default' => array(
							'top' => '1',
							'right' => '1',
							'bottom' => '1',
							'left' => '1',
							'isLinked' => true,
						),
					),
					'color' => array(
						'default' => '#FF0606',
					),
				),
			)
		);

		$this->add_responsive_control(
			'error_box_padding',
			array(
				'label' => esc_html__( 'Padding', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_account_error' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default' => array(
					'top' => '15',
					'right' => '20',
					'bottom' => '15',
					'left' => '20',
					'unit' => 'px',
					'isLinked' => false,
				),
			)
		);

		$this->add_responsive_control(
			'error_box_border_radius',
			array(
				'label' => esc_html__( 'Border Radius', 'wp-easycart' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors' => array(
					'{{WRAPPER}} .ec_account_error' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'default' => array(
					'top' => '4',
					'right' => '4',
					'bottom' => '4',
					'left' => '4',
					'unit' => 'px',
					'isLinked' => true,
				),
			)
		);

		$this->add_control(
			'error_box_text_color',
			array(
				'label' => esc_html__( 'Text Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#000000',
				'selectors' => array(
					'{{WRAPPER}} .ec_account_error > div' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'error_box_text_typography',
				'selector' => '{{WRAPPER}} .ec_account_error > div',
			)
		);

		$this->add_responsive_control(
			'error_box_text_align',
			array(
				'label' => esc_html__( 'Alignment', 'wp-easycart' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'left' => array(
						'title' => esc_html__( 'Left', 'wp-easycart' ),
						'icon' => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'wp-easycart' ),
						'icon' => 'eicon-text-align-center',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'wp-easycart' ),
						'icon' => 'eicon-text-align-right',
					),
				),
				'default' => 'center',
				'selectors' => array(
					'{{WRAPPER}} .ec_account_error > div' => 'text-align: {{VALUE}};',
				),
			)
		);
		
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'error_box_link_typography',
				'label' => esc_html__( 'Typography', 'wp-easycart' ),
				'selector' => '{{WRAPPER}} .ec_account_error > div > a',
			)
		);

		$this->start_controls_tabs( 'error_box_link_tabs' );

		$this->start_controls_tab(
			'error_box_link_normal_tab',
			array(
				'label' => esc_html__( 'Normal', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'error_box_link_color',
			array(
				'label' => esc_html__( 'Link Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_error > div > a' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'error_box_link_hover_tab',
			array(
				'label' => esc_html__( 'Hover', 'wp-easycart' ),
			)
		);

		$this->add_control(
			'error_box_link_hover_color',
			array(
				'label' => esc_html__( 'Link Color', 'wp-easycart' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ec_account_error > div > a:hover' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	protected function render() {
		$atts = $this->get_settings_for_display();
		if ( 'messages' == $atts['dashboard_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-dashboard-messages-widget.php' );
		} else if ( 'recent-orders' == $atts['dashboard_type'] ) {
			include( EC_PLUGIN_DIRECTORY . '/admin/elementor/wp-easycart-elementor-account-dashboard-orders-widget.php' );
		} else {
			echo 'Coming Soon';
		}
	}

	private function wp_easycart_elementor_dashboard_process_status_indicators( $order_status, $position, $is_first_order_item, $status_indicators_repeater ) {
		if ( $is_first_order_item && is_array( $status_indicators_repeater ) && count( $status_indicators_repeater ) > 0 ) {
			foreach ( $status_indicators_repeater as $indicator_rule ) {
				if ( $position == $indicator_rule['indicator_location'] && is_array( $indicator_rule['oir_orderstatus_ids'] ) && in_array( $order_status, $indicator_rule['oir_orderstatus_ids'] ) ) {
					$this->wp_easycart_elementor_dashboard_render_status_indicator( $indicator_rule );
				}
			}
		}
	}

	private function wp_easycart_elementor_dashboard_render_status_indicator( $indicator_rule ) {
		$repeater_item_id = $indicator_rule['_id'];
		$this->add_render_attribute( 'indicator_wrapper', 'class', 'order-item-status-indicator' );
		$this->add_render_attribute( 'indicator_wrapper', 'class', esc_attr( 'elementor-repeater-item-' . $repeater_item_id ) );
		if ( ! empty( $indicator_rule['indicator_layout'] ) ) {
			$this->add_render_attribute( 'indicator_wrapper', 'class', esc_attr( 'indicator-layout-' . $indicator_rule['indicator_layout'] ) );
		}
		$tag = 'span';
		if ( isset( $indicator_rule['indicator_link'] ) && ! empty( $indicator_rule['indicator_link']['url'] ) ) {
			$tag = 'a';
			$this->add_link_attributes( 'indicator_wrapper', $indicator_rule['indicator_link'] );
		}
		?>
		<<?php echo $tag; ?> <?php echo $this->get_render_attribute_string( 'indicator_wrapper' ); ?>>
			<?php if ( isset( $indicator_rule['indicator_icon'] ) && isset( $indicator_rule['indicator_icon']['value'] ) && ! empty( $indicator_rule['indicator_icon']['value'] ) ) { ?>
				<?php Icons_Manager::render_icon( $indicator_rule['indicator_icon'], array( 'aria-hidden' => 'true', 'title' => $indicator_rule['indicator_label'], ) ); ?>
			<?php } ?>
			<?php if ( isset( $indicator_rule['indicator_label'] ) && '' != $indicator_rule['indicator_label'] && isset( $indicator_rule['indicator_show_label'] ) && 'yes' == $indicator_rule['indicator_show_label'] ) { ?>
				<span class="indicator-label"><?php echo esc_html( $indicator_rule['indicator_label'] ); ?></span>
			<?php } ?>
		</<?php echo $tag; ?>>
		<?php
	}

	function wp_easycart_elementor_dashboard_order_list_value( $column, $order, $type = 'header' ) {
		if ( 'free_text' == $column[ $type . '_content_type'] ) {
			echo esc_html( $column[ $type . '_free_text'] );
		} else {
			if ( 'order_id' == $column[ $type . '_dynamic_value'] ) {
				echo wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_order_label' ) . ' ' . esc_attr( $order->order_id );
			} else if ( 'order_date' == $column[$type . '_dynamic_value'] ) {
				$order->display_order_date();
			} else if ( 'order_status' == $column[$type . '_dynamic_value'] ) {
				$order->display_order_status();
			} else if ( 'grand_total' == $column[$type . '_dynamic_value'] ) {
				$order->display_grand_total();
			} else if ( 'shipping_name' == $column[$type . '_dynamic_value'] ) {
				echo '<a href="#" class="ec_account_dashboard_order_info_link">';
					$order->display_order_shipping_first_name( );
					echo ' ';
					$order->display_order_shipping_last_name( );
					echo '<span><strong>';
					$order->display_order_shipping_first_name( );
					echo ' ';
					$order->display_order_shipping_last_name( );
					echo '</strong><br />';
					if ( '' != $order->shipping_company_name ) {
						echo esc_attr( htmlspecialchars( $order->shipping_company_name, ENT_QUOTES ) );
						echo '<br />';
					}
					$order->display_order_shipping_address_line_1();
					echo '<br />';
					if ( '' != $order->shipping_address_line_2 ) {
						echo esc_attr( htmlspecialchars( $order->shipping_address_line_2, ENT_QUOTES ) );
						echo '<br />';
					}
					$order->display_order_shipping_city();
					echo ', ';
					$order->display_order_shipping_state();
					echo ' ';
					$order->display_order_shipping_zip();
					echo '<br />';
					$order->display_order_shipping_country();
					if ( '' != $order->shipping_phone ) {
						echo '<br />';
						echo wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_phone' ) . ': ';
						$order->display_order_shipping_phone();
					}
					echo '</span>';
				echo '</a>';
			} else if ( 'billing_name' == $column[$type . '_dynamic_value'] ) {
				echo '<a href="#" class="ec_account_dashboard_order_info_link">';
					$order->display_order_billing_first_name( );
					echo ' ';
					$order->display_order_billing_last_name( );
					echo '<span><strong>';
					$order->display_order_billing_first_name( );
					echo ' ';
					$order->display_order_billing_last_name( );
					echo '</strong><br />';
					if ( '' != $order->billing_company_name ) {
						echo esc_attr( htmlspecialchars( $order->billing_company_name, ENT_QUOTES ) );
						echo '<br />';
					}
					$order->display_order_billing_address_line_1();
					echo '<br />';
					if ( '' != $order->billing_address_line_2 ) {
						echo esc_attr( htmlspecialchars( $order->billing_address_line_2, ENT_QUOTES ) );
						echo '<br />';
					}
					$order->display_order_billing_city();
					echo ', ';
					$order->display_order_billing_state();
					echo ' ';
					$order->display_order_billing_zip();
					echo '<br />';
					$order->display_order_billing_country();
					if ( '' != $order->billing_phone ) {
						echo '<br />';
						echo wp_easycart_language( )->get_text( 'account_billing_information', 'account_billing_information_phone' ) . ': ';
						$order->display_order_billing_phone();
					}
					echo '</span>';
				echo '</a>';
			} else if ( 'shipping_method' == $column[$type . '_dynamic_value'] ) {
				if ( $order->shipping_method ) {
					$order->display_order_shipping_method();
				}
			} else if ( 'email' == $column[$type . '_dynamic_value'] ) {
				$order->display_order_email();
			} else if ( 'coupon_code' == $column[$type . '_dynamic_value'] ) {
				if ( $order->promo_code ) {
					$order->display_order_promocode();
				}
			} else if ( 'payment_details' == $column[$type . '_dynamic_value'] ) {
				if ( '' != $order->creditcard_digits ) {
					if ( '' != trim( $order->card_holder_name ) ) {
						echo esc_attr( $order->card_holder_name );
						echo '<br />';
					}
					$order->display_payment_method();
					echo ': ****' . esc_attr( $order->creditcard_digits );
				} else {
					$order->display_payment_method();
				}
			} else if ( 'actions' == $column[$type . '_dynamic_value'] ) {
				echo '<a href="' . esc_attr( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $order->order_id ) ) ) . '">' . wp_easycart_language( )->get_text( 'account_dashboard', 'account_dashboard_order_view_details' ) . '</a> | <a href="' . esc_attr( wpeasycart_links()->get_account_page( 'print_receipt', array( 'order_id' => (int) $order->order_id ) ) ) . '" target="_blank">' . wp_easycart_language( )->get_text( 'cart_success', 'cart_success_print_receipt_text' ) . '</a>';
			}
		}
	}
}
