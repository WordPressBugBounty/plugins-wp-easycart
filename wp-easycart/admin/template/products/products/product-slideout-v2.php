<?php
/**
 * WP EasyCart — unified product slideout ( Create + Quick Edit ).
 *
 * One panel, two modes. The mode is set on the root element as
 * data-mode="create|edit" by product-slideout-v2.js; CSS shows or hides
 * the mode-specific pieces ( product type vs sort position, footer verbs ).
 *
 * PRO features render in place: licensed installs get the live control,
 * free installs get a dashed row that opens the 'products' upsell with the
 * matching feature key ( see wp_easycart_admin_upsell::catalog() ).
 *
 * Replaces new-product-slideout.php and quick-edit-product-slideout.php.
 * Legacy element id that the new-option-set slideout writes into is preserved:
 *   #ec_new_product_option1 ( hidden source list; the picker mirrors it )
 *
 * Section visibility follows Settings › Admin › Quick add panel exactly as the
 * legacy panels did: stock, shipping and variant cards are create-mode extras
 * ( quick edit always shows stock and shipping ), the tax field is gated in both
 * modes and round-trips through a hidden input when it is off so quick edit never
 * clobbers a product's tax flags.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * This slideout is printed on the product list page itself, so it must not embed the
 * manufacturer or option-set tables. Both pickers search on the server as you type
 * ( ecv2_manufacturer_search / ecv2_option_set_search ); the nonces ride on the markup.
 *
 * @since 6.0.0
 */
$ecpsv2_manufacturer_nonce = wp_create_nonce( 'wp-easycart-ecv2-manufacturer-search' );
$ecpsv2_option_set_nonce   = wp_create_nonce( 'wp-easycart-ecv2-option-set-search' );

$is_pro        = ( '' === apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ) );
$adv_pricing   = (bool) apply_filters( 'wp_easycart_admin_ecv2_advanced_pricing_enabled', false );
$variants_pro  = function_exists( 'ecv2_is_variant_tracking_enabled' ) && ecv2_is_variant_tracking_enabled();
$pro_status    = class_exists( 'wp_easycart_admin_pro_gate' ) ? wp_easycart_admin_pro_gate::pro_status() : array( 'installed' => false, 'active' => false );
$pro_inactive  = ( ! $is_pro && $pro_status['installed'] && ! $pro_status['active'] );
/* Free edition allows two option sets per product; PRO allows five. */
$max_opt_sets  = $is_pro ? 5 : (int) apply_filters( 'wp_easycart_admin_free_option_set_limit', 2 );
$currency_sym  = get_option( 'ec_option_currency_symbol', '$' );
$step          = pow( 10, -1 * (int) $GLOBALS['currency']->get_decimal_length() );

/*
 * Settings › Admin › Quick add panel ( legacy: Additional Settings › Product Quick Add
 * Options ). Defaults mirror ec_wpoptionset: stock 0, shipping 0, tax 0, variants 1.
 * Both the legacy toggles and the V2 settings engine store 1 / 0.
 *
 * @since 6.0.0
 */
$show_stock    = (bool) get_option( 'ec_option_admin_product_show_stock_option', 0 );
$show_shipping = (bool) get_option( 'ec_option_admin_product_show_shipping_option', 0 );
$show_tax      = (bool) get_option( 'ec_option_admin_product_show_tax_option', 0 );
$show_variants = (bool) get_option( 'ec_option_admin_product_show_variant_option', 1 );
$settings_url  = self_admin_url( 'admin.php?page=wp-easycart-settings&subpage=admin' );

$free_types = array(
	'0'  => __( 'Classic retail product', 'wp-easycart' ),
	'13' => __( 'Service, event, or course', 'wp-easycart' ),
);
$pro_types = array(
	'1'  => __( 'Downloadable', 'wp-easycart' ),
	'2'  => __( 'eBook', 'wp-easycart' ),
	'3'  => __( 'Donation', 'wp-easycart' ),
	'4'  => __( 'Invoice', 'wp-easycart' ),
	'5'  => __( 'Subscription', 'wp-easycart' ),
	'6'  => __( 'Membership', 'wp-easycart' ),
	'7'  => __( 'Gift card', 'wp-easycart' ),
	'9'  => __( 'Inquiry / request a quote', 'wp-easycart' ),
	'10' => __( 'Seasonal / coming soon', 'wp-easycart' ),
	'11' => __( 'Restaurant item', 'wp-easycart' ),
	'12' => __( 'Preorder for pickup', 'wp-easycart' ),
	'8'  => __( 'Deconetwork', 'wp-easycart' ),
);

$lock_svg = '<svg class="ecpsv2-lock-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5 7V5a3 3 0 016 0v2"/></svg>';

/*
 * Strings the panel derives at runtime ( variant summary, option-set search ). They
 * ride on the markup so the JS stays translatable without touching the localize
 * array; ecpsv2_vars.lang wins for any key present in both.
 *
 * @since 6.0.0
 */
