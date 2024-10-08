<?php
$table = new wp_easycart_admin_table();
$table->set_table( 'ec_review', 'review_id' );
$table->set_table_id( 'ec_admin_review_list' );
$table->set_join( 'LEFT JOIN ec_product ON ec_product.product_id = ec_review.product_id' );
$table->set_default_sort( 'ec_review.date_submitted', 'DESC' );
$table->set_icon( 'format-chat' );
$table->set_docs_link( 'products', 'product-reviews' );
$table->set_add_new ( false, '', '' );
$table->enable_mobile_column();
$table->set_list_columns(
	array(
		array(
			'name' => 'title',
			'label' => __( 'Review Title', 'wp-easycart' ),
			'format' => 'string',
			'linked' => true,
			'is_mobile' => true,
			'subactions' => array(
				array(
					'click' => 'return false',
					'name' => __( 'Approve', 'wp-easycart' ),
					'action_type' => 'approve',
					'action' => 'approve-review',
				),
				array(
					'click' => 'return false',
					'name' => __( 'Deny', 'wp-easycart' ),
					'action_type' => 'unapprove',
					'action' => 'unapprove-review',
				),
				array(
					'click' => 'return false',
					'name' => __( 'Delete', 'wp-easycart' ),
					'action_type' => 'delete',
					'action' => 'delete-review',
				),
			),
		),
		array(
			'select' => "DATE_FORMAT( ec_review.date_submitted, '%b %d, %Y' ) AS date_submitted",
			'name' => 'date_submitted', 
			'label' => __( 'Review Date', 'wp-easycart' ),
			'is_mobile' => true,
			'format' => 'string',
		),
		array(
			'select' => 'ec_product.title AS product_title',
			'name' => 'product_title', 
			'label' => __( 'Product', 'wp-easycart' ),
			'is_mobile' => true,
			'format' => 'string',
		),
		array(
			'name' => 'rating', 
			'label' => __( 'Rating', 'wp-easycart' ),
			'is_mobile' => true,
			'format' => 'star_rating',
		),
		array(
			'name' => 'approved', 
			'label' => __( 'Approved', 'wp-easycart' ),
			'is_mobile' => true,
			'format' => 'bool',
		),
	)
);
$table->set_search_columns(
	array( 'ec_review.title, ec_product.title' )
);
$table->set_bulk_actions(
	array(
		array(
			'name' => 'delete-review',
			'label' => __( 'Delete', 'wp-easycart' ),
		),
		array(
			'name' => 'approve-review',
			'label' => __( 'Approve Selected', 'wp-easycart' ),
		),
		array(
			'name' => 'unapprove-review',
			'label' => __( 'Deny Selected', 'wp-easycart' ),
		),
	)
);
$table->set_actions(
	array(
		array(
			'name' => 'approve-review',
			'label' => __( 'Approve', 'wp-easycart' ),
			'icon' => 'thumbs-up',
		),
		array(
			'name' => 'unapprove-review',
			'label' => __( 'Deny', 'wp-easycart' ),
			'icon' => 'thumbs-down',
		),
		array(
			'name' => 'edit',
			'label' => __( 'Edit', 'wp-easycart' ),
			'icon' => 'edit',
		),
		array(
			'name' => 'delete-review',
			'label' => __( 'Delete', 'wp-easycart' ),
			'icon' => 'trash',
		),
	)
);
global $wpdb;
$product_list = $wpdb->get_results( "SELECT ec_product.product_id AS value, ec_product.title AS label FROM ec_product ORDER BY ec_product.title ASC" );
$table->set_filters(
	array(
		array(
			'data' => $product_list,
			'label' => __( 'All Products', 'wp-easycart' ),
			'where' => 'ec_review.product_id = %d',
		),
	)
);
$table->set_label( __( 'Product Review', 'wp-easycart' ), __( 'Product Reviews', 'wp-easycart' ) );
$table->print_table();
