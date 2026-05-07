<?php
/**
 * Import Task Repository - Abstracted Data Layer
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Import_Tasks
 *
 * Handles database operations for import tasks.
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
	 * Run an import task.
	 *
	 * @param int           $task_id     Task ID.
	 * @param callable|null $on_progress Progress callback.
	 * @return array
	 */
	public function run_import( int $task_id, ?callable $on_progress = null ): array {
		$task = $this->get( $task_id );
		if ( ! $task ) {
			return array(
				'success' => false,
				'error'   => 'Task not found',
			);
		}

		/**
		 * Action before import task starts.
		 */
		do_action( 'apprco_before_import_task', $task );

		$logger    = Apprco_Import_Logger::get_instance();
		$import_id = $logger->start_import( 'manual', $task['provider_id'] );

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

		$created = 0;
		$updated = 0;
		$errors  = 0;
		$refs    = array();

		foreach ( $fetch_res['items'] as $index => $item ) {
			if ( $settings->get( 'import', 'deep_fetch', true ) ) {
				$uid = isset( $item[ $task['unique_id_field'] ] ) ? $item[ $task['unique_id_field'] ] : null;
				if ( $uid ) {
					$deep = $client->get( $task['api_endpoint'] . '/' . $uid );
					if ( $deep['success'] ) {
						$item = array_merge( $item, $deep['data'] );
					}
				}
			}

			/**
			 * Filter import item data.
			 */
			$item = apply_filters( 'apprco_import_item_data', $item, $task );
			$res  = $this->process_item( $task, $item );

			if ( $res['success'] ) {
				if ( 'created' === $res['action'] ) {
					++$created;
				} else {
					++$updated;
				}
				$refs[] = isset( $item[ $task['unique_id_field'] ] ) ? $item[ $task['unique_id_field'] ] : null;
			} else {
				++$errors;
			}

			if ( $on_progress ) {
				call_user_func(
					$on_progress,
					array(
						'phase'   => 'processing',
						'current' => $index + 1,
						'total'   => count( $fetch_res['items'] ),
					)
				);
			}
		}

		$deleted = 0;
		if ( $settings->get( 'import', 'delete_expired' ) ) {
			$deleted = $this->cleanup_expired_vacancies( $refs );
		}

		$this->update_stats( $task_id );
		$logger->end_import( $import_id, count( $fetch_res['items'] ), $created, $updated, $deleted, 0, $errors, 'completed' );

		/**
		 * Action after import task ends.
		 */
		do_action( 'apprco_after_import_task', $task_id, $import_id );

		return array(
			'success'   => true,
			'import_id' => $import_id,
			'fetched'   => count( $fetch_res['items'] ),
			'created'   => $created,
			'updated'   => $updated,
		);
	}

	/**
	 * Process a single import item.
	 *
	 * @param array $task Task data.
	 * @param array $item Item data.
	 * @return array
	 */
	private function process_item( array $task, array $item ): array {
		$uid = isset( $item[ $task['unique_id_field'] ] ) ? $item[ $task['unique_id_field'] ] : null;
		if ( ! $uid ) {
			return array(
				'success' => false,
				'error'   => 'Missing UID',
			);
		}

		$existing = new WP_Query(
			array(
				'post_type'      => 'apprco_vacancy',
				'meta_query'     => array(
					array(
						'key'   => '_apprco_vacancy_reference',
						'value' => $uid,
					),
				),
				'posts_per_page' => 1,
			)
		);
		$exists   = $existing->have_posts() ? $existing->posts[0] : null;

		$post_data = array(
			'post_type'    => 'apprco_vacancy',
			'post_status'  => $task['post_status'],
			'post_title'   => isset( $item['title'] ) ? $item['title'] : '',
			'post_content' => isset( $item['fullDescription'] ) ? $item['fullDescription'] : ( isset( $item['description'] ) ? $item['description'] : '' ),
		);

		if ( $exists ) {
			$post_data['ID'] = $exists->ID;
			$post_id         = wp_update_post( $post_data );
			$action          = 'updated';
		} else {
			$post_id = wp_insert_post( $post_data );
			$action  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			return array(
				'success' => false,
				'error'   => $post_id->get_error_message(),
			);
		}

		// Map meta.
		$mappings = array(
			'_apprco_vacancy_reference' => $task['unique_id_field'],
			'_apprco_employer_name'     => 'employerName',
			'_apprco_vacancy_url'       => 'vacancyUrl',
			'_apprco_postcode'          => 'addresses[0].postcode',
		);
		foreach ( $mappings as $meta => $key ) {
			$path = explode( '.', $key );
			$val  = $item;
			foreach ( $path as $pk ) {
				if ( preg_match( '/\[(\d+)\]/', $pk, $m ) ) {
					$pk  = str_replace( $m[0], '', $pk );
					$val = isset( $val[ $pk ][ $m[1] ] ) ? $val[ $pk ][ $m[1] ] : null;
				} else {
					$val = isset( $val[ $pk ] ) ? $val[ $pk ] : null;
				}
			}
			update_post_meta( $post_id, $meta, $val );
		}
		update_post_meta( $post_id, '_apprco_raw_data', $item );

		/**
		 * Action after item imported.
		 */
		do_action( 'apprco_item_imported', $post_id, $item, $action );

		return array(
			'success' => true,
			'action'  => $action,
			'post_id' => $post_id,
		);
	}

	/**
	 * Update task stats.
	 *
	 * @param int $id Task ID.
	 * @return void
	 */
	private function update_stats( int $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET last_run_at = %s, total_runs = total_runs + 1 WHERE id = %d', $this->table, current_time( 'mysql' ), $id ) );
	}

	/**
	 * Cleanup expired vacancies.
	 *
	 * @param array $refs Active references.
	 * @return int Number of deleted posts.
	 */
	private function cleanup_expired_vacancies( array $refs ): int {
		$refs = array_filter( array_map( 'strval', $refs ) );
		if ( empty( $refs ) ) {
			return 0;
		}

		$q       = new WP_Query(
			array(
				'post_type'      => 'apprco_vacancy',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$deleted = 0;
		foreach ( $q->posts as $pid ) {
			$r = get_post_meta( $pid, '_apprco_vacancy_reference', true );
			if ( ! in_array( (string) $r, $refs, true ) ) {
				wp_delete_post( $pid, true );
				++$deleted;
			}
		}
		return $deleted;
	}
}
