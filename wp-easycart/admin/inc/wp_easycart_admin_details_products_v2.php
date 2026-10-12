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

include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_product_reviews_card.php' );
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_product_types.php' );

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
	 * When false, print_fields() leaves out hidden fields too: a section's second card ( the Price card of the basic section,
	 * 6.0.4 ) must not print the first card's hidden inputs again under the same ids.
	 *
	 * @var bool
	 */
	public $print_hidden = true;

	/**
	 * Currently rendering section key, stamped on every field for dirty tracking.
	 *
	 * @var string
	 */
	public $current_section = '';

	/**
	 * 6.0.4: a new product's hidden draft that has never been saved ( wp_easycart_product_drafts ). Its SKU field starts empty.
	 *
	 * @var bool
	 */
	public $is_draft = false;

	/**
	 * 6.0.4: the tab group being printed ( tabset_open() ).
	 *
	 * @var string
	 */
	private $tabset_id = '';

	/**
	 * 6.0.4: whether the tab group's first pane is still to come.
	 *
	 * @var bool
	 */
	private $tabset_first = true;

	/**
	 * 6.0.4: labels for fields printed somewhere their list label does not fit ( the summary column ): name => label.
	 *
	 * @var array
	 */
	public $label_overrides = array();

	/**
	 * 6.0.4: this product's review counts ( review_counts_v2() ), worked out once for the Reviews heading and its card.
	 *
	 * @var array|null
	 */
	private $review_counts = null;

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
			if ( $this->id && class_exists( 'wp_easycart_product_drafts' ) && wp_easycart_product_drafts::is_draft( $this->product ) ) {
				$this->is_draft = true;
				$this->product->model_number = ''; /* the draft's mark is no SKU: the merchant gives one ( or the title does ) */
				$this->product->activate_in_store = 1; /* Active once saved, unless switched off first */
			}
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
		$this->print_hidden = ! isset( $args['hidden'] ) || false !== $args['hidden'];
		$lock = $this->section_lock( $section, $this->print_only, $this->print_except ); /* 6.0.2: what the product's managing service owns in this card */
		/* 6.0.4: a card inside a tab group ( pane, see tabset_open() ) keeps its title for the search and screen readers; the tab
		   names it on screen. So does a card whose section heading already says it ( 'heading' => false: Basics' name and SKU, the
		   parts of the Shipping box, the summary column's parts ). */
		$pane    = ! empty( $args['pane'] );
		$classes = 'ecdv2-card' . ( $pane ? ' ecdv2-card-pane' : '' ) . ( ( isset( $args['heading'] ) && false === $args['heading'] ) ? ' ecdv2-card-headless' : '' ) . ( ! empty( $args['class'] ) ? ' ' . $args['class'] : '' ) . ( $lock['fields'] ? ' is-managed' : '' );
		echo '<div class="' . esc_attr( $classes ) . '"' . ( ! empty( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '"' : '' ) . ' data-ecdv2-section="' . esc_attr( $section ) . '"' . ( $lock['ids'] ? ' data-ecdv2-locked-ids="' . esc_attr( implode( ',', $lock['ids'] ) ) . '"' : '' ) . '>';
		echo '<div class="ecdv2-card-saving"></div>';
		echo '<div class="ecdv2-card-header">';
		echo '<h3 class="ecdv2-card-title">' . esc_html( $title ) . '</h3>';
		if ( '' !== $hint ) {
			echo '<span class="ecdv2-card-hint">' . esc_html( $hint ) . '</span>';
		}
		/* 6.0.4: no Help link on every card ( each went to the same guide ); the header's menu has Documentation. */
		echo '</div><div class="ecdv2-card-body">';
		if ( $lock['fields'] ) {
			$this->print_lock( $lock );
		}
	}

	/**
	 * The inputs of each save section that a managed product's fields cover ( wp_easycart_product_lock ). true = the
	 * whole card.
	 *
	 * @since 6.0.2
	 * @return array section => field => input ids | true
	 */
	public function lock_map() {
		return apply_filters(
			'wp_easycart_admin_product_details_v2_lock_map',
			array(
				'basic'      => array(
					'title'       => array( 'title' ),
					'description' => array( 'description' ),
					'price'       => array( 'price' ),
					'list_price'  => array( 'list_price' ),
					'sku'         => array( 'model_number' ),
				),
				'quantities' => array( 'stock' => array( 'stock_quantity_type', 'stock_quantity' ) ),
				'packaging'  => array(
					'weight'     => array( 'weight' ),
					'dimensions' => array( 'width', 'height', 'length' ),
				),
				'shipping'   => array( 'shipping' => true ),
				'customs'    => array( 'shipping' => true ),
				'categories' => array( 'categories' => true ),
				/* The free edition's own Options and Images cards; WP EasyCart PRO covers its panels itself. */
				'options'    => array( 'options' => true ),
				'images'     => array( 'images' => true ),
				/* 6.0.3: a plan group owns the billing of the products it makes; payments, emails, prorating and the membership page stay editable. */
				'subscription' => array( 'subscription' => array( 'is_subscription_item', 'subscription_bill_length', 'subscription_bill_period', 'trial_period_days', 'subscription_signup_fee', 'subscription_plan_id' ) ),
			)
		);
	}

	/**
	 * What a managed product's service owns in one save section.
	 *
	 * @since 6.0.2
	 * @param string      $section Section key.
	 * @param array|false $only    6.0.4: the card prints only these inputs ( section_open()'s only ), so only the locks on them count.
	 * @param array       $except  6.0.4: the card leaves these inputs out, so locks on nothing but them do not count.
	 * @return array fields ( locked keys in the section ), ids ( inputs to disable ), full ( every input of the card ).
	 */
	public function section_lock( $section, $only = false, $except = array() ) {
		$out = array(
			'fields' => array(),
			'ids'    => array(),
			'full'   => false,
		);
		$map = $this->lock_map();
		if ( ! $this->id || ! isset( $map[ $section ] ) || ! class_exists( 'wp_easycart_product_lock' ) ) {
			return $out;
		}
		$locked = wp_easycart_product_lock::locked_fields( $this->product );
		if ( ! $locked ) {
			return $out;
		}
		$whole = true;
		foreach ( $map[ $section ] as $field => $ids ) {
			/* 6.0.4: a card that prints part of a section ( the Price card ) names only what it shows. */
			if ( true !== $ids && ( ( false !== $only && ! array_intersect( (array) $ids, (array) $only ) ) || ( $except && ! array_diff( (array) $ids, (array) $except ) ) ) ) {
				continue;
			}
			if ( ! in_array( $field, $locked, true ) ) {
				$whole = false;
				continue;
			}
			$out['fields'][] = $field;
			if ( true === $ids ) {
				continue;
			}
			$whole      = false;
			$out['ids'] = array_merge( $out['ids'], (array) $ids );
		}
		/* A card whose every input is locked gets the cover ( packaging with weight and dimensions both locked ). */
		$out['full'] = $out['fields'] && ( $whole || ( 'packaging' === $section && 2 === count( $out['fields'] ) ) );
		if ( $out['full'] ) {
			$out['ids'] = array();
		}
		return $out;
	}

	/**
	 * The cover over a locked card, or the banner above the locked inputs of a partly locked one.
	 *
	 * @since 6.0.2
	 * @param array $lock From section_lock().
	 */
	public function print_lock( $lock ) {
		if ( $lock['full'] ) {
			wp_easycart_product_lock::print_cover( $this->product );
			return;
		}
		$by = wp_easycart_product_lock::managed_by( $this->product );
		wp_easycart_product_lock::print_cover(
			$this->product,
			array(
				'inline' => true,
				/* translators: 1: the service that manages the product ( e.g. Printful ), 2: what it owns ( e.g. title, price ). */
				'title'  => sprintf( __( 'Managed by %1$s: %2$s', 'wp-easycart' ), wp_easycart_product_lock::label( $by ), wp_easycart_product_lock::field_names( $lock['fields'] ) ),
				/* translators: %s: the service that manages the product ( e.g. Printful ). */
				'text'   => sprintf( __( 'Change them in %s and they arrive here on the next sync. Everything else on this card is yours to edit.', 'wp-easycart' ), wp_easycart_product_lock::label( $by ) ),
			)
		);
	}

	public function section_close() {
		echo '</div></div>';
		$this->current_section = '';
		$this->print_only = false;
		$this->print_except = array();
		$this->print_hidden = true;
	}

	/**
	 * 6.0.4: cards that do the same kind of job share one card with tabs ( the product page's text; the notes shown after a
	 * purchase ), so they take one card's height. Each pane holds an ordinary section card ( section_open() with 'pane' ), so
	 * saving, unsaved marks, locks and the search work as before; products-details-v2.js switches the panes ( bind_tabsets() )
	 * and opens the one holding a field it takes the merchant to.
	 *
	 * @since 6.0.4
	 * @param string $id    Key of the group ( [a-z_] ).
	 * @param string $title The card's title.
	 * @param string $hint  What the card is for.
	 * @param array  $tabs  key => array( label, chip ( escaped HTML after the label ), empty ( bool: show "Empty" ), check ( the
	 *                      field whose text decides "Empty" as it changes ) ), in order.
	 */
	public function tabset_open( $id, $title, $hint, $tabs, $args = array() ) {
		$this->tabset_id    = $id;
		$this->tabset_first = true;
		$base = 'ecdv2_tabset_' . $id;
		/* 6.0.4: 'heading' => false keeps the title for screen readers only ( the product page's text sits under Basics ). */
		$headless = isset( $args['heading'] ) && false === $args['heading'];
		echo '<div class="ecdv2-tabset' . ( $headless ? ' ecdv2-tabset-headless' : '' ) . '" id="' . esc_attr( $base ) . '" data-ecdv2-tabset="' . esc_attr( $id ) . '">';
		echo '<div class="ecdv2-tabset-head"><div class="ecdv2-tabset-heading">';
		echo '<h3 class="ecdv2-tabset-title" id="' . esc_attr( $base . '_title' ) . '">' . esc_html( $title ) . '</h3>';
		if ( '' !== $hint ) {
			echo '<span class="ecdv2-tabset-hint">' . esc_html( $hint ) . '</span>';
		}
		echo '</div>';
		echo '<div class="ecdv2-tabset-tabs" role="tablist" aria-labelledby="' . esc_attr( $base . '_title' ) . '">';
		$first = true;
		foreach ( $tabs as $key => $tab ) {
			$pane_id = $base . '_' . $key;
			echo '<button type="button" role="tab" class="ecdv2-tabset-tab' . ( $first ? ' is-active' : '' ) . '" id="' . esc_attr( $pane_id . '_tab' ) . '" aria-controls="' . esc_attr( $pane_id ) . '" aria-selected="' . ( $first ? 'true' : 'false' ) . '" tabindex="' . ( $first ? '0' : '-1' ) . '" data-ecdv2-pane="' . esc_attr( $key ) . '"' . ( ! empty( $tab['check'] ) ? ' data-ecdv2-pane-check="' . esc_attr( $tab['check'] ) . '"' : '' ) . '>';
			echo '<span class="ecdv2-tabset-label">' . esc_html( $tab['label'] ) . '</span>';
			if ( ! empty( $tab['chip'] ) ) {
				echo $tab['chip']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the caller's chip is escaped markup ( wp_easycart_admin_edition::badge() ).
			}
			if ( isset( $tab['empty'] ) ) {
				echo '<span class="ecdv2-tabset-empty"' . ( $tab['empty'] ? '' : ' hidden' ) . '>' . esc_html__( 'Empty', 'wp-easycart' ) . '</span>';
			}
			echo '<span class="ecdv2-tabset-dot" title="' . esc_attr__( 'Unsaved changes', 'wp-easycart' ) . '"></span>';
			echo '</button>';
			$first = false;
		}
		echo '</div></div>';
	}

	/**
	 * 6.0.4: a heading over cards of one job ( the Shipping box's Package, Charges and Where it goes ). The cards inside are
	 * header-less ( section_open( …, array( 'heading' => false ) ) ), so each still saves as its own section.
	 *
	 * @since 6.0.4
	 * @param string $title The group's title.
	 * @param string $hint  What it is for.
	 * @param array  $args  id, class.
	 */
	public function group_open( $title, $hint = '', $args = array() ) {
		echo '<div class="ecdv2-group' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '"' . ( ! empty( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '"' : '' ) . '>';
		echo '<div class="ecdv2-group-head"><h4 class="ecdv2-group-title">' . esc_html( $title ) . '</h4>';
		if ( '' !== $hint ) {
			echo '<span class="ecdv2-group-hint">' . esc_html( $hint ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * @since 6.0.4
	 */
	public function group_close() {
		echo '</div>';
	}

	/**
	 * Opens one pane of the tab group ( the first is shown ).
	 *
	 * @since 6.0.4
	 * @param string $key The tab's key.
	 */
	public function pane_open( $key ) {
		$pane_id = 'ecdv2_tabset_' . $this->tabset_id . '_' . $key;
		echo '<div class="ecdv2-tabset-pane' . ( $this->tabset_first ? ' is-active' : '' ) . '" role="tabpanel" id="' . esc_attr( $pane_id ) . '" aria-labelledby="' . esc_attr( $pane_id . '_tab' ) . '" data-ecdv2-pane="' . esc_attr( $key ) . '"' . ( $this->tabset_first ? '' : ' hidden' ) . '>';
		$this->tabset_first = false;
	}

	/**
	 * @since 6.0.4
	 */
	public function pane_close() {
		echo '</div>';
	}

	/**
	 * @since 6.0.4
	 */
	public function tabset_close() {
		echo '</div>';
		$this->tabset_id = '';
	}

	/**
	 * Free-edition Options tab: two option-set slots ( live variation count and
	 * choice preview ), locked slots 3–5, a warning when the product still carries
	 * PRO option data, and a PRO preview that shows the variant manager and modifiers.
	 * Saves through the existing 'options' section endpoint.
	 */
	public function print_free_options_v2() {
		global $wpdb;
		$p = $this->product;
		$free_limit = (int) apply_filters( 'wp_easycart_admin_free_option_set_limit', 2 );
		$pro_status = class_exists( 'wp_easycart_admin_pro_gate' ) ? wp_easycart_admin_pro_gate::pro_status() : array( 'installed' => false, 'active' => false );
		$pro_inactive = ( $pro_status['installed'] && ! $pro_status['active'] );

		$assigned = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$assigned[ $i ] = isset( $p->{ 'option_id_' . $i } ) ? (int) $p->{ 'option_id_' . $i } : 0;
		}
		/*
		 * Only the sets this product already uses are loaded; the slot pickers search
		 * the rest through ecv2_option_set_search ( stores can hold thousands of sets ).
		 *
		 * @since 6.0.0
		 */
		$sets   = array();
		$by_id  = array();
		$counts = array();
		$assigned_ids = array_values( array_filter( array_map( 'intval', $assigned ) ) );
		if ( $assigned_ids ) {
			$sets = $wpdb->get_results( 'SELECT o.option_id, o.option_name, o.option_label, o.option_type, ( SELECT COUNT(*) FROM ec_optionitem i WHERE i.option_id = o.option_id ) AS item_count FROM ec_option o WHERE o.option_id IN ( ' . implode( ',', $assigned_ids ) . ' ) ORDER BY o.option_name ASC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IN list is built from array_map( 'intval', $assigned ).
		}
		foreach ( $sets as $s ) {
			$by_id[ (int) $s->option_id ] = $s;
			$counts[ (int) $s->option_id ] = (int) $s->item_count;
		}
		$option_search_nonce = wp_create_nonce( 'wp-easycart-ecv2-option-set-search' );
		$item_names = array();
		$used_ids = array();
		for ( $i = 1; $i <= $free_limit; $i++ ) {
			if ( $assigned[ $i ] ) {
				$used_ids[] = $assigned[ $i ];
			}
		}
		if ( $used_ids ) {
			$rows = $wpdb->get_results( 'SELECT option_id, optionitem_name, optionitem_price FROM ec_optionitem WHERE option_id IN ( ' . implode( ',', array_map( 'intval', $used_ids ) ) . ' ) ORDER BY optionitem_order ASC, optionitem_id ASC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IN list is built from array_map( 'intval', $used_ids ).
			foreach ( $rows as $r ) {
				$item_names[ (int) $r->option_id ][] = array( 'name' => $r->optionitem_name, 'price' => (float) $r->optionitem_price );
			}
		}

		// PRO data still on this product.
		$extra_sets = 0;
		for ( $i = $free_limit + 1; $i <= 5; $i++ ) {
			if ( $assigned[ $i ] ) {
				$extra_sets++;
			}
		}
		$modifier_count  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_option_to_product WHERE product_id = %d', (int) $p->product_id ) );
		$variant_rows    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_optionitemquantity WHERE product_id = %d', (int) $p->product_id ) );
		$tracks_variants = ( ! empty( $p->use_optionitem_quantity_tracking ) && $variant_rows > 0 );
		$has_legacy = ( $extra_sets > 0 || $modifier_count > 0 || $tracks_variants );

		$this->section_open( 'options', __( 'Option sets', 'wp-easycart' ), sprintf( __( 'Up to %d sets in the free edition. Each combination of choices becomes a variation.', 'wp-easycart' ), $free_limit ) );

		if ( $has_legacy ) {
			$parts = array();
			if ( $extra_sets > 0 ) {
				$parts[] = sprintf( _n( '%d extra option set', '%d extra option sets', $extra_sets, 'wp-easycart' ), $extra_sets );
			}
			if ( $modifier_count > 0 ) {
				$parts[] = sprintf( _n( '%d modifier', '%d modifiers', $modifier_count, 'wp-easycart' ), $modifier_count );
			}
			if ( $tracks_variants ) {
				$parts[] = sprintf( __( 'stock tracked on %d variations', 'wp-easycart' ), $variant_rows );
			}
			echo '<div class="ecdv2-media-notice is-warning" id="ecdv2_options_legacy_notice" data-extra="' . esc_attr( $extra_sets ) . '" data-modifiers="' . esc_attr( $modifier_count ) . '" data-variants="' . ( $tracks_variants ? esc_attr( $variant_rows ) : '0' ) . '">';
			echo '<span class="dashicons dashicons-warning"></span>';
			echo '<span><strong>' . esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'This product has %s options:', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ) . ' ' . esc_html( implode( ', ', $parts ) ) . '.</strong> ';
			echo esc_html( sprintf( __( 'Your storefront is still using them. Saving this section keeps the first %d option sets, removes the modifiers and the extra sets, and turns variation stock tracking off. Nothing changes until you save.', 'wp-easycart' ), $free_limit ) ) . '</span>';
			if ( $pro_inactive ) {
				echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( wp_easycart_admin()->get_pro_activation_link() ) . '">' . esc_html( sprintf( /* translators: %s: plugin name, WP EasyCart PRO. */ __( 'Activate %s to keep them', 'wp-easycart' ), wp_easycart_admin_edition::PLUGIN_NAME ) ) . '</a>';
			} else {
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecdv2_upsell( { context: \'products\', feature: \'variants\' } ); return false;">' . esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Keep them with %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ) . '</button>';
			}
			echo '</div>';
		}

		$sec = ' data-ecdv2-sec="' . esc_attr( $this->current_section ) . '"';
		echo '<input type="hidden" name="use_advanced_optionset" id="use_advanced_optionset" value="0"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.

		echo '<div class="ecdv2-opt-slots" id="ecdv2_opt_slots" data-counts="' . esc_attr( wp_json_encode( $counts ) ) . '" data-limit="' . esc_attr( $free_limit ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$name   = 'option' . $i;
			$cur    = $assigned[ $i ];
			$locked = ( $i > $free_limit );
			if ( $locked ) {
				$legacy = ( $cur && isset( $by_id[ $cur ] ) ) ? $by_id[ $cur ]->option_name : ( $cur ? sprintf( __( 'Option set #%d', 'wp-easycart' ), $cur ) : '' );
				echo '<div class="ecdv2-opt-slot is-locked' . ( $cur ? ' has-value' : '' ) . '" onclick="ecdv2_upsell( { context: \'products\', feature: \'variants\' } ); return false;" role="button" tabindex="0">';
				echo '<span class="ecdv2-opt-slot-n">' . (int) $i . '</span>';
				echo '<span class="ecdv2-opt-slot-name">' . esc_html( '' !== $legacy ? $legacy : sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( '%s slot', 'wp-easycart' ), wp_easycart_admin_edition::badge( 'pro' ) ) ) . '</span>';
				echo '<span class="ecdv2-opt-slot-state">' . esc_html( $cur ? __( 'Removed on save', 'wp-easycart' ) : sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Up to 5 sets with %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ) . '</span>';
				echo '<span class="ecdv2-media-pill">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $cur ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
				echo '</div>';
				continue;
			}
			echo '<div class="ecdv2-opt-slot' . ( $cur ? ' has-value' : '' ) . '" data-slot="' . (int) $i . '">';
			echo '<span class="ecdv2-opt-slot-n">' . (int) $i . '</span>';
			echo '<div class="ecdv2-opt-slot-main">';
			/* Search-as-you-type picker: the select carries only its current value ( ecv2_option_set_search, 30 per page ). */
			echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="ecdv2-opt-select ecdv2-select2-ajax" data-ecdv2-ajax="option_sets" data-ecdv2-nonce="' . esc_attr( $option_search_nonce ) . '"' . $sec . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
			echo '<option value="0">' . esc_html( 1 === $i ? __( 'Choose an option set…', 'wp-easycart' ) : __( 'Add a second option set…', 'wp-easycart' ) ) . '</option>';
			if ( $cur && isset( $by_id[ $cur ] ) ) {
				echo '<option value="' . esc_attr( $cur ) . '" selected>' . esc_html( $by_id[ $cur ]->option_name ) . ' (' . (int) $by_id[ $cur ]->item_count . ')</option>';
			}
			echo '</select>';
			echo '<div class="ecdv2-opt-chips" id="' . esc_attr( $name ) . '_chips">';
			if ( $cur && isset( $item_names[ $cur ] ) ) {
				foreach ( $item_names[ $cur ] as $it ) {
					echo '<span class="ecdv2-opt-chip">' . esc_html( $it['name'] ) . ( $it['price'] > 0 ? ' <em>+' . esc_html( $GLOBALS['currency']->get_currency_display( $it['price'] ) ) . '</em>' : '' ) . '</span>';
				}
			}
			echo '</div>';
			echo '</div>';
			echo '<button type="button" class="ecdv2-opt-slot-rm" onclick="ecdv2_opt_clear( \'' . esc_attr( $name ) . '\' ); return false;" aria-label="' . esc_attr__( 'Remove from this product', 'wp-easycart' ) . '"' . ( $cur ? '' : ' hidden' ) . '>×</button>';
			echo '</div>';
		}
		echo '</div>';

		echo '<div class="ecdv2-opt-foot">';
		echo '<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="if ( typeof ecosv2_open === \'function\' ) { ecosv2_open( { origin: \'standalone\' } ); } return false;">+ ' . esc_html__( 'Create a new option set', 'wp-easycart' ) . '</button>';
		echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' . esc_url( self_admin_url( 'admin.php?page=wp-easycart-products&subpage=option' ) ) . '" target="_blank">' . esc_html__( 'Manage all option sets', 'wp-easycart' ) . '</a>';
		echo '<span class="ecdv2-opt-variations" id="ecdv2_opt_variations"></span>';
		echo '</div>';

		$this->section_close();

		// ---- PRO preview: variant manager + modifiers, built from the merchant own sets ----
		$names_of = function( $list ) { $o = array(); foreach ( $list as $x ) { $o[] = $x['name']; } return $o; };
		$sample_a = ( $assigned[1] && isset( $item_names[ $assigned[1] ] ) ) ? array_slice( $names_of( $item_names[ $assigned[1] ] ), 0, 2 ) : array( __( 'Red', 'wp-easycart' ), __( 'Blue', 'wp-easycart' ) );
		$sample_b = ( $assigned[2] && isset( $item_names[ $assigned[2] ] ) ) ? array_slice( $names_of( $item_names[ $assigned[2] ] ), 0, 2 ) : array( __( 'Small', 'wp-easycart' ), __( 'Large', 'wp-easycart' ) );
		$sku_base = ! empty( $p->model_number ) ? $p->model_number : 'sku';
		$price    = isset( $p->price ) ? (float) $p->price : 24.99;
		/* 6.0.4: the variant table has weight too ( WP EasyCart PRO's Phase 3 table ). */
		$weight_unit = ( class_exists( 'wp_easycart_packages' ) && method_exists( 'wp_easycart_packages', 'units' ) ) ? wp_easycart_packages::units()['weight'] : 'lb';
		$weight_text = ( ( isset( $p->weight ) && (float) $p->weight > 0 ) ? rtrim( rtrim( number_format( (float) $p->weight, 2, '.', '' ), '0' ), '.' ) : '0.5' ) . ' ' . $weight_unit;

		echo '<div class="ecdv2-media-upsell" data-upsell-context="products">';
		echo '<div class="ecdv2-media-upsell-head">';
		echo '<span class="ecdv2-media-pill">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
		echo '<div class="ecdv2-media-upsell-copy"><strong>' . esc_html__( 'Variant manager, per-variation stock and modifiers', 'wp-easycart' ) . '</strong>';
		echo '<span>' . esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'What this tab looks like with %s, using your own option sets. Click anything to see how it works.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ) . '</span></div>';
		if ( $pro_inactive ) {
			echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( wp_easycart_admin()->get_pro_activation_link() ) . '">' . esc_html( sprintf( /* translators: %s: plugin name, WP EasyCart PRO. */ __( 'Activate %s', 'wp-easycart' ), wp_easycart_admin_edition::PLUGIN_NAME ) ) . '</a>';
		} else {
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="ecdv2_upsell( { context: \'products\', feature: \'variants\' } ); return false;">' . esc_html__( "See what's included", 'wp-easycart' ) . '</button>';
		}
		echo '</div>';

		echo '<div class="ecdv2-media-mock ecdv2-opt-mock" onclick="ecdv2_upsell( { context: \'products\', feature: \'variants\' } ); return false;" role="button" tabindex="0">';
		echo '<div class="ecdv2-media-mock-tag"><span class="dashicons dashicons-visibility"></span> ' . esc_html__( 'Preview — variations from your option sets', 'wp-easycart' ) . '</div>';
		echo '<table class="ecdv2-opt-mock-table"><thead><tr><th>' . esc_html__( 'Variation', 'wp-easycart' ) . '</th><th>' . esc_html__( 'SKU', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Price', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Stock', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Weight', 'wp-easycart' ) . '</th></tr></thead><tbody>';
		$n = 0;
		$stock_samples = array( 12, 3, 0, 26 );
		foreach ( $sample_a as $a ) {
			foreach ( $sample_b as $b ) {
				$stock = $stock_samples[ $n % 4 ];
				$sku   = strtolower( preg_replace( '/[^A-Za-z0-9]+/', '-', $sku_base . '-' . substr( $a, 0, 3 ) . '-' . substr( $b, 0, 3 ) ) );
				echo '<tr><td><strong>' . esc_html( $a . ' / ' . $b ) . '</strong></td><td><code>' . esc_html( $sku ) . '</code></td><td>' . esc_html( $GLOBALS['currency']->get_currency_display( $price + ( 1 === $n % 2 ? 2 : 0 ) ) ) . '</td>';
				/* translators: %d: units in stock. */
				echo '<td><span class="ecdv2-opt-mock-stock' . ( 0 === $stock ? ' is-out' : ( $stock < 5 ? ' is-low' : '' ) ) . '">' . esc_html( 0 === $stock ? __( 'Out of stock', 'wp-easycart' ) : sprintf( __( '%d in stock', 'wp-easycart' ), $stock ) ) . '</span></td><td>' . esc_html( $weight_text ) . '</td></tr>';
				$n++;
			}
		}
		echo '</tbody></table>';
		echo '<div class="ecdv2-opt-mock-mods">';
		echo '<span class="ecdv2-media-mock-sets-label">' . esc_html__( 'Modifiers', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-media-mock-chip">' . esc_html__( 'Engraving text', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-media-mock-chip is-on">' . esc_html__( 'Gift wrap', 'wp-easycart' ) . ' <em>+' . esc_html( $GLOBALS['currency']->get_currency_display( 3 ) ) . '</em></span>';
		echo '<span class="ecdv2-media-mock-chip">' . esc_html__( 'Upload artwork', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-media-mock-chip">' . esc_html__( 'Pickup date', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-media-mock-drag">' . esc_html__( 'Show “Upload artwork” only when Gift wrap is checked', 'wp-easycart' ) . '</span>';
		echo '</div>';
		echo '<div class="ecdv2-media-mock-veil"><span class="ecv2-btn ecv2-btn-primary"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Unlock variants & modifiers', 'wp-easycart' ) . '</span></div>';
		echo '</div>';

		echo '<ul class="ecdv2-media-upsell-list">';
		$bullets = array(
			array( 'dashicons-editor-table', __( 'Variant manager', 'wp-easycart' ), __( 'Every combination in one table with its own SKU, price, stock, weight and cost. Tick rows to set many at once.', 'wp-easycart' ) ),
			array( 'dashicons-archive', __( 'Stock per variation', 'wp-easycart' ), __( 'Sell out of Red / Small without hiding Red / Large. Low-stock and out-of-stock per combination.', 'wp-easycart' ) ),
			array( 'dashicons-edit', __( 'Modifiers', 'wp-easycart' ), __( 'Text, numbers, dates, file uploads, checkbox and radio add-ons — each with its own price.', 'wp-easycart' ) ),
			array( 'dashicons-randomize', __( 'Conditional logic', 'wp-easycart' ), __( 'Show or hide a modifier based on what the shopper picked before it.', 'wp-easycart' ) ),
			array( 'dashicons-plus-alt', __( 'Up to 5 option sets', 'wp-easycart' ), __( 'Size × color × material × finish × length, if you need it.', 'wp-easycart' ) ),
			array( 'dashicons-upload', __( 'Import & export variations', 'wp-easycart' ), __( 'Bulk-edit SKUs, prices and stock in a spreadsheet and import them back.', 'wp-easycart' ) ),
		);
		foreach ( $bullets as $b ) {
			echo '<li onclick="ecdv2_upsell( { context: \'products\', feature: \'variants\' } );"><span class="dashicons ' . esc_attr( $b[0] ) . '"></span><span><strong>' . esc_html( $b[1] ) . '</strong><em>' . esc_html( $b[2] ) . '</em></span></li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Resolve a stored image value ( legacy file name or absolute URL ) to a URL.
	 */
	protected function image_value_url( $value, $slot ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		if ( 0 === strpos( $value, 'http://' ) || 0 === strpos( $value, 'https://' ) ) {
			return $value;
		}
		return plugins_url( '/wp-easycart-data/products/pics' . (int) $slot . '/' . $value, EC_PLUGIN_DATA_DIRECTORY );
	}

	/**
	 * Free-edition Media tab: five image slots with real previews, then a PRO
	 * gallery preview that shows exactly what the merchant is missing.
	 * Values are saved through the existing image1..image5 section save.
	 */
	public function print_free_media_v2() {
		$p = $this->product;
		$free_limit = (int) apply_filters( 'wp_easycart_admin_free_image_slot_limit', 2 );
		$uses_option_images = ! empty( $p->use_optionitem_images );
		$pro_status = class_exists( 'wp_easycart_admin_pro_gate' ) ? wp_easycart_admin_pro_gate::pro_status() : array( 'installed' => false, 'active' => false );
		$pro_inactive = ( $pro_status['installed'] && ! $pro_status['active'] );

		// PRO data still on this product ( a former PRO install ): images past the free limit and/or per-option sets.
		$extra_images = 0;
		for ( $i = $free_limit + 1; $i <= 5; $i++ ) {
			if ( ! empty( $p->{ 'image' . $i } ) ) {
				$extra_images++;
			}
		}
		$gallery_count = 0;
		if ( isset( $p->product_images ) && '' !== trim( (string) $p->product_images ) ) {
			$gallery_count = count( array_filter( array_map( 'trim', explode( ',', (string) $p->product_images ) ) ) );
		}
		$has_legacy = ( $extra_images > 0 || $uses_option_images || $gallery_count > 0 );

		$this->section_open( 'images', __( 'Product images', 'wp-easycart' ), sprintf( __( 'Up to %d images in the free edition. The first is your main listing image.', 'wp-easycart' ), $free_limit ) );

		if ( $has_legacy ) {
			$parts = array();
			if ( $gallery_count > 0 ) {
				$parts[] = sprintf( _n( 'a %d-image gallery', 'a %d-image gallery', $gallery_count, 'wp-easycart' ), $gallery_count );
			}
			if ( $extra_images > 0 ) {
				$parts[] = sprintf( _n( '%d extra image', '%d extra images', $extra_images, 'wp-easycart' ), $extra_images );
			}
			if ( $uses_option_images ) {
				$parts[] = __( 'per-option image sets', 'wp-easycart' );
			}
			echo '<div class="ecdv2-media-notice is-warning" id="ecdv2_media_legacy_notice" data-extra="' . esc_attr( $extra_images ) . '" data-sets="' . ( $uses_option_images ? '1' : '0' ) . '" data-gallery="' . esc_attr( $gallery_count ) . '">';
			echo '<span class="dashicons dashicons-warning"></span>';
			echo '<span><strong>' . esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'This product has %s media:', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ) . ' ' . esc_html( implode( __( ' and ', 'wp-easycart' ), $parts ) ) . '.</strong> ';
			echo esc_html( sprintf( __( 'Your storefront is still showing it. Saving this section keeps the first %d images, removes the rest and switches the product page back to the standard image display. Nothing changes until you save.', 'wp-easycart' ), $free_limit ) ) . '</span>';
			if ( $pro_inactive ) {
				echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( wp_easycart_admin()->get_pro_activation_link() ) . '">' . esc_html( sprintf( /* translators: %s: plugin name, WP EasyCart PRO. */ __( 'Activate %s to keep it', 'wp-easycart' ), wp_easycart_admin_edition::PLUGIN_NAME ) ) . '</a>';
			} else {
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecdv2_upsell( { context: \'products\', feature: \'images\' } ); return false;">' . esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Keep it with %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ) . '</button>';
			}
			echo '</div>';
		}

		$sec = ' data-ecdv2-sec="' . esc_attr( $this->current_section ) . '"';
		// use_optionitem_images is posted as 0 from the free panel: saving intentionally returns the product to plain images.
		echo '<input type="hidden" name="use_optionitem_images" id="use_optionitem_images" value="0"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
		echo '<div class="ecdv2-media-slots">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$name   = 'image' . $i;
			$value  = isset( $p->{ $name } ) ? (string) $p->{ $name } : '';
			$url    = $this->image_value_url( $value, $i );
			$locked = ( $i > $free_limit );
			echo '<div class="ecdv2-media-slot' . ( '' !== $url ? ' has-image' : '' ) . ( $locked ? ' is-locked' : '' ) . '" data-slot="' . esc_attr( $i ) . '">';
			if ( $locked ) {
				// Locked slot: shows any legacy image dimmed, opens the upsell, never edits.
				echo '<div class="ecdv2-media-slot-thumb" id="' . esc_attr( $name ) . '_preview"' . ( '' !== $url ? ' style="background-image:url(' . esc_url( $url ) . ');"' : '' ) . ' onclick="ecdv2_upsell( { context: \'products\', feature: \'images\' } ); return false;" role="button" tabindex="0" aria-label="' . esc_attr( sprintf( /* translators: 1: image slot number, 2: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Image %1$d — %2$s', 'wp-easycart' ), $i, wp_easycart_admin_edition::badge( 'pro' ) ) ) . '">';
				echo '<span class="ecdv2-media-slot-lock"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5 7V5a3 3 0 016 0v2"/></svg></span>';
				echo '<span class="ecdv2-media-slot-tag is-pro">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
				echo '</div>';
				// Value is echoed unchanged so a save without PRO data is a no-op here; the server clears it regardless.
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
				echo '<div class="ecdv2-media-slot-actions"><span class="ecdv2-media-slot-locked-label">' . esc_html( '' !== $url ? __( 'Removed on save', 'wp-easycart' ) : sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( '%s slot', 'wp-easycart' ), wp_easycart_admin_edition::badge( 'pro' ) ) ) . '</span></div>';
				echo '</div>';
				continue;
			}
			echo '<div class="ecdv2-media-slot-thumb" id="' . esc_attr( $name ) . '_preview"' . ( '' !== $url ? ' style="background-image:url(' . esc_url( $url ) . ');"' : '' ) . ' onclick="ecdv2.media_pick( \'' . esc_attr( $name ) . '\', false, \'image\' ); return false;" role="button" tabindex="0" aria-label="' . esc_attr( sprintf( __( 'Choose image %d', 'wp-easycart' ), $i ) ) . '">';
			echo '<svg class="ecdv2-media-slot-ph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/></svg>';
			if ( 1 === $i ) {
				echo '<span class="ecdv2-media-slot-tag">' . esc_html__( 'Main', 'wp-easycart' ) . '</span>';
			}
			echo '</div>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
			echo '<div class="ecdv2-media-slot-actions">';
			echo '<button type="button" class="ecdv2-media-link" onclick="ecdv2.media_pick( \'' . esc_attr( $name ) . '\', false, \'image\' ); return false;">' . esc_html( '' !== $url ? __( 'Replace', 'wp-easycart' ) : __( 'Choose', 'wp-easycart' ) ) . '</button>';
			echo '<button type="button" class="ecdv2-media-link ecdv2-media-link-remove" onclick="ecdv2.media_clear( \'' . esc_attr( $name ) . '\' ); return false;"' . ( '' === $url ? ' hidden' : '' ) . '>' . esc_html__( 'Remove', 'wp-easycart' ) . '</button>';
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
		echo '<div class="ecdv2-media-hint">' . esc_html( sprintf( /* translators: 1: first locked image slot, 2: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Images are saved with this section. The first slot is used in your product list and as the storefront thumbnail. Slots %1$d–5 need %2$s.', 'wp-easycart' ), $free_limit + 1, wp_easycart_admin_edition::plan_name() ) ) . '</div>';

		$this->section_close();

		// ---- PRO gallery preview ------------------------------------------
		$main_url = $this->image_value_url( isset( $p->image1 ) ? $p->image1 : '', 1 );
		echo '<div class="ecdv2-media-upsell" data-upsell-context="products">';
		echo '<div class="ecdv2-media-upsell-head">';
		echo '<span class="ecdv2-media-pill">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
		echo '<div class="ecdv2-media-upsell-copy"><strong>' . esc_html__( 'Gallery, video and per-option image sets', 'wp-easycart' ) . '</strong>';
		echo '<span>' . esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'What this product page looks like with %s. Click anything to see how it works.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ) . '</span></div>';
		if ( $pro_inactive ) {
			echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( wp_easycart_admin()->get_pro_activation_link() ) . '">' . esc_html( sprintf( /* translators: %s: plugin name, WP EasyCart PRO. */ __( 'Activate %s', 'wp-easycart' ), wp_easycart_admin_edition::PLUGIN_NAME ) ) . '</a>';
		} else {
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="ecdv2_upsell( { context: \'products\', feature: \'images\' } ); return false;">' . esc_html__( "See what's included", 'wp-easycart' ) . '</button>';
		}
		echo '</div>';

		// Mock gallery: the merchant's own main image leads, then sample tiles.
		echo '<div class="ecdv2-media-mock" onclick="ecdv2_upsell( { context: \'products\', feature: \'images\' } ); return false;" role="button" tabindex="0">';
		echo '<div class="ecdv2-media-mock-tag"><span class="dashicons dashicons-visibility"></span> ' . esc_html__( 'Preview with sample gallery', 'wp-easycart' ) . '</div>';
		echo '<div class="ecdv2-media-mock-strip">';
		echo '<div class="ecdv2-media-mock-tile is-main"' . ( '' !== $main_url ? ' style="background-image:url(' . esc_url( $main_url ) . ');"' : '' ) . '><span>' . esc_html__( 'Main', 'wp-easycart' ) . '</span></div>';
		echo '<div class="ecdv2-media-mock-tile t2"></div>';
		echo '<div class="ecdv2-media-mock-tile t3"></div>';
		echo '<div class="ecdv2-media-mock-tile is-video"><span class="ecdv2-media-mock-play">▶</span><em>' . esc_html__( 'YouTube', 'wp-easycart' ) . '</em></div>';
		echo '<div class="ecdv2-media-mock-tile t5"></div>';
		echo '<div class="ecdv2-media-mock-tile is-video is-vimeo"><span class="ecdv2-media-mock-play">▶</span><em>' . esc_html__( 'Vimeo', 'wp-easycart' ) . '</em></div>';
		echo '<div class="ecdv2-media-mock-tile is-add">+</div>';
		echo '</div>';
		echo '<div class="ecdv2-media-mock-sets">';
		echo '<span class="ecdv2-media-mock-sets-label">' . esc_html__( 'Image set for', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-media-mock-chip is-on">' . esc_html__( 'Red', 'wp-easycart' ) . '</span><span class="ecdv2-media-mock-chip">' . esc_html__( 'Blue', 'wp-easycart' ) . '</span><span class="ecdv2-media-mock-chip">' . esc_html__( 'Green', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-media-mock-drag">' . esc_html__( 'Drag to reorder · first tile is the listing image', 'wp-easycart' ) . '</span>';
		echo '</div>';
		echo '<div class="ecdv2-media-mock-veil"><span class="ecv2-btn ecv2-btn-primary"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Unlock gallery & video', 'wp-easycart' ) . '</span></div>';
		echo '</div>';

		echo '<ul class="ecdv2-media-upsell-list">';
		$bullets = array(
			array( 'dashicons-format-gallery', __( 'Unlimited images', 'wp-easycart' ), __( 'Pull straight from the Media Library or paste an image URL — no five-slot limit.', 'wp-easycart' ) ),
			array( 'dashicons-video-alt3', __( 'Video in the gallery', 'wp-easycart' ), __( 'YouTube, Vimeo or a hosted MP4 sits alongside the photos.', 'wp-easycart' ) ),
			array( 'dashicons-move', __( 'Drag to reorder', 'wp-easycart' ), __( 'The first tile becomes the listing image; changes save automatically.', 'wp-easycart' ) ),
			array( 'dashicons-color-picker', __( 'Per-option image sets', 'wp-easycart' ), __( 'A different gallery for each color, material or style the shopper picks.', 'wp-easycart' ) ),
		);
		foreach ( $bullets as $b ) {
			echo '<li onclick="ecdv2_upsell( { context: \'products\', feature: \'images\' } );"><span class="dashicons ' . esc_attr( $b[0] ) . '"></span><span><strong>' . esc_html( $b[1] ) . '</strong><em>' . esc_html( $b[2] ) . '</em></span></li>';
		}
		echo '</ul>';
		echo '</div>';
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
			'upsell'   => wp_easycart_admin_edition::badge( 'pro' ),
			'inactive' => __( 'Activate plugin', 'wp-easycart' ),
			'update'   => __( 'Update plugin', 'wp-easycart' ),
			'license'  => __( 'LICENSE', 'wp-easycart' ),
		);
		$state = $gate['state'];
		echo '<div class="ecdv2-gate" data-gate-state="' . esc_attr( $state ) . '">';
		echo '<div class="ecdv2-gate-head" onclick="ecdv2.gate_toggle(this);">';
		echo '<span class="dashicons dashicons-lock"></span>';
		echo '<span class="ecdv2-gate-label">' . esc_html( $label ) . '</span>';
		echo '<span class="ecdv2-gate-badge is-' . esc_attr( $state ) . '">' . esc_html( isset( $badge[ $state ] ) ? $badge[ $state ] : wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
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
		add_filter( 'wp_easycart_admin_product_details_pricing_fields_list', array( $this, 'v2_add_product_cost' ), 100, 2 );
		add_filter( 'wp_easycart_admin_product_details_subscription_fields_list', array( $this, 'v2_subscription_requires' ), 99 );
		add_filter( 'wp_easycart_admin_product_details_featured_products_fields_list', array( $this, 'v2_featured_tag_inactive' ), 98, 2 );
		parent::pricing_fields();
		remove_filter( 'wp_easycart_admin_product_details_pricing_fields_list', array( $this, 'v2_remove_list_price_from_pricing' ), 99 );
		remove_filter( 'wp_easycart_admin_product_details_pricing_fields_list', array( $this, 'v2_add_product_cost' ), 100 );
	}

	/**
	 * 6.0.2: what one unit costs the store, first on the Pricing card ( saved by the pricing endpoint; order lines record it
	 * as unit_cost when no variant cost is set ).
	 *
	 * @param array $fields  Pricing fields.
	 * @param mixed $product Product.
	 * @return array
	 */
	public function v2_add_product_cost( $fields, $product = false ) {
		foreach ( $fields as $field ) {
			if ( isset( $field['name'] ) && 'product_cost' === $field['name'] ) {
				return $fields;
			}
		}
		array_unshift(
			$fields,
			array(
				'name'            => 'product_cost',
				'type'            => 'currency',
				'label'           => __( 'Cost per item', 'wp-easycart' ),
				'required'        => false,
				'validation_type' => 'price',
				'visible'         => true,
				'default'         => '0.00',
				'value'           => isset( $this->product->product_cost ) ? $this->product->product_cost : 0,
				'placeholder'     => '0.00',
				'description'     => __( 'What one unit costs you ( your supplier or fulfillment partner price ). Shoppers never see it; orders record it for profit reports.', 'wp-easycart' ),
			)
		);
		return $fields;
	}

	/** Splice Previous Price directly after Price on the General tab. */
	public function v2_add_list_price_to_basic( $fields, $product = false ) {
		$list_price_field = array(
			'name'            => 'list_price',
			'type'            => 'currency',
			'label'           => __( 'Compare-at price', 'wp-easycart' ),
			'required'        => false,
			'validation_type' => 'price',
			'visible'         => true,
			'default'         => '0.00',
			'value'           => $this->product->list_price,
			'placeholder'     => '0.00',
			'description'     => __( 'Optional. When it is higher than the price, shoppers see it crossed out next to the price. Leave 0 to hide.', 'wp-easycart' ),
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
		/* Only the options actually present need checking ( the base query already flags them
		 * when the V2 editor is active; this covers the classic full-list path without a
		 * whole-table scan ). Also mark each picker as an AJAX product search. */
		$ids = array();
		foreach ( $fields as $field ) {
			if ( isset( $field['data'] ) && is_array( $field['data'] ) ) {
				foreach ( $field['data'] as $option ) {
					if ( is_object( $option ) && ! isset( $option->inactive ) ) {
						$ids[] = (int) $option->id;
					}
				}
			}
		}
		$lookup = array();
		if ( $ids ) {
			$ids = array_slice( array_unique( $ids ), 0, 500 );
			$lookup = array_flip( array_map( 'intval', $wpdb->get_col( 'SELECT product_id FROM ec_product WHERE activate_in_store = 0 AND product_id IN ( ' . implode( ',', $ids ) . ' )' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- every $ids entry is (int) cast above.
		}
		foreach ( $fields as $fi => $field ) {
			if ( isset( $field['name'] ) && 0 === strpos( $field['name'], 'featured_product_id_' ) ) {
				$fields[ $fi ]['ajax'] = 'products';
			}
			if ( ! isset( $field['data'] ) || ! is_array( $field['data'] ) ) {
				continue;
			}
			foreach ( $field['data'] as $option ) {
				if ( is_object( $option ) && ! isset( $option->inactive ) && isset( $lookup[ (int) $option->id ] ) ) {
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
				'card'  => '#ecdv2_advanced_pricing',
			);
		}

		$role_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_roleprice WHERE product_id = %d', $this->id ) );
		if ( $role_count > 0 ) {
			$chips[] = array(
				'label' => sprintf( _n( 'B2B pricing (%d role)', 'B2B pricing (%d roles)', $role_count, 'wp-easycart' ), $role_count ),
				'tab'   => 'pricing',
				'card'  => '#ecdv2_advanced_pricing',
			);
		}

		if ( (int) $p->enable_price_label > 0 ) {
			$chips[] = array( 'label' => __( 'Custom price label', 'wp-easycart' ), 'tab' => 'pricing', 'card' => '.ecdv2-card[data-ecdv2-section="pricing"]' );
		}
		if ( ! empty( $p->show_custom_price_range ) ) {
			$chips[] = array( 'label' => __( 'Price range display', 'wp-easycart' ), 'tab' => 'pricing', 'card' => '.ecdv2-card[data-ecdv2-section="pricing"]' );
		}
		if ( ! empty( $p->login_for_pricing ) ) {
			$chips[] = array( 'label' => __( 'Login for pricing', 'wp-easycart' ), 'tab' => 'pricing', 'card' => '.ecdv2-card[data-ecdv2-section="pricing"]' );
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
			/* 6.0.4: a chip whose card is on the Price card's own section ( Pricing ) goes to that card. */
			$chip_go = empty( $chip['card'] ) ? 'ecdv2.go_tab( ' . wp_json_encode( $chip['tab'] ) . ' );' : 'ecdv2.go_card( ' . wp_json_encode( $chip['card'] ) . ' );';
			echo '<button type="button" class="ecdv2-price-context-chip" onclick="' . esc_attr( $chip_go ) . '">';
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
			$cluster_title   = __( 'Price label', 'wp-easycart' );
		}
		$cluster_open = false;

		foreach ( $fields as $field ) {
			$fname = isset( $field['name'] ) ? $field['name'] : '';

			if ( $cluster_members && $fname === $cluster_members[0] && ! $cluster_open ) {
				echo '<div class="ecdv2-subgroup ecdv2-field-full"><div class="ecdv2-subgroup-title">' . esc_html( $cluster_title ) . '</div><div class="ecdv2-subgroup-body">';
				$cluster_open = true;
			}

			/* 6.0.2: Track Quantity saves with the card's Save like every other field. The legacy onchange
			 * ( ec_admin_product_details_quantity_type_change ) saved the moment it changed and left the header
			 * behind; products-details-v2.js keeps its free-edition guard. The function stays for PRO's
			 * Change tracking dialog, which calls it directly. */
			if ( 'quantities' === $this->current_section && 'stock_quantity_type' === $fname ) {
				unset( $field['onchange'] );
			}

			$this->print_field_v2( $field );

			/* General tab: right under the Price / Previous Price pair, surface
			 * every other place this product's pricing is defined ( variants,
			 * volume tiers, B2B roles, labels ) with one-click navigation. */
			if ( 'basic' === $this->current_section && 'list_price' === $fname && $this->prints_field( 'list_price' ) ) {
				$this->print_price_context();
			}

			/* 6.0.2: right under the Subscription Product switch, whether it is on or not: subscriptions need Stripe. */
			if ( 'subscription' === $this->current_section && 'is_subscription_item' === $fname ) {
				$this->print_subscription_gateway_notice();
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
	 * Subscriptions are billed through Stripe only ( 6.0.2 ). While the store can't sell them ( another card gateway,
	 * or Stripe not connected ), say so under the Subscription Product switch, before and after it is turned on, with
	 * the way to Settings › Payments ( a new tab: the editor may hold unsaved changes ). Nothing prints once Stripe bills.
	 *
	 * @since 6.0.2
	 */
	private function print_subscription_gateway_notice() {
		if ( ! class_exists( 'wp_easycart_subscription_gateway' ) ) {
			return;
		}
		$html = wp_easycart_subscription_gateway::notice_html( 'product', array( 'new_tab' => true ) );
		if ( '' === $html ) {
			return;
		}
		echo '<div class="ecdv2-field ecdv2-field-full ecdv2-subscription-gateway" id="ecdv2_subscription_gateway">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes every part ( wp_easycart_admin::notice_html() ).
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
		echo '<p class="ecdv2-menu-locations-note">' . esc_html__( 'Each location goes Level 1, then Level 2 and 3 if you need them. Leave a level on "None Selected" to stop there.', 'wp-easycart' ) . '</p>';

		/* 6.0.4: the first location always shows; one more appears with Add another menu location, and those in use show. */
		$used = array();
		$loc  = 0;
		foreach ( $fields as $field ) {
			$fname = isset( $field['name'] ) ? $field['name'] : '';
			if ( $fname && preg_match( '/^menulevel(\d+)_id_1$/', $fname, $m ) ) {
				$loc = (int) $m[1];
			}
			if ( $loc && $fname && 0 === strpos( $fname, 'menulevel' ) && (int) $this->field_value( $field ) > 0 ) {
				$used[ $loc ] = true;
			}
		}
		$waiting = 0;

		$open = false;
		$first = true;
		foreach ( $fields as $field ) {
			$fname = isset( $field['name'] ) ? $field['name'] : '';

			/* A new location begins at its Level-1 select: menulevel{N}_id_1. */
			if ( $fname && preg_match( '/^menulevel(\d+)_id_1$/', $fname, $m ) ) {
				if ( $open ) {
					echo '</div></div>';
				}
				$loc = (int) $m[1];
				$show = $first || ! empty( $used[ $loc ] );
				$first = false;
				if ( ! $show ) {
					$waiting++;
				}
				echo '<div class="ecdv2-menu-location"' . ( $show ? '' : ' hidden' ) . '>';
				echo '<div class="ecdv2-menu-location-head">';
				echo '<span class="ecdv2-menu-location-badge">' . esc_html( $loc ) . '</span>';
				/* translators: %d: 1, 2 or 3. */
				echo '<span class="ecdv2-menu-location-title">' . esc_html( sprintf( __( 'Menu location %d', 'wp-easycart' ), $loc ) ) . '</span>';
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
		if ( $waiting ) {
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecdv2-menu-location-add" onclick="ecdv2.menu_location_add( this );"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'Add another menu location', 'wp-easycart' ) . '</button>';
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
		} else if ( isset( $field['requires'] ) && is_array( $field['requires'] ) && isset( $field['requires'][0]['name'] ) ) {
			/* Multi-condition requires ( e.g. download_file_name shows only
			 * when is_download=1 AND is_amazon_download=0 ). All conditions
			 * must match, mirroring the legacy printer's evaluation. */
			$conditions = array();
			foreach ( $field['requires'] as $condition ) {
				if ( ! isset( $condition['name'] ) ) {
					continue;
				}
				$condition_value = array_key_exists( 'value', $condition ) ? $condition['value'] : 1;
				$conditions[] = array(
					'name'  => (string) $condition['name'],
					'value' => is_array( $condition_value ) ? implode( ',', $condition_value ) : (string) $condition_value,
				);
			}
			if ( ! empty( $conditions ) ) {
				$attrs .= " data-ecdv2-requires-multi='" . esc_attr( wp_json_encode( $conditions ) ) . "'";
			}
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

	/**
	 * Customer reviews card — its own section ( not a save-section ): the Allow toggle saves immediately, reviews list
	 * with inline approve/unapprove, links to the full Reviews list filtered to this product and to review settings.
	 * Handles the disabled state: reviews are hidden on the storefront but nothing is deleted.
	 * Drop into the template wherever the Reviews tab lives: <?php $this->print_reviews_v2(); ?>
	 */
	public function print_reviews_v2() {
		global $wpdb;
		$pid = (int) $this->id; $on = (bool) $this->product->use_customer_reviews; $is_new = ! $pid;
		$counts = $this->review_counts_v2();
		$total = $counts['total'];
		$approved = $counts['approved'];
		$avg = $counts['avg'];
		$rows = $pid ? $wpdb->get_results( $wpdb->prepare( 'SELECT review_id, approved, rating, title, description AS review, reviewer_name, date_submitted FROM ec_review WHERE product_id = %d ORDER BY date_submitted DESC LIMIT 5', $pid ) ) : array();
		$list_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=reviews&filter_2=' . $pid );
		$nonce = wp_create_nonce( 'wp-easycart-ecdv2-reviews-toggle' );

		/* 6.0.4: section reviews ( the switch saves with the product, through the general options save that posts the carrier ).
		   6.0.4: the Reviews section's heading carries the count and Review settings ( the template ), so the card has no heading
		   of its own on screen ( its title stays for the search and screen readers ). */
		echo '<div class="ecdv2-card ecdv2-card-headless ecdv2-reviews-card' . ( $on ? '' : ' is-off' ) . '" id="ecdv2-reviews-card"' . ( $pid ? ' data-ecdv2-section="reviews"' : '' ) . ' data-product-id="' . $pid . '" data-nonce="' . esc_attr( $nonce ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $pid is (int) cast above.
		echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Customer reviews', 'wp-easycart' ) . '</h3>';
		echo '</div><div class="ecdv2-card-body">';

		/* the toggle mirrors the hidden carrier in the general card; 6.0.4: it saves with the product */
		echo '<div class="ecdv2-field ecdv2-field-full ecdv2-toggle-row ecdv2-reviews-toggle-row">';
		/* .ecdv2-toggle-track is the painted switch ( the input itself is transparent ); any other class renders nothing. */
		echo '<span class="ecdv2-toggle"><input type="checkbox" id="ecdv2_reviews_allow" value="1"' . checked( true, $on, false ) . ( $is_new ? ' data-new="1"' : '' ) . ' onchange="ecdv2_reviews_toggle( this );" /><span class="ecdv2-toggle-track"></span></span>';
		echo '<span class="ecdv2-toggle-meta"><label class="ecdv2-toggle-label" for="ecdv2_reviews_allow">' . esc_html__( 'Allow customer reviews on this product', 'wp-easycart' ) . '</label><div class="ecdv2-toggle-desc">' . esc_html__( 'Shows the review form and approved reviews on the product page. Turning it off hides them without deleting anything.', 'wp-easycart' ) . '</div></span>';
		echo '</div>';

		echo '<div class="ecdv2-reviews-off-note ecos-note" id="ecdv2_reviews_off"' . ( $on ? ' style="display:none"' : '' ) . '><span class="dashicons dashicons-hidden"></span><div>' . ( $total ? esc_html( sprintf( _n( 'Reviews are off for this product — the %d existing review is hidden on the storefront and customers can’t add more.', 'Reviews are off for this product — the %d existing reviews are hidden on the storefront and customers can’t add more.', $total, 'wp-easycart' ), $total ) ) : esc_html__( 'Reviews are off for this product — customers can’t leave one.', 'wp-easycart' ) ) . '</div></div>';

		if ( $is_new ) {
			echo '<div class="ecdv2-activity-empty">' . esc_html__( 'Reviews appear here once the product is saved.', 'wp-easycart' ) . '</div>';
		} else {
			if ( $approved ) { echo '<div class="ecdv2-reviews-summary"><span class="ecdv2-reviews-avg">' . esc_html( number_format_i18n( $avg, 1 ) ) . '</span><span class="ecdv2-activity-stars">' . str_repeat( '★', (int) round( $avg ) ) . str_repeat( '☆', 5 - (int) round( $avg ) ) . '</span><span class="ecv2-sub" style="display:inline">' . esc_html( sprintf( _n( 'from %d approved review', 'from %d approved reviews', $approved, 'wp-easycart' ), $approved ) ) . '</span></div>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- str_repeat() only repeats literal star glyphs.
			if ( $rows ) {
				echo '<div class="ecdv2-reviews-list">';
				foreach ( $rows as $r ) {
					$a = (int) $r->approved;
					echo '<div class="ecdv2-activity-row" data-review-id="' . (int) $r->review_id . '">';
					echo '<span class="ecdv2-activity-row-main"><span class="ecdv2-activity-stars">' . (int) $r->rating . ' ★</span><span class="ecdv2-activity-name">' . esc_html( wp_unslash( (string) $r->title ) ) . '</span><span class="ecdv2-activity-sub">' . esc_html( wp_unslash( (string) $r->reviewer_name ) ) . ' · ' . esc_html( $r->date_submitted ? date_i18n( 'M j, Y', strtotime( $r->date_submitted ) ) : '' ) . '</span>' . ( $r->review ? '<span class="ecdv2-reviews-excerpt">' . esc_html( wp_trim_words( wp_unslash( (string) $r->review ), 24 ) ) . '</span>' : '' ) . '</span>';
					echo '<span class="ecdv2-activity-row-meta"><button type="button" class="ecdv2-review-toggle' . ( $a ? ' is-approved' : '' ) . '" onclick="ecdv2.review_toggle( this, ' . (int) $r->review_id . ', ' . ( $a ? 0 : 1 ) . ' );">' . esc_html( $a ? __( 'Approved', 'wp-easycart' ) : __( 'Approve', 'wp-easycart' ) ) . '</button> <a class="ecdv2-help-link" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=reviews&ec_admin_form_action=edit&review_id=' . (int) $r->review_id ) ) . '">' . esc_html__( 'Open', 'wp-easycart' ) . '</a></span>';
					echo '</div>';
				}
				echo '</div>';
			} else {
				echo '<div class="ecdv2-activity-empty">' . esc_html( $on ? __( 'No reviews yet. Approved reviews show here and on the product page.', 'wp-easycart' ) : __( 'No reviews for this product.', 'wp-easycart' ) ) . '</div>';
			}
			echo '<div class="ecdv2-reviews-foot"><a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( $list_url ) . '">' . esc_html( $total > 5 ? sprintf( __( 'All %d reviews for this product', 'wp-easycart' ), $total ) : __( 'Open in Reviews list', 'wp-easycart' ) ) . ' <span class="dashicons dashicons-arrow-right-alt2"></span></a></div>';
		}
		echo '</div></div>';
	}

	/*
	 * PRO insight panels: stock history ( Inventory tab ) and the Offers tab.
	 */

	/**
	 * Gate for a PRO product-editor panel. PRO switches a panel on by returning true
	 * from $filter ( licensed, schema ready ) and prints it on $action. Anything else
	 * renders the locked preview, with the call to action matched to the gate state.
	 *
	 * @since 6.0.0
	 *
	 * @param string $filter Enabled filter PRO hooks.
	 * @param string $action Action PRO prints the real panel on.
	 * @return array Gate from wp_easycart_admin_pro_gate::evaluate() plus 'render' ( bool ).
	 */
	private function pro_panel_gate( $filter, $action ) {
		if ( ! class_exists( 'wp_easycart_admin_pro_gate' ) ) {
			return array(
				'state'  => 'upsell',
				'desc'   => '',
				'url'    => '',
				'render' => false,
			);
		}
		$gate = wp_easycart_admin_pro_gate::evaluate(
			array(
				'enabled_filter' => $filter,
				'min_version'    => '6.0.0',
			)
		);
		$gate['render'] = ( 'enabled' === $gate['state'] && has_action( $action ) );
		return $gate;
	}

	/**
	 * Locked PRO preview used by the stock history and offers panels: header copy with a
	 * state-aware call to action, a soft-locked mock ( $mock_html, built from escaped
	 * values by the caller ), and the matching feature bullets from the upsell catalog.
	 *
	 * @since 6.0.0
	 *
	 * @param array $args {
	 *     @type array  $gate      Gate from pro_panel_gate().
	 *     @type string $context   wp_easycart_admin_upsell catalog key.
	 *     @type string $feature   Feature key inside that context.
	 *     @type string $headline  Header line.
	 *     @type string $copy      Header sub line.
	 *     @type string $tag       Mock corner tag.
	 *     @type string $veil      Veil button label.
	 *     @type string $mock_html Pre-escaped mock markup.
	 *     @type array  $features  Catalog feature keys to list under the mock.
	 * }
	 */
	private function print_pro_panel_preview( $args ) {
		$gate    = $args['gate'];
		$context = $args['context'];
		$feature = $args['feature'];
		$onclick = 'ecdv2_upsell( { context: \'' . esc_js( $context ) . '\', feature: \'' . esc_js( $feature ) . '\' } ); return false;';

		echo '<div class="ecdv2-media-upsell ecdv2-pro-preview" data-upsell-context="' . esc_attr( $context ) . '" data-gate-state="' . esc_attr( $gate['state'] ) . '">';
		echo '<div class="ecdv2-media-upsell-head">';
		echo '<span class="ecdv2-media-pill">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
		echo '<div class="ecdv2-media-upsell-copy"><strong>' . esc_html( $args['headline'] ) . '</strong><span>' . esc_html( $args['copy'] ) . '</span></div>';
		if ( 'inactive' === $gate['state'] ) {
			echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( wp_easycart_admin()->get_pro_activation_link() ) . '">' . esc_html( sprintf( /* translators: %s: plugin name, WP EasyCart PRO. */ __( 'Activate %s', 'wp-easycart' ), wp_easycart_admin_edition::PLUGIN_NAME ) ) . '</a>';
		} elseif ( in_array( $gate['state'], array( 'update', 'license' ), true ) && '' !== $gate['url'] ) {
			echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( $gate['url'] ) . '">' . esc_html( $gate['desc'] ) . '</a>';
		} else {
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="' . esc_attr( $onclick ) . '">' . esc_html__( "See what's included", 'wp-easycart' ) . '</button>';
		}
		echo '</div>';

		echo '<div class="ecdv2-media-mock ecdv2-pro-mock" onclick="' . esc_attr( $onclick ) . '" role="button" tabindex="0">';
		echo '<div class="ecdv2-media-mock-tag"><span class="dashicons dashicons-visibility"></span> ' . esc_html( $args['tag'] ) . '</div>';
		echo $args['mock_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the caller from esc_html()/esc_attr() values only.
		echo '<div class="ecdv2-media-mock-veil"><span class="ecv2-btn ecv2-btn-primary"><span class="dashicons dashicons-lock"></span> ' . esc_html( $args['veil'] ) . '</span></div>';
		echo '</div>';

		if ( class_exists( 'wp_easycart_admin_upsell' ) && ! empty( $args['features'] ) ) {
			/* catalog() rather than entry(): entry() also runs the store stat queries, which this list does not use. */
			$catalog  = wp_easycart_admin_upsell::catalog();
			$features = isset( $catalog[ $context ]['features'] ) ? $catalog[ $context ]['features'] : array();
			echo '<ul class="ecdv2-media-upsell-list">';
			foreach ( $args['features'] as $key ) {
				if ( ! isset( $features[ $key ] ) ) {
					continue;
				}
				$f = $features[ $key ];
				echo '<li onclick="' . esc_attr( 'ecdv2_upsell( { context: \'' . esc_js( $context ) . '\', feature: \'' . esc_js( $key ) . '\' } );' ) . '"><span class="dashicons ' . esc_attr( $f['icon'] ) . '"></span><span><strong>' . esc_html( $f['title'] ) . '</strong><em>' . esc_html( $f['desc'] ) . '</em></span></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/**
	 * Inventory tab: stock change history. PRO prints the live, paginated history
	 * ( ec_inventory_log ) on 'wp_easycart_admin_product_details_v2_stock_history_pro';
	 * otherwise a locked preview seeded with this product's current stock.
	 *
	 * @since 6.0.0
	 */
	public function print_stock_history_v2() {
		if ( ! $this->id ) {
			return;
		}
		$gate = $this->pro_panel_gate( 'wp_easycart_admin_product_stock_history_pro_enabled', 'wp_easycart_admin_product_details_v2_stock_history_pro' );
		if ( $gate['render'] ) {
			do_action( 'wp_easycart_admin_product_details_v2_stock_history_pro', $this->product );
			return;
		}

		$now_qty = max( 0, (int) $this->product->stock_quantity );
		$user    = wp_get_current_user();
		$me      = ( $user && $user->exists() ) ? $user->display_name : __( 'Store admin', 'wp-easycart' );
		$ts      = time();
		/* translators: %d: sample order number. */
		$order_label = sprintf( __( 'Order #%d', 'wp-easycart' ), 1042 );
		/* Sample amounts chosen so the running quantity ends at today's real stock and never dips below zero. */
		$received = max( 1, min( 24, $now_qty + 3 ) );
		$recount  = $now_qty + 3 - $received;
		/* Newest first: ago ( seconds ), kind, amount, stock after, reason, note, by. */
		$sample = array(
			array( HOUR_IN_SECONDS * 3, 'remove', 2, $now_qty, __( 'Order', 'wp-easycart' ), '', $order_label ),
			array( DAY_IN_SECONDS, 'remove', 1, $now_qty + 2, __( 'Damaged', 'wp-easycart' ), __( 'Crushed in transit', 'wp-easycart' ), $me ),
			array( DAY_IN_SECONDS * 3, 'add', $received, $now_qty + 3, __( 'Stock received', 'wp-easycart' ), __( 'PO 318', 'wp-easycart' ), $me ),
			array( DAY_IN_SECONDS * 6, 'set', 0, $recount, __( 'Recount', 'wp-easycart' ), '', $me ),
			array( DAY_IN_SECONDS * 9, 'set', 0, $recount + 4, __( 'CSV import', 'wp-easycart' ), '', __( 'Import', 'wp-easycart' ) ),
		);
		$rows = '';
		foreach ( $sample as $s ) {
			list( $ago, $kind, $amount, $after, $reason, $note, $by ) = $s;
			if ( 'add' === $kind ) {
				/* translators: %d: units added. */
				$chip = '<span class="ecdv2-hist-delta is-add">' . esc_html( sprintf( __( 'Added %d', 'wp-easycart' ), $amount ) ) . '</span>';
			} elseif ( 'remove' === $kind ) {
				/* translators: %d: units removed. */
				$chip = '<span class="ecdv2-hist-delta is-remove">' . esc_html( sprintf( __( 'Removed %d', 'wp-easycart' ), $amount ) ) . '</span>';
			} else {
				/* translators: %d: quantity written. */
				$chip = '<span class="ecdv2-hist-delta is-set">' . esc_html( sprintf( __( 'Set to %d', 'wp-easycart' ), $after ) ) . '</span>';
			}
			$rows .= '<tr><td class="ecdv2-hist-date">' . esc_html( wp_date( get_option( 'date_format' ), $ts - $ago ) ) . '</td><td>' . $chip . '</td><td class="ecdv2-hist-qty">' . esc_html( number_format_i18n( $after ) ) . '</td><td>' . esc_html( $reason ) . ( '' !== $note ? '<span class="ecdv2-hist-note">' . esc_html( $note ) . '</span>' : '' ) . '</td><td class="ecdv2-hist-by">' . esc_html( $by ) . '</td></tr>';
		}
		$mock  = '<div class="ecdv2-hist-scroll"><table class="ecdv2-hist-table"><thead><tr>';
		$mock .= '<th>' . esc_html__( 'Date', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Change', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Stock after', 'wp-easycart' ) . '</th><th>' . esc_html__( 'Reason', 'wp-easycart' ) . '</th><th>' . esc_html__( 'By', 'wp-easycart' ) . '</th>';
		$mock .= '</tr></thead><tbody>' . $rows . '</tbody></table></div>';

		echo '<div class="ecdv2-card ecdv2-stock-history-card" id="ecdv2_stock_history">';
		echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Stock history', 'wp-easycart' ) . '</h3><span class="ecdv2-card-hint">' . esc_html__( 'Every sale, restock and adjustment for this product', 'wp-easycart' ) . '</span></div>';
		echo '<div class="ecdv2-card-body">';
		$this->print_pro_panel_preview(
			array(
				'gate'      => $gate,
				'context'   => 'inventory',
				'feature'   => 'history',
				'headline'  => __( 'See where every unit went', 'wp-easycart' ),
				'copy'      => sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( '%s records each stock change with the reason, the resulting quantity and who made it. This preview starts from your current stock.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ),
				'tag'       => __( 'Preview with sample history', 'wp-easycart' ),
				'veil'      => __( 'Unlock stock history', 'wp-easycart' ),
				'mock_html' => $mock,
				'features'  => array( 'history', 'adjust', 'reorder' ),
			)
		);
		echo '</div></div>';
	}

	/**
	 * Offers tab: offers, coupons and promotions that discount this product. PRO prints
	 * the live list on 'wp_easycart_admin_product_details_v2_offers_pro'; otherwise a
	 * locked preview built around this product.
	 *
	 * @since 6.0.0
	 */
	public function print_offers_v2() {
		if ( ! $this->id ) {
			return;
		}
		$gate = $this->pro_panel_gate( 'wp_easycart_admin_product_offers_pro_enabled', 'wp_easycart_admin_product_details_v2_offers_pro' );
		if ( $gate['render'] ) {
			do_action( 'wp_easycart_admin_product_details_v2_offers_pro', $this->product );
			return;
		}

		$title = wp_unslash( (string) $this->product->title );
		$price = (float) $this->product->price;
		$ts    = time();
		$df    = get_option( 'date_format' );
		/* translators: %s: product title. */
		$spring_what = sprintf( __( '20 percent off · %s', 'wp-easycart' ), $title );
		/* translators: %s: formatted date. */
		$starts = sprintf( __( 'Starts %s', 'wp-easycart' ), wp_date( $df, $ts + WEEK_IN_SECONDS ) );
		/* translators: %s: formatted date. */
		$ended = sprintf( __( 'Ended %s', 'wp-easycart' ), wp_date( $df, $ts - MONTH_IN_SECONDS ) );
		/* translators: %s: formatted minimum order amount. */
		$ship_what = sprintf( __( 'Free shipping · over %s', 'wp-easycart' ), $this->format_price( 50 ) );
		/* Sample offers: icon, name, summary, status key, status label, code, orders, revenue. */
		$sample = array(
			array( 'dashicons-tag', __( 'Spring sale', 'wp-easycart' ), $spring_what, 'live', __( 'Live', 'wp-easycart' ), '', 38, $price * 38 * 0.8 ),
			array( 'dashicons-tickets-alt', __( 'Welcome discount', 'wp-easycart' ), __( '10 percent off the order · first order only', 'wp-easycart' ), 'live', __( 'Live', 'wp-easycart' ), 'WELCOME10', 12, $price * 12 * 0.9 ),
			array( 'dashicons-plus-alt', __( 'Buy 2, get 1 free', 'wp-easycart' ), __( 'Buy 2, get 1 free · this category', 'wp-easycart' ), 'scheduled', $starts, '', 0, 0 ),
			array( 'dashicons-car', __( 'Free shipping weekend', 'wp-easycart' ), $ship_what, 'expired', $ended, 'SHIPFREE', 21, $price * 21 ),
		);
		$rows = array();
		foreach ( $sample as $s ) {
			$rows[] = array_combine( array( 'icon', 'name', 'what', 'status', 'label', 'code', 'uses', 'revenue' ), $s );
		}

		/* A tested code ( "Test a coupon code", 6.0.2 ): same markup as PRO's answer, with sample values. */
		$test_checks = array(
			array( 'ok', 'yes', __( 'The code is on.', 'wp-easycart' ) ),
			array( 'ok', 'yes', __( 'The offer is live.', 'wp-easycart' ) ),
			array( 'ok', 'yes', __( 'It covers this product.', 'wp-easycart' ) ),
			array( 'warn', 'warning', __( 'Conditions: First order only', 'wp-easycart' ) ),
			/* translators: 1: product price, 2: discount. */
			array( 'ok', 'yes', sprintf( __( 'One at %1$s gets %2$s off.', 'wp-easycart' ), $this->format_price( $price ), $this->format_price( $price * 0.1 ) ) ),
		);
		$mock  = '<div class="ecdv2-offer-test"><span class="ecdv2-offer-test-label">' . esc_html__( 'Test a coupon code', 'wp-easycart' ) . '</span>';
		$mock .= '<div class="ecdv2-offer-test-form"><span class="ecv2-input ecv2-input-sm">WELCOME10</span><span class="ecv2-btn ecv2-btn-sm">' . esc_html__( 'Test code', 'wp-easycart' ) . '</span></div>';
		$mock .= '<div class="ecdv2-offer-test-result"><div class="ecdv2-offer-test-card is-maybe"><div class="ecdv2-offer-test-head">';
		$mock .= '<span class="ecdv2-offer-test-icon dashicons dashicons-warning"></span>';
		/* translators: %s: coupon code. */
		$mock .= '<div class="ecdv2-offer-test-summary"><strong class="ecdv2-offer-test-title">' . esc_html( sprintf( __( '%s can discount this product, with the limits below.', 'wp-easycart' ), 'WELCOME10' ) ) . '</strong>';
		/* translators: %s: offer name. */
		$mock .= '<span class="ecdv2-offer-test-source">' . esc_html( sprintf( __( 'Offer: %s', 'wp-easycart' ), __( 'Welcome discount', 'wp-easycart' ) ) ) . '</span></div>';
		$mock .= '<div class="ecdv2-offer-test-links"><span class="ecv2-btn ecv2-btn-sm ecv2-btn-primary">' . esc_html__( 'Edit offer', 'wp-easycart' ) . '</span></div></div><ul class="ecdv2-offer-test-checks">';
		foreach ( $test_checks as $check ) {
			$mock .= '<li class="is-' . esc_attr( $check[0] ) . '"><span class="dashicons dashicons-' . esc_attr( $check[1] ) . '"></span><span>' . esc_html( $check[2] ) . '</span></li>';
		}
		$mock .= '</ul></div></div></div>';

		/* Stat tiles: the shared V2 list strip, static ( same markup PRO prints ). */
		$mock .= '<div class="ecv2-health-dashboard ecv2-health-dashboard-grouped ecdv2-offers-stats"><div class="ecv2-health-group">';
		$mock .= '<div class="ecv2-stat-card ecv2-stat-static ecv2-stat-green"><div class="ecv2-stat-main"><div class="ecv2-stat-value">2</div><div class="ecv2-stat-label">' . esc_html__( 'Live offers', 'wp-easycart' ) . '</div></div></div>';
		$mock .= '<div class="ecv2-stat-card ecv2-stat-static ecv2-stat-default"><div class="ecv2-stat-main"><div class="ecv2-stat-value">71</div><div class="ecv2-stat-label">' . esc_html__( 'Orders with an offer', 'wp-easycart' ) . '</div></div></div>';
		$mock .= '<div class="ecv2-stat-card ecv2-stat-static ecv2-stat-default"><div class="ecv2-stat-main"><div class="ecv2-stat-value">' . esc_html( $this->format_price( $price * 62 ) ) . '</div><div class="ecv2-stat-label">' . esc_html__( 'Product revenue with offers', 'wp-easycart' ) . '</div></div></div>';
		$mock .= '</div></div><div class="ecdv2-offers-list">';
		foreach ( $rows as $r ) {
			$mock .= '<div class="ecdv2-offer-row">';
			$mock .= '<span class="ecdv2-offer-icon"><span class="dashicons ' . esc_attr( $r['icon'] ) . '"></span></span>';
			$mock .= '<span class="ecdv2-offer-main"><span class="ecdv2-offer-name">' . esc_html( $r['name'] ) . ( '' !== $r['code'] ? ' <code class="ecdv2-offer-code">' . esc_html( $r['code'] ) . '</code>' : '' ) . '</span><span class="ecdv2-offer-what">' . esc_html( $r['what'] ) . '</span></span>';
			$mock .= '<span class="ecdv2-offer-stats"><span class="ecdv2-offer-stat"><strong>' . esc_html( number_format_i18n( $r['uses'] ) ) . '</strong> ' . esc_html__( 'orders', 'wp-easycart' ) . '</span><span class="ecdv2-offer-stat"><strong>' . esc_html( $this->format_price( $r['revenue'] ) ) . '</strong> ' . esc_html__( 'revenue', 'wp-easycart' ) . '</span></span>';
			$mock .= '<span class="ecdv2-offer-status is-' . esc_attr( $r['status'] ) . '">' . esc_html( $r['label'] ) . '</span>';
			$mock .= '</div>';
		}
		$mock .= '</div>';

		/* 6.0.4: the Offers section's heading says what this card is ( its title stays for the search and screen readers ). */
		echo '<div class="ecdv2-card ecdv2-card-headless ecdv2-offers-card" id="ecdv2_offers">';
		echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Offers for this product', 'wp-easycart' ) . '</h3><span class="ecdv2-card-hint">' . esc_html__( 'Promotions and coupon codes that discount it', 'wp-easycart' ) . '</span></div>';
		echo '<div class="ecdv2-card-body">';
		$this->print_pro_panel_preview(
			array(
				'gate'      => $gate,
				'context'   => 'offers',
				'feature'   => 'reporting',
				'headline'  => __( 'Every discount on this product, and what it earned', 'wp-easycart' ),
				'copy'      => sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'With %s this tab lists the offers, coupons and promotions that apply here, whether each is live, scheduled or ended, and the orders and revenue it brought in. Test any coupon code to see whether it discounts this product, and why not.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ),
				'tag'       => __( 'Preview with sample offers', 'wp-easycart' ),
				'veil'      => __( 'Unlock offers', 'wp-easycart' ),
				'mock_html' => $mock,
				'features'  => array( 'code_test', 'targeting', 'schedule', 'reporting' ),
			)
		);
		echo '</div></div>';
	}

	/**
	 * 6.0.4: the Google Shopping tab while WP EasyCart PRO has not hooked its Google Merchant fields ( it does only with an active
	 * license ): the locked preview the Offers and Stock history sections use ( state-aware button, a preview built from this
	 * product, the upsell catalog's google_feed features ).
	 *
	 * @since 6.0.4
	 * @param string $card_image The product's main picture ( the summary column's card ).
	 * @return bool false while the gate reads the store as licensed ( the caller prints the fields instead ).
	 */
	public function print_google_shopping_upsell_v2( $card_image = '' ) {
		global $wpdb;
		$gate = class_exists( 'wp_easycart_admin_pro_gate' ) ? wp_easycart_admin_pro_gate::evaluate( array() ) : array(
			'state' => 'upsell',
			'desc'  => '',
			'url'   => '',
		);
		if ( 'enabled' === $gate['state'] || ! class_exists( 'wp_easycart_admin_edition' ) ) {
			return false;
		}
		$p        = $this->product;
		$title    = wp_unslash( (string) $p->title );
		$in_stock = empty( $p->show_stock_quantity ) || ! empty( $p->use_optionitem_quantity_tracking ) || (int) $p->stock_quantity > 0;
		$category = $this->id ? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ec_category.category_name FROM ec_categoryitem INNER JOIN ec_category ON ec_category.category_id = ec_categoryitem.category_id WHERE ec_categoryitem.product_id = %d ORDER BY ec_category.category_name ASC LIMIT 1', (int) $this->id ) ) : '';

		/* A Google Shopping result for this product, and the attributes this tab sets ( this product's values where it has them ). */
		$mock  = '<div class="ecdv2-gm-mock">';
		$mock .= '<div class="ecdv2-gm-mock-tile"><span class="ecdv2-gm-mock-img">';
		$mock .= '' !== $card_image ? '<img src="' . esc_url( $card_image ) . '" alt="" loading="lazy" />' : '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>';
		$mock .= '</span><span class="ecdv2-gm-mock-title">' . esc_html( '' !== $title ? $title : __( 'Your product', 'wp-easycart' ) ) . '</span>';
		$mock .= '<span class="ecdv2-gm-mock-price">' . esc_html( $this->format_price( $p->price ) ) . '</span>';
		$mock .= '<span class="ecdv2-gm-mock-store">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		$mock .= '<span class="ecdv2-gm-mock-stock' . ( $in_stock ? '' : ' is-out' ) . '">' . ( $in_stock ? esc_html__( 'In stock', 'wp-easycart' ) : esc_html__( 'Out of stock', 'wp-easycart' ) ) . '</span></div>';
		/* Only what the tab sets: the barcode, part number and condition are the free Search & AI tab's. */
		$mock .= '<dl class="ecdv2-gm-mock-fields">';
		foreach ( array(
			__( 'Google product category', 'wp-easycart' ) => __( 'Apparel & Accessories › Clothing › Shirts & Tops', 'wp-easycart' ),
			__( 'Product type', 'wp-easycart' )            => '' !== $category ? wp_unslash( $category ) : __( 'Your own category', 'wp-easycart' ),
			__( 'Gender', 'wp-easycart' )                  => __( 'Unisex', 'wp-easycart' ),
			__( 'Age group', 'wp-easycart' )               => __( 'Adult', 'wp-easycart' ),
			__( 'Color and size', 'wp-easycart' )          => __( 'From each variation', 'wp-easycart' ),
			__( 'Item group ID', 'wp-easycart' )           => '' !== (string) $p->model_number ? wp_unslash( (string) $p->model_number ) : '—',
			__( 'Availability', 'wp-easycart' )            => __( 'Follows your stock', 'wp-easycart' ),
		) as $label => $value ) {
			$mock .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
		}
		$mock .= '</dl></div>';

		$this->print_pro_panel_preview(
			array(
				'gate'      => $gate,
				'context'   => 'google_feed',
				'feature'   => 'attributes',
				'headline'  => __( 'Show this product on Google Shopping', 'wp-easycart' ),
				/* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */
				'copy'      => sprintf( __( 'With %s your store keeps a Google product feed up to date by itself, and this tab gives this product its Google category, color, size, gender, age group and the other attributes Google asks for, so it shows up for the right searches.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ),
				'tag'       => __( 'Preview with this product and sample values', 'wp-easycart' ),
				'veil'      => __( 'Unlock Google Shopping', 'wp-easycart' ),
				'mock_html' => $mock,
				'features'  => array( 'attributes', 'auto', 'variants', 'connect' ),
			)
		);
		return true;
	}

	/*
	 * SEO tab: Yoast SEO fields.
	 */

	/**
	 * Yoast SEO card on the SEO tab. Only prints while Yoast SEO is loaded. Values are
	 * the product post's Yoast post meta; the card is a normal save section ( 'yoast_seo' )
	 * saved by ec_admin_ajax_save_product_details_yoast_seo.
	 *
	 * @since 6.0.0
	 */
	public function print_yoast_seo_v2() {
		if ( ! defined( 'WPSEO_VERSION' ) || ! $this->id ) {
			return;
		}
		$post_id = (int) $this->product->post_id;
		$post    = $post_id ? get_post( $post_id ) : null;

		$this->section_open( 'yoast_seo', __( 'Yoast SEO', 'wp-easycart' ), __( 'Saved to Yoast on this product\'s WordPress page', 'wp-easycart' ) );
		if ( ! $post ) {
			echo '<div class="ecdv2-activity-empty">' . esc_html__( 'This product is not linked to a WordPress page yet, so there is nowhere to store Yoast data. Save the product and reload to create it.', 'wp-easycart' ) . '</div>';
			$this->section_close();
			return;
		}

		$title     = (string) get_post_meta( $post_id, '_yoast_wpseo_title', true );
		$metadesc  = (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		$focuskw   = (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		$canonical = (string) get_post_meta( $post_id, '_yoast_wpseo_canonical', true );
		$noindex   = (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
		if ( ! in_array( $noindex, array( '0', '1', '2' ), true ) ) {
			$noindex = '0';
		}

		/* Yoast's own defaults for this post type, used as placeholders and in the preview. */
		$post_type     = get_post_type( $post );
		$default_title = wp_unslash( (string) $this->product->title );
		$default_desc  = '';
		$type_noindex  = false;
		if ( class_exists( 'WPSEO_Options' ) && method_exists( 'WPSEO_Options', 'get' ) ) {
			$title_template = (string) WPSEO_Options::get( 'title-' . $post_type, '' );
			$desc_template  = (string) WPSEO_Options::get( 'metadesc-' . $post_type, '' );
			$type_noindex   = (bool) WPSEO_Options::get( 'noindex-' . $post_type, false );
			if ( function_exists( 'wpseo_replace_vars' ) ) {
				if ( '' !== $title_template ) {
					$default_title = wpseo_replace_vars( $title_template, $post );
				}
				if ( '' !== $desc_template ) {
					$default_desc = wpseo_replace_vars( $desc_template, $post );
				}
			}
		}
		$permalink = get_permalink( $post );
		$sec       = ' data-ecdv2-sec="yoast_seo"';

		echo '<div class="ecdv2-grid">';

		echo '<div class="ecdv2-field ecdv2-field-full ecdv2-yoast-preview" id="ecdv2_yoast_preview" data-default-title="' . esc_attr( $default_title ) . '" data-default-desc="' . esc_attr( $default_desc ) . '">';
		echo '<span class="ecdv2-yoast-preview-label">' . esc_html__( 'Search result preview', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-yoast-preview-url">' . esc_html( $permalink ? $permalink : '' ) . '</span>';
		echo '<span class="ecdv2-yoast-preview-title" id="ecdv2_yoast_preview_title">' . esc_html( '' !== $title && function_exists( 'wpseo_replace_vars' ) ? wpseo_replace_vars( $title, $post ) : ( '' !== $title ? $title : $default_title ) ) . '</span>';
		echo '<span class="ecdv2-yoast-preview-desc" id="ecdv2_yoast_preview_desc">' . esc_html( '' !== $metadesc ? $metadesc : $default_desc ) . '</span>';
		echo '</div>';

		echo '<div class="ecdv2-field ecdv2-field-full">';
		echo '<label class="ecdv2-label" for="ecdv2_yoast_title">' . esc_html__( 'SEO title', 'wp-easycart' ) . '</label>';
		echo '<input type="text" id="ecdv2_yoast_title" name="ecdv2_yoast_title" value="' . esc_attr( $title ) . '" placeholder="' . esc_attr( $default_title ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is a static attribute literal.
		echo '<span class="ecdv2-char-count" data-ecdv2-count-for="ecdv2_yoast_title" data-ecdv2-count-max="60"></span>';
		echo '<span class="ecdv2-field-desc">' . esc_html__( 'The headline shown in search results. Leave it empty to use your Yoast title template. Yoast variables such as %%title%% and %%sitename%% work here.', 'wp-easycart' ) . '</span>';
		echo '</div>';

		echo '<div class="ecdv2-field ecdv2-field-full">';
		echo '<label class="ecdv2-label" for="ecdv2_yoast_metadesc">' . esc_html__( 'Meta description', 'wp-easycart' ) . '</label>';
		echo '<textarea id="ecdv2_yoast_metadesc" name="ecdv2_yoast_metadesc" rows="3" placeholder="' . esc_attr( $default_desc ) . '"' . $sec . '>' . esc_textarea( $metadesc ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is a static attribute literal.
		echo '<span class="ecdv2-char-count" data-ecdv2-count-for="ecdv2_yoast_metadesc" data-ecdv2-count-max="156"></span>';
		echo '<span class="ecdv2-field-desc">' . esc_html__( 'The summary under the headline in search results. Around 120 to 156 characters reads best.', 'wp-easycart' ) . '</span>';
		echo '</div>';

		echo '<div class="ecdv2-field">';
		echo '<label class="ecdv2-label" for="ecdv2_yoast_focuskw">' . esc_html__( 'Focus keyphrase', 'wp-easycart' ) . '</label>';
		echo '<input type="text" id="ecdv2_yoast_focuskw" name="ecdv2_yoast_focuskw" value="' . esc_attr( $focuskw ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is a static attribute literal.
		echo '<span class="ecdv2-field-desc">' . esc_html__( 'The search phrase this product should rank for. Yoast scores the page against it.', 'wp-easycart' ) . '</span>';
		echo '</div>';

		echo '<div class="ecdv2-field">';
		echo '<label class="ecdv2-label" for="ecdv2_yoast_noindex">' . esc_html__( 'Show in search results', 'wp-easycart' ) . '</label>';
		echo '<select id="ecdv2_yoast_noindex" name="ecdv2_yoast_noindex"' . $sec . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is a static attribute literal.
		echo '<option value="0"' . selected( '0', $noindex, false ) . '>' . esc_html( $type_noindex ? __( 'Default for products (No)', 'wp-easycart' ) : __( 'Default for products (Yes)', 'wp-easycart' ) ) . '</option>';
		echo '<option value="2"' . selected( '2', $noindex, false ) . '>' . esc_html__( 'Yes', 'wp-easycart' ) . '</option>';
		echo '<option value="1"' . selected( '1', $noindex, false ) . '>' . esc_html__( 'No, hide this product from search engines', 'wp-easycart' ) . '</option>';
		echo '</select>';
		echo '</div>';

		echo '<div class="ecdv2-field ecdv2-field-full">';
		echo '<label class="ecdv2-label" for="ecdv2_yoast_canonical">' . esc_html__( 'Canonical URL', 'wp-easycart' ) . '</label>';
		echo '<input type="url" id="ecdv2_yoast_canonical" name="ecdv2_yoast_canonical" value="' . esc_attr( $canonical ) . '" placeholder="' . esc_attr( $permalink ? $permalink : '' ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is a static attribute literal.
		echo '<span class="ecdv2-field-desc">' . esc_html__( 'Only set this when the same product lives at another address that search engines should treat as the original.', 'wp-easycart' ) . '</span>';
		echo '</div>';

		echo '<div class="ecdv2-field ecdv2-field-full ecdv2-yoast-foot">';
		echo '<span class="dashicons dashicons-info-outline"></span><span>' . esc_html__( 'Readability analysis, schema and social previews stay in the WordPress editor.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the product page in the editor', 'wp-easycart' ) . '</a>. ' . esc_html__( 'Keep the product shortcode in the page content intact.', 'wp-easycart' ) . '</span>';
		echo '</div>';

		echo '</div>';
		$this->section_close();
	}

	/**
	 * Whether the card being printed shows a visible field ( section_open()'s only / except ).
	 *
	 * @since 6.0.4
	 * @param string $name Field name.
	 * @return bool
	 */
	public function prints_field( $name ) {
		if ( false !== $this->print_only && ! in_array( $name, (array) $this->print_only, true ) ) {
			return false;
		}
		return ! in_array( $name, (array) $this->print_except, true );
	}

	public function print_field_v2( $field ) {
		$name = isset( $field['name'] ) ? $field['name'] : ( isset( $field['alt_name'] ) ? $field['alt_name'] : '' );
		if ( '' === $name ) {
			return;
		}
		/* Customer reviews live on their own tab ( print_reviews_v2 ). Print one hidden carrier the first time the general
		 * options fields are rendered, whatever the section's only/except lists say, so the general-options save still posts the flag. */
		if ( 'use_customer_reviews' === $name ) {
			static $carrier_done = false;
			if ( ! $carrier_done ) { $carrier_done = true; echo '<input type="hidden" name="use_customer_reviews" id="use_customer_reviews" value="' . ( (int) $this->field_value( $field ) ? 1 : 0 ) . '" />'; }
			return;
		}
		if ( false !== $this->print_only && ! in_array( $name, $this->print_only, true ) && 'hidden' !== $field['type'] ) {
			return;
		}
		if ( in_array( $name, $this->print_except, true ) && 'hidden' !== $field['type'] ) {
			return;
		}
		if ( 'hidden' === $field['type'] && ! $this->print_hidden ) {
			return;
		}

		$type = $field['type'];
		$value = $this->field_value( $field );
		$label = isset( $field['label'] ) ? $field['label'] : '';
		if ( isset( $this->label_overrides[ $name ] ) ) {
			$label = $this->label_overrides[ $name ];
		}
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
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
			return;
		}

		/* Locked PRO field: keep a hidden carrier input so save payloads stay complete. */
		if ( $this->field_is_gated( $field ) ) {
			$carrier = ( 'checkbox' === $type && ! $value ) ? '' : $value;
			/* 6.0.4: a locked type switch ( preorder, restaurant ) shows only while the type is Custom, as the others. */
			$gated_type_row = ( $this->id && self::is_type_switch_v2( $name ) ) ? ' ecdv2-type-switch' : '';
			echo '<div class="ecdv2-field ecdv2-field-full' . esc_attr( $gated_type_row ) . '"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( is_array( $value ) ? wp_json_encode( $value ) : $carrier ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
			$this->gate_row( $label, apply_filters( 'wp_easycart_admin_v2_gate_description', sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Unlock this feature with %s.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ), $name ) );
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
				/* 6.0.4: a type switch's row is the Product type choice's; it shows only while the type is Custom. */
				$type_row = ( $this->id && self::is_type_switch_v2( $name ) ) ? ' ecdv2-type-switch' : '';
				echo '<div class="ecdv2-field ecdv2-field-full ecdv2-toggle-row' . esc_attr( $type_row ) . '"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<span class="ecdv2-toggle"><input type="checkbox" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="1"' . checked( 1, (int) $value, false ) . $sec . $show_attr . $onclick . $onchange . ' /><span class="ecdv2-toggle-track"></span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec, $show_attr, $onclick and $onchange are built from esc_attr() above.
				echo '<span class="ecdv2-toggle-meta"><label class="ecdv2-toggle-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
				if ( '' !== $desc ) {
					echo '<div class="ecdv2-toggle-desc">' . esc_html( $desc ) . '</div>';
				}
				echo '</span></div>';
				break;

			case 'select':
				$multiple = ( isset( $field['multiple'] ) && $field['multiple'] ) ? ' multiple="multiple"' : '';
				$select2 = ( isset( $field['select2'] ) && 'none' !== $field['select2'] ) ? ' ecdv2-select2' : '';
				if ( ! empty( $field['ajax'] ) ) {
					$select2 = ' ecdv2-select2-ajax';
					$onchange .= ' data-ecdv2-ajax="' . esc_attr( $field['ajax'] ) . '" data-ecdv2-exclude="' . esc_attr( (int) $this->product->product_id ) . '"';
				}
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $required is a static markup literal.
				echo '<select name="' . esc_attr( $name ) . ( $multiple ? '[]' : '' ) . '" id="' . esc_attr( $name ) . '" class="' . esc_attr( trim( $select2 ) ) . '"' . $multiple . $sec . $onchange . $validation . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $multiple is a static literal; $sec, $onchange and $validation are built from esc_attr() above.
				if ( isset( $field['data_label'] ) ) {
					$dl_selected = ( isset( $field['default_selected'] ) && $field['default_selected'] ) ? ' selected' : '';
					$dl_value = isset( $field['default_value'] ) ? $field['default_value'] : '0';
					echo '<option value="' . esc_attr( $dl_value ) . '"' . $dl_selected . '>' . esc_html( $field['data_label'] ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $dl_selected is a static literal.
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
						echo '<option value="' . esc_attr( $option->id ) . '"' . $selected . $option_attrs . '>' . esc_html( $option->value ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $selected and $option_attrs are static literals.
					}
				}
				echo '</select>' . $msg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $msg is built from wp_kses_post() above.
				break;

			case 'currency':
				$formatted = ( '' === $value || null === $value ) ? '' : number_format( (float) $value, $this->currency_decimals(), '.', '' );
				$desc_html = ( isset( $field['description'] ) && '' !== $field['description'] ) ? '<span class="ecdv2-field-desc">' . esc_html( $field['description'] ) . '</span>' : '';
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $required is a static markup literal.
				echo '<span class="ecdv2-currency-wrap"><span class="ecdv2-currency-symbol">' . esc_html( apply_filters( 'wp_easycart_admin_currency_symbol', $this->currency_symbol() ) ) . '</span>';
				echo '<input type="number" step="' . esc_attr( $this->currency_step() ) . '" min="0" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $formatted ) . '"' . $sec . $placeholder . $validation . $onchange . ' /></span>' . $desc_html . $msg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec, $placeholder, $validation and $onchange are built from esc_attr(), $desc_html from esc_html() and $msg from wp_kses_post() above.
				break;

			case 'number':
				$step = isset( $field['step'] ) ? $field['step'] : 'any';
				/* 6.0.4: weight and size say their unit ( the store's, as the packer and the carriers read them ). */
				$unit = '';
				if ( 'packaging' === $this->current_section && class_exists( 'wp_easycart_packages' ) ) {
					$units = wp_easycart_packages::units();
					$unit  = ( 'weight' === $name ) ? $units['weight'] : ( in_array( $name, array( 'width', 'height', 'length' ), true ) ? $units['dim'] : '' );
				}
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . ( '' !== $unit ? ' <span class="ecdv2-label-hint">' . esc_html( $unit ) . '</span>' : '' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $required is a static markup literal; the unit is escaped.
				echo '<input type="number" step="' . esc_attr( $step ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . $placeholder . $validation . $onchange . ' />' . $msg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec, $placeholder, $validation and $onchange are built from esc_attr() and $msg from wp_kses_post() above.
				break;

			case 'date':
				$date_min = isset( $field['min'] ) ? ' min="' . esc_attr( $field['min'] ) . '"' : '';
				$date_max = isset( $field['max'] ) ? ' max="' . esc_attr( $field['max'] ) . '"' : '';
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $required is a static markup literal.
				echo '<input type="date" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $date_min . $date_max . $sec . ' />' . $msg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $date_min, $date_max and $sec are built from esc_attr() and $msg from wp_kses_post() above.
				break;

			case 'color':
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
				echo '<span class="ecdv2-color-wrap"><input type="color" value="' . esc_attr( '' !== $value ? $value : '#000000' ) . '" data-ecdv2-color-for="' . esc_attr( $name ) . '" /><input type="text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . $placeholder . ' /></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec and $placeholder are built from esc_attr() above.
				break;

			case 'textarea':
				echo '<div class="ecdv2-field ecdv2-field-full"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $required is a static markup literal.
				echo '<textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '"' . $sec . $placeholder . $validation . '>' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec, $placeholder and $validation are built from esc_attr() above.
				if ( 'seo_description' === $name ) {
					echo '<span class="ecdv2-char-count" data-ecdv2-count-for="seo_description" data-ecdv2-count-max="160"></span>';
				}
				echo $msg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $msg is built from wp_kses_post() above.
				break;

			case 'wp_textarea':
				echo '<div class="ecdv2-field ecdv2-field-full" data-ecdv2-wpeditor="' . esc_attr( $name ) . '"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . ' data-ecdv2-sec-editor="' . esc_attr( $this->current_section ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				if ( '' !== $label ) {
					echo '<label class="ecdv2-label">' . esc_html( $label ) . '</label>';
				}
				wp_editor( $value, $name, array( 'textarea_rows' => 8, 'media_buttons' => true ) );
				echo '</div>';
				break;

			case 'manufacturer':
				global $wpdb;
				/*
				 * Search-as-you-type picker ( ecv2_manufacturer_search, 40 per page ). Only the
				 * current manufacturer is preloaded so the editor stays light with thousands of brands.
				 *
				 * @since 6.0.0
				 */
				$current_manufacturer_id   = (int) $this->product->manufacturer_id;
				$current_manufacturer_name = $current_manufacturer_id ? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ec_manufacturer WHERE manufacturer_id = %d', $current_manufacturer_id ) ) : '';
				echo '<div class="ecdv2-field">';
				echo '<label class="ecdv2-label" for="manufacturer_id">' . esc_html( $label ) . '</label>';
				echo '<select name="manufacturer_id" id="manufacturer_id" class="ecdv2-select2-ajax" data-ecdv2-ajax="manufacturers" data-ecdv2-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-manufacturer-search' ) ) . '"' . $sec . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
				echo '<option value="0">' . esc_html__( 'No Manufacturer', 'wp-easycart' ) . '</option>';
				if ( $current_manufacturer_id && '' !== $current_manufacturer_name ) {
					echo '<option value="' . esc_attr( $current_manufacturer_id ) . '" selected>' . esc_html( $current_manufacturer_name ) . '</option>';
				}
				echo '</select>';
				/* 6.0.4: making a manufacturer is one click away, not a row of its own under the picker. */
				echo '<button type="button" class="ecdv2-inline-link" id="ecdv2_manufacturer_new_toggle" aria-expanded="false" aria-controls="ecdv2_manufacturer_new" onclick="ecdv2.manufacturer_new( true );"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'New manufacturer', 'wp-easycart' ) . '</button>';
				echo '<div class="ecdv2-manufacturer-new" id="ecdv2_manufacturer_new" hidden>';
				/* Nonce ACTION must match the server's verify_access() in
				 * ec_admin_ajax_product_details_insert_manufacturer — it checks
				 * 'wp-easycart-manufacturer-details'. A mismatched action here
				 * made every Create attempt fail verification and return "0". */
				wp_easycart_admin_verification()->print_nonce_field( 'manufacturer_new_nonce', 'wp-easycart-manufacturer-details' );
				echo '<label class="screen-reader-text" for="manufacturer_name">' . esc_html__( 'New manufacturer name', 'wp-easycart' ) . '</label>';
				echo '<input type="text" id="manufacturer_name" placeholder="' . esc_attr__( 'New manufacturer name', 'wp-easycart' ) . '" />';
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" onclick="return ec_admin_product_details_add_new_manufacturer( );">' . esc_html__( 'Create', 'wp-easycart' ) . '</button>';
				echo '<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecdv2.manufacturer_new( false );">' . esc_html__( 'Cancel', 'wp-easycart' ) . '</button>';
				echo '</div></div>';
				break;

			case 'image_upload':
			case 'image_preview':
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
				echo '<div style="display:flex; gap:8px;">';
				echo '<input type="text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" style="flex:1;"' . $sec . $placeholder . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec and $placeholder are built from esc_attr() above.
				if ( 'image_upload' === $type ) {
					// Image fields filter the library to images; other uploads ( downloads ) stay unfiltered.
					$is_image_field = ( 0 === strpos( $name, 'image' ) || false !== strpos( $name, '_image' ) || false !== strpos( $name, 'swatch' ) );
					echo '<input type="button" value="' . esc_attr( $is_image_field ? __( 'Select Image', 'wp-easycart' ) : __( 'Select File', 'wp-easycart' ) ) . '" onclick="ecdv2.media_pick( \'' . esc_attr( $name ) . '\', false' . ( $is_image_field ? ', \'image\'' : '' ) . ' ); return false;" />';
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
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
				echo '<input type="button" value="' . esc_attr__( 'Choose Image', 'wp-easycart' ) . '" onclick="ecdv2.media_pick( \'' . esc_attr( $name ) . '\', true, \'image\' ); return false;" />';
				echo '<input type="button" value="' . esc_attr__( 'Remove', 'wp-easycart' ) . '" onclick="ecdv2.media_clear( \'' . esc_attr( $name ) . '\' ); return false;" />';
				echo '</div></div>';
				break;

			case 'subscription_interval':
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<label class="ecdv2-label">' . esc_html( $label ) . '</label>';
				echo '<div style="display:flex; gap:8px;">';
				echo '<select name="subscription_bill_length" id="subscription_bill_length"' . $sec . ' style="flex:0 0 90px;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
				for ( $i = 1; $i <= 12; $i++ ) {
					echo '<option value="' . esc_attr( $i ) . '"' . selected( $i, (int) $this->product->subscription_bill_length, false ) . '>' . esc_html( $i ) . '</option>';
				}
				echo '</select>';
				$periods = array( 'D' => __( 'Day(s)', 'wp-easycart' ), 'W' => __( 'Week(s)', 'wp-easycart' ), 'M' => __( 'Month(s)', 'wp-easycart' ), 'Y' => __( 'Year(s)', 'wp-easycart' ) );
				echo '<select name="subscription_bill_period" id="subscription_bill_period"' . $sec . ' style="flex:1;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec is built from esc_attr() above.
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
				echo '<div class="ecdv2-field"' . $deps . ( $hidden_dep ? ' style="display:none;"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $deps is built from esc_attr() in dep_attrs().
				echo '<label class="ecdv2-label" for="' . esc_attr( $name ) . '">' . esc_html( $label ) . $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $required is a static markup literal.
				if ( $is_slug ) {
					echo ' <span class="ecdv2-label-hint">' . esc_html__( '( follows the title )', 'wp-easycart' ) . '</span>';
				}
				echo '</label>';
				if ( $is_slug ) {
					echo '<div class="ecdv2-slug-wrap">';
				}
				echo '<input type="text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $sec . $placeholder . $validation . $onchange . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sec, $placeholder, $validation and $onchange are built from esc_attr() above.
				if ( $is_slug ) {
					echo '<button type="button" class="ecdv2-slug-lock" id="ecdv2_slug_lock" title="' . esc_attr__( 'Lock slug (stop auto-generating from title)', 'wp-easycart' ) . '" onclick="ecdv2.slug_lock_toggle(); return false;"><span class="dashicons dashicons-unlock" style="font-size:15px;width:15px;height:15px;"></span></button></div>';
				}
				echo $msg . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $msg is built from wp_kses_post() above.
				break;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Complex field types                                                  */
	/* ------------------------------------------------------------------ */

	private function print_categories_v2() {
		$assigned = array();
		$smart_ids = array();
		foreach ( (array) $this->categories as $category ) {
			$assigned[ $category->category_id ] = $category->category_name;
			if ( ! empty( $category->is_smart ) ) { $smart_ids[ (int) $category->category_id ] = 1; }
		}
		echo '<div class="ecdv2-field ecdv2-field-full">';
		echo '<div class="ecdv2-cat-tokens" id="ecdv2_cat_tokens">';
		if ( empty( $assigned ) ) {
			echo '<span class="ecdv2-cat-empty" id="ecdv2_cat_empty">' . esc_html__( 'No categories assigned yet.', 'wp-easycart' ) . '</span>';
		}
		foreach ( $assigned as $cat_id => $cat_name ) {
			echo '<span class="ecdv2-cat-token' . ( isset( $smart_ids[ (int) $cat_id ] ) ? ' is-smart' : '' ) . '" data-category-id="' . esc_attr( $cat_id ) . '"' . ( isset( $smart_ids[ (int) $cat_id ] ) ? ' data-smart="1" title="' . esc_attr__( 'Added by this category’s rules. Removing it here excludes the product from the rules.', 'wp-easycart' ) . '"' : '' ) . '>' . esc_html( $cat_name ) . ( isset( $smart_ids[ (int) $cat_id ] ) ? ' <em class="ecdv2-cat-auto">' . esc_html__( 'rule', 'wp-easycart' ) . '</em>' : '' ) . '<button type="button" onclick="ecdv2.category_remove( ' . (int) $cat_id . ' );" aria-label="' . esc_attr__( 'Remove category', 'wp-easycart' ) . '">&times;</button></span>';
		}
		echo '</div>';
		/*
		 * Unified add/create combobox: type to search existing categories or
		 * create a new one inline. products-details-v2.js queries
		 * ecv2_category_search ( 20 matches per request ) as you type; only the
		 * assigned categories are printed, never the whole table.
		 *
		 * @since 6.0.0
		 */
		echo '<div class="ecdv2-cat-add">';
		echo '<div class="ecdv2-cat-combo" id="ecdv2_cat_combo" data-ecdv2-nodirty="1" data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-category-search' ) ) . '" data-product-id="' . esc_attr( (int) $this->id ) . '">';
		echo '<input type="text" id="ecdv2_cat_input" class="ecv2-input" autocomplete="off" placeholder="' . esc_attr__( 'Type to add a category — or create a new one…', 'wp-easycart' ) . '" />';
		echo '<div class="ecdv2-cat-menu" id="ecdv2_cat_menu" style="display:none;"></div>';
		echo '</div>';
		echo '<a href="admin.php?page=wp-easycart-products&subpage=category" target="_blank" class="ecv2-btn ecdv2-cat-manage">' . esc_html__( 'Manage', 'wp-easycart' ) . ' <span class="dashicons dashicons-external"></span></a>';
		echo '</div>';
		echo '</div>';
	}

	private function print_tier_pricing_v2( $label ) {
		global $wpdb;
		$pro_enabled = $this->gate_row(
			$label ? $label : __( 'Volume Pricing', 'wp-easycart' ),
			__( 'Reward larger orders with automatic quantity-based discounts. Set price breaks like 10+ for $9.99, 50+ for $8.49.', 'wp-easycart' ),
			array( 'min_version' => wp_easycart_admin_pro_gate::MIN_PRO_VERSION ) /* 6.0.2: the same PRO versions its saves accept ( price_rules() ) */
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
		echo '<div class="ecdv2-tier-add" data-ecdv2-nodirty="1">'; /* 6.0.4: typing a new tier changes nothing until Add Tier */
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
			__( 'Offer wholesale or member pricing by user role. Each role can see its own price for this product.', 'wp-easycart' ),
			array( 'min_version' => wp_easycart_admin_pro_gate::MIN_PRO_VERSION ) /* 6.0.2: the same PRO versions its saves accept ( price_rules() ) */
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
		echo '<div class="ecdv2-tier-add ecdv2-role-add" data-ecdv2-nodirty="1">';
		echo '<span class="ecdv2-tier-sentence">';
		echo '<select id="add_new_role_price_role">';
		/* 6.0.2: nothing is picked until the merchant picks a role ( the first role, admin, was chosen silently ). */
		echo '<option value="">' . esc_html__( 'Choose a role', 'wp-easycart' ) . '</option>';
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
		$h  = '<div class="ec_admin_role_price_row" id="' . esc_attr( $rid ) . '" data-role="' . esc_attr( $rp->role_label ) . '" data-price="' . esc_attr( (float) $rp->role_price ) . '">';
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
			array( 'key' => 'price', 'label' => __( 'Price set', 'wp-easycart' ), 'done' => ( (float) $p->price > 0 || $p->is_donation || $p->inquiry_mode ), 'tab' => 'pricing' ),
			array( 'key' => 'category', 'label' => __( 'Assigned to a category', 'wp-easycart' ), 'done' => ( $category_count > 0 ), 'tab' => 'organize' ),
			array( 'key' => 'seo', 'label' => __( 'SEO description added', 'wp-easycart' ), 'done' => ( '' !== trim( (string) $p->seo_description ) ), 'tab' => 'seo' ),
			array( 'key' => 'weight', 'label' => __( 'Shipping weight entered', 'wp-easycart' ), 'done' => ( ! $p->is_shippable || (float) $p->weight > 0 ), 'tab' => 'shipping' ),
			array( 'key' => 'stock', 'label' => __( 'Stock level or tracking decided', 'wp-easycart' ), 'done' => ( ! $p->show_stock_quantity || (int) $p->stock_quantity > 0 || $p->use_optionitem_quantity_tracking ), 'tab' => 'inventory' ),
			array( 'key' => 'active', 'label' => __( 'Product activated in store', 'wp-easycart' ), 'done' => (bool) $p->activate_in_store, 'tab' => 'general' ),
		);
		/* 6.0.2: a subscription can only be bought while Stripe takes the store's card payments ( the notice and its link
		   are under the Subscription Product switch on Type & Behavior ). */
		if ( ! empty( $p->is_subscription_item ) && class_exists( 'wp_easycart_subscription_gateway' ) ) {
			$checks[] = array( 'key' => 'subscription_gateway', 'label' => __( 'Stripe set up to bill this subscription', 'wp-easycart' ), 'done' => wp_easycart_subscription_gateway::ready(), 'tab' => 'behavior' );
		}
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

	/* ------------------------------------------------------------------ */
	/* 6.0.4: the summary column, and the view's settings for the script    */
	/* ------------------------------------------------------------------ */

	/**
	 * The product type switches ( Type & Behavior ) and what each one makes the product, in the order the summary names
	 * them. A product with none is a standard product.
	 *
	 * @since 6.0.4
	 * @return array input id => label.
	 */
	public static function type_labels_v2() {
		return apply_filters(
			'wp_easycart_admin_product_type_labels',
			array(
				'is_subscription_item' => __( 'Subscription', 'wp-easycart' ),
				'is_download'          => __( 'Digital download', 'wp-easycart' ),
				'is_giftcard'          => __( 'Gift card', 'wp-easycart' ),
				'is_donation'          => __( 'Donation or invoice', 'wp-easycart' ),
				'inquiry_mode'         => __( 'Inquiry only', 'wp-easycart' ),
				'catalog_mode'         => __( 'Seasonal ( not for sale now )', 'wp-easycart' ),
				'is_preorder_type'     => __( 'Preorder for pickup', 'wp-easycart' ),
				'is_restaurant_type'   => __( 'Restaurant pickup', 'wp-easycart' ),
				'is_deconetwork'       => __( 'Deconetwork', 'wp-easycart' ),
			)
		);
	}

	/**
	 * The product types the summary column's Product type choice offers ( 6.0.4, editor plan Phase 2 ), in its order. A
	 * type writes the switches the Type settings section has always had: its flag on and every other type's flag off, and
	 * for the types that say so, whether the product ships. Physical and Service set no flag; the switch Ships decides
	 * between them. A product with more than one flag on is Custom ( every switch shows ). Types marked pro are the ones
	 * Quick add offers only with WP EasyCart PRO.
	 *
	 * The list lives in wp_easycart_admin_product_types, which the products list's Product type filter reads too.
	 *
	 * @since 6.0.4
	 * @return array key => label, flag ( '' = none ), ships ( 1 | 0 | null = leave it ), pro, help, icon.
	 */
	public static function product_types_v2() {
		return wp_easycart_admin_product_types::types();
	}

	/**
	 * The type this product is now ( product_types_v2() key, or custom ).
	 *
	 * @since 6.0.4
	 * @return string
	 */
	public function product_type_key_v2() {
		return wp_easycart_admin_product_types::key_for( $this->product );
	}

	/**
	 * Whether a type switch's row in Type settings belongs to the Product type choice ( hidden there unless the type is Custom ).
	 *
	 * @since 6.0.4
	 * @param string $name Field name.
	 * @return bool
	 */
	public static function is_type_switch_v2( $name ) {
		static $flags = null;
		if ( null === $flags ) {
			$flags = array();
			foreach ( self::product_types_v2() as $type ) {
				if ( '' !== $type['flag'] ) {
					$flags[] = $type['flag'];
				}
			}
		}
		return in_array( $name, $flags, true );
	}

	/**
	 * What the product is, in a few words ( the summary column's Product type card ).
	 *
	 * @since 6.0.4
	 * @return string
	 */
	public function product_type_text_v2() {
		$names = array();
		foreach ( self::type_labels_v2() as $flag => $label ) {
			if ( ! empty( $this->product->{$flag} ) ) {
				$names[] = $label;
			}
		}
		return $names ? implode( ' + ', $names ) : __( 'Standard product', 'wp-easycart' );
	}

	/**
	 * The summary column beside the editor ( 6.0.4 ): visibility and product type as summaries with a way to their fields,
	 * the real Categories card ( it saves as it changes, so it can live anywhere ), listing health, the product card as
	 * shoppers see it, and sales. products-details-v2.js keeps the summaries current as fields change.
	 *
	 * @since 6.0.4
	 * @param array $args card_image ( the main picture's address ).
	 */
	public function print_side_v2( $args = array() ) {
		global $wpdb;
		$p          = $this->product;
		$card_image = isset( $args['card_image'] ) ? (string) $args['card_image'] : '';

		echo '<aside class="ecdv2-side" id="ecdv2_side" aria-label="' . esc_attr__( 'Product summary', 'wp-easycart' ) . '">';
		/* 6.0.4: below 960px of the editor's width the column is a drawer ( products-details-v2.js side_drawer() ), with this
		 * heading and a close button; the header's Summary button opens it. Hidden while the column sits beside the fields. */
		echo '<div class="ecdv2-side-head" id="ecdv2_side_head"><div class="ecdv2-side-head-text"><h2 class="ecdv2-side-head-title" id="ecdv2_side_title">' . esc_html__( 'Summary', 'wp-easycart' ) . '</h2>';
		echo '<span class="ecdv2-side-head-hint">' . esc_html__( 'Status, type, shipping, tax, categories and listing health', 'wp-easycart' ) . '</span></div>';
		echo '<button type="button" class="ecdv2-side-close" id="ecdv2_side_close" aria-label="' . esc_attr__( 'Close the summary', 'wp-easycart' ) . '"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>';

		/* 6.0.4: Status, as the editor plan's mockup drew it: the Active switch ( it saves at once, ecdv2.quick_activate(), so it
		 * left the header ), who can see it ( role_id, the general options section ) and the page address ( post_slug, the basic
		 * section ). Each field is a header-less card of its own section, so it saves as before. */
		$active = (bool) $p->activate_in_store;
		echo '<div class="ecdv2-card ecdv2-side-card ecdv2-side-status" id="ecdv2_side_visibility">';
		echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Status', 'wp-easycart' ) . '</h3></div>';
		echo '<div class="ecdv2-card-body">';
		echo '<label class="ecdv2-side-switch" for="activate_in_store" title="' . esc_attr__( 'Shows or hides the product in your store. Saves at once; your other changes wait for Save.', 'wp-easycart' ) . '">';
		echo '<span class="ecdv2-toggle"><input type="checkbox" id="activate_in_store" name="activate_in_store" value="1"' . checked( $active, true, false ) . ' data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-toggle-' . (int) $p->product_id ) ) . '" onchange="ecdv2.quick_activate( this );" /><span class="ecdv2-toggle-track"></span></span>';
		echo '<span class="ecdv2-side-switch-text"><b id="ecdv2_side_status_title">' . ( $active ? esc_html__( 'Active', 'wp-easycart' ) : esc_html__( 'Draft', 'wp-easycart' ) ) . '</b>';
		echo '<span id="ecdv2_side_status">' . ( $active ? esc_html__( 'Shoppers can find and buy it', 'wp-easycart' ) : esc_html__( 'Hidden from your store until you switch it on', 'wp-easycart' ) ) . '</span></span>';
		echo '</label>';
		$this->label_overrides['role_id'] = __( 'Who can see it', 'wp-easycart' );
		$this->section_open( 'general_options_visibility', __( 'Who can see it', 'wp-easycart' ), '', array( 'only' => array( 'role_id' ), 'hidden' => false, 'heading' => false, 'class' => 'ecdv2-side-part' ) );
		do_action( 'wp_easycart_admin_product_details_general_options_fields' );
		$this->section_close();
		$this->label_overrides['post_slug'] = __( 'Page address', 'wp-easycart' );
		$this->section_open( 'basic', __( 'Page address', 'wp-easycart' ), '', array( 'only' => array( 'post_slug' ), 'hidden' => false, 'heading' => false, 'class' => 'ecdv2-side-part' ) );
		do_action( 'wp_easycart_admin_product_details_basic_fields' );
		$this->section_close();
		echo '</div></div>';

		/* Product type ( 6.0.4, editor plan Phase 2 ): one choice that writes the type switches; the sections a type does not
		 * use move to the rail's "Not used for this type" ( products-details-v2.js ). Not part of any save section: the
		 * switches it sets mark their own sections unsaved. */
		$types   = self::product_types_v2();
		$current = $this->product_type_key_v2();
		$is_pro  = ( '' === apply_filters( 'wp_easycart_admin_lock_icon', 'locked' ) );
		echo '<div class="ecdv2-card ecdv2-side-card" id="ecdv2_side_type_card">';
		echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><label for="ecdv2_type">' . esc_html__( 'Product type', 'wp-easycart' ) . '</label></h3>';
		echo '<button type="button" class="ecdv2-side-link" data-ecdv2-go="behavior">' . esc_html__( 'Settings', 'wp-easycart' ) . '</button></div>';
		echo '<div class="ecdv2-card-body">';
		echo '<select class="ecdv2-side-select" id="ecdv2_type" aria-describedby="ecdv2_type_help">';
		foreach ( $types as $key => $type ) {
			$locked = ! empty( $type['pro'] ) && ! $is_pro && $key !== $current;
			/* translators: 1: a product type, 2: the plan that includes it ( Pro ). */
			$text = $locked && class_exists( 'wp_easycart_admin_edition' ) ? sprintf( __( '%1$s ( %2$s )', 'wp-easycart' ), $type['label'], wp_easycart_admin_edition::badge( 'pro' ) ) : $type['label'];
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . ( $locked ? ' disabled' : '' ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '<option value="custom"' . selected( $current, 'custom', false ) . '>' . esc_html__( 'Custom: every type switch', 'wp-easycart' ) . '</option>';
		echo '</select>';
		$help = 'custom' === $current ? __( 'This product mixes types, so every type switch shows in Type settings.', 'wp-easycart' ) : ( isset( $types[ $current ]['help'] ) ? $types[ $current ]['help'] : '' );
		echo '<p class="ecdv2-side-note" id="ecdv2_type_help">' . esc_html( $help ) . '</p>';
		if ( ! $is_pro && class_exists( 'wp_easycart_admin_edition' ) ) {
			/* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */
			echo '<p class="ecdv2-side-note ecdv2-side-upsell"><button type="button" class="ecdv2-side-link" onclick="ecdv2_upsell( { context: \'products\' } ); return false;">' . esc_html( sprintf( __( 'Downloads, subscriptions, gift cards and more are %s types.', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ) . '</button></p>';
		}
		echo '</div></div>';

		$this->print_ship_card_v2();
		$this->print_tax_card_v2();

		/* 6.0.4: Organization ( the mockup's ): the real Categories card ( section categories, moved here from Storefront ), with
		 * the manufacturer ( basic ) and Show on the store page ( general options ) as header-less cards of their own sections. */
		$this->section_open( 'categories', __( 'Organization', 'wp-easycart' ), '', array( 'class' => 'ecdv2-side-org' ) );
		echo '<span class="ecdv2-label ecdv2-side-label">' . esc_html__( 'Categories', 'wp-easycart' ) . '</span>';
		do_action( 'wp_easycart_admin_product_details_categories_fields' );
		$this->section_open( 'basic', __( 'Manufacturer', 'wp-easycart' ), '', array( 'only' => array( 'manufacturer_id' ), 'hidden' => false, 'heading' => false, 'class' => 'ecdv2-side-part' ) );
		do_action( 'wp_easycart_admin_product_details_basic_fields' );
		$this->section_close();
		$this->label_overrides['show_on_startup'] = __( 'Show on the store page', 'wp-easycart' );
		$this->section_open( 'general_options_visibility', __( 'Store page', 'wp-easycart' ), '', array( 'only' => array( 'show_on_startup' ), 'hidden' => false, 'heading' => false, 'class' => 'ecdv2-side-part' ) );
		do_action( 'wp_easycart_admin_product_details_general_options_fields' );
		$this->section_close();
		echo '</div></div>'; /* the Organization card */

		$this->print_health_v2();

		/* The product card as shoppers see it in a list. */
		$list_price = (float) $p->list_price;
		echo '<div class="ecdv2-card ecdv2-side-card ecdv2-side-preview" id="ecdv2_side_preview">';
		echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Storefront card', 'wp-easycart' ) . '</h3><span class="ecdv2-card-hint">' . esc_html__( 'Follows your changes', 'wp-easycart' ) . '</span></div>';
		echo '<div class="ecdv2-card-body"><div class="ecdv2-pv">';
		echo '<div class="ecdv2-pv-img" id="ecdv2_pv_img">';
		if ( '' !== $card_image ) {
			echo '<img src="' . esc_url( $card_image ) . '" alt="" loading="lazy" />';
		} else {
			echo '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>';
		}
		echo '<span class="ecdv2-pv-badge" id="ecdv2_pv_badge" hidden></span></div>';
		echo '<div class="ecdv2-pv-text"><span class="ecdv2-pv-title" id="ecdv2_pv_title">' . esc_html( wp_unslash( $p->title ) ) . '</span>';
		echo '<span class="ecdv2-pv-price"><span id="ecdv2_pv_price">' . esc_html( $this->format_price( $p->price ) ) . '</span> <s id="ecdv2_pv_was"' . ( ( $list_price > (float) $p->price ) ? '' : ' hidden' ) . '>' . esc_html( $this->format_price( $list_price ) ) . '</s></span>';
		echo '<span class="ecdv2-pv-stock" id="ecdv2_pv_stock" hidden></span></div>';
		echo '</div></div></div>';

		/* Sales ( the Activity endpoint, which needs a store manager ). */
		if ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) {
			echo '<div class="ecdv2-card ecdv2-side-card ecdv2-side-insights" id="ecdv2_side_insights">';
			echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Sales', 'wp-easycart' ) . '</h3><span class="ecdv2-card-hint">' . esc_html__( 'All time', 'wp-easycart' ) . '</span></div>';
			echo '<div class="ecdv2-card-body"><div class="ecdv2-side-kpis">';
			foreach ( array(
				'units'       => __( 'Units sold', 'wp-easycart' ),
				'revenue'     => __( 'Revenue', 'wp-easycart' ),
				'order_count' => __( 'Orders', 'wp-easycart' ),
				'views'       => __( 'Page views', 'wp-easycart' ),
			) as $kpi => $label ) {
				echo '<div><b data-ecdv2-kpi="' . esc_attr( $kpi ) . '">&ndash;</b><span>' . esc_html( $label ) . '</span></div>';
			}
			echo '</div><button type="button" class="ecv2-btn ecv2-btn-sm ecdv2-side-open-insights" data-ecdv2-mode-set="insights"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>' . esc_html__( 'Open Insights', 'wp-easycart' ) . '</button></div></div>';
		}

		echo '</aside>';
		/* 6.0.4: behind the drawer; a click on it closes the drawer. */
		echo '<div class="ecdv2-side-backdrop" id="ecdv2_side_backdrop" aria-hidden="true"></div>';
	}

	/**
	 * 6.0.4: the summary column's Shipping card, under Product type: whether the product ships, its weight and size, how it
	 * packs and what it adds, or why it does not ship; amber when it ships with no weight while the store's rates read
	 * weights. One button to the Shipping section. products-details-v2.js keeps it current ( paint_ship_card() ).
	 *
	 * @since 6.0.4
	 */
	public function print_ship_card_v2() {
		$p      = $this->product;
		$units  = class_exists( 'wp_easycart_packages' ) ? wp_easycart_packages::units() : array( 'weight' => 'lb', 'dim' => 'in' );
		$method = ( isset( $GLOBALS['ec_setting'] ) && is_object( $GLOBALS['ec_setting'] ) && method_exists( $GLOBALS['ec_setting'], 'get_shipping_method' ) ) ? (string) $GLOBALS['ec_setting']->get_shipping_method() : '';
		/* Rates that read a product's weight: by weight, live carrier rates and Fraktjakt. */
		$needs  = in_array( $method, array( 'weight', 'live', 'fraktjakt' ), true );
		$ships  = ! empty( $p->is_shippable );
		$weight = (float) $p->weight;
		echo '<div class="ecdv2-card ecdv2-side-card ecdv2-side-ship' . ( $ships ? '' : ' is-noship' ) . ( $ships && $needs && $weight <= 0 ? ' is-warn' : '' ) . '" id="ecdv2_side_shipping" data-needs-weight="' . ( $needs ? '1' : '0' ) . '" data-weight-unit="' . esc_attr( $units['weight'] ) . '" data-dim-unit="' . esc_attr( $units['dim'] ) . '">';
		echo '<button type="button" class="ecdv2-side-ship-go" data-ecdv2-go="shipping">';
		echo '<span class="ecdv2-side-ship-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg></span>';
		echo '<span class="ecdv2-side-ship-title" id="ecdv2_side_ship_title">' . ( $ships ? esc_html__( 'Ships to the customer', 'wp-easycart' ) : esc_html__( 'Doesn’t ship', 'wp-easycart' ) ) . '</span>';
		echo '<span class="ecdv2-side-link" aria-hidden="true">' . esc_html__( 'Edit', 'wp-easycart' ) . '</span>';
		echo '<span class="screen-reader-text">' . esc_html__( 'Open the Shipping section', 'wp-easycart' ) . '</span>';
		echo '</button>';
		echo '<dl class="ecdv2-side-ship-facts" id="ecdv2_side_ship_facts"' . ( $ships ? '' : ' hidden' ) . '>';
		/* translators: 1: a weight, 2: its unit ( lb or kg ). */
		echo '<dt>' . esc_html__( 'Weight', 'wp-easycart' ) . '</dt><dd id="ecdv2_side_ship_weight">' . ( $weight > 0 ? esc_html( sprintf( __( '%1$s %2$s', 'wp-easycart' ), rtrim( rtrim( number_format( $weight, 3, '.', '' ), '0' ), '.' ), $units['weight'] ) ) : esc_html__( 'Not set', 'wp-easycart' ) ) . '</dd>';
		echo '<dt>' . esc_html__( 'Size', 'wp-easycart' ) . '</dt><dd id="ecdv2_side_ship_size">&ndash;</dd>';
		echo '<dt>' . esc_html__( 'Packs in', 'wp-easycart' ) . '</dt><dd id="ecdv2_side_ship_packs">&ndash;</dd>';
		echo '<dt>' . esc_html__( 'Charges', 'wp-easycart' ) . '</dt><dd id="ecdv2_side_ship_charges">&ndash;</dd>';
		echo '</dl>';
		echo '<p class="ecdv2-side-ship-off" id="ecdv2_side_ship_off"' . ( $ships ? ' hidden' : '' ) . '>' . esc_html__( 'Checkout asks no shipping address and adds no shipping for it.', 'wp-easycart' ) . '</p>';
		echo '<p class="ecdv2-side-ship-warn" id="ecdv2_side_ship_warn"' . ( $ships && $needs && $weight <= 0 ? '' : ' hidden' ) . '>' . esc_html__( 'Add a weight: your shipping rates read it.', 'wp-easycart' ) . '</p>';
		echo '</div>';
	}

	/**
	 * 6.0.4: the summary column's Tax card, under Shipping: whether the product is taxable ( sales tax, VAT ), one button to
	 * the Tax card in Pricing. products-details-v2.js keeps it current ( paint_tax_card() ).
	 *
	 * @since 6.0.4
	 */
	public function print_tax_card_v2() {
		$p   = $this->product;
		$tax = 1 === (int) $p->is_taxable;
		$vat = (float) $p->vat_rate > 0;
		$sub = $tax && $vat ? __( 'Sales tax and VAT', 'wp-easycart' ) : ( $tax ? __( 'Sales tax', 'wp-easycart' ) : ( $vat ? __( 'VAT', 'wp-easycart' ) : __( 'No sales tax or VAT', 'wp-easycart' ) ) );
		echo '<div class="ecdv2-card ecdv2-side-card ecdv2-side-row ecdv2-side-tax' . ( $tax || $vat ? '' : ' is-off' ) . '" id="ecdv2_side_tax">';
		echo '<button type="button" class="ecdv2-side-row-go" data-ecdv2-go="pricing" data-ecdv2-go-section="tax">';
		echo '<span class="ecdv2-side-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5 5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/></svg></span>';
		echo '<span class="ecdv2-side-row-text"><b id="ecdv2_side_tax_title">' . ( $tax || $vat ? esc_html__( 'Taxable', 'wp-easycart' ) : esc_html__( 'Not taxable', 'wp-easycart' ) ) . '</b><span id="ecdv2_side_tax_sub">' . esc_html( $sub ) . '</span></span>';
		echo '<span class="ecdv2-side-link" aria-hidden="true">' . esc_html__( 'Edit', 'wp-easycart' ) . '</span>';
		echo '<span class="screen-reader-text">' . esc_html__( 'Open the Tax settings in Pricing', 'wp-easycart' ) . '</span>';
		echo '</button>';
		echo '</div>';
	}

	/**
	 * 6.0.4: under Basics' title and SKU, the price ( set in Pricing ) and the manufacturer ( in the summary column ), each a
	 * button to its field. products-details-v2.js keeps the values current ( paint_quick_links() ).
	 *
	 * @since 6.0.4
	 */
	public function print_quick_links_v2() {
		global $wpdb;
		$p        = $this->product;
		$maker_id = (int) $p->manufacturer_id;
		$maker    = $maker_id ? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ec_manufacturer WHERE manufacturer_id = %d', $maker_id ) ) : '';
		echo '<div class="ecdv2-quick-links" id="ecdv2_quick_links">';
		echo '<button type="button" class="ecdv2-quick-link" data-ecdv2-go="pricing" data-ecdv2-field="price" title="' . esc_attr__( 'Set the price in Pricing', 'wp-easycart' ) . '">';
		echo '<span class="dashicons dashicons-money-alt" aria-hidden="true"></span><span class="ecdv2-quick-link-label">' . esc_html__( 'Price', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-quick-link-value" id="ecdv2_quick_price">' . esc_html( $this->format_price( $p->price ) ) . '</span>';
		echo '<span class="ecdv2-quick-link-go" aria-hidden="true">' . esc_html__( 'Edit', 'wp-easycart' ) . '</span></button>';
		echo '<button type="button" class="ecdv2-quick-link" data-ecdv2-go="side" data-ecdv2-field="manufacturer_id" title="' . esc_attr__( 'Choose the manufacturer in the summary column', 'wp-easycart' ) . '">';
		echo '<span class="dashicons dashicons-building" aria-hidden="true"></span><span class="ecdv2-quick-link-label">' . esc_html__( 'Manufacturer', 'wp-easycart' ) . '</span>';
		echo '<span class="ecdv2-quick-link-value" id="ecdv2_quick_manufacturer">' . ( '' !== $maker ? esc_html( wp_unslash( $maker ) ) : esc_html__( 'None', 'wp-easycart' ) ) . '</span>';
		echo '<span class="ecdv2-quick-link-go" aria-hidden="true">' . esc_html__( 'Edit', 'wp-easycart' ) . '</span></button>';
		echo '</div>';
	}

	/**
	 * 6.0.4: this product's reviews, counted once for the Reviews section's heading and its card.
	 *
	 * @since 6.0.4
	 * @return array total, approved, pending, avg, chip ( "1 review · 1 pending", for the heading ).
	 */
	public function review_counts_v2() {
		global $wpdb;
		if ( null !== $this->review_counts ) {
			return $this->review_counts;
		}
		$pid      = (int) $this->id;
		$total    = $pid ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_review WHERE product_id = %d', $pid ) ) : 0;
		$approved = $pid ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_review WHERE product_id = %d AND approved = 1', $pid ) ) : 0;
		$pending  = $total - $approved;
		$avg      = $approved ? (float) $wpdb->get_var( $wpdb->prepare( 'SELECT AVG( rating ) FROM ec_review WHERE product_id = %d AND approved = 1', $pid ) ) : 0;
		/* translators: %d: a number of reviews. */
		$chip = $total ? sprintf( _n( '%d review', '%d reviews', $total, 'wp-easycart' ), $total ) : __( 'No reviews yet', 'wp-easycart' );
		if ( $pending ) {
			/* translators: %d: reviews waiting for approval. */
			$chip .= ' · ' . sprintf( _n( '%d pending', '%d pending', $pending, 'wp-easycart' ), $pending );
		}
		$this->review_counts = array(
			'total'    => $total,
			'approved' => $approved,
			'pending'  => $pending,
			'avg'      => $avg,
			'chip'     => $chip,
		);
		return $this->review_counts;
	}

	/**
	 * Listing health for the summary column ( 6.0.4 ): the same checks as print_health(), unfinished ones first, each a
	 * button to its field. products-details-v2.js re-checks them as fields change ( the keys it knows; others keep their
	 * state from the page load ).
	 *
	 * @since 6.0.4
	 */
	public function print_health_v2() {
		$checks = $this->get_health_checks();
		$todo   = array();
		$done   = array();
		foreach ( $checks as $check ) {
			if ( empty( $check['key'] ) || empty( $check['label'] ) ) {
				continue;
			}
			if ( ! empty( $check['done'] ) ) {
				$done[] = $check;
			} else {
				$todo[] = $check;
			}
		}
		$total = count( $todo ) + count( $done );
		$pct   = $total ? round( count( $done ) / $total * 100 ) : 0;
		echo '<div class="ecdv2-card ecdv2-side-card ecdv2-side-health" id="ecdv2_health">';
		/* translators: 1: checks passed, 2: all checks. */
		echo '<div class="ecdv2-card-header"><h3 class="ecdv2-card-title">' . esc_html__( 'Listing health', 'wp-easycart' ) . '</h3><span class="ecdv2-side-count" id="ecdv2_health_count">' . esc_html( sprintf( __( '%1$d of %2$d', 'wp-easycart' ), count( $done ), $total ) ) . '</span></div>';
		echo '<div class="ecdv2-card-body">';
		echo '<div class="ecdv2-health-bar"><div class="ecdv2-health-bar-fill" id="ecdv2_health_fill" style="width:' . esc_attr( $pct ) . '%;"></div></div>';
		echo '<ul class="ecdv2-side-health-list" id="ecdv2_health_list">';
		foreach ( array_merge( $todo, $done ) as $check ) {
			$is_done = ! empty( $check['done'] );
			echo '<li class="ecdv2-side-health-item ' . ( $is_done ? 'is-done' : 'is-todo' ) . '" data-ecdv2-check="' . esc_attr( $check['key'] ) . '" data-ecdv2-tab="' . esc_attr( isset( $check['tab'] ) ? $check['tab'] : '' ) . '">';
			echo '<button type="button" class="ecdv2-side-health-go"><span class="ecdv2-side-health-mark" aria-hidden="true"></span><span class="ecdv2-side-health-label">' . esc_html( $check['label'] ) . '</span>';
			echo '<span class="screen-reader-text">' . ( $is_done ? esc_html__( 'Done', 'wp-easycart' ) : esc_html__( 'To do', 'wp-easycart' ) ) . '</span></button></li>';
		}
		echo '</ul></div></div>';
	}

	/**
	 * What products-details-v2.js needs for the views ( 6.0.4 ): the person's choices, the editing sections in order, the
	 * AJAX action and nonce for the choices, the list the previous / next buttons read, and the wording.
	 *
	 * @since 6.0.4
	 * @param array    $prefs   wp_easycart_admin_product_editor_prefs().
	 * @param string   $view    page | focus.
	 * @param array    $tabs    The editor's sections.
	 * @param callable $mode_of Section key => edit | insights.
	 * @return array
	 */
	public function view_config_v2( $prefs, $view, $tabs, $mode_of ) {
		$sections = array();
		foreach ( $tabs as $key => $tab ) {
			$sections[] = array(
				'key'   => $key,
				'label' => isset( $tab['label'] ) ? $tab['label'] : $key,
				'mode'  => call_user_func( $mode_of, $key ),
			);
		}
		return array(
			'view'       => $view,
			'tip'        => ! empty( $prefs['tip'] ),
			'sections'   => $sections,
			'action'     => 'ecv2_product_editor_prefs',
			'nonce'      => wp_create_nonce( 'wp-easycart-ecv2-product-editor' ),
			'product_id' => (int) $this->id,
			'edit_url'   => admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' ),
			'draft'      => $this->is_draft,
			'type_flags' => self::type_labels_v2(),
			'types'      => self::product_types_v2(),
			'type'       => $this->product_type_key_v2(),
			'text'       => array(
				/* translators: %s: the next section of the product editor, e.g. Media. */
				'next'            => __( 'Next: %s', 'wp-easycart' ),
				'last'            => __( 'That was the last section. Save when you are ready.', 'wp-easycart' ),
				/* translators: 1: this product's place in the list, 2: products in the list. */
				'nav_position'    => __( '%1$d of %2$d', 'wp-easycart' ),
				'save_first'      => __( 'Save your changes first?', 'wp-easycart' ),
				/* translators: %s: a product title. */
				'save_first_body' => __( 'This product has changes that are not saved yet. Save them and open %s?', 'wp-easycart' ),
				'next_product'    => __( 'the next product', 'wp-easycart' ),
				'save_and_open'   => __( 'Save and open', 'wp-easycart' ),
				'stay'            => __( 'Stay here', 'wp-easycart' ),
				'no_next'         => __( 'This is the last product in the list you came from.', 'wp-easycart' ),
				'standard'        => __( 'Standard product', 'wp-easycart' ),
				'ships'           => __( 'Ships to the customer', 'wp-easycart' ),
				'no_ship'         => __( 'Doesn’t ship', 'wp-easycart' ),
				'active'          => __( 'Shoppers can find and buy it', 'wp-easycart' ),
				'draft'           => __( 'Hidden from your store until you switch it on', 'wp-easycart' ),
				'active_title'    => __( 'Active', 'wp-easycart' ),
				'draft_title'     => __( 'Draft', 'wp-easycart' ),
				/* The Shipping card and section ( 6.0.4 ). */
				'ships_free'      => __( 'Ships free', 'wp-easycart' ),
				'not_set'         => __( 'Not set', 'wp-easycart' ),
				/* translators: 1: a number, 2: its unit ( lb, kg, in or cm ). */
				'with_unit'       => __( '%1$s %2$s', 'wp-easycart' ),
				'packs_auto'      => __( 'Best box, automatic', 'wp-easycart' ),
				'packs_own'       => __( 'Its own box', 'wp-easycart' ),
				'no_handling'     => __( 'Your shipping rates', 'wp-easycart' ),
				/* translators: %s: an amount of money. */
				'handling_order'  => __( '%s handling per order', 'wp-easycart' ),
				/* translators: %s: an amount of money. */
				'handling_item'   => __( '%s per item', 'wp-easycart' ),
				'left_out'        => __( 'Left out of your rates', 'wp-easycart' ),
				'billing_address' => __( 'Ships to the billing address', 'wp-easycart' ),
				'off_download'    => __( 'A digital download: checkout asks no shipping address and adds no shipping.', 'wp-easycart' ),
				'off_service'     => __( 'Booked or attended, never sent: checkout asks no shipping address and adds no shipping.', 'wp-easycart' ),
				'off_other'       => __( 'Checkout asks no shipping address and adds no shipping for it.', 'wp-easycart' ),
				/* translators: 1: a weight with its unit, 2: a size with its unit. */
				'ship_summary'    => __( '%1$s · %2$s', 'wp-easycart' ),
				/* The Tax card, the quick links and Design ( 6.0.4 ). */
				'taxable'         => __( 'Taxable', 'wp-easycart' ),
				'not_taxable'     => __( 'Not taxable', 'wp-easycart' ),
				'tax_sales_vat'   => __( 'Sales tax and VAT', 'wp-easycart' ),
				'tax_sales'       => __( 'Sales tax', 'wp-easycart' ),
				'tax_vat'         => __( 'VAT', 'wp-easycart' ),
				'tax_none'        => __( 'No sales tax or VAT', 'wp-easycart' ),
				'no_manufacturer' => __( 'None', 'wp-easycart' ),
				'no_badge'        => __( 'No badge', 'wp-easycart' ),
				/* translators: %s: the text of a product card's badge, e.g. Sale. */
				'badge'           => __( 'Badge: %s', 'wp-easycart' ),
				'startup_on'      => __( 'Shown on the store page', 'wp-easycart' ),
				'startup_off'     => __( 'Not on the store page', 'wp-easycart' ),
				'everyone'        => __( 'Everyone', 'wp-easycart' ),
				/* translators: %s: a user role, e.g. Wholesale. */
				'only_role'       => __( 'Only %s customers', 'wp-easycart' ),
				'one_role'        => __( 'One customer role only', 'wp-easycart' ),
				/* translators: 1: checks passed, 2: all checks. */
				'health_count'    => __( '%1$d of %2$d', 'wp-easycart' ),
				'out_of_stock'    => __( 'Out of stock', 'wp-easycart' ),
				/* Folded section summaries. */
				'no_title'        => __( 'No title yet', 'wp-easycart' ),
				'no_sku'          => __( 'no SKU', 'wp-easycart' ),
				'picture_one'     => __( '1 picture', 'wp-easycart' ),
				/* translators: %d: a number of pictures. */
				'pictures'        => __( '%d pictures', 'wp-easycart' ),
				'no_pictures'     => __( 'No pictures yet', 'wp-easycart' ),
				'taxed'           => __( 'Taxed', 'wp-easycart' ),
				'not_taxed'       => __( 'Not taxed', 'wp-easycart' ),
				'not_tracked'     => __( 'Stock not tracked', 'wp-easycart' ),
				/* translators: %d: units in stock. */
				'in_stock'        => __( '%d in stock', 'wp-easycart' ),
				'per_variant'     => __( 'Stock per variant', 'wp-easycart' ),
				/* translators: %s: a weight with its unit. */
				'weight'          => __( 'weight %s', 'wp-easycart' ),
				'no_weight'       => __( 'no weight yet', 'wp-easycart' ),
				'no_options'      => __( 'No options: sold as one item', 'wp-easycart' ),
				'menu_one'        => __( '1 menu', 'wp-easycart' ),
				/* translators: %d: a number of menus. */
				'menus'           => __( '%d menus', 'wp-easycart' ),
				'related_one'     => __( '1 related product', 'wp-easycart' ),
				/* translators: %d: a number of related products. */
				'related'         => __( '%d related products', 'wp-easycart' ),
				'seo_set'         => __( 'Search description written', 'wp-easycart' ),
				'seo_missing'     => __( 'No search description yet', 'wp-easycart' ),
				/* translators: 1: notes written, 2: notes there could be. */
				'notes'           => __( '%1$d of %2$d order notes written', 'wp-easycart' ),
				'units'           => __( 'Units sold', 'wp-easycart' ),
				/* Product type ( 6.0.4, editor plan Phase 2 ). */
				'type_custom'     => __( 'This product mixes types, so every type switch shows in Type settings.', 'wp-easycart' ),
				'type_unused'     => __( 'Not used for this type', 'wp-easycart' ),
				/* translators: %s: a section of the product editor, e.g. Shipping. */
				'type_show'       => __( 'Show %s', 'wp-easycart' ),
				/* Add product ( 6.0.4, editor plan Phase 2 ). */
				'created'         => __( 'Product created.', 'wp-easycart' ),
				'draft_basics'    => __( 'Give the product a title first: it is created when you save.', 'wp-easycart' ),
				/* One way to save ( 6.0.4, editor plan Phase 2 ). */
				'errors_go'       => __( 'Show me', 'wp-easycart' ),
				/* translators: %s: a card of the product editor, e.g. Categories. */
				'errors_section'  => __( '%s was not saved. Try Save again.', 'wp-easycart' ),
				'discard_title'   => __( 'Discard your changes?', 'wp-easycart' ),
				'discard_body'    => __( 'Everything you changed since the last save goes back to how it was saved. Pictures you uploaded and categories you made stay.', 'wp-easycart' ),
				'discard'         => __( 'Discard', 'wp-easycart' ),
				'keep_editing'    => __( 'Keep editing', 'wp-easycart' ),
				'category_created' => __( 'Category created. Save to add this product to it.', 'wp-easycart' ),
				'tier_qty'        => __( 'Enter how many a shopper buys, 1 or more.', 'wp-easycart' ),
				'tier_dup'        => __( 'There is already a price break for that quantity.', 'wp-easycart' ),
				'no_tiers'        => __( 'No volume pricing tiers yet. Add your first price break above.', 'wp-easycart' ),
				'no_role_prices'  => __( 'No B2B role pricing yet. Add a role price above.', 'wp-easycart' ),
			),
		);
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
