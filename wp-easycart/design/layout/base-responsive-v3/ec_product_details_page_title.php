<<?php echo esc_attr( ( ( in_array( $atts['title_element'], array( 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) ) ? $atts['title_element'] : 'h4' ) ); ?> class="ec_details_title_ele ec_details_title_ele_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_escape_html( $product->title ); ?></<?php echo esc_attr( ( ( in_array( $atts['title_element'], array( 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) ) ? $atts['title_element'] : 'h4' ) ); ?>>