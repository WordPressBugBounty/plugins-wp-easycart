<?php
/**
 * Product reviews block ( module product-info, 6.0.2 ): rating summary with a filter by stars, the reviews ( verified buyer
 * badge, store replies ) and the review form ( review-request banner and star, "link expired / already reviewed" note ).
 *
 * Included by WP_EasyCart_Product_Info::reviews() with $wpeasycart_pi_product ( ec_product ) and $wpeasycart_pi_args. The form keeps the
 * element IDs ec_submit_product_review() in ec-store.js reads, so a review goes through the store's own request
 * ( ec_ajax_insert_customer_review ); product-info.js turns the star radios into the classes that function counts.
 *
 * Round 11 arguments: sort ( newest | oldest | highest | lowest ), per_page ( reviews shown at first; the rest carry
 * wpec-pi-review--more and wait for "Show more reviews", which without the script reloads with ?wpec_pi_reviews=all ),
 * more_text, avatars + avatar_size ( Gravatar, only where names show ), form_guests ( false: no form for signed-out shoppers ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpeasycart_pi_pid     = (int) $wpeasycart_pi_product->product_id;
$wpeasycart_pi_rand    = (int) $wpeasycart_pi_args['rand'];
$wpeasycart_pi_suffix  = $wpeasycart_pi_pid . '_' . $wpeasycart_pi_rand;
$wpeasycart_pi_rating  = WP_EasyCart_Product_Info::rating( $wpeasycart_pi_product );
$wpeasycart_pi_count   = (int) $wpeasycart_pi_rating['count'];
$wpeasycart_pi_title   = WP_EasyCart_Product_Info::title( $wpeasycart_pi_product );
$wpeasycart_pi_names   = ( 'show' === $wpeasycart_pi_args['item_name'] || ( 'store' === $wpeasycart_pi_args['item_name'] && get_option( 'ec_option_customer_review_show_user_name' ) ) );
$wpeasycart_pi_summary = ( $wpeasycart_pi_args['summary'] && $wpeasycart_pi_count > 0 );
$wpeasycart_pi_list    = (bool) $wpeasycart_pi_args['list'];
/* 6.0.2 round 11: the form's "Show to shoppers who are not signed in" off leaves the form ( and its sign-in note ) out for them. */
$wpeasycart_pi_form     = (bool) $wpeasycart_pi_args['form'] && ( ! empty( $wpeasycart_pi_args['form_guests'] ) || WP_EasyCart_Product_Info::signed_in() );
$wpeasycart_pi_rows     = WP_EasyCart_Product_Info::sort_reviews( $wpeasycart_pi_product->reviews, (string) $wpeasycart_pi_args['sort'] );
$wpeasycart_pi_per_page = max( 0, (int) $wpeasycart_pi_args['per_page'] );
/* Every review shows for a shopper who asked for all of them ( the "Show more" link without the script ). */
$wpeasycart_pi_all      = isset( $_GET['wpec_pi_reviews'] ) && 'all' === sanitize_key( wp_unslash( $_GET['wpec_pi_reviews'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: show every review.
$wpeasycart_pi_paged    = ( $wpeasycart_pi_list && $wpeasycart_pi_per_page > 0 && count( $wpeasycart_pi_rows ) > $wpeasycart_pi_per_page && ! $wpeasycart_pi_all );
$wpeasycart_pi_emails   = ( $wpeasycart_pi_list && $wpeasycart_pi_args['avatars'] && $wpeasycart_pi_names && function_exists( 'get_avatar' ) ) ? WP_EasyCart_Product_Info::review_emails( $wpeasycart_pi_rows ) : array();
$wpeasycart_pi_classes  = 'wpec-el wpec-pi-reviews' . ( ( $wpeasycart_pi_list && $wpeasycart_pi_form ) ? '' : ' wpec-pi-reviews--single' ) . ( $wpeasycart_pi_paged ? ' wpec-pi-reviews--paged' : '' ) . ( '' !== $wpeasycart_pi_args['class'] ? ' ' . $wpeasycart_pi_args['class'] : '' );
$wpeasycart_pi_showing  = WP_EasyCart_Product_Info::text( 'reviews_filter_showing', __( 'Showing [rating]-star reviews.', 'wp-easycart' ) );
$wpeasycart_pi_clear    = WP_EasyCart_Product_Info::text( 'reviews_filter_clear', __( 'Show all reviews', 'wp-easycart' ) );
$wpeasycart_pi_filtered = ( $wpeasycart_pi_summary && $wpeasycart_pi_list );
?>
<div class="<?php echo esc_attr( $wpeasycart_pi_classes ); ?>" id="wpec-pi-reviews-<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>" data-wpec-pi-reviews="<?php echo esc_attr( $wpeasycart_pi_pid ); ?>" data-product-id="<?php echo esc_attr( $wpeasycart_pi_pid ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_pi_rand ); ?>" data-wpec-pi-showing="<?php echo esc_attr( $wpeasycart_pi_showing ); ?>"<?php echo $wpeasycart_pi_paged ? ' data-wpec-pi-per-page="' . esc_attr( $wpeasycart_pi_per_page ) . '" data-wpec-pi-shown="' . esc_attr( WP_EasyCart_Product_Info::text( 'reviews_shown', __( 'Showing [shown] of [total] reviews.', 'wp-easycart' ) ) ) . '"' : ''; ?>>
	<?php if ( $wpeasycart_pi_summary ) { ?>
	<div class="wpec-pi-reviews__summary">
		<div class="wpec-pi-reviews__average">
			<span class="wpec-pi-reviews__score" aria-hidden="true"><?php echo esc_html( number_format_i18n( $wpeasycart_pi_rating['average'], 1 ) ); ?></span>
			<?php echo WP_EasyCart_Product_Info::stars( $wpeasycart_pi_rating['average'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built with esc_attr() in stars(). ?>
			<span class="wpec-pi-reviews__based">
			<?php
			if ( 1 === $wpeasycart_pi_count ) {
				echo esc_html( WP_EasyCart_Product_Info::text( 'reviews_based_on_one', __( 'Based on 1 review', 'wp-easycart' ) ) );
			} else {
				echo esc_html( str_replace( '[count]', number_format_i18n( $wpeasycart_pi_count ), WP_EasyCart_Product_Info::text( 'reviews_based_on_many', __( 'Based on [count] reviews', 'wp-easycart' ) ) ) );
			}
			?>
			</span>
		</div>
		<div class="wpec-pi-reviews__bars"<?php echo $wpeasycart_pi_filtered ? ' role="group" aria-label="' . esc_attr( WP_EasyCart_Product_Info::text( 'reviews_filter_group', __( 'Filter reviews by rating', 'wp-easycart' ) ) ) . '"' : ''; ?>>
			<?php
			for ( $wpeasycart_pi_star = 5; $wpeasycart_pi_star >= 1; $wpeasycart_pi_star-- ) {
				$wpeasycart_pi_n     = (int) $wpeasycart_pi_rating['dist'][ $wpeasycart_pi_star ];
				$wpeasycart_pi_pct   = $wpeasycart_pi_count ? round( 100 * $wpeasycart_pi_n / $wpeasycart_pi_count ) : 0;
				$wpeasycart_pi_inner = '<span class="wpec-pi-reviews__bar-label">' . esc_html( number_format_i18n( $wpeasycart_pi_star ) ) . '<span class="wpec-pi-reviews__bar-star" aria-hidden="true"></span></span>'
					. '<span class="wpec-pi-reviews__bar-track" aria-hidden="true"><span class="wpec-pi-reviews__bar-fill" style="width:' . esc_attr( (int) $wpeasycart_pi_pct ) . '%"></span></span>'
					. '<span class="wpec-pi-reviews__bar-count">' . esc_html( number_format_i18n( $wpeasycart_pi_n ) ) . '</span>';
				if ( $wpeasycart_pi_filtered ) {
					$wpeasycart_pi_bar_label = str_replace( array( '[rating]', '[count]' ), array( number_format_i18n( $wpeasycart_pi_star ), number_format_i18n( $wpeasycart_pi_n ) ), WP_EasyCart_Product_Info::text( 'reviews_filter_button', __( 'Show [rating]-star reviews ([count])', 'wp-easycart' ) ) );
					echo '<button type="button" class="wpec-pi-reviews__bar" data-rating="' . esc_attr( $wpeasycart_pi_star ) . '" aria-pressed="false" aria-label="' . esc_attr( $wpeasycart_pi_bar_label ) . '"' . ( $wpeasycart_pi_n ? '' : ' disabled' ) . '>' . $wpeasycart_pi_inner . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wpeasycart_pi_inner is escaped above.
				} else {
					echo '<div class="wpec-pi-reviews__bar">' . $wpeasycart_pi_inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wpeasycart_pi_inner is escaped above.
				}
			}
			?>
		</div>
	</div>
	<?php } ?>
	<?php if ( $wpeasycart_pi_list || $wpeasycart_pi_form ) { ?>
	<div class="wpec-pi-reviews__body">
		<?php if ( $wpeasycart_pi_list ) { ?>
		<div class="wpec-pi-reviews__list-wrap">
			<?php if ( $wpeasycart_pi_count > 0 ) { ?>
				<?php if ( $wpeasycart_pi_args['list_heading'] ) { ?>
				<h3 class="wpec-pi-reviews__heading"><?php echo esc_html( number_format_i18n( $wpeasycart_pi_count ) . ' ' . WP_EasyCart_Product_Info::text( 'product_details_reviews_for_text', __( 'Reviews for', 'wp-easycart' ), 'product_details' ) . ' ' . $wpeasycart_pi_title ); ?></h3>
				<?php } ?>
				<?php if ( $wpeasycart_pi_filtered ) { ?>
				<div class="wpec-pi-reviews__filter" hidden>
					<span class="wpec-pi-reviews__filter-text"></span>
					<button type="button" class="wpec-pi-reviews__filter-clear"><?php echo esc_html( $wpeasycart_pi_clear ); ?></button>
				</div>
				<?php } ?>
				<?php if ( $wpeasycart_pi_filtered || $wpeasycart_pi_paged ) { ?>
				<span class="wpec-el-sr-only wpec-pi-reviews__live" role="status" aria-live="polite"></span>
				<?php } ?>
				<ul class="wpec-pi-reviews__list">
					<?php
					$wpeasycart_pi_index = 0;
					foreach ( $wpeasycart_pi_rows as $wpeasycart_pi_row ) {
						$wpeasycart_pi_review = new ec_review( $wpeasycart_pi_row );
						$wpeasycart_pi_stars  = max( 0, min( 5, (int) $wpeasycart_pi_review->rating ) );
						$wpeasycart_pi_date   = WP_EasyCart_Product_Info::review_date( $wpeasycart_pi_review );
						$wpeasycart_pi_rtitle = trim( (string) wp_unslash( $wpeasycart_pi_review->title ) );
						$wpeasycart_pi_email  = isset( $wpeasycart_pi_emails[ (int) $wpeasycart_pi_review->review_id ] ) ? $wpeasycart_pi_emails[ (int) $wpeasycart_pi_review->review_id ] : '';
						$wpeasycart_pi_avatar = ( '' !== $wpeasycart_pi_email ) ? get_avatar( $wpeasycart_pi_email, max( 16, min( 192, (int) $wpeasycart_pi_args['avatar_size'] ) ), '', '', array( 'class' => 'wpec-pi-review__avatar-img' ) ) : '';
						$wpeasycart_pi_later  = ( $wpeasycart_pi_paged && $wpeasycart_pi_index >= $wpeasycart_pi_per_page );
						++$wpeasycart_pi_index;
						?>
					<li class="wpec-pi-review<?php echo $wpeasycart_pi_review->verified ? ' wpec-pi-review--verified' : ''; ?><?php echo $wpeasycart_pi_later ? ' wpec-pi-review--more' : ''; ?><?php echo ( '' !== (string) $wpeasycart_pi_avatar ) ? ' wpec-pi-review--avatar' : ''; ?>" data-rating="<?php echo esc_attr( $wpeasycart_pi_stars ); ?>">
						<?php if ( $wpeasycart_pi_args['item_rating'] || ( $wpeasycart_pi_args['item_title'] && '' !== $wpeasycart_pi_rtitle ) ) { ?>
						<div class="wpec-pi-review__head">
							<?php
							if ( $wpeasycart_pi_args['item_rating'] ) {
								echo WP_EasyCart_Product_Info::stars( $wpeasycart_pi_stars ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built with esc_attr() in stars().
							}
							?>
							<?php if ( $wpeasycart_pi_args['item_title'] && '' !== $wpeasycart_pi_rtitle ) { ?>
							<strong class="wpec-pi-review__title"><?php echo esc_html( $wpeasycart_pi_rtitle ); ?></strong>
							<?php } ?>
						</div>
						<?php } ?>
						<?php if ( $wpeasycart_pi_names || ( $wpeasycart_pi_args['item_date'] && '' !== $wpeasycart_pi_date['text'] ) || ( $wpeasycart_pi_args['verified'] && $wpeasycart_pi_review->verified ) ) { ?>
						<div class="wpec-pi-review__meta">
							<?php if ( '' !== (string) $wpeasycart_pi_avatar ) { ?>
							<span class="wpec-pi-review__avatar" aria-hidden="true"><?php echo wp_kses_post( $wpeasycart_pi_avatar ); ?></span>
							<?php } ?>
							<?php if ( $wpeasycart_pi_names ) { ?>
							<span class="wpec-pi-review__name"><?php echo esc_html( wp_unslash( $wpeasycart_pi_review->reviewer_name ) ); ?></span>
							<?php } ?>
							<?php if ( $wpeasycart_pi_args['item_date'] && '' !== $wpeasycart_pi_date['text'] ) { ?>
							<time class="wpec-pi-review__date"<?php echo ( '' !== $wpeasycart_pi_date['iso'] ) ? ' datetime="' . esc_attr( $wpeasycart_pi_date['iso'] ) . '"' : ''; ?>><?php echo esc_html( $wpeasycart_pi_date['text'] ); ?></time>
							<?php } ?>
							<?php
							if ( $wpeasycart_pi_args['verified'] ) {
								$wpeasycart_pi_review->display_verified_badge();
							}
							?>
						</div>
						<?php } ?>
						<?php if ( $wpeasycart_pi_args['item_text'] ) { ?>
						<div class="wpec-pi-review__text"><?php echo wp_kses_post( nl2br( wp_unslash( (string) $wpeasycart_pi_review->description ) ) ); ?></div>
						<?php } ?>
						<?php
						if ( $wpeasycart_pi_args['replies'] ) {
							$wpeasycart_pi_review->display_reply( get_option( 'date_format' ) );
						}
						?>
					</li>
					<?php } ?>
				</ul>
				<?php if ( $wpeasycart_pi_paged ) { ?>
				<div class="wpec-pi-reviews__more-wrap">
					<a class="wpec-pi-reviews__more" href="<?php echo esc_url( add_query_arg( 'wpec_pi_reviews', 'all' ) . '#wpec-pi-reviews-' . $wpeasycart_pi_suffix ); ?>" data-wpec-pi-more="1"><?php echo esc_html( ( '' !== trim( (string) $wpeasycart_pi_args['more_text'] ) ) ? $wpeasycart_pi_args['more_text'] : WP_EasyCart_Product_Info::text( 'reviews_more', __( 'Show more reviews', 'wp-easycart' ) ) ); ?></a>
				</div>
				<?php } ?>
			<?php } else { ?>
				<p class="wpec-pi-reviews__empty">
				<?php
				if ( $wpeasycart_pi_form ) {
					echo esc_html( WP_EasyCart_Product_Info::text( 'product_details_review_no_reviews', __( 'There are no reviews yet, submit yours in the box provided.', 'wp-easycart' ), 'product_details' ) );
				} else {
					echo esc_html( WP_EasyCart_Product_Info::text( 'reviews_none', __( 'There are no reviews yet.', 'wp-easycart' ) ) );
				}
				?>
				</p>
			<?php } ?>
		</div>
		<?php } ?>
		<?php if ( $wpeasycart_pi_form ) { ?>
		<div class="wpec-pi-reviews__form-wrap">
			<?php if ( WP_EasyCart_Product_Info::can_review() ) { ?>
				<?php
				$wpeasycart_pi_request = ( class_exists( 'ec_reviews' ) && method_exists( 'ec_reviews', 'current_request' ) ) ? ec_reviews::current_request( $wpeasycart_pi_pid ) : null;
				$wpeasycart_pi_notice  = ( class_exists( 'ec_reviews' ) && method_exists( 'ec_reviews', 'link_notice' ) ) ? ec_reviews::link_notice() : '';
				$wpeasycart_pi_prefill = ( $wpeasycart_pi_request && method_exists( 'ec_reviews', 'prefill_rating' ) ) ? (int) ec_reviews::prefill_rating() : 0;
				$wpeasycart_pi_heading = ( '' !== trim( (string) $wpeasycart_pi_args['form_title'] ) ) ? $wpeasycart_pi_args['form_title'] : WP_EasyCart_Product_Info::text( 'customer_review_title', __( 'Write a Review', 'wp-easycart' ), 'customer_review' );
				$wpeasycart_pi_button  = ( '' !== trim( (string) $wpeasycart_pi_args['button_text'] ) ) ? $wpeasycart_pi_args['button_text'] : WP_EasyCart_Product_Info::text( 'product_details_your_review_submit', __( 'Submit', 'wp-easycart' ), 'customer_review' );
				?>
			<div class="wpec-pi-review-form" id="wpec-pi-review-form-<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>">
				<?php if ( $wpeasycart_pi_args['form_heading'] ) { ?>
				<h3 class="wpec-pi-review-form__title"><?php echo esc_html( $wpeasycart_pi_heading ); ?></h3>
				<?php } ?>
				<?php if ( ! $wpeasycart_pi_request && '' !== $wpeasycart_pi_notice ) { ?>
				<div class="ec_review_request_notice wpec-pi-review-form__notice"><?php echo esc_html( $wpeasycart_pi_notice ); ?></div>
				<?php } ?>
				<?php if ( $wpeasycart_pi_request ) { ?>
				<div class="ec_review_request_banner wpec-pi-review-form__banner" data-prefill-rating="<?php echo esc_attr( $wpeasycart_pi_prefill ); ?>" data-product-id="<?php echo esc_attr( $wpeasycart_pi_pid ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_pi_rand ); ?>">
					<span class="ec_review_verified">&#10003; <?php echo esc_html__( 'Verified buyer', 'wp-easycart' ); ?></span>
					<?php
					/* translators: %d: order number. */
					echo esc_html( sprintf( __( 'Thanks for your order #%d — your review will show as a verified purchase.', 'wp-easycart' ), (int) $wpeasycart_pi_request->order_id ) );
					?>
				</div>
				<input type="hidden" class="ec_review_request_token" id="ec_review_request_token_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>" value="<?php echo esc_attr( $wpeasycart_pi_request->token ); ?>" />
				<?php } ?>
				<p class="wpec-pi-review-form__message wpec-pi-review-form__message--error" id="ec_details_review_error_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>" role="alert"><?php echo esc_html( WP_EasyCart_Product_Info::text( 'review_error', __( 'You must include a title, rating, and message in your review.', 'wp-easycart' ), 'customer_review' ) ); ?></p>
				<p class="wpec-pi-review-form__message wpec-pi-review-form__message--error" id="ec_details_review_save_error_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>" role="alert"><?php echo esc_html( WP_EasyCart_Product_Info::text( 'customer_review_save_error', __( 'Your review could not be saved. Please refresh the page and try again.', 'wp-easycart' ), 'customer_review' ) ); ?></p>
				<p class="wpec-pi-review-form__message wpec-pi-review-form__message--working" id="ec_details_customer_review_loader_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>" role="status"><?php echo esc_html( WP_EasyCart_Product_Info::text( 'product_details_submitting_review', __( 'Submitting your review, please wait', 'wp-easycart' ), 'customer_review' ) ); ?></p>
				<p class="wpec-pi-review-form__message wpec-pi-review-form__message--success" id="ec_details_customer_review_success_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>" role="status"><?php echo esc_html( WP_EasyCart_Product_Info::text( 'product_details_review_submitted', __( 'Your review has been submitted', 'wp-easycart' ), 'customer_review' ) ); ?></p>
				<form class="wpec-pi-review-form__form" data-product-id="<?php echo esc_attr( $wpeasycart_pi_pid ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_pi_rand ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-insert-customer-review-' . $wpeasycart_pi_pid ) ); ?>" novalidate>
					<fieldset class="wpec-pi-rate">
						<legend class="wpec-pi-review-form__label"><?php echo esc_html( WP_EasyCart_Product_Info::text( 'product_details_your_review_rating', __( 'Your Rating', 'wp-easycart' ), 'customer_review' ) ); ?></legend>
						<div class="wpec-pi-rate__stars">
							<?php
							for ( $wpeasycart_pi_star = 1; $wpeasycart_pi_star <= 5; $wpeasycart_pi_star++ ) {
								$wpeasycart_pi_star_id    = 'ec_details_review_star' . $wpeasycart_pi_star . '_' . $wpeasycart_pi_suffix;
								$wpeasycart_pi_star_label = ( 1 === $wpeasycart_pi_star ) ? WP_EasyCart_Product_Info::text( 'review_star_one', __( '1 star', 'wp-easycart' ) ) : str_replace( '[rating]', number_format_i18n( $wpeasycart_pi_star ), WP_EasyCart_Product_Info::text( 'review_star_many', __( '[rating] stars', 'wp-easycart' ) ) );
								?>
							<input type="radio" class="wpec-pi-rate__input<?php echo ( $wpeasycart_pi_prefill && $wpeasycart_pi_star <= $wpeasycart_pi_prefill ) ? ' ec_product_details_star_on_ele' : ''; ?>" name="wpec_pi_rating_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>" id="<?php echo esc_attr( $wpeasycart_pi_star_id ); ?>" value="<?php echo esc_attr( $wpeasycart_pi_star ); ?>"<?php checked( $wpeasycart_pi_prefill, $wpeasycart_pi_star ); ?> />
							<label class="wpec-pi-rate__star" for="<?php echo esc_attr( $wpeasycart_pi_star_id ); ?>"><span class="wpec-el-sr-only"><?php echo esc_html( $wpeasycart_pi_star_label ); ?></span></label>
							<?php } ?>
						</div>
					</fieldset>
					<div class="wpec-pi-review-form__field">
						<label class="wpec-pi-review-form__label" for="ec_review_title_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>"><?php echo esc_html( WP_EasyCart_Product_Info::text( 'product_details_your_review_title', __( 'Your Review Title', 'wp-easycart' ), 'customer_review' ) ); ?></label>
						<input type="text" class="wpec-pi-review-form__input" id="ec_review_title_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>" autocomplete="off" />
					</div>
					<div class="wpec-pi-review-form__field">
						<label class="wpec-pi-review-form__label" for="ec_review_message_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>"><?php echo esc_html( WP_EasyCart_Product_Info::text( 'product_details_your_review_message', __( 'Your Review', 'wp-easycart' ), 'customer_review' ) ); ?></label>
						<textarea class="wpec-pi-review-form__input wpec-pi-review-form__textarea" id="ec_review_message_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>" rows="5"></textarea>
					</div>
					<div class="wpec-pi-review-form__actions" id="ec_details_submit_review_button_row_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>">
						<button type="submit" class="wpec-pi-review-form__button"><?php echo esc_html( $wpeasycart_pi_button ); ?></button>
					</div>
					<div class="wpec-pi-review-form__actions wpec-pi-review-form__done" id="ec_details_review_submitted_button_row_<?php echo esc_attr( $wpeasycart_pi_suffix ); ?>">
						<button type="button" class="wpec-pi-review-form__button" disabled><?php echo esc_html( WP_EasyCart_Product_Info::text( 'product_details_review_submitted_button', __( 'Review submitted', 'wp-easycart' ), 'customer_review' ) ); ?></button>
					</div>
				</form>
			</div>
			<?php } else { ?>
			<div class="wpec-pi-review-form wpec-pi-review-form--signin">
				<p class="wpec-pi-review-form__signin-note"><?php echo esc_html( WP_EasyCart_Product_Info::text( 'product_details_review_log_in_first', __( 'Please sign in or create an account to submit a review for this product.', 'wp-easycart' ), 'customer_review' ) ); ?></p>
				<?php if ( ! empty( $wpeasycart_pi_product->account_page ) ) { ?>
				<a class="wpec-pi-review-form__button wpec-pi-review-form__signin" href="<?php echo esc_url( $wpeasycart_pi_product->account_page ); ?>"><?php echo esc_html( WP_EasyCart_Product_Info::text( 'reviews_sign_in', __( 'Sign in', 'wp-easycart' ) ) ); ?></a>
				<?php } ?>
			</div>
			<?php } ?>
		</div>
		<?php } ?>
	</div>
	<?php } ?>
</div>
