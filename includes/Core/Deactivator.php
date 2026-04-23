<?php
/**
 * Fires on plugin deactivation.
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

class Deactivator {

	public static function deactivate(): void {
		// Clear legacy WP-Cron event.
		$timestamp = wp_next_scheduled( 'appcon_run_scheduled_import' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'appcon_run_scheduled_import' );
		}

		// Cancel Action Scheduler recurring expiry action.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions(
				\ApprenticeshipConnector\Import\ActionSchedulerRunner::HOOK_EXPIRE,
				[],
				'appcon'
			);
		}

		flush_rewrite_rules();
	}
}
