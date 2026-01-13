<?php
/**
 * Action Scheduler integration class for background job processing
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles scheduled background imports using Action Scheduler
 */
class Apprco_Scheduler {

    /**
     * Action hook for scheduled sync
     *
     * @var string
     */
    public const SYNC_ACTION = 'apprco_scheduled_sync';

    /**
     * Action hook for batch processing
     *
     * @var string
     */
    public const BATCH_ACTION = 'apprco_process_batch';

    /**
     * Action hook for cleanup
     *
     * @var string
     */
    public const CLEANUP_ACTION = 'apprco_cleanup_logs';

    /**
     * Action Scheduler group name
     *
     * @var string
     */
    public const GROUP = 'apprco';

    /**
     * Batch size for processing vacancies
     *
     * @var int
     */
    private const BATCH_SIZE = 50;

    /**
     * Plugin instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger
     */
    private $logger;

    /**
     * Get singleton instance
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
     * Constructor
     */
    private function __construct() {
        $this->logger = new Apprco_Import_Logger();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Register action handlers
        add_action( self::SYNC_ACTION, array( $this, 'handle_scheduled_sync' ) );
        add_action( self::BATCH_ACTION, array( $this, 'handle_batch_process' ), 10, 2 );
        add_action( self::CLEANUP_ACTION, array( $this, 'handle_cleanup' ) );

        // Legacy WP-Cron fallback
        add_action( 'apprco_daily_fetch_vacancies', array( $this, 'handle_scheduled_sync' ) );

        // Admin hooks
        add_action( 'admin_init', array( $this, 'maybe_schedule_events' ) );
    }

    /**
     * Check if Action Scheduler is available
     *
     * @return bool
     */
    public static function has_action_scheduler(): bool {
        return function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_next_scheduled_action' );
    }

    /**
     * Schedule events on plugin init
     */
    public function maybe_schedule_events(): void {
        $this->schedule_sync();
        $this->schedule_cleanup();
    }

    /**
     * Schedule the recurring sync action
     *
     * @param string $frequency Frequency: hourly, twicedaily, daily.
     */
    public function schedule_sync( string $frequency = 'daily' ): void {
        $options   = get_option( 'apprco_plugin_options', array() );
        $frequency = $options['sync_frequency'] ?? 'daily';

        $intervals = array(
            'hourly'     => HOUR_IN_SECONDS,
            'twicedaily' => 12 * HOUR_IN_SECONDS,
            'daily'      => DAY_IN_SECONDS,
            'weekly'     => WEEK_IN_SECONDS,
        );

        $interval = $intervals[ $frequency ] ?? DAY_IN_SECONDS;

        if ( self::has_action_scheduler() ) {
            // Cancel existing scheduled actions
            as_unschedule_all_actions( self::SYNC_ACTION, array(), self::GROUP );

            // Schedule new recurring action
            as_schedule_recurring_action(
                time() + 60, // Start in 1 minute
                $interval,
                self::SYNC_ACTION,
                array(),
                self::GROUP
            );

            $this->logger->log( 'info', sprintf( 'Scheduled sync with Action Scheduler (frequency: %s)', $frequency ) );
        } else {
            // Fallback to WP-Cron
            $hook = 'apprco_daily_fetch_vacancies';

            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }

            wp_schedule_event( time() + 60, $frequency, $hook );

            $this->logger->log( 'info', sprintf( 'Scheduled sync with WP-Cron (frequency: %s)', $frequency ) );
        }

