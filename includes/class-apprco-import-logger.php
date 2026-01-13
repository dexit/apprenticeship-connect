<?php
/**
 * Import Logger class for detailed import logging
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles logging for import operations
 */
class Apprco_Import_Logger {

    /**
     * Custom database table name (without prefix)
     *
     * @var string
     */
    private const TABLE_NAME = 'apprco_import_logs';

    /**
     * Log levels
     *
     * @var array
     */
    private const LOG_LEVELS = array( 'debug', 'info', 'warning', 'error' );

    /**
     * Maximum log retention in days
     *
     * @var int
     */
    private const MAX_RETENTION_DAYS = 30;

    /**
     * Maximum log entries to keep
     *
     * @var int
     */
    private const MAX_ENTRIES = 10000;

    /**
     * Get the full table name with prefix
     *
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the logs table on plugin activation
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            import_id varchar(36) NOT NULL,
            log_level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            context longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY import_id (import_id),
            KEY log_level (log_level),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Create import runs table for tracking individual imports
        $runs_table = $wpdb->prefix . 'apprco_import_runs';
        $sql_runs = "CREATE TABLE IF NOT EXISTS {$runs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            import_id varchar(36) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'running',
            started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            total_fetched int(11) DEFAULT 0,
            total_created int(11) DEFAULT 0,
            total_updated int(11) DEFAULT 0,
            total_deleted int(11) DEFAULT 0,
            total_skipped int(11) DEFAULT 0,
            error_count int(11) DEFAULT 0,
            trigger_type varchar(50) DEFAULT 'manual',
            PRIMARY KEY (id),
            UNIQUE KEY import_id (import_id),
            KEY status (status),
            KEY started_at (started_at)
        ) {$charset_collate};";

        dbDelta( $sql_runs );
    }

    /**
     * Drop the logs table on plugin uninstall
     */
    public static function drop_table(): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}apprco_import_logs" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}apprco_import_runs" );
    }

    /**
     * Generate a unique import ID
     *
     * @return string
     */
    public function generate_import_id(): string {
        return wp_generate_uuid4();
    }

    /**
     * Start a new import run
     *
     * @param string $trigger_type Type of trigger (manual, cron, scheduler).
     * @return string Import ID.
     */
    public function start_import( string $trigger_type = 'manual' ): string {
        global $wpdb;

        $import_id = $this->generate_import_id();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $wpdb->prefix . 'apprco_import_runs',
            array(
                'import_id'    => $import_id,
                'status'       => 'running',
                'started_at'   => current_time( 'mysql' ),
                'trigger_type' => $trigger_type,
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        $this->log( 'info', sprintf( 'Import started (trigger: %s)', $trigger_type ), $import_id );

        return $import_id;
    }

    /**
     * End an import run
     *
     * @param string $import_id    Import ID.
     * @param int    $total_fetched Total fetched count.
     * @param int    $created      Created count.
     * @param int    $updated      Updated count.
     * @param int    $deleted      Deleted count.
     * @param int    $skipped      Skipped count.
     * @param int    $errors       Error count.
     * @param string $status       Final status.
     */
    public function end_import(
        string $import_id,
        int $total_fetched = 0,
        int $created = 0,
        int $updated = 0,
        int $deleted = 0,
        int $skipped = 0,
        int $errors = 0,
        string $status = 'completed'
    ): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->prefix . 'apprco_import_runs',
            array(
                'status'        => $status,
                'completed_at'  => current_time( 'mysql' ),
                'total_fetched' => $total_fetched,
                'total_created' => $created,
                'total_updated' => $updated,
                'total_deleted' => $deleted,
                'total_skipped' => $skipped,
                'error_count'   => $errors,
            ),
            array( 'import_id' => $import_id ),
            array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d' ),
            array( '%s' )
        );

        $this->log(
            'info',
            sprintf(
                'Import %s: fetched=%d, created=%d, updated=%d, deleted=%d, skipped=%d, errors=%d',
                $status,
                $total_fetched,
                $created,
                $updated,
                $deleted,
                $skipped,
                $errors
            ),
            $import_id
        );
    }

    /**
     * Log a message
     *
     * @param string      $level     Log level (debug, info, warning, error).
     * @param string      $message   Log message.
     * @param string|null $import_id Optional. Import ID for association.
     * @param array       $context   Optional. Additional context data.
     */
    public function log( string $level, string $message, ?string $import_id = null, array $context = array() ): void {
        global $wpdb;

        if ( ! in_array( $level, self::LOG_LEVELS, true ) ) {
            $level = 'info';
        }

        $import_id = $import_id ?? 'system';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            self::get_table_name(),
            array(
                'import_id'  => $import_id,
                'log_level'  => $level,
                'message'    => $message,
                'context'    => ! empty( $context ) ? wp_json_encode( $context ) : null,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        // Also log to error_log for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[Apprco][%s][%s] %s', strtoupper( $level ), $import_id, $message ) );
        }
    }

    /**
     * Get recent import runs
     *
     * @param int $limit Number of runs to retrieve.
     * @return array
     */
    public function get_import_runs( int $limit = 20 ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}apprco_import_runs ORDER BY started_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get logs for a specific import
     *
     * @param string $import_id Import ID.
     * @param int    $limit     Number of logs to retrieve.
     * @return array
     */
    public function get_logs_by_import( string $import_id, int $limit = 100 ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE import_id = %s ORDER BY created_at ASC LIMIT %d",
                self::get_table_name(),
                $import_id,
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get recent logs
     *
     * @param int         $limit  Number of logs to retrieve.
     * @param string|null $level  Optional. Filter by log level.
     * @param int         $offset Optional. Offset for pagination.
     * @return array
     */
    public function get_recent_logs( int $limit = 100, ?string $level = null, int $offset = 0 ): array {
        global $wpdb;

        $table = self::get_table_name();

        if ( $level && in_array( $level, self::LOG_LEVELS, true ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE log_level = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $table,
                    $level,
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $table,
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    /**
     * Get log statistics
     *
     * @return array
     */
    public function get_stats(): array {
        global $wpdb;

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $by_level = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT log_level, COUNT(*) as count FROM %i GROUP BY log_level",
                $table
            ),
            ARRAY_A
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_runs = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}apprco_import_runs"
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $last_run = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}apprco_import_runs ORDER BY started_at DESC LIMIT 1",
            ARRAY_A
        );

        $level_counts = array();
        foreach ( $by_level as $row ) {
            $level_counts[ $row['log_level'] ] = (int) $row['count'];
        }

        return array(
            'total_logs'   => (int) $total,
            'by_level'     => $level_counts,
            'total_runs'   => (int) $total_runs,
            'last_run'     => $last_run,
        );
    }

    /**
     * Cleanup old logs
     */
    public function cleanup(): void {
        global $wpdb;

        $table = self::get_table_name();

        // Delete logs older than retention period
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted_by_date = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $table,
                self::MAX_RETENTION_DAYS
            )
        );

        // Keep only the most recent entries if over limit
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table ) );

        if ( $count > self::MAX_ENTRIES ) {
            $to_delete = $count - self::MAX_ENTRIES;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM %i ORDER BY created_at ASC LIMIT %d",
                    $table,
                    $to_delete
                )
            );
        }

        // Clean old import runs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}apprco_import_runs WHERE started_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                self::MAX_RETENTION_DAYS
            )
        );

        $this->log( 'info', sprintf( 'Log cleanup completed. Deleted %d old entries by date.', $deleted_by_date ) );
    }

    /**
     * Clear all logs
     */
    public function clear_all(): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( $wpdb->prepare( "TRUNCATE TABLE %i", self::get_table_name() ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}apprco_import_runs" );
    }

    /**
     * Export logs to CSV
     *
     * @param string|null $import_id Optional. Filter by import ID.
     * @return string CSV content.
     */
    public function export_csv( ?string $import_id = null ): string {
        global $wpdb;

        $table = self::get_table_name();

        if ( $import_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $logs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE import_id = %s ORDER BY created_at ASC",
                    $table,
                    $import_id
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $logs = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i ORDER BY created_at DESC LIMIT 5000",
                    $table
                ),
                ARRAY_A
            );
        }

        $output = "ID,Import ID,Level,Message,Context,Created At\n";

        foreach ( $logs as $log ) {
            $output .= sprintf(
                "%d,%s,%s,\"%s\",\"%s\",%s\n",
                $log['id'],
                $log['import_id'],
                $log['log_level'],
                str_replace( '"', '""', $log['message'] ),
                str_replace( '"', '""', $log['context'] ?? '' ),
                $log['created_at']
            );
        }

        return $output;
    }
}
