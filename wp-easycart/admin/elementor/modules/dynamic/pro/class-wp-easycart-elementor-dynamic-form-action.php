<?php
/**
 * Elementor Pro form action "Add to WP EasyCart newsletter" ( 6.0.2 ).
 *
 * Adds the visitor to Marketing › Subscribers ( wp_easycart_subscribers::add(), source "elementor", so every mailing list
 * connection hears about it and the consent record keeps when, where and the visitor's address ) only with consent: the form
 * must have an Acceptance field, it must not be ticked in advance, and the visitor must tick it. The cookie banner is never
 * asked ( class-wp-easycart-consent.php: the newsletter box is its own consent to email ). Without consent nothing happens
 * and the form still sends; the action never makes a form fail.
 *
 * Field mapping: Email, First name, Last name and Consent map to the form's fields ( the editor lists only Email fields for
 * Email and only Acceptance fields for Consent ). Left unmapped, the first Email field and the first Acceptance field are
 * used, and fields named first_name / last_name ( or name ) fill the names.
 *
 * Loaded inside elementor_pro/forms/actions/register only ( it extends Elementor Pro's Integration_Base ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Dynamic_Form_Action' ) && class_exists( '\ElementorPro\Modules\Forms\Classes\Integration_Base' ) ) :

	/**
	 * The newsletter form action.
	 */
	class WP_EasyCart_Elementor_Dynamic_Form_Action extends \ElementorPro\Modules\Forms\Classes\Integration_Base {

		/**
		 * Source slug of the sign-ups ( ec_subscriber.source ).
		 */
		const SOURCE = 'elementor';

		/**
		 * Name ( stored in saved forms ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_newsletter';
		}

		/**
		 * Label in Actions After Submit.
		 *
		 * @return string
		 */
		public function get_label() {
			return __( 'Add to WP EasyCart newsletter', 'wp-easycart' );
		}

		/**
		 * The action's settings section in the Form widget.
		 *
		 * @param object $widget The Form widget.
		 */
		public function register_settings_section( $widget ) {
			$widget->start_controls_section(
				'section_wp_easycart_newsletter',
				array(
					'label'     => __( 'WP EasyCart newsletter', 'wp-easycart' ),
					'condition' => array( 'submit_actions' => $this->get_name() ),
				)
			);
			$widget->add_control(
				'wp_easycart_newsletter_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html__( 'Visitors join your newsletter only when they tick the form\'s Acceptance field (for example "Send me news and offers"). Add one and leave "Checked by default" and "Required" off: a box ticked in advance, or one every visitor must tick (such as agreeing to your privacy policy), is not consent, so nobody would be added.', 'wp-easycart' )
						. ' ' . esc_html__( 'Add a Honeypot or reCAPTCHA field to the form too, so bots cannot sign up made-up addresses.', 'wp-easycart' ),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->register_fields_map_control( $widget );
			$widget->end_controls_section();
		}

		/**
		 * The fields to map.
		 *
		 * @return array
		 */
		protected function get_fields_map_control_options() {
			return array(
				'default' => array(
					array(
						'remote_id'       => 'email',
						'remote_label'    => __( 'Email', 'wp-easycart' ),
						'remote_type'     => 'email',
						'remote_required' => true,
					),
					array(
						'remote_id'    => 'first_name',
						'remote_label' => __( 'First name', 'wp-easycart' ),
						'remote_type'  => 'text',
					),
					array(
						'remote_id'    => 'last_name',
						'remote_label' => __( 'Last name', 'wp-easycart' ),
						'remote_type'  => 'text',
					),
					array(
						'remote_id'       => 'consent',
						'remote_label'    => __( 'Consent', 'wp-easycart' ),
						'remote_type'     => 'acceptance',
						'remote_required' => true,
					),
				),
			);
		}

		/**
		 * Nothing to remove on export ( no keys or account data ).
		 *
		 * @param array $element Element data.
		 * @return array
		 */
		public function on_export( $element ) {
			return $element;
		}

		/**
		 * After the form is sent: subscribe the visitor when they gave consent.
		 *
		 * @param object $record       Elementor Pro's form record.
		 * @param object $ajax_handler Elementor Pro's AJAX handler ( unused: the form never fails because of this action ).
		 */
		public function run( $record, $ajax_handler ) {
			unset( $ajax_handler );
			if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) || ! class_exists( 'wp_easycart_subscribers' ) ) {
				return;
			}
			$settings = (array) $record->get( 'form_settings' );
			$fields   = (array) $record->get( 'fields' );
			$map      = $this->field_map( $settings );

			/* A required box ( "I agree to the privacy policy" ) is ticked by every sender: never consent to email. */
			$consent_id = isset( $map['consent'] ) ? $map['consent'] : $this->first_optional_acceptance( $settings, $fields );
			if ( '' === $consent_id || ! isset( $fields[ $consent_id ] ) || 'acceptance' !== $this->field_value( $fields, $consent_id, 'type' ) || $this->is_required( $settings, $consent_id ) ) {
				return; /* no consent box: never subscribe */
			}
			if ( '' === $this->field_value( $fields, $consent_id, 'value' ) || $this->ticked_in_advance( $settings, $consent_id ) ) {
				return; /* not ticked, or ticked for the visitor */
			}

			$email_id = isset( $map['email'] ) ? $map['email'] : $this->first_of_type( $fields, 'email' );
			$email    = sanitize_email( $this->field_value( $fields, $email_id, 'value' ) );
			if ( '' === $email || ! is_email( $email ) ) {
				return;
			}
			list( $first, $last ) = $this->names( $fields, $map );

			/**
			 * Whether an Elementor form sign-up joins the newsletter ( consent was already checked ).
			 *
			 * @since 6.0.2
			 *
			 * @param bool   $subscribe Default true.
			 * @param string $email     Address.
			 * @param object $record    Elementor Pro's form record.
			 */
			if ( ! apply_filters( 'wp_easycart_elementor_form_subscribe', true, $email, $record ) ) {
				return;
			}

			/* A source passed to add() normally keeps no visitor address ( imports, webhooks ); this sign-up is the visitor's own. */
			$keep_ip = function ( $ip, $source = '' ) {
				if ( self::SOURCE === $source && '' === (string) $ip ) {
					return wp_easycart_subscribers::client_ip();
				}
				return $ip;
			};
			add_filter( 'wp_easycart_subscriber_ip', $keep_ip, 10, 2 );
			try {
				wp_easycart_subscribers::add( $email, $first, $last, self::SOURCE );
			} finally {
				remove_filter( 'wp_easycart_subscriber_ip', $keep_ip, 10 );
			}
		}

		/**
		 * The saved field mapping: remote id => form field id.
		 *
		 * @param array $settings Form settings.
		 * @return array
		 */
		private function field_map( $settings ) {
			$key = $this->get_name() . '_fields_map';
			$map = array();
			if ( empty( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				return $map;
			}
			foreach ( $settings[ $key ] as $item ) {
				if ( is_array( $item ) && ! empty( $item['remote_id'] ) && ! empty( $item['local_id'] ) && is_string( $item['local_id'] ) ) {
					$map[ (string) $item['remote_id'] ] = $item['local_id'];
				}
			}
			return $map;
		}

		/**
		 * The first field of a type ( '' when there is none ).
		 *
		 * @param array  $fields Record fields.
		 * @param string $type   Field type.
		 * @return string
		 */
		private function first_of_type( $fields, $type ) {
			foreach ( $fields as $id => $field ) {
				if ( is_array( $field ) && isset( $field['type'] ) && $type === $field['type'] ) {
					return (string) $id;
				}
			}
			return '';
		}

		/**
		 * One property of a field as a string.
		 *
		 * @param array  $fields   Record fields.
		 * @param string $id       Field id.
		 * @param string $property Property.
		 * @return string
		 */
		private function field_value( $fields, $id, $property ) {
			if ( '' === (string) $id || ! isset( $fields[ $id ] ) || ! is_array( $fields[ $id ] ) || ! isset( $fields[ $id ][ $property ] ) ) {
				return '';
			}
			$value = $fields[ $id ][ $property ];
			return is_scalar( $value ) ? trim( (string) $value ) : '';
		}

		/**
		 * The first Acceptance field a visitor may leave unticked.
		 *
		 * @param array $settings Form settings.
		 * @param array $fields   Record fields.
		 * @return string Field id, or ''.
		 */
		private function first_optional_acceptance( $settings, $fields ) {
			foreach ( $fields as $id => $field ) {
				if ( is_array( $field ) && isset( $field['type'] ) && 'acceptance' === $field['type'] && ! $this->is_required( $settings, (string) $id ) ) {
					return (string) $id;
				}
			}
			return '';
		}

		/**
		 * Whether a field must be filled in ( ticked, for an Acceptance field ) before the form sends.
		 *
		 * @param array  $settings Form settings.
		 * @param string $id       Field id.
		 * @return bool
		 */
		private function is_required( $settings, $id ) {
			foreach ( isset( $settings['form_fields'] ) ? (array) $settings['form_fields'] : array() as $field ) {
				if ( is_array( $field ) && isset( $field['custom_id'] ) && (string) $id === (string) $field['custom_id'] ) {
					return ! empty( $field['required'] ) && 'false' !== (string) $field['required'];
				}
			}
			return false;
		}

		/**
		 * Whether the consent box is set to be ticked when the form loads.
		 *
		 * @param array  $settings Form settings.
		 * @param string $id       Field id.
		 * @return bool
		 */
		private function ticked_in_advance( $settings, $id ) {
			foreach ( isset( $settings['form_fields'] ) ? (array) $settings['form_fields'] : array() as $field ) {
				if ( is_array( $field ) && isset( $field['custom_id'] ) && (string) $id === (string) $field['custom_id'] ) {
					return ! empty( $field['checked_by_default'] );
				}
			}
			return false;
		}

		/**
		 * First and last name: the mapped fields, else fields named first_name / last_name, else a name field split in two.
		 *
		 * @param array $fields Record fields.
		 * @param array $map    Field mapping.
		 * @return array First, last.
		 */
		private function names( $fields, $map ) {
			$first = isset( $map['first_name'] ) ? $this->field_value( $fields, $map['first_name'], 'value' ) : '';
			$last  = isset( $map['last_name'] ) ? $this->field_value( $fields, $map['last_name'], 'value' ) : '';
			if ( '' === $first && ! isset( $map['first_name'] ) ) {
				foreach ( array( 'first_name', 'firstname', 'fname' ) as $id ) {
					$first = $this->field_value( $fields, $id, 'value' );
					if ( '' !== $first ) {
						break;
					}
				}
			}
			if ( '' === $last && ! isset( $map['last_name'] ) ) {
				foreach ( array( 'last_name', 'lastname', 'lname' ) as $id ) {
					$last = $this->field_value( $fields, $id, 'value' );
					if ( '' !== $last ) {
						break;
					}
				}
			}
			if ( '' === $first && '' === $last && ! isset( $map['first_name'] ) ) {
				$full = $this->field_value( $fields, 'name', 'value' );
				if ( '' !== $full ) {
					$parts = preg_split( '/\s+/', $full, 2 );
					$first = $parts[0];
					$last  = isset( $parts[1] ) ? $parts[1] : '';
				}
			}
			return array( sanitize_text_field( $first ), sanitize_text_field( $last ) );
		}
	}

endif;
