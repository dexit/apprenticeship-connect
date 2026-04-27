<?php
/**
 * Persistent cross-process rate limiter backed by WordPress transients.
 *
 * The API allows 150 requests per 5-minute window.
 * Default delay = 2 000 ms (0.5 req/s = 30 req/min) keeps us safely within cap.
 *
 * State is stored in transients so it survives between Action Scheduler
 * invocations (each of which runs in its own PHP process).
 *
 * @package ApprenticeshipConnector\API
 */

namespace ApprenticeshipConnector\API;

class RateLimiter {

	/**
	 * Transient key storing the float microtime of the last API request.
	 * Shared across all processes so AS batches respect the same clock.
	 */
	private const LAST_REQUEST_KEY = 'appcon_last_api_request';

	/**
	 * Transient key tracking requests in the current 5-minute window.
	 * Format: ['count' => int, 'window_start' => float]
	 */
	private const WINDOW_KEY = 'appcon_api_window';

	/** Pause when we have used this many requests in a 5-min window (10 headroom). */
	private const WINDOW_CAP = 140;

	/** Window duration in seconds (API uses a 5-minute sliding window). */
	private const WINDOW_SECONDS = 300;

	private int $delay_us;

	/**
	 * @param int $delay_ms Minimum milliseconds between requests.
	 *                      Default 2 000 ms keeps throughput at 30 req/min,
	 *                      safely within the 150 req/5-min API cap.
	 */
	public function __construct( int $delay_ms = 2000 ) {
		$this->delay_us = $delay_ms * 1000;
	}

	/**
	 * Enforce the per-request delay AND the sliding-window cap.
	 *
	 * Blocks via usleep/sleep until it is safe to fire the next request, then
	 * updates persistent state so that subsequent processes honour the same clock.
	 */
	public function throttle(): void {
		// ── 1. Per-request minimum delay ──────────────────────────────────
		$last = (float) get_transient( self::LAST_REQUEST_KEY );
		if ( $last > 0.0 ) {
			$elapsed_us = (int) ( ( microtime( true ) - $last ) * 1_000_000 );
			$remaining  = $this->delay_us - $elapsed_us;
			if ( $remaining > 0 ) {
				usleep( $remaining );
			}
		}

		// ── 2. Sliding-window cap (150 req / 5 min) ────────────────────────
		$window = get_transient( self::WINDOW_KEY );
		if ( ! is_array( $window ) ) {
			$window = [ 'count' => 0, 'window_start' => microtime( true ) ];
		}

		$window_age = microtime( true ) - (float) $window['window_start'];

		if ( $window_age >= self::WINDOW_SECONDS ) {
			// Current window has expired – start fresh.
			$window = [ 'count' => 0, 'window_start' => microtime( true ) ];
		} elseif ( (int) $window['count'] >= self::WINDOW_CAP ) {
			// Approaching the hard cap – pause until the window resets.
			$wait_s = (int) ceil( self::WINDOW_SECONDS - $window_age ) + 1;
			sleep( $wait_s );
			$window = [ 'count' => 0, 'window_start' => microtime( true ) ];
		}

		// ── 3. Record this request ─────────────────────────────────────────
		$now = microtime( true );
		set_transient( self::LAST_REQUEST_KEY, $now, 120 ); // 2-min TTL keeps stale data from blocking next run.

		$window['count'] = (int) $window['count'] + 1;
		set_transient( self::WINDOW_KEY, $window, self::WINDOW_SECONDS + 60 );
	}
}
