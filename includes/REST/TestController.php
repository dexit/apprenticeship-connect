<?php
/**
 * REST endpoint for API connectivity tests and Stage 2 samples.
 *
 * POST /wp-json/appcon/v1/test/api
 *
 * @package ApprenticeshipConnector\REST
 */

namespace ApprenticeshipConnector\REST;

use ApprenticeshipConnector\API\DisplayAdvertAPI;
use ApprenticeshipConnector\Import\ImportJob;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class TestController extends WP_REST_Controller {

	protected $namespace = 'appcon/v1';
	protected $rest_base = 'test';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/api', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_api' ],
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			],
		] );

		// No-job-id connectivity ping – used by the dashboard API status widget.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/connectivity', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_connectivity' ],
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			],
		] );
	}

	/**
	 * Test API connectivity and optionally return a Stage 2 sample.
	 *
	 * Request body: { "job_id": 1, "test_type": "stage1"|"stage2_sample" }
	 */
	public function test_api( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data      = $request->get_json_params();
		$job_id    = (int) ( $data['job_id']   ?? 0 );
		$test_type = (string) ( $data['test_type'] ?? 'stage1' );

		$job = ImportJob::find( $job_id );

		if ( ! $job ) {
			return new WP_Error( 'not_found', __( 'Import job not found.', 'apprenticeship-connector' ), [ 'status' => 404 ] );
		}

		$api = DisplayAdvertAPI::from_job( $job );

		// Stage 1 connectivity test.
		$stage1 = $api->getVacancies( [
			'PageNumber' => 1,
			'PageSize'   => 1,
		] );

		if ( ! $stage1['success'] ) {
			return rest_ensure_response( [
				'success' => false,
				'error'   => $stage1['error'],
				'stage'   => 1,
			] );
		}

		$result = [
			'success'       => true,
			'stage1_status' => $stage1['status'],
			'total'         => $stage1['data']['total'] ?? 0,
		];

		// Stage 2 sample: fetch one full vacancy.
		if ( 'stage2_sample' === $test_type ) {
			$first_ref = $stage1['data']['vacancies'][0]['vacancyReference'] ?? null;

			if ( $first_ref ) {
				$stage2 = $api->getVacancy( $first_ref );

				$result['stage2_status'] = $stage2['status'];
				$result['sample']        = $stage2['success'] ? $stage2['data'] : null;

				if ( ! $stage2['success'] ) {
					$result['stage2_error'] = $stage2['error'];
				}
			}
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Quick connectivity test using the globally stored API settings.
	 * No job_id required – called by the dashboard API Status widget.
	 *
	 * POST /wp-json/appcon/v1/test/connectivity
	 */
	public function test_connectivity( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$settings = get_option( 'appcon_settings', [] );
		$api_key  = $settings['api_key']      ?? '';
		$base_url = $settings['api_base_url'] ?? '';

		if ( empty( $api_key ) ) {
			return rest_ensure_response( [
				'success' => false,
				'stage'   => 'config',
				'error'   => __( 'No API key configured. Visit Settings → API to add your key.', 'apprenticeship-connector' ),
			] );
		}

		if ( empty( $base_url ) ) {
			return rest_ensure_response( [
				'success' => false,
				'stage'   => 'config',
				'error'   => __( 'No API base URL configured.', 'apprenticeship-connector' ),
			] );
		}

		$api = new \ApprenticeshipConnector\API\DisplayAdvertAPI(
			$base_url,
			$api_key,
			[
				'rate_limit_ms'   => (int) ( $settings['rate_limit_ms']   ?? 2000 ),
				'stage2_delay_ms' => (int) ( $settings['stage2_delay_ms'] ?? 2000 ),
			]
		);

		$response = $api->getVacancies( [ 'PageNumber' => 1, 'PageSize' => 1 ] );

		if ( ! $response['success'] ) {
			return rest_ensure_response( [
				'success' => false,
				'stage'   => 'api',
				'error'   => $response['error'] ?? __( 'API request failed.', 'apprenticeship-connector' ),
				'status'  => $response['status'] ?? null,
			] );
		}

		return rest_ensure_response( [
			'success' => true,
			'total'   => $response['data']['total'] ?? 0,
			/* translators: %d: vacancy count returned by API */
			'message' => sprintf( __( 'Connected. API reports %d vacancies.', 'apprenticeship-connector' ), $response['data']['total'] ?? 0 ),
		] );
	}
}
