<?php
/**
 * WP EasyCart Admin: on-screen help for email delivery.
 *
 * Renders the "How to fix" / "How to check" disclosures under each delivery check, the
 * "Improve delivery in 3 steps" guide shown when no mail plugin sends WordPress mail, the
 * SPF / DKIM / DMARC glossary and the one-line health summary. Loaded by
 * admin/inc/wp_easycart_admin_email_health.php; the checks themselves live in ec_email::checks().
 *
 * Links ( filter wp_easycart_email_help_links ): guide, docs, video.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_email_help' ) ) :

	class wp_easycart_admin_email_help {

		/** Plugins recommended in step 1, in order. Keys are ec_email::mailer_plugins() slugs. */
		const RECOMMENDED = array( 'wp-mail-smtp', 'fluent-smtp' );

		// Links.

		/**
		 * Help destinations. 'guide' is the email delivery article, 'docs' the Email settings chapter of the
		 * admin guide, 'video' the email setup video used by the legacy help system ( online_docs::print_vids_url ).
		 *
		 * @return array { guide, docs, video }
		 */
		public static function links() {
			$docs = 'https://docs.wpeasycart.com/wp-easycart-administrative-console-guide/?wpeasycartadmin=1&section=email-setup';
			if ( function_exists( 'wp_easycart_admin' ) ) {
				$admin = wp_easycart_admin();
				if ( is_object( $admin ) && isset( $admin->helpsystem ) && is_object( $admin->helpsystem ) && method_exists( $admin->helpsystem, 'print_docs_url' ) ) {
					$docs = (string) $admin->helpsystem->print_docs_url( 'settings', 'email-setup', 'email-settings' );
				}
			}
			$links = apply_filters(
				'wp_easycart_email_help_links',
				array(
					'guide' => 'https://wpeasycart.com/docs/email-delivery',
					'docs'  => $docs,
					'video' => 'https://www.youtube.com/watch?v=p96NBca16N0',
				)
			);
			return array_merge(
				array(
					'guide' => '',
					'docs'  => '',
					'video' => '',
				),
				(array) $links
			);
		}

		/** Settings › Email deep link to one field ( highlighted on arrival ). */
		public static function field_url( $field_key ) {
			if ( class_exists( 'wp_easycart_admin_settings_registry' ) ) {
				return wp_easycart_admin_settings_registry::page_url( 'email-setup', $field_key );
			}
			return admin_url( 'admin.php?page=wp-easycart-settings&subpage=email' );
		}

		/** Allowed inline markup for help sentences. */
		private static function kses() {
			return array(
				'b'    => array(),
				'code' => array(),
				'em'   => array(),
				'a'    => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			);
		}

		// Small building blocks.

		private static function toggle( $panel_id, $label ) {
			echo '<button type="button" class="ecem-toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '" data-ecem-toggle>' . esc_html( $label ) . '<span class="ecem-caret" aria-hidden="true"></span></button>';
		}

		private static function copy_button( $value, $what ) {
			/* translators: %s: what is copied, e.g. "Value" */
			echo '<button type="button" class="ecem-copy" data-ecem-copy="' . esc_attr( $value ) . '" aria-label="' . esc_attr( sprintf( __( 'Copy %s', 'wp-easycart' ), $what ) ) . '">' . esc_html__( 'Copy', 'wp-easycart' ) . '</button>';
		}

		/**
		 * A DNS record or setting laid out as label / value rows.
		 *
		 * @param array $rows array( array( label, value, copyable ) ).
		 */
		private static function record( $rows ) {
			echo '<dl class="ecem-rec">';
			foreach ( $rows as $row ) {
				echo '<div class="ecem-rec-row"><dt>' . esc_html( $row[0] ) . '</dt><dd><code>' . esc_html( $row[1] ) . '</code>';
				if ( ! empty( $row[2] ) ) {
					self::copy_button( $row[1], $row[0] );
				}
				echo '</dd></div>';
			}
			echo '</dl>';
		}

		/** Numbered steps. Each step may carry the inline markup from kses(). */
		private static function steps( $steps ) {
			echo '<ol class="ecem-howto">';
			foreach ( $steps as $step ) {
				echo '<li>' . wp_kses( $step, self::kses() ) . '</li>';
			}
			echo '</ol>';
		}

		private static function para( $html, $css_class = '' ) {
			echo '<p' . ( '' !== $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '' ) . '>' . wp_kses( $html, self::kses() ) . '</p>';
		}

		private static function run_again_button() {
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm" data-ecem-run>' . esc_html__( 'Run checks again', 'wp-easycart' ) . '</button>';
		}

		/** Sends a test through EasyCart's own path to the signed-in admin ( AJAX ecv2_email_send_test ). */
		public static function send_test_button( $primary = false ) {
			echo '<span class="ecem-test"><button type="button" class="ecv2-btn ecv2-btn-sm' . ( $primary ? ' ecv2-btn-primary' : '' ) . '" data-ecem-send-test>' . esc_html__( 'Send a test email', 'wp-easycart' ) . '</button><span class="ecem-test-result" role="status" aria-live="polite"></span></span>';
		}

		private static function ext_link( $url, $label ) {
			return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . ' ↗</a>';
		}

		// Glossary + summary.

		public static function print_glossary() {
			echo '<p class="ecem-gloss"><b>' . esc_html__( 'In plain words:', 'wp-easycart' ) . '</b> ';
			echo '<span><dfn title="' . esc_attr__( 'Sender Policy Framework', 'wp-easycart' ) . '">SPF</dfn> ' . esc_html__( 'lists the servers allowed to send email for your domain.', 'wp-easycart' ) . '</span> ';
			echo '<span><dfn title="' . esc_attr__( 'DomainKeys Identified Mail', 'wp-easycart' ) . '">DKIM</dfn> ' . esc_html__( 'signs each email so inboxes can tell it really came from you.', 'wp-easycart' ) . '</span> ';
			echo '<span><dfn title="' . esc_attr__( 'Domain-based Message Authentication, Reporting and Conformance', 'wp-easycart' ) . '">DMARC</dfn> ' . esc_html__( 'tells inboxes what to do when those two checks fail.', 'wp-easycart' ) . '</span> ';
			echo '<span>' . esc_html__( 'All three are text records you add where your domain name is managed.', 'wp-easycart' ) . '</span></p>';
		}

		/**
		 * One sentence explaining the health card state.
		 *
		 * @param array $h ec_email::health() result.
		 */
		public static function print_summary( $h ) {
			$show_test = false;
			if ( $h['streak'] >= ec_email::FAIL_STREAK_ALERT ) {
				$state = 'failing';
				$text  = __( 'Several emails in a row have failed, so customers are probably not getting receipts. The last error below says why.', 'wp-easycart' );
			} elseif ( null === $h['rate'] ) {
				$state     = 'empty';
				$text      = __( 'Nothing has been sent in the last 7 days, so there is no delivery rate yet. Send yourself a test to check the setup.', 'wp-easycart' );
				$show_test = true;
			} elseif ( $h['rate'] >= 98 ) {
				$state = 'healthy';
				$text  = __( 'Your mail server accepted every recent email. The delivery checks below help those emails reach the inbox instead of spam.', 'wp-easycart' );
			} else {
				$state = 'failures';
				/* translators: %d: number of failed emails */
				$text = sprintf( __( 'Most emails went out, but %d failed in the last 7 days. Read the last error below, fix the cause, then retry from the email log.', 'wp-easycart' ), (int) $h['failed'] );
			}
			echo '<div class="ecem-sum ecem-sum-' . esc_attr( $state ) . '"><p>' . esc_html( $text ) . '</p>';
			if ( $show_test ) {
				self::send_test_button();
			}
			echo '</div>';
		}

		// Per-check help.

		/** Whether print_row_help() has something for this check. */
		public static function has_help( $check ) {
			$key    = isset( $check['key'] ) ? $check['key'] : '';
			$status = isset( $check['status'] ) ? $check['status'] : '';
			$reason = isset( $check['reason'] ) ? $check['reason'] : '';
			if ( 'path' === $key ) {
				return 'mailer' === $reason || 'no_plugin' === $reason || 'bypassed' === $reason || 'builtin' === $reason;
			}
			if ( 'spf' === $key ) {
				return 'warn' === $status || ! empty( $check['service_missing'] );
			}
			if ( in_array( $key, array( 'from', 'dkim', 'dmarc', 'port', 'volume' ), true ) ) {
				return 'ok' !== $status;
			}
			return in_array( $key, array( 'dns', 'override' ), true );
		}

		public static function print_row_help( $check ) {
			$check = array_merge(
				array(
					'key'         => '',
					'status'      => '',
					'reason'      => '',
					'domain'      => '',
					'site_domain' => '',
					'from'        => '',
					'service'     => '',
					'fix_url'     => '',
				),
				$check
			);
			$id    = 'ecem_help_' . sanitize_key( $check['key'] . '_' . $check['reason'] );
			switch ( $check['key'] ) {
				case 'path':
					if ( 'mailer' === $check['reason'] && ! empty( $check['mailer'] ) ) {
						self::help_mailer( $id, $check['mailer'] );
					} elseif ( 'no_plugin' === $check['reason'] || ( 'builtin' === $check['reason'] && ! empty( $check['guide'] ) ) ) {
						echo '<div class="ecem-help-acts"><a class="ecem-toggle" href="#ecem_guide">' . esc_html__( 'Show me the 3 steps', 'wp-easycart' ) . '</a></div>';
					} else {
						self::help_switch_to_wp_mail( $id );
					}
					break;
				case 'override':
					self::help_two_plugins( $id, $check );
					break;
				case 'from':
					self::help_from( $id, $check );
					break;
				case 'spf':
					self::help_spf( $id, $check );
					break;
				case 'dkim':
					self::help_dkim( $id, $check );
					break;
				case 'dmarc':
					self::help_dmarc( $id, $check );
					break;
				case 'dns':
					self::help_dns_unavailable( $id, $check );
					break;
				case 'port':
					self::help_port( $id );
					break;
				case 'volume':
					self::help_volume( $id );
					break;
			}
		}

		/** Positive: a mail plugin sends. Point at its test screen, offer ours, explain how to verify. */
		private static function help_mailer( $id, $mailer ) {
			$name = $mailer['name'];
			echo '<div class="ecem-help-acts">';
			/* translators: %s: mail plugin name */
			echo '<a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( $mailer['test_url'] ) . '">' . esc_html( sprintf( __( 'Open %s', 'wp-easycart' ), $name ) ) . '</a>';
			self::send_test_button();
			self::toggle( $id, __( 'How to check it works', 'wp-easycart' ) );
			echo '</div>';
			echo '<div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';
			$from  = trim( (string) stripslashes( get_option( 'ec_option_order_from_email' ) ) );
			$from  = preg_match( '/<([^>]+)>/', $from, $match ) ? trim( $match[1] ) : $from;
			$steps = array();
			/* translators: 1: link to the mail plugin, 2: plugin name */
			$steps[] = sprintf( __( 'Open %1$s and make sure it shows as connected, with no warnings on its screen.', 'wp-easycart' ), '<a href="' . esc_url( $mailer['settings_url'] ) . '">' . esc_html( $name ) . '</a>' );
			if ( '' !== $mailer['where'] ) {
				/* translators: 1: where the test screen is, e.g. "WP Mail SMTP › Tools › Email Test" */
				$steps[] = sprintf( __( 'Send its test email to an address you can check (%1$s) and confirm it reports success.', 'wp-easycart' ), '<b>' . esc_html( $mailer['where'] ) . '</b>' );
			} else {
				/* translators: %s: plugin name */
				$steps[] = sprintf( __( 'Use the test email option in %s to send to an address you can check, and confirm it reports success.', 'wp-easycart' ), esc_html( $name ) );
			}
			$steps[] = __( 'Back here, press <b>Send a test email</b>. It goes out the same way as your order emails, so a success here means receipts take the same route. It appears in the email log below as "Test".', 'wp-easycart' );
			$steps[] = __( 'Check the inbox and the spam folder. If it lands in spam, work through SPF, DKIM and DMARC below.', 'wp-easycart' );
			self::steps( $steps );
			if ( '' !== $from ) {
				/* translators: 1: from address, 2: plugin name */
				self::para( sprintf( __( 'EasyCart sends as %1$s. If %2$s has a "force from email" setting turned on, it replaces that address, so make sure the one it uses is on your own domain.', 'wp-easycart' ), '<code>' . esc_html( $from ) . '</code>', esc_html( $name ) ), 'ecem-help-note' );
			}
			if ( '' !== $mailer['file'] && 'override' === $mailer['source'] ) {
				/* translators: %s: file path */
				self::para( sprintf( __( 'Detected from %s, which takes over WordPress mail.', 'wp-easycart' ), '<code>' . esc_html( $mailer['file'] ) . '</code>' ), 'ecem-help-meta' );
			}
			echo '</div>';
		}

		private static function help_switch_to_wp_mail( $id ) {
			echo '<div class="ecem-help-acts">';
			self::toggle( $id, __( 'How to fix', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';
			self::steps(
				array(
					/* translators: %s: link to the Send method setting */
					sprintf( __( 'Go to %s and choose <b>WordPress mail</b>. It saves straight away.', 'wp-easycart' ), '<a href="' . esc_url( self::field_url( 'ec_option_use_wp_mail' ) ) . '">' . esc_html__( 'Sender › Send method', 'wp-easycart' ) . '</a>' ),
					__( 'If you do not have a mail plugin yet, the "Improve delivery" steps appear here once you switch.', 'wp-easycart' ),
					__( 'Press <b>Send a test email</b> and check the inbox.', 'wp-easycart' ),
				)
			);
			echo '<div class="ecem-help-acts">';
			self::send_test_button();
			self::run_again_button();
			echo '</div></div>';
		}

		private static function help_two_plugins( $id, $check ) {
			$keep   = ! empty( $check['mailer']['name'] ) ? $check['mailer']['name'] : '';
			$others = ! empty( $check['others'] ) ? implode( ', ', (array) $check['others'] ) : '';
			echo '<div class="ecem-help-acts">';
			self::toggle( $id, __( 'How to fix', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';
			self::steps(
				array(
					/* translators: 1: plugin that sends, 2: other plugins */
					sprintf( __( 'Decide which one you set up. Right now %1$s is doing the sending and %2$s is not.', 'wp-easycart' ), '<b>' . esc_html( $keep ) . '</b>', '<b>' . esc_html( $others ) . '</b>' ),
					/* translators: %s: link to the Plugins page */
					sprintf( __( 'On the %s page, deactivate the one you do not use.', 'wp-easycart' ), '<a href="' . esc_url( admin_url( 'plugins.php?plugin_status=active' ) ) . '">' . esc_html__( 'Plugins', 'wp-easycart' ) . '</a>' ),
					__( 'Send a test from the plugin you kept, then press <b>Send a test email</b> here.', 'wp-easycart' ),
				)
			);
			echo '<div class="ecem-help-acts">';
			self::send_test_button();
			self::run_again_button();
			echo '</div></div>';
		}

		private static function help_from( $id, $check ) {
			$site    = '' !== $check['site_domain'] ? $check['site_domain'] : 'example.com';
			$suggest = 'orders@' . $site;
			$link    = '<a href="' . esc_url( self::field_url( 'ec_option_order_from_email' ) ) . '">' . esc_html__( 'Sender › Order emails come from', 'wp-easycart' ) . '</a>';
			echo '<div class="ecem-help-acts">';
			self::toggle( $id, __( 'How to fix', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';
			if ( 'other_domain' === $check['reason'] ) {
				/* translators: 1: from domain, 2: store domain */
				self::para( sprintf( __( 'This is fine when you own %1$s and its DNS has SPF and DKIM for the service that sends your mail. If not, use an address on %2$s instead.', 'wp-easycart' ), '<b>' . esc_html( $check['domain'] ) . '</b>', '<b>' . esc_html( $site ) . '</b>' ) );
			} elseif ( 'free' === $check['reason'] ) {
				/* translators: %s: free mail domain, e.g. gmail.com */
				self::para( sprintf( __( 'Inboxes know %s mail only comes from its own servers, so a receipt from your website that claims to be from that address looks forged.', 'wp-easycart' ), '<b>' . esc_html( $check['domain'] ) . '</b>' ) );
			}
			self::steps(
				array(
					/* translators: %s: suggested address */
					sprintf( __( 'Create a mailbox on your store\'s domain, for example %s. In cPanel: Email › Email Accounts › Create. In Google Workspace or Microsoft 365, add it as a user or an alias. A forwarder to the inbox you already read is fine.', 'wp-easycart' ), '<code>' . esc_html( $suggest ) . '</code>' ),
					/* translators: %s: link to the from address setting */
					sprintf( __( 'Enter the new address in %s and save.', 'wp-easycart' ), $link ),
					__( 'If a mail plugin is connected to Gmail or Outlook, update its from address too.', 'wp-easycart' ),
				)
			);
			self::record( array( array( __( 'Suggested address', 'wp-easycart' ), $suggest, true ) ) );
			echo '<div class="ecem-help-acts">';
			self::run_again_button();
			echo '</div></div>';
		}

		private static function help_spf( $id, $check ) {
			$domain   = $check['domain'];
			$services = ec_email::sending_services();
			$service  = ( '' !== $check['service'] && isset( $services[ $check['service'] ] ) ) ? $services[ $check['service'] ] : null;
			echo '<div class="ecem-help-acts">';
			if ( 'ok' === $check['status'] ) {
				/* translators: %s: sending service name */
				self::toggle( $id, sprintf( __( 'Sending with %s?', 'wp-easycart' ), $service ? $service['name'] : '' ) );
			} else {
				self::toggle( $id, __( 'How to fix', 'wp-easycart' ) );
			}
			echo '</div><div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';

			if ( 'ok' === $check['status'] && $service && '' !== $service['spf'] ) {
				$record  = isset( $check['record'] ) ? (string) $check['record'] : '';
				$include = 'include:' . $service['spf'];
				$merged  = preg_match( '/\s[~?+-]?all\s*$/i', $record ) ? preg_replace( '/\s([~?+-]?all)\s*$/i', ' ' . $include . ' $1', $record ) : trim( $record ) . ' ' . $include;
				/* translators: 1: service name, 2: SPF include */
				self::para( sprintf( __( 'Your SPF record does not mention %1$s. Many services only need their DKIM records, but if their domain setup asks for SPF, edit your existing record so it includes %2$s:', 'wp-easycart' ), esc_html( $service['name'] ), '<code>' . esc_html( $include ) . '</code>' ) );
				self::record(
					array(
						array( __( 'Type', 'wp-easycart' ), 'TXT', false ),
						array( __( 'Name / host', 'wp-easycart' ), '@', false ),
						array( __( 'Value', 'wp-easycart' ), $merged, true ),
					)
				);
				self::para( __( 'Edit the record you already have. A domain may only have one SPF record; a second one breaks both.', 'wp-easycart' ), 'ecem-help-note' );
				self::print_where_to_add( $domain );
				echo '</div>';
				return;
			}

			$include = ( $service && '' !== $service['spf'] ) ? ' include:' . $service['spf'] : '';
			$value   = 'v=spf1 a mx' . $include . ' ~all';
			/* translators: %s: domain */
			self::para( sprintf( __( 'Add this record to %s. It says your web server, your mail server and your sending service may send email for the domain.', 'wp-easycart' ), '<b>' . esc_html( $domain ) . '</b>' ) );
			self::record(
				array(
					array( __( 'Type', 'wp-easycart' ), 'TXT', false ),
					/* translators: %s: domain */
					array( sprintf( __( 'Name / host (@ means %s)', 'wp-easycart' ), $domain ), '@', true ),
					array( __( 'Value', 'wp-easycart' ), $value, true ),
				)
			);
			if ( $service && '' !== $service['spf'] ) {
				/* translators: %s: service name */
				self::para( sprintf( __( 'This already includes %s, which looks like the service sending your mail.', 'wp-easycart' ), '<b>' . esc_html( $service['name'] ) . '</b>' ), 'ecem-help-note' );
			} else {
				$parts = array();
				foreach ( $services as $item ) {
					if ( '' !== $item['spf'] ) {
						$parts[] = esc_html( $item['name'] ) . ' <code>include:' . esc_html( $item['spf'] ) . '</code>';
					}
				}
				self::para( __( 'Send through a service or a hosted mailbox? Add its include just before <code>~all</code>:', 'wp-easycart' ) . ' ' . implode( ' · ', $parts ), 'ecem-help-note' );
			}
			self::para( __( 'Already have a TXT record starting with <code>v=spf1</code>? Edit that one instead. A domain may only have one SPF record.', 'wp-easycart' ), 'ecem-help-note' );
			self::print_where_to_add( $domain );
			echo '</div>';
		}

		private static function help_dkim( $id, $check ) {
			$domain   = $check['domain'];
			$services = ec_email::sending_services();
			echo '<div class="ecem-help-acts">';
			self::toggle( $id, __( 'How to fix', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';
			self::para( __( 'DKIM records hold a unique key, so you copy them from whoever sends your email rather than typing one yourself. Get it here:', 'wp-easycart' ) );
			$steps = array();
			if ( '' !== $check['service'] && isset( $services[ $check['service'] ] ) ) {
				$steps[] = '<b>' . esc_html( $services[ $check['service'] ]['name'] ) . ' ' . esc_html__( '(looks like yours):', 'wp-easycart' ) . '</b> ' . esc_html( $services[ $check['service'] ]['dkim'] );
			}
			$steps[] = '<b>' . esc_html__( 'Email with your web host (cPanel):', 'wp-easycart' ) . '</b> ' . esc_html__( 'Email › Email Deliverability › Manage next to your domain. If your DNS is at the same host, press "Install the suggested record".', 'wp-easycart' );
			$steps[] = '<b>Google Workspace:</b> ' . esc_html( $services['google']['dkim'] ) . '. ' . esc_html__( 'Add the record, wait an hour, then press "Start authentication".', 'wp-easycart' );
			$steps[] = '<b>Microsoft 365:</b> ' . esc_html( $services['microsoft']['dkim'] ) . '. ' . esc_html__( 'Add the two CNAME records it shows, then turn signing on.', 'wp-easycart' );
			$steps[] = '<b>' . esc_html__( 'Sending services (Brevo, SendGrid, Mailgun, Amazon SES, Postmark):', 'wp-easycart' ) . '</b> ' . esc_html__( 'look for "Domain authentication" or "Verify domain". They list two or three records to add.', 'wp-easycart' );
			self::steps( $steps );
			self::para( __( 'The record you add looks like this:', 'wp-easycart' ) );
			self::record(
				array(
					array( __( 'Type', 'wp-easycart' ), __( 'TXT or CNAME (as given)', 'wp-easycart' ), false ),
					array( __( 'Name / host', 'wp-easycart' ), 'selector._domainkey', false ),
					array( __( 'Value', 'wp-easycart' ), __( 'the long text from your provider', 'wp-easycart' ), false ),
				)
			);
			self::para( __( 'We look for common selector names (default, google, selector1, k1, s1…). If your provider uses a different name, DKIM may already work even though this still shows a warning.', 'wp-easycart' ), 'ecem-help-note' );
			self::print_where_to_add( $domain );
			echo '</div>';
		}

		private static function help_dmarc( $id, $check ) {
			$domain = $check['domain'];
			$report = ( '' !== $check['from'] && strtolower( substr( strrchr( $check['from'], '@' ), 1 ) ) === $domain ) ? $check['from'] : 'postmaster@' . $domain;
			$value  = 'v=DMARC1; p=none; rua=mailto:' . $report;
			echo '<div class="ecem-help-acts">';
			self::toggle( $id, __( 'How to fix', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';
			/* translators: %s: domain */
			self::para( sprintf( __( 'Add this record to %s. It only watches: it never blocks your mail, and it sends you reports on who is sending as your domain.', 'wp-easycart' ), '<b>' . esc_html( $domain ) . '</b>' ) );
			self::record(
				array(
					array( __( 'Type', 'wp-easycart' ), 'TXT', false ),
					array( __( 'Name / host', 'wp-easycart' ), '_dmarc', true ),
					array( __( 'Value', 'wp-easycart' ), $value, true ),
				)
			);
			/* translators: 1: full record name, 2: report address */
			self::para( sprintf( __( 'Some DNS hosts want the full name, %1$s. Reports go to %2$s; change it to any mailbox you read.', 'wp-easycart' ), '<code>_dmarc.' . esc_html( $domain ) . '</code>', '<code>' . esc_html( $report ) . '</code>' ), 'ecem-help-note' );
			self::para( __( 'Set up SPF and DKIM first. After a few weeks of clean reports you can change <code>p=none</code> to <code>p=quarantine</code> for extra protection.', 'wp-easycart' ), 'ecem-help-note' );
			self::print_where_to_add( $domain );
			echo '</div>';
		}

		private static function help_dns_unavailable( $id, $check ) {
			echo '<div class="ecem-help-acts">';
			self::toggle( $id, __( 'How to check another way', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';
			self::steps(
				array(
					/* translators: %s: link to Google Admin Toolbox */
					sprintf( __( 'Open %s, enter your domain and read the SPF, DKIM and DMARC results.', 'wp-easycart' ), self::ext_link( 'https://toolbox.googleapps.com/apps/checkmx/', __( 'Google Admin Toolbox Check MX', 'wp-easycart' ) ) ),
					/* translators: %s: domain */
					sprintf( __( 'Or ask your host: "Please confirm SPF, DKIM and DMARC are set up for %s."', 'wp-easycart' ), esc_html( $check['domain'] ) ),
				)
			);
			echo '</div>';
		}

		private static function help_port( $id ) {
			echo '<div class="ecem-help-acts">';
			self::toggle( $id, __( 'How to fix', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';
			self::steps(
				array(
					__( 'Ask your host whether outgoing SMTP is allowed, and on which port (587 is the usual one).', 'wp-easycart' ),
					/* translators: %s: link to the Send method setting */
					sprintf( __( 'Or avoid the blocked port: set %s to WordPress mail and use a mail plugin that connects over an API (Brevo, SendGrid, Mailgun, Amazon SES, Postmark). APIs use the normal web port, which hosts do not block.', 'wp-easycart' ), '<a href="' . esc_url( self::field_url( 'ec_option_use_wp_mail' ) ) . '">' . esc_html__( 'Send method', 'wp-easycart' ) . '</a>' ),
				)
			);
			echo '<div class="ecem-help-acts">';
			self::run_again_button();
			echo '</div></div>';
		}

		private static function help_volume( $id ) {
			echo '<div class="ecem-help-acts">';
			self::toggle( $id, __( 'How to fix', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="' . esc_attr( $id ) . '" hidden>';
			self::steps(
				array(
					__( 'Sign up for a sending service built for store email (Brevo, SendGrid, Mailgun, Amazon SES or Postmark). Most have a free or low-cost plan.', 'wp-easycart' ),
					__( 'Verify your domain with them: they give you DNS records that also take care of SPF and DKIM.', 'wp-easycart' ),
					__( 'Paste their API key into your mail plugin, choosing that service instead of Gmail, and send a test.', 'wp-easycart' ),
				)
			);
			echo '</div>';
		}

		// Where to add DNS records.

		/** Seconds a domain's nameserver answer is kept ( transient ec_email_dns_host_<md5 domain> ). @since 6.0.0 */
		const DNS_HOST_CACHE_TTL = 43200;

		/**
		 * First nameserver found for the domain, walking up one label at a time ( shop.example.co.uk, example.co.uk, … ).
		 * Lookups go through ec_email::dns_lookup(), which never warns; '' when nothing answered or lookups are unavailable.
		 *
		 * @since 6.0.0
		 * @param string $domain Lower-cased domain.
		 * @return string
		 */
		private static function lookup_nameserver( $domain ) {
			if ( ! class_exists( 'ec_email' ) || ! method_exists( 'ec_email', 'dns_lookup' ) ) {
				return '';
			}
			$labels    = explode( '.', $domain );
			$remaining = count( $labels );
			while ( $remaining >= 2 ) {
				foreach ( (array) ec_email::dns_lookup( implode( '.', $labels ), DNS_NS ) as $record ) {
					if ( ! empty( $record['target'] ) ) {
						return strtolower( (string) $record['target'] );
					}
				}
				array_shift( $labels );
				--$remaining;
			}
			return '';
		}

		/**
		 * Who manages the domain's DNS, from its nameservers. The nameserver answer is cached per domain for
		 * DNS_HOST_CACHE_TTL ( a negative answer too ), and refreshed during "Run again".
		 *
		 * @param string    $domain Domain.
		 * @param bool|null $force  True ignores the cache; null ( default ) does so only during "Run again". Added in 6.0.0.
		 * @return array|null { name, how, ns }
		 */
		public static function dns_host( $domain, $force = null ) {
			static $cache     = array();
			static $refreshed = array();
			$domain           = strtolower( trim( (string) $domain ) );
			if ( '' === $domain || ! function_exists( 'dns_get_record' ) ) {
				return null;
			}
			if ( null === $force ) {
				$force = class_exists( 'ec_email' ) && method_exists( 'ec_email', 'checks_forced' ) && ec_email::checks_forced();
			}
			/* One lookup per request and domain, even when forced ( the SPF, DKIM and DMARC help all ask ). */
			if ( array_key_exists( $domain, $cache ) && ( ! $force || isset( $refreshed[ $domain ] ) ) ) {
				return $cache[ $domain ];
			}
			$transient = 'ec_email_dns_host_' . md5( $domain );
			$ns        = null;
			if ( ! $force ) {
				$stored = get_transient( $transient );
				if ( is_array( $stored ) && isset( $stored['ns'] ) ) {
					$ns = (string) $stored['ns'];
				}
			}
			if ( null === $ns ) {
				$ns = self::lookup_nameserver( $domain );
				set_transient( $transient, array( 'ns' => $ns ), self::DNS_HOST_CACHE_TTL );
				$refreshed[ $domain ] = true;
			}
			$cache[ $domain ] = null;
			if ( '' === $ns ) {
				return null;
			}
			$cpanel = __( 'cPanel › Domains › Zone Editor › Manage › Add Record', 'wp-easycart' );
			$hosts  = array(
				'cloudflare.com'        => array( 'Cloudflare', __( 'Cloudflare dashboard › your domain › DNS › Records › Add record. Leave "Proxy" off for email records.', 'wp-easycart' ) ),
				'domaincontrol.com'     => array( 'GoDaddy', __( 'GoDaddy › My Products › your domain › DNS › Add New Record', 'wp-easycart' ) ),
				'secureserver.net'      => array( 'GoDaddy', __( 'GoDaddy › My Products › your domain › DNS › Add New Record', 'wp-easycart' ) ),
				'registrar-servers.com' => array( 'Namecheap', __( 'Namecheap › Domain List › Manage › Advanced DNS › Add New Record', 'wp-easycart' ) ),
				'siteground.net'        => array( 'SiteGround', __( 'SiteGround Site Tools › Domain › DNS Zone Editor', 'wp-easycart' ) ),
				'websitewelcome.com'    => array( 'HostGator', $cpanel ),
				'hostgator.com'         => array( 'HostGator', $cpanel ),
				'bluehost.com'          => array( 'Bluehost', __( 'Bluehost › Domains › your domain › DNS', 'wp-easycart' ) ),
				'a2hosting.com'         => array( 'A2 Hosting', $cpanel ),
				'inmotionhosting.com'   => array( 'InMotion Hosting', $cpanel ),
				'dns-parking.com'       => array( 'Hostinger', __( 'Hostinger hPanel › Domains › your domain › DNS / Nameservers', 'wp-easycart' ) ),
				'awsdns'                => array( 'Amazon Route 53', __( 'AWS console › Route 53 › Hosted zones › your domain › Create record', 'wp-easycart' ) ),
				'googledomains.com'     => array( 'Google Cloud DNS', '' ),
				'ui-dns'                => array( 'IONOS', __( 'IONOS › Domains & SSL › your domain › DNS › Add record', 'wp-easycart' ) ),
				'wordpress.com'         => array( 'WordPress.com', __( 'WordPress.com › Upgrades › Domains › your domain › DNS records', 'wp-easycart' ) ),
				'wixdns.net'            => array( 'Wix', __( 'Wix › Domains › your domain › Manage DNS records', 'wp-easycart' ) ),
				'digitalocean.com'      => array( 'DigitalOcean', __( 'DigitalOcean › Networking › Domains › your domain', 'wp-easycart' ) ),
				'dreamhost.com'         => array( 'DreamHost', '' ),
				'name.com'              => array( 'Name.com', '' ),
				'worldnic.com'          => array( 'Network Solutions', '' ),
			);
			foreach ( $hosts as $needle => $host ) {
				if ( false !== strpos( $ns, $needle ) ) {
					$cache[ $domain ] = array(
						'name' => $host[0],
						'how'  => $host[1],
						'ns'   => $ns,
					);
					return $cache[ $domain ];
				}
			}
			$cache[ $domain ] = array(
				'name' => '',
				'how'  => '',
				'ns'   => $ns,
			);
			return $cache[ $domain ];
		}

		private static function print_where_to_add( $domain ) {
			echo '<div class="ecem-where"><b>' . esc_html__( 'Where to add it', 'wp-easycart' ) . '</b>';
			$host = self::dns_host( $domain );
			if ( $host && '' !== $host['name'] ) {
				/* translators: 1: DNS provider name, 2: nameserver */
				self::para( sprintf( __( 'The DNS for this domain looks like it is managed at %1$s (nameserver %2$s).', 'wp-easycart' ), '<b>' . esc_html( $host['name'] ) . '</b>', '<code>' . esc_html( $host['ns'] ) . '</code>' ) . ( '' !== $host['how'] ? ' ' . esc_html( rtrim( $host['how'], '.' ) ) . '.' : '' ) );
			} elseif ( $host ) {
				/* translators: 1: domain, 2: nameserver */
				self::para( sprintf( __( 'The DNS for %1$s is run by the company behind its nameserver, %2$s. That is usually your web host or where you bought the domain; log in there and look for DNS, Zone Editor or Advanced DNS.', 'wp-easycart' ), '<b>' . esc_html( $domain ) . '</b>', '<code>' . esc_html( $host['ns'] ) . '</code>' ) );
			} else {
				/* translators: %s: domain */
				self::para( sprintf( __( 'In the DNS settings for %s: usually where you bought the domain, or your web host if the domain points there.', 'wp-easycart' ), '<b>' . esc_html( $domain ) . '</b>' ) );
			}
			echo '<ul class="ecem-where-list">';
			echo '<li><b>Cloudflare:</b> ' . esc_html__( 'DNS › Records › Add record', 'wp-easycart' ) . '</li>';
			echo '<li><b>cPanel:</b> ' . esc_html__( 'Domains › Zone Editor › Manage › Add Record', 'wp-easycart' ) . '</li>';
			echo '<li><b>GoDaddy:</b> ' . esc_html__( 'My Products › DNS › Add New Record', 'wp-easycart' ) . '</li>';
			echo '<li><b>Namecheap:</b> ' . esc_html__( 'Domain List › Manage › Advanced DNS', 'wp-easycart' ) . '</li>';
			echo '</ul>';
			self::para( __( 'New records usually show up within an hour (occasionally up to 48 hours). Then run the checks again.', 'wp-easycart' ), 'ecem-help-note' );
			echo '<div class="ecem-help-acts">';
			self::run_again_button();
			echo '</div></div>';
		}

		// Improve delivery in 3 steps.

		/** Guide wrapper; empty when a mail plugin already sends ( or EasyCart's own SMTP is set up ). */
		public static function print_guide( $checks ) {
			$show = false;
			$from = '';
			foreach ( (array) $checks as $check ) {
				if ( isset( $check['key'] ) && 'path' === $check['key'] && ! empty( $check['guide'] ) ) {
					$show = true;
				}
				if ( isset( $check['key'] ) && 'from' === $check['key'] && ! empty( $check['from'] ) && is_email( $check['from'] ) ) {
					$from = $check['from'];
				}
			}
			echo '<div id="ecem_guide_wrap">';
			if ( $show ) {
				self::guide_card( $from );
			}
			echo '</div>';
		}

		private static function guide_card( $from ) {
			$plugins      = ec_email::mailer_plugins();
			$transport    = ec_email::configured_transport();
			$domain       = '' !== $from ? strtolower( substr( strrchr( $from, '@' ), 1 ) ) : strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
			$can_install  = current_user_can( 'install_plugins' );
			$installed    = self::installed_inactive_mailers();
			$step1_status = $installed ? __( 'Installed, not turned on', 'wp-easycart' ) : __( 'To do', 'wp-easycart' );

			echo '<section class="ecem-card ecem-guide" id="ecem_guide" aria-labelledby="ecem_guide_h">';
			echo '<div class="ecem-card-h"><h3 id="ecem_guide_h">' . esc_html__( 'Improve delivery in 3 steps', 'wp-easycart' ) . '</h3><span class="ecem-hint">' . esc_html__( 'About 15 minutes · free', 'wp-easycart' ) . '</span></div>';
			echo '<p class="ecem-guide-intro">' . esc_html__( 'Right now your store emails leave through your web server\'s basic mail, which inboxes often send to spam. A free mail plugin connects your store to a real mailbox or sending service so receipts, order updates and password emails arrive.', 'wp-easycart' ) . '</p>';
			echo '<ol class="ecem-steps">';

			/* Step 1 */
			echo '<li class="ecem-step is-current"><span class="ecem-step-n" aria-hidden="true">1</span><div class="ecem-step-b">';
			echo '<div class="ecem-step-h"><b>' . esc_html__( 'Install a free mail plugin', 'wp-easycart' ) . '</b><span class="ecv2-chip ' . ( $installed ? 'ecv2-chip-amber' : 'ecv2-chip-gray' ) . '">' . esc_html( $step1_status ) . '</span></div>';
			echo '<p>' . esc_html__( 'We suggest WP Mail SMTP or FluentSMTP. Both are free, widely used, and have a setup wizard that asks simple questions.', 'wp-easycart' ) . '</p>';
			echo '<div class="ecem-help-acts">';
			if ( $installed ) {
				foreach ( $installed as $name ) {
					/* translators: %s: plugin name */
					echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( admin_url( 'plugins.php?plugin_status=inactive&s=' . rawurlencode( $name ) ) ) . '">' . esc_html( sprintf( __( 'Turn on %s', 'wp-easycart' ), $name ) ) . '</a>';
				}
			} elseif ( $can_install ) {
				$first = true;
				foreach ( self::RECOMMENDED as $slug ) {
					if ( ! isset( $plugins[ $slug ] ) ) {
						continue;
					}
					$url = self_admin_url( 'plugin-install.php?s=' . rawurlencode( $plugins[ $slug ]['name'] ) . '&tab=search&type=term' );
					/* translators: %s: plugin name */
					echo '<a class="ecv2-btn ecv2-btn-sm' . ( $first ? ' ecv2-btn-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( sprintf( __( 'Find %s', 'wp-easycart' ), $plugins[ $slug ]['name'] ) ) . '</a>';
					$first = false;
				}
			}
			self::toggle( 'ecem_guide_s1', __( 'Show me how', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="ecem_guide_s1" hidden>';
			if ( $installed ) {
				$steps = array(
					/* translators: %s: plugin name */
					sprintf( __( 'Press <b>Turn on %s</b>. It opens the Plugins page filtered to that plugin.', 'wp-easycart' ), esc_html( $installed[0] ) ),
					__( 'Press <b>Activate</b> under the plugin name.', 'wp-easycart' ),
					__( 'The plugin opens its setup wizard or settings. Keep this page open in another tab so you can come back.', 'wp-easycart' ),
				);
			} else {
				$steps = array(
					__( 'Press <b>Find WP Mail SMTP</b> (or FluentSMTP). It opens Plugins › Add New with the search filled in.', 'wp-easycart' ),
					__( 'Press <b>Install Now</b> on the plugin card, wait a moment, then press <b>Activate</b>.', 'wp-easycart' ),
					__( 'The plugin opens its setup wizard. Keep this page open in another tab so you can come back.', 'wp-easycart' ),
				);
			}
			if ( 'plugin_mail' === $transport ) {
				/* translators: %s: link to the Send method setting */
				$steps[] = sprintf( __( 'EasyCart is set to its built-in mailer, which skips mail plugins. Set %s to WordPress mail.', 'wp-easycart' ), '<a href="' . esc_url( self::field_url( 'ec_option_use_wp_mail' ) ) . '">' . esc_html__( 'Sender › Send method', 'wp-easycart' ) . '</a>' );
			}
			self::steps( $steps );
			if ( ! $can_install ) {
				self::para( __( 'Your account cannot install plugins. Ask whoever manages this site to install WP Mail SMTP or FluentSMTP.', 'wp-easycart' ), 'ecem-help-note' );
			}
			echo '</div></div></li>';

			/* Step 2 */
			echo '<li class="ecem-step"><span class="ecem-step-n" aria-hidden="true">2</span><div class="ecem-step-b">';
			echo '<div class="ecem-step-h"><b>' . esc_html__( 'Connect it to your email', 'wp-easycart' ) . '</b><span class="ecv2-chip ecv2-chip-gray">' . esc_html__( 'After step 1', 'wp-easycart' ) . '</span></div>';
			echo '<p>' . esc_html__( 'The plugin needs an account to send from. Use the mailbox you already have, or a sending service if your store sends a lot.', 'wp-easycart' ) . '</p>';
			echo '<div class="ecem-help-acts">';
			self::toggle( 'ecem_guide_s2', __( 'Show me how', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="ecem_guide_s2" hidden>';
			echo '<div class="ecem-options">';

			echo '<div class="ecem-option"><b>' . esc_html__( 'Email with your web host (cPanel)', 'wp-easycart' ) . '</b>';
			self::para( __( 'In the plugin choose <b>Other SMTP</b> and enter:', 'wp-easycart' ) );
			self::record(
				array(
					array( __( 'SMTP host', 'wp-easycart' ), 'mail.' . $domain, true ),
					array( __( 'Port and encryption', 'wp-easycart' ), __( '465 with SSL, or 587 with TLS', 'wp-easycart' ), false ),
					array( __( 'Username', 'wp-easycart' ), '' !== $from ? $from : __( 'your full email address', 'wp-easycart' ), '' !== $from ),
					array( __( 'Password', 'wp-easycart' ), __( 'that mailbox\'s password', 'wp-easycart' ), false ),
				)
			);
			self::para( __( 'Exact values are in cPanel › Email › Email Accounts › Connect Devices.', 'wp-easycart' ), 'ecem-help-note' );
			echo '</div>';

			echo '<div class="ecem-option"><b>' . esc_html__( 'Google Workspace or Gmail', 'wp-easycart' ) . '</b>';
			self::para( __( 'Choose the plugin\'s <b>Google / Gmail</b> option and sign in with Google. Gmail limits how many emails you can send a day, so busy stores should use a sending service.', 'wp-easycart' ) );
			echo '</div>';

			echo '<div class="ecem-option"><b>' . esc_html__( 'Microsoft 365 or Outlook', 'wp-easycart' ) . '</b>';
			self::para( __( 'Choose the plugin\'s <b>Microsoft / Outlook</b> option and sign in with Microsoft. Microsoft is turning off password-only SMTP, so the sign-in option is the one that keeps working.', 'wp-easycart' ) );
			echo '</div>';

			echo '<div class="ecem-option"><b>' . esc_html__( 'A sending service (most reliable)', 'wp-easycart' ) . '</b>';
			self::para( __( 'Sign up with Brevo, SendGrid, Mailgun, Amazon SES or Postmark. Verify your domain (they give you DNS records, which also fixes SPF and DKIM), create an API key, pick that service in the plugin and paste the key.', 'wp-easycart' ) );
			echo '</div>';

			echo '</div>';
			if ( '' !== $from ) {
				/* translators: %s: from address */
				self::para( sprintf( __( 'Whichever you choose, send from the same address EasyCart uses: %s.', 'wp-easycart' ), '<code>' . esc_html( $from ) . '</code>' ), 'ecem-help-note' );
			}
			echo '</div></div></li>';

			/* Step 3 */
			$last = self::last_test();
			echo '<li class="ecem-step"><span class="ecem-step-n" aria-hidden="true">3</span><div class="ecem-step-b">';
			echo '<div class="ecem-step-h"><b>' . esc_html__( 'Send a test, then run the checks again', 'wp-easycart' ) . '</b><span class="ecv2-chip ecv2-chip-gray">' . esc_html__( 'After step 2', 'wp-easycart' ) . '</span></div>';
			echo '<p>' . esc_html__( 'Once the plugin reports a successful test, send one from EasyCart too. When the checks see the plugin, this guide is replaced by a green "Your emails are sent by…" check.', 'wp-easycart' ) . '</p>';
			if ( $last ) {
				/* translators: 1: date, 2: result, 3: transport */
				echo '<p class="ecem-help-meta">' . esc_html( sprintf( __( 'Last test: %1$s · %2$s · %3$s', 'wp-easycart' ), date_i18n( 'M j, H:i', strtotime( $last->created_at ) ), 'sent' === $last->status ? __( 'sent', 'wp-easycart' ) : __( 'failed', 'wp-easycart' ), ec_email::transport_label( $last->transport ) ) ) . '</p>';
			}
			echo '<div class="ecem-help-acts">';
			self::send_test_button( true );
			self::run_again_button();
			self::toggle( 'ecem_guide_s3', __( 'Show me how', 'wp-easycart' ) );
			echo '</div><div class="ecem-help" id="ecem_guide_s3" hidden>';
			self::steps(
				array(
					__( 'In the mail plugin, open its email test screen and send a test to an address you can check. A Gmail or Outlook address shows whether it lands in spam.', 'wp-easycart' ),
					__( 'Press <b>Send a test email</b> above. It goes to your WordPress account\'s address through the same route as order emails.', 'wp-easycart' ),
					__( 'Check the inbox and the spam folder.', 'wp-easycart' ),
					__( 'Press <b>Run checks again</b>. Then work through any SPF, DKIM or DMARC warnings in Delivery checks.', 'wp-easycart' ),
				)
			);
			echo '</div></div></li>';

			echo '</ol>';
			self::print_help_links( __( 'Prefer to read or watch?', 'wp-easycart' ) );
			echo '</section>';
		}

		/** "Read the guide / watch the video / docs" line. */
		public static function print_help_links( $lead ) {
			$links = self::links();
			$items = array();
			if ( '' !== $links['guide'] ) {
				$items[] = self::ext_link( $links['guide'], __( 'Email delivery guide', 'wp-easycart' ) );
			}
			if ( '' !== $links['video'] ) {
				$items[] = self::ext_link( $links['video'], __( 'Watch the email setup video', 'wp-easycart' ) );
			}
			if ( '' !== $links['docs'] ) {
				$items[] = self::ext_link( $links['docs'], __( 'Email settings docs', 'wp-easycart' ) );
			}
			if ( ! $items ) {
				return;
			}
			echo '<p class="ecem-helplinks"><b>' . esc_html( $lead ) . '</b> ' . wp_kses( implode( ' <span aria-hidden="true">·</span> ', $items ), array_merge( self::kses(), array( 'span' => array( 'aria-hidden' => array() ) ) ) ) . '</p>';
		}

		/** Names of known mail plugins that are installed but not active. */
		private static function installed_inactive_mailers() {
			if ( ! function_exists( 'get_plugins' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( ! function_exists( 'get_plugins' ) ) {
				return array();
			}
			$installed = array_keys( get_plugins() );
			$active    = (array) get_option( 'active_plugins', array() );
			if ( is_multisite() ) {
				$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
			}
			$names = array();
			foreach ( ec_email::mailer_plugins() as $plugin ) {
				if ( $plugin['passive'] ) {
					continue;
				}
				foreach ( $plugin['files'] as $file ) {
					if ( in_array( $file, $installed, true ) && ! in_array( $file, $active, true ) ) {
						$names[] = $plugin['name'];
						break;
					}
				}
			}
			return array_values( array_unique( $names ) );
		}

		/** Most recent EasyCart test email in the log, or null. */
		private static function last_test() {
			if ( ! ec_email::tables_exist() ) {
				return null;
			}
			global $wpdb;
			return $wpdb->get_row( "SELECT created_at, status, transport FROM ec_email_log WHERE email_type = 'test' ORDER BY log_id DESC LIMIT 1" );
		}
	}

endif;
