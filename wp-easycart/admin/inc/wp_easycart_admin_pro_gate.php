<?php
/**
 * WP EasyCart Admin PRO Gate
 *
 * @since 5.x.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_pro_gate' ) ) :

class wp_easycart_admin_pro_gate {
	const PRO_BASENAME = 'wp-easycart-pro/wp-easycart-admin-pro.php';
	private static $status = null;

	public static function pro_status() {
		if ( null !== self::$status ) {
			return self::$status;
		}

		$pro_file = EC_PLUGIN_DIRECTORY . '-pro/wp-easycart-admin-pro.php';

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = file_exists( $pro_file );
		$active = $installed && is_plugin_active( self::PRO_BASENAME );

		$version = '';
		if ( $installed ) {
			$header = get_file_data( $pro_file, array( 'Version' => 'Version' ) );
			if ( ! empty( $header['Version'] ) ) {
				$version = $header['Version'];
			}
		}

		$licensed = false;
		if ( $active && function_exists( 'wp_easycart_admin_license' ) ) {
			$licensed = (bool) wp_easycart_admin_license()->is_licensed();
		}

		self::$status = array(
			'installed' => $installed,
			'active'    => $active,
			'version'   => $version,
			'licensed'  => $licensed,
		);
		return self::$status;
	}

	public static function evaluate( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'enabled' => null,
			'enabled_filter' => '',
			'min_version' => '5.8.15',
			'labels' => array(),
			'urls' => array(),
			'upsell_action' => 'show_pro_required',
			'upsell_view' => '',
		) );

		$status = self::pro_status();

		if ( null !== $args['enabled'] ) {
			$enabled = (bool) $args['enabled'];
		} else if ( '' !== $args['enabled_filter'] ) {
			$filter_enabled = (bool) apply_filters( $args['enabled_filter'], false );
			$version_ok     = ( $status['active'] && '' !== $status['version'] && version_compare( $status['version'], $args['min_version'], '>=' ) );
			$enabled        = ( $filter_enabled && $version_ok );
		} else {
			$enabled = ( $status['active'] && $status['licensed'] && '' !== $status['version'] && version_compare( $status['version'], $args['min_version'], '>=' ) );
		}

		if ( $enabled ) {
			$state = 'enabled';
		} else if ( ! $status['installed'] ) {
			$state = 'upsell';
		} else if ( ! $status['active'] ) {
			$state = 'inactive';
		} else if ( '' === $status['version'] || version_compare( $status['version'], $args['min_version'], '<' ) ) {
			$state = 'update';
		} else {
			$state = 'license';
		}

		$labels = array_merge( self::default_labels(), $args['labels'] );
		$urls   = array_merge( self::default_urls(), $args['urls'] );

		return array(
			'state' => $state,
			'desc' => isset( $labels[ $state ] ) ? $labels[ $state ] : '',
			'url' => isset( $urls[ $state ] ) ? $urls[ $state ] : '',
			'action' => ( 'upsell' === $state ) ? $args['upsell_action'] : 'redirect',
			'upsell_view' => (string) $args['upsell_view'],
		);
	}

	public static function is_enabled( $args = array() ) {
		$gate = self::evaluate( $args );
		return ( 'enabled' === $gate['state'] );
	}

	public static function message( $gate, $feature_label = '' ) {
		$feature_label = ( '' !== $feature_label ) ? $feature_label : __( 'This feature', 'wp-easycart' );
		switch ( isset( $gate['state'] ) ? $gate['state'] : '' ) {
			case 'upsell':
				/* translators: %s: feature name. */
				return sprintf( __( '%s requires WP EasyCart PRO.', 'wp-easycart' ), $feature_label );
			case 'inactive':
				/* translators: %s: feature name. */
				return sprintf( __( '%s requires WP EasyCart PRO to be activated.', 'wp-easycart' ), $feature_label );
			case 'update':
				/* translators: %s: feature name. */
				return sprintf( __( '%s requires an updated version of WP EasyCart PRO. Please update WP EasyCart PRO.', 'wp-easycart' ), $feature_label );
			case 'license':
				/* translators: %s: feature name. */
				return sprintf( __( '%s requires an active WP EasyCart PRO license. Please check your license under Store Status.', 'wp-easycart' ), $feature_label );
			default:
				return '';
		}
	}

	private static function default_labels() {
		return array(
			'enabled'  => '',
			'upsell'   => __( 'Available in WP EasyCart PRO', 'wp-easycart' ),
			'inactive' => __( 'Activate WP EasyCart PRO', 'wp-easycart' ),
			'update'   => __( 'Update WP EasyCart PRO to use this', 'wp-easycart' ),
			'license'  => __( 'Activate your WP EasyCart PRO license', 'wp-easycart' ),
		);
	}

	private static function default_urls() {
		return array(
			'enabled'  => '',
			'upsell'   => self_admin_url( 'admin.php?page=wp-easycart-registration' ),
			'inactive' => self_admin_url( 'plugins.php' ),
			'update'   => self_admin_url( 'plugins.php' ),
			'license'  => self_admin_url( 'admin.php?page=wp-easycart-license-status' ),
		);
	}
}

endif;