<?php
/**
 * WP EasyCart Ajax Select2 for Elementor
 *
 * @category Class
 * @package  WPEasyCart_Control_Ajax_Select2
 * @author   WP EasyCart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WP EasyCart Ajax Select2 for Elementor
 *
 * @category Class
 * @package  WPEasyCart_Control_Ajax_Select2
 * @author   WP EasyCart
 */
class WPEasyCart_Control_Ajax_Select2 extends \Elementor\Base_Data_Control {

	/**
	 * Get select2 control type.
	 */
	public function get_type() {
		return 'wpecajaxselect2';
	}

	/**
	 * Get select2 control default settings.
	 */
	protected function get_default_settings() {
		return array(
			'options'        => array(),
			'select2options' => array(),
			'multiple'       => false,
		);
	}

	/**
	 * Enqueue control scripts and styles.
	 */
	public function enqueue() {
		/* 6.0.2: resolved from the plugin's main file, so it loads whatever the plugin folder is called. */
		wp_register_script( 'wpecajaxselect2-editor', plugins_url( 'admin/elementor/wp-easycart-elementor-ajaxselect2.js', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' ), array( 'jquery' ), EC_CURRENT_VERSION, false );
		wp_enqueue_script( 'wpecajaxselect2-editor' );
	}


	/**
	 * Render select2 control output in the editor.
	 */
	public function content_template() {
		$control_uid = $this->get_control_uid();
		/* 6.0.2: rest_url() works with plain permalinks ( ?rest_route= ), a WordPress in its own directory and a custom REST prefix. */
		$restapi = rest_url( 'wp-easycart/v1/' );
		?>
		<div class="elementor-control-field">
			<label for="<?php echo esc_attr( $control_uid ); ?>" class="elementor-control-title">{{{ data.label }}}</label>
			<div class="elementor-control-input-wrapper">
				<# var multiple = ( data.multiple ) ? 'multiple' : ''; #>
				<select 
					id="<?php echo esc_attr( $control_uid ); ?>"
					class="elementor-ajaxselect2" 
					type="wpecajaxselect2" {{ multiple }} 
					data-setting="{{ data.name }}"
					data-ajax-url="<?php echo esc_url( $restapi ); ?>{{ data.options }}/"
					data-ajax-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
				>
				</select>
			</div>
		</div>
		<# if ( data.description ) { #>
			<div class="elementor-control-field-description">{{{ data.description }}}</div>
		<# } #>
		<?php
	}
}
