<?php
/**
 * Setup wizard shell — header, stepper, content card + summary rail.
 * Rendered inside the v2 admin shell's content area.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$wizard  = wp_easycart_admin_setup_wizard();
$is_done = ( $wizard->step == wp_easycart_admin_setup_wizard::STEP_DONE );
?>
<div class="ecwz" id="ecwz" data-step="<?php echo esc_attr( $wizard->step ); ?>">

	<div class="ecwz-head">
		<div>
			<?php if ( $is_done ) { ?>
			<h1><?php esc_html_e( 'Store setup', 'wp-easycart' ); ?></h1>
			<p><?php esc_html_e( 'Setup is complete. Finish the launch checklist below when you are ready.', 'wp-easycart' ); ?></p>
			<?php } else { ?>
			<h1><?php esc_html_e( 'Set up your store', 'wp-easycart' ); ?></h1>
			<p><?php esc_html_e( 'Four short steps, about five minutes. Recommended technical settings are already applied; everything here can be changed later in Settings.', 'wp-easycart' ); ?></p>
			<?php } ?>
		</div>
		<?php if ( ! $is_done ) { ?>
		<a class="ecwz-skip" href="<?php echo esc_url( $wizard->skip_url() ); ?>" title="<?php esc_attr_e( 'Skips the questions. Recommended settings stay applied and your store pages stay in place.', 'wp-easycart' ); ?>">
			<?php esc_html_e( 'Skip setup', 'wp-easycart' ); ?>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
		</a>
		<?php } ?>
	</div>

	<?php do_action( 'wp_easycart_admin_wizard_navigation' ); ?>

	<div class="ecwz-grid<?php if ( $is_done ) { echo ' ecwz-grid-single'; } ?>">
		<section class="ecwz-card">
			<?php do_action( 'wp_easycart_admin_wizard_content' ); ?>
		</section>

		<?php if ( ! $is_done ) { ?>
		<aside class="ecwz-rail">
			<?php include( EC_PLUGIN_DIRECTORY . '/admin/template/settings/wizard/summary.php' ); ?>
			<div class="ecwz-rail-box">
				<h3><?php esc_html_e( 'Need a hand?', 'wp-easycart' ); ?></h3>
				<div class="ecwz-rail-help">
					<a href="https://support.wpeasycart.com/video-tutorials/" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9l5 3-5 3z"/></svg><?php esc_html_e( 'Watch the setup video', 'wp-easycart' ); ?></a>
					<a href="https://docs.wpeasycart.com/wp-easycart-administrative-console-guide/" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h12l4 4v12H4z M8 12h8M8 16h8"/></svg><?php esc_html_e( 'Read the setup guide', 'wp-easycart' ); ?></a>
					<a href="https://www.wpeasycart.com/contact-information/" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 01-11.6 7.2L4 21l1.8-5.4A8 8 0 1121 12z"/></svg><?php esc_html_e( 'Contact support', 'wp-easycart' ); ?></a>
				</div>
			</div>
		</aside>
		<?php } ?>
	</div>
</div>
