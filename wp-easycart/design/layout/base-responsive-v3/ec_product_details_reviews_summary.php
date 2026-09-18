<?php
/**
 * Rating summary shown at the top of the reviews tab: average, count, 5→1 distribution.
 * Included by ec_product_details_page.php; $this->product is the ec_product. Safe when ec_reviews is absent.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'ec_reviews' ) || ! $this->product->use_customer_reviews ) { return; }
$ec_rs = ec_reviews::summary( $this->product->product_id );
if ( ! $ec_rs['count'] ) { return; }
$ec_rs_full = (int) floor( $ec_rs['avg'] );
?>
<div class="ec_review_summary">
	<div class="ec_review_summary_avg">
		<span class="ec_review_summary_num"><?php echo esc_html( number_format_i18n( $ec_rs['avg'], 1 ) ); ?></span>
		<span class="ec_review_summary_stars" aria-label="<?php echo esc_attr( sprintf( __( 'Rated %1$s out of 5', 'wp-easycart' ), number_format_i18n( $ec_rs['avg'], 1 ) ) ); ?>">
			<?php for ( $i = 1; $i <= 5; $i++ ) { echo '<span class="' . ( $i <= $ec_rs_full ? 'ec_product_details_star_on' : ( $i - $ec_rs['avg'] < 1 && $i > $ec_rs_full ? 'ec_product_details_star_half' : 'ec_product_details_star_off' ) ) . '"></span>'; } ?>
		</span>
		<span class="ec_review_summary_count"><span><?php echo (int) $ec_rs['count']; ?></span> <?php echo esc_html( _n( 'review', 'reviews', $ec_rs['count'], 'wp-easycart' ) ); ?></span>
	</div>
	<div class="ec_review_summary_dist">
		<?php for ( $st = 5; $st >= 1; $st-- ) { $n = (int) $ec_rs['dist'][ $st ]; $pct = $ec_rs['count'] ? round( 100 * $n / $ec_rs['count'] ) : 0; ?>
		<a class="ec_review_dist_row" href="#" data-rating="<?php echo $st; ?>" title="<?php echo esc_attr( sprintf( _n( '%1$d review with %2$d stars', '%1$d reviews with %2$d stars', $n, 'wp-easycart' ), $n, $st ) ); ?>" onclick="return ec_reviews_filter( <?php echo (int) $this->product->product_id; ?>, <?php echo $st; ?> );">
			<span class="ec_review_dist_label"><?php echo $st; ?> <span class="ec_product_details_star_on ec_review_dist_star"></span></span>
			<span class="ec_review_dist_bar"><span style="width:<?php echo $pct; ?>%"></span></span>
			<span class="ec_review_dist_n"><?php echo $n; ?></span>
		</a>
		<?php } ?>
	</div>
</div>
