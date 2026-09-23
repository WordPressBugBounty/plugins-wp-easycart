<?php
/**
 * WP EasyCart Admin — Catalog V2 shared bootstrap.
 *
 * Enqueues the V2 styles/scripts for the Option Sets and Categories pages and
 * localizes ecv2_catalog_vars. Also exposes the "Recently deleted" panel for
 * Store Status.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_admin_catalog_v2_enqueue' ) ) :

	/**
	 * @param string $mode option-list | option-editor | category-list | category-editor
	 */
	/**
 * Columns every product-thumbnail query needs, so wp_easycart_admin_catalog_v2_product_thumb()
 * can resolve gallery attachments, external URLs, optionitem images and legacy image1-5 alike.
 * $alias is the ec_product alias used in the query.
 */
function wp_easycart_admin_catalog_v2_thumb_select( $alias = 'ec_product' ) {
	$a = $alias;
	return "$a.image1, $a.image2, $a.image3, $a.image4, $a.image5, $a.product_images, $a.use_optionitem_images, "
		. "( SELECT oii.product_images FROM ec_optionitemimage AS oii LEFT JOIN ec_optionitem AS oi ON oi.optionitem_id = oii.optionitem_id WHERE oii.product_id = $a.product_id AND oii.product_images IS NOT NULL AND oii.product_images != '' ORDER BY oi.optionitem_order ASC LIMIT 1 ) AS oi_first_product_images, "
		. "( SELECT oii2.image1 FROM ec_optionitemimage AS oii2 LEFT JOIN ec_optionitem AS oi2 ON oi2.optionitem_id = oii2.optionitem_id WHERE oii2.product_id = $a.product_id AND oii2.image1 IS NOT NULL AND oii2.image1 != '' ORDER BY oi2.optionitem_order ASC LIMIT 1 ) AS oi_first_image1";
}

/** Resolve a product row ( selected with the columns above ) to a thumbnail URL, or ''. */
function wp_easycart_admin_catalog_v2_product_thumb( $row ) {
	if ( ! $row ) { return ''; }
	$row = (object) $row;
	if ( class_exists( 'wp_easycart_admin_product_table' ) && method_exists( 'wp_easycart_admin_product_table', 'resolve_thumbnail_url' ) ) {
		return (string) wp_easycart_admin_product_table::resolve_thumbnail_url( $row );
	}
	/* Fallback when the product table class isn't loaded: gallery CSV first, then legacy image1. */
	if ( ! empty( $row->product_images ) ) {
		foreach ( explode( ',', $row->product_images ) as $item ) {
			$item = trim( $item );
			if ( is_numeric( $item ) ) { $src = wp_get_attachment_image_src( (int) $item, 'medium' ); if ( $src ) { return $src[0]; } }
			else if ( 0 === strpos( $item, 'image:' ) ) { return substr( $item, 6 ); }
			else if ( 0 === strpos( $item, 'http' ) ) { return $item; }
		}
	}
	return ! empty( $row->image1 ) ? wp_easycart_admin_table_v2::get_image_url( $row->image1 ) : '';
}

