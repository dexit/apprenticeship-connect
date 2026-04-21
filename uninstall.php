<?php
/**
 * Uninstall File
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Options
delete_option( 'apprco_settings' );
delete_option( 'apprco_db_version' );
delete_option( 'apprco_last_api_stats' );

// Database tables
global $wpdb;
$tables = array(
    'apprco_import_tasks',
    'apprco_import_logs',
    'apprco_import_runs',
    'apprco_employers'
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}$table" );
}
