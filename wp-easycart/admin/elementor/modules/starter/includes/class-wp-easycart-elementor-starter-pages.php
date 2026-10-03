<?php
/**
 * Settings › Elementor › Starter pages ( 6.0.2 ).
 *
 * One button adds five ready layouts to Elementor › Templates › My Templates: Shop page, Cart page, Checkout page,
 * My Account page and Order confirmation page ( WP_EasyCart_Elementor_Starter_Layouts::pages() ). Each is an
 * elementor_library post of type 'page', saved through Elementor's own template library ( _elementor_data,
 * _elementor_edit_mode builder, _elementor_template_type page, _elementor_version ) and marked with the post meta
 * _wp_easycart_elementor_starter = its key, so a second click adds only what is missing and reports the rest with
 * their Edit links. A template the merchant trashed or deleted is added again.
 *
 * Admin-post wp_easycart_elementor_starter_pages ( guard wp_easycart_elementor_starter_guard(): nonce
 * wp-easycart-elementor-starter-pages, manage_options or wpec_settings, and edit_posts ). The result is kept for the
 * person for ten minutes ( transient wpec_el_starter_{user id} ) and shown once as a notice in the section after the
 * redirect back.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Starter_Pages' ) ) :

	/**
	 * The Starter pages section and the request that adds the templates.
	 */
	final class WP_EasyCart_Elementor_Starter_Pages {

		const ACTION  = 'wp_easycart_elementor_starter_pages';
		const NONCE   = 'wp-easycart-elementor-starter-pages';
		const META    = '_wp_easycart_elementor_starter';
		const RESULT  = 'wpec_el_starter_';
		const LOCK    = 'wp_easycart_elementor_starter_lock';
		const SECTION = 'starter-pages';

		/**
		 * Starter templates found in this request ( key => post id ), or null.
		 *
		 * @var array|null
		 */
		private static $existing = null;

		/**
		 * Add the hooks ( admin requests only ).
		 */
		public static function boot() {
			add_filter( 'wp_easycart_settings_page_elementor', array( __CLASS__, 'settings_page' ), 20 );
			add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'add_pages' ) );
		}

		/**
		 * Filter wp_easycart_settings_page_elementor: the section, after the product and category page sections ( or the
		 * status section ).
		 *
		 * @param array $page Declaration.
		 * @return array
		 */
		public static function settings_page( $page ) {
			if ( ! is_array( $page ) ) {
				return $page;
			}
			$sections = ( isset( $page['sections'] ) && is_array( $page['sections'] ) ) ? $page['sections'] : array();
			$ours     = array(
				'title'    => __( 'Starter pages', 'wp-easycart' ),
				'hint'     => __( 'Ready layouts for your shop, cart, checkout, account and order confirmation pages.', 'wp-easycart' ),
				'icon'     => 'zap',
				'keywords' => array( 'starter', 'starter pages', 'templates', 'my templates', 'layouts', 'template kit', 'shop page', 'cart page', 'checkout page', 'account page', 'order confirmation', 'thank you page', 'import' ),
				'fields'   => array(),
				'render'   => array( __CLASS__, 'render_section' ),
			);
			$after    = isset( $sections['category-templates'] ) ? 'category-templates' : ( isset( $sections['status'] ) ? 'status' : '' );
			$merged   = array();
			if ( '' === $after ) {
				$merged[ self::SECTION ] = $ours;
			}
			foreach ( $sections as $slug => $section ) {
				if ( self::SECTION === $slug ) {
					continue;
				}
				$merged[ $slug ] = $section;
				if ( $slug === $after ) {
					$merged[ self::SECTION ] = $ours;
				}
			}
			$page['sections'] = $merged;
			return $page;
		}

		/**
		 * Whether the person may add templates: the store's settings ( manage_options or wpec_settings ) and Elementor
		 * templates ( edit_posts ).
		 *
		 * @return bool
		 */
		public static function user_can_add() {
			return ( current_user_can( 'manage_options' ) || current_user_can( 'wpec_settings' ) ) && current_user_can( 'edit_posts' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own roles register this capability.
		}

		/**
		 * Whether Elementor is loaded far enough to save templates.
		 *
		 * @return bool
		 */
		private static function elementor_ready() {
			return defined( 'ELEMENTOR_VERSION' ) && did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && is_object( \Elementor\Plugin::$instance );
		}

		/**
		 * The starter templates that exist ( any status but trash ), key => post id ( the oldest one per key ).
		 *
		 * @param bool $refresh Read again.
		 * @return array
		 */
		public static function existing( $refresh = false ) {
			if ( null !== self::$existing && ! $refresh ) {
				return self::$existing;
			}
			global $wpdb;
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, m.meta_value AS starter FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) ORDER BY p.ID ASC",
					self::META,
					'elementor_library'
				)
			);
			$found = array();
			foreach ( (array) $rows as $row ) {
				$key = sanitize_key( (string) $row->starter );
				if ( '' !== $key && ! isset( $found[ $key ] ) ) {
					$found[ $key ] = (int) $row->ID;
				}
			}
			self::$existing = $found;
			return $found;
		}

		/**
		 * Where Elementor edits a template.
		 *
		 * @param int $post_id Template post id.
		 * @return string
		 */
		public static function edit_url( $post_id ) {
			return add_query_arg(
				array(
					'post'   => (int) $post_id,
					'action' => 'elementor',
				),
				admin_url( 'post.php' )
			);
		}

		/**
		 * Elementor › Templates › My Templates, showing page templates.
		 *
		 * @return string
		 */
		public static function library_url() {
			return admin_url( 'edit.php?post_type=elementor_library&tabs_group=library&elementor_library_type=page' );
		}

		/**
		 * Settings › Elementor, with extra query arguments.
		 *
		 * @param array $args Query arguments.
		 * @return string
		 */
		private static function settings_url( $args = array() ) {
			return add_query_arg(
				array_merge(
					array(
						'page'    => 'wp-easycart-settings',
						'subpage' => 'elementor',
					),
					$args
				),
				admin_url( 'admin.php' )
			);
		}

		// Settings › Elementor.

		/**
		 * The section: the result of the last click ( once ), the five layouts with their state, the button and how to use them.
		 */
		public static function render_section() {
			self::print_result();
			echo '<div class="ecst-elementor-status wpec-el-starter">';
			if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
				echo '<p class="ecst-el-intro">' . esc_html__( 'Activate Elementor ( the free plugin is enough ) to add ready layouts for your store pages.', 'wp-easycart' ) . '</p>';
				echo '</div>';
				return;
			}
			$existing = self::existing();
			echo '<p class="ecst-el-intro">' . esc_html__( 'Five ready layouts built with the WP EasyCart widgets, for Elementor › Templates › My Templates. They follow your site\'s colours and fonts and work without any setup; change anything you like once a layout is on a page.', 'wp-easycart' ) . '</p>';
			echo '<ul class="ecst-el-list">';
			foreach ( WP_EasyCart_Elementor_Starter_Layouts::pages() as $key => $spec ) {
				$post_id = isset( $existing[ $key ] ) ? (int) $existing[ $key ] : 0;
				echo '<li class="ecst-el-row"><span class="ecst-el-label">' . esc_html( $spec['label'] ) . '</span>';
				echo '<span class="ecst-el-value is-' . ( $post_id ? 'ok' : 'off' ) . '">' . esc_html( $post_id ? __( 'In My Templates', 'wp-easycart' ) : __( 'Not added yet', 'wp-easycart' ) ) . '</span>';
				echo '<span class="ecst-el-detail">' . esc_html( $spec['contains'] );
				if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
					echo ' <a href="' . esc_url( self::edit_url( $post_id ) ) . '">' . esc_html__( 'Edit in Elementor', 'wp-easycart' ) . '</a>';
				}
				echo '</span></li>';
			}
			echo '</ul>';

			if ( self::user_can_add() ) {
				$have = count( array_intersect_key( $existing, WP_EasyCart_Elementor_Starter_Layouts::pages() ) );
				echo '<form class="ecst-el-links" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
				wp_nonce_field( self::NONCE );
				echo '<button type="submit" class="ecv2-btn ecv2-btn-primary">' . esc_html( 0 === $have ? __( 'Add the starter pages to My Templates', 'wp-easycart' ) : __( 'Add the missing starter pages', 'wp-easycart' ) ) . '</button>';
				echo '<a href="' . esc_url( self::library_url() ) . '">' . esc_html__( 'Open My Templates', 'wp-easycart' ) . '</a>';
				echo '</form>';
			} else {
				echo '<p class="ecst-el-intro">' . esc_html__( 'Adding templates needs an account that can edit posts in WordPress.', 'wp-easycart' ) . '</p>';
			}

			echo '<p><strong>' . esc_html__( 'How to use them', 'wp-easycart' ) . '</strong><br>';
			echo esc_html__( 'Edit a page with Elementor, open Add Template ( the folder icon ) › My Templates and insert the layout. Put the Shop page on your store page and the My Account page on your account page.', 'wp-easycart' ) . '</p>';
			echo '<p>' . esc_html__( 'The cart, the checkout and the order confirmation all show on your cart page: insert the Cart, Checkout and Order confirmation layouts there, one below the other, and each appears at its own step.', 'wp-easycart' ) . ' ';
			echo wp_kses_post(
				sprintf(
					/* translators: %s: link to Settings › Store details › Store pages. */
					__( 'Your store, cart and account pages are chosen in %s.', 'wp-easycart' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-settings&subpage=initial-setup' ) ) . '">' . esc_html__( 'Settings › Store details › Store pages', 'wp-easycart' ) . '</a>'
				)
			) . '</p>';
			echo '</div>';
		}

		/**
		 * The notice with the result of the last click, printed once after the redirect back.
		 */
		private static function print_result() {
			if ( ! isset( $_GET['wpec_starter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag; the result comes from this person's own transient.
				return;
			}
			$key    = self::RESULT . get_current_user_id();
			$result = get_transient( $key );
			if ( ! is_array( $result ) ) {
				return;
			}
			delete_transient( $key );
			$notice = self::result_notice( $result );
			if ( function_exists( 'wp_easycart_admin_notice_html' ) ) {
				echo wp_easycart_admin_notice_html( $notice['tone'], $notice['text'], $notice['args'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html() escapes every part.
				return;
			}
			echo '<div class="notice notice-' . esc_attr( 'error' === $notice['tone'] ? 'error' : ( 'warning' === $notice['tone'] ? 'warning' : 'success' ) ) . ' inline"><p>' . esc_html( $notice['text'] ) . ( '' !== $notice['args']['detail'] ? ' ' . esc_html( $notice['args']['detail'] ) : '' ) . '</p></div>';
		}

		/**
		 * The notice for a result: tone, main text, and args for wp_easycart_admin_notice_html() ( detail, Edit links ).
		 *
		 * @param array $result added, existing ( key => post id ), failed ( key => reason ), error.
		 * @return array
		 */
		public static function result_notice( $result ) {
			$result = wp_parse_args(
				$result,
				array(
					'added'    => array(),
					'existing' => array(),
					'failed'   => array(),
					'error'    => '',
				)
			);
			$pages  = WP_EasyCart_Elementor_Starter_Layouts::pages();
			$label  = function ( $key ) use ( $pages ) {
				return isset( $pages[ $key ] ) ? $pages[ $key ]['label'] : $key;
			};
			$args   = array(
				'detail'      => '',
				'actions'     => array(),
				'dismissible' => true,
			);
			if ( '' !== (string) $result['error'] ) {
				return array(
					'tone' => 'error',
					'text' => (string) $result['error'],
					'args' => $args,
				);
			}

			$added    = array_map( $label, array_keys( (array) $result['added'] ) );
			$existing = array_map( $label, array_keys( (array) $result['existing'] ) );
			$text     = array();
			if ( $added ) {
				/* translators: %s: list of starter pages, e.g. "Shop page, Cart page and Checkout page". */
				$text[] = sprintf( _n( '%s was added to Elementor › Templates › My Templates.', '%s were added to Elementor › Templates › My Templates.', count( $added ), 'wp-easycart' ), wp_sprintf( '%l', $added ) );
			}
			if ( $existing ) {
				/* translators: %s: list of starter pages. */
				$text[] = $added ? sprintf( __( 'Already there, so not added again: %s.', 'wp-easycart' ), wp_sprintf( '%l', $existing ) ) : sprintf( __( 'Already in My Templates, so nothing was added again: %s.', 'wp-easycart' ), wp_sprintf( '%l', $existing ) );
			}
			$detail = array();
			foreach ( (array) $result['failed'] as $key => $reason ) {
				/* translators: 1: starter page, 2: why it was not added. */
				$detail[] = sprintf( __( 'Not added: %1$s. %2$s', 'wp-easycart' ), $label( $key ), (string) $reason );
			}
			if ( $added || $existing ) {
				$detail[] = __( 'To use one, edit a page with Elementor and insert it from Add Template ( the folder icon ) › My Templates.', 'wp-easycart' );
			}
			if ( empty( $text ) ) {
				$text[] = __( 'No starter page was added.', 'wp-easycart' );
			}
			$args['detail'] = implode( ' ', $detail );

			foreach ( array( 'added', 'existing' ) as $group ) {
				foreach ( (array) $result[ $group ] as $key => $post_id ) {
					if ( $post_id && current_user_can( 'edit_post', (int) $post_id ) ) {
						$args['actions'][] = array(
							/* translators: %s: starter page, e.g. "Shop page". */
							'label' => sprintf( __( 'Edit %s', 'wp-easycart' ), $label( $key ) ),
							'url'   => self::edit_url( (int) $post_id ),
							'link'  => true,
						);
					}
				}
			}
			if ( $added || $existing ) {
				$args['actions'][] = array(
					'label'   => __( 'Open My Templates', 'wp-easycart' ),
					'url'     => self::library_url(),
					'primary' => true,
				);
			}

			if ( ! empty( $result['failed'] ) ) {
				$tone = 'warning';
			} elseif ( $added ) {
				$tone = 'success';
			} else {
				$tone = 'info';
			}
			return array(
				'tone' => $tone,
				'text' => implode( ' ', $text ),
				'args' => $args,
			);
		}

		// Adding the templates.

		/**
		 * Admin-post wp_easycart_elementor_starter_pages: add what is missing, keep the result, go back to the section.
		 */
		public static function add_pages() {
			wp_safe_redirect( self::handle() );
			exit;
		}

		/**
		 * The request's work: check it, add what is missing, keep the result for the person.
		 *
		 * @return string Where to go next ( the section ).
		 */
		public static function handle() {
			wp_easycart_elementor_starter_guard();
			$result = self::run();
			set_transient( self::RESULT . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
			return self::settings_url( array( 'wpec_starter' => '1' ) ) . '#ecst-sec-' . self::SECTION;
		}

		/**
		 * Add every starter page that does not exist yet.
		 *
		 * @return array added ( key => post id ), existing ( key => post id ), failed ( key => reason ), error ( '' or why
		 *               nothing could be done ).
		 */
		public static function run() {
			$result = array(
				'added'    => array(),
				'existing' => array(),
				'failed'   => array(),
				'error'    => '',
			);
			if ( ! self::elementor_ready() ) {
				$result['error'] = __( 'Activate Elementor to add the starter pages.', 'wp-easycart' );
				return $result;
			}
			if ( ! self::lock() ) {
				$result['error'] = __( 'The starter pages are being added in another window. Reload this page in a moment to see them.', 'wp-easycart' );
				return $result;
			}
			try {
				$existing = self::existing( true );
				foreach ( WP_EasyCart_Elementor_Starter_Layouts::pages() as $key => $spec ) {
					if ( isset( $existing[ $key ] ) ) {
						$result['existing'][ $key ] = $existing[ $key ];
						continue;
					}
					$missing = self::missing_widgets( $spec['widgets'] );
					if ( $missing ) {
						$result['failed'][ $key ] = sprintf(
							/* translators: %s: widget names. */
							_n( 'The %s widget is not available on this site: check that it is switched on in Elementor › Element Manager.', 'The %s widgets are not available on this site: check that they are switched on in Elementor › Element Manager.', count( $missing ), 'wp-easycart' ),
							wp_sprintf( '%l', $missing )
						);
						continue;
					}
					$post_id = self::create( $key, $spec );
					if ( is_wp_error( $post_id ) ) {
						$result['failed'][ $key ] = $post_id->get_error_message();
					} else {
						$result['added'][ $key ] = $post_id;
					}
				}
			} finally {
				self::unlock();
				self::$existing = null;
			}
			return $result;
		}

		/**
		 * Titles of the widgets Elementor does not have in this request ( a module switched off, Element Manager ).
		 *
		 * @param array $widgets Name => title.
		 * @return array
		 */
		private static function missing_widgets( $widgets ) {
			$manager = isset( \Elementor\Plugin::$instance->widgets_manager ) ? \Elementor\Plugin::$instance->widgets_manager : null;
			if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_widget_types' ) ) {
				return array_values( $widgets );
			}
			$missing = array();
			foreach ( $widgets as $name => $title ) {
				if ( ! $manager->get_widget_types( $name ) ) {
					$missing[] = $title;
				}
			}
			return $missing;
		}

		/**
		 * Save one starter page to Elementor's library and mark it.
		 *
		 * Through the library's own save ( Source_Local::save_item(): an elementor_library post of type page with
		 * _elementor_data, _elementor_edit_mode, _elementor_template_type and _elementor_version, published when the
		 * person may publish ), else the documents manager.
		 *
		 * @param string $key  A key of WP_EasyCart_Elementor_Starter_Layouts::pages().
		 * @param array  $spec Its entry.
		 * @return int|WP_Error Post id.
		 */
		private static function create( $key, $spec ) {
			$elements = WP_EasyCart_Elementor_Starter_Layouts::page( $key );
			$plugin   = \Elementor\Plugin::$instance;
			$post_id  = 0;
			$error    = null;

			$source = ( isset( $plugin->templates_manager ) && is_object( $plugin->templates_manager ) && method_exists( $plugin->templates_manager, 'get_source' ) ) ? $plugin->templates_manager->get_source( 'local' ) : null;
			if ( is_object( $source ) && method_exists( $source, 'save_item' ) ) {
				$saved = $source->save_item(
					array(
						'type'          => 'page',
						'title'         => $spec['title'],
						'content'       => $elements,
						'page_settings' => array(),
					)
				);
				if ( is_wp_error( $saved ) ) {
					$error = $saved;
				} else {
					$post_id = (int) $saved;
				}
			}

			if ( ! $post_id && isset( $plugin->documents ) && is_object( $plugin->documents ) && method_exists( $plugin->documents, 'create' ) && $plugin->documents->get_document_type( 'page', false ) ) {
				$document = $plugin->documents->create(
					'page',
					array(
						'post_title'  => $spec['title'],
						'post_status' => current_user_can( 'publish_posts' ) ? 'publish' : 'pending',
						'post_type'   => 'elementor_library',
					)
				);
				if ( is_wp_error( $document ) ) {
					return $error ? $error : $document;
				}
				if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
					$post_id = (int) $document->get_main_id();
					if ( $post_id && method_exists( $document, 'save' ) ) {
						$document->save( array( 'elements' => $elements ) );
					}
				}
			}

			if ( ! $post_id ) {
				return $error ? $error : new WP_Error( 'wpec_elementor_starter_failed', __( 'This version of Elementor could not save the layout. Update Elementor and try again.', 'wp-easycart' ) );
			}
			update_post_meta( $post_id, self::META, $key );
			return $post_id;
		}

		/**
		 * Take the lock that keeps two clicks from adding the same pages twice ( held for at most a minute ).
		 *
		 * @return bool
		 */
		private static function lock() {
			$now = time();
			if ( add_option( self::LOCK, $now, '', 'no' ) ) {
				return true;
			}
			$held = (int) get_option( self::LOCK, 0 );
			if ( $held > $now - MINUTE_IN_SECONDS ) {
				return false;
			}
			update_option( self::LOCK, $now, false );
			return true;
		}

		/**
		 * Release the lock.
		 */
		private static function unlock() {
			delete_option( self::LOCK );
		}
	}

endif;
