<<?php echo esc_attr( ( ( in_array( $atts['breadcrumb_element'], array( 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) ) ? $atts['breadcrumb_element'] : 'h4' ) ); ?> class="ec_details_breadcrumbs_ele ec_details_breadcrumbs_ele_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
	<a href="<?php echo esc_attr( home_url( ) ); ?>" class="ec_breadcrumbs_link"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_home_link' ); ?></a>
	<span class="ec_breadcrumbs_divider"><?php echo esc_attr( ( ( isset( $atts['divider_character'] ) ) ? $atts['divider_character'] : '/' ) ); ?></span>
	<a href="<?php echo esc_attr( $store_page ); ?>" class="ec_breadcrumbs_link"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_store_link' ); ?></a> <?php if( $product->menuitems[0]->menulevel1_1_name ){ ?>
	<span class="ec_breadcrumbs_divider"><?php echo esc_attr( ( ( isset( $atts['divider_character'] ) ) ? $atts['divider_character'] : '/' ) ); ?></span>
	<a href="<?php if( !get_option( 'ec_option_use_old_linking_style' ) && $product->post_id != "0" ){ 
		echo esc_attr( get_permalink( $product->menuitems[0]->menulevel1_1_post_id ) ); 
	}else{ 
		echo esc_attr( $store_page ) . esc_attr( $permalink_divider ) . "menuid=" . esc_attr( $product->menuitems[0]->menulevel1_1_menu_id );
	} ?>" class="ec_breadcrumbs_link"><?php echo wp_easycart_language( )->convert_text( $product->menuitems[0]->menulevel1_1_name ); ?></a>
	<?php if( $product->menuitems[0]->menulevel2_1_name ){ ?>
	<span class="ec_breadcrumbs_divider"><?php echo esc_attr( ( ( isset( $atts['divider_character'] ) ) ? $atts['divider_character'] : '/' ) ); ?></span> 
	<a href="<?php if( !get_option( 'ec_option_use_old_linking_style' ) && $product->post_id != "0" ){ 
		echo esc_attr( get_permalink( $product->menuitems[0]->menulevel2_1_post_id ) );
	}else{ 
		echo esc_attr( $store_page ) . esc_attr( $permalink_divider ) . "submenuid=" . esc_attr( $product->menuitems[0]->menulevel2_1_menu_id );
	} ?>" class="ec_breadcrumbs_link"><?php echo wp_easycart_language( )->convert_text( $product->menuitems[0]->menulevel2_1_name ); ?></a>
	<?php if( $product->menuitems[0]->menulevel3_1_name ){ ?>
	<span class="ec_breadcrumbs_divider"><?php echo esc_attr( ( ( isset( $atts['divider_character'] ) ) ? $atts['divider_character'] : '/' ) ); ?></span>
	<a href="<?php if( !get_option( 'ec_option_use_old_linking_style' ) && $product->post_id != "0" ){ 
		echo esc_attr( get_permalink( $product->menuitems[0]->menulevel3_1_post_id ) );
	}else{
		echo esc_attr( $store_page ) . esc_attr( $permalink_divider ) . "subsubmenuid=" . esc_attr( $product->menuitems[0]->menulevel3_1_menu_id ); 
	} ?>" class="ec_breadcrumbs_link"><?php echo wp_easycart_language( )->convert_text( $product->menuitems[0]->menulevel3_1_name ); ?></a><?php } } }?>
	<span class="ec_breadcrumbs_divider"><?php echo esc_attr( ( ( isset( $atts['divider_character'] ) ) ? $atts['divider_character'] : '/' ) ); ?></span>
	<span class="ec_breadcrumbs_item"><?php echo esc_attr( $product->title ); ?></span>
</<?php echo esc_attr( ( ( in_array( $atts['breadcrumb_element'], array( 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) ) ? $atts['breadcrumb_element'] : 'h4' ) ); ?>>
