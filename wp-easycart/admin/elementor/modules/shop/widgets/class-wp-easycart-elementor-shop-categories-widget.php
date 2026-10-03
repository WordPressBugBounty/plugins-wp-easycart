<?php
/**
 * Product Categories ( wp_easycart_categories, 6.0.2 ): a grid of category tiles ( image, name, product count ).
 *
 * All from the category list EasyCart already loads on every request ( $GLOBALS['ec_categories'] ); one query counts the
 * products of the tiles shown.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-shop-listing-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Shop_Categories_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Shop_Listing_Widget' ) ) :

	/**
	 * Category grid.
	 */
	class WP_EasyCart_Elementor_Shop_Categories_Widget extends WP_EasyCart_Elementor_Shop_Listing_Widget {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-categories';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_categories';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Categories', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-categories';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'categories', 'product categories', 'category grid', 'collections', 'departments', 'subcategories', 'woocommerce' );
		}

		/**
		 * No script of its own: only the base's.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return WP_EasyCart_Elementor_Widget_Base::get_script_depends();
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->start_controls_section(
				'ec_section_categories',
				array(
					'label' => __( 'Categories', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_cat_source',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'top',
					'options' => array(
						'top'      => __( 'Top categories', 'wp-easycart' ),
						'featured' => __( 'Featured categories', 'wp-easycart' ),
						'children' => __( 'Subcategories of the page’s category', 'wp-easycart' ),
						'pick'     => __( 'Categories I choose', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_cat_ids',
				array(
					'label'       => __( 'Categories', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_cat',
					'label_block' => true,
					'multiple'    => true,
					'description' => __( 'They show in the order you add them.', 'wp-easycart' ),
					'condition'   => array( 'ec_cat_source' => 'pick' ),
				)
			);
			$this->add_control(
				'ec_cat_children_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'On a category page or category template this shows its subcategories. Anywhere else it shows the top categories.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_cat_source' => 'children' ),
				)
			);
			$this->add_control(
				'ec_cat_featured_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					/* translators: %s: link to the categories screen. */
					'raw'             => sprintf( esc_html__( 'Mark categories as featured in %s.', 'wp-easycart' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=category' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Products › Categories', 'wp-easycart' ) . '</a>' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_cat_source' => 'featured' ),
				)
			);
			$this->add_control(
				'ec_cat_orderby',
				array(
					'label'     => __( 'Order', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'store',
					'options'   => array(
						'store' => __( 'Store order', 'wp-easycart' ),
						'name'  => __( 'Name, A to Z', 'wp-easycart' ),
					),
					'condition' => array( 'ec_cat_source!' => 'pick' ),
				)
			);
			$this->add_control(
				'ec_cat_limit',
				array(
					'label'       => __( 'Most categories', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => '',
					'min'         => 1,
					'max'         => 100,
					'description' => __( 'Leave empty to show them all.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'ec_cat_hide_empty',
				array(
					'label'   => __( 'Leave out categories without products', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_section_layout',
				array(
					'label' => __( 'Layout', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->ec_register_columns_controls();
			$this->ec_register_row_gap_control();
			$this->add_control(
				'ec_cat_show_image',
				array(
					'label'     => __( 'Image', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'separator' => 'before',
				)
			);
			$this->add_control(
				'ec_cat_name_position',
				array(
					'label'     => __( 'Name', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'below',
					'options'   => array(
						'below' => __( 'Under the image', 'wp-easycart' ),
						'over'  => __( 'On the image', 'wp-easycart' ),
					),
					'condition' => array( 'ec_cat_show_image' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_cat_title_tag',
				array(
					'label'   => __( 'Name HTML tag', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'h3',
					'options' => array(
						'h2'  => 'H2',
						'h3'  => 'H3',
						'h4'  => 'H4',
						'p'   => 'p',
						'div' => 'div',
					),
				)
			);
			$this->add_control(
				'ec_cat_show_count',
				array(
					'label'   => __( 'Number of products', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'ec_cat_show_desc',
				array(
					'label'   => __( 'Short description', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => '',
				)
			);
			$this->add_responsive_control(
				'ec_align',
				array(
					'label'                => __( 'Alignment', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array(
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
					),
					'selectors_dictionary' => array(
						'left'   => 'start',
						'center' => 'center',
						'right'  => 'end',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-products' => '--wpec-card-align: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();

			$this->ec_register_category_style_controls();
		}

		/**
		 * Style sections.
		 */
		protected function ec_register_category_style_controls() {
			$style = \Elementor\Controls_Manager::TAB_STYLE;
			$this->start_controls_section(
				'ec_style_tile',
				array(
					'label' => __( 'Tile', 'wp-easycart' ),
					'tab'   => $style,
				)
			);
			$this->add_control(
				'tile_background',
				array(
					'label'     => __( 'Background colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-category' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'tile_border',
					'selector' => '{{WRAPPER}} .wpec-category',
				)
			);
			$this->add_responsive_control(
				'tile_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-category' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'tile_padding',
				array(
					'label'      => __( 'Text padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-category__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'tile_shadow',
					'selector' => '{{WRAPPER}} .wpec-category',
				)
			);
			$this->add_control(
				'tile_image_ratio',
				array(
					'label'     => __( 'Image shape', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '1 / 1',
					'options'   => array(
						'1 / 1'  => __( 'Square', 'wp-easycart' ),
						'4 / 5'  => __( 'Portrait 4:5', 'wp-easycart' ),
						'3 / 4'  => __( 'Portrait 3:4', 'wp-easycart' ),
						'4 / 3'  => __( 'Landscape 4:3', 'wp-easycart' ),
						'16 / 9' => __( 'Wide 16:9', 'wp-easycart' ),
					),
					'selectors' => array( '{{WRAPPER}}' => '--wpec-image-ratio: {{VALUE}};' ),
					'separator' => 'before',
					'condition' => array( 'ec_cat_show_image' => 'yes' ),
				)
			);
			$this->add_control(
				'tile_overlay',
				array(
					'label'     => __( 'Shade behind the name', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}}' => '--wpec-category-overlay: {{VALUE}};' ),
					'condition' => array(
						'ec_cat_show_image'    => 'yes',
						'ec_cat_name_position' => 'over',
					),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'ec_style_category_name',
				array(
					'label' => __( 'Name', 'wp-easycart' ),
					'tab'   => $style,
				)
			);
			$this->ec_typography( 'category_name_typography', '{{WRAPPER}} .wpec-category__name', 'primary' );
			$this->add_control(
				'category_name_color',
				array(
					'label'     => __( 'Colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-category__name, {{WRAPPER}} .wpec-category__name a' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'category_name_hover_color',
				array(
					'label'     => __( 'Hover colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-category:hover .wpec-category__name a, {{WRAPPER}} .wpec-category__name a:focus-visible' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'category_count_color',
				array(
					'label'     => __( 'Product count colour', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-category__count, {{WRAPPER}} .wpec-category__desc' => 'color: {{VALUE}};' ),
					'separator' => 'before',
				)
			);
			$this->ec_typography( 'category_count_typography', '{{WRAPPER}} .wpec-category__count', 'text' );
			$this->end_controls_section();
		}

		/**
		 * The category rows to show.
		 *
		 * @param array $settings Widget settings.
		 * @return array
		 */
		protected function ec_categories( $settings ) {
			if ( empty( $GLOBALS['ec_categories'] ) || ! is_object( $GLOBALS['ec_categories'] ) ) {
				return array();
			}
			$all    = (array) $GLOBALS['ec_categories']->all_categories;
			$source = isset( $settings['ec_cat_source'] ) ? $settings['ec_cat_source'] : 'top';
			$rows   = array();
			if ( 'pick' === $source ) {
				foreach ( WP_EasyCart_Elementor_Shop_Query::ids( isset( $settings['ec_cat_ids'] ) ? $settings['ec_cat_ids'] : array() ) as $category_id ) {
					$row = $GLOBALS['ec_categories']->get_category( $category_id );
					if ( $row ) {
						$rows[] = $row;
					}
				}
				return $rows;
			}
			$parent = 0;
			if ( 'children' === $source && function_exists( 'wp_easycart_elementor_context' ) ) {
				$current = wp_easycart_elementor_context()->category( array( 'allow_sample' => $this->ec_is_editor() ) );
				if ( $current && isset( $current->category_id ) ) {
					$parent = (int) $current->category_id;
				}
			}
			foreach ( $all as $row ) {
				if ( 'featured' === $source ) {
					if ( ! empty( $row->featured_category ) ) {
						$rows[] = $row;
					}
				} elseif ( (int) $row->parent_id === $parent && (int) $row->category_id !== $parent ) {
					$rows[] = $row;
				}
			}
			if ( isset( $settings['ec_cat_orderby'] ) && 'name' === $settings['ec_cat_orderby'] ) {
				usort(
					$rows,
					function ( $a, $b ) {
						return strcasecmp( WP_EasyCart_Elementor_Shop::category_name( $a ), WP_EasyCart_Elementor_Shop::category_name( $b ) );
					}
				);
			}
			return $rows;
		}

		/**
		 * Draws the widget.
		 */
		protected function render() {
			if ( WP_EasyCart_Elementor_Shop::restricted() ) {
				return;
			}
			WP_EasyCart_Elementor_Shop_Query::role_no_cache(); /* Cacheable, unless the counts differ per customer role. */
			WP_EasyCart_Elementor_Shop::pixel_base_code();
			$settings = $this->get_settings_for_display();
			$rows     = $this->ec_categories( $settings );
			$counts   = WP_EasyCart_Elementor_Shop::category_counts( wp_list_pluck( $rows, 'category_id' ) );
			$hide     = ( ! isset( $settings['ec_cat_hide_empty'] ) || 'yes' === $settings['ec_cat_hide_empty'] );
			$limit    = WP_EasyCart_Elementor_Shop_Query::clamp( isset( $settings['ec_cat_limit'] ) ? $settings['ec_cat_limit'] : '', 1, 100, 0 );
			$tiles    = array();
			foreach ( $rows as $row ) {
				$count = isset( $counts[ (int) $row->category_id ] ) ? $counts[ (int) $row->category_id ] : 0;
				if ( $hide && ! $count && ! WP_EasyCart_Elementor_Shop::category_has_children( (int) $row->category_id ) ) {
					continue;
				}
				$tiles[] = array( $row, $count );
				if ( $limit && count( $tiles ) >= $limit ) {
					break;
				}
			}
			if ( empty( $tiles ) ) {
				$this->ec_editor_notice( __( 'No categories to show with these settings.', 'wp-easycart' ), __( 'Visitors see nothing here. Add categories with products, or choose another option under Content › Categories.', 'wp-easycart' ) );
				return;
			}

			$image = ( ! isset( $settings['ec_cat_show_image'] ) || 'yes' === $settings['ec_cat_show_image'] );
			$over  = $image && isset( $settings['ec_cat_name_position'] ) && 'over' === $settings['ec_cat_name_position'];
			$count = ( ! isset( $settings['ec_cat_show_count'] ) || 'yes' === $settings['ec_cat_show_count'] );
			$desc  = isset( $settings['ec_cat_show_desc'] ) && 'yes' === $settings['ec_cat_show_desc'];
			$tag   = ( isset( $settings['ec_cat_title_tag'] ) && in_array( $settings['ec_cat_title_tag'], array( 'h2', 'h3', 'h4', 'p', 'div' ), true ) ) ? $settings['ec_cat_title_tag'] : 'h3';
			$one   = WP_EasyCart_Elementor_Shop::text( 'shop_category_count_one', '[count] product' );
			$many  = WP_EasyCart_Elementor_Shop::text( 'shop_category_count_many', '[count] products' );

			echo '<div class="wpec-el wpec-products wpec-categories' . ( $over ? ' wpec-categories--over' : '' ) . '"><ul class="wpec-products__grid" role="list">';
			foreach ( $tiles as $tile ) {
				list( $row, $products ) = $tile;
				$name                   = WP_EasyCart_Elementor_Shop::category_name( $row );
				$url                    = WP_EasyCart_Elementor_Shop::category_url( $row );
				echo '<li class="wpec-category">';
				if ( $image ) {
					$src = ( '' !== trim( (string) $row->image ) && class_exists( 'ec_category' ) ) ? ( new ec_category( $row ) )->get_image() : '';
					if ( '' !== $src ) {
						echo '<div class="wpec-category__media"><img src="' . esc_url( $src ) . '" alt="" loading="lazy" decoding="async" /></div>';
					} else {
						echo '<div class="wpec-category__media wpec-category__media--empty" aria-hidden="true"></div>';
					}
				}
				echo '<div class="wpec-category__body">';
				echo '<' . $tag . ' class="wpec-category__name"><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is one of a fixed list.
				if ( $count && $products ) {
					echo '<p class="wpec-category__count">' . esc_html( WP_EasyCart_Elementor_Shop::fill( ( 1 === $products ) ? $one : $many, array( 'count' => $products ) ) ) . '</p>';
				}
				if ( $desc && '' !== trim( (string) $row->short_description ) ) {
					echo '<p class="wpec-category__desc">' . esc_html( wp_strip_all_tags( html_entity_decode( wp_easycart_language()->convert_text( $row->short_description ), ENT_QUOTES, 'UTF-8' ) ) ) . '</p>';
				}
				echo '</div></li>';
			}
			echo '</ul></div>';
		}
	}

endif;
