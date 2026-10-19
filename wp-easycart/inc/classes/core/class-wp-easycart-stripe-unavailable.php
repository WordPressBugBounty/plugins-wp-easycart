<?php
/**
 * When Stripe cannot start a card payment ( 6.0.5 ).
 *
 * The card form needs a payment Stripe started for the checkout ( its client secret ). When the server could not reach Stripe ( a host
 * whose DNS or firewall stops it: "cURL error 6: Could not resolve host: api.stripe.com" ) or Stripe refused the request, there is none,
 * and Stripe's script refused to draw the form: shoppers saw the developer's alert "Your WP EasyCart with Stripe has a problem: Invalid
 * value for elements(): clientSecret should be a client secret of the form ${id}_secret_${secret}. You specified: .". The same alert
 * showed when Stripe's script could not start in the browser ( js.stripe.com blocked ).
 *
 * Now the card form's place says card payments can't be taken right now ( phrase cart_payment_information › stripe_unavailable ),
 * Place order with the card stops with the same words instead of posting a card payment that cannot work, other ways to pay keep
 * working, and store managers ( only ) see why: the last connection error ec_stripe_connect and WP EasyCart PRO's ec_stripe record
 * ( ec_option_stripe_last_connection_error, cleared by the next answer from Stripe ), Stripe's refusal on this request ( note() ),
 * or the browser's own error.
 *
 * js() is the script's definition, printed inside each Stripe script ( it defines window.wpeasycart_stripe_unavailable once a page );
 * the payment scripts call window.wpeasycart_stripe_unavailable( err, holder, form ).
 *
 * @package wp-easycart
 * @since 6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_stripe_unavailable' ) ) :

	/**
	 * The card form's place when Stripe cannot start a payment.
	 */
	class wp_easycart_stripe_unavailable {

		/**
		 * Stripe's refusal on this request ( a create call answered with an error ), for store managers.
		 *
		 * @var string
		 */
		private static $refusal = '';

		/**
		 * Record Stripe's answer to a payment it would not start ( this request only ).
		 *
		 * @param string $message Stripe's error message.
		 */
		public static function note( $message ) {
			self::$refusal = trim( wp_strip_all_tags( (string) $message ) );
		}

		/**
		 * What shoppers read: card payments can't be taken right now.
		 *
		 * @return string Plain text.
		 */
		public static function text() {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'cart_payment_information', 'stripe_unavailable' ) : null;
			$text = trim( wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) );
			if ( '' === $text ) {
				$text = "We can't take card payments right now. Please try again in a few minutes, or contact us if it keeps happening.";
			}
			/**
			 * The words shown in place of the card form when Stripe cannot start a payment.
			 *
			 * @since 6.0.5
			 * @param string $text Plain text.
			 */
			return (string) apply_filters( 'wp_easycart_stripe_unavailable_text', $text );
		}

		/**
		 * Whether the person viewing runs the store ( the reason is shown to them only ).
		 *
		 * @return bool
		 */
		public static function staff() {
			return function_exists( 'current_user_can' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) );
		}

		/**
		 * Why the server got no payment from Stripe, in words for store managers ( '' when nothing was recorded ).
		 *
		 * @return string
		 */
		public static function server_reason() {
			if ( '' !== self::$refusal ) {
				/* translators: %s: Stripe's own error message. */
				return sprintf( __( 'Only store managers see this. Stripe refused to start the payment: %s', 'wp-easycart' ), self::$refusal );
			}
			$error = trim( (string) get_option( 'ec_option_stripe_last_connection_error', '' ) );
			if ( '' === $error ) {
				return '';
			}
			$error = preg_replace( '/^http_request_failed:\s*/', '', $error );
			$at    = (int) get_option( 'ec_option_stripe_last_connection_error_time', 0 );
			$when  = ( $at > 0 && function_exists( 'human_time_diff' ) ) ? sprintf( /* translators: %s: a time span, e.g. "5 mins". */ __( '%s ago', 'wp-easycart' ), human_time_diff( $at, time() ) ) : '';
			return sprintf(
				/* translators: 1: the connection error, e.g. "cURL error 6: Could not resolve host: api.stripe.com", 2: when it happened, e.g. "5 mins ago". */
				__( 'Only store managers see this. Your web server could not reach Stripe ( %1$s, %2$s ), so no card payment could start. The server has to be able to connect to api.stripe.com over HTTPS: ask your web host to check its DNS and firewall.', 'wp-easycart' ),
				$error,
				$when
			);
		}

		/**
		 * The script's settings: the words, and for store managers the reasons.
		 *
		 * @return array
		 */
		public static function config() {
			$config = array( 'text' => self::text() );
			if ( self::staff() ) {
				$config['server'] = self::server_reason();
				/* translators: %s: the browser's error message. */
				$config['browser'] = __( 'Only store managers see this. Stripe\'s script could not start in this browser ( %s ). A browser extension or a network that blocks js.stripe.com causes this.', 'wp-easycart' );
				$config['none']    = __( 'Only store managers see this. Stripe did not start a card payment for this checkout. Its answer is under Settings › Log entries.', 'wp-easycart' );
			}
			return $config;
		}

		/**
		 * The script's definition, for inside a script element: window.wpeasycart_stripe_unavailable( err, holder, form ) puts the words
		 * in the card form's place ( holder: an element or a selector; else the top of the cart or account page ) and, with a form,
		 * stops Place order with the card ( other ways to pay go on ). Defined once a page; the settings follow the last print.
		 *
		 * Never a "<" in it, comments included: a block theme runs wptexturize() over the whole page ( get_the_block_template_html() ),
		 * which reads one as a tag and then rewrites every "&&" after it, in the rest of the page's script too ( 6.0.5: "i < staff.length"
		 * broke My Account's card update on Twenty Twenty-Five; test 605/r605zzzb_stripe_js_texturize_test.php ).
		 *
		 * @return string JavaScript.
		 */
		public static function js() {
			return '
			if ( "function" !== typeof window.wpeasycart_stripe_unavailable ) {
				window.wpeasycart_stripe_unavailable = function( err, holder, form ) {
					var api = window.wpeasycart_stripe_unavailable, c = api.config || {}, doc = document;
					api.down = true;
					if ( err && window.console && console.error ) {
						console.error( "WP EasyCart: Stripe could not start.", err );
					}
					var note = doc.createElement( "div" );
					note.className = "ec_cart_error wpec-stripe-unavailable";
					note.setAttribute( "role", "alert" );
					note.style.display = "block";
					var line = doc.createElement( "div" );
					line.textContent = c.text || "";
					note.appendChild( line );
					var staff = [];
					if ( c.server ) {
						staff.push( c.server );
					}
					if ( err && err.message && c.browser && ! c.server ) {
						staff.push( c.browser.replace( "%s", String( err.message ) ) );
					}
					if ( ! staff.length && c.none ) {
						staff.push( c.none );
					}
					staff.forEach( function( text ) {
						var why = doc.createElement( "span" ); /* inside the words block: one icon */
						why.className = "wpec-stripe-unavailable-staff";
						why.style.display = "block";
						why.style.marginTop = "6px";
						why.style.fontSize = "13px";
						why.style.opacity = ".85";
						why.textContent = text;
						line.appendChild( why );
					} );
					var box = holder ? ( ( "string" === typeof holder ) ? doc.querySelector( holder ) : holder ) : null;
					if ( box ) {
						box.innerHTML = "";
						box.appendChild( note );
					} else {
						var page = doc.querySelector( ".ec_cart_page, .ec_account_page" ) || doc.body;
						page.insertBefore( note, page.firstChild );
					}
					if ( form ) {
						if ( form.wpecStripeSubmit ) {
							form.removeEventListener( "submit", form.wpecStripeSubmit );
							form.wpecStripeSubmit = null;
						}
						if ( window.wpeasycart_checkout_presubmit && window.wpeasycart_checkout_presubmit.stripe ) {
							window.wpeasycart_checkout_presubmit.stripe = null;
						}
						if ( form.wpecStripeDown ) {
							form.removeEventListener( "submit", form.wpecStripeDown );
						}
						form.wpecStripeDown = function( event ) {
							var picked = doc.querySelector( "input[name=\"ec_cart_payment_selection\"]:checked" );
							if ( ( picked && "credit_card" !== String( picked.value ) ) || ! doc.body.contains( note ) ) {
								return; /* another way to pay, or the card form was drawn again */
							}
							event.preventDefault();
							if ( event.stopImmediatePropagation ) {
								event.stopImmediatePropagation();
							}
							if ( doc.getElementById( "wpeasycart_onepage_place" ) && "function" === typeof window.wpeasycart_onepage_show_errors ) {
								window.wpeasycart_onepage_show_errors( [ { message: c.text || "", code: "stripe_unavailable" } ] );
							} else {
								if ( window.wpeasycart_checkout_busy && "function" === typeof window.wpeasycart_checkout_busy.unlock ) {
									window.wpeasycart_checkout_busy.unlock();
								}
								var show = doc.getElementById( "ec_cart_submit_order" ), wait = doc.getElementById( "ec_cart_submit_order_working" ), cover = doc.getElementById( "stripe-success-cover" );
								if ( show ) { show.style.display = ""; }
								if ( wait ) { wait.style.display = "none"; }
								if ( cover ) { cover.style.display = "none"; }
							}
							if ( note.scrollIntoView ) {
								note.scrollIntoView( { block: "center", behavior: "smooth" } );
							}
						};
						form.addEventListener( "submit", form.wpecStripeDown );
					}
					return note;
				};
			}
			window.wpeasycart_stripe_unavailable.config = ' . wp_json_encode( self::config() ) . ';
			';
		}

		/**
		 * The call for a script's catch block or its empty-secret branch: the definition's call if it is there, else the console.
		 *
		 * @param string $err    JavaScript expression for the error ( 'err', 'null' ).
		 * @param string $holder The card form's holder, a CSS selector ( '' = the top of the page ).
		 * @param string $form   JavaScript expression for the form whose Place order stops ( 'null' = none ).
		 * @return string JavaScript.
		 */
		public static function call( $err = 'err', $holder = '', $form = 'null' ) {
			return 'if ( "function" === typeof window.wpeasycart_stripe_unavailable ) { window.wpeasycart_stripe_unavailable( ' . $err . ', ' . wp_json_encode( (string) $holder ) . ', ' . $form . ' ); } else if ( window.console ) { console.error( ' . $err . ' ); }';
		}
	}

endif;
