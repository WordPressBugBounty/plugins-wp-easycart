<?php
/**
 * WP EasyCart — boxes and packing ( 6.0.2 ).
 *
 * The store's box library ( ec_package, Settings › Shipping › Boxes ) and the packer that splits an order's shippable
 * items into packages. New orders are packed when they are placed ( wp_easycart_shipments::on_order_inserted() ); an
 * order placed before 6.0.2, or one built another way, is packed from these settings the first time it is needed and
 * saved once someone edits its packages or buys a label. Label extensions read the result through
 * wp_easycart_shipments::plan().
 *
 * Units are the store's: product and box dimensions in inches or centimetres ( ec_option_enable_metric_unit_display ),
 * weights in pounds or kilograms ( ec_option_paypal_weight_unit, set by the setup wizard despite its name ).
 *
 * Products may say how they pack ( ec_product, EC_UPGRADE_DB 114 ): ships_separately ( every unit in its own box, e.g.
 * something that ships in its own carton ), package_id ( always this box ), and the customs fields hs_code,
 * country_of_origin and customs_description that label extensions put on customs forms.
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_packages' ) ) :

	/**
	 * Box library and packer.
	 */
	final class wp_easycart_packages {

		/** Box types. */
		const TYPES = array( 'box', 'envelope', 'soft' );

		/** Most single units the packer places one by one; larger lines are packed in groups. */
		const MAX_UNITS = 400;

		/**
		 * Boxes, cached per request.
		 *
		 * @var array|null
		 */
		private static $boxes = null;

		/**
		 * The tables and product columns exist: install_db() records EC_UPGRADE_DB only after dbDelta created them.
		 *
		 * @return bool
		 */
		public static function ready() {
			return (int) get_option( 'ec_option_db_new_version' ) >= 114;
		}

		// Units.

		/**
		 * The store's units.
		 *
		 * @return array dim ( in | cm ), weight ( lb | kg ).
		 */
		public static function units() {
			$weight = (string) get_option( 'ec_option_paypal_weight_unit' );
			return array(
				'dim'    => get_option( 'ec_option_enable_metric_unit_display' ) ? 'cm' : 'in',
				'weight' => ( 'kgs' === $weight || 'kg' === $weight ) ? 'kg' : 'lb',
			);
		}

		// Box library.

		/**
		 * Boxes, smallest first.
		 *
		 * @param bool $active_only Leave out boxes switched off.
		 * @return array Rows ( objects ).
		 */
		public static function boxes( $active_only = true ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return array();
			}
			if ( null === self::$boxes ) {
				$rows        = $wpdb->get_results( 'SELECT * FROM ec_package ORDER BY sort_order ASC, ( length * width * height ) ASC, package_id ASC' );
				self::$boxes = is_array( $rows ) ? $rows : array();
			}
			if ( ! $active_only ) {
				return self::$boxes;
			}
			return array_values(
				array_filter(
					self::$boxes,
					function ( $box ) {
						return ! empty( $box->is_active );
					}
				)
			);
		}

		/**
		 * One box.
		 *
		 * @param int $package_id Box.
		 * @return object|null
		 */
		public static function box( $package_id ) {
			foreach ( self::boxes( false ) as $box ) {
				if ( (int) $box->package_id === (int) $package_id ) {
					return $box;
				}
			}
			return null;
		}

		/**
		 * The default box ( used for an order that cannot be packed by size, and offered first in label windows ).
		 *
		 * @return object|null
		 */
		public static function default_box() {
			$boxes = self::boxes();
			foreach ( $boxes as $box ) {
				if ( ! empty( $box->is_default ) ) {
					return $box;
				}
			}
			return $boxes ? $boxes[0] : null;
		}

		/**
		 * Add or change a box.
		 *
		 * @param array $data package_id ( 0 = new ), label, package_type, length, width, height, box_weight, max_weight,
		 *                    carrier_template, is_default, is_active.
		 * @return int|WP_Error Box ID.
		 */
		public static function save_box( $data ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return new WP_Error( 'wp_easycart_packages', __( 'Boxes need the WP EasyCart database update to finish first.', 'wp-easycart' ) );
			}
			$data  = is_array( $data ) ? $data : array();
			$label = isset( $data['label'] ) ? trim( sanitize_text_field( (string) $data['label'] ) ) : '';
			$type  = isset( $data['package_type'] ) ? sanitize_key( $data['package_type'] ) : 'box';
			$row   = array(
				'label'            => '',
				'package_type'     => in_array( $type, self::TYPES, true ) ? $type : 'box',
				'length'           => self::number( isset( $data['length'] ) ? $data['length'] : 0 ),
				'width'            => self::number( isset( $data['width'] ) ? $data['width'] : 0 ),
				'height'           => self::number( isset( $data['height'] ) ? $data['height'] : 0 ),
				'box_weight'       => self::number( isset( $data['box_weight'] ) ? $data['box_weight'] : 0 ),
				'max_weight'       => self::number( isset( $data['max_weight'] ) ? $data['max_weight'] : 0 ),
				'carrier_template' => isset( $data['carrier_template'] ) ? substr( preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $data['carrier_template'] ), 0, 100 ) : '',
				'is_active'        => ! isset( $data['is_active'] ) || ! empty( $data['is_active'] ) ? 1 : 0,
			);
			if ( 'soft' !== $row['package_type'] && ( $row['length'] <= 0 || $row['width'] <= 0 || ( 'box' === $row['package_type'] && $row['height'] <= 0 ) ) ) {
				return new WP_Error( 'wp_easycart_packages', __( 'Enter the inside length, width and height.', 'wp-easycart' ) );
			}
			/* 6.0.2: a box saved without a name is named for itself ( its carrier box, else its type and size ). */
			if ( '' === $label ) {
				$label = self::auto_label( $row );
			}
			$row['label'] = function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 100 ) : substr( $label, 0, 100 );
			$id = isset( $data['package_id'] ) ? (int) $data['package_id'] : 0;
			if ( $id > 0 && self::box( $id ) ) {
				$wpdb->update( 'ec_package', $row, array( 'package_id' => $id ) );
			} else {
				$row['sort_order'] = (int) $wpdb->get_var( 'SELECT COALESCE( MAX( sort_order ), 0 ) + 1 FROM ec_package' );
				$wpdb->insert( 'ec_package', $row );
				$id = (int) $wpdb->insert_id;
				if ( $id <= 0 ) {
					return new WP_Error( 'wp_easycart_packages', __( 'The box could not be saved.', 'wp-easycart' ) );
				}
			}
			if ( ! empty( $data['is_default'] ) || 1 === count( self::fresh_boxes() ) ) {
				self::set_default( $id );
			}
			/* 6.0.2: a default box that stops being used for packing hands the default to the first box still in use. */
			if ( ! $row['is_active'] ) {
				$saved = self::box( $id );
				if ( $saved && ! empty( $saved->is_default ) ) {
					foreach ( self::fresh_boxes() as $other ) {
						if ( (int) $other->package_id !== $id && ! empty( $other->is_active ) ) {
							self::set_default( (int) $other->package_id );
							break;
						}
					}
				}
			}
			self::$boxes = null;
			return $id;
		}

		/**
		 * Remove a box. Products that asked for it go back to automatic packing; orders keep the sizes they were packed with.
		 *
		 * @param int $package_id Box.
		 * @return bool
		 */
		public static function delete_box( $package_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return false;
			}
			$box         = self::box( $package_id );
			$was_default = $box && ! empty( $box->is_default );
			$wpdb->delete( 'ec_package', array( 'package_id' => (int) $package_id ) );
			$wpdb->update( 'ec_product', array( 'package_id' => 0 ), array( 'package_id' => (int) $package_id ) );
			self::$boxes = null;
			if ( $was_default ) {
				$first = self::fresh_boxes();
				if ( $first ) {
					self::set_default( (int) $first[0]->package_id );
				}
			}
			return true;
		}

		/**
		 * Make one box the default.
		 *
		 * @param int $package_id Box.
		 */
		public static function set_default( $package_id ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( 'UPDATE ec_package SET is_default = IF( package_id = %d, 1, 0 )', (int) $package_id ) );
			self::$boxes = null;
		}

		/**
		 * Boxes read again from the database.
		 *
		 * @return array
		 */
		private static function fresh_boxes() {
			self::$boxes = null;
			return self::boxes( false );
		}

		/**
		 * The name a box gets when none was typed: its carrier box's name, else its type and inside size
		 * ( "Box 12 × 10 × 6 in" ).
		 *
		 * @since 6.0.2
		 * @param array $row Box row ( package_type, length, width, height, carrier_template ).
		 * @return string
		 */
		public static function auto_label( $row ) {
			$templates = self::carrier_templates();
			if ( ! empty( $row['carrier_template'] ) && isset( $templates[ $row['carrier_template'] ] ) && '' !== trim( (string) $templates[ $row['carrier_template'] ] ) ) {
				return trim( sanitize_text_field( (string) $templates[ $row['carrier_template'] ] ) );
			}
			$types = array(
				'box'      => __( 'Box', 'wp-easycart' ),
				'envelope' => __( 'Envelope', 'wp-easycart' ),
				'soft'     => __( 'Soft pack', 'wp-easycart' ),
			);
			$name  = isset( $row['package_type'], $types[ $row['package_type'] ] ) ? $types[ $row['package_type'] ] : $types['box'];
			$sizes = array();
			foreach ( array( 'length', 'width', 'height' ) as $side ) {
				if ( isset( $row[ $side ] ) && (float) $row[ $side ] > 0 ) {
					$sizes[] = rtrim( rtrim( number_format( (float) $row[ $side ], 2, '.', '' ), '0' ), '.' );
				}
			}
			if ( ! $sizes ) {
				return $name;
			}
			$units = self::units();
			return $name . ' ' . implode( ' × ', $sizes ) . ' ' . $units['dim'];
		}

		/**
		 * Carrier box codes extensions offer for the Boxes editor ( e.g. flat rate boxes ).
		 *
		 * @return array code => label.
		 */
		public static function carrier_templates() {
			/**
			 * Carrier boxes a label extension can buy labels for, offered in Settings › Shipping › Boxes.
			 *
			 * @since 6.0.2
			 * @param array $templates code => label ( e.g. 'USPS_MediumFlatRateBox1' => 'USPS Medium Flat Rate Box' ).
			 */
			$templates = apply_filters( 'wp_easycart_package_carrier_templates', array() );
			return is_array( $templates ) ? $templates : array();
		}

		// Order lines.

		/**
		 * An order's lines with what the packer needs from each product.
		 *
		 * @since 6.0.2 fulfillment_provider and optionitemquantity_id ( the line's partner and variant row, EC_UPGRADE_DB 117 );
		 *              a variant with its own weight weighs that.
		 * @param int $order_id Order.
		 * @return array Rows: orderdetail_id, product_id, title, model_number, quantity ( less refunded ), unit_price,
		 *               is_shippable, weight, length, width, height, ships_separately, package_id, hs_code,
		 *               country_of_origin, customs_description, fulfillment_provider, optionitemquantity_id.
		 */
		public static function lines( $order_id ) {
			global $wpdb;
			$has_cols = self::ready();
			$extra    = $has_cols ? ', ec_product.ships_separately, ec_product.package_id, ec_product.hs_code, ec_product.country_of_origin, ec_product.customs_description' : '';
			$join     = '';
			if ( class_exists( 'wp_easycart_fulfillment' ) && wp_easycart_fulfillment::ready() ) {
				$extra .= ', ec_orderdetail.fulfillment_provider, ec_orderdetail.optionitemquantity_id, wpec_variant.weight AS variant_weight';
				$join   = ' LEFT JOIN ec_optionitemquantity AS wpec_variant ON ( ec_orderdetail.optionitemquantity_id > 0 AND wpec_variant.optionitemquantity_id = ec_orderdetail.optionitemquantity_id )';
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $extra and $join are fixed SQL.
			$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT ec_orderdetail.orderdetail_id, ec_orderdetail.product_id, ec_orderdetail.title, ec_orderdetail.model_number, ec_orderdetail.quantity, ec_orderdetail.refunded_quantity, ec_orderdetail.unit_price, ec_orderdetail.total_price, ec_orderdetail.is_shippable, ec_orderdetail.optionitem_id_1, ec_orderdetail.optionitem_id_2, ec_orderdetail.optionitem_id_3, ec_orderdetail.optionitem_id_4, ec_orderdetail.optionitem_id_5, ec_product.weight, ec_product.length, ec_product.width, ec_product.height{$extra} FROM ec_orderdetail LEFT JOIN ec_product ON ec_product.product_id = ec_orderdetail.product_id{$join} WHERE ec_orderdetail.order_id = %d ORDER BY ec_orderdetail.orderdetail_id ASC", (int) $order_id ) );
			$weights = self::option_weights( (array) $rows );
			$out     = array();
			foreach ( (array) $rows as $row ) {
				/* 6.0.2: what one unit weighs as the cart weighed it: the variant's own weight, else the product's with the
				   chosen options' weights ( option choices' adds, overrides, one-time weights and multipliers ). */
				$row->weight   = self::unit_weight( $row, $weights );
				$row->quantity = max( 0, (int) $row->quantity - (int) $row->refunded_quantity );
				unset( $row->variant_weight, $row->optionitem_id_1, $row->optionitem_id_2, $row->optionitem_id_3, $row->optionitem_id_4, $row->optionitem_id_5 );
				foreach ( array( 'weight', 'length', 'width', 'height' ) as $key ) {
					$row->{$key} = isset( $row->{$key} ) ? max( 0, (float) $row->{$key} ) : 0;
				}
				foreach ( array( 'ships_separately', 'package_id', 'optionitemquantity_id' ) as $key ) {
					$row->{$key} = isset( $row->{$key} ) ? (int) $row->{$key} : 0;
				}
				foreach ( array( 'hs_code', 'country_of_origin', 'customs_description', 'fulfillment_provider' ) as $key ) {
					$row->{$key} = isset( $row->{$key} ) ? (string) $row->{$key} : '';
				}
				$out[] = $row;
			}
			/**
			 * Filter the lines the packer and label extensions work from.
			 *
			 * @since 6.0.2
			 * @param array $lines    Line rows.
			 * @param int   $order_id Order.
			 */
			return (array) apply_filters( 'wp_easycart_package_lines', $out, (int) $order_id );
		}

		/**
		 * The weights of the option choices on these order lines: each variation choice's weight ( ec_optionitem ), and each
		 * line's modifier choices as the order kept them ( ec_order_option ).
		 *
		 * @since 6.0.2
		 * @param array $rows Order lines ( orderdetail_id, optionitem_id_1 … _5 ).
		 * @return array items ( optionitem_id => weight ), modifiers ( orderdetail_id => rows ).
		 */
		private static function option_weights( $rows ) {
			global $wpdb;
			$item_ids = array();
			$line_ids = array();
			foreach ( $rows as $row ) {
				$line_ids[] = (int) $row->orderdetail_id;
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					if ( ! empty( $row->{ 'optionitem_id_' . $slot } ) ) {
						$item_ids[] = (int) $row->{ 'optionitem_id_' . $slot };
					}
				}
			}
			$out = array(
				'items'     => array(),
				'modifiers' => array(),
			);
			if ( $item_ids ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- a list of (int) ids.
				foreach ( (array) $wpdb->get_results( 'SELECT optionitem_id, optionitem_weight FROM ec_optionitem WHERE optionitem_id IN ( ' . implode( ',', array_unique( $item_ids ) ) . ' )' ) as $item ) {
					$out['items'][ (int) $item->optionitem_id ] = (float) $item->optionitem_weight;
				}
			}
			if ( $line_ids ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- a list of (int) ids.
				foreach ( (array) $wpdb->get_results( 'SELECT orderdetail_id, option_type, option_value, optionitem_weight, optionitem_weight_onetime, optionitem_weight_override, optionitem_weight_multiplier FROM ec_order_option WHERE orderdetail_id IN ( ' . implode( ',', $line_ids ) . ' ) ORDER BY order_option_id ASC' ) as $option ) {
					$out['modifiers'][ (int) $option->orderdetail_id ][] = $option;
				}
			}
			return $out;
		}

		/**
		 * What one unit of an order line weighs, worked out as ec_cartitem weighs a cart line: the variant's own weight ( when
		 * above 0 ), else the product's plus its variation choices' weights; a modifier choice's override replaces that,
		 * adds add per unit ( a number box's times its number ), a multiplier multiplies, and one-time and grid changes are
		 * the line's, shared among its units.
		 *
		 * @since 6.0.2
		 * @param object $row     Order line ( weight, variant_weight, quantity as ordered, optionitem_id_1 … _5 ).
		 * @param array  $weights option_weights().
		 * @return float
		 */
		private static function unit_weight( $row, $weights ) {
			$product_weight = isset( $row->weight ) ? (float) $row->weight : 0.0;
			$unit           = $product_weight;
			$adds           = 0.0;
			if ( isset( $row->variant_weight ) && null !== $row->variant_weight && (float) $row->variant_weight > 0 ) {
				$unit = (float) $row->variant_weight;
			} else {
				for ( $slot = 1; $slot <= 5; $slot++ ) {
					$item_id = isset( $row->{ 'optionitem_id_' . $slot } ) ? (int) $row->{ 'optionitem_id_' . $slot } : 0;
					if ( $item_id && isset( $weights['items'][ $item_id ] ) ) {
						$adds += $weights['items'][ $item_id ];
					}
				}
			}
			$modifiers = isset( $weights['modifiers'][ (int) $row->orderdetail_id ] ) ? $weights['modifiers'][ (int) $row->orderdetail_id ] : array();
			if ( ! $modifiers && 0.0 === $adds ) {
				return $unit; /* no option weights: as before */
			}
			$onetime    = 0.0;
			$grid       = 0.0;
			$multiplier = 0.0;
			foreach ( $modifiers as $option ) {
				$add      = (float) $option->optionitem_weight;
				$once     = (float) $option->optionitem_weight_onetime;
				$override = (float) $option->optionitem_weight_override;
				$times    = (float) $option->optionitem_weight_multiplier;
				$value    = is_numeric( $option->option_value ) ? (float) $option->option_value : 0.0;
				if ( 'grid' === $option->option_type ) {
					if ( 0.0 !== $add ) {
						$grid += $add * $value;
					} elseif ( 0.0 !== $once ) {
						$grid += $once;
					} elseif ( $override >= 0 ) {
						$grid += ( $override - $product_weight ) * $value;
					} elseif ( $times > 0 ) {
						$grid += $product_weight * ( $times - 1 ) * $value;
					}
					continue;
				}
				if ( 'dimensions1' === $option->option_type || 'dimensions2' === $option->option_type ) {
					continue;
				}
				$per = ( 'number' === $option->option_type ) ? $value : 1.0;
				if ( 0.0 !== $add ) {
					$adds += $add * $per;
				} elseif ( 0.0 !== $once ) {
					$onetime += $once;
				} elseif ( $override >= 0 ) {
					$unit = $override;
				}
				if ( $times > 0 ) {
					$multiplier = ( ( 0.0 === $multiplier ) ? 1.0 : $multiplier ) * $times * $per;
				}
			}
			$unit = $unit + $adds;
			if ( $multiplier > 0 ) {
				$unit = $unit * $multiplier;
			}
			$ordered = isset( $row->quantity ) ? (int) $row->quantity : 0;
			if ( ( 0.0 !== $onetime || 0.0 !== $grid ) && $ordered > 0 ) {
				$unit = ( ( $unit * $ordered ) + $onetime + $grid ) / $ordered;
			}
			return $unit;
		}

		// Packing.

		/**
		 * Split lines into packages.
		 *
		 * 1. Units of a product that ships separately each get their own package, sized like the product.
		 * 2. Units of a product tied to a box go into that box, as many boxes as their weight and size need.
		 * 3. Everything else goes into the smallest active box that holds it all; when none does, units are placed largest
		 *    first into the biggest box that takes them, and each box is then swapped for the smallest one that still fits
		 *    its contents. A unit no box can take ships in its own package.
		 * 4. Without any boxes, everything is one package with no size ( a single unit keeps the product's size ).
		 * 5. ( 6.0.2 ) Lines a fulfillment partner makes ( line_partner(): the partner recorded on the line ) are never boxed
		 *    with the store's: one package per partner, named for it, with no box, weighing its lines ( provider = the
		 *    partner's slug ). The partner ships it.
		 *
		 * @param array $lines Lines from lines().
		 * @return array Packages: package_id, package_name, length, width, height, weight ( contents + box ), items
		 *               ( orderdetail_id => quantity ), provider ( a partner's slug, or '' ).
		 */
		public static function pack( $lines ) {
			$units    = array();
			$own      = array();
			$fixed    = array();
			$partners = array();
			$shipping = false;
			foreach ( (array) $lines as $line ) {
				if ( empty( $line->is_shippable ) || (int) $line->quantity <= 0 ) {
					continue;
				}
				$shipping = true;
				$partner  = self::line_partner( $line );
				if ( '' !== $partner ) {
					$partners[ $partner ][] = $line;
					continue;
				}
				$qty      = (int) $line->quantity;
				$group    = max( 1, (int) ceil( $qty / self::MAX_UNITS ) );
				for ( $done = 0; $done < $qty; $done += $group ) {
					$count = min( $group, $qty - $done );
					$unit  = array(
						'id'     => (int) $line->orderdetail_id,
						'qty'    => $count,
						'dims'   => self::sorted_dims( $line->length, $line->width, $line->height ),
						'volume' => (float) $line->length * (float) $line->width * (float) $line->height * $count,
						'weight' => (float) $line->weight * $count,
					);
					if ( ! empty( $line->ships_separately ) ) {
						$own[] = $unit;
					} elseif ( ! empty( $line->package_id ) && self::box( (int) $line->package_id ) ) {
						$fixed[ (int) $line->package_id ][] = $unit;
					} else {
						$units[] = $unit;
					}
				}
			}
			if ( ! $shipping ) {
				return array();
			}

			$packages = array();
			foreach ( $own as $unit ) {
				$packages[] = self::package_from_units( null, array( $unit ), __( 'Own box', 'wp-easycart' ) );
			}
			foreach ( $fixed as $package_id => $group_units ) {
				$box = self::box( $package_id );
				foreach ( self::fill( array( $box ), $group_units, false ) as $bin ) {
					$packages[] = $bin;
				}
			}
			if ( $units ) {
				$boxes = self::boxes();
				if ( ! $boxes ) {
					$single     = ( 1 === count( $units ) && 1 === $units[0]['qty'] );
					$packages[] = self::package_from_units( null, $units, __( 'Package', 'wp-easycart' ), ! $single );
				} else {
					foreach ( self::fill( $boxes, $units, true ) as $bin ) {
						$packages[] = $bin;
					}
				}
			}
			foreach ( $partners as $partner => $partner_lines ) {
				$items  = array();
				$weight = 0;
				foreach ( $partner_lines as $line ) {
					$items[ (int) $line->orderdetail_id ] = ( isset( $items[ (int) $line->orderdetail_id ] ) ? $items[ (int) $line->orderdetail_id ] : 0 ) + (int) $line->quantity;
					$weight                              += (float) $line->weight * (int) $line->quantity;
				}
				$packages[] = array(
					'package_id'   => 0,
					'package_name' => wp_easycart_fulfillment::label( $partner ),
					'length'       => 0,
					'width'        => 0,
					'height'       => 0,
					'weight'       => round( $weight, 3 ),
					'items'        => $items,
					'provider'     => $partner,
				);
			}
			/**
			 * Filter the packages an order was split into.
			 *
			 * @since 6.0.2
			 * @param array $packages Packages ( see pack() ).
			 * @param array $lines    Lines packed.
			 */
			return array_values( (array) apply_filters( 'wp_easycart_packed_packages', $packages, $lines ) );
		}

		/**
		 * Place units in boxes.
		 *
		 * @param array $boxes  Boxes to use, smallest first.
		 * @param array $units  Units ( see pack() ).
		 * @param bool  $shrink Swap each filled box for the smallest one that still fits.
		 * @return array Packages.
		 */
		private static function fill( $boxes, $units, $shrink ) {
			$boxes = array_values( array_filter( $boxes ) );
			if ( ! $boxes ) {
				return array( self::package_from_units( null, $units, __( 'Package', 'wp-easycart' ), true ) );
			}
			/* 6.0.2: smallest first by inside volume, whatever order the boxes were created in ( sort_order only orders the Boxes
			   list; packing used to take the first-created box as the smallest ). A soft pack with no size cannot be compared,
			   so it comes last: a fallback, never the first choice. */
			usort(
				$boxes,
				function ( $a, $b ) {
					$va = self::inside_volume( $a );
					$vb = self::inside_volume( $b );
					if ( $va === $vb ) {
						return (int) $a->package_id <=> (int) $b->package_id;
					}
					return $va <=> $vb;
				}
			);
			/* Everything in one box when one is big enough. */
			foreach ( $boxes as $box ) {
				if ( self::fits( $box, $units ) ) {
					return array( self::package_from_units( $box, $units ) );
				}
			}
			usort(
				$units,
				function ( $a, $b ) {
					if ( $a['volume'] === $b['volume'] ) {
						return $b['weight'] <=> $a['weight'];
					}
					return $b['volume'] <=> $a['volume'];
				}
			);
			$bins  = array();
			$loose = array();
			foreach ( $units as $unit ) {
				$placed = false;
				foreach ( $bins as $i => $bin ) {
					if ( self::fits( $bin['box'], array_merge( $bin['units'], array( $unit ) ) ) ) {
						$bins[ $i ]['units'][] = $unit;
						$placed                = true;
						break;
					}
				}
				if ( $placed ) {
					continue;
				}
				// Open the biggest box that takes this unit, so later units can join it; with $shrink it is swapped below
				// for the smallest box that still holds what ended up in it.
				$opened = null;
				for ( $b = count( $boxes ) - 1; $b >= 0; $b-- ) {
					if ( self::fits( $boxes[ $b ], array( $unit ) ) ) {
						$opened = $boxes[ $b ];
						break;
					}
				}
				if ( $opened ) {
					$bins[] = array(
						'box'   => $opened,
						'units' => array( $unit ),
					);
				} else {
					$loose[] = $unit;
				}
			}
			$packages = array();
			foreach ( $bins as $bin ) {
				$box = $bin['box'];
				if ( $shrink ) {
					foreach ( $boxes as $candidate ) {
						if ( self::fits( $candidate, $bin['units'] ) ) {
							$box = $candidate;
							break;
						}
					}
				}
				$packages[] = self::package_from_units( $box, $bin['units'] );
			}
			foreach ( $loose as $unit ) {
				$packages[] = self::package_from_units( null, array( $unit ), __( 'Own box', 'wp-easycart' ) );
			}
			return $packages;
		}

		/**
		 * A box's inside volume for ordering boxes by size ( a soft pack with no size sorts last ).
		 *
		 * @since 6.0.2
		 * @param object $box Box.
		 * @return float
		 */
		private static function inside_volume( $box ) {
			$l = (float) $box->length;
			$w = (float) $box->width;
			$h = (float) $box->height;
			if ( 'soft' === $box->package_type && ( $l <= 0 || $w <= 0 ) ) {
				return PHP_FLOAT_MAX;
			}
			return $l * $w * ( $h > 0 ? $h : 1 );
		}

		/**
		 * Units fit a box: each unit's sides fit the box's sides, the total volume fits, and the weight is within the limit.
		 *
		 * @param object $box   Box.
		 * @param array  $units Units.
		 * @return bool
		 */
		private static function fits( $box, $units ) {
			$inside = self::sorted_dims( $box->length, $box->width, $box->height );
			$soft   = ( 'soft' === $box->package_type );
			$volume = 0;
			$weight = 0;
			foreach ( $units as $unit ) {
				if ( ! $soft && ( $unit['dims'][0] > $inside[0] || $unit['dims'][1] > $inside[1] || $unit['dims'][2] > $inside[2] ) ) {
					return false;
				}
				/* 6.0.2: a soft pack with a size still has a flat footprint: each unit's two largest sides must fit its two. */
				if ( $soft && $inside[0] > 0 && $inside[1] > 0 && ( $unit['dims'][0] > $inside[0] || $unit['dims'][1] > $inside[1] ) ) {
					return false;
				}
				$volume += $unit['volume'];
				$weight += $unit['weight'];
			}
			$capacity = $inside[0] * $inside[1] * $inside[2];
			if ( ! $soft && 'envelope' !== $box->package_type && $capacity > 0 && $volume > $capacity ) {
				return false;
			}
			return ! ( (float) $box->max_weight > 0 && $weight + (float) $box->box_weight > (float) $box->max_weight );
		}

		/**
		 * A package from units.
		 *
		 * @param object|null $box     Box, or null for a package sized like its single product ( or with no size ).
		 * @param array       $units   Units.
		 * @param string      $name    Name when there is no box.
		 * @param bool        $no_size Leave the size empty ( several products, no box ).
		 * @return array
		 */
		private static function package_from_units( $box, $units, $name = '', $no_size = false ) {
			$items  = array();
			$weight = $box ? (float) $box->box_weight : 0;
			foreach ( $units as $unit ) {
				$items[ $unit['id'] ] = ( isset( $items[ $unit['id'] ] ) ? $items[ $unit['id'] ] : 0 ) + $unit['qty'];
				$weight              += $unit['weight'];
			}
			if ( $box ) {
				$dims = array( (float) $box->length, (float) $box->width, (float) $box->height );
			} elseif ( $no_size || ! $units ) {
				$dims = array( 0, 0, 0 );
			} else {
				$dims = array_reverse( $units[0]['dims'] );
			}
			return array(
				'package_id'   => $box ? (int) $box->package_id : 0,
				'package_name' => $box ? (string) $box->label : ( '' !== $name ? $name : __( 'Package', 'wp-easycart' ) ),
				'length'       => round( $dims[0], 3 ),
				'width'        => round( $dims[1], 3 ),
				'height'       => round( $dims[2], 3 ),
				'weight'       => round( $weight, 3 ),
				'items'        => $items,
				'provider'     => '',
			);
		}

		/**
		 * The fulfillment partner that makes and ships an order line, or '' when the store ships it: the partner recorded on
		 * the line when it was inserted ( wp_easycart_fulfillment::stamp_line() records one only while that partner is
		 * connected ), whether or not the partner is still connected. A partner disconnected later still has the line;
		 * the store can add tracking to its package by hand.
		 *
		 * @since 6.0.2
		 * @param object $line Line from lines().
		 * @return string
		 */
		public static function line_partner( $line ) {
			$slug = ( is_object( $line ) && isset( $line->fulfillment_provider ) ) ? (string) $line->fulfillment_provider : '';
			if ( '' === $slug || ! class_exists( 'wp_easycart_fulfillment' ) ) {
				return '';
			}
			return wp_easycart_fulfillment::slug( $slug );
		}

		/**
		 * A fulfillment partner's line added to an order whose packages were already saved ( Add item on the order screen ):
		 * its units join that partner's packed package, or a new one, as pack() would have put them. The store's package
		 * editor leaves partner lines alone, so nothing else would pack it, and the order would never read as waiting on
		 * the partner. An order not packed yet needs nothing: its plan packs every line.
		 *
		 * @since 6.0.2
		 * @param int $order_id       Order.
		 * @param int $orderdetail_id Line.
		 * @return int The partner's package, 0 when nothing was packed.
		 */
		public static function add_partner_line( $order_id, $orderdetail_id ) {
			$order_id       = (int) $order_id;
			$orderdetail_id = (int) $orderdetail_id;
			if ( $order_id <= 0 || $orderdetail_id <= 0 || ! self::ready() || ! class_exists( 'wp_easycart_shipments' ) || ! wp_easycart_shipments::ready() ) {
				return 0;
			}
			$rows = wp_easycart_shipments::for_order( $order_id, array( 'returns' => false ) );
			if ( ! $rows ) {
				return 0;
			}
			$line = null;
			foreach ( self::lines( $order_id ) as $row ) {
				if ( (int) $row->orderdetail_id === $orderdetail_id ) {
					$line = $row;
					break;
				}
			}
			$partner = $line ? self::line_partner( $line ) : '';
			if ( '' === $partner || empty( $line->is_shippable ) || (int) $line->quantity <= 0 ) {
				return 0;
			}
			foreach ( $rows as $row ) {
				if ( isset( $row->items[ $orderdetail_id ] ) ) {
					return (int) $row->shipment_id;
				}
			}
			$weight = round( (float) $line->weight * (int) $line->quantity, 3 );
			foreach ( $rows as $row ) {
				if ( 'packed' === $row->status && $partner === (string) $row->partner ) {
					$items                    = (array) $row->items;
					$items[ $orderdetail_id ] = (int) $line->quantity;
					wp_easycart_shipments::update(
						(int) $row->shipment_id,
						array(
							'items'  => $items,
							'weight' => round( (float) $row->weight + $weight, 3 ),
						)
					);
					return (int) $row->shipment_id;
				}
			}
			$sort = 0;
			foreach ( $rows as $row ) {
				$sort = max( $sort, (int) $row->sort_order + 1 );
			}
			return wp_easycart_shipments::add(
				$order_id,
				array(
					'sort_order'   => $sort,
					'package_name' => wp_easycart_fulfillment::label( $partner ),
					'weight'       => $weight,
					'items'        => array( $orderdetail_id => (int) $line->quantity ),
					'provider'     => $partner,
				)
			);
		}

		/**
		 * Three sides, smallest first.
		 *
		 * @param float $a Side.
		 * @param float $b Side.
		 * @param float $c Side.
		 * @return array
		 */
		private static function sorted_dims( $a, $b, $c ) {
			$dims = array( max( 0, (float) $a ), max( 0, (float) $b ), max( 0, (float) $c ) );
			sort( $dims );
			return $dims;
		}

		/**
		 * A non-negative number from input.
		 *
		 * @param mixed $value Input.
		 * @return float
		 */
		public static function number( $value ) {
			$value = str_replace( ',', '.', trim( (string) $value ) );
			return is_numeric( $value ) ? max( 0, round( (float) $value, 3 ) ) : 0.0;
		}
	}

endif;
