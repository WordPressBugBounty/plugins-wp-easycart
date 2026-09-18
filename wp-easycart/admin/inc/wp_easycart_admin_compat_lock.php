<?php
/**
 * WP EasyCart Admin Compatibility Lock
 *
 * Replaces every WP EasyCart admin screen with one "update or deactivate" page while an active
 * companion plugin is too old ( or too new ) for this release. A mismatched pair cannot be made safe
 * from inside the shell: an older PRO adds hooks the moment it loads ( nav promos, store status
 * bubbles, message boxes ) that call admin controllers a newer free plugin no longer ships, so the
 * shell is never rendered while a requirement is unmet.
 *
 * Reusable for later releases: add a requirement to requirements(), or from another plugin through
 * the wp_easycart_admin_compat_requirements filter ( a newer PRO can declare the free version it
 * needs, and this release then asks for the free update instead ). Each requirement:
 *
 *   'name'               Plugin name shown to the merchant.
 *   'basename'           Plugin whose version is checked, e.g. 'wp-easycart-pro/wp-easycart-admin-pro.php'.
 *   'min_version'        Lowest version that works with this release.
 *   'version_constant'   Optional. Runtime constant that wins over the file header when defined.
 *   'status'             Optional callable returning array( 'active' => bool, 'version' => string ),
 *                        used instead of the plugin lookup.
 *   'deactivate'         Optional. Basename offered for deactivation ( default: basename ). Use the
 *                        companion's basename when the checked plugin is this free plugin itself.
 *   'deactivate_name'    Optional. Name of the plugin offered for deactivation ( default: name ).
 *   'deactivate_effect'  Optional. One sentence on what the store loses while it is deactivated.
 *   'note'               Optional. One sentence shown under the version message.
 *   'download_url'       Optional. Where to download the release manually.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_compat_lock' ) ) :

	class wp_easycart_admin_compat_lock {

		/**
		 * Cached unmet requirements for this request.
		 *
		 * @var array|null
		 */
		private static $conflicts = null;

		/**
		 * Hook the WordPress-wide notice. The EasyCart screens render the full page instead
		 * ( see wp_easycart_admin::load_admin_shell() ).
		 */
		public static function init() {
			add_action( 'admin_notices', array( __CLASS__, 'print_admin_notice' ) );
			add_action( 'network_admin_notices', array( __CLASS__, 'print_admin_notice' ) );
		}

		/**
		 * Every declared requirement, keyed by id.
		 *
		 * @return array
		 */
		public static function requirements() {
			$requirements = array(
				'pro' => array(
					'name'              => 'WP EasyCart PRO',
					'basename'          => wp_easycart_admin_pro_gate::PRO_BASENAME,
					'min_version'       => wp_easycart_admin_pro_gate::MIN_PRO_VERSION,
					'status'            => array( __CLASS__, 'pro_status' ),
					'deactivate_effect' => self::pro_deactivate_effect(),
					'note'              => self::pro_note(),
					'download_url'      => 'https://www.wpeasycart.com/my-account/',
				),
			);

			/**
			 * Filter the plugin versions this release needs before its admin can load.
			 *
			 * @since 6.0.0
			 * @param array $requirements See the class docblock for the keys.
			 */
			$requirements = apply_filters( 'wp_easycart_admin_compat_requirements', $requirements );
			return is_array( $requirements ) ? $requirements : array();
		}

		/**
		 * What deactivating WP EasyCart PRO costs, named for the store's plan.
		 *
		 * @return string
		 */
		private static function pro_deactivate_effect() {
			$tier = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::tier() : 'free';
			if ( 'premium' === $tier ) {
				return __( 'Your store keeps running on WP EasyCart, but your Premium features and extensions are off until WP EasyCart PRO is updated and activated again.', 'wp-easycart' );
			}
			if ( 'free' === $tier ) {
				return __( 'Your store keeps running on WP EasyCart, but Pro and Premium features such as extra payment gateways, live shipping rates, subscriptions and offers are off until WP EasyCart PRO is updated and activated again.', 'wp-easycart' );
			}
			return __( 'Your store keeps running on WP EasyCart, but Pro features such as extra payment gateways, live shipping rates, subscriptions and offers are off until WP EasyCart PRO is updated and activated again.', 'wp-easycart' );
		}

		/**
		 * Which license the PRO plugin runs on this store.
		 *
		 * @return string
		 */
		private static function pro_note() {
			$license = class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::license_name() : '';
			if ( '' === $license ) {
				return __( 'WP EasyCart PRO is the plugin that runs Pro and Premium licenses.', 'wp-easycart' );
			}
			/* translators: %s: the store's license, e.g. "Premium license". */
			return sprintf( __( 'WP EasyCart PRO is the plugin that runs your %s.', 'wp-easycart' ), $license );
		}

		/**
		 * PRO status from the PRO gate, so both agree on what "outdated" means.
		 *
		 * @return array
		 */
		public static function pro_status() {
			$status = wp_easycart_admin_pro_gate::pro_status();
			return array(
				'active'  => $status['active'],
				'version' => $status['version'],
			);
		}

		/**
		 * Requirements that are not met, each with the resolved 'version'.
		 *
		 * @return array
		 */
		public static function conflicts() {
			if ( null !== self::$conflicts ) {
				return self::$conflicts;
			}
			self::$conflicts = array();

			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			foreach ( self::requirements() as $id => $requirement ) {
				if ( ! is_array( $requirement ) || empty( $requirement['basename'] ) || empty( $requirement['min_version'] ) ) {
					continue;
				}
				$requirement = wp_parse_args(
					$requirement,
					array(
						'name'              => $requirement['basename'],
						'version_constant'  => '',
						'status'            => null,
						'deactivate'        => $requirement['basename'],
						'deactivate_name'   => '',
						'deactivate_effect' => '',
						'note'              => '',
						'download_url'      => '',
					)
				);
				if ( '' === $requirement['deactivate_name'] ) {
					$requirement['deactivate_name'] = $requirement['name'];
				}

				$status = is_callable( $requirement['status'] ) ? call_user_func( $requirement['status'] ) : self::plugin_status( $requirement );
				if ( empty( $status['active'] ) ) {
					continue;
				}
				$version = isset( $status['version'] ) ? (string) $status['version'] : '';
				if ( '' !== $version && version_compare( $version, $requirement['min_version'], '>=' ) ) {
					continue;
				}

				$requirement['id']      = (string) $id;
				$requirement['version'] = $version;
				self::$conflicts[]      = $requirement;
			}

			return self::$conflicts;
		}

		/**
		 * Active state and version of a plugin by basename.
		 *
		 * @param array $requirement Parsed requirement.
		 * @return array
		 */
		private static function plugin_status( $requirement ) {
			$active  = is_plugin_active( $requirement['basename'] );
			$version = '';
			if ( $active && '' !== $requirement['version_constant'] && defined( $requirement['version_constant'] ) ) {
				$version = (string) constant( $requirement['version_constant'] );
			}
			if ( $active && '' === $version ) {
				$file = WP_PLUGIN_DIR . '/' . $requirement['basename'];
				if ( file_exists( $file ) ) {
					$header  = get_file_data( $file, array( 'Version' => 'Version' ) );
					$version = isset( $header['Version'] ) ? (string) $header['Version'] : '';
				}
			}
			return array(
				'active'  => $active,
				'version' => $version,
			);
		}

		/**
		 * Whether the EasyCart admin is replaced by the lock page on this request.
		 *
		 * @return bool
		 */
		public static function is_locked() {
			/**
			 * Filter whether the admin lock applies ( support can force it off while debugging ).
			 *
			 * @since 6.0.0
			 * @param bool  $locked    True when a requirement is unmet.
			 * @param array $conflicts Unmet requirements.
			 */
			return (bool) apply_filters( 'wp_easycart_admin_compat_locked', count( self::conflicts() ) > 0, self::conflicts() );
		}

		/**
		 * URL of the lock page ( any EasyCart screen shows it ).
		 *
		 * @return string
		 */
		public static function page_url() {
			return admin_url( 'admin.php?page=wp-easycart-dashboard' );
		}

		/**
		 * One-click update link when WordPress already knows about the update, else ''.
		 *
		 * @param string $basename Plugin basename.
		 * @return string
		 */
		private static function update_url( $basename ) {
			if ( ! current_user_can( 'update_plugins' ) ) {
				return '';
			}
			$updates = get_site_transient( 'update_plugins' );
			if ( ! is_object( $updates ) || empty( $updates->response ) || ! isset( $updates->response[ $basename ] ) ) {
				return '';
			}
			return wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $basename ) ), 'upgrade-plugin_' . $basename );
		}

		/**
		 * Deactivation link for the current user, else ''.
		 *
		 * @param string $basename Plugin basename.
		 * @return string
		 */
		private static function deactivate_url( $basename ) {
			if ( ! current_user_can( 'deactivate_plugin', $basename ) ) {
				return '';
			}
			$network = is_multisite() && is_plugin_active_for_network( $basename );
			if ( $network && ! current_user_can( 'manage_network_plugins' ) ) {
				return '';
			}
			$base = $network ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
			$url  = add_query_arg(
				array(
					'action' => 'deactivate',
					'plugin' => rawurlencode( $basename ),
				),
				$base
			);
			return wp_nonce_url( $url, 'deactivate-plugin_' . $basename );
		}

		/**
		 * Plugins screen for the update and the manual upload.
		 *
		 * @return string
		 */
		private static function plugins_url() {
			return self_admin_url( 'plugins.php?plugin_status=upgrade' );
		}

		/**
		 * The sentence describing one conflict.
		 *
		 * @param array $conflict Unmet requirement.
		 * @return string
		 */
		private static function conflict_message( $conflict ) {
			if ( '' === $conflict['version'] ) {
				/* translators: 1: plugin name, 2: required version. */
				return sprintf( __( 'This version of WP EasyCart needs %1$s %2$s or newer, and the active copy could not report its version.', 'wp-easycart' ), $conflict['name'], $conflict['min_version'] );
			}
			/* translators: 1: plugin name, 2: installed version, 3: required version. */
			return sprintf( __( '%1$s %2$s is active, but this version of WP EasyCart needs %1$s %3$s or newer.', 'wp-easycart' ), $conflict['name'], $conflict['version'], $conflict['min_version'] );
		}

		/**
		 * Notice on every other admin screen; the EasyCart screens show the full page.
		 */
		public static function print_admin_notice() {
			if ( ! self::is_locked() || ! ( current_user_can( 'activate_plugins' ) || current_user_can( 'wpec_manager' ) ) ) {
				return;
			}
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.
			if ( 0 === strpos( $page, 'wp-easycart' ) || 'ec_adminv2' === $page ) {
				return;
			}
			foreach ( self::conflicts() as $conflict ) {
				echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'The WP EasyCart admin is paused.', 'wp-easycart' ) . '</strong> ' . esc_html( self::conflict_message( $conflict ) ) . ' <a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Fix this now', 'wp-easycart' ) . '</a></p></div>';
			}
		}

		/**
		 * The page shown in place of every EasyCart admin screen.
		 */
		public static function render_page() {
			$conflicts = self::conflicts();
			if ( function_exists( 'wp_easycart_shell_output_root_css' ) ) {
				wp_easycart_shell_output_root_css();
			}
			?>
			<style>
				.ec-compat-lock { max-width: 760px; margin: 48px auto; padding: 0 16px; box-sizing: border-box; font-size: 14px; color: #1f2937; }
				.ec-compat-lock * { box-sizing: border-box; }
				.ec-compat-lock-card { background: #fff; border: 1px solid #e5e7eb; border-top: 4px solid var(--ec-brand, #2271b1); border-radius: 10px; box-shadow: 0 10px 30px rgba(17, 24, 39, 0.08); padding: 32px; }
				.ec-compat-lock-icon { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; background: var(--ec-brand-soft, #f0f6fc); color: var(--ec-brand-deep, #135e96); margin-bottom: 12px; }
				.ec-compat-lock-icon .dashicons { font-size: 24px; width: 24px; height: 24px; }
				.ec-compat-lock h1 { font-size: 22px; line-height: 1.3; margin: 0 0 8px; padding: 0; }
				.ec-compat-lock-lead { font-size: 15px; color: #4b5563; margin: 0 0 24px; }
				.ec-compat-lock-item { border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-top: 16px; }
				.ec-compat-lock-item h2 { font-size: 16px; margin: 0 0 6px; }
				.ec-compat-lock-item > p { margin: 0 0 16px; color: #4b5563; }
				.ec-compat-lock-options { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
				.ec-compat-lock-option { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
				.ec-compat-lock-option h3 { font-size: 14px; margin: 0; }
				.ec-compat-lock-option p { margin: 0; color: #4b5563; flex: 1; }
				.ec-compat-lock-option .button { align-self: flex-start; }
				.ec-compat-lock-option .button-primary { background: var(--ec-brand, #2271b1); border-color: var(--ec-brand, #2271b1); color: var(--ec-on-brand, #fff); }
				.ec-compat-lock-option .button-primary:hover, .ec-compat-lock-option .button-primary:focus { background: var(--ec-brand-hover, #135e96); border-color: var(--ec-brand-hover, #135e96); color: var(--ec-on-brand, #fff); }
				.ec-compat-lock-links { font-size: 13px; }
				.ec-compat-lock-foot { margin: 20px 0 0; font-size: 13px; color: #6b7280; }
				@media (max-width: 640px) { .ec-compat-lock { margin: 24px auto; } .ec-compat-lock-card { padding: 20px; } .ec-compat-lock-options { grid-template-columns: 1fr; } }
			</style>
			<div class="wrap ec-compat-lock">
				<div class="ec-compat-lock-card">
					<span class="ec-compat-lock-icon"><span class="dashicons dashicons-update" aria-hidden="true"></span></span>
					<h1><?php esc_html_e( 'One more update to finish', 'wp-easycart' ); ?></h1>
					<p class="ec-compat-lock-lead"><?php esc_html_e( 'WP EasyCart was updated, but a plugin that works with it is on a version that does not match. The WP EasyCart admin is paused until they match, so nothing breaks while you manage your store.', 'wp-easycart' ); ?></p>
					<?php
					foreach ( $conflicts as $conflict ) {
						$update_url     = self::update_url( $conflict['basename'] );
						$deactivate_url = self::deactivate_url( $conflict['deactivate'] );
						?>
						<div class="ec-compat-lock-item">
							<h2><?php echo esc_html( $conflict['name'] ); ?></h2>
							<p><?php echo esc_html( self::conflict_message( $conflict ) ); ?><?php echo '' !== $conflict['note'] ? ' ' . esc_html( $conflict['note'] ) : ''; ?></p>
							<div class="ec-compat-lock-options">
								<div class="ec-compat-lock-option">
									<?php /* translators: %s: plugin name. */ ?>
									<h3><?php echo esc_html( sprintf( __( 'Update %s (recommended)', 'wp-easycart' ), $conflict['name'] ) ); ?></h3>
									<?php if ( '' !== $update_url ) { ?>
										<?php /* translators: %s: required version. */ ?>
										<p><?php echo esc_html( sprintf( __( 'An update is ready. Install version %s or newer and everything comes back as it was.', 'wp-easycart' ), $conflict['min_version'] ) ); ?></p>
										<a class="button button-primary" href="<?php echo esc_url( $update_url ); ?>"><?php esc_html_e( 'Update now', 'wp-easycart' ); ?></a>
									<?php } elseif ( current_user_can( 'update_plugins' ) ) { ?>
										<?php /* translators: %s: required version. */ ?>
										<p><?php echo esc_html( sprintf( __( 'Install version %s or newer from the Plugins screen. If no update is listed, download the latest release from your account and upload it under Plugins > Add New.', 'wp-easycart' ), $conflict['min_version'] ) ); ?></p>
										<span class="ec-compat-lock-links">
											<a class="button button-primary" href="<?php echo esc_url( self::plugins_url() ); ?>"><?php esc_html_e( 'Go to Plugins', 'wp-easycart' ); ?></a>
											<?php if ( '' !== $conflict['download_url'] ) { ?>
												<a class="button" href="<?php echo esc_url( $conflict['download_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download from my account', 'wp-easycart' ); ?></a>
											<?php } ?>
										</span>
									<?php } else { ?>
										<p><?php esc_html_e( 'Your account cannot update plugins. Ask a site administrator to install the update.', 'wp-easycart' ); ?></p>
									<?php } ?>
								</div>
								<div class="ec-compat-lock-option">
									<?php /* translators: %s: plugin name. */ ?>
									<h3><?php echo esc_html( sprintf( __( 'Deactivate %s for now', 'wp-easycart' ), $conflict['deactivate_name'] ) ); ?></h3>
									<p><?php echo esc_html( '' !== $conflict['deactivate_effect'] ? $conflict['deactivate_effect'] : __( 'The WP EasyCart admin opens again right away. Reactivate the plugin once it is updated.', 'wp-easycart' ) ); ?></p>
									<?php if ( '' !== $deactivate_url ) { ?>
										<a class="button" href="<?php echo esc_url( $deactivate_url ); ?>"><?php esc_html_e( 'Deactivate', 'wp-easycart' ); ?></a>
									<?php } else { ?>
										<p><?php esc_html_e( 'Your account cannot deactivate plugins. Ask a site administrator.', 'wp-easycart' ); ?></p>
									<?php } ?>
								</div>
							</div>
						</div>
						<?php
					}
					?>
					<p class="ec-compat-lock-foot"><?php esc_html_e( 'After updating or deactivating, reopen WP EasyCart from the admin menu.', 'wp-easycart' ); ?></p>
				</div>
			</div>
			<?php
		}
	}

	wp_easycart_admin_compat_lock::init();

endif;
