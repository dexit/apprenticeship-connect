<?php
/**
 * Task Scheduler Class - Two-stage import with Action Scheduler
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Task_Scheduler
 *
 * Handles scheduling of background import tasks using Action Scheduler.
 */
class Apprco_Task_Scheduler {

	/**
	 * Hook name for stage 1 (listing fetch).
	 */
	public const HOOK_STAGE1 = 'apprco_import_stage1';

	/**
	 * Hook name for stage 2 (individual vacancy deep-fetch).
	 */
	public const HOOK_STAGE2 = 'apprco_import_stage2';

	/**
	 * Legacy hook name for backwards compatibility.
	 */
	public const HOOK_LEGACY = 'apprco_run_import_task';

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Task_Scheduler|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
	}

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
	 * Initialize scheduler — register action hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( self::HOOK_STAGE1, array( $this, 'run_stage1' ), 10, 1 );
		add_action( self::HOOK_STAGE2, array( $this, 'run_stage2' ), 10, 2 );
		// Backwards compatibility: legacy hook routes through run_stage1.
		add_action( self::HOOK_LEGACY, array( $this, 'run_stage1' ), 10, 1 );
	}

	/**
	 * Schedule a recurring or single import task using Action Scheduler.
	 *
	 * @param int $task_id Task ID.
	 * @return bool True on success.
	 */
	public function schedule_task( int $task_id ): bool {
		$task = Apprco_Import_Tasks::get_instance()->get( $task_id );
		if ( ! $task ) {
			return false;
		}

		// Unschedule any existing actions first.
		$this->unschedule_task( $task_id );

		if ( empty( $task['schedule_enabled'] ) ) {
			return false;
		}

		$args = array( 'task_id' => $task_id );

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			$interval   = $this->frequency_to_seconds( isset( $task['schedule_frequency'] ) ? $task['schedule_frequency'] : 'daily' );
			$first_time = $this->calculate_first_run( isset( $task['schedule_time'] ) ? $task['schedule_time'] : '03:00:00' );

			as_schedule_recurring_action( $first_time, $interval, self::HOOK_STAGE1, $args, 'apprco' );
			return true;
		}

		// Fallback to WP cron if Action Scheduler is unavailable.
		if ( ! wp_next_scheduled( self::HOOK_LEGACY, array( $task_id ) ) ) {
			$interval = isset( $task['schedule_frequency'] ) ? $task['schedule_frequency'] : 'daily';
			wp_schedule_event( time(), $interval, self::HOOK_LEGACY, array( $task_id ) );
		}

		return true;
	}

	/**
	 * Unschedule all scheduled actions for a task.
	 *
	 * @param int $task_id Task ID.
	 * @return void
	 */
	public function unschedule_task( int $task_id ): void {
		$args = array( 'task_id' => $task_id );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_STAGE1, $args, 'apprco' );
		}

		// Also clear WP cron legacy.
		$timestamp = wp_next_scheduled( self::HOOK_LEGACY, array( $task_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_LEGACY, array( $task_id ) );
		}
	}

	/**
	 * Immediately enqueue an async stage1 action for a task.
	 *
	 * @param int $task_id Task ID.
	 * @return void
	 */
	public function run_now( int $task_id ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_STAGE1, array( 'task_id' => $task_id ), 'apprco' );
			return;
		}

		// Fallback: run synchronously.
		Apprco_Import_Tasks::get_instance()->run_stage1( $task_id );
	}

	/**
	 * Action Scheduler callback for HOOK_STAGE1.
	 *
	 * @param mixed $args Hook arguments from Action Scheduler (array or task_id int).
	 * @return void
	 */
	public function run_stage1( $args ): void {
		$task_id = $this->extract_task_id( $args );
		if ( ! $task_id ) {
			return;
		}

		Apprco_Import_Tasks::get_instance()->run_stage1( $task_id );
	}

	/**
	 * Action Scheduler callback for HOOK_STAGE2.
	 *
	 * @param mixed $args Hook arguments — expects array with task_id and ref keys.
	 * @return void
	 */
	public function run_stage2( $args ): void {
		if ( is_array( $args ) ) {
			$task_id = isset( $args['task_id'] ) ? (int) $args['task_id'] : 0;
			$ref     = isset( $args['ref'] ) ? (string) $args['ref'] : '';
		} else {
			// Fallback for positional arguments passed directly.
			$task_id = (int) $args;
			$ref     = '';
		}

		if ( ! $task_id || empty( $ref ) ) {
			return;
		}

		Apprco_Import_Tasks::get_instance()->run_stage2_single( $task_id, $ref );
	}

	/**
	 * Extract task_id from flexible args format.
	 *
	 * @param mixed $args Action Scheduler args.
	 * @return int Task ID or 0.
	 */
	private function extract_task_id( $args ): int {
		if ( is_array( $args ) ) {
			return isset( $args['task_id'] ) ? (int) $args['task_id'] : ( isset( $args[0] ) ? (int) $args[0] : 0 );
		}
		return (int) $args;
	}

	/**
	 * Convert a schedule frequency string to seconds.
	 *
	 * @param string $frequency Frequency name (hourly, twicedaily, daily, weekly).
	 * @return int Number of seconds.
	 */
	private function frequency_to_seconds( string $frequency ): int {
		$map = array(
			'hourly'     => HOUR_IN_SECONDS,
			'twicedaily' => 12 * HOUR_IN_SECONDS,
			'daily'      => DAY_IN_SECONDS,
			'weekly'     => WEEK_IN_SECONDS,
		);
		return isset( $map[ $frequency ] ) ? $map[ $frequency ] : DAY_IN_SECONDS;
	}

	/**
	 * Calculate the first run timestamp based on a time-of-day string.
	 *
	 * @param string $time_string Time string in HH:MM:SS format.
	 * @return int Unix timestamp for next occurrence of that time.
	 */
	private function calculate_first_run( string $time_string ): int {
		$parts = explode( ':', $time_string );
		$hour  = isset( $parts[0] ) ? (int) $parts[0] : 3;
		$min   = isset( $parts[1] ) ? (int) $parts[1] : 0;

		$today_ts = mktime( $hour, $min, 0, (int) gmdate( 'n' ), (int) gmdate( 'j' ), (int) gmdate( 'Y' ) );
		if ( $today_ts <= time() ) {
			$today_ts += DAY_IN_SECONDS;
		}
		return $today_ts;
	}
}
