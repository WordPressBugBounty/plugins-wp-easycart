<?php
/** User role editor ( V2 ) — $this is wp_easycart_admin_user_role_editor_v2. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
include_once( EC_PLUGIN_DIRECTORY . '/admin/template/products/shared/editor-lite-parts.php' );
$r = $this->role; $list_url = admin_url( 'admin.php?page=wp-easycart-users&subpage=user-roles' );
$upgrade = apply_filters( 'wp_easycart_admin_upgrade_url', 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/?upsell=roles' );
ecv2_lite_open( $this->js_data(), 'ecrl-wrap' );
ecv2_lite_header( array(
	'back_url' => $list_url, 'back_title' => __( 'Back to roles', 'wp-easycart' ), 'title' => wp_unslash( $r->role_label ),
	'sub_html' => esc_html( sprintf( _n( '%d user', '%d users', $this->user_total, 'wp-easycart' ), $this->user_total ) ) . ' · <span class="ecrl-rp-h-count">' . esc_html( sprintf( _n( '%d role price', '%d role prices', $this->price_total, 'wp-easycart' ), $this->price_total ) ) . '</span>' . ( $r->admin_access ? ' · <span class="ecv2-chip ecv2-chip-blue">' . esc_html__( 'Remote admin', 'wp-easycart' ) . '</span>' : '' ) . ( $this->master ? ' · <span class="ecv2-chip ecv2-chip-gray">' . esc_html__( 'Built-in', 'wp-easycart' ) . '</span>' : '' ),
) );
?>
<div class="ecdv2-body">
	<?php ecv2_lite_rail( array(
		__( 'Role', 'wp-easycart' ) => array( array( '#rlv2-details', __( 'Details', 'wp-easycart' ) ), array( '#rlv2-prices', __( 'Role prices', 'wp-easycart' ), $this->price_total ), array( '#rlv2-users', __( 'Users', 'wp-easycart' ), $this->user_total ) ),
		__( 'More', 'wp-easycart' ) => array( array( '#rlv2-danger', __( 'Danger zone', 'wp-easycart' ) ) ),
	), $this->health(), __( 'Role health', 'wp-easycart' ) ); ?>
	<div class="ecdv2-main">
		<?php ecv2_lite_card_open( 'rlv2-details', __( 'Details', 'wp-easycart' ), $this->master ? __( 'Built-in roles keep their name so the storefront can rely on it', 'wp-easycart' ) : '', '<a href="' . esc_url( $this->docs_link ) . '" target="_blank" class="ecdv2-help-link"><span class="dashicons dashicons-editor-help"></span>' . esc_html__( 'Help', 'wp-easycart' ) . '</a>' ); ?>
		<div class="ecdv2-grid">
			<?php ecv2_lite_field( 'eclite_name', __( 'Role name', 'wp-easycart' ), ecv2_lite_text( 'eclite_name', 'name', wp_unslash( $r->role_label ), '', 'data-bind="title"' . ( $this->master ? ' disabled' : '' ) ), '', __( 'Customers are assigned a role on their account. Renaming updates every user, role price and access rule that references it.', 'wp-easycart' ) ); ?>
			<div class="ecdv2-field">
				<label class="ecdv2-label"><?php esc_html_e( 'Remote admin access', 'wp-easycart' ); ?><?php if ( ! $this->pro ) { ?> <span class="ecv2-chip ecv2-chip-blue"><?php echo esc_html( wp_easycart_admin_edition::badge( 'pro' ) ); ?></span><?php } ?></label>
				<?php echo ecv2_lite_toggle_row( 'eclite_access', 'admin_access', (bool) $r->admin_access, __( 'Members can sign in to the EasyCart mobile admin', 'wp-easycart' ), $this->pro ? __( 'Choose which panels they can open below.', 'wp-easycart' ) : wp_easycart_admin_edition::included_text( 'pro' ) ); ?>
				<?php if ( ! $this->pro ) { ?><script>document.getElementById('eclite_access').disabled=true;</script><span class="ecdv2-field-desc"><a href="<?php echo esc_url( $upgrade ); ?>" target="_blank" rel="noopener"><?php echo esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Learn about %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ); ?></a></span><?php } ?>
			</div>
			<?php if ( $this->pro ) { ?>
			<div class="ecdv2-field ecdv2-field-full" id="rlv2_panels" <?php echo $r->admin_access ? '' : 'style="display:none"'; ?>>
				<label class="ecdv2-label"><?php esc_html_e( 'Panels they can open', 'wp-easycart' ); ?></label>
				<div class="ecrl-panels">
					<?php foreach ( $this->available_panels as $k => $label ) { ?><label class="ecrl-panel"><input type="checkbox" class="ecrl-panel-cb" value="<?php echo esc_attr( $k ); ?>"<?php checked( in_array( $k, $this->panels, true ) ); ?> data-track> <?php echo esc_html( $label ); ?></label><?php } ?>
				</div>
			</div>
			<?php } ?>
		</div>
		<?php ecv2_lite_card_close(); ?>

		<?php
		/*
		 * Role prices ( 6.0.0 ): one flow — the search adds products, the toolbar applies a % off to the listed /
		 * selected prices, "Every active product" is the separate bulk action, and the list pages 50 at a time.
		 * Driven by admin/js/user-role-editor-v2.js; element ids are ecrl_rp_* so the older bindings in
		 * settings-lists-v2.js stay inert.
		 */
		ecv2_lite_card_open( 'rlv2-prices', __( 'Role prices', 'wp-easycart' ), __( 'Customers in this role see these prices instead of the regular price', 'wp-easycart' ), '<a href="' . esc_url( $this->docs_link ) . '" target="_blank" class="ecdv2-help-link"><span class="dashicons dashicons-editor-help"></span>' . esc_html__( 'Help', 'wp-easycart' ) . '</a>' ); ?>
		<div class="ecrl-add ecrl-rp-add"><input type="search" class="ecv2-input" id="ecrl_rp_add" placeholder="<?php esc_attr_e( 'Add a product: search by name or SKU…', 'wp-easycart' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Add a product', 'wp-easycart' ); ?>"><div class="ecrl-results" id="ecrl_rp_add_results" style="display:none"></div></div>
		<div class="ecrl-rp-toolbar" id="ecrl_rp_toolbar">
			<input type="search" class="ecv2-input ecrl-rp-q" id="ecrl_rp_q" placeholder="<?php esc_attr_e( 'Filter role prices…', 'wp-easycart' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Filter role prices', 'wp-easycart' ); ?>">
			<div class="ecrl-rp-sel" id="ecrl_rp_sel" style="display:none"><span id="ecrl_rp_sel_count"></span><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-rp="pct-selected"><?php esc_html_e( 'Apply % off to selected…', 'wp-easycart' ); ?></button><button type="button" class="ecv2-btn ecv2-btn-sm" data-rp="remove-selected"><?php esc_html_e( 'Remove selected', 'wp-easycart' ); ?></button></div>
			<span class="ecos-grow"></span>
			<div class="ecrl-rp-actions"><button type="button" class="ecv2-btn ecv2-btn-sm" data-rp="pct"><?php esc_html_e( 'Apply % off…', 'wp-easycart' ); ?></button><span class="ecrl-rp-divider" aria-hidden="true"></span><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" data-rp="pct-all" title="<?php esc_attr_e( 'Bulk action: creates or overwrites a role price for every active product in the catalog', 'wp-easycart' ); ?>"><span class="dashicons dashicons-database"></span><?php esc_html_e( 'Every active product…', 'wp-easycart' ); ?></button></div>
		</div>
		<div class="ecrl-rp-list" id="ecrl_rp_list">
			<div class="ecrl-rp-head"><span><input type="checkbox" id="ecrl_rp_all" title="<?php esc_attr_e( 'Select all on this page', 'wp-easycart' ); ?>"></span><span><?php esc_html_e( 'Product', 'wp-easycart' ); ?></span><span><?php esc_html_e( 'Regular', 'wp-easycart' ); ?></span><span class="ecrl-rp-role"><?php echo esc_html( wp_unslash( $r->role_label ) ); ?></span><span><?php esc_html_e( 'Off', 'wp-easycart' ); ?></span><span></span></div>
			<div class="ecrl-rp-rows" id="ecrl_rp_rows"></div>
			<div class="ecos-used-foot ecrl-pager" id="ecrl_rp_pager"><span class="ecos-hint" id="ecrl_rp_foot"></span><span class="ecos-grow"></span><button type="button" class="ecv2-btn ecv2-btn-sm" id="ecrl_rp_prev" data-rp="prev">&lsaquo; <?php esc_html_e( 'Prev', 'wp-easycart' ); ?></button><button type="button" class="ecv2-btn ecv2-btn-sm" id="ecrl_rp_next" data-rp="next"><?php esc_html_e( 'Next', 'wp-easycart' ); ?> &rsaquo;</button></div>
		</div>
		<div class="ecrl-rp-empty" id="ecrl_rp_empty" style="display:none">
			<span class="dashicons dashicons-tag"></span>
			<b><?php esc_html_e( 'No role prices yet', 'wp-easycart' ); ?></b>
			<p><?php esc_html_e( 'Search for a product above, or apply a percentage to every product.', 'wp-easycart' ); ?></p>
			<div class="ecrl-rp-empty-actions"><button type="button" class="ecv2-btn ecv2-btn-sm" data-rp="focus-add"><span class="dashicons dashicons-search"></span><?php esc_html_e( 'Search for a product', 'wp-easycart' ); ?></button><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" data-rp="pct-all"><?php esc_html_e( 'Apply % off to every product…', 'wp-easycart' ); ?></button></div>
		</div>
		<?php ecv2_lite_card_close(); ?>

		<?php ecv2_lite_card_open( 'rlv2-users', __( 'Users in this role', 'wp-easycart' ), sprintf( _n( '%d user', '%d users', $this->user_total, 'wp-easycart' ), $this->user_total ), $this->user_total ? '<a href="#" class="ecdv2-help-link ecos-link-brand" onclick="ecrole.move_all(); return false;">' . esc_html__( 'Move all to another role…', 'wp-easycart' ) . '</a>' : '', 'ecrl_users_count' ); ?>
		<div class="ecrl-assign">
			<input type="search" class="ecv2-input" id="ecrl_assign_q" placeholder="<?php esc_attr_e( 'Add users: search by name or email…', 'wp-easycart' ); ?>" autocomplete="off">
			<div class="ecrl-results" id="ecrl_assign_results" style="display:none"></div>
		</div>
		<?php if ( $this->role->admin_access ) { ?><div class="ecos-note" style="margin:0 0 10px"><span class="dashicons dashicons-warning"></span><div><?php esc_html_e( 'This role grants remote admin access — anyone you add here can manage the store from the EasyCart apps.', 'wp-easycart' ); ?></div></div><?php } ?>
		<div class="ecrl-users-bar">
			<input type="search" class="ecv2-input" id="ecrl_users_q" placeholder="<?php esc_attr_e( 'Filter users in this role…', 'wp-easycart' ); ?>" autocomplete="off">
			<div class="ecrl-users-sel" id="ecrl_users_sel" style="display:none"><span id="ecrl_sel_count"></span><select class="ecv2-select" id="ecrl_sel_to"></select><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecrole.move_selected();"><?php esc_html_e( 'Move selected', 'wp-easycart' ); ?></button></div>
		</div>
		<div class="ecos-used" id="ecrl_users"></div>
		<div class="ecos-used-foot ecrl-pager" id="ecrl_users_pager" style="display:none"><span class="ecos-hint" id="ecrl_users_foot"></span><span class="ecos-grow"></span><button type="button" class="ecv2-btn ecv2-btn-sm" id="ecrl_prev" onclick="ecrole.users_page( -1 );">&lsaquo; <?php esc_html_e( 'Prev', 'wp-easycart' ); ?></button><button type="button" class="ecv2-btn ecv2-btn-sm" id="ecrl_next" onclick="ecrole.users_page( 1 );"><?php esc_html_e( 'Next', 'wp-easycart' ); ?> &rsaquo;</button></div>
		<?php ecv2_lite_card_close(); ?>

		<div class="ecdv2-card ecos-danger" id="rlv2-danger"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Danger zone', 'wp-easycart' ); ?></h3></div><div class="ecdv2-card-body ecos-danger-body"><div><b><?php esc_html_e( 'Delete this role', 'wp-easycart' ); ?></b><span><?php echo $this->master ? esc_html__( 'Built-in roles cannot be deleted.', 'wp-easycart' ) : esc_html__( 'Users move to a role you choose; role prices for this role are removed. Undo is offered for 15 minutes.', 'wp-easycart' ); ?></span></div><button type="button" class="ecv2-btn ecv2-btn-danger" onclick="ecrole.delete_role( <?php echo (int) $r->role_id; ?>, '<?php echo esc_js( $list_url ); ?>' );"<?php disabled( $this->master ); ?>><?php esc_html_e( 'Delete role…', 'wp-easycart' ); ?></button></div></div>
	</div>
