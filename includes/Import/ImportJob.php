<?php
/**
 * Import job value object – hydrated from a DB row.
 *
 * @package ApprenticeshipConnector\Import
 */

namespace ApprenticeshipConnector\Import;

use ApprenticeshipConnector\Core\Database;

class ImportJob {

	public readonly int     $id;
	public readonly string  $name;
	public readonly string  $description;
	public readonly string  $status;
	public readonly string  $api_base_url;
	public readonly string  $api_subscription_key;
	public readonly bool    $stage1_enabled;
	public readonly int     $stage1_page_size;
	public readonly int     $stage1_max_pages;
	public readonly string  $stage1_sort;
	public readonly array   $stage1_filters;
	public readonly bool    $stage2_enabled;
	public readonly int     $stage2_delay_ms;
	public readonly int     $stage2_batch_size;
	public readonly array   $field_mappings;
	public readonly bool    $schedule_enabled;
	public readonly ?string $schedule_frequency;

	private function __construct( array $row ) {
		$this->id                   = (int)    $row['id'];
		$this->name                 = (string) $row['name'];
		$this->description          = (string) ( $row['description'] ?? '' );
		$this->status               = (string) $row['status'];
		$this->api_base_url         = (string) ( $row['api_base_url'] ?? '' );
		$this->api_subscription_key = (string) ( $row['api_subscription_key'] ?? '' );
		$this->stage1_enabled       = (bool)   ( $row['stage1_enabled'] ?? true );
		$this->stage1_page_size     = (int)    ( $row['stage1_page_size'] ?? 100 );
		$this->stage1_max_pages     = (int)    ( $row['stage1_max_pages'] ?? 100 );
		$this->stage1_sort          = (string) ( $row['stage1_sort'] ?? 'AgeDesc' );
		$this->stage1_filters       = isset( $row['stage1_filters'] ) ? (array) json_decode( $row['stage1_filters'], true ) : [];
		$this->stage2_enabled       = (bool)   ( $row['stage2_enabled'] ?? true );
		$this->stage2_delay_ms      = (int)    ( $row['stage2_delay_ms'] ?? 250 );
		$this->stage2_batch_size    = (int)    ( $row['stage2_batch_size'] ?? 10 );
		$this->field_mappings       = isset( $row['field_mappings'] ) ? (array) json_decode( $row['field_mappings'], true ) : [];
		$this->schedule_enabled     = (bool)   ( $row['schedule_enabled'] ?? false );
		$this->schedule_frequency   = $row['schedule_frequency'] ?? null;
	}

	/** Load from DB by primary key. */
	public static function find( int $id ): ?self {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Database::get_jobs_table() . ' WHERE id = %d LIMIT 1', $id ),
			ARRAY_A
		);

		return $row ? new self( $row ) : null;
	}

	/** Load all active jobs. */
	public static function find_active(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			'SELECT * FROM ' . Database::get_jobs_table() . " WHERE status = 'active'",
			ARRAY_A
		);

		return array_map( fn( $r ) => new self( $r ), $rows );
	}

	/** Persist a new job or update an existing one. Returns the row id. */
	public static function save( array $data ): int {
		global $wpdb;

		$table = Database::get_jobs_table();

		// Encode JSON columns.
		foreach ( [ 'stage1_filters', 'field_mappings' ] as $col ) {
			if ( isset( $data[ $col ] ) && is_array( $data[ $col ] ) ) {
				$data[ $col ] = wp_json_encode( $data[ $col ] );
			}
		}

		if ( isset( $data['id'] ) && $data['id'] ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$wpdb->update( $table, $data, [ 'id' => $id ] );
			return $id;
		}

		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	/** Delete a job by id. */
	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( Database::get_jobs_table(), [ 'id' => $id ], [ '%d' ] );
	}
}
