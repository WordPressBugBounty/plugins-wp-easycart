<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wp_easycart_admin_details_products extends wp_easycart_admin_details {

	public $product;
	public $price_tiers;
	public $b2b_prices;
	public $option_item_images;
	public $advanced_options;
	public $categories;
	public $item;

	public function __construct() {
		parent::__construct();
		add_action( 'wp_easycart_admin_product_details_basic_fields', array( $this, 'basic_fields' ) );
		add_action( 'wp_easycart_admin_product_details_menus_fields', array( $this, 'menus_fields' ) );
		add_action( 'wp_easycart_admin_product_details_categories_fields', array( $this, 'categories_fields' ) );
		add_action( 'wp_easycart_admin_product_details_quantity_fields', array( $this, 'quantity_fields' ) );
		add_action( 'wp_easycart_admin_product_details_packaging_fields', array( $this, 'packaging_fields' ) );
		add_action( 'wp_easycart_admin_product_details_pricing_fields', array( $this, 'pricing_fields' ) );
		add_action( 'wp_easycart_admin_product_details_advanced_pricing_fields', array( $this, 'advanced_pricing_fields' ) );
		add_action( 'wp_easycart_admin_product_details_shipping_fields', array( $this, 'shipping_fields' ) );
		add_action( 'wp_easycart_admin_product_details_short_description_fields', array( $this, 'short_description_fields' ) );
		add_action( 'wp_easycart_admin_product_details_specifications_fields', array( $this, 'specifications_fields' ) );
		add_action( 'wp_easycart_admin_product_details_order_completed_note_fields', array( $this, 'order_completed_note_fields' ) );
		add_action( 'wp_easycart_admin_product_details_order_completed_email_note_fields', array( $this, 'order_completed_email_note_fields' ) );
		add_action( 'wp_easycart_admin_product_details_order_completed_details_note_fields', array( $this, 'order_completed_details_note_fields' ) );
		add_action( 'wp_easycart_admin_product_details_featured_products_fields', array( $this, 'featured_products_fields' ) );
		add_action( 'wp_easycart_admin_product_details_general_options_fields', array( $this, 'general_options_fields' ) );
		add_action( 'wp_easycart_admin_product_details_tax_fields', array( $this, 'tax_fields' ) );
		add_action( 'wp_easycart_admin_product_details_deconetwork_fields', array( $this, 'deconetwork_fields' ) );
		add_action( 'wp_easycart_admin_product_details_subscription_fields', array( $this, 'subscription_fields' ) );
		add_action( 'wp_easycart_admin_product_details_seo_fields', array( $this, 'seo_fields' ) );
		add_action( 'wp_easycart_admin_product_details_downloads_fields', array( $this, 'downloads_fields' ) );
		add_action( 'wp_easycart_admin_product_details_tags_fields', array( $this, 'tags_fields' ) );
	}

	protected function init() {
		$this->docs_link = 'http://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?wpeasycartadmin=1&section=products';
		$this->id = '0';
		$this->page = 'wp-easycart-products';
		$this->subpage = 'products';
		$this->action = 'admin.php?page=' . $this->page . '&subpage=' . $this->subpage;

		if ( isset( $_GET['pagenum'] ) ) {
			$this->action .= '&pagenum=' . (int) $_GET['pagenum'];
		}
		$valid_orderby = array( 'title', 'stock_quantity', 'price', 'model_number', 'is_visible', 'product_id' );
		if ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $valid_orderby ) ){
			$this->action .= '&orderby=' . sanitize_key( $_GET['orderby'] );
		}
		if ( isset( $_GET['order'] ) && 'desc' == strtolower( $_GET['order'] ) ){
			$this->action .= '&order=desc';
		} else {
			$this->action .= '&order=asc';
		}
		$this->form_action = 'add-new-product';
		$this->product = $this->item = (object) array(
			"product_id" => '0',
			"model_number" => '',
			"guid" => '',
			"post_id" => '',
			"post_excerpt" => '',
			"activate_in_store" => '',
			"title" => '',
			"description" => '',
			"specifications" => '',
			"order_completed_note" => '',
			"order_completed_email_note" => '',
			"order_completed_details_note" => '',
			"price" => '',
			"list_price" => '',
			"product_cost" => '',
			"vat_rate" => '',
			"handling_price" => '',
			"handling_price_each" => '',
			"stock_quantity" => '',
			"min_purchase_quantity" => '',
			"max_purchase_quantity" => '',
			"weight" => '',
			"width" => '',
			"height" => '',
			"length" => '',
			"seo_description" => '',
			"seo_keywords" => '',
			"use_specifications" => '',
			"use_customer_reviews" => '',
			"manufacturer_id" => '',
			"download_file_name" => '',
			"image1" => '',
			"image2" => '',
			"image3" => '',
			"image4" => '',
			"image5" => '',
			"product_images" => '',
			"option_id_1" => 0,
			"option_id_2" => 0,
			"option_id_3" => 0,
			"option_id_4" => 0,
			"option_id_5" => 0,
			"use_advanced_optionset" => 0,
			"menulevel1_id_1" => 0,
			"menulevel1_id_2" => 0,
			"menulevel1_id_3" => 0,
			"menulevel2_id_1" => 0,
			"menulevel2_id_2" => 0,
			"menulevel2_id_3" => 0,
			"menulevel3_id_1" => 0,
			"menulevel3_id_2" => 0,
			"menulevel3_id_3" => 0,
			"featured_product_id_1" => 0,
			"featured_product_id_2" => 0,
			"featured_product_id_3" => 0,
			"featured_product_id_4" => 0,
			"is_giftcard" => 0,
			"is_download" => 0,
			"is_donation" => 0,
			"is_special" => 0,
			"is_taxable" => 1,
			"is_shippable" => 1,
			"is_subscription_item" => 0,
			"is_preorder" => 0,
			"added_to_db_date" => '',
			"show_on_startup" => 1,
			"use_optionitem_images" => 0,
			"use_optionitem_quantity_tracking" => 0,
			"views" => '',
			"last_viewed" => '',
			"show_stock_quantity" => '',
			"maximum_downloads_allowed" => '',
			"download_timelimit_seconds" => '',
			"list_id" => '',
			"edit_sequence" => '',
			"quickbooks_status" => '',
			"income_account_ref" => '',
			"cogs_account_ref" => '',
			"asset_account_ref" => '',
			"quickbooks_parent_name" => '',
			"quickbooks_parent_list_id" => '',
			"subscription_bill_length" => '',
			"subscription_bill_period" => '',
			"subscription_shipping_recurring" => 0,
			"enable_duration" => 0,
			"subscription_bill_duration" => '',
			"trial_period_days" => '',
			"stripe_plan_added" => '',
			"allow_multiple_subscription_purchases" => '',
			"membership_page" => '',
			"is_amazon_download" => '',
			"amazon_key" => '',
			"catalog_mode" => '',
			"catalog_mode_phrase" => '',
			"is_preorder_type" => 0,
			"is_restaurant_type" => 0,
			"inquiry_mode" => '',
			"inquiry_url" => '',
			"is_deconetwork" => '',
			"deconetwork_mode" => '',
			"deconetwork_product_id" => '',
			"deconetwork_size_id" => '',
			"deconetwork_color_id" => '',
			"deconetwork_design_id" => '',
			"short_description" => '',
			"display_type" => '',
			"image_hover_type" => '',
			"tag_type" => '',
			"tag_bg_color" => '',
			"tag_text_color" => '',
			"tag_text" => '',
			"image_effect_type" => '',
			"include_code" => '',
			"TIC" => '',
			"subscription_signup_fee" => '',
			"subscription_unique_id" => '',
			"subscription_prorate" => '',
			"subscription_plan_id" => '',
			"allow_backorders" => '',
			"backorder_fill_date" => '',
			"shipping_class_id" => '',
			"ship_to_billing" => 0,
			"shipping_restriction" => '',
			"enable_price_label" => 0,
			"replace_price_label" => 0,
			"custom_price_label" => '',
		);

		$this->price_tiers = array();
		$this->b2b_prices = array();
		$this->option_item_images = array();
		$this->advanced_options = array();
		$this->categories = array();
	}

	protected function init_data() {
		$this->form_action = 'update-product';
		$record = $this->load_record( $this->wpdb->get_row( $this->wpdb->prepare( "SELECT 
				ec_product.*,
				( ec_product.subscription_bill_duration > 0 ) as enable_duration,
				" . $this->wpdb->prefix . "posts.guid,
				" . $this->wpdb->prefix . "posts.post_excerpt
			FROM 
				ec_product 
				LEFT JOIN " . $this->wpdb->prefix . "posts ON " .$this->wpdb->prefix . "posts.ID = ec_product.post_id 
			WHERE product_id = %d", (int) ( $_GET['product_id'] ?? 0 )
		) ) );
		if ( ! $record ) {
			return;
		}
		$this->product = $this->item = $record;
		$this->id = $this->product->product_id;
		// Fix for option sets
		if ( ! $this->product->use_both_option_types ) {
			if ( $this->product->use_advanced_optionset ) {
				$this->product->option_id_1 = $this->product->option_id_2 = $this->product->option_id_3 = $this->product->option_id_4 = $this->product->option_id_5 = 0;
				$this->wpdb->query( $this->wpdb->prepare( 'UPDATE ec_product SET option_id_1 = 0, option_id_2 = 0, option_id_3 = 0, option_id_4 = 0, option_id_5 = 0, use_both_option_types = 1 WHERE product_id = %d', $this->product->product_id ) );
			} else {
				$this->wpdb->query( $this->wpdb->prepare( 'DELETE FROM ec_option_to_product WHERE product_id = %d', $this->product->product_id ) );
				$this->wpdb->query( $this->wpdb->prepare( 'UPDATE ec_product SET use_both_option_types = 1 WHERE product_id = %d', $this->product->product_id ) );
			}
		}
		// Check variants and build missing
		$variants = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE product_id = %d LIMIT 5000', $this->product->product_id ) );
		$option_items_1 = ( 0 != (int) $this->product->option_id_1 ) ? $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE option_id = %d', (int) $this->product->option_id_1 ) ) : array();
		$option_items_2 = ( 0 != (int) $this->product->option_id_2 ) ? $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE option_id = %d', (int) $this->product->option_id_2 ) ) : array();
		$option_items_3 = ( 0 != (int) $this->product->option_id_3 ) ? $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE option_id = %d', (int) $this->product->option_id_3 ) ) : array();
		$option_items_4 = ( 0 != (int) $this->product->option_id_4 ) ? $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE option_id = %d', (int) $this->product->option_id_4 ) ) : array();
		$option_items_5 = ( 0 != (int) $this->product->option_id_5 ) ? $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE option_id = %d', (int) $this->product->option_id_5 ) ) : array();

		// Find and Remove Duplicates
		$found_list = array();
		$duplicate_id_list = array();
		$variants_reverse = array_reverse( $variants );
		for ( $i = 0; $i < count( $variants_reverse ); $i++ ) {
			$key = $variants_reverse[$i]->optionitem_id_1 . $variants_reverse[$i]->optionitem_id_2 . $variants_reverse[$i]->optionitem_id_3 . $variants_reverse[$i]->optionitem_id_4 . $variants_reverse[$i]->optionitem_id_5;
			if ( in_array( $key, $found_list ) ) {
				$duplicate_id_list[] = (int) $variants_reverse[$i]->optionitemquantity_id;
			} else {
				$found_list[] = $key;
			}
		}
		if ( count( $duplicate_id_list ) > 0 ) {
			$delete_dup_query = '';
			for ( $i = 0; $i < count( $duplicate_id_list ); $i++ ) {
				if ( $i % 100 == 0 ) {
					$delete_dup_query = 'DELETE FROM ec_optionitemquantity WHERE optionitemquantity_id IN (';
				} else {
					$delete_dup_query .= ',';
				}
				$delete_dup_query .= $duplicate_id_list[ $i ];
				if ( $i % 100 == 99 ) {
					$delete_dup_query .= ')';
					$this->wpdb->query( $delete_dup_query );
					$delete_dup_query = '';
				}
			}
			if ( '' != $delete_dup_query ) {
				$this->wpdb->query( $delete_dup_query . ')' );
			}
		}

		// Add Missing Variants
		$is_enabled = ( '' == $this->product->square_id ) ? 1 : 0;
		if ( count( $variants ) <= 5000 ) { // Only validate up to 5000 items
			$list_count = 0;
			$query = 'INSERT INTO ec_optionitemquantity( product_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, is_enabled ) VALUES';
			$first = true;
			for ( $a = 0; $a<count( $option_items_1 ); $a++ ) {
				if ( count( $option_items_2 ) <= 0 ) {
					$is_found = false;
					for ( $v_i = 0; $v_i < count( $variants ); $v_i++ ) {
						if ( $variants[$v_i]->optionitem_id_1 == $option_items_1[$a]->optionitem_id && 0 == $variants[$v_i]->optionitem_id_2 && 0 == $variants[$v_i]->optionitem_id_3 && 0 == $variants[$v_i]->optionitem_id_4 && 0 == $variants[$v_i]->optionitem_id_5 ) {
							$is_found = true;
							break;
						}
					}
					if ( ! $is_found ) {
						if ( ! $first ) {
							$query .= ',';
						}
						$query .= $this->wpdb->prepare( '( %d, %d, 0, 0, 0, 0, %d )', $this->product->product_id, $option_items_1[$a]->optionitem_id, $is_enabled );
						$list_count++;
						$first = false;
						if ( $list_count >= 100 ) {
							$this->wpdb->query( $query );
							$first = true;
							$query = 'INSERT INTO ec_optionitemquantity( product_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, is_enabled ) VALUES';
							$list_count = 0;
						}
					}
				} else {
					for ( $b = 0; $b<count( $option_items_2 ); $b++ ) {
						if ( count( $option_items_3 ) <= 0 ) {
							$is_found = false;
							for ( $v_i = 0; $v_i < count( $variants ); $v_i++ ) {
								if ( $variants[$v_i]->optionitem_id_1 == $option_items_1[$a]->optionitem_id && $variants[$v_i]->optionitem_id_2 == $option_items_2[$b]->optionitem_id && 0 == $variants[$v_i]->optionitem_id_3 && 0 == $variants[$v_i]->optionitem_id_4 && 0 == $variants[$v_i]->optionitem_id_5 ) {
									$is_found = true;
									break;
								}
							}
							if ( ! $is_found ) {
								if ( ! $first ) {
									$query .= ',';
								}
								$query .= $this->wpdb->prepare( '( %d, %d, %d, 0, 0, 0, %d )', $this->product->product_id, $option_items_1[$a]->optionitem_id, $option_items_2[$b]->optionitem_id, $is_enabled );
								$list_count++;
								$first = false;
								if ( $list_count >= 100 ) {
									$this->wpdb->query( $query );
									$first = true;
									$query = 'INSERT INTO ec_optionitemquantity( product_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, is_enabled ) VALUES';
									$list_count = 0;
								}
							}
						} else {
							for ( $c = 0; $c<count( $option_items_3 ); $c++ ) {
								if ( count( $option_items_4 ) <= 0 ) {
									$is_found = false;
									for ( $v_i = 0; $v_i < count( $variants ); $v_i++ ) {
										if ( $variants[$v_i]->optionitem_id_1 == $option_items_1[$a]->optionitem_id && $variants[$v_i]->optionitem_id_2 == $option_items_2[$b]->optionitem_id && $variants[$v_i]->optionitem_id_3 == $option_items_3[$c]->optionitem_id && 0 == $variants[$v_i]->optionitem_id_4 && 0 == $variants[$v_i]->optionitem_id_5 ) {
											$is_found = true;
											break;
										}
									}
									if ( ! $is_found ) {
										if ( ! $first ) {
											$query .= ',';
										}
										$query .= $this->wpdb->prepare( '( %d, %d, %d, %d, 0, 0, %d )', $this->product->product_id, $option_items_1[$a]->optionitem_id, $option_items_2[$b]->optionitem_id, $option_items_3[$c]->optionitem_id, $is_enabled );
										$list_count++;
										$first = false;
										if ( $list_count >= 100 ) {
											$this->wpdb->query( $query );
											$first = true;
											$query = 'INSERT INTO ec_optionitemquantity( product_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, is_enabled ) VALUES';
											$list_count = 0;
										}
									}
								} else {
									for ( $d = 0; $d<count( $option_items_4 ); $d++ ) {
										if ( count( $option_items_5 ) <= 0 ) {
											$is_found = false;
											for ( $v_i = 0; $v_i < count( $variants ); $v_i++ ) {
												if ( $variants[$v_i]->optionitem_id_1 == $option_items_1[$a]->optionitem_id && $variants[$v_i]->optionitem_id_2 == $option_items_2[$b]->optionitem_id && $variants[$v_i]->optionitem_id_3 == $option_items_3[$c]->optionitem_id && $variants[$v_i]->optionitem_id_4 == $option_items_4[$d]->optionitem_id && 0 == $variants[$v_i]->optionitem_id_5 ) {
													$is_found = true;
													break;
												}
											}
											if ( ! $is_found ) {
												if ( ! $first ) {
													$query .= ',';
												}
												$query .= $this->wpdb->prepare( '( %d, %d, %d, %d, %d, 0, %d )', $this->product->product_id, $option_items_1[$a]->optionitem_id, $option_items_2[$b]->optionitem_id, $option_items_3[$c]->optionitem_id, $option_items_4[$d]->optionitem_id, $is_enabled );
												$list_count++;
												$first = false;
												if ( $list_count >= 100 ) {
													$this->wpdb->query( $query );
													$first = true;
													$query = 'INSERT INTO ec_optionitemquantity( product_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, is_enabled ) VALUES';
													$list_count = 0;
												}
											}
										} else {
											for ( $e = 0; $e<count( $option_items_5 ); $e++ ) {
												$is_found = false;
												for ( $v_i = 0; $v_i < count( $variants ); $v_i++ ) {
													if ( $variants[$v_i]->optionitem_id_1 == $option_items_1[$a]->optionitem_id && $variants[$v_i]->optionitem_id_2 == $option_items_2[$b]->optionitem_id && $variants[$v_i]->optionitem_id_3 == $option_items_3[$c]->optionitem_id && $variants[$v_i]->optionitem_id_4 == $option_items_4[$d]->optionitem_id && $variants[$v_i]->optionitem_id_5 == $option_items_5[$e]->optionitem_id ) {
														$is_found = true;
														break;
													}
												}
												if ( ! $is_found ) {
													if ( ! $first ) {
														$query .= ',';
													}
													$query .= $this->wpdb->prepare( '( %d, %d, %d, %d, %d, %d, %d )', $this->product->product_id, $option_items_1[$a]->optionitem_id, $option_items_2[$b]->optionitem_id, $option_items_3[$c]->optionitem_id, $option_items_4[$d]->optionitem_id, $option_items_5[$e]->optionitem_id, $is_enabled );
													$list_count++;
													$first = false;
													if ( $list_count >= 100 ) {
														$this->wpdb->query( $query );
														$first = true;
														$query = 'INSERT INTO ec_optionitemquantity( product_id, optionitem_id_1, optionitem_id_2, optionitem_id_3, optionitem_id_4, optionitem_id_5, is_enabled ) VALUES';
														$list_count = 0;
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
			if ( ! $first ) {
				$this->wpdb->query( $query );
			}
		}
		$this->price_tiers = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT ec_pricetier.* FROM ec_pricetier WHERE product_id = %d ORDER BY quantity ASC", $this->id ) );
		$this->b2b_prices = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT ec_roleprice.* FROM ec_roleprice WHERE product_id = %d ORDER BY role_label ASC", $this->id ) );
		$this->option_item_images = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT ec_optionitemimage.* FROM ec_optionitemimage WHERE product_id = %d", $this->id ) );
		$this->advanced_options = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT ec_option.*, ec_option_to_product.product_id, ec_option_to_product.option_to_product_id, ec_option_to_product.conditional_logic FROM ec_option_to_product, ec_option WHERE ec_option_to_product.product_id = %d AND ec_option.option_id = ec_option_to_product.option_id ORDER BY ec_option_to_product.option_order ASC, ec_option.option_name ASC", $this->id ) );
		$this->categories = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM ec_categoryitem, ec_category WHERE ec_categoryitem.product_id = %d AND ec_category.category_id = ec_categoryitem.category_id", $this->id ) );
	}

	public function basic_fields() {

		global $wpdb;
		$fields = apply_filters( 'wp_easycart_admin_product_details_basic_fields_list', array(

			array(
				"name"				=> "activate_in_store",
				"type"				=> "checkbox",
				"label"				=> __( "Product Activated", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->activate_in_store,
				"selected"			=> true
			),
			array(
				"name"				=> "title",
				"type"				=> "text",
				"label"				=> __( "Title", 'wp-easycart' ),
				"required" 			=> true,
				"message"			=> __( "Your product must have a title", 'wp-easycart' ),
				"validation_type" 	=> 'text',
				"visible"			=> true,
				"value"				=> $this->product->title
			),
			array(
				"alt_name"			=> "model_number_orig",
				"type"				=> "hidden",
				"value"				=> $this->product->model_number
			),
			array(
				"name"				=> "model_number",
				"type"				=> "text",
				"label"				=> __( "SKU", 'wp-easycart' ),
				"required" 			=> true,
				"validation_type" 	=> 'model_number',
				"visible"			=> true,
				"value"				=> $this->product->model_number,
				"message"			=> '<span id="sku_invalid_error">' . __( 'SKU values must only include letters, numbers, forward slashes, underscores, and dashes.', 'wp-easycart' ) . '</span> <span id="sku_duplicate_error" style="display:none;">' . __( 'SKU is a duplicate and must be unique', 'wp-easycart' ) . '</span>'
			),
			array(
				"name"				=> "post_slug",
				"type"				=> "text",
				"label"				=> __( "Link Slug", 'wp-easycart' ),
				"required" 			=> true,
				"validation_type" 	=> 'post_slug',
				"visible"			=> ($this->id == '0') ? false : true,
				"value"				=> basename( $this->product->guid ),
				"message"			=> __( "Post Slug values must be unique and may only include letters, numbers, and dashes", 'wp-easycart' )
			),
			array(
				"name"				=> "manufacturer_id",
				"type"				=> "manufacturer",
				"label"				=> __( "Manufacturer", 'wp-easycart' ),
			),
			array(
				"name"				=> "price",
				"type"				=> "currency",
				"label"				=> __( "Price", 'wp-easycart' ),
				"required" 			=> true,
				"message"			=> __( "Your product must have a valid price", 'wp-easycart' ),
				"validation_type" 	=> 'price',
				"visible"			=> true,
				"value"				=> $this->product->price
			),
			array(
				"name"				=> "description",
				"type"				=> "wp_textarea",
				"label"				=> __( "Description", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'textarea',
				"visible"			=> true,
				"value"				=> $this->product->description
			)

		), $this->product );
		$this->print_fields( $fields );
	}

	public function menus_fields() {
		global $wpdb;
		$menus = $wpdb->get_results( "SELECT ec_menulevel1.menulevel1_id as id, ec_menulevel1.name as value FROM ec_menulevel1 ORDER BY ec_menulevel1.name ASC" );
		$submenus = $wpdb->get_results( "SELECT ec_menulevel2.menulevel2_id as id, ec_menulevel2.menulevel1_id AS parent_id, ec_menulevel2.name as value FROM ec_menulevel2 ORDER BY ec_menulevel2.menulevel1_id ASC, ec_menulevel2.name ASC" );
		$subsubmenus = $wpdb->get_results( "SELECT ec_menulevel3.menulevel3_id as id, ec_menulevel3.menulevel2_id AS parent_id, ec_menulevel3.name as value FROM ec_menulevel3 ORDER BY ec_menulevel3.menulevel2_id ASC, ec_menulevel3.name ASC" );
		$fields = apply_filters( 'wp_easycart_admin_product_details_menus_fields_list', array(
			array(
				"name"				=> "menulevel1_id_1",
				"type"				=> "select",
				"select2"			=> "basic",
				"label"				=> __( "Menu Level 1", 'wp-easycart' ),
				"data"				=> $menus,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"onchange"			=> 'product_details_update_menus',
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->menulevel1_id_1
			),
			array(
				"name"				=> "menulevel1_id_2",
				"type"				=> "select",
				"select2"			=> "basic",
				"label"				=> __( "Menu Level 2", 'wp-easycart' ),
				"data"				=> $submenus,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"onchange"			=> 'product_details_update_menus',
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->menulevel1_id_2
			),
			array(
				"name"				=> "menulevel1_id_3",
				"type"				=> "select",
				"select2"			=> "basic",
				"label"				=> __( "Menu Level 3", 'wp-easycart' ),
				"data"				=> $subsubmenus,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->menulevel1_id_3
			),
			array(
				"name"				=> "menulevel2_id_1",
				"type"				=> "select",
				"select2"			=> "basic",
				"label"				=> __( "Menu Level 1", 'wp-easycart' ),
				"data"				=> $menus,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"onchange"			=> 'product_details_update_menus',
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->menulevel2_id_1
			),
			array(
				"name"				=> "menulevel2_id_2",
				"type"				=> "select",
				"select2"			=> "basic",
				"label"				=> __( "Menu Level 2", 'wp-easycart' ),
				"data"				=> $submenus,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"onchange"			=> 'product_details_update_menus',
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->menulevel2_id_2
			),
			array(
				"name"				=> "menulevel2_id_3",
				"type"				=> "select",
				"select2"			=> "basic",
				"label"				=> __( "Menu Level 3", 'wp-easycart' ),
				"data"				=> $subsubmenus,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->menulevel2_id_3
			),
			array(
				"name"				=> "menulevel3_id_1",
				"type"				=> "select",
				"select2"			=> "basic",
				"label"				=> __( "Menu Level 1", 'wp-easycart' ),
				"data"				=> $menus,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"onchange"			=> 'product_details_update_menus',
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->menulevel3_id_1
			),
			array(
				"name"				=> "menulevel3_id_2",
				"type"				=> "select",
				"select2"			=> "basic",
				"label"				=> __( "Menu Level 2", 'wp-easycart' ),
				"data"				=> $submenus,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"onchange"			=> 'product_details_update_menus',
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->menulevel3_id_2
			),
			array(
				"name"				=> "menulevel3_id_3",
				"type"				=> "select",
				"select2"			=> "basic",
				"label"				=> __( "Menu Level 3", 'wp-easycart' ),
				"data"				=> $subsubmenus,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->menulevel3_id_3
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function categories_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_categories_fields_list', array(
			array(
				"name"				=> "categories",
				"type"				=> "categories",
				"label"				=> __( "Categories", 'wp-easycart' )
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function quantity_fields() {
		$quantity_type = 0;
		if ( $this->product->use_optionitem_quantity_tracking ) {
			$quantity_type = 2;
		} else if ( $this->product->show_stock_quantity ) {
			$quantity_type = 1;
		}
		$fields = apply_filters( 'wp_easycart_admin_product_details_quantity_fields_list', array(

			array(
				"name"				=> "stock_quantity_type",
				"type"				=> "select",
				"data"				=> array(
					(object) array(
						"id"		=> 1,
						"value"		=> __( "Track Overall Quantity", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 2,
						"value"		=> __( "Track Variation Quantity", 'wp-easycart' )
					)
				),
				"data_label"		=> __( "Do NOT Track Quantity", 'wp-easycart' ),
				"label"				=> __( "Track Quantity", 'wp-easycart' ),
				"required" 			=> false,
				"onchange"			=> "return ec_admin_product_details_quantity_type_change",
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $quantity_type
			),

			array(
				"name"				=> "stock_quantity",
				"requires"			=> array(
					"name"			=> "show_stock_quantity",
					"value"			=> 1,
					"default_show"	=> false
				),
				"type"				=> "number",
				"label"				=> __( "Total In Stock", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'number',
				"visible"			=> true,
				"value"				=> $this->product->stock_quantity
			),

			array(
				"name"				=> "min_purchase_quantity",
				"type"				=> "number",
				"label"				=> __( "Minimum Quantity", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'number',
				"visible"			=> true,
				"value"				=> $this->product->min_purchase_quantity
			),

			array(
				"name"				=> "max_purchase_quantity",
				"type"				=> "number",
				"label"				=> __( "Maximum Quantity", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'number',
				"visible"			=> true,
				"value"				=> $this->product->max_purchase_quantity
			)

		), $this->product );
		$this->print_fields( $fields );
	}

	public function packaging_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_packaging_fields_list', array(
			array(
				"name"				=> "weight",
				"type"				=> "number",
				"label"				=> __( "Weight", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'number',
				"visible"			=> true,
				"default"			=> "0.000",
				"value"				=> $this->product->weight
			),
			array(
				"name"				=> "width",
				"type"				=> "number",
				"label"				=> __( "Width", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'number',
				"visible"			=> true,
				"default"			=> "1.000",
				"value"				=> $this->product->width
			),
			array(
				"name"				=> "height",
				"type"				=> "number",
				"label"				=> __( "Height", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'number',
				"visible"			=> true,
				"default"			=> "1.000",
				"value"				=> $this->product->height
			),
			array(
				"name"				=> "length",
				"type"				=> "number",
				"label"				=> __( "Length", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'number',
				"visible"			=> true,
				"default"			=> "1.000",
				"value"				=> $this->product->length
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function pricing_fields() {
		global $wpdb;
		$user_roles = $wpdb->get_results( "SELECT ec_role.role_label AS id, ec_role.role_label AS value FROM ec_role ORDER BY role_label ASC" );
		$custom_price_label_types = array(
			(object) array(
				'id' => 0,
				'value' => __( 'Disabled', 'wp-easycart' )
			),
			(object) array(
				'id' => 1,
				'value' => __( 'Show on Product List', 'wp-easycart' )
			),
			(object) array(
				'id' => 2,
				'value' => __( 'Show on Product Details', 'wp-easycart' )
			),
			(object) array(
				'id' => 3,
				'value' => __( 'Show in Cart', 'wp-easycart' )
			),
			(object) array(
				'id' => 4,
				'value' => __( 'Show on Product List and Details', 'wp-easycart' )
			),
			(object) array(
				'id' => 5,
				'value' => __( 'Show on Product List and Cart', 'wp-easycart' )
			),
			(object) array(
				'id' => 6,
				'value' => __( 'Show on Product Details and Cart', 'wp-easycart' )
			),
			(object) array(
				'id' => 7,
				'value' => __( 'Show on List, Details, and Cart', 'wp-easycart' )
			),
		);
		$fields = apply_filters( 'wp_easycart_admin_product_details_pricing_fields_list', array(
			array(
				"name"				=> "list_price",
				"type"				=> "currency",
				"label"				=> __( "Previous Price", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'price',
				"visible"			=> true,
				"default"			=> "0.00",
				"value"				=> $this->product->list_price
			),
			array(
				"name"				=> "enable_price_label",
				"type"				=> "select",
				"label"				=> __( "Custom Price Label", 'wp-easycart' ),
				"data"				=> $custom_price_label_types,
				"select2"			=> "none",
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"onchange"			=> apply_filters( 'wp_easycart_admin_product_custom_price_label_change', 'show_pro_required' ),
				"value"				=> $this->product->enable_price_label
			),
			array(
				"name"				=> "replace_price_label",
				"type"				=> "checkbox",
				"label"				=> __( "Replace Price with Label", 'wp-easycart' ),
				"required" 			=> false,
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"requires"			=> array(
					"name"			=> "enable_price_label",
					"value"			=> array( 1, 2, 3, 4, 5, 6, 7 ),
					"default_show"	=> false
				),
				"visible"			=> false,
				"value"				=> $this->product->replace_price_label
			),
			array(
				"name"				=> "custom_price_label",
				"type"				=> "text",
				"label"				=> __( "Your Custom Price Label", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "enable_price_label",
					"value"			=> array( 1, 2, 3, 4, 5, 6, 7 ),
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> false,
				"value"				=> $this->product->custom_price_label,
				"placeholder"		=> __( 'Enter your custom label', 'wp-easycart' )
			),
			array(
				"name"				=> "login_for_pricing",
				"type"				=> "checkbox",
				"label"				=> __( "Login for Pricing", 'wp-easycart' ),
				"required" 			=> false,
				"onclick"			=> 'show_required_user_level',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->login_for_pricing
			),
			array(
				"name"				=> "login_for_pricing_user_level",
				"type"				=> "select",
				"label"				=> __( "Restrict to User Role", 'wp-easycart' ),
				"message"		=> __( "Only shoppers signed in with the selected role can see pricing and purchase — useful for wholesale or members-only catalogs.", 'wp-easycart' ),
				"data"				=> $user_roles,
				"data_label"		=> __( "Show to All User Levels", 'wp-easycart' ),
				"default_value"		=> '',
				"default_selected"	=> ( '' == $this->product->login_for_pricing_user_level || '[]' == $this->product->login_for_pricing_user_level ) ? true : false,
				"select2"			=> "basic",
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "login_for_pricing",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'select',
				"visible"			=> false,
				"multiple"			=> true,
				"value"				=> json_decode( $this->product->login_for_pricing_user_level )
			),
			array(
				"name"				=> "login_for_pricing_label",
				"type"				=> "text",
				"label"				=> __( "Login Label", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "login_for_pricing",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> false,
				"value"				=> $this->product->login_for_pricing_label,
				"placeholder"		=> __( 'Customize the login for pricing button label or leave empty for default', 'wp-easycart' )
			),
			array(
				"name"				=> "show_custom_price_range",
				"type"				=> "checkbox",
				"label"				=> __( "Custom Price Range Display (e.g. $90-$99)", 'wp-easycart' ),
				"required" 			=> false,
				"onclick"			=> 'show_custom_price_range',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->show_custom_price_range
			),
			array(
				"name"				=> "price_range_low",
				"type"				=> "currency",
				"label"				=> __( "Price Range Display Low", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "show_custom_price_range",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'price',
				"visible"			=> false,
				"value"				=> $this->product->price_range_low
			),
			array(
				"name"				=> "price_range_high",
				"type"				=> "currency",
				"label"				=> __( "Price Range Display High", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "show_custom_price_range",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'price',
				"visible"			=> false,
				"value"				=> $this->product->price_range_high
			),
		), $this->product );
		$this->print_fields( $fields );
	}

	public function advanced_pricing_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_pricing_fields_list', array(
			array(
				"name"				=> "tier_pricing",
				"type"				=> "tier_pricing",
				"label"				=> __( "Volume Pricing", 'wp-easycart' )
			),
			array(
				"name"				=> "b2b_pricing",
				"type"				=> "b2b_pricing",
				"label"				=> __( "B2B Pricing", 'wp-easycart' )
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function shipping_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_shipping_fields_list', array(
			array(
				"name"				=> "is_shippable",
				"type"				=> "checkbox",
				"label"				=> __( "Enable Shipping", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"selected"			=> true,
				"value"				=> $this->product->is_shippable
			),
			array(
				"name"				=> "exclude_shippable_calculation",
				"type"				=> "checkbox",
				"label"				=> __( "Exclude from Shipping Calculation", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"selected"			=> true,
				"value"				=> $this->product->exclude_shippable_calculation
			),
			array(
				"name"				=> "ship_to_billing",
				"type"				=> "checkbox",
				"label"				=> __( "Disable shipping address and ship to billing address when this item is in the cart", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"selected"			=> true,
				"value"				=> $this->product->ship_to_billing
			),
			array(
				"name"				=> "allow_backorders",
				"type"				=> "checkbox",
				"label"				=> __( "Allow Backorders", 'wp-easycart' ),
				"required" 			=> false,
				"show"				=> array(
					"name"			=> "backorder_fill_date",
					"value"			=> "1"
				),
				"onchange"			=> 'ec_admin_product_details_backorder_change',
				"validation_type" 	=> 'checkbox',
				"visible"			=> false,
				"value"				=> $this->product->allow_backorders
			),
			array(
				"name"				=> "backorder_fill_date",
				"type"				=> "text",
				"label"				=> __( "Expected Delivery Date", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "allow_backorders",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> true,
				"value"				=> $this->product->backorder_fill_date
			),
			array(
				"name"				=> "handling_price",
				"type"				=> "currency",
				"label"				=> __( "One-Time Handling Cost", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'price',
				"visible"			=> true,
				"default"			=> "0.000",
				"value"				=> $this->product->handling_price
			),
			array(
				"name"				=> "handling_price_each",
				"type"				=> "currency",
				"label"				=> __( "Handling Cost Each Item", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'price',
				"visible"			=> true,
				"default"			=> "0.000",
				"value"				=> $this->product->handling_price_each
			),
			array(
				"name"				=> "shipping_restriction",
				"type"				=> "checkbox",
				"label"				=> __( "Restrict Shipping for this Product", 'wp-easycart' ),
				"required" 			=> false,
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->shipping_restriction,
			),
		), $this->product );
		$this->print_fields( $fields );
	}

	public function short_description_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_short_description_fields_list', array(
			array(
				"name"				=> "short_description",
				"type"				=> "textarea",
				"label"				=> __( "Short Description", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'textarea',
				"visible"			=> true,
				"value"				=> $this->product->short_description
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function specifications_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_specifications_fields_list', array(
			array(
				"name"				=> "specifications",
				"type"				=> "wp_textarea",
				"label"				=> __( "Specifications", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'textarea',
				"visible"			=> true,
				"value"				=> $this->product->specifications
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function order_completed_note_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_order_completed_note_fields_list', array(
			array(
				"name"				=> "order_completed_note",
				"type"				=> "wp_textarea",
				"label"				=> __( "Order Completed Note", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'textarea',
				"visible"			=> true,
				"value"				=> $this->product->order_completed_note
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function order_completed_email_note_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_order_completed_email_note_fields_list', array(
			array(
				"name"				=> "order_completed_email_note",
				"type"				=> "wp_textarea",
				"label"				=> __( "Order email product note", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'textarea',
				"visible"			=> true,
				"value"				=> $this->product->order_completed_email_note
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function order_completed_details_note_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_order_completed_details_note_fields_list', array(
			array(
				"name"				=> "order_completed_details_note",
				"type"				=> "wp_textarea",
				"label"				=> __( "Order details product note", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'textarea',
				"visible"			=> true,
				"value"				=> $this->product->order_completed_details_note
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function featured_products_fields() {
		global $wpdb;
		$featured_ids = array_filter( array_map( 'intval', array( $this->product->featured_product_id_1, $this->product->featured_product_id_2, $this->product->featured_product_id_3, $this->product->featured_product_id_4 ) ) );
		$products = $featured_ids ? $wpdb->get_results( 'SELECT product_id AS id, title AS value, ( activate_in_store = 0 ) AS inactive FROM ec_product WHERE product_id IN ( ' . implode( ',', $featured_ids ) . ' ) ORDER BY title ASC' ) : array();
		$fields = apply_filters( 'wp_easycart_admin_product_details_featured_products_fields_list', array(
			array(
				"name"				=> "featured_product_id_1",
				"type"				=> "select",
				"label"				=> __( "Featured Product 1", 'wp-easycart' ),
				"data"				=> $products,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"select2"			=> "basic",
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->featured_product_id_1
			),
			array(
				"name"				=> "featured_product_id_2",
				"type"				=> "select",
				"label"				=> __( "Featured Product 2", 'wp-easycart' ),
				"data"				=> $products,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"select2"			=> "basic",
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->featured_product_id_2
			),
			array(
				"name"				=> "featured_product_id_3",
				"type"				=> "select",
				"label"				=> __( "Featured Product 3", 'wp-easycart' ),
				"data"				=> $products,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"select2"			=> "basic",
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->featured_product_id_3
			),
			array(
				"name"				=> "featured_product_id_4",
				"type"				=> "select",
				"label"				=> __( "Featured Product 4", 'wp-easycart' ),
				"data"				=> $products,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"select2"			=> "basic",
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->featured_product_id_4
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function general_options_fields() {
		global $wpdb;
		$user_roles = array();
		$user_roles[] = (object) array( 'id' => 0, 'value' => esc_attr( class_exists( 'wp_easycart_admin_edition' ) ? rtrim( wp_easycart_admin_edition::included_text( 'pro' ), '.' ) : __( 'Included with Pro and Premium licenses', 'wp-easycart' ) ) );
		$fields = apply_filters( 'wp_easycart_admin_product_details_general_options_fields_list', array(
			array(
				"name"				=> "show_on_startup",
				"type"				=> "checkbox",
				"label"				=> __( "Show on Main Store Page", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"selected"			=> true,
				"value"				=> $this->product->show_on_startup
			),
			array(
				"name"				=> "is_special",
				"type"				=> "checkbox",
				"label"				=> __( "Include in Specials Widget", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->is_special
			),
			array(
				"name"				=> "use_customer_reviews",
				"type"				=> "checkbox",
				"label"				=> __( "Allow Customer Reviews", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->use_customer_reviews
			),
			array(
				"name"				=> "is_donation",
				"type"				=> "checkbox",
				"label"				=> __( "Donation/Invoice Product", 'wp-easycart' ),
				"description"		=> __( "The customer types in the amount to pay — great for donations or letting someone pay an invoice. Your set price is ignored.", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"visible"			=> true,
				"value"				=> $this->product->is_donation
			),
			array(
				"name"				=> "is_giftcard",
				"type"				=> "checkbox",
				"label"				=> __( "Gift Card Product", 'wp-easycart' ),
				"description"		=> __( "Sells a store gift card. A unique card code is generated and emailed to the buyer, ready to spend at checkout.", 'wp-easycart' ),
				"required" 			=> false,
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->is_giftcard
			),
			array(
				"name"				=> "inquiry_mode",
				"type"				=> "checkbox",
				"label"				=> __( "Inquiry Mode", 'wp-easycart' ),
				"description"		=> __( "Swaps Add to Cart for a contact button — for quote-based or made-to-order items. Point it at your contact page below.", 'wp-easycart' ),
				"required" 			=> false,
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->inquiry_mode
			),
			array(
				"name"				=> "inquiry_url",
				"type"				=> "text",
				"label"				=> __( "Inquiry URL", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "inquiry_mode",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> true,
				"value"				=> $this->product->inquiry_url
			),
			array(
				"name"				=> "catalog_mode",
				"type"				=> "checkbox",
				"label"				=> __( "Seasonal Mode", 'wp-easycart' ),
				"description"		=> __( "Keeps the product on display but not purchasable. Shoppers see your phrase (e.g. “Coming in November”) instead of Add to Cart.", 'wp-easycart' ),
				"required" 			=> false,
				"show"				=> array(
					"name"			=> "catalog_mode_phrase",
					"value"			=> "1"
				),
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->catalog_mode
			),
			array(
				"name"				=> "catalog_mode_phrase",
				"type"				=> "text",
				"label"				=> __( "Seasonal Phrase", 'wp-easycart' ),
				"message"		=> __( "Shown on the product page in place of the Add to Cart button.", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "catalog_mode",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> true,
				"value"				=> $this->product->catalog_mode_phrase
			),
			array(
				"name"				=> "is_preorder_type",
				"type"				=> "checkbox",
				"label"				=> __( "Enable Preorder for Pickup", 'wp-easycart' ),
				"description"		=> __( "Order now, pick up on a chosen date — think pies for the holidays. Customers select a pickup date at checkout from the pickup schedule in your store settings.", 'wp-easycart' ),
				"required" 			=> false,
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->is_preorder_type
			),
			array(
				"name"				=> "is_restaurant_type",
				"type"				=> "checkbox",
				"label"				=> __( "Enable Restaurant for Pickup", 'wp-easycart' ),
				"description"		=> __( "ASAP-style pickup ordering — think pizzas. Customers choose a ready time (ASAP, in 1 hour, later today) based on your pickup schedule and hours.", 'wp-easycart' ),
				"required" 			=> false,
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->is_restaurant_type
			),
			array(
				/* Rendered in Organize > Visibility & Sorting (see the section's
				   'only' list in product-details-v2.php); excluded from the
				   Behaviors section so it is not duplicated. */
				"name"				=> "role_id",
				"type"				=> "select",
				"label"				=> __( "Limit Visibility to User Role", 'wp-easycart' ),
				"message"		=> __( "When a role is selected, this product is completely hidden from everyone else — guests and all other roles will not see it anywhere in your store.", 'wp-easycart' ),
				"data"				=> apply_filters( 'wp_easycart_admin_product_details_user_roles', $user_roles ),
				"data_label"		=> __( "Visible to Everyone", 'wp-easycart' ),
				"select2"			=> "basic",
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"value"				=> $this->product->role_id,
				"onchange"			=> 'show_pro_required',
			),
			array(
				"name"				=> "sort_position",
				"type"				=> "number",
				"label"				=> __( "Sort Position", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'number',
				"visible"			=> true,
				"default"			=> "0",
				"value"				=> $this->product->sort_position,
				"step"				=> 1,
			),
		), $this->product );
		$this->print_fields( $fields );
	}

	public function tax_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_tax_fields_list', array(
			array(
				"name"				=> "is_taxable",
				"type"				=> "checkbox",
				"label"				=> __( "Product is Taxable", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"selected"			=> true,
				"value"				=> $this->product->is_taxable
			),
			array(
				"name"				=> "vat_rate",
				"type"				=> "checkbox",
				"label"				=> __( "VAT Enabled", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> intval( $this->product->vat_rate )
			),
			array(
				"name"				=> "TIC",
				"type"				=> "text",
				"label"				=> __( "TIC (Tax Cloud)", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'text',
				"visible"			=> true,
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"value"				=> $this->product->TIC
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function deconetwork_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_deconetwork_fields_list', array(
			array(
				"name"				=> "is_deconetwork",
				"type"				=> "checkbox",
				"label"				=> __( "Deconetwork Product", 'wp-easycart' ),
				"description"		=> __( "Connects this product to your Deconetwork designer so customers customize decorated items before adding to cart.", 'wp-easycart' ),
				"required" 			=> false,
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->is_deconetwork
			),
			array(
				"name"				=> "deconetwork_mode",
				"type"				=> "select",
				"data"				=> array(
					(object) array(
						"id"		=> "designer",
						"value"		=> __( "Designer Mode", 'wp-easycart' )
					),
					(object) array(
						"id"		=> "blank",
						"value"		=> __( "Blank Mode", 'wp-easycart' )
					),
					(object) array(
						"id"		=> "designer_predec",
						"value"		=> __( "Designer Predecorated Mode", 'wp-easycart' )
					),
					(object) array(
						"id"		=> "predec",
						"value"		=> __( "Predecorated Mode", 'wp-easycart' )
					),
					(object) array(
						"id"		=> "design",
						"value"		=> __( "Blank Design Mode", 'wp-easycart' )
					),
					(object) array(
						"id"		=> "view_design",
						"value"		=> __( "Design Detail Mode", 'wp-easycart' )
					)
				),
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"label"				=> __( "Deconetwork Mode", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_deconetwork",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'select',
				"visible"			=> false,
				"value"				=> $this->product->deconetwork_mode
			),
			array(
				"name"				=> "deconetwork_product_id",
				"type"				=> "text",
				"label"				=> __( "Product ID", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_deconetwork",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> false,
				"value"				=> $this->product->deconetwork_product_id
			),
			array(
				"name"				=> "deconetwork_size_id",
				"type"				=> "text",
				"label"				=> __( "Product Size ID", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_deconetwork",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> false,
				"value"				=> $this->product->deconetwork_size_id
			),
			array(
				"name"				=> "deconetwork_color_id",
				"type"				=> "text",
				"label"				=> __( "Product Color ID", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_deconetwork",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> false,
				"value"				=> $this->product->deconetwork_color_id
			),
			array(
				"name"				=> "deconetwork_design_id",
				"type"				=> "text",
				"label"				=> __( "Deconetwork Design ID", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_deconetwork",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> false,
				"value"				=> $this->product->deconetwork_design_id
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function subscription_fields() {
		global $wpdb;
		$fields = apply_filters( 'wp_easycart_admin_product_details_subscription_fields_list', array(
			array(
				"name"				=> "is_subscription_item",
				"type"				=> "checkbox",
				"label"				=> __( "Subscription Product", 'wp-easycart' ),
				"description"		=> __( "Bills the customer automatically on the interval you set below. Requires a connected Stripe account.", 'wp-easycart' ),
				"required" 			=> false,
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"validation_type" 	=> 'checkbox',
				"visible"			=> true,
				"value"				=> $this->product->is_subscription_item
			),
			array(
				"name"				=> "subscription_interval",
				"type"				=> "subscription_interval",
				"label"				=> __( "Subscription Interval (How often to bill the customer)", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_subscription_item",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'subscription_interval',
				"visible"			=> false,
				"bill_length"		=> $this->product->subscription_bill_length,
				"bill_period"		=> $this->product->subscription_bill_period
			),
			array(
				'name' => 'enable_duration',
				'type' => 'select',
				'data' => array(
					(object) array(
						'id' => '0',
						'value' => __( 'Bill Customer Forever', 'wp-easycart' ),
					),
					(object) array(
						'id' => '1',
						'value' => __( 'Set Max Number of Payments', 'wp-easycart' ),
					),
				),
				'data_label' => __( 'Enable Billing Duration?', 'wp-easycart' ),
				'label' => __( 'Enable Billing Duration?', 'wp-easycart' ),
				'required' => false,
				'message' => __( 'Please choose a billing duration option.', 'wp-easycart' ),
				'value' => $this->product->enable_duration,
				'onchange' => 'ec_admin_product_details_billing_duration_toggle',
			),
			array(
				"name"				=> "subscription_bill_duration",
				"type"				=> "text",
				"label"				=> __( "Billing Duration (Number of payments before automatically cancelling the subscription)", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					array(
						"name"			=> "is_subscription_item",
						"value"			=> 1,
						"default_show"	=> false
					),
					array(
						"name"			=> "enable_duration",
						"value"			=> 1,
						"default_show"	=> false
					),
				),
				"validation_type" 	=> 'text',
				"visible"			=> false,
				"value"				=> $this->product->subscription_bill_duration
			),
			array(
				'name' => 'subscription_shipping_recurring',
				'type' => 'select',
				'data' => array(
					(object) array(
						'id' => '0',
						'value' => __( 'Any Shipping Cost - Only Charge Once', 'wp-easycart' ),
					),
					(object) array(
						'id' => '1',
						'value' => __( 'Shipping Costs Billing Recurring', 'wp-easycart' ),
					),
				),
				'data_label' => __( 'How to Handle Shipping Costs', 'wp-easycart' ),
				'label' => __( 'How to Handle Shipping Costs', 'wp-easycart' ),
				'required' => false,
				'message' => __( 'Please choose how you wish to handle any shipping costs.', 'wp-easycart' ),
				'value' => $this->product->subscription_shipping_recurring,
			),
			array(
				'name' => 'subscription_recurring_email',
				'type' => 'select',
				'data' => array(
					(object) array(
						'id' => '0',
						'value' => __( 'Only Send Order Receipt Email on First Payment', 'wp-easycart' ),
					),
					(object) array(
						'id' => '1',
						'value' => __( 'Send Order Email Receipts for Each Payment', 'wp-easycart' ),
					),
				),
				'data_label' => __( 'How to Handle Recurring Email Receipts', 'wp-easycart' ),
				'label' => __( 'How to Handle Recurring Email Receipts', 'wp-easycart' ),
				'required' => false,
				'message' => __( 'Please choose how you wish to handle recurring email receipts.', 'wp-easycart' ),
				'value' => $this->product->subscription_recurring_email,
			),
			array(
				"name"				=> "trial_period_days",
				"type"				=> "number",
				"label"				=> __( "Trial Days", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_subscription_item",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'number',
				"visible"			=> false,
				"value"				=> $this->product->trial_period_days
			),
			array(
				"name"				=> "subscription_signup_fee",
				"type"				=> "currency",
				"label"				=> __( "Initial Fee", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_subscription_item",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'price',
				"visible"			=> false,
				"value"				=> $this->product->subscription_signup_fee
			),
			array(
				"name"				=> "allow_multiple_subscription_purchases",
				"type"				=> "checkbox",
				"label"				=> __( "Allow Multiple Subscriptions", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_subscription_item",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'checkbox',
				"visible"			=> false,
				"value"				=> $this->product->allow_multiple_subscription_purchases
			),
			array(
				"name"				=> "subscription_prorate",
				"type"				=> "checkbox",
				"label"				=> __( "Prorate on Upgrade/Downgrade", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_subscription_item",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'checkbox',
				"visible"			=> false,
				"value"				=> $this->product->subscription_prorate
			),
			array(
				"name"				=> "subscription_plan_id",
				"type"				=> "select",
				"label"				=> __( "Subscription Plan (not compatible with price adjustments or recurring shipping)", 'wp-easycart' ),
				"data"				=> $wpdb->get_results( "SELECT subscription_plan_id AS id, plan_title AS value FROM ec_subscription_plan ORDER BY plan_title ASC" ),
				"data_label"		=> __( "No Plan", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_subscription_item",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'select',
				"visible"			=> false,
				"value"				=> $this->product->subscription_plan_id
			),
			array(
				"name"				=> "membership_page",
				"type"				=> "text",
				"label"				=> __( "Membership URL", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_subscription_item",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'text',
				"visible"			=> false,
				"value"				=> $this->product->membership_page
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function seo_fields() {
		$fields = apply_filters( 'wp_easycart_admin_product_details_seo_fields_list', array(
			array(
				"name"				=> "seo_description",
				"type"				=> "textarea",
				"label"				=> __( "SEO Description", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'textarea',
				"visible"			=> true,
				"value"				=> $this->product->seo_description
			),
			array(
				"name"				=> "seo_keywords",
				"type"				=> "textarea",
				"label"				=> __( "SEO Keywords", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'textarea',
				"visible"			=> true,
				"value"				=> $this->product->seo_keywords
			),
			array(
				"name"				=> "post_excerpt",
				"type"				=> "textarea",
				"label"				=> __( "Post Excerpt - Commonly Used in Search Results", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'textarea',
				"visible"			=> true,
				"value"				=> $this->product->post_excerpt
			),
			array(
				"name"				=> "featured_image",
				"type"				=> "wp_image_upload",
				"label"				=> __( "Post Featured Image - Commonly Used in Search Results", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'wp_image',
				"visible"			=> true,
				"value"				=> get_post_thumbnail_id( $this->product->post_id )
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function downloads_fields() {
		$s3_files = array( (object) array( "id" => "0", "value" => "Not Connected" ) );
		if ( ( get_option( 'ec_option_amazon_key' ) != '' && get_option( 'ec_option_amazon_key' ) != '0' ) && 
			( get_option( 'ec_option_amazon_secret' ) != '' && get_option( 'ec_option_amazon_secret' ) != '0' ) &&
			( get_option( 'ec_option_amazon_bucket' ) != '' && get_option( 'ec_option_amazon_bucket' ) != '0' ) && 
			( phpversion() >= 5.3 ) ) {

			try {
				require_once( EC_PLUGIN_DIRECTORY . "/inc/classes/account/ec_amazons3.php" );
				$amazons3 = new ec_amazons3();
				$s3_files_from_server = $amazons3->get_aws_files();
				$s3_files = array();
				foreach ( $s3_files_from_server as $file ) {
					$s3_files[] = (object) array(
						"id"			=> $file,
						"value"			=> $file
					);
				}
			} catch( Exception $e ) {
				echo esc_attr__( 'Error connecting to Amazon S3', 'wp-easycart' ) . ': ' . esc_attr( $e->getMessage() );
			}
		}

		$fields = apply_filters( 'wp_easycart_admin_product_details_downloads_fields_list', array(
			array(
				"name"				=> "is_download",
				"type"				=> "checkbox",
				"label"				=> __( "Download Product", 'wp-easycart' ),
				"description"		=> __( "Delivers a file instead of a shipment. The customer gets a secure download link after purchase.", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'checkbox',
				"onclick"			=> 'show_pro_required',
				"read-only"			=> true,
				"visible"			=> true,
				"value"				=> $this->product->is_download
			),
			array(
				"name"				=> "is_amazon_download",
				"type"				=> "select",
				"label"				=> __( "Download Location", 'wp-easycart' ),
				"data"				=> array(
					(object) array(
						"id"		=> "1",
						"value"		=> __( "Amazon S3", 'wp-easycart' )
					)
				),
				"data_label"		=> __( "My Server", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_download",
					"value"			=> 1,
					"default_show"	=> false
				),
				"onchange"			=> 'ec_admin_product_details_download_location_toggle',
				"validation_type" 	=> 'select',
				"visible"			=> false,
				"value"				=> $this->product->is_amazon_download
			),
			array(
				"name"				=> "amazon_key",
				"type"				=> "select",
				"label"				=> __( "S3 File", 'wp-easycart' ),
				"data"				=> $s3_files,
				"data_label"		=> __( "None Selected", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					array(
						"name"		=> "is_amazon_download",
						"value"		=> 1,
						"default_show"=> false
					),
					array(
						"name"		=> "is_download",
						"value"		=> 1,
						"default_show"=> false
					)
				),
				"validation_type" 	=> 'select',
				"visible"			=> false,
				"value"				=> $this->product->amazon_key
			),
			array(
				"name"				=> "download_file_name",
				"type"				=> "image_upload",
				"hide_preview"      => true,
				"button_label"		=> __( "Upload File", 'wp-easycart' ),
				"label"				=> __( "Download File", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					array(
						"name"		=> "is_amazon_download",
						"value"		=> 0,
						"default_show"=> false
					),
					array(
						"name"		=> "is_download",
						"value"		=> 1,
						"default_show"=> false
					)
				),
				"validation_type" 	=> 'image',
				"image_action"		=> 'ec_admin_download_upload',
				"visible"			=> true,
				"delete_label"		=> __( 'Remove File', 'wp-easycart' ),
				"value"				=> $this->product->download_file_name
			),
			array(
				"name"				=> "maximum_downloads_allowed",
				"type"				=> "number",
				"label"				=> __( "Maximum Downloads", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_download",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'number',
				"visible"			=> false,
				"value"				=> $this->product->maximum_downloads_allowed
			),
			array(
				"name"				=> "download_timelimit_seconds",
				"type"				=> "number",
				"label"				=> __( "Expiration (in seconds)", 'wp-easycart' ),
				"required" 			=> false,
				"requires"			=> array(
					"name"			=> "is_download",
					"value"			=> 1,
					"default_show"	=> false
				),
				"validation_type" 	=> 'number',
				"visible"			=> false,
				"value"				=> $this->product->download_timelimit_seconds
			)
		), $this->product );
		$this->print_fields( $fields );
	}

	public function tags_fields() {

		$fields = apply_filters( 'wp_easycart_admin_product_details_tags_fields_list', array(
			array(
				'name'				=> 'image_hover_type',
				"type"				=> "select",
				"label"				=> __( "Image Hover Type", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"data"				=> array(
					(object) array(
						"id"		=> 1,
						"value"		=> __( "Image Flip", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 2,
						"value"		=> __( "Image Crossfade", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 3,
						"value"		=> __( "Lighten", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 5,
						"value"		=> __( "Image Grow", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 6,
						"value"		=> __( "Image Shrink", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 7,
						"value"		=> __( "Grey Color", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 8,
						"value"		=> __( "Brighten", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 9,
						"value"		=> __( "Image Slide", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 10,
						"value"		=> __( "Flipbook", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 4,
						"value"		=> __( "No Effect", 'wp-easycart' )
					),
				),
				"data_label"		=> __( "Hover Effect", 'wp-easycart' ),
				"value"				=> $this->product->image_hover_type
			),
			array(
				'name'				=> 'image_effect_type',
				"type"				=> "select",
				"label"				=> __( "Image Effect Type", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"data"				=> array(
					(object) array(
						"id"		=> 'none',
						"value"		=> __( "None", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 'border',
						"value"		=> __( "Border", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 'shadow',
						"value"		=> __( "Shadow", 'wp-easycart' )
					),
				),
				"data_label"		=> __( "Image Effect", 'wp-easycart' ),
				"value"				=> $this->product->image_effect_type
			),
			array(
				"name"				=> "tag_type",
				"type"				=> "select",
				"label"				=> __( "Tag Type", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'select',
				"visible"			=> true,
				"data"				=> array(
					(object) array(
						"id"		=> 1,
						"value"		=> __( "Round Tag", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 2,
						"value"		=> __( "Square Tag", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 3,
						"value"		=> __( "Diagonal Tag", 'wp-easycart' )
					),
					(object) array(
						"id"		=> 4,
						"value"		=> __( "Classy Tag", 'wp-easycart' )
					)
				),
				"data_label"		=> __( "No Tag", 'wp-easycart' ),
				"value"				=> $this->product->tag_type
			),
			array(
				"name"				=> "tag_text",
				"type"				=> "text",
				"label"				=> __( "Tag Promo Text", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'text',
				"visible"			=> true,
				"value"				=> $this->product->tag_text
			),
			array(
				"name"				=> "tag_bg_color",
				"type"				=> "color",
				"label"				=> __( "Tag BG Color", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'color',
				"visible"			=> true,
				"value"				=> $this->product->tag_bg_color
			),
			array(
				"name"				=> "tag_text_color",
				"type"				=> "color",
				"label"				=> __( "Tag Text Color", 'wp-easycart' ),
				"required" 			=> false,
				"validation_type" 	=> 'color',
				"visible"			=> true,
				"value"				=> $this->product->tag_text_color
			)

		), $this->product );
		$this->print_fields( $fields );
	}

}