<?php
/**
 * WP EasyCart — order shipments ( 6.0.2 ).
 *
 * One row in ec_order_shipment per package on an order: what is in it, its box, size and weight, and once it ships its
 * carrier, service, tracking number and link, tracking status, label and cost. The provider-neutral fulfilment API every
 * label extension ( Shippo, Stamps.com, ShipStation ) and WP EasyCart PRO's Fulfill actions use:
 *
 *   wp_easycart_order_add_shipment( $order_id, $args )   record a label / tracking number ( see record() )
 *   wp_easycart_shipments::plan( $order_id )             the packages to ship ( saved, or packed from Settings › Boxes )
 *   wp_easycart_shipments::track( $shipment_id, ... )    a tracking update; delivered packages can mark the order Delivered
 *   wp_easycart_shipments::void( $shipment_id )          a label that was refunded or cancelled
 *
 * Package statuses: packed ( no label yet ), label ( label bought, not handed over ), shipped, delivered, returned,
 * exception ( the carrier reported a problem ), voided. Return labels are rows with is_return = 1; they never change the
 * order's own tracking number or status.
 *
 * The order keeps its single tracking_number / shipping_carrier columns ( emails, exports and older extensions read
 * them ): they hold the first package's. Every package is listed in the shipped email and in My Account.
 *
 * Delivered: the status in ec_option_orderstatus_delivered ( made on first use as "Order Delivered", changeable in
 * Settings › Shipping › Delivery ). When every outgoing package is delivered and ec_option_mark_delivered_auto is on,
 * the order moves to it ( only from a paid status ) and wp_easycart_order_delivered fires. A partly refunded order
 * ( Partial Refund ) keeps its status: it reads delivered from its packages ( order_delivered() ).
 *
 * Fulfillment partners ( 6.0.2, wp_easycart_fulfillment ): lines a partner makes are packed into a package of the
 * partner's own ( provider = its slug; each row's `partner` says so ). Only that partner's records land on it, a store
 * label never does, the store's package editor leaves it alone, and the order is not marked Shipped while it waits. A
 * record naming some of a waiting package's items splits the package ( cover() ).
 *
 * @package WP_EasyCart
 * @since   6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_shipments' ) ) :

	/**
	 * Order shipments.
	 */
	final class wp_easycart_shipments {

		/** Package statuses. */
		const STATUSES = array( 'packed', 'label', 'shipped', 'delivered', 'returned', 'exception', 'voided' );

		/** Tracking statuses ( a label extension maps its carrier's words to these ). */
		const TRACKING = array( 'pre_transit', 'in_transit', 'out_for_delivery', 'delivered', 'returned', 'failure', 'unknown' );

		/** The option holding the Delivered order status. */
		const DELIVERED_OPTION = 'ec_option_orderstatus_delivered';

		/** Built-in statuses: shipped, picked up. */
		const STATUS_SHIPPED   = 2;
		const STATUS_PICKED_UP = 18;

		/**
		 * 6.0.2 bug round 8: Partial Refund. A partly refunded order still ships and arrives, and keeps this status when it is
		 * marked delivered ( refunds, reports and the store read it ): its packages say it was delivered.
		 */
		const STATUS_PARTIAL_REFUND = 17;

		/** 6.0.2: the transient keeping the order screen's shipping queue ( wp_easycart_admin_order_screen::queue() ). */
		const QUEUE_CACHE = 'wpec_ship_queue';

		/**
		 * 6.0.2: the kept shipping queue may still be there for this request ( forget_queue() deletes it once, until
		 * keep_queue() stores it again ).
		 *
		 * @var bool
		 */
		private static $queue_kept = true;

		/**
		 * 6.0.2: order locks this request holds ( lock name => depth ).
		 *
		 * @var array
		 */
		private static $locks = array();

		/**
		 * 6.0.2: the fulfillment partners each order's lines name, per request ( order_id => slugs ).
		 *
		 * @var array
		 */
		private static $line_providers = array();

		/**
		 * 6.0.2 bug round 8: orders wp_easycart_order_delivered was fired for in this request ( order_id => true ), so
		 * mark_order_delivered() does not fire it again after a package's tracking update did.
		 *
		 * @var array
		 */
		private static $announced = array();

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wpeasycart_order_inserted', array( __CLASS__, 'on_order_inserted' ), 30, 1 );
			add_filter( 'wp_easycart_shipping_email_tracking_url', array( __CLASS__, 'email_tracking_url' ), 5, 4 );
			/* 6.0.2: whatever can move an order into or out of the shipping queue forgets the kept counts. */
			$changes = array(
				'wpeasycart_order_inserted',
				'wpeasycart_order_status_update',
				'wpeasycart_order_updated',
				'wpeasycart_order_deleted',
				'wpeasycart_order_restored',
				'wpeasycart_order_detail_line_added',
				'wpeasycart_order_detail_line_update',
				'wpeasycart_order_detail_line_delete',
				'wpeasycart_partial_order_refund',
				'wpeasycart_full_order_refund',
				'wp_easycart_order_shipment_added',
				'wp_easycart_order_shipment_tracking',
				'wp_easycart_order_shipment_voided',
				'wp_easycart_order_lines_fulfillment_updated',
			);
			foreach ( $changes as $hook ) {
				add_action( $hook, array( __CLASS__, 'forget_queue' ), 1, 0 );
			}
		}

		/**
		 * 6.0.2: keep the order screen's shipping queue ( every order waiting to ship ) for two minutes; working it out reads
		 * every paid order.
		 *
		 * @param array $queue What wp_easycart_admin_order_screen::queue() worked out.
		 */
		public static function keep_queue( $queue ) {
			set_transient( self::QUEUE_CACHE, $queue, 2 * MINUTE_IN_SECONDS );
			self::$queue_kept = true;
		}

		/**
		 * 6.0.2: forget the kept shipping queue ( an order was placed, changed, shipped or deleted ). Once per request until
		 * it is kept again.
		 */
		public static function forget_queue() {
			if ( self::$queue_kept ) {
				self::$queue_kept = false;
				delete_transient( self::QUEUE_CACHE );
			}
		}

		/**
		 * The tables exist.
		 *
		 * @return bool
		 */
		public static function ready() {
			return class_exists( 'wp_easycart_packages' ) && wp_easycart_packages::ready();
		}

		// Reading.

		/**
		 * An order's packages.
		 *
		 * @param int   $order_id Order.
		 * @param array $args     voided ( include voided rows, default false ), returns ( include return labels, default true ).
		 * @return array Rows with items ( orderdetail_id => quantity ) and meta ( array ) decoded.
		 */
		public static function for_order( $order_id, $args = array() ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return array();
			}
			$args = wp_parse_args(
				$args,
				array(
					'voided'  => false,
					'returns' => true,
				)
			);
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_order_shipment WHERE order_id = %d ORDER BY is_return ASC, sort_order ASC, shipment_id ASC', (int) $order_id ) );
			$out  = array();
			foreach ( (array) $rows as $row ) {
				if ( ( ! $args['voided'] && 'voided' === $row->status ) || ( ! $args['returns'] && ! empty( $row->is_return ) ) ) {
					continue;
				}
				$out[] = self::decode( $row );
			}
			return $out;
		}

		/**
		 * One package.
		 *
		 * @param int $shipment_id Package.
		 * @return object|null
		 */
		public static function get( $shipment_id ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_order_shipment WHERE shipment_id = %d', (int) $shipment_id ) );
			return $row ? self::decode( $row ) : null;
		}

		/**
		 * The package a provider knows by its own reference ( e.g. a Shippo transaction ).
		 *
		 * @param string $provider Provider slug.
		 * @param string $ref      Provider reference.
		 * @return object|null
		 */
		public static function find_by_ref( $provider, $ref ) {
			global $wpdb;
			if ( ! self::ready() || '' === (string) $ref ) {
				return null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_order_shipment WHERE provider = %s AND provider_ref = %s ORDER BY shipment_id DESC LIMIT 1', (string) $provider, (string) $ref ) );
			return $row ? self::decode( $row ) : null;
		}

		/**
		 * The packages to ship: the saved ones, or the order packed from Settings › Boxes ( not saved; shipment_id 0 ).
		 *
		 * @param int $order_id Order.
		 * @return array Rows ( outgoing, not voided ).
		 */
		public static function plan( $order_id ) {
			$saved = self::for_order( $order_id, array( 'returns' => false ) );
			if ( $saved ) {
				return $saved;
			}
			$out = array();
			if ( ! class_exists( 'wp_easycart_packages' ) ) {
				return $out;
			}
			foreach ( wp_easycart_packages::pack( wp_easycart_packages::lines( $order_id ) ) as $i => $package ) {
				$out[] = self::decode(
					(object) array_merge(
						self::blank(),
						$package,
						array(
							'order_id'   => (int) $order_id,
							'sort_order' => $i,
							'items'      => $package['items'],
							'is_planned' => 1,
						)
					)
				);
			}
			return $out;
		}

		/**
		 * Save the packed packages when the order has none yet.
		 *
		 * @param int $order_id Order.
		 * @return array Saved rows.
		 */
		public static function build( $order_id ) {
			if ( ! self::ready() ) {
				return array();
			}
			/* 6.0.2: one request at a time per order, so two first labels at once ( or a label while checkout packs the
			   order ) never save the plan twice. */
			$locked = self::lock( $order_id );
			try {
				$saved = self::for_order( $order_id, array( 'returns' => false ) );
				if ( $saved ) {
					return $saved;
				}
				foreach ( self::plan( $order_id ) as $i => $package ) {
					$row = array(
						'sort_order'   => $i,
						'package_id'   => $package->package_id,
						'package_name' => $package->package_name,
						'length'       => $package->length,
						'width'        => $package->width,
						'height'       => $package->height,
						'weight'       => $package->weight,
						'items'        => $package->items,
						'status'       => 'packed',
					);
					/* 6.0.2: a partner's package keeps its partner, and a plan's chosen service when it has one. */
					foreach ( array( 'provider', 'service_code', 'service' ) as $key ) {
						if ( isset( $package->{$key} ) && '' !== (string) $package->{$key} ) {
							$row[ $key ] = (string) $package->{$key};
						}
					}
					self::add( $order_id, $row );
				}
				return self::for_order( $order_id, array( 'returns' => false ) );
			} finally {
				if ( $locked ) {
					self::unlock( $order_id );
				}
			}
		}

		/**
		 * Action wpeasycart_order_inserted: pack a new order ( Settings › Shipping › Boxes › Pack new orders ).
		 *
		 * @param int $order_id Order.
		 */
		public static function on_order_inserted( $order_id ) {
			if ( ! self::ready() || ! get_option( 'ec_option_pack_new_orders', 1 ) ) {
				return;
			}
			try {
				self::build( (int) $order_id );
			} catch ( \Throwable $e ) {
				// Packing never stops an order; the packages are worked out again when someone opens them.
				unset( $e );
			}
		}

		/**
		 * Replace an order's packages that have no label yet ( the order screen's package editor ). Packages with a label
		 * are kept as they are.
		 *
		 * @since 6.0.2 Only the store's packages: a fulfillment partner's packages, and the units in them, are left alone
		 *              ( a package given for a partner is skipped, and a partner's lines cannot be put in the store's ).
		 * @param int   $order_id Order.
		 * @param array $packages Rows: shipment_id ( 0 = new ), package_id, package_name, length, width, height, weight,
		 *                        items ( orderdetail_id => quantity ).
		 * @return array|WP_Error Saved rows.
		 */
		public static function save_packages( $order_id, $packages ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return new WP_Error( 'wp_easycart_shipments', __( 'Packages need the WP EasyCart database update to finish first.', 'wp-easycart' ) );
			}
			$order_id = (int) $order_id;
			unset( self::$line_providers[ $order_id ] );
			$existing = array();
			$partners = array();
			$held     = array();
			foreach ( self::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
				if ( '' !== $row->partner ) {
					$partners[] = (int) $row->shipment_id;
					foreach ( $row->items as $detail_id => $qty ) {
						$held[ (int) $detail_id ] = ( isset( $held[ (int) $detail_id ] ) ? $held[ (int) $detail_id ] : 0 ) + (int) $qty;
					}
					continue;
				}
				$existing[ (int) $row->shipment_id ] = $row;
			}
			$valid = array();
			foreach ( wp_easycart_packages::lines( $order_id ) as $line ) {
				if ( '' !== wp_easycart_packages::line_partner( $line ) ) {
					continue;
				}
				$qty = (int) $line->quantity - ( isset( $held[ (int) $line->orderdetail_id ] ) ? $held[ (int) $line->orderdetail_id ] : 0 );
				if ( $qty > 0 ) {
					$valid[ (int) $line->orderdetail_id ] = $qty;
				}
			}
			$keep = array();
			$sort = 0;
			foreach ( (array) $packages as $package ) {
				$package = (array) $package;
				$for_partner = ! empty( $package['provider'] ) ? self::partner(
					(object) array(
						'provider' => (string) $package['provider'],
						'order_id' => $order_id,
					)
				) : '';
				if ( '' !== $for_partner || ( isset( $package['shipment_id'] ) && in_array( (int) $package['shipment_id'], $partners, true ) ) ) {
					continue;
				}
				$items = array();
				foreach ( isset( $package['items'] ) ? (array) $package['items'] : array() as $detail_id => $qty ) {
					$detail_id = (int) $detail_id;
					$qty       = (int) $qty;
					if ( $qty > 0 && isset( $valid[ $detail_id ] ) ) {
						$items[ $detail_id ] = min( $qty, $valid[ $detail_id ] );
					}
				}
				$box  = ! empty( $package['package_id'] ) ? wp_easycart_packages::box( (int) $package['package_id'] ) : null;
				$data = array(
					'sort_order'   => $sort++,
					'package_id'   => $box ? (int) $box->package_id : 0,
					'package_name' => isset( $package['package_name'] ) && '' !== trim( (string) $package['package_name'] ) ? (string) $package['package_name'] : ( $box ? $box->label : __( 'Package', 'wp-easycart' ) ),
					'length'       => isset( $package['length'] ) ? $package['length'] : 0,
					'width'        => isset( $package['width'] ) ? $package['width'] : 0,
					'height'       => isset( $package['height'] ) ? $package['height'] : 0,
					'weight'       => isset( $package['weight'] ) ? $package['weight'] : 0,
					'items'        => $items,
				);
				$id   = isset( $package['shipment_id'] ) ? (int) $package['shipment_id'] : 0;
				if ( $id > 0 && isset( $existing[ $id ] ) ) {
					if ( 'packed' === $existing[ $id ]->status ) {
						self::update( $id, $data );
					}
					$keep[] = $id;
				} elseif ( $items ) {
					$keep[] = self::add( $order_id, array_merge( $data, array( 'status' => 'packed' ) ) );
				}
			}
			foreach ( $existing as $id => $row ) {
				if ( 'packed' === $row->status && ! in_array( $id, $keep, true ) ) {
					$wpdb->delete( 'ec_order_shipment', array( 'shipment_id' => $id ) );
				}
			}
			do_action( 'wp_easycart_order_packages_saved', $order_id );
			return self::for_order( $order_id, array( 'returns' => false ) );
		}

		/**
		 * Add a package.
		 *
		 * @param int   $order_id Order.
		 * @param array $data     Columns ( see columns() ).
		 * @return int Package ID, 0 on failure.
		 */
		public static function add( $order_id, $data ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return 0;
			}
			$now  = current_time( 'mysql', true );
			$row  = array_merge(
				self::columns( array_merge( array( 'status' => 'packed' ), (array) $data ) ),
				array(
					'order_id'   => (int) $order_id,
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
			$done = $wpdb->insert( 'ec_order_shipment', $row );
			return $done ? (int) $wpdb->insert_id : 0;
		}

		/**
		 * Change a package.
		 *
		 * @param int   $shipment_id Package.
		 * @param array $data        Columns ( see columns() ).
		 * @return bool
		 */
		public static function update( $shipment_id, $data ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return false;
			}
			$row               = self::columns( (array) $data );
			$row['updated_at'] = current_time( 'mysql', true );
			return false !== $wpdb->update( 'ec_order_shipment', $row, array( 'shipment_id' => (int) $shipment_id ) );
		}

		// Fulfilment.

		/**
		 * Record a label or tracking number on an order ( wp_easycart_order_add_shipment() ).
		 *
		 * Attaches to the package given, or the one with the same provider reference ( a webhook sent twice ), or the first
		 * package still waiting for a label; otherwise adds a package. Then updates the order's own tracking columns when
		 * this is its first package, fires wpeasycart_tracking_info_update, marks the order Shipped once no package is left
		 * without a label ( only from a paid status ), and sends the shipped email for this package when asked.
		 *
		 * 6.0.2, fulfillment partners: a partner's package only takes that partner's records ( provider = its slug ), and a
		 * store record never lands on a partner's package, even when its shipment_id is given. When items are given and a
		 * waiting package holds more than them, the package is split: the items recorded get the label, the rest keep
		 * waiting ( cover() ). A partner's package still waiting keeps the order from being marked Shipped.
		 *
		 * @param int   $order_id Order.
		 * @param array $args     shipment_id, carrier, service, service_code, tracking_number, tracking_url, label_url,
		 *                        label_format, cost, currency, provider, provider_ref, meta ( array ), is_return,
		 *                        package_id / package_name / length / width / height / weight / items ( for a new package ),
		 *                        status ( 'shipped' default, or 'label' ), mark_shipped ( bool, default true ),
		 *                        notify ( bool, send the shipped email, default false ), email_args ( array ).
		 * @return int|WP_Error Package ID.
		 */
		public static function record( $order_id, $args = array() ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$order    = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.order_id, ec_order.orderstatus_id, ec_order.tracking_number, ec_order.shipping_carrier, ec_order.shipping_method, ec_order.use_expedited_shipping, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			if ( ! $order ) {
				return new WP_Error( 'wp_easycart_shipments', __( 'Order not found.', 'wp-easycart' ) );
			}
			$args     = wp_parse_args(
				$args,
				array(
					'shipment_id'     => 0,
					'carrier'         => '',
					'tracking_number' => '',
					'provider'        => '',
					'provider_ref'    => '',
					'is_return'       => 0,
					'status'          => 'shipped',
					'mark_shipped'    => true,
					'notify'          => false,
					'email_args'      => array(),
				)
			);
			$tracking = trim( sanitize_text_field( (string) $args['tracking_number'] ) );
			$carrier  = class_exists( 'wp_easycart_carriers' ) ? wp_easycart_carriers::clean( $args['carrier'] ) : trim( sanitize_text_field( (string) $args['carrier'] ) );
			if ( class_exists( 'wp_easycart_carriers' ) ) {
				wp_easycart_carriers::saved( $carrier );
			}
			$status   = in_array( $args['status'], array( 'label', 'shipped', 'delivered' ), true ) ? $args['status'] : 'shipped';
			$now      = current_time( 'mysql', true );

			$shipment_id = 0;
			if ( self::ready() ) {
				$fields = array_diff_key( $args, array_flip( array( 'shipment_id', 'mark_shipped', 'notify', 'email_args' ) ) );
				$fields = array_merge(
					$fields,
					array(
						'status'          => $status,
						'tracking_number' => $tracking,
						'carrier'         => $carrier,
						'shipped_at'      => $now,
					)
				);
				/* 6.0.2: the items this label covers, when the caller said ( a package is then matched by its contents ). */
				$items = ( isset( $args['items'] ) && is_array( $args['items'] ) ) ? self::clean_items( $args['items'] ) : array();

				/* 6.0.2: one request at a time per order. Two requests recording the same label at once ( the purchase and the
				   carrier's webhook ) both found no row and added two packages and sent two emails; the second now waits and
				   finds the first one's row. If the lock cannot be had in 10 seconds, recording goes on as before. */
				$locked = self::lock( $order_id );
				try {
					unset( self::$line_providers[ $order_id ] );
					/* 6.0.2: whose record this is: a fulfillment partner's ( its slug ) or the store's ( '' ). */
					$partner = self::partner(
						(object) array(
							'provider' => (string) $args['provider'],
							'order_id' => $order_id,
						)
					);
					$target  = null;
					if ( '' !== (string) $args['provider_ref'] ) {
						$known = self::find_by_ref( (string) $args['provider'], (string) $args['provider_ref'] );
						if ( $known && (int) $known->order_id === $order_id ) {
							/* The same label again ( a webhook sent twice, two paths recording it ): refresh it, nothing more. */
							self::update( (int) $known->shipment_id, array_diff_key( $fields, array_flip( array( 'status', 'shipped_at' ) ) ) );
							return (int) $known->shipment_id;
						}
					}
					if ( (int) $args['shipment_id'] > 0 ) {
						$target = self::get( (int) $args['shipment_id'] );
						// 6.0.2: a package takes only its owner's records ( a partner's, or the store's ), waiting or shipped: a
						// store record named for a partner's package lands on a store package instead.
						if ( $target && (string) $target->partner !== (string) $partner ) {
							$target = null;
						}
					}
					if ( ! $target && empty( $args['is_return'] ) ) {
						$target = self::pick_waiting( $order_id, $items, $partner, $fields );
					}
					if ( $target && (int) $target->order_id === $order_id ) {
						$shipment_id = (int) $target->shipment_id;
						self::update( $shipment_id, $fields );
					} else {
						if ( empty( $args['is_return'] ) && ! self::for_order( $order_id, array( 'returns' => false ) ) ) {
							/* The first label on an order that was never packed: save the plan first, so the rest of its packages show. */
							self::build( $order_id );
							$planned = self::pick_waiting( $order_id, $items, $partner, $fields );
							if ( $planned ) {
								$shipment_id = (int) $planned->shipment_id;
								self::update( $shipment_id, $fields );
							}
						}
						if ( ! $shipment_id ) {
							$shipment_id = self::add( $order_id, $fields );
						}
					}
				} finally {
					if ( $locked ) {
						self::unlock( $order_id );
					}
				}
			}

			if ( empty( $args['is_return'] ) && '' !== $tracking ) {
				$first = ( '' === trim( (string) $order->tracking_number ) ) || ! self::tracking_is_active( $order_id, (string) $order->tracking_number );
				if ( $first ) {
					do_action( 'wpeasycart_tracking_info_update', $order_id, (int) $order->use_expedited_shipping, (string) $order->shipping_method, $carrier, $tracking );
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET shipping_carrier = %s, tracking_number = %s, last_updated = NOW() WHERE order_id = %d', $carrier, $tracking, $order_id ) );
				}
				self::log(
					$order_id,
					'order-shipping-method-update',
					array(
						'use_expedited_shipping' => (int) $order->use_expedited_shipping,
						'shipping_method'        => (string) $order->shipping_method,
						'shipping_carrier'       => $carrier,
						'tracking_number'        => $tracking,
					)
				);
			}

			if ( empty( $args['is_return'] ) && $args['mark_shipped'] && ! empty( $order->is_approved ) && ! self::is_fulfilled_status( (int) $order->orderstatus_id ) && ! in_array( (int) $order->orderstatus_id, array( 16, 17, 19 ), true ) && ! self::waiting_packages( $order_id ) ) {
				self::set_order_status( $order_id, self::STATUS_SHIPPED );
			}

			/**
			 * A package was labelled or given a tracking number.
			 *
			 * @since 6.0.2
			 * @param int   $order_id    Order.
			 * @param int   $shipment_id Package ( 0 before the database update ).
			 * @param array $args        What was recorded.
			 */
			do_action( 'wp_easycart_order_shipment_added', $order_id, $shipment_id, $args );
			do_action( 'wpeasycart_order_updated', $order_id );

			if ( empty( $args['is_return'] ) && $args['notify'] ) {
				$email_args = (array) $args['email_args'];
				$package    = $shipment_id ? self::get( $shipment_id ) : null;
				if ( $package && $package->items && ! isset( $email_args['items'] ) ) {
					$email_args['items'] = array_keys( $package->items );
				}
				self::send_shipped_email( $order_id, $tracking, $carrier, $email_args );
			}
			return $shipment_id;
		}

		/**
		 * Packages still waiting for a label. A fulfillment partner's package counts only while it still holds units the
		 * partner has to make ( partner_package_open() ).
		 *
		 * @since 6.0.2 $owner.
		 * @param int         $order_id Order.
		 * @param string|null $owner    null = every package, '' = the store's, a slug = that fulfillment partner's.
		 * @return int
		 */
		public static function waiting_packages( $order_id, $owner = null ) {
			$count = 0;
			$open  = array();
			foreach ( self::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
				if ( 'packed' === $row->status && ( null === $owner || (string) $row->partner === (string) $owner ) && ( '' === (string) $row->partner || self::partner_units_open( $row, $open ) ) ) {
					++$count;
				}
			}
			return $count;
		}

		/**
		 * A fulfillment partner's packed package still holds units to make: some line in it has units left after refunds,
		 * is not cancelled or shipped at the partner, and is not in an outgoing package ( wp_easycart_fulfillment::open_items() ).
		 * A package whose units were all refunded, cancelled or shipped in another package ( the rest left by cover() ) is
		 * settled: it no longer holds the order back from Shipped, and the customer no longer sees it as being made.
		 *
		 * @since 6.0.2
		 * @param object $row Package ( decoded ).
		 * @return bool False for a store's package or one that is not packed.
		 */
		public static function partner_package_open( $row ) {
			$open = array();
			return is_object( $row ) && 'packed' === (string) $row->status && '' !== (string) $row->partner && self::partner_units_open( $row, $open );
		}

		/**
		 * The work of partner_package_open() for a partner's packed package, with the partner's open items kept in $open across one call.
		 *
		 * @param object $row  Packed partner package ( decoded ).
		 * @param array  $open partner => wp_easycart_fulfillment::open_items(), filled as needed.
		 * @return bool
		 */
		private static function partner_units_open( $row, &$open ) {
			if ( ! class_exists( 'wp_easycart_fulfillment' ) || ! method_exists( 'wp_easycart_fulfillment', 'open_items' ) ) {
				return true;
			}
			$partner = (string) $row->partner;
			if ( ! isset( $open[ $partner ] ) ) {
				$open[ $partner ] = wp_easycart_fulfillment::open_items( (int) $row->order_id, $partner );
			}
			foreach ( array_keys( (array) $row->items ) as $detail_id ) {
				if ( ! empty( $open[ $partner ][ (int) $detail_id ] ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * A tracking number belongs to a package that was not voided.
		 *
		 * @param int    $order_id Order.
		 * @param string $tracking Tracking number.
		 * @return bool
		 */
		private static function tracking_is_active( $order_id, $tracking ) {
			global $wpdb;
			if ( ! self::ready() ) {
				return true;
			}
			$voided = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_order_shipment WHERE order_id = %d AND tracking_number = %s AND status = %s', (int) $order_id, (string) $tracking, 'voided' ) );
			$active = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_order_shipment WHERE order_id = %d AND tracking_number = %s AND status <> %s', (int) $order_id, (string) $tracking, 'voided' ) );
			return ! ( $voided && ! $active );
		}

		/**
		 * A tracking update for a package.
		 *
		 * @param int    $shipment_id Package.
		 * @param string $status      One of TRACKING ( or the carrier's word; unknown words are kept as 'unknown' ).
		 * @param string $detail      What the carrier said, e.g. "Delivered, front door".
		 * @param string $time        When, any strtotime() format ( UTC ); '' = now.
		 * @return bool
		 */
		public static function track( $shipment_id, $status, $detail = '', $time = '' ) {
			$row = self::get( $shipment_id );
			if ( ! $row ) {
				return false;
			}
			$status = strtolower( str_replace( array( '-', ' ' ), '_', (string) $status ) );
			if ( 'transit' === $status ) {
				$status = 'in_transit';
			}
			if ( ! in_array( $status, self::TRACKING, true ) ) {
				$status = 'unknown';
			}
			$when = '' !== (string) $time && strtotime( (string) $time ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $time ) ) : current_time( 'mysql', true );
			/* 6.0.2: carrier updates can arrive out of order. One older than the last update kept, or an earlier step after
			   the package was delivered or returned, changes nothing ( the customer would read "In transit" again ). Delivered and
			   returned count while the package is neither: the last update kept may carry the time it was received rather than
			   the carrier's own time, so a delivery the carrier dates earlier was dropped and the order never reached Delivered.
			   Once it is delivered or returned, an older delivered / returned ( a retried webhook ) changes nothing either. */
			$last     = isset( $row->tracking_updated ) ? (string) $row->tracking_updated : '';
			$terminal = array( 'delivered', 'returned' );
			if ( '' !== (string) $time && '' !== $last && 0 !== strpos( $last, '0000' ) && strtotime( $when ) < strtotime( $last ) && ! ( in_array( $status, $terminal, true ) && ! in_array( $row->status, $terminal, true ) ) ) {
				return true;
			}
			if ( in_array( $row->status, array( 'delivered', 'returned' ), true ) && in_array( $status, array( 'pre_transit', 'in_transit', 'out_for_delivery', 'unknown' ), true ) ) {
				return true;
			}
			$data    = array(
				'tracking_status'  => $status,
				'tracking_detail'  => $detail,
				'tracking_updated' => $when,
			);
			$arrived = false;
			if ( 'voided' !== $row->status ) {
				if ( 'delivered' === $status ) {
					/* 6.0.2 bug round 8: the arrival is claimed with a conditional write, so exactly one update is a package's
					   arrival ( a retried webhook, or two requests at once, find it delivered already ). */
					$arrived              = 'delivered' !== $row->status && self::claim_delivered( (int) $shipment_id );
					$data['status']       = 'delivered';
					$data['delivered_at'] = $when;
				} elseif ( 'returned' === $status ) {
					$data['status'] = 'returned';
				} elseif ( 'failure' === $status ) {
					$data['status'] = 'exception';
				} elseif ( in_array( $status, array( 'in_transit', 'out_for_delivery' ), true ) && in_array( $row->status, array( 'label', 'exception' ), true ) ) {
					$data['status'] = 'shipped';
				}
			}
			self::update( $shipment_id, $data );
			/**
			 * A package's tracking changed.
			 *
			 * @since 6.0.2
			 * @param int    $order_id    Order.
			 * @param int    $shipment_id Package.
			 * @param string $status      Tracking status.
			 * @param string $detail      Carrier's words.
			 */
			do_action( 'wp_easycart_order_shipment_tracking', (int) $row->order_id, (int) $shipment_id, $status, (string) $detail );
			if ( 'delivered' === $status && empty( $row->is_return ) ) {
				self::maybe_mark_delivered( (int) $row->order_id, $arrived );
			}
			return true;
		}

		/**
		 * Move the order to Delivered once every outgoing package is delivered ( packages_delivered() ), while Settings ›
		 * Shipping › Delivery › Mark orders delivered from tracking is on.
		 *
		 * @since 6.0.2 bug round 8 $arrived. A partly refunded order ( Partial Refund ) keeps its status: the update that
		 *              delivered its last package fires wp_easycart_order_delivered instead. The status is written only from the
		 *              status read a moment ago, so two updates at once move the order, and fire the action, once.
		 * @param int  $order_id Order.
		 * @param bool $arrived  This update was a package's arrival ( track() claimed it ).
		 * @return bool Moved.
		 */
		public static function maybe_mark_delivered( $order_id, $arrived = false ) {
			global $wpdb;
			if ( ! get_option( 'ec_option_mark_delivered_auto', 1 ) || ! self::packages_delivered( $order_id ) ) {
				return false;
			}
			$order = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.orderstatus_id, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', (int) $order_id ) );
			if ( ! $order || empty( $order->is_approved ) ) {
				return false;
			}
			$status = (int) $order->orderstatus_id;
			if ( self::STATUS_PARTIAL_REFUND === $status ) {
				if ( $arrived ) {
					self::announce_delivered( $order_id );
				}
				return false;
			}
			if ( in_array( $status, array( 16, 19 ), true ) ) {
				return false;
			}
			$status_id = self::delivered_status_id();
			if ( $status_id <= 0 || $status === $status_id || ! self::set_order_status( $order_id, $status_id, $status ) ) {
				return false;
			}
			self::announce_delivered( $order_id );
			return true;
		}

		/**
		 * Does the order read as delivered? Its status is the Delivered status, or every package it sent arrived
		 * ( packages_delivered() ): a partly refunded order keeps its Partial Refund status when it is marked delivered, and
		 * a store with Mark orders delivered from tracking off keeps its status too.
		 *
		 * @since 6.0.2
		 * @param int      $order_id  Order.
		 * @param int|null $status_id The order's status, when the caller has it.
		 * @return bool
		 */
		public static function order_delivered( $order_id, $status_id = null ) {
			global $wpdb;
			if ( null === $status_id ) {
				$status_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', (int) $order_id ) );
			}
			$delivered = self::delivered_status_id_if_set();
			return ( $delivered > 0 && (int) $status_id === $delivered ) || self::packages_delivered( $order_id );
		}

		/**
		 * Every package the order sent arrived: at least one outgoing package, each one delivered, and none still waiting to
		 * go ( a fulfillment partner's package whose units were all refunded, cancelled or shipped in another package is
		 * settled, not waiting: partner_package_open() ).
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 * @return bool
		 */
		public static function packages_delivered( $order_id ) {
			if ( ! self::ready() ) {
				return false;
			}
			$sent = 0;
			$open = array();
			foreach ( self::for_order( (int) $order_id, array( 'returns' => false ) ) as $row ) {
				if ( 'packed' === (string) $row->status ) {
					if ( '' === (string) $row->partner || self::partner_units_open( $row, $open ) ) {
						return false;
					}
					continue;
				}
				if ( 'delivered' !== (string) $row->status ) {
					return false;
				}
				++$sent;
			}
			return $sent > 0;
		}

		/**
		 * The order's own tracking number when no package of the store's has gone out and no fulfillment partner's package
		 * carries it: typed on the order before packages existed, or written there by an older WP EasyCart PRO or an
		 * extension. wp_easycart_fulfillment::state() reads it as the store's items shipped.
		 *
		 * @since 6.0.2
		 * @param int         $order_id Order.
		 * @param object|null $order    The order row, when the caller has it ( tracking_number ).
		 * @return string '' when there is none, or a package explains it.
		 */
		public static function unrecorded_tracking( $order_id, $order = null ) {
			global $wpdb;
			if ( is_object( $order ) && property_exists( $order, 'tracking_number' ) ) {
				$tracking = trim( (string) $order->tracking_number );
			} else {
				$tracking = trim( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT tracking_number FROM ec_order WHERE order_id = %d', (int) $order_id ) ) );
			}
			if ( '' === $tracking ) {
				return '';
			}
			foreach ( self::for_order( (int) $order_id, array( 'returns' => false ) ) as $row ) {
				$store_out = '' === (string) $row->partner && in_array( (string) $row->status, array( 'label', 'shipped', 'delivered', 'exception' ), true );
				if ( $store_out || ( '' !== (string) $row->partner && trim( (string) $row->tracking_number ) === $tracking ) ) {
					return '';
				}
			}
			return $tracking;
		}

		/**
		 * Will a tracking feed mark this order delivered? A package on its way that a label extension or a fulfillment partner
		 * recorded ( its provider is set ) gets the carrier's updates ( track() ), and the order moves to Delivered once every
		 * package arrived ( a partly refunded order keeps its status and reads delivered from its packages ), while Settings ›
		 * Shipping › Delivery › Mark orders delivered from tracking is on. A package the store recorded by hand ( Add tracking,
		 * Ship order ) gets no updates, so nothing marks it delivered.
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 * @return bool
		 */
		public static function delivery_tracked( $order_id ) {
			$tracked = false;
			if ( get_option( 'ec_option_mark_delivered_auto', 1 ) && self::ready() ) {
				foreach ( self::for_order( (int) $order_id, array( 'returns' => false ) ) as $row ) {
					if ( '' !== (string) $row->provider && in_array( (string) $row->status, array( 'label', 'shipped', 'exception' ), true ) ) {
						$tracked = true;
						break;
					}
				}
			}
			/**
			 * Whether carrier tracking will mark the order delivered ( the order screen then offers no Mark delivered ). A label
			 * extension whose packages get no tracking updates can say false.
			 *
			 * @since 6.0.2
			 * @param bool $tracked  A package on its way was recorded by a label extension or a fulfillment partner.
			 * @param int  $order_id Order.
			 */
			return (bool) apply_filters( 'wp_easycart_order_delivery_tracked', $tracked, (int) $order_id );
		}

		/**
		 * The store marks an order delivered ( the order screen's Delivered step and next step panel ): each outgoing package on
		 * its way is marked delivered ( track(), so the customer's My Account says so ), then the order moves to the Delivered
		 * status and wp_easycart_order_delivered fires, once. Settings › Shipping › Delivery › Mark orders delivered from
		 * tracking is about carrier updates; the store's own click moves the order either way ( the status is made on first
		 * use, again if it was deleted ). Nothing moves while a fulfillment partner still has to ship its package ( a package of
		 * the store's that never got tracking is the store's word that it shipped ).
		 *
		 * 6.0.2 bug round 8: a partly refunded order ( Partial Refund ) still ships and arrives. It keeps its status, which the
		 * refund screens, reports and the store go by: its packages are marked delivered, the order reads delivered from them
		 * ( order_delivered() ), and wp_easycart_order_delivered fires once. An order whose tracking number is only on the order
		 * ( unrecorded_tracking(): typed before packages existed ) gets it kept as a package first ( adopt_tracking() ), so the
		 * delivery is kept with it and shown in My Account. Refused: unpaid, refunded, picked up and canceled orders.
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 * @return array|WP_Error packages ( marked delivered now ), moved ( the order moved to the Delivered status, by this call
		 *                        or by the last package's tracking update ), status_id ( the order's status now ), kept ( the
		 *                        order keeps its Partial Refund status ), delivered ( the order reads delivered now ).
		 */
		public static function mark_order_delivered( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$order    = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.orderstatus_id, ec_order.tracking_number, ec_order.shipping_carrier, ec_orderstatus.is_approved FROM ec_order LEFT JOIN ec_orderstatus ON ec_orderstatus.status_id = ec_order.orderstatus_id WHERE ec_order.order_id = %d', $order_id ) );
			if ( ! $order ) {
				return new WP_Error( 'order', __( 'The order could not be found.', 'wp-easycart' ) );
			}
			$before = (int) $order->orderstatus_id;
			if ( empty( $order->is_approved ) || in_array( $before, array( 16, self::STATUS_PICKED_UP, 19 ), true ) ) {
				return new WP_Error( 'status', __( 'Only a paid order that was not refunded, canceled or picked up can be marked delivered.', 'wp-easycart' ) );
			}
			$keep    = ( self::STATUS_PARTIAL_REFUND === $before );
			$marked  = 0;
			$waiting = false;
			unset( self::$announced[ $order_id ] );
			/* One request at a time per order: a second click waits, then finds the packages delivered ( and keeps no second
			   package for the order's own tracking number ). */
			$locked = self::lock( $order_id );
			try {
				$loose = self::unrecorded_tracking( $order_id, $order );
				/* A package of the store's still waiting for its tracking, on an order not marked shipped, has not gone yet ( unless
				   the order's own tracking number says it went ). */
				if ( ! self::is_fulfilled_status( $before ) && '' === $loose && self::waiting_packages( $order_id, '' ) > 0 ) {
					return new WP_Error( 'waiting', __( 'A package on this order is still waiting to ship. Add its tracking number first.', 'wp-easycart' ) );
				}
				if ( $keep && self::packages_delivered( $order_id ) ) {
					return array(
						'packages'  => 0,
						'moved'     => false,
						'status_id' => $before,
						'kept'      => true,
						'delivered' => true,
					);
				}
				if ( '' !== $loose && self::ready() ) {
					self::adopt_tracking( $order_id, $order );
				}
				foreach ( self::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
					if ( in_array( (string) $row->status, array( 'label', 'shipped', 'exception' ), true ) ) {
						/* Claimed first, so a carrier's update delivering it at the same moment does not count it twice. */
						if ( self::claim_delivered( (int) $row->shipment_id ) ) {
							self::track( (int) $row->shipment_id, 'delivered', __( 'Marked delivered by the store', 'wp-easycart' ) );
							++$marked;
						}
					} elseif ( self::partner_package_open( $row ) ) {
						$waiting = true;
					}
				}
			} finally {
				if ( $locked ) {
					self::unlock( $order_id );
				}
			}
			if ( $keep && 0 === $marked ) {
				return new WP_Error( 'nothing', __( 'This order has no shipped package to mark delivered. Add its tracking number first.', 'wp-easycart' ) );
			}
			$status    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', $order_id ) );
			$delivered = self::delivered_status_id_if_set();
			$moved     = ( $delivered > 0 && $status === $delivered && $status !== $before );
			if ( $keep ) {
				/* The status stays: the order is delivered once its last package is ( a tracking update may have said so already ). */
				if ( ! $waiting && empty( self::$announced[ $order_id ] ) && self::packages_delivered( $order_id ) ) {
					self::announce_delivered( $order_id );
				}
				do_action( 'wpeasycart_order_updated', $order_id );
			} elseif ( ! $waiting && ! ( $delivered > 0 && $status === $delivered ) && ! in_array( $status, array( 16, 17, 19 ), true ) ) {
				$delivered = self::delivered_status_id( true );
				/* Only from the status it had a moment ago: a second click, or a tracking update at the same time, moves it once. */
				if ( $delivered > 0 && $delivered !== $status && self::set_order_status( $order_id, $delivered, $status ) ) {
					self::announce_delivered( $order_id );
					$moved  = true;
					$status = $delivered;
				}
			}
			return array(
				'packages'  => $marked,
				'moved'     => $moved,
				'status_id' => $status,
				'kept'      => $keep,
				'delivered' => self::order_delivered( $order_id, $status ),
			);
		}

		/**
		 * Keep the order's own tracking number ( unrecorded_tracking() ) on a package of the store's, so the delivery can be
		 * kept with it and My Account lists it: the store's packages still waiting become the one package holding them all
		 * ( cover() ), or, when the order has none, one package holds the store's shippable items. The order shipped before, so
		 * nothing is announced ( no wp_easycart_order_shipment_added, which would report a new shipment, no shipped email, no
		 * second tracking entry in the activity log ). Called under the order's lock.
		 *
		 * @since 6.0.2
		 * @param int    $order_id Order.
		 * @param object $order    Order row ( tracking_number, shipping_carrier ).
		 * @return int Package, 0 when the store has nothing of its own to ship.
		 */
		private static function adopt_tracking( $order_id, $order ) {
			$fields = array(
				'carrier'         => trim( (string) $order->shipping_carrier ),
				'tracking_number' => trim( (string) $order->tracking_number ),
				'status'          => 'shipped',
			);
			if ( self::waiting_packages( $order_id, '' ) > 0 ) {
				$shipment_id = self::cover( $order_id, array(), '' );
				if ( $shipment_id > 0 ) {
					self::update( $shipment_id, $fields );
					return $shipment_id;
				}
			}
			$items  = array();
			$weight = 0.0;
			foreach ( wp_easycart_packages::lines( $order_id ) as $line ) {
				if ( empty( $line->is_shippable ) || (int) $line->quantity <= 0 || '' !== wp_easycart_packages::line_partner( $line ) ) {
					continue;
				}
				$items[ (int) $line->orderdetail_id ] = (int) $line->quantity;
				$weight                              += (float) $line->weight * (int) $line->quantity;
			}
			if ( ! $items ) {
				return 0;
			}
			return self::add(
				$order_id,
				array_merge(
					$fields,
					array(
						'package_name' => __( 'Package', 'wp-easycart' ),
						'weight'       => $weight,
						'items'        => $items,
					)
				)
			);
		}

		/**
		 * Mark a package delivered only if it is not already ( or voided ): the one request that does it is the arrival.
		 *
		 * @since 6.0.2
		 * @param int $shipment_id Package.
		 * @return bool This call delivered it.
		 */
		private static function claim_delivered( $shipment_id ) {
			global $wpdb;
			return (bool) $wpdb->query( $wpdb->prepare( "UPDATE ec_order_shipment SET status = 'delivered' WHERE shipment_id = %d AND status NOT IN ( 'delivered', 'voided' )", (int) $shipment_id ) );
		}

		/**
		 * Fire wp_easycart_order_delivered. Callers make sure it is once per delivery: a conditional status write, or the
		 * claimed arrival of the order's last package.
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 */
		private static function announce_delivered( $order_id ) {
			self::$announced[ (int) $order_id ] = true;
			/**
			 * The order was delivered: every package it sent arrived, or the store marked it delivered, and it moved to the
			 * Delivered status. A partly refunded order keeps its Partial Refund status; this fires when its last package is
			 * delivered. Once per delivery ( a second click, a retried tracking update or two at once do not fire it again ).
			 *
			 * @since 6.0.2
			 * @param int $order_id Order.
			 */
			do_action( 'wp_easycart_order_delivered', (int) $order_id );
		}

		/**
		 * A label was refunded or cancelled. When it held the order's tracking number, the next package's takes its place.
		 *
		 * @param int $shipment_id Package.
		 * @return bool
		 */
		public static function void( $shipment_id ) {
			global $wpdb;
			$row = self::get( $shipment_id );
			if ( ! $row ) {
				return false;
			}
			self::update( $shipment_id, array( 'status' => 'voided' ) );
			if ( empty( $row->is_return ) && '' !== (string) $row->tracking_number ) {
				$order_tracking = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT tracking_number FROM ec_order WHERE order_id = %d', (int) $row->order_id ) );
				if ( $order_tracking === (string) $row->tracking_number ) {
					$next = null;
					foreach ( self::for_order( (int) $row->order_id, array( 'returns' => false ) ) as $other ) {
						if ( '' !== (string) $other->tracking_number ) {
							$next = $other;
							break;
						}
					}
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET shipping_carrier = %s, tracking_number = %s, last_updated = NOW() WHERE order_id = %d', $next ? $next->carrier : '', $next ? $next->tracking_number : '', (int) $row->order_id ) );
				}
			}
			/**
			 * A package's label was voided.
			 *
			 * @since 6.0.2
			 * @param int    $order_id    Order.
			 * @param int    $shipment_id Package.
			 * @param object $row         The package before it was voided.
			 */
			do_action( 'wp_easycart_order_shipment_voided', (int) $row->order_id, (int) $shipment_id, $row );
			return true;
		}

		/**
		 * Change an order's status the way the order screen does: the row, wpeasycart_order_status_update, the activity
		 * log, and wpeasycart_order_shipped for Shipped.
		 *
		 * @since 6.0.2 $from: the status the order must still have ( the write is conditional, and nothing fires when it changed
		 *              nothing ).
		 * @param int      $order_id  Order.
		 * @param int      $status_id Status.
		 * @param int|null $from      Only from this status ( null: whatever it is ).
		 * @return bool The status was written.
		 */
		public static function set_order_status( $order_id, $status_id, $from = null ) {
			global $wpdb;
			if ( null !== $from ) {
				$previous = (int) $from;
				if ( ! $wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET orderstatus_id = %d, last_updated = NOW() WHERE order_id = %d AND orderstatus_id = %d', (int) $status_id, (int) $order_id, $previous ) ) ) {
					return false;
				}
			} else {
				$previous = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', (int) $order_id ) );
				$wpdb->query( $wpdb->prepare( 'UPDATE ec_order SET orderstatus_id = %d, last_updated = NOW() WHERE order_id = %d', (int) $status_id, (int) $order_id ) );
			}
			/* 6.0.2: the status before, as the third argument. */
			do_action( 'wpeasycart_order_status_update', (int) $order_id, (int) $status_id, $previous );
			self::log( $order_id, 'order-status-update', array( 'orderstatus_id' => (int) $status_id ) );
			if ( self::STATUS_SHIPPED === (int) $status_id ) {
				do_action( 'wpeasycart_order_shipped', (int) $order_id );
			}
			do_action( 'wpeasycart_order_updated', (int) $order_id );
			return true;
		}

		/**
		 * An activity log entry.
		 *
		 * @param int    $order_id Order.
		 * @param string $key      order_log_key.
		 * @param array  $meta     key => value.
		 */
		private static function log( $order_id, $key, $meta ) {
			global $wpdb;
			if ( function_exists( 'wp_easycart_order_log' ) ) {
				wp_easycart_order_log( $order_id, $key, $meta ); /* 6.0.2: the shared helper */
				return;
			}
			$wpdb->insert(
				'ec_order_log',
				array(
					'order_id'      => (int) $order_id,
					'order_log_key' => $key,
				)
			);
			$log_id = (int) $wpdb->insert_id;
			if ( $log_id <= 0 ) {
				return;
			}
			foreach ( $meta as $meta_key => $meta_value ) {
				$wpdb->insert(
					'ec_order_log_meta',
					array(
						'order_log_id'         => $log_id,
						'order_id'             => (int) $order_id,
						'order_log_meta_key'   => $meta_key,
						'order_log_meta_value' => (string) $meta_value,
					)
				);
			}
		}

		// Delivered status.

		/**
		 * The Delivered order status, made on first use ( "Order Delivered", counts as paid ).
		 *
		 * @since 6.0.2 bug round 8 $make: the store's own Mark delivered makes it again after it was deleted, even with
		 *              automatic delivery off.
		 * @param bool $make Make it again when it was deleted, whatever Mark orders delivered from tracking says.
		 * @return int Status ID, 0 when the status table is not there.
		 */
		public static function delivered_status_id( $make = false ) {
			global $wpdb;
			$id = (int) get_option( self::DELIVERED_OPTION, 0 );
			if ( $id > 0 && $wpdb->get_var( $wpdb->prepare( 'SELECT status_id FROM ec_orderstatus WHERE status_id = %d', $id ) ) ) {
				return $id;
			}
			/* 6.0.2: a merchant who deleted the status and has automatic delivery off doesn't get it back on every Shipping
			   settings visit. It is made again only when orders are to be marked delivered. */
			if ( $id > 0 && ! $make && ! get_option( 'ec_option_mark_delivered_auto', 1 ) ) {
				return 0;
			}
			$done = $wpdb->insert(
				'ec_orderstatus',
				array(
					'order_status' => 'Order Delivered',
					'is_approved'  => 1,
					'color_code'   => '#15803D',
				)
			);
			$id   = $done ? (int) $wpdb->insert_id : 0;
			if ( $id > 0 ) {
				update_option( self::DELIVERED_OPTION, $id );
			}
			return $id;
		}

		/**
		 * The Delivered status, only when it was already made ( no insert ).
		 *
		 * @return int
		 */
		public static function delivered_status_id_if_set() {
			return (int) get_option( self::DELIVERED_OPTION, 0 );
		}

		/**
		 * Statuses that mean the order left the store: Shipped, Picked up and Delivered.
		 *
		 * @return array
		 */
		public static function fulfilled_status_ids() {
			$ids       = array( self::STATUS_SHIPPED, self::STATUS_PICKED_UP );
			$delivered = self::delivered_status_id_if_set();
			if ( $delivered > 0 ) {
				$ids[] = $delivered;
			}
			return $ids;
		}

		/**
		 * A status is one of fulfilled_status_ids().
		 *
		 * @param int $status_id Status.
		 * @return bool
		 */
		public static function is_fulfilled_status( $status_id ) {
			return in_array( (int) $status_id, self::fulfilled_status_ids(), true );
		}

		// Display.

		/**
		 * Words for a package status.
		 *
		 * @since 6.0.2 $row: a fulfillment partner's package still waiting reads "With {partner}".
		 * @param string      $status Package status.
		 * @param object|null $row    The package, when there is one.
		 * @return string
		 */
		public static function status_label( $status, $row = null ) {
			if ( 'packed' === $status && is_object( $row ) && ! empty( $row->partner ) && class_exists( 'wp_easycart_fulfillment' ) ) {
				/* translators: %s: fulfillment partner, e.g. Printful. */
				return sprintf( __( 'With %s', 'wp-easycart' ), wp_easycart_fulfillment::label( $row->partner ) );
			}
			$labels = array(
				'packed'    => __( 'Waiting for a label', 'wp-easycart' ),
				'label'     => __( 'Label created', 'wp-easycart' ),
				'shipped'   => __( 'Shipped', 'wp-easycart' ),
				'delivered' => __( 'Delivered', 'wp-easycart' ),
				'returned'  => __( 'Returned', 'wp-easycart' ),
				'exception' => __( 'Delivery problem', 'wp-easycart' ),
				'voided'    => __( 'Voided', 'wp-easycart' ),
			);
			return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( (string) $status );
		}

		/**
		 * Words for a tracking status.
		 *
		 * @param string $status Tracking status.
		 * @return string
		 */
		public static function tracking_label( $status ) {
			$labels = array(
				'pre_transit'      => __( 'Label created', 'wp-easycart' ),
				'in_transit'       => __( 'In transit', 'wp-easycart' ),
				'out_for_delivery' => __( 'Out for delivery', 'wp-easycart' ),
				'delivered'        => __( 'Delivered', 'wp-easycart' ),
				'returned'         => __( 'Returned to sender', 'wp-easycart' ),
				'failure'          => __( 'Delivery problem', 'wp-easycart' ),
				'unknown'          => __( 'Waiting for the carrier', 'wp-easycart' ),
			);
			return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
		}

		/**
		 * A package's delivery status for the customer, in the store's wording ( language file section documents ).
		 *
		 * @param array $row A tracking_rows() row.
		 * @return string '' before the carrier reported anything.
		 */
		public static function customer_status( $row ) {
			/* 6.0.2: a fulfillment partner's package still waiting is being made ( the partner's name is not shown ). */
			if ( isset( $row['status'] ) && 'packed' === $row['status'] && ! empty( $row['partner'] ) && class_exists( 'wp_easycart_fulfillment' ) ) {
				return wp_strip_all_tags( wp_easycart_fulfillment::text( 'being_made', __( 'Being made', 'wp-easycart' ) ) );
			}
			if ( ! class_exists( 'wp_easycart_documents' ) ) {
				return '';
			}
			$status = isset( $row['tracking_status'] ) ? (string) $row['tracking_status'] : '';
			if ( '' === $status && isset( $row['status'] ) && 'delivered' === $row['status'] ) {
				$status = 'delivered';
			}
			/* 6.0.2: the fallbacks are translated, like every other document phrase's. */
			$map = array(
				'pre_transit'      => array( 'tracking_label_created', __( 'Label created', 'wp-easycart' ) ),
				'in_transit'       => array( 'tracking_in_transit', __( 'In transit', 'wp-easycart' ) ),
				'out_for_delivery' => array( 'tracking_out_for_delivery', __( 'Out for delivery', 'wp-easycart' ) ),
				'delivered'        => array( 'tracking_delivered', __( 'Delivered', 'wp-easycart' ) ),
				'failure'          => array( 'tracking_problem', __( 'Delivery problem', 'wp-easycart' ) ),
				'returned'         => array( 'tracking_returned', __( 'Returned to sender', 'wp-easycart' ) ),
			);
			return isset( $map[ $status ] ) ? wp_strip_all_tags( wp_easycart_documents::text( $map[ $status ][0], $map[ $status ][1] ) ) : '';
		}

		/**
		 * The link a customer follows for a package: the one its label service gave, else the carrier's tracking page.
		 *
		 * @param object $row Package.
		 * @return string
		 */
		public static function tracking_url( $row ) {
			if ( '' !== (string) $row->tracking_url ) {
				return (string) $row->tracking_url;
			}
			if ( class_exists( 'wp_easycart_email_design' ) && method_exists( 'wp_easycart_email_design', 'tracking_url' ) ) {
				return wp_easycart_email_design::tracking_url( (string) $row->carrier, (string) $row->tracking_number );
			}
			return '';
		}

		/**
		 * Outgoing packages with a tracking number, for emails and My Account.
		 *
		 * @param int $order_id Order.
		 * @return array Rows of carrier, service, tracking, url, status, tracking_status, label ( the status in words ), items.
		 */
		public static function tracking_rows( $order_id ) {
			$out = array();
			foreach ( self::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
				if ( '' === (string) $row->tracking_number ) {
					continue;
				}
				$words = '' !== (string) $row->tracking_status ? self::tracking_label( $row->tracking_status ) : '';
				$out[] = array(
					'shipment_id'     => (int) $row->shipment_id,
					'carrier'         => (string) $row->carrier,
					'service'         => (string) $row->service,
					'tracking'        => (string) $row->tracking_number,
					'url'             => self::tracking_url( $row ),
					'status'          => (string) $row->status,
					'tracking_status' => (string) $row->tracking_status,
					'label'           => '' !== $words ? $words : self::status_label( $row->status, $row ),
					'items'           => $row->items,
					'partner'         => (string) $row->partner,
				);
			}
			return $out;
		}

		/**
		 * The packages a customer sees in My Account: every package with a tracking number, then each fulfillment partner's
		 * package still being made ( no tracking yet; customer_status() reads "Being made" ): only once the order went to its
		 * partners ( released ), while it is not cancelled or refunded, and while the package holds units to make
		 * ( partner_package_open() ).
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 * @return array tracking_rows() rows.
		 */
		public static function customer_rows( $order_id ) {
			global $wpdb;
			$out   = self::tracking_rows( $order_id );
			$open  = array();
			$going = null;
			foreach ( self::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
				if ( 'packed' !== $row->status || '' === $row->partner ) {
					continue;
				}
				if ( null === $going ) {
					$status = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT orderstatus_id FROM ec_order WHERE order_id = %d', (int) $order_id ) );
					$going  = ! in_array( $status, array( 16, 19 ), true ) && ( ! class_exists( 'wp_easycart_fulfillment' ) || wp_easycart_fulfillment::released( (int) $order_id ) );
				}
				if ( ! $going || ! self::partner_units_open( $row, $open ) ) {
					continue;
				}
				$out[] = array(
					'shipment_id'     => (int) $row->shipment_id,
					'carrier'         => '',
					'service'         => '',
					'tracking'        => '',
					'url'             => '',
					'status'          => 'packed',
					'tracking_status' => '',
					'label'           => self::status_label( 'packed', $row ),
					'items'           => $row->items,
					'partner'         => (string) $row->partner,
				);
			}
			return $out;
		}

		/**
		 * Filter wp_easycart_shipping_email_tracking_url: the label service's own tracking link for that number.
		 *
		 * @param string $url      Link so far.
		 * @param string $carrier  Carrier.
		 * @param string $tracking Tracking number.
		 * @param object $order    Order.
		 * @return string
		 */
		public static function email_tracking_url( $url, $carrier = '', $tracking = '', $order = null ) {
			if ( '' === (string) $tracking || ! is_object( $order ) || empty( $order->order_id ) ) {
				return $url;
			}
			foreach ( self::for_order( (int) $order->order_id, array( 'returns' => false ) ) as $row ) {
				if ( (string) $row->tracking_number === (string) $tracking && '' !== (string) $row->tracking_url ) {
					return (string) $row->tracking_url;
				}
			}
			return $url;
		}

		// Email.

		/**
		 * Send the shipped email from anywhere ( a webhook, a cron run ): the order screen's sender, loaded when this request
		 * did not load the admin.
		 *
		 * @param int    $order_id Order.
		 * @param string $tracking Tracking number.
		 * @param string $carrier  Carrier.
		 * @param array  $args     render_shipping_email() choices ( items: orderdetail IDs in this package ).
		 * @return bool Sent.
		 */
		public static function send_shipped_email( $order_id, $tracking, $carrier, $args = array() ) {
			if ( ! function_exists( 'wp_easycart_admin_orders' ) ) {
				// admin-init.php includes this file on init for store staff in wp-admin; including it before then would
				// declare it twice.
				if ( ! did_action( 'init' ) || ! defined( 'EC_PLUGIN_DIRECTORY' ) || ! is_readable( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_orders.php' ) ) {
					return false;
				}
				/* 6.0.2: did_action( 'init' ) is already true while init runs. A staff wp-admin request still inside init
				   ( before admin-init.php, priority 10 ) sends the email once WordPress has loaded instead. */
				if ( doing_action( 'init' ) && ! did_action( 'wp_loaded' ) && is_admin() && ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ) ) ) {
					add_action(
						'wp_loaded',
						function () use ( $order_id, $tracking, $carrier, $args ) {
							wp_easycart_shipments::send_shipped_email( $order_id, $tracking, $carrier, $args );
						}
					);
					return true;
				}
				include_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_orders.php';
			}
			if ( ! function_exists( 'wp_easycart_admin_orders' ) ) {
				return false;
			}
			wp_easycart_admin_orders()->send_customer_shipping_email( (int) $order_id, (string) $tracking, (string) $carrier, null, (array) $args );
			return true;
		}

		// Rows.

		/**
		 * Every column, empty.
		 *
		 * @return array
		 */
		private static function blank() {
			return array(
				'shipment_id'      => 0,
				'order_id'         => 0,
				'sort_order'       => 0,
				'package_id'       => 0,
				'package_name'     => '',
				'length'           => 0,
				'width'            => 0,
				'height'           => 0,
				'weight'           => 0,
				'items'            => array(),
				'status'           => 'packed',
				'is_return'        => 0,
				'carrier'          => '',
				'service'          => '',
				'service_code'     => '',
				'tracking_number'  => '',
				'tracking_url'     => '',
				'tracking_status'  => '',
				'tracking_detail'  => '',
				'tracking_updated' => null,
				'label_url'        => '',
				'label_format'     => '',
				'cost'             => 0,
				'currency'         => '',
				'provider'         => '',
				'provider_ref'     => '',
				'meta'             => array(),
				'created_at'       => null,
				'updated_at'       => null,
				'shipped_at'       => null,
				'delivered_at'     => null,
			);
		}

		/**
		 * A row as callers use it: numbers as numbers, items and meta as arrays.
		 *
		 * @param object $row Database row.
		 * @return object
		 */
		private static function decode( $row ) {
			$row = (object) array_merge( self::blank(), (array) $row );
			foreach ( array( 'shipment_id', 'order_id', 'sort_order', 'package_id', 'is_return' ) as $key ) {
				$row->{$key} = (int) $row->{$key};
			}
			foreach ( array( 'length', 'width', 'height', 'weight', 'cost' ) as $key ) {
				$row->{$key} = (float) $row->{$key};
			}
			if ( is_string( $row->items ) ) {
				$items      = json_decode( $row->items, true );
				$row->items = array();
				foreach ( is_array( $items ) ? $items : array() as $detail_id => $qty ) {
					if ( (int) $qty > 0 ) {
						$row->items[ (int) $detail_id ] = (int) $qty;
					}
				}
			}
			if ( is_string( $row->meta ) ) {
				$meta      = json_decode( $row->meta, true );
				$row->meta = is_array( $meta ) ? $meta : array();
			}
			/* 6.0.2: a package recorded without items or meta ( a return label, an extra label ) has NULL columns, which
			   replaced blank()'s arrays; callers loop over both. */
			if ( ! is_array( $row->items ) ) {
				$row->items = array();
			}
			if ( ! is_array( $row->meta ) ) {
				$row->meta = array();
			}
			/* 6.0.2: the fulfillment partner whose package this is ( '' = the store's ). */
			$row->partner = self::partner( $row );
			return $row;
		}

		/**
		 * The fulfillment partner a package belongs to: its provider, when that is a registered partner or a partner the
		 * order's lines name ( a label extension's provider, e.g. shippo, is not ).
		 *
		 * @since 6.0.2
		 * @param object $row Package ( provider, order_id ).
		 * @return string Partner slug, or '' for the store's package.
		 */
		public static function partner( $row ) {
			$provider = ( is_object( $row ) && isset( $row->provider ) ) ? (string) $row->provider : '';
			if ( '' === $provider || ! class_exists( 'wp_easycart_fulfillment' ) ) {
				return '';
			}
			if ( null !== wp_easycart_fulfillment::provider( $provider ) ) {
				return $provider;
			}
			return in_array( $provider, self::line_providers( isset( $row->order_id ) ? (int) $row->order_id : 0 ), true ) ? $provider : '';
		}

		/**
		 * The partners an order's lines name.
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 * @return string[]
		 */
		private static function line_providers( $order_id ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! wp_easycart_fulfillment::ready() ) {
				return array();
			}
			if ( ! isset( self::$line_providers[ $order_id ] ) ) {
				self::$line_providers[ $order_id ] = array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT fulfillment_provider FROM ec_orderdetail WHERE order_id = %d AND fulfillment_provider <> ''", $order_id ) ) );
			}
			return self::$line_providers[ $order_id ];
		}

		/**
		 * Columns from caller data, cleaned.
		 *
		 * @param array $data Data.
		 * @return array
		 */
		private static function columns( $data ) {
			$out  = array();
			$text = array(
				'package_name'    => 100,
				'carrier'         => 100,
				'service'         => 255,
				'service_code'    => 100,
				'tracking_number' => 255,
				'tracking_status' => 40,
				'tracking_detail' => 255,
				'label_format'    => 20,
				'currency'        => 3,
				'provider'        => 40,
				'provider_ref'    => 100,
			);
			foreach ( $text as $key => $max ) {
				if ( array_key_exists( $key, $data ) ) {
					$value       = trim( sanitize_text_field( (string) $data[ $key ] ) );
					$out[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
				}
			}
			foreach ( array( 'tracking_url', 'label_url' ) as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					$out[ $key ] = esc_url_raw( (string) $data[ $key ] );
				}
			}
			foreach ( array( 'length', 'width', 'height', 'weight', 'cost' ) as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					$out[ $key ] = class_exists( 'wp_easycart_packages' ) ? wp_easycart_packages::number( $data[ $key ] ) : max( 0, (float) $data[ $key ] );
				}
			}
			foreach ( array( 'sort_order', 'package_id' ) as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					$out[ $key ] = max( 0, (int) $data[ $key ] );
				}
			}
			if ( array_key_exists( 'is_return', $data ) ) {
				$out['is_return'] = empty( $data['is_return'] ) ? 0 : 1;
			}
			if ( array_key_exists( 'status', $data ) && in_array( $data['status'], self::STATUSES, true ) ) {
				$out['status'] = $data['status'];
			}
			foreach ( array( 'tracking_updated', 'shipped_at', 'delivered_at' ) as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					$out[ $key ] = ( null === $data[ $key ] || '' === $data[ $key ] ) ? null : gmdate( 'Y-m-d H:i:s', strtotime( (string) $data[ $key ] . ' UTC' ) );
				}
			}
			if ( array_key_exists( 'items', $data ) ) {
				$items = array();
				foreach ( (array) $data['items'] as $detail_id => $qty ) {
					if ( (int) $qty > 0 ) {
						$items[ (int) $detail_id ] = (int) $qty;
					}
				}
				$out['items'] = wp_json_encode( (object) $items );
			}
			if ( array_key_exists( 'meta', $data ) ) {
				$out['meta'] = wp_json_encode( is_array( $data['meta'] ) ? $data['meta'] : array() );
			}
			return $out;
		}

		/**
		 * Items as packages hold them: orderdetail_id => quantity, positive quantities only, sorted by line.
		 *
		 * @since 6.0.2
		 * @param array $items Items.
		 * @return array
		 */
		private static function clean_items( $items ) {
			$out = array();
			foreach ( (array) $items as $detail_id => $qty ) {
				if ( (int) $qty > 0 ) {
					$out[ (int) $detail_id ] = (int) $qty;
				}
			}
			ksort( $out );
			return $out;
		}

		/**
		 * The package a label without a package ID goes on: the first one still waiting for a label or, when the label
		 * says which items it covers, the waiting package holding exactly those ( 6.0.2: a label for other items no longer
		 * takes over the first package's contents; it gets a package of its own ).
		 *
		 * @since 6.0.2
		 * @since 6.0.2 $partner: only packages of that fulfillment partner ( '' = the store's ).
		 * @param int    $order_id Order.
		 * @param array  $items    clean_items() of the label, or empty.
		 * @param string $partner  Whose record it is.
		 * @return object|null
		 */
		private static function waiting_package( $order_id, $items, $partner = '' ) {
			foreach ( self::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
				if ( 'packed' !== $row->status || (string) $row->partner !== (string) $partner ) {
					continue;
				}
				if ( ! $items || self::clean_items( $row->items ) === $items ) {
					return $row;
				}
			}
			return null;
		}

		/**
		 * The waiting package a record without a package ID lands on: with items, the one cover() leaves holding them
		 * ( the record then keeps the package's own items ); without, the first of the right owner.
		 *
		 * @since 6.0.2
		 * @param int    $order_id Order.
		 * @param array  $items    clean_items() of the record.
		 * @param string $partner  Whose record it is.
		 * @param array  $fields   The record's columns ( items is dropped when a package is picked by its contents ).
		 * @return object|null
		 */
		private static function pick_waiting( $order_id, $items, $partner, &$fields ) {
			if ( ! $items ) {
				return self::waiting_package( $order_id, array(), $partner );
			}
			$shipment_id = self::cover( $order_id, $items, $partner );
			if ( $shipment_id <= 0 ) {
				return null;
			}
			unset( $fields['items'] );
			return self::get( $shipment_id );
		}

		/**
		 * Re-plan the waiting packages of one owner so the items shipped form one package, and return it; what is left keeps
		 * waiting ( a package holding more than the shipment is split, one holding part of it gives that part ). With no
		 * items named, everything still waiting ships. Ported from WP EasyCart for ShipStation 2.1.0.
		 *
		 * @since 6.0.2
		 * @param int    $order_id Order.
		 * @param array  $items    orderdetail_id => quantity shipped.
		 * @param string $partner  Fulfillment partner whose packages to use ( '' = the store's ).
		 * @return int Package ID, 0 when none of the items is waiting.
		 */
		public static function cover( $order_id, $items, $partner = '' ) {
			global $wpdb;
			$order_id = (int) $order_id;
			$partner  = (string) $partner;
			$locked   = self::lock( $order_id );
			try {
				$packed = array();
				foreach ( self::for_order( $order_id, array( 'returns' => false ) ) as $row ) {
					if ( 'packed' === $row->status && $partner === (string) $row->partner ) {
						$packed[] = $row;
					}
				}
				if ( ! $packed ) {
					return 0;
				}
				$waiting = array();
				foreach ( $packed as $row ) {
					foreach ( $row->items as $detail_id => $qty ) {
						$waiting[ (int) $detail_id ] = ( isset( $waiting[ (int) $detail_id ] ) ? $waiting[ (int) $detail_id ] : 0 ) + (int) $qty;
					}
				}
				$items   = self::clean_items( $items ? $items : $waiting );
				$covered = array();
				foreach ( $items as $detail_id => $qty ) {
					$take = min( (int) $qty, isset( $waiting[ $detail_id ] ) ? $waiting[ $detail_id ] : 0 );
					if ( $take > 0 ) {
						$covered[ $detail_id ] = $take;
					}
				}
				if ( ! $covered ) {
					return 0;
				}
				/* The shipment is exactly one of the waiting packages: that package, as it is. */
				foreach ( $packed as $row ) {
					if ( self::clean_items( $row->items ) === $covered ) {
						return (int) $row->shipment_id;
					}
				}
				$unit_weight = array();
				foreach ( wp_easycart_packages::lines( $order_id ) as $line ) {
					$unit_weight[ (int) $line->orderdetail_id ] = (float) $line->weight;
				}
				$weigh   = function ( $counts ) use ( $unit_weight ) {
					$total = 0;
					foreach ( $counts as $detail_id => $qty ) {
						$total += ( isset( $unit_weight[ $detail_id ] ) ? $unit_weight[ $detail_id ] : 0 ) * (int) $qty;
					}
					return $total;
				};
				$left    = $covered;
				$sources = array();
				foreach ( $packed as $row ) {
					$keep  = array();
					$taken = array();
					foreach ( $row->items as $detail_id => $qty ) {
						$detail_id = (int) $detail_id;
						$take      = min( (int) $qty, isset( $left[ $detail_id ] ) ? $left[ $detail_id ] : 0 );
						if ( $take > 0 ) {
							$left[ $detail_id ] -= $take;
							$taken[ $detail_id ] = $take;
						}
						if ( (int) $qty - $take > 0 ) {
							$keep[ $detail_id ] = (int) $qty - $take;
						}
					}
					if ( ! $taken ) {
						continue;
					}
					$sources[] = $row;
					if ( $keep ) {
						self::update(
							(int) $row->shipment_id,
							array(
								'items'  => $keep,
								'weight' => max( 0, (float) $row->weight - $weigh( $taken ) ),
							)
						);
					} else {
						$wpdb->delete( 'ec_order_shipment', array( 'shipment_id' => (int) $row->shipment_id ) );
					}
				}
				$source = $sources[0];
				$single = ( 1 === count( $sources ) );
				$box    = ( $single && (int) $source->package_id > 0 ) ? wp_easycart_packages::box( (int) $source->package_id ) : null;
				return self::add(
					$order_id,
					array(
						'sort_order'   => (int) $source->sort_order,
						'package_id'   => $box ? (int) $box->package_id : 0,
						'package_name' => (string) $source->package_name,
						'length'       => $single ? $source->length : 0,
						'width'        => $single ? $source->width : 0,
						'height'       => $single ? $source->height : 0,
						'weight'       => $weigh( $covered ) + ( $box ? (float) $box->box_weight : 0 ),
						'items'        => $covered,
						'provider'     => $partner,
						'status'       => 'packed',
					)
				);
			} finally {
				if ( $locked ) {
					self::unlock( $order_id );
				}
			}
		}

		/**
		 * Lock an order's packages for this request ( MySQL GET_LOCK, 10 seconds ). Held locks are counted, so build()
		 * inside record() does not release record()'s lock.
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 * @return bool Held.
		 */
		private static function lock( $order_id ) {
			global $wpdb;
			$name = 'wpec_shipment_' . (int) $order_id;
			if ( ! empty( self::$locks[ $name ] ) ) {
				++self::$locks[ $name ];
				return true;
			}
			if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 10 )', $name ) ) ) {
				return false;
			}
			self::$locks[ $name ] = 1;
			return true;
		}

		/**
		 * Release lock().
		 *
		 * @since 6.0.2
		 * @param int $order_id Order.
		 */
		private static function unlock( $order_id ) {
			global $wpdb;
			$name = 'wpec_shipment_' . (int) $order_id;
			if ( empty( self::$locks[ $name ] ) ) {
				return;
			}
			--self::$locks[ $name ];
			if ( self::$locks[ $name ] > 0 ) {
				return;
			}
			unset( self::$locks[ $name ] );
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $name ) );
		}
	}

	wp_easycart_shipments::init();

