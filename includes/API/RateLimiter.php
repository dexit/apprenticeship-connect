<?php
/**
 * Simple microsecond-based rate limiter.
 *
 * @package ApprenticeshipConnector\API
 */

namespace ApprenticeshipConnector\API;

class RateLimiter {

	private int $delay_us;
	private float $last_request = 0.0;

	/**
	 * @param int $delay_ms Minimum delay between requests in milliseconds (default 250).
	 */
	public function __construct( int $delay_ms = 250 ) {
		$this->delay_us = $delay_ms * 1000;
	}

	/**
	 * Wait if needed to honour the rate limit, then record the request time.
	 */
	public function throttle(): void {
		if ( $this->last_request > 0.0 ) {
			$elapsed_us = (int) ( ( microtime( true ) - $this->last_request ) * 1_000_000 );
			$remaining  = $this->delay_us - $elapsed_us;
			if ( $remaining > 0 ) {
				usleep( $remaining );
			}
		}
		$this->last_request = microtime( true );
	}
}
