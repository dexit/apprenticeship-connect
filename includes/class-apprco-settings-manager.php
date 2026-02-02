<?php
/**
 * Unified Settings Manager
 *
 * Single source of truth for all plugin settings.
 * Consolidates wp_options, task settings, and provider configs.
 *
 * @package ApprenticeshipConnect
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Apprco_Settings_Manager
 *
 * Manages all plugin settings with validation, defaults, and REST API support.
 */
class Apprco_Settings_Manager {

	/**
	 * Settings option name
	 */
	public const OPTION_NAME = 'apprco_settings';

	/**
	 * Settings version for migrations
	 */
	public const SETTINGS_VERSION = '3.0.0';

	/**
	 * Singleton instance
	 *
	 * @var Apprco_Settings_Manager|null
	 */
	private static $instance = null;

	/**
	 * Cached settings
	 *
	 * @var array
	 */
	private $settings = null;

	/**
	 * Setting categories
	 *
	 * @var array
	 */
	private $categories = array(
		'api'      => 'API Configuration',
		'import'   => 'Import Settings',
		'schedule' => 'Scheduling',
		'display'  => 'Display Options',
		'advanced' => 'Advanced Settings',
	);

	/**
	 * Get singleton instance
	 *
	 * @return Apprco_Settings_Manager
	 */
	public static function get_instance(): Apprco_Settings_Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_init', array( $this, 'maybe_migrate' ) );
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings organized by category.
	 */
	public function get_defaults(): array {
		return array(
			'api'      => array(
				'base_url'        => 'https://api.apprenticeships.education.gov.uk/vacancies',
				'subscription_key' => '',
				'ukprn'           => '',
				'version'         => '2',
				'timeout'         => 60,
				'retry_max'       => 3,
				'retry_delay'     => 2,
			),
			'import'   => array(
				'batch_size'        => 100,
				'max_pages'         => 100,
				'rate_limit_delay'  => 250,
				'duplicate_action'  => 'update',
				'post_status'       => 'publish',
				'delete_expired'    => false,
				'expire_after_days' => 30,
			),
			'schedule' => array(
				'enabled'         => false,
				'frequency'       => 'daily',
				'time'            => '03:00',
				'use_action_scheduler' => true,
			),
			'display'  => array(
				'items_per_page'    => 10,
				'show_employer'     => true,
				'show_location'     => true,
				'show_salary'       => true,
				'show_closing_date' => true,
				'show_apply_button' => true,
				'date_format'       => 'F j, Y',
				'no_vacancy_image'  => APPRCO_PLUGIN_URL . 'assets/images/bg-no-vacancy.png',
				'show_no_vacancy_image' => true,
			),
			'advanced' => array(
				'enable_geocoding'  => true,
				'enable_employers'  => true,
				'enable_logging'    => true,
				'log_retention_days' => 30,
				'debug_mode'        => false,
			),
		);
	}

	/**
	 * Get all settings
	 *
	 * @param bool $fresh Force fresh load from database.
	 * @return array All settings.
	 */
	public function get_all( bool $fresh = false ): array {
		if ( null === $this->settings || $fresh ) {
			$stored = get_option( self::OPTION_NAME, array() );
			$defaults = $this->get_defaults();

			// Merge with defaults, preserving structure
			$this->settings = array();
			foreach ( $defaults as $category => $category_settings ) {
				$this->settings[ $category ] = wp_parse_args(
					$stored[ $category ] ?? array(),
					$category_settings
				);
			}
		}

		return $this->settings;
	}

	/**
	 * Get a specific setting
	 *
	 * @param string $category Setting category.
	 * @param string $key      Setting key.
	 * @param mixed  $default  Default value if not found.
	 * @return mixed Setting value.
	 */
	public function get( string $category, string $key, $default = null ) {
		$all = $this->get_all();

		if ( isset( $all[ $category ][ $key ] ) ) {
			return $all[ $category ][ $key ];
		}

		if ( null !== $default ) {
			return $default;
		}

		$defaults = $this->get_defaults();
		return $defaults[ $category ][ $key ] ?? null;
	}

