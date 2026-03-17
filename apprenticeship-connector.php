<?php
/**
 * Plugin Name: Apprenticeship Connector
 * Plugin URI:  https://github.com/epark-uk/apprenticeship-connector
 * Description: Connects WordPress to the UK Government Display Advert API v2 to import apprenticeship vacancies via a two-stage import system. Built on WordPress Plugin Boilerplate with Secure Custom Fields.
 * Version:     1.0.0
 * Author:      ePark Team
 * Author URI:  https://e-park.uk
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: apprenticeship-connector
 * Domain Path: /languages
 * Requires at least: 6.4
 * Tested up to:      6.7
 * Requires PHP:      8.2
 *
 * @package ApprenticeshipConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ──────────────────────────────────────────────────────────────
define( 'APPCON_VERSION',   '1.0.0' );
define( 'APPCON_FILE',      __FILE__ );
define( 'APPCON_DIR',       plugin_dir_path( __FILE__ ) );
define( 'APPCON_URL',       plugin_dir_url( __FILE__ ) );
define( 'APPCON_BASENAME',  plugin_basename( __FILE__ ) );
define( 'APPCON_DB_VERSION', '1.0.0' );

// ── Composer autoloader ────────────────────────────────────────────────────
if ( file_exists( APPCON_DIR . 'vendor/autoload.php' ) ) {
	require_once APPCON_DIR . 'vendor/autoload.php';
}

// ── Activation / Deactivation hooks ───────────────────────────────────────
require_once APPCON_DIR . 'includes/Core/Activator.php';
require_once APPCON_DIR . 'includes/Core/Deactivator.php';

register_activation_hook(   __FILE__, [ 'ApprenticeshipConnector\\Core\\Activator',   'activate'   ] );
register_deactivation_hook( __FILE__, [ 'ApprenticeshipConnector\\Core\\Deactivator', 'deactivate' ] );

// ── Bootstrap ──────────────────────────────────────────────────────────────
require_once APPCON_DIR . 'includes/Core/Plugin.php';

/**
 * Returns the singleton plugin instance.
 */
function appcon(): \ApprenticeshipConnector\Core\Plugin {
	return \ApprenticeshipConnector\Core\Plugin::get_instance();
}

appcon()->run();
