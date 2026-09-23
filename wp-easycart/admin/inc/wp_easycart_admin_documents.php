<?php
/**
 * Settings › Documents: one profile editor per document ( section render callables for
 * admin/template/settings/documents.php ), its live preview, and the ecv2_documents_* AJAX.
 *
 * The engine is wp_easycart_documents ( inc/classes/core/class-wp-easycart-documents.php ). Without WP EasyCart PRO
 * each document has its Standard profile only; the other built-ins show as locked tabs that open the upgrade popup
 * ( upsell context 'documents' ). Switches save as they change, like every other settings toggle.
 *
 * AJAX: ecv2_documents_save ( one profile's switches ), ecv2_documents_preview ( HTML for the preview frame ),
 * ecv2_documents_profile ( create, rename, delete, reset, make default ), ecv2_documents_branding_save ( a profile's own
 * logo and footer image, PRO ), ecv2_documents_wording / _wording_save ( the Edit wording drawer ).
 * Guard: ecv2_documents_guard().
 *
 * @since 6.0.1
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_documents_guard' ) ) {
	/**
	 * Settings capability and the Documents nonce, for every ecv2_documents_* request.
	 *
	 * @since 6.0.1
	 */
	function ecv2_documents_guard() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) );
		}
		check_ajax_referer( 'wp-easycart-ecv2-documents', 'nonce' );
	}
}

