<?php
/**
 * Registers the Store Locator Elementor widget.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator\Elementor;

use Aseer\StoreLocator\Elementor\Widgets\StoreLocatorWidget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ElementorIntegration
 */
class ElementorIntegration {

	/**
	 * Hook into WordPress once Elementor is available.
	 *
	 * @return void
	 */
	public function register() {
		// Primary hook: fires when Elementor itself is ready.
		// This covers the normal load order (Elementor loads before our plugin
		// does, or both load in the same request).
		add_action( 'elementor/loaded', array( $this, 'boot' ) );

		// Fallback for edge cases where our plugin loaded before Elementor
		// and 'elementor/loaded' has already fired by the time we call
		// add_action() above. In that scenario did_action() will return > 0
		// and we call boot() directly.
		add_action( 'plugins_loaded', array( $this, 'boot_if_elementor_ready' ), 20 );
	}

	/**
	 * Called on 'plugins_loaded' (priority 20) as a fallback: only boots if
	 * Elementor is already loaded and boot() hasn't run yet.
	 *
	 * @return void
	 */
	public function boot_if_elementor_ready() {
		if ( did_action( 'elementor/loaded' ) ) {
			$this->boot();
		}
	}

	/**
	 * Register widgets and category when Elementor has loaded.
	 * Guarded by a flag so it never runs more than once.
	 *
	 * @return void
	 */
	public function boot() {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Add an "Aseer Store Locator" panel in the Elementor widget library.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 * @return void
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'aseer-store-locator',
			array(
				'title' => esc_html__( 'Aseer Store Locator', 'aseer-store-locator' ),
				'icon'  => 'fa fa-map-marker',
			)
		);
	}

	/**
	 * Register plugin widgets with Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		require_once ASL_PLUGIN_DIR . 'includes/Elementor/Widgets/StoreLocatorWidget.php';
		$widgets_manager->register( new StoreLocatorWidget() );
	}
}
