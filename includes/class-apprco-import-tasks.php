<?php
/**
 * Import Tasks Manager
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Import_Tasks {

	public const TABLE_NAME = 'apprco_import_tasks';
	public const STATUS_ACTIVE   = 'active';
	public const STATUS_INACTIVE = 'inactive';
	public const STATUS_DRAFT    = 'draft';

	private static $instance = null;
	private $table_name;
	private $logger;

	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . self::TABLE_NAME;
		$this->logger     = Apprco_Import_Logger::get_instance();
	}

	public static function get_instance(): Apprco_Import_Tasks {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

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

	public function create( array $data ): int {
		global $wpdb;
		$wpdb->insert( $this->table_name, $this->prepare_data( $data ) );
		return (int) $wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		global $wpdb;
		return false !== $wpdb->update( $this->table_name, $this->prepare_data( $data ), array( 'id' => $id ) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table_name, array( 'id' => $id ) );
	}

	private function prepare_data( array $data ): array {
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

	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ), ARRAY_A );
		if ( $row ) {
			$row['api_headers']    = json_decode( $row['api_headers'] ?? '[]', true ) ?: array();
			$row['api_params']     = json_decode( $row['api_params'] ?? '[]', true ) ?: array();
			$row['field_mappings'] = json_decode( $row['field_mappings'] ?? '[]', true ) ?: array();
		}
		return $row;
	}

	public function get_all(): array {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$this->table_name}", ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['api_headers']    = json_decode( $row['api_headers'] ?? '[]', true ) ?: array();
			$row['api_params']     = json_decode( $row['api_params'] ?? '[]', true ) ?: array();
			$row['field_mappings'] = json_decode( $row['field_mappings'] ?? '[]', true ) ?: array();
		}
		return $rows;
	}

	public function run_import( int $task_id, ?callable $on_progress = null ): array {
		$task = $this->get( $task_id );
		if ( ! $task ) {
			return array( 'success' => false, 'error' => 'Task not found.' );
		}

		$import_id = $this->logger->start_import( 'manual', $task['provider_id'] );
		$this->logger->info( "Starting task: {$task['name']}", $import_id, 'core' );

		$settings = Apprco_Settings_Manager::get_instance();
		$client   = new Apprco_API_Client( $task['api_base_url'] );
		$client->set_import_id( $import_id );

		$client->set_default_headers( $task['api_headers'] );

		$fetch_result = $client->fetch_all_pages(
			$task['api_endpoint'],
			$task['api_params'],
			$task['page_param'],
			$task['data_path'],
			$task['total_path'],
			$settings->get( 'import', 'max_pages', 0 )
		);

		if ( ! $fetch_result['success'] ) {
			$this->logger->error( "Fetch failed: {$fetch_result['error']}", $import_id, 'api' );
			$this->logger->end_import( $import_id, 0, 0, 0, 0, 0, 1, 'failed' );
			return array( 'success' => false, 'error' => $fetch_result['error'] );
		}

		$items          = $fetch_result['items'];
		$created        = 0;
		$updated        = 0;
		$errors         = 0;
		$api_references = array();

		foreach ( $items as $index => $item ) {
			if ( $settings->get( 'import', 'deep_fetch', true ) ) {
				$unique_id = $this->get_nested_value( $item, $task['unique_id_field'] );
				if ( $unique_id ) {
					$this->logger->debug( "Deep fetching vacancy: $unique_id", $import_id, 'api' );
					$deep_res = $client->get( $task['api_endpoint'] . '/' . $unique_id );
					if ( $deep_res['success'] ) {
						$item = array_merge( $item, $deep_res['data'] );
					}
				}
			}

			$res = $this->process_item( $task, $item, $import_id );
			if ( $res['success'] ) {
				if ( 'created' === $res['action'] ) {
					$created++;
				} else {
					$updated++;
				}
				$api_references[] = $this->get_nested_value( $item, $task['unique_id_field'] );
			} else {
				$errors++;
			}

			if ( $on_progress ) {
				call_user_func( $on_progress, array( 'phase' => 'processing', 'current' => $index + 1, 'total' => count( $items ) ) );
			}
		}

		$deleted = 0;
		if ( $settings->get( 'import', 'delete_expired' ) ) {
			$deleted = $this->cleanup_expired_vacancies( $api_references, $import_id );
		}

		$this->update_stats( $task_id );
		$this->logger->end_import( $import_id, count( $items ), $created, $updated, $deleted, 0, $errors, 'completed' );

		return array(
			'success'   => true,
			'import_id' => $import_id,
			'fetched'   => count( $items ),
			'created'   => $created,
			'updated'   => $updated,
			'deleted'   => $deleted,
			'errors'    => $errors,
		);
	}

	private function process_item( array $task, array $item, string $import_id ): array {
		$unique_id = $this->get_nested_value( $item, $task['unique_id_field'] );
		if ( ! $unique_id ) {
			return array( 'success' => false, 'error' => 'Missing ID' );
		}

		$existing = $this->find_existing_post( $unique_id, 'apprco_vacancy' );
		$mappings = $task['field_mappings'];

		$post_data = array(
			'post_type'    => 'apprco_vacancy',
			'post_status'  => $task['post_status'],
			'post_title'   => $this->get_nested_value( $item, $mappings['post_title'] ?? 'title' ),
			'post_content' => $this->get_nested_value( $item, $mappings['post_content'] ?? 'fullDescription' ),
			'post_excerpt' => $this->get_nested_value( $item, 'description' ),
		);
		$meta_data = array( '_apprco_vacancy_reference' => $unique_id );

		// Extended Mapping for V2 Deep Fields
		$v2_mappings = array(
			'_apprco_vacancy_url'             => 'vacancyUrl',
			'_apprco_closing_date'            => 'closingDate',
			'_apprco_posted_date'             => 'postedDate',
			'_apprco_start_date'              => 'startDate',
			'_apprco_number_of_positions'     => 'numberOfPositions',
			'_apprco_employer_name'           => 'employerName',
			'_apprco_employer_description'    => 'employerDescription',
			'_apprco_employer_website_url'    => 'employerWebsiteUrl',
			'_apprco_is_disability_confident' => 'isDisabilityConfident',
			'_apprco_address_line_1'          => 'addresses[0].addressLine1',
			'_apprco_address_line_2'          => 'addresses[0].addressLine2',
			'_apprco_address_line_3'          => 'addresses[0].addressLine3',
			'_apprco_postcode'                => 'addresses[0].postcode',
			'_apprco_wage_type'               => 'wage.wageType',
			'_apprco_wage_amount'             => 'wage.wageAmount',
			'_apprco_wage_unit'               => 'wage.wageUnit',
			'_apprco_hours_per_week'          => 'hoursPerWeek',
			'_apprco_expected_duration'       => 'expectedDuration',
			'_apprco_apprenticeship_level'    => 'apprenticeshipLevel',
			'_apprco_course_title'            => 'course.title',
			'_apprco_course_lars_code'        => 'course.larsCode',
			'_apprco_course_route'            => 'course.route',
			'_apprco_skills'                  => 'skills',
			'_apprco_qualifications'          => 'qualifications',
			'_apprco_outcome_description'     => 'outcomeDescription',
		);

		foreach ( $v2_mappings as $meta_key => $api_path ) {
			$meta_data[ $meta_key ] = $this->get_nested_value( $item, $api_path );
		}

		// Custom mappings override
		foreach ( $mappings as $target => $source ) {
			if ( 0 === strpos( $target, '_' ) ) {
				$meta_data[ $target ] = $this->get_nested_value( $item, $source );
			}
		}

		// Geocoding
		if ( empty( $meta_data['_apprco_latitude'] ) && ! empty( $meta_data['_apprco_postcode'] ) ) {
			$geo = Apprco_Geocoder::get_instance()->geocode( $meta_data['_apprco_postcode'] );
			if ( $geo ) {
				$meta_data['_apprco_latitude']  = $geo['lat'];
				$meta_data['_apprco_longitude'] = $geo['lng'];
			}
		}

		if ( $existing ) {
			$post_data['ID'] = $existing->ID;
			$post_id         = wp_update_post( $post_data, true );
			$action          = 'updated';
		} else {
			$post_id = wp_insert_post( $post_data, true );
			$action  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			return array( 'success' => false, 'error' => $post_id->get_error_message() );
		}

		foreach ( $meta_data as $k => $v ) {
			if ( is_array( $v ) ) {
				$v = wp_json_encode( $v );
			}
			update_post_meta( $post_id, $k, $v );
		}
		update_post_meta( $post_id, '_apprco_raw_data', $item );

		$this->sync_taxonomies( $post_id, $item );
		Apprco_Employer::get_instance()->upsert_from_vacancy( $item );

		return array( 'success' => true, 'action' => $action, 'post_id' => $post_id );
	}

	private function sync_taxonomies( int $post_id, array $item ): void {
		$level = $this->get_nested_value( $item, 'apprenticeshipLevel' );
		if ( $level ) {
			wp_set_object_terms( $post_id, $level, 'apprco_level' );
		}

		$route = $this->get_nested_value( $item, 'course.route' );
		if ( $route ) {
			wp_set_object_terms( $post_id, $route, 'apprco_route' );
		}

		$employer = $this->get_nested_value( $item, 'employerName' );
		if ( $employer ) {
			wp_set_object_terms( $post_id, $employer, 'apprco_employer' );
		}
	}

	private function update_stats( int $id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$this->table_name} SET last_run_at = %s, total_runs = total_runs + 1 WHERE id = %d", current_time( 'mysql' ), $id ) );
	}

	private function find_existing_post( $uid, $type ) {
		$q = new WP_Query( array(
			'post_type'              => $type,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'meta_query'             => array( array( 'key' => '_apprco_vacancy_reference', 'value' => $uid ) ),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		) );
		return $q->have_posts() ? $q->posts[0] : null;
	}

	private function get_nested_value( array $array, string $path ) {
		$path = preg_replace( '/\[(\d+)\]/', '.$1', $path );
		$keys = explode( '.', $path );
		$val  = $array;
		foreach ( $keys as $k ) {
			if ( is_array( $val ) && isset( $val[ $k ] ) ) {
				$val = $val[ $k ];
			} else {
				return null;
			}
		}
		return $val;
	}

	private function cleanup_expired_vacancies( array $refs, string $import_id ): int {
		if ( empty( $refs ) ) {
			return 0;
		}
		$deleted = 0;
		$q       = new WP_Query( array( 'post_type' => 'apprco_vacancy', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		foreach ( $q->posts as $post_id ) {
			$ref = get_post_meta( $post_id, '_apprco_vacancy_reference', true );
			if ( ! in_array( (string) $ref, array_map( 'strval', $refs ), true ) ) {
				wp_delete_post( $post_id, true );
				$deleted++;
			}
		}
		return $deleted;
	}
}
