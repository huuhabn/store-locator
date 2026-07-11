<?php
/**
 * Brand logo resolution for store cards and filter pills.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BrandLogos
 */
class BrandLogos {

	/**
	 * Known brand slugs mapped to bundled icon SVG assets (59px circle card icons).
	 *
	 * @return array<string,string> slug => relative path under assets/images/brands/
	 */
	public static function icon_catalog() {
		return array(
			'aseer-time'  => 'aseer-time.svg',
			'papa-kanafa' => 'papa-kanafa.svg',
			'farooj'      => 'farooj.svg',
		);
	}

	/**
	 * Known brand slugs mapped to full-logo SVG assets (brand pill logos).
	 *
	 * @return array<string,string> slug => relative path under assets/images/brands/
	 */
	public static function full_catalog() {
		return array(
			'aseer-time'  => 'aseer-time-full.svg',
			'papa-kanafa' => 'papa-kanafa-full.svg',
			'farooj'      => 'farooj-full.svg',
		);
	}

	/**
	 * Alias kept for backward-compat. Returns the icon catalog.
	 *
	 * @return array<string,string>
	 */
	public static function catalog() {
		return self::icon_catalog();
	}

	/**
	 * Match a store brand string to a catalog slug.
	 *
	 * @param string $brand Brand name from store meta.
	 * @return string|null Slug or null when unknown.
	 */
	public static function match_slug( $brand ) {
		$brand = strtolower( trim( (string) $brand ) );
		if ( '' === $brand ) {
			return null;
		}

		if ( false !== strpos( $brand, 'aseer' ) || false !== strpos( $brand, 'juice' ) ) {
			return 'aseer-time';
		}
		if ( false !== strpos( $brand, 'kanafa' ) || false !== strpos( $brand, 'knafa' ) ) {
			return 'papa-kanafa';
		}
		if ( false !== strpos( $brand, 'farooj' ) || false !== strpos( $brand, 'farouj' ) || false !== strpos( $brand, 'abo alabed' ) ) {
			return 'farooj';
		}

		return null;
	}

	/**
	 * Public URL for a brand's icon logo (card icon), or empty string.
	 *
	 * @param string $brand Brand name.
	 * @return string
	 */
	public static function get_url( $brand ) {
		$slug = self::match_slug( $brand );
		if ( ! $slug ) {
			return '';
		}

		$catalog = self::icon_catalog();
		if ( ! isset( $catalog[ $slug ] ) ) {
			return '';
		}

		return ASL_PLUGIN_URL . 'assets/images/brands/' . $catalog[ $slug ];
	}

	/**
	 * Public URL for a brand's full logo (pill logo), or empty string.
	 *
	 * @param string $brand Brand name.
	 * @return string
	 */
	public static function get_full_url( $brand ) {
		$slug = self::match_slug( $brand );
		if ( ! $slug ) {
			return '';
		}

		$catalog = self::full_catalog();
		if ( ! isset( $catalog[ $slug ] ) ) {
			return '';
		}

		return ASL_PLUGIN_URL . 'assets/images/brands/' . $catalog[ $slug ];
	}

	/**
	 * Map of brand display names to icon logo URLs for the frontend JS (card icons).
	 *
	 * @param string[] $brand_names Distinct brand names from the database.
	 * @return array<string,string>
	 */
	public static function map_for_brands( array $brand_names ) {
		$map = array();
		foreach ( $brand_names as $name ) {
			$url = self::get_url( $name );
			if ( $url ) {
				$map[ $name ] = $url;
			}
		}
		return $map;
	}

	/**
	 * Map of brand display names to full logo URLs for the frontend JS (pill logos).
	 *
	 * @param string[] $brand_names Distinct brand names from the database.
	 * @return array<string,string>
	 */
	public static function map_full_for_brands( array $brand_names ) {
		$map = array();
		foreach ( $brand_names as $name ) {
			$url = self::get_full_url( $name );
			if ( $url ) {
				$map[ $name ] = $url;
			}
		}
		return $map;
	}

	/**
	 * Static fallback catalog of all known brand icon URLs — used by Assets.php
	 * to pre-populate ASL_Data.brandLogos before REST /filters loads.
	 *
	 * @return array<string,string> brand display name => icon URL
	 */
	public static function static_icon_map() {
		$slugs = array(
			'Aseer Time'  => 'aseer-time',
			'Papa Kanafa' => 'papa-kanafa',
			'Farooj'      => 'farooj',
		);
		$map   = array();
		$cat   = self::icon_catalog();
		foreach ( $slugs as $name => $slug ) {
			if ( isset( $cat[ $slug ] ) ) {
				$map[ $name ] = ASL_PLUGIN_URL . 'assets/images/brands/' . $cat[ $slug ];
			}
		}
		return $map;
	}

	/**
	 * Static fallback catalog of all known brand full-logo URLs — used by Assets.php
	 * to pre-populate ASL_Data.brandLogosFull before REST /filters loads.
	 *
	 * @return array<string,string> brand display name => full-logo URL
	 */
	public static function static_full_map() {
		$slugs = array(
			'Aseer Time'  => 'aseer-time',
			'Papa Kanafa' => 'papa-kanafa',
			'Farooj'      => 'farooj',
		);
		$map   = array();
		$cat   = self::full_catalog();
		foreach ( $slugs as $name => $slug ) {
			if ( isset( $cat[ $slug ] ) ) {
				$map[ $name ] = ASL_PLUGIN_URL . 'assets/images/brands/' . $cat[ $slug ];
			}
		}
		return $map;
	}
}
