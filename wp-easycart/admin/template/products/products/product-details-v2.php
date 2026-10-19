<?php
/**
 * Product Editor V2 template.
 * Loaded by wp_easycart_admin_details_products_v2::load_template().
 * Every legacy do_action / apply_filters extension point still fires.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_easycart_admin()->load_new_slideout( 'product' );
wp_easycart_admin()->load_new_slideout( 'manufacturer' );
wp_easycart_admin()->load_new_slideout( 'optionset' );
wp_easycart_admin()->load_new_slideout( 'advanced-optionset' );

$is_new = ( ! $this->id );
$product = $this->product;
/* 6.0.4 ( editor plan Phase 2 ): Add product's hidden draft, until its first Save ( wp_easycart_product_drafts ). */
$is_draft = ! $is_new && ! empty( $this->is_draft );

/* Header thumbnail: attachment gallery first, then legacy image1 url. */
$thumb_url = '';
if ( ! $is_new ) {
	if ( ! empty( $product->product_images ) ) {
		$first_image = explode( ',', $product->product_images );
		if ( is_numeric( $first_image[0] ) ) {
			$img = wp_get_attachment_image_src( (int) $first_image[0], 'thumbnail' );
			if ( $img ) {
				$thumb_url = $img[0];
			}
		}
	}
	if ( '' === $thumb_url && '' !== $product->image1 && ! in_array( $product->image1, array( 'image1', 'image2', 'image3', 'image4', 'image5' ), true ) ) {
		$thumb_url = ( 0 === strpos( $product->image1, 'http' ) ) ? $product->image1 : site_url( '/wp-content/uploads/wpeasycart/products/' . $product->image1 );
	}
}

/* 6.0.4: the picture for the summary column's storefront card, a size up from the header's thumbnail. */
$ecdv2_card_image = $thumb_url;
if ( ! $is_new && ! empty( $product->product_images ) ) {
	$ecdv2_first = explode( ',', $product->product_images );
	if ( is_numeric( $ecdv2_first[0] ) ) {
		$ecdv2_img = wp_get_attachment_image_src( (int) $ecdv2_first[0], 'medium' );
		if ( $ecdv2_img ) {
			$ecdv2_card_image = $ecdv2_img[0];
		}
	}
}

$currency_symbol = $this->currency_symbol();

/* PRO v2 panel availability ( re-hooked by an updated PRO plugin ). */
$has_pro_media_v2 = has_action( 'wp_easycart_admin_product_details_v2_media_pro' );
$has_pro_options_v2 = has_action( 'wp_easycart_admin_product_details_v2_options_pro' );

/*
 * Legacy PRO compat: the old PRO plugin stacks BOTH its images and options
 * panels onto the single 'after_images' action. When the v2 hooks are not
 * present but the PRO accessor exists, detach those two callbacks and call
 * the loaders directly so each panel lands in its own tab. 'after_images'
 * still fires in the Media tab for any third-party listeners.
 */
$pro_legacy = ( ! $has_pro_media_v2 || ! $has_pro_options_v2 ) && function_exists( 'wp_easycart_admin_products_pro' );
$ec_legacy_pro = false;
if ( $pro_legacy ) {
	$ec_legacy_pro = wp_easycart_admin_products_pro();
	/* 6.0.4: PRO hooks its two panels only while its license is active ( wp_easycart_admin_products_pro's constructor ), and
	   its saves refuse without one. The loaders are called here only when PRO hooked them, so a store whose PRO is not licensed
	   gets the free edition's panels with their upsell, never PRO's panels that look as if they work ( Add modifier did nothing ). */
	$ecdv2_pro_hooked_images  = remove_action( 'wp_easycart_admin_product_details_after_images', array( $ec_legacy_pro, 'load_images_pro' ) );
	$ecdv2_pro_hooked_options = remove_action( 'wp_easycart_admin_product_details_after_images', array( $ec_legacy_pro, 'load_options_pro' ) );
	if ( ! $ecdv2_pro_hooked_images && ! $ecdv2_pro_hooked_options ) {
		$ec_legacy_pro = false;
	}
}
$has_pro_media_legacy = has_action( 'wp_easycart_admin_product_details_after_images' );

/* 6.0.4 ( editor plan Phase 2 ): the sections in the order a product is built, price in one place ( Pricing ), stock beside the
   variants that hold it, Shipping on its own ( a download or a service has none ). The keys are unchanged; Shipping is new. */
/* 6.0.5: Type settings is named for the product's type ( Subscription settings, Download settings … ); the rail keeps one word. */
$ecdv2_type_section = method_exists( $this, 'type_section_v2' ) ? $this->type_section_v2() : array( 'rail' => __( 'Type settings', 'wp-easycart' ), 'title' => __( 'Type settings', 'wp-easycart' ), 'desc' => __( 'Settings for the product type: subscription billing, the download, and purchase rules.', 'wp-easycart' ) );
$tabs = apply_filters( 'wp_easycart_admin_product_details_v2_tabs', array(
	'general'   => array( 'label' => __( 'Basics', 'wp-easycart' ), 'icon' => 'dashicons-tag', 'group' => 'essentials', 'desc' => __( 'Its name, SKU and what shoppers read about it.', 'wp-easycart' ) ),
	'media'     => array( 'label' => __( 'Media', 'wp-easycart' ), 'icon' => 'dashicons-format-gallery', 'group' => 'essentials', 'desc' => __( 'Photos and videos. The first picture is the one on product cards.', 'wp-easycart' ) ),
	'pricing'   => array( 'label' => __( 'Pricing', 'wp-easycart' ), 'icon' => 'dashicons-money-alt', 'group' => 'essentials', 'desc' => __( 'The price and compare-at price, what it costs you, how the price shows, and volume and B2B prices.', 'wp-easycart' ) ),
	/* 6.0.4: Shipping is an essential for anything that ships ( the rail and the page follow this order ). */
	'shipping'  => array( 'label' => __( 'Shipping', 'wp-easycart' ), 'icon' => 'dashicons-car', 'group' => 'essentials', 'desc' => __( 'Whether it ships, its package, what it adds to shipping, and customs.', 'wp-easycart' ) ),
	/* 6.0.5 ( Matt: tax was rolled into Pricing ): its own essential, after Shipping as checkout totals it. */
	'tax'       => array( 'label' => __( 'Tax', 'wp-easycart' ), 'icon' => 'dashicons-calculator', 'group' => 'essentials', 'desc' => __( 'Which taxes checkout charges on it, at what rate, and the code your tax service reads.', 'wp-easycart' ) ),
	'options'   => array( 'label' => __( 'Options & variants', 'wp-easycart' ), 'icon' => 'dashicons-admin-settings', 'group' => 'catalog', 'desc' => __( 'Sizes, colours and add-ons, and each variant\'s SKU, price and stock.', 'wp-easycart' ) ),
	'inventory' => array( 'label' => __( 'Inventory', 'wp-easycart' ), 'icon' => 'dashicons-archive', 'group' => 'catalog', 'desc' => __( 'How stock is counted, limits on a purchase, back-in-stock requests and stock history.', 'wp-easycart' ) ),
	'organize'  => array( 'label' => __( 'Storefront', 'wp-easycart' ), 'icon' => 'dashicons-store', 'group' => 'advanced', 'desc' => __( 'Where it shows in your store: menus, related products, specials and sort order.', 'wp-easycart' ) ),
	/* 6.0.4: the product card's badge and picture effects, which most products never use ( they were under Media ). */
	'design'    => array( 'label' => __( 'Design', 'wp-easycart' ), 'icon' => 'dashicons-art', 'group' => 'advanced', 'desc' => __( 'A badge on its product card, and how its picture reacts to the pointer.', 'wp-easycart' ) ),
	'behavior'  => array( 'label' => $ecdv2_type_section['rail'], 'title' => $ecdv2_type_section['title'], 'icon' => 'dashicons-admin-generic', 'group' => 'advanced', 'desc' => $ecdv2_type_section['desc'] ),
	'seo'       => array( 'label' => __( 'Search & marketing', 'wp-easycart' ), 'icon' => 'dashicons-search', 'group' => 'advanced', 'desc' => __( 'How it shows in search engines, AI assistants and Google Shopping, and your marketing services.', 'wp-easycart' ) ),
	'notes'     => array( 'label' => __( 'After purchase', 'wp-easycart' ), 'icon' => 'dashicons-email-alt', 'group' => 'advanced', 'desc' => __( 'Notes for customers who bought it: on the order confirmation page, in the order email and in their account.', 'wp-easycart' ) ),
	'activity'  => array( 'label' => __( 'Activity', 'wp-easycart' ), 'icon' => 'dashicons-chart-bar', 'group' => 'insights', 'new' => true, 'desc' => __( 'Sales and customers tied to this product.', 'wp-easycart' ) ),
	'offers'    => array( 'label' => __( 'Offers', 'wp-easycart' ), 'icon' => 'dashicons-megaphone', 'group' => 'insights', 'new' => true, 'desc' => __( 'Offers, coupons, and promotions that discount this product, and how each one is performing.', 'wp-easycart' ) ),
	'reviews'   => array( 'label' => __( 'Reviews', 'wp-easycart' ), 'icon' => 'dashicons-star-filled', 'group' => 'insights', 'new' => true, 'desc' => __( 'Whether shoppers can review this product, and the reviews they have left.', 'wp-easycart' ) ),
), $product );

$tab_groups = apply_filters( 'wp_easycart_admin_product_details_v2_tab_groups', array(
	'essentials' => __( 'Essentials', 'wp-easycart' ),
	'catalog'    => __( 'Catalog', 'wp-easycart' ),
	'advanced'   => __( 'Advanced', 'wp-easycart' ),
	'insights'   => __( 'Insights', 'wp-easycart' ),
) );

/*
 * 6.0.4: one editor, two views ( Page: every section on one page, the rail a map of them; Focus: one section at a time, as
 * before ), an Edit / Insights switch ( Activity, Offers and Reviews ), sections that fold in the page view, and a summary
 * column. Each person's choices are user meta ( wp_easycart_admin_product_editor_prefs() ). A panel carries is-active
 * while it is on screen, so code that waits for its panel to open ( PRO's Offers orders ) keeps working in both views.
 */
