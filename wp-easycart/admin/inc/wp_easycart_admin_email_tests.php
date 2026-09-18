<?php
/**
 * WP EasyCart Admin — "Send test" for the subscription emails ( Settings › Email ).
 *
 * Subscription mail only goes out when Stripe / PayPal says a trial started, a trial is ending, a renewal is due,
 * a subscription ended or a payment failed, so a merchant could never see one before a customer did. These tests
 * render the real templates through the real objects ( ec_subscription / ec_orderdisplay, same variables as the
 * senders in inc/classes/core/ec_subscription.php and inc/classes/account/ec_orderdisplay.php ) and send the result
 * to the merchant with ec_email::send(), typed 'test' so the email log marks it a test and it never touches the
 * delivery figures. The newest real subscription is used when the store has one; otherwise clearly-marked sample data.
 *
 * @package wp-easycart
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_email_tests' ) ) :

	/**
	 * Subscription email tests.
	 *
	 * @since 6.0.0
	 */
	final class wp_easycart_admin_email_tests {

		/**
		 * The emails a merchant can send to themselves.
		 *
		 * @return array id => array( label, template, section title key/section, needs ).
		 */
		public static function types() {
			return array(
				'trial_start'  => array(
					'label'    => __( 'Trial started', 'wp-easycart' ),
					'template' => 'ec_cart_subscription_trial_start_email.php',
					'title'    => array( 'subscription_trial', 'subscription_trial_email_title' ),
					'log_type' => 'subscription_trial',
				),
				'trial_ending' => array(
					'label'    => __( 'Trial ending', 'wp-easycart' ),
					'template' => 'ec_cart_subscription_trial_ending_email.php',
					'title'    => array( 'subscription_trial', 'subscription_trial_ending_email_title' ),
					'log_type' => 'subscription_trial_ending',
				),
				'upcoming'     => array(
					'label'    => __( 'Renewal coming up', 'wp-easycart' ),
					'template' => 'ec_cart_subscription_upcoming_email.php',
					'title'    => array( 'subscription_upcoming', 'subscription_upcoming_email_title' ),
					'log_type' => 'subscription_upcoming',
				),
				'ended'        => array(
					'label'    => __( 'Subscription ended', 'wp-easycart' ),
					'template' => 'ec_cart_subscription_ended_email.php',
					'title'    => array( 'subscription_ended', 'subscription_ended_email_title' ),
					'log_type' => 'subscription_ended',
				),
				'failed'       => array(
					'label'    => __( 'Payment failed', 'wp-easycart' ),
					'template' => 'ec_cart_payment_failed.php',
					'title'    => array( 'ec_errors', 'subscription_payment_failed_title' ),
					'log_type' => 'subscription_failed',
					'needs'    => 'order',
				),
			);
		}

		/**
		 * Where the tests go: the address typed in Settings › Email, else the signed-in admin, else the site admin.
		 *
		 * @return string
		 */
		public static function recipient() {
			$to = trim( (string) get_option( 'ec_option_email_test_recipient', '' ) );
			if ( ! is_email( $to ) && function_exists( 'wp_get_current_user' ) ) {
				$user = wp_get_current_user();
				$to   = ( $user && isset( $user->user_email ) ) ? (string) $user->user_email : '';
			}
			if ( ! is_email( $to ) ) {
				$to = (string) get_option( 'admin_email' );
			}
			return $to;
		}

		/**
		 * Render and send one subscription email to the merchant.
		 *
		 * @param string $id One of types().
		 * @return string|WP_Error Message for the settings toast / inline result.
		 */
		public static function send( $id ) {
			$types = self::types();
			$id    = (string) $id;
			if ( ! isset( $types[ $id ] ) ) {
				return new WP_Error( 'ec_email_test_unknown', __( 'Unknown test email.', 'wp-easycart' ) );
			}
			$type = $types[ $id ];
			$to   = self::recipient();
			if ( ! is_email( $to ) ) {
				return new WP_Error( 'ec_email_test_recipient', __( 'Add a valid address in "Send test emails to", or give your WordPress user an email address.', 'wp-easycart' ) );
			}
			if ( ! class_exists( 'ec_subscription' ) || ! function_exists( 'wp_easycart_language' ) ) {
				return new WP_Error( 'ec_email_test_core', __( 'WP EasyCart is not fully loaded, so the test could not be built.', 'wp-easycart' ) );
			}

			$built = ( 'failed' === $id ) ? self::build_payment_failed() : self::build_subscription_email( $type );
			if ( is_wp_error( $built ) ) {
				return $built;
			}

			$subject = wp_strip_all_tags( wp_easycart_language()->get_text( $type['title'][0], $type['title'][1] ) );
			if ( '' === trim( $subject ) ) {
				$subject = $type['label'];
			}
			/* translators: %s: the email's normal subject. */
			$subject = sprintf( __( '[Test] %s', 'wp-easycart' ), $subject );

			if ( class_exists( 'ec_email' ) && method_exists( 'ec_email', 'send' ) ) {
				/* Typed 'test': the email log shows it as a test and it is left out of the delivery figures. */
				$ok = ec_email::send(
					$to,
					$subject,
					$built['html'],
					array(
						'type'    => ec_email::TEST_TYPE,
						'channel' => 'order',
					)
				);
			} else {
				$headers = array( 'MIME-Version: 1.0', 'Content-Type: text/html; charset=utf-8', 'From: ' . stripslashes( (string) get_option( 'ec_option_order_from_email' ) ) );
				$ok      = wp_mail( $to, $subject, $built['html'], $headers );
			}
			if ( ! $ok ) {
				/* translators: %s: email address. */
				return new WP_Error( 'ec_email_test_failed', sprintf( __( 'The test to %s could not be sent. The email log below shows what the mail server said.', 'wp-easycart' ), $to ) );
			}
			/* translators: 1: which email, 2: address, 3: where the data came from. */
			return sprintf( __( '%1$s test sent to %2$s. %3$s', 'wp-easycart' ), $type['label'], $to, $built['source'] );
		}

		/*
		 * Building the real emails.
		 */

		/**
		 * Trial started / trial ending / renewal coming up / ended, rendered from the real template.
		 *
		 * @param array $type types() entry.
		 * @return array|WP_Error html, source.
		 */
		private static function build_subscription_email( $type ) {
			$row    = self::subscription_row();
			$sample = empty( $row->subscription_id );
			$sub    = new ec_subscription( $row );
			$user   = (object) array(
				'user_id'    => (int) $row->user_id,
				'email'      => (string) $row->email,
				'first_name' => (string) $row->first_name,
				'last_name'  => (string) $row->last_name,
			);

			$vars = array( 'user' => $user );
			if ( 'ec_cart_subscription_upcoming_email.php' === $type['template'] ) {
				/* The renewal notice is normally built from a Stripe invoice: hand the template the same shape. */
				$when                 = strtotime( '+30 days', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- display date only.
				$vars['subscription'] = $row;
				$vars['webhook_data'] = (object) array(
					'amount_due'           => (int) round( (float) $row->price * 100 ),
					'next_payment_attempt' => $when,
				);
				$vars['total']        = isset( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( (float) $row->price ) : (string) $row->price;
				$vars['date']         = date_i18n( 'F j, Y', $when );
			}

			$html = self::render_template( $type['template'], $sub, $vars );
			if ( is_wp_error( $html ) ) {
				return $html;
			}
			return array(
				'html'   => $html,
				'source' => self::source_note( $row, $sample ),
			);
		}

		/**
		 * Subscription payment failed: the template runs on ec_orderdisplay, so it needs a real order.
		 *
		 * @return array|WP_Error html, source.
		 */
		private static function build_payment_failed() {
			global $wpdb;
			if ( ! class_exists( 'ec_orderdisplay' ) || ! class_exists( 'ec_db_admin' ) ) {
				return new WP_Error( 'ec_email_test_core', __( 'WP EasyCart is not fully loaded, so the test could not be built.', 'wp-easycart' ) );
			}
			$order_id = (int) $wpdb->get_var( 'SELECT order_id FROM ec_order ORDER BY order_id DESC LIMIT 1' );
			if ( $order_id <= 0 ) {
				return new WP_Error( 'ec_email_test_no_order', __( 'This email is built from a real order, and the store has none yet. Place a test order first, then send this test.', 'wp-easycart' ) );
			}
			$db  = new ec_db_admin();
			$row = $db->get_order_row_admin( $order_id );
			if ( ! $row ) {
				return new WP_Error( 'ec_email_test_no_order', __( 'The most recent order could not be loaded.', 'wp-easycart' ) );
			}
			$display = new ec_orderdisplay( $row, true, true );
			$sub_row = self::subscription_row();
			$vars    = array(
				'subscription'      => $sub_row,
				'email_logo_url'    => get_option( 'ec_option_email_logo' ),
				'store_page'        => self::store_page(),
				'permalink_divider' => ( substr_count( self::store_page(), '?' ) ? '&' : '?' ),
			);
			$html    = self::render_template( 'ec_cart_payment_failed.php', $display, $vars, 'ec_orderdisplay' );
			if ( is_wp_error( $html ) ) {
				return $html;
			}
			return array(
				'html'   => $html,
				/* translators: %d: order number. */
				'source' => sprintf( __( 'Built from order %d.', 'wp-easycart' ), $order_id ),
			);
		}

		/**
		 * Include a layout template with $this bound to the object the sender uses, so the test is the real email.
		 *
		 * @param string $file  Template file name.
		 * @param object $bind  Object the template runs as ( ec_subscription / ec_orderdisplay ).
		 * @param array  $vars  Variables the sender sets ( user, subscription, total, date … ).
		 * @param string $scope Class scope for $this.
		 * @return string|WP_Error
		 */
		private static function render_template( $file, $bind, $vars, $scope = 'ec_subscription' ) {
			$path = self::template_path( $file );
			if ( '' === $path ) {
				/* translators: %s: template file name. */
				return new WP_Error( 'ec_email_test_template', sprintf( __( 'The email template %s was not found.', 'wp-easycart' ), $file ) );
			}
			$vars = array_merge(
				array(
					'email_logo_url'    => get_option( 'ec_option_email_logo' ),
					'store_page'        => self::store_page(),
					'permalink_divider' => ( substr_count( self::store_page(), '?' ) ? '&' : '?' ),
				),
				$vars
			);

			$render = function ( $path, $vars ) {
				/* The templates read $this ( subscription / order display ) and these plain variables. */
				$email_logo_url    = isset( $vars['email_logo_url'] ) ? $vars['email_logo_url'] : '';
				$store_page        = isset( $vars['store_page'] ) ? $vars['store_page'] : '';
				$permalink_divider = isset( $vars['permalink_divider'] ) ? $vars['permalink_divider'] : '?';
				$user              = isset( $vars['user'] ) ? $vars['user'] : null;
				$subscription      = isset( $vars['subscription'] ) ? $vars['subscription'] : null;
				$webhook_data      = isset( $vars['webhook_data'] ) ? $vars['webhook_data'] : null;
				$total             = isset( $vars['total'] ) ? $vars['total'] : '';
				$date              = isset( $vars['date'] ) ? $vars['date'] : false;
				unset( $vars );
				ob_start();
				include $path;
				return ob_get_clean();
			};
			$bound  = Closure::bind( $render, $bind, $scope );
			if ( ! $bound ) {
				return new WP_Error( 'ec_email_test_bind', __( 'The email could not be rendered on this server.', 'wp-easycart' ) );
			}
			$html = $bound( $path, $vars );
			return ( is_string( $html ) && '' !== trim( $html ) ) ? $html : new WP_Error( 'ec_email_test_empty', __( 'The email template produced nothing.', 'wp-easycart' ) );
		}

		/**
		 * Data-folder / theme override first, then the plugin layout — the same lookup the senders use.
		 *
		 * @param string $file Template file name.
		 * @return string Absolute path, '' when missing.
		 */
		private static function template_path( $file ) {
			$candidates = array();
			if ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) ) {
				$candidates[] = EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/' . $file;
			}
			$candidates[] = EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/' . $file;
			$candidates[] = EC_PLUGIN_DIRECTORY . '/design/layout/base-responsive-v3/' . $file;
			foreach ( $candidates as $candidate ) {
				if ( is_file( $candidate ) ) {
					return $candidate;
				}
			}
			return '';
		}

		/** Store page URL, as the senders build it. */
		private static function store_page() {
			$page_id = get_option( 'ec_option_storepage' );
			if ( function_exists( 'icl_object_id' ) ) {
				$page_id = icl_object_id( $page_id, 'page', true, ICL_LANGUAGE_CODE );
			}
			$url = $page_id ? (string) get_permalink( $page_id ) : '';
			return ( '' !== $url ) ? $url : home_url( '/' );
		}

		/**
		 * The newest subscription in the store, or a sample row with every column the classes read.
		 *
		 * @return object
		 */
		private static function subscription_row() {
			global $wpdb;
			/* ec_subscription::set_db_vars() reads trial_period_days and membership_page, which live on ec_product and only
			   arrive through the joined accessor the real senders use, so a bare SELECT * would raise warnings mid-AJAX. */
			$subscription_id = (int) $wpdb->get_var( 'SELECT subscription_id FROM ec_subscription ORDER BY subscription_id DESC LIMIT 1' );
			if ( $subscription_id > 0 && class_exists( 'ec_db' ) ) {
				$db  = new ec_db();
				$row = $db->get_subscription_row( $subscription_id );
				if ( $row ) {
					return $row;
				}
			}
			$row = new stdClass();
			foreach ( (array) $wpdb->get_results( 'SHOW COLUMNS FROM ec_subscription' ) as $column ) {
				$name         = $column->Field; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column names from SHOW COLUMNS.
				$row->{$name} = ( null === $column->Default ) ? '' : $column->Default; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL column names from SHOW COLUMNS.
			}
			/* Every column set_db_vars() reads, including the ec_product ones the JOIN adds ( trial_period_days, membership_page ). */
			$sample = array(
				'subscription_id'           => 0,
				'subscription_type'         => 'stripe',
				'subscription_status'       => 'Active',
				'title'                     => __( 'Sample Monthly Plan', 'wp-easycart' ),
				'user_id'                   => 0,
				'email'                     => self::recipient(),
				'first_name'                => __( 'Sample', 'wp-easycart' ),
				'last_name'                 => __( 'Customer', 'wp-easycart' ),
				'user_country'              => '',
				'product_id'                => 0,
				'model_number'              => 'SAMPLE-PLAN',
				'price'                     => 19.99,
				'quantity'                  => 1,
				'payment_length'            => 1,
				'payment_period'            => 'month',
				'payment_duration'          => 0,
				'trial_period_days'         => 14,
				'membership_page'           => '',
				'subscription_unique_id'    => '',
				'min_purchase_quantity'     => 1,
				'stripe_subscription_id'    => '',
				'num_failed_payment'        => 0,
				'number_payments_completed' => 3,
				'last_payment_date'         => gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ),
				'next_payment_date'         => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ),
				'start_date'                => gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ),
			);
			foreach ( $sample as $key => $value ) {
				$row->{$key} = $value;
			}
			return $row;
		}

		/**
		 * Which subscription the merchant is looking at.
		 *
		 * @param object $row    Subscription row.
		 * @param bool   $sample Sample data.
		 * @return string
		 */
		private static function source_note( $row, $sample ) {
			if ( $sample ) {
				return __( 'The store has no subscriptions yet, so it uses sample data.', 'wp-easycart' );
			}
			/* translators: 1: subscription number, 2: plan title. */
			return sprintf( __( 'Built from subscription %1$d ( %2$s ).', 'wp-easycart' ), (int) $row->subscription_id, wp_strip_all_tags( (string) $row->title ) );
		}
	}

endif;
