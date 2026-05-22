<?php
/**
 * WP EasyCart Search Widget Display for Elementor
 *
 * Renders the search form directly (no shortcode) for full Elementor style control.
 * Uses a custom styled dropdown instead of native <datalist> for live search.
 *
 * @package  Wp_Easycart_Elementor_Search_Widget
 * @author   WP EasyCart
 */

$args = shortcode_atts(
	array(
		'label'                  => 'Search',
		'placeholder_text'       => 'Search products...',
		'postid'                 => 0,
		'enable_live_search'     => 'global',
		'live_search_max_results' => 8,
		'search_layout'          => 'inline',
		'show_search_icon'       => 'no',
		'search_icon'            => array( 'value' => 'fas fa-search', 'library' => 'fa-solid' ),
		'show_button_icon'       => 'no',
		'button_icon'            => array( 'value' => 'fas fa-search', 'library' => 'fa-solid' ),
		'button_icon_position'   => 'before',
	),
	$atts
);

// Translate if needed
if ( function_exists( 'wp_easycart_language' ) ) {
	$args['label'] = wp_easycart_language()->convert_text( $args['label'] );
	$args['placeholder_text'] = wp_easycart_language()->convert_text( $args['placeholder_text'] );
}

// Determine store page URL (same logic as load_ec_search in wpeasycart.php)
$storepageid = ( $args['postid'] ) ? (int) $args['postid'] : get_option( 'ec_option_storepage' );

if ( function_exists( 'icl_object_id' ) ) {
	$storepageid = icl_object_id( $storepageid, 'page', true, ICL_LANGUAGE_CODE );
}

$store_page = get_permalink( $storepageid );

if ( class_exists( 'WordPressHTTPS' ) && isset( $_SERVER['HTTPS'] ) ) {
	$https_class = new WordPressHTTPS();
	$store_page = $https_class->makeUrlHttps( $store_page );
}

// Determine live search state
$use_live_search = false;
if ( 'enable' === $args['enable_live_search'] ) {
	$use_live_search = true;
} elseif ( 'global' === $args['enable_live_search'] ) {
	$use_live_search = (bool) get_option( 'ec_option_use_live_search' );
}

$live_search_nonce = wp_create_nonce( 'wp-easycart-live-search' );
$max_results = (int) $args['live_search_max_results'];

// Layout class
$layout = $args['search_layout'];
$layout_class = 'ec_search_ele_layout_' . esc_attr( $layout );

// Icon helpers
$show_input_icon = ( 'yes' === $args['show_search_icon'] );
$show_button_icon = ( 'yes' === $args['show_button_icon'] );
$button_icon_position = $args['button_icon_position'];

// Unique ID for this widget instance (supports multiple on one page)
$widget_id = 'ec_search_ele_' . $this->get_id();

?>
<div class="ec_search_ele_wrapper" id="<?php echo esc_attr( $widget_id ); ?>">
	<form action="<?php echo esc_url( $store_page ); ?>" method="GET" class="ec_search_ele_form <?php echo esc_attr( $layout_class ); ?>">

		<div class="ec_search_ele_input_wrap<?php echo $show_input_icon ? ' ec_search_ele_has_icon' : ''; ?>">
			<?php if ( $show_input_icon ) : ?>
				<span class="ec_search_ele_input_icon">
					<?php \Elementor\Icons_Manager::render_icon( $args['search_icon'], array( 'aria-hidden' => 'true' ) ); ?>
				</span>
			<?php endif; ?>
			<input
				type="text"
				name="ec_search"
				class="ec_search_ele_input"
				autocomplete="off"
				placeholder="<?php echo esc_attr( $args['placeholder_text'] ); ?>"
			/>
		</div>

		<?php if ( $use_live_search ) : ?>
		<div class="ec_search_ele_dropdown" style="display:none;"></div>
		<?php endif; ?>

		<button type="submit" class="ec_search_ele_button">
			<?php if ( $show_button_icon && 'before' === $button_icon_position ) : ?>
				<?php \Elementor\Icons_Manager::render_icon( $args['button_icon'], array( 'aria-hidden' => 'true' ) ); ?>
			<?php endif; ?>

			<?php if ( ! $show_button_icon || 'only' !== $button_icon_position ) : ?>
				<span class="ec_search_ele_button_text"><?php echo esc_html( $args['label'] ); ?></span>
			<?php endif; ?>

			<?php if ( $show_button_icon && 'after' === $button_icon_position ) : ?>
				<?php \Elementor\Icons_Manager::render_icon( $args['button_icon'], array( 'aria-hidden' => 'true' ) ); ?>
			<?php endif; ?>

			<?php if ( $show_button_icon && 'only' === $button_icon_position ) : ?>
				<?php \Elementor\Icons_Manager::render_icon( $args['button_icon'], array( 'aria-hidden' => 'true' ) ); ?>
			<?php endif; ?>
		</button>

	</form>
