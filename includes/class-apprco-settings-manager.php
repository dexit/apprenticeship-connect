<?php
/**
 * Settings Manager Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Settings_Manager
 *
 * Single source of truth for plugin settings.
 */
class Apprco_Settings_Manager {

	/**
	 * Option name for settings.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'apprco_settings';

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Settings_Manager|null
	 */
	private static $instance = null;

	/**
	 * Settings cache.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings = get_option( self::OPTION_NAME, $this->get_defaults() );
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public function get_defaults(): array {
		return array(
			'api'      => array(
				'base_url'         => 'https://api.apprenticeships.education.gov.uk/vacancies',
				'subscription_key' => '',
				'retry_max'        => 3,
				'retry_delay_ms'   => 1000,
				'retry_multiplier' => 2,
			),
			'import'   => array(
				'max_pages'      => 100,
				'deep_fetch'     => true,
				'delete_expired' => false,
			),
			'advanced' => array(
				'enable_logging'     => true,
				'log_retention_days' => 30,
				'debug_mode'         => false,
			),
		);
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $category Category name.
	 * @param string $key      Setting key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	public function get( string $category, string $key, $default_value = null ) {
		return isset( $this->settings[ $category ][ $key ] ) ? $this->settings[ $category ][ $key ] : $default_value;
	}

	/**
	 * Registers settings REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'apprco/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings_rest' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings_rest' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
			)
		);
	}

	/**
	 * REST permission check.
	 *
	 * @return bool
	 */
	public function permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get settings for REST.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings_rest(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'settings' => $this->settings,
				'defaults' => $this->get_defaults(),
			),
			200
		);
	}

	/**
	 * Update settings via REST.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function update_settings_rest( $request ): WP_REST_Response {
		$new_settings   = $request->get_json_params();
		$this->settings = array_replace_recursive( $this->settings, $new_settings );
		update_option( self::OPTION_NAME, $this->settings );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
