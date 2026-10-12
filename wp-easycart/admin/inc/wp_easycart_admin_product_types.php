<?php
/**
 * WP EasyCart Admin Product Types
 *
 * The product types the admin speaks of ( 6.0.4 ): the product editor's Product type choice and the products list's
 * Product type filter read the same list and the same rule, so a product is called the same thing on both screens.
 *
 * @package WP_EasyCart
 * @since   6.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_product_types' ) ) :

	/**
	 * Product types: the list, which one a product is, and the SQL that finds them.
	 */
	class wp_easycart_admin_product_types {

		/**
		 * The ec_product switches the built-in types use, always columns of ec_product.
		 */
		const BUILT_IN_FLAGS = array( 'is_download', 'is_subscription_item', 'is_giftcard', 'is_donation', 'inquiry_mode', 'catalog_mode', 'is_preorder_type', 'is_restaurant_type', 'is_deconetwork' );

		/**
		 * The types, in the order the editor offers them. A type writes the switches the editor's Type settings section has
		 * always had: its flag on and every other type's flag off, and for the types that say so, whether the product
		 * ships. Physical and Service set no flag; the switch Ships decides between them. A product with more than one flag
		 * on is Custom ( every switch shows ). Types marked pro are the ones Quick add offers only with WP EasyCart PRO.
		 *
		 * @return array key => label, flag ( '' = none ), ships ( 1 | 0 | null = leave it ), pro, help, icon ( a dashicon ).
		 */
		public static function types() {
			$types = array(
				'physical'     => array( 'label' => __( 'Physical product', 'wp-easycart' ), 'flag' => '', 'ships' => 1, 'pro' => false, 'help' => __( 'Ships to the customer. Shows shipping and stock.', 'wp-easycart' ), 'icon' => 'archive' ),
				'service'      => array( 'label' => __( 'Service, event or course', 'wp-easycart' ), 'flag' => '', 'ships' => 0, 'pro' => false, 'help' => __( 'Nothing ships. Stock can count the places left.', 'wp-easycart' ), 'icon' => 'calendar-alt' ),
				'download'     => array( 'label' => __( 'Digital download', 'wp-easycart' ), 'flag' => 'is_download', 'ships' => 0, 'pro' => true, 'help' => __( 'Shows the download settings. Nothing ships.', 'wp-easycart' ), 'icon' => 'download' ),
				'subscription' => array( 'label' => __( 'Subscription', 'wp-easycart' ), 'flag' => 'is_subscription_item', 'ships' => null, 'pro' => true, 'help' => __( 'Shows the billing settings. Turn shipping on for a box that ships.', 'wp-easycart' ), 'icon' => 'update' ),
				'giftcard'     => array( 'label' => __( 'Gift card', 'wp-easycart' ), 'flag' => 'is_giftcard', 'ships' => null, 'pro' => true, 'help' => __( 'Sells a gift card. Turn shipping on for printed cards.', 'wp-easycart' ), 'icon' => 'tickets-alt' ),
				'donation'     => array( 'label' => __( 'Donation or invoice', 'wp-easycart' ), 'flag' => 'is_donation', 'ships' => 0, 'pro' => true, 'help' => __( 'Shoppers enter the amount they pay. Nothing ships.', 'wp-easycart' ), 'icon' => 'heart' ),
				'inquiry'      => array( 'label' => __( 'Inquiry or quote request', 'wp-easycart' ), 'flag' => 'inquiry_mode', 'ships' => null, 'pro' => true, 'help' => __( 'Shoppers send you a question instead of buying.', 'wp-easycart' ), 'icon' => 'format-chat' ),
				'seasonal'     => array( 'label' => __( 'Seasonal ( not for sale now )', 'wp-easycart' ), 'flag' => 'catalog_mode', 'ships' => null, 'pro' => true, 'help' => __( 'Shows your seasonal phrase in place of the buy button.', 'wp-easycart' ), 'icon' => 'palmtree' ),
				'preorder'     => array( 'label' => __( 'Preorder for pickup', 'wp-easycart' ), 'flag' => 'is_preorder_type', 'ships' => null, 'pro' => true, 'help' => __( 'Ordered ahead and picked up at your store.', 'wp-easycart' ), 'icon' => 'clock' ),
				'restaurant'   => array( 'label' => __( 'Restaurant item', 'wp-easycart' ), 'flag' => 'is_restaurant_type', 'ships' => null, 'pro' => true, 'help' => __( 'Ordered for pickup while the restaurant is open.', 'wp-easycart' ), 'icon' => 'food' ),
				'deconetwork'  => array( 'label' => __( 'Deconetwork', 'wp-easycart' ), 'flag' => 'is_deconetwork', 'ships' => null, 'pro' => true, 'help' => __( 'Designed and made through Deconetwork.', 'wp-easycart' ), 'icon' => 'art' ),
			);
			/**
			 * The product editor's types ( 6.0.4 ), also the products list's Product type filter. A type added here needs a
			 * checkbox with its flag as the id on the editor's page, and its flag must be a column of ec_product for the
			 * filter to find it.
			 *
			 * @since 6.0.4
			 * @param array $types key => label, flag, ships, pro, help, icon.
			 */
			$types = apply_filters( 'wp_easycart_admin_product_types', $types );
			return is_array( $types ) ? $types : array();
		}

		/**
		 * What a product with more than one type switch on is called.
		 *
		 * @return string
		 */
		public static function custom_label() {
			return __( 'Custom ( more than one type )', 'wp-easycart' );
		}

		/**
		 * The type a product is: a types() key, or custom when two or more type switches are on.
		 *
		 * @param object|array $product Product row ( the type flags and is_shippable ).
		 * @return string
		 */
		public static function key_for( $product ) {
			$product = (object) $product;
			$on      = array();
			foreach ( self::types() as $key => $type ) {
				if ( isset( $type['flag'] ) && '' !== $type['flag'] && ! empty( $product->{$type['flag']} ) ) {
					$on[] = $key;
				}
			}
			if ( count( $on ) > 1 ) {
				return 'custom';
			}
			if ( 1 === count( $on ) ) {
				return $on[0];
			}
			return empty( $product->is_shippable ) ? 'service' : 'physical';
		}

		/**
		 * The keys key_for() can answer, in the editor's order, with custom last.
		 *
		 * @return string[]
		 */
		public static function filter_keys() {
			$keys = array();
			foreach ( self::types() as $key => $type ) {
				if ( 'physical' === $key || 'service' === $key || '' !== self::column( isset( $type['flag'] ) ? $type['flag'] : '' ) ) {
					$keys[] = (string) $key;
				}
			}
			$keys[] = 'custom';
			return $keys;
		}

		/**
		 * SQL that is true for the products of one type, as key_for() decides it.
		 *
		 * @param string $key   A filter_keys() key.
		 * @param string $alias The ec_product table's name or alias in the query.
		 * @return string '' for a key no product can have.
		 */
		public static function where_sql( $key, $alias = 'ec_product' ) {
			$key = (string) $key;
			if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $alias ) || ! in_array( $key, self::filter_keys(), true ) ) {
				return '';
			}
			$count = self::count_sql( $alias );
			if ( 'custom' === $key ) {
				return $count . ' >= 2';
			}
			if ( 'physical' === $key ) {
				return $count . ' = 0 AND COALESCE( ' . $alias . '.is_shippable, 0 ) <> 0';
			}
			if ( 'service' === $key ) {
				return $count . ' = 0 AND COALESCE( ' . $alias . '.is_shippable, 0 ) = 0';
			}
			$types = self::types();
			return 'COALESCE( ' . $alias . '.' . self::column( $types[ $key ]['flag'] ) . ', 0 ) <> 0 AND ' . $count . ' = 1';
		}

		/**
		 * How many products each type has, in one query.
		 *
		 * @param string $where A fixed SQL fragment ( " AND …" ) that narrows ec_product, e.g. leaving out unsaved drafts.
		 * @return array filter_keys() key => count.
		 */
		public static function counts( $where = '' ) {
			global $wpdb;
			$keys    = self::filter_keys();
			$columns = array();
			foreach ( $keys as $i => $key ) {
				$columns[] = 'SUM( CASE WHEN ' . self::where_sql( $key ) . ' THEN 1 ELSE 0 END ) AS t' . (int) $i;
			}
			$row    = $wpdb->get_row( 'SELECT ' . implode( ', ', $columns ) . ' FROM ec_product WHERE 1 = 1' . $where, ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- where_sql() builds only from column names checked by column(); $where is the caller's fixed SQL fragment.
			$counts = array();
			foreach ( $keys as $i => $key ) {
				$counts[ $key ] = ( is_array( $row ) && isset( $row[ $i ] ) ) ? (int) $row[ $i ] : 0;
			}
			return $counts;
		}

		/**
		 * What the type list looks like to SQL, so a count kept from before a type was added is not reused.
		 *
		 * @return string
		 */
		public static function signature() {
			$parts = array();
			foreach ( self::filter_keys() as $key ) {
				$parts[] = $key . ':' . self::where_sql( $key );
			}
			return md5( implode( '|', $parts ) );
		}

		/**
		 * The count of type switches on, as SQL.
		 *
		 * @param string $alias The ec_product table's name or alias.
		 * @return string
		 */
		private static function count_sql( $alias ) {
			$on = array();
			foreach ( self::types() as $type ) {
				$column = self::column( isset( $type['flag'] ) ? $type['flag'] : '' );
				if ( '' !== $column ) {
					$on[] = '( COALESCE( ' . $alias . '.' . $column . ', 0 ) <> 0 )';
				}
			}
			return empty( $on ) ? '0' : '( ' . implode( ' + ', $on ) . ' )';
		}

		/**
		 * A flag that is a column of ec_product, or ''. A flag a filter added is checked against the table once a request.
		 *
		 * @param string $flag Flag.
		 * @return string
		 */
		private static function column( $flag ) {
			static $columns = null;
			$flag = (string) $flag;
			if ( '' === $flag || ! preg_match( '/^[A-Za-z0-9_]+$/', $flag ) ) {
				return '';
			}
			if ( in_array( $flag, self::BUILT_IN_FLAGS, true ) ) {
				return $flag;
			}
			if ( null === $columns ) {
				global $wpdb;
				$columns = array_map( 'strtolower', (array) $wpdb->get_col( 'SHOW COLUMNS FROM ec_product' ) );
			}
			return in_array( strtolower( $flag ), $columns, true ) ? $flag : '';
		}
	}

endif;
