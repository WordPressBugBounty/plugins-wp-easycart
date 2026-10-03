<?php
/**
 * Shared base of the product information widgets ( module product-info, 6.0.2 ).
 *
 * Adds to WP_EasyCart_Elementor_Widget_Base: the product lookup with the "no product" editor note and the product's
 * structured data, the module's assets, and the controls these widgets repeat ( alignment, text colour and typography, the
 * "Follow each product's settings" switch ), so they read and sit the same way in every widget.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Base of the product information widgets.
	 */
	abstract class WP_EasyCart_Elementor_Product_Info_Widget_Base extends WP_EasyCart_Elementor_Widget_Base {

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'product';

		/**
		 * The shared widget styles ( parent ) and the module stylesheet.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_merge( parent::get_style_depends(), array( WP_EasyCart_Product_Info::HANDLE ) );
		}

		/**
		 * The store script and the module script, for widgets with behaviour ( the others keep the parent's store script ).
		 *
		 * @return array
		 */
		protected function pi_script_depends() {
			return array_merge( parent::get_script_depends(), array( WP_EasyCart_Product_Info::HANDLE ) );
		}

		/**
		 * The product for these settings, or null after an editor note ( visitors see nothing ). Also prints the product's
		 * structured data once per page, as every product widget does ( not in a Loop Grid card ), and, for the page's own
		 * product, its view events ( WP_EasyCart_Product_Info::view_events(): once per product, never in the editor, a Loop
		 * Grid card or an assigned EasyCart product template ).
		 *
		 * @param array $settings Widget settings.
		 * @param bool  $details  Build the product as a details page ( its categories, menus and featured products ); widgets
		 *                        that need none of those pass false, which is lighter in product loops.
		 * @return ec_product|null
		 */
		protected function pi_product( $settings, $details = true ) {
			$product = $this->ec_product( $settings, $details );
			if ( ! $product || empty( $product->product_id ) ) {
				if ( method_exists( $this, 'ec_no_product_notice' ) ) {
					$this->ec_no_product_notice();
				} else {
					$this->ec_editor_notice( __( 'No product to show here.', 'wp-easycart' ), __( 'Add a product to your store, or choose one under Content › Product.', 'wp-easycart' ) );
				}
				return null;
			}
			if ( ! self::ec_is_loop_card() && function_exists( 'wp_easycart_product_details_schema' ) ) {
				wp_easycart_product_details_schema( $product );
			}
			if ( ! isset( $settings['ec_product_source'] ) || 'pick' !== $settings['ec_product_source'] ) {
				WP_EasyCart_Product_Info::view_events( $product );
			}
			return $product;
		}

		/**
		 * The product's name for editor notes.
		 *
		 * @param ec_product $product Product.
		 * @return string
		 */
		protected function pi_name( $product ) {
			$name = WP_EasyCart_Product_Info::title( $product );
			return ( '' !== $name ) ? $name : __( 'This product', 'wp-easycart' );
		}

		/**
		 * A link to a WP EasyCart settings page ( for panel notes ).
		 *
		 * @param string $slug  Settings page.
		 * @param string $label Link text.
		 * @return string HTML.
		 */
		protected function pi_settings_link( $slug, $label ) {
			if ( class_exists( 'wp_easycart_admin_settings_registry' ) && method_exists( 'wp_easycart_admin_settings_registry', 'page_url' ) ) {
				$url = wp_easycart_admin_settings_registry::page_url( $slug );
			} else {
				$url = admin_url( 'admin.php?page=wp-easycart-settings&subpage=' . rawurlencode( $slug ) );
			}
			return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
		}

		/**
		 * A short note in the panel.
		 *
		 * @param string $id   Control ID.
		 * @param string $html Note ( escaped by the caller ).
		 * @param array  $args Extra control arguments ( condition ).
		 */
		protected function pi_note( $id, $html, $args = array() ) {
			$this->add_control(
				$id,
				array_merge(
					array(
						'type'            => \Elementor\Controls_Manager::RAW_HTML,
						'raw'             => $html,
						'content_classes' => 'elementor-descriptor',
					),
					$args
				)
			);
		}

		/**
		 * "Follow each product's settings" ( on ): hide what the product's own switch turns off.
		 *
		 * @param string $description What it follows.
		 */
		protected function pi_follow_control( $description ) {
			$this->add_control(
				'follow_product',
				array(
					'label'       => __( 'Follow each product’s settings', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => $description,
				)
			);
		}

		/**
		 * Alignment ( responsive ). $mode 'text' sets text-align; 'flex' sets justify-content on a flex row.
		 *
		 * @param string $selector Element.
		 * @param string $mode     text | flex.
		 * @param string $id       Control ID.
		 * @param bool   $justify  Offer Justified.
		 */
		protected function pi_align_control( $selector, $mode = 'text', $id = 'align', $justify = false ) {
			$options = array(
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
			);
			if ( $justify ) {
				$options['justify'] = array(
					'title' => __( 'Justified', 'wp-easycart' ),
					'icon'  => 'eicon-text-align-justify',
				);
			}
			$args = array(
				'label'   => __( 'Alignment', 'wp-easycart' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => $options,
			);
			if ( 'flex' === $mode ) {
				$args['selectors_dictionary'] = array(
					'left'    => 'flex-start',
					'center'  => 'center',
					'right'   => 'flex-end',
					'justify' => 'space-between',
				);
				$args['selectors']            = array( $selector => 'justify-content: {{VALUE}};' );
			} else {
				$args['selectors'] = array( $selector => 'text-align: {{VALUE}};' );
			}
			$this->add_responsive_control( $id, $args );
		}

		/**
		 * Colour + typography for one element.
		 *
		 * @param string $prefix   Control ID prefix ( {prefix}_color, {prefix}_typography ).
		 * @param string $selector Element.
		 * @param string $kit   text | primary | secondary | accent.
		 * @param array  $args     'hover' => selector for a hover colour, 'label' => colour label, 'global' => false for controls
		 *                         added after 6.0.2's first widgets: no site kit default, so an untouched widget keeps its look.
		 */
		protected function pi_text_controls( $prefix, $selector, $kit = 'text', $args = array() ) {
			if ( isset( $args['global'] ) && false === $args['global'] ) {
				$this->add_control(
					$prefix . '_color',
					array(
						'label'     => isset( $args['label'] ) ? $args['label'] : __( 'Color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $selector => 'color: {{VALUE}};' ),
					)
				);
				if ( ! empty( $args['hover'] ) ) {
					$this->add_control(
						$prefix . '_hover_color',
						array(
							'label'     => __( 'Hover color', 'wp-easycart' ),
							'type'      => \Elementor\Controls_Manager::COLOR,
							'selectors' => array( $args['hover'] => 'color: {{VALUE}};' ),
						)
					);
				}
				$this->add_group_control(
					\Elementor\Group_Control_Typography::get_type(),
					array(
						'name'     => $prefix . '_typography',
						'selector' => $selector,
					)
				);
				return;
			}
			$colors     = array(
				'text'      => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_TEXT,
				'primary'   => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_PRIMARY,
				'secondary' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_SECONDARY,
				'accent'    => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT,
			);
			$typography = array(
				'text'      => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_TEXT,
				'primary'   => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_PRIMARY,
				'secondary' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_SECONDARY,
				'accent'    => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT,
			);
			$kit        = isset( $colors[ $kit ] ) ? $kit : 'text';
			$this->add_control(
				$prefix . '_color',
				array(
					'label'     => isset( $args['label'] ) ? $args['label'] : __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => array( 'default' => $colors[ $kit ] ),
					'selectors' => array( $selector => 'color: {{VALUE}};' ),
				)
			);
			if ( ! empty( $args['hover'] ) ) {
				$this->add_control(
					$prefix . '_hover_color',
					array(
						'label'     => __( 'Hover color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( $args['hover'] => 'color: {{VALUE}};' ),
					)
				);
			}
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => $prefix . '_typography',
					'global'   => array( 'default' => $typography[ $kit ] ),
					'selector' => $selector,
				)
			);
		}

		/**
		 * Box controls for one element ( 6.0.2 round 11 ): background, padding, border, corner radius and, optionally, a shadow.
		 * None has a default, so an untouched widget keeps its look.
		 *
		 * @param string $prefix   Control ID prefix ( {prefix}_background, _padding, _border, _radius, _shadow ).
		 * @param string $selector Element.
		 * @param array  $args     'shadow' => true adds a box shadow; 'separator' => 'before' on the first control.
		 */
		protected function pi_box_controls( $prefix, $selector, $args = array() ) {
			$this->add_control(
				$prefix . '_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => isset( $args['separator'] ) ? $args['separator'] : 'default',
					'selectors' => array( $selector => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				$prefix . '_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array( $selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => $prefix . '_border',
					'selector' => $selector,
				)
			);
			$this->add_responsive_control(
				$prefix . '_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			if ( ! empty( $args['shadow'] ) ) {
				$this->add_group_control(
					\Elementor\Group_Control_Box_Shadow::get_type(),
					array(
						'name'     => $prefix . '_shadow',
						'selector' => $selector,
					)
				);
			}
		}

		/**
		 * A size slider that writes a CSS variable ( px / em ).
		 *
		 * @param string $id       Control ID.
		 * @param string $label    Label.
		 * @param string $selector Element that carries the variable.
		 * @param string $variable CSS variable.
		 * @param int    $max      Largest px value.
		 * @param array  $extra    More control arguments.
		 */
		protected function pi_size_control( $id, $label, $selector, $variable, $max = 60, $extra = array() ) {
			$this->add_responsive_control(
				$id,
				array_merge(
					array(
						'label'      => $label,
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px', 'em' ),
						'range'      => array(
							'px' => array(
								'min' => 0,
								'max' => $max,
							),
							'em' => array(
								'min'  => 0,
								'max'  => 5,
								'step' => 0.1,
							),
						),
						'selectors'  => array( $selector => $variable . ': {{SIZE}}{{UNIT}};' ),
					),
					$extra
				)
			);
		}

		/**
		 * An ICONS setting's markup wrapped for this module ( '' without an icon ).
		 *
		 * @param mixed  $icon      ICONS value.
		 * @param string $css_class Wrapper class.
		 * @return string
		 */
		protected function pi_icon( $icon, $css_class ) {
			$html = WP_EasyCart_Product_Info::icon_html( $icon );
			return ( '' !== $html ) ? '<span class="' . esc_attr( $css_class ) . '" aria-hidden="true">' . $html . '</span>' : '';
		}

		/**
		 * The product's switch is on, or this widget does not follow it.
		 *
		 * @param array      $settings Settings.
		 * @param ec_product $product  Product.
		 * @param string     $property use_specifications | use_customer_reviews.
		 * @return bool
		 */
		protected function pi_allowed( $settings, $product, $property ) {
			if ( ! WP_EasyCart_Product_Info::on( $settings, 'follow_product' ) ) {
				return true;
			}
			return ! empty( $product->$property );
		}
	}

endif;
