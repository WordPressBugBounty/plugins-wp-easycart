<?php
/**
 * WP EasyCart Admin Edition
 *
 * One place that knows which plan this store is on and how to name it. Pro and Premium customers
 * both run the plugin named "WP EasyCart PRO", so the admin names the customer's own plan whenever
 * the license is known, and says "Pro/Premium" only when it is not:
 *
 *   tier()          'free' | 'trial' | 'pro' | 'premium'   ( a trial is always a Pro trial )
 *   plan_name()     'Pro/Premium' on the free edition, else 'Pro' or 'Premium'
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

		/** Premium license model number. */
		const PREMIUM_MODEL = 'ec410';

		/**
		 * Cached license state for this request.
		 *
		 * @var array|null
		 */
		private static $state = null;

		/**
		 * License state: tier, lapsed, end timestamp.
		 *
		 * @return array
		 */
		public static function state() {
			if ( null !== self::$state ) {
				return self::$state;
			}
			$state = array(
				'tier'   => 'free',
				'lapsed' => false,
				'end'    => 0,
			);
			if ( function_exists( 'wp_easycart_admin_license' ) ) {
				$license = wp_easycart_admin_license();
				$data    = $license->license_data;
				if ( $license->valid_license && is_object( $data ) ) {
					$model = isset( $data->model_number ) ? strtolower( trim( (string) $data->model_number ) ) : '';
					if ( ! empty( $data->is_trial ) ) {
						$state['tier'] = 'trial';
					} elseif ( self::PREMIUM_MODEL === $model ) {
						$state['tier'] = 'premium';
					} else {
						$state['tier'] = 'pro';
					}
					$state['lapsed'] = (bool) $license->license_expired;
					$state['end']    = ! empty( $data->support_end_date ) ? (int) strtotime( $data->support_end_date ) : 0;
				}
			}
			/**
			 * Filter the license state the admin wording is based on.
			 *
			 * @since 6.0.0
			 * @param array $state tier ( free|trial|pro|premium ), lapsed ( bool ), end ( timestamp ).
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
		 * A Pro, Premium or trial license is registered on this site.
		 *
		 * @return bool
		 */
		public static function has_license() {
			return ( 'free' !== self::tier() );
		}

		/**
		 * The plan name to use in copy: 'Pro/Premium' when no license is known, else the customer's plan.
		 *
		 * @return string
		 */
		public static function plan_name() {
			switch ( self::tier() ) {
				case 'premium':
					return __( 'Premium', 'wp-easycart' );
				case 'pro':
				case 'trial':
					return __( 'Pro', 'wp-easycart' );
				default:
					return __( 'Pro/Premium', 'wp-easycart' );
			}
		}

		/**
		 * The customer's license, named: 'Pro trial', 'Pro license', 'Premium license', or '' on the free edition.
		 *
		 * @return string
		 */
		public static function license_name() {
			switch ( self::tier() ) {
				case 'premium':
					return __( 'Premium license', 'wp-easycart' );
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
					'url'   => $keyed,
					'cta'   => __( 'Renew Premium', 'wp-easycart' ),
					'title' => __( 'Renew Premium', 'wp-easycart' ),
					'desc'  => __( 'Renew to install, update and change your extensions. They keep running in the meantime.', 'wp-easycart' ),
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
