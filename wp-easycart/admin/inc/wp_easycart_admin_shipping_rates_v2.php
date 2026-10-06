<?php
/**
 * WP EasyCart Admin — Shipping rates ( V2 settings page ) helpers + AJAX.
 *
 * Backs admin/template/settings/shipping-rates.php. The page has no plain options:
 * it edits rows of ec_shippingrate for the five free rate systems ( flat
 * methods, by cart total, by weight, by quantity, percentage of total ) and
 * shows which system is on ( ec_setting.shipping_method ). The method can be
 * switched from the top of the page; the switcher saves through the engine's
 * ecv2_settings_save with page 'shipping-settings' ( the page that declares
 * ec_option_shipping_method and its on_save ), so there is one save path.
 * Rows save one at a time through the ecv2_shipping_rate_* handlers below,
 * each opened by ecv2_settings_guard().
 *
 * 6.0.2: each table is a list of rows ( an icon or carrier badge, the key settings
 * and Edit ); Edit and Add open one side drawer ( built by settings-shipping-rates-v2.js
 * on <body> ) that holds every setting of the row. print_list(), print_row() and the
 * badge helpers are public so WP EasyCart PRO draws its live-rate list the same way
 * ( it checks method_exists() first and keeps its older grid with an older WP EasyCart ).
 *
 * Loaded from admin/admin-init.php ( so the handlers exist on admin-ajax ) and
 * required again from the declaration ( so the render callables exist when the
 * registry loads it outside the admin bootstrap, e.g. the migration-map
 * generator, where every WordPress call below is guarded ).
 *
 * The PRO live-rate list ( UPS, FedEx, USPS, … ) is a separate section the
 * free file declares locked; wp-easycart-pro/admin/template/settings/shipping-rates.php
 * attaches its render through the page filter.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_shipping_rates_v2' ) ) :

class wp_easycart_admin_shipping_rates_v2 {

	/* ------------------------------------------------------------------ */
	/* Vocabulary                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * The five rate tables the free plugin edits, keyed by the type the page and
	 * the AJAX handlers use. 'method' is the ec_setting.shipping_method value that
	 * turns the table on; 'flag' is the ec_shippingrate column that marks its rows.
	 */
	public static function types() {
		return array(
			'flat' => array(
				'method' => 'method',
				'flag'   => 'is_method_based',
				'title'  => __( 'Flat rates', 'wp-easycart' ),
				'hint'   => __( 'Named methods the shopper picks from at checkout, each with its own price', 'wp-easycart' ),
				'icon'   => 'tag',
				'desc'   => __( 'Every method listed here is offered, in the order you set. A method with a free-shipping threshold becomes free once the cart total reaches it.', 'wp-easycart' ),
			),
			'price' => array(
				'method' => 'price',
				'flag'   => 'is_price_based',
				'title'  => __( 'By cart total', 'wp-easycart' ),
				'hint'   => __( 'One rate, chosen by how much the shopper is spending', 'wp-easycart' ),
				'icon'   => 'dollar',
				'desc'   => __( 'The row whose “from” amount is the highest one at or below the cart total applies. Start a row at 0 so every order gets a rate.', 'wp-easycart' ),
			),
			'weight' => array(
				'method' => 'weight',
				'flag'   => 'is_weight_based',
				'title'  => __( 'By weight', 'wp-easycart' ),
				'hint'   => __( 'One rate, chosen by the total weight of the order', 'wp-easycart' ),
				'icon'   => 'scale',
				'desc'   => __( 'Weights use the unit your products are entered in. The row whose “from” weight is the highest one at or below the order weight applies.', 'wp-easycart' ),
			),
			'quantity' => array(
				'method' => 'quantity',
				'flag'   => 'is_quantity_based',
				'title'  => __( 'By quantity', 'wp-easycart' ),
				'hint'   => __( 'One rate, chosen by how many items are in the cart', 'wp-easycart' ),
				'icon'   => 'hash',
				'desc'   => __( 'The row whose “from” count is the highest one at or below the number of items applies.', 'wp-easycart' ),
			),
			'percentage' => array(
				'method' => 'percentage',
				'flag'   => 'is_percentage_based',
				'title'  => __( 'Percentage of total', 'wp-easycart' ),
				'hint'   => __( 'Shipping charged as a share of the cart total', 'wp-easycart' ),
				'icon'   => 'percent',
				'desc'   => __( 'The row whose “from” amount is the highest one at or below the cart total sets the percentage charged.', 'wp-easycart' ),
			),
		);
	}

	/** Every value ec_setting.shipping_method can hold, with a merchant-facing name. */
	public static function methods() {
		return array(
			'method'     => __( 'Flat rates', 'wp-easycart' ),
			'price'      => __( 'By cart total', 'wp-easycart' ),
			'weight'     => __( 'By weight', 'wp-easycart' ),
			'quantity'   => __( 'By quantity', 'wp-easycart' ),
			'percentage' => __( 'Percentage of total', 'wp-easycart' ),
			'live'       => __( 'Live carrier rates', 'wp-easycart' ),
			'fraktjakt'  => __( 'Fraktjakt', 'wp-easycart' ),
		);
	}

	/** Type key for a section slug ( the declaration names its sections after the types ). */
	public static function type_for_section( $slug ) {
		$types = self::types();
		return isset( $types[ $slug ] ) ? $slug : '';
	}

	/* ------------------------------------------------------------------ */
	/* Data                                                                */
	/* ------------------------------------------------------------------ */

	/** True inside WordPress with a database; false for the migration-map generator. */
	public static function db() {
		return isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_results' );
	}

	/** ec_setting.shipping_method, read once per request. */
	public static function method( $refresh = false ) {
		static $method = null;
		if ( $refresh ) {
			$method = null;
		}
		if ( null !== $method ) {
			return $method;
		}
		$method = 'method';
		if ( self::db() ) {
			$found = $GLOBALS['wpdb']->get_var( 'SELECT shipping_method FROM ec_setting' );
			if ( is_string( $found ) && '' !== $found ) {
				$method = $found;
			}
		}
		return $method;
	}

	/** Is shipping charged at all ( ec_option_use_shipping )? */
	public static function shipping_on() {
		return function_exists( 'get_option' ) ? (bool) get_option( 'ec_option_use_shipping' ) : true;
	}

	/** Is the PRO plugin active and licensed for this feature? */
	public static function pro_on() {
		if ( ! class_exists( 'wp_easycart_admin_settings_registry' ) ) {
			return false;
		}
		return ! wp_easycart_admin_settings_registry::is_locked( array( 'pro' => true ) );
	}

	/** zone_id => zone_name, alphabetical; 0 is always "Any destination". */
	public static function zones() {
		static $zones = null;
		if ( null !== $zones ) {
			return $zones;
		}
		$zones = array( 0 => __( 'Any destination', 'wp-easycart' ) );
		if ( self::db() ) {
			$rows = $GLOBALS['wpdb']->get_results( 'SELECT zone_id, zone_name FROM ec_zone ORDER BY zone_name ASC' );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$zones[ (int) $row->zone_id ] = (string) $row->zone_name;
				}
			}
		}
		return $zones;
	}

	/**
	 * A zone's name for a row, or a note when the zone was deleted after the row was saved.
	 *
	 * @since 6.0.2
	 * @param int $zone_id ec_shippingrate.zone_id.
	 * @return string
	 */
	public static function zone_name( $zone_id ) {
		$zones = self::zones();
		$zone_id = (int) $zone_id;
		return isset( $zones[ $zone_id ] ) ? $zones[ $zone_id ] : __( 'Deleted zone', 'wp-easycart' );
	}

	/**
	 * The rows of one table. Flat rates come back in their checkout order; the
	 * trigger tables in ascending trigger order, the way the storefront walks them.
	 */
	public static function rates( $type ) {
		$types = self::types();
		if ( ! isset( $types[ $type ] ) || ! self::db() ) {
			return array();
		}
		global $wpdb;
		if ( 'flat' === $type ) {
			$rows = $wpdb->get_results( 'SELECT shippingrate_id, zone_id, trigger_rate, shipping_rate, shipping_label, shipping_order, free_shipping_at FROM ec_shippingrate WHERE is_method_based = 1 ORDER BY shipping_order ASC, shippingrate_id ASC' );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT shippingrate_id, zone_id, trigger_rate, shipping_rate, shipping_label, shipping_order, free_shipping_at FROM ec_shippingrate WHERE ( CASE %s WHEN 'flat' THEN is_method_based WHEN 'price' THEN is_price_based WHEN 'weight' THEN is_weight_based WHEN 'quantity' THEN is_quantity_based WHEN 'percentage' THEN is_percentage_based ELSE 0 END ) = 1 ORDER BY trigger_rate ASC, shippingrate_id ASC", $type ) );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/*
	 * Queries that pick rows by type use a CASE on the %s type placeholder to choose
	 * the flag column in SQL, so every statement stays fully prepared — no column
	 * name is ever spliced into the string.
	 */

	/** One row by id, restricted to the given type. Null when missing. */
	private static function rate( $type, $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT shippingrate_id, zone_id, trigger_rate, shipping_rate, shipping_label, shipping_order, free_shipping_at FROM ec_shippingrate WHERE shippingrate_id = %d AND ( CASE %s WHEN 'flat' THEN is_method_based WHEN 'price' THEN is_price_based WHEN 'weight' THEN is_weight_based WHEN 'quantity' THEN is_quantity_based WHEN 'percentage' THEN is_percentage_based ELSE 0 END ) = 1", (int) $id, $type ) );
		return $row ? $row : null;
	}

	/** Currency symbol for column headings and affixes. */
	public static function symbol() {
		static $symbol = null;
		if ( null !== $symbol ) {
			return $symbol;
		}
		$symbol = '';
		if ( class_exists( 'ec_currency' ) && function_exists( 'get_option' ) ) {
			$currency = new ec_currency();
			$symbol = (string) $currency->symbol;
		}
		if ( '' === $symbol && function_exists( 'get_option' ) ) {
			$symbol = (string) get_option( 'ec_option_currency' );
		}
		return $symbol;
	}

	/** Money for an input: the store's decimal places, dot separator, no grouping. */
	public static function money( $amount ) {
		if ( class_exists( 'ec_currency' ) && function_exists( 'get_option' ) ) {
			self::symbol(); // instantiates ec_currency so its static decimal length is set.
			return ec_currency::get_number_safe( $amount );
		}
		return number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Money as the store shows it ( symbol on its side, decimal and grouping marks ), for the row summaries. Never
	 * converted to a shopper's display currency.
	 *
	 * @since 6.0.2
	 * @param float $amount Amount in the store currency.
	 * @return string Plain text.
	 */
	public static function money_display( $amount ) {
		if ( class_exists( 'ec_currency' ) && function_exists( 'get_option' ) ) {
			self::symbol(); // instantiates ec_currency so its static format settings are set.
			return ec_currency::get_currency_display( (float) $amount, false );
		}
		return self::symbol() . number_format( (float) $amount, 2 );
	}

	/** Is the currency symbol written before the amount ( ec_option_currency_symbol_location )? @since 6.0.2 */
	public static function symbol_first() {
		if ( ! function_exists( 'get_option' ) ) {
			return true;
		}
		return (bool) get_option( 'ec_option_currency_symbol_location', '1' ); // read as ec_currency reads it.
	}

	/** A weight or count for an input: trailing zeros dropped. */
	public static function plain_number( $value ) {
		$text = rtrim( rtrim( number_format( (float) $value, 3, '.', '' ), '0' ), '.' );
		return ( '' === $text || '-0' === $text ) ? '0' : $text;
	}

	/**
	 * The unit product weights are entered in ( the setup wizard's ec_option_paypal_weight_unit, as the option editor
	 * and the box library read it ).
	 *
	 * @since 6.0.2
	 * @return string kg | lb
	 */
	public static function weight_unit() {
		$unit = function_exists( 'get_option' ) ? (string) get_option( 'ec_option_paypal_weight_unit' ) : '';
		return ( 'kgs' === $unit || 'kg' === $unit ) ? 'kg' : 'lb';
	}

	/** The columns of a table, in display order: field, kind ( text | money | weight | int | percent | zone ), heading. */
	public static function columns( $type ) {
		switch ( $type ) {
			case 'flat':
				return array(
					array( 'label', 'text', __( 'Method name', 'wp-easycart' ) ),
					array( 'rate', 'money', __( 'Rate', 'wp-easycart' ) ),
					array( 'free_at', 'money', __( 'Free shipping at', 'wp-easycart' ) ),
					array( 'order', 'int', __( 'Order', 'wp-easycart' ) ),
					array( 'zone_id', 'zone', __( 'Zone', 'wp-easycart' ) ),
				);
			case 'weight':
				return array(
					array( 'trigger', 'weight', __( 'Weight from', 'wp-easycart' ) ),
					array( 'rate', 'money', __( 'Rate', 'wp-easycart' ) ),
					array( 'zone_id', 'zone', __( 'Zone', 'wp-easycart' ) ),
				);
			case 'quantity':
				return array(
					array( 'trigger', 'int', __( 'Items from', 'wp-easycart' ) ),
					array( 'rate', 'money', __( 'Rate', 'wp-easycart' ) ),
					array( 'zone_id', 'zone', __( 'Zone', 'wp-easycart' ) ),
				);
			case 'percentage':
				return array(
					array( 'trigger', 'money', __( 'Cart total from', 'wp-easycart' ) ),
					array( 'rate', 'percent', __( 'Percent of total', 'wp-easycart' ) ),
					array( 'zone_id', 'zone', __( 'Zone', 'wp-easycart' ) ),
				);
			default: // price
				return array(
					array( 'trigger', 'money', __( 'Cart total from', 'wp-easycart' ) ),
					array( 'rate', 'money', __( 'Rate', 'wp-easycart' ) ),
					array( 'zone_id', 'zone', __( 'Zone', 'wp-easycart' ) ),
				);
		}
	}

	/** A row as the inputs and the browser see it. */
	public static function row_data( $type, $row ) {
		$free = ( null !== $row && (float) $row->free_shipping_at >= 0 ) ? self::money( $row->free_shipping_at ) : '';
		$trigger = '';
		if ( null !== $row ) {
			if ( 'weight' === $type ) {
				$trigger = self::plain_number( $row->trigger_rate );
			} elseif ( 'quantity' === $type ) {
				$trigger = (string) (int) round( (float) $row->trigger_rate );
			} else {
				$trigger = self::money( $row->trigger_rate );
			}
		}
		return array(
			'id'      => null !== $row ? (int) $row->shippingrate_id : 0,
			'label'   => null !== $row ? (string) $row->shipping_label : '',
			'rate'    => null !== $row ? self::money( $row->shipping_rate ) : '',
			'trigger' => $trigger,
			'free_at' => $free,
			'order'   => null !== $row ? (string) (int) $row->shipping_order : '0',
			'zone_id' => null !== $row ? (int) $row->zone_id : 0,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Render: active method                                               */
	/* ------------------------------------------------------------------ */

	/** What each method does, for the active-method card ( method => sentence ). */
	public static function method_explanations() {
		$explain = array();
		foreach ( self::types() as $type ) {
			$explain[ $type['method'] ] = $type['hint'];
		}
		$explain['live']      = __( 'Rates are quoted by the carriers you connected, filtered by the live-rate list below.', 'wp-easycart' );
		$explain['fraktjakt'] = __( 'Rates are quoted by Fraktjakt from your account; there is no table to edit on this page.', 'wp-easycart' );
		return $explain;
	}

	/**
	 * The choices of the method switcher: method => array( 'label', 'desc', 'pro', 'locked' ).
	 * A method is offered when the Shipping settings declaration of ec_option_shipping_method
	 * lists it ( that is what ecv2_settings_save validates against ). Live rates and Fraktjakt
	 * are PRO: without an active, licensed PRO they show locked, never as a working choice.
	 * The stored method is always included so the switcher never hides what is on.
	 */
	public static function switcher_methods() {
		$current = self::method();
		$allowed = array();
		if ( class_exists( 'wp_easycart_admin_settings_registry' ) ) {
			$field = wp_easycart_admin_settings_registry::field( 'shipping-settings', 'ec_option_shipping_method' );
			if ( $field && isset( $field['options'] ) && is_array( $field['options'] ) ) {
				$allowed = array_map( 'strval', array_keys( $field['options'] ) );
			}
		}
		$pro_on  = self::pro_on();
		$explain = self::method_explanations();
		$out     = array();
		foreach ( self::methods() as $method => $label ) {
			$is_pro = in_array( $method, array( 'live', 'fraktjakt' ), true );
			$locked = ( $is_pro && ! $pro_on ) || ! in_array( $method, $allowed, true );
			if ( $locked && ! $is_pro && $method !== $current ) {
				continue; // a free method the declaration does not list: nothing to offer.
			}
			$out[ $method ] = array(
				'label'    => $label,
				'desc'     => isset( $explain[ $method ] ) ? $explain[ $method ] : '',
				'pro'      => $is_pro,
				'locked'   => $locked,
				'advanced' => in_array( $method, self::advanced_methods(), true ) && $method !== $current,
			);
		}
		if ( ! isset( $out[ $current ] ) ) {
			$out[ $current ] = array(
				'label'    => $current,
				'desc'     => '',
				'pro'      => false,
				'locked'   => true,
				'advanced' => false,
			);
		}
		return $out;
	}

	/**
	 * Methods few stores use, kept behind the switcher's "More methods" disclosure
	 * instead of among the main pills. The stored method is always shown as a normal pill.
	 */
	public static function advanced_methods() {
		return array( 'fraktjakt' );
	}

	/** Section render: which method is on, what that means, and a switcher to pick another. */
	public static function render_active( $page, $section ) {
		$method    = self::method();
		$choices   = self::switcher_methods();
		$name      = $choices[ $method ]['label'];
		$explain   = $choices[ $method ]['desc'];
		$reg       = class_exists( 'wp_easycart_admin_settings_registry' );
		$more_url  = $reg ? wp_easycart_admin_settings_registry::page_url( 'shipping-settings', 'ec_option_shipping_method' ) : admin_url( 'admin.php?page=wp-easycart-settings&subpage=shipping-settings' );
		$on_url    = $reg ? wp_easycart_admin_settings_registry::page_url( 'shipping-settings', 'ec_option_use_shipping' ) : $more_url;
		$frakt_url = $reg ? wp_easycart_admin_settings_registry::page_url( 'shipping-settings', 'fraktjakt_customer_id' ) : $more_url;
		?>
		<div class="ecsr-active" id="ecsr_active" data-method="<?php echo esc_attr( $method ); ?>" data-pro="<?php echo self::pro_on() ? '1' : '0'; ?>">
			<div class="ecsr-active-top">
				<div class="ecsr-active-text">
					<span class="ecsr-active-kicker"><?php esc_html_e( 'Active method', 'wp-easycart' ); ?></span>
					<b class="ecsr-active-title"><?php echo esc_html( $name ); ?></b>
					<span class="ecsr-active-desc"<?php echo '' === $explain ? ' hidden' : ''; ?>><?php echo esc_html( $explain ); ?></span>
				</div>
				<a class="ecsr-active-change" href="<?php echo esc_url( $more_url ); ?>"><?php esc_html_e( 'Shipping settings', 'wp-easycart' ); ?> →</a>
			</div>
			<div class="ecsr-switch">
				<span class="ecsr-switch-label" id="ecsr_switch_label"><?php esc_html_e( 'Charge shipping', 'wp-easycart' ); ?></span>
				<?php
				$main     = array();
				$advanced = array();
				foreach ( $choices as $key => $choice ) {
					if ( ! empty( $choice['advanced'] ) ) {
						$advanced[ $key ] = $choice;
					} else {
						$main[ $key ] = $choice;
					}
				}
				?>
				<div class="ecsr-pills" role="group" aria-labelledby="ecsr_switch_label">
					<?php
					foreach ( $main as $key => $choice ) {
						self::render_pill( $key, $choice, $method );
					}
					?>
					<?php if ( ! empty( $advanced ) ) : ?>
						<button type="button" class="ecsr-more" aria-expanded="false" aria-controls="ecsr_pills_more"><?php esc_html_e( 'More methods', 'wp-easycart' ); ?><svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false"><path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
						<span class="ecsr-pills-more" id="ecsr_pills_more" hidden>
							<?php
							foreach ( $advanced as $key => $choice ) {
								self::render_pill( $key, $choice, $method );
							}
							?>
						</span>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php if ( ! self::shipping_on() ) : ?>
			<div class="ecsr-notice is-warn"><?php esc_html_e( 'Shipping is turned off, so none of these rates are charged. Turn it on in Shipping settings to use them.', 'wp-easycart' ); ?> <a href="<?php echo esc_url( $on_url ); ?>"><?php esc_html_e( 'Open Shipping settings', 'wp-easycart' ); ?> →</a></div>
		<?php endif; ?>
		<div class="ecsr-notice is-warn" data-for-method="live"<?php echo ( 'live' === $method && ! self::pro_on() ) ? '' : ' hidden'; ?>><?php echo esc_html( self::live_locked_text() . ' ' . __( 'Until then, pick one of the rate tables below as your method.', 'wp-easycart' ) ); ?></div>
		<div class="ecsr-notice" data-for-method="fraktjakt"<?php echo 'fraktjakt' === $method ? '' : ' hidden'; ?>><?php esc_html_e( 'Your Fraktjakt account and ship-from address are on the Shipping settings page.', 'wp-easycart' ); ?> <a href="<?php echo esc_url( $frakt_url ); ?>"><?php esc_html_e( 'Open Fraktjakt account', 'wp-easycart' ); ?> →</a></div>
		<p class="ecsr-active-foot"><?php esc_html_e( 'The tables for the other methods are kept below, collapsed. Whatever you enter there is stored, but only the active method’s table is used at checkout.', 'wp-easycart' ); ?></p>
		<?php
	}

	/** Notice sentence when live carrier rates are stored but PRO is not active. */
	private static function live_locked_text() {
		if ( class_exists( 'wp_easycart_admin_edition' ) ) {
			return wp_easycart_admin_edition::requires_text( __( 'Live carrier rates', 'wp-easycart' ), 'pro' );
		}
		return __( 'Live carrier rates are included with Pro and Premium licenses.', 'wp-easycart' );
	}

	/** One method pill of the switcher. */
	private static function render_pill( $key, $choice, $method ) {
		$is_on   = ( (string) $key === $method );
		$classes = 'ecsr-pill' . ( $is_on ? ' is-on' : '' ) . ( $choice['locked'] ? ' is-locked' : '' );
		?>
		<button type="button" class="<?php echo esc_attr( $classes ); ?>" data-method="<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $choice['label'] ); ?>" data-desc="<?php echo esc_attr( $choice['desc'] ); ?>" data-locked="<?php echo $choice['locked'] ? '1' : '0'; ?>" aria-pressed="<?php echo $is_on ? 'true' : 'false'; ?>"<?php echo ( $choice['locked'] && $choice['pro'] ) ? ' title="' . esc_attr( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::included_text( 'pro' ) : __( 'Included with Pro and Premium licenses.', 'wp-easycart' ) ) . '"' : ''; ?>>
			<span class="ecsr-pill-text"><?php echo esc_html( $choice['label'] ); ?></span>
			<?php if ( $choice['locked'] && $choice['pro'] ) : ?><span class="ecst-pro-tag"><?php echo esc_html( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : __( 'Pro/Premium', 'wp-easycart' ) ); ?></span><?php endif; ?>
		</button>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Render: rate lists ( 6.0.2 )                                        */
	/* ------------------------------------------------------------------ */

	/** Section render for each of the five tables: the section slug is the type. */
	public static function render_section( $page, $section ) {
		$type = self::type_for_section( isset( $section['slug'] ) ? $section['slug'] : '' );
		if ( '' === $type ) {
			return;
		}
		self::render_table( $type );
	}

	/**
	 * One table as a list of rows ( 6.0.2: was a grid of inputs ). The .ecsr wrapper and its data-type / data-active
	 * are what the page script collapses for the methods that are not in use.
	 */
	public static function render_table( $type ) {
		$types = self::types();
		if ( ! isset( $types[ $type ] ) ) {
			return;
		}
		$rows   = self::rates( $type );
		$active = ( self::method() === $types[ $type ]['method'] ) ? '1' : '0';
		$items  = array();
		foreach ( $rows as $row ) {
			$items[] = self::row_args( $type, self::row_data( $type, $row ) );
		}
		?>
		<div class="ecsr" data-type="<?php echo esc_attr( $type ); ?>" data-active="<?php echo esc_attr( $active ); ?>">
			<p class="ecsr-desc"><?php echo esc_html( $types[ $type ]['desc'] ); ?></p>
			<?php
			self::print_list(
				array(
					'type'     => $type,
					'label'    => $types[ $type ]['title'],
					'head'     => self::list_head( $type ),
					'rows'     => $items,
					'empty'    => self::empty_text( $type ),
					'add'      => self::add_text( $type ),
					'sortable' => ( 'flat' === $type ),
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Column headings over a table's rows: array( first column, array( one per fact ) ). The facts of every row of the
	 * table ( summary() ) follow the same order.
	 *
	 * @since 6.0.2
	 * @param string $type Table type.
	 * @return array
	 */
	public static function list_head( $type ) {
		switch ( $type ) {
			case 'flat':
				return array( __( 'Method', 'wp-easycart' ), array( __( 'Rate', 'wp-easycart' ), __( 'Free shipping at', 'wp-easycart' ), __( 'Zone', 'wp-easycart' ) ) );
			case 'percentage':
				return array( __( 'Applies from', 'wp-easycart' ), array( __( 'Charge', 'wp-easycart' ), __( 'Zone', 'wp-easycart' ) ) );
			default:
				return array( __( 'Applies from', 'wp-easycart' ), array( __( 'Rate', 'wp-easycart' ), __( 'Zone', 'wp-easycart' ) ) );
		}
	}

	/** The empty-state sentence of a table. @since 6.0.2 */
	private static function empty_text( $type ) {
		if ( 'flat' === $type ) {
			return __( 'No shipping methods yet. Add the first one, for example Standard delivery.', 'wp-easycart' );
		}
		return __( 'No rates yet. Add the first one, starting from 0 so every order gets a rate.', 'wp-easycart' );
	}

	/** The Add button of a table. @since 6.0.2 */
	private static function add_text( $type ) {
		return 'flat' === $type ? __( 'Add method', 'wp-easycart' ) : __( 'Add rate', 'wp-easycart' );
	}

	/**
	 * What a row of a table shows: its title and its facts ( label => value, in list_head() order ).
	 *
	 * @since 6.0.2
	 * @param string $type Table type.
	 * @param array  $data row_data().
	 * @return array { title, sub, facts: array of array( label, value, muted ) }
	 */
	public static function summary( $type, $data ) {
		$head    = self::list_head( $type );
		$labels  = $head[1];
		$rate    = (float) $data['rate'];
		$trigger = (float) $data['trigger'];
		$zone    = self::zone_name( $data['zone_id'] );
		$sub     = '';
		if ( 'percentage' === $type ) {
			/* translators: %s: a percentage, e.g. 10 ( for 10% ). */
			$charge = sprintf( __( '%s%%', 'wp-easycart' ), self::plain_number( $rate ) );
		} else {
			$charge = ( $rate > 0 ) ? self::money_display( $rate ) : __( 'Free', 'wp-easycart' );
		}
		switch ( $type ) {
			case 'flat':
				$title = '' !== trim( (string) $data['label'] ) ? (string) $data['label'] : __( 'Unnamed method', 'wp-easycart' );
				$free  = ( '' !== (string) $data['free_at'] ) ? self::money_display( (float) $data['free_at'] ) : '';
				$facts = array(
					array( $labels[0], $charge, false ),
					array( $labels[1], '' !== $free ? $free : __( 'Never', 'wp-easycart' ), '' === $free ),
					array( $labels[2], $zone, false ),
				);
				return array(
					'title' => $title,
					'sub'   => $sub,
					'facts' => $facts,
				);
			case 'weight':
				if ( $trigger <= 0 ) {
					$title = __( 'Any weight', 'wp-easycart' );
				} else {
					/* translators: 1: a weight, e.g. 5, 2: its unit, lb or kg. */
					$title = sprintf( __( 'Orders from %1$s %2$s', 'wp-easycart' ), self::plain_number( $trigger ), self::weight_unit() );
				}
				break;
			case 'quantity':
				$count = (int) round( $trigger );
				if ( $count <= 0 ) {
					$title = __( 'Any number of items', 'wp-easycart' );
				} else {
					/* translators: %s: a number of items. */
					$title = sprintf( _n( 'Orders from %s item', 'Orders from %s items', $count, 'wp-easycart' ), number_format( $count ) );
				}
				break;
			default: // price, percentage.
				if ( $trigger <= 0 ) {
					$title = __( 'Any cart total', 'wp-easycart' );
				} else {
					/* translators: %s: an amount of money. */
					$title = sprintf( __( 'Cart total from %s', 'wp-easycart' ), self::money_display( $trigger ) );
				}
		}
		return array(
			'title' => $title,
			'sub'   => $sub,
			'facts' => array(
				array( $labels[0], $charge, false ),
				array( $labels[1], $zone, false ),
			),
		);
	}

	/**
	 * print_row() arguments for one row of a free table.
	 *
	 * @since 6.0.2
	 * @param string $type Table type.
	 * @param array  $data row_data().
	 * @return array
	 */
	public static function row_args( $type, $data ) {
		$summary = self::summary( $type, $data );
		return array(
			'id'       => (int) $data['id'],
			'type'     => $type,
			'badge'    => self::type_badge( $type ),
			'title'    => $summary['title'],
			'sub'      => $summary['sub'],
			'facts'    => $summary['facts'],
			'data'     => $data,
			'sortable' => ( 'flat' === $type ),
			'lead'     => ( 'flat' === $type ),
			'help'     => 'ecsr_move_help_' . $type, // print_list() prints it under a sortable list.
		);
	}

	/**
	 * One row's markup, for the AJAX answers ( the page swaps it in after a save ).
	 *
	 * @since 6.0.2
	 * @param string $type Table type.
	 * @param array  $data row_data().
	 * @return string
	 */
	public static function row_html( $type, $data ) {
		ob_start();
		self::print_row( self::row_args( $type, $data ) );
		return trim( (string) ob_get_clean() );
	}

	/**
	 * A list of rows with its headings, empty state and Add button. Shared with WP EasyCart PRO's live-rate list.
	 * The page script ( settings-shipping-rates-v2.js, window.ecsr.list ) finds it by .ecsr-rows[data-type].
	 *
	 * @since 6.0.2
	 * @param array $args {
	 *     @type string $type     data-type of the list ( a table type, or 'live' ).
	 *     @type string $label    Accessible name of the list.
	 *     @type array  $head     array( first column heading, array( fact headings ) ).
	 *     @type array  $rows     print_row() arguments, one per row.
	 *     @type string $empty    Empty-state sentence.
	 *     @type string $add      Add button text ( '' for none ).
	 *     @type bool   $sortable Rows can be dragged ( and moved with the arrow keys ) into order.
	 *     @type string $id       Optional id of the wrapper.
	 *     @type string $after    Optional HTML printed after the list ( already escaped ).
	 *     @type bool   $headings Print the column headings ( false for a list under another one ).
	 * }
	 */
	public static function print_list( $args ) {
		$args  = array_merge(
			array(
				'type'     => '',
				'label'    => '',
				'head'     => array( '', array() ),
				'rows'     => array(),
				'empty'    => '',
				'add'      => '',
				'sortable' => false,
				'id'       => '',
				'after'    => '',
				'headings' => true,
			),
			(array) $args
		);
		$facts = isset( $args['head'][1] ) ? (array) $args['head'][1] : array();
		$empty = empty( $args['rows'] );
		$help  = 'ecsr_move_help_' . sanitize_key( $args['type'] );
		?>
		<div class="ecsr-rows<?php echo $args['sortable'] ? ' is-sortable' : ''; ?>"<?php echo '' !== $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''; ?> data-type="<?php echo esc_attr( $args['type'] ); ?>" data-facts="<?php echo (int) count( $facts ); ?>" style="--ecsr-facts:<?php echo (int) max( 1, count( $facts ) ); ?>">
			<?php if ( $args['headings'] ) : ?>
				<div class="ecsr-list-head" aria-hidden="true"<?php echo $empty ? ' hidden' : ''; ?>>
					<?php if ( $args['sortable'] ) : ?><span class="ecsr-h-lead"></span><?php endif; ?>
					<span class="ecsr-h-badge"></span>
					<span class="ecsr-h-main"><?php echo esc_html( isset( $args['head'][0] ) ? $args['head'][0] : '' ); ?></span>
					<?php foreach ( $facts as $fact ) : ?>
						<span class="ecsr-h-fact"><?php echo esc_html( $fact ); ?></span>
					<?php endforeach; ?>
					<span class="ecsr-h-actions"></span>
				</div>
			<?php endif; ?>
			<ul class="ecsr-list" aria-label="<?php echo esc_attr( $args['label'] ); ?>"<?php echo $empty ? ' hidden' : ''; ?>>
				<?php
				foreach ( $args['rows'] as $row ) {
					$row['sortable'] = ! empty( $args['sortable'] ) && empty( $row['readonly'] ) && ( ! isset( $row['sortable'] ) || $row['sortable'] );
					$row['lead']     = ! empty( $args['sortable'] );
					$row['help']     = $help;
					self::print_row( $row );
				}
				?>
			</ul>
			<?php if ( $args['sortable'] ) : ?>
				<p class="screen-reader-text" id="<?php echo esc_attr( $help ); ?>"><?php esc_html_e( 'Use the up and down arrow keys to move it. The new order is saved right away.', 'wp-easycart' ); ?></p>
			<?php endif; ?>
			<div class="ecsr-empty-state"<?php echo $empty ? '' : ' hidden'; ?>>
				<span class="ecsr-empty-ic" aria-hidden="true"><?php echo self::icon_svg( 'truck' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG from wp_easycart_admin_settings_icons. ?></span>
				<p><?php echo esc_html( $args['empty'] ); ?></p>
				<?php if ( '' !== $args['add'] ) : ?>
					<button type="button" class="ecv2-btn ecv2-btn-primary ecsr-add"><?php echo self::plus_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $args['add'] ); ?></button>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $args['add'] ) : ?>
				<div class="ecsr-list-foot"<?php echo $empty ? ' hidden' : ''; ?>>
					<button type="button" class="ecv2-btn ecsr-add"><?php echo self::plus_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $args['add'] ); ?></button>
				</div>
			<?php endif; ?>
			<?php echo $args['after']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the caller's escaped HTML. ?>
		</div>
		<?php
	}

	/**
	 * One row: badge, title ( and a second line ), chips, the key settings, Edit and Delete. Shared with PRO.
	 *
	 * @since 6.0.2
	 * @param array $args {
	 *     @type int    $id       Row id ( data-id ).
	 *     @type string $type     data-type.
	 *     @type string $badge    Badge HTML ( type_badge(), carrier_badge(), provider_badge() ).
	 *     @type string $title    Title ( text ).
	 *     @type string $sub      Second line ( text ).
	 *     @type array  $chips    array( array( text, tone: '' | warn | muted ) ).
	 *     @type array  $facts    array( array( label, value, muted ) ), in the list's heading order.
	 *     @type string $note     Text shown across the facts instead of them ( read-only rows ).
	 *     @type array  $data     The row's values for the drawer ( data-row JSON ).
	 *     @type string $name     Name the Edit / Delete buttons say ( default the title ).
	 *     @type bool   $sortable Show the drag handle.
	 *     @type bool   $lead     The list has a handle column ( a row without a handle keeps the space ).
	 *     @type string $help     Id of the handle's keyboard help text.
	 *     @type bool   $readonly No Edit or Delete ( e.g. services an extension manages ).
	 *     @type array  $link     Read-only rows: array( url, text ).
	 * }
	 */
	public static function print_row( $args ) {
		$args = array_merge(
			array(
				'id'       => 0,
				'type'     => '',
				'badge'    => '',
				'title'    => '',
				'sub'      => '',
				'chips'    => array(),
				'facts'    => array(),
				'note'     => '',
				'data'     => array(),
				'name'     => '',
				'sortable' => false,
				'lead'     => false,
				'help'     => '',
				'readonly' => false,
				'link'     => array(),
			),
			(array) $args
		);
		$name     = '' !== $args['name'] ? $args['name'] : $args['title'];
		$readonly = ! empty( $args['readonly'] );
		$lead     = ! empty( $args['lead'] ) || ! empty( $args['sortable'] );
		$json     = function_exists( 'wp_json_encode' ) ? wp_json_encode( $args['data'] ) : json_encode( $args['data'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the migration-map generator loads this file outside WordPress.
		?>
		<li class="ecsr-item<?php echo $readonly ? ' is-readonly' : ''; ?>" data-id="<?php echo esc_attr( (string) $args['id'] ); ?>" data-type="<?php echo esc_attr( $args['type'] ); ?>" data-row="<?php echo esc_attr( (string) $json ); ?>">
			<?php if ( $lead ) : ?>
				<span class="ecsr-item-lead">
					<?php if ( ! empty( $args['sortable'] ) && ! $readonly ) : ?>
						<?php /* translators: %s: name of a shipping rate or service. */ ?>
						<button type="button" class="ecsr-grip" aria-label="<?php echo esc_attr( sprintf( __( 'Move %s', 'wp-easycart' ), $name ) ); ?>"<?php echo '' !== $args['help'] ? ' aria-describedby="' . esc_attr( $args['help'] ) . '"' : ''; ?> title="<?php esc_attr_e( 'Drag to reorder', 'wp-easycart' ); ?>"><?php echo self::grip_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<span class="ecsr-item-badge"><?php echo $args['badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the escaped badge helpers. ?></span>
			<div class="ecsr-item-main">
				<span class="ecsr-item-title"><?php echo esc_html( $args['title'] ); ?></span>
				<?php if ( '' !== $args['sub'] || ! empty( $args['chips'] ) ) : ?>
					<span class="ecsr-item-sub">
						<?php if ( '' !== $args['sub'] ) : ?><span class="ecsr-item-subtext"><?php echo esc_html( $args['sub'] ); ?></span><?php endif; ?>
						<?php foreach ( (array) $args['chips'] as $chip ) : ?>
							<?php $tone = isset( $chip[1] ) ? sanitize_key( $chip[1] ) : ''; ?>
							<span class="ecsr-chip<?php echo '' !== $tone ? ' is-' . esc_attr( $tone ) : ''; ?>"><?php echo esc_html( isset( $chip[0] ) ? $chip[0] : '' ); ?></span>
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</div>
			<div class="ecsr-item-facts<?php echo '' !== $args['note'] ? ' has-note' : ''; ?>">
				<?php if ( '' !== $args['note'] ) : ?>
					<span class="ecsr-item-note"><?php echo esc_html( $args['note'] ); ?></span>
				<?php else : ?>
					<?php foreach ( (array) $args['facts'] as $fact ) : ?>
						<span class="ecsr-fact<?php echo ! empty( $fact[2] ) ? ' is-muted' : ''; ?>"><span class="ecsr-fact-l"><?php echo esc_html( $fact[0] ); ?></span> <span class="ecsr-fact-v"><?php echo esc_html( $fact[1] ); ?></span></span>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<div class="ecsr-item-actions">
				<?php if ( $readonly ) : ?>
					<?php if ( ! empty( $args['link'][0] ) ) : ?>
						<a class="ecv2-btn ecv2-btn-sm ecsr-item-link" href="<?php echo esc_url( $args['link'][0] ); ?>"><?php echo esc_html( isset( $args['link'][1] ) ? $args['link'][1] : __( 'Settings', 'wp-easycart' ) ); ?></a>
					<?php endif; ?>
				<?php else : ?>
					<?php /* translators: %s: name of a shipping rate or service. */ ?>
					<button type="button" class="ecv2-btn ecv2-btn-sm ecsr-edit" aria-label="<?php echo esc_attr( sprintf( __( 'Edit %s', 'wp-easycart' ), $name ) ); ?>"><?php echo self::edit_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Edit', 'wp-easycart' ); ?></span></button>
					<?php /* translators: %s: name of a shipping rate or service. */ ?>
					<button type="button" class="ecsr-remove" aria-label="<?php echo esc_attr( sprintf( __( 'Delete %s', 'wp-easycart' ), $name ) ); ?>" title="<?php esc_attr_e( 'Delete', 'wp-easycart' ); ?>"><?php echo self::icon_svg( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG from wp_easycart_admin_settings_icons. ?></button>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Badges ( 6.0.2 )                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * The carriers a row can show a badge for: key => array( name, mark, bg, fg ) and optionally 'logo' ( an image URL ).
	 * WP EasyCart ships no carrier artwork, so the built-in carriers are drawn as a tile in the carrier's colour with the
	 * short form of its name ( the colours of the carrier headings on Settings › Shipping settings ). Filter
	 * wp_easycart_shipping_rate_carriers adds a carrier or gives one an image.
	 *
	 * @since 6.0.2
	 * @return array
	 */
	public static function carriers() {
		$carriers = array(
			'ups'        => array(
				'name' => 'UPS',
				'mark' => 'UPS',
				'bg'   => '#351c15',
				'fg'   => '#ffb500',
			),
			'usps'       => array(
				'name' => 'USPS',
				'mark' => 'USPS',
				'bg'   => '#333366',
				'fg'   => '#ffffff',
			),
			'fedex'      => array(
				'name' => 'FedEx',
				'mark' => 'FedEx',
				'bg'   => '#4d148c',
				'fg'   => '#ffffff',
			),
			'dhl'        => array(
				'name' => 'DHL',
				'mark' => 'DHL',
				'bg'   => '#ffcc00',
				'fg'   => '#d40511',
			),
			'auspost'    => array(
				'name' => __( 'Australia Post', 'wp-easycart' ),
				'mark' => 'AP',
				'bg'   => '#dc1928',
				'fg'   => '#ffffff',
			),
			'canadapost' => array(
				'name' => __( 'Canada Post', 'wp-easycart' ),
				'mark' => 'CP',
				'bg'   => '#c8102e',
				'fg'   => '#ffffff',
			),
			/* 6.0.3: WP EasyCart PRO's Royal Mail rates ( a rate provider, slug royalmail ): provider_badge() finds it here. */
			'royalmail'  => array(
				'name' => __( 'Royal Mail', 'wp-easycart' ),
				'mark' => 'RM',
				'bg'   => '#da202a',
				'fg'   => '#ffffff',
			),
		);
		return function_exists( 'apply_filters' ) ? (array) apply_filters( 'wp_easycart_shipping_rate_carriers', $carriers ) : $carriers;
	}

	/**
	 * A carrier key from a live rate's flag column ( is_ups_based → ups ) or a key.
	 *
	 * @since 6.0.2
	 * @param string $flag Flag column or key.
	 * @return string
	 */
	public static function carrier_key( $flag ) {
		$key = strtolower( (string) $flag );
		if ( 0 === strpos( $key, 'is_' ) ) {
			$key = substr( $key, 3 );
		}
		if ( '_based' === substr( $key, -6 ) ) {
			$key = substr( $key, 0, -6 );
		}
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}

	/**
	 * A carrier's name ( carriers() ), or the fallback.
	 *
	 * @since 6.0.2
	 * @param string $key      Carrier key or flag column.
	 * @param string $fallback Name when the carrier is not known.
	 * @return string
	 */
	public static function carrier_name( $key, $fallback = '' ) {
		$carriers = self::carriers();
		$key      = self::carrier_key( $key );
		return ( isset( $carriers[ $key ]['name'] ) && '' !== (string) $carriers[ $key ]['name'] ) ? (string) $carriers[ $key ]['name'] : $fallback;
	}

	/**
	 * Badge for a carrier ( live rates ). An unknown carrier gets a grey tile with its initials.
	 *
	 * @since 6.0.2
	 * @param string $key   Carrier key or flag column.
	 * @param string $name  Name for an unknown carrier.
	 * @param string $class Extra class ( e.g. is-small ).
	 * @return string
	 */
	public static function carrier_badge( $key, $name = '', $class = '' ) {
		$carriers = self::carriers();
		$key      = self::carrier_key( $key );
		if ( isset( $carriers[ $key ] ) && is_array( $carriers[ $key ] ) ) {
			$carrier = $carriers[ $key ];
			return self::badge_tile(
				isset( $carrier['mark'] ) ? (string) $carrier['mark'] : '',
				isset( $carrier['bg'] ) ? (string) $carrier['bg'] : '',
				isset( $carrier['fg'] ) ? (string) $carrier['fg'] : '',
				isset( $carrier['name'] ) ? (string) $carrier['name'] : $name,
				isset( $carrier['logo'] ) ? (string) $carrier['logo'] : '',
				trim( 'is-carrier is-' . $key . ' ' . $class )
			);
		}
		return self::badge_tile( self::initials( '' !== $name ? $name : $key ), '', '', $name, '', trim( 'is-carrier ' . $class ) );
	}

	/**
	 * Badge for a rate service an extension provides ( e.g. WP EasyCart for Shippo ): the logo tile its Extensions card
	 * shows, else its initials.
	 *
	 * @since 6.0.2
	 * @param string $slug  Provider slug ( the extension's catalog slug when they match ).
	 * @param string $name  Provider name.
	 * @param string $logo  Optional image URL the provider gives.
	 * @return string
	 */
	public static function provider_badge( $slug, $name, $logo = '' ) {
		$carriers = self::carriers();
		$slug     = self::carrier_key( $slug );
		if ( '' === $logo && isset( $carriers[ $slug ] ) ) {
			return self::carrier_badge( $slug, $name );
		}
		$mark  = '';
		$color = '';
		if ( class_exists( 'wp_easycart_admin_extensions' ) && method_exists( 'wp_easycart_admin_extensions', 'get' ) ) {
			$ext = wp_easycart_admin_extensions::get( $slug );
			if ( is_array( $ext ) ) {
				$mark  = isset( $ext['logo'] ) ? substr( (string) $ext['logo'], 0, 3 ) : '';
				$color = isset( $ext['color'] ) ? (string) $ext['color'] : '';
			}
		}
		return self::badge_tile( '' !== $mark ? $mark : self::initials( $name ), $color, '#ffffff', $name, $logo, 'is-provider' );
	}

	/**
	 * Badge for a free table's rows: its line icon.
	 *
	 * @since 6.0.2
	 * @param string $type Table type.
	 * @return string
	 */
	public static function type_badge( $type ) {
		$types = self::types();
		$icon  = isset( $types[ $type ]['icon'] ) ? $types[ $type ]['icon'] : 'truck';
		return '<span class="ecsr-badge is-type is-' . esc_attr( $type ) . '" aria-hidden="true">' . self::icon_svg( $icon, 'ecsr-badge-svg' ) . '</span>';
	}

	/** Two letters for a name ( "Fast Ship" → FS, "Shippo" → Sh ). */
	private static function initials( $name ) {
		$words = preg_split( '/[\s\-_]+/', trim( (string) $name ) );
		$words = array_values( array_filter( (array) $words, 'strlen' ) );
		if ( count( $words ) >= 2 ) {
			return strtoupper( substr( $words[0], 0, 1 ) . substr( $words[1], 0, 1 ) );
		}
		return isset( $words[0] ) ? ucfirst( substr( $words[0], 0, 2 ) ) : '?';
	}

	/** A badge tile: an image, or short text on a colour. */
	private static function badge_tile( $mark, $bg, $fg, $title, $logo, $class ) {
		$bg    = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $bg ) : $bg;
		$fg    = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $fg ) : $fg;
		$style = '';
		if ( $bg ) {
			$style .= '--ecsr-badge-bg:' . $bg . ';';
		}
		if ( $fg ) {
			$style .= '--ecsr-badge-fg:' . $fg . ';';
		}
		$mark  = (string) $mark;
		$size  = strlen( $mark ) >= 5 ? ' is-long' : ( strlen( $mark ) >= 4 ? ' is-wide' : '' );
		$inner = ( '' !== $logo )
			? '<img src="' . esc_url( $logo ) . '" alt="" loading="lazy" />'
			: '<span class="ecsr-badge-mark' . $size . '">' . esc_html( $mark ) . '</span>';
		return '<span class="ecsr-badge ' . esc_attr( trim( $class . ( '' !== $logo ? ' is-logo' : '' ) ) ) . '"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . ( '' !== $title ? ' title="' . esc_attr( $title ) . '"' : '' ) . ' aria-hidden="true">' . $inner . '</span>';
	}

	/** A line icon from the settings icon set. */
	private static function icon_svg( $name, $class = 'ecsr-ic' ) {
		return class_exists( 'wp_easycart_admin_settings_icons' ) ? wp_easycart_admin_settings_icons::svg( $name, $class ) : '';
	}

	/** Pencil for Edit. */
	private static function edit_svg() {
		return '<svg class="ecsr-ic" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
	}

	/** Plus for Add. */
	private static function plus_svg() {
		return '<svg class="ecsr-ic" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
	}

	/** Six dots for the drag handle. */
	private static function grip_svg() {
		return '<svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="2.5" cy="3" r="1.5"/><circle cx="7.5" cy="3" r="1.5"/><circle cx="2.5" cy="8" r="1.5"/><circle cx="7.5" cy="8" r="1.5"/><circle cx="2.5" cy="13" r="1.5"/><circle cx="7.5" cy="13" r="1.5"/></svg>';
	}

	/* ------------------------------------------------------------------ */
	/* Drawer ( 6.0.2 )                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * What the drawer shows for each free table: its titles and fields. settings-shipping-rates-v2.js builds the drawer
	 * from this ( ecsr_vars.types ); the field names are the request keys of ecv2_shipping_rate_add / _update.
	 * Field kinds: text, money, weight, int, percent, zone.
	 *
	 * @since 6.0.2
	 * @return array type => array( add, edit, save, hint, badge, fields )
	 */
	public static function drawer_types() {
		$types = self::types();
		$zone  = array(
			'name' => 'zone_id',
			'kind' => 'zone',
		);
		$rate  = array(
			'name'        => 'rate',
			'kind'        => 'money',
			'label'       => __( 'Rate', 'wp-easycart' ),
			'placeholder' => self::money( 0 ),
		);
		$from  = __( 'Applies from this amount until a row with a higher amount takes over.', 'wp-easycart' );
		$out   = array(
			'flat'       => array(
				'add'    => __( 'Add a shipping method', 'wp-easycart' ),
				'edit'   => __( 'Edit shipping method', 'wp-easycart' ),
				'save'   => __( 'Add method', 'wp-easycart' ),
				'fields' => array(
					array(
						'name'        => 'label',
						'kind'        => 'text',
						'label'       => __( 'Method name', 'wp-easycart' ),
						'hint'        => __( 'What shoppers see at checkout.', 'wp-easycart' ),
						'placeholder' => __( 'e.g. Standard delivery', 'wp-easycart' ),
						'required'    => true,
					),
					array_merge( $rate, array( 'hint' => __( 'What the shopper pays for this method.', 'wp-easycart' ) ) ),
					array(
						'name'        => 'free_at',
						'kind'        => 'money',
						'label'       => __( 'Free shipping at', 'wp-easycart' ),
						'hint'        => __( 'The method becomes free once the cart total reaches this amount. Leave it empty to always charge the rate.', 'wp-easycart' ),
						'placeholder' => __( 'Never', 'wp-easycart' ),
						'optional'    => true,
					),
					array(
						'name'  => 'order',
						'kind'  => 'int',
						'label' => __( 'Position', 'wp-easycart' ),
						'hint'  => __( 'Methods are listed from the lowest number up. You can also drag them into order in the list.', 'wp-easycart' ),
					),
					array_merge( $zone, array( 'hint' => __( 'Only addresses in this zone are offered this method.', 'wp-easycart' ) ) ),
				),
			),
			'price'      => array(
				'add'    => __( 'Add a cart total rate', 'wp-easycart' ),
				'edit'   => __( 'Edit cart total rate', 'wp-easycart' ),
				'fields' => array(
					array(
						'name'        => 'trigger',
						'kind'        => 'money',
						'label'       => __( 'Cart total from', 'wp-easycart' ),
						'hint'        => $from,
						'placeholder' => self::money( 0 ),
					),
					$rate,
					$zone,
				),
			),
			'weight'     => array(
				'add'    => __( 'Add a weight rate', 'wp-easycart' ),
				'edit'   => __( 'Edit weight rate', 'wp-easycart' ),
				'fields' => array(
					array(
						'name'        => 'trigger',
						'kind'        => 'weight',
						'label'       => __( 'Order weight from', 'wp-easycart' ),
						'hint'        => __( 'Applies from this weight until a row with a higher weight takes over. In the unit your products are weighed in.', 'wp-easycart' ),
						'placeholder' => '0',
						'unit'        => self::weight_unit(),
					),
					$rate,
					$zone,
				),
			),
			'quantity'   => array(
				'add'    => __( 'Add a quantity rate', 'wp-easycart' ),
				'edit'   => __( 'Edit quantity rate', 'wp-easycart' ),
				'fields' => array(
					array(
						'name'        => 'trigger',
						'kind'        => 'int',
						'label'       => __( 'Items in the cart from', 'wp-easycart' ),
						'hint'        => __( 'Applies from this many items until a row with a higher count takes over.', 'wp-easycart' ),
						'placeholder' => '0',
					),
					$rate,
					$zone,
				),
			),
			'percentage' => array(
				'add'    => __( 'Add a percentage rate', 'wp-easycart' ),
				'edit'   => __( 'Edit percentage rate', 'wp-easycart' ),
				'fields' => array(
					array(
						'name'        => 'trigger',
						'kind'        => 'money',
						'label'       => __( 'Cart total from', 'wp-easycart' ),
						'hint'        => $from,
						'placeholder' => self::money( 0 ),
					),
					array(
						'name'        => 'rate',
						'kind'        => 'percent',
						'label'       => __( 'Percent of the cart total', 'wp-easycart' ),
						'hint'        => __( 'Shipping costs this share of the cart total.', 'wp-easycart' ),
						'placeholder' => '0',
					),
					$zone,
				),
			),
		);
		foreach ( $out as $type => $def ) {
			$out[ $type ]['hint']  = $types[ $type ]['title'];
			$out[ $type ]['badge'] = self::type_badge( $type );
			if ( ! isset( $def['save'] ) ) {
				$out[ $type ]['save'] = __( 'Add rate', 'wp-easycart' );
			}
		}
		return $out;
	}

	/**
	 * The zones for the drawer's picker, in order: array( array( id, name ) ).
	 *
	 * @since 6.0.2
	 * @return array
	 */
	public static function zone_options() {
		$out = array();
		foreach ( self::zones() as $zone_id => $zone_name ) {
			$out[] = array( (int) $zone_id, (string) $zone_name );
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Render: locked live rates                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * html row inside the locked live-rates section: says what PRO adds and shows
	 * a mock of the list. Prints nothing once PRO is licensed — the PRO page filter
	 * attaches the real list as the section render.
	 */
	public static function render_live_note( $field, $page ) {
		if ( self::pro_on() ) {
			return;
		}
		$mock = array(
			array( 'ups', __( 'UPS Ground', 'wp-easycart' ), 'UPS Ground', __( 'Carrier rate', 'wp-easycart' ), self::money_display( 75 ) ),
			array( 'usps', __( 'Priority Mail ( 1–3 days )', 'wp-easycart' ), 'USPS Priority Mail', __( 'Carrier rate', 'wp-easycart' ), __( 'Never', 'wp-easycart' ) ),
			array( 'fedex', __( 'FedEx 2Day', 'wp-easycart' ), 'FedEx 2Day', self::money_display( 24 ), __( 'Never', 'wp-easycart' ) ),
		);
		$rows = array();
		foreach ( $mock as $m ) {
			$rows[] = array(
				'id'    => 0,
				'type'  => 'mock',
				'badge' => self::carrier_badge( $m[0] ),
				'title' => $m[1],
				'sub'   => $m[2],
				'facts' => array(
					array( __( 'Price', 'wp-easycart' ), $m[3], false ),
					array( __( 'Free shipping at', 'wp-easycart' ), $m[4], __( 'Never', 'wp-easycart' ) === $m[4] ),
					array( __( 'Zone', 'wp-easycart' ), __( 'Any destination', 'wp-easycart' ), false ),
				),
			);
		}
		?>
		<p class="ecst-row-desc"><?php esc_html_e( 'Quote real rates from Australia Post, Canada Post, DHL, FedEx, UPS or USPS at checkout. Choose which services shoppers see, rename them, fix a price instead of the carrier’s, add free-shipping thresholds and drag them into order.', 'wp-easycart' ); ?></p>
		<div class="ecsr ecsr-live ecsr-mock" aria-hidden="true" inert>
			<?php
			self::print_list(
				array(
					'type'     => 'mock',
					'label'    => __( 'Live carrier rates', 'wp-easycart' ),
					'head'     => array( __( 'Service', 'wp-easycart' ), array( __( 'Price', 'wp-easycart' ), __( 'Free shipping at', 'wp-easycart' ), __( 'Zone', 'wp-easycart' ) ) ),
					'rows'     => $rows,
					'sortable' => true,
				)
			);
			?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Assets                                                              */
	/* ------------------------------------------------------------------ */

	/** Page-level enqueue ( called by the engine on admin_enqueue_scripts ). */
	public static function enqueue( $page ) {
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$css = plugins_url( '/admin/css/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		$js  = plugins_url( '/admin/js/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		wp_enqueue_style( 'wp_easycart_admin_settings_shipping_rates_v2_css', $css . 'settings-shipping-rates-v2.css', array( 'wp_easycart_admin_settings_page_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_script( 'wp_easycart_admin_settings_shipping_rates_v2_js', $js . 'settings-shipping-rates-v2.js', array( 'jquery', 'jquery-ui-sortable', 'wp_easycart_admin_settings_page_v2_js' ), EC_CURRENT_VERSION, true );
		$zones_url = class_exists( 'wp_easycart_admin_settings_registry' ) ? wp_easycart_admin_settings_registry::page_url( 'shipping-settings' ) . '#ecst-sec-zones' : admin_url( 'admin.php?page=wp-easycart-settings&subpage=shipping-settings' );
		wp_localize_script(
			'wp_easycart_admin_settings_shipping_rates_v2_js',
			'ecsr_vars',
			array(
				'ajax'         => admin_url( 'admin-ajax.php' ),
				'nonce'        => class_exists( 'wp_easycart_admin_settings_registry' ) ? wp_create_nonce( wp_easycart_admin_settings_registry::NONCE ) : '',
				'method'       => self::method(),
				'symbol'       => self::symbol(),
				'symbol_first' => self::symbol_first() ? 1 : 0,
				'weight_unit'  => self::weight_unit(),
				'zones'        => self::zone_options(),
				'zones_url'    => $zones_url,
				'types'        => self::drawer_types(),
				'i18n'         => array(
					'saving'        => __( 'Saving…', 'wp-easycart' ),
					'saved'         => __( 'Saved', 'wp-easycart' ),
					'failed'        => __( 'Could not save', 'wp-easycart' ),
					'added'         => __( 'Rate added.', 'wp-easycart' ),
					'deleted'       => __( 'Rate deleted.', 'wp-easycart' ),
					'not_in_use'    => __( 'Not in use', 'wp-easycart' ),
					'show'          => __( 'Show', 'wp-easycart' ),
					'hide'          => __( 'Hide', 'wp-easycart' ),
					'label_needed'  => __( 'Give the method a name first.', 'wp-easycart' ),
					'number_needed' => __( 'Enter a number of 0 or more.', 'wp-easycart' ),
					'required'      => __( 'This is required.', 'wp-easycart' ),
					'confirm_title' => __( 'Delete this rate?', 'wp-easycart' ),
					'confirm_text'  => __( 'Shoppers will no longer be offered it. This cannot be undone.', 'wp-easycart' ),
					/* translators: %s: shipping method name, e.g. "By weight". */
					'method_saved'  => __( 'Shipping method changed to %s.', 'wp-easycart' ),
					'method_failed' => __( 'The shipping method could not be changed.', 'wp-easycart' ),
					'close'         => __( 'Close', 'wp-easycart' ),
					'cancel'        => __( 'Cancel', 'wp-easycart' ),
					'save'          => __( 'Save changes', 'wp-easycart' ),
					'delete'        => __( 'Delete', 'wp-easycart' ),
					'optional'      => __( 'Optional', 'wp-easycart' ),
					'zone'          => __( 'Zone', 'wp-easycart' ),
					'manage_zones'  => __( 'Manage zones', 'wp-easycart' ),
					'discard_title' => __( 'Discard your changes?', 'wp-easycart' ),
					'discard_text'  => __( 'What you changed in this panel has not been saved.', 'wp-easycart' ),
					'discard'       => __( 'Discard', 'wp-easycart' ),
					'order_saved'   => __( 'Order saved.', 'wp-easycart' ),
					/* translators: 1: new position, 2: number of rows. */
					'moved'         => __( 'Moved to position %1$d of %2$d.', 'wp-easycart' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* AJAX ( every handler opens with ecv2_settings_guard(): cap + nonce ) */
	/* ------------------------------------------------------------------ */

	/**
	 * A number from the request, normalised the way the classic handlers did
	 * ( wp_easycart_admin_verification()->filter_float() ): digits, dot and comma
	 * only; a lone comma is treated as the decimal mark. Returns a float, '' for an
	 * empty value, or WP_Error.
	 */
	private static function number( $raw ) {
		$text = trim( (string) $raw );
		if ( '' === $text ) {
			return '';
		}
		$text = preg_replace( '/[^0-9\.,\-]/', '', $text );
		if ( false === strpos( $text, '.' ) ) {
			$text = str_replace( ',', '.', $text );
		} else {
			$text = str_replace( ',', '', $text );
		}
		if ( ! is_numeric( $text ) ) {
			return new WP_Error( 'number', __( 'Enter a number.', 'wp-easycart' ) );
		}
		$number = (float) $text;
		if ( $number < 0 ) {
			return new WP_Error( 'min', __( 'Must be 0 or more.', 'wp-easycart' ) );
		}
		return $number;
	}

	/** Zone id from the request: 0 or an existing zone. */
	private static function zone( $raw ) {
		$zone_id = (int) $raw;
		$zones = self::zones();
		return isset( $zones[ $zone_id ] ) ? $zone_id : 0;
	}

	/** Both classic caches the storefront reads rates through. */
	private static function flush() {
		wp_cache_delete( 'wpeasycart-config-get-rates', 'wpeasycart-shipping' );
		wp_cache_delete( 'wpeasycart-shipping-data', 'wpeasycart-shipping' );
	}

	/**
	 * Read and validate the editable values of one row from $_POST. Called only
	 * after ecv2_settings_guard(). Returns array( values ) or WP_Error.
	 */
	private static function read_values( $type ) {
		$values = array(
			'label'   => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
			'rate'    => self::number( isset( $_POST['rate'] ) ? sanitize_text_field( wp_unslash( $_POST['rate'] ) ) : '' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
			'trigger' => self::number( isset( $_POST['trigger'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger'] ) ) : '' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
			'free_at' => self::number( isset( $_POST['free_at'] ) ? sanitize_text_field( wp_unslash( $_POST['free_at'] ) ) : '' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
			'order'   => isset( $_POST['order'] ) ? (int) $_POST['order'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
			'zone_id' => self::zone( isset( $_POST['zone_id'] ) ? (int) $_POST['zone_id'] : 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
		);
		foreach ( array( 'rate', 'trigger', 'free_at' ) as $key ) {
			if ( is_wp_error( $values[ $key ] ) ) {
				return $values[ $key ];
			}
		}
		$values['rate']    = ( '' === $values['rate'] ) ? 0.0 : (float) $values['rate'];
		$values['trigger'] = ( '' === $values['trigger'] ) ? 0.0 : (float) $values['trigger'];
		$values['free_at'] = ( '' === $values['free_at'] ) ? -1.0 : (float) $values['free_at'];
		if ( 'quantity' === $type ) {
			$values['trigger'] = (float) (int) round( $values['trigger'] );
		}
		if ( 'flat' === $type && '' === $values['label'] ) {
			return new WP_Error( 'label', __( 'Give the method a name first.', 'wp-easycart' ) );
		}
		return $values;
	}

	/** The type named in the request, or dies. Called after ecv2_settings_guard(). */
	private static function requested_type() {
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
		$types = self::types();
		if ( ! isset( $types[ $type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown rate table.', 'wp-easycart' ) ) );
		}
		return $type;
	}

	/**
	 * POST type + row values → inserts a row of that type. Ports the classic add_shipping_*() methods.
	 * 6.0.2: the answer also carries the new row's markup ( html ).
	 */
	public static function ajax_add() {
		ecv2_settings_guard();
		global $wpdb;
		$type   = self::requested_type();
		$values = self::read_values( $type );
		if ( is_wp_error( $values ) ) {
			wp_send_json_error( array( 'message' => $values->get_error_message() ) );
		}
		$wpdb->query( $wpdb->prepare(
			'INSERT INTO ec_shippingrate ( is_method_based, is_price_based, is_weight_based, is_quantity_based, is_percentage_based, trigger_rate, shipping_rate, shipping_label, shipping_order, zone_id, free_shipping_at ) VALUES ( %d, %d, %d, %d, %d, %f, %f, %s, %d, %d, %f )',
			'flat' === $type ? 1 : 0,
			'price' === $type ? 1 : 0,
			'weight' === $type ? 1 : 0,
			'quantity' === $type ? 1 : 0,
			'percentage' === $type ? 1 : 0,
			'flat' === $type ? 0 : $values['trigger'],
			$values['rate'],
			'flat' === $type ? $values['label'] : '',
			'flat' === $type ? $values['order'] : 0,
			$values['zone_id'],
			'flat' === $type ? $values['free_at'] : -1
		) );
		$id = (int) $wpdb->insert_id;
		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'The rate could not be saved.', 'wp-easycart' ) ) );
		}
		self::flush();
		do_action( 'wpeasycart_shipping_rate_added', $id );
		$row = self::row_data( $type, self::rate( $type, $id ) );
		wp_send_json_success( array(
			'id'      => $id,
			'row'     => $row,
			'html'    => self::row_html( $type, $row ),
			'message' => __( 'Rate added.', 'wp-easycart' ),
		) );
	}

	/**
	 * POST type, id + row values → updates that row. Ports the classic update_shipping_*_triggers() methods, one row
	 * at a time. 6.0.2: the answer also carries the row's markup ( html ).
	 */
	public static function ajax_update() {
		ecv2_settings_guard();
		global $wpdb;
		$type = self::requested_type();
		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() above.
		if ( $id <= 0 || null === self::rate( $type, $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That rate no longer exists. Reload the page.', 'wp-easycart' ) ) );
		}
		$values = self::read_values( $type );
		if ( is_wp_error( $values ) ) {
			wp_send_json_error( array( 'message' => $values->get_error_message() ) );
		}
		if ( 'flat' === $type ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ec_shippingrate SET shipping_label = %s, shipping_rate = %f, zone_id = %d, free_shipping_at = %f, shipping_order = %d WHERE shippingrate_id = %d AND is_method_based = 1',
				$values['label'],
				$values['rate'],
				$values['zone_id'],
				$values['free_at'],
				$values['order'],
				$id
			) );
		} else {
			$wpdb->query( $wpdb->prepare(
				"UPDATE ec_shippingrate SET trigger_rate = %f, shipping_rate = %f, zone_id = %d WHERE shippingrate_id = %d AND ( CASE %s WHEN 'flat' THEN is_method_based WHEN 'price' THEN is_price_based WHEN 'weight' THEN is_weight_based WHEN 'quantity' THEN is_quantity_based WHEN 'percentage' THEN is_percentage_based ELSE 0 END ) = 1",
				$values['trigger'],
				$values['rate'],
				$values['zone_id'],
				$id,
				$type
			) );
		}
		self::flush();
		$row = self::row_data( $type, self::rate( $type, $id ) );
		wp_send_json_success( array(
			'id'      => $id,
			'row'     => $row,
			'html'    => self::row_html( $type, $row ),
			'message' => __( 'Saved.', 'wp-easycart' ),
		) );
	}

	/** POST type, id → deletes that row. Ports the classic delete_shipping_rate(), limited to the five free tables. */
	public static function ajax_delete() {
		ecv2_settings_guard();
		global $wpdb;
		$type = self::requested_type();
		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() above.
		if ( $id <= 0 || null === self::rate( $type, $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That rate no longer exists. Reload the page.', 'wp-easycart' ) ) );
		}
		do_action( 'wpeasycart_shipping_rate_deleting', $id );
		$wpdb->query( $wpdb->prepare( "DELETE FROM ec_shippingrate WHERE shippingrate_id = %d AND ( CASE %s WHEN 'flat' THEN is_method_based WHEN 'price' THEN is_price_based WHEN 'weight' THEN is_weight_based WHEN 'quantity' THEN is_quantity_based WHEN 'percentage' THEN is_percentage_based ELSE 0 END ) = 1", $id, $type ) );
		self::flush();
		wp_send_json_success( array(
			'id'      => $id,
			'count'   => count( self::rates( $type ) ),
			'message' => __( 'Rate deleted.', 'wp-easycart' ),
		) );
	}

	/** Register the AJAX handlers once ( the file is included from admin-init and from the declaration ). */
	public static function register() {
		if ( ! function_exists( 'add_action' ) || ! function_exists( 'has_action' ) || has_action( 'wp_ajax_ecv2_shipping_rate_add', array( __CLASS__, 'ajax_add' ) ) ) {
			return;
		}
		add_action( 'wp_ajax_ecv2_shipping_rate_add', array( __CLASS__, 'ajax_add' ) );
		add_action( 'wp_ajax_ecv2_shipping_rate_update', array( __CLASS__, 'ajax_update' ) );
		add_action( 'wp_ajax_ecv2_shipping_rate_delete', array( __CLASS__, 'ajax_delete' ) );
	}
}

wp_easycart_admin_shipping_rates_v2::register();

endif;
