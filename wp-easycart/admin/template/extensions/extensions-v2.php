<?php
/**
 * EasyCart › Extensions: the catalog ( wp_easycart_admin_extensions::render_page() ).
 *
 * Cards come from wp_easycart_admin_extensions::card(), which WP EasyCart Premium fills in through
 * wp_easycart_extension_card. The category pills and the search box filter in the browser ( admin/js/extensions-v2.js );
 * a #category in the URL opens that category.
 *
 * @since 6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecext_catalog    = wp_easycart_admin_extensions::catalog();
$ecext_categories = wp_easycart_admin_extensions::categories();
$ecext_cards      = array();
$ecext_counts     = array(
	'all'       => 0,
	'installed' => 0,
);
foreach ( $ecext_catalog as $ecext_slug => $ecext_entry ) {
	$ecext_basename = wp_easycart_admin_extensions::installed( $ecext_slug );
	/* A retiring or retired extension is only listed where it is still installed; a coming one ( 6.0.2 ) always is. */
	if ( ! in_array( $ecext_entry['status'], array( 'available', 'coming' ), true ) && '' === $ecext_basename ) {
		continue;
	}
	$ecext_cards[ $ecext_slug ] = array(
		'ext'       => $ecext_entry,
		'card'      => wp_easycart_admin_extensions::card( $ecext_entry ),
		'installed' => ( '' !== $ecext_basename ),
	);
	$ecext_counts['all']++;
	$ecext_counts[ $ecext_entry['category'] ] = isset( $ecext_counts[ $ecext_entry['category'] ] ) ? $ecext_counts[ $ecext_entry['category'] ] + 1 : 1;
	if ( '' !== $ecext_basename ) {
		$ecext_counts['installed']++;
	}
}
/**
 * Header buttons on the Extensions page ( WP EasyCart Premium: Check for updates, Update all ).
 *
 * @since 6.0.2
 * @param string $html Buttons HTML.
 */
$ecext_actions = (string) apply_filters( 'wp_easycart_extensions_page_actions', '' );
/* 6.0.2: Refresh asks again for what this page keeps ( the license, WP EasyCart Premium's one-click install ), unless
   WP EasyCart Premium's own Check for updates is in the header. */
