<?php
/**
 * Swaps in the "listing detail" template (theme override if present, else
 * the plugin's bundled copy) whenever a single `store` post is viewed.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Frontend;

use Aseer\StoreLocator\PostTypes\StorePostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TemplateLoader
 */
class TemplateLoader {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'template_include', array( $this, 'maybe_load_single_store_template' ) );
	}

	/**
	 * Replace WordPress's normal template choice with single-store.php when
	 * viewing a single store, so the store's full "listing detail" page
	 * renders instead of a bare title (the `store` CPT only supports
	 * title/thumbnail, so a theme's generic single.php would show little
	 * else).
	 *
	 * @param string $template Template path WordPress resolved by default.
	 * @return string
	 */
	public function maybe_load_single_store_template( $template ) {
		if ( ! is_singular( StorePostType::POST_TYPE ) ) {
			return $template;
		}

		$found = Templates::locate( 'single-store.php' );

		return $found ? $found : $template;
	}
}
