<?php
/**
 * Database Manager Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Database
 *
 * Manages database schema and table initialization.
 */
class Apprco_Database {

	/**
	 * Database version.
	 *
	 * @var string
	 */
	public const VERSION = '3.2.0';

	/**
	 * Option name for DB version.
	 *
	 * @var string
	 */
	public const VERSION_OPTION = 'apprco_db_version';

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Database|null
	 */
	private static $instance = null;

	/**
	 * Whether upgrade has been checked.
	 *
	 * @var bool
	 */
	private static $checked = false;

	/**
	 * Get singleton instance.
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
	 * Initialize database hooks.
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Checks if an upgrade is needed.
	 */
	public function maybe_upgrade(): void {
		if ( self::$checked ) {
			return;
		}

		self::$checked = true;

		$current_version = get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $current_version, self::VERSION, '>=' ) ) {
			return;
		}

		$this->upgrade();
	}

	/**
	 * Executes database upgrades/initialization.
	 *
	 * @return array
	 */
	public function upgrade(): array {
		$results = array(
			'success'        => true,
			'tables_created' => array(),
			'errors'         => array(),
		);

		$tables = array(
			'import_tasks' => array( 'Apprco_Import_Tasks', 'create_table' ),
			'import_logs'  => array( 'Apprco_Import_Logger', 'create_table' ),
			'employers'    => array( 'Apprco_Employer', 'create_table' ),
			'vacancies'    => array( 'Apprco_Vacancy_Store', 'create_table' ),
			'workplaces'   => array( 'Apprco_Provider', 'create_workplaces_table' ),
		);

		foreach ( $tables as $name => $callback ) {
			try {
				if ( is_callable( $callback ) ) {
					call_user_func( $callback );
					$results['tables_created'][] = $name;
				}
			} catch ( Exception $e ) {
				$results['errors'][] = $e->getMessage();
				$results['success']  = false;
			}
		}

		if ( $results['success'] ) {
			update_option( self::VERSION_OPTION, self::VERSION );
		}

		return $results;
	}
}
