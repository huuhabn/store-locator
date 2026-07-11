<?php
/**
 * Plugin settings: marker appearance, locator colors, and map defaults.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Admin;

use Aseer\StoreLocator\PostTypes\StorePostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	const OPTION_KEY    = 'asl_settings';
	const PAGE_SLUG     = 'asl-settings';
	const OPTION_GROUP  = 'asl_settings_group';
	const SECTION_MARKER = 'asl_marker_section';
	const SECTION_COLOR  = 'asl_color_section';
	const SECTION_MAP    = 'asl_map_section';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Default option values.
	 *
	 * @return array<string,string>
	 */
	public static function defaults() {
		return array(
			'marker_color'        => '#111111',
			'marker_icon_url'     => '',
			'primary_color'       => '#111111',
			'panel_color'         => '#fbe9ea',
			'map_provider'        => 'leaflet',
			'google_maps_api_key' => '',
			'tile_style'          => 'osm',
			'default_center_lat'  => '20',
			'default_center_lng'  => '0',
			'default_zoom'        => '4',
			'scroll_zoom'         => '0',
		);
	}

	/**
	 * Get the current settings, merged with defaults so callers never have
	 * to null-check individual keys.
	 *
	 * @return array<string,string>
	 */
	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Register the "Settings" submenu page under the Store Locator CPT menu.
	 *
	 * @return void
	 */
	public function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=' . StorePostType::POST_TYPE,
			__( 'Store Locator Settings', 'aseer-store-locator' ),
			__( 'Settings', 'aseer-store-locator' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue the core color picker on our settings page only.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page identity check, not processing input.
		if ( ! isset( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			"jQuery(function(\$){ \$('.asl-color-field').wpColorPicker(); });"
		);

		// Media Library uploader for the "Custom Marker Icon URL" field.
		wp_enqueue_media();

		wp_enqueue_style(
			'asl-admin-settings',
			ASL_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			ASL_VERSION
		);

		wp_enqueue_script(
			'asl-admin-settings',
			ASL_PLUGIN_URL . 'assets/js/admin-settings.js',
			array( 'jquery', 'media-editor' ),
			ASL_VERSION,
			true
		);

		wp_localize_script(
			'asl-admin-settings',
			'ASL_Admin',
			array(
				'chooseImageTitle' => __( 'Choose Marker Icon', 'aseer-store-locator' ),
				'useImageText'     => __( 'Use this image', 'aseer-store-locator' ),
			)
		);
	}

	/**
	 * Register the setting, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( self::SECTION_MARKER, __( 'Marker Settings', 'aseer-store-locator' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'marker_color', __( 'Marker Color', 'aseer-store-locator' ), array( $this, 'field_marker_color' ), self::PAGE_SLUG, self::SECTION_MARKER );
		add_settings_field( 'marker_icon_url', __( 'Custom Marker Icon URL', 'aseer-store-locator' ), array( $this, 'field_marker_icon_url' ), self::PAGE_SLUG, self::SECTION_MARKER );

		add_settings_section( self::SECTION_COLOR, __( 'Locator Colors', 'aseer-store-locator' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'primary_color', __( 'Primary / Button Color', 'aseer-store-locator' ), array( $this, 'field_primary_color' ), self::PAGE_SLUG, self::SECTION_COLOR );
		add_settings_field( 'panel_color', __( 'Search Panel Background', 'aseer-store-locator' ), array( $this, 'field_panel_color' ), self::PAGE_SLUG, self::SECTION_COLOR );

		add_settings_section( self::SECTION_MAP, __( 'Map Settings', 'aseer-store-locator' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'map_provider', __( 'Map Provider', 'aseer-store-locator' ), array( $this, 'field_map_provider' ), self::PAGE_SLUG, self::SECTION_MAP );
		add_settings_field( 'google_maps_api_key', __( 'Google Maps API Key', 'aseer-store-locator' ), array( $this, 'field_google_maps_api_key' ), self::PAGE_SLUG, self::SECTION_MAP );
		add_settings_field( 'tile_style', __( 'Map Style', 'aseer-store-locator' ), array( $this, 'field_tile_style' ), self::PAGE_SLUG, self::SECTION_MAP );
		add_settings_field( 'default_center', __( 'Default Map Center', 'aseer-store-locator' ), array( $this, 'field_default_center' ), self::PAGE_SLUG, self::SECTION_MAP );
		add_settings_field( 'default_zoom', __( 'Default Zoom Level', 'aseer-store-locator' ), array( $this, 'field_default_zoom' ), self::PAGE_SLUG, self::SECTION_MAP );
		add_settings_field( 'scroll_zoom', __( 'Scroll Wheel Zoom', 'aseer-store-locator' ), array( $this, 'field_scroll_zoom' ), self::PAGE_SLUG, self::SECTION_MAP );
	}

	/**
	 * Sanitize submitted settings before saving.
	 *
	 * @param mixed $input Raw POSTed value for the option.
	 * @return array<string,string>
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		$clean['marker_color']    = ! empty( $input['marker_color'] ) ? sanitize_hex_color( $input['marker_color'] ) : $defaults['marker_color'];
		$clean['marker_icon_url'] = ! empty( $input['marker_icon_url'] ) ? esc_url_raw( $input['marker_icon_url'] ) : '';
		$clean['primary_color']   = ! empty( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : $defaults['primary_color'];
		$clean['panel_color']     = ! empty( $input['panel_color'] ) ? sanitize_hex_color( $input['panel_color'] ) : $defaults['panel_color'];

		// Fall back to the previous default if sanitize_hex_color() rejected
		// an invalid value (it returns null on failure).
		foreach ( array( 'marker_color', 'primary_color', 'panel_color' ) as $color_key ) {
			if ( empty( $clean[ $color_key ] ) ) {
				$clean[ $color_key ] = $defaults[ $color_key ];
			}
		}

		$allowed_styles      = array( 'osm', 'positron', 'dark', 'voyager' );
		$requested_style     = isset( $input['tile_style'] ) ? sanitize_key( $input['tile_style'] ) : '';
		$clean['tile_style'] = in_array( $requested_style, $allowed_styles, true ) ? $requested_style : $defaults['tile_style'];

		$allowed_providers      = array( 'leaflet', 'google' );
		$requested_provider     = isset( $input['map_provider'] ) ? sanitize_key( $input['map_provider'] ) : '';
		$clean['map_provider']  = in_array( $requested_provider, $allowed_providers, true ) ? $requested_provider : $defaults['map_provider'];
		$clean['google_maps_api_key'] = ! empty( $input['google_maps_api_key'] ) ? sanitize_text_field( $input['google_maps_api_key'] ) : '';

		$lat = isset( $input['default_center_lat'] ) && '' !== $input['default_center_lat']
			? (float) $input['default_center_lat']
			: (float) $defaults['default_center_lat'];
		$lng = isset( $input['default_center_lng'] ) && '' !== $input['default_center_lng']
			? (float) $input['default_center_lng']
			: (float) $defaults['default_center_lng'];

		$clean['default_center_lat'] = (string) max( -90, min( 90, $lat ) );
		$clean['default_center_lng'] = (string) max( -180, min( 180, $lng ) );

		$zoom                   = isset( $input['default_zoom'] ) && '' !== $input['default_zoom']
			? (int) $input['default_zoom']
			: (int) $defaults['default_zoom'];
		$clean['default_zoom'] = (string) max( 1, min( 19, $zoom ) );

		$clean['scroll_zoom'] = isset( $input['scroll_zoom'] ) && '1' === (string) $input['scroll_zoom'] ? '1' : '0';

		return $clean;
	}

	/**
	 * Render the settings page shell.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Store Locator Settings', 'aseer-store-locator' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Marker color field.
	 *
	 * @return void
	 */
	public function field_marker_color() {
		$settings = self::get();
		printf(
			'<input type="text" class="asl-color-field" name="%1$s[marker_color]" value="%2$s" data-default-color="%3$s" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $settings['marker_color'] ),
			esc_attr( self::defaults()['marker_color'] )
		);
		echo '<p class="description">' . esc_html__( 'Used for the map pin dot when no custom marker icon URL is set below.', 'aseer-store-locator' ) . '</p>';
	}

	/**
	 * Marker icon URL field.
	 *
	 * @return void
	 */
	public function field_marker_icon_url() {
		$settings = self::get();
		$value    = $settings['marker_icon_url'];
		?>
		<div class="asl-media-field">
			<div class="asl-media-field__preview">
				<?php if ( $value ) : ?>
					<img src="<?php echo esc_url( $value ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<div class="asl-media-field__controls">
				<input
					type="url"
					class="regular-text asl-media-field__input"
					id="asl_marker_icon_url"
					name="<?php echo esc_attr( self::OPTION_KEY ); ?>[marker_icon_url]"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="https://example.com/marker.png"
				/>
				<div class="asl-media-field__buttons">
					<button type="button" class="button asl-media-field__choose"><?php esc_html_e( 'Choose Image', 'aseer-store-locator' ); ?></button>
					<button type="button" class="button button-link-delete asl-media-field__remove"<?php echo $value ? '' : ' style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'aseer-store-locator' ); ?></button>
				</div>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Optional. Pick an image from the Media Library (or paste a URL) — a small PNG/SVG pin, roughly 32×40px, anchored at the bottom center. Leave empty to use a plain colored dot instead.', 'aseer-store-locator' ); ?></p>
		<?php
	}

	/**
	 * Primary color field.
	 *
	 * @return void
	 */
	public function field_primary_color() {
		$settings = self::get();
		printf(
			'<input type="text" class="asl-color-field" name="%1$s[primary_color]" value="%2$s" data-default-color="%3$s" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $settings['primary_color'] ),
			esc_attr( self::defaults()['primary_color'] )
		);
		echo '<p class="description">' . esc_html__( 'Used for buttons, links, and focus outlines in the store locator.', 'aseer-store-locator' ) . '</p>';
	}

	/**
	 * Panel background color field.
	 *
	 * @return void
	 */
	public function field_panel_color() {
		$settings = self::get();
		printf(
			'<input type="text" class="asl-color-field" name="%1$s[panel_color]" value="%2$s" data-default-color="%3$s" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $settings['panel_color'] ),
			esc_attr( self::defaults()['panel_color'] )
		);
		echo '<p class="description">' . esc_html__( 'Background color of the search box and the results-count / filter bar.', 'aseer-store-locator' ) . '</p>';
	}

	/**
	 * Map provider field: Leaflet (default, free, no API key) or Google Maps.
	 *
	 * @return void
	 */
	public function field_map_provider() {
		$settings = self::get();
		$options  = array(
			'leaflet' => __( 'Leaflet (OpenStreetMap / CARTO — free, no API key)', 'aseer-store-locator' ),
			'google'  => __( 'Google Maps (requires an API key below)', 'aseer-store-locator' ),
		);

		echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[map_provider]" id="asl_map_provider">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $settings['map_provider'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Leaflet is the default — free and requires no account. Switch to Google Maps only if you already have (or are willing to set up) a Google Cloud billing account and an API key restricted to the Maps JavaScript API.', 'aseer-store-locator' ) . '</p>';
	}

	/**
	 * Google Maps API key field. Only used when Map Provider is "Google Maps".
	 *
	 * @return void
	 */
	public function field_google_maps_api_key() {
		$settings = self::get();
		printf(
			'<input type="text" class="regular-text" id="asl_google_maps_api_key" name="%1$s[google_maps_api_key]" value="%2$s" placeholder="AIza..." autocomplete="off" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $settings['google_maps_api_key'] )
		);
		echo '<p class="description">' . esc_html__( 'From the Google Cloud Console (APIs & Services → Credentials). Enable the "Maps JavaScript API" for the project and restrict the key to your site\'s domain(s). This key is used client-side to load the map, the same way it would be on any website — restricting it by HTTP referrer in Google Cloud is what keeps it safe to expose in page source.', 'aseer-store-locator' ) . '</p>';
		if ( 'google' === $settings['map_provider'] && '' === $settings['google_maps_api_key'] ) {
			echo '<p class="description" style="color:#b32d2e;">' . esc_html__( 'Google Maps is selected but no API key is set — the frontend will automatically fall back to Leaflet until a key is added.', 'aseer-store-locator' ) . '</p>';
		}
	}

	/**
	 * Map tile style field (Leaflet only).
	 *
	 * @return void
	 */
	public function field_tile_style() {
		$settings = self::get();
		$options  = array(
			'osm'      => __( 'Standard (OpenStreetMap)', 'aseer-store-locator' ),
			'positron' => __( 'Light (Positron)', 'aseer-store-locator' ),
			'dark'     => __( 'Dark (Dark Matter)', 'aseer-store-locator' ),
			'voyager'  => __( 'Voyager', 'aseer-store-locator' ),
		);

		echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[tile_style]">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $settings['tile_style'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Leaflet only. Positron, Dark Matter, and Voyager are free CARTO basemaps. When Map Provider is set to Google Maps, this is ignored — Google Maps uses its own default styling (a simple dark theme is applied automatically when this is set to Dark Matter).', 'aseer-store-locator' ) . '</p>';
	}

	/**
	 * Default map center field (lat, lng).
	 *
	 * @return void
	 */
	public function field_default_center() {
		$settings = self::get();
		printf(
			'<input type="text" class="small-text" name="%1$s[default_center_lat]" value="%2$s" placeholder="lat" /> , ',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $settings['default_center_lat'] )
		);
		printf(
			'<input type="text" class="small-text" name="%1$s[default_center_lng]" value="%2$s" placeholder="lng" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $settings['default_center_lng'] )
		);
		echo '<p class="description">' . esc_html__( 'Where the map centers before any search/filter runs. Once stores load, the map re-fits to their bounds automatically.', 'aseer-store-locator' ) . '</p>';
	}

	/**
	 * Default zoom field.
	 *
	 * @return void
	 */
	public function field_default_zoom() {
		$settings = self::get();
		printf(
			'<input type="number" min="1" max="19" class="small-text" name="%1$s[default_zoom]" value="%2$s" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $settings['default_zoom'] )
		);
		echo '<p class="description">' . esc_html__( 'Used whenever the [store_locator] shortcode does not set its own default_zoom attribute.', 'aseer-store-locator' ) . '</p>';
	}

	/**
	 * Scroll Wheel Zoom setting field.
	 *
	 * @return void
	 */
	public function field_scroll_zoom() {
		$settings = self::get();
		$checked  = isset( $settings['scroll_zoom'] ) && '1' === (string) $settings['scroll_zoom'] ? '1' : '0';
		printf(
			'<input type="checkbox" id="scroll_zoom" name="%1$s[scroll_zoom]" value="1" %2$s />',
			esc_attr( self::OPTION_KEY ),
			checked( '1', $checked, false )
		);
		echo ' <label for="scroll_zoom">' . esc_html__( 'Enable map zoom with mouse scroll wheel', 'aseer-store-locator' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'When disabled (default), scrolling the page over the map won\'t accidentally zoom the map. Recommended for better page-scrolling usability on mobile and desktop.', 'aseer-store-locator' ) . '</p>';
	}
}
