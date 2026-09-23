<?php
/**
 * WP EasyCart — order documents: the order receipt email, the shipping confirmation email and the packing slip.
 *
 * One place that knows what each customer-facing order document can show ( its fields ), the named sets of those
 * switches a store keeps ( profiles ), and how a document is rendered for one order. Settings › Documents edits the
 * profiles; the receipt and shipped email senders, the admin print and the PRO PDF ask this class what to show.
 *
 * Storage: ec_option_document_profiles ( not autoloaded ):
 *   array( <type> => array( 'default' => <profile id>, 'profiles' => array( <id> => array( 'name' => '', 'fields' => array( <key> => 0|1 ),
 *   'branding' => array( ... ) ) ) ) )
 * 'branding' is the profile's own logo and footer image ( branding(); PRO ). Without it a profile uses the store's, from
 * Settings › Email › Sender. 'options' are the profile's choices that are not switches ( options(), profile_options():
 * the Invoice PDF's heading; the Standard profile's is ec_option_pdf_document_title ).
 *
 * The Invoice PDF ( 6.0.1, type 'invoice', template ec_invoice_pdf.php ) is a PRO document: WP EasyCart PRO builds the PDF
 * and attaches it; without PRO its Standard profile shows read only, with its preview. It replaced the printable receipt
 * as the PDF; Print receipt in My Account and on the order screen still prints ec_account_print_receipt.php.
 *
 * The Standard profile of the packing slip and of the order receipt also reads and writes the options those documents
 * used before 6.0.1 ( ec_option_packing_slip_show_*, ec_option_show_image_on_receipt, ec_option_show_email_on_receipt ),
 * so a template copied into the data folder keeps following the switches. A field with 'legacy' but no 'sync' only
 * takes its first value from that option ( the shipped email used to share the receipt's image and email switches ).
 *
 * WP EasyCart PRO turns on more than one profile per document ( filter wp_easycart_documents_pro ). Without it every
 * document uses its Standard profile, whatever was chosen before.
 *
 * Filters: wp_easycart_document_types, wp_easycart_document_fields, wp_easycart_document_builtins,
 * wp_easycart_document_profile, wp_easycart_documents_pro, wp_easycart_document_render_<type>.
 * Actions: wp_easycart_document_profile_saved, wp_easycart_document_profile_deleted, wp_easycart_document_branding_saved.
 *
 * @since 6.0.1
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_documents' ) ) :

	/**
	 * Document types, fields, profiles and rendering.
	 */
	final class wp_easycart_documents {

		const OPTION = 'ec_option_document_profiles';

		/** @var array|null Stored profiles, read once per request. */
		private static $stored = null;

		/**
		 * Does this store get more than one profile per document ( WP EasyCart PRO, licensed )?
		 *
		 * @return bool
		 */
		public static function pro_enabled() {
			return (bool) apply_filters( 'wp_easycart_documents_pro', false );
		}

		/**
		 * The document types, in the order Settings › Documents shows them.
		 *
		 * @return array type => array( label, desc, outputs )
		 */
		public static function types() {
			$types = array(
				'receipt'      => array(
					'label'   => __( 'Order receipt', 'wp-easycart' ),
					'desc'    => __( 'The email a customer gets when they pay, and the copy sent to your store.', 'wp-easycart' ),
					'outputs' => array( 'email' ),
				),
				'shipping'     => array(
					'label'   => __( 'Shipping confirmation', 'wp-easycart' ),
					'desc'    => __( 'The email a customer gets when their order is marked shipped.', 'wp-easycart' ),
					'outputs' => array( 'email' ),
				),
				'packing_slip' => array(
					'label'   => __( 'Packing slip', 'wp-easycart' ),
					'desc'    => __( 'The page that goes in the box. Printed from the orders list or an order.', 'wp-easycart' ),
					'outputs' => array( 'print', 'pdf' ),
				),
				/* 6.0.1: the PDF attached to order emails ( WP EasyCart PRO builds it; it was Settings › Email › PDF receipts ). */
				'invoice'      => array(
					'label'   => __( 'Invoice PDF', 'wp-easycart' ),
					'desc'    => __( 'The invoice or receipt PDF attached to order emails and downloaded from an order.', 'wp-easycart' ),
					'outputs' => array( 'pdf' ),
					'pro'     => true,
				),
			);
			return (array) apply_filters( 'wp_easycart_document_types', $types );
		}

		/**
		 * Is a whole document WP EasyCart PRO ( every profile, Standard included )?
		 *
		 * @since 6.0.1
		 * @param string $type Document type.
		 * @return bool
		 */
		public static function is_pro_type( $type ) {
			$types = self::types();
			return ! empty( $types[ $type ]['pro'] );
		}

		/**
		 * @param string $type Document type.
		 * @return bool
		 */
		public static function is_type( $type ) {
			$types = self::types();
			return is_string( $type ) && isset( $types[ $type ] );
		}

		/**
		 * Field groups, in display order.
		 *
		 * @return array
		 */
		public static function groups() {
			return array(
				'branding' => __( 'Branding', 'wp-easycart' ),
				'order'    => __( 'Order', 'wp-easycart' ),
				'people'   => __( 'Customer', 'wp-easycart' ),
				'items'    => __( 'Items', 'wp-easycart' ),
				'prices'   => __( 'Prices', 'wp-easycart' ),
				'text'     => __( 'Notes', 'wp-easycart' ),
			);
		}

		/**
		 * What a document can show.
		 *
		 * Each field: label, desc ( optional ), group, default ( 0|1 for a new store ), legacy ( option read for the
		 * Standard profile ), sync ( write that option back when Standard is saved ), parent ( a field that must also be on ),
		 * master ( true: its children are hidden in the editor while it is off ), edit ( array( page, option ): the setting the
		 * switch prints, linked from the editor ).
		 *
		 * @param string $type Document type.
		 * @return array key => field
		 */
		public static function fields( $type ) {
			$email = array(
				/* 6.0.1: the images at the top and foot of the email ( which image and its size: branding(), per profile ). */
				'logo'         => array( 'group' => 'branding', 'label' => __( 'Logo', 'wp-easycart' ), 'desc' => __( 'The store’s logo, or this profile’s own from Logo & footer.', 'wp-easycart' ), 'default' => 1 ),
				'footer_image' => array( 'group' => 'branding', 'label' => __( 'Footer image', 'wp-easycart' ), 'desc' => __( 'The store’s signature image, or this profile’s own from Logo & footer.', 'wp-easycart' ), 'default' => 1 ),
				/* 6.0.1: the address from Settings › Email › Sender ( ec_option_store_address ); 'edit' links the switch to it. */
				'store_address' => array( 'group' => 'branding', 'label' => __( 'Store address', 'wp-easycart' ), 'desc' => __( 'Your store’s address, in the footer.', 'wp-easycart' ), 'default' => 0, 'edit' => array( 'page' => 'email-setup', 'option' => 'ec_option_store_address' ) ),
				'billing'     => array( 'group' => 'people', 'label' => __( 'Billing address', 'wp-easycart' ), 'default' => 1 ),
				'shipping'    => array( 'group' => 'people', 'label' => __( 'Shipping address', 'wp-easycart' ), 'default' => 1 ),
				'email'       => array( 'group' => 'people', 'label' => __( 'Customer email address', 'wp-easycart' ), 'default' => 0, 'legacy' => 'ec_option_show_email_on_receipt' ),
				'image'       => array( 'group' => 'items', 'label' => __( 'Product images', 'wp-easycart' ), 'default' => 1, 'legacy' => 'ec_option_show_image_on_receipt' ),
				'sku'         => array( 'group' => 'items', 'label' => __( 'SKU / model number', 'wp-easycart' ), 'default' => 1 ),
				'options'     => array( 'group' => 'items', 'label' => __( 'Product options', 'wp-easycart' ), 'default' => 1 ),
				'prices'      => array( 'group' => 'prices', 'label' => __( 'Show prices', 'wp-easycart' ), 'desc' => __( 'Unit prices, line totals and the order totals.', 'wp-easycart' ), 'default' => 1, 'master' => true ),
				'order_notes' => array( 'group' => 'text', 'label' => __( 'Customer’s order notes', 'wp-easycart' ), 'default' => 1 ),
			);
			$fields = array();
			if ( 'receipt' === $type ) {
				$fields                    = $email;
				/* 6.0.1: the shipping method line ( with the coupon and the customer's email, when those show ). */
				$fields['shipping_method'] = array( 'group' => 'order', 'label' => __( 'Shipping method', 'wp-easycart' ), 'default' => 1 );
				$fields['email']['sync']   = true;
				$fields['email']['desc']   = __( 'Also used on refund emails.', 'wp-easycart' );
				$fields['image']['sync']   = true;
				$fields['image']['desc']   = __( 'Also used on invoice, gift card and refund emails and the printed receipt.', 'wp-easycart' );
			} elseif ( 'shipping' === $type ) {
				$fields = $email;
			} elseif ( 'packing_slip' === $type ) {
				$ps     = 'ec_option_packing_slip_show_';
				$fields = array(
					'logo'           => array( 'group' => 'branding', 'label' => __( 'Logo', 'wp-easycart' ), 'desc' => __( 'The store’s logo, or this profile’s own from Logo & footer.', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'logo', 'sync' => true ),
					'footer_image'   => array( 'group' => 'branding', 'label' => __( 'Footer image', 'wp-easycart' ), 'desc' => __( 'The store’s signature image, or this profile’s own from Logo & footer.', 'wp-easycart' ), 'default' => 0 ),
					'store_address' => array( 'group' => 'branding', 'label' => __( 'Store address', 'wp-easycart' ), 'desc' => __( 'Your store’s address, in the footer.', 'wp-easycart' ), 'default' => 0, 'edit' => array( 'page' => 'email-setup', 'option' => 'ec_option_store_address' ) ),
					'order_number'   => array( 'group' => 'order', 'label' => __( 'Order number', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'order_id', 'sync' => true ),
					'order_date'     => array( 'group' => 'order', 'label' => __( 'Order date', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'order_date', 'sync' => true ),
					'tracking'       => array( 'group' => 'order', 'label' => __( 'Shipping method, carrier and tracking', 'wp-easycart' ), 'default' => 1 ),
					'billing'        => array( 'group' => 'people', 'label' => __( 'Billing address', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'billing', 'sync' => true ),
					'shipping'       => array( 'group' => 'people', 'label' => __( 'Shipping address', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'shipping', 'sync' => true ),
					'phone'          => array( 'group' => 'people', 'label' => __( 'Phone numbers', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'phone', 'sync' => true ),
					'email'          => array( 'group' => 'people', 'label' => __( 'Customer email address', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'email', 'sync' => true ),
					'image'          => array( 'group' => 'items', 'label' => __( 'Product images', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'product_image', 'sync' => true ),
					'title'          => array( 'group' => 'items', 'label' => __( 'Product titles', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'product_title', 'sync' => true ),
					'sku'            => array( 'group' => 'items', 'label' => __( 'SKU / model number', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'model_number', 'sync' => true ),
					'options'        => array( 'group' => 'items', 'label' => __( 'Product options', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'options', 'sync' => true ),
					'prices'         => array( 'group' => 'prices', 'label' => __( 'Show prices', 'wp-easycart' ), 'desc' => __( 'Unit prices, line totals and the totals you pick below.', 'wp-easycart' ), 'default' => 0, 'master' => true, 'legacy' => $ps . 'pricing', 'sync' => true ),
					'subtotal'       => array( 'group' => 'prices', 'label' => __( 'Subtotal', 'wp-easycart' ), 'default' => 1, 'parent' => 'prices', 'legacy' => $ps . 'subtotal', 'sync' => true ),
					'discounts'      => array( 'group' => 'prices', 'label' => __( 'Discounts', 'wp-easycart' ), 'default' => 1, 'parent' => 'prices', 'legacy' => $ps . 'discounttotal', 'sync' => true ),
					'shipping_total' => array( 'group' => 'prices', 'label' => __( 'Shipping total', 'wp-easycart' ), 'default' => 1, 'parent' => 'prices', 'legacy' => $ps . 'shippingtotal', 'sync' => true ),
					'tax'            => array( 'group' => 'prices', 'label' => __( 'Tax or VAT', 'wp-easycart' ), 'default' => 1, 'parent' => 'prices', 'legacy' => $ps . 'taxtotal', 'sync' => true ),
					'tip'            => array( 'group' => 'prices', 'label' => __( 'Tip', 'wp-easycart' ), 'default' => 1, 'parent' => 'prices', 'legacy' => $ps . 'tiptotal', 'sync' => true ),
					'grand_total'    => array( 'group' => 'prices', 'label' => __( 'Grand total', 'wp-easycart' ), 'default' => 1, 'parent' => 'prices', 'legacy' => $ps . 'grandtotal', 'sync' => true ),
					'order_notes'    => array( 'group' => 'text', 'label' => __( 'Customer’s order notes', 'wp-easycart' ), 'default' => 1, 'legacy' => $ps . 'order_notes', 'sync' => true ),
				);
			} elseif ( 'invoice' === $type ) {
				/* 6.0.1: every default is what the PDF showed before it became a document, so nothing changes until a switch does. */
				$fields = array(
					'seller'        => array( 'group' => 'branding', 'label' => __( 'Business details', 'wp-easycart' ), 'desc' => __( 'Your registered name, address and VAT number, from the PDF settings above.', 'wp-easycart' ), 'default' => 1 ),
					'logo'          => array( 'group' => 'branding', 'label' => __( 'Logo', 'wp-easycart' ), 'desc' => __( 'The store’s logo, or this profile’s own from Logo & footer.', 'wp-easycart' ), 'default' => 1 ),
					'footer_image'  => array( 'group' => 'branding', 'label' => __( 'Footer image', 'wp-easycart' ), 'desc' => __( 'The store’s signature image, or this profile’s own from Logo & footer.', 'wp-easycart' ), 'default' => 0 ),
					'store_address' => array( 'group' => 'branding', 'label' => __( 'Store address', 'wp-easycart' ), 'desc' => __( 'Your store’s address, in the footer.', 'wp-easycart' ), 'default' => 0, 'edit' => array( 'page' => 'email-setup', 'option' => 'ec_option_store_address' ) ),
					'heading'       => array( 'group' => 'order', 'label' => __( 'Heading, order number and date', 'wp-easycart' ), 'desc' => __( 'Top right: this profile’s heading ( Invoice or Receipt ), the order number and the date.', 'wp-easycart' ), 'default' => 1 ),
					'intro'         => array( 'group' => 'order', 'label' => __( 'Greeting and thank-you lines', 'wp-easycart' ), 'desc' => __( 'The receipt email’s lines above the addresses and below the totals.', 'wp-easycart' ), 'default' => 1 ),
					'shipping'      => array( 'group' => 'people', 'label' => __( 'Shipping address', 'wp-easycart' ), 'default' => 1 ),
					'billing'       => array( 'group' => 'people', 'label' => __( 'Billing address', 'wp-easycart' ), 'default' => 1 ),
					'vat_number'    => array( 'group' => 'people', 'label' => __( 'Customer VAT number', 'wp-easycart' ), 'desc' => __( 'When the customer gave one at checkout.', 'wp-easycart' ), 'default' => 1 ),
					'email'         => array( 'group' => 'people', 'label' => __( 'Customer email address', 'wp-easycart' ), 'default' => 0 ),
					'image'         => array( 'group' => 'items', 'label' => __( 'Product images', 'wp-easycart' ), 'default' => 1, 'legacy' => 'ec_option_show_image_on_receipt' ),
					'sku'           => array( 'group' => 'items', 'label' => __( 'SKU / model number', 'wp-easycart' ), 'default' => 1 ),
					'options'       => array( 'group' => 'items', 'label' => __( 'Product options', 'wp-easycart' ), 'default' => 1 ),
					'prices'        => array( 'group' => 'prices', 'label' => __( 'Show prices', 'wp-easycart' ), 'desc' => __( 'Unit prices, line totals and the order totals.', 'wp-easycart' ), 'default' => 1, 'master' => true ),
					'order_notes'   => array( 'group' => 'text', 'label' => __( 'Customer’s order notes', 'wp-easycart' ), 'default' => 1 ),
				);
			}
			return (array) apply_filters( 'wp_easycart_document_fields', $fields, $type );
		}

		/**
		 * Profiles every store has. Standard is the only one without PRO.
		 *
		 * @param string $type Document type.
		 * @return array id => array( name, desc, fields ( changes from the field defaults ) )
		 */
		public static function builtins( $type ) {
			$builtins = array(
				'standard' => array(
					'name' => __( 'Standard', 'wp-easycart' ),
					'desc' => __( 'Used unless you choose another.', 'wp-easycart' ),
				),
			);
			if ( 'receipt' === $type ) {
				$builtins['minimal'] = array(
					'name'   => __( 'Minimal', 'wp-easycart' ),
					'desc'   => __( 'No images or SKUs.', 'wp-easycart' ),
					'fields' => array( 'image' => 0, 'sku' => 0 ),
				);
			} elseif ( 'shipping' === $type ) {
				$builtins['gift'] = array(
					'name'   => __( 'Gift', 'wp-easycart' ),
					'desc'   => __( 'No prices, no billing address.', 'wp-easycart' ),
					'fields' => array( 'prices' => 0, 'billing' => 0, 'email' => 0 ),
				);
			} elseif ( 'packing_slip' === $type ) {
				$builtins['prices']   = array(
					'name'   => __( 'With prices', 'wp-easycart' ),
					'desc'   => __( 'Prices and every total.', 'wp-easycart' ),
					'fields' => array( 'prices' => 1, 'subtotal' => 1, 'discounts' => 1, 'shipping_total' => 1, 'tax' => 1, 'tip' => 1, 'grand_total' => 1 ),
				);
				$builtins['gift']     = array(
					'name'   => __( 'Gift', 'wp-easycart' ),
					'desc'   => __( 'No prices, billing address, contact details or order notes.', 'wp-easycart' ),
					'fields' => array( 'prices' => 0, 'billing' => 0, 'phone' => 0, 'email' => 0, 'sku' => 0, 'tracking' => 0, 'order_notes' => 0 ),
				);
				$builtins['dropship'] = array(
					'name'   => __( 'Drop-ship', 'wp-easycart' ),
					'desc'   => __( 'Your branding and SKUs, no prices or images.', 'wp-easycart' ),
					'fields' => array( 'prices' => 0, 'image' => 0, 'billing' => 0, 'phone' => 0, 'email' => 0, 'sku' => 1 ),
				);
			} elseif ( 'invoice' === $type ) {
				$builtins['receipt']     = array(
					'name'    => __( 'Receipt', 'wp-easycart' ),
					'desc'    => __( 'Headed Receipt, for orders that are already paid.', 'wp-easycart' ),
					'options' => array( 'heading' => 'receipt' ),
				);
				$builtins['bookkeeping'] = array(
					'name'    => __( 'Bookkeeping', 'wp-easycart' ),
					'desc'    => __( 'An invoice without the greeting, thank-you lines or product images.', 'wp-easycart' ),
					'fields'  => array( 'intro' => 0, 'image' => 0 ),
					'options' => array( 'heading' => 'invoice' ),
				);
			}
			return (array) apply_filters( 'wp_easycart_document_builtins', $builtins, $type );
		}

		/**
		 * Choices a profile makes that are not on/off switches ( the Invoice PDF's heading ). Each: label, desc, choices
		 * ( value => label ), default, legacy ( an option the Standard profile reads and writes ).
		 *
		 * @since 6.0.1
		 * @param string $type Document type.
		 * @return array key => option
		 */
		public static function options( $type ) {
			$options = array();
			if ( 'invoice' === $type ) {
				$options['heading'] = array(
					'label'   => __( 'Heading', 'wp-easycart' ),
					'desc'    => __( 'The document name printed at the top of the PDF.', 'wp-easycart' ),
					'choices' => array(
						'invoice' => __( 'Invoice', 'wp-easycart' ),
						'receipt' => __( 'Receipt', 'wp-easycart' ),
					),
					'default' => 'invoice',
					'legacy'  => 'ec_option_pdf_document_title',
				);
			}
			return (array) apply_filters( 'wp_easycart_document_options', $options, $type );
		}

		/**
		 * A profile's choices ( options() ), each resolved to one of its values.
		 *
		 * @since 6.0.1
		 * @param string $type    Document type.
		 * @param string $profile Profile id ( '' or unknown: the default ).
		 * @return array key => value
		 */
		public static function profile_options( $type, $profile = '' ) {
			$defs = self::options( $type );
			if ( ! $defs ) {
				return array();
			}
			$id   = sanitize_key( (string) $profile );
			$list = self::profile_list( $type );
			if ( '' === $id || ! isset( $list[ $id ] ) ) {
				$id = self::default_id( $type );
			}
			$builtins = self::builtins( $type );
			$stored   = self::stored_profile( $type, $id );
			$values   = array();
			foreach ( $defs as $key => $def ) {
				$value = $def['default'];
				if ( isset( $builtins[ $id ]['options'][ $key ] ) ) {
					$value = $builtins[ $id ]['options'][ $key ];
				}
				if ( $stored && isset( $stored['options'][ $key ] ) ) {
					$value = $stored['options'][ $key ];
				}
				if ( 'standard' === $id && ! empty( $def['legacy'] ) ) {
					$value = get_option( $def['legacy'], $value );
				}
				$values[ $key ] = isset( $def['choices'][ (string) $value ] ) ? (string) $value : $def['default'];
			}
			return $values;
		}

		/**
		 * Save a profile's choices. Standard also writes their legacy options.
		 *
		 * @since 6.0.1
		 * @param string $type   Document type.
		 * @param string $id     Profile.
		 * @param array  $values key => value ( unknown keys and values are ignored ).
		 * @return array|WP_Error The profile's choices now.
		 */
		public static function save_profile_options( $type, $id, $values ) {
			$id   = sanitize_key( (string) $id );
			$list = self::profile_list( $type );
			if ( ! isset( $list[ $id ] ) ) {
				return new WP_Error( 'profile', __( 'That profile no longer exists.', 'wp-easycart' ) );
			}
			if ( ( 'standard' !== $id || self::is_pro_type( $type ) ) && ! self::pro_enabled() ) {
				return new WP_Error( 'pro', __( 'This needs WP EasyCart PRO.', 'wp-easycart' ) );
			}
			$defs    = self::options( $type );
			$current = self::profile_options( $type, $id );
			foreach ( (array) $values as $key => $value ) {
				if ( is_scalar( $value ) && isset( $defs[ $key ]['choices'][ (string) $value ] ) ) {
					$current[ $key ] = (string) $value;
				}
			}
			$stored           = self::stored();
			$entry            = isset( $stored[ $type ]['profiles'][ $id ] ) && is_array( $stored[ $type ]['profiles'][ $id ] ) ? $stored[ $type ]['profiles'][ $id ] : array();
			$entry['options'] = $current;

			$stored[ $type ]['profiles'][ $id ] = $entry;
			self::put( $stored );
			if ( 'standard' === $id ) {
				foreach ( $defs as $key => $def ) {
					if ( ! empty( $def['legacy'] ) ) {
						update_option( $def['legacy'], $current[ $key ] );
					}
				}
			}
			return $current;
		}

		/* ------------------------------------------------------------------ */
		/* Storage                                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * @return array
		 */
		private static function stored() {
			if ( null === self::$stored ) {
				$stored       = get_option( self::OPTION, array() );
				self::$stored = is_array( $stored ) ? $stored : array();
			}
			return self::$stored;
		}

		/**
		 * @param array $stored Whole option.
		 */
		private static function put( $stored ) {
			self::$stored = $stored;
			if ( false === get_option( self::OPTION, false ) ) {
				add_option( self::OPTION, $stored, '', 'no' );
			} else {
				update_option( self::OPTION, $stored, false );
			}
		}

		/**
		 * @param string $type Document type.
		 * @param string $id   Profile.
		 * @return array|null Stored entry.
		 */
		private static function stored_profile( $type, $id ) {
			$stored = self::stored();
			return isset( $stored[ $type ]['profiles'][ $id ] ) && is_array( $stored[ $type ]['profiles'][ $id ] ) ? $stored[ $type ]['profiles'][ $id ] : null;
		}

		/* ------------------------------------------------------------------ */
		/* Profiles                                                            */
		/* ------------------------------------------------------------------ */

		/**
		 * Every profile of a type: built-ins first, then the store's own.
		 *
		 * @param string $type Document type.
		 * @return array id => array( id, name, desc, builtin ( bool ), pro ( bool, needs PRO ) )
		 */
		public static function profile_list( $type ) {
			$out = array();
			foreach ( self::builtins( $type ) as $id => $builtin ) {
				$stored     = self::stored_profile( $type, $id );
				$out[ $id ] = array(
					'id'      => $id,
					'name'    => $builtin['name'],
					'desc'    => isset( $builtin['desc'] ) ? $builtin['desc'] : '',
					'builtin' => true,
					'pro'     => 'standard' !== $id,
					'edited'  => ( null !== $stored && ( ! empty( $stored['fields'] ) || ! empty( $stored['branding'] ) ) ),
				);
			}
			$stored = self::stored();
			if ( isset( $stored[ $type ]['profiles'] ) && is_array( $stored[ $type ]['profiles'] ) ) {
				foreach ( $stored[ $type ]['profiles'] as $id => $profile ) {
					if ( isset( $out[ $id ] ) || ! is_array( $profile ) || empty( $profile['name'] ) ) {
						continue;
					}
					$out[ $id ] = array(
						'id'      => (string) $id,
						'name'    => (string) $profile['name'],
						'desc'    => '',
						'builtin' => false,
						'pro'     => true,
						'edited'  => true,
					);
				}
			}
			return $out;
		}

		/**
		 * The profile a document uses when nothing else picks one.
		 *
		 * @param string $type Document type.
		 * @return string
		 */
		public static function default_id( $type ) {
			$stored = self::stored();
			$id     = isset( $stored[ $type ]['default'] ) ? sanitize_key( $stored[ $type ]['default'] ) : 'standard';
			$list   = self::profile_list( $type );
			if ( ! isset( $list[ $id ] ) || ( 'standard' !== $id && ! self::pro_enabled() ) ) {
				return 'standard';
			}
			return $id;
		}

		/**
		 * One profile, with every field resolved to 0 or 1.
		 *
		 * @param string $type Document type.
		 * @param string $id   Profile, '' for the default.
		 * @return array|false array( id, type, name, builtin, fields ) or false for an unknown type.
		 */
		public static function profile( $type, $id = '' ) {
			if ( ! self::is_type( $type ) ) {
				return false;
			}
			$defs = self::fields( $type );
			$id   = sanitize_key( (string) $id );
			$list = self::profile_list( $type );
			/* '' or a profile that no longer exists ( deleted after a grid cell or a saved choice named it ): the default. */
			if ( '' === $id || ! isset( $list[ $id ] ) ) {
				$id = self::default_id( $type );
			}
			if ( ! isset( $list[ $id ] ) || ( 'standard' !== $id && ! self::pro_enabled() ) ) {
				$id = 'standard';
			}
			$builtins = self::builtins( $type );
			$stored   = self::stored_profile( $type, $id );
			$values   = array();
			foreach ( $defs as $key => $def ) {
				$values[ $key ] = ! empty( $def['default'] ) ? 1 : 0;
			}
			if ( isset( $builtins[ $id ]['fields'] ) && is_array( $builtins[ $id ]['fields'] ) ) {
				foreach ( $builtins[ $id ]['fields'] as $key => $value ) {
					if ( isset( $values[ $key ] ) ) {
						$values[ $key ] = $value ? 1 : 0;
					}
				}
			}
			if ( $stored && isset( $stored['fields'] ) && is_array( $stored['fields'] ) ) {
				foreach ( $stored['fields'] as $key => $value ) {
					if ( isset( $values[ $key ] ) ) {
						$values[ $key ] = $value ? 1 : 0;
					}
				}
			}
			if ( 'standard' === $id ) {
				foreach ( $defs as $key => $def ) {
					if ( empty( $def['legacy'] ) ) {
						continue;
					}
					$is_stored = ( $stored && isset( $stored['fields'][ $key ] ) );
					if ( ! empty( $def['sync'] ) || ! $is_stored ) {
						$values[ $key ] = get_option( $def['legacy'], $values[ $key ] ) ? 1 : 0;
					}
				}
			}
			return array(
				'id'      => $id,
				'type'    => $type,
				'name'    => $list[ $id ]['name'],
				'builtin' => ! empty( $list[ $id ]['builtin'] ),
				'fields'  => (array) apply_filters( 'wp_easycart_document_profile', $values, $type, $id ),
			);
		}

		/**
		 * Save a profile's switches ( and its name, for the store's own ). Standard also writes its legacy options.
		 *
		 * @param string      $type   Document type.
		 * @param string      $id     Profile.
		 * @param array       $fields key => 0|1 ( unknown keys are ignored, missing keys keep their value ).
		 * @param string|null $name   New name ( store's own profiles only ).
		 * @return true|WP_Error
		 */
		public static function save_profile( $type, $id, $fields, $name = null ) {
			if ( ! self::is_type( $type ) ) {
				return new WP_Error( 'type', __( 'Unknown document.', 'wp-easycart' ) );
			}
			$id   = sanitize_key( (string) $id );
			$list = self::profile_list( $type );
			if ( ! isset( $list[ $id ] ) ) {
				return new WP_Error( 'profile', __( 'That profile no longer exists.', 'wp-easycart' ) );
			}
			if ( ( 'standard' !== $id || self::is_pro_type( $type ) ) && ! self::pro_enabled() ) {
				return new WP_Error( 'pro', __( 'More than one profile per document needs WP EasyCart PRO.', 'wp-easycart' ) );
			}
			$defs    = self::fields( $type );
			$current = self::profile( $type, $id );
			$values  = $current['fields'];
			foreach ( (array) $fields as $key => $value ) {
				if ( isset( $defs[ $key ] ) ) {
					$values[ $key ] = ( $value && 'false' !== $value && '0' !== (string) $value ) ? 1 : 0;
				}
			}
			$stored = self::stored();
			if ( ! isset( $stored[ $type ] ) || ! is_array( $stored[ $type ] ) ) {
				$stored[ $type ] = array();
			}
			if ( ! isset( $stored[ $type ]['profiles'] ) || ! is_array( $stored[ $type ]['profiles'] ) ) {
				$stored[ $type ]['profiles'] = array();
			}
			$entry           = isset( $stored[ $type ]['profiles'][ $id ] ) && is_array( $stored[ $type ]['profiles'][ $id ] ) ? $stored[ $type ]['profiles'][ $id ] : array();
			$entry['fields'] = $values;
			if ( empty( $list[ $id ]['builtin'] ) && null !== $name ) {
				$name = trim( sanitize_text_field( (string) $name ) );
				if ( '' !== $name ) {
					$entry['name'] = mb_substr( $name, 0, 60 );
				}
			}
			$stored[ $type ]['profiles'][ $id ] = $entry;
			self::put( $stored );
			if ( 'standard' === $id ) {
				foreach ( $defs as $key => $def ) {
					if ( ! empty( $def['legacy'] ) && ! empty( $def['sync'] ) ) {
						update_option( $def['legacy'], $values[ $key ] );
					}
				}
			}
			do_action( 'wp_easycart_document_profile_saved', $type, $id, $values );
			return true;
		}

		/**
		 * Put a built-in profile back to how it ships ( Standard: the store defaults ), with the store's logo and footer image.
		 *
		 * @param string $type Document type.
		 * @param string $id   Built-in profile.
		 * @return true|WP_Error
		 */
		public static function reset_profile( $type, $id ) {
			$builtins = self::builtins( $type );
			$id       = sanitize_key( (string) $id );
			if ( ! isset( $builtins[ $id ] ) ) {
				return new WP_Error( 'profile', __( 'Only a built-in profile can be reset.', 'wp-easycart' ) );
			}
			if ( self::is_pro_type( $type ) && ! self::pro_enabled() ) {
				return new WP_Error( 'pro', __( 'This needs WP EasyCart PRO.', 'wp-easycart' ) );
			}
			$stored = self::stored();
			unset( $stored[ $type ]['profiles'][ $id ] );
			self::put( $stored );
			if ( 'standard' === $id ) {
				foreach ( self::fields( $type ) as $def ) {
					if ( ! empty( $def['legacy'] ) && ! empty( $def['sync'] ) ) {
						update_option( $def['legacy'], ! empty( $def['default'] ) ? 1 : 0 );
					}
				}
				foreach ( self::options( $type ) as $def ) {
					if ( ! empty( $def['legacy'] ) ) {
						update_option( $def['legacy'], $def['default'] );
					}
				}
			}
			return true;
		}

		/**
		 * A new profile, copied from another ( PRO ).
		 *
		 * @param string $type Document type.
		 * @param string $name Its name.
		 * @param string $from Profile to copy.
		 * @return string|WP_Error New profile id.
		 */
		public static function create_profile( $type, $name, $from = 'standard' ) {
			if ( ! self::is_type( $type ) ) {
				return new WP_Error( 'type', __( 'Unknown document.', 'wp-easycart' ) );
			}
			if ( ! self::pro_enabled() ) {
				return new WP_Error( 'pro', __( 'More than one profile per document needs WP EasyCart PRO.', 'wp-easycart' ) );
			}
			$name = trim( sanitize_text_field( (string) $name ) );
			if ( '' === $name ) {
				return new WP_Error( 'name', __( 'Give the profile a name.', 'wp-easycart' ) );
			}
			$source = self::profile( $type, $from );
			$id     = 'p' . strtolower( wp_generate_password( 8, false, false ) );
			$stored = self::stored();
			$stored[ $type ]['profiles'][ $id ] = array(
				'name'     => mb_substr( $name, 0, 60 ),
				'fields'   => $source['fields'],
				/* The copy keeps the source's own logo and footer image, and its choices, too. */
				'branding' => self::branding( $type, $source['id'] ),
				'options'  => self::profile_options( $type, $source['id'] ),
			);
			self::put( $stored );
			return $id;
		}

		/**
		 * Delete one of the store's own profiles. A document that used it as its default goes back to Standard.
		 *
		 * @param string $type Document type.
		 * @param string $id   Profile.
		 * @return true|WP_Error
		 */
		public static function delete_profile( $type, $id ) {
			$id       = sanitize_key( (string) $id );
			$builtins = self::builtins( $type );
			if ( isset( $builtins[ $id ] ) ) {
				return new WP_Error( 'builtin', __( 'Built-in profiles can be reset but not deleted.', 'wp-easycart' ) );
			}
			$stored = self::stored();
			unset( $stored[ $type ]['profiles'][ $id ] );
			if ( isset( $stored[ $type ]['default'] ) && $stored[ $type ]['default'] === $id ) {
				$stored[ $type ]['default'] = 'standard';
			}
			self::put( $stored );
			do_action( 'wp_easycart_document_profile_deleted', $type, $id );
			return true;
		}

		/**
		 * Choose the profile a document uses by default ( PRO, for anything but Standard ).
		 *
		 * @param string $type Document type.
		 * @param string $id   Profile.
		 * @return true|WP_Error
		 */
		public static function set_default( $type, $id ) {
			$id   = sanitize_key( (string) $id );
			$list = self::profile_list( $type );
			if ( ! isset( $list[ $id ] ) ) {
				return new WP_Error( 'profile', __( 'That profile no longer exists.', 'wp-easycart' ) );
			}
			if ( ( 'standard' !== $id || self::is_pro_type( $type ) ) && ! self::pro_enabled() ) {
				return new WP_Error( 'pro', __( 'More than one profile per document needs WP EasyCart PRO.', 'wp-easycart' ) );
			}
			$stored                     = self::stored();
			$stored[ $type ]['default'] = $id;
			self::put( $stored );
			return true;
		}

		/**
		 * Is a field on, for a fields array from profile()? Unknown keys read as on, so a template asking about a field
		 * this release does not define keeps showing it. A field whose parent is off is off.
		 *
		 * @param array  $fields From profile() ( or null: everything on ).
		 * @param string $key    Field.
		 * @param string $type   Document type, to follow parents.
		 * @return bool
		 */
		public static function show( $fields, $key, $type = '' ) {
			if ( ! is_array( $fields ) || ! array_key_exists( $key, $fields ) ) {
				return true;
			}
			if ( empty( $fields[ $key ] ) ) {
				return false;
			}
			if ( '' !== $type ) {
				$defs = self::fields( $type );
				if ( ! empty( $defs[ $key ]['parent'] ) ) {
					return self::show( $fields, $defs[ $key ]['parent'], $type );
				}
			}
			return true;
		}

		/**
		 * Resolve fields for one render: a profile, then per-send changes on top.
		 *
		 * @param string $type      Document type.
		 * @param string $profile   Profile id ( '' = default ).
		 * @param array  $overrides key => 0|1.
		 * @return array key => 0|1, plus '_profile' ( the profile used, for its logo and footer image: open_args() ).
		 */
		public static function resolve( $type, $profile = '', $overrides = array() ) {
			$resolved = self::profile( $type, $profile );
			$fields   = $resolved ? $resolved['fields'] : array();
			foreach ( (array) $overrides as $key => $value ) {
				if ( array_key_exists( $key, $fields ) && '_' !== substr( (string) $key, 0, 1 ) ) {
					$fields[ $key ] = ( $value && 'false' !== $value && '0' !== (string) $value ) ? 1 : 0;
				}
			}
			if ( $resolved ) {
				$fields['_profile'] = $resolved['id'];
			}
			return $fields;
		}

		/* ------------------------------------------------------------------ */
		/* Images ( a profile's own logo and footer image )                    */
		/* ------------------------------------------------------------------ */

		/**
		 * Which logo and footer image a profile uses, and at what size. "store" means the image, size or position set on
		 * Settings › Email › Sender, which every profile uses until it is given its own. Stored with the profile
		 * ( ec_option_document_profiles[ type ]['profiles'][ id ]['branding'] ), so a copy takes them along and a reset
		 * puts the store's back. Only applied with WP EasyCart PRO ( open_args(), close_args() ).
		 *
		 * @since 6.0.1
		 * @param string $type    Document type.
		 * @param string $profile Profile id ( '' or unknown: the default ).
		 * @return array logo_source, logo_url, logo_size, logo_width ( % ), logo_height ( px ), logo_align,
		 *               footer_source, footer_url, footer_size, footer_width ( % ), footer_height ( px ).
		 */
		public static function branding( $type, $profile = '' ) {
			$id   = sanitize_key( (string) $profile );
			$list = self::profile_list( $type );
			if ( '' === $id || ! isset( $list[ $id ] ) ) {
				$id = self::default_id( $type );
			}
			$stored = self::stored_profile( $type, $id );
			return self::clean_branding( ( $stored && isset( $stored['branding'] ) ) ? $stored['branding'] : array() );
		}

		/**
		 * Does a profile use anything but the store's images?
		 *
		 * @since 6.0.1
		 * @param array $b From branding().
		 * @return bool
		 */
		public static function is_custom_branding( $b ) {
			return ( 'custom' === $b['logo_source'] && '' !== $b['logo_url'] ) || 'custom' === $b['logo_size'] || 'store' !== $b['logo_align']
				|| ( 'custom' === $b['footer_source'] && '' !== $b['footer_url'] ) || 'custom' === $b['footer_size'];
		}

		/**
		 * Every branding key, from raw values, in range.
		 *
		 * @param array $row Raw values.
		 * @return array
		 */
		private static function clean_branding( $row ) {
			$row = is_array( $row ) ? $row : array();
			$get = function ( $key, $default ) use ( $row ) {
				return ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ) ? $row[ $key ] : $default;
			};
			$pick = function ( $value, $allowed, $default ) {
				return in_array( (string) $value, $allowed, true ) ? (string) $value : $default;
			};
			return array(
				'logo_source'   => $pick( $get( 'logo_source', 'store' ), array( 'store', 'custom' ), 'store' ),
				'logo_url'      => esc_url_raw( (string) $get( 'logo_url', '' ) ),
				'logo_size'     => $pick( $get( 'logo_size', 'store' ), array( 'store', 'custom' ), 'store' ),
				'logo_width'    => max( 5, min( 100, (int) $get( 'logo_width', 40 ) ) ),
				'logo_height'   => max( 0, min( 600, (int) $get( 'logo_height', 80 ) ) ),
				'logo_align'    => $pick( $get( 'logo_align', 'store' ), array( 'store', 'left', 'center', 'right' ), 'store' ),
				'footer_source' => $pick( $get( 'footer_source', 'store' ), array( 'store', 'custom' ), 'store' ),
				'footer_url'    => esc_url_raw( (string) $get( 'footer_url', '' ) ),
				'footer_size'   => $pick( $get( 'footer_size', 'store' ), array( 'store', 'custom' ), 'store' ),
				'footer_width'  => max( 5, min( 100, (int) $get( 'footer_width', 100 ) ) ),
				'footer_height' => max( 0, min( 600, (int) $get( 'footer_height', 0 ) ) ),
			);
		}

		/**
		 * Save a profile's own logo and footer image ( PRO ).
		 *
		 * @since 6.0.1
		 * @param string $type    Document type.
		 * @param string $profile Profile id.
		 * @param array  $values  Any of branding()'s keys; missing keys keep their value.
		 * @return array|WP_Error What is stored now.
		 */
		public static function save_branding( $type, $profile, $values ) {
			if ( ! self::is_type( $type ) ) {
				return new WP_Error( 'type', __( 'Unknown document.', 'wp-easycart' ) );
			}
			if ( ! self::pro_enabled() ) {
				return new WP_Error( 'pro', __( 'A profile’s own logo and footer image need WP EasyCart PRO.', 'wp-easycart' ) );
			}
			$id   = sanitize_key( (string) $profile );
			$list = self::profile_list( $type );
			if ( '' === $id || ! isset( $list[ $id ] ) ) {
				return new WP_Error( 'profile', __( 'That profile no longer exists.', 'wp-easycart' ) );
			}
			$clean  = self::clean_branding( array_merge( self::branding( $type, $id ), is_array( $values ) ? $values : array() ) );
			$stored = self::stored();
			if ( ! isset( $stored[ $type ] ) || ! is_array( $stored[ $type ] ) ) {
				$stored[ $type ] = array();
			}
			if ( ! isset( $stored[ $type ]['profiles'] ) || ! is_array( $stored[ $type ]['profiles'] ) ) {
				$stored[ $type ]['profiles'] = array();
			}
			$entry             = isset( $stored[ $type ]['profiles'][ $id ] ) && is_array( $stored[ $type ]['profiles'][ $id ] ) ? $stored[ $type ]['profiles'][ $id ] : array();
			$entry['branding'] = $clean;

			$stored[ $type ]['profiles'][ $id ] = $entry;
			self::put( $stored );
			do_action( 'wp_easycart_document_branding_saved', $type, $id, $clean );
			return $clean;
		}

		/**
		 * The profile a resolved fields array was built from ( resolve() marks it ), or the default.
		 *
		 * @param string $type   Document type.
		 * @param array  $fields Resolved switches.
		 * @return string
		 */
		private static function fields_profile( $type, $fields ) {
			return ( is_array( $fields ) && isset( $fields['_profile'] ) && is_string( $fields['_profile'] ) ) ? $fields['_profile'] : self::default_id( $type );
		}

		/**
		 * wp_easycart_email_design::open() arguments for a document: the logo switch, and the profile's own logo, size and
		 * position.
		 *
		 * @since 6.0.1
		 * @param string $type   Document type.
		 * @param array  $fields Resolved switches ( from resolve(), which names the profile ).
		 * @param array  $args   The template's own arguments.
		 * @return array
		 */
		public static function open_args( $type, $fields, $args = array() ) {
			$args = is_array( $args ) ? $args : array();
			if ( ! self::show( $fields, 'logo', $type ) ) {
				$args['header'] = false;
				return $args;
			}
			if ( self::pro_enabled() ) {
				$b = self::branding( $type, self::fields_profile( $type, $fields ) );
				if ( 'custom' === $b['logo_source'] && '' !== $b['logo_url'] ) {
					$args['logo_url'] = $b['logo_url'];
				}
				if ( 'custom' === $b['logo_size'] ) {
					$args['logo_max_w'] = $b['logo_width'];
					$args['logo_max_h'] = $b['logo_height'];
				}
				if ( 'store' !== $b['logo_align'] ) {
					$args['logo_align'] = $b['logo_align'];
				}
			}
			return $args;
		}

		/**
		 * wp_easycart_email_design::close() arguments for a document: the store address and footer image switches, and the
		 * profile's own footer image and size.
		 *
		 * @since 6.0.1
		 * @param string $type   Document type.
		 * @param array  $fields Resolved switches ( from resolve(), which names the profile ).
		 * @param array  $args   The template's own arguments.
		 * @return array
		 */
		public static function close_args( $type, $fields, $args = array() ) {
			$args = is_array( $args ) ? $args : array();
			/* 6.0.1: the store address ( Settings › Email › Sender ), only when the profile asks for it. */
			if ( is_array( $fields ) && isset( $fields['store_address'] ) && self::show( $fields, 'store_address', $type ) ) {
				$args['store_address'] = trim( (string) get_option( 'ec_option_store_address', '' ) );
			}
			if ( ! self::show( $fields, 'footer_image', $type ) ) {
				$args['signature_image'] = '';
				return $args;
			}
			if ( self::pro_enabled() ) {
				$b = self::branding( $type, self::fields_profile( $type, $fields ) );
				if ( 'custom' === $b['footer_source'] && '' !== $b['footer_url'] ) {
					$args['signature_image'] = $b['footer_url'];
				}
				if ( 'custom' === $b['footer_size'] ) {
					$args['signature_image_w'] = $b['footer_width'];
					$args['signature_image_h'] = $b['footer_height'];
				}
			}
			return $args;
		}

		/* ------------------------------------------------------------------ */
		/* Rendering                                                           */
		/* ------------------------------------------------------------------ */

		/**
		 * The template file for a document: the data folder's copy first, then the plugin's.
		 *
		 * @param string $file File name in design/layout/<layout>/.
		 * @return string
		 */
		public static function locate( $file ) {
			$override = self::override_path( $file );
			return ( '' !== $override ) ? $override : EC_PLUGIN_DIRECTORY . '/design/layout/' . get_option( 'ec_option_latest_layout' ) . '/' . $file;
		}

		/**
		 * The data folder's copy of a template, or ''.
		 *
		 * @param string $file File name.
		 * @return string
		 */
		public static function override_path( $file ) {
			$path = EC_PLUGIN_DATA_DIRECTORY . '/design/layout/' . get_option( 'ec_option_base_layout' ) . '/' . $file;
			return file_exists( $path ) ? $path : '';
		}

		/**
		 * The template file each type renders from.
		 *
		 * @param string $type Document type.
		 * @return string
		 */
		public static function template_file( $type ) {
			$files = array(
				'receipt'      => 'ec_cart_email_receipt.php',
				'shipping'     => 'ec_shipping_email.php',
				'packing_slip' => 'ec_admin_packaging_slip.php',
				'invoice'      => 'ec_invoice_pdf.php',
			);
			return isset( $files[ $type ] ) ? $files[ $type ] : '';
		}

		/**
		 * Render a document for one order.
		 *
		 * @param string $type     Document type.
		 * @param int    $order_id Order.
		 * @param array  $args     profile ( id ), overrides ( key => 0|1 ), items ( orderdetail ids; empty = all ),
		 *                         output ( print | pdf | email | preview ), is_admin ( store copy of an email ),
		 *                         @since 6.0.1 options ( key => value: the profile's choices for this render only ).
		 * @return string HTML, '' when the order does not exist.
		 */
		public static function render( $type, $order_id, $args = array() ) {
			$args = array_merge(
				array(
					'profile'   => '',
					'overrides' => array(),
					'items'     => array(),
					'output'    => 'print',
					'is_admin'  => false,
				),
				(array) $args
			);
			$fields = self::resolve( $type, $args['profile'], $args['overrides'] );
			if ( 'packing_slip' === $type || 'invoice' === $type ) {
				$doc = new wp_easycart_document( $type, $order_id, $fields, $args );
				return $doc->order ? $doc->capture( self::locate( self::template_file( $type ) ) ) : '';
			}
			/* The receipt and shipped emails render through their senders ( ec_orderdisplay, wp_easycart_admin_orders ). */
			return (string) apply_filters( 'wp_easycart_document_render_' . $type, '', (int) $order_id, $fields, $args );
		}

		/**
		 * A phrase from the language editor's Order Documents group, or the fallback when the store's language file
		 * does not carry it yet ( new phrases reach every installed language on the next language update ).
		 *
		 * @param string $key      Phrase key.
		 * @param string $fallback English text, used as is ( escaped here ).
		 * @return string Safe HTML.
		 */
		public static function text( $key, $fallback ) {
			$text = function_exists( 'wp_easycart_language' ) ? wp_easycart_language()->get_text( 'documents', $key ) : '';
			return ( null === $text || '' === trim( (string) $text ) ) ? esc_html( $fallback ) : wp_kses_post( $text );
		}

		/**
		 * The newest real order, for previews ( the newest demo order when the store has only those ).
		 *
		 * @return int 0 when the store has no orders at all.
		 */
		public static function preview_order_id() {
			global $wpdb;
			static $id = null;
			if ( null === $id ) {
				$id = (int) $wpdb->get_var( 'SELECT order_id FROM ec_order WHERE is_demo_item = 0 ORDER BY order_id DESC LIMIT 1' );
				if ( ! $id ) {
					/* 6.0.1: a store whose only orders came with the demo data previews with the newest of those, not order 0. */
					$id = (int) $wpdb->get_var( 'SELECT order_id FROM ec_order ORDER BY order_id DESC LIMIT 1' );
				}
			}
			return $id;
		}
	}

endif;

if ( ! class_exists( 'wp_easycart_document' ) ) :

	/**
	 * One document being rendered for one order: the order, its lines ( limited to the chosen items ), the resolved
	 * switches, and small helpers the templates use. Templates get it as $document.
	 *
	 * @since 6.0.1
	 */
	class wp_easycart_document {

		/** @var string */
		public $type;

		/** @var int */
		public $order_id;

		/** @var object|null ec_order row, with billing_country_name / shipping_country_name. */
		public $order = null;

		/** @var array ec_orderdetail rows on this document. */
		public $lines = array();

		/** @var array ec_orderdetail rows left off this document ( chosen items ), "to follow". */
		public $held_back = array();

		/** @var array Resolved switches. */
		public $fields = array();

		/** @var string print | pdf | email | preview */
		public $output = 'print';

		/** @var array Render arguments. */
		public $args = array();

		/**
		 * @param string $type     Document type.
		 * @param int    $order_id Order.
		 * @param array  $fields   Resolved switches.
		 * @param array  $args     Render arguments ( see wp_easycart_documents::render() ).
		 */
		public function __construct( $type, $order_id, $fields, $args = array() ) {
			global $wpdb;
			$this->type     = (string) $type;
			$this->order_id = (int) $order_id;
			$this->fields   = (array) $fields;
			$this->args     = (array) $args;
			$this->output   = isset( $args['output'] ) ? (string) $args['output'] : 'print';
			$this->order    = $wpdb->get_row( $wpdb->prepare( 'SELECT ec_order.*, billing_country.name_cnt AS billing_country_name, shipping_country.name_cnt AS shipping_country_name FROM ec_order LEFT JOIN ec_country AS billing_country ON billing_country.iso2_cnt = ec_order.billing_country LEFT JOIN ec_country AS shipping_country ON shipping_country.iso2_cnt = ec_order.shipping_country WHERE order_id = %d', $this->order_id ) );
			if ( ! $this->order ) {
				return;
			}
			$lines = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_orderdetail WHERE order_id = %d ORDER BY orderdetail_id ASC', $this->order_id ) );
			$pick  = array_filter( array_map( 'intval', isset( $args['items'] ) ? (array) $args['items'] : array() ) );
			foreach ( $lines as $line ) {
				if ( $pick && ! in_array( (int) $line->orderdetail_id, $pick, true ) ) {
					$this->held_back[] = $line;
				} else {
					$this->lines[] = $line;
				}
			}
		}

		/**
		 * Is a switch on ( and every switch it depends on )?
		 *
		 * @param string $key Field.
		 * @return bool
		 */
		public function show( $key ) {
			return wp_easycart_documents::show( $this->fields, $key, $this->type );
		}

		/**
		 * One of the profile's choices ( wp_easycart_documents::options(), e.g. the Invoice PDF's heading ).
		 *
		 * @since 6.0.1
		 * @param string $key Choice.
		 * @return string
		 */
		public function option( $key ) {
			/* A choice made for this render only ( render args 'options', e.g. the unpaid order's invoice email: Invoice ). */
			$defs = wp_easycart_documents::options( $this->type );
			if ( isset( $this->args['options'][ $key ] ) && is_scalar( $this->args['options'][ $key ] ) && isset( $defs[ $key ]['choices'][ (string) $this->args['options'][ $key ] ] ) ) {
				return (string) $this->args['options'][ $key ];
			}
			$options = wp_easycart_documents::profile_options( $this->type, isset( $this->fields['_profile'] ) ? (string) $this->fields['_profile'] : '' );
			return isset( $options[ $key ] ) ? $options[ $key ] : '';
		}

		/** Only some items are on this document. @return bool */
		public function is_partial() {
			return ! empty( $this->held_back );
		}

		/**
		 * @param float $amount Amount.
		 * @return string
		 */
		public function money( $amount ) {
			return $GLOBALS['currency']->get_currency_display( $amount );
		}

		/**
		 * The order date in the store's time zone and date format.
		 *
		 * @return string
		 */
		public function date() {
			$timestamp = strtotime( (string) $this->order->order_date );
			return $timestamp ? date_i18n( get_option( 'date_format' ), $timestamp + $this->storage_offset() ) : '';
		}

		/**
		 * Seconds to add to a stored order date for local time ( the database clock may differ from PHP's ).
		 *
		 * @return int
		 */
		private function storage_offset() {
			global $wpdb;
			static $offset = null;
			if ( null === $offset ) {
				$now    = strtotime( (string) $wpdb->get_var( 'SELECT NOW() AS the_time' ) );
				$offset = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) - (int) ( $now - time() );
			}
			return $offset;
		}

		/**
		 * The image to show for a line ( option image first, then the product image and the store fallbacks ).
		 *
		 * @param object $line ec_orderdetail row.
		 * @return string
		 */
		public function image_url( $line ) {
			if ( ! class_exists( 'wp_easycart_email_design' ) ) {
				require_once EC_PLUGIN_DIRECTORY . '/inc/classes/core/class-wp-easycart-email-design.php';
			}
			return wp_easycart_email_design::product_image_url( isset( $line->image1 ) ? $line->image1 : '', ! empty( $line->is_deconetwork ), isset( $line->deconetwork_image_link ) ? $line->deconetwork_image_link : '' );
		}

		/**
		 * A line's chosen options as label / value pairs ( basic option items, then advanced options ).
		 *
		 * @param object $line ec_orderdetail row.
		 * @return array Each: array( 'label' => string, 'value' => string ), plain text.
		 */
		public function options( $line ) {
			global $wpdb;
			$out = array();
			if ( empty( $line->use_advanced_optionset ) || ! empty( $line->use_both_option_types ) ) {
				for ( $n = 1; $n <= 5; $n++ ) {
					$name = isset( $line->{'optionitem_name_' . $n} ) ? trim( (string) $line->{'optionitem_name_' . $n} ) : '';
					if ( '' !== $name ) {
						$out[] = array( 'label' => '', 'value' => wp_strip_all_tags( $name ) );
					}
				}
			}
			if ( ! empty( $line->use_advanced_optionset ) || ! empty( $line->use_both_option_types ) ) {
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ec_order_option WHERE orderdetail_id = %d ORDER BY order_option_id ASC', (int) $line->orderdetail_id ) ) as $option ) {
					if ( 'file' === $option->option_type ) {
						$parts = explode( '/', (string) $option->option_value );
						$value = end( $parts );
					} elseif ( 'grid' === $option->option_type ) {
						$value = $option->optionitem_name . ' (' . $option->option_value . ')';
					} else {
						$value = (string) $option->option_value;
					}
					$out[] = array(
						'label' => wp_strip_all_tags( (string) $option->option_label ),
						'value' => wp_strip_all_tags( (string) $value ),
					);
				}
			}
			return $out;
		}

		/**
		 * The variables the pre-6.0.1 packing slip template used, so a copy of it in the data folder still renders.
		 *
		 * @return array
		 */
		public function legacy_vars() {
			$order   = $this->order;
			$db      = class_exists( 'ec_db_admin' ) ? new ec_db_admin() : null;
			$curr    = $GLOBALS['currency'];
			$store   = get_permalink( get_option( 'ec_option_storepage' ) );
			$details = ( $db && method_exists( $db, 'get_order_details_admin' ) ) ? $db->get_order_details_admin( $this->order_id ) : $this->lines;
			if ( $this->is_partial() && is_array( $details ) ) {
				$keep    = wp_list_pluck( $this->lines, 'orderdetail_id' );
				$details = array_values(
					array_filter(
						$details,
						function ( $row ) use ( $keep ) {
							return in_array( $row->orderdetail_id, $keep ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- ids come back as strings from one query and ints from another.
						}
					)
				);
			}
			return array(
				'order'             => $order,
				'order_details'     => $details,
				'order_id'          => $this->order_id,
				'mysqli'            => $db,
				'db'                => $db,
				'country_list'      => ( $db && method_exists( $db, 'get_countries' ) ) ? $db->get_countries() : array(),
				'order_timestamp'   => strtotime( (string) $order->order_date ) + $this->storage_offset(),
				'total'             => $curr->get_currency_display( $order->grand_total ),
				'subtotal'          => $curr->get_currency_display( $order->sub_total ),
				'tax'               => $curr->get_currency_display( $order->tax_total ),
				'tip'               => $curr->get_currency_display( $order->tip_total ),
				'has_duty'          => ( $order->duty_total > 0 ),
				'duty'              => $curr->get_currency_display( $order->duty_total ),
				'vat'               => $curr->get_currency_display( $order->vat_total ),
				'vat_rate'          => number_format( (float) $order->vat_rate, 0, '', '' ),
				'shipping'          => $curr->get_currency_display( $order->shipping_total ),
				'discount'          => $curr->get_currency_display( $order->discount_total ),
				'gst_total'         => $curr->get_currency_display( $order->gst_total ),
				'pst_total'         => $curr->get_currency_display( $order->pst_total ),
				'hst_total'         => $curr->get_currency_display( $order->hst_total ),
				'gst_rate'          => $order->gst_rate,
				'pst_rate'          => $order->pst_rate,
				'hst_rate'          => $order->hst_rate,
				'email_logo_url'    => get_option( 'ec_option_email_logo' ),
				'store_page'        => $store,
				'permalink_divider' => ( substr_count( (string) $store, '?' ) ) ? '&' : '?',
			);
		}

		/**
		 * Include a template with this document ( and the pre-6.0.1 variables ) in scope and return its output.
		 * Runs as a method so a copied template that mentions $this does not stop the page.
		 *
		 * @param string $file Template path.
		 * @return string
		 */
		public function capture( $file ) {
			if ( '' === (string) $file || ! file_exists( $file ) ) {
				return '';
			}
			$document = $this;
			extract( $this->legacy_vars(), EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- the pre-6.0.1 template reads these as plain variables.
			ob_start();
			include $file;
			return (string) ob_get_clean();
		}
	}

endif;