endif;

if ( ! function_exists( 'wp_easycart_order_add_shipment' ) ) {
	/**
	 * Record a label or tracking number on an order: see wp_easycart_shipments::record().
	 *
	 * @since 6.0.2
	 * @param int   $order_id Order.
	 * @param array $args     Package, label and tracking details.
	 * @return int|WP_Error Package ID.
	 */
	function wp_easycart_order_add_shipment( $order_id, $args = array() ) {
		return wp_easycart_shipments::record( $order_id, $args );
	}
}

if ( ! function_exists( 'wp_easycart_order_shipments' ) ) {
	/**
	 * An order's packages: see wp_easycart_shipments::for_order().
	 *
	 * @since 6.0.2
	 * @param int $order_id Order.
	 * @return array
	 */
	function wp_easycart_order_shipments( $order_id ) {
		return wp_easycart_shipments::for_order( $order_id );
	}
}

if ( ! function_exists( 'wp_easycart_order_signature' ) ) {
	/**
	 * The delivery signature an order needs, for label extensions buying its labels ( 6.0.2 ). WP EasyCart asks for none;
	 * an extension answers through the filter, e.g. WP EasyCart for BlueCheck answers 'adult' for age-restricted products
	 * ( the PACT Act asks for an adult signature with ID on vape deliveries ).
	 *
	 * @since 6.0.2
	 * @param int $order_id Order.
	 * @return string '' ( none asked ), 'standard' or 'adult'.
	 */
	function wp_easycart_order_signature( $order_id ) {
		/**
		 * The delivery signature an order needs.
		 *
		 * @since 6.0.2
		 * @param string $signature '' , 'standard' or 'adult'.
		 * @param int    $order_id  Order.
		 */
		$signature = (string) apply_filters( 'wp_easycart_order_signature', '', (int) $order_id );
		return in_array( $signature, array( 'standard', 'adult' ), true ) ? $signature : '';
	}
}
