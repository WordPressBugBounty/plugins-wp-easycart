<?php
/**
 * Settings › Design ( V2 declaration ).
 *
 * Replaces the classic Design page ( admin/inc/wp_easycart_admin_design.php +
 * admin/template/settings/design/*.php ) and absorbs the cart icon and
 * newsletter popup rows from Additional Settings ( miscellaneous ).
 *
 * Two rows ( which menus show the cart icon, which parts of a product listing
 * are visible ) are 'multiselect' fields; their stored value is the chosen
 * option values joined by 'separator' ( ',' and '***' respectively ), exactly
 * as the classic handlers stored them.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ecv2_design_font_options' ) ) {
	/** Google font families from the bundled list, plus "theme default" and "custom". */
	function ecv2_design_font_options() {
		static $options = null;
		if ( null !== $options ) {
			return $options;
		}
		$options = array( '' => __( 'Theme default', 'wp-easycart' ) );
		$file = EC_PLUGIN_DIRECTORY . '/admin/template/settings/design/google-fonts.json';
		if ( file_exists( $file ) ) {
			$json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled plugin file, not a remote URL.
			if ( is_string( $json ) && preg_match_all( '/"family":\s*"([^"]+)"/', $json, $m ) ) {
				foreach ( $m[1] as $family ) {
					$options[ $family ] = $family;
				}
			}
		}
		$options['custom'] = __( 'Custom font ( enter the family below )', 'wp-easycart' );
		return $options;
	}
}

if ( ! function_exists( 'ecv2_design_folder_options' ) ) {
	/**
	 * Folders under wp-easycart-data/design/<kind>/ ( theme | layout ), same lookup as
	 * the classic page: the data directory when it exists, else the plugin's own.
	 */
	function ecv2_design_folder_options( $kind, $none_label ) {
		$options = array( '0' => $none_label );
		$dir = ( defined( 'EC_PLUGIN_DATA_DIRECTORY' ) && is_dir( EC_PLUGIN_DATA_DIRECTORY . '/' ) ) ? EC_PLUGIN_DATA_DIRECTORY . '/design/' . $kind . '/' : EC_PLUGIN_DIRECTORY . '/design/' . $kind . '/';
		if ( is_dir( $dir ) ) {
			$scan = scandir( $dir );
			if ( is_array( $scan ) ) {
				foreach ( $scan as $entry ) {
					if ( '.' !== $entry && '..' !== $entry && '0' !== $entry ) {
						$options[ $entry ] = $entry;
					}
				}
			}
		}
		return $options;
	}
}

if ( ! function_exists( 'ecv2_design_menu_options' ) ) {
	/**
	 * Registered theme menu locations plus every saved WordPress menu ( term_<id> ).
	 * Declared as an 'options' callable so get_terms() runs only when the Design page
	 * renders or the cart-icon row saves.
	 */
	function ecv2_design_menu_options() {
		$options = array();
		if ( function_exists( 'get_registered_nav_menus' ) ) {
			foreach ( (array) get_registered_nav_menus() as $location => $label ) {
				$options[ $location ] = (string) $label;
			}
		}
		if ( function_exists( 'get_terms' ) ) {
			$menus = get_terms( array( 'taxonomy' => 'nav_menu', 'hide_empty' => false ) );
			if ( is_array( $menus ) ) {
				foreach ( $menus as $menu ) {
					$options[ 'term_' . $menu->term_id ] = $menu->name;
				}
			}
		}
		return $options;
	}
}

if ( ! function_exists( 'ecv2_design_sanitize_css' ) ) {
	/** Store CSS as typed; only refuse sequences that could break out of the <style> block. */
	function ecv2_design_sanitize_css( $raw ) {
		$css = (string) $raw;
		if ( preg_match( '#<\s*/?\s*(style|script)\b#i', $css ) ) {
			return new WP_Error( 'css', __( 'Custom CSS cannot contain <style> or <script> tags. Enter CSS rules only.', 'wp-easycart' ) );
		}
		return $css;
	}
}

if ( ! function_exists( 'ecv2_design_sanitize_px' ) ) {
	/** Image heights are stored the classic way: digits followed by "px" ( or empty ). */
	function ecv2_design_sanitize_px( $raw ) {
		$digits = preg_replace( '/[^0-9]/', '', (string) $raw );
		return ( '' === $digits ) ? '' : $digits . 'px';
	}
}

