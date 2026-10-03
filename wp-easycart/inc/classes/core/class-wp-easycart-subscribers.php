<?php
/**
 * Newsletter subscribers ( 6.0.2 ): one reliable event and a consent record.
 *
 * The ec_subscriber table is written from many places: the checkout, My Account, registration, the newsletter widget and popup, the
 * admin subscriber list and customer screens, imports, and now extensions that keep a mailing service in step. Each place
 * fired its own older hook ( wpeasycart_insert_subscriber, wpeasycart_remove_subscriber, wpeasycart_subscriber_added ), some
 * twice and some not at all. Every one of them now also announces the change once through
 *
 *     do_action( 'wp_easycart_subscriber_changed', string $email, string $state, array $context )
 *
 * with $state subscribed | unsubscribed | updated, at most once per address and state in a request ( see fire() ).
 *
 * A sign-up also keeps a consent record on its row ( EC_UPGRADE_DB 115 ): when ( date_added, database time ), where
 * ( source, a short slug from source() ), the visitor's address ( ip_address, storefront sign-ups only ) and the matching
 * customer account ( user_id ). The record is written on the first insert only; later sign-ups for the same address change
 * the names. Stores that have not run the database update yet keep working without it ( columns_ready() ).
 *
 * The older hooks still fire as before: add() and remove() go through ec_db, so WP EasyCart PRO's mailing list connections
 * keep hearing about every change.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_subscribers' ) ) :

	// phpcs:disable PEAR.NamingConventions.ValidClassName -- WP EasyCart class names are lower case.
	/**
	 * Subscriber changes, their event and the consent record.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_subscribers {
		// phpcs:enable PEAR.NamingConventions.ValidClassName

		/** Longest source slug ( ec_subscriber.source is varchar(20) ). */
		const SOURCE_LENGTH = 20;

		/** Longest address ( ec_subscriber.ip_address is varchar(45), an IPv6 address with an IPv4 tail ). */
		const IP_LENGTH = 45;

		/**
		 * The source set for this request with set_source(), '' to guess.
		 *
		 * @var string
		 */
		private static $source = '';

		/**
		 * Consent columns found ( per request ).
		 *
		 * @var bool|null
		 */
		private static $columns_ready = null;

		/**
		 * Events already fired in this request, email|state => true.
		 *
		 * @var array
		 */
		private static $fired = array();

		// Consent record.

		/**
		 * Does ec_subscriber have the consent columns ( the 6.0.2 database update has run )? Read once per request.
		 *
		 * @since 6.0.2
		 * @return bool
		 */
		public static function columns_ready() {
			global $wpdb;
			if ( null === self::$columns_ready ) {
				$columns             = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ec_subscriber' );
				self::$columns_ready = ! array_diff( array( 'date_added', 'source', 'ip_address', 'user_id' ), $columns );
			}
			return self::$columns_ready;
		}

		/**
		 * Where the current request signs someone up: the slug set with set_source(), or else a guess from the request.
		 *
		 * Guesses: newsletter ( the widget, the popup, ec_ajax_submit_newsletter_signup ), register ( the My Account sign-up
		 * form ), account ( other My Account forms ), checkout ( the cart page and storefront AJAX ), admin ( wp-admin and admin
		 * AJAX ), other ( anything else: cron, REST, WP-CLI ).
		 *
		 * @since 6.0.2
		 * @return string A short slug ( at most 20 characters ).
		 */
		public static function source() {
			$source = ( '' !== self::$source ) ? self::$source : self::guess_source();
			/**
			 * Where the current request signs someone up.
			 *
			 * @since 6.0.2
			 * @param string $source checkout | account | register | newsletter | admin | other, or the slug set with
			 *                       wp_easycart_subscribers::set_source() ( import, privacy, an extension's own slug ).
			 */
			return self::clean_source( apply_filters( 'wp_easycart_subscriber_source', $source ) );
		}

		/**
		 * Say where the changes this request makes come from, until reset with set_source( '' ). Importers use import,
		 * extensions applying a mailing service's changes use their own short slug ( mailchimp ), so they can skip their own
		 * changes when the event comes back to them.
		 *
		 * @since 6.0.2
		 * @param string $source Short slug, or '' to go back to guessing.
		 */
		public static function set_source( $source ) {
			$source       = (string) $source;
			self::$source = ( '' === $source ) ? '' : self::clean_source( $source );
		}

		/**
		 * The storefront visitor's address: checkout protection's ( which trusts proxy headers only where they can't be
		 * faked ) when it is loaded, else REMOTE_ADDR. '' for admin requests.
		 *
		 * @since 6.0.2
		 * @return string
		 */
		public static function client_ip() {
			if ( self::is_admin_request() ) {
				return '';
			}
			if ( class_exists( 'wp_easycart_checkout_guard' ) && method_exists( 'wp_easycart_checkout_guard', 'client_ip' ) ) {
				$ip = (string) wp_easycart_checkout_guard::client_ip();
			} else {
				$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			}
			$ip = trim( $ip );
			return ( '' !== $ip && false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) ? substr( $ip, 0, self::IP_LENGTH ) : '';
		}

		/**
		 * The consent record a new sign-up gets ( date_added is the database's NOW() ). The visitor's address is only kept for
		 * storefront sign-ups ( checkout, account, register, newsletter ) the request itself makes: a source set with
		 * set_source() ( an import, a mailing service's webhook ) or a request with no visitor ( other: cron, REST, a payment
		 * webhook ) is not the visitor signing up.
		 *
		 * @since 6.0.2
		 * @param string $email Address being signed up.
		 * @return array { source, ip_address, user_id }
		 */
		public static function consent( $email ) {
			global $wpdb;
			$source = self::source();
			$ip     = ( '' === self::$source && in_array( $source, array( 'checkout', 'account', 'register', 'newsletter' ), true ) ) ? self::client_ip() : '';
			/**
			 * The visitor address kept with a new sign-up ( '' keeps none ).
			 *
			 * @since 6.0.2
			 * @param string $ip     Address.
			 * @param string $source Source slug.
			 * @param string $email  Address being signed up.
			 */
			$ip      = (string) apply_filters( 'wp_easycart_subscriber_ip', $ip, $source, (string) $email );
			$user_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s LIMIT 1', trim( (string) $email ) ) );
			return array(
				'source'     => $source,
				'ip_address' => ( '' !== $ip && false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) ? substr( $ip, 0, self::IP_LENGTH ) : '',
				'user_id'    => $user_id,
			);
		}

		/**
		 * Source slugs and what the admin calls them.
		 *
		 * @since 6.0.2
		 * @return array slug => label
		 */
		public static function source_labels() {
			/**
			 * Names for sign-up sources ( an extension adds its own slug ).
			 *
			 * @since 6.0.2
			 * @param array $labels slug => label.
			 */
			return (array) apply_filters(
				'wp_easycart_subscriber_source_labels',
				array(
					'checkout'   => __( 'Checkout', 'wp-easycart' ),
					'account'    => __( 'My Account', 'wp-easycart' ),
					'register'   => __( 'Account sign-up', 'wp-easycart' ),
					'newsletter' => __( 'Newsletter form', 'wp-easycart' ),
					'admin'      => __( 'Added by staff', 'wp-easycart' ),
					'import'     => __( 'Import', 'wp-easycart' ),
					'other'      => __( 'Other', 'wp-easycart' ),
				)
			);
		}

		/**
		 * One source's label ( an unknown slug is shown as words ).
		 *
		 * @since 6.0.2
		 * @param string $source Source slug.
		 * @return string '' when there is none.
		 */
		public static function source_label( $source ) {
			$source = (string) $source;
			$labels = self::source_labels();
			if ( isset( $labels[ $source ] ) ) {
				return (string) $labels[ $source ];
			}
			return ( '' === $source ) ? '' : ucwords( str_replace( array( '-', '_' ), ' ', $source ) );
		}

		// Reading.

		/**
		 * The subscriber row for an address ( trimmed, any case ).
		 *
		 * @since 6.0.2
		 * @param string $email Address.
		 * @return object|null ec_subscriber row.
		 */
		public static function get( $email ) {
			global $wpdb;
			$email = trim( (string) $email );
			if ( '' === $email ) {
				return null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_subscriber WHERE email = %s LIMIT 1', $email ) );
			if ( ! $row ) {
				/* Older rows can carry spaces or, under a case-sensitive collation, capitals. */
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_subscriber WHERE LOWER( TRIM( email ) ) = %s LIMIT 1', strtolower( $email ) ) );
			}
			return $row ? $row : null;
		}

		/**
		 * Is the address on the newsletter list?
		 *
		 * @since 6.0.2
		 * @param string $email Address.
		 * @return bool
		 */
		public static function is_subscribed( $email ) {
			return null !== self::get( $email );
		}

		// Changing.

		/**
		 * Subscribe an address, through ec_db::insert_subscriber() so the older hooks fire too, and tick the matching
		 * customer account's newsletter box. Names left empty keep the ones already saved.
		 *
		 * @since 6.0.2
		 * @param string $email  Address.
		 * @param string $first  First name.
		 * @param string $last   Last name.
		 * @param string $source Source slug for this change ( '' = source() ).
		 * @return int subscriber_id, 0 when the address is not valid.
		 */
		public static function add( $email, $first = '', $last = '', $source = '' ) {
			$email = trim( (string) $email );
			if ( ! is_email( $email ) ) {
				return 0;
			}
			$first = sanitize_text_field( (string) $first );
			$last  = sanitize_text_field( (string) $last );
			if ( '' === $first && '' === $last ) {
				$row = self::get( $email );
				if ( $row ) {
					$first = (string) $row->first_name;
					$last  = (string) $row->last_name;
				}
			}
			$previous = self::$source;
			if ( '' !== (string) $source ) {
				self::set_source( $source );
			}
			$db = new ec_db();
			$db->insert_subscriber( $email, $first, $last );
			self::$source = $previous;
			self::set_account_flag( $email, 1 );
			$row = self::get( $email );
			return $row ? (int) $row->subscriber_id : 0;
		}

		/**
		 * Unsubscribe an address, through ec_db::remove_subscriber() so the older hooks fire too, and untick the matching
		 * customer account's newsletter box.
		 *
		 * @since 6.0.2
		 * @param string $email  Address.
		 * @param string $source Source slug for this change ( '' = source() ).
		 * @return bool Whether the address was subscribed.
		 */
		public static function remove( $email, $source = '' ) {
			$email = trim( (string) $email );
			if ( '' === $email ) {
				return false;
			}
			$row      = self::get( $email );
			$previous = self::$source;
			if ( '' !== (string) $source ) {
				self::set_source( $source );
			}
			$db = new ec_db();
			$db->remove_subscriber( $row ? trim( (string) $row->email ) : $email );
			self::$source = $previous;
			self::set_account_flag( $email, 0 );
			return (bool) $row;
		}

		/**
		 * Move a subscription to a new address, keeping its consent record, and fire the event with state updated ( context
		 * old_email ). When the new address already has its own subscription, that one is kept and the old row goes.
		 *
		 * @since 6.0.2
		 * @param string $old_email Address now on the list.
		 * @param string $new_email New address.
		 * @return bool Whether a subscription moved.
		 */
		public static function change_email( $old_email, $new_email ) {
			global $wpdb;
			$old_email = trim( (string) $old_email );
			$new_email = trim( (string) $new_email );
			if ( '' === $old_email || ! is_email( $new_email ) || strtolower( $old_email ) === strtolower( $new_email ) ) {
				return false;
			}
			$row = self::get( $old_email );
			if ( ! $row ) {
				return false;
			}
			$target = self::get( $new_email );
			if ( $target && (int) $target->subscriber_id !== (int) $row->subscriber_id ) {
				$wpdb->delete( 'ec_subscriber', array( 'subscriber_id' => (int) $row->subscriber_id ), array( '%d' ) );
				$kept = $target;
			} else {
				$wpdb->update( 'ec_subscriber', array( 'email' => $new_email ), array( 'subscriber_id' => (int) $row->subscriber_id ), array( '%s' ), array( '%d' ) );
				$kept = $row;
			}
			self::fire(
				$new_email,
				'updated',
				array(
					'subscriber_id' => (int) $kept->subscriber_id,
					'first_name'    => (string) $kept->first_name,
					'last_name'     => (string) $kept->last_name,
					'old_email'     => trim( (string) $row->email ),
				)
			);
			return true;
		}

		/**
		 * Save a subscriber's names, firing the event with state updated when they changed.
		 *
		 * @since 6.0.2
		 * @param string $email Address.
		 * @param string $first First name.
		 * @param string $last  Last name.
		 * @return bool Whether anything changed.
		 */
		public static function update_names( $email, $first, $last ) {
			global $wpdb;
			$row = self::get( $email );
			if ( ! $row ) {
				return false;
			}
			$first = sanitize_text_field( (string) $first );
			$last  = sanitize_text_field( (string) $last );
			if ( (string) $row->first_name === $first && (string) $row->last_name === $last ) {
				return false;
			}
			$wpdb->update(
				'ec_subscriber',
				array(
					'first_name' => $first,
					'last_name'  => $last,
				),
				array( 'subscriber_id' => (int) $row->subscriber_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			self::fire(
				trim( (string) $row->email ),
				'updated',
				array(
					'subscriber_id' => (int) $row->subscriber_id,
					'first_name'    => $first,
					'last_name'     => $last,
				)
			);
			return true;
		}

		/**
		 * Keep the newsletter list in step with a customer account's newsletter box after staff save the account. Acts only
		 * on what changed, through ec_db so the older hooks fire too:
		 *
		 * - Ticked: a changed email moves the subscription ( change_email() ); a new tick, or a ticked account with no row,
		 *   subscribes the address; otherwise changed names are saved ( update_names() ).
		 * - Unticked, and ticked before: the subscription ( and one under the old email ) is removed. A new account saved
		 *   unticked leaves an existing newsletter sign-up alone.
		 *
		 * @since 6.0.2
		 * @param string    $email      The account's email now.
		 * @param bool|int  $subscribed The newsletter box now.
		 * @param string    $first      First name.
		 * @param string    $last       Last name.
		 * @param bool|null $was        The box before the save ( null for a new account ).
		 * @param string    $old_email  The email before the save ( '' for a new account ).
		 */
		public static function sync_account( $email, $subscribed, $first = '', $last = '', $was = null, $old_email = '' ) {
			$email     = trim( (string) $email );
			$old_email = trim( (string) $old_email );
			if ( '' === $email ) {
				return;
			}
			$moved = ( '' !== $old_email && strtolower( $old_email ) !== strtolower( $email ) );
			if ( $subscribed ) {
				if ( $moved && self::get( $old_email ) ) {
					/* Names first: the email change's event ( one updated per address and request ) then carries them. */
					self::update_names( $old_email, $first, $last );
					self::change_email( $old_email, $email );
				}
				if ( ! $was || ! self::get( $email ) ) {
					self::add( $email, $first, $last );
				} else {
					self::update_names( $email, $first, $last );
				}
				return;
			}
			if ( ! $was ) {
				return;
			}
			if ( $moved && self::get( $old_email ) ) {
				self::remove( $old_email );
			}
			if ( self::get( $email ) ) {
				self::remove( $email );
			}
		}

		// The event.

		/**
		 * Announce a subscriber change: do_action( 'wp_easycart_subscriber_changed', $email, $state, $context ).
		 *
		 * Fires at most once per address and state in a request, so callers that save twice ( or also fire the older hooks )
		 * announce once. A change to the other state re-arms it: subscribed, then unsubscribed, then subscribed again in one
		 * request fires three times, so listeners always end on the real state.
		 *
		 * @since 6.0.2
		 * @param string $email   Address ( the new one for an email change ).
		 * @param string $state   subscribed | unsubscribed | updated.
		 * @param array  $context subscriber_id ( 0 when unknown or deleted ), first_name, last_name, old_email ( '' unless the
		 *                        email changed ), source ( defaults to source() ).
		 * @return bool Whether the event fired.
		 */
		public static function fire( $email, $state, $context = array() ) {
			$email = trim( (string) $email );
			$state = (string) $state;
			if ( '' === $email || ! in_array( $state, array( 'subscribed', 'unsubscribed', 'updated' ), true ) ) {
				return false;
			}
			$key = strtolower( $email ) . '|' . $state;
			if ( isset( self::$fired[ $key ] ) ) {
				return false;
			}
			self::$fired[ $key ] = true;
			if ( 'subscribed' === $state ) {
				unset( self::$fired[ strtolower( $email ) . '|unsubscribed' ] );
			} elseif ( 'unsubscribed' === $state ) {
				unset( self::$fired[ strtolower( $email ) . '|subscribed' ] );
			}
			$context = wp_parse_args(
				(array) $context,
				array(
					'subscriber_id' => 0,
					'first_name'    => '',
					'last_name'     => '',
					'old_email'     => '',
					'source'        => '',
				)
			);
			if ( ! (int) $context['subscriber_id'] && 'unsubscribed' !== $state ) {
				$row = self::get( $email );
				if ( $row ) {
					$context['subscriber_id'] = (int) $row->subscriber_id;
				}
			}
			$context = array(
				'subscriber_id' => (int) $context['subscriber_id'],
				'first_name'    => (string) $context['first_name'],
				'last_name'     => (string) $context['last_name'],
				'old_email'     => trim( (string) $context['old_email'] ),
				'source'        => ( '' !== (string) $context['source'] ) ? self::clean_source( $context['source'] ) : self::source(),
			);
			/**
			 * A newsletter subscriber changed.
			 *
			 * @since 6.0.2
			 * @param string $email   Address ( the new one when the email changed ).
			 * @param string $state   subscribed ( a sign-up or re-sign-up: treat as subscribe and update the names ) |
			 *                        unsubscribed ( the row was removed ) | updated ( names or email changed; the
			 *                        subscription itself did not ).
			 * @param array  $context {
			 *     @type int    $subscriber_id ec_subscriber id, 0 when unknown or deleted.
			 *     @type string $first_name    First name.
			 *     @type string $last_name     Last name.
			 *     @type string $old_email     The previous address when the email changed, else ''.
			 *     @type string $source        Where the change came from ( see wp_easycart_subscribers::source() ).
			 * }
			 */
			do_action( 'wp_easycart_subscriber_changed', $email, $state, $context );
			return true;
		}

		// Helpers.

		/**
		 * A source slug as stored: lower case, at most 20 characters, other when empty.
		 *
		 * @param mixed $source Slug.
		 * @return string
		 */
		private static function clean_source( $source ) {
			$source = substr( sanitize_key( is_scalar( $source ) ? (string) $source : '' ), 0, self::SOURCE_LENGTH );
			return ( '' !== $source ) ? $source : 'other';
		}

		/**
		 * The AJAX action of this request.
		 *
		 * @return string '' when the request is not AJAX.
		 */
		private static function ajax_action() {
			if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
				return '';
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which action runs; the handler checks its own nonce.
			return isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		}

		/**
		 * Is an AJAX action one the storefront calls: registered for visitors, or named like EasyCart's storefront calls?
		 *
		 * @param string $action AJAX action.
		 * @return bool
		 */
		private static function is_storefront_ajax( $action ) {
			if ( '' === $action ) {
				return false;
			}
			return 0 === strpos( $action, 'ec_ajax_' ) || (bool) has_action( 'wp_ajax_nopriv_' . $action );
		}

		/**
		 * Is this a wp-admin page or an admin AJAX call ( not a storefront one )?
		 *
		 * @return bool
		 */
		private static function is_admin_request() {
			if ( ! is_admin() ) {
				return false;
			}
			$action = self::ajax_action();
			if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
				return true;
			}
			return ! self::is_storefront_ajax( $action );
		}

		/**
		 * The source this request looks like.
		 *
		 * @return string
		 */
		private static function guess_source() {
			$action = self::ajax_action();
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- only reads which form was posted; its handler checks the nonce.
			if ( 'ec_ajax_submit_newsletter_signup' === $action || isset( $_POST['ec_newsletter_email'] ) ) {
				return 'newsletter';
			}
			if ( isset( $_POST['ec_account_form_action'] ) ) {
				return ( 'register' === sanitize_key( wp_unslash( $_POST['ec_account_form_action'] ) ) ) ? 'register' : 'account';
			}
			if ( isset( $_POST['ec_cart_form_action'] ) || self::is_storefront_ajax( $action ) ) {
				return 'checkout';
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			return self::is_admin_request() ? 'admin' : 'other';
		}

		/**
		 * Tick or untick the newsletter box on the customer account with this email.
		 *
		 * @param string $email Address.
		 * @param int    $flag  1 or 0.
		 */
		private static function set_account_flag( $email, $flag ) {
			global $wpdb;
			$flag    = $flag ? 1 : 0;
			$user_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM ec_user WHERE email = %s AND is_subscriber != %d LIMIT 1', trim( (string) $email ), $flag ) );
			if ( $user_id <= 0 ) {
				return;
			}
			$wpdb->update( 'ec_user', array( 'is_subscriber' => $flag ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) );
			wp_cache_delete( 'wpeasycart-user-' . $user_id, 'wpeasycart-user' );
		}
	}

endif;
