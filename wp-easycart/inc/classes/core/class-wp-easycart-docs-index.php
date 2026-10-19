<?php
/**
 * WP EasyCart — the list of help articles the admin search looks through ( 6.0.5 ).
 *
 * The admin search finds help articles in the browser, so nothing a merchant types is sent anywhere. It needs the list of
 * articles: title, address, summary and category, and ( 6.0.5, format 2 ) what each article is about: its section headings
 * and the words its text uses most that most articles do not ( so "email setup" finds the email guides, not every page with
 * "settings" in it ). A copy ships with every release ( admin/data/docs-index.json ) and the store asks docs.wpeasycart.com
 * for the current list once a week ( WP-Cron, WordPress's own REST API there, a few requests ), keeping it in an option that is
 * not autoloaded. A failed refresh keeps the list there is; a list saved in the older format gives way to the copy shipped.
 *
 * @package wp-easycart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_docs_index' ) ) :

	/**
	 * The help articles list.
	 */
	class wp_easycart_docs_index {

		/** Option ( autoload off ): { fetched, format, items: [ { t, u, e, c, h, w } ], common: [ stems found in most articles ] }. */
		const OPTION = 'wp_easycart_docs_index';

		/** WP-Cron hook: the weekly refresh. */
		const CRON = 'wp_easycart_docs_index_refresh';

		/** The docs site's REST API. */
		const API = 'https://docs.wpeasycart.com/wp-json/wp/v2/';

		/** At most this many pages of articles ( with their text ) are asked for. */
		const MAX_PAGES = 10;

		/** Articles a page: their text comes too ( about 30 KB each ). */
		const PER_PAGE = 50;

		/** 2: with h ( headings ) and w ( key words ). A saved list in an older format gives way to the copy shipped. */
		const FORMAT = 2;

		/** Key words kept an article. */
		const MAX_WORDS = 40;

		/** A heading or word in more than this share of the articles says nothing about one ( "Related panels" ). */
		const COMMON = 0.2;

		/** Hooks. */
		public static function init() {
			add_action( self::CRON, array( __CLASS__, 'refresh' ) );
		}

		/**
		 * The articles: the refreshed list, else the copy shipped with WP EasyCart.
		 *
		 * @return array { fetched: int, format: int, items: array of { t: title, u: address, e: summary, c: category, h: headings,
		 *               w: key words } }
		 */
		public static function get() {
			$saved = get_option( self::OPTION );
			if ( is_array( $saved ) && ! empty( $saved['items'] ) && is_array( $saved['items'] ) && isset( $saved['format'] ) && (int) $saved['format'] >= self::FORMAT ) {
				return $saved;
			}
			return self::bundled();
		}

		/**
		 * The copy shipped with this release.
		 *
		 * @return array
		 */
		public static function bundled() {
			$file = EC_PLUGIN_DIRECTORY . '/admin/data/docs-index.json';
			$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file shipped with the plugin.
			if ( ! is_array( $json ) || empty( $json['items'] ) || ! is_array( $json['items'] ) ) {
				return array(
					'fetched' => 0,
					'format'  => self::FORMAT,
					'items'   => array(),
					'common'  => array(),
				);
			}
			return array(
				'fetched' => isset( $json['fetched'] ) ? (int) $json['fetched'] : 0,
				'format'  => isset( $json['format'] ) ? (int) $json['format'] : 1,
				'items'   => $json['items'],
				'common'  => isset( $json['common'] ) && is_array( $json['common'] ) ? array_map( 'strval', $json['common'] ) : array(),
			);
		}

		/**
		 * When the list was last fetched ( the admin search's index version follows it ).
		 *
		 * @return int
		 */
		public static function stamp() {
			$list = self::get();
			return (int) $list['fetched'];
		}

		/** Schedules the weekly refresh ( called from the admin, so a store nobody manages never asks ). */
		public static function schedule() {
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON );
			}
		}

		/**
		 * Asks docs.wpeasycart.com for the current list.
		 *
		 * @return bool Whether a new list was kept.
		 */
		public static function refresh() {
			/**
			 * The docs site's REST API address ( ends with /wp-json/wp/v2/ ).
			 *
			 * @since 6.0.5
			 * @param string $api Address.
			 */
			$api   = trailingslashit( (string) apply_filters( 'wp_easycart_docs_index_api', self::API ) );
			$names = array();
			$cats  = self::request( $api . 'doc_category?per_page=100&_fields=id,name' );
			if ( is_array( $cats ) ) {
				foreach ( $cats as $cat ) {
					if ( isset( $cat['id'], $cat['name'] ) ) {
						$names[ (int) $cat['id'] ] = self::clean( $cat['name'] );
					}
				}
			}
			$items = array();
			$texts = array();
			for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
				$docs = self::request( $api . 'docs?per_page=' . self::PER_PAGE . '&page=' . $page . '&_fields=title,link,excerpt,doc_category,content' );
				if ( ! is_array( $docs ) ) {
					if ( 1 === $page ) {
						return false;
					}
					break;
				}
				foreach ( $docs as $doc ) {
					$title = self::clean( isset( $doc['title']['rendered'] ) ? $doc['title']['rendered'] : '' );
					$link  = isset( $doc['link'] ) ? esc_url_raw( (string) $doc['link'] ) : '';
					if ( '' === $title || '' === $link || 0 !== strpos( $link, 'https://docs.wpeasycart.com/' ) ) {
						continue;
					}
					$cat = '';
					foreach ( isset( $doc['doc_category'] ) ? (array) $doc['doc_category'] : array() as $id ) {
						if ( isset( $names[ (int) $id ] ) ) {
							$cat = $names[ (int) $id ];
							break;
						}
					}
					$items[] = array(
						't' => $title,
						'u' => $link,
						'e' => function_exists( 'mb_substr' ) ? mb_substr( self::clean( isset( $doc['excerpt']['rendered'] ) ? $doc['excerpt']['rendered'] : '' ), 0, 220 ) : substr( self::clean( isset( $doc['excerpt']['rendered'] ) ? $doc['excerpt']['rendered'] : '' ), 0, 220 ),
						'c' => $cat,
					);
					$texts[] = isset( $doc['content']['rendered'] ) ? (string) $doc['content']['rendered'] : '';
				}
				if ( count( $docs ) < self::PER_PAGE ) {
					break;
				}
			}
			if ( count( $items ) < 20 ) {
				return false; /* a half answer never replaces a whole list */
			}
			$common = array();
			$items  = self::describe( $items, $texts, $common );
			update_option(
				self::OPTION,
				array(
					'fetched' => time(),
					'format'  => self::FORMAT,
					'items'   => $items,
					'common'  => $common,
				),
				false
			);
			return true;
		}

		/**
		 * What each article is about, from its text: h = its section headings, w = its key words ( the words it uses most, as
		 * stems, weighed against how many articles use them ). Headings and words found in more than COMMON of the articles
		 * are left out. $common gets those words ( stems, the most used first, at most 400 ): the admin search gives them less
		 * weight in a question ( "customers not getting receipts": "customer" is in most articles, "receipt" in few ).
		 *
		 * @since 6.0.5
		 * @param array $items  Articles ( t, u, e, c ).
		 * @param array $texts  Their HTML, in the same order.
		 * @param array $common Set to the words found in more than COMMON of the articles.
		 * @return array The articles with h and w.
		 */
		public static function describe( $items, $texts, &$common = null ) {
			$items    = array_values( $items );
			$count    = max( 1, count( $items ) );
			$headings = array();
			$words    = array();
			$head_df  = array();
			$word_df  = array();
			foreach ( $items as $i => $item ) {
				$html           = isset( $texts[ $i ] ) ? (string) $texts[ $i ] : '';
				$headings[ $i ] = array();
				if ( preg_match_all( '#<h[2-4][^>]*>(.*?)</h[2-4]>#is', $html, $m ) ) {
					foreach ( $m[1] as $heading ) {
						$heading = self::clean( $heading );
						$key     = strtolower( $heading );
						if ( '' !== $heading && strlen( $heading ) <= 120 && ! isset( $headings[ $i ][ $key ] ) ) {
							$headings[ $i ][ $key ] = $heading;
						}
					}
				}
				foreach ( array_keys( $headings[ $i ] ) as $key ) {
					$head_df[ $key ] = isset( $head_df[ $key ] ) ? $head_df[ $key ] + 1 : 1;
				}
				$words[ $i ] = array();
				/* Tags become spaces: "</h3><p>Next" must not join two words. */
				foreach ( self::words( self::clean( (string) preg_replace( '#<[^>]+>#', ' ', (string) preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', $html ) ) ) ) as $word ) {
					$words[ $i ][ $word ] = isset( $words[ $i ][ $word ] ) ? $words[ $i ][ $word ] + 1 : 1;
				}
				foreach ( array_keys( $words[ $i ] ) as $word ) {
					$word_df[ $word ] = isset( $word_df[ $word ] ) ? $word_df[ $word ] + 1 : 1;
				}
			}
			foreach ( $items as $i => $item ) {
				$keep = array();
				foreach ( $headings[ $i ] as $key => $heading ) {
					if ( $head_df[ $key ] / $count <= self::COMMON ) {
						$keep[] = $heading;
					}
				}
				$heading_text = implode( ' · ', $keep );
				if ( strlen( $heading_text ) > 600 ) {
					$heading_text = function_exists( 'mb_substr' ) ? mb_substr( $heading_text, 0, 600 ) : substr( $heading_text, 0, 600 );
				}
				$rank = array();
				foreach ( $words[ $i ] as $word => $tf ) {
					if ( $word_df[ $word ] / $count <= self::COMMON ) {
						$rank[ $word ] = $tf * log( $count / $word_df[ $word ] );
					}
				}
				arsort( $rank );
				$items[ $i ]['h'] = $heading_text;
				$items[ $i ]['w'] = implode( ' ', array_slice( array_map( 'strval', array_keys( $rank ) ), 0, self::MAX_WORDS ) );
			}
			$common = array();
			arsort( $word_df );
			foreach ( $word_df as $word => $df ) {
				if ( $df / $count <= self::COMMON || count( $common ) >= 400 ) {
					break;
				}
				$common[] = (string) $word;
			}
			return $items;
		}

		/**
		 * The words of a text as the admin search compares them: lower case, three letters or more, common English words out,
		 * light stems ( admin-search-v2.js stem() is the same rule: emails = email, shipping = ship, settings = setting ).
		 *
		 * @since 6.0.5
		 * @param string $text Text.
		 * @return array
		 */
		public static function words( $text ) {
			static $stop = null;
			if ( null === $stop ) {
				$stop = array_flip( explode( ' ', 'the and for you your are can not this that with from will have has was were its into onto then than them they their there here what when where which while who why how does did done get gets got use used using also any all each every one two more most other some such only own same very just about over under out off our ours should could would may might must shall let lets via per yes too want need see make makes made sure first next new now way ways' ) );
			}
			$out = array();
			foreach ( (array) preg_split( '/[^a-z0-9]+/', strtolower( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) ) as $word ) {
				if ( strlen( $word ) < 3 || isset( $stop[ $word ] ) || ctype_digit( $word ) ) {
					continue;
				}
				$out[] = self::stem( $word );
			}
			return $out;
		}

		/**
		 * A light stem, applied until it settles ( settings = setting = set; admin-search-v2.js stem() is the same rule ).
		 *
		 * @since 6.0.5
		 * @param string $word Lower case word.
		 * @return string
		 */
		public static function stem( $word ) {
			$once = self::stem_once( $word );
			return $once === $word ? $once : self::stem_once( $once );
		}

		/**
		 * One pass of the stem.
		 *
		 * @param string $word Lower case word.
		 * @return string
		 */
		private static function stem_once( $word ) {
			$len = strlen( $word );
			if ( $len > 4 && 'ies' === substr( $word, -3 ) ) {
				return substr( $word, 0, -3 ) . 'y';
			}
			if ( $len > 5 && 'ing' === substr( $word, -3 ) ) {
				return self::undouble( substr( $word, 0, -3 ) );
			}
			if ( $len > 4 && 'ed' === substr( $word, -2 ) ) {
				return self::undouble( substr( $word, 0, -2 ) );
			}
			if ( $len > 4 && preg_match( '/(ss|x|ch|sh)es$/', $word ) ) {
				return substr( $word, 0, -2 );
			}
			if ( $len > 3 && 's' === substr( $word, -1 ) && ! in_array( substr( $word, -2 ), array( 'ss', 'us', 'is' ), true ) ) {
				return substr( $word, 0, -1 );
			}
			return $word;
		}

		/**
		 * "shipp" = "ship", "stopp" = "stop" ( never ll, ss or zz ).
		 *
		 * @param string $stem Stem.
		 * @return string
		 */
		private static function undouble( $stem ) {
			$len = strlen( $stem );
			if ( $len > 2 && $stem[ $len - 1 ] === $stem[ $len - 2 ] && ! in_array( $stem[ $len - 1 ], array( 'l', 's', 'z' ), true ) ) {
				return substr( $stem, 0, -1 );
			}
			return $stem;
		}

		/**
		 * One GET to the docs site, decoded; null on any failure.
		 *
		 * @param string $url Address.
		 * @return array|null
		 */
		private static function request( $url ) {
			$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return null;
			}
			$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			return is_array( $json ) ? $json : null;
		}

		/**
		 * Text from the docs site's HTML: tags out, entities decoded, spaces folded.
		 *
		 * @param string $html HTML.
		 * @return string
		 */
		private static function clean( $html ) {
			$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
			$text = str_replace( array( '[…]', '[&hellip;]', "\xc2\xa0" ), array( '…', '…', ' ' ), $text );
			return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		}
	}

	wp_easycart_docs_index::init();

endif;
