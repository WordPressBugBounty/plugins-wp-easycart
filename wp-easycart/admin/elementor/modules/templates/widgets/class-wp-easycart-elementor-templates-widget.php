<?php
/**
 * Base of the category and manufacturer widgets ( 6.0.2, templates module ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Shared controls and lookups.
	 */
	abstract class WP_EasyCart_Elementor_Templates_Widget extends WP_EasyCart_Elementor_Widget_Base {

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'shop';

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'category-and-manufacturer-widgets';

		/**
		 * The widgets' stylesheet.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_values( array_unique( array_merge( (array) parent::get_style_depends(), array( WP_EasyCart_Elementor_Templates::STYLE_HANDLE ) ) ) );
		}

		/**
		 * "Category" section: this page's category or a chosen one.
		 */
		protected function ec_register_category_controls() {
			$this->start_controls_section(
				'ec_category_section',
				array(
					'label' => __( 'Category', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_category_source',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'current',
					'options' => array(
						'current' => __( 'The category of the page it is on', 'wp-easycart' ),
						'pick'    => __( 'A category I choose', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_category_id',
				array(
					'label'       => __( 'Category', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_cat',
					'label_block' => true,
					'multiple'    => false,
					'condition'   => array( 'ec_category_source' => 'pick' ),
				)
			);
			$this->add_control(
				'ec_category_source_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'On category pages and category templates this shows that category, and on product pages the product\'s first category. While you edit anything else, one of your categories is shown as a sample.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_category_source' => 'current' ),
				)
			);
			$this->ec_register_content_controls();
			$this->end_controls_section();
		}

		/**
		 * "Manufacturer" section: this page's manufacturer or a chosen one.
		 */
		protected function ec_register_manufacturer_controls() {
			$this->start_controls_section(
				'ec_manufacturer_section',
				array(
					'label' => __( 'Manufacturer', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'ec_manufacturer_source',
				array(
					'label'   => __( 'Show', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'current',
					'options' => array(
						'current' => __( 'The manufacturer of the page it is on', 'wp-easycart' ),
						'pick'    => __( 'A manufacturer I choose', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'ec_manufacturer_id',
				array(
					'label'       => __( 'Manufacturer', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_brand',
					'label_block' => true,
					'multiple'    => false,
					'condition'   => array( 'ec_manufacturer_source' => 'pick' ),
				)
			);
			$this->add_control(
				'ec_manufacturer_source_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'On a manufacturer page ( an Elementor Pro Theme Builder template with the WP EasyCart manufacturer condition ) this shows that manufacturer, and on product pages the product\'s manufacturer. While you edit anything else, a sample is shown.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'ec_manufacturer_source' => 'current' ),
				)
			);
			$this->ec_register_content_controls();
			$this->end_controls_section();
		}

		/**
		 * Widget-specific content controls, inside the Category / Manufacturer section.
		 */
		protected function ec_register_content_controls() {}

		/**
		 * Whether the store is closed to this visitor ( Settings › Products › Who can view the store ): the widget then
		 * prints nothing ( the editor gets the base class's note ).
		 *
		 * @return bool
		 */
		protected function ec_store_closed() {
			if ( ! function_exists( 'wp_easycart_store_is_restricted' ) || ! wp_easycart_store_is_restricted() ) {
				return false;
			}
			if ( method_exists( $this, 'ec_no_product_notice' ) ) {
				$this->ec_no_product_notice();
			}
			return true;
		}

		/**
		 * An id from a picker setting ( may be an array ).
		 *
		 * @param mixed $value Setting.
		 * @return int
		 */
		protected function ec_id( $value ) {
			if ( is_array( $value ) ) {
				$value = reset( $value );
			}
			return (int) $value;
		}

		/**
		 * The category for these settings ( ec_category row ), or null.
		 *
		 * @param array $settings Widget settings.
		 * @return object|null
		 */
		protected function ec_category_row( $settings ) {
			$pick    = ( isset( $settings['ec_category_source'] ) && 'pick' === $settings['ec_category_source'] );
			$context = wp_easycart_elementor_context();
			$args    = array(
				'source'      => $pick ? 'pick' : 'current',
				'category_id' => $pick && isset( $settings['ec_category_id'] ) ? $this->ec_id( $settings['ec_category_id'] ) : 0,
			);
			if ( $pick ) {
				return $context->category( $args );
			}
			$row = $context->category( array_merge( $args, array( 'allow_sample' => false ) ) );
			if ( ! $row ) {
				$row = $this->ec_product_category_row(); /* On a product page: the product's first category, as the manufacturer widgets use its manufacturer ( 6.0.2 ). */
			}
			return $row ? $row : $context->category( $args );
		}

		/**
		 * The page product's first active category ( highest priority, then name ), or null.
		 *
		 * @since 6.0.2
		 * @return object|null
		 */
		protected function ec_product_category_row() {
			global $wpdb;
			$product = wp_easycart_elementor_context()->product(
				array(
					'allow_sample' => false,
					'details'      => false,
				)
			);
			if ( ! $product || empty( $product->product_id ) ) {
				return null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_category.* FROM ec_categoryitem INNER JOIN ec_category ON ec_category.category_id = ec_categoryitem.category_id WHERE ec_categoryitem.product_id = %d AND ec_category.is_active = 1 ORDER BY ec_category.priority DESC, ec_category.category_name ASC LIMIT 1', (int) $product->product_id ) );
			return $row ? $row : null;
		}

		/**
		 * The manufacturer for these settings ( ec_manufacturer row ), or null.
		 *
		 * @param array $settings Widget settings.
		 * @return object|null
		 */
		protected function ec_manufacturer_row( $settings ) {
			$pick = ( isset( $settings['ec_manufacturer_source'] ) && 'pick' === $settings['ec_manufacturer_source'] );
			return wp_easycart_elementor_templates_manufacturer(
				array(
					'source'          => $pick ? 'pick' : 'current',
					'manufacturer_id' => $pick && isset( $settings['ec_manufacturer_id'] ) ? $this->ec_id( $settings['ec_manufacturer_id'] ) : 0,
				)
			);
		}

		/**
		 * A stored name as the shopper reads it ( language tags resolved ).
		 *
		 * @param string $text Stored text.
		 * @return string
		 */
		protected function ec_text( $text ) {
			$text = wp_unslash( (string) $text );
			if ( function_exists( 'wp_easycart_language' ) ) {
				$text = (string) wp_easycart_language()->convert_text( $text );
			}
			return $text;
		}

		/**
		 * Heading tag options.
		 *
		 * @return array
		 */
		protected function ec_tag_options() {
			return array(
				'h1'   => 'H1',
				'h2'   => 'H2',
				'h3'   => 'H3',
				'h4'   => 'H4',
				'h5'   => 'H5',
				'h6'   => 'H6',
				'div'  => 'div',
				'span' => 'span',
				'p'    => 'p',
			);
		}

		/**
		 * A heading tag from the settings ( h1 when unknown ).
		 *
		 * @param array  $settings Widget settings.
		 * @param string $key      Setting.
		 * @return string
		 */
		protected function ec_tag( $settings, $key = 'ec_tag' ) {
			$tag = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : 'h1';
			return array_key_exists( $tag, $this->ec_tag_options() ) ? $tag : 'h1';
		}

		/**
		 * Alignment control ( responsive ) for a selector.
		 *
		 * @param string $selector Element selector inside {{WRAPPER}}.
		 * @param bool   $justify  Offer "justified".
		 */
		protected function ec_align_control( $selector, $justify = false ) {
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
			$this->add_responsive_control(
				'ec_align',
				array(
					'label'     => __( 'Alignment', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::CHOOSE,
					'options'   => $options,
					'selectors' => array(
						'{{WRAPPER}} ' . $selector => 'text-align: {{VALUE}};',
					),
				)
			);
		}

		/**
		 * Style section "Text" with colour, typography ( from the kit ) and text shadow.
		 *
		 * @param string $section_id Section id.
		 * @param string $label      Section label.
		 * @param string $selector   Element selector inside {{WRAPPER}}.
		 * @param string $color      Global colour key.
		 * @param string $typography Global typography key.
		 * @param bool   $link_hover Add a hover colour for a link.
		 */
		protected function ec_text_style_section( $section_id, $label, $selector, $color, $typography, $link_hover = false ) {
			$this->start_controls_section(
				$section_id,
				array(
					'label' => $label,
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$color_args = array(
				'label'     => __( 'Text color', 'wp-easycart' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} ' . $selector        => 'color: {{VALUE}};',
					'{{WRAPPER}} ' . $selector . ' a' => 'color: {{VALUE}};',
				),
			);
			if ( '' !== $color ) {
				$color_args['global'] = array( 'default' => $color );
			}
			$this->add_control( $section_id . '_color', $color_args );
			if ( $link_hover ) {
				$this->add_control(
					$section_id . '_hover_color',
					array(
						'label'     => __( 'Link hover color', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array(
							'{{WRAPPER}} ' . $selector . ' a:hover, {{WRAPPER}} ' . $selector . ' a:focus' => 'color: {{VALUE}};',
						),
					)
				);
			}
			$typography_args = array(
				'name'     => $section_id . '_typography',
				'selector' => '{{WRAPPER}} ' . $selector,
			);
			if ( '' !== $typography ) {
				$typography_args['global'] = array( 'default' => $typography );
			}
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), $typography_args );
			$this->add_group_control(
				\Elementor\Group_Control_Text_Shadow::get_type(),
				array(
					'name'     => $section_id . '_shadow',
					'selector' => '{{WRAPPER}} ' . $selector,
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Global colour key ( the kit's ), '' on an Elementor without globals.
		 *
		 * @param string $name primary | secondary | text | accent.
		 * @return string
		 */
		protected function ec_global_color( $name ) {
			$class = '\Elementor\Core\Kits\Documents\Tabs\Global_Colors';
			if ( ! class_exists( $class ) ) {
				return '';
			}
			$map = array(
				'primary'   => 'COLOR_PRIMARY',
				'secondary' => 'COLOR_SECONDARY',
				'text'      => 'COLOR_TEXT',
				'accent'    => 'COLOR_ACCENT',
			);
			return isset( $map[ $name ] ) ? constant( $class . '::' . $map[ $name ] ) : '';
		}

		/**
		 * Global typography key ( the kit's ), '' on an Elementor without globals.
		 *
		 * @param string $name primary | secondary | text | accent.
		 * @return string
		 */
		protected function ec_global_typography( $name ) {
			$class = '\Elementor\Core\Kits\Documents\Tabs\Global_Typography';
			if ( ! class_exists( $class ) ) {
				return '';
			}
			$map = array(
				'primary'   => 'TYPOGRAPHY_PRIMARY',
				'secondary' => 'TYPOGRAPHY_SECONDARY',
				'text'      => 'TYPOGRAPHY_TEXT',
				'accent'    => 'TYPOGRAPHY_ACCENT',
			);
			return isset( $map[ $name ] ) ? constant( $class . '::' . $map[ $name ] ) : '';
		}

		/**
		 * An image from an attachment id ( srcset, lazy ) or a plain address.
		 *
		 * @param int    $attachment_id Attachment id ( 0 = none ).
		 * @param string $url           Address when there is no attachment.
		 * @param string $size          Image size.
		 * @param string $alt           Alternative text.
		 * @param string $class_name    Class.
		 * @return string
		 */
		protected function ec_image_html( $attachment_id, $url, $size, $alt, $class_name ) {
			if ( ! $attachment_id && '' !== $url && function_exists( 'attachment_url_to_postid' ) ) {
				$attachment_id = (int) attachment_url_to_postid( $url );
			}
			if ( $attachment_id ) {
				$html = wp_get_attachment_image(
					$attachment_id,
					$size,
					false,
					array(
						'class'    => $class_name,
						'alt'      => $alt,
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
				if ( '' !== $html ) {
					return $html;
				}
			}
			if ( '' === $url ) {
				return '';
			}
			return '<img class="' . esc_attr( $class_name ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" />';
		}

		/**
		 * Image size control options.
		 *
		 * @return array
		 */
		protected function ec_image_sizes() {
			return array(
				'thumbnail'    => __( 'Thumbnail', 'wp-easycart' ),
				'medium'       => __( 'Medium', 'wp-easycart' ),
				'medium_large' => __( 'Medium large', 'wp-easycart' ),
				'large'        => __( 'Large', 'wp-easycart' ),
				'full'         => __( 'Full size', 'wp-easycart' ),
			);
		}

		/**
		 * An image size from the settings.
		 *
		 * @param array  $settings Widget settings.
		 * @param string $fallback Default.
		 * @return string
		 */
		protected function ec_image_size( $settings, $fallback = 'large' ) {
			$size = isset( $settings['ec_image_size'] ) ? (string) $settings['ec_image_size'] : $fallback;
			return array_key_exists( $size, $this->ec_image_sizes() ) ? $size : $fallback;
		}

		/**
		 * Style section for an image ( width, max width, height, fit, border, radius, shadow ).
		 *
		 * @param string $selector Image selector inside {{WRAPPER}}.
		 */
		protected function ec_image_style_section( $selector ) {
			$this->start_controls_section(
				'ec_image_style',
				array(
					'label' => __( 'Image', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_responsive_control(
				'ec_image_width',
				array(
					'label'      => __( 'Width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( '%', 'px', 'vw' ),
					'range'      => array(
						'%'  => array(
							'min' => 1,
							'max' => 100,
						),
						'px' => array(
							'min' => 1,
							'max' => 1600,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} ' . $selector => 'width: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'ec_image_max_width',
				array(
					'label'      => __( 'Max width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( '%', 'px', 'vw' ),
					'selectors'  => array(
						'{{WRAPPER}} ' . $selector => 'max-width: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'ec_image_height',
				array(
					'label'      => __( 'Height', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'vh' ),
					'range'      => array(
						'px' => array(
							'min' => 1,
							'max' => 1000,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}} ' . $selector => 'height: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'ec_image_fit',
				array(
					'label'     => __( 'Fit', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => array(
						''        => __( 'Default', 'wp-easycart' ),
						'cover'   => __( 'Fill the box ( crop )', 'wp-easycart' ),
						'contain' => __( 'Fit inside the box', 'wp-easycart' ),
					),
					'condition' => array( 'ec_image_height[size]!' => '' ),
					'selectors' => array(
						'{{WRAPPER}} ' . $selector => 'object-fit: {{VALUE}};',
					),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'ec_image_border',
					'selector' => '{{WRAPPER}} ' . $selector,
				)
			);
			$this->add_responsive_control(
				'ec_image_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} ' . $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'ec_image_shadow',
					'selector' => '{{WRAPPER}} ' . $selector,
				)
			);
			$this->end_controls_section();
		}
	}

endif;
