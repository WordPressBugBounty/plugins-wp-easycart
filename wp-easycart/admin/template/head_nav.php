<?php
/**
 * WP EasyCart Admin Shell V2 — Help menu content.
 *
 * The old full-width marketing header bar is now the content of the Help
 * dropdown in the top bar (shell.php fires wp_easycart_admin_head_navigation
 * inside .ecsh-tb-dropdown, so third-party hooks keep working — anchors
 * appended here render as extra menu rows).
 *
 * Video Tutorials is an expandable group (works on touch, unlike the old
 * hover flyout). Each video link carries data-ecsh-video with the YouTube
 * id: shell-v2.js intercepts the click and plays it in the existing
 * in-admin lightbox (wp_easycart_admin_help_video_player). Without JS, or
 * on middle-click/new-tab, the href still goes to YouTube — same URLs as V1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecsh_videos = apply_filters( 'wp_easycart_shell_help_videos', array(
	'Pc3bCSgR-xM' => __( 'Installing the Free Edition', 'wp-easycart' ),
	'58S78jhdito' => __( 'Installing the Pro Trial Edition', 'wp-easycart' ),
	'XZmXGI02i6Y' => __( 'Creating Your First Product', 'wp-easycart' ),
	'T0dByKX67iY' => __( 'Creating a Product Option', 'wp-easycart' ),
	'rRHm0XvqXto' => __( 'Creating Product Categories', 'wp-easycart' ),
	'5rhbgYNuyOs' => __( 'How-To Design Your Store', 'wp-easycart' ),
	'_PqRz4SVCTQ' => __( 'How-to Payment Gateways', 'wp-easycart' ),
	'Mg4IM2jQwl4' => __( 'How-to Setup Taxes', 'wp-easycart' ),
	'_Bm7gtf8RCU' => __( 'How-to Setup Shipping', 'wp-easycart' ),
	'1375hHNensY' => __( 'How-to Launch Your Store', 'wp-easycart' ),
) );
?>
<a href="http://docs.wpeasycart.com/" target="_blank"><span class="dashicons dashicons-book-alt"></span> <?php esc_attr_e( 'Online Documentation', 'wp-easycart' ); ?></a>

<div class="ecsh-dd-group">
	<button class="ecsh-dd-group-toggle" type="button" aria-expanded="false">
		<span class="dashicons dashicons-format-video"></span>
		<?php esc_attr_e( 'Video Tutorials', 'wp-easycart' ); ?>
		<svg class="ecsh-dd-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
	</button>
	<div class="ecsh-dd-group-body">
		<?php foreach ( $ecsh_videos as $ecsh_video_id => $ecsh_video_label ) { ?>
		<a href="https://www.youtube.com/watch?v=<?php echo esc_attr( $ecsh_video_id ); ?>" target="_blank" data-ecsh-video="<?php echo esc_attr( $ecsh_video_id ); ?>"><?php echo esc_html( $ecsh_video_label ); ?></a>
		<?php } ?>
		<a href="http://support.wpeasycart.com/video-tutorials/" target="_blank" class="ecsh-dd-more"><?php esc_attr_e( 'View More Videos', 'wp-easycart' ); ?></a>
	</div>
</div>

<a href="http://forums.wpeasycart.com" target="_blank"><span class="dashicons dashicons-format-chat"></span> <?php esc_attr_e( 'Support Forums', 'wp-easycart' ); ?></a>
<a href="http://blog.wpeasycart.com/" target="_blank"><span class="dashicons dashicons-welcome-write-blog"></span> <?php esc_attr_e( 'eCommerce Blog', 'wp-easycart' ); ?></a>
<div class="ecsh-dd-sep"></div>
<a href="http://support.wpeasycart.com" target="_blank"><span class="dashicons dashicons-sos"></span> <?php esc_attr_e( 'Support Center', 'wp-easycart' ); ?></a>
<a href="https://www.wpeasycart.com/wordpress-ecommerce-premium-edition/" target="_blank"><span class="dashicons dashicons-cart"></span> <?php esc_attr_e( 'Extensions &amp; Add-ons', 'wp-easycart' ); ?></a>