$ecdv2_prefs = function_exists( 'wp_easycart_admin_product_editor_prefs' ) ? wp_easycart_admin_product_editor_prefs() : array(
	'view'      => 'focus',
	'collapsed' => array(),
	'menu'      => 'full',
	'tip'       => true,
);
$ecdv2_view = $is_new ? 'focus' : $ecdv2_prefs['view'];
$ecdv2_mode_of = function( $key ) use ( $tabs ) {
	return ( isset( $tabs[ $key ]['group'] ) && 'insights' === $tabs[ $key ]['group'] ) ? 'insights' : 'edit';
};
/* Sections most products never need start folded in the page view; Type & Behavior stays open on a product that uses it. */
$ecdv2_typed = false;
if ( ! $is_new ) {
	foreach ( array( 'is_subscription_item', 'is_download', 'is_donation', 'is_giftcard', 'inquiry_mode', 'catalog_mode', 'is_preorder_type', 'is_restaurant_type', 'is_deconetwork' ) as $ecdv2_flag ) {
		if ( ! empty( $product->{$ecdv2_flag} ) ) {
			$ecdv2_typed = true;
		}
	}
}
$ecdv2_default_folded = array(
	'behavior' => ! $ecdv2_typed,
	'design'   => true,
	'seo'      => true,
	'notes'    => true,
	/* 6.0.4: a product that does not ship shows Shipping folded, saying so ( it used to leave the page for the rail's "Not used
	   for this type", and a merchant who switched shipping off by mistake could not see it ). */
	'shipping' => ! $is_new && empty( $product->is_shippable ),
);
$ecdv2_is_folded = function( $key ) use ( $ecdv2_prefs, $ecdv2_default_folded ) {
	if ( isset( $ecdv2_prefs['collapsed'][ $key ] ) ) {
		return (bool) $ecdv2_prefs['collapsed'][ $key ];
	}
	return ! empty( $ecdv2_default_folded[ $key ] );
};
/* Opens a panel: its heading ( title, what the section is for, a one-line summary while folded ) and fold button. */
$ecdv2_tab_order = array_flip( array_keys( $tabs ) );
/* 6.0.4 ( editor plan Phase 2 ): the product type, and the section it does not use that the page knows now ( Shipping, for a
   product that does not ship ). products-details-v2.js keeps both current and works out the rest. */
$ecdv2_type   = $is_new ? '' : $this->product_type_key_v2();
$ecdv2_unused = array(); /* 6.0.4: Shipping is never "not used": a product that does not ship says so in its Shipping section */
/* 6.0.4: $head chip ( a few words beside the title, e.g. 1 review ) and actions ( escaped markup at the right, e.g. a link to
   the section's settings ), so a section says what its first card's heading said ( Offers, Reviews ). Insights sections fold too. */
$ecdv2_open_panel = function( $key, $extra_class = '', $head = array() ) use ( $tabs, $ecdv2_tab_order, $ecdv2_view, $ecdv2_mode_of, $ecdv2_is_folded, $is_new, $ecdv2_unused ) {
	if ( in_array( $key, $ecdv2_unused, true ) ) {
		$extra_class .= ' is-unused';
	}
	$mode   = $ecdv2_mode_of( $key );
	$active = ( 'page' === $ecdv2_view ) ? ( 'edit' === $mode ) : ( 'general' === $key );
	$folded = ! $is_new && $ecdv2_is_folded( $key );
	$label  = isset( $tabs[ $key ]['title'] ) ? $tabs[ $key ]['title'] : ( isset( $tabs[ $key ]['label'] ) ? $tabs[ $key ]['label'] : $key );
	/* The panels show in the rail's order ( the template prints Offers among the editing panels ). */
	$order  = isset( $ecdv2_tab_order[ $key ] ) ? (int) $ecdv2_tab_order[ $key ] : 99;
	echo '<div class="ecdv2-panel' . ( $active ? ' is-active' : '' ) . ( $folded ? ' is-collapsed' : '' ) . esc_attr( $extra_class ) . '" data-ecdv2-panel="' . esc_attr( $key ) . '" data-ecdv2-mode="' . esc_attr( $mode ) . '" style="order:' . esc_attr( $order ) . ';" role="' . ( 'page' === $ecdv2_view ? 'region' : 'tabpanel' ) . '" aria-labelledby="ecdv2_panel_title_' . esc_attr( $key ) . '">';
	echo '<div class="ecdv2-panel-head">';
	if ( ! empty( $tabs[ $key ]['icon'] ) ) {
		echo '<span class="dashicons ' . esc_attr( $tabs[ $key ]['icon'] ) . ' ecdv2-panel-icon" aria-hidden="true"></span>';
	}
	echo '<div class="ecdv2-panel-head-text">';
	echo '<div class="ecdv2-panel-title-row"><h2 class="ecdv2-panel-title" id="ecdv2_panel_title_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</h2>';
	if ( ! empty( $head['chip'] ) ) {
		echo '<span class="ecdv2-panel-chip" id="ecdv2_panel_chip_' . esc_attr( $key ) . '">' . esc_html( $head['chip'] ) . '</span>';
	}
	echo '</div>';
	if ( ! empty( $tabs[ $key ]['desc'] ) ) {
		echo '<p class="ecdv2-panel-desc" id="ecdv2_panel_desc_' . esc_attr( $key ) . '">' . esc_html( $tabs[ $key ]['desc'] ) . '</p>';
	}
	echo '<p class="ecdv2-panel-sum" data-ecdv2-sum="' . esc_attr( $key ) . '"></p>';
	echo '</div>';
	if ( ! empty( $head['actions'] ) ) {
		echo '<div class="ecdv2-panel-actions">' . $head['actions'] . '</div>'; /* phpcs:ignore WordPress.Security.EscapeOutput -- the caller's escaped markup */
	}
	if ( ! $is_new ) {
		/* translators: %s: a section of the product editor, e.g. Pricing & Tax. */
		$toggle_title = sprintf( __( 'Fold or open %s', 'wp-easycart' ), $label );
		echo '<button type="button" class="ecdv2-panel-toggle" data-ecdv2-fold="' . esc_attr( $key ) . '" aria-expanded="' . ( $folded ? 'false' : 'true' ) . '" title="' . esc_attr( $toggle_title ) . '"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg><span class="screen-reader-text">' . esc_html( $toggle_title ) . '</span></button>';
	}
	echo '</div>';
};
?>

<input type="hidden" name="ec_admin_form_action" value="<?php echo esc_attr( $this->form_action ); ?>" />
<input type="hidden" name="product_id" id="product_id" value="<?php echo esc_attr( $is_new ? '0' : $product->product_id ); ?>" />
<?php wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_product_details_nonce', 'wp-easycart-product-details' ); ?>

