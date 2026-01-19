<?php
/**
 * Database Manager - Ensures tables exist on every load
 *
 * Fixes the "tables not created" problem permanently
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Apprco_Database
 *
 * Manages database schema and ensures tables exist
 */
class Apprco_Database {

    /**
     * Current database version
     */
    public const VERSION = '3.0.0';

    /**
     * Option name for storing DB version
     */
    public const VERSION_OPTION = 'apprco_db_version';

    /**
     * Singleton instance
     *
     * @var Apprco_Database|null
     */
    private static $instance = null;

    /**
     * Whether tables have been checked this request
     *
     * @var bool
     */
    private static $checked = false;

    /**
     * Get singleton instance
     *
     * @return Apprco_Database
     */
    public static function get_instance(): Apprco_Database {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize database manager
     *
     * Hooks into admin_init to check tables on every admin page load
     */
    public function init(): void {
        // Check tables on admin pages only (performance)
        add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 5 );

        // Also check on plugin activation
        add_action( 'apprco_activate', array( $this, 'upgrade' ) );
    }

    /**
     * Check if upgrade is needed and run if so
     *
     * Runs on every admin page load but uses caching
     */
    public function maybe_upgrade(): void {
        // Only check once per request
        if ( self::$checked ) {
            return;
        }

        self::$checked = true;

        $current_version = get_option( self::VERSION_OPTION, '0' );

        // If version matches, we're good
        if ( version_compare( $current_version, self::VERSION, '>=' ) ) {
            return;
        }

        // Version mismatch - run upgrade
        $this->upgrade();
    }

    /**
     * Run database upgrade
     *
     * Creates/updates all tables
     *
     * @return array Results of upgrade
     */
    public function upgrade(): array {
        $results = array(
            'success' => true,
            'tables_created' => array(),
            'tables_updated' => array(),
            'errors' => array(),
        );

        try {
            // Create/update each table
            $tables = array(
                'import_tasks' => array( 'Apprco_Import_Tasks', 'create_table' ),
                'import_logs'  => array( 'Apprco_Import_Logger', 'create_table' ),
                'employers'    => array( 'Apprco_Employer', 'create_table' ),
            );

            foreach ( $tables as $name => $callback ) {
                try {
                    call_user_func( $callback );
                    $results['tables_created'][] = $name;
                } catch ( Exception $e ) {
                    $results['errors'][] = "Failed to create {$name}: " . $e->getMessage();
                    $results['success'] = false;
                }
            }

            // Verify tables exist
            global $wpdb;
            $prefix = $wpdb->prefix . 'apprco_';

            foreach ( array( 'import_tasks', 'import_logs', 'employers' ) as $table ) {
                $table_name = $prefix . $table;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

                if ( $exists !== $table_name ) {
                    $results['errors'][] = "Table {$table_name} does not exist after creation";
                    $results['success'] = false;
                }
            }

            // Update version if successful
            if ( $results['success'] ) {
                update_option( self::VERSION_OPTION, self::VERSION );
            }

            // Log result
            if ( class_exists( 'Apprco_Import_Logger' ) ) {
                $logger = Apprco_Import_Logger::get_instance();
                $logger->info(
                    sprintf(
                        'Database upgrade %s. Created: %s',
                        $results['success'] ? 'successful' : 'failed',
                        implode( ', ', $results['tables_created'] )
                    ),
                    null,
                    'database'
                );

                if ( ! empty( $results['errors'] ) ) {
                    foreach ( $results['errors'] as $error ) {
                        $logger->error( $error, null, 'database' );
                    }
                }
            }
        } catch ( Exception $e ) {
            $results['success'] = false;
            $results['errors'][] = 'Upgrade failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Check if all tables exist
     *
     * @return bool True if all tables exist
     */
    public function tables_exist(): bool {
        global $wpdb;
        $prefix = $wpdb->prefix . 'apprco_';

        $required_tables = array( 'import_tasks', 'import_logs', 'employers' );

        foreach ( $required_tables as $table ) {
            $table_name = $prefix . $table;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

            if ( $exists !== $table_name ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get database info for debugging
     *
     * @return array Database information
     */
    public function get_info(): array {
        global $wpdb;

        $info = array(
            'version'        => get_option( self::VERSION_OPTION, 'Not set' ),
            'target_version' => self::VERSION,
            'tables'         => array(),
        );

        $prefix = $wpdb->prefix . 'apprco_';
        $tables = array( 'import_tasks', 'import_logs', 'employers' );

        foreach ( $tables as $table ) {
            $table_name = $prefix . $table;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

            $info['tables'][ $table ] = array(
                'name'   => $table_name,
                'exists' => ( $exists === $table_name ),
            );

            if ( $exists === $table_name ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
                $info['tables'][ $table ]['rows'] = (int) $row_count;
            }
        }

        return $info;
    }

    /**
     * Reset database (DANGEROUS - use for testing only)
     *
     * @return bool Success
     */
    public function reset(): bool {
        global $wpdb;

        $prefix = $wpdb->prefix . 'apprco_';
        $tables = array( 'import_tasks', 'import_logs', 'employers' );

        foreach ( $tables as $table ) {
            $table_name = $prefix . $table;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
        }

        delete_option( self::VERSION_OPTION );
        self::$checked = false;

        return true;
    }
}
