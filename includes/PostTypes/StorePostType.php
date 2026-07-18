<?php
/**
 * Registers the `store` custom post type.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class StorePostType
 */
class StorePostType {

	const POST_TYPE = 'store';

	/**
	 * List of meta keys used by this CPT and their sanitize callbacks.
	 *
	 * @return array<string,string>
	 */
	public static function meta_fields() {
		return array(
			'_asl_brand'            => array( __CLASS__, 'sanitize_text' ),
			'_asl_country'          => array( __CLASS__, 'sanitize_text' ),
			'_asl_city'             => array( __CLASS__, 'sanitize_text' ),
			'_asl_address'          => array( __CLASS__, 'sanitize_text' ),
			'_asl_coordinates'      => array( __CLASS__, 'sanitize_text' ),
			'_asl_latitude'         => array( __CLASS__, 'sanitize_float' ),
			'_asl_longitude'        => array( __CLASS__, 'sanitize_float' ),
			'_asl_phone'            => array( __CLASS__, 'sanitize_text' ),
			'_asl_email'            => array( __CLASS__, 'sanitize_email_field' ),
			'_asl_opening_hours'    => array( __CLASS__, 'sanitize_textarea' ),
			'_asl_details'          => array( __CLASS__, 'sanitize_html' ),
			'_asl_directions_url'   => array( __CLASS__, 'sanitize_url' ),
		);
	}

