<?php
/**
 * Plugin Name: Apprenticeship Connect
 * Description: Robust integration with UK Government Apprenticeships API v2. Features deep-fetching, rate-limit resilience, and modern dashboard.
 * Version: 3.1.0
 * Author: Jules
 * Text Domain: apprenticeship-connect
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) die;

define( 'APPRCO_VERSION', '3.1.0' );
define( 'APPRCO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APPRCO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload classes from includes/
 */
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'Apprco_' ) !== 0 ) return;

    $file = APPRCO_PLUGIN_DIR . 'includes/class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
        return;
    }

    // Check providers subfolder
    $file = APPRCO_PLUGIN_DIR . 'includes/providers/class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
        return;
    }

    // Abstract classes/interfaces
    $file = APPRCO_PLUGIN_DIR . 'includes/providers/abstract-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
        return;
    }
} );

/**
 * Main Plugin Class
 */
class Apprco_Connector {
    private static $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
    }

    public function init(): void {
        // Composer Autoload for dependencies (Action Scheduler)
        if ( file_exists( APPRCO_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
            require_once APPRCO_PLUGIN_DIR . 'vendor/autoload.php';
        }

        // Initialize Core Components
        Apprco_Database::get_instance()->init();
        Apprco_Post_Types::get_instance();
        Apprco_Task_Scheduler::get_instance()->init();

        // Initialize Integrations
        Apprco_Blocks::get_instance();
        Apprco_Elementor::get_instance();
        Apprco_Shortcodes::get_instance();

        // Initialize API & Routing
        Apprco_REST_Controller::get_instance()->register_routes();
        Apprco_REST_Proxy::get_instance();
        Apprco_Settings_Manager::get_instance()->register_rest_routes();

        if ( is_admin() ) {
            Apprco_Admin::get_instance();
            Apprco_Meta_Box::get_instance();
        }
    }

    public function activate(): void {
        Apprco_Database::get_instance()->upgrade();
        flush_rewrite_rules();
    }
}

// Kickoff
Apprco_Connector::get_instance();

/**
 * Frontend Assets
 */
add_action( 'wp_enqueue_scripts', function() {
    $asset_file = APPRCO_PLUGIN_DIR . 'assets/build/frontend.asset.php';
    if ( file_exists( $asset_file ) ) {
        $asset = require $asset_file;
        wp_enqueue_script( 'apprco-frontend', APPRCO_PLUGIN_URL . 'assets/build/frontend.js', $asset['dependencies'], APPRCO_VERSION, true );
        wp_enqueue_style( 'apprco-frontend-style', APPRCO_PLUGIN_URL . 'assets/build/style-frontend-style.css', array(), APPRCO_VERSION );
    }
} );

/**
 * Vacancy Search Shortcode
 */
add_shortcode( 'apprco_search', function() {
    return '<div id="apprco-search-root"></div>';
} );
