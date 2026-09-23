<?php
/**
 * Settings › Email ( V2 declaration ).
 *
 * Replaces admin/inc/wp_easycart_admin_email_settings.php and the templates in
 * admin/template/settings/email-setup/. The email health card, delivery checks
 * and send log ( admin/inc/wp_easycart_admin_email_health.php ) are embedded
 * through the "Deliverability" section's render callable. Receipt phrases are
 * language strings ( option ec_option_language_data, section cart_success ),
 * so they carry their own sanitize / on_save instead of one option per field.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------- */
/* Helpers ( guarded: the file is included once per request )              */
/* ---------------------------------------------------------------------- */

if ( ! function_exists( 'ecst_email_render_pdf_moved' ) ) {
	/** 6.0.1: where the PDF settings went ( Settings › Documents › Invoice PDF ). */
	function ecst_email_render_pdf_moved() {
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=wp-easycart-settings&subpage=documents#ecst-sec-invoice' ) : '';
		echo '<p class="ecst-moved">' . esc_html__( 'The invoice and receipt PDF is a document now: its business details, file name, paper size and heading, which emails carry it and what it shows are all on Settings › Documents › Invoice PDF, with a live preview.', 'wp-easycart' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Invoice PDF', 'wp-easycart' ) . ' &rarr;</a></p>';
	}
}

if ( ! function_exists( 'ecst_email_render_deliverability' ) ) {
	/** Health card + pre-flight checks + log, exactly what the legacy page showed at the top. */
	function ecst_email_render_deliverability() {
		if ( ! function_exists( 'wp_easycart_admin_email_health_card' ) ) {
			echo '<p>' . esc_html__( 'Email health reporting is not available on this install.', 'wp-easycart' ) . '</p>';
			return;
		}
		echo '<div class="ecem-wrap">';
		wp_easycart_admin_email_health_card( true );
		wp_easycart_admin_email_checks_panel();
		if ( function_exists( 'wp_easycart_admin_email_queue_panel' ) ) {
			wp_easycart_admin_email_queue_panel(); /* 6.0.0: what is waiting to be retried, "send queue now" and the failed-send simulation. */
		}
		wp_easycart_admin_email_log_panel();
		echo '</div>';
	}
}

if ( ! function_exists( 'ecst_email_sanitize_from' ) ) {
	/** Legacy accepted "Name <address>" or a bare address; keep both forms ( ported from save_email_settings ). */
	function ecst_email_sanitize_from( $raw ) {
		$raw = trim( (string) $raw );
		if ( preg_match( '/^(.*)<(.*)>$/', $raw, $parts ) && 3 === count( $parts ) ) {
			return sanitize_text_field( $parts[1] ) . ' <' . sanitize_email( $parts[2] ) . '>';
		}
		return sanitize_text_field( $raw );
	}
}

if ( ! function_exists( 'ecst_email_validate_from' ) ) {
	function ecst_email_validate_from( $value ) {
		$value = trim( (string) $value );
		$address = preg_match( '/<([^>]*)>/', $value, $m ) ? trim( $m[1] ) : $value;
		if ( '' === $address || ! is_email( $address ) ) {
			return __( 'That does not look like a valid email address. Emails without a valid sender are rejected by most mail servers.', 'wp-easycart' );
		}
		return '';
	}
}

if ( ! function_exists( 'ecst_email_sanitize_list' ) ) {
	/** Comma-separated recipient list. Stored as text like the legacy page; spacing normalised. */
	function ecst_email_sanitize_list( $raw ) {
		$parts = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( (string) $raw ) ) ), 'strlen' );
		return implode( ', ', $parts );
	}
}

if ( ! function_exists( 'ecst_email_validate_list' ) ) {
	function ecst_email_validate_list( $value ) {
		$bad = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' ) as $part ) {
			$address = preg_match( '/<([^>]*)>/', $part, $m ) ? trim( $m[1] ) : $part;
			if ( ! is_email( $address ) ) {
				$bad[] = $part;
			}
		}
		if ( $bad ) {
			return sprintf( __( 'Not a valid address: %s', 'wp-easycart' ), implode( ', ', $bad ) );
		}
		return '';
	}
}

if ( ! function_exists( 'ecst_email_test_recipient' ) ) {
	/** Where every test email goes: the address typed below, else the signed-in admin, else the site admin address. */
	function ecst_email_test_recipient() {
		$to = trim( (string) get_option( 'ec_option_email_test_recipient', '' ) ); /* 6.0.0: one address for every test button. */
		if ( function_exists( 'is_email' ) && ! is_email( $to ) ) {
			$to = '';
		}
		if ( '' === $to && function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			if ( $user && isset( $user->user_email ) ) {
				$to = (string) $user->user_email;
			}
		}
		if ( '' === $to && function_exists( 'get_option' ) ) {
			$to = (string) get_option( 'admin_email' );
		}
		return $to;
	}
}

