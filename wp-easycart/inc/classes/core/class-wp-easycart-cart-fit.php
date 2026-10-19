<?php
/**
 * The cart and checkout laid out for the space the theme gives them ( 6.0.5, Matt: a customer's OceanWP site gave the cart about
 * 830px of a 1400px screen, so the checkout kept its desktop columns, the order summary was squeezed under 300px and the classic
 * cart's table scrolled sideways ).
 *
 * The store's layouts change with the screen's width ( head_content.php's columns per screen size, ec-store.css's narrower rules ).
 * With Settings › Design › Cart & checkout look › "Fit the cart to your theme's content area" on, a small script printed after the
 * cart ( script() ) measures each cart ( .ec_cart_page ) and names the widths it fits on the element itself:
 * data-wpec-fit="tablet_wide w989" ( the screen size of that width, as Settings › Design names them, then every narrower rule's edge it
 * is at or under ). ec-store.css repeats each narrower cart rule for those names ( right after the rule it copies, with the same weight:
 * the condition sits in :where() ), and head_css() repeats the columns per screen size, so a cart 830px wide gets what an 830px screen
 * gets. Carts the Elementor Cart / Checkout widgets draw are left alone: their layout is set per device in Elementor.
 *
 * Off, nothing on the storefront changes. For a store manager the same script then only measures the cart page and reports it
 * ( record() ), and the admin recommends the setting when the theme gives the cart less room than the screen's layout needs
 * ( recommended(), wp_easycart_admin_cart_fit ).
 *
 * A new store starts with it on ( Matt: the default for new stores, never switched on for a store that already exists ):
 * seed_new_store() saves it once, when ec_db_manager::install_db() sets up an empty store. A store set up before has no saved
 * value until its owner chooses, and enabled() reads that as off, so its cart keeps the layout it had.
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_cart_fit' ) ) :

	/**
	 * Cart layout by the cart's own width.
	 */
	class wp_easycart_cart_fit {

		/**
		 * The setting ( '1' on, '0' off ).
		 */
		const OPTION = 'ec_option_cart_fit';

		/**
		 * The latest measurement of the cart page ( autoload off ): theme, width, viewport, page, source, at, fail.
		 */
		const CHECK_OPTION = 'wp_easycart_cart_fit_check';

		/**
		 * The theme ( stylesheet ) whose recommendation a store manager put away ( Not now ).
		 */
		const DISMISS_OPTION = 'wp_easycart_cart_fit_dismissed';

		/**
		 * The nonce of the store manager's report.
		 */
		const NONCE = 'wp-easycart-cart-fit';

		/**
		 * The request parameter of the admin's check ( the cart page in a hidden frame of Settings › Design ): no report from it.
		 */
		const CHECK_ARG = 'wpec_cart_fit_check';

		/**
		 * The width the narrow layout starts at: under it a cart gets the tablet layout ( ec-store.css's max-width:989px rules ).
		 */
		const NARROW = 990;

		/**
		 * Whether the script was printed in this request.
		 *
		 * @var bool
		 */
		private static $printed = false;

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_head', array( __CLASS__, 'head_css' ), 11 );
			add_action( 'wp_ajax_ec_ajax_cart_fit_report', array( __CLASS__, 'ajax_report' ) );
		}

		/**
		 * Whether carts are laid out for their own width.
		 *
		 * @return bool
		 */
		public static function enabled() {
			/**
			 * Whether the cart and checkout are laid out for the space the theme gives them.
			 *
			 * @since 6.0.5
			 * @param bool $enabled Settings › Design › Cart & checkout look.
			 */
			return (bool) apply_filters( 'wp_easycart_cart_fit', '1' === (string) get_option( self::OPTION, '0' ) );
		}

		/**
		 * Switch the setting on for a new store. Called once, by ec_db_manager::install_db() when it seeds an empty store's base data
		 * ( never by install_base_data() itself: Store Status › Repair runs that on stores that exist ). A saved choice is kept.
		 *
		 * @since 6.0.5
		 * @return bool Whether the setting was saved now.
		 */
		public static function seed_new_store() {
			/**
			 * Whether a new store starts with its cart fitted to the theme's content area.
			 *
			 * @since 6.0.5
			 * @param bool $on On ( default ).
			 */
			$on = (bool) apply_filters( 'wp_easycart_cart_fit_new_store', true );
			return add_option( self::OPTION, $on ? '1' : '0' );
		}

		/**
		 * Forget the setting, the measurement and the recommendation put away ( uninstall: a store installed again is a new store ).
		 *
		 * @since 6.0.5
		 */
		public static function uninstall() {
			delete_option( self::OPTION );
			delete_option( self::CHECK_OPTION );
			delete_option( self::DISMISS_OPTION );
		}

		/**
		 * The screen sizes, widest first: key ( as the columns settings name them ) => the width it starts at.
		 *
		 * @return array
		 */
		public static function bands() {
			return array(
				'desktop'     => 1140,
				'laptop'      => 990,
				'tablet_wide' => 768,
				'tablet'      => 481,
				'smartphone'  => 0,
			);
		}

		/**
		 * The screen size a width falls in.
		 *
		 * @param int $width Width in CSS pixels.
		 * @return string A bands() key.
		 */
		public static function band( $width ) {
			foreach ( self::bands() as $key => $from ) {
				if ( (int) $width >= $from ) {
					return $key;
				}
			}
			return 'smartphone';
		}

		/**
		 * The edges of ec-store.css's narrower cart rules ( max-width ), the w<edge> names the script gives a cart at or under them.
		 *
		 * @return array
		 */
		public static function edges() {
			return array( 989, 767, 699, 600, 599, 489, 480, 479 );
		}

		/**
		 * Columns per screen size ( Settings › Design › Cart & checkout look ): key => 1 | 2.
		 *
		 * @return array
		 */
		public static function columns() {
			$defaults = array(
				'desktop'     => 2,
				'laptop'      => 2,
				'tablet_wide' => 1,
				'tablet'      => 1,
				'smartphone'  => 1,
			);
			$columns  = array();
			foreach ( $defaults as $key => $default ) {
				$value           = get_option( 'ec_option_cart_columns_' . $key );
				$columns[ $key ] = ( '' === (string) $value || false === $value ) ? $default : ( ( 1 === (int) $value ) ? 1 : 2 );
			}
			return $columns;
		}

		/**
		 * Whether fitting a cart of this width on a screen of that width changes its layout: a narrower screen size, and either the
		 * narrow rules ( under 990px ) or other columns.
		 *
		 * @param int $width    The cart's width.
		 * @param int $viewport The screen's width.
		 * @return bool
		 */
		public static function changes( $width, $viewport ) {
			$width    = (int) $width;
			$viewport = (int) $viewport;
			if ( $width <= 0 || $viewport <= 0 ) {
				return false;
			}
			$cart   = self::band( $width );
			$screen = self::band( max( $width, $viewport ) );
			if ( $cart === $screen ) {
				return false;
			}
			$columns = self::columns();
			return ( $width < self::NARROW || $columns[ $cart ] !== $columns[ $screen ] );
		}

		/**
		 * Whether this request is the cart page ( the page the store's cart is on, or its translation ).
		 *
		 * @return bool
		 */
		public static function is_cart_page() {
			$cart = (int) get_option( 'ec_option_cartpage' );
			if ( $cart <= 0 || ! function_exists( 'get_queried_object_id' ) ) {
				return false;
			}
			$page = (int) get_queried_object_id();
			if ( $page === $cart ) {
				return true;
			}
			/* WPML / Polylang: the page translated from the cart page. */
			$translated = (int) apply_filters( 'wpml_object_id', $page, 'page', true, apply_filters( 'wpml_default_language', null ) );
			return ( $translated > 0 && $translated === $cart );
		}

		/**
		 * Whether the person viewing is a store manager.
		 *
		 * @return bool
		 */
		public static function manager() {
			return function_exists( 'is_user_logged_in' ) && is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) );
		}

		/**
		 * The script printed after the cart ( load_ec_cart() ), once a request: '' when the setting is off and nobody needs a measurement.
		 * It has no "less than" sign and no double ampersand: a block theme runs wptexturize() over the whole page.
		 *
		 * @return string
		 */
		public static function script() {
			if ( self::$printed ) {
				return '';
			}
			$fit    = self::enabled();
			$report = false;
			if ( self::manager() && self::is_cart_page() && ! isset( $_GET[ self::CHECK_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: the admin's own check asks for no report.
				$report = array(
					'url'   => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( self::NONCE ),
				);
			}
			if ( ! $fit && ! $report ) {
				return '';
			}
			self::$printed = true;
			$bands         = array();
			foreach ( self::bands() as $key => $from ) {
				$bands[] = array( $from, $key );
			}
			$config = array(
				'fit'    => $fit ? 1 : 0,
				'report' => $report ? $report : 0,
				'bands'  => $bands,
				'edges'  => self::edges(),
			);
			$js     = <<<'JS'
(function(w,d){
var c=w.wpeasycart_cart_fit_config=%CONFIG%;
if(w.wpeasycart_cart_fit){w.wpeasycart_cart_fit();return;}
var queued=0,seen=[],sent=0,said={};
var ro=w.ResizeObserver?new w.ResizeObserver(function(){queue();}):null;
function band(x){for(var i=0;c.bands.length>i;i++){if(x>=c.bands[i][0]){return c.bands[i][1];}}return 'smartphone';}
function names(x){var t=[band(x)];for(var i=0;c.edges.length>i;i++){if(c.edges[i]>=x){t.push('w'+c.edges[i]);}}return t.join(' ');}
function kind(el){var k=' '+el.className+' ';return -1!==k.indexOf(' ec_cart_subscription ')?'subscription':(-1!==k.indexOf(' ec_cart_onepage_page ')?'onepage':'classic');}
function report(el,x,vw){
if(!c.report){return;}
if(990>vw){return;}
var key=x+'x'+vw;
if(said[key]){return;}
if(sent>2){return;}
said[key]=1;sent++;
try{var r=new XMLHttpRequest();r.open('POST',c.report.url,true);r.setRequestHeader('Content-Type','application/x-www-form-urlencoded');r.send('action=ec_ajax_cart_fit_report&nonce='+encodeURIComponent(c.report.nonce)+'&width='+x+'&viewport='+vw+'&page='+kind(el));}catch(e){}
}
function fit(){
queued=0;
var list=d.getElementsByClassName('ec_cart_page'),vw=d.documentElement.clientWidth||w.innerWidth||0,first=1;
for(var i=0;list.length>i;i++){
var el=list[i];
if(el.closest?el.closest('.wpec-el-cart-page'):0){continue;}
var x=Math.round(el.getBoundingClientRect().width);
if(!x){continue;}
if(c.fit){var t=names(x);if(el.getAttribute('data-wpec-fit')!==t){el.setAttribute('data-wpec-fit',t);}}
if(ro){if(-1===seen.indexOf(el)){seen.push(el);ro.observe(el);}}
if(first){first=0;report(el,x,vw);}
}
}
function queue(){if(!queued){queued=1;(w.requestAnimationFrame||setTimeout)(fit);}}
w.wpeasycart_cart_fit=fit;
fit();
w.addEventListener('resize',queue);
w.addEventListener('load',queue);
if(w.MutationObserver){new w.MutationObserver(function(){var list=d.getElementsByClassName('ec_cart_page');for(var i=0;list.length>i;i++){if(-1===seen.indexOf(list[i])){queue();return;}}}).observe(d.documentElement,{childList:true,subtree:true});}
})(window,document);
JS;
			$json = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
			return '<script id="wpeasycart-cart-fit">' . str_replace( '%CONFIG%', $json, $js ) . '</script>';
		}

		/**
		 * The columns per screen size for a cart of that width ( head_content.php's own rules, keyed to the names the script gives ),
		 * printed after head_content.php's styles while the setting is on. A screen size set to one column stacks the order summary
		 * under the form; two columns add nothing ( a cart never gets more columns than the screen's own layout ).
		 */
		public static function head_css() {
			if ( ! self::enabled() ) {
				return;
			}
			$css = '';
			foreach ( self::columns() as $key => $count ) {
				if ( 'desktop' === $key || 1 !== $count ) {
					continue;
				}
				$at   = ':where(.ec_cart_page[data-wpec-fit~="' . $key . '"]) ';
				$css .= $at . '.ec_cart_breadcrumb.ec_inactive, ' . $at . '.ec_cart_breadcrumb_divider{ display:none; }';
				$css .= $at . '.ec_cart_breadcrumb{ width:100%; text-align:center; font-size:22px; }';
				$css .= $at . '.ec_cart_left{ width:100%; padding-right:0px; border-right:0px; }';
				$css .= $at . '.ec_cart_right{ width:100%; padding-left:0px; }';
				$css .= $at . '.ec_cart_right > .ec_cart_header.ec_top{ margin-top:15px; }';
				$css .= $at . '.ec_show_two_column_only{ display:none !important; }';
				$css .= $at . '#ec_cart_payment_one_column{ display:block; }';
				$css .= $at . '#ec_cart_payment_hide_column{ display:none; }';
			}
			if ( '' !== $css ) {
				echo '<style id="wpeasycart-cart-fit-css">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed CSS built from the bands() keys.
			}
		}

		/**
		 * The latest measurement, for the active theme only.
		 *
		 * @return array|null theme, name, width, viewport, page, source, at, fail.
		 */
		public static function check() {
			$check = get_option( self::CHECK_OPTION );
			if ( ! is_array( $check ) || empty( $check['theme'] ) || get_stylesheet() !== $check['theme'] ) {
				return null;
			}
			return wp_parse_args(
				$check,
				array(
					'width'    => 0,
					'viewport' => 0,
					'page'     => '',
					'source'   => '',
					'at'       => 0,
					'fail'     => '',
				)
			);
		}

		/**
		 * Keeps a measurement of the cart page for the active theme. A store manager's visits write at most once an hour unless the
		 * width moved; the admin's check always writes.
		 *
		 * @param int    $width    The cart's width ( 0 with a failure ).
		 * @param int    $viewport The screen's width.
		 * @param string $page     classic | onepage | subscription | elementor.
		 * @param string $source   visit | check.
		 * @param string $fail     '' | blocked ( the cart page could not be opened in a frame ) | none ( no cart on it ) | page ( no cart page ).
		 * @return array The measurement kept.
		 */
		public static function record( $width, $viewport, $page = 'classic', $source = 'visit', $fail = '' ) {
			$width    = max( 0, min( 10000, (int) $width ) );
			$viewport = max( 0, min( 10000, (int) $viewport ) );
			$page     = in_array( $page, array( 'classic', 'onepage', 'subscription', 'elementor' ), true ) ? $page : 'classic';
			$fail     = in_array( $fail, array( 'blocked', 'none', 'page' ), true ) ? $fail : '';
			$old      = self::check();
			$new      = array(
				'theme'    => get_stylesheet(),
				'width'    => $width,
				'viewport' => $viewport,
				'page'     => $page,
				'source'   => ( 'check' === $source ) ? 'check' : 'visit',
				'at'       => time(),
				'fail'     => $fail,
			);
			if ( 'visit' === $new['source'] && $old && '' === $old['fail'] && abs( (int) $old['width'] - $width ) <= 8 && abs( (int) $old['viewport'] - $viewport ) <= 8 && ( time() - (int) $old['at'] ) < HOUR_IN_SECONDS ) {
				return $old;
			}
			update_option( self::CHECK_OPTION, $new, false );
			/**
			 * A measurement of the cart page was kept.
			 *
			 * @since 6.0.5
			 * @param array $new The measurement.
			 */
			do_action( 'wp_easycart_cart_fit_measured', $new );
			return $new;
		}

		/**
		 * Whether to recommend the setting: off, the active theme measured, and the cart narrower than the screen's layout needs.
		 *
		 * @param bool $dismissed Count a recommendation put away ( Not now ) as none.
		 * @return bool
		 */
		public static function recommended( $dismissed = true ) {
			if ( self::enabled() ) {
				return false;
			}
			$check = self::check();
			if ( ! $check || '' !== $check['fail'] || 'elementor' === $check['page'] || (int) $check['viewport'] < self::NARROW ) {
				return false;
			}
			if ( $dismissed && get_stylesheet() === get_option( self::DISMISS_OPTION, '' ) ) {
				return false;
			}
			return self::changes( (int) $check['width'], (int) $check['viewport'] );
		}

		/**
		 * AJAX ec_ajax_cart_fit_report ( signed-in store managers only ): the script's measurement of the cart page.
		 */
		public static function ajax_report() {
			wp_easycart_cart_fit_guard();
			$width    = isset( $_POST['width'] ) ? (int) $_POST['width'] : 0;
			$viewport = isset( $_POST['viewport'] ) ? (int) $_POST['viewport'] : 0;
			$page     = isset( $_POST['page'] ) ? sanitize_key( wp_unslash( $_POST['page'] ) ) : 'classic';
			if ( $width <= 0 || $viewport < self::NARROW ) {
				wp_send_json_success( array( 'kept' => false ) );
			}
			self::record( $width, $viewport, $page, 'visit' );
			wp_send_json_success( array( 'kept' => true ) );
		}
	}

	wp_easycart_cart_fit::init();

endif;

if ( ! function_exists( 'wp_easycart_cart_fit_guard' ) ) {
	/**
	 * The store manager's measurement report: the nonce the cart page printed for them and a store manager's capability.
	 */
	function wp_easycart_cart_fit_guard() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) {
			wp_send_json_error( array( 'message' => 'denied' ), 403 );
		}
		check_ajax_referer( wp_easycart_cart_fit::NONCE, 'nonce' );
	}
}
