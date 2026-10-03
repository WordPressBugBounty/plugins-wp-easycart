<?php
/**
 * Product Title widget ( wp_easycart_product_title, 6.0.2 ).
 *
 * The product's name in the heading tag the merchant picks, optionally linked to the product page ( for product lists and
 * landing pages ). Replaces wp_easycart_product_details_title.
 *
 * Round 11: the link can open in a new tab, and a long name can stop after a number of lines.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Title_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Product Title.
	 */
	class WP_EasyCart_Elementor_Product_Info_Title_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-title';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_title';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Title', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-title';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'product', 'title', 'name', 'heading', 'product title' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			$this->start_controls_section(
				'section_title',
				array(
					'label' => __( 'Title', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'title_tag',
				array(
					'label'   => __( 'HTML tag', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'h1',
					'options' => array(
						'h1'   => 'H1',
						'h2'   => 'H2',
						'h3'   => 'H3',
						'h4'   => 'H4',
						'h5'   => 'H5',
						'h6'   => 'H6',
						'div'  => 'div',
						'span' => 'span',
						'p'    => 'p',
					),
				)
			);
			$this->add_control(
				'link_to',
				array(
					'label'       => __( 'Link', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'options'     => array(
						''        => __( 'None', 'wp-easycart' ),
						'product' => __( 'Product page', 'wp-easycart' ),
					),
					'description' => __( 'Link the title to its product page when it sits in a product list or on another page.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'link_new_tab',
				array(
					'label'     => __( 'Open in a new tab', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => '',
					'condition' => array( 'link_to' => 'product' ),
				)
			);
			$this->add_responsive_control(
				'line_clamp',
				array(
					'label'       => __( 'Most lines shown', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 1,
					'max'         => 10,
					'step'        => 1,
					'selectors'   => array( '{{WRAPPER}} .wpec-pi-title' => 'display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: {{VALUE}}; line-clamp: {{VALUE}}; overflow: hidden;' ),
					'description' => __( 'A longer name ends with “…”. Leave empty to show it all.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_title_style',
				array(
					'label' => __( 'Title', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-title', 'text', 'align', true );
			$this->pi_text_controls(
				'title',
				'{{WRAPPER}} .wpec-pi-title, {{WRAPPER}} .wpec-pi-title a',
				'primary',
				array( 'hover' => '{{WRAPPER}} .wpec-pi-title a:hover, {{WRAPPER}} .wpec-pi-title a:focus' )
			);
			$this->add_group_control(
				\Elementor\Group_Control_Text_Shadow::get_type(),
				array(
					'name'     => 'title_text_shadow',
					'selector' => '{{WRAPPER}} .wpec-pi-title',
				)
			);
			$this->end_controls_section();
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
			$tag = ( isset( $settings['title_tag'] ) && in_array( $settings['title_tag'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ) ? $settings['title_tag'] : 'h1';
			echo '<' . tag_escape( $tag ) . ' class="wpec-el wpec-pi-title">';
			if ( isset( $settings['link_to'] ) && 'product' === $settings['link_to'] ) {
				echo '<a href="' . esc_url( $product->get_product_link() ) . '"' . ( WP_EasyCart_Product_Info::on( $settings, 'link_new_tab' ) ? ' target="_blank" rel="noopener"' : '' ) . '>';
			}
			echo wp_easycart_escape_html( $product->title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the product name through the store's HTML filter, as on the product page.
			if ( isset( $settings['link_to'] ) && 'product' === $settings['link_to'] ) {
				echo '</a>';
			}
			echo '</' . tag_escape( $tag ) . '>';
		}
	}

endif;
