<?php
/**
 * Pricing Table ( wp_easycart_plan_table, 6.0.3 ): a plan group's pricing table, as [ec_plan_table] draws it.
 *
 * WP EasyCart PRO draws the table ( tiers, the monthly / yearly choice, cards or a compare table ) and adds 'plan_tables' to
 * wp_easycart_elementor_pro_features; the table keeps selling on a lapsed licence ( D13 ). The style controls set the table's
 * own custom properties ( --wpec-plan-* ), so the table needs no Elementor-only markup.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Plan_Table_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Pricing table.
	 */
	class WP_EasyCart_Elementor_Plan_Table_Widget extends WP_EasyCart_Elementor_Widget_Base {

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'shop';

		/**
		 * The WP EasyCart PRO feature ( upsell catalog 'elementor' ).
		 *
		 * @var string
		 */
		protected static $ec_pro_feature = 'plan_tables';

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'pricing-table';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_plan_table';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Pricing Table', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-price-table';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'pricing', 'pricing table', 'plans', 'tiers', 'subscription', 'membership', 'monthly', 'yearly', 'compare' );
		}

		/**
		 * The table's stylesheet ( WP EasyCart PRO registers it ).
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_values( array_unique( array_merge( parent::get_style_depends(), array( 'wp_easycart_plan_table' ) ) ) );
		}

		/**
		 * The table's script ( the monthly / yearly choice ).
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_values( array_unique( array_merge( parent::get_script_depends(), array( 'wp_easycart_plan_table' ) ) ) );
		}

		/**
		 * PRO 6.0.3 draws the table.
		 *
		 * @return string active | update | locked
		 */
		protected function ec_pro_state() {
			return function_exists( 'wp_easycart_elementor_pro_feature' ) ? wp_easycart_elementor_pro_feature( 'plan_tables', '6.0.3' ) : 'locked';
		}

		/**
		 * The plan groups, id => title.
		 *
		 * @return array
		 */
		protected function ec_groups() {
			global $wpdb;
			$out = array();
			if ( ! function_exists( 'wp_easycart_plan_groups_ready' ) || ! wp_easycart_plan_groups_ready() || ! is_object( $wpdb ) ) {
				return $out;
			}
			foreach ( (array) $wpdb->get_results( "SELECT subscription_plan_id, plan_title FROM ec_subscription_plan WHERE plan_kind = 'table' ORDER BY plan_title ASC LIMIT 500" ) as $row ) {
				$out[ (string) (int) $row->subscription_plan_id ] = wp_unslash( (string) $row->plan_title );
			}
			return $out;
		}

		/**
		 * The shortcode attributes the settings make.
		 *
		 * @param array $s Settings.
		 * @return array
		 */
		protected function ec_atts( $s ) {
			return array(
				'id'       => isset( $s['ec_plan_group'] ) ? (int) $s['ec_plan_group'] : 0,
				'interval' => ( isset( $s['ec_interval'] ) && in_array( $s['ec_interval'], array( 'm', 'y' ), true ) ) ? $s['ec_interval'] : '',
				'layout'   => ( isset( $s['ec_layout'] ) && in_array( $s['ec_layout'], array( 'cards', 'table' ), true ) ) ? $s['ec_layout'] : '',
				'head'     => ( isset( $s['ec_show_head'] ) && 'yes' !== $s['ec_show_head'] ) ? '0' : '',
			);
		}

		/**
		 * The equivalent shortcode.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			$atts = $this->ec_atts( $this->get_settings_for_display() );
			if ( $atts['id'] <= 0 ) {
				return '';
			}
			$out = '[ec_plan_table id="' . (int) $atts['id'] . '"';
			foreach ( array( 'interval', 'layout', 'head' ) as $key ) {
				$out .= ( '' !== $atts[ $key ] ) ? ' ' . $key . '="' . esc_attr( $atts[ $key ] ) . '"' : '';
			}
			return $out . ']';
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->start_controls_section(
				'ec_section_plan_table',
				array(
					'label' => __( 'Pricing table', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$groups = $this->ec_groups();
			$this->add_control(
				'ec_plan_group',
				array(
					'label'       => __( 'Plan group', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'options'     => array( '' => __( 'Choose a plan group', 'wp-easycart' ) ) + $groups,
					'description' => empty( $groups ) ? __( 'Create one under Products › Subscription plans ( WP EasyCart PRO ).', 'wp-easycart' ) : __( 'Tiers, prices and features are edited in the plan group.', 'wp-easycart' ),
					'label_block' => true,
				)
			);
			$this->add_control(
				'ec_interval',
				array(
					'label'   => __( 'Opens on', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => '',
					'options' => array(
						''  => __( 'As the plan group is set', 'wp-easycart' ),
						'm' => __( 'Monthly', 'wp-easycart' ),
						'y' => __( 'Yearly', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_layout',
				array(
					'label'   => __( 'Layout', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => '',
					'options' => array(
						''      => __( 'As the plan group is set', 'wp-easycart' ),
						'cards' => __( 'Cards', 'wp-easycart' ),
						'table' => __( 'Compare table', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_show_head',
				array(
					'label'        => __( 'Heading and intro', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
					'description'  => __( 'The plan group\'s own heading and intro, above the table.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_section_plan_colors',
				array(
					'label' => __( 'Colors', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$colors = array(
				'ec_accent'    => array( __( 'Accent', 'wp-easycart' ), '--wpec-plan-accent', __( 'Buttons, the popular tier and the switch. Empty uses the store\'s main color.', 'wp-easycart' ) ),
				'ec_on_accent' => array( __( 'Text on the accent', 'wp-easycart' ), '--wpec-plan-on-accent', '' ),
				'ec_text'      => array( __( 'Text', 'wp-easycart' ), '--wpec-plan-text', '' ),
				'ec_muted'     => array( __( 'Muted text', 'wp-easycart' ), '--wpec-plan-muted', '' ),
				'ec_line'      => array( __( 'Borders', 'wp-easycart' ), '--wpec-plan-line', '' ),
				'ec_card'      => array( __( 'Card background', 'wp-easycart' ), '--wpec-plan-card', '' ),
				'ec_soft'      => array( __( 'Soft background', 'wp-easycart' ), '--wpec-plan-soft', __( 'The Contact us card and the current plan\'s button.', 'wp-easycart' ) ),
			);
			foreach ( $colors as $id => $color ) {
				$this->add_control(
					$id,
					array(
						'label'       => $color[0],
						'type'        => \Elementor\Controls_Manager::COLOR,
						'description' => $color[2],
						/* !important: the table sets the store's main color inline. */
						'selectors'   => array( '{{WRAPPER}} .wpec-plans' => $color[1] . ': {{VALUE}} !important;' ),
					)
				);
			}
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_section_plan_cards',
				array(
					'label' => __( 'Tiers', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_responsive_control(
				'ec_gap',
				array(
					'label'      => __( 'Space between cards', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 60,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-plans' => '--wpec-plan-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'ec_radius',
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
					'selectors'  => array( '{{WRAPPER}} .wpec-plans' => '--wpec-plan-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'ec_price_size',
				array(
					'label'      => __( 'Price size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 14,
							'max' => 72,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-plans' => '--wpec-plan-price-size: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'ec_name_typography',
					'label'    => __( 'Tier name', 'wp-easycart' ),
					'selector' => '{{WRAPPER}} .wpec-plans .wpec-plan__name',
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'ec_price_typography',
					'label'    => __( 'Price', 'wp-easycart' ),
					'exclude'  => array( 'font_size' ),
					'selector' => '{{WRAPPER}} .wpec-plans .wpec-plan__amount',
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'ec_button_typography',
					'label'    => __( 'Buttons', 'wp-easycart' ),
					'selector' => '{{WRAPPER}} .wpec-plans .wpec-plan__cta',
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Output.
		 */
		protected function render() {
			if ( $this->ec_render_pro_lock() ) {
				return;
			}
			$atts = $this->ec_atts( $this->get_settings_for_display() );
			if ( $atts['id'] <= 0 ) {
				$this->ec_editor_notice( __( 'Choose a plan group', 'wp-easycart' ), empty( $this->ec_groups() ) ? __( 'Create one under Products › Subscription plans, then pick it in Content › Pricing table.', 'wp-easycart' ) : __( 'Pick it in Content › Pricing table.', 'wp-easycart' ) );
				return;
			}
			$html = function_exists( 'load_ec_plan_table' ) ? load_ec_plan_table( $atts ) : '';
			if ( '' === $html ) {
				$this->ec_editor_notice( __( 'This plan group has nothing to sell yet', 'wp-easycart' ), __( 'Give it tiers with prices, then save it.', 'wp-easycart' ) );
				return;
			}
			echo '<div class="wpec-el wpec-el-plan-table">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP EasyCart PRO escapes the table as it builds it.
		}
	}

endif;
