<?php
/**
 * Marketing > Cart Links — list + builder drawer. $this is
 * wp_easycart_admin_cart_links (FREE). PRO extends via the documented hooks.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 25 links per page; summaries for the page come from one batched query. @since 6.0.0 */
$cart_links = $this->get_links( isset( $_GET['pagenum'] ) ? (int) $_GET['pagenum'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number for a list view.

/* Post-save confirmation ( set by cart-links.js after a save reload ). */
$ecv2_cl_saved_link = false;
$ecv2_cl_saved_mode = '';
if ( isset( $_GET['cl_saved'] ) || isset( $_GET['cl_updated'] ) ) {
	$ecv2_cl_saved_mode = isset( $_GET['cl_saved'] ) ? 'created' : 'updated';
	$ecv2_cl_saved_id = isset( $_GET['cl_saved'] ) ? (int) $_GET['cl_saved'] : (int) $_GET['cl_updated'];
	$ecv2_cl_saved_link = wp_easycart_cart_link::get_link( $ecv2_cl_saved_id, false );
	if ( $ecv2_cl_saved_link ) {
		$ecv2_cl_saved_link->url = wp_easycart_cart_link::get_url( $ecv2_cl_saved_link->link_token );
	}
}
?>
<div class="ecv2-wrap ecv2-cart-links" id="ecv2_cart_links">

	<?php if ( $ecv2_cl_saved_link ) : ?>
	<div class="ecv2-cl-banner" id="ecv2_cl_banner">
		<span class="dashicons dashicons-yes-alt"></span>
		<div class="ecv2-cl-banner-text">
			<strong><?php echo ( 'created' === $ecv2_cl_saved_mode ) ? esc_html__( 'Cart link created.', 'wp-easycart' ) : esc_html__( 'Cart link updated.', 'wp-easycart' ); ?></strong>
			<span><?php echo esc_html( '' !== $ecv2_cl_saved_link->link_label ? wp_unslash( $ecv2_cl_saved_link->link_label ) : $ecv2_cl_saved_link->link_token ); ?></span>
		</div>
		<div class="ecv2-cl-banner-actions">
			<button type="button" class="ecv2-btn ecv2-btn-sm" data-url="<?php echo esc_attr( $ecv2_cl_saved_link->url ); ?>" onclick="ecv2_cart_link_copy_row( this ); return false;"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy link', 'wp-easycart' ); ?></button>
			<?php do_action( 'wp_easycart_ecv2_cart_link_saved_banner', $ecv2_cl_saved_link ); ?>
			<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecv2_cart_link_edit( <?php echo esc_attr( $ecv2_cl_saved_link->cart_link_id ); ?> ); return false;"><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2-cl-banner-dismiss" onclick="jQuery( '#ecv2_cl_banner' ).slideUp( 140 ); return false;" aria-label="<?php esc_attr_e( 'Dismiss', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		</div>
	</div>
	<?php endif; ?>

	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left">
			<span class="dashicons dashicons-admin-links ecv2-page-header-icon"></span>
			<h2 class="ecv2-page-title"><?php esc_html_e( 'Cart Links', 'wp-easycart' ); ?></h2>
			<span class="ecv2-count-chip"><?php echo esc_html( count( $cart_links ) ); ?></span>
		</div>
		<div class="ecv2-page-header-right">
			<?php /* 6.0.1: the docs page for cart links. */ ?>
			<a class="ecv2-btn ecv2-btn-ghost ecv2-btn-sm" href="<?php echo esc_url( wp_easycart_admin()->helpsystem->print_docs_url( 'marketing', 'cart-links', '' ) ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Help', 'wp-easycart' ); ?>"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span> <span class="ecv2-btn-label"><?php esc_html_e( 'Help', 'wp-easycart' ); ?></span></a>
			<button type="button" class="ecv2-btn ecv2-btn-primary" onclick="ecv2_cart_link_new();" title="<?php esc_attr_e( 'New Cart Link', 'wp-easycart' ); ?>"><span class="dashicons dashicons-plus-alt2"></span> <span class="ecv2-btn-label"><?php esc_html_e( 'New Cart Link', 'wp-easycart' ); ?></span></button>
		</div>
	</div>

	<p class="ecv2-page-intro">
		<?php esc_html_e( 'A cart link fills the shopper\'s cart the moment they open it — product, quantity, and options pre-selected. Share it in emails and social posts, or print it as a QR code on packaging and flyers.', 'wp-easycart' ); ?>
	</p>

	<?php $this->print_upsell_strip(); ?>

	<?php if ( empty( $cart_links ) ) : ?>
		<div class="ecv2-empty-state" id="ecv2_cart_links_empty">
			<span class="dashicons dashicons-admin-links"></span>
			<h3><?php esc_html_e( 'No cart links yet', 'wp-easycart' ); ?></h3>
			<p><?php esc_html_e( 'Create your first link and share a one-click path to a filled cart.', 'wp-easycart' ); ?></p>
			<button type="button" class="ecv2-btn ecv2-btn-primary" onclick="ecv2_cart_link_new();">+ <?php esc_html_e( 'New Cart Link', 'wp-easycart' ); ?></button>
		</div>
	<?php endif; ?>

	<div class="ecv2-table-card" id="ecv2_cart_links_table_card"<?php echo empty( $cart_links ) ? ' style="display:none;"' : ''; ?>>
		<table class="ecv2-table ecv2-cart-link-table">
			<thead>
				<tr>
					<th class="ecv2-col ecv2-col-label"><?php esc_html_e( 'Link', 'wp-easycart' ); ?></th>
					<th class="ecv2-col ecv2-col-contents"><?php esc_html_e( 'Contents', 'wp-easycart' ); ?></th>
					<th class="ecv2-col ecv2-col-dest ecv2-hide-tablet"><?php esc_html_e( 'Sends to', 'wp-easycart' ); ?></th>
					<th class="ecv2-col ecv2-col-uses"><?php esc_html_e( 'Uses', 'wp-easycart' ); ?></th>
					<th class="ecv2-col ecv2-col-status"><?php esc_html_e( 'Status', 'wp-easycart' ); ?></th>
					<th class="ecv2-col ecv2-col-actions"><?php esc_html_e( 'Actions', 'wp-easycart' ); ?></th>
				</tr>
			</thead>
			<tbody id="ecv2_cart_links_body">
				<?php foreach ( $cart_links as $ecv2_link ) : ?>
					<?php $this->print_link_row( $ecv2_link ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php $this->print_pagination(); ?>
	</div>

	<?php do_action( 'wp_easycart_ecv2_cart_link_page_end' ); ?>

	<!-- ================= Builder drawer ================= -->
	<div class="ecv2-drawer-backdrop ecv2-cl-backdrop" id="ecv2_cl_backdrop" style="display:none;" onclick="ecv2_cart_link_close();"></div>
	<div class="ecv2-cl-drawer" id="ecv2_cl_drawer" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="ecv2_cl_title">
		<div class="ecv2-drawer-header ecv2-qe-header">
			<div class="ecv2-drawer-header-left">
				<span class="dashicons dashicons-admin-links ecv2-drawer-header-icon"></span>
				<h3 class="ecv2-drawer-title" id="ecv2_cl_title"><?php esc_html_e( 'New Cart Link', 'wp-easycart' ); ?></h3>
			</div>
			<button type="button" class="ecv2-drawer-close" onclick="ecv2_cart_link_close();" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		</div>

		<div class="ecv2-drawer-body ecv2-qe-body">
			<div class="ecv2-qe-content" id="ecv2_cl_content">
				<input type="hidden" id="ecv2_cl_id" value="0" />

				<section class="ecv2-qe-section ecv2-qe-section-edit">
					<label class="ecv2-modal-label" for="ecv2_cl_label"><?php esc_html_e( 'Name', 'wp-easycart' ); ?> <span class="ecv2-qe-muted"><?php esc_html_e( '(internal — customers never see it)', 'wp-easycart' ); ?></span></label>
					<input type="text" id="ecv2_cl_label" class="ecv2-input" placeholder="<?php esc_attr_e( 'e.g. Holiday pie special', 'wp-easycart' ); ?>" />
				</section>

				<section class="ecv2-qe-section ecv2-qe-section-edit">
					<div class="ecv2-qe-section-label"><span><?php esc_html_e( 'Product', 'wp-easycart' ); ?></span></div>
					<div id="ecv2_cl_items"></div>
					<?php do_action( 'wp_easycart_ecv2_cart_link_drawer_items_end' ); ?>
				</section>

				<?php do_action( 'wp_easycart_ecv2_cart_link_drawer_fields' ); ?>

				<section class="ecv2-qe-section ecv2-qe-section-edit">
					<div class="ecv2-qe-section-label"><span><?php esc_html_e( 'When the link is opened', 'wp-easycart' ); ?></span></div>
					<label class="ecv2-qe-check"><input type="radio" name="ecv2_cl_destination" value="cart" checked /> <?php esc_html_e( 'Send the shopper to the cart', 'wp-easycart' ); ?></label>
					<label class="ecv2-qe-check"><input type="radio" name="ecv2_cl_destination" value="checkout" /> <?php esc_html_e( 'Send the shopper straight to checkout', 'wp-easycart' ); ?></label>
					<label class="ecv2-qe-check" style="margin-top:14px;"><input type="checkbox" id="ecv2_cl_clear_cart" /> <?php esc_html_e( 'Empty their existing cart first', 'wp-easycart' ); ?> <span class="ecv2-qe-muted"><?php esc_html_e( '(off = adds to whatever they already have)', 'wp-easycart' ); ?></span></label>
				</section>

			</div>
		</div>

		<div class="ecv2-drawer-footer ecv2-qe-footer">
			<span class="ecv2-qe-muted" id="ecv2_cl_footer_note"></span>
			<div class="ecv2-qe-footer-actions">
				<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecv2_cart_link_close();"><?php esc_html_e( 'Close', 'wp-easycart' ); ?></button>
				<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecv2_cl_save" onclick="ecv2_cart_link_save();"><?php esc_html_e( 'Save Cart Link', 'wp-easycart' ); ?></button>
			</div>
		</div>
	</div>
	<script>jQuery( function( $ ) { $( '#ecv2_cl_backdrop, #ecv2_cl_drawer' ).appendTo( document.body ); } );</script>
</div>