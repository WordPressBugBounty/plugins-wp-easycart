<?php if ( ! isset( $atts['enable_review_list'] ) || $atts['enable_review_list'] ) { ?>
	<?php if( count( $product->reviews ) > 0 ){ ?>
		<div class="ec_details_customer_reviews_left_ele"<?php if ( isset( $atts['enable_review_form'] ) && ! $atts['enable_review_form'] ) { echo ' style="width:100% !important;"'; } ?>>
			<?php if ( ! isset( $atts['enable_review_list_title'] ) || $atts['enable_review_list_title'] ) { ?>
				<h3 class="ec_details_customer_reviews_list_title"><?php echo count( $product->reviews ); ?> <?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_reviews_for_text' ); ?> <?php echo esc_attr( $product->title ); ?></h3>
			<?php } ?>
			<?php $perpage = apply_filters( 'wp_easycart_reviews_pagnation_perpage', 6 ); ?>
			<input type="hidden" id="ec_details_reviews_per_page_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $perpage ); ?>" />
			<ul class="ec_details_customer_review_list" data-product-id="<?php echo esc_attr( $product->product_id ); ?>">
				<?php foreach( $product->reviews as $review_row ){ 
					$review = new ec_review( $review_row ); ?>
				<li>
					<?php if ( ! isset( $atts['enable_review_item_title'] ) || $atts['enable_review_item_title'] ) { ?>
						<span class="ec_details_customer_review_title_ele"><?php echo esc_attr( wp_unslash( $review->title ) ); ?></span>
					<?php } ?>
					<?php if ( ! isset( $atts['enable_review_item_date'] ) || $atts['enable_review_item_date'] ) { ?>
						<span class="ec_details_customer_review_date_ele"><?php echo esc_attr( $review->review_date ); ?></span>
					<?php } ?>
					<?php if ( ! isset( $atts['enable_review_item_user_name'] ) || $atts['enable_review_item_user_name'] ) { ?>
						<span class="ec_details_customer_review_name_ele"><?php echo esc_attr( wp_unslash( $review->reviewer_name ) ); ?></span>
					<?php } ?>
					<?php if ( ! isset( $atts['enable_review_item_rating'] ) || $atts['enable_review_item_rating'] ) { ?>
						<span class="ec_details_customer_review_stars" title="Rated <?php echo esc_attr( $review->rating ); ?> of 5"><?php $review->display_review_stars( true ); ?></span>
					<?php } ?>
					<?php if ( ! isset( $atts['enable_review_item_review'] ) || $atts['enable_review_item_review'] ) { ?>
						<div class="ec_details_customer_review_data_ele"><?php echo wp_easycart_escape_html( nl2br( wp_unslash( $review->description ) ) ); ?></div>
					<?php }?>
				</li>
				<?php } ?>
			</ul>
		</div>
	<?php }else{ ?>
		<div class="ec_details_customer_reviews_left"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_review_no_reviews' ); ?></div>
	<?php } ?>
<?php } ?>
<?php if ( ! isset( $atts['enable_review_form'] ) || $atts['enable_review_form'] ) { ?>
	<?php if( !get_option( 'ec_option_customer_review_require_login' ) || ( $GLOBALS['ec_cart_data']->cart_data->user_id != "" && $GLOBALS['ec_cart_data']->cart_data->user_id != 0 ) ){ ?>
	<div class="ec_details_customer_reviews_form_ele"<?php if ( isset( $atts['enable_review_list'] ) && ! $atts['enable_review_list'] ) { echo ' style="width:100% !important; margin-left:0px !important;"'; } ?>>
		<div class="ec_details_customer_reviews_form_holder">
			<div class="ec_details_customer_review_loader_holder" id="ec_details_customer_review_loader_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
				<div class="ec_details_customer_review_loader"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_submitting_review' ); ?></div>
			</div>
			<div class="ec_details_customer_review_success_holder" id="ec_details_customer_review_success_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
				<div class="ec_details_customer_review_success"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_review_submitted' ); ?></div>
			</div>
			<?php if ( ! isset( $atts['enable_review_form_title'] ) || $atts['enable_review_form_title'] ) { ?>
				<div class="ec_details_customer_review_form_title_ele"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_add_a_review_for' ); ?> <?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?></div>
			<?php } ?>
			<div class="ec_details_option_row_error" id="ec_details_review_error_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
				<span class=""><?php echo wp_easycart_language( )->get_text( 'customer_review', 'review_error' ); ?></span>
			</div>
			<div class="ec_details_customer_reviews_row ec_details_customer_reviews_label_ele">
				<?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_title' ); ?>
			</div>
			<div class="ec_details_customer_reviews_row ec_lower_space">
				<input type="text" id="ec_review_title_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" />
			</div>
			<div class="ec_details_customer_reviews_row ec_details_customer_reviews_label_ele">
				<?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_rating' ); ?>
			</div>
			<div class="ec_details_customer_reviews_row ec_stars" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
				<div class="ec_product_details_star_off_ele ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="1" id="ec_details_review_star1_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
				<div class="ec_product_details_star_off_ele ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="2" id="ec_details_review_star2_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
				<div class="ec_product_details_star_off_ele ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="3" id="ec_details_review_star3_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
				<div class="ec_product_details_star_off_ele ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="4" id="ec_details_review_star4_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
				<div class="ec_product_details_star_off_ele ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="5" id="ec_details_review_star5_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
			</div>
			<div class="ec_details_customer_reviews_row ec_details_customer_reviews_label_ele">
				<?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_message' ); ?>
			</div>
			<div class="ec_details_customer_reviews_row ec_lower_space">
				<textarea id="ec_review_message_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></textarea>
			</div>
			<div class="ec_details_customer_reviews_row ec_details_submit_review_button_row" id="ec_details_submit_review_button_row_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
				<input class="ec_details_customer_reviews_button_ele" type="button" value="<?php echo esc_attr( ( isset( $atts['form_button_text'] ) ) ? $atts['form_button_text'] : wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_submit' ) ); ?>" onclick="ec_submit_product_review( <?php echo esc_attr( $product->product_id ); ?>, <?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>, '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-insert-customer-review-' . (int) $product->product_id ) ); ?>' )" />
			</div>
			<div class="ec_details_customer_reviews_row ec_details_review_submitted_button_row" id="ec_details_review_submitted_button_row_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
				<input class="ec_details_customer_reviews_button_submitted_ele" type="button" disabled="disabled" value="<?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_review_submitted_button' ); ?>" />
			</div>
		</div>
	</div>
	<?php }else{ ?>
	<div class="ec_details_customer_reviews_form">
		<div class="ec_details_customer_reviews_form_holder">
			<div class="ec_details_customer_reviews_form_login_note"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_review_log_in_first' ); ?></div>
		</div> 
	</div>
	<?php }?>
<?php }?>
