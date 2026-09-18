<?php
/**
 * WP EasyCart — Create Option Set slideout ( V2 ).
 *
 * One panel creates the option set and all of its choices in a single save.
 * It replaces new-optionset-slideout.php, new-optionitem-slideout.php,
 * new-advanced-optionset-slideout.php and new-advanced-optionitem-slideout.php.
 *
 * Opened from the product slideout it stacks above it ( the product panel
 * recedes ) and the footer verb becomes "Create & add to product". Opened
 * from the Option Sets page it stands alone. Origin is set by JS as
 * data-origin="product|standalone".
 *
 * Types:
 *   free  → basic-combo ( Dropdown ), basic-swatch ( Swatches )
 *   PRO   → modifiers: text, textarea, number, file, radio, checkbox, grid,
 *           date, dimensions1, dimensions2
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_pro     = ( '' === apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ) );
$currency   = get_option( 'ec_option_currency_symbol', '$' );
$weight_u   = get_option( 'ec_option_weight_unit', 'lb' );
$lock_svg   = '<svg class="ecosv2-lock-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5 7V5a3 3 0 016 0v2"/></svg>';

$icons = array(
	'basic-combo'  => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M8 12h5M16 11l1.5 1.5L16 14"/>',
	'basic-swatch' => '<circle cx="8" cy="9" r="3.5"/><circle cx="16" cy="9" r="3.5"/><circle cx="12" cy="16" r="3.5"/>',
	'text'         => '<path d="M4 7h16M4 12h10M4 17h7"/>',
	'textarea'     => '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 10h8M8 14h5"/>',
	'number'       => '<path d="M5 9h14M5 15h14M10 4L8 20M16 4l-2 16"/>',
	'file'         => '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
	'radio'        => '<circle cx="7" cy="8" r="3"/><circle cx="7" cy="16" r="3"/><circle cx="7" cy="8" r="1" fill="currentColor"/><path d="M13 8h7M13 16h7"/>',
	'checkbox'     => '<rect x="4" y="5" width="6" height="6" rx="1.5"/><path d="M5.5 8l1.5 1.5L9.5 6.5M4 15h6M13 8h7M13 16h7"/>',
	'grid'         => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 12h16M12 4v16"/>',
	'date'         => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M8 3v4M16 3v4"/>',
	'dimensions1'  => '<path d="M4 20L20 4M4 20h6M4 20v-6M20 4h-6M20 4v6"/>',
	'dimensions2'  => '<path d="M4 20L20 4M4 20h6M4 20v-6M20 4h-6M20 4v6M9 15l1-1"/>',
);
$free_types = array(
	'basic-combo'  => array( __( 'Dropdown', 'wp-easycart' ), __( 'One pick from a list', 'wp-easycart' ) ),
	'basic-swatch' => array( __( 'Swatches', 'wp-easycart' ), __( 'Tap a chip or photo', 'wp-easycart' ) ),
);
$pro_types = array(
	'text'        => array( __( 'Text field', 'wp-easycart' ), __( 'Single line', 'wp-easycart' ) ),
	'textarea'    => array( __( 'Text area', 'wp-easycart' ), __( 'Multi-line', 'wp-easycart' ) ),
	'number'      => array( __( 'Number', 'wp-easycart' ), __( 'Min · max · step', 'wp-easycart' ) ),
	'file'        => array( __( 'File upload', 'wp-easycart' ), __( 'Artwork, photos', 'wp-easycart' ) ),
	'radio'       => array( __( 'Radio group', 'wp-easycart' ), __( 'One visible pick', 'wp-easycart' ) ),
	'checkbox'    => array( __( 'Checkboxes', 'wp-easycart' ), __( 'Multiple add-ons', 'wp-easycart' ) ),
	'grid'        => array( __( 'Quantity grid', 'wp-easycart' ), __( 'Qty per choice', 'wp-easycart' ) ),
	'date'        => array( __( 'Date', 'wp-easycart' ), __( 'Pickup, event…', 'wp-easycart' ) ),
	'dimensions1' => array( __( 'Dimensions', 'wp-easycart' ), __( 'Whole inches', 'wp-easycart' ) ),
	'dimensions2' => array( __( 'Dimensions, fine', 'wp-easycart' ), __( 'Sub-inch', 'wp-easycart' ) ),
);
$type_hints = array(
	'basic-combo'  => __( 'Dropdowns and swatches create variations — every choice can carry its own SKU suffix, price and weight.', 'wp-easycart' ),
	'basic-swatch' => $is_pro ? __( 'Swatches show as chips; give each chip a color or a photo.', 'wp-easycart' ) : sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Swatches show as chips; give each chip a color. Photo swatches come with %s.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ),
	'text'         => __( 'A modifier: shoppers type something. It can add a price but never changes stock.', 'wp-easycart' ),
	'textarea'     => __( 'Multi-line text for longer messages or instructions.', 'wp-easycart' ),
	'number'       => __( 'A numeric input with optional minimum, maximum and step.', 'wp-easycart' ),
	'file'         => __( 'Shoppers upload a file with their order — artwork, a photo, a document.', 'wp-easycart' ),
	'radio'        => __( 'Like a dropdown, but every choice is visible at once.', 'wp-easycart' ),
	'checkbox'     => __( 'Shoppers can pick several add-ons; each has its own price.', 'wp-easycart' ),
	'grid'         => __( 'A quantity box per choice — e.g. 3 small, 2 large — in one line item.', 'wp-easycart' ),
	'date'         => __( 'A date picker, for pickup or event dates.', 'wp-easycart' ),
	'dimensions1'  => __( 'Width × height in whole inches, priced per unit area.', 'wp-easycart' ),
	'dimensions2'  => __( 'Width × height with fractions of an inch, priced per unit area.', 'wp-easycart' ),
);

if ( ! function_exists( 'ecosv2_tile' ) ) :
function ecosv2_tile( $type, $label, $desc, $icon_path, $on = false, $locked = false ) {
	$cls = 'ecosv2-tile' . ( $on ? ' is-on' : '' ) . ( $locked ? ' is-locked' : '' );
	$click = $locked ? 'ecdv2_upsell( { context: \'products\', feature: \'modifiers\' } ); return false;' : 'ecosv2_set_type( this );';
	echo '<button type="button" class="' . esc_attr( $cls ) . '" data-type="' . esc_attr( $type ) . '" onclick="' . $click . '">';
	if ( $locked ) {
		echo '<span class="ecosv2-pill">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
	}
	echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">' . $icon_path . '</svg>';
	echo '<strong>' . esc_html( $label ) . '</strong><span>' . esc_html( $desc ) . '</span>';
	echo '</button>';
}
endif;
?>
<div class="ecosv2-overlay" id="ecosv2_box" data-origin="standalone" data-type="basic-combo" data-pro="<?php echo $is_pro ? '1' : '0'; ?>" data-type-hints="<?php echo esc_attr( wp_json_encode( $type_hints ) ); ?>" style="display:none;">
<aside class="ecosv2" role="dialog" aria-modal="true" aria-labelledby="ecosv2_title">
	<input type="hidden" id="wp_easycart_optionset_quick_edit_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wp-easycart-optionset-quick-edit' ) ); ?>" />
	<div class="ecosv2-loader" id="ecosv2_loader"><span class="ecosv2-spinner"></span></div>

	<header class="ecosv2-header">
		<div class="ecosv2-header-text">
			<button type="button" class="ecosv2-back" data-origin-only="product" onclick="ecosv2_close();">
				<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7.5 2.5L4 6l3.5 3.5"/></svg><?php esc_html_e( 'Back to product', 'wp-easycart' ); ?>
			</button>
			<div class="ecosv2-eyebrow"><span><?php esc_html_e( 'New option set', 'wp-easycart' ); ?></span><?php if ( $is_pro ) : ?><span class="ecosv2-pill ecosv2-pill-active"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span><?php endif; ?></div>
			<h2 class="ecosv2-title" id="ecosv2_title"><?php esc_html_e( 'Create an option set', 'wp-easycart' ); ?></h2>
			<div class="ecosv2-sub">
				<span data-origin-only="product" id="ecosv2_sub_product"></span>
				<span data-origin-only="standalone"><?php esc_html_e( 'Reusable on any product — size, color, flavor, anything with a fixed list of choices.', 'wp-easycart' ); ?></span>
			</div>
		</div>
		<button type="button" class="ecosv2-x" onclick="ecosv2_close();" aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>
	</header>

	<div class="ecosv2-body">

		<!-- ============================ TYPE ============================ -->
		<section class="ecosv2-section">
			<div class="ecosv2-section-head"><?php esc_html_e( 'How shoppers choose', 'wp-easycart' ); ?></div>
			<div class="ecosv2-tiles">
				<?php foreach ( $free_types as $t => $l ) { ecosv2_tile( $t, $l[0], $l[1], $icons[ $t ], 'basic-combo' === $t ); } ?>
				<?php if ( ! $is_pro ) : ?>
					<?php ecosv2_tile( 'text', __( 'Text field', 'wp-easycart' ), __( 'Engraving, a name…', 'wp-easycart' ), $icons['text'], false, true ); ?>
					<?php ecosv2_tile( 'checkbox', __( 'Add-ons', 'wp-easycart' ), __( 'Checkboxes, radios…', 'wp-easycart' ), $icons['checkbox'], false, true ); ?>
				<?php endif; ?>
			</div>
			<?php if ( $is_pro ) : ?>
			<div class="ecosv2-tiles-head"><?php esc_html_e( "Modifiers — collect input or add-ons; they don't create stock variations", 'wp-easycart' ); ?></div>
			<div class="ecosv2-tiles">
				<?php foreach ( $pro_types as $t => $l ) { ecosv2_tile( $t, $l[0], $l[1], $icons[ $t ] ); } ?>
			</div>
			<?php endif; ?>
			<div class="ecosv2-hint ecosv2-type-hint" id="ecosv2_type_hint"><?php echo esc_html( $type_hints['basic-combo'] ); ?></div>
		</section>

		<!-- =========================== NAMING =========================== -->
		<section class="ecosv2-section">
			<div class="ecosv2-section-head"><?php esc_html_e( 'Naming', 'wp-easycart' ); ?></div>
			<div class="ecosv2-grid2">
				<div class="ecosv2-f">
					<label for="ecosv2_name"><?php esc_html_e( 'Name', 'wp-easycart' ); ?> <span class="ecosv2-req">*</span></label>
					<input type="text" class="ecv2-input" id="ecosv2_name" placeholder="<?php esc_attr_e( 'e.g. Size', 'wp-easycart' ); ?>" />
					<div class="ecosv2-hint"><?php esc_html_e( 'Internal — shown in your admin lists.', 'wp-easycart' ); ?></div>
					<div class="ecosv2-hint ecosv2-err" id="ecosv2_name_err" hidden><?php esc_html_e( 'A name is required.', 'wp-easycart' ); ?></div>
				</div>
				<div class="ecosv2-f">
					<label for="ecosv2_label"><?php esc_html_e( 'Label shoppers see', 'wp-easycart' ); ?></label>
					<input type="text" class="ecv2-input" id="ecosv2_label" placeholder="<?php esc_attr_e( 'Choose a size', 'wp-easycart' ); ?>" />
					<div class="ecosv2-hint" id="ecosv2_label_hint"><?php esc_html_e( 'Generated from the name — edit to override.', 'wp-easycart' ); ?></div>
				</div>
			</div>
		</section>

		<!-- =========================== CHOICES ========================== -->
		<section class="ecosv2-section" id="ecosv2_choices">
			<div class="ecosv2-section-head ecosv2-head-split"><span><?php esc_html_e( 'Choices', 'wp-easycart' ); ?></span><span class="ecosv2-hint" id="ecosv2_count"></span></div>
			<div class="ecosv2-items">
				<div class="ecosv2-irow ecosv2-irow-head">
					<span></span>
					<span class="ecosv2-col-swatch" data-swatch-only><?php esc_html_e( 'Swatch', 'wp-easycart' ); ?></span>
					<span><?php esc_html_e( 'Name', 'wp-easycart' ); ?></span>
					<span><?php esc_html_e( 'SKU suffix', 'wp-easycart' ); ?></span>
					<span><?php esc_html_e( 'Price ±', 'wp-easycart' ); ?></span>
					<span><?php esc_html_e( 'Weight ±', 'wp-easycart' ); ?></span>
					<span></span>
				</div>
				<div id="ecosv2_rows"></div>
				<div class="ecosv2-iadd">
					<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecosv2_add_row();">+ <?php esc_html_e( 'Add choice', 'wp-easycart' ); ?></button>
					<button type="button" class="ecosv2-link" onclick="ecosv2_toggle_paste();"><?php esc_html_e( 'Paste a list', 'wp-easycart' ); ?></button>
					<span class="ecosv2-spacer"></span>
					<span class="ecosv2-hint"><?php esc_html_e( 'Drag to reorder · Enter adds the next', 'wp-easycart' ); ?></span>
				</div>
			</div>
			<div class="ecosv2-paste" id="ecosv2_paste" hidden>
				<textarea id="ecosv2_pastebox" class="ecv2-input" rows="4" placeholder="<?php echo esc_attr( "Small\nMedium\nLarge\nX-Large  +2.00" ); ?>"></textarea>
				<div class="ecosv2-paste-actions">
					<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="ecosv2_do_paste();"><?php esc_html_e( 'Add these', 'wp-easycart' ); ?></button>
					<span class="ecosv2-hint"><?php esc_html_e( 'One per line. Add “+2.00” after a name to set a price bump.', 'wp-easycart' ); ?></span>
				</div>
			</div>

			<div class="ecosv2-preview">
				<div class="ecosv2-preview-head"><span><?php esc_html_e( 'Storefront preview', 'wp-easycart' ); ?></span><span id="ecosv2_preview_meta"></span></div>
				<div class="ecosv2-preview-label" id="ecosv2_preview_label"></div>
				<div id="ecosv2_preview_body"></div>
			</div>

			<?php if ( ! $is_pro ) : ?>
			<button type="button" class="ecosv2-pro-row" onclick="ecdv2_upsell( { context: 'products', feature: 'images' } ); return false;">
				<?php echo $lock_svg; ?>
				<span class="ecosv2-pro-row-text"><strong><?php esc_html_e( 'Swatch photos, default choice, per-choice stock', 'wp-easycart' ); ?></strong><span><?php esc_html_e( 'Show a photo per chip, preselect the most popular choice, and track inventory per combination.', 'wp-easycart' ); ?></span></span>
				<span class="ecosv2-pill"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span>
			</button>
			<?php else : ?>
			<div class="ecosv2-pro-live">
				<label class="ecosv2-sw"><input type="checkbox" id="ecosv2_preselect" /><span class="ecosv2-knob"></span><?php esc_html_e( 'Preselect the first choice', 'wp-easycart' ); ?></label>
			</div>
			<?php endif; ?>
		</section>

		<?php if ( $is_pro ) : ?>
		<!-- ===================== MODIFIER INPUT SETTINGS ================= -->
		<section class="ecosv2-section" id="ecosv2_modifier" hidden>
			<div class="ecosv2-section-head"><?php esc_html_e( 'Input settings', 'wp-easycart' ); ?></div>
			<div class="ecosv2-grid2">
				<div class="ecosv2-f">
					<label for="ecosv2_input_price"><?php esc_html_e( 'Price for this input', 'wp-easycart' ); ?> <span class="ecosv2-hint"><?php esc_html_e( 'optional', 'wp-easycart' ); ?></span></label>
					<div class="ecosv2-prefix"><span><?php echo esc_html( $currency ); ?></span><input type="number" step=".01" class="ecv2-input" id="ecosv2_input_price" placeholder="0.00" /></div>
				</div>
			</div>
			<div class="ecosv2-meta" id="ecosv2_meta_num" hidden>
				<div class="ecosv2-grid3">
					<div class="ecosv2-f"><label for="ecosv2_meta_min"><?php esc_html_e( 'Minimum', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" id="ecosv2_meta_min" placeholder="—" /></div>
					<div class="ecosv2-f"><label for="ecosv2_meta_max"><?php esc_html_e( 'Maximum', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" id="ecosv2_meta_max" placeholder="—" /></div>
					<div class="ecosv2-f"><label for="ecosv2_meta_step"><?php esc_html_e( 'Step', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" id="ecosv2_meta_step" placeholder="1" /></div>
				</div>
			</div>
			<div class="ecosv2-meta" id="ecosv2_meta_text" hidden>
				<div class="ecosv2-grid2">
					<div class="ecosv2-f"><label for="ecosv2_meta_min_length"><?php esc_html_e( 'Minimum characters', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" id="ecosv2_meta_min_length" placeholder="—" /></div>
					<div class="ecosv2-f"><label for="ecosv2_meta_max_length"><?php esc_html_e( 'Maximum characters', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" id="ecosv2_meta_max_length" placeholder="—" /></div>
				</div>
			</div>
			<div class="ecosv2-pro-live">
				<label class="ecosv2-sw"><input type="checkbox" id="ecosv2_required" /><span class="ecosv2-knob"></span><?php esc_html_e( 'Required', 'wp-easycart' ); ?></label>
				<input type="text" class="ecv2-input ecosv2-reqmsg" id="ecosv2_error_text" placeholder="<?php esc_attr_e( 'Message when left blank, e.g. “Please enter your engraving text”', 'wp-easycart' ); ?>" hidden />
			</div>
		</section>
		<?php endif; ?>

		<?php if ( ! $is_pro ) : ?>
		<section class="ecosv2-section">
			<div class="ecosv2-card">
				<div class="ecosv2-card-head"><span class="ecosv2-pill"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span><strong><?php esc_html_e( 'Options that do more than pick a size', 'wp-easycart' ); ?></strong></div>
				<ul>
					<li><?php esc_html_e( 'Text, number, date and file-upload fields on any product', 'wp-easycart' ); ?></li>
					<li><?php esc_html_e( 'Add-on checkboxes and radios with their own prices', 'wp-easycart' ); ?></li>
					<li><?php esc_html_e( 'Swatch photos, default choices, conditional show/hide, per-variation stock', 'wp-easycart' ); ?></li>
				</ul>
				<div class="ecosv2-card-actions">
					<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="ecdv2_upsell( { context: 'products' } ); return false;"><?php esc_html_e( "See what's included", 'wp-easycart' ); ?></button>
					<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( self_admin_url( 'admin.php?page=wp-easycart-registration' ) ); ?>"><?php esc_html_e( 'Try free for 14 days', 'wp-easycart' ); ?></a>
				</div>
			</div>
		</section>
		<?php endif; ?>

	</div>

	<footer class="ecosv2-footer">
		<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="ecosv2_close();"><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
		<span class="ecosv2-spacer"></span>
		<button type="button" class="ecv2-btn" data-origin-only="standalone" onclick="ecosv2_save( 'another' );"><?php esc_html_e( 'Create & add another', 'wp-easycart' ); ?></button>
		<button type="button" class="ecv2-btn ecv2-btn-primary ecosv2-cta" data-origin-only="standalone" onclick="ecosv2_save( 'close' );"><?php esc_html_e( 'Create option set', 'wp-easycart' ); ?></button>
		<button type="button" class="ecv2-btn ecv2-btn-primary ecosv2-cta" data-origin-only="product" onclick="ecosv2_save( 'close' );"><?php esc_html_e( 'Create & add to product', 'wp-easycart' ); ?></button>
	</footer>
</aside>
</div>
<script>jQuery( document.getElementById( 'ecosv2_box' ) ).appendTo( document.body );</script>