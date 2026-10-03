<?php
/**
 * Elementor › Site Settings › WP EasyCart ( 6.0.2 ): store colours, buttons, prices and spacing for every WP EasyCart widget.
 *
 * Each setting writes one shared design variable on the kit wrapper ( .elementor-kit-N on body ), the variables the 6.0.2
 * widgets read ( admin/elementor/assets/wpeasycart-elementor.css defines their defaults ):
 *
 *   --wpec-price-color, --wpec-sale-price-color, --wpec-regular-price-color, --wpec-badge-bg, --wpec-badge-color,
 *   --wpec-button-bg, --wpec-button-color, --wpec-button-hover-bg, --wpec-button-hover-color, --wpec-button-radius,
 *   --wpec-radius, --wpec-border-color, --wpec-muted-color, --wpec-success-color, --wpec-error-color, --wpec-gap,
 *   --wpec-price-font-family, --wpec-price-font-size, --wpec-price-font-weight, --wpec-button-font-family,
 *   --wpec-button-font-size, --wpec-button-font-weight, --wpec-button-text-transform, --wpec-button-letter-spacing.
 *
 * Colours start from the kit's global colours ( the same ones the widgets fall back to ); a widget's own Style tab still wins
 * for that widget. Nothing is copied into EasyCart's options, so saving the kit has no side effects.
 *
 * Loaded inside elementor/kit/register_tabs only ( it extends Elementor's Tab_Base ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Kit_Tab' ) && class_exists( '\Elementor\Core\Kits\Documents\Tabs\Tab_Base' ) ) :

	/**
	 * The WP EasyCart Site Settings tab.
	 */
	class WP_EasyCart_Elementor_Dynamic_Kit_Tab extends \Elementor\Core\Kits\Documents\Tabs\Tab_Base {

		/**
		 * Tab id ( stored with the kit's settings ).
		 */
		const TAB_ID = 'settings-wp-easycart';

		/**
		 * Id.
		 *
		 * @return string
		 */
		public function get_id() {
			return self::TAB_ID;
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'WP EasyCart', 'wp-easycart' );
		}

		/**
		 * Site Settings group ( Theme Style: next to Typography and Buttons ).
		 *
		 * @return string
		 */
		public function get_group() {
			return 'theme-style';
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-cart';
		}

		/**
		 * Help link.
		 *
		 * @return string
		 */
		public function get_help_url() {
			return WP_EasyCart_Elementor::help_url( 'site-settings', self::TAB_ID );
		}

		/**
		 * A colour setting that writes one variable.
		 *
		 * @param string $id       Control id.
		 * @param string $label    Label.
		 * @param string $variable CSS variable.
		 * @param string $from     Kit global colour it starts from ( '' for none ).
		 */
		private function add_color( $id, $label, $variable, $from = '' ) {
			$args = array(
				'label'     => $label,
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => $variable . ': {{VALUE}};',
				),
			);
			if ( '' !== $from ) {
				$args['global'] = array( 'default' => $from );
			}
			$this->add_control( $id, $args );
		}

		/**
		 * A size setting that writes one variable.
		 *
		 * @param string $id       Control id.
		 * @param string $label    Label.
		 * @param string $variable CSS variable.
		 * @param int    $max      Largest px value on the slider.
		 */
		private function add_size( $id, $label, $variable, $max ) {
			$this->add_control(
				$id,
				array(
					'label'      => $label,
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em', 'rem' ),
					'range'      => array(
						'px'  => array(
							'min' => 0,
							'max' => $max,
						),
						'em'  => array(
							'min'  => 0,
							'max'  => 5,
							'step' => 0.1,
						),
						'rem' => array(
							'min'  => 0,
							'max'  => 5,
							'step' => 0.1,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}}' => $variable . ': {{SIZE}}{{UNIT}};',
					),
				)
			);
		}

		/**
		 * A typography setting whose parts write variables ( --<prefix>-font-family, ... ).
		 *
		 * @param string $name   Control name.
		 * @param string $label  Label.
		 * @param string $prefix Variable prefix, e.g. --wpec-price.
		 * @param array  $parts  Typography parts ( font_family, font_size, font_weight, text_transform, letter_spacing ).
		 */
		private function add_typography( $name, $label, $prefix, $parts ) {
			$all     = array( 'font_family', 'font_size', 'font_weight', 'text_transform', 'font_style', 'text_decoration', 'line_height', 'letter_spacing', 'word_spacing' );
			$values  = array(
				'font_family'    => $prefix . '-font-family: "{{VALUE}}"',
				'font_size'      => $prefix . '-font-size: {{SIZE}}{{UNIT}}',
				'font_weight'    => $prefix . '-font-weight: {{VALUE}}',
				'text_transform' => $prefix . '-text-transform: {{VALUE}}',
				'letter_spacing' => $prefix . '-letter-spacing: {{SIZE}}{{UNIT}}',
			);
			$options = array(
				'typography' => array( 'label' => $label ),
			);
			foreach ( $parts as $part ) {
				if ( isset( $values[ $part ] ) ) {
					$options[ $part ] = array(
						'selectors' => array(
							'{{SELECTOR}}' => $values[ $part ],
						),
					);
				}
			}
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'           => $name,
					'label'          => $label,
					'selector'       => '{{WRAPPER}}',
					'exclude'        => array_values( array_diff( $all, $parts ) ),
					'fields_options' => $options,
				)
			);
		}

		/**
		 * Controls.
		 */
		protected function register_tab_controls() {
			$colors = '\Elementor\Core\Kits\Documents\Tabs\Global_Colors';
			$has    = class_exists( $colors );

			$this->start_controls_section(
				'section_wpec_prices',
				array(
					'label' => __( 'Prices', 'wp-easycart' ),
					'tab'   => $this->get_id(),
				)
			);
			$this->add_control(
				'wpec_kit_intro',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'These styles apply to every WP EasyCart widget on the site. A widget\'s own Style tab can still change them for that widget.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->add_color( 'wpec_price_color', __( 'Price', 'wp-easycart' ), '--wpec-price-color', $has ? $colors::COLOR_PRIMARY : '' );
			$this->add_color( 'wpec_sale_price_color', __( 'Sale price', 'wp-easycart' ), '--wpec-sale-price-color', $has ? $colors::COLOR_ACCENT : '' );
			$this->add_color( 'wpec_regular_price_color', __( 'Regular price (struck through)', 'wp-easycart' ), '--wpec-regular-price-color', $has ? $colors::COLOR_TEXT : '' );
			$this->add_typography( 'wpec_price_typography', __( 'Price typography', 'wp-easycart' ), '--wpec-price', array( 'font_family', 'font_size', 'font_weight' ) );
			$this->end_controls_section();

			$this->start_controls_section(
				'section_wpec_buttons',
				array(
					'label' => __( 'Buttons', 'wp-easycart' ),
					'tab'   => $this->get_id(),
				)
			);
			$this->start_controls_tabs( 'wpec_button_tabs' );
			$this->start_controls_tab( 'wpec_button_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->add_color( 'wpec_button_bg', __( 'Background', 'wp-easycart' ), '--wpec-button-bg', $has ? $colors::COLOR_ACCENT : '' );
			$this->add_color( 'wpec_button_color', __( 'Text', 'wp-easycart' ), '--wpec-button-color' );
			$this->end_controls_tab();
			$this->start_controls_tab( 'wpec_button_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->add_color( 'wpec_button_hover_bg', __( 'Background', 'wp-easycart' ), '--wpec-button-hover-bg', $has ? $colors::COLOR_SECONDARY : '' );
			$this->add_color( 'wpec_button_hover_color', __( 'Text', 'wp-easycart' ), '--wpec-button-hover-color' );
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_size( 'wpec_button_radius', __( 'Corner radius', 'wp-easycart' ), '--wpec-button-radius', 50 );
			$this->add_typography( 'wpec_button_typography', __( 'Button typography', 'wp-easycart' ), '--wpec-button', array( 'font_family', 'font_size', 'font_weight', 'text_transform', 'letter_spacing' ) );
			$this->end_controls_section();

			$this->start_controls_section(
				'section_wpec_badges',
				array(
					'label' => __( 'Sale and product badges', 'wp-easycart' ),
					'tab'   => $this->get_id(),
				)
			);
			$this->add_color( 'wpec_badge_bg', __( 'Background', 'wp-easycart' ), '--wpec-badge-bg', $has ? $colors::COLOR_ACCENT : '' );
			$this->add_color( 'wpec_badge_color', __( 'Text', 'wp-easycart' ), '--wpec-badge-color' );
			$this->end_controls_section();

			$this->start_controls_section(
				'section_wpec_layout',
				array(
					'label' => __( 'Cards and spacing', 'wp-easycart' ),
					'tab'   => $this->get_id(),
				)
			);
			$this->add_size( 'wpec_radius', __( 'Card corner radius', 'wp-easycart' ), '--wpec-radius', 40 );
			$this->add_size( 'wpec_gap', __( 'Space between items', 'wp-easycart' ), '--wpec-gap', 80 );
			$this->add_color( 'wpec_border_color', __( 'Borders', 'wp-easycart' ), '--wpec-border-color' );
			$this->add_color( 'wpec_muted_color', __( 'Secondary text', 'wp-easycart' ), '--wpec-muted-color' );
			$this->end_controls_section();

			$this->start_controls_section(
				'section_wpec_messages',
				array(
					'label' => __( 'Messages', 'wp-easycart' ),
					'tab'   => $this->get_id(),
				)
			);
			$this->add_color( 'wpec_success_color', __( 'Success', 'wp-easycart' ), '--wpec-success-color' );
			$this->add_color( 'wpec_error_color', __( 'Error', 'wp-easycart' ), '--wpec-error-color' );
			$this->end_controls_section();
		}
	}

endif;
