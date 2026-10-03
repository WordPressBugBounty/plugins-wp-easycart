<?php
/**
 * WP EasyCart Elementor helpers ( 6.0.2 ).
 *
 * Loaded with the Elementor integration on every request ( the widgets render on the storefront ), so nothing here may
 * assume Elementor is active.
 *
 * @package  Wp_Easycart_Elementor
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_elementor_shortcode_atts' ) ) {
	/**
	 * Builds the attribute string a widget's display file hands to its EasyCart shortcode.
	 *
	 * The display files used to build `key=json_encode( value )` pairs. Shortcode parsing runs stripcslashes() on a quoted
	 * value, so an accented or non-Latin character ( json_encode's \uXXXX ) came out as the letters "uXXXX", and a quote
	 * or a square bracket in a setting ended the attribute or the whole shortcode early. Plain ASCII values produce exactly
	 * the old string; only values that were garbled or broken before change.
	 *
	 * @since 6.0.2
	 *
	 * @param array $atts Attribute name => value.
	 * @return string Leading space, then `name=value` pairs separated by spaces.
	 */
	function wp_easycart_elementor_shortcode_atts( $atts ) {
		$out = ' ';
		foreach ( (array) $atts as $key => $value ) {
			if ( is_string( $value ) ) {
				$encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS );
				$inner   = ( is_string( $encoded ) && strlen( $encoded ) >= 2 ) ? substr( $encoded, 1, -1 ) : '';
				/* An escaped quote ends a quoted attribute and a bracket ends the shortcode: use entities, which the templates print as-is. */
				$inner = str_replace( array( '\\"', '[', ']' ), array( '&quot;', '&#91;', '&#93;' ), $inner );
				$out  .= $key . '="' . $inner . '" ';
			} else {
				$out .= $key . '=' . wp_json_encode( $value ) . ' ';
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'wp_easycart_elementor_pro_feature' ) ) {
	/**
	 * State of a WP EasyCart PRO Elementor feature ( a key of the 'elementor' upsell catalog ): 'active', 'update' ( an
	 * older WP EasyCart PRO is active: the store updates, it is never offered a plan ) or 'locked'.
	 *
	 * PRO answers filter wp_easycart_elementor_pro_features with the features its license unlocks ( array of keys ).
	 *
	 * @since 6.0.2
	 *
	 * @param string $feature     Feature key.
	 * @param string $min_version PRO version that ships the feature.
	 * @return string
	 */
	function wp_easycart_elementor_pro_feature( $feature, $min_version = '6.0.2' ) {
		$features = (array) apply_filters( 'wp_easycart_elementor_pro_features', array() );
		if ( in_array( $feature, $features, true ) ) {
			return 'active';
		}
		if ( defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) && version_compare( WP_EASYCART_ADMIN_PRO_VERSION, $min_version, '<' ) ) {
			return 'update';
		}
		return 'locked';
	}
}

