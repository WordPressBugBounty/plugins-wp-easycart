<?php
/** Review editor ( V2 ) template — $this is wp_easycart_admin_review_editor_v2. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
include_once( EC_PLUGIN_DIRECTORY . '/admin/template/products/shared/editor-lite-parts.php' );
$r = $this->r; $p = $this->product;
$list_url = admin_url( 'admin.php?page=wp-easycart-products&subpage=reviews' );
$who = trim( (string) $r->reviewer_name ); if ( '' === $who && $this->user ) { $who = $this->user->display_name; }
$ts = strtotime( $r->date_submitted );
$product_url = $p ? admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' . (int) $p->product_id ) : '';
$product_img = wp_easycart_admin_catalog_v2_product_thumb( $p );

ecv2_lite_open( $this->js_data(), 'ecrv-wrap' );
ecv2_lite_header( array(
	'back_url' => $list_url, 'back_title' => __( 'Back to reviews', 'wp-easycart' ), 'title' => trim( wp_unslash( $r->title ) ) !== '' ? wp_unslash( $r->title ) : __( '(no title)', 'wp-easycart' ),
	'sub_html' => wp_easycart_admin_review_table::stars_html( $r->rating ) . ' · ' . ( $who ? esc_html( $who ) . ' · ' : '' ) . ( $p ? '<a href="' . esc_url( $product_url ) . '">' . esc_html( wp_unslash( $p->title ) ) . '</a> · ' : '' ) . esc_html( $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '' ) . ' · ' . esc_html( 'ID ' . $r->review_id ),
	'pill_html' => '<span class="ecdv2-status-pill' . ( $r->approved ? ' is-active' : '' ) . '" id="eclite_h_status">' . ( $r->approved ? esc_html__( 'Approved', 'wp-easycart' ) : esc_html__( 'Pending', 'wp-easycart' ) ) . '</span>' . ( $this->native && ! empty( $r->verified ) ? ' <span class="ecv2-chip ecv2-chip-green" title="' . esc_attr__( 'This customer purchased this product', 'wp-easycart' ) . '">✓ ' . esc_html__( 'Verified buyer', 'wp-easycart' ) . '</span>' : '' ),
	'actions_html' => ( $p ? '<a class="ecv2-btn ecv2-btn-ghost" href="' . esc_url( $product_url ) . '">' . esc_html__( 'Open product', 'wp-easycart' ) . '</a>' : '' ) . '<button type="button" class="ecv2-btn ' . ( $r->approved ? '' : 'ecv2-btn-primary-outline' ) . '" id="eclite_quick_approve" onclick="eclite.quick_approve();">' . ( $r->approved ? esc_html__( 'Deny', 'wp-easycart' ) : esc_html__( 'Approve', 'wp-easycart' ) ) . '</button>',
) );
?>
<div class="ecdv2-body">
	<?php ecv2_lite_rail( array(
		__( 'Review', 'wp-easycart' ) => array_values( array_filter( array( array( '#rvv2-review', __( 'Review', 'wp-easycart' ) ), $this->native ? array( '#rvv2-reply', __( 'Your reply', 'wp-easycart' ) ) : null, array( '#rvv2-product', __( 'Product', 'wp-easycart' ) ), array( '#rvv2-reviewer', __( 'Reviewer', 'wp-easycart' ), count( $this->other_reviews ) ?: null ) ) ) ),
		__( 'More', 'wp-easycart' ) => array( array( '#rvv2-danger', __( 'Danger zone', 'wp-easycart' ) ) ),
	), $this->health(), __( 'Review status', 'wp-easycart' ) ); ?>
	<div class="ecdv2-main">
		<?php ecv2_lite_card_open( 'rvv2-review', __( 'Review', 'wp-easycart' ), __( 'Edit sparingly — shoppers expect reviews to be the customer\'s own words', 'wp-easycart' ), '<a href="' . esc_url( $this->docs_link ) . '" target="_blank" class="ecdv2-help-link"><span class="dashicons dashicons-editor-help"></span>' . esc_html__( 'Help', 'wp-easycart' ) . '</a>' ); ?>
		<div class="ecdv2-grid">
			<div class="ecdv2-field">
				<label class="ecdv2-label"><?php esc_html_e( 'Rating', 'wp-easycart' ); ?></label>
				<div class="ecv2-star-picker" id="eclite_stars" role="radiogroup">
					<?php for ( $i = 1; $i <= 5; $i++ ) { ?><button type="button" class="ecv2-star-btn<?php echo $i <= (int) $r->rating ? ' is-on' : ''; ?>" data-value="<?php echo $i; ?>" aria-label="<?php echo esc_attr( sprintf( __( '%d stars', 'wp-easycart' ), $i ) ); ?>" onclick="eclite.set_rating( <?php echo $i; ?> );"><span class="dashicons dashicons-star-filled"></span></button><?php } ?>
					<span class="ecos-hint" id="eclite_stars_label"><?php echo esc_html( sprintf( __( '%d of 5', 'wp-easycart' ), (int) $r->rating ) ); ?></span>
				</div>
				<input type="hidden" id="eclite_rating" data-field="rating" value="<?php echo (int) $r->rating; ?>" data-track>
			</div>
			<div class="ecdv2-field"><label class="ecdv2-label"><?php esc_html_e( 'Visibility', 'wp-easycart' ); ?></label><?php echo ecv2_lite_toggle_row( 'eclite_approved', 'approved', (bool) $r->approved, __( 'Approved', 'wp-easycart' ), __( 'Approved reviews appear on the product page and count toward its average rating.', 'wp-easycart' ) ); ?></div>
			<?php ecv2_lite_field( 'eclite_title', __( 'Title', 'wp-easycart' ), ecv2_lite_text( 'eclite_title', 'title', wp_unslash( $r->title ), '', 'data-bind="title"' ), '', '', true ); ?>
			<?php ecv2_lite_field( 'eclite_desc', __( 'Review', 'wp-easycart' ), '<textarea class="ecv2-input" id="eclite_desc" data-field="description" rows="6" data-track>' . esc_textarea( (string) wp_unslash( $r->description ) ) . '</textarea>', '', '', true ); ?>
			<?php ecv2_lite_field( 'eclite_reviewer', __( 'Reviewer name', 'wp-easycart' ), ecv2_lite_text( 'eclite_reviewer', 'reviewer_name', wp_unslash( $r->reviewer_name ), $this->user ? $this->user->display_name : '' ), __( 'shown publicly', 'wp-easycart' ) ); ?>
			<?php ecv2_lite_field( 'eclite_date', __( 'Submitted', 'wp-easycart' ), '<input type="datetime-local" class="ecv2-input" id="eclite_date" data-field="date_submitted" value="' . esc_attr( $ts ? date( 'Y-m-d\TH:i', $ts ) : '' ) . '" data-track>' ); ?>
		</div>
		<?php ecv2_lite_card_close(); ?>

		<?php if ( $this->native ) { ?>
		<div class="ecdv2-card<?php echo $this->pro ? '' : ' ecrv-locked'; ?>" id="rvv2-reply">
			<div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Your reply', 'wp-easycart' ); ?></h3><span class="ecdv2-card-hint"><?php echo $this->pro ? esc_html__( 'Shown publicly under the review, signed as the store', 'wp-easycart' ) : '<span class="ecv2-chip ecv2-chip-blue">' . esc_html( wp_easycart_admin_edition::badge( 'pro' ) ) . '</span> ' . esc_html__( 'Reply publicly to reviews and email the customer your response', 'wp-easycart' ); ?></span></div>
			<div class="ecdv2-card-body">
				<div class="ecrv-thread">
					<div class="ecrv-thread-review"><?php echo wp_easycart_admin_review_table::stars_html( $r->rating ); ?> <b><?php echo esc_html( wp_unslash( $r->title ) ? wp_unslash( $r->title ) : __( '(no title)', 'wp-easycart' ) ); ?></b> <span class="ecv2-sub" style="display:inline"><?php echo esc_html( $who ? $who : __( 'Anonymous', 'wp-easycart' ) ); ?><?php if ( $this->request ) { echo ' · ' . esc_html( sprintf( __( 'Order #%d', 'wp-easycart' ), (int) $this->request->order_id ) ); } ?></span>
						<p><?php echo nl2br( esc_html( wp_unslash( $r->description ) ) ); ?></p></div>
					<div class="ecrv-reply-box">
						<div class="ecrv-reply-who"><b id="ecrv_reply_signature"><?php echo esc_html( ec_reviews::reply_signature() ); ?></b> <span id="ecrv_reply_status" class="ecv2-sub" style="display:inline"><?php echo ! empty( $r->reply_text ) ? esc_html( sprintf( __( 'replied %s', 'wp-easycart' ), date_i18n( get_option( 'date_format' ), strtotime( $r->reply_date ) ) ) ) : esc_html__( 'no reply yet', 'wp-easycart' ); ?></span></div>
						<textarea class="ecv2-input" id="ecrv_reply" rows="4" placeholder="<?php esc_attr_e( 'Thank the customer, address what they said, say what you did about it…', 'wp-easycart' ); ?>"<?php echo $this->pro ? '' : ' disabled'; ?>><?php echo esc_textarea( wp_unslash( (string) $r->reply_text ) ); ?></textarea>
						<div class="ecrv-reply-foot">
							<?php if ( $this->pro ) { ?>
							<label class="ecos-toggle-row" style="padding:0"><span class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" id="ecrv_reply_email"<?php checked( (int) ec_reviews::settings()['reply_email'], 1 ); ?>><span class="ecv2-toggle-slider"></span></span><span><?php esc_html_e( 'Also email this reply to the reviewer', 'wp-easycart' ); ?></span></label>
							<span class="ecos-grow"></span>
							<?php if ( ! empty( $r->reply_text ) ) { ?><button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" onclick="eclite.reply_clear();"><?php esc_html_e( 'Remove reply', 'wp-easycart' ); ?></button><?php } ?>
							<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" id="ecrv_reply_post" onclick="eclite.reply_post();"><?php echo ! empty( $r->reply_text ) ? esc_html__( 'Update reply', 'wp-easycart' ) : esc_html__( 'Post reply', 'wp-easycart' ); ?></button>
							<?php } else { ?>
							<span class="ecos-hint"><?php echo esc_html( __( 'Public replies turn a low rating into a demonstration of service.', 'wp-easycart' ) . ' ' . wp_easycart_admin_edition::included_text( 'pro' ) ); ?></span><span class="ecos-grow"></span><a class="ecv2-btn ecv2-btn-sm" target="_blank" rel="noopener" href="<?php echo esc_url( apply_filters( 'wp_easycart_admin_upgrade_url', 'https://www.wpeasycart.com/wordpress-shopping-cart-pricing/?upsell=reviews' ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: plan name ( Pro/Premium, Pro or Premium ). */ __( 'Learn about %s', 'wp-easycart' ), wp_easycart_admin_edition::plan_name() ) ); ?></a>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php } ?>

		<?php ecv2_lite_card_open( 'rvv2-product', __( 'Product', 'wp-easycart' ), __( 'read-only', 'wp-easycart' ) ); ?>
		<?php if ( ! $p ) { ?>
			<div class="ecos-note ecos-note-danger" style="margin:0"><span class="dashicons dashicons-warning"></span><div><b><?php esc_html_e( 'This product no longer exists.', 'wp-easycart' ); ?></b> <?php esc_html_e( 'The review is orphaned and is never shown in the store. It is safe to delete.', 'wp-easycart' ); ?></div></div>
		<?php } else { $s = $this->product_stats; ?>
			<div class="ecrv-product">
				<span class="ecv2-thumb ecv2-thumb-lg"><?php echo $product_img ? '<img src="' . esc_url( $product_img ) . '" alt="">' : '<span class="dashicons dashicons-format-image"></span>'; ?></span>
				<div class="ecrv-product-main">
					<a class="ecv2-link-primary ecv2-title-link" href="<?php echo esc_url( $product_url ); ?>"><?php echo esc_html( wp_unslash( $p->title ) ); ?></a>
					<span class="ecv2-sub"><?php echo esc_html( $p->model_number . ' · ' . $GLOBALS['currency']->get_currency_display( $p->price ) ); ?> · <?php echo $p->activate_in_store ? esc_html__( 'Active', 'wp-easycart' ) : '<span class="ecv2-sub-warn">' . esc_html__( 'Inactive', 'wp-easycart' ) . '</span>'; ?></span>
				</div>
				<div class="ecrv-product-stats">
					<div><b><?php echo esc_html( $s && $s->avg ? $s->avg : '—' ); ?></b><span><?php esc_html_e( 'avg rating', 'wp-easycart' ); ?></span></div>
					<div><b><?php echo (int) ( $s ? $s->n : 0 ); ?></b><span><?php esc_html_e( 'reviews', 'wp-easycart' ); ?></span></div>
					<div><b class="<?php echo $s && $s->pending ? 'is-warn' : ''; ?>"><?php echo (int) ( $s ? $s->pending : 0 ); ?></b><span><?php esc_html_e( 'pending', 'wp-easycart' ); ?></span></div>
				</div>
				<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( add_query_arg( 'filter_2', (int) $p->product_id, $list_url ) ); ?>"><?php esc_html_e( 'All reviews for this product', 'wp-easycart' ); ?></a>
			</div>
			<?php if ( $this->summary && $this->summary['count'] ) { ?>
			<div class="ecrv-dist">
				<?php for ( $st = 5; $st >= 1; $st-- ) { $n = (int) $this->summary['dist'][ $st ]; $pct = round( 100 * $n / $this->summary['count'] ); ?>
				<a class="ecrv-dist-row" href="<?php echo esc_url( add_query_arg( array( 'filter_2' => (int) $p->product_id, 'filter_1' => $st ), $list_url ) ); ?>"><span><?php echo $st; ?> ★</span><span class="ecrv-dist-bar"><i style="width:<?php echo $pct; ?>%"></i></span><span><?php echo $n; ?></span></a>
				<?php } ?>
			</div>
			<?php } ?>
		<?php } ?>
		<?php ecv2_lite_card_close(); ?>

		<?php ecv2_lite_card_open( 'rvv2-reviewer', __( 'Reviewer', 'wp-easycart' ), $this->user ? __( 'Registered customer', 'wp-easycart' ) : ( $who ? __( 'Guest', 'wp-easycart' ) : __( 'Anonymous', 'wp-easycart' ) ) ); ?>
		<?php if ( $this->user ) { ?>
			<div class="ecos-u" style="border:0;padding:0 0 10px"><div class="ecos-u-main"><b><?php echo esc_html( $this->user->display_name ); ?></b><span class="ecv2-sub"><?php echo esc_html( $this->user->user_email ); ?></span></div><a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-easycart-users&subpage=accounts&s=' . rawurlencode( $this->user->user_email ) ) ); ?>"><?php esc_html_e( 'Customer record', 'wp-easycart' ); ?></a></div>
		<?php } ?>
		<?php if ( $this->other_reviews ) { ?>
			<div class="ecos-hint" style="margin-bottom:6px"><?php echo esc_html( sprintf( _n( '%d other review by this reviewer', '%d other reviews by this reviewer', count( $this->other_reviews ), 'wp-easycart' ), count( $this->other_reviews ) ) ); ?></div>
			<div class="ecos-used">
			<?php foreach ( $this->other_reviews as $o ) { ?>
				<div class="ecos-u"><?php echo wp_easycart_admin_review_table::stars_html( $o->rating ); ?><div class="ecos-u-main"><a class="ecv2-link-primary" href="<?php echo esc_url( wp_easycart_admin_review_table::editor_url( $o->review_id ) ); ?>"><?php echo esc_html( wp_unslash( $o->title ) ? wp_unslash( $o->title ) : __( '(no title)', 'wp-easycart' ) ); ?></a><span class="ecv2-sub"><?php echo esc_html( ( $o->product_title ? wp_unslash( $o->product_title ) . ' · ' : '' ) . date_i18n( get_option( 'date_format' ), strtotime( $o->date_submitted ) ) ); ?></span></div><span class="ecv2-chip <?php echo $o->approved ? 'ecv2-chip-green' : 'ecv2-chip-amber'; ?>"><?php echo $o->approved ? esc_html__( 'Approved', 'wp-easycart' ) : esc_html__( 'Pending', 'wp-easycart' ); ?></span></div>
			<?php } ?>
			</div>
		<?php } else if ( ! $this->user ) { ?>
			<div class="ecos-hint"><?php esc_html_e( 'No account is linked to this review.', 'wp-easycart' ); ?></div>
		<?php } ?>
		<?php ecv2_lite_card_close(); ?>

		<div class="ecdv2-card ecos-danger" id="rvv2-danger"><div class="ecdv2-card-header"><h3 class="ecdv2-card-title"><?php esc_html_e( 'Danger zone', 'wp-easycart' ); ?></h3></div><div class="ecdv2-card-body ecos-danger-body"><div><b><?php esc_html_e( 'Delete this review', 'wp-easycart' ); ?></b><span><?php esc_html_e( 'Removes it permanently. Undo is offered for 15 minutes. If you only want to hide it from shoppers, deny it instead.', 'wp-easycart' ); ?></span></div><button type="button" class="ecv2-btn ecv2-btn-danger" onclick="ecv2_catalog.review_delete( <?php echo (int) $r->review_id; ?>, '<?php echo esc_js( $list_url ); ?>' );"><?php esc_html_e( 'Delete review', 'wp-easycart' ); ?></button></div></div>
	</div>
</div>
<?php ecv2_lite_close();
