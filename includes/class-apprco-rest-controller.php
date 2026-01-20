<?php
/**
 * REST API Controller
 *
 * Provides REST endpoints for React dashboard and settings.
 *
 * @package ApprenticeshipConnect
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Apprco_REST_Controller
 */
class Apprco_REST_Controller {

	/**
	 * Register REST API routes
	 */
	public static function register_routes(): void {
		// Stats endpoint
		register_rest_route(
			'apprco/v1',
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_stats' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
			)
		);

		// Recent imports endpoint
		register_rest_route(
			'apprco/v1',
			'/imports/recent',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_recent_imports' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
			)
		);

		// Manual import endpoint
		register_rest_route(
			'apprco/v1',
			'/import/manual',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'run_manual_import' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
			)
		);

		// API test endpoint
		register_rest_route(
			'apprco/v1',
			'/api/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'test_api_connection' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
			)
		);
	}

	/**
	 * Permission check
	 *
	 * @return bool
	 */
	public static function permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get dashboard stats
	 *
	 * @return WP_REST_Response
	 */
	public static function get_stats(): WP_REST_Response {
		$adapter = Apprco_Import_Adapter::get_instance();
		$stats   = $adapter->get_stats();

		// Add API configured status
		$settings_manager = Apprco_Settings_Manager::get_instance();
		$api_key = $settings_manager->get( 'api', 'subscription_key' );

		$stats['api_configured'] = ! empty( $api_key );

		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * Get recent imports
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function get_recent_imports( WP_REST_Request $request ): WP_REST_Response {
		$limit = $request->get_param( 'limit' ) ?: 5;
		$limit = min( absint( $limit ), 100 );

		$logger = Apprco_Import_Logger::get_instance();
		$imports = $logger->get_recent( $limit );

		return new WP_REST_Response(
			array( 'imports' => $imports ),
			200
		);
	}

	/**
	 * Run manual import
	 *
	 * @return WP_REST_Response
	 */
	public static function run_manual_import(): WP_REST_Response {
		$adapter = Apprco_Import_Adapter::get_instance();
		$result = $adapter->run_manual_sync();

		if ( ! $result['success'] ) {
			return new WP_REST_Response( $result, 400 );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Test API connection
	 *
	 * @return WP_REST_Response
	 */
	public static function test_api_connection(): WP_REST_Response {
		$settings_manager = Apprco_Settings_Manager::get_instance();

		$base_url = $settings_manager->get( 'api', 'base_url' );
		$api_key  = $settings_manager->get( 'api', 'subscription_key' );

		if ( empty( $api_key ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'API credentials not configured. Please configure in Settings.', 'apprenticeship-connect' ),
				),
				400
			);
		}

		// Test API request
		$response = wp_remote_get(
			add_query_arg( array( 'PageNumber' => 1, 'PageSize' => 1 ), $base_url ),
			array(
				'headers' => array(
					'Ocp-Apim-Subscription-Key' => $api_key,
					'X-Version'                => '2',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => sprintf(
						__( 'Connection failed: %s', 'apprenticeship-connect' ),
						$response->get_error_message()
					),
				),
				400
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			$error_messages = array(
				401 => __( 'Unauthorized. Please check your API subscription key.', 'apprenticeship-connect' ),
				403 => __( 'Forbidden. Your API key may not have permission.', 'apprenticeship-connect' ),
				404 => __( 'Not found. Please check your API base URL.', 'apprenticeship-connect' ),
				429 => __( 'Rate limit exceeded. Please try again later.', 'apprenticeship-connect' ),
				500 => __( 'Server error. The API service is experiencing issues.', 'apprenticeship-connect' ),
			);

			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => sprintf(
						__( 'HTTP %d: %s', 'apprenticeship-connect' ),
						$code,
						$error_messages[ $code ] ?? __( 'Request failed', 'apprenticeship-connect' )
					),
				),
				400
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Invalid JSON response from API.', 'apprenticeship-connect' ),
				),
				400
			);
		}

		$sample_count = isset( $data['vacancies'] ) ? count( $data['vacancies'] ) : 0;

		return new WP_REST_Response(
			array(
				'success'      => true,
				'message'      => __( 'API connection successful!', 'apprenticeship-connect' ),
				'sample_count' => $sample_count,
			),
			200
		);
	}
}

// Register routes on init
add_action( 'rest_api_init', array( 'Apprco_REST_Controller', 'register_routes' ) );
