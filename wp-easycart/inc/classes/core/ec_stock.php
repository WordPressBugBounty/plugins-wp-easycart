<?php
/**
 * WP EasyCart — the store's low stock threshold.
 *
 * One number decides everything that calls stock "low": the Low chip, stat card
 * and filters on the Inventory and Products screens, the PRO low stock digest,
 * and the low stock admin emails.
 *
 * Canonical option: ec_option_low_stock_trigger_total ( Settings > Checkout >
 * Stock alerts ). It predates 6.0.0, so long-standing merchant values are the
 * ones that survive. ec_option_inventory_low_stock_threshold, added during 6.0.0
 * development for the Inventory screen, is a read-only legacy alias: it is copied
 * into the canonical key by the 6.0.0 upgrade and only ever read again when the
 * canonical key is missing.
 *
 * Precedence for a single item:
 *   variant reorder point  ( ec_optionitemquantity.reorder_point, PRO )
 *   product reorder point  ( ec_product.reorder_point, PRO )
 *   store-wide setting
 * A reorder point of -1 means "not set, use the next one down".
 *
 * @since 6.0.0
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'EC_DEFAULT_LOW_STOCK_THRESHOLD' ) ) {
	/** What a brand new store flags as low stock until the merchant changes it. */
	define( 'EC_DEFAULT_LOW_STOCK_THRESHOLD', 10 );
}

if ( ! function_exists( 'wp_easycart_store_low_stock_threshold' ) ) {
	/**
	 * The store-wide number, before any per-product or per-variant reorder point.
	 *
	 * @since 6.0.0
	 * @return int
	 */
	function wp_easycart_store_low_stock_threshold( ) {
		$value = get_option( 'ec_option_low_stock_trigger_total', '' );

		if ( '' === $value || false === $value || null === $value ) {
			/* Stores that only ever set the Inventory screen's own number ( 6.0.0 dev ). */
			$value = get_option( 'ec_option_inventory_low_stock_threshold', '' );
		}

		if ( '' === $value || false === $value || null === $value ) {
			return (int) EC_DEFAULT_LOW_STOCK_THRESHOLD;
		}

		return (int) $value;
	}
}

if ( ! function_exists( 'wp_easycart_low_stock_threshold' ) ) {
	/**
	 * The quantity at or below which one item counts as low stock.
	 *
	 * @since 6.0.0
	 * @param object|array|null $context Optional product or variant row. Recognised keys:
	 *                                   product_id, optionitemquantity_id,
	 *                                   optionitem_reorder_point / product_reorder_point, or a
	 *                                   bare reorder_point belonging to whichever row it came from.
	 * @return int
	 */
	function wp_easycart_low_stock_threshold( $context = null ) {
		$threshold  = wp_easycart_store_low_stock_threshold();
		$product_id = 0;
		$oiq_id     = 0;

		if ( is_object( $context ) || is_array( $context ) ) {
			$row = (object) $context;

			$product_id = isset( $row->product_id ) ? (int) $row->product_id : 0;
			$oiq_id     = isset( $row->optionitemquantity_id ) ? (int) $row->optionitemquantity_id : 0;
			if ( ! $oiq_id && isset( $row->oiq_id ) ) {
				$oiq_id = (int) $row->oiq_id;
			}

			$variant_reorder = isset( $row->optionitem_reorder_point ) ? (int) $row->optionitem_reorder_point : -1;
			$product_reorder = isset( $row->product_reorder_point ) ? (int) $row->product_reorder_point : -1;

			/* A row read straight from one table carries a bare reorder_point. */
			if ( isset( $row->reorder_point ) ) {
				if ( $oiq_id && $variant_reorder < 0 ) {
					$variant_reorder = (int) $row->reorder_point;
				} else if ( ! $oiq_id && $product_reorder < 0 ) {
					$product_reorder = (int) $row->reorder_point;
				}
			}

			if ( $variant_reorder >= 0 ) {
				$threshold = $variant_reorder;
			} else if ( $product_reorder >= 0 ) {
				$threshold = $product_reorder;
			}
		}

		/**
		 * Filter the effective low stock threshold for one item.
		 *
		 * @since 6.0.0
		 * @param int               $threshold  Effective threshold.
		 * @param int               $product_id Product, 0 when store-wide.
		 * @param int               $oiq_id     Variant row, 0 for product-level stock.
		 * @param object|array|null $context    Row the threshold was resolved from.
		 */
		return (int) apply_filters( 'wp_easycart_low_stock_threshold', $threshold, $product_id, $oiq_id, $context );
	}
}

if ( ! function_exists( 'wp_easycart_low_stock_threshold_sql' ) ) {
	/**
	 * The same precedence as SQL, for the screens that classify many rows at once.
	 *
	 * @since 6.0.0
	 * @param string $variant_column Variant reorder_point column, '' for product-level rows.
	 * @param string $product_column Product reorder_point column, '' when unavailable.
	 * @return string A CASE expression returning the effective threshold.
	 */
	function wp_easycart_low_stock_threshold_sql( $variant_column = '', $product_column = '' ) {
		if ( '' === $variant_column && '' === $product_column ) {
			/* No reorder point columns to consult: a bare CASE would be invalid SQL, so return the store number. @since 6.0.0 */
			return (string) (int) wp_easycart_store_low_stock_threshold();
		}
		$sql = 'CASE';
		if ( '' !== $variant_column ) {
			$sql .= ' WHEN ' . $variant_column . ' >= 0 THEN ' . $variant_column;
		}
		if ( '' !== $product_column ) {
			$sql .= ' WHEN ' . $product_column . ' >= 0 THEN ' . $product_column;
		}
		return '( ' . $sql . ' ELSE ' . (int) wp_easycart_store_low_stock_threshold() . ' END )';
	}
}
