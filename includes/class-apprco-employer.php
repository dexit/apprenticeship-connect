<?php
/**
 * Employer Manager - Store and manage employer/company data
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Apprco_Employer
 *
 * Manages employer/company data storage and retrieval.
 * Stores all employer information from imported vacancies for future reuse.
 * Uses a custom database table for efficient querying.
 */
class Apprco_Employer {

    /**
     * Database table name (without prefix)
     *
     * @var string
     */
    public const TABLE_NAME = 'apprco_employers';

    /**
     * Singleton instance
     *
     * @var Apprco_Employer|null
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger
     */
    private $logger;

    /**
     * Database table name with prefix
     *
     * @var string
     */
    private $table_name;

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        global $wpdb;

        $this->logger     = Apprco_Import_Logger::get_instance();
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Get singleton instance
     *
     * @return Apprco_Employer
     */
    public static function get_instance(): Apprco_Employer {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Create employer database table
     *
     * Should be called on plugin activation.
     */
    public static function create_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            employer_id VARCHAR(100) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            name_normalized VARCHAR(255) NOT NULL,
            website VARCHAR(500) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            contact_email VARCHAR(255) DEFAULT NULL,
            contact_phone VARCHAR(50) DEFAULT NULL,
            contact_name VARCHAR(255) DEFAULT NULL,
            address_line1 VARCHAR(255) DEFAULT NULL,
            address_line2 VARCHAR(255) DEFAULT NULL,
            address_line3 VARCHAR(255) DEFAULT NULL,
            address_line4 VARCHAR(255) DEFAULT NULL,
            postcode VARCHAR(20) DEFAULT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
            is_disability_confident TINYINT(1) DEFAULT 0,
            vacancy_count INT(11) DEFAULT 0,
            last_vacancy_date DATETIME DEFAULT NULL,
            provider_id VARCHAR(50) DEFAULT NULL,
            raw_data LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employer_name_unique (name_normalized(191)),
            KEY employer_id_idx (employer_id),
            KEY postcode_idx (postcode),
            KEY provider_idx (provider_id),
            KEY vacancy_count_idx (vacancy_count),
            KEY last_vacancy_idx (last_vacancy_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        $logger = Apprco_Import_Logger::get_instance();
        $logger->info( 'Employers table created/updated', null, 'employer' );
    }

    /**
     * Save or update employer from vacancy data
     *
     * @param array  $vacancy_data Normalized vacancy data.
     * @param string $provider_id  Provider ID.
     * @return int|false Employer ID or false on failure.
     */
    public function save_from_vacancy( array $vacancy_data, string $provider_id = '' ) {
        $employer_name = $vacancy_data['employer_name'] ?? '';

        if ( empty( $employer_name ) ) {
            return false;
        }

        $normalized_name = $this->normalize_name( $employer_name );

        // Check if employer exists
        $existing = $this->get_by_name( $employer_name );

        $employer_data = array(
            'employer_id'            => $vacancy_data['employer_id'] ?? null,
            'name'                   => $employer_name,
            'name_normalized'        => $normalized_name,
            'website'                => $vacancy_data['employer_website'] ?? null,
            'description'            => $vacancy_data['employer_description'] ?? null,
            'contact_email'          => $vacancy_data['employer_contact_email'] ?? null,
            'contact_phone'          => $vacancy_data['employer_contact_phone'] ?? null,
            'contact_name'           => $vacancy_data['employer_contact_name'] ?? null,
            'is_disability_confident' => ! empty( $vacancy_data['is_disability_confident'] ) ? 1 : 0,
            'provider_id'            => $provider_id,
            'last_vacancy_date'      => current_time( 'mysql' ),
        );

        // Extract address from primary_address or top-level
        $address = $vacancy_data['primary_address'] ?? array();
        if ( ! empty( $address ) ) {
            $employer_data['address_line1'] = $address['address_line1'] ?? null;
            $employer_data['address_line2'] = $address['address_line2'] ?? null;
            $employer_data['address_line3'] = $address['address_line3'] ?? null;
            $employer_data['address_line4'] = $address['address_line4'] ?? null;
            $employer_data['postcode']      = $address['postcode'] ?? null;
            $employer_data['latitude']      = $address['latitude'] ?? null;
            $employer_data['longitude']     = $address['longitude'] ?? null;
        }

        if ( $existing ) {
            // Update existing - increment vacancy count
            $employer_data['vacancy_count'] = $existing['vacancy_count'] + 1;

            // Merge data - only update if new value is not empty
            foreach ( $employer_data as $key => $value ) {
                if ( empty( $value ) && ! empty( $existing[ $key ] ) ) {
                    $employer_data[ $key ] = $existing[ $key ];
                }
            }

            return $this->update( $existing['id'], $employer_data );
        } else {
            // Insert new employer
            $employer_data['vacancy_count'] = 1;
            return $this->insert( $employer_data );
        }
    }

    /**
     * Insert a new employer
     *
     * @param array $data Employer data.
     * @return int|false Inserted ID or false on failure.
     */
    public function insert( array $data ) {
        global $wpdb;

        // Ensure normalized name
        if ( empty( $data['name_normalized'] ) && ! empty( $data['name'] ) ) {
            $data['name_normalized'] = $this->normalize_name( $data['name'] );
        }

        // Serialize raw_data if array
        if ( isset( $data['raw_data'] ) && is_array( $data['raw_data'] ) ) {
            $data['raw_data'] = wp_json_encode( $data['raw_data'] );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            $this->get_format( $data )
        );

        if ( false === $result ) {
            $this->logger->error( sprintf( 'Failed to insert employer: %s', $wpdb->last_error ), null, 'employer' );
            return false;
        }

        $this->logger->debug( sprintf( 'Inserted employer: %s (ID: %d)', $data['name'], $wpdb->insert_id ), null, 'employer' );

        return $wpdb->insert_id;
    }

    /**
     * Update an employer
     *
     * @param int   $id   Employer ID.
     * @param array $data Employer data.
     * @return int|false Employer ID or false on failure.
     */
    public function update( int $id, array $data ) {
        global $wpdb;

        // Update normalized name if name changed
        if ( ! empty( $data['name'] ) && empty( $data['name_normalized'] ) ) {
            $data['name_normalized'] = $this->normalize_name( $data['name'] );
        }

        // Serialize raw_data if array
        if ( isset( $data['raw_data'] ) && is_array( $data['raw_data'] ) ) {
            $data['raw_data'] = wp_json_encode( $data['raw_data'] );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_name,
            $data,
            array( 'id' => $id ),
            $this->get_format( $data ),
            array( '%d' )
        );

        if ( false === $result ) {
            $this->logger->error( sprintf( 'Failed to update employer ID %d: %s', $id, $wpdb->last_error ), null, 'employer' );
            return false;
        }

        $this->logger->debug( sprintf( 'Updated employer ID: %d', $id ), null, 'employer' );

        return $id;
    }

    /**
     * Get employer by ID
     *
     * @param int $id Employer ID.
     * @return array|null Employer data or null.
     */
    public function get( int $id ): ?array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get employer by name
     *
     * @param string $name Employer name.
     * @return array|null Employer data or null.
     */
    public function get_by_name( string $name ): ?array {
        global $wpdb;

        $normalized = $this->normalize_name( $name );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE name_normalized = %s", $normalized ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get employer by external ID
     *
     * @param string $employer_id External employer ID.
     * @return array|null Employer data or null.
     */
    public function get_by_employer_id( string $employer_id ): ?array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE employer_id = %s", $employer_id ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Search employers
     *
     * @param array $args Search arguments.
     * @return array{employers: array, total: int, page: int, per_page: int}
     */
    public function search( array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'search'     => '',
            'postcode'   => '',
            'provider'   => '',
            'orderby'    => 'vacancy_count',
            'order'      => 'DESC',
            'page'       => 1,
            'per_page'   => 20,
            'min_vacancies' => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $values = array();

        if ( ! empty( $args['search'] ) ) {
            $where[]  = "(name LIKE %s OR description LIKE %s)";
            $search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[] = $search;
            $values[] = $search;
        }

        if ( ! empty( $args['postcode'] ) ) {
            $where[]  = "postcode LIKE %s";
            $values[] = $wpdb->esc_like( $args['postcode'] ) . '%';
        }

        if ( ! empty( $args['provider'] ) ) {
            $where[]  = "provider_id = %s";
            $values[] = $args['provider'];
        }

        if ( $args['min_vacancies'] > 0 ) {
            $where[]  = "vacancy_count >= %d";
            $values[] = $args['min_vacancies'];
        }

        $where_clause = implode( ' AND ', $where );

        // Validate orderby
        $allowed_orderby = array( 'name', 'vacancy_count', 'last_vacancy_date', 'created_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'vacancy_count';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if ( ! empty( $values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var( $count_sql );

        // Get paginated results
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $employers = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

        return array(
            'employers' => $employers ?: array(),
            'total'     => $total,
            'page'      => $args['page'],
            'per_page'  => $args['per_page'],
        );
    }

    /**
     * Get employers by postcode area
     *
     * @param string $postcode_prefix Postcode prefix (e.g., 'B1', 'SW1').
     * @param int    $limit           Maximum results.
     * @return array Array of employers.
     */
    public function get_by_postcode_area( string $postcode_prefix, int $limit = 50 ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE postcode LIKE %s
                 ORDER BY vacancy_count DESC
                 LIMIT %d",
                $wpdb->esc_like( $postcode_prefix ) . '%',
                $limit
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Get top employers by vacancy count
     *
     * @param int    $limit      Maximum results.
     * @param string $provider   Optional provider filter.
     * @return array Array of employers.
     */
    public function get_top_employers( int $limit = 20, string $provider = '' ): array {
        global $wpdb;

        $where = '1=1';
        $values = array();

        if ( ! empty( $provider ) ) {
            $where = 'provider_id = %s';
            $values[] = $provider;
        }

        $values[] = $limit;

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY vacancy_count DESC LIMIT %d";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) ?: array();
    }

    /**
     * Get recently active employers
     *
     * @param int $days  Days to look back.
     * @param int $limit Maximum results.
     * @return array Array of employers.
     */
    public function get_recently_active( int $days = 30, int $limit = 50 ): array {
        global $wpdb;

        $date_limit = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE last_vacancy_date >= %s
                 ORDER BY last_vacancy_date DESC
                 LIMIT %d",
                $date_limit,
                $limit
            ),
            ARRAY_A
        ) ?: array();
    }

    /**
     * Get employer statistics
     *
     * @return array Statistics array.
     */
    public function get_stats(): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_employers,
                SUM(vacancy_count) as total_vacancies,
                AVG(vacancy_count) as avg_vacancies,
                MAX(vacancy_count) as max_vacancies,
                COUNT(CASE WHEN is_disability_confident = 1 THEN 1 END) as disability_confident_count,
                COUNT(CASE WHEN website IS NOT NULL AND website != '' THEN 1 END) as with_website,
                COUNT(CASE WHEN contact_email IS NOT NULL AND contact_email != '' THEN 1 END) as with_email,
                COUNT(DISTINCT provider_id) as provider_count
             FROM {$this->table_name}",
            ARRAY_A
        );

        return array(
            'total_employers'           => (int) ( $stats['total_employers'] ?? 0 ),
            'total_vacancies'           => (int) ( $stats['total_vacancies'] ?? 0 ),
            'avg_vacancies_per_employer' => round( (float) ( $stats['avg_vacancies'] ?? 0 ), 1 ),
            'max_vacancies_single'      => (int) ( $stats['max_vacancies'] ?? 0 ),
            'disability_confident'      => (int) ( $stats['disability_confident_count'] ?? 0 ),
            'with_website'              => (int) ( $stats['with_website'] ?? 0 ),
            'with_email'                => (int) ( $stats['with_email'] ?? 0 ),
            'provider_count'            => (int) ( $stats['provider_count'] ?? 0 ),
        );
    }