	/**
	 * Sanitize plain text meta.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_text( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize a textarea field.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_textarea( $value ) {
		return sanitize_textarea_field( $value );
	}

	/**
	 * Sanitize an email field.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_email_field( $value ) {
		return sanitize_email( $value );
	}

	/**
	 * Sanitize limited HTML meta.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_html( $value ) {
		return wp_kses_post( $value );
	}

	/**
	 * Sanitize a URL field.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_url( $value ) {
		return esc_url_raw( $value );
	}

	/**
	 * Sanitize a latitude/longitude style float value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_float( $value ) {
		return (string) floatval( $value );
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'admin_init', array( $this, 'migrate_legacy_data' ) );

		// Brand taxonomy term meta fields hooks
		add_action( 'store_brand_add_form_fields', array( $this, 'add_brand_fields' ), 10, 2 );
		add_action( 'store_brand_edit_form_fields', array( $this, 'edit_brand_fields' ), 10, 2 );
		add_action( 'created_store_brand', array( $this, 'save_brand_fields' ), 10, 2 );
		add_action( 'edited_store_brand', array( $this, 'save_brand_fields' ), 10, 2 );

		// Country taxonomy term meta fields hooks
		add_action( 'store_country_add_form_fields', array( $this, 'add_country_fields' ), 10, 2 );
		add_action( 'store_country_edit_form_fields', array( $this, 'edit_country_fields' ), 10, 2 );
		add_action( 'created_store_country', array( $this, 'save_country_fields' ), 10, 2 );
		add_action( 'edited_store_country', array( $this, 'save_country_fields' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_term_assets' ) );

		// Allow SVG uploads in WordPress media library.
		add_filter( 'upload_mimes', array( $this, 'allow_svg_uploads' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check_svg_filetype' ), 10, 4 );
	}

	/**
	 * Register the `store` CPT.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Stores', 'post type general name', 'aseer-store-locator' ),
			'singular_name'      => _x( 'Store', 'post type singular name', 'aseer-store-locator' ),
			'menu_name'          => __( 'Store Locator', 'aseer-store-locator' ),
			'add_new'            => __( 'Add New Store', 'aseer-store-locator' ),
			'add_new_item'       => __( 'Add New Store', 'aseer-store-locator' ),
			'edit_item'          => __( 'Edit Store', 'aseer-store-locator' ),
			'new_item'           => __( 'New Store', 'aseer-store-locator' ),
			'view_item'          => __( 'View Store', 'aseer-store-locator' ),
			'search_items'       => __( 'Search Stores', 'aseer-store-locator' ),
			'not_found'          => __( 'No stores found', 'aseer-store-locator' ),
			'not_found_in_trash' => __( 'No stores found in Trash', 'aseer-store-locator' ),
			'all_items'          => __( 'All Stores', 'aseer-store-locator' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => false, // We expose a dedicated, filtered REST controller instead.
			'menu_icon'          => 'dashicons-store',
			'capability_type'    => 'post',
			'hierarchical'       => false,
			'supports'           => array( 'title', 'thumbnail' ),
			'has_archive'        => false,
			// Enables pretty URLs like /store/some-branch-name/ for the
			// "listing detail" page (see TemplateLoader + templates/single-store.php).
			// NOTE: after upgrading an existing install, visit
			// Settings → Permalinks and click Save once to flush rewrite
			// rules — WordPress only does this automatically on activation.
			'rewrite'            => array(
				'slug'       => 'store',
				'with_front' => false,
			),
			'exclude_from_search' => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register meta fields for sanitization / capability control.
	 *
	 * @return void
	 */
	public function register_meta() {
		foreach ( self::meta_fields() as $key => $sanitize_callback ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => $sanitize_callback,
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
					'show_in_rest'      => false,
				)
			);
		}
	}

	/**
	 * Register the `store_brand` and `store_country` custom taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		// Brands
		$labels = array(
			'name'              => _x( 'Brands', 'taxonomy general name', 'aseer-store-locator' ),
			'singular_name'     => _x( 'Brand', 'taxonomy singular name', 'aseer-store-locator' ),
			'search_items'      => __( 'Search Brands', 'aseer-store-locator' ),
			'all_items'         => __( 'All Brands', 'aseer-store-locator' ),
			'parent_item'       => __( 'Parent Brand', 'aseer-store-locator' ),
			'parent_item_colon' => __( 'Parent Brand:', 'aseer-store-locator' ),
			'edit_item'         => __( 'Edit Brand', 'aseer-store-locator' ),
			'update_item'       => __( 'Update Brand', 'aseer-store-locator' ),
			'add_new_item'      => __( 'Add New Brand', 'aseer-store-locator' ),
			'new_item_name'     => __( 'New Brand Name', 'aseer-store-locator' ),
			'menu_name'         => __( 'Brands', 'aseer-store-locator' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'store-brand' ),
		);

		register_taxonomy( 'store_brand', self::POST_TYPE, $args );

		// Countries
		$country_labels = array(
			'name'              => _x( 'Countries', 'taxonomy general name', 'aseer-store-locator' ),
			'singular_name'     => _x( 'Country', 'taxonomy singular name', 'aseer-store-locator' ),
			'search_items'      => __( 'Search Countries', 'aseer-store-locator' ),
			'all_items'         => __( 'All Countries', 'aseer-store-locator' ),
			'parent_item'       => __( 'Parent Country', 'aseer-store-locator' ),
			'parent_item_colon' => __( 'Parent Country:', 'aseer-store-locator' ),
			'edit_item'         => __( 'Edit Country', 'aseer-store-locator' ),
			'update_item'       => __( 'Update Country', 'aseer-store-locator' ),
			'add_new_item'      => __( 'Add New Country', 'aseer-store-locator' ),
			'new_item_name'     => __( 'New Country Name', 'aseer-store-locator' ),
			'menu_name'         => __( 'Countries', 'aseer-store-locator' ),
		);

		$country_args = array(
			'hierarchical'      => true,
			'labels'            => $country_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'store-country' ),
		);

		register_taxonomy( 'store_country', self::POST_TYPE, $country_args );
	}

	/**
	 * Run a quick one-time lazy migration of legacy _asl_brand and _asl_country postmeta to new custom taxonomies.
	 *
	 * @return void
	 */
	public function migrate_legacy_data() {
		// 1. Migrate Brands
		$brand_stores = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'store_brand',
						'operator' => 'NOT EXISTS',
					),
				),
				'meta_query'     => array(
					array(
						'key'     => '_asl_brand',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		if ( ! empty( $brand_stores ) ) {
			foreach ( $brand_stores as $store ) {
				$brand_name = get_post_meta( $store->ID, '_asl_brand', true );
				if ( ! empty( $brand_name ) ) {
					$term = get_term_by( 'name', $brand_name, 'store_brand' );
					if ( ! $term ) {
						$term_data = wp_insert_term( $brand_name, 'store_brand' );
						$term_id   = ! is_wp_error( $term_data ) ? $term_data['term_id'] : 0;
					} else {
						$term_id = $term->term_id;
					}
					if ( $term_id ) {
						wp_set_object_terms( $store->ID, array( (int) $term_id ), 'store_brand' );
					}
				}
			}
		}

		// 2. Migrate Countries
		$country_stores = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'store_country',
						'operator' => 'NOT EXISTS',
					),
				),
				'meta_query'     => array(
					array(
						'key'     => '_asl_country',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		if ( ! empty( $country_stores ) ) {
			foreach ( $country_stores as $store ) {
				$country_name = get_post_meta( $store->ID, '_asl_country', true );
				if ( ! empty( $country_name ) ) {
					$term = get_term_by( 'name', $country_name, 'store_country' );
					if ( ! $term ) {
						$term_data = wp_insert_term( $country_name, 'store_country' );
						$term_id   = ( ! is_wp_error( $term_data ) && $term_data ) ? $term_data['term_id'] : 0;
					} else {
						$term_id = $term->term_id;
					}
					if ( $term_id ) {
						wp_set_object_terms( $store->ID, array( (int) $term_id ), 'store_country' );
					}
				}
			}
		}
	}

	/**
	 * Render fields on the Add New Country screen.
	 *
	 * @return void
	 */
	public function add_country_fields() {
		?>
		<div class="form-field term-group">
			<label for="asl_country_flag_id"><?php esc_html_e( 'Country Flag', 'aseer-store-locator' ); ?></label>
			<input type="hidden" id="asl_country_flag_id" name="asl_country_flag_id" value="" />
			<div id="asl_country_flag_preview"></div>
			<p style="margin-top: 5px;">
				<button type="button" class="button" id="asl_upload_flag_btn"><?php esc_html_e( 'Upload/Choose Image', 'aseer-store-locator' ); ?></button>
				<button type="button" class="button" id="asl_upload_flag_btn-clear"><?php esc_html_e( 'Clear', 'aseer-store-locator' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Upload or select an image for the country flag.', 'aseer-store-locator' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render fields on the Edit Country screen.
	 *
	 * @param \WP_Term $term Current term object.
	 * @return void
	 */
	public function edit_country_fields( $term ) {
		$flag_id   = get_term_meta( $term->term_id, 'asl_country_flag_id', true );
		$flag_html = '';
		if ( $flag_id ) {
			$flag_url = wp_get_attachment_url( $flag_id );
			if ( $flag_url ) {
				$flag_html = '<img src="' . esc_url( $flag_url ) . '" style="max-width:150px;max-height:100px;display:block;margin-top:10px;" />';
			}
		}
		?>
		<tr class="form-field term-group-wrap">
			<th scope="row">
				<label for="asl_country_flag_id"><?php esc_html_e( 'Country Flag', 'aseer-store-locator' ); ?></label>
			</th>
			<td>
				<input type="hidden" id="asl_country_flag_id" name="asl_country_flag_id" value="<?php echo esc_attr( $flag_id ); ?>" />
				<div id="asl_country_flag_preview"><?php echo $flag_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<p style="margin-top: 5px;">
					<button type="button" class="button" id="asl_upload_flag_btn"><?php esc_html_e( 'Upload/Choose Image', 'aseer-store-locator' ); ?></button>
					<button type="button" class="button" id="asl_upload_flag_btn-clear"><?php esc_html_e( 'Clear', 'aseer-store-locator' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'Upload or select an image for the country flag.', 'aseer-store-locator' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term meta fields when created or edited.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_country_fields( $term_id ) {
		if ( isset( $_POST['asl_country_flag_id'] ) ) {
			$flag_id = sanitize_text_field( wp_unslash( $_POST['asl_country_flag_id'] ) );
			update_term_meta( $term_id, 'asl_country_flag_id', $flag_id );
		}
	}

	/**
	 * Convert a country name (English, Spanish, Arabic) to its corresponding 2-letter ISO country code.
	 *
	 * @param string $country_name Country name.
	 * @return string ISO 2-letter code or empty string.
	 */
	public static function country_name_to_code( $country_name ) {
		$name = strtolower( trim( (string) $country_name ) );
		if ( empty( $name ) ) {
			return '';
		}

		$mapping = array(
			// Saudi Arabia
			'saudi arabia'                 => 'SA',
			'saudi'                        => 'SA',
			'ksa'                          => 'SA',
			'السعودية'                     => 'SA',
			'المملكة العربية السعودية'      => 'SA',

			// Kuwait
			'kuwait'                       => 'KW',
			'الكويت'                       => 'KW',

			// UAE
			'united arab emirates'         => 'AE',
			'uae'                          => 'AE',
			'emirates'                     => 'AE',
			'الإمارات العربية المتحدة'      => 'AE',
			'الامارات العربية المتحدة'      => 'AE',
			'الإمارات'                     => 'AE',
			'الامارات'                     => 'AE',

			// Qatar
			'qatar'                        => 'QA',
			'قطر'                          => 'QA',

			// Oman
			'oman'                         => 'OM',
			'عمان'                         => 'OM',
			'سلطنة عمان'                   => 'OM',

			// Bahrain
			'bahrain'                      => 'BH',
			'البحرين'                      => 'BH',
			'مملكة البحرين'                => 'BH',

			// Egypt
			'egypt'                        => 'EG',
			'مصر'                          => 'EG',
			'جمهورية مصر العربية'          => 'EG',

			// Jordan
			'jordan'                       => 'JO',
			'الأردن'                       => 'JO',
			'الاردن'                       => 'JO',
			'المملكة الأردنية الهاشمية'     => 'JO',

			// Spain
			'spain'                        => 'ES',
			'españa'                       => 'ES',
			'espana'                       => 'ES',
			'español'                      => 'ES',
			'espanol'                      => 'ES',
			'إسبانيا'                       => 'ES',
			'اسبانيا'                      => 'ES',

			// US
			'united states'                => 'US',
			'united states of america'     => 'US',
			'usa'                          => 'US',
			'us'                           => 'US',
			'الولايات المتحدة'             => 'US',
			'الولايات المتحدة الأمريكية'    => 'US',
			'الولايات المتحدة الامريكية'    => 'US',
			'أمريكا'                       => 'US',
			'امريكا'                       => 'US',

			// UK
			'united kingdom'               => 'GB',
			'uk'                           => 'GB',
			'britain'                      => 'GB',
			'great britain'                => 'GB',
			'المملكة المتحدة'               => 'GB',
			'بريطانيا'                     => 'GB',
		);

		return isset( $mapping[ $name ] ) ? $mapping[ $name ] : '';
	}

	/**
	 * Convert a 2-letter ISO country code (e.g. 'SA') into its corresponding flag emoji.
	 *
	 * @param string $country_code ISO 2-letter code.
	 * @return string Flag emoji or empty string.
	 */
	public static function country_code_to_flag( $country_code ) {
		$code = strtoupper( trim( (string) $country_code ) );
		if ( strlen( $code ) !== 2 ) {
			return '';
		}
		$chr_a = ord( 'A' );
		$first = ord( $code[0] ) - $chr_a + 0x1F1E6;
		$second = ord( $code[1] ) - $chr_a + 0x1F1E6;
		return html_entity_decode( '&#x' . dechex( $first ) . ';&#x' . dechex( $second ) . ';', ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Enqueue the WordPress media uploader scripts for term edit pages.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_term_assets( $hook ) {
		global $current_screen;
		if ( 'edit-tags.php' === $hook || 'term.php' === $hook ) {
			if ( $current_screen && ( 'store_brand' === $current_screen->taxonomy || 'store_country' === $current_screen->taxonomy ) ) {
				wp_enqueue_media();
				wp_add_inline_script(
					'jquery',
					"jQuery(document).ready(function($) {
						function setupUploader(buttonId, inputId, previewId, titleText) {
							titleText = titleText || '" . esc_js( __( 'Choose Image', 'aseer-store-locator' ) ) . "';
							$('body').on('click', buttonId, function(e) {
								e.preventDefault();
								var button = $(this);
								var custom_uploader = wp.media({
									title: titleText,
									layout: 'select',
									button: { text: '" . esc_js( __( 'Use Image', 'aseer-store-locator' ) ) . "' },
									multiple: false
								}).on('select', function() {
									var attachment = custom_uploader.state().get('selection').first().toJSON();
									$(inputId).val(attachment.id);
									$(previewId).html('<img src=\"' + attachment.url + '\" style=\"max-width:150px;max-height:100px;display:block;margin-top:10px;\" />');
								}).open();
							});
							$('body').on('click', buttonId + '-clear', function(e) {
								e.preventDefault();
								$(inputId).val('');
								$(previewId).html('');
							});
						}
						setupUploader('#asl_upload_thumbnail_btn', '#asl_brand_thumbnail_id', '#asl_brand_thumbnail_preview', '" . esc_js( __( 'Choose Brand Logo', 'aseer-store-locator' ) ) . "');
						setupUploader('#asl_upload_icon_btn', '#asl_brand_icon_id', '#asl_brand_icon_preview', '" . esc_js( __( 'Choose Brand Logo', 'aseer-store-locator' ) ) . "');
						setupUploader('#asl_upload_flag_btn', '#asl_country_flag_id', '#asl_country_flag_preview', '" . esc_js( __( 'Choose Country Flag', 'aseer-store-locator' ) ) . "');
					});"
				);
			}
		}
	}

	/**
	 * Render fields on the Add New Brand screen.
	 *
	 * @return void
	 */
	public function add_brand_fields() {
		?>
		<div class="form-field term-group">
			<label for="asl_brand_thumbnail_id"><?php esc_html_e( 'Full Brand Logo (Pill Logo)', 'aseer-store-locator' ); ?></label>
			<input type="hidden" id="asl_brand_thumbnail_id" name="asl_brand_thumbnail_id" value="" />
			<div id="asl_brand_thumbnail_preview"></div>
			<p style="margin-top: 5px;">
				<button type="button" class="button" id="asl_upload_thumbnail_btn"><?php esc_html_e( 'Upload/Choose Image', 'aseer-store-locator' ); ?></button>
				<button type="button" class="button" id="asl_upload_thumbnail_btn-clear"><?php esc_html_e( 'Clear', 'aseer-store-locator' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Bundled fallback is the full logo (e.g. Aseer Time, Papa Kanafa, Farooj) matching the brand selector pills on top of the locator widget.', 'aseer-store-locator' ); ?></p>
		</div>
		<div class="form-field term-group">
			<label for="asl_brand_icon_id"><?php esc_html_e( 'Icon Brand Logo (Circle Logo)', 'aseer-store-locator' ); ?></label>
			<input type="hidden" id="asl_brand_icon_id" name="asl_brand_icon_id" value="" />
			<div id="asl_brand_icon_preview"></div>
			<p style="margin-top: 5px;">
				<button type="button" class="button" id="asl_upload_icon_btn"><?php esc_html_e( 'Upload/Choose Image', 'aseer-store-locator' ); ?></button>
				<button type="button" class="button" id="asl_upload_icon_btn-clear"><?php esc_html_e( 'Clear', 'aseer-store-locator' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Bundled fallback is the circular logo icon matching the circular emblem on the store card listings.', 'aseer-store-locator' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render fields on the Edit Brand screen.
	 *
	 * @param \WP_Term $term Current term object.
	 * @return void
	 */
	public function edit_brand_fields( $term ) {
		$thumb_id   = get_term_meta( $term->term_id, 'asl_brand_thumbnail_id', true );
		$thumb_html = '';
		if ( $thumb_id ) {
			$thumb_url = wp_get_attachment_url( $thumb_id );
			if ( $thumb_url ) {
				$thumb_html = '<img src="' . esc_url( $thumb_url ) . '" style="max-width:150px;max-height:100px;display:block;margin-top:10px;" />';
			}
		}

		$icon_id   = get_term_meta( $term->term_id, 'asl_brand_icon_id', true );
		$icon_html = '';
		if ( $icon_id ) {
			$icon_url = wp_get_attachment_url( $icon_id );
			if ( $icon_url ) {
				$icon_html = '<img src="' . esc_url( $icon_url ) . '" style="max-width:150px;max-height:100px;display:block;margin-top:10px;" />';
			}
		}
		?>
		<tr class="form-field term-group-wrap">
			<th scope="row">
				<label for="asl_brand_thumbnail_id"><?php esc_html_e( 'Full Brand Logo (Pill Logo)', 'aseer-store-locator' ); ?></label>
			</th>
			<td>
				<input type="hidden" id="asl_brand_thumbnail_id" name="asl_brand_thumbnail_id" value="<?php echo esc_attr( $thumb_id ); ?>" />
				<div id="asl_brand_thumbnail_preview"><?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<p style="margin-top: 5px;">
					<button type="button" class="button" id="asl_upload_thumbnail_btn"><?php esc_html_e( 'Upload/Choose Image', 'aseer-store-locator' ); ?></button>
					<button type="button" class="button" id="asl_upload_thumbnail_btn-clear"><?php esc_html_e( 'Clear', 'aseer-store-locator' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'Bundled fallback is the full logo (e.g. Aseer Time, Papa Kanafa, Farooj) matching the brand selector pills on top of the locator widget.', 'aseer-store-locator' ); ?></p>
			</td>
		</tr>
		<tr class="form-field term-group-wrap">
			<th scope="row">
				<label for="asl_brand_icon_id"><?php esc_html_e( 'Icon Brand Logo (Circle Logo)', 'aseer-store-locator' ); ?></label>
			</th>
			<td>
				<input type="hidden" id="asl_brand_icon_id" name="asl_brand_icon_id" value="<?php echo esc_attr( $icon_id ); ?>" />
				<div id="asl_brand_icon_preview"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<p style="margin-top: 5px;">
					<button type="button" class="button" id="asl_upload_icon_btn"><?php esc_html_e( 'Upload/Choose Image', 'aseer-store-locator' ); ?></button>
					<button type="button" class="button" id="asl_upload_icon_btn-clear"><?php esc_html_e( 'Clear', 'aseer-store-locator' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'Bundled fallback is the circular logo icon matching the circular emblem on the store card listings.', 'aseer-store-locator' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save term meta fields when created or edited.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_brand_fields( $term_id ) {
		if ( isset( $_POST['asl_brand_thumbnail_id'] ) ) {
			$thumb_id = sanitize_text_field( wp_unslash( $_POST['asl_brand_thumbnail_id'] ) );
			update_term_meta( $term_id, 'asl_brand_thumbnail_id', $thumb_id );
		}
		if ( isset( $_POST['asl_brand_icon_id'] ) ) {
			$icon_id = sanitize_text_field( wp_unslash( $_POST['asl_brand_icon_id'] ) );
			update_term_meta( $term_id, 'asl_brand_icon_id', $icon_id );
		}
	}

	/**
	 * Allow SVG uploads in WordPress.
	 *
	 * @param array $mimes Allowed mime types.
	 * @return array
	 */
	public function allow_svg_uploads( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Bypass filetype check warning for SVGs in WordPress.
	 *
	 * @param array  $data     File data.
	 * @param string $file     File path.
	 * @param string $filename File name.
	 * @param array  $mimes    Mime types.
	 * @return array
	 */
	public function check_svg_filetype( $data, $file, $filename, $mimes ) {
		$filetype = wp_check_filetype( $filename, $mimes );
		if ( 'svg' === $filetype['ext'] ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	}
}

