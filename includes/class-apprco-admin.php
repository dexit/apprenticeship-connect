<?php
/**
 * Admin Manager
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Admin {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu_pages(): void {
		add_menu_page(
			__( 'Apprenticeship Connect', 'apprenticeship-connect' ),
			__( 'Appr Connect', 'apprenticeship-connect' ),
			'manage_options',
			'apprco-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-welcome-learn-more'
		);

		add_submenu_page(
			'apprco-dashboard',
			__( 'Settings', 'apprenticeship-connect' ),
			__( 'Settings', 'apprenticeship-connect' ),
			'manage_options',
			'apprco-settings',
			array( $this, 'render_settings' )
		);
	}

	public function render_dashboard(): void {
		echo '<div id="apprco-dashboard-root"></div>';
	}

	public function render_settings(): void {
		echo '<div id="apprco-settings-root"></div>';
	}

	public function enqueue_assets( $hook ): void {
		if ( strpos( $hook, 'apprco' ) === false && strpos( $hook, 'apprco_vacancy' ) === false ) {
			return;
		}

		$asset_file = APPRCO_PLUGIN_DIR . 'assets/build/admin.asset.php';
		$deps       = array( 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-components' );
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			$deps  = $asset['dependencies'];
		}

		wp_enqueue_script( 'apprco-admin', APPRCO_PLUGIN_URL . 'assets/build/admin.js', $deps, APPRCO_VERSION, true );
		wp_enqueue_style( 'apprco-admin-style', APPRCO_PLUGIN_URL . 'assets/build/style-admin-style.css', array( 'wp-components' ), APPRCO_VERSION );
	}
}