<div class="ecdv2-wrap ecdv2-product ecdv2-view-<?php echo esc_attr( $ecdv2_view ); ?> ecdv2-mode-edit<?php echo $is_new ? ' ecdv2-is-new' : ''; ?>" id="ecdv2_wrap" data-view="<?php echo esc_attr( $ecdv2_view ); ?>" data-mode="edit"<?php echo '' !== $ecdv2_type ? ' data-ecdv2-type="' . esc_attr( $ecdv2_type ) . '"' : ''; ?>>

	<!-- ============ Sticky header ============ -->
	<div class="ecdv2-header">
		<a href="<?php echo esc_attr( $this->action ); ?>" class="ecdv2-header-back" title="<?php esc_attr_e( 'Back to Products', 'wp-easycart' ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
		<div class="ecdv2-header-thumb" id="ecdv2_header_thumb">
			<?php if ( '' !== $thumb_url ) { ?><img src="<?php echo esc_url( $thumb_url ); ?>" alt="" /><?php } else { ?><span class="dashicons dashicons-format-image"></span><?php } ?>
		</div>
		<div class="ecdv2-header-meta">
			<p class="ecdv2-header-title" id="ecdv2_header_title"><?php echo ( $is_new || ( $is_draft && '' === (string) $product->title ) ) ? esc_html__( 'New Product', 'wp-easycart' ) : esc_html( wp_unslash( $product->title ) ); ?></p>
			<div class="ecdv2-header-sub" id="ecdv2_header_sub">
				<?php if ( $is_draft ) { ?>
					<span class="ecdv2-header-draft"><?php esc_html_e( 'Not saved yet: nobody sees it until you save', 'wp-easycart' ); ?></span>
				<?php } else if ( ! $is_new ) { ?>
					<span id="ecdv2_header_sku"><?php echo esc_html( wp_unslash( $product->model_number ) ); ?></span>
					&middot; <span id="ecdv2_header_price"><?php echo esc_html( $this->format_price( $product->price ) ); ?></span>
					<?php /* 6.0.2: always printed ( hidden while stock is not tracked ), so the header follows a saved Track Quantity change. */ ?>
					<span id="ecdv2_header_stock_wrap"<?php echo ( $product->show_stock_quantity || $product->use_optionitem_quantity_tracking ) ? '' : ' style="display:none;"'; ?>>
						&middot; <span class="<?php echo ( (int) $product->stock_quantity > 0 || $product->use_optionitem_quantity_tracking ) ? 'ecdv2-instock' : 'ecdv2-outstock'; ?>" id="ecdv2_header_stock" data-text-variant="<?php esc_attr_e( 'Variant stock', 'wp-easycart' ); ?>" data-text-in="<?php /* translators: %d: units in stock. */ esc_attr_e( '%d in stock', 'wp-easycart' ); ?>" data-text-out="<?php esc_attr_e( 'Out of stock', 'wp-easycart' ); ?>"><?php
							if ( $product->use_optionitem_quantity_tracking ) {
								esc_attr_e( 'Variant stock', 'wp-easycart' );
							} else if ( (int) $product->stock_quantity > 0 ) {
								echo esc_html( sprintf( __( '%d in stock', 'wp-easycart' ), (int) $product->stock_quantity ) );
							} else {
								esc_attr_e( 'Out of stock', 'wp-easycart' );
							}
						?></span>
					</span>
				<?php } else { ?>
					<?php esc_attr_e( 'Fill in the essentials, then save to unlock all sections', 'wp-easycart' ); ?>
				<?php } ?>
			</div>
		</div>

		<?php /* 6.0.4 ( editor plan Phase 2 ): the switch saves only itself, at once ( ecv2_product_toggle_status, as on the list ).
		   6.0.4: it lives in the summary column's Status card ( print_side_v2() ); the header keeps the status as a button to it. */ ?>
		<?php if ( ! $is_new ) { ?>
		<div class="ecdv2-status-toggle">
			<button type="button" class="ecdv2-status-pill<?php echo $product->activate_in_store ? ' is-active' : ''; ?>" id="ecdv2_status_pill" onclick="ecdv2.go_tab( 'status' );" title="<?php esc_attr_e( 'Switch it on or off under Status', 'wp-easycart' ); ?>"><?php echo $product->activate_in_store ? esc_html__( 'Active', 'wp-easycart' ) : esc_html__( 'Draft', 'wp-easycart' ); ?></button>
			<a class="ecdv2-status-pill ecdv2-restricted-pill" id="ecdv2_restricted_pill" href="#"<?php echo ( (int) $product->role_id > 0 ) ? '' : ' style="display:none;"'; ?> onclick="ecdv2.go_tab( 'status' ); return false;" title="<?php esc_attr_e( 'This product is only visible to one user role. Everyone else cannot see it in your store.', 'wp-easycart' ); ?>"><span class="dashicons dashicons-lock"></span><?php esc_html_e( 'Role restricted', 'wp-easycart' ); ?></a>
		</div>
		<?php } else { ?>
		<label class="ecdv2-status-toggle ecdv2-requires-save" title="<?php esc_attr_e( 'Shows or hides the product in your store. Saves at once; your other changes wait for Save.', 'wp-easycart' ); ?>">
			<span class="ecdv2-toggle">
				<input type="checkbox" id="activate_in_store" name="activate_in_store" value="1"<?php checked( ! $is_new && $product->activate_in_store ); ?><?php echo $is_new ? '' : ' data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-toggle-' . (int) $product->product_id ) ) . '"'; ?> onchange="ecdv2.quick_activate( this );" />
				<span class="ecdv2-toggle-track"></span>
			</span>
			<span class="ecdv2-status-pill<?php echo ( ! $is_new && $product->activate_in_store ) ? ' is-active' : ''; ?>" id="ecdv2_status_pill"><?php echo ( ! $is_new && $product->activate_in_store ) ? esc_html__( 'Active', 'wp-easycart' ) : esc_html__( 'Draft', 'wp-easycart' ); ?></span>
		</label>
		<?php } ?>

		<div class="ecdv2-header-spacer"></div>
		<span class="ecdv2-dirty-pill" id="ecdv2_dirty_pill"><?php esc_attr_e( 'Unsaved changes', 'wp-easycart' ); ?></span>

		<?php if ( ! $is_new ) { ?>
			<?php /* 6.0.4: Edit / Insights, Page / Focus, and the previous / next product of the list the merchant came from. */ ?>
			<div class="ecdv2-seg ecdv2-mode-switch" role="group" aria-label="<?php esc_attr_e( 'Edit the product or see how it is doing', 'wp-easycart' ); ?>">
				<button type="button" class="ecdv2-seg-btn" data-ecdv2-mode-set="edit" aria-pressed="true" title="<?php esc_attr_e( 'Edit this product', 'wp-easycart' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="ecdv2-seg-label"><?php esc_html_e( 'Edit', 'wp-easycart' ); ?></span></button>
				<button type="button" class="ecdv2-seg-btn" data-ecdv2-mode-set="insights" aria-pressed="false" title="<?php esc_attr_e( 'Sales, offers and reviews for this product', 'wp-easycart' ); ?>"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span><span class="ecdv2-seg-label"><?php esc_html_e( 'Insights', 'wp-easycart' ); ?></span></button>
			</div>
			<div class="ecdv2-seg ecdv2-view-switch" role="group" aria-label="<?php esc_attr_e( 'Layout', 'wp-easycart' ); ?>">
				<button type="button" class="ecdv2-seg-btn" data-ecdv2-view-set="page" aria-pressed="<?php echo 'page' === $ecdv2_view ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Page: every section on one page', 'wp-easycart' ); ?>"><span class="dashicons dashicons-list-view" aria-hidden="true"></span><span class="ecdv2-seg-label"><?php esc_html_e( 'Page', 'wp-easycart' ); ?></span></button>
				<button type="button" class="ecdv2-seg-btn" data-ecdv2-view-set="focus" aria-pressed="<?php echo 'focus' === $ecdv2_view ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Focus: one section at a time', 'wp-easycart' ); ?>"><span class="dashicons dashicons-index-card" aria-hidden="true"></span><span class="ecdv2-seg-label"><?php esc_html_e( 'Focus', 'wp-easycart' ); ?></span></button>
			</div>
			<?php if ( empty( $ecdv2_prefs['tip'] ) ) { ?>
				<div class="ecdv2-view-tip" id="ecdv2_view_tip" role="note" hidden>
					<b><?php esc_html_e( 'Every section on one page', 'wp-easycart' ); ?></b>
					<span><?php esc_html_e( 'Scroll, or use the list on the left to jump. Prefer one section at a time? Choose Focus. We remember your choice.', 'wp-easycart' ); ?></span>
					<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecdv2_view_tip_ok"><?php esc_html_e( 'Got it', 'wp-easycart' ); ?></button>
				</div>
			<?php } ?>
			<div class="ecdv2-nav" id="ecdv2_nav" hidden>
				<a class="ecv2-btn ecdv2-nav-btn" id="ecdv2_nav_prev" href="#" aria-label="<?php esc_attr_e( 'Previous product', 'wp-easycart' ); ?>" title="<?php esc_attr_e( 'Previous product ( K )', 'wp-easycart' ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></a>
				<span class="ecdv2-nav-pos" id="ecdv2_nav_pos"></span>
				<a class="ecv2-btn ecdv2-nav-btn" id="ecdv2_nav_next" href="#" aria-label="<?php esc_attr_e( 'Next product', 'wp-easycart' ); ?>" title="<?php esc_attr_e( 'Next product ( J )', 'wp-easycart' ); ?>"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
			</div>
		<?php } ?>

		<div class="ecdv2-header-actions">
			<?php if ( ! $is_new ) { ?>
				<?php /* 6.0.4: below 960px of the editor's width the summary column is a drawer, and this opens it ( the count is the
				   listing health items left to finish ). Hidden while the column sits beside the fields. */ ?>
				<button type="button" class="ecv2-btn ecdv2-side-toggle" id="ecdv2_side_toggle" aria-controls="ecdv2_side" aria-expanded="false" title="<?php esc_attr_e( 'Status, type, shipping, tax, categories and listing health', 'wp-easycart' ); ?>">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M15 4v16"/></svg>
					<span class="ecdv2-side-toggle-label"><?php esc_html_e( 'Summary', 'wp-easycart' ); ?></span>
					<span class="ecdv2-side-toggle-count" id="ecdv2_side_toggle_count" hidden><span id="ecdv2_side_toggle_num"></span><span class="screen-reader-text"> <?php esc_html_e( 'to finish', 'wp-easycart' ); ?></span></span>
				</button>
			<?php } ?>
			<?php do_action( 'wp_easycart_admin_product_details_buttons_pre', $product ); ?>
			<a href="<?php echo esc_attr( $is_new || $is_draft ? '#' : wp_easycart_admin_products()->get_product_link( $product->product_id ) ); ?>" target="_blank" class="ecv2-btn ecdv2-requires-save" id="ec_admin_product_details_view_product_link"<?php echo $is_draft ? ' hidden' : ''; ?>><span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span> <?php esc_attr_e( 'View', 'wp-easycart' ); ?></a>

			<div class="ecdv2-menu-wrap ecdv2-requires-save">
				<button type="button" class="ecv2-btn" onclick="ecdv2.menu_toggle( this );" aria-label="<?php esc_attr_e( 'More actions', 'wp-easycart' ); ?>"><span class="dashicons dashicons-ellipsis" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span></button>
				<div class="ecdv2-menu" id="ecdv2_header_menu">
					<?php if ( ! $is_new ) { ?>
					<button type="button" id="ecdv2_menu_save_next" hidden onclick="ecdv2.menu_close(); ecdv2.save_next();"><span class="dashicons dashicons-controls-forward"></span><?php esc_html_e( 'Save and open the next product', 'wp-easycart' ); ?></button>
					<?php } ?>
					<?php /* Quick add, on this page ( loaded above ); the link opens it on the products list without script. */ ?>
					<a href="<?php echo esc_url( wp_easycart_admin_product_new_url() ); ?>" id="ec_admin_product_details_add_new_button" onclick="ecdv2.menu_close(); wp_easycart_admin_open_slideout( 'new_product_box' ); return false;"><span class="dashicons dashicons-plus-alt2"></span><?php esc_attr_e( 'Add New Product', 'wp-easycart' ); ?></a>
					<?php if ( ! $is_new && ! $is_draft ) { ?>
					<?php /* 6.0.4: the nonce process_duplicate_product() checks ( wp_easycart_nonce, as the list's Duplicate builds it ); a _wpnonce link did nothing. */ ?>
					<a href="<?php echo esc_url( 'admin.php?page=wp-easycart-products&subpage=products&product_id=' . (int) $product->product_id . '&ec_admin_form_action=duplicate-product&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-action-duplicate-product' ) ); ?>" id="ecdv2_duplicate_product"><span class="dashicons dashicons-admin-page"></span><?php esc_attr_e( 'Duplicate Product', 'wp-easycart' ); ?></a>
					<?php } ?>
					<div class="ecdv2-menu-sep"></div>
					<?php
					/*
					 * The QR action and the help system print legacy markup (a bare
					 * QR image, an icon-less "Video" link) that reads as clutter
					 * inside this menu. Capture both and re-emit them as normal
					 * menu items: the QR tucks behind a "View on phone" row that
					 * expands in place, the video becomes "Watch help video".
					 */
					ob_start();
					do_action( 'wp_easycart_admin_product_details_qr_code', $is_new ? 0 : $product->product_id );
					$ecdv2_qr_html = trim( (string) ob_get_clean() );
					if ( '' !== $ecdv2_qr_html ) {
						$ecdv2_qr_slug = sanitize_title( '' !== trim( (string) $product->model_number ) ? $product->model_number : 'product-' . (int) $product->product_id );
						echo '<a href="#" onclick="jQuery( \'#ecdv2_menu_qr\' ).slideToggle( 120 ); return false;"><span class="dashicons dashicons-smartphone"></span>' . esc_html__( 'Product QR code', 'wp-easycart' ) . '</a>';
						echo '<div class="ecdv2-menu-qr" id="ecdv2_menu_qr" style="display:none;" data-qr-filename="' . esc_attr( 'qr-' . $ecdv2_qr_slug . '.png' ) . '">';
						echo $ecdv2_qr_html; /* phpcs:ignore WordPress.Security.EscapeOutput -- QR markup from the plugin's own action */
						echo '<span class="ecdv2-menu-qr-hint">' . esc_html__( 'Links to this product page. Download the PNG for packaging, flyers, or other marketing material.', 'wp-easycart' ) . '</span>';
						echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecdv2-qr-download" onclick="ecdv2.download_qr( this ); return false;"><span class="dashicons dashicons-download"></span>' . esc_html__( 'Download PNG', 'wp-easycart' ) . '</button>';
						echo '</div>';
					}

					ob_start();
					wp_easycart_admin()->helpsystem->print_vids_url( 'products', 'products', 'details' );
					$ecdv2_vids_html = trim( (string) ob_get_clean() );
					$ecdv2_vids_href = '';
					$ecdv2_vids_onclick = '';
					if ( '' !== $ecdv2_vids_html && preg_match( '/<a\s[^>]*>/i', $ecdv2_vids_html, $ecdv2_vids_tag ) ) {
						if ( preg_match( '/href=(["\'])(.*?)\1/i', $ecdv2_vids_tag[0], $ecdv2_m ) ) {
							$ecdv2_vids_href = html_entity_decode( $ecdv2_m[2] );
						}
						if ( preg_match( '/onclick=(["\'])(.*?)\1/i', $ecdv2_vids_tag[0], $ecdv2_m ) ) {
							$ecdv2_vids_onclick = html_entity_decode( $ecdv2_m[2] );
						}
					}
					if ( '' !== $ecdv2_vids_href || '' !== $ecdv2_vids_onclick ) {
						$ecdv2_vids_target = ( '' !== $ecdv2_vids_href && '#' !== $ecdv2_vids_href && false === strpos( $ecdv2_vids_onclick, 'video_help' ) ) ? ' target="_blank" rel="noopener"' : '';
						echo '<a href="' . esc_url( '' !== $ecdv2_vids_href ? $ecdv2_vids_href : '#' ) . '"' . $ecdv2_vids_target . ' onclick="ecdv2.menu_close(); ' . esc_attr( rtrim( $ecdv2_vids_onclick, '; ' ) ) . ( '' !== $ecdv2_vids_onclick ? ';' : '' ) . ( '' === $ecdv2_vids_href || '#' === $ecdv2_vids_href ? ' return false;' : '' ) . '"><span class="dashicons dashicons-video-alt3"></span>' . esc_html__( 'Watch help video', 'wp-easycart' ) . '</a>';
					}
					?>
					<a href="<?php echo esc_url_raw( $this->docs_link ); ?>" target="_blank"><span class="dashicons dashicons-editor-help"></span><?php esc_attr_e( 'Documentation', 'wp-easycart' ); ?></a>
					<?php do_action( 'wp_easycart_admin_product_details_v2_header_menu', $product ); ?>
				</div>
			</div>

			<?php if ( ! $is_new ) { ?>
				<?php /* 6.0.4 ( editor plan Phase 2 ): puts every field back as it was saved ( a reload ). */ ?>
				<button type="button" class="ecv2-btn ecdv2-discard-btn" id="ecdv2_discard_btn" onclick="ecdv2.discard();" hidden><?php esc_html_e( 'Discard', 'wp-easycart' ); ?></button>
			<?php } ?>
			<button type="button" class="ecv2-btn ecv2-btn-primary ecdv2-save-btn" id="ecdv2_save_btn" onclick="ecdv2.save_all();">
				<span class="ecdv2-save-spin"></span>
				<span class="ecdv2-save-label"><?php echo $is_new ? esc_html__( 'Create', 'wp-easycart' ) : esc_html__( 'Save', 'wp-easycart' ); ?></span>
			</button>
		</div>
	</div>

	<?php if ( get_option( 'ec_option_display_as_catalog' ) ) { ?>
		<div class="ecdv2-newproduct-note"><span class="dashicons dashicons-info" style="color:var(--ecv2-blue);"></span><span><?php echo sprintf( esc_attr__( 'Your store is in catalog mode and all cart features are disabled. %1$sVisit product settings%2$s to re-enable your shopping cart.', 'wp-easycart' ), '<a href="admin.php?page=wp-easycart-settings&subpage=products">', '</a>' ); ?></span></div>
	<?php } ?>
	<?php if ( $is_new ) { ?>
		<div class="ecdv2-newproduct-note"><span class="dashicons dashicons-info" style="color:var(--ecv2-blue);"></span><span><?php esc_attr_e( 'Start with a title, SKU, and price. Once you create the product, every other section unlocks automatically.', 'wp-easycart' ); ?></span></div>
	<?php } ?>

	<?php do_action( 'wp_easycart_admin_product_details_sections_pre' ); ?>

	<!-- ============ Body: rail + panels ============ -->
	<div class="ecdv2-body">

		<nav class="ecdv2-rail" aria-label="<?php esc_attr_e( 'Product editor sections', 'wp-easycart' ); ?>"<?php echo $is_new ? '' : ' data-mode="edit" data-view="' . esc_attr( $ecdv2_view ) . '"'; ?>>
			<div class="ecdv2-rail-search ecdv2-requires-save">
				<span class="dashicons dashicons-search"></span>
				<input type="text" id="ecdv2_search" placeholder="<?php esc_attr_e( 'Find a setting...', 'wp-easycart' ); ?>" autocomplete="off" />
			</div>
			<div class="ecdv2-search-results" id="ecdv2_search_results"></div>
			<?php if ( ! $is_new ) { ?>
				<?php /* 6.0.4: the page view's sections all at once. */ ?>
				<div class="ecdv2-rail-tools" role="group" aria-label="<?php esc_attr_e( 'All sections', 'wp-easycart' ); ?>">
					<button type="button" class="ecdv2-rail-tool" data-ecdv2-fold-all="0"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 15 5 5 5-5M7 9l5-5 5 5"/></svg><?php esc_html_e( 'Open all', 'wp-easycart' ); ?></button>
					<button type="button" class="ecdv2-rail-tool" data-ecdv2-fold-all="1"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 20 5-5 5 5M7 4l5 5 5-5"/></svg><?php esc_html_e( 'Fold all', 'wp-easycart' ); ?></button>
				</div>
			<?php } ?>
			<div class="ecdv2-tabs" id="ecdv2_tabs" role="tablist">
				<?php $ecdv2_current_group = ''; ?>
				<?php foreach ( $tabs as $tab_key => $tab ) { ?>
					<?php
					$ecdv2_group = isset( $tab['group'] ) ? $tab['group'] : '';
					$ecdv2_tab_mode = $ecdv2_mode_of( $tab_key );
					if ( $ecdv2_group !== $ecdv2_current_group && isset( $tab_groups[ $ecdv2_group ] ) ) {
						$ecdv2_current_group = $ecdv2_group;
						echo '<div class="ecdv2-rail-group" data-ecdv2-mode="' . esc_attr( $ecdv2_tab_mode ) . '">' . esc_html( $tab_groups[ $ecdv2_group ] ) . '</div>';
					}
					?>
					<button type="button" role="tab" class="ecdv2-tab<?php echo ( 'general' === $tab_key ) ? ' is-active' : ''; ?><?php echo ( 'general' !== $tab_key ) ? ' ecdv2-requires-save' : ''; ?>" data-ecdv2-tab="<?php echo esc_attr( $tab_key ); ?>" data-ecdv2-mode="<?php echo esc_attr( $ecdv2_tab_mode ); ?>" onclick="ecdv2.go_tab( '<?php echo esc_attr( $tab_key ); ?>' );">
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<span class="ecdv2-tab-label"><?php echo esc_html( $tab['label'] ); ?></span>
						<?php if ( ! empty( $tab['new'] ) ) { ?><span class="ecdv2-tab-new"><?php esc_attr_e( 'NEW', 'wp-easycart' ); ?></span><?php } ?>
						<span class="ecdv2-tab-todo-dot" title="<?php esc_attr_e( 'Something to finish here', 'wp-easycart' ); ?>"></span>
						<span class="ecdv2-tab-dirty-dot" title="<?php esc_attr_e( 'Unsaved changes', 'wp-easycart' ); ?>"></span>
					</button>
				<?php } ?>
			</div>
		</nav>

		<div class="ecdv2-panel-col">

			<?php if ( ! $is_new ) { ?>
				<?php /* 6.0.4: Insights says what it is and how to get back to the fields ( the header's switch alone was easy to miss ). */ ?>
				<div class="ecdv2-insights-bar" id="ecdv2_insights_bar">
					<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
					<span class="ecdv2-insights-bar-text"><strong><?php esc_html_e( 'Insights', 'wp-easycart' ); ?></strong> <?php esc_html_e( 'How this product is doing: its sales, the offers that discount it and its reviews.', 'wp-easycart' ); ?></span>
					<button type="button" class="ecv2-btn ecv2-btn-primary ecdv2-insights-back" data-ecdv2-mode-set="edit"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><?php esc_html_e( 'Back to editing', 'wp-easycart' ); ?></button>
				</div>
			<?php } ?>

			<?php /* 6.0.4 ( editor plan Phase 2 ): what kept the last Save from finishing, each with a way to its field. */ ?>
			<div class="ecdv2-errors" id="ecdv2_errors" role="alert" tabindex="-1" hidden>
				<div class="ecdv2-errors-head">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<strong id="ecdv2_errors_title"><?php esc_html_e( 'Some changes were not saved', 'wp-easycart' ); ?></strong>
					<button type="button" class="ecdv2-errors-close" onclick="ecdv2.errors_hide();" aria-label="<?php esc_attr_e( 'Hide this list', 'wp-easycart' ); ?>">&times;</button>
				</div>
				<ul class="ecdv2-errors-list" id="ecdv2_errors_list"></ul>
			</div>

			<!-- ===== GENERAL ===== -->
			<?php $ecdv2_open_panel( 'general' ); ?>
				<?php /* 6.0.4 ( editor plan Phase 2 ): the price leads Pricing ( a second card of the basic section, below ); a new
				   product still asks for it here, since only this panel shows until it is created. */ ?>
				<?php /* 6.0.4: the description moved to the Product page text card below ( a new product still writes it here ). */ ?>
				<?php /* 6.0.4: an existing product's page address and manufacturer are in the summary column ( print_side_v2() ). */ ?>
				<?php $this->section_open( 'basic', __( 'Product details', 'wp-easycart' ), $is_new ? __( 'Its name, SKU, page address and manufacturer', 'wp-easycart' ) : '', array( 'except' => $is_new ? array( 'activate_in_store' ) : array( 'activate_in_store', 'price', 'list_price', 'description', 'post_slug', 'manufacturer_id' ), 'heading' => $is_new ) ); ?>
					<?php /* 6.0.1: inside the card, so a cover ( PRO: Square product sync ) spans Product Details only, not the
					   Short Description and Specifications cards Square never writes. */ ?>
					<?php do_action( 'wp_easycart_admin_product_details_basic_start', $product ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_basic_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php if ( ! $is_new ) { ?>
					<?php
					/* 6.0.4: the price ( Pricing ) and the manufacturer ( the summary column ) were under the title before; a quick
					   link to each says what it is and takes the merchant there ( products-details-v2.js keeps the values current ). */
					$this->print_quick_links_v2();
					?>
					<?php
					/* 6.0.4: the description, short description, specifications and the Tabs extension's tabs do one job ( what
					   shoppers read ), so they share one card with tabs. Each tab is still its own save section; the description
					   is a third card of the basic section ( like the Price card ), so it saves with Product details. */
					$ecdv2_tabs_card = '';
					if ( class_exists( 'wp_easycart_admin_extensions' ) ) {
						ob_start();
						wp_easycart_admin_extensions::print_product_tabs_card( $product ); /* 6.0.2: the Tabs extension ( Premium ): its tabs for this product, or what Premium adds. */
						$ecdv2_tabs_card = trim( (string) ob_get_clean() );
					}
					$ecdv2_is_blank = function( $html ) {
						return '' === trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES ) );
					};
					$ecdv2_text_tabs = array(
						'description'       => array( 'label' => __( 'Description', 'wp-easycart' ), 'empty' => $ecdv2_is_blank( $product->description ), 'check' => 'description' ),
						'short_description' => array( 'label' => __( 'Short description', 'wp-easycart' ), 'empty' => $ecdv2_is_blank( $product->short_description ), 'check' => 'short_description' ),
						'specifications'    => array( 'label' => __( 'Specifications', 'wp-easycart' ), 'empty' => $ecdv2_is_blank( $product->specifications ), 'check' => 'specifications' ),
					);
					/* 6.0.4: the Tabs extension's own card ( installed and active ) lists every tab of the product page in order, so it
					   sits above the text it orders; its upsell or install prompt stays a tab. */
					$ecdv2_tabs_live = ( '' !== $ecdv2_tabs_card && false !== strpos( $ecdv2_tabs_card, 'wpec-tabs-card' ) );
					if ( $ecdv2_tabs_live ) {
						echo $ecdv2_tabs_card; /* phpcs:ignore WordPress.Security.EscapeOutput -- the Tabs extension's own card */
					}
					if ( '' !== $ecdv2_tabs_card && ! $ecdv2_tabs_live ) {
						$ecdv2_text_tabs['more_tabs'] = array(
							'label' => __( 'More tabs', 'wp-easycart' ),
							'chip'  => ( false !== strpos( $ecdv2_tabs_card, 'ecv2-cl-pro-badge' ) ) ? '<span class="ecv2-cl-pro-badge">' . esc_html__( 'Premium', 'wp-easycart' ) . '</span>' : '',
						);
					}
					$this->tabset_open( 'content', __( 'Product page text', 'wp-easycart' ), __( 'What shoppers read about it', 'wp-easycart' ), $ecdv2_text_tabs, array( 'heading' => false ) );
					$this->pane_open( 'description' );
					$this->section_open( 'basic', __( 'Description', 'wp-easycart' ), __( 'The main text on the product page.', 'wp-easycart' ), array( 'only' => array( 'description' ), 'hidden' => false, 'pane' => true ) );
					do_action( 'wp_easycart_admin_product_details_basic_start', $product );
					do_action( 'wp_easycart_admin_product_details_basic_fields' );
					$this->section_close();
					$this->pane_close();
					$this->pane_open( 'short_description' );
					$this->section_open( 'short_description', __( 'Short description', 'wp-easycart' ), __( 'A line or two shown on product listings and grids.', 'wp-easycart' ), array( 'pane' => true ) );
					do_action( 'wp_easycart_admin_product_details_short_description_fields' );
					$this->section_close();
					$this->pane_close();
					$this->pane_open( 'specifications' );
					$this->section_open( 'specifications', __( 'Specifications', 'wp-easycart' ), __( 'Shown in a Specifications tab on the product page when it has text.', 'wp-easycart' ), array( 'pane' => true ) );
					do_action( 'wp_easycart_admin_product_details_specifications_fields' );
					$this->section_close();
					$this->pane_close();
					if ( '' !== $ecdv2_tabs_card && ! $ecdv2_tabs_live ) {
						$this->pane_open( 'more_tabs' );
						echo $ecdv2_tabs_card; /* phpcs:ignore WordPress.Security.EscapeOutput -- print_product_tabs_card() escapes its parts, or it is the Tabs extension's own card */
						$this->pane_close();
					}
					$this->tabset_close();
					?>
				<?php } else { ?>
					<div class="ecdv2-requires-save">
						<?php $this->section_open( 'short_description', __( 'Short description', 'wp-easycart' ), __( 'Shown on product listings and grids', 'wp-easycart' ) ); ?>
							<?php do_action( 'wp_easycart_admin_product_details_short_description_fields' ); ?>
						<?php $this->section_close(); ?>

						<?php $this->section_open( 'specifications', __( 'Specifications', 'wp-easycart' ), __( 'Optional specs tab on the product page', 'wp-easycart' ) ); ?>
							<?php do_action( 'wp_easycart_admin_product_details_specifications_fields' ); ?>
						<?php $this->section_close(); ?>
					</div>
				<?php } ?>
			</div>

			<!-- ===== MEDIA ===== -->
			<?php $ecdv2_open_panel( 'media', ' ecdv2-requires-save' ); ?>
				<?php if ( $has_pro_media_v2 ) { ?>
					<?php /* 6.0.1: wrapped so the panel can be refetched after the option slots change, instead of
					   showing the choices that existed when the page loaded ( products-details-v2.js ). */ ?>
					<div id="ecdv2_media_pro_wrap">
						<?php do_action( 'wp_easycart_admin_product_details_v2_media_pro', $product ); ?>
					</div>
				<?php } else if ( $ec_legacy_pro ) { ?>
					<?php /* 6.0.4: bare, since PRO prints its own cards here ( a card around them drew a card in a card ). */ ?>
					<div class="ecdv2-card ecdv2-card-bare"><div class="ecdv2-card-body ecdv2-legacy-pro" id="ecdv2_legacy_pro_media">
						<?php do_action( 'wp_easycart_admin_product_details_after_images' ); ?>
						<?php /* 6.0.1: the panel PRO prints here is the one products-details-v2.js refetches after the option sets
						   change; this branch is the one current PRO takes ( its V2 hook is added before the editor class
						   it checks for is loaded ), so it carries the same wrapper as the branch above. */ ?>
						<div id="ecdv2_media_pro_wrap">
							<?php $ec_legacy_pro->load_images_pro(); ?>
						</div>
					</div></div>
				<?php } else if ( $has_pro_media_legacy ) { ?>
					<div class="ecdv2-card"><div class="ecdv2-card-body ecdv2-legacy-pro" id="ecdv2_legacy_pro_media">
						<?php do_action( 'wp_easycart_admin_product_details_after_images' ); ?>
					</div></div>
				<?php } else { ?>
					<?php /* Free edition: real image slots + a PRO gallery preview ( replaces the legacy text fields and locked h3 block ). */ ?>
					<?php $this->print_free_media_v2(); ?>
				<?php } ?>
			</div>

			<!-- ===== PRICING ===== -->
			<?php $ecdv2_open_panel( 'pricing', ' ecdv2-requires-save' ); ?>
				<?php if ( ! $is_new ) { ?>
					<?php /* 6.0.4 ( editor plan Phase 2 ): the price and compare-at price lead Pricing, with every other place the
					   price is set ( variants, tiers, roles ) listed under them. It is the basic section's second card: it saves
					   with Product Details ( same endpoint, same element ids ), and its fields, cover and lock print only here. */ ?>
					<?php $this->section_open( 'basic', __( 'Price', 'wp-easycart' ), __( 'What the product sells for, and the price shown crossed out beside it', 'wp-easycart' ), array( 'only' => array( 'price', 'list_price' ), 'hidden' => false ) ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_basic_start', $product ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_basic_fields' ); ?>
					<?php $this->section_close(); ?>
					<?php /* 6.0.5: what shoppers see under a reduced price, and the price before a reduction that began before 6.0.5. */ ?>
					<?php if ( class_exists( 'wp_easycart_admin_price_history' ) ) { wp_easycart_admin_price_history::print_card( $product ); } ?>
				<?php } ?>
				<?php do_action( 'wp_easycart_admin_product_details_pricing_start', $product ); ?>
				<?php $this->section_open( 'pricing', __( 'Cost and price display', 'wp-easycart' ), __( 'What one item costs you, and how the price shows to shoppers', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_pricing_fields', $product ); ?>
				<?php $this->section_close(); ?>

				<?php /* 6.0.4: volume pricing and role prices wait for Save ( section price_rules ). */ ?>
				<div class="ecdv2-card" id="ecdv2_advanced_pricing"<?php echo $is_new ? '' : ' data-ecdv2-section="price_rules"'; ?>><div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Volume and B2B prices', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint"><?php esc_html_e( 'Lower prices for larger quantities, or for a customer role', 'wp-easycart' ); ?></span></div><div class="ecdv2-card-body">
					<?php do_action( 'wp_easycart_admin_product_details_advanced_pricing_fields' ); ?>
				</div></div>

				<?php /* 6.0.5: tax has its own section ( Tax, after Shipping ). */ ?>
			</div>

			<!-- ===== OFFERS ===== -->
			<?php $ecdv2_open_panel( 'offers', ' ecdv2-requires-save' ); ?>
				<?php /* Offers engine is PRO: PRO prints the live list, free prints a locked preview. */ ?>
				<?php $this->print_offers_v2(); ?>
				<?php do_action( 'wp_easycart_admin_product_details_v2_offers_end', $product ); ?>
			</div>

			<!-- ===== OPTIONS & VARIANTS ===== -->
			<?php $ecdv2_open_panel( 'options', ' ecdv2-requires-save' ); ?>
				<?php if ( $has_pro_options_v2 ) { ?>
					<?php do_action( 'wp_easycart_admin_product_details_v2_options_pro', $product ); ?>
				<?php } else if ( $ec_legacy_pro ) { ?>
					<div class="ecdv2-card ecdv2-card-bare"><div class="ecdv2-card-body ecdv2-legacy-pro" id="ecdv2_legacy_pro_options">
						<?php $ec_legacy_pro->load_options_pro(); ?>
					</div></div>
				<?php } else { ?>
					<?php /* Free edition: two option-set slots with a live variation preview + a PRO options preview. */ ?>
					<?php $this->print_free_options_v2(); ?>
				<?php } ?>
			</div>

			<!-- ===== INVENTORY ===== -->
			<?php $ecdv2_open_panel( 'inventory', ' ecdv2-requires-save' ); ?>
				<?php do_action( 'wp_easycart_admin_product_details_quantity_start', $product ); ?>
				<?php $this->section_open( 'quantities', __( 'Stock and order limits', 'wp-easycart' ), __( 'How many you have, and how many one order may buy', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_quantity_fields', $product ); ?>
					<div class="ecdv2-variant-stock-note" id="ecdv2_variant_stock_note" style="display:none;">
						<span class="dashicons dashicons-info-outline"></span>
						<span class="ecdv2-variant-stock-note-text"><?php esc_attr_e( 'Stock is tracked per variation, so the overall total above is calculated automatically. Set each variation\'s quantity in the Variations table.', 'wp-easycart' ); ?></span>
						<button type="button" class="ecdv2-variant-stock-link" onclick="ecdv2.go_tab( 'options' );"><?php esc_attr_e( 'Edit Variation Stock', 'wp-easycart' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></button>
					</div>
				<?php $this->section_close(); ?>
				<?php if ( ! $is_new ) { ?>
					<?php /* 6.0.4: backorders are about stock, so they sit here; the card is the shipping section's second card ( it
					   saves with Shipping, under the same ids ). */ ?>
					<?php $this->section_open( 'shipping', __( 'Backorders', 'wp-easycart' ), __( 'Keep selling when it is out of stock', 'wp-easycart' ), array( 'only' => array( 'allow_backorders', 'backorder_fill_date' ), 'hidden' => false ) ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_shipping_fields' ); ?>
					<?php $this->section_close(); ?>
				<?php } ?>
				<?php
				/* PRO injects variation inventory + stock-notification management
				 * here; the free plugin prints nothing. Buffer it so an empty
				 * action doesn't leave a blank card on the page. */
				ob_start();
				do_action( 'wp_easycart_admin_product_details_optionitem_quantity_fields' );
				$ecdv2_optionitem_quantity = trim( ob_get_clean() );
				if ( '' !== $ecdv2_optionitem_quantity ) {
					?>
					<div class="ecdv2-card" id="ecdv2_optionitem_quantity_wrap">
						<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_attr_e( 'Variation Inventory', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint"><?php esc_attr_e( 'Per-variant stock and notification tools', 'wp-easycart' ); ?></span></div>
						<div class="ecdv2-card-body ecdv2-legacy-pro"><?php echo $ecdv2_optionitem_quantity; /* phpcs:ignore WordPress.Security.EscapeOutput */ ?></div>
					</div>
				<?php } ?>

				<?php /* Stock change history: live from PRO ( ec_inventory_log ), a locked preview otherwise. */ ?>
				<?php $this->print_stock_history_v2(); ?>
			</div>

			<!-- ===== SHIPPING ===== -->
			<?php
			/* 6.0.4: one box. The Ships to the customer switch leads; when it is off the rest folds away behind a note, and the
			   section starts folded saying so ( products-details-v2.js keeps .is-noship current ). The groups ( Package, Charges,
			   Where it goes, Customs ) hold header-less cards of the shipping, packaging and customs sections, which save as before. */
			$ecdv2_packs_ready = class_exists( 'wp_easycart_packages' ) && wp_easycart_packages::ready();
			$ecdv2_open_panel( 'shipping', ' ecdv2-requires-save' . ( ( ! $is_new && empty( $product->is_shippable ) ) ? ' is-noship' : '' ) );
			$this->section_open( 'shipping', __( 'Ships to the customer', 'wp-easycart' ), '', array( 'only' => array( 'is_shippable' ), 'heading' => false, 'class' => 'ecdv2-ship-master' ) );
			do_action( 'wp_easycart_admin_product_details_shipping_fields' );
			$this->section_close();
			?>
				<div class="ecdv2-ship-off" id="ecdv2_ship_off" role="note">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<span><?php esc_html_e( 'This product doesn’t ship. Checkout won’t ask for a shipping address or add shipping for it. Its weight, size and customs details are kept in case you turn shipping back on.', 'wp-easycart' ); ?></span>
				</div>
				<div class="ecdv2-ship-body" id="ecdv2_ship_body">
					<?php
					$this->group_open( __( 'Package', 'wp-easycart' ), __( 'As it leaves you, packed. Live rates and boxes use it.', 'wp-easycart' ), array( 'id' => 'ecdv2_ship_package' ) );
					$this->section_open( 'packaging', __( 'Weight and size', 'wp-easycart' ), '', array( 'heading' => false ) );
					do_action( 'wp_easycart_admin_product_details_packaging_fields' );
					$this->section_close();
					if ( $ecdv2_packs_ready ) {
						$this->section_open( 'customs', __( 'Packs in', 'wp-easycart' ), '', array( 'only' => array( 'packs_in' ), 'heading' => false ) );
						do_action( 'wp_easycart_admin_product_details_customs_fields' );
						$this->section_close();
					}
					$this->group_close();

					$this->group_open( __( 'Charges', 'wp-easycart' ), __( 'What this product adds to the shipping price', 'wp-easycart' ), array( 'id' => 'ecdv2_ship_charges' ) );
					$this->section_open( 'shipping', __( 'Charges', 'wp-easycart' ), '', array( 'only' => array( 'exclude_shippable_calculation', 'handling_price', 'handling_price_each' ), 'hidden' => false, 'heading' => false ) );
					do_action( 'wp_easycart_admin_product_details_shipping_fields' );
					$this->section_close();
					$this->group_close();

					/* Everything else of the shipping section ( an extension's fields too ); a new product's backorders as well. */
					$this->group_open( __( 'Where it goes', 'wp-easycart' ), __( 'The address checkout asks for, and where it can ship', 'wp-easycart' ), array( 'id' => 'ecdv2_ship_where' ) );
					$this->section_open( 'shipping', __( 'Where it goes', 'wp-easycart' ), '', array( 'except' => array_merge( array( 'is_shippable', 'exclude_shippable_calculation', 'handling_price', 'handling_price_each' ), $is_new ? array() : array( 'allow_backorders', 'backorder_fill_date' ) ), 'hidden' => false, 'heading' => false ) );
					do_action( 'wp_easycart_admin_product_details_shipping_fields' );
					$this->section_close();
					$this->group_close();
					?>
					<?php if ( $ecdv2_packs_ready ) { ?>
						<?php /* 6.0.2: what customs forms say about it. */ ?>
						<details class="ecdv2-group ecdv2-disclosure" id="ecdv2_ship_customs">
							<summary class="ecdv2-group-head"><span class="ecdv2-group-title"><?php esc_html_e( 'Customs', 'wp-easycart' ); ?></span><span class="ecdv2-group-hint" id="ecdv2_customs_sum"><?php esc_html_e( 'For international labels', 'wp-easycart' ); ?></span></summary>
							<?php
							$this->section_open( 'customs', __( 'Customs', 'wp-easycart' ), '', array( 'except' => array( 'packs_in' ), 'hidden' => false, 'heading' => false ) );
							do_action( 'wp_easycart_admin_product_details_customs_fields' );
							$this->section_close();
							?>
						</details>
					<?php } ?>
				</div>
			</div>

			<!-- ===== TAX ===== -->
			<?php
			/* 6.0.5 ( Matt: tax was rolled into Pricing, and hard to read ): its own section, laid out as Shipping is: what the store
			   charges, the switch of the tax it charges, the rate with what a shopper pays, the tax service's code, and the taxes the
			   store does not charge folded away ( print_tax_section_v2(); every card saves with the tax section, as before ). */
			$ecdv2_open_panel( 'tax', ' ecdv2-requires-save' );
			$this->print_tax_section_v2();
			?>
			</div>

			<!-- ===== ORGANIZATION ===== -->
			<?php $ecdv2_open_panel( 'organize', ' ecdv2-requires-save' ); ?>
				<?php if ( $is_new ) { ?>
					<?php $this->section_open( 'categories', __( 'Categories', 'wp-easycart' ) ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_categories_fields' ); ?>
					<?php $this->section_close(); ?>
				<?php } else { ?>
					<?php /* 6.0.4: the Categories card is in the summary column, so it stays in view whichever section is open. */ ?>
					<div class="ecdv2-moved-note">
						<span class="dashicons dashicons-category" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Categories, the manufacturer, Show on the store page and who can see it are in the summary column, so they stay in view while you work on any section.', 'wp-easycart' ); ?></span>
						<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecdv2.go_tab( 'categories' );"><?php esc_html_e( 'Show them', 'wp-easycart' ); ?></button>
					</div>
				<?php } ?>

				<?php if ( $is_new ) { ?>
					<?php $this->section_open( 'general_options_visibility', __( 'Visibility and sort order', 'wp-easycart' ), __( 'Who sees it, and where it sits in your store', 'wp-easycart' ), array( 'only' => array( 'show_on_startup', 'is_special', 'role_id', 'sort_position' ) ) ); ?>
				<?php } else { ?>
					<?php $this->section_open( 'general_options_visibility', __( 'Sort order and specials', 'wp-easycart' ), __( 'Where it sits in your store', 'wp-easycart' ), array( 'only' => array( 'is_special', 'sort_position' ) ) ); ?>
				<?php } ?>
					<?php do_action( 'wp_easycart_admin_product_details_general_options_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'menus', __( 'Store menus', 'wp-easycart' ), __( 'Up to 3 places in your store menus', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_menus_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'featured_products', __( 'Featured products', 'wp-easycart' ), __( 'Up to 4 related products suggested on its page', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_featured_products_fields' ); ?>
				<?php $this->section_close(); ?>

			</div>

			<!-- ===== DESIGN ===== -->
			<?php /* 6.0.4: its own section after Storefront ( it was under Media ); the panel's heading says what it is. */ ?>
			<?php $ecdv2_open_panel( 'design', ' ecdv2-requires-save' ); ?>
				<?php $this->section_open( 'tags', __( 'Badges and image effects', 'wp-easycart' ), '', array( 'heading' => false ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_tags_fields' ); ?>
					<div class="ecdv2-field ecdv2-field-full" id="ecdv2_badge_preview_wrap" style="display:none;">
						<label class="ecdv2-label"><?php esc_attr_e( 'Badge Preview', 'wp-easycart' ); ?></label>
						<span class="ecdv2-badge-preview" id="ecdv2_badge_preview"></span>
					</div>
				<?php $this->section_close(); ?>
			</div>

			<!-- ===== TYPE & BEHAVIOR ===== -->
			<?php $ecdv2_open_panel( 'behavior', ' ecdv2-requires-save' ); ?>
				<?php if ( method_exists( $this, 'print_type_bar_v2' ) ) { $this->print_type_bar_v2(); } /* 6.0.5: what the product is, Change type goes to the summary column's card */ ?>
				<?php $this->section_open( 'general_options', __( 'Purchase rules', 'wp-easycart' ), __( 'How it can be bought', 'wp-easycart' ), array( 'except' => array( 'show_on_startup', 'is_special', 'use_customer_reviews', 'role_id', 'sort_position', 'mailerlite_group_name' ) ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_general_options_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'subscription', __( 'Subscription', 'wp-easycart' ), __( 'Recurring billing (Stripe required)', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_subscription_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php if ( ! $is_new && method_exists( $this, 'print_access_pages_v2' ) ) { $this->print_access_pages_v2(); } /* 6.0.5: the limited pages its buyers can open */ ?>

				<?php $this->section_open( 'downloads', __( 'Digital Download', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_downloads_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'deconetwork', __( 'Deconetwork', 'wp-easycart' ), __( 'Custom decorated product integration', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_deconetwork_fields' ); ?>
				<?php $this->section_close(); ?>
				<?php
				/**
				 * More purchase-rule cards at the end of Type & Behavior ( e.g. WP EasyCart for BlueCheck's age restriction ).
				 * They save with the editor's Save button like the marketing cards: a .ecdv2-card with
				 * data-ecdv2-section="<key>", registered with ecdv2.register_section( '<key>', payload_fn )
				 * ( admin/js/products-details-v2.js ).
				 *
				 * @since 6.0.2
				 * @param object $product The product being edited ( no product_id yet for a new one ).
				 */
				do_action( 'wp_easycart_admin_product_details_v2_behavior_cards', $product );
				?>
			</div>

			<!-- ===== SEO & MARKETING ===== -->
			<?php $ecdv2_open_panel( 'seo', ' ecdv2-requires-save' ); ?>
				<?php
				/*
				 * 6.0.4: one card with tabs ( Search engines, Search & AI, Google Shopping, Cart links, Marketing ), so the section
				 * takes one card's height. Each pane holds the card it held before ( every card still saves as its own section ).
				 */
				$ecdv2_seo_panes = array();

				/* Search engines: Yoast SEO's fields ( saved to the product post's Yoast meta ) when it is loaded, else the listing card. */
				ob_start();
				$has_yoast = defined( 'WPSEO_VERSION' );
				$yoast_setup = false;
				if ( $has_yoast ) {
					$post_meta = $is_new ? false : get_post_meta( $product->post_id );
					if ( $post_meta && isset( $post_meta['_yoast_wpseo_metadesc'] ) ) {
						$yoast_setup = true;
					}
					$this->print_yoast_seo_v2();
				}
				if ( ! $has_yoast || ! $yoast_setup ) {
					$this->section_open( 'seo', __( 'Search engine listing', 'wp-easycart' ), __( 'The text and picture search results show for its page', 'wp-easycart' ) );
					do_action( 'wp_easycart_admin_product_details_seo_fields' );
					$this->section_close();
				} else {
					/* 6.0.5: WP EasyCart's own description, still saved and exported, shows under Yoast's while it holds text. */
					$this->print_seo_fallback_v2();
				}
				$ecdv2_seo_panes['search'] = array(
					'label' => __( 'Search engines', 'wp-easycart' ),
					'html'  => ob_get_clean(),
				);
				if ( ! $has_yoast || ! $yoast_setup ) {
					$ecdv2_seo_panes['search']['empty'] = '' === trim( (string) $product->seo_description );
					$ecdv2_seo_panes['search']['check'] = 'seo_description';
				}

				/* 6.0.2: Search & AI: barcode, part number and condition ( shared with the Google Merchant attributes ), a checklist
				 * and a preview of the product data search engines and AI assistants read. */
				if ( ! $is_new && class_exists( 'wp_easycart_admin_search_ai' ) ) {
					ob_start();
					wp_easycart_admin_search_ai::print_product_card( $this );
					$ecdv2_seo_panes['ai'] = array(
						'label' => __( 'Search & AI', 'wp-easycart' ),
						'html'  => ob_get_clean(),
					);
				}

				/*
				 * Google Merchant ( Pro ): PRO's field list as a native section ( the same grid, unsaved marks and Save as every
				 * other card ), else PRO's own markup for an older PRO. 6.0.4: only while PRO hooked its fields ( it does only
				 * with an active license ); otherwise the locked row of the upsell system, not the free edition's old
				 * "Google Merchant Setup" box.
				 */
				ob_start();
				$ecdv2_gm = array( 'fields' => false, 'legacy' => '' );
				$ecdv2_gm_has_pro = function_exists( 'wp_easycart_admin_products_pro' ) && false !== has_action( 'wp_easycart_admin_product_details_googlemerchant_fields', array( wp_easycart_admin_products_pro(), 'google_merchant_fields' ) );
				if ( $ecdv2_gm_has_pro && ! $is_new ) {
					$ecdv2_gm = $this->capture_google_merchant_fields();
				}
				if ( $ecdv2_gm['fields'] ) {
					$this->section_open( 'googlemerchant', __( 'Google Merchant', 'wp-easycart' ), __( 'Product feed attributes for Google Shopping', 'wp-easycart' ) );
					echo '<div class="ecdv2-gm-doc"><span class="dashicons dashicons-info-outline"></span><span>' . sprintf( esc_html__( 'These attributes feed Google Shopping. %1$sReview Google&rsquo;s valid values%2$s before publishing.', 'wp-easycart' ), '<a href="https://support.google.com/merchants/answer/7052112?hl=en" target="_blank" rel="noopener">', '</a>' ) . '</span></div>';
					$this->print_fields( $ecdv2_gm['fields'] );
					$this->section_close();
				} else {
					/* 6.0.4: locked, the upsell preview says what the tab is ( so the card has no heading on screen ). */
					$ecdv2_gm_locked = ! $ecdv2_gm_has_pro && class_exists( 'wp_easycart_admin_pro_gate' ) && 'enabled' !== wp_easycart_admin_pro_gate::evaluate( array() )['state'];
					echo '<div class="ecdv2-card' . ( $ecdv2_gm_locked ? ' ecdv2-card-headless ecdv2-gm-locked' : '' ) . '"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Google Merchant', 'wp-easycart' ) . '</h3><span class="ecdv2-card-hint">' . esc_html__( 'Product feed attributes for Google Shopping', 'wp-easycart' ) . '</span></div><div class="ecdv2-card-body ecdv2-gm-body" id="ecdv2_gm_body">';
					if ( '' !== $ecdv2_gm['legacy'] ) {
						echo $ecdv2_gm['legacy']; /* phpcs:ignore WordPress.Security.EscapeOutput -- PRO-rendered markup, reskinned by the #ecdv2_gm_body CSS */
					} else if ( $ecdv2_gm_has_pro ) {
						do_action( 'wp_easycart_admin_product_details_googlemerchant_fields' );
					} else if ( ! $this->print_google_shopping_upsell_v2( $ecdv2_card_image ) ) {
						/* The gate reads the store as licensed while PRO has not hooked its fields ( it has not loaded them yet ). */
						do_action( 'wp_easycart_admin_product_details_googlemerchant_fields' );
					}
					echo '</div></div>';
				}
				$ecdv2_seo_panes['google'] = array(
					'label' => __( 'Google Shopping', 'wp-easycart' ),
					'html'  => ob_get_clean(),
				);

				/* Cart Links quick-create: marketing capability only ( a products-only admin never sees it ). */
				if ( ! $is_new
					&& class_exists( 'wp_easycart_admin_cart_links' )
					&& class_exists( 'wp_easycart_cart_link' )
					&& wp_easycart_admin_cart_links::current_user_allowed()
					&& ! $product->is_subscription_item && ! $product->is_donation && ! $product->is_deconetwork ) {
					ob_start();
					include( EC_PLUGIN_DIRECTORY . '/admin/template/products/products/product-cart-links-card.php' );
					$ecdv2_seo_panes['links'] = array(
						'label' => __( 'Cart links', 'wp-easycart' ),
						'html'  => ob_get_clean(),
					);
				}

				ob_start();
				/* 6.0.2: PRO 6.0.2 prints its own Email marketing card ( every connected service ) through wp_easycart_admin_product_details_v2_marketing_cards; this one stays for an older PRO. */
				if ( get_option( 'ec_option_enable_mailerlite' ) && ! $is_new && ( ! defined( 'WP_EASYCART_ADMIN_PRO_VERSION' ) || version_compare( WP_EASYCART_ADMIN_PRO_VERSION, '6.0.2', '<' ) ) ) {
					$this->section_open( 'general_options_marketing', __( 'Email Marketing', 'wp-easycart' ), __( 'Add buyers of this product to a Mailer Lite subscriber group', 'wp-easycart' ), array( 'only' => array( 'mailerlite_group_name' ) ) );
					do_action( 'wp_easycart_admin_product_details_general_options_fields' );
					$this->section_close();
				}
				/**
				 * More marketing cards ( e.g. the Facebook & Instagram card, PRO's Email marketing card ); 6.0.4: in the Marketing tab.
				 * A card that saves with the editor's Save button is a .ecdv2-card with data-ecdv2-section="<key>", and its script
				 * calls ecdv2.register_section( '<key>', payload_fn ) ( admin/js/products-details-v2.js ).
				 *
				 * @since 6.0.2
				 * @param object $product The product being edited ( no product_id yet for a new one ).
				 */
				do_action( 'wp_easycart_admin_product_details_v2_marketing_cards', $product );
				$ecdv2_seo_more = trim( (string) ob_get_clean() );
				if ( '' !== $ecdv2_seo_more ) {
					$ecdv2_seo_panes['more'] = array(
						'label' => __( 'Marketing', 'wp-easycart' ),
						'html'  => $ecdv2_seo_more,
					);
				}

				$ecdv2_seo_tabs = array();
				foreach ( $ecdv2_seo_panes as $ecdv2_pane_key => $ecdv2_pane ) {
					if ( '' === trim( (string) $ecdv2_pane['html'] ) ) {
						unset( $ecdv2_seo_panes[ $ecdv2_pane_key ] );
						continue;
					}
					$ecdv2_seo_tabs[ $ecdv2_pane_key ] = array_intersect_key( $ecdv2_pane, array_flip( array( 'label', 'empty', 'check' ) ) );
				}
				if ( $ecdv2_seo_tabs ) {
					$this->tabset_open( 'seo', __( 'Search & marketing', 'wp-easycart' ), '', $ecdv2_seo_tabs, array( 'heading' => false ) );
					foreach ( $ecdv2_seo_panes as $ecdv2_pane_key => $ecdv2_pane ) {
						$this->pane_open( $ecdv2_pane_key );
						echo $ecdv2_pane['html']; /* phpcs:ignore WordPress.Security.EscapeOutput -- cards printed above by this editor, PRO and extensions, each escaping its own parts */
						$this->pane_close();
					}
					$this->tabset_close();
				}
				?>
			</div>

			<!-- ===== ORDER MESSAGING ===== -->
			<?php $ecdv2_open_panel( 'notes', ' ecdv2-requires-save' ); ?>
				<?php
				/* 6.0.4: three notes for one moment ( after the order ), in one card with tabs; each tab is still its own save section.
				   The section's heading says what they are, so the tabs follow it ( no second heading ). */
				$ecdv2_note_blank = function( $html ) {
					return '' === trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES ) );
				};
				$this->tabset_open(
					'notes',
					__( 'Notes for buyers', 'wp-easycart' ),
					__( 'Shown to customers who bought this product', 'wp-easycart' ),
					array(
						'confirmation' => array( 'label' => __( 'Order confirmation page', 'wp-easycart' ), 'empty' => $ecdv2_note_blank( $product->order_completed_note ), 'check' => 'order_completed_note' ),
						'email'        => array( 'label' => __( 'Order email', 'wp-easycart' ), 'empty' => $ecdv2_note_blank( $product->order_completed_email_note ), 'check' => 'order_completed_email_note' ),
						'account'      => array( 'label' => __( 'My Account', 'wp-easycart' ), 'empty' => $ecdv2_note_blank( $product->order_completed_details_note ), 'check' => 'order_completed_details_note' ),
					),
					array( 'heading' => false )
				);
				$this->pane_open( 'confirmation' );
				$this->section_open( 'order_completed_note', __( 'Order confirmation page note', 'wp-easycart' ), __( 'Shown on the order confirmation page after they buy it.', 'wp-easycart' ), array( 'pane' => true ) );
				do_action( 'wp_easycart_admin_product_details_order_completed_note_fields' );
				$this->section_close();
				$this->pane_close();
				$this->pane_open( 'email' );
				$this->section_open( 'order_completed_email_note', __( 'Order email note', 'wp-easycart' ), __( 'Added to the order confirmation email.', 'wp-easycart' ), array( 'pane' => true ) );
				do_action( 'wp_easycart_admin_product_details_order_completed_email_note_fields' );
				$this->section_close();
				$this->pane_close();
				$this->pane_open( 'account' );
				$this->section_open( 'order_completed_details_note', __( 'Order details note', 'wp-easycart' ), __( 'Shown with the order in the customer\'s account.', 'wp-easycart' ), array( 'pane' => true ) );
				do_action( 'wp_easycart_admin_product_details_order_completed_details_note_fields' );
				$this->section_close();
				$this->pane_close();
				$this->tabset_close();
				?>
			</div>

			<!-- ===== REVIEWS ===== -->
			<?php
			/* 6.0.4: the count and Review settings sit in the section's heading ( the card under it said them again ). */
			$ecdv2_reviews = $is_new ? array() : $this->review_counts_v2();
			$ecdv2_open_panel(
				'reviews',
				' ecdv2-requires-save',
				$is_new ? array() : array(
					'chip'    => $ecdv2_reviews['chip'],
					'actions' => '<a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=reviews&tab=requests' ) ) . '" class="ecdv2-panel-action"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>' . esc_html__( 'Review settings', 'wp-easycart' ) . '</a>',
				)
			);
			?>
				<?php $this->print_reviews_v2(); ?>
				<?php do_action( 'wp_easycart_admin_product_details_v2_reviews_end', $product ); ?>
			</div>

			<!-- ===== ACTIVITY ===== -->
			<?php $ecdv2_open_panel( 'activity', ' ecdv2-requires-save' ); ?>
				<div id="ecdv2_activity_content">
					<div class="ecdv2-activity-loading"><?php esc_attr_e( 'Loading activity...', 'wp-easycart' ); ?></div>
				</div>
				<?php do_action( 'wp_easycart_admin_product_details_v2_activity_end', $product ); ?>
			</div>

		</div>
		<?php
		if ( ! $is_new ) {
			/* 6.0.4: the summary column ( wp_easycart_admin_details_products_v2::print_side_v2() ). */
			$this->print_side_v2( array( 'card_image' => $ecdv2_card_image ) );
		}
		?>
	</div>
</div>

<?php /* 6.0.4: the editor's columns follow its own width ( set before the first paint; products-details-v2.js keeps it current ).
   Below 960px the summary column is a drawer ( ecdv2-side-drawer ). */ ?>
<script>( function() { var w = document.getElementById( 'ecdv2_wrap' ); if ( ! w || ! w.classList || ! w.classList.contains( 'ecdv2-product' ) ) { return; } var x = w.getBoundingClientRect().width; w.classList.add( x >= 960 ? 'ecdv2-w3' : ( x >= 760 ? 'ecdv2-w2' : 'ecdv2-w1' ) ); if ( x >= 1240 ) { w.classList.add( 'ecdv2-roomy' ); } if ( x < 960 && document.getElementById( 'ecdv2_side' ) ) { w.classList.add( 'ecdv2-side-drawer' ); } } )();</script>

<div class="ecdv2-toasts" id="ecdv2_toasts"></div>

<script>
window.wpeasycart_ecdv2_currency = <?php echo wp_json_encode( array(
	'symbol'   => $this->currency_symbol(),
	'decimals' => $this->currency_decimals(),
	'dec'      => $this->currency_decimal_symbol(),
	'thou'     => $this->currency_grouping_symbol(),
	'left'     => $this->currency_symbol_left(),
) ); ?>;
<?php if ( ! $is_new ) { ?>
window.wpeasycart_ecdv2_view = <?php echo wp_json_encode( $this->view_config_v2( $ecdv2_prefs, $ecdv2_view, $tabs, $ecdv2_mode_of ) ); ?>;
<?php } ?>
</script>