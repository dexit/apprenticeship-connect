<?php
/**
 * Fires on plugin activation.
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

class Activator {

	public static function activate(): void {
		// Require minimum PHP version.
		if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
			deactivate_plugins( APPCON_BASENAME );
			wp_die(
				esc_html__( 'Apprenticeship Connector requires PHP 8.2 or higher.', 'apprenticeship-connector' ),
				esc_html__( 'Plugin Activation Error', 'apprenticeship-connector' ),
				[ 'back_link' => true ]
			);
		}

		// Create/update DB tables.
		require_once APPCON_DIR . 'includes/Core/Database.php';
		Database::install();

		// Flush rewrite rules after CPT registration.
		flush_rewrite_rules();

		// Set default options.
		if ( ! get_option( 'appcon_settings' ) ) {
			update_option( 'appcon_settings', [
				'api_base_url'      => 'https://api.apprenticeships.education.gov.uk/vacancies',
				'api_key'           => '',
				'rate_limit_ms'     => 250,
				'stage1_page_size'  => 100,
				'stage1_max_pages'  => 100,
			] );
		}

		update_option( 'appcon_db_version', APPCON_DB_VERSION );
	}
}