if ( ! function_exists( 'wp_easycart_elementor_upgrade_url' ) ) {
	/**
	 * Where a locked Elementor feature sends the merchant ( the pricing page ).
	 *
	 * @since 6.0.2
	 *
	 * @param string $feature Feature key ( for the filter ).
	 * @return string
	 */
	function wp_easycart_elementor_upgrade_url( $feature = '' ) {
		$url = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::PRICING_URL : 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/';
		return (string) apply_filters( 'wp_easycart_elementor_upgrade_url', $url, $feature );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_requires_text' ) ) {
	/**
	 * "Included with …" wording for a locked widget, naming the store's plan ( wp_easycart_admin_edition when loaded ).
	 *
	 * @since 6.0.2
	 *
	 * @param string $feature Feature name shown to the merchant ( default: this widget ).
	 * @return string
	 */
	function wp_easycart_elementor_requires_text( $feature = '' ) {
		if ( '' === $feature ) {
			$feature = __( 'This widget', 'wp-easycart' );
		}
		if ( class_exists( 'wp_easycart_admin_edition' ) && method_exists( 'wp_easycart_admin_edition', 'requires_text' ) ) {
			return wp_easycart_admin_edition::requires_text( $feature );
		}
		/* translators: %s: feature name. */
		return sprintf( __( '%s is included with a Pro/Premium plan.', 'wp-easycart' ), $feature );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_is_editor' ) ) {
	/**
	 * Whether this request draws Elementor's editor or its preview ( both check the user may edit ).
	 *
	 * Covers the editor page, the preview iframe ( elementor-preview ) and the editor's AJAX re-renders ( elementor_ajax ).
	 * Widgets use it to show a sample product, or a notice, where a visitor must see nothing.
	 *
	 * @since 6.0.2
	 *
	 * @return bool
	 */
	function wp_easycart_elementor_is_editor() {
		/* 6.0.2: a widget's plain content ( what post_content keeps for visitors ) never carries the editor's sample product or notes. */
		if ( function_exists( 'wp_easycart_elementor_capturing' ) && wp_easycart_elementor_capturing() ) {
			return false;
		}
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$elementor = \Elementor\Plugin::$instance;
		if ( isset( $elementor->editor ) && is_object( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}
		if ( isset( $elementor->preview ) && is_object( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) ) {
			if ( $elementor->preview->is_preview_mode() ) {
				return true;
			}
			/* A Theme Builder template previewed "as" another post switches the query, so get_the_ID() no longer matches the
			 * elementor-preview ID; ask about the document being previewed instead ( Elementor still checks the user may edit it ). */
			$preview_id = isset( $_GET['elementor-preview'] ) ? absint( $_GET['elementor-preview'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; Elementor checks the capability for this ID.
			if ( $preview_id && $elementor->preview->is_preview_mode( $preview_id ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'ecv2_elementor_guard' ) ) {
	/**
	 * Nonce and capability check for the Elementor editor's AJAX requests ( 6.0.2, WP_EasyCart_Elementor_Editor ). Ends the
	 * request on failure. Nonce 'wp-easycart-ecv2-elementor' in the field 'nonce'; the person must be able to edit posts.
	 *
	 * @since 6.0.2
	 */
	function ecv2_elementor_guard() {
		if ( ! check_ajax_referer( 'wp-easycart-ecv2-elementor', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the editor and try again.', 'wp-easycart' ) ), 403 );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this page.', 'wp-easycart' ) ), 403 );
		}
	}
}

if ( ! function_exists( 'wp_easycart_elementor_store_assets' ) ) {
	/**
	 * The store's own script or style handles for a widget's get_script_depends() / get_style_depends() ( 6.0.2 ): the
	 * handles while EasyCart loads its script ( 'js', filter wp_easycart_load_js_scripts ) or stylesheet ( 'css', filter
	 * wp_easycart_load_css_scripts ), else none, so a store that turned them off never gets them back through Elementor.
	 *
	 * @since 6.0.2
	 *
	 * @param string $kind    js | css.
	 * @param array  $handles Handles that belong to the store's script or stylesheet ( wpeasycart_js, wpeasycart_css, Owl ).
	 * @return array
	 */
	function wp_easycart_elementor_store_assets( $kind, $handles ) {
		$on = apply_filters( ( 'css' === $kind ) ? 'wp_easycart_load_css_scripts' : 'wp_easycart_load_js_scripts', true );
		return $on ? array_values( (array) $handles ) : array();
	}
}

if ( ! function_exists( 'wp_easycart_elementor_capturing' ) ) {
	/**
	 * Whether a widget's plain content is being captured ( wp_easycart_elementor_capture_shortcodes() ).
	 *
	 * @since 6.0.2
	 *
	 * @return bool
	 */
	function wp_easycart_elementor_capturing() {
		return ! empty( $GLOBALS['wp_easycart_elementor_plain_capture'] );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_do_shortcode' ) ) {
	/**
	 * Runs the shortcode a widget's display file builds ( 6.0.2 ). While the widget's plain content is captured it records
	 * the shortcode instead and returns '', so post_content gets the same shortcode the widget draws with.
	 *
	 * @since 6.0.2
	 *
	 * @param string $shortcode Full shortcode, e.g. `[ec_store ...]`.
	 * @return string Its output ( '' while capturing ).
	 */
	function wp_easycart_elementor_do_shortcode( $shortcode ) {
		if ( wp_easycart_elementor_capturing() ) {
			wp_easycart_elementor_plain_shortcode( $shortcode );
			return '';
		}
		return do_shortcode( $shortcode );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_plain_shortcode' ) ) {
	/**
	 * Names the shortcode equivalent of a widget that draws its own markup ( 6.0.2 ). Nothing happens in a normal render;
	 * while the widget's plain content is captured the shortcode is recorded for post_content.
	 *
	 * @since 6.0.2
	 *
	 * @param string $shortcode Full shortcode.
	 */
	function wp_easycart_elementor_plain_shortcode( $shortcode ) {
		if ( ! wp_easycart_elementor_capturing() || ! is_string( $shortcode ) || '' === trim( $shortcode ) ) {
			return;
		}
		$level = count( $GLOBALS['wp_easycart_elementor_plain_capture'] ) - 1;
		$GLOBALS['wp_easycart_elementor_plain_capture'][ $level ][] = trim( $shortcode );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_capture_shortcodes' ) ) {
	/**
	 * Runs a widget render and returns the shortcodes its display file named, one per line, discarding everything it
	 * printed ( 6.0.2 ). Used for render_plain_content(): Elementor saves that into post_content on every editor save,
	 * which search, SEO plugins and a site with Elementor switched off read. The editor's sample product and notes stay
	 * out of it ( wp_easycart_elementor_is_editor() is false meanwhile ).
	 *
	 * @since 6.0.2
	 *
	 * @param callable $render Draws the widget.
	 * @return string
	 */
	function wp_easycart_elementor_capture_shortcodes( $render ) {
		if ( ! isset( $GLOBALS['wp_easycart_elementor_plain_capture'] ) || ! is_array( $GLOBALS['wp_easycart_elementor_plain_capture'] ) ) {
			$GLOBALS['wp_easycart_elementor_plain_capture'] = array();
		}
		$GLOBALS['wp_easycart_elementor_plain_capture'][] = array();
		$ob_level = ob_get_level();
		ob_start();
		try {
			if ( is_callable( $render ) ) {
				call_user_func( $render );
			}
		} catch ( Throwable $e ) {
			unset( $e ); /* a widget that cannot draw here saves no plain content */
		} finally {
			while ( ob_get_level() > $ob_level ) {
				ob_end_clean();
			}
		}
		$shortcodes = array_pop( $GLOBALS['wp_easycart_elementor_plain_capture'] );
		return implode( "\n", array_unique( (array) $shortcodes ) );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_retired_widgets' ) ) {
	/**
	 * Older widgets that have a newer replacement ( 6.0.2 ), from the filter wp_easycart_elementor_retired_widgets:
	 * legacy widget name => array( 'replacement' => new widget name, 'title' => new widget title, 'map' => callable ).
	 * The map takes the older widget's saved settings and returns the new widget's; it must be pure ( no output, no writes ).
	 *
	 * @since 6.0.2
	 *
	 * @return array Entries with a replacement name ( malformed ones are dropped ).
	 */
	function wp_easycart_elementor_retired_widgets() {
		$retired = apply_filters( 'wp_easycart_elementor_retired_widgets', array() );
		$valid   = array();
		if ( ! is_array( $retired ) ) {
			return $valid;
		}
		foreach ( $retired as $legacy => $entry ) {
			if ( ! is_string( $legacy ) || ! is_array( $entry ) || empty( $entry['replacement'] ) || ! is_string( $entry['replacement'] ) || $entry['replacement'] === $legacy ) {
				continue;
			}
			$valid[ $legacy ] = array(
				'replacement' => $entry['replacement'],
				'title'       => ( isset( $entry['title'] ) && is_string( $entry['title'] ) && '' !== $entry['title'] ) ? $entry['title'] : $entry['replacement'],
				'map'         => ( isset( $entry['map'] ) && is_callable( $entry['map'] ) ) ? $entry['map'] : null,
			);
		}
		return $valid;
	}
}

if ( ! function_exists( 'wp_easycart_elementor_widget_registered' ) ) {
	/**
	 * Whether WP EasyCart registered a widget under this name with Elementor in this request ( 6.0.2 ). Answers only after
	 * Elementor registered its widgets ( the editor, a page that draws widgets ).
	 *
	 * @since 6.0.2
	 *
	 * @param string $name Widget name ( get_name() ).
	 * @return bool
	 */
	function wp_easycart_elementor_widget_registered( $name ) {
		return class_exists( 'WP_EasyCart_Elementor' ) && method_exists( 'WP_EasyCart_Elementor', 'is_widget_registered' ) && WP_EasyCart_Elementor::is_widget_registered( $name );
	}
}

if ( ! function_exists( 'wp_easycart_elementor_retired_replacement' ) ) {
	/**
	 * The replacement of an older widget when one is mapped AND registered ( 6.0.2 ), else ''. Only then is the older
	 * widget hidden from the panel and offered for replacement; it always keeps drawing saved pages.
	 *
	 * @since 6.0.2
	 *
	 * @param string $legacy Older widget name.
	 * @return string
	 */
	function wp_easycart_elementor_retired_replacement( $legacy ) {
		$retired = wp_easycart_elementor_retired_widgets();
		if ( ! isset( $retired[ $legacy ] ) || ! wp_easycart_elementor_widget_registered( $retired[ $legacy ]['replacement'] ) ) {
			return '';
		}
		return $retired[ $legacy ]['replacement'];
	}
}

if ( ! function_exists( 'wp_easycart_elementor_map_legacy_settings' ) ) {
	/**
	 * The new widget's settings for an older widget's saved settings, through its map ( 6.0.2 ).
	 *
	 * A map may pick another replacement for these settings ( one legacy widget split into several new ones ): when the
	 * array it returns holds the key `_wpec_replacement`, that widget name is inserted instead of the entry's
	 * 'replacement' ( the key is removed from the settings ). The legacy widget still leaves the panel only when the entry's
	 * own 'replacement' is registered.
	 *
	 * @since 6.0.2
	 *
	 * @param string $legacy   Older widget name.
	 * @param array  $settings Its saved settings ( only the values that differ from the defaults, as the editor saves them ).
	 * @return array|WP_Error array( 'replacement', 'title', 'settings' ), or an error.
	 */
	function wp_easycart_elementor_map_legacy_settings( $legacy, $settings ) {
		$retired = wp_easycart_elementor_retired_widgets();
		if ( ! isset( $retired[ $legacy ] ) ) {
			return new WP_Error( 'wpec_elementor_not_retired', __( 'This widget has no newer version yet.', 'wp-easycart' ) );
		}
		$entry  = $retired[ $legacy ];
		$mapped = array();
		if ( $entry['map'] ) {
			ob_start();
			try {
				$mapped = call_user_func( $entry['map'], is_array( $settings ) ? $settings : array() );
			} catch ( Throwable $e ) {
				$mapped = new WP_Error( 'wpec_elementor_map_failed', __( 'The settings could not be copied to the new widget.', 'wp-easycart' ) );
			}
			ob_end_clean();
		}
		if ( is_wp_error( $mapped ) ) {
			return $mapped;
		}
		$mapped      = is_array( $mapped ) ? $mapped : array();
		$replacement = $entry['replacement'];
		$title       = $entry['title'];
		if ( array_key_exists( '_wpec_replacement', $mapped ) ) {
			$other = is_string( $mapped['_wpec_replacement'] ) ? sanitize_key( $mapped['_wpec_replacement'] ) : '';
			unset( $mapped['_wpec_replacement'] );
			if ( '' !== $other && $other !== $legacy && $other !== $replacement ) {
				$replacement = $other;
				$title       = $other; /* the AJAX answer names it from Elementor's registry */
			}
		}
		return array(
			'replacement' => $replacement,
			'title'       => $title,
			'settings'    => $mapped,
		);
	}
}
