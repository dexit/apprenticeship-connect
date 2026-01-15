<?php
/**
 * Geocoder - OpenStreetMap Nominatim integration for postcode lookups
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Apprco_Geocoder
 *
 * Provides geocoding functionality using OpenStreetMap Nominatim API.
 * Supports forward geocoding (postcode → lat/long) and reverse geocoding.
 * Includes caching and rate limiting to comply with OSM usage policy.
 */
class Apprco_Geocoder {

    /**
     * Nominatim API base URL
     *
     * @var string
     */
    private const API_URL = 'https://nominatim.openstreetmap.org';

    /**
     * Cache duration in seconds (7 days - postcodes rarely change)
     *
     * @var int
     */
    private const CACHE_DURATION = 604800;

    /**
     * Rate limit delay in milliseconds (OSM requires max 1 request/second)
     *
     * @var int
     */
    private const RATE_LIMIT_DELAY = 1100;

    /**
     * Singleton instance
     *
     * @var Apprco_Geocoder|null
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger
     */
    private $logger;

    /**
     * Last request timestamp for rate limiting
     *
     * @var float
     */
    private $last_request_time = 0;

    /**
     * Request counter for stats
     *
     * @var int
     */
    private $request_count = 0;

    /**
     * Cache hits counter
     *
     * @var int
     */
    private $cache_hits = 0;

