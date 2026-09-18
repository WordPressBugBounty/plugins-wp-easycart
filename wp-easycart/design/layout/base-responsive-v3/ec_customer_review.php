<div class="ec_customer_review<?php echo $review->verified ? ' ec_customer_review_verified' : ''; ?>">
  <div class="ec_customer_review_title">
    <?php $review->display_review_title( ); ?>
  </div>
  <div class="ec_customer_review_meta">
    <span class="ec_customer_review_date"><?php $review->display_review_date( "F j, Y" ); ?></span>
    <?php $review->display_verified_badge( ); ?>
  </div>
  <div class="ec_customer_review_stars">
    <?php $review->display_review_stars( ); ?>
  </div>
  <div class="ec_customer_review_description">
    <?php $review->display_review_description( ); ?>
  </div>
  <?php $review->display_reply( "F j, Y" ); ?>
</div>
