<div class="ec_details_social">
	<?php if ( get_option( 'ec_option_use_facebook_icon' ) ) { ?>
	<div class="ec_details_social_icon ec_facebook"><a href="<?php echo esc_attr( $product->social_icons->get_facebook_link( ) ); ?>" target="_blank"><img src="<?php echo esc_attr( $product->social_icons->get_icon_image( "facebook-icon.png" ) ); ?>" alt="Facebook" /></a></div>
	<?php }?>

	<?php if( get_option( 'ec_option_use_twitter_icon' ) ){ ?>
	<div class="ec_details_social_icon ec_twitter"><a href="<?php echo esc_attr( $product->social_icons->get_twitter_link( ) ); ?>" target="_blank"><img src="<?php echo esc_attr( $product->social_icons->get_icon_image( "twitter-icon.png" ) ); ?>" alt="X" /></a></div>
	<?php }?>

	<?php if( get_option( 'ec_option_use_email_icon' ) ){ ?>
	<div class="ec_details_social_icon ec_email"><a href="<?php echo esc_attr( $product->social_icons->get_email_link( ) ); ?>" target="_blank"><img src="<?php echo esc_attr( $product->social_icons->get_icon_image( "email-icon.png" ) ); ?>" alt="Email" /></a></div>
	<?php }?>

	<?php if( get_option( 'ec_option_use_pinterest_icon' ) ){ ?>
	<div class="ec_details_social_icon ec_pinterest"><a href="<?php echo esc_attr( $product->social_icons->get_pinterest_link( ) ); ?>" target="_blank"><img src="<?php echo esc_attr( $product->social_icons->get_icon_image( "pinterest-icon.png" ) ); ?>" alt="Pinterest" /></a></div>
	<?php }?>

	<?php if( get_option( 'ec_option_use_googleplus_icon' ) ){ ?>
	<div class="ec_details_social_icon ec_googleplus"><a href="<?php echo esc_attr( $product->social_icons->get_googleplus_link( ) ); ?>" target="_blank"><img src="<?php echo esc_attr( $product->social_icons->get_icon_image( "google-icon.png" ) ); ?>" alt="Google+" /></a></div>
	<?php }?>

	<?php if( get_option( 'ec_option_use_linkedin_icon' ) ){ ?>
	<div class="ec_details_social_icon ec_linkedin"><a href="<?php echo esc_attr( $product->social_icons->get_linkedin_link( ) ); ?>" target="_blank"><img src="<?php echo esc_attr( $product->social_icons->get_icon_image( "linkedin-icon.png" ) ); ?>" alt="LinkedIn" /></a></div>
	<?php }?>

	<?php if( get_option( 'ec_option_use_myspace_icon' ) ){ ?>
	<div class="ec_details_social_icon ec_myspace"><a href="<?php echo esc_attr( $product->social_icons->get_myspace_link( ) ); ?>" target="_blank"><img src="<?php echo esc_attr( $product->social_icons->get_icon_image( "myspace-icon.png" ) ); ?>" alt="MySpace" /></a></div>
	<?php }?>

	<?php if( get_option( 'ec_option_use_digg_icon' ) ){ ?>
	<div class="ec_details_social_icon ec_digg"><a href="<?php echo esc_attr( $product->social_icons->get_digg_link( ) ); ?>" target="_blank"><img src="<?php echo esc_attr( $product->social_icons->get_icon_image( "digg-icon.png" ) ); ?>" alt="Digg" /></a></div>
	<?php }?>

	<?php if( get_option( 'ec_option_use_delicious_icon' ) ){ ?>
	<div class="ec_details_social_icon ec_delicious"><a href="<?php echo esc_attr( $product->social_icons->get_delicious_link( ) ); ?>" target="_blank"><img src="<?php echo esc_attr( $product->social_icons->get_icon_image( "delicious-icon.png" ) ); ?>" alt="Delicious" /></a></div>
	<?php }?>

</div>