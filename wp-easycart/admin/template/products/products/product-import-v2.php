<?php
/**
 * Products list — Import / export panel ( V2 ).
 *
 * Printed once under the products list by wp_easycart_admin_product_import::render_modal(). The import pane's
 * preview, progress and results are rendered by admin/js/product-import-v2.js from the AJAX replies; everything
 * static lives here.
 *
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ecv2pi_export_all = wp_easycart_admin_product_import::export_all_url();
$ecv2pi_max_upload = size_format( (int) wp_max_upload_size() );
?>
<div class="ecv2-modal-overlay ecv2pi" id="ecv2pi_modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="ecv2pi_title">
	<div class="ecv2-modal ecv2pi-modal">
		<div class="ecv2-modal-header">
			<h2 id="ecv2pi_title"><span class="dashicons dashicons-database-import" aria-hidden="true"></span> <?php esc_html_e( 'Import &amp; export products', 'wp-easycart' ); ?></h2>
			<button type="button" class="ecv2-modal-close" data-ecv2pi-close aria-label="<?php esc_attr_e( 'Close', 'wp-easycart' ); ?>">&times;</button>
		</div>

		<div class="ecv2pi-tabs" role="tablist">
			<button type="button" class="ecv2pi-tab is-active" role="tab" data-ecv2pi-tab="import" aria-selected="true"><span class="dashicons dashicons-upload" aria-hidden="true"></span> <?php esc_html_e( 'Import', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2pi-tab" role="tab" data-ecv2pi-tab="export" aria-selected="false"><span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'Export', 'wp-easycart' ); ?></button>
			<button type="button" class="ecv2pi-tab" role="tab" data-ecv2pi-tab="help" aria-selected="false"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span> <?php esc_html_e( 'Help', 'wp-easycart' ); ?></button>
		</div>

		<div class="ecv2-modal-body">

			<!-- Import -->
			<div class="ecv2pi-pane" data-ecv2pi-pane="import">
				<div class="ecv2pi-step" data-ecv2pi-step="pick">
					<div class="ecv2pi-drop" id="ecv2pi_drop" tabindex="0">
						<span class="dashicons dashicons-upload" aria-hidden="true"></span>
						<b><?php esc_html_e( 'Drop a CSV here or choose a file', 'wp-easycart' ); ?></b>
						<span class="ecv2-sub"><?php
							/* translators: %s is the server upload limit, e.g. 8 MB. */
							echo esc_html( sprintf( __( 'First row must be the column names. Up to %s per file.', 'wp-easycart' ), $ecv2pi_max_upload ) );
						?></span>
						<input type="file" accept=".csv,text/csv" id="ecv2pi_file" aria-label="<?php esc_attr_e( 'Choose a CSV file', 'wp-easycart' ); ?>" />
					</div>
					<div class="ecv2pi-how">
						<div class="ecv2pi-how-item"><span class="ecv2-chip ecv2-chip-green">0</span><div><b><?php esc_html_e( 'product_id 0 adds a product', 'wp-easycart' ); ?></b><span><?php esc_html_e( 'Its model_number must be new to the store and unique in the file.', 'wp-easycart' ); ?></span></div></div>
						<div class="ecv2pi-how-item"><span class="ecv2-chip ecv2-chip-blue">#</span><div><b><?php esc_html_e( 'An exported product_id updates that product', 'wp-easycart' ); ?></b><span><?php esc_html_e( 'Every column in the file is written, so start from an export to keep the values you are not changing.', 'wp-easycart' ); ?></span></div></div>
						<div class="ecv2pi-how-item"><span class="ecv2-chip ecv2-chip-gray">?</span><div><b><?php esc_html_e( 'You get a preview first', 'wp-easycart' ); ?></b><span><?php esc_html_e( 'Nothing is written until you confirm the counts on the next screen.', 'wp-easycart' ); ?></span></div></div>
					</div>
				</div>
				<div class="ecv2pi-step" data-ecv2pi-step="busy" hidden><div class="ecv2pi-busy"><span class="dashicons dashicons-update ecv2-spin" aria-hidden="true"></span> <span id="ecv2pi_busy_text"></span></div><div class="ecv2pi-bar" id="ecv2pi_upload_bar_wrap" hidden><span id="ecv2pi_upload_bar"></span></div></div>
				<div class="ecv2pi-step" data-ecv2pi-step="preview" hidden id="ecv2pi_preview"></div>
				<div class="ecv2pi-step" data-ecv2pi-step="run" hidden>
					<div class="ecv2pi-progress">
						<div class="ecv2pi-bar"><span id="ecv2pi_run_bar"></span></div>
						<div class="ecv2-sub" id="ecv2pi_run_text"></div>
					</div>
				</div>
				<div class="ecv2pi-step" data-ecv2pi-step="done" hidden id="ecv2pi_done"></div>
			</div>

			<!-- Export -->
			<div class="ecv2pi-pane" data-ecv2pi-pane="export" hidden>
				<div class="ecv2pi-cards">
					<div class="ecv2pi-card">
						<span class="dashicons dashicons-database-export" aria-hidden="true"></span>
						<b><?php esc_html_e( 'Export all products', 'wp-easycart' ); ?></b>
						<span class="ecv2-sub"><?php esc_html_e( 'One CSV with every product in the store, streamed so large catalogs download without timing out.', 'wp-easycart' ); ?></span>
						<a href="<?php echo esc_url( $ecv2pi_export_all ); ?>" class="ecv2-btn ecv2-btn-primary ecv2-btn-sm" id="ecv2pi_export_all" target="_blank" rel="noopener"><span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'Export all', 'wp-easycart' ); ?></a>
					</div>
					<div class="ecv2pi-card">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<b><?php esc_html_e( 'Export selected products', 'wp-easycart' ); ?></b>
						<span class="ecv2-sub" id="ecv2pi_export_selected_hint"><?php esc_html_e( 'Tick products in the list to export only those.', 'wp-easycart' ); ?></span>
						<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecv2pi_export_selected" disabled><span class="dashicons dashicons-download" aria-hidden="true"></span> <span id="ecv2pi_export_selected_label"><?php esc_html_e( 'Export selected', 'wp-easycart' ); ?></span></button>
					</div>
				</div>
				<div class="ecos-note ecv2pi-note info">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<div>
						<b><?php esc_html_e( 'What is in the file', 'wp-easycart' ); ?></b>
						<?php esc_html_e( 'One row per product with every product field, plus categories ( category ids ), price_tiers ( quantity,price pairs ), b2b_prices ( role,price pairs ) and advanced_option_ids. Open it in a spreadsheet, change what you need and import it back: the product_id column keeps each row linked to its product, and a row with product_id 0 becomes a new product.', 'wp-easycart' ); ?>
					</div>
				</div>
			</div>

			<!-- Help -->
			<div class="ecv2pi-pane" data-ecv2pi-pane="help" hidden>
				<div class="ecv2pi-cards">
					<div class="ecv2pi-card">
						<span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
						<b><?php esc_html_e( 'Sample CSV', 'wp-easycart' ); ?></b>
						<span class="ecv2-sub"><?php esc_html_e( 'The exact header row the export produces, with one example product you can copy from.', 'wp-easycart' ); ?></span>
						<button type="button" class="ecv2-btn ecv2-btn-sm" id="ecv2pi_sample"><span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'Download sample', 'wp-easycart' ); ?></button>
					</div>
					<div class="ecv2pi-card">
						<span class="dashicons dashicons-book" aria-hidden="true"></span>
						<b><?php esc_html_e( 'Importer guide', 'wp-easycart' ); ?></b>
						<span class="ecv2-sub"><?php esc_html_e( 'Every column explained, with the formats for images, categories, price tiers and option sets.', 'wp-easycart' ); ?></span>
						<a href="<?php echo esc_url( wp_easycart_admin_product_import::DOCS_URL ); ?>" class="ecv2-btn ecv2-btn-sm" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> <?php esc_html_e( 'Open the docs', 'wp-easycart' ); ?></a>
					</div>
				</div>
				<div class="ecv2pi-tips">
					<b><?php esc_html_e( 'Good to know', 'wp-easycart' ); ?></b>
					<ul>
						<li><?php esc_html_e( 'product_id and model_number are required; every other column is optional, but a column that is present is written to every row.', 'wp-easycart' ); ?></li>
						<li><?php esc_html_e( 'Column names must match the export exactly ( lower case, underscores ). The preview points out any that do not.', 'wp-easycart' ); ?></li>
						<li><?php esc_html_e( 'The import stops at the first blank line or the first row without a model_number; the preview warns you when that would skip rows.', 'wp-easycart' ); ?></li>
						<li><?php esc_html_e( 'model_number keeps letters, numbers, dashes, underscores and slashes; other punctuation is removed.', 'wp-easycart' ); ?></li>
						<li><?php esc_html_e( 'product_images uses “ml:” for Media Library ids, e.g. ml:123,ml:124. price_tiers is quantity,price pairs and b2b_prices is role,price pairs, each pair separated by commas.', 'wp-easycart' ); ?></li>
						<li><?php esc_html_e( 'Big files import in chunks of 100 rows, so a 10,000-row file takes a few minutes — keep the window open until it finishes.', 'wp-easycart' ); ?></li>
					</ul>
				</div>
			</div>

		</div>

		<div class="ecv2-modal-footer">
			<div class="ecv2pi-foot-left" id="ecv2pi_foot_left"></div>
			<div class="ecv2-modal-footer-right" id="ecv2pi_foot">
				<button type="button" class="ecv2-btn" data-ecv2pi-close><?php esc_html_e( 'Close', 'wp-easycart' ); ?></button>
			</div>
		</div>
	</div>
</div>
