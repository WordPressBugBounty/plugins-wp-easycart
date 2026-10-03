<?php
/**
 * Shared controls of the product purchase widgets ( 6.0.2 ): kit-aware typography, alignment and the box ( background,
 * padding, margin, border, radius ), so the same controls read the same way in every widget.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( 'WP_EasyCart_Product_Buy_Controls' ) ) :

	/**
	 * Control helpers for WP_EasyCart_Elementor_Widget_Base widgets.
	 */
	trait WP_EasyCart_Product_Buy_Controls {

		/**
		 * A typography group that starts from one of the site kit's fonts.
		 *
		 * @param string $name     Group name.
		 * @param string $label    Label.
		 * @param string $selector CSS selector.
		 * @param string $kit      primary | secondary | text | accent ( the kit font it starts from ), or '' for none: the
		 *                         stylesheet then reads the shared --wpec-button-font-* / --wpec-price-font-* variables
		 *                         ( Site Settings › WP EasyCart ), which a global default would override.
		 * @param array  $extra    6.0.2: extra group arguments ( 'condition' … ).
		 */
		protected function pb_typography( $name, $label, $selector, $kit = 'text', $extra = array() ) {
			$args  = array_merge(
				array(
					'name'     => $name,
					'label'    => $label,
					'selector' => $selector,
				),
				$extra
			);
			$class = '\Elementor\Core\Kits\Documents\Tabs\Global_Typography';
			if ( '' !== $kit && class_exists( $class ) ) {
				$constants = array(
					'primary'   => 'TYPOGRAPHY_PRIMARY',
					'secondary' => 'TYPOGRAPHY_SECONDARY',
					'text'      => 'TYPOGRAPHY_TEXT',
					'accent'    => 'TYPOGRAPHY_ACCENT',
				);
				$constant  = $class . '::' . ( isset( $constants[ $kit ] ) ? $constants[ $kit ] : 'TYPOGRAPHY_TEXT' );
				if ( defined( $constant ) ) {
					$args['global'] = array( 'default' => constant( $constant ) );
				}
			}
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), $args );
		}

		/**
		 * A colour control that sets a CSS custom property on the widget ( the stylesheet falls back to the shared
		 * --wpec-* variables and the kit ).
		 *
		 * @param string $id       Control id.
		 * @param string $label    Label.
		 * @param string $property Custom property name, e.g. --wpec-atc-button-bg.
		 * @param array  $extra    Extra control arguments.
		 */
		protected function pb_color( $id, $label, $property, $extra = array() ) {
			$this->add_control(
				$id,
				array_merge(
					array(
						'label'     => $label,
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array(
							'{{WRAPPER}}' => $property . ': {{VALUE}};',
						),
					),
					$extra
				)
			);
		}

		/**
		 * Alignment ( start / center / end ), responsive. Sets --wpec-justify and --wpec-text-align on the widget.
		 *
		 * @param string $id    Control id.
		 * @param string $label Label.
		 */
		protected function pb_align( $id = 'align', $label = '' ) {
			$this->add_responsive_control(
				$id,
				array(
					'label'                => ( '' !== $label ) ? $label : __( 'Alignment', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array(
						'start'  => array(
							'title' => __( 'Start', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center' => array(
							'title' => __( 'Center', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-center',
						),
						'end'    => array(
							'title' => __( 'End', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-right',
						),
					),
					'selectors_dictionary' => array(
						'start'  => '--wpec-justify: flex-start; --wpec-text-align: start;',
						'center' => '--wpec-justify: center; --wpec-text-align: center;',
						'end'    => '--wpec-justify: flex-end; --wpec-text-align: end;',
					),
					'selectors'            => array(
						'{{WRAPPER}}' => '{{VALUE}}',
					),
				)
			);
		}

		/**
		 * The box around a widget's content: background, padding, margin, border and radius ( a Style section ).
		 *
		 * @param string $selector CSS selector of the box.
		 */
		protected function pb_box_section( $selector ) {
			$this->start_controls_section(
				'style_box',
				array(
					'label' => __( 'Box', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'box_background_color',
				array(
					'label'     => __( 'Background color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array(
						$selector => 'background-color: {{VALUE}};',
					),
				)
			);
			$this->add_responsive_control(
				'box_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', 'rem', '%' ),
					'selectors'  => array(
						$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'box_margin',
				array(
					'label'      => __( 'Margin', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', 'rem', '%' ),
					'selectors'  => array(
						$selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'box_border',
					'selector' => $selector,
				)
			);
			$this->add_responsive_control(
				'box_border_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array(
						$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * A responsive size ( slider ). $target is a custom property set on the widget ( '--wpec-…' ), or selectors.
		 *
		 * @since 6.0.2
		 *
		 * @param string       $id     Control id.
		 * @param string       $label  Label.
		 * @param string|array $target Custom property name, or a selectors array ( {{SIZE}}{{UNIT}} ).
		 * @param array        $extra  Extra control arguments ( 'size_units', 'range', 'condition' … ).
		 */
		protected function pb_size( $id, $label, $target, $extra = array() ) {
			$selectors = is_array( $target ) ? $target : array( '{{WRAPPER}}' => $target . ': {{SIZE}}{{UNIT}};' );
			$this->add_responsive_control(
				$id,
				array_merge(
					array(
						'label'      => $label,
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px', 'em', 'rem' ),
						'range'      => array(
							'px'  => array(
								'min' => 0,
								'max' => 100,
							),
							'em'  => array(
								'min'  => 0,
								'max'  => 10,
								'step' => 0.05,
							),
							'rem' => array(
								'min'  => 0,
								'max'  => 10,
								'step' => 0.05,
							),
						),
						'selectors'  => $selectors,
					),
					$extra
				)
			);
		}

		/**
		 * Responsive padding, margin or radius of an element ( DIMENSIONS ).
		 *
		 * @since 6.0.2
		 *
		 * @param string $id       Control id.
		 * @param string $label    Label.
		 * @param string $selector CSS selector.
		 * @param string $property padding | margin | border-radius, or a custom property ( one value from the four ).
		 * @param array  $extra    Extra control arguments.
		 */
		protected function pb_dimensions( $id, $label, $selector, $property = 'padding', $extra = array() ) {
			$this->add_responsive_control(
				$id,
				array_merge(
					array(
						'label'      => $label,
						'type'       => \Elementor\Controls_Manager::DIMENSIONS,
						'size_units' => array( 'px', 'em', 'rem', '%' ),
						'selectors'  => array(
							$selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
						),
					),
					$extra
				)
			);
		}

		/**
		 * A border group ( style, width, colour ).
		 *
		 * @since 6.0.2
		 *
		 * @param string $name     Group name.
		 * @param string $selector CSS selector.
		 * @param array  $extra    Extra arguments ( 'separator', 'condition' … ).
		 */
		protected function pb_border( $name, $selector, $extra = array() ) {
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array_merge(
					array(
						'name'     => $name,
						'selector' => $selector,
					),
					$extra
				)
			);
		}

		/**
		 * A box shadow group.
		 *
		 * @since 6.0.2
		 *
		 * @param string $name     Group name.
		 * @param string $selector CSS selector.
		 * @param array  $extra    Extra arguments.
		 */
		protected function pb_shadow( $name, $selector, $extra = array() ) {
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array_merge(
					array(
						'name'     => $name,
						'selector' => $selector,
					),
					$extra
				)
			);
		}

		/**
		 * A heading row in the panel.
		 *
		 * @since 6.0.2
		 *
		 * @param string $id    Control id.
		 * @param string $label Label.
		 * @param array  $extra Extra arguments.
		 */
		protected function pb_heading( $id, $label, $extra = array() ) {
			$this->add_control(
				$id,
				array_merge(
					array(
						'label'     => $label,
						'type'      => \Elementor\Controls_Manager::HEADING,
						'separator' => 'before',
					),
					$extra
				)
			);
		}

		/**
		 * The colour and size of an icon ( font icons and SVGs ) inside $selector.
		 *
		 * @since 6.0.2
		 *
		 * @param string $prefix   Control id prefix ( ids <prefix>_color / <prefix>_size ).
		 * @param string $selector CSS selector of the icon's wrapper.
		 * @param array  $extra    Extra arguments for both controls ( 'condition' ).
		 */
		protected function pb_icon_style( $prefix, $selector, $extra = array() ) {
			$this->add_control(
				$prefix . '_color',
				array_merge(
					array(
						'label'     => __( 'Icon color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array(
							$selector => 'color: {{VALUE}};',
							str_replace( ',', ' svg,', $selector ) . ' svg' => 'fill: {{VALUE}};',
						),
					),
					$extra
				)
			);
			/* Dashicons ( the store's own icons ) have a fixed 20px box: it grows with the size. */
			$dashicons = implode( ', ', array_map( 'trim', explode( ',', $selector ) ) );
			$dashicons = str_replace( ', ', '.dashicons, ', $dashicons ) . '.dashicons';
			$this->pb_size(
				$prefix . '_size',
				__( 'Icon size', 'wp-easycart' ),
				array(
					$selector => 'font-size: {{SIZE}}{{UNIT}};',
					str_replace( ',', ' svg,', $selector ) . ' svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
					$dashicons => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: 1;',
				),
				$extra
			);
		}

		/**
		 * Markup of an Elementor icon setting ( '' when none is chosen ).
		 *
		 * @param mixed $icon ICONS control value.
		 * @return string
		 */
		protected function pb_icon_html( $icon ) {
			if ( ! is_array( $icon ) || empty( $icon['value'] ) || ! class_exists( '\Elementor\Icons_Manager' ) ) {
				return '';
			}
			ob_start();
			\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			return trim( (string) ob_get_clean() );
		}

		/**
		 * The shortcode that shows the same product part without Elementor ( saved into post_content by Elementor ).
		 *
		 * @param string $tag      Shortcode tag.
		 * @param array  $settings Widget settings.
		 * @return string
		 */
		protected function pb_plain_shortcode( $tag, $settings ) {
			$pick = ( isset( $settings['ec_product_source'] ) && 'pick' === $settings['ec_product_source'] );
			$id   = isset( $settings['ec_product_id'] ) ? $settings['ec_product_id'] : 0;
			if ( is_array( $id ) ) {
				$id = reset( $id );
			}
			$id = is_scalar( $id ) ? absint( $id ) : 0;
			if ( $pick && $id ) {
				return '[' . $tag . ' product_id="' . $id . '"]';
			}
			return ( $pick ) ? '' : '[' . $tag . ' use_post_id="1"]';
		}
	}

endif;
