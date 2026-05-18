<?php
/**
 * Vacancy Store Class - Dedicated DB table for vacancies
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Vacancy_Store
 *
 * Manages the apprco_vacancies database table.
 */
class Apprco_Vacancy_Store {

	/**
	 * Table name without prefix.
	 */
	private const TABLE_NAME = 'apprco_vacancies';

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Vacancy_Store|null
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
	 * Create the vacancies table via dbDelta.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  vacancy_reference varchar(100) NOT NULL,
  task_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(500) NOT NULL DEFAULT '',
  employer_name varchar(255) NOT NULL DEFAULT '',
  employer_website varchar(500) DEFAULT '',
  description text DEFAULT NULL,
  full_description longtext DEFAULT NULL,
  outcome_description text DEFAULT NULL,
  postcode varchar(20) NOT NULL DEFAULT '',
  town varchar(100) DEFAULT '',
  county varchar(100) DEFAULT '',
  lat decimal(10,7) DEFAULT NULL,
  lng decimal(10,7) DEFAULT NULL,
  all_addresses longtext DEFAULT NULL,
  provider_ukprn bigint(20) unsigned DEFAULT NULL,
  provider_name varchar(255) DEFAULT '',
  apprenticeship_level varchar(100) DEFAULT '',
  route varchar(255) DEFAULT '',
  standard_code varchar(100) DEFAULT '',
  standard_title varchar(255) DEFAULT '',
  framework_lars_code varchar(100) DEFAULT '',
  posted_date datetime DEFAULT NULL,
  closing_date datetime DEFAULT NULL,
  wage_text varchar(255) DEFAULT '',
  wage_amount decimal(10,2) DEFAULT NULL,
  wage_unit varchar(50) DEFAULT '',
  hours_per_week decimal(5,2) DEFAULT NULL,
  working_week varchar(255) DEFAULT '',
  expected_duration varchar(100) DEFAULT '',
  number_of_positions int(11) unsigned DEFAULT 1,
  vacancy_url varchar(1000) DEFAULT '',
  qualifications longtext DEFAULT NULL,
  skills longtext DEFAULT NULL,
  import_stage tinyint(1) NOT NULL DEFAULT 1,
  raw_data longtext DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY vacancy_reference (vacancy_reference),
  KEY task_id (task_id),
  KEY closing_date (closing_date),
  KEY postcode (postcode),
  KEY route (route(100)),
  KEY apprenticeship_level (apprenticeship_level),
  KEY provider_ukprn (provider_ukprn)
) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert or update a vacancy record.
	 *
	 * @param array $data    Vacancy data array (API field names supported).
	 * @param int   $task_id Task ID.
	 * @return int Row ID on success, 0 on error.
	 */
	public function upsert( array $data, int $task_id ): int {
		global $wpdb;

		$now     = current_time( 'mysql' );
		$mapped  = $this->map_api_fields( $data );

		$mapped['task_id']    = $task_id;
		$mapped['updated_at'] = $now;

		if ( empty( $mapped['vacancy_reference'] ) ) {
			return 0;
		}

		// Check if row already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE vacancy_reference = %s',
				$this->table,
				$mapped['vacancy_reference']
			)
		);

		if ( $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->update(
				$this->table,
				$mapped,
				array( 'vacancy_reference' => $mapped['vacancy_reference'] )
			);
			return false !== $result ? (int) $existing_id : 0;
		}

		$mapped['created_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $this->table, $mapped );
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Get a vacancy by reference.
	 *
	 * @param string $ref Vacancy reference.
	 * @return array|null
	 */
	public function get_by_ref( string $ref ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE vacancy_reference = %s',
				$this->table,
				$ref
			),
			ARRAY_A
		);
		return $row ? $this->decode_row( $row ) : null;
	}

	/**
	 * Search vacancies with filters.
	 *
	 * @param array $args Search arguments.
	 * @return array { items: array, total: int, pages: int }
	 */
	public function search( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'keyword'        => '',
			'postcode'       => '',
			'distance_miles' => 10,
			'level'          => '',
			'route'          => '',
			'employer'       => '',
			'page'           => 1,
			'per_page'       => 20,
			'order_by'       => 'closing_date',
			'order'          => 'ASC',
			'search_lat'     => null,
			'search_lng'     => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses  = array();
		$prepare_values = array();

		// Always filter out expired vacancies.
		$where_clauses[] = "(closing_date IS NULL OR closing_date >= %s)";
		$prepare_values[] = current_time( 'mysql' );

		if ( ! empty( $args['keyword'] ) ) {
			$like             = '%' . $wpdb->esc_like( $args['keyword'] ) . '%';
			$where_clauses[]  = '(title LIKE %s OR employer_name LIKE %s OR description LIKE %s)';
			$prepare_values[] = $like;
			$prepare_values[] = $like;
			$prepare_values[] = $like;
		}

		if ( ! empty( $args['level'] ) ) {
			$where_clauses[]  = 'apprenticeship_level = %s';
			$prepare_values[] = $args['level'];
		}

		if ( ! empty( $args['route'] ) ) {
			$where_clauses[]  = 'route = %s';
			$prepare_values[] = $args['route'];
		}

		if ( ! empty( $args['employer'] ) ) {
			$like             = '%' . $wpdb->esc_like( $args['employer'] ) . '%';
			$where_clauses[]  = 'employer_name LIKE %s';
			$prepare_values[] = $like;
		}

		// Distance filter using Haversine formula.
		$distance_select = '';
		$having_clause   = '';
		if ( ! empty( $args['search_lat'] ) && ! empty( $args['search_lng'] ) ) {
			$lat             = (float) $args['search_lat'];
			$lng             = (float) $args['search_lng'];
			$distance_miles  = (float) $args['distance_miles'];
			$distance_select = $wpdb->prepare(
				', (6371 * acos(cos(radians(%f)) * cos(radians(lat)) * cos(radians(lng) - radians(%f)) + sin(radians(%f)) * sin(radians(lat)))) AS distance_km',
				$lat,
				$lng,
				$lat
			);
			// Haversine gives km; convert miles to km for comparison (1 mile = 1.60934 km).
			$distance_km   = $distance_miles * 1.60934;
			$having_clause = $wpdb->prepare( 'HAVING distance_km <= %f', $distance_km );
			// Filter to rows that have lat/lng.
			$where_clauses[] = 'lat IS NOT NULL AND lng IS NOT NULL';
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Build ORDER BY.
		$allowed_order_by = array( 'closing_date', 'posted_date', 'relevance', 'title' );
		$order_by         = in_array( $args['order_by'], $allowed_order_by, true ) ? $args['order_by'] : 'closing_date';
		if ( 'relevance' === $order_by ) {
			$order_by = 'posted_date';
		}
		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// Build the full query with optional HAVING.
		$page     = max( 1, (int) $args['page'] );
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( $page - 1 ) * $per_page;

		// Count query.
		if ( ! empty( $prepare_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count_sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM (SELECT id {$distance_select} FROM %i {$where_sql} {$having_clause}) AS sub",
				array_merge( array( $this->table ), $prepare_values )
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count_sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM (SELECT id {$distance_select} FROM %i {$having_clause}) AS sub",
				$this->table
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $count_sql );

		// Items query.
		if ( ! empty( $prepare_values ) ) {
			$items_sql = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * {$distance_select} FROM %i {$where_sql} {$having_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
				array_merge( array( $this->table ), $prepare_values, array( $per_page, $offset ) )
			);
		} else {
			$items_sql = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * {$distance_select} FROM %i {$having_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
				$this->table,
				$per_page,
				$offset
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $items_sql, ARRAY_A );

		$items = array_map( array( $this, 'decode_row' ), $rows ? $rows : array() );

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	/**
	 * Get available filter options (unique levels and routes).
	 *
	 * @return array { levels: array, routes: array }
	 */
	public function get_filters(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$levels = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT apprenticeship_level FROM %i WHERE apprenticeship_level != %s ORDER BY apprenticeship_level ASC',
				$this->table,
				''
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$routes = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT route FROM %i WHERE route != %s ORDER BY route ASC',
				$this->table,
				''
			)
		);

		return array(
			'levels' => $levels ? $levels : array(),
			'routes' => $routes ? $routes : array(),
		);
	}

	/**
	 * Mark a vacancy as stage 2 (fully deep-fetched).
	 *
	 * @param string $ref Vacancy reference.
	 * @return void
	 */
	public function mark_stage_2( string $ref ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$this->table,
			array(
				'import_stage' => 2,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'vacancy_reference' => $ref )
		);
	}

	/**
	 * Delete a vacancy by reference.
	 *
	 * @param string $ref Vacancy reference.
	 * @return void
	 */
	public function delete_by_ref( string $ref ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( $this->table, array( 'vacancy_reference' => $ref ) );
	}

	/**
	 * Get all vacancy references imported by a task.
	 *
	 * @param int $task_id Task ID.
	 * @return array Array of vacancy reference strings.
	 */
	public function get_refs_for_task( int $task_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$refs = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT vacancy_reference FROM %i WHERE task_id = %d',
				$this->table,
				$task_id
			)
		);
		return $refs ? $refs : array();
	}

	/**
	 * Count vacancies by import stage for a task.
	 *
	 * @param int $task_id Task ID.
	 * @return array { stage1: int, stage2: int }
	 */
	public function count_by_stage( int $task_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT import_stage, COUNT(*) as cnt FROM %i WHERE task_id = %d GROUP BY import_stage',
				$this->table,
				$task_id
			),
			ARRAY_A
		);

		$counts = array( 'stage1' => 0, 'stage2' => 0 );
		foreach ( $rows ? $rows : array() as $row ) {
			if ( '1' === (string) $row['import_stage'] ) {
				$counts['stage1'] = (int) $row['cnt'];
			} elseif ( '2' === (string) $row['import_stage'] ) {
				$counts['stage2'] = (int) $row['cnt'];
			}
		}
		return $counts;
	}

	/**
	 * Map API field names to database column names.
	 *
	 * @param array $data Raw API data.
	 * @return array Mapped DB data.
	 */
	private function map_api_fields( array $data ): array {
		$mapped = array();

		// Direct mappings from API camelCase to DB snake_case.
		$field_map = array(
			'vacancy_reference'   => array( 'vacancyReference', 'vacancy_reference' ),
			'title'               => array( 'title' ),
			'employer_name'       => array( 'employerName', 'employer_name' ),
			'employer_website'    => array( 'employerWebsite', 'employer_website' ),
			'description'         => array( 'description' ),
			'full_description'    => array( 'fullDescription', 'full_description' ),
			'outcome_description' => array( 'outcomeDescription', 'outcome_description' ),
			'postcode'            => array( 'postcode' ),
			'town'                => array( 'town' ),
			'county'              => array( 'county' ),
			'lat'                 => array( 'lat', 'latitude' ),
			'lng'                 => array( 'lng', 'lon', 'longitude' ),
			'provider_ukprn'      => array( 'providerUkprn', 'provider_ukprn' ),
			'provider_name'       => array( 'providerName', 'provider_name' ),
			'apprenticeship_level' => array( 'apprenticeshipLevel', 'apprenticeship_level' ),
			'route'               => array( 'route' ),
			'standard_code'       => array( 'standardCode', 'standard_code' ),
			'standard_title'      => array( 'standardTitle', 'standard_title' ),
			'framework_lars_code' => array( 'frameworkLarsCode', 'framework_lars_code' ),
			'wage_text'           => array( 'wageText', 'wage_text' ),
			'wage_amount'         => array( 'wageAmount', 'wage_amount' ),
			'wage_unit'           => array( 'wageUnit', 'wage_unit' ),
			'hours_per_week'      => array( 'hoursPerWeek', 'hours_per_week' ),
			'working_week'        => array( 'workingWeek', 'working_week' ),
			'expected_duration'   => array( 'expectedDuration', 'expected_duration' ),
			'number_of_positions' => array( 'numberOfPositions', 'number_of_positions' ),
			'vacancy_url'         => array( 'vacancyUrl', 'vacancy_url' ),
			'import_stage'        => array( 'import_stage' ),
		);

		foreach ( $field_map as $db_col => $api_keys ) {
			foreach ( $api_keys as $api_key ) {
				if ( array_key_exists( $api_key, $data ) ) {
					$mapped[ $db_col ] = $data[ $api_key ];
					break;
				}
			}
		}

		// Handle addresses array.
		if ( isset( $data['addresses'] ) && is_array( $data['addresses'] ) && ! empty( $data['addresses'] ) ) {
			$primary = $data['addresses'][0];
			if ( ! isset( $mapped['postcode'] ) && isset( $primary['postcode'] ) ) {
				$mapped['postcode'] = $primary['postcode'];
			}
			if ( ! isset( $mapped['lat'] ) && isset( $primary['lat'] ) ) {
				$mapped['lat'] = $primary['lat'];
			}
			if ( ! isset( $mapped['lng'] ) ) {
				if ( isset( $primary['lon'] ) ) {
					$mapped['lng'] = $primary['lon'];
				} elseif ( isset( $primary['lng'] ) ) {
					$mapped['lng'] = $primary['lng'];
				}
			}
			if ( ! isset( $mapped['town'] ) && isset( $primary['town'] ) ) {
				$mapped['town'] = $primary['town'];
			}
			$mapped['all_addresses'] = wp_json_encode( $data['addresses'] );
		}

		// Handle qualifications array.
		if ( isset( $data['qualifications'] ) ) {
			$mapped['qualifications'] = is_array( $data['qualifications'] )
				? wp_json_encode( $data['qualifications'] )
				: $data['qualifications'];
		}

		// Handle skills array.
		if ( isset( $data['skills'] ) ) {
			$mapped['skills'] = is_array( $data['skills'] )
				? wp_json_encode( $data['skills'] )
				: $data['skills'];
		}

		// Store raw data.
		$mapped['raw_data'] = wp_json_encode( $data );

		// Handle date parsing.
		foreach ( array( 'posted_date', 'closing_date' ) as $date_field ) {
			$api_keys = array(
				'posted_date'  => array( 'postedDate', 'posted_date' ),
				'closing_date' => array( 'closingDate', 'closing_date' ),
			);
			if ( isset( $mapped[ $date_field ] ) ) {
				$mapped[ $date_field ] = $this->parse_date( $mapped[ $date_field ] );
			} elseif ( isset( $api_keys[ $date_field ] ) ) {
				foreach ( $api_keys[ $date_field ] as $key ) {
					if ( isset( $data[ $key ] ) ) {
						$mapped[ $date_field ] = $this->parse_date( $data[ $key ] );
						break;
					}
				}
			}
		}

		return $mapped;
	}

	/**
	 * Parse a date value into MySQL datetime format.
	 *
	 * @param mixed $value Date value (string, timestamp, etc.).
	 * @return string|null MySQL datetime string or null.
	 */
	private function parse_date( $value ): ?string {
		if ( empty( $value ) ) {
			return null;
		}
		if ( is_numeric( $value ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $value );
		}
		$ts = strtotime( (string) $value );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}

	/**
	 * Decode a database row, unserializing JSON fields.
	 *
	 * @param array $row Raw DB row.
	 * @return array
	 */
	private function decode_row( array $row ): array {
		$json_fields = array( 'all_addresses', 'qualifications', 'skills' );
		foreach ( $json_fields as $field ) {
			if ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) ) {
				$decoded = json_decode( $row[ $field ], true );
				if ( null !== $decoded ) {
					$row[ $field ] = $decoded;
				}
			}
		}
		return $row;
	}
}
