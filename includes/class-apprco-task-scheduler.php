<?php
/**
 * Task Scheduler Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Task_Scheduler
 *
 * Handles scheduling of background import tasks.
 */
class Apprco_Task_Scheduler {

	/**
	 * Hook name for import task.
	 */
	public const HOOK_NAME = 'apprco_run_import_task';

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
		add_action( self::HOOK_NAME, array( $this, 'execute_scheduled_task' ), 10, 1 );
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
	 * Initialize scheduler.
	 *
	 * @return void
	 */
	public function init(): void {
		// Initialization logic if needed.
	}

	/**
	 * Schedule a task.
	 *
	 * @param int $task_id Task ID.
	 * @return bool
	 */
	public function schedule_task( int $task_id ): bool {
		// Scheduling logic placeholder.
		return (bool) $task_id;
	}

	/**
	 * Unschedule a task.
	 *
	 * @param int $task_id Task ID.
	 * @return void
	 */
	public function unschedule_task( int $task_id ): void {
		// Unscheduling logic placeholder.
		unset( $task_id );
	}

	/**
	 * Execute a scheduled task.
	 *
	 * @param mixed $args Hook arguments.
	 * @return void
	 */
	public function execute_scheduled_task( $args ): void {
		$task_id = is_array( $args ) ? ( isset( $args['task_id'] ) ? $args['task_id'] : ( isset( $args[0] ) ? $args[0] : 0 ) ) : $args;
		if ( ! $task_id ) {
			return;
		}

		Apprco_Import_Tasks::get_instance()->run_import( (int) $task_id );
	}
}
