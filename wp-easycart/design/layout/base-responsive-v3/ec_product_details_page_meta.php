<?php
do_action( 'wp_easycart_product_details_before', $product );
$wpeasycart_meta_in_editor = ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() ); // 6.0.2: the Elementor editor draws this widget too; the view events only count shoppers.
if ( ! $wpeasycart_meta_in_editor && function_exists( 'wp_easycart_meta_view_content' ) ) {
	wp_easycart_meta_view_content( $product ); /* 6.0.2: Meta ViewContent with event ID ( product_group for a product with variants ) */
}
?>

<?php
/* 6.0.2: product data for search engines and AI assistants ( wp_easycart_product_schema, printed once per product ). */
if ( class_exists( 'wp_easycart_product_schema' ) ) {
	wp_easycart_product_schema::print_for( $product );
}
?>

<?php
// 6.0.2: GA4 view_item once per product per page ( $GLOBALS['wpeasycart_ga4_view_item_printed'] ), never in the editor.
if ( ! isset( $GLOBALS['wpeasycart_ga4_view_item_printed'] ) || ! is_array( $GLOBALS['wpeasycart_ga4_view_item_printed'] ) ) {
	$GLOBALS['wpeasycart_ga4_view_item_printed'] = array();
}
if ( ! $wpeasycart_meta_in_editor && ! isset( $GLOBALS['wpeasycart_ga4_view_item_printed'][ (int) $product->product_id ] ) && '' !== (string) get_option( 'ec_option_google_ga4_property_id' ) ) {
	$GLOBALS['wpeasycart_ga4_view_item_printed'][ (int) $product->product_id ] = true;
	if ( get_option( 'ec_option_google_ga4_tag_manager' ) ) {
		echo '<script>
		document.addEventListener( \'DOMContentLoaded\', function() {
			dataLayer.push( { ecommerce: null } );
			dataLayer.push( {
				event: "view_item",
				ecommerce: {
					currency: "' . esc_attr( wp_easycart_base_currency_code() ) . '",
					value: ' . esc_attr( number_format( $product->price, 2, '.', '' ) ) . ',
					items: [ {
						item_id: "' . esc_attr( $product->model_number ) . '",
						item_name: "' . esc_attr( $product->title ) . '",
						index: 0,
						price: ' . esc_attr( number_format( $product->price, 2, '.', '' ) ) . ',
						item_brand: "' . esc_attr( $product->manufacturer_name ) . '",
						quantity: 1
					} ]
				}
			} );
		} );
		</script>';
	} else {
		echo '<script>
		document.addEventListener( \'DOMContentLoaded\', function() {
			gtag( "event", "view_item", {
				currency: "' . esc_attr( wp_easycart_base_currency_code() ) . '",
				value: ' . esc_attr( number_format( $product->price, 2, '.', '' ) ) . ',
				items: [ {
					item_id: "' . esc_attr( $product->model_number ) . '",
					item_name: "' . esc_attr( $product->title ) . '",
					index: 0,
					price: ' . esc_attr( number_format( $product->price, 2, '.', '' ) ) . ',
					item_brand: "' . esc_attr( $product->manufacturer_name ) . '",
					quantity: 1
				} ]
			} );
		} );
		</script>';
	}
}
