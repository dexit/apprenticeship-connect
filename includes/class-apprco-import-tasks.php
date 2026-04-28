<?php
/**
 * Import Task Repository - Abstracted Data Layer
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Import_Tasks {

	private const TABLE_NAME = 'apprco_import_tasks';
	public const STATUS_ACTIVE = 'active';
	public const STATUS_DRAFT = 'draft';
	public const STATUS_FAILED = 'failed';

	private static $instance = null;
	private $table;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . self::TABLE_NAME;
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

	public function get_all(): array {
		global $wpdb;
		$results = $wpdb->get_results( "SELECT * FROM {$this->table}", ARRAY_A );
		return array_map( array( $this, 'decode_task' ), $results ?: array() );
	}

	public function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $this->decode_task( $row ) : null;
	}

	public function create( array $data ): int {
		global $wpdb;
		$wpdb->insert( $this->table, $this->encode_task( $data ) );
		return (int) $wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		global $wpdb;
		return false !== $wpdb->update( $this->table, $this->encode_task( $data ), array( 'id' => $id ) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table, array( 'id' => $id ) );
	}

	private function decode_task( array $row ): array {
		$row['api_headers']    = json_decode( $row['api_headers'] ?? '[]', true ) ?: array();
		$row['api_params']     = json_decode( $row['api_params'] ?? '[]', true ) ?: array();
		$row['field_mappings'] = json_decode( $row['field_mappings'] ?? '[]', true ) ?: array();
		return $row;
	}

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

    public function run_import( int $task_id, ?callable $on_progress = null ): array {
        $task = $this->get( $task_id );
        if ( ! $task ) return array( 'success' => false, 'error' => 'Task not found' );

        do_action( 'apprco_before_import_task', $task );

        $logger = Apprco_Import_Logger::get_instance();
        $import_id = $logger->start_import( 'manual', $task['provider_id'] );

        $settings = Apprco_Settings_Manager::get_instance();
        $client = new Apprco_API_Client( $task['api_base_url'] );
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

        $created = 0; $updated = 0; $errors = 0; $refs = array();
        foreach ( $fetch_res['items'] as $index => $item ) {
            if ( $settings->get('import', 'deep_fetch', true) ) {
                $uid = $item[ $task['unique_id_field'] ] ?? null;
                if ( $uid ) {
                    $deep = $client->get( $task['api_endpoint'] . '/' . $uid );
                    if ( $deep['success'] ) $item = array_merge( $item, $deep['data'] );
                }
            }

            $item = apply_filters( 'apprco_import_item_data', $item, $task );
            $res = $this->process_item( $task, $item, $import_id );

            if ( $res['success'] ) {
                'created' === $res['action'] ? $created++ : $updated++;
                $refs[] = $item[ $task['unique_id_field'] ] ?? null;
            } else {
                $errors++;
            }

            if ( $on_progress ) call_user_func( $on_progress, array( 'phase' => 'processing', 'current' => $index + 1, 'total' => count($fetch_res['items']) ) );
        }

        $deleted = 0;
        if ( $settings->get( 'import', 'delete_expired' ) ) $deleted = $this->cleanup_expired_vacancies( $refs, $import_id );

        $this->update_stats( $task_id );
        $logger->end_import( $import_id, count($fetch_res['items']), $created, $updated, $deleted, 0, $errors, 'completed' );

        do_action( 'apprco_after_import_task', $task_id, $import_id );

        return array( 'success' => true, 'import_id' => $import_id, 'fetched' => count($fetch_res['items']), 'created' => $created, 'updated' => $updated );
    }

    private function process_item( array $task, array $item, string $import_id ): array {
        $uid = $item[ $task['unique_id_field'] ] ?? null;
        if ( ! $uid ) return array( 'success' => false, 'error' => 'Missing UID' );

        $existing = new WP_Query( array( 'post_type' => 'apprco_vacancy', 'meta_query' => array( array( 'key' => '_apprco_vacancy_reference', 'value' => $uid ) ), 'posts_per_page' => 1 ) );
        $exists = $existing->have_posts() ? $existing->posts[0] : null;

        $post_data = array(
            'post_type' => 'apprco_vacancy',
            'post_status' => $task['post_status'],
            'post_title' => $item['title'] ?? '',
            'post_content' => $item['fullDescription'] ?? $item['description'] ?? '',
        );

        if ( $exists ) {
            $post_data['ID'] = $exists->ID;
            $post_id = wp_update_post( $post_data );
            $action = 'updated';
        } else {
            $post_id = wp_insert_post( $post_data );
            $action = 'created';
        }

        if ( is_wp_error( $post_id ) ) return array( 'success' => false, 'error' => $post_id->get_error_message() );

        // Map meta
        $mappings = array(
            '_apprco_vacancy_reference' => $task['unique_id_field'],
            '_apprco_employer_name' => 'employerName',
            '_apprco_vacancy_url' => 'vacancyUrl',
            '_apprco_postcode' => 'addresses[0].postcode'
        );
        foreach ( $mappings as $meta => $key ) {
            $path = explode('.', $key);
            $val = $item;
            foreach($path as $pk) {
                if (preg_match('/\[(\d+)\]/', $pk, $m)) { $pk = str_replace($m[0], '', $pk); $val = $val[$pk][$m[1]] ?? null; }
                else { $val = $val[$pk] ?? null; }
            }
            update_post_meta( $post_id, $meta, $val );
        }
        update_post_meta( $post_id, '_apprco_raw_data', $item );

        do_action( 'apprco_item_imported', $post_id, $item, $action );

        return array( 'success' => true, 'action' => $action, 'post_id' => $post_id );
    }

    private function update_stats( int $id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "UPDATE {$this->table} SET last_run_at = %s, total_runs = total_runs + 1 WHERE id = %d", current_time('mysql'), $id ) );
    }

    private function cleanup_expired_vacancies( array $refs, string $import_id ): int {
        $refs = array_filter( array_map( 'strval', $refs ) );
        if ( empty($refs) ) return 0;

        $q = new WP_Query( array( 'post_type' => 'apprco_vacancy', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $deleted = 0;
        foreach ( $q->posts as $pid ) {
            $r = get_post_meta( $pid, '_apprco_vacancy_reference', true );
            if ( ! in_array( (string)$r, $refs, true ) ) {
                wp_delete_post( $pid, true );
                $deleted++;
            }
        }
        return $deleted;
    }

    public static function get_default_field_mappings(): array {
        return array(
            array( 'source' => 'vacancyReference', 'target' => '_apprco_vacancy_reference', 'type' => 'meta' ),
            array( 'source' => 'title', 'target' => 'post_title', 'type' => 'core' ),
            array( 'source' => 'description', 'target' => 'post_content', 'type' => 'core' ),
            array( 'source' => 'employerName', 'target' => '_apprco_employer_name', 'type' => 'meta' ),
            array( 'source' => 'vacancyUrl', 'target' => '_apprco_vacancy_url', 'type' => 'meta' ),
        );
    }
}