if ( ! function_exists( 'ecst_email_send_test' ) ) {
	/**
	 * Ported from wp_easycart_admin_email_settings::wpeasycart_smtp_test1() / test2(),
	 * addressed to the current admin instead of the store notification list.
	 *
	 * @param string $channel 'order' | 'account'
	 * @return string|WP_Error
	 */
	function ecst_email_send_test( $channel ) {
		$to = ecst_email_test_recipient();
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'ecst_email_no_recipient', __( 'Your WordPress user has no valid email address to send the test to.', 'wp-easycart' ) );
		}
		if ( 'order' === $channel ) {
			$subject = __( 'WP EasyCart Order Receipt Email Test', 'wp-easycart' );
			$message = __( 'This is a simple test from WP EasyCart to make sure your email setup is correct. If you receive this your order type emails should be working properly!', 'wp-easycart' );
			$from    = stripslashes( get_option( 'ec_option_order_from_email' ) );
		} else {
			$subject = __( 'WP EasyCart Account Test Email', 'wp-easycart' );
			$message = __( 'This is a simple test from WP EasyCart to make sure your email setup is correct. If you receive this your account type emails should be working properly!', 'wp-easycart' );
			$from    = stripslashes( get_option( 'ec_option_password_from_email' ) );
		}
		if ( class_exists( 'wp_easycart_email_design' ) ) { /* 6.0.0: shared email design. */
			$message = wp_easycart_email_design::wrap(
				wp_easycart_email_design::get_paragraph( esc_html( $message ), array( 'margin' => '0' ) ),
				array(
					'title'     => $subject,
					'heading'   => $subject,
					'preheader' => $message,
					'eyebrow'   => __( 'Test email', 'wp-easycart' ),
				)
			);
		}
		$log = class_exists( 'ec_email' ) && method_exists( 'ec_email', 'context' );
		if ( $log ) {
			ec_email::context( 'test', 0 );
		}
		$error = false;
		if ( '0' === (string) get_option( 'ec_option_use_wp_mail' ) && class_exists( 'wpeasycart_mailer' ) ) {
			$mailer = new wpeasycart_mailer();
			$error  = ( 'order' === $channel ) ? $mailer->send_order_email( $to, $subject, $message ) : $mailer->send_customer_email( $to, $subject, $message );
		} else {
			$headers = array(
				'MIME-Version: 1.0',
				'Content-Type: text/html; charset=utf-8',
				'From: ' . $from,
				'Reply-To: ' . $from,
				'X-Mailer: PHP/' . phpversion(),
			);
			if ( ! wp_mail( $to, $subject, $message, implode( "\r\n", $headers ) ) ) {
				$error = __( 'wp_mail() returned false. The email log above shows the mailer error.', 'wp-easycart' );
			}
		}
		if ( $log ) {
			ec_email::context( null );
		}
		if ( false !== $error && '' !== (string) $error ) {
			return new WP_Error( 'ecst_email_test_failed', sprintf( __( 'Test email to %1$s failed: %2$s', 'wp-easycart' ), $to, (string) $error ) );
		}
		return sprintf( __( 'Test email sent to %s. Check the inbox and the spam folder.', 'wp-easycart' ), $to );
	}
}

if ( ! function_exists( 'ecst_email_subscription_test' ) ) {
	/**
	 * Send one subscription email to the merchant, rendered from the real template ( 6.0.0 ).
	 *
	 * @param string $id trial_start | trial_ending | upcoming | ended | failed.
	 * @return string|WP_Error
	 */
	function ecst_email_subscription_test( $id ) {
		if ( ! class_exists( 'wp_easycart_admin_email_tests' ) && defined( 'EC_PLUGIN_DIRECTORY' ) && file_exists( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_email_tests.php' ) ) {
			require_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_email_tests.php';
		}
		if ( ! class_exists( 'wp_easycart_admin_email_tests' ) ) {
			return new WP_Error( 'ecst_email_tests_missing', __( 'The email test builder is missing. Reinstall WP EasyCart.', 'wp-easycart' ) );
		}
		return wp_easycart_admin_email_tests::send( $id );
	}
	function ecst_email_test_subscription_trial_start() {
		return ecst_email_subscription_test( 'trial_start' );
	}
	function ecst_email_test_subscription_trial_ending() {
		return ecst_email_subscription_test( 'trial_ending' );
	}
	function ecst_email_test_subscription_upcoming() {
		return ecst_email_subscription_test( 'upcoming' );
	}
	function ecst_email_test_subscription_ended() {
		return ecst_email_subscription_test( 'ended' );
	}
	function ecst_email_test_subscription_failed() {
		return ecst_email_subscription_test( 'failed' );
	}
}

if ( ! function_exists( 'ecst_email_sanitize_test_recipient' ) ) {
	/** Empty ( use the signed-in admin ) or one valid address. */
	function ecst_email_sanitize_test_recipient( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		return ( '' === $value ) ? '' : sanitize_email( $value );
	}
}

if ( ! function_exists( 'ecst_email_validate_test_recipient' ) ) {
	function ecst_email_validate_test_recipient( $value ) {
		if ( '' !== (string) $value && ! is_email( $value ) ) {
			return __( 'That does not look like a valid email address, so tests will go to your own address instead.', 'wp-easycart' );
		}
		return '';
	}
}

if ( ! function_exists( 'ecst_email_test_order' ) ) {
	function ecst_email_test_order() {
		return ecst_email_send_test( 'order' );
	}
}

if ( ! function_exists( 'ecst_email_test_account' ) ) {
	function ecst_email_test_account() {
		return ecst_email_send_test( 'account' );
	}
}

if ( ! function_exists( 'ecst_email_next_order_id' ) ) {
	/**
	 * Live AUTO_INCREMENT of ec_order ( the legacy page read this, not an option ). '' when
	 * unavailable. The row declares it as its 'current' callable, so INFORMATION_SCHEMA is
	 * read only when the row renders or saves. Cached for the request.
	 */
	function ecst_email_next_order_id() {
		static $next = null;
		if ( null !== $next ) {
			return $next;
		}
		global $wpdb;
		$next = '';
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return $next;
		}
		$found = $wpdb->get_var( $wpdb->prepare( 'SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s', $wpdb->dbname, 'ec_order' ) );
		$next  = $found ? (int) $found : '';
		return $next;
	}
}

if ( ! function_exists( 'ecst_email_save_order_id' ) ) {
	/** Ported side effect: ALTER TABLE ec_order AUTO_INCREMENT, capped like the legacy handler. */
	function ecst_email_save_order_id( $value, $old, $field ) {
		global $wpdb;
		$value = (int) $value;
		if ( $value < 1 || ! isset( $wpdb ) ) {
			return;
		}
		$value = min( $value, 2140000000 );
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE ec_order AUTO_INCREMENT = %d', $value ) );
		/* The number lives in the table, not in an option: drop the copy the batch save wrote. */
		delete_option( $field['key'] );
	}
}

if ( ! function_exists( 'ecst_email_validate_order_id' ) ) {
	function ecst_email_validate_order_id( $value ) {
		$next = ecst_email_next_order_id();
		if ( '' !== $next && (int) $value < (int) $next ) {
			return sprintf( __( 'Order numbers can only go up. The next order will still be #%d.', 'wp-easycart' ), (int) $next );
		}
		return '';
	}
}

if ( ! function_exists( 'ecst_email_upload_access_saved' ) ) {
	/** Leaving private-link mode revokes the links already emailed ( wp_easycart_admin_order_uploads::on_access_saved ). */
	function ecst_email_upload_access_saved( $value, $old, $field ) {
		if ( class_exists( 'wp_easycart_admin_order_uploads' ) ) {
			wp_easycart_admin_order_uploads::on_access_saved( $value, $old );
		}
	}
}

