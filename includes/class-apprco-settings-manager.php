<?php
/**
 * Settings Manager - Centralized Configuration API
 *
 * @package ApprenticeshipConnect
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Settings_Manager {

	public const OPTION_NAME = 'apprco_settings';
	private static $instance = null;
	private $settings        = array();

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load();
	}

	private function load(): void {
		$defaults       = $this->get_defaults();
		$stored         = get_option( self::OPTION_NAME, array() );
		$this->settings = array_replace_recursive( $defaults, $stored );
	}

	public function get_defaults(): array {
		$defaults = array(
			'api'      => array(
				'base_url'         => 'https://api.apprenticeships.education.gov.uk/vacancies',
				'subscription_key' => '',
				'ukprn'            => '',
				'version'          => '2',
				'timeout'          => 60,
				'retry_max'        => 5,
				'retry_delay_ms'   => 1000,
				'retry_multiplier' => 2,
			),
			'import'   => array(
				'batch_size'        => 100,
				'max_pages'         => 0,
				'rate_limit_delay'  => 500,
				'duplicate_action'  => 'update',
				'post_status'       => 'publish',
				'delete_expired'    => true,
				'expire_after_days' => 30,
				'deep_fetch'        => true,
			),
			'schedule' => array(
				'enabled'              => false,
				'frequency'            => 'daily',
				'time'                 => '03:00',
				'use_action_scheduler' => true,
			),
			'advanced' => array(
				'enable_geocoding'   => true,
				'enable_logging'     => true,
				'log_retention_days' => 30,
				'debug_mode'         => false,
			),
		);

		return apply_filters( 'apprco_settings_defaults', $defaults );
	}

	public function get( string $group, string $key, $default = null ) {
		return $this->settings[ $group ][ $key ] ?? $default;
	}

	public function get_all(): array {
		return $this->settings;
	}

	public function update_group( string $group, array $values ): bool {
		if ( ! isset( $this->settings[ $group ] ) ) {
			return false;
		}

		$this->settings[ $group ] = array_merge( $this->settings[ $group ], $values );
		return update_option( self::OPTION_NAME, $this->settings );
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'apprco/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_get_settings' ),
					'permission_callback' => array( $this, 'rest_permission_check' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_update_settings' ),
					'permission_callback' => array( $this, 'rest_permission_check' ),
				),
			)
		);
	}

	public function rest_permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function rest_get_settings(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'settings' => $this->get_all(),
				'defaults' => $this->get_defaults(),
			),
			200
		);
	}

	public function rest_update_settings( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		$this->settings = array_replace_recursive( $this->settings, $params );
		update_option( self::OPTION_NAME, $this->settings );
		return new WP_REST_Response( array( 'success' => true, 'settings' => $this->settings ), 200 );
	}
}
