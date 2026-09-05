<?php
/**
 * WP EasyCart — Cart Links admin (FREE).
 *
 * Marketing > Cart Links: list + builder drawer for saved add-to-cart links
 * ( resolved on the storefront by class-wp-easycart-cart-link.php via
 * ?ec_cart_apply=TOKEN ).
 *
 * FREE builds single-product links ( product + quantity + basic option
 * preselects, destination, active toggle ). PRO layers on via the hooks below:
 *  - action 'wp_easycart_ecv2_cart_link_drawer_items_end'   ( multi-product )
 *  - action 'wp_easycart_ecv2_cart_link_drawer_modifiers'   ( modifier preselects )
 *  - action 'wp_easycart_ecv2_cart_link_drawer_fields'      ( codes / expiry / max uses )
 *  - action 'wp_easycart_ecv2_cart_link_row_menu'           ( QR download, stats )
 *  - filter 'wp_easycart_ecv2_cart_link_save_data'          ( extend saved fields )
 *  - filter 'wp_easycart_ecv2_cart_link_save_items'         ( extend saved items )
 *  - filter 'wp_easycart_ecv2_cart_link_list_row'           ( extend list payload )
 *
 * Capability: wpec_marketing ( or manage_options ) everywhere, including the
 * quick-create card the product editor mounts ( phase 4 ).
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wp_easycart_admin_cart_links {

	const NONCE_ACTION = 'wp-easycart-ecv2-cart-links';

	public static function current_user_allowed() {
		return current_user_can( 'manage_options' ) || current_user_can( 'wpec_marketing' );
	}

	/* ------------------------------------------------------------------ */
	/* PRO detection + upsell                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Is a licensed PRO layering onto this page? PRO's constructor bails
	 * before registering any hook when unlicensed, so "installed but not
	 * activated" correctly reads as free here. The filter lets PRO ( or a
	 * future free build ) override the inference.
	 */
	public static function is_pro() {
		/* max_items is a filter only PRO registers — the free class hooks the
		 * render actions itself ( for these stand-ins ), so those can't be used. */
		$pro = (bool) has_filter( 'wp_easycart_ecv2_cart_link_max_items' );
		return (bool) apply_filters( 'wp_easycart_ecv2_cart_link_is_pro', $pro );
	}

	/** PRO feature list comes from the shared upsell catalog. */
	public static function pro_features() {
		$e = wp_easycart_admin_upsell::entry( 'cart_links' );
		return $e['features'];
	}

	/** Compact feature strip shown on the list page ( free only ). */
	public function print_upsell_strip() {
		if ( self::is_pro() ) {
			return;
		}
		wp_easycart_admin_upsell::print_feature_strip( 'cart_links' );
	}

	/**
	 * Locked stand-ins rendered exactly where PRO would mount its real UI.
	 * Same section shell as the live fields so the drawer layout is
	 * identical across free / PRO; clicking anywhere opens the upsell.
	 */
	public function print_drawer_upsell_items_end() {
		if ( self::is_pro() ) {
			return;
		}
		echo '<button type="button" class="ecv2-cl-locked-add" onclick="' . wp_easycart_admin_upsell::onclick( 'cart_links', 'multi_product' ) . '"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Add another product', 'wp-easycart' ) . ' <span class="ecv2-cl-pro-badge">PRO</span></button>';
	}

	public function print_drawer_upsell_fields() {
		if ( self::is_pro() ) {
			return;
		}
		$features = self::pro_features();
		$locked = array(
			'codes'  => array( 'label' => __( 'Coupon / offer codes', 'wp-easycart' ), 'placeholder' => __( 'Type a code and press Enter', 'wp-easycart' ) ),
			'limits' => array( 'label' => __( 'Limits', 'wp-easycart' ), 'placeholder' => '' ),
		);
		foreach ( $locked as $key => $meta ) {
			echo '<section class="ecv2-qe-section ecv2-qe-section-edit ecv2-cl-locked" data-feature="' . esc_attr( $key ) . '" onclick="' . wp_easycart_admin_upsell::onclick( 'cart_links', $key ) . '" role="button" tabindex="0">';
			echo '<div class="ecv2-qe-section-label"><span>' . esc_html( $meta['label'] ) . '</span> <span class="ecv2-cl-pro-badge">PRO</span></div>';
			echo '<div class="ecv2-cl-locked-body">';
			if ( 'codes' === $key ) {
				echo '<input type="text" class="ecv2-input" placeholder="' . esc_attr( $meta['placeholder'] ) . '" disabled />';
			} else {
				echo '<div class="ecv2-cl-locked-limits">';
				echo '<label class="ecv2-cl-field"><span>' . esc_html__( 'Expires', 'wp-easycart' ) . '</span><input type="text" class="ecv2-input" placeholder="—" disabled /></label>';
				echo '<label class="ecv2-cl-field"><span>' . esc_html__( 'Max uses', 'wp-easycart' ) . '</span><input type="text" class="ecv2-input" placeholder="' . esc_attr__( 'Unlimited', 'wp-easycart' ) . '" disabled /></label>';
				echo '</div>';
			}
			echo '<div class="ecv2-cl-locked-overlay"><span class="dashicons dashicons-lock"></span> <span>' . esc_html( isset( $features[ $key ] ) ? $features[ $key ]['desc'] : '' ) . '</span></div>';
			echo '</div>';
			echo '</section>';
		}
	}

	public function print_row_menu_upsell( $link ) {
		if ( self::is_pro() ) {
			return;
		}
		echo '<a href="#" class="ecv2-row-menu-item ecv2-row-menu-item-locked" onclick="' . wp_easycart_admin_upsell::onclick( 'cart_links', 'qr' ) . '"><span class="dashicons dashicons-smartphone"></span>' . esc_html__( 'Download QR code', 'wp-easycart' ) . ' <span class="ecv2-cl-pro-badge">PRO</span></a>';
	}

	public function print_banner_upsell( $link ) {
		if ( self::is_pro() ) {
			return;
		}
		echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-cl-btn-locked" onclick="' . wp_easycart_admin_upsell::onclick( 'cart_links', 'qr' ) . '"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'QR code', 'wp-easycart' ) . '</button>';
	}

	public static function enqueue_assets() {
		if ( ! isset( $_GET['page'] ) || 'wp-easycart-rates' != $_GET['page'] || ! isset( $_GET['subpage'] ) || 'cart-links' != $_GET['subpage'] ) {
			return;
		}
		wp_enqueue_style( 'wp_easycart_admin_v2_css', plugins_url( 'wp-easycart/admin/css/admin-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
		wp_enqueue_style( 'wp_easycart_admin_cart_links_css', plugins_url( 'wp-easycart/admin/css/admin-cart-links.css', EC_PLUGIN_DIRECTORY ), array( 'wp_easycart_admin_v2_css', 'wp_easycart_admin_upsell_css' ), EC_CURRENT_VERSION );
		wp_enqueue_script( 'wp_easycart_admin_cart_links_js', plugins_url( 'wp-easycart/admin/js/cart-links.js', EC_PLUGIN_DIRECTORY ), array( 'jquery', 'wp_easycart_admin_upsell_js' ), EC_CURRENT_VERSION, true );
		wp_localize_script( 'wp_easycart_admin_cart_links_js', 'ecv2_cart_link_vars', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
			'lang'     => array(
				'error'          => __( 'Something went wrong. Please try again.', 'wp-easycart' ),
				'saved'          => __( 'Cart link saved.', 'wp-easycart' ),
				'deleted'        => __( 'Cart link deleted.', 'wp-easycart' ),
				'copied'         => __( 'Link copied to clipboard.', 'wp-easycart' ),
				'confirm_delete' => __( 'Delete this cart link? Anywhere it is already shared or printed, it will stop working.', 'wp-easycart' ),
				'delete'         => __( 'Delete', 'wp-easycart' ),
				'select_product' => __( 'Search for a product first.', 'wp-easycart' ),
				'no_products'    => __( 'No matching products found.', 'wp-easycart' ),
				'searching'      => __( 'Searching…', 'wp-easycart' ),
				'any'            => __( 'Choose…', 'wp-easycart' ),
				'active'         => __( 'Active', 'wp-easycart' ),
				'inactive'       => __( 'Inactive', 'wp-easycart' ),
				'uses'           => __( '%d uses', 'wp-easycart' ),
			),
			'is_pro'       => self::is_pro(),
		) );
		do_action( 'wp_easycart_ecv2_cart_link_enqueue' );
	}

	/* ------------------------------------------------------------------ */
	/* Page                                                                */
	/* ------------------------------------------------------------------ */

	public function load_cart_links_list() {
		if ( ! self::current_user_allowed() ) {
			return;
		}
		include( EC_PLUGIN_DIRECTORY . '/admin/template/marketing/cart-links/cart-links.php' );
	}

	public function get_links() {
		global $wpdb;
		$links = $wpdb->get_results(
			'SELECT l.*, ( SELECT COUNT(*) FROM ec_cart_link_item i WHERE i.cart_link_id = l.cart_link_id ) AS item_count
			 FROM ec_cart_link l ORDER BY l.created_at DESC, l.cart_link_id DESC'
		);
		foreach ( $links as $link ) {
			$link->url = wp_easycart_cart_link::get_url( $link->link_token );
			$link->summary = $this->link_summary( $link->cart_link_id );
		}
		return $links;
	}

	private function link_summary( $cart_link_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT i.quantity, p.title FROM ec_cart_link_item i LEFT JOIN ec_product p ON p.product_id = i.product_id WHERE i.cart_link_id = %d ORDER BY i.sort_order ASC LIMIT 4',
			$cart_link_id
		) );
		$parts = array();
		foreach ( $rows as $row ) {
			$parts[] = ( $row->quantity > 1 ? (int) $row->quantity . ' × ' : '' ) . wp_unslash( (string) $row->title );
		}
		return $parts;
	}

	/** One list row ( server-rendered; the page reloads after drawer close ). */
	public function print_link_row( $link ) {
		$dest_labels = array( 'cart' => __( 'Cart', 'wp-easycart' ), 'checkout' => __( 'Checkout', 'wp-easycart' ) );
		echo '<tr class="ecv2-row" data-cart-link-id="' . esc_attr( $link->cart_link_id ) . '">';

		echo '<td class="ecv2-cell ecv2-cell-label">';
		echo '<div class="ecv2-cl-label">' . esc_html( '' !== $link->link_label ? $link->link_label : __( '(untitled link)', 'wp-easycart' ) ) . '</div>';
		echo '<div class="ecv2-cl-url"><code>' . esc_html( $link->link_token ) . '</code>';
		echo '<button type="button" class="ecv2-cl-copy" data-url="' . esc_attr( $link->url ) . '" title="' . esc_attr__( 'Copy link', 'wp-easycart' ) . '" onclick="ecv2_cart_link_copy_row( this );"><span class="dashicons dashicons-clipboard"></span></button>';
		echo '</div>';
		echo '</td>';

		echo '<td class="ecv2-cell ecv2-cell-contents">';
		if ( empty( $link->summary ) ) {
			echo '<span class="ecv2-qe-muted">—</span>';
		} else {
			echo esc_html( implode( ', ', $link->summary ) );
			if ( (int) $link->item_count > count( $link->summary ) ) {
				echo ' <span class="ecv2-qe-muted">+' . esc_html( (int) $link->item_count - count( $link->summary ) ) . '</span>';
			}
		}
		do_action( 'wp_easycart_ecv2_cart_link_row_contents', $link );
		echo '</td>';

		echo '<td class="ecv2-cell ecv2-hide-tablet">' . esc_html( isset( $dest_labels[ $link->destination ] ) ? $dest_labels[ $link->destination ] : $link->destination ) . '</td>';

		echo '<td class="ecv2-cell ecv2-cell-uses">' . esc_html( (int) $link->use_count );
		do_action( 'wp_easycart_ecv2_cart_link_row_uses', $link );
		echo '</td>';

		$expired = ( $link->expires && strtotime( $link->expires ) < time() );
		$maxed   = ( $link->max_uses > 0 && $link->use_count >= $link->max_uses );
		echo '<td class="ecv2-cell ecv2-cell-status">';
		echo '<label class="ecv2-switch"><input type="checkbox" ' . checked( 1, (int) $link->is_active, false ) . ' onchange="ecv2_cart_link_toggle( this, ' . esc_attr( $link->cart_link_id ) . ' );" /><span class="ecv2-switch-slider"></span></label>';
		if ( $expired ) {
			echo ' <span class="ecv2-cl-flag">' . esc_html__( 'Expired', 'wp-easycart' ) . '</span>';
		} else if ( $maxed ) {
			echo ' <span class="ecv2-cl-flag">' . esc_html__( 'Limit reached', 'wp-easycart' ) . '</span>';
		}
		echo '</td>';

		echo '<td class="ecv2-cell ecv2-cell-actions"><div class="ecv2-row-menu-wrap">';
		echo '<button type="button" class="ecv2-row-menu-trigger" onclick="ecv2_cart_link_menu( this );" aria-label="' . esc_attr__( 'Actions', 'wp-easycart' ) . '"><span class="dashicons dashicons-ellipsis"></span></button>';
		echo '<div class="ecv2-row-menu">';
		echo '<a href="#" class="ecv2-row-menu-item" onclick="ecv2_cart_link_edit( ' . esc_attr( $link->cart_link_id ) . ' ); return false;"><span class="dashicons dashicons-edit"></span>' . esc_html__( 'Edit', 'wp-easycart' ) . '</a>';
		echo '<a href="#" class="ecv2-row-menu-item" data-url="' . esc_attr( $link->url ) . '" onclick="ecv2_cart_link_copy_row( this ); return false;"><span class="dashicons dashicons-clipboard"></span>' . esc_html__( 'Copy link', 'wp-easycart' ) . '</a>';
		do_action( 'wp_easycart_ecv2_cart_link_row_menu', $link );
		echo '<div class="ecv2-row-menu-sep"></div>';
		echo '<a href="#" class="ecv2-row-menu-item ecv2-row-menu-item-danger" onclick="ecv2_cart_link_delete( ' . esc_attr( $link->cart_link_id ) . ' ); return false;"><span class="dashicons dashicons-trash"></span>' . esc_html__( 'Delete', 'wp-easycart' ) . '</a>';
		echo '</div></div></td>';

		echo '</tr>';
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	private static function verify_request() {
		if ( ! self::current_user_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
		}
		if ( ! isset( $_POST['wp_easycart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_easycart_nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-easycart' ) ) );
		}
	}

	/** Product picker: active products a cart link can preload. */
	public static function ajax_product_search() {
		self::verify_request();
		global $wpdb;
		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$like = '%' . $wpdb->esc_like( $term ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT product_id, title, model_number, price, is_giftcard
			 FROM ec_product
			 WHERE activate_in_store = 1
			   AND is_subscription_item = 0 AND is_donation = 0 AND is_deconetwork = 0
			   AND ( title LIKE %s OR model_number LIKE %s )
			 ORDER BY title ASC LIMIT 20',
			$like, $like
		) );
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'product_id' => (int) $row->product_id,
				'title'      => wp_unslash( (string) $row->title ),
				'sku'        => (string) $row->model_number,
				'price'      => $GLOBALS['currency']->get_currency_display( (float) $row->price ),
			);
		}
		wp_send_json_success( array( 'products' => $out ) );
	}

	/** Everything the drawer needs to configure one product line. */
	public static function ajax_product_data() {
		self::verify_request();
		global $wpdb;
		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$product = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_product WHERE product_id = %d', $product_id ) );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'wp-easycart' ) ) );
		}

		/* Basic option slots ( create variations; selects are required ). */
		$basic = array();
		if ( ! $product->use_advanced_optionset || $product->use_both_option_types ) {
			for ( $slot = 1; $slot <= 5; $slot++ ) {
				$option_id = (int) $product->{ 'option_id_' . $slot };
				if ( ! $option_id ) {
					continue;
				}
				$option = $wpdb->get_row( $wpdb->prepare( 'SELECT option_id, option_label FROM ec_option WHERE option_id = %d', $option_id ) );
				if ( ! $option ) {
					continue;
				}
				$items = $wpdb->get_results( $wpdb->prepare( 'SELECT optionitem_id, optionitem_name FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_order ASC, optionitem_name ASC', $option_id ) );
				$basic[] = array(
					'slot'  => $slot,
					'label' => wp_unslash( (string) $option->option_label ),
					'items' => array_map( function( $item ) {
						return array( 'id' => (int) $item->optionitem_id, 'name' => wp_unslash( (string) $item->optionitem_name ) );
					}, $items ),
				);
			}
		}

		$payload = array(
			'product_id' => (int) $product->product_id,
			'title'      => wp_unslash( (string) $product->title ),
			'sku'        => (string) $product->model_number,
			'price'      => $GLOBALS['currency']->get_currency_display( (float) $product->price ),
			'basic'      => $basic,
			'modifiers'  => array(), /* PRO fills via the filter below */
		);
		wp_send_json_success( apply_filters( 'wp_easycart_ecv2_cart_link_product_data', $payload, $product ) );
	}

	public static function ajax_get() {
		self::verify_request();
		$link = wp_easycart_cart_link::get_link( isset( $_POST['cart_link_id'] ) ? (int) $_POST['cart_link_id'] : 0 );
		if ( ! $link ) {
			wp_send_json_error( array( 'message' => __( 'Cart link not found.', 'wp-easycart' ) ) );
		}
		$link->url = wp_easycart_cart_link::get_url( $link->link_token );
		wp_send_json_success( apply_filters( 'wp_easycart_ecv2_cart_link_get', $link ) );
	}

	public static function ajax_save() {
		self::verify_request();
		$cart_link_id = isset( $_POST['cart_link_id'] ) ? (int) $_POST['cart_link_id'] : 0;

		$data = array(
			'link_label'  => isset( $_POST['link_label'] ) ? sanitize_text_field( wp_unslash( $_POST['link_label'] ) ) : '',
			'destination' => isset( $_POST['destination'] ) ? sanitize_key( $_POST['destination'] ) : 'cart',
			'clear_cart'  => ! empty( $_POST['clear_cart'] ) ? 1 : 0,
			'is_active'   => isset( $_POST['is_active'] ) ? (int) (bool) $_POST['is_active'] : 1,
			/* FREE ships no codes / expiry / max uses; PRO merges them here. */
			'promo_codes' => array(),
			'expires'     => '',
			'max_uses'    => 0,
		);
		$data = apply_filters( 'wp_easycart_ecv2_cart_link_save_data', $data, $_POST );

		$raw_items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$items = array();
		foreach ( $raw_items as $raw ) {
			$items[] = array(
				'product_id'      => isset( $raw['product_id'] ) ? (int) $raw['product_id'] : 0,
				'quantity'        => isset( $raw['quantity'] ) ? max( 1, (int) $raw['quantity'] ) : 1,
				'optionitem_id_1' => isset( $raw['optionitem_id_1'] ) ? (int) $raw['optionitem_id_1'] : 0,
				'optionitem_id_2' => isset( $raw['optionitem_id_2'] ) ? (int) $raw['optionitem_id_2'] : 0,
				'optionitem_id_3' => isset( $raw['optionitem_id_3'] ) ? (int) $raw['optionitem_id_3'] : 0,
				'optionitem_id_4' => isset( $raw['optionitem_id_4'] ) ? (int) $raw['optionitem_id_4'] : 0,
				'optionitem_id_5' => isset( $raw['optionitem_id_5'] ) ? (int) $raw['optionitem_id_5'] : 0,
				'modifier_values' => array(), /* PRO merges via filter */
				'_raw'            => $raw,
			);
		}
		$items = apply_filters( 'wp_easycart_ecv2_cart_link_save_items', $items, $_POST );
		foreach ( $items as &$item ) {
			unset( $item['_raw'] );
		}
		unset( $item );
		/* FREE links hold a single product; PRO lifts the cap via the filter. */
		$max_items = apply_filters( 'wp_easycart_ecv2_cart_link_max_items', 1 );
		if ( count( $items ) > $max_items ) {
			$items = array_slice( $items, 0, $max_items );
		}
		if ( empty( $items ) || ! $items[0]['product_id'] ) {
			wp_send_json_error( array( 'message' => __( 'Add at least one product to the link.', 'wp-easycart' ) ) );
		}

		$cart_link_id = wp_easycart_cart_link::save_link( $cart_link_id, $data, $items );
		if ( ! $cart_link_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save the cart link.', 'wp-easycart' ) ) );
		}
		$link = wp_easycart_cart_link::get_link( $cart_link_id, false );
		wp_send_json_success( array(
			'cart_link_id' => $cart_link_id,
			'token'        => $link->link_token,
			'url'          => wp_easycart_cart_link::get_url( $link->link_token ),
		) );
	}

	public static function ajax_delete() {
		self::verify_request();
		wp_easycart_cart_link::delete_link( isset( $_POST['cart_link_id'] ) ? (int) $_POST['cart_link_id'] : 0 );
		wp_send_json_success();
	}

	public static function ajax_set_active() {
		self::verify_request();
		wp_easycart_cart_link::set_active( isset( $_POST['cart_link_id'] ) ? (int) $_POST['cart_link_id'] : 0, ! empty( $_POST['is_active'] ) );
		wp_send_json_success();
	}
}

