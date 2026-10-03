<?php
/**
 * WP EasyCart Elementor templates, admin side ( 6.0.2 ).
 *
 * - Settings › Elementor sections "Product pages" and "Category pages" ( filter wp_easycart_settings_page_elementor ):
 *   the template every product / category page uses, Create, Edit, and a warning when an Elementor Pro Theme Builder
 *   template also targets those pages.
 * - The locked "Page layout" card on the product and category editors ( per-product / per-category layouts are Pro:
 *   upsell context 'elementor', feature 'template_overrides' ). WP EasyCart PRO prints the working card instead.
 *
 * @package  Wp_Easycart_Elementor
 * @since    6.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_EasyCart_Elementor_Templates_Settings' ) ) :

	/**
	 * Settings sections and editor cards.
	 */
	final class WP_EasyCart_Elementor_Templates_Settings {

		/**
		 * Add the hooks.
		 */
		public static function boot() {
			add_filter( 'wp_easycart_settings_page_elementor', array( __CLASS__, 'settings_page' ), 5 );
			add_action( 'wp_easycart_admin_product_details_v2_behavior_cards', array( __CLASS__, 'product_card' ), 30, 1 );
			add_action( 'wp_easycart_admin_category_editor_v2_cards', array( __CLASS__, 'category_card' ), 30, 1 );
		}

		/**
		 * Filter wp_easycart_settings_page_elementor: the two sections, right after the status section.
		 *
		 * @param array $page Declaration.
		 * @return array
		 */
		public static function settings_page( $page ) {
			if ( ! is_array( $page ) ) {
				return $page;
			}
			$sections = isset( $page['sections'] ) && is_array( $page['sections'] ) ? $page['sections'] : array();
			$ours     = array(
				'product-templates'  => self::section( 'product' ),
				'category-templates' => self::section( 'category' ),
			);
			$merged   = array();
			if ( isset( $sections['status'] ) ) {
				$merged['status'] = $sections['status'];
				unset( $sections['status'] );
			}
			$page['sections'] = $merged + $ours + $sections;
			return $page;
		}

		/**
		 * One section's declaration.
		 *
		 * @param string $type product | category.
		 * @return array
		 */
		private static function section( $type ) {
			$is_category = ( 'category' === $type );
			$section     = array(
				'title'   => $is_category ? __( 'Category pages', 'wp-easycart' ) : __( 'Product pages', 'wp-easycart' ),
				'hint'    => $is_category ? __( 'One Elementor layout for every category page', 'wp-easycart' ) : __( 'One Elementor layout for every product page', 'wp-easycart' ),
				'icon'    => $is_category ? 'grid' : 'box',
				'enqueue' => array( __CLASS__, 'enqueue' ),
				'fields'  => array(),
			);
			if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
				$section['fields'][ 'ecst_elementor_' . $type . '_template_off' ] = array(
					'type'   => 'html',
					'label'  => $section['title'],
					'render' => array( __CLASS__, 'render_inactive' ),
				);
				return $section;
			}
			$option                       = WP_EasyCart_Elementor_Templates::option_name( $type );
			$section['fields'][ $option ] = array(
				'type'     => 'select',
				'label'    => $is_category ? __( 'Layout for category pages', 'wp-easycart' ) : __( 'Layout for product pages', 'wp-easycart' ),
				'desc'     => $is_category ? __( 'Every category page shows this Elementor template instead of the standard category layout. Add the Shop widget to list the category\'s products with page numbers, sorting and filters ( it follows the page\'s category ).', 'wp-easycart' ) : __( 'Every product page shows this Elementor template instead of the standard product layout. Search results, sharing, analytics and hidden products work as before.', 'wp-easycart' ),
				'default'  => '0',
				'options'  => $is_category ? array( __CLASS__, 'category_options' ) : array( __CLASS__, 'product_options' ),
				'keywords' => $is_category ? array( 'elementor', 'template', 'category page', 'archive', 'product archive', 'layout', 'design' ) : array( 'elementor', 'template', 'product page', 'single product', 'product details', 'layout', 'design' ),
				'legacy'   => array(
					'page'    => 'elementor',
					'section' => $is_category ? 'Category pages' : 'Product pages',
					'label'   => 'New in 6.0.2',
				),
			);
			$section['fields'][ 'ecst_elementor_' . $type . '_template_tools' ] = array(
				'type'     => 'html',
				'label'    => $is_category ? __( 'Category template', 'wp-easycart' ) : __( 'Product template', 'wp-easycart' ),
				'template' => $type,
				'render'   => array( __CLASS__, 'render_tools' ),
			);
			return $section;
		}

		/**
		 * Options of the product select.
		 *
		 * @return array
		 */
		public static function product_options() {
			return self::options( 'product' );
		}

		/**
		 * Options of the category select.
		 *
		 * @return array
		 */
		public static function category_options() {
			return self::options( 'category' );
		}

		/**
		 * "WP EasyCart's standard layout" and every template of the type ( drafts marked ).
		 *
		 * @param string $type product | category.
		 * @return array
		 */
		private static function options( $type ) {
			$options = array( '0' => __( 'WP EasyCart\'s standard layout', 'wp-easycart' ) );
			foreach ( WP_EasyCart_Elementor_Templates::templates( $type ) as $id => $post ) {
				$options[ (string) $id ] = self::template_label( $post );
			}
			$current = (int) get_option( WP_EasyCart_Elementor_Templates::option_name( $type ), 0 );
			if ( $current > 0 && ! isset( $options[ (string) $current ] ) ) {
				/* The chosen template was deleted or changed type: keep the value visible so the note below can explain it. */
				$options[ (string) $current ] = __( 'A template that no longer exists', 'wp-easycart' );
			}
			return $options;
		}

		/**
		 * A template's name for lists ( "( draft )" when unpublished ).
		 *
		 * @param WP_Post $post Template.
		 * @return string
		 */
		private static function template_label( $post ) {
			$title = ( '' !== trim( $post->post_title ) ) ? $post->post_title : __( '( no title )', 'wp-easycart' );
			if ( 'publish' !== $post->post_status ) {
				/* translators: %s: template name. */
				$title = sprintf( __( '%s ( draft: publish it in Elementor to use it )', 'wp-easycart' ), $title );
			}
			return $title;
		}

		/**
		 * Section assets.
		 */
		public static function enqueue() {
			$base = plugins_url( 'admin/elementor/modules/templates/assets/', EC_PLUGIN_DIRECTORY . '/wpeasycart.php' );
			wp_enqueue_style( 'wpeasycart-elementor-templates-settings', $base . 'templates-settings.css', array(), EC_CURRENT_VERSION );
			wp_enqueue_script( 'wpeasycart-elementor-templates-settings', $base . 'templates-settings.js', array( 'jquery' ), EC_CURRENT_VERSION, true );
		}

		/**
		 * The note when Elementor is not active.
		 */
		public static function render_inactive() {
			echo '<p class="wpec-elt-note">' . esc_html__( 'Activate Elementor ( the free plugin is enough ) to design your product and category pages with it.', 'wp-easycart' ) . '</p>';
		}

		/**
		 * Create, Edit and the notes under a section's select.
		 *
		 * @param array $field The html row.
		 */
		public static function render_tools( $field ) {
			$type        = ( isset( $field['template'] ) && 'category' === $field['template'] ) ? 'category' : 'product';
			$option      = WP_EasyCart_Elementor_Templates::option_name( $type );
			$current     = (int) get_option( $option, 0 );
			$templates   = WP_EasyCart_Elementor_Templates::templates( $type );
			$is_category = ( 'category' === $type );

			echo '<div class="wpec-elt-tools" data-wpec-elt-select="' . esc_attr( 'ecst_f_' . $option ) . '">';

			/* Edit links: one per template, the chosen one shown ( templates-settings.js follows the select ). */
			foreach ( $templates as $id => $post ) {
				echo '<div class="wpec-elt-chosen" data-wpec-elt-template="' . esc_attr( (string) $id ) . '"' . ( $id === $current ? '' : ' hidden' ) . '>';
				echo '<a class="ecv2-btn ecv2-btn-sm ecv2-btn-primary" href="' . esc_url( WP_EasyCart_Elementor_Templates::edit_url( $id ) ) . '">' . esc_html__( 'Edit in Elementor', 'wp-easycart' ) . '</a>';
				if ( 'publish' !== $post->post_status ) {
					echo '<span class="wpec-elt-status is-draft">' . esc_html( $is_category ? __( 'Draft: category pages keep the standard layout until you publish this template in Elementor.', 'wp-easycart' ) : __( 'Draft: product pages keep the standard layout until you publish this template in Elementor.', 'wp-easycart' ) ) . '</span>';
				} else {
					echo '<span class="wpec-elt-status is-live">' . esc_html( $is_category ? __( 'In use on every category page.', 'wp-easycart' ) : __( 'In use on every product page.', 'wp-easycart' ) ) . '</span>';
					$sample = self::sample_url( $type );
					if ( '' !== $sample ) {
						echo ' <a class="wpec-elt-view" href="' . esc_url( $sample ) . '" target="_blank" rel="noopener">' . esc_html( $is_category ? __( 'View a category page', 'wp-easycart' ) : __( 'View a product page', 'wp-easycart' ) ) . '</a>';
					}
				}
				echo '</div>';
			}
			if ( $current > 0 && ! isset( $templates[ $current ] ) ) {
				echo '<p class="wpec-elt-note is-warning" data-wpec-elt-template="' . esc_attr( (string) $current ) . '">' . esc_html( $is_category ? __( 'The chosen template no longer exists, so category pages use the standard layout. Choose another template or create one.', 'wp-easycart' ) : __( 'The chosen template no longer exists, so product pages use the standard layout. Choose another template or create one.', 'wp-easycart' ) ) . '</p>';
			}

			/* Create. */
			echo '<div class="wpec-elt-create">';
			if ( WP_EasyCart_Elementor_Templates::user_can_manage() ) {
				echo '<a class="ecv2-btn ecv2-btn-sm" href="' . esc_url( WP_EasyCart_Elementor_Templates::create_url( $type ) ) . '">' . esc_html( $is_category ? __( 'Create a category template', 'wp-easycart' ) : __( 'Create a product template', 'wp-easycart' ) ) . '</a>';
			}
			echo '<span class="wpec-elt-hint">' . esc_html( $is_category ? __( 'Opens a new template in Elementor, showing one of your categories while you design. With no category template chosen yet, it becomes the one your category pages use when you publish it.', 'wp-easycart' ) : __( 'Opens a new template in Elementor, showing one of your products while you design. With no product template chosen yet, it becomes the one your product pages use when you publish it.', 'wp-easycart' ) ) . '</span>';
			echo '</div>';

			self::render_conflicts( $type, $current );
			echo '</div>';
		}

		/**
		 * The address of one product or category page, to look at the result.
		 *
		 * @param string $type product | category.
		 * @return string
		 */
		private static function sample_url( $type ) {
			global $wpdb;
			if ( 'category' === $type ) {
				$row = $wpdb->get_row( 'SELECT category_id, post_id FROM ec_category WHERE is_active = 1 AND post_id > 0 ORDER BY priority DESC, category_name ASC LIMIT 1' );
				return $row ? (string) wp_easycart_elementor_templates_link( 'category', $row ) : '';
			}
			$post_id = (int) $wpdb->get_var( 'SELECT post_id FROM ec_product WHERE activate_in_store = 1 AND post_id > 0 ORDER BY product_id ASC LIMIT 1' );
			$link    = $post_id ? get_permalink( $post_id ) : '';
			return $link ? (string) $link : '';
		}

		/**
		 * Theme Builder single templates that also draw product ( or category ) pages, id => title.
		 *
		 * @param string $type product | category.
		 * @return array
		 */
		public static function theme_builder_conflicts( $type ) {
			if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
				return array();
			}
			$item_mode = (bool) get_option( 'ec_option_use_custom_post_theme_template' );
			$ours      = ( 'category' === $type ) ? array( 'wp_easycart_category' ) : array( 'wp_easycart_product', 'wp_easycart_product_in_category' );
			$found     = array();
			foreach ( WP_EasyCart_Elementor_Templates::theme_builder_templates( 'single' ) as $template_id => $conditions ) {
				$template_id = (int) $template_id;
				if ( 'publish' !== get_post_status( $template_id ) ) {
					continue;
				}
				foreach ( (array) $conditions as $condition ) {
					$c = WP_EasyCart_Elementor_Templates::parse_condition( $condition );
					if ( 'include' !== $c['type'] ) {
						continue;
					}
					$hit = false;
					if ( 'general' === $c['name'] || ( 'wp_easycart' === $c['name'] && '' === $c['sub_name'] ) ) {
						$hit = true;
					} elseif ( 'singular' === $c['name'] ) {
						/* Store items are drawn as pages unless "Use the theme's post template for products" is on. */
						$hit = ( '' === $c['sub_name'] ) || ( ! $item_mode && 'page' === $c['sub_name'] && ! $c['sub_id'] ) || ( $item_mode && 'ec_store' === $c['sub_name'] );
					} elseif ( 'wp_easycart' === $c['name'] ) {
						$hit = in_array( $c['sub_name'], $ours, true );
					} elseif ( in_array( $c['name'], $ours, true ) ) {
						$hit = true;
					}
					if ( $hit ) {
						$found[ $template_id ] = get_the_title( $template_id );
						break;
					}
				}
			}
			return $found;
		}

		/**
		 * The warning when both WP EasyCart and an Elementor Pro Theme Builder template target the same pages.
		 *
		 * @param string $type    product | category.
		 * @param int    $current The chosen WP EasyCart template.
		 */
		private static function render_conflicts( $type, $current ) {
			$conflicts = self::theme_builder_conflicts( $type );
			if ( empty( $conflicts ) ) {
				return;
			}
			$is_category = ( 'category' === $type );
			$links       = array();
			foreach ( $conflicts as $id => $title ) {
				$links[] = '<a href="' . esc_url( WP_EasyCart_Elementor_Templates::edit_url( $id ) ) . '">' . esc_html( '' !== $title ? $title : '#' . $id ) . '</a>';
			}
			echo '<div class="wpec-elt-conflict" role="note"' . ( $current > 0 ? '' : ' data-wpec-elt-when-set hidden' ) . '>';
			echo '<strong>' . esc_html( $is_category ? __( 'An Elementor Pro Theme Builder template also designs your category pages', 'wp-easycart' ) : __( 'An Elementor Pro Theme Builder template also designs your product pages', 'wp-easycart' ) ) . '</strong>';
			echo '<span>';
			/* translators: %s: links to the Theme Builder templates. */
			echo wp_kses_post( sprintf( __( 'Theme Builder: %s.', 'wp-easycart' ), implode( ', ', $links ) ) ) . ' ';
			echo esc_html( $is_category ? __( 'That template wins: Elementor Pro replaces the whole page before WP EasyCart draws the category, so the WP EasyCart template chosen here only shows if the Theme Builder template contains a Post Content widget. To use the template chosen here, remove category pages from the Theme Builder template\'s display conditions.', 'wp-easycart' ) : __( 'That template wins: Elementor Pro replaces the whole page before WP EasyCart draws the product, so the WP EasyCart template chosen here only shows if the Theme Builder template contains a Post Content widget. To use the template chosen here, remove product pages from the Theme Builder template\'s display conditions.', 'wp-easycart' ) );
			echo '</span></div>';
		}

		// Product and category editors.

		/**
		 * Whether the locked card shows: Elementor active and the Pro feature not unlocked.
		 *
		 * @return bool
		 */
		private static function show_locked_card() {
			return defined( 'ELEMENTOR_VERSION' ) && function_exists( 'wp_easycart_elementor_pro_feature' ) && 'active' !== wp_easycart_elementor_pro_feature( 'template_overrides' );
		}

		/**
		 * Action wp_easycart_admin_product_details_v2_behavior_cards: the product's layout ( locked ).
		 *
		 * @param object $product The product.
		 */
		public static function product_card( $product ) {
			if ( ! self::show_locked_card() || ! is_object( $product ) || empty( $product->product_id ) ) {
				return;
			}
			self::print_locked_card( 'product', 'ecdv2-elementor-layout-locked' );
		}

		/**
		 * Action wp_easycart_admin_category_editor_v2_cards: the category's layout ( locked ).
		 *
		 * @param object $category ec_category row.
		 */
		public static function category_card( $category ) {
			if ( ! self::show_locked_card() || ! is_object( $category ) || empty( $category->category_id ) ) {
				return;
			}
			self::print_locked_card( 'category', 'catv2-elementor-layout-locked' );
		}

		/**
		 * The locked card: what the page uses today, where to change it for every page, and the Pro choice.
		 *
		 * @param string $type product | category.
		 * @param string $id   Card id.
		 */
		private static function print_locked_card( $type, $id ) {
			$is_category = ( 'category' === $type );
			$store       = WP_EasyCart_Elementor_Templates::store_template( $type );
			$uses        = $store ? get_the_title( $store ) : __( 'WP EasyCart\'s standard layout', 'wp-easycart' );
			$badge       = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::badge_for( 'elementor', 'pro', 'template_overrides' ) : __( 'Pro', 'wp-easycart' );
			$onclick     = class_exists( 'wp_easycart_admin_upsell' ) ? wp_easycart_admin_upsell::onclick( 'elementor', 'template_overrides' ) : '';
			$settings    = admin_url( 'admin.php?page=wp-easycart-settings&subpage=elementor' );
			?>
			<div class="ecdv2-card" id="<?php echo esc_attr( $id ); ?>">
				<div class="ecdv2-card-header">
					<h3 class="ecdv2-card-title"><?php esc_html_e( 'Page layout', 'wp-easycart' ); ?></h3>
					<span class="ecdv2-card-hint"><?php echo esc_html( $is_category ? __( 'The Elementor template this category page uses', 'wp-easycart' ) : __( 'The Elementor template this product page uses', 'wp-easycart' ) ); ?></span>
				</div>
				<div class="ecdv2-card-body">
					<div class="ecdv2-grid">
						<div class="ecdv2-field ecdv2-field-full">
							<label class="ecdv2-label" for="<?php echo esc_attr( $id . '-select' ); ?>"><?php esc_html_e( 'Layout', 'wp-easycart' ); ?></label>
							<select class="ecv2-select" id="<?php echo esc_attr( $id . '-select' ); ?>" disabled>
								<?php /* translators: %s: template name or "WP EasyCart's standard layout". */ ?>
								<option><?php echo esc_html( sprintf( $is_category ? __( 'Use the store\'s category template ( %s )', 'wp-easycart' ) : __( 'Use the store\'s product template ( %s )', 'wp-easycart' ), $uses ) ); ?></option>
							</select>
							<span class="ecdv2-field-desc">
								<?php echo esc_html( $is_category ? __( 'Every category uses the layout chosen in Settings › Elementor › Category pages.', 'wp-easycart' ) : __( 'Every product uses the layout chosen in Settings › Elementor › Product pages.', 'wp-easycart' ) ); ?>
								<a href="<?php echo esc_url( $settings ); ?>"><?php esc_html_e( 'Open Settings › Elementor', 'wp-easycart' ); ?></a>
							</span>
						</div>
					</div>
					<button type="button" class="ecv2-btn ecv2-btn-sm" onclick="<?php echo esc_attr( $onclick ); ?>">
						<span class="dashicons dashicons-lock" aria-hidden="true"></span>
						<?php echo esc_html( $is_category ? __( 'Give this category its own layout', 'wp-easycart' ) : __( 'Give this product its own layout', 'wp-easycart' ) ); ?>
						<span class="ecv2-cl-pro-badge"><?php echo esc_html( $badge ); ?></span>
					</button>
				</div>
			</div>
			<?php
		}
	}

endif;
