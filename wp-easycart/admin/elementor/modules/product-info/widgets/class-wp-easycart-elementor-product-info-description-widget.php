<?php
/**
 * Product Description widget ( wp_easycart_product_description, 6.0.2 ).
 *
 * The product's full description as the product page shows it. Prints nothing when the product has none. Replaces
 * wp_easycart_product_details_description.
 *
 * Round 11: "Shorten long text" folds the text to a height the merchant picks behind an accessible Read more button ( folded
 * by CSS from the start, so nothing jumps as the page loads; without the script the whole text shows ), heading, paragraph and
 * list spacing and type, and box controls ( background, padding, border, corners ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Description_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Product Description.
	 */
	class WP_EasyCart_Elementor_Product_Info_Description_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-description';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_description';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Description', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-description';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'product', 'description', 'content', 'product content', 'long description', 'text' );
		}

		/**
		 * Script: the Read more button.
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
			$this->register_content_controls();
			$this->register_text_style( 'wpec-pi-description' );
		}

		/**
		 * Content tab: Read more ( round 11 ).
		 *
		 * @param bool $words Offer a word limit ( short description ).
		 */
		protected function register_content_controls( $words = false ) {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => __( 'Text', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			if ( $words ) {
				$this->add_control(
					'word_limit',
					array(
						'label'       => __( 'Most words shown', 'wp-easycart' ),
						'type'        => \Elementor\Controls_Manager::NUMBER,
						'default'     => 0,
						'min'         => 0,
						'max'         => 500,
						'description' => __( 'Longer text ends with “…” as plain text. 0 shows it all.', 'wp-easycart' ),
					)
				);
			}
			$this->add_control(
				'collapse',
				array(
					'label'       => __( 'Shorten long text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Text taller than the height below folds away behind a “Read more” button.', 'wp-easycart' ),
				)
			);
			$this->add_responsive_control(
				'collapse_height',
				array(
					'label'      => __( 'Height before “Read more”', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 40,
							'max' => 1000,
						),
						'em' => array(
							'min' => 2,
							'max' => 60,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-collapse' => '--wpec-pi-collapse-h: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'collapse' => 'yes' ),
				)
			);
			$this->add_control(
				'more_text',
				array(
					'label'       => __( 'Button text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Product_Info::text( 'read_more', __( 'Read more', 'wp-easycart' ) ),
					'condition'   => array( 'collapse' => 'yes' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'less_text',
				array(
					'label'       => __( 'Button text while open', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Product_Info::text( 'read_less', __( 'Show less', 'wp-easycart' ) ),
					'condition'   => array( 'collapse' => 'yes' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'collapse_fade',
				array(
					'label'     => __( 'Fade the last line', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'condition' => array( 'collapse' => 'yes' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Selectors for every heading level inside the text.
		 *
		 * @param string $css_class Content class.
		 * @return string
		 */
		private function headings_selector( $css_class ) {
			$out = array();
			foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) {
				$out[] = '{{WRAPPER}} .' . $css_class . ' ' . $tag;
			}
			return implode( ', ', $out );
		}

		/**
		 * Style tab: text and links, headings, paragraphs and lists, the box, the Read more button.
		 *
		 * @param string $css_class Content class.
		 */
		protected function register_text_style( $css_class ) {
			$this->start_controls_section(
				'section_text_style',
				array(
					'label' => __( 'Text', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .' . $css_class, 'text', 'align', true );
			$this->pi_text_controls( 'text', '{{WRAPPER}} .' . $css_class );
			$this->add_control(
				'link_color',
				array(
					'label'     => __( 'Link color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => 'before',
					'selectors' => array( '{{WRAPPER}} .' . $css_class . ' a' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'link_hover_color',
				array(
					'label'     => __( 'Link hover color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .' . $css_class . ' a:hover, {{WRAPPER}} .' . $css_class . ' a:focus' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'paragraph_spacing',
				array(
					'label'      => __( 'Space after paragraphs', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'separator'  => 'before',
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 60,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .' . $css_class . ' p' => 'margin-block-end: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'headings_heading',
				array(
					'label'     => __( 'Headings in the text', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'headings', $this->headings_selector( $css_class ), 'primary', array( 'global' => false ) );
			$this->add_responsive_control(
				'headings_spacing',
				array(
					'label'      => __( 'Space below headings', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 60,
						),
					),
					'selectors'  => array( $this->headings_selector( $css_class ) => 'margin-block-end: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'lists_heading',
				array(
					'label'     => __( 'Lists in the text', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_responsive_control(
				'list_indent',
				array(
					'label'      => __( 'Indent', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 80,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .' . $css_class . ' ul, {{WRAPPER}} .' . $css_class . ' ol' => 'padding-inline-start: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'list_item_spacing',
				array(
					'label'      => __( 'Space between items', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .' . $css_class . ' li' => 'margin-block-end: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'list_typography',
					'selector' => '{{WRAPPER}} .' . $css_class . ' li',
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_box_style',
				array(
					'label' => __( 'Box', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_box_controls( 'box', '{{WRAPPER}} .' . $css_class, array( 'shadow' => true ) );
			$this->end_controls_section();

			$this->start_controls_section(
				'section_collapse_style',
				array(
					'label'     => __( 'Read more button', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'collapse' => 'yes' ),
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-collapse__actions', 'text', 'collapse_align' );
			$this->pi_text_controls(
				'collapse_button',
				'{{WRAPPER}} .wpec-pi-collapse .wpec-pi-collapse__toggle',
				'accent',
				array(
					'global' => false,
					'hover'  => '{{WRAPPER}} .wpec-pi-collapse .wpec-pi-collapse__toggle:hover, {{WRAPPER}} .wpec-pi-collapse .wpec-pi-collapse__toggle:focus-visible',
				)
			);
			$this->add_control(
				'collapse_fade_color',
				array(
					'label'       => __( 'Fade to', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'description' => __( 'The background behind the text, so the last line fades into it.', 'wp-easycart' ),
					'selectors'   => array( '{{WRAPPER}} .wpec-pi-collapse' => '--wpec-pi-collapse-fade: {{VALUE}};' ),
					'condition'   => array( 'collapse_fade' => 'yes' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * The text, folded behind Read more when the merchant asks for it.
		 *
		 * @param array  $settings  Settings.
		 * @param string $css_class Content class.
		 * @param string $html      Text ( escaped by the caller ).
		 */
		protected function print_text( $settings, $css_class, $html ) {
			if ( ! WP_EasyCart_Product_Info::on( $settings, 'collapse' ) ) {
				echo '<div class="wpec-el ' . esc_attr( $css_class ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller ( the store's HTML filter ).
				return;
			}
			$id   = 'wpec-pi-collapse-' . WP_EasyCart_Product_Info::rand();
			$more = WP_EasyCart_Product_Info::label( $settings, 'more_text', WP_EasyCart_Product_Info::text( 'read_more', __( 'Read more', 'wp-easycart' ) ) );
			$less = WP_EasyCart_Product_Info::label( $settings, 'less_text', WP_EasyCart_Product_Info::text( 'read_less', __( 'Show less', 'wp-easycart' ) ) );
			$fade = ( ! isset( $settings['collapse_fade'] ) || WP_EasyCart_Product_Info::on( $settings, 'collapse_fade' ) );
			echo '<div class="wpec-el ' . esc_attr( $css_class ) . ' wpec-pi-collapse is-collapsed' . ( $fade ? ' wpec-pi-collapse--fade' : '' ) . '" id="' . esc_attr( $id ) . '" data-wpec-pi-collapse="1" data-more="' . esc_attr( $more ) . '" data-less="' . esc_attr( $less ) . '">';
			echo '<div class="wpec-pi-collapse__content" id="' . esc_attr( $id ) . '-content">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller ( the store's HTML filter ).
			echo '<div class="wpec-pi-collapse__actions"><button type="button" class="wpec-pi-collapse__toggle" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '-content">' . esc_html( $more ) . '</button></div>';
			/* Without the script the whole text shows and the button stays out of the way. */
			echo '<noscript><style>#' . esc_attr( $id ) . ' .wpec-pi-collapse__content{max-height:none!important;overflow:visible!important}#' . esc_attr( $id ) . ' .wpec-pi-collapse__content::after,#' . esc_attr( $id ) . ' .wpec-pi-collapse__actions{display:none!important}</style></noscript>';
			echo '</div>';
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
			$html = WP_EasyCart_Product_Info::description_html( $product );
			if ( '' === $html ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( '%s has no description yet.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see nothing here until you add one in the product editor.', 'wp-easycart' ) );
				return;
			}
			$this->print_text( $settings, 'wpec-pi-description', $html );
		}
	}

endif;