if ( ! function_exists( 'ecst_email_revoke_upload_links' ) ) {
	/** Rotates the private upload link secret so every link already emailed stops working. */
	function ecst_email_revoke_upload_links() {
		if ( ! class_exists( 'wp_easycart_admin_order_uploads' ) ) {
			return new WP_Error( 'ecst_email_uploads_missing', __( 'Customer upload links are not available on this install.', 'wp-easycart' ) );
		}
		wp_easycart_admin_order_uploads::rotate_secret();
		return __( 'All private upload links sent so far have been revoked. New order emails get fresh links.', 'wp-easycart' );
	}
}

if ( ! function_exists( 'ecst_email_phrase_file' ) ) {
	/** Language file the legacy page edited: the store language option. */
	function ecst_email_phrase_file() {
		$file = function_exists( 'get_option' ) ? (string) get_option( 'ec_option_language' ) : '';
		return '' !== $file ? $file : 'en-us';
	}
}

if ( ! function_exists( 'ecst_email_phrase_items' ) ) {
	/** key => { title, value } for the cart_success language section, from the live data or the shipped en-us file. */
	function ecst_email_phrase_items() {
		$file = ecst_email_phrase_file();
		if ( function_exists( 'wp_easycart_language' ) ) {
			$data = wp_easycart_language()->get_language_data();
			if ( is_object( $data ) && isset( $data->{$file} ) && isset( $data->{$file}->options->cart_success->options ) ) {
				return get_object_vars( $data->{$file}->options->cart_success->options );
			}
		}
		$path = EC_PLUGIN_DIRECTORY . '/inc/language/en-us.txt';
		if ( file_exists( $path ) ) {
			$json = json_decode( (string) file_get_contents( $path ) ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local plugin file.
			if ( is_object( $json ) && isset( $json->options->cart_success->options ) ) {
				return get_object_vars( $json->options->cart_success->options );
			}
		}
		return array();
	}
}

if ( ! function_exists( 'ecst_email_sanitize_phrase' ) ) {
	/** Same filter the legacy save used ( wp_easycart_escape_html ). */
	function ecst_email_sanitize_phrase( $raw ) {
		$raw = (string) $raw;
		return function_exists( 'wp_easycart_escape_html' ) ? wp_easycart_escape_html( $raw ) : wp_kses_post( $raw );
	}
}

if ( ! function_exists( 'ecst_email_save_phrase' ) ) {
	/** Write the phrase into ec_option_language_data ( ported from save_language_text ) and drop the stray option. */
	function ecst_email_save_phrase( $value, $old, $field ) {
		if ( function_exists( 'wp_easycart_language' ) ) {
			$file = ecst_email_phrase_file();
			$data = wp_easycart_language()->get_language_data();
			if ( is_object( $data ) && isset( $data->{$file} ) && isset( $data->{$file}->options->cart_success->options->{$field['key']} ) ) {
				wp_easycart_language()->update_language_item( $file, 'cart_success', $field['key'], $value );
			}
		}
		delete_option( $field['key'] );
	}
}

if ( ! function_exists( 'ecst_email_phrase_fields' ) ) {
	/** One text/textarea row per phrase. Receipt-email phrases stay visible; success-page and other email phrases fold as advanced. */
	function ecst_email_phrase_fields() {
		$labels = array(
			'cart_payment_receipt_title'                    => __( 'Receipt subject line', 'wp-easycart' ),
			'cart_payment_receipt_order_details_link'       => __( 'Order details link', 'wp-easycart' ),
			'cart_payment_receipt_subscription_details_link' => __( 'Subscription details link', 'wp-easycart' ),
			'cart_payment_complete_line_1'                  => __( 'Greeting', 'wp-easycart' ),
			'cart_payment_complete_line_2'                  => __( 'Thank-you line', 'wp-easycart' ),
			'cart_payment_complete_line_3'                  => __( 'Summary intro', 'wp-easycart' ),
			'cart_payment_complete_line_4'                  => __( 'Keep-this-record line', 'wp-easycart' ),
			'cart_payment_complete_line_5'                  => __( 'Membership content link', 'wp-easycart' ),
			'cart_payment_complete_click_here'              => __( '“Click here” link text', 'wp-easycart' ),
			'cart_payment_complete_to_view_order'           => __( '“to view your order” text', 'wp-easycart' ),
			'cart_payment_complete_billing_label'           => __( 'Billing address heading', 'wp-easycart' ),
			'cart_payment_complete_shipping_label'          => __( 'Shipping address heading', 'wp-easycart' ),
			'cart_payment_complete_details_header_1'        => __( 'Items column: product', 'wp-easycart' ),
			'cart_payment_complete_details_header_2'        => __( 'Items column: quantity', 'wp-easycart' ),
			'cart_payment_complete_details_header_3'        => __( 'Items column: price', 'wp-easycart' ),
			'cart_payment_complete_details_header_4'        => __( 'Items column: extended price', 'wp-easycart' ),
			'cart_payment_complete_order_totals_subtotal'   => __( 'Totals: subtotal', 'wp-easycart' ),
			'cart_payment_complete_order_totals_shipping'   => __( 'Totals: shipping', 'wp-easycart' ),
			'cart_payment_complete_order_totals_tax'        => __( 'Totals: tax', 'wp-easycart' ),
			'cart_payment_complete_order_totals_discount'   => __( 'Totals: discount', 'wp-easycart' ),
			'cart_payment_complete_order_totals_vat'        => __( 'Totals: VAT', 'wp-easycart' ),
			'cart_payment_complete_order_totals_duty'       => __( 'Totals: duty', 'wp-easycart' ),
			'cart_payment_complete_order_totals_grand_total' => __( 'Totals: order total', 'wp-easycart' ),
			'cart_payment_complete_order_totals_balance_left' => __( 'Totals: balance required', 'wp-easycart' ),
			'cart_payment_complete_bottom_line_1'           => __( 'Closing line 1', 'wp-easycart' ),
			'cart_payment_complete_bottom_line_2'           => __( 'Closing line 2', 'wp-easycart' ),
			'cart_payment_view_order'                       => __( 'View order button', 'wp-easycart' ),
			'cart_success_thank_you_title'                  => __( 'Success page: thank-you heading', 'wp-easycart' ),
			'cart_success_order_number_is'                  => __( 'Success page: order number label', 'wp-easycart' ),
			'cart_success_will_receive_email'               => __( 'Success page: email confirmation note', 'wp-easycart' ),
			'cart_success_print_receipt_text'               => __( 'Success page: print receipt link', 'wp-easycart' ),
			'cart_success_save_order_text'                  => __( 'Success page: save your details prompt', 'wp-easycart' ),
			'cart_success_create_password'                  => __( 'Success page: create password label', 'wp-easycart' ),
			'cart_success_verify_password'                  => __( 'Success page: verify password label', 'wp-easycart' ),
			'cart_success_password_hint'                    => __( 'Success page: password hint', 'wp-easycart' ),
			'cart_success_create_account'                   => __( 'Success page: create account button', 'wp-easycart' ),
			'cart_success_view_downloads'                   => __( 'Success page: view downloads button', 'wp-easycart' ),
			'cart_downloads_available'                      => __( 'Downloads available note', 'wp-easycart' ),
			'cart_downloads_unavailable'                    => __( 'Downloads pending payment note', 'wp-easycart' ),
			'cart_downloads_click_to_go'                    => __( 'Downloads: “go there now” link', 'wp-easycart' ),
			'cart_giftcards_unavailable'                    => __( 'Gift cards pending payment note', 'wp-easycart' ),
			'cart_giftcard_receipt_title'                   => __( 'Gift card email subject', 'wp-easycart' ),
			'cart_giftcard_receipt_header'                  => __( 'Gift card email heading', 'wp-easycart' ),
			'cart_giftcard_receipt_to'                      => __( 'Gift card: “To” label', 'wp-easycart' ),
			'cart_giftcard_receipt_from'                    => __( 'Gift card: “From” label', 'wp-easycart' ),
			'cart_giftcard_receipt_id'                      => __( 'Gift card: ID label', 'wp-easycart' ),
			'cart_giftcard_receipt_amount'                  => __( 'Gift card: amount label', 'wp-easycart' ),
			'cart_giftcard_receipt_message'                 => __( 'Gift card: redeem instructions', 'wp-easycart' ),
			'cart_invoice_line_1'                           => __( 'Invoice: payment note', 'wp-easycart' ),
			'cart_invoice_pay_online'                       => __( 'Invoice: “to pay online” text', 'wp-easycart' ),
			'cart_invoice_items_label'                      => __( 'Invoice: items heading', 'wp-easycart' ),
			'cart_text_notification_title'                  => __( 'Text updates: title', 'wp-easycart' ),
			'cart_text_notification_description'            => __( 'Text updates: description', 'wp-easycart' ),
			'cart_text_notification_placeholder'            => __( 'Text updates: phone placeholder', 'wp-easycart' ),
			'cart_text_notification_success'                => __( 'Text updates: success message', 'wp-easycart' ),
			'cart_text_notification_error'                  => __( 'Text updates: error message', 'wp-easycart' ),
			'cart_text_notification_button'                 => __( 'Text updates: button', 'wp-easycart' ),
			'subscription_success_thank_you_title'          => __( 'Subscription success heading', 'wp-easycart' ),
			'subscription_success_thank_you_info'           => __( 'Subscription success note', 'wp-easycart' ),
			'cart_refund_email_title'                       => __( 'Refund email subject', 'wp-easycart' ),
			'cart_refund_email_message'                     => __( 'Refund email message', 'wp-easycart' ),
		);
		/* Phrases that appear in the receipt email itself stay visible; everything else is folded. */
		$visible = array(
			'cart_payment_receipt_title', 'cart_payment_receipt_order_details_link', 'cart_payment_receipt_subscription_details_link',
			'cart_payment_complete_line_1', 'cart_payment_complete_line_2', 'cart_payment_complete_line_3', 'cart_payment_complete_line_4', 'cart_payment_complete_line_5',
			'cart_payment_complete_click_here', 'cart_payment_complete_to_view_order', 'cart_payment_complete_billing_label', 'cart_payment_complete_shipping_label',
			'cart_payment_complete_details_header_1', 'cart_payment_complete_details_header_2', 'cart_payment_complete_details_header_3', 'cart_payment_complete_details_header_4',
			'cart_payment_complete_order_totals_subtotal', 'cart_payment_complete_order_totals_shipping', 'cart_payment_complete_order_totals_tax', 'cart_payment_complete_order_totals_discount',
			'cart_payment_complete_order_totals_vat', 'cart_payment_complete_order_totals_duty', 'cart_payment_complete_order_totals_grand_total', 'cart_payment_complete_order_totals_balance_left',
			'cart_payment_complete_bottom_line_1', 'cart_payment_complete_bottom_line_2', 'cart_payment_view_order',
		);
		$fields = array();
		foreach ( ecst_email_phrase_items() as $key => $item ) {
			$key = sanitize_key( $key );
			if ( '' === $key ) {
				continue;
			}
			$title = ( is_object( $item ) && isset( $item->title ) ) ? (string) $item->title : $key;
			$value = ( is_object( $item ) && isset( $item->value ) ) ? (string) $item->value : '';
			$long  = strlen( $value ) > 60 || preg_match( '/message|note|description|info|line 1|line 2|line 3|line 4/i', $title );
			$words = array( 'phrase', 'wording', 'language', 'receipt' );
			if ( false !== strpos( $key, 'giftcard' ) ) {
				$words[] = 'gift card';
			} elseif ( false !== strpos( $key, 'invoice' ) ) {
				$words[] = 'invoice';
			} elseif ( false !== strpos( $key, 'refund' ) ) {
				$words[] = 'refund';
			} elseif ( false !== strpos( $key, 'text_notification' ) ) {
				$words[] = 'sms';
			} elseif ( false !== strpos( $key, 'download' ) ) {
				$words[] = 'downloads';
			} elseif ( false !== strpos( $key, 'subscription' ) ) {
				$words[] = 'subscription';
			}
			$fields[ $key ] = array(
				'type'     => $long ? 'textarea' : 'text',
				'rows'     => 2,
				'label'    => isset( $labels[ $key ] ) ? $labels[ $key ] : $title,
				'desc'     => '',
				'default'  => $value,
				'advanced' => ! in_array( $key, $visible, true ),
				'sanitize' => 'ecst_email_sanitize_phrase',
				'on_save'  => 'ecst_email_save_phrase',
				'keywords' => $words,
				'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Phrases', 'label' => $title ),
			);
		}
		return $fields;
	}
}

