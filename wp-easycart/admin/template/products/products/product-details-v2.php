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
	remove_action( 'wp_easycart_admin_product_details_after_images', array( $ec_legacy_pro, 'load_images_pro' ) );
	remove_action( 'wp_easycart_admin_product_details_after_images', array( $ec_legacy_pro, 'load_options_pro' ) );
}
$has_pro_media_legacy = has_action( 'wp_easycart_admin_product_details_after_images' );

$tabs = apply_filters( 'wp_easycart_admin_product_details_v2_tabs', array(
	'general'   => array( 'label' => __( 'General', 'wp-easycart' ), 'icon' => 'dashicons-tag', 'group' => 'essentials', 'desc' => __( 'The core details every product needs: title, description, and price.', 'wp-easycart' ) ),
	'media'     => array( 'label' => __( 'Media', 'wp-easycart' ), 'icon' => 'dashicons-format-gallery', 'group' => 'essentials', 'desc' => __( 'Photos and videos. The first image is your main listing image.', 'wp-easycart' ) ),
	'pricing'   => array( 'label' => __( 'Pricing & Tax', 'wp-easycart' ), 'icon' => 'dashicons-money-alt', 'group' => 'essentials', 'desc' => __( 'Set the price, sale pricing, and how tax applies to this product.', 'wp-easycart' ) ),
	'inventory' => array( 'label' => __( 'Inventory & Shipping', 'wp-easycart' ), 'icon' => 'dashicons-archive', 'group' => 'essentials', 'desc' => __( 'Stock tracking, shipping options, and package dimensions.', 'wp-easycart' ) ),
	'options'   => array( 'label' => __( 'Options & Variants', 'wp-easycart' ), 'icon' => 'dashicons-admin-settings', 'group' => 'catalog', 'desc' => __( 'Sizes, colors, and add-ons that create purchasable variations.', 'wp-easycart' ) ),
	'organize'  => array( 'label' => __( 'Organization', 'wp-easycart' ), 'icon' => 'dashicons-category', 'group' => 'catalog', 'desc' => __( 'Place this product in menus and categories, and feature related items.', 'wp-easycart' ) ),
	'behavior'  => array( 'label' => __( 'Type & Behavior', 'wp-easycart' ), 'icon' => 'dashicons-admin-generic', 'group' => 'advanced', 'desc' => __( 'Special product types: subscriptions, downloads, donations, and more.', 'wp-easycart' ) ),
	'seo'       => array( 'label' => __( 'SEO & Marketing', 'wp-easycart' ), 'icon' => 'dashicons-search', 'group' => 'advanced', 'desc' => __( 'Search engine titles, descriptions, and marketing integrations.', 'wp-easycart' ) ),
	'notes'     => array( 'label' => __( 'Order Messaging', 'wp-easycart' ), 'icon' => 'dashicons-email-alt', 'group' => 'advanced', 'desc' => __( 'Custom messages shown on receipts and order emails for this product.', 'wp-easycart' ) ),
	'activity'  => array( 'label' => __( 'Activity', 'wp-easycart' ), 'icon' => 'dashicons-chart-bar', 'group' => 'insights', 'new' => true, 'desc' => __( 'Sales, customers, and reviews tied to this product.', 'wp-easycart' ) ),
), $product );

$tab_groups = apply_filters( 'wp_easycart_admin_product_details_v2_tab_groups', array(
	'essentials' => __( 'Essentials', 'wp-easycart' ),
	'catalog'    => __( 'Catalog', 'wp-easycart' ),
	'advanced'   => __( 'Advanced', 'wp-easycart' ),
	'insights'   => __( 'Insights', 'wp-easycart' ),
) );
?>

<input type="hidden" name="ec_admin_form_action" value="<?php echo esc_attr( $this->form_action ); ?>" />
<input type="hidden" name="product_id" id="product_id" value="<?php echo esc_attr( $is_new ? '0' : $product->product_id ); ?>" />
<?php wp_easycart_admin_verification()->print_nonce_field( 'wp_easycart_product_details_nonce', 'wp-easycart-product-details' ); ?>

