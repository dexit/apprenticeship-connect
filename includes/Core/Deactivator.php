<?php
/**
 * Fires on plugin deactivation.
 *
 * @package ApprenticeshipConnector\Core
 */

namespace ApprenticeshipConnector\Core;

class Deactivator {

	public static function deactivate(): void {
		// Clear scheduled cron events.
		$timestamp = wp_next_scheduled( 'appcon_run_scheduled_import' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'appcon_run_scheduled_import' );
		}

		flush_rewrite_rules();
	}
}
