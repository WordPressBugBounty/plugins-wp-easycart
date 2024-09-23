<?php
$table = new wp_easycart_admin_table( );
$table->set_table( 'ec_country', 'id_cnt' );
$table->set_default_sort( 'sort_order', 'ASC' );
$table->set_header( __( 'Manage Countries', 'wp-easycart' ) );
$table->set_icon( 'admin-site' );
$table->set_docs_link( 'settings', 'countries' );
$table->enable_mobile_column();
$table->set_list_columns( 
	array(
		array( 
			'name' 	=> 'name_cnt', 
			'label'	=> __( 'Country Name', 'wp-easycart' ),
			'format'=> 'string',
			'linked'=> true,
			'is_mobile' => true,
			'subactions'=> array(
				array(
					'click'         => 'return false',
					'name'          => __( 'Delete', 'wp-easycart' ),
					'action_type'   => 'delete',
					'action'        => 'delete-country'
				)
			)
		),
		array( 
			'name' 	=> 'iso2_cnt',
			'is_mobile' => true,
			'label'	=> __( '2 Digit Name', 'wp-easycart' ),
			'format'=> 'string'
		),
		array( 
			'name' 	=> 'iso3_cnt',
			'is_mobile' => true,
			'label'	=> __( '3 Digit Name', 'wp-easycart' ),
			'format'=> 'string'
		),
		array( 
			'name' 	=> 'ship_to_active',
			'is_mobile' => true,
			'label'	=> __( 'Enabled?', 'wp-easycart' ),
			'format'=> 'checkbox'
		),
		array( 
			'name' 	=> 'sort_order',
			'is_mobile' => true,
			'label'	=> __( 'Sort Order', 'wp-easycart' ),
			'format'=> 'string'
		)
	)
);
$table->set_search_columns(
	array( 'ec_country.name_cnt' )
);
$table->set_bulk_actions(
	array(
		array(
			'name'	=> 'delete-country',
			'label'	=> __( 'Delete', 'wp-easycart' )
		),
		array(
			'name'	=> 'bulk-enable-country',
			'label'	=> __( 'Enable', 'wp-easycart' )
		),
		array(
			'name'	=> 'bulk-disable-country',
			'label'	=> __( 'Disable', 'wp-easycart' )
		)
	)
);
$table->set_actions(
	array(
		array(
			'name'	=> 'edit',
			'label'	=> __( 'Edit', 'wp-easycart' ),
			'icon'	=> 'edit'
		),
		array(
			'name'	=> 'delete-country',
			'label'	=> __( 'Delete', 'wp-easycart' ),
			'icon'	=> 'trash'
		)
	)
);

$table->set_filters(
	array( )
);
$table->set_label( __( 'Country', 'wp-easycart' ), __( 'Countries', 'wp-easycart' ) );
$table->print_table();
