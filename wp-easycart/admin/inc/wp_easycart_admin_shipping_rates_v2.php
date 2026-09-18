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

	/** A weight or count for an input: trailing zeros dropped. */
	public static function plain_number( $value ) {
		$text = rtrim( rtrim( number_format( (float) $value, 3, '.', '' ), '0' ), '.' );
		return ( '' === $text || '-0' === $text ) ? '0' : $text;
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
	/* Render                                                              */
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

	/** Section render for each of the five tables: the section slug is the type. */
	public static function render_section( $page, $section ) {
		$type = self::type_for_section( isset( $section['slug'] ) ? $section['slug'] : '' );
		if ( '' === $type ) {
			return;
		}
		self::render_table( $type );
	}

	/** The editable table for one type. */
	public static function render_table( $type ) {
		$types   = self::types();
		$rows    = self::rates( $type );
		$columns = self::columns( $type );
		$active  = ( $types[ $type ]['method'] === self::method() ) ? '1' : '0';
		?>
		<div class="ecsr" data-type="<?php echo esc_attr( $type ); ?>" data-active="<?php echo esc_attr( $active ); ?>">
			<p class="ecsr-desc"><?php echo esc_html( $types[ $type ]['desc'] ); ?></p>
			<div class="ecsr-scroll">
				<table class="ecsr-table ecsr-table-<?php echo esc_attr( $type ); ?>">
					<thead>
						<tr>
							<?php foreach ( $columns as $column ) : ?>
								<th class="ecsr-th-<?php echo esc_attr( $column[1] ); ?>"><?php echo esc_html( $column[2] ); ?></th>
							<?php endforeach; ?>
							<th class="ecsr-th-state"><span class="screen-reader-text"><?php esc_html_e( 'Status', 'wp-easycart' ); ?></span></th>
							<th class="ecsr-th-del"><span class="screen-reader-text"><?php esc_html_e( 'Delete', 'wp-easycart' ); ?></span></th>
						</tr>
					</thead>
					<tbody class="ecsr-body">
						<?php foreach ( $rows as $row ) : ?>
							<?php self::render_row( $type, self::row_data( $type, $row ), '' ); ?>
						<?php endforeach; ?>
						<?php self::render_row( $type, self::row_data( $type, null ), 'ecsr-tpl' ); ?>
						<tr class="ecsr-empty"<?php echo empty( $rows ) ? '' : ' hidden'; ?>><td colspan="<?php echo (int) count( $columns ) + 2; ?>"><?php esc_html_e( 'No rates yet. Add the first one below.', 'wp-easycart' ); ?></td></tr>
					</tbody>
					<tfoot>
						<tr class="ecsr-new">
							<?php foreach ( $columns as $column ) : ?>
								<td class="ecsr-cell ecsr-cell-<?php echo esc_attr( $column[1] ); ?>" data-label="<?php echo esc_attr( $column[2] ); ?>"><?php self::render_control( $type, $column, self::row_data( $type, null ), true ); ?></td>
							<?php endforeach; ?>
							<td colspan="2" class="ecsr-cell-add"><button type="button" class="ecv2-btn ecv2-btn-primary ecsr-add"><?php esc_html_e( 'Add rate', 'wp-easycart' ); ?></button></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>
		<?php
	}

	private static function render_row( $type, $data, $extra_class ) {
		$columns = self::columns( $type );
		?>
		<tr class="ecsr-row<?php echo '' !== $extra_class ? ' ' . esc_attr( $extra_class ) : ''; ?>" data-id="<?php echo (int) $data['id']; ?>" data-type="<?php echo esc_attr( $type ); ?>"<?php echo 'ecsr-tpl' === $extra_class ? ' hidden' : ''; ?>>
			<?php foreach ( $columns as $column ) : ?>
				<td class="ecsr-cell ecsr-cell-<?php echo esc_attr( $column[1] ); ?>" data-label="<?php echo esc_attr( $column[2] ); ?>"><?php self::render_control( $type, $column, $data, false ); ?></td>
			<?php endforeach; ?>
			<td class="ecsr-cell-state"><span class="ecsr-state" aria-live="polite"></span></td>
			<td class="ecsr-cell-del"><button type="button" class="ecsr-del" title="<?php esc_attr_e( 'Delete this rate', 'wp-easycart' ); ?>" aria-label="<?php esc_attr_e( 'Delete this rate', 'wp-easycart' ); ?>">×</button></td>
		</tr>
		<?php
	}

	/** One input. $is_new marks the "Add rate" row ( no value, hint placeholders ). */
	private static function render_control( $type, $column, $data, $is_new ) {
		list( $field, $kind, $heading ) = $column;
		$value  = isset( $data[ $field ] ) ? $data[ $field ] : '';
		$symbol = self::symbol();
		$class  = 'ecv2-input ecsr-in';
		switch ( $kind ) {
			case 'zone':
				?>
				<select class="ecv2-select ecsr-in" data-field="zone_id" aria-label="<?php echo esc_attr( $heading ); ?>">
					<?php foreach ( self::zones() as $zone_id => $zone_name ) : ?>
						<option value="<?php echo (int) $zone_id; ?>"<?php selected( (int) $value, (int) $zone_id ); ?>><?php echo esc_html( $zone_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
				break;
			case 'text':
				?>
				<input type="text" class="<?php echo esc_attr( $class ); ?>" data-field="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $is_new ? __( 'e.g. Standard delivery', 'wp-easycart' ) : '' ); ?>" aria-label="<?php echo esc_attr( $heading ); ?>" autocomplete="off" />
				<?php
				break;
			case 'int':
				$placeholder = ( 'order' === $field ) ? '0' : '1';
				?>
				<span class="ecsr-in-wrap ecsr-in-short"><input type="number" step="1" min="0" class="<?php echo esc_attr( $class ); ?>" data-field="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" aria-label="<?php echo esc_attr( $heading ); ?>" /></span>
				<?php
				break;
			case 'weight':
				?>
				<span class="ecsr-in-wrap ecsr-in-short"><input type="number" step="0.01" min="0" class="<?php echo esc_attr( $class ); ?>" data-field="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="0" aria-label="<?php echo esc_attr( $heading ); ?>" /></span>
				<?php
				break;
			case 'percent':
				?>
				<span class="ecsr-in-wrap ecsr-in-short has-unit"><input type="number" step="0.01" min="0" class="<?php echo esc_attr( $class ); ?>" data-field="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="0" aria-label="<?php echo esc_attr( $heading ); ?>" /><span class="ecsr-affix">%</span></span>
				<?php
				break;
			default: // money
				$placeholder = ( 'free_at' === $field ) ? __( 'Never', 'wp-easycart' ) : self::money( 0 );
				?>
				<span class="ecsr-in-wrap<?php echo '' !== $symbol ? ' has-prefix' : ''; ?>"><?php if ( '' !== $symbol ) : ?><span class="ecsr-affix"><?php echo esc_html( $symbol ); ?></span><?php endif; ?><input type="number" step="0.01" min="0" class="<?php echo esc_attr( $class ); ?>" data-field="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" aria-label="<?php echo esc_attr( $heading ); ?>" /></span>
				<?php
		}
	}

	/**
	 * html row inside the locked live-rates section: says what PRO adds and shows
	 * a mock of the list. Prints nothing once PRO is licensed — the PRO page filter
	 * attaches the real list as the section render.
	 */
	public static function render_live_note( $field, $page ) {
		if ( self::pro_on() ) {
			return;
		}
		$symbol = self::symbol();
		$mock = array(
			array( 'UPS', 'Ground', __( 'UPS Ground', 'wp-easycart' ), '', '75.00' ),
			array( 'USPS', 'Priority Mail', __( 'Priority Mail ( 1–3 days )', 'wp-easycart' ), '', '' ),
			array( 'FedEx', '2Day', __( 'FedEx 2Day', 'wp-easycart' ), '24.00', '' ),
		);
		?>
		<p class="ecst-row-desc"><?php esc_html_e( 'Quote real rates from Australia Post, Canada Post, DHL, FedEx, UPS or USPS at checkout. Choose which services shoppers see, rename them, fix a price instead of the carrier’s, add free-shipping thresholds and drag them into order.', 'wp-easycart' ); ?></p>
		<div class="ecsr ecsr-live ecsr-mock" aria-hidden="true">
			<div class="ecsr-live-list">
				<div class="ecsr-lrow ecsr-lhead">
					<span class="ecsr-lc-grip"></span>
					<span class="ecsr-lc-carrier"><?php esc_html_e( 'Carrier', 'wp-easycart' ); ?></span>
					<span class="ecsr-lc-service"><?php esc_html_e( 'Service', 'wp-easycart' ); ?></span>
					<span class="ecsr-lc-label"><?php esc_html_e( 'Shown as', 'wp-easycart' ); ?></span>
					<span class="ecsr-lc-override"><?php esc_html_e( 'Fixed price', 'wp-easycart' ); ?></span>
					<span class="ecsr-lc-free"><?php esc_html_e( 'Free shipping at', 'wp-easycart' ); ?></span>
					<span class="ecsr-lc-zone"><?php esc_html_e( 'Zone', 'wp-easycart' ); ?></span>
				</div>
				<div class="ecsr-live-body">
					<?php foreach ( $mock as $m ) : ?>
						<div class="ecsr-row ecsr-lrow">
							<span class="ecsr-lc ecsr-lc-grip"><span class="ecsr-grip">⋮⋮</span></span>
							<div class="ecsr-lc ecsr-lc-carrier" data-label="<?php esc_attr_e( 'Carrier', 'wp-easycart' ); ?>"><select class="ecv2-select" disabled><option><?php echo esc_html( $m[0] ); ?></option></select></div>
							<div class="ecsr-lc ecsr-lc-service" data-label="<?php esc_attr_e( 'Service', 'wp-easycart' ); ?>"><select class="ecv2-select" disabled><option><?php echo esc_html( $m[1] ); ?></option></select></div>
							<div class="ecsr-lc ecsr-lc-label" data-label="<?php esc_attr_e( 'Shown as', 'wp-easycart' ); ?>"><input type="text" class="ecv2-input" value="<?php echo esc_attr( $m[2] ); ?>" disabled /></div>
							<div class="ecsr-lmore">
								<span class="ecsr-lc ecsr-lc-override"><span class="ecsr-llabel"><?php esc_html_e( 'Fixed price', 'wp-easycart' ); ?></span><span class="ecsr-in-wrap has-prefix"><span class="ecsr-affix"><?php echo esc_html( $symbol ); ?></span><input type="text" class="ecv2-input" value="<?php echo esc_attr( $m[3] ); ?>" placeholder="<?php esc_attr_e( 'Carrier rate', 'wp-easycart' ); ?>" disabled /></span></span>
								<span class="ecsr-lc ecsr-lc-free"><span class="ecsr-llabel"><?php esc_html_e( 'Free shipping at', 'wp-easycart' ); ?></span><span class="ecsr-in-wrap has-prefix"><span class="ecsr-affix"><?php echo esc_html( $symbol ); ?></span><input type="text" class="ecv2-input" value="<?php echo esc_attr( $m[4] ); ?>" placeholder="<?php esc_attr_e( 'Never', 'wp-easycart' ); ?>" disabled /></span></span>
								<span class="ecsr-lc ecsr-lc-zone"><span class="ecsr-llabel"><?php esc_html_e( 'Zone', 'wp-easycart' ); ?></span><select class="ecv2-select" disabled><option><?php esc_html_e( 'Any destination', 'wp-easycart' ); ?></option></select></span>
							</div>
							<span class="ecsr-lc ecsr-lc-del"><span class="ecsr-del">×</span></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
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
		wp_enqueue_script( 'wp_easycart_admin_settings_shipping_rates_v2_js', $js . 'settings-shipping-rates-v2.js', array( 'jquery', 'wp_easycart_admin_settings_page_v2_js' ), EC_CURRENT_VERSION, true );
		wp_localize_script( 'wp_easycart_admin_settings_shipping_rates_v2_js', 'ecsr_vars', array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => class_exists( 'wp_easycart_admin_settings_registry' ) ? wp_create_nonce( wp_easycart_admin_settings_registry::NONCE ) : '',
			'method' => self::method(),
			'i18n'   => array(
				'saving'        => __( 'Saving…', 'wp-easycart' ),
				'saved'         => __( 'Saved', 'wp-easycart' ),
				'failed'        => __( 'Could not save', 'wp-easycart' ),
				'added'         => __( 'Rate added.', 'wp-easycart' ),
				'deleted'       => __( 'Rate deleted.', 'wp-easycart' ),
				'not_in_use'    => __( 'Not in use', 'wp-easycart' ),
				'show'          => __( 'Show', 'wp-easycart' ),
				'hide'          => __( 'Hide', 'wp-easycart' ),
				'label_needed'  => __( 'Give the method a name first.', 'wp-easycart' ),
				'number_needed' => __( 'Enter a number.', 'wp-easycart' ),
				'confirm_title' => __( 'Delete this rate?', 'wp-easycart' ),
				'confirm_text'  => __( 'Shoppers will no longer be offered it. This cannot be undone.', 'wp-easycart' ),
				/* translators: %s: shipping method name, e.g. "By weight". */
				'method_saved'  => __( 'Shipping method changed to %s.', 'wp-easycart' ),
				'method_failed' => __( 'The shipping method could not be changed.', 'wp-easycart' ),
			),
		) );
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

	/** POST type + row values → inserts a row of that type. Ports the classic add_shipping_*() methods. */
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
		wp_send_json_success( array(
			'id'      => $id,
			'row'     => self::row_data( $type, self::rate( $type, $id ) ),
			'message' => __( 'Rate added.', 'wp-easycart' ),
		) );
	}

	/** POST type, id + row values → updates that row. Ports the classic update_shipping_*_triggers() methods, one row at a time. */
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
		wp_send_json_success( array(
			'id'      => $id,
			'row'     => self::row_data( $type, self::rate( $type, $id ) ),
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
