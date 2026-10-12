<?php
/**
 * The withdrawal page ( the EU "withdraw from contract here" function, 6.0.4 ).
 *
 * Drawn by wp_easycart_withdrawals::render() ( shortcode [ec_withdrawal], or the account page with ec_page=withdrawal ) with
 * $withdrawal ( wp_easycart_withdrawals::view_data() ):
 *   view     start ( the signed-in customer's orders, then a form for an order number and email ) | form ( one order's items,
 *            from its link; with no order: the form without a link again ) | review ( what will be sent, Confirm withdrawal ) |
 *            received | already | closed | lookup ( the order lookup ) | off ( the button is switched off: the lookup only )
 *   heading  print the page heading ( the account page; a page's own title usually is the heading )
 *   errors   messages ( plain text )
 *   values   order, key, order_ref, name, email, reason, note, items ( orderdetail_id => units )
 *   order    the order row, summary ( wp_easycart_withdrawals::order_summary() ), record ( a statement ), check ( the robot
 *            check: provider, site_key ), action ( where the forms post ), lookup, ajax_url, language, next.
 *
 * The confirm button carries only the words the law sets ( "Confirm withdrawal", language section withdrawal ). The forms carry
 * no page nonce, so the page works when it is cached. Copy this file to your wp-easycart-data layout folder to customise it; the
 * file name and the field names must stay the same.
 *
 * @since 6.0.4
 * @package wp-easycart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- a template: it is included inside a method, so its variables are that method's locals.

$ec_wd         = 'wp_easycart_withdrawals';
$ec_wd_t       = function ( $key, $vars = array() ) {
	return wp_easycart_withdrawals::text( $key, $vars );
};
$ec_wd_view    = (string) $withdrawal['view'];
$ec_wd_values  = wp_parse_args(
	(array) $withdrawal['values'],
	array(
		'order'     => 0,
		'key'       => '',
		'order_ref' => '',
		'name'      => '',
		'email'     => '',
		'reason'    => '',
		'note'      => '',
		'items'     => array(),
	)
);
$ec_wd_order   = $withdrawal['order'];
$ec_wd_summary = $withdrawal['summary'];
$ec_wd_accent  = sanitize_hex_color( (string) get_option( 'ec_option_details_main_color' ) );
$ec_wd_accent  = $ec_wd_accent ? $ec_wd_accent : '#222222';
$ec_wd_dark    = (bool) get_option( 'ec_option_use_dark_bg' );
/* The form's hidden values, carried from step to step. */
$ec_wd_hidden = function ( $values, $with_items ) {
	$html  = '<input type="hidden" name="wpec_wd_order" value="' . esc_attr( (string) (int) $values['order'] ) . '" />';
	$html .= '<input type="hidden" name="wpec_wd_key" value="' . esc_attr( $values['key'] ) . '" />';
	$html .= '<input type="hidden" name="wpec_wd_order_ref" value="' . esc_attr( $values['order_ref'] ) . '" />';
	$html .= '<input type="hidden" name="wpec_wd_name" value="' . esc_attr( $values['name'] ) . '" />';
	$html .= '<input type="hidden" name="wpec_wd_email" value="' . esc_attr( $values['email'] ) . '" />';
	$html .= '<input type="hidden" name="wpec_wd_reason" value="' . esc_attr( $values['reason'] ) . '" />';
	$html .= '<input type="hidden" name="wpec_wd_note" value="' . esc_attr( $values['note'] ) . '" />';
	if ( $with_items ) {
		foreach ( (array) $values['items'] as $line_id => $units ) {
			$html .= '<input type="hidden" name="wpec_wd_pick[' . esc_attr( (string) (int) $line_id ) . ']" value="1" />';
			$html .= '<input type="hidden" name="wpec_wd_items[' . esc_attr( (string) (int) $line_id ) . ']" value="' . esc_attr( (string) (int) $units ) . '" />';
		}
	}
	return $html;
};
/* Label / value rows as a definition list. */
$ec_wd_rows = function ( $rows ) {
	$html = '<dl class="ec-wd-summary">';
	foreach ( (array) $rows as $row ) {
		$html .= '<div><dt>' . esc_html( $row['label'] ) . '</dt><dd>' . nl2br( esc_html( $row['value'] ) ) . '</dd></div>';
	}
	return $html . '</dl>';
};
/* The robot check ( Settings › Checkout protection ), when the visitor has made several tries. */
$ec_wd_check = function ( $check ) {
	if ( empty( $check['site_key'] ) ) {
		return '';
	}
	$class = ( 'recaptcha' === $check['provider'] ) ? 'g-recaptcha' : 'cf-turnstile';
	return '<div class="ec-wd-check ' . esc_attr( $class ) . '" data-sitekey="' . esc_attr( $check['site_key'] ) . '"></div>';
};
?>
<div class="ec-wd<?php echo $ec_wd_dark ? ' is-dark' : ''; ?>" id="ec-wd" style="--ec-wd-accent:<?php echo esc_attr( $ec_wd_accent ); ?>;">
<style>
	.ec-wd { --ec-wd-text:inherit; --ec-wd-muted:#6b7280; --ec-wd-card:#fff; --ec-wd-border:#e5e7eb; --ec-wd-field:#d1d5db; max-width:720px; margin:0 auto; font-size:15px; line-height:1.55; }
	.ec-wd.is-dark { --ec-wd-muted:#d1d5db; --ec-wd-card:rgba(255,255,255,.06); --ec-wd-border:rgba(255,255,255,.18); --ec-wd-field:rgba(255,255,255,.35); }
	.ec-wd * { box-sizing:border-box; }
	.ec-wd h2, .ec-wd h3 { color:inherit; font-family:inherit; margin:0 0 8px 0; line-height:1.25; }
	.ec-wd h2 { font-size:24px; }
	.ec-wd h3 { font-size:18px; }
	.ec-wd p { margin:0 0 12px 0; }
	.ec-wd-card { background:var(--ec-wd-card); border:1px solid var(--ec-wd-border); border-radius:10px; padding:20px; margin:0 0 16px 0; }
	.ec-wd-muted, .ec-wd .ec_withdrawal_muted { color:var(--ec-wd-muted); font-size:14px; }
	.ec-wd-field { margin:0 0 14px 0; }
	.ec-wd-field label, .ec-wd-legend { display:block; font-size:14px; font-weight:600; margin:0 0 4px 0; color:inherit; }
	.ec-wd-field input[type="text"], .ec-wd-field input[type="email"], .ec-wd-field textarea, .ec-wd-qty select { width:100%; padding:9px 11px; border:1px solid var(--ec-wd-field); border-radius:6px; font-family:inherit; font-size:15px; background:#fff; color:#1f2937; }
	.ec-wd-field textarea { min-height:72px; }
	.ec-wd fieldset { border:0; margin:0 0 14px 0; padding:0; min-width:0; }
	.ec-wd-item { display:flex; flex-wrap:wrap; align-items:center; gap:8px 12px; padding:10px 0; border-bottom:1px solid var(--ec-wd-border); }
	.ec-wd-item:last-child { border-bottom:0; }
	.ec-wd-item label { display:flex; align-items:flex-start; gap:10px; flex:1 1 260px; margin:0; font-weight:400; }
	.ec-wd-item input[type="checkbox"] { width:18px; height:18px; margin:2px 0 0 0; flex:0 0 auto; }
	.ec-wd-item small { display:block; color:var(--ec-wd-muted); }
	.ec-wd-item.is-off { opacity:.7; }
	.ec-wd-qty { flex:0 0 auto; display:flex; align-items:center; gap:6px; font-size:14px; }
	.ec-wd-qty select { width:auto; padding:6px 8px; }
	.ec-wd-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:16px 0 0 0; }
	.ec-wd-button { display:inline-block; padding:12px 20px; border:2px solid var(--ec-wd-accent); border-radius:8px; background:var(--ec-wd-accent); color:#fff; font-family:inherit; font-size:16px; font-weight:700; line-height:1.2; cursor:pointer; text-transform:none; }
	.ec-wd-button:hover, .ec-wd-button:focus { filter:brightness(1.08); color:#fff; background:var(--ec-wd-accent); }
	.ec-wd-button.is-secondary { background:transparent; color:inherit; border-color:var(--ec-wd-field); font-weight:600; font-size:15px; }
	.ec-wd-button[disabled] { opacity:.6; cursor:default; }
	.ec-wd-button:focus-visible, .ec-wd input:focus-visible, .ec-wd select:focus-visible, .ec-wd textarea:focus-visible { outline:2px solid var(--ec-wd-accent); outline-offset:2px; }
	.ec-wd-notice { padding:12px 16px; border-radius:8px; margin:0 0 16px 0; }
	.ec-wd-notice ul { margin:0; padding:0 0 0 18px; }
	.ec-wd-notice.is-error { background:#fef2f2; color:#7f1d1d; }
	.ec-wd-notice.is-success { background:#ecfdf5; color:#14532d; }
	.ec-wd-notice.is-info { background:#eff6ff; color:#1e3a8a; }
	.ec-wd-summary { margin:0 0 12px 0; }
	.ec-wd-summary > div { display:grid; grid-template-columns:minmax(110px, 30%) 1fr; gap:12px; padding:8px 0; border-bottom:1px solid var(--ec-wd-border); }
	.ec-wd-summary dt { font-weight:600; margin:0; }
	.ec-wd-summary dd { margin:0; overflow-wrap:anywhere; }
	.ec-wd .screen-reader-text { position:absolute !important; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
	.ec-wd-hp { position:absolute !important; left:-9999px !important; width:1px; height:1px; overflow:hidden; }
	.ec-wd-check { margin:12px 0 0 0; }
	.ec-wd .ec_withdrawal_orders { list-style:none; margin:0 0 4px 0; padding:0; }
	.ec-wd .ec_withdrawal_order { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8px 16px; padding:10px 0; border-bottom:1px solid var(--ec-wd-border); }
	.ec-wd .ec_withdrawal_order .ec_withdrawal_button_wrap { margin:0; }
	.ec-wd .ec_withdrawal_subtitle { font-size:18px; }
	.ec-wd a { color:var(--ec-wd-accent); }
	.ec-wd.is-dark a { color:inherit; text-decoration:underline; }
	@media ( max-width:600px ) {
		.ec-wd-summary > div { grid-template-columns:1fr; gap:2px; }
		.ec-wd-card { padding:16px; }
	}
</style>
<?php if ( ! empty( $withdrawal['heading'] ) && 'lookup' !== $ec_wd_view && 'off' !== $ec_wd_view ) : ?>
	<h2 class="ec-wd-title"><?php echo esc_html( $ec_wd_t( 'page_title' ) ); ?></h2>
<?php endif; ?>
<?php if ( ! empty( $withdrawal['errors'] ) ) : ?>
	<div class="ec-wd-notice is-error" role="alert" tabindex="-1" id="ec-wd-errors">
		<ul>
			<?php foreach ( (array) $withdrawal['errors'] as $ec_wd_error ) : ?>
				<li><?php echo esc_html( $ec_wd_error ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>
<?php
if ( 'off' === $ec_wd_view || 'lookup' === $ec_wd_view ) :
	$ec_wd_lookup = wp_easycart_order_lookup::render(
		array(
			'heading'  => true,
			'embedded' => true,
		)
	);
	echo $ec_wd_lookup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the lookup template escapes every part.

elseif ( 'received' === $ec_wd_view && $withdrawal['record'] ) :
	$ec_wd_record = $withdrawal['record'];
	?>
	<div class="ec-wd-card" id="ec-wd-received" tabindex="-1">
		<h3><?php echo esc_html( $ec_wd_t( 'received_title' ) ); ?></h3>
		<div class="ec-wd-notice is-success" role="status">
		<?php
		echo esc_html(
			$ec_wd_t(
				'received_text',
				array(
					'datetime' => $ec_wd::when( strtotime( $ec_wd_record->submitted_at . ' UTC' ) ),
					'email'    => $ec_wd_record->customer_email,
				)
			)
		);
		?>
															</div>
		<?php echo $ec_wd_rows( $ec_wd::summary_rows( $ec_wd_record ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the rows are escaped as they are built. ?>
		<?php if ( '' !== (string) $withdrawal['next'] ) : ?>
			<h3><?php echo esc_html( $ec_wd_t( 'received_next' ) ); ?></h3>
			<p><?php echo nl2br( esc_html( (string) $withdrawal['next'] ) ); ?></p>
		<?php endif; ?>
	</div>
	<?php

elseif ( 'already' === $ec_wd_view && $withdrawal['record'] ) :
	$ec_wd_record = $withdrawal['record'];
	?>
	<div class="ec-wd-card">
		<h3><?php echo esc_html( $ec_wd_t( 'order_title', array( 'order_id' => (int) $ec_wd_record->order_id ) ) ); ?></h3>
		<div class="ec-wd-notice is-info" role="status"><?php echo esc_html( $ec_wd_t( 'already_text', array( 'datetime' => $ec_wd::when( strtotime( $ec_wd_record->submitted_at . ' UTC' ) ) ) ) ); ?></div>
		<?php echo $ec_wd_rows( $ec_wd::summary_rows( $ec_wd_record ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the rows are escaped as they are built. ?>
	</div>
	<?php

elseif ( 'closed' === $ec_wd_view ) :
	?>
	<div class="ec-wd-card">
		<?php if ( $ec_wd_summary ) : ?>
			<h3><?php echo esc_html( $ec_wd_t( 'order_title', array( 'order_id' => (int) $ec_wd_summary['id'] ) ) ); ?></h3>
		<?php endif; ?>
		<p><?php echo esc_html( $ec_wd_t( 'closed_text' ) ); ?></p>
	</div>
	<?php

elseif ( 'review' === $ec_wd_view ) :
	if ( $ec_wd_order ) {
		$ec_wd_window = $ec_wd::window( $ec_wd_order );
		$ec_wd_review = array(
			'order_ref' => (string) (int) $ec_wd_order->order_id,
			'name'      => $ec_wd_values['name'],
			'email'     => $ec_wd_values['email'],
			'reason'    => $ec_wd_values['reason'],
			'whole'     => $ec_wd::covers_whole( $ec_wd_window, $ec_wd_values['items'] ),
			'lines'     => $ec_wd_values['items'],
		);
	} else {
		$ec_wd_review = array(
			'order_ref' => $ec_wd_values['order_ref'],
			'name'      => $ec_wd_values['name'],
			'email'     => $ec_wd_values['email'],
			'reason'    => $ec_wd_values['reason'],
			'note'      => $ec_wd_values['note'],
			'whole'     => true,
		);
	}
	?>
	<div class="ec-wd-card" id="ec-wd-review" tabindex="-1">
		<h3><?php echo esc_html( $ec_wd_t( 'review_title' ) ); ?></h3>
		<p class="ec-wd-muted"><?php echo esc_html( $ec_wd_t( 'review_intro' ) ); ?></p>
		<?php echo $ec_wd_rows( $ec_wd::summary_rows( $ec_wd_review, false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the rows are escaped as they are built. ?>
		<form method="post" action="<?php echo esc_url( $withdrawal['action'] ); ?>" class="ec-wd-form" data-ec-wd-form>
			<?php echo $ec_wd_hidden( $ec_wd_values, (bool) $ec_wd_order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped as it is built. ?>
			<?php echo $ec_wd_check( $withdrawal['check'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built. ?>
			<div class="ec-wd-actions">
				<button type="submit" name="wpec_wd_step" value="confirm" class="ec-wd-button ec_withdrawal_confirm"><?php echo esc_html( $ec_wd_t( 'confirm_button' ) ); ?></button>
				<button type="submit" name="wpec_wd_step" value="edit" class="ec-wd-button is-secondary" formnovalidate><?php echo esc_html( $ec_wd_t( 'change_button' ) ); ?></button>
			</div>
		</form>
	</div>
	<?php

elseif ( 'form' === $ec_wd_view && $ec_wd_order && $ec_wd_summary ) :
	?>
	<div class="ec-wd-card">
		<h3><?php echo esc_html( $ec_wd_t( 'order_title', array( 'order_id' => (int) $ec_wd_summary['id'] ) ) ); ?></h3>
		<p class="ec-wd-muted"><?php echo esc_html( $ec_wd_summary['date'] ); ?><?php echo ( '' !== $ec_wd_summary['deadline_text'] ) ? ' · ' . esc_html( $ec_wd_summary['deadline_text'] ) : ''; ?></p>
		<form method="post" action="<?php echo esc_url( $withdrawal['action'] ); ?>" class="ec-wd-form" data-ec-wd-form novalidate>
			<input type="hidden" name="wpec_wd_order" value="<?php echo esc_attr( (string) (int) $ec_wd_order->order_id ); ?>" />
			<input type="hidden" name="wpec_wd_key" value="<?php echo esc_attr( $ec_wd_values['key'] ); ?>" />
			<fieldset>
				<legend class="ec-wd-legend"><?php echo esc_html( $ec_wd_t( 'items_label' ) ); ?></legend>
				<?php foreach ( $ec_wd_summary['lines'] as $ec_wd_line ) : ?>
					<?php
					$ec_wd_open   = ( 'open' === $ec_wd_line['state'] && $ec_wd_line['available'] > 0 );
					$ec_wd_picked = $ec_wd_open && isset( $ec_wd_values['items'][ $ec_wd_line['id'] ] );
					$ec_wd_units  = $ec_wd_picked ? (int) $ec_wd_values['items'][ $ec_wd_line['id'] ] : (int) $ec_wd_line['available'];
					$ec_wd_box    = 'ec-wd-item-' . (int) $ec_wd_line['id'];
					?>
					<div class="ec-wd-item<?php echo $ec_wd_open ? '' : ' is-off'; ?>">
						<label for="<?php echo esc_attr( $ec_wd_box ); ?>">
							<input type="checkbox" id="<?php echo esc_attr( $ec_wd_box ); ?>" name="wpec_wd_pick[<?php echo esc_attr( (string) (int) $ec_wd_line['id'] ); ?>]" value="1"<?php checked( $ec_wd_picked ); ?><?php disabled( ! $ec_wd_open ); ?> />
							<span>
								<?php echo esc_html( $ec_wd_line['title'] ); ?>
								<?php if ( '' !== $ec_wd_line['options'] ) : ?>
									<small><?php echo esc_html( $ec_wd_line['options'] ); ?></small>
								<?php endif; ?>
								<?php if ( '' !== $ec_wd_line['state_text'] ) : ?>
									<small><?php echo esc_html( $ec_wd_line['state_text'] ); ?></small>
								<?php endif; ?>
							</span>
						</label>
						<?php if ( $ec_wd_open && $ec_wd_line['available'] > 1 ) : ?>
							<span class="ec-wd-qty">
								<label class="screen-reader-text" for="<?php echo esc_attr( $ec_wd_box . '-qty' ); ?>"><?php echo esc_html( $ec_wd_line['title'] ); ?></label>
								<select id="<?php echo esc_attr( $ec_wd_box . '-qty' ); ?>" name="wpec_wd_items[<?php echo esc_attr( (string) (int) $ec_wd_line['id'] ); ?>]">
									<?php for ( $ec_wd_n = 1; $ec_wd_n <= (int) $ec_wd_line['available']; $ec_wd_n++ ) : ?>
										<option value="<?php echo esc_attr( (string) $ec_wd_n ); ?>"<?php selected( $ec_wd_units, $ec_wd_n ); ?>><?php echo esc_html( $ec_wd_n . ' / ' . (int) $ec_wd_line['available'] ); ?></option>
									<?php endfor; ?>
								</select>
							</span>
						<?php elseif ( $ec_wd_open ) : ?>
							<input type="hidden" name="wpec_wd_items[<?php echo esc_attr( (string) (int) $ec_wd_line['id'] ); ?>]" value="1" />
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</fieldset>
			<div class="ec-wd-field">
				<label for="ec-wd-name"><?php echo esc_html( $ec_wd_t( 'name_label' ) ); ?> *</label>
				<input type="text" id="ec-wd-name" name="wpec_wd_name" value="<?php echo esc_attr( $ec_wd_values['name'] ); ?>" autocomplete="name" required />
			</div>
			<div class="ec-wd-field">
				<label for="ec-wd-email"><?php echo esc_html( $ec_wd_t( 'email_label' ) ); ?> *</label>
				<input type="email" id="ec-wd-email" name="wpec_wd_email" value="<?php echo esc_attr( $ec_wd_values['email'] ); ?>" autocomplete="email" required />
			</div>
			<div class="ec-wd-field">
				<label for="ec-wd-reason"><?php echo esc_html( $ec_wd_t( 'reason_label' ) ); ?></label>
				<textarea id="ec-wd-reason" name="wpec_wd_reason" maxlength="500" rows="3"><?php echo esc_textarea( $ec_wd_values['reason'] ); ?></textarea>
			</div>
			<div class="ec-wd-actions">
				<button type="submit" name="wpec_wd_step" value="review" class="ec-wd-button"><?php echo esc_html( $ec_wd_t( 'continue_button' ) ); ?></button>
			</div>
		</form>
	</div>
	<?php

else : /* start, or the form without a link again */
	if ( 'start' === $ec_wd_view ) :
		?>
		<p class="ec-wd-intro"><?php echo esc_html( $ec_wd_t( 'page_intro' ) ); ?></p>
		<div class="ec-wd-card" data-ec-wd-orders hidden></div>
		<?php
	endif;
	?>
	<div class="ec-wd-card">
		<h3><?php echo esc_html( $ec_wd_t( 'other_title' ) ); ?></h3>
		<p class="ec-wd-muted"><?php echo esc_html( $ec_wd_t( 'other_intro' ) ); ?></p>
		<form method="post" action="<?php echo esc_url( $withdrawal['action'] ); ?>" class="ec-wd-form" data-ec-wd-form novalidate>
			<div class="ec-wd-field">
				<label for="ec-wd-order-ref"><?php echo esc_html( $ec_wd_t( 'order_number_label' ) ); ?> *</label>
				<input type="text" id="ec-wd-order-ref" name="wpec_wd_order_ref" value="<?php echo esc_attr( $ec_wd_values['order_ref'] ); ?>" inputmode="numeric" autocomplete="off" required />
			</div>
			<div class="ec-wd-field">
				<label for="ec-wd-email"><?php echo esc_html( $ec_wd_t( 'order_email_label' ) ); ?> *</label>
				<input type="email" id="ec-wd-email" name="wpec_wd_email" value="<?php echo esc_attr( $ec_wd_values['email'] ); ?>" autocomplete="email" required />
			</div>
			<div class="ec-wd-field">
				<label for="ec-wd-name"><?php echo esc_html( $ec_wd_t( 'name_label' ) ); ?> *</label>
				<input type="text" id="ec-wd-name" name="wpec_wd_name" value="<?php echo esc_attr( $ec_wd_values['name'] ); ?>" autocomplete="name" required />
			</div>
			<div class="ec-wd-field">
				<label for="ec-wd-note"><?php echo esc_html( $ec_wd_t( 'note_label' ) ); ?></label>
				<textarea id="ec-wd-note" name="wpec_wd_note" maxlength="500" rows="2"><?php echo esc_textarea( $ec_wd_values['note'] ); ?></textarea>
			</div>
			<div class="ec-wd-field">
				<label for="ec-wd-reason"><?php echo esc_html( $ec_wd_t( 'reason_label' ) ); ?></label>
				<textarea id="ec-wd-reason" name="wpec_wd_reason" maxlength="500" rows="3"><?php echo esc_textarea( $ec_wd_values['reason'] ); ?></textarea>
			</div>
			<div class="ec-wd-hp" aria-hidden="true">
				<label for="ec-wd-website">Website</label>
				<input type="text" id="ec-wd-website" name="wpec_wd_website" value="" tabindex="-1" autocomplete="off" />
			</div>
			<div class="ec-wd-actions">
				<button type="submit" name="wpec_wd_step" value="review" class="ec-wd-button"><?php echo esc_html( $ec_wd_t( 'continue_button' ) ); ?></button>
			</div>
		</form>
	</div>
	<?php if ( '' !== (string) $withdrawal['lookup'] ) : ?>
		<p class="ec-wd-muted"><?php echo esc_html( $ec_wd_t( 'lookup_prompt' ) ); ?> <a href="<?php echo esc_url( $withdrawal['lookup'] ); ?>"><?php echo esc_html( $ec_wd_t( 'lookup_title' ) ); ?></a></p>
	<?php endif; ?>
	<?php
endif;
?>
<script>
( function() {
	var root = document.getElementById( 'ec-wd' );
	if ( ! root ) {
		return;
	}
	/* One press per form: a second click while the page loads would send the statement twice. */
	Array.prototype.forEach.call( root.querySelectorAll( '[data-ec-wd-form]' ), function( form ) {
		form.addEventListener( 'submit', function( event ) {
			if ( form.getAttribute( 'data-sent' ) ) {
				event.preventDefault();
				return;
			}
			form.setAttribute( 'data-sent', '1' );
			/* The pressed button's step travels in a hidden field, so the buttons can be disabled ( a disabled button is not sent ). */
			if ( ! event.submitter || ! event.submitter.name ) {
				return;
			}
			var carry = document.createElement( 'input' );
			carry.type = 'hidden';
			carry.name = event.submitter.name;
			carry.value = event.submitter.value;
			form.appendChild( carry );
			Array.prototype.forEach.call( form.querySelectorAll( 'button[type="submit"]' ), function( button ) {
				button.disabled = true;
			} );
		} );
	} );
	/* A ticked item sends its quantity; an unticked one does not. */
	Array.prototype.forEach.call( root.querySelectorAll( '.ec-wd-item input[type="checkbox"]' ), function( box ) {
		var item = box.closest( '.ec-wd-item' ), qty = item ? item.querySelector( 'select' ) : null;
		var sync = function() {
			if ( qty ) {
				qty.disabled = ! box.checked;
			}
		};
		box.addEventListener( 'change', sync );
		sync();
	} );
	var focus = root.querySelector( '#ec-wd-errors, #ec-wd-review, #ec-wd-received' );
	if ( focus && focus.focus ) {
		focus.focus();
	}
	/* A signed-in customer's orders: asked after the page loads, so a cached page never shows them to someone else. */
	var host = root.querySelector( '[data-ec-wd-orders]' );
	if ( host && window.fetch && window.FormData ) {
		var body = new FormData();
		body.append( 'action', 'ec_ajax_withdrawal_orders' );
		body.append( 'language', <?php echo wp_json_encode( (string) $withdrawal['language'] ); ?> );
		fetch( <?php echo wp_json_encode( (string) $withdrawal['ajax_url'] ); ?>, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function( response ) {
			return response.json();
		} ).then( function( answer ) {
			if ( answer && answer.success && answer.data && answer.data.signed_in && answer.data.html ) {
				host.innerHTML = answer.data.html;
				host.hidden = false;
			}
		} ).catch( function() {} );
	}
}() );
</script>
</div>