        update_option( 'apprco_sync_scheduled', true );
    }

    /**
     * Schedule cleanup action
     */
    public function schedule_cleanup(): void {
        if ( self::has_action_scheduler() ) {
            if ( ! as_next_scheduled_action( self::CLEANUP_ACTION, array(), self::GROUP ) ) {
                as_schedule_recurring_action(
                    time() + DAY_IN_SECONDS,
                    WEEK_IN_SECONDS,
                    self::CLEANUP_ACTION,
                    array(),
                    self::GROUP
                );
            }
        } else {
            if ( ! wp_next_scheduled( self::CLEANUP_ACTION ) ) {
                wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CLEANUP_ACTION );
            }
        }
    }

    /**
     * Unschedule all events
     */
    public function unschedule_all(): void {
        if ( self::has_action_scheduler() ) {
            as_unschedule_all_actions( self::SYNC_ACTION, array(), self::GROUP );
            as_unschedule_all_actions( self::BATCH_ACTION, array(), self::GROUP );
            as_unschedule_all_actions( self::CLEANUP_ACTION, array(), self::GROUP );
        }

        // Clear WP-Cron schedules
        $hooks = array( 'apprco_daily_fetch_vacancies', self::CLEANUP_ACTION );
        foreach ( $hooks as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }

        delete_option( 'apprco_sync_scheduled' );
        $this->logger->log( 'info', 'All scheduled events unscheduled.' );
    }

    /**
     * Handle the scheduled sync action
     */
    public function handle_scheduled_sync(): void {
        $this->logger->log( 'info', 'Scheduled sync triggered.' );

        // Check if import is already running
        if ( $this->is_import_running() ) {
            $this->logger->log( 'warning', 'Import already running. Skipping scheduled sync.' );
            return;
        }

        $this->set_import_running( true );

        try {
            $core = Apprco_Core::get_instance();
            $result = $core->fetch_and_save_vacancies( 'scheduler' );

            if ( $result ) {
                $this->logger->log( 'info', 'Scheduled sync completed successfully.' );
            } else {
                $this->logger->log( 'error', 'Scheduled sync failed.' );
            }
        } catch ( Exception $e ) {
            $this->logger->log( 'error', 'Scheduled sync exception: ' . $e->getMessage() );
        } finally {
            $this->set_import_running( false );
        }
    }

    /**
     * Handle batch processing of vacancies
     *
     * @param string $import_id Import ID.
     * @param array  $batch     Batch of vacancy data.
     */
    public function handle_batch_process( string $import_id, array $batch ): void {
        $this->logger->log( 'info', sprintf( 'Processing batch of %d vacancies', count( $batch ) ), $import_id );

        $core = Apprco_Core::get_instance();

        foreach ( $batch as $vacancy ) {
            $core->process_single_vacancy( $vacancy, $import_id );
        }

        $this->logger->log( 'info', 'Batch processing completed.', $import_id );
    }

    /**
     * Handle cleanup action
     */
    public function handle_cleanup(): void {
        $this->logger->log( 'info', 'Running scheduled cleanup.' );
        $this->logger->cleanup();
    }

    /**
     * Queue batches for processing using Action Scheduler
     *
     * @param array  $vacancies All vacancies to process.
     * @param string $import_id Import ID.
     */
    public function queue_batches( array $vacancies, string $import_id ): void {
        if ( ! self::has_action_scheduler() ) {
            $this->logger->log( 'warning', 'Action Scheduler not available. Processing synchronously.' );
            return;
        }

        $batches = array_chunk( $vacancies, self::BATCH_SIZE );
        $delay   = 0;

        foreach ( $batches as $batch ) {
            as_schedule_single_action(
                time() + $delay,
                self::BATCH_ACTION,
                array( $import_id, $batch ),
                self::GROUP
            );
            $delay += 5; // 5 seconds between batches
        }

        $this->logger->log( 'info', sprintf( 'Queued %d batches for processing.', count( $batches ) ), $import_id );
    }

    /**
     * Trigger immediate sync
     *
     * @return bool
     */
    public function trigger_immediate_sync(): bool {
        if ( $this->is_import_running() ) {
            return false;
        }

        if ( self::has_action_scheduler() ) {
            as_enqueue_async_action( self::SYNC_ACTION, array(), self::GROUP );
            $this->logger->log( 'info', 'Immediate sync queued via Action Scheduler.' );
        } else {
            // Run synchronously
            $this->handle_scheduled_sync();
        }

        return true;
    }

    /**
     * Check if import is currently running
     *
     * @return bool
     */
    public function is_import_running(): bool {
        return (bool) get_transient( 'apprco_import_running' );
    }

    /**
     * Set import running state
     *
     * @param bool $running Whether import is running.
     */
    public function set_import_running( bool $running ): void {
        if ( $running ) {
            set_transient( 'apprco_import_running', true, HOUR_IN_SECONDS );
        } else {
            delete_transient( 'apprco_import_running' );
        }
    }

    /**
     * Get next scheduled sync time
     *
     * @return int|false Timestamp or false if not scheduled.
     */
    public function get_next_sync_time() {
        if ( self::has_action_scheduler() ) {
            $next = as_next_scheduled_action( self::SYNC_ACTION, array(), self::GROUP );
            return $next ? $next : false;
        }

        return wp_next_scheduled( 'apprco_daily_fetch_vacancies' );
    }

    /**
     * Get pending action count
     *
     * @return int
     */
    public function get_pending_count(): int {
        if ( ! self::has_action_scheduler() ) {
            return 0;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions
                WHERE hook IN (%s, %s) AND status = 'pending' AND `group_id` IN (
                    SELECT group_id FROM {$wpdb->prefix}actionscheduler_groups WHERE slug = %s
                )",
                self::SYNC_ACTION,
                self::BATCH_ACTION,
                self::GROUP
            )
        );

        return (int) $count;
    }

    /**
     * Get scheduler status
     *
     * @return array
     */
    public function get_status(): array {
        $next_sync     = $this->get_next_sync_time();
        $is_running    = $this->is_import_running();
        $pending_count = $this->get_pending_count();
        $options       = get_option( 'apprco_plugin_options', array() );

        return array(
            'action_scheduler_available' => self::has_action_scheduler(),
            'is_running'                 => $is_running,
            'next_sync'                  => $next_sync,
            'next_sync_formatted'        => $next_sync ? wp_date( 'Y-m-d H:i:s', $next_sync ) : null,
            'pending_actions'            => $pending_count,
            'frequency'                  => $options['sync_frequency'] ?? 'daily',
            'last_sync'                  => get_option( 'apprco_last_sync' ),
        );
    }
}
