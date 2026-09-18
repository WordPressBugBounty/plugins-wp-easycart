<?php
/**
 * Category editor ( V2 ) template. $this is wp_easycart_admin_category_editor_v2.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$c        = $this->category;
$health   = $this->health();
$list_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=category' );
$img_url  = wp_easycart_admin_category_table::image_url( $c->image );
$thumb_id = $this->post ? (int) get_post_thumbnail_id( $this->post ) : 0;
$thumb    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
$permalink = $this->permalink();
$ok_svg   = '<span class="dashicons dashicons-yes"></span>';
$warn_svg = '<span class="dashicons dashicons-warning"></span>';
$products_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=products&filter_2=' . (int) $c->category_id );
/* Yoast card renders only while Yoast is active and the category has its ec_store page. @since 6.0.0 */
$yoast_card = class_exists( 'wp_easycart_admin_catalog_yoast_v2' ) && wp_easycart_admin_catalog_yoast_v2::available( 'category', (int) $c->category_id );
?>
<div class="ecv2-wrap ecdv2-wrap eccat-wrap" id="eccat" data-category-id="<?php echo esc_attr( $c->category_id ); ?>">
	<script type="application/json" id="eccat_data"><?php echo wp_json_encode( $this->js_data() ); ?></script>

	<div class="ecdv2-header">
		<a class="ecdv2-header-back" href="<?php echo esc_url( $list_url ); ?>" title="<?php esc_attr_e( 'Back to categories', 'wp-easycart' ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
		<div class="ecdv2-header-thumb" id="eccat_h_thumb"><?php if ( $img_url ) { ?><img src="<?php echo esc_url( $img_url ); ?>" alt=""><?php } else { ?><span class="dashicons dashicons-format-image"></span><?php } ?></div>
		<div class="ecdv2-header-meta">
			<h1 class="ecdv2-header-title" id="eccat_h_title"><?php echo esc_html( wp_unslash( $c->category_name ) ); ?></h1>
			<div class="ecdv2-header-sub"><span id="eccat_h_path"><?php echo $this->path ? esc_html( implode( ' › ', $this->path ) . ' › ' . wp_unslash( $c->category_name ) ) : esc_html__( 'Top level', 'wp-easycart' ); ?></span> · <a href="<?php echo esc_url( $products_url ); ?>" id="eccat_h_count"><?php echo esc_html( sprintf( _n( '%d product', '%d products', $this->product_total, 'wp-easycart' ), $this->product_total ) ); ?></a><?php if ( $permalink ) { ?> · <span class="ecv2-mono" id="eccat_h_url"><?php echo esc_html( wp_parse_url( $permalink, PHP_URL_PATH ) ); ?></span><?php } ?></div>
		</div>
		<span class="ecdv2-status-pill<?php echo $c->is_active ? ' is-active' : ''; ?>" id="eccat_h_status"><?php echo $c->is_active ? esc_html__( 'Active', 'wp-easycart' ) : esc_html__( 'Hidden', 'wp-easycart' ); ?></span>
		<span class="ecdv2-header-spacer"></span>
		<span class="ecdv2-dirty-pill" id="eccat_dirty"><?php esc_html_e( 'Unsaved changes', 'wp-easycart' ); ?></span>
		<div class="ecdv2-header-actions">
			<?php if ( $permalink ) { ?><a class="ecv2-btn ecv2-btn-ghost" href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer" id="eccat_view"><?php esc_html_e( 'View on site', 'wp-easycart' ); ?> <span class="dashicons dashicons-external"></span></a><?php } ?>
			<button type="button" class="ecv2-btn ecv2-btn-primary ecdv2-save-btn" id="eccat_save" onclick="eccat.save();"><span class="ecdv2-save-spin"></span><span class="ecdv2-save-label"><?php esc_html_e( 'Save', 'wp-easycart' ); ?></span></button>
		</div>
	</div>

	<div class="ecdv2-body">
		<aside class="ecdv2-rail">
			<div class="ecdv2-rail-group"><?php esc_html_e( 'Category', 'wp-easycart' ); ?></div>
			<a href="#catv2-details" class="ecdv2-rail-link is-active"><?php esc_html_e( 'Details', 'wp-easycart' ); ?></a>
			<a href="#catv2-images" class="ecdv2-rail-link"><?php esc_html_e( 'Images', 'wp-easycart' ); ?></a>
			<a href="#catv2-products" class="ecdv2-rail-link"><?php esc_html_e( 'Products', 'wp-easycart' ); ?> <span class="ecv2-chip ecv2-chip-gray" id="eccat_rail_count"><?php echo (int) $this->product_total; ?></span></a>
			<a href="#catv2-subs" class="ecdv2-rail-link"><?php esc_html_e( 'Subcategories', 'wp-easycart' ); ?> <span class="ecv2-chip ecv2-chip-gray" id="eccat_rail_subs"><?php echo count( $this->children ); ?></span></a>
			<a href="#catv2-seo" class="ecdv2-rail-link"><?php esc_html_e( 'SEO & URL', 'wp-easycart' ); ?></a>
			<?php if ( $yoast_card ) { ?><a href="#catv2-yoast" class="ecdv2-rail-link"><?php esc_html_e( 'Yoast SEO', 'wp-easycart' ); ?></a><?php } ?>
			<div class="ecdv2-rail-group"><?php esc_html_e( 'More', 'wp-easycart' ); ?></div>
			<a href="#catv2-danger" class="ecdv2-rail-link"><?php esc_html_e( 'Danger zone', 'wp-easycart' ); ?></a>
			<div class="ecdv2-health" id="eccat_health">
				<div class="ecdv2-health-top"><b><?php esc_html_e( 'Category health', 'wp-easycart' ); ?></b></div>
				<div class="ecdv2-health-items">
					<?php foreach ( $health as $h ) { ?>
					<div class="ecdv2-health-item <?php echo $h['ok'] ? 'is-ok' : 'is-warn'; ?>"><?php echo $h['ok'] ? $ok_svg : $warn_svg; ?><span class="ecdv2-health-label"><?php echo esc_html( $h['label'] ); ?></span></div>
					<?php } ?>
				</div>
			</div>
		</aside>

		<div class="ecdv2-main">

			<!-- Details -->
			<div class="ecdv2-card" id="catv2-details">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Details', 'wp-easycart' ); ?></h3><a href="<?php echo esc_url( $this->docs_link ); ?>" target="_blank" class="ecdv2-help-link"><span class="dashicons dashicons-editor-help"></span><?php esc_html_e( 'Help', 'wp-easycart' ); ?></a></div>
				<div class="ecdv2-card-body">
					<div class="ecdv2-grid">
						<div class="ecdv2-field"><label class="ecdv2-label" for="eccat_name"><?php esc_html_e( 'Name', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" id="eccat_name" value="<?php echo esc_attr( wp_unslash( $c->category_name ) ); ?>" data-track></div>
						<div class="ecdv2-field">
							<label class="ecdv2-label" for="eccat_parent"><?php esc_html_e( 'Parent category', 'wp-easycart' ); ?></label>
							<select class="ecv2-select ecos-select" id="eccat_parent" data-track>
								<option value="0"><?php esc_html_e( '— Top level —', 'wp-easycart' ); ?></option>
								<?php foreach ( $this->parents as $p ) { ?><option value="<?php echo esc_attr( $p['id'] ); ?>"<?php selected( $p['id'], (int) $c->parent_id ); ?>><?php echo esc_html( $p['label'] ); ?></option><?php } ?>
							</select>
							<span class="ecdv2-field-desc"><?php esc_html_e( 'Moving does not change the URL; edit the slug under SEO & URL if you want it to match.', 'wp-easycart' ); ?></span>
						</div>
						<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label" for="eccat_desc"><?php esc_html_e( 'Description', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'shown at the top of the category page', 'wp-easycart' ); ?></span></label><textarea class="ecv2-input" id="eccat_desc" rows="4" data-track placeholder="<?php esc_attr_e( 'Tell shoppers what they will find here…', 'wp-easycart' ); ?>"><?php echo esc_textarea( (string) wp_unslash( $c->short_description ) ); ?></textarea></div>
						<div class="ecdv2-field">
							<label class="ecdv2-label"><?php esc_html_e( 'Visibility', 'wp-easycart' ); ?></label>
							<label class="ecos-toggle-row"><span class="ecv2-toggle"><input type="checkbox" id="eccat_active"<?php checked( (bool) $c->is_active ); ?> data-track><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t"><?php esc_html_e( 'Active', 'wp-easycart' ); ?></span><small><?php esc_html_e( 'Hidden categories keep their products; the page is private and not listed.', 'wp-easycart' ); ?></small></span></label>
							<label class="ecos-toggle-row"><span class="ecv2-toggle"><input type="checkbox" id="eccat_featured"<?php checked( (bool) $c->featured_category ); ?> data-track><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t"><?php esc_html_e( 'Featured', 'wp-easycart' ); ?></span><small><?php esc_html_e( 'Appears in featured-category widgets and the store landing page.', 'wp-easycart' ); ?></small></span></label>
						</div>
						<div class="ecdv2-field"><label class="ecdv2-label" for="eccat_priority"><?php esc_html_e( 'Store order', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'higher shows first', 'wp-easycart' ); ?></span></label><input type="number" class="ecv2-input" id="eccat_priority" value="<?php echo esc_attr( (int) $c->priority ); ?>" style="max-width:120px" data-track><span class="ecdv2-field-desc"><?php esc_html_e( 'Or drag rows on the Categories list to set this visually.', 'wp-easycart' ); ?></span></div>
					</div>
				</div>
			</div>

			<!-- Images -->
			<div class="ecdv2-card" id="catv2-images">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Images', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint"><?php esc_html_e( 'Category image is used in grids and menus; the banner is the wide image on the category page', 'wp-easycart' ); ?></span></div>
				<div class="ecdv2-card-body">
					<div class="ecdv2-grid">
						<div class="eccat-imgslot" id="eccat_img_slot">
							<span class="eccat-imgslot-thumb<?php echo $img_url ? ' has' : ''; ?>" id="eccat_img_thumb"><?php if ( $img_url ) { ?><img src="<?php echo esc_url( $img_url ); ?>" alt=""><?php } else { ?><span class="dashicons dashicons-format-image"></span><?php } ?></span>
							<div class="eccat-imgslot-meta"><b><?php esc_html_e( 'Category image', 'wp-easycart' ); ?></b><span id="eccat_img_meta"><?php echo $img_url ? esc_html( basename( $c->image ) ) : esc_html__( 'Recommended: square, at least 600×600', 'wp-easycart' ); ?></span></div>
							<div class="eccat-imgslot-acts"><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="eccat.pick_image();"><?php echo $img_url ? esc_html__( 'Replace', 'wp-easycart' ) : esc_html__( 'Upload', 'wp-easycart' ); ?></button><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="eccat_img_remove" onclick="eccat.remove_image();"<?php if ( ! $img_url ) { echo ' style="display:none"'; } ?>><?php esc_html_e( 'Remove', 'wp-easycart' ); ?></button></div>
							<input type="hidden" id="eccat_image" value="<?php echo esc_attr( $c->image ); ?>" data-track>
						</div>
						<div class="eccat-imgslot" id="eccat_banner_slot">
							<span class="eccat-imgslot-thumb eccat-imgslot-wide<?php echo $thumb ? ' has' : ''; ?>" id="eccat_banner_thumb"><?php if ( $thumb ) { ?><img src="<?php echo esc_url( $thumb ); ?>" alt=""><?php } else { ?><span class="dashicons dashicons-format-image"></span><?php } ?></span>
							<div class="eccat-imgslot-meta"><b><?php esc_html_e( 'Featured banner', 'wp-easycart' ); ?></b><span><?php esc_html_e( 'Optional · recommended 1600×500 · used by themes that show a category header', 'wp-easycart' ); ?></span></div>
							<div class="eccat-imgslot-acts"><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="eccat.pick_banner();"><?php echo $thumb ? esc_html__( 'Replace', 'wp-easycart' ) : esc_html__( 'Upload', 'wp-easycart' ); ?></button><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="eccat_banner_remove" onclick="eccat.remove_banner();"<?php if ( ! $thumb ) { echo ' style="display:none"'; } ?>><?php esc_html_e( 'Remove', 'wp-easycart' ); ?></button></div>
							<input type="hidden" id="eccat_featured_image" value="<?php echo esc_attr( $thumb_id ); ?>" data-track>
						</div>
					</div>
				</div>
			</div>

			<!-- Products -->
			<div class="ecdv2-card" id="catv2-products">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Products', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint" id="eccat_products_hint"><?php echo esc_html( sprintf( _n( '%d in this category', '%d in this category', $this->product_total, 'wp-easycart' ), $this->product_total ) ); ?> · <?php esc_html_e( 'changes apply immediately', 'wp-easycart' ); ?></span><a href="#" class="ecdv2-help-link ecos-link-brand" onclick="ecv2_catalog.pick_products( <?php echo (int) $c->category_id; ?> ); return false;"><?php esc_html_e( 'Browse products…', 'wp-easycart' ); ?></a></div>
				<div class="ecdv2-card-body">
					<?php if ( $this->smart_available ) { $sm = $this->smart_mode; ?>
					<div class="eccat-mode<?php echo $this->smart_pro ? '' : ' is-locked'; ?>" id="eccat_mode_row">
						<div class="ecv2-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'How products get into this category', 'wp-easycart' ); ?>">
							<button type="button" class="ecv2-seg-btn<?php echo 0 === $sm ? ' is-on' : ''; ?>" data-mode="0" onclick="eccat.set_mode( 0 );"><?php esc_html_e( 'Hand-picked', 'wp-easycart' ); ?></button>
							<button type="button" class="ecv2-seg-btn<?php echo 1 === $sm ? ' is-on' : ''; ?>" data-mode="1" onclick="eccat.set_mode( 1 );"><?php esc_html_e( 'Smart rules', 'wp-easycart' ); ?><?php if ( ! $this->smart_pro ) { ?> <span class="dashicons dashicons-lock"></span><?php } ?></button>
							<button type="button" class="ecv2-seg-btn<?php echo 2 === $sm ? ' is-on' : ''; ?>" data-mode="2" onclick="eccat.set_mode( 2 );"><?php esc_html_e( 'Both', 'wp-easycart' ); ?><?php if ( ! $this->smart_pro ) { ?> <span class="dashicons dashicons-lock"></span><?php } ?></button>
						</div>
						<span class="ecos-hint" id="eccat_mode_hint"><?php
							if ( ! $this->smart_pro ) { $g = $this->smart_gate; echo '<span class="ecv2-chip ecv2-chip-blue">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span> ' . esc_html( ! empty( $g['desc'] ) ? $g['desc'] : wp_easycart_admin_edition::included_text( 'pro' ) ) . ( ! empty( $g['url'] ) ? ' <a href="' . esc_url( $g['url'] ) . '"' . ( 'upsell' === $g['state'] ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html( 'upsell' === $g['state'] ? sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'See %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) : __( 'Fix this', 'wp-easycart' ) ) . '</a>' : '' ); }
							else { esc_html_e( 'Smart rules re-evaluate whenever a product is saved and once an hour.', 'wp-easycart' ); }
						?></span>
					</div>

					<?php if ( ! $this->smart_pro ) { ?>
					<div class="eccat-upsell">
						<div class="eccat-upsell-title"><?php esc_html_e( 'What smart rules do', 'wp-easycart' ); ?></div>
						<div class="eccat-upsell-ex"><span class="ecv2-chip">New arrivals</span><span>= <?php esc_html_e( 'date added · within the last · 30 days', 'wp-easycart' ); ?></span></div>
						<div class="eccat-upsell-ex"><span class="ecv2-chip">Sale</span><span>= <?php esc_html_e( 'on sale · is · yes', 'wp-easycart' ); ?></span></div>
						<div class="eccat-upsell-ex"><span class="ecv2-chip">Under $25</span><span>= <?php esc_html_e( 'price · less than · 25', 'wp-easycart' ); ?></span></div>
						<div class="eccat-upsell-ex"><span class="ecv2-chip">Acme Outdoor</span><span>= <?php esc_html_e( 'manufacturer · is · Acme · and · in stock', 'wp-easycart' ); ?></span></div>
						<p class="ecos-hint"><?php esc_html_e( 'Products join and leave automatically whenever they’re saved and once an hour. Your themes need no changes — memberships are real category rows.', 'wp-easycart' ); ?></p>
					</div>
					<?php } ?>
					<div class="eccat-rules" id="eccat_rules" <?php if ( ! $sm || ! $this->smart_pro ) { echo 'style="display:none"'; } ?>>
						<div class="eccat-rules-match"><?php esc_html_e( 'Include products that match', 'wp-easycart' ); ?>
							<select class="ecv2-select ecos-select" id="eccat_rules_mode"><option value="all"<?php selected( $this->smart_rules['mode'], 'all' ); ?>><?php esc_html_e( 'all', 'wp-easycart' ); ?></option><option value="any"<?php selected( $this->smart_rules['mode'], 'any' ); ?>><?php esc_html_e( 'any', 'wp-easycart' ); ?></option></select>
							<?php esc_html_e( 'of these rules:', 'wp-easycart' ); ?>
						</div>
						<div id="eccat_rules_list"></div>
						<div class="eccat-rules-foot">
							<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="eccat.add_rule();">+ <?php esc_html_e( 'Add rule', 'wp-easycart' ); ?></button>
							<span class="ecos-hint" id="eccat_exclude_hint"></span>
						</div>
						<div class="eccat-preview" id="eccat_preview">
							<div class="eccat-preview-num"><b id="eccat_preview_count">—</b><small><?php esc_html_e( 'products match right now', 'wp-easycart' ); ?></small></div>
							<div class="eccat-preview-thumbs" id="eccat_preview_thumbs"></div>
							<span class="ecos-hint eccat-preview-note"><?php esc_html_e( 'Live preview — membership updates when you click Save.', 'wp-easycart' ); ?></span>
						</div>
						<div class="ecos-note" id="eccat_rules_only_warn" style="display:none"><span class="dashicons dashicons-warning"></span><div><?php esc_html_e( 'Smart rules only: hand-picked products that don’t match the rules are removed from this category when you save.', 'wp-easycart' ); ?> <b id="eccat_rules_only_n"></b></div></div>
					</div>
					<?php } ?>

					<div class="eccat-combo" id="eccat_combo" <?php if ( $this->smart_available && 1 === $this->smart_mode && $this->smart_pro ) { echo 'data-readonly="1"'; } ?>>
						<div class="eccat-tokens" id="eccat_tokens">
							<input type="text" id="eccat_search" placeholder="<?php esc_attr_e( 'Type a product name or SKU to add…', 'wp-easycart' ); ?>" autocomplete="off">
						</div>
						<div class="eccat-dd" id="eccat_dd" style="display:none"></div>
					</div>
					<div class="eccat-products-foot">
						<span class="ecos-hint" id="eccat_products_stats"><?php echo esc_html( sprintf( __( '%1$d active · %2$d inactive', 'wp-easycart' ), $this->product_total - $this->product_inactive, $this->product_inactive ) ); ?></span>
						<a href="<?php echo esc_url( $products_url ); ?>" class="ecos-link-brand"><?php esc_html_e( 'Open all in Products', 'wp-easycart' ); ?></a>
						<span class="ecos-grow"></span>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="eccat_more" onclick="eccat.load_more( this );"<?php if ( $this->product_total <= count( $this->products ) ) { echo ' style="display:none"'; } ?>><?php esc_html_e( 'Show more', 'wp-easycart' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Subcategories -->
			<div class="ecdv2-card" id="catv2-subs">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Subcategories', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint" id="eccat_subs_hint"><?php echo $this->children ? esc_html( sprintf( _n( '%d subcategory', '%d subcategories', count( $this->children ), 'wp-easycart' ), count( $this->children ) ) ) : esc_html__( 'None yet', 'wp-easycart' ); ?></span><a href="#" class="ecdv2-help-link ecos-link-brand" onclick="eccat.focus_sub_search(); return false;"><?php esc_html_e( 'Add existing category', 'wp-easycart' ); ?></a><a href="#" class="ecdv2-help-link ecos-link-brand" onclick="ecv2_catalog.new_category( <?php echo (int) $c->category_id; ?> ); return false;">+ <?php esc_html_e( 'Add subcategory', 'wp-easycart' ); ?></a></div>
				<div class="ecdv2-card-body">
					<?php /* "Add existing category": typeahead over ecv2_category_search; the pick moves that category under this one ( ecv2_category_set_parent ). @since 6.0.0 */ ?>
					<div class="eccat-combo eccat-sub-combo" id="eccat_sub_combo">
						<div class="eccat-tokens"><input type="text" id="eccat_sub_search" placeholder="<?php esc_attr_e( 'Type a category name to move it under this one…', 'wp-easycart' ); ?>" autocomplete="off"></div>
						<div class="eccat-dd" id="eccat_sub_dd" style="display:none"></div>
					</div>
					<div class="ecos-hint" id="eccat_subs_empty"<?php if ( ! empty( $this->children ) ) { echo ' style="display:none"'; } ?>><?php esc_html_e( 'Subcategories appear beneath this one in menus and the store sidebar. Products can belong to a parent and a child at the same time.', 'wp-easycart' ); ?></div>
					<div class="ecos-used" id="eccat_subs_list">
						<?php foreach ( $this->children as $ch ) { $cu = wp_easycart_admin_category_table::image_url( $ch->image ); ?>
						<div class="ecos-u">
							<span class="ecv2-thumb"><?php if ( $cu ) { ?><img src="<?php echo esc_url( $cu ); ?>" alt="" loading="lazy"><?php } else { ?><span class="dashicons dashicons-format-image"></span><?php } ?></span>
							<div class="ecos-u-main"><a class="ecv2-link-primary" href="<?php echo esc_url( wp_easycart_admin_category_table::editor_url( $ch->category_id ) ); ?>"><?php echo esc_html( wp_unslash( $ch->category_name ) ); ?></a><span class="ecv2-sub"><?php echo esc_html( sprintf( _n( '%d product', '%d products', (int) $ch->direct_products, 'wp-easycart' ), (int) $ch->direct_products ) ); ?></span></div>
							<span class="ecv2-chip <?php echo $ch->is_active ? 'ecv2-chip-green' : 'ecv2-chip-gray'; ?>"><?php echo $ch->is_active ? esc_html__( 'Active', 'wp-easycart' ) : esc_html__( 'Hidden', 'wp-easycart' ); ?></span>
							<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="<?php echo esc_url( wp_easycart_admin_category_table::editor_url( $ch->category_id ) ); ?>"><?php esc_html_e( 'Open', 'wp-easycart' ); ?></a>
						</div>
						<?php } ?>
					</div>
				</div>
			</div>

			<!-- SEO & URL -->
			<div class="ecdv2-card" id="catv2-seo">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'SEO & URL', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint"><?php if ( $yoast_card ) { esc_html_e( 'Slug and search excerpt for the category page — the Yoast title and description are in the card below', 'wp-easycart' ); } else { echo $this->yoast ? esc_html__( 'Yoast SEO detected — title and meta description are managed there', 'wp-easycart' ) : esc_html__( 'Slug and search excerpt for the category page', 'wp-easycart' ); } ?></span><?php if ( $this->yoast && $this->post && ! $yoast_card ) { ?><a href="<?php echo esc_url( get_edit_post_link( $this->post->ID, '' ) ); ?>" target="_blank" class="ecdv2-help-link ecos-link-brand"><?php esc_html_e( 'Edit in Yoast', 'wp-easycart' ); ?> <span class="dashicons dashicons-external"></span></a><?php } ?></div>
				<div class="ecdv2-card-body">
					<div class="ecdv2-grid">
						<div class="ecdv2-field ecdv2-field-full">
							<label class="ecdv2-label" for="eccat_slug"><?php esc_html_e( 'URL slug', 'wp-easycart' ); ?></label>
							<div class="eccat-slug"><span class="eccat-slug-pre" id="eccat_slug_pre"><?php echo esc_html( str_replace( home_url(), '', $this->slug_prefix() ) ); ?></span><input type="text" id="eccat_slug" value="<?php echo esc_attr( $this->post ? $this->post->post_name : '' ); ?>" data-track><button type="button" class="eccat-slug-btn" title="<?php esc_attr_e( 'Generate from name', 'wp-easycart' ); ?>" onclick="eccat.slug_from_name();"><span class="dashicons dashicons-update"></span></button></div>
							<span class="ecdv2-field-desc" id="eccat_slug_desc"><?php esc_html_e( 'Slugs are not updated automatically when you rename a category.', 'wp-easycart' ); ?></span>
							<label class="ecos-toggle-row" id="eccat_redirect_row" style="display:none"><span class="ecv2-toggle"><input type="checkbox" id="eccat_redirect" checked><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t"><?php esc_html_e( 'Redirect the old URL to the new one', 'wp-easycart' ); ?></span><small><?php echo class_exists( 'ec_url_redirects' ) ? esc_html__( 'Adds a permanent (301) redirect so existing links, menus and search results keep working.', 'wp-easycart' ) : esc_html__( 'Requires the EasyCart redirect module (inc/classes/core/ec_url_redirects.php) to be loaded.', 'wp-easycart' ); ?></small></span></label>
						</div>
						<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label" for="eccat_excerpt"><?php esc_html_e( 'Search excerpt', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'used as the meta description when no SEO plugin overrides it', 'wp-easycart' ); ?></span></label><input type="text" class="ecv2-input" id="eccat_excerpt" value="<?php echo esc_attr( $this->post ? $this->post->post_excerpt : '' ); ?>" maxlength="160" data-track><span class="ecdv2-field-desc"><span id="eccat_excerpt_count"><?php echo $this->post ? (int) strlen( $this->post->post_excerpt ) : 0; ?></span>/160</span></div>
						<div class="ecdv2-field ecdv2-field-full">
							<label class="ecdv2-label"><?php esc_html_e( 'How it looks in Google', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'live preview', 'wp-easycart' ); ?></span></label>
							<div class="ecv2-serp">
								<div class="ecv2-serp-url"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?> <span id="eccat_serp_path"><?php echo esc_html( str_replace( home_url(), '', $this->permalink() ) ); ?></span></div>
								<div class="ecv2-serp-title" id="eccat_serp_title"><?php echo esc_html( $this->post ? $this->post->post_title : $this->category->category_name ); ?> – <?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
								<div class="ecv2-serp-desc" id="eccat_serp_desc"><?php echo esc_html( $this->post && '' !== $this->post->post_excerpt ? $this->post->post_excerpt : __( 'Google will pull text from the page — add a search excerpt above to control it.', 'wp-easycart' ) ); ?></div>
							</div>
							<span class="ecdv2-field-desc" id="eccat_serp_count"></span>
						</div>
					</div>
				</div>
			</div>

			<?php if ( $yoast_card ) { wp_easycart_admin_catalog_yoast_v2::print_card( array( 'id' => 'catv2-yoast', 'type' => 'category', 'entity_id' => (int) $c->category_id, 'name' => wp_unslash( $c->category_name ), 'label' => __( 'category', 'wp-easycart' ) ) ); } ?>

			<!-- Danger -->
			<div class="ecdv2-card ecos-danger" id="catv2-danger">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Danger zone', 'wp-easycart' ); ?></h3></div>
				<div class="ecdv2-card-body ecos-danger-body">
					<div><b><?php esc_html_e( 'Delete this category', 'wp-easycart' ); ?></b><span><?php esc_html_e( 'Products are never deleted. You will choose what happens to subcategories and products, and the old URL can redirect. Undo is available for 30 days.', 'wp-easycart' ); ?></span></div>
					<button type="button" class="ecv2-btn ecv2-btn-danger" onclick="ecv2_catalog.safe_delete( 'category', <?php echo (int) $c->category_id; ?>, { redirect_to: '<?php echo esc_js( $list_url ); ?>' } );"><?php esc_html_e( 'Delete category…', 'wp-easycart' ); ?></button>
				</div>
			</div>
		</div>
	</div>
	<div id="ecv2-toast-container"></div>
</div>
