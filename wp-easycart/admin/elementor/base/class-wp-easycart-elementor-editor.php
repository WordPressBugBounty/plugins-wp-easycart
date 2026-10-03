<?php
/**
 * WP EasyCart inside the Elementor editor ( 6.0.2 ).
 *
 * Loaded on every request with the integration ( no Elementor dependency until its hooks fire ):
 * - the editor script admin/elementor/assets/wpeasycart-elementor-editor.js ( the "Replace with …" button of retired
 *   widgets, "Show in preview" for the document's preview product and category );
 * - AJAX ecv2_elementor_replace_widget: the new widget's settings for a retired widget's saved settings, through the map a
 *   module registered on wp_easycart_elementor_retired_widgets ( guard ecv2_elementor_guard(): nonce + edit_posts );
 * - the shared stylesheet in the preview, so editor notes ( .wpec-el-notice ) look right on every widget.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Editor' ) ) :

	/**
	 * Editor script, preview styles and the widget replacement request.
	 */
	class WP_EasyCart_Elementor_Editor {

		/**
		 * Hooks ( once ).
		 */
		public static function init() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue_editor' ) );
			add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'enqueue_preview' ) );
			add_action( 'wp_ajax_ecv2_elementor_replace_widget', array( __CLASS__, 'ajax_replace_widget' ) );
		}

		/**
		 * The editor panel's script.
		 */
		public static function enqueue_editor() {
			wp_enqueue_script(
				'wpeasycart-elementor-editor',
				plugins_url( 'admin/elementor/assets/wpeasycart-elementor-editor.js', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ),
				array( 'jquery' ),
				EC_CURRENT_VERSION,
				true
			);
			wp_localize_script(
				'wpeasycart-elementor-editor',
				'wpeasycartElementorEditor',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wp-easycart-ecv2-elementor' ),
					'i18n'    => array(
						'replaceTitle'   => __( 'Replace this widget?', 'wp-easycart' ),
						/* translators: %s: title of the newer widget. */
						'replaceMessage' => __( 'This puts %s in its place, with your choices copied where the new widget has them. You can undo it.', 'wp-easycart' ),
						'replace'        => __( 'Replace', 'wp-easycart' ),
						'cancel'         => __( 'Cancel', 'wp-easycart' ),
						'failed'         => __( 'The widget could not be replaced. Reload the editor and try again.', 'wp-easycart' ),
						'working'        => __( 'Replacing…', 'wp-easycart' ),
					),
				)
			);
		}

		/**
		 * The shared stylesheet inside the preview ( editor notes, locked widgets ).
		 */
		public static function enqueue_preview() {
			if ( wp_style_is( 'wpeasycart-elementor', 'registered' ) ) {
				wp_enqueue_style( 'wpeasycart-elementor' );
			}
		}

		/**
		 * AJAX ecv2_elementor_replace_widget: widget ( a retired widget's name ) + settings ( its saved settings, JSON ) →
		 * { replacement, title, settings } for the editor to insert. Nothing is written here.
		 */
		public static function ajax_replace_widget() {
			ecv2_elementor_guard();
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in ecv2_elementor_guard().
			$widget   = isset( $_POST['widget'] ) ? sanitize_key( wp_unslash( $_POST['widget'] ) ) : '';
			$settings = array();
			if ( isset( $_POST['settings'] ) ) {
				$decoded  = json_decode( wp_unslash( $_POST['settings'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON of the editor's own widget settings, handed only to the module's map; the editor applies the result as widget settings.
				$settings = is_array( $decoded ) ? $decoded : array();
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			if ( '' === $widget || ! function_exists( 'wp_easycart_elementor_map_legacy_settings' ) ) {
				wp_send_json_error( array( 'message' => __( 'This widget has no newer version yet.', 'wp-easycart' ) ) );
			}
			$result = wp_easycart_elementor_map_legacy_settings( $widget, $settings );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			/* The replacement must exist for Elementor in this request too ( a module switched off, an older WP EasyCart PRO ). */
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->widgets_manager ) && method_exists( \Elementor\Plugin::$instance->widgets_manager, 'get_widget_types' ) ) {
				$type = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $result['replacement'] );
				if ( ! $type ) {
					wp_send_json_error( array( 'message' => __( 'The newer widget is not available on this site.', 'wp-easycart' ) ) );
				}
				if ( is_object( $type ) && method_exists( $type, 'get_title' ) ) {
					$result['title'] = wp_strip_all_tags( (string) $type->get_title() );
				}
			}
			/* An empty PHP array would reach the editor as [] ( a list ); widget settings are always an object. */
			$result['settings'] = (object) $result['settings'];
			wp_send_json_success( $result );
		}
	}

	WP_EasyCart_Elementor_Editor::init();

endif;
