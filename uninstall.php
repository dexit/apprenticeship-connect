<?php
/**
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * Removes all plugin-created database tables, options, transients, post-meta,
 * and posts.  This is irreversible – by design.
 *
 * @package ApprenticeshipConnector
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ── Options ────────────────────────────────────────────────────────────────
$options = [
	'appcon_settings',
	'appcon_db_version',
	// Legacy keys (v1/v2 of the old plugin)
	'apprco_plugin_options',
	'apprco_settings',
	'apprco_settings_migrated',
	'apprco_last_sync',
	'apprco_setup_completed',
	'apprco_plugin_activated',
	'apprco_vacancy_page_id',
	'apprco_db_version',
	'apprco_sync_scheduled',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// ── Transients ─────────────────────────────────────────────────────────────
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_appcon_%'
	    OR option_name LIKE '_transient_timeout_appcon_%'
	    OR option_name LIKE '_transient_apprco_%'
	    OR option_name LIKE '_transient_timeout_apprco_%'"
);

// ── Custom tables ──────────────────────────────────────────────────────────
$tables = [
	$wpdb->prefix . 'appcon_vacancy_index',
	$wpdb->prefix . 'appcon_import_logs',
	$wpdb->prefix . 'appcon_import_runs',
	$wpdb->prefix . 'appcon_import_jobs',
	$wpdb->prefix . 'appcon_employers',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// ── CPT posts and meta ─────────────────────────────────────────────────────
$post_types = [ 'appcon_vacancy', 'appcon_employer', 'apprco_vacancy' ];

foreach ( $post_types as $pt ) {
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
			$pt
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
}

// ── User meta (dismissed notice keys) ─────────────────────────────────────
$wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'appcon_dismissed_notices' ] );

// ── Action Scheduler – cancel all plugin actions ───────────────────────────
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	$hooks = [
		'appcon_as_import_start',
		'appcon_as_stage1_page',
		'appcon_as_stage2_batch',
		'appcon_as_expire_vacancies',
		'appcon_run_scheduled_import',
	];

	foreach ( $hooks as $hook ) {
		as_unschedule_all_actions( $hook, [], 'appcon' );
	}
}

// ── WP-Cron (legacy) ───────────────────────────────────────────────────────
wp_clear_scheduled_hook( 'appcon_run_scheduled_import' );
wp_clear_scheduled_hook( 'apprco_daily_fetch_vacancies' );

// ── Rewrite rules ──────────────────────────────────────────────────────────
flush_rewrite_rules();
