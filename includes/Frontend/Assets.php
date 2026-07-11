<?php
/**
 * Registers and conditionally enqueues frontend assets.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Frontend;

use Aseer\StoreLocator\Admin\Settings;
use Aseer\StoreLocator\Frontend\BrandLogos;
use Aseer\StoreLocator\PostTypes\StorePostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 */
class Assets {

	const LEAFLET_VERSION         = '1.9.4';
	const CLUSTER_VERSION         = '1.5.3';
	const GOOGLE_CLUSTERER_VERSION = '2.5.3';

	/**
	 * Flag flipped to true when the shortcode has actually rendered.
	 * Used as a fallback for themes/builders that don't run has_shortcode()
	 * against the queried post content (e.g. shortcode inside a widget or
	 * page builder module).
	 *
	 * @var bool
	 */
	public static $is_active = false;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		// Frontend: register + conditionally enqueue on public pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_late' ), 1 );

		// Elementor editor context: the editor admin page and its preview
		// iframe both need the handles registered so that the widget's
		// get_script_depends() / get_style_depends() declarations resolve
		// correctly. Without this, Elementor silently skips unrecognised
		// handles and the widget loads unstyled / without JS.
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (and conditionally enqueue) all plugin assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		// Guard: wp_register_* calls are idempotent in WP core, but we also
		// call wp_localize_script here which appends data on each call. The
		// static flag prevents double-localisation when this method is invoked
		// from both wp_enqueue_scripts and the Elementor editor hooks.
		static $registered = false;
		if ( $registered ) {
			// If already registered, still allow conditional enqueue checks.
			if ( $this->should_enqueue() ) {
				$this->enqueue( Settings::get() );
			}
			return;
		}
		$registered = true;

		$settings = Settings::get();

		// Google Maps requires an API key to actually load; if it's selected
		// but not configured, silently fall back to Leaflet on the frontend
		// rather than rendering a broken map. Settings::render_page() /
		// field_google_maps_api_key() already surfaces this to the admin.
		$provider = $settings['map_provider'];
		if ( 'google' === $provider && empty( $settings['google_maps_api_key'] ) ) {
			$provider = 'leaflet';
		}

		// ---- Leaflet core (default provider) ----
		wp_register_style(
			'leaflet',
			'https://unpkg.com/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.css',
			array(),
			self::LEAFLET_VERSION
		);
		wp_register_script(
			'leaflet',
			'https://unpkg.com/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.js',
			array(),
			self::LEAFLET_VERSION,
			true
		);

		// Leaflet marker clustering.
		wp_register_style(
			'leaflet-markercluster',
			'https://unpkg.com/leaflet.markercluster@' . self::CLUSTER_VERSION . '/dist/MarkerCluster.css',
			array( 'leaflet' ),
			self::CLUSTER_VERSION
		);
		wp_register_style(
			'leaflet-markercluster-default',
			'https://unpkg.com/leaflet.markercluster@' . self::CLUSTER_VERSION . '/dist/MarkerCluster.Default.css',
			array( 'leaflet' ),
			self::CLUSTER_VERSION
		);
		wp_register_script(
			'leaflet-markercluster',
			'https://unpkg.com/leaflet.markercluster@' . self::CLUSTER_VERSION . '/dist/leaflet.markercluster.js',
			array( 'leaflet' ),
			self::CLUSTER_VERSION,
			true
		);

		// ---- Google Maps (opt-in alternative provider) ----
		// Loaded as a plain (non-async) script, so WordPress's normal
		// dependency ordering is enough to guarantee `window.google.maps` is
		// ready by the time our own script (which depends on this handle)
		// runs — no extra callback bootstrapping needed.
		wp_register_script(
			'google-maps-api',
			'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $settings['google_maps_api_key'] ) . '&libraries=marker&v=weekly',
			array(),
			null,
			true
		);
		// Optional clustering for Google Maps. Loaded defensively — if it
		// fails to load for any reason, single-store.js/store-locator.js
		// both check `typeof markerClusterer` before using it and simply
		// render individual markers instead.
		wp_register_script(
			'google-marker-clusterer',
			'https://unpkg.com/@googlemaps/markerclusterer@' . self::GOOGLE_CLUSTERER_VERSION . '/dist/index.min.js',
			array( 'google-maps-api' ),
			self::GOOGLE_CLUSTERER_VERSION,
			true
		);

