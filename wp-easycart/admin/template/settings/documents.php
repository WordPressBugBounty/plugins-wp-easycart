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
 * @since 6.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'slug'        => 'documents',
	'title'       => __( 'Documents', 'wp-easycart' ),
	'description' => __( 'What receipts, shipped emails and packing slips show, and which emails carry them.', 'wp-easycart' ),
	'group'       => 'customize',
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
					'desc'        => __( 'What the attachment is called. {order_id} becomes the order number and {date} the order date.', 'wp-easycart' ),
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

		'attachments'  => array(
			'title'    => __( 'Email attachments', 'wp-easycart' ),
			'hint'     => __( 'Which documents go out as PDFs with each email, and with which profile', 'wp-easycart' ),
			'pro'      => true,
			/* The working grid comes with WP EasyCart PRO 6.0.1; an older PRO leaves it locked ( with the update wording ). */
			'pro_min_version' => '6.0.1',
			'fields'   => array(),
			'keywords' => array( 'pdf', 'attachment', 'attach packing slip', 'packing slip pdf', 'receipt pdf', 'invoice pdf' ),
			'render'   => array( 'wp_easycart_admin_documents', 'render_attachments_locked' ),
		),
	),
);
