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
					<a href="<?php echo esc_url_raw( $store_page ); ?>" target="_blank"><img src="<?php echo esc_attr( $email_logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( "name" ) ); ?>" style="max-height:250px; max-width:100%; height:auto;" /></a>
				</td>
			</tr>
			<tr>
				<td colspan='4' align='left' class='style22'>
					<strong><?php echo wp_easycart_language( )->get_text( 'subscription_trial', 'subscription_trial_ending_email_title' ); ?></strong>

					<p><br><?php echo wp_easycart_language( )->get_text( 'subscription_trial', 'trial_ending_message_1' ); ?> <?php echo esc_attr( $this->trial_period_days ); ?> <?php echo wp_easycart_language( )->get_text( 'subscription_trial', 'trial_ending_message_2' ); ?> <?php echo esc_attr( $this->title ); ?> <?php echo wp_easycart_language( )->get_text( 'subscription_trial', 'trial_ending_message_3' ); ?></p>

					<p><?php echo wp_easycart_language( )->get_text( 'subscription_trial', 'trial_ending_message_4' ); ?></p>

				   <p><a href="<?php echo esc_attr( wpeasycart_links()->get_account_page( 'subscription_details', array( 'subscription_id' => (int) $this->subscription_id ) ) ); ?>"><?php echo wp_easycart_language( )->get_text( 'subscription_trial', 'trial_ending_message_link' ); ?></a></p>
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