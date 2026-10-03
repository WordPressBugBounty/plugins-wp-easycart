<?php
/**
 * Elementor widget "Product Gallery" ( wp_easycart_product_gallery, 6.0.2 ).
 *
 * The product's images and videos: a swipeable slider ( slide or fade, loop, autoplay ) with thumbnails below, above, left or
 * right ( or none ), dots, zoom ( inside the image or a lens, on hover or click ), a keyboard-friendly lightbox ( images and
 * videos, counter, thumbnail strip ), responsive images ( srcset ) and lazy loading. Products with images per option switch
 * sets when the shopper picks the option in an Add to Cart widget on the same page; two galleries of one product ( desktop
 * and mobile copies ) both follow. Thumbnail positions follow the site's own breakpoints ( Elementor's responsive CSS ).
 * Replaces wp_easycart_product_details_images.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/trait-wp-easycart-product-buy-controls.php';

if ( ! class_exists( 'WP_EasyCart_Elementor_Product_Gallery_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Product Gallery.
	 */
	class WP_EasyCart_Elementor_Product_Gallery_Widget extends WP_EasyCart_Elementor_Widget_Base {

		use WP_EasyCart_Product_Buy_Controls;

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'product';

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'product-gallery';

		/**
		 * Widget name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_gallery';
		}

		/**
		 * Widget title.
		 *
		 * @return string
		 */
		public function get_title() {
			return __( 'Product Gallery', 'wp-easycart' );
		}

		/**
		 * Widget icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-product-images';
		}

		/**
		 * Search keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'gallery', 'product image', 'product images', 'images', 'photos', 'slider', 'carousel', 'zoom', 'lightbox', 'video', 'product' );
		}

		/**
		 * Scripts.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_merge( parent::get_script_depends(), array( WP_EasyCart_Product_Buy::HANDLE ) );
		}

		/**
		 * Styles.
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_merge( parent::get_style_depends(), array( WP_EasyCart_Product_Buy::HANDLE ) );
		}

		/**
		 * The CSS custom properties each thumbnail position sets on the widget ( read by product-buy.css ). Elementor prints
		 * them per breakpoint, the site's own ones included; the wpec-gallery-*thumbs-* classes set the same values for
		 * pages whose Elementor CSS was made before 6.0.2 round 11.
		 *
		 * @return array position => declarations.
		 */
		public static function position_vars() {
			$row    = '--wpec-g-cols: minmax(0, 1fr); --wpec-g-thumbs-display: flex; --wpec-g-thumbs-dir: row; --wpec-g-th-h: auto; --wpec-g-th-minh: 0px; --wpec-g-th-ox: auto; --wpec-g-th-oy: hidden; --wpec-g-th-align: flex-start; --wpec-g-th-w: var(--wpec-thumb-basis, var(--wpec-thumb-size, 80px)); --wpec-g-th-prev: -135deg; --wpec-g-th-next: 45deg;';
			$column = '--wpec-g-thumbs-display: flex; --wpec-g-thumbs-dir: column; --wpec-g-th-h: var(--wpec-g-strip-height, 0px); --wpec-g-th-minh: var(--wpec-g-strip-min, 100%); --wpec-g-th-ox: hidden; --wpec-g-th-oy: auto; --wpec-g-th-align: stretch; --wpec-g-th-w: 100%; --wpec-g-th-prev: -45deg; --wpec-g-th-next: 135deg;';
			return array(
				'below' => '--wpec-g-stage-row: 1; --wpec-g-stage-col: 1; --wpec-g-thumbs-row: 2; --wpec-g-thumbs-col: 1; ' . $row,
				'above' => '--wpec-g-stage-row: 2; --wpec-g-stage-col: 1; --wpec-g-thumbs-row: 1; --wpec-g-thumbs-col: 1; ' . $row,
				'left'  => '--wpec-g-stage-row: 1; --wpec-g-stage-col: 2; --wpec-g-thumbs-row: 1; --wpec-g-thumbs-col: 1; --wpec-g-cols: var(--wpec-thumb-size, 80px) minmax(0, 1fr); ' . $column,
				'right' => '--wpec-g-stage-row: 1; --wpec-g-stage-col: 1; --wpec-g-thumbs-row: 1; --wpec-g-thumbs-col: 2; --wpec-g-cols: minmax(0, 1fr) var(--wpec-thumb-size, 80px); ' . $column,
				'none'  => '--wpec-g-stage-row: 1; --wpec-g-stage-col: 1; --wpec-g-cols: minmax(0, 1fr); --wpec-g-thumbs-display: none;',
			);
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_register_product_controls();

			$this->start_controls_section(
				'section_gallery',
				array(
					'label' => __( 'Gallery', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_responsive_control(
				'thumbnails_position',
				array(
					'label'                => __( 'Thumbnails', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => 'below',
					'mobile_default'       => 'below',
					'options'              => array(
						'below' => __( 'Below', 'wp-easycart' ),
						'above' => __( 'Above', 'wp-easycart' ),
						'left'  => __( 'Left', 'wp-easycart' ),
						'right' => __( 'Right', 'wp-easycart' ),
						'none'  => __( 'None', 'wp-easycart' ),
					),
					'prefix_class'         => 'wpec-gallery%s-thumbs-',
					'selectors_dictionary' => self::position_vars(),
					'selectors'            => array(
						'{{WRAPPER}}' => '{{VALUE}}',
					),
				)
			);
			$this->add_control(
				'mobile_slider',
				array(
					'label'        => __( 'Dots instead of thumbnails on phones', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'description'  => __( 'Shoppers swipe between images either way. Thumbnails you put on the left or right on phones stay.', 'wp-easycart' ),
					'prefix_class' => 'wpec-gallery-dots-',
				)
			);
			$this->add_control(
				'dots_everywhere',
				array(
					'label'        => __( 'Dots on every screen', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => '',
					'description'  => __( 'Also on computers and tablets, with the thumbnails.', 'wp-easycart' ),
					'prefix_class' => 'wpec-gallery-dots-all-',
				)
			);
			$this->add_control(
				'show_arrows',
				array(
					'label'        => __( 'Arrows', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'prefix_class' => 'wpec-gallery-arrows-',
				)
			);
			$this->add_control(
				'arrow_prev_icon',
				array(
					'label'     => __( 'Previous arrow icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'skin'      => 'inline',
					'condition' => array(
						'show_arrows' => 'yes',
					),
				)
			);
			$this->add_control(
				'arrow_next_icon',
				array(
					'label'     => __( 'Next arrow icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'skin'      => 'inline',
					'condition' => array(
						'show_arrows' => 'yes',
					),
				)
			);
			$this->add_control(
				'image_ratio',
				array(
					'label'        => __( 'Image shape', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'auto',
					'options'      => array(
						'auto'   => __( 'As uploaded', 'wp-easycart' ),
						'1-1'    => __( 'Square', 'wp-easycart' ),
						'4-3'    => __( 'Landscape 4:3', 'wp-easycart' ),
						'3-4'    => __( 'Portrait 3:4', 'wp-easycart' ),
						'16-9'   => __( 'Wide 16:9', 'wp-easycart' ),
						'custom' => __( 'Custom', 'wp-easycart' ),
					),
					'separator'    => 'before',
					'prefix_class' => 'wpec-gallery-ratio-',
				)
			);
			$this->add_control(
				'image_ratio_custom',
				array(
					'label'       => __( 'Width to height', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => 1.25,
					'min'         => 0.2,
					'max'         => 5,
					'step'        => 0.05,
					'description' => __( 'For example 1.25 for 5:4, 0.8 for 4:5.', 'wp-easycart' ),
					'selectors'   => array(
						'{{WRAPPER}}' => '--wpec-gallery-ratio: {{VALUE}};',
					),
					'condition'   => array(
						'image_ratio' => 'custom',
					),
				)
			);
			$this->add_control(
				'image_fit',
				array(
					'label'        => __( 'Fit the image', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'contain',
					'options'      => array(
						'contain' => __( 'Show all of it', 'wp-easycart' ),
						'cover'   => __( 'Fill the shape ( crops the edges )', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-gallery-fit-',
					'condition'    => array(
						'image_ratio!' => 'auto',
					),
				)
			);
			$this->add_control(
				'image_size',
				array(
					'label'     => __( 'Image size', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'large',
					'options'   => WP_EasyCart_Product_Buy::image_size_options(),
					'separator' => 'before',
				)
			);
			$this->add_control(
				'thumbnail_size',
				array(
					'label'       => __( 'Thumbnail size', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'thumbnail',
					'options'     => WP_EasyCart_Product_Buy::image_size_options(),
					'description' => __( 'For images from the media library. Browsers pick the best file for the screen.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();

			$this->register_motion_controls();
			$this->register_zoom_controls();
			$this->register_thumbnail_controls();
			$this->register_style_controls();
		}

		/**
		 * Content › Slides: transition, loop, autoplay, videos.
		 */
		private function register_motion_controls() {
			$this->start_controls_section(
				'section_slides',
				array(
					'label' => __( 'Slides', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'transition',
				array(
					'label'        => __( 'Transition', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'slide',
					'options'      => array(
						'slide' => __( 'Slide', 'wp-easycart' ),
						'fade'  => __( 'Fade', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-gallery-transition-',
				)
			);
			$this->add_control(
				'transition_speed',
				array(
					'label'       => __( 'Speed ( ms )', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => '',
					'min'         => 0,
					'max'         => 3000,
					'step'        => 50,
					'placeholder' => '400',
					'description' => __( 'Empty: the browser’s own smooth scroll ( slide ) or 400 ms ( fade ).', 'wp-easycart' ),
					'selectors'   => array(
						'{{WRAPPER}}' => '--wpec-gallery-speed: {{VALUE}}ms;',
					),
				)
			);
			$this->add_control(
				'loop',
				array(
					'label'       => __( 'Loop', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'After the last image the arrows go back to the first.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'autoplay',
				array(
					'label'       => __( 'Autoplay', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'Moves to the next image by itself, with a pause button. Never for visitors who ask their device for less motion, nor while the larger image is open.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'autoplay_interval',
				array(
					'label'     => __( 'Every ( ms )', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::NUMBER,
					'default'   => 5000,
					'min'       => 1500,
					'max'       => 20000,
					'step'      => 500,
					'condition' => array(
						'autoplay' => 'yes',
					),
				)
			);
			$this->add_control(
				'autoplay_pause',
				array(
					'label'     => __( 'Pause on hover', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'condition' => array(
						'autoplay' => 'yes',
					),
				)
			);
			$this->add_control(
				'video_autoplay',
				array(
					'label'       => __( 'Play videos by themselves', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'separator'   => 'before',
					'description' => __( 'Uploaded videos play muted and on a loop while they are shown. YouTube and Vimeo videos still start with a click.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Content › Zoom and lightbox.
		 */
		private function register_zoom_controls() {
			$this->start_controls_section(
				'section_zoom',
				array(
					'label' => __( 'Zoom and larger image', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'zoom',
				array(
					'label'   => __( 'Zoom on hover', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->add_control(
				'zoom_type',
				array(
					'label'     => __( 'Zoom', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'inner',
					'options'   => array(
						'inner' => __( 'Inside the image', 'wp-easycart' ),
						'lens'  => __( 'A lens that follows the pointer', 'wp-easycart' ),
					),
					'condition' => array(
						'zoom' => 'yes',
					),
				)
			);
			$this->add_control(
				'zoom_trigger',
				array(
					'label'       => __( 'Zoom starts', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'hover',
					'options'     => array(
						'hover' => __( 'On hover', 'wp-easycart' ),
						'click' => __( 'On click', 'wp-easycart' ),
					),
					'description' => __( 'With a click to zoom, the larger image opens from its button.', 'wp-easycart' ),
					'condition'   => array(
						'zoom' => 'yes',
					),
				)
			);
			$this->add_control(
				'zoom_level',
				array(
					'label'     => __( 'Magnification', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SLIDER,
					'default'   => array(
						'size' => 2,
					),
					'range'     => array(
						'px' => array(
							'min'  => 1.25,
							'max'  => 5,
							'step' => 0.25,
						),
					),
					'condition' => array(
						'zoom' => 'yes',
					),
				)
			);
			$this->add_control(
				'lens_size',
				array(
					'label'      => __( 'Lens size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 80,
							'max' => 400,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}}' => '--wpec-gallery-lens: {{SIZE}}{{UNIT}};',
					),
					'condition'  => array(
						'zoom'      => 'yes',
						'zoom_type' => 'lens',
					),
				)
			);
			$this->add_control(
				'lightbox',
				array(
					'label'     => __( 'Open larger image on click', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'separator' => 'before',
				)
			);
			$this->add_control(
				'lightbox_videos',
				array(
					'label'     => __( 'Videos in the larger view', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => '',
					'condition' => array(
						'lightbox' => 'yes',
					),
				)
			);
			$this->add_control(
				'lightbox_counter',
				array(
					'label'     => __( 'Counter ( 2 / 5 )', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => 'yes',
					'condition' => array(
						'lightbox' => 'yes',
					),
				)
			);
			$this->add_control(
				'lightbox_thumbnails',
				array(
					'label'     => __( 'Thumbnails in the larger view', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => '',
					'condition' => array(
						'lightbox' => 'yes',
					),
				)
			);
			$this->add_control(
				'lightbox_button',
				array(
					'label'       => __( 'Magnifier button', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SWITCHER,
					'default'     => '',
					'description' => __( 'A button on the image that opens the larger view.', 'wp-easycart' ),
					'condition'   => array(
						'lightbox' => 'yes',
					),
				)
			);
			$this->add_control(
				'lightbox_button_icon',
				array(
					'label'     => __( 'Button icon', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'skin'      => 'inline',
					'condition' => array(
						'lightbox'        => 'yes',
						'lightbox_button' => 'yes',
					),
				)
			);
			$this->add_control(
				'lightbox_button_position',
				array(
					'label'        => __( 'Button corner', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'top-right',
					'options'      => array(
						'top-left'     => __( 'Top left', 'wp-easycart' ),
						'top-right'    => __( 'Top right', 'wp-easycart' ),
						'bottom-left'  => __( 'Bottom left', 'wp-easycart' ),
						'bottom-right' => __( 'Bottom right', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-gallery-expand-',
					'condition'    => array(
						'lightbox'        => 'yes',
						'lightbox_button' => 'yes',
					),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Content › Thumbnails: how many show and how the strip moves.
		 */
		private function register_thumbnail_controls() {
			$this->start_controls_section(
				'section_thumbnails',
				array(
					'label' => __( 'Thumbnails', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_responsive_control(
				'thumbnails_visible',
				array(
					'label'       => __( 'Thumbnails in view', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 2,
					'max'         => 10,
					'step'        => 1,
					'description' => __( 'For thumbnails below or above: this many fill the width. Empty keeps the thumbnail size.', 'wp-easycart' ),
					'selectors'   => array(
						'{{WRAPPER}}' => '--wpec-thumb-basis: calc( ( 100% - ( {{VALUE}} - 1 ) * var( --wpec-gallery-gap, 10px ) ) / {{VALUE}} );',
					),
				)
			);
			$this->add_control(
				'thumbnails_nav',
				array(
					'label'       => __( 'More thumbnails than fit', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'scroll',
					'options'     => array(
						'scroll' => __( 'Scroll the strip', 'wp-easycart' ),
						'arrows' => __( 'Arrows on the strip', 'wp-easycart' ),
					),
					'description' => __( 'The arrows show only when the strip has more than fits.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'thumbnail_ratio',
				array(
					'label'                => __( 'Thumbnail shape', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::SELECT,
					'default'              => '1-1',
					'options'              => array(
						'1-1'  => __( 'Square', 'wp-easycart' ),
						'4-3'  => __( 'Landscape 4:3', 'wp-easycart' ),
						'3-4'  => __( 'Portrait 3:4', 'wp-easycart' ),
						'16-9' => __( 'Wide 16:9', 'wp-easycart' ),
					),
					'selectors_dictionary' => array(
						'1-1'  => '1 / 1',
						'4-3'  => '4 / 3',
						'3-4'  => '3 / 4',
						'16-9' => '16 / 9',
					),
					'selectors'            => array(
						'{{WRAPPER}}' => '--wpec-thumb-ratio: {{VALUE}};',
					),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Style tab.
		 */
		private function register_style_controls() {
			$this->start_controls_section(
				'style_image',
				array(
					'label' => __( 'Main image', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_color( 'image_background_color', __( 'Background', 'wp-easycart' ), '--wpec-gallery-bg' );
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'image_border',
					'selector' => '{{WRAPPER}} .wpec-gallery__stage',
				)
			);
			$this->add_responsive_control(
				'image_border_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-gallery__stage' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->add_control(
				'image_hover',
				array(
					'label'        => __( 'On hover', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => '',
					'options'      => array(
						''     => __( 'Nothing', 'wp-easycart' ),
						'zoom' => __( 'Grow slightly', 'wp-easycart' ),
					),
					'separator'    => 'before',
					'description'  => __( 'Shows when zoom is off, or with the lens.', 'wp-easycart' ),
					'prefix_class' => 'wpec-gallery-hover-',
				)
			);
			$this->start_controls_tabs( 'image_filter_tabs' );
			$this->start_controls_tab( 'image_filter_normal', array( 'label' => __( 'Normal', 'wp-easycart' ) ) );
			$this->add_group_control(
				\Elementor\Group_Control_Css_Filter::get_type(),
				array(
					'name'     => 'image_filters',
					'selector' => '{{WRAPPER}} .wpec-gallery__img',
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab( 'image_filter_hover', array( 'label' => __( 'Hover', 'wp-easycart' ) ) );
			$this->add_group_control(
				\Elementor\Group_Control_Css_Filter::get_type(),
				array(
					'name'     => 'image_hover_filters',
					'selector' => '{{WRAPPER}} .wpec-gallery__slide:hover .wpec-gallery__img',
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->end_controls_section();

			$this->start_controls_section(
				'style_arrows',
				array(
					'label'     => __( 'Arrows', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'show_arrows' => 'yes',
					),
				)
			);
			$this->pb_color( 'arrow_color', __( 'Arrow color', 'wp-easycart' ), '--wpec-gallery-arrow-color' );
			$this->pb_color( 'arrow_background_color', __( 'Arrow background', 'wp-easycart' ), '--wpec-gallery-arrow-bg' );
			$this->pb_size(
				'arrow_size',
				__( 'Button size', 'wp-easycart' ),
				'--wpec-gallery-arrow-size',
				array(
					'range' => array(
						'px' => array(
							'min' => 20,
							'max' => 80,
						),
					),
				)
			);
			$this->pb_size( 'arrow_icon_size', __( 'Icon size', 'wp-easycart' ), '--wpec-gallery-arrow-icon' );
			$this->add_control(
				'arrow_shape',
				array(
					'label'        => __( 'Shape', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'circle',
					'options'      => array(
						'circle' => __( 'Circle', 'wp-easycart' ),
						'square' => __( 'Square', 'wp-easycart' ),
						'none'   => __( 'No background', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-gallery-arrow-shape-',
				)
			);
			$this->add_control(
				'arrows_visibility',
				array(
					'label'        => __( 'Show', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'hover',
					'options'      => array(
						'hover'  => __( 'On hover ( always on touch screens )', 'wp-easycart' ),
						'always' => __( 'Always', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-gallery-arrows-show-',
				)
			);
			$this->add_control(
				'arrows_position',
				array(
					'label'        => __( 'Position', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'inside',
					'options'      => array(
						'inside'  => __( 'On the image', 'wp-easycart' ),
						'outside' => __( 'Beside the image', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-gallery-arrows-at-',
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'style_dots',
				array(
					'label' => __( 'Dots', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->pb_color( 'dot_color', __( 'Color', 'wp-easycart' ), '--wpec-gallery-dot' );
			$this->pb_color( 'dot_active_color', __( 'Current image', 'wp-easycart' ), '--wpec-gallery-dot-active' );
			$this->pb_size(
				'dot_size',
				__( 'Size', 'wp-easycart' ),
				'--wpec-gallery-dot-size',
				array(
					'range' => array(
						'px' => array(
							'min' => 4,
							'max' => 24,
						),
					),
				)
			);
			$this->add_control(
				'dots_position',
				array(
					'label'        => __( 'Position', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SELECT,
					'default'      => 'inside',
					'options'      => array(
						'inside' => __( 'On the image', 'wp-easycart' ),
						'below'  => __( 'Under the image', 'wp-easycart' ),
					),
					'prefix_class' => 'wpec-gallery-dots-at-',
				)
			);
			$this->end_controls_section();

			$this->start_controls_section(
				'style_thumbnails',
				array(
					'label' => __( 'Thumbnails', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_responsive_control(
				'thumbnail_width',
				array(
					'label'      => __( 'Size', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 32,
							'max' => 200,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}}' => '--wpec-thumb-size: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'thumbnail_gap',
				array(
					'label'      => __( 'Space between', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array(
						'{{WRAPPER}}' => '--wpec-gallery-gap: {{SIZE}}{{UNIT}};',
					),
				)
			);
			$this->add_responsive_control(
				'thumbnail_strip_height',
				array(
					'label'       => __( 'Height of a strip on the left or right', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'size_units'  => array( 'px', 'vh' ),
					'range'       => array(
						'px' => array(
							'min' => 120,
							'max' => 1000,
						),
					),
					'description' => __( 'Empty: as tall as the image.', 'wp-easycart' ),
					'selectors'   => array(
						'{{WRAPPER}}' => '--wpec-g-strip-height: {{SIZE}}{{UNIT}}; --wpec-g-strip-min: 0px;',
					),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'thumbnail_border',
					'selector' => '{{WRAPPER}} .wpec-gallery__thumb',
				)
			);
			$this->add_responsive_control(
				'thumbnail_border_radius',
				array(
					'label'      => __( 'Border radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array(
						'{{WRAPPER}} .wpec-gallery__thumb, {{WRAPPER}} .wpec-gallery__thumb img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->pb_color( 'thumbnail_active_color', __( 'Chosen thumbnail', 'wp-easycart' ), '--wpec-thumb-active' );
			$this->pb_heading( 'thumbnail_opacity_heading', __( 'Opacity', 'wp-easycart' ) );
			foreach ( array(
				'thumbnail_opacity'        => array( __( 'Normal', 'wp-easycart' ), '--wpec-thumb-opacity' ),
				'thumbnail_hover_opacity'  => array( __( 'Hover', 'wp-easycart' ), '--wpec-thumb-hover-opacity' ),
				'thumbnail_active_opacity' => array( __( 'Chosen', 'wp-easycart' ), '--wpec-thumb-active-opacity' ),
			) as $id => $spec ) {
				$this->add_control(
					$id,
					array(
						'label'     => $spec[0],
						'type'      => \Elementor\Controls_Manager::SLIDER,
						'range'     => array(
							'px' => array(
								'min'  => 0.1,
								'max'  => 1,
								'step' => 0.05,
							),
						),
						'selectors' => array(
							'{{WRAPPER}}' => $spec[1] . ': {{SIZE}};',
						),
					)
				);
			}
			$this->pb_heading( 'thumbnail_nav_heading', __( 'Strip arrows', 'wp-easycart' ), array( 'condition' => array( 'thumbnails_nav' => 'arrows' ) ) );
			$this->pb_color( 'thumbnail_nav_color', __( 'Arrow color', 'wp-easycart' ), '--wpec-thumb-nav-color', array( 'condition' => array( 'thumbnails_nav' => 'arrows' ) ) );
			$this->pb_color( 'thumbnail_nav_background_color', __( 'Arrow background', 'wp-easycart' ), '--wpec-thumb-nav-bg', array( 'condition' => array( 'thumbnails_nav' => 'arrows' ) ) );
			$this->end_controls_section();

			$this->start_controls_section(
				'style_lightbox',
				array(
					'label'     => __( 'Larger image', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array(
						'lightbox' => 'yes',
					),
				)
			);
			$this->pb_color( 'lightbox_background_color', __( 'Background', 'wp-easycart' ), '--wpec-lb-bg' );
			$this->pb_color( 'lightbox_controls_color', __( 'Buttons and counter', 'wp-easycart' ), '--wpec-lb-color' );
			$this->pb_color( 'lightbox_controls_background_color', __( 'Button background', 'wp-easycart' ), '--wpec-lb-button-bg' );
			$this->pb_heading( 'expand_heading', __( 'Magnifier button', 'wp-easycart' ), array( 'condition' => array( 'lightbox_button' => 'yes' ) ) );
			$this->pb_color( 'expand_color', __( 'Icon color', 'wp-easycart' ), '--wpec-gallery-expand-color', array( 'condition' => array( 'lightbox_button' => 'yes' ) ) );
			$this->pb_color( 'expand_background_color', __( 'Background', 'wp-easycart' ), '--wpec-gallery-expand-bg', array( 'condition' => array( 'lightbox_button' => 'yes' ) ) );
			$this->pb_size( 'expand_size', __( 'Size', 'wp-easycart' ), '--wpec-gallery-expand-size', array( 'condition' => array( 'lightbox_button' => 'yes' ) ) );
			$this->end_controls_section();
		}

		/**
		 * The widget's choices for the storefront script ( data-wpec-* on the gallery ).
		 *
		 * @param array $settings Settings.
		 * @return array name => value.
		 */
		private function script_options( $settings ) {
			$on       = function ( $id ) use ( $settings ) {
				return isset( $settings[ $id ] ) && 'yes' === $settings[ $id ];
			};
			$zoom     = $on( 'zoom' );
			$lightbox = $on( 'lightbox' );
			$level    = ( isset( $settings['zoom_level']['size'] ) && is_numeric( $settings['zoom_level']['size'] ) ) ? (float) $settings['zoom_level']['size'] : 2.0;
			$interval = isset( $settings['autoplay_interval'] ) ? (int) $settings['autoplay_interval'] : 5000;
			$speed    = ( isset( $settings['transition_speed'] ) && is_numeric( $settings['transition_speed'] ) ) ? max( 0, (int) $settings['transition_speed'] ) : -1;
			return array(
				'data-wpec-zoom'             => $zoom ? '1' : '0',
				'data-wpec-zoom-type'        => ( $zoom && isset( $settings['zoom_type'] ) && 'lens' === $settings['zoom_type'] ) ? 'lens' : 'inner',
				'data-wpec-zoom-trigger'     => ( $zoom && isset( $settings['zoom_trigger'] ) && 'click' === $settings['zoom_trigger'] ) ? 'click' : 'hover',
				'data-wpec-zoom-level'       => (string) min( 5, max( 1.25, $level ) ),
				'data-wpec-lightbox'         => $lightbox ? '1' : '0',
				'data-wpec-lightbox-videos'  => ( $lightbox && $on( 'lightbox_videos' ) ) ? '1' : '0',
				'data-wpec-lightbox-counter' => ( ! $lightbox || ! isset( $settings['lightbox_counter'] ) || 'yes' === $settings['lightbox_counter'] ) ? '1' : '0',
				'data-wpec-lightbox-thumbs'  => ( $lightbox && $on( 'lightbox_thumbnails' ) ) ? '1' : '0',
				'data-wpec-transition'       => ( isset( $settings['transition'] ) && 'fade' === $settings['transition'] ) ? 'fade' : 'slide',
				'data-wpec-speed'            => (string) $speed,
				'data-wpec-loop'             => $on( 'loop' ) ? '1' : '0',
				'data-wpec-autoplay'         => $on( 'autoplay' ) ? (string) min( 20000, max( 1500, $interval ) ) : '0',
				'data-wpec-autoplay-pause'   => ( ! isset( $settings['autoplay_pause'] ) || 'yes' === $settings['autoplay_pause'] ) ? '1' : '0',
				'data-wpec-video-autoplay'   => $on( 'video_autoplay' ) ? '1' : '0',
				'data-wpec-thumbs-nav'       => ( isset( $settings['thumbnails_nav'] ) && 'arrows' === $settings['thumbnails_nav'] ) ? 'arrows' : 'scroll',
			);
		}

		/**
		 * The phones rule of "Dots instead of thumbnails on phones", at the site's own phone breakpoint. Thumbnails set to show
		 * on the left or right on phones stay ( round 11: the phone position was ignored while the dots were on ).
		 *
		 * @param array $settings Settings.
		 * @return string A <style> element, or ''.
		 */
		private function phone_dots_style( $settings ) {
			if ( ! isset( $settings['mobile_slider'] ) || 'yes' !== $settings['mobile_slider'] ) {
				return '';
			}
			/* The position phones get: their own, else the next larger device's, as Elementor inherits it. */
			$phone = 'below';
			foreach ( array( '_mobile', '_mobile_extra', '_tablet', '_tablet_extra', '_laptop', '' ) as $device ) {
				if ( isset( $settings[ 'thumbnails_position' . $device ] ) && '' !== (string) $settings[ 'thumbnails_position' . $device ] ) {
					$phone = (string) $settings[ 'thumbnails_position' . $device ];
					break;
				}
			}
			if ( in_array( $phone, array( 'left', 'right' ), true ) ) {
				return '';
			}
			$max = 767;
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) && is_object( \Elementor\Plugin::$instance->breakpoints ) && method_exists( \Elementor\Plugin::$instance->breakpoints, 'get_breakpoints' ) ) {
				$breakpoint = \Elementor\Plugin::$instance->breakpoints->get_breakpoints( 'mobile' );
				if ( is_object( $breakpoint ) && method_exists( $breakpoint, 'get_value' ) && (int) $breakpoint->get_value() > 0 ) {
					$max = (int) $breakpoint->get_value();
				}
			}
			$id = '.elementor-element-' . preg_replace( '/[^a-z0-9]/i', '', (string) $this->get_id() );
			return '<style>@media (max-width:' . (int) $max . 'px){' . $id . ' .wpec-gallery__set{grid-template-columns:minmax(0,1fr)}' . $id . ' .wpec-gallery__stage{grid-row:1;grid-column:1}' . $id . ' .wpec-gallery__set>.wpec-gallery__thumbs,' . $id . ' .wpec-gallery__thumbs-wrap{display:none}' . $id . ' .wpec-gallery__dots{display:flex}}</style>';
		}

		/**
		 * Render.
		 */
		protected function render() {
			$settings = $this->get_settings_for_display();
			$product  = $this->ec_product( $settings );
			if ( ! $product ) {
				$this->ec_no_product_notice( __( 'Product Gallery', 'wp-easycart' ) );
				return;
			}
			if ( ! apply_filters( 'wp_easycart_product_details_show_images', true ) ) {
				return;
			}
			WP_EasyCart_Product_Buy::prepare( $product, $this->ec_is_editor() );
			$gallery = WP_EasyCart_Product_Buy::gallery(
				$product,
				array(
					'image_size' => WP_EasyCart_Product_Buy::image_size( isset( $settings['image_size'] ) ? $settings['image_size'] : '', 'large' ),
					'thumb_size' => WP_EasyCart_Product_Buy::image_size( isset( $settings['thumbnail_size'] ) ? $settings['thumbnail_size'] : '', 'thumbnail' ),
					'zoom_size'  => 'full',
				)
			);
			if ( empty( $gallery['sets'] ) ) {
				$this->ec_editor_notice( __( 'Product Gallery', 'wp-easycart' ), __( 'This product has no images yet.', 'wp-easycart' ) );
				return;
			}
			$rand     = WP_EasyCart_Product_Buy::next_rand();
			$pid      = (int) $product->product_id;
			$title    = wp_strip_all_tags( stripslashes( (string) $product->title ) );
			$options  = $this->script_options( $settings );
			$lightbox = ( '1' === $options['data-wpec-lightbox'] );
			$texts    = array(
				'previous'        => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_previous', __( 'Previous image', 'wp-easycart' ) ) ),
				'next'            => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_next', __( 'Next image', 'wp-easycart' ) ) ),
				'open'            => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_open', __( 'Open larger image', 'wp-easycart' ) ) ),
				'close'           => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_close', __( 'Close', 'wp-easycart' ) ) ),
				'thumbnail'       => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_thumbnail', __( 'Show image [number]', 'wp-easycart' ) ) ),
				'play'            => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_play', __( 'Play video', 'wp-easycart' ) ) ),
				'slide'           => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_slide', __( '[number] of [count]', 'wp-easycart' ) ) ),
				'thumbs_previous' => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_thumbs_previous', __( 'Earlier thumbnails', 'wp-easycart' ) ) ),
				'thumbs_next'     => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_thumbs_next', __( 'More thumbnails', 'wp-easycart' ) ) ),
				'pause'           => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_pause', __( 'Pause the slideshow', 'wp-easycart' ) ) ),
				'resume'          => wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_resume', __( 'Play the slideshow', 'wp-easycart' ) ) ),
			);
			$icons    = array(
				'prev'   => $this->pb_icon_html( isset( $settings['arrow_prev_icon'] ) ? $settings['arrow_prev_icon'] : null ),
				'next'   => $this->pb_icon_html( isset( $settings['arrow_next_icon'] ) ? $settings['arrow_next_icon'] : null ),
				'expand' => ( $lightbox && isset( $settings['lightbox_button'] ) && 'yes' === $settings['lightbox_button'] ) ? $this->pb_icon_html( isset( $settings['lightbox_button_icon'] ) ? $settings['lightbox_button_icon'] : null ) : false,
			);
			if ( '' === $icons['expand'] ) {
				$icons['expand'] = '<svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 3a7 7 0 0 1 5.6 11.2l4.6 4.6-1.4 1.4-4.6-4.6A7 7 0 1 1 10 3zm0 2a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm-1 2h2v2h2v2h-2v2H9v-2H7V9h2z"/></svg>';
			}
			$label = str_replace( '[product]', $title, wp_strip_all_tags( WP_EasyCart_Product_Buy::text( 'gallery_label', __( '[product] images', 'wp-easycart' ) ) ) );
			$many = false;
			foreach ( $gallery['sets'] as $items ) {
				$many = ( $many || count( $items ) > 1 );
			}
			if ( $many ) {
				echo $this->phone_dots_style( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS built from integers and a sanitized element id.
			}
			echo '<div class="wpec-el wpec-gallery" role="region" aria-label="' . esc_attr( $label ) . '" data-product-id="' . esc_attr( $pid ) . '" data-rand-id="' . esc_attr( $rand ) . '"';
			foreach ( $options as $name => $value ) {
				echo ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
			}
			echo ' data-wpec-image-field="' . esc_attr( $gallery['field'] ) . '" data-wpec-texts="' . esc_attr( wp_json_encode( $texts ) ) . '">';

			// The hook ec_option1_image_change() looks for: it then switches this gallery's sets ( #ec_details_thumbnails_* ). Its
			// image is the one shown ( product-buy.js keeps it current ), which wpeasycart_product_state reports as state.image;
			// lazy, so the hidden copy is never downloaded.
			$initial = isset( $gallery['sets'][ $gallery['initial'] ][0] ) ? $gallery['sets'][ $gallery['initial'] ][0] : reset( $gallery['sets'] )[0];
			echo '<span class="ec_details_main_image wpec-gallery__hook" data-product-id="' . esc_attr( $pid ) . '" data-rand-id="' . esc_attr( $rand ) . '" hidden>';
			echo '<img class="wpec-gallery__hook-img" src="' . esc_url( $initial['src'] ) . '" alt="" loading="lazy" decoding="async" /></span>';
			$first_set = true;
			foreach ( $gallery['sets'] as $optionitem_id => $items ) {
				$active = ( (int) $optionitem_id === (int) $gallery['initial'] );
				$this->render_set( $pid, $rand, (int) $optionitem_id, $items, $active, ( $active && $first_set ), $texts, $lightbox, $icons, $options );
				if ( $active ) {
					$first_set = false;
				}
			}
			echo '</div>';
		}

		/**
		 * One image set ( a set per option item for products with images per option ).
		 *
		 * @param int   $pid           Product id.
		 * @param int   $rand          Instance number.
		 * @param int   $optionitem_id Option item id ( 0 = the product's own images ).
		 * @param array $items         Items from WP_EasyCart_Product_Buy::gallery().
		 * @param bool  $active        The set shown first.
		 * @param bool  $eager         Load its first image at once.
		 * @param array $texts         Labels.
		 * @param bool  $lightbox      Lightbox on.
		 * @param array $icons         Arrow and magnifier icons ( markup; expand false = no magnifier button ).
		 * @param array $options       script_options().
		 */
		private function render_set( $pid, $rand, $optionitem_id, $items, $active, $eager, $texts, $lightbox, $icons = array(), $options = array() ) {
			$count    = count( $items );
			$sizes    = '(max-width: 767px) 100vw, (max-width: 1024px) 60vw, 50vw';
			$autoplay = ( isset( $options['data-wpec-autoplay'] ) && '0' !== $options['data-wpec-autoplay'] );
			$muted    = ( isset( $options['data-wpec-video-autoplay'] ) && '1' === $options['data-wpec-video-autoplay'] );
			echo '<div class="wpec-gallery__set ec_details_thumbnails_' . esc_attr( $pid . '_' . $rand ) . ( $active ? ' is-active' : ' ec_inactive' ) . ( ( $count > 1 ) ? ' has-many' : '' ) . '" id="' . esc_attr( 'ec_details_thumbnails_' . $optionitem_id . '_' . $pid . '_' . $rand ) . '" data-wpec-set="' . esc_attr( $optionitem_id ) . '">';
			echo '<div class="wpec-gallery__stage">';
			echo '<div class="wpec-gallery__track"' . ( ( $count > 1 ) ? ' tabindex="0"' : '' ) . '>';
			foreach ( $items as $index => $item ) {
				$is_first    = ( 0 === $index );
				$slide_label = str_replace( array( '[number]', '[count]' ), array( (string) ( $index + 1 ), (string) $count ), $texts['slide'] );
				echo '<div class="wpec-gallery__slide' . ( $is_first ? ' is-active' : '' ) . '" data-index="' . esc_attr( $index ) . '" data-type="' . esc_attr( $item['type'] ) . '" data-full="' . esc_url( $item['full'] ) . '"' . ( ( 'image' !== $item['type'] ) ? ' data-video="' . esc_url( $item['video'] ) . '"' : '' ) . ' role="group" aria-roledescription="slide" aria-label="' . esc_attr( $slide_label ) . '">';
				if ( 'video' === $item['type'] ) {
					echo '<video class="wpec-gallery__video" controls playsinline' . ( $muted ? ' muted loop preload="metadata"' : ' preload="none"' ) . ( ( '' !== $item['src'] ) ? ' poster="' . esc_url( $item['src'] ) . '"' : '' ) . '><source src="' . esc_url( $item['video'] ) . '" /></video>';
				} elseif ( 'embed' === $item['type'] ) {
					echo '<button type="button" class="wpec-gallery__play" data-embed="' . esc_url( $item['video'] ) . '" aria-label="' . esc_attr( $texts['play'] ) . '">';
					echo '<img class="wpec-gallery__img" src="' . esc_url( $item['src'] ) . '" alt="' . esc_attr( $item['alt'] ) . '" loading="lazy" decoding="async" />';
					echo '<span class="wpec-gallery__play-icon" aria-hidden="true"></span></button>';
				} else {
					$attrs  = ' src="' . esc_url( $item['src'] ) . '" alt="' . esc_attr( $item['alt'] ) . '" decoding="async"';
					$attrs .= ( '' !== $item['srcset'] ) ? ' srcset="' . esc_attr( $item['srcset'] ) . '" sizes="' . esc_attr( $sizes ) . '"' : '';
					$attrs .= ( $item['width'] > 0 && $item['height'] > 0 ) ? ' width="' . esc_attr( $item['width'] ) . '" height="' . esc_attr( $item['height'] ) . '"' : '';
					$attrs .= ( $eager && $is_first ) ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"';
					if ( $lightbox ) {
						echo '<button type="button" class="wpec-gallery__open" aria-label="' . esc_attr( $texts['open'] ) . '"><img class="wpec-gallery__img"' . $attrs . ' /></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above.
					} else {
						echo '<img class="wpec-gallery__img"' . $attrs . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above.
					}
				}
				echo '</div>';
			}
			echo '</div>';
			if ( $count > 1 ) {
				echo '<button type="button" class="wpec-gallery__nav wpec-gallery__nav--prev" aria-label="' . esc_attr( $texts['previous'] ) . '">' . $this->nav_icon( isset( $icons['prev'] ) ? $icons['prev'] : '' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's icon markup.
				echo '<button type="button" class="wpec-gallery__nav wpec-gallery__nav--next" aria-label="' . esc_attr( $texts['next'] ) . '">' . $this->nav_icon( isset( $icons['next'] ) ? $icons['next'] : '' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's icon markup.
				echo '<div class="wpec-gallery__dots" aria-hidden="true">';
				for ( $i = 0; $i < $count; $i++ ) {
					echo '<span class="wpec-gallery__dot' . ( ( 0 === $i ) ? ' is-active' : '' ) . '" data-index="' . esc_attr( $i ) . '"></span>';
				}
				echo '</div>';
				if ( $autoplay ) {
					echo '<button type="button" class="wpec-gallery__autoplay" aria-label="' . esc_attr( $texts['pause'] ) . '" aria-pressed="false"><span class="wpec-gallery__autoplay-icon" aria-hidden="true"></span></button>';
				}
			}
			if ( ! empty( $icons['expand'] ) ) {
				echo '<button type="button" class="wpec-gallery__expand" aria-label="' . esc_attr( $texts['open'] ) . '"><span class="wpec-gallery__expand-icon" aria-hidden="true">' . $icons['expand'] . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's icon markup, or a fixed SVG.
			}
			echo '</div>';
			if ( $count > 1 ) {
				$arrows = ( isset( $options['data-wpec-thumbs-nav'] ) && 'arrows' === $options['data-wpec-thumbs-nav'] );
				if ( $arrows ) {
					echo '<div class="wpec-gallery__thumbs-wrap">';
					echo '<button type="button" class="wpec-gallery__thumbs-nav wpec-gallery__thumbs-nav--prev" aria-label="' . esc_attr( $texts['thumbs_previous'] ) . '" hidden><span aria-hidden="true"></span></button>';
				}
				echo '<div class="wpec-gallery__thumbs">';
				foreach ( $items as $index => $item ) {
					$thumb_label = str_replace( '[number]', (string) ( $index + 1 ), $texts['thumbnail'] );
					echo '<button type="button" class="wpec-gallery__thumb' . ( ( 0 === $index ) ? ' is-active' : '' ) . ( ( 'image' !== $item['type'] ) ? ' is-video' : '' ) . '" data-index="' . esc_attr( $index ) . '" aria-label="' . esc_attr( $thumb_label ) . '"' . ( ( 0 === $index ) ? ' aria-current="true"' : '' ) . '>';
					echo '<img src="' . esc_url( $item['thumb'] ) . '"' . ( ( '' !== $item['thumb_srcset'] ) ? ' srcset="' . esc_attr( $item['thumb_srcset'] ) . '" sizes="' . esc_attr( '160px' ) . '"' : '' ) . ' alt="" loading="lazy" decoding="async" />';
					echo '</button>';
				}
				echo '</div>';
				if ( $arrows ) {
					echo '<button type="button" class="wpec-gallery__thumbs-nav wpec-gallery__thumbs-nav--next" aria-label="' . esc_attr( $texts['thumbs_next'] ) . '" hidden><span aria-hidden="true"></span></button>';
					echo '</div>';
				}
			}
			echo '</div>';
		}

		/**
		 * The inside of an arrow button: the widget's icon, else the drawn chevron.
		 *
		 * @param string $icon Icon markup.
		 * @return string
		 */
		private function nav_icon( $icon ) {
			return ( '' !== $icon ) ? '<span class="wpec-gallery__nav-icon" aria-hidden="true">' . $icon . '</span>' : '<span aria-hidden="true"></span>';
		}

		/**
		 * The equivalent shortcode for post_content.
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return $this->pb_plain_shortcode( 'ec_product_details_images', $this->get_settings_for_display() );
		}
	}

endif;