$ecpsv2_lang = array(
	'opt_search'       => __( 'Search option sets…', 'wp-easycart' ),
	'opt_no_match'     => __( 'No option sets match', 'wp-easycart' ),
	'searching'        => __( 'Searching…', 'wp-easycart' ),
	/* translators: %s is the option-set names joined with ×. */
	'opt_summary'      => __( 'Variants: %s', 'wp-easycart' ),
	'opt_combo_one'    => __( '1 combination', 'wp-easycart' ),
	/* translators: %d is a count. */
	'opt_combos'       => __( '%d combinations', 'wp-easycart' ),
	'opt_choice_one'   => __( '1 choice', 'wp-easycart' ),
	/* translators: %d is a count. */
	'opt_choices'      => __( '%d choices', 'wp-easycart' ),
	'opt_max'          => __( 'Maximum of 5 — use modifiers for more', 'wp-easycart' ),
	/* translators: %d: free-edition option set limit ( filled in by the script ), %s: plan name ( Pro/Premium, Pro or Premium ). */
	'opt_max_free'     => str_replace( '%s', wp_easycart_admin_edition::plan_name(), __( 'Free edition allows %d — %s allows 5', 'wp-easycart' ) ),
	'opt_one_left'     => __( '1 more option set available', 'wp-easycart' ),
	/* translators: %d is a count. */
	'opt_left'         => __( '%d more option sets available', 'wp-easycart' ),
	'drag'             => __( 'Drag to reorder', 'wp-easycart' ),
	'remove'           => __( 'Remove', 'wp-easycart' ),
);

if ( ! function_exists( 'ecpsv2_locked_row' ) ) :
	/**
	 * Locked PRO row. Free installs only.
	 */
	function ecpsv2_locked_row( $feature, $title, $desc, $lock_svg ) {
		echo '<button type="button" class="ecpsv2-pro-row" data-feature="' . esc_attr( $feature ) . '" onclick="ecdv2_upsell( { context: \'products\', feature: \'' . esc_js( $feature ) . '\' } ); return false;">';
		echo $lock_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup defined in this file.
		echo '<span class="ecpsv2-pro-row-text"><strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $desc ) . '</span></span>';
		echo '<span class="ecpsv2-pro-pill">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
		echo '</button>';
	}
endif;

