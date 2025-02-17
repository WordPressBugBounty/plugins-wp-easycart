<?php
do_action( 'wp_easycart_product_details_before', $product );
if( trim( get_option( 'ec_option_fb_pixel' ) ) != '' ){
	echo "<script>
			fbq('track', 'ViewContent', {
				content_name: '" . esc_attr( ucwords( strtolower( strip_tags( $product->title ) ) ) ) . "',
				content_ids: ['" . esc_attr( $product->product_id ) . "'],
				content_type: 'product',";
	if ( ( ! $product->login_for_pricing || $product->is_login_for_pricing_valid() ) && ( ! $product->is_catalog_mode || ! get_option( 'ec_option_hide_price_seasonal' ) ) && ( ! $product->is_inquiry_mode || ! get_option( 'ec_option_hide_price_inquiry' ) ) ) {
	echo "
				value: " . esc_attr( number_format( $product->price, 2, '.', '' ) ) . ",
				currency: '" . esc_attr( $GLOBALS['currency']->get_currency_code( ) ) . "',";
	}
	echo "
			});
		</script>";
}
?>

<?php
/* If using Google Merchant Show Necessary META */
if( isset( $product->google_attributes ) && $product->google_attributes != NULL && $product->google_attributes != "" ){
	$google_attributes = json_decode( $product->google_attributes );
}else{
	$google_attributes = false;
}
$first_image_url = '';
if ( $product->use_optionitem_images ) {
	$first_optionitem_id = false;
	if ( $product->use_advanced_optionset ) {
		if ( count( $product->advanced_optionsets ) > 0 ) {
			$valid_optionset = false;
			foreach ( $product->advanced_optionsets as $adv_optionset ) {
				if( ! $valid_optionset && ( $adv_optionset->option_type == 'combo' || $adv_optionset->option_type == 'swatch' || $adv_optionset->option_type == 'radio' ) ) {
					$valid_optionset = $adv_optionset;
				}
			}
			if ( $valid_optionset ) {
				$optionitems = $product->get_advanced_optionitems( $valid_optionset->option_id );
				if ( count( $optionitems ) > 0 ) {
					$first_optionitem_id = $optionitems[0]->optionitem_id;
				}
			}
		}
	} else {
		if ( count( $product->options->optionset1->optionset ) > 0 ) {
			for ( $j = 0; $j < count( $product->options->optionset1->optionset ) && ! $first_optionitem_id; $j++ ) {
				if ( $product->allow_backorders ) {
					$optionitem_in_stock = true;
				} else if ( $product->use_optionitem_quantity_tracking && ( $product->option1quantity[ $product->options->optionset1->optionset[ $j ]->optionitem_id ] <= 0 ) ) {
					$optionitem_in_stock = false;
				} else {
					$optionitem_in_stock = true;
				}
				if ( $product->options->verify_optionitem( 1, $product->options->optionset1->optionset[ $j ]->optionitem_id ) ) {
					if ( ! $product->use_optionitem_quantity_tracking || $product->option1quantity[ $product->options->optionset1->optionset[ $j ]->optionitem_id ] > 0 || $optionitem_in_stock ){
						for ( $k = 0; $k < count( $product->images->imageset ) && ! $first_optionitem_id; $k++ ) {
							if ( $product->images->imageset[ $k ]->optionitem_id == $product->options->optionset1->optionset[ $j ]->optionitem_id ) {
								$first_optionitem_id = $product->options->optionset1->optionset[ $j ]->optionitem_id;
							}
						}
					}
				}
			}
		}
	}
	$first_image_found = false;
	if ( $first_optionitem_id ) {
		for ( $i = 0; $i < count( $product->images->imageset ); $i++ ) {
			if ( ! $first_image_found && ( 0 == (int) $product->images->imageset[$i]->optionitem_id || (int) $product->images->imageset[$i]->optionitem_id == $first_optionitem_id ) ) {
				if ( count( $product->images->imageset[$i]->product_images ) > 0 ) {
					if( 'video:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
						$video_str = substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 );
						$video_arr = explode( ':::', $video_str );
						if ( count( $video_arr ) >= 2 ) {
							$first_image_url = $video_arr[1];
							$first_image_found = true;
						}
					} else if( 'youtube:' == substr( $product->images->imageset[$i]->product_images[0], 0, 8 ) ) {
						$youtube_video_str = substr( $product->images->imageset[$i]->product_images[0], 8, strlen( $product->images->imageset[$i]->product_images[0] ) - 8 );
						$youtube_video_arr = explode( ':::', $youtube_video_str );
						if ( count( $youtube_video_arr ) >= 2 ) {
							$first_image_url = $youtube_video_arr[1];
							$first_image_found = true;
						}
					} else if( 'vimeo:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
						$vimeo_video_str = substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 );
						$vimeo_video_arr = explode( ':::', $vimeo_video_str );
						if ( count( $vimeo_video_arr ) >= 2 ) {
							$first_image_url = $vimeo_video_arr[1];
							$first_image_found = true;
						}
					} else {
						if ( 'image1' == $product->images->imageset[$i]->product_images[0] ) {
							$first_image_url = $product->get_first_image_url();
							$first_image_found = true;
						} else if( 'image2' == $product->images->imageset[$i]->product_images[0] ) {
							$first_image_url = $product->get_second_image_url();
							$first_image_found = true;
						} else if( 'image3' == $product->images->imageset[$i]->product_images[0] ) {
							$first_image_url = $product->get_third_image_url();
							$first_image_found = true;
						} else if( 'image4' == $product->images->imageset[$i]->product_images[0] ) {
							$first_image_url = $product->get_fourth_image_url();
							$first_image_found = true;
						} else if( 'image5' == $product->images->imageset[$i]->product_images[0] ) {
							$first_image_url = $product->get_fifth_image_url();
							$first_image_found = true;
						} else if( 'image:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
							$first_image_url = esc_attr( apply_filters('wp_easycart_product_details_image_url_type', substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 ) ) );
							$first_image_found = true;
						} else {
							$product_image_media = wp_get_attachment_image_src( $product->images->imageset[$i]->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', 'medium_large' ) );
							if ( $product_image_media && isset( $product_image_media[0] ) ) {
								$first_image_url = $product_image_media[0];
								$first_image_found = true;
							}
						}
					} // close check for video
				} else {
					if ( (int) $product->images->imageset[$i]->optionitem_id != 0 ) {
						$first_image_url = $product->get_first_image_url();
						$first_image_found = true;
					}
				}
			}
		}
	}
} else {
	if ( count( $product->images->product_images ) > 0  && 'video:' == substr( $product->images->product_images[0], 0, 6 ) ) {
		$video_str = substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 );
		$video_arr = explode( ':::', $video_str );
		if ( count( $video_arr ) >= 2 ) {
			$first_image_url = $video_arr[1];
			$first_image_found = true;
		}
	} else if( count( $product->images->product_images ) > 0  && 'youtube:' == substr( $product->images->product_images[0], 0, 8 ) ) {
		$youtube_video_str = substr( $product->images->product_images[0], 8, strlen( $product->images->product_images[0] ) - 8 );
		$youtube_video_arr = explode( ':::', $youtube_video_str );
		if ( count( $youtube_video_arr ) >= 2 ) {
			$first_image_url = $youtube_video_arr[1];
			$first_image_found = true;
		}
	} else if( count( $product->images->product_images ) > 0  && 'vimeo:' == substr( $product->images->product_images[0], 0, 6 ) ) {
		$vimeo_video_str = substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 );
		$vimeo_video_arr = explode( ':::', $vimeo_video_str );
		if ( count( $vimeo_video_arr ) >= 2 ) {
			$first_image_url = $vimeo_video_arr[1];
			$first_image_found = true;
		}
	} else {
		if ( count( $product->images->product_images ) > 0 ) {
			if ( 'image1' == $product->images->product_images[0] ) {
				$first_image_url = $product->get_first_image_url();
				$first_image_found = true;
			} else if( 'image2' == $product->images->product_images[0] ) {
				$first_image_url = $product->get_second_image_url();
				$first_image_found = true;
			} else if( 'image3' == $product->images->product_images[0] ) {
				$first_image_url = $product->get_third_image_url();
				$first_image_found = true;
			} else if( 'image4' == $product->images->product_images[0] ) {
				$first_image_url = $product->get_fourth_image_url();
				$first_image_found = true;
			} else if( 'image5' == $product->images->product_images[0] ) {
				$first_image_url = $product->get_fifth_image_url();
				$first_image_found = true;
			} else if( 'image:' == substr( $product->images->product_images[0], 0, 6 ) ) {
				$first_image_url = apply_filters('wp_easycart_product_details_image_url_type', substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 ) );
				$first_image_found = true;
			} else {
				$product_image_media = wp_get_attachment_image_src( $product->images->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', 'medium_large' ) );
				if ( $product_image_media && isset( $product_image_media[0] ) ) {
					$first_image_url = $product_image_media[0];
				$first_image_found = true;
				}
			}
		}
	}
}
if ( ! $first_image_found ) {
	$first_image_url = $product->get_first_image_url();
}
?>
<script type="application/ld+json">
{
	"@context": "http://schema.org",
	"@type": "Product",
	"offers": {
		"@type": "Offer",
		"url": <?php echo wp_json_encode( esc_url( $product->get_product_link() ) ); ?>,
		"availability": "<?php echo ( !$product->show_stock_quantity || $product->stock_quantity > 0 ) ? 'InStock' : 'OutOfStock'; ?>"<?php if ( ( ! $product->login_for_pricing || $product->is_login_for_pricing_valid() ) && ( ! $product->is_catalog_mode || ! get_option( 'ec_option_hide_price_seasonal' ) ) && ( ! $product->is_inquiry_mode || ! get_option( 'ec_option_hide_price_inquiry' ) ) ) { ?>,
		"price": <?php echo wp_json_encode( number_format( $product->price, 2, '.', '' ) ); ?>,
		"priceValidUntil": <?php echo wp_json_encode( date( 'Y-m-d', strtotime( '+1 year' ) ) ); ?>,
		"priceCurrency": <?php echo wp_json_encode( $GLOBALS['currency']->get_currency_code() ); ?><?php }?><?php if( $google_attributes && isset( $google_attributes->condition ) ){ ?>,
		"itemCondition": "<?php if( 'new' == strtolower( $google_attributes->condition ) || '' == $google_attributes->condition ) { echo 'NewCondition'; }else if( 'used' == strtolower( $google_attributes->condition ) ){ echo 'UsedCondition'; }else{ echo 'RefurbishedCondition'; } ?>"<?php }?>
	},
	"brand": <?php echo wp_json_encode( $product->manufacturer_name ); ?>,
	"sku": <?php echo wp_json_encode( $product->model_number ); ?>,
	"name": <?php echo wp_json_encode( strip_tags( $product->title ) ); ?>,
	"description": <?php echo  wp_json_encode( trim( preg_replace( '/[\r\n]+/', ' ', ( ( isset( $product->short_description ) && strlen( $product->short_description ) > 0 ) ? str_replace( "\n", ' ', str_replace( "\r", ' ', strip_tags( stripslashes( $product->short_description ) ) ) ) : str_replace( "\n", ' ', str_replace( "\r", ' ', stripslashes( $product->description ) ) ) ) ) ) ); ?><?php if( $google_attributes && isset( $google_attributes->gtin ) && strlen( $google_attributes->gtin ) > 0 ){ ?>,
	"gtin": <?php echo wp_json_encode( $google_attributes->gtin ); ?><?php }else if( $google_attributes && isset( $google_attributes->mpn ) && strlen( $google_attributes->mpn ) > 0 ){ ?>,
	"mpn": <?php echo wp_json_encode( $google_attributes->mpn ); ?><?php }?>,
	"url": <?php echo wp_json_encode( esc_url( $product->get_product_link() ) ); ?>,<?php if( $product->use_customer_reviews && count( $product->reviews ) > 0 ){
	$best_review = false;
	foreach( $product->reviews as $one_review ){
		if( !$best_review || $one_review->rating > $best_review->rating ){
			$best_review = $one_review;
		}
	}
	if( $best_review ){
		$best_review = new ec_review( $best_review );
	?>
	"review": {
		"@type": "Review",
		"reviewRating": {
			"@type": "Rating",
			"ratingValue": <?php echo wp_json_encode( $best_review->rating ); ?>
		},
		"author":{
			"@type": "Person",
			"name": <?php echo wp_json_encode( stripslashes( $best_review->reviewer_name ) ); ?>
		},
		"reviewBody": <?php echo wp_json_encode( trim( preg_replace( '/[\r\n]+/', ' ', stripslashes( $best_review->description ) ) ) ); ?>
	},<?php }?>
	"aggregateRating": {
		"@type": "AggregateRating",
		"reviewCount": <?php echo wp_json_encode( (int) count( $product->reviews ) ); ?>,
		"ratingValue": <?php echo wp_json_encode( $product->get_rating( ) ); ?>
	},<?php }?>
	"image": <?php echo wp_json_encode( esc_url( $first_image_url ) ); ?>
}
</script>
