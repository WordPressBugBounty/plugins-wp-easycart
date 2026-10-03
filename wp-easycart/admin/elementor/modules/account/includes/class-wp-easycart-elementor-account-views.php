<?php
/**
 * The account views the widgets draw ( 6.0.2 ).
 *
 * Most views are EasyCart's own account templates, drawn through WP_EasyCart_Elementor_Account_Page. Downloads, payment
 * methods, the signed-in note and the back links have no template of their own in EasyCart, so their markup is here; it
 * uses the templates' class names where one exists, so the widgets' Style settings reach them too.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Account_Views' ) ) :

	/**
	 * Account views.
	 */
	class WP_EasyCart_Elementor_Account_Views {

		/**
		 * Page the sign-in form returns to after a wrong password: this post, else the store's account page.
		 *
		 * @return int
		 */
		public static function page_id() {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Post ) {
				return (int) $queried->ID;
			}
			$post_id = (int) get_the_ID();
			if ( $post_id && ( wp_doing_ajax() || ! did_action( 'wp' ) ) ) {
				return $post_id;
			}
			return (int) apply_filters( 'wp_easycart_account_page_id', get_option( 'ec_option_accountpage' ) );
		}

		/**
		 * Where to go after signing in ( '' = the account area: this page when it shows the dashboard, else the store's
		 * account page ).
		 *
		 * @param array $settings Widget settings ( login_redirect, login_redirect_url ).
		 * @return string
		 */
		public static function login_redirect( $settings ) {
			$choice = isset( $settings['login_redirect'] ) ? $settings['login_redirect'] : 'account';
			if ( 'page' === $choice ) {
				return WP_EasyCart_Elementor_Account::here_url();
			}
			if ( 'custom' === $choice && isset( $settings['login_redirect_url'] ) ) {
				$url = is_array( $settings['login_redirect_url'] ) ? ( isset( $settings['login_redirect_url']['url'] ) ? $settings['login_redirect_url']['url'] : '' ) : $settings['login_redirect_url'];
				$url = trim( (string) $url );
				if ( '' !== $url ) {
					return esc_url_raw( $url );
				}
			}
			return '';
		}

		/**
		 * The sign-in form ( and the new customer box beside it ).
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page     Account page.
		 * @param array                              $settings Widget settings.
		 */
		public static function login( $page, $settings ) {
			$redirect             = self::login_redirect( $settings );
			$saved                = $page->redirect_login;
			$page->redirect_login = ( '' !== $redirect ) ? $redirect : false;
			$page->display_account_login_page( self::page_id(), array( 'form_only' => false ) );
			$page->redirect_login = $saved;
		}

		/**
		 * The account sign-up form.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page Account page.
		 */
		public static function register( $page ) {
			$page->display_register_page();
		}

		/**
		 * The lost password form, or the new password form when the emailed link opened this page.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page  Account page.
		 * @param bool                               $reset Draw the new password form.
		 */
		public static function lost_password( $page, $reset ) {
			if ( $reset ) {
				$page->display_reset_password_page();
			} else {
				$page->display_forgot_password_page();
			}
		}

		/**
		 * Whether this request opened the emailed reset link.
		 *
		 * @return bool
		 */
		public static function is_reset_request() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which view to draw; the reset key is checked by EasyCart.
			return isset( $_GET['ec_page'] ) && 'reset_password' === sanitize_key( wp_unslash( $_GET['ec_page'] ) );
		}

		/**
		 * The view this request asks for ( ec_page ), or ''.
		 *
		 * @return string
		 */
		public static function requested_view() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which view to draw.
			return isset( $_GET['ec_page'] ) ? sanitize_key( wp_unslash( $_GET['ec_page'] ) ) : '';
		}

		/**
		 * A "back to …" link above a detail view.
		 *
		 * @param string $url  Link.
		 * @param string $text Text.
		 */
		public static function back_link( $url, $text ) {
			echo '<p class="wpec-acc-back"><a class="wpec-acc-link" href="' . esc_url( $url ) . '"><span aria-hidden="true">&larr;</span> ' . esc_html( $text ) . '</a></p>';
		}

		/**
		 * An order's details, with a link back to the order list.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page  Account page.
		 * @param ec_orderdisplay|null               $order Order.
		 * @param array                              $views Views of the widget ( where the list opens ).
		 */
		public static function order_details( $page, $order, $views ) {
			self::back_link( WP_EasyCart_Elementor_Account::link_to( 'orders', $views ), WP_EasyCart_Elementor_Account::text( 'back_to_orders', 'Back to orders' ) );
			$page->wpec_display_order_details( $order );
		}

		/**
		 * The billing and / or shipping address forms.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page  Account page.
		 * @param string                             $which both | billing | shipping.
		 */
		public static function addresses( $page, $which ) {
			$shipping = get_option( 'ec_option_use_shipping' ) && 'billing' !== $which;
			$billing  = ( 'shipping' !== $which ) || ! $shipping;
			echo '<div class="wpec-acc-columns' . ( ( $billing && $shipping ) ? ' wpec-acc-columns--2' : '' ) . '">';
			if ( $billing ) {
				echo '<div class="wpec-acc-column wpec-acc-column--billing">';
				$page->display_billing_information_page();
				echo '</div>';
			}
			if ( $shipping ) {
				echo '<div class="wpec-acc-column wpec-acc-column--shipping">';
				$page->display_shipping_information_page();
				echo '</div>';
			}
			echo '</div>';
		}

		/**
		 * The name and email form and / or the password form.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page  Account page.
		 * @param string                             $which both | personal | password.
		 */
		public static function details( $page, $which ) {
			$personal = ( 'password' !== $which );
			$password = ( 'personal' !== $which );
			echo '<div class="wpec-acc-columns' . ( ( $personal && $password ) ? ' wpec-acc-columns--2' : '' ) . '">';
			if ( $personal ) {
				echo '<div class="wpec-acc-column wpec-acc-column--personal">';
				$page->display_personal_information_page();
				echo '</div>';
			}
			if ( $password ) {
				echo '<div class="wpec-acc-column wpec-acc-column--password">';
				$page->display_password_page();
				echo '</div>';
			}
			echo '</div>';
		}

		/**
		 * The customer's downloads.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page       Account page.
		 * @param bool                               $show_title Show the heading.
		 */
		public static function downloads( $page, $show_title ) {
			$items = $page->wpec_downloads();
			echo '<section class="ec_account_page wpec-acc-downloads">';
			if ( $show_title ) {
				echo '<div class="ec_cart_header ec_top">' . esc_html( WP_EasyCart_Elementor_Account::text( 'downloads_title', 'Downloads' ) ) . '</div>';
			}
			if ( empty( $items ) ) {
				echo '<p class="wpec-acc-empty">' . esc_html( WP_EasyCart_Elementor_Account::text( 'downloads_none', 'You have no downloads yet.' ) ) . '</p>';
				echo '</section>';
				return;
			}
			$date_format = get_option( 'date_format' );
			echo '<ul class="wpec-acc-downloads__list">';
			foreach ( $items as $entry ) {
				$item    = $entry['item'];
				$expired = ( (int) $item->download_timelimit_seconds > 0 && (int) $item->timecheck >= (int) $item->download_timelimit_seconds );
				$used_up = ( (int) $item->maximum_downloads_allowed > 0 && (int) $item->download_count >= (int) $item->maximum_downloads_allowed );
				echo '<li class="wpec-acc-downloads__item">';
				echo '<div class="wpec-acc-downloads__info">';
				echo '<span class="wpec-acc-downloads__title">' . esc_html( wp_strip_all_tags( wp_easycart_language()->convert_text( $item->title ) ) ) . '</span>';
				echo '<span class="wpec-acc-downloads__meta">';
				echo '<a class="wpec-acc-link" href="' . esc_url( wpeasycart_links()->get_account_page( 'order_details', array( 'order_id' => (int) $entry['order_id'] ) ) ) . '">' . esc_html( WP_EasyCart_Elementor_Account::text( 'downloads_order', 'Order' ) . ' #' . (int) $entry['order_id'] ) . '</a>';
				if ( (int) $item->maximum_downloads_allowed > 0 ) {
					echo ' <span class="wpec-acc-downloads__count">' . esc_html( (int) $item->download_count . '/' . (int) $item->maximum_downloads_allowed . ' ' . WP_EasyCart_Elementor_Account::store_text( 'account_order_details', 'account_orders_details_downloads_used', 'downloads used' ) ) . '</span>';
				}
				if ( (int) $item->download_timelimit_seconds > 0 && ! $expired ) {
					echo ' <span class="wpec-acc-downloads__expires">' . esc_html( WP_EasyCart_Elementor_Account::text( 'downloads_expires', 'Available until' ) . ' ' . $item->get_download_expire_date( $date_format ) ) . '</span>';
				}
				echo '</span>';
				echo '</div>';
				echo '<div class="wpec-acc-downloads__actions">';
				if ( $expired ) {
					echo '<span class="wpec-acc-downloads__status">' . esc_html( WP_EasyCart_Elementor_Account::text( 'downloads_expired', 'This download has expired.' ) ) . '</span>';
				} elseif ( $used_up ) {
					echo '<span class="wpec-acc-downloads__status">' . esc_html( WP_EasyCart_Elementor_Account::text( 'downloads_used_up', 'No downloads left.' ) ) . '</span>';
				} else {
					$item->display_download_link( WP_EasyCart_Elementor_Account::store_text( 'account_order_details', 'account_orders_details_download', 'Download' ), $entry['extra'] );
				}
				echo '</div>';
				echo '</li>';
			}
			echo '</ul>';
			echo '</section>';
		}

		/**
		 * The card on file and where to change it.
		 *
		 * @param WP_EasyCart_Elementor_Account_Page $page       Account page.
		 * @param bool                               $show_title Show the heading.
		 * @param array                              $args       change_links ( list the subscriptions, default true ),
		 *                                                       change_style ( link | button ).
		 */
		public static function payment_methods( $page, $show_title, $args = array() ) {
			$args  = wp_parse_args(
				$args,
				array(
					'change_links' => true,
					'change_style' => 'link',
				)
			);
			$class = ( 'button' === $args['change_style'] ) ? 'wpec-acc-button wpec-acc-card__change' : 'wpec-acc-link wpec-acc-card__change';
			$data  = $page->wpec_payment_methods();
			echo '<section class="ec_account_page wpec-acc-payment">';
			if ( $show_title ) {
				echo '<div class="ec_cart_header ec_top">' . esc_html( WP_EasyCart_Elementor_Account::text( 'payment_title', 'Payment methods' ) ) . '</div>';
			}
			if ( '' !== $data['last4'] ) {
				echo '<div class="wpec-acc-card">';
				echo '<span class="wpec-acc-card__brand">' . esc_html( '' !== $data['brand'] ? $data['brand'] : WP_EasyCart_Elementor_Account::text( 'payment_card', 'Card' ) ) . '</span>';
				echo '<span class="wpec-acc-card__number">&bull;&bull;&bull;&bull; ' . esc_html( $data['last4'] ) . '</span>';
				echo '<span class="wpec-acc-card__note">' . esc_html( WP_EasyCart_Elementor_Account::text( 'payment_card_note', 'Used for your subscriptions.' ) ) . '</span>';
				echo '</div>';
			} else {
				echo '<p class="wpec-acc-empty">' . esc_html( WP_EasyCart_Elementor_Account::text( 'payment_none', 'You have no saved payment method.' ) ) . '</p>';
			}
			if ( ! empty( $data['subscriptions'] ) && $args['change_links'] ) {
				echo '<ul class="wpec-acc-card__subscriptions">';
				foreach ( $data['subscriptions'] as $subscription ) {
					echo '<li class="wpec-acc-card__subscription">';
					echo '<span class="wpec-acc-card__subscription-title">' . esc_html( wp_strip_all_tags( wp_easycart_language()->convert_text( $subscription->title ) ) ) . '</span> ';
					echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $subscription->subscription_id ) ) ) . '">' . esc_html( WP_EasyCart_Elementor_Account::store_text( 'cart_payment_information', 'cart_change_payment_method', 'Change payment method' ) ) . '</a>';
					echo '</li>';
				}
				echo '</ul>';
			}
			echo '</section>';
		}

		/**
		 * For a signed-in customer where a sign-in or sign-up form would be: who is signed in, the account and sign out.
		 *
		 * @param array $views Views of the widget.
		 */
		public static function signed_in( $views ) {
			$user = isset( $GLOBALS['ec_user'] ) ? $GLOBALS['ec_user'] : null;
			$name = $user ? trim( (string) $user->first_name . ' ' . (string) $user->last_name ) : '';
			if ( '' === $name && $user ) {
				$name = (string) $user->email;
			}
			$text = WP_EasyCart_Elementor_Account::text( 'signed_in_as', 'You are signed in as [name].' );
			echo '<div class="wpec-acc-signed-in">';
			echo '<p>' . esc_html( str_replace( '[name]', $name, $text ) ) . '</p>';
			echo '<p class="wpec-acc-signed-in__actions">';
			echo '<a class="wpec-acc-button" href="' . esc_url( WP_EasyCart_Elementor_Account::link_to( 'dashboard', $views ) ) . '">' . esc_html( WP_EasyCart_Elementor_Account::text( 'my_account', 'My account' ) ) . '</a> ';
			echo '<a class="wpec-acc-link" href="' . esc_url( WP_EasyCart_Elementor_Account::logout_url( $views ) ) . '">' . esc_html( WP_EasyCart_Elementor_Account::text( 'nav_logout', 'Sign out' ) ) . '</a>';
			echo '</p>';
			echo '</div>';
		}
	}

endif;
