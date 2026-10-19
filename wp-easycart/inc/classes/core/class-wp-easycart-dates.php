<?php
/**
 * WP EasyCart — dates in the admin ( 6.0.5, admin review M1 ).
 *
 * Every date the admin prints goes through wp_easycart_admin_date( $time, $style ): month and day names in the site's language
 * ( date_i18n() ) and in the order WordPress's own date format uses, so a store set to "3 September 2026" reads "3 Sep" in its lists
 * and one set to "September 3, 2026" reads "Sep 3". Before 6.0.5 the lists, the order screen and Reports were month-first and English
 * whatever the store used.
 *
 * $time is a timestamp of the wall clock to print ( what date() printed before: strtotime() of a local date string, a timestamp the
 * list already moved to the store's time ), as date_i18n() takes it. Styles:
 *   day          3 Sep            Sep 3            09-03
 *   date         3 Sep 2026       Sep 3, 2026      2026-09-03
 *   long         WordPress's date format
 *   time         WordPress's time format
 *   day_time     3 Sep 4:15 am    Sep 3 4:15 am
 *   date_time    3 Sep 2026 4:15 am …
 *   weekday      Sun 20 Sep       Sun, Sep 20
 *   weekday_time Sunday 4:15 am
 *   day_clock    3 Sep 16:15      Sep 3 16:15      ( logs: always the 24-hour clock )
 *   month        Sep
 *   month_year   Sep 2026
 * A German format ( "j. F Y" ) keeps its dot ( "3. Sep" ); a year-first format ( "Y-m-d", Japanese "Y年n月j日" ) drops the year for day.
 *
 * The browser gets the same rule ( config(): order, the language as a BCP 47 tag, moment formats; Reports adds WordPress's month and
 * weekday names to its moment copy through moment_locale_script() ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_dates' ) ) :

	/**
	 * Admin dates in the store's order and language.
	 */
	class wp_easycart_dates {

		/**
		 * The order of day, month and year in WordPress's date format: dmy, mdy or ymd.
		 *
		 * @param string|null $format A date format ( WordPress's when null ).
		 * @return string
		 */
		public static function order( $format = null ) {
			$format = ( null === $format ) ? (string) get_option( 'date_format', 'F j, Y' ) : (string) $format;
			$plain  = preg_replace( '/\\\\./u', '', $format ); /* escaped characters are text */
			$day    = self::first( $plain, array( 'd', 'j' ) );
			$month  = self::first( $plain, array( 'F', 'M', 'm', 'n' ) );
			$year   = self::first( $plain, array( 'Y', 'y' ) );
			if ( null !== $year && ( null === $day || $year < $day ) && ( null === $month || $year < $month ) ) {
				$order = 'ymd';
			} elseif ( null !== $day && null !== $month && $day < $month ) {
				$order = 'dmy';
			} else {
				$order = 'mdy';
			}
			/**
			 * The order admin dates follow.
			 *
			 * @since 6.0.5
			 * @param string $order  dmy | mdy | ymd.
			 * @param string $format The date format read.
			 */
			$order = (string) apply_filters( 'wp_easycart_admin_date_order', $order, $format );
			return in_array( $order, array( 'dmy', 'mdy', 'ymd' ), true ) ? $order : 'mdy';
		}

		/**
		 * Where the first of these format characters sits.
		 *
		 * @param string $format Format without escaped characters.
		 * @param array  $chars  Characters.
		 * @return int|null
		 */
		private static function first( $format, $chars ) {
			$at = null;
			foreach ( $chars as $char ) {
				$pos = strpos( $format, $char );
				if ( false !== $pos && ( null === $at || $pos < $at ) ) {
					$at = $pos;
				}
			}
			return $at;
		}

		/**
		 * The PHP date format for a style.
		 *
		 * @param string $style See the file comment.
		 * @return string
		 */
		public static function format( $style = 'date' ) {
			$wp_date = (string) get_option( 'date_format', 'F j, Y' );
			$wp_time = (string) get_option( 'time_format', 'g:i a' );
			$order   = self::order( $wp_date );
			$dot     = ( 'dmy' === $order && 1 === preg_match( '/[dj]\./', $wp_date ) ); /* German, Danish, Finnish … "3. Sep" */
			if ( 'ymd' === $order ) {
				$day  = trim( preg_replace( '/^[Yy][^a-zA-Z\\\\]*/u', '', $wp_date ) );
				$day  = '' !== $day ? $day : 'm-d';
				$date = $wp_date;
				$week = 'D ' . $day;
			} elseif ( 'dmy' === $order ) {
				$day  = $dot ? 'j. M' : 'j M';
				$date = $dot ? 'j. M Y' : 'j M Y';
				$week = $dot ? 'D, j. M' : 'D j M';
			} else {
				$day  = 'M j';
				$date = 'M j, Y';
				$week = 'D, M j';
			}
			switch ( $style ) {
				case 'day':
					$format = $day;
					break;
				case 'long':
					$format = $wp_date;
					break;
				case 'time':
					$format = $wp_time;
					break;
				case 'day_time':
					$format = $day . ' ' . $wp_time;
					break;
				case 'date_time':
					$format = $date . ' ' . $wp_time;
					break;
				case 'weekday':
					$format = $week;
					break;
				case 'day_clock':
					$format = $day . ' H:i';
					break;
				case 'weekday_time':
					$format = 'l ' . $wp_time;
					break;
				case 'month':
					$format = 'M';
					break;
				case 'month_year':
					$format = ( 'ymd' === $order && 1 === preg_match( '/^Y[^a-zA-Z\\\\]*[nm]/u', $wp_date ) ) ? 'Y-m' : 'M Y';
					break;
				default:
					$format = $date;
					break;
			}
			/**
			 * The PHP date format an admin date style prints with.
			 *
			 * @since 6.0.5
			 * @param string $format Date format.
			 * @param string $style  Style.
			 * @param string $order  dmy | mdy | ymd.
			 */
			return (string) apply_filters( 'wp_easycart_admin_date_format', $format, $style, $order );
		}

		/**
		 * A date for the admin.
		 *
		 * @param int|string $time  Timestamp of the wall clock to print, or a date string ( read as that wall clock ).
		 * @param string     $style See the file comment.
		 * @return string '' for no date.
		 */
		public static function date( $time, $style = 'date' ) {
			if ( ! is_numeric( $time ) ) {
				$time = ( is_string( $time ) && '' !== trim( $time ) && 0 !== strpos( $time, '0000-00-00' ) ) ? strtotime( $time ) : 0;
			}
			$time = (int) $time;
			if ( $time <= 0 ) {
				return '';
			}
			return (string) date_i18n( self::format( $style ), $time );
		}

		/**
		 * A list's recent date: Today 4:15 am, Yesterday 4:15 am, a weekday within the week, else the day ( and the year when it is
		 * not this year ) with the time.
		 *
		 * @param int $time Wall clock timestamp.
		 * @param int $now  The same clock now.
		 * @return string
		 */
		public static function relative( $time, $now ) {
			$time = (int) $time;
			$now  = (int) $now;
			$day  = gmdate( 'Ymd', $time );
			if ( gmdate( 'Ymd', $now ) === $day ) {
				/* translators: %s: a time of day, e.g. 4:15 am. */
				return sprintf( __( 'Today %s', 'wp-easycart' ), self::date( $time, 'time' ) );
			}
			if ( gmdate( 'Ymd', $now - DAY_IN_SECONDS ) === $day ) {
				/* translators: %s: a time of day, e.g. 4:15 am. */
				return sprintf( __( 'Yesterday %s', 'wp-easycart' ), self::date( $time, 'time' ) );
			}
			if ( $time > $now - 6 * DAY_IN_SECONDS && $time <= $now ) {
				return self::date( $time, 'weekday_time' );
			}
			if ( gmdate( 'Y', $time ) !== gmdate( 'Y', $now ) ) {
				return self::date( $time, 'date_time' );
			}
			return self::date( $time, 'day_time' );
		}

		/**
		 * The admin language as a BCP 47 tag for the browser's Intl, with the store's order for English ( en-GB when an English admin
		 * uses a day-first date format ).
		 *
		 * @return string
		 */
		public static function locale_tag() {
			$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$tag    = str_replace( '_', '-', (string) $locale );
			if ( 0 === strpos( $tag, 'en' ) ) {
				$order = self::order();
				if ( 'dmy' === $order && in_array( $tag, array( 'en', 'en-US' ), true ) ) {
					$tag = 'en-GB';
				} elseif ( 'mdy' === $order && in_array( $tag, array( 'en-GB', 'en-AU', 'en-NZ', 'en-ZA', 'en-IE' ), true ) ) {
					$tag = 'en-US';
				}
			}
			return '' !== $tag ? $tag : 'en-US';
		}

		/**
		 * The moment.js formats for a day and a date ( Reports' date picker and range ).
		 *
		 * @return array day, date.
		 */
		public static function moment_formats() {
			$order = self::order();
			$dot   = ( 'dmy' === $order && 1 === preg_match( '/[dj]\./', (string) get_option( 'date_format', 'F j, Y' ) ) );
			if ( 'ymd' === $order ) {
				return array(
					'day'  => 'MM-DD',
					'date' => 'YYYY-MM-DD',
				);
			}
			if ( 'dmy' === $order ) {
				return array(
					'day'  => $dot ? 'D. MMM' : 'D MMM',
					'date' => $dot ? 'D. MMM YYYY' : 'D MMM YYYY',
				);
			}
			return array(
				'day'  => 'MMM D',
				'date' => 'MMM D, YYYY',
			);
		}

		/**
		 * What the browser needs to print dates the same way.
		 *
		 * @return array order, locale, moment ( day, date ), hour12.
		 */
		public static function config() {
			$time = (string) get_option( 'time_format', 'g:i a' );
			return array(
				'order'  => self::order(),
				'locale' => self::locale_tag(),
				'moment' => self::moment_formats(),
				'hour12' => ( 1 === preg_match( '/[gh]/', preg_replace( '/\\\\./u', '', $time ) ) ),
			);
		}

		/**
		 * A script that gives moment WordPress's month and weekday names and first day of the week ( as WordPress's own moment has ),
		 * for the copy of moment Reports loads.
		 *
		 * @return string
		 */
		public static function moment_locale_script() {
			global $wp_locale;
			if ( ! is_object( $wp_locale ) || empty( $wp_locale->month ) ) {
				return '';
			}
			$data = array(
				'months'        => array_values( $wp_locale->month ),
				'monthsShort'   => array_values( $wp_locale->month_abbrev ),
				'weekdays'      => array_values( $wp_locale->weekday ),
				'weekdaysShort' => array_values( $wp_locale->weekday_abbrev ),
				'weekdaysMin'   => array_values( $wp_locale->weekday_initial ),
				'week'          => array( 'dow' => (int) get_option( 'start_of_week', 0 ) ),
			);
			return 'if ( window.moment && moment.updateLocale ) { moment.updateLocale( "en", ' . wp_json_encode( $data ) . ' ); }';
		}
	}

endif;

if ( ! function_exists( 'wp_easycart_admin_date' ) ) {
	/**
	 * A date for the admin in the store's order and language ( wp_easycart_dates::date() ).
	 *
	 * @since 6.0.5
	 * @param int|string $time  Wall clock timestamp or date string.
	 * @param string     $style day | date | long | time | day_time | date_time | day_clock | weekday | weekday_time | month | month_year.
	 * @return string
	 */
	function wp_easycart_admin_date( $time, $style = 'date' ) {
		return wp_easycart_dates::date( $time, $style );
	}
}