if ( ! class_exists( 'wp_easycart_admin_documents' ) ) :

	/**
	 * Settings › Documents screens and AJAX.
	 */
	final class wp_easycart_admin_documents {

		const NONCE = 'wp-easycart-ecv2-documents';

		/** Hook the AJAX handlers ( this file is also loaded from admin/admin-init.php for admin-ajax requests ). */
		public static function init() {
			add_action( 'wp_ajax_ecv2_documents_save', array( __CLASS__, 'ajax_save' ) );
			add_action( 'wp_ajax_ecv2_documents_preview', array( __CLASS__, 'ajax_preview' ) );
			add_action( 'wp_ajax_ecv2_documents_profile', array( __CLASS__, 'ajax_profile' ) );
			add_action( 'wp_ajax_ecv2_documents_branding_save', array( __CLASS__, 'ajax_branding_save' ) );
			add_action( 'wp_ajax_ecv2_documents_wording', array( __CLASS__, 'ajax_wording' ) );
			add_action( 'wp_ajax_ecv2_documents_wording_save', array( __CLASS__, 'ajax_wording_save' ) );
		}

		/* ------------------------------------------------------------------ */
		/* Wording ( the language editor's phrases each document prints )      */
		/* ------------------------------------------------------------------ */

		/**
		 * The phrases a document shows, as language group => keys, in the order they appear on it.
		 * Filter: wp_easycart_document_wording_keys.
		 *
		 * @since 6.0.1
		 * @param string $type Document type.
		 * @return array group => key[]
		 */
		public static function wording_keys( $type ) {
			$totals = array( 'cart_payment_complete_order_totals_subtotal', 'cart_payment_complete_order_totals_shipping', 'cart_payment_complete_order_totals_discount', 'cart_payment_complete_order_totals_tax', 'cart_payment_complete_order_totals_grand_total' );
			$keys   = array(
				'receipt'      => array(
					'cart_success' => array_merge(
						array( 'cart_payment_receipt_title', 'cart_payment_complete_line_1', 'cart_payment_complete_line_2', 'cart_payment_complete_line_3', 'cart_payment_complete_line_4', 'cart_payment_complete_billing_label', 'cart_payment_complete_shipping_label', 'cart_payment_complete_details_header_1', 'cart_payment_complete_details_header_2', 'cart_payment_complete_details_header_3', 'cart_payment_complete_details_header_4' ),
						$totals,
						array( 'cart_payment_complete_bottom_line_1', 'cart_payment_complete_bottom_line_2' )
					),
				),
				'shipping'     => array(
					'ec_shipping_email' => array( 'shipping_email_title', 'shipping_dear', 'shipping_subtitle1', 'shipping_subtitle2', 'shipping_description', 'shipping_billing_label', 'shipping_shipping_label', 'shipping_carrier', 'shipping_tracking', 'shipping_product', 'shipping_quantity', 'shipping_unit_price', 'shipping_total_price', 'shipping_final_note1', 'shipping_final_note2' ),
					'documents'         => array( 'items_to_follow' ),
				),
				'packing_slip' => array(
					'documents'    => array( 'packing_slip_title', 'order_number', 'order_date', 'ship_to', 'bill_to', 'shipping_method', 'carrier', 'tracking', 'items_to_follow', 'packing_slip_email_subject' ),
					'cart_success' => array_merge( array( 'cart_payment_complete_details_header_1', 'cart_payment_complete_details_header_2', 'cart_payment_complete_details_header_3', 'cart_payment_complete_details_header_4' ), $totals ),
				),
				'invoice'      => array(
					'documents'    => array( 'invoice_title', 'receipt_title', 'invoice_order_number', 'invoice_date' ),
					'cart_success' => array_merge(
						array( 'cart_payment_complete_line_1', 'cart_payment_complete_line_2', 'cart_payment_complete_line_3', 'cart_payment_complete_line_4', 'cart_payment_complete_shipping_label', 'cart_payment_complete_billing_label', 'cart_payment_complete_details_header_1', 'cart_payment_complete_details_header_2', 'cart_payment_complete_details_header_3', 'cart_payment_complete_details_header_4' ),
						$totals,
						array( 'cart_payment_complete_bottom_line_1', 'cart_payment_complete_bottom_line_2' )
					),
				),
			);
			return (array) apply_filters( 'wp_easycart_document_wording_keys', isset( $keys[ $type ] ) ? $keys[ $type ] : array(), $type );
		}

		/**
		 * Make sure a language carries every phrase the documents use. Phrases added in an update reach a store's saved
		 * language data only when the plugin version changes, so a missing one is merged from the shipped file first and,
		 * for a language no shipped file covers, created from the English text.
		 *
		 * @param string $file Installed language.
		 * @return bool Whether anything was added.
		 */
		private static function ensure_wording( $file ) {
			$language = wp_easycart_admin_language_v2::language();
			$data     = wp_easycart_admin_language_v2::data();
			if ( ! $language || ! $data || ! isset( $data->{$file}->options ) ) {
				return false;
			}
			$missing = false;
			foreach ( wp_easycart_documents::types() as $type => $info ) {
				foreach ( self::wording_keys( $type ) as $group => $keys ) {
					foreach ( $keys as $key ) {
						if ( ! isset( $data->{$file}->options->{$group}->options->{$key} ) ) {
							$missing = true;
							break 3;
						}
					}
				}
			}
			if ( ! $missing ) {
				return false;
			}
			$language->update_language_data(); /* merges what the shipped files add; idempotent */
			$data  = wp_easycart_admin_language_v2::data();
			$added = false;
			foreach ( wp_easycart_documents::types() as $type => $info ) {
				foreach ( self::wording_keys( $type ) as $group => $keys ) {
					foreach ( $keys as $key ) {
						if ( isset( $data->{$file}->options->{$group}->options->{$key} ) ) {
							continue;
						}
						$english = wp_easycart_admin_language_v2::shipped( 'en-us' );
						if ( ! $english || ! isset( $english->options->{$group}->options->{$key} ) ) {
							continue;
						}
						if ( ! isset( $data->{$file}->options->{$group} ) ) {
							$data->{$file}->options->{$group} = (object) array(
								'label'   => isset( $english->options->{$group}->label ) ? $english->options->{$group}->label : $group,
								'options' => (object) array(),
							);
						}
						$data->{$file}->options->{$group}->options->{$key} = clone $english->options->{$group}->options->{$key};
						$added = true;
					}
				}
			}
			if ( $added ) {
				$language->save_language_data();
			}
			wp_cache_flush();
			return true;
		}

		/**
		 * The wording drawer's rows for one document in one language.
		 *
		 * @param string $type Document type.
		 * @param string $file Installed language.
		 * @return array
		 */
		private static function wording_rows( $type, $file ) {
			$data  = wp_easycart_admin_language_v2::data();
			$names = array();
			foreach ( wp_easycart_documents::types() as $other => $info ) {
				$names[ $other ] = $info['label'];
			}
			$rows = array();
			foreach ( self::wording_keys( $type ) as $group => $keys ) {
				foreach ( $keys as $key ) {
					if ( ! isset( $data->{$file}->options->{$group}->options->{$key} ) ) {
						continue;
					}
					$entry   = $data->{$file}->options->{$group}->options->{$key};
					$value   = wp_easycart_admin_language_v2::display( html_entity_decode( (string) $entry->value, ENT_QUOTES, 'UTF-8' ) );
					$default = wp_easycart_admin_language_v2::shipped_value( $file, $group, $key );
					$shared  = array();
					foreach ( wp_easycart_documents::types() as $other => $info ) {
						$other_keys = self::wording_keys( $other );
						if ( $other !== $type && isset( $other_keys[ $group ] ) && in_array( $key, $other_keys[ $group ], true ) ) {
							$shared[] = $names[ $other ];
						}
					}
					$rows[] = array(
						'id'      => $group . '.' . $key,
						'label'   => isset( $entry->title ) ? (string) $entry->title : $key,
						'value'   => $value,
						'default' => ( null === $default ) ? null : wp_easycart_admin_language_v2::display( $default ),
						'changed' => ( null !== $default && wp_easycart_admin_language_v2::norm( $value ) !== wp_easycart_admin_language_v2::norm( $default ) ),
						'long'    => ( strlen( $value ) > 70 || ( null !== $default && strlen( $default ) > 70 ) ),
						'shared'  => $shared,
					);
				}
			}
			return $rows;
		}

		/** The installed language named in the request ( the storefront's by default ), or ''. */
		private static function posted_wording_language() {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- ecv2_documents_guard() ran first in every caller.
			$file      = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : '';
			$installed = wp_easycart_admin_language_v2::installed();
			if ( '' === $file || ! isset( $installed[ $file ] ) ) {
				$file = wp_easycart_admin_language_v2::active();
			}
			return isset( $installed[ $file ] ) ? $file : '';
		}

		/** The payload the wording drawer draws from. */
		private static function wording_payload( $type, $file ) {
			$languages = array();
			foreach ( wp_easycart_admin_language_v2::installed() as $slug => $label ) {
				$languages[] = array(
					'file'  => $slug,
					'label' => is_string( $label ) ? $label : wp_easycart_admin_language_v2::name( $slug ),
				);
			}
			return array(
				'type'      => $type,
				'language'  => $file,
				'languages' => $languages,
				'rows'      => self::wording_rows( $type, $file ),
				'editor'    => admin_url( 'admin.php?page=wp-easycart-settings&subpage=language-editor' ),
			);
		}

		/** AJAX: the phrases a document prints, for the wording drawer. POST type, language. */
		public static function ajax_wording() {
			ecv2_documents_guard();
			$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
			if ( ! wp_easycart_documents::is_type( $type ) || ! class_exists( 'wp_easycart_admin_language_v2' ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown document.', 'wp-easycart' ) ) );
			}
			$file = self::posted_wording_language();
			if ( '' === $file ) {
				wp_send_json_error( array( 'message' => __( 'Language data is not available.', 'wp-easycart' ) ) );
			}
			self::ensure_wording( $file );
			wp_send_json_success( self::wording_payload( $type, $file ) );
		}

		/** AJAX: save the wording drawer. POST type, language, values ( JSON: "group.key" => text ). */
		public static function ajax_wording_save() {
			ecv2_documents_guard();
			$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
			if ( ! wp_easycart_documents::is_type( $type ) || ! class_exists( 'wp_easycart_admin_language_v2' ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown document.', 'wp-easycart' ) ) );
			}
			$file     = self::posted_wording_language();
			$language = wp_easycart_admin_language_v2::language();
			if ( '' === $file || ! $language ) {
				wp_send_json_error( array( 'message' => __( 'Language data is not available.', 'wp-easycart' ) ) );
			}
			self::ensure_wording( $file );
			$raw = isset( $_POST['values'] ) && is_string( $_POST['values'] ) ? json_decode( wp_unslash( $_POST['values'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; each value goes through update_language_item(), which filters it like the language editor does.
			if ( ! is_array( $raw ) ) {
				wp_send_json_error( array( 'message' => __( 'Nothing to save.', 'wp-easycart' ) ) );
			}
			$allowed = array();
			foreach ( self::wording_keys( $type ) as $group => $keys ) {
				foreach ( $keys as $key ) {
					$allowed[ $group . '.' . $key ] = array( $group, $key );
				}
			}
			$data  = wp_easycart_admin_language_v2::data();
			$saved = 0;
			foreach ( $raw as $id => $value ) {
				if ( ! isset( $allowed[ $id ] ) || ! is_scalar( $value ) || strlen( (string) $value ) > 20000 ) {
					continue;
				}
				list( $group, $key ) = $allowed[ $id ];
				if ( ! isset( $data->{$file}->options->{$group}->options->{$key} ) ) {
					continue;
				}
				/* update_language_item() unslashes and filters ( wp_easycart_escape_html + htmlspecialchars ) like the language editor. */
				$language->update_language_item( $file, $group, $key, wp_slash( (string) $value ) );
				++$saved;
			}
			wp_cache_flush();
			$payload            = self::wording_payload( $type, $file );
			$payload['saved']   = $saved;
			$payload['message'] = __( 'Wording saved.', 'wp-easycart' );
			wp_send_json_success( $payload );
		}

		/**
		 * Page 'enqueue' callable: the editor script and styles.
		 *
		 * @param array $page Declaration.
		 */
		public static function enqueue( $page = array() ) {
			/* 6.0.1: a document's own logo or footer image is picked from the Media Library. */
			if ( function_exists( 'wp_enqueue_media' ) ) {
				wp_enqueue_media();
			}
			wp_enqueue_style( 'wp_easycart_admin_documents_v2', plugins_url( 'wp-easycart/admin/css/documents-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_admin_documents_v2', plugins_url( 'wp-easycart/admin/js/documents-v2.js', EC_PLUGIN_DIRECTORY ), array( 'jquery' ), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wp_easycart_admin_documents_v2',
				'ecv2_documents',
				array(
					'ajax_url'      => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( self::NONCE ),
					'pro'           => class_exists( 'wp_easycart_documents' ) && wp_easycart_documents::pro_enabled(),
					'update_url'    => self::needs_update() ? self_admin_url( 'plugins.php' ) : '', /* 6.0.1 */
					'preview_order' => class_exists( 'wp_easycart_documents' ) ? wp_easycart_documents::preview_order_id() : 0,
					'lang'          => array(
						'saving'         => __( 'Saving…', 'wp-easycart' ),
						'saved'          => __( 'Saved', 'wp-easycart' ),
						'error'          => __( 'Could not save. Please try again.', 'wp-easycart' ),
						'loading'        => __( 'Loading preview…', 'wp-easycart' ),
						'no_order'       => __( 'The preview appears once the store has an order.', 'wp-easycart' ),
						'preview_failed' => __( 'The preview could not be loaded for that order.', 'wp-easycart' ),
						'reset_title'    => __( 'Reset this profile?', 'wp-easycart' ),
						'reset_body'     => __( 'Every switch goes back to how this profile ships, and it uses the store’s logo and footer image again.', 'wp-easycart' ),
						'reset'          => __( 'Reset', 'wp-easycart' ),
						'cancel'         => __( 'Cancel', 'wp-easycart' ),
						'new_title'      => __( 'New profile', 'wp-easycart' ),
						'new_body'       => __( 'It starts as a copy of the profile you are looking at.', 'wp-easycart' ),
						'name'           => __( 'Name', 'wp-easycart' ),
						'create'         => __( 'Create', 'wp-easycart' ),
						'rename_title'   => __( 'Rename profile', 'wp-easycart' ),
						'rename'         => __( 'Rename', 'wp-easycart' ),
						'delete_title'   => __( 'Delete this profile?', 'wp-easycart' ),
						'delete_body'    => __( 'Anything that used it goes back to the default profile.', 'wp-easycart' ),
						'delete'         => __( 'Delete', 'wp-easycart' ),
						'default'        => __( 'Default', 'wp-easycart' ),
						'made_default'   => __( 'Now the default.', 'wp-easycart' ),
						/* 6.0.1: wording drawer */
						'close'          => __( 'Close', 'wp-easycart' ),
						'loading_words'  => __( 'Loading…', 'wp-easycart' ),
						'wording_title'  => __( 'Wording', 'wp-easycart' ),
						'wording_intro'  => __( 'The headings and phrases this document prints, in your store’s language. A phrase marked "Also on" changes on those documents too.', 'wp-easycart' ),
						'wording_none'   => __( 'This language has none of these phrases.', 'wp-easycart' ),
						'language'       => __( 'Language', 'wp-easycart' ),
						/* translators: %s: other documents that print the same phrase. */
						'shared'         => __( 'Also on %s', 'wp-easycart' ),
						'reset_phrase'   => __( 'Use the original', 'wp-easycart' ),
						'save_wording'   => __( 'Save wording', 'wp-easycart' ),
						'open_editor'    => __( 'Open the language editor', 'wp-easycart' ),
						'discard_title'  => __( 'Discard your changes?', 'wp-easycart' ),
						'discard'        => __( 'Discard', 'wp-easycart' ),
						/* 6.0.1: a profile's own logo and footer image ( the Logo & footer drawer ) */
						'brand_title'    => __( 'Logo and footer image', 'wp-easycart' ),
						'brand_editing'  => __( 'Editing', 'wp-easycart' ),
						'brand_scope'    => __( 'Only this profile changes. Other profiles, and every other email, keep the store’s logo and footer image.', 'wp-easycart' ),
						'brand_is_def'   => __( 'It is the default profile, so it goes out unless another profile is picked when you send.', 'wp-easycart' ),
						'brand_not_def'  => __( 'It goes out when you pick it while sending from an order, or once you make it the default.', 'wp-easycart' ),
						'brand_defaults' => __( 'Manage store defaults', 'wp-easycart' ),
						'brand_def_hint' => __( 'The store’s logo and footer image, and their sizes, are set on Settings › Email › Sender.', 'wp-easycart' ),
						'brand_logo'     => __( 'Logo', 'wp-easycart' ),
						'brand_footer'   => __( 'Footer image', 'wp-easycart' ),
						'brand_off_logo' => __( 'This profile hides the logo.', 'wp-easycart' ),
						'brand_off_foot' => __( 'This profile hides the footer image.', 'wp-easycart' ),
						'brand_show'     => __( 'Show it', 'wp-easycart' ),
						'brand_image'    => __( 'Image', 'wp-easycart' ),
						'brand_st_logo'  => __( 'The store logo', 'wp-easycart' ),
						'brand_st_foot'  => __( 'The store footer image', 'wp-easycart' ),
						'brand_own'      => __( 'A different image for this profile', 'wp-easycart' ),
						'brand_none'     => __( 'None set yet', 'wp-easycart' ),
						'brand_choose'   => __( 'Choose image', 'wp-easycart' ),
						'brand_size'     => __( 'Size', 'wp-easycart' ),
						'brand_st_size'  => __( 'The store size', 'wp-easycart' ),
						'brand_own_sz'   => __( 'Its own size', 'wp-easycart' ),
						/* translators: 1: a width such as 40%, 2: a height such as 80px. */
						'brand_sz_h'     => __( '%1$s wide, up to %2$s tall', 'wp-easycart' ),
						/* translators: %s: a width such as 40%. */
						'brand_sz'       => __( '%s wide, any height', 'wp-easycart' ),
						'brand_width'    => __( 'Width', 'wp-easycart' ),
						'brand_w_hint'   => __( 'Share of the page', 'wp-easycart' ),
						'brand_height'   => __( 'Height limit', 'wp-easycart' ),
						'brand_h_hint'   => __( '0 = no limit', 'wp-easycart' ),
						'brand_position' => __( 'Position', 'wp-easycart' ),
						/* translators: %s: Left, Center or Right. */
						'brand_st_pos'   => __( 'Store ( %s )', 'wp-easycart' ),
						'brand_left'     => __( 'Left', 'wp-easycart' ),
						'brand_center'   => __( 'Center', 'wp-easycart' ),
						'brand_right'    => __( 'Right', 'wp-easycart' ),
						'brand_pdf'      => __( 'This changes the receipt email. The PDF attached to it is the Invoice PDF, which has its own profiles and logo.', 'wp-easycart' ),
						'brand_need_url' => __( 'Choose an image, or use the store’s.', 'wp-easycart' ),
						'brand_custom'   => __( 'This profile has its own logo or footer image', 'wp-easycart' ),
						'brand_locked'   => self::needs_update() ? __( 'A logo and footer image for each profile comes with the WP EasyCart PRO plugin already on this site. Update the plugin to use it.', 'wp-easycart' ) : ( class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::requires_text( __( 'A logo and footer image for each profile', 'wp-easycart' ) ) : __( 'A logo and footer image for each profile needs WP EasyCart PRO.', 'wp-easycart' ) ),
						'brand_unlock'   => self::needs_update() ? __( 'Update WP EasyCart PRO', 'wp-easycart' ) : __( 'Unlock a logo for each profile', 'wp-easycart' ),
						'brand_save'     => __( 'Save for this profile', 'wp-easycart' ),
					),
					/* 6.0.1: what "the store's" means in the Logo & footer drawer ( Settings › Email › Sender ). */
					'store'         => self::store_branding(),
					'email_url'     => admin_url( 'admin.php?page=wp-easycart-settings&subpage=email-setup#ecst-sec-sender' ),
				)
			);
		}

		/**
		 * The store's logo and footer image and their sizes ( Settings › Email › Sender ), for the Logo & footer drawer.
		 *
		 * @since 6.0.1
		 * @return array
		 */
		private static function store_branding() {
			$design = class_exists( 'wp_easycart_email_design' );
			return array(
				'logo_url'      => (string) get_option( 'ec_option_email_logo' ),
				'logo_width'    => $design ? wp_easycart_email_design::logo_number( 'ec_option_email_logo_max_width', 40, 5, 100 ) : 40,
				'logo_height'   => $design ? wp_easycart_email_design::logo_number( 'ec_option_email_logo_max_height', 80, 0, 600 ) : 80,
				'logo_align'    => $design ? wp_easycart_email_design::logo_align() : 'center',
				'footer_url'    => (string) get_option( 'ec_option_email_signature_image' ),
				'footer_width'  => $design ? wp_easycart_email_design::logo_number( 'ec_option_email_signature_image_max_width', 100, 5, 100 ) : 100,
				'footer_height' => $design ? wp_easycart_email_design::logo_number( 'ec_option_email_signature_image_max_height', 0, 0, 600 ) : 0,
			);
		}

		/**
		 * Which document a section edits ( its 'document' key, or its slug ).
		 *
		 * @param array $section Section.
		 * @return string
		 */
		private static function section_type( $section ) {
			if ( ! empty( $section['document'] ) ) {
				return (string) $section['document'];
			}
			return str_replace( '-', '_', isset( $section['slug'] ) ? (string) $section['slug'] : '' );
		}

		/**
		 * A switch that prints a setting ( its 'edit' => array( page, option ) ) links to that row of the settings page:
		 * "Edit", or "Not set yet. Add it" while the setting is empty.
		 *
		 * @since 6.0.1
		 * @param array $def Field.
		 */
		private static function print_edit_link( $def ) {
			if ( empty( $def['edit']['page'] ) || empty( $def['edit']['option'] ) ) {
				return;
			}
			$option = sanitize_key( $def['edit']['option'] );
			$empty  = ( '' === trim( (string) get_option( $option, '' ) ) );
			$url    = admin_url( 'admin.php?page=wp-easycart-settings&subpage=' . rawurlencode( sanitize_key( $def['edit']['page'] ) ) . '&highlight=' . rawurlencode( $option ) );
			echo ' <a class="ecdoc-sw-link' . ( $empty ? ' is-empty' : '' ) . '" href="' . esc_url( $url ) . '" data-edit-link="1">' . esc_html( $empty ? __( 'Not set yet. Add it', 'wp-easycart' ) : __( 'Edit', 'wp-easycart' ) ) . '</a>';
		}

		/**
		 * Section 'render' callable: one document's profile editor.
		 *
		 * @param array $page    Declaration.
		 * @param array $section Section.
		 */
		public static function render_editor( $page, $section ) {
			if ( ! class_exists( 'wp_easycart_documents' ) ) {
				return;
			}
			$type = self::section_type( $section );
			if ( ! wp_easycart_documents::is_type( $type ) ) {
				return;
			}
			$pro      = wp_easycart_documents::pro_enabled();
			/* 6.0.1: a PRO document ( the Invoice PDF ) shows its Standard profile without PRO, read only, as a preview of it. */
			$doc_lock = wp_easycart_documents::is_pro_type( $type ) && ! $pro;
			$default  = wp_easycart_documents::default_id( $type );
			$profiles = array();
			foreach ( wp_easycart_documents::profile_list( $type ) as $id => $info ) {
				$locked          = $info['pro'] && ! $pro;
				$resolved        = $locked ? null : wp_easycart_documents::profile( $type, $id );
				$profiles[ $id ] = array(
					'id'       => $id,
					'name'     => $info['name'],
					'desc'     => $info['desc'],
					'builtin'  => $info['builtin'],
					'locked'   => $locked,
					'fields'   => $resolved ? $resolved['fields'] : null,
					/* 6.0.1: its own logo and footer image ( the Logo & footer drawer ), and its choices ( the heading ). */
					'branding' => $locked ? null : wp_easycart_documents::branding( $type, $id ),
					'options'  => $locked ? null : wp_easycart_documents::profile_options( $type, $id ),
				);
			}
			$data = array(
				'type'     => $type,
				'default'  => $default,
				'profiles' => $profiles,
			);
			$fields = wp_easycart_documents::fields( $type );
			$groups  = wp_easycart_documents::groups();
			$choices = wp_easycart_documents::options( $type );
			$upsell  = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( 'documents', $doc_lock ? 'invoice' : 'profiles' ) : 'return false;';
			?>
			<div class="ecdoc<?php echo $doc_lock ? ' is-locked' : ''; ?>" id="ecdoc_<?php echo esc_attr( $type ); ?>" data-type="<?php echo esc_attr( $type ); ?>"<?php echo $doc_lock ? ' data-locked="1"' : ''; ?>>
				<script type="application/json" class="ecdoc-data"><?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
				<div class="ecdoc-profiles" role="tablist" aria-label="<?php esc_attr_e( 'Profiles', 'wp-easycart' ); ?>">
					<?php foreach ( $profiles as $id => $profile ) : ?>
						<?php if ( $profile['locked'] ) : ?>
							<button type="button" class="ecdoc-tab is-locked" onclick="<?php echo esc_attr( $upsell ); ?>" title="<?php echo esc_attr( $profile['desc'] ); ?>"><?php echo esc_html( $profile['name'] ); ?> <span class="ecst-pro-chip"><?php echo esc_html( self::lock_badge() ); ?></span></button>
						<?php else : ?>
							<button type="button" class="ecdoc-tab<?php echo ( $id === $default ) ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( $id === $default ) ? 'true' : 'false'; ?>" data-profile="<?php echo esc_attr( $id ); ?>"><span class="ecdoc-tab-name"><?php echo esc_html( $profile['name'] ); ?></span><?php if ( $id === $default ) : ?> <span class="ecdoc-default"><?php esc_html_e( 'Default', 'wp-easycart' ); ?></span><?php endif; ?></button>
						<?php endif; ?>
					<?php endforeach; ?>
					<?php if ( $pro ) : ?>
						<button type="button" class="ecdoc-tab ecdoc-add" data-add="1">+ <?php esc_html_e( 'New profile', 'wp-easycart' ); ?></button>
					<?php else : ?>
						<button type="button" class="ecdoc-tab ecdoc-add is-locked" onclick="<?php echo esc_attr( $upsell ); ?>">+ <?php esc_html_e( 'New profile', 'wp-easycart' ); ?> <span class="ecst-pro-chip"><?php echo esc_html( self::lock_badge() ); ?></span></button>
					<?php endif; ?>
				</div>
				<?php
				/* A copy of the template in the data folder keeps rendering as it was written: the switches it predates cannot reach it. */
				$override = wp_easycart_documents::override_path( wp_easycart_documents::template_file( $type ) );
				if ( '' !== $override ) :
					$relative = str_replace( wp_normalize_path( WP_CONTENT_DIR ), 'wp-content', wp_normalize_path( $override ) );
					?>
					<p class="ecdoc-notice" role="note">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: path of the store's copy of the template. */
								__( 'Your store uses its own copy of this template, %s. It keeps working, and the Standard profile still sets the options it reads, but switches added since it was copied do not change it. The preview shows your copy.', 'wp-easycart' ),
								'<code>' . esc_html( $relative ) . '</code>'
							),
							array( 'code' => array() )
						);
						?>
					</p>
				<?php endif; ?>
				<?php
				$print_copy = ( 'invoice' === $type && '' === $override ) ? wp_easycart_documents::override_path( 'ec_account_print_receipt.php' ) : '';
				if ( '' !== $print_copy ) :
					$relative = str_replace( wp_normalize_path( WP_CONTENT_DIR ), 'wp-content', wp_normalize_path( $print_copy ) );
					?>
					<p class="ecdoc-notice" role="note">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: path of the store's copy of the printable receipt template. */
								__( 'Your store has its own copy of the printable receipt, %s. The PDF used to be built from it; it is now built from this document, so check the preview still shows what you need. Print receipt in My Account and on the order screen keeps using your copy.', 'wp-easycart' ),
								'<code>' . esc_html( $relative ) . '</code>'
							),
							array( 'code' => array() )
						);
						?>
					</p>
				<?php endif; ?>
				<div class="ecdoc-body">
					<div class="ecdoc-fields">
						<p class="ecdoc-desc"></p>
						<?php foreach ( $choices as $key => $choice ) : ?>
							<div class="ecdoc-choice" data-option="<?php echo esc_attr( $key ); ?>">
								<span class="ecdoc-sw-text"><b><?php echo esc_html( $choice['label'] ); ?></b><?php if ( ! empty( $choice['desc'] ) ) : ?><small><?php echo esc_html( $choice['desc'] ); ?></small><?php endif; ?></span>
								<span class="ecdoc-pills" role="radiogroup" aria-label="<?php echo esc_attr( $choice['label'] ); ?>">
									<?php foreach ( $choice['choices'] as $value => $label ) : ?>
										<label><input type="radio" name="<?php echo esc_attr( 'ecdoc_' . $type . '_' . $key ); ?>" value="<?php echo esc_attr( $value ); ?>" data-option-key="<?php echo esc_attr( $key ); ?>"<?php disabled( $doc_lock ); ?>><span><?php echo esc_html( $label ); ?></span></label>
									<?php endforeach; ?>
								</span>
							</div>
						<?php endforeach; ?>
						<?php foreach ( $groups as $group => $group_label ) : ?>
							<?php
							$in_group = array_filter(
								$fields,
								function ( $def ) use ( $group ) {
									return isset( $def['group'] ) && $def['group'] === $group;
								}
							);
							if ( ! $in_group ) {
								continue;
							}
							?>
							<div class="ecdoc-group">
								<div class="ecdoc-group-t"><?php echo esc_html( $group_label ); ?></div>
								<?php foreach ( $in_group as $key => $def ) : ?>
									<?php $input_id = 'ecdoc_' . $type . '_' . $key; ?>
									<label class="ecdoc-sw<?php echo ! empty( $def['master'] ) ? ' is-master' : ''; ?><?php echo ! empty( $def['parent'] ) ? ' is-child' : ''; ?>" for="<?php echo esc_attr( $input_id ); ?>"<?php if ( ! empty( $def['parent'] ) ) : ?> data-parent="<?php echo esc_attr( $def['parent'] ); ?>"<?php endif; ?>>
										<span class="ecdoc-sw-text"><b><?php echo esc_html( $def['label'] ); ?></b><?php if ( ! empty( $def['desc'] ) ) : ?><small><?php echo esc_html( $def['desc'] ); ?><?php self::print_edit_link( $def ); ?></small><?php endif; ?></span>
										<input type="checkbox" id="<?php echo esc_attr( $input_id ); ?>" data-key="<?php echo esc_attr( $key ); ?>"<?php disabled( $doc_lock ); ?>>
										<span class="ecdoc-knob" aria-hidden="true"></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
						<div class="ecdoc-actions">
							<span class="ecdoc-status" role="status" aria-live="polite"></span>
							<span class="ecdoc-grow"></span>
							<?php if ( $pro ) : ?>
								<button type="button" class="ecv2-btn ecv2-btn-sm" data-op="default"><?php esc_html_e( 'Make default', 'wp-easycart' ); ?></button>
								<button type="button" class="ecv2-btn ecv2-btn-sm" data-op="rename"><?php esc_html_e( 'Rename', 'wp-easycart' ); ?></button>
								<button type="button" class="ecv2-btn ecv2-btn-sm ecst-btn-danger" data-op="delete"><?php esc_html_e( 'Delete', 'wp-easycart' ); ?></button>
							<?php endif; ?>
							<?php if ( ! $doc_lock ) : ?>
								<button type="button" class="ecv2-btn ecv2-btn-sm" data-op="reset"><?php esc_html_e( 'Reset', 'wp-easycart' ); ?></button>
							<?php endif; ?>
						</div>
					</div>
					<div class="ecdoc-preview">
						<div class="ecdoc-preview-bar">
							<span class="ecdoc-preview-t"><?php esc_html_e( 'Preview', 'wp-easycart' ); ?></span>
							<button type="button" class="ecv2-btn ecv2-btn-sm ecdoc-wording-btn" data-wording="1" data-title="<?php echo esc_attr( wp_strip_all_tags( isset( $section['title'] ) ? (string) $section['title'] : '' ) ); ?>" title="<?php esc_attr_e( 'Change the headings and phrases this document prints, in your store’s language', 'wp-easycart' ); ?>"><span class="dashicons dashicons-editor-textcolor" aria-hidden="true"></span><?php esc_html_e( 'Edit wording', 'wp-easycart' ); ?></button>
							<button type="button" class="ecv2-btn ecv2-btn-sm ecdoc-brand-btn" data-branding="1" data-title="<?php echo esc_attr( wp_strip_all_tags( isset( $section['title'] ) ? (string) $section['title'] : '' ) ); ?>" title="<?php esc_attr_e( 'The logo and footer image this profile uses, and their size', 'wp-easycart' ); ?>"><span class="dashicons dashicons-format-image" aria-hidden="true"></span><?php esc_html_e( 'Logo & footer', 'wp-easycart' ); ?><span class="ecdoc-brand-dot" hidden></span></button>
							<label class="ecdoc-order"><?php esc_html_e( 'Order', 'wp-easycart' ); ?> # <input type="number" min="1" step="1" class="ecv2-input ecdoc-order-id" id="ecdoc_<?php echo esc_attr( $type ); ?>_order" autocomplete="off" value="<?php echo wp_easycart_documents::preview_order_id() ? (int) wp_easycart_documents::preview_order_id() : ''; ?>"></label>
						</div>
						<div class="ecdoc-frame-wrap">
							<iframe class="ecdoc-frame" title="<?php echo esc_attr( sprintf( /* translators: %s: document name. */ __( '%s preview', 'wp-easycart' ), wp_strip_all_tags( isset( $section['title'] ) ? (string) $section['title'] : '' ) ) ); ?>" sandbox="allow-same-origin"></iframe>
							<div class="ecdoc-frame-msg" hidden></div>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * The rows ( emails ) and columns ( PDF documents ) of the attachments grid. PRO renders the working grid from the same list.
		 *
		 * @return array array( 'emails' => key => label/desc, 'documents' => key => label )
		 */
		public static function attachment_grid() {
			return (array) apply_filters(
				'wp_easycart_document_attachment_grid',
				array(
					'emails'    => array(
						'receipt_customer' => array( 'label' => __( 'Order receipt', 'wp-easycart' ), 'desc' => __( 'To the customer, when they pay, and when you resend it', 'wp-easycart' ) ),
						'receipt_admin'    => array( 'label' => __( 'Order receipt, store copy', 'wp-easycart' ), 'desc' => __( 'To your order alert addresses', 'wp-easycart' ) ),
						'shipping'         => array( 'label' => __( 'Order shipped', 'wp-easycart' ), 'desc' => __( 'When an order is marked shipped, or sent from the order', 'wp-easycart' ) ),
						'packing_slip'     => array( 'label' => __( 'Packing slip email', 'wp-easycart' ), 'desc' => __( 'Sent from the order screen', 'wp-easycart' ) ),
					),
					'documents' => array(
						'invoice'      => __( 'Invoice PDF', 'wp-easycart' ),
						'packing_slip' => __( 'Packing slip PDF', 'wp-easycart' ),
					),
				)
			);
		}

		/**
		 * Section 'render' callable without PRO: the grid, locked, so the merchant sees what it does.
		 *
		 * @param array $page    Declaration.
		 * @param array $section Section.
		 */
		/**
		 * Whether the Documents features only need a newer WP EasyCart PRO ( 6.0.1: they said Pro / Premium and offered
		 * the plans to stores that already have one ).
		 *
		 * @since 6.0.1
		 * @return bool
		 */
		private static function needs_update() {
			return class_exists( 'wp_easycart_admin_upsell' ) && '' !== wp_easycart_admin_upsell::update_version( 'documents' );
		}

		/**
		 * Chip text on locked profiles: Update when only a newer WP EasyCart PRO is missing, otherwise the store's plan.
		 *
		 * @since 6.0.1
		 * @return string
		 */
		private static function lock_badge() {
			if ( class_exists( 'wp_easycart_admin_upsell' ) ) {
				return wp_easycart_admin_upsell::badge_for( 'documents' );
			}
			return class_exists( 'wp_easycart_admin_edition' ) ? wp_easycart_admin_edition::badge( 'pro' ) : 'Pro';
		}

		public static function render_attachments_locked( $page, $section ) {
			$grid = self::attachment_grid();
			?>
			<div class="ecdoc-grid-wrap is-locked" aria-disabled="true">
				<table class="ecdoc-grid">
					<thead>
						<tr><th><?php esc_html_e( 'Email', 'wp-easycart' ); ?></th><?php foreach ( $grid['documents'] as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?></tr>
					</thead>
					<tbody>
						<?php foreach ( $grid['emails'] as $email_key => $email ) : ?>
							<tr>
								<th><?php echo esc_html( $email['label'] ); ?><small><?php echo esc_html( $email['desc'] ); ?></small></th>
								<?php foreach ( $grid['documents'] as $doc_key => $label ) : ?>
									<?php
									/* The invoice PDF cells of the receipt rows are the ec_option_pdf_attach_* options, which an older WP EasyCart PRO may have on. */
									$legacy_on = ( 'invoice' === $doc_key && in_array( $email_key, array( 'receipt_customer', 'receipt_admin' ), true ) && get_option( 'receipt_admin' === $email_key ? 'ec_option_pdf_attach_admin' : 'ec_option_pdf_attach_customer', 0 ) );
									?>
									<td><span class="ecdoc-cell<?php echo $legacy_on ? '' : ' is-off'; ?>"><?php echo $legacy_on ? esc_html__( 'Attach', 'wp-easycart' ) : esc_html__( 'Off', 'wp-easycart' ); ?></span></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="ecdoc-grid-note"><?php esc_html_e( 'Attach the invoice or the packing slip as a PDF to any of these emails, each with its own profile, so the buyer’s invoice can show prices while the slip in a gift box does not.', 'wp-easycart' ); ?></p>
			<?php
		}

		/* ------------------------------------------------------------------ */
		/* AJAX                                                                */
		/* ------------------------------------------------------------------ */

		/**
		 * Posted switches: key => 0|1 ( JSON object or form array ).
		 *
		 * @return array
		 */
		private static function posted_fields() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- ecv2_documents_guard() ran first in every caller.
			$raw = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and reduced to key => 0|1 below.
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			if ( is_string( $raw ) ) {
				$raw = json_decode( $raw, true );
			}
			$out = array();
			foreach ( (array) $raw as $key => $value ) {
				$out[ sanitize_key( $key ) ] = ( $value && 'false' !== $value && '0' !== (string) $value ) ? 1 : 0;
			}
			return $out;
		}

		/**
		 * Save a profile's own logo and footer image ( PRO ). POST type, profile, values ( JSON: logo_source, logo_url,
		 * logo_size, logo_width, logo_height, logo_align, footer_source, footer_url, footer_size, footer_width, footer_height ).
		 *
		 * @since 6.0.1
		 */
		public static function ajax_branding_save() {
			ecv2_documents_guard();
			$type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
			$profile = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : '';
			$raw     = isset( $_POST['values'] ) && is_string( $_POST['values'] ) ? json_decode( wp_unslash( $_POST['values'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; wp_easycart_documents::save_branding() keeps known keys only and cleans each ( esc_url_raw, ranges, lists ).
			if ( ! is_array( $raw ) ) {
				wp_send_json_error( array( 'message' => __( 'Nothing to save.', 'wp-easycart' ) ) );
			}
			$values = array();
			foreach ( array( 'logo', 'footer' ) as $part ) {
				foreach ( array( 'source', 'url', 'size', 'width', 'height', 'align' ) as $key ) {
					$name = $part . '_' . $key;
					if ( isset( $raw[ $name ] ) && is_scalar( $raw[ $name ] ) && ( 'align' !== $key || 'logo' === $part ) ) {
						$values[ $name ] = (string) $raw[ $name ];
					}
				}
			}
			$saved = wp_easycart_documents::save_branding( $type, $profile, $values );
			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( array( 'message' => $saved->get_error_message() ) );
			}
			wp_send_json_success(
				array(
					'branding' => $saved,
					'message'  => __( 'Saved', 'wp-easycart' ),
				)
			);
		}

		/** Save one profile's switches. */
		public static function ajax_save() {
			ecv2_documents_guard();
			$type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
			$profile = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : '';
			$result  = wp_easycart_documents::save_profile( $type, $profile, self::posted_fields() );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			/* 6.0.1: the profile's choices ( the Invoice PDF's heading ), when the editor sends them. */
			$raw = isset( $_POST['options'] ) && is_string( $_POST['options'] ) ? json_decode( wp_unslash( $_POST['options'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; save_profile_options() keeps known keys and values only.
			if ( is_array( $raw ) && wp_easycart_documents::options( $type ) ) {
				$options = wp_easycart_documents::save_profile_options( $type, $profile, $raw );
				if ( is_wp_error( $options ) ) {
					wp_send_json_error( array( 'message' => $options->get_error_message() ) );
				}
			}
			$saved = wp_easycart_documents::profile( $type, $profile );
			wp_send_json_success(
				array(
					'fields'  => $saved['fields'],
					'options' => wp_easycart_documents::profile_options( $type, $profile ),
				)
			);
		}

		/** The rendered document for the preview frame, with the editor's unsaved switches. */
		public static function ajax_preview() {
			ecv2_documents_guard();
			/* The preview shows a real order's addresses and items: it needs order access as well as settings access. */
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_orders' ) ) {
				wp_send_json_error( array( 'message' => __( 'Previews use a real order, so they need access to orders.', 'wp-easycart' ) ) );
			}
			$type     = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
			$profile  = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : '';
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			if ( ! wp_easycart_documents::is_type( $type ) ) {
				wp_send_json_error( array( 'message' => __( 'Unknown document.', 'wp-easycart' ) ) );
			}
			if ( ! $order_id ) {
				$order_id = wp_easycart_documents::preview_order_id();
			}
			$html = self::render_preview( $type, $order_id, wp_easycart_documents::resolve( $type, $profile, self::posted_fields() ) );
			if ( '' === $html ) {
				wp_send_json_error( array( 'message' => __( 'The preview could not be loaded for that order.', 'wp-easycart' ) ) );
			}
			wp_send_json_success( array( 'html' => $html ) );
		}

		/**
		 * Any document, rendered the way it goes out, for one order.
		 *
		 * @param string $type     Document type.
		 * @param int    $order_id Order.
		 * @param array  $fields   Resolved switches.
		 * @return string
		 */
		public static function render_preview( $type, $order_id, $fields ) {
			global $wpdb;
			$order_id = (int) $order_id;
			if ( $order_id <= 0 ) {
				return '';
			}
			if ( 'packing_slip' === $type || 'invoice' === $type ) {
				$doc = new wp_easycart_document( $type, $order_id, $fields, array( 'output' => 'preview' ) );
				return $doc->order ? $doc->capture( wp_easycart_documents::locate( wp_easycart_documents::template_file( $type ) ) ) : '';
			}
			if ( 'receipt' === $type ) {
				$db  = new ec_db_admin();
				$row = $db->get_order_row_admin( $order_id );
				if ( ! $row ) {
					return '';
				}
				$display = new ec_orderdisplay( $row, true, true );
				return $display->render_email_receipt( false, $fields );
			}
			if ( 'shipping' === $type && function_exists( 'wp_easycart_admin_orders' ) ) {
				$order = $wpdb->get_row( $wpdb->prepare( 'SELECT tracking_number, shipping_carrier FROM ec_order WHERE order_id = %d', $order_id ) );
				if ( ! $order ) {
					return '';
				}
				return wp_easycart_admin_orders()->render_shipping_email( $order_id, $order->tracking_number, $order->shipping_carrier, array( 'document_fields' => $fields ) );
			}
			return (string) apply_filters( 'wp_easycart_document_preview_' . $type, '', $order_id, $fields );
		}

		/** Profile housekeeping: create, rename, delete, reset, make default. */
		public static function ajax_profile() {
			ecv2_documents_guard();
			$type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
			$profile = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : '';
			$op      = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
			$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			switch ( $op ) {
				case 'create':
					$result = wp_easycart_documents::create_profile( $type, $name, $profile );
					break;
				case 'rename':
					$result = wp_easycart_documents::save_profile( $type, $profile, array(), $name );
					break;
				case 'delete':
					$result = wp_easycart_documents::delete_profile( $type, $profile );
					break;
				case 'reset':
					$result = wp_easycart_documents::reset_profile( $type, $profile );
					break;
				case 'default':
					$result = wp_easycart_documents::set_default( $type, $profile );
					break;
				default:
					$result = new WP_Error( 'op', __( 'Unknown action.', 'wp-easycart' ) );
			}
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			$id       = ( 'create' === $op ) ? (string) $result : $profile;
			$resolved = ( 'delete' === $op ) ? null : wp_easycart_documents::profile( $type, $id );
			$list     = wp_easycart_documents::profile_list( $type );
			wp_send_json_success(
				array(
					'id'       => $id,
					'name'     => isset( $list[ $id ] ) ? $list[ $id ]['name'] : '',
					'fields'   => $resolved ? $resolved['fields'] : null,
					'branding' => $resolved ? wp_easycart_documents::branding( $type, $id ) : null,
					'options'  => $resolved ? wp_easycart_documents::profile_options( $type, $id ) : null,
					'default'  => wp_easycart_documents::default_id( $type ),
				)
			);
		}
	}

	wp_easycart_admin_documents::init();

endif;
