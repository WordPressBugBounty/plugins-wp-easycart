<?php
/**
 * WP EasyCart Admin — Product Reviews ( V2 ): list, editor and AJAX.
 *
 * Reviews have no post and no dependents, so deletion is a plain confirm with a
 * session Undo ( the row is re-inserted with its original id ) rather than the
 * 30-day trash.
 *
 * AJAX: ecv2_review_toggle, ecv2_review_bulk, ecv2_review_delete, ecv2_review_restore, ecv2_review_save.
 *
 * @since 5.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* The base list class normally arrives later in admin-init.php; load it here so this
   file is safe to include from a constructor regardless of order. The base is wrapped
   in class_exists(), so the later plain include() in admin-init is harmless. */
include_once( EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_table_v2.php' );

if ( ! class_exists( 'wp_easycart_admin_review_table' ) ) :

	class wp_easycart_admin_review_table extends wp_easycart_admin_table_v2 {

		public static function native_available() { return class_exists( 'ec_reviews' ) && ec_reviews::columns_exist(); }
		public static function native_pro() { return class_exists( 'ec_reviews' ) && ec_reviews::is_pro(); }

		public static function editor_url( $id ) {
			$id = ( '{id}' === $id ) ? '{id}' : (int) $id;
			return admin_url( 'admin.php?page=wp-easycart-products&subpage=reviews&ec_admin_form_action=edit&review_id=' . $id );
		}

		public static function stars_html( $rating, $small = true ) {
			$rating = max( 0, min( 5, (int) $rating ) );
			$out = '<span class="ecv2-stars' . ( $small ? ' ecv2-stars-sm' : '' ) . '" title="' . esc_attr( sprintf( __( '%d of 5', 'wp-easycart' ), $rating ) ) . '" aria-label="' . esc_attr( sprintf( __( '%d of 5 stars', 'wp-easycart' ), $rating ) ) . '">';
			for ( $i = 1; $i <= 5; $i++ ) { $out .= '<span class="dashicons dashicons-star-' . ( $i <= $rating ? 'filled' : 'empty' ) . '"></span>'; }
			return $out . '</span>';
		}

		public function __construct() { parent::__construct(); $this->setup(); }

		public function print_table() {
			if ( class_exists( 'wp_easycart_admin_review_settings_v2' ) ) { echo '<div class="ecv2-wrap ecv2-tabs-wrap">'; wp_easycart_admin_review_settings_v2::print_tabs( '' ); echo '</div>'; }
			parent::print_table();
		}

		public function setup() {
			global $wpdb;
			$this->set_table( 'ec_review', 'review_id' );
			$this->set_table_id( 'ec_admin_review_list_v2' );
			$this->set_join( 'LEFT JOIN ec_product ON ec_product.product_id = ec_review.product_id' );
			$this->set_default_sort( 'ec_review.date_submitted', 'DESC' );
			$this->set_header( __( 'Product Reviews', 'wp-easycart' ) );
			$this->set_docs_link( 'products', 'product-reviews' );
			$this->set_add_new( false, '', '' );
			$this->set_label( __( 'Review', 'wp-easycart' ), __( 'Reviews', 'wp-easycart' ) );
			$this->set_view_modes( array( 'table', 'card' ) );

			$this->set_list_columns( array(
				array( 'name' => 'title', 'label' => __( 'Review', 'wp-easycart' ), 'format' => 'rv_title', 'linked' => true ),
				array( 'select' => 'ec_product.title AS product_title', 'name' => 'product_title', 'label' => __( 'Product', 'wp-easycart' ), 'format' => 'rv_product' ),
				array( 'name' => 'rating', 'label' => __( 'Rating', 'wp-easycart' ), 'format' => 'rv_rating' ),
				array( 'name' => 'approved', 'label' => __( 'Approved', 'wp-easycart' ), 'format' => 'rv_approved' ),
				array( 'select' => '1 AS reply_col', 'name' => 'reply_col', 'label' => __( 'Reply', 'wp-easycart' ), 'format' => 'rv_reply', 'tablet_hide' => true ),
				array( 'name' => 'date_submitted', 'label' => __( 'Submitted', 'wp-easycart' ), 'format' => 'rv_date', 'tablet_hide' => true ),
				array( 'name' => 'review_id', 'label' => __( 'ID', 'wp-easycart' ), 'format' => 'int', 'is_id' => true, 'laptop_hide' => true ),
				array( 'name' => 'description', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'reviewer_name', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => ( self::native_available() ? 'ec_review.verified' : '0 AS verified' ), 'name' => 'verified', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => ( self::native_available() ? 'ec_review.reply_text' : '"" AS reply_text' ), 'name' => 'reply_text', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => ( self::native_available() ? 'ec_review.reply_date' : 'NULL AS reply_date' ), 'name' => 'reply_date', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => ( self::native_available() ? 'ec_review.held_reason' : '"" AS held_reason' ), 'name' => 'held_reason', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'user_id', 'format' => 'hidden', 'label' => '' ),
				array( 'name' => 'product_id', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_product.image1', 'name' => 'image1', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_product.product_images', 'name' => 'product_images', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_product.use_optionitem_images', 'name' => 'use_optionitem_images', 'format' => 'hidden', 'label' => '' ),
				array( 'select' => 'ec_product.activate_in_store AS product_active', 'name' => 'product_active', 'format' => 'hidden', 'label' => '' ),
			) );
			$this->set_search_columns( array( 'ec_review.title', 'ec_review.description', 'ec_review.reviewer_name', 'ec_product.title' ) );
			$this->set_bulk_actions( array(
				array( 'name' => 'ecv2-review-approve', 'label' => __( 'Approve', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-review-deny', 'label' => __( 'Deny (hide)', 'wp-easycart' ) ),
				array( 'name' => 'ecv2-review-delete', 'label' => __( 'Delete', 'wp-easycart' ) ),
			) );
			$this->set_row_menu_actions( array(
				array( 'label' => __( 'Edit review', 'wp-easycart' ), 'name' => 'edit', 'icon' => 'edit', 'href' => self::editor_url( '{id}' ) ),
				array( 'label' => __( 'Approve', 'wp-easycart' ), 'name' => 'approve', 'icon' => 'thumbs-up', 'href' => '#', 'onclick' => 'ecv2_catalog.review_set( {id}, 1 ); return false;' ),
				array( 'label' => __( 'Deny', 'wp-easycart' ), 'name' => 'deny', 'icon' => 'thumbs-down', 'href' => '#', 'onclick' => 'ecv2_catalog.review_set( {id}, 0 ); return false;' ),
				array( 'label' => __( 'View product', 'wp-easycart' ), 'name' => 'product', 'icon' => 'products', 'href' => '#', 'onclick' => 'return ecv2_catalog.review_product( this );' ),
				array( 'label' => __( 'Delete', 'wp-easycart' ), 'name' => 'delete', 'icon' => 'trash', 'href' => '#', 'onclick' => 'ecv2_catalog.review_delete( {id} ); return false;', 'danger' => true ),
			) );

			$ratings = array();
			for ( $i = 5; $i >= 1; $i-- ) { $ratings[] = (object) array( 'value' => $i, 'label' => sprintf( _n( '%d star', '%d stars', $i, 'wp-easycart' ), $i ) ); }
			/* 6.0.0: product filter is a typeahead ( ec_admin_ajax_ecv2_product_search ); only the selected product is printed. */
			$products = array();
			$selected_product = (int) $this->filter_value( 2 );
			if ( $selected_product > 0 ) {
				$product_title = $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM ec_product WHERE product_id = %d', $selected_product ) );
				$products[] = (object) array( 'value' => (string) $selected_product, 'label' => ( null !== $product_title ) ? wp_unslash( $product_title ) : '#' . $selected_product );
			}
			$this->set_filters( array(
				array( 'data' => array( (object) array( 'value' => '1', 'label' => __( 'Approved', 'wp-easycart' ), 'icon' => 'yes-alt' ), (object) array( 'value' => '0', 'label' => __( 'Pending', 'wp-easycart' ), 'icon' => 'clock' ) ), 'label' => __( 'Status', 'wp-easycart' ), 'type' => 'pills', 'where' => 'ec_review.approved = %d' ),
				array( 'data' => $ratings, 'label' => __( 'Rating', 'wp-easycart' ), 'type' => 'select', 'where' => 'ec_review.rating = %d' ),
				array( 'data' => $products, 'label' => __( 'Product', 'wp-easycart' ), 'type' => 'select', 'ajax' => array( 'action' => 'ec_admin_ajax_ecv2_product_search' ), 'where' => 'ec_review.product_id = %d' ),
				array( 'data' => array( (object) array( 'value' => '7', 'label' => __( 'Last 7 days', 'wp-easycart' ), 'icon' => 'calendar' ), (object) array( 'value' => '30', 'label' => __( 'Last 30 days', 'wp-easycart' ), 'icon' => 'calendar-alt' ) ), 'label' => __( 'Submitted', 'wp-easycart' ), 'type' => 'pills', 'where_callback' => true ),
				array( 'data' => array( (object) array( 'value' => 'verified', 'label' => __( 'Verified buyers', 'wp-easycart' ), 'icon' => 'yes' ), (object) array( 'value' => 'unverified', 'label' => __( 'Unverified', 'wp-easycart' ), 'icon' => 'minus' ), (object) array( 'value' => 'replied', 'label' => __( 'Replied', 'wp-easycart' ), 'icon' => 'testimonial' ), (object) array( 'value' => 'needs_reply', 'label' => __( 'Needs reply', 'wp-easycart' ), 'icon' => 'flag' ) ), 'label' => __( 'Trust & replies', 'wp-easycart' ), 'type' => 'pills', 'where_callback' => true ),
			) );

			$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_review' );
			$pending = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_review WHERE approved = 0' );
			$avg = $wpdb->get_var( 'SELECT ROUND( AVG( rating ), 1 ) FROM ec_review WHERE approved = 1' );
			$this->set_health_stats( array(
				array( 'label' => __( 'All', 'wp-easycart' ), 'value' => $total, 'filter_value' => '', 'color' => 'default', 'group' => 'catalog' ),
				array( 'label' => __( 'Pending', 'wp-easycart' ), 'value' => $pending, 'filter_value' => 'pending', 'color' => $pending ? 'amber' : 'gray', 'group' => 'catalog' ),
				array( 'label' => __( 'Approved', 'wp-easycart' ), 'value' => $total - $pending, 'filter_value' => 'approved', 'color' => 'green', 'group' => 'catalog' ),
				array( 'label' => __( 'Avg rating', 'wp-easycart' ), 'value' => $avg ? $avg : '—', 'filter_value' => '', 'color' => 'blue', 'group' => 'catalog' ),
				array( 'label' => __( 'Low (1–2★)', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_review WHERE rating <= 2' ), 'filter_value' => 'low', 'color' => 'red', 'group' => 'attention' ),
				array( 'label' => __( 'This week', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_review WHERE date_submitted >= DATE_SUB( NOW(), INTERVAL 7 DAY )' ), 'filter_value' => 'week', 'color' => 'gray', 'group' => 'attention' ),
				array( 'label' => __( 'Orphaned', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_review WHERE NOT EXISTS ( SELECT 1 FROM ec_product p WHERE p.product_id = ec_review.product_id )' ), 'filter_value' => 'orphaned', 'color' => 'red', 'group' => 'attention' ),
			) );
			if ( self::native_available() ) {
				$stats = $this->health_stats;
				array_splice( $stats, 3, 0, array( array( 'label' => __( 'Verified buyers', 'wp-easycart' ), 'value' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_review WHERE verified = 1' ), 'filter_value' => 'verified', 'color' => 'green', 'group' => 'catalog' ) ) );
				if ( self::native_pro() ) {
					$needs = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ec_review WHERE ' . $this->needs_reply_sql() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- needs_reply_sql() returns a static SQL fragment.
					$stats[] = array( 'label' => __( 'Needs reply', 'wp-easycart' ), 'value' => $needs, 'filter_value' => 'needs_reply', 'color' => $needs ? 'amber' : 'gray', 'group' => 'attention' );
				}
				$this->set_health_stats( $stats );
			}
		}

		protected function get_health_filter_where( $k ) {
			switch ( $k ) {
				case 'pending': return 'ec_review.approved = 0';
				case 'approved': return 'ec_review.approved = 1';
				case 'low': return 'ec_review.rating <= 2';
				case 'week': return 'ec_review.date_submitted >= DATE_SUB( NOW(), INTERVAL 7 DAY )';
				case 'orphaned': return 'ec_product.product_id IS NULL';
				case 'verified': return self::native_available() ? 'ec_review.verified = 1' : '1=0';
				case 'unverified': return self::native_available() ? 'ec_review.verified = 0' : '1=1';
				case 'replied': return self::native_available() ? "ec_review.reply_text IS NOT NULL AND ec_review.reply_text != ''" : '1=0';
				case 'needs_reply': return self::native_available() ? $this->needs_reply_sql() : '1=0';
			}
			return '';
		}
		protected function get_filter_callback_where( $i, $v ) {
			if ( 3 === $i && in_array( $v, array( '7', '30' ), true ) ) { return 'ec_review.date_submitted >= DATE_SUB( NOW(), INTERVAL ' . (int) $v . ' DAY )'; }
			if ( 4 === $i ) { return $this->get_health_filter_where( $v ); }
			return '';
		}
		/** Approved, three stars or fewer, no reply yet. */
		private function needs_reply_sql() { return "approved = 1 AND rating <= 3 AND ( reply_text IS NULL OR reply_text = '' )"; }

		protected function print_table_row( $result ) {
			echo '<tr class="ecv2-row' . ( $result->approved ? '' : ' ecv2-row-pending' ) . '" data-id="' . esc_attr( $result->review_id ) . '" data-product-id="' . (int) $result->product_id . '">';
			echo '<td class="ecv2-col-check"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->review_id ) . '" class="ecv2-row-check" /></td>';
			foreach ( $this->list_columns as $col ) {
				if ( 'hidden' === $col['format'] ) { continue; }
				$extra = ( ! empty( $col['tablet_hide'] ) ? ' ecv2-hide-tablet' : '' ) . ( ! empty( $col['laptop_hide'] ) ? ' ecv2-hide-laptop' : '' );
				echo '<td class="ecv2-cell ecv2-cell-' . esc_attr( $col['name'] ) . $extra . '">'; $this->print_cell_content( $result, $col ); echo '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $extra is only static literal class names.
			}
			echo '<td class="ecv2-col-actions">'; $this->print_row_actions( $result ); echo '</td></tr>';
		}

		protected function print_cell_content( $result, $col ) {
			switch ( $col['format'] ) {
				case 'rv_title':
					$title = trim( wp_unslash( (string) $result->title ) );
					echo '<a href="' . esc_url( self::editor_url( $result->review_id ) ) . '" class="ecv2-link-primary ecv2-title-link">' . esc_html( '' !== $title ? $title : __( '(no title)', 'wp-easycart' ) ) . '</a>';
					if ( ! empty( $result->verified ) ) { echo ' <span class="ecv2-chip ecv2-chip-green" title="' . esc_attr__( 'This customer purchased this product', 'wp-easycart' ) . '">✓ ' . esc_html__( 'Verified', 'wp-easycart' ) . '</span>'; }
					if ( ! $result->approved && ! empty( $result->held_reason ) ) { $reasons = array( 'duplicate' => __( 'Duplicate', 'wp-easycart' ), 'links' => __( 'Contains a link', 'wp-easycart' ), 'blocked_word' => __( 'Blocked word', 'wp-easycart' ), 'unverified' => __( 'Not a verified buyer', 'wp-easycart' ) ); echo ' <span class="ecv2-chip ecv2-chip-amber" title="' . esc_attr__( 'Held by a moderation rule', 'wp-easycart' ) . '">' . esc_html( isset( $reasons[ $result->held_reason ] ) ? $reasons[ $result->held_reason ] : $result->held_reason ) . '</span>'; }
					$excerpt = wp_strip_all_tags( wp_unslash( (string) $result->description ) );
					if ( strlen( $excerpt ) > 110 ) { $excerpt = substr( $excerpt, 0, 108 ) . '…'; }
					$who = trim( (string) $result->reviewer_name );
					if ( '' === $who && $result->user_id ) { $u = get_userdata( (int) $result->user_id ); $who = $u ? $u->display_name : ''; }
					echo '<span class="ecv2-sub">' . ( $who ? '<b>' . esc_html( $who ) . '</b> · ' : '' ) . esc_html( $excerpt ) . '</span>';
					break;
				case 'rv_product':
					if ( ! $result->product_id || null === $result->product_title ) { echo '<span class="ecv2-sub-danger">' . esc_html__( 'Product deleted', 'wp-easycart' ) . '</span>'; break; }
					$img = wp_easycart_admin_catalog_v2_product_thumb( $result );
					echo '<div class="ecv2-tree"><span class="ecv2-thumb">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy">' : '<span class="dashicons dashicons-format-image"></span>' ) . '</span><div class="ecv2-tree-text"><a class="ecv2-link-primary" href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=products&ec_admin_form_action=edit&product_id=' . (int) $result->product_id ) ) . '">' . esc_html( wp_unslash( $result->product_title ) ) . '</a>' . ( $result->product_active ? '' : '<span class="ecv2-sub ecv2-sub-warn">' . esc_html__( 'Inactive', 'wp-easycart' ) . '</span>' ) . '</div></div>';
					break;
				case 'rv_rating': echo self::stars_html( $result->rating ); break; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stars_html() returns markup built from esc_attr() and literals with an int-clamped rating.
				case 'rv_reply':
					if ( ! self::native_available() ) { echo '<span class="ecv2-sub">—</span>'; break; }
					if ( ! empty( $result->reply_text ) ) { $ts = $result->reply_date ? strtotime( $result->reply_date ) : 0; echo '<span class="ecv2-chip ecv2-chip-gray" title="' . esc_attr( wp_strip_all_tags( wp_unslash( $result->reply_text ) ) ) . '">' . esc_html__( 'Replied', 'wp-easycart' ) . ( $ts ? ' · ' . esc_html( human_time_diff( $ts ) ) : '' ) . '</span>'; }
					else if ( self::native_pro() && $result->approved && (int) $result->rating <= 3 ) { echo '<a class="ecv2-chip ecv2-chip-amber" href="' . esc_url( self::editor_url( $result->review_id ) . '#rvv2-reply' ) . '">' . esc_html__( 'Needs reply', 'wp-easycart' ) . '</a>'; }
					else if ( ! self::native_pro() && $result->approved && (int) $result->rating <= 3 ) { echo '<span class="ecv2-chip" title="' . esc_attr( wp_easycart_admin_edition::requires_text( __( 'Replying publicly', 'wp-easycart' ), 'pro' ) ) . '"><span class="dashicons dashicons-lock" style="font-size:12px;width:12px;height:12px"></span> ' . esc_html__( 'Reply', 'wp-easycart' ) . '</span>'; }
					else { echo '<span class="ecv2-sub">—</span>'; }
					break;
				case 'rv_approved':
					echo '<label class="ecv2-toggle ecv2-toggle-sm" title="' . esc_attr__( 'Approved — shown in the store', 'wp-easycart' ) . '"><input type="checkbox" class="ecv2-review-toggle" data-id="' . esc_attr( $result->review_id ) . '"' . ( $result->approved ? ' checked' : '' ) . ' /><span class="ecv2-toggle-slider"></span></label>';
					if ( ! $result->approved ) { echo ' <span class="ecv2-chip ecv2-chip-amber">' . esc_html__( 'Pending', 'wp-easycart' ) . '</span>'; }
					break;
				case 'rv_date':
					$ts = strtotime( $result->date_submitted );
					echo '<span title="' . esc_attr( $result->date_submitted ) . '">' . esc_html( $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '—' ) . '</span>';
					if ( $ts && $ts > time() - 7 * DAY_IN_SECONDS ) { echo '<span class="ecv2-sub">' . esc_html( sprintf( __( '%s ago', 'wp-easycart' ), human_time_diff( $ts ) ) ) . '</span>'; }
					break;
				default: parent::print_cell_content( $result, $col );
			}
		}

		protected function print_card( $result ) {
			echo '<div class="ecv2-card ecv2-rv-card' . ( $result->approved ? '' : ' ecv2-card-pending' ) . '" data-id="' . esc_attr( $result->review_id ) . '"><div class="ecv2-card-body">';
			echo '<div class="ecv2-os-card-head">' . self::stars_html( $result->rating, false ) . ( $result->approved ? '<span class="ecv2-chip ecv2-chip-green">' . esc_html__( 'Approved', 'wp-easycart' ) . '</span>' : '<span class="ecv2-chip ecv2-chip-amber">' . esc_html__( 'Pending', 'wp-easycart' ) . '</span>' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stars_html() returns markup built from esc_attr() and literals with an int-clamped rating.
			echo '<h3 class="ecv2-card-title"><a href="' . esc_url( self::editor_url( $result->review_id ) ) . '">' . esc_html( wp_unslash( $result->title ) ? wp_unslash( $result->title ) : __( '(no title)', 'wp-easycart' ) ) . '</a></h3>';
			echo '<p class="ecv2-rv-text">' . esc_html( wp_strip_all_tags( wp_unslash( (string) $result->description ) ) ) . '</p>';
			echo '<span class="ecv2-sub">' . esc_html( trim( (string) $result->reviewer_name ) ) . ( $result->product_title ? ' · ' . esc_html( wp_unslash( $result->product_title ) ) : '' ) . ' · ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $result->date_submitted ) ) ) . '</span>';
			echo '</div><div class="ecv2-card-footer"><input type="checkbox" name="bulk[]" value="' . esc_attr( $result->review_id ) . '" class="ecv2-row-check" /> <label class="ecv2-toggle ecv2-toggle-sm"><input type="checkbox" class="ecv2-review-toggle" data-id="' . esc_attr( $result->review_id ) . '"' . ( $result->approved ? ' checked' : '' ) . ' /><span class="ecv2-toggle-slider"></span></label>';
			$this->print_row_actions( $result );
			echo '</div></div>';
		}
	}

	class wp_easycart_admin_review_editor_v2 {
		const NONCE = 'wp-easycart-rvv2';
		public $r; public $product; public $user; public $other_reviews = array(); public $product_stats; public $docs_link = '';
		public function __construct() { $this->docs_link = wp_easycart_admin()->helpsystem->print_docs_url( 'products', 'product-reviews', 'product-reviews' ); }
		public function load( $id ) {
			global $wpdb;
			$this->r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_review WHERE review_id = %d', $id ) );
			if ( ! $this->r ) { return false; }
			$this->product = $wpdb->get_row( $wpdb->prepare( 'SELECT product_id, title, model_number, price, activate_in_store, post_id, ' . wp_easycart_admin_catalog_v2_thumb_select( 'ec_product' ) . ' FROM ec_product WHERE product_id = %d', $this->r->product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- wp_easycart_admin_catalog_v2_thumb_select() returns a static column list; the id goes through prepare().
			if ( $this->product ) { $this->product_stats = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS n, ROUND( AVG( rating ), 1 ) AS avg, SUM( approved = 0 ) AS pending FROM ec_review WHERE product_id = %d', $this->r->product_id ) ); }
			$this->user = $this->r->user_id ? get_userdata( (int) $this->r->user_id ) : null;
			if ( $this->r->user_id || '' !== trim( (string) $this->r->reviewer_name ) ) {
				$this->other_reviews = $wpdb->get_results( $wpdb->prepare( 'SELECT r.review_id, r.title, r.rating, r.approved, r.date_submitted, p.title AS product_title FROM ec_review r LEFT JOIN ec_product p ON p.product_id = r.product_id WHERE r.review_id != %d AND ( ( %d > 0 AND r.user_id = %d ) OR ( %s != "" AND r.reviewer_name = %s ) ) ORDER BY r.date_submitted DESC LIMIT 10', $id, (int) $this->r->user_id, (int) $this->r->user_id, (string) $this->r->reviewer_name, (string) $this->r->reviewer_name ) );
			}
			$this->load_native();
			return true;
		}
		public $native = false; public $pro = false; public $request = null; public $summary = null;
		public function load_native() {
			global $wpdb;
			$this->native = wp_easycart_admin_review_table::native_available();
			$this->pro = wp_easycart_admin_review_table::native_pro();
			if ( $this->native && $this->r ) {
				if ( ! empty( $this->r->request_id ) ) { $this->request = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_review_request WHERE request_id = %d', (int) $this->r->request_id ) ); }
				if ( $this->product ) { $this->summary = ec_reviews::summary( (int) $this->product->product_id ); }
			}
		}
		public function health() {
			$r = $this->r;
			$out = array();
			if ( $this->native ) {
				$out[] = array( 'ok' => (bool) $r->verified, 'label' => $r->verified ? __( 'Verified buyer — purchased this product', 'wp-easycart' ) : __( 'Not matched to a purchase', 'wp-easycart' ) );
				if ( $this->pro ) { $out[] = array( 'ok' => ! ( $r->approved && (int) $r->rating <= 3 && '' === trim( (string) $r->reply_text ) ), 'label' => '' !== trim( (string) $r->reply_text ) ? __( 'Replied publicly', 'wp-easycart' ) : ( $r->approved && (int) $r->rating <= 3 ? __( 'Low rating with no reply yet', 'wp-easycart' ) : __( 'No reply needed', 'wp-easycart' ) ) ); }
				if ( ! $r->approved && ! empty( $r->held_reason ) ) { $out[] = array( 'ok' => false, 'label' => sprintf( __( 'Held by rule: %s', 'wp-easycart' ), $r->held_reason ) ); }
			}
			$out = array_merge( $out, array(
				array( 'ok' => (bool) $r->approved, 'label' => $r->approved ? __( 'Approved — visible in store', 'wp-easycart' ) : __( 'Pending — not shown to shoppers', 'wp-easycart' ) ),
				array( 'ok' => (bool) $this->product, 'label' => $this->product ? __( 'Product exists', 'wp-easycart' ) : __( 'Product was deleted — review is orphaned', 'wp-easycart' ) ),
				array( 'ok' => '' !== trim( wp_strip_all_tags( (string) $r->description ) ), 'label' => '' !== trim( wp_strip_all_tags( (string) $r->description ) ) ? __( 'Has review text', 'wp-easycart' ) : __( 'Rating only, no text', 'wp-easycart' ) ),
				array( 'ok' => (bool) $this->user || '' !== trim( (string) $r->reviewer_name ), 'label' => $this->user ? __( 'Linked to a customer account', 'wp-easycart' ) : ( '' !== trim( (string) $r->reviewer_name ) ? __( 'Guest reviewer', 'wp-easycart' ) : __( 'Anonymous — no name', 'wp-easycart' ) ) ),
			) );
			if ( $this->product && $this->product_stats && (int) $this->product_stats->pending > 1 ) { $out[] = array( 'ok' => false, 'label' => sprintf( __( '%d more reviews pending on this product', 'wp-easycart' ), (int) $this->product_stats->pending - ( $r->approved ? 0 : 1 ) ) ); }
			return $out;
		}
		public function output() {
			if ( ! $this->load( isset( $_GET['review_id'] ) ? (int) $_GET['review_id'] : 0 ) ) {
				echo '<div class="ecv2-wrap"><div class="ecv2-empty-state">' . esc_html__( 'That review no longer exists.', 'wp-easycart' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wp-easycart-products&subpage=reviews' ) ) . '">' . esc_html__( 'Back to reviews', 'wp-easycart' ) . '</a></div></div>'; return;
			}
			include( EC_PLUGIN_DIRECTORY . '/admin/template/products/reviews/review-editor-v2.php' );
		}
		public function js_data() {
			return array( 'kind' => 'review', 'id' => (int) $this->r->review_id, 'nonce' => wp_create_nonce( self::NONCE ), 'save_action' => 'ecv2_review_save', 'native' => $this->native, 'is_pro' => $this->pro, 'reply_action' => 'ecv2_review_reply', 'list_url' => admin_url( 'admin.php?page=wp-easycart-products&subpage=reviews' ), 'redirects' => false, 'slug' => '' );
		}
	}

endif;

/* ---------------------------------------------------------------------- */
function ecv2_rv_guard( $nonce = 'wp-easycart-ecv2-inline-update', $field = 'wp_easycart_nonce' ) {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpec_products' ) ) { wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-easycart' ) ) ); }
	check_ajax_referer( $nonce, $field );
}
function ecv2_review_set_approved( $id, $val ) {
	global $wpdb;
	$wpdb->update( 'ec_review', array( 'approved' => $val ? 1 : 0 ), array( 'review_id' => (int) $id ) );
	do_action( $val ? 'wpeasycart_review_approved' : 'wpeasycart_review_unapproved', (int) $id );
	do_action( 'wpeasycart_review_updated', (int) $id );
}

add_action( 'wp_ajax_ecv2_review_toggle', 'ecv2_review_toggle' );
function ecv2_review_toggle() {
	ecv2_rv_guard();
	$id = isset( $_POST['review_id'] ) ? (int) $_POST['review_id'] : 0; $val = ! empty( $_POST['approved'] ) && '0' !== $_POST['approved'] ? 1 : 0;
	if ( ! $id ) { wp_send_json_error( array( 'message' => __( 'Invalid review.', 'wp-easycart' ) ) ); }
	ecv2_review_set_approved( $id, $val );
	wp_send_json_success( array( 'approved' => $val ) );
}

add_action( 'wp_ajax_ecv2_review_bulk', 'ecv2_review_bulk' );
function ecv2_review_bulk() {
	ecv2_rv_guard(); global $wpdb;
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_slice( array_map( 'intval', $_POST['ids'] ), 0, 500 ) : array();
	$op = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	if ( empty( $ids ) ) { wp_send_json_error( array( 'message' => __( 'Nothing selected.', 'wp-easycart' ) ) ); }
	$done = 0; $snap = array();
	foreach ( $ids as $id ) {
		if ( 'approve' === $op || 'deny' === $op ) { ecv2_review_set_approved( $id, 'approve' === $op ); $done++; }
		else if ( 'delete' === $op ) { $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_review WHERE review_id = %d', $id ), ARRAY_A ); if ( $row ) { $snap[] = $row; do_action( 'wpeasycart_review_deleting', $id ); $wpdb->delete( 'ec_review', array( 'review_id' => $id ) ); $done++; } }
	}
	$undo = '';
	if ( $snap ) { $undo = 'rv_' . time() . '_' . wp_rand( 100, 999 ); set_transient( 'ec_review_undo_' . $undo, $snap, 15 * MINUTE_IN_SECONDS ); }
	wp_send_json_success( array( 'done' => $done, 'undo' => $undo ) );
}

add_action( 'wp_ajax_ecv2_review_delete', 'ecv2_review_delete' );
function ecv2_review_delete() {
	ecv2_rv_guard(); global $wpdb;
	$id = isset( $_POST['review_id'] ) ? (int) $_POST['review_id'] : 0;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ec_review WHERE review_id = %d', $id ), ARRAY_A );
	if ( ! $row ) { wp_send_json_error( array( 'message' => __( 'Review not found.', 'wp-easycart' ) ) ); }
	do_action( 'wpeasycart_review_deleting', $id );
	$wpdb->delete( 'ec_review', array( 'review_id' => $id ) );
	$undo = 'rv_' . time() . '_' . wp_rand( 100, 999 );
	set_transient( 'ec_review_undo_' . $undo, array( $row ), 15 * MINUTE_IN_SECONDS );
	wp_send_json_success( array( 'undo' => $undo, 'message' => sprintf( __( 'Deleted review “%s”.', 'wp-easycart' ), wp_unslash( $row['title'] ) ) ) );
}

add_action( 'wp_ajax_ecv2_review_restore', 'ecv2_review_restore' );
function ecv2_review_restore() {
	ecv2_rv_guard(); global $wpdb;
	$key = isset( $_POST['undo'] ) ? preg_replace( '/[^a-z0-9_]/', '', (string) $_POST['undo'] ) : '';
	$rows = get_transient( 'ec_review_undo_' . $key );
	if ( ! $rows || ! is_array( $rows ) ) { wp_send_json_error( array( 'message' => __( 'This deletion can no longer be undone.', 'wp-easycart' ) ) ); }
	foreach ( $rows as $row ) { $wpdb->replace( 'ec_review', $row ); }
	delete_transient( 'ec_review_undo_' . $key );
	wp_send_json_success( array( 'message' => sprintf( _n( 'Restored %d review.', 'Restored %d reviews.', count( $rows ), 'wp-easycart' ), count( $rows ) ) ) );
}

add_action( 'wp_ajax_ecv2_review_save', 'ecv2_review_save' );
function ecv2_review_save() {
	ecv2_rv_guard( wp_easycart_admin_review_editor_v2::NONCE, 'nonce' ); global $wpdb;
	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT review_id FROM ec_review WHERE review_id = %d', $id ) ) ) { wp_send_json_error( array( 'message' => __( 'Review not found.', 'wp-easycart' ) ) ); }
	$d = json_decode( wp_unslash( isset( $_POST['data'] ) ? $_POST['data'] : '{}' ), true );
	if ( ! is_array( $d ) ) { wp_send_json_error( array( 'message' => __( 'Invalid payload.', 'wp-easycart' ) ) ); }
	$rating = isset( $d['rating'] ) ? max( 1, min( 5, (int) $d['rating'] ) ) : 5;
	$date = isset( $d['date_submitted'] ) && strtotime( $d['date_submitted'] ) ? date( 'Y-m-d H:i:s', strtotime( $d['date_submitted'] ) ) : null;
	$upd = array(
		'title' => isset( $d['title'] ) ? wp_easycart_escape_html( $d['title'] ) : '',
		'description' => isset( $d['description'] ) ? wp_easycart_escape_html( $d['description'] ) : '',
		'reviewer_name' => isset( $d['reviewer_name'] ) ? sanitize_text_field( $d['reviewer_name'] ) : '',
		'rating' => $rating,
		'approved' => ! empty( $d['approved'] ) ? 1 : 0,
	);
	if ( $date ) { $upd['date_submitted'] = $date; }
	$wpdb->update( 'ec_review', $upd, array( 'review_id' => $id ) );
	do_action( 'wpeasycart_review_updated', $id );
	$ed = new wp_easycart_admin_review_editor_v2(); $ed->load( $id );
	wp_send_json_success( array( 'message' => __( 'Saved', 'wp-easycart' ), 'health' => $ed->health(), 'approved' => $upd['approved'] ) );
}

/** Post / clear the public reply ( PRO ). POST: nonce, review_id, reply, email ( 0|1 ). */
add_action( 'wp_ajax_ecv2_review_reply', 'ecv2_review_reply' );
function ecv2_review_reply() {
	ecv2_rv_guard( wp_easycart_admin_review_editor_v2::NONCE, 'nonce' );
	if ( ! wp_easycart_admin_review_table::native_available() ) { wp_send_json_error( array( 'message' => __( 'Database update required for replies.', 'wp-easycart' ) ) ); }
	$r = ec_reviews::set_reply( isset( $_POST['review_id'] ) ? (int) $_POST['review_id'] : 0, isset( $_POST['reply'] ) ? wp_unslash( $_POST['reply'] ) : '', get_current_user_id(), isset( $_POST['email'] ) ? (bool) (int) $_POST['email'] : null );
	if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
	$ts = $r['reply_date'] ? strtotime( $r['reply_date'] ) : 0;
	wp_send_json_success( array( 'message' => $r['reply_date'] ? ( $r['emailed'] ? __( 'Reply posted and emailed to the reviewer.', 'wp-easycart' ) : __( 'Reply posted.', 'wp-easycart' ) ) : __( 'Reply removed.', 'wp-easycart' ), 'reply_date' => $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '', 'signature' => ec_reviews::reply_signature() ) );
}
