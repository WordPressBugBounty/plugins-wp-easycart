<?php
/**
 * Settings › Documents ( V2 declaration ).
 *
 * What the order receipt email, the shipping confirmation email and the packing slip show, as named profiles.
 * Each section is one document's profile editor with a live preview; the editors, their preview and their AJAX live
 * in admin/inc/wp_easycart_admin_documents.php, the engine in inc/classes/core/class-wp-easycart-documents.php.
 * The packing slip switches used to be the "Packing slip" section of Settings › Shipping; the Standard profile still
 * reads and writes those ec_option_packing_slip_show_* options.
 *
 * Email attachments is a PRO section: WP EasyCart PRO fills it through the wp_easycart_settings_page_documents filter.
 * Invoice PDF ( 6.0.1 ) is the PDF WP EasyCart PRO attaches to order emails: its settings moved here from Settings › Email
 * ( same option names ), its heading is chosen per profile ( the Standard profile's is ec_option_pdf_document_title ).
 * The store's logo and footer image live on Settings › Email › Sender only; each profile can use its own from the
 * Logo & footer drawer beside its preview ( PRO ).
 *
 * 6.0.2 adds the Invoice email ( what the order screen's Send Email › Invoice sends ), the Gift receipt and Credit note
 * documents and the store-wide sections that decide which documents an order
 * gets: gift orders, document rules, customer downloads, invoice numbers ( with credit notes ), PO numbers and payment
 * terms. All of them are WP EasyCart PRO 6.0.2 except the print receipt switch; PRO attaches their behaviour through
 * wp-easycart-pro/admin/template/settings/documents.php.
 *
 * @since 6.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_easycart_settings_documents_profiles' ) ) {
	/**
	 * A document's profiles as id => name, for the gift profile selects ( 6.0.2 ).
	 *
	 * @since 6.0.2
	 * @param string $type Document type.
	 * @return array
	 */
	function wp_easycart_settings_documents_profiles( $type ) {
		$out = array( '' => __( 'The default profile', 'wp-easycart' ) );
		if ( class_exists( 'wp_easycart_documents' ) && wp_easycart_documents::is_type( $type ) ) {
			foreach ( wp_easycart_documents::profile_list( $type ) as $id => $info ) {
				$out[ $id ] = $info['name'];
			}
		}
		return $out;
	}

	/** @return array Packing slip profiles. */
	function wp_easycart_settings_documents_packing_slip_profiles() {
		return wp_easycart_settings_documents_profiles( 'packing_slip' );
	}

	/** @return array Shipped email profiles. */
	function wp_easycart_settings_documents_shipping_profiles() {
		return wp_easycart_settings_documents_profiles( 'shipping' );
	}

	/** @return array Gift receipt profiles. */
	function wp_easycart_settings_documents_gift_receipt_profiles() {
		return wp_easycart_settings_documents_profiles( 'gift_receipt' );
	}
}

if ( ! function_exists( 'wp_easycart_settings_documents_role_options' ) ) {
	/**
	 * Who can give a PO number at checkout: guests and each customer role ( 6.0.2 ).
	 *
	 * @since 6.0.2
	 * @return array
	 */
	function wp_easycart_settings_documents_role_options() {
		$out = array( 'guest' => __( 'Guest checkout', 'wp-easycart' ) );
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'get_col' ) ) {
			foreach ( (array) $GLOBALS['wpdb']->get_col( "SELECT role_label FROM ec_role WHERE admin_access = 0 AND role_label != 'admin' ORDER BY role_label ASC" ) as $role ) {
				$out[ (string) $role ] = ucwords( str_replace( array( '_', '-' ), ' ', (string) $role ) );
			}
		}
		if ( ! isset( $out['shopper'] ) ) {
			$out['shopper'] = __( 'Shopper', 'wp-easycart' );
		}
		return $out;
	}
}

