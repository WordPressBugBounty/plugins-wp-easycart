<?php
/**
 * Settings › Products ( V2 declaration ).
 *
 * Replaces the classic Settings › Products page ( admin/template/settings/products/*.php,
 * saved by wp_easycart_admin_products::save_product_settings_v2() ) and absorbs the
 * storefront search block and the product export limit from Additional Settings.
 *
 * Every key the legacy page could save is declared here. The legacy multi-selects and
 * the category select read the store tables, which can hold thousands of rows, so none
 * of them is loaded up front: the roles list is an 'options' callable resolved only
 * when the page renders or saves, and the category / manufacturer / option-set rows are
 * search pickers ( 'search_callback' + 'validate_callback' ) that query with LIKE as the
 * merchant types and check the posted ids with WHERE … IN on save. The stored formats
 * are unchanged ( roles joined by '***', ids joined by ',' ).
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_settings_products_has_db' ) ) {
	/** True inside WordPress with a database; false for the migration-map generator, which includes this file standalone. */
	function wp_easycart_settings_products_has_db() {
		return isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_results' );
	}
}

if ( ! function_exists( 'wp_easycart_settings_products_role_options' ) ) {
	/**
	 * 'options' callable for "Who can view the store": the fixed "No restrictions"
	 * choice plus every customer role. Runs only when the Products page renders or saves.
	 *
	 * @return array value => label
	 */
	function wp_easycart_settings_products_role_options() {
		$options = array( '0' => __( 'No restrictions', 'wp-easycart' ) );
		if ( ! wp_easycart_settings_products_has_db() ) {
			return $options;
		}
		$rows = $GLOBALS['wpdb']->get_results( 'SELECT role_label AS value, role_label AS label FROM ec_role WHERE admin_access = 0 ORDER BY role_label ASC' );
		foreach ( (array) $rows as $row ) {
			if ( isset( $row->value ) && '' !== (string) $row->value ) {
				$options[ (string) $row->value ] = (string) $row->label;
			}
		}
		return $options;
	}
}

if ( ! function_exists( 'wp_easycart_settings_products_picker_sql' ) ) {
	/**
	 * Column map for the three store-table pickers. Categories carry their parent's
	 * name as the hint so same-named sub-categories can be told apart.
	 *
	 * @since 6.0.0
	 * @param string $kind categories | manufacturers | options
	 * @return array|false select, from, id column, label column, order clause
	 */
	function wp_easycart_settings_products_picker_sql( $kind ) {
		switch ( $kind ) {
			case 'categories':
				return array(
					'select' => 'c.category_id AS value, c.category_name AS label, p.category_name AS parent',
					'from'   => 'ec_category c LEFT JOIN ec_category p ON p.category_id = c.parent_id',
					'id'     => 'c.category_id',
					'label'  => 'c.category_name',
					'order'  => 'c.priority DESC, c.category_name ASC',
				);
			case 'manufacturers':
				return array(
					'select' => 'manufacturer_id AS value, `name` AS label',
					'from'   => 'ec_manufacturer',
					'id'     => 'manufacturer_id',
					'label'  => '`name`',
					'order'  => '`name` ASC',
				);
			case 'options':
				return array(
					'select' => 'option_id AS value, option_name AS label',
					'from'   => 'ec_option',
					'id'     => 'option_id',
					'label'  => 'option_name',
					'order'  => 'option_name ASC',
				);
		}
		return false;
	}
}

if ( ! function_exists( 'wp_easycart_settings_products_picker_rows' ) ) {
	/** Query rows → value / label / hint rows for the registry. */
	function wp_easycart_settings_products_picker_rows( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! isset( $row->value ) || '' === (string) $row->value ) {
				continue;
			}
			$hint = '';
			if ( isset( $row->parent ) && '' !== (string) $row->parent ) {
				/* translators: %s: parent category name */
				$hint = sprintf( __( 'in %s', 'wp-easycart' ), (string) $row->parent );
			}
			$out[] = array( 'value' => (string) $row->value, 'label' => (string) $row->label, 'hint' => $hint );
		}
		return $out;
	}
}

