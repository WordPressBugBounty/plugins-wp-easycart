<?php
/**
 * My Account ( 6.0.2 ): the whole account area in one widget.
 *
 * Visitors get the sign-in form ( and sign-up, lost password and the emailed reset link, and a guest's order from its
 * email link ). Signed-in customers get a menu ( left, top, or none when an Account Navigation widget sits elsewhere ) and
 * the view it points to: dashboard, orders and order details, downloads, subscriptions and their details, addresses,
 * account details, payment methods. Every link stays on this page ( ?ec_page= ). With no setup it is the complete account
 * area, drawn by the store's own account templates.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_My_Account_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) ) :

	/**
	 * My Account widget.
	 */
	class WP_EasyCart_Elementor_My_Account_Widget extends WP_EasyCart_Elementor_Account_Widget {

		/**
		 * Help topic: the account guide's My Account step ( the part widgets link its "build your own layout" step ).
		 *
		 * @var string
		 */
		protected static $ec_help = 'my-account';

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_my_account';
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public function get_title() {
			return esc_html__( 'My Account', 'wp-easycart' );
		}

		/**
		 * Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			return 'eicon-my-account';
		}

		/**
		 * Keywords.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'account', 'my account', 'customer', 'login', 'sign in', 'register', 'orders', 'dashboard', 'profile', 'woocommerce', 'woocommerce my account' );
		}

		/**
		 * Scripts: the widget's own, reCAPTCHA ( sign-in and sign-up ) and Stripe ( a subscription's card ), each only
		 * where the store registered it.
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_merge( parent::get_script_depends(), array( 'wpeasycart_google_recaptcha_js', 'wpeasycart_stripe_js' ) );
		}

		/**
		 * Post content: the account shortcode ( search, SEO plugins, a site without Elementor ).
		 *
		 * @return string
		 */
		protected function plain_content_shortcode() {
			return '[ec_account]';
		}

		/**
		 * Views customers see ( editor preview choices ).
		 *
		 * @return array
		 */
		private function preview_views() {
			return array(
				'dashboard'            => esc_html__( 'Dashboard', 'wp-easycart' ),
				'orders'               => esc_html__( 'Orders', 'wp-easycart' ),
				'order_details'        => esc_html__( 'Order details', 'wp-easycart' ),
				'downloads'            => esc_html__( 'Downloads', 'wp-easycart' ),
				'subscriptions'        => esc_html__( 'Subscriptions', 'wp-easycart' ),
				'subscription_details' => esc_html__( 'Subscription details', 'wp-easycart' ),
				'addresses'            => esc_html__( 'Addresses', 'wp-easycart' ),
				'details'              => esc_html__( 'Account details', 'wp-easycart' ),
				'payment_methods'      => esc_html__( 'Payment methods', 'wp-easycart' ),
				'login'                => esc_html__( 'Visitor: sign in', 'wp-easycart' ),
				'register'             => esc_html__( 'Visitor: create an account', 'wp-easycart' ),
				'forgot_password'      => esc_html__( 'Visitor: lost password', 'wp-easycart' ),
			);
		}

		/**
		 * Content controls.
		 */
		protected function ec_content_controls() {
			$this->start_controls_section(
				'section_menu',
				array(
					'label' => esc_html__( 'Account menu', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'menu_position',
				array(
					'label'   => esc_html__( 'Menu', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'left',
					'options' => array(
						'left' => esc_html__( 'Beside the content', 'wp-easycart' ),
						'top'  => esc_html__( 'Above the content', 'wp-easycart' ),
						'none' => esc_html__( 'Hidden ( use the Account Navigation widget )', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'menu_dropdown',
				array(
					'label'        => esc_html__( 'Menu as a dropdown on phones', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
					'condition'    => array( 'menu_position!' => 'none' ),
				)
			);
			$this->add_control(
				'menu_items_heading',
				array(
					'label'     => esc_html__( 'Pages in the account', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_menu_item_controls();
			$this->ec_visibility_control();
			$this->end_controls_section();

			$this->start_controls_section(
				'section_visitors',
				array(
					'label' => esc_html__( 'Visitors', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_register_box',
				array(
					'label'        => esc_html__( 'Show the new customer box beside the sign-in form', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
				)
			);
			$this->ec_login_redirect_controls();
			$this->ec_login_field_controls( false );
			$this->end_controls_section();

			$this->start_controls_section(
				'section_orders',
				array(
					'label' => esc_html__( 'Orders', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->ec_order_column_controls();
			$this->ec_order_details_content_controls();
			$this->end_controls_section();

			$this->start_controls_section(
				'section_preview',
				array(
					'label' => esc_html__( 'Editor preview', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'preview_view',
				array(
					'label'       => esc_html__( 'Show in the editor', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'dashboard',
					'options'     => $this->preview_views(),
					'description' => esc_html__( 'Only changes what you see while editing, so you can style every page of the account. Customers see the page they open.', 'wp-easycart' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Wording this widget can replace ( Content › Texts ): the sign-in, sign-up and lost password pages.
		 *
		 * @return array
		 */
		protected function ec_text_fields() {
			return array_merge( $this->ec_login_text_fields( true ), $this->ec_register_text_fields(), $this->ec_lost_password_text_fields() );
		}

		/**
		 * Placeholders: setting => field id.
		 *
		 * @return array
		 */
		protected function ec_placeholder_fields() {
			return array(
				'login_email_placeholder'    => 'ec_account_login_email',
				'login_password_placeholder' => 'ec_account_login_password',
			);
		}

		/**
		 * Style sections of the menu and of every page in the account.
		 */
		protected function ec_widget_style_controls() {
			$this->ec_menu_style_controls( true );
			$this->ec_content_area_style_controls();
			$this->ec_login_boxes_style_controls();
			$this->ec_order_list_style_controls();
			$this->ec_order_details_style_controls();
			$this->ec_subscription_style_controls();
			$this->ec_columns_style_controls();
			$this->ec_downloads_style_controls();
			$this->ec_payment_style_controls();
		}

		/**
		 * Wrapper classes.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_classes( $settings ) {
			$position = isset( $settings['menu_position'] ) && in_array( $settings['menu_position'], array( 'left', 'top', 'none' ), true ) ? $settings['menu_position'] : 'left';
			$classes  = array_merge( array( 'wpec-acc--menu-' . $position ), parent::ec_classes( $settings ) );
			if ( isset( $settings['show_register_box'] ) && 'yes' !== $settings['show_register_box'] ) {
				$classes[] = 'wpec-acc--no-register-box';
			}
			return $classes;
		}

		/**
		 * Removes the templates' menus, and the new customer box when it is off.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_strip( $settings ) {
			$rules = parent::ec_strip( $settings );
			if ( isset( $settings['show_register_box'] ) && 'yes' !== $settings['show_register_box'] ) {
				$rules[] = array( 'has' => array( 'ec_account_right', 'ec_account_login' ) );
			}
			return $rules;
		}

		/**
		 * Render arguments: every view, messages printed in the content area, the editor note.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_render_args( $settings ) {
			return array(
				'marker'   => array( 'all' ),
				'messages' => false,
				'note'     => __( 'Visitors see the sign-in form; signed-in customers see their account.', 'wp-easycart' ),
			);
		}

		/**
		 * The editor previews a signed-in view unless a visitor view is chosen; the storefront follows the shopper.
		 *
		 * @param array $settings Settings.
		 * @return bool
		 */
		protected function ec_is_customer_view( $settings ) {
			if ( WP_EasyCart_Elementor_Account::is_editor() ) {
				return ! in_array( $this->preview_choice( $settings ), array( 'login', 'register', 'forgot_password' ), true );
			}
			return WP_EasyCart_Elementor_Account::signed_in();
		}

		/**
		 * The editor preview choice.
		 *
		 * @param array $settings Settings.
		 * @return string
		 */
		private function preview_choice( $settings ) {
			$choice = isset( $settings['preview_view'] ) ? (string) $settings['preview_view'] : 'dashboard';
			return array_key_exists( $choice, $this->preview_views() ) ? $choice : 'dashboard';
		}

		/**
		 * Whether a menu item is on.
		 *
		 * @param array  $settings Settings.
		 * @param string $key      Item.
		 * @return bool
		 */
		private function item_on( $settings, $key ) {
			if ( 'downloads' === $key && ! WP_EasyCart_Elementor_Account::sells_downloads() ) {
				return false;
			}
			if ( ( 'subscriptions' === $key || 'payment_methods' === $key ) && ! WP_EasyCart_Elementor_Account::uses_subscriptions() ) {
				return false;
			}
			return ! isset( $settings[ 'show_' . $key ] ) || 'yes' === $settings[ 'show_' . $key ];
		}

		/**
		 * The view to draw for a signed-in customer.
		 *
		 * @param array $settings Settings.
		 * @param array $context  Render context.
		 * @return string
		 */
		private function customer_view( $settings, $context ) {
			$requested = $context['editor'] ? $this->preview_choice( $settings ) : WP_EasyCart_Elementor_Account_Views::requested_view();
			$map       = array(
				'dashboard'            => 'dashboard',
				'orders'               => 'orders',
				'order_details'        => 'order_details',
				'downloads'            => 'downloads',
				'subscriptions'        => 'subscriptions',
				'subscription_details' => 'subscription_details',
				'addresses'            => 'addresses',
				'billing_information'  => 'addresses',
				'shipping_information' => 'addresses',
				'details'              => 'details',
				'personal_information' => 'details',
				'password'             => 'details',
				'payment_methods'      => 'payment_methods',
				'reset_password'       => 'reset_password',
			);
			$view      = isset( $map[ $requested ] ) ? $map[ $requested ] : 'dashboard';
			$item      = ( 'reset_password' === $view ) ? '' : self::menu_key_for( 'addresses' === $view ? 'billing_information' : ( 'details' === $view ? 'personal_information' : $view ) );
			if ( '' !== $item && 'dashboard' !== $item && ! $this->item_on( $settings, $item ) ) {
				$view = 'dashboard';
			}
			return $view;
		}

		/**
		 * The view to draw for a visitor.
		 *
		 * @param array                              $settings Settings.
		 * @param array                              $context  Render context.
		 * @param WP_EasyCart_Elementor_Account_Page $page     Account page.
		 * @return string
		 */
		private function visitor_view( $settings, $context, $page ) {
			$requested = $context['editor'] ? $this->preview_choice( $settings ) : WP_EasyCart_Elementor_Account_Views::requested_view();
			if ( in_array( $requested, array( 'register', 'forgot_password', 'reset_password' ), true ) ) {
				return $requested;
			}
			/* A guest's order ( its emailed link, or this visit's guest checkout ): ec_accountpage matched it to the guest key. */
			if ( 'order_details' === $requested && ! $context['editor'] && $page->order ) {
				return 'order_details';
			}
			return 'login';
		}

		/**
		 * Draws the account.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context.
		 * @return bool
		 */
		public function ec_render_body( $page, $context ) {
			$settings = $context['settings'];
			$views    = array( 'all' );
			if ( ! $context['customer'] ) {
				$view = $this->visitor_view( $settings, $context, $page );
				echo '<div class="wpec-acc__content wpec-acc__content--' . esc_attr( str_replace( '_', '-', $view ) ) . '">';
				WP_EasyCart_Elementor_Account::print_account_top( $context );
				WP_EasyCart_Elementor_Account::print_message( $page, $views );
				if ( 'register' === $view ) {
					WP_EasyCart_Elementor_Account_Views::register( $page );
				} elseif ( 'forgot_password' === $view || 'reset_password' === $view ) {
					WP_EasyCart_Elementor_Account_Views::lost_password( $page, 'reset_password' === $view );
				} elseif ( 'order_details' === $view ) {
					$page->wpec_display_order_details( $page->order );
				} else {
					WP_EasyCart_Elementor_Account_Views::login( $page, $settings );
				}
				echo '</div>';
				return true;
			}

			$view     = $this->customer_view( $settings, $context );
			$position = ( isset( $settings['menu_position'] ) && in_array( $settings['menu_position'], array( 'left', 'top', 'none' ), true ) ) ? $settings['menu_position'] : 'left';
			$active   = self::menu_key_for( 'addresses' === $view ? 'billing_information' : ( 'details' === $view ? 'personal_information' : $view ) );

			echo '<div class="wpec-acc__layout">';
			if ( 'none' !== $position ) {
				self::print_menu( self::menu_links( $settings, $views, $active ), 'top' === $position ? 'horizontal' : 'vertical', isset( $settings['menu_dropdown'] ) && 'yes' === $settings['menu_dropdown'] );
			}
			echo '<div class="wpec-acc__content wpec-acc__content--' . esc_attr( str_replace( '_', '-', $view ) ) . '">';
			WP_EasyCart_Elementor_Account::print_account_top( $context );
			WP_EasyCart_Elementor_Account::print_message( $page, $views );
			switch ( $view ) {
				case 'orders':
					$page->display_orders_page();
					break;
				case 'order_details':
					WP_EasyCart_Elementor_Account_Views::order_details( $page, $page->wpec_details_order( $context['editor'] ), $views );
					break;
				case 'downloads':
					WP_EasyCart_Elementor_Account_Views::downloads( $page, true );
					break;
				case 'subscriptions':
					$page->display_subscriptions_page();
					break;
				case 'subscription_details':
					$page->wpec_display_subscription_details( $page->wpec_details_subscription() );
					break;
				case 'addresses':
					WP_EasyCart_Elementor_Account_Views::addresses( $page, 'both' );
					break;
				case 'details':
					WP_EasyCart_Elementor_Account_Views::details( $page, 'both' );
					break;
				case 'payment_methods':
					WP_EasyCart_Elementor_Account_Views::payment_methods( $page, true );
					break;
				case 'reset_password':
					WP_EasyCart_Elementor_Account_Views::lost_password( $page, true );
					break;
				default:
					$page->display_dashboard_page();
			}
			echo '</div>';
			echo '</div>';
			return true;
		}
	}

endif;
