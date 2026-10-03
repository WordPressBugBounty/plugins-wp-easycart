<?php
/**
 * WP EasyCart Admin — boxes and packages ( 6.0.2 ).
 *
 * Settings › Shipping › Boxes ( the box library, a section render in admin/template/settings/shipping-settings.php ) and
 * the order screen's Packages list inside the Fulfillment card: each package, its box, weight and items, its label and
 * tracking, with an editor to split items between boxes, a manual "Add tracking" for stores without a label extension,
 * and "Mark delivered". Label extensions add their own buttons to a package row with the filter
 * wp_easycart_order_package_actions and their own row to the Create shipping label window with
 * wp_easycart_ecv2_label_services ( see order-details.php ).
 *
 * AJAX: ecv2_packages_box_save / _box_delete ( guard ecv2_packages_guard(), Settings access ) and
 * ecv2_order_packages_save / _repack / _track / _delivered / _refresh ( guard ecv2_order_packages_guard(), order access ).
 * Order actions answer with the Packages block redrawn ( order_block_html(), which label extensions also call ).
 *
 * The editor ( packages-v2.js ) is drawn from the block's .ecpk-data JSON: packages ( shipment_id, box, size, weight,
 * items, locked once a label exists ) and the order's shippable lines. Saving sends the same packages list as before;
 * the save only goes through when every shippable unit is in exactly one package ( allocation_error() ).
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_packages_guard' ) ) {
	/**
	 * Settings › Shipping › Boxes: settings access and the packages nonce.
	 */
	function ecv2_packages_guard() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
		}
		check_ajax_referer( 'wp-easycart-ecv2-packages', 'nonce' );
	}
}

if ( ! function_exists( 'ecv2_order_packages_guard' ) ) {
	/**
	 * The order screen's Packages list: order access and the order packages nonce.
	 */
	function ecv2_order_packages_guard() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) && ! current_user_can( 'wpec_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
		}
		check_ajax_referer( 'wp-easycart-ecv2-order-packages', 'nonce' );
	}
}

