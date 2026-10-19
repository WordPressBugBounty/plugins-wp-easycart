<?php
/**
 * Setup wizard stepper. Steps already completed become checkmarks and stay clickable.
 * 6.0.5: data-ecwz-goto lets setup-wizard-v2.js offer to save the step's changes first; on phones only the current step's name shows.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$wizard    = wp_easycart_admin_setup_wizard();
$steps     = $wizard->get_steps();
$completed = $wizard->completed_through();
$is_done   = ( $wizard->step == wp_easycart_admin_setup_wizard::STEP_DONE );
?>
<ol class="ecwz-steps" aria-label="<?php esc_attr_e( 'Setup steps', 'wp-easycart' ); ?>">
	<?php foreach ( $steps as $n => $s ) {
		$current = ( ! $is_done && $n == $wizard->step );
		$done    = ( $is_done || ( $n <= $completed && ! $current ) );
		$cls     = 'ecwz-step' . ( $current ? ' is-current' : '' ) . ( $done ? ' is-done' : '' );
	?>
	<li class="<?php echo esc_attr( $cls ); ?>">
		<a href="<?php echo esc_url( $wizard->step_url( $n ) ); ?>"<?php echo $current ? ' aria-current="step"' : ' data-ecwz-goto="' . esc_attr( $n ) . '"'; ?>>
			<span class="ecwz-step-num" aria-hidden="true"><?php echo esc_html( $n ); ?></span>
			<strong><?php echo esc_html( $s['label'] ); ?></strong>
			<small><?php echo $done ? esc_html__( 'Completed', 'wp-easycart' ) : esc_html( $s['sub'] ); ?></small>
		</a>
	</li>
	<?php } ?>
</ol>
