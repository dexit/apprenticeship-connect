<?php
/**
 * Admin Manager Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Admin
 *
 * Handles the admin menu and asset enqueuing for the plugin.
 */
class Apprco_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the admin menu and submenu pages.
	 *
	 * @return void
	 */
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

	/**
	 * Renders the dashboard React mount point.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		echo '<div id="apprco-dashboard-root"></div>';
	}

	/**
	 * Renders the settings React mount point.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		echo '<div id="apprco-settings-root"></div>';
	}

	/**
	 * Enqueues admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ): void {
		if ( strpos( $hook, 'apprco' ) === false && strpos( $hook, 'apprco_vacancy' ) === false ) {
			return;
		}

		$asset_file = APPRCO_PLUGIN_DIR . 'build/admin/index.asset.php';
		$deps       = array( 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-components' );
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			$deps  = $asset['dependencies'];
		}

		wp_enqueue_script( 'apprco-admin', APPRCO_PLUGIN_URL . 'build/admin/index.js', $deps, APPRCO_VERSION, true );
		wp_enqueue_style( 'apprco-admin-style', APPRCO_PLUGIN_URL . 'build/admin/index.css', array( 'wp-components' ), APPRCO_VERSION );
	}
}
