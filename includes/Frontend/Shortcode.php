<?php
/**
 * [store_locator] shortcode handler.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcode
 */
class Shortcode {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'store_locator', array( $this, 'render' ) );
	}

	/**
	 * Render the [store_locator] shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'height'         => '689px',
				// Left empty on purpose: an empty value tells the frontend JS to
				// fall back to the site-wide defaults set on the Settings page,
				// rather than silently overriding them with a hardcoded '4'.
				'default_zoom'   => '',
				'default_center' => '',
				'default_brand'  => '',
				'show_hero'      => '1',
				'hero_title'     => '',
				'hero_subtitle'  => '',
				'instance_id'    => 'asl-locator',
			),
			$atts,
			'store_locator'
		);

		// Let Assets know the shortcode is present so it can conditionally enqueue.
		Assets::$is_active = true;

		ob_start();
		Templates::get_template( 'locator.php', array( 'atts' => $atts ) );
		return ob_get_clean();
	}
}
