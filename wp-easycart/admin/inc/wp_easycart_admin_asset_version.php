<?php
/**
 * Cache busting for EasyCart admin assets.
 *
 * Every script and stylesheet of WP EasyCart and WP EasyCart PRO is enqueued with the release as its version
 * ( EC_CURRENT_VERSION, WP_EASYCART_ADMIN_PRO_VERSION ). That string stays the same across every build of one release,
 * so after an update a browser can keep running the old file until the merchant forces a reload. In the admin, the
 * version of an asset that lives in one of those plugins also carries the file's modified time.
 *
 * @since 6.0.1
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_admin_asset_version' ) ) {
	/**
	 * Filter script_loader_src / style_loader_src: add the file time to an EasyCart asset's version.
	 *
	 * @param string $src    Asset URL.
	 * @param string $handle Handle.
	 * @return string
	 */
	function wp_easycart_admin_asset_version( $src, $handle = '' ) {
		if ( ! is_string( $src ) || false === strpos( $src, '/wp-easycart' ) || ! defined( 'WP_PLUGIN_DIR' ) ) {
			return $src;
		}
		static $plugins_path = null;
		if ( null === $plugins_path ) {
			$plugins_path = (string) wp_parse_url( plugins_url(), PHP_URL_PATH );
		}
		$path = (string) wp_parse_url( $src, PHP_URL_PATH );
		if ( '' === $plugins_path || 0 !== strpos( $path, $plugins_path . '/' ) ) {
			return $src;
		}
		/* Resolve "." and ".." ( some assets are enqueued as plugins_url( '../css/…', __FILE__ ) ), then require the result
		   to still be a .js / .css file inside one of the two plugins. */
		$parts = array();
		foreach ( explode( '/', substr( $path, strlen( $plugins_path ) ) ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				array_pop( $parts );
				continue;
			}
			$parts[] = $part;
		}
		$relative = '/' . implode( '/', $parts );
		if ( ! preg_match( '#^/wp-easycart(-pro)?/[^?]+\.(js|css)$#', $relative ) ) {
			return $src;
		}
		$file = WP_PLUGIN_DIR . $relative;
		if ( ! is_file( $file ) ) {
			return $src;
		}
		$query   = (string) wp_parse_url( $src, PHP_URL_QUERY );
		$current = '';
		if ( '' !== $query ) {
			parse_str( $query, $args );
			$current = isset( $args['ver'] ) ? (string) $args['ver'] : '';
		}
		$stamp = (string) filemtime( $file );
		if ( '' !== $current && false !== strpos( $current, '-' . $stamp ) ) {
			return $src;
		}
		return add_query_arg( 'ver', rawurlencode( ( '' !== $current ? $current : 'x' ) . '-' . $stamp ), $src );
	}
	add_filter( 'script_loader_src', 'wp_easycart_admin_asset_version', 20, 2 );
	add_filter( 'style_loader_src', 'wp_easycart_admin_asset_version', 20, 2 );
}
