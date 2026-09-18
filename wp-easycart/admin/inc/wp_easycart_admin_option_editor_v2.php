<?php
/**
 * WP EasyCart Admin — Option Set editor ( V2 ).
 *
 * Replaces the three legacy pages ( set details → item list → item details )
 * with one screen: details, a choices table editor with live preview, type-aware
 * rules, products using the set, and a safe-delete danger zone.
 *
 * Save is a single AJAX request ( ecv2_option_save ) carrying the set fields and
 * the full choice list; the server diffs against the DB, so removed choices can
 * be reported ( and their stock rows handled ) before anything is written.
 *
 * AJAX: ecv2_option_save, ecv2_option_choice_impact, ecv2_option_usage,
 *       ecv2_option_assign_search, ecv2_option_assign.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_option_editor_v2' ) ) :

	class wp_easycart_admin_option_editor_v2 {

		const NONCE = 'wp-easycart-osv2';

		public $option;
		public $items = array();
		public $meta = array();
		public $usage = array();
		public $usage_total = 0;
		public $stock_rows = 0;
		public $is_pro = false;
		public $locked = false;
		public $docs_link = '';

		public static function is_pro() {
			return ( '' === apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ) );
		}

		public static function basic_types() {
			return array( 'basic-combo', 'basic-swatch' );
		}

		/**
		 * Variation sets ( Dropdown / Swatch ) are free. Every other type is a modifier ( advanced option
		 * set ), which is a PRO feature: without a licensed PRO a modifier set is shown read-only and can't
		 * be saved, duplicated or assigned to products. Unknown types count as modifiers.
		 *
		 * @since 6.0.0
		 */
		public static function is_modifier_type( $type ) {
			return ! in_array( (string) $type, self::basic_types(), true );
		}

		/** @since 6.0.0 */
		public static function is_locked_type( $type ) {
			return self::is_modifier_type( $type ) && ! self::is_pro();
		}

		/** @since 6.0.0 */
		public static function is_locked_option( $option_id ) {
			if ( self::is_pro() ) {
				return false;
			}
			global $wpdb;
			$type = $wpdb->get_var( $wpdb->prepare( 'SELECT option_type FROM ec_option WHERE option_id = %d', (int) $option_id ) );
			return null !== $type && self::is_modifier_type( $type );
		}

		/**
		 * Merchant-facing reason a modifier set is locked ( no PRO, PRO inactive, license lapsed … ).
		 *
		 * @since 6.0.0
		 */
		public static function lock_message() {
			$label = __( 'Editing modifier option sets and assigning them to products', 'wp-easycart' );
			if ( class_exists( 'wp_easycart_admin_pro_gate' ) ) {
				$gate = wp_easycart_admin_pro_gate::evaluate( array( 'enabled' => self::is_pro() ) );
				$msg  = wp_easycart_admin_pro_gate::message( $gate, $label );
				if ( '' !== $msg ) {
					return $msg;
				}
			}
			return wp_easycart_admin_edition::requires_text( $label, 'pro' );
		}

		public function __construct() {
			$this->is_pro = self::is_pro();
			$this->docs_link = wp_easycart_admin()->helpsystem->print_docs_url( 'products', 'option-sets', 'option-sets' );
		}

		/* ------------------------------------------------------------------ */
		/* Load                                                                */
		/* ------------------------------------------------------------------ */

		public function load( $option_id ) {
			global $wpdb;
			$this->option = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_option WHERE option_id = %d', $option_id ) );
			if ( ! $this->option ) {
				return false;
			}
			$this->locked = ! $this->is_pro && self::is_modifier_type( $this->option->option_type );
			$this->meta = maybe_unserialize( $this->option->option_meta );
			if ( ! is_array( $this->meta ) ) { $this->meta = array(); }
			$this->items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_order ASC, optionitem_id ASC', $option_id ) );
			$this->load_usage( $option_id );
			return true;
		}

		private function load_usage( $option_id ) {
			global $wpdb;
			$this->usage_total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_product WHERE option_id_1 = %d OR option_id_2 = %d OR option_id_3 = %d OR option_id_4 = %d OR option_id_5 = %d', $option_id, $option_id, $option_id, $option_id, $option_id ) )
				+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_option_to_product WHERE option_id = %d', $option_id ) );
			$this->usage = self::usage_rows( $option_id, 8 );
			$ids = array_map( 'intval', wp_list_pluck( $this->items, 'optionitem_id' ) );
			$in  = $ids ? implode( ',', $ids ) : '0';
			$this->stock_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_optionitemquantity WHERE optionitem_id_1 IN ($in) OR optionitem_id_2 IN ($in) OR optionitem_id_3 IN ($in) OR optionitem_id_4 IN ($in) OR optionitem_id_5 IN ($in)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of intval-cast ids built above.
		}

		public static function usage_rows( $option_id, $limit = 8, $offset = 0 ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- wp_easycart_admin_catalog_v2_thumb_select() returns a static column list; all values go through prepare().
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT p.product_id, p.title, p.activate_in_store, p.option_id_1, p.option_id_2, p.option_id_3, p.option_id_4, p.option_id_5,
				 ' . wp_easycart_admin_catalog_v2_thumb_select( 'p' ) . ',
				 ( SELECT COUNT(*) FROM ec_optionitemquantity q WHERE q.product_id = p.product_id ) AS variant_rows,
				 ( SELECT COUNT(*) FROM ec_optionitemquantity q2 WHERE q2.product_id = p.product_id AND q2.quantity <= 0 ) AS variant_oos,
				 CASE WHEN p.option_id_1 = %d OR p.option_id_2 = %d OR p.option_id_3 = %d OR p.option_id_4 = %d OR p.option_id_5 = %d THEN 0 ELSE 1 END AS advanced
				 FROM ec_product p
				 WHERE p.option_id_1 = %d OR p.option_id_2 = %d OR p.option_id_3 = %d OR p.option_id_4 = %d OR p.option_id_5 = %d
				    OR EXISTS ( SELECT 1 FROM ec_option_to_product otp WHERE otp.product_id = p.product_id AND otp.option_id = %d )
				 ORDER BY p.title ASC LIMIT %d OFFSET %d',
				$option_id, $option_id, $option_id, $option_id, $option_id,
				$option_id, $option_id, $option_id, $option_id, $option_id,
				$option_id, $limit, $offset
			) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
			$out = array();
			foreach ( $rows as $r ) {
				$slot = 0; $others = array();
				for ( $n = 1; $n <= 5; $n++ ) {
					$v = (int) $r->{ 'option_id_' . $n };
					if ( $v === (int) $option_id ) { $slot = $n; } else if ( $v ) { $others[] = $v; }
				}
				$other_names = $others ? $wpdb->get_col( 'SELECT option_name FROM ec_option WHERE option_id IN ( ' . implode( ',', $others ) . ' )' ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $others only holds (int)-cast option ids from the loop above.
				$out[] = array(
					'id'           => (int) $r->product_id,
					'title'        => wp_unslash( $r->title ),
					'image'        => wp_easycart_admin_catalog_v2_product_thumb( $r ),
					'active'       => (bool) $r->activate_in_store,
					'slot'         => $slot,
					'advanced'     => (bool) $r->advanced,
					'other_sets'   => $other_names,
					'variant_rows' => (int) $r->variant_rows,
					'variant_oos'  => (int) $r->variant_oos,
					'edit_url'     => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' . (int) $r->product_id ),
				);
			}
			return $out;
		}

		/* ------------------------------------------------------------------ */
		/* Normalised choice model                                             */
		/* ------------------------------------------------------------------ */

		/** DB row -> editor model ( derives a single price/weight "type" from the five legacy columns ). */
		public static function item_to_model( $it ) {
			$price_type = 'add'; $price = (float) $it->optionitem_price;
			if ( (float) $it->optionitem_price_override != -1 ) { $price_type = 'override'; $price = (float) $it->optionitem_price_override; }
			else if ( (float) $it->optionitem_price_multiplier != 0 ) { $price_type = 'multiply'; $price = (float) $it->optionitem_price_multiplier; }
			else if ( (float) $it->optionitem_price_onetime != 0 ) { $price_type = 'onetime'; $price = (float) $it->optionitem_price_onetime; }
			else if ( (float) $it->optionitem_price_per_character != 0 ) { $price_type = 'per_char'; $price = (float) $it->optionitem_price_per_character; }

			$weight_type = 'add'; $weight = (float) $it->optionitem_weight;
			if ( (float) $it->optionitem_weight_override != -1 ) { $weight_type = 'override'; $weight = (float) $it->optionitem_weight_override; }
			else if ( (float) $it->optionitem_weight_multiplier != 0 ) { $weight_type = 'multiply'; $weight = (float) $it->optionitem_weight_multiplier; }
			else if ( (float) $it->optionitem_weight_onetime != 0 ) { $weight_type = 'onetime'; $weight = (float) $it->optionitem_weight_onetime; }

			$icon = trim( (string) $it->optionitem_icon );
			$is_color = '' !== $icon && ( class_exists( 'ec_optionitem' ) ? (bool) ec_optionitem::swatch_colors( $icon ) : (bool) self::swatch_colors_fallback( $icon ) );
			if ( '' !== $icon && ! $is_color && 0 !== strpos( $icon, 'http' ) ) {
				/* legacy image swatches are stored as a file name under wp-easycart-data/products/swatches */
				$icon = plugins_url( '/wp-easycart-data/products/swatches/' . ltrim( $icon, '/' ), EC_PLUGIN_DATA_DIRECTORY );
			}
			return array(
				'id'                 => (int) $it->optionitem_id,
				'name'               => wp_unslash( (string) $it->optionitem_name ),
				'sku'                => (string) $it->optionitem_model_number,
				'price_type'         => $price_type,
				'price'              => $price,
				'weight_type'        => $weight_type,
				'weight'             => $weight,
				'icon'               => $icon,
				'initially_selected' => (bool) $it->optionitem_initially_selected,
				'initial_value'      => (string) $it->optionitem_initial_value,
				'custom_label_on'    => (bool) $it->optionitem_enable_custom_price_label,
				'custom_label'       => wp_unslash( (string) $it->optionitem_custom_price_label ),
				'allow_download'     => (bool) $it->optionitem_allow_download,
				'disallow_shipping'  => (bool) $it->optionitem_disallow_shipping,
				'download_override'  => self::download_field_to_text( $it->optionitem_download_override_file, 'override' ),
				'download_addition'  => self::download_field_to_text( $it->optionitem_download_addition_file, 'additional' ),
				'order'              => (int) $it->optionitem_order,
			);
		}

		/** Editor model -> DB columns ( resets every adjustment column, then sets the chosen one ). */
		/**
		 * Swatch icon: a color ( "#rrggbb" or "#rrggbb,#rrggbb" ) is free for every store and every
		 * swatch type. Image swatches are PRO; free stores keep any image that is already stored
		 * ( never wiped on save ) but can't set a new one.
		 */
		/**
		 * The legacy editor stores these two columns as JSON objects
		 * ( {"is_override_file":1,"is_override_amazon":0,"override_amazon_key":"","override_file_name":"x.zip"} ).
		 * The V2 editor shows one text field: a file name, a URL, or "s3:key" for an Amazon object.
		 */
		public static function download_field_to_text( $raw, $kind ) {
			$raw = trim( (string) $raw );
			if ( '' === $raw || '{}' === $raw || '{' === $raw ) { return ''; }
			$o = json_decode( $raw, true );
			if ( ! is_array( $o ) ) { return $raw; } /* plain file name from an older version */
			$on = ! empty( $o[ 'is_' . $kind . '_file' ] );
			if ( ! $on ) { return ''; }
			if ( ! empty( $o[ 'is_' . $kind . '_amazon' ] ) ) { return 's3:' . (string) ( isset( $o[ $kind . '_amazon_key' ] ) ? $o[ $kind . '_amazon_key' ] : '' ); }
			return (string) ( isset( $o[ $kind . '_file_name' ] ) ? $o[ $kind . '_file_name' ] : '' );
		}
		public static function download_text_to_field( $text, $kind ) {
			$text = trim( (string) $text );
			$s3 = 0 === stripos( $text, 's3:' );
			return wp_json_encode( array(
				'is_' . $kind . '_file'   => '' !== $text ? 1 : 0,
				'is_' . $kind . '_amazon' => $s3 ? 1 : 0,
				$kind . '_amazon_key'     => $s3 ? trim( substr( $text, 3 ) ) : '',
				$kind . '_file_name'      => $s3 ? '' : $text,
			) );
		}

		public static function sanitize_icon( $icon, $is_pro, $optionitem_id = 0 ) {
			$icon = trim( (string) $icon );
			if ( '' === $icon ) { return ''; }
			$colors = class_exists( 'ec_optionitem' ) ? ec_optionitem::swatch_colors( $icon ) : self::swatch_colors_fallback( $icon );
			if ( $colors ) { return implode( ',', $colors ); }
			if ( $is_pro ) { return esc_url_raw( $icon ); }
			if ( $optionitem_id ) {
				global $wpdb;
				$existing = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT optionitem_icon FROM ec_optionitem WHERE optionitem_id = %d', $optionitem_id ) );
				if ( '' !== $existing && $existing === $icon ) { return $existing; }
			}
			return '';
		}

		/** Same rules as ec_optionitem::swatch_colors(), for admin contexts where core isn't loaded. */
		public static function swatch_colors_fallback( $icon ) {
			$icon = trim( (string) $icon );
			if ( '' === $icon || '#' !== $icon[0] ) { return false; }
			$parts = array_map( 'trim', explode( ',', $icon ) );
			if ( count( $parts ) > 2 ) { return false; }
			$out = array();
			foreach ( $parts as $c ) {
				if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $c ) ) { return false; }
				if ( 4 === strlen( $c ) ) { $c = '#' . $c[1] . $c[1] . $c[2] . $c[2] . $c[3] . $c[3]; }
				$out[] = strtolower( $c );
			}
			return $out;
		}

		public static function model_to_columns( $m, $option_id, $order, $is_pro, $is_basic ) {
			$price  = wp_easycart_admin_verification()->filter_float( isset( $m['price'] ) ? $m['price'] : 0 );
			$weight = wp_easycart_admin_verification()->filter_float( isset( $m['weight'] ) ? $m['weight'] : 0 );
			$pt = ( $is_pro && ! $is_basic && isset( $m['price_type'] ) ) ? sanitize_key( $m['price_type'] ) : 'add';
			$wt = ( $is_pro && ! $is_basic && isset( $m['weight_type'] ) ) ? sanitize_key( $m['weight_type'] ) : 'add';
			$cols = array(
				'option_id'                            => (int) $option_id,
				'optionitem_name'                      => sanitize_text_field( isset( $m['name'] ) ? $m['name'] : '' ),
				'optionitem_model_number'              => preg_replace( '/[^A-Za-z0-9\-_]/', '', isset( $m['sku'] ) ? $m['sku'] : '' ),
				'optionitem_price'                     => 'add' === $pt ? $price : 0,
				'optionitem_price_onetime'             => 'onetime' === $pt ? $price : 0,
				'optionitem_price_override'            => 'override' === $pt ? $price : -1,
				'optionitem_price_multiplier'          => 'multiply' === $pt ? $price : 0,
				'optionitem_price_per_character'       => 'per_char' === $pt ? $price : 0,
				'optionitem_weight'                    => 'add' === $wt ? $weight : 0,
				'optionitem_weight_onetime'            => 'onetime' === $wt ? $weight : 0,
				'optionitem_weight_override'           => 'override' === $wt ? $weight : -1,
				'optionitem_weight_multiplier'         => 'multiply' === $wt ? $weight : 0,
				'optionitem_order'                     => (int) $order,
				'optionitem_icon'                      => self::sanitize_icon( isset( $m['icon'] ) ? $m['icon'] : '', $is_pro, isset( $m['id'] ) ? (int) $m['id'] : 0 ),
				'optionitem_initially_selected'        => ! empty( $m['initially_selected'] ) ? 1 : 0,
				'optionitem_initial_value'             => sanitize_text_field( isset( $m['initial_value'] ) ? $m['initial_value'] : '' ),
				'optionitem_enable_custom_price_label' => ( $is_pro && ! empty( $m['custom_label_on'] ) ) ? 1 : 0,
				'optionitem_custom_price_label'        => $is_pro ? sanitize_text_field( isset( $m['custom_label'] ) ? $m['custom_label'] : '' ) : '',
				'optionitem_allow_download'            => isset( $m['allow_download'] ) ? ( ! empty( $m['allow_download'] ) ? 1 : 0 ) : 1,
				'optionitem_disallow_shipping'         => ! empty( $m['disallow_shipping'] ) ? 1 : 0,
				'optionitem_download_override_file'    => self::download_text_to_field( $is_pro ? sanitize_text_field( isset( $m['download_override'] ) ? $m['download_override'] : '' ) : '', 'override' ),
				'optionitem_download_addition_file'    => self::download_text_to_field( $is_pro ? sanitize_text_field( isset( $m['download_addition'] ) ? $m['download_addition'] : '' ) : '', 'additional' ),
			);
			return $cols;
		}

		/* ------------------------------------------------------------------ */
		/* Health                                                              */
		/* ------------------------------------------------------------------ */

		public function health() {
			$meta = wp_easycart_admin_option_table::type_meta( $this->option->option_type );
			$out = array();
			$out[] = array( 'ok' => '' !== trim( (string) $this->option->option_label ), 'label' => __( 'Shopper label set', 'wp-easycart' ) );
			if ( $meta['list'] ) {
				$out[] = array( 'ok' => count( $this->items ) >= 2, 'label' => count( $this->items ) >= 2 ? __( 'Has at least two choices', 'wp-easycart' ) : __( 'Fewer than two choices', 'wp-easycart' ) );
				$has_default = false; $no_sku = 0; $names = array(); $dupes = 0; $no_icon = 0;
				foreach ( $this->items as $it ) {
					if ( $it->optionitem_initially_selected ) { $has_default = true; }
					if ( '' === trim( (string) $it->optionitem_model_number ) ) { $no_sku++; }
					$k = strtolower( trim( (string) $it->optionitem_name ) );
					if ( isset( $names[ $k ] ) ) { $dupes++; } $names[ $k ] = 1;
					if ( '' === trim( (string) $it->optionitem_icon ) ) { $no_icon++; }
				}
				$out[] = array( 'ok' => $has_default, 'label' => $has_default ? __( 'Has a default choice', 'wp-easycart' ) : __( 'No default choice ( first one is used )', 'wp-easycart' ) );
				$out[] = array( 'ok' => 0 === $no_sku, 'label' => $no_sku ? sprintf( _n( '%d choice missing SKU suffix', '%d choices missing SKU suffix', $no_sku, 'wp-easycart' ), $no_sku ) : __( 'Every choice has a SKU suffix', 'wp-easycart' ) );
				$out[] = array( 'ok' => 0 === $dupes, 'label' => $dupes ? __( 'Duplicate choice names', 'wp-easycart' ) : __( 'No duplicate names', 'wp-easycart' ) );
				if ( in_array( $this->option->option_type, array( 'basic-swatch', 'swatch' ), true ) ) {
					$out[] = array( 'ok' => 0 === $no_icon, 'label' => $no_icon ? sprintf( _n( '%d swatch has no color or image', '%d swatches have no color or image', $no_icon, 'wp-easycart' ), $no_icon ) : __( 'All swatches have images', 'wp-easycart' ) );
				}
			}
			$out[] = array( 'ok' => $this->usage_total > 0, 'label' => $this->usage_total ? sprintf( _n( 'Used by %d product', 'Used by %d products', $this->usage_total, 'wp-easycart' ), $this->usage_total ) : __( 'Not assigned to any product yet', 'wp-easycart' ) );
			return $out;
		}

		/* ------------------------------------------------------------------ */
		/* Render                                                              */
		/* ------------------------------------------------------------------ */

		public function output() {
			$option_id = isset( $_GET['option_id'] ) ? (int) $_GET['option_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing; the id is int-cast.
			if ( ! $this->load( $option_id ) ) {
				echo '<div class="ecv2-wrap"><div class="ecv2-empty-state">' . esc_html__( 'That option set no longer exists.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=option' ) ) . '">' . esc_html__( 'Back to option sets', 'wp-easycart' ) . '</a></div></div>';
				return;
			}
			include( EC_PLUGIN_DIRECTORY . '/admin/template/products/options/option-editor-v2.php' );
		}

		public function js_data() {
			$models = array();
			foreach ( $this->items as $it ) { $models[] = self::item_to_model( $it ); }
			return array(
				'option_id'  => (int) $this->option->option_id,
				'type'       => $this->option->option_type,
				'is_pro'     => $this->is_pro,
				'locked'     => $this->locked,
				'choices'    => $models,
				'usage'      => $this->usage_total,
				'stock_rows' => $this->stock_rows,
				'currency'   => html_entity_decode( get_option( 'ec_option_currency' ) ? get_option( 'ec_option_currency' ) : '$' ),
				'decimals'   => (int) get_option( 'ec_option_currency_decimal_places', 2 ),
				'weight_unit'=> get_option( 'ec_option_paypal_weight_unit' ) ? get_option( 'ec_option_paypal_weight_unit' ) : 'lbs',
				'types'      => wp_easycart_admin_option_table::types(),
				'nonce'      => wp_create_nonce( self::NONCE ),
				'delete_nonce' => wp_create_nonce( wp_easycart_admin_safe_delete::NONCE ),
				'list_url'   => admin_url( 'admin.php?page=wp-easycart-products&subpage=option' ),
				/* 6.0.0 file upload types: the store default list shown when no type is ticked. */
				'file_default' => class_exists( 'wp_easycart_customer_uploads' ) ? wp_easycart_customer_uploads::display_extensions() : '',
				'i18n'       => array(
					'saved'         => __( 'Saved', 'wp-easycart' ),
					'saving'        => __( 'Saving…', 'wp-easycart' ),
					'unsaved'       => __( 'Unsaved changes', 'wp-easycart' ),
					'error'         => __( 'Something went wrong. Please try again.', 'wp-easycart' ),
					'name_required' => __( 'Give the option set a name.', 'wp-easycart' ),
					'choice_name'   => __( 'Choice name', 'wp-easycart' ),
					'remove_title'  => __( 'Remove choice?', 'wp-easycart' ),
					/* translators: 1: choice name, 2: stock rows, 3: products */
					'remove_impact' => __( 'Removing “%1$s” deletes %2$d variant stock rows across %3$d products. Continue and save to apply.', 'wp-easycart' ),
					'remove_simple' => __( 'Remove “%s”? It has no stock rows yet.', 'wp-easycart' ),
					'paste_title'   => __( 'Paste choices', 'wp-easycart' ),
					'paste_hint'    => __( 'One per line. Optional: name, price, SKU suffix — e.g. “XL, 2.00, -XL”.', 'wp-easycart' ),
					'undo'          => __( 'Undo', 'wp-easycart' ),
					'leave'         => __( 'You have unsaved changes.', 'wp-easycart' ),
					'pick_photo'    => __( 'Choose a swatch image', 'wp-easycart' ),
					'use_photo'     => __( 'Use this image', 'wp-easycart' ),
					'pro_swatch'    => wp_easycart_admin_edition::requires_text( __( 'Swatch image support', 'wp-easycart' ), 'pro' ),
					'type_warn'     => __( 'Switching to a modifier type removes variant stock tracking on %d products.', 'wp-easycart' ),
					'type_safe'     => __( 'Changing between Dropdown and Swatch is safe for the %d products using this set.', 'wp-easycart' ),
					'type_input'    => __( 'This type has no list of choices; the shopper types or picks a value.', 'wp-easycart' ),
					'min_choices'   => __( 'Add at least one choice, or switch to an input type.', 'wp-easycart' ),
					'assigned'      => __( 'Assigned to %d products.', 'wp-easycart' ),
					'locked'        => self::lock_message(),
					/* translators: %s: list of file types, e.g. JPG, PNG, PDF. */
					'ft_default'    => __( 'Default types: %s', 'wp-easycart' ),
					/* translators: %s: list of file types, e.g. JPG, PNG, PDF. */
					'ft_custom'     => __( 'Shoppers can upload: %s', 'wp-easycart' ),
					/* translators: %s: list of file types, e.g. JPG, PNG, PDF. */
					'ft_accepted'   => __( 'Accepted file types: %s', 'wp-easycart' ),
					'ft_select_all' => __( 'Select all', 'wp-easycart' ),
					'ft_clear_all'  => __( 'Clear', 'wp-easycart' ),
				),
			);
		}
	}

