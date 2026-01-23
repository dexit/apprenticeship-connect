<?php
/**
 * REST API CORS Proxy for UK Government Display Advert API v2
 *
 * Proxies requests from frontend to the gov.uk API with proper authentication
 * and CORS headers, avoiding browser same-origin policy restrictions.
 *
 * @package    Apprenticeship_Connect
 * @subpackage Rest
 * @since      3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Apprco_REST_Proxy
 *
 * Handles proxying of requests to the UK Government Apprenticeships API
 * with proper authentication, CORS support, and error handling.
 */
class Apprco_REST_Proxy {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private const API_BASE_URL = 'https://api.apprenticeships.education.gov.uk';

	/**
	 * Register REST API routes
	 */
	public function register_routes(): void {
		// Proxy vacancy list with location/filter support
		register_rest_route(
			'apprco/v1',
			'/proxy/vacancies',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'proxy_vacancies' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => $this->get_vacancy_list_args(),
			)
		);

		// Proxy single vacancy by reference
		register_rest_route(
			'apprco/v1',
			'/proxy/vacancy/(?P<reference>[a-zA-Z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'proxy_single_vacancy' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => array(
					'reference' => array(
						'description' => 'Vacancy reference number',
						'type'        => 'string',
						'required'    => true,
					),
				),
			)
		);

		// Proxy reference data - courses
		register_rest_route(
			'apprco/v1',
			'/proxy/courses',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'proxy_courses' ),
				'permission_callback' => '__return_true', // Public endpoint
			)
		);

		// Proxy reference data - routes
		register_rest_route(
			'apprco/v1',
			'/proxy/routes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'proxy_routes' ),
				'permission_callback' => '__return_true', // Public endpoint
			)
		);
	}

	/**
	 * Get argument schema for vacancy list endpoint
	 *
	 * @return array
	 */
	private function get_vacancy_list_args(): array {
		return array(
			'Lat'                      => array(
				'description'       => 'Latitude for location-based search',
				'type'              => 'number',
				'validate_callback' => function( $param ) {
					return is_numeric( $param ) && $param >= -90 && $param <= 90;
				},
			),
			'Lon'                      => array(
				'description'       => 'Longitude for location-based search',
				'type'              => 'number',
				'validate_callback' => function( $param ) {
					return is_numeric( $param ) && $param >= -180 && $param <= 180;
				},
			),
			'DistanceInMiles'          => array(
				'description'       => 'Search radius in miles',
				'type'              => 'integer',
				'validate_callback' => function( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			),
			'Sort'                     => array(
				'description' => 'Sort order for results',
				'type'        => 'string',
				'enum'        => array(
					'AgeDesc',
					'AgeAsc',
					'DistanceDesc',
					'DistanceAsc',
					'ExpectedStartDateDesc',
					'ExpectedStartDateAsc',
				),
				'default'     => 'AgeDesc',
			),
			'PageNumber'               => array(
				'description'       => 'Page number (1-based)',
				'type'              => 'integer',
				'default'           => 1,
				'validate_callback' => function( $param ) {
					return is_numeric( $param ) && $param >= 1;
				},
			),
			'PageSize'                 => array(
				'description' => 'Number of results per page',
				'type'        => 'integer',
				'enum'        => array( 10, 20, 30, 50 ),
				'default'     => 10,
			),
			'PostedInLastNumberOfDays' => array(
				'description' => 'Filter by posting date',
				'type'        => 'integer',
				'enum'        => array( 3, 7, 14, 28 ),
			),
		);
	}

	/**
	 * Proxy vacancy list request
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function proxy_vacancies( WP_REST_Request $request ) {
		// Build query params from request
		$query_params = array();

		foreach ( array( 'Lat', 'Lon', 'DistanceInMiles', 'Sort', 'PageNumber', 'PageSize', 'PostedInLastNumberOfDays' ) as $param ) {
			$value = $request->get_param( $param );
			if ( null !== $value && '' !== $value ) {
				$query_params[ $param ] = $value;
			}
		}

		// Make API request
		$url = self::API_BASE_URL . '/vacancies/vacancy';
		return $this->make_proxy_request( $url, $query_params );
	}

	/**
	 * Proxy single vacancy request
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function proxy_single_vacancy( WP_REST_Request $request ) {
		$reference = $request->get_param( 'reference' );
		$url       = self::API_BASE_URL . '/vacancies/vacancy/' . urlencode( $reference );

		return $this->make_proxy_request( $url );
	}

	/**
	 * Proxy courses reference data request
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function proxy_courses( WP_REST_Request $request ) {
		$url = self::API_BASE_URL . '/vacancies/referencedata/courses';
		return $this->make_proxy_request( $url );
	}

	/**
	 * Proxy routes reference data request
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function proxy_routes( WP_REST_Request $request ) {
		$url = self::API_BASE_URL . '/vacancies/referencedata/courses/routes';
		return $this->make_proxy_request( $url );
	}

	/**
	 * Make proxied API request with authentication
	 *
	 * @param string $url          API endpoint URL.
	 * @param array  $query_params Optional query parameters.
	 * @return WP_REST_Response|WP_Error
	 */
	private function make_proxy_request( string $url, array $query_params = array() ) {
		// Get API credentials from Settings Manager
		$settings_manager = Apprco_Settings_Manager::get_instance();
		$api_key          = $settings_manager->get( 'api', 'subscription_key' );

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'no_api_key',
				'API subscription key not configured. Please configure your API key in Settings.',
				array( 'status' => 500 )
			);
		}

		// Build full URL with query params
		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		// Make request with proper headers
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'X-Version'                => '2',
					'Ocp-Apim-Subscription-Key' => $api_key,
					'Accept'                   => 'application/json',
				),
			)
		);

		// Handle errors
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_request_failed',
				'Failed to connect to API: ' . $response->get_error_message(),
				array( 'status' => 503 )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		// Handle HTTP errors
		if ( $status_code >= 400 ) {
			$error_message = $this->get_error_message_for_status( $status_code );
			return new WP_Error(
				'api_error',
				$error_message,
				array( 'status' => $status_code )
			);
		}

		// Parse JSON response
		$data = json_decode( $body, true );

		if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'invalid_json',
				'API returned invalid JSON',
				array( 'status' => 502 )
			);
		}

		// Return response with CORS headers
		$rest_response = new WP_REST_Response( $data, $status_code );

		// Add CORS headers for frontend access
		$rest_response->header( 'Access-Control-Allow-Origin', '*' );
		$rest_response->header( 'Access-Control-Allow-Methods', 'GET, OPTIONS' );
		$rest_response->header( 'Access-Control-Allow-Headers', 'Content-Type' );

		return $rest_response;
	}

	/**
	 * Get user-friendly error message for HTTP status code
	 *
	 * @param int $status_code HTTP status code.
	 * @return string
	 */
	private function get_error_message_for_status( int $status_code ): string {
		$messages = array(
			400 => 'Bad request - Invalid parameters provided',
			401 => 'Unauthorized - Invalid API subscription key',
			403 => 'Forbidden - Access denied',
			404 => 'Not found - Requested resource does not exist',
			429 => 'Rate limit exceeded - Too many requests (max 150 per 5 minutes)',
			500 => 'Internal server error - API is experiencing issues',
			502 => 'Bad gateway - API is temporarily unavailable',
			503 => 'Service unavailable - API is down for maintenance',
		);

		return $messages[ $status_code ] ?? "HTTP error $status_code";
	}
}
