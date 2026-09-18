<?php
/**
 * WP EasyCart Admin — Settings registry (V2 settings pages).
 *
 * Every converted settings page is a declaration file in admin/template/settings/<slug>.php
 * that returns an array:
 *
 *   array(
 *     'slug'        => 'account',
 *     'title'       => __( 'Accounts', 'wp-easycart' ),
 *     'description' => __( 'Registration, guest checkout and the account page.', 'wp-easycart' ),
 *     'group'       => 'store-setup',            // see groups()
 *     'icon'        => 'admin-users',            // dashicon name for the home page card
 *     'docs'        => array( 'settings', 'account-settings', 'account' ), // helpsystem->print_docs_url() args
 *     'legacy'      => array( 'account' ),       // old subpage slugs that now land here
 *     'upsell'      => 'default',                // wp_easycart_admin_upsell context for locked rows
 *     'sections'    => array(
 *       'registration' => array(
 *         'title'  => __( 'Registration', 'wp-easycart' ),
 *         'hint'   => __( 'Who can create an account and what they must provide.', 'wp-easycart' ),
 *         'pro'    => false,                     // true locks the whole section
 *         'fields' => array(
 *           'ec_option_require_account_terms' => array(
 *             'type'      => 'toggle',           // toggle | text | number | select | pills | multiselect | pairs | color | textarea | url | email | password | html
 *                                                // multiselect: stored as chosen values joined by 'separator' ( default ',' ); shown as
 *                                                // checkbox pills, or as a searchable chip picker once the list is long ( see 'display' )
 *             'label'     => __( 'Require terms agreement', 'wp-easycart' ),
 *             'desc'      => __( 'Shoppers must accept your terms to register.', 'wp-easycart' ),
 *             'default'   => 0,
 *             'advanced'  => false,              // folded behind "Show advanced"
 *             'pro'       => false,              // true | 'premium' locks the row
 *             'parent'    => '',                 // key of the toggle this row depends on
 *             'show_when' => '1',                // parent value that reveals this row
 *             'options'   => array(),            // select / pills / multiselect: value => label, OR a callable( $field ) returning
 *                                                // that array ( a Closure or a function name ). A callable is resolved only when
 *                                                // needed: for the page being rendered, for the field being saved, and for the
 *                                                // chosen values when a search result is described. Settings search never runs it,
 *                                                // and neither does the migration-map generator. Put every database or WP_Query
 *                                                // read behind a callable; keep literal arrays for fixed lists.
 *             'search_callback' => null,         // callable( $term, $field, $limit ) → value => label ( or rows with value / label / hint ).
 *                                                // Declaring it makes the row a search-as-you-type picker: nothing but the chosen
 *                                                // values is printed and ecv2_settings_option_search asks this for matches ( ≤ 50 ).
 *                                                // Without it a long list is filtered with a LIKE-style match on the resolved 'options'.
 *             'validate_callback' => null,       // callable( array $values, $field ) → value => label ( or rows ) for the values that
 *                                                // exist ( e.g. WHERE id IN ( … ) ). Used on save instead of the full option list, and to
 *                                                // label the chosen chips. Optional: without it the resolved 'options' are used.
 *             'display'   => 'auto',             // multiselect: auto ( pills up to PILLS_THRESHOLD, a chip picker above it, a remote
 *                                                // search picker above PICKER_THRESHOLD or with 'search_callback' ) | pills | picker | remote
 *             'option_hints' => array(),         // multiselect picker: value => short secondary text ( e.g. parent category ), or a callable( $field )
 *             'current'   => null,               // callable( $field ) → the live value when the setting does not live in an option
 *                                                // ( e.g. a table AUTO_INCREMENT ). Read at render and save time, never during search.
 *             'exclusive' => array( '0' ),       // multiselect: choices that clear the others when picked, and return when nothing else is left
 *             'suggestions' => array(),          // text: value => label offered as a datalist ( free typing still allowed )
 *             'pair'      => array(              // pairs: one row per entry, stored as key<join>value entries joined by 'separator'
 *               'key'   => array( 'label' => 'Currency', 'placeholder' => 'EUR', 'maxlength' => 3, 'upper' => true ),
 *               'value' => array( 'label' => 'Rate', 'placeholder' => '0.92', 'inputmode' => 'decimal' ),
 *               'join'  => '=', 'add' => __( 'Add currency', 'wp-easycart' ),
 *             ),
 *             'unit'      => '', 'prefix' => '', 'placeholder' => '', 'min' => null, 'max' => null, 'step' => null,
 *             'sanitize'  => null,               // callable( $value, $field ) → value, replaces the type default
 *             'validate'  => null,               // callable( $value, $field ) → '' | warning string ( non-blocking )
 *             'on_save'   => null,               // callable( $value, $old, $field ) for legacy side effects
 *             'keywords'  => array(),            // extra search terms
 *             'legacy'    => array( 'page' => 'account', 'section' => 'Account Options', 'label' => 'Require Terms' ),
 *           ),
 *         ),
 *         'actions' => array(                    // store-wide / destructive buttons, rendered after the fields
 *           array( 'id' => 'reset', 'label' => __( 'Reset colors', 'wp-easycart' ), 'desc' => '', 'button' => __( 'Reset', 'wp-easycart' ), 'confirm' => __( 'Really?', 'wp-easycart' ), 'danger' => true, 'callback' => 'some_function' ),
 *         ),
 *         'render' => null,                      // callable printing custom HTML inside the card ( legacy embeds )
 *       ),
 *     ),
 *   );
 *
 * PRO extends or unlocks a page through apply_filters( 'wp_easycart_settings_page_<slug>', $page ).
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_settings_registry' ) ) :

class wp_easycart_admin_settings_registry {

	const NONCE = 'wp-easycart-ecv2-settings';

	/** Multiselects with more options than this render as a searchable chip picker instead of pills. */
	const PILLS_THRESHOLD = 12;

	/**
	 * Pickers with more options than this never print their list: only the chosen chips are
	 * rendered and matches are fetched through ecv2_settings_option_search as the user types.
	 *
	 * @since 6.0.0
	 */
	const PICKER_THRESHOLD = 200;

	/** Most rows an option search returns. */
	const SEARCH_LIMIT = 50;

	private static $pages = null;

	/** Resolved option lists, keyed by page|field, so render and save in one request run each query once. */
	private static $option_cache = array();

	/** Where the declaration files live. */
	public static function dir() {
		return EC_PLUGIN_DIRECTORY . '/admin/template/settings/';
	}

	/** Sidebar groups, in display order. */
	public static function groups() {
		return array(
			'store-setup'  => array( 'label' => __( 'Store setup', 'wp-easycart' ), 'hint' => __( 'What you sell and how shoppers buy it', 'wp-easycart' ) ),
			'financial'    => array( 'label' => __( 'Financial', 'wp-easycart' ), 'hint' => __( 'Getting paid, taxes and shipping costs', 'wp-easycart' ) ),
			'customize'    => array( 'label' => __( 'Customize', 'wp-easycart' ), 'hint' => __( 'Look, wording, emails and regional lists', 'wp-easycart' ) ),
			'integrations' => array( 'label' => __( 'Integrations', 'wp-easycart' ), 'hint' => __( 'Other services your store talks to', 'wp-easycart' ) ),
			'troubleshoot' => array( 'label' => __( 'Troubleshoot', 'wp-easycart' ), 'hint' => __( 'Logs and diagnostics', 'wp-easycart' ) ),
		);
	}

	/** All declared ( converted ) pages, keyed by slug, normalised and filtered. */
	public static function pages() {
		if ( null !== self::$pages ) {
			return self::$pages;
		}
		self::$pages = array();
		$files = glob( self::dir() . '*.php' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				/* Declarations only: a stray template or class file in this folder would be included on every settings request
				   ( 6.0.0: the old setup-actions.php class fataled when the Settings screen included it a second time ). */
				if ( false === strpos( (string) file_get_contents( $file, false, null, 0, 512 ), 'V2 declaration' ) ) {
					continue;
				}
				$decl = include $file;
				if ( ! is_array( $decl ) || empty( $decl['slug'] ) ) {
					continue;
				}
				$slug = sanitize_key( $decl['slug'] );
				$decl = apply_filters( 'wp_easycart_settings_page_' . $slug, $decl );
				self::$pages[ $slug ] = self::normalize_page( $decl );
			}
		}
		self::$pages = apply_filters( 'wp_easycart_settings_pages', self::$pages );
		return self::$pages;
	}

	public static function has( $slug ) {
		$pages = self::pages();
		return isset( $pages[ sanitize_key( $slug ) ] );
	}

	public static function page( $slug ) {
		$pages = self::pages();
		$slug = sanitize_key( $slug );
		return isset( $pages[ $slug ] ) ? $pages[ $slug ] : false;
	}

	/** Slug of the converted page that owns a legacy subpage slug, or ''. */
	public static function page_for_legacy( $legacy_slug ) {
		foreach ( self::pages() as $slug => $page ) {
			if ( in_array( $legacy_slug, $page['legacy'], true ) ) {
				return $slug;
			}
		}
		return '';
	}

	public static function field( $slug, $key ) {
		$page = self::page( $slug );
		if ( ! $page ) {
			return false;
		}
		foreach ( $page['sections'] as $section ) {
			if ( isset( $section['fields'][ $key ] ) ) {
				return $section['fields'][ $key ];
			}
		}
		return false;
	}

	public static function page_url( $slug, $field_key = '' ) {
		$url = admin_url( 'admin.php?page=wp-easycart-settings&subpage=' . rawurlencode( $slug ) );
		if ( '' !== $field_key ) {
			$url .= '&highlight=' . rawurlencode( $field_key ) . '#ecst-' . rawurlencode( $field_key );
		}
		return $url;
	}

	/**
	 * Current stored value of a field ( option ), falling back to its declared default.
	 * A field with a 'current' callable ( the value lives in a table, not an option ) reads
	 * it live unless $live is false ( settings search, which must stay query-free ).
	 */
	public static function value( $field, $live = true ) {
		if ( $live && isset( $field['current'] ) && self::is_lazy( $field['current'] ) ) {
			$now = call_user_func( $field['current'], $field );
			if ( null !== $now && false !== $now && '' !== $now ) {
				return $now;
			}
		}
		$value = get_option( $field['key'], null );
		if ( null === $value || false === $value ) {
			return $field['default'];
		}
		return $value;
	}

	/* ------------------------------------------------------------------ */
	/* Option lists ( lazy )                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Is this declaration value a deferred callable? Only Closures and the names of
	 * existing functions count, so an ordinary options array is never mistaken for one.
	 *
	 * @since 6.0.0
	 */
	public static function is_lazy( $thing ) {
		if ( $thing instanceof Closure ) {
			return true;
		}
		return is_string( $thing ) && '' !== $thing && function_exists( $thing );
	}

	/**
	 * The field's option list, running its callable if it has one. Cached for the request.
	 *
	 * @since 6.0.0
	 * @return array value => label
	 */
	public static function options( $field ) {
		$options = isset( $field['options'] ) ? $field['options'] : array();
		if ( ! self::is_lazy( $options ) ) {
			return is_array( $options ) ? $options : array();
		}
		$cache_key = ( isset( $field['page'] ) ? $field['page'] : '' ) . '|' . $field['key'];
		if ( ! isset( self::$option_cache[ $cache_key ] ) ) {
			$resolved = call_user_func( $options, $field );
			self::$option_cache[ $cache_key ] = is_array( $resolved ) ? $resolved : array();
		}
		return self::$option_cache[ $cache_key ];
	}

	/**
	 * Secondary text per option value for the chip picker, running its callable if it has one.
	 *
	 * @since 6.0.0
	 * @return array value => hint
	 */
	public static function option_hints( $field ) {
		$hints = isset( $field['option_hints'] ) ? $field['option_hints'] : array();
		if ( self::is_lazy( $hints ) ) {
			$hints = call_user_func( $hints, $field );
		}
		return is_array( $hints ) ? $hints : array();
	}

	/**
	 * Does the row find its choices on demand ( search box backed by AJAX ) rather than
	 * from a printed list? True with a 'search_callback', or 'display' => 'remote'.
	 *
	 * @since 6.0.0
	 */
	public static function is_remote( $field ) {
		if ( isset( $field['search_callback'] ) && self::is_lazy( $field['search_callback'] ) ) {
			return true;
		}
		return isset( $field['display'] ) && 'remote' === $field['display'];
	}

	/**
	 * Turn whatever a search / validate callback returned into a list of
	 * array( 'value', 'label', 'hint' ) rows. Accepts value => label, value => array( label, hint ),
	 * or a list of arrays / objects carrying value|id, label|name and hint.
	 *
	 * @since 6.0.0
	 */
	public static function normalize_rows( $rows, $hints = array() ) {
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $key => $row ) {
			if ( is_object( $row ) ) {
				$row = get_object_vars( $row );
			}
			if ( is_array( $row ) ) {
				$value = isset( $row['value'] ) ? $row['value'] : ( isset( $row['id'] ) ? $row['id'] : $key );
				$label = isset( $row['label'] ) ? $row['label'] : ( isset( $row['name'] ) ? $row['name'] : ( isset( $row[0] ) ? $row[0] : $value ) );
				$hint  = isset( $row['hint'] ) ? $row['hint'] : ( isset( $row[1] ) ? $row[1] : '' );
			} else {
				$value = $key;
				$label = $row;
				$hint  = '';
			}
			$value = (string) $value;
			if ( '' === $hint && isset( $hints[ $value ] ) ) {
				$hint = $hints[ $value ];
			}
			$out[] = array( 'value' => $value, 'label' => (string) $label, 'hint' => (string) $hint );
		}
		/* Same-named choices ( two "Sale" categories ) get their id as the hint so they can be told apart. */
		$counts = array();
		foreach ( $out as $row ) {
			$k = strtolower( $row['label'] );
			$counts[ $k ] = isset( $counts[ $k ] ) ? $counts[ $k ] + 1 : 1;
		}
		foreach ( $out as $i => $row ) {
			if ( '' === $row['hint'] && $counts[ strtolower( $row['label'] ) ] > 1 ) {
				/* translators: %s: option id, shown to tell apart options that share a name */
				$out[ $i ]['hint'] = sprintf( __( 'ID %s', 'wp-easycart' ), $row['value'] );
			}
		}
		return $out;
	}

	/**
	 * Label ( and hint ) for the given values only, in the order given, skipping values that
	 * no longer exist. Uses 'validate_callback' when declared ( a targeted lookup ), else the
	 * resolved option list.
	 *
	 * @since 6.0.0
	 * @return array value => array( 'value', 'label', 'hint' )
	 */
	public static function labels( $field, $values ) {
		$wanted = array();
		foreach ( (array) $values as $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value && ! in_array( $value, $wanted, true ) ) {
				$wanted[] = $value;
			}
		}
		if ( empty( $wanted ) ) {
			return array();
		}
		$found = array();
		if ( isset( $field['validate_callback'] ) && self::is_lazy( $field['validate_callback'] ) ) {
			foreach ( self::normalize_rows( call_user_func( $field['validate_callback'], $wanted, $field ) ) as $row ) {
				$found[ $row['value'] ] = $row;
			}
		} else {
			$options = self::options( $field );
			$hints   = self::option_hints( $field );
			foreach ( $wanted as $value ) {
				if ( isset( $options[ $value ] ) ) {
					$found[ $value ] = array( 'value' => $value, 'label' => (string) $options[ $value ], 'hint' => isset( $hints[ $value ] ) ? (string) $hints[ $value ] : '' );
				}
			}
		}
		$ordered = array();
		foreach ( $wanted as $value ) {
			if ( isset( $found[ $value ] ) ) {
				$ordered[ $value ] = $found[ $value ];
			}
		}
		return $ordered;
	}

	/**
	 * Matches for a picker search box. 'search_callback' answers when declared; otherwise the
	 * resolved option list is filtered on label or value ( case-insensitive substring ).
	 *
	 * @since 6.0.0
	 * @return array list of array( 'value', 'label', 'hint' ), at most $limit
	 */
	public static function option_search( $field, $term, $limit = self::SEARCH_LIMIT ) {
		$limit = max( 1, min( (int) $limit, self::SEARCH_LIMIT ) );
		$term  = trim( (string) $term );
		if ( isset( $field['search_callback'] ) && self::is_lazy( $field['search_callback'] ) ) {
			$rows = self::normalize_rows( call_user_func( $field['search_callback'], $term, $field, $limit ) );
			return array_slice( $rows, 0, $limit );
		}
		$options = self::options( $field );
		$hints   = self::option_hints( $field );
		$needle  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term ) : strtolower( $term );
		$rows    = array();
		foreach ( $options as $value => $label ) {
			$value = (string) $value;
			$label = (string) $label;
			if ( '' !== $needle ) {
				$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label . ' ' . $value ) : strtolower( $label . ' ' . $value );
				if ( false === strpos( $hay, $needle ) ) {
					continue;
				}
			}
			$rows[ $value ] = array( 'label' => $label, 'hint' => isset( $hints[ $value ] ) ? (string) $hints[ $value ] : '' );
			if ( count( $rows ) >= $limit ) {
				break;
			}
		}
		return self::normalize_rows( $rows );
	}

	/** Is the PRO ( or Premium ) feature this row belongs to available on this install? */
	public static function is_locked( $field_or_section ) {
		$pro = isset( $field_or_section['pro'] ) ? $field_or_section['pro'] : false;
		if ( ! $pro ) {
			return false;
		}
		$locked = true;
		if ( class_exists( 'wp_easycart_admin_pro_gate' ) ) {
			$args = array( 'min_version' => isset( $field_or_section['pro_min_version'] ) ? $field_or_section['pro_min_version'] : '6.0.0' );
			$locked = ! wp_easycart_admin_pro_gate::is_enabled( $args );
		}
		if ( ! $locked && 'premium' === $pro && function_exists( 'wp_easycart_admin_license' ) ) {
			if ( class_exists( 'wp_easycart_admin_edition' ) ) {
				$locked = ! wp_easycart_admin_edition::is_premium();
			} else {
				$ld = wp_easycart_admin_license()->license_data;
				$model = ( $ld && isset( $ld->model_number ) ) ? strtolower( trim( (string) $ld->model_number ) ) : '';
				$locked = ( 'ec410' !== $model );
			}
		}
		return (bool) apply_filters( 'wp_easycart_settings_field_locked', $locked, $field_or_section );
	}

	/**
	 * Search every declared page. Returns ranked rows:
	 * array( 'key', 'label', 'desc', 'type', 'page', 'page_title', 'section', 'section_title', 'url', 'locked', 'value_text' ).
	 */
	public static function search( $term, $limit = 20 ) {
		$term = trim( strtolower( (string) $term ) );
		if ( '' === $term ) {
			return array();
		}
		$words = preg_split( '/\s+/', $term );
		$hits = array();
		foreach ( self::pages() as $slug => $page ) {
			foreach ( $page['sections'] as $section_slug => $section ) {
				foreach ( $section['fields'] as $key => $field ) {
					if ( 'html' === $field['type'] ) {
						continue;
					}
					$label = strtolower( $field['label'] );
					$desc  = strtolower( $field['desc'] );
					$hay   = $label . ' ' . $desc . ' ' . strtolower( implode( ' ', $field['keywords'] ) ) . ' ' . strtolower( $key ) . ' ' . strtolower( $section['title'] ) . ' ' . strtolower( $page['title'] );
					$score = 0;
					foreach ( $words as $w ) {
						if ( '' === $w ) {
							continue;
						}
						if ( false === strpos( $hay, $w ) ) {
							$score = 0;
							break;
						}
						if ( $label === $w ) {
							$score += 100;
						} elseif ( 0 === strpos( $label, $w ) ) {
							$score += 60;
						} elseif ( false !== strpos( $label, $w ) ) {
							$score += 40;
						} elseif ( false !== strpos( $desc, $w ) ) {
							$score += 20;
						} else {
							$score += 10;
						}
					}
					if ( $score <= 0 ) {
						continue;
					}
					$hits[] = array(
						'score'         => $score,
						'key'           => $key,
						'label'         => $field['label'],
						'desc'          => $field['desc'],
						'type'          => $field['type'],
						'page'          => $slug,
						'page_title'    => $page['title'],
						'section'       => $section_slug,
						'section_title' => $section['title'],
						'url'           => self::page_url( $slug, $key ),
						'locked'        => self::is_locked( $field ) || self::is_locked( $section ),
						'value_text'    => self::value_text( $field, false ),
					);
				}
				/* Sections built from custom markup ( cart importer, order statuses, tax tables ) or tool buttons
				 * have no fields to match, so the section itself is searchable by title, hint and keywords. */
				if ( empty( $section['render'] ) && empty( $section['actions'] ) ) {
					continue;
				}
				$title   = strtolower( $section['title'] );
				$sec_hay = $title . ' ' . strtolower( isset( $section['hint'] ) ? (string) $section['hint'] : '' ) . ' ' . strtolower( implode( ' ', isset( $section['keywords'] ) ? (array) $section['keywords'] : array() ) ) . ' ' . strtolower( $page['title'] );
				foreach ( $section['actions'] as $action ) {
					$sec_hay .= ' ' . strtolower( $action['label'] . ' ' . $action['desc'] );
				}
				$score = 0;
				foreach ( $words as $w ) {
					if ( '' === $w ) {
						continue;
					}
					if ( false === strpos( $sec_hay, $w ) ) {
						$score = 0;
						break;
					}
					$score += ( false !== strpos( $title, $w ) ) ? 50 : 15;
				}
				if ( $score > 0 ) {
					$hits[] = array(
						'score'         => $score,
						'key'           => '',
						'label'         => $section['title'],
						'desc'          => isset( $section['hint'] ) ? (string) $section['hint'] : '',
						'type'          => 'section',
						'page'          => $slug,
						'page_title'    => $page['title'],
						'section'       => $section_slug,
						'section_title' => $section['title'],
						'url'           => self::page_url( $slug ) . '#ecst-sec-' . rawurlencode( $section_slug ),
						'locked'        => self::is_locked( $section ),
						'value_text'    => __( 'Section', 'wp-easycart' ),
					);
				}
			}
		}
		/* Whole pages, including the ones still on the classic layout ( Shipping rates, Countries, Logs … ). */
		$catalog = class_exists( 'wp_easycart_admin_settings_home' ) ? wp_easycart_admin_settings_home::catalog() : array();
		foreach ( $catalog as $slug => $entry ) {
			$score = self::match_score( $words, $entry['title'], $entry['description'], array( $slug ) );
			if ( $score > 0 ) {
				$hits[] = array(
					'score'         => $score + 5,
					'key'           => '',
					'label'         => $entry['title'],
					'desc'          => $entry['description'],
					'type'          => 'page',
					'page'          => $slug,
					'page_title'    => __( 'Settings', 'wp-easycart' ),
					'section_title' => __( 'Page', 'wp-easycart' ),
					'url'           => $entry['url'],
					'locked'        => false,
					'value_text'    => __( 'Page', 'wp-easycart' ),
				);
			}
		}

		/**
		 * Things that live inside custom page markup rather than declared fields ( payment gateways, carriers … ).
		 * Each item: label, desc, url, keywords ( array ), page_title, section_title, value_text, locked.
		 *
		 * @since 6.0.0
		 */
		foreach ( (array) apply_filters( 'wp_easycart_settings_search_items', array() ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['label'] ) || empty( $item['url'] ) ) {
				continue;
			}
			$item  = wp_parse_args( $item, array( 'desc' => '', 'keywords' => array(), 'page' => '', 'page_title' => '', 'section_title' => '', 'value_text' => '', 'locked' => false ) );
			$score = self::match_score( $words, $item['label'], $item['desc'], (array) $item['keywords'] );
			if ( $score > 0 ) {
				$hits[] = array(
					'score'         => $score + 10,
					'key'           => '',
					'label'         => (string) $item['label'],
					'desc'          => (string) $item['desc'],
					'type'          => 'item',
					'page'          => (string) $item['page'],
					'page_title'    => (string) $item['page_title'],
					'section_title' => (string) $item['section_title'],
					'url'           => (string) $item['url'],
					'locked'        => (bool) $item['locked'],
					'value_text'    => (string) $item['value_text'],
				);
			}
		}

		usort( $hits, function( $a, $b ) {
			if ( $a['score'] === $b['score'] ) {
				return strcmp( $a['label'], $b['label'] );
			}
			return ( $a['score'] > $b['score'] ) ? -1 : 1;
		} );
		return array_slice( $hits, 0, (int) $limit );
	}

	/**
	 * Every word must appear in the label, description or keywords; label matches rank highest.
	 * Returns 0 when any word is missing.
	 *
	 * @since 6.0.0
	 */
	private static function match_score( $words, $label, $desc, $keywords ) {
		$label = strtolower( (string) $label );
		$desc  = strtolower( (string) $desc );
		$hay   = $label . ' ' . $desc . ' ' . strtolower( implode( ' ', array_map( 'strval', (array) $keywords ) ) );
		$score = 0;
		foreach ( $words as $w ) {
			if ( '' === $w ) {
				continue;
			}
			if ( false === strpos( $hay, $w ) ) {
				return 0;
			}
			if ( $label === $w ) {
				$score += 100;
			} elseif ( 0 === strpos( $label, $w ) ) {
				$score += 60;
			} elseif ( false !== strpos( $label, $w ) ) {
				$score += 40;
			} elseif ( false !== strpos( $desc, $w ) ) {
				$score += 20;
			} else {
				$score += 10;
			}
		}
		return $score;
	}

	/**
	 * Short human description of the stored value, for search results and save replies.
	 * With $resolve false ( settings search ) no option callable and no 'current' callable
	 * runs: a targeted 'validate_callback' may still label the chosen values, otherwise the
	 * raw value or a count is shown.
	 */
	public static function value_text( $field, $resolve = true ) {
		if ( ! $resolve && isset( $field['current'] ) && self::is_lazy( $field['current'] ) ) {
			return '';
		}
		$value = self::value( $field, $resolve );
		$can_label = $resolve || ! self::is_lazy( isset( $field['options'] ) ? $field['options'] : array() ) || ( isset( $field['validate_callback'] ) && self::is_lazy( $field['validate_callback'] ) );
		switch ( $field['type'] ) {
			case 'toggle':
				return $value ? __( 'On', 'wp-easycart' ) : __( 'Off', 'wp-easycart' );
			case 'select':
			case 'pills':
				$value = (string) $value;
				if ( '' === $value || ! $can_label ) {
					return $value;
				}
				$known = self::labels( $field, array( $value ) );
				return isset( $known[ $value ] ) ? $known[ $value ]['label'] : $value;
			case 'multiselect':
				$parts = is_array( $value ) ? $value : array_map( 'trim', explode( ( 'array' === $field['separator'] ) ? ',' : $field['separator'], (string) $value ) );
				$parts = array_values( array_filter( array_map( 'strval', $parts ), 'strlen' ) );
				if ( empty( $parts ) ) {
					return __( 'None', 'wp-easycart' );
				}
				if ( ! $can_label ) {
					/* translators: %d: number of chosen values */
					return sprintf( __( '%d selected', 'wp-easycart' ), count( $parts ) );
				}
				$known  = self::labels( $field, $parts );
				$labels = array();
				foreach ( $parts as $part ) {
					$labels[] = isset( $known[ $part ] ) ? $known[ $part ]['label'] : $part;
				}
				$text = implode( ', ', $labels );
				return ( strlen( $text ) > 40 ) ? substr( $text, 0, 37 ) . '…' : $text;
			case 'password':
				return '' === (string) $value ? __( 'Not set', 'wp-easycart' ) : '••••••';
			default:
				$text = trim( (string) $value );
				if ( '' === $text ) {
					return __( 'Empty', 'wp-easycart' );
				}
				return ( strlen( $text ) > 40 ) ? substr( $text, 0, 37 ) . '…' : $text;
		}
	}

	/** Fill in defaults so renderers and savers never need isset() gymnastics. */
	public static function normalize_page( $decl ) {
		$page = wp_parse_args( $decl, array(
			'slug'        => '',
			'title'       => '',
			'description' => '',
			'group'       => 'customize',
			'icon'        => 'admin-generic',
			'docs'        => array(),
			'legacy'      => array(),
			'upsell'      => 'default',
			'sections'    => array(),
		) );
		$page['slug']   = sanitize_key( $page['slug'] );
		$page['legacy'] = array_values( array_map( 'sanitize_key', (array) $page['legacy'] ) );
		$page['url']    = self::page_url( $page['slug'] );
		$total = 0;
		$sections = array();
		foreach ( $page['sections'] as $section_slug => $section ) {
			$section_slug = sanitize_key( $section_slug );
			$section = wp_parse_args( $section, array(
				'title'   => '',
				'hint'    => '',
				'pro'     => false,
				'fields'  => array(),
				'actions' => array(),
				'render'  => null,
			) );
			$section['slug'] = $section_slug;
			$fields = array();
			foreach ( $section['fields'] as $key => $field ) {
				$key = sanitize_key( $key );
				$fields[ $key ] = self::normalize_field( $key, $field );
				$fields[ $key ]['page'] = $page['slug'];
				if ( 'html' !== $fields[ $key ]['type'] ) {
					$total++;
				}
			}
			$section['fields'] = $fields;
			$section['count']  = count( array_filter( $fields, function( $f ) { return 'html' !== $f['type']; } ) );
			$actions = array();
			foreach ( (array) $section['actions'] as $action ) {
				$action = wp_parse_args( $action, array( 'id' => '', 'label' => '', 'desc' => '', 'button' => '', 'confirm' => '', 'danger' => false, 'callback' => null, 'pro' => false ) );
				$action['id'] = sanitize_key( $action['id'] );
				if ( '' !== $action['id'] ) {
					$actions[ $action['id'] ] = $action;
				}
			}
			$section['actions'] = $actions;
			$sections[ $section_slug ] = $section;
		}
		$page['sections'] = $sections;
		$page['count']    = $total;
		return $page;
	}

	public static function normalize_field( $key, $field ) {
		$field = wp_parse_args( $field, array(
			'type'        => 'toggle',
			'label'       => $key,
			'desc'        => '',
			'default'     => '',
			'advanced'    => false,
			'pro'         => false,
			'parent'      => '',
			'show_when'   => '1',
			'options'     => array(),          // value => label, or a callable( $field ) resolved on demand ( see options() )
			'search_callback'   => null,       // callable( $term, $field, $limit ): remote search picker
			'validate_callback' => null,       // callable( $values, $field ): targeted lookup for save + chip labels
			'current'     => null,             // callable( $field ): live value for settings not stored as an option
			'page'        => '',               // slug of the owning page ( filled in by normalize_page() )
			'separator'   => ',',              // multiselect: how the chosen values are joined when stored
			'display'     => 'auto',           // multiselect: auto | pills | picker | remote
			'exclusive'   => array(),          // multiselect: values that cannot combine with others
			'suggestions' => array(),          // text: value => label offered in a datalist
			'pair'        => array(),          // pairs: key / value column settings, join character, add-row label
			'option_hints' => array(),         // multiselect picker: value => secondary text, or a callable( $field )
			'unit'        => '',
			'prefix'      => '',
			'placeholder' => '',
			'min'         => null,
			'max'         => null,
			'step'        => null,
			'rows'        => 4,
			'sanitize'    => null,
			'validate'    => null,
			'on_save'     => null,
			'keywords'    => array(),
			'legacy'      => array(),
			'help'        => '',
			'render'      => null,
		) );
		$field['key']  = $key;
		$field['type'] = sanitize_key( $field['type'] );
		if ( 'toggle' === $field['type'] && '' === $field['default'] ) {
			$field['default'] = 0;
		}
		$field['keywords'] = array_values( (array) $field['keywords'] );
		if ( 'pairs' === $field['type'] ) {
			$pair          = wp_parse_args( (array) $field['pair'], array( 'key' => array(), 'value' => array(), 'join' => '=', 'add' => '' ) );
			$pair['key']   = wp_parse_args( (array) $pair['key'], array( 'label' => '', 'placeholder' => '', 'maxlength' => 0, 'upper' => false ) );
			$pair['value'] = wp_parse_args( (array) $pair['value'], array( 'label' => '', 'placeholder' => '', 'inputmode' => 'text' ) );
			$field['pair'] = $pair;
		}
		return $field;
	}

	/**
	 * Multiselect presentation: 'remote' ( chosen chips + AJAX search, nothing else printed ),
	 * 'picker' ( chips + a searchable list of every choice ) or 'pills' ( every choice visible ).
	 * Pass the resolved options when you already have them; a remote row never resolves its list.
	 */
	public static function multiselect_display( $field, $options = null ) {
		if ( self::is_remote( $field ) ) {
			return 'remote';
		}
		if ( null === $options ) {
			$options = self::options( $field );
		}
		if ( count( $options ) > self::PICKER_THRESHOLD ) {
			return 'remote';
		}
		if ( in_array( $field['display'], array( 'pills', 'picker' ), true ) ) {
			return $field['display'];
		}
		return ( count( $options ) > self::PILLS_THRESHOLD ) ? 'picker' : 'pills';
	}

	/** Autosave ( on change ) or batch ( save bar )? */
	public static function save_mode( $field ) {
		return in_array( $field['type'], array( 'toggle', 'select', 'pills' ), true ) ? 'auto' : 'batch';
	}

	/**
	 * Sanitize a posted value for a field. Returns the clean value, or a WP_Error
	 * when the value is not acceptable at all ( unknown option, out of range ).
	 */
	public static function sanitize( $field, $raw ) {
		if ( is_callable( $field['sanitize'] ) ) {
			return call_user_func( $field['sanitize'], $raw, $field );
		}
		switch ( $field['type'] ) {
			case 'toggle':
				return ( '1' === (string) $raw || 'true' === (string) $raw || 'on' === (string) $raw ) ? 1 : 0;
			case 'number':
				if ( '' === trim( (string) $raw ) ) {
					return '';
				}
				$number = is_numeric( $raw ) ? $raw + 0 : null;
				if ( null === $number ) {
					return new WP_Error( 'number', __( 'Enter a number.', 'wp-easycart' ) );
				}
				if ( null !== $field['min'] && $number < $field['min'] ) {
					return new WP_Error( 'min', sprintf( __( 'Must be at least %s.', 'wp-easycart' ), $field['min'] ) );
				}
				if ( null !== $field['max'] && $number > $field['max'] ) {
					return new WP_Error( 'max', sprintf( __( 'Must be at most %s.', 'wp-easycart' ), $field['max'] ) );
				}
				return ( null !== $field['step'] && 1 == $field['step'] ) ? (int) $number : $number;
			case 'select':
			case 'pills':
				$raw = (string) $raw;
				if ( self::is_remote( $field ) ) {
					/* Search picker: an empty pick clears the setting; anything else must exist right now. */
					if ( '' === $raw ) {
						return '';
					}
					$known = self::labels( $field, array( $raw ) );
					if ( ! isset( $known[ $raw ] ) ) {
						return new WP_Error( 'option', __( 'That is not one of the available choices.', 'wp-easycart' ) );
					}
					return $raw;
				}
				$options = self::options( $field );
				if ( ! isset( $options[ $raw ] ) ) {
					return new WP_Error( 'option', __( 'That is not one of the available choices.', 'wp-easycart' ) );
				}
				return $raw;
			case 'multiselect':
				// Stored as the chosen option values joined by the field's separator, in option
				// order; 'separator' => 'array' stores a PHP array instead ( the browser always
				// sends a comma-joined list ).
				$chosen = array();
				$sep = ( 'array' === $field['separator'] ) ? ',' : $field['separator'];
				$parts = array_map( 'trim', explode( $sep, (string) $raw ) );
				if ( self::is_remote( $field ) || ( isset( $field['validate_callback'] ) && self::is_lazy( $field['validate_callback'] ) ) || count( $parts ) > self::PICKER_THRESHOLD ) {
					/* Long or remote lists: check only the posted ids ( WHERE id IN … ), keeping the order the
					 * chips were added in, which is also the order the row renders them next time. */
					$chosen = array_map( 'strval', array_keys( self::labels( $field, $parts ) ) );
				} else {
					foreach ( self::options( $field ) as $opt_value => $opt_label ) {
						if ( in_array( (string) $opt_value, $parts, true ) ) {
							$chosen[] = (string) $opt_value;
						}
					}
				}
				// 'exclusive' choices ( e.g. "No restrictions" ) never combine with others, and stand in for an empty choice.
				$exclusive = array_map( 'strval', (array) $field['exclusive'] );
				if ( $exclusive ) {
					$others = array_values( array_diff( $chosen, $exclusive ) );
					if ( $others ) {
						$chosen = $others;
					} elseif ( ! $chosen || count( $chosen ) > 1 ) {
						$chosen = array( $exclusive[0] );
					}
				}
				return ( 'array' === $field['separator'] ) ? $chosen : implode( $field['separator'], $chosen );
			case 'color':
				$hex = sanitize_hex_color( (string) $raw );
				return ( null === $hex ) ? '' : $hex;
			case 'url':
				return esc_url_raw( trim( (string) $raw ) );
			case 'email':
				return sanitize_email( (string) $raw );
			case 'textarea':
				return sanitize_textarea_field( (string) $raw );
			case 'html':
				return null;
			default:
				return sanitize_text_field( (string) $raw );
		}
	}
}

endif;
