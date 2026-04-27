<?php
/**
 * Generic HTTP client wrapping wp_remote_*.
 *
 * Automatically retries requests that receive HTTP 429 (Too Many Requests),
 * honouring the Retry-After response header when present.
 *
 * @package ApprenticeshipConnector\API
 */

namespace ApprenticeshipConnector\API;

class Client {

	private readonly string $base_url;
	private readonly array  $default_headers;
	private readonly int    $timeout;

	/** Maximum number of retry attempts on HTTP 429. */
	private const MAX_RETRIES = 3;

	/** Base back-off in seconds; doubles on each retry: 5 s, 10 s, 20 s. */
	private const BACKOFF_BASE_S = 5;

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

		return $this->request_with_retry( 'GET', $url );
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

		return $this->request_with_retry( 'POST', $url, $body );
	}

	// ── Private ────────────────────────────────────────────────────────────

	/**
	 * Execute an HTTP request, retrying up to MAX_RETRIES times on HTTP 429.
	 */
	private function request_with_retry( string $method, string $url, array $body = [] ): array {
		$attempt = 0;

		do {
			$response = $this->do_request( $method, $url, $body );

			if ( $response['status'] !== 429 || $attempt >= self::MAX_RETRIES ) {
				// Success, non-retryable error, or exhausted retries.
				unset( $response['retry_after'] ); // Strip internal field from public return.
				return $response;
			}

			// 429: honour Retry-After header or fall back to exponential back-off.
			$retry_after = (int) ( $response['retry_after'] ?? 0 );
			$backoff     = $retry_after > 0
				? $retry_after
				: self::BACKOFF_BASE_S * ( 2 ** $attempt ); // 5 s, 10 s, 20 s

			sleep( $backoff );
			$attempt++;

		} while ( $attempt <= self::MAX_RETRIES );

		unset( $response['retry_after'] );
		return $response;
	}

	private function do_request( string $method, string $url, array $body = [] ): array {
		$args = [
			'headers' => $this->default_headers,
			'timeout' => $this->timeout,
		];

		if ( $method === 'POST' ) {
			$args['headers'] = array_merge( $args['headers'], [ 'Content-Type' => 'application/json' ] );
			$args['body']    = wp_json_encode( $body );
		}

		$raw = ( $method === 'POST' )
			? wp_remote_post( $url, $args )
			: wp_remote_get( $url, $args );

		return $this->parse_response( $raw );
	}

	private function parse_response( \WP_Error|array $response ): array {
		if ( is_wp_error( $response ) ) {
			return [
				'success'     => false,
				'status'      => 0,
				'data'        => null,
				'error'       => $response->get_error_message(),
				'retry_after' => null,
			];
		}

		$status      = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );
		$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );

		if ( $status < 200 || $status >= 300 ) {
			return [
				'success'     => false,
				'status'      => $status,
				'data'        => $data,
				'error'       => sprintf( 'HTTP %d: %s', $status, wp_remote_retrieve_response_message( $response ) ),
				'retry_after' => $retry_after !== '' ? (int) $retry_after : null,
			];
		}

		return [
			'success'     => true,
			'status'      => $status,
			'data'        => $data,
			'error'       => null,
			'retry_after' => null,
		];
	}
}
