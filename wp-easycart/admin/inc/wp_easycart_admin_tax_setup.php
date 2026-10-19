<?php
/**
 * WP EasyCart admin: recommended tax settings by store country ( 6.0.5 ). The card at the top of Settings › Taxes ( what would change,
 * Apply, Keep my settings, Undo ), the setup wizard's tax summary, and the preview both ask for as choices change ( AJAX
 * ecv2_tax_setup_preview ). The settings themselves: wp_easycart_tax_setup ( core ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_tax_setup_guard' ) ) {
	/**
	 * The preview's guard: the nonce, and a person who may change settings.
	 *
	 * @return bool
	 */
	function ecv2_tax_setup_guard() {
		if ( ! check_ajax_referer( 'wp-easycart-ecv2-tax-setup', 'nonce', false ) ) {
			return false;
		}
		return current_user_can( 'manage_options' ) || current_user_can( 'wpec_settings' );
	}
}

if ( ! class_exists( 'wp_easycart_admin_tax_setup' ) ) :

	/**
	 * The recommendation's admin surfaces.
	 */
	class wp_easycart_admin_tax_setup {

		/**
		 * Hooks.
		 */
		public static function init() {
			add_action( 'wp_ajax_ecv2_tax_setup_preview', array( __CLASS__, 'ajax_preview' ) );
		}

		/**
		 * Whether the core class is there ( an older core file kept by a partial update has none ).
		 *
		 * @return bool
		 */
		public static function ready() {
			return class_exists( 'wp_easycart_tax_setup' );
		}

		/**
		 * The store's recommendation and what it would change.
		 *
		 * @param string|null $input A control's value ( "state:TX|eu:oss" ).
		 * @return array rec, changes
		 */
		public static function state( $input = null ) {
			$cc      = wp_easycart_tax_setup::store_country();
			$choices = ( null === $input ) ? null : wp_easycart_tax_setup::choices_with( $cc, $input );
			$rec     = wp_easycart_tax_setup::recommend( $cc, $choices );
			return array(
				'rec'     => $rec,
				'changes' => $rec['supported'] ? wp_easycart_tax_setup::changes( $rec ) : array(),
			);
		}

		/**
		 * Whether Settings › Taxes shows the card: changes to offer ( not kept ), an undo, or a country without a recommendation and no
		 * tax set up.
		 *
		 * @return bool
		 */
		public static function card_needed() {
			if ( ! self::ready() ) {
				return false;
			}
			if ( self::recent_apply() ) {
				return true;
			}
			$cc = wp_easycart_tax_setup::store_country();
			if ( '' === $cc ) {
				return false;
			}
			if ( ! wp_easycart_tax_setup::supported( $cc ) ) {
				return ! wp_easycart_tax_setup::tax_in_use();
			}
			$state = self::state();
			return $state['changes'] && ! wp_easycart_tax_setup::is_kept( $state['rec'] );
		}

		/**
		 * The snapshot of an apply made in the last day ( the card offers Undo ).
		 *
		 * @return array|null
		 */
		public static function recent_apply() {
			$snap = wp_easycart_tax_setup::undo_available();
			return ( $snap && (int) $snap['time'] > time() - DAY_IN_SECONDS ) ? $snap : null;
		}

		/**
		 * The section's render ( Settings › Taxes, first section ).
		 */
		public static function render_card() {
			if ( ! self::ready() ) {
				return;
			}
			$snap  = self::recent_apply();
			$state = self::state();
			$rec   = $state['rec'];
			$count = count( $state['changes'] );
			echo '<div class="ectx-setup" id="ectx_setup" data-nonce="' . esc_attr( wp_create_nonce( 'wp-easycart-ecv2-tax-setup' ) ) . '">';
			if ( $snap ) {
				?>
				<div class="ecst-action ectx-setup-done">
					<div class="ecst-action-text">
						<b><?php esc_html_e( 'Tax settings updated', 'wp-easycart' ); ?></b>
						<span><?php esc_html_e( 'Checkout uses the recommended settings now. Changed your mind? Undo puts back what you had.', 'wp-easycart' ); ?></span>
					</div>
					<button type="button" class="ecv2-btn" data-action="tax_setup_undo" data-sec="setup" data-confirm-title="<?php esc_attr_e( 'Put back your previous tax settings?', 'wp-easycart' ); ?>" data-confirm="<?php esc_attr_e( 'Every tax setting the recommendation changed goes back to what it was before.', 'wp-easycart' ); ?>" data-confirm-ok="<?php esc_attr_e( 'Undo', 'wp-easycart' ); ?>"><?php esc_html_e( 'Undo', 'wp-easycart' ); ?></button>
				</div>
				<?php
			}
			if ( ! $rec['supported'] ) {
				if ( ! wp_easycart_tax_setup::tax_in_use() ) {
					/* translators: %s: country */
					echo '<p class="ectx-setup-none">' . esc_html( sprintf( __( 'WP EasyCart has no recommended tax settings for %s yet. Set up the tax you collect below: VAT, rates by country or a flat rate.', 'wp-easycart' ), $rec['country_name'] ) ) . '</p>';
				}
				echo '</div>';
				return;
			}
			if ( ! $count || wp_easycart_tax_setup::is_kept( $rec ) ) {
				echo '</div>';
				return;
			}
			$place = $rec['country_name'] . ( '' !== $rec['state_name'] ? ', ' . $rec['state_name'] : '' );
			?>
			<div class="ectx-setup-card">
				<div class="ectx-setup-head">
					<b><?php esc_html_e( 'Recommended tax settings for your country', 'wp-easycart' ); ?></b>
					<span><?php echo esc_html( $place ); ?> · <?php /* translators: %s: date */ echo esc_html( sprintf( __( 'rates checked %s', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), strtotime( wp_easycart_tax_setup::CHECKED ) ) ) ); ?></span>
				</div>
				<p class="ectx-setup-lede"><?php esc_html_e( 'Most stores in your country use these settings. You can change anything afterwards, and undo it in one click.', 'wp-easycart' ); ?></p>
				<?php echo self::choices_html( $rec ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- choices_html() escapes every part. ?>
				<div class="ectx-setup-changes" data-ectx-changes aria-live="polite"><?php echo self::changes_html( $rec, $state['changes'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- changes_html() escapes every part. ?></div>
				<div class="ecst-action ectx-setup-actions">
					<input type="hidden" class="ecst-action-input" value="<?php echo esc_attr( self::choice_string( $rec ) ); ?>">
					<div class="ecst-action-text"></div>
					<button type="button" class="ecv2-btn ecv2-btn-primary" data-action="tax_setup_apply" data-sec="setup" data-ectx-apply data-confirm-title="<?php esc_attr_e( 'Apply the recommended tax settings?', 'wp-easycart' ); ?>" data-confirm="<?php esc_attr_e( 'Checkout uses them from now on. You can undo this afterwards, and change any setting yourself.', 'wp-easycart' ); ?>" data-confirm-ok="<?php esc_attr_e( 'Apply', 'wp-easycart' ); ?>"><?php echo esc_html( self::apply_label( $count ) ); ?></button>
					<button type="button" class="ecv2-btn" data-action="tax_setup_keep" data-sec="setup"><?php esc_html_e( 'Keep my settings', 'wp-easycart' ); ?></button>
				</div>
				<?php echo self::notes_html( $rec ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notes_html() escapes every part. ?>
			</div>
			<?php
			echo '</div>';
		}

		/**
		 * "Apply 3 changes".
		 *
		 * @param int $count Changes.
		 * @return string
		 */
		public static function apply_label( $count ) {
			/* translators: %d: number of changes */
			return sprintf( _n( 'Apply %d change', 'Apply %d changes', (int) $count, 'wp-easycart' ), (int) $count );
		}

		/**
		 * The choices the card offers: the state ( US ), the province and PST ( Canada ), the EU mode.
		 *
		 * @param array $rec Recommendation.
		 * @return string HTML.
		 */
		public static function choices_html( $rec ) {
			$choices = $rec['choices'];
			$html    = '';
			if ( in_array( $rec['system'], array( 'us', 'canada' ), true ) ) {
				$label = ( 'us' === $rec['system'] ) ? __( 'Your state', 'wp-easycart' ) : __( 'Your province', 'wp-easycart' );
				$html .= '<label class="ectx-setup-choice"><span>' . esc_html( $label ) . '</span><select data-ectx-choice="state"><option value="">' . esc_html__( 'Choose…', 'wp-easycart' ) . '</option>';
				foreach ( wp_easycart_tax_setup::regions( $rec['country'] ) as $code => $name ) {
					$html .= '<option value="' . esc_attr( $code ) . '"' . selected( $code, $choices['state'], false ) . '>' . esc_html( $name ) . '</option>';
				}
				$html .= '</select></label>';
			}
			if ( 'canada' === $rec['system'] ) {
				$html .= '<label class="ectx-setup-choice"><span>' . esc_html__( 'PST / QST', 'wp-easycart' ) . '</span><select data-ectx-choice="pst">';
				$html .= '<option value="home"' . selected( 'home', $choices['pst'], false ) . '>' . esc_html__( 'Only in my own province', 'wp-easycart' ) . '</option>';
				$html .= '<option value="all"' . selected( 'all', $choices['pst'], false ) . '>' . esc_html__( 'Every PST province ( registered in each )', 'wp-easycart' ) . '</option>';
				$html .= '</select></label>';
			}
			if ( 'vat' === $rec['system'] && class_exists( 'wp_easycart_vat' ) && wp_easycart_vat::in_eu( $rec['country'] ) ) {
				$html .= '<label class="ectx-setup-choice"><span>' . esc_html__( 'Consumers in other EU countries', 'wp-easycart' ) . '</span><select data-ectx-choice="eu">';
				$html .= '<option value="home"' . selected( 'home', $choices['eu'], false ) . '>' . esc_html__( 'Under €10,000 a year: my country\'s VAT', 'wp-easycart' ) . '</option>';
				$html .= '<option value="oss"' . selected( 'oss', $choices['eu'], false ) . '>' . esc_html__( 'Over €10,000, or One-Stop Shop: each country\'s VAT', 'wp-easycart' ) . '</option>';
				$html .= '</select></label>';
			}
			return '' === $html ? '' : '<div class="ectx-setup-choices">' . $html . '</div>';
		}

		/**
		 * The choices as the action sends them ( "state:TX|pst:home" ).
		 *
		 * @param array $rec Recommendation.
		 * @return string
		 */
		public static function choice_string( $rec ) {
			$parts = array();
			if ( in_array( $rec['system'], array( 'us', 'canada' ), true ) ) {
				$parts[] = 'state:' . $rec['choices']['state'];
			}
			if ( 'canada' === $rec['system'] ) {
				$parts[] = 'pst:' . $rec['choices']['pst'];
			}
			if ( 'vat' === $rec['system'] ) {
				$parts[] = 'eu:' . $rec['choices']['eu'];
				$parts[] = 'included:' . (int) $rec['choices']['included'];
			}
			return implode( '|', $parts );
		}

		/**
		 * The changes, each with the value now, the value after and why.
		 *
		 * @param array $rec     Recommendation.
		 * @param array $changes Changes.
		 * @return string HTML.
		 */
		public static function changes_html( $rec, $changes ) {
			if ( ! $changes ) {
				return '<p class="ectx-setup-ok">' . esc_html__( 'Your settings already match.', 'wp-easycart' ) . '</p>';
			}
			$html = '<ul class="ectx-setup-list">';
			foreach ( $changes as $change ) {
				$html .= '<li><span class="ectx-setup-label">' . esc_html( $change['label'] ) . '</span>';
				$html .= '<span class="ectx-setup-ft"><s>' . esc_html( $change['from'] ) . '</s> <span aria-hidden="true">→</span><span class="screen-reader-text"> ' . esc_html__( 'becomes', 'wp-easycart' ) . ' </span> <b>' . esc_html( $change['to'] ) . '</b></span>';
				$html .= '<span class="ectx-setup-why">' . esc_html( $change['why'] ) . '</span></li>';
			}
			return $html . '</ul>';
		}

		/**
		 * The notes and where the facts come from.
		 *
		 * @param array $rec Recommendation.
		 * @return string HTML.
		 */
		public static function notes_html( $rec ) {
			$html = '';
			if ( $rec['notes'] ) {
				$html .= '<ul class="ectx-setup-notes">';
				foreach ( $rec['notes'] as $note ) {
					$html .= '<li>' . esc_html( $note ) . '</li>';
				}
				$html .= '</ul>';
			}
			$links = array();
			foreach ( $rec['sources'] as $label => $url ) {
				$links[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
			}
			$html .= '<p class="ectx-setup-fine">' . esc_html__( 'Rates and rules from the tax authorities, checked by WP EasyCart. This isn\'t tax advice: if your situation is unusual, check with your accountant.', 'wp-easycart' ) . ( $links ? ' ' . implode( ' · ', $links ) : '' ) . '</p>';
			return $html;
		}

		/**
		 * The setup wizard's summary for a country: what will be set, and what would change in tax set up before.
		 *
		 * @param array $rec Recommendation.
		 * @return string HTML.
		 */
		public static function wizard_html( $rec ) {
			if ( ! $rec['supported'] ) {
				return '<p class="ecwz-tax-none">' . esc_html__( 'We don\'t have recommended tax settings for this country yet. Set up the tax you collect under Settings › Taxes.', 'wp-easycart' ) . '</p>';
			}
			$html = '';
			if ( $rec['summary'] ) {
				/* 6.0.5: no empty box while a US or Canadian store has no state or province yet ( its note asks for one ). */
				$html = '<ul class="ecwz-tax-lines">';
				foreach ( $rec['summary'] as $line ) {
					$html .= '<li>' . esc_html( $line ) . '</li>';
				}
				$html .= '</ul>';
			}
			if ( wp_easycart_tax_setup::tax_in_use() ) {
				$changes = wp_easycart_tax_setup::changes( $rec );
				if ( $changes ) {
					/* translators: %d: number of changes */
					$html .= '<p class="ecwz-tax-changes-title">' . esc_html( sprintf( _n( 'Your tax settings would change in %d place:', 'Your tax settings would change in %d places:', count( $changes ), 'wp-easycart' ), count( $changes ) ) ) . '</p>' . self::changes_html( $rec, $changes );
				} else {
					$html .= '<p class="ecwz-tax-changes-title">' . esc_html__( 'Your tax settings already match.', 'wp-easycart' ) . '</p>';
				}
			}
			if ( $rec['notes'] ) {
				$html .= '<ul class="ecwz-tax-notes">';
				foreach ( $rec['notes'] as $note ) {
					$html .= '<li>' . esc_html( $note ) . '</li>';
				}
				$html .= '</ul>';
			}
			return $html;
		}

		/**
		 * AJAX ecv2_tax_setup_preview: country, input ( "state:TX|eu:oss" ), context card | wizard. Answers html, count, button.
		 */
		public static function ajax_preview() {
			if ( ! ecv2_tax_setup_guard() ) {
				wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page.', 'wp-easycart' ) ), 403 );
			}
			if ( ! self::ready() ) {
				wp_send_json_error( array( 'message' => __( 'This needs WP EasyCart 6.0.5.', 'wp-easycart' ) ) );
			}
			$cc      = isset( $_POST['country'] ) ? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', sanitize_text_field( wp_unslash( $_POST['country'] ) ) ), 0, 2 ) ) : wp_easycart_tax_setup::store_country();
			$input   = isset( $_POST['input'] ) ? sanitize_text_field( wp_unslash( $_POST['input'] ) ) : '';
			$context = ( isset( $_POST['context'] ) && 'wizard' === sanitize_key( wp_unslash( $_POST['context'] ) ) ) ? 'wizard' : 'card';
			$choices = wp_easycart_tax_setup::choices_with( $cc, $input );
			$rec     = wp_easycart_tax_setup::recommend( $cc, $choices );
			$changes = $rec['supported'] ? wp_easycart_tax_setup::changes( $rec ) : array();
			wp_send_json_success(
				array(
					'html'      => ( 'wizard' === $context ) ? self::wizard_html( $rec ) : self::changes_html( $rec, $changes ),
					'notes'     => self::notes_html( $rec ),
					'count'     => count( $changes ),
					'button'    => self::apply_label( count( $changes ) ),
					'supported' => (bool) $rec['supported'],
					'name'      => $rec['name'],
				)
			);
		}

		/**
		 * Action tax_setup_apply ( the card ): the recommendation with the card's choices.
		 *
		 * @param array  $action Action.
		 * @param array  $page   Page.
		 * @param string $input  The card's choices ( "state:TX|eu:oss" ).
		 * @return array|string|WP_Error
		 */
		public static function action_apply( $action = array(), $page = array(), $input = '' ) {
			if ( ! self::ready() ) {
				return __( 'This needs WP EasyCart 6.0.5.', 'wp-easycart' );
			}
			$cc      = wp_easycart_tax_setup::store_country();
			$choices = wp_easycart_tax_setup::choices_with( $cc, (string) $input );
			if ( in_array( wp_easycart_tax_setup::system( $cc ), array( 'us', 'canada' ), true ) && '' === $choices['state'] ) {
				return new WP_Error( 'state', ( 'US' === $cc ) ? __( 'Choose your state first.', 'wp-easycart' ) : __( 'Choose your province first.', 'wp-easycart' ) );
			}
			$done = wp_easycart_tax_setup::apply( $cc, $choices );
			if ( is_wp_error( $done ) ) {
				return $done;
			}
			return array(
				/* translators: %d: number of changes */
				'message' => sprintf( _n( 'Tax settings updated: %d change.', 'Tax settings updated: %d changes.', (int) $done, 'wp-easycart' ), (int) $done ),
				'reload'  => true,
			);
		}

		/**
		 * Action tax_setup_keep: hide the card until the recommendation changes.
		 *
		 * @param array  $action Action.
		 * @param array  $page   Page.
		 * @param string $input  The card's choices.
		 * @return array|string
		 */
		public static function action_keep( $action = array(), $page = array(), $input = '' ) {
			if ( ! self::ready() ) {
				return __( 'This needs WP EasyCart 6.0.5.', 'wp-easycart' );
			}
			$state = self::state( (string) $input );
			wp_easycart_tax_setup::save_choices( $state['rec']['country'], $state['rec']['choices'] );
			wp_easycart_tax_setup::keep( wp_easycart_tax_setup::recommend() );
			return array(
				'message' => __( 'Your settings stay as they are. The recommendation shows again if rates change in an update.', 'wp-easycart' ),
				'reload'  => true,
			);
		}

		/**
		 * Action tax_setup_undo: put back what the last apply changed.
		 *
		 * @return array|WP_Error
		 */
		public static function action_undo() {
			if ( ! self::ready() ) {
				return new WP_Error( 'old', __( 'This needs WP EasyCart 6.0.5.', 'wp-easycart' ) );
			}
			$done = wp_easycart_tax_setup::undo();
			if ( is_wp_error( $done ) ) {
				return $done;
			}
			wp_easycart_tax_setup::keep( wp_easycart_tax_setup::recommend() );
			return array(
				'message' => __( 'Your previous tax settings are back.', 'wp-easycart' ),
				'reload'  => true,
			);
		}

		/**
		 * The launch checklist's item ( the setup wizard's "Launch" list ), or null for a country without a recommendation.
		 *
		 * @return array|null
		 */
		public static function checklist_item() {
			if ( ! self::ready() ) {
				return null;
			}
			$cc = wp_easycart_tax_setup::store_country();
			if ( '' === $cc || ! wp_easycart_tax_setup::supported( $cc ) ) {
				return null;
			}
			$state = self::state();
			$done  = ! $state['changes'] || wp_easycart_tax_setup::is_kept( $state['rec'] );
			return array(
				/* translators: %s: country */
				'label'        => sprintf( __( 'Set up tax for %s', 'wp-easycart' ), $state['rec']['country_name'] ),
				/* translators: %d: number of changes */
				'sub'          => $done ? __( 'Your tax settings match your country', 'wp-easycart' ) : sprintf( _n( '%d recommended change to review', '%d recommended changes to review', count( $state['changes'] ), 'wp-easycart' ), count( $state['changes'] ) ),
				'done'         => $done,
				'action_label' => __( 'Review', 'wp-easycart' ),
				'action_url'   => admin_url( 'admin.php?page=wp-easycart-settings&subpage=tax' ),
			);
		}
	}

	wp_easycart_admin_tax_setup::init();

endif;
