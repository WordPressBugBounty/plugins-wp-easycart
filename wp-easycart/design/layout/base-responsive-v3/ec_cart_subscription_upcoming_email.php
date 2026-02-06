<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<style type='text/css'>
		<!--
			.style20 {font-family: Arial, Helvetica, sans-serif; font-weight: bold; font-size: 12px; }
			.style22 {font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
			.ec_option_label{font-family: Arial, Helvetica, sans-serif; font-size:11px; font-weight:bold; }
			.ec_option_name{font-family: Arial, Helvetica, sans-serif; font-size:11px; }
		-->
		</style>
	</head>
	<body>
		<table width='539' border='0' align='center'>
			<tr>
				<td colspan='4' align='left' class='style22'>
					<img src='<?php echo $email_logo_url; ?>' style="max-height:250px; max-width:100%; height:auto;">
				</td>
			</tr>
			<tr>
				<td colspan='4' align='left' class='style22'>
					<p><strong><?php echo $GLOBALS['language']->get_text( 'subscription_upcoming', 'subscription_upcoming_email_title' ); ?></strong></p>
					<p><?php echo $GLOBALS['language']->get_text( 'subscription_upcoming', 'upcoming_message_1' ); ?></p>
					<?php if ( false !== $date ) { ?>
					<p><?php echo esc_attr( str_replace( '[total]', $total, str_replace( '[date]', $date, $GLOBALS['language']->get_text( 'subscription_upcoming', 'upcoming_message_2' ) ) ) ); ?></p>
					<?php }?>
					<p><br><?php echo $GLOBALS['language']->get_text( 'subscription_upcoming', 'upcoming_details' ); ?> <?php echo $this->title; ?></p>
					<p><a href="<?php echo esc_url( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $subscription->subscription_id ) ) ); ?>"><?php echo $GLOBALS['language']->get_text( 'subscription_upcoming', 'upcoming_message_link' ); ?></a></p>
				</td>
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