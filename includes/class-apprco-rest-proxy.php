<?php
/**
 * REST Proxy
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_REST_Proxy {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'apprco/v1',
			'/proxy/vacancies',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'proxy_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function proxy_request( WP_REST_Request $request ): WP_REST_Response {
		$settings = Apprco_Settings_Manager::get_instance();
		$base_url = $settings->get( 'api', 'base_url' );
		$api_key  = $settings->get( 'api', 'subscription_key' );

		if ( empty( $api_key ) ) {
			return new WP_REST_Response( array( 'error' => 'API not configured' ), 500 );
		}

		$params   = $request->get_query_params();
		$url      = add_query_arg( $params, $base_url . '/vacancy' );
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Ocp-Apim-Subscription-Key' => $api_key,
					'X-Version'                 => '2',
					'Accept'                    => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( array( 'error' => $response->get_error_message() ), 500 );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return new WP_REST_Response( $body, 200 );
	}
}
