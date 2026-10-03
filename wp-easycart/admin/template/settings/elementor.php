<?php
/**
 * Settings › Elementor ( V2 declaration ).
 *
 * 6.0.2: one page for everything EasyCart does inside Elementor. This file declares the page and its status section;
 * the Elementor modules ( admin/elementor/modules/<name>/module.php ) add their own sections through the filter
 * wp_easycart_settings_page_elementor ( product and category templates, starter templates, account widgets ... ), so no
 * module edits this file. Must stay loadable outside WordPress ( the migration-map generator stubs WP ).
 *
 * @package wp-easycart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecst_elementor_feature_active' ) ) {
	/**
	 * Whether an Elementor experiment ( feature ) is on, or null when this Elementor cannot say.
	 *
	 * @param string $feature Experiment name.
	 * @return bool|null
	 */
	function ecst_elementor_feature_active( $feature ) {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->experiments ) || ! method_exists( \Elementor\Plugin::$instance->experiments, 'is_feature_active' ) ) {
			return null;
		}
		return (bool) \Elementor\Plugin::$instance->experiments->is_feature_active( $feature );
	}
}

if ( ! function_exists( 'ecst_elementor_status_row' ) ) {
	/**
	 * One row of the status list.
	 *
	 * @param string $label  What the row is about.
	 * @param string $value  Its state, a few words.
	 * @param string $state  ok | warn | off.
	 * @param string $detail One sentence on what it means for the store ( optional ).
	 * @param string $url    Where to change it ( optional ).
	 * @param string $link   Link text.
	 */
	function ecst_elementor_status_row( $label, $value, $state, $detail = '', $url = '', $link = '' ) {
		echo '<li class="ecst-el-row"><span class="ecst-el-label">' . esc_html( $label ) . '</span>';
		echo '<span class="ecst-el-value is-' . esc_attr( $state ) . '">' . esc_html( $value ) . '</span>';
		if ( '' !== $detail || '' !== $url ) {
			echo '<span class="ecst-el-detail">' . esc_html( $detail );
			if ( '' !== $url ) {
				echo ' <a href="' . esc_url( $url ) . '">' . esc_html( $link ) . '</a>';
			}
			echo '</span>';
		}
		echo '</li>';
	}
}