if ( ! function_exists( 'wp_easycart_settings_documents_terms' ) ) {
	/**
	 * The store's payment terms as label => days ( ec_option_payment_terms_list, "Net 30=30|Net 60=60" ).
	 *
	 * @since 6.0.2
	 * @param string|null $raw Stored list, or null to read the option.
	 * @return array
	 */
	function wp_easycart_settings_documents_terms( $raw = null ) {
		if ( null === $raw ) {
			$raw = function_exists( 'get_option' ) ? get_option( 'ec_option_payment_terms_list', 'Due on receipt=0|Net 15=15|Net 30=30|Net 60=60' ) : '';
		}
		$out = array();
		foreach ( explode( '|', (string) $raw ) as $entry ) {
			$parts = explode( '=', $entry, 2 );
			$label = trim( $parts[0] );
			if ( '' === $label ) {
				continue;
			}
			$out[ $label ] = isset( $parts[1] ) ? max( 0, min( 3650, (int) $parts[1] ) ) : 0;
		}
		return $out;
	}

	/** @return array Terms as label => label, for the default terms select. */
	function wp_easycart_settings_documents_terms_options() {
		$out = array();
		foreach ( wp_easycart_settings_documents_terms() as $label => $days ) {
			$out[ $label ] = $label;
		}
		return $out ? $out : array( 'Due on receipt' => __( 'Due on receipt', 'wp-easycart' ) );
	}

	/**
	 * Clean the posted terms list: "label=days" entries, labels without the separators, days 0 to 3650.
	 *
	 * @param mixed $raw Posted value.
	 * @return string
	 */
	function wp_easycart_settings_documents_sanitize_terms( $raw ) {
		$out = array();
		foreach ( wp_easycart_settings_documents_terms( sanitize_text_field( (string) $raw ) ) as $label => $days ) {
			$label = trim( str_replace( array( '|', '=' ), ' ', $label ) );
			if ( '' !== $label ) {
				$out[] = substr( $label, 0, 60 ) . '=' . (int) $days;
			}
		}
		return implode( '|', array_slice( $out, 0, 20 ) );
	}
}

if ( ! function_exists( 'wp_easycart_settings_documents_sanitize_format' ) ) {
	/**
	 * An invoice or credit note number format must contain {number}.
	 *
	 * @since 6.0.2
	 * @param mixed $raw   Posted value.
	 * @param array $field Field.
	 * @return string|WP_Error
	 */
	function wp_easycart_settings_documents_sanitize_format( $raw, $field ) {
		$value = trim( sanitize_text_field( (string) $raw ) );
		if ( strlen( $value ) > 40 ) {
			return new WP_Error( 'format', __( 'Keep the format to 40 characters or fewer.', 'wp-easycart' ) );
		}
		if ( false === strpos( $value, '{number}' ) ) {
			return new WP_Error( 'format', __( 'Include {number} so each number is different.', 'wp-easycart' ) );
		}
		return $value;
	}
}

