<?php
/** Menu editor ( V2 ) template — $this is wp_easycart_admin_menu_editor_v2. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
include_once( EC_PLUGIN_DIRECTORY . '/admin/template/products/shared/editor-lite-parts.php' );
$m = $this->menu; $level = $this->level;
$list_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=menus' );
$permalink = $this->permalink();
$thumb_id = $this->post ? (int) get_post_thumbnail_id( $this->post ) : 0;
$banner_url = $m->banner_image ? ( 0 === strpos( $m->banner_image, 'http' ) ? $m->banner_image : plugins_url( '/wp-easycart-data/products/banners/' . ltrim( $m->banner_image, '/' ), EC_PLUGIN_DATA_DIRECTORY ) ) : '';
$path = $this->path();
$products_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=products&menu=' . $level . ':' . $m->menu_id );
/* Yoast card renders only while Yoast is active and the menu has its ec_store page. @since 6.0.0 */
$yoast_card = class_exists( 'wp_easycart_admin_catalog_yoast_v2' ) && wp_easycart_admin_catalog_yoast_v2::available( 'menu', (int) $m->menu_id, $level );

ecv2_lite_open( $this->js_data(), 'ecmenu-wrap' );
ecv2_lite_header( array(
	'back_url' => $list_url, 'back_title' => __( 'Back to menus', 'wp-easycart' ), 'title' => wp_unslash( $m->name ),
	'sub_html' => '<span class="ecv2-chip ecv2-chip-' . ( 1 === $level ? 'brand' : 'gray' ) . '">' . esc_html( wp_easycart_admin_menu_editor_v2::level_label( $level ) ) . '</span> · <span id="eclite_h_path">' . ( $path ? esc_html( implode( ' › ', $path ) . ' › ' . wp_unslash( $m->name ) ) : esc_html__( 'Top level', 'wp-easycart' ) ) . '</span> · <a href="' . esc_url( $products_url ) . '" id="eclite_h_count">' . esc_html( sprintf( _n( '%d product', '%d products', $this->product_total, 'wp-easycart' ), $this->product_total ) ) . '</a>' . ( $permalink ? ' · <span class="ecv2-mono" id="eclite_h_url">' . esc_html( wp_parse_url( $permalink, PHP_URL_PATH ) ) . '</span>' : '' ) . ' · ' . esc_html( sprintf( __( '%d clicks', 'wp-easycart' ), (int) $m->clicks ) ),
	'thumb_url' => $banner_url,
	'actions_html' => ( $permalink ? '<a class="ecv2-btn ecv2-btn-ghost" href="' . esc_url( $permalink ) . '" target="_blank" rel="noopener noreferrer" id="eclite_view">' . esc_html__( 'View on site', 'wp-easycart' ) . ' <span class="dashicons dashicons-external"></span></a>' : '' ) . ( $level < 3 ? '<button type="button" class="ecv2-btn" onclick="ecv2_catalog.new_menu( \'' . $level . ':' . (int) $m->menu_id . '\', ' . $level . ' );">' . esc_html__( 'Add sub-menu', 'wp-easycart' ) . '</button>' : '' ),
) );
?>
<div class="ecdv2-body">
	<?php
	$groups = array( __( 'Menu', 'wp-easycart' ) => array( array( '#mv2-details', __( 'Details', 'wp-easycart' ) ), array( '#mv2-images', __( 'Images', 'wp-easycart' ) ), array( '#mv2-products', __( 'Products', 'wp-easycart' ), $this->product_total ) ) );
	if ( $level < 3 ) { $groups[ __( 'Menu', 'wp-easycart' ) ][] = array( '#mv2-subs', __( 'Sub-menus', 'wp-easycart' ), count( $this->children ) ); }
	$groups[ __( 'Menu', 'wp-easycart' ) ][] = array( '#mv2-seo', __( 'SEO & URL', 'wp-easycart' ) );
	if ( $yoast_card ) { $groups[ __( 'Menu', 'wp-easycart' ) ][] = array( '#mv2-yoast', __( 'Yoast SEO', 'wp-easycart' ) ); }
	$groups[ __( 'More', 'wp-easycart' ) ] = array( array( '#mv2-danger', __( 'Danger zone', 'wp-easycart' ) ) );
	ecv2_lite_rail( $groups, $this->health(), __( 'Menu health', 'wp-easycart' ) );
	?>
	<div class="ecdv2-main">
		<?php ecv2_lite_card_open( 'mv2-details', __( 'Details', 'wp-easycart' ), '', '<a href="' . esc_url( $this->docs_link ) . '" target="_blank" class="ecdv2-help-link"><span class="dashicons dashicons-editor-help"></span>' . esc_html__( 'Help', 'wp-easycart' ) . '</a>' ); ?>
		<div class="ecdv2-grid">
			<?php ecv2_lite_field( 'eclite_name', __( 'Name', 'wp-easycart' ), ecv2_lite_text( 'eclite_name', 'name', wp_unslash( $m->name ), '', 'data-bind="title"' ) ); ?>
			<?php
			$opts = '';
			foreach ( $this->placements as $pl ) { $opts .= '<option value="' . esc_attr( $pl['value'] ) . '"' . ( $pl['current'] ? ' selected' : '' ) . ( $pl['disabled'] ? ' disabled' : '' ) . '>' . esc_html( $pl['label'] ) . ( $pl['disabled'] && ! $pl['current'] ? ' ' . esc_html__( '(not available)', 'wp-easycart' ) : '' ) . '</option>'; }
			ecv2_lite_field( 'eclite_placement', __( 'Parent menu', 'wp-easycart' ), '<select class="ecv2-select ecos-select" id="eclite_placement" data-field="placement" data-track>' . $opts . '</select>', '', __( 'Top level, or nested under a menu or sub-menu. Moving to a different level gives the menu a new id; links on your site keep working through its page. Products in this menu follow it.', 'wp-easycart' ) );
			?>
			<?php ecv2_lite_field( 'eclite_order', __( 'Display order', 'wp-easycart' ), '<input type="number" class="ecv2-input" id="eclite_order" data-field="menu_order" value="' . (int) $m->menu_order . '" style="max-width:120px" data-track>', __( 'lower shows first', 'wp-easycart' ), __( 'Or drag rows on the Menus list.', 'wp-easycart' ) ); ?>
		</div>
		<?php ecv2_lite_card_close(); ?>

		<?php ecv2_lite_card_open( 'mv2-images', __( 'Images', 'wp-easycart' ), __( 'The banner shows at the top of this menu\'s store page; the featured image is used by themes and social previews', 'wp-easycart' ) ); ?>
		<div class="ecdv2-grid">
			<?php ecv2_lite_image_slot( 'eclite_banner', 'banner_image', $m->banner_image, $banner_url, __( 'Banner image', 'wp-easycart' ), __( 'Recommended 1600×400', 'wp-easycart' ), 'url', true ); ?>
			<?php ecv2_lite_image_slot( 'eclite_featured', 'featured_image', $thumb_id, $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '', __( 'Featured image', 'wp-easycart' ), __( 'Optional', 'wp-easycart' ), 'id' ); ?>
		</div>
		<?php ecv2_lite_card_close(); ?>

		<?php
		/* Products card: list + "Add product" typeahead ( ec_admin_ajax_ecv2_product_search → ecv2_menu_add_product ) and a per-row × ( ecv2_menu_remove_product ).
		   Built here rather than through ecv2_lite_products_card() so the combo sits inside the card; ids match what catalog-editor-lite-v2.js refreshes. @since 6.0.0 */
		ecv2_lite_card_open( 'mv2-products', __( 'Products', 'wp-easycart' ), sprintf( _n( '%d product has this menu in one of its three menu paths', '%d products have this menu in one of their three menu paths', $this->product_total, 'wp-easycart' ), $this->product_total ), '<a href="#" class="ecdv2-help-link ecos-link-brand" onclick="eclite.focus_product_search(); return false;">+ ' . esc_html__( 'Add product', 'wp-easycart' ) . '</a>', 'eclite_products_hint' );
		?>
		<div class="eccat-combo" id="eclite_product_combo">
			<div class="eccat-tokens"><input type="text" id="eclite_product_search" placeholder="<?php esc_attr_e( 'Type a product name or SKU to add it to this menu…', 'wp-easycart' ); ?>" autocomplete="off"></div>
			<div class="eccat-dd" id="eclite_product_dd" style="display:none"></div>
		</div>
		<div class="ecos-hint eclite-products-note"><?php esc_html_e( 'Adding fills the product’s first empty menu path ( each product has three ); removing clears the path that runs through this menu. Changes apply immediately.', 'wp-easycart' ); ?></div>
		<div class="ecos-empty" id="eclite_products_empty"<?php if ( $this->products ) { echo ' style="display:none"'; } ?>><?php esc_html_e( 'No products use this menu yet. Add one above, or assign menus from each product’s editor.', 'wp-easycart' ); ?></div>
		<div class="ecos-used" id="eclite_products"><?php foreach ( $this->products as $u ) { echo wp_easycart_admin_menu_editor_v2::product_row_html( $u ); } ?></div>
		<div class="ecos-used-foot" id="eclite_products_footwrap"<?php if ( ! $this->products ) { echo ' style="display:none"'; } ?>><span class="ecos-hint" id="eclite_products_foot"><?php echo esc_html( sprintf( __( 'Showing %1$d of %2$d', 'wp-easycart' ), count( $this->products ), $this->product_total ) ); ?></span><span class="ecos-grow"></span><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="eclite_more" onclick="eclite.load_more( this );"<?php if ( $this->product_total <= count( $this->products ) ) { echo ' style="display:none"'; } ?>><?php esc_html_e( 'Show more', 'wp-easycart' ); ?></button><a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( $products_url ); ?>"><?php esc_html_e( 'View all in Products', 'wp-easycart' ); ?></a></div>
		<?php ecv2_lite_card_close(); ?>

		<?php if ( $level < 3 ) {
			ecv2_lite_card_open( 'mv2-subs', wp_easycart_admin_menu_editor_v2::level_label( $level + 1 ) . 's', $this->children ? sprintf( __( '%d items', 'wp-easycart' ), count( $this->children ) ) : __( 'None yet', 'wp-easycart' ), '<a href="#" class="ecdv2-help-link ecos-link-brand" onclick="ecv2_catalog.new_menu( \'' . $level . ':' . (int) $m->menu_id . '\', ' . $level . ' ); return false;">+ ' . esc_html__( 'Add', 'wp-easycart' ) . '</a>' );
			if ( empty( $this->children ) ) { echo '<div class="ecos-hint">' . esc_html__( 'Sub-menus appear under this menu in the store navigation.', 'wp-easycart' ) . '</div>'; }
			else {
				echo '<div class="ecos-used">';
				foreach ( $this->children as $ch ) { $u = wp_easycart_admin_menu_table::editor_url( $level + 1, $ch->menu_id ); echo '<div class="ecos-u"><div class="ecos-u-main"><a class="ecv2-link-primary" href="' . esc_url( $u ) . '">' . esc_html( wp_unslash( $ch->name ) ) . '</a><span class="ecv2-sub">' . esc_html( sprintf( __( 'Order %d · %d clicks', 'wp-easycart' ), (int) $ch->menu_order, (int) $ch->clicks ) ) . '</span></div><a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' . esc_url( $u ) . '">' . esc_html__( 'Open', 'wp-easycart' ) . '</a></div>'; }
				echo '</div>';
			}
			ecv2_lite_card_close();
		} ?>

		<?php
		$legacy = '<div class="ecdv2-field"><label class="ecdv2-label" for="eclite_kw">' . esc_html__( 'Keywords', 'wp-easycart' ) . ' <span class="ecdv2-label-hint">' . esc_html__( 'legacy meta keywords', 'wp-easycart' ) . '</span></label>' . ecv2_lite_text( 'eclite_kw', 'seo_keywords', wp_unslash( $m->seo_keywords ) ) . '</div>';
		$legacy .= '<div class="ecdv2-field"><label class="ecdv2-label" for="eclite_seodesc">' . esc_html__( 'Description', 'wp-easycart' ) . ' <span class="ecdv2-label-hint">' . esc_html__( 'legacy meta description', 'wp-easycart' ) . '</span></label><textarea class="ecv2-input" id="eclite_seodesc" data-field="seo_description" rows="2" data-track>' . esc_textarea( (string) wp_unslash( $m->seo_description ) ) . '</textarea></div>';
		/* When the Yoast card renders, the SEO card drops its "Edit in Yoast" link ( the card below has its own ). */
		ecv2_lite_seo_card( 'mv2-seo', $this->post, $this->slug_prefix(), $this->yoast && ! $yoast_card, $legacy );
		if ( $yoast_card ) { wp_easycart_admin_catalog_yoast_v2::print_card( array( 'id' => 'mv2-yoast', 'type' => 'menu', 'entity_id' => (int) $m->menu_id, 'level' => $level, 'name' => wp_unslash( $m->name ), 'label' => __( 'menu', 'wp-easycart' ) ) ); }
		ecv2_lite_danger_card( 'mv2-danger', __( 'Delete this menu', 'wp-easycart' ), __( 'Products are never deleted; their menu path is trimmed. Sub-menus can be moved to another menu. Undo is available for 30 days.', 'wp-easycart' ), __( 'Delete menu…', 'wp-easycart' ), 'menu' . $level, $m->menu_id, $list_url );
		?>
	</div>
</div>
<?php ecv2_lite_close();
