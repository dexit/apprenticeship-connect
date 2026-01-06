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
delete_option( 'apprco_last_sync' );
delete_option( 'apprco_setup_completed' );
delete_option( 'apprco_plugin_activated' );
delete_option( 'apprco_vacancy_page_id' );

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