<?php
/**
 * Employer Manager - Store and manage employer/company data
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Employer {

	public const TABLE_NAME = 'apprco_employers';
	private static $instance = null;
	private $logger;
	private $table_name;

	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . self::TABLE_NAME;
		$this->logger     = Apprco_Import_Logger::get_instance();
	}

	public static function get_instance(): Apprco_Employer {
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
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            normalized_name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            website varchar(255) DEFAULT NULL,
            contact_name varchar(255) DEFAULT NULL,
            contact_email varchar(255) DEFAULT NULL,
            contact_phone varchar(255) DEFAULT NULL,
            address_line_1 varchar(255) DEFAULT NULL,
            address_line_2 varchar(255) DEFAULT NULL,
            address_line_3 varchar(255) DEFAULT NULL,
            town varchar(100) DEFAULT NULL,
            county varchar(100) DEFAULT NULL,
            postcode varchar(20) DEFAULT NULL,
            latitude decimal(10,8) DEFAULT NULL,
            longitude decimal(11,8) DEFAULT NULL,
            is_disability_confident tinyint(1) DEFAULT 0,
            provider_id varchar(100) DEFAULT NULL,
            vacancy_count int(11) DEFAULT 0,
            last_vacancy_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY name_normalized (normalized_name),
            KEY postcode (postcode)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function upsert_from_vacancy( array $data ): int {
		global $wpdb;
		$name = $data['employerName'] ?? '';
		if ( empty( $name ) ) {
			return 0;
		}

		$normalized = $this->normalize_name( $name );
		$existing   = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE normalized_name = %s", $normalized ) );

		$employer_data = array(
			'name'                    => $name,
			'normalized_name'         => $normalized,
			'description'             => $data['employerDescription'] ?? null,
			'website'                 => $data['employerWebsiteUrl'] ?? null,
			'is_disability_confident' => ! empty( $data['isDisabilityConfident'] ) ? 1 : 0,
			'last_vacancy_date'       => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$wpdb->update( $this->table_name, $employer_data, array( 'id' => $existing->id ) );
			$wpdb->query( $wpdb->prepare( "UPDATE {$this->table_name} SET vacancy_count = vacancy_count + 1 WHERE id = %d", $existing->id ) );
			return (int) $existing->id;
		} else {
			$wpdb->insert( $this->table_name, $employer_data );
			return (int) $wpdb->insert_id;
		}
	}

	private function normalize_name( string $name ): string {
		return trim( strtolower( preg_replace( '/[^a-z0-9]/i', '', $name ) ) );
	}
}
