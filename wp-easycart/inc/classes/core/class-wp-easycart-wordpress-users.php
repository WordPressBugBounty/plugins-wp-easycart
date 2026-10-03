<?php
/**
 * WP EasyCart — store accounts and WordPress users.
 *
 * Nothing here runs unless the WordPress User Sync extension ( wp-easycart-enable-wp-users ) turns one of two switches on:
 *
 *   wp_easycart_use_wordpress_user    The WordPress login decides who the store customer is ( ec_user::init_wp_user() ). The
 *                                     store account is found through the link ( user meta wp_easycart_user_id ), else by email
 *                                     ( filter wp_easycart_wordpress_user_store_account may refuse ), else made for the WordPress
 *                                     user ( filter wp_easycart_wordpress_user_create_store_account may refuse ).
 *   wp_easycart_sync_wordpress_users  WordPress User Sync 1.x only: WP EasyCart itself creates the WordPress user at store
 *                                     sign-up, signs it in, and copies password and email changes ( the legacy_* methods ).
 *                                     2.0 answers false and does all of this on the neutral hooks below.
 *
 * Neutral hooks ( 6.0.2 ):
 *   wpeasycart_account_added( $user_id, $email, $password, $context )   context register | checkout | subscription | admin
 *   wpeasycart_password_set( $user_id, $password, $context )            a password the store set ( change | reset | admin ),
 *                                                                        slashed the way WordPress's own forms post it
 *   wpeasycart_password_reset_forced( $user_id )                        the merchant's forced reset scrambled the password
 *   wpeasycart_account_email_exists     filter( $exists, $email ): store sign-up email checks ( self::email_taken() )
 *   wpeasycart_account_email_change_error filter( '', $user_id, $new, $old ): My Account email change
 *   wpeasycart_logout( $user_id )                                        store sign-out ( My Account and the cart )
 *   wp_easycart_wordpress_user_linked / _unlinked( $wp_user_id, $store_user_id )
 *
 * The link is user meta wp_easycart_user_id on the WordPress user. 1.x wrote wpeasycart_user_id right after the store made
 * the WordPress user, so that key also marks the user as made by the store ( MADE_META ) and is moved to the new key on read.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_wordpress_users' ) ) :

	/**
	 * Store accounts and WordPress users.
	 */
	final class wp_easycart_wordpress_users {

		/** The link: the store account's user_id, on the WordPress user. */
		const META = 'wp_easycart_user_id';

		/** The key WordPress User Sync 1.x and WP EasyCart before 6.0.2 wrote at store sign-up. */
		const LEGACY_META = 'wpeasycart_user_id';

		/** Set on WordPress users the store created ( their password may follow the store's ). */
		const MADE_META = 'wp_easycart_user_made';

		/** How long an admin's "Login as Customer" lasts on the storefront. */
		const IMPERSONATE_TTL = 43200;

		/**
		 * The store is writing a WordPress password: the WordPress → store copy skips it.
		 *
		 * @var bool
		 */
		private static $writing = false;

		/**
		 * Hooks.
		 */
		public static function init() {
			self::retire_legacy_extension_hooks();
			add_action( 'plugins_loaded', array( __CLASS__, 'retire_legacy_extension_hooks' ), 1 );
			add_action( 'wpeasycart_account_deleted', array( __CLASS__, 'unlink_store' ) );
			add_action( 'password_reset', array( __CLASS__, 'wp_password_reset' ), 10, 2 );
			add_action( 'wp_set_password', array( __CLASS__, 'wp_password_set' ), 10, 2 );
			add_action( 'profile_update', array( __CLASS__, 'wp_profile_update' ), 10, 2 );
			add_filter( 'wpeasycart_password_verify', array( __CLASS__, 'legacy_verify_password' ), 5, 4 );
		}

		/**
		 * WordPress User Sync 1.x copies accounts itself.
		 *
		 * @return bool
		 */
		public static function legacy() {
			return (bool) apply_filters( 'wp_easycart_sync_wordpress_users', false );
		}

		/**
		 * The WordPress login decides who the store customer is.
		 *
		 * @return bool
		 */
		public static function wp_mode() {
			return (bool) apply_filters( 'wp_easycart_use_wordpress_user', false );
		}

		/**
		 * WordPress User Sync 1.0.x hooked two callbacks that must not run with this WP EasyCart: a storefront hook that turned
		 * the store session's email into a WordPress login, and an init callback that rewrote the checkout session and emptied
		 * the object cache on every request. This WP EasyCart does their useful part itself ( ec_user::init_wp_user() ).
		 */
		public static function retire_legacy_extension_hooks() {
			if ( defined( 'WP_EASYCART_USERSYNC_VERSION' ) || ! function_exists( 'wp_easycart_enable_wp_users' ) || ! class_exists( 'wp_easycart_enable_wp_users' ) ) {
				return;
			}
			$extension = wp_easycart_enable_wp_users();
			remove_filter( 'determine_current_user', array( $extension, 'maybe_user_is_logged_in' ) );
			remove_action( 'init', array( $extension, 'maybe_login_wp_easycart_user' ) );
		}

		// ------------------------------------------------------------------
		// Links.
		// ------------------------------------------------------------------

		/**
		 * The store account linked to a WordPress user ( 0 when none, or when it no longer exists ).
		 *
		 * @param int $wp_user_id WordPress user.
		 * @return int
		 */
		public static function store_user_for( $wp_user_id ) {
			$wp_user_id = (int) $wp_user_id;
			if ( $wp_user_id <= 0 ) {
				return 0;
			}
			$store_user_id = (int) get_user_meta( $wp_user_id, self::META, true );
			if ( ! $store_user_id ) {
				$legacy = (int) get_user_meta( $wp_user_id, self::LEGACY_META, true );
				if ( $legacy ) {
					update_user_meta( $wp_user_id, self::META, $legacy );
					update_user_meta( $wp_user_id, self::MADE_META, 1 );
					delete_user_meta( $wp_user_id, self::LEGACY_META );
					$store_user_id = $legacy;
				}
			}
			if ( $store_user_id && ! self::store_account_exists( $store_user_id ) ) {
				/* Deleted or merged away: forget the link so the email lookup can find the account that is left. */
				delete_user_meta( $wp_user_id, self::META );
				$store_user_id = 0;
			}
			return $store_user_id;
		}

		/**
		 * The WordPress user linked to a store account ( 0 when none ).
		 *
		 * @param int $store_user_id Store account.
		 * @return int
		 */
		public static function wp_user_for( $store_user_id ) {
			global $wpdb;
			$store_user_id = (int) $store_user_id;
			if ( $store_user_id <= 0 ) {
				return 0;
			}
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ( %s, %s ) AND meta_value = %s ORDER BY umeta_id ASC", self::META, self::LEGACY_META, (string) $store_user_id ) );
			foreach ( (array) $rows as $wp_user_id ) {
				if ( get_userdata( (int) $wp_user_id ) && self::store_user_for( (int) $wp_user_id ) === $store_user_id ) {
					return (int) $wp_user_id;
				}
			}
			return 0;
		}

		/**
		 * Link a WordPress user to a store account ( one to one ).
		 *
		 * @param int    $wp_user_id    WordPress user.
		 * @param int    $store_user_id Store account.
		 * @param string $how           made | email | confirmed | admin | login | wordpress ( 6.0.2: a store account made for a WordPress user ).
		 * @return bool
		 */
		public static function link( $wp_user_id, $store_user_id, $how = '' ) {
			$wp_user_id    = (int) $wp_user_id;
			$store_user_id = (int) $store_user_id;
			if ( $wp_user_id <= 0 || $store_user_id <= 0 ) {
				return false;
			}
			$other = self::wp_user_for( $store_user_id );
			if ( $other && $other !== $wp_user_id ) {
				return false;
			}
			update_user_meta( $wp_user_id, self::META, $store_user_id );
			delete_user_meta( $wp_user_id, self::LEGACY_META );
			if ( 'made' === $how ) {
				update_user_meta( $wp_user_id, self::MADE_META, 1 );
			}
			/**
			 * A WordPress user and a store account were linked.
			 *
			 * @since 6.0.2
			 * @param int    $wp_user_id    WordPress user.
			 * @param int    $store_user_id Store account.
			 * @param string $how           made | email | confirmed | admin | login | wordpress ( 6.0.2: a store account made for a WordPress user ).
			 */
			do_action( 'wp_easycart_wordpress_user_linked', $wp_user_id, $store_user_id, $how );
			return true;
		}

		/**
		 * Remove every link to a store account ( action wpeasycart_account_deleted ).
		 *
		 * @param int $store_user_id Store account.
		 */
		public static function unlink_store( $store_user_id ) {
			global $wpdb;
			$store_user_id = (int) $store_user_id;
			if ( $store_user_id <= 0 ) {
				return;
			}
			$wp_user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ( %s, %s ) AND meta_value = %s", self::META, self::LEGACY_META, (string) $store_user_id ) );
			foreach ( (array) $wp_user_ids as $wp_user_id ) {
				delete_user_meta( (int) $wp_user_id, self::META );
				delete_user_meta( (int) $wp_user_id, self::LEGACY_META );
				/**
				 * A WordPress user and a store account were unlinked.
				 *
				 * @since 6.0.2
				 * @param int $wp_user_id    WordPress user.
				 * @param int $store_user_id Store account.
				 */
				do_action( 'wp_easycart_wordpress_user_unlinked', (int) $wp_user_id, $store_user_id );
			}
		}

		/**
		 * The store created this WordPress user.
		 *
		 * @param int $wp_user_id WordPress user.
		 * @return bool
		 */
		public static function made_by_store( $wp_user_id ) {
			$wp_user_id = (int) $wp_user_id;
			return $wp_user_id > 0 && ( get_user_meta( $wp_user_id, self::MADE_META, true ) || get_user_meta( $wp_user_id, self::LEGACY_META, true ) );
		}

		/**
		 * A WordPress user who works on the site ( their password is never changed from the storefront ).
		 *
		 * @param int|WP_User $wp_user WordPress user.
		 * @return bool
		 */
		public static function is_staff( $wp_user ) {
			return user_can( $wp_user, 'edit_posts' ) || user_can( $wp_user, 'manage_options' ) || user_can( $wp_user, 'wpec_users' ) || user_can( $wp_user, 'wpec_orders' );
		}

		/**
		 * A store account row exists.
		 *
		 * @param int $store_user_id Store account.
		 * @return bool
		 */
		public static function store_account_exists( $store_user_id ) {
			global $wpdb;
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE user_id = %d', (int) $store_user_id ) );
		}

		/**
		 * The store account a signed-in WordPress user shops as: the link, else the account with the same email ( when the filter
		 * allows ), else a new one ( when the filter allows ). 0 = none.
		 *
		 * @param WP_User $wp_user WordPress user.
		 * @return int
		 */
		public static function resolve_store_account( $wp_user ) {
			global $wpdb;
			if ( ! $wp_user || empty( $wp_user->ID ) ) {
				return 0;
			}
			$linked = self::store_user_for( $wp_user->ID );
			if ( $linked ) {
				return $linked;
			}
			$email = (string) $wp_user->user_email;
			if ( '' === $email ) {
				return 0;
			}
			$by_email = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s', $email ) );
			if ( $by_email ) {
				if ( self::wp_user_for( $by_email ) ) {
					return 0; // Another WordPress user holds it.
				}
				/**
				 * Link a signed-in WordPress user to the store account with the same email. Return 0 to keep them apart
				 * ( WordPress User Sync 2.0 asks the customer to confirm first ).
				 *
				 * @since 6.0.2
				 * @param int     $store_user_id The store account with the same email.
				 * @param WP_User $wp_user       The WordPress user.
				 */
				$by_email = (int) apply_filters( 'wp_easycart_wordpress_user_store_account', $by_email, $wp_user );
				if ( $by_email && self::link( $wp_user->ID, $by_email, 'email' ) ) {
					return $by_email;
				}
				return 0;
			}
			/**
			 * Make a store account for a signed-in WordPress user who has none.
			 *
			 * @since 6.0.2
			 * @param bool    $create  Default true.
			 * @param WP_User $wp_user The WordPress user.
			 */
			if ( ! apply_filters( 'wp_easycart_wordpress_user_create_store_account', true, $wp_user ) ) {
				return 0;
			}
			/* Straight insert: a store account made for a WordPress user skips the sign-up side of ec_db::insert_user() ( no
			   account_added event ). 6.0.2: its newsletter box starts ticked when the address is already on the list, as
			   insert_user() does. */
			$listed        = class_exists( 'wp_easycart_subscribers' ) && wp_easycart_subscribers::is_subscribed( $email );
			$inserted      = $wpdb->insert(
				'ec_user',
				array(
					'email'         => $email,
					'password'      => wp_easycart_hash_password( wp_generate_password( 32, true, true ) ),
					'first_name'    => (string) $wp_user->first_name,
					'last_name'     => (string) $wp_user->last_name,
					'user_level'    => 'shopper',
					'user_notes'    => '',
					'is_subscriber' => $listed ? 1 : 0,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
			);
			$store_user_id = $inserted ? (int) $wpdb->insert_id : 0;
			if ( ! $store_user_id ) {
				return 0;
			}
			self::link( $wp_user->ID, $store_user_id, 'wordpress' );
			if ( function_exists( 'wp_easycart_log_user_activity' ) ) {
				wp_easycart_log_user_activity(
					$store_user_id,
					'account_created',
					array(
						'actor_type' => 'system',
						'meta'       => array( 'source' => 'wordpress_sync' ),
					)
				);
			}
			/**
			 * A store account was made for a WordPress user.
			 *
			 * @since 6.0.2
			 * @param int     $store_user_id Store account.
			 * @param WP_User $wp_user       WordPress user.
			 */
			do_action( 'wp_easycart_wordpress_user_store_account_created', $store_user_id, $wp_user );
			return $store_user_id;
		}

		// ------------------------------------------------------------------
		// Signing in and out.
		// ------------------------------------------------------------------

		/**
		 * Sign a WordPress user in for this request and the next ones, and point $GLOBALS['ec_user'] at their store account.
		 *
		 * @param int  $wp_user_id WordPress user.
		 * @param bool $remember   Long-lived login cookie.
		 * @return bool
		 */
		public static function sign_in( $wp_user_id, $remember = true ) {
			$wp_user_id = (int) $wp_user_id;
			if ( $wp_user_id <= 0 || ! get_userdata( $wp_user_id ) ) {
				return false;
			}
			if ( ! headers_sent() ) {
				wp_set_auth_cookie( $wp_user_id, (bool) $remember, is_ssl() );
			}
			wp_set_current_user( $wp_user_id );
			self::refresh_current_customer();
			return true;
		}

		/**
		 * Rebuild $GLOBALS['ec_user'] from the current WordPress login.
		 */
		public static function refresh_current_customer() {
			if ( self::wp_mode() && class_exists( 'ec_user' ) ) {
				$GLOBALS['ec_user'] = new ec_user( '' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WP EasyCart's own customer global.
			}
		}

		/**
		 * Store sign-out ( My Account and the cart ): ends an admin's Login as Customer, else signs out of WordPress too when
		 * WordPress User Sync 1.x is on. 2.0 signs out of WordPress on wpeasycart_logout itself.
		 */
		public static function store_logout() {
			if ( self::stop_impersonating() ) {
				return;
			}
			if ( self::legacy() && is_user_logged_in() ) {
				wp_logout();
			}
		}

		/**
		 * Clear the store session's customer and start a fresh session id ( used when WordPress signs out on its own ).
		 */
		public static function end_customer_session() {
			if ( ! isset( $GLOBALS['ec_cart_data'] ) || ! is_object( $GLOBALS['ec_cart_data'] ) || ! isset( $GLOBALS['ec_cart_data']->cart_data ) || ! is_object( $GLOBALS['ec_cart_data']->cart_data ) ) {
				return;
			}
			$cart_data = $GLOBALS['ec_cart_data']->cart_data;
			if ( empty( $cart_data->user_id ) ) {
				return;
			}
			$fields = array(
				'user_id',
				'email',
				'username',
				'first_name',
				'last_name',
				'is_guest',
				'guest_key',
				'email_other',
				'vat_registration_number',
				'billing_first_name',
				'billing_last_name',
				'billing_company_name',
				'billing_address_line_1',
				'billing_address_line_2',
				'billing_city',
				'billing_state',
				'billing_zip',
				'billing_country',
				'billing_phone',
				'shipping_selector',
				'shipping_first_name',
				'shipping_last_name',
				'shipping_company_name',
				'shipping_address_line_1',
				'shipping_address_line_2',
				'shipping_city',
				'shipping_state',
				'shipping_zip',
				'shipping_country',
				'shipping_phone',
				'create_account',
				'order_notes',
				'shipping_method',
				'expedited_shipping',
				'coupon_code',
				'giftcard',
				'stripe_paymentintent_id',
				'stripe_pi_client_secret',
			);
			foreach ( $fields as $field ) {
				if ( property_exists( $cart_data, $field ) ) {
					$cart_data->{$field} = '';
				}
			}
			$GLOBALS['ec_cart_data']->save_session_to_db();
			if ( function_exists( 'wpeasycart_session' ) ) {
				$previous = $GLOBALS['ec_cart_data']->ec_cart_id;
				wpeasycart_session()->rotate_session_id();
				if ( function_exists( 'wp_easycart_offers_active' ) && wp_easycart_offers_active() && class_exists( 'ec_offer_integration' ) ) {
					ec_offer_integration::migrate_session_codes( $previous );
				}
			}
			$GLOBALS['ec_user'] = new ec_user( '' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WP EasyCart's own customer global.
		}

		/**
		 * Fill the checkout session from a store account when a WordPress login first brings it in ( only fields the shopper
		 * has not typed yet ).
		 *
		 * @param int $store_user_id Store account.
		 */
		public static function prefill_session( $store_user_id ) {
			if ( ! isset( $GLOBALS['ec_cart_data'] ) || ! is_object( $GLOBALS['ec_cart_data'] ) || ! isset( $GLOBALS['ec_cart_data']->cart_data ) ) {
				return;
			}
			$cart_data = $GLOBALS['ec_cart_data']->cart_data;
			if ( '' !== (string) $cart_data->billing_first_name && '' !== (string) $cart_data->billing_address_line_1 ) {
				return;
			}
			$row = ec_db::get_user( (int) $store_user_id, self::store_email( $store_user_id ) );
			if ( ! $row || '' === (string) $row->billing_first_name ) {
				return;
			}
			foreach ( array( 'first_name', 'last_name', 'company_name', 'address_line_1', 'address_line_2', 'city', 'state', 'zip', 'country', 'phone' ) as $part ) {
				$billing                = 'billing_' . $part;
				$shipping               = 'shipping_' . $part;
				$cart_data->{$billing}  = isset( $row->{$billing} ) ? (string) $row->{$billing} : '';
				$cart_data->{$shipping} = ( '' !== (string) $row->shipping_first_name && isset( $row->{$shipping} ) ) ? (string) $row->{$shipping} : $cart_data->{$billing};
			}
			$cart_data->shipping_selector = '';
		}

		/**
		 * A store account's email.
		 *
		 * @param int $store_user_id Store account.
		 * @return string
		 */
		public static function store_email( $store_user_id ) {
			global $wpdb;
			return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ec_user WHERE user_id = %d', (int) $store_user_id ) );
		}

		// ------------------------------------------------------------------
		// Login as Customer while the WordPress login leads.
		// ------------------------------------------------------------------

		/**
		 * The transient that holds an admin's Login as Customer ( per WordPress login session ).
		 *
		 * @return string '' without a WordPress login.
		 */
		private static function impersonation_key() {
			$wp_user_id = get_current_user_id();
			$token      = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '';
			if ( ! $wp_user_id || '' === $token ) {
				return '';
			}
			return 'wpec_imp_' . $wp_user_id . '_' . substr( hash( 'sha256', $token ), 0, 20 );
		}

		/**
		 * Start showing the storefront as a customer ( "Login as Customer" ) while the admin keeps their WordPress login.
		 *
		 * @param int $store_user_id Store account.
		 * @return bool
		 */
		public static function impersonate( $store_user_id ) {
			$key = self::impersonation_key();
			if ( ! self::wp_mode() || '' === $key || (int) $store_user_id <= 0 ) {
				return false;
			}
			set_transient( $key, (int) $store_user_id, self::IMPERSONATE_TTL );
			return true;
		}

		/**
		 * The customer an admin is viewing the storefront as ( 0 = none ).
		 *
		 * @return int
		 */
		public static function impersonating() {
			$key = self::impersonation_key();
			if ( '' === $key ) {
				return 0;
			}
			$store_user_id = (int) get_transient( $key );
			if ( $store_user_id && ( ! ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_users' ) ) || ! self::store_account_exists( $store_user_id ) ) ) {
				delete_transient( $key );
				return 0;
			}
			return $store_user_id;
		}

		/**
		 * Stop a Login as Customer.
		 *
		 * @return bool One was running.
		 */
		public static function stop_impersonating() {
			$key = self::impersonation_key();
			if ( '' === $key || ! get_transient( $key ) ) {
				return false;
			}
			delete_transient( $key );
			return true;
		}

		// ------------------------------------------------------------------
		// Store events, for WordPress User Sync 2.0 ( neutral hooks ) and 1.x ( legacy copies ).
		// ------------------------------------------------------------------

		/**
		 * A store sign-up email is taken: by a store account, or ( filter ) by a WordPress account WordPress User Sync wants
		 * signed in instead.
		 *
		 * @param string $email Email.
		 * @return bool
		 */
		public static function email_taken( $email ) {
			$exists = (bool) ec_db::does_user_exist( $email );
			/**
			 * A store sign-up may not use this email ( WordPress User Sync 2.0: a WordPress account has it, sign in instead ).
			 *
			 * @since 6.0.2
			 * @param bool   $exists A store account has it.
			 * @param string $email  The email.
			 */
			return (bool) apply_filters( 'wpeasycart_account_email_exists', $exists, $email );
		}

		/**
		 * Why My Account may not change a store account's email ( '' = it may ).
		 *
		 * @param int    $store_user_id Store account.
		 * @param string $new_email     New email.
		 * @param string $old_email     Current email.
		 * @return string
		 */
		public static function email_change_error( $store_user_id, $new_email, $old_email ) {
			$error = '';
			if ( self::legacy() && strtolower( (string) $old_email ) !== strtolower( (string) $new_email ) ) {
				$owner = get_user_by( 'email', $new_email );
				if ( $owner && self::wp_user_for( $store_user_id ) !== (int) $owner->ID ) {
					$error = 'email_taken';
				}
			}
			/**
			 * Why My Account may not change a store account's email ( '' = it may ).
			 *
			 * @since 6.0.2
			 * @param string $error         '' or a short reason code.
			 * @param int    $store_user_id Store account.
			 * @param string $new_email     New email.
			 * @param string $old_email     Current email.
			 */
			return (string) apply_filters( 'wpeasycart_account_email_change_error', $error, (int) $store_user_id, (string) $new_email, (string) $old_email );
		}

		/**
		 * The store set a password ( My Account change, reset, admin ): fires wpeasycart_password_set and, for 1.x, copies it to
		 * the linked WordPress user and keeps them signed in.
		 *
		 * @param int    $store_user_id Store account.
		 * @param string $password      As typed, slashed the way WordPress's own forms post it.
		 * @param string $context       change | reset | admin.
		 */
		public static function password_set( $store_user_id, $password, $context ) {
			$store_user_id = (int) $store_user_id;
			if ( $store_user_id <= 0 || '' === (string) $password ) {
				return;
			}
			/**
			 * The store set an account's password.
			 *
			 * @since 6.0.2
			 * @param int    $store_user_id Store account.
			 * @param string $password      As typed, slashed the way WordPress's own forms post it.
			 * @param string $context       change | reset | admin.
			 */
			do_action( 'wpeasycart_password_set', $store_user_id, $password, $context );
			if ( ! self::legacy() ) {
				return;
			}
			$wp_user_id = self::wp_user_for( $store_user_id );
			if ( ! $wp_user_id || self::is_staff( $wp_user_id ) ) {
				return;
			}
			self::set_wp_password( $wp_user_id, $password );
		}

		/**
		 * Set a WordPress user's password from the store, and keep them signed in when it is the current user.
		 *
		 * @param int    $wp_user_id WordPress user.
		 * @param string $password   Password.
		 */
		public static function set_wp_password( $wp_user_id, $password ) {
			self::$writing = true;
			wp_set_password( $password, (int) $wp_user_id );
			self::$writing = false;
			if ( (int) get_current_user_id() === (int) $wp_user_id ) {
				self::sign_in( $wp_user_id, true );
			}
		}

		/**
		 * The merchant forced a password reset: fires wpeasycart_password_reset_forced and, for 1.x, scrambles the linked
		 * WordPress password and ends its sessions too.
		 *
		 * @param int $store_user_id Store account.
		 */
		public static function password_reset_forced( $store_user_id ) {
			/**
			 * The merchant forced a password reset on a store account.
			 *
			 * @since 6.0.2
			 * @param int $store_user_id Store account.
			 */
			do_action( 'wpeasycart_password_reset_forced', (int) $store_user_id );
			if ( ! self::legacy() ) {
				return;
			}
			$wp_user_id = self::wp_user_for( $store_user_id );
			if ( ! $wp_user_id || self::is_staff( $wp_user_id ) ) {
				return;
			}
			self::$writing = true;
			wp_set_password( wp_generate_password( 32, true, true ), $wp_user_id );
			self::$writing = false;
			if ( class_exists( 'WP_Session_Tokens' ) ) {
				WP_Session_Tokens::get_instance( $wp_user_id )->destroy_all();
			}
		}

		/**
		 * A username for a new WordPress user: the email's local part, made unique.
		 *
		 * @param string $email      Email.
		 * @param string $first_name First name.
		 * @param string $last_name  Last name.
		 * @return string
		 */
		public static function username_for( $email, $first_name = '', $last_name = '' ) {
			$base = sanitize_user( strtolower( (string) strstr( (string) $email, '@', true ) ), true );
			if ( strlen( $base ) < 3 ) {
				$base = sanitize_user( strtolower( trim( $first_name . '.' . $last_name, '.' ) ), true );
			}
			if ( strlen( $base ) < 3 ) {
				$base = 'customer';
			}
			$base     = substr( $base, 0, 50 );
			$username = $base;
			$i        = 2;
			while ( username_exists( $username ) ) {
				$username = $base . $i;
				++$i;
			}
			return $username;
		}

		/**
		 * The role for a WordPress user the store creates in 1.x mode: the site's default role, unless that role can edit
		 * content or manage the site.
		 *
		 * @return string
		 */
		public static function legacy_role() {
			$role = (string) get_option( 'default_role', 'subscriber' );
			$obj  = get_role( $role );
			if ( ! $obj || $obj->has_cap( 'edit_posts' ) || $obj->has_cap( 'manage_options' ) || $obj->has_cap( 'promote_users' ) ) {
				return 'subscriber';
			}
			return $role;
		}

		/**
		 * 1.x: a storefront sign-up made a store account. Create the WordPress user ( not while the account waits for email
		 * confirmation; its first sign-in creates it then ), link it and sign it in.
		 *
		 * @param int    $store_user_id Store account.
		 * @param string $email         Email.
		 * @param string $password      As typed ( slashed, as posted ).
		 * @param string $first_name    First name.
		 * @param string $last_name     Last name.
		 * @param bool   $sign_in       Sign in now.
		 * @return int WordPress user, 0 when none.
		 */
		public static function legacy_account_created( $store_user_id, $email, $password, $first_name = '', $last_name = '', $sign_in = true ) {
			global $wpdb;
			$store_user_id = (int) $store_user_id;
			if ( ! self::legacy() || $store_user_id <= 0 || '' === (string) $password ) {
				return 0;
			}
			if ( 'pending' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT user_level FROM ec_user WHERE user_id = %d', $store_user_id ) ) ) {
				return 0;
			}
			$wp_user_id = self::wp_user_for( $store_user_id );
			if ( ! $wp_user_id ) {
				if ( email_exists( $email ) ) {
					return 0; // A WordPress account already has this email: it is joined when its owner signs in with it.
				}
				$first_name = (string) $first_name;
				$last_name  = (string) $last_name;
				$wp_user_id = wp_insert_user(
					array(
						'user_pass'    => $password,
						'user_login'   => self::username_for( $email, $first_name, $last_name ),
						'user_email'   => $email,
						'first_name'   => $first_name,
						'last_name'    => $last_name,
						'nickname'     => trim( $first_name . ' ' . $last_name ),
						'display_name' => ( '' !== trim( $first_name . ' ' . $last_name ) ) ? trim( $first_name . ' ' . $last_name ) : $email,
						'role'         => self::legacy_role(),
					)
				);
				if ( is_wp_error( $wp_user_id ) || ! $wp_user_id ) {
					return 0;
				}
				self::link( (int) $wp_user_id, $store_user_id, 'made' );
			}
			if ( $sign_in ) {
				self::sign_in( (int) $wp_user_id, true );
			}
			return (int) $wp_user_id;
		}

		/**
		 * 1.x: a store login succeeded. Sign in to the linked WordPress user when its own password was typed, link a WordPress
		 * account whose own password was typed, or create the WordPress user when there is none.
		 *
		 * @param object $store_user Row from ec_db::get_user_login().
		 * @param string $email      Email.
		 * @param string $password   As typed ( slashed, as posted ).
		 */
		public static function legacy_store_login( $store_user, $email, $password ) {
			if ( ! self::legacy() || ! is_object( $store_user ) || empty( $store_user->user_id ) ) {
				return;
			}
			$store_user_id = (int) $store_user->user_id;
			$wp_user_id    = self::wp_user_for( $store_user_id );
			if ( ! $wp_user_id ) {
				$owner = get_user_by( 'email', $email );
				if ( ! $owner ) {
					self::legacy_account_created( $store_user_id, $email, $password, (string) $store_user->first_name, (string) $store_user->last_name, true );
					return;
				}
				if ( wp_check_password( $password, $owner->user_pass, $owner->ID ) && self::link( $owner->ID, $store_user_id, 'login' ) ) {
					self::sign_in( $owner->ID, true );
				}
				return;
			}
			$wp_user = get_userdata( $wp_user_id );
			if ( ! $wp_user ) {
				return;
			}
			/* 6.0.2: a WordPress password that does not match is never overwritten. The customer may have changed it in
			   WordPress ( wp_update_user() does not tell the store ), and the old store password must not undo that change.
			   The store sign-in stands; WordPress stays signed out, as before 6.0.2. A WordPress password typed at the store
			   login is let in by legacy_verify_password(), which also brings the store copy up to date. */
			if ( ! wp_check_password( $password, $wp_user->user_pass, $wp_user_id ) ) {
				return;
			}
			self::sign_in( $wp_user_id, true );
		}

		/**
		 * 1.x: My Account changed a store account's email; the linked WordPress user follows.
		 *
		 * @param int    $store_user_id Store account.
		 * @param string $email         New email.
		 */
		public static function legacy_email_changed( $store_user_id, $email ) {
			if ( ! self::legacy() ) {
				return;
			}
			$wp_user_id = self::wp_user_for( $store_user_id );
			if ( ! $wp_user_id ) {
				return;
			}
			$wp_user = get_userdata( $wp_user_id );
			if ( ! $wp_user || strtolower( $wp_user->user_email ) === strtolower( (string) $email ) ) {
				return;
			}
			$owner = get_user_by( 'email', $email );
			if ( $owner && (int) $owner->ID !== $wp_user_id ) {
				return;
			}
			wp_update_user(
				array(
					'ID'         => $wp_user_id,
					'user_email' => $email,
				)
			);
		}

		/**
		 * 1.x: a store login whose store password no longer matches still succeeds with the linked WordPress password ( one the
		 * customer changed in WordPress ), and the store copy is brought up to date ( filter wpeasycart_password_verify ).
		 *
		 * @param bool   $verified     The store's own check.
		 * @param string $raw_password Typed password.
		 * @param string $stored_hash  Store hash.
		 * @param object $user         Store row ( user_id, email, password ).
		 * @return bool
		 */
		public static function legacy_verify_password( $verified, $raw_password, $stored_hash, $user = null ) {
			global $wpdb;
			if ( $verified || ! self::legacy() || ! is_object( $user ) || empty( $user->user_id ) || '' === (string) $raw_password ) {
				return $verified;
			}
			$wp_user_id = self::wp_user_for( (int) $user->user_id );
			$wp_user    = $wp_user_id ? get_userdata( $wp_user_id ) : false;
			if ( ! $wp_user || ! wp_check_password( $raw_password, $wp_user->user_pass, $wp_user_id ) ) {
				return $verified;
			}
			$hash = apply_filters( 'wpeasycart_password_hash', wp_easycart_hash_password( $raw_password ), $raw_password );
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET password = %s WHERE user_id = %d', $hash, (int) $user->user_id ) );
			wp_cache_delete( 'wpeasycart-user-' . (int) $user->user_id, 'wpeasycart-user' );
			return true;
		}

		// ------------------------------------------------------------------
		// WordPress events: the store copy follows.
		// ------------------------------------------------------------------

		/**
		 * Keep a linked store account's password in step with WordPress ( both modes ), so turning the extension off never
		 * locks a customer out of the store's own login.
		 *
		 * @param int    $wp_user_id WordPress user.
		 * @param string $password   New password.
		 */
		private static function copy_wp_password( $wp_user_id, $password ) {
			global $wpdb;
			if ( self::$writing || '' === (string) $password || ! ( self::legacy() || self::wp_mode() ) ) {
				return;
			}
			$store_user_id = self::store_user_for( $wp_user_id );
			if ( ! $store_user_id ) {
				return;
			}
			$hash = apply_filters( 'wpeasycart_password_hash', wp_easycart_hash_password( $password ), $password );
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET password = %s WHERE user_id = %d', $hash, $store_user_id ) );
			wp_cache_delete( 'wpeasycart-user-' . $store_user_id, 'wpeasycart-user' );
		}

		/**
		 * Action password_reset ( WordPress's lost-password form ).
		 *
		 * @param WP_User $wp_user  WordPress user.
		 * @param string  $password New password.
		 */
		public static function wp_password_reset( $wp_user, $password ) {
			if ( is_object( $wp_user ) && ! empty( $wp_user->ID ) ) {
				self::copy_wp_password( (int) $wp_user->ID, $password );
			}
		}

		/**
		 * Action wp_set_password ( WordPress 6.2+ ).
		 *
		 * @param string $password   New password.
		 * @param int    $wp_user_id WordPress user.
		 */
		public static function wp_password_set( $password, $wp_user_id ) {
			self::copy_wp_password( (int) $wp_user_id, $password );
		}

		/**
		 * Action profile_update: a linked store account takes the WordPress user's new email, when no other store account has it.
		 *
		 * @param int     $wp_user_id    WordPress user.
		 * @param WP_User $old_user_data Before the update.
		 */
		public static function wp_profile_update( $wp_user_id, $old_user_data = null ) {
			global $wpdb;
			if ( ! ( self::legacy() || self::wp_mode() ) || ! is_object( $old_user_data ) ) {
				return;
			}
			$wp_user = get_userdata( (int) $wp_user_id );
			if ( ! $wp_user || strtolower( (string) $wp_user->user_email ) === strtolower( (string) $old_user_data->user_email ) ) {
				return;
			}
			$store_user_id = self::store_user_for( (int) $wp_user_id );
			if ( ! $store_user_id ) {
				return;
			}
			$taken = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s AND user_id != %d', $wp_user->user_email, $store_user_id ) );
			if ( $taken ) {
				return;
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_user SET email = %s WHERE user_id = %d', $wp_user->user_email, $store_user_id ) );
			wp_cache_delete( 'wpeasycart-user-' . $store_user_id, 'wpeasycart-user' );
			/**
			 * A linked store account took its WordPress user's new email.
			 *
			 * @since 6.0.2
			 * @param int    $store_user_id Store account.
			 * @param string $email         New email.
			 * @param string $old_email     Previous email.
			 */
			do_action( 'wp_easycart_wordpress_user_email_copied', $store_user_id, (string) $wp_user->user_email, (string) $old_user_data->user_email );
		}
	}

	wp_easycart_wordpress_users::init();

endif;
