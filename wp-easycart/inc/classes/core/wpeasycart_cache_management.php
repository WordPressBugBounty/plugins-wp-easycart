<?php

class wpeasycart_cache_management{
	
	public function __construct( ){
		// 6.0.4: storefront changes forget only what they changed ( wp_easycart_cache ); a cart update emptied the whole object
		// cache, every page's and every plugin's, on every add to cart for a store with Redis or Memcached.
		add_action( 'wpeasycart_cart_updated', array( $this, 'clear_cart_cache' ), 10, 0 );
		add_action( 'wpeasycart_amf_complete', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_page_options_updated', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_user_updated', array( $this, 'clear_user_cache' ), 10, 0 );
		add_action( 'wpeasycart_order_success', array( $this, 'clear_user_cache' ), 10, 0 );
		add_action( 'wpeasycart_review_added', array( $this, 'clear_review_cache' ), 10, 1 );
		add_action( 'wpeasycart_product_deactivated', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_product_activated', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wp_easycart_option_to_product_created', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_product_deleted', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_product_added', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_product_updated', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wp_easycart_option_to_product_deleted', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_admin_product_details_general_saved', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_manufacturer_added', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_manufacturer_updated', array( $this, 'clear_cache' ), 10, 0 );
		add_action( 'wpeasycart_manufacturer_deleted', array( $this, 'clear_cache' ), 10, 0 );
		// 6.0.4: nothing listened to the category hooks, so with a persistent object cache the store page, the category widgets and
		// each product's categories kept a category switched off, featured, renamed or moved until the cache was emptied.
		add_action( 'wpeasycart_category_added', array( $this, 'clear_category_cache' ), 10, 0 );
		add_action( 'wpeasycart_category_updated', array( $this, 'clear_category_cache' ), 10, 0 );
		add_action( 'wpeasycart_category_deleted', array( $this, 'clear_category_cache' ), 10, 0 );
		add_action( 'wpeasycart_category_activated', array( $this, 'clear_category_cache' ), 10, 0 );
		add_action( 'wpeasycart_category_deactivated', array( $this, 'clear_category_cache' ), 10, 0 );
		add_action( 'wpeasycart_product_to_category_added', array( $this, 'clear_category_cache' ), 10, 0 );
		add_action( 'wpeasycart_product_to_category_deleted', array( $this, 'clear_category_cache' ), 10, 0 );
		add_action( 'wpeasycart_admin_demo_data_installed', array( $this, 'clear_category_cache' ), 10, 0 );
		add_action( 'wpeasycart_taxrate_added', array( $this, 'clear_tax_cache' ), 10, 0 );
		add_action( 'wpeasycart_taxrate_updated', array( $this, 'clear_tax_cache' ), 10, 0 );
		add_action( 'wpeasycart_taxrate_deleted', array( $this, 'clear_tax_cache' ), 10, 0 );
		add_action( 'wpeasycart_order_updated', array( $this, 'clear_order_cache' ), 10, 1 );
		add_action( 'wpeasycart_order_deleted', array( $this, 'clear_cache' ), 10, 0 );
		// 6.0.4: orders change on the storefront too ( payments, gateway notifications, pay links, refunds ): their customer's views
		// and the account's stored totals are forgotten as they change. Product lookups carry stock: every stock move ( checkout, a pay
		// link, a cancelled order, a refund's restock, WP EasyCart PRO's too ) announces itself, and a new order may take a bundle's
		// parts without announcing them.
		add_action( 'wpeasycart_order_inserted', array( $this, 'clear_new_order_cache' ), 10, 1 );
		add_action( 'wpeasycart_subscription_first_order_inserted', array( $this, 'clear_new_order_cache' ), 10, 1 );
		add_action( 'wpeasycart_order_status_update', array( $this, 'clear_order_view_cache' ), 10, 1 );
		add_action( 'wpeasycart_order_paid', array( $this, 'clear_order_view_cache' ), 10, 1 );
		add_action( 'wpeasycart_subscription_paid', array( $this, 'clear_order_view_cache' ), 10, 1 );
		add_action( 'wpeasycart_full_order_refund', array( $this, 'clear_order_view_cache' ), 10, 1 );
		add_action( 'wpeasycart_partial_order_refund', array( $this, 'clear_order_view_cache' ), 10, 1 );
		add_action( 'wp_easycart_admin_order_user_changed', array( $this, 'clear_order_user_cache' ), 10, 3 );
		add_action( 'wpeasycart_subscription_cancelled', array( $this, 'clear_account_cache' ), 10, 1 );
		add_action( 'wp_easycart_updated_product_stock', array( $this, 'clear_stock_cache' ), 10, 0 );
		add_action( 'wp_easycart_updated_optionitem_quantity_value', array( $this, 'clear_stock_cache' ), 10, 0 );
	}

	public function clear_tempcart_cache( ){
		wp_cache_delete( 'wpeasycart-tempcart-product-quantities-'.$GLOBALS['ec_cart_data']->ec_cart_id, 'wpeasycart-tempcart' );
		wp_cache_delete( 'wpeasycart-tempcart-'.$GLOBALS['ec_cart_data']->ec_cart_id, 'wpeasycart-tempcart' );
		wp_cache_delete( 'wpeasycart-advanced-cart-options-'.$GLOBALS['ec_cart_data']->ec_cart_id, 'wpeasycart-tempcart' );
		wp_cache_delete( 'wpeasycart-cart-data-'.$GLOBALS['ec_cart_data']->ec_cart_id, 'wpeasycart-tempcart' );
		wp_cache_delete( 'wpeasycart-user-'.$GLOBALS['ec_cart_data']->cart_data->user_id, 'wpeasycart-user' );
	}

	public function clear_user_cache( ){
		wp_easycart_cache::forget_shopper();
	}

	public function clear_tax_cache() {
		wp_cache_delete( 'wpeasycart-tax-rates' );
		/* 6.0.4: whether prices include or add VAT ( inc/ec_config.php ) follows the tax rates. */
		wp_cache_delete( 'wpeasycart-config-vat-included' );
		wp_cache_delete( 'wpeasycart-config-vat-added' );
	}

	/**
	 * A category changed ( the category hooks, products added to or taken out of one, the demo store ): every cached category lookup
	 * and the product lookups ( 6.0.4 ).
	 */
	public function clear_category_cache() {
		wp_easycart_cache::categories_changed();
	}

	/**
	 * Action wpeasycart_cart_updated: the cart or the checkout session changed. Nothing shared is cached per cart ( 6.0.4 ).
	 */
	public function clear_cart_cache() {
		wp_easycart_cache::cart_changed();
	}

	/**
	 * Action wpeasycart_order_updated ( the order screen, a pay link, a shipment, a claimed guest order ): its customer's views of it,
	 * the account ( stored totals ) and, as an edit on the order screen may move stock without announcing it, the product lookups
	 * ( 6.0.4; the whole object cache was emptied before ).
	 *
	 * @param int $order_id Order.
	 */
	public function clear_order_cache( $order_id = 0 ) {
		wp_easycart_cache::forget_order( $order_id );
		wp_easycart_cache::stock_changed();
	}

	/**
	 * An order's status, payment or refunds changed: its customer's views of it and the account ( stored totals ).
	 *
	 * @param int $order_id Order.
	 */
	public function clear_order_view_cache( $order_id = 0 ) {
		wp_easycart_cache::forget_order( $order_id );
	}

	/**
	 * Stock moved ( wp_easycart_updated_product_stock, wp_easycart_updated_optionitem_quantity_value ): the product lookups.
	 */
	public function clear_stock_cache() {
		wp_easycart_cache::stock_changed();
	}

	/**
	 * A new order ( checkout, a subscription's first order ): as clear_order_cache(), and the shopper's own account.
	 *
	 * @param int $order_id Order.
	 */
	public function clear_new_order_cache( $order_id = 0 ) {
		wp_easycart_cache::forget_shopper();
		$this->clear_order_cache( $order_id );
	}

	/**
	 * An order moved to another account ( the order screen ): both accounts.
	 *
	 * @param int $order_id         Order.
	 * @param int $user_id          Account it belongs to now.
	 * @param int $previous_user_id Account it belonged to.
	 */
	public function clear_order_user_cache( $order_id = 0, $user_id = 0, $previous_user_id = 0 ) {
		wp_easycart_cache::forget_user( $previous_user_id );
		wp_easycart_cache::forget_user( $user_id );
		wp_easycart_cache::forget_order( $order_id );
	}

	/**
	 * Action wpeasycart_subscription_cancelled( $user_id, $subscription_id ): the account's subscriptions.
	 *
	 * @param int $user_id Account.
	 */
	public function clear_account_cache( $user_id = 0 ) {
		wp_easycart_cache::forget_user( $user_id );
	}

	/**
	 * A storefront review was added: the review lists, the product's rating and the product lookups ( 6.0.4 ).
	 *
	 * @param int $review_id Review.
	 */
	public function clear_review_cache( $review_id = 0 ) {
		wp_easycart_cache::review_added( $review_id );
	}

	public function clear_cache( ){
		wp_cache_flush( );
	}
}

new wpeasycart_cache_management( );
?>
