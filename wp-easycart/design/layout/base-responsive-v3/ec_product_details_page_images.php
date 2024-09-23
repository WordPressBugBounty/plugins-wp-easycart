<div class="ec_details_images ec_details_images-100 ec_image_size_<?php echo esc_attr( $image_size ); ?> ec_thumb_size_<?php echo esc_attr( $thumb_size ); ?> ec_image_layout_<?php echo esc_attr( $thumbnails_position ); ?> ec_thumb_stack_<?php echo esc_attr( $thumbnails_stack ); ?>">
<?php if( apply_filters( 'wp_easycart_product_details_show_images', true ) ){ ?>
	<div class="ec_details_main_image ec_details_main_image_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?><?php echo ( ! $ipad && ! $iphone && get_option( 'ec_option_show_magnification' ) ) ? ' mag_enabled' : ''; ?>" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php if( ( isset( $show_lightbox ) && $show_lightbox ) || ( ! isset( $show_lightbox ) && get_option( 'ec_option_show_large_popup' ) ) ){ ?> onclick="ec_details_show_image_popup( '<?php echo esc_attr( $product->model_number ); ?>' );"<?php }else{ ?> style="cursor:inherit;"<?php }?>>
		<?php do_action( 'wp_easycart_product_details_image_holder_pre', $product );
		$magbox_active = true;
		if( $product->use_optionitem_images ){
			$first_image_found = false;
			$first_optionitem_id = false;
			if( $product->use_advanced_optionset ) {
				if( count( $product->advanced_optionsets ) > 0 ) {
					$valid_optionset = false;
					foreach( $product->advanced_optionsets as $adv_optionset ) {
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
				if( count( $product->options->optionset1->optionset ) > 0 ){
					for ( $j = 0; $j < count( $product->options->optionset1->optionset ) && ! $first_optionitem_id; $j++ ) {
						//print_r( $product->options->optionset1 );
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
			if( $first_optionitem_id ) {
				for( $i=0; $i<count( $product->images->imageset ); $i++ ){
					if( $product->images->imageset[$i]->optionitem_id == $first_optionitem_id ){
						if( count( $product->images->imageset[$i]->product_images ) > 0  && 'video:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
							$video_str = substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 );
							$video_arr = explode( ':::', $video_str );
							if ( count( $video_arr ) >= 2 ) {
								echo '<div class="wp-easycart-video-box"><video controls><source src="' . esc_attr( $video_arr[0] ) . '" /></video></div>';
								echo '<img src="' . esc_attr( $video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" style="display:none" />';
							}
							$magbox_active = false;
						} else if( count( $product->images->imageset[$i]->product_images ) > 0  && 'youtube:' == substr( $product->images->imageset[$i]->product_images[0], 0, 8 ) ) {
							$youtube_video_str = substr( $product->images->imageset[$i]->product_images[0], 8, strlen( $product->images->imageset[$i]->product_images[0] ) - 8 );
							$youtube_video_arr = explode( ':::', $youtube_video_str );
							if ( count( $youtube_video_arr ) >= 2 ) {
								echo '<div class="wp-easycart-video-box"><iframe src="' . esc_attr( $youtube_video_arr[0] ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
								echo '<img src="' . esc_attr( $youtube_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" style="display:none" />';
							}
							$magbox_active = false;
						} else if( count( $product->images->imageset[$i]->product_images ) > 0  && 'vimeo:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
							$vimeo_video_str = substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 );
							$vimeo_video_arr = explode( ':::', $vimeo_video_str );
							if ( count( $vimeo_video_arr ) >= 2 ) {
								echo '<div class="wp-easycart-video-box"><iframe src="' . esc_attr( $vimeo_video_arr[0] ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
								echo '<img src="' . esc_attr( $vimeo_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" style="display:none" />';
							}
							$magbox_active = false;
						} else { ?>
							<img src="<?php if( count( $product->images->imageset[$i]->product_images ) > 0 ) { 
								if ( 'image1' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_first_image_url( ) );
								} else if( 'image2' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_second_image_url( ) );
								} else if( 'image3' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_third_image_url( ) );
								} else if( 'image4' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_fourth_image_url( ) );
								} else if( 'image5' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_fifth_image_url( ) );
								} else if( 'image:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
									echo esc_attr( apply_filters('wp_easycart_product_details_image_url_type', substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 ) ) );
								} else {
									$product_image_media = wp_get_attachment_image_src( $product->images->imageset[$i]->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
									if( $product_image_media && isset( $product_image_media[0] ) ) {
										echo esc_attr( $product_image_media[0] );
									}
								}
							} else { 
								echo esc_attr( $product->get_first_image_url( ) );
							} ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" />
						<?php } // close check for video
					}
				}
			}
		} else {
			if( count( $product->images->product_images ) > 0  && 'video:' == substr( $product->images->product_images[0], 0, 6 ) ) {
				$video_str = substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 );
				$video_arr = explode( ':::', $video_str );
				if ( count( $video_arr ) >= 2 ) {
					echo '<div class="wp-easycart-video-box"><video controls><source src="' . esc_attr( $video_arr[0] ) . '" /></video></div>';
					echo '<img src="' . esc_attr( $video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" style="display:none" />';
				}
				$magbox_active = false;
			} else if( count( $product->images->product_images ) > 0  && 'youtube:' == substr( $product->images->product_images[0], 0, 8 ) ) {
				$youtube_video_str = substr( $product->images->product_images[0], 8, strlen( $product->images->product_images[0] ) - 8 );
				$youtube_video_arr = explode( ':::', $youtube_video_str );
				if ( count( $youtube_video_arr ) >= 2 ) {
					echo '<div class="wp-easycart-video-box"><iframe src="' . esc_attr( $youtube_video_arr[0] ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
					echo '<img src="' . esc_attr( $youtube_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" style="display:none" />';
				}
				$magbox_active = false;
			} else if( count( $product->images->product_images ) > 0  && 'vimeo:' == substr( $product->images->product_images[0], 0, 6 ) ) {
				$vimeo_video_str = substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 );
				$vimeo_video_arr = explode( ':::', $vimeo_video_str );
				if ( count( $vimeo_video_arr ) >= 2 ) {
					echo '<div class="wp-easycart-video-box"><iframe src="' . esc_attr( $vimeo_video_arr[0] ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
					echo '<img src="' . esc_attr( $vimeo_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" style="display:none" />';
				}
				$magbox_active = false;
			} else { ?>
				<img src="<?php if( count( $product->images->product_images ) > 0 ) { 
					if ( 'image1' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_first_image_url( ) );
					} else if( 'image2' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_second_image_url( ) );
					} else if( 'image3' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_third_image_url( ) );
					} else if( 'image4' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_fourth_image_url( ) );
					} else if( 'image5' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_fifth_image_url( ) );
					} else if( 'image:' == substr( $product->images->product_images[0], 0, 6 ) ) {
						echo esc_attr( apply_filters('wp_easycart_product_details_image_url_type', substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 ) ) );
					} else {
						$product_image_media = wp_get_attachment_image_src( $product->images->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
						if( $product_image_media && isset( $product_image_media[0] ) ) {
							echo esc_attr( $product_image_media[0] );
						}
					}
				} else { 
					echo esc_attr( $product->get_first_image_url( ) );
				} ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" />
			<?php } // close check for video ?>
		<?php } // close check for option item images ?>
		<?php do_action( 'wp_easycart_product_details_image_holder_post', $product ); ?>
	</div>

	<?php /* START MAIN IMAGE THUMBNAILS */ ?>
	<?php /* START DISPLAY FOR OPTION ITEM IMAGES USEAGE */ ?>
	<?php if( ( isset( $show_thumbnails ) && $show_thumbnails ) || ( ! isset( $show_thumbnails ) ) ) { ?>
	<?php if( $product->use_optionitem_images ){
		$optionitem_id_array = array( );
		if( $product->use_advanced_optionset ) {
			if( count( $product->advanced_optionsets ) > 0 ) {
				$valid_optionset = false;
				foreach( $product->advanced_optionsets as $adv_optionset ) {
					if( ! $valid_optionset && ( $adv_optionset->option_type == 'combo' || $adv_optionset->option_type == 'swatch' || $adv_optionset->option_type == 'radio' ) ) {
						$valid_optionset = $adv_optionset;
					}
				}
				if ( $valid_optionset ) {
					$optionitems = $product->get_advanced_optionitems( $valid_optionset->option_id );
					foreach( $optionitems as $optionitem ) {
						$optionitem_id_array[] = $optionitem->optionitem_id;
					}
				}
			}
		} else {
			if( count( $product->options->optionset1->optionset ) > 0 ){
				for ( $j = 0; $j < count( $product->options->optionset1->optionset ); $j++ ) {
					//print_r( $product->options->optionset1 );
					if ( $product->allow_backorders ) {
						$optionitem_in_stock = true;
					} else if ( $product->use_optionitem_quantity_tracking && ( $product->option1quantity[ $product->options->optionset1->optionset[ $j ]->optionitem_id ] <= 0 ) ) {
						$optionitem_in_stock = false;
					} else {
						$optionitem_in_stock = true;
					}
					if ( $product->options->verify_optionitem( 1, $product->options->optionset1->optionset[ $j ]->optionitem_id ) ) {
						if ( ! $product->use_optionitem_quantity_tracking || $product->option1quantity[ $product->options->optionset1->optionset[ $j ]->optionitem_id ] > 0 || $optionitem_in_stock ){
							$optionitem_id_array[] = $product->options->optionset1->optionset[ $j ]->optionitem_id;
						}
					}
				}
			}
		}
		$thumbnails_displayed = 0;
		for( $i=0; $i<count( $product->images->imageset ); $i++ ){
			if( in_array( $product->images->imageset[$i]->optionitem_id, $optionitem_id_array ) ){
				if( is_array( $product->images->imageset[$i]->product_images ) && count( $product->images->imageset[$i]->product_images ) > 0 ) { ?>
					<div class="ec_details_thumbnails ec_details_thumbnails_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?><?php if( $thumbnails_displayed > 0 ){ ?> ec_inactive<?php }?><?php if( count( $product->images->imageset[$i]->product_images ) <= 1 ){ ?> ec_no_thumbnails<?php }?>" id="ec_details_thumbnails_<?php echo esc_attr( $product->images->imageset[$i]->optionitem_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php if( count( $product->images->imageset[$i]->product_images ) <= 1 ){ ?> style="display:none !important;"<?php }?>>
					<?php $is_first_prod_image = true;
					foreach( $product->images->imageset[$i]->product_images as $product_image_id ) {
						if( 'image1' == $product_image_id ) {
							echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
								echo '<img src="';
								if ( substr( $product->images->imageset[$i]->image1, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image1, 0, 8 ) == 'https://' ){
									echo esc_attr( $product->images->imageset[$i]->image1 );
								} else {
									echo esc_attr( plugins_url( "/wp-easycart-data/products/pics1/" . $product->images->imageset[$i]->image1, EC_PLUGIN_DATA_DIRECTORY ) );
								}
								echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
								do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
							echo '</div>';
							$is_first_prod_image = false;
						} else if( 'image2' == $product_image_id ) {
							echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
								echo '<img src="';
								if ( substr( $product->images->imageset[$i]->image2, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image2, 0, 8 ) == 'https://' ){
									echo esc_attr( $product->images->imageset[$i]->image2 );
								} else {
									echo esc_attr( plugins_url( "/wp-easycart-data/products/pics2/" . $product->images->imageset[$i]->image2, EC_PLUGIN_DATA_DIRECTORY ) );
								}
								echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
								do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
							echo '</div>';
							$is_first_prod_image = false;
						} else if( 'image3' == $product_image_id ) {
							echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
								echo '<img src="';
								if ( substr( $product->images->imageset[$i]->image3, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image3, 0, 8 ) == 'https://' ){
									echo esc_attr( $product->images->imageset[$i]->image3 );
								} else {
									echo esc_attr( plugins_url( "/wp-easycart-data/products/pics3/" . $product->images->imageset[$i]->image3, EC_PLUGIN_DATA_DIRECTORY ) );
								}
								echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
								do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
							echo '</div>';
							$is_first_prod_image = false;
						} else if( 'image4' == $product_image_id ) {
							echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
								echo '<img src="';
								if ( substr( $product->images->imageset[$i]->image4, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image4, 0, 8 ) == 'https://' ){
									echo esc_attr( $product->images->imageset[$i]->image4 );
								} else {
									echo esc_attr( plugins_url( "/wp-easycart-data/products/pics4/" . $product->images->imageset[$i]->image4, EC_PLUGIN_DATA_DIRECTORY ) );
								}
								echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
								do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
							echo '</div>';
							$is_first_prod_image = false;
						} else if( 'image5' == $product_image_id ) {
							echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
								echo '<img src="';
								if ( substr( $product->images->imageset[$i]->image5, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image5, 0, 8 ) == 'https://' ){
									echo esc_attr( $product->images->imageset[$i]->image5 );
								} else {
									echo esc_attr( plugins_url( "/wp-easycart-data/products/pics5/" . $product->images->imageset[$i]->image5, EC_PLUGIN_DATA_DIRECTORY ) );
								}
								echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
								do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
							echo '</div>';
							$is_first_prod_image = false;
						} else if( 'image:' == substr( $product_image_id, 0, 6 ) ) {
							echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
								echo '<img src="' . esc_attr( apply_filters('wp_easycart_product_details_thumbnail_image_url_type', substr( $product_image_id, 6, strlen( $product_image_id ) - 6 ) ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
								do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
							echo '</div>';
							$is_first_prod_image = false;
						} else if( 'video:' == substr( $product_image_id, 0, 6 ) ) {
							$video_str = substr( $product_image_id, 6, strlen( $product_image_id ) - 6 );
							$video_arr = explode( ':::', $video_str );
							if ( count( $video_arr ) >= 2 ) {
								echo '<div class="ec_details_thumbnail ec_details_thumbnail_video videoType' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
									echo '<a href="' . esc_attr( $video_arr[0] ) . '" class="ec_details_video_thumb">';
										echo '<img src="' . esc_attr( $video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
										echo '<div class="wp-easycart-video-cover"></div>';
										echo '<div class="dashicons dashicons-controls-play"></div>';
									echo '</a>';
									do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
								echo '</div>';
								$is_first_prod_image = false;
							}
						} else if( 'youtube:' == substr( $product_image_id, 0, 8 ) ) {
							$youtube_video_str = substr( $product_image_id, 8, strlen( $product_image_id ) - 8 );
							$youtube_video_arr = explode( ':::', $youtube_video_str );
							if ( count( $youtube_video_arr ) >= 2 ) {
								echo '<div class="ec_details_thumbnail ec_details_thumbnail_video' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
									echo '<a href="' . esc_attr( $youtube_video_arr[0] ) . '" class="ec_details_youtube_thumb">';
										echo '<img src="' . esc_attr( $youtube_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
										echo '<div class="wp-easycart-video-cover"></div>';
										echo '<div class="dashicons dashicons-controls-play"></div>';
									echo '</a>';
									do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
								echo '</div>';
								$is_first_prod_image = false;
							}
						} else if( 'vimeo:' == substr( $product_image_id, 0, 6 ) ) {
							$vimeo_video_str = substr( $product_image_id, 6, strlen( $product_image_id ) - 6 );
							$vimeo_video_arr = explode( ':::', $vimeo_video_str );
							if ( count( $vimeo_video_arr ) >= 2 ) {
								echo '<div class="ec_details_thumbnail ec_details_thumbnail_video' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
									echo '<a href="' . esc_attr( $vimeo_video_arr[0] ) . '" class="ec_details_vimeo_thumb">';
										echo '<img src="' . esc_attr( $vimeo_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
										echo '<div class="wp-easycart-video-cover"></div>';
										echo '<div class="dashicons dashicons-controls-play"></div>';
									echo '</a>';
									do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
								echo '</div>';
								$is_first_prod_image = false;
							}
						} else {
							$product_image_media = wp_get_attachment_image_src( $product_image_id, apply_filters( 'wp_easycart_product_details_thumbnail_size', $thumb_default_size ) );
							$product_image_media_large = wp_get_attachment_image_src( $product_image_id, apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
							if( $product_image_media && isset( $product_image_media[0] ) ) {
								echo '<div class="ec_details_thumbnail ec_details_thumbnail_wpmedia' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
									echo '<img src="' . esc_attr( $product_image_media[0] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '"' . ( ( $product_image_media_large && isset( $product_image_media_large[0] ) ) ? ' data-large-src="' . esc_attr( $product_image_media_large[0] ) . '"': '' ) . ' />';
									do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
								echo '</div>';
								$is_first_prod_image = false;
							}
						}
					} ?>
					</div>
				<?php } else { ?>
				<div class="ec_details_thumbnails ec_details_thumbnails_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?><?php if( $thumbnails_displayed > 0 ){ ?> ec_inactive<?php }?><?php if( $product->images->imageset[$i]->image2 == "" ){ ?> ec_no_thumbnails<?php }?>" id="ec_details_thumbnails_<?php echo esc_attr( $product->images->imageset[$i]->optionitem_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php if( trim( $product->images->imageset[$i]->image2 ) == "" ){ ?> style="display:none !important;"<?php }?>>
					<div class="ec_details_thumbnail ec_active" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php if( substr( $product->images->imageset[$i]->image1, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image1, 0, 8 ) == 'https://' ){ echo esc_attr( $product->images->imageset[$i]->image1 ); }else{ echo esc_attr( plugins_url( "/wp-easycart-data/products/pics1/" . $product->images->imageset[$i]->image1, EC_PLUGIN_DATA_DIRECTORY ) ); } ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div>

					<?php if( trim( $product->images->imageset[$i]->image2 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php if( substr( $product->images->imageset[$i]->image2, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image2, 0, 8 ) == 'https://' ){ echo esc_attr( $product->images->imageset[$i]->image2 ); }else{ echo esc_attr( plugins_url( "/wp-easycart-data/products/pics2/" . $product->images->imageset[$i]->image2, EC_PLUGIN_DATA_DIRECTORY ) ); } ?>" /></div><?php } ?>
					<?php if( trim( $product->images->imageset[$i]->image3 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php if( substr( $product->images->imageset[$i]->image3, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image3, 0, 8 ) == 'https://' ){ echo esc_attr( $product->images->imageset[$i]->image3 ); }else{ echo esc_attr( plugins_url( "/wp-easycart-data/products/pics3/" . $product->images->imageset[$i]->image3, EC_PLUGIN_DATA_DIRECTORY ) ); } ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
					<?php if( trim( $product->images->imageset[$i]->image4 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php if( substr( $product->images->imageset[$i]->image4, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image4, 0, 8 ) == 'https://' ){ echo esc_attr( $product->images->imageset[$i]->image4 ); }else{ echo esc_attr( plugins_url( "/wp-easycart-data/products/pics4/" . $product->images->imageset[$i]->image4, EC_PLUGIN_DATA_DIRECTORY ) ); } ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
					<?php if( trim( $product->images->imageset[$i]->image5 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php if( substr( $product->images->imageset[$i]->image5, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image5, 0, 8 ) == 'https://' ){ echo esc_attr( $product->images->imageset[$i]->image5 ); }else{ echo esc_attr( plugins_url( "/wp-easycart-data/products/pics5/" . $product->images->imageset[$i]->image5, EC_PLUGIN_DATA_DIRECTORY ) ); } ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
					<?php do_action( 'wp_easycart_product_details_thumbnail_items_simple', $product, $wpeasycart_addtocart_shortcode_rand ); ?>
				</div>
				<?php if( $thumbnails_displayed == 0 ){ ?>
				<div class="ec_details_thumbnails ec_details_thumbnails_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?> ec_inactive<?php if( $product->images->imageset[$i]->image2 == "" ){ ?> ec_no_thumbnails<?php }?>" id="ec_details_thumbnails_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php if( trim( $product->images->imageset[$i]->image2 ) == "" ){ ?> style="display:none !important;"<?php }?>>
					<div class="ec_details_thumbnail ec_active" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( plugins_url( "/wp-easycart-data/products/pics1/" . $product->images->imageset[$i]->image1, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div>
					<?php if( trim( $product->images->imageset[$i]->image2 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( plugins_url( "/wp-easycart-data/products/pics2/" . $product->images->imageset[$i]->image2, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
					<?php if( trim( $product->images->imageset[$i]->image3 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( plugins_url( "/wp-easycart-data/products/pics3/" . $product->images->imageset[$i]->image3, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
					<?php if( trim( $product->images->imageset[$i]->image4 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( plugins_url( "/wp-easycart-data/products/pics4/" . $product->images->imageset[$i]->image4, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
					<?php if( trim( $product->images->imageset[$i]->image5 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( plugins_url( "/wp-easycart-data/products/pics5/" . $product->images->imageset[$i]->image5, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
					<?php do_action( 'wp_easycart_product_details_thumbnail_items_simple', $product, $wpeasycart_addtocart_shortcode_rand ); ?>
				</div>

				<?php } // Close test for thumbs displayed
			} // Close test for unlimited options
			$thumbnails_displayed++;
		}// Close test for existing option item id (bad data fix)

	} //Close for loop of image set
	/* END DISPLAY FOR OPTION ITEM IMAGES THUMNAILS */

	/* START DISPLAY FOR BASIC IMAGE THUMBNAILS */
	} else if( count( $product->images->product_images ) > 0 ) {
		if( count( $product->images->product_images ) > 1 ) {
			echo '<div class="ec_details_thumbnails ec_details_thumbnails_' . esc_attr( $product->product_id ) . '_' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
			$is_first_prod_image = true;
			foreach( $product->images->product_images as $product_image_id ) {
				if( 'image1' == $product_image_id ) {
					echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
						echo '<img src="' . esc_attr( $product->get_first_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
						do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
					echo '</div>';
					$is_first_prod_image = false;
				} else if( 'image2' == $product_image_id ) {
					echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
						echo '<img src="' . esc_attr( $product->get_second_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
						do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
					echo '</div>';
					$is_first_prod_image = false;
				} else if( 'image3' == $product_image_id ) {
					echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
						echo '<img src="' . esc_attr( $product->get_third_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
						do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
					echo '</div>';
					$is_first_prod_image = false;
				} else if( 'image4' == $product_image_id ) {
					echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
						echo '<img src="' . esc_attr( $product->get_fourth_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
						do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
					echo '</div>';
					$is_first_prod_image = false;
				} else if( 'image5' == $product_image_id ) {
					echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
						echo '<img src="' . esc_attr( $product->get_fifth_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
						do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
					echo '</div>';
					$is_first_prod_image = false;
				} else if( 'image:' == substr( $product_image_id, 0, 6 ) ) {
					echo '<div class="ec_details_thumbnail' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
						echo '<img src="' . esc_attr( apply_filters('wp_easycart_product_details_thumbnail_image_url_type', substr( $product_image_id, 6, strlen( $product_image_id ) - 6 ) ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
						do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
					echo '</div>';
					$is_first_prod_image = false;
				} else if( 'video:' == substr( $product_image_id, 0, 6 ) ) {
					$video_str = substr( $product_image_id, 6, strlen( $product_image_id ) - 6 );
					$video_arr = explode( ':::', $video_str );
					if ( count( $video_arr ) >= 2 ) {
						echo '<div class="ec_details_thumbnail ec_details_thumbnail_video videoType' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
							echo '<a href="' . esc_attr( $video_arr[0] ) . '" class="ec_details_video_thumb">';
								echo '<img src="' . esc_attr( $video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
								echo '<div class="wp-easycart-video-cover"></div>';
								echo '<div class="dashicons dashicons-controls-play"></div>';
							echo '</a>';
							do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
						echo '</div>';
						$is_first_prod_image = false;
					}
				} else if( 'youtube:' == substr( $product_image_id, 0, 8 ) ) {
					$youtube_video_str = substr( $product_image_id, 8, strlen( $product_image_id ) - 8 );
					$youtube_video_arr = explode( ':::', $youtube_video_str );
					if ( count( $youtube_video_arr ) >= 2 ) {
						echo '<div class="ec_details_thumbnail ec_details_thumbnail_video' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
							echo '<a href="' . esc_attr( $youtube_video_arr[0] ) . '" class="ec_details_youtube_thumb">';
								echo '<img src="' . esc_attr( $youtube_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
								echo '<div class="wp-easycart-video-cover"></div>';
								echo '<div class="dashicons dashicons-controls-play"></div>';
							echo '</a>';
							do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
						echo '</div>';
						$is_first_prod_image = false;
					}
				} else if( 'vimeo:' == substr( $product_image_id, 0, 6 ) ) {
					$vimeo_video_str = substr( $product_image_id, 6, strlen( $product_image_id ) - 6 );
					$vimeo_video_arr = explode( ':::', $vimeo_video_str );
					if ( count( $vimeo_video_arr ) >= 2 ) {
						echo '<div class="ec_details_thumbnail ec_details_thumbnail_video' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
							echo '<a href="' . esc_attr( $vimeo_video_arr[0] ) . '" class="ec_details_vimeo_thumb">';
								echo '<img src="' . esc_attr( $vimeo_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
								echo '<div class="wp-easycart-video-cover"></div>';
								echo '<div class="dashicons dashicons-controls-play"></div>';
							echo '</a>';
							do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
						echo '</div>';
						$is_first_prod_image = false;
					}
				} else {
					$product_image_media = wp_get_attachment_image_src( $product_image_id, apply_filters( 'wp_easycart_product_details_thumbnail_size', $thumb_default_size ) );
					$product_image_media_large = wp_get_attachment_image_src( $product_image_id, apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
					if( $product_image_media && isset( $product_image_media[0] ) ) {
						echo '<div class="ec_details_thumbnail ec_details_thumbnail_wpmedia' . ( ( $is_first_prod_image ) ? ' ec_active' : '' ) . '" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
							echo '<img src="' . esc_attr( $product_image_media[0] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '"' . ( ( $product_image_media_large && isset( $product_image_media_large[0] ) ) ? ' data-large-src="' . esc_attr( $product_image_media_large[0] ) . '"': '' ) . ' />';
							do_action( 'wp_easycart_product_details_thumbnail_item', $product_image_id, $product->product_id, $wpeasycart_addtocart_shortcode_rand );
						echo '</div>';
						$is_first_prod_image = false;
					}
				}
			}
			echo '</div>';
		}
	} else if ( trim( $product->images->image2 ) != "" ) { ?>
	<div class="ec_details_thumbnails ec_details_thumbnails_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
		<div class="ec_details_thumbnail ec_active" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_first_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div>
		<div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_second_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div>
		<?php if( trim( $product->images->image3 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_third_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
		<?php if( trim( $product->images->image4 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_fourth_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
		<?php if( trim( $product->images->image5 ) != "" ){ ?><div class="ec_details_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_fifth_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
		<?php do_action( 'wp_easycart_product_details_thumbnail_items_simple', $product, $wpeasycart_addtocart_shortcode_rand ); ?>
	</div>
	<?php }?>
	<?php }?>
	<?php /* END MAIN IMAGE THUMBNAILS */ ?>

	<?php /* START IMAGE MAGNIFICATION BOX */ ?>
	<?php if( !$ipad && !$iphone && ( ( isset( $show_image_hover ) && $show_image_hover ) || ( !isset( $show_image_hover ) && get_option( 'ec_option_show_magnification' ) ) ) ){?>
	<div class="ec_details_magbox ec_details_magbox_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?><?php echo ( $magbox_active ) ? '' : ' inactive'; ?>">
		<div class="ec_details_magbox_image ec_details_magbox_image_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>" style="background:url( '<?php 
		if( $product->use_optionitem_images ){
			$first_image_found = false;
			if( $first_optionitem_id ) {
				for( $i=0; $i<count( $product->images->imageset ); $i++ ){
					if( ! $first_image_found && (int) $product->images->imageset[$i]->optionitem_id == (int) $first_optionitem_id ){
						if( count( $product->images->imageset[$i]->product_images ) > 0 ) {
							if( 'video:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
								$video_str = substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 );
								$video_arr = explode( ':::', $video_str );
								if ( count( $video_arr ) >= 2 ) {
									echo esc_attr( $video_arr[1] );
									$first_image_found = true;
								}
							} else if( 'youtube:' == substr( $product->images->imageset[$i]->product_images[0], 0, 8 ) ) {
								$youtube_video_str = substr( $product->images->imageset[$i]->product_images[0], 8, strlen( $product->images->imageset[$i]->product_images[0] ) - 8 );
								$youtube_video_arr = explode( ':::', $youtube_video_str );
								if ( count( $youtube_video_arr ) >= 2 ) {
									echo esc_attr( $youtube_video_arr[1] );
									$first_image_found = true;
								}
							} else if( 'vimeo:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
								$vimeo_video_str = substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 );
								$vimeo_video_arr = explode( ':::', $vimeo_video_str );
								if ( count( $vimeo_video_arr ) >= 2 ) {
									echo esc_attr( $vimeo_video_arr[1] );
									$first_image_found = true;
								}
							} else { 
								if ( 'image1' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_first_image_url( ) );
								} else if( 'image2' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_second_image_url( ) );
								} else if( 'image3' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_third_image_url( ) );
								} else if( 'image4' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_fourth_image_url( ) );
								} else if( 'image5' == $product->images->imageset[$i]->product_images[0] ) {
									echo esc_attr( $product->get_fifth_image_url( ) );
								} else if( 'image:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
									echo esc_attr( apply_filters('wp_easycart_product_details_image_url_type', substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 ) ) );
								} else {
									$product_image_media = wp_get_attachment_image_src( $product->images->imageset[$i]->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
									if( $product_image_media && isset( $product_image_media[0] ) ) {
										echo esc_attr( $product_image_media[0] );
									}
								}
								$first_image_found = true;
							}
						} else {
							echo esc_attr( $product->get_first_image_url( ) );
						}
					}
				}
			}
		} else { // Close check for option item images
			if( count( $product->images->product_images ) > 0  && 'video:' == substr( $product->images->product_images[0], 0, 6 ) ) {
				$video_str = substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 );
				$video_arr = explode( ':::', $video_str );
				if ( count( $video_arr ) >= 2 ) {
					echo esc_attr( $video_arr[1] );
				}
			} else if( count( $product->images->product_images ) > 0  && 'youtube:' == substr( $product->images->product_images[0], 0, 8 ) ) {
				$youtube_video_str = substr( $product->images->product_images[0], 8, strlen( $product->images->product_images[0] ) - 8 );
				$youtube_video_arr = explode( ':::', $youtube_video_str );
				if ( count( $youtube_video_arr ) >= 2 ) {
					echo esc_attr( $youtube_video_arr[1] );
				}
			} else if( count( $product->images->product_images ) > 0  && 'vimeo:' == substr( $product->images->product_images[0], 0, 6 ) ) {
				$vimeo_video_str = substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 );
				$vimeo_video_arr = explode( ':::', $vimeo_video_str );
				if ( count( $vimeo_video_arr ) >= 2 ) {
					echo esc_attr( $vimeo_video_arr[1] );
				}
			} else {
				if( count( $product->images->product_images ) > 0 ) { 
					if ( 'image1' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_first_image_url( ) );
					} else if( 'image2' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_second_image_url( ) );
					} else if( 'image3' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_third_image_url( ) );
					} else if( 'image4' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_fourth_image_url( ) );
					} else if( 'image5' == $product->images->product_images[0] ) {
						echo esc_attr( $product->get_fifth_image_url( ) );
					} else if( 'image:' == substr( $product->images->product_images[0], 0, 6 ) ) {
						echo esc_attr( apply_filters('wp_easycart_product_details_image_url_type', substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 ) ) );
					} else {
						$product_image_media = wp_get_attachment_image_src( $product->images->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
						if( $product_image_media && isset( $product_image_media[0] ) ) {
							echo esc_attr( $product_image_media[0] );
						}
					}
				} else { 
					echo esc_attr( $product->get_first_image_url( ) );
				}
			} // close check for video
		} // close check for option item images ?>' ) no-repeat"></div>
	</div>
	<?php }?>
	<?php /* END IMAGE MAGNICFICATION BOX */ ?>

	<?php /* START PRODUCT IMAGES POPUP AREA */ ?>
	<?php if( ( isset( $show_lightbox ) && $show_lightbox ) || ( ! isset( $show_lightbox ) && get_option( 'ec_option_show_large_popup' ) ) ){?>
	<div class="ec_details_large_popup" id="ec_details_large_popup_<?php echo esc_attr( $product->model_number ); ?>">
		<div class="ec_details_large_popup_content">
			<div class="ec_details_large_popup_padding">
				<div class="ec_details_large_popup_holder">
					<div class="ec_details_large_popup_main ec_details_large_popup_main_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php 
						if( $product->use_optionitem_images ){
							$first_image_found = false;
							if( $first_optionitem_id ) {
								for( $i=0; $i<count( $product->images->imageset ); $i++ ){
									if( ! $first_image_found && (int) $product->images->imageset[$i]->optionitem_id == (int) $first_optionitem_id ){
										if( count( $product->images->imageset[$i]->product_images ) > 0 ) {
											if( 'video:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
												$video_str = substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 );
												$video_arr = explode( ':::', $video_str );
												if ( count( $video_arr ) >= 2 ) {
													echo esc_attr( $video_arr[1] );
													$first_image_found = true;
												}
											} else if( 'youtube:' == substr( $product->images->imageset[$i]->product_images[0], 0, 8 ) ) {
												$youtube_video_str = substr( $product->images->imageset[$i]->product_images[0], 8, strlen( $product->images->imageset[$i]->product_images[0] ) - 8 );
												$youtube_video_arr = explode( ':::', $youtube_video_str );
												if ( count( $youtube_video_arr ) >= 2 ) {
													echo esc_attr( $youtube_video_arr[1] );
													$first_image_found = true;
												}
											} else if( 'vimeo:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
												$vimeo_video_str = substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 );
												$vimeo_video_arr = explode( ':::', $vimeo_video_str );
												if ( count( $vimeo_video_arr ) >= 2 ) {
													echo esc_attr( $vimeo_video_arr[1] );
													$first_image_found = true;
												}
											} else { 
												if ( 'image1' == $product->images->imageset[$i]->product_images[0] ) {
													echo esc_attr( $product->get_first_image_url( ) );
												} else if( 'image2' == $product->images->imageset[$i]->product_images[0] ) {
													echo esc_attr( $product->get_second_image_url( ) );
												} else if( 'image3' == $product->images->imageset[$i]->product_images[0] ) {
													echo esc_attr( $product->get_third_image_url( ) );
												} else if( 'image4' == $product->images->imageset[$i]->product_images[0] ) {
													echo esc_attr( $product->get_fourth_image_url( ) );
												} else if( 'image5' == $product->images->imageset[$i]->product_images[0] ) {
													echo esc_attr( $product->get_fifth_image_url( ) );
												} else if( 'image:' == substr( $product->images->imageset[$i]->product_images[0], 0, 6 ) ) {
													echo esc_attr( apply_filters('wp_easycart_product_details_image_url_type', substr( $product->images->imageset[$i]->product_images[0], 6, strlen( $product->images->imageset[$i]->product_images[0] ) - 6 ) ) );
												} else {
													$product_image_media = wp_get_attachment_image_src( $product->images->imageset[$i]->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
													if( $product_image_media && isset( $product_image_media[0] ) ) {
														echo esc_attr( $product_image_media[0] );
													}
												}
												$first_image_found = true;
											}
										} else {
											echo esc_attr( $product->get_first_image_url( ) );
										}
									}
								}
							}
						} else { // Close check for option item images
							if( count( $product->images->product_images ) > 0  && 'video:' == substr( $product->images->product_images[0], 0, 6 ) ) {
								$video_str = substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 );
								$video_arr = explode( ':::', $video_str );
								if ( count( $video_arr ) >= 2 ) {
									echo esc_attr( $video_arr[1] );
								}
							} else if( count( $product->images->product_images ) > 0  && 'youtube:' == substr( $product->images->product_images[0], 0, 8 ) ) {
								$youtube_video_str = substr( $product->images->product_images[0], 8, strlen( $product->images->product_images[0] ) - 8 );
								$youtube_video_arr = explode( ':::', $youtube_video_str );
								if ( count( $youtube_video_arr ) >= 2 ) {
									echo esc_attr( $youtube_video_arr[1] );
								}
							} else if( count( $product->images->product_images ) > 0  && 'vimeo:' == substr( $product->images->product_images[0], 0, 6 ) ) {
								$vimeo_video_str = substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 );
								$vimeo_video_arr = explode( ':::', $vimeo_video_str );
								if ( count( $vimeo_video_arr ) >= 2 ) {
									echo esc_attr( $vimeo_video_arr[1] );
								}
							} else {
								if( count( $product->images->product_images ) > 0 ) { 
									if ( 'image1' == $product->images->product_images[0] ) {
										echo esc_attr( $product->get_first_image_url( ) );
									} else if( 'image2' == $product->images->product_images[0] ) {
										echo esc_attr( $product->get_second_image_url( ) );
									} else if( 'image3' == $product->images->product_images[0] ) {
										echo esc_attr( $product->get_third_image_url( ) );
									} else if( 'image4' == $product->images->product_images[0] ) {
										echo esc_attr( $product->get_fourth_image_url( ) );
									} else if( 'image5' == $product->images->product_images[0] ) {
										echo esc_attr( $product->get_fifth_image_url( ) );
									} else if( 'image:' == substr( $product->images->product_images[0], 0, 6 ) ) {
										echo esc_attr( apply_filters('wp_easycart_product_details_image_url_type', substr( $product->images->product_images[0], 6, strlen( $product->images->product_images[0] ) - 6 ) ) );
									} else {
										$product_image_media = wp_get_attachment_image_src( $product->images->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
										if( $product_image_media && isset( $product_image_media[0] ) ) {
											echo esc_attr( $product_image_media[0] );
										}
									}
								} else { 
									echo esc_attr( $product->get_first_image_url( ) );
								}
							} // close check for video
						} // close check for option item images ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div>

					<?php /* SETUP POPUP THUMBNAILS */ ?>
					<?php if ( $product->use_optionitem_images ) { 
						$thumbnails_displayed = 0;
						for( $i=0; $i<count( $product->images->imageset ); $i++ ){
							if( in_array( $product->images->imageset[$i]->optionitem_id, $optionitem_id_array ) ){
								if( is_array( $product->images->imageset[$i]->product_images ) && count( $product->images->imageset[$i]->product_images ) > 0 ) { ?>
									<div class="ec_details_large_popup_thumbnails ec_details_large_popup_thumbnails_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?><?php if( $thumbnails_displayed > 0 ){ ?> ec_inactive<?php }?><?php if( count( $product->images->imageset[$i]->product_images ) <= 1 ){ ?> ec_no_thumbnails<?php }?>" id="ec_details_large_popup_thumbnails_<?php echo esc_attr( $product->images->imageset[$i]->optionitem_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php if( count( $product->images->imageset[$i]->product_images ) <= 1 ){ ?> style="display:none !important;"<?php }?>>
									<?php $is_first_prod_image = true;
									foreach( $product->images->imageset[$i]->product_images as $product_image_id ) {
										if( 'image1' == $product_image_id ) {
											echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
												echo '<img src="';
												if ( substr( $product->images->imageset[$i]->image1, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image1, 0, 8 ) == 'https://' ){
													echo esc_attr( $product->images->imageset[$i]->image1 );
												} else {
													echo esc_attr( plugins_url( "/wp-easycart-data/products/pics1/" . $product->images->imageset[$i]->image1, EC_PLUGIN_DATA_DIRECTORY ) );
												}
												echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
											echo '</div>';
											$is_first_prod_image = false;
										} else if( 'image2' == $product_image_id ) {
											echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
												echo '<img src="';
												if ( substr( $product->images->imageset[$i]->image2, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image2, 0, 8 ) == 'https://' ){
													echo esc_attr( $product->images->imageset[$i]->image2 );
												} else {
													echo esc_attr( plugins_url( "/wp-easycart-data/products/pics2/" . $product->images->imageset[$i]->image2, EC_PLUGIN_DATA_DIRECTORY ) );
												}
												echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
											echo '</div>';
											$is_first_prod_image = false;
										} else if( 'image3' == $product_image_id ) {
											echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
												echo '<img src="';
												if ( substr( $product->images->imageset[$i]->image3, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image3, 0, 8 ) == 'https://' ){
													echo esc_attr( $product->images->imageset[$i]->image3 );
												} else {
													echo esc_attr( plugins_url( "/wp-easycart-data/products/pics3/" . $product->images->imageset[$i]->image3, EC_PLUGIN_DATA_DIRECTORY ) );
												}
												echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
											echo '</div>';
											$is_first_prod_image = false;
										} else if( 'image4' == $product_image_id ) {
											echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
												echo '<img src="';
												if ( substr( $product->images->imageset[$i]->image4, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image4, 0, 8 ) == 'https://' ){
													echo esc_attr( $product->images->imageset[$i]->image4 );
												} else {
													echo esc_attr( plugins_url( "/wp-easycart-data/products/pics4/" . $product->images->imageset[$i]->image4, EC_PLUGIN_DATA_DIRECTORY ) );
												}
												echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
											echo '</div>';
											$is_first_prod_image = false;
										} else if( 'image5' == $product_image_id ) {
											echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
												echo '<img src="';
												if ( substr( $product->images->imageset[$i]->image5, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image5, 0, 8 ) == 'https://' ){
													echo esc_attr( $product->images->imageset[$i]->image5 );
												} else {
													echo esc_attr( plugins_url( "/wp-easycart-data/products/pics5/" . $product->images->imageset[$i]->image5, EC_PLUGIN_DATA_DIRECTORY ) );
												}
												echo '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
											echo '</div>';
											$is_first_prod_image = false;
										} else if( 'image:' == substr( $product_image_id, 0, 6 ) ) {
											echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
												echo '<img src="' . esc_attr( apply_filters('wp_easycart_product_details_popup_thumbnail_image_url_type', substr( $product_image_id, 6, strlen( $product_image_id ) - 6 ) ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
											echo '</div>';
											$is_first_prod_image = false;
										} else if( 'video:' == substr( $product_image_id, 0, 6 ) ) {
											$video_str = substr( $product_image_id, 6, strlen( $product_image_id ) - 6 );
											$video_arr = explode( ':::', $video_str );
											if ( count( $video_arr ) >= 2 ) {
												echo '<div class="ec_details_large_popup_thumbnail ec_details_thumbnail_video videoType" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
													echo '<a href="' . esc_attr( $video_arr[0] ) . '" class="ec_details_video_thumb">';
														echo '<img src="' . esc_attr( $video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
														echo '<div class="wp-easycart-video-cover"></div>';
														echo '<div class="dashicons dashicons-controls-play"></div>';
													echo '</a>';
												echo '</div>';
												$is_first_prod_image = false;
											}
										} else if( 'youtube:' == substr( $product_image_id, 0, 8 ) ) {
											$youtube_video_str = substr( $product_image_id, 8, strlen( $product_image_id ) - 8 );
											$youtube_video_arr = explode( ':::', $youtube_video_str );
											if ( count( $youtube_video_arr ) >= 2 ) {
												echo '<div class="ec_details_large_popup_thumbnail ec_details_thumbnail_video" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
													echo '<a href="' . esc_attr( $youtube_video_arr[0] ) . '" class="ec_details_youtube_thumb">';
														echo '<img src="' . esc_attr( $youtube_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
														echo '<div class="wp-easycart-video-cover"></div>';
														echo '<div class="dashicons dashicons-controls-play"></div>';
													echo '</a>';
												echo '</div>';
												$is_first_prod_image = false;
											}
										} else if( 'vimeo:' == substr( $product_image_id, 0, 6 ) ) {
											$vimeo_video_str = substr( $product_image_id, 6, strlen( $product_image_id ) - 6 );
											$vimeo_video_arr = explode( ':::', $vimeo_video_str );
											if ( count( $vimeo_video_arr ) >= 2 ) {
												echo '<div class="ec_details_large_popup_thumbnail ec_details_thumbnail_video" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
													echo '<a href="' . esc_attr( $vimeo_video_arr[0] ) . '" class="ec_details_vimeo_thumb">';
														echo '<img src="' . esc_attr( $vimeo_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
														echo '<div class="wp-easycart-video-cover"></div>';
														echo '<div class="dashicons dashicons-controls-play"></div>';
													echo '</a>';
												echo '</div>';
												$is_first_prod_image = false;
											}
										} else {
											$product_image_media = wp_get_attachment_image_src( $product_image_id, apply_filters( 'wp_easycart_product_details_thumbnail_size', $thumb_default_size ) );
											$product_image_media_large = wp_get_attachment_image_src( $product_image_id, apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
											if( $product_image_media && isset( $product_image_media[0] ) ) {
												echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
													echo '<img src="' . esc_attr( $product_image_media[0] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '"' . ( ( $product_image_media_large && isset( $product_image_media_large[0] ) ) ? ' data-large-src="' . esc_attr( $product_image_media_large[0] ) . '"': '' ) . ' />';
												echo '</div>';
												$is_first_prod_image = false;
											}
										}
									} ?>
									</div>
								<?php } else { ?>
									<div class="ec_details_large_popup_thumbnails ec_details_large_popup_thumbnails_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?><?php if( $thumbnails_displayed > 0 ){ ?> ec_inactive<?php }?>" id="ec_details_large_popup_thumbnails_<?php echo esc_attr( $product->images->imageset[$i]->optionitem_id ); ?>_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"<?php if( trim( $product->images->imageset[$i]->image2 ) == "" ){ ?> style="display:none;"<?php }?>>
										<div class="ec_details_large_popup_thumbnail ec_active" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( ( substr( $product->images->imageset[$i]->image1, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image1, 0, 8 ) == 'https://' ) ? $product->images->imageset[$i]->image1 : plugins_url( "/wp-easycart-data/products/pics1/" . $product->images->imageset[$i]->image1, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div>
										<?php if( trim( $product->images->imageset[$i]->image2 ) != "" ){ ?><div class="ec_details_large_popup_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( ( substr( $product->images->imageset[$i]->image2, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image2, 0, 8 ) == 'https://' ) ? $product->images->imageset[$i]->image2 : plugins_url( "/wp-easycart-data/products/pics2/" . $product->images->imageset[$i]->image2, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
										<?php if( trim( $product->images->imageset[$i]->image3 ) != "" ){ ?><div class="ec_details_large_popup_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( ( substr( $product->images->imageset[$i]->image3, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image3, 0, 8 ) == 'https://' ) ? $product->images->imageset[$i]->image3 : plugins_url( "/wp-easycart-data/products/pics3/" . $product->images->imageset[$i]->image3, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
										<?php if( trim( $product->images->imageset[$i]->image4 ) != "" ){ ?><div class="ec_details_large_popup_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( ( substr( $product->images->imageset[$i]->image4, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image4, 0, 8 ) == 'https://' ) ? $product->images->imageset[$i]->image4 : plugins_url( "/wp-easycart-data/products/pics4/" . $product->images->imageset[$i]->image4, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
										<?php if( trim( $product->images->imageset[$i]->image5 ) != "" ){ ?><div class="ec_details_large_popup_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( ( substr( $product->images->imageset[$i]->image5, 0, 7 ) == 'http://' || substr( $product->images->imageset[$i]->image5, 0, 8 ) == 'https://' ) ? $product->images->imageset[$i]->image5 : plugins_url( "/wp-easycart-data/products/pics5/" . $product->images->imageset[$i]->image5, EC_PLUGIN_DATA_DIRECTORY ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
									</div>
							<?php }
								$thumbnails_displayed++;
							}
						}
						?>
					<?php } else if( count( $product->images->product_images ) > 0 ) {
						if( count( $product->images->product_images ) > 1 ) {
							echo '<div class="ec_details_large_popup_thumbnails ec_details_large_popup_thumbnails_' . esc_attr( $product->product_id ) . '_' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
							$is_first_prod_image = true;
							foreach( $product->images->product_images as $product_image_id ) {
								if( 'image1' == $product_image_id ) {
									echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
										echo '<img src="' . esc_attr( $product->get_first_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
									echo '</div>';
									$is_first_prod_image = false;
								} else if( 'image2' == $product_image_id ) {
									echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
										echo '<img src="' . esc_attr( $product->get_second_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
									echo '</div>';
									$is_first_prod_image = false;
								} else if( 'image3' == $product_image_id ) {
									echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
										echo '<img src="' . esc_attr( $product->get_third_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
									echo '</div>';
									$is_first_prod_image = false;
								} else if( 'image4' == $product_image_id ) {
									echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
										echo '<img src="' . esc_attr( $product->get_fourth_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
									echo '</div>';
									$is_first_prod_image = false;
								} else if( 'image5' == $product_image_id ) {
									echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
										echo '<img src="' . esc_attr( $product->get_fifth_image_url( ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
									echo '</div>';
									$is_first_prod_image = false;
								} else if( 'image:' == substr( $product_image_id, 0, 6 ) ) {
									echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
										echo '<img src="' . esc_attr( apply_filters('wp_easycart_product_details_thumbnail_large_image_url_type', substr( $product_image_id, 6, strlen( $product_image_id ) - 6 ) ) ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
									echo '</div>';
									$is_first_prod_image = false;
								} else if( 'video:' == substr( $product_image_id, 0, 6 ) ) {
									$video_str = substr( $product_image_id, 6, strlen( $product_image_id ) - 6 );
									$video_arr = explode( ':::', $video_str );
									if ( count( $video_arr ) >= 2 ) {
										echo '<div class="ec_details_large_popup_thumbnail ec_details_thumbnail_video videoType" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
											echo '<a href="' . esc_attr( $video_arr[0] ) . '" class="ec_details_video_thumb">';
												echo '<img src="' . esc_attr( $video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
												echo '<div class="wp-easycart-video-cover"></div>';
												echo '<div class="dashicons dashicons-controls-play"></div>';
											echo '</a>';
										echo '</div>';
										$is_first_prod_image = false;
									}
									$is_first_prod_image = false;
								} else if( 'youtube:' == substr( $product_image_id, 0, 8 ) ) {
									$youtube_video_str = substr( $product_image_id, 8, strlen( $product_image_id ) - 8 );
									$youtube_video_arr = explode( ':::', $youtube_video_str );
									if ( count( $youtube_video_arr ) >= 2 ) {
										echo '<div class="ec_details_large_popup_thumbnail ec_details_thumbnail_video" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
											echo '<a href="' . esc_attr( $youtube_video_arr[0] ) . '" class="ec_details_youtube_thumb">';
												echo '<img src="' . esc_attr( $youtube_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
												echo '<div class="wp-easycart-video-cover"></div>';
												echo '<div class="dashicons dashicons-controls-play"></div>';
											echo '</a>';
										echo '</div>';
										$is_first_prod_image = false;
									}
								} else if( 'vimeo:' == substr( $product_image_id, 0, 6 ) ) {
									$vimeo_video_str = substr( $product_image_id, 6, strlen( $product_image_id ) - 6 );
									$vimeo_video_arr = explode( ':::', $vimeo_video_str );
									if ( count( $vimeo_video_arr ) >= 2 ) {
										echo '<div class="ec_details_large_popup_thumbnail ec_details_thumbnail_video" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
											echo '<a href="' . esc_attr( $vimeo_video_arr[0] ) . '" class="ec_details_vimeo_thumb">';
												echo '<img src="' . esc_attr( $vimeo_video_arr[1] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '" />';
												echo '<div class="wp-easycart-video-cover"></div>';
												echo '<div class="dashicons dashicons-controls-play"></div>';
											echo '</a>';
										echo '</div>';
										$is_first_prod_image = false;
									}
								} else {
									$product_image_media = wp_get_attachment_image_src( $product_image_id, apply_filters( 'wp_easycart_product_details_thumbnail_size', $thumb_default_size ) );
									$product_image_media_large = wp_get_attachment_image_src( $product_image_id, apply_filters( 'wp_easycart_product_details_full_size', $image_default_size ) );
									if( $product_image_media && isset( $product_image_media[0] ) ) {
										echo '<div class="ec_details_large_popup_thumbnail" data-product-id="' . esc_attr( $product->product_id ) . '" data-rand-id="' . esc_attr( $wpeasycart_addtocart_shortcode_rand ) . '">';
											echo '<img src="' . esc_attr( $product_image_media[0] ) . '" alt="' . esc_attr( strip_tags( stripslashes( $product->title ) ) ) . '"' . ( ( $product_image_media_large && isset( $product_image_media_large[0] ) ) ? ' data-large-src="' . esc_attr( $product_image_media_large[0] ) . '"': '' ) . ' />';
										echo '</div>';
										$is_first_prod_image = false;
									}
								}
							}
							echo '</div>';
						}
					} else if( trim( $product->images->image2 ) != "" ) { ?>
					<div class="ec_details_large_popup_thumbnails ec_details_large_popup_thumbnails_<?php echo esc_attr( $product->product_id ); ?>_<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>">
						<div class="ec_details_large_popup_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_first_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div>
						<div class="ec_details_large_popup_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_second_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div>
						<?php if( trim( $product->images->image3 ) != "" ){ ?><div class="ec_details_large_popup_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_third_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
						<?php if( trim( $product->images->image4 ) != "" ){ ?><div class="ec_details_large_popup_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_fourth_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
						<?php if( trim( $product->images->image5 ) != "" ){ ?><div class="ec_details_large_popup_thumbnail" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-rand-id="<?php echo esc_attr( $wpeasycart_addtocart_shortcode_rand ); ?>"><img src="<?php echo esc_attr( $product->get_fifth_image_url( ) ); ?>" alt="<?php echo esc_attr( strip_tags( stripslashes( $product->title ) ) ); ?>" /></div><?php } ?>
					</div>
					<?php }?>
					<?php /* END POPUP THUMBNAIL SETUP */ ?>
					<div class="ec_details_large_popup_close"><input type="button" onclick="ec_details_hide_large_popup( '<?php echo esc_attr( $product->model_number ); ?>' );" value="x"></div>
				</div>
			</div>
		</div>
	</div>
	<?php }?>
	<?php /* END PRODUCT IMAGE POPUP AREA */ ?>
	<?php } // Close image show filter ?>
	<?php do_action( 'wp_easycart_product_details_after_left_content_area', $product->product_id ); ?>
</div>
