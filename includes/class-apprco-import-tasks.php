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

    /**
     * Database table name (without prefix)
     */
    public const TABLE_NAME = 'apprco_import_tasks';

    /**
     * Task statuses
     */
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_DRAFT    = 'draft';

    /**
     * Singleton instance
     *
     * @var Apprco_Import_Tasks|null
     */
    private static $instance = null;

    /**
     * Database table name with prefix
     *
     * @var string
     */
    private $table_name;

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
        global $wpdb;
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;
        $this->logger     = Apprco_Import_Logger::get_instance();
    }

    /**
     * Get singleton instance
     *
     * @return Apprco_Import_Tasks
     */
    public static function get_instance(): Apprco_Import_Tasks {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create database table
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            provider_id varchar(100) NOT NULL DEFAULT 'uk-gov-apprenticeships',
            api_base_url varchar(500) NOT NULL,
            api_endpoint varchar(255) NOT NULL DEFAULT '/vacancy',
            api_method varchar(10) NOT NULL DEFAULT 'GET',
            api_headers longtext DEFAULT NULL,
            api_params longtext DEFAULT NULL,
            api_auth_type varchar(50) DEFAULT 'header_key',
            api_auth_key varchar(255) DEFAULT NULL,
            api_auth_value text DEFAULT NULL,
            response_format varchar(20) NOT NULL DEFAULT 'json',
            data_path varchar(255) NOT NULL DEFAULT 'vacancies',
            total_path varchar(255) DEFAULT 'total',
            pagination_type varchar(50) DEFAULT 'page_number',
            page_param varchar(50) DEFAULT 'PageNumber',
            page_size_param varchar(50) DEFAULT 'PageSize',
            page_size int DEFAULT 100,
            field_mappings longtext NOT NULL,
            unique_id_field varchar(100) NOT NULL DEFAULT 'vacancyReference',
            transforms_enabled tinyint(1) DEFAULT 0,
            transforms_code longtext DEFAULT NULL,
            target_post_type varchar(50) NOT NULL DEFAULT 'apprco_vacancy',
            post_status varchar(20) NOT NULL DEFAULT 'publish',
            schedule_enabled tinyint(1) DEFAULT 0,
            schedule_frequency varchar(50) DEFAULT 'daily',
            schedule_time time DEFAULT '03:00:00',
            last_run_at datetime DEFAULT NULL,
            last_run_status varchar(50) DEFAULT NULL,
            last_run_fetched int DEFAULT 0,
            last_run_created int DEFAULT 0,
            last_run_updated int DEFAULT 0,
            last_run_errors int DEFAULT 0,
            total_runs int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status_idx (status),
            KEY provider_idx (provider_id),
            KEY schedule_idx (schedule_enabled, schedule_frequency)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get default field mappings for UK Gov API
     *
     * @return array
     */
    public static function get_default_field_mappings(): array {
        return array(
            // Post fields
            'post_title'       => 'title',
            'post_content'     => 'fullDescription',
            'post_excerpt'     => 'description',

            // Meta fields
            '_apprco_vacancy_reference'           => 'vacancyReference',
            '_apprco_vacancy_url'                 => 'vacancyUrl',
            '_apprco_employer_name'               => 'employerName',
            '_apprco_employer_website_url'        => 'employerWebsiteUrl',
            '_apprco_employer_description'        => 'employerDescription',
            '_apprco_employer_contact_name'       => 'employerContactName',
            '_apprco_employer_contact_email'      => 'employerContactEmail',
            '_apprco_employer_contact_phone'      => 'employerContactPhone',
            '_apprco_provider_name'               => 'providerName',
            '_apprco_ukprn'                       => 'ukprn',
            '_apprco_course_title'                => 'course.title',
            '_apprco_course_level'                => 'course.level',
            '_apprco_course_route'                => 'course.route',
            '_apprco_course_lars_code'            => 'course.larsCode',
            '_apprco_apprenticeship_level'        => 'apprenticeshipLevel',
            '_apprco_wage_type'                   => 'wage.wageType',
            '_apprco_wage_amount'                 => 'wage.wageAmount',
            '_apprco_wage_unit'                   => 'wage.wageUnit',
            '_apprco_wage_additional_information' => 'wage.wageAdditionalInformation',
            '_apprco_working_week_description'    => 'wage.workingWeekDescription',
            '_apprco_hours_per_week'              => 'hoursPerWeek',
            '_apprco_expected_duration'           => 'expectedDuration',
            '_apprco_number_of_positions'         => 'numberOfPositions',
            '_apprco_posted_date'                 => 'postedDate',
            '_apprco_closing_date'                => 'closingDate',
            '_apprco_start_date'                  => 'startDate',
            '_apprco_address_line_1'              => 'addresses[0].addressLine1',
            '_apprco_address_line_2'              => 'addresses[0].addressLine2',
            '_apprco_address_line_3'              => 'addresses[0].addressLine3',
            '_apprco_postcode'                    => 'addresses[0].postcode',
            '_apprco_latitude'                    => 'addresses[0].latitude',
            '_apprco_longitude'                   => 'addresses[0].longitude',
            '_apprco_distance'                    => 'distance',
            '_apprco_skills'                      => 'skills',
            '_apprco_qualifications'              => 'qualifications',
            '_apprco_outcome_description'         => 'outcomeDescription',
            '_apprco_things_to_consider'          => 'thingsToConsider',
            '_apprco_is_disability_confident'     => 'isDisabilityConfident',
            '_apprco_is_national_vacancy'         => 'isNationalVacancy',
        );
    }

    /**
     * Create a new import task
     *
     * @param array $data Task data.
     * @return int|false Task ID or false on failure.
     */
    public function create( array $data ) {
        global $wpdb;

        $defaults = array(
            'name'             => '',
            'description'      => '',
            'status'           => self::STATUS_DRAFT,
            'provider_id'      => 'uk-gov-apprenticeships',
            'api_base_url'     => 'https://api.apprenticeships.education.gov.uk/vacancies',
            'api_endpoint'     => '/vacancy',
            'api_method'       => 'GET',
            'api_headers'      => wp_json_encode( array( 'X-Version' => '2' ) ),
            'api_params'       => wp_json_encode( array( 'Sort' => 'AgeDesc' ) ),
            'api_auth_type'    => 'header_key',
            'api_auth_key'     => 'Ocp-Apim-Subscription-Key',
            'api_auth_value'   => '',
            'response_format'  => 'json',
            'data_path'        => 'vacancies',
            'total_path'       => 'total',
            'pagination_type'  => 'page_number',
            'page_param'       => 'PageNumber',
            'page_size_param'  => 'PageSize',
            'page_size'        => 100,
            'field_mappings'   => wp_json_encode( self::get_default_field_mappings() ),
            'unique_id_field'  => 'vacancyReference',
            'transforms_enabled' => 0,
            'transforms_code'  => '',
            'target_post_type' => 'apprco_vacancy',
            'post_status'      => 'publish',
            'schedule_enabled' => 0,
            'schedule_frequency' => 'daily',
            'created_by'       => get_current_user_id(),
        );

        $data = wp_parse_args( $data, $defaults );

        // Encode arrays to JSON
        if ( is_array( $data['api_headers'] ) ) {
            $data['api_headers'] = wp_json_encode( $data['api_headers'] );
        }
        if ( is_array( $data['api_params'] ) ) {
            $data['api_params'] = wp_json_encode( $data['api_params'] );
        }
        if ( is_array( $data['field_mappings'] ) ) {
            $data['field_mappings'] = wp_json_encode( $data['field_mappings'] );
        }

        $result = $wpdb->insert( $this->table_name, $data );

        if ( false === $result ) {
            $this->logger->error( 'Failed to create import task: ' . $wpdb->last_error, null, 'core' );
            return false;
        }

        $task_id = $wpdb->insert_id;
        $this->logger->info( sprintf( 'Created import task: %s (ID: %d)', $data['name'], $task_id ), null, 'core' );

        // Trigger action for scheduler
        do_action( 'apprco_task_saved', $task_id );

        return $task_id;
    }

    /**
     * Update an import task
     *
     * @param int   $id   Task ID.
     * @param array $data Task data.
     * @return bool Success.
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        // Encode arrays to JSON
        if ( isset( $data['api_headers'] ) && is_array( $data['api_headers'] ) ) {
            $data['api_headers'] = wp_json_encode( $data['api_headers'] );
        }
        if ( isset( $data['api_params'] ) && is_array( $data['api_params'] ) ) {
            $data['api_params'] = wp_json_encode( $data['api_params'] );
        }
        if ( isset( $data['field_mappings'] ) && is_array( $data['field_mappings'] ) ) {
            $data['field_mappings'] = wp_json_encode( $data['field_mappings'] );
        }

        $result = $wpdb->update( $this->table_name, $data, array( 'id' => $id ) );

        if ( false === $result ) {
            $this->logger->error( sprintf( 'Failed to update import task %d: %s', $id, $wpdb->last_error ), null, 'core' );
            return false;
        }

        // Trigger action for scheduler
        do_action( 'apprco_task_saved', $id );

        return true;
    }

    /**
     * Get a task by ID
     *
     * @param int $id Task ID.
     * @return array|null Task data or null.
     */
    public function get( int $id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( $row ) {
            $row = $this->decode_json_fields( $row );
        }

        return $row;
    }

    /**
     * Get all tasks
     *
     * @param array $args Query arguments.
     * @return array Tasks.
     */
    public function get_all( array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'status'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'limit'    => 50,
            'offset'   => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $where = '1=1';
        $values = array();

        if ( ! empty( $args['status'] ) ) {
            $where .= ' AND status = %s';
            $values[] = $args['status'];
        }

        $orderby = in_array( $args['orderby'], array( 'name', 'status', 'created_at', 'last_run_at' ), true )
            ? $args['orderby'] : 'created_at';
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

        return array_map( array( $this, 'decode_json_fields' ), $rows ?: array() );
    }

    /**
     * Delete a task
     *
     * @param int $id Task ID.
     * @return bool Success.
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $result = $wpdb->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) );

        if ( $result ) {
            $this->logger->info( sprintf( 'Deleted import task ID: %d', $id ), null, 'core' );

            // Trigger action for scheduler
            do_action( 'apprco_task_deleted', $id );
        }

        return (bool) $result;
    }

    /**
     * Decode JSON fields in task data
     *
     * @param array $row Task row.
     * @return array Decoded task.
     */
    private function decode_json_fields( array $row ): array {
        $json_fields = array( 'api_headers', 'api_params', 'field_mappings' );

        foreach ( $json_fields as $field ) {
            if ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) ) {
                $decoded = json_decode( $row[ $field ], true );
                $row[ $field ] = is_array( $decoded ) ? $decoded : array();
            }
        }

        return $row;
    }

    /**
     * Test API connection for a task
     *
     * @param int $task_id Task ID.
     * @param int $limit   Number of records to fetch for test.
     * @return array Test result with sample data.
     */
    public function test_connection( int $task_id, int $limit = 10 ): array {
        $task = $this->get( $task_id );

        if ( ! $task ) {
            return array( 'success' => false, 'error' => 'Task not found.' );
        }

        return $this->execute_api_request( $task, $limit, true );
    }

    /**
     * Execute API request for a task
     *
     * @param array $task     Task configuration.
     * @param int   $limit    Max records.
     * @param bool  $test_mode Whether this is a test (don't import).
     * @return array Result with data or error.
     */
    public function execute_api_request( array $task, int $limit = 100, bool $test_mode = false ): array {
        $client = new Apprco_API_Client( $task['api_base_url'], array(), $this->logger );

        // Build headers
        $headers = $task['api_headers'] ?? array();
        if ( $task['api_auth_type'] === 'header_key' && ! empty( $task['api_auth_key'] ) && ! empty( $task['api_auth_value'] ) ) {
            $headers[ $task['api_auth_key'] ] = $task['api_auth_value'];
        }
        $client->set_default_headers( $headers );

        // Build params
        $params = $task['api_params'] ?? array();
        if ( $task['pagination_type'] === 'page_number' ) {
            $params[ $task['page_param'] ] = 1;
            $params[ $task['page_size_param'] ] = min( $limit, $task['page_size'] );
        }

        $result = $client->get( $task['api_endpoint'], $params );

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
