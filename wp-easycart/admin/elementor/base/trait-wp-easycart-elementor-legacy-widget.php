<?php
/**
 * What the 25 WP EasyCart widgets from before 6.0.2 share ( 6.0.2 ).
 *
 * Every legacy widget class uses this trait and calls ec_legacy_register_notice() first thing in register_controls().
 *
 * - Retiring: a module maps a legacy widget to its replacement through the filter wp_easycart_elementor_retired_widgets.
 *   Once that replacement is registered, the legacy widget leaves the panel and panel search ( show_in_panel() false,
 *   hide_on_search() true ) but stays registered, so saved pages keep drawing it exactly as before. Its Content tab then
 *   opens with a note and a "Replace with <title>" button ( editor script admin/elementor/assets/wpeasycart-elementor-editor.js,
 *   AJAX ecv2_elementor_replace_widget ), which asks first and is never automatic.
 * - The button names the widget the map actually inserts: a map may return '_wpec_replacement' ( a slider
 *   Products widget becomes a Product Carousel, an Account Forms widget the part for its form ). Panel labels are the same
 *   for every copy of a widget, so where one control decides the replacement the note and button come once per outcome,
 *   each shown by an Elementor condition on that control. Which control and which values: ec_legacy_variant_control()
 *   ( the three legacy widgets whose maps split, or an entry's own 'title_for' ); each value's title comes from running
 *   the map on it and asking Elementor for the inserted widget's title, so the label always follows the map.
 * - Plain content: render_plain_content() prints the shortcode the widget's display file draws with, so post_content holds
 *   working shortcodes instead of stale store HTML. The account widgets and the Cart Icon keep their own empty
 *   render_plain_content() ( a class method wins over the trait ).
 * - Assets: the store stylesheet as a style dependency ( ec-store.css ). Each class lists its scripts itself.
 *
 * Control IDs added here all start with `ec_legacy_`: they are panel-only ( no setting a saved page depends on ), and the
 * contract harness ignores them. Never add an `ec_legacy_` control that changes how a widget draws.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( 'WP_EasyCart_Elementor_Legacy_Widget' ) ) :

	/**
	 * Retirement, plain content and assets for the legacy WP EasyCart widgets.
	 */
	trait WP_EasyCart_Elementor_Legacy_Widget {

		/**
		 * Listed in the panel until a registered replacement exists.
		 *
		 * @return bool
		 */
		public function show_in_panel(): bool {
			return ! $this->ec_legacy_is_retired();
		}

		/**
		 * Left out of the panel search once retired.
		 *
		 * @return bool
		 */
		public function hide_on_search(): bool {
			return $this->ec_legacy_is_retired();
		}

		/**
		 * The store stylesheet ( registered on every page by WP_EasyCart_Elementor::register_assets() ), while EasyCart loads
		 * it ( wp_easycart_load_css_scripts ).
		 *
		 * @return array
		 */
		public function get_style_depends() {
			return $this->ec_legacy_assets( 'css', array( 'wpeasycart_css' ) );
		}

		/**
		 * The store's handles for this widget, or none while a filter keeps EasyCart's script ( js ) or stylesheet ( css ) off.
		 *
		 * @param string $kind    js | css.
		 * @param array  $handles Handles.
		 * @return array
		 */
		protected function ec_legacy_assets( $kind, $handles ) {
			return function_exists( 'wp_easycart_elementor_store_assets' ) ? wp_easycart_elementor_store_assets( $kind, $handles ) : $handles;
		}

		/**
		 * Whether a registered newer widget replaces this one.
		 *
		 * @return bool
		 */
		protected function ec_legacy_is_retired() {
			return function_exists( 'wp_easycart_elementor_retired_replacement' ) && '' !== wp_easycart_elementor_retired_replacement( $this->get_name() );
		}

		/**
		 * Saved into post_content on every editor save: the shortcode this widget draws with ( one per line ), or nothing.
		 */
		public function render_plain_content() {
			if ( ! function_exists( 'wp_easycart_elementor_capture_shortcodes' ) ) {
				return;
			}
			$widget = $this;
			$plain  = wp_easycart_elementor_capture_shortcodes(
				function () use ( $widget ) {
					$widget->ec_legacy_render_for_capture();
				}
			);
			if ( '' !== $plain ) {
				echo $plain; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcodes built by wp_easycart_elementor_shortcode_atts() from sanitized settings; Elementor strips tags from plain content.
			}
		}

		/**
		 * Draws the widget for wp_easycart_elementor_capture_shortcodes() ( render() is protected ).
		 *
		 * @internal
		 */
		public function ec_legacy_render_for_capture() {
			$this->render();
		}

		/**
		 * The "newer version available" note and Replace button, first in the Content tab. Call first in register_controls().
		 *
		 * One note and button per widget the map can insert ( see ec_legacy_replace_variants() ): the first pair keeps the IDs
		 * ec_legacy_note / ec_legacy_replace, the others get a number ( ec_legacy_note_1, ec_legacy_replace_1 … ). Each pair
		 * shows only while its condition holds, so the panel always names the widget the button inserts.
		 */
		protected function ec_legacy_register_notice() {
			if ( ! function_exists( 'wp_easycart_elementor_retired_widgets' ) || ! $this->ec_legacy_is_retired() ) {
				return;
			}
			$variants = $this->ec_legacy_replace_variants();

			$this->start_controls_section(
				'ec_legacy_section',
				array(
					'label' => __( 'Newer widget available', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			foreach ( $variants as $index => $variant ) {
				$suffix = ( 0 === $index ) ? '' : '_' . $index;
				if ( ! empty( $variant['refusal'] ) ) {
					/* Settings no newer widget covers ( the map refuses them ): say why, and offer no button. */
					$this->add_control(
						'ec_legacy_note' . $suffix,
						array(
							'type'            => \Elementor\Controls_Manager::RAW_HTML,
							'raw'             => esc_html( $variant['refusal'] ),
							'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
							'condition'       => $variant['condition'],
						)
					);
					continue;
				}
				$note = array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => '<strong>' . esc_html__( 'A newer version of this widget is available.', 'wp-easycart' ) . '</strong><br>' . esc_html(
						sprintf(
							/* translators: %s: title of the newer widget. */
							__( '%s is easier to set up and follows your site\'s colours and fonts. This widget keeps working on your pages until you replace it; replacing it copies your choices where the new widget has them, and Undo brings it back.', 'wp-easycart' ),
							$variant['title']
						)
					),
					'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				);
				$button = array(
					'type'        => \Elementor\Controls_Manager::BUTTON,
					'label'       => '',
					'show_label'  => false,
					/* translators: %s: title of the newer widget. */
					'text'        => sprintf( esc_html__( 'Replace with %s', 'wp-easycart' ), esc_html( $variant['title'] ) ), /* Elementor prints it unescaped */
					'button_type' => 'default',
					'event'       => 'wpeasycart:replaceLegacyWidget',
					'wpec_title'  => $variant['title'], /* read by the editor script's confirmation */
					'separator'   => 'before',
				);
				if ( ! empty( $variant['condition'] ) ) {
					$note['condition']   = $variant['condition'];
					$button['condition'] = $variant['condition'];
				}
				$this->add_control( 'ec_legacy_note' . $suffix, $note );
				$this->add_control( 'ec_legacy_replace' . $suffix, $button );
			}
			$this->end_controls_section();
		}

		/**
		 * The Replace buttons this widget needs: array( array( 'title', 'condition' ) … ). The first names what the map
		 * inserts for the widget's default settings and shows for every value no other button claims. Just that one, with no
		 * condition, when the map always inserts the same widget ( most legacy widgets ). Values the map refuses ( a
		 * WP_Error 'wpec_elementor_no_replacement', e.g. the account Messages box ) get array( 'refusal' => why,
		 * 'condition' ) instead: a note and no button.
		 *
		 * @return array
		 */
		protected function ec_legacy_replace_variants() {
			$name    = $this->get_name();
			$retired = wp_easycart_elementor_retired_widgets();
			$entry   = $retired[ $name ];
			$base    = $this->ec_legacy_inserted_title( array(), $entry );
			$control = $this->ec_legacy_variant_control();
			if ( ! $control ) {
				return array(
					array(
						'title'     => $base,
						'condition' => array(),
					),
				);
			}
			/* Values whose replacement differs from the default one, grouped by the widget they insert ( or the refusal ). */
			$groups   = array();
			$refusals = array();
			foreach ( $control['values'] as $value => $title ) {
				$value   = (string) $value;
				$refusal = $this->ec_legacy_refusal( array( $control['id'] => $value ) );
				if ( '' !== $refusal ) {
					$refusals[ $refusal ][] = $value;
					continue;
				}
				if ( '' === $title ) {
					$title = $this->ec_legacy_inserted_title( array( $control['id'] => $value ), $entry );
				}
				if ( $title === $base ) {
					continue;
				}
				$groups[ $title ][] = $value;
			}
			if ( empty( $groups ) && empty( $refusals ) ) {
				return array(
					array(
						'title'     => $base,
						'condition' => array(),
					),
				);
			}
			$claimed  = array();
			$variants = array();
			foreach ( $groups as $title => $values ) {
				$claimed    = array_merge( $claimed, $values );
				$variants[] = array(
					'title'     => (string) $title,
					'condition' => array( $control['id'] => $values ),
				);
			}
			foreach ( $refusals as $refusal => $values ) {
				$claimed    = array_merge( $claimed, $values );
				$variants[] = array(
					'title'     => '',
					'refusal'   => (string) $refusal,
					'condition' => array( $control['id'] => $values ),
				);
			}
			array_unshift(
				$variants,
				array(
					'title'     => $base,
					'condition' => array( $control['id'] . '!' => $claimed ),
				)
			);
			return $variants;
		}

		/**
		 * The control whose value picks the replacement, as array( 'id' => control ID, 'values' => value => title ( '' = ask
		 * the map ) ), or null.
		 *
		 * An entry on wp_easycart_elementor_retired_widgets may say it with 'title_for': array( '<control id>' => array(
		 * '<value>', … ) ) ( titles from the map ), array( '<control id>' => array( '<value>' => '<title>', … ) ), or a
		 * callable returning either. Otherwise the three legacy widgets whose maps split ( known here, since their controls
		 * never change ): the Products layout, the Account Forms form and the Account Dashboard element.
		 *
		 * @return array|null
		 */
		protected function ec_legacy_variant_control() {
			$name = $this->get_name();
			$raw  = apply_filters( 'wp_easycart_elementor_retired_widgets', array() );
			$spec = ( is_array( $raw ) && isset( $raw[ $name ]['title_for'] ) ) ? $raw[ $name ]['title_for'] : null;
			if ( is_callable( $spec ) ) {
				$spec = call_user_func( $spec );
			}
			if ( ! is_array( $spec ) || empty( $spec ) ) {
				$known = array(
					'wp_easycart_product'           => array( 'layout_mode' => array( 'grid', 'slider' ) ),
					'wp_easycart_account_forms'     => array( 'form_type' => array( 'login', 'register', 'forgot-password', 'billing', 'shipping', 'personal', 'password', 'connect-order' ) ),
					'wp_easycart_account_dashboard' => array( 'dashboard_type' => array( 'messages', 'recent-orders', 'subscriptions', 'downloads', 'email', 'billing', 'shipping' ) ),
				);
				$spec  = isset( $known[ $name ] ) ? $known[ $name ] : null;
			}
			if ( ! is_array( $spec ) || empty( $spec ) ) {
				return null;
			}
			$id     = (string) key( $spec );
			$listed = current( $spec );
			if ( '' === $id || sanitize_key( $id ) !== $id || ! is_array( $listed ) || empty( $listed ) ) {
				return null;
			}
			$values = array();
			foreach ( $listed as $key => $item ) {
				if ( is_int( $key ) && is_scalar( $item ) ) {
					$values[ (string) $item ] = '';
				} elseif ( is_scalar( $item ) ) {
					$values[ (string) $key ] = (string) $item;
				}
			}
			return empty( $values ) ? null : array(
				'id'     => $id,
				'values' => $values,
			);
		}

		/**
		 * Why no newer widget can take these saved settings, or '' ( only the map's own 'wpec_elementor_no_replacement'
		 * answer counts; a map that failed still shows the button, whose request reports the error ).
		 *
		 * @param array $settings Saved settings ( only what differs from the defaults ).
		 * @return string
		 */
		protected function ec_legacy_refusal( $settings ) {
			if ( ! function_exists( 'wp_easycart_elementor_map_legacy_settings' ) ) {
				return '';
			}
			$result = wp_easycart_elementor_map_legacy_settings( $this->get_name(), $settings );
			if ( is_wp_error( $result ) && 'wpec_elementor_no_replacement' === $result->get_error_code() ) {
				return (string) $result->get_error_message();
			}
			return '';
		}

		/**
		 * The title of the widget Replace inserts for these saved settings ( the map decides, Elementor names it ).
		 *
		 * @param array $settings Saved settings ( only what differs from the defaults ).
		 * @param array $entry    The retired entry ( replacement, title, map ).
		 * @return string
		 */
		protected function ec_legacy_inserted_title( $settings, $entry ) {
			if ( ! function_exists( 'wp_easycart_elementor_map_legacy_settings' ) ) {
				return $entry['title'];
			}
			$result = wp_easycart_elementor_map_legacy_settings( $this->get_name(), $settings );
			if ( is_wp_error( $result ) || ! is_array( $result ) || empty( $result['replacement'] ) || $result['replacement'] === $entry['replacement'] ) {
				return $entry['title'];
			}
			/* Another widget: its own title from Elementor's registry ( the same source the Replace request answers with ). */
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->widgets_manager ) && is_object( \Elementor\Plugin::$instance->widgets_manager ) && method_exists( \Elementor\Plugin::$instance->widgets_manager, 'get_widget_types' ) ) {
				$type = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $result['replacement'] );
				if ( is_object( $type ) && method_exists( $type, 'get_title' ) ) {
					$title = trim( wp_strip_all_tags( (string) $type->get_title() ) );
					if ( '' !== $title ) {
						return $title;
					}
				}
			}
			return $entry['title'];
		}
	}

endif;
