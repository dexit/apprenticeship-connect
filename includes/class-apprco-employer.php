<?php
/**
 * Employer Data Manager Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Employer
 *
 * Manages employer-specific data and tables.
 */
class Apprco_Employer {

	/**
	 * Create database table for employers.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'apprco_employers';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			website_url varchar(500) DEFAULT NULL,
			logo_url varchar(500) DEFAULT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
