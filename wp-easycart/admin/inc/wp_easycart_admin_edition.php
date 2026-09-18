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
