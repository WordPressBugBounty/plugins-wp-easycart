<?php
/**
 * Subcategories widget ( wp_easycart_subcategories, 6.0.2 ): a grid of categories with image, name and product count.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-templates-widget.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates_Subcategories_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Templates_Widget' ) ) :

	/**
	 * Category grid.
	 */
	class WP_EasyCart_Elementor_Templates_Subcategories_Widget extends WP_EasyCart_Elementor_Templates_Widget {

		/**
		 * Name ( stored in pages ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_subcategories';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Subcategories', 'wp-easycart' );
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
			return array( 'subcategories', 'product categories', 'categories', 'category grid', 'shop by category', 'collections' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->start_controls_section(
				'ec_categories_section',
				array(
					'label' => __( 'Categories', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_show',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'children',
					'options' => array(
						'children' => __( 'Subcategories of the page\'s category', 'wp-easycart' ),
						'top'      => __( 'Top-level categories', 'wp-easycart' ),
						'featured' => __( 'Featured categories', 'wp-easycart' ),
						'pick'     => __( 'Categories I choose', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_category_ids',
				array(
					'label'       => __( 'Categories', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_cat',
					'label_block' => true,
					'multiple'    => true,
					'condition'   => array( 'ec_show' => 'pick' ),
				)
			);
			$this->add_control(
				'ec_show_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'On a category page this lists the categories inside it. Visitors see nothing when there are none.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_show' => 'children' ),
				)
			);
			$this->add_control(
				'ec_limit',
				array(
					'label'   => __( 'Most categories', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::NUMBER,
					'default' => 12,
					'min'     => 1,
					'max'     => 100,
				)
			);
			$this->add_responsive_control(
				'ec_columns',
				array(
					'label'          => __( 'Columns', 'wp-easycart' ),
					'type'           => \Elementor\Controls_Manager::SELECT,
					'default'        => '4',
					'tablet_default' => '3',
					'mobile_default' => '2',
					'options'        => array(
						'1' => '1',
						'2' => '2',
						'3' => '3',
						'4' => '4',
						'5' => '5',
						'6' => '6',
					),
					'selectors'      => array(
						'{{WRAPPER}} .wpec-el-subcategories' => 'grid-template-columns: repeat( {{VALUE}}, minmax( 0, 1fr ) );',
					),
					'separator'      => 'before',
				)
			);
			$this->add_control(
				'ec_show_image',
				array(
					'label'        => __( 'Image', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
				)
			);
			$this->add_control(
				'ec_image_ratio',
				array(
					'label'     => __( 'Image shape', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '1 / 1',
					'options'   => array(
						'1 / 1'  => __( 'Square', 'wp-easycart' ),
						'4 / 3'  => __( 'Landscape ( 4:3 )', 'wp-easycart' ),
						'16 / 9' => __( 'Wide ( 16:9 )', 'wp-easycart' ),
						'3 / 4'  => __( 'Portrait ( 3:4 )', 'wp-easycart' ),
					),
					'condition' => array( 'ec_show_image' => 'yes' ),
					'selectors' => array(
						'{{WRAPPER}} .wpec-el-subcategory-media' => 'aspect-ratio: {{VALUE}};',
					),
				)
			);
			$this->add_control(
				'ec_image_size',
				array(
					'label'     => __( 'Image size', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'medium',
					'options'   => $this->ec_image_sizes(),
					'condition' => array( 'ec_show_image' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_show_count',
				array(
					'label'        => __( 'Number of products', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
				)
			);
			$this->add_control(
				'ec_show_description',
				array(
					'label'        => __( 'Description', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => '',
					'return_value' => 'yes',
				)
			);
			$this->end_controls_section();

			$this->ec_register_card_style();
			$this->start_controls_section(
				'ec_image_style',
				array(
					'label'     => __( 'Image', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'ec_show_image' => 'yes' ),
				)
			);
			$this->add_control(
				'ec_image_fit',
				array(
					'label'     => __( 'Fit', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'cover',
					'options'   => array(
						'cover'   => __( 'Fill the box ( crop )', 'wp-easycart' ),
						'contain' => __( 'Fit inside the box', 'wp-easycart' ),
					),
					'selectors' => array(
						'{{WRAPPER}} .wpec-el-subcategory-media img' => 'object-fit: {{VALUE}};',
					),
				)
			);
			$this->add_responsive_control(
				'ec_image_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-el-subcategory-media' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'ec_image_spacing',
				array(
					'label'      => __( 'Space below', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-el-subcategory-media' => 'margin-bottom: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->end_controls_section();

			$this->ec_text_style_section( 'ec_name_style', __( 'Name', 'wp-easycart' ), '.wpec-el-subcategory-name', $this->ec_global_color( 'primary' ), $this->ec_global_typography( 'primary' ) );
			$this->ec_text_style_section( 'ec_count_style', __( 'Number of products', 'wp-easycart' ), '.wpec-el-subcategory-count', '', $this->ec_global_typography( 'text' ) );
			$this->ec_text_style_section( 'ec_description_style', __( 'Description', 'wp-easycart' ), '.wpec-el-subcategory-description', $this->ec_global_color( 'text' ), $this->ec_global_typography( 'text' ) );
		}

		/**
		 * Style › Card ( grid gap, alignment, background, border, radius, padding, shadow; normal / hover ).
		 */
		private function ec_register_card_style() {
			$this->start_controls_section(
				'ec_card_style',
				array(
					'label' => __( 'Card', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_responsive_control(
				'ec_gap',
				array(
					'label'      => __( 'Space between', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-el-subcategories' => 'gap: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->ec_align_control( '.wpec-el-subcategory' );
			$this->add_responsive_control(
				'ec_card_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-el-subcategory' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'ec_card_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-el-subcategory' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->start_controls_tabs( 'ec_card_tabs' );
			foreach ( array(
				'normal' => array( __( 'Normal', 'wp-easycart' ), '.wpec-el-subcategory' ),
				'hover'  => array( __( 'Hover', 'wp-easycart' ), '.wpec-el-subcategory:hover, {{WRAPPER}} .wpec-el-subcategory:focus-within' ),
			) as $state => $tab ) {
				$this->start_controls_tab(
					'ec_card_tab_' . $state,
					array( 'label' => $tab[0] )
				);
				$this->add_control(
					'ec_card_bg_' . $state,
					array(
						'label'     => __( 'Background color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array(
							'{{WRAPPER}} ' . $tab[1] => 'background-color: {{VALUE}};',
						),
					)
				);
				$this->add_group_control(
					\Elementor\Group_Control_Border::get_type(),
					array(
						'name'     => 'ec_card_border_' . $state,
						'selector' => '{{WRAPPER}} ' . $tab[1],
					)
				);
				$this->add_group_control(
					\Elementor\Group_Control_Box_Shadow::get_type(),
					array(
						'name'     => 'ec_card_shadow_' . $state,
						'selector' => '{{WRAPPER}} ' . $tab[1],
					)
				);
				$this->end_controls_tab();
			}
			$this->end_controls_tabs();
			$this->end_controls_section();
		}

		/**
		 * Categories to list, with product counts.
		 *
		 * @param array $settings Widget settings.
		 * @param bool  $sample   Out: true when the editor shows sample categories instead of empty children.
		 * @return array Rows ( category_id, category_name, short_description, image, post_id, product_count ).
		 */
		private function ec_rows( $settings, &$sample ) {
			global $wpdb;
			$sample = false;
			$show   = isset( $settings['ec_show'] ) ? (string) $settings['ec_show'] : 'children';
			$limit  = isset( $settings['ec_limit'] ) ? max( 1, min( 100, (int) $settings['ec_limit'] ) ) : 12;

			if ( 'pick' === $show ) {
				$ids = array();
				foreach ( (array) ( isset( $settings['ec_category_ids'] ) ? $settings['ec_category_ids'] : array() ) as $id ) {
					$id = (int) $id;
					if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
						$ids[] = $id;
					}
				}
				if ( empty( $ids ) ) {
					return array();
				}
				$ids = array_slice( $ids, 0, $limit );
				$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $in is one %d placeholder per id; every value goes through prepare().
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT c.category_id, c.category_name, c.short_description, c.image, c.post_id, ( SELECT COUNT( DISTINCT ci.product_id ) FROM ec_categoryitem ci INNER JOIN ec_product p ON p.product_id = ci.product_id WHERE ci.category_id = c.category_id AND p.activate_in_store = 1 ) AS product_count FROM ec_category c WHERE c.is_active = 1 AND c.category_id IN ( ' . $in . ' ) ORDER BY FIELD( c.category_id, ' . $in . ' )',
						array_merge( $ids, $ids )
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				return is_array( $rows ) ? $rows : array();
			}
			if ( 'featured' === $show ) {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT c.category_id, c.category_name, c.short_description, c.image, c.post_id, ( SELECT COUNT( DISTINCT ci.product_id ) FROM ec_categoryitem ci INNER JOIN ec_product p ON p.product_id = ci.product_id WHERE ci.category_id = c.category_id AND p.activate_in_store = 1 ) AS product_count FROM ec_category c WHERE c.is_active = 1 AND c.featured_category = 1 ORDER BY c.priority DESC, c.category_name ASC LIMIT %d', $limit ) );
				return is_array( $rows ) ? $rows : array();
			}
			$parent = 0;
			if ( 'children' === $show ) {
				$category = wp_easycart_elementor_context()->category();
				if ( ! $category ) {
					return array();
				}
				$parent = (int) $category->category_id;
			}
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT c.category_id, c.category_name, c.short_description, c.image, c.post_id, ( SELECT COUNT( DISTINCT ci.product_id ) FROM ec_categoryitem ci INNER JOIN ec_product p ON p.product_id = ci.product_id WHERE ci.category_id = c.category_id AND p.activate_in_store = 1 ) AS product_count FROM ec_category c WHERE c.is_active = 1 AND c.parent_id = %d ORDER BY c.priority DESC, c.category_name ASC LIMIT %d', $parent, $limit ) );
			if ( empty( $rows ) && 'children' === $show && $this->ec_is_editor() ) {
				// In the editor a category without subcategories shows your top-level categories, so the design is not empty.
				$sample = true;
				$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT c.category_id, c.category_name, c.short_description, c.image, c.post_id, ( SELECT COUNT( DISTINCT ci.product_id ) FROM ec_categoryitem ci INNER JOIN ec_product p ON p.product_id = ci.product_id WHERE ci.category_id = c.category_id AND p.activate_in_store = 1 ) AS product_count FROM ec_category c WHERE c.is_active = 1 AND c.parent_id = 0 ORDER BY c.priority DESC, c.category_name ASC LIMIT %d', $limit ) );
			}
			return is_array( $rows ) ? $rows : array();
		}

		/**
		 * Product count wording.
		 *
		 * @param int $count Products.
		 * @return string
		 */
		private function ec_count_text( $count ) {
			$key      = ( 1 === (int) $count ) ? 'category_product_count_one' : 'category_product_count';
			$fallback = ( 1 === (int) $count ) ? '1 product' : '[count] products';
			$text     = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( 'elementor_templates', $key ) : '';
			if ( '' === $text ) {
				$text = $fallback;
			}
			return str_replace( '[count]', number_format_i18n( (int) $count ), $text );
		}

		/**
		 * Output.
		 */
		protected function render() {
			if ( $this->ec_store_closed() ) {
				return;
			}
			$settings = $this->get_settings_for_display();
			$sample   = false;
			$rows     = $this->ec_rows( $settings, $sample );
			if ( empty( $rows ) ) {
				$this->ec_editor_notice( __( 'No categories to show', 'wp-easycart' ), __( 'On a category page with subcategories they show here. Choose "Top-level categories" or "Categories I choose" to list others.', 'wp-easycart' ) );
				return;
			}
			if ( $sample ) {
				$this->ec_editor_notice( __( 'This category has no subcategories', 'wp-easycart' ), __( 'Your top-level categories are shown as a sample. Visitors see nothing here on categories without subcategories.', 'wp-easycart' ) );
			}
			$image = ( isset( $settings['ec_show_image'] ) && 'yes' === $settings['ec_show_image'] );
			$count = ( isset( $settings['ec_show_count'] ) && 'yes' === $settings['ec_show_count'] );
			$desc  = ( isset( $settings['ec_show_description'] ) && 'yes' === $settings['ec_show_description'] );
			$size  = $this->ec_image_size( $settings, 'medium' );
			$label = function_exists( 'wp_easycart_language' ) ? (string) wp_easycart_language()->get_text( 'elementor_templates', 'categories_label' ) : '';
			if ( '' === $label ) {
				$label = 'Categories';
			}
			echo '<ul class="wpec-el wpec-el-subcategories" aria-label="' . esc_attr( $label ) . '">';
			foreach ( $rows as $row ) {
				$name = $this->ec_text( $row->category_name );
				$link = wp_easycart_elementor_templates_link( 'category', $row );
				echo '<li class="wpec-el-subcategory">';
				echo '<a class="wpec-el-subcategory-link" href="' . esc_url( $link ) . '">';
				if ( $image ) {
					$html = $this->ec_image_html( 0, wp_easycart_elementor_templates_category_image_url( $row->image ), $size, '', 'wpec-el-subcategory-img' );
					echo '<span class="wpec-el-subcategory-media' . ( '' === $html ? ' is-empty' : '' ) . '" aria-hidden="true">' . $html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ec_image_html(): wp_get_attachment_image() output, or an img built with esc_url() / esc_attr().
				}
				echo '<span class="wpec-el-subcategory-name">' . esc_html( $name ) . '</span>';
				if ( $count ) {
					echo '<span class="wpec-el-subcategory-count">' . esc_html( $this->ec_count_text( (int) $row->product_count ) ) . '</span>';
				}
				echo '</a>';
				if ( $desc ) {
					$text = trim( $this->ec_text( $row->short_description ) );
					if ( '' !== $text ) {
						echo '<div class="wpec-el-subcategory-description">' . wp_kses_post( wpautop( function_exists( 'wp_easycart_escape_html' ) ? wp_easycart_escape_html( $text ) : $text ) ) . '</div>';
					}
				}
				echo '</li>';
			}
			echo '</ul>';
		}
	}

endif;
