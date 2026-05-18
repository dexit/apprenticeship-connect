<?php
/**
 * Import Task Repository - Two-Stage Import Pipeline
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Import_Tasks
 *
 * Handles database operations for import tasks and the two-stage import pipeline.
 */
class Apprco_Import_Tasks {

	/**
	 * Table name without prefix.
	 */
	private const TABLE_NAME = 'apprco_import_tasks';

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Import_Tasks|null
	 */
	private static $instance = null;

	/**
	 * Full table name with prefix.
	 *
	 * @var string
	 */
	private $table;

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
		global $wpdb;
		$this->table = $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create database table.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			provider_id varchar(100) NOT NULL DEFAULT 'uk-gov-apprenticeships',
			api_base_url varchar(500) NOT NULL,
			api_endpoint varchar(255) NOT NULL DEFAULT '/vacancy',
			api_headers longtext DEFAULT NULL,
			api_params longtext DEFAULT NULL,
			page_param varchar(50) DEFAULT 'PageNumber',
			data_path varchar(255) NOT NULL DEFAULT 'vacancies',
			total_path varchar(255) DEFAULT 'total',
			unique_id_field varchar(100) NOT NULL DEFAULT 'vacancyReference',
			field_mappings longtext NOT NULL,
			post_status varchar(20) NOT NULL DEFAULT 'publish',
			schedule_enabled tinyint(1) DEFAULT 0,
			schedule_frequency varchar(50) DEFAULT 'daily',
			schedule_time time DEFAULT '03:00:00',
			last_run_at datetime DEFAULT NULL,
			total_runs int(11) DEFAULT 0,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get all tasks.
	 *
	 * @return array
	 */
	public function get_all(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $this->table ), ARRAY_A );
		return array_map( array( $this, 'decode_task' ), $results ? $results : array() );
	}

	/**
	 * Get a single task.
	 *
	 * @param int $id Task ID.
	 * @return array|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ), ARRAY_A );
		return $row ? $this->decode_task( $row ) : null;
	}

	/**
	 * Create a task.
	 *
	 * @param array $data Task data.
	 * @return int Created ID.
	 */
	public function create( array $data ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->table, $this->encode_task( $data ) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a task.
	 *
	 * @param int   $id   Task ID.
	 * @param array $data Task data.
	 * @return bool Success.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->update( $this->table, $this->encode_task( $data ), array( 'id' => $id ) );
	}

	/**
	 * Delete a task.
	 *
	 * @param int $id Task ID.
	 * @return bool Success.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->delete( $this->table, array( 'id' => $id ) );
	}

	// -------------------------------------------------------------------------
	// Two-Stage Import Pipeline
	// -------------------------------------------------------------------------

	/**
	 * Stage 1: Fetch all pages from the listing API and enqueue per-vacancy
	 * Stage 2 deep-fetch actions.
	 *
	 * @param int $task_id Task ID.
	 * @return array Result summary.
	 */
	public function run_stage1( int $task_id ): array {
		$task = $this->get( $task_id );
		if ( ! $task ) {
			return array(
				'success' => false,
				'error'   => 'Task not found',
			);
		}

		/**
		 * Action before stage 1 starts.
		 */
		do_action( 'apprco_before_import_task', $task );

		$logger    = Apprco_Import_Logger::get_instance();
		$import_id = $logger->start_import( 'stage1', $task['provider_id'] );

		$settings = Apprco_Settings_Manager::get_instance();
		$client   = new Apprco_API_Client( $task['api_base_url'] );
		$client->set_import_id( $import_id );
		$client->set_default_headers( $task['api_headers'] );

		$fetch_res = $client->fetch_all_pages(
			$task['api_endpoint'],
			$task['api_params'],
			$task['page_param'],
			$task['data_path'],
			$task['total_path'],
			$settings->get( 'import', 'max_pages', 0 )
		);

		if ( ! $fetch_res['success'] ) {
			$logger->end_import( $import_id, 0, 0, 0, 0, 0, 1, 'failed' );
			return $fetch_res;
		}

		$store      = Apprco_Vacancy_Store::get_instance();
		$batch_size = (int) $settings->get( 'import', 'stage2_batch_size', 50 );
		$upserted   = 0;
		$errors     = 0;
		$refs       = array();

		foreach ( $fetch_res['items'] as $item ) {
			// Upsert with stage 1 data only.
			$item['import_stage'] = 1;
			$row_id               = $store->upsert( $item, $task_id );

			if ( $row_id ) {
				$uid = isset( $item[ $task['unique_id_field'] ] ) ? (string) $item[ $task['unique_id_field'] ] : '';
				if ( $uid ) {
					$refs[] = $uid;
					++$upserted;
				}
			} else {
				++$errors;
			}
		}

		// Enqueue per-vacancy stage 2 actions in batches.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$enqueued = 0;
			foreach ( $refs as $ref ) {
				// Respect batch size to avoid flooding the AS queue in one go.
				if ( $batch_size > 0 && $enqueued >= $batch_size ) {
					break;
				}
				as_enqueue_async_action(
					Apprco_Task_Scheduler::HOOK_STAGE2,
					array(
						'task_id' => $task_id,
						'ref'     => $ref,
					),
					'apprco'
				);
				++$enqueued;
			}

			// If we hit the batch limit, schedule remaining via a follow-up action.
			if ( $batch_size > 0 && count( $refs ) > $batch_size ) {
				$remaining = array_slice( $refs, $batch_size );
				foreach ( $remaining as $ref ) {
					as_enqueue_async_action(
						Apprco_Task_Scheduler::HOOK_STAGE2,
						array(
							'task_id' => $task_id,
							'ref'     => $ref,
						),
						'apprco'
					);
				}
			}
		}

		$this->update_stats( $task_id );
		$logger->end_import( $import_id, count( $fetch_res['items'] ), $upserted, 0, 0, 0, $errors, 'completed' );

		$message = sprintf( 'Stage 1 complete: %d vacancies queued for deep fetch', $upserted );

		/**
		 * Action after stage 1 completes.
		 */
		do_action( 'apprco_stage1_complete', $task_id, $import_id, $upserted );

		return array(
			'success'   => true,
			'import_id' => $import_id,
			'fetched'   => count( $fetch_res['items'] ),
			'upserted'  => $upserted,
			'errors'    => $errors,
			'message'   => $message,
		);
	}

	/**
	 * Stage 2: Fetch full details for a single vacancy and update the store.
	 *
	 * @param int    $task_id Task ID.
	 * @param string $ref     Vacancy reference.
	 * @return void
	 */
	public function run_stage2_single( int $task_id, string $ref ): void {
		$task = $this->get( $task_id );
		if ( ! $task ) {
			return;
		}

		$logger    = Apprco_Import_Logger::get_instance();
		$import_id = $this->get_stage2_import_id( $task_id, $logger );

		$client = new Apprco_API_Client( $task['api_base_url'] );
		$client->set_default_headers( $task['api_headers'] );
		$client->set_import_id( $import_id );

		$endpoint = rtrim( $task['api_endpoint'], '/' ) . '/' . rawurlencode( $ref );

		$logger->log( $import_id, 'info', 'stage2', sprintf( 'Fetching detail for vacancy %s', $ref ) );

		$response = $client->get( $endpoint );

		if ( ! $response['success'] ) {
			$logger->log(
				$import_id,
				'error',
				'stage2',
				sprintf( 'Stage 2 failed for ref %s: %s (code: %d)', $ref, $response['error'] ?? 'unknown', $response['code'] ?? 0 )
			);
			return;
		}

		$detail_data = $response['data'] ?? array();

		// Ensure the reference field is present so the store can identify the row.
		if ( ! isset( $detail_data[ $task['unique_id_field'] ] ) ) {
			$detail_data[ $task['unique_id_field'] ] = $ref;
		}
		$detail_data['import_stage'] = 2;

		$store  = Apprco_Vacancy_Store::get_instance();
		$row_id = $store->upsert( $detail_data, $task_id );

		if ( $row_id ) {
			$store->mark_stage_2( $ref );
			$this->process_to_cpt( $detail_data, $task );

			$api_stats = $client->get_stats();
			$logger->log( $import_id, 'info', 'stage2', sprintf(
				'Vacancy %s stored (row %d). API stats — requests:%d retries:%d errors:%d remaining:%s',
				$ref,
				$row_id,
				$api_stats['requests'],
				$api_stats['retries'],
				$api_stats['errors'],
				$api_stats['remaining'] ?? 'n/a'
			) );

			/**
			 * Action after a stage 2 vacancy is fully fetched.
			 */
			do_action( 'apprco_stage2_vacancy_complete', $ref, $task_id, $detail_data );
		} else {
			$logger->log( $import_id, 'error', 'stage2', sprintf( 'DB upsert failed for vacancy %s', $ref ) );
		}
	}

	/**
	 * Get (or create) a persistent stage-2 import_id for the given task.
	 * Re-uses the same run across all per-vacancy AS jobs for the task so logs
	 * are grouped together in the dashboard.
	 *
	 * @param int                  $task_id Task ID.
	 * @param Apprco_Import_Logger $logger  Logger instance.
	 * @return string Import UUID.
	 */
	private function get_stage2_import_id( int $task_id, Apprco_Import_Logger $logger ): string {
		$transient_key = 'apprco_s2_importid_' . $task_id;
		$import_id     = get_transient( $transient_key );

		if ( ! $import_id ) {
			$task      = $this->get( $task_id );
			$import_id = $logger->start_import( 'stage2', $task['provider_id'] ?? 'uk-gov-apprenticeships' );
			// Keep for 24 h — long enough to cover a full deep-fetch batch.
			set_transient( $transient_key, $import_id, DAY_IN_SECONDS );
		}

		return $import_id;
	}

	/**
	 * Map API detail data to CPT, meta, taxonomies, provider, and geocoder.
	 *
	 * @param array $data  Full vacancy detail data from the API.
	 * @param array $task  Decoded task row (includes field_mappings).
	 * @return void
	 */
	private function process_to_cpt( array $data, array $task ): void {
		$mappings = ! empty( $task['field_mappings'] ) ? $task['field_mappings'] : Apprco_DTO_Mapper::default_mappings();
		$mapper   = new Apprco_DTO_Mapper();
		$mapped   = $mapper->map( $data, $mappings );

		$post_data = $mapped['post_data'] ?? array();
		$meta      = $mapped['meta'] ?? array();
		$taxs      = $mapped['taxonomies'] ?? array();

		// Find existing CPT post by vacancy reference.
		$ref          = $data[ $task['unique_id_field'] ] ?? '';
		$existing_ids = get_posts(
			array(
				'post_type'      => 'apprco_vacancy',
				'meta_key'       => '_apprco_vacancy_ref',
				'meta_value'     => $ref,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post_status'    => 'any',
			)
		);

		if ( ! empty( $existing_ids ) ) {
			$post_data['ID'] = $existing_ids[0];
		}

		$post_data['post_type']   = 'apprco_vacancy';
		$post_data['post_status'] = $post_data['post_status'] ?? 'publish';

		$post_id = empty( $post_data['ID'] ) ? wp_insert_post( $post_data, true ) : wp_update_post( $post_data, true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		// Always store the reference for future lookups.
		update_post_meta( $post_id, '_apprco_vacancy_ref', $ref );

		// Set all mapped meta.
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		// Set taxonomy terms.
		foreach ( $taxs as $taxonomy => $terms ) {
			if ( ! empty( $terms ) ) {
				wp_set_post_terms( $post_id, (array) $terms, $taxonomy, false );
			}
		}

		// Sync provider CPT and workplace locations.
		if ( class_exists( 'Apprco_Provider' ) ) {
			$provider      = Apprco_Provider::get_instance();
			$provider_id   = $provider->sync_from_vacancy( $data );
			$ukprn         = intval( $data['providerUkprn'] ?? $data['provider_ukprn'] ?? 0 );
			$all_addresses = $data['otherAddresses'] ?? $data['all_addresses'] ?? array();

			if ( $provider_id && $ukprn && is_array( $all_addresses ) ) {
				$provider->sync_workplaces( $ukprn, $provider_id, $all_addresses );
			}
		}

		// Enqueue async geocoding (Stage 3) for the vacancy postcode.
		$postcode = $data['address']['postcode'] ?? $data['postcode'] ?? '';
		if ( $postcode && class_exists( 'Apprco_Geocoder' ) ) {
			Apprco_Geocoder::get_instance()->enqueue_for_vacancy( $ref, $postcode );
		}
	}

	/**
	 * Get progress of a two-stage import for a given task.
	 *
	 * @param int $task_id Task ID.
	 * @return array { stage1_done: bool, stage2_total: int, stage2_done: int, stage2_pending: int }
	 */
	public function get_stage_progress( int $task_id ): array {
		$store  = Apprco_Vacancy_Store::get_instance();
		$counts = $store->count_by_stage( $task_id );

		$stage2_done    = $counts['stage2'];
		$stage2_total   = $counts['stage1'] + $counts['stage2'];
		$stage2_pending = $counts['stage1'];

		// Check Action Scheduler pending count for remaining stage 2 jobs.
		$as_pending = 0;
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$pending_actions = as_get_scheduled_actions(
				array(
					'hook'   => Apprco_Task_Scheduler::HOOK_STAGE2,
					'args'   => array( 'task_id' => $task_id ),
					'status' => 'pending',
					'per_page' => -1,
				)
			);
			$as_pending = is_array( $pending_actions ) ? count( $pending_actions ) : 0;
		}

		return array(
			'stage1_done'    => $stage2_total > 0,
			'stage2_total'   => $stage2_total,
			'stage2_done'    => $stage2_done,
			'stage2_pending' => max( $stage2_pending, $as_pending ),
		);
	}

	/**
	 * Run an import task (legacy compatibility wrapper — routes to stage1).
	 *
	 * @param int           $task_id     Task ID.
	 * @param callable|null $on_progress Progress callback.
	 * @return array
	 */
	public function run_import( int $task_id, ?callable $on_progress = null ): array {
		return $this->run_stage1( $task_id );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Decode task JSON fields.
	 *
	 * @param array $row Raw DB row.
	 * @return array
	 */
	private function decode_task( array $row ): array {
		$row['api_headers']    = json_decode( isset( $row['api_headers'] ) ? $row['api_headers'] : '[]', true );
		$row['api_params']     = json_decode( isset( $row['api_params'] ) ? $row['api_params'] : '[]', true );
		$row['field_mappings'] = json_decode( isset( $row['field_mappings'] ) ? $row['field_mappings'] : '[]', true );
		return $row;
	}

	/**
	 * Encode task JSON fields.
	 *
	 * @param array $data Task data.
	 * @return array
	 */
	private function encode_task( array $data ): array {
		if ( isset( $data['api_headers'] ) && is_array( $data['api_headers'] ) ) {
			$data['api_headers'] = wp_json_encode( $data['api_headers'] );
		}
		if ( isset( $data['api_params'] ) && is_array( $data['api_params'] ) ) {
			$data['api_params'] = wp_json_encode( $data['api_params'] );
		}
		if ( isset( $data['field_mappings'] ) && is_array( $data['field_mappings'] ) ) {
			$data['field_mappings'] = wp_json_encode( $data['field_mappings'] );
		}
		return $data;
	}

	/**
	 * Update task stats after a run.
	 *
	 * @param int $id Task ID.
	 * @return void
	 */
	private function update_stats( int $id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET last_run_at = %s, total_runs = total_runs + 1 WHERE id = %d', $this->table, current_time( 'mysql' ), $id ) );
	}
}
