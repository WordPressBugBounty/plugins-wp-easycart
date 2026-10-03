<?php
/**
 * Logout ( 6.0.2 ): a sign-out button for signed-in customers ( headers, account sidebars ). Afterwards the customer lands
 * on the sign-in page, this page or the home page.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Logout_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * Logout widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Logout_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account_logout';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'Logout', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-sign-out';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'logout', 'log out', 'sign out', 'account', 'my account', 'woocommerce' );
		}

		/**
		 * Style sections: the box around the button ( round 11 ) and the button.
		 *
		 * @return array
		 */
		protected function ec_style_parts() {
			return array( 'box', 'buttons' );
		}

		/**
		 * No secondary buttons here.
		 *
		 * @return bool
		 */
		protected function ec_has_secondary_buttons() {
			return false;
		}

		/**
		 * Style section of the button's icon.
		 */
		protected function ec_widget_style_controls() {
			$this->start_controls_section(
				'section_style_icon',
				array(
					'label'     => esc_html__( 'Icon', 'wp-easycart' ),
					'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
					'condition' => array( 'selected_icon[value]!' => '' ),
				)
			);
			$this->ec_slider( 'icon_size', esc_html__( 'Size', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc .wpec-acc-logout__icon' => 'font-size: {{SIZE}}{{UNIT}};' ), 60, true );
			$this->ec_slider( 'icon_spacing', esc_html__( 'Space between icon and text', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc .wpec-acc-logout__button' => 'gap: {{SIZE}}{{UNIT}};' ), 40, true );
			$this->ec_color( 'icon_color', esc_html__( 'Colour', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc .wpec-acc-logout__icon' => 'color: {{VALUE}};' ) );
			$this->ec_color( 'icon_hover_color', esc_html__( 'Colour on hover', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc .wpec-acc-logout__button:hover .wpec-acc-logout__icon, {{WRAPPER}} .wpec-acc .wpec-acc-logout__button:focus-visible .wpec-acc-logout__icon' => 'color: {{VALUE}};' ) );
			$this->end_controls_section();
		}

		/**
		 * No customer data.
		 *
		 * @param array $settings Settings.
		 * @return bool
		 */
		protected function ec_is_customer_view( $settings ) {
			return false;
		}

		/**
		 * No messages, no forms.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_render_args( $settings ) {
			return array(
				'messages'   => false,
				'marker'     => array(),
				'needs_page' => false,
			);
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_content',
				array(
					'label' => esc_html__( 'Sign out', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'text',
				array(
					'label'       => esc_html__( 'Text', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => WP_EasyCart_Elementor_Account::text( 'nav_logout', 'Sign out' ),
					'description' => esc_html__( 'Leave empty to use the store\'s language file.', 'wp-easycart' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'selected_icon',
				array(
					'label'       => esc_html__( 'Icon', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::ICONS,
					'skin'        => 'inline',
					'label_block' => false,
				)
			);
			$this->add_control(
				'icon_position',
				array(
					'label'     => esc_html__( 'Icon position', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => 'before',
					'options'   => array(
						'before' => esc_html__( 'Before the text', 'wp-easycart' ),
						'after'  => esc_html__( 'After the text', 'wp-easycart' ),
					),
					'condition' => array( 'selected_icon[value]!' => '' ),
				)
			);
			$this->add_control(
				'redirect',
				array(
					'label'   => esc_html__( 'After signing out, go to', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => '',
					'options' => array(
						''     => esc_html__( 'The sign-in page', 'wp-easycart' ),
						'page' => esc_html__( 'This page', 'wp-easycart' ),
						'home' => esc_html__( 'The home page', 'wp-easycart' ),
					),
				)
			);
			$this->add_responsive_control(
				'align',
				array(
					'label'                => esc_html__( 'Alignment', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array(
						'left'    => array(
							'title' => esc_html__( 'Left', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center'  => array(
							'title' => esc_html__( 'Center', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-center',
						),
						'right'   => array(
							'title' => esc_html__( 'Right', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-right',
						),
						'justify' => array(
							'title' => esc_html__( 'Full width', 'wp-easycart' ),
							'icon'  => 'eicon-text-align-justify',
						),
					),
					'selectors_dictionary' => array(
						'left'    => 'flex-start',
						'center'  => 'center',
						'right'   => 'flex-end',
						'justify' => 'stretch',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-acc--logout' => '--wpec-acc-logout-align: {{VALUE}};' ),
				)
			);
			$this->ec_visibility_control();
			$this->end_controls_section();
		}

		/**
		 * Draws the button.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			$settings = $context['settings'];
			$text     = ( isset( $settings['text'] ) && is_string( $settings['text'] ) ) ? trim( $settings['text'] ) : '';
			if ( '' === $text ) {
				$text = WP_EasyCart_Elementor_Account::text( 'nav_logout', 'Sign out' );
			}
			$to       = ( isset( $settings['redirect'] ) && in_array( $settings['redirect'], array( 'page', 'home' ), true ) ) ? $settings['redirect'] : '';
			$icon     = ( isset( $settings['selected_icon'] ) && is_array( $settings['selected_icon'] ) && ! empty( $settings['selected_icon']['value'] ) ) ? $settings['selected_icon'] : null;
			$position = ( isset( $settings['icon_position'] ) && 'after' === $settings['icon_position'] ) ? 'after' : 'before';
			echo '<a class="wpec-acc-button wpec-acc-logout__button" href="' . esc_url( WP_EasyCart_Elementor_Account::logout_url( array(), $to ) ) . '">';
			if ( $icon && 'before' === $position ) {
				$this->print_icon( $icon );
			}
			echo '<span class="wpec-acc-logout__text">' . esc_html( $text ) . '</span>';
			if ( $icon && 'after' === $position ) {
				$this->print_icon( $icon );
			}
			echo '</a>';
			return true;
		}

		/**
		 * The button's icon.
		 *
		 * @param array $icon ICONS control value.
		 */
		private function print_icon( $icon ) {
			if ( ! class_exists( '\Elementor\Icons_Manager' ) ) {
				return;
			}
			echo '<span class="wpec-acc-logout__icon" aria-hidden="true">';
			\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			echo '</span>';
		}
	}

endif;
