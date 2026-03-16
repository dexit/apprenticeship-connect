<?php
/**
 * Admin menu + asset registration.
 *
 * @package ApprenticeshipConnector\Admin
 */

namespace ApprenticeshipConnector\Admin;

class AdminLoader {

	public function register_menus(): void {
		add_menu_page(
			__( 'Apprenticeship Connector', 'apprenticeship-connector' ),
			__( 'Apprenticeships',           'apprenticeship-connector' ),
			'manage_options',
			'apprenticeship-connector',
			[ Dashboard::class, 'render' ],
			'dashicons-welcome-learn-more',
			30
		);

		add_submenu_page(
			'apprenticeship-connector',
			__( 'Dashboard',    'apprenticeship-connector' ),
			__( 'Dashboard',    'apprenticeship-connector' ),
			'manage_options',
			'apprenticeship-connector',
			[ Dashboard::class, 'render' ]
		);

		add_submenu_page(
			'apprenticeship-connector',
			__( 'Import Jobs',  'apprenticeship-connector' ),
			__( 'Import Jobs',  'apprenticeship-connector' ),
			'manage_options',
			'appcon-import-jobs',
			[ ImportJobsPage::class, 'render' ]
		);

		add_submenu_page(
			'apprenticeship-connector',
			__( 'Settings',     'apprenticeship-connector' ),
			__( 'Settings',     'apprenticeship-connector' ),
			'manage_options',
			'appcon-settings',
			[ SettingsPage::class, 'render' ]
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		$pages = [
			'toplevel_page_apprenticeship-connector',
			'apprenticeships_page_appcon-import-jobs',
			'apprenticeships_page_appcon-settings',
		];

		if ( ! in_array( $hook_suffix, $pages, true ) ) {
			return;
		}

		$asset_file = APPCON_DIR . 'build/admin/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'appcon-admin',
			APPCON_URL . 'build/admin/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'appcon-admin',
			APPCON_URL . 'build/admin/index.css',
			[],
			$asset['version']
		);

		// Pass data to JS.
		wp_localize_script( 'appcon-admin', 'appconData', [
			'apiUrl'   => esc_url_raw( rest_url( 'appcon/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'version'  => APPCON_VERSION,
		] );
	}
}
