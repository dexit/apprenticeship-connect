<?php
/**
 * Abstract Provider - Base class for vacancy data providers
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Abstract class Apprco_Abstract_Provider
 *
 * Provides common functionality for all vacancy providers.
 * Extend this class to create new data providers.
 */
abstract class Apprco_Abstract_Provider implements Apprco_Provider_Interface {

    /**
     * Provider configuration
     *
     * @var array
     */
    protected $config = array();

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger
     */
    protected $logger;

    /**
     * API client instance
     *
     * @var Apprco_API_Client
     */
    protected $api_client;

    /**
     * Default rate limits
     *
     * @var array
     */
    protected $rate_limits = array(
        'requests_per_minute' => 60,
        'delay_ms'            => 200,
    );

    /**
     * Constructor
     *
     * @param Apprco_Import_Logger|null $logger     Logger instance.
     * @param Apprco_API_Client|null    $api_client API client instance.
     */
    public function __construct( ?Apprco_Import_Logger $logger = null, ?Apprco_API_Client $api_client = null ) {
        $this->logger     = $logger ?? Apprco_Import_Logger::get_instance();
        $this->api_client = $api_client;
    }

    /**
     * Set API client
     *
     * @param Apprco_API_Client $client API client instance.
     */
    public function set_api_client( Apprco_API_Client $client ): void {
        $this->api_client = $client;
    }

    /**
     * Get API client
     *
     * @return Apprco_API_Client|null
     */
    public function get_api_client(): ?Apprco_API_Client {
        return $this->api_client;
    }

    /**
     * Set provider configuration
     *
     * @param array $config Configuration values.
     */
    public function set_config( array $config ): void {
        $schema = $this->get_config_schema();

        foreach ( $schema as $key => $field ) {
            if ( isset( $config[ $key ] ) ) {
                $this->config[ $key ] = $this->sanitize_config_value( $config[ $key ], $field );
            } elseif ( isset( $field['default'] ) ) {
                $this->config[ $key ] = $field['default'];
            }
        }

        $this->log( 'debug', 'Configuration updated', 'provider', array(
            'provider' => $this->get_id(),
            'keys'     => array_keys( $this->config ),
        ) );
    }

