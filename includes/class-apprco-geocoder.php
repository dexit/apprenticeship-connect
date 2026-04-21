<?php
/**
 * Geocoding Service
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Geocoder {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function geocode( string $address ): ?array {
		$settings = Apprco_Settings_Manager::get_instance();
		if ( ! $settings->get( 'advanced', 'enable_geocoding', true ) ) {
			return null;
		}

		$cache_key = 'apprco_geo_' . md5( $address );
		$cached    = get_transient( $cache_key );
		if ( $cached ) {
			return $cached;
		}

		$url    = 'https://nominatim.openstreetmap.org/search';
		$params = array(
			'q'              => $address,
			'format'         => 'json',
			'limit'          => 1,
			'addressdetails' => 1,
		);

		$response = wp_remote_get(
			add_query_arg( $params, $url ),
			array(
				'headers' => array( 'User-Agent' => 'ApprenticeshipConnect/3.1.0' ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data ) ) {
			return null;
		}

		$result = array(
			'lat' => $data[0]['lat'],
			'lng' => $data[0]['lon'],
		);

		set_transient( $cache_key, $result, MONTH_IN_SECONDS );
		usleep( 1000000 ); // Nominatim rate limit: 1 req/sec.

		return $result;
	}
}