    /**
     * Delete employer by ID
     *
     * @param int $id Employer ID.
     * @return bool True on success.
     */
    public function delete( int $id ): bool {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $this->table_name,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( false !== $result ) {
            $this->logger->info( sprintf( 'Deleted employer ID: %d', $id ), null, 'employer' );
        }

        return false !== $result;
    }

    /**
     * Delete all employers for a provider
     *
     * @param string $provider_id Provider ID.
     * @return int Number of deleted rows.
     */
    public function delete_by_provider( string $provider_id ): int {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $this->table_name,
            array( 'provider_id' => $provider_id ),
            array( '%s' )
        );

        if ( false !== $result ) {
            $this->logger->info( sprintf( 'Deleted %d employers for provider: %s', $result, $provider_id ), null, 'employer' );
        }

        return $result ?: 0;
    }

    /**
     * Normalize employer name for matching
     *
     * @param string $name Employer name.
     * @return string Normalized name.
     */
    private function normalize_name( string $name ): string {
        // Convert to lowercase
        $name = strtolower( $name );

        // Remove common suffixes
        $suffixes = array( ' ltd', ' limited', ' plc', ' llp', ' inc', ' corp', ' co.', ' company' );
        foreach ( $suffixes as $suffix ) {
            if ( substr( $name, -strlen( $suffix ) ) === $suffix ) {
                $name = substr( $name, 0, -strlen( $suffix ) );
            }
        }

        // Remove special characters, keep alphanumeric and spaces
        $name = preg_replace( '/[^a-z0-9\s]/', '', $name );

        // Normalize whitespace
        $name = preg_replace( '/\s+/', ' ', $name );

        return trim( $name );
    }

    /**
     * Get format array for database operations
     *
     * @param array $data Data array.
     * @return array Format array.
     */
    private function get_format( array $data ): array {
        $formats = array();

        $int_fields = array( 'id', 'is_disability_confident', 'vacancy_count' );
        $float_fields = array( 'latitude', 'longitude' );

        foreach ( array_keys( $data ) as $key ) {
            if ( in_array( $key, $int_fields, true ) ) {
                $formats[] = '%d';
            } elseif ( in_array( $key, $float_fields, true ) ) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    /**
     * Get employers as select options
     *
     * @param int $limit Maximum options.
     * @return array Associative array id => name.
     */
    public function get_select_options( int $limit = 100 ): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name FROM {$this->table_name} ORDER BY name ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $options = array();
        foreach ( $rows as $row ) {
            $options[ $row['id'] ] = $row['name'];
        }

        return $options;
    }

    /**
     * Sync employer data to taxonomy term
     *
     * Creates or updates the apprco_employer taxonomy term for this employer.
     *
     * @param int $employer_id Employer table ID.
     * @return int|false Term ID or false on failure.
     */
    public function sync_to_taxonomy( int $employer_id ) {
        $employer = $this->get( $employer_id );

        if ( ! $employer ) {
            return false;
        }

        $term_name = $employer['name'];
        $term_slug = sanitize_title( $term_name );

        // Check if term exists
        $existing = term_exists( $term_slug, 'apprco_employer' );

        if ( $existing ) {
            $term_id = $existing['term_id'];
        } else {
            $result = wp_insert_term( $term_name, 'apprco_employer', array(
                'slug'        => $term_slug,
                'description' => $employer['description'] ?? '',
            ) );

            if ( is_wp_error( $result ) ) {
                $this->logger->error( sprintf( 'Failed to create term for employer: %s', $result->get_error_message() ), null, 'employer' );
                return false;
            }

            $term_id = $result['term_id'];
        }

        // Store employer ID in term meta
        update_term_meta( $term_id, '_apprco_employer_id', $employer_id );
        update_term_meta( $term_id, '_apprco_employer_website', $employer['website'] ?? '' );
        update_term_meta( $term_id, '_apprco_vacancy_count', $employer['vacancy_count'] );

        return $term_id;
    }
}