$ecext_refresh = ( '' === trim( $ecext_actions ) ) ? wp_easycart_admin_extensions::refresh_url() : '';
?>
<div class="ecv2-wrap ecext" id="ecext">

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-admin-plugins ecv2-page-header-icon" aria-hidden="true"></span>
			<div class="ecv2-page-header-text">
				<h1 class="ecv2-page-title"><?php esc_html_e( 'Extensions', 'wp-easycart' ); ?></h1>
				<p class="ecv2-page-subline"><?php esc_html_e( 'Connect EasyCart to the services you already use. Every extension is included with a Premium license.', 'wp-easycart' ); ?></p>
			</div>
		</div>
		<div class="ecv2-page-header-right">
			<?php echo $ecext_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from WP EasyCart Premium through wp_easycart_extensions_page_actions. ?>
			<?php if ( '' !== $ecext_refresh ) : ?>
				<a class="ecv2-btn ecv2-btn-ghost ecext-refresh" href="<?php echo esc_url( $ecext_refresh ); ?>" title="<?php esc_attr_e( 'Check your license and WP EasyCart Premium again', 'wp-easycart' ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'Refresh', 'wp-easycart' ); ?></a>
			<?php endif; ?>
			<a class="ecv2-btn ecv2-btn-ghost" href="<?php echo esc_url( wp_easycart_admin_extensions::DOCS ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Docs', 'wp-easycart' ); ?> <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
		</div>
	</div>

	<?php echo wp_easycart_admin_extensions::banner_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts, or WP EasyCart Premium's HTML. ?>

	<?php
	/**
	 * Notices above the extensions catalog.
	 *
	 * @since 6.0.2
	 */
	do_action( 'wp_easycart_extensions_page_notice' );
	?>

	<div class="ecext-tools">
		<div class="ecext-pills" role="group" aria-label="<?php esc_attr_e( 'Show extensions', 'wp-easycart' ); ?>">
			<button type="button" class="ecext-pill" data-filter="all" aria-pressed="true"><?php esc_html_e( 'All', 'wp-easycart' ); ?> <i><?php echo (int) $ecext_counts['all']; ?></i></button>
			<?php if ( $ecext_counts['installed'] ) : ?>
				<button type="button" class="ecext-pill" data-filter="installed" aria-pressed="false"><?php esc_html_e( 'Installed', 'wp-easycart' ); ?> <i><?php echo (int) $ecext_counts['installed']; ?></i></button>
			<?php endif; ?>
			<?php foreach ( $ecext_categories as $ecext_cat => $ecext_cat_label ) : ?>
				<?php if ( empty( $ecext_counts[ $ecext_cat ] ) ) { continue; } ?>
				<button type="button" class="ecext-pill" data-filter="<?php echo esc_attr( $ecext_cat ); ?>" aria-pressed="false"><?php echo esc_html( $ecext_cat_label ); ?> <i><?php echo (int) $ecext_counts[ $ecext_cat ]; ?></i></button>
			<?php endforeach; ?>
		</div>
		<label class="ecext-search">
			<span class="screen-reader-text"><?php esc_html_e( 'Search extensions', 'wp-easycart' ); ?></span>
			<span class="dashicons dashicons-search" aria-hidden="true"></span>
			<input type="search" id="ecext_search" placeholder="<?php esc_attr_e( 'Search extensions', 'wp-easycart' ); ?>" autocomplete="off" />
		</label>
	</div>

	<div class="ecext-grid" id="ecext_grid">
		<?php foreach ( $ecext_cards as $ecext_slug => $ecext_row ) :
			$ecext_entry = $ecext_row['ext'];
			$ecext_card  = $ecext_row['card'];
			$ecext_cat   = isset( $ecext_categories[ $ecext_entry['category'] ] ) ? $ecext_categories[ $ecext_entry['category'] ] : '';
			?>
			<div class="ecext-card is-<?php echo esc_attr( $ecext_card['state'] ); ?>" id="ecext-<?php echo esc_attr( $ecext_slug ); ?>" data-ext="<?php echo esc_attr( $ecext_slug ); ?>" data-category="<?php echo esc_attr( $ecext_entry['category'] ); ?>" data-installed="<?php echo $ecext_row['installed'] ? '1' : '0'; ?>" data-search="<?php echo esc_attr( strtolower( $ecext_entry['name'] . ' ' . $ecext_entry['summary'] . ' ' . $ecext_cat ) ); ?>">
				<div class="ecext-card-top">
					<?php echo wp_easycart_admin_extensions::logo_html( $ecext_entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- logo_html() escapes its parts. ?>
					<div class="ecext-card-name">
						<b><?php echo esc_html( $ecext_entry['name'] ); ?></b>
						<small><?php echo esc_html( $ecext_cat ); ?></small>
					</div>
					<?php /* 6.0.2: chips stack in their own column, so "Premium" + "Update to X" never run over the name. */ ?>
					<div class="ecext-card-chips"><?php echo wp_kses_post( $ecext_card['chip'] ); ?></div>
				</div>
				<p class="ecext-card-desc"><?php echo esc_html( $ecext_entry['summary'] ); ?></p>
				<?php if ( ! empty( $ecext_entry['requires']['plugin'] ) ) : ?>
					<?php /* translators: %s: another plugin's name, e.g. AffiliateWP. */ ?>
					<p class="ecext-card-note"><?php echo esc_html( sprintf( __( 'Works alongside your own %s plugin.', 'wp-easycart' ), $ecext_entry['requires']['plugin'] ) ); ?></p>
				<?php elseif ( ! empty( $ecext_entry['requires']['pro'] ) ) : ?>
					<p class="ecext-card-note"><?php esc_html_e( 'Needs WP EasyCart PRO for live shipping rates.', 'wp-easycart' ); ?></p>
				<?php endif; ?>
				<div class="ecext-card-foot">
					<span class="ecext-card-status"><?php echo wp_kses_post( $ecext_card['status'] ); ?></span>
					<span class="ecext-card-actions">
						<?php foreach ( $ecext_card['actions'] as $ecext_action ) : ?>
							<?php echo wp_easycart_admin_extensions::action_html( $ecext_action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- action_html() escapes every part. ?>
						<?php endforeach; ?>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<p class="ecext-empty" id="ecext_empty" hidden><?php esc_html_e( 'No extension matches. Try another word, or show all extensions.', 'wp-easycart' ); ?></p>

</div>
