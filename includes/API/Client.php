<?php
/**
 * Generic HTTP client wrapping wp_remote_*.
 *
 * @package ApprenticeshipConnector\API
 */

namespace ApprenticeshipConnector\API;

class Client {

	private readonly string $base_url;
	private readonly array  $default_headers;
	private readonly int    $timeout;

	public function __construct(
		string $base_url,
		array  $default_headers = [],
		int    $timeout         = 30
	) {
		$this->base_url        = rtrim( $base_url, '/' );
		$this->default_headers = $default_headers;
		$this->timeout         = $timeout;
	}

	/**
	 * HTTP GET.
	 *
	 * @param  string $path    Relative path.
	 * @param  array  $params  Query parameters.
	 * @return array{success:bool, status:int, data:mixed, error:string|null}
	 */
	public function get( string $path, array $params = [] ): array {
		$url = $this->base_url . '/' . ltrim( $path, '/' );

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get( $url, [
			'headers' => $this->default_headers,
			'timeout' => $this->timeout,
		] );

		return $this->parse_response( $response );
	}

	/**
	 * HTTP POST.
	 *
	 * @param  string $path    Relative path.
	 * @param  array  $body    Request body (will be JSON-encoded).
	 * @return array{success:bool, status:int, data:mixed, error:string|null}
	 */
	public function post( string $path, array $body = [] ): array {
		$url = $this->base_url . '/' . ltrim( $path, '/' );

		$response = wp_remote_post( $url, [
			'headers' => array_merge( $this->default_headers, [ 'Content-Type' => 'application/json' ] ),
			'body'    => wp_json_encode( $body ),
			'timeout' => $this->timeout,
		] );

		return $this->parse_response( $response );
	}

	// ── Private ────────────────────────────────────────────────────────────

	private function parse_response( \WP_Error|array $response ): array {
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'status'  => 0,
				'data'    => null,
				'error'   => $response->get_error_message(),
			];
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 ) {
			return [
				'success' => false,
				'status'  => $status,
				'data'    => $data,
				'error'   => sprintf( 'HTTP %d: %s', $status, wp_remote_retrieve_response_message( $response ) ),
			];
		}

		return [
			'success' => true,
			'status'  => $status,
			'data'    => $data,
			'error'   => null,
		];
	}
}
