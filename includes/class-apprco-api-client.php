<?php
/**
 * API Client — resilient HTTP client with retry, exponential backoff,
 * rate-limit awareness, and full import logging.
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_API_Client
 *
 * Wraps wp_remote_get() with:
 *  - Up to MAX_RETRIES retries on transient failures (WP_Error, 429, 5xx).
 *  - Exponential backoff with ±20 % jitter between attempts.
 *  - Retry-After header respect on 429 responses.
 *  - Proactive rate-limit slowdown when x-ratelimit-remaining < 10.
 *  - Per-request logging via Apprco_Import_Logger when an import_id is set.
 */
class Apprco_API_Client {

	/** Maximum retry attempts per request (not counting the first try). */
	private const MAX_RETRIES = 5;

	/** Base delay in milliseconds for backoff calculation. */
	private const BASE_DELAY_MS = 1000;

	/** Hard ceiling on backoff delay in milliseconds. */
	private const MAX_DELAY_MS = 30000;

	/** HTTP status codes that trigger a retry. */
	private const RETRY_CODES = array( 429, 500, 502, 503, 504 );

	/** wp_remote_get() timeout in seconds. */
	private const REQUEST_TIMEOUT = 30;

	/** Base URL (no trailing slash). */
	private string $base_url;

	/** Default request headers. */
	private array $headers = array();

	/** Import run ID for log correlation (optional). */
	private ?string $import_id = null;

	/** Accumulated request statistics. */
	private array $stats;

	/**
	 * @param string $base_url API base URL.
	 */
	public function __construct( string $base_url ) {
		$this->base_url = rtrim( $base_url, '/' );
		$this->stats    = array(
			'requests'  => 0,
			'retries'   => 0,
			'errors'    => 0,
			'remaining' => null,
			'reset_at'  => null,
		);
	}

	/**
	 * Set default request headers (e.g. API subscription key).
	 *
	 * @param array $headers Key-value header map.
	 */
	public function set_default_headers( array $headers ): void {
		$this->headers = $headers;
	}

	/**
	 * Associate this client with an import run for log correlation.
	 *
	 * @param string $import_id UUID from Apprco_Import_Logger::start_import().
	 */
	public function set_import_id( string $import_id ): void {
		$this->import_id = $import_id;
	}

