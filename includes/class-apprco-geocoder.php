<?php
/**
 * Geocoder — Forward + Reverse geocoding with caching
 *
 * Three resolution paths:
 *  1. Forward (postcode → lat/lng): postcodes.io — UK-specific, no key needed.
 *  2. Forward fallback (address → lat/lng): OSM Nominatim.
 *  3. Reverse (lat/lng → friendly string): OSM Nominatim "{town}, {county}".
 *
 * All results cached as WP transients (7-day TTL). Action Scheduler
 * integration via enqueue_for_vacancy() for non-blocking async geocoding.
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Geocoder
 */
class Apprco_Geocoder {

	private const POSTCODES_IO = 'https://api.postcodes.io';
	private const NOMINATIM    = 'https://nominatim.openstreetmap.org';
	private const CACHE_TTL    = 7 * DAY_IN_SECONDS;
	private const AS_HOOK      = 'apprco_geocode_vacancy';

	/** @var self|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( self::AS_HOOK, array( $this, 'process_async_vacancy' ), 10, 1 );
	}

	// ── Forward: postcode → lat/lng/town/county ─────────────────────────────

	/**
	 * Resolve a UK postcode to coordinates + district names.
	 * Tries postcodes.io first, falls back to Nominatim.
	 *
	 * @param string $postcode Raw postcode.
	 * @return array|null { lat, lng, town, county }
	 */
	public static function forward( string $postcode ): ?array {
		$postcode = strtoupper( preg_replace( '/\s+/', '', trim( $postcode ) ) );
		if ( empty( $postcode ) ) {
			return null;
		}

		$key    = 'apprco_geo_fwd_' . md5( $postcode );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$result = self::postcodes_io_lookup( $postcode )
			?? self::nominatim_forward( $postcode . ', UK' );

		set_transient( $key, $result ?? '', self::CACHE_TTL );
		return $result;
	}

	/**
	 * Bulk forward geocode (uses postcodes.io batch endpoint).
	 *
	 * @param string[] $postcodes Array of postcodes.
	 * @return array Normalised_postcode → result array (null on failure).
	 */
	public static function forward_bulk( array $postcodes ): array {
		$results  = array();
		$to_fetch = array();

		foreach ( $postcodes as $pc ) {
			$n = strtoupper( preg_replace( '/\s+/', '', trim( $pc ) ) );
			if ( empty( $n ) ) {
				continue;
			}
			$cached = get_transient( 'apprco_geo_fwd_' . md5( $n ) );
			if ( false !== $cached ) {
				$results[ $n ] = $cached ?: null;
			} else {
				$to_fetch[] = $n;
			}
		}

		foreach ( array_chunk( array_unique( $to_fetch ), 100 ) as $chunk ) {
			$response = wp_remote_post(
				self::POSTCODES_IO . '/postcodes',
				array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( array( 'postcodes' => $chunk ) ),
					'timeout' => 15,
				)
			);
			if ( is_wp_error( $response ) ) {
				continue;
			}
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			foreach ( (array) ( $body['result'] ?? array() ) as $item ) {
				$n   = strtoupper( preg_replace( '/\s+/', '', $item['query'] ?? '' ) );
				$res = is_array( $item['result'] ?? null )
					? self::extract_postcodes_io( $item['result'] )
					: null;
				$results[ $n ] = $res;
				set_transient( 'apprco_geo_fwd_' . md5( $n ), $res ?? '', self::CACHE_TTL );
			}
		}

