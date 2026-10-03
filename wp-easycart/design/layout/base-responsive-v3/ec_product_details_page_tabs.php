<?php /* 6.0.2: an extension may draw this area itself ( see wp_easycart_product_tabs_takeover() ). */ $wpec_tabs_takeover = function_exists( 'wp_easycart_product_tabs_takeover' ) && wp_easycart_product_tabs_takeover( $product, $wpeasycart_addtocart_shortcode_rand, isset( $atts ) ? $atts : array() ); $wpec_tab_panels = array(); ?>
<div class="ec_details_extra_area ec_details_extra_area_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?><?php echo $wpec_tabs_takeover ? ' ec_details_extra_area_custom' : ''; ?>">

		<?php if ( $wpec_tabs_takeover ) {
			$wpec_tab_panels['addon_tabs'] = wp_easycart_product_tabs_capture_gaps( $product->product_id, $wpeasycart_addtocart_shortcode_rand );
		} else { ?>
		<ul class="ec_details_tabs" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">

			<?php do_action( 'wpeasycart_pre_description_tab', $product->product_id, $wpeasycart_addtocart_shortcode_rand ); ?>
			<?php if( ( isset( $atts['show_description'] ) && $atts['show_description'] ) || ( ! isset( $atts['show_description'] ) ) ){ ?>
			<li class="ec_details_tab ec_details_tab_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> <?php echo esc_attr( apply_filters( 'wpeasycart_description_initally_active', 'ec_active', $product->product_id ) ); ?> ec_description"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_description' ); ?></li>
			<?php }?>

			<?php do_action( 'wpeasycart_pre_specifications_tab', $product->product_id, $wpeasycart_addtocart_shortcode_rand ); ?>
			<?php if( ( isset( $atts['show_specifications'] ) && $atts['show_specifications'] ) || ( ! isset( $atts['show_specifications'] ) && $product->use_specifications ) ){ ?>
			<li class="ec_details_tab ec_details_tab_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> ec_specifications<?php if( isset( $atts['show_description'] ) && ! $atts['show_description'] ){ echo ' ec_active'; } ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_specifications' ); ?></li>
			<?php }?>

			<?php do_action( 'wpeasycart_pre_customer_reviews_tab', $product->product_id, $wpeasycart_addtocart_shortcode_rand ); ?>
			<?php if( ( isset( $atts['show_customer_reviews'] ) && $atts['show_customer_reviews'] ) || ( ! isset( $atts['show_customer_reviews'] ) && $product->use_customer_reviews ) ){ ?>
			<li class="ec_details_tab ec_details_tab_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> ec_customer_reviews<?php if( isset( $atts['show_description'] ) && ! $atts['show_description'] && isset( $atts['show_specifications'] ) && ! $atts['show_specifications'] ){ echo ' ec_active'; } ?>"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_customer_reviews' ); ?> (<?php echo count( $product->reviews ); ?>)</li>
			<?php } ?>

			<?php do_action( 'wpeasycart_addon_product_details_tab', $product->product_id, $wpeasycart_addtocart_shortcode_rand ); ?>

		</ul>
		<?php } ?>

		<?php if( ( isset( $atts['show_description'] ) && $atts['show_description'] ) || ( ! isset( $atts['show_description'] ) ) ){ ?>
		<?php if ( $wpec_tabs_takeover ) { ob_start(); } ?>
		<div class="ec_details_description_tab ec_details_description_tab_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php echo wp_easycart_product_tab_panel_attributes( apply_filters( 'wpeasycart_description_content_initally_active', '', $product->product_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup: '' or ' style="display:none;"'. ?>>

			<div class="ec_details_description_content ec_details_description_content_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
				<?php
					if( substr( $product->description, 0, 3 ) == "[ec" ){
						$product->display_product_description( );
					}else{
						$content = do_shortcode( stripslashes( $product->description ) );
						$content = str_replace( ']]>', ']]&gt;', $content );
						echo wp_easycart_escape_html( $content ); // XSS OK.
					}
				?>
			</div>

		</div>
		<?php if ( $wpec_tabs_takeover ) { $wpec_tab_panels['description'] = wp_easycart_product_tabs_panel( 'description', ob_get_clean(), $product ); } ?>
		<?php } ?>

		<?php if( ( isset( $atts['show_specifications'] ) && $atts['show_specifications'] ) || ( ! isset( $atts['show_specifications'] ) && $product->use_specifications ) ){ ?>
		<?php if ( $wpec_tabs_takeover ) { ob_start(); } ?>
		<div class="ec_details_specifications_tab ec_details_specifications_tab_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php if( isset( $atts['show_description'] ) && ! $atts['show_description'] ){ echo ' style="display:block;"'; } ?>>

			<div class="ec_details_specifications_content ec_details_specifications_content_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
				<?php
					if( isset( $product->specifications ) && '' != $product->specifications && substr( $product->specifications, 0, 3 ) == "[ec" ){
						$product->display_product_specifications( );
					}else{
						$content = ( isset( $product->specifications ) && '' != $product->specifications ) ? do_shortcode( stripslashes( $product->specifications ) ) : '';
						$content = stripslashes( str_replace( ']]>', ']]&gt;', $content ) );
						echo wp_easycart_escape_html( $content ); // XSS OK.
					}
				?>
			</div>

		</div>
		<?php if ( $wpec_tabs_takeover ) { $wpec_tab_panels['specifications'] = wp_easycart_product_tabs_panel( 'specifications', ob_get_clean(), $product ); } ?>
		<?php }?>

		<?php 
		/* START CUSTOMER REVIEW AREA */
		if( ( isset( $atts['show_customer_reviews'] ) && $atts['show_customer_reviews'] ) || ( ! isset( $atts['show_customer_reviews'] ) && $product->use_customer_reviews ) ){ ?>
		<?php if ( $wpec_tabs_takeover ) { ob_start(); } ?>
		<div class="ec_details_customer_reviews_tab ec_details_customer_reviews_tab_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php if( isset( $atts['show_description'] ) && ! $atts['show_description'] && isset( $atts['show_specifications'] ) && ! $atts['show_specifications'] ){ echo ' style="display:block;"'; } ?>>
			<?php
			/* Native reviews: rating summary + distribution ( ec_product_details_reviews_summary.php, a wp-easycart-data copy first ) */
			if ( class_exists( 'ec_reviews' ) && method_exists( 'ec_reviews', 'print_summary' ) ) {
				ec_reviews::print_summary( $product );
			}
			?>
			<?php if( count( $product->reviews ) > 0 ){ ?>
			<div class="ec_details_customer_reviews_left">
				<h3><?php echo count( $product->reviews ); ?> <?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_reviews_for_text' ); ?> <?php echo esc_attr( $product->title ); ?></h3>
				<?php $perpage = apply_filters( 'wp_easycart_reviews_pagnation_perpage', 6 ); ?>
				<input type="hidden" id="ec_details_reviews_per_page_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $perpage ); ?>" />
				<ul class="ec_details_customer_review_list" data-product-id="<?php echo esc_attr( $product->product_id ); ?>">
					<?php foreach( $product->reviews as $review_row ){ 
					$review = new ec_review( $review_row );
					?>
					<li data-rating="<?php echo (int) $review->rating; ?>"<?php echo $review->verified ? ' class="ec_review_is_verified"' : ''; ?>>
						<div>
							<span class="ec_details_customer_review_date"><strong><?php echo esc_attr( wp_unslash( $review->title ) ); ?></strong> - <?php echo esc_attr( $review->review_date ); ?></span>
							<?php if( get_option( 'ec_option_customer_review_show_user_name' ) ){ ?><span class="ec_details_customer_review_name"><?php echo esc_attr( wp_unslash( $review->reviewer_name ) ); ?></span><?php }?>
							<?php $review->display_verified_badge(); ?>
							<span class="ec_details_customer_review_stars" title="Rated <?php echo esc_attr( $review->rating ); ?> of 5"><?php $review->display_review_stars( ); ?></span>
						</div>
						<div class="ec_details_customer_review_data"><?php echo wp_easycart_escape_html( nl2br( wp_unslash( $review->description ) ) ); ?></div>
						<?php $review->display_reply(); ?>
					</li>
					<?php } ?>
				</ul>
			</div>
			<?php }else{ ?>
			<div class="ec_details_customer_reviews_left"><?php echo wp_easycart_language( )->get_text( 'product_details', 'product_details_review_no_reviews' ); ?></div>
			<?php } ?>
			<?php if( !get_option( 'ec_option_customer_review_require_login' ) || ( $GLOBALS['ec_cart_data']->cart_data->user_id != "" && $GLOBALS['ec_cart_data']->cart_data->user_id != 0 ) ){ ?>
			<div class="ec_details_customer_reviews_form">
				<div class="ec_details_customer_reviews_form_holder">
					<div class="ec_details_customer_review_loader_holder" id="ec_details_customer_review_loader_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
						<div class="ec_details_customer_review_loader"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_submitting_review' ); ?></div>
					</div>
					<div class="ec_details_customer_review_success_holder" id="ec_details_customer_review_success_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
						<div class="ec_details_customer_review_success"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_review_submitted' ); ?></div>
					</div>
					<h3><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_add_a_review_for' ); ?> <?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?></h3>
					<?php
					/* Native reviews: arrived from a review-request email → confirm the order and pre-select the star they tapped */
					$wpeasycart_review_request = class_exists( 'ec_reviews' ) ? ec_reviews::current_request( $product->product_id ) : null;
					$wpeasycart_review_notice  = class_exists( 'ec_reviews' ) ? ec_reviews::link_notice() : '';
					?>
					<?php if ( ! $wpeasycart_review_request && '' !== $wpeasycart_review_notice ) { ?>
					<div class="ec_review_request_notice"><?php echo esc_html( $wpeasycart_review_notice ); ?></div>
					<?php } ?>
					<?php if ( $wpeasycart_review_request ) { ?>
					<div class="ec_review_request_banner" data-prefill-rating="<?php echo (int) ec_reviews::prefill_rating(); ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
						<span class="ec_review_verified">&#10003; <?php echo esc_html__( 'Verified buyer', 'wp-easycart' ); ?></span>
						<?php
						/* translators: %d: order number. */
						echo esc_html( sprintf( __( 'Thanks for your order #%d — your review will show as a verified purchase.', 'wp-easycart' ), (int) $wpeasycart_review_request->order_id ) );
						?>
					</div>
					<input type="hidden" class="ec_review_request_token" id="ec_review_request_token_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" value="<?php echo esc_attr( $wpeasycart_review_request->token ); ?>" />
					<?php } ?>
					<div class="ec_details_option_row_error" id="ec_details_review_error_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'review_error' ); ?></div>
					<?php /* 6.0.2: shown when the store could not save the review ( ec_submit_product_review() ). */ $wpec_review_save_error = wp_easycart_language( )->get_text( 'customer_review', 'customer_review_save_error' ); ?><div class="ec_details_option_row_error" id="ec_details_review_save_error_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><?php echo ( '' != $wpec_review_save_error ) ? $wpec_review_save_error : esc_html__( 'Your review could not be saved. Please refresh the page and try again.', 'wp-easycart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the language class escapes its text. ?></div>
					<div class="ec_details_customer_reviews_row"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_title' ); ?></div>
					<div class="ec_details_customer_reviews_row ec_lower_space"><input type="text" id="ec_review_title_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" /></div>
					<div class="ec_details_customer_reviews_row"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_rating' ); ?></div>
					<div class="ec_details_customer_reviews_row ec_stars" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
						<div class="ec_product_details_star_off ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="1" id="ec_details_review_star1_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
						<div class="ec_product_details_star_off ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="2" id="ec_details_review_star2_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
						<div class="ec_product_details_star_off ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="3" id="ec_details_review_star3_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
						<div class="ec_product_details_star_off ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="4" id="ec_details_review_star4_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
						<div class="ec_product_details_star_off ec_details_review_input ec_details_review_input_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" data-review-score="5" id="ec_details_review_star5_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></div>
					</div>
					<div class="ec_details_customer_reviews_row"><?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_message' ); ?></div>
					<div class="ec_details_customer_reviews_row ec_lower_space"><textarea id="ec_review_message_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"></textarea></div>
					<div class="ec_details_customer_reviews_row ec_details_submit_review_button_row" id="ec_details_submit_review_button_row_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><input type="button" value="<?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_your_review_submit' ); ?>" onclick="ec_submit_product_review( <?php echo esc_attr( $product->product_id ); ?>, <?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>, '<?php echo esc_attr( wp_create_nonce( 'wp-easycart-insert-customer-review-' . (int) $product->product_id ) ); ?>' )" /></div>
					<div class="ec_details_customer_reviews_row ec_details_review_submitted_button_row" id="ec_details_review_submitted_button_row_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><input type="button" disabled="disabled" value="<?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_review_submitted_button' ); ?>" /></div>
				</div>
			</div>
			<?php }else{ ?>
			<div class="ec_details_customer_reviews_form">
				<div class="ec_details_customer_reviews_form_holder">
					<?php echo wp_easycart_language( )->get_text( 'customer_review', 'product_details_review_log_in_first' ); ?>
				</div> 
			</div>
			<?php }?>
		</div>
		<?php if ( $wpec_tabs_takeover ) { $wpec_tab_panels['reviews'] = wp_easycart_product_tabs_panel( 'reviews', ob_get_clean(), $product ); } ?>
		<?php }?>

		<?php if ( $wpec_tabs_takeover ) {
			wp_easycart_product_tabs_area( $product, $wpeasycart_addtocart_shortcode_rand, $wpec_tab_panels, isset( $atts ) ? $atts : array() );
		} else {
			do_action( 'wpeasycart_addon_product_details_tab_content', $product->product_id, $wpeasycart_addtocart_shortcode_rand );
		} ?>

	</div>