return array(
	'slug'        => 'documents',
	'title'       => __( 'Documents', 'wp-easycart' ),
	'description' => __( 'What receipts, invoices, packing slips and gift receipts show, and which orders and emails get them.', 'wp-easycart' ),
	'group'       => 'emails-documents',
	'order'       => 20,
	'icon'        => 'media-document',
	'legacy'      => array(),
	'upsell'      => 'documents',
	'enqueue'     => array( 'wp_easycart_admin_documents', 'enqueue' ),
	'sections'    => array(

		'receipt'      => array(
			'title'    => __( 'Order receipt', 'wp-easycart' ),
			'hint'     => __( 'The email a customer gets when they pay, and your store’s copy', 'wp-easycart' ),
			'document' => 'receipt',
			'fields'   => array(),
			'keywords' => array( 'receipt', 'order email', 'confirmation', 'logo per profile', 'footer image', 'images', 'sku', 'prices', 'billing address', 'shipping address', 'order notes', 'customer email' ),
			'render'   => array( 'wp_easycart_admin_documents', 'render_editor' ),
		),

		'shipping'     => array(
			'title'    => __( 'Shipping confirmation', 'wp-easycart' ),
			'hint'     => __( 'The email a customer gets when their order is marked shipped', 'wp-easycart' ),
			'document' => 'shipping',
			'fields'   => array(),
			'keywords' => array( 'shipped email', 'shipping email', 'order shipped', 'logo per profile', 'footer image', 'tracking', 'hide prices', 'gift', 'images', 'sku' ),
			'render'   => array( 'wp_easycart_admin_documents', 'render_editor' ),
		),

		'packing-slip' => array(
			'title'    => __( 'Packing slip', 'wp-easycart' ),
			'hint'     => __( 'The page that goes in the box', 'wp-easycart' ),
			'document' => 'packing_slip',
			'fields'   => array(),
			'keywords' => array( 'packing slip', 'packing list', 'print', 'hide prices', 'prices', 'totals', 'logo', 'logo per profile', 'footer image', 'images', 'sku', 'model number', 'options', 'billing address', 'shipping address', 'phone', 'order notes', 'tracking' ),
			'render'   => array( 'wp_easycart_admin_documents', 'render_editor' ),
		),

		/* 6.0.1: the invoice / receipt PDF ( was Settings › Email › PDF receipts ): its PDF settings, then its profiles. */
		'invoice'      => array(
			'title'           => __( 'Invoice PDF', 'wp-easycart' ),
			'hint'            => __( 'The invoice or receipt PDF attached to order emails, with your business details', 'wp-easycart' ),
			'document'        => 'invoice',
			'pro'             => true,
			/* The PDF is built by WP EasyCart PRO 6.0.1 from this document; an older PRO leaves it locked ( with the update wording ). */
			'pro_min_version' => '6.0.1',
			'keywords'        => array( 'invoice', 'pdf', 'pdf receipt', 'receipt pdf', 'invoice pdf', 'eu invoice', 'vat', 'bookkeeping', 'heading', 'logo per profile' ),
			'fields'          => array(
				'ec_option_pdf_seller_details' => array(
					'type'        => 'textarea',
					'label'       => __( 'Business details', 'wp-easycart' ),
					'desc'        => __( 'Your registered business name, address and VAT number, printed at the top of the PDF. EU invoices must show them.', 'wp-easycart' ),
					'default'     => '',
					'placeholder' => "Store Name Ltd.\n1 Example Street, 10115 Berlin, Germany\nVAT ID: DE123456789",
					'pro'         => true,
					'keywords'    => array( 'pdf', 'company', 'address', 'vat number', 'tax id', 'seller', 'legal' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'PDF receipts', 'label' => 'Business details on the PDF' ),
				),
				'ec_option_pdf_filename' => array(
					'type'        => 'text',
					'label'       => __( 'File name', 'wp-easycart' ),
					'desc'        => __( 'What the attachment is called. {order_id} becomes the order number, {invoice_number} the invoice number once one is issued, and {date} the order date.', 'wp-easycart' ),
					'default'     => 'invoice-{order_id}.pdf',
					'placeholder' => 'invoice-{order_id}.pdf',
					'pro'         => true,
					'keywords'    => array( 'pdf', 'file name', 'attachment name', 'invoice number' ),
					'legacy'      => array( 'page' => 'email-setup', 'section' => 'PDF receipts', 'label' => 'PDF file name' ),
				),
				'ec_option_pdf_paper_size' => array(
					'type'     => 'select',
					'label'    => __( 'Paper size', 'wp-easycart' ),
					'desc'     => __( 'Automatic uses US Letter where it is the local standard ( such as the US, Canada and Mexico ) and A4 everywhere else.', 'wp-easycart' ),
					'default'  => 'auto',
					'options'  => array(
						'auto'   => __( 'Automatic', 'wp-easycart' ),
						'a4'     => __( 'A4', 'wp-easycart' ),
						'letter' => __( 'US Letter', 'wp-easycart' ),
					),
					'advanced' => true,
					'pro'      => true,
					'keywords' => array( 'pdf', 'paper', 'a4', 'letter', 'page size' ),
					'legacy'   => array( 'page' => 'email-setup', 'section' => 'PDF receipts', 'label' => 'PDF paper size' ),
				),
			),
			'actions'         => array(
				array(
					'id'     => 'send_pdf_sample',
					'label'  => __( 'Email me a sample PDF', 'wp-easycart' ),
					'desc'   => __( 'Builds the PDF for your most recent order with the default profile and sends it to your own email address only.', 'wp-easycart' ),
					'button' => __( 'Send sample', 'wp-easycart' ),
					'pro'    => true,
				),
			),
			'render'          => array( 'wp_easycart_admin_documents', 'render_editor' ),
		),

		/* 6.0.2: the email an invoice goes out in from the order screen ( Send Email › Invoice ), with its Pay button. */
		'invoice-email' => array(
			'title'           => __( 'Invoice email', 'wp-easycart' ),
			'hint'            => __( 'The email you send from an order with its invoice, and a Pay button while it is unpaid', 'wp-easycart' ),
			'document'        => 'invoice_email',
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'fields'          => array(),
			'keywords'        => array( 'invoice email', 'send invoice', 'email invoice', 'pay button', 'pay link', 'pay invoice', 'invoice template', 'email template', 'bank transfer' ),
			'render'          => array( 'wp_easycart_admin_documents', 'render_editor' ),
		),

		/* 6.0.2: what a gift recipient gets, and the credit note a refund on an invoiced order issues. */
		'gift-receipt' => array(
			'title'           => __( 'Gift receipt', 'wp-easycart' ),
			'hint'            => __( 'Emailed to the person receiving a gift order, without prices', 'wp-easycart' ),
			'document'        => 'gift_receipt',
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'fields'          => array(),
			'keywords'        => array( 'gift receipt', 'gift', 'recipient', 'no prices', 'exchange' ),
			'render'          => array( 'wp_easycart_admin_documents', 'render_editor' ),
		),

		'credit-note'  => array(
			'title'           => __( 'Credit note', 'wp-easycart' ),
			'hint'            => __( 'The PDF a refund on an invoiced order issues', 'wp-easycart' ),
			'document'        => 'credit_note',
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'fields'          => array(),
			'keywords'        => array( 'credit note', 'credit memo', 'refund', 'invoice correction' ),
			'render'          => array( 'wp_easycart_admin_documents', 'render_editor' ),
		),

		'attachments'  => array(
			'title'    => __( 'Email attachments', 'wp-easycart' ),
			'hint'     => __( 'Which documents go out as PDFs with each email, and with which profile', 'wp-easycart' ),
			'pro'      => true,
			/* The working grid comes with WP EasyCart PRO 6.0.1; an older PRO leaves it locked ( with the update wording ). */
			'pro_min_version' => '6.0.1',
			'fields'   => array(),
			'keywords' => array( 'pdf', 'attachment', 'attach packing slip', 'packing slip pdf', 'receipt pdf', 'invoice pdf', 'download link' ),
			'render'   => array( 'wp_easycart_admin_documents', 'render_attachments_locked' ),
		),

		/* 6.0.2: gift purchases. */
		'gifts'        => array(
			'title'           => __( 'Gift orders', 'wp-easycart' ),
			'hint'            => __( 'A gift option at checkout, and what a gift order sends', 'wp-easycart' ),
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'keywords'        => array( 'gift', 'gift message', 'gift wrap', 'gift receipt', 'send as gift', 'recipient', 'gift options placement', 'payment step' ),
			'fields'          => array(
				'ec_option_gift_purchases' => array(
					'type'     => 'toggle',
					'label'    => __( 'Let customers mark an order as a gift', 'wp-easycart' ),
					'desc'     => __( 'Adds a gift option at checkout. Gift orders use the profiles chosen here, so no prices go in the box.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Gift orders', 'label' => 'New in 6.0.2' ),
				),
				/* 6.0.2: where both checkouts ask ( WP EasyCart PRO's wp_easycart_order_extras_pro::placements() ). */
				'ec_option_gift_placement' => array(
					'type'     => 'pills',
					'label'    => __( 'Where checkout asks', 'wp-easycart' ),
					'desc'     => __( 'Most stores ask with the shipping address, where shoppers decide who the parcel is for; asking just before Place order keeps the first step shorter.', 'wp-easycart' ),
					'default'  => 'details',
					'options'  => array(
						'details' => __( 'With the shipping address', 'wp-easycart' ),
						'review'  => __( 'Just before Place order', 'wp-easycart' ),
					),
					'parent'   => 'ec_option_gift_purchases',
					'pro'      => true,
					'keywords' => array( 'gift placement', 'checkout step', 'payment step', 'review step', 'shipping step' ),
					'legacy'   => array( 'page' => 'documents', 'section' => 'Gift orders', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_gift_message' => array(
					'type'     => 'pills',
					'label'    => __( 'Gift message', 'wp-easycart' ),
					'desc'     => __( 'Up to 200 characters, printed on the packing slip and the gift receipt.', 'wp-easycart' ),
					'default'  => 'optional',
					'options'  => array(
						'optional' => __( 'Optional', 'wp-easycart' ),
						'off'      => __( 'Off', 'wp-easycart' ),
					),
					'parent'   => 'ec_option_gift_purchases',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Gift orders', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_gift_packing_slip_profile' => array(
					'type'     => 'select',
					'label'    => __( 'Packing slip for gift orders', 'wp-easycart' ),
					'desc'     => __( 'The slip printed or attached for a gift order.', 'wp-easycart' ),
					'default'  => 'gift',
					'options'  => 'wp_easycart_settings_documents_packing_slip_profiles',
					'parent'   => 'ec_option_gift_purchases',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Gift orders', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_gift_shipping_profile' => array(
					'type'     => 'select',
					'label'    => __( 'Shipped email for gift orders', 'wp-easycart' ),
					'desc'     => __( 'What the buyer gets when a gift order ships.', 'wp-easycart' ),
					'default'  => 'gift',
					'options'  => 'wp_easycart_settings_documents_shipping_profiles',
					'parent'   => 'ec_option_gift_purchases',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Gift orders', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_gift_receipt_recipient' => array(
					'type'     => 'toggle',
					'label'    => __( 'Email a gift receipt to the recipient', 'wp-easycart' ),
					'desc'     => __( 'Asks for the recipient’s email at checkout and sends them the gift receipt when the order ships.', 'wp-easycart' ),
					'default'  => 0,
					'parent'   => 'ec_option_gift_purchases',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Gift orders', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_gift_receipt_profile' => array(
					'type'     => 'select',
					'label'    => __( 'Gift receipt profile', 'wp-easycart' ),
					'desc'     => __( 'Which gift receipt the recipient gets.', 'wp-easycart' ),
					'default'  => '',
					'options'  => 'wp_easycart_settings_documents_gift_receipt_profiles',
					'parent'   => 'ec_option_gift_receipt_recipient',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Gift orders', 'label' => 'New in 6.0.2' ),
				),
			),
		),

		/* 6.0.2: rules pick the profiles, attachments and payment terms for an order ( first match wins ). */
		'rules'        => array(
			'title'           => __( 'Document rules', 'wp-easycart' ),
			'hint'            => __( 'Different profiles, attachments and customer downloads for different kinds of orders', 'wp-easycart' ),
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'fields'          => array(),
			'keywords'        => array( 'rules', 'conditions', 'wholesale', 'b2b', 'local pickup', 'customer role', 'payment method', 'country', 'automatic profile' ),
			'render'          => array( 'wp_easycart_admin_documents', 'render_rules_locked' ),
		),

		/* 6.0.2: what customers can download from their account, and download links in emails. */
		'downloads'    => array(
			'title'    => __( 'Customer downloads', 'wp-easycart' ),
			'hint'     => __( 'Documents customers can open from their account and from emails. Document rules can change these for some orders.', 'wp-easycart' ),
			'keywords' => array( 'download', 'my account', 'print receipt', 'pdf download', 'download link', 'link expiry' ),
			'fields'   => array(
				'ec_option_account_print_receipt' => array(
					'type'     => 'toggle',
					'label'    => __( 'Print receipt link on the account order page', 'wp-easycart' ),
					'desc'     => __( 'The printer icon on each order in My Account.', 'wp-easycart' ),
					'default'  => 1,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Customer downloads', 'label' => 'New in 6.0.2' ),
				),
				/* 6.0.2: the same choice for the page shown right after checkout. */
				'ec_option_success_print_receipt' => array(
					'type'     => 'toggle',
					'label'    => __( 'Print receipt link on the order confirmation page', 'wp-easycart' ),
					'desc'     => __( 'The Print receipt button customers see right after they place an order.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'success page', 'thank you page', 'order confirmation', 'print receipt' ),
					'legacy'   => array( 'page' => 'documents', 'section' => 'Customer downloads', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_account_receipt_pdf' => array(
					'type'     => 'toggle',
					'label'    => __( 'Download receipt PDF', 'wp-easycart' ),
					'desc'     => __( 'The Invoice PDF with the Receipt heading, from the order page in My Account.', 'wp-easycart' ),
					'default'  => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'legacy'   => array( 'page' => 'documents', 'section' => 'Customer downloads', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_account_invoice_pdf' => array(
					'type'     => 'toggle',
					'label'    => __( 'Download invoice PDF', 'wp-easycart' ),
					'desc'     => __( 'Once the order has an invoice number, the invoice as it was issued.', 'wp-easycart' ),
					'default'  => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'legacy'   => array( 'page' => 'documents', 'section' => 'Customer downloads', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_account_packing_slip' => array(
					'type'     => 'toggle',
					'label'    => __( 'Download packing slip', 'wp-easycart' ),
					'desc'     => __( 'The default packing slip profile, as a PDF.', 'wp-easycart' ),
					'default'  => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'legacy'   => array( 'page' => 'documents', 'section' => 'Customer downloads', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_account_gift_receipt' => array(
					'type'     => 'toggle',
					'label'    => __( 'Download gift receipt', 'wp-easycart' ),
					'desc'     => __( 'On gift orders, so the buyer can print it for the recipient.', 'wp-easycart' ),
					'default'  => 0,
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'legacy'   => array( 'page' => 'documents', 'section' => 'Customer downloads', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_document_link_days' => array(
					'type'     => 'select',
					'label'    => __( 'Download links in emails stop working after', 'wp-easycart' ),
					'desc'     => __( 'For documents the attachments grid sends as a link instead of a file.', 'wp-easycart' ),
					'default'  => '30',
					'options'  => array(
						'7'  => __( '7 days', 'wp-easycart' ),
						'30' => __( '30 days', 'wp-easycart' ),
						'90' => __( '90 days', 'wp-easycart' ),
						'0'  => __( 'Never', 'wp-easycart' ),
					),
					'pro'             => true,
					'pro_min_version' => '6.0.2',
					'legacy'   => array( 'page' => 'documents', 'section' => 'Customer downloads', 'label' => 'New in 6.0.2' ),
				),
			),
		),

		/* 6.0.2: pay an unpaid order from a link ( wp_easycart_order_pay ). */
		'pay-links'    => array(
			'title'    => __( 'Pay links', 'wp-easycart' ),
			'hint'     => __( 'A page where customers pay an unpaid order, linked from invoices and My Account', 'wp-easycart' ),
			'keywords' => array( 'pay link', 'payment link', 'pay invoice', 'pay now', 'unpaid order', 'stripe', 'square', 'paypal', 'bank transfer' ),
			'fields'   => array(
				'ec_option_order_pay_links' => array(
					'type'    => 'toggle',
					'label'   => __( 'Let customers pay unpaid orders from a link', 'wp-easycart' ),
					'desc'    => __( 'Invoice emails and the order page in My Account get a Pay button that opens a page for that order.', 'wp-easycart' ),
					'default' => 1,
					'legacy'  => array( 'page' => 'documents', 'section' => 'Pay links', 'label' => 'New in 6.0.2' ),
				),
			),
			'render'   => array( 'wp_easycart_order_pay', 'render_settings_status' ),
		),

		/* 6.0.2: invoice numbers, and credit notes for refunds. */
		'invoicing'    => array(
			'title'           => __( 'Invoice numbers', 'wp-easycart' ),
			'hint'            => __( 'Numbered invoices that never change once issued, and credit notes for refunds', 'wp-easycart' ),
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'keywords'        => array( 'invoice number', 'numbering', 'sequential', 'INV', 'credit note', 'credit memo', 'refund', 'accounting', 'bookkeeping' ),
			'fields'          => array(
				'ec_option_invoice_numbering' => array(
					'type'     => 'toggle',
					'label'    => __( 'Give invoices their own numbers', 'wp-easycart' ),
					'desc'     => __( 'Each order gets the next invoice number once, and its Invoice PDF is kept exactly as it was issued.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_invoice_issue' => array(
					'type'     => 'select',
					'label'    => __( 'Issue the invoice', 'wp-easycart' ),
					'desc'     => __( 'Orders paid by manual payment get their number when you first send the invoice, or when the payment arrives.', 'wp-easycart' ),
					'default'  => 'paid',
					'options'  => array(
						'paid'   => __( 'When payment is approved', 'wp-easycart' ),
						'placed' => __( 'When the order is placed', 'wp-easycart' ),
						'manual' => __( 'Only when I send it from the order', 'wp-easycart' ),
					),
					'parent'   => 'ec_option_invoice_numbering',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_invoice_number_format' => array(
					'type'        => 'text',
					'label'       => __( 'Number format', 'wp-easycart' ),
					'desc'        => __( '{year} becomes the year it is issued and {number} the next number.', 'wp-easycart' ),
					'default'     => 'INV-{year}-{number}',
					'placeholder' => 'INV-{year}-{number}',
					'sanitize'    => 'wp_easycart_settings_documents_sanitize_format',
					'parent'      => 'ec_option_invoice_numbering',
					'pro'         => true,
					'legacy'      => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_invoice_next_number' => array(
					'type'     => 'number',
					'label'    => __( 'Next number', 'wp-easycart' ),
					'desc'     => __( 'Set this once to carry on from the numbers you used before.', 'wp-easycart' ),
					'default'  => 1,
					'min'      => 1,
					'step'     => 1,
					'parent'   => 'ec_option_invoice_numbering',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_invoice_number_padding' => array(
					'type'     => 'number',
					'label'    => __( 'Digits', 'wp-easycart' ),
					'desc'     => __( 'Pads the number with zeros: 4 makes 7 into 0007.', 'wp-easycart' ),
					'default'  => 4,
					'min'      => 1,
					'max'      => 10,
					'step'     => 1,
					'advanced' => true,
					'parent'   => 'ec_option_invoice_numbering',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_invoice_number_reset' => array(
					'type'     => 'pills',
					'label'    => __( 'Start again from 1', 'wp-easycart' ),
					'desc'     => __( 'Every year keeps numbers short when the format has {year} in it.', 'wp-easycart' ),
					'default'  => 'yearly',
					'options'  => array(
						'yearly' => __( 'Every year', 'wp-easycart' ),
						'never'  => __( 'Never', 'wp-easycart' ),
					),
					'advanced' => true,
					'parent'   => 'ec_option_invoice_numbering',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				/* 6.0.2: who sees the invoice number with their order in My Account ( owner bug round 4, item 9 ). */
				'ec_option_account_invoice_number' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the invoice number in My Account', 'wp-easycart' ),
					'desc'     => __( 'The number shows with the order details. Invoices and credit notes are still numbered when this is off.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_invoice_numbering',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_account_invoice_number_roles' => array(
					'type'     => 'multiselect',
					'label'    => __( 'Show it only to these roles', 'wp-easycart' ),
					'desc'     => __( 'Leave empty to show it to everyone, guests included. Choose roles, such as wholesale, to show it only to them.', 'wp-easycart' ),
					'default'  => '',
					'options'  => 'wp_easycart_settings_documents_role_options',
					'parent'   => 'ec_option_account_invoice_number',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_credit_notes' => array(
					'type'     => 'toggle',
					'label'    => __( 'Issue a credit note for refunds', 'wp-easycart' ),
					'desc'     => __( 'A refund on an order that has an invoice gets its own numbered credit note PDF, linked to the invoice.', 'wp-easycart' ),
					'default'  => 0,
					'parent'   => 'ec_option_invoice_numbering',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_credit_note_format' => array(
					'type'        => 'text',
					'label'       => __( 'Credit note number format', 'wp-easycart' ),
					'desc'        => __( 'Its own sequence, with the same {year} and {number}.', 'wp-easycart' ),
					'default'     => 'CN-{year}-{number}',
					'placeholder' => 'CN-{year}-{number}',
					'sanitize'    => 'wp_easycart_settings_documents_sanitize_format',
					'parent'      => 'ec_option_credit_notes',
					'pro'         => true,
					'legacy'      => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_credit_note_next_number' => array(
					'type'     => 'number',
					'label'    => __( 'Next credit note number', 'wp-easycart' ),
					'desc'     => __( 'Credit notes restart with invoices when those start again every year.', 'wp-easycart' ),
					'default'  => 1,
					'min'      => 1,
					'step'     => 1,
					'parent'   => 'ec_option_credit_notes',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Invoice numbers', 'label' => 'New in 6.0.2' ),
				),
			),
		),

		/* 6.0.2: purchase order numbers. */
		'po-numbers'   => array(
			'title'           => __( 'PO numbers', 'wp-easycart' ),
			'hint'            => __( 'A purchase order number field at checkout for business customers', 'wp-easycart' ),
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'keywords'        => array( 'po number', 'purchase order', 'b2b', 'wholesale', 'reference' ),
			'fields'          => array(
				'ec_option_po_numbers' => array(
					'type'     => 'toggle',
					'label'    => __( 'Ask for a PO number at checkout', 'wp-easycart' ),
					'desc'     => __( 'You can add or change the PO number on the order screen whether this is on or not.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'PO numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_po_number_roles' => array(
					'type'     => 'multiselect',
					'label'    => __( 'Who sees the field', 'wp-easycart' ),
					'desc'     => __( 'Usually your wholesale or business roles. Leave empty to show it to everyone.', 'wp-easycart' ),
					'default'  => '',
					'options'  => 'wp_easycart_settings_documents_role_options',
					'parent'   => 'ec_option_po_numbers',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'PO numbers', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_po_number_required' => array(
					'type'     => 'pills',
					'label'    => __( 'PO number is', 'wp-easycart' ),
					'default'  => 'optional',
					'options'  => array(
						'optional' => __( 'Optional', 'wp-easycart' ),
						'required' => __( 'Required', 'wp-easycart' ),
					),
					'parent'   => 'ec_option_po_numbers',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'PO numbers', 'label' => 'New in 6.0.2' ),
				),
			),
		),

		/* 6.0.2: payment terms on manual-payment orders. */
		'terms'        => array(
			'title'           => __( 'Payment terms', 'wp-easycart' ),
			'hint'            => __( 'Net 30 and other terms, with a due date, for orders paid later', 'wp-easycart' ),
			'pro'             => true,
			'pro_min_version' => '6.0.2',
			'keywords'        => array( 'payment terms', 'net 30', 'due date', 'pay later', 'manual payment', 'direct deposit', 'invoice due' ),
			'fields'          => array(
				'ec_option_payment_terms' => array(
					'type'     => 'toggle',
					'label'    => __( 'Give manual-payment orders payment terms', 'wp-easycart' ),
					'desc'     => __( 'Orders paid by manual payment get terms and a due date, shown on the invoice and the receipt email. A customer’s own terms, set on their account, come first.', 'wp-easycart' ),
					'default'  => 0,
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Payment terms', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_payment_terms_list' => array(
					'type'      => 'pairs',
					'separator' => '|',
					'pair'      => array(
						'key'   => array( 'label' => __( 'Terms', 'wp-easycart' ), 'placeholder' => 'Net 30', 'maxlength' => 60 ),
						'value' => array( 'label' => __( 'Days to pay', 'wp-easycart' ), 'placeholder' => '30', 'inputmode' => 'numeric' ),
						'join'  => '=',
						'add'   => __( 'Add terms', 'wp-easycart' ),
					),
					'label'     => __( 'Terms you offer', 'wp-easycart' ),
					'desc'      => __( 'The due date is the order date plus these days; 0 means due on receipt.', 'wp-easycart' ),
					'default'   => 'Due on receipt=0|Net 15=15|Net 30=30|Net 60=60',
					'sanitize'  => 'wp_easycart_settings_documents_sanitize_terms',
					'parent'    => 'ec_option_payment_terms',
					'pro'       => true,
					'legacy'    => array( 'page' => 'documents', 'section' => 'Payment terms', 'label' => 'New in 6.0.2' ),
				),
				'ec_option_payment_terms_default' => array(
					'type'     => 'select',
					'label'    => __( 'Store default', 'wp-easycart' ),
					'desc'     => __( 'For customers with no terms of their own.', 'wp-easycart' ),
					'default'  => 'Due on receipt',
					'options'  => 'wp_easycart_settings_documents_terms_options',
					'parent'   => 'ec_option_payment_terms',
					'pro'      => true,
					'legacy'   => array( 'page' => 'documents', 'section' => 'Payment terms', 'label' => 'New in 6.0.2' ),
				),
			),
		),
	),
);
