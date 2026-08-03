<html>
	<head>
		<title><?php echo wp_easycart_language( )->get_text( "account_forgot_password_email", "account_forgot_password_email_title" ); ?></title>
		<style type='text/css'>
			<!--
			.style20 {
				font-family: Arial, Helvetica, sans-serif;
				font-weight: bold;
				font-size: 12px;
			}
			.style22 {
				font-family: Arial, Helvetica, sans-serif;
				font-size: 12px;
			}
			-->
		</style>
	</head>
	<body>
		<table width='539' border='0' align='center'>
			<tr>
				<td colspan='4' align='left' class='style22'>
					<a href="<?php echo esc_url_raw( $store_page ); ?>" target="_blank"><img src="<?php echo esc_attr( $email_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( "name" ) ); ?>" style="max-height:250px; max-width:100%; height:auto;"></a>
				</td>
			</tr>
			<tr>
				<td colspan='4' align='left' class='style22'>
					<p><br>
					<?php echo wp_easycart_language( )->get_text( "account_forgot_password_email", "account_forgot_password_email_dear" ); ?> <?php echo esc_attr( $user->first_name ); ?> <?php echo esc_attr( $user->last_name ); ?>:</p>
					<p><?php echo wp_easycart_language( )->get_text( "account_forgot_password_email", "account_forgot_password_email_reset_intro" ); ?></p>
				</td>
			</tr>
			<tr>
				<td colspan='4' align='center' class='style22'>
					<p>
						<a href="<?php echo esc_url( $reset_url ); ?>" target="_blank" style="display:inline-block; padding:12px 24px; background:#2a2a2a; color:#ffffff; text-decoration:none; font-weight:bold; border-radius:4px;"><?php echo wp_easycart_language( )->get_text( "account_forgot_password_email", "account_forgot_password_email_reset_button" ); ?></a>
					</p>
				</td>
			</tr>
			<tr>
				<td colspan='4' align='left' class='style22'>
					<p><?php echo wp_easycart_language( )->get_text( "account_forgot_password_email", "account_forgot_password_email_reset_expiry" ); ?></p>
					<p style="word-break:break-all;"><a href="<?php echo esc_url( $reset_url ); ?>" target="_blank"><?php echo esc_html( $reset_url ); ?></a></p>
				</td>
			</tr>
			<tr>
				<td colspan='4' class='style22'><p><br>
					<?php echo wp_easycart_language( )->get_text( "account_forgot_password_email", "account_forgot_password_email_thank_you" ); ?></p>
				  <p>&nbsp;</p></td>
			</tr>
			<tr height="10"><td colspan='4'></td></tr>
			<?php if ( get_option( 'ec_option_email_signature_text' ) ) { ?>
			<tr>
				<td class="style22" colspan='4'>
					<?php echo nl2br( esc_html( get_option( 'ec_option_email_signature_text' ) ) ); ?>
				</td>
			</tr>
			<tr height="10"><td colspan='4'></td></tr>
			<?php }?>
			<?php if ( get_option( 'ec_option_email_signature_image' ) ) { ?>
			<tr>
				<td class="style22" colspan='4'>
					<img src="<?php echo esc_url( get_option( 'ec_option_email_signature_image' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( "name" ) ); ?>" style="max-width:100%; height:auto;" />
				</td>
			</tr>
			<tr height="10"><td colspan='4'></td></tr>
			<?php }?>
		</table>
	</body>
</html>