</div>
<?php
/* First page of role prices + strings for user-role-editor-v2.js ( 6.0.0 ). wp_json_encode() escapes "/" so "</script>" cannot occur inside the block. */
$rp_data = array(
	'items'           => $this->prices,
	'total'           => (int) $this->price_total,
	'per'             => wp_easycart_admin_user_role_editor_v2::PRICE_PER_PAGE,
	'active_products' => (int) $this->active_products,
	'label'           => wp_unslash( $r->role_label ),
	'i18n'            => array(
		'inactive'        => __( 'Inactive', 'wp-easycart' ),
		'same'            => __( 'Same as regular', 'wp-easycart' ),
		/* translators: %s: percentage, e.g. 15%. */
		'higher'          => __( '%s higher', 'wp-easycart' ),
		/* translators: %s: percentage, e.g. 15%. */
		'off'             => __( '%s off', 'wp-easycart' ),
		'remove'          => __( 'Remove', 'wp-easycart' ),
		'remove_title'    => __( 'Remove this role price — customers in this role see the regular price again', 'wp-easycart' ),
		/* translators: 1: first row number, 2: last row number, 3: total. */
		'showing'         => __( 'Showing %1$s–%2$s of %3$s', 'wp-easycart' ),
		/* translators: %s: the filter text. */
		'matching'        => __( 'matching “%s”', 'wp-easycart' ),
		'count_one'       => __( '%s role price', 'wp-easycart' ),
		'count_many'      => __( '%s role prices', 'wp-easycart' ),
		/* translators: %s: the filter text. */
		'no_match'        => __( 'No role prices match “%s”.', 'wp-easycart' ),
		'selected'        => __( '%s selected', 'wp-easycart' ),
		'saved'           => __( 'Role price saved.', 'wp-easycart' ),
		/* translators: %s: product title. */
		'added'           => __( 'Added a role price for %s.', 'wp-easycart' ),
		'enter_price'     => __( 'Enter a price of 0 or more.', 'wp-easycart' ),
		'no_products'     => __( 'No matching products.', 'wp-easycart' ),
		'regular_short'   => __( 'regular', 'wp-easycart' ),
		'role_price_short' => __( 'role price', 'wp-easycart' ),
		'set'             => __( 'Set', 'wp-easycart' ),
		'update'          => __( 'Update', 'wp-easycart' ),
		'pct_title'       => __( 'Apply a percentage off', 'wp-easycart' ),
		'pct_label'       => __( 'Percent off the regular price', 'wp-easycart' ),
		'apply_to'        => __( 'Apply to', 'wp-easycart' ),
		/* translators: %s: number of products. */
		'scope_existing'  => __( 'All %s products with a role price', 'wp-easycart' ),
		/* translators: %s: number of products. */
		'scope_selected'  => __( '%s selected products', 'wp-easycart' ),
		'pct_note'        => __( 'Role prices are recalculated from each product’s current regular price. Existing values are overwritten.', 'wp-easycart' ),
		'cancel'          => __( 'Cancel', 'wp-easycart' ),
		'apply'           => __( 'Apply', 'wp-easycart' ),
		'all_title'       => __( 'Apply % off to every active product', 'wp-easycart' ),
		/* translators: %s: number of active products. */
		'all_note'        => __( 'Bulk action: this creates a role price for every one of the %s active products in your catalog and overwrites any role price they already have, using each product’s current regular price.', 'wp-easycart' ),
		/* translators: %s: number of active products. */
		'all_check'       => __( 'I understand this affects all %s active products', 'wp-easycart' ),
		/* translators: %s: number of active products. */
		'all_go'          => __( 'Apply to %s products', 'wp-easycart' ),
		'no_active'       => __( 'There are no active products to price.', 'wp-easycart' ),
		'remove_sel_title' => __( 'Remove selected role prices?', 'wp-easycart' ),
		/* translators: %s: number of role prices. */
		'remove_sel_msg'  => __( 'Remove %s role prices? Customers in this role will see the regular price for those products.', 'wp-easycart' ),
	),
);
?>
<script type="application/json" id="ecrl_rp_data"><?php echo wp_json_encode( $rp_data ); ?></script>
<?php ecv2_lite_close();