		$map_deps       = ( 'google' === $provider )
			? array( 'google-maps-api', 'google-marker-clusterer' )
			: array( 'leaflet', 'leaflet-markercluster' );
		$map_style_deps = ( 'google' === $provider )
			? array()
			: array( 'leaflet', 'leaflet-markercluster', 'leaflet-markercluster-default' );

		// ---- Typography (Figma) ----
		// Always enqueue Barlow Condensed (EN/ES). When the page is
		// Arabic (RTL) also load Tajawal and apply the font override.
		$font_families = array( 'Barlow+Condensed:wght@400;500;700' );
		if ( is_rtl() ) {
			$font_families[] = 'Tajawal:wght@400;500;700';
		}
		$font_url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $font_families ) . '&display=swap';
		wp_register_style(
			'asl-google-fonts',
			$font_url,
			array(),
			null
		);

		// Plugin assets.
		wp_register_style(
			'aseer-store-locator',
			ASL_PLUGIN_URL . 'assets/css/store-locator.css',
			$map_style_deps,
			ASL_VERSION
		);
		wp_register_script(
			'aseer-store-locator',
			ASL_PLUGIN_URL . 'assets/js/store-locator.js',
			$map_deps,
			ASL_VERSION,
			true
		);

		// Single store "listing detail" page assets (only enqueued on that page).
		wp_register_style(
			'aseer-store-locator-single',
			ASL_PLUGIN_URL . 'assets/css/single-store.css',
			array( 'aseer-store-locator' ),
			ASL_VERSION
		);
		wp_register_script(
			'aseer-store-locator-single',
			ASL_PLUGIN_URL . 'assets/js/single-store.js',
			$map_deps,
			ASL_VERSION,
			true
		);

		$tile = $this->tile_providers();
		$tile = isset( $tile[ $settings['tile_style'] ] ) ? $tile[ $settings['tile_style'] ] : $tile['osm'];

		$localized_data = array(
			'restUrl'    => esc_url_raw( rest_url( 'aseer-store-locator/v1' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'i18n'       => array(
				'noResults'      => __( 'No stores found matching your criteria.', 'aseer-store-locator' ),
				'open'           => __( 'Open', 'aseer-store-locator' ),
				'closed'         => __( 'Closed', 'aseer-store-locator' ),
				'directions'     => __( 'Directions', 'aseer-store-locator' ),
				'details'        => __( 'Store Details', 'aseer-store-locator' ),
				'useMyLocation'  => __( 'Use My Location', 'aseer-store-locator' ),
				'locating'       => __( 'Locating…', 'aseer-store-locator' ),
				'locationDenied' => __( 'Could not access your location.', 'aseer-store-locator' ),
				'storesFound'    => __( 'stores found', 'aseer-store-locator' ),
				'noneFound'      => __( 'No stores found.', 'aseer-store-locator' ),
				'mapView'        => __( 'Map', 'aseer-store-locator' ),
				'listView'       => __( 'List', 'aseer-store-locator' ),
				'kmAway'         => __( 'km away', 'aseer-store-locator' ),
				'filterBy'       => __( 'Filter by', 'aseer-store-locator' ),
				'clearFilters'   => __( 'Clear Filters', 'aseer-store-locator' ),
				'searching'      => __( 'Searching…', 'aseer-store-locator' ),
				'mapLoadError'   => __( 'Map failed to load.', 'aseer-store-locator' ),
				// Figma UI strings.
				'findStore'      => __( 'Find Store', 'aseer-store-locator' ),
				'openMap'        => __( 'Open Map', 'aseer-store-locator' ),
				'viewMap'        => __( 'View Map', 'aseer-store-locator' ),
				'allBrands'      => __( 'All Brands', 'aseer-store-locator' ),
				'allCountries'   => __( 'All Countries', 'aseer-store-locator' ),
				// translators: %1$s = count, %2$s = brand name, %3$s = country name.
				'resultsSummary' => __( '%1$s Store Found for %2$s in %3$s', 'aseer-store-locator' ),
			),
			'settings'      => array(
				'mapProvider'     => $provider,
				'markerColor'     => $settings['marker_color'],
				'markerIconUrl'   => $settings['marker_icon_url'],
				'tileUrl'         => $tile['url'],
				'tileAttribution' => $tile['attribution'],
				'tileStyle'       => $settings['tile_style'],
				'defaultCenter'   => array(
					'lat' => (float) $settings['default_center_lat'],
					'lng' => (float) $settings['default_center_lng'],
				),
				'defaultZoom'     => (int) $settings['default_zoom'],
			),
			// Static fallback brand logo maps populated before REST /filters loads.
			'brandLogos'     => BrandLogos::static_icon_map(),
			'brandLogosFull' => BrandLogos::static_full_map(),
		);

		// Localized onto BOTH script handles (not just the widget's), since
		// 'aseer-store-locator-single' (the single-store detail page's mini
		// map) doesn't depend on the full widget script and would otherwise
		// never see ASL_Data — leaving single-store.js unable to tell which
		// map provider is configured.
		wp_localize_script( 'aseer-store-locator', 'ASL_Data', $localized_data );
		wp_localize_script( 'aseer-store-locator-single', 'ASL_Data', $localized_data );

		if ( $this->should_enqueue() ) {
			$this->enqueue( $settings );
		}

		if ( is_singular( StorePostType::POST_TYPE ) ) {
			// 'aseer-store-locator-single' already depends on the correct
			// map-provider handles ($map_deps set above), so enqueuing it
			// alone pulls in the right chain (Leaflet+cluster, or Google
			// Maps+clusterer) automatically.
			wp_enqueue_style( 'aseer-store-locator-single' );
			wp_enqueue_script( 'aseer-store-locator-single' );
			$this->apply_color_overrides( $settings );
		}
	}

	/**
	 * Map of tile style keys to their Leaflet tile URL template + attribution.
	 * Positron / Dark Matter / Voyager are free CARTO basemaps that pair with
	 * Leaflet the same way the default OpenStreetMap tiles do.
	 *
	 * @return array<string,array{url:string,attribution:string}>
	 */
	private function tile_providers() {
		return array(
			'osm'      => array(
				'url'         => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
				'attribution' => '&copy; OpenStreetMap contributors',
			),
			'positron' => array(
				'url'         => 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
				'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
			),
			'dark'     => array(
				'url'         => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
				'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
			),
			'voyager'  => array(
				'url'         => 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
				'attribution' => '&copy; OpenStreetMap contributors &copy; CARTO',
			),
		);
	}

	/**
	 * Late safety-net enqueue for shortcodes rendered outside the normal
	 * content flow that should_enqueue() can detect ahead of time — most
	 * notably Elementor's "Shortcode" widget, which stores the shortcode
	 * text inside `_elementor_data` postmeta (JSON) rather than
	 * `$post->post_content`, so `has_shortcode( $post->post_content, ... )`
	 * never finds it. The same applies to ACF fields, widget areas, etc.
	 *
	 * Assets::$is_active is set the moment Shortcode::render() actually
	 * runs (regardless of *where* it was called from), which happens during
	 * the normal page render — after wp_enqueue_scripts (too early to know
	 * yet) but before wp_footer, so checking it here reliably catches every
	 * case should_enqueue() missed.
	 *
	 * @return void
	 */
	public function maybe_enqueue_late() {
		if ( ! self::$is_active || wp_script_is( 'aseer-store-locator', 'enqueued' ) ) {
			return; // Nothing rendered the shortcode, or it was already enqueued normally.
		}

		$this->enqueue( Settings::get() );

		// WordPress automatically prints any script enqueued by this point
		// via its own wp_print_footer_scripts callback (hooked to wp_footer
		// at priority 20, after this runs at priority 1) — but there's no
		// equivalent "print footer styles" pass in core. A style enqueued
		// this late would otherwise silently never reach the page, leaving
		// the widget rendered but completely unstyled. Force it out here.
		wp_print_styles( array( 'asl-google-fonts', 'aseer-store-locator' ) );
	}

	/**
	 * Guards against attaching the same inline color-override CSS twice in
	 * one request (e.g. both the locator widget and the single-store page
	 * assets loading on the same request via maybe_enqueue_late()).
	 *
	 * @var bool
	 */
	private static $colors_applied = false;

	/**
	 * Enqueue all registered assets for the [store_locator] widget. The
	 * correct map-provider scripts/styles (Leaflet+cluster, or Google Maps
	 * +clusterer) are already wired in as dependencies of 'aseer-store-locator'
	 * back in register_assets(), so enqueuing just that handle is enough —
	 * WordPress resolves and loads the rest of the dependency chain itself.
	 *
	 * @param array<string,string> $settings Plugin settings (see Settings::get()).
	 * @return void
	 */
	private function enqueue( $settings ) {
		wp_enqueue_style( 'asl-google-fonts' );
		wp_enqueue_style( 'aseer-store-locator' );
		wp_enqueue_script( 'aseer-store-locator' );
		$this->apply_color_overrides( $settings );
		$this->apply_font_overrides();
	}

	/**
	 * Feed the admin-configured colors in as CSS custom property overrides
	 * so the stylesheet never has to be hand-edited per install. Scoped to
	 * `.asl-locator`, the shared root class used by both the locator widget
	 * and the single-store "listing detail" page.
	 *
	 * @param array<string,string> $settings Plugin settings (see Settings::get()).
	 * @return void
	 */
	private function apply_color_overrides( $settings ) {
		if ( self::$colors_applied ) {
			return;
		}
		self::$colors_applied = true;

		wp_add_inline_style(
			'aseer-store-locator',
			sprintf(
				'.asl-locator{--asl-color-primary:%1$s;--asl-color-panel:%2$s;}',
				esc_attr( $settings['primary_color'] ),
				esc_attr( $settings['panel_color'] )
			)
		);
	}

	/**
	 * Apply Tajawal font override when the page locale is RTL.
	 *
	 * @return void
	 */
	private function apply_font_overrides() {
		if ( is_rtl() ) {
			wp_add_inline_style(
				'aseer-store-locator',
				'[dir="rtl"] .asl-locator { --asl-font: "Tajawal", sans-serif; }'
			);
		}
	}

	/**
	 * Determine whether the current request likely contains the shortcode.
	 *
	 * @return bool
	 */
	private function should_enqueue() {
		// Elementor's "Shortcode" widget can't live-preview client-side the
		// way its native widgets do — whenever its content changes,
		// Elementor re-renders it server-side via an AJAX request straight
		// to admin-ajax.php and injects the returned HTML into the
		// already-loaded iframe with JavaScript. That AJAX request never
		// fires wp_enqueue_scripts at all (there's no page/footer for it to
		// print into), so there's no reliable hook to enqueue anything
		// specifically for that piecemeal re-render.
		//
		// The fix: whenever the iframe's *own* initial page load happens —
		// which is a normal request and DOES fire wp_enqueue_scripts — load
		// our assets unconditionally, regardless of whether the shortcode
		// happens to be present in the content yet. That way they're
		// already sitting in the iframe's <head> by the time the user adds
		// the widget and Elementor does its AJAX re-render.
		if ( $this->is_elementor_editor_preview() ) {
			return true;
		}

		if ( ! is_singular() ) {
			return false;
		}

		global $post;
		if ( ! ( $post instanceof \WP_Post ) ) {
			return false;
		}

		if ( has_shortcode( $post->post_content, 'store_locator' ) ) {
			return true;
		}

		// Elementor stores widget content — including its "Shortcode" widget's
		// raw shortcode text — inside `_elementor_data` postmeta as JSON, not
		// in $post->post_content, so has_shortcode() above never finds it
		// there. A cheap substring check on the raw meta (no need to fully
		// decode the JSON) catches that case on normal frontend views of an
		// Elementor page (as opposed to the editor preview, handled above).
		// Also detect the native Elementor widget slug ('asl-store-locator')
		// alongside the shortcode text, to handle pages where the widget
		// was placed directly rather than via a Shortcode widget.
		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
		if ( is_string( $elementor_data ) && (
			false !== strpos( $elementor_data, 'store_locator' ) ||
			false !== strpos( $elementor_data, 'asl-store-locator' )
		) ) {
			return true;
		}

		return false;
	}

	/**
	 * True while viewing Elementor's editor preview iframe.
	 *
	 * Elementor has used the `elementor-preview` query var for that iframe's
	 * URL across every recent version — checking it directly is simpler and
	 * more version-resilient than instantiating Elementor's own classes to
	 * ask, and doesn't require Elementor to even be active to check safely.
	 *
	 * @return bool
	 */
	private function is_elementor_editor_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context check, not processing input.
		return isset( $_GET['elementor-preview'] );
	}
}