</div>

<?php if ( $use_live_search ) : ?>
<script>
(function() {
	var widgetEl = document.getElementById('<?php echo esc_js( $widget_id ); ?>');
	if ( ! widgetEl ) return;

	var input      = widgetEl.querySelector('.ec_search_ele_input');
	var dropdown   = widgetEl.querySelector('.ec_search_ele_dropdown');
	var form       = widgetEl.querySelector('.ec_search_ele_form');
	var nonce      = '<?php echo esc_js( $live_search_nonce ); ?>';
	var ajaxUrl    = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
	var maxResults = <?php echo (int) $max_results; ?>;
	var debounce   = null;

	if ( ! input || ! dropdown ) return;

	/* ---- Keyboard / Input Events ---- */
	input.addEventListener('input', function() {
		clearTimeout( debounce );
		var val = input.value.trim();
		if ( val.length < 1 ) {
			hideDropdown();
			return;
		}
		debounce = setTimeout( function() {
			fetchResults( val );
		}, 250 );
	});

	/* ---- Hide on click outside ---- */
	document.addEventListener('click', function( e ) {
		if ( ! widgetEl.contains( e.target ) ) {
			hideDropdown();
		}
	});

	/* ---- Hide on Escape ---- */
	input.addEventListener('keydown', function( e ) {
		if ( e.key === 'Escape' ) {
			hideDropdown();
		}
	});

	/* ---- AJAX fetch (same endpoint as original) ---- */
	function fetchResults( val ) {
		var data = new FormData();
		data.append( 'action', 'ec_ajax_live_search' );
		data.append( 'nonce', nonce );
		data.append( 'search_val', val );

		fetch( ajaxUrl, {
			method: 'POST',
			body: data,
			credentials: 'same-origin'
		})
		.then( function( r ) { return r.json(); })
		.then( function( results ) {
			renderDropdown( results, val );
		})
		.catch( function() {
			hideDropdown();
		});
	}

	/* ---- Render dropdown items ---- */
	function renderDropdown( results, query ) {
		dropdown.innerHTML = '';

		if ( ! results || results.length === 0 ) {
			var empty = document.createElement('div');
			empty.className = 'ec_search_ele_dropdown_empty';
			empty.textContent = 'No results found';
			dropdown.appendChild( empty );
			dropdown.style.display = 'block';
			return;
		}

		var shown = Math.min( results.length, maxResults );
		for ( var i = 0; i < shown; i++ ) {
			var item = document.createElement('div');
			item.className = 'ec_search_ele_dropdown_item';

			// Search icon
			var icon = document.createElement('span');
			icon.className = 'ec_search_ele_dropdown_item_icon';
			icon.innerHTML = '<i class="fas fa-search" aria-hidden="true"></i>';
			item.appendChild( icon );

			// Text with bold matching portion
			var text = document.createElement('span');
			text.className = 'ec_search_ele_dropdown_item_text';
			var name = ( typeof results[i] === 'object' ) ? ( results[i].title || results[i].name || results[i] ) : results[i];
			text.innerHTML = highlightMatch( String( name ), query );
			item.appendChild( text );

			// Click to fill input and submit
			(function( n ) {
				item.addEventListener('click', function() {
					input.value = n;
					hideDropdown();
					form.submit();
				});
			})( typeof name === 'string' ? name : String( name ) );

			dropdown.appendChild( item );
		}

		dropdown.style.display = 'block';
	}

	/* ---- Highlight matching text ---- */
	function highlightMatch( text, query ) {
		var idx = text.toLowerCase().indexOf( query.toLowerCase() );
		if ( idx === -1 ) return escapeHtml( text );
		var before = text.substring( 0, idx );
		var match  = text.substring( idx, idx + query.length );
		var after  = text.substring( idx + query.length );
		return escapeHtml( before ) + '<strong>' + escapeHtml( match ) + '</strong>' + escapeHtml( after );
	}

	function escapeHtml( str ) {
		var div = document.createElement('div');
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	function hideDropdown() {
		dropdown.style.display = 'none';
		dropdown.innerHTML = '';
	}
})();
</script>
<?php endif; ?>