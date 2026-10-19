<?php
if ( ! class_exists( 'wp_easycart_language' ) ) :

	final class wp_easycart_language {

		protected static $_instance = null;

		/**
		 * Where the store's wording lives ( 6.0.4 ). Every installed language used to sit in one option, ec_option_language_data,
		 * which was autoloaded and decoded in full on every request ( 2.3 MB with all eleven languages: about 15 ms and 14 MB a
		 * decode, again for each EasyCart shortcode on a page; Site Health counted it as a critical issue ). Now each language has its
		 * own option, never autoloaded, and is decoded only when a phrase of it is needed; an autoloaded index names the languages
		 * and their codes. The combined option is still written when the wording is saved ( not autoloaded ), so a store that goes
		 * back to 6.0.3 keeps every language.
		 */
		const OPTION_COMBINED = 'ec_option_language_data';
		const OPTION_INDEX    = 'ec_option_language_index';
		const OPTION_PREFIX   = 'ec_option_language_data_';

		private static $selected_language;
		public static $language_code;

		/** @var object Language file => its data: only the languages loaded so far in this request ( 6.0.4 ). */
		public static $language_data;
		public static $languages;

		/** @var array|null Language file => language code, from OPTION_INDEX ( 6.0.4 ). */
		private static $index = null;

		/** @var array Language file => true: the languages load() has read in this request ( 6.0.4 ). */
		private static $loaded = array();

		/** @var bool $language_data holds every installed language ( load_all(), set_language_data(), migrate() ): a save writes them all ( 6.0.4 ). */
		private static $whole = false;

		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;

		}

		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_attr__( 'Cloning is forbidden.', 'wp-easycart' ), '5.8' );
		}

		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_attr__( 'Unserializing instances of this class is forbidden.', 'wp-easycart' ), '5.8' );
		}

		public function get_selected_language() {
			return self::$selected_language;
		}

		public function get_language_code() {
			return self::$language_code;
		}

		public function update_selected_language( $selected_language = 'NONE' ) {
			if ( $selected_language == 'NONE' ) {
				self::$selected_language = strtolower( get_option( 'ec_option_language' ) );
				if ( isset( $_GET['lang'] ) ) {
					self::$language_code = strtoupper( htmlspecialchars( sanitize_text_field( $_GET['lang'] ), ENT_QUOTES ) );
					if ( isset( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) && isset( $GLOBALS['ec_cart_data']->cart_data->translate_to ) ) {
						$GLOBALS['ec_cart_data']->cart_data->translate_to = self::$language_code;
						$GLOBALS['ec_cart_data']->save_session_to_db( );
					}
				} else if ( isset( $_COOKIE['ec_translate_to'] ) ) {
					self::$language_code = preg_replace( '/[^A-Z\-\_]/', '', strtoupper( sanitize_text_field( $_COOKIE['ec_translate_to'] ) ) );
					if ( (string) self::$language_code != '' && isset( $GLOBALS['ec_cart_data'] ) && isset( $GLOBALS['ec_cart_data']->cart_data ) && isset( $GLOBALS['ec_cart_data']->cart_data->translate_to ) ){
						$GLOBALS['ec_cart_data']->cart_data->translate_to = self::$language_code;
						$GLOBALS['ec_cart_data']->save_session_to_db( );
					} else {
						self::$language_code = "NEEDTOSET";
					}
				} else if( ! isset( $GLOBALS['ec_cart_data'] ) || ! isset( $GLOBALS['ec_cart_data']->cart_data ) && ! isset( $GLOBALS['ec_cart_data']->cart_data->translate_to ) || '' == $GLOBALS['ec_cart_data']->cart_data->translate_to ) {
					self::$language_code = "NEEDTOSET";
				}
			} else {
				self::$selected_language = strtolower( $selected_language );
				self::$language_code = strtoupper( $selected_language );
			}

			/* 6.0.4: the languages and their codes come from the index; a language's phrases are read when one is printed
			   ( load() ), once per request. This ran a full decode of every language on each call, and it is called on every
			   request and again by each shortcode. */
			self::index();
			if ( ! is_object( self::$language_data ) ) {
				self::$language_data = (object) array();
			}
			self::$languages = self::get_languages();

			if ( self::$language_code == "NEEDTOSET" ) {
				$codes = self::codes();
				$code  = isset( $codes[ self::$selected_language ] ) ? (string) $codes[ self::$selected_language ] : '';
				self::$language_code = ( '' !== $code ) ? strtoupper( $code ) : "NONE";
			} else {
				self::set_selected_language();
			}
		}

		/**
		 * The installed languages: language file => its language code ( e.g. 'en-us' => 'EN' ). The first request after an update
		 * from 6.0.3 or older builds it from the combined option ( migrate() ).
		 *
		 * @since 6.0.4
		 * @return array
		 */
		private static function index() {
			if ( is_array( self::$index ) ) {
				return self::$index;
			}
			$stored = get_option( self::OPTION_INDEX );
			if ( is_array( $stored ) && isset( $stored['files'] ) && is_array( $stored['files'] ) ) {
				self::$index = $stored['files'];
				return self::$index;
			}
			self::migrate();
			return is_array( self::$index ) ? self::$index : array();
		}

		/**
		 * Language file => language code for the languages in hand: the loaded set when it is the whole set ( it may have been
		 * replaced or edited in this request ), else the index.
		 *
		 * @since 6.0.4
		 * @return array
		 */
		private static function codes() {
			if ( ! self::$whole ) {
				return self::index();
			}
			$codes = array();
			foreach ( get_object_vars( self::$language_data ) as $file => $data ) {
				$codes[ $file ] = self::code_of( $data );
			}
			return $codes;
		}

		/**
		 * Split the combined option into one option per language and the index, and stop autoloading the combined option ( it
		 * stays, for a downgrade ). A store with no wording yet, or wording that cannot be read, gets the English file, as before.
		 *
		 * @since 6.0.4
		 */
		private static function migrate() {
			$raw  = get_option( self::OPTION_COMBINED );
			$data = is_string( $raw ) ? json_decode( html_entity_decode( $raw ) ) : null;
			self::$index = array();
			if ( ! is_object( $data ) ) {
				self::set_whole( self::instance()->get_new_language_data() );
				self::instance()->save_language_data();
				return;
			}
			self::set_whole( $data );
			self::write_languages( md5( $raw ) );
			self::stop_autoloading_combined();
		}

		/**
		 * After a downgrade and back: an older WP EasyCart reads and writes only the combined option, so wording edited there is
		 * newer than the per-language copies. The index keeps a hash of the combined option as it was last written here; when
		 * the option no longer matches it, its copy wins. Asked by update_language_data() ( every update, the language editor ).
		 *
		 * @since 6.0.4
		 */
		private static function resync() {
			$stored = get_option( self::OPTION_INDEX );
			if ( ! is_array( $stored ) || ! isset( $stored['combined'] ) || '' === (string) $stored['combined'] ) {
				return;
			}
			$raw = get_option( self::OPTION_COMBINED );
			if ( ! is_string( $raw ) || '' === $raw || md5( $raw ) === (string) $stored['combined'] ) {
				return;
			}
			$data = json_decode( html_entity_decode( $raw ) );
			if ( ! is_object( $data ) || ! get_object_vars( $data ) ) {
				return;
			}
			$previous = is_array( $stored['files'] ) ? array_keys( $stored['files'] ) : array();
			self::set_whole( $data );
			self::write_languages( md5( $raw ) );
			self::forget_removed( $previous );
			self::stop_autoloading_combined();
		}

		/**
		 * Take a whole set of languages as $language_data ( every language in it counts as loaded ).
		 *
		 * @since 6.0.4
		 * @param object $data Language file => its data.
		 */
		private static function set_whole( $data ) {
			self::$language_data = is_object( $data ) ? $data : (object) array();
			self::$loaded        = array_fill_keys( array_keys( get_object_vars( self::$language_data ) ), true );
			self::$whole         = true;
		}

		/**
		 * Read one language's phrases into $language_data, once per request: its own option, else ( missing or unreadable ) the
		 * combined option's copy, which is then written as its own.
		 *
		 * @since 6.0.4
		 * @param string $file Language file ( e.g. 'en-us' ).
		 * @return bool Whether the language is loaded.
		 */
		private static function load( $file ) {
			$file  = (string) $file;
			$index = self::index();
			if ( ! is_object( self::$language_data ) ) {
				self::$language_data = (object) array();
			}
			if ( '' === $file ) {
				return false;
			}
			if ( isset( self::$loaded[ $file ] ) || self::$whole ) {
				return isset( self::$language_data->{$file} );
			}
			self::$loaded[ $file ] = true;
			if ( ! array_key_exists( $file, $index ) ) {
				return false;
			}
			$raw  = get_option( self::option_name( $file ) );
			$data = ( is_string( $raw ) && '' !== $raw ) ? json_decode( html_entity_decode( $raw ) ) : null;
			if ( ! is_object( $data ) ) {
				$all = self::instance()->get_decoded_language_data();
				if ( is_object( $all ) && isset( $all->{$file} ) && is_object( $all->{$file} ) ) {
					$data = $all->{$file};
					update_option( self::option_name( $file ), json_encode( $data ), false );
				}
			}
			if ( ! is_object( $data ) ) {
				return false;
			}
			self::$language_data->{$file} = $data;
			return true;
		}

		/**
		 * Every installed language, in the index's order ( the language editor and the saves need them all ). The object stays
		 * the same one, so data a caller already holds ( get_language_data() ) sees the languages added.
		 *
		 * @since 6.0.4
		 */
		private static function load_all() {
			if ( self::$whole ) {
				return;
			}
			$index = self::index();
			foreach ( array_keys( $index ) as $file ) {
				self::load( $file );
			}
			$current = get_object_vars( self::$language_data );
			$ordered = array();
			foreach ( array_keys( $index ) as $file ) {
				if ( isset( $current[ $file ] ) ) {
					$ordered[ $file ] = $current[ $file ];
				}
			}
			foreach ( $current as $file => $data ) {
				if ( ! isset( $ordered[ $file ] ) ) {
					$ordered[ $file ] = $data;
				}
			}
			if ( array_keys( $ordered ) !== array_keys( $current ) ) {
				foreach ( array_keys( $current ) as $file ) {
					unset( self::$language_data->{$file} );
				}
				foreach ( $ordered as $file => $data ) {
					self::$language_data->{$file} = $data;
				}
			}
			self::$whole = true;
		}

		/**
		 * Write every language in $language_data to its own option ( not autoloaded ) and the index ( autoloaded: a few hundred
		 * bytes ). $language_data is the whole set here.
		 *
		 * @since 6.0.4
		 * @param string $combined md5 of the combined option as it is now stored.
		 */
		private static function write_languages( $combined ) {
			$index = array();
			foreach ( get_object_vars( self::$language_data ) as $file => $data ) {
				if ( ! is_object( $data ) ) {
					continue;
				}
				update_option( self::option_name( $file ), json_encode( $data ), false );
				$index[ $file ] = self::code_of( $data );
			}
			self::$index = $index;
			update_option( self::OPTION_INDEX, array( 'version' => 1, 'files' => $index, 'combined' => (string) $combined ), true );
		}

		/**
		 * Delete the options of languages no longer installed.
		 *
		 * @since 6.0.4
		 * @param array $previous The language files installed before.
		 */
		private static function forget_removed( $previous ) {
			foreach ( (array) $previous as $file ) {
				if ( ! isset( self::$index[ $file ] ) ) {
					delete_option( self::option_name( $file ) );
				}
			}
		}

		/**
		 * A language's own option name: the prefix and its file name, or a hash of a file name that is not plain.
		 *
		 * @since 6.0.4
		 * @param string $file Language file.
		 * @return string
		 */
		private static function option_name( $file ) {
			$file = (string) $file;
			return self::OPTION_PREFIX . ( preg_match( '/^[a-z0-9_-]{1,64}$/', $file ) ? $file : md5( $file ) );
		}

		/**
		 * A language's code ( its language_code section ), or ''.
		 *
		 * @since 6.0.4
		 * @param object $data One language's data.
		 * @return string
		 */
		private static function code_of( $data ) {
			return ( is_object( $data ) && isset( $data->options->language_code->options->code->value ) ) ? (string) $data->options->language_code->options->code->value : '';
		}

		/**
		 * Take the combined option out of the autoloaded options ( stores that have it from before WordPress 6.6 kept it autoloaded ).
		 *
		 * @since 6.0.4
		 */
		private static function stop_autoloading_combined() {
			if ( false === get_option( self::OPTION_COMBINED, false ) ) {
				return;
			}
			if ( function_exists( 'wp_set_option_autoload' ) ) {
				wp_set_option_autoload( self::OPTION_COMBINED, false );
				return;
			}
			global $wpdb;
			$wpdb->update( $wpdb->options, array( 'autoload' => 'no' ), array( 'option_name' => self::OPTION_COMBINED ) );
			wp_cache_delete( 'alloptions', 'options' );
		}

		/**
		 * Delete every language option ( uninstall ): each language's own option and the index. The combined option is in
		 * ec_wpoptionset's list.
		 *
		 * @since 6.0.4
		 */
		public static function delete_storage() {
			global $wpdb;
			$stored = get_option( self::OPTION_INDEX );
			if ( is_array( $stored ) && isset( $stored['files'] ) && is_array( $stored['files'] ) ) {
				foreach ( array_keys( $stored['files'] ) as $file ) {
					delete_option( self::option_name( $file ) );
				}
			}
			delete_option( self::OPTION_INDEX );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::OPTION_PREFIX ) . '%' ) );
			self::$index         = null;
			self::$loaded        = array();
			self::$whole         = false;
			self::$language_data = (object) array();
		}

		public function set_language( $code ) {
			self::$language_code = strtoupper( htmlspecialchars( $code, ENT_QUOTES ) );
			$GLOBALS['ec_cart_data']->cart_data->translate_to = self::$language_code;
			self::set_selected_language();
		}

		public function set_language_data( $language_data ) {
			/* 6.0.4: the given data is the whole set of languages ( the setup wizard passes every language back, edited ). Data
			   that is not an object is ignored: saving it used to stop with an error. */
			if ( is_object( $language_data ) ) {
				self::set_whole( $language_data );
			}
		}

		public function get_language_section( $file_name, $key_section ) {
			self::load( $file_name );
			return self::$language_data->{$file_name}->options->{$key_section};
		}

		public function add_new_language( $file_name ) {
			self::load_all();
			self::$language_data->{$file_name} = self::get_language_file_decoded( $file_name . ".txt" );
			self::save_language_data();
			self::$languages = self::get_languages();
		}

		public function update_language_data() {
			self::resync();
			self::load_all();
			self::$languages = self::get_languages();
			if ( isset( $_POST['isupdate'] ) ) {
				$language_file = sanitize_text_field( $_POST['file_name'] );
				$language_section = sanitize_key( $_POST['key_section'] );
				foreach ( (array) $_POST['ec_language_field'] as $key => $value ) { // XSS OK. Forced array and each item sanitized.
					self::$language_data->{$language_file}->options->{$language_section}->options->{sanitize_key( $key )}->value = htmlspecialchars( wp_easycart_escape_html( stripslashes( $value ) ), ENT_NOQUOTES, "UTF-8" );
				}
			}
			$file_names = self::get_language_file_list();
			foreach ( $file_names as $file_name ) {
				if ( in_array( $file_name, self::$languages ) )
					self::update_language_entry( $file_name );
			}
			self::save_language_data();
			self::$languages = self::get_languages();
		}

		public function update_language_item( $language_file, $language_section, $key, $value ) {
			self::load_all();
			self::$language_data->{$language_file}->options->{$language_section}->options->{$key}->value = htmlspecialchars( wp_easycart_escape_html( stripslashes( $value ) ), ENT_NOQUOTES, "UTF-8" );
			self::save_language_data();
			self::$languages = self::get_languages();
		}

		public function remove_language( $file_name ) {
			self::load_all();
			unset( self::$language_data->{$file_name} );
			self::save_language_data();
			self::$languages = self::get_languages();
		}

		public function get_languages_array() {
			return self::$languages;
		}

		/**
		 * Every installed language's data ( 6.0.4: all of them are read first; edit the object returned, then save_language_data() ).
		 *
		 * @return object
		 */
		public function get_language_data() {
			self::load_all();
			return self::$language_data;
		}

		private function set_selected_language() {
			foreach ( self::codes() as $file => $item_language_code ) {
				if ( strtoupper( self::$language_code ) == strtoupper( $item_language_code ) ) {
					self::$selected_language = strtolower( $file );
					break;
				}
			}
		}

		private function get_languages() {
			return array_keys( self::codes() );
		}

		private function get_new_language_data() {
			$language_data = (object) array();
			$file_names = self::get_language_file_list();
			for( $i=0; $i<count( $file_names ); $i++ ) {
				if ( $file_names[$i] == "en-us" ) {
					$language_data->{$file_names[$i]} = self::get_language_file_decoded( $file_names[$i] . ".txt" );
				}
			}
			return $language_data;
		}

		public function get_language_file_list() {
			$file_names = array();
			$dir = EC_PLUGIN_DIRECTORY . "/inc/language/";
			$handle = opendir( $dir );
			while( false !== ( $file = readdir( $handle ) ) ) {
				$extension = pathinfo( $file, PATHINFO_EXTENSION );
				$name = pathinfo( $file, PATHINFO_FILENAME );
				if ( $extension == "txt" )
				$file_names[] = $name;
			}
			return $file_names;
		}

		private function get_language_file_decoded( $file_name ) {
			return json_decode( self::get_language_file_contents( $file_name ) );
		}

		private function get_language_file_contents( $file_name ) {
			if ( !file_exists( EC_PLUGIN_DIRECTORY . "/inc/language/" . $file_name ) ) {
				return '';
			}
			ob_start();
			include EC_PLUGIN_DIRECTORY . "/inc/language/" . $file_name;
			$contents = ob_get_clean();
			return $contents;
		}

		public function get_text( $lang_section, $lang_var ) {
			$text = null;
			if ( ! isset( self::$loaded[ (string) self::$selected_language ] ) ) {
				self::load( (string) self::$selected_language ); /* 6.0.4: only the storefront's language is read, once a request */
			}
			if ( isset( self::$language_data->{self::$selected_language} ) &&
				isset( self::$language_data->{self::$selected_language}->options->{$lang_section} ) &&
				isset( self::$language_data->{self::$selected_language}->options->{$lang_section}->options->{$lang_var} ) )
				$text = str_replace( "[terms]", "<a href=\"" . esc_url_raw( stripslashes( get_option( 'ec_option_terms_link' ) ) ) . "\" target=\"_blank\">", str_replace( "[/terms]", "</a>", str_replace( "[privacy]", "<a href=\"" . esc_url_raw( stripslashes( get_option( 'ec_option_privacy_link' ) ) ) . "\" target=\"_blank\">", str_replace( "[/privacy]", "</a>", wp_easycart_escape_html( self::$language_data->{self::$selected_language}->options->{$lang_section}->options->{$lang_var}->value ) ) ) ) );
			/**
			 * The store's wording for one phrase, as templates print it ( HTML ). The Elementor cart, checkout and account widgets
			 * answer their own "Texts" settings here while they draw; a callback returns HTML that is safe to print.
			 *
			 * @since 6.0.2
			 * @param string|null $text         The phrase ( null when the language file has none ).
			 * @param string      $lang_section Language section.
			 * @param string      $lang_var     Phrase key.
			 */
			return apply_filters( 'wp_easycart_language_text', $text, $lang_section, $lang_var );
		}

		public function convert_text( $text ) {
			if ( self::$language_code != "NONE" && $text != null && preg_match_all( '/[\[][a-zA-Z][a-zA-Z][\]]|[\[][\/][a-zA-Z][a-zA-Z][\]]/', $text, $matches ) > 0 ) {
				$text_arr = preg_split( '/[\[][\/]|[\[]|[\]]/', $text );
				$texts = array();
				for( $i=1; $i<count( $text_arr ); $i++ ) {
					$key = strtoupper( $text_arr[$i] );
					$val = $text_arr[($i+1)];
					$texts[$key] = $val;
					$i = $i + 3;
				}
				if ( isset( $texts[ strtoupper( self::$language_code ) ] ) ) {
					return wp_easycart_escape_html( $texts[ strtoupper( self::$language_code ) ] );
				}
			}
			return wp_easycart_escape_html( $text );
		}

		public function export_language( $language_key ) {
			self::load( $language_key );
			$download_content = json_encode( self::$language_data->{$language_key} );
			header( "Cache-Control: public, must-revalidate" );
			header( "Pragma: no-cache" );
			header( "Content-Type: text/plain" );
			header( "Content-Length: " . strlen( $download_content ) );
			header( 'Content-Disposition: attachment; filename="' . $language_key . '.txt"' );
			header( "Content-Transfer-Encoding: binary\n" );
			echo json_encode( self::$language_data->{$language_key} );
		}

		private function update_language_entry( $file_name ) {
			$new_language_object = self::get_language_file_decoded( $file_name . ".txt" );
			$new_array = ( $new_language_object && isset( $new_language_object->options ) ) ? get_object_vars( $new_language_object->options ) : array();
			$new_keys = array_keys( $new_array );

			$current_language_object = self::$language_data->{$file_name};
			$current_array = get_object_vars( $current_language_object->options );
			$current_keys = array_keys( $current_array );

			foreach ( $new_keys as $new_key ) {
				if ( !in_array( $new_key, $current_keys ) ) {
					self::$language_data->{$file_name}->options->{$new_key} = $new_language_object->options->{$new_key};
				} else {
					$new_sub_array = get_object_vars( $new_language_object->options->{$new_key}->options );
					$new_sub_keys = array_keys( $new_sub_array );

					$current_sub_array = get_object_vars( $current_language_object->options->{$new_key}->options );
					$current_sub_keys = array_keys( $current_sub_array );

					foreach ( $new_sub_keys as $new_sub_key ) {
						if ( !in_array( $new_sub_key, $current_sub_keys ) ) {
							self::$language_data->{$file_name}->options->{$new_key}->options->{$new_sub_key} = $new_language_object->options->{$new_key}->options->{$new_sub_key};
						} else {
							self::refresh_phrase( $file_name, $new_key, $new_sub_key, $new_language_object->options->{$new_key}->options->{$new_sub_key} );
						}
					}
				}

			}

		}

		/**
		 * A phrase the store never changed takes the language file's wording. Phrases added in 6.0.2 were copied into every
		 * language file in English, and a few translations had lost a [token] the code fills in; a store that merged them keeps
		 * that copy, because updates only add missing keys. A phrase still reading as the English ( en-us.txt ), or as one of
		 * those broken translations ( replaced_phrases() ), is replaced; anything a store wrote itself stays. 6.0.5: English too, for
		 * the phrases replaced_phrases() lists ( the order button ).
		 *
		 * @since 6.0.3
		 * @param string $file_name Language file ( without .txt ).
		 * @param string $section   Section key.
		 * @param string $key       Phrase key.
		 * @param object $new_item  The phrase in the language file.
		 */
		private function refresh_phrase( $file_name, $section, $key, $new_item ) {
			if ( ! is_object( $new_item ) || ! isset( $new_item->value ) ) {
				return;
			}
			$replaced = self::replaced_phrases();
			$id       = $file_name . ' ' . $section . '.' . $key;
			if ( 'en-us' === $file_name && ! isset( $replaced[ $id ] ) ) {
				return;
			}
			$current = self::$language_data->{$file_name}->options->{$section}->options->{$key};
			if ( ! is_object( $current ) || ! isset( $current->value ) ) {
				return;
			}
			$now = self::phrase_text( $current->value );
			if ( self::phrase_text( $new_item->value ) === $now ) {
				return;
			}
			$english    = ( 'en-us' === $file_name ) ? null : self::english_phrase( $section, $key );
			$is_english = ( null !== $english && self::phrase_text( $english ) === $now );
			$is_broken  = ( isset( $replaced[ $id ] ) && in_array( md5( $now ), (array) $replaced[ $id ], true ) );
			if ( $is_english || $is_broken ) {
				$current->value = $new_item->value;
			}
		}

		/**
		 * A stored phrase as text, for comparing: without the backslashes saves put before quotes, entities decoded.
		 *
		 * @param string $value Phrase.
		 * @return string
		 */
		private static function phrase_text( $value ) {
			return trim( html_entity_decode( preg_replace( '/\\\\+(?=")/', '', (string) $value ), ENT_QUOTES, 'UTF-8' ) );
		}

		/**
		 * A phrase's English in en-us.txt, or null.
		 *
		 * @param string $section Section key.
		 * @param string $key     Phrase key.
		 * @return string|null
		 */
		private function english_phrase( $section, $key ) {
			static $english = null;
			if ( null === $english ) {
				$english = self::get_language_file_decoded( 'en-us.txt' );
			}
			if ( is_object( $english ) && isset( $english->options->{$section}->options->{$key}->value ) ) {
				return $english->options->{$section}->options->{$key}->value;
			}
			return null;
		}

		/**
		 * Translations earlier language files shipped with a lost [token] ( the pickup date, the terms and privacy links, the
		 * product name ): md5 of the text as phrase_text() reads it, keyed "<file> <section>.<key>". 6.0.4: German's closing line
		 * under receipts, invoice PDFs and refund emails ( one merchant's bank details with a placeholder IBAN, a § 19 UStG note
		 * and a made-to-order notice, for the English "Thank you very much!" ), and guest checkout messages that lost their line
		 * break ( French, Greek and Dutch also their accents or question; Spanish was broken ). 6.0.5: the order button and the
		 * sentences that name it, which said only "submit order" ( "Bestellung absenden" ); the button must say that payment is
		 * owed ( § 312j(3) BGB, Art. 8(2) of the Consumer Rights Directive, UK CCR 2013 reg. 14(3) ). A value may list several.
		 *
		 * @return array
		 */
		private static function replaced_phrases() {
			return array(
				'german ec_errors.preorder_message'                                     => '18a4579ce8e8d1d9a2ab5f69f2c33b97',
				'ch-tr cart_payment_information.cart_payment_information_checkout_text' => 'd830353399e88695bc841f07921bce19',
				'ch-tr ec_success.store_added_to_cart'                                  => '9c6a3ba681288c7821731bb1ee7f7e9f',
				'fr-fr cart_payment_information.cart_payment_information_checkout_text' => array( '31abf776af2345cc6bfe5ac8a9172b87', 'd4142e8ad53e85241100aef023e2ffcc' ),
				'greek cart_payment_information.cart_payment_information_checkout_text' => '9d5b5848a531fbaea45d20723c2cae80',
				'german cart_success.cart_payment_complete_bottom_line_2'               => 'ccce1adc2c80883a7312ed32837857ec',
				'fr-fr cart_guest_checkout.cart_guest_message'                          => '88b097e712fc83183704f26bd30e3e54',
				'es cart_subscription_guest_checkout.cart_subscription_guest_message'   => '1a99e5959a0c316b2696aea3a92088d6',
				'greek cart_guest_checkout.cart_guest_message'                          => 'd6a8dd065c7bde899405ad4215201780',
				'dutch cart_guest_checkout.cart_guest_message'                          => '772072ed18ab2b864fda31faa0a76942',
				/* 6.0.5: the order button. */
				'en-us cart_payment_information.cart_payment_information_submit_order_button'   => '225a64509d5136bd7da6282bba5f5639',
				'en-us cart_payment_information.cart_payment_information_third_party_second'    => '5f94bdb077b28700b53c8162d1e8f22d',
				'en-us cart_payment_information.cart_payment_information_checkout_text'         => '22d4a41cf46a27763699f21387839827',
				'german cart_payment_information.cart_payment_information_submit_order_button'  => 'fc4d6b29cda457875b17c36f66725c7f',
				'german cart_payment_information.cart_payment_information_third_party_first'    => 'cba849a8a5e9c0aedf95145c77fefb90',
				'german cart_payment_information.cart_payment_information_third_party_second'   => 'f39000f985669ed33e5d31ec999023bd',
				'german cart_payment_information.cart_payment_information_checkout_text'        => '4c2ec20cf195378aabac5b032426849f',
				'da-dk cart_payment_information.cart_payment_information_submit_order_button'   => 'e00c36c5e664f973fbbffc6b4194613f',
				'da-dk cart_payment_information.cart_payment_information_third_party_second'    => '9a9ef6e4392a8690e4bffd795c8dd2d3',
				'dutch cart_payment_information.cart_payment_information_submit_order_button'   => '2a16437dd469b39bb91690596af82c07',
				'dutch cart_payment_information.cart_payment_information_third_party_second'    => 'c2b2e97b952a999d477a2ecfd9c95d47',
				'dutch cart_payment_information.cart_payment_information_checkout_text'         => 'c5bbc3dd618887dff688d65af5c0f8cf',
				'es cart_payment_information.cart_payment_information_submit_order_button'      => '2eea71091dd263c647e1f81eda7ac4f4',
				'es cart_payment_information.cart_payment_information_third_party_second'       => '7e434488078906e817c5ce560dd80c34',
				'es cart_payment_information.cart_payment_information_checkout_text'            => 'f2ecb4e1fce58f2e5aad0d0e4cfb2df6',
				'fr-fr cart_payment_information.cart_payment_information_submit_order_button'   => '8f1a114d4dd77a512552219d8ba00b72',
				'fr-fr cart_payment_information.cart_payment_information_third_party_first'     => '7d62f8587ed756c6b3c5a14fa697535a',
				'fr-fr cart_payment_information.cart_payment_information_third_party_second'    => '58bae5eea7da22db1df682162067ad20',
				'greek cart_payment_information.cart_payment_information_submit_order_button'   => 'b5e360cf9f3c556d8509173d1480cbb0',
				'hu-hun cart_payment_information.cart_payment_information_submit_order_button'  => '61fdb6f67a551c8ebbd0f0a8485b5934',
				'hu-hun cart_payment_information.cart_payment_information_third_party_second'   => '75d6841f78e0a05d6fb6ff80c15ca414',
				'hu-hun cart_payment_information.cart_payment_information_checkout_text'        => '0d37a7370b5da544780966468151112c',
				'lv-lat cart_payment_information.cart_payment_information_submit_order_button'  => '69efc1f3a763f63925ad961bf04a1273',
				'lv-lat cart_payment_information.cart_payment_information_third_party_second'   => '6656fea1b2a14c384db93511762b647c',
				'lv-lat cart_payment_information.cart_payment_information_checkout_text'        => 'e26cf5d69c8a6522f92b3d986a789a03',
				/* 6.0.5: Bill later is a bank transfer ( it read direct deposit, cash transfer or direct debit ). */
				'en-us cart_payment_information.cart_payment_information_manual_payment' => '626faf898a73038a3e9adf8ca4567b96', /* Pay by Direct Deposit */
				'en-us account_order_details.account_order_details_payment_method_manual' => '26597f68b42701c9a9ab0ba1512ffa5f', /* Direct Deposit */
				'es cart_payment_information.cart_payment_information_manual_payment' => '8e0eac764052276001f4b9258a9da281', /* Pago por Depósito Directo */
				'es account_order_details.account_order_details_payment_method_manual' => '51ebe26c67aea8d23531de0f4a64eefd', /* Depósito Directo */
				'fr-fr cart_payment_information.cart_payment_information_manual_payment' => '4f1131a9bc7d306caa1850ea9fd6dc5c', /* Paiement par depot direct */
				'fr-fr account_order_details.account_order_details_payment_method_manual' => '89d751ea7b3bba3d553d21d91759bd0a', /* Depot Direct */
				'hu-hun cart_payment_information.cart_payment_information_manual_payment' => 'af6843bb69b86c6e583ec3d7c9717d2e', /* Közvetlen fizetés */
				'hu-hun account_order_details.account_order_details_payment_method_manual' => '1c7e304e925e78d38f8195d900ab9060', /* Csoportos beszedéssel */
				'ch-tr cart_payment_information.cart_payment_information_manual_payment' => '65796d4544656f3260698186ca774d62', /* 現金轉賬 */
				'ch-tr account_order_details.account_order_details_payment_method_manual' => '65796d4544656f3260698186ca774d62', /* 現金轉賬 */
			);
		}

		private function add_new_language_file( $file_name ) {
			if ( !isset( self::$language_data->{$file_name} ) )
				self::$language_data->{$file_name} = self::get_language_file_decoded( $file_name . ".txt" );	
		}

		/**
		 * Save the wording: every language to its own option and the index, and ( 6.0.4 ) the combined option, which is no
		 * longer autoloaded and is kept only so an older WP EasyCart still finds every language.
		 */
		public function save_language_data() {
			self::load_all();
			$previous = is_array( self::$index ) ? array_keys( self::$index ) : array();
			foreach ( self::$language_data as $language_file => $files ) {
				foreach ( $files as $language_section => $sections ) {
					if ( is_array( $sections ) || is_object( $sections ) ) {
						foreach ( $sections as $key => $value ) {
							if ( isset( self::$language_data->{$language_file} ) && isset( self::$language_data->{$language_file}->options->{$language_section} ) && isset( self::$language_data->{$language_file}->options->{$language_section}->options->{$key} ) ) {
								self::$language_data->{$language_file}->options->{$language_section}->options->{$key}->value = str_replace( '"', '\"', $value->value );
							}
						}
					}
				}
			}
			update_option( self::OPTION_COMBINED, self::get_encoded_language_data(), false );
			/* The hash of what is stored now ( a write the database refused, e.g. over max_allowed_packet, leaves the older copy ),
			   so resync() only takes the combined option after another version wrote it. */
			$stored = get_option( self::OPTION_COMBINED );
			self::write_languages( md5( is_string( $stored ) ? $stored : '' ) );
			self::forget_removed( $previous );
		}

		private function get_encoded_language_data() {
			return json_encode( self::$language_data );
		}

		private function get_decoded_language_data() {
			$raw = get_option( self::OPTION_COMBINED );
			return is_string( $raw ) ? json_decode( html_entity_decode( $raw ) ) : null;
		}

		private function language_file_checker( $file_contents ) {
			self::check_bracket_count( $file_contents );
			self::check_for_quotes( $file_contents );
		}

		private function check_bracket_count( $file_contents ) {
			$open_count = substr_count( trim($file_contents), "{" );
			$close_count = substr_count( trim($file_contents), "}" );

			if ( $open_count > $close_count ) {
				throw new Exception( "Too many open brackets in language file." );
			} else if ( $close_count > $open_count ) {
				throw new Exception( "Too many closed brackets in language file." );
			}
		}

		private function check_for_quotes( $file_contents ) {
			$open_bracket_found = false;
			$open_paren_found = false;
			$closed_paren_found = false;
			for ( $i = 0; $i < strlen( $file_contents ); $i++) {
				$char = substr( $file_contents, $i, 1 );
				if ( $open_bracket_found && $char != '"' ) {
					throw new Exception( "Needed a paren after open bracket at character " . esc_attr( $i ) . "." );
				} else {
					$open_bracket_found = false;
				}
				if ( $open_paren_found && $char == '"' ) {
					$closed_paren_found = true;
					$open_paren_found = false;
				}
				if ( $closed_paren_found && $char != ':' && $char != ',' && $char != "}" ) {
					throw new Exception( "Expected a : or , or } after a closed paren at character " . esc_attr( $i ) . "." );
				} else {
					$closed_paren_found = false;	
				}
			}
		}

	}
endif;

function wp_easycart_language() {
	return wp_easycart_language::instance();
}
wp_easycart_language();
$GLOBALS['language'] = wp_easycart_language::instance();