<div class="ecdv2-wrap<?php echo $is_new ? ' ecdv2-is-new' : ''; ?>" id="ecdv2_wrap">

	<!-- ============ Sticky header ============ -->
	<div class="ecdv2-header">
		<a href="<?php echo esc_attr( $this->action ); ?>" class="ecdv2-header-back" title="<?php esc_attr_e( 'Back to Products', 'wp-easycart' ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
		<div class="ecdv2-header-thumb" id="ecdv2_header_thumb">
			<?php if ( '' !== $thumb_url ) { ?><img src="<?php echo esc_url( $thumb_url ); ?>" alt="" /><?php } else { ?><span class="dashicons dashicons-format-image"></span><?php } ?>
		</div>
		<div class="ecdv2-header-meta">
			<p class="ecdv2-header-title" id="ecdv2_header_title"><?php echo $is_new ? esc_html__( 'New Product', 'wp-easycart' ) : esc_html( $product->title ); ?></p>
			<div class="ecdv2-header-sub" id="ecdv2_header_sub">
				<?php if ( ! $is_new ) { ?>
					<span id="ecdv2_header_sku"><?php echo esc_html( $product->model_number ); ?></span>
					&middot; <span id="ecdv2_header_price"><?php echo esc_html( $this->format_price( $product->price ) ); ?></span>
					<?php if ( $product->show_stock_quantity || $product->use_optionitem_quantity_tracking ) { ?>
						&middot; <span class="<?php echo ( (int) $product->stock_quantity > 0 || $product->use_optionitem_quantity_tracking ) ? 'ecdv2-instock' : 'ecdv2-outstock'; ?>" id="ecdv2_header_stock"><?php
							if ( $product->use_optionitem_quantity_tracking ) {
								esc_attr_e( 'Variant stock', 'wp-easycart' );
							} else if ( (int) $product->stock_quantity > 0 ) {
								echo esc_html( sprintf( __( '%d in stock', 'wp-easycart' ), (int) $product->stock_quantity ) );
							} else {
								esc_attr_e( 'Out of stock', 'wp-easycart' );
							}
						?></span>
					<?php } ?>
				<?php } else { ?>
					<?php esc_attr_e( 'Fill in the essentials, then save to unlock all sections', 'wp-easycart' ); ?>
				<?php } ?>
			</div>
		</div>

		<label class="ecdv2-status-toggle ecdv2-requires-save" title="<?php esc_attr_e( 'Toggle product visibility in your store. Saves immediately.', 'wp-easycart' ); ?>">
			<span class="ecdv2-toggle">
				<input type="checkbox" id="activate_in_store" name="activate_in_store" value="1"<?php checked( ! $is_new && $product->activate_in_store ); ?> data-ecdv2-sec="basic" onchange="ecdv2.quick_activate( this );" />
				<span class="ecdv2-toggle-track"></span>
			</span>
			<span class="ecdv2-status-pill<?php echo ( ! $is_new && $product->activate_in_store ) ? ' is-active' : ''; ?>" id="ecdv2_status_pill"><?php echo ( ! $is_new && $product->activate_in_store ) ? esc_html__( 'Active', 'wp-easycart' ) : esc_html__( 'Draft', 'wp-easycart' ); ?></span>
		</label>

		<div class="ecdv2-header-spacer"></div>
		<span class="ecdv2-dirty-pill" id="ecdv2_dirty_pill"><?php esc_attr_e( 'Unsaved changes', 'wp-easycart' ); ?></span>

		<div class="ecdv2-header-actions">
			<?php do_action( 'wp_easycart_admin_product_details_buttons_pre', $product ); ?>
			<a href="<?php echo esc_attr( $is_new ? '#' : wp_easycart_admin_products()->get_product_link( $product->product_id ) ); ?>" target="_blank" class="ecv2-btn ecdv2-requires-save" id="ec_admin_product_details_view_product_link"><span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span> <?php esc_attr_e( 'View', 'wp-easycart' ); ?></a>

			<div class="ecdv2-menu-wrap ecdv2-requires-save">
				<button type="button" class="ecv2-btn" onclick="ecdv2.menu_toggle( this );" aria-label="<?php esc_attr_e( 'More actions', 'wp-easycart' ); ?>"><span class="dashicons dashicons-ellipsis" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span></button>
				<div class="ecdv2-menu" id="ecdv2_header_menu">
					<a href="admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=add-new" id="ec_admin_product_details_add_new_button" onclick="wp_easycart_admin_open_slideout( 'new_product_box' ); ecdv2.menu_close(); return false;"><span class="dashicons dashicons-plus-alt2"></span><?php esc_attr_e( 'Add New Product', 'wp-easycart' ); ?></a>
					<?php if ( ! $is_new ) { ?>
					<a href="<?php echo esc_url( wp_nonce_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=duplicate-product&product_id=' . (int) $product->product_id, 'wp-easycart-duplicate-product' ) ); ?>"><span class="dashicons dashicons-admin-page"></span><?php esc_attr_e( 'Duplicate Product', 'wp-easycart' ); ?></a>
					<?php } ?>
					<div class="ecdv2-menu-sep"></div>
					<?php do_action( 'wp_easycart_admin_product_details_qr_code', $is_new ? 0 : $product->product_id ); ?>
					<?php wp_easycart_admin()->helpsystem->print_vids_url( 'products', 'products', 'details' ); ?>
					<a href="<?php echo esc_url_raw( $this->docs_link ); ?>" target="_blank"><span class="dashicons dashicons-editor-help"></span><?php esc_attr_e( 'Documentation', 'wp-easycart' ); ?></a>
					<?php do_action( 'wp_easycart_admin_product_details_v2_header_menu', $product ); ?>
				</div>
			</div>

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

		<nav class="ecdv2-rail" aria-label="<?php esc_attr_e( 'Product editor sections', 'wp-easycart' ); ?>">
			<div class="ecdv2-rail-search ecdv2-requires-save">
				<span class="dashicons dashicons-search"></span>
				<input type="text" id="ecdv2_search" placeholder="<?php esc_attr_e( 'Find a setting...', 'wp-easycart' ); ?>" autocomplete="off" />
			</div>
			<div class="ecdv2-search-results" id="ecdv2_search_results"></div>
			<div class="ecdv2-tabs" id="ecdv2_tabs" role="tablist">
				<?php $ecdv2_current_group = ''; ?>
				<?php foreach ( $tabs as $tab_key => $tab ) { ?>
					<?php
					$ecdv2_group = isset( $tab['group'] ) ? $tab['group'] : '';
					if ( $ecdv2_group !== $ecdv2_current_group && isset( $tab_groups[ $ecdv2_group ] ) ) {
						$ecdv2_current_group = $ecdv2_group;
						echo '<div class="ecdv2-rail-group">' . esc_html( $tab_groups[ $ecdv2_group ] ) . '</div>';
					}
					?>
					<button type="button" role="tab" class="ecdv2-tab<?php echo ( 'general' === $tab_key ) ? ' is-active' : ''; ?><?php echo ( 'general' !== $tab_key ) ? ' ecdv2-requires-save' : ''; ?>" data-ecdv2-tab="<?php echo esc_attr( $tab_key ); ?>" onclick="ecdv2.go_tab( '<?php echo esc_attr( $tab_key ); ?>' );">
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<span><?php echo esc_html( $tab['label'] ); ?></span>
						<?php if ( ! empty( $tab['new'] ) ) { ?><span class="ecdv2-tab-new"><?php esc_attr_e( 'NEW', 'wp-easycart' ); ?></span><?php } ?>
						<span class="ecdv2-tab-dirty-dot"></span>
					</button>
				<?php } ?>
			</div>
		</nav>

		<div class="ecdv2-panel-col">

			<?php $ecdv2_intro = function( $key ) use ( $tabs ) {
				if ( empty( $tabs[ $key ]["desc"] ) ) { return; }
				echo '<div class="ecdv2-panel-intro"><span class="dashicons ' . esc_attr( $tabs[ $key ]["icon"] ) . '"></span><span>' . esc_html( $tabs[ $key ]["desc"] ) . '</span></div>';
			}; ?>

			<!-- ===== GENERAL ===== -->
			<div class="ecdv2-panel is-active" data-ecdv2-panel="general" role="tabpanel">
				<?php $ecdv2_intro( 'general' ); ?>
				<?php if ( ! $is_new ) { $this->print_health(); } ?>
				<?php do_action( 'wp_easycart_admin_product_details_basic_start', $product ); ?>
				<?php $this->section_open( 'basic', __( 'Product Details', 'wp-easycart' ), '', array( 'except' => array( 'activate_in_store' ) ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_basic_fields' ); ?>
				<?php $this->section_close(); ?>

				<div class="ecdv2-requires-save">
					<?php $this->section_open( 'short_description', __( 'Short Description', 'wp-easycart' ), __( 'Shown on product listings and grids', 'wp-easycart' ) ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_short_description_fields' ); ?>
					<?php $this->section_close(); ?>

					<?php $this->section_open( 'specifications', __( 'Specifications', 'wp-easycart' ), __( 'Optional specs tab on the product page', 'wp-easycart' ) ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_specifications_fields' ); ?>
					<?php $this->section_close(); ?>
				</div>
			</div>

			<!-- ===== MEDIA ===== -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="media" role="tabpanel">
				<?php $ecdv2_intro( 'media' ); ?>
				<?php if ( $has_pro_media_v2 ) { ?>
					<?php do_action( 'wp_easycart_admin_product_details_v2_media_pro', $product ); ?>
				<?php } else if ( $ec_legacy_pro ) { ?>
					<div class="ecdv2-card"><div class="ecdv2-card-body ecdv2-legacy-pro" id="ecdv2_legacy_pro_media">
						<?php do_action( 'wp_easycart_admin_product_details_after_images' ); ?>
						<?php $ec_legacy_pro->load_images_pro(); ?>
					</div></div>
				<?php } else if ( $has_pro_media_legacy ) { ?>
					<div class="ecdv2-card"><div class="ecdv2-card-body ecdv2-legacy-pro" id="ecdv2_legacy_pro_media">
						<?php do_action( 'wp_easycart_admin_product_details_after_images' ); ?>
					</div></div>
				<?php } else { ?>
					<?php $this->section_open( 'images', __( 'Product Images', 'wp-easycart' ), __( 'Up to 5 images. The first image is your main listing image.', 'wp-easycart' ) ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_images_fields' ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_after_images_save_button' ); ?>
					<?php $this->section_close(); ?>
					<?php
					$this->gate_row( __( 'Unlimited gallery images with drag-and-drop sorting', 'wp-easycart' ), __( 'Add as many product photos and videos as you need, reorder by dragging, and pull from your WordPress media library.', 'wp-easycart' ) );
					$this->gate_row( __( 'Option set images (per-variant galleries)', 'wp-easycart' ), __( 'Show a different image set for each color, style, or material your customer selects.', 'wp-easycart' ) );
					?>
				<?php } ?>

				<?php $this->section_open( 'tags', __( 'Badges & Image Effects', 'wp-easycart' ), __( 'Design the listing: promo ribbons and image hover effects', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_tags_fields' ); ?>
					<div class="ecdv2-field ecdv2-field-full" id="ecdv2_badge_preview_wrap" style="display:none;">
						<label class="ecdv2-label"><?php esc_attr_e( 'Badge Preview', 'wp-easycart' ); ?></label>
						<span class="ecdv2-badge-preview" id="ecdv2_badge_preview"></span>
					</div>
				<?php $this->section_close(); ?>
			</div>

			<!-- ===== PRICING & TAX ===== -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="pricing" role="tabpanel">
				<?php $ecdv2_intro( 'pricing' ); ?>
				<?php do_action( 'wp_easycart_admin_product_details_pricing_start', $product ); ?>
				<?php $this->section_open( 'pricing', __( 'Pricing', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_pricing_fields', $product ); ?>
				<?php $this->section_close(); ?>

				<div class="ecdv2-card"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_attr_e( 'Advanced Pricing', 'wp-easycart' ); ?></h3></div><div class="ecdv2-card-body">
					<?php do_action( 'wp_easycart_admin_product_details_advanced_pricing_fields' ); ?>
				</div></div>

				<?php $this->section_open( 'tax', __( 'Tax', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_tax_fields' ); ?>
				<?php $this->section_close(); ?>
			</div>

			<!-- ===== INVENTORY & SHIPPING ===== -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="inventory" role="tabpanel">
				<?php $ecdv2_intro( 'inventory' ); ?>
				<?php do_action( 'wp_easycart_admin_product_details_quantity_start', $product ); ?>
				<?php $this->section_open( 'quantities', __( 'Stock & Purchase Limits', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_quantity_fields', $product ); ?>
					<div class="ecdv2-variant-stock-note" id="ecdv2_variant_stock_note" style="display:none;">
						<span class="dashicons dashicons-info-outline"></span>
						<span class="ecdv2-variant-stock-note-text"><?php esc_attr_e( 'Stock is tracked per variation, so the overall total above is calculated automatically. Set each variation\'s quantity in the Variations table.', 'wp-easycart' ); ?></span>
						<button type="button" class="ecdv2-variant-stock-link" onclick="ecdv2.go_tab( 'options' );"><?php esc_attr_e( 'Edit Variation Stock', 'wp-easycart' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></button>
					</div>
				<?php $this->section_close(); ?>
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

				<?php $this->section_open( 'shipping', __( 'Shipping', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_shipping_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'packaging', __( 'Packaging Dimensions', 'wp-easycart' ), __( 'Used by live shipping rate calculators', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_packaging_fields' ); ?>
				<?php $this->section_close(); ?>
			</div>

			<!-- ===== OPTIONS & VARIANTS ===== -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="options" role="tabpanel">
				<?php $ecdv2_intro( 'options' ); ?>
				<?php if ( $has_pro_options_v2 ) { ?>
					<?php do_action( 'wp_easycart_admin_product_details_v2_options_pro', $product ); ?>
				<?php } else if ( $ec_legacy_pro ) { ?>
					<div class="ecdv2-card"><div class="ecdv2-card-body ecdv2-legacy-pro" id="ecdv2_legacy_pro_options">
						<?php $ec_legacy_pro->load_options_pro(); ?>
					</div></div>
				<?php } else { ?>
					<?php $this->section_open( 'options', __( 'Option Sets', 'wp-easycart' ), __( 'Choices like size or color (up to 5 sets)', 'wp-easycart' ) ); ?>
						<div style="display:flex; gap:8px; margin-bottom:12px;">
							<input type="button" value="<?php esc_attr_e( 'Quick Option Creator', 'wp-easycart' ); ?>" onclick="ec_admin_open_new_option( );" />
							<a href="admin.php?page=wp-easycart-products&subpage=option" target="_blank" class="ecv2-btn"><?php esc_attr_e( 'Full Option Manager', 'wp-easycart' ); ?></a>
						</div>
						<?php do_action( 'wp_easycart_admin_product_details_options_fields' ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_after_options_save_button' ); ?>
					<?php $this->section_close(); ?>
					<?php
					$this->gate_row( __( 'Modifiers (advanced options) with conditional logic', 'wp-easycart' ), __( 'Text inputs, file uploads, date pickers, checkboxes, and price-adjusting add-ons. Show or hide modifiers based on other selections.', 'wp-easycart' ) );
					$this->gate_row( __( 'Variant manager with per-variant SKU, price, and stock', 'wp-easycart' ), __( 'Manage every size and color combination in a grid: individual SKUs, price adjustments, weights, and live inventory counts.', 'wp-easycart' ) );
					?>
				<?php } ?>
			</div>

			<!-- ===== ORGANIZATION ===== -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="organize" role="tabpanel">
				<?php $ecdv2_intro( 'organize' ); ?>
				<?php $this->section_open( 'categories', __( 'Categories', 'wp-easycart' ), __( 'Changes save instantly', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_categories_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'general_options_visibility', __( 'Visibility & Sorting', 'wp-easycart' ), '', array( 'only' => array( 'show_on_startup', 'is_special', 'use_customer_reviews', 'sort_position' ) ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_general_options_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'menus', __( 'Menu Locations', 'wp-easycart' ), __( 'Place this product in up to 3 store menu locations', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_menus_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'featured_products', __( 'Featured Products', 'wp-easycart' ), __( 'Cross-sell up to 4 related products on this page', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_featured_products_fields' ); ?>
				<?php $this->section_close(); ?>

			</div>

			<!-- ===== TYPE & BEHAVIOR ===== -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="behavior" role="tabpanel">
				<?php $ecdv2_intro( 'behavior' ); ?>
				<?php $this->section_open( 'general_options', __( 'Product Behaviors', 'wp-easycart' ), __( 'Special product types and purchase rules', 'wp-easycart' ), array( 'except' => array( 'show_on_startup', 'is_special', 'use_customer_reviews', 'sort_position', 'mailerlite_group_name' ) ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_general_options_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'subscription', __( 'Subscription', 'wp-easycart' ), __( 'Recurring billing (Stripe required)', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_subscription_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'downloads', __( 'Digital Download', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_downloads_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'deconetwork', __( 'Deconetwork', 'wp-easycart' ), __( 'Custom decorated product integration', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_deconetwork_fields' ); ?>
				<?php $this->section_close(); ?>
			</div>

			<!-- ===== SEO & MARKETING ===== -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="seo" role="tabpanel">
				<?php $ecdv2_intro( 'seo' ); ?>
				<?php if ( get_option( 'ec_option_enable_mailerlite' ) && ! $is_new ) { ?>
					<?php $this->section_open( 'general_options_marketing', __( 'Email Marketing', 'wp-easycart' ), __( 'Add buyers of this product to a Mailer Lite subscriber group', 'wp-easycart' ), array( 'only' => array( 'mailerlite_group_name' ) ) ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_general_options_fields' ); ?>
					<?php $this->section_close(); ?>
				<?php } ?>

				<?php
				$has_yoast = false;
				$yoast_setup = false;
				if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
					$has_yoast = true;
					$post_meta = $is_new ? false : get_post_meta( $product->post_id );
					if ( $post_meta && isset( $post_meta['_yoast_wpseo_metadesc'] ) ) {
						$yoast_setup = true;
					}
				}
				?>
				<?php if ( $has_yoast ) { ?>
					<div class="ecdv2-newproduct-note"><span class="dashicons dashicons-yes-alt" style="color:var(--ecv2-primary);"></span><span><?php echo sprintf( esc_attr__( 'Yoast SEO is active. %1$sEdit the product post%2$s to manage Yoast settings. Keep the shortcode in the post content intact.', 'wp-easycart' ), '<a target="_blank" href="post.php?post=' . esc_attr( $is_new ? 0 : $product->post_id ) . '&action=edit">', '</a>' ); ?></span></div>
				<?php } ?>
				<?php if ( ! $has_yoast || ! $yoast_setup ) { ?>
					<?php $this->section_open( 'seo', __( 'Search Engine Listing', 'wp-easycart' ) ); ?>
						<?php do_action( 'wp_easycart_admin_product_details_seo_fields' ); ?>
					<?php $this->section_close(); ?>
				<?php } ?>

				<?php
				/* Google Merchant: capture the PRO field list and render it as a
				 * native v2 section — same grid, dirty tracking, and global Save
				 * as every other panel. Falls back to the CSS-reskinned legacy
				 * output for older PRO builds, and to the upsell gate when PRO
				 * is not active. */
				$ecdv2_gm = array( 'fields' => false, 'legacy' => '' );
				$ecdv2_gm_has_pro = has_action( 'wp_easycart_admin_product_details_googlemerchant_fields' );
				if ( $ecdv2_gm_has_pro && ! $is_new ) {
					$ecdv2_gm = $this->capture_google_merchant_fields();
				}
				?>
				<?php if ( $ecdv2_gm['fields'] ) { ?>
					<?php $this->section_open( 'googlemerchant', __( 'Google Merchant', 'wp-easycart' ), __( 'Product feed attributes for Google Shopping', 'wp-easycart' ) ); ?>
						<div class="ecdv2-gm-doc"><span class="dashicons dashicons-info-outline"></span><span><?php echo sprintf( esc_html__( 'These attributes feed Google Shopping. %1$sReview Google&rsquo;s valid values%2$s before publishing.', 'wp-easycart' ), '<a href="https://support.google.com/merchants/answer/7052112?hl=en" target="_blank" rel="noopener">', '</a>' ); ?></span></div>
						<?php $this->print_fields( $ecdv2_gm['fields'] ); ?>
					<?php $this->section_close(); ?>
				<?php } else { ?>
					<div class="ecdv2-card"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_attr_e( 'Google Merchant', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint"><?php esc_attr_e( 'Product feed attributes for Google Shopping', 'wp-easycart' ); ?></span></div><div class="ecdv2-card-body ecdv2-gm-body" id="ecdv2_gm_body">
						<?php
						if ( '' !== $ecdv2_gm['legacy'] ) {
							echo $ecdv2_gm['legacy']; /* phpcs:ignore WordPress.Security.EscapeOutput -- PRO-rendered markup, reskinned by the #ecdv2_gm_body CSS */
						} else if ( $ecdv2_gm_has_pro ) {
							do_action( 'wp_easycart_admin_product_details_googlemerchant_fields' );
						} else {
							$this->gate_row( __( 'Google Merchant feed attributes', 'wp-easycart' ), __( 'Map this product into your Google Shopping feed with categories, GTIN/MPN, condition, and availability fields.', 'wp-easycart' ) );
						}
						?>
					</div></div>
				<?php } ?>
			</div>

			<!-- ===== ORDER MESSAGING ===== -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="notes" role="tabpanel">
				<?php $ecdv2_intro( 'notes' ); ?>
				<?php $this->section_open( 'order_completed_note', __( 'Receipt Page Note', 'wp-easycart' ), __( 'Shown on the order-complete page when this product is purchased', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_order_completed_note_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'order_completed_email_note', __( 'Order Email Note', 'wp-easycart' ), __( 'Added to the order confirmation email', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_order_completed_email_note_fields' ); ?>
				<?php $this->section_close(); ?>

				<?php $this->section_open( 'order_completed_details_note', __( 'Order Details Note', 'wp-easycart' ), __( 'Shown in the customer account order details view', 'wp-easycart' ) ); ?>
					<?php do_action( 'wp_easycart_admin_product_details_order_completed_details_note_fields' ); ?>
				<?php $this->section_close(); ?>
			</div>

			<!-- ===== ACTIVITY ===== -->
			<div class="ecdv2-panel ecdv2-requires-save" data-ecdv2-panel="activity" role="tabpanel">
				<?php $ecdv2_intro( 'activity' ); ?>
				<div id="ecdv2_activity_content">
					<div class="ecdv2-activity-loading"><?php esc_attr_e( 'Loading activity...', 'wp-easycart' ); ?></div>
				</div>
				<?php do_action( 'wp_easycart_admin_product_details_v2_activity_end', $product ); ?>
			</div>

		</div>
	</div>
</div>

<div class="ecdv2-toasts" id="ecdv2_toasts"></div>

<script>
window.wpeasycart_ecdv2_currency = <?php echo wp_json_encode( array(
	'symbol'   => $this->currency_symbol(),
	'decimals' => $this->currency_decimals(),
	'dec'      => $this->currency_decimal_symbol(),
	'thou'     => $this->currency_grouping_symbol(),
	'left'     => $this->currency_symbol_left(),
) ); ?>;
</script>