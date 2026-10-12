<?php
/**
 * WP EasyCart Admin Edition
 *
 * One place that knows which plan this store is on and how to name it. Pro and Premium customers
 * both run the plugin named "WP EasyCart PRO", so the admin names the customer's own plan whenever
 * the license is known, and says "Pro/Premium" only when it is not:
 *
 *   tier()          'free' | 'trial' | 'pro' | 'premium'   ( a trial is always a Pro trial; Elite is 'premium' )
 *   edition()       tier(), with 'elite' for Elite ( 6.0.4 )
 *   plan_name()     'Pro/Premium' on the free edition, else 'Pro', 'Premium' or 'Elite'
 *   is_auto_renew() the license renews itself through a subscription ( AutoPay, 6.0.4 )
 *   badge( $plan )  chip text for a locked control: a Premium-only feature is always 'Premium'
 *   included_text() / requires_text()   the sentences locked controls and refused actions use
 *
 * Use PLUGIN_NAME only when the copy is about the plugin itself ( install, activate, update,
 * deactivate ): it is the name shown on the Plugins screen and is never renamed.
 *
 * PRO calls this class guarded by class_exists(); every method is safe without PRO.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_edition' ) ) :

	class wp_easycart_admin_edition {

		/** The PRO plugin's name on the Plugins screen. It runs both Pro and Premium licenses. */
		const PLUGIN_NAME = 'WP EasyCart PRO';

		/** The one-time Premium license's model number. Every Premium model is in PREMIUM_MODELS. */
		const PREMIUM_MODEL = 'ec410';

		/**
		 * Every license model number that sells Premium ( 6.0.4 ). ec410 / ec415 / ec420 are the one-time Premium license, its
		 * renewal and the Pro-to-Premium upgrade. The AutoPay subscriptions are ec6x0 ( monthly ) and ec6x5 ( yearly ), the
		 * middle digit being the edition ( 0 Pro, 1 Premium, 2 Elite ). Turning AutoPay on for a Premium key changes its model
		 * from ec410 to ec610 / ec615, so a test for ec410 alone treated a paying Premium customer as Pro. Keep in step with
		 * WP EasyCart PRO's wp_easycart_pro_model_edition().
		 */
		const PREMIUM_MODELS = array( 'ec410', 'ec415', 'ec420', 'ec610', 'ec615' );

		/** Every license model number that sells Elite ( 6.0.4 ). Elite includes everything in Premium. */
		const ELITE_MODELS = array( 'ec620', 'ec625' );

		/** Where a customer manages a license that renews itself, or fixes its payment ( 6.0.4 ). */
		const ACCOUNT_URL = 'https://www.wpeasycart.com/my-account/';

		/**
		 * Cached license state for this request.
		 *
		 * @var array|null
		 */
		private static $state = null;

		/**
		 * The edition a license model number sells: 'elite', 'premium' or 'pro' ( any other model ).
		 *
		 * @since 6.0.4
		 * @param string $model License model number.
		 * @return string
		 */
		public static function model_edition( $model ) {
			$model = strtolower( trim( (string) $model ) );
			if ( in_array( $model, self::ELITE_MODELS, true ) ) {
				return 'elite';
			}
			if ( in_array( $model, self::PREMIUM_MODELS, true ) ) {
				return 'premium';
			}
			return 'pro';
		}

		/**
		 * A model number that includes every Premium feature ( Premium or Elite ).
		 *
		 * @since 6.0.4
		 * @param string $model License model number.
		 * @return bool
		 */
		public static function is_premium_model( $model ) {
			return ( 'pro' !== self::model_edition( $model ) );
		}

		/**
		 * License state: tier, edition, lapsed, end timestamp, auto_renew.
		 *
		 * @return array
		 */
		public static function state() {
			if ( null !== self::$state ) {
				return self::$state;
			}
			$state = array(
				'tier'       => 'free',
				'edition'    => 'free',
				'lapsed'     => false,
				'end'        => 0,
				'auto_renew' => false,
			);
			if ( function_exists( 'wp_easycart_admin_license' ) ) {
				$license = wp_easycart_admin_license();
				$data    = $license->license_data;
				if ( $license->valid_license && is_object( $data ) ) {
					$model = isset( $data->model_number ) ? strtolower( trim( (string) $data->model_number ) ) : '';
					if ( ! empty( $data->is_trial ) ) {
						$state['tier'] = 'trial';
					} else {
						/* 6.0.4: Premium and Elite AutoPay keys are Premium too, not only ec410 ( Elite includes every Premium feature ). */
						$state['tier'] = self::is_premium_model( $model ) ? 'premium' : 'pro';
					}
					$state['edition']    = ( 'trial' === $state['tier'] ) ? 'trial' : self::model_edition( $model );
					/* 6.0.4: the licensing server says when the key renews through a subscription ( AutoPay ). */
					$state['auto_renew'] = ! empty( $data->is_auto_renew );
					$state['lapsed'] = (bool) $license->license_expired;
					$state['end']    = ! empty( $data->support_end_date ) ? (int) strtotime( $data->support_end_date ) : 0;
				}
			}
			/**
			 * Filter the license state the admin wording is based on.
			 *
			 * @since 6.0.0
			 * @param array $state tier ( free|trial|pro|premium ), edition ( tier, or elite; 6.0.4 ), lapsed ( bool ), end ( timestamp ),
			 *                     auto_renew ( bool, 6.0.4 ).
			 */
			$state = apply_filters( 'wp_easycart_admin_edition_state', $state );
			/* PRO checks its license while it loads; an answer given before every plugin has loaded is not kept. */
			if ( did_action( 'plugins_loaded' ) ) {
				self::$state = $state;
			}
			return $state;
		}

		/**
		 * Drop the cached state ( after a license is activated or removed in this request ).
		 */
		public static function reset() {
			self::$state = null;
		}

		/**
		 * The store's plan: 'free', 'trial', 'pro' or 'premium'.
		 *
		 * @return string
		 */
		public static function tier() {
			$state = self::state();
			return $state['tier'];
		}

		/**
		 * A paid license or trial that is past its end date.
		 *
		 * @return bool
		 */
		public static function is_lapsed() {
			$state = self::state();
			return ( 'free' !== $state['tier'] && $state['lapsed'] );
		}

		/**
		 * Premium license ( active or lapsed ).
		 *
		 * @return bool
		 */
		public static function is_premium() {
			return ( 'premium' === self::tier() );
		}

		/**
		 * Pro trial ( active or ended ).
		 *
		 * @return bool
		 */
		public static function is_trial() {
			return ( 'trial' === self::tier() );
		}

		/**
		 * The store's edition: 'free', 'trial', 'pro', 'premium' or 'elite'. tier() calls Elite 'premium', so every Premium
		 * check keeps working; use this only where Elite needs its own name.
		 *
		 * @since 6.0.4
		 * @return string
		 */
		public static function edition() {
			$state = self::state();
			return isset( $state['edition'] ) ? $state['edition'] : $state['tier'];
		}

		/**
		 * Elite license ( active or lapsed ). An Elite store is also is_premium().
		 *
		 * @since 6.0.4
		 * @return bool
		 */
		public static function is_elite() {
			return ( 'elite' === self::edition() );
		}

		/**
		 * A paid license that renews itself through a subscription ( AutoPay ): the admin says when it renews instead of asking
		 * the store to renew. Read from the licensing server's is_auto_renew; false while the server does not send it.
		 *
		 * @since 6.0.4
		 * @return bool
		 */
		public static function is_auto_renew() {
			$state = self::state();
			return ( in_array( $state['tier'], array( 'pro', 'premium' ), true ) && ! empty( $state['auto_renew'] ) );
		}

		/**
		 * Where a paid license is renewed: the WP EasyCart account for one on AutoPay ( a payment that did not go through is
		 * fixed there ), the pricing page for Elite ( sold only as a subscription ), else the Premium or Pro renewal with the key.
		 *
		 * @since 6.0.4
		 * @return string
		 */
		public static function renew_url() {
			if ( self::is_auto_renew() ) {
				return self::ACCOUNT_URL;
			}
			if ( self::is_elite() ) {
				return 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/';
			}
			$key = self::license_key();
			return 'https://www.wpeasycart.com/products/' . ( self::is_premium() ? 'wp-easycart-premium-support-extensions/' : 'wp-easycart-professional-support-upgrades/' ) . ( '' !== $key ? '?transaction_key=' . rawurlencode( $key ) : '' );
		}

		/**
		 * A Pro, Premium or trial license is registered on this site.
		 *
		 * @return bool
		 */
		public static function has_license() {
			return ( 'free' !== self::tier() );
		}

		/**
		 * The plan name to use in copy: 'Pro/Premium' when no license is known, else the customer's plan ( Elite since 6.0.4 ).
		 *
		 * @return string
		 */
		public static function plan_name() {
			switch ( self::tier() ) {
				case 'premium':
					return self::is_elite() ? __( 'Elite', 'wp-easycart' ) : __( 'Premium', 'wp-easycart' );
				case 'pro':
				case 'trial':
					return __( 'Pro', 'wp-easycart' );
				default:
					return __( 'Pro/Premium', 'wp-easycart' );
			}
		}

		/**
		 * The customer's license, named: 'Pro trial', 'Pro license', 'Premium license', 'Elite license', or '' on the free edition.
		 *
		 * @return string
		 */
		public static function license_name() {
			switch ( self::tier() ) {
				case 'premium':
					return self::is_elite() ? __( 'Elite license', 'wp-easycart' ) : __( 'Premium license', 'wp-easycart' );
				case 'pro':
					return __( 'Pro license', 'wp-easycart' );
				case 'trial':
					return __( 'Pro trial', 'wp-easycart' );
				default:
					return '';
			}
		}

		/**
		 * Chip / badge text for a locked control.
		 *
		 * @param string $plan 'pro' for features in both plans, 'premium' for Premium-only features.
		 * @return string
		 */
		public static function badge( $plan = 'pro' ) {
			if ( 'premium' === $plan ) {
				return __( 'Premium', 'wp-easycart' );
			}
			return self::plan_name();
		}

		/**
		 * One sentence for a locked control's tooltip or notice.
		 *
		 * @param string $plan 'pro' or 'premium'.
		 * @return string
		 */
		public static function included_text( $plan = 'pro' ) {
			$tier   = self::tier();
			$lapsed = self::is_lapsed();

			if ( 'premium' === $plan && 'premium' !== $tier ) {
				if ( 'free' === $tier ) {
					return __( 'Included with a Premium license.', 'wp-easycart' );
				}
				/* translators: %s: the customer's current license, e.g. "Pro license". */
				return sprintf( __( 'Included with a Premium license. Upgrade your %s to Premium to use it.', 'wp-easycart' ), self::license_name() );
			}
			if ( 'free' === $tier ) {
				return __( 'Included with Pro and Premium licenses.', 'wp-easycart' );
			}
			if ( 'trial' === $tier ) {
				return $lapsed ? __( 'Part of your Pro trial. Upgrade to turn it back on.', 'wp-easycart' ) : __( 'Included in your Pro trial.', 'wp-easycart' );
			}
			if ( $lapsed ) {
				/* translators: %s: plan name, Pro or Premium. */
				return sprintf( __( 'Part of your %s license. Renew to turn it back on.', 'wp-easycart' ), self::plan_name() );
			}
			/* translators: %s: plan name, Pro or Premium. */
			return sprintf( __( 'Included with your %s license.', 'wp-easycart' ), self::plan_name() );
		}

		/**
		 * One sentence when an action is refused because a feature is locked.
		 *
		 * @param string $feature Feature name, already translated ( e.g. "Flex-Fees" ).
		 * @param string $plan    'pro' or 'premium'.
		 * @return string
		 */
		public static function requires_text( $feature, $plan = 'pro' ) {
			$tier   = self::tier();
			$lapsed = self::is_lapsed();

			if ( 'premium' === $plan && 'premium' !== $tier ) {
				/* translators: %s: feature name. */
				return sprintf( __( '%s is included with a Premium license.', 'wp-easycart' ), $feature );
			}
			if ( 'trial' === $tier && $lapsed ) {
				/* translators: %s: feature name. */
				return sprintf( __( '%s is paused because your Pro trial ended. Upgrade to turn it back on.', 'wp-easycart' ), $feature );
			}
			if ( $lapsed ) {
				/* translators: 1: feature name, 2: plan name, Pro or Premium. */
				return sprintf( __( '%1$s is paused because your %2$s license expired. Renew to turn it back on.', 'wp-easycart' ), $feature, self::plan_name() );
			}
			if ( 'free' === $tier ) {
				/* translators: %s: feature name. */
				return sprintf( __( '%s is included with Pro and Premium licenses.', 'wp-easycart' ), $feature );
			}
			/* translators: 1: feature name, 2: plugin name. */
			return sprintf( __( '%1$s is not available right now. Check that the %2$s plugin is active and up to date.', 'wp-easycart' ), $feature, self::PLUGIN_NAME );
		}

		/**
		 * How this store gets Premium, and where its button goes. A licensed Pro store upgrades at the Pro discount, a Pro
		 * trial upgrades through the trial link ( it keeps the trial's key ), a lapsed Premium store renews, and every other
		 * store ( free, a lapsed Pro license ) buys Premium on premium-support-extensions. A Premium store with a current
		 * license gets mode 'active' and no link.
		 *
		 * @since 6.0.2
		 * @return array mode ( active|renew|upgrade|trial|get ), url, cta, title, desc.
		 */
		public static function premium_offer() {
			$tier   = self::tier();
			$lapsed = self::is_lapsed();
			$key    = self::license_key();
			$buy    = 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/';
			$keyed  = ( '' !== $key ) ? $buy . '?transaction_key=' . rawurlencode( $key ) : $buy;
			if ( 'premium' === $tier && ! $lapsed ) {
				$offer = array(
					'mode'  => 'active',
					'url'   => '',
					'cta'   => '',
					'title' => '',
					'desc'  => '',
				);
			} elseif ( 'premium' === $tier ) {
				$offer = array(
					'mode'  => 'renew',
					'url'   => self::renew_url(), // 6.0.4: the account for AutoPay, the pricing page for Elite, else $keyed.
					'cta'   => __( 'Renew Premium', 'wp-easycart' ),
					'title' => __( 'Renew Premium', 'wp-easycart' ),
					'desc'  => __( 'Renew to install, update and change your extensions. They keep running in the meantime.', 'wp-easycart' ),
				);
			} elseif ( 'pro' === $tier && ! $lapsed && self::is_auto_renew() ) {
				/* 6.0.4: a Pro subscription ( AutoPay ) moves to Premium in the WP EasyCart account, as a change of plan. */
				$offer = array(
					'mode'  => 'upgrade',
					'url'   => self::ACCOUNT_URL,
					'cta'   => __( 'Upgrade to Premium', 'wp-easycart' ),
					'title' => __( 'Upgrade to Premium', 'wp-easycart' ),
					'desc'  => __( 'Change your plan to Premium in your WP EasyCart account. You keep everything in Pro and add every extension.', 'wp-easycart' ),
				);
			} elseif ( 'pro' === $tier && ! $lapsed ) {
				$offer = array(
					'mode'  => 'upgrade',
					'url'   => 'https://www.wpeasycart.com/products/wp-easycart-pro-to-premium-upgrade/' . ( '' !== $key ? '?transaction_key=' . rawurlencode( $key ) : '' ),
					'cta'   => __( 'Upgrade to Premium', 'wp-easycart' ),
					'title' => __( 'Upgrade to Premium', 'wp-easycart' ),
					'desc'  => __( 'Pro customers upgrade at a discount. You keep everything in Pro and add every extension.', 'wp-easycart' ),
				);
			} elseif ( 'trial' === $tier ) {
				$offer = array(
					'mode'  => 'trial',
					'url'   => 'https://www.wpeasycart.com/products/wp-easycart-trial-upgrade/?transaction_key=' . rawurlencode( $key ) . '&license_type=Premium',
					'cta'   => __( 'Upgrade to Premium', 'wp-easycart' ),
					'title' => __( 'Upgrade your trial to Premium', 'wp-easycart' ),
					'desc'  => __( 'Everything in Pro, plus every extension, installed and updated from your dashboard.', 'wp-easycart' ),
				);
			} else {
				$offer = array(
					'mode'  => 'get',
					'url'   => $keyed,
					'cta'   => __( 'Get Premium', 'wp-easycart' ),
					'title' => __( 'Premium', 'wp-easycart' ),
					'desc'  => __( 'Everything in Pro, plus every extension, installed and updated from your dashboard.', 'wp-easycart' ),
				);
			}
			/**
			 * Filter the Premium offer ( its mode, link and wording ).
			 *
			 * @since 6.0.2
			 * @param array  $offer  mode, url, cta, title, desc.
			 * @param string $tier   free|trial|pro|premium.
			 * @param bool   $lapsed The license is past its end date.
			 */
			return apply_filters( 'wp_easycart_admin_premium_offer', $offer, $tier, $lapsed );
		}

		/**
		 * Where a Premium button goes for this store ( see premium_offer() ). A Premium store with a current license gets
		 * the Premium product page, keyed to its license.
		 *
		 * @since 6.0.2
		 * @return string
		 */
		public static function premium_url() {
			$offer = self::premium_offer();
			if ( '' !== $offer['url'] ) {
				return $offer['url'];
			}
			$key = self::license_key();
			return 'https://www.wpeasycart.com/products/wp-easycart-premium-support-extensions/' . ( '' !== $key ? '?transaction_key=' . rawurlencode( $key ) : '' );
		}

		/**
		 * The registered license key, or ''.
		 *
		 * @since 6.0.2
		 * @return string
		 */
		private static function license_key() {
			$info = get_option( 'wp_easycart_license_info' );
			return ( is_array( $info ) && ! empty( $info['transaction_key'] ) ) ? (string) $info['transaction_key'] : '';
		}

		/**
		 * Labels for admin scripts ( localized as wp_easycart_edition ).
		 *
		 * @return array
		 */
		public static function for_js() {
			return array(
				'tier'          => self::tier(),
				'edition'       => self::edition(),
				'auto_renew'    => self::is_auto_renew(),
				'lapsed'        => self::is_lapsed(),
				'plan'          => self::plan_name(),
				'badge_pro'     => self::badge( 'pro' ),
				'badge_premium' => self::badge( 'premium' ),
				'included_pro'  => self::included_text( 'pro' ),
				'included_prem' => self::included_text( 'premium' ),
				'plugin'        => self::PLUGIN_NAME,
			);
		}
	}

endif;
