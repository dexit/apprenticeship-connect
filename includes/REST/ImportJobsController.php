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

	public function trigger_run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$runner = new ImportRunner();
			$run_id = $runner->trigger( (int) $request['id'] );
			return rest_ensure_response( [ 'run_id' => $run_id ] );
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
