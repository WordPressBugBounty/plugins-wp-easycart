<?php
/**
 * Breadcrumbs widget ( wp_easycart_product_breadcrumbs, 6.0.2 ).
 *
 * Home › Store › the product's category ( with the categories above it ) › the product, every label editable, as a navigation
 * landmark with breadcrumb data for search engines ( BreadcrumbList, left to an SEO plugin when one is active ). The older
 * widget followed the store's menus, so a store without menus got Home / Store / Product. Replaces
 * wp_easycart_product_details_breadcrumbs.
 *
 * Round 11: icons for Home ( with or without its text, which screen readers still hear ) and for the separator, separator type,
 * a longest product name ( cut with "…" ), and box controls.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Breadcrumbs_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Breadcrumbs.
	 */
	class WP_EasyCart_Elementor_Product_Info_Breadcrumbs_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-breadcrumbs';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_breadcrumbs';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Breadcrumbs', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-breadcrumbs';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'breadcrumbs', 'breadcrumb', 'navigation', 'path', 'trail', 'product', 'category' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			$this->start_controls_section(
				'section_breadcrumbs',
				array(
					'label' => __( 'Breadcrumbs', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_home',
				array(
					'label'   => __( 'Home', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'home_label',
				array(
					'label'       => __( 'Home text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Product_Info::text( 'product_details_home_link', __( 'Home', 'wp-easycart' ), 'product_details' ),
					'condition'   => array( 'show_home' => 'yes' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'show_store',
				array(
					'label'   => __( 'Store page', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'store_label',
				array(
					'label'       => __( 'Store page text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Product_Info::text( 'product_details_store_link', __( 'Shop', 'wp-easycart' ), 'product_details' ),
					'condition'   => array( 'show_store' => 'yes' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'show_category',
				array(
					'label'   => __( 'Category', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'path',
					'options' => array(
						'path'     => __( 'The product’s category and the ones above it', 'wp-easycart' ),
						'category' => __( 'The product’s category only', 'wp-easycart' ),
						''         => __( 'None', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'show_current',
				array(
					'label'   => __( 'Product name', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'home_icon',
				array(
					'label'     => __( 'Home icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'default'   => array(
						'value'   => '',
						'library' => '',
					),
					'skin'      => 'inline',
					'condition' => array( 'show_home' => 'yes' ),
				)
			);
			$this->add_control(
				'home_icon_only',
				array(
					'label'       => __( 'Icon instead of the Home text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Screen readers still hear the Home text.', 'wp-easycart' ),
					'condition'   => array(
						'show_home'         => 'yes',
						'home_icon[value]!' => '',
					),
				)
			);
			$this->add_control(
				'separator',
				array(
					'label'   => __( 'Separator', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::TEXT,
					'default' => '›',
					'ai'      => array( 'active' => false ),
				)
			);
			$this->add_control(
				'separator_icon',
				array(
					'label'       => __( 'Separator icon', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::ICONS,
					'default'     => array(
						'value'   => '',
						'library' => '',
					),
					'skin'        => 'inline',
					'description' => __( 'Used instead of the separator text when you pick one.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'schema',
				array(
					'label'       => __( 'Tell search engines the path', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Adds breadcrumb data so Google can show the path in results. Left out while an SEO plugin ( Yoast SEO, Rank Math, … ) is active, since it adds its own.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_breadcrumbs_style',
				array(
					'label' => __( 'Breadcrumbs', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-breadcrumbs__list', 'flex' );
			$this->add_responsive_control(
				'gap',
				array(
					'label'      => __( 'Space around separators', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-breadcrumbs' => '--wpec-pi-crumb-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'links_heading',
				array(
					'label'     => __( 'Links', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls(
				'link',
				'{{WRAPPER}} .wpec-pi-breadcrumbs__link',
				'text',
				array( 'hover' => '{{WRAPPER}} .wpec-pi-breadcrumbs__link:hover, {{WRAPPER}} .wpec-pi-breadcrumbs__link:focus' )
			);
			$this->add_control(
				'current_heading',
				array(
					'label'     => __( 'Product name', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'current', '{{WRAPPER}} .wpec-pi-breadcrumbs__current' );
			$this->add_responsive_control(
				'current_max_width',
				array(
					'label'       => __( 'Longest product name', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px', '%', 'em' ),
					'range'       => array(
						'px' => array(
							'min' => 40,
							'max' => 600,
						),
						'%'  => array(
							'min' => 10,
							'max' => 100,
						),
					),
					'selectors'   => array( '{{WRAPPER}} .wpec-pi-breadcrumbs .wpec-pi-breadcrumbs__current' => 'display: inline-block; max-width: {{SIZE}}{{UNIT}}; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom;' ),
					'description' => __( 'A longer name ends with “…”. Leave empty to let it wrap.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'separator_heading',
				array(
					'label'     => __( 'Separator', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'separator_color',
				array(
					'label'     => __( 'Separator color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-breadcrumbs__sep' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'separator_typography',
					'selector' => '{{WRAPPER}} .wpec-pi-breadcrumbs .wpec-pi-breadcrumbs__sep',
				)
			);
			$this->pi_size_control( 'icon_size', __( 'Icon size', 'wp-easycart' ), '{{WRAPPER}} .wpec-pi-breadcrumbs', '--wpec-pi-crumb-icon', 48 );
			$this->end_controls_section();

			$this->start_controls_section(
				'section_breadcrumbs_box_style',
				array(
					'label' => __( 'Box', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_box_controls( 'box', '{{WRAPPER}} .wpec-pi-breadcrumbs', array( 'shadow' => true ) );
			$this->end_controls_section();
		}

		/**
		 * Output.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->pi_product( $settings );
			if ( ! $product ) {
				return;
			}
			$category = ( isset( $settings['show_category'] ) && in_array( $settings['show_category'], array( 'path', 'category' ), true ) ) ? $settings['show_category'] : '';
			$items    = WP_EasyCart_Product_Info::breadcrumb_trail(
				$product,
				array(
					'home'        => WP_EasyCart_Product_Info::on( $settings, 'show_home' ),
					'home_label'  => WP_EasyCart_Product_Info::label( $settings, 'home_label', WP_EasyCart_Product_Info::text( 'product_details_home_link', __( 'Home', 'wp-easycart' ), 'product_details' ) ),
					'store'       => WP_EasyCart_Product_Info::on( $settings, 'show_store' ),
					'store_label' => WP_EasyCart_Product_Info::label( $settings, 'store_label', WP_EasyCart_Product_Info::text( 'product_details_store_link', __( 'Shop', 'wp-easycart' ), 'product_details' ) ),
					'category'    => $category,
					'current'     => WP_EasyCart_Product_Info::on( $settings, 'show_current' ),
				)
			);
			if ( ! $items ) {
				$this->ec_editor_notice( __( 'Every part of the breadcrumbs is off.', 'wp-easycart' ), __( 'Turn on Home, the store page, the category or the product name under Content › Breadcrumbs.', 'wp-easycart' ) );
				return;
			}
			$separator = isset( $settings['separator'] ) ? (string) $settings['separator'] : '›';
			$sep_icon  = WP_EasyCart_Product_Info::icon_html( isset( $settings['separator_icon'] ) ? $settings['separator_icon'] : null );
			$home_icon = WP_EasyCart_Product_Info::on( $settings, 'show_home' ) ? WP_EasyCart_Product_Info::icon_html( isset( $settings['home_icon'] ) ? $settings['home_icon'] : null ) : '';
			$icon_only = ( '' !== $home_icon && WP_EasyCart_Product_Info::on( $settings, 'home_icon_only' ) );
			$last      = count( $items ) - 1;
			echo '<nav class="wpec-el wpec-pi-breadcrumbs" aria-label="' . esc_attr( WP_EasyCart_Product_Info::text( 'breadcrumbs_label', __( 'Breadcrumb', 'wp-easycart' ) ) ) . '">';
			echo '<ol class="wpec-pi-breadcrumbs__list">';
			foreach ( $items as $index => $item ) {
				echo '<li class="wpec-pi-breadcrumbs__item">';
				$name = esc_html( $item['name'] );
				if ( 0 === $index && '' !== $home_icon ) {
					/* The first item is Home when Home is on ( breadcrumb_trail() ). */
					$name = '<span class="wpec-pi-breadcrumbs__icon" aria-hidden="true">' . $home_icon . '</span>' . ( $icon_only ? '<span class="wpec-el-sr-only">' . $name . '</span>' : '<span class="wpec-pi-breadcrumbs__text">' . $name . '</span>' );
				}
				if ( '' === $item['url'] ) {
					echo '<span class="wpec-pi-breadcrumbs__current" aria-current="page">' . $name . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ( Icons_Manager markup ).
				} else {
					echo '<a class="wpec-pi-breadcrumbs__link" href="' . esc_url( $item['url'] ) . '">' . $name . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ( Icons_Manager markup ).
				}
				if ( $index < $last && '' !== $sep_icon ) {
					echo '<span class="wpec-pi-breadcrumbs__sep wpec-pi-breadcrumbs__sep--icon" aria-hidden="true">' . $sep_icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager markup.
				} elseif ( $index < $last && '' !== trim( $separator ) ) {
					echo '<span class="wpec-pi-breadcrumbs__sep" aria-hidden="true">' . esc_html( $separator ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ol>';
			echo '</nav>';
			if ( WP_EasyCart_Product_Info::on( $settings, 'schema' ) && ! WP_EasyCart_Product_Info::is_editor() && ! self::ec_is_loop_card() ) {
				WP_EasyCart_Product_Info::print_breadcrumb_schema( $product, $items );
			}
		}
	}

endif;
