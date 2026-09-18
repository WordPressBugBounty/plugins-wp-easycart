<?php
/**
 * WP EasyCart Admin — Category editor ( V2 ).
 *
 * One screen: details, images, products ( token combo + browse modal ),
 * subcategories, SEO & URL ( slug is manual; a redirect from the old URL is
 * offered when it changes ), safe-delete danger zone.
 *
 * Product membership changes are applied immediately ( ecv2_category_add_products /
 * ecv2_category_remove_product in the list class ); everything else saves with the
 * Save button through ecv2_category_save.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_category_editor_v2' ) ) :

	class wp_easycart_admin_category_editor_v2 {

		const NONCE = 'wp-easycart-catv2';

		public $category;
		public $post;
		public $children = array();
		public $products = array();
		public $product_total = 0;
		public $product_inactive = 0;
		public $parents = array();
		public $path = array();
		public $docs_link = '';
		public $yoast = false;

		public function __construct() {
			$this->docs_link = wp_easycart_admin()->helpsystem->print_docs_url( 'products', 'categories', 'categories' );
			$this->yoast = function_exists( 'is_plugin_active' ) && ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) );
		}

		/* Smart categories ( PRO ) */
		public $smart_available = false;   /* columns present ( DB upgraded ) */
		public $smart_gate = array(); public $smart_pro = false;         /* PRO active */
		public $smart_mode = 0;
		public $smart_rules = array( 'mode' => 'all', 'rules' => array(), 'exclude' => array() );
		public $smart_count = 0;

		public static function smart_available() {
			return class_exists( 'ec_smart_categories_support' ) && ec_smart_categories_support::columns_exist();
		}

		public function load_smart() {
			global $wpdb;
			$this->smart_available = self::smart_available();
			$this->smart_gate = class_exists( 'ec_smart_categories_support' ) ? ec_smart_categories_support::gate() : array( 'state' => 'upsell', 'desc' => '', 'url' => '' );
			$this->smart_pro = class_exists( 'ec_smart_categories_support' ) && ec_smart_categories_support::enabled();
			if ( $this->smart_available && $this->category ) {
				$this->smart_mode  = isset( $this->category->smart_mode ) ? (int) $this->category->smart_mode : 0;
				$this->smart_rules = ec_smart_categories_support::decode_rules( isset( $this->category->smart_rules ) ? $this->category->smart_rules : '' );
				$this->smart_count = $this->smart_pro ? count( ec_smart_categories::smart_member_ids( (int) $this->category->category_id ) ) : (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_categoryitem WHERE category_id = %d AND is_smart = 1', (int) $this->category->category_id ) );
			}
		}

		public function load( $category_id ) {
			global $wpdb;
			$this->category = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_category WHERE category_id = %d', $category_id ) );
			if ( ! $this->category ) { return false; }
			$this->post = $this->category->post_id ? get_post( (int) $this->category->post_id ) : null;
			$this->children = $wpdb->get_results( $wpdb->prepare( 'SELECT c.*, ( SELECT COUNT(*) FROM ec_categoryitem ci WHERE ci.category_id = c.category_id ) AS direct_products FROM ec_category c WHERE c.parent_id = %d ORDER BY c.priority DESC, c.category_name ASC', $category_id ) );
			$this->product_total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_categoryitem WHERE category_id = %d', $category_id ) );
			$this->product_inactive = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_categoryitem ci JOIN ec_product p ON p.product_id = ci.product_id WHERE ci.category_id = %d AND p.activate_in_store = 0', $category_id ) );
			$this->products = self::product_rows( $category_id, 30 );
			/*
			 * Parent choices: only the current parent is preloaded. The #eccat_parent select is
			 * turned into a search-as-you-type picker by catalog-v2.js ( ecv2_category_search ),
			 * and ecv2_category_save refuses a parent inside this category's own subtree, so the
			 * whole table is never rendered here.
			 *
			 * @since 6.0.0
			 */
			$this->parents = array();
			$cur = (int) $this->category->parent_id; $guard = 0;
			while ( $cur && $guard++ < 20 ) {
				$ancestor = $wpdb->get_row( $wpdb->prepare( 'SELECT category_id, category_name, parent_id FROM ec_category WHERE category_id = %d', $cur ) );
				if ( ! $ancestor ) { break; }
				if ( 1 === $guard ) { $this->parents[] = array( 'id' => (int) $ancestor->category_id, 'label' => $ancestor->category_name ); }
				array_unshift( $this->path, $ancestor->category_name );
				$cur = (int) $ancestor->parent_id;
			}
			$this->load_smart();
			return true;
		}

		/** Indented option list, depth-first. Orphans ( parent missing ) are listed as roots. */
		public static function flatten_tree( $all, $exclude = array(), $parent = 0, $depth = 0, &$out = array() ) {
			static $ids = null;
			if ( 0 === $parent && 0 === $depth ) { $ids = array(); foreach ( $all as $c ) { $ids[ (int) $c->category_id ] = true; } }
			foreach ( $all as $c ) {
				$p = (int) $c->parent_id;
				$is_root_here = ( 0 === $parent ) && ( 0 === $p || ! isset( $ids[ $p ] ) );
				if ( ! $is_root_here && $p !== (int) $parent ) { continue; }
				if ( 0 !== $parent && $p !== (int) $parent ) { continue; }
				if ( in_array( (int) $c->category_id, $exclude, true ) ) { continue; }
				$out[] = array( 'id' => (int) $c->category_id, 'label' => str_repeat( '— ', $depth ) . $c->category_name );
				self::flatten_tree( $all, $exclude, (int) $c->category_id, $depth + 1, $out );
			}
			return $out;
		}

		public static function product_rows( $category_id, $limit = 30, $offset = 0 ) {
			global $wpdb;
			$smart_col = self::smart_available() ? 'ci.is_smart' : '0 AS is_smart';
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT p.product_id, p.title, p.model_number, p.activate_in_store, ' . $smart_col . ', ' . wp_easycart_admin_catalog_v2_thumb_select( 'p' ) . ' FROM ec_categoryitem ci JOIN ec_product p ON p.product_id = ci.product_id WHERE ci.category_id = %d ORDER BY p.title ASC LIMIT %d OFFSET %d', $category_id, $limit, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $smart_col is one of two literal strings; thumb_select() is a static column list.
			$out = array();
			foreach ( $rows as $r ) {
				$out[] = array(
					'id' => (int) $r->product_id, 'title' => wp_unslash( $r->title ), 'sku' => $r->model_number,
					'image' => wp_easycart_admin_catalog_v2_product_thumb( $r ),
					'active' => (bool) $r->activate_in_store,
					'smart' => ! empty( $r->is_smart ),
					'edit_url' => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' . (int) $r->product_id ),
				);
			}
			return $out;
		}

		/**
		 * Subcategory rows for the Subcategories card, in the shape category-editor-v2.js re-renders
		 * after "Add existing category".
		 *
		 * @since 6.0.0
		 */
		public function children_rows() {
			$out = array();
			foreach ( $this->children as $ch ) {
				$out[] = array(
					'id'       => (int) $ch->category_id,
					'name'     => wp_unslash( $ch->category_name ),
					'image'    => wp_easycart_admin_category_table::image_url( $ch->image ),
					'products' => (int) $ch->direct_products,
					'active'   => (bool) $ch->is_active,
					'edit_url' => wp_easycart_admin_category_table::editor_url( $ch->category_id ),
				);
			}
			return $out;
		}

		/** This category plus every descendant: the ids the "Add existing category" picker must never offer. @since 6.0.0 */
		public function subtree_ids() {
			return array_map( 'intval', wp_easycart_admin_safe_delete()->descendant_ids( (int) $this->category->category_id ) );
		}

		public function permalink() {
			return $this->post ? get_permalink( $this->post ) : '';
		}

		/** Prefix shown before the slug input ( everything up to the final path segment ). */
		public function slug_prefix() {
			$link = $this->permalink();
			if ( ! $link ) { return home_url( '/store/' ); }
			$path = wp_parse_url( $link, PHP_URL_PATH );
			$path = rtrim( (string) $path, '/' );
			$pos  = strrpos( $path, '/' );
			$prefix = false === $pos ? '/' : substr( $path, 0, $pos + 1 );
			return home_url( $prefix );
		}

		public function smart_health() {
			if ( ! $this->smart_available || ! $this->smart_mode ) { return null; }
			if ( ! $this->smart_pro ) { return array( 'ok' => false, 'label' => ( wp_easycart_admin_edition::is_lapsed() ? sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Smart rules paused — your %s license expired', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) : sprintf( /* translators: %s: plugin name, WP EasyCart PRO. */ __( 'Smart rules paused — %s is not active', 'wp-easycart' ), wp_easycart_admin_edition::PLUGIN_NAME ) ) ); }
			if ( empty( $this->smart_rules['rules'] ) ) { return array( 'ok' => false, 'label' => __( 'Smart mode is on but has no rules', 'wp-easycart' ) ); }
			return array( 'ok' => true, 'label' => sprintf( _n( '%d product added by rules', '%d products added by rules', $this->smart_count, 'wp-easycart' ), $this->smart_count ) );
		}

		public function health() {
			$c = $this->category;
			$out = $this->health_items();
			$sh = $this->smart_health();
			if ( $sh ) { array_unshift( $out, $sh ); }
			return $out;
		}

		private function health_items() {
			$c = $this->category;
			return array(
				array( 'ok' => $this->product_total > 0 || count( $this->children ) > 0, 'label' => $this->product_total ? __( 'Has products', 'wp-easycart' ) : ( count( $this->children ) ? __( 'Has subcategories', 'wp-easycart' ) : __( 'Empty — nothing to show shoppers', 'wp-easycart' ) ) ),
				array( 'ok' => '' !== trim( (string) $c->image ), 'label' => '' !== trim( (string) $c->image ) ? __( 'Category image set', 'wp-easycart' ) : __( 'No category image', 'wp-easycart' ) ),
				array( 'ok' => '' !== trim( wp_strip_all_tags( (string) $c->short_description ) ), 'label' => '' !== trim( wp_strip_all_tags( (string) $c->short_description ) ) ? __( 'Has a description', 'wp-easycart' ) : __( 'No description (helps SEO)', 'wp-easycart' ) ),
				array( 'ok' => (bool) $c->is_active, 'label' => $c->is_active ? __( 'Active in store', 'wp-easycart' ) : __( 'Hidden from store', 'wp-easycart' ) ),
				array( 'ok' => 0 === $this->product_inactive, 'label' => $this->product_inactive ? sprintf( _n( '%d product is inactive', '%d products are inactive', $this->product_inactive, 'wp-easycart' ), $this->product_inactive ) : __( 'All products active', 'wp-easycart' ) ),
				array( 'ok' => (bool) $this->post, 'label' => $this->post ? __( 'Category page linked', 'wp-easycart' ) : __( 'Category page missing — save to recreate', 'wp-easycart' ) ),
			);
		}

		public function output() {
			$id = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;
			if ( ! $this->load( $id ) ) {
				echo '<div class="ecv2-wrap"><div class="ecv2-empty-state">' . esc_html__( 'That category no longer exists.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=category' ) ) . '">' . esc_html__( 'Back to categories', 'wp-easycart' ) . '</a></div></div>';
				return;
			}
			include( EC_PLUGIN_DIRECTORY . '/admin/template/products/categories/category-editor-v2.php' );
		}

		public function js_data() {
			return array(
				'category_id'   => (int) $this->category->category_id,
				'slug'          => $this->post ? $this->post->post_name : '',
				'permalink'     => $this->permalink(),
				'redirects'     => class_exists( 'ec_url_redirects' ),
				'products'      => $this->products,
				'product_total' => $this->product_total,
				'children'      => $this->children_rows(),
				'subtree_ids'   => $this->subtree_ids(),
				'search_nonce'  => wp_create_nonce( 'wp-easycart-ecv2-category-search' ),
				'smart'         => array(
					'available' => $this->smart_available,
					'is_pro'    => $this->smart_pro,
					'mode'      => $this->smart_mode,
					'rules'     => $this->smart_rules,
					'count'     => $this->smart_count,
					'fields'    => $this->smart_pro ? ec_smart_categories::fields() : array(),
					'ops'       => $this->smart_pro ? ec_smart_categories::ops() : array(),
					'sources'   => $this->smart_pro ? ec_smart_categories::sources() : array(),
					'gate'      => $this->smart_gate,
					'upgrade_url' => ! empty( $this->smart_gate['url'] ) ? $this->smart_gate['url'] : apply_filters( 'wp_easycart_admin_upgrade_url', 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/?upsell=smart-categories' ),
				),
				'nonce'         => wp_create_nonce( self::NONCE ),
				'list_nonce'    => wp_create_nonce( 'wp-easycart-ecv2-inline-update' ),
				'delete_nonce'  => wp_create_nonce( wp_easycart_admin_safe_delete::NONCE ),
				'list_url'      => admin_url( 'admin.php?page=wp-easycart-products&subpage=category' ),
				'i18n'          => array(
					'saved'        => __( 'Saved', 'wp-easycart' ),
					'saving'       => __( 'Saving…', 'wp-easycart' ),
					'error'        => __( 'Something went wrong. Please try again.', 'wp-easycart' ),
					'name_required'=> __( 'Give the category a name.', 'wp-easycart' ),
					'smart_locked' => wp_easycart_admin_edition::requires_text( __( 'Smart mode for categories', 'wp-easycart' ), 'pro' ),
					'leave'        => __( 'You have unsaved changes.', 'wp-easycart' ),
					'added'        => __( 'Added “%s”', 'wp-easycart' ),
					'removed'      => __( 'Removed “%s”', 'wp-easycart' ),
					'undo'         => __( 'Undo', 'wp-easycart' ),
					'pick_image'   => __( 'Choose a category image', 'wp-easycart' ),
					'pick_banner'  => __( 'Choose a banner image', 'wp-easycart' ),
					'use_image'    => __( 'Use this image', 'wp-easycart' ),
					'slug_changed' => __( 'The URL will change. Old links will 301-redirect to the new address if the option below stays on.', 'wp-easycart' ),
					'slug_same'    => __( 'Slugs are not updated automatically when you rename a category.', 'wp-easycart' ),
					'no_matches'   => __( 'No matching products.', 'wp-easycart' ),
					'create_product'=> __( 'Create a new product in this category', 'wp-easycart' ),
					'no_categories'=> __( 'No matching categories.', 'wp-easycart' ),
					'sub_moved'    => __( 'Moved “%s” under this category', 'wp-easycart' ),
					'sub_none'     => __( 'None yet', 'wp-easycart' ),
					/* translators: %d: number of subcategories */
					'sub_count'    => __( '%d subcategories', 'wp-easycart' ),
					'sub_one'      => __( '1 subcategory', 'wp-easycart' ),
					'yoast_saved'  => __( 'Yoast SEO saved', 'wp-easycart' ),
				),
			);
		}
	}

endif;

/* ---------------------------------------------------------------------- */
/* AJAX                                                                    */
/* ---------------------------------------------------------------------- */

add_action( 'wp_ajax_ecv2_category_save', 'ecv2_category_save' );
function ecv2_category_save() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	check_ajax_referer( wp_easycart_admin_category_editor_v2::NONCE, 'nonce' );
	global $wpdb;

	$id  = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$cat = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_category WHERE category_id = %d', $id ) );
	if ( ! $cat ) { wp_send_json_error( array( 'message' => __( 'Category not found.', 'wp-easycart' ) ) ); }

	$d = json_decode( wp_unslash( isset( $_POST['data'] ) ? $_POST['data'] : '{}' ), true );
	if ( ! is_array( $d ) ) { wp_send_json_error( array( 'message' => __( 'Invalid payload.', 'wp-easycart' ) ) ); }

	$name = isset( $d['name'] ) ? sanitize_text_field( $d['name'] ) : '';
	if ( '' === $name ) { wp_send_json_error( array( 'message' => __( 'A name is required.', 'wp-easycart' ) ) ); }
	$parent = isset( $d['parent_id'] ) ? (int) $d['parent_id'] : 0;
	if ( $parent === $id || in_array( $parent, wp_easycart_admin_safe_delete()->descendant_ids( $id ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'A category cannot be placed inside itself.', 'wp-easycart' ) ) );
	}
	if ( $parent && ! $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE category_id = %d', $parent ) ) ) { $parent = 0; }

	$is_active = ! empty( $d['is_active'] ) ? 1 : 0;
	$featured  = ! empty( $d['featured_category'] ) ? 1 : 0;
	$priority  = isset( $d['priority'] ) ? (int) $d['priority'] : (int) $cat->priority;
	$image     = isset( $d['image'] ) ? sanitize_text_field( $d['image'] ) : (string) $cat->image;
	$desc      = isset( $d['description'] ) ? wp_easycart_escape_html( $d['description'] ) : (string) $cat->short_description;
	$excerpt   = isset( $d['excerpt'] ) ? sanitize_text_field( $d['excerpt'] ) : '';
	$featured_image = isset( $d['featured_image'] ) ? (int) $d['featured_image'] : 0;
	$slug      = isset( $d['slug'] ) ? sanitize_title( $d['slug'] ) : '';
	$redirect  = ! empty( $d['redirect'] );

	$wpdb->update( 'ec_category', array(
		'is_active' => $is_active, 'category_name' => $name, 'parent_id' => $parent,
		'short_description' => $desc, 'image' => $image, 'featured_category' => $featured, 'priority' => $priority,
	), array( 'category_id' => $id ) );

	/* Post ( verified sync; relinks or recreates on a broken link ) */
	$old_link = $cat->post_id ? get_permalink( (int) $cat->post_id ) : '';
	$post = array(
		'post_content' => '[ec_store groupid="' . $id . '"]',
		'post_status'  => $is_active ? 'publish' : 'private',
		'post_title'   => wp_easycart_language()->convert_text( $name ),
		'post_type'    => 'ec_store',
		'post_excerpt' => $excerpt,
	);
	if ( '' !== $slug ) { $post['post_name'] = $slug; }
	$post_id = wp_easycart_post_sync()->update( 'category', $id, (int) $cat->post_id, $post );
	if ( $post_id && $post_id != $cat->post_id ) { wp_set_post_tags( $post_id, array( 'category' ), true ); }
	if ( $post_id ) {
		if ( $featured_image ) { set_post_thumbnail( $post_id, $featured_image ); } else { delete_post_thumbnail( $post_id ); }
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET guid = %s WHERE ID = %d", get_permalink( $post_id ), $post_id ) );
	}
	$new_link = $post_id ? get_permalink( $post_id ) : '';

	$redirect_added = false;
	if ( $redirect && $old_link && $new_link && class_exists( 'ec_url_redirects' ) && ec_url_redirects::key( $old_link ) !== ec_url_redirects::key( $new_link ) ) {
		$redirect_added = ec_url_redirects::add( $old_link, $new_link, 'category-slug:' . $id );
	}

	/* Smart categories ( PRO ) — only when the DB has the columns and PRO is active; otherwise the fields are ignored, never cleared. */
	$smart_result = null;
	if ( wp_easycart_admin_category_editor_v2::smart_available() && ec_smart_categories_support::enabled() && isset( $d['smart_mode'] ) ) {
		$mode = max( 0, min( 2, (int) $d['smart_mode'] ) );
		$rules = ec_smart_categories::sanitize_rules( isset( $d['smart_rules'] ) && is_array( $d['smart_rules'] ) ? $d['smart_rules'] : array() );
		if ( $mode && empty( $rules['rules'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Add at least one rule, or switch the category back to hand-picked.', 'wp-easycart' ) ) );
		}
		$wpdb->update( 'ec_category', array( 'smart_mode' => $mode, 'smart_rules' => wp_json_encode( $rules ) ), array( 'category_id' => $id ) );
		$smart_result = ec_smart_categories::sync_category( $id );
		ec_smart_categories::maybe_schedule();
	}

	do_action( 'wpeasycart_category_updated', $id );
	wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );

	$ed = new wp_easycart_admin_category_editor_v2();
	$ed->load( $id );
	wp_send_json_success( array(
		'message'        => __( 'Saved', 'wp-easycart' ),
		'smart'          => $smart_result,
		'products'       => $smart_result ? $ed->products : null,
		'product_total'  => $ed->product_total,
		'permalink'      => $new_link,
		'slug'           => $ed->post ? $ed->post->post_name : '',
		'slug_prefix'    => $ed->slug_prefix(),
		'redirect_added' => $redirect_added,
		'health'         => $ed->health(),
		'path'           => $ed->path,
	) );
}

add_action( 'wp_ajax_ecv2_category_products', 'ecv2_category_products' );
function ecv2_category_products() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	check_ajax_referer( wp_easycart_admin_category_editor_v2::NONCE, 'nonce' );
	$id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
	wp_send_json_success( array( 'items' => wp_easycart_admin_category_editor_v2::product_rows( $id, 30, $offset ) ) );
}

/**
 * Subcategories card, "Add existing category": make an existing category a child of this one.
 * POST: nonce ( wp-easycart-catv2 ), category_id ( the new parent, the category being edited ), child_id.
 * Only parent_id changes; the moved category keeps its products, subcategories and URL. Refuses the
 * category itself, anything already inside its subtree ( a cycle ) and a category that is already
 * a direct child. Flushes the same caches as ecv2_category_save.
 *
 * @since 6.0.0
 */
add_action( 'wp_ajax_ecv2_category_set_parent', 'ecv2_category_set_parent' );
function ecv2_category_set_parent() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	check_ajax_referer( wp_easycart_admin_category_editor_v2::NONCE, 'nonce' );
	global $wpdb;
	$parent_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$child_id  = isset( $_POST['child_id'] ) ? (int) $_POST['child_id'] : 0;
	if ( ! $parent_id || ! $child_id || $parent_id === $child_id ) {
		wp_send_json_error( array( 'message' => __( 'A category cannot be placed inside itself.', 'wp-easycart' ) ) );
	}
	$parent = $wpdb->get_row( $wpdb->prepare( 'SELECT category_id, category_name FROM ec_category WHERE category_id = %d', $parent_id ) );
	$child  = $wpdb->get_row( $wpdb->prepare( 'SELECT category_id, category_name, parent_id FROM ec_category WHERE category_id = %d', $child_id ) );
	if ( ! $parent || ! $child ) {
		wp_send_json_error( array( 'message' => __( 'Category not found.', 'wp-easycart' ) ) );
	}
	if ( in_array( $parent_id, array_map( 'intval', wp_easycart_admin_safe_delete()->descendant_ids( $child_id ) ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'That category is a parent of this one, so it cannot also be a subcategory.', 'wp-easycart' ) ) );
	}
	if ( (int) $child->parent_id === $parent_id ) {
		wp_send_json_error( array( 'message' => __( 'That category is already a subcategory of this one.', 'wp-easycart' ) ) );
	}
	$wpdb->update( 'ec_category', array( 'parent_id' => $parent_id ), array( 'category_id' => $child_id ) );

	do_action( 'wpeasycart_category_updated', $child_id );
	wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );

	$ed = new wp_easycart_admin_category_editor_v2();
	$ed->load( $parent_id );
	wp_send_json_success( array(
		'message'     => sprintf( __( 'Moved “%s” under this category', 'wp-easycart' ), wp_unslash( $child->category_name ) ),
		'children'    => $ed->children_rows(),
		'subtree_ids' => $ed->subtree_ids(),
		'health'      => $ed->health(),
	) );
}

