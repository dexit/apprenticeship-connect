<?php
/**
 * Uninstall — remove all plugin data when the plugin is deleted.
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

global $wpdb;

// Remove options.
$options = array(
	'apprco_settings',
	'apprco_db_version',
	'apprco_last_api_stats',
);
foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove all plugin transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_apprco_%' OR option_name LIKE '_transient_timeout_apprco_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

// Drop custom tables (use %i identifier placeholder — safe for table names).
$tables = array(
	'apprco_import_tasks',
	'apprco_import_runs',
	'apprco_import_logs',
	'apprco_employers',
	'apprco_vacancies',
	'apprco_workplaces',
	'apprco_enquiries',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
}

// Remove CPT posts and associated meta.
$post_types = array( 'apprco_vacancy', 'apprco_provider' );
foreach ( $post_types as $post_type ) {
	$posts = get_posts(
		array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

// Unschedule all Action Scheduler actions registered by this plugin.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( null, null, 'apprco' );
}
