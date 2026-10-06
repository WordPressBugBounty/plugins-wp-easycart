<?php
/**
 * WP EasyCart — import from Square ( 6.0.3 ).
 *
 * Square items come across through ec_square's own mapper ( insert_option(), insert_category(), insert_product() ), the
 * same code WP EasyCart PRO's hourly sync and "Sync from Square" use, so an import and a later sync never disagree about a
 * product. This adapter adds the Import page's flow on top: what the account holds, the choices, a trial, progress, the
 * report, the map rows ( undo, reruns ) and the product hooks the mapper does not fire.
 *
 * - Connecting Square only to import keeps the store's payment gateway ( the return handler's goto=import ).
 * - Catalog pages are read with this class's own request ( search() ): an error answer pauses the run with Square's reason
 *   instead of reading as "done", and nothing sleeps between retries.
 * - Kitchen categories are left out; category parents follow Square's tree. Services, events and donations come across only
 *   when asked.
 *
 * @package WP_EasyCart
 * @since   6.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_import_square' ) ) :

	/**
	 * Square source.
	 */
	class wp_easycart_import_square extends wp_easycart_import_source {

		const PAGE    = 20;
		const VERSION = '2024-02-22';

		/**
		 * The ec_square client.
		 *
		 * @var object|null
		 */
		private $client = null;

		/**
		 * The answer of detect() for this request.
		 *
		 * @var array|null
		 */
		private $detected = null;

		/**
		 * Source id.
		 *
		 * @return string
		 */
		public function id() {
			return 'square';
		}

		/**
		 * Name.
		 *
		 * @return string
		 */
		public function label() {
			return 'Square';
		}

		/**
		 * One line under the name.
		 *
		 * @return string
		 */
		public function description() {
			return __( 'Items, variations, modifiers, categories and stock from your Square account.', 'wp-easycart' );
		}

		/**
		 * Square's test account is in use.
		 *
		 * @return bool
		 */
		private function sandbox() {
			return (bool) get_option( 'ec_option_square_is_sandbox' );
		}

		/**
		 * The access token for the mode.
		 *
		 * @return string
		 */
		private function token() {
			return (string) get_option( $this->sandbox() ? 'ec_option_square_sandbox_access_token' : 'ec_option_square_access_token' );
		}

		/**
		 * The location whose stock and items are read.
		 *
		 * @return string
		 */
		private function location() {
			return (string) get_option( $this->sandbox() ? 'ec_option_square_sandbox_location_id' : 'ec_option_square_location_id' );
		}

		/**
		 * The Square account and mode the map rows belong to.
		 *
		 * @return string
		 */
		public function site() {
			$merchant = (string) get_option( $this->sandbox() ? 'ec_option_square_sandbox_merchant_id' : 'ec_option_square_merchant_id' );
			return substr( ( $this->sandbox() ? 'sandbox:' : 'live:' ) . ( '' !== $merchant ? $merchant : 'account' ), 0, 40 );
		}

		/**
		 * The ec_square client ( set_client() for tests ).
		 *
		 * @return object|null
		 */
		public function client() {
			if ( null === $this->client && class_exists( 'ec_square' ) ) {
				$this->client = new ec_square();
			}
			return $this->client;
		}

		/**
		 * Use another client.
		 *
		 * @param object $client ec_square or a stand-in.
		 */
		public function set_client( $client ) {
			$this->client = $client;
		}

		/**
		 * The address that connects Square for an import: the store's gateway stays as it is.
		 *
		 * @param bool|null $sandbox Square's test account ( null = the mode in use ).
		 * @return string
		 */
		public static function connect_url( $sandbox = null ) {
			$sandbox = ( null === $sandbox ) ? (bool) get_option( 'ec_option_square_is_sandbox' ) : (bool) $sandbox;
			$state   = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'wp-easycart-square' ) : '';
			return 'https://connect.wpeasycart.com/' . ( $sandbox ? 'square-sandbox' : 'square-v2' ) . '/?url=' . rawurlencode( esc_url_raw( admin_url() ) . '?ec_admin_form_action=handle-square&goto=import' ) . '&state=' . $state;
		}

		// ---- Square ----.

		/**
		 * One page of catalog objects.
		 *
		 * @param string[]    $types  Object types.
		 * @param string|null $cursor Square's cursor.
		 * @param int         $limit  Page size.
		 * @return object|WP_Error
		 */
		public function search( $types, $cursor = null, $limit = self::PAGE ) {
			$body = array(
				'object_types' => array_values( (array) $types ),
				'limit'        => max( 1, min( 1000, (int) $limit ) ),
			);
			if ( $cursor ) {
				$body['cursor'] = (string) $cursor;
			}
			$request  = new WP_Http();
			$response = $request->request(
				( $this->sandbox() ? 'https://connect.squareupsandbox.com' : 'https://connect.squareup.com' ) . '/v2/catalog/search',
				array(
					'method'  => 'POST',
					'headers' => array(
						'Accept'         => 'application/json',
						'Content-Type'   => 'application/json',
						'Authorization'  => 'Bearer ' . $this->token(),
						'Square-Version' => self::VERSION,
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => 20,
				)
			);
			if ( is_wp_error( $response ) ) {
				/* translators: %s: the network error. */
				return new WP_Error( 'wp_easycart_square_http', sprintf( __( 'Square did not answer ( %s ). Press Resume to try again.', 'wp-easycart' ), $response->get_error_message() ) );
			}
			$data = json_decode( (string) ( is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '' ) );
			if ( ! is_object( $data ) ) {
				return new WP_Error( 'wp_easycart_square_http', __( 'Square sent an answer that could not be read. Press Resume to try again.', 'wp-easycart' ) );
			}
			if ( ! empty( $data->errors ) && is_array( $data->errors ) ) {
				return self::square_error( $data->errors[0] );
			}
			return $data;
		}

		/**
		 * Square's error in words the merchant can act on.
		 *
		 * @param object $error Square error.
		 * @return WP_Error
		 */
		private static function square_error( $error ) {
			$code = isset( $error->code ) ? (string) $error->code : '';
			if ( in_array( $code, array( 'UNAUTHORIZED', 'ACCESS_TOKEN_EXPIRED', 'ACCESS_TOKEN_REVOKED', 'FORBIDDEN' ), true ) ) {
				return new WP_Error( 'wp_easycart_square_auth', __( 'Square refused the connection. Connect Square again, then press Resume.', 'wp-easycart' ) );
			}
			if ( 'INSUFFICIENT_SCOPES' === $code ) {
				return new WP_Error( 'wp_easycart_square_scope', __( 'Square needs one more permission. Connect Square again, then press Resume.', 'wp-easycart' ) );
			}
			if ( 'RATE_LIMITED' === $code ) {
				return new WP_Error( 'wp_easycart_square_rate', __( 'Square asked for a short break. Press Resume in a minute or two.', 'wp-easycart' ) );
			}
			$detail = isset( $error->detail ) ? sanitize_text_field( (string) $error->detail ) : $code;
			/* translators: %s: Square's error. */
			return new WP_Error( 'wp_easycart_square', sprintf( __( 'Square answered: %s', 'wp-easycart' ), $detail ) );
		}

		/**
		 * Square counts stock for this connection ( the permission was granted ), kept for an hour.
		 *
		 * @return bool
		 */
		private function stock_permission() {
			$key  = 'wpec_import_square_scope_' . md5( $this->site() . $this->token() );
			$kept = get_transient( $key );
			if ( 'yes' === $kept || 'no' === $kept ) {
				return 'yes' === $kept;
			}
			$client = $this->client();
			$ok     = ( $client && method_exists( $client, 'has_inventory_scope' ) ) ? (bool) $client->has_inventory_scope() : true;
			set_transient( $key, $ok ? 'yes' : 'no', HOUR_IN_SECONDS );
			return $ok;
		}

		/**
		 * How many items, categories and modifier lists the account has ( kept ten minutes ).
		 *
		 * @param bool $fresh Ask again.
		 * @return array|WP_Error
		 */
		private function counts( $fresh ) {
			$key = 'wpec_import_square_counts_' . md5( $this->site() . $this->location() );
			if ( ! $fresh ) {
				$kept = get_transient( $key );
				if ( is_array( $kept ) ) {
					return $kept;
				}
			}
			$client = $this->client();
			$out    = array(
				'items'      => 0,
				'variations' => 0,
				'categories' => 0,
				'modifiers'  => 0,
				'more'       => false,
				'location'   => '',
				'existing'   => 0,
			);
			foreach ( array(
				'ITEM'          => 'items',
				'CATEGORY'      => 'categories',
				'MODIFIER_LIST' => 'modifiers',
			) as $type => $count_key ) {
				$cursor = null;
				$pages  = 0;
				do {
					$page = $this->search( array( $type ), $cursor, 1000 );
					if ( is_wp_error( $page ) ) {
						return $page;
					}
					foreach ( isset( $page->objects ) ? (array) $page->objects : array() as $object ) {
						if ( ! empty( $object->is_deleted ) || ( $client && ! $client->allowed_at_location( $object ) ) ) {
							continue;
						}
						if ( 'CATEGORY' === $type && isset( $object->category_data->category_type ) && 'KITCHEN_CATEGORY' === $object->category_data->category_type ) {
							continue;
						}
						++$out[ $count_key ];
						if ( 'ITEM' === $type && isset( $object->item_data->variations ) ) {
							$out['variations'] += count( (array) $object->item_data->variations );
						}
					}
					$cursor = ! empty( $page->cursor ) ? (string) $page->cursor : null;
				} while ( $cursor && ++$pages < 20 );
				if ( $cursor ) {
					$out['more'] = true;
				}
			}
			if ( $client && method_exists( $client, 'get_locations' ) ) {
				foreach ( (array) $client->get_locations() as $location ) {
					if ( isset( $location->id ) && $location->id === $this->location() ) {
						$out['location'] = isset( $location->name ) ? sanitize_text_field( (string) $location->name ) : '';
					}
				}
			}
			global $wpdb;
			$out['existing'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ec_product WHERE square_id != ''" );
			set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
			return $out;
		}

		/**
		 * What the Square account holds.
		 *
		 * @param bool $fresh Ask Square again.
		 * @return array
		 */
		public function detect( $fresh = false ) {
			if ( null !== $this->detected && ! $fresh ) {
				return $this->detected;
			}
			$connected = class_exists( 'ec_square' ) && ec_square::is_ready();
			if ( ! $connected ) {
				$this->detected = array(
					'available' => false,
					'connected' => false,
					'status'    => ( '' !== $this->token() ) ? __( 'Square is connected but has no location yet. Choose one under Settings › Payments › Square.', 'wp-easycart' ) : __( 'Connect your Square account to bring your items in. Your payment settings stay as they are.', 'wp-easycart' ),
					'counts'    => array(),
					'checks'    => array(),
					'action'    => array(
						'label' => __( 'Connect Square', 'wp-easycart' ),
						'url'   => self::connect_url(),
					),
				);
				return $this->detected;
			}
			$counts = $this->counts( $fresh );
			if ( is_wp_error( $counts ) ) {
				$this->detected = array(
					'available' => false,
					'connected' => true,
					'status'    => $counts->get_error_message(),
					'counts'    => array(),
					'checks'    => array(),
					'action'    => array(
						'label' => __( 'Connect Square again', 'wp-easycart' ),
						'url'   => self::connect_url(),
					),
				);
				return $this->detected;
			}
			$checks = array();
			if ( 'square' === get_option( 'ec_option_payment_process_method' ) ) {
				$checks[] = array( 'ok', __( 'Square takes your card payments', 'wp-easycart' ), __( 'Web sales are sent to Square, so its stock counts stay right.', 'wp-easycart' ) );
			} else {
				$checks[] = array( 'info', __( 'Your payment gateway stays as it is', 'wp-easycart' ), __( 'Importing from Square does not change how your store takes payments.', 'wp-easycart' ) );
			}
			$stock = $this->stock_permission();
			if ( ! $stock ) {
				$checks[] = array( 'warn', __( 'Stock needs one more permission', 'wp-easycart' ), __( 'Connect Square again to let your store read stock counts. Items still come across without them.', 'wp-easycart' ) );
			}
			$square_currency = strtoupper( (string) get_option( 'ec_option_square_currency', '' ) );
			$store_currency  = strtoupper( (string) get_option( 'ec_option_base_currency', 'USD' ) );
			if ( '' !== $square_currency && $square_currency !== $store_currency ) {
				/* translators: 1: Square's currency code, 2: the store's currency code. */
				$checks[] = array( 'warn', __( 'Different currency', 'wp-easycart' ), sprintf( __( 'Square prices are in %1$s and your store sells in %2$s. Prices are copied as they are.', 'wp-easycart' ), $square_currency, $store_currency ) );
			}
			if ( $counts['existing'] ) {
				/* translators: %d: number of products. */
				$checks[] = array( 'info', __( 'Imported before', 'wp-easycart' ), sprintf( _n( '%d product in your store came from Square. Choose below whether to update it.', '%d products in your store came from Square. Choose below whether to update them.', $counts['existing'], 'wp-easycart' ), $counts['existing'] ) );
			}
			if ( get_option( 'ec_option_square_auto_product_sync' ) ) {
				$checks[] = array( 'info', __( 'Hourly sync is on', 'wp-easycart' ), __( 'WP EasyCart PRO keeps these products in step with Square every hour after the import.', 'wp-easycart' ) );
			}
			$status = '' !== $counts['location']
				/* translators: %s: Square location name. */
				? sprintf( __( 'Connected · %s', 'wp-easycart' ), $counts['location'] )
				: __( 'Connected', 'wp-easycart' );
			$this->detected = array(
				'available' => ( $counts['items'] > 0 ),
				'connected' => true,
				'status'    => $counts['items'] > 0 ? $status : __( 'Connected, but Square has no items for this location.', 'wp-easycart' ),
				'counts'    => $counts,
				'checks'    => $checks,
				'stock'     => $stock,
			);
			return $this->detected;
		}

		/**
		 * What can come across.
		 *
		 * @param array $detect From detect().
		 * @return array
		 */
		public function entities( $detect ) {
			$c     = isset( $detect['counts'] ) ? $detect['counts'] : array();
			$items = isset( $c['items'] ) ? (int) $c['items'] : 0;
			$next  = __( 'Coming in the next update', 'wp-easycart' );
			return array(
				'products'  => array(
					'label'     => __( 'Items', 'wp-easycart' ),
					/* translators: 1: number of items, 2: number of variations. */
					'desc'      => sprintf( __( '%1$s items and %2$s variations, with their modifiers, categories, images and stock', 'wp-easycart' ), number_format_i18n( $items ) . ( ! empty( $c['more'] ) ? '+' : '' ), number_format_i18n( isset( $c['variations'] ) ? (int) $c['variations'] : 0 ) ),
					'count'     => $items,
					'available' => $items > 0,
					'default'   => true,
				),
				'customers' => array(
					'label'     => __( 'Customers', 'wp-easycart' ),
					'desc'      => __( 'Your Square customer directory', 'wp-easycart' ),
					'count'     => 0,
					'available' => false,
					'note'      => $next,
				),
				'orders'    => array(
					'label'     => __( 'Orders', 'wp-easycart' ),
					'desc'      => __( 'Sales history', 'wp-easycart' ),
					'count'     => 0,
					'available' => false,
					'note'      => $next,
				),
			);
		}

		/**
		 * The merchant's choices.
		 *
		 * @param array $detect From detect().
		 * @return array
		 */
		public function fields( $detect ) {
			$stock = ! empty( $detect['stock'] );
			return array(
				'existing' => array(
					'type'    => 'select',
					'label'   => __( 'Products already in your store', 'wp-easycart' ),
					'desc'    => __( 'Items imported from Square before.', 'wp-easycart' ),
					'options' => array(
						'update' => __( 'Update them from Square', 'wp-easycart' ),
						'skip'   => __( 'Leave them as they are', 'wp-easycart' ),
					),
					'default' => 'update',
				),
				'stock'    => array(
					'type'    => 'select',
					'label'   => __( 'Stock', 'wp-easycart' ),
					'desc'    => $stock ? __( 'The counts at your Square location.', 'wp-easycart' ) : __( 'Connect Square again to read stock counts.', 'wp-easycart' ),
					'options' => array(
						'import' => __( 'Copy Square\'s stock counts', 'wp-easycart' ),
						'skip'   => __( 'Leave stock as it is', 'wp-easycart' ),
					),
					'default' => $stock ? 'import' : 'skip',
				),
				'types'    => array(
					'type'    => 'select',
					'label'   => __( 'Services, events and donations', 'wp-easycart' ),
					'desc'    => __( 'Square items that are not goods or food.', 'wp-easycart' ),
					'options' => array(
						'goods' => __( 'Leave them out', 'wp-easycart' ),
						'all'   => __( 'Bring them in too', 'wp-easycart' ),
					),
					'default' => 'goods',
				),
			);
		}

		/**
		 * The phases.
		 *
		 * @param array $settings Settings.
		 * @param bool  $trial    Trial.
		 * @return string[]
		 */
		public function phases( $settings, $trial ) {
			return $trial ? array( 'categories', 'items' ) : array( 'modifiers', 'categories', 'items' );
		}

		/**
		 * A phase's name.
		 *
		 * @param string $phase Phase.
		 * @return string
		 */
		public function phase_label( $phase ) {
			$labels = array(
				'modifiers'  => __( 'Modifiers', 'wp-easycart' ),
				'categories' => __( 'Categories', 'wp-easycart' ),
				'items'      => __( 'Items', 'wp-easycart' ),
			);
			return isset( $labels[ $phase ] ) ? $labels[ $phase ] : parent::phase_label( $phase );
		}

		/**
		 * How many items a phase has.
		 *
		 * @param string $phase    Phase.
		 * @param array  $settings Settings.
		 * @param bool   $trial    Trial.
		 * @return int
		 */
		public function phase_total( $phase, $settings, $trial ) {
			$detect = $this->detect();
			$c      = isset( $detect['counts'] ) ? $detect['counts'] : array();
			$keys   = array(
				'modifiers'  => 'modifiers',
				'categories' => 'categories',
				'items'      => 'items',
			);
			$total  = isset( $keys[ $phase ], $c[ $keys[ $phase ] ] ) ? (int) $c[ $keys[ $phase ] ] : 0;
			return ( $trial && 'items' === $phase ) ? min( wp_easycart_import::TRIAL, $total ) : $total;
		}

		/**
		 * Work on a phase.
		 *
		 * @param string $phase    Phase.
		 * @param mixed  $cursor   Cursor.
		 * @param array  $job      Job.
		 * @param float  $deadline Stop time.
		 * @return array
		 */
		public function run( $phase, $cursor, &$job, $deadline ) {
			$types = array(
				'modifiers'  => 'MODIFIER_LIST',
				'categories' => 'CATEGORY',
				'items'      => 'ITEM',
			);
			if ( ! isset( $types[ $phase ] ) || ! $this->client() ) {
				return array(
					'cursor' => null,
					'done'   => true,
					'count'  => 0,
				);
			}
			$cursor = is_array( $cursor ) ? $cursor : array(
				'cursor' => null,
				'offset' => 0,
			);
			$count  = 0;
			$trial  = ! empty( $job['trial'] );
			while ( 0 === $count || $this->time_left( $deadline ) ) {
				if ( $trial && 'items' === $phase && $this->trial_count( $job ) >= wp_easycart_import::TRIAL ) {
					return array(
						'cursor' => null,
						'done'   => true,
						'count'  => $count,
					);
				}
				$page = $this->search( array( $types[ $phase ] ), $cursor['cursor'], 'items' === $phase ? self::PAGE : 200 );
				if ( is_wp_error( $page ) ) {
					return array(
						'cursor' => $cursor,
						'done'   => false,
						'count'  => $count,
						'error'  => $page->get_error_message(),
					);
				}
				$objects = isset( $page->objects ) ? array_values( (array) $page->objects ) : array();
				$total   = count( $objects );
				for ( $i = (int) $cursor['offset']; $i < $total; $i++ ) {
					if ( $count > 0 && ! $this->time_left( $deadline ) ) {
						$cursor['offset'] = $i;
						return array(
							'cursor' => $cursor,
							'done'   => false,
							'count'  => $count,
						);
					}
					if ( $trial && 'items' === $phase && $this->trial_count( $job ) >= wp_easycart_import::TRIAL ) {
						break;
					}
					try {
						if ( 'modifiers' === $phase ) {
							$this->import_modifier_list( $objects[ $i ], $job );
						} elseif ( 'categories' === $phase ) {
							$this->import_category( $objects[ $i ], $job );
						} else {
							$this->import_item( $objects[ $i ], $job );
						}
					} catch ( \Throwable $e ) {
						$this->record( $job, 'categories' === $phase ? 'category' : ( 'modifiers' === $phase ? 'option' : 'product' ), isset( $objects[ $i ]->id ) ? $objects[ $i ]->id : 'unknown', 'failed', array( 'message' => $e->getMessage() ) );
					}
					++$count;
				}
				if ( empty( $page->cursor ) ) {
					if ( 'categories' === $phase ) {
						$this->link_category_parents( $job );
					}
					return array(
						'cursor' => null,
						'done'   => true,
						'count'  => $count,
					);
				}
				$cursor = array(
					'cursor' => (string) $page->cursor,
					'offset' => 0,
				);
				if ( 0 === count( $objects ) ) {
					++$count; /* an empty page with a cursor: move on */
				}
			}
			return array(
				'cursor' => $cursor,
				'done'   => false,
				'count'  => $count,
			);
		}

		/**
		 * Update items already imported.
		 *
		 * @param array $job Job.
		 * @return bool
		 */
		private function updating( $job ) {
			return 'skip' !== $this->field( $job['settings'], 'existing', 'update' );
		}

		/**
		 * A modifier list as a shared option set of modifiers.
		 *
		 * @param object $object MODIFIER_LIST.
		 * @param array  $job    Job.
		 */
		private function import_modifier_list( $object, &$job ) {
			global $wpdb;
			$id    = isset( $object->id ) ? (string) $object->id : '';
			$label = isset( $object->modifier_list_data->name ) ? (string) $object->modifier_list_data->name : $id;
			if ( '' === $id || ! empty( $object->is_deleted ) || ! $this->client()->allowed_at_location( $object ) ) {
				$this->record( $job, 'option', $id, 'skipped', array( 'label' => $label ) );
				return;
			}
			$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT option_id FROM ec_option WHERE square_id = %s', $id ) );
			$update = $this->updating( $job );
			$this->client()->insert_option( $object, $update, ! $update );
			$after = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT option_id FROM ec_option WHERE square_id = %s', $id ) );
			if ( ! $after ) {
				$this->record(
					$job,
					'option',
					$id,
					'failed',
					array(
						'label'   => $label,
						'message' => __( 'The modifier list could not be saved.', 'wp-easycart' ),
					)
				);
				return;
			}
			$this->record(
				$job,
				'option',
				$id,
				$before ? ( $update ? 'updated' : 'skipped' ) : 'imported',
				array(
					'target_id' => $after,
					'label'     => $label,
				)
			);
		}

		/**
		 * A category ( kitchen categories are left out ).
		 *
		 * @param object $object CATEGORY.
		 * @param array  $job    Job.
		 */
		private function import_category( $object, &$job ) {
			global $wpdb;
			$id    = isset( $object->id ) ? (string) $object->id : '';
			$label = isset( $object->category_data->name ) ? (string) $object->category_data->name : $id;
			if ( '' === $id || ! empty( $object->is_deleted ) || ! $this->client()->allowed_at_location( $object ) ) {
				$this->record( $job, 'category', $id, 'skipped', array( 'label' => $label ) );
				return;
			}
			if ( isset( $object->category_data->category_type ) && 'KITCHEN_CATEGORY' === $object->category_data->category_type ) {
				$this->record(
					$job,
					'category',
					$id,
					'skipped',
					array(
						'label'   => $label,
						'message' => __( 'A kitchen category: left out.', 'wp-easycart' ),
					)
				);
				return;
			}
			$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE square_id = %s', $id ) );
			$update = $this->updating( $job );
			$this->client()->insert_category( $object, $update, ! $update );
			$after = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE square_id = %s', $id ) );
			if ( ! $after ) {
				$this->record(
					$job,
					'category',
					$id,
					'failed',
					array(
						'label'   => $label,
						'message' => __( 'The category could not be saved.', 'wp-easycart' ),
					)
				);
				return;
			}
			if ( isset( $object->category_data->parent_category->id ) && '' !== (string) $object->category_data->parent_category->id ) {
				$job['state']['parents'][ $id ] = (string) $object->category_data->parent_category->id;
			}
			$this->record(
				$job,
				'category',
				$id,
				$before ? ( $update ? 'updated' : 'skipped' ) : 'imported',
				array(
					'target_id' => $after,
					'label'     => $label,
				)
			);
		}

		/**
		 * Categories sit under their Square parent ( every category arrived at the top before 6.0.3 ).
		 *
		 * @param array $job Job.
		 */
		private function link_category_parents( &$job ) {
			global $wpdb;
			foreach ( isset( $job['state']['parents'] ) ? (array) $job['state']['parents'] : array() as $child => $parent ) {
				$parent_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE square_id = %s', $parent ) );
				$child_id  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE square_id = %s', $child ) );
				if ( $parent_id && $child_id && $parent_id !== $child_id ) {
					$wpdb->query( $wpdb->prepare( 'UPDATE ec_category SET parent_id = %d WHERE category_id = %d', $parent_id, $child_id ) );
				}
			}
			unset( $job['state']['parents'] );
			wp_cache_delete( 'wpeasycart-all-categories', 'wpeasycart-categories' );
			wp_cache_delete( 'wpeasycart-all-categories' );
		}

		/**
		 * An item as a product, through ec_square's mapper.
		 *
		 * @param object $object ITEM.
		 * @param array  $job    Job.
		 */
		private function import_item( $object, &$job ) {
			global $wpdb;
			$id    = isset( $object->id ) ? (string) $object->id : '';
			$label = isset( $object->item_data->name ) ? (string) $object->item_data->name : $id;
			if ( '' === $id || ! empty( $object->is_deleted ) ) {
				return;
			}
			if ( ! $this->client()->allowed_at_location( $object ) ) {
				$this->record(
					$job,
					'product',
					$id,
					'skipped',
					array(
						'label'   => $label,
						'message' => __( 'Not sold at your Square location: left out.', 'wp-easycart' ),
					)
				);
				return;
			}
			$type = isset( $object->item_data->product_type ) ? (string) $object->item_data->product_type : ( isset( $object->product_type ) ? (string) $object->product_type : 'REGULAR' );
			if ( 'goods' === $this->field( $job['settings'], 'types', 'goods' ) && ! in_array( $type, array( 'REGULAR', 'FOOD_AND_BEV', 'GIFT_CARD', '' ), true ) ) {
				/* translators: %s: Square's item type. */
				$this->record(
					$job,
					'product',
					$id,
					'skipped',
					array(
						'label'   => $label,
						/* translators: %s: Square's item type ( appointments service, event, donation … ). */
						'message' => sprintf( __( 'A %s item: left out.', 'wp-easycart' ), strtolower( str_replace( '_', ' ', $type ) ) ),
					)
				);
				return;
			}
			$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE square_id = %s LIMIT 1', $id ) );
			$update = $this->updating( $job );
			if ( $before && ! $update ) {
				$this->record(
					$job,
					'product',
					$id,
					'skipped',
					array(
						'target_id' => $before,
						'label'     => $label,
						'created'   => false,
					)
				);
				return;
			}
			$detect = $this->detect();
			$stock  = ( 'import' === $this->field( $job['settings'], 'stock', 'skip' ) ) && ! empty( $detect['stock'] );
			$this->client()->insert_product( $object, true, $stock, false );
			$after = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE square_id = %s LIMIT 1', $id ) );
			if ( ! $after ) {
				$this->record(
					$job,
					'product',
					$id,
					'failed',
					array(
						'label'   => $label,
						'message' => __( 'The item could not be saved.', 'wp-easycart' ),
					)
				);
				return;
			}
			$notes = array();
			foreach ( isset( $object->item_data->variations ) ? (array) $object->item_data->variations : array() as $variation ) {
				if ( isset( $variation->item_variation_data->pricing_type ) && 'VARIABLE_PRICING' === $variation->item_variation_data->pricing_type ) {
					$notes['price'] = __( 'A variation has a price set at the register: it came in at 0, so set its price.', 'wp-easycart' );
				}
				if ( isset( $variation->item_variation_data->item_option_values ) && count( (array) $variation->item_variation_data->item_option_values ) > 5 ) {
					$notes['options'] = __( 'A product has at most five choices: the sixth item option was left out.', 'wp-easycart' );
				}
			}
			$model = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT model_number FROM ec_product WHERE product_id = %d', $after ) );
			if ( $before ) {
				wp_easycart_product_writer::updated( $after, $model );
			} else {
				/* ec_square writes the product itself: tell listeners as the product writer does ( Google feed, Zapier ). */
				do_action( 'wpeasycart_product_added', $after, $model );
			}
			$this->record(
				$job,
				'product',
				$id,
				$before ? 'updated' : 'imported',
				array(
					'target_id' => $after,
					'label'     => $label,
					'message'   => implode( ' ', $notes ),
				)
			);
		}
	}

endif;
