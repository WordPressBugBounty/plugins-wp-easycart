<?php
/**
 * Share Buttons widget ( wp_easycart_product_share, 6.0.2 ).
 *
 * Facebook, X, Pinterest, LinkedIn, email and "Copy link" buttons for the product's own page ( wherever the buttons sit ), in
 * the order the merchant drags them, with the networks' colours or their own. Icons are Elementor's ( Icons_Manager ), and each
 * button can take another icon. Replaces wp_easycart_product_details_social.
 *
 * Round 11: WhatsApp, Telegram, Reddit, Threads, Bluesky ( inline SVG icons ), text message, Print and the device's own share
 * menu ( navigator.share, shown only where it exists ); a colour per button in network colours, border, shadow, padding for
 * text buttons and a grow / lift hover.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Share_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Share Buttons.
	 */
	class WP_EasyCart_Elementor_Product_Info_Share_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-share';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_share';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Share Buttons', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-share';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'share', 'share buttons', 'social', 'social icons', 'facebook', 'x', 'twitter', 'pinterest', 'linkedin', 'email', 'copy link' );
		}

		/**
		 * Whether Elementor draws font icons as inline SVG ( then no icon font is needed ).
		 *
		 * @return bool
		 */
		private function inline_icons() {
			if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->experiments ) || ! is_object( \Elementor\Plugin::$instance->experiments ) || ! method_exists( \Elementor\Plugin::$instance->experiments, 'is_feature_active' ) ) {
				return false;
			}
			return (bool) \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' );
		}

		/**
		 * The module stylesheet, and Font Awesome unless Elementor draws icons inline.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			$styles = parent::get_style_depends();
			if ( ! $this->inline_icons() ) {
				$styles[] = 'elementor-icons-fa-brands';
				$styles[] = 'elementor-icons-fa-solid';
			}
			return $styles;
		}

		/**
		 * Script: Copy link.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return $this->pi_script_depends();
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			$this->start_controls_section(
				'section_share',
				array(
					'label' => __( 'Buttons', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$repeater = new \Elementor\Repeater();
			$repeater->add_control(
				'network',
				array(
					'label'   => __( 'Network', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'facebook',
					'options' => $this->network_names(),
				)
			);
			$repeater->add_control(
				'label',
				array(
					'label'       => __( 'Text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'Leave empty for the network’s name', 'wp-easycart' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$repeater->add_control(
				'icon',
				array(
					'label'       => __( 'Other icon', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::ICONS,
					'default'     => array(
						'value'   => '',
						'library' => '',
					),
					'description' => __( 'Leave empty for the network’s own icon.', 'wp-easycart' ),
				)
			);
			$repeater->add_control(
				'button_color',
				array(
					'label'       => __( 'Button color', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'description' => __( 'Replaces the network’s color while Colors is “Each network’s colors”.', 'wp-easycart' ),
					'selectors'   => array( '{{WRAPPER}} .wpec-pi-share {{CURRENT_ITEM}}.wpec-pi-share__btn' => '--wpec-pi-share-brand: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'networks',
				array(
					'label'       => __( 'Buttons', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::REPEATER,
					'fields'      => $repeater->get_controls(),
					'default'     => array(
						array( 'network' => 'facebook' ),
						array( 'network' => 'x' ),
						array( 'network' => 'pinterest' ),
						array( 'network' => 'linkedin' ),
						array( 'network' => 'email' ),
						array( 'network' => 'copy' ),
					),
					'title_field' => '<# var wpecPiNets = ' . wp_json_encode( $this->network_names() ) . '; #>{{{ label ? label : wpecPiNets[ network ] }}}',
				)
			);
			$this->add_control(
				'view',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'icon',
					'options' => array(
						'icon'      => __( 'Icons', 'wp-easycart' ),
						'icon_text' => __( 'Icons and text', 'wp-easycart' ),
						'text'      => __( 'Text', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'shape',
				array(
					'label'   => __( 'Shape', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'circle',
					'options' => array(
						'circle'  => __( 'Circle', 'wp-easycart' ),
						'rounded' => __( 'Rounded', 'wp-easycart' ),
						'square'  => __( 'Square', 'wp-easycart' ),
						'plain'   => __( 'Icon only, no background', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'colors',
				array(
					'label'   => __( 'Colors', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'brand',
					'options' => array(
						'brand'  => __( 'Each network’s colors', 'wp-easycart' ),
						'custom' => __( 'My colors ( Style tab )', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'share_label',
				array(
					'label'       => __( 'Text before the buttons', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'For example: Share', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_share_style',
				array(
					'label' => __( 'Buttons', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-share', 'flex' );
			$this->add_responsive_control(
				'gap',
				array(
					'label'      => __( 'Space between buttons', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-share' => '--wpec-pi-share-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'button_size',
				array(
					'label'      => __( 'Button size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 20,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-share' => '--wpec-pi-share-size: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'icon_size',
				array(
					'label'      => __( 'Icon size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 8,
							'max' => 60,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-share' => '--wpec-pi-share-icon: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->start_controls_tabs(
				'share_colors',
				array( 'condition' => array( 'colors' => 'custom' ) )
			);
			$this->start_controls_tab( 'share_colors_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->add_control(
				'icon_color',
				array(
					'label'     => __( 'Icon and text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-share' => '--wpec-pi-share-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'button_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-share' => '--wpec-pi-share-bg: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'share_colors_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->add_control(
				'icon_hover_color',
				array(
					'label'     => __( 'Icon and text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-share' => '--wpec-pi-share-hover-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'button_hover_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-share' => '--wpec-pi-share-hover-bg: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'      => 'text_typography',
					'selector'  => '{{WRAPPER}} .wpec-pi-share__text',
					'condition' => array( 'view!' => 'icon' ),
				)
			);
			$this->add_responsive_control(
				'text_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-share' => '--wpec-pi-share-pad: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
					'condition'  => array( 'view!' => 'icon' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'      => 'button_border',
					'separator' => 'before',
					'selector'  => '{{WRAPPER}} .wpec-pi-share .wpec-pi-share__btn',
				)
			);
			$this->add_control(
				'button_hover_border_color',
				array(
					'label'     => __( 'Border color on hover', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-share .wpec-pi-share__btn:hover, {{WRAPPER}} .wpec-pi-share .wpec-pi-share__btn:focus-visible' => 'border-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'button_shadow',
					'selector' => '{{WRAPPER}} .wpec-pi-share .wpec-pi-share__btn',
				)
			);
			$this->add_control(
				'hover_animation',
				array(
					'label'       => __( 'On hover', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'separator'   => 'before',
					'options'     => array(
						''     => __( 'No movement', 'wp-easycart' ),
						'grow' => __( 'Grow', 'wp-easycart' ),
						'lift' => __( 'Lift', 'wp-easycart' ),
					),
					'description' => __( 'Shoppers who ask their device for less motion see no movement.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_share_label_style',
				array(
					'label'     => __( 'Text before the buttons', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'share_label!' => '' ),
				)
			);
			$this->pi_text_controls( 'share_label', '{{WRAPPER}} .wpec-pi-share__label' );
			$this->end_controls_section();
		}

		/**
		 * The networks for the panel ( slug => name ).
		 *
		 * @return array
		 */
		private function network_names() {
			return array(
				'facebook'  => 'Facebook',
				'x'         => 'X',
				'pinterest' => 'Pinterest',
				'linkedin'  => 'LinkedIn',
				'whatsapp'  => 'WhatsApp',
				'telegram'  => 'Telegram',
				'reddit'    => 'Reddit',
				'threads'   => 'Threads',
				'bluesky'   => 'Bluesky',
				'email'     => __( 'Email', 'wp-easycart' ),
				'sms'       => __( 'Text message', 'wp-easycart' ),
				'copy'      => __( 'Copy link', 'wp-easycart' ),
				'print'     => __( 'Print', 'wp-easycart' ),
				'native'    => __( 'Share menu of the device ( where it has one )', 'wp-easycart' ),
			);
		}

		/**
		 * An icon's markup through Elementor ( or one of the module's own SVG icons ).
		 *
		 * @param array $icon ICONS value.
		 * @return string
		 */
		private function icon_html( $icon ) {
			return WP_EasyCart_Product_Info::icon_html( $icon );
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
			$known = WP_EasyCart_Product_Info::share_networks();
			$rows  = ( isset( $settings['networks'] ) && is_array( $settings['networks'] ) ) ? $settings['networks'] : array();
			$items = array();
			foreach ( $rows as $row ) {
				if ( is_array( $row ) && isset( $row['network'] ) && isset( $known[ $row['network'] ] ) ) {
					$items[] = $row;
				}
			}
			if ( ! $items ) {
				$this->ec_editor_notice( __( 'No share buttons yet.', 'wp-easycart' ), __( 'Add a button under Content › Buttons.', 'wp-easycart' ) );
				return;
			}
			$view   = ( isset( $settings['view'] ) && in_array( $settings['view'], array( 'icon', 'icon_text', 'text' ), true ) ) ? $settings['view'] : 'icon';
			$shape  = ( isset( $settings['shape'] ) && in_array( $settings['shape'], array( 'circle', 'rounded', 'square', 'plain' ), true ) ) ? $settings['shape'] : 'circle';
			$colors = ( isset( $settings['colors'] ) && 'custom' === $settings['colors'] ) ? 'custom' : 'brand';
			$label  = isset( $settings['share_label'] ) ? trim( (string) $settings['share_label'] ) : '';
			$copied = WP_EasyCart_Product_Info::text( 'share_copied', __( 'Link copied', 'wp-easycart' ) );
			$hover  = ( isset( $settings['hover_animation'] ) && in_array( $settings['hover_animation'], array( 'grow', 'lift' ), true ) ) ? ' wpec-pi-share--hover-' . $settings['hover_animation'] : '';

			echo '<div class="wpec-el wpec-pi-share wpec-pi-share--' . esc_attr( $shape ) . ' wpec-pi-share--' . esc_attr( $colors ) . ' wpec-pi-share--view-' . esc_attr( $view ) . esc_attr( $hover ) . '" data-wpec-pi-copied="' . esc_attr( $copied ) . '">';
			if ( '' !== $label ) {
				echo '<span class="wpec-pi-share__label">' . esc_html( $label ) . '</span>';
			}
			foreach ( $items as $row ) {
				$network = $row['network'];
				$info    = $known[ $network ];
				$text    = ( isset( $row['label'] ) && '' !== trim( (string) $row['label'] ) ) ? trim( (string) $row['label'] ) : $info['name'];
				$icon    = ( isset( $row['icon'] ) && is_array( $row['icon'] ) && ! empty( $row['icon']['value'] ) ) ? $row['icon'] : $info['icon'];
				$inner   = '';
				if ( 'text' !== $view ) {
					$icon_html = $this->icon_html( $icon );
					$inner    .= '<span class="wpec-pi-share__icon" aria-hidden="true">' . ( '' !== $icon_html ? $icon_html : esc_html( substr( $info['name'], 0, 1 ) ) ) . '</span>';
				}
				if ( 'icon' !== $view ) {
					$inner .= '<span class="wpec-pi-share__text">' . esc_html( $text ) . '</span>';
				}
				$classes = 'wpec-pi-share__btn wpec-pi-share__btn--' . $network;
				if ( isset( $row['_id'] ) && '' !== (string) $row['_id'] ) {
					/* Round 11: the row's own color ( Button color ) reaches it through Elementor's {{CURRENT_ITEM}}. */
					$classes .= ' elementor-repeater-item-' . sanitize_html_class( (string) $row['_id'] );
				}
				/* A button that shows the merchant's own text is named by that text ( what voice control users say ). */
				$name = ( 'icon' !== $view && isset( $row['label'] ) && '' !== trim( (string) $row['label'] ) ) ? $text : $info['action'];
				if ( 'copy' === $network ) {
					echo '<button type="button" class="' . esc_attr( $classes ) . '" data-wpec-pi-copy="' . esc_url( $product->get_product_link() ) . '" aria-label="' . esc_attr( $name ) . '" title="' . esc_attr( $info['action'] ) . '">' . $inner . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $inner is escaped above ( Icons_Manager markup ).
				} elseif ( 'print' === $network ) {
					echo '<button type="button" class="' . esc_attr( $classes ) . '" data-wpec-pi-print="1" aria-label="' . esc_attr( $name ) . '" title="' . esc_attr( $info['action'] ) . '">' . $inner . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $inner is escaped above ( Icons_Manager markup ).
				} elseif ( 'native' === $network ) {
					/* Shown by the script only where the device has a share menu ( navigator.share ). */
					echo '<button type="button" class="' . esc_attr( $classes ) . '" data-wpec-pi-native="' . esc_url( $product->get_product_link() ) . '" data-title="' . esc_attr( WP_EasyCart_Product_Info::title( $product ) ) . '" aria-label="' . esc_attr( $name ) . '" title="' . esc_attr( $info['action'] ) . '" hidden>' . $inner . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $inner is escaped above ( Icons_Manager markup ).
				} else {
					$target = in_array( $network, array( 'email', 'sms' ), true ) ? '' : ' target="_blank" rel="noopener noreferrer nofollow"';
					echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( WP_EasyCart_Product_Info::share_url( $network, $product ), WP_EasyCart_Product_Info::share_protocols() ) . '"' . $target . ' aria-label="' . esc_attr( $name ) . '" title="' . esc_attr( $info['action'] ) . '">' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $target is fixed markup; $inner is escaped above ( Icons_Manager markup ).
				}
			}
			echo '<span class="wpec-el-sr-only wpec-pi-share__status" role="status" aria-live="polite"></span>';
			echo '</div>';
		}
	}

endif;
