<?php
/**
 * Executes import jobs and persists run records.
 *
 * @package ApprenticeshipConnector\Import
 */

namespace ApprenticeshipConnector\Import;

use ApprenticeshipConnector\API\DisplayAdvertAPI;
use ApprenticeshipConnector\Core\Database;

class ImportRunner {

	/**
	 * Trigger an import job by id, returns the run_id.
	 */
	public function trigger( int $job_id ): string {
		$job = ImportJob::find( $job_id );

		if ( ! $job ) {
			throw new \InvalidArgumentException( "Import job #{$job_id} not found." );
		}

		$run_id = wp_generate_uuid4();
		$this->createRunRecord( $run_id, $job_id );

		$this->execute( $job, $run_id );

		return $run_id;
	}

	/**
	 * Called by WP-Cron action `appcon_run_scheduled_import`.
	 */
	public function run_scheduled(): void {
		$jobs = ImportJob::find_active();

		foreach ( $jobs as $job ) {
			if ( $job->schedule_enabled ) {
				$run_id = wp_generate_uuid4();
				$this->createRunRecord( $run_id, $job->id );
				$this->execute( $job, $run_id );
			}
		}
	}

	// ── Internals ──────────────────────────────────────────────────────────

	private function execute( ImportJob $job, string $run_id ): void {
		global $wpdb;

		$table = Database::get_runs_table();

		// Mark as running.
		$wpdb->update( $table, [
			'status'     => 'running',
			'started_at' => current_time( 'mysql' ),
		], [ 'run_id' => $run_id ] );

		$api    = DisplayAdvertAPI::from_job( $job );
		$logger = new Logger( $run_id );
		$mapper = new FieldMapper( $job->field_mappings );
		$importer = new TwoStageImporter( $api, $mapper, $logger );

		$progress_cb = function ( array $status ) use ( $run_id, $wpdb, $table ) {
			if ( $status['stage'] === 2 ) {
				$wpdb->update( $table, [
					'current_stage' => 2,
					'current_item'  => $status['current'],
					'total_items'   => $status['total'],
					'progress_pct'  => $status['progress_pct'],
				], [ 'run_id' => $run_id ] );
			}
		};

		$results = $importer->run( $job, $progress_cb );

		// Finalise run record.
		$wpdb->update( $table, [
			'status'         => $results['status'],
			'completed_at'   => current_time( 'mysql' ),
			'duration'       => $results['duration'],
			'stage1_pages'   => $results['stage1']['pages'],
			'stage1_fetched' => $results['stage1']['fetched'],
			'stage1_errors'  => $results['stage1']['errors'],
			'stage2_total'   => $results['stage2']['total'],
			'stage2_fetched' => $results['stage2']['fetched'],
			'stage2_created' => $results['stage2']['created'],
			'stage2_updated' => $results['stage2']['updated'],
			'stage2_errors'  => $results['stage2']['errors'],
			'stage2_skipped' => $results['stage2']['skipped'],
			'progress_pct'   => 100.00,
			'error_message'  => $results['error'] ?? null,
		], [ 'run_id' => $run_id ] );

		// Update job stats.
		ImportJob::save( [
			'id'                     => $job->id,
			'last_run_at'            => current_time( 'mysql' ),
			'last_run_status'        => $results['status'],
			'last_run_stage1_fetched' => $results['stage1']['fetched'],
			'last_run_stage2_fetched' => $results['stage2']['fetched'],
			'last_run_created'       => $results['stage2']['created'],
			'last_run_updated'       => $results['stage2']['updated'],
			'last_run_errors'        => $results['stage2']['errors'],
			'last_run_duration'      => $results['duration'],
		] );
	}

	private function createRunRecord( string $run_id, int $job_id ): void {
		global $wpdb;

		$wpdb->insert( Database::get_runs_table(), [
			'run_id'     => $run_id,
			'job_id'     => $job_id,
			'status'     => 'queued',
			'created_at' => current_time( 'mysql' ),
		] );
	}
}
