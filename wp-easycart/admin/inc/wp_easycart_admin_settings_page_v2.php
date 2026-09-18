<?php
/**
 * WP EasyCart Admin — V2 settings page renderer + AJAX.
 *
 * Renders any page declared in the settings registry: header with docs link
 * and the settings search, sticky section list, one card per section, rows per
 * field, advanced fold, PRO-locked rows, and a sticky save bar for typed
 * fields. Toggles / selects save on change; everything else batches.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_settings_guard' ) ) {
	/** Capability + nonce guard for every settings V2 AJAX handler. Dies on failure. */
	function ecv2_settings_guard() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
		}
		check_ajax_referer( wp_easycart_admin_settings_registry::NONCE, 'nonce' );
	}
}

if ( ! class_exists( 'wp_easycart_admin_settings_page_v2' ) ) :

class wp_easycart_admin_settings_page_v2 {

	/** Transient ( per user ) holding the store-page problems the merchant dismissed from the admin notice. */
	const STORE_NOTICE_TRANSIENT = 'wp_easycart_store_pages_notice_';

	/** Problems reported by the page being rendered, keyed by field key ( see page_problems() ). */
	private static $problems = array();

	/**
	 * The settings slug being viewed ( 'home', a declared page, or the owner of a
	 * legacy slug ), or '' when this request is not a V2 settings page.
	 */
	public static function current_slug() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) || 'wp-easycart-settings' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
			return '';
		}
		$sub = isset( $_GET['subpage'] ) ? sanitize_key( wp_unslash( $_GET['subpage'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$wizard_done = (bool) get_option( 'ec_option_setup_wizard_done' );
		if ( '' === $sub ) {
			return $wizard_done ? 'home' : '';
		}
		if ( 'home' === $sub ) {
			return 'home';
		}
		if ( 'initial-setup' === $sub && ! $wizard_done ) {
			return '';
		}
		if ( wp_easycart_admin_settings_registry::has( $sub ) ) {
			return $sub;
		}
		return wp_easycart_admin_settings_registry::page_for_legacy( $sub );
	}

	/**
	 * A settings subpage that is not a declared page, the home or the setup wizard.
	 *
	 * @since 6.0.0
	 */
	public static function is_classic_settings_screen() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) || 'wp-easycart-settings' !== $_GET['page'] || ! isset( $_GET['subpage'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
			return false;
		}
		$sub = sanitize_key( wp_unslash( $_GET['subpage'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		if ( in_array( $sub, array( '', 'home', 'setup-wizard' ), true ) || ! get_option( 'ec_option_setup_wizard_done' ) ) {
			return false;
		}
		return '' === self::current_slug();
	}

	/**
	 * Enqueue everything a settings page needs from admin_enqueue_scripts, so
	 * styles print in <head> instead of the footer ( no flash of unstyled rows ).
	 * Pages and sections may declare 'enqueue' => callable for their own assets.
	 */
	public static function enqueue_early() {
		$slug = self::current_slug();
		if ( '' === $slug ) {
			/* Classic settings pages ( Shipping rates, Countries, Logs … ) still get the search bar printed above them. */
			if ( self::is_classic_settings_screen() ) {
				self::enqueue();
			}
			return;
		}
		self::enqueue();
		if ( 'home' === $slug ) {
			add_filter( 'wp_easycart_setup_wizard_assets_needed', '__return_true' );
			return;
		}
		$page = wp_easycart_admin_settings_registry::page( $slug );
		if ( ! $page ) {
			return;
		}
		if ( isset( $page['enqueue'] ) && is_callable( $page['enqueue'] ) ) {
			call_user_func( $page['enqueue'], $page );
		}
		foreach ( $page['sections'] as $section ) {
			if ( isset( $section['enqueue'] ) && is_callable( $section['enqueue'] ) ) {
				call_user_func( $section['enqueue'], $page, $section );
			}
		}
		do_action( 'wp_easycart_settings_page_enqueue', $slug, $page );
	}

	public static function enqueue() {
		$css = plugins_url( '/admin/css/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		$js  = plugins_url( '/admin/js/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		wp_enqueue_style( 'wp_easycart_admin_v2_css', $css . 'admin-v2.css', array(), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wp_easycart_admin_details_v2_css', $css . 'admin-details-v2.css', array( 'wp_easycart_admin_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wp_easycart_admin_catalog_v2_css', $css . 'catalog-v2.css', array( 'wp_easycart_admin_details_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wp_easycart_admin_settings_page_v2_css', $css . 'settings-page-v2.css', array( 'wp_easycart_admin_catalog_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_script( 'wp_easycart_admin_catalog_v2_js', $js . 'catalog-v2.js', array( 'jquery' ), EC_CURRENT_VERSION, true );
		wp_enqueue_script( 'wp_easycart_admin_settings_page_v2_js', $js . 'settings-page-v2.js', array( 'jquery', 'wp_easycart_admin_catalog_v2_js' ), EC_CURRENT_VERSION, true );
		wp_localize_script( 'wp_easycart_admin_settings_page_v2_js', 'ecst_vars', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( wp_easycart_admin_settings_registry::NONCE ),
			'i18n'  => array(
				'saved'        => __( 'Saved', 'wp-easycart' ),
				'saving'       => __( 'Saving…', 'wp-easycart' ),
				'save_failed'  => __( 'Could not save. Retry', 'wp-easycart' ),
				'all_saved'    => __( 'All changes saved', 'wp-easycart' ),
				'unsaved_one'  => __( '1 unsaved change', 'wp-easycart' ),
				'unsaved_many' => __( '%d unsaved changes', 'wp-easycart' ),
				'leave'        => __( 'You have unsaved changes.', 'wp-easycart' ),
				'show_adv'     => __( 'Show %d advanced settings', 'wp-easycart' ),
				'show_adv_one' => __( 'Show 1 advanced setting', 'wp-easycart' ),
				'hide_adv'     => __( 'Hide advanced settings', 'wp-easycart' ),
				'no_match'     => __( 'No settings match. Try a different word, or search all settings from the Settings home.', 'wp-easycart' ),
				'match_count'  => __( '%1$d of %2$d settings', 'wp-easycart' ),
				'searching'    => __( 'Searching…', 'wp-easycart' ),
				'no_results'   => __( 'Nothing found. Try another word, such as a gateway, carrier or page name.', 'wp-easycart' ),
				'locked'       => self::locked_text( array( 'pro' => true ) ),
				'on'           => __( 'On', 'wp-easycart' ),
				'off'          => __( 'Off', 'wp-easycart' ),
				'picker_count' => __( '%1$d of %2$d selected', 'wp-easycart' ),
				'picker_none'  => __( 'None selected', 'wp-easycart' ),
				'picker_n'     => __( '%d selected', 'wp-easycart' ),
				'picker_more'  => __( 'Showing the first %d matches. Keep typing to narrow the list.', 'wp-easycart' ),
				'remove'       => __( 'Remove %s', 'wp-easycart' ),
				'working'      => __( 'Working…', 'wp-easycart' ),
				'req_failed'   => __( 'The request failed. Reload the page and try again.', 'wp-easycart' ),
				'confirm'      => __( 'Confirm', 'wp-easycart' ),
				/* translators: %d: number of store pages with a problem */
				'banner_title' => __( '%d store page needs attention', 'wp-easycart' ),
				/* translators: %d: number of store pages with a problem */
				'banner_title_many' => __( '%d store pages need attention', 'wp-easycart' ),
				'banner_sub'   => __( 'Shoppers cannot reach the store, cart or account until every page below is published and carries its shortcode.', 'wp-easycart' ),
				'fix'          => __( 'Fix', 'wp-easycart' ),
				'edit_page'    => __( 'Edit page', 'wp-easycart' ),
				'pick_page'    => __( 'Search pages and posts…', 'wp-easycart' ),
			),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Store page health ( store / cart / account pages )                  */
	/* ------------------------------------------------------------------ */

	/**
	 * The three pages the store runs on and the shortcode each must carry.
	 *
	 * @since 6.0.0
	 * @return array option key => array( 'label', 'shortcode', 'what' )
	 */
	public static function store_pages() {
		return array(
			'ec_option_storepage'   => array( 'label' => __( 'Store page', 'wp-easycart' ), 'shortcode' => '[ec_store]', 'what' => __( 'the product catalog', 'wp-easycart' ) ),
			'ec_option_cartpage'    => array( 'label' => __( 'Cart and checkout page', 'wp-easycart' ), 'shortcode' => '[ec_cart]', 'what' => __( 'the cart and checkout', 'wp-easycart' ) ),
			'ec_option_accountpage' => array( 'label' => __( 'Account page', 'wp-easycart' ), 'shortcode' => '[ec_account]', 'what' => __( 'the customer account', 'wp-easycart' ) ),
		);
	}

	/**
	 * Does this content carry the shortcode? Same rule as wp_easycart_check_for_shortcode()
	 * on the storefront ( a case-insensitive match anywhere in post_content, so a shortcode
	 * block or attributes such as [ec_store groups="1"] count ), tightened so [ec_account]
	 * is not satisfied by [ec_account_login] and [ec_cart] not by [ec_cart_icon].
	 *
	 * @since 6.0.0
	 */
	public static function has_shortcode( $content, $shortcode ) {
		$tag = preg_quote( rtrim( (string) $shortcode, ']' ), '/' );
		return (bool) preg_match( '/' . $tag . '(\s|\]|\/)/i', (string) $content );
	}

	/**
	 * Does the page deliver the store / cart / account, whichever way it was built?
	 *
	 * The shortcode in the content is the classic way. Pages built with the block editor can use the
	 * EasyCart "store" block instead of shortcode text, and pages built with Elementor keep their layout
	 * in the _elementor_data meta ( the EasyCart store / account widgets, or Elementor's shortcode widget
	 * holding the shortcode ), with an empty post_content. All of those count; only a page with none of
	 * them is a real problem.
	 *
	 * @since 6.0.0
	 * @param WP_Post $post      The page.
	 * @param string  $key       Option key ( ec_option_storepage, ec_option_cartpage, ec_option_accountpage ).
	 * @param string  $shortcode e.g. [ec_store].
	 * @return bool
	 */
	public static function page_has_store_content( $post, $key, $shortcode ) {
		$content = (string) $post->post_content;
		if ( self::has_shortcode( $content, $shortcode ) ) {
			return true;
		}
		/* Block editor: the EasyCart store block renders the store without shortcode text. */
		if ( 'ec_option_storepage' === $key && false !== stripos( $content, 'wp:wp-easycart/storecat' ) ) {
			return true;
		}
		/* Elementor: the layout lives in meta. One meta read, only for pages that have it. */
		$elementor = get_post_meta( $post->ID, '_elementor_data', true );
		if ( is_string( $elementor ) && '' !== $elementor ) {
			if ( self::has_shortcode( wp_unslash( $elementor ), $shortcode ) ) {
				return true; /* Elementor's shortcode widget holding the shortcode */
			}
			$widgets = array(
				'ec_option_storepage'   => array( 'wp_easycart_store' ),
				'ec_option_accountpage' => array( 'wp_easycart_account' ),
				'ec_option_cartpage'    => array(),
			);
			foreach ( isset( $widgets[ $key ] ) ? $widgets[ $key ] : array() as $widget ) {
				if ( false !== stripos( $elementor, '"widgetType":"' . $widget ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * What is wrong with one store page, or false when it is fine. One get_post() per
	 * page id ( served by the object cache afterwards ); the answer is kept for the
	 * request so the row, the banner and the notice never repeat the lookup.
	 *
	 * @since 6.0.0
	 * @param string $key Option key ( ec_option_storepage, ec_option_cartpage, ec_option_accountpage ).
	 * @param mixed  $id  The page id to check ( the stored option, or a value about to be stored ).
	 * @return array|false array( key, label, shortcode, id, title, status, text, edit_url )
	 */
	public static function store_page_problem( $key, $id ) {
		static $seen = array();
		$pages = self::store_pages();
		if ( ! isset( $pages[ $key ] ) ) {
			return false;
		}
		$id        = (int) $id;
		$cache_key = $key . ':' . $id;
		if ( isset( $seen[ $cache_key ] ) ) {
			return $seen[ $cache_key ];
		}
		$spec   = $pages[ $key ];
		$status = '';
		$text   = '';
		$title  = '';
		$edit   = '';
		if ( $id <= 0 ) {
			$status = 'none';
			/* translators: %s: shortcode such as [ec_store] */
			$text = sprintf( __( 'No page is selected. Choose a page that contains %s or create one.', 'wp-easycart' ), $spec['shortcode'] );
		} else {
			$post = get_post( $id );
			if ( ! $post || 'trash' === $post->post_status ) {
				$status = 'missing';
				/* translators: %d: page id */
				$text = sprintf( __( 'The selected page (#%d) no longer exists. Choose another or create one.', 'wp-easycart' ), $id );
			} else {
				$title = trim( (string) get_the_title( $post ) );
				if ( '' === $title ) {
					/* translators: %d: page id */
					$title = sprintf( __( '(no title) #%d', 'wp-easycart' ), $id );
				}
				if ( current_user_can( 'edit_post', $id ) ) {
					$edit = admin_url( 'post.php?post=' . $id . '&action=edit' );
				}
				if ( 'publish' !== $post->post_status ) {
					$status = 'unpublished';
					/* translators: 1: page title, 2: post status such as draft */
					$text = sprintf( __( '“%1$s” is not published (status: %2$s), so shoppers cannot open it.', 'wp-easycart' ), $title, $post->post_status );
				} elseif ( ! self::page_has_store_content( $post, $key, $spec['shortcode'] ) ) {
					$status = 'no_shortcode';
					/* translators: 1: page title, 2: shortcode such as [ec_store], 3: what the shortcode shows */
					$text = sprintf( __( '“%1$s” does not contain the %2$s shortcode, so it will not show %3$s. Add the shortcode to the page or pick a different one.', 'wp-easycart' ), $title, $spec['shortcode'], $spec['what'] );
				}
			}
		}
		$seen[ $cache_key ] = ( '' === $status ) ? false : array(
			'key'       => $key,
			'label'     => $spec['label'],
			'shortcode' => $spec['shortcode'],
			'id'        => $id,
			'title'     => $title,
			'status'    => $status,
			'text'      => $text,
			'edit_url'  => $edit,
		);
		return $seen[ $cache_key ];
	}

	/**
	 * Every store page with a problem, keyed by option, from the stored options.
	 * Computed once per request; pass $refresh after an option changed.
	 *
	 * @since 6.0.0
	 */
	public static function store_pages_health( $refresh = false ) {
		static $cache = null;
		if ( null !== $cache && ! $refresh ) {
			return $cache;
		}
		$cache = array();
		foreach ( array_keys( self::store_pages() ) as $key ) {
			$problem = self::store_page_problem( $key, get_option( $key ) );
			if ( $problem ) {
				$cache[ $key ] = $problem;
			}
		}
		return $cache;
	}

	/**
	 * Problems a page reports through its 'health' callable ( page => list of
	 * array( key, label, text, edit_url ) ), keyed by field key. Empty for pages without one.
	 *
	 * @since 6.0.0
	 */
	public static function page_problems( $page ) {
		$out = array();
		if ( empty( $page['health'] ) || ! is_callable( $page['health'] ) ) {
			return $out;
		}
		$list = call_user_func( $page['health'], $page );
		foreach ( (array) $list as $problem ) {
			if ( ! is_array( $problem ) || empty( $problem['key'] ) ) {
				continue;
			}
			$out[ $problem['key'] ] = wp_parse_args( $problem, array( 'label' => $problem['key'], 'text' => '', 'edit_url' => '', 'fix_url' => '' ) );
		}
		return $out;
	}

	/**
	 * The red banner listing broken store pages, printed at the top of the Store details
	 * page ( "Fix" scrolls to the row ) and on the Settings home ( "Fix" opens the row ).
	 * Prints an empty, hidden shell on the page so settings-page-v2.js can fill it after a save.
	 *
	 * @since 6.0.0
	 * @param array  $problems List of problems ( see page_problems() ).
	 * @param string $context  'page' or 'home'.
	 */
	public static function print_store_pages_banner( $problems, $context = 'page' ) {
		$problems = array_values( (array) $problems );
		$n        = count( $problems );
		if ( 0 === $n && 'page' !== $context ) {
			return;
		}
		?>
		<div class="ecst-banner is-error" id="ecst_banner" role="alert"<?php echo $n ? '' : ' hidden'; ?>>
			<span class="ecst-banner-ic dashicons dashicons-warning" aria-hidden="true"></span>
			<div class="ecst-banner-body">
				<b class="ecst-banner-title"><?php
					/* translators: %d: number of store pages with a problem */
					echo esc_html( sprintf( _n( '%d store page needs attention', '%d store pages need attention', $n, 'wp-easycart' ), $n ) );
				?></b>
				<span class="ecst-banner-sub"><?php esc_html_e( 'Shoppers cannot reach the store, cart or account until every page below is published and carries its shortcode.', 'wp-easycart' ); ?></span>
				<ul class="ecst-banner-list">
					<?php foreach ( $problems as $problem ) : ?>
						<li>
							<b><?php echo esc_html( $problem['label'] ); ?></b>
							<span><?php echo esc_html( $problem['text'] ); ?></span>
							<?php if ( 'home' === $context ) : ?>
								<a class="ecst-banner-fix" href="<?php echo esc_url( wp_easycart_admin_settings_registry::page_url( 'initial-setup', $problem['key'] ) ); ?>"><?php esc_html_e( 'Fix', 'wp-easycart' ); ?> →</a>
							<?php else : ?>
								<a class="ecst-banner-fix" href="#ecst-<?php echo esc_attr( $problem['key'] ); ?>" data-key="<?php echo esc_attr( $problem['key'] ); ?>"><?php esc_html_e( 'Fix', 'wp-easycart' ); ?> →</a>
							<?php endif; ?>
							<?php if ( ! empty( $problem['edit_url'] ) ) : ?>
								<a class="ecst-banner-edit" href="<?php echo esc_url( $problem['edit_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Edit page', 'wp-easycart' ); ?> ↗</a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Admin notice for every EasyCart admin screen ( fired from wp_easycart_admin_messages,
	 * the in-shell notice position ): which store page is broken, with a link to Store details.
	 * Administrators only; dismissible per broken page per user for a day. Skipped on the
	 * Store details page and the Settings home, which print the full banner instead.
	 *
	 * @since 6.0.0
	 */
	public static function print_store_pages_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_option( 'ec_option_setup_wizard_done' ) ) {
			return;
		}
		if ( in_array( self::current_slug(), array( 'home', 'initial-setup' ), true ) ) {
			return;
		}
		$problems = self::store_pages_health();
		if ( empty( $problems ) ) {
			return;
		}
		$dismissed = get_transient( self::STORE_NOTICE_TRANSIENT . get_current_user_id() );
		$dismissed = is_array( $dismissed ) ? $dismissed : array();
		$pending   = array();
		foreach ( $problems as $problem ) {
			$sig = $problem['key'] . ':' . (int) $problem['id'];
			if ( ! in_array( $sig, $dismissed, true ) ) {
				$pending[ $sig ] = $problem;
			}
		}
		if ( empty( $pending ) ) {
			return;
		}
		$url = wp_easycart_admin_settings_registry::page_url( 'initial-setup', key( array_slice( $problems, 0, 1, true ) ) );
		?>
		<div id="ec_store_pages_notice" class="wpec-pro-notice wpec-pro-notice--danger wpec-pro-notice--stacked" data-sigs="<?php echo esc_attr( implode( ',', array_keys( $pending ) ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( wp_easycart_admin_settings_registry::NONCE ) ); ?>">
			<span class="wpec-pro-notice-icon dashicons dashicons-warning"></span>
			<div class="wpec-pro-notice-body">
				<p><strong><?php
					/* translators: %d: number of store pages with a problem */
					echo esc_html( sprintf( _n( '%d store page needs attention: shoppers cannot use your store until it is fixed.', '%d store pages need attention: shoppers cannot use your store until they are fixed.', count( $pending ), 'wp-easycart' ), count( $pending ) ) );
				?></strong></p>
				<ul>
					<?php foreach ( $pending as $problem ) : ?>
						<li><b><?php echo esc_html( $problem['label'] ); ?>:</b> <?php echo esc_html( $problem['text'] ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p><button type="button" id="ec_store_pages_notice_dismiss" style="border:0;background:none;padding:0;margin:0;cursor:pointer;font-size:12px;color:#646970;text-decoration:underline;"><?php esc_html_e( 'Dismiss for today', 'wp-easycart' ); ?></button></p>
			</div>
			<a class="wpec-pro-notice-button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open Store details', 'wp-easycart' ); ?></a>
		</div>
		<script>
		( function() {
			var btn = document.getElementById( 'ec_store_pages_notice_dismiss' ), box = document.getElementById( 'ec_store_pages_notice' );
			if ( ! btn || ! box || ! window.jQuery ) { return; }
			btn.addEventListener( 'click', function() {
				box.style.display = 'none';
				window.jQuery.post( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { action: 'ecv2_settings_dismiss_store_notice', nonce: box.getAttribute( 'data-nonce' ), sigs: box.getAttribute( 'data-sigs' ) } );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * The settings search box: every declared setting, section, page ( classic ones too ) and the
	 * items other modules add through wp_easycart_settings_search_items ( payment gateways … ).
	 * Shown on the Settings home, in the header of every declared page and above classic settings pages.
	 *
	 * @since 6.0.0
	 * @param string $variant 'large' ( home ) or 'compact' ( page headers ).
	 * @param string $current Slug of the page being viewed, so results on it scroll instead of reloading.
	 */
	public static function print_search( $variant = 'compact', $current = '' ) {
		static $n = 0;
		$n++;
		$menu_id = 'ecst_search_menu_' . $n;
		?>
		<div class="ecst-search<?php echo 'large' === $variant ? ' is-large' : ' is-compact'; ?>" data-ecst-search data-current="<?php echo esc_attr( $current ); ?>">
			<span class="dashicons dashicons-search" aria-hidden="true"></span>
			<input type="search" class="ecst-search-input" placeholder="<?php echo esc_attr( 'large' === $variant ? __( 'Search all settings, gateways and pages…', 'wp-easycart' ) : __( 'Search all settings…', 'wp-easycart' ) ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Search all settings', 'wp-easycart' ); ?>" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr( $menu_id ); ?>" />
			<kbd aria-hidden="true">/</kbd>
			<div class="ecst-search-menu" id="<?php echo esc_attr( $menu_id ); ?>" role="listbox" hidden></div>
		</div>
		<?php
	}

	/** Render a declared page. $slug must exist in the registry. */
	public static function render( $slug ) {
		$page = wp_easycart_admin_settings_registry::page( $slug );
		if ( ! $page ) {
			echo '<div class="ecv2-wrap"><p>' . esc_html__( 'This settings page is not available.', 'wp-easycart' ) . '</p></div>';
			return;
		}
		self::enqueue();
		$docs = '';
		if ( ! empty( $page['docs'] ) && is_array( $page['docs'] ) && isset( wp_easycart_admin()->helpsystem ) ) {
			$docs = call_user_func_array( array( wp_easycart_admin()->helpsystem, 'print_docs_url' ), $page['docs'] );
		}
		$highlight = isset( $_GET['highlight'] ) ? sanitize_key( wp_unslash( $_GET['highlight'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only deep link into the page.
		$page_locked = wp_easycart_admin_settings_registry::is_locked( $page );
		/* Problems the page reports on every render ( e.g. a store page without its shortcode ): a red banner up top and a red note under each affected row. */
		self::$problems = self::page_problems( $page );
		$has_health     = ! empty( $page['health'] ) && is_callable( $page['health'] );
		?>
		<div class="ecv2-wrap ecst-wrap" id="ecst" data-page="<?php echo esc_attr( $page['slug'] ); ?>" data-highlight="<?php echo esc_attr( $highlight ); ?>" data-upsell="<?php echo esc_attr( $page['upsell'] ); ?>"<?php echo $has_health ? ' data-health="1"' : ''; ?>>
			<div class="ecv2-page-header ecst-header">
				<div class="ecv2-page-header-left">
					<h1 class="ecv2-page-title"><?php echo esc_html( $page['title'] ); ?></h1>
					<?php if ( '' !== $page['description'] || '' !== $docs ) : ?>
						<div class="ecst-desc"><?php echo esc_html( $page['description'] ); ?><?php if ( '' !== $docs ) : ?> <a href="<?php echo esc_url( $docs ); ?>" target="_blank" rel="noopener noreferrer" class="ecst-docs"><?php esc_html_e( 'Docs', 'wp-easycart' ); ?> ↗</a><?php endif; ?></div>
					<?php endif; ?>
				</div>
				<div class="ecv2-page-header-right ecst-header-right">
					<?php self::print_search( 'compact', $page['slug'] ); ?>
					<span class="ecv2-chip ecv2-chip-green ecst-status" id="ecst_status"><?php esc_html_e( 'All changes saved', 'wp-easycart' ); ?></span>
				</div>
			</div>

			<?php if ( $has_health ) { self::print_store_pages_banner( self::$problems, 'page' ); } ?>

			<?php do_action( 'wp_easycart_settings_page_before', $page ); ?>

			<div class="ecst-body">
				<nav class="ecst-secnav" id="ecst_secnav" aria-label="<?php esc_attr_e( 'Sections', 'wp-easycart' ); ?>">
					<?php foreach ( $page['sections'] as $section ) : ?>
						<?php $glyph = wp_easycart_admin_settings_icons::for_section( $page, $section, 'ecst-ic-svg' ); ?>
						<a href="#ecst-sec-<?php echo esc_attr( $section['slug'] ); ?>" data-sec="<?php echo esc_attr( $section['slug'] ); ?>">
							<span class="ecst-secnav-ic<?php echo 'mark' === $glyph['type'] ? ' is-mark' : ''; ?>"><?php echo $glyph['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG / escaped lettermark from wp_easycart_admin_settings_icons. ?></span>
							<span class="ecst-secnav-label"><?php echo esc_html( $section['title'] ); ?></span>
							<?php $nav_lock = self::section_lock( $section ); ?>
							<?php if ( $nav_lock ) : ?><span class="ecst-secnav-lock" title="<?php echo esc_attr( self::locked_text( $nav_lock ) ); ?>"><?php echo self::lock_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span class="screen-reader-text"><?php echo esc_html( self::locked_badge( $nav_lock ) ); ?></span></span><?php endif; ?>
							<?php if ( $section['count'] > 0 ) : ?><span class="ecst-secnav-n"><?php echo (int) $section['count']; ?></span><?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="ecst-content">
					<?php foreach ( $page['sections'] as $section ) : ?>
						<?php self::render_section( $page, $section, $page_locked ); ?>
					<?php endforeach; ?>
					<?php do_action( 'wp_easycart_settings_page_after_sections', $page ); ?>
				</div>
			</div>

			<div class="ecst-savebar" id="ecst_savebar" hidden>
				<b id="ecst_savebar_count"></b>
				<span class="ecst-savebar-names" id="ecst_savebar_names"></span>
				<span class="ecst-grow"></span>
				<button type="button" class="ecv2-btn ecst-btn-ghost" id="ecst_discard"><?php esc_html_e( 'Discard', 'wp-easycart' ); ?></button>
				<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecst_save"><?php esc_html_e( 'Save changes', 'wp-easycart' ); ?></button>
			</div>
		</div>
		<?php
	}

	private static function render_section( $page, $section, $page_locked ) {
		$section_locked = $page_locked || wp_easycart_admin_settings_registry::is_locked( $section );
		/* The fold counts and names only the advanced settings that appear when it opens: not rows hidden
		 * because their parent is off, and not rows nested under another advanced row ( they show inside that
		 * row's group ). settings-page-v2.js keeps the count current as toggles change. */
		$advanced_all   = array();
		$advanced_names = array();
		foreach ( $section['fields'] as $field ) {
			if ( $field['advanced'] && 'html' !== $field['type'] ) {
				$advanced_all[ $field['key'] ] = true;
			}
		}
		foreach ( $section['fields'] as $field ) {
			if ( ! isset( $advanced_all[ $field['key'] ] ) ) {
				continue;
			}
			if ( '' !== $field['parent'] && isset( $advanced_all[ $field['parent'] ] ) ) {
				continue;
			}
			if ( self::is_hidden_by_parents( $page, $field ) ) {
				continue;
			}
			$advanced_names[] = $field['label'];
		}
		$advanced = count( $advanced_names );
		?>
		<section class="ecdv2-card ecst-section<?php echo $section_locked ? ' is-locked' : ''; ?>" id="ecst-sec-<?php echo esc_attr( $section['slug'] ); ?>" data-sec="<?php echo esc_attr( $section['slug'] ); ?>">
			<?php $glyph = wp_easycart_admin_settings_icons::for_section( $page, $section, 'ecst-ic-svg' ); ?>
			<div class="ecdv2-card-header ecst-section-head">
				<span class="ecst-sec-ic<?php echo 'mark' === $glyph['type'] ? ' is-mark' : ''; ?>"><?php echo $glyph['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG / escaped lettermark from wp_easycart_admin_settings_icons. ?></span>
				<div class="ecst-sec-titles">
					<h3 class="ecdv2-card-title"><?php echo esc_html( $section['title'] ); ?><?php $head_lock = $section['pro'] ? $section : self::section_lock( $section ); ?><?php if ( $head_lock ) : ?> <span class="ecst-pro-chip"><?php echo esc_html( self::locked_badge( $head_lock ) ); ?></span><?php endif; ?></h3>
					<?php if ( '' !== $section['hint'] ) : ?><span class="ecdv2-card-hint"><?php echo esc_html( $section['hint'] ); ?></span><?php endif; ?>
				</div>
				<span class="ecst-grow"></span>
				<?php if ( $section_locked ) : ?>
					<button type="button" class="ecst-link" onclick="ecst.upsell( '<?php echo esc_js( $section['slug'] ); ?>' ); return false;"><?php esc_html_e( 'See what’s included →', 'wp-easycart' ); ?></button>
				<?php endif; ?>
			</div>
			<div class="ecst-rows">
				<?php
				/* A top-level row and the rows that depend on it ( children, grandchildren ) share one .ecst-group,
				 * so the group can be drawn as a unit while its children are showing. */
				$group_keys = array();
				foreach ( $section['fields'] as $field ) {
					$joins = ( '' !== $field['parent'] && isset( $group_keys[ $field['parent'] ] ) );
					if ( ! $joins ) {
						if ( $group_keys ) {
							echo '</div>';
						}
						echo '<div class="ecst-group">';
						$group_keys = array();
					}
					$group_keys[ $field['key'] ] = true;
					self::render_row( $page, $section, $field, $section_locked );
				}
				if ( $group_keys ) {
					echo '</div>';
				}
				?>
			</div>
			<?php if ( $advanced_all ) : ?>
				<div class="ecst-fold"<?php echo $advanced ? '' : ' hidden'; ?>>
					<button type="button" class="ecst-link ecst-fold-btn" data-count="<?php echo (int) $advanced; ?>" aria-expanded="false"><?php
						/* translators: %d: number of advanced settings */
						echo esc_html( sprintf( _n( 'Show %d advanced setting', 'Show %d advanced settings', $advanced, 'wp-easycart' ), $advanced ) );
					?></button>
					<span class="ecst-fold-names"><?php echo esc_html( implode( ' · ', array_slice( $advanced_names, 0, 4 ) ) . ( count( $advanced_names ) > 4 ? '…' : '' ) ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( is_callable( $section['render'] ) ) : ?>
				<div class="ecst-custom"><?php call_user_func( $section['render'], $page, $section ); ?></div>
			<?php endif; ?>
			<?php foreach ( $section['actions'] as $action ) : ?>
				<?php $action_locked = $section_locked || wp_easycart_admin_settings_registry::is_locked( $action ); ?>
				<div class="ecst-action<?php echo $action['danger'] ? ' is-danger' : ''; ?><?php echo $action_locked ? ' is-locked' : ''; ?>">
					<div class="ecst-action-text"><b><?php echo esc_html( $action['label'] ); ?></b><?php if ( '' !== $action['desc'] ) : ?><span><?php echo esc_html( $action['desc'] ); ?></span><?php endif; ?></div>
					<?php if ( $action_locked ) : ?>
						<button type="button" class="ecst-pro-btn" onclick="ecst.upsell( '<?php echo esc_js( $action['id'] ); ?>' ); return false;" title="<?php echo esc_attr( self::locked_text( $action['pro'] ? $action : $section ) ); ?>">🔒 <?php echo esc_html( self::locked_badge( $action['pro'] ? $action : $section ) ); ?></button>
					<?php else : ?>
						<?php
						/* 'confirm' alone is the dialog title ( classic ). With 'confirm_title' it becomes the one-line body and
						 * 'confirm_button' names the primary button; settings-page-v2.js opens the V2 confirm dialog with them. */
						$confirm_title  = isset( $action['confirm_title'] ) ? (string) $action['confirm_title'] : '';
						$confirm_button = isset( $action['confirm_button'] ) ? (string) $action['confirm_button'] : '';
						?>
						<button type="button" class="ecv2-btn<?php echo $action['danger'] ? ' ecst-btn-danger' : ''; ?>" data-action="<?php echo esc_attr( $action['id'] ); ?>" data-sec="<?php echo esc_attr( $section['slug'] ); ?>" data-confirm="<?php echo esc_attr( $action['confirm'] ); ?>"<?php if ( '' !== $confirm_title ) : ?> data-confirm-title="<?php echo esc_attr( $confirm_title ); ?>"<?php endif; ?><?php if ( '' !== $confirm_button ) : ?> data-confirm-ok="<?php echo esc_attr( $confirm_button ); ?>"<?php endif; ?>><?php echo esc_html( '' !== $action['button'] ? $action['button'] : $action['label'] ); ?></button>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
	}

	private static function render_row( $page, $section, $field, $section_locked ) {
		$key    = $field['key'];
		$locked = $section_locked || wp_easycart_admin_settings_registry::is_locked( $field );
		$value  = wp_easycart_admin_settings_registry::value( $field );
		$mode   = wp_easycart_admin_settings_registry::save_mode( $field );
		$hidden = self::is_hidden_by_parents( $page, $field );
		$classes = array( 'ecst-row', 'ecst-type-' . $field['type'] );
		if ( 'toggle' === $field['type'] ) { $classes[] = $value ? 'is-on' : 'is-off'; }
		if ( $locked ) { $classes[] = 'is-locked'; }
		if ( $field['advanced'] ) { $classes[] = 'is-advanced'; }
		if ( '' !== $field['parent'] ) { $classes[] = 'is-child'; }
		if ( ! empty( $field['attach'] ) ) { $classes[] = 'has-attach'; }
		$problem = isset( self::$problems[ $key ] ) ? self::$problems[ $key ] : false;
		if ( $problem ) { $classes[] = 'has-error'; }
		$search_text = strtolower( $field['label'] . ' ' . $field['desc'] . ' ' . implode( ' ', $field['keywords'] ) . ' ' . $key );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="ecst-<?php echo esc_attr( $key ); ?>" data-key="<?php echo esc_attr( $key ); ?>" data-type="<?php echo esc_attr( $field['type'] ); ?>" data-mode="<?php echo esc_attr( $mode ); ?>" data-label="<?php echo esc_attr( $field['label'] ); ?>" data-search="<?php echo esc_attr( $search_text ); ?>"<?php if ( '' !== $field['parent'] ) : ?> data-parent="<?php echo esc_attr( $field['parent'] ); ?>" data-show-when="<?php echo esc_attr( implode( '|', (array) $field['show_when'] ) ); ?>"<?php endif; ?><?php if ( $hidden ) : ?> hidden<?php endif; ?>>
			<?php if ( 'html' === $field['type'] ) : ?>
				<div class="ecst-row-html"><?php if ( is_callable( $field['render'] ) ) { call_user_func( $field['render'], $field, $page ); } ?></div>
			<?php else : ?>
				<?php if ( 'toggle' === $field['type'] ) : ?><span class="ecst-bar" aria-hidden="true"></span><?php endif; ?>
				<div class="ecst-row-text">
					<label class="ecst-label" for="ecst_f_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?><?php if ( '' !== $field['help'] ) : ?> <span class="ecst-help" title="<?php echo esc_attr( $field['help'] ); ?>" tabindex="0">ⓘ</span><?php endif; ?></label>
					<?php if ( '' !== $field['desc'] ) : ?><span class="ecst-row-desc"><?php echo esc_html( $field['desc'] ); ?></span><?php endif; ?>
					<span class="ecst-row-msg<?php echo $problem ? ' is-error' : ''; ?>" id="ecst_msg_<?php echo esc_attr( $key ); ?>"<?php echo $problem ? '' : ' hidden'; ?>><?php if ( $problem ) { echo esc_html( $problem['text'] ); } ?></span>
				</div>
				<div class="ecst-row-control">
					<span class="ecst-state" id="ecst_state_<?php echo esc_attr( $key ); ?>"></span>
					<?php if ( 'toggle' === $field['type'] && ! $locked ) : ?><span class="ecst-onoff"><?php echo esc_html( $value ? __( 'On', 'wp-easycart' ) : __( 'Off', 'wp-easycart' ) ); ?></span><?php endif; ?>
					<?php if ( $locked ) : ?>
						<button type="button" class="ecst-pro-btn" onclick="ecst.upsell( '<?php echo esc_js( $key ); ?>' ); return false;" title="<?php echo esc_attr( self::locked_text( $field ) ); ?>">🔒 <?php echo esc_html( self::locked_badge( $field ) ); ?></button>
						<?php if ( 'toggle' === $field['type'] ) : ?><span class="ecst-toggle is-disabled" aria-hidden="true"></span><?php endif; ?>
					<?php else : ?>
						<?php self::render_control( $field, $value ); ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * A row is hidden when its parent's value does not match show_when, or when
	 * any ancestor is hidden ( a grandchild must not show while the grandparent
	 * is off ). Walks up at most 8 levels to be safe against cycles.
	 */
	private static function is_hidden_by_parents( $page, $field ) {
		$depth = 0;
		while ( '' !== $field['parent'] && $depth < 8 ) {
			$parent = wp_easycart_admin_settings_registry::field( $page['slug'], $field['parent'] );
			if ( ! $parent ) {
				return false;
			}
			$parent_value = (string) wp_easycart_admin_settings_registry::value( $parent );
			if ( ! in_array( $parent_value, array_map( 'strval', (array) $field['show_when'] ), true ) ) {
				return true;
			}
			$field = $parent;
			$depth++;
		}
		return false;
	}

	/** One removable chip in a picker box. */
	private static function print_chip( $value, $label, $hint = '' ) {
		?>
		<span class="ecst-chip" data-value="<?php echo esc_attr( $value ); ?>"><span class="ecst-chip-name"><?php echo esc_html( $label ); ?></span><?php if ( '' !== $hint ) : ?><span class="ecst-chip-hint"><?php echo esc_html( $hint ); ?></span><?php endif; ?><button type="button" class="ecst-chip-x" aria-label="<?php /* translators: %s: option name */ echo esc_attr( sprintf( __( 'Remove %s', 'wp-easycart' ), $label ) ); ?>">×</button></span>
		<?php
	}

	/**
	 * Search-as-you-type picker for lists too long to print ( thousands of categories,
	 * every WordPress page ): only the chosen values are rendered as chips; typing asks
	 * ecv2_settings_option_search for matches. $max = 1 makes it a single-value control
	 * ( a 'select' with a 'search_callback' ). The hidden .ecst-input carries the joined
	 * value like every other picker, so batch save, autosave and discard are unchanged.
	 *
	 * @since 6.0.0
	 */
	private static function render_remote_picker( $field, $chosen, $sep, $max = 0 ) {
		$key     = $field['key'];
		$menu_id = 'ecst_pm_' . $key;
		$known   = wp_easycart_admin_settings_registry::labels( $field, $chosen );
		$placeholder = '' !== $field['placeholder'] ? $field['placeholder'] : __( 'Type to search…', 'wp-easycart' );
		?>
		<div class="ecst-picker is-remote<?php echo 1 === $max ? ' is-single' : ''; ?>" data-sep="<?php echo esc_attr( $sep ); ?>" data-exclusive="<?php echo esc_attr( implode( '|', (array) $field['exclusive'] ) ); ?>" data-max="<?php echo (int) $max; ?>" data-limit="<?php echo (int) wp_easycart_admin_settings_registry::SEARCH_LIMIT; ?>">
			<input type="hidden" class="ecst-input" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( implode( $sep, array_keys( $known ) ) ); ?>" />
			<div class="ecst-picker-box">
				<span class="ecst-picker-chips">
					<?php foreach ( $known as $row ) { self::print_chip( $row['value'], $row['label'], $row['hint'] ); } ?>
				</span>
				<input type="text" id="ecst_f_<?php echo esc_attr( $key ); ?>" class="ecst-picker-search" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr( $menu_id ); ?>" />
			</div>
			<ul class="ecst-picker-menu" id="<?php echo esc_attr( $menu_id ); ?>" role="listbox"<?php echo 1 === $max ? '' : ' aria-multiselectable="true"'; ?> aria-label="<?php echo esc_attr( $field['label'] ); ?>" hidden>
				<li class="ecst-picker-status" role="presentation" hidden><?php esc_html_e( 'Searching…', 'wp-easycart' ); ?></li>
				<li class="ecst-picker-empty" role="presentation" hidden><?php esc_html_e( 'No matches', 'wp-easycart' ); ?></li>
			</ul>
			<?php if ( 1 !== $max ) : ?>
				<div class="ecst-picker-foot">
					<span class="ecst-picker-count" data-total="0"><?php
						/* translators: %d: number chosen */
						echo esc_html( $known ? sprintf( __( '%d selected', 'wp-easycart' ), count( $known ) ) : __( 'None selected', 'wp-easycart' ) );
					?></span>
					<button type="button" class="ecst-link ecst-picker-clear"<?php echo $known ? '' : ' hidden'; ?>><?php esc_html_e( 'Clear all', 'wp-easycart' ); ?></button>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Long multiselect: chosen values as removable chips, plus a search box that
	 * filters a listbox of every option. The hidden .ecst-input carries the joined
	 * value exactly like the pill version, so saving and discard are unchanged.
	 *
	 * @since 6.0.0
	 */
	private static function render_picker( $field, $chosen, $sep, $all ) {
		$key     = $field['key'];
		$menu_id = 'ecst_pm_' . $key;
		$hints   = wp_easycart_admin_settings_registry::option_hints( $field );
		$counts  = array_count_values( array_map( 'strtolower', array_map( 'strval', array_values( $all ) ) ) );
		$options = array();
		foreach ( $all as $opt_value => $opt_label ) {
			$opt_value = (string) $opt_value;
			$hint      = isset( $hints[ $opt_value ] ) ? (string) $hints[ $opt_value ] : '';
			if ( '' === $hint && $counts[ strtolower( (string) $opt_label ) ] > 1 ) {
				/* translators: %s: option id, shown to tell apart options that share a name */
				$hint = sprintf( __( 'ID %s', 'wp-easycart' ), $opt_value );
			}
			$options[] = array(
				'value' => $opt_value,
				'label' => (string) $opt_label,
				'hint'  => $hint,
				'on'    => in_array( $opt_value, $chosen, true ),
			);
		}
		$chosen_count = count( wp_list_filter( $options, array( 'on' => true ) ) );
		$placeholder  = '' !== $field['placeholder'] ? $field['placeholder'] : __( 'Search to add…', 'wp-easycart' );
		?>
		<div class="ecst-picker" data-sep="<?php echo esc_attr( $sep ); ?>" data-exclusive="<?php echo esc_attr( implode( '|', (array) $field['exclusive'] ) ); ?>">
			<input type="hidden" class="ecst-input" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( implode( $sep, wp_list_pluck( wp_list_filter( $options, array( 'on' => true ) ), 'value' ) ) ); ?>" />
			<div class="ecst-picker-box">
				<span class="ecst-picker-chips">
					<?php foreach ( $options as $opt ) { if ( $opt['on'] ) { self::print_chip( $opt['value'], $opt['label'], $opt['hint'] ); } } ?>
				</span>
				<input type="text" id="ecst_f_<?php echo esc_attr( $key ); ?>" class="ecst-picker-search" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr( $menu_id ); ?>" />
			</div>
			<ul class="ecst-picker-menu" id="<?php echo esc_attr( $menu_id ); ?>" role="listbox" aria-multiselectable="true" aria-label="<?php echo esc_attr( $field['label'] ); ?>" hidden>
				<?php foreach ( $options as $i => $opt ) : ?>
					<li class="ecst-picker-opt<?php echo $opt['on'] ? ' is-on' : ''; ?>" id="<?php echo esc_attr( $menu_id . '_' . $i ); ?>" role="option" aria-selected="<?php echo $opt['on'] ? 'true' : 'false'; ?>" data-value="<?php echo esc_attr( $opt['value'] ); ?>" data-search="<?php echo esc_attr( strtolower( $opt['label'] . ' ' . $opt['hint'] ) ); ?>"><span class="ecst-picker-check" aria-hidden="true"></span><span class="ecst-picker-name"><?php echo esc_html( $opt['label'] ); ?></span><?php if ( '' !== $opt['hint'] ) : ?><span class="ecst-picker-hint"><?php echo esc_html( $opt['hint'] ); ?></span><?php endif; ?></li>
				<?php endforeach; ?>
				<li class="ecst-picker-empty" role="presentation" hidden><?php esc_html_e( 'No matches', 'wp-easycart' ); ?></li>
			</ul>
			<div class="ecst-picker-foot">
				<span class="ecst-picker-count" data-total="<?php echo (int) count( $options ); ?>"><?php
					/* translators: 1: number chosen, 2: number available */
					echo esc_html( $chosen_count ? sprintf( __( '%1$d of %2$d selected', 'wp-easycart' ), $chosen_count, count( $options ) ) : __( 'None selected', 'wp-easycart' ) );
				?></span>
				<button type="button" class="ecst-link ecst-picker-clear"<?php echo $chosen_count ? '' : ' hidden'; ?>><?php esc_html_e( 'Clear all', 'wp-easycart' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * List editor for key=value entries ( e.g. EUR=0.92 ): one row per entry with
	 * remove buttons and an add button. The hidden .ecst-input carries the joined
	 * string, so saving, validation and discard work like a text field.
	 *
	 * @since 6.0.0
	 */
	private static function render_pairs( $field, $value ) {
		$key  = $field['key'];
		$pair = $field['pair'];
		$sep  = (string) $field['separator'];
		$rows = array();
		foreach ( preg_split( '/[\r\n' . preg_quote( $sep, '/' ) . ']+/', (string) $value ) as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			$parts  = explode( $pair['join'], $entry, 2 );
			$rows[] = array( trim( $parts[0] ), isset( $parts[1] ) ? trim( $parts[1] ) : '' );
		}
		$print_row = function ( $k, $v ) use ( $pair ) {
			?>
			<li class="ecst-pair">
				<input type="text" class="ecv2-input ecst-pair-k" value="<?php echo esc_attr( $k ); ?>" placeholder="<?php echo esc_attr( $pair['key']['placeholder'] ); ?>" aria-label="<?php echo esc_attr( $pair['key']['label'] ); ?>"<?php if ( $pair['key']['maxlength'] ) : ?> maxlength="<?php echo (int) $pair['key']['maxlength']; ?>"<?php endif; ?><?php if ( $pair['key']['upper'] ) : ?> data-upper="1"<?php endif; ?> autocomplete="off" />
				<span class="ecst-pair-join" aria-hidden="true"><?php echo esc_html( $pair['join'] ); ?></span>
				<input type="text" class="ecv2-input ecst-pair-v" value="<?php echo esc_attr( $v ); ?>" placeholder="<?php echo esc_attr( $pair['value']['placeholder'] ); ?>" aria-label="<?php echo esc_attr( $pair['value']['label'] ); ?>" inputmode="<?php echo esc_attr( $pair['value']['inputmode'] ); ?>" autocomplete="off" />
				<button type="button" class="ecst-pair-x" aria-label="<?php esc_attr_e( 'Remove', 'wp-easycart' ); ?>" title="<?php esc_attr_e( 'Remove', 'wp-easycart' ); ?>">×</button>
			</li>
			<?php
		};
		?>
		<div class="ecst-pairs" data-sep="<?php echo esc_attr( $sep ); ?>" data-join="<?php echo esc_attr( $pair['join'] ); ?>">
			<input type="hidden" class="ecst-input" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" />
			<?php if ( '' !== $pair['key']['label'] || '' !== $pair['value']['label'] ) : ?>
				<div class="ecst-pairs-head" aria-hidden="true"><span><?php echo esc_html( $pair['key']['label'] ); ?></span><span></span><span><?php echo esc_html( $pair['value']['label'] ); ?></span><span></span></div>
			<?php endif; ?>
			<ul class="ecst-pairs-list">
				<?php foreach ( $rows as $row ) { $print_row( $row[0], $row[1] ); } ?>
			</ul>
			<template class="ecst-pair-tpl"><?php $print_row( '', '' ); ?></template>
			<button type="button" class="ecst-link ecst-pairs-add">+ <?php echo esc_html( '' !== $pair['add'] ? $pair['add'] : __( 'Add', 'wp-easycart' ) ); ?></button>
		</div>
		<?php
	}

	private static function render_control( $field, $value ) {
		$key = $field['key'];
		$id  = 'ecst_f_' . $key;
		switch ( $field['type'] ) {
			case 'toggle':
				?>
				<label class="ecst-toggle<?php echo $value ? ' is-on' : ''; ?>" for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" class="ecst-input" data-key="<?php echo esc_attr( $key ); ?>" value="1"<?php checked( (bool) $value ); ?> />
					<span class="ecst-toggle-track"><span class="ecst-toggle-knob"></span></span>
				</label>
				<?php
				break;
			case 'select':
				if ( wp_easycart_admin_settings_registry::is_remote( $field ) ) {
					/* One value found by search ( e.g. a WordPress page ): never prints the list. */
					self::render_remote_picker( $field, array( (string) $value ), ',', 1 );
					break;
				}
				?>
				<select id="<?php echo esc_attr( $id ); ?>" class="ecv2-select ecst-input" data-key="<?php echo esc_attr( $key ); ?>">
					<?php foreach ( wp_easycart_admin_settings_registry::options( $field ) as $opt_value => $opt_label ) : ?>
						<option value="<?php echo esc_attr( $opt_value ); ?>"<?php selected( (string) $value, (string) $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
				break;
			case 'pills':
				?>
				<div class="ecst-pills" role="radiogroup" id="<?php echo esc_attr( $id ); ?>">
					<?php foreach ( wp_easycart_admin_settings_registry::options( $field ) as $opt_value => $opt_label ) : ?>
						<label class="ecst-pill<?php echo ( (string) $value === (string) $opt_value ) ? ' is-on' : ''; ?>"><input type="radio" name="<?php echo esc_attr( $id ); ?>" class="ecst-input" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $opt_value ); ?>"<?php checked( (string) $value, (string) $opt_value ); ?> /><?php echo esc_html( $opt_label ); ?></label>
					<?php endforeach; ?>
				</div>
				<?php
				break;
			case 'multiselect':
				$sep    = ( 'array' === $field['separator'] ) ? ',' : $field['separator'];
				$chosen = is_array( $value ) ? array_map( 'strval', $value ) : array_map( 'trim', explode( $sep, (string) $value ) );
				/* A remote row never resolves its option list; everything else resolves it once here. */
				$all     = wp_easycart_admin_settings_registry::is_remote( $field ) ? array() : wp_easycart_admin_settings_registry::options( $field );
				$display = wp_easycart_admin_settings_registry::multiselect_display( $field, $all );
				if ( 'remote' === $display ) {
					self::render_remote_picker( $field, $chosen, $sep );
					break;
				}
				if ( 'picker' === $display ) {
					self::render_picker( $field, $chosen, $sep, $all );
					break;
				}
				?>
				<div class="ecst-pills ecst-multi" id="<?php echo esc_attr( $id ); ?>" data-sep="<?php echo esc_attr( $sep ); ?>" data-exclusive="<?php echo esc_attr( implode( '|', (array) $field['exclusive'] ) ); ?>">
					<input type="hidden" class="ecst-input" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( implode( $sep, $chosen ) ); ?>" />
					<?php foreach ( $all as $opt_value => $opt_label ) : ?>
						<?php $on = in_array( (string) $opt_value, $chosen, true ); ?>
						<label class="ecst-pill<?php echo $on ? ' is-on' : ''; ?>"><input type="checkbox" class="ecst-multi-opt" value="<?php echo esc_attr( $opt_value ); ?>"<?php checked( $on ); ?> /><?php echo esc_html( $opt_label ); ?></label>
					<?php endforeach; ?>
				</div>
				<?php
				break;
			case 'pairs':
				self::render_pairs( $field, $value );
				break;
			case 'textarea':
				?>
				<textarea id="<?php echo esc_attr( $id ); ?>" class="ecv2-input ecst-input ecst-textarea" data-key="<?php echo esc_attr( $key ); ?>" rows="<?php echo (int) $field['rows']; ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php
				break;
			case 'color':
				?>
				<span class="ecst-color"><input type="color" id="<?php echo esc_attr( $id ); ?>" class="ecst-input ecst-color-pick" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( '' !== (string) $value ? $value : '#ffffff' ); ?>" /><input type="text" class="ecv2-input ecst-color-hex" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="#000000" aria-label="<?php echo esc_attr( $field['label'] ); ?>" /></span>
				<?php
				break;
			case 'number':
				?>
				<span class="ecst-in<?php echo '' !== $field['prefix'] ? ' has-prefix' : ''; ?><?php echo '' !== $field['unit'] ? ' has-unit' : ''; ?>">
					<?php if ( '' !== $field['prefix'] ) : ?><span class="ecst-affix"><?php echo esc_html( $field['prefix'] ); ?></span><?php endif; ?>
					<input type="number" id="<?php echo esc_attr( $id ); ?>" class="ecv2-input ecst-input ecst-number" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"<?php if ( null !== $field['min'] ) : ?> min="<?php echo esc_attr( $field['min'] ); ?>"<?php endif; ?><?php if ( null !== $field['max'] ) : ?> max="<?php echo esc_attr( $field['max'] ); ?>"<?php endif; ?><?php if ( null !== $field['step'] ) : ?> step="<?php echo esc_attr( $field['step'] ); ?>"<?php endif; ?> />
					<?php if ( '' !== $field['unit'] ) : ?><span class="ecst-affix ecst-unit"><?php echo esc_html( $field['unit'] ); ?></span><?php endif; ?>
				</span>
				<?php
				break;
			default: // text, url, email, password
				$input_type = in_array( $field['type'], array( 'url', 'email', 'password' ), true ) ? $field['type'] : 'text';
				?>
				<span class="ecst-in<?php echo '' !== $field['prefix'] ? ' has-prefix' : ''; ?><?php echo '' !== $field['unit'] ? ' has-unit' : ''; ?>">
					<?php if ( '' !== $field['prefix'] ) : ?><span class="ecst-affix"><?php echo esc_html( $field['prefix'] ); ?></span><?php endif; ?>
					<input type="<?php echo esc_attr( $input_type ); ?>" id="<?php echo esc_attr( $id ); ?>" class="ecv2-input ecst-input" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" autocomplete="off"<?php if ( ! empty( $field['suggestions'] ) ) : ?> list="<?php echo esc_attr( $id . '_list' ); ?>"<?php endif; ?> />
					<?php if ( '' !== $field['unit'] ) : ?><span class="ecst-affix ecst-unit"><?php echo esc_html( $field['unit'] ); ?></span><?php endif; ?>
				</span>
				<?php if ( ! empty( $field['suggestions'] ) ) : ?>
					<datalist id="<?php echo esc_attr( $id . '_list' ); ?>">
						<?php foreach ( (array) $field['suggestions'] as $sug_value => $sug_label ) : ?>
							<option value="<?php echo esc_attr( $sug_value ); ?>"><?php echo esc_html( $sug_label ); ?></option>
						<?php endforeach; ?>
					</datalist>
				<?php endif; ?>
				<?php if ( ! empty( $field['attach'] ) && 'page_picker' === $field['attach'] && wp_easycart_admin_settings_registry::is_remote( $field ) ) { self::render_attach_picker( $field, $value ); } ?>
				<?php
		}
	}

	/**
	 * Page picker attached to a URL field ( 'attach' => 'page_picker' with a 'search_callback' ):
	 * the stored value stays the typed URL, and picking a page or post from the search
	 * writes its permalink into the field. The chip names the page the URL currently
	 * resolves to ( 'validate_callback' answers for the stored value ) and disappears as
	 * soon as the URL is edited by hand. Unlike the other pickers there is no hidden
	 * .ecst-input here: the URL input is the field's value.
	 *
	 * @since 6.0.0
	 */
	private static function render_attach_picker( $field, $value ) {
		$key         = $field['key'];
		$menu_id     = 'ecst_pm_' . $key;
		$known       = wp_easycart_admin_settings_registry::labels( $field, array( (string) $value ) );
		$placeholder = ! empty( $field['attach_placeholder'] ) ? $field['attach_placeholder'] : __( 'Search pages and posts…', 'wp-easycart' );
		?>
		<div class="ecst-picker is-remote is-single is-attach" data-sep="," data-exclusive="" data-max="1" data-limit="<?php echo (int) wp_easycart_admin_settings_registry::SEARCH_LIMIT; ?>">
			<div class="ecst-picker-box">
				<span class="ecst-picker-chips">
					<?php foreach ( $known as $row ) { self::print_chip( $row['value'], $row['label'], $row['hint'] ); } ?>
				</span>
				<input type="text" id="<?php echo esc_attr( 'ecst_a_' . $key ); ?>" class="ecst-picker-search" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr( $menu_id ); ?>" aria-label="<?php echo esc_attr( $placeholder ); ?>" />
			</div>
			<ul class="ecst-picker-menu" id="<?php echo esc_attr( $menu_id ); ?>" role="listbox" aria-label="<?php echo esc_attr( $field['label'] ); ?>" hidden>
				<li class="ecst-picker-status" role="presentation" hidden><?php esc_html_e( 'Searching…', 'wp-easycart' ); ?></li>
				<li class="ecst-picker-empty" role="presentation" hidden><?php esc_html_e( 'No matches', 'wp-easycart' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	/** POST page, changes = JSON { key: value }. Saves every valid key; reports per-key errors and warnings. */
	public static function ajax_save() {
		ecv2_settings_guard();
		$slug = isset( $_POST['page'] ) ? sanitize_key( wp_unslash( $_POST['page'] ) ) : '';
		$page = wp_easycart_admin_settings_registry::page( $slug );
		if ( ! $page ) {
			wp_send_json_error( array( 'message' => __( 'Unknown settings page.', 'wp-easycart' ) ) );
		}
		$changes = isset( $_POST['changes'] ) ? json_decode( wp_unslash( $_POST['changes'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; every key and value is validated against the registry below.
		if ( ! is_array( $changes ) || empty( $changes ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to save.', 'wp-easycart' ) ) );
		}
		if ( count( $changes ) > 200 ) {
			wp_send_json_error( array( 'message' => __( 'Too many changes in one request.', 'wp-easycart' ) ) );
		}
		$saved = array();
		$errors = array();
		$warnings = array();
		$to_validate = array();
		$page_locked = wp_easycart_admin_settings_registry::is_locked( $page );
		foreach ( $changes as $raw_key => $raw_value ) {
			$key = sanitize_key( $raw_key );
			$field = wp_easycart_admin_settings_registry::field( $slug, $key );
			if ( ! $field || 'html' === $field['type'] ) {
				$errors[ $key ] = __( 'This setting cannot be saved from here.', 'wp-easycart' );
				continue;
			}
			$section = self::section_of( $page, $key );
			if ( $page_locked || wp_easycart_admin_settings_registry::is_locked( $field ) || ( $section && wp_easycart_admin_settings_registry::is_locked( $section ) ) ) {
				$errors[ $key ] = self::locked_text( $field['pro'] ? $field : $section );
				continue;
			}
			$clean = wp_easycart_admin_settings_registry::sanitize( $field, is_scalar( $raw_value ) ? (string) $raw_value : '' );
			if ( is_wp_error( $clean ) ) {
				$errors[ $key ] = $clean->get_error_message();
				continue;
			}
			$old = get_option( $key );
			update_option( $key, $clean );
			if ( is_callable( $field['on_save'] ) ) {
				call_user_func( $field['on_save'], $clean, $old, $field );
			}
			do_action( 'wp_easycart_settings_saved', $key, $clean, $old, $slug );
			if ( is_callable( $field['validate'] ) ) {
				$to_validate[ $key ] = array( $clean, $field );
			}
			$saved[ $key ] = array( 'value' => $clean, 'text' => wp_easycart_admin_settings_registry::value_text( array_merge( $field, array( 'key' => $key ) ) ) );
			if ( wp_easycart_admin_settings_registry::is_remote( $field ) ) {
				/* Search pickers redraw their chip from this ( a URL field's attached page picker names the page the new URL points at ). */
				$saved[ $key ]['chips'] = array_values( wp_easycart_admin_settings_registry::labels( $field, is_array( $clean ) ? $clean : array( (string) $clean ) ) );
			}
		}
		/* 6.0.0: validate after every change in the request is written, so a check that reads several settings ( a carrier
		   connection probe ) sees the complete new set once, not a half-saved one per field. */
		foreach ( $to_validate as $key => $pair ) {
			$warning = call_user_func( $pair[1]['validate'], $pair[0], $pair[1] );
			if ( is_string( $warning ) && '' !== $warning ) {
				$warnings[ $key ] = $warning;
			}
		}
		/* translators: %d: number of settings saved. */
		$reply = array( 'saved' => $saved, 'errors' => $errors, 'warnings' => $warnings, 'message' => count( $saved ) === 1 ? __( 'Saved.', 'wp-easycart' ) : sprintf( __( 'Saved %d settings.', 'wp-easycart' ), count( $saved ) ) );
		/**
		 * Filter the save reply, e.g. to return state the page redraws without a reload ( Shipping settings sends the
		 * carriers' fresh connection status ).
		 *
		 * @since 6.0.0
		 * @param array  $reply      saved, errors, warnings, message.
		 * @param string $slug       Settings page slug.
		 * @param array  $saved_keys Keys that were saved.
		 */
		$reply = apply_filters( 'wp_easycart_settings_save_reply', $reply, $slug, array_keys( $saved ) );
		if ( ! empty( $page['health'] ) && is_callable( $page['health'] ) ) {
			/* Re-checked after the options changed, so the banner and the row notes follow the new selection. */
			$reply['problems'] = array_values( self::page_problems( $page ) );
		}
		wp_send_json_success( $reply );
	}

	/**
	 * POST sigs = "key:id,key:id" → hides the store-pages admin notice for those pages for
	 * this user for a day ( see print_store_pages_notice() ). Only the pages currently
	 * broken can be dismissed, so a stale request cannot silence a future problem.
	 *
	 * @since 6.0.0
	 */
	public static function ajax_dismiss_store_notice() {
		ecv2_settings_guard();
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
		}
		$raw   = isset( $_POST['sigs'] ) ? sanitize_text_field( wp_unslash( $_POST['sigs'] ) ) : '';
		$valid = array();
		foreach ( self::store_pages_health() as $problem ) {
			$valid[] = $problem['key'] . ':' . (int) $problem['id'];
		}
		$wanted = array_values( array_intersect( array_map( 'trim', explode( ',', $raw ) ), $valid ) );
		if ( empty( $wanted ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to dismiss.', 'wp-easycart' ) ) );
		}
		$name      = self::STORE_NOTICE_TRANSIENT . get_current_user_id();
		$dismissed = get_transient( $name );
		$dismissed = is_array( $dismissed ) ? $dismissed : array();
		set_transient( $name, array_values( array_unique( array_merge( $dismissed, $wanted ) ) ), DAY_IN_SECONDS );
		wp_send_json_success( array( 'dismissed' => $wanted ) );
	}

	/** GET term → ranked matches across every declared page. */
	public static function ajax_search() {
		ecv2_settings_guard();
		$term = isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['term'] ) ) : '';
		wp_send_json_success( array( 'results' => wp_easycart_admin_settings_registry::search( $term, 20 ) ) );
	}

	/**
	 * GET page, key, term → matches for a search picker: at most SEARCH_LIMIT rows of
	 * { id, label, hint }. The field's 'search_callback' answers when declared; otherwise
	 * its resolved option list is filtered. Locked rows answer nothing.
	 *
	 * @since 6.0.0
	 */
	public static function ajax_option_search() {
		ecv2_settings_guard();
		$slug = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
		$key  = isset( $_REQUEST['key'] ) ? sanitize_key( wp_unslash( $_REQUEST['key'] ) ) : '';
		$term = isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['term'] ) ) : '';
		$page = wp_easycart_admin_settings_registry::page( $slug );
		$field = $page ? wp_easycart_admin_settings_registry::field( $slug, $key ) : false;
		/* Option rows, plus any row with a 'search_callback' ( a URL field's attached page picker ). */
		if ( ! $page || ! $field || ( ! in_array( $field['type'], array( 'select', 'pills', 'multiselect' ), true ) && ! wp_easycart_admin_settings_registry::is_remote( $field ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown setting.', 'wp-easycart' ) ) );
		}
		$section = self::section_of( $page, $key );
		if ( wp_easycart_admin_settings_registry::is_locked( $page ) || wp_easycart_admin_settings_registry::is_locked( $field ) || ( $section && wp_easycart_admin_settings_registry::is_locked( $section ) ) ) {
			wp_send_json_error( array( 'message' => self::locked_text( $field['pro'] ? $field : $section ) ) );
		}
		$results = array();
		foreach ( wp_easycart_admin_settings_registry::option_search( $field, $term, wp_easycart_admin_settings_registry::SEARCH_LIMIT ) as $row ) {
			$results[] = array( 'id' => $row['value'], 'label' => $row['label'], 'hint' => $row['hint'] );
		}
		wp_send_json_success( array( 'results' => $results, 'term' => $term, 'limit' => wp_easycart_admin_settings_registry::SEARCH_LIMIT ) );
	}

	/**
	 * The declaration a section's lock comes from: the section itself when it is locked, else its first field when every
	 * main setting in it is locked ( Integrations declares 'pro' per field, so a section can be all locked without being
	 * 'pro' ). Advanced rows are skipped: a locked service may still carry a free leftover behind the fold, such as the
	 * retired Universal Analytics id or DecoNetwork's blank-item toggle. False when a main setting can be used.
	 *
	 * @since 6.0.0
	 * @param array $section Section declaration.
	 * @return array|false
	 */
	public static function section_lock( $section ) {
		if ( wp_easycart_admin_settings_registry::is_locked( $section ) ) {
			return $section;
		}
		$first = false;
		foreach ( $section['fields'] as $field ) {
			if ( 'html' === $field['type'] || ! empty( $field['advanced'] ) ) {
				continue;
			}
			if ( ! wp_easycart_admin_settings_registry::is_locked( $field ) ) {
				return false;
			}
			if ( ! $first ) {
				$first = $field;
			}
		}
		return $first;
	}

	/**
	 * Small padlock for locked section links ( the plan name is in the title and screen-reader text ).
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public static function lock_icon() {
		return '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
	}

	/**
	 * Chip text for a locked page, section, field or action ( its 'pro' flag: true or 'premium' ).
	 *
	 * @since 6.0.0
	 * @param array|false $item Declaration.
	 * @return string
	 */
	public static function locked_badge( $item ) {
		$plan = ( is_array( $item ) && isset( $item['pro'] ) && 'premium' === $item['pro'] ) ? 'premium' : 'pro';
		if ( class_exists( 'wp_easycart_admin_edition' ) ) {
			return wp_easycart_admin_edition::badge( $plan );
		}
		return 'premium' === $plan ? __( 'Premium', 'wp-easycart' ) : __( 'Pro/Premium', 'wp-easycart' );
	}

	/**
	 * Tooltip / refusal sentence for a locked declaration.
	 *
	 * @since 6.0.0
	 * @param array|false $item Declaration.
	 * @return string
	 */
	public static function locked_text( $item ) {
		$plan = ( is_array( $item ) && isset( $item['pro'] ) && 'premium' === $item['pro'] ) ? 'premium' : 'pro';
		if ( class_exists( 'wp_easycart_admin_edition' ) ) {
			return wp_easycart_admin_edition::included_text( $plan );
		}
		return 'premium' === $plan ? __( 'Included with a Premium license.', 'wp-easycart' ) : __( 'Included with Pro and Premium licenses.', 'wp-easycart' );
	}

	/** POST page, section, action → runs the declared callback. */
	public static function ajax_action() {
		ecv2_settings_guard();
		$slug = isset( $_POST['page'] ) ? sanitize_key( wp_unslash( $_POST['page'] ) ) : '';
		$sec  = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
		$id   = isset( $_POST['action_id'] ) ? sanitize_key( wp_unslash( $_POST['action_id'] ) ) : '';
		$page = wp_easycart_admin_settings_registry::page( $slug );
		if ( ! $page || ! isset( $page['sections'][ $sec ]['actions'][ $id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown action.', 'wp-easycart' ) ) );
		}
		$action = $page['sections'][ $sec ]['actions'][ $id ];
		if ( wp_easycart_admin_settings_registry::is_locked( $page ) || wp_easycart_admin_settings_registry::is_locked( $page['sections'][ $sec ] ) || wp_easycart_admin_settings_registry::is_locked( $action ) ) {
			wp_send_json_error( array( 'message' => self::locked_text( $action['pro'] ? $action : $page['sections'][ $sec ] ) ) );
		}
		if ( ! is_callable( $action['callback'] ) ) {
			wp_send_json_error( array( 'message' => __( 'This action is not available.', 'wp-easycart' ) ) );
		}
		$result = call_user_func( $action['callback'], $action, $page );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$message = is_string( $result ) ? $result : ( ( is_array( $result ) && ! empty( $result['message'] ) ) ? (string) $result['message'] : __( 'Done.', 'wp-easycart' ) );
		$reply   = array( 'message' => $message, 'reload' => ( is_array( $result ) && ! empty( $result['reload'] ) ) );
		/* 'fields' => key => new value: the rows the action changed, redrawn in place ( with chip labels for search pickers ) instead of reloading. */
		if ( is_array( $result ) && ! empty( $result['fields'] ) && is_array( $result['fields'] ) ) {
			$reply['fields'] = array();
			foreach ( $result['fields'] as $key => $value ) {
				$field = wp_easycart_admin_settings_registry::field( $slug, $key );
				if ( ! $field ) {
					continue;
				}
				$row = array( 'value' => is_array( $value ) ? $value : (string) $value, 'text' => wp_easycart_admin_settings_registry::value_text( $field ) );
				if ( wp_easycart_admin_settings_registry::is_remote( $field ) ) {
					$row['chips'] = array_values( wp_easycart_admin_settings_registry::labels( $field, is_array( $value ) ? $value : array( (string) $value ) ) );
				}
				$reply['fields'][ $key ] = $row;
			}
		}
		if ( ! empty( $page['health'] ) && is_callable( $page['health'] ) ) {
			$reply['problems'] = array_values( self::page_problems( $page ) );
		}
		wp_send_json_success( $reply );
	}

	private static function section_of( $page, $key ) {
		foreach ( $page['sections'] as $section ) {
			if ( isset( $section['fields'][ $key ] ) ) {
				return $section;
			}
		}
		return false;
	}
}

add_action( 'admin_enqueue_scripts', array( 'wp_easycart_admin_settings_page_v2', 'enqueue_early' ), 5 );

/*
 * Declaration files are loaded lazily by the registry, so wp_ajax_* handlers a page
 * registers from its declaration ( or from a file it requires ) would never exist on
 * admin-ajax requests. Load every declaration early for any ecv2_* AJAX action.
 */
add_action( 'admin_init', function() {
	if ( ! wp_doing_ajax() || ! isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; each handler verifies its own nonce.
		return;
	}
	$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
	if ( 0 === strpos( $action, 'ecv2_' ) && class_exists( 'wp_easycart_admin_settings_registry' ) ) {
		wp_easycart_admin_settings_registry::pages();
	}
}, 1 );
add_action( 'wp_ajax_ecv2_settings_save', array( 'wp_easycart_admin_settings_page_v2', 'ajax_save' ) );
add_action( 'wp_ajax_ecv2_settings_search', array( 'wp_easycart_admin_settings_page_v2', 'ajax_search' ) );
add_action( 'wp_ajax_ecv2_settings_option_search', array( 'wp_easycart_admin_settings_page_v2', 'ajax_option_search' ) );
add_action( 'wp_ajax_ecv2_settings_action', array( 'wp_easycart_admin_settings_page_v2', 'ajax_action' ) );
add_action( 'wp_ajax_ecv2_settings_dismiss_store_notice', array( 'wp_easycart_admin_settings_page_v2', 'ajax_dismiss_store_notice' ) );

endif;
