<?php
/**
 * WP EasyCart Admin — Settings home.
 *
 * Landing page for Settings: a search across every V2 settings page, the
 * setup wizard's state, and the settings pages grouped the same way the
 * sidebar groups them. Pages still on the classic layout are listed too,
 * so the home page is complete from day one.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_settings_home' ) ) :

class wp_easycart_admin_settings_home {

	/**
	 * Every settings page, converted or not. Converted pages are overridden by
	 * their registry declaration ( title, description, count, url ).
	 */
	public static function catalog() {
		$pages = array(
			'products'          => array( 'group' => 'store-setup', 'icon' => 'products', 'title' => __( 'Products', 'wp-easycart' ), 'description' => __( 'Product pages, lists, reviews, inventory and store defaults.', 'wp-easycart' ) ),
			'checkout'          => array( 'group' => 'store-setup', 'icon' => 'cart', 'title' => __( 'Checkout', 'wp-easycart' ), 'description' => __( 'Cart behavior, checkout form fields, order statuses and stock control.', 'wp-easycart' ) ),
			'account'           => array( 'group' => 'store-setup', 'icon' => 'admin-users', 'title' => __( 'Accounts', 'wp-easycart' ), 'description' => __( 'Registration requirements, account page and spam protection.', 'wp-easycart' ) ),
			'payment'           => array( 'group' => 'financial', 'icon' => 'money-alt', 'title' => __( 'Payment', 'wp-easycart' ), 'description' => __( 'Live gateway, third-party checkout, bill later and test mode.', 'wp-easycart' ) ),
			'tax'               => array( 'group' => 'financial', 'icon' => 'media-spreadsheet', 'title' => __( 'Taxes', 'wp-easycart' ), 'description' => __( 'Global, state, country, VAT, duty and Canada tax.', 'wp-easycart' ) ),
			'fee'               => array( 'group' => 'financial', 'icon' => 'tag', 'title' => __( 'Flex-Fees', 'wp-easycart' ), 'description' => __( 'Percentage or flat fees and discounts by location, role or payment type.', 'wp-easycart' ), 'pro' => true ),
			'shipping-settings' => array( 'group' => 'financial', 'icon' => 'car', 'title' => __( 'Shipping settings', 'wp-easycart' ), 'description' => __( 'Shipping method, zones, packing slip and live-rate carriers.', 'wp-easycart' ) ),
			'shipping-rates'    => array( 'group' => 'financial', 'icon' => 'list-view', 'title' => __( 'Shipping rates', 'wp-easycart' ), 'description' => __( 'The rate tables for your chosen shipping method.', 'wp-easycart' ) ),
			'miscellaneous'     => array( 'group' => 'customize', 'icon' => 'admin-settings', 'title' => __( 'Additional settings', 'wp-easycart' ), 'description' => __( 'Admin options, storefront search, cart icon and newsletter popup.', 'wp-easycart' ) ),
			'design'            => array( 'group' => 'customize', 'icon' => 'art', 'title' => __( 'Design', 'wp-easycart' ), 'description' => __( 'Store colors, layout options and custom CSS.', 'wp-easycart' ) ),
			'language-editor'   => array( 'group' => 'customize', 'icon' => 'translation', 'title' => __( 'Language', 'wp-easycart' ), 'description' => __( 'Every storefront phrase, editable.', 'wp-easycart' ) ),
			'email-setup'       => array( 'group' => 'customize', 'icon' => 'email', 'title' => __( 'Email', 'wp-easycart' ), 'description' => __( 'Sender, order receipts, account emails and a test send.', 'wp-easycart' ) ),
			'country'           => array( 'group' => 'customize', 'icon' => 'admin-site-alt3', 'title' => __( 'Countries & Regions', 'wp-easycart' ), 'description' => __( 'Where you ship, and the regions shoppers can pick.', 'wp-easycart' ) ),
			'perpage'           => array( 'group' => 'customize', 'icon' => 'grid-view', 'title' => __( 'Per page options', 'wp-easycart' ), 'description' => __( 'How many products a shopper can show per page.', 'wp-easycart' ) ),
			'pricepoint'        => array( 'group' => 'customize', 'icon' => 'filter', 'title' => __( 'Price points', 'wp-easycart' ), 'description' => __( 'Price ranges shoppers filter by.', 'wp-easycart' ) ),
			'schedule'          => array( 'group' => 'customize', 'icon' => 'clock', 'title' => __( 'Schedule & locations', 'wp-easycart' ), 'description' => __( 'Opening hours, holidays and pickup windows, plus the store locations shoppers pick up from.', 'wp-easycart' ), 'pro' => true ),
			'third-party'       => array( 'group' => 'integrations', 'icon' => 'admin-plugins', 'title' => __( 'Third party', 'wp-easycart' ), 'description' => __( 'Google Analytics, Amazon, DecoNetwork.', 'wp-easycart' ) ),
			'cart-importer'     => array( 'group' => 'integrations', 'icon' => 'download', 'title' => __( 'Cart importer', 'wp-easycart' ), 'description' => __( 'Bring products in from Square or another cart.', 'wp-easycart' ) ),
			'logs'              => array( 'group' => 'troubleshoot', 'icon' => 'editor-ul', 'title' => __( 'Log entries', 'wp-easycart' ), 'description' => __( 'Gateway and webhook responses for troubleshooting.', 'wp-easycart' ) ),
		);
		$pages = apply_filters( 'wp_easycart_settings_home_catalog', $pages );
		foreach ( $pages as $slug => $entry ) {
			$pages[ $slug ] = wp_parse_args( $entry, array( 'pro' => false, 'legacy' => true, 'count' => 0, 'url' => admin_url( 'admin.php?page=wp-easycart-settings&subpage=' . $slug ) ) );
		}
		foreach ( wp_easycart_admin_settings_registry::pages() as $slug => $page ) {
			$pages[ $slug ] = array(
				'group'       => $page['group'],
				'icon'        => $page['icon'],
				'title'       => $page['title'],
				'description' => $page['description'],
				'pro'         => false,
				'legacy'      => false,
				'count'       => $page['count'],
				'url'         => $page['url'],
			);
			foreach ( $page['legacy'] as $old ) {
				if ( $old !== $slug ) {
					unset( $pages[ $old ] );
				}
			}
		}
		return $pages;
	}

	/**
	 * The wizard's checklist partial, captured so a failure inside it can never
	 * truncate the Settings home. On failure the error is logged and a plain
	 * link to the wizard is shown instead.
	 */
	private static function checklist_html( $wizard ) {
		$level = ob_get_level();
		ob_start();
		try {
			// The wizard only registers its stylesheet on its own screens; the checklist partial needs it here too.
			if ( ! wp_style_is( 'wp_easycart_setup_wizard_v2_css', 'registered' ) ) {
				wp_register_style( 'wp_easycart_setup_wizard_v2_css', plugins_url( 'wp-easycart/admin/css/setup-wizard-v2.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_css' ), EC_CURRENT_VERSION );
			}
			wp_enqueue_style( 'wp_easycart_setup_wizard_v2_css' );
			$wizard->render_checklist( 'settings' );
			return ob_get_clean();
		} catch ( Throwable $e ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			error_log( 'WP EasyCart settings home: checklist failed to render: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			return '<p class="ecst-desc">' . esc_html__( 'The launch checklist could not be shown here.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=5' ) ) . '">' . esc_html__( 'Open it in the setup wizard', 'wp-easycart' ) . '</a></p>';
		}
	}

	/**
	 * The wizard's cart importer checklist item when it applies and is neither done nor dismissed.
	 *
	 * @since 6.0.0
	 *
	 * @param object|false $wizard wp_easycart_admin_setup_wizard instance.
	 * @return array|null
	 */
	private static function importer_suggestion( $wizard ) {
		if ( ! $wizard || ! method_exists( $wizard, 'get_cart_importer_suggestion' ) ) {
			return null;
		}
		try {
			$item = $wizard->get_cart_importer_suggestion();
		} catch ( Throwable $e ) {
			error_log( 'WP EasyCart settings home: cart importer suggestion failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- mirrors checklist_html(); never break the Settings home.
			return null;
		}
		if ( ! $item || ! empty( $item['done'] ) ) {
			return null;
		}
		$state = method_exists( $wizard, 'get_checklist_state' ) ? $wizard->get_checklist_state() : array();
		return empty( $state['dismissed_importer'] ) ? $item : null;
	}

	public static function render() {
		wp_easycart_admin_settings_page_v2::enqueue();
		$groups  = wp_easycart_admin_settings_registry::groups();
		$catalog = self::catalog();
		$wizard  = function_exists( 'wp_easycart_admin_setup_wizard' ) ? wp_easycart_admin_setup_wizard() : false;
		$wizard_done = (bool) get_option( 'ec_option_setup_wizard_done' );
		$remaining = ( $wizard && method_exists( $wizard, 'count_checklist_remaining' ) ) ? (int) $wizard->count_checklist_remaining() : 0;
		$wizard_url = admin_url( 'admin.php?page=wp-easycart-settings&subpage=setup-wizard' );
		?>
		<div class="ecv2-wrap ecst-wrap ecst-home" id="ecst_home">
			<div class="ecv2-page-header ecst-header">
				<div class="ecv2-page-header-left">
					<h1 class="ecv2-page-title"><?php esc_html_e( 'Settings', 'wp-easycart' ); ?></h1>
					<div class="ecst-desc"><?php esc_html_e( 'Everything about how your store works, in one place.', 'wp-easycart' ); ?></div>
				</div>
				<div class="ecv2-page-header-right">
					<a class="ecv2-btn" href="<?php echo esc_url( $wizard_url ); ?>"><?php echo esc_html( $wizard_done ? __( 'Run the setup wizard again', 'wp-easycart' ) : __( 'Continue setup', 'wp-easycart' ) ); ?></a>
				</div>
			</div>

			<?php wp_easycart_admin_settings_page_v2::print_search( 'large', 'home' ); ?>

			<?php
			/* A store, cart or account page that is missing, unpublished or without its shortcode: the same red banner
			 * Store details shows, with "Fix" opening that row. Three get_post() calls at most. @since 6.0.0 */
			if ( $wizard_done && method_exists( 'wp_easycart_admin_settings_page_v2', 'store_pages_health' ) ) {
				wp_easycart_admin_settings_page_v2::print_store_pages_banner( wp_easycart_admin_settings_page_v2::store_pages_health(), 'home' );
			}
			?>

			<?php if ( $wizard && ( ! $wizard_done || $remaining > 0 ) ) : ?>
				<div class="ecdv2-card ecst-wizard-card">
					<div class="ecdv2-card-header">
						<h3 class="ecdv2-card-title"><?php echo esc_html( $wizard_done ? __( 'Launch checklist', 'wp-easycart' ) : __( 'Finish setting up your store', 'wp-easycart' ) ); ?></h3>
						<span class="ecdv2-card-hint"><?php echo esc_html( $wizard_done ? sprintf( _n( '%d item left', '%d items left', $remaining, 'wp-easycart' ), $remaining ) : __( 'Location, payments, shipping and pages, in four short steps.', 'wp-easycart' ) ); ?></span>
						<span class="ecst-grow"></span>
						<a class="ecv2-btn ecv2-btn-primary" href="<?php echo esc_url( $wizard_url ); ?>"><?php echo esc_html( $wizard_done ? __( 'Open checklist', 'wp-easycart' ) : __( 'Continue setup', 'wp-easycart' ) ); ?></a>
					</div>
					<?php if ( $wizard_done && method_exists( $wizard, 'render_checklist' ) ) : ?>
						<div class="ecdv2-card-body ecst-checklist"><?php echo self::checklist_html( $wizard ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial output, escaped where it is built. ?></div>
					<?php elseif ( ! $wizard_done ) : ?>
						<?php
						// The launch checklist only appears once the wizard is done. A store moving from Square or
						// WooCommerce should not have to finish the wizard to find the importer, so surface that one item.
						$importer = self::importer_suggestion( $wizard );
						?>
						<?php if ( $importer ) : ?>
							<div class="ecdv2-card-body ecst-importer-hint">
								<p class="ecst-desc" style="margin:0;"><b><?php echo esc_html( $importer['label'] ); ?></b> &middot; <?php echo esc_html( $importer['sub'] ); ?> <a href="<?php echo esc_url( $importer['action_url'] ); ?>"><?php echo esc_html( $importer['action_label'] ); ?></a></p>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php do_action( 'wp_easycart_settings_home_before_groups' ); ?>

			<?php foreach ( $groups as $group_slug => $group ) : ?>
				<?php
				$entries = array_filter( $catalog, function( $e ) use ( $group_slug ) { return $e['group'] === $group_slug; } );
				if ( empty( $entries ) ) {
					continue;
				}
				?>
				<div class="ecst-group">
					<div class="ecst-group-head"><h2><?php echo esc_html( $group['label'] ); ?></h2><span><?php echo esc_html( $group['hint'] ); ?></span></div>
					<div class="ecst-tiles">
						<?php foreach ( $entries as $slug => $entry ) : ?>
							<a class="ecst-tile<?php echo $entry['legacy'] ? ' is-legacy' : ''; ?>" href="<?php echo esc_url( $entry['url'] ); ?>">
								<?php $tile_svg = class_exists( 'wp_easycart_admin_settings_icons' ) ? wp_easycart_admin_settings_icons::svg( wp_easycart_admin_settings_icons::for_page( $entry ) ) : ''; ?>
								<span class="ecst-tile-ic"><?php if ( '' !== $tile_svg ) { echo $tile_svg; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG from wp_easycart_admin_settings_icons. */ } else { ?><span class="dashicons dashicons-<?php echo esc_attr( $entry['icon'] ); ?>"></span><?php } ?></span>
								<span class="ecst-tile-text">
									<b><?php echo esc_html( $entry['title'] ); ?><?php if ( $entry['pro'] ) : ?> <span class="ecst-pro-tag"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span><?php endif; ?></b>
									<span><?php echo esc_html( $entry['description'] ); ?></span>
									<?php if ( ! $entry['legacy'] && $entry['count'] > 0 ) : ?><em><?php echo esc_html( sprintf( _n( '%d setting', '%d settings', $entry['count'], 'wp-easycart' ), $entry['count'] ) ); ?></em><?php endif; ?>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<?php do_action( 'wp_easycart_settings_home_after_groups' ); ?>
		</div>
		<?php
	}
}

endif;