	/**
	 * Perform a GET request with automatic retry and backoff.
	 *
	 * @param string $endpoint Relative API endpoint.
	 * @param array  $params   Query parameters.
	 * @return array { success: bool, data?: mixed, error?: string, code?: int }
	 */
	public function get( string $endpoint, array $params = array() ): array {
		$url = $this->base_url . '/' . ltrim( $endpoint, '/' );
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		++$this->stats['requests'];
		$attempt = 0;

		do {
			if ( $attempt > 0 ) {
				++$this->stats['retries'];
				$this->log(
					'info',
					'api-client',
					sprintf( 'Retry %d/%d → %s', $attempt, self::MAX_RETRIES, $url )
				);
			}

			$response = wp_remote_get(
				$url,
				array(
					'headers' => $this->headers,
					'timeout' => self::REQUEST_TIMEOUT,
				)
			);

			// Transport-level error (DNS failure, timeout, etc.).
			if ( is_wp_error( $response ) ) {
				++$this->stats['errors'];
				$msg = $response->get_error_message();
				$this->log( 'error', 'api-client', sprintf( 'WP_Error on %s: %s', $url, $msg ) );

				if ( $attempt >= self::MAX_RETRIES ) {
					return array( 'success' => false, 'error' => $msg, 'code' => 0 );
				}

				$this->sleep_backoff( $attempt, null );
				++$attempt;
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			// Update rate-limit header stats.
			$remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
			$reset_at  = wp_remote_retrieve_header( $response, 'x-ratelimit-reset' );
			if ( '' !== $remaining ) {
				$this->stats['remaining'] = (int) $remaining;
			}
			if ( '' !== $reset_at ) {
				$this->stats['reset_at'] = $reset_at;
			}

			// Retryable HTTP error.
			if ( in_array( $code, self::RETRY_CODES, true ) ) {
				++$this->stats['errors'];
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
				$this->log(
					'warning',
					'api-client',
					sprintf(
						'HTTP %d on %s (Retry-After: %s) — attempt %d/%d',
						$code,
						$url,
						$retry_after ?: 'none',
						$attempt + 1,
						self::MAX_RETRIES + 1
					)
				);

				if ( $attempt >= self::MAX_RETRIES ) {
					return array(
						'success' => false,
						'error'   => sprintf( 'HTTP %d after %d retries', $code, self::MAX_RETRIES ),
						'code'    => $code,
					);
				}

				$this->sleep_backoff( $attempt, $retry_after ?: null );
				++$attempt;
				continue;
			}

			// Non-retryable client error.
			if ( $code >= 400 ) {
				++$this->stats['errors'];
				$error_msg = $body['message'] ?? $body['error'] ?? "HTTP {$code}";
				$this->log( 'error', 'api-client', sprintf( 'HTTP %d on %s: %s', $code, $url, $error_msg ) );
				return array( 'success' => false, 'error' => $error_msg, 'code' => $code );
			}

			// Success.
			$this->log(
				'debug',
				'api-client',
				sprintf(
					'GET %s → %d (rate-limit remaining: %s)',
					$url,
					$code,
					$this->stats['remaining'] ?? 'n/a'
				)
			);

			return array( 'success' => true, 'data' => $body, 'code' => $code );

		} while ( true );
	}

	/**
	 * Fetch all pages of a paginated API endpoint.
	 *
	 * Automatically adjusts inter-page delay when rate-limit remaining is low.
	 *
	 * @param string $endpoint   Relative API endpoint.
	 * @param array  $params     Base query parameters (merged with page param).
	 * @param string $page_param Query param name for the page number.
	 * @param string $data_path  Key in the response body that holds the items array.
	 * @param string $total_path Key in the response body that holds the total count.
	 * @param int    $max_pages  Stop after this many pages (0 = no limit).
	 * @return array { success: bool, items?: array, total?: int, error?: string }
	 */
	public function fetch_all_pages(
		string $endpoint,
		array $params,
		string $page_param,
		string $data_path,
		string $total_path,
		int $max_pages = 0
	): array {
		$items      = array();
		$page       = 1;
		$total_seen = 0;

		$this->log( 'info', 'api-client', sprintf(
			'Starting paginated fetch of %s (max_pages=%d)',
			$endpoint,
			$max_pages
		) );

		while ( true ) {
			$params[ $page_param ] = $page;
			$res                   = $this->get( $endpoint, $params );

			if ( ! $res['success'] ) {
				$this->log( 'error', 'api-client', sprintf(
					'Pagination stopped at page %d: %s',
					$page,
					$res['error'] ?? 'unknown error'
				) );
				return $res;
			}

			$data       = $res['data'] ?? array();
			$page_items = (array) ( $data[ $data_path ] ?? array() );
			$items      = array_merge( $items, $page_items );
			$total_seen += count( $page_items );

			$total_reported = isset( $data[ $total_path ] ) ? (int) $data[ $total_path ] : null;

			$this->log( 'info', 'api-client', sprintf(
				'Page %d: %d items (running total: %d%s)',
				$page,
				count( $page_items ),
				$total_seen,
				null !== $total_reported ? ' / ' . $total_reported : ''
			) );

			if ( empty( $page_items ) ) {
				break;
			}
			if ( $max_pages > 0 && $page >= $max_pages ) {
				$this->log( 'info', 'api-client', sprintf( 'Reached max_pages limit (%d)', $max_pages ) );
				break;
			}

			++$page;

			// Adaptive delay: slow down when rate-limit headroom is thin.
			$remaining = $this->stats['remaining'];
			if ( null !== $remaining && $remaining < 10 ) {
				$this->log( 'warning', 'api-client', sprintf(
					'Rate-limit headroom low (%d remaining) — pausing 5 s before page %d',
					$remaining,
					$page
				) );
				sleep( 5 );
			} elseif ( null !== $remaining && $remaining < 50 ) {
				usleep( 1500000 ); // 1.5 s when getting close.
			} else {
				usleep( 500000 ); // 0.5 s default polite delay.
			}
		}

		$this->log( 'info', 'api-client', sprintf(
			'Pagination complete — %d total items across %d pages',
			$total_seen,
			$page
		) );

		return array( 'success' => true, 'items' => $items, 'total' => $total_seen );
	}

	/**
	 * Return accumulated request statistics.
	 *
	 * @return array
	 */
	public function get_stats(): array {
		return $this->stats;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Sleep for the appropriate backoff duration.
	 *
	 * Uses the Retry-After value when provided (HTTP 429), otherwise
	 * exponential backoff with ±20 % random jitter.
	 *
	 * @param int         $attempt      Zero-based attempt index.
	 * @param string|null $retry_after  Retry-After header value (seconds) or null.
	 */
	private function sleep_backoff( int $attempt, ?string $retry_after ): void {
		if ( null !== $retry_after && is_numeric( $retry_after ) && (int) $retry_after > 0 ) {
			$delay_ms = min( (int) $retry_after * 1000, self::MAX_DELAY_MS );
		} else {
			$delay_ms = min( self::BASE_DELAY_MS * (int) pow( 2, $attempt ), self::MAX_DELAY_MS );
			// Add ±20 % jitter to spread concurrent retries.
			$jitter   = (int) ( $delay_ms * 0.2 );
			$delay_ms = $delay_ms + wp_rand( -$jitter, $jitter );
		}

		$this->log( 'info', 'api-client', sprintf( 'Backoff: sleeping %d ms before retry', $delay_ms ) );
		usleep( max( 0, $delay_ms ) * 1000 );
	}

	/**
	 * Write a log entry if an import_id has been set.
	 *
	 * @param string $level     Log level (debug, info, warning, error).
	 * @param string $component Component identifier.
	 * @param string $message   Human-readable message.
	 */
	private function log( string $level, string $component, string $message ): void {
		if ( $this->import_id && class_exists( 'Apprco_Import_Logger' ) ) {
			Apprco_Import_Logger::get_instance()->log(
				$this->import_id,
				$level,
				$component,
				$message
			);
		}
	}
}
