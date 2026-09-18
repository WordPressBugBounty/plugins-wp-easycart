<?php
/**
 * Shared markup helpers for the "lite" V2 editors ( menus, manufacturers, reviews ).
 * Paired with admin/js/catalog-editor-lite-v2.js, which reads #eclite_data and wires
 * everything by data-attributes: data-field ( saved ), data-track ( dirty ),
 * data-media ( media picker ), data-bind ( mirrors to header ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_lite_open' ) ) :

	function ecv2_lite_open( $js_data, $extra_class = '' ) {
		echo '<div class="ecv2-wrap ecdv2-wrap ecos-wrap eclite-wrap ' . esc_attr( $extra_class ) . '" id="eclite" data-kind="' . esc_attr( $js_data['kind'] ) . '">';
		echo '<script type="application/json" id="eclite_data">' . wp_json_encode( $js_data ) . '</script>';
	}

	function ecv2_lite_close() {
		echo '<div id="ecv2-toast-container"></div></div>';
	}

	/**
	 * @param array $a { back_url, back_title, title, sub_html, pill_html, thumb_url, actions_html, save_label }
	 */
	function ecv2_lite_header( $a ) {
		echo '<div class="ecdv2-header">';
		echo '<a class="ecdv2-header-back" href="' . esc_url( $a['back_url'] ) . '" title="' . esc_attr( $a['back_title'] ) . '"><span class="dashicons dashicons-arrow-left-alt2"></span></a>';
		if ( isset( $a['thumb_url'] ) ) {
			echo '<div class="ecdv2-header-thumb" id="eclite_h_thumb">' . ( $a['thumb_url'] ? '<img src="' . esc_url( $a['thumb_url'] ) . '" alt="">' : '<span class="dashicons dashicons-format-image"></span>' ) . '</div>';
		}
		echo '<div class="ecdv2-header-meta"><h1 class="ecdv2-header-title" id="eclite_h_title">' . esc_html( $a['title'] ) . '</h1><div class="ecdv2-header-sub" id="eclite_h_sub">' . $a['sub_html'] . '</div></div>';
		if ( ! empty( $a['pill_html'] ) ) { echo $a['pill_html']; }
		echo '<span class="ecdv2-header-spacer"></span><span class="ecdv2-dirty-pill" id="eclite_dirty">' . esc_html__( 'Unsaved changes', 'wp-easycart' ) . '</span>';
		echo '<div class="ecdv2-header-actions">' . ( isset( $a['actions_html'] ) ? $a['actions_html'] : '' );
		if ( empty( $a['no_save'] ) ) echo '<button type="button" class="ecv2-btn ecv2-btn-primary ecdv2-save-btn" id="eclite_save" onclick="eclite.save();"><span class="ecdv2-save-spin"></span><span class="ecdv2-save-label">' . esc_html( isset( $a['save_label'] ) ? $a['save_label'] : __( 'Save', 'wp-easycart' ) ) . '</span></button>';
		echo '</div></div>';
	}

	/** $links: [ [ href, label, count|null ] ], grouped by a heading key; $health: [ { ok, label } ] */
	function ecv2_lite_rail( $groups, $health, $health_title ) {
		echo '<aside class="ecdv2-rail">';
		$first = true;
		foreach ( $groups as $heading => $links ) {
			echo '<div class="ecdv2-rail-group">' . esc_html( $heading ) . '</div>';
			foreach ( $links as $l ) {
				echo '<a href="' . esc_attr( $l[0] ) . '" class="ecdv2-rail-link' . ( $first ? ' is-active' : '' ) . '">' . esc_html( $l[1] ) . ( isset( $l[2] ) && null !== $l[2] ? ' <span class="ecv2-chip ecv2-chip-gray">' . (int) $l[2] . '</span>' : '' ) . '</a>';
				$first = false;
			}
		}
		echo '<div class="ecdv2-health" id="eclite_health"><div class="ecdv2-health-top"><b>' . esc_html( $health_title ) . '</b></div><div class="ecdv2-health-items">';
		ecv2_lite_health_items( $health );
		echo '</div></div></aside>';
	}

	function ecv2_lite_health_items( $health ) {
		foreach ( $health as $h ) {
			echo '<div class="ecdv2-health-item ' . ( $h['ok'] ? 'is-ok' : 'is-warn' ) . '"><span class="dashicons dashicons-' . ( $h['ok'] ? 'yes' : 'warning' ) . '"></span><span class="ecdv2-health-label">' . esc_html( $h['label'] ) . '</span></div>';
		}
	}

	/** $hint is plain text ( escaped here ); pass $hint_id to give the hint span an id that JS can update. */
	function ecv2_lite_card_open( $id, $title, $hint = '', $link_html = '', $hint_id = '' ) {
		echo '<div class="ecdv2-card" id="' . esc_attr( $id ) . '"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html( $title ) . '</h3>' . ( $hint ? '<span class="ecdv2-card-hint"' . ( $hint_id ? ' id="' . esc_attr( $hint_id ) . '"' : '' ) . '>' . esc_html( $hint ) . '</span>' : '' ) . $link_html . '</div><div class="ecdv2-card-body">';
	}
	function ecv2_lite_card_close() { echo '</div></div>'; }

	function ecv2_lite_field( $id, $label, $input_html, $hint = '', $desc = '', $full = false ) {
		echo '<div class="ecdv2-field' . ( $full ? ' ecdv2-field-full' : '' ) . '"><label class="ecdv2-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $hint ? ' <span class="ecdv2-label-hint">' . esc_html( $hint ) . '</span>' : '' ) . '</label>' . $input_html . ( $desc ? '<span class="ecdv2-field-desc">' . esc_html( $desc ) . '</span>' : '' ) . '</div>';
	}

	function ecv2_lite_text( $id, $field, $value, $placeholder = '', $attrs = '' ) {
		return '<input type="text" class="ecv2-input" id="' . esc_attr( $id ) . '" data-field="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" data-track ' . $attrs . '>';
	}

	/** Image slot with hidden input; $mode 'url' stores the image URL, 'id' stores the attachment id. */
	function ecv2_lite_image_slot( $id, $field, $value, $preview_url, $label, $hint, $mode = 'url', $wide = false ) {
		echo '<div class="eccat-imgslot" id="' . esc_attr( $id ) . '_slot" data-media="' . esc_attr( $mode ) . '" data-target="' . esc_attr( $id ) . '">';
		echo '<span class="eccat-imgslot-thumb' . ( $wide ? ' eccat-imgslot-wide' : '' ) . ( $preview_url ? ' has' : '' ) . '" id="' . esc_attr( $id ) . '_thumb">' . ( $preview_url ? '<img src="' . esc_url( $preview_url ) . '" alt="">' : '<span class="dashicons dashicons-format-image"></span>' ) . '</span>';
		echo '<div class="eccat-imgslot-meta"><b>' . esc_html( $label ) . '</b><span>' . esc_html( $hint ) . '</span></div>';
		echo '<div class="eccat-imgslot-acts"><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="eclite.pick( \'' . esc_js( $id ) . '\' );">' . esc_html( $preview_url ? __( 'Replace', 'wp-easycart' ) : __( 'Upload', 'wp-easycart' ) ) . '</button><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="' . esc_attr( $id ) . '_remove" onclick="eclite.clear_image( \'' . esc_js( $id ) . '\' );"' . ( $preview_url ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove', 'wp-easycart' ) . '</button></div>';
		echo '<input type="hidden" id="' . esc_attr( $id ) . '" data-field="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '" data-track></div>';
	}

	/** Read-only product list ( menus / manufacturers ). */
	function ecv2_lite_products_card( $id, $products, $total, $all_url, $hint, $empty_text, $extra_link_html = '' ) {
		ecv2_lite_card_open( $id, __( 'Products', 'wp-easycart' ), $hint, $extra_link_html );
		if ( empty( $products ) ) {
			echo '<div class="ecos-empty">' . esc_html( $empty_text ) . '</div>';
		} else {
			echo '<div class="ecos-used" id="eclite_products">';
			foreach ( $products as $u ) { ecv2_lite_product_row( $u ); }
			echo '</div>';
			echo '<div class="ecos-used-foot"><span class="ecos-hint" id="eclite_products_foot">' . esc_html( sprintf( __( 'Showing %1$d of %2$d', 'wp-easycart' ), count( $products ), $total ) ) . '</span><span class="ecos-grow"></span>';
			if ( $total > count( $products ) ) { echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="eclite_more" onclick="eclite.load_more( this );">' . esc_html__( 'Show more', 'wp-easycart' ) . '</button>'; }
			echo '<a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( $all_url ) . '">' . esc_html__( 'View all in Products', 'wp-easycart' ) . '</a></div>';
		}
		ecv2_lite_card_close();
	}

	function ecv2_lite_product_row( $u ) {
		echo '<div class="ecos-u"><span class="ecv2-thumb">' . ( $u['image'] ? '<img src="' . esc_url( $u['image'] ) . '" alt="" loading="lazy">' : '<span class="dashicons dashicons-format-image"></span>' ) . '</span><div class="ecos-u-main"><a class="ecv2-link-primary" href="' . esc_url( $u['edit_url'] ) . '">' . esc_html( $u['title'] ) . '</a><span class="ecv2-sub">' . esc_html( $u['sku'] ) . '</span></div><span class="ecv2-chip ' . ( $u['active'] ? 'ecv2-chip-green' : 'ecv2-chip-gray' ) . '">' . ( $u['active'] ? esc_html__( 'Active', 'wp-easycart' ) : esc_html__( 'Inactive', 'wp-easycart' ) ) . '</span><a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' . esc_url( $u['edit_url'] ) . '">' . esc_html__( 'Open', 'wp-easycart' ) . '</a></div>';
	}

	/** SEO & URL card: manual slug + redirect offer + excerpt ( + optional legacy keyword/description fields ). */
	function ecv2_lite_seo_card( $id, $post, $slug_prefix, $yoast, $extra_fields_html = '' ) {
		$link = '';
		if ( $yoast && $post ) { $link = '<a href="' . esc_url( get_edit_post_link( $post->ID, '' ) ) . '" target="_blank" class="ecdv2-help-link ecos-link-brand">' . esc_html__( 'Edit in Yoast', 'wp-easycart' ) . ' <span class="dashicons dashicons-external"></span></a>'; }
		ecv2_lite_card_open( $id, __( 'SEO & URL', 'wp-easycart' ), $yoast ? __( 'Yoast SEO detected — title and meta description are managed there', 'wp-easycart' ) : __( 'Slug and search excerpt for the page', 'wp-easycart' ), $link );
		echo '<div class="ecdv2-grid">';
		echo '<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label" for="eclite_slug">' . esc_html__( 'URL slug', 'wp-easycart' ) . '</label>';
		echo '<div class="eccat-slug"><span class="eccat-slug-pre" id="eclite_slug_pre">' . esc_html( $slug_prefix ) . '</span><input type="text" id="eclite_slug" data-field="slug" value="' . esc_attr( $post ? $post->post_name : '' ) . '" data-track><button type="button" class="eccat-slug-btn" title="' . esc_attr__( 'Generate from name', 'wp-easycart' ) . '" onclick="eclite.slug_from_name();"><span class="dashicons dashicons-update"></span></button></div>';
		echo '<span class="ecdv2-field-desc" id="eclite_slug_desc">' . esc_html__( 'Slugs are not updated automatically when you rename.', 'wp-easycart' ) . '</span>';
		echo '<label class="ecos-toggle-row" id="eclite_redirect_row" style="display:none"><span class="ecv2-toggle"><input type="checkbox" id="eclite_redirect" checked><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t">' . esc_html__( 'Redirect the old URL to the new one', 'wp-easycart' ) . '</span><small>' . esc_html( class_exists( 'ec_url_redirects' ) ? __( 'Adds a permanent (301) redirect so existing links keep working.', 'wp-easycart' ) : __( 'Requires inc/classes/core/ec_url_redirects.php to be loaded.', 'wp-easycart' ) ) . '</small></span></label></div>';
		ecv2_lite_field( 'eclite_excerpt', __( 'Search excerpt', 'wp-easycart' ), '<input type="text" class="ecv2-input" id="eclite_excerpt" data-field="excerpt" value="' . esc_attr( $post ? $post->post_excerpt : '' ) . '" maxlength="160" data-track>', __( 'meta description when no SEO plugin overrides it', 'wp-easycart' ), '', true );
		echo $extra_fields_html;
		echo '</div>';
		ecv2_lite_card_close();
	}

	function ecv2_lite_danger_card( $id, $title, $text, $button, $delete_type, $record_id, $list_url ) {
		echo '<div class="ecdv2-card ecos-danger" id="' . esc_attr( $id ) . '"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Danger zone', 'wp-easycart' ) . '</h3></div><div class="ecdv2-card-body ecos-danger-body"><div><b>' . esc_html( $title ) . '</b><span>' . esc_html( $text ) . '</span></div>';
		echo '<button type="button" class="ecv2-btn ecv2-btn-danger" onclick="ecv2_catalog.safe_delete( \'' . esc_js( $delete_type ) . '\', ' . (int) $record_id . ', { redirect_to: \'' . esc_js( $list_url ) . '\' } );">' . esc_html( $button ) . '</button></div></div>';
	}

	function ecv2_lite_toggle_row( $id, $field, $checked, $title, $hint = '' ) {
		return '<label class="ecos-toggle-row"><span class="ecv2-toggle"><input type="checkbox" id="' . esc_attr( $id ) . '" data-field="' . esc_attr( $field ) . '"' . ( $checked ? ' checked' : '' ) . ' data-track><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t">' . esc_html( $title ) . '</span>' . ( $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</span></label>';
	}

endif;
