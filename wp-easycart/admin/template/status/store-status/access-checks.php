<?php
/**
 * Diagnostics: whether customer uploads ( $ecds_area uploads ) or paid downloads ( downloads ) can be fetched by URL. Printed by
 * store-status.php when the answer is cached and by wp_easycart_admin_deferred_checks::access_rows() after the page shows
 * ( 6.0.5, admin review M15 ). Expects $status ( wp_easycart_admin_store_status ) and $ecds_area.
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( 'uploads' === $ecds_area ) :
	?>
		<?php
		////////////////////////////
		// Customer Uploads Privacy Check ( 6.0.0 )
		////////////////////////////
		$ecds_uploads_access = $status->uploads_access_check();
		if ( is_array( $ecds_uploads_access ) ) {
			$ecds_uploads_fix_url = add_query_arg(
				array(
					'page'                 => 'wp-easycart-status',
					'subpage'              => 'store-status',
					'ec_admin_form_action' => 'fix-upload-protection',
					'wp_easycart_nonce'    => wp_create_nonce( 'wp-easycart-fix-upload-protection' ),
				),
				admin_url( 'admin.php' )
			);
			$ecds_uploads_nginx = wp_easycart_customer_uploads::nginx_rule();
			if ( 'protected' === $ecds_uploads_access['status'] ) {
				?>
		<div class="ec_status_success"><div class="dashicons-before dashicons-yes"></div><span class="ec_status_label"><?php esc_html_e( 'Customer uploads are private (they cannot be downloaded by URL)', 'wp-easycart' ); ?></span></div>
				<?php
			} elseif ( 'exposed' === $ecds_uploads_access['status'] ) {
				?>
		<div class="ec_status_error"><div class="dashicons-before dashicons-no"></div><span class="ec_status_label">
			<strong><?php esc_html_e( 'Customer uploads can be downloaded by anyone who knows the file address.', 'wp-easycart' ); ?></strong>
			<?php esc_html_e( 'Your web server ignored the protection rules in the uploads folder. On Apache or LiteSpeed, allow .htaccess files for the WordPress folder (AllowOverride). On nginx, which never reads .htaccess, add this rule to the server block for this site and reload nginx:', 'wp-easycart' ); ?>
			<pre class="ecds-code"><?php echo esc_html( $ecds_uploads_nginx ); ?></pre>
			<a href="<?php echo esc_url( $ecds_uploads_fix_url ); ?>"><?php esc_html_e( 'Rewrite protection files and check again', 'wp-easycart' ); ?></a>
		</span></div>
				<?php
			} else {
				?>
		<div class="ec_status_subs">
			<div class="ec_status_subtitles"><div class="dashicons-before dashicons-warning"></div><?php esc_html_e( 'Could not check whether customer uploads are private', 'wp-easycart' ); ?><?php echo ( '' !== $ecds_uploads_access['message'] ) ? ': ' . esc_html( $ecds_uploads_access['message'] ) : ''; ?></div>
			<span class="ec_status_label"><?php esc_html_e( 'Apache, LiteSpeed and IIS are protected by the files WP EasyCart writes into the uploads folder. If this site runs on nginx, add this rule to the server block for this site and reload nginx:', 'wp-easycart' ); ?>
			<pre class="ecds-code"><?php echo esc_html( $ecds_uploads_nginx ); ?></pre>
			<a href="<?php echo esc_url( $ecds_uploads_fix_url ); ?>"><?php esc_html_e( 'Rewrite protection files and check again', 'wp-easycart' ); ?></a></span>
		</div>
				<?php
			}
		}
		?>

<?php
endif;
if ( 'downloads' === $ecds_area ) :
	?>
		<?php
		////////////////////////////
		// Paid Downloads Privacy Check ( 6.0.0 )
		////////////////////////////
		$ecds_downloads_access = method_exists( $status, 'downloads_access_check' ) ? $status->downloads_access_check() : false;
		if ( is_array( $ecds_downloads_access ) ) {
			$ecds_downloads_fix_url = add_query_arg(
				array(
					'page'                 => 'wp-easycart-status',
					'subpage'              => 'store-status',
					'ec_admin_form_action' => 'fix-upload-protection',
					'wp_easycart_nonce'    => wp_create_nonce( 'wp-easycart-fix-upload-protection' ),
				),
				admin_url( 'admin.php' )
			);
			$ecds_downloads_nginx = wp_easycart_customer_uploads::nginx_rule( 'downloads' );
			if ( 'protected' === $ecds_downloads_access['status'] ) {
				?>
		<div class="ec_status_success"><div class="dashicons-before dashicons-yes"></div><span class="ec_status_label"><?php esc_html_e( 'Paid download files are private (they cannot be downloaded by URL)', 'wp-easycart' ); ?></span></div>
				<?php
			} elseif ( 'missing' === $ecds_downloads_access['status'] ) {
				?>
		<div class="ec_status_success"><div class="dashicons-before dashicons-yes"></div><span class="ec_status_label"><?php esc_html_e( 'No downloads folder yet, so there are no download files to protect', 'wp-easycart' ); ?></span></div>
				<?php
			} elseif ( 'exposed' === $ecds_downloads_access['status'] ) {
				?>
		<div class="ec_status_error"><div class="dashicons-before dashicons-no"></div><span class="ec_status_label">
			<strong><?php esc_html_e( 'Paid download files can be downloaded by anyone who knows the file address, without buying them.', 'wp-easycart' ); ?></strong>
			<?php esc_html_e( 'Your web server ignored the protection rules in the downloads folder. On Apache or LiteSpeed, allow .htaccess files for the WordPress folder (AllowOverride). On nginx, which never reads .htaccess, add this rule to the server block for this site and reload nginx:', 'wp-easycart' ); ?>
			<pre class="ecds-code"><?php echo esc_html( $ecds_downloads_nginx ); ?></pre>
			<a href="<?php echo esc_url( $ecds_downloads_fix_url ); ?>"><?php esc_html_e( 'Rewrite protection files and check again', 'wp-easycart' ); ?></a>
		</span></div>
				<?php
			} else {
				?>
		<div class="ec_status_subs">
			<div class="ec_status_subtitles"><div class="dashicons-before dashicons-warning"></div><?php esc_html_e( 'Could not check whether paid download files are private', 'wp-easycart' ); ?><?php echo ( '' !== $ecds_downloads_access['message'] ) ? ': ' . esc_html( $ecds_downloads_access['message'] ) : ''; ?></div>
			<span class="ec_status_label"><?php esc_html_e( 'Apache, LiteSpeed and IIS are protected by the files WP EasyCart writes into the downloads folders. If this site runs on nginx, add this rule to the server block for this site and reload nginx:', 'wp-easycart' ); ?>
			<pre class="ecds-code"><?php echo esc_html( $ecds_downloads_nginx ); ?></pre>
			<a href="<?php echo esc_url( $ecds_downloads_fix_url ); ?>"><?php esc_html_e( 'Rewrite protection files and check again', 'wp-easycart' ); ?></a></span>
		</div>
				<?php
			}
		}
		?>

<?php
endif;
