<?php if ( $this_product->featured_products->product1 || $this_product->featured_products->product2 || $this_product->featured_products->product3 || $this_product->featured_products->product4 ){ ?>
<ul class="ec_details_related_products">
	<?php if ( ( ! isset( $atts['enable_product1'] ) || $atts['enable_product1'] ) && $this_product->featured_products->product1 ) {
		$product = $this_product->featured_products->product1;
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_featured_product.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option('ec_option_base_layout') . '/ec_product_details_page_featured_product.php' );
		} else if ( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_featured_product.php' ) ) {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option('ec_option_latest_layout') . '/ec_product_details_page_featured_product.php' );
		}
	}
	if ( ( ! isset( $atts['enable_product2'] ) || $atts['enable_product2'] ) && $this_product->featured_products->product2 ) {
		$product = $this_product->featured_products->product2;
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_featured_product.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option('ec_option_base_layout') . '/ec_product_details_page_featured_product.php' );
		} else if ( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_featured_product.php' ) ) {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option('ec_option_latest_layout') . '/ec_product_details_page_featured_product.php' );
		}
	}
	if ( ( ! isset( $atts['enable_product3'] ) || $atts['enable_product3'] ) && $this_product->featured_products->product3 ) {
		$product = $this_product->featured_products->product3;
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_featured_product.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option('ec_option_base_layout') . '/ec_product_details_page_featured_product.php' );
		} else if( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_featured_product.php' ) ) {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option('ec_option_latest_layout') . '/ec_product_details_page_featured_product.php' );
		}
	}
	if ( ( ! isset( $atts['enable_product4'] ) || $atts['enable_product4'] ) && $this_product->featured_products->product4 ) {
		$product = $this_product->featured_products->product4;
		if ( file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/ec_product_details_page_featured_product.php' ) ) {
			include( EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option('ec_option_base_layout') . '/ec_product_details_page_featured_product.php' );
		} else if ( file_exists( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/ec_product_details_page_featured_product.php' ) ) {
			include( EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option('ec_option_latest_layout') . '/ec_product_details_page_featured_product.php' );
		}
	} ?>
</ul>
<?php } ?>
