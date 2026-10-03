<?php
/**
 * The store page is also the site's front page ( 6.0.2, bug round 14 ).
 *
 * Product pages take their address from the store page ( /store/product-name/ ). When the store page is the front page they
 * have none, and product links end on a "page not found". Before 6.0.2 any visitor's 404 then published a new "Store" page
 * and flushed the rewrite rules ( wp_easycart_show_404_help() ). Now a store manager is told on every EasyCart screen and in
 * Store Status, and the Create Store page button makes the page: admin-post action wp_easycart_create_store_page
 * ( manage_options and a nonce ), through the Store details page's own "Create New Store Page"
 * ( ecv2_store_details_create_page() ), which also sets up the product addresses.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_store_front_page' ) ) :

	/**
	 * Notice, Store Status row and the Create Store page action.
	 */
	final class wp_easycart_admin_store_front_page {

		/**
		 * The admin-post action.
		 */
		const ACTION = 'wp_easycart_create_store_page';

		/**
		 * Nonce action.
		 */
		const NONCE = 'wp-easycart-create-store-page';

		/**
		 * Query argument carrying the answer back ( created | failed ).
		 */
		const RESULT_ARG = 'wpec_store_page';

		/**
		 * Help on product links that end on a 404.
		 */
		const HELP_URL = 'https://docs.wpeasycart.com/docs/how-to-guides/how-to-fix-404-errors-on-cart-pages/';

		/**
		 * Hooks ( once ).
		 */
		public static function init() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'create_page' ) );
			add_action( 'wp_easycart_admin_messages', array( __CLASS__, 'print_notice' ), 6 );
			add_filter( 'wp_easycart_admin_success_messages', array( __CLASS__, 'success_messages' ) );
			add_filter( 'wp_easycart_admin_error_messages', array( __CLASS__, 'error_messages' ) );
		}

		/**
		 * Whether the store page is the page WordPress shows as the front page.
		 *
		 * @return bool
		 */
		public static function applies() {
			$store = (int) get_option( 'ec_option_storepage' );
			return $store > 0 && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $store;
		}

		/**
		 * The Create Store page address ( a GET with the nonce: Store Status prints it as a link ).
		 *
		 * @return string
		 */
		public static function create_url() {
			return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::NONCE );
		}

		/**
		 * What the notice and the Store Status row say.
		 *
		 * @return string
		 */
		public static function text() {
			return __( 'Your store page is also your site\'s front page, so product pages have no address of their own and their links open a "page not found" error. Create a Store page for them: your front page stays as it is.', 'wp-easycart' );
		}

		/**
		 * Action wp_easycart_admin_messages ( every EasyCart screen, inside the shell ).
		 */
		public static function print_notice() {
			if ( ! current_user_can( 'manage_options' ) || ! get_option( 'ec_option_setup_wizard_done' ) || ! self::applies() ) {
				return;
			}
			?>
			<div id="ec_store_front_page_notice" class="wpec-pro-notice wpec-pro-notice--danger wpec-pro-notice--stacked">
				<span class="wpec-pro-notice-icon dashicons dashicons-warning"></span>
				<div class="wpec-pro-notice-body">
					<p><strong><?php esc_html_e( 'Product links lead to a "page not found" error.', 'wp-easycart' ); ?></strong></p>
					<p><?php echo esc_html( self::text() ); ?> <a href="<?php echo esc_url( self::HELP_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'wp-easycart' ); ?></a></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="flex:0 0 auto;margin:0;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<?php wp_nonce_field( self::NONCE ); ?>
					<button type="submit" class="wpec-pro-notice-button" style="border:0;cursor:pointer;font-family:inherit;"><?php esc_html_e( 'Create Store page', 'wp-easycart' ); ?></button>
				</form>
			</div>
			<?php
		}

		/**
		 * Admin-post wp_easycart_create_store_page: publishes a "Store" page holding [ec_store], makes it the store page and sets
		 * up the product addresses under it, then returns to the screen the button was on.
		 */
		public static function create_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'wp-easycart' ), '', array( 'response' => 403 ) );
			}
			check_admin_referer( self::NONCE );

			$result = 'created';
			if ( self::applies() ) {
				if ( ! function_exists( 'ecv2_store_details_create_page' ) && file_exists( EC_PLUGIN_DIRECTORY . '/admin/template/settings/initial-setup.php' ) ) {
					include_once EC_PLUGIN_DIRECTORY . '/admin/template/settings/initial-setup.php';
				}
				if ( function_exists( 'ecv2_store_details_create_page' ) ) {
					$made = ecv2_store_details_create_page( 'ec_option_storepage', __( 'Store', 'wp-easycart' ), '[ec_store]' );
				} else {
					$made = wp_insert_post(
						array(
							'post_content' => '[ec_store]',
							'post_title'   => __( 'Store', 'wp-easycart' ),
							'post_type'    => 'page',
							'post_status'  => 'publish',
						),
						true
					);
					if ( ! is_wp_error( $made ) ) {
						update_option( 'ec_option_storepage', (int) $made );
						flush_rewrite_rules();
					}
				}
				$result = is_wp_error( $made ) ? 'failed' : 'created';
			}

			$back = wp_get_referer();
			$back = $back ? $back : admin_url( 'admin.php?page=wp-easycart-settings&subpage=initial-setup' );
			wp_safe_redirect( add_query_arg( self::RESULT_ARG, $result, remove_query_arg( self::RESULT_ARG, $back ) ) );
			exit;
		}

		/**
		 * The answer after the redirect.
		 *
		 * @return string created | failed | ''.
		 */
		private static function result() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only: which notice to show after the redirect.
			return isset( $_GET[ self::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_ARG ] ) ) : '';
		}

		/**
		 * Filter wp_easycart_admin_success_messages.
		 *
		 * @param array $messages Messages.
		 * @return array
		 */
		public static function success_messages( $messages ) {
			if ( 'created' === self::result() ) {
				$messages   = is_array( $messages ) ? $messages : array();
				$messages[] = __( 'Your new Store page is published and product links now open under it. Your front page is unchanged.', 'wp-easycart' );
			}
			return $messages;
		}

		/**
		 * Filter wp_easycart_admin_error_messages.
		 *
		 * @param array $messages Messages.
		 * @return array
		 */
		public static function error_messages( $messages ) {
			if ( 'failed' === self::result() ) {
				$messages   = is_array( $messages ) ? $messages : array();
				$messages[] = __( 'The Store page could not be created. Create a page holding [ec_store] and choose it on Settings › Store details.', 'wp-easycart' );
			}
			return $messages;
		}
	}

	wp_easycart_admin_store_front_page::init();

endif;
