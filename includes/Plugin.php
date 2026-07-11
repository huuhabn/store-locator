<?php
/**
 * Core plugin bootstrapper.
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator;

use Aseer\StoreLocator\PostTypes\StorePostType;
use Aseer\StoreLocator\Admin\MetaBoxes;
use Aseer\StoreLocator\Admin\Import;
use Aseer\StoreLocator\Admin\Settings;
use Aseer\StoreLocator\Elementor\ElementorIntegration;
use Aseer\StoreLocator\Frontend\Shortcode;
use Aseer\StoreLocator\Frontend\Assets;
use Aseer\StoreLocator\Frontend\TemplateLoader;
use Aseer\StoreLocator\Rest\StoreController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Singleton responsible for wiring up all plugin components.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get (and lazily create) the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Wire up all hooks / components.
	 *
	 * @return void
	 */
	private function init() {
		load_plugin_textdomain( 'aseer-store-locator', false, dirname( ASL_PLUGIN_BASENAME ) . '/languages' );

		// Custom post type + meta fields.
		( new StorePostType() )->register();

		// Admin UI (meta boxes, CSV importer, list table columns).
		if ( is_admin() ) {
			( new MetaBoxes() )->register();
			( new Import() )->register();
			( new Settings() )->register();
		}

		// Frontend shortcode + assets + single-store "listing detail" template.
		( new Shortcode() )->register();
		( new Assets() )->register();
		( new TemplateLoader() )->register();

		// REST API endpoints.
		( new StoreController() )->register();

		// Elementor widget (no-op when Elementor is inactive).
		( new ElementorIntegration() )->register();
	}

	/**
	 * Plugin activation callback.
	 *
	 * Registers the CPT so rewrite rules can be flushed, then flushes them.
	 *
	 * @return void
	 */
	public static function activate() {
		( new StorePostType() )->register();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
