<?php
/**
 * WP EasyCart Elementor module: Limit Access in the page's own settings ( 6.0.5, D1 ).
 *
 * A "WP EasyCart Limit Access" section in the Settings tab of every Elementor document whose post can be limited
 * ( wp_easycart_page_access::post_types() ), for store administrators and store managers. It edits the same rule as the WordPress
 * editor's box and Users › Limit Access ( wp_easycart_page_access::save_rule() ); customers chosen one by one and where members are sent
 * stay as they are and are set there. The controls start from the saved rule ( their defaults ); after Elementor saves, the rule is
 * saved and the section's values are taken back out of _elementor_page_settings, so the panel always shows the rule as it is.
 *
 * Loaded on plugins_loaded by WP_EasyCart_Elementor::load_modules(); nothing is translated until Elementor asks for the controls.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Access' ) ) :

	/**
	 * Limit Access in Elementor's page settings.
	 */
	final class WP_EasyCart_Elementor_Access {

		/** The section's control ids ( saved into the rule, never kept in _elementor_page_settings ). */
		const KEYS = array(
			'wpec_access_on',
			'wpec_access_signed_in',
			'wpec_access_products',
			'wpec_access_roles',
			'wpec_access_match',
			'wpec_access_signed_out',
			'wpec_access_signed_out_page',
			'wpec_access_signed_out_url',
			'wpec_access_no_access',
			'wpec_access_no_access_page',
			'wpec_access_no_access_url',
			'wpec_access_external_ok',
		);

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'elementor/documents/register_controls', array( __CLASS__, 'register_controls' ), 20 );
			add_action( 'elementor/document/after_save', array( __CLASS__, 'after_save' ), 10, 2 );
		}

		/**
		 * The post a document is about, when it can be limited and the current user may set Limit Access ( 0 otherwise ).
		 *
		 * @param object $document Elementor document.
		 * @return int
		 */
		public static function post_id( $document ) {
			if ( ! class_exists( 'wp_easycart_page_access' ) || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
				return 0;
			}
			if ( is_a( $document, '\Elementor\Core\Kits\Documents\Kit' ) ) {
				return 0; /* Site Settings */
			}
			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_manager' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own role.
				return 0;
			}
			$post_id = (int) $document->get_main_id();
			if ( $post_id <= 0 || ! in_array( get_post_type( $post_id ), wp_easycart_page_access::post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
				return 0;
			}
			return $post_id;
		}

		/**
		 * The rule as the controls show it ( a page set up before 6.0.5 keeps "all of these" and its addresses; no rule = the defaults ).
		 *
		 * @param int $post_id Post.
		 * @return array
		 */
		private static function shown_rule( $post_id ) {
			$rule = wp_easycart_page_access::rule( $post_id );
			if ( empty( $rule['legacy'] ) ) {
				return $rule;
			}
			if ( ! wp_easycart_page_access::restricted( $rule ) ) {
				return wp_easycart_page_access::defaults();
			}
			if ( empty( $rule['on'] ) ) {
				$rule['signed_out'] = array(
					'to'   => 'sign_in',
					'page' => 0,
					'url'  => '',
				);
				$rule['no_access']  = array(
					'to'   => 'message',
					'page' => 0,
					'url'  => '',
				);
			} else {
				$rule['external_ok'] = true;
			}
			return $rule;
		}

		/**
		 * The section.
		 *
		 * @param object $document Elementor document.
		 */
		public static function register_controls( $document ) {
			$post_id = self::post_id( $document );
			if ( ! $post_id || ! method_exists( $document, 'start_controls_section' ) || ! class_exists( '\Elementor\Controls_Manager' ) ) {
				return;
			}
			$rule  = self::shown_rule( $post_id );
			$on    = array( 'wpec_access_on' => 'yes' );
			$roles = array();
			foreach ( array_unique( array_merge( wp_easycart_page_access::role_labels(), $rule['roles'] ) ) as $label ) {
				$roles[ $label ] = $label;
			}
			$document->start_controls_section(
				'wpec_access_section',
				array(
					'label' => __( 'WP EasyCart Limit Access', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
				)
			);
			$document->add_control(
				'wpec_access_on',
				array(
					'label'        => __( 'Limit who can see this page', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => ! empty( $rule['on'] ) ? 'yes' : '',
				)
			);
			$document->add_control(
				'wpec_access_signed_in',
				array(
					'label'        => __( 'Any signed-in customer', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => ! empty( $rule['signed_in'] ) ? 'yes' : '',
					'condition'    => $on,
					'separator'    => 'before',
				)
			);
			$document->add_control(
				'wpec_access_products',
				array(
					'label'       => __( 'Customers who bought', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product',
					'label_block' => true,
					'multiple'    => true,
					'default'     => array_map( 'strval', $rule['products'] ),
					'description' => __( 'Subscriptions while they run, installment plans once paid in full, anything else once paid.', 'wp-easycart' ),
					'condition'   => $on,
				)
			);
			if ( ! empty( $roles ) ) {
				$document->add_control(
					'wpec_access_roles',
					array(
						'label'       => __( 'Customers with a role', 'wp-easycart' ),
						'type'        => \Elementor\Controls_Manager::SELECT2,
						'options'     => $roles,
						'multiple'    => true,
						'label_block' => true,
						'default'     => $rule['roles'],
						'condition'   => $on,
					)
				);
			}
			$document->add_control(
				'wpec_access_match',
				array(
					'label'     => __( 'Customers need', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'options'   => array(
						'any' => __( 'Any one of these', 'wp-easycart' ),
						'all' => __( 'All of these', 'wp-easycart' ),
					),
					'default'   => 'all' === $rule['match'] ? 'all' : 'any',
					'condition' => $on,
				)
			);
			$destinations = array(
				'signed_out' => array(
					'label'   => __( 'Signed-out visitors', 'wp-easycart' ),
					'options' => array(
						'sign_in' => __( 'Sign in, then come back here', 'wp-easycart' ),
						'page'    => __( 'A page on this site', 'wp-easycart' ),
						'url'     => __( 'Another address', 'wp-easycart' ),
					),
				),
				'no_access'  => array(
					'label'   => __( 'Signed-in customers without access', 'wp-easycart' ),
					'options' => array(
						'message' => __( 'Show what unlocks this page', 'wp-easycart' ),
						'product' => __( 'The page of the product that unlocks it', 'wp-easycart' ),
						'store'   => __( 'The store page', 'wp-easycart' ),
						'page'    => __( 'A page on this site', 'wp-easycart' ),
						'url'     => __( 'Another address', 'wp-easycart' ),
					),
				),
			);
			foreach ( $destinations as $which => $choice ) {
				$target = $rule[ $which ];
				$to     = isset( $choice['options'][ $target['to'] ] ) ? $target['to'] : key( $choice['options'] );
				$document->add_control(
					'wpec_access_' . $which,
					array(
						'label'       => $choice['label'],
						'type'        => \Elementor\Controls_Manager::SELECT,
						'options'     => $choice['options'],
						'default'     => $to,
						'label_block' => true,
						'condition'   => $on,
						'separator'   => 'before',
					)
				);
				$document->add_control(
					'wpec_access_' . $which . '_page',
					array(
						'label'       => __( 'Page', 'wp-easycart' ),
						'type'        => 'wpecajaxselect2',
						'options'     => 'easycart_page',
						'label_block' => true,
						'multiple'    => false,
						'default'     => (int) $target['page'] > 0 ? (string) (int) $target['page'] : '',
						'condition'   => array(
							'wpec_access_on'        => 'yes',
							'wpec_access_' . $which => 'page',
						),
					)
				);
				$document->add_control(
					'wpec_access_' . $which . '_url',
					array(
						'label'       => __( 'Address', 'wp-easycart' ),
						'type'        => \Elementor\Controls_Manager::TEXT,
						'placeholder' => __( 'https://… or /join', 'wp-easycart' ),
						'default'     => (string) $target['url'],
						'label_block' => true,
						'condition'   => array(
							'wpec_access_on'        => 'yes',
							'wpec_access_' . $which => 'url',
						),
					)
				);
			}
			$document->add_control(
				'wpec_access_external_ok',
				array(
					'label'        => __( 'Send visitors to another site', 'wp-easycart' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'return_value' => 'yes',
					'default'      => ! empty( $rule['external_ok'] ) ? 'yes' : '',
					'condition'    => $on,
				)
			);
			$document->add_control(
				'wpec_access_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => self::note_html( $post_id, $rule ),
					'content_classes' => 'elementor-descriptor',
					'separator'       => 'before',
				)
			);
			$document->end_controls_section();
		}

		/**
		 * The saved rule in words, its problems, and where the rest of it is set.
		 *
		 * @param int   $post_id Post.
		 * @param array $rule    Rule.
		 * @return string HTML.
		 */
		private static function note_html( $post_id, $rule ) {
			$out = '<p>' . esc_html( wp_easycart_page_access::summary( $post_id, $rule ) ) . '</p>';
			foreach ( wp_easycart_page_access::problems( $post_id ) as $problem ) {
				$out .= '<p style="color:' . ( 'bad' === $problem['level'] ? '#b32d2e' : '#8a4b00' ) . '">' . esc_html( $problem['text'] ) . '</p>';
			}
			$out .= '<p>' . esc_html__( 'Customers chosen one by one, and where customers who can see the page are sent, are set in the WordPress editor or on Users › Limit Access. Save the page to apply a change.', 'wp-easycart' ) . '</p>';
			$out .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-users&subpage=limit-access' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'All limited pages', 'wp-easycart' ) . '</a></p>';
			return $out;
		}

		/**
		 * After Elementor saves a document: the section's values become the rule, and leave the page settings.
		 *
		 * @param object $document Elementor document.
		 * @param array  $data     What was saved ( settings: the page settings that differ from their defaults ).
		 */
		public static function after_save( $document, $data ) {
			$post_id = self::post_id( $document );
			if ( ! $post_id || ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
				return;
			}
			$changed = array_intersect_key( $data['settings'], array_flip( self::KEYS ) );
			if ( ! empty( $changed ) ) {
				wp_easycart_page_access::save_rule( $post_id, self::apply( self::shown_rule( $post_id ), $changed ) );
			}
			$page_settings = get_post_meta( $post_id, '_elementor_page_settings', true );
			if ( is_array( $page_settings ) && array_intersect_key( $page_settings, array_flip( self::KEYS ) ) ) {
				update_post_meta( $post_id, '_elementor_page_settings', array_diff_key( $page_settings, array_flip( self::KEYS ) ) );
			}
		}

		/**
		 * A rule with the section's changed values ( a value left at its default is not sent, so it keeps the rule's ).
		 *
		 * @param array $rule    Rule.
		 * @param array $changed Control id => value.
		 * @return array
		 */
		public static function apply( $rule, $changed ) {
			$yes = function ( $value ) {
				return 'yes' === $value || true === $value || '1' === (string) $value;
			};
			if ( array_key_exists( 'wpec_access_on', $changed ) ) {
				$rule['on'] = $yes( $changed['wpec_access_on'] );
			}
			if ( array_key_exists( 'wpec_access_signed_in', $changed ) ) {
				$rule['signed_in'] = $yes( $changed['wpec_access_signed_in'] );
			}
			if ( array_key_exists( 'wpec_access_products', $changed ) ) {
				$rule['products'] = wp_easycart_page_access::ids( $changed['wpec_access_products'] );
			}
			if ( array_key_exists( 'wpec_access_roles', $changed ) ) {
				$rule['roles'] = wp_easycart_page_access::labels( (array) $changed['wpec_access_roles'] );
			}
			if ( array_key_exists( 'wpec_access_match', $changed ) ) {
				$rule['match'] = ( 'all' === $changed['wpec_access_match'] ) ? 'all' : 'any';
			}
			foreach ( array( 'signed_out', 'no_access' ) as $which ) {
				if ( array_key_exists( 'wpec_access_' . $which, $changed ) && is_scalar( $changed[ 'wpec_access_' . $which ] ) ) {
					$rule[ $which ]['to'] = sanitize_key( (string) $changed[ 'wpec_access_' . $which ] );
				}
				if ( array_key_exists( 'wpec_access_' . $which . '_page', $changed ) ) {
					$page                   = $changed[ 'wpec_access_' . $which . '_page' ];
					$rule[ $which ]['page'] = is_array( $page ) ? absint( reset( $page ) ) : absint( $page );
				}
				if ( array_key_exists( 'wpec_access_' . $which . '_url', $changed ) && is_scalar( $changed[ 'wpec_access_' . $which . '_url' ] ) ) {
					$rule[ $which ]['url'] = sanitize_text_field( (string) $changed[ 'wpec_access_' . $which . '_url' ] );
				}
			}
			if ( array_key_exists( 'wpec_access_external_ok', $changed ) ) {
				$rule['external_ok'] = $yes( $changed['wpec_access_external_ok'] );
			}
			return wp_easycart_page_access::clean( $rule );
		}
	}

	WP_EasyCart_Elementor_Access::init();

endif;
