<?php
/**
 * REST API Controller Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_REST_Controller
 *
 * Handles REST API endpoint logic.
 */
class Apprco_REST_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_REST_Controller|null
	 */
	private static $instance = null;

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
	 * Registers REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'apprco/v1',
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			'apprco/v1',
			'/tasks',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_tasks' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_task' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);

		register_rest_route(
			'apprco/v1',
			'/tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_task' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_task' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_task' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);

		register_rest_route(
			'apprco/v1',
			'/tasks/(?P<id>\d+)/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_task' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			'apprco/v1',
			'/tasks/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_task' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			'apprco/v1',
			'/import/logs/(?P<import_id>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_import_logs' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);
	}

	/**
	 * Permission check for admin endpoints.
	 *
	 * @return bool
	 */
	public function permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get stats endpoint.
	 *
	 * @return WP_REST_Response
	 */
	public function get_stats(): WP_REST_Response {
		$logger              = Apprco_Import_Logger::get_instance();
		$stats               = $logger->get_stats();
		$stats['resilience'] = get_transient( 'apprco_last_api_stats' ) ? get_transient( 'apprco_last_api_stats' ) : array();
		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * Get tasks endpoint.
	 *
	 * @return WP_REST_Response
	 */
	public function get_tasks(): WP_REST_Response {
		$manager = Apprco_Import_Tasks::get_instance();
		return new WP_REST_Response( array( 'tasks' => $manager->get_all() ), 200 );
	}

	/**
	 * Get task endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_task( $request ): WP_REST_Response {
		$manager = Apprco_Import_Tasks::get_instance();
		return new WP_REST_Response( $manager->get( (int) $request['id'] ), 200 );
	}

	/**
	 * Create task endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function create_task( $request ): WP_REST_Response {
		$manager = Apprco_Import_Tasks::get_instance();
		$id      = $manager->create( $request->get_json_params() );
		return new WP_REST_Response( array( 'id' => $id ), 200 );
	}

	/**
	 * Update task endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function update_task( $request ): WP_REST_Response {
		$manager = Apprco_Import_Tasks::get_instance();
		$success = $manager->update( (int) $request['id'], $request->get_json_params() );
		return new WP_REST_Response( array( 'success' => $success ), 200 );
	}

	/**
	 * Delete task endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function delete_task( $request ): WP_REST_Response {
		$manager = Apprco_Import_Tasks::get_instance();
		$success = $manager->delete( (int) $request['id'] );
		return new WP_REST_Response( array( 'success' => $success ), 200 );
	}

	/**
	 * Run task endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function run_task( $request ): WP_REST_Response {
		$id      = (int) $request['id'];
		$manager = Apprco_Import_Tasks::get_instance();
		$result  = $manager->run_import( $id );
		return new WP_REST_Response( $result, $result['success'] ? 200 : 400 );
	}

	/**
	 * Test task endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function test_task( $request ): WP_REST_Response {
		$data   = $request->get_json_params();
		$client = new Apprco_API_Client( $data['api_base_url'] );
		$client->set_default_headers( isset( $data['api_headers'] ) ? $data['api_headers'] : array() );
		$params = isset( $data['api_params'] ) ? $data['api_params'] : array();
		if ( isset( $data['page_param'] ) ) {
			$params[ $data['page_param'] ] = 1;
		}
		$res = $client->get( isset( $data['api_endpoint'] ) ? $data['api_endpoint'] : '/vacancy', $params );
		set_transient( 'apprco_last_api_stats', $client->get_stats(), 300 );
		return new WP_REST_Response( $res, 200 );
	}

	/**
	 * Get import logs endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_import_logs( $request ): WP_REST_Response {
		$logger = Apprco_Import_Logger::get_instance();
		$logs   = $logger->get_logs( $request['import_id'] );
		return new WP_REST_Response( array( 'logs' => $logs ), 200 );
	}
}