if ( ! function_exists( 'wp_easycart_settings_products_picker_search' ) ) {
	/**
	 * 'search_callback' for the store-table pickers: rows whose name contains the typed
	 * text ( LIKE ), at most $limit, so a store with thousands of categories never sends
	 * them all to the browser. An empty term lists the first $limit in display order.
	 *
	 * @since 6.0.0
	 * @param string $kind  categories | manufacturers | options
	 * @param string $term  Typed text.
	 * @param int    $limit Most rows to return.
	 * @return array rows of value / label / hint
	 */
	function wp_easycart_settings_products_picker_search( $kind, $term, $limit ) {
		$sql = wp_easycart_settings_products_picker_sql( $kind );
		if ( ! $sql || ! wp_easycart_settings_products_has_db() ) {
			return array();
		}
		$wpdb  = $GLOBALS['wpdb'];
		$limit = max( 1, min( (int) $limit, 50 ) );
		$term  = trim( (string) $term );
		if ( '' === $term ) {
			$query = $wpdb->prepare( 'SELECT ' . $sql['select'] . ' FROM ' . $sql['from'] . ' ORDER BY ' . $sql['order'] . ' LIMIT %d', $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed column and table names from wp_easycart_settings_products_picker_sql().
		} else {
			$like  = '%' . $wpdb->esc_like( $term ) . '%';
			$query = $wpdb->prepare( 'SELECT ' . $sql['select'] . ' FROM ' . $sql['from'] . ' WHERE ' . $sql['label'] . ' LIKE %s ORDER BY ' . $sql['order'] . ' LIMIT %d', $like, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed column and table names from wp_easycart_settings_products_picker_sql().
		}
		return wp_easycart_settings_products_picker_rows( $wpdb->get_results( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}
}

if ( ! function_exists( 'wp_easycart_settings_products_picker_lookup' ) ) {
	/**
	 * 'validate_callback' for the store-table pickers: the rows behind the given ids
	 * ( WHERE id IN … ), used on save and to label the chosen chips. Ids that no longer
	 * exist are simply absent, so a deleted category drops out of the setting.
	 *
	 * @since 6.0.0
	 * @param string $kind   categories | manufacturers | options
	 * @param array  $values Posted or stored ids.
	 * @return array rows of value / label / hint
	 */
	function wp_easycart_settings_products_picker_lookup( $kind, $values ) {
		$sql = wp_easycart_settings_products_picker_sql( $kind );
		$ids = array();
		foreach ( (array) $values as $value ) {
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				$ids[] = (int) $value;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		if ( ! $sql || empty( $ids ) || ! wp_easycart_settings_products_has_db() ) {
			return array();
		}
		$wpdb  = $GLOBALS['wpdb'];
		$query = $wpdb->prepare( 'SELECT ' . $sql['select'] . ' FROM ' . $sql['from'] . ' WHERE ' . $sql['id'] . ' IN ( ' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ' )', $ids ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed column and table names; one %d placeholder per id.
		return wp_easycart_settings_products_picker_rows( $wpdb->get_results( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}
}

if ( ! function_exists( 'wp_easycart_settings_products_picker' ) ) {
	/**
	 * The picker keys a category / manufacturer / option-set row needs: search and
	 * lookup callables bound to one table, and no printed option list at all.
	 *
	 * @since 6.0.0
	 * @param string $kind categories | manufacturers | options
	 */
	function wp_easycart_settings_products_picker( $kind ) {
		return array(
			'display'           => 'remote',
			'search_callback'   => function ( $term, $field, $limit ) use ( $kind ) {
				return wp_easycart_settings_products_picker_search( $kind, $term, $limit );
			},
			'validate_callback' => function ( $values, $field ) use ( $kind ) {
				return wp_easycart_settings_products_picker_lookup( $kind, $values );
			},
		);
	}
}

if ( ! function_exists( 'wp_easycart_settings_products_catalog_warning' ) ) {
	/** Non-blocking reminder shown after catalog mode is switched on. */
	function wp_easycart_settings_products_catalog_warning( $value, $field ) {
		return $value ? __( 'Shoppers cannot buy anything while catalog mode is on.', 'wp-easycart' ) : '';
	}
}

if ( ! function_exists( 'wp_easycart_settings_products_stock_warning' ) ) {
	/** Non-blocking reminder shown after stock counts are hidden store-wide. */
	function wp_easycart_settings_products_stock_warning( $value, $field ) {
		return $value ? '' : __( 'Stock levels are now hidden on every product, whatever each product’s own setting says.', 'wp-easycart' );
	}
}

$wp_easycart_settings_products_sort_labels = array(
	'0' => __( 'Default order (the order you set in admin)', 'wp-easycart' ),
	'1' => __( 'Price, low to high', 'wp-easycart' ),
	'2' => __( 'Price, high to low', 'wp-easycart' ),
	'3' => __( 'Title, A to Z', 'wp-easycart' ),
	'4' => __( 'Title, Z to A', 'wp-easycart' ),
	'5' => __( 'Newest first', 'wp-easycart' ),
	'8' => __( 'Oldest first', 'wp-easycart' ),
	'6' => __( 'Best rated first', 'wp-easycart' ),
	'7' => __( 'Most viewed first', 'wp-easycart' ),
);

$wp_easycart_settings_products_sizing = array( '' => __( 'Theme default', 'wp-easycart' ) );
for ( $wp_easycart_settings_products_i = 5; $wp_easycart_settings_products_i <= 95; $wp_easycart_settings_products_i += 5 ) {
	/* translators: 1: image column percentage, 2: details column percentage. */
	$wp_easycart_settings_products_sizing[ (string) $wp_easycart_settings_products_i ] = sprintf( __( '%1$d%% image / %2$d%% details', 'wp-easycart' ), $wp_easycart_settings_products_i, 100 - $wp_easycart_settings_products_i );
}

return array(
	'slug'        => 'products',
	'title'       => __( 'Products', 'wp-easycart' ),
	'description' => __( 'How products look on their pages and in lists, what happens when a shopper adds one to the cart, stock, reviews and search.', 'wp-easycart' ),
	'group'       => 'store-setup',
	'icon'        => 'products',
	'docs'        => array( 'settings', 'product-settings', 'product-display' ),
	'legacy'      => array( 'products' ),
	'upsell'      => 'products',
	'sections'    => array(

		/* ------------------------------------------------------------------ */
		'catalog-mode' => array(
			'title'  => __( 'Catalog mode & access', 'wp-easycart' ),
			'hint'   => __( 'Pause selling, or limit who can see the store', 'wp-easycart' ),
			'fields' => array(
				'ec_option_display_as_catalog' => array(
					'type'     => 'toggle',
					'label'    => __( 'Catalog mode', 'wp-easycart' ),
					'desc'     => __( 'Removes every Add to cart button and the cart itself, so shoppers can browse but not buy. Use it for a vacation or a showcase site.', 'wp-easycart' ),
					'default'  => 0,
					'validate' => 'wp_easycart_settings_products_catalog_warning',
					'keywords' => array( 'vacation', 'showcase', 'disable purchasing', 'browse only' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Display', 'label' => 'Catalog Mode' ),
				),
				'ec_option_vacation_mode_button_text' => array(
					'type'        => 'text',
					'label'       => __( 'Text shown where the Add to cart button was', 'wp-easycart' ),
					'desc'        => __( 'Optional. Leave empty to show nothing in the button’s place.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => __( 'Back on 1 June', 'wp-easycart' ),
					'parent'      => 'ec_option_display_as_catalog',
					'keywords'    => array( 'vacation', 'holiday', 'closed', 'catalog' ),
					'legacy'      => array( 'page' => 'products', 'section' => 'Product Display', 'label' => 'Vacation Mode: Button Text' ),
				),
				'ec_option_vacation_mode_banner_text' => array(
					'type'        => 'text',
					'label'       => __( 'Banner above products and the cart', 'wp-easycart' ),
					'desc'        => __( 'Optional message shown at the top of product and cart pages while catalog mode is on.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => __( 'We are away until 1 June. Orders reopen then.', 'wp-easycart' ),
					'parent'      => 'ec_option_display_as_catalog',
					'keywords'    => array( 'vacation', 'holiday', 'notice', 'catalog' ),
					'legacy'      => array( 'page' => 'products', 'section' => 'Product Display', 'label' => 'Vacation Mode: Banner Text' ),
				),
				'ec_option_restrict_store' => array(
					'type'      => 'multiselect',
					'separator' => '***',
					'options'   => 'wp_easycart_settings_products_role_options',
					'exclusive' => array( '0' ),
					'label'     => __( 'Who can view the store', 'wp-easycart' ),
					'desc'      => __( 'Pick “No restrictions” for a public store, or the customer roles allowed in. Restricted shoppers must sign in before the store shows.', 'wp-easycart' ),
					'default'   => '0',
					'keywords' => array( 'restrict', 'private', 'wholesale', 'roles', 'login' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Display', 'label' => 'Restrict Store' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'product-pages' => array(
			'title'  => __( 'Product pages', 'wp-easycart' ),
			'hint'   => __( 'What appears on a single product’s page', 'wp-easycart' ),
			'fields' => array(
				'ec_option_show_breadcrumbs' => array(
					'type'     => 'toggle',
					'label'    => __( 'Breadcrumb links', 'wp-easycart' ),
					'desc'     => __( 'Store › Category › Product links at the top of the page.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'navigation', 'trail', 'links' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Breadcrumbs' ),
				),
				'ec_option_show_model_number' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the model number', 'wp-easycart' ),
					'desc'     => __( 'Prints the product’s model number ( SKU ) near the title.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'sku', 'model', 'code' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Model Number' ),
				),
				'ec_option_show_categories' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the product’s categories', 'wp-easycart' ),
					'desc'     => __( 'Lists each category the product belongs to, linked to that category.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'category', 'links', 'taxonomy' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Categories' ),
				),
				'ec_option_show_manufacturer' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the manufacturer', 'wp-easycart' ),
					'desc'     => __( 'Names the manufacturer, linked to every product they make.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'brand', 'maker', 'vendor' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Manufacturer' ),
				),
				'ec_option_show_magnification' => array(
					'type'     => 'toggle',
					'label'    => __( 'Zoom on image hover', 'wp-easycart' ),
					'desc'     => __( 'A magnified panel follows the cursor over the main image. Off on phones and tablets regardless.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'magnify', 'zoom', 'hover', 'image' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Image Magnification' ),
				),
				'ec_option_show_large_popup' => array(
					'type'     => 'toggle',
					'label'    => __( 'Open images in a lightbox', 'wp-easycart' ),
					'desc'     => __( 'Clicking the main image opens a larger version in a popup.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'lightbox', 'popup', 'gallery', 'image' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Image Lightbox' ),
				),
				'ec_option_product_details_sizing' => array(
					'type'     => 'select',
					'label'    => __( 'Image and details column split', 'wp-easycart' ),
					'desc'     => __( 'How much of the page width the image column takes versus the details column. A shortcode’s details_sizing attribute overrides this.', 'wp-easycart' ),
					'default'  => '',
					'options'  => $wp_easycart_settings_products_sizing,
					'advanced' => true,
					'keywords' => array( 'layout', 'width', 'columns', 'sizing' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Product Details: Image/Details Sizing' ),
				),
				'ec_option_short_description_below' => array(
					'type'     => 'toggle',
					'label'    => __( 'Short description below the Add to cart button', 'wp-easycart' ),
					'desc'     => __( 'Moves the short description under the button instead of above it.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'short description', 'layout', 'position' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Short Description Below' ),
				),
				'ec_option_product_image_default' => array(
					'type'        => 'url',
					'label'       => __( 'Placeholder image for products without a photo', 'wp-easycart' ),
					'desc'        => __( 'Full URL of an image from your media library. Shown in the cart and on orders when a product has no image.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'https://',
					'keywords'    => array( 'default image', 'placeholder', 'missing image', 'no photo' ),
					'legacy'      => array( 'page' => 'products', 'section' => 'Product Display', 'label' => 'Default Product Image' ),
				),
				'ec_option_model_number_extension' => array(
					'type'        => 'text',
					'label'       => __( 'Separator between the model number and option codes', 'wp-easycart' ),
					'desc'        => __( 'Placed between a product’s model number and each chosen option’s code on cart lines and orders, for example SHIRT-RED-L.', 'wp-easycart' ),
					'default'     => '-',
					'placeholder' => '-',
					'advanced'    => true,
					'keywords'    => array( 'sku', 'model number', 'options', 'separator' ),
					'legacy'      => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Model Number Extension' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'sharing' => array(
			'title'  => __( 'Social sharing', 'wp-easycart' ),
			'hint'   => __( 'Share links shown on every product page', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_facebook_icon' => array(
					'type'     => 'toggle',
					'label'    => __( 'Facebook share link', 'wp-easycart' ),
					'desc'     => __( 'Adds a Facebook share icon to each product page.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'social', 'share', 'facebook' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Social Icon: Facebook' ),
				),
				'ec_option_use_twitter_icon' => array(
					'type'     => 'toggle',
					'label'    => __( 'X (Twitter) share link', 'wp-easycart' ),
					'desc'     => __( 'Adds an X share icon to each product page.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'social', 'share', 'twitter', 'x' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Social Icon: X (Twitter)' ),
				),
				'ec_option_use_pinterest_icon' => array(
					'type'     => 'toggle',
					'label'    => __( 'Pinterest share link', 'wp-easycart' ),
					'desc'     => __( 'Adds a Pin it icon to each product page.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'social', 'share', 'pinterest', 'pin' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Social Icon: Pinterest' ),
				),
				'ec_option_use_email_icon' => array(
					'type'     => 'toggle',
					'label'    => __( 'Email share link', 'wp-easycart' ),
					'desc'     => __( 'Adds a share-by-email icon to each product page.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'social', 'share', 'email', 'mailto' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Social Icon: Email' ),
				),
				'ec_option_use_linkedin_icon' => array(
					'type'     => 'toggle',
					'label'    => __( 'LinkedIn share link', 'wp-easycart' ),
					'desc'     => __( 'Adds a LinkedIn share icon to each product page.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'social', 'share', 'linkedin' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Social Icon: LinkedIn' ),
				),
				'ec_option_use_delicious_icon' => array(
					'type'     => 'toggle',
					'label'    => __( 'Delicious share link', 'wp-easycart' ),
					'desc'     => __( 'Adds a Delicious bookmark icon to each product page. The service is largely defunct.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'social', 'share', 'delicious', 'bookmark' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Social Icon: Delicious' ),
				),
				'ec_option_use_myspace_icon' => array(
					'type'     => 'toggle',
					'label'    => __( 'MySpace share link', 'wp-easycart' ),
					'desc'     => __( 'Adds a MySpace share icon to each product page.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'social', 'share', 'myspace' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Social Icon: MySpace' ),
				),
				'ec_option_use_digg_icon' => array(
					'type'     => 'toggle',
					'label'    => __( 'Digg share link', 'wp-easycart' ),
					'desc'     => __( 'Adds a Digg share icon to each product page.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'social', 'share', 'digg' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Social Icon: Digg' ),
				),
				'ec_option_use_googleplus_icon' => array(
					'type'     => 'toggle',
					'label'    => __( 'Google+ share link', 'wp-easycart' ),
					'desc'     => __( 'Adds a Google+ share icon to each product page. Google+ has closed; the link no longer works.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'social', 'share', 'google plus', 'google+' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Social Icon: Google+' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'product-lists' => array(
			'title'  => __( 'Product lists', 'wp-easycart' ),
			'hint'   => __( 'The store page, category pages and other product grids', 'wp-easycart' ),
			'fields' => array(
				'ec_option_show_sort_box' => array(
					'type'     => 'toggle',
					'label'    => __( 'Sort menu', 'wp-easycart' ),
					'desc'     => __( 'Lets shoppers reorder the list by price, title, rating and more.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'sort', 'order', 'dropdown', 'filter' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting' ),
				),
				'ec_option_default_store_filter' => array(
					'type'     => 'select',
					'label'    => __( 'Default sort order', 'wp-easycart' ),
					'desc'     => __( 'The order products appear in until a shopper picks another.', 'wp-easycart' ),
					'default'  => '0',
					'options'  => $wp_easycart_settings_products_sort_labels,
					'parent'   => 'ec_option_show_sort_box',
					'keywords' => array( 'sort', 'default', 'order' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Default Selection' ),
				),
				'ec_option_product_filter_0' => array(
					'type'     => 'toggle',
					'label'    => __( '“Default order” sort option', 'wp-easycart' ),
					'desc'     => __( 'Offers the admin-defined order in the sort menu.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_sort_box',
					'advanced' => true,
					'keywords' => array( 'sort', 'option', 'menu' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Default Sorting' ),
				),
				'ec_option_product_filter_1' => array(
					'type'     => 'toggle',
					'label'    => __( '“Price, low to high” sort option', 'wp-easycart' ),
					'desc'     => __( 'Offers cheapest-first in the sort menu.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_sort_box',
					'advanced' => true,
					'keywords' => array( 'sort', 'price', 'ascending' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Price Low-High' ),
				),
				'ec_option_product_filter_2' => array(
					'type'     => 'toggle',
					'label'    => __( '“Price, high to low” sort option', 'wp-easycart' ),
					'desc'     => __( 'Offers dearest-first in the sort menu.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_sort_box',
					'advanced' => true,
					'keywords' => array( 'sort', 'price', 'descending' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Price High-Low' ),
				),
				'ec_option_product_filter_3' => array(
					'type'     => 'toggle',
					'label'    => __( '“Title, A to Z” sort option', 'wp-easycart' ),
					'desc'     => __( 'Offers alphabetical order in the sort menu.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_sort_box',
					'advanced' => true,
					'keywords' => array( 'sort', 'title', 'alphabetical' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Title A-Z' ),
				),
				'ec_option_product_filter_4' => array(
					'type'     => 'toggle',
					'label'    => __( '“Title, Z to A” sort option', 'wp-easycart' ),
					'desc'     => __( 'Offers reverse alphabetical order in the sort menu.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_sort_box',
					'advanced' => true,
					'keywords' => array( 'sort', 'title', 'alphabetical' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Title Z-A' ),
				),
				'ec_option_product_filter_5' => array(
					'type'     => 'toggle',
					'label'    => __( '“Newest first” sort option', 'wp-easycart' ),
					'desc'     => __( 'Offers most recently added first in the sort menu.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_sort_box',
					'advanced' => true,
					'keywords' => array( 'sort', 'newest', 'date added' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Newest' ),
				),
				'ec_option_product_filter_8' => array(
					'type'     => 'toggle',
					'label'    => __( '“Oldest first” sort option', 'wp-easycart' ),
					'desc'     => __( 'Offers longest-listed first in the sort menu.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_sort_box',
					'advanced' => true,
					'keywords' => array( 'sort', 'oldest', 'date added' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Oldest' ),
				),
				'ec_option_product_filter_6' => array(
					'type'     => 'toggle',
					'label'    => __( '“Best rated” sort option', 'wp-easycart' ),
					'desc'     => __( 'Offers highest review rating first in the sort menu.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_sort_box',
					'advanced' => true,
					'keywords' => array( 'sort', 'rating', 'reviews' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Best Rating' ),
				),
				'ec_option_product_filter_7' => array(
					'type'     => 'toggle',
					'label'    => __( '“Most viewed” sort option', 'wp-easycart' ),
					'desc'     => __( 'Offers most-visited products first in the sort menu.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_sort_box',
					'advanced' => true,
					'keywords' => array( 'sort', 'views', 'popular' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Sorting: Most Viewed' ),
				),
				'ec_option_short_description_on_product' => array(
					'type'     => 'toggle',
					'label'    => __( 'Short description on grid tiles', 'wp-easycart' ),
					'desc'     => __( 'Shows each product’s short description under its title in the grid layout.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'grid', 'short description', 'tile', 'excerpt' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product Grid Type: Display Short Description' ),
				),
				'ec_option_show_featured_categories' => array(
					'type'     => 'toggle',
					'label'    => __( 'Featured categories first on the store page', 'wp-easycart' ),
					'desc'     => __( 'Shows your featured categories above the product grid on the first page of the store.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'featured', 'categories', 'landing', 'store page' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product List: Show Featured Categories First' ),
				),
				'ec_option_enable_product_paging' => array(
					'type'     => 'toggle',
					'label'    => __( 'Split products into pages', 'wp-easycart' ),
					'desc'     => __( 'Off shows every product on one page. Page sizes are set under Settings › Per page options.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'pagination', 'paging', 'per page', 'pages' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Enable Product Paging' ),
				),
				'ec_option_enable_product_paging_per_page' => array(
					'type'     => 'toggle',
					'label'    => __( 'Let shoppers pick a page size', 'wp-easycart' ),
					'desc'     => __( 'Shows the per-page menu on product lists. The choices offered live under Settings › Per page options.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_enable_product_paging',
					'keywords' => array( 'per page', 'page size', 'pagination', 'menu' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Products Per Page', 'note' => 'was mislabelled as a number; the storefront and Per page options treat it as on/off' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'add-to-cart' => array(
			'title'  => __( 'Add to cart flow', 'wp-easycart' ),
			'hint'   => __( 'What happens the moment a shopper adds a product', 'wp-easycart' ),
			'fields' => array(
				'ec_option_addtocart_return_to_product' => array(
					'type'     => 'toggle',
					'label'    => __( 'Stay on the product page after adding to cart', 'wp-easycart' ),
					'desc'     => __( 'Off sends the shopper straight to the cart.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'redirect', 'cart', 'stay', 'continue shopping' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Add to Cart: Stay on Page' ),
				),
				'ec_option_redirect_add_to_cart' => array(
					'type'     => 'toggle',
					'label'    => __( 'Go straight to checkout when adding from a list', 'wp-easycart' ),
					'desc'     => __( 'Adding a product from a grid or list sends the shopper to checkout immediately.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'redirect', 'checkout', 'list', 'buy now' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product List: Redirect Add to Cart' ),
				),
				'ec_option_product_no_checkout_button' => array(
					'type'     => 'toggle',
					'label'    => __( 'Keep the Add to cart button after adding', 'wp-easycart' ),
					'desc'     => __( 'Off swaps a list product’s button to Checkout once it is in the cart. The view-cart bar appears either way.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'checkout button', 'add to cart', 'list', 'swap' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product List: Keep Add to Cart Button' ),
				),
				'ec_option_product_add_to_cart_enable_quantity' => array(
					'type'     => 'toggle',
					'label'    => __( 'Quantity box beside list Add to cart buttons', 'wp-easycart' ),
					'desc'     => __( 'Lets shoppers choose how many from the grid. Only for products with no options to pick.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'quantity', 'list', 'grid', 'add to cart' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product List: Add quantity box when add to cart button shows' ),
				),
				'ec_option_subscription_one_only' => array(
					'type'     => 'toggle',
					'label'    => __( 'One subscription per purchase', 'wp-easycart' ),
					'desc'     => __( 'Removes the quantity box from subscription products so each purchase starts a single subscription.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'subscription', 'quantity', 'recurring' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Display', 'label' => 'Subscriptions: Hide Quantity' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'store-sidebar' => array(
			'title'  => __( 'Store sidebar', 'wp-easycart' ),
			'hint'   => __( 'The filter column beside the product list', 'wp-easycart' ),
			'fields' => array(
				'ec_option_show_store_sidebar' => array(
					'type'     => 'toggle',
					'label'    => __( 'Store sidebar', 'wp-easycart' ),
					'desc'     => __( 'Adds a sidebar of search, category, manufacturer and option filters to the store page.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'sidebar', 'filters', 'facets', 'store page' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Sidebar' ),
				),
				'ec_option_store_sidebar_position' => array(
					'type'     => 'select',
					'label'    => __( 'Sidebar position', 'wp-easycart' ),
					'desc'     => __( 'Fixed beside the list, or hidden behind a button that slides it in.', 'wp-easycart' ),
					'default'  => 'left',
					'options'  => array(
						'left'        => __( 'Left column', 'wp-easycart' ),
						'right'       => __( 'Right column', 'wp-easycart' ),
						'slide-left'  => __( 'Slide in from the left', 'wp-easycart' ),
						'slide-right' => __( 'Slide in from the right', 'wp-easycart' ),
					),
					'parent'   => 'ec_option_show_store_sidebar',
					'keywords' => array( 'sidebar', 'left', 'right', 'slideout' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Sidebar Position' ),
				),
				'ec_option_store_sidebar_filter_clear' => array(
					'type'     => 'toggle',
					'label'    => __( '“Clear filters” link', 'wp-easycart' ),
					'desc'     => __( 'Adds a link that resets every active filter at once.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_store_sidebar',
					'advanced' => true,
					'keywords' => array( 'clear', 'reset', 'filters' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Enable Clear Filter Feature' ),
				),
				'ec_option_store_sidebar_include_search' => array(
					'type'     => 'toggle',
					'label'    => __( 'Search box', 'wp-easycart' ),
					'desc'     => __( 'A product search field at the top of the sidebar.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_store_sidebar',
					'keywords' => array( 'search', 'sidebar', 'box' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Enable Sidebar Search' ),
				),
				'ec_option_store_sidebar_include_categories' => array(
					'type'     => 'toggle',
					'label'    => __( 'Category links', 'wp-easycart' ),
					'desc'     => __( 'A simple list of category links you choose below.', 'wp-easycart' ),
					'default'  => 0,
					'parent'   => 'ec_option_show_store_sidebar',
					'keywords' => array( 'categories', 'links', 'sidebar' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Enable Sidebar Category Links' ),
				),
				'ec_option_store_sidebar_categories' => array(
					'type'        => 'multiselect',
					'separator'   => ',',
					'label'       => __( 'Categories to link', 'wp-easycart' ),
					'desc'        => __( 'Every category chosen here appears in the sidebar list. Type to find a category.', 'wp-easycart' ),
					'placeholder' => __( 'Search categories…', 'wp-easycart' ),
					'default'     => '',
					'parent'      => 'ec_option_store_sidebar_include_categories',
					'keywords'    => array( 'categories', 'sidebar', 'links', 'pick' ),
					'legacy'      => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Sidebar Categories' ),
				) + wp_easycart_settings_products_picker( 'categories' ),
				'ec_option_sidebar_include_categories_first' => array(
					'type'     => 'toggle',
					'label'    => __( 'Category links above option filters', 'wp-easycart' ),
					'desc'     => __( 'Off lists the option filters first.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_store_sidebar_include_categories',
					'advanced' => true,
					'keywords' => array( 'order', 'categories', 'options', 'sidebar' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Show Category Filters First' ),
				),
				'ec_option_sidebar_include_category_filters' => array(
					'type'     => 'toggle',
					'label'    => __( 'Category filter groups', 'wp-easycart' ),
					'desc'     => __( 'Builds tick-box filter groups from one top-level category: its sub-categories become the groups and their children the choices.', 'wp-easycart' ),
					'default'  => 0,
					'parent'   => 'ec_option_show_store_sidebar',
					'keywords' => array( 'category filters', 'groups', 'facets', 'sidebar' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Complex Category Filters' ),
				),
				'ec_option_sidebar_category_filter_id' => array(
					'type'        => 'select',
					'label'       => __( 'Top-level category for the filter groups', 'wp-easycart' ),
					'desc'        => __( 'Its sub-categories become groups; their sub-categories become the selectable filters. Type to find the category; clear it for none.', 'wp-easycart' ),
					'placeholder' => __( 'Search categories…', 'wp-easycart' ),
					'default'     => '0',
					'parent'      => 'ec_option_sidebar_include_category_filters',
					'keywords'    => array( 'category', 'top level', 'filter groups' ),
					'legacy'      => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Category Top Level', 'note' => 'was a dropdown of every category; now a search picker, and "None selected" is the empty picker' ),
				) + wp_easycart_settings_products_picker( 'categories' ),
				'ec_option_sidebar_category_filter_method' => array(
					'type'     => 'pills',
					'label'    => __( 'How selections combine across groups', 'wp-easycart' ),
					'desc'     => __( '“Match all” narrows results: a product must match a choice in every group used. “Match any” widens them.', 'wp-easycart' ),
					'default'  => 'AND',
					'options'  => array(
						'AND' => __( 'Match all groups', 'wp-easycart' ),
						'OR'  => __( 'Match any group', 'wp-easycart' ),
					),
					'parent'   => 'ec_option_sidebar_include_category_filters',
					'advanced' => true,
					'keywords' => array( 'and', 'or', 'combine', 'filter logic' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Filter Method' ),
				),
				'ec_option_sidebar_category_filter_open' => array(
					'type'     => 'pills',
					'label'    => __( 'Filter groups start', 'wp-easycart' ),
					'desc'     => __( 'Whether the groups load expanded or collapsed.', 'wp-easycart' ),
					'default'  => '1',
					'options'  => array(
						'0' => __( 'All collapsed', 'wp-easycart' ),
						'1' => __( 'All expanded', 'wp-easycart' ),
						'2' => __( 'First expanded', 'wp-easycart' ),
					),
					'parent'   => 'ec_option_sidebar_include_category_filters',
					'advanced' => true,
					'keywords' => array( 'expanded', 'collapsed', 'open', 'accordion' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Filters Open by Default' ),
				),
				'ec_option_store_sidebar_include_manufacturers' => array(
					'type'     => 'toggle',
					'label'    => __( 'Manufacturer links', 'wp-easycart' ),
					'desc'     => __( 'A simple list of manufacturer links you choose below.', 'wp-easycart' ),
					'default'  => 0,
					'parent'   => 'ec_option_show_store_sidebar',
					'keywords' => array( 'manufacturers', 'brands', 'links', 'sidebar' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Enable Sidebar Manufacturer Links' ),
				),
				'ec_option_store_sidebar_manufacturers' => array(
					'type'        => 'multiselect',
					'separator'   => ',',
					'label'       => __( 'Manufacturers to link', 'wp-easycart' ),
					'desc'        => __( 'Every manufacturer chosen here appears in the sidebar list. Type to find a manufacturer.', 'wp-easycart' ),
					'placeholder' => __( 'Search manufacturers…', 'wp-easycart' ),
					'default'     => '',
					'parent'      => 'ec_option_store_sidebar_include_manufacturers',
					'keywords'    => array( 'manufacturers', 'brands', 'sidebar', 'pick' ),
					'legacy'      => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Sidebar Manufacturers' ),
				) + wp_easycart_settings_products_picker( 'manufacturers' ),
				'ec_option_store_sidebar_include_pricepoints' => array(
					'type'     => 'toggle',
					'label'    => __( 'Price filter', 'wp-easycart' ),
					'desc'     => __( 'Lists your price ranges ( Settings › Price points ) with product counts, so shoppers can narrow the store by price.', 'wp-easycart' ),
					'default'  => 0,
					'parent'   => 'ec_option_show_store_sidebar',
					'keywords' => array( 'price', 'price points', 'price range', 'filter', 'sidebar' ),
					'legacy'   => array(),
				),
				'ec_option_sidebar_include_option_filters' => array(
					'type'     => 'toggle',
					'label'    => __( 'Option filters', 'wp-easycart' ),
					'desc'     => __( 'Filter by option values such as size or color, using the option sets you choose below.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_show_store_sidebar',
					'keywords' => array( 'options', 'size', 'color', 'filters' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Show Option Filters' ),
				),
				'ec_option_store_sidebar_option_filters' => array(
					'type'        => 'multiselect',
					'separator'   => ',',
					'label'       => __( 'Option sets to filter by', 'wp-easycart' ),
					'desc'        => __( 'Every option set chosen here gets its own filter block. Type to find an option set.', 'wp-easycart' ),
					'placeholder' => __( 'Search option sets…', 'wp-easycart' ),
					'default'     => '',
					'parent'      => 'ec_option_sidebar_include_option_filters',
					'keywords'    => array( 'option sets', 'filters', 'sidebar', 'pick' ),
					'legacy'      => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Sidebar Options' ),
				) + wp_easycart_settings_products_picker( 'options' ),
				'ec_option_store_sidebar_include_location' => array(
					'type'     => 'toggle',
					'label'    => __( 'Pickup location selector', 'wp-easycart' ),
					'desc'     => __( 'Lets shoppers filter the store to what a chosen pickup location stocks. Needs pickup locations turned on under Shipping.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'parent'   => 'ec_option_show_store_sidebar',
					'advanced' => true,
					'keywords' => array( 'pickup', 'locations', 'sidebar', 'multiple locations' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Store Default Settings', 'label' => 'Store Defaults: Enable Sidebar Locations' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'pricing' => array(
			'title'  => __( 'Pricing display', 'wp-easycart' ),
			'hint'   => __( 'How prices, discounts and tiers are shown', 'wp-easycart' ),
			'fields' => array(
				'ec_option_show_promotion_discount_total' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show promotion savings on each line', 'wp-easycart' ),
					'desc'     => __( 'Prints the amount a promotion took off, on the product and on cart and order lines, whenever one applies.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'promotion', 'discount', 'savings', 'line item' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Price Display Options', 'label' => 'Discounts: Show Promotion Line Discount' ),
				),
				'ec_option_show_coupon_discount_total' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show coupon savings on each line', 'wp-easycart' ),
					'desc'     => __( 'Prints the amount a coupon took off, on the product and on cart and order lines, whenever one applies.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'coupon', 'discount', 'savings', 'line item' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Price Display Options', 'label' => 'Discounts: Show Coupon Line Discount' ),
				),
				'ec_option_hide_price_seasonal' => array(
					'type'     => 'toggle',
					'label'    => __( 'Hide prices on seasonal (catalog-only) products', 'wp-easycart' ),
					'desc'     => __( 'Products marked seasonal cannot be bought; this also removes their price.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'seasonal', 'catalog', 'hide price', 'no price' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Price Display Options', 'label' => 'Seasonal Products: Hide Price' ),
				),
				'ec_option_hide_price_inquiry' => array(
					'type'     => 'toggle',
					'label'    => __( 'Hide prices on inquiry products', 'wp-easycart' ),
					'desc'     => __( 'Products that show an inquiry form instead of Add to cart also drop their price.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'inquiry', 'quote', 'hide price', 'contact' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Price Display Options', 'label' => 'Inquiry Products: Hide Price' ),
				),
				'ec_option_tiered_price_format' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show volume pricing as “as low as”', 'wp-easycart' ),
					'desc'     => __( 'Products with price tiers show their lowest tier price in lists, prefixed “as low as”.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'volume', 'tiers', 'as low as', 'bulk' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Price Display Options', 'label' => 'Volume Pricing: As low as Formatting' ),
				),
				'ec_option_tiered_price_by_option' => array(
					'type'     => 'toggle',
					'label'    => __( 'Count each option combination separately for volume pricing', 'wp-easycart' ),
					'desc'     => __( 'Small shirts and large shirts reach a price tier on their own. Off adds every variant of the product together.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'volume', 'tiers', 'options', 'variants' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Price Display Options', 'label' => 'Volume Pricing: Applies Individually' ),
				),
				'ec_option_show_multiple_vat_pricing' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show prices both with and without VAT', 'wp-easycart' ),
					'desc'     => __( 'Needs VAT enabled with prices entered inclusive of VAT; otherwise both figures come out the same.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'vat', 'tax', 'inclusive', 'exclusive' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Price Display Options', 'label' => 'VAT Pricing: Show Included and Excluded Pricing' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'inventory' => array(
			'title'  => __( 'Inventory', 'wp-easycart' ),
			'hint'   => __( 'Stock counts, reservations and out-of-stock products', 'wp-easycart' ),
			'fields' => array(
				'ec_option_show_stock_quantity' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show remaining stock on products', 'wp-easycart' ),
					'desc'     => __( 'Prints “N left in stock” on product pages. Turning it off hides stock levels store-wide and overrides each product’s own setting; keep it on.', 'wp-easycart' ),
					'default'  => 1,
					'validate' => 'wp_easycart_settings_products_stock_warning',
					'keywords' => array( 'stock', 'quantity', 'left in stock', 'tracking' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Details Display', 'label' => 'Stock Quantity (CAUTION ON IS RECOMMENDED)' ),
				),
				'ec_option_hide_out_of_stock' => array(
					'type'     => 'toggle',
					'label'    => __( 'Hide out-of-stock products from lists', 'wp-easycart' ),
					'desc'     => __( 'Off keeps them listed with an out-of-stock notice.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'out of stock', 'hide', 'sold out', 'list' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product List Display', 'label' => 'Product List: Hide out of Stock' ),
				),
				'ec_option_stock_removed_in_cart' => array(
					'type'     => 'toggle',
					'label'    => __( 'Reserve stock while an item sits in a cart', 'wp-easycart' ),
					'desc'     => __( 'Stock drops the moment a shopper adds an item, not only at purchase, and returns when the reservation expires.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'reserve', 'hold', 'temporary', 'cart stock' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Inventory Options', 'label' => 'Remove Stock: Add to Cart' ),
				),
				'ec_option_tempcart_stock_hours' => array(
					'type'     => 'number',
					'label'    => __( 'Reservation length', 'wp-easycart' ),
					'desc'     => __( 'How long an item stays reserved after it is added to a cart, in the unit below.', 'wp-easycart' ),
					'default'  => 1,
					'min'      => 1,
					'step'     => 1,
					'parent'   => 'ec_option_stock_removed_in_cart',
					'keywords' => array( 'reserve', 'duration', 'timeout', 'cart stock' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Inventory Options', 'label' => 'Cart Stock: Length' ),
				),
				'ec_option_tempcart_stock_timeframe' => array(
					'type'     => 'pills',
					'label'    => __( 'Reservation unit', 'wp-easycart' ),
					'desc'     => __( 'The unit the reservation length is measured in.', 'wp-easycart' ),
					'default'  => 'HOUR',
					'options'  => array(
						'SECOND' => __( 'Seconds', 'wp-easycart' ),
						'MINUTE' => __( 'Minutes', 'wp-easycart' ),
						'HOUR'   => __( 'Hours', 'wp-easycart' ),
					),
					'parent'   => 'ec_option_stock_removed_in_cart',
					'keywords' => array( 'reserve', 'unit', 'hours', 'minutes' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Inventory Options', 'label' => 'Cart Stock: Unit' ),
				),
				'ec_option_enable_inventory_notification' => array(
					'type'     => 'toggle',
					'label'    => __( 'Let shoppers ask for a back-in-stock email', 'wp-easycart' ),
					'desc'     => __( 'Out-of-stock products get a “notify me” box. Subscribers are managed from each product’s stock tab.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'keywords' => array( 'back in stock', 'notify', 'waitlist', 'subscribe' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Product Inventory Options', 'label' => 'Stock Notifications: Customers' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'reviews' => array(
			'title'  => __( 'Reviews', 'wp-easycart' ),
			'hint'   => __( 'Customer reviews on product pages', 'wp-easycart' ),
			'fields' => array(
				'ec_option_customer_review_require_login' => array(
					'type'     => 'toggle',
					'label'    => __( 'Only signed-in shoppers can review', 'wp-easycart' ),
					'desc'     => __( 'Guests see the reviews but must log in before writing one.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'reviews', 'login', 'guests', 'spam' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Customer Review Display', 'label' => 'Customer Reviews: Require Login' ),
				),
				'ec_option_customer_review_show_user_name' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the reviewer’s name', 'wp-easycart' ),
					'desc'     => __( 'Prints the name given with each review. Off shows reviews anonymously.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'reviews', 'name', 'anonymous', 'privacy' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Customer Review Display', 'label' => 'Customer Reviews: Show Name' ),
				),
				'ec_option_customer_review_notification' => array(
					'type'     => 'toggle',
					'label'    => __( 'Email me when a review is submitted', 'wp-easycart' ),
					'desc'     => __( 'Sends a notice to the store’s admin email so you can approve it.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'reviews', 'email', 'notify', 'moderation' ),
					'legacy'   => array( 'page' => 'products', 'section' => 'Customer Review Display', 'label' => 'Customer Reviews: Notify Admin' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */

		// 6.0.0: abuse protection for the inquiry form on inquiry-mode products.
		// Implemented by wp_easycart_inquiry_guard ( inc/classes/core/class-wp-easycart-inquiry-guard.php );
		// every check is invisible to a real shopper and works with or without reCAPTCHA.
		'inquiry-protection' => array(
			'title'  => __( 'Inquiry form protection', 'wp-easycart' ),
			'hint'   => __( 'Stops spam reaching the inquiry form on inquiry-mode products, without showing shoppers a challenge', 'wp-easycart' ),
			'fields' => array(
				'ec_option_inquiry_honeypot' => array(
					'type'     => 'toggle',
					'label'    => __( 'Hidden trap field on the inquiry form', 'wp-easycart' ),
					'desc'     => __( 'Adds a field only automated scripts can see. Anything that fills it in is discarded. Shoppers never see or tab into it.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'spam', 'bots', 'honeypot', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_min_seconds' => array(
					'type'     => 'number',
					'label'    => __( 'Shortest time an inquiry may take to write', 'wp-easycart' ),
					'desc'     => __( 'Submissions that arrive sooner than this after the page loaded are refused, which is how most scripted spam behaves. Set to 0 to turn the timing check off.', 'wp-easycart' ),
					'default'  => 4,
					'min'      => 0,
					'max'      => 120,
					'step'     => 1,
					'unit'     => __( 'seconds', 'wp-easycart' ),
					'keywords' => array( 'spam', 'bots', 'timing', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_rate_limit' => array(
					'type'     => 'toggle',
					'label'    => __( 'Limit how many inquiries one visitor can send', 'wp-easycart' ),
					'desc'     => __( 'Counts sent inquiries for an hour at a time and refuses the rest with a short message. The defaults are well above normal shopper behavior.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'rate limit', 'throttle', 'flood', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_max_per_ip' => array(
					'type'     => 'number',
					'label'    => __( 'Inquiries an hour from one internet address', 'wp-easycart' ),
					'desc'     => __( 'Counted against a one-way hash of the address, never the address itself. Set to 0 for no limit.', 'wp-easycart' ),
					'default'  => 10,
					'min'      => 0,
					'max'      => 500,
					'step'     => 1,
					'parent'   => 'ec_option_inquiry_rate_limit',
					'keywords' => array( 'rate limit', 'ip', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_max_per_email' => array(
					'type'     => 'number',
					'label'    => __( 'Inquiries an hour from one email address', 'wp-easycart' ),
					'desc'     => __( 'Stops the same address being used over and over from different connections. Set to 0 for no limit.', 'wp-easycart' ),
					'default'  => 5,
					'min'      => 0,
					'max'      => 500,
					'step'     => 1,
					'parent'   => 'ec_option_inquiry_rate_limit',
					'keywords' => array( 'rate limit', 'email', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_max_per_product' => array(
					'type'     => 'number',
					'label'    => __( 'Inquiries an hour about the same product', 'wp-easycart' ),
					'desc'     => __( 'A visitor who has already asked about a product this many times is asked to wait. Set to 0 for no limit.', 'wp-easycart' ),
					'default'  => 3,
					'min'      => 0,
					'max'      => 100,
					'step'     => 1,
					'parent'   => 'ec_option_inquiry_rate_limit',
					'keywords' => array( 'rate limit', 'product', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_require_login' => array(
					'type'     => 'toggle',
					'label'    => __( 'Only signed-in shoppers can send an inquiry', 'wp-easycart' ),
					'desc'     => __( 'Guests still see the form but are asked to sign in first. Off by default so existing stores keep taking inquiries from anyone.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'login', 'account', 'guests', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_content_checks' => array(
					'type'     => 'toggle',
					'label'    => __( 'Check what the message contains', 'wp-easycart' ),
					'desc'     => __( 'Applies the length, link and blocked-word rules below to the name and message before anything is emailed.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'spam', 'content', 'links', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_max_length' => array(
					'type'     => 'number',
					'label'    => __( 'Longest inquiry message', 'wp-easycart' ),
					'desc'     => __( 'Longer messages are refused with a note asking the shopper to shorten it. Set to 0 for no limit.', 'wp-easycart' ),
					'default'  => 4000,
					'min'      => 0,
					'max'      => 50000,
					'step'     => 1,
					'unit'     => __( 'characters', 'wp-easycart' ),
					'parent'   => 'ec_option_inquiry_content_checks',
					'keywords' => array( 'length', 'characters', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_max_links' => array(
					'type'     => 'number',
					'label'    => __( 'Most web links a message may contain', 'wp-easycart' ),
					'desc'     => __( 'Link-stuffed messages are the commonest form of form spam. 0 refuses any message with a link in it; raise it if your shoppers legitimately send links.', 'wp-easycart' ),
					'default'  => 3,
					'min'      => 0,
					'max'      => 50,
					'step'     => 1,
					'unit'     => __( 'links', 'wp-easycart' ),
					'parent'   => 'ec_option_inquiry_content_checks',
					'keywords' => array( 'links', 'urls', 'spam', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_blocklist' => array(
					'type'        => 'textarea',
					'label'       => __( 'Blocked words and domains', 'wp-easycart' ),
					'desc'        => __( 'One word, phrase or domain per line. An inquiry whose name, email or message contains any of them is refused. Empty by default.', 'wp-easycart' ),
					'default'     => '',
					'rows'        => 4,
					'placeholder' => "casino\nseo-services\nexample-spam.ru",
					'parent'      => 'ec_option_inquiry_content_checks',
					'keywords'    => array( 'blocklist', 'blacklist', 'words', 'domains', 'inquiry' ),
					'legacy'      => array( 'note' => 'new in 6.0.0' ),
				),
				'ec_option_inquiry_log_blocked' => array(
					'type'     => 'toggle',
					'label'    => __( 'Log refused inquiries', 'wp-easycart' ),
					'desc'     => __( 'Records the reason, the product, a masked email and a one-way reference for the visitor under Settings › Log entries, so you can tell spam from a shopper who hit a limit. No addresses are stored.', 'wp-easycart' ),
					'default'  => 1,
					'advanced' => true,
					'keywords' => array( 'log', 'blocked', 'spam', 'inquiry' ),
					'legacy'   => array( 'note' => 'new in 6.0.0' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'search' => array(
			'title'  => __( 'Search', 'wp-easycart' ),
			'hint'   => __( 'What the storefront search widget and store search match against', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_live_search' => array(
					'type'     => 'toggle',
					'label'    => __( 'Live results while typing', 'wp-easycart' ),
					'desc'     => __( 'The search widget suggests matching products as the shopper types.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'live search', 'autocomplete', 'suggest', 'widget' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Search Options', 'label' => 'Use Live Search' ),
				),
				'ec_option_search_title' => array(
					'type'     => 'toggle',
					'label'    => __( 'Match product titles', 'wp-easycart' ),
					'desc'     => __( 'Applies to the search widget and the store’s own search box.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'search', 'title', 'name' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Search Options', 'label' => 'Search Includes Title', 'note' => 'no longer hidden when live search is off: it applies to store search too' ),
				),
				'ec_option_search_model_number' => array(
					'type'     => 'toggle',
					'label'    => __( 'Match model numbers', 'wp-easycart' ),
					'desc'     => __( 'Shoppers can find a product by typing its model number ( SKU ).', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'search', 'sku', 'model number' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Search Options', 'label' => 'Search Includes Model Number' ),
				),
				'ec_option_search_manufacturer' => array(
					'type'     => 'toggle',
					'label'    => __( 'Match manufacturer names', 'wp-easycart' ),
					'desc'     => __( 'Typing a brand returns everything that brand makes.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'search', 'manufacturer', 'brand' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Search Options', 'label' => 'Search Includes Manufacturer' ),
				),
				'ec_option_search_description' => array(
					'type'     => 'toggle',
					'label'    => __( 'Match full descriptions', 'wp-easycart' ),
					'desc'     => __( 'Broader results; slower on very large catalogs.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'search', 'description', 'content' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Search Options', 'label' => 'Search Includes Description' ),
				),
				'ec_option_search_short_description' => array(
					'type'     => 'toggle',
					'label'    => __( 'Match short descriptions', 'wp-easycart' ),
					'desc'     => __( 'Also searches each product’s short description.', 'wp-easycart' ),
					'default'  => 1,
					'advanced' => true,
					'keywords' => array( 'search', 'short description', 'excerpt' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Search Options', 'label' => 'Search Includes Short Description' ),
				),
				'ec_option_search_menu' => array(
					'type'     => 'toggle',
					'label'    => __( 'Match category names', 'wp-easycart' ),
					'desc'     => __( 'Typing a category name returns the products in it.', 'wp-easycart' ),
					'default'  => 1,
					'advanced' => true,
					'keywords' => array( 'search', 'category', 'menu', 'navigation' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Search Options', 'label' => 'Search Includes Menu Items' ),
				),
				'ec_option_search_by_or' => array(
					'type'     => 'toggle',
					'label'    => __( 'Match any word instead of every word', 'wp-easycart' ),
					'desc'     => __( 'Returns more results: a product matches when any word of the phrase is found, not only when all of them are.', 'wp-easycart' ),
					'default'  => 1,
					'advanced' => true,
					'keywords' => array( 'search', 'or', 'any word', 'broad' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Search Options', 'label' => 'Search Expands Terms' ),
				),
			),
		),

		/* ------------------------------------------------------------------ */
		'import-export' => array(
			'title'  => __( 'Import & export', 'wp-easycart' ),
			'hint'   => __( 'Limits for the product CSV export', 'wp-easycart' ),
			'fields' => array(
				'ec_option_product_export_max' => array(
					'type'     => 'number',
					'label'    => __( 'Products per export file', 'wp-easycart' ),
					'desc'     => __( 'Large catalogs export as several CSV files of this size. Lower it if exports time out on your server.', 'wp-easycart' ),
					'default'  => 500,
					'min'      => 1,
					'step'     => 1,
					'unit'     => __( 'products', 'wp-easycart' ),
					'advanced' => true,
					'keywords' => array( 'export', 'csv', 'batch', 'block size' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Product Export: Max Block Size' ),
				),
			),
		),
	),
);
