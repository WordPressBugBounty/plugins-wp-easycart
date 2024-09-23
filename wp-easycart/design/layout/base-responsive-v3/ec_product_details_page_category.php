<?php if( count( $product->categoryitems ) > 0 ){ ?>
<<?php echo esc_attr( ( ( in_array( $atts['categories_element'], array( 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) ) ? $atts['categories_element'] : 'h4' ) ); ?> class="ec_details_categories_ele ec_details_categories_ele_<?php echo esc_attr( (int) $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
	<span class="ec_category_label"><?php echo esc_attr( $categories_label ); ?></span>
	<?php $categoryitems = array( );
	$is_first_categoryitem = true;
	foreach( $product->categoryitems as $categoryitem ){
		if ( ! $is_first_categoryitem ) { ?>
			<span class="ec_category_divider"><?php echo esc_attr( $categories_divider ); ?></span>
		<?php } ?>
		<a href="<?php echo esc_attr( $product->get_category_link( $categoryitem->post_id, $categoryitem->category_id ) ); ?>" class="ec_category_link"><?php echo wp_easycart_language( )->convert_text( $categoryitem->category_name ); // XSS OK ?></a>
		<?php $is_first_categoryitem = false;
	} ?>
</<?php echo esc_attr( ( ( in_array( $atts['categories_element'], array( 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) ) ? $atts['categories_element'] : 'h4' ) ); ?>>
<?php }?>