$ecv2_design_columns_1_2 = array(
	'1' => __( '1 column', 'wp-easycart' ),
	'2' => __( '2 columns', 'wp-easycart' ),
);
$ecv2_design_columns_1_5 = array(
	'1' => __( '1 column', 'wp-easycart' ),
	'2' => __( '2 columns', 'wp-easycart' ),
	'3' => __( '3 columns', 'wp-easycart' ),
	'4' => __( '4 columns', 'wp-easycart' ),
	'5' => __( '5 columns', 'wp-easycart' ),
);

return array(
	'slug'        => 'design',
	'title'       => __( 'Design', 'wp-easycart' ),
	'description' => __( 'Colors, fonts, product grid layout, columns per screen size, the cart icon, the newsletter popup and your own CSS.', 'wp-easycart' ),
	'group'       => 'customize',
	'icon'        => 'admin-appearance',
	'docs'        => array( 'settings', 'design', 'settings' ),
	'legacy'      => array( 'design' ),
	'upsell'      => 'default',
	'sections'    => array(

		'colors' => array(
			'title'  => __( 'Store colors', 'wp-easycart' ),
			'hint'   => __( 'Brand colors, text on dark sites and corner style', 'wp-easycart' ),
			'fields' => array(
				'ec_option_details_main_color' => array(
					'type'     => 'color',
					'label'    => __( 'Main color', 'wp-easycart' ),
					'desc'     => __( 'Used for buttons, links and highlights across the store and in order emails.', 'wp-easycart' ),
					'default'  => '#222222',
					'keywords' => array( 'brand', 'primary', 'button', 'accent' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Store Colors', 'label' => 'Main Color' ),
				),
				'ec_option_details_second_color' => array(
					'type'     => 'color',
					'label'    => __( 'Second color', 'wp-easycart' ),
					'desc'     => __( 'Hover states and secondary accents.', 'wp-easycart' ),
					'default'  => '#666666',
					'keywords' => array( 'secondary', 'hover', 'accent' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Store Colors', 'label' => 'Second Color' ),
				),
				'ec_option_use_dark_bg' => array(
					'type'     => 'pills',
					'label'    => __( 'Text color', 'wp-easycart' ),
					'desc'     => __( 'Choose white text only if your site has a dark background.', 'wp-easycart' ),
					'default'  => '0',
					'options'  => array(
						'0' => __( 'Dark text', 'wp-easycart' ),
						'1' => __( 'White text', 'wp-easycart' ),
					),
					'keywords' => array( 'dark background', 'invert', 'white text', 'contrast' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Store Colors', 'label' => 'Invert Colors' ),
				),
				'ec_option_no_rounded_corners' => array(
					'type'     => 'toggle',
					'label'    => __( 'Square corners', 'wp-easycart' ),
					'desc'     => __( 'Removes rounded corners from buttons, images and boxes across the store to match a squarer theme.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'rounded', 'radius', 'corners', 'border' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Settings', 'label' => 'Enable Rounded Corners', 'note' => 'classic toggle was inverted ( on = rounded ); this toggle is on = square' ),
				),
				'ec_option_admin_color' => array(
					'type'     => 'color',
					'label'    => __( 'Admin accent color', 'wp-easycart' ),
					'desc'     => __( 'Color of the WP EasyCart admin menu, buttons and dashboard charts. Does not affect the storefront.', 'wp-easycart' ),
					'default'  => '#242424',
					'keywords' => array( 'admin', 'dashboard', 'menu', 'theme' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Store Colors', 'label' => 'Admin Color' ),
				),
			),
		),

		'typography' => array(
			'title'  => __( 'Typography', 'wp-easycart' ),
			'hint'   => __( 'The font used on every store element', 'wp-easycart' ),
			'fields' => array(
				'ec_option_font_main' => array(
					'type'     => 'select',
					'label'    => __( 'Store font', 'wp-easycart' ),
					'desc'     => __( 'A Google font loaded on store pages and applied to all EasyCart elements. Theme default leaves your theme\'s font in place.', 'wp-easycart' ),
					'default'  => '',
					'options'  => ecv2_design_font_options(),
					'keywords' => array( 'google font', 'typeface', 'font family' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Settings', 'label' => 'Font Selection' ),
				),
				'ec_option_font_custom' => array(
					'type'        => 'text',
					'label'       => __( 'Custom font family', 'wp-easycart' ),
					'desc'        => __( 'The CSS font-family name. The font itself must already be loaded by your theme or another plugin.', 'wp-easycart' ),
					'placeholder' => 'Helvetica Neue',
					'parent'      => 'ec_option_font_main',
					'show_when'   => 'custom',
					'keywords'    => array( 'font family', 'typeface', 'custom font' ),
					'legacy'      => array( 'page' => 'design', 'section' => 'Design Settings', 'label' => 'Font Family' ),
				),
			),
		),

		'product-listings' => array(
			'title'  => __( 'Product listings', 'wp-easycart' ),
			'hint'   => __( 'How products look on store and category pages', 'wp-easycart' ),
			'fields' => array(
				'ec_option_default_product_type' => array(
					'type'     => 'select',
					'label'    => __( 'Product layout', 'wp-easycart' ),
					'desc'     => __( 'The card style used for each product in a listing. Individual store pages can override it in the live editor.', 'wp-easycart' ),
					'default'  => '1',
					'options'  => array(
						'1' => __( 'Grid style 1', 'wp-easycart' ),
						'2' => __( 'Grid style 2', 'wp-easycart' ),
						'3' => __( 'Grid style 3', 'wp-easycart' ),
						'4' => __( 'Grid style 4', 'wp-easycart' ),
						'5' => __( 'Grid style 5', 'wp-easycart' ),
						'6' => __( 'List', 'wp-easycart' ),
					),
					'keywords' => array( 'grid', 'list', 'card', 'display type' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Product Display Type' ),
				),
				'ec_option_default_product_align' => array(
					'type'     => 'pills',
					'label'    => __( 'Content alignment', 'wp-easycart' ),
					'desc'     => __( 'Aligns the title, price and buttons inside each product card.', 'wp-easycart' ),
					'default'  => '',
					'options'  => array(
						''       => __( 'Default', 'wp-easycart' ),
						'left'   => __( 'Left', 'wp-easycart' ),
						'center' => __( 'Centre', 'wp-easycart' ),
						'right'  => __( 'Right', 'wp-easycart' ),
					),
					'keywords' => array( 'align', 'centre', 'left', 'right' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Product Alignment' ),
				),
				'ec_option_default_product_visible_options' => array(
					'type'      => 'multiselect',
					'separator' => ',',
					'label'     => __( 'Parts shown on each product', 'wp-easycart' ),
					'desc'      => __( 'Which elements appear on a product card in listings.', 'wp-easycart' ),
					'default'   => 'title,price,rating,cart',
					'options'   => array(
						'title'     => __( 'Title', 'wp-easycart' ),
						'price'     => __( 'Price', 'wp-easycart' ),
						'rating'    => __( 'Star rating', 'wp-easycart' ),
						'cart'      => __( 'Add to cart button', 'wp-easycart' ),
						'quickview' => __( 'Quick view button', 'wp-easycart' ),
						'desc'      => __( 'Short description', 'wp-easycart' ),
					),
					'keywords'  => array( 'visible', 'title', 'price', 'rating', 'add to cart' ),
					'legacy'    => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Product Visible Items' ),
				),
				'ec_option_default_product_image_hover_type' => array(
					'type'     => 'select',
					'label'    => __( 'Image hover effect', 'wp-easycart' ),
					'desc'     => __( 'What happens to the product image when a shopper hovers over it. Flip, crossfade, slide and flipbook use the product\'s extra images.', 'wp-easycart' ),
					'default'  => '3',
					'options'  => array(
						'1'  => __( 'Flip to second image', 'wp-easycart' ),
						'2'  => __( 'Crossfade to second image', 'wp-easycart' ),
						'3'  => __( 'Lighten', 'wp-easycart' ),
						'5'  => __( 'Grow', 'wp-easycart' ),
						'6'  => __( 'Shrink', 'wp-easycart' ),
						'7'  => __( 'Grey to color', 'wp-easycart' ),
						'8'  => __( 'Brighten', 'wp-easycart' ),
						'9'  => __( 'Slide', 'wp-easycart' ),
						'10' => __( 'Flipbook', 'wp-easycart' ),
						'4'  => __( 'No effect', 'wp-easycart' ),
					),
					'keywords' => array( 'hover', 'mouse over', 'rollover', 'animation' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Product Hover Type' ),
				),
				'ec_option_default_product_image_effect_type' => array(
					'type'     => 'pills',
					'label'    => __( 'Image frame', 'wp-easycart' ),
					'desc'     => __( 'A thin border or a drop shadow around each product image.', 'wp-easycart' ),
					'default'  => 'none',
					'options'  => array(
						'none'   => __( 'None', 'wp-easycart' ),
						'border' => __( 'Border', 'wp-easycart' ),
						'shadow' => __( 'Shadow', 'wp-easycart' ),
					),
					'keywords' => array( 'image', 'border', 'shadow', 'frame' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Product Image Style' ),
				),
				'ec_option_default_quick_view' => array(
					'type'     => 'toggle',
					'label'    => __( 'Quick view popup', 'wp-easycart' ),
					'desc'     => __( 'Shoppers can open a product summary in a popup from the listing without leaving the page.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'quick view', 'popup', 'modal', 'preview' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Enable Quick View' ),
				),
				'ec_option_default_product_border' => array(
					'type'     => 'toggle',
					'label'    => __( 'Border around each product', 'wp-easycart' ),
					'desc'     => __( 'Draws a box around every product card in listings.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'border', 'box', 'outline', 'card' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Enable Product Border' ),
				),
				'ec_option_default_product_rounded_corners' => array(
					'type'     => 'toggle',
					'label'    => __( 'Round the border corners', 'wp-easycart' ),
					'desc'     => __( 'Rounds the product card border. Pick which corners below.', 'wp-easycart' ),
					'default'  => 0,
					'parent'   => 'ec_option_default_product_border',
					'keywords' => array( 'rounded', 'radius', 'corners' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Enable Rounded Product Corners' ),
				),
				'ec_option_default_product_rounded_corners_tl' => array(
					'type'     => 'toggle',
					'label'    => __( 'Top-left corner', 'wp-easycart' ),
					'desc'     => __( 'Round the top-left corner of the product card.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_default_product_rounded_corners',
					'keywords' => array( 'rounded', 'corner', 'top left' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Rounded Corners: Top Left' ),
				),
				'ec_option_default_product_rounded_corners_tr' => array(
					'type'     => 'toggle',
					'label'    => __( 'Top-right corner', 'wp-easycart' ),
					'desc'     => __( 'Round the top-right corner of the product card.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_default_product_rounded_corners',
					'keywords' => array( 'rounded', 'corner', 'top right' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Rounded Corners: Top Right' ),
				),
				'ec_option_default_product_rounded_corners_bl' => array(
					'type'     => 'toggle',
					'label'    => __( 'Bottom-left corner', 'wp-easycart' ),
					'desc'     => __( 'Round the bottom-left corner of the product card.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_default_product_rounded_corners',
					'keywords' => array( 'rounded', 'corner', 'bottom left' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Rounded Corners: Bottom Left' ),
				),
				'ec_option_default_product_rounded_corners_br' => array(
					'type'     => 'toggle',
					'label'    => __( 'Bottom-right corner', 'wp-easycart' ),
					'desc'     => __( 'Round the bottom-right corner of the product card.', 'wp-easycart' ),
					'default'  => 1,
					'parent'   => 'ec_option_default_product_rounded_corners',
					'keywords' => array( 'rounded', 'corner', 'bottom right' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Rounded Corners: Bottom Right' ),
				),
				'ec_option_default_desktop_columns' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on desktop', 'wp-easycart' ),
					'desc'     => __( 'Products per row on wide screens. More columns means smaller images.', 'wp-easycart' ),
					'default'  => '3',
					'options'  => $ecv2_design_columns_1_5,
					'keywords' => array( 'columns', 'grid', 'desktop', 'per row' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Desktop Columns' ),
				),
				'ec_option_default_laptop_columns' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on tablet ( landscape )', 'wp-easycart' ),
					'desc'     => __( 'Products per row on a sideways tablet or small laptop.', 'wp-easycart' ),
					'default'  => '3',
					'advanced' => true,
					'options'  => $ecv2_design_columns_1_5,
					'keywords' => array( 'columns', 'tablet', 'landscape', 'laptop' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Tablet Landscape Columns' ),
				),
				'ec_option_default_tablet_wide_columns' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on tablet ( portrait )', 'wp-easycart' ),
					'desc'     => __( 'Products per row on an upright tablet.', 'wp-easycart' ),
					'default'  => '2',
					'advanced' => true,
					'options'  => $ecv2_design_columns_1_5,
					'keywords' => array( 'columns', 'tablet', 'portrait' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Tablet Portrait Columns' ),
				),
				'ec_option_default_tablet_columns' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on phone ( landscape )', 'wp-easycart' ),
					'desc'     => __( 'Products per row on a sideways phone.', 'wp-easycart' ),
					'default'  => '2',
					'advanced' => true,
					'options'  => $ecv2_design_columns_1_5,
					'keywords' => array( 'columns', 'phone', 'landscape', 'mobile' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Smartphone Landscape Columns' ),
				),
				'ec_option_default_smartphone_columns' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on phone', 'wp-easycart' ),
					'desc'     => __( 'Products per row on an upright phone.', 'wp-easycart' ),
					'default'  => '1',
					'options'  => $ecv2_design_columns_1_5,
					'keywords' => array( 'columns', 'phone', 'portrait', 'mobile' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Smartphone Portrait Columns' ),
				),
				'ec_option_default_dynamic_sizing' => array(
					'type'     => 'toggle',
					'label'    => __( 'Automatic image height', 'wp-easycart' ),
					'desc'     => __( 'Listing images keep their natural proportions. Turn off to crop every image to a fixed height per screen size.', 'wp-easycart' ),
					'default'  => 1,
					'keywords' => array( 'image height', 'crop', 'aspect ratio', 'dynamic' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Enable Dynamic Image Height' ),
				),
				'ec_option_default_desktop_image_height' => array(
					'type'        => 'text',
					'label'       => __( 'Image height on desktop', 'wp-easycart' ),
					'desc'        => __( 'Fixed height in pixels that listing images are cropped to on wide screens.', 'wp-easycart' ),
					'default'     => '310px',
					'placeholder' => '310px',
					'parent'      => 'ec_option_default_dynamic_sizing',
					'show_when'   => '0',
					'sanitize'    => 'ecv2_design_sanitize_px',
					'keywords'    => array( 'image height', 'crop', 'desktop' ),
					'legacy'      => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Desktop Image Height' ),
				),
				'ec_option_default_laptop_image_height' => array(
					'type'        => 'text',
					'label'       => __( 'Image height on tablet ( landscape )', 'wp-easycart' ),
					'desc'        => __( 'Fixed height in pixels on a sideways tablet or small laptop.', 'wp-easycart' ),
					'default'     => '310px',
					'placeholder' => '310px',
					'parent'      => 'ec_option_default_dynamic_sizing',
					'show_when'   => '0',
					'sanitize'    => 'ecv2_design_sanitize_px',
					'keywords'    => array( 'image height', 'crop', 'tablet', 'laptop' ),
					'legacy'      => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Tablet Landscape Image Height' ),
				),
				'ec_option_default_tablet_wide_image_height' => array(
					'type'        => 'text',
					'label'       => __( 'Image height on tablet ( portrait )', 'wp-easycart' ),
					'desc'        => __( 'Fixed height in pixels on an upright tablet.', 'wp-easycart' ),
					'default'     => '310px',
					'placeholder' => '310px',
					'parent'      => 'ec_option_default_dynamic_sizing',
					'show_when'   => '0',
					'sanitize'    => 'ecv2_design_sanitize_px',
					'keywords'    => array( 'image height', 'crop', 'tablet' ),
					'legacy'      => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Tablet Portrait Image Height' ),
				),
				'ec_option_default_tablet_image_height' => array(
					'type'        => 'text',
					'label'       => __( 'Image height on phone ( landscape )', 'wp-easycart' ),
					'desc'        => __( 'Fixed height in pixels on a sideways phone.', 'wp-easycart' ),
					'default'     => '380px',
					'placeholder' => '380px',
					'parent'      => 'ec_option_default_dynamic_sizing',
					'show_when'   => '0',
					'sanitize'    => 'ecv2_design_sanitize_px',
					'keywords'    => array( 'image height', 'crop', 'phone', 'mobile' ),
					'legacy'      => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Smartphone Landscape Image Height' ),
				),
				'ec_option_default_smartphone_image_height' => array(
					'type'        => 'text',
					'label'       => __( 'Image height on phone', 'wp-easycart' ),
					'desc'        => __( 'Fixed height in pixels on an upright phone.', 'wp-easycart' ),
					'default'     => '270px',
					'placeholder' => '270px',
					'parent'      => 'ec_option_default_dynamic_sizing',
					'show_when'   => '0',
					'sanitize'    => 'ecv2_design_sanitize_px',
					'keywords'    => array( 'image height', 'crop', 'phone', 'mobile' ),
					'legacy'      => array( 'page' => 'design', 'section' => 'Product Design Options', 'label' => 'Smartphone Portrait Image Height' ),
				),
			),
		),

		'product-page' => array(
			'title'  => __( 'Product page', 'wp-easycart' ),
			'hint'   => __( 'Gallery beside or above the details, per screen size', 'wp-easycart' ),
			'fields' => array(
				'ec_option_details_columns_desktop' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on desktop', 'wp-easycart' ),
					'desc'     => __( '2 columns puts the image gallery beside the details; 1 column stacks them.', 'wp-easycart' ),
					'default'  => '2',
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'product details', 'columns', 'desktop', 'gallery' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Details Design Options', 'label' => 'Product Details: Desktop Columns' ),
				),
				'ec_option_details_columns_laptop' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on tablet ( landscape )', 'wp-easycart' ),
					'desc'     => __( 'Gallery beside ( 2 ) or above ( 1 ) the details on a sideways tablet or small laptop.', 'wp-easycart' ),
					'default'  => '2',
					'advanced' => true,
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'product details', 'columns', 'tablet', 'laptop' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Details Design Options', 'label' => 'Product Details: Tablet Landscape Columns' ),
				),
				'ec_option_details_columns_tablet_wide' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on tablet ( portrait )', 'wp-easycart' ),
					'desc'     => __( 'Gallery beside ( 2 ) or above ( 1 ) the details on an upright tablet.', 'wp-easycart' ),
					'default'  => '1',
					'advanced' => true,
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'product details', 'columns', 'tablet', 'portrait' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Details Design Options', 'label' => 'Product Details: Tablet Portrait Columns' ),
				),
				'ec_option_details_columns_tablet' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on phone ( landscape )', 'wp-easycart' ),
					'desc'     => __( 'Gallery beside ( 2 ) or above ( 1 ) the details on a sideways phone.', 'wp-easycart' ),
					'default'  => '1',
					'advanced' => true,
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'product details', 'columns', 'phone', 'landscape' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Details Design Options', 'label' => 'Product Details: Smartphone Landscape Columns' ),
				),
				'ec_option_details_columns_smartphone' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on phone', 'wp-easycart' ),
					'desc'     => __( 'Gallery beside ( 2 ) or above ( 1 ) the details on an upright phone.', 'wp-easycart' ),
					'default'  => '1',
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'product details', 'columns', 'phone', 'mobile' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Product Details Design Options', 'label' => 'Product Details: Smartphone Portrait Columns' ),
				),
			),
		),

		'cart-checkout' => array(
			'title'  => __( 'Cart & checkout look', 'wp-easycart' ),
			'hint'   => __( 'Order summary beside or below the checkout form, per screen size', 'wp-easycart' ),
			'fields' => array(
				'ec_option_cart_columns_desktop' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on desktop', 'wp-easycart' ),
					'desc'     => __( '2 columns shows the order summary beside the checkout form; 1 column stacks them.', 'wp-easycart' ),
					'default'  => '2',
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'cart', 'checkout', 'columns', 'desktop' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Cart Design Options', 'label' => 'Cart Page Columns: Desktop' ),
				),
				'ec_option_cart_columns_laptop' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on tablet ( landscape )', 'wp-easycart' ),
					'desc'     => __( 'Summary beside ( 2 ) or below ( 1 ) the form on a sideways tablet or small laptop.', 'wp-easycart' ),
					'default'  => '2',
					'advanced' => true,
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'cart', 'checkout', 'columns', 'tablet' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Cart Design Options', 'label' => 'Cart Page Columns: Tablet Horizontal' ),
				),
				'ec_option_cart_columns_tablet_wide' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on tablet ( portrait )', 'wp-easycart' ),
					'desc'     => __( 'Summary beside ( 2 ) or below ( 1 ) the form on an upright tablet.', 'wp-easycart' ),
					'default'  => '1',
					'advanced' => true,
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'cart', 'checkout', 'columns', 'tablet' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Cart Design Options', 'label' => 'Cart Page Columns: Tablet Vertical' ),
				),
				'ec_option_cart_columns_tablet' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on phone ( landscape )', 'wp-easycart' ),
					'desc'     => __( 'Summary beside ( 2 ) or below ( 1 ) the form on a sideways phone.', 'wp-easycart' ),
					'default'  => '1',
					'advanced' => true,
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'cart', 'checkout', 'columns', 'phone' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Cart Design Options', 'label' => 'Cart Page Columns: Phone Horizontal' ),
				),
				'ec_option_cart_columns_smartphone' => array(
					'type'     => 'select',
					'label'    => __( 'Columns on phone', 'wp-easycart' ),
					'desc'     => __( 'Summary beside ( 2 ) or below ( 1 ) the form on an upright phone.', 'wp-easycart' ),
					'default'  => '1',
					'options'  => $ecv2_design_columns_1_2,
					'keywords' => array( 'cart', 'checkout', 'columns', 'phone', 'mobile' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Cart Design Options', 'label' => 'Cart Page Columns: Phone Vertical' ),
				),
			),
		),

		'cart-icon' => array(
			'title'  => __( 'Cart icon', 'wp-easycart' ),
			'hint'   => __( 'The cart icon and item count added to your navigation menus', 'wp-easycart' ),
			'fields' => array(
				'ec_option_cart_menu_id' => array(
					'type'      => 'multiselect',
					'separator' => '***',
					'label'     => __( 'Menus that show the cart icon', 'wp-easycart' ),
					'desc'      => __( 'A cart icon with the item count is appended to each selected menu. Clear every menu to remove the icon.', 'wp-easycart' ),
					'default'   => '',
					'options'   => 'ecv2_design_menu_options',
					'on_save'   => function( $value ) {
						// Classic handler: the icon is switched on when at least one menu is chosen, off when none.
						update_option( 'ec_option_show_menu_cart_icon', ( '' === (string) $value ) ? 0 : 1 );
					},
					'keywords'  => array( 'cart icon', 'menu', 'navigation', 'header' ),
					'legacy'    => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Cart Icon Display', 'note' => 'also sets ec_option_show_menu_cart_icon' ),
				),
				'ec_option_hide_cart_icon_on_empty' => array(
					'type'     => 'toggle',
					'label'    => __( 'Hide the icon when the cart is empty', 'wp-easycart' ),
					'desc'     => __( 'The menu icon only appears once a shopper has added something to the cart.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'cart icon', 'empty cart', 'menu', 'hide' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Hide Cart Icon for Empty Cart' ),
				),
			),
		),

		'newsletter-popup' => array(
			'title'  => __( 'Newsletter popup', 'wp-easycart' ),
			'hint'   => __( 'A sign-up form that opens over the page for new visitors', 'wp-easycart' ),
			'fields' => array(
				'ec_option_enable_newsletter_popup' => array(
					'type'     => 'toggle',
					'label'    => __( 'Show the newsletter sign-up popup', 'wp-easycart' ),
					'desc'     => __( 'Opens a name and email form on every page until the visitor closes or submits it; it then stays hidden on that browser. Sign-ups appear under Users › Subscribers.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'newsletter', 'popup', 'subscribe', 'mailing list' ),
					'legacy'   => array( 'page' => 'miscellaneous', 'section' => 'Additional Options', 'label' => 'Newsletter Popup' ),
				),
			),
		),

		'custom-css' => array(
			'title'  => __( 'Custom CSS', 'wp-easycart' ),
			'hint'   => __( 'Your own rules, printed on every store page after the store stylesheet', 'wp-easycart' ),
			'fields' => array(
				'ec_option_custom_css' => array(
					'type'        => 'textarea',
					'rows'        => 12,
					'label'       => __( 'CSS rules', 'wp-easycart' ),
					'desc'        => __( 'Plain CSS only, without <style> tags. Applied to all EasyCart pages and survives plugin updates.', 'wp-easycart' ),
					'placeholder' => ".ec_product_title_type1 { text-transform: uppercase; }",
					'default'     => '',
					'sanitize'    => 'ecv2_design_sanitize_css',
					'keywords'    => array( 'css', 'stylesheet', 'override', 'custom styles' ),
					'legacy'      => array( 'page' => 'design', 'section' => 'Custom CSS', 'label' => 'Custom CSS' ),
				),
			),
		),

		'theme-integration' => array(
			'title'  => __( 'Theme integration', 'wp-easycart' ),
			'hint'   => __( 'The on-page editor, and switches for theme conflicts and performance', 'wp-easycart' ),
			'fields' => array(
				'ec_option_hide_live_editor' => array(
					'type'     => 'toggle',
					'label'    => __( 'Hide the live design editor', 'wp-easycart' ),
					'desc'     => __( 'Removes the on-page design toolbar that administrators and store managers see on store pages.', 'wp-easycart' ),
					'default'  => 0,
					'keywords' => array( 'live editor', 'front end', 'toolbar', 'design editor' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Settings', 'label' => 'Enable Live Design Editor', 'note' => 'classic toggle was inverted ( on = shown ); this toggle is on = hidden' ),
				),
				'ec_option_use_custom_post_theme_template' => array(
					'type'     => 'toggle',
					'label'    => __( 'Use the theme\'s post template for products', 'wp-easycart' ),
					'desc'     => __( 'Product pages render with your theme\'s single-post template ( or a single-ec_store.php file ) instead of being treated as pages.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'template', 'single', 'theme', 'post type' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Settings', 'label' => 'Enable Custom Post Template' ),
				),
				'ec_option_match_store_meta' => array(
					'type'     => 'toggle',
					'label'    => __( 'Match store page meta on product pages', 'wp-easycart' ),
					'desc'     => __( 'Legacy option for older layouts that copied the store page\'s meta onto product pages. The current design does not use it.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'meta', 'legacy', 'store page' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Settings', 'label' => 'Enable Match Store Meta' ),
				),
				'ec_option_enabled_minified_scripts' => array(
					'type'     => 'toggle',
					'label'    => __( 'Load minified store CSS and JavaScript', 'wp-easycart' ),
					'desc'     => __( 'Serves ec-store.min.css and ec-store.min.js for faster page loads. Turn off if the store breaks after an update. Ignored when a custom theme folder provides its own files.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'minified', 'performance', 'scripts', 'speed' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Settings', 'label' => 'Enable Minified Store Scripts' ),
				),
				'ec_option_exclude_accordion' => array(
					'type'     => 'toggle',
					'label'    => __( 'Skip the jQuery UI accordion script', 'wp-easycart' ),
					'desc'     => __( 'Stops loading jQuery UI accordion with the store scripts. Only for resolving a conflict with your theme; some collapsible store panels may stop working.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'accordion', 'jquery ui', 'conflict', 'javascript' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Settings', 'label' => 'Disable Accordion JS' ),
				),
				'ec_option_exclude_datepicker' => array(
					'type'     => 'toggle',
					'label'    => __( 'Skip the jQuery UI datepicker script', 'wp-easycart' ),
					'desc'     => __( 'Stops loading jQuery UI datepicker with the store scripts. Only for resolving a conflict with your theme; date fields on product options and pickup dates may stop working.', 'wp-easycart' ),
					'default'  => 0,
					'advanced' => true,
					'keywords' => array( 'datepicker', 'jquery ui', 'conflict', 'calendar' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Settings', 'label' => 'Disable DatePicker JS' ),
				),
			),
		),

		'templates' => array(
			'title'  => __( 'Template system', 'wp-easycart' ),
			'hint'   => __( 'For developers: override the built-in CSS, images and PHP templates from wp-easycart-data', 'wp-easycart' ),
			'fields' => array(
				'ec_option_base_theme' => array(
					'type'     => 'select',
					'label'    => __( 'Custom theme folder', 'wp-easycart' ),
					'desc'     => __( 'A folder under wp-easycart-data/design/theme whose CSS, images and scripts replace the built-in ones. Only folders that exist are listed.', 'wp-easycart' ),
					'default'  => '0',
					'options'  => ecv2_design_folder_options( 'theme', __( 'None ( built-in design )', 'wp-easycart' ) ),
					'keywords' => array( 'theme', 'child theme', 'custom css', 'developer' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Template System', 'label' => 'EasyCart Custom Theme' ),
				),
				'ec_option_base_layout' => array(
					'type'     => 'select',
					'label'    => __( 'Custom layout folder', 'wp-easycart' ),
					'desc'     => __( 'A folder under wp-easycart-data/design/layout whose PHP templates replace the built-in store, cart and account templates. Only folders that exist are listed.', 'wp-easycart' ),
					'default'  => '0',
					'options'  => ecv2_design_folder_options( 'layout', __( 'None ( built-in layout )', 'wp-easycart' ) ),
					'keywords' => array( 'layout', 'templates', 'php', 'developer' ),
					'legacy'   => array( 'page' => 'design', 'section' => 'Design Template System', 'label' => 'EasyCart Custom Layout' ),
				),
			),
		),
	),
);
