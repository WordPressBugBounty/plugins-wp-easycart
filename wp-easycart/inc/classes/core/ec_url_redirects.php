<?php
/**
 * WP EasyCart — URL redirects
 *
 * A small registry of path -> URL redirects, stored in one option and applied
 * on template_redirect with a 301. Used when a category / menu / manufacturer
 * slug changes or the record is deleted, so old links in search results, emails
 * and menus keep landing somewhere sensible. EasyCart had no redirect handling
 * before this.
 *
 * Registered from inc/ec_config.php alongside the other core classes:
 *   require_once( EC_PLUGIN_DIRECTORY . '/inc/classes/core/ec_url_redirects.php' );
 * The file calls ec_url_redirects::init() itself, so the require is sufficient.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ec_url_redirects' ) ) :

	final class ec_url_redirects {

		const OPTION = 'ec_option_url_redirects';

		public static function init() {
			add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
		}

		/** Normalise a URL or path to a comparable path key: no scheme/host, leading slash, no trailing slash, no query. */
		public static function key( $url ) {
			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( null === $path || '' === $path ) {
				$path = '/';
			}
			$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
			if ( $home_path && '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
				$path = substr( $path, strlen( $home_path ) );
			}
			$path = '/' . trim( $path, '/' );
			return strtolower( $path );
		}

		public static function all() {
			$r = get_option( self::OPTION, array() );
			return is_array( $r ) ? $r : array();
		}

		/**
		 * @param string $from  Old URL or path.
		 * @param string $to    New absolute URL.
		 * @param string $note  Where it came from ( 'category-slug:12', 'category-delete:12' ).
		 */
		public static function add( $from, $to, $note = '' ) {
			$from_key = self::key( $from );
			$to_key   = self::key( $to );
			if ( $from_key === $to_key || '/' === $from_key ) {
				return false;
			}
			$all = self::all();
			/* Collapse chains: anything that pointed at $from now points at $to */
			foreach ( $all as $k => $entry ) {
				if ( self::key( $entry['to'] ) === $from_key ) {
					$all[ $k ]['to'] = $to;
				}
			}
			$all[ $from_key ] = array( 'to' => $to, 'note' => $note, 'time' => time(), 'hits' => 0 );
			update_option( self::OPTION, $all, 'no' );
			return true;
		}

		public static function remove( $from ) {
			$all = self::all();
			$k   = self::key( $from );
			if ( isset( $all[ $k ] ) ) {
				unset( $all[ $k ] );
				update_option( self::OPTION, $all, 'no' );
				return true;
			}
			return false;
		}

		public static function maybe_redirect() {
			if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
				return;
			}
			$all = self::all();
			if ( empty( $all ) ) {
				return;
			}
			/* Only 404s ( or a hit on a private/draft page ) should redirect; a live page always wins. */
			if ( ! is_404() ) {
				return;
			}
			$request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			$k = self::key( $request );
			if ( ! isset( $all[ $k ] ) ) {
				return;
			}
			$to = $all[ $k ]['to'];
			$all[ $k ]['hits'] = isset( $all[ $k ]['hits'] ) ? (int) $all[ $k ]['hits'] + 1 : 1;
			update_option( self::OPTION, $all, 'no' );
			wp_safe_redirect( $to, 301 );
			exit;
		}
	}

	ec_url_redirects::init();

endif;
