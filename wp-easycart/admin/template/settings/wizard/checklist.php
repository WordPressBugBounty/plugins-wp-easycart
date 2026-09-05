<?php
/**
 * Launch checklist partial.
 * Expects: $items ( from get_checklist() ), $context ( 'wizard' | 'status' ).
 * Include via wp_easycart_admin_setup_wizard()->render_checklist( $context ).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$visible = array();
$done_n  = 0;
foreach ( $items as $it ) {
	if ( $it['dismissed'] ) {
		continue;
	}
	$visible[] = $it;
	if ( $it['done'] ) {
		$done_n++;
	}
}
$total = count( $visible );
$pct   = $total ? round( $done_n / $total * 100 ) : 100;
?>
<div class="ecwz-chk" id="ecwz_checklist" data-context="<?php echo esc_attr( $context ); ?>">
	<div class="ecwz-chk-head">
		<b><?php esc_html_e( 'Launch checklist', 'wp-easycart' ); ?></b>
		<div class="ecwz-progress"><i style="width:<?php echo esc_attr( $pct ); ?>%"></i></div>
		<small><?php echo esc_html( sprintf( __( '%1$d of %2$d', 'wp-easycart' ), $done_n, $total ) ); ?></small>
	</div>
	<?php foreach ( $visible as $it ) {
		$cls = 'ecwz-ci' . ( $it['done'] ? ' is-done' : '' ) . ( ! empty( $it['featured'] ) && ! $it['done'] ? ' is-feat' : '' );
	?>
	<div class="<?php echo esc_attr( $cls ); ?>" data-ci="<?php echo esc_attr( $it['key'] ); ?>">
		<span class="ecwz-ck" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg></span>
		<span class="ecwz-ci-t"><?php echo esc_html( $it['label'] ); ?><small><?php echo esc_html( $it['sub'] ); ?></small></span>
		<?php if ( ! $it['done'] ) { ?>
			<?php if ( ! empty( $it['action_js'] ) ) { ?>
			<a class="ecwz-btn ecwz-btn-sm<?php echo ! empty( $it['featured'] ) ? ' ecwz-btn-primary' : ''; ?>" href="<?php echo esc_url( $it['action_url'] ); ?>" onclick="<?php echo esc_attr( $it['action_js'] ); ?>"><?php echo esc_html( $it['action_label'] ); ?></a>
			<?php } else { ?>
			<a class="ecwz-btn ecwz-btn-sm<?php echo ! empty( $it['featured'] ) ? ' ecwz-btn-primary' : ''; ?>" href="<?php echo esc_url( $it['action_url'] ); ?>"<?php if ( ! empty( $it['target'] ) ) { echo ' target="' . esc_attr( $it['target'] ) . '" rel="noopener noreferrer"'; } ?>><?php echo esc_html( $it['action_label'] ); ?></a>
			<?php } ?>
			<?php if ( ! empty( $it['optional'] ) ) { ?>
			<button type="button" class="ecwz-btn ecwz-btn-sm ecwz-btn-ghost ecwz-dismiss" title="<?php esc_attr_e( 'Hide this item', 'wp-easycart' ); ?>" onclick="wpEasyCartWizard.dismissItem( this, '<?php echo esc_js( $it['key'] ); ?>' ); return false;">&times;</button>
			<?php } ?>
		<?php } ?>
	</div>
	<?php } ?>
	<div class="ecwz-chk-foot">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
		<?php if ( 'status' === $context ) { ?>
		<?php echo sprintf( esc_html__( 'From the %s. Items disappear as you complete them.', 'wp-easycart' ), '<a href="admin.php?page=wp-easycart-settings&subpage=setup-wizard&step=5">' . esc_html__( 'setup wizard', 'wp-easycart' ) . '</a>' ); ?>
		<?php } else { ?>
		<?php esc_html_e( 'Also shown on Store Status with a count in the sidebar until complete.', 'wp-easycart' ); ?>
		<?php } ?>
	</div>
</div>
