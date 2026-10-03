<?php
/**
 * Base of the 6.0.2 account widgets ( My Account and its parts ).
 *
 * Every account widget: panel category Account, the Show to setting ( visibility ), the same Style sections in the same
 * order ( Box, Headings, Text, Fields, Buttons, Links, Accent, Messages ) and the render pipeline of
 * WP_EasyCart_Elementor_Account ( links on this page, no page cache, sample data in the editor, messages once ).
 *
 * Style controls set CSS variables on the widget ( account.css applies them, with the kit's colours and fonts as the
 * defaults ): EasyCart's own stylesheet forces its colours and fonts on the account templates with !important, so a
 * plain Elementor rule would lose. Font families go through a variable too ( fields_options on the typography groups );
 * sizes, weights and spacing are plain rules. Every control added in 6.0.2 bug round 11 leaves the widget as it was
 * until it is set ( no default value, or a CSS fallback equal to the old rule ).
 *
 * Texts ( round 11 ): the widgets that draw EasyCart's sign-in, sign-up and lost password forms can replace the store's
 * wording for their labels and buttons while they draw ( filter wp_easycart_language_text, see
 * WP_EasyCart_Elementor_Account::language_filter_ready() ). The text controls exist only when the store's language
 * class has that filter.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Widget' ) && class_exists( 'WP_EasyCart_Elementor_Widget_Base' ) ) :

	/**
	 * Account widget base.
	 */
	abstract class WP_EasyCart_Elementor_Account_Widget extends WP_EasyCart_Elementor_Widget_Base {

		/**
		 * Panel category.
		 *
		 * @var string
		 */
		protected static $ec_category = 'account';

		/**
		 * Help page.
		 *
		 * @var string
		 */
		protected static $ec_help = 'account';

		/**
		 * Draws the widget's body ( inside the wrapper ). Returns false when there is nothing to show.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page    Account page.
		 * @param array                              $context Render context ( editor, signed_in, sample, customer, settings, views ).
		 * @return bool|null
		 */
		abstract public function ec_render_body( $page, $context );

		/**
		 * Content controls.
		 */
		abstract protected function ec_content_controls();

		/**
		 * This widget's entry in WP_EasyCart_Elementor_Account::widgets().
		 *
		 * @param string $key class | file | views | audience.
		 * @return mixed
		 */
		protected function ec_def( $key ) {
			$widgets = WP_EasyCart_Elementor_Account::widgets();
			$name    = $this->get_name();
			return ( isset( $widgets[ $name ] ) && isset( $widgets[ $name ][ $key ] ) ) ? $widgets[ $name ][ $key ] : null;
		}

		/**
		 * Keywords merchants search for.
		 *
		 * @return array
		 */
		protected function ec_keywords() {
			return array( 'account', 'my account', 'customer', 'woocommerce' );
		}

		/**
		 * Stylesheets: the store's and the shared widget styles ( base class ), and the account widgets' ( registered by the
		 * module ).
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return array_merge( parent::get_style_depends(), array( 'wpeasycart-elementor-account' ) );
		}

		/**
		 * Scripts: the store's ( base class ) and the account widgets' ( registered by the module ).
		 *
		 * @return array
		 */
		public function get_script_depends() {
			return array_merge( parent::get_script_depends(), array( 'wpeasycart-elementor-account' ) );
		}

		/**
		 * The Style sections this widget has.
		 *
		 * @return array
		 */
		protected function ec_style_parts() {
			return array( 'box', 'headings', 'text', 'fields', 'buttons', 'links', 'accent', 'messages' );
		}

		/**
		 * Whether the widget draws secondary buttons ( Create account, Download, View order, back to the account ): the
		 * Buttons section then has their own colours.
		 *
		 * @return bool
		 */
		protected function ec_has_secondary_buttons() {
			return true;
		}

		/**
		 * Whether the widget draws a signed-in customer's data.
		 *
		 * @param array $settings Settings.
		 * @return bool
		 */
		protected function ec_is_customer_view( $settings ) {
			return 'logged_in' === $this->ec_def( 'audience' );
		}

		/**
		 * Blocks removed from the templates' markup: the account menu columns every signed-in template carries.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_strip( $settings ) {
			return array(
				array( 'has' => 'ec_account_mobile' ),
				array(
					'has' => 'ec_account_right',
					'not' => 'ec_account_login',
				),
			);
		}

		/**
		 * Extra wrapper classes.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_classes( $settings ) {
			$classes = array();
			if ( isset( $settings['show_title'] ) && 'yes' !== $settings['show_title'] ) {
				$classes[] = 'wpec-acc--no-title';
			}
			/* Round 11: switches the widgets share ( only a widget that has the control has the setting ). */
			$off = array(
				'show_labels'       => 'wpec-acc--no-labels',
				'show_order_date'   => 'wpec-acc--no-order-date',
				'show_order_total'  => 'wpec-acc--no-order-total',
				'show_order_status' => 'wpec-acc--no-order-status',
				'show_order_button' => 'wpec-acc--no-order-button',
				'show_order_print'  => 'wpec-acc--no-order-print',
				'show_order_notes'  => 'wpec-acc--no-order-notes',
			);
			foreach ( $off as $key => $class ) {
				if ( isset( $settings[ $key ] ) && 'yes' !== $settings[ $key ] ) {
					$classes[] = $class;
				}
			}
			if ( isset( $settings['menu_indicator'] ) && in_array( $settings['menu_indicator'], array( 'bar', 'underline' ), true ) ) {
				$classes[] = 'wpec-acc--nav-' . $settings['menu_indicator'];
			}
			if ( isset( $settings['columns_layout'] ) && 'stack' === $settings['columns_layout'] ) {
				$classes[] = 'wpec-acc--stack-columns';
			}
			return $classes;
		}

		/**
		 * Other render() arguments ( marker, messages, note, empty_note ).
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_render_args( $settings ) {
			$views = (array) $this->ec_def( 'views' );
			if ( 'logged_in' === $this->ec_def( 'audience' ) && ! empty( $views ) ) {
				/* Signed-in forms redirect to the dashboard with their message: that comes back here too. */
				$views[] = 'dashboard';
			}
			return array(
				'marker'     => $views,
				'empty_note' => $this->ec_empty_note(),
			);
		}

		/**
		 * Editor note when the widget has nothing to show ( '' = the general one ).
		 *
		 * @return string
		 */
		protected function ec_empty_note() {
			return '';
		}

		/**
		 * Controls.
		 */
		protected function register_controls() {
			$this->ec_content_controls();
			$this->ec_text_controls();
			$this->ec_widget_style_controls();
			$this->ec_shared_style_controls( $this->ec_style_parts() );
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Texts ( round 11 ).
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * The store's wording this widget can replace: control ID => array( section, key, label, fallback, condition ).
		 *
		 * @return array
		 */
		protected function ec_text_fields() {
			return array();
		}

		/**
		 * The sign-in form's wording ( Customer Login and My Account ).
		 *
		 * @param bool $with_box Include the new customer box.
		 * @return array
		 */
		protected function ec_login_text_fields( $with_box ) {
			$fields = array(
				'text_login_title'          => array( 'account_login', 'account_login_title', esc_html__( 'Title', 'wp-easycart' ), 'Returning Customer', array() ),
				'text_login_subtitle'       => array( 'account_login', 'account_login_sub_title', esc_html__( 'Text under the title', 'wp-easycart' ), 'Sign in below to access your existing account.', array() ),
				'text_login_email_label'    => array( 'account_login', 'account_login_email_label', esc_html__( 'Email label', 'wp-easycart' ), 'Email Address', array() ),
				'text_login_password_label' => array( 'account_login', 'account_login_password_label', esc_html__( 'Password label', 'wp-easycart' ), 'Password', array() ),
				'text_login_button'         => array( 'account_login', 'account_login_button', esc_html__( 'Sign-in button', 'wp-easycart' ), 'SIGN IN', array() ),
				'text_login_forgot'         => array( 'account_login', 'account_login_forgot_password_link', esc_html__( '"Forgot your password?" link', 'wp-easycart' ), 'Forgot Your Password?', array() ),
			);
			if ( $with_box ) {
				$box                              = array( 'show_register_box' => 'yes' );
				$fields['text_new_user_title']    = array( 'account_login', 'account_new_user_title', esc_html__( 'New customer box: title', 'wp-easycart' ), 'New User', $box );
				$fields['text_new_user_subtitle'] = array( 'account_login', 'account_new_user_sub_title', esc_html__( 'New customer box: text under the title', 'wp-easycart' ), 'Not registered? Click the button below', $box );
				$fields['text_new_user_message']  = array( 'account_login', 'account_new_user_message', esc_html__( 'New customer box: message', 'wp-easycart' ), 'No account? Create an account to take full advantage of this website.', $box );
				$fields['text_new_user_button']   = array( 'account_login', 'account_new_user_button', esc_html__( 'Create account button', 'wp-easycart' ), 'CREATE ACCOUNT', $box );
			}
			return $fields;
		}

		/**
		 * The registration form's wording.
		 *
		 * @return array
		 */
		protected function ec_register_text_fields() {
			return array(
				'text_register_title'  => array( 'account_register', 'account_register_title', esc_html__( 'Registration: title', 'wp-easycart' ), 'Create an Account', array() ),
				'text_register_button' => array( 'account_register', 'account_register_button', esc_html__( 'Registration: button', 'wp-easycart' ), 'REGISTER', array() ),
			);
		}

		/**
		 * The lost password and new password forms' wording.
		 *
		 * @return array
		 */
		protected function ec_lost_password_text_fields() {
			return array(
				'text_forgot_title'       => array( 'account_forgot_password', 'account_forgot_password_title', esc_html__( 'Lost password: title', 'wp-easycart' ), 'Retrieve Your Password', array() ),
				'text_forgot_email_label' => array( 'account_forgot_password', 'account_forgot_password_email_label', esc_html__( 'Lost password: email label', 'wp-easycart' ), 'Email', array() ),
				'text_forgot_button'      => array( 'account_forgot_password', 'account_forgot_password_button', esc_html__( 'Lost password: button', 'wp-easycart' ), 'RETRIEVE PASSWORD', array() ),
				'text_reset_title'        => array( 'account_reset_password', 'account_reset_password_title', esc_html__( 'New password: title', 'wp-easycart' ), 'Choose a New Password', array() ),
				'text_reset_button'       => array( 'account_reset_password', 'account_reset_password_button', esc_html__( 'New password: button', 'wp-easycart' ), 'Reset Password', array() ),
			);
		}

		/**
		 * The Texts section ( Content tab ), only when the store's language class lets widgets replace its wording.
		 */
		protected function ec_text_controls() {
			$fields = $this->ec_text_fields();
			if ( empty( $fields ) || ! class_exists( 'WP_EasyCart_Elementor_Account' ) || ! WP_EasyCart_Elementor_Account::language_filter_ready() ) {
				return;
			}
			$this->start_controls_section(
				'section_texts',
				array(
					'label' => esc_html__( 'Texts', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'texts_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Replace the store\'s wording in this widget only. Leave a text empty to keep the wording from your store\'s language file.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			foreach ( $fields as $id => $field ) {
				$current = WP_EasyCart_Elementor_Account::store_text( $field[0], $field[1], $field[3] );
				$args    = array(
					'label'       => $field[2],
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => html_entity_decode( wp_strip_all_tags( $current ), ENT_QUOTES, 'UTF-8' ),
					'label_block' => true,
					'ai'          => array( 'active' => false ),
				);
				if ( ! empty( $field[4] ) ) {
					$args['condition'] = $field[4];
				}
				$this->add_control( $id, $args );
			}
			$this->end_controls_section();
		}

		/**
		 * The wording this widget replaces while it draws: 'section|key' => text.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_text_overrides( $settings ) {
			$texts = array();
			foreach ( $this->ec_text_fields() as $id => $field ) {
				if ( isset( $settings[ $id ] ) && is_string( $settings[ $id ] ) && '' !== trim( $settings[ $id ] ) ) {
					$texts[ $field[0] . '|' . $field[1] ] = trim( $settings[ $id ] );
				}
			}
			return $texts;
		}

		/**
		 * Placeholder texts for the store's fields: setting ID => field id.
		 *
		 * @return array
		 */
		protected function ec_placeholder_fields() {
			return array();
		}

		/**
		 * The placeholders to add while the widget draws: field id => text.
		 *
		 * @param array $settings Settings.
		 * @return array
		 */
		protected function ec_placeholders( $settings ) {
			$out = array();
			foreach ( $this->ec_placeholder_fields() as $id => $field ) {
				if ( isset( $settings[ $id ] ) && is_string( $settings[ $id ] ) && '' !== trim( $settings[ $id ] ) ) {
					$out[ $field ] = trim( $settings[ $id ] );
				}
			}
			return $out;
		}

		/**
		 * The sign-in form's placeholder controls ( IDs login_email_placeholder, login_password_placeholder ) and the
		 * Show the labels switch ( show_labels ).
		 *
		 * @param bool $with_labels Include the labels switch.
		 */
		protected function ec_login_field_controls( $with_labels ) {
			if ( $with_labels ) {
				$this->ec_labels_control();
			}
			$this->add_control(
				'login_email_placeholder',
				array(
					'label'       => esc_html__( 'Email placeholder', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'description' => esc_html__( 'Shown inside the empty email field. Leave empty for none.', 'wp-easycart' ),
					'ai'          => array( 'active' => false ),
				)
			);
			$this->add_control(
				'login_password_placeholder',
				array(
					'label'   => esc_html__( 'Password placeholder', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::TEXT,
					'default' => '',
					'ai'      => array( 'active' => false ),
				)
			);
		}

		/**
		 * The Show the labels switch ( show_labels ).
		 */
		protected function ec_labels_control() {
			$this->add_control(
				'show_labels',
				array(
					'label'        => esc_html__( 'Show the field labels', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
					'description'  => esc_html__( 'Off: the fields you give a placeholder show it instead of their label. Screen readers still read the labels.', 'wp-easycart' ),
				)
			);
		}

		/**
		 * Style sections of the widget's own elements ( before the shared ones ).
		 */
		protected function ec_widget_style_controls() {}

		/**
		 * Draws the widget.
		 */
		protected function render() {
			if ( ! class_exists( 'WP_EasyCart_Elementor_Account' ) ) {
				return;
			}
			$settings = $this->get_settings_for_display();
			$args     = array_merge(
				array(
					'name'         => $this->get_name(),
					'settings'     => $settings,
					'audience'     => $this->ec_def( 'audience' ),
					'views'        => (array) $this->ec_def( 'views' ),
					'customer'     => $this->ec_is_customer_view( $settings ),
					'strip'        => $this->ec_strip( $settings ),
					'classes'      => $this->ec_classes( $settings ),
					'texts'        => $this->ec_text_overrides( $settings ),
					'placeholders' => $this->ec_placeholders( $settings ),
					'render'       => array( $this, 'ec_render_body' ),
				),
				$this->ec_render_args( $settings )
			);
			WP_EasyCart_Elementor_Account::render( $args );
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Shared controls.
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * The Show to control ( ID visibility ). Default: the widget's audience.
		 */
		protected function ec_visibility_control() {
			$this->add_control(
				'visibility',
				array(
					'label'       => esc_html__( 'Show to', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => $this->ec_def( 'audience' ),
					'options'     => array(
						'always'     => esc_html__( 'Everyone', 'wp-easycart' ),
						'logged_in'  => esc_html__( 'Signed-in customers', 'wp-easycart' ),
						'logged_out' => esc_html__( 'Visitors who are not signed in', 'wp-easycart' ),
					),
					'separator'   => 'before',
					'description' => esc_html__( 'The editor always shows it, with a note saying who sees it.', 'wp-easycart' ),
				)
			);
		}

		/**
		 * A "Show the title" switcher ( ID show_title ).
		 *
		 * @param string $label Label.
		 */
		protected function ec_title_control( $label = '' ) {
			$this->add_control(
				'show_title',
				array(
					'label'        => ( '' !== $label ) ? $label : esc_html__( 'Show the title', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
				)
			);
		}

		/**
		 * After signing in ( IDs login_redirect, login_redirect_url ).
		 */
		protected function ec_login_redirect_controls() {
			$this->add_control(
				'login_redirect',
				array(
					'label'       => esc_html__( 'After signing in, go to', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'account',
					'options'     => array(
						'account' => esc_html__( 'Their account', 'wp-easycart' ),
						'page'    => esc_html__( 'This page', 'wp-easycart' ),
						'custom'  => esc_html__( 'A page I choose', 'wp-easycart' ),
					),
					'description' => esc_html__( 'Their account: this page when it shows the account, otherwise your store\'s account page.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'login_redirect_url',
				array(
					'label'       => esc_html__( 'Page address', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::URL,
					'placeholder' => home_url( '/' ),
					'options'     => false,
					'description' => esc_html__( 'A page on this site. Addresses on other sites are ignored.', 'wp-easycart' ),
					'condition'   => array( 'login_redirect' => 'custom' ),
				)
			);
		}

		/**
		 * Global colour / typography default argument when the kit classes exist.
		 *
		 * @param string $kind  typography | color.
		 * @param string $which PRIMARY | SECONDARY | TEXT | ACCENT.
		 * @return array
		 */
		protected function ec_global( $kind, $which ) {
			$class = ( 'typography' === $kind ) ? '\Elementor\Core\Kits\Documents\Tabs\Global_Typography' : '\Elementor\Core\Kits\Documents\Tabs\Global_Colors';
			$const = $class . '::' . ( ( 'typography' === $kind ) ? 'TYPOGRAPHY_' : 'COLOR_' ) . $which;
			if ( class_exists( $class ) && defined( $const ) ) {
				return array( 'default' => constant( $const ) );
			}
			return array();
		}

		/**
		 * Selector list under the widget.
		 *
		 * @param array $parts Selectors inside .wpec-acc.
		 * @return string
		 */
		protected function ec_sel( $parts ) {
			$out = array();
			foreach ( $parts as $part ) {
				$out[] = '{{WRAPPER}} .wpec-acc ' . $part;
			}
			return implode( ', ', $out );
		}

		/**
		 * Heading elements of the account templates.
		 *
		 * @return array
		 */
		public static function heading_parts() {
			return array( '.ec_cart_header', '.ec_account_subscription_v2_card_title', '.ec_account_order_notes h4', '.wpec-acc-title' );
		}

		/**
		 * Text elements of the account templates.
		 *
		 * @return array
		 */
		public static function text_parts() {
			return array( '.ec_cart_input_row', '.ec_account_subheader', '.ec_cart_error_row', '.ec_account_order_line_header > div', '.ec_account_order_line_0 > div', '.ec_account_order_line_1 > div', '.ec_account_order_header_column_left > span', '.ec_account_order_item_details > span', '.ec_account_order_details_table td', '.ec_account_order_details_table th', '.ec_account_download_line', '.ec_account_no_order_found', '.ec_account_subscription_card_body', '.ec_subscription_none_found', '.wpec-acc-empty', '.wpec-acc-downloads__item', '.wpec-acc-card', '.wpec-acc-signed-in' );
		}

		/**
		 * Buttons of the account templates ( and the module's own ).
		 *
		 * @return array
		 */
		public static function button_parts() {
			return array( '.ec_account_button', '.ec_account_login_create_account_button', '.ec_account_order_item_buy_button > a', '.ec_account_order_item_download_button', '.ec_account_dashboard_row_divider a', '.ec_account_order_line_column5 a', '.ec_account_complete_payment_button', '.ec_account_complete_order_link', '.ec_account_return_to_dashboard_button a', '.ec_account_subscription_card_action a', '.wpec-acc-button' );
		}

		/**
		 * Form fields.
		 *
		 * @return array
		 */
		public static function field_parts() {
			return array( 'input[type="text"]', 'input[type="email"]', 'input[type="password"]', 'input[type="tel"]', 'input[type="number"]', 'select', 'textarea' );
		}

		/**
		 * Secondary buttons: actions beside the main one ( round 11; they are also in button_parts() ).
		 *
		 * @return array
		 */
		public static function secondary_button_parts() {
			return array( '.ec_account_login_create_account_button', '.ec_account_order_item_download_button', '.ec_account_return_to_dashboard_button a', '.ec_account_dashboard_row_divider a', '.ec_account_order_line_column5 a' );
		}

		/**
		 * Text links of the account templates ( and the module's own ).
		 *
		 * @return array
		 */
		public static function link_parts() {
			return array( '.ec_cart_input_row > a', '.ec_cart_button_row a.ec_account_login_link', '.ec_account_dashboard_order_info_link', '.wpec-acc-link', '.ec_account_subscription_v2_back a', '.ec_account_order_details_item_display_title > a', '.ec_account_order_header_column_left > div > a' );
		}

		/**
		 * Selector list under the widget, each part with a suffix ( :hover, :focus, ::placeholder ).
		 *
		 * @param array  $parts  Selectors inside .wpec-acc.
		 * @param string $suffix Suffix.
		 * @return string
		 */
		protected function ec_sel_suffix( $parts, $suffix ) {
			$out = array();
			foreach ( $parts as $part ) {
				$out[] = '{{WRAPPER}} .wpec-acc ' . $part . $suffix;
			}
			return implode( ', ', $out );
		}

		/**
		 * A slider control.
		 *
		 * @param string $name       Control name.
		 * @param string $label      Label.
		 * @param array  $selectors  Selectors ( {{SIZE}}{{UNIT}} ).
		 * @param int    $max        Largest px value.
		 * @param bool   $responsive Per device.
		 * @param array  $extra      More arguments.
		 */
		protected function ec_slider( $name, $label, $selectors, $max = 40, $responsive = false, $extra = array() ) {
			$args = array_merge(
				array(
					'label'      => $label,
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => $max,
						),
					),
					'selectors'  => $selectors,
				),
				$extra
			);
			if ( $responsive ) {
				$this->add_responsive_control( $name, $args );
			} else {
				$this->add_control( $name, $args );
			}
		}

		/**
		 * A plain colour control ( its own selectors ).
		 *
		 * @param string $name      Control name.
		 * @param string $label     Label.
		 * @param array  $selectors Selectors ( {{VALUE}} ).
		 * @param array  $extra     More arguments.
		 */
		protected function ec_color( $name, $label, $selectors, $extra = array() ) {
			$this->add_control(
				$name,
				array_merge(
					array(
						'label'     => $label,
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => $selectors,
					),
					$extra
				)
			);
		}

		/**
		 * A padding ( or other four-sided ) control.
		 *
		 * @param string $name     Control name.
		 * @param string $label    Label.
		 * @param string $selector Selector.
		 * @param string $property CSS property.
		 * @param string $suffix   Appended to the value ( ' !important' ).
		 */
		protected function ec_dimensions( $name, $label, $selector, $property = 'padding', $suffix = '' ) {
			$this->add_responsive_control(
				$name,
				array(
					'label'      => $label,
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),
					'selectors'  => array( $selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' . $suffix . ';' ),
				)
			);
		}

		/**
		 * A box: background, border, corner radius, padding and shadow on one element ( IDs <prefix>_background,
		 * <prefix>_border, <prefix>_radius, <prefix>_padding, <prefix>_shadow ).
		 *
		 * @param string $prefix   ID prefix.
		 * @param string $selector Selector.
		 */
		protected function ec_box_controls( $prefix, $selector ) {
			$this->add_control(
				$prefix . '_background',
				array(
					'label'     => esc_html__( 'Background', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $selector => 'background-color: {{VALUE}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => $prefix . '_border',
					'selector' => $selector,
				)
			);
			$this->add_responsive_control(
				$prefix . '_radius',
				array(
					'label'      => esc_html__( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%', 'em' ),
					'selectors'  => array( $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->ec_dimensions( $prefix . '_padding', esc_html__( 'Padding', 'wp-easycart' ), $selector );
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => $prefix . '_shadow',
					'selector' => $selector,
				)
			);
		}

		/**
		 * Alignment choices ( left, center, right ).
		 *
		 * @return array
		 */
		protected function ec_align_options() {
			return array(
				'left'   => array(
					'title' => esc_html__( 'Left', 'wp-easycart' ),
					'icon'  => 'eicon-text-align-left',
				),
				'center' => array(
					'title' => esc_html__( 'Center', 'wp-easycart' ),
					'icon'  => 'eicon-text-align-center',
				),
				'right'  => array(
					'title' => esc_html__( 'Right', 'wp-easycart' ),
					'icon'  => 'eicon-text-align-right',
				),
			);
		}

		/**
		 * Typography group arguments whose font family goes through a CSS variable.
		 *
		 * @param string $name     Control name.
		 * @param string $label    Label.
		 * @param array  $parts    Selectors inside .wpec-acc.
		 * @param string $font_var Variable the font family sets.
		 * @param string $kit      Kit typography default: PRIMARY | TEXT | ACCENT | ''.
		 */
		protected function ec_typography( $name, $label, $parts, $font_var, $kit = '' ) {
			$args = array(
				'name'           => $name,
				'label'          => $label,
				'selector'       => $this->ec_sel( $parts ),
				'fields_options' => array(
					/* Both keys ( round 11 ): whichever one the Elementor version reads, the family reaches the variable. */
					'font_family' => array(
						'selector_value' => $font_var . ': "{{VALUE}}";',
						'selectors'      => array( '{{WRAPPER}}' => $font_var . ': "{{VALUE}}";' ),
					),
				),
			);
			if ( '' !== $kit ) {
				$args['global'] = $this->ec_global( 'typography', $kit );
			}
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), $args );
		}

		/**
		 * A colour control that sets a CSS variable on the widget.
		 *
		 * @param string $name     Control name.
		 * @param string $label    Label.
		 * @param string $variable CSS variable.
		 * @param string $extra    More declarations set with it.
		 * @param array  $args     More control arguments ( description ).
		 */
		protected function ec_color_var( $name, $label, $variable, $extra = '', $args = array() ) {
			$this->add_control(
				$name,
				array_merge(
					array(
						'label'     => $label,
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( '{{WRAPPER}}' => $variable . ': {{VALUE}};' . $extra ),
					),
					$args
				)
			);
		}

		/**
		 * The shared Style sections.
		 *
		 * @param array $parts box | headings | text | fields | buttons | links | accent | messages.
		 */
		protected function ec_shared_style_controls( $parts ) {
			$tab = \Elementor\Controls_Manager::TAB_STYLE;

			if ( in_array( 'box', $parts, true ) ) {
				$this->start_controls_section(
					'section_style_box',
					array(
						'label' => esc_html__( 'Box', 'wp-easycart' ),
						'tab'   => $tab,
					)
				);
				$this->add_control(
					'box_background',
					array(
						'label'     => esc_html__( 'Background', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::COLOR,
						'selectors' => array( '{{WRAPPER}} .wpec-acc' => 'background-color: {{VALUE}};' ),
					)
				);
				$this->add_group_control(
					\Elementor\Group_Control_Border::get_type(),
					array(
						'name'     => 'box_border',
						'selector' => '{{WRAPPER}} .wpec-acc',
					)
				);
				$this->add_responsive_control(
					'box_radius',
					array(
						'label'      => esc_html__( 'Corner radius', 'wp-easycart' ),
						'type'       => \Elementor\Controls_Manager::DIMENSIONS,
						'size_units' => array( 'px', '%', 'em' ),
						'selectors'  => array( '{{WRAPPER}} .wpec-acc' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
					)
				);
				$this->add_responsive_control(
					'box_padding',
					array(
						'label'      => esc_html__( 'Padding', 'wp-easycart' ),
						'type'       => \Elementor\Controls_Manager::DIMENSIONS,
						'size_units' => array( 'px', 'em', '%' ),
						'selectors'  => array( '{{WRAPPER}} .wpec-acc' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
					)
				);
				$this->add_group_control(
					\Elementor\Group_Control_Box_Shadow::get_type(),
					array(
						'name'     => 'box_shadow',
						'selector' => '{{WRAPPER}} .wpec-acc',
					)
				);
				$this->end_controls_section();
			}

			if ( in_array( 'headings', $parts, true ) ) {
				$this->start_controls_section(
					'section_style_headings',
					array(
						'label' => esc_html__( 'Headings', 'wp-easycart' ),
						'tab'   => $tab,
					)
				);
				$this->ec_color_var( 'heading_color', esc_html__( 'Color', 'wp-easycart' ), '--wpec-acc-heading-color' );
				$this->ec_typography( 'heading_typography', esc_html__( 'Typography', 'wp-easycart' ), self::heading_parts(), '--wpec-acc-heading-font', 'PRIMARY' );
				$this->ec_color_var( 'heading_divider_color', esc_html__( 'Line under headings', 'wp-easycart' ), '--wpec-acc-divider-color' );
				$this->ec_slider( 'heading_line_width', esc_html__( 'Line thickness', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc .ec_cart_header' => 'border-bottom-width: {{SIZE}}{{UNIT}};' ), 10 );
				$this->add_responsive_control(
					'heading_align',
					array(
						'label'     => esc_html__( 'Alignment', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::CHOOSE,
						'options'   => $this->ec_align_options(),
						'selectors' => array( $this->ec_sel( self::heading_parts() ) => 'text-align: {{VALUE}};' ),
					)
				);
				$this->ec_slider( 'heading_spacing', esc_html__( 'Space below', 'wp-easycart' ), array( $this->ec_sel( self::heading_parts() ) => 'margin-bottom: {{SIZE}}{{UNIT}};' ), 60, true );
				$this->end_controls_section();
			}

			if ( in_array( 'text', $parts, true ) ) {
				$this->start_controls_section(
					'section_style_text',
					array(
						'label' => esc_html__( 'Text', 'wp-easycart' ),
						'tab'   => $tab,
					)
				);
				$this->ec_color_var( 'text_color', esc_html__( 'Color', 'wp-easycart' ), '--wpec-acc-text-color' );
				$this->ec_color_var( 'text_muted_color', esc_html__( 'Secondary text', 'wp-easycart' ), '--wpec-acc-muted-color' );
				$this->ec_typography( 'text_typography', esc_html__( 'Typography', 'wp-easycart' ), self::text_parts(), '--wpec-acc-text-font', 'TEXT' );
				$this->end_controls_section();
			}

			if ( in_array( 'fields', $parts, true ) ) {
				$this->ec_field_style_section( $tab );
			}

			if ( in_array( 'buttons', $parts, true ) ) {
				$this->ec_button_style_section( $tab );
			}

			if ( in_array( 'links', $parts, true ) ) {
				$this->start_controls_section(
					'section_style_links',
					array(
						'label' => esc_html__( 'Links', 'wp-easycart' ),
						'tab'   => $tab,
					)
				);
				$decorations = array(
					''          => esc_html__( 'Default', 'wp-easycart' ),
					'none'      => esc_html__( 'None', 'wp-easycart' ),
					'underline' => esc_html__( 'Underline', 'wp-easycart' ),
				);
				$this->start_controls_tabs( 'tabs_link_style' );
				$this->start_controls_tab(
					'tab_link_normal',
					array( 'label' => esc_html__( 'Normal', 'wp-easycart' ) )
				);
				$this->ec_color_var( 'link_color', esc_html__( 'Color', 'wp-easycart' ), '--wpec-acc-link-color' );
				$this->add_control(
					'link_decoration',
					array(
						'label'     => esc_html__( 'Underline', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::SELECT,
						'default'   => '',
						'options'   => $decorations,
						'selectors' => array( $this->ec_sel( self::link_parts() ) => 'text-decoration: {{VALUE}} !important;' ),
					)
				);
				$this->end_controls_tab();
				$this->start_controls_tab(
					'tab_link_hover',
					array( 'label' => esc_html__( 'Hover', 'wp-easycart' ) )
				);
				$this->ec_color_var( 'link_hover_color', esc_html__( 'Hover color', 'wp-easycart' ), '--wpec-acc-link-hover-color' );
				$this->add_control(
					'link_hover_decoration',
					array(
						'label'     => esc_html__( 'Underline', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::SELECT,
						'default'   => '',
						'options'   => $decorations,
						'selectors' => array( $this->ec_sel_suffix( self::link_parts(), ':hover' ) . ', ' . $this->ec_sel_suffix( self::link_parts(), ':focus-visible' ) => 'text-decoration: {{VALUE}} !important;' ),
					)
				);
				$this->end_controls_tab();
				$this->end_controls_tabs();
				$this->add_group_control(
					\Elementor\Group_Control_Typography::get_type(),
					array(
						'name'      => 'link_typography',
						'label'     => esc_html__( 'Typography', 'wp-easycart' ),
						'selector'  => $this->ec_sel( self::link_parts() ),
						'separator' => 'before',
					)
				);
				$this->end_controls_section();
			}

			if ( in_array( 'accent', $parts, true ) ) {
				$this->start_controls_section(
					'section_style_accent',
					array(
						'label' => esc_html__( 'Accent colour', 'wp-easycart' ),
						'tab'   => $tab,
					)
				);
				$this->ec_color_var(
					'accent_color',
					esc_html__( 'Accent colour', 'wp-easycart' ),
					'--wpec-acc-accent-color',
					'',
					array( 'description' => esc_html__( 'Links, the current menu item, focus rings and subscription details. Empty: the button background, as before.', 'wp-easycart' ) )
				);
				$this->ec_color_var(
					'focus_color',
					esc_html__( 'Focus ring', 'wp-easycart' ),
					'--wpec-acc-focus-color',
					'',
					array( 'description' => esc_html__( 'The outline keyboard users see on the button, menu item or field they are on. Empty: the accent colour.', 'wp-easycart' ) )
				);
				$this->end_controls_section();
			}

			if ( in_array( 'messages', $parts, true ) ) {
				$this->start_controls_section(
					'section_style_messages',
					array(
						'label' => esc_html__( 'Messages', 'wp-easycart' ),
						'tab'   => $tab,
					)
				);
				$this->add_control(
					'messages_note',
					array(
						'type'            => \Elementor\Controls_Manager::RAW_HTML,
						'raw'             => esc_html__( 'The notes customers see after they save or when something goes wrong ("Your details were updated", "Wrong password").', 'wp-easycart' ),
						'content_classes' => 'elementor-descriptor',
					)
				);
				$this->ec_color_var( 'success_color', esc_html__( 'Success text', 'wp-easycart' ), '--wpec-success-color' );
				$this->ec_color_var( 'success_background', esc_html__( 'Success background', 'wp-easycart' ), '--wpec-acc-success-bg' );
				$this->ec_color_var( 'error_color', esc_html__( 'Error text', 'wp-easycart' ), '--wpec-error-color' );
				$this->ec_color_var( 'error_background', esc_html__( 'Error background', 'wp-easycart' ), '--wpec-acc-error-bg' );
				$messages = array( '.ec_account_success', '.ec_account_error' );
				$this->ec_typography( 'messages_typography', esc_html__( 'Typography', 'wp-easycart' ), $messages, '--wpec-acc-message-font' );
				$this->ec_slider( 'messages_border_width', esc_html__( 'Border thickness', 'wp-easycart' ), array( $this->ec_sel( $messages ) => 'border-width: {{SIZE}}{{UNIT}};' ), 10 );
				$this->ec_slider( 'messages_radius', esc_html__( 'Corner radius', 'wp-easycart' ), array( $this->ec_sel( $messages ) => 'border-radius: {{SIZE}}{{UNIT}};' ), 40 );
				$this->ec_dimensions( 'messages_padding', esc_html__( 'Padding', 'wp-easycart' ), $this->ec_sel( array( '.ec_account_success > div', '.ec_account_error > div' ) ) );
				$this->end_controls_section();
			}
		}

		/**
		 * The Fields section: labels, fields in each state, spacing.
		 *
		 * @param string $tab Style tab.
		 */
		protected function ec_field_style_section( $tab ) {
			$this->start_controls_section(
				'section_style_fields',
				array(
					'label' => esc_html__( 'Fields', 'wp-easycart' ),
					'tab'   => $tab,
				)
			);
			$this->ec_color_var( 'label_color', esc_html__( 'Label color', 'wp-easycart' ), '--wpec-acc-label-color' );
			$this->ec_typography( 'label_typography', esc_html__( 'Label typography', 'wp-easycart' ), array( 'label' ), '--wpec-acc-label-font' );
			$this->ec_slider( 'label_spacing', esc_html__( 'Space under labels', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc .ec_cart_input_row label' => 'margin-bottom: {{SIZE}}{{UNIT}};' ), 30, true );
			$this->ec_color(
				'required_color',
				esc_html__( 'Required mark colour', 'wp-easycart' ),
				array( $this->ec_sel( array( '.wpec-acc-required', '#ec_billing_state_required', '#ec_shipping_state_required' ) ) => 'color: {{VALUE}};' ),
				array( 'description' => esc_html__( 'The * after the labels of fields customers must fill in.', 'wp-easycart' ) )
			);
			$this->add_control(
				'fields_heading',
				array(
					'label'     => esc_html__( 'Fields', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_typography( 'field_typography', esc_html__( 'Typography', 'wp-easycart' ), self::field_parts(), '--wpec-acc-field-font' );
			$this->start_controls_tabs( 'tabs_field_style' );
			$this->start_controls_tab(
				'tab_field_normal',
				array( 'label' => esc_html__( 'Normal', 'wp-easycart' ) )
			);
			$this->ec_color_var( 'field_color', esc_html__( 'Text color', 'wp-easycart' ), '--wpec-acc-field-color' );
			$this->ec_color_var( 'field_background', esc_html__( 'Background', 'wp-easycart' ), '--wpec-acc-field-bg' );
			$this->ec_color_var( 'field_border_color', esc_html__( 'Border color', 'wp-easycart' ), '--wpec-acc-field-border' );
			/* EasyCart's stylesheet greys every placeholder with !important. */
			$this->ec_color( 'field_placeholder_color', esc_html__( 'Placeholder colour', 'wp-easycart' ), array( $this->ec_sel_suffix( array( 'input', 'textarea' ), '::placeholder' ) => 'color: {{VALUE}} !important; opacity: 1;' ) );
			$this->end_controls_tab();
			$this->start_controls_tab(
				'tab_field_focus',
				array( 'label' => esc_html__( 'Focus', 'wp-easycart' ) )
			);
			$this->ec_color_var( 'field_focus_color', esc_html__( 'Border color when typing', 'wp-easycart' ), '--wpec-acc-field-focus' );
			$this->ec_color_var( 'field_focus_background', esc_html__( 'Background when typing', 'wp-easycart' ), '--wpec-acc-field-focus-bg' );
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'field_focus_shadow',
					'label'    => esc_html__( 'Glow when typing', 'wp-easycart' ),
					'selector' => $this->ec_sel_suffix( self::field_parts(), ':focus' ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab(
				'tab_field_error',
				array( 'label' => esc_html__( 'Error', 'wp-easycart' ) )
			);
			$this->ec_color_var(
				'field_error_color',
				esc_html__( 'Border colour', 'wp-easycart' ),
				'--wpec-acc-field-error',
				'',
				array( 'description' => esc_html__( 'A field whose "Please enter" note shows after a customer tries to send the form.', 'wp-easycart' ) )
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->ec_slider( 'field_border_width', esc_html__( 'Border thickness', 'wp-easycart' ), array( '{{WRAPPER}}' => '--wpec-acc-field-border-width: {{SIZE}}{{UNIT}};' ), 10, false, array( 'separator' => 'before' ) );
			$this->add_control(
				'field_radius',
				array(
					'label'      => esc_html__( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-acc-field-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'field_height',
				array(
					'label'      => esc_html__( 'Height', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 28,
							'max' => 72,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-acc-field-height: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->ec_dimensions( 'field_padding', esc_html__( 'Padding', 'wp-easycart' ), '{{WRAPPER}}', '--wpec-acc-field-padding' );
			$this->ec_color( 'field_accent_color', esc_html__( 'Tick boxes and choices', 'wp-easycart' ), array( $this->ec_sel( array( 'input[type="checkbox"]', 'input[type="radio"]' ) ) => 'accent-color: {{VALUE}};' ) );
			$this->ec_slider( 'field_row_gap', esc_html__( 'Space between fields', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc form .ec_cart_input_row' => 'margin-top: {{SIZE}}{{UNIT}};' ), 60, true, array( 'separator' => 'before' ) );
			$this->ec_slider( 'field_column_gap', esc_html__( 'Space between fields side by side', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc .ec_cart_input_row > .ec_cart_input_right_half' => 'padding-left: {{SIZE}}{{UNIT}};' ), 60, true, array( 'description' => esc_html__( 'First and last name, state and postal code on the registration form. Phones always show one field per row.', 'wp-easycart' ) ) );
			$this->end_controls_section();
		}

		/**
		 * The Buttons section: the main buttons, then the secondary ones.
		 *
		 * @param string $tab Style tab.
		 */
		protected function ec_button_style_section( $tab ) {
			$this->start_controls_section(
				'section_style_buttons',
				array(
					'label' => esc_html__( 'Buttons', 'wp-easycart' ),
					'tab'   => $tab,
				)
			);
			$this->ec_typography( 'button_typography', esc_html__( 'Typography', 'wp-easycart' ), self::button_parts(), '--wpec-acc-button-font', 'ACCENT' );
			$this->start_controls_tabs( 'tabs_button_style' );
			$this->start_controls_tab(
				'tab_button_normal',
				array( 'label' => esc_html__( 'Normal', 'wp-easycart' ) )
			);
			$this->ec_color_var( 'button_color', esc_html__( 'Text color', 'wp-easycart' ), '--wpec-button-color' );
			$this->ec_color_var( 'button_background', esc_html__( 'Background', 'wp-easycart' ), '--wpec-button-bg' );
			$this->ec_color_var( 'button_border_color', esc_html__( 'Border colour', 'wp-easycart' ), '--wpec-acc-button-border-color' );
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'button_shadow',
					'selector' => $this->ec_sel( self::button_parts() ),
				)
			);
			$this->end_controls_tab();
			$this->start_controls_tab(
				'tab_button_hover',
				array( 'label' => esc_html__( 'Hover', 'wp-easycart' ) )
			);
			$this->ec_color_var( 'button_hover_color', esc_html__( 'Text color', 'wp-easycart' ), '--wpec-button-hover-color' );
			$this->ec_color_var( 'button_hover_background', esc_html__( 'Background', 'wp-easycart' ), '--wpec-button-hover-bg' );
			$this->ec_color_var( 'button_hover_border_color', esc_html__( 'Border colour', 'wp-easycart' ), '--wpec-acc-button-hover-border-color' );
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'button_hover_shadow',
					'selector' => $this->ec_sel_suffix( self::button_parts(), ':hover' ),
				)
			);
			$this->end_controls_tab();
			$this->end_controls_tabs();
			$this->add_control(
				'button_radius',
				array(
					'label'      => esc_html__( 'Corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'separator'  => 'before',
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-button-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->ec_slider( 'button_border_width', esc_html__( 'Border thickness', 'wp-easycart' ), array( '{{WRAPPER}}' => '--wpec-acc-button-border-width: {{SIZE}}{{UNIT}};' ), 10 );
			$this->add_responsive_control(
				'button_padding',
				array(
					'label'      => esc_html__( 'Padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( $this->ec_sel( self::button_parts() ) => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;' ),
				)
			);
			if ( $this->ec_has_secondary_buttons() ) {
				$this->add_control(
					'button2_heading',
					array(
						'label'     => esc_html__( 'Secondary buttons', 'wp-easycart' ),
						'type'      => \Elementor\Controls_Manager::HEADING,
						'separator' => 'before',
					)
				);
				$this->add_control(
					'button2_note',
					array(
						'type'            => \Elementor\Controls_Manager::RAW_HTML,
						'raw'             => esc_html__( 'Create account, View order, View all orders, Download and back to the account. Left empty, they look like the buttons above.', 'wp-easycart' ),
						'content_classes' => 'elementor-descriptor',
					)
				);
				$this->start_controls_tabs( 'tabs_button2_style' );
				$this->start_controls_tab(
					'tab_button2_normal',
					array( 'label' => esc_html__( 'Normal', 'wp-easycart' ) )
				);
				$this->ec_color_var( 'button2_color', esc_html__( 'Text colour', 'wp-easycart' ), '--wpec-acc-button2-color' );
				$this->ec_color_var( 'button2_background', esc_html__( 'Background', 'wp-easycart' ), '--wpec-acc-button2-bg' );
				$this->ec_color_var( 'button2_border_color', esc_html__( 'Border colour', 'wp-easycart' ), '--wpec-acc-button2-border-color' );
				$this->end_controls_tab();
				$this->start_controls_tab(
					'tab_button2_hover',
					array( 'label' => esc_html__( 'Hover', 'wp-easycart' ) )
				);
				$this->ec_color_var( 'button2_hover_color', esc_html__( 'Text colour', 'wp-easycart' ), '--wpec-acc-button2-hover-color' );
				$this->ec_color_var( 'button2_hover_background', esc_html__( 'Background', 'wp-easycart' ), '--wpec-acc-button2-hover-bg' );
				$this->ec_color_var( 'button2_hover_border_color', esc_html__( 'Border colour', 'wp-easycart' ), '--wpec-acc-button2-hover-border-color' );
				$this->end_controls_tab();
				$this->end_controls_tabs();
				$this->ec_slider( 'button2_border_width', esc_html__( 'Border thickness', 'wp-easycart' ), array( '{{WRAPPER}}' => '--wpec-acc-button2-border-width: {{SIZE}}{{UNIT}};' ), 10 );
			}
			$this->end_controls_section();
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Account menu ( My Account and Account Navigation ).
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * Menu items: key => array( label, link view, language key, default on ).
		 *
		 * @return array
		 */
		public static function menu_items() {
			return array(
				'dashboard'       => array(
					'label' => 'Dashboard',
					'view'  => 'dashboard',
				),
				'orders'          => array(
					'label' => 'Orders',
					'view'  => 'orders',
				),
				'downloads'       => array(
					'label' => 'Downloads',
					'view'  => 'downloads',
				),
				'subscriptions'   => array(
					'label' => 'Subscriptions',
					'view'  => 'subscriptions',
				),
				'addresses'       => array(
					'label' => 'Addresses',
					'view'  => 'billing_information',
				),
				'details'         => array(
					'label' => 'Account details',
					'view'  => 'personal_information',
				),
				'payment_methods' => array(
					'label' => 'Payment methods',
					'view'  => 'payment_methods',
				),
				'logout'          => array(
					'label' => 'Sign out',
					'view'  => 'logout',
				),
			);
		}

		/**
		 * Panel labels of the menu items.
		 *
		 * @return array
		 */
		protected function menu_panel_labels() {
			return array(
				'dashboard'       => esc_html__( 'Dashboard', 'wp-easycart' ),
				'orders'          => esc_html__( 'Orders', 'wp-easycart' ),
				'downloads'       => esc_html__( 'Downloads', 'wp-easycart' ),
				'subscriptions'   => esc_html__( 'Subscriptions', 'wp-easycart' ),
				'addresses'       => esc_html__( 'Addresses', 'wp-easycart' ),
				'details'         => esc_html__( 'Account details', 'wp-easycart' ),
				'payment_methods' => esc_html__( 'Payment methods', 'wp-easycart' ),
				'logout'          => esc_html__( 'Sign out', 'wp-easycart' ),
			);
		}

		/**
		 * Switchers, labels, icons and positions of the menu items ( IDs show_<item>, label_<item>, icon_<item>,
		 * position_<item> ).
		 */
		protected function ec_menu_item_controls() {
			$labels = $this->menu_panel_labels();
			foreach ( self::menu_items() as $key => $item ) {
				if ( 'dashboard' !== $key ) {
					$this->add_control(
						'show_' . $key,
						array(
							'label'        => $labels[ $key ],
							'type'         => \Elementor\Controls_Manager::SWITCHER,
							'default'      => 'yes',
							'return_value' => 'yes',
							'separator'    => 'before',
						)
					);
				} else {
					$this->add_control(
						'dashboard_heading',
						array(
							'label' => $labels[ $key ],
							'type'  => \Elementor\Controls_Manager::HEADING,
						)
					);
				}
				$this->add_control(
					'label_' . $key,
					array(
						'label'       => esc_html__( 'Text', 'wp-easycart' ),
						'type'        => \Elementor\Controls_Manager::TEXT,
						'default'     => '',
						'placeholder' => WP_EasyCart_Elementor_Account::text( 'nav_' . $key, $item['label'] ),
						'description' => ( 'dashboard' === $key ) ? esc_html__( 'Leave the texts empty to use the store\'s language file.', 'wp-easycart' ) : '',
						'condition'   => ( 'dashboard' === $key ) ? array() : array( 'show_' . $key => 'yes' ),
						'ai'          => array( 'active' => false ),
					)
				);
				/* Round 11: an icon and a place in the menu per item. */
				$this->add_control(
					'icon_' . $key,
					array(
						'label'       => esc_html__( 'Icon', 'wp-easycart' ),
						'type'        => \Elementor\Controls_Manager::ICONS,
						'skin'        => 'inline',
						'label_block' => false,
						'condition'   => ( 'dashboard' === $key ) ? array() : array( 'show_' . $key => 'yes' ),
					)
				);
				$this->add_control(
					'position_' . $key,
					array(
						'label'       => esc_html__( 'Position', 'wp-easycart' ),
						'type'        => \Elementor\Controls_Manager::NUMBER,
						'default'     => '',
						'min'         => 0,
						'max'         => 99,
						'step'        => 1,
						'placeholder' => (string) ( array_search( $key, array_keys( self::menu_items() ), true ) + 1 ),
						'description' => ( 'dashboard' === $key ) ? esc_html__( 'Lower numbers come first. Empty: the usual place ( Dashboard 1, Orders 2 … Sign out 8 ).', 'wp-easycart' ) : '',
						'condition'   => ( 'dashboard' === $key ) ? array() : array( 'show_' . $key => 'yes' ),
					)
				);
			}
			$this->add_control(
				'menu_auto_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Downloads shows only when your store sells downloads, and Subscriptions and Payment methods only when customers manage Stripe subscriptions in their account.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
		}

		/**
		 * The menu items to show, with their text and link.
		 *
		 * Links other plugins add to the store's account menu ( actions wpeasycart_account_links, _after_billing,
		 * _after_shipping, _after_personal, _after_password, _after_subscriptions, _end, which the account templates fire in
		 * the menu columns the widgets leave out ) join this menu at the same places. Filter
		 * wp_easycart_elementor_account_menu_items( $items, $settings, $views ) changes the list.
		 *
		 * Each item ( with the links hooked after it ) goes where its Position setting ( position_<item> ) puts it; items
		 * without one keep their usual place ( 1 to 8 ), and a tie keeps the usual order.
		 *
		 * @param array  $settings Settings.
		 * @param array  $views    Views of the widget ( routing ).
		 * @param string $active   Active item key.
		 * @return array Each array( key, text, url, active, icon ).
		 */
		public static function menu_links( $settings, $views, $active ) {
			$hooks  = array(
				'dashboard'     => array( 'wpeasycart_account_links' ),
				'addresses'     => array( 'wpeasycart_account_links_after_billing', 'wpeasycart_account_links_after_shipping' ),
				'details'       => array( 'wpeasycart_account_links_after_personal', 'wpeasycart_account_links_after_password' ),
				'subscriptions' => array( 'wpeasycart_account_links_after_subscriptions' ),
				'logout'        => array( 'wpeasycart_account_links_end' ),
			);
			$blocks = array();
			$index  = 0;
			foreach ( self::menu_items() as $key => $item ) {
				++$index;
				$items = array();
				$shown = ! ( 'dashboard' !== $key && isset( $settings[ 'show_' . $key ] ) && 'yes' !== $settings[ 'show_' . $key ] );
				if ( 'downloads' === $key && ! WP_EasyCart_Elementor_Account::sells_downloads() ) {
					$shown = false;
				}
				if ( ( 'subscriptions' === $key || 'payment_methods' === $key ) && ! WP_EasyCart_Elementor_Account::uses_subscriptions() ) {
					$shown = false;
				}
				if ( $shown ) {
					$text = ( isset( $settings[ 'label_' . $key ] ) && is_string( $settings[ 'label_' . $key ] ) ) ? trim( $settings[ 'label_' . $key ] ) : '';
					if ( '' === $text ) {
						$text = WP_EasyCart_Elementor_Account::text( 'nav_' . $key, $item['label'] );
					}
					if ( 'logout' === $key ) {
						$url = WP_EasyCart_Elementor_Account::logout_url( $views );
					} else {
						$url = WP_EasyCart_Elementor_Account::link_to( $item['view'], $views );
					}
					$items[] = array(
						'key'    => $key,
						'text'   => $text,
						'url'    => $url,
						'active' => ( $key === $active ),
						'icon'   => self::menu_icon( $settings, $key ),
					);
				}
				if ( isset( $hooks[ $key ] ) ) {
					foreach ( $hooks[ $key ] as $hook ) {
						$items = array_merge( $items, self::hooked_links( $hook ) );
					}
				}
				$position = isset( $settings[ 'position_' . $key ] ) ? $settings[ 'position_' . $key ] : '';
				$blocks[] = array(
					'order' => ( is_numeric( $position ) ) ? (float) $position : (float) $index,
					'index' => $index,
					'items' => $items,
				);
			}
			usort( $blocks, array( __CLASS__, 'compare_menu_blocks' ) );
			$items = array();
			foreach ( $blocks as $block ) {
				$items = array_merge( $items, $block['items'] );
			}
			$items = apply_filters( 'wp_easycart_elementor_account_menu_items', $items, $settings, $views );
			$valid = array();
			foreach ( (array) $items as $item ) {
				if ( is_array( $item ) && isset( $item['text'], $item['url'] ) && '' !== trim( (string) $item['text'] ) ) {
					$valid[] = array(
						'key'    => isset( $item['key'] ) ? sanitize_key( (string) $item['key'] ) : 'extension',
						'text'   => (string) $item['text'],
						'url'    => (string) $item['url'],
						'active' => ! empty( $item['active'] ),
						'icon'   => ( isset( $item['icon'] ) && is_array( $item['icon'] ) && ! empty( $item['icon']['value'] ) ) ? $item['icon'] : null,
					);
				}
			}
			return $valid;
		}

		/**
		 * Menu order: Position, then the usual place.
		 *
		 * @param array $a Block.
		 * @param array $b Block.
		 * @return int
		 */
		public static function compare_menu_blocks( $a, $b ) {
			if ( $a['order'] === $b['order'] ) {
				return $a['index'] - $b['index'];
			}
			return ( $a['order'] < $b['order'] ) ? -1 : 1;
		}

		/**
		 * A menu item's icon setting ( icon_<item> ), or null.
		 *
		 * @param array  $settings Settings.
		 * @param string $key      Item.
		 * @return array|null
		 */
		private static function menu_icon( $settings, $key ) {
			$icon = isset( $settings[ 'icon_' . $key ] ) ? $settings[ 'icon_' . $key ] : null;
			return ( is_array( $icon ) && ! empty( $icon['value'] ) ) ? $icon : null;
		}

		/**
		 * The links one of the store's account menu actions prints ( its markup is read, the menu draws them its own way ).
		 *
		 * @param string $hook Action.
		 * @return array Menu items.
		 */
		private static function hooked_links( $hook ) {
			if ( ! has_action( $hook ) ) {
				return array();
			}
			ob_start();
			do_action( $hook ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- EasyCart's own wpeasycart_account_links* actions, listed in menu_links().
			$html  = (string) ob_get_clean();
			$items = array();
			if ( preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $links, PREG_SET_ORDER ) ) {
				foreach ( $links as $link ) {
					$text = trim( wp_strip_all_tags( html_entity_decode( $link[3], ENT_QUOTES, 'UTF-8' ) ) );
					$url  = html_entity_decode( $link[2], ENT_QUOTES, 'UTF-8' );
					if ( '' !== $text && '' !== $url ) {
						$items[] = array(
							'key'    => 'extension',
							'text'   => $text,
							'url'    => $url,
							'active' => false,
						);
					}
				}
			}
			return $items;
		}

		/**
		 * Menu item for a view.
		 *
		 * @param string $view View key.
		 * @return string
		 */
		public static function menu_key_for( $view ) {
			$map = array(
				'dashboard'            => 'dashboard',
				'orders'               => 'orders',
				'order_details'        => 'orders',
				'downloads'            => 'downloads',
				'subscriptions'        => 'subscriptions',
				'subscription_details' => 'subscriptions',
				'billing_information'  => 'addresses',
				'shipping_information' => 'addresses',
				'personal_information' => 'details',
				'password'             => 'details',
				'payment_methods'      => 'payment_methods',
			);
			return isset( $map[ $view ] ) ? $map[ $view ] : '';
		}

		/**
		 * Prints the menu.
		 *
		 * @param array  $items       menu_links().
		 * @param string $orientation vertical | horizontal.
		 * @param bool   $dropdown    A dropdown on phones.
		 */
		public static function print_menu( $items, $orientation, $dropdown ) {
			if ( empty( $items ) ) {
				return;
			}
			$label  = WP_EasyCart_Elementor_Account::text( 'nav_menu_label', 'Account menu' );
			$select = 'wpec-acc-nav-' . wp_rand( 1000, 999999 );
			echo '<nav class="wpec-acc-nav wpec-acc-nav--' . esc_attr( 'horizontal' === $orientation ? 'horizontal' : 'vertical' ) . ( $dropdown ? ' wpec-acc-nav--dropdown' : '' ) . '" aria-label="' . esc_attr( $label ) . '">';
			echo '<ul class="wpec-acc-nav__list">';
			foreach ( $items as $item ) {
				$icon = ( ! empty( $item['icon'] ) && is_array( $item['icon'] ) && class_exists( '\Elementor\Icons_Manager' ) ) ? $item['icon'] : null;
				echo '<li class="wpec-acc-nav__item wpec-acc-nav__item--' . esc_attr( str_replace( '_', '-', $item['key'] ) ) . ( $item['active'] ? ' is-active' : '' ) . '">';
				echo '<a class="wpec-acc-nav__link' . ( $icon ? ' has-icon' : '' ) . '" href="' . esc_url( $item['url'] ) . '"' . ( $item['active'] ? ' aria-current="page"' : '' ) . '>';
				if ( $icon ) {
					echo '<span class="wpec-acc-nav__icon" aria-hidden="true">';
					\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
					echo '</span><span class="wpec-acc-nav__text">' . esc_html( $item['text'] ) . '</span>';
				} else {
					echo esc_html( $item['text'] );
				}
				echo '</a>';
				echo '</li>';
			}
			echo '</ul>';
			if ( $dropdown ) {
				echo '<div class="wpec-acc-nav__select-wrap">';
				echo '<label class="wpec-acc-sr" for="' . esc_attr( $select ) . '">' . esc_html( $label ) . '</label>';
				echo '<select class="wpec-acc-nav__select" id="' . esc_attr( $select ) . '">';
				foreach ( $items as $item ) {
					echo '<option value="' . esc_url( $item['url'] ) . '"' . selected( $item['active'], true, false ) . '>' . esc_html( $item['text'] ) . '</option>';
				}
				echo '</select>';
				echo '</div>';
			}
			echo '</nav>';
		}

		/**
		 * Style section of the menu ( IDs menu_* ).
		 *
		 * @param bool $with_width Include the menu width ( My Account's side menu ).
		 */
		protected function ec_menu_style_controls( $with_width ) {
			$this->start_controls_section(
				'section_style_menu',
				array(
					'label' => esc_html__( 'Menu', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			if ( $with_width ) {
				$this->add_responsive_control(
					'menu_width',
					array(
						'label'      => esc_html__( 'Side menu width', 'wp-easycart' ),
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px', '%' ),
						'range'      => array(
							'px' => array(
								'min' => 120,
								'max' => 480,
							),
							'%'  => array(
								'min' => 10,
								'max' => 50,
							),
						),
						'selectors'  => array( '{{WRAPPER}}' => '--wpec-acc-nav-width: {{SIZE}}{{UNIT}};' ),
						'condition'  => array( 'menu_position' => 'left' ),
					)
				);
				$this->add_responsive_control(
					'menu_gap',
					array(
						'label'      => esc_html__( 'Space between menu and content', 'wp-easycart' ),
						'type'       => \Elementor\Controls_Manager::SLIDER,
						'size_units' => array( 'px' ),
						'range'      => array(
							'px' => array(
								'min' => 0,
								'max' => 100,
							),
						),
						'selectors'  => array( '{{WRAPPER}}' => '--wpec-acc-layout-gap: {{SIZE}}{{UNIT}};' ),
						'condition'  => array( 'menu_position!' => 'none' ),
					)
				);
			}
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'menu_typography',
					'selector' => '{{WRAPPER}} .wpec-acc-nav__link, {{WRAPPER}} .wpec-acc-nav__select',
					'global'   => $this->ec_global( 'typography', 'TEXT' ),
				)
			);
			$this->start_controls_tabs( 'tabs_menu_style' );
			foreach ( array(
				'normal' => esc_html__( 'Normal', 'wp-easycart' ),
				'hover'  => esc_html__( 'Hover', 'wp-easycart' ),
				'active' => esc_html__( 'Current', 'wp-easycart' ),
			) as $state => $state_label ) {
				$this->start_controls_tab( 'tab_menu_' . $state, array( 'label' => $state_label ) );
				$suffix = ( 'normal' === $state ) ? '' : '-' . $state;
				$this->ec_color_var( 'menu_' . $state . '_color', esc_html__( 'Text color', 'wp-easycart' ), '--wpec-acc-nav' . $suffix . '-color' );
				$this->ec_color_var( 'menu_' . $state . '_background', esc_html__( 'Background', 'wp-easycart' ), '--wpec-acc-nav' . $suffix . '-bg' );
				$item_selectors = array(
					'normal' => array( '{{WRAPPER}} .wpec-acc-nav__link' ),
					'hover'  => array( '{{WRAPPER}} .wpec-acc-nav__link:hover', '{{WRAPPER}} .wpec-acc-nav__link:focus-visible' ),
					'active' => array( '{{WRAPPER}} .wpec-acc-nav__item.is-active > .wpec-acc-nav__link' ),
				);
				$icon_selectors = array();
				foreach ( $item_selectors[ $state ] as $item_selector ) {
					$icon_selectors[] = $item_selector . ' .wpec-acc-nav__icon';
				}
				$this->ec_color( 'menu_' . $state . '_border_color', esc_html__( 'Border colour', 'wp-easycart' ), array( implode( ', ', $item_selectors[ $state ] ) => 'border-color: {{VALUE}};' ), array( 'description' => ( 'normal' === $state ) ? esc_html__( 'Shows with an item border ( below ).', 'wp-easycart' ) : '' ) );
				$this->ec_color( 'menu_' . $state . '_icon_color', esc_html__( 'Icon colour', 'wp-easycart' ), array( implode( ', ', $icon_selectors ) => 'color: {{VALUE}};' ) );
				$this->end_controls_tab();
			}
			$this->end_controls_tabs();
			$this->add_responsive_control(
				'menu_align',
				array(
					'label'                => esc_html__( 'Alignment', 'wp-easycart' ),
					'type'                 => \Elementor\Controls_Manager::CHOOSE,
					'options'              => array_merge(
						$this->ec_align_options(),
						array(
							'justify' => array(
								'title' => esc_html__( 'Full width', 'wp-easycart' ),
								'icon'  => 'eicon-text-align-justify',
							),
						)
					),
					'separator'            => 'before',
					'description'          => esc_html__( 'Full width: items in a row share the width.', 'wp-easycart' ),
					'selectors_dictionary' => array(
						'left'    => 'text-align: left; --wpec-acc-nav-justify: flex-start;',
						'center'  => 'text-align: center; --wpec-acc-nav-justify: center;',
						'right'   => 'text-align: right; --wpec-acc-nav-justify: flex-end;',
						'justify' => 'text-align: center; --wpec-acc-nav-justify: center; --wpec-acc-nav-grow: 1;',
					),
					'selectors'            => array( '{{WRAPPER}} .wpec-acc-nav' => '{{VALUE}}' ),
				)
			);
			$this->add_control(
				'menu_indicator',
				array(
					'label'   => esc_html__( 'Current item mark', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => '',
					'options' => array(
						''          => esc_html__( 'None', 'wp-easycart' ),
						'bar'       => esc_html__( 'Bar beside it ( a list ) or under it ( a row )', 'wp-easycart' ),
						'underline' => esc_html__( 'Underlined text', 'wp-easycart' ),
					),
				)
			);
			$this->ec_color_var( 'menu_indicator_color', esc_html__( 'Mark colour', 'wp-easycart' ), '--wpec-acc-nav-indicator-color', '', array( 'condition' => array( 'menu_indicator!' => '' ) ) );
			$this->ec_slider( 'menu_indicator_width', esc_html__( 'Mark thickness', 'wp-easycart' ), array( '{{WRAPPER}}' => '--wpec-acc-nav-indicator-width: {{SIZE}}{{UNIT}};' ), 10, false, array( 'condition' => array( 'menu_indicator!' => '' ) ) );
			$this->add_responsive_control(
				'menu_item_padding',
				array(
					'label'      => esc_html__( 'Item padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'separator'  => 'before',
					'selectors'  => array( '{{WRAPPER}} .wpec-acc-nav__link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_responsive_control(
				'menu_item_gap',
				array(
					'label'      => esc_html__( 'Space between items', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 40,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-acc-nav-gap: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->add_control(
				'menu_item_radius',
				array(
					'label'      => esc_html__( 'Item corner radius', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::SLIDER,
					'size_units' => array( 'px' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 30,
						),
					),
					'selectors'  => array( '{{WRAPPER}}' => '--wpec-acc-nav-radius: {{SIZE}}{{UNIT}};' ),
				)
			);
			$this->ec_color_var( 'menu_border_color', esc_html__( 'Line color', 'wp-easycart' ), '--wpec-acc-nav-border' );
			$this->add_group_control(
				\Elementor\Group_Control_Border::get_type(),
				array(
					'name'     => 'menu_item_border',
					'label'    => esc_html__( 'Item border', 'wp-easycart' ),
					'selector' => '{{WRAPPER}} .wpec-acc-nav__link',
				)
			);
			$this->add_control(
				'menu_icons_heading',
				array(
					'label'     => esc_html__( 'Icons', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_slider( 'menu_icon_size', esc_html__( 'Icon size', 'wp-easycart' ), array( '{{WRAPPER}}' => '--wpec-acc-nav-icon-size: {{SIZE}}{{UNIT}};' ), 60, true, array( 'description' => esc_html__( 'Choose an icon for each item under Content › Account menu.', 'wp-easycart' ) ) );
			$this->ec_slider( 'menu_icon_gap', esc_html__( 'Space after the icon', 'wp-easycart' ), array( '{{WRAPPER}}' => '--wpec-acc-nav-icon-gap: {{SIZE}}{{UNIT}};' ), 40, true );
			$this->add_control(
				'menu_select_heading',
				array(
					'label'     => esc_html__( 'Dropdown on phones', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_color( 'menu_select_color', esc_html__( 'Text colour', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc-nav__select' => 'color: {{VALUE}};' ) );
			$this->ec_color( 'menu_select_background', esc_html__( 'Background', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc-nav__select' => 'background-color: {{VALUE}};' ) );
			$this->ec_color( 'menu_select_border_color', esc_html__( 'Border colour', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc-nav__select' => 'border-color: {{VALUE}};' ) );
			$this->ec_slider( 'menu_select_radius', esc_html__( 'Corner radius', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc-nav__select' => 'border-radius: {{SIZE}}{{UNIT}};' ), 30 );
			$this->end_controls_section();
		}

		/*
		 * ---------------------------------------------------------------------------------------------------------------
		 * Style sections of the account views ( round 11 ): My Account has them all, each part widget the ones it draws.
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		/**
		 * My Account's content area ( IDs content_* ): a box around the page the menu opens.
		 */
		protected function ec_content_area_style_controls() {
			$this->start_controls_section(
				'section_style_content_area',
				array(
					'label' => esc_html__( 'Content area', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'content_area_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'The box beside or under the menu that holds the page customers opened. Box styles the whole widget.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->ec_box_controls( 'content', '{{WRAPPER}} .wpec-acc__content' );
			$this->end_controls_section();
		}

		/**
		 * The sign-in page's two boxes ( IDs login_* ): the line between them, the space around it, each box.
		 */
		protected function ec_login_boxes_style_controls() {
			$this->start_controls_section(
				'section_style_login_boxes',
				array(
					'label' => esc_html__( 'Sign-in and new customer boxes', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$left  = '{{WRAPPER}} .wpec-acc:not(.wpec-acc--no-register-box) .ec_account_left.ec_account_login';
			$right = '{{WRAPPER}} .wpec-acc .ec_account_right.ec_account_login';
			$this->ec_color( 'login_divider_color', esc_html__( 'Line between the boxes', 'wp-easycart' ), array( $left => 'border-right-color: {{VALUE}};' ) );
			$this->ec_slider( 'login_divider_width', esc_html__( 'Line thickness', 'wp-easycart' ), array( $left => 'border-right-width: {{SIZE}}{{UNIT}};' ), 10 );
			$space = array(
				$left  => 'padding-right: {{SIZE}}{{UNIT}};',
				$right => 'padding-left: {{SIZE}}{{UNIT}};',
			);
			$this->ec_slider( 'login_divider_space', esc_html__( 'Space on each side of the line', 'wp-easycart' ), $space, 100, true );
			$this->add_control(
				'login_new_box_heading',
				array(
					'label'     => esc_html__( 'New customer box', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_box_controls( 'login_new_box', $right );
			$this->end_controls_section();
		}

		/**
		 * Content switches of the order list ( IDs show_order_date, show_order_total, show_order_status, show_order_button ).
		 */
		protected function ec_order_column_controls() {
			$columns = array(
				'show_order_date'   => esc_html__( 'Show the date column', 'wp-easycart' ),
				'show_order_total'  => esc_html__( 'Show the total column', 'wp-easycart' ),
				'show_order_status' => esc_html__( 'Show the status column', 'wp-easycart' ),
				'show_order_button' => esc_html__( 'Show the View order buttons', 'wp-easycart' ),
			);
			foreach ( $columns as $id => $label ) {
				$this->add_control(
					$id,
					array(
						'label'        => $label,
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'default'      => 'yes',
						'return_value' => 'yes',
					)
				);
			}
		}

		/**
		 * The order list ( IDs list_* ): header, rows, status, lines.
		 */
		protected function ec_order_list_style_controls() {
			$this->start_controls_section(
				'section_style_list',
				array(
					'label' => esc_html__( 'Order list', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$rows = array( '.ec_account_order_line_0', '.ec_account_order_line_1' );
			$this->ec_color_var( 'list_header_color', esc_html__( 'Header text', 'wp-easycart' ), '--wpec-acc-table-head-color' );
			$this->ec_color_var( 'list_header_background', esc_html__( 'Header background', 'wp-easycart' ), '--wpec-acc-table-head-bg' );
			$this->ec_typography( 'list_header_typography', esc_html__( 'Header typography', 'wp-easycart' ), array( '.ec_account_order_line_header > div' ), '--wpec-acc-table-head-font' );
			$this->ec_color_var( 'list_rows_background', esc_html__( 'Row background', 'wp-easycart' ), '--wpec-acc-table-row-bg', '', array( 'separator' => 'before' ) );
			$this->ec_color_var( 'list_row_background', esc_html__( 'Every other row', 'wp-easycart' ), '--wpec-acc-table-stripe' );
			$this->ec_color( 'list_row_hover_background', esc_html__( 'Row background on hover', 'wp-easycart' ), array( $this->ec_sel_suffix( $rows, ':hover' ) => 'background-color: {{VALUE}};' ) );
			$this->ec_color( 'list_row_color', esc_html__( 'Row text', 'wp-easycart' ), array( $this->ec_sel_suffix( $rows, ' > div' ) => 'color: {{VALUE}};' ) );
			$this->ec_typography( 'list_row_typography', esc_html__( 'Row typography', 'wp-easycart' ), array( '.ec_account_order_line_0 > div', '.ec_account_order_line_1 > div' ), '--wpec-acc-table-font' );
			$this->ec_color_var( 'list_border_color', esc_html__( 'Lines', 'wp-easycart' ), '--wpec-acc-table-border', '', array( 'description' => esc_html__( 'The line under the header, and a line under every order once set.', 'wp-easycart' ) ) );
			$this->ec_slider( 'list_row_padding', esc_html__( 'Row height ( space above and below )', 'wp-easycart' ), array( $this->ec_sel( array_merge( array( '.ec_account_order_line_header' ), $rows ) ) => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};' ), 40, true, array( 'separator' => 'before' ) );
			$this->ec_slider( 'list_indent', esc_html__( 'Space before the first column', 'wp-easycart' ), array( $this->ec_sel( array( '.ec_account_order_line_column1_header', '.ec_account_order_line_column1' ) ) => 'padding-left: {{SIZE}}{{UNIT}};' ), 60, true );
			$this->add_control(
				'list_status_heading',
				array(
					'label'     => esc_html__( 'Status', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$status = $this->ec_sel( array( '.wpec-acc-status' ) );
			$this->ec_color( 'list_status_color', esc_html__( 'Text colour', 'wp-easycart' ), array( $status => 'color: {{VALUE}};' ) );
			$this->ec_color( 'list_status_background', esc_html__( 'Badge background', 'wp-easycart' ), array( $status => 'background-color: {{VALUE}}; display: inline-block;' ) );
			$this->ec_slider( 'list_status_radius', esc_html__( 'Badge corner radius', 'wp-easycart' ), array( $status => 'border-radius: {{SIZE}}{{UNIT}}; display: inline-block;' ), 30 );
			$this->add_responsive_control(
				'list_status_padding',
				array(
					'label'      => esc_html__( 'Badge padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( $status => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; display: inline-block;' ),
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Content switches of an order's page ( IDs show_order_print, show_order_notes ).
		 */
		protected function ec_order_details_content_controls() {
			$this->add_control(
				'show_order_print',
				array(
					'label'        => esc_html__( 'Show the print button', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
					'description'  => esc_html__( 'Settings › Documents decides whether customers can print their receipt at all.', 'wp-easycart' ),
				)
			);
			$this->add_control(
				'show_order_notes',
				array(
					'label'        => esc_html__( 'Show the customer\'s order notes', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
				)
			);
		}

		/**
		 * An order's page ( IDs order_* ): the item table and the order information column.
		 */
		protected function ec_order_details_style_controls() {
			$this->start_controls_section(
				'section_style_order',
				array(
					'label' => esc_html__( 'Order details', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$head = $this->ec_sel( array( '.ec_account_order_details_table > thead > tr > th' ) );
			$row  = $this->ec_sel( array( 'tr.ec_account_orderitem_row' ) );
			$this->ec_color( 'order_head_color', esc_html__( 'Item table header text', 'wp-easycart' ), array( $head => 'color: {{VALUE}};' ) );
			$this->ec_color( 'order_head_background', esc_html__( 'Item table header background', 'wp-easycart' ), array( $head => 'background-color: {{VALUE}};' ) );
			$this->ec_color( 'order_head_line_color', esc_html__( 'Line under the header', 'wp-easycart' ), array( $this->ec_sel( array( '.ec_account_order_details_table > thead' ) ) => 'border-bottom-color: {{VALUE}};' ) );
			$this->ec_color( 'order_row_background', esc_html__( 'Item row background', 'wp-easycart' ), array( $row => 'background-color: {{VALUE}} !important;' ) );
			$this->ec_color( 'order_row_line_color', esc_html__( 'Lines between items', 'wp-easycart' ), array( $row => 'border-bottom-color: {{VALUE}};' ) );
			$image = array(
				$this->ec_sel( array( 'td.ec_account_orderitem_image' ) )      => 'width: {{SIZE}}{{UNIT}};',
				$this->ec_sel( array( '.ec_account_orderitem_image > img' ) ) => 'width: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};',
			);
			$this->ec_slider( 'order_image_width', esc_html__( 'Item image width', 'wp-easycart' ), $image, 240, true, array( 'separator' => 'before' ) );
			$this->ec_slider( 'order_image_radius', esc_html__( 'Item image corner radius', 'wp-easycart' ), array( $this->ec_sel( array( '.ec_account_orderitem_image > img' ) ) => 'border-radius: {{SIZE}}{{UNIT}};' ), 60 );
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'order_item_title_typography',
					'label'    => esc_html__( 'Item name typography', 'wp-easycart' ),
					'selector' => $this->ec_sel( array( '.ec_account_order_details_item_display_title', '.ec_account_order_details_item_display_title > a' ) ),
				)
			);
			$this->ec_color( 'order_label_color', esc_html__( 'Order information labels', 'wp-easycart' ), array( $this->ec_sel( array( '.ec_account_order_details_left .ec_cart_input_row strong' ) ) => 'color: {{VALUE}};' ), array( 'separator' => 'before' ) );
			$this->ec_slider( 'order_info_spacing', esc_html__( 'Space between information lines', 'wp-easycart' ), array( $this->ec_sel( array( '.ec_account_order_details_left > .ec_cart_input_row' ) ) => 'margin-top: {{SIZE}}{{UNIT}};' ), 30, true );
			$this->end_controls_section();
		}

		/**
		 * Subscription cards and pages ( IDs sub_* ). EasyCart's subscription styles read --ec-sub-* variables.
		 */
		protected function ec_subscription_style_controls() {
			$this->start_controls_section(
				'section_style_subscriptions',
				array(
					'label' => esc_html__( 'Subscriptions', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$scope = $this->ec_sel( array( '.ec_account_subscription_v2', '.ec_account_subscriptions_v2', '.ec_account_subscription_card' ) );
			$cards = $this->ec_sel( array( '.ec_account_subscription_card', '.ec_account_subscription_v2_card', '.ec_account_subscription_v2 .ec_account_subscription_upgrade_row', '.ec_account_subscription_v2 .ec_account_subscription_details_payment_form' ) );
			$this->ec_color( 'sub_card_background', esc_html__( 'Card background', 'wp-easycart' ), array( $scope => '--ec-sub-bg: {{VALUE}};' ) );
			$this->ec_color( 'sub_card_border_color', esc_html__( 'Card border and lines', 'wp-easycart' ), array( $scope => '--ec-sub-border: {{VALUE}};' ) );
			$this->ec_color( 'sub_muted_color', esc_html__( 'Secondary text', 'wp-easycart' ), array( $scope => '--ec-sub-muted: {{VALUE}};' ) );
			$this->ec_color( 'sub_soft_color', esc_html__( 'Image and note background', 'wp-easycart' ), array( $scope => '--ec-sub-soft: {{VALUE}};' ) );
			$this->ec_slider( 'sub_card_radius', esc_html__( 'Card corner radius', 'wp-easycart' ), array( $cards => 'border-radius: {{SIZE}}{{UNIT}};' ), 40 );
			$this->add_responsive_control(
				'sub_card_padding',
				array(
					'label'      => esc_html__( 'Card padding', 'wp-easycart' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array( $cards => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);
			$this->add_group_control(
				\Elementor\Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'sub_card_shadow',
					'label'    => esc_html__( 'Card shadow', 'wp-easycart' ),
					'selector' => $cards,
				)
			);
			$media = $this->ec_sel( array( '.ec_account_subscription_card .ec_account_subscription_card_media' ) );
			$this->ec_slider( 'sub_image_size', esc_html__( 'Image size in the list', 'wp-easycart' ), array( $media => 'flex-basis: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ), 160, true, array( 'separator' => 'before' ) );
			$this->ec_slider( 'sub_image_radius', esc_html__( 'Image corner radius', 'wp-easycart' ), array( $this->ec_sel( array( '.ec_account_subscription_card_media', '.ec_account_subscription_v2_media' ) ) => 'border-radius: {{SIZE}}{{UNIT}};' ), 80 );
			$this->add_control(
				'sub_badges_heading',
				array(
					'label'     => esc_html__( 'Status badges', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$badges = array(
				'active'   => array( esc_html__( 'Active', 'wp-easycart' ), array( '.is-active' ) ),
				'trialing' => array( esc_html__( 'Trial', 'wp-easycart' ), array( '.is-trialing' ) ),
				'past_due' => array( esc_html__( 'Payment due', 'wp-easycart' ), array( '.is-past_due' ) ),
				'ending'   => array( esc_html__( 'Ending or incomplete', 'wp-easycart' ), array( '.is-canceling', '.is-incomplete' ) ),
				'paused'   => array( esc_html__( 'Paused', 'wp-easycart' ), array( '.is-paused', '.is-suspended' ) ),
				'other'    => array( esc_html__( 'Ended and others', 'wp-easycart' ), array( ':not(.is-active):not(.is-trialing):not(.is-past_due):not(.is-canceling):not(.is-incomplete):not(.is-paused):not(.is-suspended)' ) ),
			);
			foreach ( $badges as $state => $badge ) {
				$parts = array();
				foreach ( $badge[1] as $class ) {
					$parts[] = '.ec_account_subscription_v2_badge' . $class;
				}
				/* translators: %s: subscription state. */
				$this->ec_color( 'sub_badge_' . $state . '_color', sprintf( esc_html__( '%s: text', 'wp-easycart' ), $badge[0] ), array( $this->ec_sel( $parts ) => 'color: {{VALUE}};' ) );
				/* translators: %s: subscription state. */
				$this->ec_color( 'sub_badge_' . $state . '_background', sprintf( esc_html__( '%s: background', 'wp-easycart' ), $badge[0] ), array( $this->ec_sel( $parts ) => 'background-color: {{VALUE}};' ) );
			}
			$this->end_controls_section();
		}

		/**
		 * Two forms side by side ( Addresses, Account Details; IDs columns_*, column_* ).
		 */
		protected function ec_columns_style_controls() {
			$this->start_controls_section(
				'section_style_columns',
				array(
					'label' => esc_html__( 'Forms side by side', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				'columns_layout',
				array(
					'label'   => esc_html__( 'Layout', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => '',
					'options' => array(
						''      => esc_html__( 'Side by side when there is room', 'wp-easycart' ),
						'stack' => esc_html__( 'Always one under the other', 'wp-easycart' ),
					),
				)
			);
			$this->ec_slider( 'columns_min_width', esc_html__( 'Narrowest form before they stack', 'wp-easycart' ), array( '{{WRAPPER}}' => '--wpec-acc-column-min: {{SIZE}}{{UNIT}};' ), 800, false, array( 'condition' => array( 'columns_layout' => '' ) ) );
			$this->ec_slider( 'columns_gap', esc_html__( 'Space between the forms', 'wp-easycart' ), array( '{{WRAPPER}} .wpec-acc-columns' => 'gap: {{SIZE}}{{UNIT}};' ), 120, true );
			$this->add_control(
				'column_box_heading',
				array(
					'label'     => esc_html__( 'Each form', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);
			$this->ec_box_controls( 'column', '{{WRAPPER}} .wpec-acc-column' );
			$this->end_controls_section();
		}

		/**
		 * The downloads list ( IDs downloads_* ).
		 */
		protected function ec_downloads_style_controls() {
			$this->start_controls_section(
				'section_style_downloads',
				array(
					'label' => esc_html__( 'Download list', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$item = $this->ec_sel( array( '.wpec-acc-downloads__item' ) );
			$this->ec_color( 'downloads_divider_color', esc_html__( 'Line between downloads', 'wp-easycart' ), array( $item => 'border-bottom-color: {{VALUE}};' ) );
			$this->ec_slider( 'downloads_divider_width', esc_html__( 'Line thickness', 'wp-easycart' ), array( $item => 'border-bottom-width: {{SIZE}}{{UNIT}};' ), 10 );
			$this->ec_dimensions( 'downloads_item_padding', esc_html__( 'Padding of each download', 'wp-easycart' ), $item );
			$this->ec_color( 'downloads_title_color', esc_html__( 'Name colour', 'wp-easycart' ), array( $this->ec_sel( array( '.wpec-acc-downloads__title' ) ) => 'color: {{VALUE}};' ), array( 'separator' => 'before' ) );
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'downloads_title_typography',
					'label'    => esc_html__( 'Name typography', 'wp-easycart' ),
					'selector' => $this->ec_sel( array( '.wpec-acc-downloads__title' ) ),
				)
			);
			$this->ec_color( 'downloads_meta_color', esc_html__( 'Order and limits colour', 'wp-easycart' ), array( $this->ec_sel( array( '.wpec-acc-downloads__meta' ) ) => 'color: {{VALUE}};' ) );
			$status = $this->ec_sel( array( '.wpec-acc-downloads__status' ) );
			$this->ec_color( 'downloads_status_color', esc_html__( 'Expired or used up: text', 'wp-easycart' ), array( $status => 'color: {{VALUE}};' ), array( 'separator' => 'before' ) );
			$this->ec_color( 'downloads_status_background', esc_html__( 'Expired or used up: background', 'wp-easycart' ), array( $status => 'background-color: {{VALUE}}; display: inline-block; padding: 4px 10px; border-radius: var(--wpec-radius, 4px);' ) );
			$this->end_controls_section();
		}

		/**
		 * The card on file ( IDs payment_* ).
		 */
		protected function ec_payment_style_controls() {
			$this->start_controls_section(
				'section_style_payment',
				array(
					'label' => esc_html__( 'Card on file', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->ec_box_controls( 'payment_card', '{{WRAPPER}} .wpec-acc-card' );
			$this->ec_color( 'payment_brand_color', esc_html__( 'Card type colour', 'wp-easycart' ), array( $this->ec_sel( array( '.wpec-acc-card__brand' ) ) => 'color: {{VALUE}};' ), array( 'separator' => 'before' ) );
			$this->add_group_control(
				\Elementor\Group_Control_Typography::get_type(),
				array(
					'name'     => 'payment_brand_typography',
					'label'    => esc_html__( 'Card type typography', 'wp-easycart' ),
					'selector' => $this->ec_sel( array( '.wpec-acc-card__brand' ) ),
				)
			);
			$this->ec_color( 'payment_number_color', esc_html__( 'Card number colour', 'wp-easycart' ), array( $this->ec_sel( array( '.wpec-acc-card__number' ) ) => 'color: {{VALUE}};' ) );
			$this->ec_color( 'payment_list_line_color', esc_html__( 'Lines between subscriptions', 'wp-easycart' ), array( $this->ec_sel( array( '.wpec-acc-card__subscription' ) ) => 'border-bottom-color: {{VALUE}};' ), array( 'separator' => 'before' ) );
			$this->end_controls_section();
		}
	}

endif;