if ( ! class_exists( 'wp_easycart_admin_packages' ) ) :

	/**
	 * Boxes and packages admin.
	 */
	final class wp_easycart_admin_packages {

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_easycart_ecv2_order_details_packages', array( __CLASS__, 'print_order_block' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_order_screen' ) );
			add_action( 'wp_ajax_ecv2_packages_box_save', array( __CLASS__, 'ajax_box_save' ) );
			add_action( 'wp_ajax_ecv2_packages_box_delete', array( __CLASS__, 'ajax_box_delete' ) );
			add_action( 'wp_ajax_ecv2_order_packages_save', array( __CLASS__, 'ajax_order_save' ) );
			add_action( 'wp_ajax_ecv2_order_packages_repack', array( __CLASS__, 'ajax_order_repack' ) );
			add_action( 'wp_ajax_ecv2_order_packages_track', array( __CLASS__, 'ajax_order_track' ) );
			add_action( 'wp_ajax_ecv2_order_packages_track_all', array( __CLASS__, 'ajax_order_track_all' ) );
			add_action( 'wp_ajax_ecv2_order_packages_delivered', array( __CLASS__, 'ajax_order_delivered' ) );
			add_action( 'wp_ajax_ecv2_order_packages_refresh', array( __CLASS__, 'ajax_order_refresh' ) );
		}

		/**
		 * Asset URL.
		 *
		 * @param string $file Path under admin/.
		 * @return string
		 */
		private static function url( $file ) {
			return plugins_url( '/admin/' . $file, EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		}

		/**
		 * Units, in words for the screen.
		 *
		 * @return array dim, weight.
		 */
		private static function units() {
			return wp_easycart_packages::units();
		}

		/**
		 * Strings and data both screens' script reads.
		 *
		 * @return array
		 */
		private static function script_vars() {
			$units = self::units();
			return array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'units'     => $units,
				'templates' => wp_easycart_packages::carrier_templates(),
				'i18n'      => array(
					'add_box'        => __( 'Add a box', 'wp-easycart' ),
					'edit_box'       => __( 'Edit box', 'wp-easycart' ),
					'delete_confirm' => __( 'Remove this box? Products packed in it go back to automatic packing.', 'wp-easycart' ),
					'saved'          => __( 'Saved.', 'wp-easycart' ),
					'error'          => __( 'That did not work. Please try again.', 'wp-easycart' ),
					'custom'         => __( 'Custom size', 'wp-easycart' ),
					'remove'         => __( 'Remove', 'wp-easycart' ),
					'need_tracking'  => __( 'Enter the tracking number.', 'wp-easycart' ),
					'default'        => __( 'Default', 'wp-easycart' ),
					'active'         => __( 'Active', 'wp-easycart' ),
					'off'            => __( 'Off', 'wp-easycart' ),
					'none'           => __( 'No boxes yet. Add the boxes you pack with, and new orders are packed into them automatically.', 'wp-easycart' ),
					'edit'           => __( 'Edit', 'wp-easycart' ),
					'col_box'        => __( 'Box', 'wp-easycart' ),
					'col_size'       => __( 'Inside size', 'wp-easycart' ),
					'col_empty'      => __( 'Empty weight', 'wp-easycart' ),
					'col_max'        => __( 'Holds up to', 'wp-easycart' ),
					'type_box'       => __( 'Box', 'wp-easycart' ),
					'type_envelope'  => __( 'Envelope', 'wp-easycart' ),
					'type_soft'      => __( 'Soft pack', 'wp-easycart' ),
					/* 6.0.2: the box list's "…" menu, its confirm dialog and automatic names. */
					'actions'        => __( 'Box actions', 'wp-easycart' ),
					'make_default'   => __( 'Make default', 'wp-easycart' ),
					'turn_off'       => __( 'Stop using for packing', 'wp-easycart' ),
					'turn_on'        => __( 'Use for packing', 'wp-easycart' ),
					'remove_title'   => __( 'Remove box', 'wp-easycart' ),
					'cancel'         => __( 'Cancel', 'wp-easycart' ),
					'auto_name'      => __( 'Leave empty to name it by its size', 'wp-easycart' ),
					'default_hint'   => __( 'Used for orders that cannot be packed by size.', 'wp-easycart' ),
					'default_locked' => __( 'This is the default box. To move the default, choose Make default in another box’s … menu.', 'wp-easycart' ),
					'repack'          => __( 'Pack again', 'wp-easycart' ),
					'repack_title'    => __( 'Pack again?', 'wp-easycart' ),
					'repack_ask'      => __( 'Packages without a label are rebuilt from your box settings. Changes you made to them are lost.', 'wp-easycart' ),
					'delivered'       => __( 'Mark delivered', 'wp-easycart' ),
					'delivered_title' => __( 'Mark delivered?', 'wp-easycart' ),
					'delivered_ask'   => __( 'The package is marked delivered. Once every package has arrived, the order reads as delivered; a partly refunded order keeps its Partial Refund status.', 'wp-easycart' ),
					'length'          => __( 'Length', 'wp-easycart' ),
					'width'           => __( 'Width', 'wp-easycart' ),
					'height'          => __( 'Height', 'wp-easycart' ),
					'weight'          => __( 'Weight', 'wp-easycart' ),
				) + self::editor_strings(),
			);
		}

		/**
		 * The package editor's wording ( order screen, 6.0.2 ).
		 *
		 * @since 6.0.2
		 * @return array
		 */
		private static function editor_strings() {
			return array(
				/* translators: %d: number of items. */
				'items_one'         => _n( '%d item', '%d items', 1, 'wp-easycart' ),
				/* translators: %d: number of items. */
				'items_many'        => _n( '%d item', '%d items', 2, 'wp-easycart' ),
				/* translators: 1: order number, 2: number of items, e.g. "5 items". */
				'ed_sub'            => __( 'Order %1$s · %2$s to pack', 'wp-easycart' ),
				'ed_intro'          => __( 'Choose a box for each package and what goes in it. Every item goes in exactly one package.', 'wp-easycart' ),
				'ed_planned'        => __( 'These packages were worked out from your box settings and are not saved yet. Saving keeps them for this order.', 'wp-easycart' ),
				'ed_locked_intro'   => __( 'Packages that already have a label keep their box and items.', 'wp-easycart' ),
				/* translators: %d: package number. */
				'ed_package'        => __( 'Package %d', 'wp-easycart' ),
				/* translators: %d: package number. */
				'ed_remove_pkg'     => __( 'Remove package %d', 'wp-easycart' ),
				'ed_box'            => __( 'Box', 'wp-easycart' ),
				/* translators: %s: box name. */
				'ed_not_in_use'     => __( '%s ( not in use )', 'wp-easycart' ),
				/* translators: %s: in or cm. */
				'ed_size'           => __( 'Size ( %s )', 'wp-easycart' ),
				/* translators: %s: lb or kg. */
				'ed_weight'         => __( 'Weight ( %s )', 'wp-easycart' ),
				/* translators: %s: inside size, e.g. "12 × 10 × 6 in". */
				'ed_inside'         => __( 'Inside %s', 'wp-easycart' ),
				/* translators: %s: weight, e.g. "0.5 lb". */
				'ed_box_weighs'     => __( 'box weighs %s', 'wp-easycart' ),
				/* translators: %s: weight, e.g. "20 lb". */
				'ed_holds'          => __( 'holds up to %s', 'wp-easycart' ),
				'ed_w_auto'         => __( 'Worked out from the items and the box.', 'wp-easycart' ),
				'ed_w_none'         => __( 'These items have no weight saved. Enter the packed weight.', 'wp-easycart' ),
				'ed_w_manual'       => __( 'Entered by you.', 'wp-easycart' ),
				/* translators: %s: weight worked out from the items, e.g. "2.5 lb". */
				'ed_w_reset'        => __( 'Use %s', 'wp-easycart' ),
				/* translators: %s: weight, e.g. "20 lb". */
				'ed_w_over'         => __( 'Heavier than this box holds ( %s ).', 'wp-easycart' ),
				'ed_items'          => __( 'Items', 'wp-easycart' ),
				/* translators: %s: weight of one unit, e.g. "0.4 lb". */
				'ed_each'           => __( '%s each', 'wp-easycart' ),
				/* translators: %d: quantity ordered. */
				'ed_of'             => __( 'of %d', 'wp-easycart' ),
				/* translators: 1: item name, 2: package number. */
				'ed_qty_group'      => __( 'Quantity of %1$s in package %2$d', 'wp-easycart' ),
				/* translators: %s: item name. */
				'ed_fewer'          => __( 'One fewer %s', 'wp-easycart' ),
				/* translators: %s: item name. */
				'ed_more'           => __( 'One more %s', 'wp-easycart' ),
				'ed_qty'            => __( 'Quantity', 'wp-easycart' ),
				'ed_move'           => __( 'Move', 'wp-easycart' ),
				/* translators: %s: item name. */
				'ed_move_label'     => __( 'Move %s to another package', 'wp-easycart' ),
				/* translators: 1: quantity, 2: package, e.g. "Package 2 ( Medium box )". */
				'ed_move_all_to'    => __( 'Move all %1$d to %2$s', 'wp-easycart' ),
				/* translators: %s: package, e.g. "Package 2 ( Medium box )". */
				'ed_move_one_to'    => __( 'Move 1 to %s', 'wp-easycart' ),
				/* translators: %s: package, e.g. "Package 2 ( Medium box )". */
				'ed_move_to'        => __( 'Move to %s', 'wp-easycart' ),
				'ed_new_pkg'        => __( 'a new package', 'wp-easycart' ),
				'ed_take_out'       => __( 'Take out of this package', 'wp-easycart' ),
				'ed_empty'          => __( 'Nothing in this package yet. Move items here, or remove it: empty packages are not saved.', 'wp-easycart' ),
				'ed_tray'           => __( 'Not in a package yet', 'wp-easycart' ),
				/* translators: %d: quantity not packed yet. */
				'ed_left'           => __( '%d left', 'wp-easycart' ),
				'ed_add'            => __( 'Add to', 'wp-easycart' ),
				/* translators: %s: item name. */
				'ed_add_label'      => __( 'Add %s to a package', 'wp-easycart' ),
				/* translators: 1: quantity, 2: package, e.g. "Package 1 ( Medium box )". */
				'ed_add_all_to'     => __( 'Add all %1$d to %2$s', 'wp-easycart' ),
				/* translators: %s: package, e.g. "Package 1 ( Medium box )". */
				'ed_add_one_to'     => __( 'Add 1 to %s', 'wp-easycart' ),
				/* translators: %s: package, e.g. "Package 1 ( Medium box )". */
				'ed_add_to'         => __( 'Add to %s', 'wp-easycart' ),
				'ed_add_everything' => __( 'Pack all into', 'wp-easycart' ),
				'ed_over_title'     => __( 'More than the order has', 'wp-easycart' ),
				/* translators: %d: quantity packed too many times. */
				'ed_over_row'       => __( '%d too many', 'wp-easycart' ),
				'ed_trim'           => __( 'Take out the extra', 'wp-easycart' ),
				/* translators: %s: item name. */
				'ed_locked_over'    => __( 'Packages with a label hold more %s than the order has now.', 'wp-easycart' ),
				'ed_add_pkg'        => __( 'Add package', 'wp-easycart' ),
				'ed_no_boxes'       => __( 'Save the boxes you ship in under Settings › Shipping to choose them here.', 'wp-easycart' ),
				'ed_boxes_link'     => __( 'Set up boxes', 'wp-easycart' ),
				'ed_status_ok'      => __( 'Every item is packed.', 'wp-easycart' ),
				/* translators: %s: number of items, e.g. "2 items". */
				'ed_status_under'   => __( 'Not in a package yet: %s.', 'wp-easycart' ),
				/* translators: %s: number of items, e.g. "1 item". */
				'ed_status_over'    => __( 'Packed more than once: %s.', 'wp-easycart' ),
				'ed_status_same'    => __( 'No changes yet.', 'wp-easycart' ),
				'ed_fix_first'      => __( 'Put every item in exactly one package to save.', 'wp-easycart' ),
				'ed_dirty'          => __( 'Unsaved changes', 'wp-easycart' ),
				'ed_saving'         => __( 'Saving…', 'wp-easycart' ),
				'ed_save'           => __( 'Save packages', 'wp-easycart' ),
				'ed_discard_title'  => __( 'Discard changes?', 'wp-easycart' ),
				'ed_discard_ask'    => __( 'Your changes to the packages have not been saved.', 'wp-easycart' ),
				'ed_discard'        => __( 'Discard changes', 'wp-easycart' ),
				'ed_keep'           => __( 'Keep editing', 'wp-easycart' ),
				/* translators: 1: quantity and item, e.g. "2 × Tee", 2: package, e.g. "Package 2". */
				'ed_moved'          => __( 'Moved %1$s to %2$s.', 'wp-easycart' ),
				/* translators: %s: quantity and item, e.g. "2 × Tee". */
				'ed_taken_out'      => __( '%s is not in a package now.', 'wp-easycart' ),
				/* translators: %s: package, e.g. "Package 3". */
				'ed_added_pkg'      => __( '%s added.', 'wp-easycart' ),
				/* translators: %s: package, e.g. "Package 3". */
				'ed_removed_pkg'    => __( '%s removed.', 'wp-easycart' ),
				'ed_locked_note'    => __( 'This package has a label, so its box and items stay as they are.', 'wp-easycart' ),
				'ed_close'          => __( 'Close', 'wp-easycart' ),
			);
		}

		// Settings › Shipping › Boxes.

		/**
		 * Section enqueue ( Settings › Shipping ).
		 */
		public static function enqueue_settings() {
			wp_enqueue_style( 'wp_easycart_admin_packages_v2_css', self::url( 'css/packages-v2.css' ), array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_packages_v2_js', self::url( 'js/packages-v2.js' ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wp_easycart_admin_packages_v2_js',
				'ecpk_vars',
				array_merge(
					self::script_vars(),
					array(
						'nonce' => wp_create_nonce( 'wp-easycart-ecv2-packages' ),
						'boxes' => self::boxes_for_js(),
					)
				)
			);
		}

		/**
		 * Boxes for the editor.
		 *
		 * @return array
		 */
		private static function boxes_for_js() {
			$out = array();
			foreach ( wp_easycart_packages::boxes( false ) as $box ) {
				$out[] = array(
					'package_id'       => (int) $box->package_id,
					'label'            => (string) $box->label,
					'package_type'     => (string) $box->package_type,
					'length'           => (float) $box->length,
					'width'            => (float) $box->width,
					'height'           => (float) $box->height,
					'box_weight'       => (float) $box->box_weight,
					'max_weight'       => (float) $box->max_weight,
					'carrier_template' => (string) $box->carrier_template,
					'is_default'       => (int) $box->is_default,
					'is_active'        => (int) $box->is_active,
				);
			}
			return $out;
		}

		/**
		 * Section render: the box list ( drawn by packages-v2.js from ecpk_vars.boxes ) and its editor.
		 */
		public static function render_boxes() {
			if ( ! wp_easycart_packages::ready() ) {
				echo '<p class="ecpk-muted">' . esc_html__( 'Boxes appear once the WP EasyCart database update has finished.', 'wp-easycart' ) . '</p>';
				return;
			}
			$units = self::units();
			?>
			<div class="ecpk-boxes" id="ecpk_boxes">
				<div class="ecpk-boxes-list" id="ecpk_boxes_list" aria-live="polite"></div>
				<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecpk_box_add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add a box', 'wp-easycart' ); ?></button>
			</div>
			<div class="ecpk-modal-backdrop" id="ecpk_box_backdrop" hidden></div>
			<div class="ecpk-modal" id="ecpk_box_modal" role="dialog" aria-modal="true" aria-labelledby="ecpk_box_title" hidden>
				<div class="ecpk-modal-head"><h3 id="ecpk_box_title"><?php esc_html_e( 'Add a box', 'wp-easycart' ); ?></h3><button type="button" class="ecpk-x" data-ecpk-close aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>">&times;</button></div>
				<div class="ecpk-modal-body">
					<input type="hidden" id="ecpk_box_id" value="0" />
					<label class="ecpk-field"><span><?php esc_html_e( 'Name', 'wp-easycart' ); ?> <em class="ecpk-muted"><?php esc_html_e( '( optional )', 'wp-easycart' ); ?></em></span><input type="text" id="ecpk_box_label" class="ecv2-input" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Medium box', 'wp-easycart' ); ?>" /><small><?php esc_html_e( 'Leave empty to name it by its size.', 'wp-easycart' ); ?></small></label>
					<label class="ecpk-field"><span><?php esc_html_e( 'Type', 'wp-easycart' ); ?></span>
						<select id="ecpk_box_type" class="ecv2-select">
							<option value="box"><?php esc_html_e( 'Box', 'wp-easycart' ); ?></option>
							<option value="envelope"><?php esc_html_e( 'Envelope or mailer', 'wp-easycart' ); ?></option>
							<option value="soft"><?php esc_html_e( 'Poly bag or soft pack', 'wp-easycart' ); ?></option>
						</select>
					</label>
					<div class="ecpk-field"><span><?php /* translators: %s: in or cm. */ echo esc_html( sprintf( __( 'Inside size ( %s )', 'wp-easycart' ), $units['dim'] ) ); ?></span>
						<div class="ecpk-dims">
							<input type="number" id="ecpk_box_length" class="ecv2-input" min="0" step="0.01" aria-label="<?php esc_attr_e( 'Length', 'wp-easycart' ); ?>" placeholder="<?php esc_attr_e( 'Length', 'wp-easycart' ); ?>" />
							<span>&times;</span>
							<input type="number" id="ecpk_box_width" class="ecv2-input" min="0" step="0.01" aria-label="<?php esc_attr_e( 'Width', 'wp-easycart' ); ?>" placeholder="<?php esc_attr_e( 'Width', 'wp-easycart' ); ?>" />
							<span>&times;</span>
							<input type="number" id="ecpk_box_height" class="ecv2-input" min="0" step="0.01" aria-label="<?php esc_attr_e( 'Height', 'wp-easycart' ); ?>" placeholder="<?php esc_attr_e( 'Height', 'wp-easycart' ); ?>" />
						</div>
					</div>
					<div class="ecpk-two">
						<label class="ecpk-field"><span><?php /* translators: %s: lb or kg. */ echo esc_html( sprintf( __( 'Empty box weight ( %s )', 'wp-easycart' ), $units['weight'] ) ); ?></span><input type="number" id="ecpk_box_weight" class="ecv2-input" min="0" step="0.001" placeholder="0" /></label>
						<label class="ecpk-field"><span><?php /* translators: %s: lb or kg. */ echo esc_html( sprintf( __( 'Most it holds ( %s )', 'wp-easycart' ), $units['weight'] ) ); ?></span><input type="number" id="ecpk_box_max" class="ecv2-input" min="0" step="0.001" placeholder="<?php esc_attr_e( 'No limit', 'wp-easycart' ); ?>" /></label>
					</div>
					<label class="ecpk-field" id="ecpk_box_template_row" hidden><span><?php esc_html_e( 'Carrier box', 'wp-easycart' ); ?></span>
						<select id="ecpk_box_template" class="ecv2-select"><option value=""><?php esc_html_e( 'None ( my own box )', 'wp-easycart' ); ?></option></select>
						<small><?php esc_html_e( 'A carrier\'s own box, such as a flat rate box, so labels are bought at its price.', 'wp-easycart' ); ?></small>
					</label>
					<label class="ecpk-check"><input type="checkbox" id="ecpk_box_default" aria-describedby="ecpk_box_default_note" /> <?php esc_html_e( 'Default box', 'wp-easycart' ); ?></label>
					<small class="ecpk-check-note" id="ecpk_box_default_note" hidden></small>
					<label class="ecpk-check"><input type="checkbox" id="ecpk_box_active" checked="checked" /> <?php esc_html_e( 'Use this box when packing orders', 'wp-easycart' ); ?></label>
					<p class="ecpk-error" id="ecpk_box_error" role="alert" hidden></p>
				</div>
				<div class="ecpk-modal-foot">
					<button type="button" class="ecv2-btn" data-ecpk-close><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
					<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecpk_box_save"><?php esc_html_e( 'Save box', 'wp-easycart' ); ?></button>
				</div>
			</div>
			<?php
		}

		/**
		 * AJAX: add or change a box.
		 */
		public static function ajax_box_save() {
			ecv2_packages_guard();
			$raw  = isset( $_POST['box'] ) ? json_decode( wp_unslash( $_POST['box'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; every value is cleaned by wp_easycart_packages::save_box().
			$done = wp_easycart_packages::save_box( is_array( $raw ) ? $raw : array() );
			if ( is_wp_error( $done ) ) {
				wp_send_json_error( array( 'message' => $done->get_error_message() ) );
			}
			wp_send_json_success(
				array(
					'boxes'   => self::boxes_for_js(),
					'message' => __( 'Box saved.', 'wp-easycart' ),
				)
			);
		}

		/**
		 * AJAX: remove a box.
		 */
		public static function ajax_box_delete() {
			ecv2_packages_guard();
			$id = isset( $_POST['package_id'] ) ? absint( wp_unslash( $_POST['package_id'] ) ) : 0;
			wp_easycart_packages::delete_box( $id );
			wp_send_json_success(
				array(
					'boxes'   => self::boxes_for_js(),
					'message' => __( 'Box removed.', 'wp-easycart' ),
				)
			);
		}

		// Order screen.

		/**
		 * The order details page is open.
		 *
		 * @return bool
		 */
		private static function on_order_screen() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
			return isset( $_GET['page'], $_GET['order_id'], $_GET['ec_admin_form_action'] ) && 'wp-easycart-orders' === $_GET['page'] && ( ! isset( $_GET['subpage'] ) || 'orders' === $_GET['subpage'] ) && 'edit' === $_GET['ec_admin_form_action'];
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * Action admin_enqueue_scripts: the Packages list's script on the order screen.
		 */
		public static function enqueue_order_screen() {
			if ( ! self::on_order_screen() || ! wp_easycart_packages::ready() ) {
				return;
			}
			wp_enqueue_style( 'wp_easycart_admin_packages_v2_css', self::url( 'css/packages-v2.css' ), array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_packages_v2_js', self::url( 'js/packages-v2.js' ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wp_easycart_admin_packages_v2_js',
				'ecpk_vars',
				array_merge(
					self::script_vars(),
					array(
						'order_nonce' => wp_create_nonce( 'wp-easycart-ecv2-order-packages' ),
						'boxes'       => self::boxes_for_js(),
						'boxes_url'   => class_exists( 'wp_easycart_admin_settings_registry' ) ? wp_easycart_admin_settings_registry::page_url( 'shipping-settings', 'ec_option_pack_new_orders' ) : admin_url( 'admin.php?page=wp-easycart-settings&subpage=shipping-settings' ),
					)
				)
			);
		}

		/**
		 * Action wp_easycart_ecv2_order_details_packages: the Packages block inside the Fulfillment card.
		 *
		 * @param object $order Order row.
		 * @param string $state Fulfillment state.
		 */
		public static function print_order_block( $order, $state = '' ) {
			if ( ! is_object( $order ) || in_array( $state, array( 'digital', 'pickup' ), true ) || ! wp_easycart_packages::ready() ) {
				return;
			}
			if ( function_exists( 'wp_easycart_order_is_local_pickup' ) && wp_easycart_order_is_local_pickup( $order ) ) {
				return;
			}
			echo '<div class="ecpk-order" id="ecpk_order" data-order-id="' . esc_attr( (int) $order->order_id ) . '">';
			echo self::order_block_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			echo '</div>';
			self::print_order_modals( $order );
		}

		/**
		 * The Packages block's inner HTML ( also sent back by the AJAX actions ).
		 *
		 * @param object $order Order row.
		 * @return string
		 */
		public static function order_block_html( $order ) {
			$order_id = (int) $order->order_id;
			$packages = wp_easycart_shipments::plan( $order_id );
			if ( ! $packages ) {
				return '';
			}
			$returns = array();
			foreach ( wp_easycart_shipments::for_order( $order_id ) as $row ) {
				if ( ! empty( $row->is_return ) ) {
					$returns[] = $row;
				}
			}
			$order_lines = wp_easycart_packages::lines( $order_id );
			$details     = self::line_details( $order_id );
			$titles      = array();
			foreach ( $order_lines as $line ) {
				$titles[ (int) $line->orderdetail_id ] = (string) $line->title;
			}
			$planned = ! empty( $packages[0]->is_planned );
			$waiting = 0;
			$in_box  = array();
			foreach ( $packages as $package ) {
				/* 6.0.2: a fulfillment partner's package is the partner's to ship: the store's package editor leaves it alone. */
				if ( 'packed' === $package->status && empty( $package->partner ) ) {
					++$waiting;
				}
				foreach ( $package->items as $detail_id => $qty ) {
					$in_box[ (int) $detail_id ] = ( isset( $in_box[ (int) $detail_id ] ) ? $in_box[ (int) $detail_id ] : 0 ) + (int) $qty;
				}
			}
			// 6.0.2: units in no package ( a line added or changed after the packages were saved, or after every label was
			// bought ) keep the editor open, so they can still be packed.
			$unpacked = 0;
			foreach ( $order_lines as $line ) {
				if ( ! empty( $line->is_shippable ) && (int) $line->quantity > 0 ) {
					$unpacked += max( 0, (int) $line->quantity - ( isset( $in_box[ (int) $line->orderdetail_id ] ) ? $in_box[ (int) $line->orderdetail_id ] : 0 ) );
				}
			}
			$editable = $waiting > 0 || $unpacked > 0;

			$html  = '<div class="ecpk-order-head">';
			$html .= '<span class="ecpk-eyebrow">' . esc_html__( 'Packages', 'wp-easycart' ) . ' <span class="ecpk-count">' . esc_html( (string) count( $packages ) ) . '</span></span>';
			if ( $editable ) {
				$html .= '<span class="ecpk-order-tools">';
				$html .= '<button type="button" class="ecv2-btn ecv2-btn-sm ecpk-tool" data-ecpk-edit><span class="dashicons dashicons-edit" aria-hidden="true"></span> ' . esc_html__( 'Edit packages', 'wp-easycart' ) . '</button>';
				$html .= '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost ecpk-tool" data-ecpk-repack title="' . esc_attr__( 'Rebuild the packages without a label from your box settings', 'wp-easycart' ) . '"><span class="dashicons dashicons-update" aria-hidden="true"></span> ' . esc_html__( 'Pack again', 'wp-easycart' ) . '</button>';
				$html .= '</span>';
			}
			$html .= '</div>';
			if ( $planned ) {
				$html .= '<p class="ecpk-note is-planned"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><span>' . esc_html__( 'Suggested from your box settings and not saved yet. They are saved when you edit them or buy a label.', 'wp-easycart' ) . '</span></p>';
			}
			if ( $unpacked > 0 ) {
				/* translators: %d: number of items. */
				$html .= '<p class="ecpk-note is-warning"><span class="dashicons dashicons-warning" aria-hidden="true"></span><span>' . esc_html( sprintf( _n( '%d item is not in a package yet. Edit the packages to pack it.', '%d items are not in a package yet. Edit the packages to pack them.', $unpacked, 'wp-easycart' ), $unpacked ) ) . '</span></p>';
			}
			$html .= '<ol class="ecpk-list">';
			foreach ( $packages as $i => $package ) {
				$html .= self::package_row_html( $order, $package, $i + 1, $titles );
			}
			$html .= '</ol>';
			if ( $returns ) {
				$html .= '<div class="ecpk-order-head is-returns"><span class="ecpk-eyebrow">' . esc_html__( 'Return labels', 'wp-easycart' ) . '</span></div><ol class="ecpk-list is-returns">';
				foreach ( $returns as $i => $package ) {
					$html .= self::package_row_html( $order, $package, $i + 1, $titles );
				}
				$html .= '</ol>';
			}
			$data = array();
			$held = array();
			foreach ( $packages as $package ) {
				if ( ! empty( $package->partner ) ) {
					/* 6.0.2: not the store's to edit, and its units are not the store's to pack. */
					foreach ( $package->items as $detail_id => $qty ) {
						$held[ (int) $detail_id ] = ( isset( $held[ (int) $detail_id ] ) ? $held[ (int) $detail_id ] : 0 ) + (int) $qty;
					}
					continue;
				}
				$data[] = array(
					'shipment_id'  => (int) $package->shipment_id,
					'package_id'   => (int) $package->package_id,
					'package_name' => (string) $package->package_name,
					'length'       => (float) $package->length,
					'width'        => (float) $package->width,
					'height'       => (float) $package->height,
					'weight'       => (float) $package->weight,
					'items'        => (object) $package->items,
					'locked'       => 'packed' !== $package->status,
					'status'       => (string) $package->status,
					'label'        => self::status_words( $package ),
					'tracking'     => (string) $package->tracking_number,
				);
			}
			$lines = array();
			foreach ( $order_lines as $line ) {
				$store_qty = (int) $line->quantity - ( isset( $held[ (int) $line->orderdetail_id ] ) ? $held[ (int) $line->orderdetail_id ] : 0 );
				if ( ! empty( $line->is_shippable ) && $store_qty > 0 && '' === wp_easycart_packages::line_partner( $line ) ) {
					$lines[] = array(
						'id'     => (int) $line->orderdetail_id,
						'title'  => (string) $line->title,
						'qty'    => $store_qty,
						'weight' => (float) $line->weight,
						'sku'    => (string) $line->model_number,
						'detail' => isset( $details[ (int) $line->orderdetail_id ] ) ? $details[ (int) $line->orderdetail_id ] : '',
					);
				}
			}
			$html .= '<script type="application/json" class="ecpk-data">' . wp_json_encode(
				array(
					'order_id' => $order_id,
					'planned'  => $planned,
					'packages' => $data,
					'lines'    => $lines,
				),
				JSON_HEX_TAG | JSON_HEX_AMP
			) . '</script>';
			return $html;
		}

		/**
		 * The chosen options of an order's lines, short, so lines of the same product can be told apart in the package
		 * editor ( "M / Blue" ).
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 * @return array orderdetail_id => text.
		 */
		private static function line_details( $order_id ) {
			global $wpdb;
			$parts = array();
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT orderdetail_id, optionitem_name_1, optionitem_name_2, optionitem_name_3, optionitem_name_4, optionitem_name_5 FROM ec_orderdetail WHERE order_id = %d', (int) $order_id ) );
			foreach ( (array) $rows as $row ) {
				foreach ( array( 1, 2, 3, 4, 5 ) as $n ) {
					$value = trim( wp_strip_all_tags( (string) $row->{'optionitem_name_' . $n} ) );
					if ( '' !== $value ) {
						$parts[ (int) $row->orderdetail_id ][] = $value;
					}
				}
			}
			$options = $wpdb->get_results( $wpdb->prepare( 'SELECT ec_order_option.orderdetail_id, ec_order_option.option_type, ec_order_option.optionitem_name, ec_order_option.option_value FROM ec_order_option INNER JOIN ec_orderdetail ON ec_orderdetail.orderdetail_id = ec_order_option.orderdetail_id WHERE ec_orderdetail.order_id = %d ORDER BY ec_order_option.order_option_id ASC', (int) $order_id ) );
			foreach ( (array) $options as $option ) {
				if ( 'file' === $option->option_type ) {
					continue;
				}
				$value = trim( wp_strip_all_tags( 'grid' === $option->option_type ? $option->optionitem_name . ' ( ' . $option->option_value . ' )' : (string) $option->option_value ) );
				if ( '' !== $value ) {
					$parts[ (int) $option->orderdetail_id ][] = $value;
				}
			}
			$out = array();
			foreach ( $parts as $detail_id => $values ) {
				$short = array();
				foreach ( array_slice( $values, 0, 4 ) as $value ) {
					$short[] = ( function_exists( 'mb_strlen' ) && mb_strlen( $value ) > 40 ) ? mb_substr( $value, 0, 39 ) . '…' : $value;
				}
				$out[ $detail_id ] = implode( ' / ', $short );
			}
			return $out;
		}

		/**
		 * A package's status in words: the carrier's tracking status once it has one, else the package status.
		 *
		 * @since 6.0.2
		 * @param object $package Package.
		 * @return string
		 */
		private static function status_words( $package ) {
			if ( ! empty( $package->is_planned ) && empty( $package->partner ) ) {
				return __( 'Suggested', 'wp-easycart' );
			}
			$words = ( '' !== (string) $package->tracking_status && 'voided' !== $package->status ) ? wp_easycart_shipments::tracking_label( $package->tracking_status ) : '';
			return '' !== $words ? $words : wp_easycart_shipments::status_label( $package->status, $package );
		}

		/**
		 * One package row.
		 *
		 * @param object $order   Order row.
		 * @param object $package Package.
		 * @param int    $number  Position.
		 * @param array  $titles  orderdetail_id => title.
		 * @return string
		 */
		private static function package_row_html( $order, $package, $number, $titles ) {
			$units = self::units();
			$size  = ( $package->length > 0 && $package->width > 0 ) ? self::num( $package->length ) . ' × ' . self::num( $package->width ) . ( $package->height > 0 ? ' × ' . self::num( $package->height ) : '' ) . ' ' . $units['dim'] : '';
			$name  = (string) $package->package_name;
			/* 6.0.2: a box named for its size ( "Box 12 × 10 × 6 in" ) does not repeat the size. */
			$meta = array_filter( array( $name, ( '' !== $size && false === strpos( $name, $size ) ) ? $size : '', $package->weight > 0 ? self::num( $package->weight ) . ' ' . $units['weight'] : '' ) );
			$tone = array(
				'packed'    => 'gray',
				'label'     => 'blue',
				'shipped'   => 'blue',
				'delivered' => 'green',
				'returned'  => 'amber',
				'exception' => 'red',
				'voided'    => 'gray',
			);
			/* 6.0.2: a fulfillment partner's package ( "With Printful" while it waits, never "Suggested" ). */
			$partner = isset( $package->partner ) ? (string) $package->partner : '';
			if ( '' !== $partner && 'packed' === $package->status ) {
				$tone['packed'] = 'blue';
			}
			$tone = ( ! empty( $package->is_planned ) && '' === $partner ) ? 'planned' : ( isset( $tone[ $package->status ] ) ? $tone[ $package->status ] : 'gray' );
			$icon = array(
				'packed'    => 'archive',
				'label'     => 'media-text',
				'shipped'   => 'airplane',
				'delivered' => 'yes-alt',
				'returned'  => 'undo',
				'exception' => 'warning',
				'voided'    => 'dismiss',
			);
			/* translators: %d: package number. */
			$title = ! empty( $package->is_return ) ? sprintf( __( 'Return %d', 'wp-easycart' ), $number ) : sprintf( __( 'Package %d', 'wp-easycart' ), $number );
			$html  = '<li class="ecpk-pkg is-' . esc_attr( $package->status ) . ( ! empty( $package->is_planned ) ? ' is-planned' : '' ) . '" data-shipment-id="' . esc_attr( (int) $package->shipment_id ) . '">';
			$html .= '<span class="ecpk-pkg-icon dashicons dashicons-' . esc_attr( isset( $icon[ $package->status ] ) ? $icon[ $package->status ] : 'archive' ) . '" aria-hidden="true"></span>';
			$html .= '<div class="ecpk-pkg-main"><div class="ecpk-pkg-title"><b>' . esc_html( $title ) . '</b> <span class="ecpk-chip ecpk-chip-' . esc_attr( $tone ) . ' ecv2-chip ecv2-chip-' . esc_attr( $tone ) . '">' . esc_html( self::status_words( $package ) ) . '</span></div>';
			if ( $meta ) {
				$html .= '<div class="ecpk-pkg-meta">' . esc_html( implode( ' · ', $meta ) ) . '</div>';
			}
			if ( $package->items ) {
				$html .= '<ul class="ecpk-pkg-items">';
				foreach ( $package->items as $detail_id => $qty ) {
					$html .= '<li><span class="ecpk-pkg-qty">' . esc_html( (int) $qty . '×' ) . '</span> ' . esc_html( isset( $titles[ $detail_id ] ) ? $titles[ $detail_id ] : '#' . (int) $detail_id ) . '</li>';
				}
				$html .= '</ul>';
			}
			if ( '' !== (string) $package->tracking_number ) {
				$url   = wp_easycart_shipments::tracking_url( $package );
				$label = trim( $package->carrier . ( '' !== (string) $package->service ? ' ' . $package->service : '' ) );
				$html .= '<div class="ecpk-pkg-track">' . ( '' !== $label ? esc_html( $label ) . ' · ' : '' );
				$html .= '' !== $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $package->tracking_number ) . '</a>' : '<span>' . esc_html( $package->tracking_number ) . '</span>';
				if ( '' !== (string) $package->tracking_detail ) {
					$html .= ' <span class="ecpk-muted">— ' . esc_html( $package->tracking_detail ) . '</span>';
				}
				$html .= '</div>';
			}
			$html .= '</div>';

			$actions = array();
			if ( '' !== $partner && class_exists( 'wp_easycart_fulfillment' ) ) {
				$partner_url = wp_easycart_fulfillment::order_url( $partner, (int) $order->order_id, (string) $package->provider_ref );
				if ( '' !== $partner_url ) {
					$actions['partner'] = array(
						/* translators: %s: fulfillment partner, e.g. Printful. */
						'label'  => sprintf( __( 'View in %s', 'wp-easycart' ), wp_easycart_fulfillment::label( $partner ) ),
						'url'    => $partner_url,
						'target' => '_blank',
					);
				}
			}
			if ( empty( $package->is_return ) ) {
				/* 6.0.2: the partner records its own tracking. The store adds it by hand only when the partner is no longer
				   connected ( the package would otherwise wait forever ). */
				if ( 'packed' === $package->status && ( '' === $partner || ! wp_easycart_fulfillment::is_active( $partner ) ) ) {
					$actions['track'] = array(
						'label' => __( 'Add tracking', 'wp-easycart' ),
						// A planned package has no id yet: a partner's is named p:<slug>, so ajax_order_track() saves the plan and
						// picks that partner's package rather than the store's first one.
						'attrs' => array( 'data-ecpk-track' => ( (int) $package->shipment_id <= 0 && '' !== $partner ) ? 'p:' . $partner : (int) $package->shipment_id ),
					);
					if ( '' === $partner ) {
						/* 6.0.2 bug round 6: the store's own package opens the order screen's Fulfill window on its row ( by id, or by
						   its place in the plan while the packages are not saved ), drawn again first when the packages just changed. */
						$actions['track']['attrs']['data-ecpk-store'] = 1;
						$actions['track']['attrs']['data-ecpk-index'] = max( 0, (int) $number - 1 );
					}
				} elseif ( in_array( $package->status, array( 'label', 'shipped', 'exception' ), true ) && (int) $package->shipment_id > 0 ) {
					$actions['delivered'] = array(
						'label' => __( 'Mark delivered', 'wp-easycart' ),
						'attrs' => array(
							'data-ecpk-delivered' => (int) $package->shipment_id,
							'data-busy-text'      => __( 'Saving…', 'wp-easycart' ),
						),
					);
				}
			}
			/**
			 * Buttons on a package row ( a label extension adds Buy label, Reprint, Void, Return label ).
			 *
			 * @since 6.0.2
			 * @param array  $actions key => array( label, onclick | url | attrs, primary ( bool ), target ).
			 * @param object $package Package ( shipment_id 0 while the order's packages are not saved yet ).
			 * @param object $order   Order row.
			 */
			$actions = apply_filters( 'wp_easycart_order_package_actions', $actions, $package, $order );
			if ( is_array( $actions ) && $actions ) {
				$html .= '<div class="ecpk-pkg-actions">';
				foreach ( $actions as $action ) {
					if ( empty( $action['label'] ) ) {
						continue;
					}
					$class = 'ecv2-btn ecv2-btn-sm' . ( ! empty( $action['primary'] ) ? ' ecv2-btn-primary' : '' );
					$attrs = '';
					foreach ( isset( $action['attrs'] ) ? (array) $action['attrs'] : array() as $name => $value ) {
						$attrs .= ' ' . esc_attr( sanitize_key( $name ) ) . '="' . esc_attr( (string) $value ) . '"';
					}
					if ( ! empty( $action['url'] ) ) {
						$html .= '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $action['url'] ) . '"' . ( ! empty( $action['target'] ) ? ' target="' . esc_attr( $action['target'] ) . '" rel="noopener noreferrer"' : '' ) . $attrs . '>' . esc_html( $action['label'] ) . '</a>';
					} else {
						$html .= '<button type="button" class="' . esc_attr( $class ) . '"' . ( ! empty( $action['onclick'] ) ? ' onclick="' . esc_attr( $action['onclick'] ) . '"' : '' ) . $attrs . '>' . esc_html( $action['label'] ) . '</button>';
					}
				}
				$html .= '</div>';
			}
			$html .= '</li>';
			return $html;
		}

		/**
		 * The package editor and the Add tracking dialog.
		 *
		 * @param object $order Order row.
		 */
		private static function print_order_modals( $order ) {
			$carriers = array( 'USPS', 'UPS', 'FedEx', 'DHL', 'Canada Post', 'Australia Post', 'Royal Mail' );
			?>
			<div class="ecpk-modal-backdrop" id="ecpk_order_backdrop" hidden></div>
			<?php /* 6.0.2: the package editor. packages-v2.js draws #ecpk_edit_packages from the block's .ecpk-data. */ ?>
			<div class="ecpk-modal ecpk-editor" id="ecpk_edit_modal" role="dialog" aria-modal="true" aria-labelledby="ecpk_edit_title" aria-describedby="ecpk_edit_sub" hidden>
				<div class="ecpk-modal-head">
					<div class="ecpk-modal-titles">
						<h3 id="ecpk_edit_title" tabindex="-1"><?php esc_html_e( 'Edit packages', 'wp-easycart' ); ?></h3>
						<p class="ecpk-modal-sub" id="ecpk_edit_sub"></p>
					</div>
					<span class="ecpk-dirty" id="ecpk_edit_dirty" hidden><?php esc_html_e( 'Unsaved changes', 'wp-easycart' ); ?></span>
					<button type="button" class="ecpk-x" data-ecpk-close aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				</div>
				<div class="ecpk-modal-body ecpk-ed-body" id="ecpk_edit_body">
					<p class="ecpk-error" id="ecpk_edit_error" role="alert" tabindex="-1" hidden></p>
					<div class="ecpk-ed" id="ecpk_edit_packages"></div>
				</div>
				<div class="ecpk-modal-foot ecpk-ed-foot">
					<p class="ecpk-ed-status" id="ecpk_edit_status"></p>
					<span class="screen-reader-text ecpk-sr" id="ecpk_edit_live" role="status" aria-live="polite"></span>
					<button type="button" class="ecv2-btn" data-ecpk-close><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
					<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecpk_edit_save"><?php esc_html_e( 'Save packages', 'wp-easycart' ); ?></button>
				</div>
			</div>
			<div class="ecpk-modal" id="ecpk_track_modal" role="dialog" aria-modal="true" aria-labelledby="ecpk_track_title" hidden>
				<div class="ecpk-modal-head"><h3 id="ecpk_track_title"><?php esc_html_e( 'Add tracking', 'wp-easycart' ); ?></h3><button type="button" class="ecpk-x" data-ecpk-close aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
				<div class="ecpk-modal-body">
					<input type="hidden" id="ecpk_track_shipment" value="0" />
					<label class="ecpk-field"><span><?php esc_html_e( 'Carrier', 'wp-easycart' ); ?></span>
						<input type="text" id="ecpk_track_carrier" class="ecv2-input" list="ecpk_track_carriers" value="<?php echo esc_attr( (string) $order->shipping_carrier ); ?>" />
						<datalist id="ecpk_track_carriers">
							<?php foreach ( $carriers as $carrier ) : ?>
								<option value="<?php echo esc_attr( $carrier ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</label>
					<label class="ecpk-field"><span><?php esc_html_e( 'Tracking number', 'wp-easycart' ); ?></span><input type="text" id="ecpk_track_number" class="ecv2-input" autocomplete="off" /></label>
					<label class="ecpk-check"><input type="checkbox" id="ecpk_track_notify" checked="checked" /> <?php esc_html_e( 'Email the customer the tracking link', 'wp-easycart' ); ?></label>
					<p class="ecpk-error" id="ecpk_track_error" role="alert" hidden></p>
				</div>
				<div class="ecpk-modal-foot">
					<button type="button" class="ecv2-btn" data-ecpk-close><?php esc_html_e( 'Cancel', 'wp-easycart' ); ?></button>
					<button type="button" class="ecv2-btn ecv2-btn-primary" id="ecpk_track_save" data-busy-text="<?php esc_attr_e( 'Saving…', 'wp-easycart' ); ?>"><?php esc_html_e( 'Save tracking', 'wp-easycart' ); ?></button>
				</div>
			</div>
			<?php
		}

		/**
		 * The order an AJAX request names.
		 *
		 * @return object Order row ( ends the request when missing ).
		 */
		private static function request_order() {
			global $wpdb;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- every caller ran ecv2_order_packages_guard() first.
			$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
			$order    = $order_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_order WHERE order_id = %d', $order_id ) ) : null;
			if ( ! $order ) {
				wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-easycart' ) ) );
			}
			return $order;
		}

		/**
		 * The redrawn block.
		 *
		 * @param int    $order_id Order.
		 * @param string $message  Toast.
		 * @param array  $extra    More keys for the answer ( 6.0.2 bug round 14: unpaid ).
		 */
		private static function reply( $order_id, $message, $extra = array() ) {
			global $wpdb;
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_order WHERE order_id = %d', (int) $order_id ) );
			wp_send_json_success(
				array_merge(
					array(
						'html'     => $order ? self::order_block_html( $order ) : '',
						'message'  => $message,
						'tracking' => $order ? (string) $order->tracking_number : '',
						'carrier'  => $order ? (string) $order->shipping_carrier : '',
						'status'   => $order ? (int) $order->orderstatus_id : 0,
					),
					(array) $extra
				)
			);
		}

		/**
		 * AJAX: the block drawn again, after the order's lines changed on the order screen.
		 *
		 * @since 6.0.2
		 */
		public static function ajax_order_refresh() {
			ecv2_order_packages_guard();
			$order = self::request_order();
			self::reply( (int) $order->order_id, '' );
		}

		/**
		 * AJAX: save the package editor.
		 */
		public static function ajax_order_save() {
			ecv2_order_packages_guard();
			$order    = self::request_order();
			$packages = isset( $_POST['packages'] ) ? json_decode( wp_unslash( $_POST['packages'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; save_packages() checks every item against the order and cleans every value.
			/* 6.0.2: packages left empty are removed, and every item has to be in exactly one package. */
			$packages = self::editor_packages( (int) $order->order_id, is_array( $packages ) ? $packages : array() );
			$problem  = self::allocation_error( (int) $order->order_id, $packages );
			if ( is_wp_error( $problem ) ) {
				wp_send_json_error(
					array(
						'message' => $problem->get_error_message(),
						'code'    => $problem->get_error_code(),
					)
				);
			}
			$done = wp_easycart_shipments::save_packages( (int) $order->order_id, $packages );
			if ( is_wp_error( $done ) ) {
				wp_send_json_error( array( 'message' => $done->get_error_message() ) );
			}
			self::reply( (int) $order->order_id, __( 'Packages saved.', 'wp-easycart' ) );
		}

		/**
		 * The editor's packages without the ones left empty: an empty package still waiting for a label is removed
		 * ( save_packages() deletes a waiting package it is not sent ), one with a label is always kept.
		 *
		 * @since 6.0.2
		 * @param int   $order_id Order.
		 * @param array $packages Packages as the editor sends them.
		 * @return array
		 */
		public static function editor_packages( $order_id, $packages ) {
			$status = array();
			foreach ( wp_easycart_shipments::for_order( (int) $order_id, array( 'returns' => false ) ) as $row ) {
				$status[ (int) $row->shipment_id ] = (string) $row->status;
			}
			$out = array();
			foreach ( (array) $packages as $package ) {
				if ( ! is_array( $package ) ) {
					continue;
				}
				$id    = isset( $package['shipment_id'] ) ? (int) $package['shipment_id'] : 0;
				$count = 0;
				foreach ( isset( $package['items'] ) ? (array) $package['items'] : array() as $qty ) {
					$count += max( 0, (int) $qty );
				}
				if ( $count > 0 || ( $id > 0 && isset( $status[ $id ] ) && 'packed' !== $status[ $id ] ) ) {
					$out[] = $package;
				}
			}
			return $out;
		}

		/**
		 * Every shippable unit of the order is in exactly one package: the packages with a label ( as saved ) plus the
		 * packages the editor sent that are still waiting for one.
		 *
		 * @since 6.0.2
		 * @param int   $order_id Order.
		 * @param array $packages Packages as the editor sends them ( shipment_id, items ).
		 * @return true|WP_Error
		 */
		public static function allocation_error( $order_id, $packages ) {
			$existing    = array();
			$locked      = array();
			$partner_ids = array();
			foreach ( wp_easycart_shipments::for_order( (int) $order_id, array( 'returns' => false ) ) as $row ) {
				$existing[ (int) $row->shipment_id ] = (string) $row->status;
				if ( '' !== (string) $row->partner ) {
					$partner_ids[ (int) $row->shipment_id ] = true;
				}
				// 6.0.2: units in a fulfillment partner's package ( waiting or not ) are the partner's: the editor neither shows
				// nor sends them, whether or not the partner is still connected.
				if ( 'packed' !== $row->status || '' !== (string) $row->partner ) {
					foreach ( $row->items as $detail_id => $qty ) {
						$locked[ (int) $detail_id ] = ( isset( $locked[ (int) $detail_id ] ) ? $locked[ (int) $detail_id ] : 0 ) + (int) $qty;
					}
				}
			}
			$sent = array();
			foreach ( (array) $packages as $package ) {
				$package = (array) $package;
				$id      = isset( $package['shipment_id'] ) ? (int) $package['shipment_id'] : 0;
				if ( $id > 0 && isset( $existing[ $id ] ) && 'packed' !== $existing[ $id ] ) {
					continue; // A package with a label keeps what it holds.
				}
				if ( $id > 0 && isset( $partner_ids[ $id ] ) ) {
					continue; // 6.0.2: a partner's package is not the store's to edit ( save_packages() skips it too ).
				}
				foreach ( isset( $package['items'] ) ? (array) $package['items'] : array() as $detail_id => $qty ) {
					$sent[ (int) $detail_id ] = ( isset( $sent[ (int) $detail_id ] ) ? $sent[ (int) $detail_id ] : 0 ) + max( 0, (int) $qty );
				}
			}
			$under = array();
			$over  = array();
			foreach ( wp_easycart_packages::lines( (int) $order_id ) as $line ) {
				/* 6.0.2: a fulfillment partner's lines ship in the partner's own package, never in the store's. */
				if ( empty( $line->is_shippable ) || (int) $line->quantity <= 0 || '' !== wp_easycart_packages::line_partner( $line ) ) {
					continue;
				}
				$id   = (int) $line->orderdetail_id;
				$need = max( 0, (int) $line->quantity - ( isset( $locked[ $id ] ) ? $locked[ $id ] : 0 ) );
				$have = isset( $sent[ $id ] ) ? $sent[ $id ] : 0;
				if ( $have < $need ) {
					$under[] = ( $need - $have ) . ' × ' . $line->title;
				} elseif ( $have > $need ) {
					$over[] = ( $have - $need ) . ' × ' . $line->title;
				}
			}
			if ( $over ) {
				/* translators: %s: items, e.g. "1 × Tee, 2 × Mug". */
				return new WP_Error( 'wp_easycart_packages_over', sprintf( __( 'The packages hold more than the order has: %s. Take the extra out and save again.', 'wp-easycart' ), implode( ', ', $over ) ) );
			}
			if ( $under ) {
				/* translators: %s: items, e.g. "1 × Tee, 2 × Mug". */
				return new WP_Error( 'wp_easycart_packages_unpacked', sprintf( __( 'Every item needs a package. Not packed yet: %s.', 'wp-easycart' ), implode( ', ', $under ) ) );
			}
			return true;
		}

		/**
		 * AJAX: pack the order again from Settings › Boxes ( packages without a label only ).
		 */
		public static function ajax_order_repack() {
			ecv2_order_packages_guard();
			$order    = self::request_order();
			$order_id = (int) $order->order_id;
			$kept     = array();
			foreach ( wp_easycart_shipments::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
				if ( 'packed' !== $row->status ) {
					$kept[] = array( 'shipment_id' => (int) $row->shipment_id );
				}
			}
			/* Units already in a labelled package are not packed again. */
			$labelled = array();
			foreach ( wp_easycart_shipments::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
				if ( 'packed' !== $row->status ) {
					foreach ( $row->items as $detail_id => $qty ) {
						$labelled[ $detail_id ] = ( isset( $labelled[ $detail_id ] ) ? $labelled[ $detail_id ] : 0 ) + $qty;
					}
				}
			}
			$lines = array();
			foreach ( wp_easycart_packages::lines( $order_id ) as $line ) {
				/* 6.0.2: a fulfillment partner's lines stay in the partner's package ( save_packages() leaves it alone ). */
				if ( '' !== wp_easycart_packages::line_partner( $line ) ) {
					continue;
				}
				if ( isset( $labelled[ (int) $line->orderdetail_id ] ) ) {
					$line->quantity = max( 0, (int) $line->quantity - $labelled[ (int) $line->orderdetail_id ] );
				}
				$lines[] = $line;
			}
			$fresh = array();
			foreach ( wp_easycart_packages::pack( $lines ) as $package ) {
				$fresh[] = array_merge( $package, array( 'shipment_id' => 0 ) );
			}
			wp_easycart_shipments::save_packages( $order_id, array_merge( $kept, $fresh ) );
			self::reply( $order_id, __( 'Packed again from your boxes.', 'wp-easycart' ) );
		}

		/**
		 * AJAX: tracking number for a package bought elsewhere.
		 */
		public static function ajax_order_track() {
			ecv2_order_packages_guard();
			$order    = self::request_order();
			$order_id = (int) $order->order_id;
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ecv2_order_packages_guard() above.
			$target   = isset( $_POST['shipment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['shipment_id'] ) ) : '';
			$shipment = absint( $target );
			$owner    = ( 0 === strpos( $target, 'p:' ) ) ? sanitize_key( substr( $target, 2 ) ) : ''; /* a partner's planned package */
			$tracking = isset( $_POST['tracking_number'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_number'] ) ) : '';
			$carrier  = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
			$notify   = ! empty( $_POST['notify'] );
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			if ( '' === trim( $tracking ) ) {
				wp_send_json_error( array( 'message' => __( 'Enter the tracking number.', 'wp-easycart' ) ) );
			}
			if ( ! $shipment ) {
				/* The order's packages were not saved yet: save the plan so the number lands on the first package
				   ( 6.0.2: the store's first one, or the partner's package the button was on ). */
				foreach ( wp_easycart_shipments::build( $order_id ) as $saved ) {
					if ( 'packed' === $saved->status && $owner === (string) $saved->partner ) {
						$shipment = (int) $saved->shipment_id;
						break;
					}
				}
			}
			$args = array(
				'shipment_id'     => $shipment,
				'carrier'         => $carrier,
				'tracking_number' => $tracking,
				'notify'          => $notify,
			);
			/* 6.0.2: tracking typed for a partner's package ( only offered once the partner is disconnected ) is recorded as the
			   partner's, so it lands on that package. */
			$target = $shipment ? wp_easycart_shipments::get( $shipment ) : null;
			if ( $target && (int) $target->order_id === $order_id && '' !== (string) $target->partner ) {
				$args['provider'] = (string) $target->partner;
			}
			$done = wp_easycart_order_add_shipment( $order_id, $args );
			if ( is_wp_error( $done ) ) {
				wp_send_json_error( array( 'message' => $done->get_error_message() ) );
			}
			self::reply( $order_id, $notify ? __( 'Tracking saved and emailed to the customer.', 'wp-easycart' ) : __( 'Tracking saved.', 'wp-easycart' ) );
		}

		/**
		 * The packages the Create shipping label window asks tracking numbers for: the saved packages, or the plan the
		 * box settings suggest, in order. A package that already has a label or a tracking number is done.
		 *
		 * @since 6.0.2
		 * @param object $order ec_order row.
		 * @return array Rows: index, shipment_id ( 0 while planned ), number, meta, items, status, tracking, carrier, done.
		 */
		public static function label_rows( $order ) {
			if ( ! class_exists( 'wp_easycart_shipments' ) || ! wp_easycart_shipments::ready() ) {
				return array();
			}
			$order_id = (int) $order->order_id;
			$titles   = array();
			foreach ( wp_easycart_packages::lines( $order_id ) as $line ) {
				$titles[ (int) $line->orderdetail_id ] = (string) $line->title;
			}
			$units = self::units();
			$rows  = array();
			foreach ( wp_easycart_shipments::plan( $order_id ) as $i => $package ) {
				/* 6.0.2: a fulfillment partner ships its own package and brings its own tracking ( index stays the plan's position ). */
				if ( ! empty( $package->is_return ) || ! empty( $package->partner ) ) {
					continue;
				}
				$size  = ( $package->length > 0 && $package->width > 0 ) ? self::num( $package->length ) . ' × ' . self::num( $package->width ) . ( $package->height > 0 ? ' × ' . self::num( $package->height ) : '' ) . ' ' . $units['dim'] : '';
				$name  = (string) $package->package_name;
				$meta  = array_filter( array( $name, ( '' !== $size && false === strpos( $name, $size ) ) ? $size : '', $package->weight > 0 ? self::num( $package->weight ) . ' ' . $units['weight'] : '' ) );
				$items = array();
				foreach ( (array) $package->items as $detail_id => $qty ) {
					if ( (int) $qty > 0 ) {
						$items[] = (int) $qty . ' × ' . ( isset( $titles[ (int) $detail_id ] ) ? $titles[ (int) $detail_id ] : '#' . (int) $detail_id );
					}
				}
				$tracking = isset( $package->tracking_number ) ? (string) $package->tracking_number : '';
				$rows[]   = array(
					'index'       => (int) $i,
					'shipment_id' => (int) $package->shipment_id,
					'number'      => count( $rows ) + 1,
					'meta'        => implode( ' · ', $meta ),
					'items'       => implode( ', ', $items ),
					'status'      => (string) $package->status,
					'tracking'    => $tracking,
					'carrier'     => isset( $package->carrier ) ? (string) $package->carrier : '',
					'done'        => ( 'packed' !== (string) $package->status || '' !== trim( $tracking ) ),
				);
			}
			return $rows;
		}

		/**
		 * AJAX: tracking numbers for several packages at once, from the Create shipping label window ( a label made at the
		 * carrier for each box ). A planned order is saved first so each number lands on its own package. Every package is
		 * recorded without an email; then one shipped email goes out for all of them, listing each package. The order is
		 * marked shipped once no package is still waiting ( wp_easycart_shipments::record() ), unless mark_shipped is 0 ( the
		 * Fulfill window's "Mark the order as shipped", on when not posted ).
		 *
		 * @since 6.0.2
		 */
		public static function ajax_order_track_all() {
			ecv2_order_packages_guard();
			$order    = self::request_order();
			$order_id = (int) $order->order_id;
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ecv2_order_packages_guard() above.
			$raw    = isset( $_POST['rows'] ) ? json_decode( wp_unslash( $_POST['rows'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, each value sanitized below.
			$notify = ! empty( $_POST['notify'] );
			$mark   = ! isset( $_POST['mark_shipped'] ) || ! empty( $_POST['mark_shipped'] );
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$rows = array();
			foreach ( is_array( $raw ) ? $raw : array() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$tracking = isset( $row['tracking_number'] ) ? trim( sanitize_text_field( (string) $row['tracking_number'] ) ) : '';
				if ( '' === $tracking ) {
					continue;
				}
				$rows[] = array(
					'index'       => isset( $row['index'] ) ? absint( $row['index'] ) : 0,
					'shipment_id' => isset( $row['shipment_id'] ) ? absint( $row['shipment_id'] ) : 0,
					'carrier'     => isset( $row['carrier'] ) ? sanitize_text_field( (string) $row['carrier'] ) : '',
					'tracking'    => $tracking,
				);
			}
			if ( ! $rows ) {
				wp_send_json_error( array( 'message' => __( 'Enter a tracking number for at least one package.', 'wp-easycart' ) ) );
			}
			$saved = wp_easycart_shipments::for_order( $order_id, array( 'returns' => false ) );
			$built = false;
			if ( ! $saved ) {
				$saved = wp_easycart_shipments::build( $order_id );
				$built = true;
			}
			$saved = array_values( (array) $saved );
			$by_id = array();
			foreach ( $saved as $package ) {
				$by_id[ (int) $package->shipment_id ] = $package;
			}
			/* 6.0.2: the window's rows were printed with the page. Every row must still name a package waiting for its tracking
			   number ( a planned row only when the plan was saved just now, by position ), or nothing is recorded: after Edit
			   packages or Pack again without a reload a number would land on another package, or replace a label's. */
			$targets = array();
			$stale   = false;
			foreach ( $rows as $key => $row ) {
				if ( $row['shipment_id'] ) {
					$package = isset( $by_id[ $row['shipment_id'] ] ) ? $by_id[ $row['shipment_id'] ] : null;
				} else {
					$package = ( $built && isset( $saved[ $row['index'] ] ) ) ? $saved[ $row['index'] ] : null;
				}
				if ( ! $package || 'packed' !== (string) $package->status || '' !== trim( (string) $package->tracking_number ) || isset( $targets[ (int) $package->shipment_id ] ) ) {
					$stale = true;
					break;
				}
				$targets[ (int) $package->shipment_id ] = $key;
			}
			if ( $stale ) {
				/* 6.0.2 bug round 6: code stale: the order screen draws the rows again and asks for the numbers once more. */
				wp_send_json_error(
					array(
						'message' => __( 'The packages changed since this page was loaded. Reload the order and try again.', 'wp-easycart' ),
						'code'    => 'stale',
					)
				);
			}
			$items  = array();
			$first  = null;
			$count  = 0;
			$errors = array();
			foreach ( $targets as $shipment => $key ) {
				$row = $rows[ $key ];
				if ( ! empty( $by_id[ $shipment ]->partner ) ) {
					continue; // 6.0.2: never the store's tracking number on a fulfillment partner's package.
				}
				$done = wp_easycart_order_add_shipment(
					$order_id,
					array(
						'shipment_id'     => $shipment,
						'carrier'         => $row['carrier'],
						'tracking_number' => $row['tracking'],
						'notify'          => false,
						'mark_shipped'    => $mark,
					)
				);
				if ( is_wp_error( $done ) ) {
					$errors[] = $done->get_error_message();
					continue;
				}
				++$count;
				if ( null === $first ) {
					$first = $row;
				}
				foreach ( isset( $by_id[ $shipment ] ) ? array_keys( (array) $by_id[ $shipment ]->items ) : array() as $detail_id ) {
					$items[ (int) $detail_id ] = true;
				}
			}
			if ( ! $count ) {
				wp_send_json_error( array( 'message' => $errors ? $errors[0] : __( 'The tracking numbers could not be saved. Reload the order and try again.', 'wp-easycart' ) ) );
			}
			if ( $notify ) {
				wp_easycart_shipments::send_shipped_email( $order_id, $first['tracking'], $first['carrier'], $items ? array( 'items' => array_keys( $items ) ) : array() );
			}
			/* 6.0.2: the store's own packages still waiting ( a fulfillment partner ships its package and brings its own tracking ). */
			$waiting  = wp_easycart_shipments::waiting_packages( $order_id, '' );
			$partners = array();
			foreach ( wp_easycart_shipments::for_order( $order_id, array( 'returns' => false ) ) as $package ) {
				if ( wp_easycart_shipments::partner_package_open( $package ) && class_exists( 'wp_easycart_fulfillment' ) ) {
					$partners[ (string) $package->partner ] = wp_easycart_fulfillment::label( (string) $package->partner );
				}
			}
			/* translators: %d: number of packages. */
			$message = sprintf( _n( 'Tracking saved for %d package.', 'Tracking saved for %d packages.', $count, 'wp-easycart' ), $count );
			if ( $notify ) {
				$message .= ' ' . __( 'The customer was emailed once, with every package listed.', 'wp-easycart' );
			}
			if ( $waiting > 0 && $mark ) {
				/* translators: %d: number of packages. */
				$message .= ' ' . sprintf( _n( '%d package still needs a tracking number; the order is marked shipped once it has one.', '%d packages still need a tracking number; the order is marked shipped once they have one.', $waiting, 'wp-easycart' ), $waiting );
			} elseif ( $waiting > 0 ) {
				/* translators: %d: number of packages. */
				$message .= ' ' . sprintf( _n( '%d package still needs a tracking number.', '%d packages still need a tracking number.', $waiting, 'wp-easycart' ), $waiting );
			}
			if ( $partners && $mark ) {
				/* translators: %s: fulfillment partners, e.g. Printful. */
				$message .= ' ' . sprintf( __( '%s still has items to ship; the order is marked shipped once they ship.', 'wp-easycart' ), implode( ', ', $partners ) );
			}
			/* 6.0.2 bug round 14: tracking on an order not paid yet ( a manual payment ) leaves it unpaid and not shipped: say so
			   ( wp_easycart_admin_order_screen::ship() answers the same ). */
			global $wpdb;
			$now    = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.orderstatus_id, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			$unpaid = $now && empty( $now->is_approved ) && ! in_array( (int) $now->orderstatus_id, array( 16, 19 ), true );
			if ( $unpaid ) {
				$message .= ' ' . ( $mark ? __( 'The order is not paid yet, so it was not marked shipped. Mark it paid to finish it.', 'wp-easycart' ) : __( 'The order is not paid yet. Mark it paid to finish it.', 'wp-easycart' ) );
			}
			if ( $errors ) {
				$message .= ' ' . implode( ' ', array_unique( $errors ) );
			}
			self::reply( $order_id, $message, array( 'unpaid' => $unpaid ) );
		}

		/**
		 * AJAX: a package arrived.
		 */
		public static function ajax_order_delivered() {
			ecv2_order_packages_guard();
			$order = self::request_order();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_order_packages_guard() above.
			$shipment = isset( $_POST['shipment_id'] ) ? absint( wp_unslash( $_POST['shipment_id'] ) ) : 0;
			$row      = wp_easycart_shipments::get( $shipment );
			if ( ! $row || (int) $row->order_id !== (int) $order->order_id ) {
				wp_send_json_error( array( 'message' => __( 'Package not found.', 'wp-easycart' ) ) );
			}
			wp_easycart_shipments::track( $shipment, 'delivered', __( 'Marked delivered by the store', 'wp-easycart' ) );
			self::reply( (int) $order->order_id, __( 'Package marked delivered.', 'wp-easycart' ) );
		}

		/**
		 * A size or weight for the screen.
		 *
		 * @param float $value Number.
		 * @return string
		 */
		private static function num( $value ) {
			return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
		}
	}

	wp_easycart_admin_packages::init();

endif;
