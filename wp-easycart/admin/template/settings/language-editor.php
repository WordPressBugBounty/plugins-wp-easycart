<?php
/**
 * Settings › Language ( V2 declaration ).
 *
 * Replaces admin/inc/wp_easycart_admin_language_editor.php and the templates in
 * admin/template/settings/language-editor/. The only stored option is
 * ec_option_language ( the file the storefront reads ). Installed languages
 * ( add / export / remove ) and the storefront phrase editor are not options:
 * they live in ec_option_language_data and render through the two section
 * 'render' callables in admin/inc/wp_easycart_admin_language_v2.php, which
 * also owns the ecv2_language_* AJAX handlers. Order-receipt phrases
 * ( group cart_success ) are fields on Settings › Email for the storefront
 * language, so the editor here links there instead of repeating them.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Helpers + AJAX. Also loaded from admin/admin-init.php so the handlers exist on admin-ajax requests, where the registry is never asked for its pages. */
require_once EC_PLUGIN_DIRECTORY . '/admin/inc/wp_easycart_admin_language_v2.php';

$ecst_language_options = class_exists( 'wp_easycart_admin_language_v2' ) ? wp_easycart_admin_language_v2::language_options() : array( 'en-us' => 'English (US)' );

return array(
	'slug'        => 'language-editor',
	'title'       => __( 'Language', 'wp-easycart' ),
	'description' => __( 'Which language shoppers see, which translations are installed, and every storefront phrase, editable.', 'wp-easycart' ),
	'group'       => 'customize',
	'icon'        => 'translation',
	'docs'        => array( 'settings', 'language-editor', 'current-language' ),
	'legacy'      => array( 'language-editor' ),
	'upsell'      => 'default',
	'enqueue'     => array( 'wp_easycart_admin_language_v2', 'enqueue' ),
	'sections'    => array(

		'languages' => array(
			'title'  => __( 'Languages', 'wp-easycart' ),
			'hint'   => __( 'The language shoppers see and the translations installed on this store', 'wp-easycart' ),
			'fields' => array(
				'ec_option_language' => array(
					'type'     => 'select',
					'label'    => __( 'Storefront language', 'wp-easycart' ),
					'desc'     => __( 'Shoppers read this language’s phrases. Switching also pulls any phrases added in this release into every installed language.', 'wp-easycart' ),
					'default'  => 'en-us',
					'options'  => $ecst_language_options,
					'on_save'  => array( 'wp_easycart_admin_language_v2', 'on_save_language' ),
					'keywords' => array( 'translation', 'translate', 'locale', 'english', 'spanish', 'french', 'german', 'text', 'wording', 'labels' ),
					'legacy'   => array( 'page' => 'language-editor', 'section' => 'Current Language to Edit', 'label' => 'Select Language to Edit' ),
				),
			),
			'render' => array( 'wp_easycart_admin_language_v2', 'render_languages' ),
		),

		'phrases' => array(
			'title'  => __( 'Storefront phrases', 'wp-easycart' ),
			'hint'   => __( 'Every phrase on the store, grouped by area. A phrase saves when you leave its box.', 'wp-easycart' ),
			'fields' => array(),
			'render' => array( 'wp_easycart_admin_language_v2', 'render_phrases' ),
		),
	),
);
