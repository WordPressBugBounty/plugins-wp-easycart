<?php
/**
 * Product Specifications widget ( wp_easycart_product_specifications, 6.0.2 ).
 *
 * The product's specifications ( additional information ). With "Follow each product's settings" on ( the default ) it shows
 * only on products whose Specifications switch is on, as the product page does; the older widget ignored that switch.
 * Replaces wp_easycart_product_details_specifications.
 *
 * Round 11: header cells ( and, on request, the first column ) as labels with their own background, type, colour and width,
 * the table's border width, cell colours; the alternate row colour now covers header cells in the row too.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Specifications_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Product Specifications.
	 */
	class WP_EasyCart_Elementor_Product_Info_Specifications_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-specifications';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_specifications';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Specifications', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-info';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'product', 'specifications', 'specs', 'additional information', 'attributes', 'details' );
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			$this->start_controls_section(
				'section_specifications',
				array(
					'label' => __( 'Specifications', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->pi_follow_control( __( 'Show the specifications only on products whose Specifications switch is on in the product editor.', 'wp-easycart' ) );
			$this->add_control(
				'first_column_header',
				array(
					'label'       => __( 'First column as labels', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Styles a table’s first column like its header cells ( Style › Tables › Labels ).', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_text_style',
				array(
					'label' => __( 'Text', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-specifications', 'text', 'align', true );
			$this->pi_text_controls( 'text', '{{WRAPPER}} .wpec-pi-specifications' );
			$this->add_control(
				'link_color',
				array(
					'label'     => __( 'Link color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-specifications a' => 'color: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_table_style',
				array(
					'label' => __( 'Tables', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_note( 'table_note', esc_html__( 'For specifications written as a table.', 'wp-easycart' ) );
			$this->add_control(
				'table_border_color',
				array(
					'label'     => __( 'Border color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-specifications' => '--wpec-pi-table-border: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'table_stripe_color',
				array(
					'label'     => __( 'Alternate row color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-specifications' => '--wpec-pi-table-stripe: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'table_cell_padding',
				array(
					'label'      => __( 'Cell padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-specifications td, {{WRAPPER}} .wpec-pi-specifications th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'table_border_width',
				array(
					'label'      => __( 'Border width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 10,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-specifications' => '--wpec-pi-table-border-w: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'table_text_color',
				array(
					'label'     => __( 'Cell text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-specifications td' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'table_background',
				array(
					'label'     => __( 'Cell background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-specifications' => '--wpec-pi-table-bg: {{VALUE}};' ),
				)
			);
			/* Heavier than the stripe rule ( .wpec-pi-specifications tr:nth-child( even ) > th ), so a label keeps its background. */
			$header = '{{WRAPPER}} .wpec-pi-specifications table tr > th, {{WRAPPER}} .wpec-pi-specifications--first-col table tr > td:first-child';
			$this->add_control(
				'header_heading',
				array(
					'label'     => __( 'Labels ( header cells )', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'header_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $header => 'background-color: {{VALUE}};' ),
				)
			);
			$this->pi_text_controls( 'header', $header, 'primary', array( 'global' => false ) );
			$this->add_responsive_control(
				'header_width',
				array(
					'label'      => __( 'Label column width', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( '%', 'px' ),
					'range'      => array(
						'%'  => array(
							'min' => 10,
							'max' => 80,
						),
						'px' => array(
							'min' => 60,
							'max' => 500,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-specifications tr > th:first-child, {{WRAPPER}} .wpec-pi-specifications--first-col tr > td:first-child' => 'width: {{SIZE}}{{UNIT}};' ),
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
			if ( ! $this->pi_allowed( $settings, $product, 'use_specifications' ) ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( 'Specifications are off for %s.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see nothing here. Turn on Specifications in the product editor, or turn off “Follow each product’s settings”.', 'wp-easycart' ) );
				return;
			}
			$html = WP_EasyCart_Product_Info::specifications_html( $product );
			if ( '' === $html ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( '%s has no specifications yet.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see nothing here until you add them in the product editor.', 'wp-easycart' ) );
				return;
			}
			$first = WP_EasyCart_Product_Info::on( $settings, 'first_column_header' ) ? ' wpec-pi-specifications--first-col' : '';
			echo '<div class="wpec-el wpec-pi-specifications' . esc_attr( $first ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the specifications through the store's HTML filter ( specifications_html() ).
		}
	}

endif;
