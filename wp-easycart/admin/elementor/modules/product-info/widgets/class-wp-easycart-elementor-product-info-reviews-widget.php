<?php
/**
 * Product Reviews widget ( wp_easycart_product_reviews, 6.0.2 ).
 *
 * Everything the product page's reviews tab has, which the older Customer Reviews widget lacked: the rating summary with a
 * filter by stars, the verified buyer badge, the store's replies, the review-request banner with its pre-filled star and the
 * "link expired / already reviewed" note; then the reviews and the review form ( sent through ec_submit_product_review() and
 * the store's own review request ). With "Follow each product's settings" on ( the default ) it shows only on products whose
 * customer reviews are on. Replaces wp_easycart_product_details_customer_reviews.
 *
 * Round 11: the order ( newest, oldest, highest or lowest rated ), "Reviews shown at first" with a "Show more reviews" link
 * ( the script pages in place; without it the link reloads with every review, ?wpec_pi_reviews=all ), reviewer pictures
 * ( Gravatar, only where reviewer names show ), the form hidden from shoppers who are not signed in, and styling for the
 * summary box, its texts and bars, the form's stars, fields, focus ring and messages, the button's border and shadow, the
 * verified badge, the store's replies and the "Show all reviews" link. Field and button selectors outweigh the module's
 * own rules ( .wpec-pi-review-form .wpec-pi-review-form__input ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-easycart-elementor-product-info-widget-base.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Info_Reviews_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Product_Info_Widget_Base' ) ) :

	/**
	 * Product Reviews.
	 */
	class WP_EasyCart_Elementor_Product_Info_Reviews_Widget extends WP_EasyCart_Elementor_Product_Info_Widget_Base {

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-reviews';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_reviews';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Reviews', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-review';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'product', 'reviews', 'customer reviews', 'rating', 'review form', 'testimonials', 'stars' );
		}

		/**
		 * Script: star picker, filter, review-request landing.
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
				'section_reviews',
				array(
					'label' => __( 'Reviews', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_summary',
				array(
					'label'       => __( 'Rating summary', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'The average and how many reviews gave each star; shoppers click a row to see only those reviews.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'show_list',
				array(
					'label'   => __( 'Reviews', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'show_form',
				array(
					'label'   => __( 'Review form', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_responsive_control(
				'layout',
				array(
					'label'                => __( 'Reviews and form', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => 'side',
					'tablet_default'       => 'stacked',
					'mobile_default'       => 'stacked',
					'options'              => array(
						'side'    => __( 'Side by side', 'wp-easycart' ),
						'stacked' => __( 'Form below the reviews', 'wp-easycart' ),
					),
					'selectors_dictionary' => array(
						'side'    => '--wpec-pi-reviews-cols: minmax(0, 3fr) minmax(0, 2fr);',
						'stacked' => '--wpec-pi-reviews-cols: minmax(0, 1fr);',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-pi-reviews' => '{{VALUE}}' ),
					'condition'            => array(
						'show_list' => 'yes',
						'show_form' => 'yes',
					),
				)
			);
			$this->pi_follow_control( __( 'Show reviews only on products whose Customer reviews switch is on in the product editor.', 'wp-easycart' ) );
			$this->add_control(
				'sort',
				array(
					'label'     => __( 'Order', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'newest',
					'separator' => 'before',
					'options'   => array(
						'newest'  => __( 'Newest first', 'wp-easycart' ),
						'oldest'  => __( 'Oldest first', 'wp-easycart' ),
						'highest' => __( 'Highest rated first', 'wp-easycart' ),
						'lowest'  => __( 'Lowest rated first', 'wp-easycart' ),
					),
					'condition' => array( 'show_list' => 'yes' ),
				)
			);
			$this->add_control(
				'per_page',
				array(
					'label'       => __( 'Reviews shown at first', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => 0,
					'min'         => 0,
					'max'         => 100,
					'description' => __( 'The rest wait behind a “Show more reviews” button. 0 shows every review.', 'wp-easycart' ),
					'condition'   => array( 'show_list' => 'yes' ),
				)
			);
			$this->add_control(
				'more_text',
				array(
					'label'       => __( 'Show more text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Product_Info::text( 'reviews_more', __( 'Show more reviews', 'wp-easycart' ) ),
					'condition'   => array( 'show_list' => 'yes' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->pi_note(
				'reviews_note',
				esc_html__( 'Who may review, reviewer names and review requests:', 'wp-easycart' ) . ' ' . $this->pi_settings_link( 'products', __( 'Settings › Products › Reviews', 'wp-easycart' ) )
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_review_details',
				array(
					'label'     => __( 'Each review', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
					'condition' => array( 'show_list' => 'yes' ),
				)
			);
			$this->add_control(
				'show_list_heading',
				array(
					'label'   => __( 'Heading ( “3 Reviews for …” )', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'show_rating',
				array(
					'label'   => __( 'Stars', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'show_review_title',
				array(
					'label'   => __( 'Review title', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'show_text',
				array(
					'label'   => __( 'Review text', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'show_date',
				array(
					'label'   => __( 'Date', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'show_name',
				array(
					'label'   => __( 'Reviewer name', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'store',
					'options' => array(
						'store' => __( 'As in Settings › Products', 'wp-easycart' ),
						'show'  => __( 'Show', 'wp-easycart' ),
						'hide'  => __( 'Hide', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'show_verified',
				array(
					'label'       => __( 'Verified buyer badge', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'On reviews from shoppers who bought the product.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'show_replies',
				array(
					'label'   => __( 'Your replies', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'show_avatar',
				array(
					'label'       => __( 'Reviewer picture', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'The reviewer’s Gravatar beside their name. Shows only where reviewer names show.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'section_review_form',
				array(
					'label'     => __( 'Review form', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
					'condition' => array( 'show_form' => 'yes' ),
				)
			);
			$this->add_control(
				'show_form_title',
				array(
					'label'   => __( 'Form heading', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'form_title',
				array(
					'label'       => __( 'Heading text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Product_Info::text( 'customer_review_title', __( 'Write a Review', 'wp-easycart' ), 'customer_review' ),
					'description' => __( 'Leave empty to use the text from your store’s language settings.', 'wp-easycart' ),
					'condition'   => array( 'show_form_title' => 'yes' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'button_text',
				array(
					'label'       => __( 'Button text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Product_Info::text( 'product_details_your_review_submit', __( 'Submit', 'wp-easycart' ), 'customer_review' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'form_guests',
				array(
					'label'       => __( 'Show to shoppers who are not signed in', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => 'yes',
					'description' => __( 'Off leaves the form, and its sign-in note, out until the shopper signs in.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->register_style_controls();
		}

		/**
		 * Style tab.
		 */
		protected function register_style_controls() {
			/* Summary. */
			$this->start_controls_section(
				'section_summary_style',
				array(
					'label'     => __( 'Rating summary', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'show_summary' => 'yes' ),
				)
			);
			$this->add_control(
				'summary_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews__summary' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->pi_text_controls( 'summary_score', '{{WRAPPER}} .wpec-pi-reviews__score', 'primary', array( 'label' => __( 'Average color', 'wp-easycart' ) ) );
			$this->add_control(
				'bar_color',
				array(
					'label'     => __( 'Bar color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => 'before',
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-bar: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'bar_background',
				array(
					'label'     => __( 'Bar background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-bar-bg: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'bar_height',
				array(
					'label'      => __( 'Bar height', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 2,
							'max' => 24,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-bar-h: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'      => 'summary_border',
					'separator' => 'before',
					'selector'  => '{{WRAPPER}} .wpec-pi-reviews .wpec-pi-reviews__summary',
				)
			);
			$this->add_responsive_control(
				'summary_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews .wpec-pi-reviews__summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'summary_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews .wpec-pi-reviews__summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'summary_based_heading',
				array(
					'label'     => __( '“Based on” text', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'summary_based', '{{WRAPPER}} .wpec-pi-reviews .wpec-pi-reviews__based', 'text', array( 'global' => false ) );
			$this->add_control(
				'bar_text_heading',
				array(
					'label'     => __( 'Star rows', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'bar_text', '{{WRAPPER}} .wpec-pi-reviews .wpec-pi-reviews__bar', 'text', array( 'global' => false ) );
			$this->end_controls_section();

			/* Stars. */
			$this->start_controls_section(
				'section_stars_style',
				array(
					'label' => __( 'Stars', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'star_color',
				array(
					'label'     => __( 'Color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-star: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'star_empty_color',
				array(
					'label'     => __( 'Empty star color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-star-empty: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'star_size',
				array(
					'label'      => __( 'Size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 8,
							'max' => 48,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-star-size: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'form_star_heading',
				array(
					'label'     => __( 'Stars in the review form', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'show_form' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'form_star_size',
				array(
					'label'      => __( 'Size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 12,
							'max' => 64,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-rate-size: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'show_form' => 'yes' ),
				)
			);
			$this->add_control(
				'form_star_color',
				array(
					'label'     => __( 'Chosen star color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-rate-on: {{VALUE}};' ),
					'condition' => array( 'show_form' => 'yes' ),
				)
			);
			$this->add_control(
				'form_star_empty_color',
				array(
					'label'     => __( 'Empty star color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-rate-off: {{VALUE}};' ),
					'condition' => array( 'show_form' => 'yes' ),
				)
			);
			$this->end_controls_section();

			/* Reviews. */
			$this->start_controls_section(
				'section_list_style',
				array(
					'label'     => __( 'Reviews', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'show_list' => 'yes' ),
				)
			);
			$this->pi_text_controls( 'list_heading', '{{WRAPPER}} .wpec-pi-reviews__heading', 'primary', array( 'label' => __( 'Heading color', 'wp-easycart' ) ) );
			$this->add_control(
				'review_background',
				array(
					'label'     => __( 'Review background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => 'before',
					'selectors' => array( '{{WRAPPER}} .wpec-pi-review' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'review_border',
					'selector' => '{{WRAPPER}} .wpec-pi-review',
				)
			);
			$this->add_responsive_control(
				'review_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-review' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'review_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-review' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'review_gap',
				array(
					'label'      => __( 'Space between reviews', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 60,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews__list' => 'gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'review_title_heading',
				array(
					'label'     => __( 'Review title', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'review_title', '{{WRAPPER}} .wpec-pi-review__title', 'secondary' );
			$this->add_control(
				'review_text_heading',
				array(
					'label'     => __( 'Review text', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'review_text', '{{WRAPPER}} .wpec-pi-review__text' );
			$this->add_control(
				'review_meta_heading',
				array(
					'label'     => __( 'Name and date', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'review_meta', '{{WRAPPER}} .wpec-pi-review__meta' );
			$this->add_responsive_control(
				'avatar_size',
				array(
					'label'      => __( 'Reviewer picture size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 16,
							'max' => 96,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-avatar: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'show_avatar' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'avatar_radius',
				array(
					'label'      => __( 'Reviewer picture corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-avatar-radius: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'show_avatar' => 'yes' ),
				)
			);

			$this->add_control(
				'verified_heading',
				array(
					'label'     => __( 'Verified buyer badge', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'show_verified' => 'yes' ),
				)
			);
			$this->add_control(
				'verified_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews .ec_review_verified' => 'color: {{VALUE}};' ),
					'condition' => array( 'show_verified' => 'yes' ),
				)
			);
			$this->add_control(
				'verified_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews .ec_review_verified' => 'background-color: {{VALUE}};' ),
					'condition' => array( 'show_verified' => 'yes' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'      => 'verified_typography',
					'selector'  => '{{WRAPPER}} .wpec-pi-reviews .ec_review_verified',
					'condition' => array( 'show_verified' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'verified_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews .ec_review_verified' => 'border-radius: {{SIZE}}{{UNIT}};' ),
					'condition'  => array( 'show_verified' => 'yes' ),
				)
			);

			$this->add_control(
				'reply_heading',
				array(
					'label'     => __( 'Your replies', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array( 'show_replies' => 'yes' ),
				)
			);
			$this->add_control(
				'reply_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews .ec_review_reply' => 'background-color: {{VALUE}};' ),
					'condition' => array( 'show_replies' => 'yes' ),
				)
			);
			$this->add_control(
				'reply_line_color',
				array(
					'label'     => __( 'Line color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews .ec_review_reply' => 'border-left-color: {{VALUE}};' ),
					'condition' => array( 'show_replies' => 'yes' ),
				)
			);
			$this->add_responsive_control(
				'reply_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews .ec_review_reply' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
					'condition'  => array( 'show_replies' => 'yes' ),
				)
			);
			$this->add_control(
				'reply_name_color',
				array(
					'label'     => __( 'Name color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews .ec_review_reply_who' => 'color: {{VALUE}};' ),
					'condition' => array( 'show_replies' => 'yes' ),
				)
			);
			$this->add_control(
				'reply_text_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews .ec_review_reply_text' => 'color: {{VALUE}};' ),
					'condition' => array( 'show_replies' => 'yes' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'      => 'reply_typography',
					'selector'  => '{{WRAPPER}} .wpec-pi-reviews .ec_review_reply_text',
					'condition' => array( 'show_replies' => 'yes' ),
				)
			);

			$this->add_control(
				'filter_clear_color',
				array(
					'label'     => __( '“Show all reviews” link color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'separator' => 'before',
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews .wpec-pi-reviews__filter-clear' => 'color: {{VALUE}};' ),
					'condition' => array( 'show_summary' => 'yes' ),
				)
			);
			$this->end_controls_section();

			/* Show more. */
			$this->start_controls_section(
				'section_more_style',
				array(
					'label'     => __( 'Show more button', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'show_list' => 'yes' ),
				)
			);
			$this->pi_note( 'more_style_note', esc_html__( 'Shows when “Reviews shown at first” leaves some out.', 'wp-easycart' ) );
			$this->pi_align_control( '{{WRAPPER}} .wpec-pi-reviews__more-wrap', 'text', 'more_align' );
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'more_typography',
					'selector' => '{{WRAPPER}} .wpec-pi-reviews .wpec-pi-reviews__more',
				)
			);
			$this->start_controls_tabs( 'more_tabs' );
			$this->start_controls_tab( 'more_tab_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->add_control(
				'more_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-more-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'more_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-more-bg: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'more_border_color',
				array(
					'label'     => __( 'Border color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-more-border: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'more_tab_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->add_control(
				'more_hover_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-more-hover-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'more_hover_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-more-hover-bg: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_responsive_control(
				'more_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'separator'  => 'before',
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-more-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'more_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-more-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();

			/* Form. */
			$this->start_controls_section(
				'section_form_style',
				array(
					'label'     => __( 'Review form', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'show_form' => 'yes' ),
				)
			);
			$this->add_control(
				'form_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-review-form' => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'form_border',
					'selector' => '{{WRAPPER}} .wpec-pi-review-form',
				)
			);
			$this->add_responsive_control(
				'form_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-review-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'form_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-review-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'form_title_heading',
				array(
					'label'     => __( 'Form heading', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'form_title', '{{WRAPPER}} .wpec-pi-review-form__title', 'primary' );
			$this->add_control(
				'form_label_heading',
				array(
					'label'     => __( 'Labels', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->pi_text_controls( 'form_label', '{{WRAPPER}} .wpec-pi-review-form__label' );
			$this->add_control(
				'input_heading',
				array(
					'label'     => __( 'Fields', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			/* Round 11: the fields' selectors outweigh the module's own field rule ( .wpec-pi-review-form .wpec-pi-review-form__input ). */
			$input = '{{WRAPPER}} .wpec-pi-review-form .wpec-pi-review-form__input';
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'input_typography',
					'selector' => $input,
				)
			);
			$this->add_control(
				'input_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $input => 'color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'input_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $input => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'input_border_color',
				array(
					'label'     => __( 'Border color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $input => 'border-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'input_focus_color',
				array(
					'label'       => __( 'Focus color', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'description' => __( 'The ring around the field the shopper is typing in, and around a star picked with the keyboard.', 'wp-easycart' ),
					'selectors'   => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-focus: {{VALUE}};' ),
				)
			);
			$this->add_responsive_control(
				'input_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( $input => 'border-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'input_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( $input => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'message_heading',
				array(
					'label'     => __( 'Messages', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->add_control(
				'message_success_color',
				array(
					'label'     => __( 'Review sent: text', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-msg-ok: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'message_success_background',
				array(
					'label'     => __( 'Review sent: background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-msg-ok-bg: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'message_error_color',
				array(
					'label'     => __( 'Problem: text', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-msg-error: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'message_error_background',
				array(
					'label'     => __( 'Problem: background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-msg-error-bg: {{VALUE}};' ),
				)
			);
			$this->end_controls_section();

			/* Button. */
			$this->start_controls_section(
				'section_button_style',
				array(
					'label'     => __( 'Button', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'show_form' => 'yes' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'button_typography',
					'global'   => array( 'default' => \Elementor\Core\Kits\Documents\Tabs\Global_Typography::TYPOGRAPHY_ACCENT ),
					'selector' => '{{WRAPPER}} .wpec-pi-review-form__button',
				)
			);
			$this->start_controls_tabs( 'button_tabs' );
			$this->start_controls_tab(
				'button_tab_normal',
				array( 'label' => __( 'Normal', 'wp-easycart' ) )
			);
			$this->add_control(
				'button_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-button-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'button_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-button-bg: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab(
				'button_tab_hover',
				array( 'label' => __( 'Hover', 'wp-easycart' ) )
			);
			$this->add_control(
				'button_hover_color',
				array(
					'label'     => __( 'Text color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-button-hover-color: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'button_hover_background',
				array(
					'label'     => __( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-button-hover-bg: {{VALUE}};' ),
				)
			);
			$this->add_control(
				'button_hover_border_color',
				array(
					'label'     => __( 'Border color', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .wpec-pi-review-form .wpec-pi-review-form__button:hover, {{WRAPPER}} .wpec-pi-review-form .wpec-pi-review-form__button:focus-visible' => 'border-color: {{VALUE}};' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'      => 'button_border',
					'separator' => 'before',
					'selector'  => '{{WRAPPER}} .wpec-pi-review-form .wpec-pi-review-form__button',
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'button_shadow',
					'selector' => '{{WRAPPER}} .wpec-pi-review-form .wpec-pi-review-form__button',
				)
			);
			$this->add_responsive_control(
				'button_radius',
				array(
					'label'      => __( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px', 'em' ),
					'separator'  => 'before',
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 50,
						),
					),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-reviews' => '--wpec-pi-button-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'button_padding',
				array(
					'label'      => __( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( '{{WRAPPER}} .wpec-pi-review-form .wpec-pi-review-form__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Output.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$on       = function ( $key ) use ( $settings ) {
				return WP_EasyCart_Product_Info::on( $settings, $key );
			};
			if ( ! $on( 'show_summary' ) && ! $on( 'show_list' ) && ! $on( 'show_form' ) ) {
				$this->ec_editor_notice( __( 'Every part of this widget is off.', 'wp-easycart' ), __( 'Turn on the rating summary, the reviews or the review form under Content › Reviews.', 'wp-easycart' ) );
				return;
			}
			$product = $this->pi_product( $settings, false );
			if ( ! $product ) {
				return;
			}
			if ( ! $this->pi_allowed( $settings, $product, 'use_customer_reviews' ) ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( 'Customer reviews are off for %s.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'Visitors see nothing here. Turn on Customer reviews in the product editor, or turn off “Follow each product’s settings” to show reviews on every product.', 'wp-easycart' ) );
				return;
			}
			$rating = WP_EasyCart_Product_Info::rating( $product );
			if ( ! $on( 'show_list' ) && ! $on( 'show_form' ) && 0 === $rating['count'] ) {
				/* translators: %s: product name. */
				$this->ec_editor_notice( sprintf( __( '%s has no reviews yet.', 'wp-easycart' ), $this->pi_name( $product ) ), __( 'The rating summary shows once the first review is approved.', 'wp-easycart' ) );
				return;
			}
			if ( $on( 'show_form' ) && function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				/* The form carries a nonce: a cached copy would refuse every review a day later. */
				wp_easycart_product_details_no_cache();
			}
			WP_EasyCart_Product_Info::reviews(
				$product,
				array(
					'summary'      => $on( 'show_summary' ),
					'list'         => $on( 'show_list' ),
					'form'         => $on( 'show_form' ),
					'list_heading' => $on( 'show_list_heading' ),
					'item_title'   => $on( 'show_review_title' ),
					'item_date'    => $on( 'show_date' ),
					'item_name'    => ( isset( $settings['show_name'] ) && in_array( $settings['show_name'], array( 'store', 'show', 'hide' ), true ) ) ? $settings['show_name'] : 'store',
					'item_rating'  => $on( 'show_rating' ),
					'item_text'    => $on( 'show_text' ),
					'verified'     => $on( 'show_verified' ),
					'replies'      => $on( 'show_replies' ),
					'form_heading' => $on( 'show_form_title' ),
					'form_title'   => isset( $settings['form_title'] ) ? (string) $settings['form_title'] : '',
					'button_text'  => isset( $settings['button_text'] ) ? (string) $settings['button_text'] : '',
					'sort'         => ( isset( $settings['sort'] ) && in_array( $settings['sort'], array( 'newest', 'oldest', 'highest', 'lowest' ), true ) ) ? $settings['sort'] : 'newest',
					'per_page'     => isset( $settings['per_page'] ) ? max( 0, (int) $settings['per_page'] ) : 0,
					'more_text'    => isset( $settings['more_text'] ) ? (string) $settings['more_text'] : '',
					'avatars'      => $on( 'show_avatar' ),
					'avatar_size'  => $this->avatar_pixels( $settings ),
					/* A switch never saved is on ( its default ); only an explicit off hides the form from guests. */
					'form_guests'  => ! isset( $settings['form_guests'] ) || $on( 'form_guests' ),
				)
			);
		}

		/**
		 * The size to ask Gravatar for: twice the widest size set ( sharp on high-density screens ).
		 *
		 * @param array $settings Settings.
		 * @return int
		 */
		private function avatar_pixels( $settings ) {
			$size = 40;
			foreach ( array( 'avatar_size', 'avatar_size_tablet', 'avatar_size_mobile' ) as $key ) {
				if ( isset( $settings[ $key ]['size'] ) && is_numeric( $settings[ $key ]['size'] ) ) {
					$size = max( $size, (int) $settings[ $key ]['size'] );
				}
			}
			return min( 192, $size * 2 );
		}
	}

endif;