    /**
     * Sanitize configuration value based on field type
     *
     * @param mixed $value Field value.
     * @param array $field Field schema.
     * @return mixed Sanitized value.
     */
    protected function sanitize_config_value( $value, array $field ) {
        $type = $field['type'] ?? 'string';

        switch ( $type ) {
            case 'string':
                return sanitize_text_field( $value );
            case 'url':
                return esc_url_raw( $value );
            case 'int':
            case 'integer':
                return (int) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
                return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Get current configuration
     *
     * @return array Current configuration values.
     */
    public function get_config(): array {
        return $this->config;
    }

    /**
     * Get a specific config value
     *
     * @param string $key     Configuration key.
     * @param mixed  $default Default value if not set.
     * @return mixed Configuration value.
     */
    protected function get_config_value( string $key, $default = null ) {
        return $this->config[ $key ] ?? $default;
    }

    /**
     * Check if provider is properly configured
     *
     * @return bool True if all required configuration is present.
     */
    public function is_configured(): bool {
        $schema = $this->get_config_schema();

        foreach ( $schema as $key => $field ) {
            if ( ! empty( $field['required'] ) && empty( $this->config[ $key ] ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get rate limit settings
     *
     * @return array{requests_per_minute: int, delay_ms: int}
     */
    public function get_rate_limits(): array {
        return $this->rate_limits;
    }

    /**
     * Log a message through the logger
     *
     * @param string      $level     Log level.
     * @param string      $message   Log message.
     * @param string      $component Component name.
     * @param array       $context   Additional context.
     * @param string|null $import_id Import ID.
     */
    protected function log( string $level, string $message, string $component = 'provider', array $context = array(), ?string $import_id = null ): void {
        $context['provider'] = $this->get_id();

        if ( method_exists( $this->logger, $level ) ) {
            $this->logger->$level( $message, $import_id, $component, $context );
        } else {
            $this->logger->log( $level, $message, $import_id, $context );
        }
    }

    /**
     * Build common normalized vacancy structure
     *
     * Override normalize_vacancy() to use this as a template.
     *
     * @return array Empty normalized structure.
     */
    protected function get_normalized_template(): array {
        return array(
            // Core identifiers
            'vacancy_reference'      => '',
            'vacancy_url'            => '',
            'provider_id'            => $this->get_id(),

            // Basic info
            'title'                  => '',
            'description'            => '',
            'short_description'      => '',

            // Employer
            'employer_name'          => '',
            'employer_website'       => '',
            'employer_description'   => '',
            'employer_contact_email' => '',
            'employer_contact_phone' => '',
            'employer_contact_name'  => '',

            // Provider (Training)
            'provider_name'          => '',
            'provider_ukprn'         => '',
            'provider_contact_email' => '',
            'provider_contact_phone' => '',
            'provider_contact_name'  => '',

            // Location
            'addresses'              => array(),
            'primary_address'        => array(
                'address_line1' => '',
                'address_line2' => '',
                'address_line3' => '',
                'address_line4' => '',
                'postcode'      => '',
                'latitude'      => null,
                'longitude'     => null,
            ),

            // Course/Qualification
            'course_title'           => '',
            'course_route'           => '',
            'course_level'           => 0,
            'course_id'              => 0,
            'qualification'          => '',
            'apprenticeship_level'   => '',

            // Employment
            'wage_type'              => '',
            'wage_amount'            => 0,
            'wage_amount_lower'      => 0,
            'wage_amount_upper'      => 0,
            'wage_unit'              => '',
            'wage_text'              => '',
            'wage_additional_info'   => '',
            'working_week'           => '',
            'hours_per_week'         => 0,
            'expected_duration'      => '',
            'employment_type'        => '',
            'positions_available'    => 1,

            // Requirements
            'skills_required'        => array(),
            'qualifications_required' => array(),
            'things_to_consider'     => '',
            'outcome_description'    => '',

            // Dates
            'posted_date'            => '',
            'closing_date'           => '',
            'start_date'             => '',

            // Application
            'apply_url'              => '',
            'apply_email'            => '',
            'apply_instructions'     => '',

            // Status
            'status'                 => 'active',
            'is_disability_confident' => false,
            'is_national'            => false,

            // Meta
            'raw_data'               => array(),
            'imported_at'            => current_time( 'mysql' ),
        );
    }

    /**
     * Merge vacancy data with normalized template
     *
     * @param array $data Partial vacancy data.
     * @return array Complete normalized vacancy.
     */
    protected function merge_with_template( array $data ): array {
        $template = $this->get_normalized_template();

        foreach ( $data as $key => $value ) {
            if ( array_key_exists( $key, $template ) ) {
                $template[ $key ] = $value;
            }
        }

        return $template;
    }

    /**
     * Parse date string to MySQL format
     *
     * @param string $date_string Date string from API.
     * @return string MySQL formatted date or empty string.
     */
    protected function parse_date( string $date_string ): string {
        if ( empty( $date_string ) ) {
            return '';
        }

        $timestamp = strtotime( $date_string );

        if ( false === $timestamp ) {
            return '';
        }

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Clean HTML from description text
     *
     * @param string $text Text to clean.
     * @return string Cleaned text.
     */
    protected function clean_description( string $text ): string {
        // Decode HTML entities
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

        // Strip tags but preserve line breaks
        $text = str_replace( array( '<br>', '<br/>', '<br />' ), "\n", $text );
        $text = wp_strip_all_tags( $text );

        // Normalize whitespace
        $text = preg_replace( '/[ \t]+/', ' ', $text );
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }
}
