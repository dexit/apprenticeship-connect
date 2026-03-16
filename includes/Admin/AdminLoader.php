<?php
/**
 * Admin menu + asset registration.
 *
 * React app is loaded when the compiled build exists.
 * A lightweight PHP fallback is used otherwise (e.g. during development before
 * the first `npm run build`, or if the build step was skipped).
 *
 * @package ApprenticeshipConnector\Admin
 */

namespace ApprenticeshipConnector\Admin;

class AdminLoader {

	/** True when the React build artefacts are present. */
	public static function react_build_available(): bool {
		return file_exists( APPCON_DIR . 'build/admin/index.asset.php' );
	}

	// ── Menus ─────────────────────────────────────────────────────────────

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
			__( 'Dashboard',   'apprenticeship-connector' ),
			__( 'Dashboard',   'apprenticeship-connector' ),
			'manage_options',
			'apprenticeship-connector',
			[ Dashboard::class, 'render' ]
		);

		add_submenu_page(
			'apprenticeship-connector',
			__( 'Import Jobs', 'apprenticeship-connector' ),
			__( 'Import Jobs', 'apprenticeship-connector' ),
			'manage_options',
			'appcon-import-jobs',
			[ ImportJobsPage::class, 'render' ]
		);

		add_submenu_page(
			'apprenticeship-connector',
			__( 'Settings',    'apprenticeship-connector' ),
			__( 'Settings',    'apprenticeship-connector' ),
			'manage_options',
			'appcon-settings',
			[ SettingsPage::class, 'render' ]
		);
	}

	// ── Assets ────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook_suffix ): void {
		$pages = [
			'toplevel_page_apprenticeship-connector',
			'apprenticeships_page_appcon-import-jobs',
			'apprenticeships_page_appcon-settings',
		];

		if ( ! in_array( $hook_suffix, $pages, true ) ) {
			return;
		}

		if ( ! self::react_build_available() ) {
			// No React build: enqueue only core WP admin styles so the PHP fallback looks decent.
			wp_enqueue_style( 'wp-components' );
			return;
		}

		$asset = require APPCON_DIR . 'build/admin/index.asset.php';

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
			[ 'wp-components' ],
			$asset['version']
		);

		// Pass runtime data to JS.
		wp_localize_script( 'appcon-admin', 'appconData', [
			'apiUrl'         => esc_url_raw( rest_url( 'appcon/v1' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'version'        => APPCON_VERSION,
			'reactAvailable' => true,
			'dismissNonce'   => wp_create_nonce( 'appcon_dismiss_notice' ),
		] );
	}
}
