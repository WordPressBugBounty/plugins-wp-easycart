<?php
/**
 * Product editor — Cart Links card ( SEO & Marketing tab ).
 *
 * Quick-create a cart link prefilled with THIS product ( name, quantity,
 * basic option preselects ), plus a compact list of links already containing
 * it. Complex builds ( multi-product, codes, limits ) hand off to the
 * Marketing manager.
 *
 * Included by product-details-v2.php only when: not a new product, the free
 * Cart Links class is loaded, and the user holds wpec_marketing — a
 * products-only admin never sees this card. Uses the same AJAX endpoints and
 * nonce as the Marketing page ( registered on every admin request ).
 *
 * PRO mounts modifier preselect inputs via
 * do_action( 'wp_easycart_ecv2_cart_link_card_modifiers', $product ) and the
 * QR button via do_action( 'wp_easycart_ecv2_cart_link_card_share_extra' ).
 *
 * @var object $product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecdv2_cl_links = wp_easycart_cart_link::get_links_for_product( $product->product_id );
$ecdv2_cl_nonce = wp_create_nonce( wp_easycart_admin_cart_links::NONCE_ACTION );
$ecdv2_cl_manage_url = admin_url( 'admin.php?page=wp-easycart-rates&subpage=cart-links' );

/* Basic option slots, server-rendered ( same data ajax_product_data returns ). */
global $wpdb;
$ecdv2_cl_slots = array();
if ( ! $product->use_advanced_optionset || $product->use_both_option_types ) {
	for ( $ecdv2_cl_slot = 1; $ecdv2_cl_slot <= 5; $ecdv2_cl_slot++ ) {
		$ecdv2_cl_option_id = (int) $product->{ 'option_id_' . $ecdv2_cl_slot };
		if ( ! $ecdv2_cl_option_id ) {
			continue;
		}
		$ecdv2_cl_option = $wpdb->get_row( $wpdb->prepare( 'SELECT option_id, option_label FROM ec_option WHERE option_id = %d', $ecdv2_cl_option_id ) );
		if ( ! $ecdv2_cl_option ) {
			continue;
		}
		$ecdv2_cl_slots[] = array(
			'slot'  => $ecdv2_cl_slot,
			'label' => wp_unslash( (string) $ecdv2_cl_option->option_label ),
			'items' => $wpdb->get_results( $wpdb->prepare( 'SELECT optionitem_id, optionitem_name FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_order ASC, optionitem_name ASC', $ecdv2_cl_option_id ) ),
		);
	}
}
?>
<div class="ecdv2-card ecdv2-cart-links-card" id="ecdv2_cart_links_card" data-product-id="<?php echo esc_attr( $product->product_id ); ?>" data-nonce="<?php echo esc_attr( $ecdv2_cl_nonce ); ?>">
	<div class="ecdv2-card-header">
		<h3 class="ecdv2-card-title"><?php esc_html_e( 'Cart Links', 'wp-easycart' ); ?></h3>
		<span class="ecdv2-card-hint"><?php esc_html_e( 'Shareable links that add this product to the cart, pre-configured', 'wp-easycart' ); ?></span>
		<a href="<?php echo esc_url( $ecdv2_cl_manage_url ); ?>" class="ecv2-btn"><?php esc_html_e( 'Manage in Marketing', 'wp-easycart' ); ?></a>
	</div>
	<div class="ecdv2-card-body">

		<div class="ecdv2-cl-existing" id="ecdv2_cl_existing"<?php echo empty( $ecdv2_cl_links ) ? ' style="display:none;"' : ''; ?>>
			<?php foreach ( $ecdv2_cl_links as $ecdv2_cl_link ) : ?>
				<div class="ecdv2-cl-existing-row<?php echo $ecdv2_cl_link->is_active ? '' : ' is-inactive'; ?>" data-cart-link-id="<?php echo esc_attr( $ecdv2_cl_link->cart_link_id ); ?>">
					<div class="ecdv2-cl-existing-main">
						<span class="ecdv2-cl-existing-label"><?php echo esc_html( '' !== $ecdv2_cl_link->link_label ? wp_unslash( $ecdv2_cl_link->link_label ) : __( '(untitled link)', 'wp-easycart' ) ); ?></span>
						<span class="ecdv2-cl-existing-meta">
							<code><?php echo esc_html( $ecdv2_cl_link->link_token ); ?></code>
							· <span class="ecdv2-cl-uses"><?php echo esc_html( sprintf( _n( '%d use', '%d uses', (int) $ecdv2_cl_link->use_count, 'wp-easycart' ), (int) $ecdv2_cl_link->use_count ) ); ?></span>
							<?php if ( (int) $ecdv2_cl_link->item_count > 1 ) : ?>
								· <?php echo esc_html( sprintf( __( '%d products', 'wp-easycart' ), (int) $ecdv2_cl_link->item_count ) ); ?>
							<?php endif; ?>
							<?php if ( ! $ecdv2_cl_link->is_active ) : ?>
								· <em><?php esc_html_e( 'inactive', 'wp-easycart' ); ?></em>
							<?php endif; ?>
						</span>
					</div>
					<div class="ecdv2-cl-existing-actions">
						<button type="button" class="ecv2-btn ecv2-btn-sm" data-url="<?php echo esc_attr( $ecdv2_cl_link->url ); ?>" onclick="ecdv2_cart_links.copy( this ); return false;"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'wp-easycart' ); ?></button>
						<?php do_action( 'wp_easycart_ecv2_cart_link_card_row_actions', $ecdv2_cl_link ); ?>
						<button type="button" class="ecdv2-cl-row-delete" title="<?php esc_attr_e( 'Delete link', 'wp-easycart' ); ?>" onclick="ecdv2_cart_links.remove( this, <?php echo esc_attr( $ecdv2_cl_link->cart_link_id ); ?> ); return false;"><span class="dashicons dashicons-trash"></span></button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<button type="button" class="ecdv2-cl-quick-toggle" id="ecdv2_cl_quick_toggle" onclick="ecdv2_cart_links.toggle_form(); return false;">+ <?php esc_html_e( 'Create cart link for this product', 'wp-easycart' ); ?></button>

		<div class="ecdv2-cl-quick" id="ecdv2_cl_quick" style="display:none;">
			<div class="ecdv2-cl-quick-grid">
				<label class="ecv2-cl-field ecdv2-cl-quick-name"><span><?php esc_html_e( 'Name', 'wp-easycart' ); ?> <em><?php esc_html_e( '(internal)', 'wp-easycart' ); ?></em></span>
					<input type="text" id="ecdv2_cl_quick_label" class="ecv2-input" placeholder="<?php echo esc_attr( sprintf( __( 'e.g. %s promo', 'wp-easycart' ), wp_unslash( $product->title ) ) ); ?>" />
				</label>
				<label class="ecv2-cl-field"><span><?php esc_html_e( 'Quantity', 'wp-easycart' ); ?></span>
					<input type="number" min="1" step="1" id="ecdv2_cl_quick_qty" class="ecv2-input" value="1" />
				</label>
				<?php foreach ( $ecdv2_cl_slots as $ecdv2_cl_slot_data ) : ?>
					<label class="ecv2-cl-field"><span><?php echo esc_html( $ecdv2_cl_slot_data['label'] ); ?></span>
						<select class="ecv2-select ecdv2-cl-quick-basic" data-slot="<?php echo esc_attr( $ecdv2_cl_slot_data['slot'] ); ?>">
							<?php foreach ( $ecdv2_cl_slot_data['items'] as $ecdv2_cl_item ) : ?>
								<option value="<?php echo esc_attr( $ecdv2_cl_item->optionitem_id ); ?>"><?php echo esc_html( wp_unslash( $ecdv2_cl_item->optionitem_name ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endforeach; ?>
			</div>

			<?php do_action( 'wp_easycart_ecv2_cart_link_card_modifiers', $product ); ?>

			<div class="ecdv2-cl-quick-foot">
				<span class="ecdv2-cl-quick-hint"><?php esc_html_e( 'Sends shoppers to their cart. Need multiple products, coupon codes, or limits?', 'wp-easycart' ); ?> <a href="<?php echo esc_url( $ecdv2_cl_manage_url ); ?>"><?php esc_html_e( 'Build it in Marketing', 'wp-easycart' ); ?></a></span>
				<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecdv2_cl_quick_create" onclick="ecdv2_cart_links.create(); return false;"><?php esc_html_e( 'Create Link', 'wp-easycart' ); ?></button>
			</div>

		</div>

	</div>
	<?php do_action( 'wp_easycart_ecv2_cart_link_card_end' ); ?>
</div>