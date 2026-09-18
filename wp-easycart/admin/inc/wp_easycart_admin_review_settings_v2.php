<?php
/**
 * WP EasyCart Admin — Reviews → Requests & rules ( V2 ).
 *
 * Free: turn on post-shipping review requests, choose trigger statuses and delay, customise subject/intro,
 *       send a test, see the 30-day funnel and recent request log, recompute verified badges.
 * PRO:  reminder email, reply signature / auto-email, moderation rules.
 *
 * URL: subpage=reviews&tab=requests
 * AJAX: ecv2_review_settings_save, ecv2_review_request_test, ecv2_review_requests_send_now, ecv2_review_verified_recompute.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_review_settings_v2' ) ) :

	class wp_easycart_admin_review_settings_v2 {

		const NONCE = 'wp-easycart-rvsettings';

		public $native = false; public $pro = false; public $settings = array(); public $statuses = array(); public $funnel = array(); public $log = array(); public $docs_link = '';

		public function __construct() {
			$this->docs_link = wp_easycart_admin()->helpsystem->print_docs_url( 'products', 'product-reviews', 'product-reviews' );
		}

		public static function tab_url( $tab = '' ) {
			return admin_url( 'admin.php?page=wp-easycart-products&subpage=reviews' . ( $tab ? '&tab=' . $tab : '' ) );
		}

		/** Tab strip shared by the list and this screen. */
		public static function print_tabs( $active ) {
			$tabs = array( '' => __( 'All reviews', 'wp-easycart' ), 'requests' => __( 'Requests & rules', 'wp-easycart' ) );
			echo '<div class="ecv2-tabs" role="tablist">';
			foreach ( $tabs as $k => $label ) {
				echo '<a class="ecv2-tab' . ( $k === $active ? ' is-active' : '' ) . '" href="' . esc_url( self::tab_url( $k ) ) . '" role="tab"' . ( $k === $active ? ' aria-selected="true"' : '' ) . '>' . esc_html( $label ) . ( 'requests' === $k && ! wp_easycart_admin_review_table::native_available() ? ' <span class="ecv2-chip ecv2-chip-amber">' . esc_html__( 'DB update', 'wp-easycart' ) . '</span>' : '' ) . '</a>';
			}
			echo '</div>';
		}

		public function load() {
			global $wpdb;
			$this->native = wp_easycart_admin_review_table::native_available();
			$this->pro = wp_easycart_admin_review_table::native_pro();
			$this->settings = class_exists( 'ec_reviews' ) ? ec_reviews::settings() : array();
			$this->statuses = $wpdb->get_results( 'SELECT status_id, order_status FROM ec_orderstatus WHERE is_archieved = 0 ORDER BY status_id' );
			if ( $this->native ) {
				$this->funnel = ec_reviews::funnel( 30 );
				$this->log = $wpdb->get_results( 'SELECT q.*, p.title AS product_title FROM ec_review_request q LEFT JOIN ec_product p ON p.product_id = q.product_id ORDER BY q.created_at DESC LIMIT 40' );
			}
		}

		public function output() {
			$this->load();
			include( EC_PLUGIN_DIRECTORY . '/admin/template/products/reviews/review-requests-v2.php' );
		}

		public function js_data() {
			return array( 'nonce' => wp_create_nonce( self::NONCE ), 'native' => $this->native, 'is_pro' => $this->pro, 'next_run' => wp_next_scheduled( ec_reviews::CRON_HOOK ) );
		}
	}

endif;

function ecv2_rvs_guard() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_review_settings_v2::NONCE, 'nonce' );
	if ( ! wp_easycart_admin_review_table::native_available() ) { wp_send_json_error( array( 'message' => __( 'The database update for reviews has not run yet.', 'wp-easycart' ) ) ); }
}

add_action( 'wp_ajax_ecv2_review_settings_save', 'ecv2_review_settings_save' );
function ecv2_review_settings_save() {
	ecv2_rvs_guard();
	$d = json_decode( wp_unslash( isset( $_POST['data'] ) ? $_POST['data'] : '{}' ), true );
	if ( ! is_array( $d ) ) { wp_send_json_error( array( 'message' => __( 'Invalid payload.', 'wp-easycart' ) ) ); }
	$s = ec_reviews::save_settings( $d );
	wp_send_json_success( array( 'message' => __( 'Saved', 'wp-easycart' ), 'settings' => $s, 'next_run' => wp_next_scheduled( ec_reviews::CRON_HOOK ) ) );
}

/** Sends the request email for a real recent order ( or a synthetic one ) to the admin address. */
add_action( 'wp_ajax_ecv2_review_request_test', 'ecv2_review_request_test' );
function ecv2_review_request_test() {
	ecv2_rvs_guard();
	global $wpdb;
	$to = isset( $_POST['to'] ) && is_email( $_POST['to'] ) ? sanitize_email( $_POST['to'] ) : wp_get_current_user()->user_email;
	$rows = $wpdb->get_results( 'SELECT * FROM ec_review_request ORDER BY request_id DESC LIMIT 2' );
	if ( empty( $rows ) ) {
		$p = $wpdb->get_row( 'SELECT product_id FROM ec_product WHERE activate_in_store = 1 ORDER BY product_id DESC LIMIT 1' );
		if ( ! $p ) { wp_send_json_error( array( 'message' => __( 'Add a product first.', 'wp-easycart' ) ) ); }
		$rows = array( (object) array( 'request_id' => 0, 'order_id' => 12345, 'product_id' => (int) $p->product_id, 'user_id' => 0, 'email' => $to, 'first_name' => wp_get_current_user()->display_name, 'token' => 'TESTTOKEN' ) );
	}
	$sample = ( isset( $rows[0]->token ) && 'TESTTOKEN' === $rows[0]->token );
	$ok = ec_reviews::send_request_email( $rows, ! empty( $_POST['reminder'] ), $to );
	if ( ! $ok ) { wp_send_json_error( array( 'message' => __( 'The email could not be sent — check Settings → Email.', 'wp-easycart' ) ) ); }
	/* 6.0.0: with no review requests yet the test uses a sample token, so its links cannot open the review form. Say so. */
	$message = $sample
		? sprintf( __( 'Test sent to %s. The store has no review requests yet, so the links in it use a sample token and will not open the review form.', 'wp-easycart' ), $to )
		: sprintf( __( 'Test sent to %s, built from a real review request, so its links work.', 'wp-easycart' ), $to );
	wp_send_json_success( array( 'message' => $message ) );
}

add_action( 'wp_ajax_ecv2_review_requests_send_now', 'ecv2_review_requests_send_now' );
function ecv2_review_requests_send_now() {
	ecv2_rvs_guard();
	$r = ec_reviews::send_due( 500 );
	wp_send_json_success( array( 'message' => sprintf( __( 'Sent %1$d request emails and %2$d reminders.', 'wp-easycart' ), (int) $r['sent'], (int) $r['reminded'] ), 'funnel' => ec_reviews::funnel( 30 ) ) );
}

add_action( 'wp_ajax_ecv2_review_verified_recompute', 'ecv2_review_verified_recompute' );
function ecv2_review_verified_recompute() {
	ecv2_rvs_guard();
	$n = ec_reviews::recompute_verified();
	wp_send_json_success( array( 'message' => sprintf( _n( '%d review is now marked verified.', '%d reviews are now marked verified.', $n, 'wp-easycart' ), $n ) ) );
}
