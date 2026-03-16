<?php
/**
 * UK Government Display Advert API v2 wrapper.
 *
 * @package ApprenticeshipConnector\API
 */

namespace ApprenticeshipConnector\API;

/**
 * Wraps the two endpoints used by the two-stage importer.
 *
 * Stage 1: GET /vacancy          – paginated list with basic info + vacancyReference.
 * Stage 2: GET /vacancy/{ref}    – full details for a single vacancy.
 */
class DisplayAdvertAPI {

	private readonly Client      $client;
	private readonly RateLimiter $rate_limiter;

	public function __construct(
		string      $base_url       = 'https://api.apprenticeships.education.gov.uk/vacancies',
		string      $subscription_key = '',
		int         $rate_limit_ms  = 250
	) {
		$this->client = new Client(
			$base_url,
			[
				'X-Version'              => '2',
				'Ocp-Apim-Subscription-Key' => $subscription_key,
				'Accept'                 => 'application/json',
			]
		);

		$this->rate_limiter = new RateLimiter( $rate_limit_ms );
	}

	// ── Stage 1 ────────────────────────────────────────────────────────────

	/**
	 * Fetch one page of the vacancy list.
	 *
	 * Accepted $params keys (all optional):
	 *   PageNumber, PageSize, Sort, Lat, Lon, DistanceInMiles,
	 *   Routes[], StandardLarsCode[], PostedInLastNumberOfDays,
	 *   FilterBySubscription, Ukprn
	 *
	 * @param  array $params Query parameters.
	 * @return array{success:bool, data:array|null, error:string|null}
	 */
	public function getVacancies( array $params = [] ): array {
		$this->rate_limiter->throttle();
		return $this->client->get( 'vacancy', $params );
	}

	// ── Stage 2 ────────────────────────────────────────────────────────────

	/**
	 * Fetch full details for a single vacancy reference.
	 *
	 * @param  string $vacancy_reference e.g. "VAC1234567890".
	 * @return array{success:bool, data:array|null, error:string|null}
	 */
	public function getVacancy( string $vacancy_reference ): array {
		$this->rate_limiter->throttle();
		return $this->client->get( 'vacancy/' . rawurlencode( $vacancy_reference ) );
	}

	// ── Factory ────────────────────────────────────────────────────────────

	/**
	 * Build from a stored ImportJob object.
	 */
	public static function from_job( object $job ): self {
		return new self(
			$job->api_base_url       ?? 'https://api.apprenticeships.education.gov.uk/vacancies',
			$job->api_subscription_key ?? '',
			$job->stage2_delay_ms    ?? 250
		);
	}
}
