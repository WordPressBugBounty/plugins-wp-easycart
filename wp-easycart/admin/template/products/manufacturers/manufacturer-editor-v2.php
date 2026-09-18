<?php
/** Manufacturer editor ( V2 ) template — $this is wp_easycart_admin_manufacturer_editor_v2. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
include_once( EC_PLUGIN_DIRECTORY . '/admin/template/products/shared/editor-lite-parts.php' );
$m = $this->m;
$list_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=manufacturers' );
$permalink = $this->permalink();
$thumb_id = $this->post ? (int) get_post_thumbnail_id( $this->post ) : 0;
$thumb = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
$products_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=products&filter_3=' . (int) $m->manufacturer_id );
/* Yoast card renders only while Yoast is active and the manufacturer has its ec_store page. @since 6.0.0 */
$yoast_card = class_exists( 'wp_easycart_admin_catalog_yoast_v2' ) && wp_easycart_admin_catalog_yoast_v2::available( 'manufacturer', (int) $m->manufacturer_id );
$rail = array( array( '#mfv2-details', __( 'Details', 'wp-easycart' ) ), array( '#mfv2-products', __( 'Products', 'wp-easycart' ), $this->total ), array( '#mfv2-seo', __( 'SEO & URL', 'wp-easycart' ) ) );
if ( $yoast_card ) { $rail[] = array( '#mfv2-yoast', __( 'Yoast SEO', 'wp-easycart' ) ); }

ecv2_lite_open( $this->js_data(), 'ecmf-wrap' );
ecv2_lite_header( array(
	'back_url' => $list_url, 'back_title' => __( 'Back to manufacturers', 'wp-easycart' ), 'title' => wp_unslash( $m->name ),
	'sub_html' => '<a href="' . esc_url( $products_url ) . '">' . esc_html( sprintf( _n( '%d product', '%d products', $this->total, 'wp-easycart' ), $this->total ) ) . '</a>' . ( $permalink ? ' · <span class="ecv2-mono" id="eclite_h_url">' . esc_html( wp_parse_url( $permalink, PHP_URL_PATH ) ) . '</span>' : '' ) . ' · ' . esc_html( sprintf( __( '%d clicks', 'wp-easycart' ), (int) $m->clicks ) ) . ' · ' . esc_html( 'ID ' . $m->manufacturer_id ),
	'thumb_url' => $thumb,
	'actions_html' => ( $permalink ? '<a class="ecv2-btn ecv2-btn-ghost" href="' . esc_url( $permalink ) . '" target="_blank" rel="noopener noreferrer" id="eclite_view">' . esc_html__( 'View on site', 'wp-easycart' ) . ' <span class="dashicons dashicons-external"></span></a>' : '' ) . '<button type="button" class="ecv2-btn" onclick="ecv2_catalog.assign_manufacturer( ' . (int) $m->manufacturer_id . ' );">' . esc_html__( 'Assign products', 'wp-easycart' ) . '</button>',
) );
?>
<div class="ecdv2-body">
	<?php ecv2_lite_rail( array(
		__( 'Manufacturer', 'wp-easycart' ) => $rail,
		__( 'More', 'wp-easycart' ) => array( array( '#mfv2-danger', __( 'Danger zone', 'wp-easycart' ) ) ),
	), $this->health(), __( 'Manufacturer health', 'wp-easycart' ) ); ?>
	<div class="ecdv2-main">
		<?php ecv2_lite_card_open( 'mfv2-details', __( 'Details', 'wp-easycart' ), '', '<a href="' . esc_url( $this->docs_link ) . '" target="_blank" class="ecdv2-help-link"><span class="dashicons dashicons-editor-help"></span>' . esc_html__( 'Help', 'wp-easycart' ) . '</a>' ); ?>
		<div class="ecdv2-grid">
			<?php ecv2_lite_field( 'eclite_name', __( 'Name', 'wp-easycart' ), ecv2_lite_text( 'eclite_name', 'name', wp_unslash( $m->name ), '', 'data-bind="title"' ), '', __( 'Shown on product pages and as the page title of the manufacturer store page.', 'wp-easycart' ) ); ?>
			<?php ecv2_lite_image_slot( 'eclite_featured', 'featured_image', $thumb_id, $thumb, __( 'Logo / featured image', 'wp-easycart' ), __( 'Optional · used by themes that show a manufacturer header', 'wp-easycart' ), 'id' ); ?>
		</div>
		<?php ecv2_lite_card_close(); ?>

		<?php ecv2_lite_products_card( 'mfv2-products', $this->products, $this->total, $products_url, sprintf( __( '%1$d active · %2$d inactive', 'wp-easycart' ), $this->total - $this->inactive, $this->inactive ), __( 'No products yet.', 'wp-easycart' ), '<a href="#" class="ecdv2-help-link ecos-link-brand" onclick="ecv2_catalog.assign_manufacturer( ' . (int) $m->manufacturer_id . ' ); return false;">+ ' . esc_html__( 'Assign products', 'wp-easycart' ) . '</a>' ); ?>

		<?php /* When the Yoast card renders, the SEO card drops its "Edit in Yoast" link ( the card below has its own ). */ ?>
		<?php ecv2_lite_seo_card( 'mfv2-seo', $this->post, $this->slug_prefix(), $this->yoast && ! $yoast_card ); ?>
		<?php if ( $yoast_card ) { wp_easycart_admin_catalog_yoast_v2::print_card( array( 'id' => 'mfv2-yoast', 'type' => 'manufacturer', 'entity_id' => (int) $m->manufacturer_id, 'name' => wp_unslash( $m->name ), 'label' => __( 'manufacturer', 'wp-easycart' ) ) ); } ?>
		<?php ecv2_lite_danger_card( 'mfv2-danger', __( 'Delete this manufacturer', 'wp-easycart' ), __( 'Products are never deleted. You can reassign them to another manufacturer, and the old URL can redirect. Undo is available for 30 days.', 'wp-easycart' ), __( 'Delete manufacturer…', 'wp-easycart' ), 'manufacturer', $m->manufacturer_id, $list_url ); ?>
	</div>
</div>
<?php ecv2_lite_close();
