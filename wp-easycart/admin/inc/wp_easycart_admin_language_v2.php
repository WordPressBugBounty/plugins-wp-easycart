<?php
/**
 * WP EasyCart Admin — Language ( V2 settings page ) helpers + AJAX.
 *
 * Backs admin/template/settings/language-editor.php. The page is a registry page
 * with one real option ( ec_option_language ) and two render callables:
 * the installed-languages block and the storefront phrase editor. Phrases live
 * in ec_option_language_data ( see inc/classes/core/ec_language.php ), not in
 * options, so they save through the ecv2_language_* AJAX handlers below.
 *
 * Loaded from admin/admin-init.php ( so the handlers exist on admin-ajax ) and
 * required again from the declaration ( so the render callables exist when
 * the registry loads it outside the admin bootstrap, e.g. the map generator ).
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_language_v2' ) ) :

class wp_easycart_admin_language_v2 {

	/** Language group edited from Settings › Email ( order receipt phrases ). */
	const RECEIPT_GROUP = 'cart_success';

	/** Shipped-file decode cache: file => object|false. */
	private static $shipped = array();

	/* ------------------------------------------------------------------ */
	/* Data                                                                */
	/* ------------------------------------------------------------------ */

	/** The language store, with its data loaded. Null when the core class is unavailable. */
	public static function language() {
		if ( ! function_exists( 'wp_easycart_language' ) ) {
			return null;
		}
		$language = wp_easycart_language();
		if ( ! is_object( $language->get_language_data() ) && method_exists( $language, 'update_selected_language' ) ) {
			$language->update_selected_language();
		}
		return $language;
	}

	/** Decoded ec_option_language_data, or null. */
	public static function data() {
		$language = self::language();
		if ( ! $language ) {
			return null;
		}
		$data = $language->get_language_data();
		return is_object( $data ) ? $data : null;
	}

	/** The language file the storefront reads. */
	public static function active() {
		$file = function_exists( 'get_option' ) ? sanitize_key( (string) get_option( 'ec_option_language' ) ) : '';
		return '' !== $file ? $file : 'en-us';
	}

	/** English names for the shipped files ( the file's own label is shown beside it ). */
	public static function names() {
		return array(
			'en-us'  => __( 'English (US)', 'wp-easycart' ),
			'es'     => __( 'Spanish', 'wp-easycart' ),
			'greek'  => __( 'Greek', 'wp-easycart' ),
			'lv-lat' => __( 'Latvian', 'wp-easycart' ),
			'dutch'  => __( 'Dutch', 'wp-easycart' ),
			'ch-tr'  => __( 'Chinese (Traditional)', 'wp-easycart' ),
			'ru-rus' => __( 'Russian', 'wp-easycart' ),
			'german' => __( 'German', 'wp-easycart' ),
			'hu-hun' => __( 'Hungarian', 'wp-easycart' ),
			'fr-fr'  => __( 'French', 'wp-easycart' ),
			'da-dk'  => __( 'Danish', 'wp-easycart' ),
		);
	}

	/** "German — Deutsch" style display name for a file, from the English map and the file's own label. */
	public static function name( $file, $label = '' ) {
		$names = self::names();
		$label = trim( (string) $label );
		$name  = isset( $names[ $file ] ) ? $names[ $file ] : $file;
		if ( '' !== $label && strtolower( $label ) !== strtolower( $name ) ) {
			$name .= ' — ' . $label;
		}
		return $name;
	}

	/** Installed languages: file => display name. */
	public static function installed() {
		$out  = array();
		$data = self::data();
		if ( ! $data ) {
			return $out;
		}
		foreach ( get_object_vars( $data ) as $file => $entry ) {
			$file = sanitize_key( $file );
			if ( '' === $file || ! is_object( $entry ) ) {
				continue;
			}
			$out[ $file ] = self::name( $file, isset( $entry->label ) ? $entry->label : '' );
		}
		return $out;
	}

	/** Options for the ec_option_language select ( installed languages ). Safe outside WordPress. */
	public static function language_options() {
		$options = self::installed();
		if ( empty( $options ) ) {
			$active = self::active();
			$options[ $active ] = self::name( $active );
		}
		return $options;
	}

	/** Shipped translation files ( inc/language/*.txt ), sorted. */
	public static function shipped_files() {
		$files = array();
		foreach ( (array) glob( EC_PLUGIN_DIRECTORY . '/inc/language/*.txt' ) as $path ) {
			$file = sanitize_key( pathinfo( $path, PATHINFO_FILENAME ) );
			if ( '' !== $file ) {
				$files[] = $file;
			}
		}
		sort( $files );
		return $files;
	}

	/** Decoded shipped file, or false. */
	public static function shipped( $file ) {
		$file = sanitize_key( $file );
		if ( isset( self::$shipped[ $file ] ) ) {
			return self::$shipped[ $file ];
		}
		$path = EC_PLUGIN_DIRECTORY . '/inc/language/' . $file . '.txt';
		$json = ( '' !== $file && file_exists( $path ) ) ? json_decode( (string) file_get_contents( $path ) ) : null; // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local plugin file.
		self::$shipped[ $file ] = ( is_object( $json ) && isset( $json->options ) ) ? $json : false;
		return self::$shipped[ $file ];
	}

	/** Shipped default value for one phrase, or null when the file does not carry it. */
	public static function shipped_value( $file, $group, $key ) {
		$json = self::shipped( $file );
		if ( ! $json || ! isset( $json->options->{$group}->options->{$key}->value ) ) {
			return null;
		}
		return (string) $json->options->{$group}->options->{$key}->value;
	}

	/**
	 * Every save of the language store re-escapes quotes in every phrase
	 * ( save_language_data() ), so stored values with quotes grow backslashes.
	 * Collapse them for display and comparison.
	 */
	public static function display( $value ) {
		return preg_replace( '/\\\\+"/', '"', (string) $value );
	}

	/** Comparison form: entities decoded, quotes collapsed, whitespace folded. */
	public static function norm( $value ) {
		$value = html_entity_decode( self::display( $value ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/** The legacy store filter ( update_language_item() ), for values not coming from a slashed POST. */
	public static function clean( $value ) {
		$value = (string) $value;
		if ( function_exists( 'wp_easycart_escape_html' ) ) {
			$value = wp_easycart_escape_html( $value );
		} else {
			$value = wp_kses_post( $value );
		}
		return htmlspecialchars( $value, ENT_NOQUOTES, 'UTF-8' );
	}

	/**
	 * Areas of one installed language, in file order:
	 * slug => array( 'label', 'total', 'changed', 'phrases' => array( key => array( 'title', 'value', 'default', 'changed' ) ) ).
	 */
	public static function areas( $file ) {
		$out  = array();
		$data = self::data();
		$file = sanitize_key( $file );
		if ( ! $data || ! isset( $data->{$file}->options ) || ! is_object( $data->{$file}->options ) ) {
			return $out;
		}
		foreach ( get_object_vars( $data->{$file}->options ) as $group => $section ) {
			$group = sanitize_key( $group );
			if ( '' === $group || ! is_object( $section ) || ! isset( $section->options ) || ! is_object( $section->options ) ) {
				continue;
			}
			$phrases = array();
			$changed = 0;
			foreach ( get_object_vars( $section->options ) as $key => $item ) {
				$key = sanitize_key( $key );
				if ( '' === $key || ! is_object( $item ) ) {
					continue;
				}
				$value   = isset( $item->value ) ? (string) $item->value : '';
				$default = self::shipped_value( $file, $group, $key );
				$is_changed = ( null !== $default ) && ( self::norm( $value ) !== self::norm( $default ) );
				if ( $is_changed ) {
					$changed++;
				}
				$phrases[ $key ] = array(
					'title'   => isset( $item->title ) ? (string) $item->title : $key,
					'value'   => $value,
					'default' => $default,
					'changed' => $is_changed,
				);
			}
			$out[ $group ] = array(
				'label'   => isset( $section->label ) ? (string) $section->label : $group,
				'total'   => count( $phrases ),
				'changed' => $changed,
				'phrases' => $phrases,
			);
		}
		return $out;
	}

	public static function export_url( $file ) {
		return admin_url( 'admin.php?page=wp-easycart-settings&subpage=language-editor&ec_action=export-language&ec_language=' . rawurlencode( $file ) );
	}

	public static function email_url() {
		return admin_url( 'admin.php?page=wp-easycart-settings&subpage=email-setup#ecst-sec-receipt-wording' );
	}

	/* ------------------------------------------------------------------ */
	/* Option side effect                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Ported from wp_easycart_admin_language_editor::process_update_selected_language():
	 * after the storefront language changes, pull any phrases added in this
	 * release into every installed language and clear caches.
	 */
	public static function on_save_language( $value, $old, $field ) {
		$language = self::language();
		if ( $language ) {
			$language->update_language_data();
		}
		wp_cache_flush();
	}

	/* ------------------------------------------------------------------ */
	/* Rendering                                                           */
	/* ------------------------------------------------------------------ */

	/** Page-level 'enqueue' callable: runs from admin_enqueue_scripts via the renderer, never from a render callable. */
	public static function enqueue( $page = null ) {
		$css = plugins_url( '/admin/css/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		$js  = plugins_url( '/admin/js/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
		wp_enqueue_style( 'wp_easycart_admin_settings_language_v2_css', $css . 'settings-language-v2.css', array( 'wp_easycart_admin_settings_page_v2_css' ), EC_CURRENT_VERSION );
		wp_enqueue_script( 'wp_easycart_admin_settings_language_v2_js', $js . 'settings-language-v2.js', array( 'jquery', 'wp_easycart_admin_settings_page_v2_js' ), EC_CURRENT_VERSION, true );
		wp_localize_script( 'wp_easycart_admin_settings_language_v2_js', 'ecl_vars', array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => class_exists( 'wp_easycart_admin_settings_registry' ) ? wp_create_nonce( wp_easycart_admin_settings_registry::NONCE ) : '',
			'active' => self::active(),
			'i18n'   => array(
				'saving'        => __( 'Saving…', 'wp-easycart' ),
				'saved'         => __( 'Saved', 'wp-easycart' ),
				'save_failed'   => __( 'Could not save. Retry', 'wp-easycart' ),
				'loading'       => __( 'Loading phrases…', 'wp-easycart' ),
				'reset_title'   => __( 'Reset this phrase to the shipped wording?', 'wp-easycart' ),
				'reset_text'    => __( 'Your edit is discarded and the phrase goes back to the default text.', 'wp-easycart' ),
				'reset_area'    => __( 'Reset every phrase in “%s”?', 'wp-easycart' ),
				'reset_area_tx' => __( 'All %d edited phrases in this area go back to the shipped wording. This cannot be undone.', 'wp-easycart' ),
				'reset_done'    => __( 'Phrase reset.', 'wp-easycart' ),
				'area_done'     => __( 'Reset %d phrases.', 'wp-easycart' ),
				'area_none'     => __( 'Nothing to reset in this area.', 'wp-easycart' ),
				'remove_title'  => __( 'Remove %s?', 'wp-easycart' ),
				'remove_text'   => __( 'Every edited phrase in this language is deleted. You can add the language again later, with the shipped wording.', 'wp-easycart' ),
				'reloading'     => __( 'Storefront language changed. Reloading…', 'wp-easycart' ),
				'no_match'      => __( 'No phrases match.', 'wp-easycart' ),
				'changed_n'     => __( '%d changed', 'wp-easycart' ),
				'shown_n'       => __( '%1$d of %2$d phrases', 'wp-easycart' ),
				'error'         => __( 'Request failed.', 'wp-easycart' ),
			),
		) );
	}

	/** Languages section: installed list ( export / remove ) + add-a-language. */
	public static function render_languages( $page = null, $section = null ) {
		$installed = self::installed();
		$active    = self::active();
		$shipped   = self::shipped_files();
		$addable   = array();
		foreach ( $shipped as $file ) {
			if ( ! isset( $installed[ $file ] ) ) {
				$json = self::shipped( $file );
				$addable[ $file ] = self::name( $file, ( $json && isset( $json->label ) ) ? $json->label : '' );
			}
		}
		?>
		<div class="ecl-langs" id="ecl_langs">
			<div class="ecl-langs-head"><b><?php esc_html_e( 'Installed languages', 'wp-easycart' ); ?></b><span><?php esc_html_e( 'Each one keeps its own set of phrases. Export downloads the file you can send to a translator or another store.', 'wp-easycart' ); ?></span></div>
			<?php if ( empty( $installed ) ) : ?>
				<p class="ecl-muted"><?php esc_html_e( 'No language is installed yet. Add one below.', 'wp-easycart' ); ?></p>
			<?php endif; ?>
			<div class="ecl-lang-list">
				<?php foreach ( $installed as $file => $name ) : ?>
					<?php $is_active = ( $file === $active ); ?>
					<div class="ecl-lang<?php echo $is_active ? ' is-active' : ''; ?>" data-file="<?php echo esc_attr( $file ); ?>">
						<span class="ecl-lang-name"><?php echo esc_html( $name ); ?></span>
						<code class="ecl-lang-file"><?php echo esc_html( $file ); ?></code>
						<span class="ecv2-chip ecv2-chip-brand ecl-active-chip"<?php echo $is_active ? '' : ' hidden'; ?>><?php esc_html_e( 'Storefront', 'wp-easycart' ); ?></span>
						<span class="ecst-grow"></span>
						<a class="ecv2-btn ecv2-btn-sm" href="<?php echo esc_url( self::export_url( $file ) ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Export', 'wp-easycart' ); ?></a>
						<button type="button" class="ecv2-btn ecv2-btn-sm ecst-btn-danger ecl-remove" data-file="<?php echo esc_attr( $file ); ?>" data-name="<?php echo esc_attr( $name ); ?>"<?php echo $is_active ? ' disabled title="' . esc_attr__( 'Switch the storefront language first.', 'wp-easycart' ) . '"' : ''; ?>><?php esc_html_e( 'Remove', 'wp-easycart' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="ecl-add">
				<label for="ecl_add_select"><b><?php esc_html_e( 'Add a language', 'wp-easycart' ); ?></b></label>
				<?php if ( empty( $addable ) ) : ?>
					<span class="ecl-muted"><?php esc_html_e( 'Every translation that ships with WP EasyCart is already installed.', 'wp-easycart' ); ?></span>
				<?php else : ?>
					<select id="ecl_add_select" class="ecv2-select">
						<?php foreach ( $addable as $file => $name ) : ?>
							<option value="<?php echo esc_attr( $file ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm ecl-accent" id="ecl_add_btn"><?php esc_html_e( 'Add', 'wp-easycart' ); ?></button>
					<span class="ecl-muted"><?php esc_html_e( 'Installs the shipped translation so you can pick it for the storefront and edit its phrases.', 'wp-easycart' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** Storefront phrases section: the editor for the storefront language ( switchable ). */
	public static function render_phrases( $page = null, $section = null ) {
		/* Legacy language.php forced this option on whenever the page loaded; keep the side effect. */
		if ( ! get_option( 'ec_option_use_seperate_language_forms' ) ) {
			update_option( 'ec_option_use_seperate_language_forms', 1 );
		}
		echo self::editor_html( self::active() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in editor_html().
	}

	/** The whole editor ( toolbar, area pills, groups, rows ) for one language, as HTML. */
	public static function editor_html( $file ) {
		$file      = sanitize_key( $file );
		$installed = self::installed();
		$active    = self::active();
		if ( ! isset( $installed[ $file ] ) ) {
			$file = isset( $installed[ $active ] ) ? $active : (string) key( $installed );
		}
		$is_active = ( $file === $active );
		$areas     = self::areas( $file );
		$total     = 0;
		$changed   = 0;
		foreach ( $areas as $group => $area ) {
			if ( $is_active && self::RECEIPT_GROUP === $group ) {
				continue;
			}
			$total   += $area['total'];
			$changed += $area['changed'];
		}
		ob_start();
		?>
		<div class="ecl" id="ecl" data-language="<?php echo esc_attr( $file ); ?>" data-active="<?php echo esc_attr( $active ); ?>" data-total="<?php echo (int) $total; ?>">
			<div class="ecl-toolbar">
				<label class="ecl-editing" for="ecl_lang"><span><?php esc_html_e( 'Editing', 'wp-easycart' ); ?></span>
					<select id="ecl_lang" class="ecv2-select">
						<?php foreach ( $installed as $f => $name ) : ?>
							<option value="<?php echo esc_attr( $f ); ?>"<?php selected( $f, $file ); ?>><?php echo esc_html( $name ); ?><?php echo ( $f === $active ) ? esc_html( ' · ' . __( 'storefront', 'wp-easycart' ) ) : ''; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="ecl-search" for="ecl_search">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="search" id="ecl_search" placeholder="<?php echo esc_attr( sprintf( __( 'Search %d phrases', 'wp-easycart' ), $total ) ); ?>" autocomplete="off" />
				</label>
				<?php if ( $is_active ) : ?>
					<span class="ecv2-chip ecv2-chip-brand"><?php esc_html_e( 'Shown on the storefront', 'wp-easycart' ); ?></span>
				<?php else : ?>
					<span class="ecv2-chip ecv2-chip-gray" title="<?php esc_attr_e( 'Pick it as the storefront language above to show these phrases to shoppers.', 'wp-easycart' ); ?>"><?php esc_html_e( 'Not shown on the storefront', 'wp-easycart' ); ?></span>
				<?php endif; ?>
				<span class="ecst-grow"></span>
				<span class="ecl-count" id="ecl_count"></span>
				<label class="ecl-changed-only"><input type="checkbox" id="ecl_changed_only" /><span><?php esc_html_e( 'Changed only', 'wp-easycart' ); ?></span><span class="ecv2-chip ecv2-chip-amber" id="ecl_changed_count"><?php echo esc_html( sprintf( __( '%d changed', 'wp-easycart' ), $changed ) ); ?></span></label>
			</div>
			<div class="ecl-pills" id="ecl_pills" role="tablist">
				<button type="button" class="ecl-pill is-on" data-area=""><?php esc_html_e( 'All areas', 'wp-easycart' ); ?> <b><?php echo (int) $total; ?></b></button>
				<?php foreach ( $areas as $group => $area ) : ?>
					<?php if ( $is_active && self::RECEIPT_GROUP === $group ) { continue; } ?>
					<button type="button" class="ecl-pill<?php echo $area['changed'] > 0 ? ' has-changed' : ''; ?>" data-area="<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $area['label'] ); ?> <b><?php echo (int) $area['total']; ?></b></button>
				<?php endforeach; ?>
			</div>
			<div class="ecl-body" id="ecl_body">
				<?php foreach ( $areas as $group => $area ) : ?>
					<?php if ( $is_active && self::RECEIPT_GROUP === $group ) : ?>
						<div class="ecl-group ecl-group-note" data-area="<?php echo esc_attr( $group ); ?>">
							<div class="ecl-group-head">
								<b><?php echo esc_html( $area['label'] ); ?></b>
								<span class="ecl-group-n"><?php echo esc_html( sprintf( __( '%d phrases', 'wp-easycart' ), $area['total'] ) ); ?></span>
								<span class="ecst-grow"></span>
								<a class="ecst-link" href="<?php echo esc_url( self::email_url() ); ?>"><?php esc_html_e( 'Edited under Settings › Email →', 'wp-easycart' ); ?></a>
							</div>
						</div>
						<?php continue; ?>
					<?php endif; ?>
					<div class="ecl-group" data-area="<?php echo esc_attr( $group ); ?>" data-total="<?php echo (int) $area['total']; ?>">
						<div class="ecl-group-head">
							<b><?php echo esc_html( $area['label'] ); ?></b>
							<code class="ecl-key"><?php echo esc_html( $group ); ?></code>
							<span class="ecl-group-n"><span class="ecl-group-total"><?php echo esc_html( sprintf( __( '%d phrases', 'wp-easycart' ), $area['total'] ) ); ?></span><span class="ecl-group-changed"<?php echo $area['changed'] > 0 ? '' : ' hidden'; ?>> · <?php echo esc_html( sprintf( __( '%d changed', 'wp-easycart' ), $area['changed'] ) ); ?></span></span>
							<span class="ecst-grow"></span>
							<button type="button" class="ecv2-btn ecv2-btn-sm ecst-btn-danger ecl-reset-area" data-area="<?php echo esc_attr( $group ); ?>" data-label="<?php echo esc_attr( $area['label'] ); ?>"<?php echo $area['changed'] > 0 ? '' : ' hidden'; ?>><?php esc_html_e( 'Reset area', 'wp-easycart' ); ?></button>
						</div>
						<?php foreach ( $area['phrases'] as $key => $phrase ) : ?>
							<?php self::row_html( $file, $group, $area['label'], $key, $phrase ); ?>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
				<div class="ecl-empty" id="ecl_empty" hidden><?php esc_html_e( 'No phrases match.', 'wp-easycart' ); ?></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** One phrase row. Carries data-key / data-search so the page filter box also matches phrases. */
	private static function row_html( $file, $group, $group_label, $key, $phrase ) {
		$value   = self::display( $phrase['value'] );
		$default = ( null === $phrase['default'] ) ? '' : self::display( $phrase['default'] );
		$rows    = max( 1, min( 6, (int) ceil( strlen( $value ) / 70 ) ) );
		$sbase   = strtolower( $phrase['title'] . ' ' . $key . ' ' . $group_label . ' ' . $group );
		$search  = $sbase . ' ' . strtolower( $value );
		$row_key = 'ecl__' . $group . '__' . $key;
		?>
		<div class="ecst-row ecl-row" id="ecst-<?php echo esc_attr( $row_key ); ?>" data-key="<?php echo esc_attr( $row_key ); ?>" data-search="<?php echo esc_attr( $search ); ?>" data-sbase="<?php echo esc_attr( $sbase ); ?>" data-area="<?php echo esc_attr( $group ); ?>" data-phrase="<?php echo esc_attr( $key ); ?>" data-changed="<?php echo $phrase['changed'] ? '1' : '0'; ?>" data-has-default="<?php echo ( null === $phrase['default'] ) ? '0' : '1'; ?>">
			<div class="ecl-row-text">
				<span class="ecl-title"><?php echo esc_html( $phrase['title'] ); ?></span>
				<code class="ecl-key"><?php echo esc_html( $key ); ?></code>
				<span class="ecl-flags"><span class="ecv2-chip ecv2-chip-amber ecl-chip"<?php echo $phrase['changed'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Changed', 'wp-easycart' ); ?></span><button type="button" class="ecst-link ecl-reset"<?php echo $phrase['changed'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Reset to default', 'wp-easycart' ); ?></button></span>
			</div>
			<div class="ecl-row-control">
				<textarea class="ecv2-input ecl-input" rows="<?php echo (int) $rows; ?>" aria-label="<?php echo esc_attr( $phrase['title'] ); ?>" spellcheck="true"><?php echo esc_textarea( $value ); ?></textarea>
				<div class="ecl-row-foot">
					<span class="ecl-state"></span>
					<span class="ecl-default"<?php echo $phrase['changed'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Default:', 'wp-easycart' ); ?> <em><?php echo esc_html( $default ); ?></em></span>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* AJAX ( every handler opens with ecv2_settings_guard(): cap + nonce ) */
	/* ------------------------------------------------------------------ */

	/** Installed language named in the request, or dies. Called after ecv2_settings_guard(). */
	private static function posted_language() {
		$file = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
		$installed = self::installed();
		if ( '' === $file || ! isset( $installed[ $file ] ) ) {
			wp_send_json_error( array( 'message' => __( 'That language is not installed.', 'wp-easycart' ) ) );
		}
		return $file;
	}

	/** Area + phrase key named in the request, checked against the stored data. Dies when unknown. */
	private static function posted_phrase( $file, $need_key = true ) {
		$group = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
		$key   = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by ecv2_settings_guard() in the calling handler.
		$data  = self::data();
		if ( '' === $group || ! $data || ! isset( $data->{$file}->options->{$group}->options ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown phrase area.', 'wp-easycart' ) ) );
		}
		if ( $need_key && ( '' === $key || ! isset( $data->{$file}->options->{$group}->options->{$key} ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown phrase.', 'wp-easycart' ) ) );
		}
		return array( $group, $key );
	}

	/** Summary of one area after a write, for the row chips. */
	private static function area_summary( $file, $group ) {
		$areas = self::areas( $file );
		return isset( $areas[ $group ] ) ? array( 'total' => $areas[ $group ]['total'], 'changed' => $areas[ $group ]['changed'] ) : array( 'total' => 0, 'changed' => 0 );
	}

	/** POST language → the editor HTML for that installed language. */
	public static function ajax_editor() {
		ecv2_settings_guard();
		$file = self::posted_language();
		wp_send_json_success( array( 'html' => self::editor_html( $file ), 'language' => $file ) );
	}

	/** POST language, group, key, value → wp_easycart_language()->update_language_item(). */
	public static function ajax_save_phrase() {
		ecv2_settings_guard();
		$file = self::posted_language();
		list( $group, $key ) = self::posted_phrase( $file );
		/* Legacy path: update_language_item() unslashes the posted value itself and filters it through wp_easycart_escape_html() + htmlspecialchars(). */
		$raw = isset( $_POST['value'] ) && is_scalar( $_POST['value'] ) ? (string) $_POST['value'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- unslashed and sanitized ( wp_easycart_escape_html ) inside update_language_item() below, exactly like the legacy save.
		if ( strlen( $raw ) > 20000 ) {
			wp_send_json_error( array( 'message' => __( 'That phrase is too long.', 'wp-easycart' ) ) );
		}
		$language = self::language();
		if ( ! $language ) {
			wp_send_json_error( array( 'message' => __( 'Language data is not available.', 'wp-easycart' ) ) );
		}
		$language->update_language_item( $file, $group, $key, $raw );
		wp_cache_flush();
		$areas  = self::areas( $file );
		$phrase = isset( $areas[ $group ]['phrases'][ $key ] ) ? $areas[ $group ]['phrases'][ $key ] : array( 'value' => '', 'changed' => false );
		wp_send_json_success( array(
			'value'   => self::display( html_entity_decode( $phrase['value'], ENT_QUOTES, 'UTF-8' ) ),
			'changed' => (bool) $phrase['changed'],
			'area'    => array( 'total' => $areas[ $group ]['total'], 'changed' => $areas[ $group ]['changed'] ),
		) );
	}

	/** POST language, group, key → phrase back to the shipped wording. */
	public static function ajax_reset_phrase() {
		ecv2_settings_guard();
		$file = self::posted_language();
		list( $group, $key ) = self::posted_phrase( $file );
		$default = self::shipped_value( $file, $group, $key );
		if ( null === $default ) {
			wp_send_json_error( array( 'message' => __( 'This phrase has no shipped default to go back to.', 'wp-easycart' ) ) );
		}
		$language = self::language();
		$data     = self::data();
		if ( ! $language || ! $data ) {
			wp_send_json_error( array( 'message' => __( 'Language data is not available.', 'wp-easycart' ) ) );
		}
		$data->{$file}->options->{$group}->options->{$key}->value = self::clean( $default );
		$language->save_language_data();
		wp_cache_flush();
		wp_send_json_success( array(
			'value'   => self::display( $default ),
			'changed' => false,
			'area'    => self::area_summary( $file, $group ),
			'message' => __( 'Phrase reset.', 'wp-easycart' ),
		) );
	}

	/** POST language, group → every edited phrase in the area back to the shipped wording. */
	public static function ajax_reset_area() {
		ecv2_settings_guard();
		$file = self::posted_language();
		list( $group ) = self::posted_phrase( $file, false );
		$language = self::language();
		$data     = self::data();
		if ( ! $language || ! $data ) {
			wp_send_json_error( array( 'message' => __( 'Language data is not available.', 'wp-easycart' ) ) );
		}
		$areas  = self::areas( $file );
		$values = array();
		if ( isset( $areas[ $group ] ) ) {
			foreach ( $areas[ $group ]['phrases'] as $key => $phrase ) {
				if ( ! $phrase['changed'] || null === $phrase['default'] ) {
					continue;
				}
				$data->{$file}->options->{$group}->options->{$key}->value = self::clean( $phrase['default'] );
				$values[ $key ] = self::display( $phrase['default'] );
			}
		}
		if ( ! empty( $values ) ) {
			$language->save_language_data();
			wp_cache_flush();
		}
		wp_send_json_success( array(
			'values'  => $values,
			'area'    => self::area_summary( $file, $group ),
			'message' => empty( $values ) ? __( 'Nothing to reset in this area.', 'wp-easycart' ) : sprintf( __( 'Reset %d phrases.', 'wp-easycart' ), count( $values ) ),
		) );
	}

	/** POST language ( a shipped file not yet installed ) → wp_easycart_language()->add_new_language(). */
	public static function ajax_add_language() {
		ecv2_settings_guard();
		$file = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : '';
		if ( '' === $file || ! in_array( $file, self::shipped_files(), true ) || ! self::shipped( $file ) ) {
			wp_send_json_error( array( 'message' => __( 'That translation does not ship with WP EasyCart.', 'wp-easycart' ) ) );
		}
		$installed = self::installed();
		if ( isset( $installed[ $file ] ) ) {
			wp_send_json_error( array( 'message' => __( 'That language is already installed.', 'wp-easycart' ) ) );
		}
		$language = self::language();
		if ( ! $language ) {
			wp_send_json_error( array( 'message' => __( 'Language data is not available.', 'wp-easycart' ) ) );
		}
		$language->add_new_language( $file );
		wp_cache_flush();
		wp_send_json_success( array( 'message' => __( 'The new language was added successfully.', 'wp-easycart' ), 'reload' => true ) );
	}

	/** POST language ( installed, not the storefront one ) → wp_easycart_language()->remove_language(). */
	public static function ajax_remove_language() {
		ecv2_settings_guard();
		$file = self::posted_language();
		if ( $file === self::active() ) {
			wp_send_json_error( array( 'message' => __( 'This is the storefront language. Pick another storefront language first, then remove this one.', 'wp-easycart' ) ) );
		}
		$language = self::language();
		if ( ! $language ) {
			wp_send_json_error( array( 'message' => __( 'Language data is not available.', 'wp-easycart' ) ) );
		}
		$language->remove_language( $file );
		wp_cache_flush();
		wp_send_json_success( array( 'message' => __( 'The language was deleted.', 'wp-easycart' ), 'reload' => true ) );
	}

	/** Register the AJAX handlers once ( the file is included from admin-init.php and from the declaration ). */
	public static function register() {
		if ( ! function_exists( 'add_action' ) || has_action( 'wp_ajax_ecv2_language_save_phrase', array( __CLASS__, 'ajax_save_phrase' ) ) ) {
			return;
		}
		add_action( 'wp_ajax_ecv2_language_editor', array( __CLASS__, 'ajax_editor' ) );
		add_action( 'wp_ajax_ecv2_language_save_phrase', array( __CLASS__, 'ajax_save_phrase' ) );
		add_action( 'wp_ajax_ecv2_language_reset_phrase', array( __CLASS__, 'ajax_reset_phrase' ) );
		add_action( 'wp_ajax_ecv2_language_reset_area', array( __CLASS__, 'ajax_reset_area' ) );
		add_action( 'wp_ajax_ecv2_language_add', array( __CLASS__, 'ajax_add_language' ) );
		add_action( 'wp_ajax_ecv2_language_remove', array( __CLASS__, 'ajax_remove_language' ) );
	}
}

wp_easycart_admin_language_v2::register();

endif;
