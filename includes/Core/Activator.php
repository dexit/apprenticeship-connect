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
				// API
				'api_base_url'        => 'https://api.apprenticeships.education.gov.uk/vacancies',
				'api_key'             => '',
				'rate_limit_ms'       => 2000,
				// Import defaults
				'stage1_page_size'    => 100,
				'stage1_max_pages'    => 100,
				'stage2_delay_ms'     => 2000,
				'stage2_batch_size'   => 10,
				// Expiry
				'auto_expiry_enabled' => true,
				'expiry_notice_days'  => 7,
				// Cache / performance
				'cache_api_responses' => false,
				'cache_ttl_minutes'   => 60,
				// Display
				'vacancies_per_page'  => 10,
				'vacancy_slug'        => 'vacancies',
			] );
		}

		// Schedule daily expiry check via Action Scheduler.
		// as_has_scheduled_action() returns false if Action Scheduler isn't
		// loaded yet (e.g. during a fresh install before vendor autoload) –
		// so we guard with function_exists.
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( \ApprenticeshipConnector\Import\ActionSchedulerRunner::HOOK_EXPIRE, [], 'appcon' ) ) {
				// Fire daily at 02:00 UTC.
				$next_2am = strtotime( 'today 02:00:00 UTC' );
				if ( $next_2am < time() ) {
					$next_2am = strtotime( 'tomorrow 02:00:00 UTC' );
				}
				as_schedule_recurring_action(
					$next_2am,
					DAY_IN_SECONDS,
					\ApprenticeshipConnector\Import\ActionSchedulerRunner::HOOK_EXPIRE,
					[],
					'appcon'
				);
			}
		}

		update_option( 'appcon_db_version', APPCON_DB_VERSION );
	}
}