		return $results;
	}

	// ── Reverse: lat/lng → friendly location ────────────────────────────────

	/**
	 * Resolve lat/lng to "{town}, {county}" string.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return string|null E.g. "Leeds, West Yorkshire".
	 */
	public static function reverse( float $lat, float $lng ): ?string {
		$key    = 'apprco_geo_rev_' . md5( sprintf( '%.5f,%.5f', $lat, $lng ) );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached ?: null;
		}
		$result = self::nominatim_reverse( $lat, $lng );
		set_transient( $key, $result ?? '', self::CACHE_TTL );
		return $result;
	}

	/**
	 * Full reverse geocode — returns address components including postcode.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array|null { display, town, county, postcode, country }
	 */
	public static function reverse_full( float $lat, float $lng ): ?array {
		$key    = 'apprco_geo_revf_' . md5( sprintf( '%.5f,%.5f', $lat, $lng ) );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$url  = add_query_arg(
			array(
				'lat'            => $lat,
				'lon'            => $lng,
				'format'         => 'jsonv2',
				'addressdetails' => 1,
				'zoom'           => 14,
			),
			self::NOMINATIM . '/reverse'
		);
		$data = self::nominatim_request( $url );
		if ( null === $data || empty( $data['address'] ) ) {
			set_transient( $key, '', self::CACHE_TTL );
			return null;
		}

		$addr   = $data['address'];
		$town   = $addr['town'] ?? $addr['city'] ?? $addr['village'] ?? $addr['municipality'] ?? '';
		$county = $addr['county'] ?? $addr['state_district'] ?? $addr['state'] ?? '';
		$pc     = isset( $addr['postcode'] )
			? strtoupper( preg_replace( '/\s+/', '', $addr['postcode'] ) )
			: null;

		$result = array(
			'display'  => trim( $town . ( $county ? ', ' . $county : '' ) ),
			'town'     => $town,
			'county'   => $county,
			'postcode' => $pc,
			'country'  => $addr['country'] ?? '',
		);

		set_transient( $key, $result, self::CACHE_TTL );
		return $result;
	}

	// ── Async (Action Scheduler) ─────────────────────────────────────────────

	/**
	 * Enqueue an async geocode job for a vacancy reference + postcode.
	 * Used after Stage 2 deep-fetch when the vacancy now has a postcode.
	 *
	 * @param string $ref      Vacancy reference.
	 * @param string $postcode Postcode to resolve.
	 * @return void
	 */
	public static function enqueue_for_vacancy( string $ref, string $postcode ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				self::AS_HOOK,
				array( array( 'ref' => $ref, 'postcode' => $postcode ) ),
				'apprco-geocode'
			);
		}
	}

	/**
	 * Execute an async geocode job (Action Scheduler callback).
	 *
	 * @param array $args { ref: string, postcode: string }
	 * @return void
	 */
	public function process_async_vacancy( $args ): void {
		$ref      = $args['ref'] ?? '';
		$postcode = $args['postcode'] ?? '';
		if ( empty( $ref ) || empty( $postcode ) ) {
			return;
		}

		$coords = self::forward( $postcode );
		if ( null === $coords ) {
			return;
		}

		// Update vacancy store row.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'apprco_vacancies',
			array(
				'lat'        => $coords['lat'],
				'lng'        => $coords['lng'],
				'town'       => $coords['town'] ?? '',
				'county'     => $coords['county'] ?? '',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'vacancy_reference' => $ref ),
			array( '%f', '%f', '%s', '%s', '%s' ),
			array( '%s' )
		);

		// Update CPT post meta if the vacancy exists as a post.
		$posts = get_posts(
			array(
				'post_type'      => 'apprco_vacancy',
				'meta_key'       => '_apprco_vacancy_reference',
				'meta_value'     => $ref,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! empty( $posts ) ) {
			update_post_meta( $posts[0], '_apprco_lat', $coords['lat'] );
			update_post_meta( $posts[0], '_apprco_lng', $coords['lng'] );
			update_post_meta( $posts[0], '_apprco_town', $coords['town'] ?? '' );
			update_post_meta( $posts[0], '_apprco_county', $coords['county'] ?? '' );
		}
	}

	// ── Internal helpers ─────────────────────────────────────────────────────

	/** @param string $postcode Normalised postcode. */
	private static function postcodes_io_lookup( string $postcode ): ?array {
		$response = wp_remote_get(
			self::POSTCODES_IO . '/postcodes/' . rawurlencode( $postcode ),
			array( 'timeout' => 10 )
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body['result'] ?? null ) ? self::extract_postcodes_io( $body['result'] ) : null;
	}

	/** @param array $r postcodes.io result object. */
	private static function extract_postcodes_io( array $r ): ?array {
		if ( empty( $r['latitude'] ) || empty( $r['longitude'] ) ) {
			return null;
		}
		return array(
			'lat'    => (float) $r['latitude'],
			'lng'    => (float) $r['longitude'],
			'town'   => $r['admin_district'] ?? $r['parish'] ?? '',
			'county' => $r['admin_county'] ?? $r['region'] ?? '',
		);
	}

	/** @param string $query Address query. */
	private static function nominatim_forward( string $query ): ?array {
		$data = self::nominatim_request(
			add_query_arg(
				array(
					'q'              => $query,
					'format'         => 'jsonv2',
					'addressdetails' => 1,
					'limit'          => 1,
					'countrycodes'   => 'gb',
				),
				self::NOMINATIM . '/search'
			)
		);
		if ( empty( $data[0] ) ) {
			return null;
		}
		$item = $data[0];
		$addr = $item['address'] ?? array();
		return array(
			'lat'    => (float) $item['lat'],
			'lng'    => (float) $item['lon'],
			'town'   => $addr['town'] ?? $addr['city'] ?? $addr['village'] ?? '',
			'county' => $addr['county'] ?? $addr['state_district'] ?? '',
		);
	}

	private static function nominatim_reverse( float $lat, float $lng ): ?string {
		$data = self::nominatim_request(
			add_query_arg(
				array(
					'lat'            => $lat,
					'lon'            => $lng,
					'format'         => 'jsonv2',
					'addressdetails' => 1,
					'zoom'           => 14,
				),
				self::NOMINATIM . '/reverse'
			)
		);
		if ( null === $data || empty( $data['address'] ) ) {
			return null;
		}
		$addr   = $data['address'];
		$town   = $addr['town'] ?? $addr['city'] ?? $addr['village'] ?? $addr['municipality'] ?? '';
		$county = $addr['county'] ?? $addr['state_district'] ?? $addr['state'] ?? '';
		return trim( $town . ( $county ? ', ' . $county : '' ) ) ?: null;
	}

	/**
	 * Execute a Nominatim HTTP request.
	 * Adds required User-Agent (Nominatim policy).
	 *
	 * @param string $url Full URL with query params.
	 * @return array|null Decoded JSON.
	 */
	private static function nominatim_request( string $url ): ?array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'User-Agent' => 'ApprenticeshipConnect/3.2.0 (' . get_bloginfo( 'url' ) . '; geocoding)',
					'Accept'     => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
	}

	// ── Legacy static shim ───────────────────────────────────────────────────

	/**
	 * Legacy entry point kept for backwards compatibility.
	 *
	 * @param string $postcode The postcode.
	 * @return array|null { lat, lng }
	 */
	public static function get_coordinates( string $postcode ): ?array {
		$r = self::forward( $postcode );
		return $r ? array( 'lat' => $r['lat'], 'lng' => $r['lng'] ) : null;
	}
}
