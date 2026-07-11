<?php
/**
 * Template locator/loader helper.
 *
 * Lets a theme (or child theme) override any plugin template by placing a
 * same-named file in `yourtheme/aseer-store-locator/`. This is the same
 * pattern WooCommerce, Easy Digital Downloads, etc. use — the theme's copy
 * always wins, the plugin's `templates/` copy is the fallback.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Templates
 */
class Templates {

	/**
	 * Subdirectory (inside the active theme) that overrides are looked up
	 * in, e.g. yourtheme/aseer-store-locator/single-store.php.
	 */
	const THEME_SUBDIR = 'aseer-store-locator';

	/**
	 * Resolve the highest-priority path for a template file: child theme,
	 * then parent theme, then the plugin's own bundled copy.
	 *
	 * @param string $template_name File name, e.g. 'single-store.php'.
	 * @return string Absolute path, or '' if the template doesn't exist anywhere.
	 */
	public static function locate( $template_name ) {
		/**
		 * Filters the theme subdirectory used to look up template overrides.
		 *
		 * @param string $subdir Default 'aseer-store-locator'.
		 */
		$subdir = apply_filters( 'asl_template_theme_subdir', self::THEME_SUBDIR );

		// locate_template() checks the child theme, then the parent theme,
		// and returns '' if neither has the file.
		$found = locate_template( array( trailingslashit( $subdir ) . $template_name ) );

		if ( ! $found ) {
			$plugin_path = ASL_PLUGIN_DIR . 'templates/' . $template_name;
			if ( file_exists( $plugin_path ) ) {
				$found = $plugin_path;
			}
		}

		/**
		 * Filters the final resolved template path, in case a plugin/theme
		 * needs to inject a template from somewhere other than the standard
		 * theme-override folder.
		 *
		 * @param string $found         Resolved absolute path (or '' if not found).
		 * @param string $template_name Requested template file name.
		 */
		return apply_filters( 'asl_locate_template', $found, $template_name );
	}

	/**
	 * Render a template file, exposing $vars to it as local variables.
	 *
	 * @param string              $template_name File name, e.g. 'locator.php'.
	 * @param array<string,mixed> $vars          Variables to expose to the template.
	 * @return void
	 */
	public static function get_template( $template_name, array $vars = array() ) {
		$path = self::locate( $template_name );

		if ( ! $path ) {
			return;
		}

		if ( $vars ) {
			// Deliberate, documented bridge from an explicit, developer-supplied
			// $vars array to named template variables — nothing here comes
			// from raw user input, so this is not the usual extract() footgun.
			extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		include $path;
	}
}
