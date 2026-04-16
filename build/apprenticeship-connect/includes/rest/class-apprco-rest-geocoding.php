<?php
/**
 * REST API Geocoding Endpoint
 *
 * Provides frontend access to OSM Nominatim geocoding services
 * for converting locations to coordinates and vice versa.
 *
 * @package    Apprenticeship_Connect
 * @subpackage Rest
 * @since      3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Apprco_REST_Geocoding
 *
 * Exposes geocoding functionality via REST API for frontend use.
 */
class Apprco_REST_Geocoding {

	/**
	 * Register REST API routes
	 */
	public function register_routes(): void {
		// Forward geocoding: Location → Lat/Long
		register_rest_route(
			'apprco/v1',
			'/geocode/forward',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'forward_geocode' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => array(
					'location' => array(
						'description'       => 'Location to geocode (postcode, city, or full address)',
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function( $param ) {
							return ! empty( trim( $param ) );
						},
					),
					'country'  => array(
						'description'       => 'Country code for more accurate results (e.g., "GB", "UK")',
						'type'              => 'string',
						'default'           => 'GB',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Reverse geocoding: Lat/Long → Address
		register_rest_route(
			'apprco/v1',
			'/geocode/reverse',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'reverse_geocode' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => array(
					'lat' => array(
						'description'       => 'Latitude',
						'type'              => 'number',
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param >= -90 && $param <= 90;
						},
					),
					'lon' => array(
						'description'       => 'Longitude',
						'type'              => 'number',
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param >= -180 && $param <= 180;
						},
					),
				),
			)
		);

		// Get user's current location (browser geolocation passthrough)
		register_rest_route(
			'apprco/v1',
			'/geocode/current',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'process_current_location' ),
				'permission_callback' => '__return_true', // Public endpoint
				'args'                => array(
					'lat' => array(
						'description'       => 'Latitude from browser geolocation',
						'type'              => 'number',
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param >= -90 && $param <= 90;
						},
					),
					'lon' => array(
						'description'       => 'Longitude from browser geolocation',
						'type'              => 'number',
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param >= -180 && $param <= 180;
						},
					),
				),
			)
		);

		// Get geocoding statistics and cache info
		register_rest_route(
			'apprco/v1',
			'/geocode/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Forward geocode: Convert location to coordinates
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function forward_geocode( WP_REST_Request $request ) {
		$location = $request->get_param( 'location' );
		$country  = $request->get_param( 'country' );

		// Append country if provided
		$query = $location;
		if ( ! empty( $country ) ) {
			$query .= ', ' . $country;
		}

		// Get geocoder instance
		$geocoder = Apprco_Geocoder::get_instance();

		// Perform geocoding
		$result = $geocoder->forward_geocode( $query );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'geocoding_failed',
				$result->get_error_message(),
				array( 'status' => 500 )
			);
		}

		if ( empty( $result ) ) {
			return new WP_Error(
				'location_not_found',
				sprintf( 'Could not find location: %s', $location ),
				array( 'status' => 404 )
			);
		}

		// Format response
		$response_data = array(
			'success'  => true,
			'location' => array(
				'lat'          => $result['lat'],
				'lon'          => $result['lon'],
				'display_name' => $result['display_name'] ?? $location,
			),
			'source'   => $result['_source'] ?? 'osm', // cached or fresh
		);

		$response = new WP_REST_Response( $response_data, 200 );

		// Add CORS headers
		$response->header( 'Access-Control-Allow-Origin', '*' );

		return $response;
	}

	/**
	 * Reverse geocode: Convert coordinates to address
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reverse_geocode( WP_REST_Request $request ) {
		$lat = $request->get_param( 'lat' );
		$lon = $request->get_param( 'lon' );

		// Get geocoder instance
		$geocoder = Apprco_Geocoder::get_instance();

		// Perform reverse geocoding
		$result = $geocoder->reverse_geocode( $lat, $lon );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'reverse_geocoding_failed',
				$result->get_error_message(),
				array( 'status' => 500 )
			);
		}

		if ( empty( $result ) ) {
			return new WP_Error(
				'address_not_found',
				sprintf( 'Could not find address for coordinates: %f, %f', $lat, $lon ),
				array( 'status' => 404 )
			);
		}

		// Format response
		$response_data = array(
			'success' => true,
			'address' => array(
				'display_name' => $result['display_name'] ?? '',
				'postcode'     => $result['address']['postcode'] ?? '',
				'city'         => $result['address']['city'] ?? $result['address']['town'] ?? $result['address']['village'] ?? '',
				'county'       => $result['address']['county'] ?? '',
				'country'      => $result['address']['country'] ?? '',
				'full'         => $result['address'] ?? array(),
			),
			'source'  => $result['_source'] ?? 'osm',
		);

		$response = new WP_REST_Response( $response_data, 200 );

		// Add CORS headers
		$response->header( 'Access-Control-Allow-Origin', '*' );

		return $response;
	}

	/**
	 * Process current location from browser geolocation
	 *
	 * This endpoint receives coordinates from browser's geolocation API
	 * and returns the reverse geocoded address.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function process_current_location( WP_REST_Request $request ) {
		$lat = $request->get_param( 'lat' );
		$lon = $request->get_param( 'lon' );

		// Get geocoder instance
		$geocoder = Apprco_Geocoder::get_instance();

		// Reverse geocode to get address
		$result = $geocoder->reverse_geocode( $lat, $lon );

		if ( is_wp_error( $result ) ) {
			// Return coordinates even if reverse geocoding fails
			return new WP_REST_Response(
				array(
					'success'  => true,
					'location' => array(
						'lat'          => $lat,
						'lon'          => $lon,
						'display_name' => sprintf( 'Location: %f, %f', $lat, $lon ),
					),
					'address'  => null,
					'warning'  => 'Could not determine address from coordinates',
				),
				200
			);
		}

		// Format response with both coordinates and address
		$response_data = array(
			'success'  => true,
			'location' => array(
				'lat'          => $lat,
				'lon'          => $lon,
				'display_name' => $result['display_name'] ?? sprintf( '%f, %f', $lat, $lon ),
			),
			'address'  => array(
				'postcode' => $result['address']['postcode'] ?? '',
				'city'     => $result['address']['city'] ?? $result['address']['town'] ?? $result['address']['village'] ?? '',
				'county'   => $result['address']['county'] ?? '',
				'country'  => $result['address']['country'] ?? '',
			),
			'source'   => 'browser_geolocation',
		);

		$response = new WP_REST_Response( $response_data, 200 );

		// Add CORS headers
		$response->header( 'Access-Control-Allow-Origin', '*' );

		return $response;
	}

	/**
	 * Get geocoding statistics
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function get_stats( WP_REST_Request $request ) {
		$geocoder = Apprco_Geocoder::get_instance();
		$stats    = $geocoder->get_stats();

		return new WP_REST_Response( $stats, 200 );
	}
}
