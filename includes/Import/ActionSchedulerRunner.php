<?php
/**
 * Action Scheduler–based import runner.
 *
 * ── Import flow ────────────────────────────────────────────────────────────
 *
 * 1. appcon_as_import_start   (async, single-fire)
 *    Creates the run record, then dispatches first Stage 1 page action.
 *
 * 2. appcon_as_stage1_page    (async, chained)
 *    Fetches one page of the vacancy list → inserts references into
 *    wp_appcon_vacancy_index.  Chains the next page until done, then
 *    dispatches the first Stage 2 batch action.
 *
 * 3. appcon_as_stage2_batch   (async, chained)
 *    Fetches one batch of PENDING references from the index, calls Stage 2
 *    endpoint for each, creates/updates posts, marks rows completed/failed.
 *    Chains itself until no PENDING rows remain, then marks run completed.
 *
 * 4. appcon_as_expire_vacancies  (scheduled recurring, daily)
 *    Finds published appcon_vacancy posts whose _appcon_closing_date has
 *    passed and transitions them to 'draft'.
 *
 * Rate-limit handling
 * ───────────────────
 * The API allows 150 requests per 5 minutes (30 req/min).
 * Each AS action processes one Stage-1 page (1 request) or one Stage-2 batch
 * (STAGE2_BATCH_SIZE requests at 250 ms spacing).
 * Default STAGE2_BATCH_SIZE = 20  → 20 × 250 ms ≈ 5 s per batch.
 * AS runs actions as fast as it can, but because each action is a real HTTP
 * request the natural throughput stays well under the rate cap.
 * A per-run mutex prevents concurrent batches for the same run_id.
 *
 * @package ApprenticeshipConnector\Import
 */

namespace ApprenticeshipConnector\Import;

use ApprenticeshipConnector\API\DisplayAdvertAPI;
use ApprenticeshipConnector\Core\Database;

class ActionSchedulerRunner {

	// Action hook names registered in Plugin.php
	public const HOOK_START         = 'appcon_as_import_start';
	public const HOOK_STAGE1_PAGE   = 'appcon_as_stage1_page';
	public const HOOK_STAGE2_BATCH  = 'appcon_as_stage2_batch';
	public const HOOK_EXPIRE        = 'appcon_as_expire_vacancies';

	// How many Stage-2 references to process per AS action.
	// Keeps us safely under the 30 req/min cap even if AS fires quickly.
	private const STAGE2_BATCH_SIZE = 20;

	// ── Public API ─────────────────────────────────────────────────────────

	// ── Action Scheduler entry-point wrappers ─────────────────────────────
	// AS passes each named arg as a positional array element, so we unpack.

	public function handle_start_action( array $args ): void {
		$this->handle_start( (int) $args['job_id'], (string) $args['run_id'] );
	}

	public function handle_stage1_page_action( array $args ): void {
		$this->handle_stage1_page( (int) $args['job_id'], (string) $args['run_id'], (int) $args['page'] );
	}

	public function handle_stage2_batch_action( array $args ): void {
		$this->handle_stage2_batch( (int) $args['job_id'], (string) $args['run_id'] );
	}

	// ── Public API ─────────────────────────────────────────────────────────

	/**
	 * Enqueue an async import for a job.  Returns the run_id.
	 */
	public function enqueue( int $job_id ): string {
		$run_id = wp_generate_uuid4();
		$this->create_run_record( $run_id, $job_id );

		as_enqueue_async_action( self::HOOK_START, [
			'job_id' => $job_id,
			'run_id' => $run_id,
		], 'appcon' );

		return $run_id;
	}

	// ── Action handlers ────────────────────────────────────────────────────