if ( ! function_exists( 'ecst_elementor_render_status' ) ) {
	/**
	 * Status card: Elementor and Elementor Pro versions, the editor in use, the element cache, inline icons, where to start.
	 */
	function ecst_elementor_render_status() {
		$has_elementor = defined( 'ELEMENTOR_VERSION' );
		$has_pro       = defined( 'ELEMENTOR_PRO_VERSION' );
		$tested        = '4.3.2';
		$tested_pro    = '4.3.1';
		$settings_url  = admin_url( 'admin.php?page=elementor-settings' );
		echo '<div class="ecst-elementor-status">';
		if ( ! $has_elementor ) {
			echo '<p class="ecst-el-intro">' . esc_html__( 'Elementor is not active. Install and activate Elementor ( the free version is enough ) to design your product, category, cart and account pages with the WP EasyCart widgets.', 'wp-easycart' ) . '</p>';
			echo '<p><a class="ecst-el-button" href="' . esc_url( admin_url( 'plugin-install.php?s=elementor&tab=search&type=term' ) ) . '">' . esc_html__( 'Get Elementor', 'wp-easycart' ) . '</a></p>';
			echo '</div>';
			do_action( 'wp_easycart_settings_elementor_status' );
			return;
		}
		echo '<ul class="ecst-el-list">';

		$newer = version_compare( ELEMENTOR_VERSION, $tested, '>' );
		ecst_elementor_status_row(
			__( 'Elementor', 'wp-easycart' ),
			/* translators: %s: version number. */
			sprintf( __( 'Version %s', 'wp-easycart' ), ELEMENTOR_VERSION ),
			$newer ? 'warn' : 'ok',
			/* translators: %s: version number. */
			$newer ? sprintf( __( 'Newer than WP EasyCart was tested with ( %s ). The widgets keep working; tell us if anything looks wrong.', 'wp-easycart' ), $tested ) : __( 'Every WP EasyCart widget and template works with the free Elementor.', 'wp-easycart' )
		);

		if ( $has_pro ) {
			$newer_pro = version_compare( ELEMENTOR_PRO_VERSION, $tested_pro, '>' );
			ecst_elementor_status_row(
				__( 'Elementor Pro', 'wp-easycart' ),
				/* translators: %s: version number. */
				sprintf( __( 'Version %s', 'wp-easycart' ), ELEMENTOR_PRO_VERSION ),
				$newer_pro ? 'warn' : 'ok',
				/* translators: %s: version number. */
				$newer_pro ? sprintf( __( 'Newer than WP EasyCart was tested with ( %s ).', 'wp-easycart' ), $tested_pro ) : __( 'The Theme Builder can show your EasyCart templates on product and category pages.', 'wp-easycart' )
			);
			/* 6.0.2: WP EasyCart's element display conditions register only on the Elementor Pro versions they were written for. */
			if ( class_exists( 'WP_EasyCart_Elementor_Dynamic' ) && method_exists( 'WP_EasyCart_Elementor_Dynamic', 'conditions_status' ) ) {
				$conditions = WP_EasyCart_Elementor_Dynamic::conditions_status();
				$warn       = in_array( $conditions['state'], array( 'newer', 'off' ), true ) && $conditions['in_use'] > 0;
				ecst_elementor_status_row(
					__( 'Display conditions', 'wp-easycart' ),
					$conditions['value'],
					( 'on' === $conditions['state'] ) ? 'ok' : ( ( $warn || 'newer' === $conditions['state'] ) ? 'warn' : 'off' ),
					$conditions['detail']
				);
			}
		} else {
			ecst_elementor_status_row( __( 'Elementor Pro', 'wp-easycart' ), __( 'Not installed', 'wp-easycart' ), 'off', __( 'Not needed: WP EasyCart assigns its own product and category templates.', 'wp-easycart' ) );
		}

		$atomic = ecst_elementor_feature_active( 'e_opt_in_v4' );
		if ( ! $atomic ) {
			$atomic = ecst_elementor_feature_active( 'e_atomic_elements' );
		}
		if ( null !== $atomic ) {
			ecst_elementor_status_row(
				__( 'Editor V4 ( Atomic )', 'wp-easycart' ),
				$atomic ? __( 'On', 'wp-easycart' ) : __( 'Off', 'wp-easycart' ),
				$atomic ? 'warn' : 'ok',
				$atomic ? __( 'WP EasyCart widgets are classic widgets. They work next to atomic elements; find them in the widget panel under WP EasyCart.', 'wp-easycart' ) : __( 'The classic editor, where every WP EasyCart widget is listed.', 'wp-easycart' )
			);
		}

		if ( version_compare( ELEMENTOR_VERSION, '3.22.0', '>=' ) ) {
			$cache_on = version_compare( ELEMENTOR_VERSION, '3.31.0', '>=' ) ? true : (bool) ecst_elementor_feature_active( 'e_element_cache' );
			$ttl      = (string) get_option( 'elementor_element_cache_ttl', '24' );
			if ( 'disable' === $ttl ) {
				$cache_on = false;
			}
			ecst_elementor_status_row(
				__( 'Element cache', 'wp-easycart' ),
				$cache_on ? __( 'On', 'wp-easycart' ) : __( 'Off', 'wp-easycart' ),
				'ok',
				__( 'WP EasyCart widgets are never cached, so prices, stock and carts stay current. Leave each widget\'s Advanced › Cache Settings on Default.', 'wp-easycart' ),
				$settings_url . '#tab-performance',
				__( 'Performance settings', 'wp-easycart' )
			);
		}

		$inline_icons = ecst_elementor_feature_active( 'e_font_icon_svg' );
		if ( null !== $inline_icons ) {
			ecst_elementor_status_row(
				__( 'Inline font icons', 'wp-easycart' ),
				$inline_icons ? __( 'On', 'wp-easycart' ) : __( 'Off', 'wp-easycart' ),
				'ok',
				$inline_icons ? __( 'Icons are drawn as SVG; the WP EasyCart widgets draw theirs the same way.', 'wp-easycart' ) : __( 'Icons load from the Font Awesome stylesheet.', 'wp-easycart' )
			);
		}

		echo '</ul>';
		echo '<p class="ecst-el-links">';
		echo '<a class="ecst-el-button" href="' . esc_url( admin_url( 'edit.php?post_type=elementor_library&tabs_group=library' ) ) . '">' . esc_html__( 'Elementor templates', 'wp-easycart' ) . '</a> ';
		$overview = class_exists( 'WP_EasyCart_Elementor' ) ? WP_EasyCart_Elementor::help_url( 'overview', 'settings' ) : 'https://docs.wpeasycart.com/docs/how-to-guides/elementor-connect-and-widgets-overview/';
		echo '<a href="' . esc_url( $overview ) . '" target="_blank" rel="noopener">' . esc_html__( 'How the WP EasyCart widgets work', 'wp-easycart' ) . '</a>';
		echo '</p>';
		if ( class_exists( 'WP_EasyCart_Elementor' ) ) {
			/* 6.0.2: the Elementor how-to guides on docs.wpeasycart.com, in reading order. */
			echo '<div class="ecst-el-guides"><span class="ecst-el-guides-label">' . esc_html__( 'Guides', 'wp-easycart' ) . '</span><ul>';
			foreach ( WP_EasyCart_Elementor::help_guides() as $guide_key => $guide ) {
				echo '<li><a href="' . esc_url( WP_EasyCart_Elementor::help_url( $guide_key, 'settings' ) ) . '" target="_blank" rel="noopener">' . esc_html( $guide[1] ) . '</a></li>';
			}
			echo '</ul></div>';
		}
		echo '</div>';
		do_action( 'wp_easycart_settings_elementor_status' );
	}
}

if ( ! function_exists( 'ecst_elementor_enqueue' ) ) {
	/**
	 * The status card's styles.
	 */
	function ecst_elementor_enqueue() {
		if ( function_exists( 'wp_enqueue_style' ) && defined( 'EC_PLUGIN_DIRECTORY' ) ) {
			wp_enqueue_style( 'wpeasycart-elementor-settings', plugins_url( 'admin/elementor/assets/wpeasycart-elementor-settings.css', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), array(), defined( 'EC_CURRENT_VERSION' ) ? EC_CURRENT_VERSION : false );
		}
	}
}

return array(
	'slug'        => 'elementor',
	'title'       => __( 'Elementor', 'wp-easycart' ),
	'description' => __( 'Design product, category, cart and account pages with the WP EasyCart widgets for Elementor.', 'wp-easycart' ),
	'group'       => 'appearance',
	'order'       => 15,
	'icon'        => 'layout',
	'enqueue'     => 'ecst_elementor_enqueue',
	'sections'    => array(
		'status' => array(
			'title'  => __( 'Elementor', 'wp-easycart' ),
			'hint'   => __( 'Whether Elementor is ready for your store pages.', 'wp-easycart' ),
			'icon'   => 'layout',
			'fields' => array(),
			'render' => 'ecst_elementor_render_status',
		),
	),
);
