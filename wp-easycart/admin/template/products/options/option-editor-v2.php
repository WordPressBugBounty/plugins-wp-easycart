<?php
/**
 * Option Set editor ( V2 ) template. $this is wp_easycart_admin_option_editor_v2.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$o        = $this->option;
$types    = wp_easycart_admin_option_table::types();
$tmeta    = wp_easycart_admin_option_table::type_meta( $o->option_type );
$is_basic = in_array( $o->option_type, wp_easycart_admin_option_editor_v2::basic_types(), true );
$health   = $this->health();
$list_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=option' );
$products_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=products&option_set=' . (int) $o->option_id );
$ok_svg   = '<span class="dashicons dashicons-yes"></span>';
$warn_svg = '<span class="dashicons dashicons-warning"></span>';
/* 6.0.0: modifier ( advanced ) sets are PRO. Without it the set is shown read-only; save, duplicate and assign open the upsell. */
$locked        = $this->locked;
$upsell_click  = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( 'products', 'modifiers' ) : 'return false;';
$assign_click  = $locked ? $upsell_click : 'ecv2_catalog.assign_option( ' . (int) $o->option_id . ' ); return false;';
?>
<div class="ecv2-wrap ecdv2-wrap ecos-wrap<?php echo $locked ? ' is-locked' : ''; ?>" id="ecos" data-option-id="<?php echo esc_attr( $o->option_id ); ?>">
	<script type="application/json" id="ecos_data"><?php echo wp_json_encode( $this->js_data() ); ?></script>

	<div class="ecdv2-header">
		<a class="ecdv2-header-back" href="<?php echo esc_url( $list_url ); ?>" title="<?php esc_attr_e( 'Back to option sets', 'wp-easycart' ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
		<div class="ecdv2-header-meta">
			<h1 class="ecdv2-header-title" id="ecos_h_title"><?php echo esc_html( wp_unslash( $o->option_name ) ); ?></h1>
			<div class="ecdv2-header-sub"><span id="ecos_h_type"><?php echo esc_html( $tmeta['label'] ); ?></span> · <span id="ecos_h_count"><?php echo esc_html( sprintf( _n( '%d choice', '%d choices', count( $this->items ), 'wp-easycart' ), count( $this->items ) ) ); ?></span> · <a href="<?php echo esc_url( $products_url ); ?>"><?php echo esc_html( sprintf( _n( 'used by %d product', 'used by %d products', $this->usage_total, 'wp-easycart' ), $this->usage_total ) ); ?></a> · <?php echo esc_html( 'ID ' . $o->option_id ); ?></div>
		</div>
		<span class="ecdv2-status-pill<?php echo $o->option_required ? ' is-active' : ''; ?>" id="ecos_h_req"><?php echo $o->option_required ? esc_html__( 'Required', 'wp-easycart' ) : esc_html__( 'Optional', 'wp-easycart' ); ?></span>
		<span class="ecdv2-header-spacer"></span>
		<span class="ecdv2-dirty-pill" id="ecos_dirty"><?php esc_html_e( 'Unsaved changes', 'wp-easycart' ); ?></span>
		<div class="ecdv2-header-actions">
			<?php if ( $locked ) { ?>
				<button type="button" class="ecv2-btn ecv2-btn-ghost" onclick="<?php echo esc_attr( $upsell_click ); ?>"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Duplicate', 'wp-easycart' ); ?></button>
				<button type="button" class="ecv2-btn" onclick="<?php echo esc_attr( $upsell_click ); ?>"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Assign to products', 'wp-easycart' ); ?></button>
				<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecos_save" onclick="<?php echo esc_attr( $upsell_click ); ?>"><span class="dashicons dashicons-lock"></span> <?php echo esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Unlock with %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ); ?></button>
				<?php } else { ?>
				<a class="ecv2-btn ecv2-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=option&ec_admin_form_action=duplicate-option&option_id=' . (int) $o->option_id . '&wp_easycart_nonce=' . wp_create_nonce( 'wp-easycart-action-duplicate-option' ) ) ); ?>"><?php esc_html_e( 'Duplicate', 'wp-easycart' ); ?></a>
				<button type="button" class="ecv2-btn" onclick="ecv2_catalog.assign_option( <?php echo (int) $o->option_id; ?> );"><?php esc_html_e( 'Assign to products', 'wp-easycart' ); ?></button>
				<button type="button" class="ecv2-btn ecv2-btn-primary ecdv2-save-btn" id="ecos_save" onclick="ecos.save();"><span class="ecdv2-save-spin"></span><span class="ecdv2-save-label"><?php esc_html_e( 'Save', 'wp-easycart' ); ?></span></button>
				<?php } ?>
		</div>
	</div>

	<div class="ecdv2-body">
		<aside class="ecdv2-rail">
			<div class="ecdv2-rail-group"><?php esc_html_e( 'Option set', 'wp-easycart' ); ?></div>
			<a href="#osv2-details" class="ecdv2-rail-link is-active"><?php esc_html_e( 'Details', 'wp-easycart' ); ?></a>
			<a href="#osv2-choices" class="ecdv2-rail-link" id="ecos_rail_choices"><?php esc_html_e( 'Choices', 'wp-easycart' ); ?> <span class="ecv2-chip ecv2-chip-gray" id="ecos_rail_count"><?php echo count( $this->items ); ?></span></a>
			<a href="#osv2-rules" class="ecdv2-rail-link"><?php esc_html_e( 'Rules & validation', 'wp-easycart' ); ?></a>
			<div class="ecdv2-rail-group"><?php esc_html_e( 'Usage', 'wp-easycart' ); ?></div>
			<a href="#osv2-used" class="ecdv2-rail-link"><?php esc_html_e( 'Products', 'wp-easycart' ); ?> <span class="ecv2-chip ecv2-chip-gray"><?php echo (int) $this->usage_total; ?></span></a>
			<a href="#osv2-danger" class="ecdv2-rail-link"><?php esc_html_e( 'Danger zone', 'wp-easycart' ); ?></a>
			<div class="ecdv2-health" id="ecos_health">
				<div class="ecdv2-health-top"><b><?php esc_html_e( 'Set health', 'wp-easycart' ); ?></b></div>
				<div class="ecdv2-health-items">
					<?php foreach ( $health as $h ) { ?>
					<div class="ecdv2-health-item <?php echo $h['ok'] ? 'is-ok' : 'is-warn'; ?>"><?php echo $h['ok'] ? $ok_svg : $warn_svg; ?><span class="ecdv2-health-label"><?php echo esc_html( $h['label'] ); ?></span></div>
					<?php } ?>
				</div>
			</div>
		</aside>

		<div class="ecdv2-main">

			<?php if ( $locked ) { ?>
			<!-- Locked: modifier set without PRO -->
			<div class="ecos-locked" id="ecos_locked">
				<div class="ecos-note ecos-note-brand"><span class="dashicons dashicons-lock"></span><div><b><?php esc_html_e( 'This is a modifier option set', 'wp-easycart' ); ?></b> <?php echo esc_html( wp_easycart_admin_option_editor_v2::lock_message() ); ?> <?php esc_html_e( 'You can still view it, and delete it below.', 'wp-easycart' ); ?></div></div>
				<?php
				if ( class_exists( 'wp_easycart_admin_upsell' ) ) {
					wp_easycart_admin_upsell::print_feature_strip(
						'products',
						true,
						array(
							'headline' => __( 'Unlock product modifiers', 'wp-easycart' ),
							'lede'     => sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Edit this set and add it to products with %s.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ),
						)
					);
				}
				?>
			</div>
			<?php } ?>

			<!-- Details -->
			<div class="ecdv2-card" id="osv2-details">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Details', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint"><?php esc_html_e( 'What this set is and how it appears on the product page', 'wp-easycart' ); ?></span><a href="<?php echo esc_url( $this->docs_link ); ?>" target="_blank" class="ecdv2-help-link"><span class="dashicons dashicons-editor-help"></span><?php esc_html_e( 'Help', 'wp-easycart' ); ?></a></div>
				<div class="ecdv2-card-body">
					<div class="ecdv2-grid">
						<div class="ecdv2-field"><label class="ecdv2-label" for="ecos_name"><?php esc_html_e( 'Name', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'internal, shown in admin', 'wp-easycart' ); ?></span></label><input type="text" class="ecv2-input" id="ecos_name" value="<?php echo esc_attr( wp_unslash( $o->option_name ) ); ?>" data-track></div>
						<div class="ecdv2-field"><label class="ecdv2-label" for="ecos_label"><?php esc_html_e( 'Label shown to shoppers', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" id="ecos_label" value="<?php echo esc_attr( wp_unslash( $o->option_label ) ); ?>" placeholder="<?php echo esc_attr( wp_unslash( $o->option_name ) ); ?>" data-track><span class="ecdv2-field-desc"><?php esc_html_e( 'Leave blank to use the name.', 'wp-easycart' ); ?></span></div>
						<div class="ecdv2-field">
							<label class="ecdv2-label" for="ecos_type"><?php esc_html_e( 'Type', 'wp-easycart' ); ?></label>
							<select class="ecv2-select ecos-select" id="ecos_type" data-track<?php disabled( $locked ); ?>>
								<optgroup label="<?php esc_attr_e( 'Variations — create stock-tracked variants', 'wp-easycart' ); ?>">
									<?php foreach ( $types as $k => $t ) { if ( 'variation' !== $t['family'] ) { continue; } ?><option value="<?php echo esc_attr( $k ); ?>"<?php selected( $k, $o->option_type ); ?>><?php echo esc_html( $t['label'] ); ?></option><?php } ?>
								</optgroup>
								<optgroup label="<?php echo esc_attr( $this->is_pro ? __( 'Modifiers — adjust the product', 'wp-easycart' ) : sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Modifiers — %s', 'wp-easycart' ), wp_easycart_admin_edition::badge( 'pro' ) ) ); ?>">
									<?php foreach ( $types as $k => $t ) { if ( 'modifier' !== $t['family'] ) { continue; } ?><option value="<?php echo esc_attr( $k ); ?>"<?php selected( $k, $o->option_type ); ?><?php if ( ! $this->is_pro && $k !== $o->option_type ) { echo ' disabled'; } ?>><?php echo esc_html( $t['label'] ); ?></option><?php } ?>
								</optgroup>
							</select>
							<span class="ecdv2-field-desc" id="ecos_type_desc"></span>
						</div>
						<div class="ecdv2-field">
							<label class="ecdv2-label"><?php esc_html_e( 'Requirement', 'wp-easycart' ); ?></label>
							<label class="ecos-toggle-row">
								<span class="ecv2-toggle<?php echo $is_basic ? ' ecv2-toggle-locked' : ''; ?>"><input type="checkbox" id="ecos_required"<?php checked( (bool) $o->option_required ); ?><?php disabled( $is_basic ); ?> data-track><span class="ecv2-toggle-slider"></span></span>
								<span><span class="ecos-toggle-t"><?php esc_html_e( 'Shopper must choose before adding to cart', 'wp-easycart' ); ?></span><small id="ecos_required_hint"><?php echo $is_basic ? esc_html__( 'Variation sets are always required.', 'wp-easycart' ) : esc_html__( 'Turn off for optional add-ons.', 'wp-easycart' ); ?></small></span>
							</label>
						</div>
						<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label" for="ecos_error"><?php esc_html_e( 'Error message', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'shown if required and nothing chosen', 'wp-easycart' ); ?></span></label><input type="text" class="ecv2-input" id="ecos_error" value="<?php echo esc_attr( wp_unslash( $o->option_error_text ) ); ?>" data-track></div>
					</div>
				</div>
			</div>

			<!-- Choices -->
			<div class="ecdv2-card" id="osv2-choices">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Choices', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint" id="ecos_choices_hint"><?php esc_html_e( 'Drag to reorder. Price and weight adjust the product unless you pick Override.', 'wp-easycart' ); ?></span></div>
				<div class="ecdv2-card-body">
					<div class="ecos-ch-wrap">
						<div class="ecos-ch-main">
							<div class="ecos-input-note" id="ecos_input_note" style="display:none">
								<span class="dashicons dashicons-info-outline"></span>
								<div><?php esc_html_e( 'This type has no list of choices — the shopper types or picks a value. Set the default and any price rule below.', 'wp-easycart' ); ?></div>
							</div>
							<table class="ecos-ch-tbl" id="ecos_tbl">
								<thead><tr>
									<th class="ecos-col-drag"></th>
									<th class="ecos-col-sw"><?php esc_html_e( 'Swatch', 'wp-easycart' ); ?></th>
									<th><?php esc_html_e( 'Choice', 'wp-easycart' ); ?></th>
									<th class="ecos-col-sku"><?php esc_html_e( 'SKU suffix', 'wp-easycart' ); ?></th>
									<th class="ecos-col-price"><?php esc_html_e( 'Price', 'wp-easycart' ); ?></th>
									<th class="ecos-col-weight"><?php echo esc_html( sprintf( __( 'Weight (%s)', 'wp-easycart' ), get_option( 'ec_option_paypal_weight_unit' ) ? get_option( 'ec_option_paypal_weight_unit' ) : 'lbs' ) ); ?></th>
									<th class="ecos-col-def"><?php esc_html_e( 'Default', 'wp-easycart' ); ?></th>
									<th class="ecos-col-menu"></th>
								</tr></thead>
								<tbody id="ecos_tbody"></tbody>
							</table>
							<div class="ecos-ch-foot">
								<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecos_add" onclick="ecos.add_choice();"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add choice', 'wp-easycart' ); ?></button>
								<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="ecos_paste" onclick="ecos.paste_open();"><?php esc_html_e( 'Paste a list…', 'wp-easycart' ); ?></button>
								<span class="ecos-grow"></span>
								<span class="ecos-hint" id="ecos_count_hint"></span>
							</div>
							<div class="ecos-paste" id="ecos_paste_box" style="display:none">
								<textarea class="ecv2-input" id="ecos_paste_text" rows="5" placeholder="<?php esc_attr_e( 'Small&#10;Medium&#10;Large, 2.00, -L&#10;X-Large, 4.00, -XL', 'wp-easycart' ); ?>"></textarea>
								<div class="ecos-paste-actions"><span class="ecos-hint"><?php esc_html_e( 'One per line. Optional: name, price, SKU suffix.', 'wp-easycart' ); ?></span><span class="ecos-grow"></span><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecos.paste_close();"><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="ecos.paste_apply();"><?php esc_html_e( 'Add these', 'wp-easycart' ); ?></button></div>
							</div>
							<div class="ecos-stock-note" id="ecos_stock_note"<?php if ( ! $this->stock_rows ) { echo ' style="display:none"'; } ?>>
								<span class="dashicons dashicons-warning"></span>
								<div><b><?php esc_html_e( 'Removing a choice', 'wp-easycart' ); ?></b> <?php echo esc_html( sprintf( __( 'also removes its variant stock rows. This set currently has %1$d stock rows across %2$d products. You will be shown the exact impact before anything is deleted.', 'wp-easycart' ), $this->stock_rows, $this->usage_total ) ); ?></div>
							</div>
						</div>
						<div class="ecos-preview" id="ecos_preview">
							<div class="ecos-preview-head"><?php esc_html_e( 'Storefront preview', 'wp-easycart' ); ?> <span><?php esc_html_e( 'live', 'wp-easycart' ); ?></span></div>
							<div class="ecos-pv-label"><span id="ecos_pv_label"><?php echo esc_html( wp_unslash( '' !== trim( (string) $o->option_label ) ? $o->option_label : $o->option_name ) ); ?></span> <i id="ecos_pv_req"<?php if ( ! $o->option_required ) { echo ' style="display:none"'; } ?>>*</i></div>
							<div id="ecos_pv_body"></div>
							<div class="ecos-pv-note"><?php esc_html_e( 'Preview uses default styling; your theme may differ. Adjustments show as +$X.XX unless set to Override.', 'wp-easycart' ); ?></div>
						</div>
					</div>
				</div>
			</div>

			<!-- Rules -->
			<div class="ecdv2-card" id="osv2-rules">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Rules & validation', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint"><?php esc_html_e( 'Only the rules that apply to this type are shown', 'wp-easycart' ); ?></span></div>
				<div class="ecdv2-card-body">
					<div class="ecdv2-grid">
						<div class="ecdv2-field" data-rule="swatch"><label class="ecdv2-label" for="ecos_meta_swatch_size"><?php esc_html_e( 'Swatch size', 'wp-easycart' ); ?></label><select class="ecv2-select ecos-select" id="ecos_meta_swatch_size" data-track><?php foreach ( array( 20 => __( 'Small (20px)', 'wp-easycart' ), 30 => __( 'Medium (30px)', 'wp-easycart' ), 40 => __( 'Large (40px)', 'wp-easycart' ), 60 => __( 'Extra large (60px)', 'wp-easycart' ) ) as $v => $l ) { ?><option value="<?php echo esc_attr( $v ); ?>"<?php selected( (int) ( isset( $this->meta['swatch_size'] ) ? $this->meta['swatch_size'] : 30 ), $v ); ?>><?php echo esc_html( $l ); ?></option><?php } ?></select></div>
						<div class="ecdv2-field" data-rule="number"><label class="ecdv2-label"><?php esc_html_e( 'Number range', 'wp-easycart' ); ?></label><div class="ecos-range"><input type="number" class="ecv2-input" id="ecos_meta_min" placeholder="<?php esc_attr_e( 'Min', 'wp-easycart' ); ?>" value="<?php echo esc_attr( isset( $this->meta['min'] ) ? $this->meta['min'] : '' ); ?>" data-track><span>–</span><input type="number" class="ecv2-input" id="ecos_meta_max" placeholder="<?php esc_attr_e( 'Max', 'wp-easycart' ); ?>" value="<?php echo esc_attr( isset( $this->meta['max'] ) ? $this->meta['max'] : '' ); ?>" data-track><input type="number" class="ecv2-input" id="ecos_meta_step" placeholder="<?php esc_attr_e( 'Step', 'wp-easycart' ); ?>" value="<?php echo esc_attr( isset( $this->meta['step'] ) ? $this->meta['step'] : '' ); ?>" data-track></div></div>
						<div class="ecdv2-field" data-rule="text"><label class="ecdv2-label"><?php esc_html_e( 'Length', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'characters', 'wp-easycart' ); ?></span></label><div class="ecos-range"><input type="number" class="ecv2-input" id="ecos_meta_min_length" placeholder="<?php esc_attr_e( 'Min', 'wp-easycart' ); ?>" value="<?php echo esc_attr( isset( $this->meta['min_length'] ) ? $this->meta['min_length'] : '' ); ?>" data-track><span>–</span><input type="number" class="ecv2-input" id="ecos_meta_max_length" placeholder="<?php esc_attr_e( 'Max', 'wp-easycart' ); ?>" value="<?php echo esc_attr( isset( $this->meta['max_length'] ) ? $this->meta['max_length'] : '' ); ?>" data-track></div><span class="ecdv2-field-desc"><?php esc_html_e( 'Shoppers must type at least the minimum before adding to cart; the box stops accepting characters at the maximum.', 'wp-easycart' ); ?></span></div>
							<?php
							$ecos_text_rules   = class_exists( 'wp_easycart_text_input_rules' ) ? wp_easycart_text_input_rules::get_rules( $this->meta ) : array( 'case' => 'none', 'allowed' => 'any', 'placeholder' => '' );
							$ecos_text_cases   = array(
								'none'  => __( 'As typed', 'wp-easycart' ),
								'upper' => __( 'UPPERCASE', 'wp-easycart' ),
								'lower' => __( 'lowercase', 'wp-easycart' ),
								'title' => __( 'Title Case', 'wp-easycart' ),
							);
							$ecos_text_allowed = array(
								'any'         => __( 'Anything', 'wp-easycart' ),
								'letters'     => __( 'Letters only', 'wp-easycart' ),
								'letters_space' => __( 'Letters and spaces', 'wp-easycart' ),
								'numbers'     => __( 'Numbers only', 'wp-easycart' ),
								'alnum'       => __( 'Letters and numbers', 'wp-easycart' ),
								'alnum_space' => __( 'Letters, numbers and spaces', 'wp-easycart' ),
							);
							?>
							<div class="ecdv2-field" data-rule="text"><label class="ecdv2-label" for="ecos_meta_text_case"><?php esc_html_e( 'Text case', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'input rule', 'wp-easycart' ); ?></span></label><select class="ecv2-select ecos-select" id="ecos_meta_text_case" data-track><?php foreach ( $ecos_text_cases as $v => $l ) { ?><option value="<?php echo esc_attr( $v ); ?>"<?php selected( $ecos_text_rules['case'], $v ); ?>><?php echo esc_html( $l ); ?></option><?php } ?></select><span class="ecdv2-field-desc"><?php esc_html_e( 'Applied as the shopper types and again when the item is added to the cart.', 'wp-easycart' ); ?></span></div>
							<div class="ecdv2-field" data-rule="text"><label class="ecdv2-label" for="ecos_meta_text_allowed"><?php esc_html_e( 'Allowed characters', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'input rule', 'wp-easycart' ); ?></span></label><select class="ecv2-select ecos-select" id="ecos_meta_text_allowed" data-track><?php foreach ( $ecos_text_allowed as $v => $l ) { ?><option value="<?php echo esc_attr( $v ); ?>"<?php selected( $ecos_text_rules['allowed'], $v ); ?>><?php echo esc_html( $l ); ?></option><?php } ?></select><span class="ecdv2-field-desc"><?php esc_html_e( 'Anything else is removed as the shopper types or pastes.', 'wp-easycart' ); ?></span></div>
							<div class="ecdv2-field" data-rule="text"><label class="ecdv2-label" for="ecos_meta_placeholder"><?php esc_html_e( 'Placeholder', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'hint shown in the empty box', 'wp-easycart' ); ?></span></label><input type="text" class="ecv2-input" id="ecos_meta_placeholder" value="<?php echo esc_attr( $ecos_text_rules['placeholder'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Name to engrave', 'wp-easycart' ); ?>" data-track></div>
						<?php
							/* 6.0.0: file upload types. Empty = the store default list; dangerous types ( scripts, web pages, SVG ) are never offered or accepted. */
							if ( class_exists( 'wp_easycart_customer_uploads' ) ) {
								$ecos_file_types   = wp_easycart_customer_uploads::option_extensions( $this->meta );
								$ecos_file_catalog = wp_easycart_customer_uploads::file_types();
								?>
							<div class="ecdv2-field ecdv2-field-full" data-rule="file">
								<label class="ecdv2-label" id="ecos_file_types_label"><?php esc_html_e( 'Accepted file types', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'what shoppers can upload', 'wp-easycart' ); ?></span></label>
								<div class="ecos-ft-state<?php echo empty( $ecos_file_types ) ? ' is-default' : ''; ?>" id="ecos_ft_state" aria-live="polite">
									<span class="dashicons dashicons-<?php echo empty( $ecos_file_types ) ? 'info-outline' : 'yes'; ?>"></span>
									<span class="ecos-ft-state-text" id="ecos_ft_state_text">
									<?php
									if ( empty( $ecos_file_types ) ) {
										/* translators: %s: list of file types, e.g. JPG, PNG, PDF. */
										echo esc_html( sprintf( __( 'Default types: %s', 'wp-easycart' ), wp_easycart_customer_uploads::display_extensions() ) );
									} else {
										/* translators: %s: list of file types, e.g. JPG, PNG, PDF. */
										echo esc_html( sprintf( __( 'Shoppers can upload: %s', 'wp-easycart' ), wp_easycart_customer_uploads::display_extensions( $ecos_file_types ) ) );
									}
									?>
									</span>
									<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" id="ecos_ft_clear"<?php echo empty( $ecos_file_types ) ? ' style="display:none"' : ''; ?>><?php esc_html_e( 'Use default types', 'wp-easycart' ); ?></button>
								</div>
								<div class="ecos-ft" role="group" aria-labelledby="ecos_file_types_label">
									<?php foreach ( wp_easycart_customer_uploads::file_type_groups() as $ecos_group_key => $ecos_group ) { ?>
									<div class="ecos-ft-group" data-group="<?php echo esc_attr( $ecos_group_key ); ?>">
										<div class="ecos-ft-head"><span class="ecos-ft-name"><?php echo esc_html( $ecos_group['label'] ); ?></span><button type="button" class="ecos-ft-all" data-group="<?php echo esc_attr( $ecos_group_key ); ?>"><?php esc_html_e( 'Select all', 'wp-easycart' ); ?></button></div>
										<div class="ecos-ft-chips">
											<?php foreach ( $ecos_group['types'] as $ecos_type ) { ?>
											<label class="ecos-ft-chip" title="<?php echo esc_attr( '.' . implode( ', .', $ecos_file_catalog[ $ecos_type ]['extensions'] ) ); ?>"><input type="checkbox" class="ecos-ft-cb" value="<?php echo esc_attr( $ecos_type ); ?>"<?php checked( in_array( $ecos_type, $ecos_file_types, true ) ); ?> data-track><span><?php echo esc_html( strtoupper( $ecos_type ) ); ?></span></label>
											<?php } ?>
										</div>
									</div>
									<?php } ?>
								</div>
								<span class="ecdv2-field-desc"><?php esc_html_e( 'Leave every type unticked to accept the default types. Scripts, web pages and SVG files are always refused, and each file\'s contents must match its type.', 'wp-easycart' ); ?></span>
							</div>
								<?php
							}
							?>
						<div class="ecdv2-field" data-rule="all"><label class="ecdv2-label" for="ecos_meta_url_var"><?php esc_html_e( 'URL variable', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'pre-select via link', 'wp-easycart' ); ?></span></label><input type="text" class="ecv2-input" id="ecos_meta_url_var" value="<?php echo esc_attr( isset( $this->meta['url_var'] ) ? $this->meta['url_var'] : '' ); ?>" placeholder="<?php echo esc_attr( sanitize_title( $o->option_name ) ); ?>" data-track><span class="ecdv2-field-desc" id="ecos_url_var_desc"></span></div>
					</div>
					<?php if ( ! $this->is_pro ) { ?>
					<div class="ecos-note ecos-note-brand"><span class="dashicons dashicons-info-outline"></span><div><?php esc_html_e( 'Text, number and dimension types add min/max, step and length rules here. Quantity grid adds row and column limits.', 'wp-easycart' ); ?> <span class="ecv2-chip ecv2-chip-blue"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span></div></div>
					<?php } ?>
				</div>
			</div>

			<!-- Used by -->
			<div class="ecdv2-card" id="osv2-used">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Products using this set', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint" id="ecos_used_hint"><?php echo esc_html( sprintf( _n( '%d product', '%d products', $this->usage_total, 'wp-easycart' ), $this->usage_total ) ); ?><?php if ( $is_basic ) { echo ' · ' . esc_html__( 'variant stock is tracked per product', 'wp-easycart' ); } ?></span><a href="#" class="ecdv2-help-link ecos-link-brand" onclick="<?php echo esc_attr( $assign_click ); ?>"><?php if ( $locked ) { ?><span class="dashicons dashicons-lock"></span> <?php } else { ?>+ <?php } ?><?php esc_html_e( 'Assign to more', 'wp-easycart' ); ?></a></div>
				<div class="ecdv2-card-body">
					<?php if ( empty( $this->usage ) ) { ?>
					<div class="ecos-empty"><?php esc_html_e( 'No products use this set yet.', 'wp-easycart' ); ?> <button type="button" class="ecv2-btn ecv2-btn-sm" onclick="<?php echo esc_attr( $assign_click ); ?>"><?php if ( $locked ) { ?><span class="dashicons dashicons-lock"></span> <?php } ?><?php esc_html_e( 'Assign to products', 'wp-easycart' ); ?></button></div>
					<?php } else { ?>
					<div class="ecos-used" id="ecos_used_list">
						<?php foreach ( $this->usage as $u ) { ?>
						<div class="ecos-u">
							<span class="ecv2-thumb"><?php if ( $u['image'] ) { ?><img src="<?php echo esc_url( $u['image'] ); ?>" alt="" loading="lazy"><?php } else { ?><span class="dashicons dashicons-format-image"></span><?php } ?></span>
							<div class="ecos-u-main">
								<a class="ecv2-link-primary" href="<?php echo esc_url( $u['edit_url'] ); ?>"><?php echo esc_html( $u['title'] ); ?></a>
								<span class="ecv2-sub"><?php
									$bits = array();
									$bits[] = $u['advanced'] ? __( 'Advanced option', 'wp-easycart' ) : sprintf( __( 'Slot %d', 'wp-easycart' ), $u['slot'] );
									if ( $u['other_sets'] ) { $bits[] = sprintf( __( 'also uses %s', 'wp-easycart' ), implode( ', ', $u['other_sets'] ) ); }
									if ( $u['variant_rows'] ) { $bits[] = sprintf( _n( '%d variant', '%d variants', $u['variant_rows'], 'wp-easycart' ), $u['variant_rows'] ) . ( $u['variant_oos'] ? ', ' . sprintf( __( '%d out of stock', 'wp-easycart' ), $u['variant_oos'] ) : '' ); }
									echo esc_html( implode( ' · ', $bits ) );
								?></span>
							</div>
							<span class="ecv2-chip <?php echo $u['active'] ? 'ecv2-chip-green' : 'ecv2-chip-gray'; ?>"><?php echo $u['active'] ? esc_html__( 'Active', 'wp-easycart' ) : esc_html__( 'Inactive', 'wp-easycart' ); ?></span>
							<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="<?php echo esc_url( $u['edit_url'] ); ?>"><?php esc_html_e( 'Open', 'wp-easycart' ); ?></a>
						</div>
						<?php } ?>
					</div>
					<div class="ecos-used-foot"><span class="ecos-hint"><?php echo esc_html( sprintf( __( 'Showing %1$d of %2$d', 'wp-easycart' ), count( $this->usage ), $this->usage_total ) ); ?></span><span class="ecos-grow"></span><?php if ( $this->usage_total > count( $this->usage ) ) { ?><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="ecos.load_more_usage( this );"><?php esc_html_e( 'Show more', 'wp-easycart' ); ?></button><?php } ?><a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( $products_url ); ?>"><?php esc_html_e( 'View all in Products', 'wp-easycart' ); ?></a></div>
					<?php } ?>
				</div>
			</div>

			<!-- Danger -->
			<div class="ecdv2-card ecos-danger" id="osv2-danger">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Danger zone', 'wp-easycart' ); ?></h3></div>
				<div class="ecdv2-card-body ecos-danger-body">
					<div><b><?php esc_html_e( 'Delete this option set', 'wp-easycart' ); ?></b><span><?php echo $this->usage_total ? esc_html( sprintf( __( 'You will see exactly what is affected across %d products and can replace the set instead of removing it. Undo is available for 30 days.', 'wp-easycart' ), $this->usage_total ) ) : esc_html__( 'Nothing else references this set. Undo is available for 30 days.', 'wp-easycart' ); ?></span></div>
					<button type="button" class="ecv2-btn ecv2-btn-danger" onclick="ecv2_catalog.safe_delete( 'option', <?php echo (int) $o->option_id; ?>, { redirect_to: '<?php echo esc_js( $list_url ); ?>' } );"><?php esc_html_e( 'Delete option set…', 'wp-easycart' ); ?></button>
				</div>
			</div>
		</div>
	</div>
	<div id="ecv2-toast-container"></div>
</div>
