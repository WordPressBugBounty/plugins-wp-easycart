<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<style type='text/css'>
		<!--
			.ec_title {font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 18px; float:left; width:100%; border-bottom:3px solid #CCC; margin-bottom:15px; }
			.ec_image{ width:200px; }
			.ec_image > img{ width:200px; max-width:200px; }
		-->
		</style>
	</head>

	<body>
		<table width="600" border="0" align="center" cellpadding="0" cellspacing="0">
			<tbody>
				<tr>
					<td width="200" style="text-align:left; width:200px" valign="top" class="ec_image">
						<img src="<?php if ( $product->use_optionitem_images ) {
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
										$first_optionitem_id = $product->options->optionset1->optionset[0]->optionitem_id;
									}
								}
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
														$product_image_media = wp_get_attachment_image_src( $product->images->imageset[$i]->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', 'medium_large' ) );
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
											$product_image_media = wp_get_attachment_image_src( $product->images->product_images[0], apply_filters( 'wp_easycart_product_details_full_size', 'medium_large' ) );
											if( $product_image_media && isset( $product_image_media[0] ) ) {
												echo esc_attr( $product_image_media[0] );
											}
										}
									} else { 
										echo esc_attr( $product->get_first_image_url( ) );
									}
								}
							} ?>" alt="<?php echo esc_attr( $product->title ); ?>" style="max-width:200px; width:200px; height:auto;" />
					</td>
					<td width="15" style="width:15px;"></td>
					<td valign="top">
						<h1 style="margin-top:0px; margin-bottom:20px;"><?php echo esc_attr( htmlspecialchars( $product->title, ENT_QUOTES ) ); ?></h1>
						<p><a href="<?php echo esc_attr( $product->get_product_link( ) ); ?>" target="_blank"><?php echo wp_easycart_language( )->get_text( 'ec_stock_notify_email', 'view_now' ); ?></a></p>
						<p><?php echo esc_attr( $product->display_product_description( ) ); ?></p>
					</td>
				</tr>
				<tr>
					<td></td>
					<td></td>
					<td><a href="<?php echo esc_attr( $product->get_product_unsubscribe_link( $subscriber->email, $subscriber->product_subscriber_id ) ); ?>" target="_blank"><?php echo wp_easycart_language( )->get_text( 'ec_stock_notify_email', 'unsubscribe' ); ?></a></td>
				</tr>
			</tbody>
		</table>
	</body>
</html>