endif;

/* ---------------------------------------------------------------------- */
/* AJAX                                                                    */
/* ---------------------------------------------------------------------- */

function ecv2_osv2_guard() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
	}
	check_ajax_referer( wp_easycart_admin_option_editor_v2::NONCE, 'nonce' );
}

/**
 * Save the whole set. POST: option_id, set ( JSON ), choices ( JSON array ), removed ( JSON array of ids ).
 */
add_action( 'wp_ajax_ecv2_option_save', 'ecv2_option_save' );
function ecv2_option_save() {
	ecv2_osv2_guard();
	global $wpdb;
	$option_id = isset( $_POST['option_id'] ) ? (int) $_POST['option_id'] : 0;
	$existing  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_option WHERE option_id = %d', $option_id ) );
	if ( ! $existing ) { wp_send_json_error( array( 'message' => __( 'Option set not found.', 'wp-easycart' ) ) ); }

	$is_pro  = wp_easycart_admin_option_editor_v2::is_pro();
	/* Modifier ( advanced ) sets are PRO: without it they are read-only, whatever the payload asks for. */
	if ( ! $is_pro && wp_easycart_admin_option_editor_v2::is_modifier_type( $existing->option_type ) ) {
		wp_send_json_error( array( 'message' => wp_easycart_admin_option_editor_v2::lock_message(), 'code' => 'pro_required' ) );
	}
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payloads; every field is sanitized where it is used ( model_to_columns(), the $meta array below ).
	$set     = json_decode( wp_unslash( isset( $_POST['set'] ) ? $_POST['set'] : '{}' ), true );
	$choices = json_decode( wp_unslash( isset( $_POST['choices'] ) ? $_POST['choices'] : '[]' ), true );
	$removed = json_decode( wp_unslash( isset( $_POST['removed'] ) ? $_POST['removed'] : '[]' ), true );
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! is_array( $set ) || ! is_array( $choices ) ) { wp_send_json_error( array( 'message' => __( 'Invalid payload.', 'wp-easycart' ) ) ); }

	$types = wp_easycart_admin_option_table::types();
	$type  = isset( $set['type'] ) ? sanitize_text_field( $set['type'] ) : $existing->option_type;
	if ( 'dimension1' === $type ) { $type = 'dimensions1'; } if ( 'dimension2' === $type ) { $type = 'dimensions2'; }
	if ( ! isset( $types[ $type ] ) ) { wp_send_json_error( array( 'message' => __( 'Unknown option type.', 'wp-easycart' ) ) ); }
	$is_basic = in_array( $type, wp_easycart_admin_option_editor_v2::basic_types(), true );
	if ( ! $is_pro && ! $is_basic && $type !== $existing->option_type ) {
		wp_send_json_error( array( 'message' => wp_easycart_admin_edition::requires_text( __( 'Modifier support', 'wp-easycart' ), 'pro' ) ) );
	}
	$name = isset( $set['name'] ) ? sanitize_text_field( $set['name'] ) : '';
	if ( '' === $name ) { wp_send_json_error( array( 'message' => __( 'A name is required.', 'wp-easycart' ) ) ); }
	$label = isset( $set['label'] ) ? wp_easycart_escape_html( $set['label'] ) : '';
	if ( '' === trim( $label ) ) { $label = $name; }
	$required = ( $is_basic || ! empty( $set['required'] ) ) ? 1 : 0;
	$meta_in = isset( $set['meta'] ) && is_array( $set['meta'] ) ? $set['meta'] : array();
	$meta = array(
		'min'         => isset( $meta_in['min'] ) ? sanitize_text_field( $meta_in['min'] ) : '',
		'max'         => isset( $meta_in['max'] ) ? sanitize_text_field( $meta_in['max'] ) : '',
		'min_length'  => isset( $meta_in['min_length'] ) ? sanitize_text_field( $meta_in['min_length'] ) : '',
		'max_length'  => isset( $meta_in['max_length'] ) ? sanitize_text_field( $meta_in['max_length'] ) : '',
		'step'        => isset( $meta_in['step'] ) ? sanitize_text_field( $meta_in['step'] ) : '',
		'url_var'     => isset( $meta_in['url_var'] ) ? preg_replace( '/[^a-zA-Z0-9\_]+/', '', sanitize_text_field( $meta_in['url_var'] ) ) : '',
		'swatch_size' => isset( $meta_in['swatch_size'] ) ? (int) $meta_in['swatch_size'] : 30,
	);
	/* 6.0.0 input rules ( text / textarea ): text_case, text_allowed, placeholder. Kept for every type so a type switch doesn't lose them. */
	if ( class_exists( 'wp_easycart_text_input_rules' ) ) {
		$meta = array_merge( $meta, wp_easycart_text_input_rules::sanitize_meta( $meta_in ) );
	}
	/* 6.0.0 file upload types: known, never-dangerous type keys only; empty = the store default list. Kept for every type like the rules above. */
	if ( class_exists( 'wp_easycart_customer_uploads' ) ) {
		$meta['file_types'] = wp_easycart_customer_uploads::sanitize_extensions( isset( $meta_in['file_types'] ) ? $meta_in['file_types'] : array() );
	}

	$takes_list = $types[ $type ]['list'];
	$clean = array();
	if ( $takes_list ) {
		foreach ( $choices as $c ) {
			if ( ! is_array( $c ) || '' === trim( isset( $c['name'] ) ? $c['name'] : '' ) ) { continue; }
			$clean[] = $c;
		}
		if ( empty( $clean ) ) { wp_send_json_error( array( 'message' => __( 'Add at least one choice, or switch to an input type.', 'wp-easycart' ) ) ); }
	}

	/* ---- Set row ---- */
	$wpdb->update( 'ec_option', array(
		'option_name'       => $name,
		'option_label'      => $label,
		'option_type'       => $type,
		'option_required'   => $required,
		'option_error_text' => isset( $set['error_text'] ) ? wp_easycart_escape_html( $set['error_text'] ) : '',
		'option_meta'       => maybe_serialize( $meta ),
	), array( 'option_id' => $option_id ) );

	/* ---- Choices ---- */
	$existing_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT optionitem_id FROM ec_optionitem WHERE option_id = %d', $option_id ) ) );
	$kept_ids = array(); $id_map = array(); $stock_deleted = 0;

	if ( $takes_list ) {
		$order = 1; $default_seen = false;
		foreach ( $clean as $c ) {
			if ( ! empty( $c['initially_selected'] ) ) { if ( $default_seen ) { $c['initially_selected'] = 0; } $default_seen = true; }
			$cols = wp_easycart_admin_option_editor_v2::model_to_columns( $c, $option_id, $order++, $is_pro, $is_basic );
			$cid = isset( $c['id'] ) ? (int) $c['id'] : 0;
			if ( $cid && in_array( $cid, $existing_ids, true ) ) {
				$wpdb->update( 'ec_optionitem', $cols, array( 'optionitem_id' => $cid ) );
				$kept_ids[] = $cid;
			} else {
				$wpdb->insert( 'ec_optionitem', $cols );
				$new_id = (int) $wpdb->insert_id;
				$kept_ids[] = $new_id;
				if ( isset( $c['tmp'] ) ) { $id_map[ (string) $c['tmp'] ] = $new_id; }
			}
		}
		/* Anything in the DB that wasn't in the payload is a removal ( explicit list or silent ) */
		$to_delete = array_diff( $existing_ids, $kept_ids );
	} else {
		/* Input types keep one hidden item row carrying default value / price rules, like the legacy code */
		$to_delete = $existing_ids;
		$single = ! empty( $clean ) ? $clean[0] : ( ! empty( $choices ) && is_array( $choices[0] ) ? $choices[0] : array() );
		$single['name'] = isset( $single['name'] ) && '' !== $single['name'] ? $single['name'] : $types[ $type ]['label'];
		$cols = wp_easycart_admin_option_editor_v2::model_to_columns( $single, $option_id, 1, $is_pro, false );
		if ( count( $existing_ids ) ) {
			$wpdb->update( 'ec_optionitem', $cols, array( 'optionitem_id' => $existing_ids[0] ) );
			$kept_ids[] = $existing_ids[0];
			$to_delete = array_slice( $existing_ids, 1 );
		} else {
			$wpdb->insert( 'ec_optionitem', $cols );
			$kept_ids[] = (int) $wpdb->insert_id;
		}
	}
	if ( ! empty( $to_delete ) ) {
		$in = implode( ',', array_map( 'intval', $to_delete ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of intval-cast ids built on the line above.
		$stock_deleted = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_optionitemquantity WHERE optionitem_id_1 IN ($in) OR optionitem_id_2 IN ($in) OR optionitem_id_3 IN ($in) OR optionitem_id_4 IN ($in) OR optionitem_id_5 IN ($in)" );
		$wpdb->query( "DELETE FROM ec_optionitemquantity WHERE optionitem_id_1 IN ($in) OR optionitem_id_2 IN ($in) OR optionitem_id_3 IN ($in) OR optionitem_id_4 IN ($in) OR optionitem_id_5 IN ($in)" );
		$wpdb->query( "DELETE FROM ec_optionitemimage WHERE optionitem_id IN ($in)" );
		$wpdb->query( "DELETE FROM ec_optionitem WHERE optionitem_id IN ($in)" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/* Type family change: variation -> modifier drops stock rows for products using the set */
	$was_basic = in_array( $existing->option_type, wp_easycart_admin_option_editor_v2::basic_types(), true );
	if ( $was_basic && ! $is_basic ) {
		$in = implode( ',', array_map( 'intval', $kept_ids ) );
		if ( '' !== $in ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of intval-cast ids built above.
			$stock_deleted += (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_optionitemquantity WHERE optionitem_id_1 IN ($in) OR optionitem_id_2 IN ($in) OR optionitem_id_3 IN ($in) OR optionitem_id_4 IN ($in) OR optionitem_id_5 IN ($in)" );
			$wpdb->query( "DELETE FROM ec_optionitemquantity WHERE optionitem_id_1 IN ($in) OR optionitem_id_2 IN ($in) OR optionitem_id_3 IN ($in) OR optionitem_id_4 IN ($in) OR optionitem_id_5 IN ($in)" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	do_action( 'wp_easycart_optionset_updated', $option_id );
	wp_cache_delete( 'wpeasycart-settings', 'wpeasycart-settings' );

	$editor = new wp_easycart_admin_option_editor_v2();
	$editor->load( $option_id );
	$models = array();
	foreach ( $editor->items as $it ) { $models[] = wp_easycart_admin_option_editor_v2::item_to_model( $it ); }
	wp_send_json_success( array(
		'choices'       => $models,
		'id_map'        => $id_map,
		'stock_deleted' => $stock_deleted,
		'health'        => $editor->health(),
		'usage'         => $editor->usage_total,
		'stock_rows'    => $editor->stock_rows,
		'message'       => __( 'Saved', 'wp-easycart' ),
	) );
}

/** Impact of removing specific choices ( before save ). POST: ids[] */
add_action( 'wp_ajax_ecv2_option_choice_impact', 'ecv2_option_choice_impact' );
function ecv2_option_choice_impact() {
	ecv2_osv2_guard();
	global $wpdb;
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();
	$ids = array_filter( $ids );
	if ( empty( $ids ) ) { wp_send_json_success( array( 'stock_rows' => 0, 'products' => 0 ) ); }
	$in = implode( ',', $ids );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is an implode of $ids, which is array_map( 'intval' ) + array_filter above.
	wp_send_json_success( array(
		'stock_rows' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_optionitemquantity WHERE optionitem_id_1 IN ($in) OR optionitem_id_2 IN ($in) OR optionitem_id_3 IN ($in) OR optionitem_id_4 IN ($in) OR optionitem_id_5 IN ($in)" ),
		'products'   => (int) $wpdb->get_var( "SELECT COUNT( DISTINCT product_id ) FROM ec_optionitemquantity WHERE optionitem_id_1 IN ($in) OR optionitem_id_2 IN ($in) OR optionitem_id_3 IN ($in) OR optionitem_id_4 IN ($in) OR optionitem_id_5 IN ($in)" ),
		'in_carts'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_tempcart WHERE optionitem_id_1 IN ($in) OR optionitem_id_2 IN ($in) OR optionitem_id_3 IN ($in) OR optionitem_id_4 IN ($in) OR optionitem_id_5 IN ($in)" ),
	) );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/** Paged "used by" list. POST: option_id, offset */
add_action( 'wp_ajax_ecv2_option_usage', 'ecv2_option_usage' );
function ecv2_option_usage() {
	ecv2_osv2_guard();
	$option_id = isset( $_POST['option_id'] ) ? (int) $_POST['option_id'] : 0;
	$offset    = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
	wp_send_json_success( array( 'items' => wp_easycart_admin_option_editor_v2::usage_rows( $option_id, 25, $offset ) ) );
}

/** Products the set could be assigned to, with free-slot info. POST: option_id, q, category_id */
add_action( 'wp_ajax_ecv2_option_assign_search', 'ecv2_option_assign_search' );
function ecv2_option_assign_search() {
	ecv2_osv2_guard();
	global $wpdb;
	$option_id = isset( $_POST['option_id'] ) ? (int) $_POST['option_id'] : 0;
	$q   = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
	$cat = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$type = $wpdb->get_var( $wpdb->prepare( 'SELECT option_type FROM ec_option WHERE option_id = %d', $option_id ) );
	if ( null === $type ) { wp_send_json_error( array( 'message' => __( 'Option set not found.', 'wp-easycart' ) ) ); }
	$modifier = wp_easycart_admin_option_editor_v2::is_modifier_type( $type );
	if ( $modifier && ! wp_easycart_admin_option_editor_v2::is_pro() ) {
		wp_send_json_error( array( 'message' => wp_easycart_admin_option_editor_v2::lock_message(), 'code' => 'pro_required' ) );
	}
	$limit = wp_easycart_admin_option_editor_v2::is_pro() ? 5 : (int) apply_filters( 'wp_easycart_admin_free_option_set_limit', 2 );
	$where = $wpdb->prepare( 'WHERE NOT ( p.option_id_1 = %d OR p.option_id_2 = %d OR p.option_id_3 = %d OR p.option_id_4 = %d OR p.option_id_5 = %d ) AND NOT EXISTS ( SELECT 1 FROM ec_option_to_product otp WHERE otp.product_id = p.product_id AND otp.option_id = %d )', $option_id, $option_id, $option_id, $option_id, $option_id, $option_id );
	if ( '' !== $q ) { $where .= $wpdb->prepare( ' AND ( p.title LIKE %s OR p.model_number LIKE %s )', '%' . $wpdb->esc_like( $q ) . '%', '%' . $wpdb->esc_like( $q ) . '%' ); }
	if ( $cat ) { $where .= $wpdb->prepare( ' AND EXISTS ( SELECT 1 FROM ec_categoryitem ci WHERE ci.product_id = p.product_id AND ci.category_id = %d )', $cat ); }
	$rows = $wpdb->get_results( "SELECT p.product_id, p.title, p.model_number, p.activate_in_store, " . wp_easycart_admin_catalog_v2_thumb_select( "p" ) . ", p.option_id_1, p.option_id_2, p.option_id_3, p.option_id_4, p.option_id_5 FROM ec_product p $where ORDER BY p.title ASC LIMIT 40" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- thumb_select() is a static column list; $where is assembled entirely from $wpdb->prepare() calls above.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_product p $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is assembled entirely from $wpdb->prepare() calls above.
	$out = array();
	foreach ( $rows as $r ) {
		$used = 0; $names = array();
		for ( $n = 1; $n <= $limit; $n++ ) { if ( (int) $r->{ 'option_id_' . $n } ) { $used++; $names[] = (int) $r->{ 'option_id_' . $n }; } }
		$set_names = $names ? $wpdb->get_col( 'SELECT option_name FROM ec_option WHERE option_id IN ( ' . implode( ',', $names ) . ' )' ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $names only holds (int)-cast option ids from the loop above.
		$out[] = array(
			'id' => (int) $r->product_id, 'title' => wp_unslash( $r->title ), 'sku' => $r->model_number,
			'image' => wp_easycart_admin_catalog_v2_product_thumb( $r ),
			'active' => (bool) $r->activate_in_store, 'slots_used' => $used, 'slots' => $limit, 'sets' => $set_names, 'full' => $modifier ? false : $used >= $limit,
		);
	}
	/* Modifiers don't use the variation slots: they are added to the product's modifier list, which has no limit. */
	wp_send_json_success( array( 'items' => $out, 'total' => $total, 'slots' => $limit, 'advanced' => $modifier ) );
}

/**
 * Assign the set to products. Variation sets go in the first free slot ( option_id_1..N ) and get
 * the variant stock rows the product editor expects so stock tracking works immediately. Modifier
 * sets ( PRO ) are appended to the product's modifier list ( ec_option_to_product ).
 * POST: option_id, product_ids[]
 */
add_action( 'wp_ajax_ecv2_option_assign', 'ecv2_option_assign' );
function ecv2_option_assign() {
	ecv2_osv2_guard();
	global $wpdb;
	$option_id = isset( $_POST['option_id'] ) ? (int) $_POST['option_id'] : 0;
	$pids = isset( $_POST['product_ids'] ) && is_array( $_POST['product_ids'] ) ? array_map( 'intval', $_POST['product_ids'] ) : array();
	if ( ! $option_id || empty( $pids ) ) { wp_send_json_error( array( 'message' => __( 'Nothing to assign.', 'wp-easycart' ) ) ); }
	$type = $wpdb->get_var( $wpdb->prepare( 'SELECT option_type FROM ec_option WHERE option_id = %d', $option_id ) );
	if ( null === $type ) { wp_send_json_error( array( 'message' => __( 'Option set not found.', 'wp-easycart' ) ) ); }
	if ( wp_easycart_admin_option_editor_v2::is_modifier_type( $type ) ) {
		if ( ! wp_easycart_admin_option_editor_v2::is_pro() ) {
			wp_send_json_error( array( 'message' => wp_easycart_admin_option_editor_v2::lock_message(), 'code' => 'pro_required' ) );
		}
		$assigned = 0; $skipped = 0;
		foreach ( $pids as $pid ) {
			$p = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, use_advanced_optionset, use_both_option_types FROM ec_product WHERE product_id = %d', $pid ) );
			if ( ! $p ) { continue; }
			if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_option_to_product WHERE product_id = %d AND option_id = %d', $pid, $option_id ) ) ) { $skipped++; continue; }
			/* Same normalisation the product editor applies on open: products still on the one-model flag
			 * switch to "both", so the new modifier shows alongside any variation sets. */
			if ( ! (int) $p->use_both_option_types ) {
				if ( (int) $p->use_advanced_optionset ) {
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET option_id_1 = 0, option_id_2 = 0, option_id_3 = 0, option_id_4 = 0, option_id_5 = 0, use_both_option_types = 1 WHERE product_id = %d', $pid ) );
				} else {
					$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_option_to_product WHERE product_id = %d', $pid ) );
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_product SET use_both_option_types = 1 WHERE product_id = %d', $pid ) );
				}
			}
			$order = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX( option_order ) FROM ec_option_to_product WHERE product_id = %d', $pid ) );
			$wpdb->insert( 'ec_option_to_product', array( 'option_id' => $option_id, 'product_id' => $pid, 'option_order' => $order + 1 ) );
			$assigned++;
			do_action( 'wp_easycart_option_to_product_created', $option_id, $pid );
			do_action( 'wp_easycart_product_updated', $pid );
		}
		do_action( 'wp_easycart_optionset_updated', $option_id );
		wp_cache_flush();
		wp_send_json_success( array( 'assigned' => $assigned, 'skipped' => $skipped, 'stock_created' => 0 ) );
	}
	$limit = wp_easycart_admin_option_editor_v2::is_pro() ? 5 : (int) apply_filters( 'wp_easycart_admin_free_option_set_limit', 2 );
	$items = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT optionitem_id FROM ec_optionitem WHERE option_id = %d ORDER BY optionitem_order', $option_id ) ) );
	$assigned = 0; $skipped = 0; $stock_created = 0;
	foreach ( $pids as $pid ) {
		$p = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, option_id_1, option_id_2, option_id_3, option_id_4, option_id_5, use_optionitem_quantity_tracking FROM ec_product WHERE product_id = %d', $pid ) );
		if ( ! $p ) { continue; }
		$slot = 0;
		for ( $n = 1; $n <= $limit; $n++ ) {
			if ( (int) $p->{ 'option_id_' . $n } === $option_id ) { $slot = -1; break; }
			if ( ! $slot && ! (int) $p->{ 'option_id_' . $n } ) { $slot = $n; }
		}
		if ( $slot <= 0 ) { $skipped++; continue; }
		$wpdb->update( 'ec_product', array( 'option_id_' . $slot => $option_id ), array( 'product_id' => $pid ) );
		$assigned++;
		/* Build the cartesian stock rows for this product ( existing rows × new choices ) */
		if ( $items ) {
			$existing = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_optionitemquantity WHERE product_id = %d', $pid ), ARRAY_A );
			if ( empty( $existing ) ) {
				foreach ( $items as $iid ) {
					$row = array( 'product_id' => $pid, 'optionitem_id_1' => 0, 'optionitem_id_2' => 0, 'optionitem_id_3' => 0, 'optionitem_id_4' => 0, 'optionitem_id_5' => 0, 'quantity' => 0 );
					$row[ 'optionitem_id_' . $slot ] = $iid;
					$wpdb->insert( 'ec_optionitemquantity', $row ); $stock_created++;
				}
			} else {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ec_optionitemquantity WHERE product_id = %d', $pid ) );
				foreach ( $existing as $row ) {
					foreach ( $items as $iid ) {
						$new = array( 'product_id' => $pid, 'optionitem_id_1' => (int) $row['optionitem_id_1'], 'optionitem_id_2' => (int) $row['optionitem_id_2'], 'optionitem_id_3' => (int) $row['optionitem_id_3'], 'optionitem_id_4' => (int) $row['optionitem_id_4'], 'optionitem_id_5' => (int) $row['optionitem_id_5'], 'quantity' => $row['quantity'] );
						$new[ 'optionitem_id_' . $slot ] = $iid;
						$wpdb->insert( 'ec_optionitemquantity', $new ); $stock_created++;
					}
				}
			}
		}
		do_action( 'wp_easycart_product_updated', $pid );
	}
	do_action( 'wp_easycart_optionset_updated', $option_id );
	wp_send_json_success( array( 'assigned' => $assigned, 'skipped' => $skipped, 'stock_created' => $stock_created ) );
}