function wp_easycart_admin_catalog_v2_enqueue( $mode ) {
		static $done = false;
		if ( $done ) { return; }
		$done = true;

		$css = plugins_url( 'wp-easycart/admin/css/', EC_PLUGIN_DIRECTORY );
		$js  = plugins_url( 'wp-easycart/admin/js/', EC_PLUGIN_DIRECTORY );

		wp_enqueue_style( 'wp_easycart_admin_v2_css', $css . 'admin-v2.css', array(), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wp_easycart_admin_details_v2_css', $css . 'admin-details-v2.css', array( 'wp_easycart_admin_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wp_easycart_admin_catalog_v2_css', $css . 'catalog-v2.css', array( 'wp_easycart_admin_details_v2_css' ), EC_CURRENT_VERSION );

		wp_enqueue_media();
		wp_enqueue_script( 'wp_easycart_admin_catalog_v2_js', $js . 'catalog-v2.js', array( 'jquery' ), EC_CURRENT_VERSION, true );
		if ( 'option-editor' === $mode ) {
			wp_enqueue_script( 'wp_easycart_admin_option_editor_v2_js', $js . 'option-editor-v2.js', array( 'jquery', 'wp_easycart_admin_catalog_v2_js' ), EC_CURRENT_VERSION, true );
		}
		if ( 'category-editor' === $mode ) {
			wp_enqueue_script( 'wp_easycart_admin_category_editor_v2_js', $js . 'category-editor-v2.js', array( 'jquery', 'wp_easycart_admin_catalog_v2_js' ), EC_CURRENT_VERSION, true );
		}
		if ( in_array( $mode, array( 'pricepoint', 'perpage', 'log-list', 'user-role-list', 'user-role-editor', 'subscribers', 'countries' ), true ) ) {
			wp_enqueue_style( 'wp_easycart_admin_settings_lists_v2_css', $css . 'settings-lists-v2.css', array( 'wp_easycart_admin_catalog_v2_css' ), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_settings_lists_v2_js', $js . 'settings-lists-v2.js', array( 'jquery', 'wp_easycart_admin_catalog_v2_js' ), EC_CURRENT_VERSION, true );
		}
		if ( 'review-requests' === $mode ) {
			wp_enqueue_script( 'wp_easycart_admin_review_requests_v2_js', $js . 'review-requests-v2.js', array( 'jquery', 'wp_easycart_admin_catalog_v2_js' ), EC_CURRENT_VERSION, true );
		}
		if ( in_array( $mode, array( 'menu-editor', 'manufacturer-editor', 'review-editor', 'user-role-editor' ), true ) ) {
			wp_enqueue_script( 'wp_easycart_admin_catalog_editor_lite_v2_js', $js . 'catalog-editor-lite-v2.js', array( 'jquery', 'wp_easycart_admin_catalog_v2_js' ), EC_CURRENT_VERSION, true );
		}
		if ( 'user-role-editor' === $mode ) {
			/* Role prices card ( 6.0.0 ): runs after settings-lists-v2.js, which defines window.ecrole. */
			wp_enqueue_script( 'wp_easycart_admin_user_role_editor_v2_js', $js . 'user-role-editor-v2.js', array( 'jquery', 'wp_easycart_admin_catalog_v2_js', 'wp_easycart_admin_settings_lists_v2_js' ), EC_CURRENT_VERSION, true );
		}

		/*
		 * Reference lists are localized only for the modes that use them, and never as a
		 * whole table: category parents are searched through ecv2_category_search
		 * ( catalog-v2.js typeahead ), and the menu parent list ( menu pages only ) is
		 * capped at 500 entries with a flag so the UI can say so.
		 *
		 * @since 6.0.0
		 */
		$menus        = array();
		$menus_capped = false;
		if ( in_array( $mode, array( 'menu-list', 'menu-editor' ), true ) && class_exists( 'wp_easycart_admin_menu_table' ) ) {
			$menus = wp_easycart_admin_menu_table::parent_options( 2, 501 );
			if ( count( $menus ) > 500 ) {
				$menus        = array_slice( $menus, 0, 500 );
				$menus_capped = true;
			}
		}
		$store_page = get_permalink( (int) get_option( 'ec_option_storepage' ) );
		$store_base = $store_page ? rtrim( (string) wp_parse_url( $store_page, PHP_URL_PATH ), '/' ) . '/' : '/store/';

		wp_localize_script( 'wp_easycart_admin_catalog_v2_js', 'ecv2_catalog_vars', array(
			'mode'         => $mode,
			'menus'        => $menus,
			'menus_capped' => $menus_capped,
			'store_base'   => $store_base,
			'nonces'       => array(
				'rolev2'          => wp_create_nonce( 'wp-easycart-rolev2' ),
				'inline_update'   => wp_create_nonce( 'wp-easycart-ecv2-inline-update' ),
				'safe_delete'     => wp_create_nonce( wp_easycart_admin_safe_delete::NONCE ),
				'osv2'            => wp_create_nonce( 'wp-easycart-osv2' ),
				'catv2'           => wp_create_nonce( 'wp-easycart-catv2' ),
				'stat_toggle'     => wp_create_nonce( 'wp-easycart-ecv2-stat-toggle' ),
				'category_search' => wp_create_nonce( 'wp-easycart-ecv2-category-search' ),
			),
			'lang'         => array(
				'type_to_search'         => __( 'Type to search categories…', 'wp-easycart' ),
				'searching'              => __( 'Searching…', 'wp-easycart' ),
				'no_categories_match'    => __( 'No matching categories.', 'wp-easycart' ),
				'menus_capped'           => __( 'Showing the first 500 menus. Drag rows on the Menus list to place a menu elsewhere.', 'wp-easycart' ),
				'load_children'          => __( 'Show subcategories', 'wp-easycart' ),
				/* translators: %d: number of subcategories still hidden */
				'show_more_children'     => __( 'Show %d more…', 'wp-easycart' ),
				'loading'                => __( 'Loading…', 'wp-easycart' ),
				'saved'                  => __( 'Saved', 'wp-easycart' ),
				'error'                  => __( 'Something went wrong. Please try again.', 'wp-easycart' ),
				'cancel'                 => __( 'Cancel', 'wp-easycart' ),
				'delete'                 => __( 'Delete', 'wp-easycart' ),
				'deleting'               => __( 'Deleting…', 'wp-easycart' ),
				'analyzing'              => __( 'Checking what this affects…', 'wp-easycart' ),
				/* translators: %s: record name */
				'delete_q'               => __( 'Delete “%s”?', 'wp-easycart' ),
				'recommended'            => __( 'Recommended', 'wp-easycart' ),
				'no_targets'             => __( 'Nothing to move to yet.', 'wp-easycart' ),
				'redirect'               => __( 'Redirect the old URL', 'wp-easycart' ),
				'redirect_to'            => __( 'the target category, or the store page', 'wp-easycart' ),
				/* translators: %d: days */
				'undo_note'              => __( 'You can undo this for %d days from Store Status › Recently deleted.', 'wp-easycart' ),
				'undo'                   => __( 'Undo', 'wp-easycart' ),
				'restoring'              => __( 'Restoring…', 'wp-easycart' ),
				'view'                   => __( 'View', 'wp-easycart' ),
				'bulk_no_action'         => __( 'Please select a bulk action.', 'wp-easycart' ),
				'bulk_none_selected'     => __( 'Select some rows first.', 'wp-easycart' ),
				'bulk_max_exceeded'      => __( 'Too many selected. Narrow your selection.', 'wp-easycart' ),
				'bulk_done'              => __( 'Updated %d items.', 'wp-easycart' ),
				'bulk_deleted'           => __( 'Deleted %d items.', 'wp-easycart' ),
				'bulk_delete_options'    => __( 'Delete %d option sets?', 'wp-easycart' ),
				'bulk_delete_options_msg'=> __( 'Each set is removed from its products and their variant stock rows for it are deleted. Each deletion can be undone for 30 days.', 'wp-easycart' ),
				'bulk_delete_categories' => __( 'Delete %d categories?', 'wp-easycart' ),
				'bulk_delete_categories_msg' => __( 'Products are never deleted. Subcategories move up a level and old URLs redirect to the store page. Each deletion can be undone for 30 days.', 'wp-easycart' ),
				'nested'                 => __( 'Moved into “%s”', 'wp-easycart' ),
				'reordered'              => __( 'Order saved', 'wp-easycart' ),
				'now_top'                => __( 'Moved to the top level', 'wp-easycart' ),
				/* translators: %d: tree level number ( 1 = top ) */
				'relevelled'             => __( 'Moved to level %d', 'wp-easycart' ),
				/* translators: %s: menu or category name */
				'drop_nest'              => __( 'Nest under “%s”', 'wp-easycart' ),
				/* translators: %s: menu or category name */
				'drop_inside'            => __( 'Inside “%s”', 'wp-easycart' ),
				'drop_top'               => __( 'Top level', 'wp-easycart' ),
				'drop_too_deep'          => __( 'Too deep — menus go three levels, counting sub-menus', 'wp-easycart' ),
				'drop_not_allowed'       => __( 'Can’t drop here', 'wp-easycart' ),
				'drag_keys'              => __( 'Move: arrow keys ( left / right change level )', 'wp-easycart' ),
				'no_page'                => __( 'This category has no page yet — save it once in the editor.', 'wp-easycart' ),
				'assign_title'           => __( 'Assign to products', 'wp-easycart' ),
				'assign'                 => __( 'Assign', 'wp-easycart' ),
				'assigned'               => __( 'Assigned to %d products.', 'wp-easycart' ),
				'stock_created'          => __( '%d variant stock rows created.', 'wp-easycart' ),
				'slots_note'             => __( 'Products have %d option-set slots. Products with every slot full are shown but cannot be selected.', 'wp-easycart' ),
				'slots_full'             => __( 'Slots full', 'wp-easycart' ),
				'modifier_note'          => __( 'This modifier is added after any modifiers the product already has.', 'wp-easycart' ),
				'modifier'               => __( 'Modifier', 'wp-easycart' ),
				'free'                   => __( 'free', 'wp-easycart' ),
				'uses'                   => __( 'uses', 'wp-easycart' ),
				'search_products'        => __( 'Search products by name or SKU…', 'wp-easycart' ),
				'no_matches'             => __( 'No matching products.', 'wp-easycart' ),
				'showing'                => __( 'Showing %1 of %2', 'wp-easycart' ),
				'select_shown'           => __( 'Select all shown', 'wp-easycart' ),
				'add_products_title'     => __( 'Add products', 'wp-easycart' ),
				'add_to_category'        => __( 'Add to category', 'wp-easycart' ),
				'products_added'         => __( 'Added %d products.', 'wp-easycart' ),
				'not_in_cat'             => __( 'Not in this category', 'wp-easycart' ),
				'uncategorized'          => __( 'Uncategorized only', 'wp-easycart' ),
				'all_products'           => __( 'All products', 'wp-easycart' ),
				'active'                 => __( 'Active', 'wp-easycart' ),
				'inactive'               => __( 'Inactive', 'wp-easycart' ),
				'new_category'           => __( 'New category', 'wp-easycart' ),
				'name'                   => __( 'Name', 'wp-easycart' ),
				'name_ph'                => __( 'e.g. Accessories', 'wp-easycart' ),
				'parent'                 => __( 'Parent', 'wp-easycart' ),
				'visibility'             => __( 'Visibility', 'wp-easycart' ),
				'top_level'              => __( '— Top level —', 'wp-easycart' ),
				'nc_hint'                => __( 'You can add products, images and a description after creating.', 'wp-easycart' ),
				'create'                 => __( 'Create', 'wp-easycart' ),
				'create_edit'            => __( 'Create & edit', 'wp-easycart' ),
				'created'                => __( 'Created “%s”', 'wp-easycart' ),
				'move_title'             => __( 'Move %d categories under…', 'wp-easycart' ),
				'new_parent'             => __( 'New parent', 'wp-easycart' ),
				'move_hint'              => __( 'Categories keep their products and subcategories. URLs do not change.', 'wp-easycart' ),
				'move'                   => __( 'Move', 'wp-easycart' ),
				'moved'                  => __( 'Moved %d categories.', 'wp-easycart' ),
				'field_updated'          => __( 'Field updated — click Undo to revert.', 'wp-easycart' ),
				'reverted'               => __( 'Reverted', 'wp-easycart' ),
				'pending'                => __( 'Pending', 'wp-easycart' ),
				'approved'               => __( 'Approved — now visible in the store', 'wp-easycart' ),
				'denied'                 => __( 'Denied — hidden from shoppers', 'wp-easycart' ),
				'new_menu'               => __( 'New menu', 'wp-easycart' ),
				'menu_name_ph'           => __( 'e.g. Shop by Brand', 'wp-easycart' ),
				'menu_parent_hint'       => __( 'Top-level menus, sub-menus and sub-sub-menus — three levels at most.', 'wp-easycart' ),
				'top_level_menu'         => __( '— Top-level menu —', 'wp-easycart' ),
				'menu_depth'             => __( 'Menus go three levels deep at most.', 'wp-easycart' ),
				'new_manufacturer'       => __( 'New manufacturer', 'wp-easycart' ),
				'mf_name_ph'             => __( 'e.g. Acme Co.', 'wp-easycart' ),
				'mf_hint'                => __( 'A store page is created automatically. Assign products after creating.', 'wp-easycart' ),
				'assign_products_title'  => __( 'Assign products', 'wp-easycart' ),
				'mf_unassigned'          => __( 'Without a manufacturer', 'wp-easycart' ),
				'mf_any'                 => __( 'Any product (reassign)', 'wp-easycart' ),
				'currently'              => __( 'currently', 'wp-easycart' ),
				'no_manufacturer'        => __( 'no manufacturer', 'wp-easycart' ),
				'products_assigned'      => __( 'Assigned %d products.', 'wp-easycart' ),
				'product_gone'           => __( 'That product no longer exists.', 'wp-easycart' ),
				'delete_review_q'        => __( 'Delete this review?', 'wp-easycart' ),
				'delete_review_msg'      => __( 'It is removed from the store immediately. You can undo for 15 minutes. If you only want to hide it, deny it instead.', 'wp-easycart' ),
				'bulk_delete_menus'      => __( 'Delete %d menus?', 'wp-easycart' ),
				'bulk_delete_menus_msg'  => __( 'Products are never deleted; their menu paths are trimmed. Sub-menus of a deleted menu are deleted too. Old URLs redirect to the store page. Undo for 30 days.', 'wp-easycart' ),
				'bulk_delete_manufacturers' => __( 'Delete %d manufacturers?', 'wp-easycart' ),
				'bulk_delete_manufacturers_msg' => __( 'Products keep everything except their manufacturer. Old URLs redirect to the store page. Undo for 30 days.', 'wp-easycart' ),
				'bulk_delete_reviews'    => __( 'Delete %d reviews?', 'wp-easycart' ),
				'bulk_delete_reviews_msg'=> __( 'Reviews are removed from the store immediately. Undo is available for 15 minutes.', 'wp-easycart' ),
				'applying_filters'       => __( 'Applying filters…', 'wp-easycart' ),
				'clear_filter'           => __( 'Clear this filter', 'wp-easycart' ),
			),
		) );
	}

endif;

if ( ! function_exists( 'wp_easycart_admin_catalog_recent_trash_html' ) ) :

	/**
	 * "Recently deleted" panel for Store Status. Call from the status template:
	 *   echo wp_easycart_admin_catalog_recent_trash_html();
	 * Requires catalog-v2.js on that page for the Restore buttons; falls back to a
	 * plain list otherwise.
	 */
	function wp_easycart_admin_catalog_recent_trash_html( $limit = 10 ) {
		if ( ! class_exists( 'wp_easycart_admin_safe_delete' ) ) { return ''; }
		$items = array_slice( wp_easycart_admin_safe_delete()->list_trash(), 0, $limit, true );
		$nonce = wp_create_nonce( wp_easycart_admin_safe_delete::NONCE );
		$html  = '<div class="ecv2-recent-trash" data-nonce="' . esc_attr( $nonce ) . '">';
		if ( empty( $items ) ) {
			return $html . '<p class="ecos-hint">' . esc_html__( 'Nothing deleted in the last 30 days.', 'wp-easycart' ) . '</p></div>';
		}
		foreach ( $items as $id => $e ) {
			$labels = array( 'option' => __( 'Option set', 'wp-easycart' ), 'category' => __( 'Category', 'wp-easycart' ), 'manufacturer' => __( 'Manufacturer', 'wp-easycart' ), 'menu1' => __( 'Menu', 'wp-easycart' ), 'menu2' => __( 'Sub-menu', 'wp-easycart' ), 'menu3' => __( 'Sub-sub-menu', 'wp-easycart' ), 'customer' => __( 'Customer', 'wp-easycart' ) );
			$type = isset( $labels[ $e['type'] ] ) ? $labels[ $e['type'] ] : $e['type'];
			$user = get_userdata( (int) $e['user'] );
			$html .= '<div class="ecos-u"><div class="ecos-u-main"><b>' . esc_html( $e['name'] ) . '</b> <span class="ecv2-chip ecv2-chip-gray">' . esc_html( $type ) . '</span><span class="ecv2-sub">' . esc_html( sprintf( __( 'Deleted %1$s by %2$s · restorable until %3$s', 'wp-easycart' ), human_time_diff( $e['time'] ) . ' ' . __( 'ago', 'wp-easycart' ), $user ? $user->display_name : '—', date_i18n( get_option( 'date_format' ), $e['expires'] ) ) ) . '</span></div>';
			$html .= '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-trash-restore" data-trash-id="' . esc_attr( $id ) . '">' . esc_html__( 'Restore', 'wp-easycart' ) . '</button></div>';
		}
		$html .= '</div>';
		$html .= '<script>jQuery(function($){$(document).on("click",".ecv2-trash-restore",function(){var $b=$(this).prop("disabled",true);$.post(ajaxurl,{action:"ecv2_delete_restore",nonce:$b.closest(".ecv2-recent-trash").data("nonce"),trash_id:$b.data("trash-id")},function(r){if(r&&r.success){$b.closest(".ecos-u").fadeOut();}else{$b.prop("disabled",false);alert((r&&r.data&&r.data.message)||"Could not restore.");}},"json");});});</script>';
		return $html;
	}

endif;

/* ---------------------------------------------------------------------- */
/* Search-as-you-type pickers ( product editor, product slideout )         */
/* ---------------------------------------------------------------------- */

if ( ! function_exists( 'ecv2_manufacturer_search' ) ) :

	/**
	 * Paged manufacturer search for select2 pickers. Read-only.
	 * POST: q, page ( 1-based ), wp_easycart_nonce ( 'wp-easycart-ecv2-manufacturer-search' ).
	 * Answers { results: [ { id, text } ], more }. Page 1 starts with the "No Manufacturer" row.
	 *
	 * @since 6.0.0
	 */
	function ecv2_manufacturer_search() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) && ! current_user_can( 'wpec_manager' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
		}
		check_ajax_referer( 'wp-easycart-ecv2-manufacturer-search', 'wp_easycart_nonce' );
		global $wpdb;
		$q        = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
		$page     = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page = 40;
		$offset   = ( $page - 1 ) * $per_page;
		if ( '' !== $q ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT manufacturer_id, name FROM ec_manufacturer WHERE name LIKE %s ORDER BY name ASC LIMIT %d OFFSET %d', '%' . $wpdb->esc_like( $q ) . '%', $per_page + 1, $offset ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT manufacturer_id, name FROM ec_manufacturer ORDER BY name ASC LIMIT %d OFFSET %d', $per_page + 1, $offset ) );
		}
		$more = ( count( $rows ) > $per_page );
		if ( $more ) {
			array_pop( $rows );
		}
		$results = array();
		if ( 1 === $page && '' === $q ) {
			$results[] = array( 'id' => 0, 'text' => __( 'No Manufacturer', 'wp-easycart' ) );
		}
		foreach ( $rows as $r ) {
			$results[] = array( 'id' => (int) $r->manufacturer_id, 'text' => wp_unslash( (string) $r->name ) );
		}
		wp_send_json_success( array( 'results' => $results, 'more' => $more ) );
	}
	add_action( 'wp_ajax_ecv2_manufacturer_search', 'ecv2_manufacturer_search' );

