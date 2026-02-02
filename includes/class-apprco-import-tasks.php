<?php
/**
 * Import Tasks Manager - CRUD for import job configurations
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Apprco_Import_Tasks
 *
 * Manages import task configurations stored in a custom database table.
 * Each task defines: API endpoint, field mappings, transforms, schedule.
 */
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
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            provider_id VARCHAR(100) NOT NULL DEFAULT 'uk-gov-apprenticeships',

            -- API Configuration
            api_base_url VARCHAR(500) NOT NULL,
            api_endpoint VARCHAR(255) NOT NULL DEFAULT '/vacancy',
            api_method VARCHAR(10) NOT NULL DEFAULT 'GET',
            api_headers LONGTEXT DEFAULT NULL,
            api_params LONGTEXT DEFAULT NULL,
            api_auth_type VARCHAR(50) DEFAULT 'header_key',
            api_auth_key VARCHAR(255) DEFAULT NULL,
            api_auth_value TEXT DEFAULT NULL,

            -- Response Parsing
            response_format VARCHAR(20) NOT NULL DEFAULT 'json',
            data_path VARCHAR(255) NOT NULL DEFAULT 'vacancies',
            total_path VARCHAR(255) DEFAULT 'total',
            pagination_type VARCHAR(50) DEFAULT 'page_number',
            page_param VARCHAR(50) DEFAULT 'PageNumber',
            page_size_param VARCHAR(50) DEFAULT 'PageSize',
            page_size INT DEFAULT 100,

            -- Field Mappings (JSON: {cpt_field: api_field})
            field_mappings LONGTEXT NOT NULL,
            unique_id_field VARCHAR(100) NOT NULL DEFAULT 'vacancyReference',

            -- ETL Transforms (PHP code)
            transforms_enabled TINYINT(1) DEFAULT 0,
            transforms_code LONGTEXT DEFAULT NULL,

            -- Target CPT
            target_post_type VARCHAR(50) NOT NULL DEFAULT 'apprco_vacancy',
            post_status VARCHAR(20) NOT NULL DEFAULT 'publish',

            -- Schedule
            schedule_enabled TINYINT(1) DEFAULT 0,
            schedule_frequency VARCHAR(50) DEFAULT 'daily',
            schedule_time TIME DEFAULT '03:00:00',

            -- Run Statistics
            last_run_at DATETIME DEFAULT NULL,
            last_run_status VARCHAR(50) DEFAULT NULL,
            last_run_fetched INT DEFAULT 0,
            last_run_created INT DEFAULT 0,
            last_run_updated INT DEFAULT 0,
            last_run_errors INT DEFAULT 0,
            total_runs INT DEFAULT 0,

            -- Timestamps
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,

            PRIMARY KEY (id),
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
            'post_content'     => 'description',
            'post_excerpt'     => 'shortDescription',

            // Meta fields
            '_apprco_vacancy_reference'      => 'vacancyReference',
            '_apprco_vacancy_url'            => 'vacancyUrl',
            '_apprco_employer_name'          => 'employerName',
            '_apprco_employer_website'       => 'employerWebsiteUrl',
            '_apprco_employer_description'   => 'employerDescription',
            '_apprco_provider_name'          => 'providerName',
            '_apprco_provider_ukprn'         => 'ukprn',
            '_apprco_course_title'           => 'courseTitle',
            '_apprco_course_level'           => 'courseLevel',
            '_apprco_apprenticeship_level'   => 'apprenticeshipLevel',
            '_apprco_wage_type'              => 'wageType',
            '_apprco_wage_amount'            => 'wageAmount',
            '_apprco_wage_unit'              => 'wageUnit',
            '_apprco_wage_text'              => 'wageText',
            '_apprco_working_week'           => 'workingWeek',
            '_apprco_hours_per_week'         => 'hoursPerWeek',
            '_apprco_expected_duration'      => 'expectedDuration',
            '_apprco_positions_available'    => 'numberOfPositions',
            '_apprco_posted_date'            => 'postedDate',
            '_apprco_closing_date'           => 'closingDate',
            '_apprco_start_date'             => 'startDate',
            '_apprco_address_line1'          => 'addresses[0].addressLine1',
            '_apprco_address_line2'          => 'addresses[0].addressLine2',
            '_apprco_address_line3'          => 'addresses[0].addressLine3',
            '_apprco_postcode'               => 'addresses[0].postcode',
            '_apprco_latitude'               => 'addresses[0].latitude',
            '_apprco_longitude'              => 'addresses[0].longitude',
            '_apprco_skills'                 => 'skills',
            '_apprco_qualifications'         => 'qualifications',
            '_apprco_is_disability_confident' => 'isDisabilityConfident',
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

        if ( ! $result['success'] ) {
            return array(
                'success'      => false,
                'error'        => $result['error'] ?? 'Request failed',
                'status_code'  => $result['status_code'] ?? 0,
                'raw_response' => isset( $result['data'] ) ? wp_json_encode( $result['data'] ) : '',
            );
        }

        $data = $result['data'];

        // Extract data array using data_path
        $items = $this->get_nested_value( $data, $task['data_path'] );
        $total = $this->get_nested_value( $data, $task['total_path'] ) ?? ( is_array( $items ) ? count( $items ) : 0 );

        if ( ! is_array( $items ) ) {
            return array(
                'success'       => false,
                'error'         => sprintf( 'Data path "%s" did not return an array', $task['data_path'] ),
                'response_keys' => array_keys( $data ),
                'raw_response'  => substr( wp_json_encode( $data ), 0, 2000 ),
            );
        }

        // Get sample for test mode
        $sample = array_slice( $items, 0, $limit );

        // Detect available fields from first item
        $available_fields = array();
        if ( ! empty( $sample[0] ) && is_array( $sample[0] ) ) {
            $available_fields = $this->flatten_array_keys( $sample[0] );
        }

        return array(
            'success'          => true,
            'total'            => $total,
            'fetched'          => count( $items ),
            'sample_count'     => count( $sample ),
            'sample'           => $sample,
            'available_fields' => $available_fields,
            'response_keys'    => array_keys( $data ),
        );
    }

    /**
     * Run a full import for a task
     *
     * @param int      $task_id     Task ID.
     * @param callable $on_progress Optional progress callback.
     * @return array Import result.
     */
    public function run_import( int $task_id, ?callable $on_progress = null ): array {
        $task = $this->get( $task_id );

        if ( ! $task ) {
            return array( 'success' => false, 'error' => 'Task not found.' );
        }

        if ( $task['status'] !== self::STATUS_ACTIVE ) {
            return array( 'success' => false, 'error' => 'Task is not active.' );
        }

        $import_id = $this->logger->start_import();
        $this->logger->info( sprintf( 'Starting import task: %s (ID: %d)', $task['name'], $task_id ), $import_id, 'core' );

        $client = new Apprco_API_Client( $task['api_base_url'], array(), $this->logger );
        $client->set_import_id( $import_id );

        // Build headers
        $headers = $task['api_headers'] ?? array();
        if ( $task['api_auth_type'] === 'header_key' && ! empty( $task['api_auth_key'] ) && ! empty( $task['api_auth_value'] ) ) {
            $headers[ $task['api_auth_key'] ] = $task['api_auth_value'];
        }
        $client->set_default_headers( $headers );

        $max_pages = 100;
        $params = $task['api_params'] ?? array();

        $fetch_result = $client->fetch_all_pages(
            $task['api_endpoint'],
            $params,
            $task['page_param'],
            $task['data_path'],
            $task['total_path'],
            $max_pages,
            function( $progress ) use ( $on_progress ) {
                if ( is_callable( $on_progress ) ) {
                    call_user_func( $on_progress, array(
                        'phase'   => 'fetching',
                        'page'    => $progress['page'],
                        'fetched' => $progress['total_so_far'],
                        'total'   => $progress['total_pages'],
                    ) );
                }
            }
        );

        if ( ! $fetch_result['success'] && empty( $fetch_result['items'] ) ) {
            $this->logger->error( sprintf( 'Import failed: %s', $fetch_result['error'] ?? 'Unknown error' ), $import_id, 'api' );
            return array( 'success' => false, 'error' => $fetch_result['error'] ?? 'Fetch failed' );
        }

        $all_items = $fetch_result['items'];

        // Process items
        $created = 0;
        $updated = 0;
        $errors  = 0;

        foreach ( $all_items as $index => $item ) {
            $result = $this->process_item( $task, $item, $import_id );

            if ( $result['success'] ) {
                if ( $result['action'] === 'created' ) {
                    $created++;
                } else {
                    $updated++;
                }
            } else {
                $errors++;
            }

            if ( is_callable( $on_progress ) && ( $index + 1 ) % 10 === 0 ) {
                call_user_func( $on_progress, array(
                    'phase'     => 'processing',
                    'current'   => $index + 1,
                    'total'     => count( $all_items ),
                    'created'   => $created,
                    'updated'   => $updated,
                    'errors'    => $errors,
                ) );
            }
        }

        // Update task stats
        $this->update( $task_id, array(
            'last_run_at'      => current_time( 'mysql' ),
            'last_run_status'  => $errors > 0 ? 'completed_with_errors' : 'success',
            'last_run_fetched' => count( $all_items ),
            'last_run_created' => $created,
            'last_run_updated' => $updated,
            'last_run_errors'  => $errors,
            'total_runs'       => $task['total_runs'] + 1,
        ) );

        $this->logger->end_import( $import_id, count( $all_items ) );

        return array(
            'success'   => true,
            'fetched'   => count( $all_items ),
            'created'   => $created,
            'updated'   => $updated,
            'errors'    => $errors,
            'import_id' => $import_id,
        );
    }


    /**
     * Process a single item (create/update post)
     *
     * @param array  $task      Task config.
     * @param array  $item      Item data.
     * @param string $import_id Import ID.
     * @return array Result.
     */
    private function process_item( array $task, array $item, string $import_id ): array {
        // Apply transforms if enabled
        if ( $task['transforms_enabled'] && ! empty( $task['transforms_code'] ) ) {
            $item = $this->apply_transforms( $item, $task['transforms_code'] );
        }

        // Get unique ID
        $unique_id = $this->get_nested_value( $item, $task['unique_id_field'] );

        if ( empty( $unique_id ) ) {
            return array( 'success' => false, 'error' => 'Missing unique ID' );
        }

        // Check if exists
        $existing = $this->find_existing_post( $unique_id, $task['target_post_type'] );

        // Map fields
        $post_data = array(
            'post_type'   => $task['target_post_type'],
            'post_status' => $task['post_status'],
        );

        $meta_data = array();

        foreach ( $task['field_mappings'] as $target => $source ) {
            $value = $this->get_nested_value( $item, $source );

            if ( strpos( $target, '_apprco_' ) === 0 || strpos( $target, '_' ) === 0 ) {
                // Meta field
                $meta_data[ $target ] = $value;
            } else {
                // Post field
                $post_data[ $target ] = $value;
            }
        }

        // Store raw data
        $meta_data['_apprco_raw_data']    = $item;
        $meta_data['_apprco_imported_at'] = current_time( 'mysql' );
        $meta_data['_apprco_task_id']     = $task['id'];

        if ( $existing ) {
            $post_data['ID'] = $existing->ID;
            $post_id = wp_update_post( $post_data, true );
            $action  = 'updated';
        } else {
            $post_id = wp_insert_post( $post_data, true );
            $action  = 'created';
        }

        if ( is_wp_error( $post_id ) ) {
            $this->logger->error( sprintf( 'Failed to %s post for %s: %s', $action, $unique_id, $post_id->get_error_message() ), $import_id, 'core' );
            return array( 'success' => false, 'error' => $post_id->get_error_message() );
        }

        // Save meta
        foreach ( $meta_data as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        return array(
            'success' => true,
            'action'  => $action,
            'post_id' => $post_id,
        );
    }

    /**
     * Apply custom PHP transforms
     *
     * @param array  $item Item data.
     * @param string $code Transform code.
     * @return array Transformed item.
     */
    private function apply_transforms( array $item, string $code ): array {
        // Sandbox the transform code
        $transform_func = function( $item ) use ( $code ) {
            // $item is available to the code
            eval( $code );
            return $item;
        };

        try {
            return $transform_func( $item );
        } catch ( \Throwable $e ) {
            $this->logger->error( 'Transform error: ' . $e->getMessage(), null, 'core' );
            return $item;
        }
    }

    /**
     * Find existing post by unique ID
     *
     * @param string $unique_id Unique identifier.
     * @param string $post_type Post type.
     * @return WP_Post|null
     */
    private function find_existing_post( string $unique_id, string $post_type ): ?WP_Post {
        $query = new WP_Query( array(
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'   => '_apprco_vacancy_reference',
                    'value' => $unique_id,
                ),
            ),
        ) );

        return $query->have_posts() ? $query->posts[0] : null;
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array  $array Array to search.
     * @param string $path  Dot-notation path (e.g., 'addresses[0].postcode').
     * @return mixed Value or null.
     */
    private function get_nested_value( array $array, string $path ) {
        // Handle array index notation: addresses[0].postcode
        $path = preg_replace( '/\[(\d+)\]/', '.$1', $path );
        $keys = explode( '.', $path );

        $value = $array;
        foreach ( $keys as $key ) {
            if ( is_array( $value ) && array_key_exists( $key, $value ) ) {
                $value = $value[ $key ];
            } elseif ( is_array( $value ) && is_numeric( $key ) && isset( $value[ (int) $key ] ) ) {
                $value = $value[ (int) $key ];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Flatten array keys for field detection
     *
     * @param array  $array  Array to flatten.
     * @param string $prefix Key prefix.
     * @return array Flattened keys.
     */
    private function flatten_array_keys( array $array, string $prefix = '' ): array {
        $keys = array();

        foreach ( $array as $key => $value ) {
            $full_key = $prefix ? "{$prefix}.{$key}" : $key;

            if ( is_array( $value ) && ! empty( $value ) ) {
                if ( isset( $value[0] ) ) {
                    // Indexed array
                    $keys[] = "{$full_key}[]";
                    if ( is_array( $value[0] ) ) {
                        $keys = array_merge( $keys, $this->flatten_array_keys( $value[0], "{$full_key}[0]" ) );
                    }
                } else {
                    // Associative array
                    $keys = array_merge( $keys, $this->flatten_array_keys( $value, $full_key ) );
                }
            } else {
                $keys[] = $full_key;
            }
        }

        return $keys;
    }

    /**
     * Get active scheduled tasks
     *
     * @return array Active tasks with schedule enabled.
     */
    public function get_scheduled_tasks(): array {
        return $this->get_all( array(
            'status' => self::STATUS_ACTIVE,
        ) );
    }
}
