<?php
/**
 * Plugin Name:       Aseer Store Locator
 * Plugin URI:         https://aseertime.com
 * Description:        A store locator plugin built with Leaflet.js (Google Map API Support), a REST API backend, marker clustering, live filtering, and CSV bulk import.
 * Version:            1.5.12
 * Requires at least:  5.8
 * Requires PHP:       7.4
 * Author:             Aseer Time Group
 * Author URI:         https://aseertime.com
 * License:             GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         aseer-store-locator
 * Domain Path:         /languages
 *
 * @package AseerStoreLocator
 */

namespace Aseer\StoreLocator;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -----------------------------------------------------------------------
// Constants.
// -----------------------------------------------------------------------
define( 'ASL_VERSION', '1.5.12' );
define( 'ASL_PLUGIN_FILE', __FILE__ );
define( 'ASL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ASL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// -----------------------------------------------------------------------
// Simple PSR-4-ish autoloader for the Aseer\StoreLocator namespace.
// -----------------------------------------------------------------------
spl_autoload_register(
	function ( $class ) {
		$prefix = 'Aseer\\StoreLocator\\';

		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class );
		$file           = ASL_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative_path . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// -----------------------------------------------------------------------
// Activation / Deactivation hooks.
// -----------------------------------------------------------------------
register_activation_hook( __FILE__, array( '\\Aseer\\StoreLocator\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\Aseer\\StoreLocator\\Plugin', 'deactivate' ) );

// -----------------------------------------------------------------------
// Boot the plugin.
// -----------------------------------------------------------------------
add_action( 'plugins_loaded', array( '\\Aseer\\StoreLocator\\Plugin', 'instance' ) );
