<?php
/**
 * Geocoding Helper Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Geocoder
 *
 * Provides utilities for geocoding postcodes.
 */
class Apprco_Geocoder {

	/**
	 * Get coordinates for a postcode.
	 *
	 * @param string $postcode The postcode to geocode.
	 * @return array|null Lat/lng array or null on failure.
	 */
	public static function get_coordinates( string $postcode ): ?array {
		$response = wp_remote_get( 'https://api.postcodes.io/postcodes/' . rawurlencode( $postcode ) );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['result'] ) ) {
			return null;
		}

		return array(
			'lat' => $body['result']['latitude'],
			'lng' => $body['result']['longitude'],
		);
	}
}