endif;

if ( ! function_exists( 'ecv2_option_set_search' ) ) :

	/**
	 * Paged option-set search for the product option-slot pickers. Read-only.
	 * POST: q, page ( 1-based ), wp_easycart_nonce ( 'wp-easycart-ecv2-option-set-search' ).
	 * Answers { results: [ { id, text, option_name, item_count } ], more }. Basic
	 * ( combo / swatch ) sets only; PRO may widen the type list through the filter.
	 *
	 * @since 6.0.0
	 */
	function ecv2_option_set_search() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) && ! current_user_can( 'wpec_manager' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
		}
		check_ajax_referer( 'wp-easycart-ecv2-option-set-search', 'wp_easycart_nonce' );
		global $wpdb;
		$q        = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
		$page     = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page = 30;
		$offset   = ( $page - 1 ) * $per_page;
		$types    = (array) apply_filters( 'wp_easycart_admin_ecv2_option_set_search_types', array( 'basic-combo', 'basic-swatch' ) );
		$types    = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		if ( empty( $types ) ) {
			$types = array( 'basic-combo', 'basic-swatch' );
		}
		$type_sql = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$args     = $types;
		$where    = 'WHERE o.option_type IN ( ' . $type_sql . ' )';
		if ( '' !== $q ) {
			$where .= ' AND o.option_name LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $q ) . '%';
		}
		$args[] = $per_page + 1;
		$args[] = $offset;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT o.option_id, o.option_name, ( SELECT COUNT(*) FROM ec_optionitem i WHERE i.option_id = o.option_id ) AS item_count FROM ec_option o ' . $where . ' ORDER BY o.option_name ASC LIMIT %d OFFSET %d', $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is literal SQL with %s / %d placeholders only; every value is passed through $args.
		$more = ( count( $rows ) > $per_page );
		if ( $more ) {
			array_pop( $rows );
		}
		$results = array();
		foreach ( $rows as $r ) {
			$name = wp_unslash( (string) $r->option_name );
			$results[] = array(
				'id'          => (int) $r->option_id,
				'text'        => $name . ' (' . (int) $r->item_count . ')',
				'option_name' => $name,
				'item_count'  => (int) $r->item_count,
			);
		}
		wp_send_json_success( array( 'results' => $results, 'more' => $more ) );
	}
	add_action( 'wp_ajax_ecv2_option_set_search', 'ecv2_option_set_search' );

