<?php
/**
 * API Client Class - V3.1.0
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_API_Client
 *
 * Handles requests to the Apprenticeships API.
 */
class Apprco_API_Client {

	/**
	 * Base URL for the API.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Default headers for requests.
	 *
	 * @var array
	 */
	private $headers = array();

	/**
	 * Current import run ID.
	 *
	 * @var string|null
	 */
	private $import_id = null;

	/**
	 * Request statistics.
	 *
	 * @var array
	 */
	private $stats = array();

	/**
	 * Constructor.
	 *
	 * @param string $base_url API base URL.
	 */
	public function __construct( string $base_url ) {
		$this->base_url = rtrim( $base_url, '/' );
		$this->stats    = array(
			'requests'  => 0,
			'retries'   => 0,
			'remaining' => null,
			'reset_at'  => null,
		);
	}

	/**
	 * Set default headers.
	 *
	 * @param array $headers Headers array.
	 * @return void
	 */
	public function set_default_headers( array $headers ): void {
		$this->headers = $headers;
	}

	/**
	 * Set current import ID.
	 *
	 * @param string $import_id Import ID.
	 * @return void
	 */
	public function set_import_id( string $import_id ): void {
		$this->import_id = $import_id;
	}

	/**
	 * Perform a GET request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $params   Query parameters.
	 * @return array
	 */
	public function get( string $endpoint, array $params = array() ): array {
		$url = $this->base_url . '/' . ltrim( $endpoint, '/' );
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		++$this->stats['requests'];

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $this->headers,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->stats['remaining'] = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
		$this->stats['reset_at']  = wp_remote_retrieve_header( $response, 'x-ratelimit-reset' );

		if ( 429 === (int) $code ) {
			return array(
				'success' => false,
				'error'   => 'Rate limit exceeded',
				'code'    => 429,
			);
		}

		if ( $code >= 400 ) {
			return array(
				'success' => false,
				'error'   => isset( $body['message'] ) ? $body['message'] : 'API Error',
				'code'    => $code,
			);
		}

		return array(
			'success' => true,
			'data'    => $body,
		);
	}

	/**
	 * Fetch all pages for an endpoint.
	 *
	 * @param string $endpoint   API endpoint.
	 * @param array  $params     Query parameters.
	 * @param string $page_param Parameter name for page number.
	 * @param string $data_path  Path in response JSON for items array.
	 * @param string $total_path Path in response JSON for total count.
	 * @param int    $max_pages  Maximum pages to fetch (0 for all).
	 * @return array
	 */
	public function fetch_all_pages( string $endpoint, array $params, string $page_param, string $data_path, string $total_path, int $max_pages = 0 ): array {
		$items = array();
		$page  = 1;

		while ( true ) {
			$params[ $page_param ] = $page;
			$res                   = $this->get( $endpoint, $params );

			if ( ! $res['success'] ) {
				return $res;
			}

			$data       = $res['data'];
			$page_items = isset( $data[ $data_path ] ) ? $data[ $data_path ] : array();
			$items      = array_merge( $items, $page_items );

			if ( empty( $page_items ) ) {
				break;
			}
			if ( $max_pages > 0 && $page >= $max_pages ) {
				break;
			}

			++$page;
			usleep( 500000 ); // 0.5s delay to be polite to API.
		}

		return array(
			'success' => true,
			'items'   => $items,
		);
	}

	/**
	 * Get current request statistics.
	 *
	 * @return array
	 */
	public function get_stats(): array {
		return $this->stats;
	}
}
