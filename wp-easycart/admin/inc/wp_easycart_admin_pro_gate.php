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

	/**
	 * Oldest PRO this FREE release can load into its admin. 6.0.0 removed the legacy admin
	 * controllers older PRO releases call unguarded, so an older PRO is kept out of the admin
	 * ( see wp_easycart_admin::setup_pro_hooks() ) and every gate reports 'update'. Mirrors the
	 * MIN_WP_EASYCART_VERSION gate in the PRO bootstrap.
	 *
	 * @since 6.0.0
	 */
	const MIN_PRO_VERSION = '6.0.0';

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

		/* The runtime constant wins over the file header when PRO is active ( renamed or symlinked folders ). */
		if ( $active && defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) && '' !== WP_EASYCART_ADMIN_PRO_VERSION ) {
			$version = WP_EASYCART_ADMIN_PRO_VERSION;
		}
		$outdated = ( $active && ( '' === $version || version_compare( $version, self::MIN_PRO_VERSION, '<' ) ) );

		self::$status = array(
			'installed' => $installed,
			'active'    => $active,
			'version'   => $version,
			'licensed'  => $licensed,
			'outdated'  => $outdated,
		);
		return self::$status;
	}

	/**
	 * Whether an active PRO is older than MIN_PRO_VERSION ( its admin is not loaded then ).
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_outdated() {
		$status = self::pro_status();
		return ! empty( $status['outdated'] );
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
			/*
			 * A truthy filter can only come from running PRO code, so it is
			 * authoritative proof of an installed + active PRO plugin - more
			 * reliable than folder-path detection (which breaks on renamed
			 * or symlinked plugin directories). Prefer the runtime version
			 * constant for the same reason, falling back to the file header.
			 */
			$runtime_version = defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) ? WP_EASYCART_ADMIN_PRO_VERSION : $status['version'];
			$version_ok      = ( '' !== $runtime_version && version_compare( $runtime_version, $args['min_version'], '>=' ) );
			$enabled         = ( $filter_enabled && $version_ok );
		} else {
			$enabled = ( $status['active'] && $status['licensed'] && '' !== $status['version'] && version_compare( $status['version'], $args['min_version'], '>=' ) );
		}

		if ( ! empty( $status['outdated'] ) ) {
			$enabled = false; // An outdated PRO never unlocks anything: its admin is not loaded.
		}

		if ( $enabled ) {
			$state = 'enabled';
		} else if ( ! empty( $status['outdated'] ) ) {
			$state = 'update';
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
				if ( class_exists( 'wp_easycart_admin_edition' ) ) {
					return wp_easycart_admin_edition::requires_text( $feature_label );
				}
				/* translators: %s: feature name. */
				return sprintf( __( '%s is included with Pro and Premium licenses.', 'wp-easycart' ), $feature_label );
			case 'inactive':
				/* translators: %s: feature name. */
				return sprintf( __( '%s requires the WP EasyCart PRO plugin to be activated.', 'wp-easycart' ), $feature_label );
			case 'update':
				/* translators: %s: feature name. */
				return sprintf( __( '%s requires an update to the WP EasyCart PRO plugin. Please update WP EasyCart PRO.', 'wp-easycart' ), $feature_label );
			case 'license':
				if ( class_exists( 'wp_easycart_admin_edition' ) && wp_easycart_admin_edition::is_lapsed() ) {
					return wp_easycart_admin_edition::requires_text( $feature_label );
				}
				/* translators: %s: feature name. */
				return sprintf( __( '%s requires an active Pro or Premium license. Please check your license under Store Status.', 'wp-easycart' ), $feature_label );
			default:
				return '';
		}
	}

	private static function default_labels() {
		return array(
			'enabled'  => '',
			'upsell'   => class_exists( 'wp_easycart_admin_edition' ) ? rtrim( wp_easycart_admin_edition::included_text( 'pro' ), '.' ) : __( 'Included with Pro and Premium licenses', 'wp-easycart' ),
			'inactive' => __( 'Activate WP EasyCart PRO', 'wp-easycart' ),
			'update'   => __( 'Update WP EasyCart PRO to use this', 'wp-easycart' ),
			'license'  => ( class_exists( 'wp_easycart_admin_edition' ) && wp_easycart_admin_edition::is_lapsed() ) ? rtrim( wp_easycart_admin_edition::included_text( 'pro' ), '.' ) : __( 'Activate your Pro or Premium license', 'wp-easycart' ),
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