endif;

/* ---------------------------------------------------------------------- */
/* Yoast SEO card for the catalog editors ( category / menu / manufacturer ) */
/* ---------------------------------------------------------------------- */

if ( ! class_exists( 'wp_easycart_admin_catalog_yoast_v2' ) ) :

	/**
	 * Yoast SEO for the ec_store pages behind categories, menus and manufacturers.
	 *
	 * Mirrors the product editor's Yoast card ( wp_easycart_admin_details_products_v2::print_yoast_seo_v2 )
	 * and its save handler ( ec_admin_ajax_save_product_details_yoast_seo ): same meta keys, same
	 * sanitisation, empty values delete the meta so Yoast falls back to its templates. print_card()
	 * renders nothing unless Yoast is active and the entity has a linked post; write_meta() is the
	 * shared writer the product handler can call too.
	 *
	 * @since 6.0.0
	 */
	class wp_easycart_admin_catalog_yoast_v2 {

		const NONCE = 'wp-easycart-ecv2-catalog-yoast';

		public static function active() {
			return defined( 'WPSEO_VERSION' );
		}

		public static function types() {
			return array( 'category', 'menu', 'manufacturer' );
		}

		/** ec_store post id linked to an entity, or 0 when unlinked / the post is gone. $level is for menus ( 1..3 ). */
		public static function post_id( $type, $id, $level = 0 ) {
			global $wpdb;
			$id = (int) $id;
			if ( ! $id ) { return 0; }
			switch ( $type ) {
				case 'category':
					$post_id = $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_category WHERE category_id = %d', $id ) );
					break;
				case 'manufacturer':
					$post_id = $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_manufacturer WHERE manufacturer_id = %d', $id ) );
					break;
				case 'menu':
					$level   = max( 1, min( 3, (int) $level ) );
					$post_id = $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ec_menulevel' . $level . ' WHERE menulevel' . $level . '_id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are built from (int) $level clamped to 1..3.
					break;
				default:
					return 0;
			}
			$post_id = (int) $post_id;
			return ( $post_id && get_post( $post_id ) ) ? $post_id : 0;
		}

		/**
		 * Write the Yoast meta. $values is keyed by meta key and already sanitised; '' deletes the key
		 * ( Yoast's own behavior, so templates apply again ). Returns the values written.
		 */
		public static function write_meta( $post_id, $values ) {
			$post_id = (int) $post_id;
			foreach ( $values as $meta_key => $meta_value ) {
				if ( '' === $meta_value || null === $meta_value ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $meta_value );
				}
			}
			return $values;
		}

		/** Current Yoast values for a post, keyed by short name. */
		public static function values( $post_id ) {
			$noindex = (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
			if ( ! in_array( $noindex, array( '0', '1', '2' ), true ) ) { $noindex = '0'; }
			return array(
				'title'     => (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ),
				'metadesc'  => (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
				'focuskw'   => (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
				'canonical' => (string) get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
				'noindex'   => $noindex,
			);
		}

		/** Yoast's title / description templates for the post's type, resolved for this post. */
		public static function defaults( $post, $fallback_title ) {
			$out = array( 'title' => $fallback_title, 'desc' => '', 'type_noindex' => false );
			if ( ! $post || ! class_exists( 'WPSEO_Options' ) || ! method_exists( 'WPSEO_Options', 'get' ) ) { return $out; }
			$post_type      = get_post_type( $post );
			$title_template = (string) WPSEO_Options::get( 'title-' . $post_type, '' );
			$desc_template  = (string) WPSEO_Options::get( 'metadesc-' . $post_type, '' );
			$out['type_noindex'] = (bool) WPSEO_Options::get( 'noindex-' . $post_type, false );
			if ( function_exists( 'wpseo_replace_vars' ) ) {
				if ( '' !== $title_template ) { $out['title'] = wpseo_replace_vars( $title_template, $post ); }
				if ( '' !== $desc_template ) { $out['desc'] = wpseo_replace_vars( $desc_template, $post ); }
			}
			return $out;
		}

		/** True when print_card() would render for this entity. */
		public static function available( $type, $id, $level = 0 ) {
			return self::active() && self::post_id( $type, $id, $level ) > 0;
		}

		/**
		 * Print the card. $a: id ( element id ), type, entity_id, level ( menus ), name ( fallback title ),
		 * label ( 'category' / 'menu' / 'manufacturer', already translated, for the copy ).
		 * Fields carry data-track ( dirty pill ) but no data-field: the editor scripts post them to
		 * ecv2_catalog_save_yoast after their own save succeeds.
		 */
		public static function print_card( $a ) {
			$type    = isset( $a['type'] ) ? $a['type'] : '';
			$level   = isset( $a['level'] ) ? (int) $a['level'] : 0;
			$post_id = self::post_id( $type, isset( $a['entity_id'] ) ? (int) $a['entity_id'] : 0, $level );
			if ( ! self::active() || ! $post_id ) { return; }
			$post      = get_post( $post_id );
			$v         = self::values( $post_id );
			$d         = self::defaults( $post, isset( $a['name'] ) ? (string) $a['name'] : $post->post_title );
			$permalink = get_permalink( $post );
			$label     = isset( $a['label'] ) ? $a['label'] : __( 'page', 'wp-easycart' );
			$preview_title = '' !== $v['title'] && function_exists( 'wpseo_replace_vars' ) ? wpseo_replace_vars( $v['title'], $post ) : ( '' !== $v['title'] ? $v['title'] : $d['title'] );

			echo '<div class="ecdv2-card ecv2-yoast-card" id="' . esc_attr( $a['id'] ) . '" data-yoast-type="' . esc_attr( $type ) . '" data-yoast-id="' . (int) $a['entity_id'] . '" data-yoast-level="' . (int) $level . '" data-yoast-nonce="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '">';
			/* translators: %s: category / menu / manufacturer */
			echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Yoast SEO', 'wp-easycart' ) . '</h3><span class="ecdv2-card-hint">' . esc_html( sprintf( __( 'Saved to Yoast on this %s page when you click Save', 'wp-easycart' ), $label ) ) . '</span>';
			echo '<a href="' . esc_url( get_edit_post_link( $post_id, '' ) ) . '" target="_blank" rel="noopener" class="ecdv2-help-link ecos-link-brand">' . esc_html__( 'Open in the WordPress editor', 'wp-easycart' ) . ' <span class="dashicons dashicons-external"></span></a></div>';
			echo '<div class="ecdv2-card-body"><div class="ecdv2-grid">';

			echo '<div class="ecdv2-field ecdv2-field-full ecdv2-yoast-preview" id="ecv2_yoast_preview" data-default-title="' . esc_attr( $d['title'] ) . '" data-default-desc="' . esc_attr( $d['desc'] ) . '">';
			echo '<span class="ecdv2-yoast-preview-label">' . esc_html__( 'Search result preview', 'wp-easycart' ) . '</span>';
			echo '<span class="ecdv2-yoast-preview-url">' . esc_html( $permalink ? $permalink : '' ) . '</span>';
			echo '<span class="ecdv2-yoast-preview-title" id="ecv2_yoast_preview_title">' . esc_html( $preview_title ) . '</span>';
			echo '<span class="ecdv2-yoast-preview-desc" id="ecv2_yoast_preview_desc">' . esc_html( '' !== $v['metadesc'] ? $v['metadesc'] : $d['desc'] ) . '</span>';
			echo '</div>';

			echo '<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label" for="ecv2_yoast_title">' . esc_html__( 'SEO title', 'wp-easycart' ) . '</label>';
			echo '<input type="text" class="ecv2-input" id="ecv2_yoast_title" value="' . esc_attr( $v['title'] ) . '" placeholder="' . esc_attr( $d['title'] ) . '" data-track>';
			echo '<span class="ecdv2-char-count" data-count-for="ecv2_yoast_title" data-count-max="60"></span>';
			echo '<span class="ecdv2-field-desc">' . esc_html__( 'The headline shown in search results. Leave it empty to use your Yoast title template. Yoast variables such as %%title%% and %%sitename%% work here.', 'wp-easycart' ) . '</span></div>';

			echo '<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label" for="ecv2_yoast_metadesc">' . esc_html__( 'Meta description', 'wp-easycart' ) . '</label>';
			echo '<textarea class="ecv2-input" id="ecv2_yoast_metadesc" rows="3" placeholder="' . esc_attr( $d['desc'] ) . '" data-track>' . esc_textarea( $v['metadesc'] ) . '</textarea>';
			echo '<span class="ecdv2-char-count" data-count-for="ecv2_yoast_metadesc" data-count-max="156"></span>';
			echo '<span class="ecdv2-field-desc">' . esc_html__( 'The summary under the headline in search results. Around 120 to 156 characters reads best.', 'wp-easycart' ) . '</span></div>';

			echo '<div class="ecdv2-field"><label class="ecdv2-label" for="ecv2_yoast_focuskw">' . esc_html__( 'Focus keyphrase', 'wp-easycart' ) . '</label>';
			echo '<input type="text" class="ecv2-input" id="ecv2_yoast_focuskw" value="' . esc_attr( $v['focuskw'] ) . '" data-track>';
			/* translators: %s: category / menu / manufacturer */
			echo '<span class="ecdv2-field-desc">' . esc_html( sprintf( __( 'The search phrase this %s page should rank for. Yoast scores the page against it.', 'wp-easycart' ), $label ) ) . '</span></div>';

			echo '<div class="ecdv2-field"><label class="ecdv2-label" for="ecv2_yoast_noindex">' . esc_html__( 'Show in search results', 'wp-easycart' ) . '</label>';
			echo '<select class="ecv2-select ecos-select" id="ecv2_yoast_noindex" data-track>';
			echo '<option value="0"' . selected( '0', $v['noindex'], false ) . '>' . esc_html( $d['type_noindex'] ? __( 'Default for store pages (No)', 'wp-easycart' ) : __( 'Default for store pages (Yes)', 'wp-easycart' ) ) . '</option>';
			echo '<option value="2"' . selected( '2', $v['noindex'], false ) . '>' . esc_html__( 'Yes', 'wp-easycart' ) . '</option>';
			echo '<option value="1"' . selected( '1', $v['noindex'], false ) . '>' . esc_html__( 'No, hide this page from search engines', 'wp-easycart' ) . '</option>';
			echo '</select></div>';

			echo '<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label" for="ecv2_yoast_canonical">' . esc_html__( 'Canonical URL', 'wp-easycart' ) . '</label>';
			echo '<input type="url" class="ecv2-input" id="ecv2_yoast_canonical" value="' . esc_attr( $v['canonical'] ) . '" placeholder="' . esc_attr( $permalink ? $permalink : '' ) . '" data-track>';
			echo '<span class="ecdv2-field-desc">' . esc_html__( 'Only set this when the same page lives at another address that search engines should treat as the original.', 'wp-easycart' ) . '</span></div>';

			echo '<div class="ecdv2-field ecdv2-field-full ecdv2-yoast-foot"><span class="dashicons dashicons-info-outline"></span><span>' . esc_html__( 'Readability analysis, schema and social previews stay in the WordPress editor. Keep the store shortcode in the page content intact.', 'wp-easycart' ) . '</span></div>';

			echo '</div></div></div>';
		}
	}

endif;

if ( ! function_exists( 'ecv2_catalog_save_yoast' ) ) :

	/**
	 * Save the Yoast card of a category / menu / manufacturer editor.
	 * POST: nonce ( wp-easycart-ecv2-catalog-yoast ), entity ( category|menu|manufacturer ), id, level ( menus ),
	 * yoast_title, yoast_metadesc, yoast_focuskw, yoast_canonical, yoast_noindex ( 0|1|2 ).
	 * Sanitisation matches ec_admin_ajax_save_product_details_yoast_seo().
	 *
	 * @since 6.0.0
	 */
	function ecv2_catalog_save_yoast() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ), 403 );
		}
		check_ajax_referer( wp_easycart_admin_catalog_yoast_v2::NONCE, 'nonce' );
		if ( ! wp_easycart_admin_catalog_yoast_v2::active() ) {
			wp_send_json_error( array( 'message' => __( 'Yoast SEO is not active.', 'wp-easycart' ) ), 400 );
		}
		$type  = isset( $_POST['entity'] ) ? sanitize_key( wp_unslash( $_POST['entity'] ) ) : '';
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$level = isset( $_POST['level'] ) ? (int) $_POST['level'] : 0;
		if ( ! in_array( $type, wp_easycart_admin_catalog_yoast_v2::types(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-easycart' ) ), 400 );
		}
		$post_id = wp_easycart_admin_catalog_yoast_v2::post_id( $type, $id, $level );
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'This record has no WordPress page to store Yoast data on. Save it once and reload.', 'wp-easycart' ) ), 400 );
		}
		$noindex = isset( $_POST['yoast_noindex'] ) ? sanitize_key( wp_unslash( $_POST['yoast_noindex'] ) ) : '0';
		$values  = array(
			'_yoast_wpseo_title'               => isset( $_POST['yoast_title'] ) ? sanitize_text_field( wp_unslash( $_POST['yoast_title'] ) ) : '',
			'_yoast_wpseo_metadesc'            => isset( $_POST['yoast_metadesc'] ) ? sanitize_text_field( wp_unslash( $_POST['yoast_metadesc'] ) ) : '',
			'_yoast_wpseo_focuskw'             => isset( $_POST['yoast_focuskw'] ) ? sanitize_text_field( wp_unslash( $_POST['yoast_focuskw'] ) ) : '',
			'_yoast_wpseo_canonical'           => isset( $_POST['yoast_canonical'] ) ? esc_url_raw( trim( wp_unslash( $_POST['yoast_canonical'] ) ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by esc_url_raw().
			'_yoast_wpseo_meta-robots-noindex' => in_array( $noindex, array( '1', '2' ), true ) ? $noindex : '',
		);
		wp_easycart_admin_catalog_yoast_v2::write_meta( $post_id, $values );
		/* Yoast's post meta watcher refreshes the post's indexable from these meta writes. */
		do_action( 'wp_easycart_catalog_yoast_seo_saved', $type, $id, $post_id, $values );
		wp_send_json_success( array( 'saved' => true ) );
	}
	add_action( 'wp_ajax_ecv2_catalog_save_yoast', 'ecv2_catalog_save_yoast' );

endif;
