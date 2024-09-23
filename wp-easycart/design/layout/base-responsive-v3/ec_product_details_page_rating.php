<div class="ec_details_rating">
	<?php $rating = $product->get_rating( ); ?>
	<div class="ec_product_details_star_<?php if( $rating > 0.49 ){ ?>on<?php }else{ ?>off<?php }?>_ele"></div>
	<div class="ec_product_details_star_<?php if( $rating > 1.49 ){ ?>on<?php }else{ ?>off<?php }?>_ele"></div>
	<div class="ec_product_details_star_<?php if( $rating > 2.49 ){ ?>on<?php }else{ ?>off<?php }?>_ele"></div>
	<div class="ec_product_details_star_<?php if( $rating > 3.49 ){ ?>on<?php }else{ ?>off<?php }?>_ele"></div>
	<div class="ec_product_details_star_<?php if( $rating > 4.49 ){ ?>on<?php }else{ ?>off<?php }?>_ele"></div>
</div>