$ecst_email_test_to = ecst_email_test_recipient();
$ecst_email_test_desc = '' !== $ecst_email_test_to
	? sprintf( __( 'Sends a short test message to %s so you can confirm delivery.', 'wp-easycart' ), $ecst_email_test_to )
	: __( 'Sends a short test message to your own address so you can confirm delivery.', 'wp-easycart' );

$ecst_email_smtp_encryption = array(
	'none' => __( 'None', 'wp-easycart' ),
	'ssl'  => 'SSL',
	'tls'  => 'TLS',
);

return array(
	'slug'        => 'email-setup',
	'title'       => __( 'Email', 'wp-easycart' ),
	'description' => __( 'How store emails are sent, who they come from, what receipts include, and the wording shoppers read.', 'wp-easycart' ),
	'group'       => 'customize',
	'icon'        => 'email',
	'docs'        => array( 'settings', 'email-setup', 'email-settings' ),
	'legacy'      => array( 'email-setup', 'email' ),
	'upsell'      => 'default',
	'sections'    => array(

		'deliverability' => array(
			'title'  => __( 'Deliverability', 'wp-easycart' ),
			'hint'   => __( 'Delivery rate, pre-flight checks and the send log', 'wp-easycart' ),
			'fields' => array(),
			'render' => 'ecst_email_render_deliverability',
		),

		'sender' => array(
			'title'  => __( 'Sender', 'wp-easycart' ),
			'hint'   => __( 'How mail leaves the site and how every email is branded', 'wp-easycart' ),
			'fields' => array(
				'ec_option_use_wp_mail' => array(
					'type'     => 'pills',
					'label'    => __( 'Send method', 'wp-easycart' ),
					'desc'     => __( 'WordPress mail hands delivery to WordPress and any SMTP or API mail plugin you run — the recommended setup. The built-in mailer sends straight from this server, with optional SMTP per email type below.', 'wp-easycart' ),
					'default'  => '1',
					'options'  => array(
						'1' => __( 'WordPress mail', 'wp-easycart' ),
						'0' => __( 'EasyCart built-in mailer', 'wp-easycart' ),
					),
					'keywords' => array( 'wp_mail', 'smtp', 'phpmailer', 'transport', 'delivery' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Global Email Settings', 'label' => 'Use WordPress Mail' ),
				),
				'ec_option_bcc_email_addresses' => array(
					'type'        => 'text',
					'label'       => __( 'Store notification addresses', 'wp-easycart' ),
					'desc'        => __( 'Gets a copy of every order, refund, stock, review and new-account email. Separate several addresses with commas.', 'wp-easycart' ),
					'default'     => 'youremail@url.com',
					'placeholder' => 'orders@example.com, owner@example.com',
					'sanitize'    => 'ecst_email_sanitize_list',
					'validate'    => 'ecst_email_validate_list',
					'keywords'    => array( 'admin email', 'bcc', 'copy', 'notifications', 'recipients' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'Admin Email Address' ),
				),
				'ec_option_email_logo' => array(
					'type'        => 'url',
					'label'       => __( 'Email logo', 'wp-easycart' ),
					'desc'        => __( 'Shown at the top of every EasyCart email, and on documents whose profile has no logo of its own. Choose an image from the Media Library, or paste a URL.', 'wp-easycart' ),
					'placeholder' => 'https://',
					'media'       => true,
					'keywords'    => array( 'logo', 'branding', 'header image', 'template' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'Email Logo' ),
				),
				/* 6.0.1: how the header image is sized and placed in every email. */
				'ec_option_email_logo_max_width' => array(
					'type'     => 'number',
					'label'    => __( 'Logo width', 'wp-easycart' ),
					'desc'     => __( 'The widest the logo may be, as a share of the email. 100 fills the full width.', 'wp-easycart' ),
					'default'  => 40,
					'min'      => 5,
					'max'      => 100,
					'step'     => 1,
					'unit'     => '%',
					'keywords' => array( 'logo width', 'email logo size', 'header image' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => '( new in 6.0.1 )' ),
				),
				'ec_option_email_logo_max_height' => array(
					'type'     => 'number',
					'label'    => __( 'Logo height limit', 'wp-easycart' ),
					'desc'     => __( 'Keeps a tall logo in check. 0 lets it be as tall as the width allows.', 'wp-easycart' ),
					'default'  => 80,
					'min'      => 0,
					'max'      => 600,
					'step'     => 1,
					'unit'     => 'px',
					'keywords' => array( 'logo height', 'max height', 'header image' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => '( new in 6.0.1 )' ),
				),
				'ec_option_email_logo_align' => array(
					'type'     => 'pills',
					'label'    => __( 'Logo position', 'wp-easycart' ),
					'desc'     => __( 'Where the logo sits at the top of every email.', 'wp-easycart' ),
					'default'  => 'center',
					'options'  => array(
						'left'   => __( 'Left', 'wp-easycart' ),
						'center' => __( 'Center', 'wp-easycart' ),
						'right'  => __( 'Right', 'wp-easycart' ),
					),
					'keywords' => array( 'logo alignment', 'centered', 'header image' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => '( new in 6.0.1 )' ),
				),
				/* 6.0.1: printed at the foot of a receipt, shipped email or packing slip whose profile has Store address on. */
				'ec_option_store_address' => array(
					'type'        => 'textarea',
					'rows'        => 3,
					'label'       => __( 'Store address', 'wp-easycart' ),
					'desc'        => __( 'Your store’s postal address. Receipts, shipped emails and packing slips print it in their footer when their profile on Settings › Documents has Store address on.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => "Store Name\n1 Example Street\nSpringfield, IL 62701",
					'keywords'    => array( 'store address', 'business address', 'return address', 'company address', 'footer', 'packing slip' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'Sender', 'label' => '( new in 6.0.1 )' ),
				),
				'ec_option_email_signature_text' => array(
					'type'        => 'textarea',
					'rows'        => 3,
					'label'       => __( 'Email signature text', 'wp-easycart' ),
					'desc'        => __( 'Closing text at the foot of every automatic email — your address, support hours, a thank-you.', 'wp-easycart' ),
					'placeholder' => __( 'Enter a customer message', 'wp-easycart' ),
					'pro'         => true,
					'keywords'    => array( 'signature', 'footer', 'closing', 'branding' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'Global Email Settings', 'label' => 'Email Signature: Text' ),
				),
				'ec_option_email_signature_image' => array(
					'type'        => 'url',
					'label'       => __( 'Email signature image', 'wp-easycart' ),
					'desc'        => __( 'Image shown under the signature text at the foot of every automatic email. Choose one from the Media Library, or paste a URL.', 'wp-easycart' ),
					'placeholder' => 'https://',
					'media'       => true,
					'pro'         => true,
					'keywords'    => array( 'signature', 'footer', 'image', 'branding' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'Global Email Settings', 'label' => 'Email Signature: Image' ),
				),
				/* 6.0.1: the footer ( signature ) image's size, like the logo's. A document profile can use its own ( Settings › Documents, Logo & footer ). */
				'ec_option_email_signature_image_max_width' => array(
					'type'     => 'number',
					'label'    => __( 'Signature image width', 'wp-easycart' ),
					'desc'     => __( 'The widest the signature image may be, as a share of the email. 100 fills the full width.', 'wp-easycart' ),
					'default'  => 100,
					'min'      => 5,
					'max'      => 100,
					'step'     => 1,
					'unit'     => '%',
					'pro'      => true,
					'keywords' => array( 'signature image size', 'footer image width' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Global Email Settings', 'label' => '( new in 6.0.1 )' ),
				),
				'ec_option_email_signature_image_max_height' => array(
					'type'     => 'number',
					'label'    => __( 'Signature image height limit', 'wp-easycart' ),
					'desc'     => __( 'Keeps a tall image in check. 0 lets it be as tall as the width allows.', 'wp-easycart' ),
					'default'  => 0,
					'min'      => 0,
					'max'      => 600,
					'step'     => 1,
					'unit'     => 'px',
					'pro'      => true,
					'keywords' => array( 'signature image height', 'footer image height' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Global Email Settings', 'label' => '( new in 6.0.1 )' ),
				),
			),
		),

		'order-emails' => array(
			'title'   => __( 'Order emails', 'wp-easycart' ),
			'hint'    => __( 'Receipts, invoices, refunds and store notifications', 'wp-easycart' ),
			'fields'  => array(
				'ec_option_order_from_email' => array(
					'type'        => 'text',
					'label'       => __( 'Order emails come from', 'wp-easycart' ),
					'desc'        => __( 'The sender shoppers see on receipts, invoices and refunds. Use “Store Name <orders@yourdomain.com>” to show a name. Must be on a domain you control.', 'wp-easycart' ),
					'default'     => 'youremail@url.com',
					'placeholder' => 'Store Name <orders@example.com>',
					'sanitize'    => 'ecst_email_sanitize_from',
					'validate'    => 'ecst_email_validate_from',
					'keywords'    => array( 'from address', 'sender', 'reply-to', 'receipt' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'Order Receipt From Email Address' ),
				),
				'ec_option_order_use_smtp' => array(
					'type'      => 'toggle',
					'label'     => __( 'Send order emails over SMTP', 'wp-easycart' ),
					'desc'      => __( 'The built-in mailer connects to the SMTP server below instead of using PHP mail() on this server.', 'wp-easycart' ),
					'parent'    => 'ec_option_use_wp_mail',
					'show_when' => '0',
					'keywords'  => array( 'smtp', 'phpmailer', 'mail server' ),
					'legacy'    => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'Use SMTP' ),
				),
				'ec_option_order_from_smtp_host' => array(
					'type'        => 'text',
					'label'       => __( 'SMTP host', 'wp-easycart' ),
					'desc'        => __( 'Your mail provider’s outgoing server, for example smtp.yourprovider.com.', 'wp-easycart' ),
					'placeholder' => 'smtp.example.com',
					'parent'      => 'ec_option_order_use_smtp',
					'keywords'    => array( 'smtp', 'server', 'hostname' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'SMTP Host' ),
				),
				'ec_option_order_from_smtp_port' => array(
					'type'     => 'number',
					'label'    => __( 'SMTP port', 'wp-easycart' ),
					'desc'     => __( 'Usually 465 for SSL, 587 for TLS, 25 for no encryption.', 'wp-easycart' ),
					'default'  => '465',
					'min'      => 1,
					'max'      => 65535,
					'step'     => 1,
					'parent'   => 'ec_option_order_use_smtp',
					'keywords' => array( 'smtp', 'port' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'SMTP Port Number' ),
				),
				'ec_option_order_from_smtp_encryption_type' => array(
					'type'     => 'select',
					'label'    => __( 'SMTP encryption', 'wp-easycart' ),
					'desc'     => __( 'Match what your provider lists for the port above. A mismatch is the most common reason SMTP sends fail.', 'wp-easycart' ),
					'default'  => 'ssl',
					'options'  => $ecst_email_smtp_encryption,
					'parent'   => 'ec_option_order_use_smtp',
					'keywords' => array( 'smtp', 'ssl', 'tls', 'security' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'Use SMTP ( encryption select )' ),
				),
				'ec_option_order_from_smtp_username' => array(
					'type'     => 'text',
					'label'    => __( 'SMTP username', 'wp-easycart' ),
					'desc'     => __( 'Often the full mailbox address.', 'wp-easycart' ),
					'parent'   => 'ec_option_order_use_smtp',
					'keywords' => array( 'smtp', 'login', 'user' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'SMTP User Name' ),
				),
				'ec_option_order_from_smtp_password' => array(
					'type'     => 'password',
					'label'    => __( 'SMTP password', 'wp-easycart' ),
					'desc'     => __( 'Only sent to your SMTP server. Use an app password if your provider offers one.', 'wp-easycart' ),
					'parent'   => 'ec_option_order_use_smtp',
					'keywords' => array( 'smtp', 'password', 'credentials' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'SMTP Password' ),
				),
				'ec_option_show_email_on_receipt' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the shopper’s email address on receipts', 'wp-easycart' ),
					'desc'     => __( 'Adds the customer’s email address beside their order details in receipt and refund emails.', 'wp-easycart' ),
					'keywords' => array( 'receipt', 'customer email', 'order details' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'Email on Receipt' ),
				),
				'ec_option_show_image_on_receipt' => array(
					'type'     => 'toggle',
					'label'    => __( 'Product images on receipts', 'wp-easycart' ),
					'desc'     => __( 'Each line item shows its product image in receipt, invoice, gift card and refund emails and on the printable receipt.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'receipt', 'images', 'thumbnails', 'line items' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'Product Images on Receipt' ),
				),
				'ec_option_upload_link_access' => array(
					'type'     => 'select',
					'label'    => __( 'Customer upload links in admin emails', 'wp-easycart' ),
					'desc'     => __( 'Sign-in required keeps uploaded files to administrators and order managers; a private link lets anyone who receives an order or product inquiry notification from the store download the file until the link expires.', 'wp-easycart' ),
					'help'     => __( 'Shopper receipts and the copy of an inquiry sent to the shopper never include these links. Switching back to sign-in required cancels every private link already sent.', 'wp-easycart' ),
					'default'  => 'login',
					'options'  => array(
						'login'   => __( 'Sign-in required (administrators and order managers)', 'wp-easycart' ),
						'private' => __( 'Anyone with the link (expiring private link)', 'wp-easycart' ),
					),
					'on_save'  => 'ecst_email_upload_access_saved',
					'keywords' => array( 'file upload', 'customer file', 'upload modifier', 'download', 'private link', 'warehouse', 'print partner', 'admin email', 'login' ),
				),
				'ec_option_upload_link_expiry_days' => array(
					'type'      => 'select',
					'label'     => __( 'Link expires after', 'wp-easycart' ),
					'desc'      => __( 'Private links stop working after this long; shortening it also shortens links already sent.', 'wp-easycart' ),
					'default'   => '30',
					'options'   => array(
						'7'  => __( '7 days', 'wp-easycart' ),
						'30' => __( '30 days', 'wp-easycart' ),
						'90' => __( '90 days', 'wp-easycart' ),
						'0'  => __( 'Never', 'wp-easycart' ),
					),
					'parent'    => 'ec_option_upload_link_access',
					'show_when' => 'private',
					'keywords'  => array( 'file upload', 'private link', 'expiry', 'expire', 'download' ),
				),
				'ec_option_current_order_id' => array(
					'type'     => 'number',
					'label'    => __( 'Next order number', 'wp-easycart' ),
					'desc'     => __( 'Order numbers count up from here. You can only raise it — the database ignores lower values.', 'wp-easycart' ),
					'default'  => '',
					'current'  => 'ecst_email_next_order_id',
					'min'      => 1,
					'max'      => 2140000000,
					'step'     => 1,
					'advanced' => true,
					'on_save'  => 'ecst_email_save_order_id',
					'validate' => 'ecst_email_validate_order_id',
					'keywords' => array( 'order number', 'invoice number', 'auto increment', 'sequence', 'order id' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Order Receipt Email Setup', 'label' => 'Current Order ID' ),
				),
			),
			'actions' => array(
				array(
					'id'       => 'send_test_order',
					'label'    => __( 'Send a test order email', 'wp-easycart' ),
					'desc'     => $ecst_email_test_desc,
					'button'   => __( 'Send test', 'wp-easycart' ),
					'callback' => 'ecst_email_test_order',
					'legacy'   => 'email-setup › Order Receipt Email Setup › Send Test Email',
				),
				array(
					'id'       => 'revoke_upload_links',
					'label'    => __( 'Revoke all existing private links', 'wp-easycart' ),
					'desc'     => __( 'Every customer upload link already emailed stops working at once; new order emails get fresh links.', 'wp-easycart' ),
					'button'   => __( 'Revoke links', 'wp-easycart' ),
					'confirm'  => __( 'Revoke every private upload link sent so far? Anyone still using an old link will need the order email sent again.', 'wp-easycart' ),
					'danger'   => true,
					'callback' => 'ecst_email_revoke_upload_links',
				),
			),
		),

		/* 6.0.1: the PDF is a document now ( Settings › Documents › Invoice PDF ): its settings moved there, and the attach
		   switches are the Invoice PDF column of Email attachments. The section stays as a signpost for old links. */
		'pdf-copies' => array(
			'title'    => __( 'PDF invoices', 'wp-easycart' ),
			'icon'     => 'file-text',
			'hint'     => __( 'Now set up on Settings › Documents', 'wp-easycart' ),
			'fields'   => array(),
			'keywords' => array( 'pdf', 'invoice', 'receipt pdf' ),
			'render'   => 'ecst_email_render_pdf_moved',
		),
		/* 6.0.0: subscription mail only goes out on a gateway event, so give the merchant a way to see each one. */
		'subscription-emails' => array(
			'title'   => __( 'Subscription emails', 'wp-easycart' ),
			'icon'    => 'refresh',
			'hint'    => __( 'Send yourself the emails a subscriber gets, built from a real subscription when you have one', 'wp-easycart' ),
			'pro'     => true,
			'fields'  => array(
				'ec_option_email_test_recipient' => array(
					'type'        => 'text',
					'label'       => __( 'Send test emails to', 'wp-easycart' ),
					'desc'        => __( 'Every "Send test" button on this page uses this address. Leave it empty to use your own WordPress address.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => 'you@example.com',
					'sanitize'    => 'ecst_email_sanitize_test_recipient',
					'validate'    => 'ecst_email_validate_test_recipient',
					'keywords'    => array( 'test', 'recipient', 'preview', 'send test' ),
					'legacy'      => array(),
				),
			),
			'actions' => array(
				array(
					'id'       => 'test_subscription_trial_start',
					'label'    => __( 'Trial started', 'wp-easycart' ),
					'desc'     => __( 'What a subscriber gets the moment a free trial begins.', 'wp-easycart' ),
					'button'   => __( 'Send test', 'wp-easycart' ),
					'callback' => 'ecst_email_test_subscription_trial_start',
					'pro'      => true,
				),
				array(
					'id'       => 'test_subscription_trial_ending',
					'label'    => __( 'Trial ending', 'wp-easycart' ),
					'desc'     => __( 'The reminder sent before a trial turns into a paid subscription.', 'wp-easycart' ),
					'button'   => __( 'Send test', 'wp-easycart' ),
					'callback' => 'ecst_email_test_subscription_trial_ending',
					'pro'      => true,
				),
				array(
					'id'       => 'test_subscription_upcoming',
					'label'    => __( 'Renewal coming up', 'wp-easycart' ),
					'desc'     => __( 'The notice that a renewal payment is about to be taken, with the amount and date.', 'wp-easycart' ),
					'button'   => __( 'Send test', 'wp-easycart' ),
					'callback' => 'ecst_email_test_subscription_upcoming',
					'pro'      => true,
				),
				array(
					'id'       => 'test_subscription_ended',
					'label'    => __( 'Subscription ended', 'wp-easycart' ),
					'desc'     => __( 'What a subscriber gets when the subscription finishes or is cancelled.', 'wp-easycart' ),
					'button'   => __( 'Send test', 'wp-easycart' ),
					'callback' => 'ecst_email_test_subscription_ended',
					'pro'      => true,
				),
				array(
					'id'       => 'test_subscription_failed',
					'label'    => __( 'Payment failed', 'wp-easycart' ),
					'desc'     => __( 'The "we could not take the payment" email. It is built from your most recent order, so place a test order first if the store has none.', 'wp-easycart' ),
					'button'   => __( 'Send test', 'wp-easycart' ),
					'callback' => 'ecst_email_test_subscription_failed',
					'pro'      => true,
				),
			),
		),

		'account-emails' => array(
			'title'   => __( 'Account emails', 'wp-easycart' ),
			'hint'    => __( 'Password resets, account activation and new-account alerts', 'wp-easycart' ),
			'fields'  => array(
				'ec_option_password_from_email' => array(
					'type'        => 'text',
					'label'       => __( 'Account emails come from', 'wp-easycart' ),
					'desc'        => __( 'The sender shoppers see on password reset, activation and other account emails. Use “Store Name <help@yourdomain.com>” to show a name.', 'wp-easycart' ),
					'default'     => 'youremail@url.com',
					'placeholder' => 'Store Name <help@example.com>',
					'sanitize'    => 'ecst_email_sanitize_from',
					'validate'    => 'ecst_email_validate_from',
					'keywords'    => array( 'from address', 'sender', 'password reset', 'account' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'Customer Account Email Setup', 'label' => 'Customer Account From Email Address' ),
				),
				'ec_option_password_use_smtp' => array(
					'type'      => 'toggle',
					'label'     => __( 'Send account emails over SMTP', 'wp-easycart' ),
					'desc'      => __( 'The built-in mailer connects to the SMTP server below instead of using PHP mail() on this server.', 'wp-easycart' ),
					'parent'    => 'ec_option_use_wp_mail',
					'show_when' => '0',
					'keywords'  => array( 'smtp', 'phpmailer', 'mail server' ),
					'legacy'    => array( 'page' => 'email-setup', 'section' => 'Customer Account Email Setup', 'label' => 'Use SMTP' ),
				),
				'ec_option_password_from_smtp_host' => array(
					'type'        => 'text',
					'label'       => __( 'SMTP host', 'wp-easycart' ),
					'desc'        => __( 'Your mail provider’s outgoing server, for example smtp.yourprovider.com.', 'wp-easycart' ),
					'placeholder' => 'smtp.example.com',
					'parent'      => 'ec_option_password_use_smtp',
					'keywords'    => array( 'smtp', 'server', 'hostname' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'Customer Account Email Setup', 'label' => 'SMTP Host' ),
				),
				'ec_option_password_from_smtp_port' => array(
					'type'     => 'number',
					'label'    => __( 'SMTP port', 'wp-easycart' ),
					'desc'     => __( 'Usually 465 for SSL, 587 for TLS, 25 for no encryption.', 'wp-easycart' ),
					'default'  => '465',
					'min'      => 1,
					'max'      => 65535,
					'step'     => 1,
					'parent'   => 'ec_option_password_use_smtp',
					'keywords' => array( 'smtp', 'port' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Customer Account Email Setup', 'label' => 'SMTP Port Number' ),
				),
				'ec_option_password_from_smtp_encryption_type' => array(
					'type'     => 'select',
					'label'    => __( 'SMTP encryption', 'wp-easycart' ),
					'desc'     => __( 'Match what your provider lists for the port above.', 'wp-easycart' ),
					'default'  => 'ssl',
					'options'  => $ecst_email_smtp_encryption,
					'parent'   => 'ec_option_password_use_smtp',
					'keywords' => array( 'smtp', 'ssl', 'tls', 'security' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Customer Account Email Setup', 'label' => 'Use SMTP ( encryption select )' ),
				),
				'ec_option_password_from_smtp_username' => array(
					'type'     => 'text',
					'label'    => __( 'SMTP username', 'wp-easycart' ),
					'desc'     => __( 'Often the full mailbox address.', 'wp-easycart' ),
					'parent'   => 'ec_option_password_use_smtp',
					'keywords' => array( 'smtp', 'login', 'user' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Customer Account Email Setup', 'label' => 'SMTP User Name' ),
				),
				'ec_option_password_from_smtp_password' => array(
					'type'     => 'password',
					'label'    => __( 'SMTP password', 'wp-easycart' ),
					'desc'     => __( 'Only sent to your SMTP server. Use an app password if your provider offers one.', 'wp-easycart' ),
					'parent'   => 'ec_option_password_use_smtp',
					'keywords' => array( 'smtp', 'password', 'credentials' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Customer Account Email Setup', 'label' => 'SMTP Password' ),
				),
				'ec_option_send_signup_email' => array(
					'type'     => 'toggle',
					'label'    => __( 'Tell me when someone registers', 'wp-easycart' ),
					'desc'     => __( 'Sends a note to the store notification addresses each time a shopper creates an account.', 'wp-easycart' ),
					'keywords' => array( 'signup', 'registration', 'new account', 'alert', 'admin notification' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'Global Email Settings', 'label' => 'New Account Notifications' ),
				),
			),
			'actions' => array(
				array(
					'id'       => 'send_test_account',
					'label'    => __( 'Send a test account email', 'wp-easycart' ),
					'desc'     => $ecst_email_test_desc,
					'button'   => __( 'Send test', 'wp-easycart' ),
					'callback' => 'ecst_email_test_account',
					'legacy'   => 'email-setup › Customer Account Email Setup › Send Test Email',
				),
			),
		),

		'receipt-wording' => array(
			'title'  => __( 'Receipt wording', 'wp-easycart' ),
			'hint'   => __( 'Phrases in the order receipt email and on the order-complete page, in the store language', 'wp-easycart' ),
			'fields' => ecst_email_phrase_fields(),
		),
	),
);
