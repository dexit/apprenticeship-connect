<?php
/**
 * REST API controller for import jobs.
 *
 * Namespace: /wp-json/appcon/v1/import-jobs
 *
 * @package ApprenticeshipConnector\REST
 */

namespace ApprenticeshipConnector\REST;

use ApprenticeshipConnector\Import\ImportJob;
use ApprenticeshipConnector\Import\ImportRunner;
use ApprenticeshipConnector\Import\ActionSchedulerRunner;
use ApprenticeshipConnector\Import\ExpiryManager;
use ApprenticeshipConnector\Core\Database;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ImportJobsController extends WP_REST_Controller {

	protected $namespace = 'appcon/v1';
	protected $rest_base = 'import-jobs';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
				'args'                => [ 'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) ] ],
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
		] );

		// Trigger an import run.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/run', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'trigger_run' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
		] );

		// List runs for a job.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/runs', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_runs' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
		] );

		// Single run status (used by ProgressMonitor polling).
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/runs/(?P<run_id>[0-9a-f\-]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_run' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
		] );

		// Logs for a run.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/runs/(?P<run_id>[0-9a-f\-]+)/logs', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_run_logs' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
		] );

		// Index progress for a run.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/runs/(?P<run_id>[0-9a-f\-]+)/index', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_run_index' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
		] );

		// Expiry management.
		register_rest_route( $this->namespace, '/expiry/run', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run_expiry' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/expiry/stats', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_expiry_stats' ],
				'permission_callback' => [ $this, 'admin_permissions_check' ],
			],
		] );
	}

	// ── Handlers ──────────────────────────────────────────────────────────

	public function get_items( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$rows = $wpdb->get_results( 'SELECT * FROM ' . Database::get_jobs_table() . ' ORDER BY created_at DESC', ARRAY_A );
		return rest_ensure_response( array_map( [ $this, 'prepare_item' ], $rows ) );
	}

	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Database::get_jobs_table() . ' WHERE id = %d', $request['id'] ),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Import job not found.', 'apprenticeship-connector' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->prepare_item( $row ) );
	}

	public function create_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();

		if ( empty( $data['name'] ) ) {
			return new WP_Error( 'missing_name', __( 'Name is required.', 'apprenticeship-connector' ), [ 'status' => 400 ] );
		}

		$id = ImportJob::save( $data );
		return rest_ensure_response( [ 'id' => $id ] );
	}

	public function update_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data       = $request->get_json_params();
		$data['id'] = (int) $request['id'];

		ImportJob::save( $data );
		return rest_ensure_response( [ 'updated' => true ] );
	}

	public function delete_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$deleted = ImportJob::delete( (int) $request['id'] );

		if ( ! $deleted ) {
			return new WP_Error( 'not_found', __( 'Import job not found.', 'apprenticeship-connector' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	/**
	 * Trigger an import via Action Scheduler (async, recommended).
	 * Falls back to synchronous ImportRunner if AS is unavailable.
	 */
	public function trigger_run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job_id = (int) $request['id'];

		$job = ImportJob::find( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'not_found', __( 'Import job not found.', 'apprenticeship-connector' ), [ 'status' => 404 ] );
		}

		try {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				$as_runner = new ActionSchedulerRunner();
				$run_id    = $as_runner->enqueue( $job_id );
				return rest_ensure_response( [
					'run_id'  => $run_id,
					'mode'    => 'action_scheduler',
					'message' => __( 'Import queued via Action Scheduler.', 'apprenticeship-connector' ),
				] );
			}

			// Synchronous fallback.
			$runner = new ImportRunner();
			$run_id = $runner->trigger( $job_id );
			return rest_ensure_response( [
				'run_id'  => $run_id,
				'mode'    => 'synchronous',
				'message' => __( 'Import executed synchronously.', 'apprenticeship-connector' ),
			] );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'run_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	public function get_runs( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . Database::get_runs_table() . ' WHERE job_id = %d ORDER BY created_at DESC LIMIT 50',
			$request['id']
		), ARRAY_A );

		return rest_ensure_response( $rows );
	}

	/** Single run status – polled by ProgressMonitor every 3 s. */
	public function get_run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . Database::get_runs_table() . ' WHERE run_id = %s',
			$request['run_id']
		), ARRAY_A );

		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Run not found.', 'apprenticeship-connector' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $row );
	}

	/** Paginated log lines for a run. */
	public function get_run_logs( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$limit  = min( (int) ( $request->get_param( 'limit' )  ?? 100 ), 500 );
		$offset = (int) ( $request->get_param( 'offset' ) ?? 0 );

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . Database::get_logs_table() . '
			 WHERE run_id = %s
			 ORDER BY id ASC
			 LIMIT %d OFFSET %d',
			$request['run_id'], $limit, $offset
		), ARRAY_A );

		return rest_ensure_response( $rows );
	}

	/** Summary of index rows for a run (pending/completed/failed counts). */
	public function get_run_index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$table = Database::get_vacancy_index_table();
		$run_id = $request['run_id'];

		$counts = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, COUNT(*) AS cnt FROM {$table} WHERE run_id = %s GROUP BY status",
			$run_id
		), ARRAY_A );

		$summary = [ 'pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0, 'total' => 0 ];
		foreach ( $counts as $row ) {
			$summary[ $row['status'] ] = (int) $row['cnt'];
			$summary['total'] += (int) $row['cnt'];
		}

		return rest_ensure_response( $summary );
	}

	/** Manually trigger the expiry check. */
	public function run_expiry( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$expiry  = new ExpiryManager();
			$results = $expiry->run();
			return rest_ensure_response( array_merge( $results, [ 'success' => true ] ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'expiry_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/** Expiry statistics for the dashboard. */
	public function get_expiry_stats( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$expiry = new ExpiryManager();
		return rest_ensure_response( $expiry->get_stats() );
	}

	// ── Permissions ────────────────────────────────────────────────────────

	public function admin_permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	// ── Helpers ────────────────────────────────────────────────────────────

	private function prepare_item( array $row ): array {
		foreach ( [ 'stage1_filters', 'field_mappings' ] as $col ) {
			if ( isset( $row[ $col ] ) && is_string( $row[ $col ] ) ) {
				$row[ $col ] = json_decode( $row[ $col ], true ) ?? [];
			}
		}
		return $row;
	}
}
