<?php
/**
 * WP EasyCart — words for the admin scripts ( 6.0.5, admin review M17 ).
 *
 * Scripts that build their screens in the browser ( settings-lists-v2.js: price points, per page, logs, roles, subscribers,
 * countries and regions; option-editor-v2.js; category-editor-v2.js; products-v2.js ) ask for every word with T( 'English' ) and
 * every count with TN( 'one', 'many', n ). attach( $handle, $group ) hands them this store's translations, keyed by the English, and
 * each plural's forms with the admin language's rule worked out for 0–199 ( so Polish, Russian or French counts read right without
 * the browser knowing the rule ). Sentences keep their words together with placeholders ( %s, %d, %1$s ), never glued from parts.
 *
 * A new string in one of these scripts: add it to its group below ( the .pot scanner reads it here ).
 *
 * @package WP_EasyCart
 * @since   6.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'wp_easycart_admin_js_text' ) ) :

	/**
	 * Words for the admin scripts.
	 */
	class wp_easycart_admin_js_text {

		/**
		 * Groups already printed on this page.
		 *
		 * @var array
		 */
		private static $printed = array();

		/**
		 * Hands a script its words: window.wpEasyCartText ( s = strings, p = plurals ) and the helpers window.ecv2_t / ecv2_tn, printed
		 * before the script.
		 *
		 * @param string $handle Script handle ( registered ).
		 * @param string $group  lists | option_editor | category_editor | products.
		 */
		public static function attach( $handle, $group ) {
			if ( isset( self::$printed[ $group ] ) || ! function_exists( 'wp_add_inline_script' ) ) {
				return;
			}
			self::$printed[ $group ] = true;
			$data = array(
				's' => (object) self::translated( self::strings( $group ) ),
				'p' => (object) self::plural_forms( self::plurals( $group ) ),
			);
			/**
			 * The words an admin script gets.
			 *
			 * @since 6.0.5
			 * @param array  $data  { s: English => words, p: "one|many" => { f: forms, i: form index for 0–199 } }.
			 * @param string $group Group.
			 */
			$data = apply_filters( 'wp_easycart_admin_js_text', $data, $group );
			wp_add_inline_script( $handle, self::helpers() . 'window.wpEasyCartText=window.wpEasyCartText||{s:{},p:{}};(function(d,t){var k;for(k in d.s){t.s[k]=d.s[k];}for(k in d.p){t.p[k]=d.p[k];}})(' . wp_json_encode( $data ) . ',window.wpEasyCartText);', 'before' );
		}

		/**
		 * The helpers window.ecv2_t( text, … ) and window.ecv2_tn( one, many, n, … ): the store's words, placeholders %s, %d, %1$s and %% filled.
		 *
		 * @return string JavaScript.
		 */
		public static function helpers() {
			return 'window.ecv2_t_fill=window.ecv2_t_fill||function(s,a){var i=0;return String(s).replace(/%(?:(\\d+)\\$)?([sd%])/g,function(m,n,k){if("%"===k){return "%";}var v=n?a[n-1]:a[i++];return undefined===v||null===v?"":String(v);});};'
				. 'window.ecv2_t=window.ecv2_t||function(text){var w=window.wpEasyCartText,s=(w&&w.s&&Object.prototype.hasOwnProperty.call(w.s,text))?w.s[text]:text;return window.ecv2_t_fill(s,Array.prototype.slice.call(arguments,1));};'
				. 'window.ecv2_tn=window.ecv2_tn||function(one,many,n){var w=window.wpEasyCartText,p=w&&w.p&&w.p[one+"|"+many],c=Math.abs(Math.floor(Number(n))),s;if(!isFinite(c)){c=2;}if(p&&p.f&&p.f.length){var k=c<200?c:100+c%100,x=p.i?Number(p.i.charAt(k)):(1===c?0:1);s=p.f[x]||p.f[p.f.length-1];}else{s=1===c?one:many;}return window.ecv2_t_fill(s,Array.prototype.slice.call(arguments,2));};';
		}

		/**
		 * The strings, translated, without the ones the store's language leaves in English.
		 *
		 * @param array $strings English => translated.
		 * @return array
		 */
		private static function translated( $strings ) {
			$out = array();
			foreach ( $strings as $english => $words ) {
				if ( $words !== $english ) {
					$out[ $english ] = $words;
				}
			}
			return $out;
		}

		/**
		 * Each plural's forms and which form 0–199 take ( i: one digit per number, left out when it is English's rule ).
		 *
		 * @param array $nooped _n_noop() results.
		 * @return array "one|many" => { f, i }.
		 */
		private static function plural_forms( $nooped ) {
			$out = array();
			foreach ( $nooped as $noop ) {
				$forms   = array();
				$index   = '';
				$english = '';
				for ( $n = 0; $n < 200; $n++ ) {
					$text = translate_nooped_plural( $noop, $n, 'wp-easycart' );
					$at   = array_search( $text, $forms, true );
					if ( false === $at ) {
						$forms[] = $text;
						$at      = count( $forms ) - 1;
					}
					$index   .= ( $at < 10 ) ? (string) $at : '9';
					$english .= ( 1 === $n ) ? '0' : '1';
				}
				if ( 1 === count( $forms ) && $forms[0] === $noop['plural'] ) {
					continue;
				}
				if ( array( $noop['singular'], $noop['plural'] ) === $forms && $index === $english ) {
					continue;
				}
				$entry = array( 'f' => $forms );
				if ( $index !== $english ) {
					$entry['i'] = $index;
				}
				$out[ $noop['singular'] . '|' . $noop['plural'] ] = $entry;
			}
			return $out;
		}

		/**
		 * A group's strings.
		 *
		 * @param string $group Group.
		 * @return array English => translated.
		 */
		public static function strings( $group ) {
			switch ( $group ) {
				case 'lists':
					return array(
						'Something went wrong.' => __( 'Something went wrong.', 'wp-easycart' ),
						'Under' => __( 'Under', 'wp-easycart' ),
						'Between' => __( 'Between', 'wp-easycart' ),
						'Over' => __( 'Over', 'wp-easycart' ),
						'Drag to reorder' => __( 'Drag to reorder', 'wp-easycart' ),
						'Remove bucket' => __( 'Remove bucket', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Extend the next bucket to start at %s' => __( 'Extend the next bucket to start at %s', 'wp-easycart' ),
						'No buckets yet. Add one, or let us suggest ranges from your prices.' => __( 'No buckets yet. Add one, or let us suggest ranges from your prices.', 'wp-easycart' ),
						'Buckets' => __( 'Buckets', 'wp-easycart' ),
						'Empty buckets' => __( 'Empty buckets', 'wp-easycart' ),
						'Gaps / overlaps' => __( 'Gaps / overlaps', 'wp-easycart' ),
						'Products not in any bucket' => __( 'Products not in any bucket', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'%1$d of %2$d' => __( '%1$d of %2$d', 'wp-easycart' ),
						'You have unsaved changes.' => __( 'You have unsaved changes.', 'wp-easycart' ),
						'Replace your buckets with suggested ranges?' => __( 'Replace your buckets with suggested ranges?', 'wp-easycart' ),
						'We look at your active product prices and propose an Under, two Between and an Over bucket that split them evenly. Nothing is saved until you click Save.' => __( 'We look at your active product prices and propose an Under, two Between and an Over bucket that split them evenly. Nothing is saved until you click Save.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Bucket %d has its high amount below its low amount.' => __( 'Bucket %d has its high amount below its low amount.', 'wp-easycart' ),
						'Make this the default' => __( 'Make this the default', 'wp-easycart' ),
						'default' => __( 'default', 'wp-easycart' ),
						'Remove' => __( 'Remove', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Added %s.' => __( 'Added %s.', 'wp-easycart' ),
						'expand' => __( 'expand', 'wp-easycart' ),
						'collapse' => __( 'collapse', 'wp-easycart' ),
						'Payload copied.' => __( 'Payload copied.', 'wp-easycart' ),
						'Clipboard unavailable — expand the row and copy manually.' => __( 'Clipboard unavailable — expand the row and copy manually.', 'wp-easycart' ),
						'This entry is not linked to an order.' => __( 'This entry is not linked to an order.', 'wp-easycart' ),
						'Remove entries older than how many days?' => __( 'Remove entries older than how many days?', 'wp-easycart' ),
						'Undo' => __( 'Undo', 'wp-easycart' ),
						'higher than regular' => __( 'higher than regular', 'wp-easycart' ),
						'remove' => __( 'remove', 'wp-easycart' ),
						'No role prices yet — search a product above.' => __( 'No role prices yet — search a product above.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'regular %s' => __( 'regular %s', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'role price %s' => __( 'role price %s', 'wp-easycart' ),
						'Set' => __( 'Set', 'wp-easycart' ),
						'No matching products.' => __( 'No matching products.', 'wp-easycart' ),
						'Remote admin access on.' => __( 'Remote admin access on.', 'wp-easycart' ),
						'Remote admin access off.' => __( 'Remote admin access off.', 'wp-easycart' ),
						'New role' => __( 'New role', 'wp-easycart' ),
						'Role name' => __( 'Role name', 'wp-easycart' ),
						'e.g. Wholesale' => __( 'e.g. Wholesale', 'wp-easycart' ),
						'Assign it to customers from their account page; set role prices in the editor.' => __( 'Assign it to customers from their account page; set role prices in the editor.', 'wp-easycart' ),
						'Cancel' => __( 'Cancel', 'wp-easycart' ),
						'Create & edit' => __( 'Create & edit', 'wp-easycart' ),
						'Role price saved.' => __( 'Role price saved.', 'wp-easycart' ),
						'Apply a discount to role prices' => __( 'Apply a discount to role prices', 'wp-easycart' ),
						'Percent off regular price' => __( 'Percent off regular price', 'wp-easycart' ),
						'Apply to' => __( 'Apply to', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Products that already have a role price (%d)' => __( 'Products that already have a role price (%d)', 'wp-easycart' ),
						'Every active product (creates role prices)' => __( 'Every active product (creates role prices)', 'wp-easycart' ),
						'Role prices are recalculated from each product\'s current regular price. Existing values are overwritten.' => __( 'Role prices are recalculated from each product\'s current regular price. Existing values are overwritten.', 'wp-easycart' ),
						'Apply' => __( 'Apply', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'No users in this role match “%s”.' => __( 'No users in this role match “%s”.', 'wp-easycart' ),
						'No users have this role yet — search above to add some.' => __( 'No users have this role yet — search above to add some.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Showing %1$d–%2$d of %3$d matching' => __( 'Showing %1$d–%2$d of %3$d matching', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Showing %1$d–%2$d of %3$d' => __( 'Showing %1$d–%2$d of %3$d', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'%d selected →' => __( '%d selected →', 'wp-easycart' ),
						'No other role to move this user to.' => __( 'No other role to move this user to.', 'wp-easycart' ),
						'Remove from this role' => __( 'Remove from this role', 'wp-easycart' ),
						'Move the user to' => __( 'Move the user to', 'wp-easycart' ),
						'Every account has a role; role prices and access follow it.' => __( 'Every account has a role; role prices and access follow it.', 'wp-easycart' ),
						'Move' => __( 'Move', 'wp-easycart' ),
						'Type at least two characters.' => __( 'Type at least two characters.', 'wp-easycart' ),
						'No other accounts match.' => __( 'No other accounts match.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Currently %s' => __( 'Currently %s', 'wp-easycart' ),
						'Add' => __( 'Add', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'All %d shown' => __( 'All %d shown', 'wp-easycart' ),
						'Add all shown' => __( 'Add all shown', 'wp-easycart' ),
						'Grant admin access?' => __( 'Grant admin access?', 'wp-easycart' ),
						'Move all users to another role' => __( 'Move all users to another role', 'wp-easycart' ),
						'Move users' => __( 'Move users', 'wp-easycart' ),
						'<b>0</b> users have this role.' => __( '<b>0</b> users have this role.', 'wp-easycart' ),
						'Undo is offered for 15 minutes.' => __( 'Undo is offered for 15 minutes.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Delete role “%s”?' => __( 'Delete role “%s”?', 'wp-easycart' ),
						'Keep role' => __( 'Keep role', 'wp-easycart' ),
						'Delete role' => __( 'Delete role', 'wp-easycart' ),
						'Built-in roles cannot be deleted.' => __( 'Built-in roles cannot be deleted.', 'wp-easycart' ),
						'Delete roles one at a time — each needs a destination for its users.' => __( 'Delete roles one at a time — each needs a destination for its users.', 'wp-easycart' ),
						'Edit subscriber' => __( 'Edit subscriber', 'wp-easycart' ),
						'Add subscriber' => __( 'Add subscriber', 'wp-easycart' ),
						'Email' => __( 'Email', 'wp-easycart' ),
						'First name' => __( 'First name', 'wp-easycart' ),
						'Last name' => __( 'Last name', 'wp-easycart' ),
						'Save' => __( 'Save', 'wp-easycart' ),
						'Saved.' => __( 'Saved.', 'wp-easycart' ),
						'No customer account matches this email.' => __( 'No customer account matches this email.', 'wp-easycart' ),
						'Delete this subscriber?' => __( 'Delete this subscriber?', 'wp-easycart' ),
						'They stop receiving the newsletter. You can undo this for 15 minutes.' => __( 'They stop receiving the newsletter. You can undo this for 15 minutes.', 'wp-easycart' ),
						'Import subscribers' => __( 'Import subscribers', 'wp-easycart' ),
						'<b>Choose a CSV file</b> or paste addresses below.' => __( '<b>Choose a CSV file</b> or paste addresses below.', 'wp-easycart' ),
						'Choose file' => __( 'Choose file', 'wp-easycart' ),
						'email, first name, last name — one per line. A header row is fine.' => __( 'email, first name, last name — one per line. A header row is fine.', 'wp-easycart' ),
						'Import' => __( 'Import', 'wp-easycart' ),
						'Skip' => __( 'Skip', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Column %d' => __( 'Column %d', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'<b>Preview:</b> %1$d new · %2$d already subscribed (skipped) · %3$d invalid' => __( '<b>Preview:</b> %1$d new · %2$d already subscribed (skipped) · %3$d invalid', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'(e.g. %s)' => __( '(e.g. %s)', 'wp-easycart' ),
						'Add country' => __( 'Add country', 'wp-easycart' ),
						'Ship-to on' => __( 'Ship-to on', 'wp-easycart' ),
						'Ship-to off' => __( 'Ship-to off', 'wp-easycart' ),
						'Details' => __( 'Details', 'wp-easycart' ),
						'Regions' => __( 'Regions', 'wp-easycart' ),
						'Tax' => __( 'Tax', 'wp-easycart' ),
						'Where it’s used' => __( 'Where it’s used', 'wp-easycart' ),
						'Name' => __( 'Name', 'wp-easycart' ),
						'ISO 2' => __( 'ISO 2', 'wp-easycart' ),
						'ISO 3' => __( 'ISO 3', 'wp-easycart' ),
						'Sort order' => __( 'Sort order', 'wp-easycart' ),
						'Ship to' => __( 'Ship to', 'wp-easycart' ),
						'Customers can ship here' => __( 'Customers can ship here', 'wp-easycart' ),
						'Ship-to is off: this country is hidden from checkout address menus.' => __( 'Ship-to is off: this country is hidden from checkout address menus.', 'wp-easycart' ),
						'Automatic: required' => __( 'Automatic: required', 'wp-easycart' ),
						'Automatic: optional' => __( 'Automatic: optional', 'wp-easycart' ),
						'Region at checkout' => __( 'Region at checkout', 'wp-easycart' ),
						'Required' => __( 'Required', 'wp-easycart' ),
						'Optional' => __( 'Optional', 'wp-easycart' ),
						'Whether shoppers must pick one of these regions in their address. UK, German and New Zealand addresses use none, so there it is optional unless you choose Required.' => __( 'Whether shoppers must pick one of these regions in their address. UK, German and New Zealand addresses use none, so there it is optional unless you choose Required.', 'wp-easycart' ),
						'Add region' => __( 'Add region', 'wp-easycart' ),
						'Code' => __( 'Code', 'wp-easycart' ),
						'Group' => __( 'Group', 'wp-easycart' ),
						'optional' => __( 'optional', 'wp-easycart' ),
						'e.g. Territory' => __( 'e.g. Territory', 'wp-easycart' ),
						'Clear' => __( 'Clear', 'wp-easycart' ),
						'VAT rate %%' => __( 'VAT rate %%', 'wp-easycart' ),
						'Used by the VAT tax mode. Leave blank for no VAT.' => __( 'Used by the VAT tax mode. Leave blank for no VAT.', 'wp-easycart' ),
						'Stripe tax rate' => __( 'Stripe tax rate', 'wp-easycart' ),
						'Created by Stripe tax sync.' => __( 'Created by Stripe tax sync.', 'wp-easycart' ),
						'Set automatically when Stripe tax sync runs.' => __( 'Set automatically when Stripe tax sync runs.', 'wp-easycart' ),
						'Apply to VAT-registered businesses' => __( 'Apply to VAT-registered businesses', 'wp-easycart' ),
						'Businesses with a VAT number pay this rate too. Off, a business abroad pays the business rate ( Settings › Taxes › VAT ).' => __( 'Businesses with a VAT number pay this rate too. Off, a business abroad pays the business rate ( Settings › Taxes › VAT ).', 'wp-easycart' ),
						'regions' => __( 'regions', 'wp-easycart' ),
						'shipping zones' => __( 'shipping zones', 'wp-easycart' ),
						'tax rules' => __( 'tax rules', 'wp-easycart' ),
						'past orders' => __( 'past orders', 'wp-easycart' ),
						'Deleting a country removes its regions and its shipping-zone memberships. Tax rules and past orders are never touched — they keep the ISO code. If you just want to stop shipping here, turn Ship-to off instead.' => __( 'Deleting a country removes its regions and its shipping-zone memberships. Tax rules and past orders are never touched — they keep the ISO code. If you just want to stop shipping here, turn Ship-to off instead.', 'wp-easycart' ),
						'Delete…' => __( 'Delete…', 'wp-easycart' ),
						'Close' => __( 'Close', 'wp-easycart' ),
						'Create country' => __( 'Create country', 'wp-easycart' ),
						'No regions. Add states, provinces or territories below if this country needs them at checkout.' => __( 'No regions. Add states, provinces or territories below if this country needs them at checkout.', 'wp-easycart' ),
						'Region' => __( 'Region', 'wp-easycart' ),
						'edit' => __( 'edit', 'wp-easycart' ),
						'delete' => __( 'delete', 'wp-easycart' ),
						'Discard unsaved changes?' => __( 'Discard unsaved changes?', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Edit %s' => __( 'Edit %s', 'wp-easycart' ),
						'Save region' => __( 'Save region', 'wp-easycart' ),
						'Region saved.' => __( 'Region saved.', 'wp-easycart' ),
						'Region added.' => __( 'Region added.', 'wp-easycart' ),
						'Confirm delete' => __( 'Confirm delete', 'wp-easycart' ),
						'Delete?' => __( 'Delete?', 'wp-easycart' ),
						'Delete' => __( 'Delete', 'wp-easycart' ),
						'Removed from checkout menus and from any shipping zone that lists it. Past orders keep the code.' => __( 'Removed from checkout menus and from any shipping zone that lists it. Past orders keep the code.', 'wp-easycart' ),
						'Region deleted.' => __( 'Region deleted.', 'wp-easycart' ),
						'Delete this region?' => __( 'Delete this region?', 'wp-easycart' ),
						'It stops being offered at checkout. Shipping rates and tax rates that name it are not changed.' => __( 'It stops being offered at checkout. Shipping rates and tax rates that name it are not changed.', 'wp-easycart' ),
						'Enter 0 to clear.' => __( 'Enter 0 to clear.', 'wp-easycart' ),
						'Set rate' => __( 'Set rate', 'wp-easycart' ),
						'Their regions are left as they are.' => __( 'Their regions are left as they are.', 'wp-easycart' ),
						'Their regions are also disabled.' => __( 'Their regions are also disabled.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Delete %s?' => __( 'Delete %s?', 'wp-easycart' ),
						'This country is enabled for shipping.' => __( 'This country is enabled for shipping.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'If you only want to stop shipping there, %1$sdisable ship-to instead%2$s — it keeps regions, zones and tax rules.' => __( 'If you only want to stop shipping there, %1$sdisable ship-to instead%2$s — it keeps regions, zones and tax rules.', 'wp-easycart' ),
						'<b>0</b> shipping zones lose this location.' => __( '<b>0</b> shipping zones lose this location.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'<b>%1$d</b> tax rules and <b>%2$d</b> past orders keep the ISO code and are not changed.' => __( '<b>%1$d</b> tax rules and <b>%2$d</b> past orders keep the ISO code and are not changed.', 'wp-easycart' ),
						'Undo is offered for 15 minutes and restores regions and zone memberships too.' => __( 'Undo is offered for 15 minutes and restores regions and zone memberships too.', 'wp-easycart' ),
						'Checking…' => __( 'Checking…', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'The default set has %1$d countries, with %2$d regions across %3$d of them.' => __( 'The default set has %1$d countries, with %2$d regions across %3$d of them.', 'wp-easycart' ),
						'Nothing to restore: every default country and region is already in your list.' => __( 'Nothing to restore: every default country and region is already in your list.', 'wp-easycart' ),
						'New entries start with ship-to off, so checkout does not change until you turn them on. Nothing you already have is changed or removed.' => __( 'New entries start with ship-to off, so checkout does not change until you turn them on. Nothing you already have is changed or removed.', 'wp-easycart' ),
						'Adding countries and regions… This can take a little while on some hosts. Keep this page open.' => __( 'Adding countries and regions… This can take a little while on some hosts. Keep this page open.', 'wp-easycart' ),
						'Restore default countries & regions?' => __( 'Restore default countries & regions?', 'wp-easycart' ),
						'Restore defaults' => __( 'Restore defaults', 'wp-easycart' ),
						'Restoring…' => __( 'Restoring…', 'wp-easycart' ),
						'Countries and regions are still being restored.' => __( 'Countries and regions are still being restored.', 'wp-easycart' ),
						'Reloading…' => __( 'Reloading…', 'wp-easycart' ),
						'The restore did not finish (the request timed out or was interrupted). Anything already added is kept; run Restore defaults again to add the rest.' => __( 'The restore did not finish (the request timed out or was interrupted). Anything already added is kept; run Restore defaults again to add the rest.', 'wp-easycart' ),
					);
				case 'option_editor':
					return array(
						'Required' => __( 'Required', 'wp-easycart' ),
						'Optional' => __( 'Optional', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'%s pre-selects that choice on the product page.' => __( '%s pre-selects that choice on the product page.', 'wp-easycart' ),
						'Change type?' => __( 'Change type?', 'wp-easycart' ),
						'Drag to reorder. Price and weight adjust the product unless you pick Override.' => __( 'Drag to reorder. Price and weight adjust the product unless you pick Override.', 'wp-easycart' ),
						'Default value and price rule for the shopper input.' => __( 'Default value and price rule for the shopper input.', 'wp-easycart' ),
						'+ Add' => __( '+ Add', 'wp-easycart' ),
						'One-time' => __( 'One-time', 'wp-easycart' ),
						'Override' => __( 'Override', 'wp-easycart' ),
						'× Multiply' => __( '× Multiply', 'wp-easycart' ),
						'Per character' => __( 'Per character', 'wp-easycart' ),
						'+ Each' => __( '+ Each', 'wp-easycart' ),
						'Price, weight and default value for the shopper input.' => __( 'Price, weight and default value for the shopper input.', 'wp-easycart' ),
						'The size scales the product price by its area.' => __( 'The size scales the product price by its area.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'%s fills in that value on the product page.' => __( '%s fills in that value on the product page.', 'wp-easycart' ),
						/* translators: %1$s: an amount ( 2.00 ), %2$s: four times it; filled in by the admin script. */
						'+ Each is charged for every 1 the shopper enters: 4 × %1$s = %2$s. Weight counts the same way. × Multiply multiplies the item price by the number entered and the factor ( 1 = price × the number ). One-time is charged once; Override replaces the item price.' => __( '+ Each is charged for every 1 the shopper enters: 4 × %1$s = %2$s. Weight counts the same way. × Multiply multiplies the item price by the number entered and the factor ( 1 = price × the number ). One-time is charged once; Override replaces the item price.', 'wp-easycart' ),
						/* translators: %1$s: an amount ( 2.00 ), %2$s: four times it; filled in by the admin script. */
						'The price is charged for every 1 the shopper enters: 4 × %1$s = %2$s. Weight counts the same way.' => __( 'The price is charged for every 1 the shopper enters: 4 × %1$s = %2$s. Weight counts the same way.', 'wp-easycart' ),
						'Per character charges for every character typed, spaces left out.' => __( 'Per character charges for every character typed, spaces left out.', 'wp-easycart' ),
						/* translators: %s: a price adjustment ( +$2.00 ) for each 1 a shopper enters in a number box; filled in by the admin script. */
						'%s each' => __( '%s each', 'wp-easycart' ),
						'price × the number' => __( 'price × the number', 'wp-easycart' ),
						/* translators: %s: a multiplying factor; filled in by the admin script. */
						'price × the number × %s' => __( 'price × the number × %s', 'wp-easycart' ),
						'Change swatch' => __( 'Change swatch', 'wp-easycart' ),
						'Set swatch color or image' => __( 'Set swatch color or image', 'wp-easycart' ),
						'Remove swatch' => __( 'Remove swatch', 'wp-easycart' ),
						'Initially selected' => __( 'Initially selected', 'wp-easycart' ),
						'Advanced…' => __( 'Advanced…', 'wp-easycart' ),
						'Duplicate' => __( 'Duplicate', 'wp-easycart' ),
						'Remove choice' => __( 'Remove choice', 'wp-easycart' ),
						'No choices yet. Add one below or paste a list.' => __( 'No choices yet. Add one below or paste a list.', 'wp-easycart' ),
						'Default value' => __( 'Default value', 'wp-easycart' ),
						'pre-filled for the shopper' => __( 'pre-filled for the shopper', 'wp-easycart' ),
						'Custom price label' => __( 'Custom price label', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'replaces %s' => __( 'replaces %s', 'wp-easycart' ),
						'e.g. Free' => __( 'e.g. Free', 'wp-easycart' ),
						'Behavior when chosen' => __( 'Behavior when chosen', 'wp-easycart' ),
						'Allow downloads' => __( 'Allow downloads', 'wp-easycart' ),
						'Disallow shipping' => __( 'Disallow shipping', 'wp-easycart' ),
						'Download override' => __( 'Download override', 'wp-easycart' ),
						'File name in products/downloads, or s3:key for Amazon S3' => __( 'File name in products/downloads, or s3:key for Amazon S3', 'wp-easycart' ),
						'Additional download' => __( 'Additional download', 'wp-easycart' ),
						'Extra file delivered with this choice (file name or s3:key)' => __( 'Extra file delivered with this choice (file name or s3:key)', 'wp-easycart' ),
						'Close' => __( 'Close', 'wp-easycart' ),
						'Color' => __( 'Color', 'wp-easycart' ),
						'Image' => __( 'Image', 'wp-easycart' ),
						'Two-tone' => __( 'Two-tone', 'wp-easycart' ),
						'Use a photo or pattern instead of a flat color.' => __( 'Use a photo or pattern instead of a flat color.', 'wp-easycart' ),
						'Choose image…' => __( 'Choose image…', 'wp-easycart' ),
						'Color swatches are included with every store.' => __( 'Color swatches are included with every store.', 'wp-easycart' ),
						'Type here…' => __( 'Type here…', 'wp-easycart' ),
						'Choose file…' => __( 'Choose file…', 'wp-easycart' ),
						'Add choices to see the preview.' => __( 'Add choices to see the preview.', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Selected: %s' => __( 'Selected: %s', 'wp-easycart' ),
						'Advanced option' => __( 'Advanced option', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Slot %d' => __( 'Slot %d', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'also uses %s' => __( 'also uses %s', 'wp-easycart' ),
						'Active' => __( 'Active', 'wp-easycart' ),
						'Inactive' => __( 'Inactive', 'wp-easycart' ),
					);
				case 'category_editor':
					return array(
						'Unsaved changes' => __( 'Unsaved changes', 'wp-easycart' ),
						'Active' => __( 'Active', 'wp-easycart' ),
						'Hidden' => __( 'Hidden', 'wp-easycart' ),
						'added by a rule' => __( 'added by a rule', 'wp-easycart' ),
						'Added by a smart rule' => __( 'Added by a smart rule', 'wp-easycart' ),
						'rule' => __( 'rule', 'wp-easycart' ),
						'Exclude from this category\'s rules' => __( 'Exclude from this category\'s rules', 'wp-easycart' ),
						'Remove from category' => __( 'Remove from category', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'%d in this category' => __( '%d in this category', 'wp-easycart' ),
						'filled by rules' => __( 'filled by rules', 'wp-easycart' ),
						'rules + hand-picked · adds apply immediately' => __( 'rules + hand-picked · adds apply immediately', 'wp-easycart' ),
						'changes apply immediately' => __( 'changes apply immediately', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'Excluded “%s” — rules won\'t add it back.' => __( 'Excluded “%s” — rules won\'t add it back.', 'wp-easycart' ),
						'Open' => __( 'Open', 'wp-easycart' ),
						'Top level' => __( 'Top level', 'wp-easycart' ),
						'redirect added from the old URL' => __( 'redirect added from the old URL', 'wp-easycart' ),
						'No rules yet — add one to start matching products.' => __( 'No rules yet — add one to start matching products.', 'wp-easycart' ),
						'likely truncated' => __( 'likely truncated', 'wp-easycart' ),
					);
				case 'products':
					return array(
						'The clean-up could not be run. Please try again.' => __( 'The clean-up could not be run. Please try again.', 'wp-easycart' ),
						'Image could not be loaded' => __( 'Image could not be loaded', 'wp-easycart' ),
						/* translators: the placeholders are filled in by the admin script. */
						'%d%% off' => __( '%d%% off', 'wp-easycart' ),
						'Stock quantity' => __( 'Stock quantity', 'wp-easycart' ),
						'Not tracked' => __( 'Not tracked', 'wp-easycart' ),
						'Do not track stock' => __( 'Do not track stock', 'wp-easycart' ),
						'Total' => __( 'Total', 'wp-easycart' ),
						'Track overall quantity' => __( 'Track overall quantity', 'wp-easycart' ),
						'Per variant' => __( 'Per variant', 'wp-easycart' ),
						'Track per variation' => __( 'Track per variation', 'wp-easycart' ),
						'Video' => __( 'Video', 'wp-easycart' ),
					);
			}
			return array();
		}

		/**
		 * A group's plurals.
		 *
		 * @param string $group Group.
		 * @return array _n_noop() results.
		 */
		public static function plurals( $group ) {
			switch ( $group ) {
				case 'lists':
					return array(
						_n_noop( 'product', 'products', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d bucket', '%d buckets', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d value', '%d values', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d role price', '%d role prices', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d order', '%d orders', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d user', '%d users', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'This role can manage the store from the EasyCart apps. Add %d user to it?', 'This role can manage the store from the EasyCart apps. Add %d users to it?', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'Move %d user to', 'Move %d users to', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '<b>%d</b> user has this role — choose where they go:', '<b>%d</b> users have this role — choose where they go:', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '<b>%d</b> role price will be removed.', '<b>%d</b> role prices will be removed.', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'Delete %d subscriber?', 'Delete %d subscribers?', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'Import %d subscriber', 'Import %d subscribers', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d Country', '%d Countries', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'Set VAT rate for %d country', 'Set VAT rate for %d countries', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'Enable ship-to for %d country?', 'Enable ship-to for %d countries?', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'Disable ship-to for %d country?', 'Disable ship-to for %d countries?', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'Delete %d country?', 'Delete %d countries?', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d of these is enabled for shipping.', '%d of these are enabled for shipping.', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '<b>%d</b> region will be deleted.', '<b>%d</b> regions will be deleted.', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '<b>%1$d</b> shipping zone loses this location: %2$s', '<b>%1$d</b> shipping zones lose this location: %2$s', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '<b>%d</b> country will be added.', '<b>%d</b> countries will be added.', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '<b>%d</b> region will be added.', '<b>%d</b> regions will be added.', 'wp-easycart' ),
					);
				case 'option_editor':
					return array(
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'Switching to a modifier type removes variant stock tracking on %d product.', 'Switching to a modifier type removes variant stock tracking on %d products.', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d choice added — save to apply.', '%d choices added — save to apply.', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d variant stock row removed', '%d variant stock rows removed', 'wp-easycart' ),
					);
				case 'category_editor':
					return array(
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d product', '%d products', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( '%d hand-picked product will be re-checked against the rules.', '%d hand-picked products will be re-checked against the rules.', 'wp-easycart' ),
						/* translators: %d: how many; the other placeholders are filled in by the admin script. */
						_n_noop( 'room for about %d more character', 'room for about %d more characters', 'wp-easycart' ),
					);
			}
			return array();
		}
	}

endif;