/*
 * Hooks registered at file scope, matching the other admin table/list classes
 * ( admin-init.php includes this file on every admin + admin-ajax request ).
 */
add_action( 'admin_enqueue_scripts', array( 'wp_easycart_admin_cart_links', 'enqueue_assets' ), 20 );

/*
 * Upsell stand-ins ride the very hooks PRO renders into. Each one returns
 * early via is_pro(), so with a licensed PRO present they emit nothing.
 * Priority 99: is_pro() must run AFTER PRO has had its chance to register.
 * These are instance callbacks; the page template instantiates $this, so a
 * throwaway instance is used here ( the methods hold no state ).
 */
add_action( 'wp_easycart_ecv2_cart_link_drawer_items_end', array( new wp_easycart_admin_cart_links(), 'print_drawer_upsell_items_end' ), 99 );
add_action( 'wp_easycart_ecv2_cart_link_drawer_fields', array( new wp_easycart_admin_cart_links(), 'print_drawer_upsell_fields' ), 99 );
add_action( 'wp_easycart_ecv2_cart_link_row_menu', array( new wp_easycart_admin_cart_links(), 'print_row_menu_upsell' ), 99 );
add_action( 'wp_easycart_ecv2_cart_link_saved_banner', array( new wp_easycart_admin_cart_links(), 'print_banner_upsell' ), 99 );
add_action( 'wp_ajax_ecv2_cart_link_product_search', array( 'wp_easycart_admin_cart_links', 'ajax_product_search' ) );
add_action( 'wp_ajax_ecv2_cart_link_product_data', array( 'wp_easycart_admin_cart_links', 'ajax_product_data' ) );
add_action( 'wp_ajax_ecv2_cart_link_get', array( 'wp_easycart_admin_cart_links', 'ajax_get' ) );
add_action( 'wp_ajax_ecv2_cart_link_save', array( 'wp_easycart_admin_cart_links', 'ajax_save' ) );
add_action( 'wp_ajax_ecv2_cart_link_delete', array( 'wp_easycart_admin_cart_links', 'ajax_delete' ) );
add_action( 'wp_ajax_ecv2_cart_link_set_active', array( 'wp_easycart_admin_cart_links', 'ajax_set_active' ) );