    /**
     * User agent for API requests (required by Nominatim)
     *
     * @var string
     */
    private $user_agent;

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->logger     = Apprco_Import_Logger::get_instance();
        $this->user_agent = 'ApprenticeshipConnect/' . APPRCO_PLUGIN_VERSION . ' (WordPress Plugin; ' . get_site_url() . ')';
    }

    /**
     * Get singleton instance
     *
     * @return Apprco_Geocoder
     */
    public static function get_instance(): Apprco_Geocoder {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Geocode a UK postcode to latitude/longitude
     *
     * @param string $postcode UK postcode.
     * @return array{success: bool, latitude?: float, longitude?: float, display_name?: string, error?: string}
     */
    public function geocode_postcode( string $postcode ): array {
        // Normalize postcode
        $postcode = $this->normalize_postcode( $postcode );

        if ( empty( $postcode ) ) {
            return array(
                'success' => false,
                'error'   => 'Invalid postcode format.',
            );
        }

        // Check cache first
        $cache_key = $this->get_cache_key( 'forward', $postcode );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            $this->cache_hits++;
            $this->logger->trace( sprintf( 'Geocode cache hit for postcode: %s', $postcode ), null, 'geocoder' );

            return array(
                'success'      => true,
                'latitude'     => $cached['latitude'],
                'longitude'    => $cached['longitude'],
                'display_name' => $cached['display_name'] ?? '',
                'cached'       => true,
            );
        }

        // Apply rate limiting
        $this->apply_rate_limit();

        // Build search query
        $params = array(
            'format'         => 'json',
            'postalcode'     => $postcode,
            'countrycodes'   => 'gb',
            'addressdetails' => 1,
            'limit'          => 1,
        );

        $result = $this->make_request( '/search', $params );

        if ( ! $result['success'] ) {
            return $result;
        }

        $data = $result['data'];

        if ( empty( $data ) || ! is_array( $data ) || count( $data ) === 0 ) {
            $this->logger->debug( sprintf( 'No results for postcode: %s', $postcode ), null, 'geocoder' );

            return array(
                'success' => false,
                'error'   => 'Postcode not found.',
            );
        }

        $location = $data[0];
        $lat      = isset( $location['lat'] ) ? (float) $location['lat'] : null;
        $lon      = isset( $location['lon'] ) ? (float) $location['lon'] : null;

        if ( null === $lat || null === $lon ) {
            return array(
                'success' => false,
                'error'   => 'Invalid coordinates in response.',
            );
        }

        // Cache the result
        $cache_data = array(
            'latitude'     => $lat,
            'longitude'    => $lon,
            'display_name' => $location['display_name'] ?? '',
        );
        set_transient( $cache_key, $cache_data, self::CACHE_DURATION );

        $this->logger->debug( sprintf( 'Geocoded postcode %s to [%.6f, %.6f]', $postcode, $lat, $lon ), null, 'geocoder' );

        return array(
            'success'      => true,
            'latitude'     => $lat,
            'longitude'    => $lon,
            'display_name' => $location['display_name'] ?? '',
            'cached'       => false,
        );
    }

    /**
     * Reverse geocode latitude/longitude to address
     *
     * @param float $latitude  Latitude.
     * @param float $longitude Longitude.
     * @return array{success: bool, postcode?: string, address?: array, display_name?: string, error?: string}
     */
    public function reverse_geocode( float $latitude, float $longitude ): array {
        // Validate coordinates
        if ( $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
            return array(
                'success' => false,
                'error'   => 'Invalid coordinates.',
            );
        }

        // Check cache first
        $coord_key = sprintf( '%.6f_%.6f', $latitude, $longitude );
        $cache_key = $this->get_cache_key( 'reverse', $coord_key );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            $this->cache_hits++;
            $this->logger->trace( sprintf( 'Reverse geocode cache hit for [%.6f, %.6f]', $latitude, $longitude ), null, 'geocoder' );

            return array_merge( array( 'success' => true, 'cached' => true ), $cached );
        }

        // Apply rate limiting
        $this->apply_rate_limit();

        // Build reverse geocode query
        $params = array(
            'format'         => 'json',
            'lat'            => $latitude,
            'lon'            => $longitude,
            'addressdetails' => 1,
        );

        $result = $this->make_request( '/reverse', $params );

        if ( ! $result['success'] ) {
            return $result;
        }

        $data = $result['data'];

        if ( empty( $data ) || isset( $data['error'] ) ) {
            return array(
                'success' => false,
                'error'   => $data['error'] ?? 'Location not found.',
            );
        }

        // Extract address components
        $address_parts = $data['address'] ?? array();
        $postcode      = $address_parts['postcode'] ?? '';

        $address = array(
            'house_number' => $address_parts['house_number'] ?? '',
            'road'         => $address_parts['road'] ?? '',
            'suburb'       => $address_parts['suburb'] ?? '',
            'city'         => $address_parts['city'] ?? $address_parts['town'] ?? $address_parts['village'] ?? '',
            'county'       => $address_parts['county'] ?? '',
            'state'        => $address_parts['state'] ?? '',
            'postcode'     => $postcode,
            'country'      => $address_parts['country'] ?? '',
            'country_code' => $address_parts['country_code'] ?? '',
        );

        // Cache the result
        $cache_data = array(
            'postcode'     => $postcode,
            'address'      => $address,
            'display_name' => $data['display_name'] ?? '',
        );
        set_transient( $cache_key, $cache_data, self::CACHE_DURATION );

        $this->logger->debug( sprintf( 'Reverse geocoded [%.6f, %.6f] to %s', $latitude, $longitude, $postcode ), null, 'geocoder' );

        return array(
            'success'      => true,
            'postcode'     => $postcode,
            'address'      => $address,
            'display_name' => $data['display_name'] ?? '',
            'cached'       => false,
        );
    }

    /**
     * Geocode a full address string
     *
     * @param string $address  Full address string.
     * @param string $postcode Optional postcode to improve accuracy.
     * @return array{success: bool, latitude?: float, longitude?: float, display_name?: string, error?: string}
     */
    public function geocode_address( string $address, string $postcode = '' ): array {
        $query = trim( $address );

        if ( ! empty( $postcode ) ) {
            $query .= ', ' . $this->normalize_postcode( $postcode );
        }

        if ( empty( $query ) ) {
            return array(
                'success' => false,
                'error'   => 'Empty address.',
            );
        }

        // Check cache first
        $cache_key = $this->get_cache_key( 'address', md5( $query ) );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            $this->cache_hits++;
            return array_merge( array( 'success' => true, 'cached' => true ), $cached );
        }

        // Apply rate limiting
        $this->apply_rate_limit();

        $params = array(
            'format'         => 'json',
            'q'              => $query,
            'countrycodes'   => 'gb',
            'addressdetails' => 1,
            'limit'          => 1,
        );

        $result = $this->make_request( '/search', $params );

        if ( ! $result['success'] ) {
            return $result;
        }

        $data = $result['data'];

        if ( empty( $data ) ) {
            return array(
                'success' => false,
                'error'   => 'Address not found.',
            );
        }

        $location = $data[0];
        $lat      = (float) $location['lat'];
        $lon      = (float) $location['lon'];

        // Cache the result
        $cache_data = array(
            'latitude'     => $lat,
            'longitude'    => $lon,
            'display_name' => $location['display_name'] ?? '',
        );
        set_transient( $cache_key, $cache_data, self::CACHE_DURATION );

        return array(
            'success'      => true,
            'latitude'     => $lat,
            'longitude'    => $lon,
            'display_name' => $location['display_name'] ?? '',
            'cached'       => false,
        );
    }

    /**
     * Batch geocode multiple postcodes
     *
     * Respects rate limiting between requests.
     *
     * @param array    $postcodes   Array of postcodes.
     * @param callable $on_progress Optional progress callback(index, total, postcode, result).
     * @return array Array of results keyed by postcode.
     */
    public function batch_geocode( array $postcodes, ?callable $on_progress = null ): array {
        $results = array();
        $total   = count( $postcodes );

        $this->logger->info( sprintf( 'Starting batch geocode for %d postcodes', $total ), null, 'geocoder' );

        foreach ( $postcodes as $index => $postcode ) {
            $result = $this->geocode_postcode( $postcode );
            $results[ $postcode ] = $result;

            if ( is_callable( $on_progress ) ) {
                call_user_func( $on_progress, $index + 1, $total, $postcode, $result );
            }
        }

        $success_count = count( array_filter( $results, function ( $r ) {
            return $r['success'] ?? false;
        } ) );

        $this->logger->info( sprintf( 'Batch geocode complete: %d/%d successful', $success_count, $total ), null, 'geocoder' );

        return $results;
    }

    /**
     * Enrich vacancy data with geocoded coordinates
     *
     * @param array $vacancy Vacancy data with postcode.
     * @return array Vacancy data with added lat/long if available.
     */
    public function enrich_vacancy( array $vacancy ): array {
        // Check primary address first
        $postcode = $vacancy['primary_address']['postcode']
                   ?? $vacancy['postcode']
                   ?? '';

        if ( empty( $postcode ) ) {
            return $vacancy;
        }

        // Skip if already has coordinates
        $lat = $vacancy['primary_address']['latitude'] ?? $vacancy['latitude'] ?? null;
        $lon = $vacancy['primary_address']['longitude'] ?? $vacancy['longitude'] ?? null;

        if ( null !== $lat && null !== $lon && $lat !== 0 && $lon !== 0 ) {
            return $vacancy;
        }

        // Geocode the postcode
        $result = $this->geocode_postcode( $postcode );

        if ( $result['success'] ) {
            // Update primary address
            if ( isset( $vacancy['primary_address'] ) ) {
                $vacancy['primary_address']['latitude']  = $result['latitude'];
                $vacancy['primary_address']['longitude'] = $result['longitude'];
            }

            // Update top-level for backwards compatibility
            $vacancy['latitude']  = $result['latitude'];
            $vacancy['longitude'] = $result['longitude'];

            // Also update addresses array if present
            if ( isset( $vacancy['addresses'] ) && is_array( $vacancy['addresses'] ) && ! empty( $vacancy['addresses'] ) ) {
                $vacancy['addresses'][0]['latitude']  = $result['latitude'];
                $vacancy['addresses'][0]['longitude'] = $result['longitude'];
            }
        }

        return $vacancy;
    }

    /**
     * Make HTTP request to Nominatim API
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Query parameters.
     * @return array{success: bool, data?: array, error?: string}
     */
    private function make_request( string $endpoint, array $params ): array {
        $url = self::API_URL . $endpoint . '?' . http_build_query( $params );

        $this->request_count++;
        $this->last_request_time = microtime( true );

        $args = array(
            'headers' => array(
                'User-Agent' => $this->user_agent,
                'Accept'     => 'application/json',
            ),
            'timeout' => 10,
        );

        $this->logger->trace( sprintf( 'OSM request: %s', $endpoint ), null, 'geocoder' );

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( sprintf( 'OSM request failed: %s', $response->get_error_message() ), null, 'geocoder' );

            return array(
                'success' => false,
                'error'   => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            $this->logger->error( sprintf( 'OSM returned HTTP %d', $status_code ), null, 'geocoder' );

            return array(
                'success' => false,
                'error'   => sprintf( 'HTTP %d error', $status_code ),
            );
        }

        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array(
                'success' => false,
                'error'   => 'Invalid JSON response.',
            );
        }

        return array(
            'success' => true,
            'data'    => $data,
        );
    }

    /**
     * Apply rate limiting to comply with Nominatim usage policy
     */
    private function apply_rate_limit(): void {
        if ( $this->last_request_time > 0 ) {
            $elapsed = ( microtime( true ) - $this->last_request_time ) * 1000;

            if ( $elapsed < self::RATE_LIMIT_DELAY ) {
                $wait = (int) ( self::RATE_LIMIT_DELAY - $elapsed );
                $this->logger->trace( sprintf( 'Rate limit: waiting %dms', $wait ), null, 'geocoder' );
                usleep( $wait * 1000 );
            }
        }
    }

    /**
     * Normalize UK postcode format
     *
     * @param string $postcode Raw postcode.
     * @return string Normalized postcode.
     */
    private function normalize_postcode( string $postcode ): string {
        // Remove all whitespace and convert to uppercase
        $postcode = strtoupper( preg_replace( '/\s+/', '', $postcode ) );

        // Basic UK postcode validation (loose)
        if ( ! preg_match( '/^[A-Z]{1,2}[0-9][0-9A-Z]?[0-9][A-Z]{2}$/', $postcode ) ) {
            return '';
        }

        // Add space in correct position (before last 3 characters)
        return substr( $postcode, 0, -3 ) . ' ' . substr( $postcode, -3 );
    }

    /**
     * Generate cache key
     *
     * @param string $type  Cache type (forward, reverse, address).
     * @param string $value Value to cache.
     * @return string Cache key.
     */
    private function get_cache_key( string $type, string $value ): string {
        return 'apprco_geo_' . $type . '_' . md5( $value );
    }

    /**
     * Clear geocoder cache
     *
     * @param string|null $type Optional type to clear (forward, reverse, address). Null clears all.
     */
    public function clear_cache( ?string $type = null ): void {
        global $wpdb;

        $pattern = '_transient_apprco_geo_';
        if ( null !== $type ) {
            $pattern .= $type . '_';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $pattern . '%',
                '_transient_timeout' . substr( $pattern, 10 ) . '%'
            )
        );

        $this->logger->info( 'Geocoder cache cleared', null, 'geocoder' );
    }

    /**
     * Get geocoder statistics
     *
     * @return array{requests: int, cache_hits: int, hit_rate: float}
     */
    public function get_stats(): array {
        $total    = $this->request_count + $this->cache_hits;
        $hit_rate = $total > 0 ? ( $this->cache_hits / $total ) * 100 : 0;

        return array(
            'requests'   => $this->request_count,
            'cache_hits' => $this->cache_hits,
            'hit_rate'   => round( $hit_rate, 2 ),
        );
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     *
     * @param float $lat1 Latitude 1.
     * @param float $lon1 Longitude 1.
     * @param float $lat2 Latitude 2.
     * @param float $lon2 Longitude 2.
     * @param string $unit Unit: 'km' (default), 'miles', or 'meters'.
     * @return float Distance in specified unit.
     */
    public function calculate_distance( float $lat1, float $lon1, float $lat2, float $lon2, string $unit = 'km' ): float {
        $earth_radius_km = 6371;

        $lat1_rad = deg2rad( $lat1 );
        $lat2_rad = deg2rad( $lat2 );
        $dlat     = deg2rad( $lat2 - $lat1 );
        $dlon     = deg2rad( $lon2 - $lon1 );

        $a = sin( $dlat / 2 ) * sin( $dlat / 2 ) +
             cos( $lat1_rad ) * cos( $lat2_rad ) *
             sin( $dlon / 2 ) * sin( $dlon / 2 );

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        $distance_km = $earth_radius_km * $c;

        switch ( $unit ) {
            case 'miles':
                return $distance_km * 0.621371;
            case 'meters':
                return $distance_km * 1000;
            default:
                return $distance_km;
        }
    }

    /**
     * Find vacancies within a radius of a postcode
     *
     * @param string $postcode    Center postcode.
     * @param float  $radius      Radius in specified unit.
     * @param string $unit        Unit: 'km' (default), 'miles'.
     * @param int    $limit       Maximum results.
     * @return array{success: bool, vacancies?: array, center?: array, error?: string}
     */
    public function find_vacancies_near_postcode( string $postcode, float $radius = 25, string $unit = 'miles', int $limit = 50 ): array {
        // First, geocode the center postcode
        $center_result = $this->geocode_postcode( $postcode );

        if ( ! $center_result['success'] ) {
            return $center_result;
        }

        $center_lat = $center_result['latitude'];
        $center_lon = $center_result['longitude'];

        // Query vacancies with coordinates
        $args = array(
            'post_type'      => 'apprco_vacancy',
            'post_status'    => 'publish',
            'posts_per_page' => $limit * 2, // Fetch more to filter by distance
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_apprco_latitude',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => '_apprco_longitude',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $query     = new WP_Query( $args );
        $vacancies = array();

        foreach ( $query->posts as $post ) {
            $lat = (float) get_post_meta( $post->ID, '_apprco_latitude', true );
            $lon = (float) get_post_meta( $post->ID, '_apprco_longitude', true );

            if ( $lat === 0.0 || $lon === 0.0 ) {
                continue;
            }

            $distance = $this->calculate_distance( $center_lat, $center_lon, $lat, $lon, $unit );

            if ( $distance <= $radius ) {
                $vacancies[] = array(
                    'id'       => $post->ID,
                    'title'    => $post->post_title,
                    'distance' => round( $distance, 1 ),
                    'unit'     => $unit,
                    'latitude' => $lat,
                    'longitude' => $lon,
                );
            }
        }

        // Sort by distance
        usort( $vacancies, function ( $a, $b ) {
            return $a['distance'] <=> $b['distance'];
        } );

        // Apply limit
        $vacancies = array_slice( $vacancies, 0, $limit );

        return array(
            'success'   => true,
            'vacancies' => $vacancies,
            'center'    => array(
                'postcode'  => $postcode,
                'latitude'  => $center_lat,
                'longitude' => $center_lon,
            ),
            'radius'    => $radius,
            'unit'      => $unit,
            'total'     => count( $vacancies ),
        );
    }
}
