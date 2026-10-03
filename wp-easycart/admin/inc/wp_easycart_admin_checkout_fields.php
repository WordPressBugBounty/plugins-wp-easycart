<?php
/**
 * Settings › Checkout fields without WP EasyCart PRO 6.0.2 ( 6.0.2 ).
 *
 * The page is declared in admin/template/settings/checkout-fields.php. Until WP EasyCart PRO 6.0.2 is active and
 * licensed, its section shows the feature strip and a locked example of the builder: the checkout's placements with a
 * few typical questions. WP EasyCart PRO replaces the section's render and enqueue callables with the builder.
 *
 * @since 6.0.2
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_checkout_fields' ) ) :

	/**
	 * The locked Checkout fields page.
	 *
	 * @since 6.0.2
	 */
	final class wp_easycart_admin_checkout_fields {

		/**
		 * Section 'enqueue' callable: the locked example's styles.
		 *
		 * @param array $page    Declaration.
		 * @param array $section Section.
		 */
		public static function enqueue_locked( $page = array(), $section = array() ) {
			wp_enqueue_style( 'wp-easycart-checkout-fields-locked', plugins_url( '../css/checkout-fields-locked-v2.css', __FILE__ ), array(), EC_CURRENT_VERSION );
		}

		/**
		 * Section 'render' callable: what the feature does, then an example of the builder under a soft lock ( a click
		 * opens the upgrade popup, or the update wording when WP EasyCart PRO is older than 6.0.2 ).
		 *
		 * @param array $page    Declaration.
		 * @param array $section Section.
		 */
		public static function render_locked( $page, $section ) {
			if ( class_exists( 'wp_easycart_admin_upsell' ) ) {
				wp_easycart_admin_upsell::print_feature_strip( 'checkout_fields' );
			}
			$onclick = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( 'checkout_fields' ) : 'return false;';
			$places  = array(
				array(
					__( 'Contact details', 'wp-easycart' ),
					array(
						array( __( 'How did you hear about us?', 'wp-easycart' ), __( 'Dropdown', 'wp-easycart' ), '' ),
						array( __( 'Tell us where', 'wp-easycart' ), __( 'Text', 'wp-easycart' ), __( 'When "Other"', 'wp-easycart' ) ),
					),
				),
				array(
					__( 'Shipping address', 'wp-easycart' ),
					array( array( __( 'Delivery instructions', 'wp-easycart' ), __( 'Paragraph', 'wp-easycart' ), '' ) ),
				),
				array(
					__( 'Delivery', 'wp-easycart' ),
					array( array( __( 'Preferred delivery date', 'wp-easycart' ), __( 'Date', 'wp-easycart' ), __( 'Local delivery · Required', 'wp-easycart' ) ) ),
				),
				array(
					__( 'Order notes', 'wp-easycart' ),
					array( array( __( 'Leave at the door if nobody answers', 'wp-easycart' ), __( 'Checkbox', 'wp-easycart' ), '' ) ),
				),
				array(
					__( 'Review and place order', 'wp-easycart' ),
					array( array( __( 'Workshop waiver', 'wp-easycart' ), __( 'Consent', 'wp-easycart' ), __( 'Cart has workshops · Required', 'wp-easycart' ) ) ),
				),
			);
			?>
			<div class="eccf-lock" role="button" tabindex="0" onclick="<?php echo esc_attr( $onclick ); ?>" onkeydown="if ( 'Enter' === event.key || ' ' === event.key ) { <?php echo esc_attr( $onclick ); ?> }" aria-label="<?php esc_attr_e( 'Checkout fields example: see what is included', 'wp-easycart' ); ?>">
				<?php foreach ( $places as $place ) : ?>
				<div class="eccf-lock-place">
					<div class="eccf-lock-head"><?php echo esc_html( $place[0] ); ?></div>
					<?php foreach ( $place[1] as $row ) : ?>
					<div class="eccf-lock-row">
						<span class="eccf-lock-label"><?php echo esc_html( $row[0] ); ?><em><?php echo esc_html( $row[1] ); ?></em></span>
						<?php
						if ( '' !== $row[2] ) :
							?>
							<span class="eccf-lock-tag"><?php echo esc_html( $row[2] ); ?></span><?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endforeach; ?>
			</div>
			<p class="eccf-lock-note"><?php esc_html_e( 'Each field works the same in the classic and the one-page checkout, and its answers are kept on the order: on the order screen, in the emails, on the packing slip and invoice, in My Account and in the order export.', 'wp-easycart' ); ?></p>
			<?php
		}

		/**
		 * The link to this page from Settings › Checkout › Checkout form.
		 *
		 * @return string URL.
		 */
		public static function url() {
			return function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=wp-easycart-settings&subpage=checkout-fields' ) : '';
		}
	}

endif;