	/**
	 * Update a specific setting
	 *
	 * @param string $category Setting category.
	 * @param string $key      Setting key.
	 * @param mixed  $value    New value.
	 * @return bool Success.
	 */
	public function update( string $category, string $key, $value ): bool {
		$all = $this->get_all();

		if ( ! isset( $all[ $category ] ) ) {
			return false;
		}

		$all[ $category ][ $key ] = $value;
		$this->settings = $all;

		return update_option( self::OPTION_NAME, $all );
	}

	/**
	 * Update multiple settings at once
	 *
	 * @param array $settings Settings to update (category => array of key => value).
	 * @return array Result with success/errors.
	 */
	public function update_bulk( array $settings ): array {
		$all = $this->get_all();
		$validation_errors = array();

		// Validate each setting
		foreach ( $settings as $category => $category_settings ) {
			if ( ! isset( $all[ $category ] ) ) {
				$validation_errors[] = sprintf( 'Invalid category: %s', $category );
				continue;
			}

			foreach ( $category_settings as $key => $value ) {
				$result = $this->validate_setting( $category, $key, $value );

				if ( ! $result['valid'] ) {
					$validation_errors[] = $result['error'];
				} else {
					$all[ $category ][ $key ] = $result['value'];
				}
			}
		}

		if ( ! empty( $validation_errors ) ) {
			return array(
				'success' => false,
				'errors'  => $validation_errors,
			);
		}

		$this->settings = $all;
		$success = update_option( self::OPTION_NAME, $all );

		return array(
			'success' => $success,
			'message' => $success ? 'Settings updated successfully.' : 'Failed to save settings.',
		);
	}

	/**
	 * Validate a setting value
	 *
	 * @param string $category Category.
	 * @param string $key      Key.
	 * @param mixed  $value    Value to validate.
	 * @return array Validation result.
	 */
	private function validate_setting( string $category, string $key, $value ): array {
		// API validations
		if ( 'api' === $category ) {
			if ( 'base_url' === $key && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				return array( 'valid' => false, 'error' => 'API Base URL must be a valid URL.' );
			}

			if ( 'subscription_key' === $key && ! empty( $value ) && strlen( $value ) < 10 ) {
				return array( 'valid' => false, 'error' => 'API Subscription Key appears invalid.' );
			}

			if ( 'timeout' === $key && ( $value < 10 || $value > 300 ) ) {
				return array( 'valid' => false, 'error' => 'Timeout must be between 10 and 300 seconds.' );
			}

			if ( 'retry_max' === $key && ( $value < 0 || $value > 10 ) ) {
				return array( 'valid' => false, 'error' => 'Max retries must be between 0 and 10.' );
			}
		}

		// Import validations
		if ( 'import' === $category ) {
			if ( 'batch_size' === $key && ( $value < 1 || $value > 1000 ) ) {
				return array( 'valid' => false, 'error' => 'Batch size must be between 1 and 1000.' );
			}

			if ( 'max_pages' === $key && ( $value < 1 || $value > 1000 ) ) {
				return array( 'valid' => false, 'error' => 'Max pages must be between 1 and 1000.' );
			}

			if ( 'expire_after_days' === $key && ( $value < 1 || $value > 365 ) ) {
				return array( 'valid' => false, 'error' => 'Expire after days must be between 1 and 365.' );
			}
		}

		// Display validations
		if ( 'display' === $category ) {
			if ( 'items_per_page' === $key && ( $value < 1 || $value > 100 ) ) {
				return array( 'valid' => false, 'error' => 'Items per page must be between 1 and 100.' );
			}
		}

		// Advanced validations
		if ( 'advanced' === $category ) {
			if ( 'log_retention_days' === $key && ( $value < 1 || $value > 365 ) ) {
				return array( 'valid' => false, 'error' => 'Log retention must be between 1 and 365 days.' );
			}
		}

		return array( 'valid' => true, 'value' => $value );
	}

