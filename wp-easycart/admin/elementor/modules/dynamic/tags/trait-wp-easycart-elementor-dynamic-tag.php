<?php
/**
 * What every WP EasyCart dynamic tag shares ( 6.0.2 ): the "WP EasyCart" group, the product picker and the V4 flag.
 *
 * Controls use only the types Elementor converts for V4 ( atomic ) elements ( select, text, textarea, switcher, number ... )
 * and every one has a label and a default, so the same tags work in atomic elements. The product picker is a select of
 * products built only in the editor ( WP_EasyCart_Elementor_Dynamic::product_options() ) plus a product ID field for stores
 * with more products than the list shows.
 *
 * Loaded inside elementor/dynamic_tags/register only.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( 'WP_EasyCart_Elementor_Dynamic_Tag_Trait' ) ) :

	/**
	 * Shared tag behaviour.
	 */
	trait WP_EasyCart_Elementor_Dynamic_Tag_Trait {

		/**
		 * Tag group.
		 *
		 * @return string
		 */
		public function get_group() {
			return WP_EasyCart_Elementor_Dynamic::GROUP;
		}

		/**
		 * Editor config: V4 ( atomic ) elements keep the tag even if a future control can't be converted.
		 *
		 * @return array
		 */
		public function get_editor_config() {
			$config                            = parent::get_editor_config();
			$config['force_convert_to_atomic'] = true;
			return $config;
		}

		/**
		 * The product controls ( last in the tag's settings: most tags show the product of the page ).
		 */
		protected function ec_add_product_controls() {
			$this->add_control(
				'product_source',
				array(
					'label'   => __( 'Product', 'wp-easycart' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'current',
					'options' => array(
						'current' => __( 'The product of the page it is on', 'wp-easycart' ),
						'pick'    => __( 'A product I choose', 'wp-easycart' ),
					),
				)
			);
			$this->add_control(
				'product_id',
				array(
					'label'     => __( 'Choose product', 'wp-easycart' ),
					'type'      => \Elementor\Controls_Manager::SELECT,
					'default'   => '',
					'options'   => WP_EasyCart_Elementor_Dynamic::product_options(),
					'condition' => array( 'product_source' => 'pick' ),
				)
			);
			$this->add_control(
				'product_id_manual',
				array(
					'label'       => __( 'Or product ID', 'wp-easycart' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'default'     => '',
					'min'         => 0,
					'step'        => 1,
					'description' => __( 'Only needed when the product is not in the list.', 'wp-easycart' ),
					'condition'   => array( 'product_source' => 'pick' ),
				)
			);
		}

		/**
		 * The product this tag shows ( null: nothing to show ).
		 *
		 * @return ec_product|null
		 */
		protected function ec_product() {
			return WP_EasyCart_Elementor_Dynamic::product( (array) $this->get_settings() );
		}

		/**
		 * One setting as a string.
		 *
		 * @param string $key      Setting.
		 * @param string $fallback When missing.
		 * @return string
		 */
		protected function ec_setting( $key, $fallback = '' ) {
			$value = $this->get_settings( $key );
			return ( is_scalar( $value ) && '' !== (string) $value ) ? (string) $value : $fallback;
		}

		/**
		 * One switcher setting ( 'yes' in the classic editor, true from a V4 switch ).
		 *
		 * @param string $key Setting.
		 * @return bool
		 */
		protected function ec_switch( $key ) {
			$value = $this->get_settings( $key );
			return 'yes' === $value || true === $value;
		}
	}

endif;
