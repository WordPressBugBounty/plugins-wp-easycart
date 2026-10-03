<div class="ec_search_widget">
<form action="<?php echo esc_attr( $store_page ); ?>" method="GET"><?php if ( function_exists( 'wp_easycart_print_form_query_inputs' ) ) { wp_easycart_print_form_query_inputs( $store_page ); /* 6.0.2: plain permalinks / WPML ?lang= */ } ?>
	<input type="text" name="ec_search" class="ec_search_input"<?php if( get_option( 'ec_option_use_live_search' ) ){ ?> onkeyup="ec_live_search_update( '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-live-search' ) ); ?>' );"<?php }?> list="ec_search_suggestions" />
    <datalist id="ec_search_suggestions">
    </datalist>
	<input type="submit" value="<?php echo esc_attr( $label ); ?>" />
</form>
</div>