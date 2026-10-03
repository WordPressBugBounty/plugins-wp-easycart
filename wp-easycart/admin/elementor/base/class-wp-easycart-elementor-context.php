<?php
/**
 * WP EasyCart Elementor context ( 6.0.2 ): which product, category or store page a widget, dynamic tag, template or
 * condition is looking at.
 *
 * Loaded on every request ( no Elementor dependency ). One instance, wp_easycart_elementor_context().
 *
 * Resolution order for product():
 *   1. an explicit pick ( 'source' => 'pick', 'product_id' ),
 *   2. a product pushed by EasyCart itself ( push_product(): EasyCart's own product templates, loops ),
 *   3. the current post, when it is an EasyCart product page ( ec_store post linked from ec_product.post_id; this is also
 *      what Elementor Pro's Theme Builder preview and Loop Grid items switch the global post to ),
 *   4. in the Elementor editor / preview only: the document's preview product ( filter
 *      wp_easycart_elementor_preview_product_id ), else the first active product.
 * Visitors on a page without a product get null, and widgets then print nothing.
 *
 * Visibility follows wp_easycart_get_shortcode_product_list(): inactive products only for store managers, and no product at
 * all for a visitor that Settings › Products › Who can view the store keeps out ( is_restricted(), 6.0.2 ).
 *
 * The preview product and category ( 6.0.2 ): every Elementor document's Settings tab has a "WP EasyCart preview" section
 * ( wpec_preview_product, wpec_preview_category ) that answers the two preview filters while the editor or its preview
 * draws that document; the main document's choice applies to templates drawn inside it ( header, footer ). A Theme Builder
 * template previewed "as" another post needs none of this: Elementor Pro switches the global post to that post, so
 * current_post_product_id() finds its product through get_the_ID().
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Context' ) ) :

	/**
	 * Current product / category / manufacturer / page type for Elementor integrations.
	 */
	class WP_EasyCart_Elementor_Context {

		/**
		 * Single instance.
		 *
		 * @var WP_EasyCart_Elementor_Context|null
		 */
		private static $instance = null;

		/**
		 * Products pushed by EasyCart templates and loops ( last one wins ).
		 *
		 * @var array
		 */
		private $product_stack = array();

		/**
		 * Categories pushed by EasyCart category templates.
		 *
		 * @var array
		 */
		private $category_stack = array();

		/**
		 * Built ec_product objects, keyed by product id + details flag.
		 *
		 * @var array
		 */
		private $products = array();

		/**
		 * Post id => product id ( 0 = not a product page ), per request.
		 *
		 * @var array
		 */
		private $post_products = array();

		/**
		 * The instance.
		 *
		 * @return WP_EasyCart_Elementor_Context
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Whether this request draws Elementor's editor or preview.
		 *
		 * @return bool
		 */
		public function is_editor() {
			return function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor();
		}

		/**
		 * Whether Settings › Products › Who can view the store keeps this visitor out ( 6.0.2 ): product() then returns null
		 * for every source, sample included; widgets say so in the editor ( WP_EasyCart_Elementor_Widget_Base::ec_no_product_notice() ).
		 *
		 * @return bool
		 */
		public function is_restricted() {
			return function_exists( 'wp_easycart_store_is_restricted' ) && wp_easycart_store_is_restricted();
		}

		/**
		 * Document setting values already looked up in this request ( key => value ).
		 *
		 * @var array
		 */
		private $document_values = array();

		/**
		 * A "WP EasyCart preview" setting of the document the editor or its preview is drawing ( 6.0.2 ): the document being
		 * rendered first ( a header or footer template inside the page ), then the page being edited. Reads the editor's
		 * autosave when it is newer, as Elementor's preview does. Returns 0 outside the editor.
		 *
		 * @param string $key wpec_preview_product | wpec_preview_category.
		 * @return int
		 */
		public function document_preview_setting( $key ) {
			if ( isset( $this->document_values[ $key ] ) ) {
				return $this->document_values[ $key ];
			}
			$value = 0;
			if ( $this->is_editor() && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
				$documents = \Elementor\Plugin::$instance->documents;
				$ids       = array();
				if ( method_exists( $documents, 'get_current' ) ) {
					$current = $documents->get_current();
					if ( is_object( $current ) && method_exists( $current, 'get_main_id' ) ) {
						$ids[] = (int) $current->get_main_id();
					}
				}
				$ids[] = $this->edited_document_id();
				foreach ( array_unique( array_filter( $ids ) ) as $document_id ) {
					$document = method_exists( $documents, 'get_doc_or_auto_save' ) ? $documents->get_doc_or_auto_save( $document_id, get_current_user_id() ) : $documents->get( $document_id );
					if ( ! is_object( $document ) || ! method_exists( $document, 'get_settings' ) ) {
						continue;
					}
					$setting = $document->get_settings( $key );
					if ( is_array( $setting ) ) {
						$setting = reset( $setting );
					}
					if ( is_scalar( $setting ) && (int) $setting > 0 ) {
						$value = (int) $setting;
						break;
					}
				}
			}
			$this->document_values[ $key ] = $value;
			return $value;
		}

		/**
		 * The post the Elementor editor has open ( the preview iframe, or the editor's own AJAX requests ), else 0.
		 *
		 * @return int
		 */
		private function edited_document_id() {
			$elementor = \Elementor\Plugin::$instance;
			if ( isset( $elementor->preview ) && is_object( $elementor->preview ) && method_exists( $elementor->preview, 'get_post_id' ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
				return (int) $elementor->preview->get_post_id();
			}
			// phpcs:disable WordPress.Security.NonceVerification -- read-only: which document the editor has open; Elementor checks the user may edit it.
			if ( isset( $_REQUEST['editor_post_id'] ) ) {
				return absint( $_REQUEST['editor_post_id'] );
			}
			if ( isset( $_GET['elementor-preview'] ) ) {
				return absint( $_GET['elementor-preview'] );
			}
			if ( isset( $_REQUEST['initial_document_id'] ) ) {
				return absint( $_REQUEST['initial_document_id'] );
			}
			// phpcs:enable WordPress.Security.NonceVerification
			return 0;
		}

		/**
		 * Filter wp_easycart_elementor_preview_product_id: the document's own preview product wins ( 6.0.2 ).
		 *
		 * @param int $product_id Product id from earlier filters.
		 * @return int
		 */
		public static function filter_preview_product( $product_id ) {
			$chosen = self::instance()->document_preview_setting( 'wpec_preview_product' );
			return $chosen ? $chosen : (int) $product_id;
		}

		/**
		 * Filter wp_easycart_elementor_preview_category_id: the document's own preview category wins ( 6.0.2 ).
		 *
		 * @param int $category_id Category id from earlier filters.
		 * @return int
		 */
		public static function filter_preview_category( $category_id ) {
			$chosen = self::instance()->document_preview_setting( 'wpec_preview_category' );
			return $chosen ? $chosen : (int) $category_id;
		}

		/**
		 * "WP EasyCart preview" in every Elementor document's Settings tab ( elementor/documents/register_controls, 6.0.2 ):
		 * the product and category widgets show while the page or template is designed. Visitors always see the product or
		 * category of the page they are on.
		 *
		 * @param object $document Elementor document.
		 */
		public static function register_document_controls( $document ) {
			if ( ! is_object( $document ) || ! method_exists( $document, 'start_controls_section' ) || ! class_exists( '\Elementor\Controls_Manager' ) ) {
				return;
			}
			if ( is_a( $document, '\Elementor\Core\Kits\Documents\Kit' ) ) {
				return; /* Site Settings */
			}
			$document->start_controls_section(
				'wpec_preview_section',
				array(
					'label' => __( 'WP EasyCart preview', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
				)
			);
			$document->add_control(
				'wpec_preview_product',
				array(
					'label'       => __( 'Preview product', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product',
					'label_block' => true,
					'multiple'    => false,
					'description' => __( 'Widgets that show "the product of the page it is on" show this product while you edit. Empty: your first product.', 'wp-easycart' ),
				)
			);
			$document->add_control(
				'wpec_preview_category',
				array(
					'label'       => __( 'Preview category', 'wp-easycart' ),
					'type'        => 'wpecajaxselect2',
					'options'     => 'easycart_product_cat',
					'label_block' => true,
					'multiple'    => false,
					'description' => __( 'Category widgets show this category while you edit. Visitors always see the product or category of the page they are on.', 'wp-easycart' ),
				)
			);
			$document->add_control(
				'wpec_preview_apply',
				array(
					'type'        => \Elementor\Controls_Manager::BUTTON,
					'label'       => '',
					'show_label'  => false,
					'text'        => __( 'Save and show in preview', 'wp-easycart' ),
					'button_type' => 'default',
					'event'       => 'wpeasycart:applyPreview',
					'separator'   => 'before',
				)
			);
			$document->end_controls_section();
		}

		/**
		 * The product a widget or tag should show.
		 *
		 * @param array $args {
		 *     @type string $source       'current' ( default ) or 'pick'.
		 *     @type int    $product_id   Product id for 'pick'.
		 *     @type bool   $allow_sample In the editor, fall back to a sample product ( default true ).
		 *     @type bool   $details      Build the product as a details page ( featured products, options; default true ).
		 * }
		 * @return ec_product|null
		 */
		public function product( $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'source'       => 'current',
					'product_id'   => 0,
					'allow_sample' => true,
					'details'      => true,
				)
			);
			/* 6.0.2: D5, Who can view the store ( also for products pushed by EasyCart's templates ). What shows depends on who
			 * is signed in, so a store that restricts viewing never lets a page cache keep a customer's copy for visitors. */
			if ( get_option( 'ec_option_restrict_store' ) && function_exists( 'wp_easycart_product_details_no_cache' ) ) {
				wp_easycart_product_details_no_cache();
			}
			if ( $this->is_restricted() ) {
				if ( function_exists( 'wp_easycart_product_details_no_cache' ) ) {
					wp_easycart_product_details_no_cache();
				}
				return null;
			}
			$product_id = 0;
			if ( 'pick' === $args['source'] ) {
				$product_id = (int) $args['product_id'];
			}
			if ( ! $product_id && ! empty( $this->product_stack ) ) {
				$top = end( $this->product_stack );
				if ( is_object( $top ) && isset( $top->product_id ) ) {
					return $top;
				}
				$product_id = (int) $top;
			}
			if ( ! $product_id ) {
				$product_id = $this->current_post_product_id();
			}
			if ( ! $product_id && $args['allow_sample'] && $this->is_editor() ) {
				$product_id = $this->sample_product_id();
			}
			if ( ! $product_id ) {
				return null;
			}
			return $this->build_product( $product_id, (bool) $args['details'] );
		}

		/**
		 * Product id of the current post when it is an EasyCart product page, else 0.
		 *
		 * @param int $post_id Post id ( default: the current post ).
		 * @return int
		 */
		public function current_post_product_id( $post_id = 0 ) {
			if ( ! $post_id ) {
				$post_id = (int) get_the_ID();
			}
			if ( ! $post_id ) {
				$queried = get_queried_object();
				$post_id = ( is_object( $queried ) && isset( $queried->ID ) ) ? (int) $queried->ID : 0;
			}
			if ( ! $post_id ) {
				return 0;
			}
			if ( ! isset( $this->post_products[ $post_id ] ) ) {
				global $wpdb;
				$this->post_products[ $post_id ] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT product_id FROM ec_product WHERE post_id = %d ORDER BY product_id ASC LIMIT 1', $post_id ) );
			}
			return $this->post_products[ $post_id ];
		}

		/**
		 * The editor's sample product: the document's preview product ( filter ), else the first active product.
		 *
		 * @return int
		 */
		public function sample_product_id() {
			static $sample = null;
			$filtered = (int) apply_filters( 'wp_easycart_elementor_preview_product_id', 0 );
			if ( $filtered ) {
				return $filtered;
			}
			if ( null === $sample ) {
				global $wpdb;
				$sample = (int) $wpdb->get_var( 'SELECT product_id FROM ec_product WHERE activate_in_store = 1 ORDER BY product_id ASC LIMIT 1' );
			}
			return $sample;
		}

		/**
		 * Build ( once per request ) the ec_product for an id, honouring visibility rules.
		 *
		 * @param int  $product_id Product id.
		 * @param bool $details    Build as a details page.
		 * @return ec_product|null
		 */
		public function build_product( $product_id, $details = true ) {
			$key = (int) $product_id . ( $details ? ':d' : ':l' );
			if ( array_key_exists( $key, $this->products ) ) {
				return $this->products[ $key ];
			}
			$product = null;
			if ( function_exists( 'wp_easycart_get_shortcode_product_list' ) && class_exists( 'ec_product' ) ) {
				$rows = wp_easycart_get_shortcode_product_list( false, 'NOPRODUCT', (int) $product_id );
				if ( is_array( $rows ) && count( $rows ) > 0 ) {
					$product = new ec_product( $rows[0], 0, ( $details ? 1 : 0 ), 1 );
				}
			}
			$this->products[ $key ] = $product;
			return $product;
		}

		/**
		 * EasyCart's own templates and loops set the product they are drawing.
		 *
		 * @param ec_product|int $product Product object or id.
		 */
		public function push_product( $product ) {
			$this->product_stack[] = $product;
		}

		/**
		 * Undo push_product().
		 */
		public function pop_product() {
			array_pop( $this->product_stack );
		}

		/**
		 * The category a category template or widget should show ( row from ec_category ), or null.
		 *
		 * @param array $args 'source' => 'current' | 'pick', 'category_id' => int, 'allow_sample' => bool.
		 * @return object|null
		 */
		public function category( $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'source'       => 'current',
					'category_id'  => 0,
					'allow_sample' => true,
				)
			);
			global $wpdb;
			$category_id = ( 'pick' === $args['source'] ) ? (int) $args['category_id'] : 0;
			if ( ! $category_id && ! empty( $this->category_stack ) ) {
				$category_id = (int) end( $this->category_stack );
			}
			if ( ! $category_id && isset( $_GET['group_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only storefront filter.
				$category_id = (int) $_GET['group_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only storefront filter.
			}
			if ( ! $category_id ) {
				$post_id = (int) get_the_ID();
				if ( $post_id ) {
					$category_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT category_id FROM ec_category WHERE post_id = %d LIMIT 1', $post_id ) );
				}
			}
			if ( ! $category_id && $args['allow_sample'] && $this->is_editor() ) {
				$category_id = (int) apply_filters( 'wp_easycart_elementor_preview_category_id', 0 );
				if ( ! $category_id ) {
					$category_id = (int) $wpdb->get_var( 'SELECT category_id FROM ec_category WHERE is_active = 1 ORDER BY priority DESC, category_name ASC LIMIT 1' );
				}
			}
			if ( ! $category_id ) {
				return null;
			}
			/* A switched-off category is only for store managers and the editor, as the classic store's category lookup. */
			$any = $this->is_editor() || current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' );
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_category WHERE category_id = %d' . ( $any ? '' : ' AND is_active = 1' ), $category_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- a fixed clause.
			return $row ? $row : null;
		}

		/**
		 * EasyCart's category templates set the category they are drawing.
		 *
		 * @param int $category_id Category id.
		 */
		public function push_category( $category_id ) {
			$this->category_stack[] = (int) $category_id;
		}

		/**
		 * Undo push_category().
		 */
		public function pop_category() {
			array_pop( $this->category_stack );
		}

		/**
		 * The manufacturer of the current manufacturer page ( row from ec_manufacturer ), or null.
		 *
		 * @return object|null
		 */
		public function manufacturer() {
			global $wpdb;
			$manufacturer_id = 0;
			if ( isset( $_GET['manufacturer'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only storefront filter.
				$manufacturer_id = (int) $_GET['manufacturer']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only storefront filter.
			}
			if ( ! $manufacturer_id ) {
				$post_id = (int) get_the_ID();
				if ( $post_id ) {
					$manufacturer_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT manufacturer_id FROM ec_manufacturer WHERE post_id = %d LIMIT 1', $post_id ) );
				}
			}
			if ( ! $manufacturer_id ) {
				return null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_manufacturer WHERE manufacturer_id = %d', $manufacturer_id ) );
			return $row ? $row : null;
		}

		/**
		 * What kind of EasyCart page this request is: product | category | manufacturer | store | cart | account | ''.
		 *
		 * @return string
		 */
		public function page_type() {
			if ( ! empty( $this->product_stack ) || $this->current_post_product_id() ) {
				return 'product';
			}
			$post_id = (int) get_queried_object_id();
			if ( $post_id ) {
				global $wpdb;
				if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_category WHERE post_id = %d', $post_id ) ) ) {
					return 'category';
				}
				if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ec_manufacturer WHERE post_id = %d', $post_id ) ) ) {
					return 'manufacturer';
				}
				if ( (int) get_option( 'ec_option_storepage' ) === $post_id ) {
					return 'store';
				}
				if ( (int) get_option( 'ec_option_cartpage' ) === $post_id ) {
					return 'cart';
				}
				if ( (int) get_option( 'ec_option_accountpage' ) === $post_id ) {
					return 'account';
				}
			}
			return '';
		}
	}

endif;

if ( ! function_exists( 'wp_easycart_elementor_context' ) ) {
	/**
	 * The Elementor context instance ( 6.0.2 ).
	 *
	 * @return WP_EasyCart_Elementor_Context
	 */
	function wp_easycart_elementor_context() {
		return WP_EasyCart_Elementor_Context::instance();
	}
}

/* 6.0.2: the document's "WP EasyCart preview" choices; late, so a merchant's explicit choice beats a module's default. */
add_filter( 'wp_easycart_elementor_preview_product_id', array( 'WP_EasyCart_Elementor_Context', 'filter_preview_product' ), 20 );
add_filter( 'wp_easycart_elementor_preview_category_id', array( 'WP_EasyCart_Elementor_Context', 'filter_preview_category' ), 20 );
