<?php
/**
 * Task Scheduler
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Task_Scheduler {

	public const HOOK_NAME = 'apprco_run_import_task';
	private static $instance = null;
	private $tasks_manager;
	private $logger;

	private function __construct() {
		$this->tasks_manager = Apprco_Import_Tasks::get_instance();
		$this->logger        = Apprco_Import_Logger::get_instance();

		add_action( self::HOOK_NAME, array( $this, 'execute_scheduled_task' ), 10, 1 );
	}

	public static function get_instance(): Apprco_Task_Scheduler {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		if ( ! is_admin() && ! wp_doing_cron() && ! ( defined( 'ACTION_SCHEDULER_VERSION' ) ) ) {
			return;
		}

		$tasks = $this->tasks_manager->get_all();
		foreach ( $tasks as $task ) {
			if ( 'active' === $task['status'] && $task['schedule_enabled'] ) {
				$this->schedule_task( (int) $task['id'] );
			}
		}
	}

	public function schedule_task( int $task_id ): bool {
		$task = $this->tasks_manager->get( $task_id );
		if ( ! $task ) {
			return false;
		}

		$this->unschedule_task( $task_id );

		$frequency = $task['schedule_frequency'];
		$next_run  = $this->calculate_next_run( $task['schedule_time'] );

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			$interval = $this->get_interval_seconds( $frequency );
			as_schedule_recurring_action( $next_run, $interval, self::HOOK_NAME, array( 'task_id' => $task_id ), 'apprco' );
			$this->logger->info( "Scheduled task (ID: $task_id) via Action Scheduler. Next run: " . wp_date( 'Y-m-d H:i:s', $next_run ), null, 'scheduler' );
		} else {
			wp_schedule_event( $next_run, $frequency, self::HOOK_NAME, array( 'task_id' => $task_id ) );
			$this->logger->info( "Scheduled task (ID: $task_id) via WP-Cron. Next run: " . wp_date( 'Y-m-d H:i:s', $next_run ), null, 'scheduler' );
		}

		return true;
	}

	public function unschedule_task( int $task_id ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_NAME, array( 'task_id' => $task_id ), 'apprco' );
		}
		$timestamp = wp_next_scheduled( self::HOOK_NAME, array( 'task_id' => $task_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_NAME, array( 'task_id' => $task_id ) );
		}
	}

	public function execute_scheduled_task( $args ): void {
		$task_id = is_array( $args ) ? ( $args['task_id'] ?? $args[0] ?? 0 ) : $args;
		if ( ! $task_id ) {
			return;
		}

		$this->logger->info( "Executing scheduled task (ID: $task_id)", null, 'scheduler' );
		$this->tasks_manager->run_import( (int) $task_id );
	}

	private function calculate_next_run( $time ) {
		$timestamp = strtotime( "today $time" );
		if ( ! $timestamp || $timestamp < time() ) {
			$timestamp = strtotime( "tomorrow $time" );
		}
		return $timestamp;
	}

	private function get_interval_seconds( $freq ) {
		$map = array(
			'hourly'      => HOUR_IN_SECONDS,
			'twicedaily'  => 12 * HOUR_IN_SECONDS,
			'daily'       => DAY_IN_SECONDS,
			'weekly'      => WEEK_IN_SECONDS,
		);
		return $map[ $freq ] ?? DAY_IN_SECONDS;
	}

	public function unschedule_all(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_NAME, array(), 'apprco' );
		}
		wp_clear_scheduled_hook( self::HOOK_NAME );
	}
}