	/**
	 * 1. Import start – initialise run and kick off Stage 1.
	 */
	public function handle_start( int $job_id, string $run_id ): void {
		global $wpdb;

		$wpdb->update( Database::get_runs_table(), [
			'status'        => 'running',
			'started_at'    => current_time( 'mysql' ),
			'current_stage' => 1,
		], [ 'run_id' => $run_id ] );

		$this->log( $run_id, 'info', 'Import started via Action Scheduler', 'start' );

		// Dispatch page 1 of Stage 1.
		$this->dispatch_stage1_page( $job_id, $run_id, 1 );
	}

	/**
	 * 2. Stage 1 – fetch one page of the vacancy list.
	 */
	public function handle_stage1_page( int $job_id, string $run_id, int $page ): void {
		global $wpdb;

		$job = \ApprenticeshipConnector\Import\ImportJob::find( $job_id );
		if ( ! $job ) {
			$this->fail_run( $run_id, "Job #{$job_id} not found." );
			return;
		}

		$api = DisplayAdvertAPI::from_job( $job );

		$params = array_merge( $job->stage1_filters, [
			'PageNumber' => $page,
			'PageSize'   => $job->stage1_page_size,
			'Sort'       => $job->stage1_sort,
		] );

		$this->log( $run_id, 'debug', "Stage 1 – fetching page {$page}", 'stage1', [ 'params' => $params ] );

		$response = $api->getVacancies( $params );

		if ( ! $response['success'] ) {
			$this->log( $run_id, 'error', "Stage 1 page {$page} failed: {$response['error']}", 'stage1' );
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . Database::get_runs_table() . ' SET stage1_errors = stage1_errors + 1 WHERE run_id = %s',
				$run_id
			) );
			// Stop on API error – don't chain next page.
			$this->dispatch_stage2_first_batch( $run_id );
			return;
		}

		$vacancies   = $response['data']['vacancies'] ?? [];
		$total_pages = $response['data']['totalPages'] ?? 1;

		// Insert references into the index table.
		$index_table = Database::get_vacancy_index_table();
		$inserted    = 0;

		foreach ( $vacancies as $v ) {
			$ref = $v['vacancyReference'] ?? '';
			if ( ! $ref ) continue;

			$wpdb->insert( $index_table, [
				'run_id'            => $run_id,
				'job_id'            => $job_id,
				'vacancy_reference' => $ref,
				'status'            => 'pending',
				'created_at'        => current_time( 'mysql' ),
			] );
			$inserted++;
		}

		// Update run-level Stage 1 stats.
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . Database::get_runs_table() . '
			 SET stage1_pages   = %d,
			     stage1_fetched = stage1_fetched + %d
			 WHERE run_id = %s',
			$page, $inserted, $run_id
		) );

		$this->log( $run_id, 'debug', "Stage 1 page {$page} – {$inserted} references indexed", 'stage1', [
			'page'        => $page,
			'total_pages' => $total_pages,
			'inserted'    => $inserted,
		] );

		// Chain next page or transition to Stage 2.
		if ( $page < $total_pages && $page < $job->stage1_max_pages ) {
			$this->dispatch_stage1_page( $job_id, $run_id, $page + 1 );
		} else {
			$this->log( $run_id, 'info', "Stage 1 complete – transitioning to Stage 2", 'stage1' );
			$this->dispatch_stage2_first_batch( $run_id );
		}
	}

	/**
	 * 3. Stage 2 – process one batch of pending vacancy references.
	 */
	public function handle_stage2_batch( int $job_id, string $run_id ): void {
		global $wpdb;

		// ── Mutex: prevent two batches for the same run running simultaneously.
		$lock_key = 'appcon_lock_' . md5( $run_id );
		if ( get_transient( $lock_key ) ) {
			// Another batch is still running; re-queue self for a few seconds later.
			as_schedule_single_action( time() + 5, self::HOOK_STAGE2_BATCH, [
				'job_id' => $job_id,
				'run_id' => $run_id,
			], 'appcon' );
			return;
		}
		set_transient( $lock_key, 1, 60 ); // 60 s TTL

		try {
			$this->process_stage2_batch( $job_id, $run_id );
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * 4. Expiry – find vacancies past their closing date and draft them.
	 */
	public function handle_expire_vacancies(): void {
		$today = gmdate( 'Y-m-d' );

		$query = new \WP_Query( [
			'post_type'      => 'appcon_vacancy',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => [ [
				'key'     => '_appcon_closing_date',
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			] ],
		] );

		$expired = 0;
		foreach ( $query->posts as $post_id ) {
			wp_update_post( [
				'ID'          => $post_id,
				'post_status' => 'draft',
			] );
			update_post_meta( $post_id, '_appcon_expired', 1 );
			$expired++;
		}

		if ( $expired > 0 ) {
			\ApprenticeshipConnector\Core\Settings::set( 'last_expiry_run', [
				'date'    => $today,
				'expired' => $expired,
			] );
		}
	}

	// ── Private helpers ────────────────────────────────────────────────────

	private function process_stage2_batch( int $job_id, string $run_id ): void {
		global $wpdb;

		$index_table = Database::get_vacancy_index_table();
		$runs_table  = Database::get_runs_table();

		// Grab one batch of PENDING references.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, vacancy_reference FROM {$index_table}
			 WHERE run_id = %s AND status = 'pending'
			 ORDER BY id ASC
			 LIMIT %d",
			$run_id, self::STAGE2_BATCH_SIZE
		), ARRAY_A );

		if ( empty( $rows ) ) {
			// No more pending rows → run is complete.
			$this->complete_run( $run_id );
			return;
		}

		$job = ImportJob::find( $job_id );
		if ( ! $job ) {
			$this->fail_run( $run_id, "Job #{$job_id} not found during Stage 2." );
			return;
		}

		$api    = DisplayAdvertAPI::from_job( $job );
		$mapper = new FieldMapper( $job->field_mappings );

		// Count total pending for progress tracking.
		$total_remaining = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$index_table} WHERE run_id = %s AND status IN ('pending','processing')",
			$run_id
		) );
		$total_indexed = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$index_table} WHERE run_id = %s",
			$run_id
		) );

		foreach ( $rows as $row ) {
			$ref = $row['vacancy_reference'];
			$idx_id = (int) $row['id'];

			// Mark as processing.
			$wpdb->update( $index_table, [ 'status' => 'processing' ], [ 'id' => $idx_id ] );

			try {
				$response = $api->getVacancy( $ref );

				if ( ! $response['success'] ) {
					$this->log( $run_id, 'warning', "Stage 2 failed: {$ref} – {$response['error']}", 'stage2' );
					$wpdb->update( $index_table, [
						'status'        => 'failed',
						'error_message' => $response['error'],
					], [ 'id' => $idx_id ] );
					$wpdb->query( $wpdb->prepare(
						'UPDATE ' . $runs_table . ' SET stage2_errors = stage2_errors + 1 WHERE run_id = %s',
						$run_id
					) );
					continue;
				}

				$vacancy   = $response['data'];
				$post_data = $mapper->mapToPost( $vacancy );
				$meta_data = $mapper->mapToMeta( $vacancy );
				$tax_data  = $mapper->mapToTaxonomies( $vacancy );

				// ── Expiry: ensure closing_date meta is set from API ───────
				$closing_date = $vacancy['closingDate'] ?? null;
				if ( $closing_date ) {
					$meta_data['_appcon_closing_date'] = substr( $closing_date, 0, 10 ); // Y-m-d
				}

				$existing_id = $this->find_existing_vacancy( $ref );

				if ( $existing_id ) {
					$this->update_vacancy( $existing_id, $post_data, $meta_data, $tax_data );
					$wpdb->update( $index_table, [ 'status' => 'completed', 'post_id' => $existing_id, 'fetched_at' => current_time( 'mysql' ) ], [ 'id' => $idx_id ] );
					$wpdb->query( $wpdb->prepare(
						'UPDATE ' . $runs_table . ' SET stage2_fetched = stage2_fetched + 1, stage2_updated = stage2_updated + 1 WHERE run_id = %s',
						$run_id
					) );
					$this->log( $run_id, 'debug', "Updated: {$ref} (post #{$existing_id})", 'stage2' );
				} else {
					$post_id = $this->create_vacancy( $post_data, $meta_data, $tax_data );
					$wpdb->update( $index_table, [ 'status' => 'completed', 'post_id' => $post_id, 'fetched_at' => current_time( 'mysql' ) ], [ 'id' => $idx_id ] );
					$wpdb->query( $wpdb->prepare(
						'UPDATE ' . $runs_table . ' SET stage2_fetched = stage2_fetched + 1, stage2_created = stage2_created + 1 WHERE run_id = %s',
						$run_id
					) );
					$this->log( $run_id, 'debug', "Created: {$ref} (post #{$post_id})", 'stage2' );
				}
			} catch ( \Throwable $e ) {
				$wpdb->update( $index_table, [
					'status'        => 'failed',
					'error_message' => $e->getMessage(),
				], [ 'id' => $idx_id ] );
				$wpdb->query( $wpdb->prepare(
					'UPDATE ' . $runs_table . ' SET stage2_errors = stage2_errors + 1 WHERE run_id = %s',
					$run_id
				) );
				$this->log( $run_id, 'error', "Exception on {$ref}: " . $e->getMessage(), 'stage2' );
			}
		}

		// Recalculate progress.
		$completed = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$index_table} WHERE run_id = %s AND status IN ('completed','failed','skipped')",
			$run_id
		) );
		$pct = $total_indexed > 0 ? round( $completed / $total_indexed * 100, 2 ) : 0.0;

		$wpdb->update( $runs_table, [
			'current_stage' => 2,
			'current_item'  => $completed,
			'total_items'   => $total_indexed,
			'progress_pct'  => $pct,
			'stage2_total'  => $total_indexed,
		], [ 'run_id' => $run_id ] );

		// Check if there are still pending rows; if so, chain next batch.
		$still_pending = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$index_table} WHERE run_id = %s AND status = 'pending'",
			$run_id
		) );

		if ( $still_pending > 0 ) {
			as_enqueue_async_action( self::HOOK_STAGE2_BATCH, [
				'job_id' => $job_id,
				'run_id' => $run_id,
			], 'appcon' );
		} else {
			$this->complete_run( $run_id );
		}
	}

	// ── Dispatch helpers ───────────────────────────────────────────────────

	private function dispatch_stage1_page( int $job_id, string $run_id, int $page ): void {
		as_enqueue_async_action( self::HOOK_STAGE1_PAGE, [
			'job_id' => $job_id,
			'run_id' => $run_id,
			'page'   => $page,
		], 'appcon' );
	}

	private function dispatch_stage2_first_batch( string $run_id ): void {
		global $wpdb;

		$job_id = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT job_id FROM ' . Database::get_runs_table() . ' WHERE run_id = %s',
			$run_id
		) );

		as_enqueue_async_action( self::HOOK_STAGE2_BATCH, [
			'job_id' => $job_id,
			'run_id' => $run_id,
		], 'appcon' );
	}

	// ── Run state ─────────────────────────────────────────────────────────

	private function complete_run( string $run_id ): void {
		global $wpdb;

		$runs_table  = Database::get_runs_table();
		$index_table = Database::get_vacancy_index_table();

		// Final stats.
		$totals = $wpdb->get_row( $wpdb->prepare(
			"SELECT
			   COUNT(*) AS total,
			   SUM(status = 'completed') AS completed,
			   SUM(status = 'failed')    AS failed
			 FROM {$index_table}
			 WHERE run_id = %s",
			$run_id
		) );

		$started_at = $wpdb->get_var( $wpdb->prepare(
			'SELECT started_at FROM ' . $runs_table . ' WHERE run_id = %s',
			$run_id
		) );

		$duration = $started_at
			? (int) ( strtotime( current_time( 'mysql' ) ) - strtotime( $started_at ) )
			: 0;

		$wpdb->update( $runs_table, [
			'status'       => 'completed',
			'completed_at' => current_time( 'mysql' ),
			'duration'     => $duration,
			'stage2_total' => (int) $totals->total,
			'progress_pct' => 100.00,
		], [ 'run_id' => $run_id ] );

		// Update job last-run stats.
		$run = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . $runs_table . ' WHERE run_id = %s',
			$run_id
		), ARRAY_A );

		if ( $run ) {
			ImportJob::save( [
				'id'                      => $run['job_id'],
				'last_run_at'             => current_time( 'mysql' ),
				'last_run_status'         => 'completed',
				'last_run_stage1_fetched' => (int) $run['stage1_fetched'],
				'last_run_stage2_fetched' => (int) $run['stage2_fetched'],
				'last_run_created'        => (int) $run['stage2_created'],
				'last_run_updated'        => (int) $run['stage2_updated'],
				'last_run_errors'         => (int) $run['stage2_errors'],
				'last_run_duration'       => $duration,
			] );
		}

		$this->log( $run_id, 'info', sprintf(
			'Import complete – %d created, %d updated, %d errors (%ds)',
			(int) ( $run['stage2_created'] ?? 0 ),
			(int) ( $run['stage2_updated'] ?? 0 ),
			(int) ( $run['stage2_errors']  ?? 0 ),
			$duration
		), 'complete' );
	}

	private function fail_run( string $run_id, string $message ): void {
		global $wpdb;

		$wpdb->update( Database::get_runs_table(), [
			'status'        => 'failed',
			'completed_at'  => current_time( 'mysql' ),
			'error_message' => $message,
		], [ 'run_id' => $run_id ] );

		$this->log( $run_id, 'critical', $message, 'error' );
	}

	private function create_run_record( string $run_id, int $job_id ): void {
		global $wpdb;

		$wpdb->insert( Database::get_runs_table(), [
			'run_id'     => $run_id,
			'job_id'     => $job_id,
			'status'     => 'queued',
			'created_at' => current_time( 'mysql' ),
		] );
	}

	// ── Post CRUD helpers ─────────────────────────────────────────────────

	private function find_existing_vacancy( string $reference ): ?int {
		global $wpdb;

		$post_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key   = '_appcon_vacancy_reference'
			   AND meta_value = %s
			 LIMIT 1",
			$reference
		) );

		return $post_id ? (int) $post_id : null;
	}

	private function create_vacancy( array $post_data, array $meta_data, array $tax_data ): int {
		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( 'wp_insert_post failed: ' . $post_id->get_error_message() );
		}

		$this->save_meta_and_taxonomies( $post_id, $meta_data, $tax_data );
		return $post_id;
	}

	private function update_vacancy( int $post_id, array $post_data, array $meta_data, array $tax_data ): void {
		wp_update_post( array_merge( $post_data, [ 'ID' => $post_id ] ) );
		$this->save_meta_and_taxonomies( $post_id, $meta_data, $tax_data );
	}

	private function save_meta_and_taxonomies( int $post_id, array $meta_data, array $tax_data ): void {
		foreach ( $meta_data as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		foreach ( $tax_data as $taxonomy => $terms ) {
			wp_set_object_terms( $post_id, $terms, $taxonomy );
		}
	}

	// ── Logging ───────────────────────────────────────────────────────────

	private function log( string $run_id, string $level, string $message, ?string $context = null, array $meta = [] ): void {
		global $wpdb;

		$wpdb->insert( Database::get_logs_table(), [
			'run_id'    => $run_id,
			'log_level' => $level,
			'message'   => $message,
			'context'   => $context,
			'meta_data' => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
		] );
	}
}
