<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- one file for the related classes loaded by one hook.
/**
 * "EasyCart Product" and "EasyCart Category" template types for Elementor's library ( 6.0.2, free Elementor ).
 *
 * Loaded only on elementor/documents/register ( they extend Elementor's Library_Document ). While the editor, its preview
 * or the template's own preview page draws the template, the document pushes its preview product / category into
 * wp_easycart_elementor_context(), so every WP EasyCart widget shows real data. When WP EasyCart draws the template for
 * a real product or category page nothing is pushed here ( the page's own product / category is already in the context ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- the two template types and their shared base load together from one Elementor hook.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Template_Document' ) && class_exists( '\Elementor\Modules\Library\Documents\Library_Document' ) ) :

	/**
	 * Shared behaviour of the two template types.
	 */
	abstract class WP_EasyCart_Elementor_Template_Document extends \Elementor\Modules\Library\Documents\Library_Document {

		/**
		 * 'product' or 'category'.
		 *
		 * @return string
		 */
		abstract protected static function ec_kind();

		/**
		 * Document properties.
		 *
		 * @return array
		 */
		public static function get_properties() {
			$properties                   = parent::get_properties();
			$properties['support_kit']    = true;
			$properties['show_in_finder'] = true;
			return $properties;
		}

		/**
		 * Type name ( older Elementor versions ask the instance ).
		 *
		 * @return string
		 */
		public function get_name() {
			return static::get_type();
		}

		/**
		 * The WP EasyCart panel category of this template's kind comes first in the widget panel.
		 *
		 * @return array
		 */
		protected static function get_editor_panel_categories() {
			$categories = parent::get_editor_panel_categories();
			$first      = array();
			$slugs      = ( 'category' === static::ec_kind() ) ? array( 'wp-easycart-shop', 'wp-easycart-product' ) : array( 'wp-easycart-product', 'wp-easycart-shop' );
			foreach ( $slugs as $slug ) {
				if ( isset( $categories[ $slug ] ) ) {
					$first[ $slug ]           = $categories[ $slug ];
					$first[ $slug ]['active'] = true;
					unset( $categories[ $slug ] );
				}
			}
			return $first + $categories;
		}

		/**
		 * The preview setting's key.
		 *
		 * @return string
		 */
		protected static function ec_preview_key() {
			return ( 'category' === static::ec_kind() ) ? 'wpec_preview_category' : 'wpec_preview_product';
		}

		/**
		 * Document controls: Elementor's ( with the "WP EasyCart preview" section every document gets:
		 * WP_EasyCart_Elementor_Context::register_document_controls(), wpec_preview_product / wpec_preview_category ), then
		 * where this template is used.
		 */
		protected function register_controls() {
			parent::register_controls();

			$kind = static::ec_kind();
			$this->start_controls_section(
				'wpec_template_section',
				array(
					'label' => ( 'category' === $kind ) ? __( 'Category template', 'wp-easycart' ) : __( 'Product template', 'wp-easycart' ),
					'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
				)
			);
			$this->add_control(
				'wpec_template_usage',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => $this->ec_usage_html(),
					'content_classes' => 'elementor-descriptor',
				)
			);
			$this->end_controls_section();
		}

		/**
		 * Where this template is used, for the editor's settings panel.
		 *
		 * @return string
		 */
		private function ec_usage_html() {
			$kind     = static::ec_kind();
			$settings = admin_url( 'admin.php?page=wp-easycart-settings&subpage=elementor' );
			$in_use   = ( isset( $this->post->ID ) && (int) get_option( WP_EasyCart_Elementor_Templates::option_name( $kind ), 0 ) === (int) $this->post->ID );
			if ( $in_use ) {
				$text = ( 'category' === $kind ) ? __( 'Every category page uses this template once it is published.', 'wp-easycart' ) : __( 'Every product page uses this template once it is published.', 'wp-easycart' );
			} else {
				$text = ( 'category' === $kind ) ? __( 'Not in use yet. Choose it for your category pages in Settings › Elementor.', 'wp-easycart' ) : __( 'Not in use yet. Choose it for your product pages in Settings › Elementor.', 'wp-easycart' );
			}
			return esc_html( $text ) . ' <a href="' . esc_url( $settings ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open Settings › Elementor', 'wp-easycart' ) . '</a>';
		}

		/**
		 * Whether this render shows the template itself ( editor, preview, or its own page for someone who can edit it ).
		 *
		 * @return bool
		 */
		private function ec_is_design_view() {
			if ( WP_EasyCart_Elementor_Templates::rendering() ) {
				return false;
			}
			if ( function_exists( 'wp_easycart_elementor_is_editor' ) && wp_easycart_elementor_is_editor() ) {
				return true;
			}
			$main_id = (int) $this->get_main_id();
			return $main_id > 0 && ! is_admin() && is_singular() && (int) get_queried_object_id() === $main_id && current_user_can( 'edit_post', $main_id );
		}

		/**
		 * Push the preview product / category ( true when something was pushed ).
		 *
		 * @return bool
		 */
		private function ec_push_preview() {
			if ( ! $this->ec_is_design_view() || ! function_exists( 'wp_easycart_elementor_context' ) ) {
				return false;
			}
			$context = wp_easycart_elementor_context();
			$key     = static::ec_preview_key();
			/* The editor's choice ( autosave-aware ), else this template's saved one ( its own preview page ), else a sample. */
			$id = method_exists( $context, 'document_preview_setting' ) ? (int) $context->document_preview_setting( $key ) : 0;
			if ( ! $id ) {
				$id = WP_EasyCart_Elementor_Templates::document_id_setting( $this, $key );
			}
			if ( 'category' === static::ec_kind() ) {
				if ( ! $id ) {
					$id = WP_EasyCart_Elementor_Templates::sample_category_id();
				}
				if ( ! $id ) {
					return false;
				}
				wp_easycart_elementor_context()->push_category( $id );
				return true;
			}
			if ( ! $id ) {
				$id = (int) wp_easycart_elementor_context()->sample_product_id();
			}
			if ( ! $id ) {
				return false;
			}
			wp_easycart_elementor_context()->push_product( $id );
			return true;
		}

		/**
		 * Undo ec_push_preview().
		 *
		 * @param bool $pushed What ec_push_preview() returned.
		 */
		private function ec_pop_preview( $pushed ) {
			if ( ! $pushed ) {
				return;
			}
			if ( 'category' === static::ec_kind() ) {
				wp_easycart_elementor_context()->pop_category();
			} else {
				wp_easycart_elementor_context()->pop_product();
			}
		}

		/**
		 * The editor's first render of every element.
		 *
		 * @param array|null $data              Elements.
		 * @param bool       $with_html_content With rendered HTML.
		 * @return array
		 */
		public function get_elements_raw_data( $data = null, $with_html_content = false ) {
			$pushed = $this->ec_push_preview();
			try {
				$result = parent::get_elements_raw_data( $data, $with_html_content );
			} finally {
				$this->ec_pop_preview( $pushed );
			}
			return $result;
		}

		/**
		 * The editor's re-render of one widget.
		 *
		 * @param array $data Element data.
		 * @return string
		 */
		public function render_element( $data ) {
			$pushed = $this->ec_push_preview();
			try {
				$result = parent::render_element( $data );
			} finally {
				$this->ec_pop_preview( $pushed );
			}
			return $result;
		}

		/**
		 * Front-end render ( the template's own preview page; WP EasyCart's product and category pages ).
		 *
		 * @param array|null $elements_data Elements.
		 */
		public function print_elements_with_wrapper( $elements_data = null ) {
			$pushed = $this->ec_push_preview();
			try {
				parent::print_elements_with_wrapper( $elements_data );
			} finally {
				$this->ec_pop_preview( $pushed );
			}
		}
	}

	/**
	 * "EasyCart Product": the layout of product pages.
	 */
	class WP_EasyCart_Elementor_Product_Template_Document extends WP_EasyCart_Elementor_Template_Document {

		/**
		 * Kind.
		 *
		 * @return string
		 */
		protected static function ec_kind() {
			return 'product';
		}

		/**
		 * Type ( stored in every template: never rename ).
		 *
		 * @return string
		 */
		public static function get_type() {
			return WP_EasyCart_Elementor_Templates::PRODUCT_TYPE;
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public static function get_title() {
			return esc_html__( 'EasyCart Product', 'wp-easycart' );
		}

		/**
		 * Plural title.
		 *
		 * @return string
		 */
		public static function get_plural_title() {
			return esc_html__( 'EasyCart Products', 'wp-easycart' );
		}

		/**
		 * "Add New" title.
		 *
		 * @return string
		 */
		public static function get_add_new_title() {
			return esc_html__( 'Add New Product Template', 'wp-easycart' );
		}
	}

	/**
	 * "EasyCart Category": the layout of category pages.
	 */
	class WP_EasyCart_Elementor_Category_Template_Document extends WP_EasyCart_Elementor_Template_Document {

		/**
		 * Kind.
		 *
		 * @return string
		 */
		protected static function ec_kind() {
			return 'category';
		}

		/**
		 * Type ( stored in every template: never rename ).
		 *
		 * @return string
		 */
		public static function get_type() {
			return WP_EasyCart_Elementor_Templates::CATEGORY_TYPE;
		}

		/**
		 * Title.
		 *
		 * @return string
		 */
		public static function get_title() {
			return esc_html__( 'EasyCart Category', 'wp-easycart' );
		}

		/**
		 * Plural title.
		 *
		 * @return string
		 */
		public static function get_plural_title() {
			return esc_html__( 'EasyCart Categories', 'wp-easycart' );
		}

		/**
		 * "Add New" title.
		 *
		 * @return string
		 */
		public static function get_add_new_title() {
			return esc_html__( 'Add New Category Template', 'wp-easycart' );
		}
	}

endif;
