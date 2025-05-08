<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class wpeasycart_mailer {
	public function send_order_email( $to, $subject, $message ) {
		$mail = null;
		$phpmailer_class_loaded = false;
		if ( class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
			try {
				$mail = new PHPMailer( true );
				$phpmailer_class_loaded = true;
			} catch ( \Exception $e ) {
				$phpmailer_class_loaded = false;
			}
		}
		if ( ! $phpmailer_class_loaded && class_exists( 'PHPMailer' ) ) {
			try {
				$mail = new PHPMailer( true );
				$phpmailer_class_loaded = true;
			} catch ( \Exception $e ) {
				$phpmailer_class_loaded = false;
			}
		}
		if ( ! $phpmailer_class_loaded ) {
			$wp_phpmailer_6_dir = ABSPATH . WPINC . '/PHPMailer/';
			$wp_phpmailer_5_file = ABSPATH . WPINC . '/class-phpmailer.php';
			$wp_smtp_5_file = ABSPATH . WPINC . '/class-smtp.php';
			if ( is_dir( $wp_phpmailer_6_dir ) && file_exists( $wp_phpmailer_6_dir . 'PHPMailer.php' ) && file_exists( $wp_phpmailer_6_dir . 'Exception.php' ) && file_exists( $wp_phpmailer_6_dir . 'SMTP.php' ) ) {
				if ( ! class_exists( 'PHPMailer\\PHPMailer\\Exception' ) ) {
					require_once( $wp_phpmailer_6_dir . 'Exception.php' );
				}
				if ( ! class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
					require_once( $wp_phpmailer_6_dir . 'PHPMailer.php' );
				}
				if ( ! class_exists( 'PHPMailer\\PHPMailer\\SMTP' ) ) {
					require_once( $wp_phpmailer_6_dir . 'SMTP.php' );
				}
				if ( class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
					try {
						$mail = new PHPMailer( true );
						$phpmailer_class_loaded = true;
					} catch ( \Exception $e ) {
						$phpmailer_class_loaded = false;
					}
				}
			}
			if ( ! $phpmailer_class_loaded && file_exists( $wp_phpmailer_5_file ) ) {
				if ( ! class_exists( 'PHPMailer' ) ) {
					if ( file_exists( $wp_smtp_5_file ) && ! class_exists( 'SMTP' ) ) {
						require_once( $wp_smtp_5_file );
					}
					require_once( $wp_phpmailer_5_file );
				}
				if ( class_exists( 'PHPMailer' ) ) {
					 try {
						$mail = new PHPMailer( true );
						$phpmailer_class_loaded = true;
					} catch ( \Exception $e ) {
						$phpmailer_class_loaded = false;
					}
				}
			}
		}
		if ( $phpmailer_class_loaded ) {
			$errors = false;
			try {
				$to_addresses = explode( ",", $to );
				foreach( $to_addresses as $to_address ){
					$mail->AddAddress( trim( $to_address ) );
				}

				$from_val = stripslashes( get_option( 'ec_option_order_from_email' ) );
				$matches = array( );
				preg_match( "/(.*)\<(.*)\>/", $from_val, $matches );
				if ( count( $matches ) == 3 ) {
					$from_name = $matches[1];
					$from_email = $matches[2];
				} else {
					$from_name = get_bloginfo( "name" );
					$from_email = $from_val;
				}
				$mail->SetFrom( $from_email, $from_name );

				$charset = get_bloginfo( 'charset' );
				$mail->CharSet = $charset;
				$mail->SMTPDebug = 0;

				if( get_option( 'ec_option_order_use_smtp' ) ){
					$mail->IsSMTP();
					$mail->SMTPAuth = true;
					if( get_option( 'ec_option_order_from_smtp_username' ) != "" )
						$mail->Username = get_option( 'ec_option_order_from_smtp_username' );
					if( get_option( 'ec_option_order_from_smtp_password' ) != "" )
						$mail->Password = get_option( 'ec_option_order_from_smtp_password' );
					$mail->Host = get_option( 'ec_option_order_from_smtp_host' );
					$mail->Port = get_option( 'ec_option_order_from_smtp_port' ); 
				}

				if ( get_option( 'ec_option_order_from_smtp_encryption_type' ) !== 'none' ) {
					$mail->SMTPSecure = get_option( 'ec_option_order_from_smtp_encryption_type' );
				}

				$mail->SMTPAutoTLS = false;
				$mail->isHTML( true );
				$mail->Subject = $subject;
				$mail->MsgHTML( $message );

				/* Send mail and return result */
				if ( ! $mail->Send() ) {
					$errors = $mail->ErrorInfo;
				}
				$mail->ClearAddresses();
				$mail->ClearAllRecipients();
			} catch (phpmailerException $e) {
				$errors = $e->errorMessage();
			} catch (Exception $e) {
				$errors = $e->getMessage();
			}
			return $errors;
		} else {
			return false;
		}
	}

	public function send_customer_email( $to, $subject, $message ){
		$mail = null;
		$phpmailer_class_loaded = false;
		if ( class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
			try {
				$mail = new PHPMailer( true );
				$phpmailer_class_loaded = true;
			} catch ( \Exception $e ) {
				$phpmailer_class_loaded = false;
			}
		}
		if ( ! $phpmailer_class_loaded && class_exists( 'PHPMailer' ) ) {
			try {
				$mail = new PHPMailer( true );
				$phpmailer_class_loaded = true;
			} catch ( \Exception $e ) {
				$phpmailer_class_loaded = false;
			}
		}
		if ( ! $phpmailer_class_loaded ) {
			$wp_phpmailer_6_dir = ABSPATH . WPINC . '/PHPMailer/';
			$wp_phpmailer_5_file = ABSPATH . WPINC . '/class-phpmailer.php';
			$wp_smtp_5_file = ABSPATH . WPINC . '/class-smtp.php';
			if ( is_dir( $wp_phpmailer_6_dir ) && file_exists( $wp_phpmailer_6_dir . 'PHPMailer.php' ) && file_exists( $wp_phpmailer_6_dir . 'Exception.php' ) && file_exists( $wp_phpmailer_6_dir . 'SMTP.php' ) ) {
				if ( ! class_exists( 'PHPMailer\\PHPMailer\\Exception' ) ) {
					require_once( $wp_phpmailer_6_dir . 'Exception.php' );
				}
				if ( ! class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
					require_once( $wp_phpmailer_6_dir . 'PHPMailer.php' );
				}
				if ( ! class_exists( 'PHPMailer\\PHPMailer\\SMTP' ) ) {
					require_once( $wp_phpmailer_6_dir . 'SMTP.php' );
				}
				if ( class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
					try {
						$mail = new PHPMailer( true );
						$phpmailer_class_loaded = true;
					} catch ( \Exception $e ) {
						$phpmailer_class_loaded = false;
					}
				}
			}
			if ( ! $phpmailer_class_loaded && file_exists( $wp_phpmailer_5_file ) ) {
				if ( ! class_exists( 'PHPMailer' ) ) {
					if ( file_exists( $wp_smtp_5_file ) && ! class_exists( 'SMTP' ) ) {
						require_once( $wp_smtp_5_file );
					}
					require_once( $wp_phpmailer_5_file );
				}
				if ( class_exists( 'PHPMailer' ) ) {
					 try {
						$mail = new PHPMailer( true );
						$phpmailer_class_loaded = true;
					} catch ( \Exception $e ) {
						$phpmailer_class_loaded = false;
					}
				}
			}
		}
		if ( $phpmailer_class_loaded ) {
			$errors = false;
			try {
				$to_addresses = explode( ",", $to );
				foreach ( $to_addresses as $to_address ) {
					$mail->AddAddress( trim( $to_address ) );
				}

				$from_val = stripslashes( get_option( 'ec_option_password_from_email' ) );
				$matches = array();
				preg_match( "/(.*)\<(.*)\>/", $from_val, $matches );
				if ( count( $matches ) == 3 ) {
					$from_name = $matches[1];
					$from_email = $matches[2];
				} else {
					$from_name = get_bloginfo( "name" );
					$from_email = $from_val;
				}
				$mail->SetFrom( $from_email, $from_name );

				$charset = get_bloginfo( 'charset' );
				$mail->CharSet = $charset;
				$mail->SMTPDebug = 0;

				if ( get_option( 'ec_option_password_use_smtp' ) ) {
					$mail->IsSMTP();
					$mail->SMTPAuth = true;
					if( get_option( 'ec_option_password_from_smtp_username' ) != "" )
						$mail->Username = get_option( 'ec_option_password_from_smtp_username' );
					if( get_option( 'ec_option_password_from_smtp_password' ) != "" )
						$mail->Password = get_option( 'ec_option_password_from_smtp_password' );
					$mail->Host = get_option( 'ec_option_password_from_smtp_host' );
					$mail->Port = get_option( 'ec_option_password_from_smtp_port' ); 
				}

				if ( get_option( 'ec_option_password_from_smtp_encryption_type' ) !== 'none' ) {
					$mail->SMTPSecure = get_option( 'ec_option_password_from_smtp_encryption_type' );
				}
				$mail->SMTPAutoTLS = false;
				$mail->isHTML( true );
				$mail->Subject = $subject;
				$mail->MsgHTML( $message );

				if ( ! $mail->Send( ) ) {
					$errors = $mail->ErrorInfo;
				}
				$mail->ClearAddresses();
				$mail->ClearAllRecipients();
			} catch (phpmailerException $e) {
				$errors = $e->errorMessage();
			} catch (Exception $e) {
				$errors = $e->getMessage();
			}
			return $errors;
		} else {
			return 'PHP Mailer Failed to Load.';
		}
	}
}
