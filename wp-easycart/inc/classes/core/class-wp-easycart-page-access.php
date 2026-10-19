<?php
/**
 * WP EasyCart Limit Access: who may open a page, and where everyone else goes ( 6.0.5 ).
 *
 * One rule per post, read here and nowhere else. A rule saved by 6.0.5 lives in the post meta wpeasycart_access; a page set up
 * before keeps its six wpeasycart_restrict_* keys, read as the same rule they always were ( every restriction must pass, the
 * three addresses, limited only while an address is set: legacy() ). Plan, findings F1–F14 and decisions D1–D14:
 * https://claude.ai/artifact/XjaV96DLMp5nkVg4nDG5QN
 *
 *   rule()      the rule of a post ( clean(), or legacy() ), filter wp_easycart_page_access_rule.
 *   decide()    for this visitor: open | staff | member | signed_out | no_access. Staff are everyone who can edit the post, store
 *               administrators and store managers, so page builder previews always load ( D6 ).
 *   guard()     on template_redirect: limited pages are never cached; a visitor without access goes to the rule's destination,
 *               checked first so nobody is sent to the page itself, to another limited page they can't open, or round in circles
 *               ( safe_url(), the hop cookie ); else to the store's own sign-in or members-only view on the account page, which is
 *               never limited ( exempt() ).
 *   Content outside the page view ( D7 ): REST, feeds, search, embeds and lists show a short members-only line instead of the
 *   content to anyone who could not open the page ( filter_content(), filter_excerpt() ).
 *   Sign in, then back ( D2 ): the sign-in link carries wpec_return and a short cookie; ec_accountpage's sign-in and new account
 *   handlers send the customer back ( take_return() ).
 *   Checkout ( D10 ): a cart holding a product that unlocks a page needs an account, as downloads do ( cart_unlocks_page() ).
 *
 * @package wp-easycart
 * @since 6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_page_access' ) ) :

	/**
	 * Limit Access rules.
	 */
	final class wp_easycart_page_access {

		/** Post meta: the rule saved by 6.0.5 and later. */
		const META = 'wpeasycart_access';

		/** Post meta from before 6.0.5 ( still written on every save, so going back to 6.0.4 keeps pages limited ). */
		const LEGACY_PRODUCTS   = 'wpeasycart_restrict_product_id';
		const LEGACY_USERS      = 'wpeasycart_restrict_user_id';
		const LEGACY_ROLES      = 'wpeasycart_restrict_role_id';
		const LEGACY_SIGNED_OUT = 'wpeasycart_restrict_redirect_url';
		const LEGACY_MEMBERS    = 'wpeasycart_restrict_redirect_url_auth';
		const LEGACY_NO_ACCESS  = 'wpeasycart_restrict_redirect_url_not_auth';

		/** Query argument on the sign-in link: the page to return to. */
		const RETURN_PARAM = 'wpec_return';

		/** Cookie that carries the page to return to through sign-in or a new account ( 30 minutes ). */
		const RETURN_COOKIE = 'wpec_access_return';

		/** Cookie that counts redirects made in the last seconds, so nothing bounces forever. */
		const HOP_COOKIE = 'wpec_access_hops';

		/** Redirects in a row before the store's own view takes over. */
		const MAX_HOPS = 3;

		/** Account page view: what unlocks a page ( ec_page=members_only&wpec_page=<id> ). */
		const VIEW       = 'members_only';
		const PAGE_PARAM = 'wpec_page';

		/** Option: the products that unlock a page ( rebuilt when a rule or a limited post changes ). */
		const PRODUCTS_OPTION = 'wp_easycart_page_access_products';

		/** Option: '1' pauses every limit ( Settings, D11 ). */
		const PAUSE_OPTION = 'ec_option_page_access_paused';

		/**
		 * Rules read this request, post id => rule.
		 *
		 * @var array
		 */
		private static $rules = array();

		/**
		 * Decisions this request, post id => decision.
		 *
		 * @var array
		 */
		private static $decisions = array();

		/**
		 * Membership answers this request.
		 *
		 * @var array
		 */
		private static $members = array();

		/**
		 * Pages each customer can open this request ( pages_for() ).
		 *
		 * @var array
		 */
		private static $pages = array();

		/**
		 * Hooks added.
		 *
		 * @var bool
		 */
		private static $started = false;

		/**
		 * The visitor a staff preview stands for ( preview() ): post, state signed_out | no_access | member; null otherwise.
		 *
		 * @var array|null
		 */
		private static $as = null;

		/** Query argument: staff preview a limited page as signed_out | no_access | member. */
		const PREVIEW_PARAM = 'wpec_access_preview';

		/**
		 * Hooks.
		 */
		public static function init() {
			if ( self::$started ) {
				return;
			}
			self::$started = true;
			add_action( 'template_redirect', array( __CLASS__, 'account_request' ), 2 );
			add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 999 );
			add_filter( 'get_the_excerpt', array( __CLASS__, 'filter_excerpt' ), 999, 2 );
			foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
				add_action( $hook, array( __CLASS__, 'meta_changed' ), 10, 3 );
			}
			add_action( 'transition_post_status', array( __CLASS__, 'post_status_changed' ), 10, 3 );
			add_action( 'deleted_post', array( __CLASS__, 'forget_products' ) );
			add_action( 'admin_bar_menu', array( __CLASS__, 'staff_bar' ), 90 );
			add_action( 'wp_footer', array( __CLASS__, 'staff_footer' ), 50 );
			add_action( 'wpeasycart_dashboard_top', array( __CLASS__, 'account_pages' ), 20 );
			add_action( 'wpeasycart_success_page_content_middle', array( __CLASS__, 'order_pages' ), 18, 2 );
		}

		/* ------------------------------------------------------------------ The rule */

		/**
		 * An empty rule ( everything a rule has ).
		 *
		 * @return array
		 */
		public static function defaults() {
			return array(
				'version'     => 1,
				'on'          => false,
				'match'       => 'any',
				'signed_in'   => false,
				'products'    => array(),
				'roles'       => array(),
				'users'       => array(),
				'signed_out'  => array(
					'to'   => 'sign_in',
					'page' => 0,
					'url'  => '',
				),
				'no_access'   => array(
					'to'   => 'message',
					'page' => 0,
					'url'  => '',
				),
				'members'     => array(
					'to'   => 'stay',
					'page' => 0,
					'url'  => '',
				),
				'external_ok' => false,
				'legacy'      => false,
			);
		}

		/**
		 * Where each kind of destination may send people.
		 *
		 * @return array signed_out | no_access | members => allowed "to" values.
		 */
		public static function destinations() {
			return array(
				'signed_out' => array( 'sign_in', 'page', 'url' ),
				'no_access'  => array( 'message', 'product', 'store', 'page', 'url' ),
				'members'    => array( 'stay', 'page', 'url' ),
			);
		}

		/**
		 * The rule of a post.
		 *
		 * @param int $post_id Post.
		 * @return array See defaults().
		 */
		public static function rule( $post_id ) {
			$post_id = (int) $post_id;
			if ( isset( self::$rules[ $post_id ] ) ) {
				return self::$rules[ $post_id ];
			}
			$stored = ( $post_id > 0 ) ? get_post_meta( $post_id, self::META, true ) : '';
			$rule   = ( is_array( $stored ) && ! empty( $stored['version'] ) ) ? self::clean( $stored ) : self::legacy( $post_id );
			/**
			 * Filters a post's Limit Access rule.
			 *
			 * @since 6.0.5
			 * @param array $rule    See wp_easycart_page_access::defaults().
			 * @param int   $post_id Post.
			 */
			$rule                    = apply_filters( 'wp_easycart_page_access_rule', $rule, $post_id );
			self::$rules[ $post_id ] = is_array( $rule ) ? array_merge( self::defaults(), $rule ) : self::defaults();
			return self::$rules[ $post_id ];
		}

		/**
		 * A stored or posted rule with every value checked.
		 *
		 * @param array $raw Rule.
		 * @return array
		 */
		public static function clean( $raw ) {
			$raw                 = is_array( $raw ) ? $raw : array();
			$rule                = self::defaults();
			$rule['on']          = ! empty( $raw['on'] );
			$rule['match']       = ( isset( $raw['match'] ) && 'all' === $raw['match'] ) ? 'all' : 'any';
			$rule['signed_in']   = ! empty( $raw['signed_in'] );
			$rule['products']    = self::ids( isset( $raw['products'] ) ? $raw['products'] : array() );
			$rule['users']       = self::ids( isset( $raw['users'] ) ? $raw['users'] : array() );
			$rule['roles']       = self::labels( isset( $raw['roles'] ) ? $raw['roles'] : array() );
			$rule['external_ok'] = ! empty( $raw['external_ok'] );
			foreach ( self::destinations() as $which => $allowed ) {
				$given          = ( isset( $raw[ $which ] ) && is_array( $raw[ $which ] ) ) ? $raw[ $which ] : array();
				$to             = ( isset( $given['to'] ) && in_array( (string) $given['to'], $allowed, true ) ) ? (string) $given['to'] : $rule[ $which ]['to'];
				$rule[ $which ] = array(
					'to'   => $to,
					'page' => isset( $given['page'] ) ? max( 0, (int) $given['page'] ) : 0,
					'url'  => isset( $given['url'] ) && is_string( $given['url'] ) ? self::clean_url( $given['url'] ) : '',
				);
			}
			return $rule;
		}

		/**
		 * The rule of a page set up before 6.0.5, exactly as 6.0.4 applied it: every restriction must pass, limited only while one of
		 * the three addresses is set ( or the filter wp_easycart_restrict_access_without_redirect says so ), a signed-out visitor goes
		 * to the first address else to sign in, a customer without access to the third, else the first, else the store page, and a
		 * member to the second when it is set.
		 *
		 * @param int $post_id Post.
		 * @return array
		 */
		public static function legacy( $post_id ) {
			$rule                = self::defaults();
			$rule['legacy']      = true;
			$rule['match']       = 'all';
			$rule['external_ok'] = true;
			if ( $post_id <= 0 ) {
				return $rule;
			}
			$rule['products'] = self::ids( get_post_meta( $post_id, self::LEGACY_PRODUCTS, true ) );
			$rule['users']    = self::ids( get_post_meta( $post_id, self::LEGACY_USERS, true ) );
			$rule['roles']    = self::labels( get_post_meta( $post_id, self::LEGACY_ROLES, true ), false );
			$signed_out       = trim( (string) get_post_meta( $post_id, self::LEGACY_SIGNED_OUT, true ) );
			$members          = trim( (string) get_post_meta( $post_id, self::LEGACY_MEMBERS, true ) );
			$no_access        = trim( (string) get_post_meta( $post_id, self::LEGACY_NO_ACCESS, true ) );
			$has_address      = ( '' !== $signed_out || '' !== $members || '' !== $no_access );
			if ( self::restricted( $rule ) ) {
				$rule['on'] = $has_address || (bool) apply_filters( 'wp_easycart_restrict_access_without_redirect', false, $post_id );
			}
			if ( '' !== $signed_out ) {
				$rule['signed_out'] = array(
					'to'   => 'url',
					'page' => 0,
					'url'  => $signed_out,
				);
			}
			if ( '' !== $no_access ) {
				$rule['no_access'] = array(
					'to'   => 'url',
					'page' => 0,
					'url'  => $no_access,
				);
			} elseif ( '' !== $signed_out ) {
				$rule['no_access'] = array(
					'to'   => 'url',
					'page' => 0,
					'url'  => $signed_out,
				);
			} else {
				$rule['no_access'] = array(
					'to'   => 'store',
					'page' => 0,
					'url'  => '',
				);
			}
			if ( '' !== $members ) {
				$rule['members'] = array(
					'to'   => 'url',
					'page' => 0,
					'url'  => $members,
				);
			}
			return $rule;
		}

		/**
		 * Whether a rule names anyone ( a rule with no restriction limits nothing ).
		 *
		 * @param array $rule Rule.
		 * @return bool
		 */
		public static function restricted( $rule ) {
			return ! empty( $rule['signed_in'] ) || ! empty( $rule['products'] ) || ! empty( $rule['roles'] ) || ! empty( $rule['users'] );
		}

		/**
		 * Whether a post is limited right now.
		 *
		 * @param int        $post_id Post.
		 * @param array|null $rule    Its rule, when already read.
		 * @return bool
		 */
		public static function active( $post_id, $rule = null ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || self::paused() || self::exempt( $post_id ) ) {
				return false;
			}
			$rule = is_array( $rule ) ? $rule : self::rule( $post_id );
			return ! empty( $rule['on'] ) && self::restricted( $rule );
		}

		/**
		 * Every limit is paused ( Settings, for troubleshooting ).
		 *
		 * @return bool
		 */
		public static function paused() {
			return '1' === (string) get_option( self::PAUSE_OPTION, '0' );
		}

		/**
		 * The account and cart pages are never limited: signing in and buying always stay open ( D9 ).
		 *
		 * @param int $post_id Post.
		 * @return bool
		 */
		public static function exempt( $post_id ) {
			$post_id = (int) $post_id;
			$exempt  = in_array( $post_id, self::store_page_ids( array( 'ec_option_accountpage', 'ec_option_cartpage' ) ), true );
			/**
			 * Filters whether a post can never be limited.
			 *
			 * @since 6.0.5
			 * @param bool $exempt  Exempt.
			 * @param int  $post_id Post.
			 */
			return (bool) apply_filters( 'wp_easycart_page_access_exempt', $exempt, $post_id );
		}

		/**
		 * The ids of store pages ( and their WPML / Polylang translations ).
		 *
		 * @param array $options Option names.
		 * @return int[]
		 */
		private static function store_page_ids( $options ) {
			$ids = array();
			foreach ( $options as $option ) {
				$id = (int) get_option( $option );
				if ( $id <= 0 ) {
					continue;
				}
				$ids[] = $id;
				if ( function_exists( 'icl_object_id' ) ) {
					$translated = (int) icl_object_id( $id, 'page', true );
					if ( $translated > 0 ) {
						$ids[] = $translated;
					}
				}
				if ( function_exists( 'pll_get_post_translations' ) ) {
					foreach ( (array) pll_get_post_translations( $id ) as $translated ) {
						$ids[] = (int) $translated;
					}
				}
			}
			return array_values( array_unique( $ids ) );
		}

		/* ------------------------------------------------------------------ Who is in */

		/**
		 * Staff open every limited page: anyone who can edit it, store administrators and store managers ( D6 ). Page builders preview
		 * a page as the person editing it, so their previews always load.
		 *
		 * @param int $post_id Post.
		 * @return bool
		 */
		public static function is_staff( $post_id ) {
			$staff = current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) || ( (int) $post_id > 0 && current_user_can( 'edit_post', (int) $post_id ) );
			/**
			 * Filters whether the current user is staff for a limited page ( sees it, is never sent away ).
			 *
			 * @since 6.0.5
			 * @param bool $staff   Staff.
			 * @param int  $post_id Post.
			 */
			return (bool) apply_filters( 'wp_easycart_page_access_staff', $staff, (int) $post_id );
		}

		/**
		 * The store account of this visit: id and role ( user_level ).
		 *
		 * @return array { 0: int, 1: string }
		 */
		private static function visitor() {
			$user = ( isset( $GLOBALS['ec_user'] ) && is_object( $GLOBALS['ec_user'] ) ) ? $GLOBALS['ec_user'] : null;
			$id   = $user ? (int) $user->user_id : 0;
			$role = ( $user && isset( $user->user_level ) ) ? (string) $user->user_level : '';
			return array( $id, $role );
		}

		/**
		 * Whether a signed-in customer passes a rule: any one of its restrictions, or all of them ( a page set up before 6.0.5 ).
		 *
		 * @param array  $rule    Rule.
		 * @param int    $user_id ec_user id.
		 * @param string $role    The customer's role ( user_level ).
		 * @return bool
		 */
		public static function passes( $rule, $user_id, $role ) {
			$user_id = (int) $user_id;
			if ( $user_id <= 0 || ! self::restricted( $rule ) ) {
				return false;
			}
			$all = ( 'all' === $rule['match'] );
			/* Cheap checks first; the purchase check reads orders and subscriptions, so it runs only when it decides something. */
			$checks = array();
			if ( ! empty( $rule['signed_in'] ) ) {
				$checks[] = true;
			}
			if ( ! empty( $rule['users'] ) ) {
				$checks[] = in_array( $user_id, $rule['users'], true );
			}
			if ( ! empty( $rule['roles'] ) ) {
				$checks[] = in_array( (string) $role, $rule['roles'], true );
			}
			if ( $all && in_array( false, $checks, true ) ) {
				return false;
			}
			if ( ! $all && in_array( true, $checks, true ) ) {
				return true;
			}
			if ( ! empty( $rule['products'] ) ) {
				return self::has_products( $user_id, $rule['products'] );
			}
			return $all ? ! in_array( false, $checks, true ) : false;
		}

		/**
		 * The membership rule shared with [ec_membership]: a subscription while it runs, an installment plan once paid in full, any
		 * other product once an order holding it is paid.
		 *
		 * @param int   $user_id     ec_user id.
		 * @param int[] $product_ids Products.
		 * @return bool
		 */
		private static function has_products( $user_id, $product_ids ) {
			$key = (int) $user_id . ':' . implode( ',', $product_ids );
			if ( ! isset( self::$members[ $key ] ) ) {
				self::$members[ $key ] = function_exists( 'wp_easycart_user_has_membership' ) ? (bool) wp_easycart_user_has_membership( (int) $user_id, $product_ids ) : false;
			}
			return self::$members[ $key ];
		}

		/**
		 * What this visitor gets on a post.
		 *
		 * @param int $post_id Post.
		 * @return array allowed ( bool ), state: open | staff | member | signed_out | no_access.
		 */
		public static function decide( $post_id ) {
			$post_id = (int) $post_id;
			if ( isset( self::$decisions[ $post_id ] ) ) {
				return self::$decisions[ $post_id ];
			}
			$rule = self::rule( $post_id );
			if ( ! self::active( $post_id, $rule ) ) {
				$decision = array(
					'allowed' => true,
					'state'   => 'open',
				);
			} elseif ( null !== self::$as ) {
				/* Preview as ( preview() ): the previewed post opens for a member only; every other limited post stays shut. */
				$member   = ( 'member' === self::$as['state'] && self::$as['post'] === $post_id );
				$decision = array(
					'allowed' => $member,
					'state'   => $member ? 'member' : ( 'signed_out' === self::$as['state'] ? 'signed_out' : 'no_access' ),
				);
			} elseif ( self::is_staff( $post_id ) ) {
				$decision = array(
					'allowed' => true,
					'state'   => 'staff',
				);
			} else {
				list( $user_id, $role ) = self::visitor();
				if ( $user_id <= 0 ) {
					$decision = array(
						'allowed' => false,
						'state'   => 'signed_out',
					);
				} elseif ( self::passes( $rule, $user_id, $role ) ) {
					$decision = array(
						'allowed' => true,
						'state'   => 'member',
					);
				} else {
					$decision = array(
						'allowed' => false,
						'state'   => 'no_access',
					);
				}
			}
			/**
			 * Filters what a visitor gets on a limited post.
			 *
			 * @since 6.0.5
			 * @param array $decision allowed, state.
			 * @param int   $post_id  Post.
			 * @param array $rule     Rule.
			 */
			$decision                    = apply_filters( 'wp_easycart_page_access_decision', $decision, $post_id, $rule );
			self::$decisions[ $post_id ] = $decision;
			return $decision;
		}

		/**
		 * Whether this visitor may see a post's content.
		 *
		 * @param int $post_id Post.
		 * @return bool
		 */
		public static function allowed( $post_id ) {
			$decision = self::decide( $post_id );
			return ! empty( $decision['allowed'] );
		}

		/**
		 * Forget what this request read ( after a rule changes, and in tests ).
		 */
		public static function reset() {
			self::$rules     = array();
			self::$decisions = array();
			self::$members   = array();
			self::$pages     = array();
		}

		/* ------------------------------------------------------------------ The page itself */

		/**
		 * On template_redirect ( through wp_easycart_restrict_access() ): a limited page is never cached; a visitor who may not see it
		 * goes where its rule says, when that is safe, else to the store's own sign-in or members-only view.
		 */
		public static function guard() {
			if ( ! is_singular() ) {
				return; /* a lock protects its own page; lists and archives get the members-only line ( filter_content() ) */
			}
			$post_id = (int) get_queried_object_id();
			if ( ! self::active( $post_id ) ) {
				return;
			}
			/* What shows depends on who is looking, so no page cache or CDN may keep the page or its redirect. */
			if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				wp_easycart_product_details_no_cache();
			}
			if ( ! headers_sent() ) {
				nocache_headers();
			}
			$destination = self::destination( $post_id );
			if ( $destination['allowed'] && '' === $destination['url'] ) {
				self::clear_hops(); /* the page draws: no loop */
				return;
			}
			if ( 'signed_out' === $destination['state'] && self::is_sign_in_url( $destination['url'] ) ) {
				self::remember_return( $post_id );
			}
			self::redirect( $destination['url'] );
		}

		/**
		 * What this visit to a post gets: the page ( url '' and allowed ), or the address the visitor is sent to ( a member's own page
		 * when the rule sends members on; '' while not allowed = nowhere safe, a plain "no access" page ).
		 *
		 * @param int $post_id Post.
		 * @return array allowed, state ( see decide() ), url.
		 */
		public static function destination( $post_id ) {
			$post_id  = (int) $post_id;
			$rule     = self::rule( $post_id );
			$decision = self::decide( $post_id );
			$out      = array(
				'allowed' => ! empty( $decision['allowed'] ),
				'state'   => isset( $decision['state'] ) ? (string) $decision['state'] : 'open',
				'url'     => '',
			);
			if ( 'open' === $out['state'] ) {
				return $out;
			}
			if ( $out['allowed'] ) {
				if ( 'member' === $out['state'] && 'stay' !== $rule['members']['to'] ) {
					$out['url'] = self::safe_url( self::target_url( $rule['members'], $post_id, $rule ), $post_id, $rule );
				}
				return $out;
			}
			$which      = ( 'signed_out' === $out['state'] ) ? 'signed_out' : 'no_access';
			$url        = self::safe_url( self::target_url( $rule[ $which ], $post_id, $rule ), $post_id, $rule );
			$out['url'] = ( '' !== $url ) ? $url : self::fallback_url( $which, $post_id );
			return $out;
		}

		/**
		 * The address a destination stands for ( '' when it has none or its page is gone ).
		 *
		 * @param array $target  to, page, url.
		 * @param int   $post_id The limited post.
		 * @param array $rule    Its rule.
		 * @return string
		 */
		public static function target_url( $target, $post_id, $rule ) {
			$to = isset( $target['to'] ) ? (string) $target['to'] : '';
			switch ( $to ) {
				case 'sign_in':
					return self::sign_in_url( $post_id );
				case 'message':
					return self::message_url( $post_id );
				case 'store':
					return self::store_url();
				case 'product':
					if ( ! empty( $rule['products'] ) && class_exists( 'wp_easycart_storefront_access' ) ) {
						return (string) wp_easycart_storefront_access::product_url( (int) $rule['products'][0] );
					}
					return self::message_url( $post_id );
				case 'page':
					$page = isset( $target['page'] ) ? (int) $target['page'] : 0;
					if ( $page > 0 && 'publish' === get_post_status( $page ) ) {
						$link = get_permalink( $page );
						return $link ? (string) $link : '';
					}
					return '';
				case 'url':
					return self::absolute( isset( $target['url'] ) ? (string) $target['url'] : '' );
			}
			return '';
		}

		/**
		 * The store's sign-in, coming back to a page afterwards ( D2 ).
		 *
		 * @param int $post_id Page to return to.
		 * @return string
		 */
		public static function sign_in_url( $post_id ) {
			$login = function_exists( 'wpeasycart_links' ) ? (string) wpeasycart_links()->get_account_page( 'login' ) : '';
			if ( '' === $login ) {
				return '';
			}
			return add_query_arg( self::RETURN_PARAM, (int) $post_id, $login );
		}

		/**
		 * The account page's members-only view of a page ( D3 ).
		 *
		 * @param int $post_id Limited post.
		 * @return string
		 */
		public static function message_url( $post_id ) {
			$account = self::account_url();
			if ( '' === $account ) {
				return '';
			}
			return add_query_arg(
				array(
					'ec_page'        => self::VIEW,
					self::PAGE_PARAM => (int) $post_id,
				),
				$account
			);
		}

		/**
		 * The account page's address.
		 *
		 * @return string
		 */
		private static function account_url() {
			$id = (int) get_option( 'ec_option_accountpage' );
			if ( $id > 0 && function_exists( 'icl_object_id' ) ) {
				$id = (int) icl_object_id( $id, 'page', true );
			}
			$link = ( $id > 0 ) ? get_permalink( $id ) : '';
			return $link ? (string) apply_filters( 'wp_easycart_account_page_url', (string) $link ) : '';
		}

		/**
		 * The store page's address, else the home page.
		 *
		 * @return string
		 */
		private static function store_url() {
			$id   = (int) get_option( 'ec_option_storepage' );
			$link = ( $id > 0 ) ? get_permalink( $id ) : '';
			return $link ? (string) $link : home_url( '/' );
		}

		/**
		 * Where a visitor goes when the rule's own destination is not safe: the store's sign-in or members-only view ( the account
		 * page is never limited ), else the home page.
		 *
		 * @param string $which   signed_out | no_access.
		 * @param int    $post_id Limited post.
		 * @return string
		 */
		private static function fallback_url( $which, $post_id ) {
			$url = ( 'signed_out' === $which ) ? self::sign_in_url( $post_id ) : self::message_url( $post_id );
			if ( '' !== $url && ! self::same_address( $url, self::current_url() ) ) {
				return $url;
			}
			$home = home_url( '/' );
			return self::same_address( $home, self::current_url() ) ? '' : $home;
		}

		/**
		 * A destination that is safe to send this visitor to, or '' ( D9 ): never the address they are on or the page itself, never a
		 * limited page of this site they could not open either, no further once redirects keep coming back ( the hop cookie ), and
		 * another site only for a page set up before 6.0.5 or a rule that allows it.
		 *
		 * @param string $url     Destination.
		 * @param int    $post_id Limited post.
		 * @param array  $rule    Its rule.
		 * @return string
		 */
		public static function safe_url( $url, $post_id, $rule ) {
			$url = (string) $url;
			if ( '' === $url || self::hops() >= self::MAX_HOPS ) {
				return '';
			}
			if ( self::same_address( $url, self::current_url() ) || self::same_address( $url, (string) get_permalink( $post_id ) ) ) {
				return '';
			}
			if ( self::is_local( $url ) ) {
				$target_id = function_exists( 'url_to_postid' ) ? (int) url_to_postid( $url ) : 0;
				if ( $target_id > 0 && $target_id !== (int) $post_id && self::active( $target_id ) && ! self::allowed( $target_id ) ) {
					return '';
				}
				return $url;
			}
			return ( ! empty( $rule['legacy'] ) || ! empty( $rule['external_ok'] ) ) ? $url : '';
		}

		/**
		 * Redirect and stop. A redirect counts toward the hop cookie, so a loop through other plugins' redirects ends at the store's own
		 * view. Nothing safe to go to: a plain "no access" page.
		 *
		 * @param string $url Destination.
		 */
		private static function redirect( $url ) {
			if ( '' === (string) $url ) {
				wp_die( esc_html( self::text( 'page_access_excerpt', 'This content is for members.' ) ), '', array( 'response' => 403 ) );
			}
			self::count_hop();
			if ( self::is_local( $url ) ) {
				wp_safe_redirect( $url );
			} else {
				wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the store's own address from the page's Limit Access rule ( protected meta ), allowed off-site only by its rule.
			}
			exit;
		}

		/**
		 * Redirects counted in the last 20 seconds.
		 *
		 * @return int
		 */
		private static function hops() {
			return isset( $_COOKIE[ self::HOP_COOKIE ] ) ? absint( $_COOKIE[ self::HOP_COOKIE ] ) : 0;
		}

		/**
		 * Count a redirect.
		 */
		private static function count_hop() {
			if ( ! headers_sent() ) {
				setcookie( self::HOP_COOKIE, (string) ( self::hops() + 1 ), time() + 20, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			}
		}

		/**
		 * A page drew, so the redirects before it ended: start counting again.
		 */
		private static function clear_hops() {
			if ( self::hops() > 0 && ! headers_sent() ) {
				setcookie( self::HOP_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			}
			unset( $_COOKIE[ self::HOP_COOKIE ] );
		}

		/**
		 * A typed address made whole: https:// and http:// as given, a path on this site from the home page, nothing else.
		 *
		 * @param string $url Address.
		 * @return string
		 */
		public static function absolute( $url ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				return '';
			}
			if ( '/' === substr( $url, 0, 1 ) && '//' !== substr( $url, 0, 2 ) ) {
				return home_url( $url );
			}
			if ( preg_match( '#^https?://[^/\s]+#i', $url ) ) {
				return $url;
			}
			return '';
		}

		/**
		 * A typed address as it is kept ( https:// and http:// addresses, or a path on this site ).
		 *
		 * @param string $url Address.
		 * @return string
		 */
		public static function clean_url( $url ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				return '';
			}
			if ( '/' === substr( $url, 0, 1 ) && '//' !== substr( $url, 0, 2 ) ) {
				/* a path stays a path ( the site may move ), cleaned as part of a whole address */
				$base  = 'https://wpec.invalid';
				$clean = esc_url_raw( $base . $url, array( 'https' ) );
				return ( 0 === strpos( $clean, $base . '/' ) ) ? substr( $clean, strlen( $base ) ) : '';
			}
			return preg_match( '#^https?://[^/\s]+#i', $url ) ? esc_url_raw( $url, array( 'http', 'https' ) ) : '';
		}

		/**
		 * Whether an address is on this site.
		 *
		 * @param string $url Address.
		 * @return bool
		 */
		public static function is_local( $url ) {
			$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
			$home = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
			return '' !== $host && ( $host === $home || preg_replace( '/^www\./', '', $host ) === preg_replace( '/^www\./', '', $home ) );
		}

		/**
		 * Whether two addresses are the same place ( scheme, www., a trailing slash and our own arguments aside ).
		 *
		 * @param string $a Address.
		 * @param string $b Address.
		 * @return bool
		 */
		public static function same_address( $a, $b ) {
			$a = self::address_key( $a );
			return '' !== $a && self::address_key( $b ) === $a;
		}

		/**
		 * Host, path and query of an address, compared loosely.
		 *
		 * @param string $url Address.
		 * @return string
		 */
		private static function address_key( $url ) {
			$url = (string) $url;
			if ( '' === $url ) {
				return '';
			}
			$parts = wp_parse_url( $url );
			if ( empty( $parts['host'] ) ) {
				return '';
			}
			$query = array();
			if ( ! empty( $parts['query'] ) ) {
				wp_parse_str( $parts['query'], $query );
				unset( $query[ self::RETURN_PARAM ] );
				ksort( $query );
			}
			$host = preg_replace( '/^www\./', '', strtolower( $parts['host'] ) );
			$path = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';
			return $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' ) . $path . ( $query ? '?' . http_build_query( $query ) : '' );
		}

		/**
		 * The address of this request.
		 *
		 * @return string
		 */
		private static function current_url() {
			$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, never printed or stored.
			$home = wp_parse_url( home_url( '/' ) );
			if ( empty( $home['host'] ) ) {
				return '';
			}
			/* REQUEST_URI holds the whole path, a site in a folder included */
			return ( isset( $home['scheme'] ) ? $home['scheme'] : 'http' ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . (int) $home['port'] : '' ) . $uri;
		}

		/**
		 * Whether an address is the store's sign-in ( with a page to return to ).
		 *
		 * @param string $url Address.
		 * @return bool
		 */
		private static function is_sign_in_url( $url ) {
			$query = (string) wp_parse_url( (string) $url, PHP_URL_QUERY );
			return '' !== $query && false !== strpos( $query, self::RETURN_PARAM . '=' );
		}

		/* ------------------------------------------------------------------ Sign in, then back */

		/**
		 * Remember a page to return to after signing in or making an account ( 30 minutes ).
		 *
		 * @param int $post_id Page.
		 */
		public static function remember_return( $post_id ) {
			if ( (int) $post_id <= 0 ) {
				return;
			}
			if ( ! headers_sent() ) {
				setcookie( self::RETURN_COOKIE, (string) (int) $post_id, time() + 30 * MINUTE_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			}
			$_COOKIE[ self::RETURN_COOKIE ] = (string) (int) $post_id;
		}

		/**
		 * The page to return to after signing in, once ( '' when none ): called by ec_accountpage's sign-in and new account handlers.
		 *
		 * @return string
		 */
		public static function take_return() {
			$post_id = isset( $_COOKIE[ self::RETURN_COOKIE ] ) ? absint( $_COOKIE[ self::RETURN_COOKIE ] ) : 0;
			if ( $post_id <= 0 ) {
				return '';
			}
			unset( $_COOKIE[ self::RETURN_COOKIE ] );
			if ( ! headers_sent() ) {
				setcookie( self::RETURN_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			}
			if ( ! in_array( get_post_status( $post_id ), array( 'publish', 'private' ), true ) ) {
				return '';
			}
			$link = get_permalink( $post_id );
			return $link ? (string) $link : '';
		}

		/**
		 * On template_redirect on the account page: a sign-in link from a limited page remembers the page ( links shared or opened later
		 * work too ), and neither that link nor the members-only view is ever cached.
		 */
		public static function account_request() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which page to return to and which view to draw; nothing is changed.
			$return = isset( $_GET[ self::RETURN_PARAM ] ) ? absint( $_GET[ self::RETURN_PARAM ] ) : 0;
			$view   = isset( $_GET['ec_page'] ) && self::VIEW === sanitize_key( wp_unslash( $_GET['ec_page'] ) );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( ( $return <= 0 && ! $view ) || ! is_singular() ) {
				return;
			}
			if ( ! in_array( (int) get_queried_object_id(), self::store_page_ids( array( 'ec_option_accountpage' ) ), true ) ) {
				return;
			}
			self::clear_hops(); /* the store's own view drew */
			$limited = ( $return > 0 && self::active( $return ) );
			if ( $limited ) {
				self::remember_return( $return );
			}
			if ( $view || $limited ) { /* what shows depends on who is signed in */
				if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
					wp_easycart_product_details_no_cache();
				}
				if ( ! headers_sent() ) {
					nocache_headers();
				}
			}
		}

		/* ------------------------------------------------------------------ The account page's views */

		/**
		 * The limited page a sign-in link or the members-only view is about ( 0 when none, or not limited ).
		 *
		 * @return int
		 */
		public static function requested_page() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which page the view is about; nothing is changed.
			if ( isset( $_GET['ec_page'], $_GET[ self::PAGE_PARAM ] ) && self::VIEW === sanitize_key( wp_unslash( $_GET['ec_page'] ) ) ) {
				$post_id = absint( $_GET[ self::PAGE_PARAM ] );
			} else {
				$post_id = isset( $_GET[ self::RETURN_PARAM ] ) ? absint( $_GET[ self::RETURN_PARAM ] ) : 0;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return ( $post_id > 0 && self::active( $post_id ) ) ? $post_id : 0;
		}

		/**
		 * Whether this request asks for the members-only view.
		 *
		 * @return bool
		 */
		public static function is_view_request() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which view to draw.
			return isset( $_GET['ec_page'] ) && self::VIEW === sanitize_key( wp_unslash( $_GET['ec_page'] ) );
		}

		/**
		 * The line above the sign-in form for a visitor sent from a limited page: "Sign in to open <page>." ( '' otherwise ).
		 *
		 * @return string HTML.
		 */
		public static function account_notice() {
			list( $user_id ) = self::visitor();
			$post_id         = self::requested_page();
			if ( $user_id > 0 || $post_id <= 0 ) {
				return '';
			}
			$text = str_replace( '[title]', wp_strip_all_tags( get_the_title( $post_id ) ), self::text( 'page_access_signin_notice', 'Sign in to open [title].' ) );
			return '<div class="ec_account_page_access_notice" role="status"><div>' . esc_html( $text ) . '</div></div>';
		}

		/**
		 * The account page's members-only view for a signed-in customer ( null when this request is not one: the account page draws as
		 * usual, a visitor gets the sign-in form with account_notice() ).
		 *
		 * @return string|null HTML.
		 */
		public static function account_route() {
			if ( ! self::is_view_request() ) {
				return null;
			}
			list( $user_id ) = self::visitor();
			if ( $user_id <= 0 ) {
				return null;
			}
			return self::message_html( self::requested_page() );
		}

		/**
		 * What unlocks a page, for a signed-in customer without access ( D3 ): the products with a Buy or Subscribe button, or a line
		 * to contact the store; and a link back to the store. A customer who can open the page gets a link to it.
		 *
		 * @param int $post_id Limited post ( 0 = the page could not be read ).
		 * @return string HTML.
		 */
		public static function message_html( $post_id ) {
			$post_id = (int) $post_id;
			$title   = ( $post_id > 0 ) ? wp_strip_all_tags( get_the_title( $post_id ) ) : '';
			$out     = '<section class="ec_account_members_only">';
			if ( $post_id > 0 && self::allowed( $post_id ) ) {
				$out .= '<h2 class="ec_account_members_only_title">' . esc_html( $title ) . '</h2>';
				$out .= '<p class="ec_account_members_only_open"><a class="ec_account_members_only_button" href="' . esc_url( (string) get_permalink( $post_id ) ) . '">' . esc_html( str_replace( '[title]', $title, self::text( 'page_access_open', 'Open [title]' ) ) ) . '</a></p>';
			} else {
				$rule     = ( $post_id > 0 ) ? self::rule( $post_id ) : self::defaults();
				$heading  = ( '' !== $title ) ? str_replace( '[title]', $title, self::text( 'page_access_title', '[title] is for members' ) ) : self::text( 'page_access_excerpt', 'This content is for members.' );
				$products = self::product_rows( $rule['products'] );
				$out     .= '<h2 class="ec_account_members_only_title">' . esc_html( $heading ) . '</h2>';
				if ( ! empty( $products ) ) {
					$intro = ( 'all' === $rule['match'] && count( $products ) > 1 ) ? self::text( 'page_access_products_all', 'Your account needs each of these:' ) : self::text( 'page_access_products', 'Any one of these opens it:' );
					$out  .= '<p class="ec_account_members_only_intro">' . esc_html( $intro ) . '</p>';
					$out  .= '<ul class="ec_account_members_only_products">';
					foreach ( $products as $product ) {
						$out .= '<li class="ec_account_members_only_product"><span class="ec_account_members_only_product_name">' . esc_html( $product['title'] ) . '</span>';
						if ( '' !== $product['price'] ) {
							$out .= ' <span class="ec_account_members_only_product_price">' . esc_html( $product['price'] ) . '</span>';
						}
						$out .= '<a class="ec_account_members_only_button" href="' . esc_url( $product['url'] ) . '">' . esc_html( $product['subscription'] ? self::text( 'page_access_subscribe', 'Subscribe' ) : self::text( 'page_access_buy', 'Buy' ) ) . '</a></li>';
					}
					$out .= '</ul>';
				}
				if ( empty( $products ) || ! empty( $rule['roles'] ) || ! empty( $rule['users'] ) ) {
					$out .= '<p class="ec_account_members_only_contact">' . esc_html( self::text( 'page_access_contact', 'Your account does not have access to this page. If you think it should, please contact us.' ) ) . '</p>';
				}
			}
			$out      .= '<p class="ec_account_members_only_links"><a href="' . esc_url( self::store_url() ) . '">' . esc_html( self::text( 'page_access_back', 'Back to the store' ) ) . '</a>';
			$dashboard = function_exists( 'wpeasycart_links' ) ? (string) wpeasycart_links()->get_account_page( 'dashboard' ) : '';
			if ( '' !== $dashboard ) {
				$out .= ' <span aria-hidden="true">·</span> <a href="' . esc_url( $dashboard ) . '">' . esc_html( self::text( 'page_access_account', 'My account' ) ) . '</a>';
			}
			$out .= '</p></section>';
			/**
			 * Filters the members-only view on the account page.
			 *
			 * @since 6.0.5
			 * @param string $out     HTML.
			 * @param int    $post_id Limited post.
			 */
			return (string) apply_filters( 'wp_easycart_page_access_message_html', $out, $post_id );
		}

		/**
		 * The products of a rule a customer can buy: title, price, subscription, link ( switched off products left out ).
		 *
		 * @param int[] $product_ids Products.
		 * @return array
		 */
		private static function product_rows( $product_ids ) {
			global $wpdb;
			$product_ids = array_slice( self::ids( $product_ids ), 0, 12 );
			if ( empty( $product_ids ) ) {
				return array();
			}
			$rows = $wpdb->get_results( 'SELECT product_id, title, price, is_subscription_item, subscription_bill_length, subscription_bill_period, subscription_bill_duration, activate_in_store FROM ec_product WHERE product_id IN ( ' . implode( ',', array_map( 'intval', $product_ids ) ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integer ids cast with intval.
			$by   = array();
			foreach ( (array) $rows as $row ) {
				$by[ (int) $row->product_id ] = $row;
			}
			$out = array();
			foreach ( $product_ids as $product_id ) {
				if ( ! isset( $by[ $product_id ] ) || empty( $by[ $product_id ]->activate_in_store ) ) {
					continue;
				}
				$row   = $by[ $product_id ];
				$price = ( isset( $GLOBALS['currency'] ) && is_object( $GLOBALS['currency'] ) ) ? (string) $GLOBALS['currency']->get_currency_display( (float) $row->price ) : '';
				if ( ! empty( $row->is_subscription_item ) && function_exists( 'wp_easycart_subscription_period_text' ) && '' !== $price ) {
					$period = trim( wp_strip_all_tags( (string) wp_easycart_subscription_period_text( (int) $row->subscription_bill_length, (string) $row->subscription_bill_period, (int) $row->subscription_bill_duration ) ) );
					$price  = trim( $price . ' ' . $period );
				}
				$out[] = array(
					'title'        => wp_strip_all_tags( wp_unslash( (string) $row->title ) ),
					'price'        => wp_strip_all_tags( html_entity_decode( $price, ENT_QUOTES, 'UTF-8' ) ),
					'subscription' => ! empty( $row->is_subscription_item ),
					'url'          => class_exists( 'wp_easycart_storefront_access' ) ? (string) wp_easycart_storefront_access::product_url( $product_id ) : self::store_url(),
				);
			}
			return $out;
		}

		/* ------------------------------------------------------------------ Content outside the page view */

		/**
		 * Whether the content being drawn could reach someone outside the page view: the REST API, a feed, an embed, a list or search
		 * results ( the page itself is guarded by guard() ).
		 *
		 * @return bool
		 */
		private static function protecting_context() {
			return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || ( function_exists( 'is_embed' ) && is_embed() ) || in_the_loop() || is_search();
		}

		/**
		 * Filter the_content: a limited post's content becomes the members-only line for anyone who could not open the post ( D7 ).
		 *
		 * @param string $content Content.
		 * @return string
		 */
		public static function filter_content( $content ) {
			$post = get_post();
			if ( ! $post || ! self::protecting_context() || ! self::active( $post->ID ) || self::allowed( $post->ID ) ) {
				return $content;
			}
			return '<p class="wpec-members-only">' . esc_html( self::text( 'page_access_excerpt', 'This content is for members.' ) ) . '</p>';
		}

		/**
		 * Filter get_the_excerpt: the same for excerpts, also written ones ( D7 ).
		 *
		 * @param string       $excerpt Excerpt.
		 * @param WP_Post|null $post    Post.
		 * @return string
		 */
		public static function filter_excerpt( $excerpt, $post = null ) {
			$post = get_post( $post );
			if ( ! $post || ! self::active( $post->ID ) || self::allowed( $post->ID ) ) {
				return $excerpt;
			}
			return self::text( 'page_access_excerpt', 'This content is for members.' );
		}

		/* ------------------------------------------------------------------ Checkout */

		/**
		 * Whether a cart holds a product that unlocks a page: it then needs an account at checkout, as downloads do ( D10 ).
		 *
		 * @param array $cart_items ec_cartitem objects.
		 * @return bool
		 */
		public static function cart_unlocks_page( $cart_items ) {
			$unlocking = self::unlocking_products();
			if ( empty( $unlocking ) ) {
				return false;
			}
			foreach ( (array) $cart_items as $item ) {
				if ( is_object( $item ) && isset( $item->product_id ) && in_array( (int) $item->product_id, $unlocking, true ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * The products that unlock a limited page ( kept in an option, rebuilt after a rule or a limited post changes ).
		 *
		 * @return int[]
		 */
		public static function unlocking_products() {
			$kept = get_option( self::PRODUCTS_OPTION );
			if ( is_array( $kept ) && isset( $kept['ids'] ) && is_array( $kept['ids'] ) ) {
				return array_map( 'intval', $kept['ids'] );
			}
			global $wpdb;
			$post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key IN ( %s, %s ) AND p.post_status IN ( 'publish', 'private', 'future' )",
					self::META,
					self::LEGACY_PRODUCTS
				)
			);
			$ids      = array();
			foreach ( (array) $post_ids as $post_id ) {
				$rule = self::rule( (int) $post_id );
				if ( ! empty( $rule['on'] ) && ! self::exempt( (int) $post_id ) ) {
					$ids = array_merge( $ids, $rule['products'] );
				}
			}
			$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
			update_option( self::PRODUCTS_OPTION, array( 'ids' => $ids ), false );
			return $ids;
		}

		/**
		 * Rebuild the unlocking products next time.
		 */
		public static function forget_products() {
			delete_option( self::PRODUCTS_OPTION );
			self::reset();
		}

		/**
		 * A rule's meta changed.
		 *
		 * @param int    $meta_id  Meta id.
		 * @param int    $post_id  Post.
		 * @param string $meta_key Key.
		 */
		public static function meta_changed( $meta_id, $post_id, $meta_key ) {
			if ( self::META === $meta_key || 0 === strpos( (string) $meta_key, 'wpeasycart_restrict_' ) ) {
				self::forget_products();
			}
		}

		/**
		 * A limited post was published, unpublished or trashed.
		 *
		 * @param string  $new_status New status.
		 * @param string  $old_status Old status.
		 * @param WP_Post $post Post.
		 */
		public static function post_status_changed( $new_status, $old_status, $post ) {
			if ( $new_status === $old_status || ! is_object( $post ) ) {
				return;
			}
			if ( metadata_exists( 'post', $post->ID, self::META ) || metadata_exists( 'post', $post->ID, self::LEGACY_PRODUCTS ) ) {
				self::forget_products();
			}
		}

		/* ------------------------------------------------------------------ Saving a rule ( Phase 2 ) */

		/**
		 * Save a post's rule: the 6.0.5 meta, and the six keys 6.0.4 reads ( legacy_values() ), so a store that goes back to 6.0.4
		 * keeps the page limited.
		 *
		 * @param int   $post_id Post.
		 * @param array $raw     Rule ( clean() checks every value ).
		 * @return array The rule as read back.
		 */
		public static function save_rule( $post_id, $raw ) {
			$post_id = (int) $post_id;
			$rule    = self::clean( $raw );
			$stored  = $rule;
			unset( $stored['legacy'] );
			$stored['version'] = 1;
			update_post_meta( $post_id, self::META, $stored );
			foreach ( self::legacy_values( $post_id, $rule ) as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
			self::forget_products();
			/**
			 * A post's Limit Access rule was saved.
			 *
			 * @since 6.0.5
			 * @param int   $post_id Post.
			 * @param array $rule    Rule.
			 */
			do_action( 'wp_easycart_page_access_saved', $post_id, $rule );
			return self::rule( $post_id );
		}

		/**
		 * The six keys 6.0.4 reads, for a 6.0.5 rule: the same people ( "any one" of several restrictions is "all" there, so stricter;
		 * any signed-in customer is every role ), and real addresses for the destinations; no address while the rule is off, which
		 * 6.0.4 reads as not limited.
		 *
		 * @param int   $post_id Post.
		 * @param array $rule    Rule.
		 * @return array meta key => value.
		 */
		public static function legacy_values( $post_id, $rule ) {
			$products = $rule['products'];
			$users    = $rule['users'];
			$roles    = $rule['roles'];
			if ( ! empty( $rule['signed_in'] ) && ( 'any' === $rule['match'] || ( empty( $products ) && empty( $users ) && empty( $roles ) ) ) ) {
				$products = array();
				$users    = array();
				$roles    = self::role_labels();
			}
			$out = array(
				self::LEGACY_PRODUCTS   => $products,
				self::LEGACY_USERS      => $users,
				self::LEGACY_ROLES      => $roles,
				self::LEGACY_SIGNED_OUT => '',
				self::LEGACY_MEMBERS    => '',
				self::LEGACY_NO_ACCESS  => '',
			);
			if ( empty( $rule['on'] ) || ! self::restricted( $rule ) ) {
				return $out;
			}
			$signed_out                     = self::target_url( $rule['signed_out'], $post_id, $rule );
			$no_access                      = self::target_url( $rule['no_access'], $post_id, $rule );
			$out[ self::LEGACY_SIGNED_OUT ] = esc_url_raw( '' !== $signed_out ? $signed_out : self::sign_in_url( $post_id ) );
			$out[ self::LEGACY_NO_ACCESS ]  = esc_url_raw( '' !== $no_access ? $no_access : self::message_url( $post_id ) );
			if ( 'stay' !== $rule['members']['to'] ) {
				$out[ self::LEGACY_MEMBERS ] = esc_url_raw( self::target_url( $rule['members'], $post_id, $rule ) );
			}
			return $out;
		}

		/**
		 * The store's customer roles ( ec_role labels ).
		 *
		 * @return string[]
		 */
		public static function role_labels() {
			global $wpdb;
			return array_values( array_filter( array_map( 'strval', (array) $wpdb->get_col( 'SELECT role_label FROM ec_role ORDER BY role_label ASC' ) ) ) );
		}

		/**
		 * Turn a post's rule on or off, keeping everything else.
		 *
		 * @param int  $post_id Post.
		 * @param bool $on      On.
		 * @return array The rule.
		 */
		public static function set_on( $post_id, $on ) {
			$rule       = self::rule( $post_id );
			$rule['on'] = (bool) $on;
			return self::save_rule( $post_id, $rule );
		}

		/* ------------------------------------------------------------------ Checking and describing a rule ( Phase 2 ) */

		/**
		 * What would go wrong with a rule ( D9, also shown on every visit's list ): level bad ( a visitor would be sent somewhere that
		 * can't work; the visit falls back to the store's own view ), warn ( it works, but probably not as meant ) or off ( not limited ).
		 *
		 * @param int        $post_id Post.
		 * @param array|null $rule    Rule ( the saved one when null ).
		 * @return array[] level, code, which ( signed_out | no_access | members | '' ), text.
		 */
		public static function problems( $post_id, $rule = null ) {
			$post_id = (int) $post_id;
			$rule    = is_array( $rule ) ? array_merge( self::defaults(), $rule ) : self::rule( $post_id );
			$out     = array();
			if ( ! self::restricted( $rule ) ) {
				if ( ! empty( $rule['on'] ) ) {
					$out[] = self::problem( 'warn', 'nobody', '', __( 'The limit is on but names nobody who can see the page, so everyone can open it.', 'wp-easycart' ) );
				}
				return $out;
			}
			if ( empty( $rule['on'] ) ) {
				if ( ! empty( $rule['legacy'] ) ) {
					$out[] = self::problem( 'off', 'no_address', '', __( 'Set up before WP EasyCart 6.0.5 without an address to send visitors to, so it limits nothing. Turn it on to limit the page.', 'wp-easycart' ) );
				}
				return $out;
			}
			if ( self::exempt( $post_id ) ) {
				$out[] = self::problem( 'warn', 'exempt', '', __( 'The account and cart pages are never limited, so signing in and buying always work.', 'wp-easycart' ) );
				return $out;
			}
			$labels = array(
				'signed_out' => __( 'Signed-out visitors', 'wp-easycart' ),
				'no_access'  => __( 'Customers without access', 'wp-easycart' ),
				'members'    => __( 'Customers who can see it', 'wp-easycart' ),
			);
			foreach ( $labels as $which => $who ) {
				$target = $rule[ $which ];
				if ( 'members' === $which && 'stay' === $target['to'] ) {
					continue;
				}
				$problem = self::destination_problem( $which, $target, $post_id, $rule );
				if ( $problem ) {
					/* translators: 1: who ( e.g. Signed-out visitors ), 2: what is wrong with where they are sent */
					$out[] = self::problem( $problem[0], $problem[1], $which, sprintf( __( '%1$s: %2$s', 'wp-easycart' ), $who, $problem[2] ) );
				}
			}
			return $out;
		}

		/**
		 * One problem.
		 *
		 * @param string $level bad | warn | off.
		 * @param string $code  Code.
		 * @param string $which Destination.
		 * @param string $text  Text.
		 * @return array
		 */
		private static function problem( $level, $code, $which, $text ) {
			return array(
				'level' => $level,
				'code'  => $code,
				'which' => $which,
				'text'  => $text,
			);
		}

		/**
		 * What is wrong with one destination ( null when nothing ).
		 *
		 * @param string $which   signed_out | no_access | members.
		 * @param array  $target  to, page, url.
		 * @param int    $post_id The limited post.
		 * @param array  $rule    Its rule.
		 * @return array|null level, code, text.
		 */
		private static function destination_problem( $which, $target, $post_id, $rule ) {
			$to = $target['to'];
			if ( 'product' === $to && empty( $rule['products'] ) ) {
				return array( 'warn', 'no_product', __( 'sent to the product\'s page, but no product unlocks this page, so they see what unlocks it instead.', 'wp-easycart' ) );
			}
			if ( 'page' !== $to && 'url' !== $to ) {
				return null;
			}
			$target_id = 0;
			if ( 'page' === $to ) {
				$target_id = (int) $target['page'];
				if ( $target_id <= 0 || ! in_array( get_post_status( $target_id ), array( 'publish' ), true ) ) {
					return array( 'bad', 'page_gone', __( 'the page chosen for them is missing or not published.', 'wp-easycart' ) );
				}
			} else {
				$url = self::absolute( $target['url'] );
				if ( '' === $url ) {
					return array( 'bad', 'no_address', __( 'no address is set ( a full https:// address or a path such as /join ).', 'wp-easycart' ) );
				}
				if ( ! self::is_local( $url ) ) {
					if ( empty( $rule['external_ok'] ) && empty( $rule['legacy'] ) ) {
						return array( 'bad', 'external', __( 'the address is on another site. Tick "Send visitors to another site" to allow it.', 'wp-easycart' ) );
					}
					return null;
				}
				$target_id = function_exists( 'url_to_postid' ) ? (int) url_to_postid( $url ) : 0;
				if ( $target_id <= 0 && self::same_address( $url, (string) get_permalink( $post_id ) ) ) {
					$target_id = $post_id;
				}
			}
			if ( $target_id === $post_id ) {
				return array( 'bad', 'self', __( 'sent back to this same page, which would go round in circles.', 'wp-easycart' ) );
			}
			if ( $target_id > 0 && self::active( $target_id ) ) {
				$title = wp_strip_all_tags( get_the_title( $target_id ) );
				if ( 'signed_out' === $which ) {
					/* translators: %s: page title */
					return array( 'bad', 'limited', sprintf( __( 'sent to "%s", which is limited too, so they could not open it either.', 'wp-easycart' ), $title ) );
				}
				/* translators: %s: page title */
				return array( 'warn', 'limited', sprintf( __( 'sent to "%s", which is limited too. Check they can open it.', 'wp-easycart' ), $title ) );
			}
			return null;
		}

		/**
		 * Status of a post's rule for the list: on, off or problem ( a bad problem ).
		 *
		 * @param int        $post_id  Post.
		 * @param array|null $rule     Rule.
		 * @param array|null $problems Its problems.
		 * @return string
		 */
		public static function status( $post_id, $rule = null, $problems = null ) {
			$rule     = is_array( $rule ) ? $rule : self::rule( $post_id );
			$problems = is_array( $problems ) ? $problems : self::problems( $post_id, $rule );
			if ( empty( $rule['on'] ) || ! self::restricted( $rule ) || self::exempt( $post_id ) ) {
				return 'off';
			}
			foreach ( $problems as $problem ) {
				if ( 'bad' === $problem['level'] ) {
					return 'problem';
				}
			}
			return 'on';
		}

		/**
		 * Who can see the page, in a few words ( "Bought Gold Membership or role Wholesale" ).
		 *
		 * @param array $rule Rule.
		 * @return string
		 */
		public static function who_text( $rule ) {
			global $wpdb;
			$parts = array();
			if ( ! empty( $rule['signed_in'] ) ) {
				$parts[] = __( 'Any signed-in customer', 'wp-easycart' );
			}
			if ( ! empty( $rule['products'] ) ) {
				$ids    = array_slice( $rule['products'], 0, 3 );
				$titles = (array) $wpdb->get_col( 'SELECT title FROM ec_product WHERE product_id IN ( ' . implode( ',', array_map( 'intval', $ids ) ) . ' ) ORDER BY FIELD( product_id, ' . implode( ',', array_map( 'intval', $ids ) ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integer ids cast with intval.
				$titles = array_map( 'wp_unslash', $titles );
				$more   = count( $rule['products'] ) - count( $titles );
				/* translators: %s: product names */
				$text = sprintf( __( 'Bought %s', 'wp-easycart' ), implode( ', ', $titles ) );
				if ( $more > 0 ) {
					/* translators: %d: number of other products */
					$text .= ' ' . sprintf( _n( 'and %d more', 'and %d more', $more, 'wp-easycart' ), $more );
				}
				$parts[] = $text;
			}
			if ( ! empty( $rule['roles'] ) ) {
				/* translators: %s: role names */
				$parts[] = sprintf( _n( 'Role %s', 'Roles %s', count( $rule['roles'] ), 'wp-easycart' ), implode( ', ', $rule['roles'] ) );
			}
			if ( ! empty( $rule['users'] ) ) {
				/* translators: %d: number of customers */
				$parts[] = sprintf( _n( '%d chosen customer', '%d chosen customers', count( $rule['users'] ), 'wp-easycart' ), count( $rule['users'] ) );
			}
			if ( empty( $parts ) ) {
				return __( 'Nobody chosen', 'wp-easycart' );
			}
			return implode( ( 'all' === $rule['match'] ) ? __( ' and ', 'wp-easycart' ) : __( ' or ', 'wp-easycart' ), $parts );
		}

		/**
		 * Where one kind of visitor goes, in a few words.
		 *
		 * @param array  $rule  Rule.
		 * @param string $which signed_out | no_access | members.
		 * @return string
		 */
		public static function where_text( $rule, $which ) {
			$target = $rule[ $which ];
			switch ( $target['to'] ) {
				case 'sign_in':
					return __( 'Sign in, then back', 'wp-easycart' );
				case 'message':
					return __( 'What unlocks it', 'wp-easycart' );
				case 'product':
					return __( 'The product', 'wp-easycart' );
				case 'store':
					return __( 'The store page', 'wp-easycart' );
				case 'stay':
					return __( 'Stay on the page', 'wp-easycart' );
				case 'page':
					$title = ( (int) $target['page'] > 0 ) ? wp_strip_all_tags( get_the_title( (int) $target['page'] ) ) : '';
					return ( '' !== $title ) ? $title : __( 'A missing page', 'wp-easycart' );
				case 'url':
					$url = (string) $target['url'];
					if ( '' === $url ) {
						return __( 'No address', 'wp-easycart' );
					}
					$parts = wp_parse_url( $url );
					if ( ! empty( $parts['host'] ) && ! self::is_local( self::absolute( $url ) ) ) {
						return $parts['host'] . ( isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '' );
					}
					return ( isset( $parts['path'] ) ? $parts['path'] : '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
			}
			return '';
		}

		/**
		 * One sentence for the box: what the rule does ( or that it does nothing ).
		 *
		 * @param int   $post_id Post.
		 * @param array $rule    Rule.
		 * @return string
		 */
		public static function summary( $post_id, $rule ) {
			if ( self::paused() ) {
				return __( 'Every limit is paused in Settings › Accounts, so everyone can open this page for now.', 'wp-easycart' );
			}
			if ( self::exempt( $post_id ) ) {
				return __( 'The account and cart pages are never limited.', 'wp-easycart' );
			}
			if ( empty( $rule['on'] ) || ! self::restricted( $rule ) ) {
				return __( 'Everyone can open this page.', 'wp-easycart' );
			}
			/* translators: 1: who can see the page, 2: where signed-out visitors go, 3: where customers without access go */
			return sprintf( __( 'Open to: %1$s. Signed-out visitors: %2$s. Customers without access: %3$s.', 'wp-easycart' ), self::who_text( $rule ), self::where_text( $rule, 'signed_out' ), self::where_text( $rule, 'no_access' ) );
		}

		/**
		 * Every post with a Limit Access rule ( saved by 6.0.5, or one that 6.0.4 would read as limiting someone ), newest title first
		 * by title.
		 *
		 * @param string $search Words in the title.
		 * @return int[]
		 */
		public static function limited_post_ids( $search = '' ) {
			global $wpdb;
			$types = array_map( 'esc_sql', self::post_types() );
			if ( empty( $types ) ) {
				return array();
			}
			$sql  = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID";
			$sql .= " WHERE pm.meta_key IN ( %s, %s, %s, %s ) AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )";
			$sql .= " AND p.post_type IN ( '" . implode( "','", $types ) . "' )";
			$args = array( self::META, self::LEGACY_PRODUCTS, self::LEGACY_USERS, self::LEGACY_ROLES );
			if ( '' !== trim( (string) $search ) ) {
				$sql   .= ' AND p.post_title LIKE %s';
				$args[] = '%' . $wpdb->esc_like( trim( (string) $search ) ) . '%';
			}
			$sql .= ' ORDER BY p.post_title ASC, p.ID ASC';
			$ids  = $wpdb->get_col( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders bound here; the post types are esc_sql()'d registered names.
			$out  = array();
			foreach ( (array) $ids as $id ) {
				$id   = (int) $id;
				$rule = self::rule( $id );
				if ( empty( $rule['legacy'] ) || self::restricted( $rule ) ) {
					$out[] = $id;
				}
			}
			return $out;
		}

		/**
		 * How many posts are limited right now ( the weekly usage check-in sends it as a range ).
		 *
		 * @return int
		 */
		public static function count_limited() {
			$count = 0;
			foreach ( self::limited_post_ids() as $post_id ) {
				if ( self::active( $post_id ) ) {
					++$count;
				}
			}
			return $count;
		}

		/* ------------------------------------------------------------------ Pages a customer can open ( Phase 3, D13 ) */

		/**
		 * The limited pages a customer can open ( published, on, not paused ), post id => title and address.
		 *
		 * @param int    $user_id ec_user id.
		 * @param string $role    The customer's role ( user_level ).
		 * @return array post id => array( title, url ).
		 */
		public static function pages_for( $user_id, $role ) {
			$user_id = (int) $user_id;
			$key     = $user_id . '|' . $role;
			if ( isset( self::$pages[ $key ] ) ) {
				return self::$pages[ $key ];
			}
			$out = array();
			if ( $user_id > 0 && ! self::paused() ) {
				foreach ( self::limited_post_ids() as $post_id ) {
					$rule = self::rule( $post_id );
					if ( 'publish' !== get_post_status( $post_id ) || ! self::active( $post_id, $rule ) || ! self::passes( $rule, $user_id, $role ) ) {
						continue;
					}
					$out[ $post_id ] = array(
						'title' => wp_strip_all_tags( get_the_title( $post_id ) ),
						'url'   => (string) get_permalink( $post_id ),
					);
				}
			}
			/**
			 * Filters the limited pages a customer can open ( My Account ).
			 *
			 * @since 6.0.5
			 * @param array $out     post id => title, url.
			 * @param int   $user_id ec_user id.
			 */
			self::$pages[ $key ] = (array) apply_filters( 'wp_easycart_page_access_customer_pages', $out, $user_id );
			return self::$pages[ $key ];
		}

		/**
		 * The limited pages a product opens, post id => title and address ( published, on, not paused ).
		 *
		 * @param int[] $product_ids Products.
		 * @return array post id => array( title, url ).
		 */
		public static function pages_opened_by( $product_ids ) {
			$product_ids = self::ids( $product_ids );
			$out         = array();
			if ( empty( $product_ids ) || self::paused() ) {
				return $out;
			}
			foreach ( self::limited_post_ids() as $post_id ) {
				$rule = self::rule( $post_id );
				if ( 'publish' !== get_post_status( $post_id ) || ! self::active( $post_id, $rule ) || ! array_intersect( $rule['products'], $product_ids ) ) {
					continue;
				}
				$out[ $post_id ] = array(
					'title' => wp_strip_all_tags( get_the_title( $post_id ) ),
					'url'   => (string) get_permalink( $post_id ),
				);
			}
			return $out;
		}

		/**
		 * The links of a list of pages.
		 *
		 * @param array  $pages post id => title, url.
		 * @param string $list_class List class.
		 * @return string HTML.
		 */
		private static function page_links( $pages, $list_class ) {
			$out = '<ul class="' . esc_attr( $list_class ) . '">';
			foreach ( $pages as $page ) {
				$out .= '<li><a href="' . esc_url( $page['url'] ) . '">' . esc_html( '' !== $page['title'] ? $page['title'] : $page['url'] ) . '</a></li>';
			}
			return $out . '</ul>';
		}

		/**
		 * My Account's dashboard ( wpeasycart_dashboard_top ): "Pages you can open", when the customer can open a limited page.
		 */
		public static function account_pages() {
			list( $user_id, $role ) = self::visitor();
			$pages                  = self::pages_for( $user_id, $role );
			if ( empty( $pages ) ) {
				return;
			}
			echo '<div class="ec_account_dashboard_pages ec_account_dashboard_card_v2" id="ec_account_dashboard_pages">';
			echo '<div class="ec_cart_header">' . esc_html( self::text( 'page_access_your_pages', 'Pages you can open' ) ) . '</div>';
			echo self::page_links( $pages, 'ec_account_dashboard_pages_list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by page_links().
			echo '</div>';
		}

		/**
		 * The order confirmation page ( wpeasycart_success_page_content_middle ): the pages the order opens, with a note while the
		 * payment is not complete yet.
		 *
		 * @param int         $order_id Order.
		 * @param object|null $order    ec_orderdisplay.
		 */
		public static function order_pages( $order_id, $order = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the action's signature.
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 ) {
				return;
			}
			$product_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT product_id FROM ec_orderdetail WHERE order_id = %d', $order_id ) ) );
			$pages       = self::pages_opened_by( $product_ids );
			if ( empty( $pages ) ) {
				return;
			}
			list( $user_id, $role ) = self::visitor();
			$open                   = array_intersect_key( $pages, self::pages_for( $user_id, $role ) );
			$waiting                = array_diff_key( $pages, $open );
			echo '<div class="ec_order_success_pages" id="ec_order_success_pages">';
			if ( ! empty( $open ) ) {
				echo '<div class="ec_order_success_pages_title">' . esc_html( self::text( 'page_access_order_opens', 'Your order opens:' ) ) . '</div>';
				echo self::page_links( $open, 'ec_order_success_pages_list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by page_links().
			}
			if ( ! empty( $waiting ) ) {
				echo '<div class="ec_order_success_pages_title">' . esc_html( self::text( 'page_access_order_pending', 'Once your payment is complete, your order opens:' ) ) . '</div>';
				echo self::page_links( $waiting, 'ec_order_success_pages_list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by page_links().
			}
			echo '</div>';
		}

		/* ------------------------------------------------------------------ Staff: Preview as ( Phase 2, D6 ) */

		/**
		 * Where a kind of visitor would go on a post: what destination() answers for a signed-out visitor, a customer without access or a
		 * member, whoever is asking.
		 *
		 * @param int    $post_id Post.
		 * @param string $visitor signed_out | no_access | member.
		 * @return array allowed, state, url.
		 */
		public static function preview( $post_id, $visitor ) {
			$visitor  = in_array( $visitor, array( 'signed_out', 'no_access', 'member' ), true ) ? $visitor : 'signed_out';
			self::$as = array(
				'post'  => (int) $post_id,
				'state' => $visitor,
			);
			self::reset();
			$out      = self::destination( $post_id );
			self::$as = null;
			self::reset();
			return $out;
		}

		/**
		 * A preview in words: "A signed-out visitor would be sent to sign in ( /account/?ec_page=login ) and back here afterwards."
		 *
		 * @param int    $post_id Post.
		 * @param string $visitor      signed_out | no_access | member.
		 * @return string
		 */
		public static function preview_text( $post_id, $visitor ) {
			$d    = self::preview( $post_id, $visitor );
			$path = self::short_url( $d['url'] );
			if ( 'member' === $visitor ) {
				if ( '' === $d['url'] ) {
					return __( 'A customer who can see it opens this page.', 'wp-easycart' );
				}
				/* translators: %s: address */
				return sprintf( __( 'A customer who can see it is sent on to %s.', 'wp-easycart' ), $path );
			}
			$who = ( 'signed_out' === $visitor ) ? __( 'A signed-out visitor', 'wp-easycart' ) : __( 'A signed-in customer without access', 'wp-easycart' );
			if ( '' === $d['url'] ) {
				/* translators: %s: who */
				return sprintf( __( '%s sees a plain "no access" page.', 'wp-easycart' ), $who );
			}
			if ( self::is_sign_in_url( $d['url'] ) ) {
				/* translators: 1: who, 2: address */
				return sprintf( __( '%1$s is sent to sign in ( %2$s ) and back here afterwards.', 'wp-easycart' ), $who, $path );
			}
			if ( false !== strpos( $d['url'], 'ec_page=' . self::VIEW ) ) {
				/* translators: 1: who, 2: address */
				return sprintf( __( '%1$s is shown what unlocks this page on your account page ( %2$s ).', 'wp-easycart' ), $who, $path );
			}
			/* translators: 1: who, 2: address */
			return sprintf( __( '%1$s is sent to %2$s.', 'wp-easycart' ), $who, $path );
		}

		/**
		 * An address as staff read it: the path on this site, else the whole address.
		 *
		 * @param string $url Address.
		 * @return string
		 */
		private static function short_url( $url ) {
			if ( '' === (string) $url || ! self::is_local( $url ) ) {
				return (string) $url;
			}
			$parts = wp_parse_url( $url );
			$query = array();
			if ( ! empty( $parts['query'] ) ) {
				wp_parse_str( $parts['query'], $query );
				unset( $query[ self::RETURN_PARAM ] );
			}
			return ( isset( $parts['path'] ) ? $parts['path'] : '/' ) . ( $query ? '?' . http_build_query( $query ) : '' );
		}

		/**
		 * Whether this request draws a page builder's editor or preview canvas ( no staff bar inside it ).
		 *
		 * @return bool
		 */
		private static function builder_canvas() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only checks which screen this is.
			foreach ( array( 'elementor-preview', 'et_fb', 'fl_builder', 'bricks', 'ct_builder', 'brizy-edit-iframe', 'vc_editable', 'tve', 'oxygen_iframe' ) as $arg ) {
				if ( isset( $_GET[ $arg ] ) ) {
					return true;
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			return is_customize_preview() || ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() );
		}

		/**
		 * The limited page staff are looking at ( 0 when none ).
		 *
		 * @return int
		 */
		private static function staff_page() {
			if ( is_admin() || ! is_singular() || self::builder_canvas() ) {
				return 0;
			}
			$post_id = (int) get_queried_object_id();
			if ( ! self::active( $post_id ) ) {
				return 0;
			}
			$decision = self::decide( $post_id );
			return ( 'staff' === $decision['state'] ) ? $post_id : 0;
		}

		/**
		 * Admin bar: "Members only" with Preview as, on a limited page staff are looking at.
		 *
		 * @param WP_Admin_Bar $bar Admin bar.
		 */
		public static function staff_bar( $bar ) {
			$post_id = self::staff_page();
			if ( ! $post_id || ! is_object( $bar ) ) {
				return;
			}
			$here = (string) get_permalink( $post_id );
			$bar->add_node(
				array(
					'id'    => 'wpec-access',
					'title' => '<span class="ab-icon dashicons dashicons-lock" aria-hidden="true" style="margin-top:2px"></span><span class="ab-label">' . esc_html__( 'Members only', 'wp-easycart' ) . '</span>',
					'href'  => esc_url( add_query_arg( self::PREVIEW_PARAM, 'signed_out', $here ) ),
					'meta'  => array( 'title' => __( 'This page is limited. You see it as staff.', 'wp-easycart' ) ),
				)
			);
			foreach ( self::preview_choices() as $as => $label ) {
				$bar->add_node(
					array(
						'parent' => 'wpec-access',
						'id'     => 'wpec-access-' . $as,
						'title'  => esc_html( $label ),
						'href'   => esc_url( add_query_arg( self::PREVIEW_PARAM, $as, $here ) ),
					)
				);
			}
			$edit = get_edit_post_link( $post_id, 'raw' );
			if ( $edit ) {
				$bar->add_node(
					array(
						'parent' => 'wpec-access',
						'id'     => 'wpec-access-edit',
						'title'  => esc_html__( 'Edit who can see it', 'wp-easycart' ),
						'href'   => esc_url( $edit . '#wp-easycart-product-lock' ),
					)
				);
			}
		}

		/**
		 * The kinds of visitor staff can preview.
		 *
		 * @return array as => label.
		 */
		private static function preview_choices() {
			return array(
				'signed_out' => __( 'Preview as a signed-out visitor', 'wp-easycart' ),
				'no_access'  => __( 'Preview as a customer without access', 'wp-easycart' ),
				'member'     => __( 'Preview as a customer who can see it', 'wp-easycart' ),
			);
		}

		/**
		 * The footer card for staff: the preview's answer, and the Preview as links when there is no admin bar.
		 */
		public static function staff_footer() {
			$post_id = self::staff_page();
			if ( ! $post_id ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which preview to describe; nothing is changed.
			$as = isset( $_GET[ self::PREVIEW_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::PREVIEW_PARAM ] ) ) : '';
			if ( '' === $as && is_admin_bar_showing() ) {
				return;
			}
			$here = (string) get_permalink( $post_id );
			echo '<div class="wpec-access-staff" role="region" aria-label="' . esc_attr__( 'Members only page', 'wp-easycart' ) . '" style="position:fixed;z-index:99999;inset-inline-start:16px;bottom:16px;max-width:min(440px,calc(100vw - 32px));box-sizing:border-box;padding:14px 16px;border-radius:10px;background:#1d2327;color:#f0f0f1;font:14px/1.5 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.25)">';
			echo '<div style="font-weight:600;margin:0 0 4px">' . esc_html__( 'Members only', 'wp-easycart' ) . ' <span style="font-weight:400;opacity:.75">' . esc_html__( 'You see this page as staff.', 'wp-easycart' ) . '</span></div>';
			if ( isset( self::preview_choices()[ $as ] ) ) {
				echo '<p style="margin:0 0 8px;color:#f0f0f1">' . esc_html( self::preview_text( $post_id, $as ) ) . '</p>';
			}
			echo '<p style="margin:0;display:flex;flex-wrap:wrap;gap:4px 12px">';
			foreach ( self::preview_choices() as $key => $label ) {
				$style = 'color:#72aee6;text-decoration:' . ( $key === $as ? 'none;font-weight:600' : 'underline' );
				echo '<a style="' . esc_attr( $style ) . '" href="' . esc_url( add_query_arg( self::PREVIEW_PARAM, $key, $here ) ) . '"' . ( $key === $as ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
			}
			if ( '' !== $as ) {
				echo '<a style="color:#f0f0f1;text-decoration:underline" href="' . esc_url( $here ) . '">' . esc_html__( 'Close', 'wp-easycart' ) . '</a>';
			}
			echo '</p></div>';
		}

		/* ------------------------------------------------------------------ Helpers */

		/**
		 * The post types a page can be limited on ( D8 ): every public type but media, plus product pages.
		 *
		 * @return string[]
		 */
		public static function post_types() {
			$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment', 'elementor_library' ) ) );
			if ( ! in_array( 'ec_store', $types, true ) && post_type_exists( 'ec_store' ) ) {
				$types[] = 'ec_store';
			}
			/**
			 * Filters the post types that show WP EasyCart Limit Access.
			 *
			 * @since 6.0.5
			 * @param string[] $types Post types.
			 */
			return array_values( (array) apply_filters( 'wp_easycart_page_access_post_types', $types ) );
		}

		/**
		 * Positive integer ids from a stored value ( array, or the older comma list ).
		 *
		 * @param mixed $value Value.
		 * @return int[]
		 */
		public static function ids( $value ) {
			$list = is_array( $value ) ? $value : explode( ',', (string) $value );
			$ids  = array();
			foreach ( $list as $id ) {
				if ( is_scalar( $id ) && (int) $id > 0 && ! in_array( (int) $id, $ids, true ) ) {
					$ids[] = (int) $id;
				}
			}
			return $ids;
		}

		/**
		 * Role labels from a stored value ( '' and '0' are "No Restriction" ).
		 *
		 * @param mixed $value Value.
		 * @param bool  $clean Clean each label ( a rule being saved ); a page set up before 6.0.5 compares its value as 6.0.4 did.
		 * @return string[]
		 */
		public static function labels( $value, $clean = true ) {
			$labels = array();
			foreach ( ( is_array( $value ) ? $value : array( $value ) ) as $label ) {
				$label = is_scalar( $label ) ? ( $clean ? sanitize_text_field( (string) $label ) : (string) $label ) : '';
				if ( '' !== $label && '0' !== $label && ! in_array( $label, $labels, true ) ) {
					$labels[] = $label;
				}
			}
			return $labels;
		}

		/**
		 * A storefront phrase ( language section product_page ), else the English.
		 *
		 * @param string $key      Phrase.
		 * @param string $fallback English.
		 * @return string
		 */
		public static function text( $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'product_page', $key ) : '';
			$text = is_string( $text ) ? trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) : '';
			return ( '' !== $text ) ? $text : $fallback;
		}
	}

	wp_easycart_page_access::init();

endif;
