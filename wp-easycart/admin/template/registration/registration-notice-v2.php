<?php
/**
 * Registration — the result of the last license action ( activate, deactivate ), V2.
 *
 * Included by registration-status-v2.php, registration-expired-v2.php and trial-v2.php. The $_GET codes are the ones
 * WP EasyCart PRO's license forms redirect with. With PRO 6.0.2, reason=refused means the licensing server answered and did
 * not accept the key ( a mistyped key, or one in use on another site ); without it the server could not be reached.
 *
 * @package wp-easycart
 * @since 6.0.2 Before, only the licensed screen printed the result, and every refusal read "try again in a few minutes".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only result codes after PRO's nonce-checked license forms.
$wp_easycart_regn_refused = isset( $_GET['reason'] ) && 'refused' === sanitize_key( wp_unslash( $_GET['reason'] ) );
$wp_easycart_regn_codes   = array(
	'success' => array(
		/* translators: %s: plan name, Pro or Premium. */
		'activate-complete'   => sprintf( __( 'License activated. The %s sections are now open.', 'wp-easycart' ), class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::plan_name() : __( 'Pro', 'wp-easycart' ) ),
		'deactivate-complete' => __( 'License deactivated. You can use the key on another site now.', 'wp-easycart' ),
	),
	'error'   => array(
		'activate-no-key-found'          => __( 'No license was found for that key. Check the value and try again.', 'wp-easycart' ),
		'activate-registration-failed'   => $wp_easycart_regn_refused ? __( 'That license key could not be activated. Check it against the key in your account, and if it is in use on another site, deactivate it there first.', 'wp-easycart' ) : __( 'Activation failed. Please try again in a few minutes.', 'wp-easycart' ),
		'activate-failed'                => $wp_easycart_regn_refused ? __( 'That license key could not be activated. Check it against the key in your account, and if it is in use on another site, deactivate it there first.', 'wp-easycart' ) : __( 'Activation failed. Please try again in a few minutes.', 'wp-easycart' ),
		'license-form-expired'           => __( 'That form had expired. Reload the page and try again.', 'wp-easycart' ),
		'deactivate-no-key-found'        => __( 'No license was found for that key. Check the value and try again.', 'wp-easycart' ),
		'deactivate-registration-failed' => $wp_easycart_regn_refused ? __( 'That key could not be deactivated. Enter the license key shown in this site\'s license details.', 'wp-easycart' ) : __( 'Deactivation failed. Please try again in a few minutes.', 'wp-easycart' ),
	),
);
/* A key the server did not know or accept: point to where the right key is. */
$wp_easycart_regn_key_help = array( 'activate-no-key-found', 'activate-registration-failed', 'activate-failed' );
$wp_easycart_regn_flash    = null;
foreach ( $wp_easycart_regn_codes as $wp_easycart_regn_kind => $wp_easycart_regn_map ) {
	$wp_easycart_regn_code = isset( $_GET[ $wp_easycart_regn_kind ] ) ? sanitize_key( wp_unslash( $_GET[ $wp_easycart_regn_kind ] ) ) : '';
	if ( '' !== $wp_easycart_regn_code && isset( $wp_easycart_regn_map[ $wp_easycart_regn_code ] ) ) {
		$wp_easycart_regn_flash = array(
			'kind'    => $wp_easycart_regn_kind,
			'message' => $wp_easycart_regn_map[ $wp_easycart_regn_code ],
			'account' => 'error' === $wp_easycart_regn_kind && in_array( $wp_easycart_regn_code, $wp_easycart_regn_key_help, true ) && ( 'activate-no-key-found' === $wp_easycart_regn_code || $wp_easycart_regn_refused ),
		);
	}
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( $wp_easycart_regn_flash ) :
	?>
	<div class="ecreg-notice <?php echo 'success' === $wp_easycart_regn_flash['kind'] ? 'is-ok' : 'is-ended'; ?>" role="<?php echo 'success' === $wp_easycart_regn_flash['kind'] ? 'status' : 'alert'; ?>">
		<span class="dashicons <?php echo 'success' === $wp_easycart_regn_flash['kind'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
		<span><?php echo esc_html( $wp_easycart_regn_flash['message'] ); ?></span>
		<?php if ( $wp_easycart_regn_flash['account'] ) : ?>
		<a class="ecv2-btn ecv2-btn-sm" href="https://www.wpeasycart.com/my-account/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Find your key', 'wp-easycart' ); ?></a>
		<?php endif; ?>
	</div>
	<?php
endif;
