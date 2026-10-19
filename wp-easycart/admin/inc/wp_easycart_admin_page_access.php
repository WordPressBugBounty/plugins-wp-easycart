<?php
/**
 * WP EasyCart Limit Access settings ( 6.0.5, Phase 2 ): the editor box, Users › Limit Access, the Access column of the post lists.
 *
 * Every rule is saved and read through wp_easycart_page_access ( save_rule(), rule(), problems() ). Plan and decisions D1–D14:
 * https://claude.ai/artifact/XjaV96DLMp5nkVg4nDG5QN
 *
 * @package wp-easycart
 * @since 6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_page_access_guard' ) ) {
	/**
	 * Guard for the Limit Access AJAX handlers: the nonce and a store administrator or store manager.
	 *
	 * @since 6.0.5
	 */
	function ecv2_page_access_guard() {
		if ( ! check_ajax_referer( 'wp-easycart-ecv2-page-access', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Reload it and try again.', 'wp-easycart' ) ), 403 );
		}
		if ( ! wp_easycart_admin_page_access::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'wp-easycart' ) ), 403 );
		}
	}
}

if ( ! class_exists( 'wp_easycart_admin_page_access' ) ) :

	/**
	 * Limit Access in the admin.
	 */
	final class wp_easycart_admin_page_access {

		/** Users › Limit Access ( admin.php?page=wp-easycart-users&subpage=… ). */
		const SUBPAGE = 'limit-access';

		/** Rows per page on Users › Limit Access. */
		const PER_PAGE = 30;

		/**
		 * Hooks.
		 */
		public static function init() {
			if ( ! class_exists( 'wp_easycart_page_access' ) ) {
				return;
			}
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
			add_action( 'wp_ajax_ecv2_page_access_search', array( __CLASS__, 'ajax_search' ) );
			add_action( 'wp_ajax_ecv2_page_access_form', array( __CLASS__, 'ajax_form' ) );
			add_action( 'wp_ajax_ecv2_page_access_save', array( __CLASS__, 'ajax_save' ) );
			add_action( 'wp_ajax_ecv2_page_access_toggle', array( __CLASS__, 'ajax_toggle' ) );
			add_action( 'admin_init', array( __CLASS__, 'list_columns' ) );
		}

		/**
		 * Who may set Limit Access: store administrators and store managers ( as the box always was ).
		 *
		 * @return bool
		 */
		public static function can_manage() {
			return current_user_can( 'manage_options' ) || current_user_can( 'wpec_manager' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- EasyCart's own role.
		}

		/**
		 * The screen's address.
		 *
		 * @param array $args Query arguments.
		 * @return string
		 */
		public static function screen_url( $args = array() ) {
			return add_query_arg(
				array_merge(
					array(
						'page'    => 'wp-easycart-users',
						'subpage' => self::SUBPAGE,
					),
					$args
				),
				admin_url( 'admin.php' )
			);
		}

		/**
		 * Whether this request is Users › Limit Access.
		 *
		 * @return bool
		 */
		public static function is_screen() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen this is.
			return isset( $_GET['page'], $_GET['subpage'] ) && 'wp-easycart-users' === $_GET['page'] && self::SUBPAGE === $_GET['subpage'];
		}

		/**
		 * Styles and script: on Users › Limit Access, and on the edit screen of a post type that has the box.
		 *
		 * @param string $hook Admin page hook.
		 */
		public static function enqueue( $hook ) {
			$box = false;
			if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && self::can_manage() ) {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				$box    = $screen && in_array( $screen->post_type, wp_easycart_page_access::post_types(), true );
			}
			if ( ! $box && ! self::is_screen() ) {
				return;
			}
			wp_enqueue_style( 'wp_easycart_page_access_v2', plugins_url( 'wp-easycart/admin/css/page-access-v2.css', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wp_easycart_page_access_v2', plugins_url( 'wp-easycart/admin/js/page-access-v2.js', EC_PLUGIN_DIRECTORY ), array(), EC_CURRENT_VERSION, true );
			wp_localize_script(
				'wp_easycart_page_access_v2',
				'wpecPageAccess',
				array(
					'ajax'  => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'wp-easycart-ecv2-page-access' ),
					'home'  => home_url( '/' ),
					'text'  => self::script_text(),
				)
			);
		}

		/**
		 * The script's wording.
		 *
		 * @return array
		 */
		private static function script_text() {
			return array(
				'everyone'       => __( 'Everyone can open this page.', 'wp-easycart' ),
				'nobody'         => __( 'Until you choose who can see it below, everyone can still open it.', 'wp-easycart' ),
				/* The status card ( print_status() ); the who / where words are wp_easycart_page_access::who_text() / where_text()'s. */
				'chooseWho'      => __( 'Choose who can see this page', 'wp-easycart' ),
				'membersOnly'    => __( 'Members only', 'wp-easycart' ),
				'fixBelow'       => __( 'Check the notes below.', 'wp-easycart' ),
				'rowWho'         => __( 'Open to', 'wp-easycart' ),
				'rowSignedOut'   => __( 'Signed-out visitors', 'wp-easycart' ),
				'rowNoAccess'    => __( 'Customers without access', 'wp-easycart' ),
				'rowMembers'     => __( 'Customers who can see it', 'wp-easycart' ),
				'anySignedIn'    => __( 'Any signed-in customer', 'wp-easycart' ),
				/* translators: %s: product names */
				'bought'         => __( 'Bought %s', 'wp-easycart' ),
				/* translators: %d: number of other products */
				'andMoreOne'     => _n( 'and %d more', 'and %d more', 1, 'wp-easycart' ),
				/* translators: %d: number of other products */
				'andMoreMany'    => _n( 'and %d more', 'and %d more', 2, 'wp-easycart' ),
				/* translators: %s: role names */
				'roleOne'        => _n( 'Role %s', 'Roles %s', 1, 'wp-easycart' ),
				/* translators: %s: role names */
				'roleMany'       => _n( 'Role %s', 'Roles %s', 2, 'wp-easycart' ),
				/* translators: %d: number of customers */
				'custOne'        => _n( '%d chosen customer', '%d chosen customers', 1, 'wp-easycart' ),
				/* translators: %d: number of customers */
				'custMany'       => _n( '%d chosen customer', '%d chosen customers', 2, 'wp-easycart' ),
				'joinAnd'        => __( ' and ', 'wp-easycart' ),
				'joinOr'         => __( ' or ', 'wp-easycart' ),
				'whereSignIn'    => __( 'Sign in, then back', 'wp-easycart' ),
				'whereMessage'   => __( 'What unlocks it', 'wp-easycart' ),
				'whereProduct'   => __( 'The product', 'wp-easycart' ),
				'whereStore'     => __( 'The store page', 'wp-easycart' ),
				'whereStay'      => __( 'Stay on the page', 'wp-easycart' ),
				'wherePageNone'  => __( 'A missing page', 'wp-easycart' ),
				'whereUrlNone'   => __( 'No address', 'wp-easycart' ),
				'self'           => __( 'That is this same page: visitors would go round in circles. Choose another page.', 'wp-easycart' ),
				'limited'        => __( 'That page is limited too. Check the visitors sent there can open it.', 'wp-easycart' ),
				'limitedOut'     => __( 'That page is limited too, so signed-out visitors could not open it either. Choose another page.', 'wp-easycart' ),
				'noPage'         => __( 'Choose a page.', 'wp-easycart' ),
				'noAddress'      => __( 'Enter a full https:// address or a path on this site, such as /join.', 'wp-easycart' ),
				'external'       => __( 'That address is on another site. Tick "Send visitors to another site" to allow it.', 'wp-easycart' ),
				'noProduct'      => __( 'No product unlocks this page yet, so these customers see what unlocks it instead.', 'wp-easycart' ),
				'saving'         => __( 'Saving…', 'wp-easycart' ),
				'saved'          => __( 'Saved.', 'wp-easycart' ),
				'failed'         => __( 'That did not save. Try again.', 'wp-easycart' ),
				'choosePage'     => __( 'Choose the page to limit first.', 'wp-easycart' ),
				'loading'        => __( 'Loading…', 'wp-easycart' ),
				'limitedChip'    => __( 'limited', 'wp-easycart' ),
				'confirmOffMany' => __( 'Turn off the limits of the chosen pages? Everyone will be able to open them; their settings are kept.', 'wp-easycart' ),
			);
		}

		/* ------------------------------------------------------------------ The fields ( editor box and drawer ) */

		/**
		 * The editor box ( D14: PHP, works in the classic and the block editor ).
		 *
		 * @param WP_Post $post Post.
		 */
		public static function print_box( $post ) {
			wp_nonce_field( 'wp_easycart_page_lock_' . $post->ID, 'wp_easycart_page_lock_nonce' );
			echo '<div class="wpec-access wpec-access-box">';
			self::print_fields( (int) $post->ID );
			echo '<p class="wpec-access-foot"><a href="' . esc_url( self::screen_url() ) . '">' . esc_html__( 'All limited pages', 'wp-easycart' ) . '</a></p>';
			echo '</div>';
		}

		/**
		 * The rule's fields ( box and drawer share them ). Field names: wpec_access[…].
		 *
		 * @param int $post_id Post ( 0 = none chosen yet ).
		 */
		public static function print_fields( $post_id ) {
			global $wpdb;
			$rule     = ( $post_id > 0 ) ? wp_easycart_page_access::rule( $post_id ) : wp_easycart_page_access::defaults();
			$uid      = 'wpec_access_' . ( $post_id > 0 ? $post_id : 'new' );
			$rule     = self::shown_rule( $rule ); /* a page set up before 6.0.5, or one with no rule yet */
			$problems = ( $post_id > 0 ) ? wp_easycart_page_access::problems( $post_id ) : array();
			echo '<div class="wpec-access-fields" data-post="' . esc_attr( $post_id ) . '" data-post-url="' . esc_attr( $post_id > 0 ? (string) get_permalink( $post_id ) : '' ) . '" data-legacy="' . ( ! empty( $rule['legacy'] ) ? '1' : '0' ) . '">';
			echo '<input type="hidden" name="wpec_access[present]" value="1" />';

			if ( wp_easycart_page_access::paused() ) {
				echo '<p class="wpec-access-note is-warn">' . esc_html__( 'Every limit is paused in Settings › Accounts, so everyone can open this page for now.', 'wp-easycart' ) . '</p>';
			}
			if ( $post_id > 0 && wp_easycart_page_access::exempt( $post_id ) ) {
				echo '<p class="wpec-access-note">' . esc_html__( 'The account and cart pages are never limited, so signing in and buying always work.', 'wp-easycart' ) . '</p>';
			}

			/* 6.0.5 design: a switch, then the status ( who can open the page, where everyone else goes ), then the two groups. */
			echo '<label class="wpec-access-switch"><input type="checkbox" role="switch" name="wpec_access[on]" value="1" class="wpec-access-on"' . checked( ! empty( $rule['on'] ), true, false ) . ' /> <span class="wpec-access-switch-text">' . esc_html__( 'Limit who can see this page', 'wp-easycart' ) . '</span></label>';
			self::print_status( $post_id, $rule, $problems );

			echo '<div class="wpec-access-body"' . ( empty( $rule['on'] ) ? ' hidden' : '' ) . '>';

			/* Who can see it */
			echo '<fieldset class="wpec-access-group" data-group="who"><legend>' . esc_html__( 'Who can see it', 'wp-easycart' ) . '</legend>';
			echo '<label class="wpec-access-check"><input type="checkbox" name="wpec_access[signed_in]" value="1" class="wpec-access-signed-in"' . checked( ! empty( $rule['signed_in'] ), true, false ) . ' /> <span>' . esc_html__( 'Any signed-in customer', 'wp-easycart' ) . '</span></label>';

			$product_labels = array();
			if ( ! empty( $rule['products'] ) ) {
				foreach ( $rule['products'] as $product_id ) {
					$product_labels[ $product_id ] = '#' . $product_id;
				}
				$rows = $wpdb->get_results( 'SELECT product_id, title FROM ec_product WHERE product_id IN ( ' . implode( ',', array_map( 'intval', $rule['products'] ) ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integer ids cast with intval.
				foreach ( (array) $rows as $row ) {
					$product_labels[ (int) $row->product_id ] = wp_unslash( $row->title );
				}
			}
			echo '<div class="wpec-access-row"><span class="wpec-access-label" id="' . esc_attr( $uid ) . '_products_label">' . esc_html__( 'Customers who bought', 'wp-easycart' ) . '</span>';
			wp_easycart_admin::print_picker(
				array(
					'id'          => $uid . '_products',
					'mode'        => 'product',
					'name'        => 'wpec_access[products][]',
					'multiple'    => true,
					'selected'    => $product_labels,
					'placeholder' => __( 'Search products', 'wp-easycart' ),
					'on_change'   => 'wpecPageAccessChanged',
				)
			);
			echo '<span class="wpec-access-hint">' . esc_html__( 'Subscriptions while they run, installment plans once paid in full, anything else once paid.', 'wp-easycart' ) . '</span></div>';

			$roles = wp_easycart_page_access::role_labels();
			foreach ( $rule['roles'] as $label ) {
				if ( ! in_array( $label, $roles, true ) ) {
					$roles[] = $label; /* a role saved before it was renamed or removed stays visible */
				}
			}
			if ( ! empty( $roles ) ) {
				echo '<div class="wpec-access-row" role="group" aria-labelledby="' . esc_attr( $uid ) . '_roles_label"><span class="wpec-access-label" id="' . esc_attr( $uid ) . '_roles_label">' . esc_html__( 'Customers with a role', 'wp-easycart' ) . '</span><span class="wpec-access-pills">';
				foreach ( $roles as $label ) {
					echo '<label class="wpec-access-pill"><input type="checkbox" name="wpec_access[roles][]" value="' . esc_attr( $label ) . '" class="wpec-access-role"' . checked( in_array( $label, $rule['roles'], true ), true, false ) . ' /><span>' . esc_html( $label ) . '</span></label>';
				}
				echo '</span></div>';
			}

			$user_labels = array();
			if ( ! empty( $rule['users'] ) ) {
				foreach ( $rule['users'] as $user_id ) {
					$user_labels[ $user_id ] = '#' . $user_id;
				}
				$rows = $wpdb->get_results( 'SELECT user_id, first_name, last_name, email FROM ec_user WHERE user_id IN ( ' . implode( ',', array_map( 'intval', $rule['users'] ) ) . ' )' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integer ids cast with intval.
				foreach ( (array) $rows as $row ) {
					$name                               = trim( wp_unslash( $row->first_name . ' ' . $row->last_name ) );
					$user_labels[ (int) $row->user_id ] = ( '' !== $name ? $name : wp_unslash( $row->email ) ) . ' (' . (int) $row->user_id . ')';
				}
			}
			echo '<div class="wpec-access-row"><span class="wpec-access-label">' . esc_html__( 'These customers', 'wp-easycart' ) . '</span>';
			wp_easycart_admin::print_picker(
				array(
					'id'          => $uid . '_users',
					'mode'        => 'user',
					'name'        => 'wpec_access[users][]',
					'multiple'    => true,
					'selected'    => $user_labels,
					'placeholder' => __( 'Search customers', 'wp-easycart' ),
					'on_change'   => 'wpecPageAccessChanged',
				)
			);
			echo '</div>';

			/* Any one / all: it only decides something once two kinds of choice are made ( the script hides it otherwise ). */
			$kinds = (int) ! empty( $rule['signed_in'] ) + (int) ! empty( $rule['products'] ) + (int) ! empty( $rule['roles'] ) + (int) ! empty( $rule['users'] );
			$any   = __( 'A customer who meets any one of the choices above can open the page.', 'wp-easycart' );
			$all   = __( 'A customer must meet every choice above to open the page.', 'wp-easycart' );
			echo '<div class="wpec-access-row wpec-access-match"' . ( $kinds < 2 ? ' hidden' : '' ) . ' data-hint-any="' . esc_attr( $any ) . '" data-hint-all="' . esc_attr( $all ) . '">';
			echo '<span class="wpec-access-label" id="' . esc_attr( $uid ) . '_match_label">' . esc_html__( 'Customers need', 'wp-easycart' ) . '</span>';
			echo '<span class="wpec-access-seg" role="radiogroup" aria-labelledby="' . esc_attr( $uid ) . '_match_label">';
			echo '<label><input type="radio" name="wpec_access[match]" value="any"' . checked( 'all' !== $rule['match'], true, false ) . ' /><span>' . esc_html__( 'Any one', 'wp-easycart' ) . '</span></label>';
			echo '<label><input type="radio" name="wpec_access[match]" value="all"' . checked( 'all' === $rule['match'], true, false ) . ' /><span>' . esc_html__( 'All of them', 'wp-easycart' ) . '</span></label>';
			echo '</span>';
			echo '<span class="wpec-access-hint wpec-access-match-hint">' . esc_html( 'all' === $rule['match'] ? $all : $any ) . '</span>';
			echo '</div>';
			echo '</fieldset>';

			/* Where everyone else goes */
			$choices = array(
				'signed_out' => array(
					'title'   => __( 'Signed-out visitors', 'wp-easycart' ),
					'options' => array(
						'sign_in' => __( 'Sign in, then come back here', 'wp-easycart' ),
						'page'    => __( 'A page on this site', 'wp-easycart' ),
						'url'     => __( 'Another address', 'wp-easycart' ),
					),
				),
				'no_access'  => array(
					'title'   => __( 'Signed-in customers without access', 'wp-easycart' ),
					'options' => array(
						'message' => __( 'Show what unlocks this page', 'wp-easycart' ),
						'product' => __( 'The page of the product that unlocks it', 'wp-easycart' ),
						'store'   => __( 'The store page', 'wp-easycart' ),
						'page'    => __( 'A page on this site', 'wp-easycart' ),
						'url'     => __( 'Another address', 'wp-easycart' ),
					),
				),
				'members'    => array(
					'title'   => __( 'Customers who can see it', 'wp-easycart' ),
					'options' => array(
						'stay' => __( 'Stay on this page', 'wp-easycart' ),
						'page' => __( 'Send them on to a page on this site', 'wp-easycart' ),
						'url'  => __( 'Send them on to another address', 'wp-easycart' ),
					),
				),
			);
			echo '<fieldset class="wpec-access-group" data-group="where"><legend>' . esc_html__( 'Where visitors go', 'wp-easycart' ) . '</legend>';
			foreach ( $choices as $which => $choice ) {
				$target = $rule[ $which ];
				if ( 'members' === $which && 'stay' === $target['to'] ) {
					continue; /* kept for pages set up before 6.0.5 ( "Logged In + Authorized" ); new rules don't offer it */
				}
				$id = $uid . '_' . $which;
				echo '<div class="wpec-access-dest" data-which="' . esc_attr( $which ) . '">';
				echo '<label class="wpec-access-label" for="' . esc_attr( $id ) . '_to">' . esc_html( $choice['title'] ) . '</label>';
				echo '<select id="' . esc_attr( $id ) . '_to" name="wpec_access[' . esc_attr( $which ) . '][to]" class="wpec-access-to">';
				foreach ( $choice['options'] as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '"' . selected( $target['to'], $value, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '<p class="wpec-access-dest-hint" data-for="message"' . ( 'message' === $target['to'] ? '' : ' hidden' ) . '>' . esc_html__( 'On your account page, with a Buy button for each product that unlocks it.', 'wp-easycart' ) . '</p>';
				echo '<p class="wpec-access-dest-hint" data-for="sign_in"' . ( 'sign_in' === $target['to'] ? '' : ' hidden' ) . '>' . esc_html__( 'Your store\'s sign-in page, with a line naming this page. New customers can make an account there too.', 'wp-easycart' ) . '</p>';

				$page_label = array();
				if ( (int) $target['page'] > 0 ) {
					$page_label[ (int) $target['page'] ] = wp_strip_all_tags( get_the_title( (int) $target['page'] ) );
				}
				echo '<div class="wpec-access-dest-page" data-for="page"' . ( 'page' === $target['to'] ? '' : ' hidden' ) . '>';
				echo '<input type="hidden" id="' . esc_attr( $id ) . '_page" name="wpec_access[' . esc_attr( $which ) . '][page]" value="' . esc_attr( (int) $target['page'] ) . '" class="wpec-access-page" />';
				self::print_page_picker( $id . '_pick', $id . '_page', $page_label, (int) $post_id );
				echo '</div>';
				echo '<div class="wpec-access-dest-url" data-for="url"' . ( 'url' === $target['to'] ? '' : ' hidden' ) . '>';
				echo '<input type="text" inputmode="url" id="' . esc_attr( $id ) . '_url" name="wpec_access[' . esc_attr( $which ) . '][url]" value="' . esc_attr( $target['url'] ) . '" class="wpec-access-url widefat" placeholder="' . esc_attr__( 'https://… or /join', 'wp-easycart' ) . '" aria-label="' . esc_attr( $choice['title'] ) . '" />';
				echo '</div>';
				echo '<p class="wpec-access-check-line" role="status" hidden></p>';
				echo '</div>';
			}
			echo '<label class="wpec-access-check wpec-access-external"><input type="checkbox" name="wpec_access[external_ok]" value="1" class="wpec-access-external-ok"' . checked( ! empty( $rule['external_ok'] ), true, false ) . ' /> <span>' . esc_html__( 'Send visitors to another site', 'wp-easycart' ) . '</span></label>';
			echo '</fieldset>';
			echo '</div>'; /* .wpec-access-body */
			echo '</div>'; /* .wpec-access-fields */
		}

		/**
		 * The status card under the switch ( 6.0.5 design ): a title, then who can open the page and where everyone else goes, one line
		 * each, and the saved rule's problems. page-access-v2.js draws it again from the fields as they change ( the same words ).
		 *
		 * @param int   $post_id  Post ( 0 = none chosen yet ).
		 * @param array $rule     shown_rule().
		 * @param array $problems wp_easycart_page_access::problems().
		 */
		private static function print_status( $post_id, $rule, $problems ) {
			$level = 'ok';
			foreach ( $problems as $problem ) {
				$level = ( 'bad' === $problem['level'] ) ? 'bad' : ( 'ok' === $level ? 'warn' : $level );
			}
			$text  = self::script_text();
			$lines = array();
			if ( empty( $rule['on'] ) ) {
				$tone  = 'off';
				$title = $text['everyone'];
			} elseif ( ! wp_easycart_page_access::restricted( $rule ) ) {
				$tone    = 'warn';
				$title   = $text['chooseWho'];
				$lines[] = array( '', $text['nobody'] );
			} else {
				$tone    = $level;
				$title   = $text['membersOnly'];
				$lines[] = array( $text['rowWho'], wp_easycart_page_access::who_text( $rule ) );
				$lines[] = array( $text['rowSignedOut'], wp_easycart_page_access::where_text( $rule, 'signed_out' ) );
				$lines[] = array( $text['rowNoAccess'], wp_easycart_page_access::where_text( $rule, 'no_access' ) );
				if ( 'stay' !== $rule['members']['to'] ) {
					$lines[] = array( $text['rowMembers'], wp_easycart_page_access::where_text( $rule, 'members' ) );
				}
			}
			echo '<div class="wpec-access-summary is-' . esc_attr( $tone ) . '" role="status" aria-live="polite">';
			echo '<p class="wpec-access-summary-title">' . esc_html( $title ) . '</p>';
			echo '<dl class="wpec-access-summary-lines">';
			foreach ( $lines as $line ) {
				echo '<div>' . ( '' !== $line[0] ? '<dt>' . esc_html( $line[0] ) . '</dt>' : '' ) . '<dd>' . esc_html( $line[1] ) . '</dd></div>';
			}
			echo '</dl>';
			if ( ! empty( $problems ) && ! empty( $rule['on'] ) ) {
				echo '<ul class="wpec-access-problems">';
				foreach ( $problems as $problem ) {
					echo '<li class="is-' . esc_attr( $problem['level'] ) . '">' . esc_html( $problem['text'] ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}

		/**
		 * A single page picker ( the store's search-as-you-type picker, mode page: ecv2_page_access_search ).
		 *
		 * @param string $id       Picker id.
		 * @param string $target   Hidden input that receives the page id.
		 * @param array  $selected id => title.
		 * @param int    $exclude  The limited post itself.
		 */
		private static function print_page_picker( $id, $target, $selected, $exclude ) {
			wp_easycart_admin::print_picker(
				array(
					'id'          => $id,
					'mode'        => 'page',
					'target'      => $target,
					'multiple'    => false,
					'selected'    => $selected,
					'placeholder' => __( 'Search pages', 'wp-easycart' ),
					'on_change'   => 'wpecPageAccessChanged',
					'class'       => 'wpec-access-page-search',
					'exclude'     => $exclude,
				)
			);
		}

		/**
		 * The rule posted by the box or the drawer ( wpec_access[…] ), as clean() takes it.
		 *
		 * @param array $raw $_POST['wpec_access'], unslashed.
		 * @return array
		 */
		public static function posted_rule( $raw ) {
			$raw  = is_array( $raw ) ? $raw : array();
			$rule = array(
				'on'          => ! empty( $raw['on'] ),
				'match'       => ( isset( $raw['match'] ) && 'all' === $raw['match'] ) ? 'all' : 'any',
				'signed_in'   => ! empty( $raw['signed_in'] ),
				'products'    => isset( $raw['products'] ) ? (array) $raw['products'] : array(),
				'roles'       => isset( $raw['roles'] ) ? (array) $raw['roles'] : array(),
				'users'       => isset( $raw['users'] ) ? (array) $raw['users'] : array(),
				'external_ok' => ! empty( $raw['external_ok'] ),
			);
			foreach ( array( 'signed_out', 'no_access', 'members' ) as $which ) {
				if ( isset( $raw[ $which ] ) && is_array( $raw[ $which ] ) ) {
					$rule[ $which ] = array(
						'to'   => isset( $raw[ $which ]['to'] ) ? sanitize_key( $raw[ $which ]['to'] ) : '',
						'page' => isset( $raw[ $which ]['page'] ) ? absint( $raw[ $which ]['page'] ) : 0,
						'url'  => isset( $raw[ $which ]['url'] ) ? sanitize_text_field( (string) $raw[ $which ]['url'] ) : '',
					);
				}
			}
			return $rule;
		}

		/**
		 * Save from the editor box ( save_post, through wp_easycart_admin::save_page_lock_meta(), which checked the nonce and rights ).
		 *
		 * @param int $post_id Post.
		 * @return bool Whether the new box was posted.
		 */
		public static function save_box( $post_id ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by wp_easycart_admin::save_page_lock_meta() before this runs.
			if ( ! isset( $_POST['wpec_access'] ) || ! is_array( $_POST['wpec_access'] ) || empty( $_POST['wpec_access']['present'] ) ) {
				return false;
			}
			$raw = map_deep( wp_unslash( $_POST['wpec_access'] ), 'sanitize_text_field' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by map_deep().
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			$rule = self::posted_rule( $raw );
			if ( ! self::changed( $post_id, $rule ) ) {
				return true; /* nothing changed: a page never limited keeps no rule */
			}
			wp_easycart_page_access::save_rule( $post_id, $rule );
			return true;
		}

		/**
		 * Whether a posted rule differs from the saved one ( a post that never had a rule and is saved with the box untouched gets
		 * none ).
		 *
		 * @param int   $post_id Post.
		 * @param array $posted  Posted rule.
		 * @return bool
		 */
		private static function changed( $post_id, $posted ) {
			$has_rule = metadata_exists( 'post', $post_id, wp_easycart_page_access::META ) || wp_easycart_page_access::restricted( wp_easycart_page_access::legacy( $post_id ) );
			if ( $has_rule ) {
				return true;
			}
			$clean = wp_easycart_page_access::clean( $posted );
			return ! empty( $clean['on'] ) || wp_easycart_page_access::restricted( $clean );
		}

		/* ------------------------------------------------------------------ AJAX */

		/**
		 * Page search for the pickers: published posts of the types that can be limited.
		 */
		public static function ajax_search() {
			ecv2_page_access_guard();
			$q       = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
			$exclude = isset( $_POST['exclude'] ) ? absint( $_POST['exclude'] ) : 0;
			$any     = ! empty( $_POST['any'] ); /* "Limit a page": every status but trash */
			$query   = new WP_Query(
				array(
					'post_type'              => wp_easycart_page_access::post_types(),
					'post_status'            => $any ? array( 'publish', 'private', 'draft', 'pending', 'future' ) : array( 'publish' ),
					's'                      => $q,
					'posts_per_page'         => 20,
					'orderby'                => '' === $q ? 'title' : 'relevance',
					'order'                  => 'ASC',
					'post__not_in'           => $exclude ? array( $exclude ) : array(),
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			);
			$items   = array();
			foreach ( $query->posts as $post ) {
				$type    = get_post_type_object( $post->post_type );
				$limited = wp_easycart_page_access::active( $post->ID );
				$items[] = array(
					'id'      => (int) $post->ID,
					'label'   => '' !== $post->post_title ? wp_strip_all_tags( $post->post_title ) : sprintf( '#%d', $post->ID ),
					'meta'    => ( $type ? $type->labels->singular_name : $post->post_type ) . ( 'publish' !== $post->post_status ? ' · ' . $post->post_status : '' ) . ( $limited ? ' · ' . __( 'limited', 'wp-easycart' ) : '' ),
					'limited' => $limited,
					'url'     => (string) get_permalink( $post->ID ),
				);
			}
			wp_send_json( array( 'items' => $items ) );
		}

		/**
		 * The drawer's fields for a post.
		 */
		public static function ajax_form() {
			ecv2_page_access_guard();
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$post    = $post_id ? get_post( $post_id ) : null;
			if ( ! $post || ! in_array( $post->post_type, wp_easycart_page_access::post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'That page could not be opened.', 'wp-easycart' ) ) );
			}
			ob_start();
			self::print_fields( $post_id );
			wp_send_json_success(
				array(
					'html'  => ob_get_clean(),
					'title' => wp_strip_all_tags( get_the_title( $post_id ) ),
					'view'  => (string) get_permalink( $post_id ),
					'edit'  => (string) get_edit_post_link( $post_id, 'raw' ),
				)
			);
		}

		/**
		 * Save from the drawer.
		 */
		public static function ajax_save() {
			ecv2_page_access_guard();
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$post    = $post_id ? get_post( $post_id ) : null;
			if ( ! $post || ! in_array( $post->post_type, wp_easycart_page_access::post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'That page could not be saved.', 'wp-easycart' ) ) );
			}
			$raw  = isset( $_POST['wpec_access'] ) && is_array( $_POST['wpec_access'] ) ? map_deep( wp_unslash( $_POST['wpec_access'] ), 'sanitize_text_field' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by map_deep().
			$rule = wp_easycart_page_access::save_rule( $post_id, self::posted_rule( $raw ) );
			wp_send_json_success(
				array(
					'status'   => wp_easycart_page_access::status( $post_id, $rule ),
					'problems' => wp_easycart_page_access::problems( $post_id, $rule ),
				)
			);
		}

		/**
		 * Turn the limits of several posts on or off.
		 */
		public static function ajax_toggle() {
			ecv2_page_access_guard();
			$on    = ! empty( $_POST['on'] );
			$ids   = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint on each id.
			$count = 0;
			foreach ( array_unique( array_filter( $ids ) ) as $post_id ) {
				if ( ! current_user_can( 'edit_post', $post_id ) || ! in_array( get_post_type( $post_id ), wp_easycart_page_access::post_types(), true ) ) {
					continue;
				}
				$rule = wp_easycart_page_access::rule( $post_id );
				if ( $on && ! empty( $rule['legacy'] ) && empty( $rule['on'] ) ) {
					/* restrictions with no address ( 6.0.4 left them open ): the new defaults */
					$rule['signed_out'] = array(
						'to'   => 'sign_in',
						'page' => 0,
						'url'  => '',
					);
					$rule['no_access']  = array(
						'to'   => 'message',
						'page' => 0,
						'url'  => '',
					);
				}
				$rule['on'] = $on;
				wp_easycart_page_access::save_rule( $post_id, $rule );
				++$count;
			}
			wp_send_json_success( array( 'count' => $count ) );
		}

		/* ------------------------------------------------------------------ Users › Limit Access */

		/**
		 * The screen.
		 */
		public static function print_screen() {
			if ( ! self::can_manage() ) {
				echo '<div class="ecv2-wrap"><p>' . esc_html__( 'You do not have permission to manage Limit Access.', 'wp-easycart' ) . '</p></div>';
				return;
			}
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters ( search, status, page ); nothing is changed.
			$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
			$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$rows   = array();
			$counts = array(
				'all'     => 0,
				'on'      => 0,
				'off'     => 0,
				'problem' => 0,
			);
			$issues = array();
			foreach ( wp_easycart_page_access::limited_post_ids( $search ) as $post_id ) {
				$rule     = wp_easycart_page_access::rule( $post_id );
				$problems = wp_easycart_page_access::problems( $post_id, $rule );
				$state    = wp_easycart_page_access::status( $post_id, $rule, $problems );
				++$counts['all'];
				++$counts[ $state ];
				if ( 'problem' === $state ) {
					foreach ( $problems as $problem ) {
						if ( 'bad' === $problem['level'] ) {
							$issues[] = array(
								'post' => $post_id,
								'text' => $problem['text'],
							);
						}
					}
				}
				if ( 'all' === $status || $status === $state ) {
					$rows[] = array(
						'post'     => $post_id,
						'rule'     => $rule,
						'problems' => $problems,
						'state'    => $state,
					);
				}
			}
			$total = count( $rows );
			$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
			$paged = min( $paged, $pages );
			$rows  = array_slice( $rows, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );

			echo '<div class="ecv2-wrap wpec-access-screen">';
			echo '<div class="ecv2-page-header"><div class="ecv2-page-header-left"><span class="dashicons dashicons-lock ecv2-page-header-icon" aria-hidden="true"></span><div class="ecv2-page-header-text">';
			echo '<h1 class="ecv2-page-title">' . esc_html__( 'Limit Access', 'wp-easycart' ) . '</h1>';
			/* translators: %d: number of pages with a Limit Access rule */
			echo '<p class="ecv2-page-subline">' . esc_html( sprintf( _n( '%d page with a rule', '%d pages with a rule', $counts['all'], 'wp-easycart' ), $counts['all'] ) ) . ' &middot; ' . esc_html__( 'Who can see each page, and where everyone else goes', 'wp-easycart' ) . '</p>';
			echo '</div></div><div class="ecv2-page-header-right">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-primary" data-wpec-access-new><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <span class="ecv2-btn-label">' . esc_html__( 'Limit a page', 'wp-easycart' ) . '</span></button>';
			echo '</div></div>';

			if ( wp_easycart_page_access::paused() ) {
				$settings = admin_url( 'admin.php?page=wp-easycart-settings&subpage=account#limit-access' );
				echo '<div class="wpec-access-banner is-warn" role="status"><strong>' . esc_html__( 'Every limit is paused.', 'wp-easycart' ) . '</strong> ' . esc_html__( 'Everyone can open these pages until you switch the pause off.', 'wp-easycart' ) . ' <a href="' . esc_url( $settings ) . '">' . esc_html__( 'Settings › Accounts', 'wp-easycart' ) . '</a></div>';
			}
			if ( ! empty( $issues ) ) {
				echo '<div class="wpec-access-banner is-bad" role="status"><strong>' . esc_html( sprintf( /* translators: %d: number of problems */ _n( '%d problem to fix', '%d problems to fix', count( $issues ), 'wp-easycart' ), count( $issues ) ) ) . '</strong><ul>';
				foreach ( array_slice( $issues, 0, 8 ) as $issue ) {
					echo '<li><button type="button" class="wpec-access-link" data-wpec-access-edit="' . esc_attr( $issue['post'] ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $issue['post'] ) ) ) . '</button>: ' . esc_html( $issue['text'] ) . '</li>';
				}
				echo '</ul><p class="wpec-access-banner-note">' . esc_html__( 'Until then, visitors who would be sent there get your sign-in page or the members-only view instead.', 'wp-easycart' ) . '</p></div>';
			}

			echo '<div class="ecv2-toolbar"><div class="ecv2-toolbar-left"><nav class="wpec-access-tabs" aria-label="' . esc_attr__( 'Filter by status', 'wp-easycart' ) . '">';
			$tabs = array(
				'all'     => __( 'All', 'wp-easycart' ),
				'on'      => __( 'On', 'wp-easycart' ),
				'off'     => __( 'Off', 'wp-easycart' ),
				'problem' => __( 'Problems', 'wp-easycart' ),
			);
			foreach ( $tabs as $key => $label ) {
				$args = array( 'status' => $key );
				if ( '' !== $search ) {
					$args['s'] = $search;
				}
				echo '<a class="wpec-access-tab' . ( $key === $status ? ' is-current' : '' ) . '" href="' . esc_url( self::screen_url( $args ) ) . '"' . ( $key === $status ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="wpec-access-count">' . (int) $counts[ $key ] . '</span></a>';
			}
			echo '</nav>';
			echo '<span class="wpec-access-bulk" hidden><span class="wpec-access-bulk-count"></span> <button type="button" class="ecv2-btn ecv2-btn-sm" data-wpec-access-bulk="1">' . esc_html__( 'Turn on', 'wp-easycart' ) . '</button> <button type="button" class="ecv2-btn ecv2-btn-sm" data-wpec-access-bulk="0">' . esc_html__( 'Turn off', 'wp-easycart' ) . '</button></span>';
			echo '</div><div class="ecv2-toolbar-right">';
			echo '<form method="get" class="wpec-access-search" role="search"><input type="hidden" name="page" value="wp-easycart-users" /><input type="hidden" name="subpage" value="' . esc_attr( self::SUBPAGE ) . '" /><input type="hidden" name="status" value="' . esc_attr( $status ) . '" />';
			echo '<label class="screen-reader-text" for="wpec-access-s">' . esc_html__( 'Search limited pages', 'wp-easycart' ) . '</label><input type="search" id="wpec-access-s" name="s" class="ecv2-input" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search limited pages', 'wp-easycart' ) . '" /> <button type="submit" class="ecv2-btn ecv2-btn-sm">' . esc_html__( 'Search', 'wp-easycart' ) . '</button></form>';
			echo '</div></div>';

			if ( empty( $rows ) ) {
				echo '<div class="ecv2-empty-state wpec-access-empty">';
				if ( 0 === $counts['all'] && '' === $search ) {
					echo '<h2>' . esc_html__( 'No limited pages yet', 'wp-easycart' ) . '</h2><p>' . esc_html__( 'Keep a page, post or product page for members: customers who bought a product or subscription, customers with a role, or any signed-in customer.', 'wp-easycart' ) . '</p>';
					echo '<p><button type="button" class="ecv2-btn ecv2-btn-primary" data-wpec-access-new>' . esc_html__( 'Limit a page', 'wp-easycart' ) . '</button></p>';
				} else {
					echo '<p>' . esc_html__( 'No pages match.', 'wp-easycart' ) . '</p>';
				}
				echo '</div>';
			} else {
				echo '<div class="ecv2-table-scroll"><table class="ecv2-table wpec-access-table"><thead><tr>';
				echo '<th class="ecv2-col-check" scope="col"><input type="checkbox" class="wpec-access-check-all" aria-label="' . esc_attr__( 'Choose every page', 'wp-easycart' ) . '" /></th>';
				echo '<th scope="col">' . esc_html__( 'Page', 'wp-easycart' ) . '</th><th scope="col">' . esc_html__( 'Who can see it', 'wp-easycart' ) . '</th><th scope="col">' . esc_html__( 'Signed-out visitors', 'wp-easycart' ) . '</th><th scope="col">' . esc_html__( 'Without access', 'wp-easycart' ) . '</th><th scope="col">' . esc_html__( 'Status', 'wp-easycart' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'wp-easycart' ) . '</span></th>';
				echo '</tr></thead><tbody>';
				foreach ( $rows as $row ) {
					self::print_row( $row );
				}
				echo '</tbody></table></div>';
				if ( $pages > 1 ) {
					echo '<nav class="wpec-access-pages" aria-label="' . esc_attr__( 'Pages', 'wp-easycart' ) . '">';
					for ( $i = 1; $i <= $pages; $i++ ) {
						$args = array(
							'status' => $status,
							'paged'  => $i,
						);
						if ( '' !== $search ) {
							$args['s'] = $search;
						}
						echo '<a class="ecv2-page-btn' . ( $i === $paged ? ' is-current' : '' ) . '" href="' . esc_url( self::screen_url( $args ) ) . '"' . ( $i === $paged ? ' aria-current="page"' : '' ) . '>' . (int) $i . '</a> ';
					}
					echo '</nav>';
				}
			}

			self::print_drawer();
			echo '</div>';
		}

		/**
		 * One row of the list.
		 *
		 * @param array $row post, rule, problems, state.
		 */
		private static function print_row( $row ) {
			$post_id = (int) $row['post'];
			$post    = get_post( $post_id );
			if ( ! $post ) {
				return;
			}
			$rule   = self::shown_rule( $row['rule'] );
			$type   = get_post_type_object( $post->post_type );
			$states = array(
				'on'      => __( 'On', 'wp-easycart' ),
				'off'     => __( 'Off', 'wp-easycart' ),
				'problem' => __( 'Problem', 'wp-easycart' ),
			);
			$title  = '' !== $post->post_title ? wp_strip_all_tags( $post->post_title ) : sprintf( '#%d', $post_id );
			echo '<tr data-post="' . esc_attr( $post_id ) . '" class="is-' . esc_attr( $row['state'] ) . '">';
			echo '<td class="ecv2-col-check"><input type="checkbox" class="wpec-access-row-check" value="' . esc_attr( $post_id ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: page title */ __( 'Choose %s', 'wp-easycart' ), $title ) ) . '" /></td>';
			echo '<td class="wpec-access-page"><button type="button" class="wpec-access-link wpec-access-title" data-wpec-access-edit="' . esc_attr( $post_id ) . '">' . esc_html( $title ) . '</button>';
			echo '<span class="wpec-access-type">' . esc_html( $type ? $type->labels->singular_name : $post->post_type ) . ( 'publish' !== $post->post_status ? ' · ' . esc_html( $post->post_status ) : '' ) . ( ! empty( $rule['legacy'] ) ? ' · ' . esc_html__( 'set up before 6.0.5', 'wp-easycart' ) : '' ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Who can see it', 'wp-easycart' ) . '">' . esc_html( wp_easycart_page_access::who_text( $rule ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Signed-out visitors', 'wp-easycart' ) . '">' . esc_html( wp_easycart_page_access::where_text( $rule, 'signed_out' ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Without access', 'wp-easycart' ) . '">' . esc_html( wp_easycart_page_access::where_text( $rule, 'no_access' ) ) . '</td>';
			echo '<td class="wpec-access-state"><span class="wpec-access-status is-' . esc_attr( $row['state'] ) . '">' . esc_html( $states[ $row['state'] ] ) . '</span>';
			foreach ( $row['problems'] as $problem ) {
				if ( 'bad' === $problem['level'] || 'off' === $problem['level'] ) {
					echo '<span class="wpec-access-row-note">' . esc_html( $problem['text'] ) . '</span>';
					break;
				}
			}
			echo '</td>';
			echo '<td class="wpec-access-actions">';
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm" data-wpec-access-edit="' . esc_attr( $post_id ) . '">' . esc_html__( 'Edit', 'wp-easycart' ) . '</button> ';
			echo '<button type="button" class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" data-wpec-access-toggle="' . esc_attr( $post_id ) . '" data-on="' . ( 'off' === $row['state'] ? '1' : '0' ) . '">' . esc_html( 'off' === $row['state'] ? __( 'Turn on', 'wp-easycart' ) : __( 'Turn off', 'wp-easycart' ) ) . '</button> ';
			echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-ghost" href="' . esc_url( (string) get_permalink( $post_id ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'wp-easycart' ) . '<span class="screen-reader-text"> ' . esc_html__( '( opens in a new tab )', 'wp-easycart' ) . '</span></a>';
			echo '</td></tr>';
		}

		/**
		 * A rule as the fields and the list show it: a page set up before 6.0.5 with restrictions but no address ( off ) shows the
		 * destinations it gets when turned on ( sign in, what unlocks it ); a page with nothing restricted shows the 6.0.5 defaults.
		 *
		 * @param array $rule Rule.
		 * @return array
		 */
		public static function shown_rule( $rule ) {
			if ( empty( $rule['legacy'] ) ) {
				return $rule;
			}
			if ( ! wp_easycart_page_access::restricted( $rule ) ) {
				return wp_easycart_page_access::defaults();
			}
			if ( empty( $rule['on'] ) ) {
				$rule['signed_out'] = array(
					'to'   => 'sign_in',
					'page' => 0,
					'url'  => '',
				);
				$rule['no_access']  = array(
					'to'   => 'message',
					'page' => 0,
					'url'  => '',
				);
				$rule['members']    = array(
					'to'   => 'stay',
					'page' => 0,
					'url'  => '',
				);
			} else {
				$rule['external_ok'] = true; /* its addresses were allowed on another site */
			}
			return $rule;
		}

		/**
		 * The edit drawer ( filled through ecv2_page_access_form ).
		 */
		private static function print_drawer() {
			echo '<div class="wpec-access-drawer" data-ecv2-layer hidden>';
			echo '<div class="wpec-access-drawer-backdrop" data-wpec-access-close></div>';
			echo '<section class="wpec-access-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="wpec-access-drawer-title">';
			echo '<header class="wpec-access-drawer-head"><h2 id="wpec-access-drawer-title">' . esc_html__( 'Limit a page', 'wp-easycart' ) . '</h2><button type="button" class="wpec-access-drawer-close" data-wpec-access-close aria-label="' . esc_attr__( 'Close', 'wp-easycart' ) . '">&times;</button></header>';
			echo '<div class="wpec-access-drawer-body">';
			echo '<div class="wpec-access-choose"><span class="wpec-access-label" id="wpec-access-choose-label">' . esc_html__( 'Page to limit', 'wp-easycart' ) . '</span>';
			echo '<input type="hidden" id="wpec_access_choose_post" value="0" />';
			wp_easycart_admin::print_picker(
				array(
					'id'          => 'wpec_access_choose',
					'mode'        => 'page',
					'target'      => 'wpec_access_choose_post',
					'multiple'    => false,
					'selected'    => array(),
					'placeholder' => __( 'Search pages, posts and product pages', 'wp-easycart' ),
					'on_change'   => 'wpecPageAccessChosen',
					'any'         => true,
				)
			);
			echo '<p class="wpec-access-hint">' . esc_html__( 'Any page works, whichever builder made it.', 'wp-easycart' ) . '</p></div>';
			echo '<form class="wpec-access-drawer-form" novalidate><div class="wpec-access wpec-access-drawer-fields"></div></form>';
			echo '</div>';
			echo '<footer class="wpec-access-drawer-foot"><span class="wpec-access-drawer-msg" role="status" aria-live="polite"></span><a class="ecv2-btn ecv2-btn-ghost wpec-access-drawer-view" href="#" target="_blank" rel="noopener" hidden>' . esc_html__( 'View page', 'wp-easycart' ) . '</a><button type="button" class="ecv2-btn" data-wpec-access-close>' . esc_html__( 'Cancel', 'wp-easycart' ) . '</button><button type="button" class="ecv2-btn ecv2-btn-primary wpec-access-drawer-save" disabled>' . esc_html__( 'Save', 'wp-easycart' ) . '</button></footer>';
			echo '</section></div>';
		}

		/* ------------------------------------------------------------------ The Access column */

		/**
		 * An Access column on the post lists of every type that can be limited.
		 */
		public static function list_columns() {
			if ( ! self::can_manage() ) {
				return;
			}
			foreach ( wp_easycart_page_access::post_types() as $type ) {
				add_filter( 'manage_' . $type . '_posts_columns', array( __CLASS__, 'add_column' ) );
				add_action( 'manage_' . $type . '_posts_custom_column', array( __CLASS__, 'print_column' ), 10, 2 );
			}
			add_filter( 'default_hidden_columns', array( __CLASS__, 'hide_column' ), 10, 2 );
		}

		/**
		 * Add the column after the title.
		 *
		 * @param array $columns Columns.
		 * @return array
		 */
		public static function add_column( $columns ) {
			$out = array();
			foreach ( $columns as $key => $label ) {
				$out[ $key ] = $label;
				if ( 'title' === $key ) {
					$out['wpec_access'] = __( 'Access', 'wp-easycart' );
				}
			}
			if ( ! isset( $out['wpec_access'] ) ) {
				$out['wpec_access'] = __( 'Access', 'wp-easycart' );
			}
			return $out;
		}

		/**
		 * The column starts hidden on stores with no limited pages ( Screen Options shows it ).
		 *
		 * @param array     $hidden Hidden columns.
		 * @param WP_Screen $screen Screen.
		 * @return array
		 */
		public static function hide_column( $hidden, $screen ) {
			if ( is_object( $screen ) && 'edit' === $screen->base && in_array( $screen->post_type, wp_easycart_page_access::post_types(), true ) && ! wp_easycart_page_access::limited_post_ids() ) {
				$hidden[] = 'wpec_access';
			}
			return $hidden;
		}

		/**
		 * One cell.
		 *
		 * @param string $column  Column.
		 * @param int    $post_id Post.
		 */
		public static function print_column( $column, $post_id ) {
			if ( 'wpec_access' !== $column ) {
				return;
			}
			$rule = wp_easycart_page_access::rule( $post_id );
			if ( ! empty( $rule['legacy'] ) && ! wp_easycart_page_access::restricted( $rule ) ) {
				echo '<span class="wpec-access-col-open">&mdash;<span class="screen-reader-text"> ' . esc_html__( 'Everyone', 'wp-easycart' ) . '</span></span>';
				return;
			}
			$state  = wp_easycart_page_access::status( $post_id, $rule );
			$labels = array(
				'on'      => __( 'Members only', 'wp-easycart' ),
				'off'     => __( 'Limit off', 'wp-easycart' ),
				'problem' => __( 'Problem', 'wp-easycart' ),
			);
			$styles = array(
				'on'      => 'background:#e1f2e6;color:#1e5c33',
				'off'     => 'background:#f0f0f1;color:#50575e',
				'problem' => 'background:#fcf0e3;color:#8a4b00',
			);
			echo '<a href="' . esc_url( self::screen_url( array( 'status' => $state ) ) ) . '" style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;font-weight:600;text-decoration:none;' . esc_attr( $styles[ $state ] ) . '">' . esc_html( $labels[ $state ] ) . '</a>';
			if ( 'on' === $state ) {
				echo '<br /><span style="color:#646970;font-size:12px">' . esc_html( wp_easycart_page_access::who_text( $rule ) ) . '</span>';
			}
		}
	}

	wp_easycart_admin_page_access::init();

endif;
