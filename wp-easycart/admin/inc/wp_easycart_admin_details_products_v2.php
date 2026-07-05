<?php
/**
 * WP EasyCart Product Editor V2.
 *
 * Extends the existing details class so every field-group function
 * ( basic_fields, pricing_fields, ... ) and every public filter
 * ( wp_easycart_admin_product_details_*_fields_list ) keeps firing
 * unchanged. Only the rendering layer ( print_fields ) is replaced.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wp_easycart_admin_details_products_v2 extends wp_easycart_admin_details_products {

	/**
	 * When set, print_fields() only renders fields whose name is in this list.
	 * Lets one field-group action ( e.g. general options ) be split across tabs.
	 *
	 * @var array|false
	 */
	public $print_only = false;

	/**
	 * When set, print_fields() skips fields whose name is in this list.
	 *
	 * @var array
	 */
	public $print_except = array();

	/**
	 * Currently rendering section key, stamped on every field for dirty tracking.
	 *
	 * @var string
	 */
	public $current_section = '';

	public function load_template() {
		include( EC_PLUGIN_DIRECTORY . '/admin/template/products/products/product-details-v2.php' );
	}

	/**
	 * Drop-in replacement for the legacy output(). Mirrors the parent
	 * init flow but renders the v2 tabbed template.
	 */
	public function output( $type = 'edit' ) {
		$this->init();
		if ( 'edit' == $type ) {
			$this->init_data();
		}
		if ( $this->record_not_found ) {
			$this->print_record_not_found_notice();
			return;
		}
		$this->load_template();
	}

	/* ------------------------------------------------------------------ */
	/* Section + gate helpers used by the template                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Open a save-section card. $section maps 1:1 to a legacy save endpoint
	 * in products-details-v2.js ( ecdv2_payloads ).
	 */
	public function section_open( $section, $title, $hint = '', $args = array() ) {
		$this->current_section = $section;
		$this->print_only = isset( $args['only'] ) ? $args['only'] : false;
		$this->print_except = isset( $args['except'] ) ? $args['except'] : array();
		echo '<div class="ecdv2-card" data-ecdv2-section="' . esc_attr( $section ) . '">';
		echo '<div class="ecdv2-card-saving"></div>';
		echo '<div class="ecdv2-card-header">';
		echo '<h3 class="ecdv2-card-title">' . esc_html( $title ) . '</h3>';
		if ( '' !== $hint ) {
			echo '<span class="ecdv2-card-hint">' . esc_html( $hint ) . '</span>';
		}
		echo '<a href="' . esc_url_raw( $this->docs_link ) . '" target="_blank" class="ecdv2-help-link"><span class="dashicons dashicons-editor-help" style="font-size:14px;width:14px;height:14px;"></span>' . esc_html__( 'Help', 'wp-easycart' ) . '</a>';
		echo '</div><div class="ecdv2-card-body">';
	}

	public function section_close() {
		echo '</div></div>';
		$this->current_section = '';
		$this->print_only = false;
		$this->print_except = array();
	}

	/**
	 * Render a locked PRO feature row using the central gate.
	 * $feature_key controls the optional enabled_filter check.
	 */
	public function gate_row( $label, $description, $args = array() ) {
		$gate = wp_easycart_admin_pro_gate::evaluate( $args );
		if ( 'enabled' === $gate['state'] ) {
			return true; /* caller renders the real feature */
		}
		$badge = array(
			'upsell'   => __( 'PRO', 'wp-easycart' ),
			'inactive' => __( 'ACTIVATE PRO', 'wp-easycart' ),
			'update'   => __( 'UPDATE PRO', 'wp-easycart' ),
			'license'  => __( 'LICENSE', 'wp-easycart' ),
		);
		$state = $gate['state'];
		echo '<div class="ecdv2-gate" data-gate-state="' . esc_attr( $state ) . '">';
		echo '<div class="ecdv2-gate-head" onclick="ecdv2.gate_toggle(this);">';
		echo '<span class="dashicons dashicons-lock"></span>';
		echo '<span class="ecdv2-gate-label">' . esc_html( $label ) . '</span>';
		echo '<span class="ecdv2-gate-badge is-' . esc_attr( $state ) . '">' . esc_html( isset( $badge[ $state ] ) ? $badge[ $state ] : 'PRO' ) . '</span>';
		echo '</div>';
		echo '<div class="ecdv2-gate-body">';
		echo '<p class="ecdv2-gate-desc">' . esc_html( $description ) . '</p>';
		echo '<a class="ecdv2-gate-cta" href="' . esc_url( $gate['url'] ) . '">' . esc_html( $gate['desc'] ) . ' <span class="dashicons dashicons-arrow-right-alt2" style="font-size:13px;width:13px;height:13px;"></span></a>';
		echo '</div></div>';
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* V2 field-list adjustments                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * V2 keeps Price and Previous Price together on the General tab so the
	 * sale-pricing pair is managed in one place. The legacy field lists are
	 * untouched — the list_price field is spliced in/out on render only, and
	 * both the basic and pricing save endpoints persist it ( the element id
	 * is unchanged, so payload collection by id keeps working ).
	 */
	public function basic_fields() {
		add_filter( 'wp_easycart_admin_product_details_basic_fields_list', array( $this, 'v2_add_list_price_to_basic' ), 99, 2 );
		parent::basic_fields();
		remove_filter( 'wp_easycart_admin_product_details_basic_fields_list', array( $this, 'v2_add_list_price_to_basic' ), 99 );
	}

	public function pricing_fields() {
		add_filter( 'wp_easycart_admin_product_details_pricing_fields_list', array( $this, 'v2_remove_list_price_from_pricing' ), 99, 2 );
		add_filter( 'wp_easycart_admin_product_details_subscription_fields_list', array( $this, 'v2_subscription_requires' ), 99 );
		add_filter( 'wp_easycart_admin_product_details_featured_products_fields_list', array( $this, 'v2_featured_tag_inactive' ), 98, 2 );
		parent::pricing_fields();
		remove_filter( 'wp_easycart_admin_product_details_pricing_fields_list', array( $this, 'v2_remove_list_price_from_pricing' ), 99 );
	}

	/** Splice Previous Price directly after Price on the General tab. */
	public function v2_add_list_price_to_basic( $fields, $product = false ) {
		$list_price_field = array(
			'name'            => 'list_price',
			'type'            => 'currency',
			'label'           => __( 'Previous Price', 'wp-easycart' ),
			'required'        => false,
			'validation_type' => 'price',
			'visible'         => true,
			'default'         => '0.00',
			'value'           => $this->product->list_price,
			'placeholder'     => '0.00',
			'description'     => __( 'Optional compare-at price. When it is higher than Price, shoppers see it crossed out next to the new price. Leave 0 to hide.', 'wp-easycart' ),
		);
		$out = array();
		$inserted = false;
		foreach ( $fields as $field ) {
			$out[] = $field;
			if ( isset( $field['name'] ) && 'price' === $field['name'] && ! $inserted ) {
				$out[] = $list_price_field;
				$inserted = true;
			}
		}
		if ( ! $inserted ) {
			$out[] = $list_price_field;
		}
		return $out;
	}

	/** Previous Price now lives on the General tab; drop it here so the id stays unique. */
	public function v2_remove_list_price_from_pricing( $fields, $product = false ) {
		$out = array();
		foreach ( $fields as $field ) {
			if ( isset( $field['name'] ) && 'list_price' === $field['name'] ) {
				continue;
			}
			$out[] = $field;
		}
		return $out;
	}

	/**
	 * The featured-product pickers list every product, including deactivated
	 * ones — legitimate ( merchants stage cross-sells before activation ) but
	 * previously invisible. Tag deactivated products in the option data so
	 * the select renderer emits data-ec-inactive and the progressive list UI
	 * can badge them once chosen.
	 */
	public function v2_featured_tag_inactive( $fields, $product = false ) {
		global $wpdb;
		$inactive_ids = $wpdb->get_col( 'SELECT product_id FROM ec_product WHERE activate_in_store = 0' );
		if ( empty( $inactive_ids ) ) {
			return $fields;
		}
		$lookup = array_flip( array_map( 'intval', $inactive_ids ) );
		foreach ( $fields as $fi => $field ) {
			if ( ! isset( $field['data'] ) || ! is_array( $field['data'] ) ) {
				continue;
			}
			foreach ( $field['data'] as $option ) {
				if ( is_object( $option ) && isset( $lookup[ (int) $option->id ] ) ) {
					$option->inactive = 1;
				}
			}
		}
		return $fields;
	}

	/**
	 * Every subscription setting is meaningless until the Subscription
	 * Product toggle is on. Most core fields already declare a 'requires'
	 * dependency on it, but a few ( enable_duration,
	 * subscription_shipping_recurring, subscription_recurring_email ) don't,
	 * so they sat visible under a disabled toggle. Backfill the dependency
	 * on any non-toggle field that lacks one — the existing deps machinery
	 * handles the show/hide from there.
	 */
	public function v2_subscription_requires( $fields ) {
		foreach ( $fields as $i => $field ) {
			if ( ! isset( $field['name'] ) || 'is_subscription_item' === $field['name'] ) {
				continue;
			}
			if ( ! isset( $field['requires'] ) ) {
				$fields[ $i ]['requires'] = array(
					'name'         => 'is_subscription_item',
					'value'        => 1,
					'default_show' => false,
				);
			}
		}
		return $fields;
	}

	/**
	 * Pricing-context chips, printed directly under the Price / Previous
	 * Price pair on the General tab. When a product's real selling price is
	 * defined somewhere else ( per-variant prices, volume tiers, B2B roles,
	 * labels, ranges ), the merchant sees it right next to the base price and
	 * can jump there in one click instead of learning the left menu.
	 */
	public function print_price_context() {
		if ( ! $this->id ) {
			return;
		}
		global $wpdb;
		$p = $this->product;
		$chips = array();

		/* Variants: show whenever combinations exist; count how many carry a
		 * price override ( ec_optionitemquantity.price of -1 = use base ). */
		$variant_total = 0;
		$variant_priced = 0;
		if ( $p->option_id_1 || $p->option_id_2 || $p->option_id_3 || $p->option_id_4 || $p->option_id_5 ) {
			$variant_total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_optionitemquantity WHERE product_id = %d', $this->id ) );
			if ( $variant_total > 0 ) {
				$variant_priced = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_optionitemquantity WHERE product_id = %d AND price != -1', $this->id ) );
				$chips[] = array(
					'label' => $variant_priced > 0
						? sprintf( __( 'Variant pricing (%1$d of %2$d priced)', 'wp-easycart' ), $variant_priced, $variant_total )
						: sprintf( __( 'Variant pricing & stock (%d variants)', 'wp-easycart' ), $variant_total ),
					'tab'   => 'options',
				);
			}
		}

		$tier_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_pricetier WHERE product_id = %d', $this->id ) );
		if ( $tier_count > 0 ) {
			$chips[] = array(
				'label' => sprintf( _n( 'Volume pricing (%d tier)', 'Volume pricing (%d tiers)', $tier_count, 'wp-easycart' ), $tier_count ),
				'tab'   => 'pricing',
			);
		}

		$role_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_roleprice WHERE product_id = %d', $this->id ) );
		if ( $role_count > 0 ) {
			$chips[] = array(
				'label' => sprintf( _n( 'B2B pricing (%d role)', 'B2B pricing (%d roles)', $role_count, 'wp-easycart' ), $role_count ),
				'tab'   => 'pricing',
			);
		}

		if ( (int) $p->enable_price_label > 0 ) {
			$chips[] = array( 'label' => __( 'Custom price label', 'wp-easycart' ), 'tab' => 'pricing' );
		}
		if ( ! empty( $p->show_custom_price_range ) ) {
			$chips[] = array( 'label' => __( 'Price range display', 'wp-easycart' ), 'tab' => 'pricing' );
		}
		if ( ! empty( $p->login_for_pricing ) ) {
			$chips[] = array( 'label' => __( 'Login for pricing', 'wp-easycart' ), 'tab' => 'pricing' );
		}

		$chips = apply_filters( 'wp_easycart_admin_v2_price_context_chips', $chips, $p );

		/* The container always renders ( hidden when empty ) so the JS layer
		 * can dynamically add or remove chips as tiers, B2B roles, labels,
		 * ranges, login-gating, and variants change — without a reload. The
		 * variant seed carries the server-side counts the client can't cheaply
		 * recompute. */
		echo '<div class="ecdv2-field-full ecdv2-price-context" id="ecdv2_price_context" data-variant-total="' . esc_attr( $variant_total ) . '" data-variant-priced="' . esc_attr( $variant_priced ) . '"' . ( empty( $chips ) ? ' style="display:none;"' : '' ) . '>';
		echo '<span class="ecdv2-price-context-label"><span class="dashicons dashicons-tag"></span>' . esc_html__( 'Also affects pricing:', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-price-context-chips" id="ecdv2_price_context_chips">';
		foreach ( $chips as $chip ) {
			if ( empty( $chip['label'] ) || empty( $chip['tab'] ) ) {
				continue;
			}
			echo '<button type="button" class="ecdv2-price-context-chip" onclick="ecdv2.go_tab( \'' . esc_attr( $chip['tab'] ) . '\' );">';
			echo esc_html( $chip['label'] );
			echo '<span class="dashicons dashicons-arrow-right-alt2"></span>';
			echo '</button>';
		}
		echo '</span>';
		echo '</div>';
	}

	/**
	 * Google Merchant, natively in v2.
	 *
	 * The PRO printer builds a standard field array and passes it through the
	 * 'wp_easycart_admin_product_details_google_merchant_fields_list' filter
	 * before handing it to the LEGACY renderer. We hook that filter to capture
	 * the array and return an empty list ( so the legacy renderer prints
	 * nothing ), buffer-and-discard the legacy chrome ( heading + submit
	 * button ), and render the captured fields with print_field_v2 inside a
	 * real v2 section. That gives Google Merchant the same grid, dirty
	 * tracking, and global-Save behavior as every other panel.
	 *
	 * Older PRO builds without the filter fall back gracefully: the captured
	 * list stays empty and the buffered legacy HTML is returned for the
	 * CSS-reskinned compat card instead.
	 *
	 * @return array { 'fields' => array|false, 'legacy' => string }
	 */
	public function capture_google_merchant_fields() {
		$captured = array();
		$capture_cb = function( $fields ) use ( &$captured ) {
			$captured = is_array( $fields ) ? $fields : array();
			return array();
		};
		add_filter( 'wp_easycart_admin_product_details_google_merchant_fields_list', $capture_cb, 9999 );
		ob_start();
		do_action( 'wp_easycart_admin_product_details_googlemerchant_fields' );
		$legacy_html = ob_get_clean();
		remove_filter( 'wp_easycart_admin_product_details_google_merchant_fields_list', $capture_cb, 9999 );

		if ( empty( $captured ) ) {
			return array( 'fields' => false, 'legacy' => $legacy_html );
		}

		/* Heal values that historic saves stored slash/entity-escaped ( the
		 * same class of bug as the variant Google Merchant modal ): peel
		 * layered backslashes + HTML entities until stable so the form shows
		 * the literal text and the next save re-stores it clean. */
		foreach ( $captured as $gm_i => $gm_field ) {
			if ( isset( $gm_field['value'] ) && is_string( $gm_field['value'] ) ) {
				$gm_value = $gm_field['value'];
				$gm_guard = 0;
				do {
					$gm_prev = $gm_value;
					$gm_value = wp_specialchars_decode( stripslashes( $gm_value ), ENT_QUOTES );
					$gm_guard++;
				} while ( $gm_value !== $gm_prev && $gm_guard < 6 );
				$captured[ $gm_i ]['value'] = $gm_value;
			}
		}
		return array( 'fields' => $captured, 'legacy' => '' );
	}

	/* ------------------------------------------------------------------ */
	/* V2 renderer — replaces the legacy print_fields entirely             */
	/* ------------------------------------------------------------------ */

	public function print_fields( $fields ) {
		/* Menu Locations gets a bespoke layout ( per-location cards ) rather
		 * than the generic two-column grid — see print_menu_locations(). */
		if ( 'menus' === $this->current_section ) {
			$this->print_menu_locations( $fields );
			return;
		}

		echo '<div class="ecdv2-grid">';

		/* Field clusters: wrap a set of related fields in a contained
		 * sub-group so their relationship reads clearly. */
		$cluster_members = array();
		$cluster_title   = '';
		if ( 'pricing' === $this->current_section ) {
			$cluster_members = array( 'enable_price_label', 'replace_price_label', 'custom_price_label' );
			$cluster_title   = __( 'Custom Price Label', 'wp-easycart' );
		}
		$cluster_open = false;

		foreach ( $fields as $field ) {
			$fname = isset( $field['name'] ) ? $field['name'] : '';

			if ( $cluster_members && $fname === $cluster_members[0] && ! $cluster_open ) {
				echo '<div class="ecdv2-subgroup ecdv2-field-full"><div class="ecdv2-subgroup-title">' . esc_html( $cluster_title ) . '</div><div class="ecdv2-subgroup-body">';
				$cluster_open = true;
			}

			$this->print_field_v2( $field );

			/* General tab: right under the Price / Previous Price pair, surface
			 * every other place this product's pricing is defined ( variants,
			 * volume tiers, B2B roles, labels ) with one-click navigation. */
			if ( 'basic' === $this->current_section && 'list_price' === $fname ) {
				$this->print_price_context();
			}

			if ( $cluster_open && $fname === end( $cluster_members ) ) {
				echo '</div></div>';
				$cluster_open = false;
			}
		}

		if ( $cluster_open ) {
			echo '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * Menu Locations — a product can sit in up to three store menu locations,
	 * each of which is a drill-down path: Menu Level 1 → Level 2 → Level 3.
	 *
	 * The nine level selects arrive flat but already grouped by location
	 * ( location N's levels 1-3, then location N+1 ). The legacy renderer dumped
	 * them into a two-column grid and mis-detected location boundaries
	 * ( keying on menulevel1_id_* — which are location 1's three levels — instead
	 * of menulevel{N}_id_1 ), which scattered the fields. Here each location is
	 * rendered as its own card so the three-level path reads clearly.
	 *
	 * Select ids/names, the select2 class and the data-ecdv2-sec attribute are
	 * all preserved ( via print_field_v2 ), so the section save ( which reads
	 * each select by id ) and the dirty-tracking continue to work unchanged.
	 */
	private function print_menu_locations( $fields ) {
		echo '<div class="ecdv2-menu-locations">';
		echo '<p class="ecdv2-menu-locations-note">' . esc_html__( 'Each location drills down Menu Level 1 → 2 → 3. Choose a Level 1, then narrow with Level 2 and 3 if you need to; leave a level on "None Selected" to stop there.', 'wp-easycart' ) . '</p>';

		$open = false;
		foreach ( $fields as $field ) {
			$fname = isset( $field['name'] ) ? $field['name'] : '';

			/* A new location begins at its Level-1 select: menulevel{N}_id_1. */
			if ( $fname && preg_match( '/^menulevel(\d+)_id_1$/', $fname, $m ) ) {
				if ( $open ) {
					echo '</div></div>';
				}
				$loc = (int) $m[1];
				echo '<div class="ecdv2-menu-location">';
				echo '<div class="ecdv2-menu-location-head">';
				echo '<span class="ecdv2-menu-location-badge">' . esc_html( $loc ) . '</span>';
				echo '<span class="ecdv2-menu-location-title">' . esc_html( sprintf( __( 'Menu Location %d', 'wp-easycart' ), $loc ) ) . '</span>';
				echo '</div>';
				echo '<div class="ecdv2-menu-location-body">';
				$open = true;
			}

			/* The legacy onchange ( product_details_update_menus ) is inert in v2:
			 * it expects a string field name and targets #ec_admin_row_* wrappers
			 * that print_field_v2 does not emit. Drop it so no dead handler fires;
			 * wiring a real v2 parent→child cascade is a separate follow-up. */
			unset( $field['onchange'] );

			$this->print_field_v2( $field );
		}
		if ( $open ) {
			echo '</div></div>';
		}
		echo '</div>';
	}

	private function field_is_gated( $field ) {
		$gated_handler = false;
		foreach ( array( 'onclick', 'onchange' ) as $evt ) {
			if ( isset( $field[ $evt ] ) && false !== strpos( (string) $field[ $evt ], 'show_pro_required' ) ) {
				$gated_handler = true;
			}
		}
		return $gated_handler;
	}

	private function dep_attrs( $field ) {
		$attrs = '';
		if ( isset( $field['requires'] ) && is_array( $field['requires'] ) && isset( $field['requires']['name'] ) ) {
			/* Some fields ( e.g. backorder_fill_date ) define requires without
			 * a value key; default to 1 = "show when the controller is on". */
			$req_value = array_key_exists( 'value', $field['requires'] ) ? $field['requires']['value'] : 1;
			$req_val = is_array( $req_value ) ? implode( ',', $req_value ) : $req_value;
			$attrs .= ' data-ecdv2-requires="' . esc_attr( $field['requires']['name'] ) . '" data-ecdv2-requires-value="' . esc_attr( $req_val ) . '"';
		}
		return $attrs;
	}

	/* ------------------------------------------------------------------ */
	/* Store currency formatting ( mirrors ec_currency settings )           */
	/* ------------------------------------------------------------------ */

	private function bootstrap_currency() {
		static $done = false;
		if ( ! $done && class_exists( 'ec_currency' ) && null === ec_currency::$static_decimal_length ) {
			new ec_currency();
		}
		$done = true;
	}

	public function currency_decimals() {
		$this->bootstrap_currency();
		if ( class_exists( 'ec_currency' ) && null !== ec_currency::$static_decimal_length ) {
			return (int) ec_currency::$static_decimal_length;
		}
		$decimals = get_option( 'ec_option_currency_decimal_places' );
		return ( '' === $decimals || ! is_numeric( $decimals ) || $decimals < 0 ) ? 2 : (int) $decimals;
	}

	public function currency_symbol() {
		$this->bootstrap_currency();
		if ( class_exists( 'ec_currency' ) && ec_currency::$static_symbol ) {
			return ec_currency::$static_symbol;
		}
		$symbol = get_option( 'ec_option_currency' );
		return $symbol ? $symbol : '$';
	}

	public function currency_decimal_symbol() {
		$this->bootstrap_currency();
		if ( class_exists( 'ec_currency' ) && null !== ec_currency::$static_decimal_symbol ) {
			return (string) ec_currency::$static_decimal_symbol;
		}
		$symbol = get_option( 'ec_option_currency_decimal_symbol' );
		return ( false === $symbol || '' === $symbol ) ? '.' : (string) $symbol;
	}

	public function currency_grouping_symbol() {
		$this->bootstrap_currency();
		if ( class_exists( 'ec_currency' ) && null !== ec_currency::$static_grouping_symbol ) {
			return (string) ec_currency::$static_grouping_symbol;
		}
		$symbol = get_option( 'ec_option_currency_thousands_seperator' );
		return ( false === $symbol ) ? ',' : (string) $symbol;
	}

	public function currency_symbol_left() {
		$this->bootstrap_currency();
		if ( class_exists( 'ec_currency' ) ) {
			return (bool) ec_currency::$static_symbol_location;
		}
		return (bool) get_option( 'ec_option_currency_symbol_location' );
	}

	/** Format a price with the store's symbol, decimals, and separators. */
	public function format_price( $amount ) {
		$number = number_format( (float) $amount, $this->currency_decimals(), $this->currency_decimal_symbol(), $this->currency_grouping_symbol() );
		return $this->currency_symbol_left() ? $this->currency_symbol() . $number : $number . $this->currency_symbol();
	}

	/** Step attribute value for number inputs at the store's precision. */
	private function currency_step() {
		$decimals = $this->currency_decimals();
		return ( $decimals > 0 ) ? '0.' . str_repeat( '0', $decimals - 1 ) . '1' : '1';
	}

	private function field_value( $field ) {
		$value = isset( $field['value'] ) ? $field['value'] : '';
		if ( ( '' === $value || null === $value ) && isset( $field['default'] ) ) {
			$value = $field['default'];
		}
		return $value;
	}

	public function print_field_v2( $field ) {
		$name = isset( $field['name'] ) ? $field['name'] : ( isset( $field['alt_name'] ) ? $field['alt_name'] : '' );
		if ( '' === $name ) {
			return;
		}
		if ( false !== $this->print_only && ! in_array( $name, $this->print_only, true ) && 'hidden' !== $field['type'] ) {
			return;
		}
		if ( in_array( $name, $this->print_except, true ) && 'hidden' !== $field['type'] ) {
			return;
		}

		$type = $field['type'];
		$value = $this->field_value( $field );
		$label = isset( $field['label'] ) ? $field['label'] : '';
		$sec = ' data-ecdv2-sec="' . esc_attr( $this->current_section ) . '"';
		$deps = $this->dep_attrs( $field );
		$hidden_dep = ( isset( $field['requires'] ) && ( ! isset( $field['requires']['default_show'] ) || ! $field['requires']['default_show'] ) );
		$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
		$onchange = ( isset( $field['onchange'] ) && ! $this->field_is_gated( $field ) ) ? ' onchange="' . esc_attr( $field['onchange'] ) . '( this );"' : '';
		$onclick = ( isset( $field['onclick'] ) && ! $this->field_is_gated( $field ) ) ? ' onclick="' . esc_attr( $field['onclick'] ) . '( this );"' : '';
		$required = ( isset( $field['required'] ) && $field['required'] ) ? '<span class="ecdv2-req">*</span>' : '';
		$validation = isset( $field['validation_type'] ) ? ' data-ecdv2-validate="' . esc_attr( $field['validation_type'] ) . '"' : '';
		$msg = isset( $field['message'] ) ? '<span class="ecdv2-field-msg">' . wp_kses_post( $field['message'] ) . '</span>' : '';

		/* Hidden fields always render so legacy collection by id works. */
		if ( 'hidden' === $type ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . ' />';
			return;
		}

		/* Locked PRO field: keep a hidden carrier input so save payloads stay complete. */
		if ( $this->field_is_gated( $field ) ) {
			$carrier = ( 'checkbox' === $type && ! $value ) ? '' : $value;
			echo '<div class="ecdv2-field ecdv2-field-full"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( is_array( $value ) ? wp_json_encode( $value ) : $carrier ) . '"' . $sec . ' />';
			$this->gate_row( $label, apply_filters( 'wp_easycart_admin_v2_gate_description', __( 'Unlock this feature with WP EasyCart PRO.', 'wp-easycart' ), $name ) );
			echo '</div>';
			return;
		}

		switch ( $type ) {

			case 'checkbox':
				$desc = isset( $field['description'] ) ? $field['description'] : '';
				$show_attr = '';
				if ( isset( $field['show'] ) ) {
					$show_attr = ' data-ecdv2-shows="' . esc_attr( $field['show']['name'] ) . '"';
				}
				echo '<div class="ecdv2-field ecdv2-field-full ecdv2-toggle-row"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<span class="ecdv2-toggle"><input type="checkbox" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="1"' . checked( 1, (int) $value, false ) . $sec . $show_attr . $onclick . $onchange . ' /><span class="ecdv2-toggle-track"></span></span>';
				echo '<span class="ecdv2-toggle-meta"><label class="ecdv2-toggle-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
				if ( '' !== $desc ) {
					echo '<div class="ecdv2-toggle-desc">' . esc_html( $desc ) . '</div>';
				}
				echo '</span></div>';
				break;

			case 'select':
				$multiple = ( isset( $field['multiple'] ) && $field['multiple'] ) ? ' multiple="multiple"' : '';
				$select2 = ( isset( $field['select2'] ) && 'none' !== $field['select2'] ) ? ' ecdv2-select2' : '';
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . '</label>';
				echo '<select name="' . esc_attr( $name ) . ( $multiple ? '[]' : '' ) . '" id="' . esc_attr( $name ) . '" class="' . esc_attr( trim( $select2 ) ) . '"' . $multiple . $sec . $onchange . $validation . '>';
				if ( isset( $field['data_label'] ) ) {
					$dl_selected = ( isset( $field['default_selected'] ) && $field['default_selected'] ) ? ' selected' : '';
					$dl_value = isset( $field['default_value'] ) ? $field['default_value'] : '0';
					echo '<option value="' . esc_attr( $dl_value ) . '"' . $dl_selected . '>' . esc_html( $field['data_label'] ) . '</option>';
				}
				if ( isset( $field['data'] ) && is_array( $field['data'] ) ) {
					foreach ( $field['data'] as $option ) {
						$selected = '';
						if ( is_array( $value ) ) {
							$selected = in_array( $option->id, $value ) ? ' selected' : ''; // phpcs:ignore WordPress.PHP.StrictInArray
						} else if ( (string) $option->id === (string) $value ) {
							$selected = ' selected';
						}
						/* Optional per-option marker ( e.g. featured-product
						 * pickers tag deactivated products so the JS list can
						 * badge them ). */
						$option_attrs = ! empty( $option->inactive ) ? ' data-ec-inactive="1"' : '';
						echo '<option value="' . esc_attr( $option->id ) . '"' . $selected . $option_attrs . '>' . esc_html( $option->value ) . '</option>';
					}
				}
				echo '</select>' . $msg . '</div>';
				break;

			case 'currency':
				$formatted = ( '' === $value || null === $value ) ? '' : number_format( (float) $value, $this->currency_decimals(), '.', '' );
				$desc_html = ( isset( $field['description'] ) && '' !== $field['description'] ) ? '<span class="ecdv2-field-desc">' . esc_html( $field['description'] ) . '</span>' : '';
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . '</label>';
				echo '<span class="ecdv2-currency-wrap"><span class="ecdv2-currency-symbol">' . esc_html( apply_filters( 'wp_easycart_admin_currency_symbol', $this->currency_symbol() ) ) . '</span>';
				echo '<input type="number" step="' . esc_attr( $this->currency_step() ) . '" min="0" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $formatted ) . '"' . $sec . $placeholder . $validation . $onchange . ' /></span>' . $desc_html . $msg . '</div>';
				break;

			case 'number':
				$step = isset( $field['step'] ) ? $field['step'] : 'any';
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . '</label>';
				echo '<input type="number" step="' . esc_attr( $step ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . $placeholder . $validation . $onchange . ' />' . $msg . '</div>';
				break;

			case 'date':
				$date_min = isset( $field['min'] ) ? ' min="' . esc_attr( $field['min'] ) . '"' : '';
				$date_max = isset( $field['max'] ) ? ' max="' . esc_attr( $field['max'] ) . '"' : '';
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . '</label>';
				echo '<input type="date" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $date_min . $date_max . $sec . ' />' . $msg . '</div>';
				break;

			case 'color':
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
				echo '<span class="ecdv2-color-wrap"><input type="color" value="' . esc_attr( '' !== $value ? $value : '#000000' ) . '" data-ecdv2-color-for="' . esc_attr( $name ) . '" /><input type="text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . $placeholder . ' /></span></div>';
				break;

			case 'textarea':
				echo '<div class="ecdv2-field ecdv2-field-full"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . '</label>';
				echo '<textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '"' . $sec . $placeholder . $validation . '>' . esc_textarea( $value ) . '</textarea>';
				if ( 'seo_description' === $name ) {
					echo '<span class="ecdv2-char-count" data-ecdv2-count-for="seo_description" data-ecdv2-count-max="160"></span>';
				}
				echo $msg . '</div>';
				break;

			case 'wp_textarea':
				echo '<div class="ecdv2-field ecdv2-field-full" data-ecdv2-wpeditor="' . esc_attr( $name ) . '"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . ' data-ecdv2-sec-editor="' . esc_attr( $this->current_section ) . '">';
				if ( '' !== $label ) {
					echo '<label class="ecdv2-label">' . esc_html( $label ) . '</label>';
				}
				wp_editor( $value, $name, array( 'textarea_rows' => 8, 'media_buttons' => true ) );
				echo '</div>';
				break;

			case 'manufacturer':
				global $wpdb;
				$manufacturers = $wpdb->get_results( 'SELECT manufacturer_id, name FROM ec_manufacturer ORDER BY name ASC' );
				echo '<div class="ecdv2-field">';
				echo '<label class="ecdv2-label" for="manufacturer_id">' . esc_html( $label ) . '</label>';
				echo '<select name="manufacturer_id" id="manufacturer_id" class="ecdv2-select2"' . $sec . '>';
				echo '<option value="0">' . esc_html__( 'No Manufacturer', 'wp-easycart' ) . '</option>';
				foreach ( $manufacturers as $manufacturer ) {
					echo '<option value="' . esc_attr( $manufacturer->manufacturer_id ) . '"' . selected( $manufacturer->manufacturer_id, $this->product->manufacturer_id, false ) . '>' . esc_html( $manufacturer->name ) . '</option>';
				}
				echo '</select>';
				echo '<div style="display:flex; gap:8px; margin-top:6px;">';
				/* Nonce ACTION must match the server's verify_access() in
				 * ec_admin_ajax_product_details_insert_manufacturer — it checks
				 * 'wp-easycart-manufacturer-details'. A mismatched action here
				 * made every Create attempt fail verification and return "0". */
				wp_easycart_admin_verification()->print_nonce_field( 'manufacturer_new_nonce', 'wp-easycart-manufacturer-details' );
				echo '<input type="text" id="manufacturer_name" placeholder="' . esc_attr__( 'New manufacturer name', 'wp-easycart' ) . '" style="flex:1;" />';
				echo '<input type="button" value="' . esc_attr__( 'Create', 'wp-easycart' ) . '" onclick="return ec_admin_product_details_add_new_manufacturer( );" />';
				echo '</div></div>';
				break;

			case 'image_upload':
			case 'image_preview':
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
				echo '<div style="display:flex; gap:8px;">';
				echo '<input type="text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" style="flex:1;"' . $sec . $placeholder . ' />';
				if ( 'image_upload' === $type ) {
					echo '<input type="button" value="' . esc_attr__( 'Select File', 'wp-easycart' ) . '" onclick="ecdv2.media_pick( \'' . esc_attr( $name ) . '\' ); return false;" />';
				}
				echo '</div></div>';
				break;

			case 'wp_image_upload':
				$preview = '';
				if ( $value ) {
					$img = wp_get_attachment_image_src( (int) $value, 'thumbnail' );
					if ( $img ) {
						$preview = $img[0];
					}
				}
				echo '<div class="ecdv2-field ecdv2-field-full">';
				echo '<label class="ecdv2-label">' . esc_html( $label ) . '</label>';
				echo '<div style="display:flex; align-items:center; gap:10px;">';
				echo '<div id="' . esc_attr( $name ) . '_preview" style="width:64px; height:64px; border-radius:6px; background:var(--ecv2-g100) center/cover no-repeat; flex-shrink:0;' . ( $preview ? ' background-image:url(' . esc_url( $preview ) . ');' : '' ) . '"></div>';
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . ' />';
				echo '<input type="button" value="' . esc_attr__( 'Choose Image', 'wp-easycart' ) . '" onclick="ecdv2.media_pick( \'' . esc_attr( $name ) . '\', true ); return false;" />';
				echo '<input type="button" value="' . esc_attr__( 'Remove', 'wp-easycart' ) . '" onclick="ecdv2.media_clear( \'' . esc_attr( $name ) . '\' ); return false;" />';
				echo '</div></div>';
				break;

			case 'subscription_interval':
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<label class="ecdv2-label">' . esc_html( $label ) . '</label>';
				echo '<div style="display:flex; gap:8px;">';
				echo '<select name="subscription_bill_length" id="subscription_bill_length"' . $sec . ' style="flex:0 0 90px;">';
				for ( $i = 1; $i <= 12; $i++ ) {
					echo '<option value="' . esc_attr( $i ) . '"' . selected( $i, (int) $this->product->subscription_bill_length, false ) . '>' . esc_html( $i ) . '</option>';
				}
				echo '</select>';
				$periods = array( 'D' => __( 'Day(s)', 'wp-easycart' ), 'W' => __( 'Week(s)', 'wp-easycart' ), 'M' => __( 'Month(s)', 'wp-easycart' ), 'Y' => __( 'Year(s)', 'wp-easycart' ) );
				echo '<select name="subscription_bill_period" id="subscription_bill_period"' . $sec . ' style="flex:1;">';
				foreach ( $periods as $period_key => $period_label ) {
					echo '<option value="' . esc_attr( $period_key ) . '"' . selected( $period_key, $this->product->subscription_bill_period, false ) . '>' . esc_html( $period_label ) . '</option>';
				}
				echo '</select></div></div>';
				break;

			case 'categories':
				$this->print_categories_v2();
				break;

			case 'tier_pricing':
				$this->print_tier_pricing_v2( $label );
				break;

			case 'b2b_pricing':
				$this->print_b2b_pricing_v2( $label );
				break;

			case 'text':
			default:
				$is_slug = ( 'post_slug' === $name );
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>';
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required;
				if ( $is_slug ) {
					echo ' <span class="ecdv2-label-hint">' . esc_html__( '(auto-generated from title)', 'wp-easycart' ) . '</span>';
				}
				echo '</label>';
				if ( $is_slug ) {
					echo '<div class="ecdv2-slug-wrap">';
				}
				echo '<input type="text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . $placeholder . $validation . $onchange . ' />';
				if ( $is_slug ) {
					echo '<button type="button" class="ecdv2-slug-lock" id="ecdv2_slug_lock" title="' . esc_attr__( 'Lock slug (stop auto-generating from title)', 'wp-easycart' ) . '" onclick="ecdv2.slug_lock_toggle(); return false;"><span class="dashicons dashicons-unlock" style="font-size:15px;width:15px;height:15px;"></span></button></div>';
				}
				echo $msg . '</div>';
				break;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Complex field types                                                  */
	/* ------------------------------------------------------------------ */

	private function print_categories_v2() {
		global $wpdb;
		$all_categories = $wpdb->get_results( 'SELECT category_id, category_name FROM ec_category ORDER BY category_name ASC' );
		$assigned = array();
		foreach ( (array) $this->categories as $category ) {
			$assigned[ $category->category_id ] = $category->category_name;
		}
		echo '<div class="ecdv2-field ecdv2-field-full">';
		echo '<div class="ecdv2-cat-tokens" id="ecdv2_cat_tokens">';
		if ( empty( $assigned ) ) {
			echo '<span class="ecdv2-cat-empty" id="ecdv2_cat_empty">' . esc_html__( 'No categories assigned yet.', 'wp-easycart' ) . '</span>';
		}
		foreach ( $assigned as $cat_id => $cat_name ) {
			echo '<span class="ecdv2-cat-token" data-category-id="' . esc_attr( $cat_id ) . '">' . esc_html( $cat_name ) . '<button type="button" onclick="ecdv2.category_remove( ' . (int) $cat_id . ' );" aria-label="' . esc_attr__( 'Remove category', 'wp-easycart' ) . '">&times;</button></span>';
		}
		echo '</div>';
		echo '<div class="ecdv2-cat-add">';
		echo '<select id="ecdv2_cat_select" class="ecdv2-select2">';
		echo '<option value="0">' . esc_html__( 'Select a category to add...', 'wp-easycart' ) . '</option>';
		foreach ( $all_categories as $category ) {
			if ( ! isset( $assigned[ $category->category_id ] ) ) {
				echo '<option value="' . esc_attr( $category->category_id ) . '">' . esc_html( $category->category_name ) . '</option>';
			}
		}
		echo '</select>';
		echo '<input type="button" value="' . esc_attr__( 'Add', 'wp-easycart' ) . '" onclick="ecdv2.category_add(); return false;" />';
		echo '<a href="admin.php?page=wp-easycart-products&subpage=categories" target="_blank" style="align-self:center; font-size:12px;">' . esc_html__( 'Manage categories', 'wp-easycart' ) . '</a>';
		echo '</div></div>';
	}

	private function print_tier_pricing_v2( $label ) {
		global $wpdb;
		$pro_enabled = $this->gate_row(
			$label ? $label : __( 'Volume Pricing', 'wp-easycart' ),
			__( 'Reward larger orders with automatic quantity-based discounts. Set price breaks like 10+ for $9.99, 50+ for $8.49.', 'wp-easycart' )
		);
		if ( ! $pro_enabled ) {
			return;
		}
		$tiers = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_pricetier WHERE product_id = %d ORDER BY quantity ASC', $this->id ) );
		echo '<div class="ecdv2-field ecdv2-field-full ecdv2-advblock ecdv2-tier-block">';
		echo '<div class="ecdv2-advblock-head">';
		echo '<span class="ecdv2-advblock-title">' . esc_html( $label ? $label : __( 'Volume Pricing', 'wp-easycart' ) ) . '</span>';
		echo '<span class="ecdv2-advblock-hint">' . esc_html__( 'Quantity-based price breaks', 'wp-easycart' ) . '</span>';
		echo '</div>';

		/* Add row at the TOP, written as a fill-in-the-blank sentence so the
		 * columns are self-labeling; the list rows below read the same way. */
		echo '<div class="ecdv2-tier-add">';
		echo '<span class="ecdv2-tier-sentence">';
		echo '<span class="ecdv2-tier-word">' . esc_html__( 'Buy', 'wp-easycart' ) . '</span>';
		echo '<input type="number" id="ec_admin_new_price_tier_quantity" min="2" step="1" placeholder="10" />';
		echo '<span class="ecdv2-tier-word">' . esc_html__( 'or more', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-tier-word">' . esc_html__( 'for', 'wp-easycart' ) . ' ' . esc_html( $this->currency_symbol() ) . '</span>';
		echo '<input type="number" id="ec_admin_new_price_tier_price" min="0" step="' . esc_attr( $this->currency_step() ) . '" placeholder="0.00" />';
		echo '<span class="ecdv2-tier-word">' . esc_html__( 'each', 'wp-easycart' ) . '</span>';
		echo '</span>';
		echo '<button type="button" class="ecdv2-tier-add-btn" onclick="return ec_admin_product_details_add_price_tier();"><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__( 'Add Tier', 'wp-easycart' ) . '</button>';
		echo '</div>';

		echo '<div id="price_tiers_holder" class="ecdv2-tier-list">';
		if ( empty( $tiers ) ) {
			echo '<div id="ec_admin_no_price_tiers" class="ecdv2-advblock-empty">' . esc_html__( 'No volume pricing tiers yet. Add your first price break above.', 'wp-easycart' ) . '</div>';
		} else {
			foreach ( $tiers as $tier ) {
				echo $this->tier_row_html( $tier ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * A clean inline "x" delete icon. Inline SVG so it never depends on the
	 * dashicons font loading or on a CSS glyph override surviving cache.
	 */
	private function x_icon() {
		return '<svg class="ecdv2-x" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true" focusable="false"><path d="M5 5l10 10M15 5L5 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
	}

	/**
	 * Renders a tier row. Quantity/price inputs auto-save through their
	 * onchange ( the edit endpoint ), so there is no separate save button;
	 * delete is a clean inline-SVG "x". Server-added rows are normalized to
	 * this same shape by the JS.
	 */
	private function tier_row_html( $tier ) {
		$id  = $tier->pricetier_id;
		$rid = 'ec_admin_product_details_price_tier_row_' . $id;
		$price = number_format( (float) $tier->price, 2, '.', '' );
		$h  = '<div class="ec_admin_price_tier_row" id="' . esc_attr( $rid ) . '">';
		$h .= '<span><input type="number" value="' . esc_attr( $tier->quantity ) . '" id="' . esc_attr( $rid ) . '_quantity" onchange="ec_admin_product_details_edit_price_tier( \'' . esc_attr( $id ) . '\' );" /></span>';
		$h .= '<span><input type="number" min="0" step=".001" value="' . esc_attr( $price ) . '" id="' . esc_attr( $rid ) . '_price" onchange="ec_admin_product_details_edit_price_tier( \'' . esc_attr( $id ) . '\' );" /></span>';
		$h .= '<span class="ecdv2-row-actions"><a href="" class="ecdv2-row-del" onclick="return ec_admin_product_details_delete_price_tier( \'' . esc_attr( $id ) . '\' );" title="' . esc_attr__( 'Delete', 'wp-easycart' ) . '">' . $this->x_icon() . '</a></span>';
		$h .= '</div>';
		return $h;
	}

	private function print_b2b_pricing_v2( $label ) {
		global $wpdb;
		$pro_enabled = $this->gate_row(
			$label ? $label : __( 'B2B Pricing', 'wp-easycart' ),
			__( 'Offer wholesale or member pricing by user role. Each role can see its own price for this product.', 'wp-easycart' )
		);
		if ( ! $pro_enabled ) {
			return;
		}
		$role_prices = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_roleprice WHERE product_id = %d ORDER BY role_label ASC', $this->id ) );
		$roles = $wpdb->get_results( 'SELECT role_label FROM ec_role ORDER BY role_label ASC' );
		echo '<div class="ecdv2-field ecdv2-field-full ecdv2-advblock ecdv2-role-block">';
		echo '<div class="ecdv2-advblock-head">';
		echo '<span class="ecdv2-advblock-title">' . esc_html( $label ? $label : __( 'B2B Pricing', 'wp-easycart' ) ) . '</span>';
		echo '<span class="ecdv2-advblock-hint">' . esc_html__( 'Wholesale or member pricing by user role', 'wp-easycart' ) . '</span>';
		echo '</div>';

		/* Add row on top, same fill-in-the-blank sentence pattern as volume. */
		echo '<div class="ecdv2-tier-add ecdv2-role-add">';
		echo '<span class="ecdv2-tier-sentence">';
		echo '<select id="add_new_role_price_role">';
		foreach ( $roles as $role ) {
			echo '<option value="' . esc_attr( $role->role_label ) . '">' . esc_html( $role->role_label ) . '</option>';
		}
		echo '</select>';
		echo '<span class="ecdv2-tier-word">' . esc_html__( 'pays', 'wp-easycart' ) . ' ' . esc_html( $this->currency_symbol() ) . '</span>';
		echo '<input type="number" id="ec_admin_new_role_price" min="0" step="' . esc_attr( $this->currency_step() ) . '" placeholder="0.00" />';
		echo '</span>';
		echo '<button type="button" class="ecdv2-tier-add-btn" onclick="return ecdv2.add_role_price();"><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__( 'Add Role Price', 'wp-easycart' ) . '</button>';
		echo '</div>';

		echo '<div id="role_prices_holder" class="ecdv2-role-list">';
		if ( empty( $role_prices ) ) {
			echo '<div id="ec_admin_no_role_prices" class="ecdv2-advblock-empty">' . esc_html__( 'No B2B role pricing yet. Add a role price above.', 'wp-easycart' ) . '</div>';
		} else {
			foreach ( $role_prices as $rp ) {
				echo $this->role_price_row_html( $rp ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * One B2B role-price row. The row id matches what the legacy
	 * delete_role_price() targets, and data-role lets the add handler block
	 * duplicate roles. Read price from the ec_roleprice.role_price column.
	 */
	private function role_price_row_html( $rp ) {
		$id  = $rp->roleprice_id;
		$rid = 'ec_admin_product_details_role_price_row_' . $id;
		$h  = '<div class="ec_admin_role_price_row" id="' . esc_attr( $rid ) . '" data-role="' . esc_attr( $rp->role_label ) . '">';
		$h .= '<span class="ecdv2-role-label">' . esc_html( $rp->role_label ) . '</span>';
		$h .= '<span class="ecdv2-role-price">' . esc_html( $this->format_price( $rp->role_price ) ) . '</span>';
		$h .= '<span class="ecdv2-role-actions"><a href="" class="ecdv2-row-del" onclick="return ec_admin_product_details_delete_role_price( \'' . esc_attr( $id ) . '\' );" title="' . esc_attr__( 'Delete', 'wp-easycart' ) . '">' . $this->x_icon() . '</a></span>';
		$h .= '</div>';
		return $h;
	}

	/* ------------------------------------------------------------------ */
	/* Listing health                                                       */
	/* ------------------------------------------------------------------ */

	public function get_health_checks() {
		global $wpdb;
		$p = $this->product;
		$has_image = ( '' !== $p->image1 || ( isset( $p->product_images ) && '' !== (string) $p->product_images ) );
		$category_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_categoryitem WHERE product_id = %d', $this->id ) );
		$checks = array(
			array( 'key' => 'image', 'label' => __( 'At least one product image', 'wp-easycart' ), 'done' => $has_image, 'tab' => 'media' ),
			array( 'key' => 'description', 'label' => __( 'Description written', 'wp-easycart' ), 'done' => ( strlen( wp_strip_all_tags( (string) $p->description ) ) > 20 ), 'tab' => 'general' ),
			array( 'key' => 'short_description', 'label' => __( 'Short description for listings', 'wp-easycart' ), 'done' => ( '' !== trim( (string) $p->short_description ) ), 'tab' => 'general' ),
			array( 'key' => 'price', 'label' => __( 'Price set', 'wp-easycart' ), 'done' => ( (float) $p->price > 0 || $p->is_donation || $p->inquiry_mode ), 'tab' => 'general' ),
			array( 'key' => 'category', 'label' => __( 'Assigned to a category', 'wp-easycart' ), 'done' => ( $category_count > 0 ), 'tab' => 'organize' ),
			array( 'key' => 'seo', 'label' => __( 'SEO description added', 'wp-easycart' ), 'done' => ( '' !== trim( (string) $p->seo_description ) ), 'tab' => 'seo' ),
			array( 'key' => 'weight', 'label' => __( 'Shipping weight entered', 'wp-easycart' ), 'done' => ( ! $p->is_shippable || (float) $p->weight > 0 ), 'tab' => 'inventory' ),
			array( 'key' => 'stock', 'label' => __( 'Stock level or tracking decided', 'wp-easycart' ), 'done' => ( ! $p->show_stock_quantity || (int) $p->stock_quantity > 0 || $p->use_optionitem_quantity_tracking ), 'tab' => 'inventory' ),
			array( 'key' => 'active', 'label' => __( 'Product activated in store', 'wp-easycart' ), 'done' => (bool) $p->activate_in_store, 'tab' => 'general' ),
		);
		return apply_filters( 'wp_easycart_admin_v2_health_checks', $checks, $p );
	}

	public function print_health() {
		$checks = $this->get_health_checks();
		$done = 0;
		foreach ( $checks as $check ) {
			if ( $check['done'] ) {
				$done++;
			}
		}
		$total = count( $checks );
		$pct = $total ? round( $done / $total * 100 ) : 0;
		echo '<div class="ecdv2-health" id="ecdv2_health">';
		echo '<div class="ecdv2-health-top" onclick="this.parentNode.classList.toggle( \'is-open\' );">';
		echo '<span class="ecdv2-health-ring">' . esc_html( $done . '/' . $total ) . '</span>';
		echo '<div class="ecdv2-health-bar"><div class="ecdv2-health-bar-fill" style="width:' . esc_attr( $pct ) . '%;"></div></div>';
		echo '<span class="ecdv2-health-label">' . esc_html__( 'Listing health', 'wp-easycart' ) . ' &middot; ' . esc_html( $pct ) . '%</span>';
		echo '<span class="dashicons dashicons-arrow-down-alt2" style="color:var(--ecv2-g400); font-size:14px; width:14px; height:14px;"></span>';
		echo '</div><div class="ecdv2-health-items">';
		foreach ( $checks as $check ) {
			echo '<div class="ecdv2-health-item ' . ( $check['done'] ? 'is-done' : 'is-todo' ) . '">';
			echo '<span class="dashicons ' . ( $check['done'] ? 'dashicons-yes-alt' : 'dashicons-marker' ) . '"></span>';
			if ( $check['done'] ) {
				echo esc_html( $check['label'] );
			} else {
				echo '<button type="button" onclick="ecdv2.go_tab( \'' . esc_attr( $check['tab'] ) . '\' );">' . esc_html( $check['label'] ) . '</button>';
			}
			echo '</div>';
		}
		echo '</div></div>';
	}
}

/*
 * NOTE: The Activity-tab and review-moderation AJAX endpoints
 * ( ec_admin_ajax_ecdv2_activity, ec_admin_ajax_ecdv2_review_status ) live in
 * wp_easycart_admin_products.php with the other always-registered product
 * endpoints. Details classes like this one load only while rendering the
 * editor page, so AJAX handlers declared here would never register during an
 * admin-ajax.php request. Deploy this file together with
 * wp_easycart_admin_products.php — the handlers were relocated, not removed.
 */