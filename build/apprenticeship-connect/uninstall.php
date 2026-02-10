<?php
/**
 * Uninstall file for Apprenticeship Connect
 *
 * This file is executed when the plugin is deleted from WordPress.
 * It removes all plugin data from the database.
 *
 * @package ApprenticeshipConnect
 * @version 1.2.0
 */

// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'apprco_plugin_options' );
delete_option( 'apprco_settings' );
delete_option( 'apprco_settings_migrated' );
delete_option( 'apprco_last_sync' );
delete_option( 'apprco_setup_completed' );
delete_option( 'apprco_plugin_activated' );
delete_option( 'apprco_vacancy_page_id' );
delete_option( 'apprco_db_version' );
delete_option( 'apprco_sync_scheduled' );

// Clear all transients
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_apprco_%' OR option_name LIKE '_transient_timeout_apprco_%'" );

// Drop custom tables
require_once plugin_dir_path( __FILE__ ) . 'includes/class-apprco-import-logger.php';
Apprco_Import_Logger::drop_table();

// Delete all vacancy posts
$vacancies = get_posts( array(
    'post_type'      => 'apprco_vacancy',
    'post_status'    => 'any',
    'numberposts'    => -1,
    'fields'         => 'ids',
) );

foreach ( $vacancies as $vacancy_id ) {
    wp_delete_post( $vacancy_id, true );
}

// Clear any scheduled cron events
wp_clear_scheduled_hook( 'apprco_daily_fetch_vacancies' );

// Flush rewrite rules
flush_rewrite_rules();