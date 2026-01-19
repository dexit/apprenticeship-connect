<?php
/**
 * Import Task Scheduler - WP-Cron Integration
 *
 * Schedules and executes import tasks based on their frequency settings
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Apprco_Task_Scheduler
 */
class Apprco_Task_Scheduler {

    /**
     * Singleton instance
     *
     * @var Apprco_Task_Scheduler|null
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger
     */
    private $logger;

    /**
     * Tasks manager
     *
     * @var Apprco_Import_Tasks
     */
    private $tasks_manager;

    /**
     * Hook name for scheduled imports
     */
    public const HOOK_NAME = 'apprco_run_scheduled_task';

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->logger         = Apprco_Import_Logger::get_instance();
        $this->tasks_manager  = Apprco_Import_Tasks::get_instance();

        // Register WP-Cron hooks
        add_action( self::HOOK_NAME, array( $this, 'execute_scheduled_task' ), 10, 1 );

        // Re-schedule tasks on settings save
        add_action( 'apprco_task_saved', array( $this, 'reschedule_task' ), 10, 1 );

        // Clean up schedules on task deletion
        add_action( 'apprco_task_deleted', array( $this, 'unschedule_task' ), 10, 1 );
    }

    /**
     * Get singleton instance
     *
     * @return Apprco_Task_Scheduler
     */
    public static function get_instance(): Apprco_Task_Scheduler {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize scheduler - schedule all active tasks
     */
    public function init(): void {
        $tasks = $this->tasks_manager->get_all( array( 'status' => 'active', 'schedule_enabled' => 1 ) );

        foreach ( $tasks as $task ) {
            $this->schedule_task( $task['id'] );
        }
    }

    /**
     * Schedule a task
     *
     * @param int $task_id Task ID.
     * @return bool Success.
     */
    public function schedule_task( int $task_id ): bool {
        $task = $this->tasks_manager->get( $task_id );

        if ( ! $task || ! $task['schedule_enabled'] || $task['status'] !== 'active' ) {
            return false;
        }

        // Unschedule any existing schedule first
        $this->unschedule_task( $task_id );

        // Get WP-Cron frequency
        $frequency = $this->map_frequency( $task['schedule_frequency'] );

        if ( ! $frequency ) {
            return false;
        }

        // Calculate next run time based on schedule_time
        $next_run = $this->calculate_next_run( $task['schedule_time'], $frequency );

        // Schedule the event
        wp_schedule_event( $next_run, $frequency, self::HOOK_NAME, array( $task_id ) );

        $this->logger->info(
            sprintf(
                'Scheduled task "%s" (ID: %d) to run %s at %s',
                $task['name'],
                $task_id,
                $frequency,
                gmdate( 'Y-m-d H:i:s', $next_run )
            ),
            null,
            'scheduler'
        );

        return true;
    }

    /**
     * Unschedule a task
     *
     * @param int $task_id Task ID.
     */
    public function unschedule_task( int $task_id ): void {
        $timestamp = wp_next_scheduled( self::HOOK_NAME, array( $task_id ) );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK_NAME, array( $task_id ) );
            $this->logger->info( sprintf( 'Unscheduled task ID: %d', $task_id ), null, 'scheduler' );
        }
    }

    /**
     * Reschedule a task (called after task is saved)
     *
     * @param int $task_id Task ID.
     */
    public function reschedule_task( int $task_id ): void {
        $this->unschedule_task( $task_id );
        $this->schedule_task( $task_id );
    }

    /**
     * Execute a scheduled task
     *
     * @param int $task_id Task ID.
     */
    public function execute_scheduled_task( int $task_id ): void {
        $task = $this->tasks_manager->get( $task_id );

        if ( ! $task ) {
            $this->logger->error( sprintf( 'Scheduled task not found: ID %d', $task_id ), null, 'scheduler' );
            return;
        }

        $this->logger->info(
            sprintf( 'Executing scheduled task: "%s" (ID: %d)', $task['name'], $task_id ),
            null,
            'scheduler'
        );

        // Run the import
        $result = $this->tasks_manager->run_import( $task_id );

        if ( $result['success'] ) {
            $this->logger->info(
                sprintf(
                    'Scheduled task completed: "%s" - Fetched: %d, Created: %d, Updated: %d, Errors: %d',
                    $task['name'],
                    $result['fetched'],
                    $result['created'],
                    $result['updated'],
                    $result['errors']
                ),
                $result['import_id'],
                'scheduler'
            );
        } else {
            $this->logger->error(
                sprintf( 'Scheduled task failed: "%s" - %s', $task['name'], $result['error'] ?? 'Unknown error' ),
                null,
                'scheduler'
            );
        }
    }

    /**
     * Map task frequency to WP-Cron recurrence
     *
     * @param string $frequency Task frequency.
     * @return string|false WP-Cron recurrence key or false.
     */
    private function map_frequency( string $frequency ) {
        $map = array(
            'hourly'      => 'hourly',
            'twicedaily'  => 'twicedaily',
            'daily'       => 'daily',
            'weekly'      => 'weekly',
        );

        return $map[ $frequency ] ?? false;
    }

    /**
     * Calculate next run timestamp
     *
     * @param string $schedule_time Time in HH:MM:SS format.
     * @param string $frequency     Frequency (hourly, daily, etc).
     * @return int Unix timestamp.
     */
    private function calculate_next_run( string $schedule_time, string $frequency ): int {
        // For hourly, run at next hour
        if ( $frequency === 'hourly' ) {
            return strtotime( '+1 hour' );
        }

        // For others, use the schedule_time
        $time_parts = explode( ':', $schedule_time );
        $hour       = (int) ( $time_parts[0] ?? 3 );
        $minute     = (int) ( $time_parts[1] ?? 0 );

        $today = strtotime( sprintf( 'today %02d:%02d:00', $hour, $minute ) );

        // If time has passed today, schedule for tomorrow
        if ( $today < time() ) {
            return strtotime( sprintf( 'tomorrow %02d:%02d:00', $hour, $minute ) );
        }

        return $today;
    }

    /**
     * Get scheduled tasks with next run times
     *
     * @return array List of scheduled tasks.
     */
    public function get_scheduled_tasks(): array {
        $scheduled = array();
        $cron      = _get_cron_array();

        foreach ( $cron as $timestamp => $hooks ) {
            if ( isset( $hooks[ self::HOOK_NAME ] ) ) {
                foreach ( $hooks[ self::HOOK_NAME ] as $hook ) {
                    $task_id = $hook['args'][0] ?? 0;
                    $task    = $this->tasks_manager->get( $task_id );

                    if ( $task ) {
                        $scheduled[] = array(
                            'task_id'   => $task_id,
                            'task_name' => $task['name'],
                            'frequency' => $task['schedule_frequency'],
                            'next_run'  => $timestamp,
                            'next_run_human' => human_time_diff( $timestamp ) . ( $timestamp > time() ? ' from now' : ' ago' ),
                        );
                    }
                }
            }
        }

        return $scheduled;
    }

    /**
     * Unschedule all tasks
     */
    public function unschedule_all(): void {
        $tasks = $this->tasks_manager->get_all();

        foreach ( $tasks as $task ) {
            $this->unschedule_task( $task['id'] );
        }

        $this->logger->info( 'Unscheduled all import tasks', null, 'scheduler' );
    }

    /**
     * Register custom WP-Cron schedules
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public static function add_cron_schedules( array $schedules ): array {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = array(
                'interval' => 604800, // 7 days
                'display'  => __( 'Once Weekly', 'apprenticeship-connect' ),
            );
        }

        return $schedules;
    }
}

// Register custom cron schedules
add_filter( 'cron_schedules', array( 'Apprco_Task_Scheduler', 'add_cron_schedules' ) );
