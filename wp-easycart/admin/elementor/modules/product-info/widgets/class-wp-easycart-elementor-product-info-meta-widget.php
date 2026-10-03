<?php
/**
 * Product Meta widget ( wp_easycart_product_meta, 6.0.2 ).
 *
 * The product's SKU ( following the option the shopper picks ), categories, brand and tag, stacked or on one line, labels
 * editable. Every product information widget tells the store's analytics that a shopper viewed the page's product ( Meta
 * Pixel, Google Analytics; once per product per page, never in the editor or a Loop Grid card ); like the older Product Meta
 * widget this one also does it for a product picked in the widget, so a replaced older widget keeps them. Replaces
 * wp_easycart_product_details_meta, wp_easycart_product_details_category and wp_easycart_product_details_manufacturer.
 *
 * Round 11: an icon per detail, stock ( following the chosen option ), weight and dimensions, a divider between details on
 * one line, and the brand as its logo ( the brand page's featured image, or filter wp_easycart_product_brand_logo ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Meta_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Product Meta.
	 */
	class WP_EasyCart_Elementor_Product_Info_Meta_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-meta';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_meta';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Meta', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-meta';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'product', 'meta', 'sku', 'model number', 'categories', 'category', 'brand', 'manufacturer', 'tags' );
		}

		/**
		 * Script: the SKU follows the chosen option.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return $this->pi_script_depends();
		}

		/**
		 * One detail: switch + label.
		 *
		 * @param string $key         sku | categories | brand | tag.
		 * @param string $label       Panel label.
		 * @param bool   $is_on     On by default.
		 * @param string $placeholder Default text.
		 * @param string $description Help.
		 */
		private function detail_controls( $key, $label, $is_on, $placeholder, $description = '' ) {
			$args = array(
				'label'   => $label,
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => $is_on ? 'yes' : '',
			);
			if ( '' !== $description ) {
				$args['description'] = $description;
			}
			$this->add_control( 'show_' . $key, $args );
			$this->add_control(
				$key . '_label',
				array(
					'label'       => __( 'Label', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => $placeholder,
					'condition'   => array( 'show_' . $key => 'yes' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				$key . '_icon',
				array(
					'label'     => __( 'Icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'default'   => array(
						'value'   => '',
						'library' => '',
					),
					'skin'      => 'inline',
					'condition' => array( 'show_' . $key => 'yes' ),
				)
			);
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			$this->start_controls_section(
				'section_meta',
				array(
					'label' => __( 'Details', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->detail_controls( 'sku', __( 'SKU', 'wp-easycart' ), true, WP_EasyCart_Product_Info::text( 'meta_sku', __( 'SKU:', 'wp-easycart' ) ), __( 'Changes with the option the shopper picks when the option has its own SKU.', 'wp-easycart' ) );
			$this->detail_controls( 'categories', __( 'Categories', 'wp-easycart' ), true, WP_EasyCart_Product_Info::text( 'product_details_categories', __( 'Categories:', 'wp-easycart' ), 'product_details' ) );
			$this->detail_controls( 'brand', __( 'Brand ( manufacturer )', 'wp-easycart' ), true, WP_EasyCart_Product_Info::text( 'meta_brand', __( 'Brand:', 'wp-easycart' ) ) );
			$this->detail_controls( 'tag', __( 'Product tag', 'wp-easycart' ), false, WP_EasyCart_Product_Info::text( 'meta_tag', __( 'Tag:', 'wp-easycart' ) ), __( 'The label shown on the product’s card, when it has one.', 'wp-easycart' ) );
			$this->detail_controls( 'stock', __( 'Stock', 'wp-easycart' ), false, WP_EasyCart_Product_Info::text( 'meta_stock', __( 'Availability:', 'wp-easycart' ) ), __( 'In stock, how many are left ( when your store shows stock counts ), out of stock or on backorder.', 'wp-easycart' ) );
			$this->detail_controls( 'weight', __( 'Weight', 'wp-easycart' ), false, WP_EasyCart_Product_Info::text( 'meta_weight', __( 'Weight:', 'wp-easycart' ) ), __( 'The product’s shipping weight, when it has one.', 'wp-easycart' ) );
			$this->detail_controls( 'dimensions', __( 'Dimensions', 'wp-easycart' ), false, WP_EasyCart_Product_Info::text( 'meta_dimensions', __( 'Dimensions:', 'wp-easycart' ) ), __( 'Length × width × height, when they are set.', 'wp-easycart' ) );
			$this->add_control(
				'brand_display',
				array(
					'label'       => __( 'Brand shows as', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'name',
					'separator'   => 'before',
					'options'     => array(
						'name' => __( 'Its name', 'wp-easycart' ),
						'logo' => __( 'Its logo, else its name', 'wp-easycart' ),
					),
					'description' => __( 'The logo is the featured image of the brand’s page.', 'wp-easycart' ),
					'condition'   => array( 'show_brand' => 'yes' ),
				)
			);
			$this->add_control(
				'layout',
				array(
					'label'     => __( 'Layout', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'stacked',
					'separator' => 'before',
					'options'   => array(
						'stacked' => __( 'One per line', 'wp-easycart' ),
						'inline'  => __( 'All on one line', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'inline_divider',
				array(
					'label'       => __( 'Between details', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'For example: |', 'wp-easycart' ),
					'condition'   => array( 'layout' => 'inline' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'list_separator',
				array(
					'label'     => __( 'Between categories', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::TEXT,
					'default'   => ', ',
					'condition' => array( 'show_categories' => 'yes' ),
					'ai'        => array( 'active' => false ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_meta_style',
				array(
					'label' => __( 'Details', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-meta', 'text' );
			$this->add_responsive_control(
				'item_gap',
				array(
					'label'      => __( 'Space between details', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-meta' => '--wpec-pi-meta-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'label_heading',
				array(
					'label'     => __( 'Labels', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'label', '{{WRAPPER}} .wpec-pi-meta__label' );
			$this->add_control(
				'value_heading',
				array(
					'label'     => __( 'Values', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'value', '{{WRAPPER}} .wpec-pi-meta__value' );
			$this->add_control(
				'link_color',
				array(
					'label'     => __( 'Link color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'global'    => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Colors::COLOR_ACCENT ),
					'selectors' => array( '{{WRAPPER}} .wpec-pi-meta__value a' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'link_hover_color',
				array(
					'label'     => __( 'Link hover color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-meta__value a:hover, {{WRAPPER}} .wpec-pi-meta__value a:focus' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'icons_heading',
				array(
					'label'     => __( 'Icons', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'icon_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-meta' => '--wpec-pi-meta-icon-color: {{VALUE}};' ),
				)
			);
			$this->pi_size_control( 'icon_size', __( 'Size', 'wp-easycart' ), '{{WRAPPER}} .wpec-pi-meta', '--wpec-pi-meta-icon', 48 );
			$this->pi_size_control( 'icon_spacing', __( 'Space after the icon', 'wp-easycart' ), '{{WRAPPER}} .wpec-pi-meta', '--wpec-pi-meta-icon-gap', 30 );
			$this->add_control(
				'divider_heading',
				array(
					'label'     => __( 'Between details', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'layout' => 'inline' ),
				)
			);
			$this->add_control(
				'divider_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-meta .wpec-pi-meta__sep' => 'color: {{VALUE}};' ),
					'condition' => array( 'layout' => 'inline' ),
				)
			);
			$this->add_control(
				'stock_heading',
				array(
					'label'     => __( 'Stock', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'show_stock' => 'yes' ),
				)
			);
			$this->add_control(
				'stock_in_color',
				array(
					'label'     => __( 'In stock color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-meta .wpec-pi-meta__value--in' => 'color: {{VALUE}};' ),
					'condition' => array( 'show_stock' => 'yes' ),
				)
			);
			$this->add_control(
				'stock_out_color',
				array(
					'label'     => __( 'Out of stock color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-meta .wpec-pi-meta__value--out' => 'color: {{VALUE}};' ),
					'condition' => array( 'show_stock' => 'yes' ),
				)
			);
			$this->add_control(
				'stock_backorder_color',
				array(
					'label'     => __( 'Backorder color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-meta .wpec-pi-meta__value--backorder' => 'color: {{VALUE}};' ),
					'condition' => array( 'show_stock' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'logo_height',
				array(
					'label'      => __( 'Brand logo height', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'separator'  => 'before',
					'range'      => array(
						'px' => array(
							'min' => 12,
							'max' => 160,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-meta' => '--wpec-pi-meta-logo: {{SIZE}}{{UNIT}};' ),
					'condition'  => array(
						'show_brand'    => 'yes',
						'brand_display' => 'logo',
					),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * One detail row.
		 *
		 * @param string $key      Detail.
		 * @param string $label    Label.
		 * @param string $value    Value HTML ( escaped ).
		 * @param string $attrs    Extra attributes on the value ( escaped ).
		 * @param array  $settings Settings ( the detail's icon ).
		 * @param string $modifier Extra value class suffix ( stock status ).
		 */
		private function print_row( $key, $label, $value, $attrs = '', $settings = array(), $modifier = '' ) {
			$icon = $this->pi_icon( isset( $settings[ $key . '_icon' ] ) ? $settings[ $key . '_icon' ] : null, 'wpec-pi-meta__icon' );
			echo '<span class="wpec-pi-meta__item wpec-pi-meta__item--' . esc_attr( $key ) . ( '' !== $icon ? ' wpec-pi-meta__item--icon' : '' ) . '">';
			echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons_Manager markup in a fixed wrapper.
			if ( '' !== trim( $label ) ) {
				echo '<span class="wpec-pi-meta__label">' . esc_html( $label ) . '</span> ';
			}
			echo '<span class="wpec-pi-meta__value' . ( '' !== $modifier ? ' wpec-pi-meta__value--' . esc_attr( $modifier ) : '' ) . '"' . $attrs . '>' . $value . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- both escaped by the caller.
			echo '</span>';
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
			WP_EasyCart_Product_Info::view_events( $product );

			$rows = array();
			if ( WP_EasyCart_Product_Info::on( $settings, 'show_sku' ) && '' !== trim( (string) $product->model_number ) ) {
				$sku    = (string) $product->model_number;
				$rows[] = array(
					'sku',
					WP_EasyCart_Product_Info::label( $settings, 'sku_label', WP_EasyCart_Product_Info::text( 'meta_sku', __( 'SKU:', 'wp-easycart' ) ) ),
					esc_html( $sku ),
					' data-wpec-pi-sku="' . esc_attr( (int) $product->product_id ) . '" data-default="' . esc_attr( $sku ) . '"',
				);
			}
			if ( WP_EasyCart_Product_Info::on( $settings, 'show_categories' ) ) {
				$categories = WP_EasyCart_Product_Info::categories( $product );
				if ( $categories ) {
					$links = array();
					foreach ( $categories as $category ) {
						$links[] = '<a href="' . esc_url( $category['url'] ) . '">' . esc_html( $category['name'] ) . '</a>';
					}
					$default = ( 1 === count( $categories ) )
						? WP_EasyCart_Product_Info::text( 'meta_category', __( 'Category:', 'wp-easycart' ) )
						: WP_EasyCart_Product_Info::text( 'product_details_categories', __( 'Categories:', 'wp-easycart' ), 'product_details' );
					$divider = isset( $settings['list_separator'] ) ? (string) $settings['list_separator'] : ', ';
					$rows[]  = array( 'categories', WP_EasyCart_Product_Info::label( $settings, 'categories_label', $default ), implode( '<span class="wpec-pi-meta__divider">' . esc_html( $divider ) . '</span>', $links ) );
				}
			}
			if ( WP_EasyCart_Product_Info::on( $settings, 'show_brand' ) && ! empty( $product->manufacturer_id ) && '' !== trim( (string) $product->manufacturer_name ) ) {
				$brand  = WP_EasyCart_Product_Info::convert( $product->manufacturer_name );
				$logo   = ( isset( $settings['brand_display'] ) && 'logo' === $settings['brand_display'] ) ? WP_EasyCart_Product_Info::brand_logo( $product ) : '';
				$rows[] = array( 'brand', WP_EasyCart_Product_Info::label( $settings, 'brand_label', WP_EasyCart_Product_Info::text( 'meta_brand', __( 'Brand:', 'wp-easycart' ) ) ), '<a href="' . esc_url( $product->get_manufacturer_link() ) . '">' . ( '' !== $logo ? wp_kses_post( $logo ) : esc_html( $brand ) ) . '</a>' );
			}
			if ( WP_EasyCart_Product_Info::on( $settings, 'show_tag' ) && ! empty( $product->tag_type ) && '' !== trim( (string) $product->tag_text ) ) {
				$rows[] = array( 'tag', WP_EasyCart_Product_Info::label( $settings, 'tag_label', WP_EasyCart_Product_Info::text( 'meta_tag', __( 'Tag:', 'wp-easycart' ) ) ), esc_html( WP_EasyCart_Product_Info::convert( $product->tag_text ) ) );
			}
			if ( WP_EasyCart_Product_Info::on( $settings, 'show_stock' ) ) {
				$stock  = WP_EasyCart_Product_Info::stock_status( $product );
				$rows[] = array( 'stock', WP_EasyCart_Product_Info::label( $settings, 'stock_label', WP_EasyCart_Product_Info::text( 'meta_stock', __( 'Availability:', 'wp-easycart' ) ) ), esc_html( $stock['text'] ), ' data-wpec-pi-stock="' . esc_attr( (int) $product->product_id ) . '" data-default="' . esc_attr( $stock['text'] ) . '"', $stock['status'] );
			}
			if ( WP_EasyCart_Product_Info::on( $settings, 'show_weight' ) && '' !== WP_EasyCart_Product_Info::weight_text( $product ) ) {
				$rows[] = array( 'weight', WP_EasyCart_Product_Info::label( $settings, 'weight_label', WP_EasyCart_Product_Info::text( 'meta_weight', __( 'Weight:', 'wp-easycart' ) ) ), esc_html( WP_EasyCart_Product_Info::weight_text( $product ) ) );
			}
			if ( WP_EasyCart_Product_Info::on( $settings, 'show_dimensions' ) && '' !== WP_EasyCart_Product_Info::dimensions_text( $product ) ) {
				$rows[] = array( 'dimensions', WP_EasyCart_Product_Info::label( $settings, 'dimensions_label', WP_EasyCart_Product_Info::text( 'meta_dimensions', __( 'Dimensions:', 'wp-easycart' ) ) ), esc_html( WP_EasyCart_Product_Info::dimensions_text( $product ) ) );
			}
			if ( ! $rows ) {
				$this->ec_editor_notice( __( 'No details to show here.', 'wp-easycart' ), __( 'Turn on a detail under Content › Details ( SKU, categories, brand, tag, stock, weight, dimensions ). Visitors see nothing; the widget still tells your analytics the product was viewed.', 'wp-easycart' ) );
				return;
			}
			$layout  = ( isset( $settings['layout'] ) && 'inline' === $settings['layout'] ) ? 'inline' : 'stacked';
			$divider = ( 'inline' === $layout && isset( $settings['inline_divider'] ) ) ? trim( (string) $settings['inline_divider'] ) : '';
			echo '<div class="wpec-el wpec-pi-meta wpec-pi-meta--' . esc_attr( $layout ) . ( '' !== $divider ? ' wpec-pi-meta--divided' : '' ) . '">';
			foreach ( $rows as $index => $row ) {
				if ( $index > 0 && '' !== $divider ) {
					echo '<span class="wpec-pi-meta__sep" aria-hidden="true">' . esc_html( $divider ) . '</span>';
				}
				$this->print_row( $row[0], $row[1], $row[2], isset( $row[3] ) ? $row[3] : '', $settings, isset( $row[4] ) ? $row[4] : '' );
			}
			echo '</div>';
		}
	}

endif;
