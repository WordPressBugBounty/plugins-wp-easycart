<?php
/**
 * WP EasyCart — shared email design ( building blocks for every store email ).
 *
 * One look for all emails: 600px table layout, inline styles, accent bar in the store color
 * ( ec_option_details_main_color ), logo or store name header, cards, bulletproof buttons, address cards with a
 * country-aware formatter, item table, totals and footer. Mail-client auto-linking of addresses / numbers is
 * neutralised, RTL is supported and columns stack below 620px.
 *
 * Usage inside a template ( every method echoes; the get_*() twin returns the same HTML ):
 *
 *   $ed = 'wp_easycart_email_design';
 *   $ed::open( array( 'title' => $subject, 'preheader' => $first_line ) );
 *   $ed::section_start();
 *   $ed::heading( esc_html( $headline ) );
 *   $ed::paragraph( wp_kses_post( $intro ) );
 *   $ed::section_end();
 *   $ed::button_row( $url, $label );
 *   $ed::close();
 *
 * Argument naming: anything ending in _html ( and $html ) is inserted as given, so escape it first ( esc_html(),
 * wp_kses_post(), or HTML returned by another method here ). Every other value is escaped by this class.
 *
 * Filters: wp_easycart_email_accent_color, wp_easycart_email_design_context, wp_easycart_ecv2_tracking_url_map,
 *          wp_easycart_email_address_lines, wp_easycart_email_footer_html.
 *
 * @package wp-easycart
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_email_design' ) ) :

	/**
	 * Email building blocks.
	 *
	 * @since 6.0.0
	 */
	final class wp_easycart_email_design {

		/**
		 * Look of the email being built ( set by open(), defaults otherwise ).
		 *
		 * @var array|null
		 */
		private static $ctx = null;

		/**
		 * Flat mode ( no page wrapper / card ), used by PDF renderers. Set by open().
		 *
		 * @var bool
		 */
		private static $flat = false;

		/* ------------------------------------------------------------------ */
		/* Context and styles                                                  */
		/* ------------------------------------------------------------------ */

		/**
		 * Resolve colors, direction, store name / URL / logo.
		 *
		 * @param array $args accent, rtl, logo_url, store_url, store_name.
		 * @return array
		 */
		public static function context( $args = array() ) {
			$args   = is_array( $args ) ? $args : array();
			$accent = isset( $args['accent'] ) ? (string) $args['accent'] : ( function_exists( 'get_option' ) ? (string) get_option( 'ec_option_details_main_color' ) : '' );
			$accent = function_exists( 'apply_filters' ) ? (string) apply_filters( 'wp_easycart_email_accent_color', $accent, $args ) : $accent;
			if ( ! preg_match( '/^#(?:[0-9a-fA-F]{3}){1,2}$/', $accent ) ) {
				$accent = '#222222';
			}
			$hex = ltrim( $accent, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$luma = ( 0.299 * hexdec( substr( $hex, 0, 2 ) ) + 0.587 * hexdec( substr( $hex, 2, 2 ) ) + 0.114 * hexdec( substr( $hex, 4, 2 ) ) ) / 255;
			$rtl  = isset( $args['rtl'] ) ? (bool) $args['rtl'] : ( function_exists( 'is_rtl' ) && is_rtl() );

			$store_name = isset( $args['store_name'] ) ? (string) $args['store_name'] : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			if ( isset( $args['store_url'] ) ) {
				$store_url = (string) $args['store_url'];
			} else {
				$store_url = '';
				$page_id   = get_option( 'ec_option_storepage' );
				if ( $page_id && function_exists( 'get_permalink' ) ) {
					$store_url = (string) get_permalink( $page_id );
				}
				if ( '' === $store_url ) {
					$store_url = home_url( '/' );
				}
			}
			$ctx = array(
				'accent'     => $accent,
				'on_accent'  => ( $luma > 0.62 ) ? '#111827' : '#ffffff',
				'rtl'        => $rtl,
				'start'      => $rtl ? 'right' : 'left',
				'end'        => $rtl ? 'left' : 'right',
				'font'       => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
				'mono'       => "Menlo, Consolas, 'Courier New', monospace",
				'store_name' => $store_name,
				'store_url'  => $store_url,
				'logo_url'   => isset( $args['logo_url'] ) ? (string) $args['logo_url'] : (string) get_option( 'ec_option_email_logo' ),
				/* 6.0.1: the header image is sized and placed by the merchant. Width is a share of the email body,
				   height is a ceiling in pixels ( 0 = no ceiling ), and the default stays the old 80px / centered. */
				/* A document can size and place its own logo ( Settings › Documents, wp_easycart_documents::open_args() ). */
				'logo_align' => ( isset( $args['logo_align'] ) && in_array( $args['logo_align'], array( 'left', 'center', 'right' ), true ) ) ? $args['logo_align'] : self::logo_align(),
				'logo_max_w' => isset( $args['logo_max_w'] ) ? max( 5, min( 100, (int) $args['logo_max_w'] ) ) : self::logo_number( 'ec_option_email_logo_max_width', 40, 5, 100 ),
				'logo_max_h' => isset( $args['logo_max_h'] ) ? max( 0, min( 600, (int) $args['logo_max_h'] ) ) : self::logo_number( 'ec_option_email_logo_max_height', 80, 0, 600 ),
			);
			return (array) apply_filters( 'wp_easycart_email_design_context', $ctx, $args );
		}

		/**
		 * Where the header image sits: left, center ( the default ) or right.
		 *
		 * @since 6.0.1
		 * @return string
		 */
		public static function logo_align() {
			$align = (string) get_option( 'ec_option_email_logo_align' );
			return in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center';
		}

		/**
		 * One of the header image's size settings, kept inside sane bounds.
		 *
		 * @since 6.0.1
		 * @param string $option  Option name.
		 * @param int    $default Value when the option was never set.
		 * @param int    $min     Lowest allowed.
		 * @param int    $max     Highest allowed.
		 * @return int
		 */
		public static function logo_number( $option, $default, $min, $max ) {
			$value = get_option( $option );
			if ( '' === $value || null === $value || false === $value ) {
				return (int) $default;
			}
			$value = (int) $value;
			if ( $value < $min ) {
				return (int) $min;
			}
			return ( $value > $max ) ? (int) $max : $value;
		}
		/**
		 * Current context ( the one open() set, else defaults ).
		 *
		 * @return array
		 */
		public static function ctx() {
			if ( null === self::$ctx ) {
				self::$ctx = self::context();
			}
			return self::$ctx;
		}

		/**
		 * Inline style fragment.
		 *
		 * @param string $key text | small | label | link | heading | strong | muted | mono.
		 * @return string
		 */
		public static function css( $key ) {
			$c = self::ctx();
			$f = 'font-family:' . $c['font'] . ';';
			switch ( $key ) {
				case 'small':
					return $f . 'font-size:12px;line-height:1.5;color:#6b7280;';
				case 'label':
					return $f . 'font-size:11px;line-height:1.4;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;font-weight:600;';
				case 'link':
					return 'color:' . $c['accent'] . ';text-decoration:underline;';
				case 'heading':
					return $f . 'font-size:22px;line-height:1.3;font-weight:700;color:#111827;';
				case 'strong':
					return $f . 'font-size:14px;line-height:1.55;color:#111827;font-weight:600;';
				case 'muted':
					return $f . 'font-size:14px;line-height:1.55;color:#6b7280;';
				case 'mono':
					return 'font-family:' . $c['mono'] . ';font-size:14px;font-weight:600;color:#111827;word-break:break-all;';
				default:
					return $f . 'font-size:14px;line-height:1.55;color:#374151;';
			}
		}

		/**
		 * Text that must stay left-to-right inside RTL emails ( amounts, codes, tracking numbers ).
		 *
		 * @param string $text Plain text.
		 * @return string HTML.
		 */
		public static function ltr( $text ) {
			return '<span dir="ltr">' . esc_html( (string) $text ) . '</span>';
		}

		/**
		 * Formatted amount ( store currency ), LTR-safe.
		 *
		 * @param float $amount Amount.
		 * @return string HTML.
		 */
		public static function money( $amount ) {
			$text = isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ? $GLOBALS['currency']->get_currency_display( $amount ) : number_format( (float) $amount, 2 );
			return self::ltr( $text );
		}

		/* ------------------------------------------------------------------ */
		/* Document                                                            */
		/* ------------------------------------------------------------------ */

		/**
		 * Doctype, head, body, preheader, 600px container, accent bar and header.
		 *
		 * @param array $args title, preheader, lang, accent, rtl, logo_url, store_url, store_name,
		 *                    header ( bool, default true ), header_align ( start | center ), eyebrow ( small label above the content ),
		 *                    @since 6.0.1 top_html ( escaped HTML above the accent bar: the Invoice PDF's business details and heading ).
		 * @return string
		 */
		public static function get_open( $args = array() ) {
			$args       = is_array( $args ) ? $args : array();
			self::$ctx  = self::context( $args );
			self::$flat = ! empty( $args['flat'] ) || (bool) apply_filters( 'wp_easycart_email_design_flat', false, $args );
			$c          = self::$ctx;
			$lang      = isset( $args['lang'] ) ? (string) $args['lang'] : get_bloginfo( 'language' );
			$title     = isset( $args['title'] ) ? wp_strip_all_tags( (string) $args['title'] ) : $c['store_name'];
			$preheader = isset( $args['preheader'] ) ? trim( wp_strip_all_tags( (string) $args['preheader'] ) ) : '';
			/* 6.0.1: the header image's own alignment setting, unless the caller asked for one. */
			$align     = isset( $args['header_align'] ) ? ( 'center' === $args['header_align'] ? 'center' : $c['start'] ) : ( 'left' === $c['logo_align'] ? $c['start'] : ( 'right' === $c['logo_align'] ? $c['end'] : 'center' ) );

			$h  = '<!DOCTYPE html>' . "\n";
			$h .= '<html lang="' . esc_attr( $lang ) . '" dir="' . ( $c['rtl'] ? 'rtl' : 'ltr' ) . '" xmlns="http://www.w3.org/1999/xhtml">' . "\n";
			$h .= '<head>' . "\n";
			$h .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . "\n";
			$h .= '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n";
			$h .= '<meta name="x-apple-disable-message-reformatting" />' . "\n";
			$h .= '<meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no" />' . "\n";
			$h .= '<title>' . esc_html( $title ) . '</title>' . "\n";
			$h .= '<style type="text/css">'
				. 'body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}'
				. 'table,td{mso-table-lspace:0pt;mso-table-rspace:0pt;}'
				. 'img{-ms-interpolation-mode:bicubic;border:0;outline:none;text-decoration:none;}'
				. 'body{margin:0 !important;padding:0 !important;width:100% !important;}'
				/* Addresses, phone numbers and dates that mail clients turn into links keep the surrounding text style. */
				. 'a[x-apple-data-detectors],.ec-email-nolink a{color:inherit !important;text-decoration:none !important;font-size:inherit !important;font-family:inherit !important;font-weight:inherit !important;line-height:inherit !important;}'
				. 'u + #ec-email-body .ec-email-nolink a,#MessageViewBody .ec-email-nolink a{color:inherit !important;text-decoration:none !important;font-size:inherit !important;font-family:inherit !important;font-weight:inherit !important;line-height:inherit !important;}'
				. '@media only screen and (max-width:620px){'
				. '.ec-email-container{width:100% !important;}'
				. '.ec-email-pad{padding-left:20px !important;padding-right:20px !important;}'
				. '.ec-email-col{display:block !important;width:100% !important;box-sizing:border-box;}'
				. '.ec-email-col-gap{display:none !important;}'
				. '.ec-email-hide-sm{display:none !important;}'
				. '}'
				. ( isset( $args['extra_css'] ) ? (string) $args['extra_css'] : '' )
				. '</style>' . "\n";
			$h .= '</head>' . "\n";
			/* Flat ( PDF ) pages are white: a PDF renderer paints the body colour down to the foot of the page. */
			$h .= '<body id="ec-email-body" style="margin:0;padding:0;background-color:' . ( self::$flat ? '#ffffff' : '#f3f4f6' ) . ';">' . "\n";
			if ( '' !== $preheader ) {
				$h .= '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">' . esc_html( $preheader ) . '</div>' . "\n";
			}
			/*
			 * "Flat" leaves out the grey page wrapper and the card border so the content table is a direct child of
			 * <body>: PDF renderers ( dompdf, PRO PDF receipt ) cannot split a nested table across pages, so a long
			 * receipt would otherwise start on a blank page and be cut off.
			 */
			if ( ! empty( $args['top_html'] ) ) {
				$h .= (string) $args['top_html'] . "\n";
			}
			if ( ! self::$flat ) {
				$h .= '<table role="presentation" class="ec-email-bg" width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#f3f4f6" style="background-color:#f3f4f6;"><tr><td class="ec-email-shell" align="center" style="padding:24px 12px;">' . "\n";
				$h .= '<table role="presentation" class="ec-email-container" width="600" border="0" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:10px;border-collapse:separate;">' . "\n";
			} else {
				$h .= '<table role="presentation" class="ec-email-container" width="100%" border="0" cellpadding="0" cellspacing="0" style="width:100%;background-color:#ffffff;border-collapse:collapse;">' . "\n";
			}
			$h .= '<tr><td style="height:4px;line-height:4px;font-size:4px;background-color:' . esc_attr( $c['accent'] ) . ';border-radius:10px 10px 0 0;">&nbsp;</td></tr>' . "\n";
			if ( ! isset( $args['header'] ) || $args['header'] ) {
				$h .= '<tr><td class="ec-email-pad" align="' . esc_attr( $align ) . '" style="padding:24px 32px 8px 32px;">';
				if ( '' !== $c['logo_url'] ) {
					$logo_style = 'display:' . ( 'center' === $align ? 'inline-block' : 'block' ) . ';max-width:' . (int) $c['logo_max_w'] . '%;width:auto;height:auto;';
					if ( (int) $c['logo_max_h'] > 0 ) {
						$logo_style .= 'max-height:' . (int) $c['logo_max_h'] . 'px;';
					}
					$h .= '<a href="' . esc_url( $c['store_url'] ) . '" target="_blank" style="text-decoration:none;"><img src="' . esc_url( $c['logo_url'] ) . '" alt="' . esc_attr( $c['store_name'] ) . '" style="' . esc_attr( $logo_style ) . '" /></a>';
				} else {
					$h .= '<a href="' . esc_url( $c['store_url'] ) . '" target="_blank" style="font-family:' . esc_attr( $c['font'] ) . ';font-size:20px;font-weight:700;color:#111827;text-decoration:none;">' . esc_html( $c['store_name'] ) . '</a>';
				}
				$h .= '</td></tr>' . "\n";
			}
			if ( ! empty( $args['eyebrow'] ) ) {
				$h .= '<tr><td class="ec-email-pad" align="' . esc_attr( $c['start'] ) . '" style="padding:12px 32px 0 32px;"><span style="display:inline-block;padding:3px 10px;border-radius:999px;background-color:#f3f4f6;' . esc_attr( self::css( 'label' ) ) . '">' . esc_html( $args['eyebrow'] ) . '</span></td></tr>' . "\n";
			}
			return $h;
		}

		/**
		 * Signature ( optional ), footer with the store link, closing tags.
		 *
		 * @param array $args footer_html ( e.g. an unsubscribe link ), after_html ( e.g. an open-tracking pixel ),
		 *                    signature ( bool, default true: ec_option_email_signature_text / _image ),
		 *                    @since 6.0.1 signature_text ( bool, default true ), signature_image ( URL, '' for none, default the
		 *                    store's ), signature_image_w ( % ), signature_image_h ( px ) — see get_signature(); store_address ( text
 *                    printed under the store name in the footer ).
		 * @return string
		 */
		public static function get_close( $args = array() ) {
			$args = is_array( $args ) ? $args : array();
			$c    = self::ctx();
			$h    = '';
			if ( ! isset( $args['signature'] ) || $args['signature'] ) {
				$h .= self::get_signature( $args );
			}
			$footer = '<a href="' . esc_url( $c['store_url'] ) . '" target="_blank" style="color:#6b7280;text-decoration:none;">' . esc_html( $c['store_name'] ) . '</a>';
			/* 6.0.1: the store address, for documents whose profile shows it ( wp_easycart_documents::close_args() ). */
			if ( ! empty( $args['store_address'] ) && is_string( $args['store_address'] ) ) {
				$footer .= '<br /><span class="ec-email-nolink">' . nl2br( esc_html( trim( $args['store_address'] ) ) ) . '</span>';
			}
			if ( ! empty( $args['footer_html'] ) ) {
				$footer .= '<br />' . $args['footer_html'];
			}
			$footer = (string) apply_filters( 'wp_easycart_email_footer_html', $footer, $args );
			$h     .= '<tr><td class="ec-email-pad" align="center" style="padding:24px 32px 24px 32px;"><table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding-top:16px;border-top:1px solid #e5e7eb;' . esc_attr( self::css( 'small' ) ) . '">' . $footer . '</td></tr></table></td></tr>' . "\n";
			$h     .= '</table>' . "\n" . ( self::$flat ? '' : '</td></tr></table>' . "\n" );
			if ( ! empty( $args['after_html'] ) ) {
				$h .= $args['after_html'] . "\n";
			}
			$h        .= '</body>' . "\n" . '</html>' . "\n";
			self::$ctx  = null;
			self::$flat = false;
			return $h;
		}

		/**
		 * Signature text / image from Settings › Email ( '' when neither is set ).
		 *
		 * @since 6.0.1 $args: signature_text ( bool ), signature_image ( URL, '' for none; the store's when not given ),
		 *              signature_image_w ( % of the email ), signature_image_h ( px ceiling, 0 = none ). The store's image
		 *              size comes from ec_option_email_signature_image_max_width / _max_height ( 100 % / none ).
		 * @param array $args Overrides ( Settings › Documents, wp_easycart_documents::close_args() ).
		 * @return string
		 */
		public static function get_signature( $args = array() ) {
			$args  = is_array( $args ) ? $args : array();
			$text  = ( ! isset( $args['signature_text'] ) || $args['signature_text'] ) ? (string) get_option( 'ec_option_email_signature_text' ) : '';
			$image = isset( $args['signature_image'] ) ? (string) $args['signature_image'] : (string) get_option( 'ec_option_email_signature_image' );
			if ( '' === $text && '' === $image ) {
				return '';
			}
			$width  = isset( $args['signature_image_w'] ) ? max( 5, min( 100, (int) $args['signature_image_w'] ) ) : self::logo_number( 'ec_option_email_signature_image_max_width', 100, 5, 100 );
			$height = isset( $args['signature_image_h'] ) ? max( 0, min( 600, (int) $args['signature_image_h'] ) ) : self::logo_number( 'ec_option_email_signature_image_max_height', 0, 0, 600 );
			$c      = self::ctx();
			$h      = '<tr><td class="ec-email-pad" align="' . esc_attr( $c['start'] ) . '" style="padding:12px 32px 0 32px;' . esc_attr( self::css( 'text' ) ) . '">';
			if ( '' !== $text ) {
				$h .= '<div style="margin:0 0 10px 0;">' . nl2br( esc_html( $text ) ) . '</div>';
			}
			if ( '' !== $image ) {
				$style = 'display:block;max-width:' . (int) $width . '%;width:auto;height:auto;' . ( $height > 0 ? 'max-height:' . (int) $height . 'px;' : '' );
				$h    .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $c['store_name'] ) . '" style="' . esc_attr( $style ) . '" />';
			}
			return $h . '</td></tr>' . "\n";
		}

		/* ------------------------------------------------------------------ */
		/* Sections and text                                                   */
		/* ------------------------------------------------------------------ */

		/**
		 * Open a full-width content row.
		 *
		 * @param array $args top ( px, default 16 ), bottom ( px, default 0 ), align ( start | center | end ), style ( extra CSS ).
		 * @return string
		 */
		public static function get_section_start( $args = array() ) {
			$c      = self::ctx();
			$top    = isset( $args['top'] ) ? (int) $args['top'] : 16;
			$bottom = isset( $args['bottom'] ) ? (int) $args['bottom'] : 0;
			$align  = isset( $args['align'] ) ? ( 'center' === $args['align'] ? 'center' : ( 'end' === $args['align'] ? $c['end'] : $c['start'] ) ) : $c['start'];
			$style  = isset( $args['style'] ) ? (string) $args['style'] : '';
			return '<tr><td class="ec-email-pad" align="' . esc_attr( $align ) . '" style="padding:' . $top . 'px 32px ' . $bottom . 'px 32px;' . esc_attr( self::css( 'text' ) . $style ) . '">' . "\n";
		}

		/** @return string */
		public static function get_section_end() {
			return '</td></tr>' . "\n";
		}

		/**
		 * A whole section around ready-made HTML.
		 *
		 * @param string $html Inner HTML ( escaped ).
		 * @param array  $args See get_section_start().
		 * @return string
		 */
		public static function get_block( $html, $args = array() ) {
			return self::get_section_start( $args ) . $html . self::get_section_end();
		}

		/**
		 * Headline.
		 *
		 * @param string $html Escaped HTML.
		 * @return string
		 */
		public static function get_heading( $html ) {
			return '<p style="margin:0 0 12px 0;' . esc_attr( self::css( 'heading' ) ) . '">' . $html . '</p>' . "\n";
		}

		/**
		 * Paragraph.
		 *
		 * @param string $html Escaped HTML.
		 * @param array  $args tone ( text | small | muted | strong ), margin ( CSS, default "0 0 16px 0" ), nolink ( bool ).
		 * @return string
		 */
		public static function get_paragraph( $html, $args = array() ) {
			$tone   = isset( $args['tone'] ) ? (string) $args['tone'] : 'text';
			$margin = isset( $args['margin'] ) ? (string) $args['margin'] : '0 0 16px 0';
			return '<p' . ( ! empty( $args['nolink'] ) ? ' class="ec-email-nolink"' : '' ) . ' style="margin:' . esc_attr( $margin ) . ';' . esc_attr( self::css( $tone ) ) . '">' . $html . '</p>' . "\n";
		}

		/**
		 * Small uppercase label.
		 *
		 * @param string $html Escaped HTML.
		 * @return string
		 */
		public static function get_label( $html ) {
			return '<div style="margin:0 0 6px 0;' . esc_attr( self::css( 'label' ) ) . '">' . $html . '</div>';
		}

		/**
		 * Colored status banner ( payment failed, refunded, pre-order pickup … ) as a whole section.
		 *
		 * @param string $html Escaped HTML.
		 * @param string $tone success | danger | warning | info.
		 * @return string
		 */
		public static function get_notice( $html, $tone = 'info' ) {
			$tones = array(
				'success' => array( '#ecfdf5', '#16a34a', '#14532d' ),
				'danger'  => array( '#fef2f2', '#dc2626', '#7f1d1d' ),
				'warning' => array( '#fffbeb', '#d97706', '#78350f' ),
				'info'    => array( '#eff6ff', '#2563eb', '#1e3a8a' ),
			);
			$t = isset( $tones[ $tone ] ) ? $tones[ $tone ] : $tones['info'];
			$c = self::ctx();
			return '<tr><td class="ec-email-pad" style="padding:16px 32px 0 32px;"><table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="border-collapse:separate;"><tr>'
				. '<td align="' . esc_attr( $c['start'] ) . '" style="background-color:' . $t[0] . ';border-' . esc_attr( $c['start'] ) . ':4px solid ' . $t[1] . ';border-radius:6px;padding:12px 16px;' . esc_attr( self::css( 'strong' ) ) . 'color:' . $t[2] . ';">' . $html . '</td>'
				. '</tr></table></td></tr>' . "\n";
		}

		/* ------------------------------------------------------------------ */
		/* Cards, key / values, buttons                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Open a grey rounded card ( inside a section ).
		 *
		 * @param array $args padding ( CSS, default "16px 20px" ), background, border ( bool ).
		 * @return string
		 */
		public static function get_card_start( $args = array() ) {
			$c   = self::ctx();
			$pad = isset( $args['padding'] ) ? (string) $args['padding'] : '16px 20px';
			$bg  = isset( $args['background'] ) ? (string) $args['background'] : '#f9fafb';
			$bd  = isset( $args['dashed'] ) && $args['dashed'] ? '1px dashed #cbd5e1' : '1px solid #e5e7eb';
			return '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:' . esc_attr( $bg ) . ';border:' . $bd . ';border-radius:8px;border-collapse:separate;"><tr><td align="' . esc_attr( $c['start'] ) . '" style="padding:' . esc_attr( $pad ) . ';' . esc_attr( self::css( 'text' ) ) . '">' . "\n";
		}

		/** @return string */
		public static function get_card_end() {
			return '</td></tr></table>' . "\n";
		}

		/**
		 * Label / value columns that stack on phones ( carrier + tracking, order number + date … ).
		 *
		 * @param array $pairs Each: array( 'label' => html, 'value' => html, 'mono' => bool ).
		 * @return string
		 */
		public static function get_key_values( $pairs ) {
			$c     = self::ctx();
			$cells = '';
			foreach ( (array) $pairs as $pair ) {
				if ( ! isset( $pair['value'] ) || '' === trim( (string) $pair['value'] ) ) {
					continue;
				}
				$value_css = ! empty( $pair['mono'] ) ? self::css( 'mono' ) : 'font-family:' . $c['font'] . ';font-size:15px;font-weight:600;color:#111827;';
				$cells    .= '<td class="ec-email-col" valign="top" align="' . esc_attr( $c['start'] ) . '" style="padding-top:4px;padding-bottom:4px;padding-' . esc_attr( $c['end'] ) . ':16px;' . esc_attr( self::css( 'text' ) ) . '">'
					. '<div style="' . esc_attr( self::css( 'label' ) ) . '">' . ( isset( $pair['label'] ) ? $pair['label'] : '' ) . '</div>'
					. '<div style="' . esc_attr( $value_css ) . '">' . $pair['value'] . '</div></td>';
			}
			if ( '' === $cells ) {
				return '';
			}
			return '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0"><tr>' . $cells . '</tr></table>' . "\n";
		}

		/**
		 * Bulletproof button ( table cell with the accent background ).
		 *
		 * @param string $url  Link.
		 * @param string $text Plain text label.
		 * @param array  $args variant ( primary | secondary ), margin ( CSS ), arrow ( bool ).
		 * @return string
		 */
		public static function get_button( $url, $text, $args = array() ) {
			if ( '' === (string) $url ) {
				return '';
			}
			$c         = self::ctx();
			$secondary = isset( $args['variant'] ) && 'secondary' === $args['variant'];
			$bg        = $secondary ? '#ffffff' : $c['accent'];
			$fg        = $secondary ? '#111827' : $c['on_accent'];
			$border    = $secondary ? '1px solid #d1d5db' : '1px solid ' . $c['accent'];
			$margin    = isset( $args['margin'] ) ? (string) $args['margin'] : '0';
			$label     = esc_html( wp_strip_all_tags( (string) $text ) ) . ( ! empty( $args['arrow'] ) ? ' &rarr;' : '' );
			return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:' . esc_attr( $margin ) . ';"' . ( isset( $args['align'] ) && 'center' === $args['align'] ? ' align="center"' : '' ) . '><tr>'
				. '<td align="center" bgcolor="' . esc_attr( $bg ) . '" style="border-radius:6px;background-color:' . esc_attr( $bg ) . ';">'
				. '<a href="' . esc_url( $url ) . '" target="_blank" style="display:inline-block;padding:11px 22px;font-family:' . esc_attr( $c['font'] ) . ';font-size:14px;font-weight:600;color:' . esc_attr( $fg ) . ';text-decoration:none;border-radius:6px;border:' . esc_attr( $border ) . ';">' . $label . '</a>'
				. '</td></tr></table>' . "\n";
		}

		/**
		 * A section holding one button.
		 *
		 * @param string $url  Link.
		 * @param string $text Label.
		 * @param array  $args Button args plus top / bottom / align.
		 * @return string
		 */
		public static function get_button_row( $url, $text, $args = array() ) {
			$button = self::get_button( $url, $text, $args );
			if ( '' === $button ) {
				return '';
			}
			$section = array(
				'top'    => isset( $args['top'] ) ? $args['top'] : 8,
				'bottom' => isset( $args['bottom'] ) ? $args['bottom'] : 8,
				'align'  => isset( $args['align'] ) ? $args['align'] : 'start',
			);
			return self::get_block( $button, $section );
		}

		/**
		 * Dashed coupon / code box.
		 *
		 * @param string $code      Code ( plain ).
		 * @param string $label_html Escaped label.
		 * @param string $note_html  Escaped note under the code.
		 * @return string
		 */
		public static function get_code_box( $code, $label_html = '', $note_html = '' ) {
			$h  = self::get_card_start(
				array(
					'dashed'     => true,
					'background' => '#f8fafc',
					'padding'    => '14px 18px',
				)
			);
			$h .= ( '' !== $label_html ? '<div style="' . esc_attr( self::css( 'text' ) ) . '">' . $label_html . '</div>' : '' );
			$h .= '<div style="' . esc_attr( self::css( 'mono' ) ) . 'font-size:18px;letter-spacing:.04em;margin-top:4px;">' . self::ltr( $code ) . '</div>';
			$h .= ( '' !== $note_html ? '<div style="margin-top:4px;' . esc_attr( self::css( 'small' ) ) . '">' . $note_html . '</div>' : '' );
			return $h . self::get_card_end();
		}

		/* ------------------------------------------------------------------ */
		/* Addresses                                                           */
		/* ------------------------------------------------------------------ */

		/**
		 * Address lines in the destination country's order.
		 *
		 * @param object|array $source Order / user row.
		 * @param string       $side   Field prefix, e.g. 'billing' or 'shipping' ( '' for none ).
		 * @param array        $map    Field => property overrides ( first_name, last_name, company_name, address_line_1,
		 *                             address_line_2, city, state, zip, country, country_name, phone ).
		 * @return array array( 'lines' => string[], 'phone' => string, 'has' => bool ).
		 */
		public static function address( $source, $side = 'billing', $map = array() ) {
			$source = is_array( $source ) ? (object) $source : $source;
			$get    = function ( $field ) use ( $source, $side, $map ) {
				$key = isset( $map[ $field ] ) ? $map[ $field ] : ( '' !== $side ? $side . '_' . $field : $field );
				return ( is_object( $source ) && isset( $source->{$key} ) && is_scalar( $source->{$key} ) ) ? trim( (string) $source->{$key} ) : '';
			};
			$lines   = array();
			$lines[] = trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) );
			$lines[] = $get( 'company_name' );
			$lines[] = $get( 'address_line_1' );
			$lines[] = $get( 'address_line_2' );
			$country = strtoupper( $get( 'country' ) );
			$city    = $get( 'city' );
			$state   = $get( 'state' );
			$zip     = $get( 'zip' );
			if ( in_array( $country, array( 'GB', 'IE', 'IM', 'JE', 'GG' ), true ) ) {
				$lines[] = $city;
				$lines[] = $state;
				$lines[] = $zip;
			} elseif ( in_array( $country, array( 'AT', 'BE', 'BG', 'CH', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IS', 'IT', 'LI', 'LT', 'LU', 'MC', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'TR' ), true ) ) {
				$lines[] = trim( $zip . ' ' . $city );
				$lines[] = $state;
			} else {
				$line = $city;
				if ( '' !== $state ) {
					$line .= ( '' !== $line ? ', ' : '' ) . $state;
				}
				if ( '' !== $zip ) {
					$line .= ( '' !== $line ? ' ' : '' ) . $zip;
				}
				$lines[] = $line;
			}
			$country_name = $get( 'country_name' );
			$lines[]      = ( '' !== $country_name ) ? $country_name : $get( 'country' );
			$lines        = array_values( array_filter( $lines, 'strlen' ) );
			$lines        = (array) apply_filters( 'wp_easycart_email_address_lines', $lines, $side, $source );
			return array(
				'lines' => $lines,
				'phone' => $get( 'phone' ),
				'has'   => ( '' !== $get( 'address_line_1' ) || '' !== $city || '' !== $zip ),
			);
		}

		/**
		 * One or two address cards side by side ( stacked on phones ). Cards without an address are skipped.
		 *
		 * @param array  $cards      Each: array( 'label' => html, 'address' => address() result ).
		 * @param string $extra_html Escaped HTML under the cards ( e.g. VAT number ).
		 * @return string Whole section, '' when there is nothing to show.
		 */
		public static function get_address_cards( $cards, $extra_html = '' ) {
			$c     = self::ctx();
			$cards = array_values(
				array_filter(
					(array) $cards,
					function ( $card ) {
						return ! empty( $card['address']['has'] );
					}
				)
			);
			if ( ! $cards && '' === $extra_html ) {
				return '';
			}
			$width = ( 2 === count( $cards ) ) ? '48%' : '100%';
			$cells = '';
			foreach ( $cards as $i => $card ) {
				if ( $i > 0 ) {
					$cells .= '<td class="ec-email-col-gap" width="4%" style="width:4%;font-size:0;line-height:0;">&nbsp;</td>';
				}
				$body = implode( '<br />', array_map( 'esc_html', $card['address']['lines'] ) );
				if ( '' !== $card['address']['phone'] ) {
					$body .= '<br /><span style="color:#6b7280;">' . esc_html( $card['address']['phone'] ) . '</span>';
				}
				$cells .= '<td class="ec-email-col" width="' . $width . '" valign="top" align="' . esc_attr( $c['start'] ) . '" style="width:' . $width . ';padding:0 0 12px 0;">'
					. '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;border-collapse:separate;"><tr>'
					. '<td align="' . esc_attr( $c['start'] ) . '" style="padding:14px 16px;">'
					. self::get_label( isset( $card['label'] ) ? $card['label'] : '' )
					. '<div class="ec-email-nolink" style="' . esc_attr( self::css( 'text' ) ) . 'color:#111827;">' . $body . '</div>'
					. '</td></tr></table></td>';
			}
			$h  = '<tr><td class="ec-email-pad" style="padding:16px 32px 8px 32px;"><table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">';
			$h .= $cells ? '<tr>' . $cells . '</tr>' : '';
			if ( '' !== $extra_html ) {
				$h .= '<tr><td colspan="' . max( 1, ( 2 * count( $cards ) ) - 1 ) . '" align="' . esc_attr( $c['start'] ) . '" style="padding:0 0 8px 0;' . esc_attr( self::css( 'small' ) ) . '">' . $extra_html . '</td></tr>';
			}
			return $h . '</table></td></tr>' . "\n";
		}

		/* ------------------------------------------------------------------ */
		/* Items and totals                                                    */
		/* ------------------------------------------------------------------ */

		/**
		 * Open the item table ( as a section ) with its header row.
		 *
		 * @param array $labels product, qty, unit, total ( escaped HTML; a missing / null key drops that column ).
		 * @return string
		 */
		public static function get_items_start( $labels = array() ) {
			$c  = self::ctx();
			$th = 'border-bottom:2px solid #111827;' . self::css( 'label' );
			$h  = '<tr><td class="ec-email-pad" style="padding:16px 32px 0 32px;"><table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>';
			$h .= '<td align="' . esc_attr( $c['start'] ) . '" style="padding:0 0 8px 0;' . esc_attr( $th ) . '">' . ( isset( $labels['product'] ) ? $labels['product'] : '' ) . '</td>';
			if ( isset( $labels['qty'] ) ) {
				$h .= '<td align="center" width="44" style="padding:0 4px 8px 4px;' . esc_attr( $th ) . '">' . $labels['qty'] . '</td>';
			}
			if ( isset( $labels['unit'] ) ) {
				$h .= '<td class="ec-email-hide-sm" align="' . esc_attr( $c['end'] ) . '" width="90" style="padding:0 4px 8px 4px;' . esc_attr( $th ) . '">' . $labels['unit'] . '</td>';
			}
			if ( isset( $labels['total'] ) ) {
				$h .= '<td align="' . esc_attr( $c['end'] ) . '" width="90" style="padding:0 0 8px 4px;' . esc_attr( $th ) . '">' . $labels['total'] . '</td>';
			}
			return $h . '</tr>' . "\n";
		}

		/**
		 * Open one item row: image cell, then a details table whose first row is the title.
		 * Echo detail rows ( get_detail() or any "<tr><td>…</td></tr>" a hook prints ) before item_end().
		 *
		 * @param array $args image_url, image_alt, image_width ( default 70 ), title_html.
		 * @return string
		 */
		public static function get_item_start( $args = array() ) {
			$c     = self::ctx();
			$width = isset( $args['image_width'] ) ? max( 32, min( 120, (int) $args['image_width'] ) ) : 70;
			$h     = '<tr><td valign="top" align="' . esc_attr( $c['start'] ) . '" style="padding:14px 0;border-bottom:1px solid #e5e7eb;"><table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0"><tr>';
			if ( ! empty( $args['image_url'] ) ) {
				$h .= '<td class="ec_receipt_item_image" valign="top" width="' . ( $width + 12 ) . '" style="width:' . ( $width + 12 ) . 'px;padding-' . esc_attr( $c['end'] ) . ':12px;">'
					. '<img src="' . esc_url( $args['image_url'] ) . '" width="' . $width . '" alt="' . esc_attr( isset( $args['image_alt'] ) ? wp_strip_all_tags( (string) $args['image_alt'] ) : '' ) . '" style="display:block;width:' . $width . 'px;max-width:' . $width . 'px;height:auto;border-radius:6px;border:1px solid #e5e7eb;" /></td>';
			}
			$h .= '<td valign="top" align="' . esc_attr( $c['start'] ) . '" style="' . esc_attr( self::css( 'text' ) ) . 'word-wrap:break-word;overflow-wrap:anywhere;">';
			$h .= '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="' . esc_attr( self::css( 'small' ) ) . '">';
			$h .= '<tr><td style="font-family:' . esc_attr( $c['font'] ) . ';font-size:14px;line-height:1.4;font-weight:600;color:#111827;padding:0 0 2px 0;">' . ( isset( $args['title_html'] ) ? $args['title_html'] : '' ) . '</td></tr>' . "\n";
			return $h;
		}

		/**
		 * One detail row under an item title ( SKU, option, gift message, download link … ).
		 *
		 * @param string $html Escaped HTML.
		 * @param array  $args nolink ( bool ), style ( extra CSS ).
		 * @return string
		 */
		public static function get_detail( $html, $args = array() ) {
			return '<tr><td' . ( ! empty( $args['nolink'] ) ? ' class="ec-email-nolink"' : '' ) . ' style="' . esc_attr( self::css( 'small' ) . ( isset( $args['style'] ) ? $args['style'] : '' ) ) . '">' . $html . '</td></tr>' . "\n";
		}

		/**
		 * "Label: value" detail row.
		 *
		 * @param string $label_html Escaped label.
		 * @param string $value_html Escaped value.
		 * @return string
		 */
		public static function get_option_detail( $label_html, $value_html ) {
			return self::get_detail( ( '' !== $label_html ? '<strong style="color:#374151;">' . $label_html . ':</strong> ' : '' ) . $value_html );
		}

		/**
		 * Close an item row with its quantity / price cells.
		 *
		 * @param array $args qty ( plain ), unit_html, total_html ( escaped; omit a key to omit that column, matching items_start() ).
		 * @return string
		 */
		public static function get_item_end( $args = array() ) {
			$c    = self::ctx();
			$cell = 'border-bottom:1px solid #e5e7eb;' . self::css( 'text' );
			$h    = '</table></td></tr></table></td>';
			if ( array_key_exists( 'qty', $args ) ) {
				$h .= '<td valign="top" align="center" style="padding:14px 4px;' . esc_attr( $cell ) . '">' . esc_html( (string) $args['qty'] ) . '</td>';
			}
			if ( array_key_exists( 'unit_html', $args ) ) {
				$h .= '<td class="ec-email-hide-sm" valign="top" align="' . esc_attr( $c['end'] ) . '" style="padding:14px 4px;white-space:nowrap;' . esc_attr( $cell ) . '">' . $args['unit_html'] . '</td>';
			}
			if ( array_key_exists( 'total_html', $args ) ) {
				$h .= '<td valign="top" align="' . esc_attr( $c['end'] ) . '" style="padding:14px 0 14px 4px;white-space:nowrap;' . esc_attr( $cell ) . 'color:#111827;font-weight:600;">' . $args['total_html'] . '</td>';
			}
			return $h . '</tr>' . "\n";
		}

		/** @return string */
		public static function get_items_end() {
			return '</table></td></tr>' . "\n";
		}

		/**
		 * Totals block ( a section ).
		 *
		 * @param array $rows  Each: array( label_html, value_html ) or array( 'label' => html, 'value' => html, 'tone' => 'danger'|'success'|'strong' ).
		 * @param array $grand Optional array( label_html, value_html ) shown bold under a rule.
		 * @return string
		 */
		public static function get_totals( $rows, $grand = array() ) {
			$c = self::ctx();
			$h = '<tr><td class="ec-email-pad" style="padding:12px 32px 8px 32px;"><table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">';
			foreach ( (array) $rows as $row ) {
				$label = isset( $row['label'] ) ? $row['label'] : ( isset( $row[0] ) ? $row[0] : '' );
				$value = isset( $row['value'] ) ? $row['value'] : ( isset( $row[1] ) ? $row[1] : '' );
				$tone  = isset( $row['tone'] ) ? $row['tone'] : '';
				$color = ( 'danger' === $tone ) ? '#b91c1c' : ( ( 'success' === $tone ) ? '#15803d' : '#111827' );
				$h    .= '<tr><td align="' . esc_attr( $c['end'] ) . '" style="padding:3px 12px 3px 0;' . esc_attr( self::css( 'muted' ) ) . ( 'strong' === $tone ? 'font-weight:700;color:#111827;' : '' ) . '">' . $label . '</td>'
					. '<td align="' . esc_attr( $c['end'] ) . '" width="120" style="padding:3px 0;white-space:nowrap;' . esc_attr( self::css( 'text' ) ) . 'color:' . $color . ';' . ( 'strong' === $tone ? 'font-weight:700;' : '' ) . '">' . $value . '</td></tr>';
			}
			if ( ! empty( $grand ) ) {
				$big = 'font-family:' . $c['font'] . ';font-size:15px;font-weight:700;color:#111827;';
				$h  .= '<tr><td align="' . esc_attr( $c['end'] ) . '" style="padding:10px 12px 0 0;border-top:1px solid #e5e7eb;' . esc_attr( $big ) . '">' . $grand[0] . '</td>'
					. '<td align="' . esc_attr( $c['end'] ) . '" width="120" style="padding:10px 0 0 0;border-top:1px solid #e5e7eb;white-space:nowrap;' . esc_attr( $big ) . '">' . $grand[1] . '</td></tr>';
			}
			return $h . '</table></td></tr>' . "\n";
		}

		/**
		 * Simple data table for admin digests ( low stock report, etc. ).
		 *
		 * @param array $head  Escaped header cells; array( html, 'align' ) pairs allowed.
		 * @param array $rows  Rows of escaped cells.
		 * @return string Table HTML ( place it inside a section ).
		 */
		public static function get_table( $head, $rows ) {
			$c  = self::ctx();
			$h  = '<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>';
			$al = array();
			foreach ( (array) $head as $i => $cell ) {
				$html     = is_array( $cell ) ? $cell[0] : $cell;
				$al[ $i ] = ( is_array( $cell ) && isset( $cell[1] ) && 'end' === $cell[1] ) ? $c['end'] : $c['start'];
				$h       .= '<td align="' . esc_attr( $al[ $i ] ) . '" style="padding:0 6px 8px 6px;border-bottom:2px solid #111827;' . esc_attr( self::css( 'label' ) ) . '">' . $html . '</td>';
			}
			$h .= '</tr>';
			foreach ( (array) $rows as $row ) {
				$h .= '<tr>';
				foreach ( array_values( (array) $row ) as $i => $cell ) {
					$h .= '<td align="' . esc_attr( isset( $al[ $i ] ) ? $al[ $i ] : $c['start'] ) . '" valign="top" style="padding:10px 6px;border-bottom:1px solid #e5e7eb;' . esc_attr( self::css( 'text' ) ) . '">' . $cell . '</td>';
				}
				$h .= '</tr>';
			}
			return $h . '</table>' . "\n";
		}

		/* ------------------------------------------------------------------ */
		/* Data helpers                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Carrier tracking page for a tracking number ( '' when the carrier is unknown ).
		 *
		 * @param string $carrier  Carrier name.
		 * @param string $tracking Tracking number.
		 * @return string
		 */
		public static function tracking_url( $carrier, $tracking ) {
			$carrier  = strtolower( trim( (string) $carrier ) );
			$tracking = trim( (string) $tracking );
			if ( '' === $carrier || '' === $tracking ) {
				return '';
			}
			$map = (array) apply_filters(
				'wp_easycart_ecv2_tracking_url_map',
				array(
					'usps'           => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=%s',
					'ups'            => 'https://www.ups.com/track?tracknum=%s',
					'fedex'          => 'https://www.fedex.com/fedextrack/?trknbr=%s',
					'dhl'            => 'https://www.dhl.com/en/express/tracking.html?AWB=%s',
					'canada post'    => 'https://www.canadapost-postescanada.ca/track-reperage/en#/search?searchFor=%s',
					'royal mail'     => 'https://www.royalmail.com/track-your-item#/tracking-results/%s',
					'auspost'        => 'https://auspost.com.au/mypost/track/#/details/%s',
					'australia post' => 'https://auspost.com.au/mypost/track/#/details/%s',
				)
			);
			if ( isset( $map[ $carrier ] ) ) {
				return sprintf( $map[ $carrier ], rawurlencode( $tracking ) );
			}
			foreach ( $map as $key => $url ) {
				if ( '' !== (string) $key && false !== strpos( $carrier, (string) $key ) ) {
					return sprintf( $url, rawurlencode( $tracking ) );
				}
			}
			return '';
		}

		/**
		 * Product image for an order / cart line, with the store's fallbacks ( same order as the legacy templates ).
		 *
		 * @param string $image1           image1 value.
		 * @param bool   $is_deconetwork   DecoNetwork line.
		 * @param string $deconetwork_link DecoNetwork image path.
		 * @return string URL ( https kept ).
		 */
		public static function product_image_url( $image1, $is_deconetwork = false, $deconetwork_link = '' ) {
			$image1 = (string) $image1;
			if ( $is_deconetwork ) {
				return 'https://' . get_option( 'ec_option_deconetwork_url' ) . $deconetwork_link;
			}
			if ( 'http://' === substr( $image1, 0, 7 ) || 'https://' === substr( $image1, 0, 8 ) ) {
				return $image1;
			}
			if ( '' !== $image1 && defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $image1 ) && ! is_dir( EC_PLUGIN_DATA_DIRECTORY . '/products/pics1/' . $image1 ) ) {
				return plugins_url( 'wp-easycart-data/products/pics1/' . $image1, EC_PLUGIN_DATA_DIRECTORY );
			}
			if ( '' !== (string) get_option( 'ec_option_product_image_default' ) ) {
				return (string) get_option( 'ec_option_product_image_default' );
			}
			if ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && file_exists( EC_PLUGIN_DATA_DIRECTORY . '/design/theme/' . get_option( 'ec_option_base_theme' ) . '/images/ec_image_not_found.jpg' ) ) {
				return plugins_url( 'wp-easycart-data/design/theme/' . get_option( 'ec_option_base_theme' ) . '/images/ec_image_not_found.jpg', EC_PLUGIN_DATA_DIRECTORY );
			}
			return plugins_url( 'wp-easycart/design/theme/' . get_option( 'ec_option_latest_theme' ) . '/images/ec_image_not_found.jpg', EC_PLUGIN_DIRECTORY );
		}

		/**
		 * Wrap a plain-text or bare-HTML message ( built in PHP, not a template ) in the store design.
		 *
		 * @param string $body_html Escaped HTML body ( paragraphs / links ).
		 * @param array  $args      open() args plus heading ( plain ), button_url, button_text, eyebrow, footer_html.
		 * @return string Complete HTML email.
		 */
		public static function wrap( $body_html, $args = array() ) {
			$args = is_array( $args ) ? $args : array();
			$h    = self::get_open( $args );
			$h   .= self::get_section_start();
			if ( ! empty( $args['heading'] ) ) {
				$h .= self::get_heading( esc_html( $args['heading'] ) );
			}
			$h .= '<div style="' . esc_attr( self::css( 'text' ) ) . '">' . $body_html . '</div>';
			$h .= self::get_section_end();
			if ( ! empty( $args['button_url'] ) && ! empty( $args['button_text'] ) ) {
				$h .= self::get_button_row( $args['button_url'], $args['button_text'], array( 'top' => 16 ) );
			}
			$h .= self::get_close( array( 'footer_html' => isset( $args['footer_html'] ) ? $args['footer_html'] : '' ) );
			return $h;
		}

		/* ------------------------------------------------------------------ */
		/* Echo twins ( templates )                                            */
		/* ------------------------------------------------------------------ */

		/**
		 * Echo the get_*() twin of a method. Output is escaped inside each get_*() method.
		 *
		 * @param string $name Method.
		 * @param array  $args Arguments.
		 */
		public static function __callStatic( $name, $args ) {
			$getter = 'get_' . $name;
			if ( method_exists( __CLASS__, $getter ) ) {
				echo call_user_func_array( array( __CLASS__, $getter ), $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every get_*() method escapes its plain arguments; *_html arguments are documented as pre-escaped.
				return;
			}
			if ( function_exists( '_doing_it_wrong' ) ) {
				_doing_it_wrong( esc_html( __CLASS__ . '::' . $name ), 'Unknown email design block.', '6.0.0' );
			}
		}
	}

endif;