if ( ! function_exists( 'ecpsv2_card_open' ) ) :
	/**
	 * Card shell matching the lite editors ( admin-details-v2 .ecdv2-card ): a header with
	 * the title, a one-line hint and an optional right-aligned slot, then the body.
	 *
	 * @since 6.0.0
	 *
	 * @param string $id    Element id.
	 * @param string $title Card title.
	 * @param string $hint  One-line hint ( plain text ).
	 * @param string $attrs Extra attributes, already escaped ( e.g. data-mode-only="edit" ).
	 * @param string $aside Pre-escaped markup for the right side of the header.
	 */
	function ecpsv2_card_open( $id, $title, $hint = '', $attrs = '', $aside = '' ) {
		echo '<section class="ecpsv2-card" id="' . esc_attr( $id ) . '"' . ( '' !== $attrs ? ' ' . $attrs : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is a literal attribute string built by the template.
		echo '<header class="ecpsv2-card-head"><h3 class="ecpsv2-card-title">' . esc_html( $title ) . '</h3>';
		if ( '' !== $hint ) {
			echo '<span class="ecpsv2-card-hint">' . esc_html( $hint ) . '</span>';
		}
		echo $aside; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-escaped by the caller.
		echo '</header><div class="ecpsv2-card-body">';
	}

	function ecpsv2_card_close() {
		echo '</div></section>';
	}

	/**
	 * Toggle row ( switch + label + one-line consequence ), same anatomy as the product
	 * editor's .ecdv2-toggle-row. The checkbox keeps the id the JS reads.
	 *
	 * @since 6.0.0
	 */
	function ecpsv2_toggle_row( $id, $label, $desc = '', $checked = false, $attrs = '' ) {
		echo '<div class="ecpsv2-toggle-row"' . ( '' !== $attrs ? ' ' . $attrs : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is a literal attribute string built by the template.
		echo '<span class="ecpsv2-toggle"><input type="checkbox" id="' . esc_attr( $id ) . '" value="1"' . ( $checked ? ' checked' : '' ) . ' /><span class="ecpsv2-toggle-track"></span></span>';
		echo '<span class="ecpsv2-toggle-meta"><label class="ecpsv2-toggle-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( '' !== $desc ) {
			echo '<span class="ecpsv2-toggle-desc">' . esc_html( $desc ) . '</span>';
		}
		echo '</span></div>';
	}

	/**
	 * Money input with the currency symbol as a prefix cell ( .ecdv2-currency-wrap look ).
	 *
	 * @since 6.0.0
	 */
	function ecpsv2_money_input( $id, $symbol, $step, $placeholder = '' ) {
		echo '<div class="ecpsv2-money"><span class="ecpsv2-money-sym">' . esc_html( $symbol ) . '</span><input type="number" min="0" step="' . esc_attr( $step ) . '" class="ecv2-input" id="' . esc_attr( $id ) . '" placeholder="' . esc_attr( $placeholder ) . '" /></div>';
	}
endif;
?>
<div class="ecv2-slideout-overlay ecpsv2-overlay" id="ecpsv2_box" data-mode="create" data-pro="<?php echo $is_pro ? '1' : '0'; ?>" data-max-opts="<?php echo (int) $max_opt_sets; ?>" data-show-stock="<?php echo $show_stock ? '1' : '0'; ?>" data-show-shipping="<?php echo $show_shipping ? '1' : '0'; ?>" data-show-tax="<?php echo $show_tax ? '1' : '0'; ?>" data-show-variants="<?php echo $show_variants ? '1' : '0'; ?>" style="display:none;">
<aside class="ecv2-slideout ecpsv2" role="dialog" aria-modal="true" aria-labelledby="ecpsv2_title">

	<input type="hidden" id="ecpsv2_product_id" value="" />
	<input type="hidden" id="wp_easycart_product_quick_edit_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-product-quick-edit' ) ); ?>" />
	<script type="application/json" id="ecpsv2_lang"><?php echo wp_json_encode( $ecpsv2_lang, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
	<div class="ecpsv2-loader" id="ecpsv2_loader"><span class="ecpsv2-spinner"></span></div>

	<header class="ecv2-slideout-header ecpsv2-header">
		<div class="ecv2-slideout-header-text">
			<div class="ecv2-slideout-eyebrow">
				<span data-mode-only="create"><?php esc_html_e( 'New product', 'wp-easycart' ); ?></span>
				<span data-mode-only="edit"><?php esc_html_e( 'Quick edit', 'wp-easycart' ); ?> · <span id="ecpsv2_eyebrow_id"></span></span>
				<?php if ( $is_pro ) : ?><span class="ecpsv2-pro-pill ecpsv2-pro-pill-active"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span><?php endif; ?>
			</div>
			<h2 class="ecv2-slideout-title ecpsv2-title" id="ecpsv2_title">
				<span data-mode-only="create"><?php esc_html_e( 'Create a product', 'wp-easycart' ); ?></span>
				<span data-mode-only="edit" id="ecpsv2_title_live"></span>
			</h2>
			<div class="ecpsv2-sub">
				<span data-mode-only="create"><?php esc_html_e( 'Just the essentials — everything else can be set after you create it.', 'wp-easycart' ); ?> <a href="<?php echo esc_url( $settings_url ); ?>" class="ecpsv2-link" target="_blank" rel="noopener"><?php esc_html_e( 'Customise this panel', 'wp-easycart' ); ?></a></span>
				<span data-mode-only="edit"><a href="#" id="ecpsv2_full_editor_link" class="ecpsv2-link"><?php esc_html_e( 'Open full editor', 'wp-easycart' ); ?></a></span>
			</div>
		</div>
		<button type="button" class="ecpsv2-x" onclick="ecpsv2_close();" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>">
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
		</button>
	</header>

	<div class="ecv2-slideout-body ecpsv2-body">

		<?php if ( $pro_inactive ) : ?>
		<div class="ecpsv2-notice">
			<span><?php echo esc_html( sprintf( /* translators: 1: plugin name, WP EasyCart PRO, 2: plan name ( Pro/Premium, Pro or Premium ). */ __( '%1$s is installed on this site — activate it to unlock the %2$s fields below.', 'wp-easycart' ), wp_easycart_admin_edition::PLUGIN_NAME, wp_easycart_admin_edition::plan_name() ) ); ?></span>
			<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( wp_easycart_admin()->get_pro_activation_link() ); ?>"><?php esc_html_e( 'Activate', 'wp-easycart' ); ?></a>
		</div>
		<?php endif; ?>

		<!-- ============================ BASICS ============================ -->
		<?php ecpsv2_card_open( 'ecpsv2_card_basics', __( 'Basics', 'wp-easycart' ), __( 'Title, SKU and where it appears', 'wp-easycart' ) ); ?>

			<div class="ecpsv2-toggle-grid">
				<?php ecpsv2_toggle_row( 'ecpsv2_status', __( 'Active in store', 'wp-easycart' ), __( 'Visible and purchasable on the storefront.', 'wp-easycart' ), true ); ?>
				<?php ecpsv2_toggle_row( 'ecpsv2_featured', __( 'Feature on store page', 'wp-easycart' ), __( 'Shows in the featured products area.', 'wp-easycart' ), true ); ?>
			</div>

			<div class="ecpsv2-f">
				<label for="ecpsv2_title_input"><?php esc_html_e( 'Title', 'wp-easycart' ); ?> <span class="ecpsv2-req">*</span></label>
				<input type="text" class="ecv2-input" id="ecpsv2_title_input" placeholder="<?php esc_attr_e( 'e.g. Smoked chicken sandwich', 'wp-easycart' ); ?>" />
				<div class="ecpsv2-hint ecpsv2-err" id="ecpsv2_title_err" hidden><?php esc_html_e( 'A title is required.', 'wp-easycart' ); ?></div>
			</div>

			<div class="ecpsv2-grid2">
				<div class="ecpsv2-f">
					<label for="ecpsv2_sku"><?php esc_html_e( 'SKU', 'wp-easycart' ); ?> <span class="ecpsv2-req">*</span></label>
					<input type="text" class="ecv2-input ecpsv2-mono" id="ecpsv2_sku" placeholder="<?php esc_attr_e( 'auto-generated from title', 'wp-easycart' ); ?>" />
					<div class="ecpsv2-hint" id="ecpsv2_sku_hint"><?php esc_html_e( 'Leave blank to generate from the title.', 'wp-easycart' ); ?></div>
					<div class="ecpsv2-hint ecpsv2-err" id="ecpsv2_sku_err" hidden></div>
				</div>

				<div class="ecpsv2-f" data-mode-only="create">
					<label for="ecpsv2_type"><?php esc_html_e( 'Product type', 'wp-easycart' ); ?></label>
					<select class="ecv2-select" id="ecpsv2_type">
						<?php foreach ( $free_types as $v => $l ) : ?>
						<option value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
						<optgroup label="<?php echo $is_pro ? esc_attr__( 'More product types', 'wp-easycart' ) : esc_attr( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( '%s product types', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ); ?>" id="ecpsv2_type_pro">
							<?php foreach ( $pro_types as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" data-pro="1"<?php echo $is_pro ? '' : ' disabled'; ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					</select>
					<div class="ecpsv2-hint" id="ecpsv2_type_hint"><?php echo $is_pro ? '' : esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Downloads, subscriptions, gift cards and more are %s types.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ); ?></div>
				</div>

				<div class="ecpsv2-f" data-mode-only="edit">
					<label><?php esc_html_e( 'Product type', 'wp-easycart' ); ?></label>
					<div class="ecpsv2-readonly">
						<span id="ecpsv2_type_label"></span>
						<a href="#" class="ecpsv2-link ecpsv2-full-editor" data-tab="general"><?php esc_html_e( 'Change in full editor', 'wp-easycart' ); ?></a>
					</div>
				</div>
			</div>

			<div class="ecpsv2-grid2">
				<div class="ecpsv2-f">
					<label for="ecpsv2_manu_input"><?php esc_html_e( 'Manufacturer / brand', 'wp-easycart' ); ?> <span class="ecpsv2-hint"><?php esc_html_e( 'optional', 'wp-easycart' ); ?></span></label>
					<div class="ecpsv2-combo" id="ecpsv2_manu_combo" data-nonce="<?php echo esc_attr( $ecpsv2_manufacturer_nonce ); ?>">
						<input type="text" class="ecv2-input" id="ecpsv2_manu_input" autocomplete="off" placeholder="<?php esc_attr_e( 'Type to search or add a brand', 'wp-easycart' ); ?>" role="combobox" aria-expanded="false" aria-controls="ecpsv2_manu_list" />
						<input type="hidden" id="ecpsv2_manu_id" value="0" />
						<button type="button" class="ecpsv2-combo-clear" id="ecpsv2_manu_clear" aria-label="<?php esc_attr_e( 'Clear', 'wp-easycart' ); ?>" hidden>×</button>
						<ul class="ecpsv2-combo-list" id="ecpsv2_manu_list" role="listbox" hidden></ul>
					</div>
					<div class="ecpsv2-hint" id="ecpsv2_manu_hint"></div>
				</div>
				<div class="ecpsv2-f" data-mode-only="edit">
					<label for="ecpsv2_sort"><?php esc_html_e( 'Sort position', 'wp-easycart' ); ?></label>
					<input type="number" step="1" class="ecv2-input" id="ecpsv2_sort" value="0" />
				</div>
			</div>
		<?php ecpsv2_card_close(); ?>

		<!-- ========================= PRICING & TAX ======================== -->
		<?php
		ecpsv2_card_open(
			'ecpsv2_card_pricing',
			$show_tax ? __( 'Pricing & tax', 'wp-easycart' ) : __( 'Pricing', 'wp-easycart' ),
			$show_tax ? __( 'What shoppers pay and how tax applies at checkout', 'wp-easycart' ) : __( 'What shoppers pay; a list price shows the discount', 'wp-easycart' )
		);
		?>
			<div class="ecpsv2-grid2">
				<div class="ecpsv2-f">
					<label for="ecpsv2_price"><?php esc_html_e( 'Price', 'wp-easycart' ); ?> <span class="ecpsv2-req">*</span></label>
					<?php ecpsv2_money_input( 'ecpsv2_price', $currency_sym, $step, '19.99' ); ?>
					<div class="ecpsv2-hint ecpsv2-err" id="ecpsv2_price_err" hidden><?php esc_html_e( 'Enter a price of 0 or more.', 'wp-easycart' ); ?></div>
				</div>
				<div class="ecpsv2-f">
					<label for="ecpsv2_list_price"><?php esc_html_e( 'List price', 'wp-easycart' ); ?> <span class="ecpsv2-hint"><?php esc_html_e( 'optional', 'wp-easycart' ); ?></span></label>
					<?php ecpsv2_money_input( 'ecpsv2_list_price', $currency_sym, $step, '—' ); ?>
					<div class="ecpsv2-hint" id="ecpsv2_disc_hint"><?php esc_html_e( 'Shows a strike-through price and "% off" badge.', 'wp-easycart' ); ?></div>
				</div>
			</div>

			<?php if ( $show_tax ) : ?>
			<div class="ecpsv2-grid2 ecpsv2-tax-row">
				<div class="ecpsv2-f">
					<label for="ecpsv2_tax"><?php esc_html_e( 'Tax', 'wp-easycart' ); ?></label>
					<select class="ecv2-select" id="ecpsv2_tax">
						<option value="0"><?php esc_html_e( 'Not taxable', 'wp-easycart' ); ?></option>
						<option value="1"><?php esc_html_e( 'Sales tax', 'wp-easycart' ); ?></option>
						<option value="2"><?php esc_html_e( 'VAT', 'wp-easycart' ); ?></option>
						<option value="3"><?php esc_html_e( 'Sales tax + VAT', 'wp-easycart' ); ?></option>
					</select>
					<div class="ecpsv2-hint"><?php esc_html_e( 'Uses the rates set under Settings › Tax.', 'wp-easycart' ); ?></div>
				</div>
			</div>
			<?php else : ?>
			<!-- Tax fields are off in Settings › Admin › Quick add panel. Quick edit still posts the product's current value. -->
			<input type="hidden" id="ecpsv2_tax" value="0" />
			<?php endif; ?>

			<?php if ( $adv_pricing ) : ?>
			<!-- PRO: advanced pricing chips. Wrapper mirrors the list's .ecv2-price-cell contract so the PRO managers can open from here. -->
			<div class="ecpsv2-pro-live" data-mode-only="edit">
				<div class="ecv2-price-cell ecpsv2-price-cell" id="ecpsv2_price_cell" data-product-id="" data-nonce="" data-volume-nonce="" data-b2b-nonce="" data-price="" data-list-price="" data-advanced="{}">
					<span class="ecpsv2-pro-live-label"><?php esc_html_e( 'Advanced pricing', 'wp-easycart' ); ?></span>
					<div class="ecpsv2-chips">
						<button type="button" class="ecpsv2-chip" id="ecpsv2_chip_volume" onclick="ecpsv2_open_manager( 'volume', this ); return false;"></button>
						<button type="button" class="ecpsv2-chip" id="ecpsv2_chip_b2b" onclick="ecpsv2_open_manager( 'b2b', this ); return false;"></button>
						<button type="button" class="ecpsv2-chip" id="ecpsv2_chip_advanced" onclick="ecpsv2_open_manager( 'advanced', this ); return false;"></button>
					</div>
				</div>
			</div>
			<div class="ecpsv2-hint ecpsv2-after-create" data-mode-only="create"><?php esc_html_e( 'Volume tiers, B2B prices and price labels can be added right after you create the product.', 'wp-easycart' ); ?></div>
			<?php else : ?>
			<?php ecpsv2_locked_row( 'advanced', __( 'Volume tiers, B2B role pricing & price labels', 'wp-easycart' ), __( 'Buy-more-save-more, wholesale prices by customer role, login-to-view.', 'wp-easycart' ), $lock_svg ); ?>
			<?php endif; ?>
		<?php ecpsv2_card_close(); ?>

		<!-- ============================ MEDIA ============================= -->
		<?php ecpsv2_card_open( 'ecpsv2_card_media', __( 'Media', 'wp-easycart' ), __( 'Main image for the list and the storefront thumbnail', 'wp-easycart' ) ); ?>
			<div class="ecpsv2-img">
				<div class="ecpsv2-img-thumb" id="ecpsv2_thumb">
					<img id="ecpsv2_thumb_img" src="" alt="" hidden />
					<svg id="ecpsv2_thumb_ph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/></svg>
				</div>
				<div class="ecpsv2-img-meta">
					<input type="hidden" id="ecpsv2_image" value="" />
					<div class="ecpsv2-img-name" id="ecpsv2_img_name"><?php esc_html_e( 'No main image yet', 'wp-easycart' ); ?></div>
					<div class="ecpsv2-hint"><?php esc_html_e( 'JPG, PNG or WebP from the Media Library.', 'wp-easycart' ); ?></div>
					<div class="ecpsv2-img-actions">
						<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecpsv2_pick_image();"><?php esc_html_e( 'Choose image', 'wp-easycart' ); ?></button>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="ecpsv2_img_remove" onclick="ecpsv2_set_image( '' );" hidden><?php esc_html_e( 'Remove', 'wp-easycart' ); ?></button>
					</div>
				</div>
			</div>

			<?php if ( $is_pro ) : ?>
			<div class="ecpsv2-pro-live" data-mode-only="edit">
				<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecpsv2_gallery_btn" onclick="ecpsv2_open_manager( 'images', this ); return false;"><?php esc_html_e( 'Manage gallery & video', 'wp-easycart' ); ?></button>
				<span class="ecpsv2-hint" id="ecpsv2_gallery_hint"></span>
			</div>
			<div class="ecpsv2-hint ecpsv2-after-create" data-mode-only="create"><?php esc_html_e( 'Add gallery images and video right after you create the product.', 'wp-easycart' ); ?></div>
			<?php else : ?>
			<?php ecpsv2_locked_row( 'images', __( 'Gallery, per-option image sets & video', 'wp-easycart' ), __( 'Drag-to-reorder galleries from the Media Library, YouTube and Vimeo embeds.', 'wp-easycart' ), $lock_svg ); ?>
			<?php endif; ?>
		<?php ecpsv2_card_close(); ?>

		<!-- ===================== INVENTORY & SHIPPING ===================== -->
		<?php
		/*
		 * Quick edit always carries stock and shipping ( as the legacy quick-edit panel did );
		 * on create each group follows its Quick add panel toggle. When both are off the whole
		 * card is edit-only.
		 */
		$inv_title = ( $show_stock && ! $show_shipping ) ? __( 'Inventory', 'wp-easycart' ) : ( ( $show_shipping && ! $show_stock ) ? __( 'Shipping', 'wp-easycart' ) : __( 'Inventory & shipping', 'wp-easycart' ) );
		$inv_hint  = ( $show_stock && ! $show_shipping ) ? __( 'Stock tracking and quantity on hand', 'wp-easycart' ) : ( ( $show_shipping && ! $show_stock ) ? __( 'Whether it ships and its package dimensions', 'wp-easycart' ) : __( 'Stock tracking and package dimensions', 'wp-easycart' ) );
		ecpsv2_card_open( 'ecpsv2_card_inventory', $inv_title, $inv_hint, ( ! $show_stock && ! $show_shipping ) ? 'data-mode-only="edit"' : '' );
		?>
			<div class="ecpsv2-group" id="ecpsv2_stock_group"<?php echo $show_stock ? '' : ' data-mode-only="edit"'; ?>>
				<div class="ecpsv2-grid2">
					<div class="ecpsv2-f">
						<label for="ecpsv2_stock"><?php esc_html_e( 'Stock tracking', 'wp-easycart' ); ?></label>
						<select class="ecv2-select" id="ecpsv2_stock">
							<option value="0"><?php esc_html_e( "Don't track", 'wp-easycart' ); ?></option>
							<option value="1"><?php esc_html_e( 'Track this product', 'wp-easycart' ); ?></option>
							<option value="2"<?php echo $variants_pro ? '' : ' data-pro="1"'; ?>><?php esc_html_e( 'Track per variation', 'wp-easycart' ); ?><?php echo $variants_pro ? '' : ' (' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . ')'; ?></option>
						</select>
					</div>
					<div class="ecpsv2-f" id="ecpsv2_qty_f">
						<label for="ecpsv2_qty"><?php esc_html_e( 'Quantity on hand', 'wp-easycart' ); ?></label>
						<input type="number" step="1" class="ecv2-input" id="ecpsv2_qty" placeholder="0" />
					</div>
				</div>
				<div class="ecpsv2-hint ecpsv2-block" id="ecpsv2_variant_hint" hidden>
					<span data-mode-only="create"><?php esc_html_e( 'Per-variation quantities are generated from your option sets when the product is created; set them in the full editor.', 'wp-easycart' ); ?></span>
					<span data-mode-only="edit"><?php esc_html_e( 'Quantities are tracked per variation.', 'wp-easycart' ); ?> <a href="#" class="ecpsv2-link" id="ecpsv2_manage_variants"><?php esc_html_e( 'Manage variations', 'wp-easycart' ); ?></a></span>
				</div>
			</div>

			<div class="ecpsv2-group" id="ecpsv2_ship_group"<?php echo $show_shipping ? '' : ' data-mode-only="edit"'; ?>>
				<?php ecpsv2_toggle_row( 'ecpsv2_ship', __( 'Requires shipping', 'wp-easycart' ), __( 'Adds this product to shipping calculations at checkout.', 'wp-easycart' ), false ); ?>
				<div class="ecpsv2-dims" id="ecpsv2_dims" hidden>
					<div class="ecpsv2-grid4">
						<div class="ecpsv2-f"><label for="ecpsv2_weight"><?php esc_html_e( 'Weight', 'wp-easycart' ); ?></label><input type="number" min="0" step=".01" class="ecv2-input" id="ecpsv2_weight" placeholder="0.0" /></div>
						<div class="ecpsv2-f"><label for="ecpsv2_length"><?php esc_html_e( 'Length', 'wp-easycart' ); ?></label><input type="number" min="0" step=".01" class="ecv2-input" id="ecpsv2_length" placeholder="0" /></div>
						<div class="ecpsv2-f"><label for="ecpsv2_width"><?php esc_html_e( 'Width', 'wp-easycart' ); ?></label><input type="number" min="0" step=".01" class="ecv2-input" id="ecpsv2_width" placeholder="0" /></div>
						<div class="ecpsv2-f"><label for="ecpsv2_height"><?php esc_html_e( 'Height', 'wp-easycart' ); ?></label><input type="number" min="0" step=".01" class="ecv2-input" id="ecpsv2_height" placeholder="0" /></div>
					</div>
					<div class="ecpsv2-hint"><?php esc_html_e( 'Used for live carrier rates and rate tables. Leave blank for flat-rate shipping.', 'wp-easycart' ); ?></div>
				</div>
			</div>
		<?php ecpsv2_card_close(); ?>

		<!-- ===================== OPTIONS & VARIATIONS ===================== -->
		<?php
		/* Create-mode picker follows the "Variant fields" toggle; the quick-edit summary always shows. */
		ecpsv2_card_open( 'ecpsv2_card_options', __( 'Options & variations', 'wp-easycart' ), __( 'Size, colour and other choices shoppers pick', 'wp-easycart' ), $show_variants ? '' : 'data-mode-only="edit"' );
		?>

			<?php if ( $show_variants ) : ?>
			<div data-mode-only="create">
				<div class="ecpsv2-f" id="ecpsv2_optmode_f">
					<label for="ecpsv2_optmode"><?php esc_html_e( 'Choices on this product', 'wp-easycart' ); ?></label>
					<select class="ecv2-select" id="ecpsv2_optmode">
						<option value="0"><?php esc_html_e( 'No options or modifiers', 'wp-easycart' ); ?></option>
						<option value="1"><?php esc_html_e( 'Product options (size, color, …)', 'wp-easycart' ); ?></option>
						<?php do_action( 'wp_easycart_admin_product_slideout_option_types' ); ?>
					</select>
				</div>
				<div id="ecpsv2_optrows" hidden>
					<!-- Legacy insertion point only: the new-option-set slideout appends its new set here and the picker adopts it. Existing sets are searched, never listed. -->
					<select id="ec_new_product_option1" hidden aria-hidden="true" tabindex="-1">
						<option value="0"></option>
					</select>

					<!-- Chosen sets as chips, in slot order ( option_id_1..5 ). Drag a chip to reorder. -->
					<div class="ecpsv2-opt-chips" id="ecpsv2_opt_list" role="list" aria-label="<?php esc_attr_e( 'Chosen option sets', 'wp-easycart' ); ?>"></div>

					<div class="ecpsv2-opt-search" id="ecpsv2_opt_search_wrap" data-nonce="<?php echo esc_attr( $ecpsv2_option_set_nonce ); ?>">
						<svg class="ecpsv2-opt-search-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5L14 14"/></svg>
						<input type="text" class="ecv2-input" id="ecpsv2_opt_search" autocomplete="off" placeholder="<?php esc_attr_e( 'Search option sets…', 'wp-easycart' ); ?>" role="combobox" aria-expanded="false" aria-controls="ecpsv2_opt_results" aria-autocomplete="list" />
						<ul class="ecpsv2-combo-list ecpsv2-opt-results" id="ecpsv2_opt_results" role="listbox" hidden></ul>
					</div>

					<div class="ecpsv2-opt-foot">
						<span class="ecpsv2-hint" id="ecpsv2_opt_count"></span>
						<button type="button" class="ecpsv2-link" onclick="if ( typeof ecosv2_open === 'function' ) { ecosv2_open( { origin: 'product' } ); } else { ecpsv2_open_nested( 'new_option_box' ); }"><?php esc_html_e( '+ Create a new option set', 'wp-easycart' ); ?></button>
					</div>

					<div class="ecpsv2-hint ecpsv2-opt-empty" id="ecpsv2_opt_empty"><?php esc_html_e( 'No option sets yet. Search above — the order of the chips is the order shoppers see.', 'wp-easycart' ); ?></div>

					<div class="ecpsv2-opt-summary" id="ecpsv2_opt_summary_line" hidden>
						<strong id="ecpsv2_opt_summary_text"></strong>
						<span><?php esc_html_e( 'Per-variation prices, SKUs and stock are set in the editor once the product exists.', 'wp-easycart' ); ?></span>
					</div>

					<?php if ( ! $is_pro ) : ?>
					<div class="ecpsv2-hint ecpsv2-opt-more" id="ecpsv2_opt_more" hidden>
						<?php esc_html_e( 'You have used both option sets available in the free edition.', 'wp-easycart' ); ?>
						<button type="button" class="ecpsv2-link" onclick="ecdv2_upsell( { context: 'products', feature: 'variants' } ); return false;"><?php echo esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( '%s allows 5 plus modifiers', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ); ?></button>
					</div>
					<?php endif; ?>
				</div>
				<div class="ecpsv2-hint ecpsv2-block" id="ecpsv2_modifier_note" hidden><?php esc_html_e( 'Modifiers (text fields, add-ons, uploads) are attached in the full editor after the product is created.', 'wp-easycart' ); ?></div>
			</div>
			<?php endif; ?>

			<!-- Edit: read-only summary, points to the full editor. -->
			<div data-mode-only="edit">
				<div class="ecpsv2-readonly ecpsv2-readonly-block">
					<div class="ecpsv2-chips ecpsv2-chips-static" id="ecpsv2_opt_summary"></div>
					<a href="#" class="ecpsv2-link ecpsv2-full-editor" data-tab="options"><?php esc_html_e( 'Edit options in full editor', 'wp-easycart' ); ?></a>
				</div>
			</div>

			<?php if ( ! $variants_pro ) : ?>
			<?php ecpsv2_locked_row( 'variants', __( 'Up to 5 option sets, modifiers & per-variation pricing', 'wp-easycart' ), sprintf( /* translators: 1: free-edition option set limit, 2: plan name ( Pro/Premium, Pro or Premium ). */ __( 'The free edition allows %1$d option sets per product. %2$s allows 5, plus modifiers ( text, uploads, add-ons ) and a price and SKU for every combination.', 'wp-easycart' ), $max_opt_sets, wp_easycart_admin_edition::plan_name() ), $lock_svg ); ?>
			<?php endif; ?>
		<?php ecpsv2_card_close(); ?>

		<?php if ( ! $is_pro ) : ?>
		<!-- ====================== FREE: summary card ====================== -->
		<div class="ecpsv2-upsell-card">
			<div class="ecpsv2-upsell-head"><span class="ecpsv2-pro-pill"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span><strong><?php esc_html_e( 'Everything greyed out above, unlocked', 'wp-easycart' ); ?></strong></div>
			<ul>
				<li><?php esc_html_e( 'Subscriptions, downloads and gift cards as product types', 'wp-easycart' ); ?></li>
				<li><?php esc_html_e( 'Volume, B2B and per-variation pricing', 'wp-easycart' ); ?></li>
				<li><?php esc_html_e( 'Galleries with video, modifiers, back-in-stock alerts', 'wp-easycart' ); ?></li>
			</ul>
			<div class="ecpsv2-upsell-actions">
				<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="ecdv2_upsell( { context: 'products' } ); return false;"><?php esc_html_e( "See what's included", 'wp-easycart' ); ?></button>
				<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( self_admin_url( 'admin.php?page=wp-easycart-registration' ) ); ?>"><?php esc_html_e( 'Try free for 14 days', 'wp-easycart' ); ?></a>
			</div>
		</div>
		<?php endif; ?>

	</div>

	<footer class="ecv2-slideout-footer ecpsv2-footer">
		<button type="button" class="ecv2-btn ecv2-btn-ghost ecpsv2-discard" data-mode-only="edit" id="ecpsv2_discard" onclick="ecpsv2_discard();" disabled><?php esc_html_e( 'Discard changes', 'wp-easycart' ); ?></button>
		<span class="ecpsv2-spacer"></span>
		<button type="button" class="ecv2-btn" data-mode-only="create" onclick="ecpsv2_save( 'another' );"><?php esc_html_e( 'Create & add another', 'wp-easycart' ); ?></button>
		<button type="button" class="ecv2-btn" data-mode-only="create" onclick="ecpsv2_save( 'edit' );"><?php esc_html_e( 'Create & open editor', 'wp-easycart' ); ?></button>
		<button type="button" class="ecv2-btn ecv2-btn-primary" data-mode-only="create" onclick="ecpsv2_save( 'close' );"><?php esc_html_e( 'Create product', 'wp-easycart' ); ?></button>
		<button type="button" class="ecv2-btn" data-mode-only="edit" onclick="ecpsv2_save( 'edit' );"><?php esc_html_e( 'Save & open editor', 'wp-easycart' ); ?></button>
		<button type="button" class="ecv2-btn ecv2-btn-primary" data-mode-only="edit" onclick="ecpsv2_save( 'close' );"><?php esc_html_e( 'Save changes', 'wp-easycart' ); ?></button>
	</footer>
</aside>
</div>
<script>jQuery( document.getElementById( 'ecpsv2_box' ) ).appendTo( document.body );</script>