/** Live preview for the smart-rule builder. POST: nonce, category_id, rules ( JSON ). */
add_action( 'wp_ajax_ecv2_category_rules_preview', 'ecv2_category_rules_preview' );
function ecv2_category_rules_preview() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( wp_easycart_admin_category_editor_v2::NONCE, 'nonce' );
	if ( ! wp_easycart_admin_category_editor_v2::smart_available() ) { wp_send_json_error( array( 'message' => __( 'Smart categories require the latest database update.', 'wp-easycart' ) ) ); }
	if ( ! class_exists( 'ec_smart_categories_support' ) || ! ec_smart_categories_support::enabled() ) { wp_send_json_error( array( 'message' => wp_easycart_admin_edition::requires_text( __( 'Smart mode for categories', 'wp-easycart' ), 'pro' ) ) ); }
	$rules = json_decode( wp_unslash( isset( $_POST['rules'] ) ? $_POST['rules'] : '{}' ), true );
	$pv = ec_smart_categories::preview( is_array( $rules ) ? $rules : array(), isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0, 6 );
	$sample = array();
	foreach ( $pv['sample'] as $r ) { $sample[] = array( 'id' => (int) $r->product_id, 'title' => wp_unslash( $r->title ), 'image' => wp_easycart_admin_catalog_v2_product_thumb( $r ), 'active' => (bool) $r->activate_in_store ); }
	wp_send_json_success( array( 'count' => $pv['count'], 'sample' => $sample, 'empty_rules' => $pv['empty_rules'] ) );
}
