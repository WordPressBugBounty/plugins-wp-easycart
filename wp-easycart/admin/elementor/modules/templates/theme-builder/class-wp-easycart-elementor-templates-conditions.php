<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- one file for the related classes loaded by one hook.
/**
 * Elementor Pro Theme Builder: the "WP EasyCart" display conditions ( 6.0.2 ).
 *
 * Loaded only inside elementor/theme/register_conditions ( the classes extend Elementor Pro's Condition_Base ). Condition
 * names are stored in every Theme Builder template that uses them: never rename one.
 *
 *   WP EasyCart › Entire store ( wp_easycart ): product, category, manufacturer, store, cart and account pages.
 *     Products ( wp_easycart_product ), any or one;          In category ( wp_easycart_product_in_category ), a category;
 *     Category pages ( wp_easycart_category ), any or one;  Manufacturer pages ( wp_easycart_manufacturer ), any or one;
 *     Store page ( wp_easycart_store_page ), Cart page ( wp_easycart_cart_page ), Account page ( wp_easycart_account_page ).
 *
 * The checks use WP EasyCart's own lookups ( WP_EasyCart_Elementor_Templates::condition_check() ), so they match whether
 * or not store items are drawn as pages. The pickers list store items of one kind ( products, categories, manufacturers ).
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Elementor Pro's conditions are registered together from one hook, only when Elementor Pro runs.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Condition_Store' ) && class_exists( '\ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base' ) ) :

	/**
	 * Base for the WP EasyCart sub-conditions.
	 */
	abstract class WP_EasyCart_Elementor_Condition_Base extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

		/**
		 * The check ( see WP_EasyCart_Elementor_Templates::condition_check() ).
		 *
		 * @return string
		 */
		abstract protected function ec_check();

		/**
		 * Picker kind ( product | category | manufacturer ), '' for none.
		 *
		 * @return string
		 */
		protected function ec_picker() {
			return '';
		}

		/**
		 * Group.
		 *
		 * @return string
		 */
		public static function get_type() {
			return 'wp_easycart';
		}

		/**
		 * More specific than "Singular" and its post types.
		 *
		 * @return int
		 */
		public static function get_priority() {
			return 30;
		}

		/**
		 * Whether the condition holds.
		 *
		 * @param array $args 'id' => the chosen item's post id.
		 * @return bool
		 */
		public function check( $args ) {
			return WP_EasyCart_Elementor_Templates::condition_check( $this->ec_check(), isset( $args['id'] ) ? (int) $args['id'] : 0 );
		}

		/**
		 * The picker for one item.
		 */
		protected function register_controls() {
			$kind = $this->ec_picker();
			if ( '' === $kind || ! class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ) {
				return;
			}
			$this->add_control(
				'post_id',
				array(
					'section'        => 'settings',
					'type'           => \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID,
					'select2options' => array(
						'dropdownCssClass' => 'elementor-conditions-select2-dropdown',
					),
					'autocomplete'   => array(
						'object'  => 'post',
						'display' => 'wp_easycart_' . $kind,
						'query'   => array(
							'post_type'   => 'ec_store',
							'post_status' => 'publish',
						),
					),
				)
			);
		}
	}

	/**
	 * WP EasyCart › Entire store.
	 */
	class WP_EasyCart_Elementor_Condition_Store extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

		/**
		 * Group this condition opens.
		 *
		 * @return string
		 */
		public static function get_type() {
			return 'wp_easycart';
		}

		/**
		 * Between "Singular" ( 60 ) and a post type ( 40 ).
		 *
		 * @return int
		 */
		public static function get_priority() {
			return 45;
		}

		/**
		 * Name ( stored ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'WP EasyCart', 'wp-easycart' );
		}

		/**
		 * "All" label.
		 *
		 * @return string
		 */
		public function get_all_label() {
			return esc_html__( 'Entire store', 'wp-easycart' );
		}

		/**
		 * The sub-conditions.
		 */
		public function register_sub_conditions() {
			foreach ( array(
				'WP_EasyCart_Elementor_Condition_Product',
				'WP_EasyCart_Elementor_Condition_In_Category',
				'WP_EasyCart_Elementor_Condition_Category',
				'WP_EasyCart_Elementor_Condition_Manufacturer',
				'WP_EasyCart_Elementor_Condition_Store_Page',
				'WP_EasyCart_Elementor_Condition_Cart_Page',
				'WP_EasyCart_Elementor_Condition_Account_Page',
			) as $class_name ) {
				$this->register_sub_condition( new $class_name() );
			}
		}

		/**
		 * Any WP EasyCart page.
		 *
		 * @param array $args Unused.
		 * @return bool
		 */
		public function check( $args ) {
			return WP_EasyCart_Elementor_Templates::condition_check( 'store' );
		}
	}

	/**
	 * Products ( any, or one ).
	 */
	class WP_EasyCart_Elementor_Condition_Product extends WP_EasyCart_Elementor_Condition_Base {

		/**
		 * Name ( stored ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Product', 'wp-easycart' );
		}

		/**
		 * "All" label.
		 *
		 * @return string
		 */
		public function get_all_label() {
			return esc_html__( 'All products', 'wp-easycart' );
		}

		/**
		 * Check.
		 *
		 * @return string
		 */
		protected function ec_check() {
			return 'product';
		}

		/**
		 * Picker.
		 *
		 * @return string
		 */
		protected function ec_picker() {
			return 'product';
		}
	}

	/**
	 * Products in a category.
	 */
	class WP_EasyCart_Elementor_Condition_In_Category extends WP_EasyCart_Elementor_Condition_Base {

		/**
		 * Name ( stored ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_product_in_category';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Products in category', 'wp-easycart' );
		}

		/**
		 * Check.
		 *
		 * @return string
		 */
		protected function ec_check() {
			return 'in_category';
		}

		/**
		 * Picker.
		 *
		 * @return string
		 */
		protected function ec_picker() {
			return 'category';
		}
	}

	/**
	 * Category pages ( any, or one ).
	 */
	class WP_EasyCart_Elementor_Condition_Category extends WP_EasyCart_Elementor_Condition_Base {

		/**
		 * Name ( stored ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_category';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Category page', 'wp-easycart' );
		}

		/**
		 * "All" label.
		 *
		 * @return string
		 */
		public function get_all_label() {
			return esc_html__( 'All category pages', 'wp-easycart' );
		}

		/**
		 * Check.
		 *
		 * @return string
		 */
		protected function ec_check() {
			return 'category';
		}

		/**
		 * Picker.
		 *
		 * @return string
		 */
		protected function ec_picker() {
			return 'category';
		}
	}

	/**
	 * Manufacturer pages ( any, or one ).
	 */
	class WP_EasyCart_Elementor_Condition_Manufacturer extends WP_EasyCart_Elementor_Condition_Base {

		/**
		 * Name ( stored ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_manufacturer';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Manufacturer page', 'wp-easycart' );
		}

		/**
		 * "All" label.
		 *
		 * @return string
		 */
		public function get_all_label() {
			return esc_html__( 'All manufacturer pages', 'wp-easycart' );
		}

		/**
		 * Check.
		 *
		 * @return string
		 */
		protected function ec_check() {
			return 'manufacturer';
		}

		/**
		 * Picker.
		 *
		 * @return string
		 */
		protected function ec_picker() {
			return 'manufacturer';
		}
	}

	/**
	 * The store page.
	 */
	class WP_EasyCart_Elementor_Condition_Store_Page extends WP_EasyCart_Elementor_Condition_Base {

		/**
		 * Name ( stored ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_store_page';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Store page', 'wp-easycart' );
		}

		/**
		 * Check.
		 *
		 * @return string
		 */
		protected function ec_check() {
			return 'store_page';
		}
	}

	/**
	 * The cart page.
	 */
	class WP_EasyCart_Elementor_Condition_Cart_Page extends WP_EasyCart_Elementor_Condition_Base {

		/**
		 * Name ( stored ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_cart_page';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Cart page', 'wp-easycart' );
		}

		/**
		 * Check.
		 *
		 * @return string
		 */
		protected function ec_check() {
			return 'cart_page';
		}
	}

	/**
	 * The account page.
	 */
	class WP_EasyCart_Elementor_Condition_Account_Page extends WP_EasyCart_Elementor_Condition_Base {

		/**
		 * Name ( stored ).
		 *
		 * @return string
		 */
		public function get_name() {
			return 'wp_easycart_account_page';
		}

		/**
		 * Label.
		 *
		 * @return string
		 */
		public function get_label() {
			return esc_html__( 'Account page', 'wp-easycart' );
		}

		/**
		 * Check.
		 *
		 * @return string
		 */
		protected function ec_check() {
			return 'account_page';
		}
	}

endif;
