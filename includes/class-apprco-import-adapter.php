<?php
/**
 * Import Adapter - Unified import interface
 *
 * Consolidates all import methods to use Import Tasks as the underlying engine.
 * This ensures consistent behavior across:
 * - Settings page "Manual Sync" button (Apprco_Core::manual_sync)
 * - Import Wizard multi-step UI
 * - Import Tasks scheduled jobs
 *
 * @package ApprenticeshipConnect
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Apprco_Import_Adapter
 *
 * Provides a unified interface for all import operations by delegating
 * to the Import Tasks system.
 */
class Apprco_Import_Adapter {

	/**
	 * Singleton instance
	 *
	 * @var Apprco_Import_Adapter|null
	 */
	private static $instance = null;

	/**
	 * Import Tasks manager
	 *
	 * @var Apprco_Import_Tasks
	 */
	private $tasks_manager;

	/**
	 * Logger instance
	 *
	 * @var Apprco_Import_Logger
	 */
	private $logger;

	/**
	 * Private constructor for singleton
	 */
	private function __construct() {
		$this->tasks_manager = Apprco_Import_Tasks::get_instance();
		$this->logger        = Apprco_Import_Logger::get_instance();
	}

	/**
	 * Get singleton instance
	 *
	 * @return Apprco_Import_Adapter
	 */
	public static function get_instance(): Apprco_Import_Adapter {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run a one-time import using global settings
	 *
	 * This is used by the "Manual Sync" button on the settings page.
	 * Creates a temporary task with global settings and runs it immediately.
	 *
	 * @param array $override_options Optional settings to override global defaults.
	 * @return array Import result with success, fetched, created, updated, errors.
	 */
	public function run_manual_sync( array $override_options = array() ): array {
		// Get settings from Settings Manager (unified settings system)
		$settings_manager = Apprco_Settings_Manager::get_instance();
		$options          = $settings_manager->get_options_array();

		// Merge with overrides
		$options = array_merge( $options, $override_options );

		// Validate required settings
		if ( empty( $options['api_subscription_key'] ) || empty( $options['api_base_url'] ) ) {
			return array(
				'success' => false,
				'error'   => 'API credentials not configured.',
			);
		}

		// Create temporary task
		$task_data = array(
			'name'               => 'Manual Sync - ' . gmdate( 'Y-m-d H:i:s' ),
			'description'        => 'Temporary task for manual sync from settings page',
			'provider_id'        => 'uk-gov-apprenticeships',
			'api_base_url'       => $options['api_base_url'],
			'api_endpoint'       => '',
			'api_method'         => 'GET',
			'api_auth_type'      => 'header_key',
			'api_auth_key'       => 'Ocp-Apim-Subscription-Key',
			'api_auth_value'     => $options['api_subscription_key'],
			'api_headers'        => array( 'X-Version' => '2' ),
			'api_params'         => array( 'Sort' => 'AgeDesc' ),
			'response_format'    => 'json',
			'data_path'          => 'vacancies',
			'total_path'         => 'total',
			'pagination_type'    => 'page_number',
			'page_param'         => 'PageNumber',
			'page_size_param'    => 'PageSize',
			'page_size'          => ! empty( $options['batch_size'] ) ? (int) $options['batch_size'] : 100,
			'field_mappings'     => $this->get_default_field_mapping(),
			'unique_id_field'    => 'vacancyReference',
			'target_post_type'   => 'apprco_vacancy',
			'post_status'        => ! empty( $options['post_status'] ) ? $options['post_status'] : 'publish',
			'transforms_enabled' => 0,
			'transforms_code'    => '',
			'schedule_enabled'   => 0,
			'schedule_frequency' => 'none',
			'status'             => Apprco_Import_Tasks::STATUS_ACTIVE,
		);

		// Add UKPRN filter if configured
		if ( ! empty( $options['api_ukprn'] ) ) {
			$task_data['api_params']['ukprn'] = $options['api_ukprn'];
		}

		// Create task
		$task_id = $this->tasks_manager->create( $task_data );

		if ( ! $task_id ) {
			return array(
				'success' => false,
				'error'   => 'Failed to create import task.',
			);
		}

		// Run import
		$result = $this->tasks_manager->run_import( $task_id );

		// Delete temporary task after import
		$this->tasks_manager->delete( $task_id );

		return $result;
	}

	/**
	 * Run a wizard import
	 *
	 * Used by the Import Wizard. Creates a temporary task with wizard settings
	 * and runs it immediately.
	 *
	 * @param string   $provider_id Provider identifier.
	 * @param array    $params      Import parameters.
	 * @param callable $on_progress Optional progress callback.
	 * @return array Import result.
	 */
	public function run_wizard_import( string $provider_id, array $params = array(), ?callable $on_progress = null ): array {
		$registry = Apprco_Provider_Registry::get_instance();
		$provider = $registry->get( $provider_id );

		if ( ! $provider ) {
			return array(
				'success' => false,
				'error'   => 'Provider not found: ' . $provider_id,
			);
		}

		// Get provider configuration
		$provider_config = $provider->get_config();

		// Create temporary task
		$task_data = array(
			'name'               => 'Wizard Import - ' . $provider_id . ' - ' . gmdate( 'Y-m-d H:i:s' ),
			'provider'           => $provider_id,
			'api_base_url'       => $provider_config['api_base_url'] ?? '',
			'api_endpoint'       => $provider_config['api_endpoint'] ?? '',
			'api_headers'        => $provider_config['api_headers'] ?? array(),
			'api_params'         => array_merge( $provider_config['api_params'] ?? array(), $params ),
			'field_mapping'      => $provider_config['field_mapping'] ?? $this->get_default_field_mapping(),
			'page_param'         => $provider_config['page_param'] ?? 'pageNumber',
			'page_size_param'    => $provider_config['page_size_param'] ?? 'pageSize',
			'page_size'          => $provider_config['page_size'] ?? 100,
			'items_path'         => $provider_config['items_path'] ?? '',
			'schedule_enabled'   => false,
			'schedule_frequency' => 'none',
			'status'             => Apprco_Import_Tasks::STATUS_ACTIVE,
		);

		// Create task
		$task_id = $this->tasks_manager->create( $task_data );

		if ( ! $task_id ) {
			return array(
				'success' => false,
				'error'   => 'Failed to create import task.',
			);
		}

		// Run import with progress callback
		$result = $this->tasks_manager->run_import( $task_id, $on_progress );

		// Delete temporary task after import
		$this->tasks_manager->delete( $task_id );

		return $result;
	}

	/**
	 * Get default field mapping
	 *
	 * @return array Default field mapping configuration.
	 */
	private function get_default_field_mapping(): array {
		return array(
			'title'               => 'title',
			'description'         => 'description',
			'vacancy_reference'   => 'vacancyReference',
			'provider_name'       => 'standardOrFramework',
			'employer_name'       => 'employerName',
			'location'            => 'location',
			'wage_text'           => 'wageText',
			'wage_amount'         => 'wageAmount',
			'hours_per_week'      => 'hoursPerWeek',
			'working_week'        => 'workingWeek',
			'posted_date'         => 'postedDate',
			'closing_date'        => 'closingDate',
			'start_date'          => 'expectedStartDate',
			'duration'            => 'expectedDuration',
			'apprenticeship_level' => 'apprenticeshipLevel',
			'positions_available' => 'numberOfPositions',
			'apply_url'           => 'applicationUrl',
			'contact_name'        => 'contactName',
			'contact_email'       => 'contactEmail',
			'contact_phone'       => 'contactPhone',
		);
	}

	/**
	 * Get import statistics
	 *
	 * Provides unified stats across all import methods.
	 *
	 * @return array Import statistics.
	 */
	public function get_stats(): array {
		$logger    = Apprco_Import_Logger::get_instance();
		$log_stats = $logger->get_stats();

		$total_vacancies = wp_count_posts( 'apprco_vacancy' );

		return array(
			'last_import'      => $log_stats['last_run'],
			'total_imports'    => $log_stats['total_runs'],
			'total_vacancies'  => $total_vacancies->publish ?? 0,
			'draft_vacancies'  => $total_vacancies->draft ?? 0,
			'last_sync'        => get_option( 'apprco_last_sync' ),
			'last_sync_human'  => get_option( 'apprco_last_sync' ) ? human_time_diff( get_option( 'apprco_last_sync' ) ) . ' ago' : 'Never',
		);
	}
}
