<?php
/**
 * Product Rating widget ( wp_easycart_product_rating, 6.0.2 ).
 *
 * The product's exact average as stars ( 3.4 fills three stars and 40% of the fourth; the older widget rounded up ), the
 * number of reviews and a link that goes to the reviews on the page ( opening their tab ). Hidden while the product has no
 * reviews unless "Show when there are no reviews" is on, and always on products whose customer reviews are off. Replaces
 * wp_easycart_product_details_rating.
 *
 * Round 11: solid, outlined or icon stars ( any icon, filled to the exact rating ), the number of reviews as "(3 reviews)",
 * "3 reviews", "(3)" or "3" in the merchant's own words, the average as 4.3 or 4.3/5, the "No reviews yet" text, and separate
 * type and colour for the average and the count.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Rating_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Product Rating.
	 */
	class WP_EasyCart_Elementor_Product_Info_Rating_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-rating';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_rating';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Rating', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-rating';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'product', 'rating', 'stars', 'reviews', 'star rating', 'review stars' );
		}

		/**
		 * Script: the link to the reviews.
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
				'section_rating',
				array(
					'label' => __( 'Rating', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_count',
				array(
					'label'   => __( 'Number of reviews', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'show_average',
				array(
					'label'   => __( 'Average as a number', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => '',
				)
			);
			$this->add_control(
				'link_to_reviews',
				array(
					'label'       => __( 'Go to the reviews when clicked', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Scrolls to the Product Reviews or Product Tabs widget on the page and opens its reviews.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'count_format',
				array(
					'label'     => __( 'Number of reviews as', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'paren_text',
					'options'   => array(
						'paren_text'   => __( '(3 reviews)', 'wp-easycart' ),
						'text'         => __( '3 reviews', 'wp-easycart' ),
						'paren_number' => __( '(3)', 'wp-easycart' ),
						'number'       => __( '3', 'wp-easycart' ),
					),
					'condition' => array( 'show_count' => 'yes' ),
				)
			);
			$this->add_control(
				'count_label',
				array(
					'label'       => __( 'Words', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( '[count] reviews', 'wp-easycart' ),
					'description' => __( '[count] is the number. Leave empty for your store’s wording ( “1 review”, “3 reviews” ).', 'wp-easycart' ),
					'condition'   => array(
						'show_count'   => 'yes',
						'count_format' => array( 'paren_text', 'text' ),
					),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'average_format',
				array(
					'label'     => __( 'Average as', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'plain',
					'options'   => array(
						'plain'  => __( '4.3', 'wp-easycart' ),
						'out_of' => __( '4.3/5', 'wp-easycart' ),
					),
					'condition' => array( 'show_average' => 'yes' ),
				)
			);
			$this->add_control(
				'show_empty',
				array(
					'label'       => __( 'Show when there are no reviews', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Shows empty stars and “No reviews yet”. Off, the rating stays hidden until the first review.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'empty_text',
				array(
					'label'       => __( 'Text when there are no reviews', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Product_Info::text( 'rating_none', __( 'No reviews yet', 'wp-easycart' ) ),
					'condition'   => array( 'show_empty' => 'yes' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'star_style',
				array(
					'label'     => __( 'Stars', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'filled',
					'separator' => 'before',
					'options'   => array(
						'filled'  => __( 'Solid stars', 'wp-easycart' ),
						'outline' => __( 'Outlined empty stars', 'wp-easycart' ),
						'icon'    => __( 'An icon I choose', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'star_icon',
				array(
					'label'     => __( 'Icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'default'   => array(
						'value'   => 'fas fa-star',
						'library' => 'fa-solid',
					),
					'condition' => array( 'star_style' => 'icon' ),
				)
			);
			$this->pi_note(
				'rating_note',
				esc_html__( 'Hidden on products whose customer reviews are off ( product editor ). Store-wide review choices:', 'wp-easycart' ) . ' ' . $this->pi_settings_link( 'products', __( 'Settings › Products', 'wp-easycart' ) )
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_stars_style',
				array(
					'label' => __( 'Stars', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-rating', 'flex' );
			$this->add_control(
				'star_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-rating' => '--wpec-pi-star: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'star_empty_color',
				array(
					'label'     => __( 'Empty star color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-rating' => '--wpec-pi-star-empty: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'star_size',
				array(
					'label'      => __( 'Size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'range'      => array(
						'px' => array(
							'min' => 8,
							'max' => 64,
						),
						'em' => array(
							'min'  => 0.5,
							'max'  => 4,
							'step' => 0.1,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-rating' => '--wpec-pi-star-size: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'star_gap',
				array(
					'label'      => __( 'Space between stars', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 20,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-rating' => '--wpec-pi-star-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_rating_text_style',
				array(
					'label' => __( 'Text', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pi_text_controls(
				'text',
				'{{WRAPPER}} .wpec-pi-rating__text',
				'text',
				array( 'hover' => '{{WRAPPER}} a.wpec-pi-rating__inner:hover .wpec-pi-rating__text, {{WRAPPER}} a.wpec-pi-rating__inner:focus .wpec-pi-rating__text' )
			);
			$this->add_responsive_control(
				'text_gap',
				array(
					'label'      => __( 'Space before the text', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-rating' => '--wpec-pi-rating-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'average_heading',
				array(
					'label'     => __( 'Average', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'show_average' => 'yes' ),
				)
			);
			$this->add_control(
				'average_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-rating .wpec-pi-rating__average' => 'color: {{VALUE}};' ),
					'condition' => array( 'show_average' => 'yes' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'      => 'average_typography',
					'selector'  => '{{WRAPPER}} .wpec-pi-rating .wpec-pi-rating__average',
					'condition' => array( 'show_average' => 'yes' ),
				)
			);
			$this->add_control(
				'count_heading',
				array(
					'label'     => __( 'Number of reviews', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'count_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-rating .wpec-pi-rating__count' => 'color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'count_typography',
					'selector' => '{{WRAPPER}} .wpec-pi-rating .wpec-pi-rating__count',
				)
			);
			$this->end_controls_section();
		}

		/**
		 * The number of reviews in the chosen format.
		 *
		 * @param array $settings Settings.
		 * @param int   $count    Reviews.
		 * @return string Plain text.
		 */
		private function count_text( $settings, $count ) {
			$format = ( isset( $settings['count_format'] ) && in_array( $settings['count_format'], array( 'paren_text', 'text', 'paren_number', 'number' ), true ) ) ? $settings['count_format'] : 'paren_text';
			if ( 'number' === $format || 'paren_number' === $format ) {
				$text = number_format_i18n( $count );
			} else {
				$label = isset( $settings['count_label'] ) ? trim( (string) $settings['count_label'] ) : '';
				$text  = ( '' !== $label ) ? str_replace( '[count]', number_format_i18n( $count ), $label ) : WP_EasyCart_Product_Info::review_count( $count );
			}
			return ( 0 === strpos( $format, 'paren_' ) ) ? '(' . $text . ')' : $text;
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
			if ( empty( $product->use_customer_reviews ) ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( 'Customer reviews are off for %s.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see no rating. Turn on Customer reviews in the product editor to show it.', 'wp-easycart' ) );
				return;
			}
			$rating = WP_EasyCart_Product_Info::rating( $product );
			$empty  = ( 0 === $rating['count'] );
			if ( $empty && ! WP_EasyCart_Product_Info::on( $settings, 'show_empty' ) ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( '%s has no reviews yet.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see the rating once the first review is approved. Turn on “Show when there are no reviews” to show empty stars now.', 'wp-easycart' ) );
				return;
			}

			$texts = array();
			if ( ! $empty && WP_EasyCart_Product_Info::on( $settings, 'show_average' ) ) {
				$average = number_format_i18n( $rating['average'], 1 ) . ( ( isset( $settings['average_format'] ) && 'out_of' === $settings['average_format'] ) ? '/' . number_format_i18n( 5 ) : '' );
				$texts[] = '<span class="wpec-pi-rating__average">' . esc_html( $average ) . '</span>';
			}
			if ( $empty ) {
				$texts[] = '<span class="wpec-pi-rating__count">' . esc_html( WP_EasyCart_Product_Info::label( $settings, 'empty_text', WP_EasyCart_Product_Info::text( 'rating_none', __( 'No reviews yet', 'wp-easycart' ) ) ) ) . '</span>';
			} elseif ( WP_EasyCart_Product_Info::on( $settings, 'show_count' ) ) {
				$texts[] = '<span class="wpec-pi-rating__count">' . esc_html( $this->count_text( $settings, (int) $rating['count'] ) ) . '</span>';
			}
			$style = ( isset( $settings['star_style'] ) && in_array( $settings['star_style'], array( 'outline', 'icon' ), true ) ) ? $settings['star_style'] : 'filled';
			$icon  = ( 'icon' === $style ) ? WP_EasyCart_Product_Info::icon_html( isset( $settings['star_icon'] ) ? $settings['star_icon'] : null ) : '';
			$star  = array(
				'style' => ( 'icon' === $style && '' === $icon ) ? 'filled' : $style,
				'icon'  => $icon,
			);

			$link = WP_EasyCart_Product_Info::on( $settings, 'link_to_reviews' );
			echo '<div class="wpec-el wpec-pi-rating' . ( $empty ? ' wpec-pi-rating--empty' : '' ) . '">';
			if ( $link ) {
				/* The product's own reviews: on its page the script opens them in place; elsewhere ( loops, landing pages ) the link goes there. */
				echo '<a class="wpec-pi-rating__inner" href="' . esc_url( $product->get_product_link() . '#reviews' ) . '" data-wpec-pi-reviews-link="' . esc_attr( (int) $product->product_id ) . '">';
			} else {
				echo '<span class="wpec-pi-rating__inner">';
			}
			/* No reviews: the stars are decorative ( "No reviews yet" says it ), never "Rated 0 out of 5". */
			echo WP_EasyCart_Product_Info::stars( $rating['average'], '', $empty, $star ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built with esc_attr() in stars(); the icon is Icons_Manager markup.
			if ( $texts ) {
				echo '<span class="wpec-pi-rating__text">' . implode( ' ', $texts ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
			}
			echo $link ? '</a>' : '</span>';
			echo '</div>';
		}
	}

endif;
