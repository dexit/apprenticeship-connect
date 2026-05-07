<?php
/**
 * Import Logger Class - V3.1.0
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Import_Logger
 *
 * Handles logging of import processes and system events.
 */
class Apprco_Import_Logger {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Import_Logger|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Initialization if needed.
	}

	/**
	 * Create database tables for logging.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Runs Table.
		$runs_table = $wpdb->prefix . 'apprco_import_runs';
		$sql        = "CREATE TABLE $runs_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			import_id varchar(50) NOT NULL,
			type varchar(20) NOT NULL,
			provider varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			started_at datetime NOT NULL,
			ended_at datetime DEFAULT NULL,
			items_total int(11) DEFAULT 0,
			items_created int(11) DEFAULT 0,
			items_updated int(11) DEFAULT 0,
			items_deleted int(11) DEFAULT 0,
			items_skipped int(11) DEFAULT 0,
			items_errors int(11) DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY import_id (import_id)
		) $charset_collate;";

		// Logs Table.
		$logs_table = $wpdb->prefix . 'apprco_import_logs';
		$sql2       = "CREATE TABLE $logs_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			import_id varchar(50) NOT NULL,
			level varchar(20) NOT NULL,
			component varchar(50) NOT NULL,
			message text NOT NULL,
			context longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY import_id (import_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $sql2 );
	}

	/**
	 * Start a new import run.
	 *
	 * @param string $type     The type of import (e.g., 'manual', 'scheduled').
	 * @param string $provider The provider ID.
	 * @return string The generated import ID.
	 */
	public function start_import( $type, $provider ): string {
		global $wpdb;
		$import_id = wp_generate_uuid4();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'apprco_import_runs',
			array(
				'import_id'  => $import_id,
				'type'       => $type,
				'provider'   => $provider,
				'status'     => 'running',
				'started_at' => current_time( 'mysql' ),
			)
		);
		return $import_id;
	}

	/**
	 * Log a message to the database.
	 *
	 * @param string $import_id The import ID.
	 * @param string $level     Log level (e.g., 'info', 'error').
	 * @param string $component The component generating the log.
	 * @param string $message   The log message.
	 * @param array  $context   Additional context data.
	 * @return void
	 */
	public function log( $import_id, $level, $component, $message, $context = array() ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'apprco_import_logs',
			array(
				'import_id'  => $import_id,
				'level'      => $level,
				'component'  => $component,
				'message'    => $message,
				'context'    => wp_json_encode( $context ),
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Mark an import run as completed.
	 *
	 * @param string $import_id The import ID.
	 * @param int    $total     Total items processed.
	 * @param int    $created   Number of items created.
	 * @param int    $updated   Number of items updated.
	 * @param int    $deleted   Number of items deleted.
	 * @param int    $skipped   Number of items skipped.
	 * @param int    $errors    Number of errors encountered.
	 * @param string $status    Final status ('completed', 'failed').
	 * @return void
	 */
	public function end_import( $import_id, $total, $created, $updated, $deleted, $skipped, $errors, $status ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'apprco_import_runs',
			array(
				'status'        => $status,
				'ended_at'      => current_time( 'mysql' ),
				'items_total'   => $total,
				'items_created' => $created,
				'items_updated' => $updated,
				'items_deleted' => $deleted,
				'items_skipped' => $skipped,
				'items_errors'  => $errors,
			),
			array( 'import_id' => $import_id )
		);
	}

	/**
	 * Get logs for a specific import run.
	 *
	 * @param string $import_id The import ID.
	 * @return array
	 */
	public function get_logs( $import_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE import_id = %s ORDER BY created_at ASC', $wpdb->prefix . 'apprco_import_logs', $import_id ), ARRAY_A );
	}

	/**
	 * Get general logging statistics.
	 *
	 * @return array
	 */
	public function get_stats(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_logs = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'apprco_import_logs' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_runs = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'apprco_import_runs' ) );

		return array(
			'total_logs' => (int) $total_logs,
			'total_runs' => (int) $total_runs,
		);
	}
}
