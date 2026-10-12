<?php
/**
 * WP EasyCart — the product editor's new-product drafts ( 6.0.4, editor plan Phase 2 ).
 *
 * Add product opens the whole editor on a hidden draft row, so every section works at once ( WP EasyCart PRO's gallery,
 * option sets and variants save against a product id ). Until its first Save the draft is invisible: shoppers never see
 * it ( inactive, no store page ), admin lists, counts, pickers and exports leave it out ( wp_easycart_product_draft_sql() ),
 * and no product hook fires for it ( wp_easycart_product_writer::updated() stays quiet ). The first Save turns it into a
 * product and fires wpeasycart_product_added / wpeasycart_admin_product_inserted, as Quick add does. A draft nobody saves
 * is deleted after DAYS days ( daily WP-Cron CRON ).
 *
 * The mark is the row's own SKU: DRAFT_PREFIX . "<user>#<time>#<random>". No SKU check accepts a #, so no product can
 * carry one, and the mark needs no option or column ( two people adding products at once never overwrite each other ).
 * The editor shows a draft's SKU field empty.
 *
 * @package WP_EasyCart
 * @since   6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_product_drafts' ) ) :

	/**
	 * New-product drafts.
	 */
	class wp_easycart_product_drafts {

		const DRAFT_PREFIX = '#draft#';
		const CRON         = 'wp_easycart_product_drafts_cleanup';
		const DAYS         = 7;

		/**
		 * Hooks: the daily cleanup.
		 */
		public static function init() {
			add_action( self::CRON, array( __CLASS__, 'cleanup' ) );
			add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
		}

		/**
		 * Whether a SKU is a draft's mark.
		 *
		 * @param string $model_number SKU.
		 * @return bool
		 */
		public static function is_mark( $model_number ) {
			return 0 === strpos( (string) $model_number, self::DRAFT_PREFIX );
		}

		/**
		 * Whether a product is a draft nobody has saved yet.
		 *
		 * @param int|object $product Product id or row.
		 * @return bool
		 */
		public static function is_draft( $product ) {
			if ( is_object( $product ) ) {
				return isset( $product->model_number ) && self::is_mark( $product->model_number );
			}
			global $wpdb;
			$product_id = (int) $product;
			if ( $product_id <= 0 ) {
				return false;
			}
			return self::is_mark( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT model_number FROM ec_product WHERE product_id = %d', $product_id ) ) );
		}

		/**
		 * The SQL that leaves drafts out of a query on ec_product. The wildcard is written %% , so the fragment works inside
		 * $wpdb->prepare() ( which makes it % ) and outside it ( where LIKE reads %% as one wildcard ).
		 *
		 * @param string $alias The ec_product table or its alias in the query.
		 * @return string " AND …"
		 */
		public static function sql( $alias = 'ec_product' ) {
			$alias = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $alias );
			return ' AND COALESCE( ' . ( '' !== $alias ? $alias . '.' : '' ) . "model_number, '' ) NOT LIKE '" . self::DRAFT_PREFIX . "%%'";
		}

		/**
		 * A new draft for a person. A draft the same person started in the last two minutes is reused ( a double click,
		 * a reloaded link ).
		 *
		 * @param int $user_id WordPress user.
		 * @return int Product id, 0 when the row could not be written.
		 */
		public static function create( $user_id ) {
			global $wpdb;
			$user_id = (int) $user_id;
			$recent  = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT product_id, model_number FROM ec_product WHERE model_number LIKE %s ORDER BY product_id DESC LIMIT 10',
					$wpdb->esc_like( self::DRAFT_PREFIX . $user_id . '#' ) . '%'
				)
			);
			foreach ( (array) $recent as $row ) {
				$parts = explode( '#', (string) $row->model_number );
				if ( isset( $parts[3] ) && (int) $parts[3] > time() - 120 ) {
					return (int) $row->product_id;
				}
			}
			$mark = self::DRAFT_PREFIX . $user_id . '#' . time() . '#' . strtolower( wp_generate_password( 8, false, false ) );
			/* As the editor's first save made a product: inactive until the person saves it, on the store page, shipping. */
			$done = $wpdb->insert(
				'ec_product',
				array(
					'activate_in_store'   => 0,
					'title'               => '',
					'model_number'        => $mark,
					'price'               => 0,
					'post_id'             => 0,
					'show_on_startup'     => 1,
					'show_stock_quantity' => 0,
					'is_shippable'        => 1,
					'is_taxable'          => 1,
				),
				array( '%d', '%s', '%s', '%f', '%d', '%d', '%d', '%d', '%d' )
			);
			if ( ! $done ) {
				return 0;
			}
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
			}
			return (int) $wpdb->insert_id;
		}

		/**
		 * The first Save made the draft a product ( its SKU is the one the person gave ): it is added now. Call once the
		 * save has finished.
		 *
		 * @param int $product_id Product.
		 */
		public static function promote( $product_id ) {
			global $wpdb;
			$product_id   = (int) $product_id;
			$model_number = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT model_number FROM ec_product WHERE product_id = %d', $product_id ) );
			if ( '' === $model_number || self::is_mark( $model_number ) ) {
				return;
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET added_to_db_date = NOW() WHERE product_id = %d', $product_id ) );
			ec_db::product_cache_changed();
			do_action( 'wpeasycart_product_added', $product_id, $model_number );
			do_action( 'wpeasycart_admin_product_inserted', $product_id, $model_number );
		}

		/**
		 * Deletes a draft and everything saved against it ( its pictures stay in the media library ). Nothing is announced:
		 * as far as the store's listeners know, it never existed.
		 *
		 * @param int $product_id Product.
		 * @return bool Deleted.
		 */
		public static function discard( $product_id ) {
			global $wpdb;
			$product_id = (int) $product_id;
			$row        = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, post_id, model_number FROM ec_product WHERE product_id = %d', $product_id ) );
			if ( ! $row || ! self::is_mark( $row->model_number ) ) {
				return false;
			}
			if ( (int) $row->post_id > 0 && function_exists( 'wp_easycart_post_sync' ) ) {
				wp_easycart_post_sync()->delete( 'product', $product_id, (int) $row->post_id, true );
			}
			foreach ( array( 'ec_optionitemimage', 'ec_pricetier', 'ec_roleprice', 'ec_optionitemquantity', 'ec_option_to_product', 'ec_review', 'ec_affiliate_rule_to_product', 'ec_categoryitem' ) as $table ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $table . ' WHERE product_id = %d', $product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- a fixed table name from the list above.
			}
			if ( class_exists( 'wp_easycart_product_writer' ) && method_exists( 'wp_easycart_product_writer', 'delete_owned_sets' ) ) {
				wp_easycart_product_writer::delete_owned_sets( $product_id );
			}
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_product WHERE product_id = %d', $product_id ) );
			ec_db::product_cache_changed();
			return true;
		}

		/**
		 * Daily: drafts nobody saved for DAYS days are deleted.
		 *
		 * @return int Drafts deleted.
		 */
		public static function cleanup() {
			global $wpdb;
			$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, model_number FROM ec_product WHERE model_number LIKE %s', $wpdb->esc_like( self::DRAFT_PREFIX ) . '%' ) );
			$cutoff  = time() - (int) apply_filters( 'wp_easycart_product_draft_days', self::DAYS ) * DAY_IN_SECONDS;
			$deleted = 0;
			foreach ( (array) $rows as $row ) {
				$parts = explode( '#', (string) $row->model_number );
				$time  = isset( $parts[3] ) ? (int) $parts[3] : 0;
				if ( $time < $cutoff && self::discard( (int) $row->product_id ) ) {
					++$deleted;
				}
			}
			if ( count( (array) $rows ) === $deleted ) {
				wp_clear_scheduled_hook( self::CRON );
			}
			return $deleted;
		}

		/**
		 * The daily cleanup runs while there are drafts ( create() schedules it; this looks once a day for drafts left
		 * when the event was cleared ).
		 */
		public static function schedule() {
			if ( ! wp_next_scheduled( self::CRON ) && get_transient( 'wpec_product_drafts_check' ) === false ) {
				set_transient( 'wpec_product_drafts_check', 1, DAY_IN_SECONDS );
				global $wpdb;
				$any = $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE model_number LIKE %s LIMIT 1', $wpdb->esc_like( self::DRAFT_PREFIX ) . '%' ) );
				if ( $any ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
				}
			}
		}
	}

	wp_easycart_product_drafts::init();

endif;

if ( ! function_exists( 'wp_easycart_product_draft_sql' ) ) {
	/**
	 * Leaves the product editor's unsaved new-product drafts out of a query on ec_product ( 6.0.4 ). WP EasyCart PRO and
	 * extensions call it with function_exists().
	 *
	 * @since 6.0.4
	 * @param string $alias The ec_product table or its alias in the query.
	 * @return string " AND …"
	 */
	function wp_easycart_product_draft_sql( $alias = 'ec_product' ) {
		return wp_easycart_product_drafts::sql( $alias );
	}
}