	/**
	 * Reset to defaults
	 *
	 * @param string|null $category Optional category to reset. Null resets all.
	 * @return bool Success.
	 */
	public function reset_to_defaults( ?string $category = null ): bool {
		$defaults = $this->get_defaults();

		if ( null !== $category ) {
			if ( ! isset( $defaults[ $category ] ) ) {
				return false;
			}

			$all = $this->get_all();
			$all[ $category ] = $defaults[ $category ];
			$this->settings = $all;

			return update_option( self::OPTION_NAME, $all );
		}

		$this->settings = $defaults;
		return update_option( self::OPTION_NAME, $defaults );
	}

	/**
	 * Export settings as JSON
	 *
	 * @return string JSON encoded settings.
	 */
	public function export(): string {
		$data = array(
			'version'  => self::SETTINGS_VERSION,
			'exported' => gmdate( 'Y-m-d H:i:s' ),
			'settings' => $this->get_all(),
		);

		return wp_json_encode( $data, JSON_PRETTY_PRINT );
	}

	/**
	 * Import settings from JSON
	 *
	 * @param string $json JSON encoded settings.
	 * @return array Result.
	 */
	public function import( string $json ): array {
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'error'   => 'Invalid JSON: ' . json_last_error_msg(),
			);
		}

		if ( ! isset( $data['settings'] ) ) {
			return array(
				'success' => false,
				'error'   => 'Invalid format: missing settings key.',
			);
		}

		return $this->update_bulk( $data['settings'] );
	}

	/**
	 * Migrate from old settings format
	 */
	public function maybe_migrate(): void {
		// Check if already migrated
		if ( get_option( self::OPTION_NAME . '_migrated' ) ) {
			return;
		}

		// Get old settings
		$old_options = get_option( 'apprco_plugin_options', array() );

		if ( empty( $old_options ) ) {
			update_option( self::OPTION_NAME . '_migrated', true );
			return;
		}

		// Map old settings to new structure
		$new_settings = $this->get_defaults();

		// API settings
		if ( isset( $old_options['api_base_url'] ) ) {
			$new_settings['api']['base_url'] = $old_options['api_base_url'];
		}
		if ( isset( $old_options['api_subscription_key'] ) ) {
			$new_settings['api']['subscription_key'] = $old_options['api_subscription_key'];
		}
		if ( isset( $old_options['api_ukprn'] ) ) {
			$new_settings['api']['ukprn'] = $old_options['api_ukprn'];
		}

		// Schedule settings
		if ( isset( $old_options['sync_frequency'] ) ) {
			$new_settings['schedule']['frequency'] = $old_options['sync_frequency'];
		}

		// Import settings
		if ( isset( $old_options['delete_expired'] ) ) {
			$new_settings['import']['delete_expired'] = $old_options['delete_expired'];
		}
		if ( isset( $old_options['expire_after_days'] ) ) {
			$new_settings['import']['expire_after_days'] = $old_options['expire_after_days'];
		}

		// Display settings
		if ( isset( $old_options['display_count'] ) ) {
			$new_settings['display']['items_per_page'] = $old_options['display_count'];
		}
		if ( isset( $old_options['show_employer'] ) ) {
			$new_settings['display']['show_employer'] = $old_options['show_employer'];
		}
		if ( isset( $old_options['show_location'] ) ) {
			$new_settings['display']['show_location'] = $old_options['show_location'];
		}
		if ( isset( $old_options['show_closing_date'] ) ) {
			$new_settings['display']['show_closing_date'] = $old_options['show_closing_date'];
		}
		if ( isset( $old_options['show_apply_button'] ) ) {
			$new_settings['display']['show_apply_button'] = $old_options['show_apply_button'];
		}
		if ( isset( $old_options['no_vacancy_image'] ) ) {
			$new_settings['display']['no_vacancy_image'] = $old_options['no_vacancy_image'];
		}
		if ( isset( $old_options['show_no_vacancy_image'] ) ) {
			$new_settings['display']['show_no_vacancy_image'] = $old_options['show_no_vacancy_image'];
		}

		// Save migrated settings
		update_option( self::OPTION_NAME, $new_settings );
		update_option( self::OPTION_NAME . '_migrated', true );

		// Keep old options for backward compatibility (will be removed in future version)
	}

	/**
	 * Get settings as flat options array (legacy format)
	 *
	 * Converts categorized settings into the flat array format
	 * expected by legacy code components.
	 *
	 * @return array Flat options array with legacy key names.
	 */
	public function get_options_array(): array {
		return array(
			// API settings
			'api_subscription_key' => $this->get( 'api', 'subscription_key' ),
			'api_base_url'         => $this->get( 'api', 'base_url' ),
			'api_ukprn'            => $this->get( 'api', 'ukprn' ),
			'api_version'          => $this->get( 'api', 'version' ),
			'api_timeout'          => $this->get( 'api', 'timeout' ),
			'retry_max'            => $this->get( 'api', 'retry_max' ),
			'retry_delay'          => $this->get( 'api', 'retry_delay' ),

			// Import settings
			'batch_size'           => $this->get( 'import', 'batch_size' ),
			'max_pages'            => $this->get( 'import', 'max_pages' ),
			'rate_limit_delay'     => $this->get( 'import', 'rate_limit_delay' ),
			'duplicate_action'     => $this->get( 'import', 'duplicate_action' ),
			'post_status'          => $this->get( 'import', 'post_status' ),
			'delete_expired'       => $this->get( 'import', 'delete_expired' ),
			'expire_after_days'    => $this->get( 'import', 'expire_after_days' ),

			// Schedule settings
			'sync_frequency'       => $this->get( 'schedule', 'frequency' ),
			'sync_time'            => $this->get( 'schedule', 'time' ),
			'use_action_scheduler' => $this->get( 'schedule', 'use_action_scheduler' ),

			// Display settings
			'display_count'        => $this->get( 'display', 'items_per_page' ),
			'show_employer'        => $this->get( 'display', 'show_employer' ),
			'show_location'        => $this->get( 'display', 'show_location' ),
			'show_salary'          => $this->get( 'display', 'show_salary' ),
			'show_closing_date'    => $this->get( 'display', 'show_closing_date' ),
			'show_apply_button'    => $this->get( 'display', 'show_apply_button' ),
			'date_format'          => $this->get( 'display', 'date_format' ),
			'no_vacancy_image'     => $this->get( 'display', 'no_vacancy_image' ),
			'show_no_vacancy_image' => $this->get( 'display', 'show_no_vacancy_image' ),

			// Advanced settings
			'enable_geocoding'     => $this->get( 'advanced', 'enable_geocoding' ),
			'enable_employers'     => $this->get( 'advanced', 'enable_employers' ),
			'enable_logging'       => $this->get( 'advanced', 'enable_logging' ),
			'log_retention_days'   => $this->get( 'advanced', 'log_retention_days' ),
			'debug_mode'           => $this->get( 'advanced', 'debug_mode' ),
		);
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'apprco/v1',
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_settings' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);

		register_rest_route(
			'apprco/v1',
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_update_settings' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);

		register_rest_route(
			'apprco/v1',
			'/settings/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_export_settings' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);
	}

	/**
	 * REST permission check
	 *
	 * @return bool
	 */
	public function rest_permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST: Get settings
	 *
	 * @return WP_REST_Response
	 */
	public function rest_get_settings(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'settings'   => $this->get_all(),
				'defaults'   => $this->get_defaults(),
				'categories' => $this->categories,
			),
			200
		);
	}

	/**
	 * REST: Update settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_update_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = $request->get_json_params();

		if ( empty( $settings ) ) {
			return new WP_REST_Response(
				array( 'error' => 'No settings provided.' ),
				400
			);
		}

		$result = $this->update_bulk( $settings );

		if ( ! $result['success'] ) {
			return new WP_REST_Response( $result, 400 );
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => $result['message'],
				'settings' => $this->get_all(),
			),
			200
		);
	}

	/**
	 * REST: Export settings
	 *
	 * @return WP_REST_Response
	 */
	public function rest_export_settings(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'export' => $this->export(),
			),
			200
		);
	}
}
