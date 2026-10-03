<?php
/**
 * Style controls the cart and checkout widgets share ( 6.0.2 ), so a heading, a field or a button is styled with the same
 * controls, in the same order, in every widget. Loaded by the widget files ( Elementor is loaded then ).
 *
 * EasyCart's own cart and checkout CSS sets fonts, button colours and cart line titles with !important. A colour or font
 * the merchant links to a global ( Site Settings ) reaches the page as a CSS variable without !important, so it would
 * lose. Those controls therefore set custom properties on the widget ( --wpec-el-font on the element the typography
 * control targets; --wpec-el-btn-*, --wpec-el-cta-*, --wpec-el-title-color ... on the widget ) and checkout.css applies
 * them with !important, falling back to EasyCart's own values ( --wpec-main-color and so on ) when they are not set.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( 'WP_EasyCart_Elementor_Checkout_Controls' ) ) :

	/**
	 * Shared control groups.
	 */
	trait WP_EasyCart_Elementor_Checkout_Controls {

		/**
		 * A selector list inside the widget: every selector prefixed with {{WRAPPER}}.
		 *
		 * @param string|string[] $selectors Selectors.
		 * @param string          $suffix    Appended to each ( :hover, :focus ).
		 * @return string
		 */
		protected function ec_sel( $selectors, $suffix = '' ) {
			$out = array();
			foreach ( (array) $selectors as $selector ) {
				foreach ( explode( ',', $selector ) as $part ) {
					$part = trim( $part );
					if ( '' !== $part ) {
						$out[] = '{{WRAPPER}} ' . $part . $suffix;
					}
				}
			}
			return implode( ', ', $out );
		}

		/**
		 * A group control field's CSS ( fields_options entry ). Elementor's typography group builds each field's selectors from
		 * its selector_value before it merges fields_options, and the base group merges them after: both keys are given, so the
		 * value applies whichever way a version orders them ( round 11: the font family never reached --wpec-el-font ).
		 *
		 * @param string $css Declarations for {{SELECTOR}}.
		 * @return array
		 */
		protected function ec_group_field( $css ) {
			return array(
				'selector_value' => $css,
				'selectors'      => array( '{{SELECTOR}}' => $css ),
			);
		}

		/**
		 * A typography group control.
		 *
		 * @param string      $name      Control name.
		 * @param string      $selector  Selector ( inside the widget ).
		 * @param string|null $preset    Global typography default, or null.
		 * @param bool        $font_var  EasyCart markup: the font family goes through --wpec-el-font ( see the file header ).
		 * @param array       $important Typography parts EasyCart sets with !important on this markup ( font_size, font_weight,
		 *                               text_transform, line_height, letter_spacing ): written with !important too.
		 */
		protected function ec_typography( $name, $selector, $preset = null, $font_var = false, $important = array() ) {
			$args = array(
				'name'     => $name,
				'selector' => $this->ec_sel( $selector ),
			);
			if ( $preset ) {
				$args['global'] = array( 'default' => $preset );
			}
			$options = array();
			if ( $font_var ) {
				$options['font_family'] = $this->ec_group_field( '--wpec-el-font: "{{VALUE}}", Sans-serif;' );
			}
			$values = array(
				'font_size'      => 'font-size: {{SIZE}}{{UNIT}} !important;',
				'font_weight'    => 'font-weight: {{VALUE}} !important;',
				'text_transform' => 'text-transform: {{VALUE}} !important;',
				'line_height'    => 'line-height: {{SIZE}}{{UNIT}} !important;',
				'letter_spacing' => 'letter-spacing: {{SIZE}}{{UNIT}} !important;',
			);
			foreach ( (array) $important as $part ) {
				if ( isset( $values[ $part ] ) ) {
					$options[ $part ] = $this->ec_group_field( $values[ $part ] );
				}
			}
			if ( $options ) {
				$args['fields_options'] = $options;
			}
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), $args );
		}

		/**
		 * Style section for text: typography and colour ( optionally a hover colour, alignment and spacing below ).
		 *
		 * @param string $id       Section id prefix.
		 * @param string $label    Section label.
		 * @param string $selector Selector ( inside the widget ).
		 * @param array  $args     'typography' => global typography, 'color' => global colour, 'hover' => bool,
		 *                         'color_var' => custom property for the colour ( EasyCart !important rules ), 'font_var' => bool,
		 *                         'important' => typography parts written with !important ( see ec_typography() ),
		 *                         'color_important' => bool ( a plain colour rule written with !important ),
		 *                         'spacing' => bool, 'align' => bool, 'condition' => array.
		 */
		protected function ec_text_section( $id, $label, $selector, $args = array() ) {
			$args    = wp_parse_args(
				$args,
				array(
					'typography'      => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT,
					'color'           => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT,
					'hover'           => false,
					'color_var'       => '',
					'font_var'        => false,
					'important'       => array(),
					'color_important' => false,
					'spacing'         => false,
					'align'           => false,
					'condition'       => array(),
				)
			);
			$section = array(
				'label' => $label,
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			);
			if ( $args['condition'] ) {
				$section['condition'] = $args['condition'];
			}
			$this->start_controls_section( $id . '_style', $section );
			$this->ec_text_controls( $id, $selector, $args );
			$this->end_controls_section();
		}

		/**
		 * The text controls ( inside an open section ).
		 *
		 * @param string $id       Control id prefix.
		 * @param string $selector Selector.
		 * @param array  $args     See ec_text_section().
		 */
		protected function ec_text_controls( $id, $selector, $args = array() ) {
			$args      = wp_parse_args(
				$args,
				array(
					'typography'      => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT,
					'color'           => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT,
					'hover'           => false,
					'color_var'       => '',
					'font_var'        => false,
					'important'       => array(),
					'color_important' => false,
					'spacing'         => false,
					'align'           => false,
				)
			);
			$important = $args['color_important'] ? ' !important' : '';
			$this->ec_typography( $id . '_typography', $selector, $args['typography'], $args['font_var'], $args['important'] );
			$color = array(
				'label'     => __( 'Color', 'wp-easycart' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => ( '' !== $args['color_var'] ) ? array( '{{WRAPPER}}' => '--' . $args['color_var'] . ': {{VALUE}};' ) : array( $this->ec_sel( $selector ) => 'color: {{VALUE}}' . $important . ';' ),
			);
			if ( $args['color'] ) {
				$color['global'] = array( 'default' => $args['color'] );
			}
			$this->add_control( $id . '_color', $color );
			if ( $args['hover'] ) {
				$this->add_control(
					$id . '_hover_color',
					array(
						'label'     => __( 'Hover color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => ( '' !== $args['color_var'] ) ? array( '{{WRAPPER}}' => '--' . $args['color_var'] . '-hover: {{VALUE}};' ) : array( $this->ec_sel( $selector, ':hover' ) => 'color: {{VALUE}}' . $important . ';' ),
					)
				);
			}
			if ( $args['align'] ) {
				$this->add_responsive_control(
					$id . '_align',
					array(
						'label'     => __( 'Alignment', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::CHOOSE,
						'options'   => $this->ec_align_options( 'text' ),
						'selectors' => array( $this->ec_sel( $selector ) => 'text-align: {{VALUE}};' ),
					)
				);
			}
			if ( $args['spacing'] ) {
				$this->add_responsive_control(
					$id . '_spacing',
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
						'selectors'  => array( $this->ec_sel( $selector ) => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
					)
				);
			}
		}

		/**
		 * Style section for buttons: typography, Normal / Hover colours, border, corners, padding and shadow.
		 *
		 * @param string $id       Section id prefix.
		 * @param string $label    Section label.
		 * @param string $selector Buttons.
		 * @param array  $args     'vars' => custom property prefix ( btn, cta ... ) for EasyCart's buttons, whose colours
		 *                         EasyCart sets with !important, or '' for the widget's own buttons; 'font_var' => bool;
		 *                         'condition' => array; 'background' => global colour default ( '' for none ); 'width' => bool;
		 *                         'working' => selector of EasyCart's "Please wait" copy of these buttons ( adds a Please wait
		 *                         tab: --wpec-el-{vars}-working-color / -bg ), '' for none; 'align' => selector list whose
		 *                         alignment the section sets ( '' for none ).
		 */
		protected function ec_button_section( $id, $label, $selector, $args = array() ) {
			$args      = wp_parse_args(
				$args,
				array(
					'vars'       => '',
					'font_var'   => false,
					'condition'  => array(),
					'background' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT,
					'width'      => false,
					'working'    => '',
					'align'      => '',
				)
			);
			$vars      = ( '' !== $args['vars'] );
			$important = $vars ? ' !important' : '';
			$section   = array(
				'label' => $label,
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			);
			if ( $args['condition'] ) {
				$section['condition'] = $args['condition'];
			}
			$this->start_controls_section( $id . '_style', $section );
			$this->ec_typography( $id . '_typography', $selector, \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT, $args['font_var'] );
			$this->start_controls_tabs( $id . '_tabs' );
			$properties = array(
				'text_color'   => array( __( 'Text color', 'wp-easycart' ), 'color', 'color' ),
				'background'   => array( __( 'Background color', 'wp-easycart' ), 'bg', 'background-color' ),
				'border_color' => array( __( 'Border color', 'wp-easycart' ), 'border', 'border-color' ),
			);
			$states     = array(
				'normal' => __( 'Normal', 'wp-easycart' ),
				'hover'  => __( 'Hover', 'wp-easycart' ),
			);
			if ( $vars && '' !== $args['working'] ) {
				$states['working'] = __( 'Please wait', 'wp-easycart' );
			}
			foreach ( $states as $state => $state_label ) {
				$this->start_controls_tab( $id . '_tab_' . $state, array( 'label' => $state_label ) );
				if ( 'working' === $state ) {
					/* EasyCart swaps a button for its grey "Please wait" copy while a request runs ( !important in ec-store.css ). */
					foreach ( array(
						'text_color' => array( __( 'Text color', 'wp-easycart' ), 'color' ),
						'background' => array( __( 'Background color', 'wp-easycart' ), 'bg' ),
					) as $key => $property ) {
						$this->add_control(
							$id . '_working_' . $key,
							array(
								'label'     => $property[0],
								'type'      => \Elementor\Controls_Manager::COLOR,
								'selectors' => array( '{{WRAPPER}}' => '--wpec-el-' . $args['vars'] . '-working-' . $property[1] . ': {{VALUE}};' ),
							)
						);
					}
					$this->end_controls_tab();
					continue;
				}
				foreach ( $properties as $key => $property ) {
					$name = $id . ( 'hover' === $state ? '_hover_' : '_' ) . $key;
					if ( $vars && 'hover' === $state && 'border_color' === $key ) {
						/* Round 11: nothing read the hover border variable; the colour goes on the button ( EasyCart: border none !important ). */
						$selectors = array( $this->ec_sel( $selector, ':hover' ) . ', ' . $this->ec_sel( $selector, ':focus-visible' ) => 'border-color: {{VALUE}} !important;' );
					} elseif ( $vars ) {
						$selectors = array( '{{WRAPPER}}' => '--wpec-el-' . $args['vars'] . ( 'hover' === $state ? '-hover-' : '-' ) . $property[1] . ': {{VALUE}};' );
					} else {
						$selectors = array( $this->ec_sel( $selector, 'hover' === $state ? ':hover' : '' ) => $property[2] . ': {{VALUE}};' );
					}
					$control = array(
						'label'     => $property[0],
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => $selectors,
					);
					if ( 'normal' === $state && 'background' === $key && $args['background'] ) {
						$control['global'] = array( 'default' => $args['background'] );
					}
					if ( 'normal' === $state && 'border_color' === $key ) {
						$control['description'] = __( 'Shows once the button has a border ( Border type below ).', 'wp-easycart' );
					}
					$this->add_control( $name, $control );
				}
				$this->end_controls_tab();
			}
			$this->end_controls_tabs();

			/*
			 * No colour field in the group: the Normal tab's "Border color" above is {$id}_border_color, the name the group's
			 * own colour would take. Elementor kept the tab's control and refused the group's with a "Cannot redeclare
			 * control" notice, so leaving it out changes nothing a page saved.
			 */
			$border = array(
				'name'      => $id . '_border',
				'selector'  => $this->ec_sel( $selector ),
				'separator' => 'before',
				'exclude'   => array( 'color' ),
			);
			if ( $vars ) {
				/*
				 * Round 11: EasyCart's buttons carry border: none !important, so the border is written with !important, in the
				 * Normal tab's border colour ( --wpec-el-{vars}-border ).
				 */
				$border['fields_options'] = array(
					'border' => $this->ec_group_field( 'border-style: {{VALUE}} !important; border-color: var(--wpec-el-' . $args['vars'] . '-border, currentColor) !important;' ),
					'width'  => $this->ec_group_field( 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;' ),
				);
			}
			$this->add_group_control( \Elementor\Group_Control_Border::get_type(), $border );
			$this->add_responsive_control(
				$id . '_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( $this->ec_sel( $selector ) => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' . $important . ';' ),
				)
			);
			$this->add_responsive_control(
				$id . '_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( $this->ec_sel( $selector ) => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' . $important . '; height: auto' . $important . ';' ),
				)
			);
			if ( $args['width'] ) {
				$this->add_control(
					$id . '_full_width',
					array(
						'label'        => __( 'Full width', 'wp-easycart' ),
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'return_value' => 'yes',
						'selectors'    => array( $this->ec_sel( $selector ) => 'width: 100%' . $important . '; box-sizing: border-box; text-align: center;' ),
					)
				);
			}
			if ( '' !== $args['align'] ) {
				$this->add_responsive_control(
					$id . '_align',
					array(
						'label'     => __( 'Alignment', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::CHOOSE,
						'options'   => $this->ec_align_options( 'text' ),
						'selectors' => array( $this->ec_sel( $args['align'] ) => 'text-align: {{VALUE}};' ),
					)
				);
			}
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => $id . '_shadow',
					'selector' => $this->ec_sel( $selector ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Style section for form fields: labels, then the boxes ( typography, colours, border, corners, padding, focus ).
		 *
		 * @param string $id        Section id prefix.
		 * @param string $label     Section label.
		 * @param string $labels    Label selector ( '' for none ).
		 * @param string $fields    Field selector.
		 * @param array  $condition Section condition.
		 * @param array  $args      'rows' => field rows ( space between rows ), 'columns' => the second of two fields side by
		 *                          side ( space between columns ), 'flex_rows' => rows laid out with column-gap, 'choices' =>
		 *                          checkboxes and radio buttons ( accent colour ); '' leaves a control out.
		 */
		protected function ec_fields_section( $id, $label, $labels, $fields, $condition = array(), $args = array() ) {
			$args    = wp_parse_args(
				$args,
				array(
					'rows'      => '',
					'columns'   => '',
					'flex_rows' => '',
					'choices'   => '',
				)
			);
			$section = array(
				'label' => $label,
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			);
			if ( $condition ) {
				$section['condition'] = $condition;
			}
			$this->start_controls_section( $id . '_style', $section );
			if ( '' !== $labels ) {
				$this->add_control(
					$id . '_labels_heading',
					array(
						'label' => __( 'Labels', 'wp-easycart' ),
						'type'  => \Elementor\Controls_Manager::HEADING,
					)
				);
				$this->ec_typography( $id . '_label_typography', $labels, \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT, true );
				$this->add_control(
					$id . '_label_color',
					array(
						'label'     => __( 'Color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'global'    => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT ),
						'selectors' => array( $this->ec_sel( $labels ) => 'color: {{VALUE}};' ),
					)
				);
				$this->add_control(
					$id . '_required_color',
					array(
						'label'       => __( 'Required mark colour', 'wp-easycart' ),
						'type'        => \Elementor\Controls_Manager::COLOR,
						'description' => __( 'The * after the label of a field shoppers must fill in.', 'wp-easycart' ),
						'selectors'   => array( $this->ec_sel( $labels, ' .wpec-required' ) => 'color: {{VALUE}};' ),
					)
				);
				$this->add_responsive_control(
					$id . '_label_spacing',
					array(
						'label'      => __( 'Space below labels', 'wp-easycart' ),
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px', 'em' ),
						'range'      => array(
							'px' => array(
								'min' => 0,
								'max' => 40,
							),
						),
						'selectors'  => array( $this->ec_sel( $labels ) => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
					)
				);
				$this->add_control(
					$id . '_fields_heading',
					array(
						'label'     => __( 'Fields', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::HEADING,
						'separator' => 'before',
					)
				);
			}
			$this->ec_typography( $id . '_field_typography', $fields, null, true );
			$this->start_controls_tabs( $id . '_field_tabs' );
			foreach ( array(
				'normal' => __( 'Normal', 'wp-easycart' ),
				'focus'  => __( 'Focus', 'wp-easycart' ),
			) as $state => $state_label ) {
				$suffix = ( 'focus' === $state ) ? ':focus' : '';
				$key    = ( 'focus' === $state ) ? '_focus' : '';
				$this->start_controls_tab( $id . '_field_tab_' . $state, array( 'label' => $state_label ) );
				$this->add_control(
					$id . $key . '_field_color',
					array(
						'label'     => __( 'Text color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $this->ec_sel( $fields, $suffix ) => 'color: {{VALUE}};' ),
					)
				);
				$this->add_control(
					$id . $key . '_field_background',
					array(
						'label'     => __( 'Background color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $this->ec_sel( $fields, $suffix ) => 'background-color: {{VALUE}};' ),
					)
				);
				$this->add_control(
					$id . $key . '_field_border_color',
					array(
						'label'     => __( 'Border color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $this->ec_sel( $fields, $suffix ) => 'border-color: {{VALUE}};' ),
					)
				);
				if ( 'focus' === $state ) {
					$this->add_group_control(
						\Elementor\Group_Control_Box_Shadow::get_type(),
						array(
							'name'     => $id . '_focus_shadow',
							'selector' => $this->ec_sel( $fields, ':focus' ),
						)
					);
				}
				$this->end_controls_tab();
			}
			$this->end_controls_tabs();
			$this->add_responsive_control(
				$id . '_field_border_width',
				array(
					'label'      => __( 'Border width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px' ),
					'separator'  => 'before',
					'selectors'  => array( $this->ec_sel( $fields ) => 'border-style: solid; border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				$id . '_field_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( $this->ec_sel( $fields ) => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				$id . '_field_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( $this->ec_sel( $fields ) => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; height: auto;' ),
				)
			);
			/* EasyCart greys every placeholder with !important ( ec-store.css ). */
			$this->add_control(
				$id . '_placeholder_color',
				array(
					'label'     => __( 'Placeholder colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => 'before',
					'selectors' => array( $this->ec_sel( $fields, '::placeholder' ) => 'color: {{VALUE}} !important; opacity: 1;' ),
				)
			);
			/* A field EasyCart found a problem with: its message row, shown right after it. */
			$this->add_control(
				$id . '_error_border_color',
				array(
					'label'       => __( 'Border colour with a problem', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'description' => __( 'While the message under a field says what to fix.', 'wp-easycart' ),
					'selectors'   => array( $this->ec_sel( $fields, ':has(+ .ec_cart_error_row[style*="block"])' ) => 'border-color: {{VALUE}} !important;' ),
				)
			);
			if ( '' !== $args['choices'] ) {
				$this->add_control(
					$id . '_choice_color',
					array(
						'label'       => __( 'Tick and choice colour', 'wp-easycart' ),
						'type'        => \Elementor\Controls_Manager::COLOR,
						'description' => __( 'Checkboxes and radio buttons.', 'wp-easycart' ),
						'selectors'   => array( $this->ec_sel( $args['choices'] ) => 'accent-color: {{VALUE}};' ),
					)
				);
			}
			if ( '' !== $args['rows'] ) {
				$this->add_responsive_control(
					$id . '_row_gap',
					array(
						'label'      => __( 'Space between rows', 'wp-easycart' ),
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px', 'em' ),
						'range'      => array(
							'px' => array(
								'min' => 0,
								'max' => 60,
							),
						),
						'separator'  => 'before',
						'selectors'  => array( $this->ec_sel( $args['rows'] ) => 'margin-top: {{SIZE}}{{UNIT}};' ),
					)
				);
			}
			if ( '' !== $args['columns'] || '' !== $args['flex_rows'] ) {
				$selectors = array();
				if ( '' !== $args['columns'] ) {
					$selectors[ $this->ec_sel( $args['columns'] ) ] = 'padding-left: {{SIZE}}{{UNIT}};';
				}
				if ( '' !== $args['flex_rows'] ) {
					$selectors[ $this->ec_sel( $args['flex_rows'] ) ] = 'column-gap: {{SIZE}}{{UNIT}};';
				}
				$this->add_responsive_control(
					$id . '_column_gap',
					array(
						'label'      => __( 'Space between fields side by side', 'wp-easycart' ),
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px', 'em' ),
						'range'      => array(
							'px' => array(
								'min' => 0,
								'max' => 60,
							),
						),
						'selectors'  => $selectors,
					)
				);
			}
			$this->end_controls_section();
		}

		/**
		 * Style section for a box: background, border, corners, padding, shadow.
		 *
		 * @param string $id        Section id prefix.
		 * @param string $label     Section label.
		 * @param string $selector  Box.
		 * @param array  $condition Section condition.
		 */
		protected function ec_box_section( $id, $label, $selector, $condition = array() ) {
			$section = array(
				'label' => $label,
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			);
			if ( $condition ) {
				$section['condition'] = $condition;
			}
			$this->start_controls_section( $id . '_style', $section );
			$this->ec_box_controls( $id, $selector );
			$this->end_controls_section();
		}

		/**
		 * The box controls ( inside an open section ).
		 *
		 * @param string $id       Control id prefix.
		 * @param string $selector Box.
		 */
		protected function ec_box_controls( $id, $selector ) {
			$this->add_control(
				$id . '_background',
				array(
					'label'     => __( 'Background color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $this->ec_sel( $selector ) => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => $id . '_border',
					'selector' => $this->ec_sel( $selector ),
				)
			);
			$this->add_responsive_control(
				$id . '_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( $this->ec_sel( $selector ) => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				$id . '_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array( $this->ec_sel( $selector ) => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => $id . '_shadow',
					'selector' => $this->ec_sel( $selector ),
				)
			);
		}

		/**
		 * A divider colour control ( inside an open section ).
		 *
		 * @param string $id       Control id.
		 * @param string $selector Elements.
		 * @param string $side     border side ( bottom, right, top ) or '' for all.
		 * @param array  $more     More selector ( inside the widget ) => declarations the colour writes ( {{VALUE}} ).
		 */
		protected function ec_divider_control( $id, $selector, $side = 'bottom', $more = array() ) {
			$property  = ( '' === $side ) ? 'border-color' : 'border-' . $side . '-color';
			$selectors = array( $this->ec_sel( $selector ) => $property . ': {{VALUE}} !important;' );
			foreach ( (array) $more as $more_selector => $css ) {
				$selectors[ ( '' === (string) $more_selector ) ? '{{WRAPPER}}' : $this->ec_sel( $more_selector ) ] = $css;
			}
			$this->add_control(
				$id,
				array(
					'label'     => __( 'Divider color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => $selectors,
				)
			);
		}

		/**
		 * The checkout steps' other controls ( inside the steps section ): the steps not reached yet, and the dividers or arrows
		 * between steps. EasyCart greys inactive steps with !important.
		 */
		protected function ec_steps_extra_controls() {
			$this->add_control(
				'steps_inactive_color',
				array(
					'label'     => __( 'Colour of the other steps', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_breadcrumb.ec_inactive, {{WRAPPER}} .ec_cart_breadcrumb_item_v2.wpeasycart-deactivated-link' => 'color: {{VALUE}} !important;' ),
				)
			);
			$this->add_control(
				'steps_divider_color',
				array(
					'label'     => __( 'Arrow colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_breadcrumb_divider, {{WRAPPER}} .ec_cart_breadcrumbs_v2 .dashicons' => 'color: {{VALUE}};' ),
				)
			);
		}

		/**
		 * The quantity box's size, corners and background ( inside an open section ). EasyCart sets its width, height and
		 * corners with !important.
		 *
		 * @param string $id       Control id prefix.
		 * @param string $selector Quantity boxes.
		 */
		protected function ec_quantity_box_controls( $id, $selector ) {
			$this->add_control(
				$id . '_box_background',
				array(
					'label'     => __( 'Quantity background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $this->ec_sel( $selector ) => 'background-color: {{VALUE}} !important;' ),
				)
			);
			$this->add_responsive_control(
				$id . '_box_width',
				array(
					'label'      => __( 'Quantity width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'range'      => array(
						'px' => array(
							'min' => 30,
							'max' => 160,
						),
					),
					'selectors'  => array( $this->ec_sel( $selector ) => 'width: {{SIZE}}{{UNIT}} !important; max-width: 100%;' ),
				)
			);
			$this->add_responsive_control(
				$id . '_box_height',
				array(
					'label'      => __( 'Quantity height', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 20,
							'max' => 80,
						),
					),
					'selectors'  => array( $this->ec_sel( $selector ) => 'height: {{SIZE}}{{UNIT}} !important; min-height: 0;' ),
				)
			);
			$this->add_responsive_control(
				$id . '_box_radius',
				array(
					'label'      => __( 'Quantity border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( $this->ec_sel( $selector ) => 'border-radius: {{SIZE}}{{UNIT}} !important;' ),
				)
			);
		}

		/**
		 * The remove button's icon, size and background ( inside an open section ). EasyCart draws it with a dashicon in
		 * ::before; the one-page cart shows the word Remove instead.
		 *
		 * @param string $id Control id prefix.
		 */
		protected function ec_remove_icon_controls( $id ) {
			$this->add_control(
				$id . '_icon',
				array(
					'label'                => __( 'Icon', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => '',
					'options'              => array(
						''        => __( 'Cross', 'wp-easycart' ),
						'trash'   => __( 'Bin', 'wp-easycart' ),
						'minus'   => __( 'Minus', 'wp-easycart' ),
						'dismiss' => __( 'Cross in a circle', 'wp-easycart' ),
					),
					'selectors_dictionary' => array(
						'trash'   => 'content: "\f182" !important;',
						'minus'   => 'content: "\f460" !important;',
						'dismiss' => 'content: "\f153" !important;',
					),
					'selectors'            => array( '{{WRAPPER}} .ec_cart .ec_cartitem_delete::before' => '{{VALUE}}' ),
				)
			);
			$this->add_responsive_control(
				$id . '_size',
				array(
					'label'      => __( 'Icon size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 10,
							'max' => 48,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .ec_cart .ec_cartitem_delete' => 'font-size: {{SIZE}}{{UNIT}}; width: calc( {{SIZE}}{{UNIT}} + 5px ); height: calc( {{SIZE}}{{UNIT}} + 5px ); line-height: calc( {{SIZE}}{{UNIT}} + 1px );' ),
				)
			);
			$this->add_responsive_control(
				$id . '_border_width',
				array(
					'label'      => __( 'Ring thickness', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 6,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .ec_cart .ec_cartitem_delete' => 'border-width: {{SIZE}}{{UNIT}} !important;' ),
				)
			);
			$this->add_control(
				$id . '_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cartitem_delete' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				$id . '_hover_background',
				array(
					'label'     => __( 'Hover background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cartitem_delete:hover, {{WRAPPER}} .ec_cartitem_delete:focus-visible' => 'background-color: {{VALUE}};' ),
				)
			);
		}

		/**
		 * Totals lines: labels and amounts styled apart, and the discount line ( inside an open section ).
		 *
		 * @param string $id     Control id prefix.
		 * @param string $labels Line labels.
		 * @param string $values Line amounts.
		 */
		protected function ec_totals_split_controls( $id, $labels, $values ) {
			$this->add_control(
				$id . '_label_color',
				array(
					'label'     => __( 'Label colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => 'before',
					'selectors' => array( $this->ec_sel( $labels ) => 'color: {{VALUE}};' ),
				)
			);
			$this->ec_typography( $id . '_value_typography', $values, null, true );
			$this->add_control(
				$id . '_value_color',
				array(
					'label'     => __( 'Amount colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $this->ec_sel( $values ) => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				$id . '_discount_color',
				array(
					'label'     => __( 'Discount colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .ec_cart_price_row_discount_total .ec_cart_price_row_label, {{WRAPPER}} .ec_cart_price_row_discount_total .ec_cart_price_row_total' => 'color: {{VALUE}};' ),
				)
			);
		}

		/**
		 * A round icon button of the mini carts ( close, remove ): size, colours ( inside an open section ).
		 *
		 * @param string $id          Control id prefix.
		 * @param string $selector    Buttons.
		 * @param bool   $with_colour Add the Normal colour ( false when the section already has one ).
		 */
		protected function ec_icon_button_controls( $id, $selector, $with_colour = true ) {
			$this->add_responsive_control(
				$id . '_size',
				array(
					'label'      => __( 'Icon size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 8,
							'max' => 48,
						),
					),
					'selectors'  => array(
						$this->ec_sel( $selector )         => 'font-size: {{SIZE}}{{UNIT}}; width: calc( {{SIZE}}{{UNIT}} + 12px ); height: calc( {{SIZE}}{{UNIT}} + 12px );',
						$this->ec_sel( $selector, ' svg' ) => 'width: 1em; height: 1em;',
					),
				)
			);
			if ( $with_colour ) {
				$this->add_control(
					$id . '_color',
					array(
						'label'     => __( 'Colour', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $this->ec_sel( $selector ) => 'color: {{VALUE}};' ),
					)
				);
			}
			$this->add_control(
				$id . '_hover_color',
				array(
					'label'     => __( 'Hover colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $this->ec_sel( $selector, ':hover' ) . ', ' . $this->ec_sel( $selector, ':focus-visible' ) => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				$id . '_hover_background',
				array(
					'label'     => __( 'Hover background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $this->ec_sel( $selector, ':hover' ) . ', ' . $this->ec_sel( $selector, ':focus-visible' ) => 'background-color: {{VALUE}};' ),
				)
			);
		}

		/**
		 * The module's own totals list ( .wpec-summary__line: dt label, dd amount ): labels and amounts apart, and the space
		 * between lines ( inside an open section ).
		 *
		 * @param string $id       Control id prefix.
		 * @param bool   $font_var The list sits in EasyCart's cart page ( fonts through --wpec-el-font ).
		 */
		protected function ec_summary_totals_controls( $id, $font_var = false ) {
			$this->add_control(
				$id . '_labels_heading',
				array(
					'label'     => __( 'Labels', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_typography( $id . '_label_typography', '.wpec-summary__line dt', null, $font_var );
			$this->add_control(
				$id . '_label_color',
				array(
					'label'     => __( 'Colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-summary__line dt' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				$id . '_values_heading',
				array(
					'label'     => __( 'Amounts', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_typography( $id . '_value_typography', '.wpec-summary__line dd', null, $font_var );
			$this->add_control(
				$id . '_value_color',
				array(
					'label'     => __( 'Colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-summary__line dd' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				$id . '_discount_color',
				array(
					'label'     => __( 'Discount colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-summary__line--discount dt, {{WRAPPER}} .wpec-summary__line--discount dd' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				$id . '_line_spacing',
				array(
					'label'      => __( 'Space between lines', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-summary__line' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};' ),
				)
			);
		}

		/**
		 * The "Divider" style of a line: its thickness and style ( inside an open section ).
		 *
		 * @param string $id       Control id prefix.
		 * @param string $selector Elements.
		 * @param string $side     border side ( bottom, top, right ).
		 */
		protected function ec_divider_style_controls( $id, $selector, $side = 'bottom' ) {
			$this->add_responsive_control(
				$id . '_width',
				array(
					'label'      => __( 'Divider thickness', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 10,
						),
					),
					'selectors'  => array( $this->ec_sel( $selector ) => 'border-' . $side . '-width: {{SIZE}}{{UNIT}} !important;' ),
				)
			);
			$this->add_control(
				$id . '_style',
				array(
					'label'     => __( 'Divider style', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '',
					'options'   => array(
						''       => __( 'Default', 'wp-easycart' ),
						'solid'  => __( 'Solid', 'wp-easycart' ),
						'dashed' => __( 'Dashed', 'wp-easycart' ),
						'dotted' => __( 'Dotted', 'wp-easycart' ),
						'double' => __( 'Double', 'wp-easycart' ),
						'none'   => __( 'None', 'wp-easycart' ),
					),
					'selectors' => array( $this->ec_sel( $selector ) => 'border-' . $side . '-style: {{VALUE}} !important;' ),
				)
			);
		}

		/**
		 * A content section of text fields that replace the store's own wording in this widget ( labels, headings, buttons
		 * EasyCart's templates print ). Registered only while WP EasyCart can pass its wording through the filter
		 * wp_easycart_language_text ( WP_EasyCart_Elementor_Checkout_Module::language_filter_ready() ), so no field ever does
		 * nothing. The text applies while this widget draws, and in the checkout's later requests.
		 *
		 * @param string $id    Section id.
		 * @param string $label Section label.
		 * @param array  $keys  Keys of WP_EasyCart_Elementor_Checkout_Module::text_keys().
		 * @param array  $condition Section condition.
		 */
		protected function ec_text_override_section( $id, $label, $keys, $condition = array() ) {
			if ( ! method_exists( 'WP_EasyCart_Elementor_Checkout_Module', 'language_filter_ready' ) || ! WP_EasyCart_Elementor_Checkout_Module::language_filter_ready() ) {
				return;
			}
			$known   = WP_EasyCart_Elementor_Checkout_Module::text_keys();
			$section = array(
				'label' => $label,
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			);
			if ( $condition ) {
				$section['condition'] = $condition;
			}
			$this->start_controls_section( $id, $section );
			$this->add_control(
				$id . '_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Leave a field empty for your store\'s own wording ( Settings › Language ). What you type here shows in this widget only.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			foreach ( (array) $keys as $key ) {
				if ( ! isset( $known[ $key ] ) ) {
					continue;
				}
				$this->add_control(
					'ec_text_' . $key,
					array(
						'label'       => $known[ $key ]['label'],
						'type'        => \Elementor\Controls_Manager::TEXT,
						'default'     => '',
						'placeholder' => WP_EasyCart_Elementor_Checkout_Module::store_text( $key ),
						'label_block' => true,
						'ai'          => array( 'active' => false ),
					)
				);
			}
			$this->end_controls_section();
		}

		/**
		 * Messages style section ( problems and confirmations the cart and checkout show ).
		 *
		 * @param string $errors    Error selector.
		 * @param string $successes Success selector.
		 */
		protected function ec_notices_section( $errors, $successes ) {
			$this->start_controls_section(
				'notices_style',
				array(
					'label' => __( 'Messages', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_typography( 'notices_typography', $errors . ', ' . $successes, null, true );
			$groups = array(
				'error'   => array( __( 'Problems', 'wp-easycart' ), $errors ),
				'success' => array( __( 'Confirmations', 'wp-easycart' ), $successes ),
			);
			foreach ( $groups as $key => $group ) {
				$this->add_control(
					'notices_' . $key . '_heading',
					array(
						'label'     => $group[0],
						'type'      => \Elementor\Controls_Manager::HEADING,
						'separator' => ( 'success' === $key ) ? 'before' : 'none',
					)
				);
				$this->add_control(
					'notices_' . $key . '_color',
					array(
						'label'     => __( 'Text color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $this->ec_sel( $group[1] ) => 'color: {{VALUE}} !important;' ),
					)
				);
				$this->add_control(
					'notices_' . $key . '_background',
					array(
						'label'     => __( 'Background color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $this->ec_sel( $group[1] ) => 'background-color: {{VALUE}} !important;' ),
					)
				);
				$this->add_control(
					'notices_' . $key . '_border_color',
					array(
						'label'     => __( 'Border color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $this->ec_sel( $group[1] ) => 'border-color: {{VALUE}} !important;' ),
					)
				);
			}
			$this->end_controls_section();
		}

		/**
		 * Alignment choices.
		 *
		 * @param string $kind text ( left / center / right ) or flex ( flex-start / center / flex-end ).
		 * @return array
		 */
		protected function ec_align_options( $kind = 'text' ) {
			$values = ( 'flex' === $kind ) ? array( 'flex-start', 'center', 'flex-end' ) : array( 'left', 'center', 'right' );
			return array(
				$values[0] => array(
					'title' => __( 'Left', 'wp-easycart' ),
					'icon'  => 'eicon-text-align-left',
				),
				$values[1] => array(
					'title' => __( 'Center', 'wp-easycart' ),
					'icon'  => 'eicon-text-align-center',
				),
				$values[2] => array(
					'title' => __( 'Right', 'wp-easycart' ),
					'icon'  => 'eicon-text-align-right',
				),
			);
		}

		/**
		 * Content section: what shows when the cart is empty ( a message and a button, or a saved Elementor template ).
		 */
		protected function ec_empty_cart_controls() {
			$this->start_controls_section(
				'ec_empty_section',
				array(
					'label' => __( 'When the cart is empty', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_empty_source',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'message',
					'options' => array(
						'message'  => __( 'A message and a button', 'wp-easycart' ),
						'template' => __( 'A saved template', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_empty_text',
				array(
					'label'       => __( 'Message', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXTAREA,
					'rows'        => 2,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_empty_cart', 'There are no items in your cart.', 'cart' ) ),
					'description' => __( 'Leave it empty for your store\'s own wording ( Settings › Language ).', 'wp-easycart' ),
					'condition'   => array( 'ec_empty_source' => 'message' ),
				)
			);
			$this->add_control(
				'ec_empty_button',
				array(
					'label'       => __( 'Button text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_return_to_store', 'RETURN TO STORE', 'cart' ) ),
					'condition'   => array( 'ec_empty_source' => 'message' ),
				)
			);
			$this->add_control(
				'ec_empty_link',
				array(
					'label'       => __( 'Button link', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::URL,
					'dynamic'     => array( 'active' => true ),
					'placeholder' => __( 'Your store page', 'wp-easycart' ),
					'condition'   => array( 'ec_empty_source' => 'message' ),
				)
			);
			$this->add_control(
				'ec_empty_template',
				array(
					'label'       => __( 'Template', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => $this->ec_saved_templates(),
					'default'     => '',
					'description' => __( 'A section or page template from Templates › Saved Templates.', 'wp-easycart' ),
					'condition'   => array( 'ec_empty_source' => 'template' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Saved Elementor templates, id => title ( the first 100 ).
		 *
		 * @return array
		 */
		protected function ec_saved_templates() {
			static $templates = null;
			if ( null !== $templates ) {
				return $templates;
			}
			$templates = array( '' => __( 'Choose a template', 'wp-easycart' ) );
			$posts     = get_posts(
				array(
					'post_type'      => 'elementor_library',
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( (array) $posts as $post ) {
				$templates[ (string) $post->ID ] = ( '' !== $post->post_title ) ? $post->post_title : '#' . $post->ID;
			}
			return $templates;
		}

		/**
		 * A saved template's content ( '' when it is missing, not published, or the one being drawn ).
		 *
		 * @param int $template_id Template.
		 * @return string
		 */
		protected function ec_template_html( $template_id ) {
			$template_id = (int) $template_id;
			if ( $template_id <= 0 || 'publish' !== get_post_status( $template_id ) || ! class_exists( '\Elementor\Plugin' ) ) {
				return '';
			}
			if ( WP_EasyCart_Elementor_Checkout_Module::current_document_id() === $template_id ) {
				return '';
			}
			return (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id, true );
		}

		/**
		 * The empty cart state.
		 *
		 * @param array $settings Widget settings.
		 * @param bool  $hidden   Printed hidden ( checkout.js shows it when the cart page says the cart is empty ).
		 */
		protected function ec_render_empty( $settings, $hidden = false ) {
			echo '<div class="wpec-cart-empty"' . ( $hidden ? ' hidden' : '' ) . '>';
			if ( isset( $settings['ec_empty_source'] ) && 'template' === $settings['ec_empty_source'] ) {
				echo $this->ec_template_html( isset( $settings['ec_empty_template'] ) ? $settings['ec_empty_template'] : 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's own rendering of a saved template.
			} else {
				$text   = ( isset( $settings['ec_empty_text'] ) && '' !== trim( (string) $settings['ec_empty_text'] ) ) ? (string) $settings['ec_empty_text'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_empty_cart', 'There are no items in your cart.', 'cart' ) );
				$button = ( isset( $settings['ec_empty_button'] ) && '' !== trim( (string) $settings['ec_empty_button'] ) ) ? (string) $settings['ec_empty_button'] : wp_strip_all_tags( WP_EasyCart_Elementor_Checkout_Module::text( 'cart_return_to_store', 'RETURN TO STORE', 'cart' ) );
				$link   = ( isset( $settings['ec_empty_link']['url'] ) && '' !== $settings['ec_empty_link']['url'] ) ? $settings['ec_empty_link'] : array( 'url' => WP_EasyCart_Elementor_Checkout_Module::store_url() );
				echo '<p class="wpec-cart-empty__text">' . esc_html( $text ) . '</p>';
				if ( '' !== trim( $button ) ) {
					echo '<a class="wpec-cart-empty__button" href="' . esc_url( $link['url'] ) . '"' . ( ! empty( $link['is_external'] ) ? ' target="_blank"' : '' ) . ( ! empty( $link['nofollow'] ) ? ' rel="nofollow"' : '' ) . '>' . esc_html( $button ) . '</a>';
				}
			}
			echo '</div>';
		}

		/**
		 * Style sections for the empty cart state ( the widget's own, and EasyCart's when the cart page draws it ).
		 */
		protected function ec_empty_style_sections() {
			$this->ec_text_section(
				'empty_text',
				__( 'Empty cart message', 'wp-easycart' ),
				'.wpec-cart-empty__text, .ec_cart_empty',
				array(
					'align'     => true,
					'font_var'  => true,
					'condition' => array( 'ec_empty_source' => 'message' ),
				)
			);
			$this->ec_button_section(
				'empty_button',
				__( 'Empty cart button', 'wp-easycart' ),
				'.wpec-cart-empty__button, a.ec_cart_empty_button',
				array(
					'vars'      => 'empty',
					'font_var'  => true,
					'condition' => array( 'ec_empty_source' => 'message' ),
				)
			);
		}

		/**
		 * A locked Pro section: what it does, and Upgrade ( or Update, for an older WP EasyCart PRO ).
		 *
		 * @param string $id          Section id.
		 * @param string $label       Section label.
		 * @param string $feature     Feature key ( 'elementor' upsell catalog ).
		 * @param string $description What the section does.
		 * @param array  $mock        Names of the controls it holds ( shown greyed out ).
		 */
		protected function ec_locked_section( $id, $label, $feature, $description, $mock = array() ) {
			$state = function_exists( 'wp_easycart_elementor_pro_feature' ) ? wp_easycart_elementor_pro_feature( $feature ) : 'locked';
			$badge = ( class_exists( 'wp_easycart_admin_edition' ) && method_exists( 'wp_easycart_admin_edition', 'badge' ) ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro', 'wp-easycart' );
			$this->start_controls_section(
				$id,
				array(
					'label' => $label . ' · ' . ( ( 'update' === $state ) ? __( 'Update', 'wp-easycart' ) : $badge ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$html = '<div class="wpec-el-locked-controls"><p>' . esc_html( $description ) . '</p>';
			if ( $mock ) {
				$html .= '<ul class="wpec-el-locked-mock" aria-hidden="true" style="margin:8px 0 12px;padding:0;list-style:none;opacity:.6;line-height:1.9;">';
				foreach ( $mock as $item ) {
					$html .= '<li><span class="eicon-lock" aria-hidden="true"></span> ' . esc_html( $item ) . '</li>';
				}
				$html .= '</ul>';
			}
			if ( 'update' === $state ) {
				$html .= '<p><strong>' . esc_html__( 'Update WP EasyCart PRO to use this.', 'wp-easycart' ) . '</strong></p><a class="elementor-button elementor-button-default" href="' . esc_url( admin_url( 'plugins.php' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Update', 'wp-easycart' ) . '</a>';
			} else {
				$text  = function_exists( 'wp_easycart_elementor_requires_text' ) ? wp_easycart_elementor_requires_text( $label ) : '';
				$url   = function_exists( 'wp_easycart_elementor_upgrade_url' ) ? wp_easycart_elementor_upgrade_url( $feature ) : '';
				$html .= '<p><strong>' . esc_html( $text ) . '</strong></p><a class="elementor-button elementor-button-success" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Upgrade', 'wp-easycart' ) . '</a>';
			}
			$html .= '</div>';
			$this->add_control(
				$id . '_locked',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => $html,
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Whether a switch is on ( a missing value takes the default ).
		 *
		 * @param array  $settings Settings.
		 * @param string $key      Control.
		 * @param bool   $fallback When missing.
		 * @return bool
		 */
		protected function ec_on( $settings, $key, $fallback = true ) {
			if ( ! isset( $settings[ $key ] ) ) {
				return $fallback;
			}
			return 'yes' === $settings[ $key ];
		}
	}

endif;
