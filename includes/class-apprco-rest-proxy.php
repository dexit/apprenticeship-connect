<?php
/**
 * REST API Proxy Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_REST_Proxy
 *
 * Provides a secure proxy for vacancy searches.
 */
class Apprco_REST_Proxy {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_REST_Proxy|null
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
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_proxy_routes' ) );
	}

	/**
	 * Registers proxy routes for the frontend.
	 *
	 * @return void
	 */
	public function register_proxy_routes(): void {
		register_rest_route(
			'apprco/v1',
			'/proxy/vacancies',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'proxy_vacancies' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Proxy request to the Apprenticeships API.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function proxy_vacancies( $request ): WP_REST_Response {
		$settings = Apprco_Settings_Manager::get_instance();
		$api_key  = $settings->get( 'api', 'subscription_key' );

		if ( ! $api_key ) {
			return new WP_REST_Response( array( 'error' => 'API not configured' ), 500 );
		}

		$params = $request->get_params();
		$client = new Apprco_API_Client( $settings->get( 'api', 'base_url' ) );
		$client->set_default_headers(
			array(
				'Ocp-Apim-Subscription-Key' => $api_key,
				'X-Version'                 => '2',
			)
		);

		$result = $client->get( '/vacancy', $params );

		return new WP_REST_Response( $result, 200 );
	}
}
