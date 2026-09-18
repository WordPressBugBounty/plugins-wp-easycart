<?php
/** Reviews → Requests & rules ( V2 ) template — $this is wp_easycart_admin_review_settings_v2. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = $this->settings; $pro = $this->pro; $f = $this->funnel;
$lock = $pro ? '' : ' <span class="ecv2-chip ecv2-chip-blue">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span>';
$upgrade = apply_filters( 'wp_easycart_admin_upgrade_url', 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/?upsell=reviews' );
?>
<div class="ecv2-wrap ecrq-wrap" id="ecrq" data-nonce="<?php echo esc_attr( wp_create_nonce( wp_easycart_admin_review_settings_v2::NONCE ) ); ?>">
	<script type="application/json" id="ecrq_data"><?php echo wp_json_encode( $this->js_data() ); ?></script>
	<div class="ecv2-page-header">
		<div class="ecv2-page-header-left"><h1 class="ecv2-page-title"><?php esc_html_e( 'Product Reviews', 'wp-easycart' ); ?></h1></div>
		<div class="ecv2-page-header-right"><a href="<?php echo esc_url( $this->docs_link ); ?>" target="_blank" class="ecv2-help-link"><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Help', 'wp-easycart' ); ?></a>
			<?php if ( $this->native ) { ?><button type="button" class="ecv2-btn ecv2-btn-primary" id="ecrq_save" onclick="ecrq.save();"><?php esc_html_e( 'Save', 'wp-easycart' ); ?></button><?php } ?></div>
	</div>
	<?php wp_easycart_admin_review_settings_v2::print_tabs( 'requests' ); ?>

	<?php if ( ! $this->native ) { ?>
	<div class="ecos-note"><span class="dashicons dashicons-warning"></span><div><b><?php esc_html_e( 'Database update pending.', 'wp-easycart' ); ?></b> <?php esc_html_e( 'Review requests, verified-buyer badges and replies need the reviews tables added by the latest EasyCart update. Load any EasyCart admin page after updating to run it, then return here.', 'wp-easycart' ); ?></div></div>
	<?php } else { ?>

	<div class="ecrq-grid">
		<div class="ecrq-main">

			<div class="ecdv2-card" id="ecrq-requests">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Review requests', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint"><?php esc_html_e( 'One email per order, listing what they bought, with clickable stars', 'wp-easycart' ); ?></span></div>
				<div class="ecdv2-card-body">
					<label class="ecos-toggle-row"><span class="ecv2-toggle"><input type="checkbox" id="ecrq_enabled" data-field="request_enabled"<?php checked( (int) $s['request_enabled'], 1 ); ?>><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t"><?php esc_html_e( 'Ask customers for a review after their order ships', 'wp-easycart' ); ?></span><small><?php esc_html_e( 'Sent once per order. Customers are never asked twice for the same product, anyone who already reviewed it is skipped, and every email carries a one-click opt-out.', 'wp-easycart' ); ?></small></span></label>

					<div class="ecrq-timeline">
						<div class="ecrq-step"><b><?php esc_html_e( 'Trigger', 'wp-easycart' ); ?></b><small><?php esc_html_e( 'when an order reaches', 'wp-easycart' ); ?></small>
							<div class="ecrq-statuses">
							<?php foreach ( $this->statuses as $st ) { ?>
								<label class="ecrq-status"><input type="checkbox" class="ecrq-status-cb" value="<?php echo (int) $st->status_id; ?>"<?php checked( in_array( (int) $st->status_id, array_map( 'intval', $s['request_statuses'] ), true ) ); ?>> <?php echo esc_html( $st->order_status ); ?></label>
							<?php } ?>
							</div>
						</div>
						<div class="ecrq-step"><b><?php esc_html_e( 'Wait', 'wp-easycart' ); ?></b><small><?php esc_html_e( 'so the parcel has arrived', 'wp-easycart' ); ?></small>
							<div class="ecrq-inline"><input type="number" min="0" max="90" class="ecv2-input" id="ecrq_delay" data-field="request_delay" value="<?php echo (int) $s['request_delay']; ?>" style="width:80px"> <span><?php esc_html_e( 'days', 'wp-easycart' ); ?></span></div>
						</div>
						<div class="ecrq-step"><b><?php esc_html_e( 'Request email', 'wp-easycart' ); ?></b><small><?php esc_html_e( 'stars in the email pre-fill the form', 'wp-easycart' ); ?></small>
							<div class="ecrq-inline"><button type="button" class="ecv2-btn ecv2-btn-sm" onclick="ecrq.test( 0 );"><?php esc_html_e( 'Send me a test', 'wp-easycart' ); ?></button></div>
						</div>
						<div class="ecrq-step<?php echo $pro ? '' : ' is-locked'; ?>"><b><?php esc_html_e( 'Reminder', 'wp-easycart' ); ?><?php echo $lock; ?></b><small><?php esc_html_e( 'once, if they haven’t reviewed', 'wp-easycart' ); ?></small>
							<div class="ecrq-inline"><?php if ( $pro ) { ?><input type="number" min="0" max="60" class="ecv2-input" id="ecrq_reminder" data-field="reminder_days" value="<?php echo (int) $s['reminder_days']; ?>" style="width:80px"> <span><?php esc_html_e( 'days later · 0 = off', 'wp-easycart' ); ?></span><?php } else { ?><a href="<?php echo esc_url( $upgrade ); ?>" target="_blank" rel="noopener" class="ecos-hint"><?php echo esc_html( wp_easycart_admin_edition::included_text( 'pro' ) ); ?></a><?php } ?></div>
						</div>
					</div>

					<div class="ecdv2-grid" style="margin-top:14px">
						<div class="ecdv2-field"><label class="ecdv2-label" for="ecrq_subject"><?php esc_html_e( 'Subject', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( '{store} and {first_name} are replaced', 'wp-easycart' ); ?></span></label><input type="text" class="ecv2-input" id="ecrq_subject" data-field="request_subject" value="<?php echo esc_attr( $s['request_subject'] ); ?>" placeholder="<?php esc_attr_e( 'How did you like your order?', 'wp-easycart' ); ?>"></div>
						<div class="ecdv2-field ecdv2-field-full"><label class="ecdv2-label" for="ecrq_intro"><?php esc_html_e( 'Opening paragraph', 'wp-easycart' ); ?> <span class="ecdv2-label-hint"><?php esc_html_e( 'leave blank for the default wording', 'wp-easycart' ); ?></span></label><textarea class="ecv2-input" id="ecrq_intro" data-field="request_intro" rows="3" placeholder="<?php esc_attr_e( 'Thanks for your recent order! Would you take a moment to tell other shoppers what you think?', 'wp-easycart' ); ?>"><?php echo esc_textarea( $s['request_intro'] ); ?></textarea><span class="ecdv2-field-desc"><?php esc_html_e( 'The full template lives in design/layout/…/ec_review_request_email.php and can be copied into your layout folder to customise.', 'wp-easycart' ); ?></span></div>
					</div>
					<div class="ecos-note info"><span class="dashicons dashicons-info-outline"></span><div><?php esc_html_e( 'Sends through your Order email settings (Settings → Email). Requests are queued when the status changes and delivered by WP-Cron on the schedule above.', 'wp-easycart' ); ?> <?php if ( wp_next_scheduled( ec_reviews::CRON_HOOK ) ) { echo esc_html( sprintf( __( 'Next delivery run: %s.', 'wp-easycart' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), wp_next_scheduled( ec_reviews::CRON_HOOK ) ) ) ); } ?> <a href="#" onclick="ecrq.send_now(); return false;"><?php esc_html_e( 'Send due requests now', 'wp-easycart' ); ?></a></div></div>
				</div>
			</div>

			<div class="ecdv2-card<?php echo $pro ? '' : ' ecrv-locked'; ?>" id="ecrq-rules">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Moderation rules', 'wp-easycart' ); ?><?php echo $lock; ?></h3><span class="ecdv2-card-hint"><?php esc_html_e( 'Applied when a review arrives. Anything not matched waits for you as Pending.', 'wp-easycart' ); ?></span></div>
				<div class="ecdv2-card-body">
					<?php $r = $s['rules']; $dis = $pro ? '' : ' disabled'; ?>
					<div class="ecrq-rules">
						<div class="ecrq-rule"><span class="ecv2-chip ecv2-chip-green"><?php esc_html_e( 'Auto-approve', 'wp-easycart' ); ?></span> <?php esc_html_e( 'verified buyers rating', 'wp-easycart' ); ?> <select class="ecv2-select" id="ecrq_rule_auto" data-rule="auto_approve_verified_min"<?php echo $dis; ?>><option value="0"<?php selected( $r['auto_approve_verified_min'], 0 ); ?>><?php esc_html_e( 'off', 'wp-easycart' ); ?></option><?php for ( $i = 3; $i <= 5; $i++ ) { ?><option value="<?php echo $i; ?>"<?php selected( $r['auto_approve_verified_min'], $i ); ?>><?php echo esc_html( sprintf( __( '%d stars or more', 'wp-easycart' ), $i ) ); ?></option><?php } ?></select></div>
						<div class="ecrq-rule"><span class="ecv2-chip ecv2-chip-amber"><?php esc_html_e( 'Hold', 'wp-easycart' ); ?></span> <?php esc_html_e( 'anything containing a link or email address', 'wp-easycart' ); ?> <span class="ecv2-toggle ecv2-toggle-sm" style="margin-left:auto"><input type="checkbox" data-rule="hold_links"<?php checked( (int) $r['hold_links'], 1 ); echo $dis; ?>><span class="ecv2-toggle-slider"></span></span></div>
						<div class="ecrq-rule"><span class="ecv2-chip ecv2-chip-amber"><?php esc_html_e( 'Hold', 'wp-easycart' ); ?></span> <?php esc_html_e( 'reviews from people with no order for that product', 'wp-easycart' ); ?> <span class="ecv2-toggle ecv2-toggle-sm" style="margin-left:auto"><input type="checkbox" data-rule="hold_unverified"<?php checked( (int) $r['hold_unverified'], 1 ); echo $dis; ?>><span class="ecv2-toggle-slider"></span></span></div>
						<div class="ecrq-rule ecrq-rule-col"><div><span class="ecv2-chip ecv2-chip-amber"><?php esc_html_e( 'Hold', 'wp-easycart' ); ?></span> <?php esc_html_e( 'anything containing these words', 'wp-easycart' ); ?> <span class="ecos-hint"><?php esc_html_e( '(comma or space separated)', 'wp-easycart' ); ?></span></div><textarea class="ecv2-input" rows="2" data-rule="blocked_words"<?php echo $dis; ?>><?php echo esc_textarea( $r['blocked_words'] ); ?></textarea></div>
						<div class="ecrq-rule"><span class="ecv2-chip ecv2-chip-blue"><?php esc_html_e( 'Notify me', 'wp-easycart' ); ?></span> <?php esc_html_e( 'immediately for reviews rated', 'wp-easycart' ); ?> <select class="ecv2-select" data-rule="notify_low"<?php echo $dis; ?>><option value="0"<?php selected( $r['notify_low'], 0 ); ?>><?php esc_html_e( 'off', 'wp-easycart' ); ?></option><?php for ( $i = 1; $i <= 3; $i++ ) { ?><option value="<?php echo $i; ?>"<?php selected( $r['notify_low'], $i ); ?>><?php echo esc_html( sprintf( __( '%d stars or fewer', 'wp-easycart' ), $i ) ); ?></option><?php } ?></select> <span class="ecos-hint"><?php echo esc_html( sprintf( __( 'to %s', 'wp-easycart' ), stripslashes( get_option( 'ec_option_bcc_email_addresses' ) ) ) ); ?></span></div>
					</div>
					<?php if ( ! $pro ) { ?><div class="ecos-note info" style="margin-top:12px"><span class="dashicons dashicons-lock"></span><div><?php esc_html_e( 'Moderation rules keep your queue at zero: approve trusted reviews automatically, hold spam, get pinged on low ratings.', 'wp-easycart' ); ?> <a href="<?php echo esc_url( $upgrade ); ?>" target="_blank" rel="noopener"><?php echo esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Learn about %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ); ?></a></div></div><?php } ?>
					<div class="ecos-note" style="margin-top:12px"><span class="dashicons dashicons-shield"></span><div><?php esc_html_e( 'Always on, every store: a second review of the same product from the same customer is held as a duplicate, and reviewers matched to an order get the Verified buyer badge.', 'wp-easycart' ); ?> <a href="#" onclick="ecrq.recompute(); return false;"><?php esc_html_e( 'Recompute verified badges for existing reviews', 'wp-easycart' ); ?></a></div></div>
				</div>
			</div>

			<div class="ecdv2-card<?php echo $pro ? '' : ' ecrv-locked'; ?>" id="ecrq-replies">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Replies', 'wp-easycart' ); ?><?php echo $lock; ?></h3><span class="ecdv2-card-hint"><?php esc_html_e( 'How your public responses are signed and delivered', 'wp-easycart' ); ?></span></div>
				<div class="ecdv2-card-body ecdv2-grid">
					<div class="ecdv2-field"><label class="ecdv2-label" for="ecrq_sig"><?php esc_html_e( 'Sign replies as', 'wp-easycart' ); ?></label><input type="text" class="ecv2-input" id="ecrq_sig" data-field="reply_signature" value="<?php echo esc_attr( $s['reply_signature'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"<?php echo $dis; ?>></div>
					<div class="ecdv2-field"><label class="ecdv2-label"><?php esc_html_e( 'Delivery', 'wp-easycart' ); ?></label><label class="ecos-toggle-row" style="padding:4px 0"><span class="ecv2-toggle"><input type="checkbox" data-field="reply_email"<?php checked( (int) $s['reply_email'], 1 ); echo $dis; ?>><span class="ecv2-toggle-slider"></span></span><span><span class="ecos-toggle-t"><?php esc_html_e( 'Email the reviewer when you post a reply', 'wp-easycart' ); ?></span><small><?php esc_html_e( 'Default for the checkbox in the review editor; you can override per reply.', 'wp-easycart' ); ?></small></span></label></div>
				</div>
			</div>
		</div>

		<aside class="ecrq-side">
			<div class="ecdv2-card" id="ecrq-funnel">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Last 30 days', 'wp-easycart' ); ?></h3></div>
				<div class="ecdv2-card-body">
					<div class="ecrq-funnel">
						<div><b id="ecrq_f_queued"><?php echo (int) $f['queued']; ?></b><span><?php esc_html_e( 'queued', 'wp-easycart' ); ?></span></div>
						<div><b id="ecrq_f_sent"><?php echo (int) $f['sent']; ?></b><span><?php esc_html_e( 'sent', 'wp-easycart' ); ?></span></div>
						<div><b id="ecrq_f_opened"><?php echo (int) $f['opened']; ?></b><span><?php esc_html_e( 'opened', 'wp-easycart' ); ?></span></div>
						<div><b id="ecrq_f_reviewed"><?php echo (int) $f['reviewed']; ?></b><span><?php esc_html_e( 'reviews', 'wp-easycart' ); ?></span></div>
					</div>
					<div class="ecrq-funnel-foot">
						<span><?php echo esc_html( $f['sent'] ? sprintf( __( '%s%% of sent requests became reviews', 'wp-easycart' ), round( 100 * $f['reviewed'] / max( 1, $f['sent'] ) ) ) : __( 'No requests sent yet.', 'wp-easycart' ) ); ?></span>
						<?php if ( $f['avg'] ) { ?><span><?php echo esc_html( sprintf( __( 'Average rating from requests: %s ★', 'wp-easycart' ), $f['avg'] ) ); ?></span><?php } ?>
						<?php if ( $f['pending'] ) { ?><span><?php echo esc_html( sprintf( _n( '%d request waiting to send', '%d requests waiting to send', $f['pending'], 'wp-easycart' ), $f['pending'] ) ); ?></span><?php } ?>
						<?php if ( $f['unsubscribed'] ) { ?><span><?php echo esc_html( sprintf( _n( '%d opt-out', '%d opt-outs', $f['unsubscribed'], 'wp-easycart' ), $f['unsubscribed'] ) ); ?></span><?php } ?>
					</div>
				</div>
			</div>
			<div class="ecdv2-card" id="ecrq-log">
				<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Recent requests', 'wp-easycart' ); ?></h3></div>
				<div class="ecdv2-card-body ecrq-log">
					<?php if ( empty( $this->log ) ) { ?><div class="ecos-hint"><?php esc_html_e( 'Requests appear here once an order reaches a trigger status.', 'wp-easycart' ); ?></div><?php } ?>
					<?php foreach ( $this->log as $q ) { $state = $q->review_id ? array( 'green', __( 'Reviewed', 'wp-easycart' ) ) : ( $q->unsubscribed ? array( 'gray', __( 'Opted out', 'wp-easycart' ) ) : ( $q->reminded_at ? array( 'blue', __( 'Reminded', 'wp-easycart' ) ) : ( $q->opened_at ? array( 'blue', __( 'Opened', 'wp-easycart' ) ) : ( $q->sent_at ? array( 'gray', __( 'Sent', 'wp-easycart' ) ) : array( 'amber', __( 'Scheduled', 'wp-easycart' ) ) ) ) ) ); ?>
					<div class="ecrq-log-row"><div class="ecrq-log-main"><b><?php echo esc_html( wp_unslash( (string) $q->product_title ) ); ?></b><span class="ecv2-sub"><?php echo esc_html( $q->email ); ?> · <?php echo esc_html( sprintf( __( 'Order #%d', 'wp-easycart' ), (int) $q->order_id ) ); ?><?php if ( ! $q->sent_at && $q->scheduled_at ) { echo ' · ' . esc_html( sprintf( __( 'sends %s', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), strtotime( $q->scheduled_at ) ) ) ); } ?></span></div><span class="ecv2-chip ecv2-chip-<?php echo esc_attr( $state[0] ); ?>"><?php echo esc_html( $state[1] ); ?></span></div>
					<?php } ?>
				</div>
			</div>
		</aside>
	</div>
	<?php } ?>
	<div id="ecv2-toast-container"></div>
</div>
