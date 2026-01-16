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

    /**
     * Get example API response structure
     *
     * Returns a sample response based on the provider's typical response format.
     * Override this method in child classes to provide provider-specific examples.
     *
     * @return array {
     *     Example API response structure.
     *     @type array  $response       Sample response body.
     *     @type array  $template_vars  Available template variables.
     *     @type string $description    Description of the response.
     * }
     */
    public function get_example_response(): array {
        return array(
            'response'       => array(
                'items' => array(
                    $this->get_normalized_template(),
                ),
                'total' => 1,
                'page'  => 1,
            ),
            'template_vars'  => $this->extract_template_variables( $this->get_normalized_template() ),
            'description'    => sprintf(
                'Example API response structure for %s provider',
                $this->get_name()
            ),
        );
    }

    /**
     * Get example API request structure
     *
     * Returns a sample request with template variables that can be used.
     *
     * @return array {
     *     Example API request structure.
     *     @type string $url            Sample API endpoint URL.
     *     @type string $method         HTTP method (GET, POST, etc).
     *     @type array  $headers        Sample request headers.
     *     @type array  $params         Sample query/body parameters.
     *     @type array  $template_vars  Available template variables for requests.
     *     @type string $description    Description of the request.
     * }
     */
    public function get_example_request(): array {
        $config_schema = $this->get_config_schema();

        $example_headers = array(
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        );

        if ( isset( $config_schema['api_key'] ) ) {
            $example_headers['Authorization'] = 'Bearer {{api_key}}';
        }

        return array(
            'url'            => $this->config['base_url'] ?? 'https://api.example.com/vacancies',
            'method'         => 'GET',
            'headers'        => $example_headers,
            'params'         => array(
                'page'     => '{{page}}',
                'pageSize' => '{{page_size}}',
            ),
            'template_vars'  => array(
                '{{api_key}}'   => 'Your API key',
                '{{page}}'      => 'Current page number',
                '{{page_size}}' => 'Number of items per page',
            ),
            'description'    => sprintf(
                'Example API request structure for %s provider',
                $this->get_name()
            ),
        );
    }

    /**
     * Extract template variables from a response structure
     *
     * Recursively analyzes a data structure and extracts all available field paths
     * that can be used as template variables.
     *
     * @param mixed  $data   Data structure to analyze (array or object).
     * @param string $prefix Current path prefix (for recursion).
     * @param int    $depth  Current recursion depth (max 10).
     * @return array {
     *     Template variables with descriptions.
     *     Format: 'path' => 'description'
     *     Example: 'item.title' => 'Vacancy title (string)'
     * }
     */
    public function extract_template_variables( $data, string $prefix = '', int $depth = 0 ): array {
        if ( $depth > 10 ) {
            return array();
        }

        $variables = array();

        if ( is_array( $data ) ) {
            // Check if it's a numeric array (list)
            $is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );

            if ( $is_list && ! empty( $data ) ) {
                // For arrays, use [0] notation and recurse into first item
                $item_prefix = $prefix ? "{$prefix}[0]" : '[0]';
                $variables = array_merge(
                    $variables,
                    $this->extract_template_variables( $data[0], $item_prefix, $depth + 1 )
                );
            } else {
                // For associative arrays, recurse into each key
                foreach ( $data as $key => $value ) {
                    $path = $prefix ? "{$prefix}.{$key}" : $key;

                    if ( is_array( $value ) || is_object( $value ) ) {
                        $variables = array_merge(
                            $variables,
                            $this->extract_template_variables( $value, $path, $depth + 1 )
                        );
                    } else {
                        $type = gettype( $value );
                        $sample = $this->get_sample_value( $value );
                        $variables[ $path ] = sprintf(
                            '%s (%s) - Example: %s',
                            $this->humanize_field_name( $key ),
                            $type,
                            $sample
                        );
                    }
                }
            }
        } elseif ( is_object( $data ) ) {
            foreach ( get_object_vars( $data ) as $key => $value ) {
                $path = $prefix ? "{$prefix}.{$key}" : $key;

                if ( is_array( $value ) || is_object( $value ) ) {
                    $variables = array_merge(
                        $variables,
                        $this->extract_template_variables( $value, $path, $depth + 1 )
                    );
                } else {
                    $type = gettype( $value );
                    $sample = $this->get_sample_value( $value );
                    $variables[ $path ] = sprintf(
                        '%s (%s) - Example: %s',
                        $this->humanize_field_name( $key ),
                        $type,
                        $sample
                    );
                }
            }
        }

        return $variables;
    }

    /**
     * Get enhanced rate limit information
     *
     * Provides detailed rate limiting configuration including current usage stats.
     *
     * @return array {
     *     Enhanced rate limit information.
     *     @type int    $requests_per_minute     Maximum requests per minute.
     *     @type int    $delay_ms                Delay between requests (milliseconds).
     *     @type int    $requests_per_hour       Calculated hourly limit.
     *     @type int    $requests_per_day        Calculated daily limit.
     *     @type string $recommended_page_size   Recommended pagination size.
     *     @type array  $retry_config           Retry configuration settings.
     * }
     */
    public function get_rate_limit_info(): array {
        $limits = $this->get_rate_limits();

        return array(
            'requests_per_minute'   => $limits['requests_per_minute'],
            'delay_ms'              => $limits['delay_ms'],
            'requests_per_hour'     => $limits['requests_per_minute'] * 60,
            'requests_per_day'      => $limits['requests_per_minute'] * 60 * 24,
            'recommended_page_size' => 100, // Can be overridden by child class
            'retry_config'          => array(
                'max_retries'     => 3,
                'initial_delay'   => 1000, // ms
                'backoff_multiplier' => 2,
            ),
            'throttle_on_429'       => true,
            'respect_retry_after'   => true,
        );
    }

    /**
     * Validate response structure against expected template
     *
     * Checks if a response contains the expected fields for normalization.
     *
     * @param array $response   Response data to validate.
     * @param array $required_fields  Required field paths (dot notation).
     * @return array {
     *     Validation result.
     *     @type bool   $valid    Whether validation passed.
     *     @type array  $missing  Missing required fields.
     *     @type array  $warnings Optional field warnings.
     * }
     */
    public function validate_response_structure( array $response, array $required_fields = array() ): array {
        if ( empty( $required_fields ) ) {
            // Default required fields for vacancy data
            $required_fields = array( 'title', 'employer_name', 'posted_date' );
        }

        $missing = array();
        $warnings = array();

        foreach ( $required_fields as $field_path ) {
            $value = $this->get_nested_value( $response, $field_path );

            if ( null === $value || '' === $value ) {
                $missing[] = $field_path;
            }
        }

        // Check optional but recommended fields
        $optional_fields = array( 'description', 'location', 'apply_url' );
        foreach ( $optional_fields as $field_path ) {
            $value = $this->get_nested_value( $response, $field_path );

            if ( null === $value || '' === $value ) {
                $warnings[] = "Optional field '{$field_path}' is missing";
            }
        }

        return array(
            'valid'    => empty( $missing ),
            'missing'  => $missing,
            'warnings' => $warnings,
        );
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array  $array Array to search.
     * @param string $path  Dot-notated path (e.g., 'items.0.title').
     * @return mixed Value at path or null if not found.
     */
    protected function get_nested_value( array $array, string $path ) {
        $keys = explode( '.', $path );
        $value = $array;

        foreach ( $keys as $key ) {
            // Handle array notation like [0]
            if ( preg_match( '/\[(\d+)\]/', $key, $matches ) ) {
                $key = (int) $matches[1];
            }

            if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
                return null;
            }

            $value = $value[ $key ];
        }

        return $value;
    }

    /**
     * Humanize field name
     *
     * Converts snake_case or camelCase to readable format.
     *
     * @param string $field_name Field name to humanize.
     * @return string Humanized field name.
     */
    protected function humanize_field_name( string $field_name ): string {
        // Convert camelCase to snake_case first
        $field_name = preg_replace( '/(?<!^)[A-Z]/', '_$0', $field_name );

        // Replace underscores and hyphens with spaces
        $field_name = str_replace( array( '_', '-' ), ' ', $field_name );

        // Capitalize words
        return ucwords( strtolower( $field_name ) );
    }

    /**
     * Get sample value for display
     *
     * Returns a shortened version of a value for example display.
     *
     * @param mixed $value Value to format.
     * @return string Formatted sample value.
     */
    protected function get_sample_value( $value ): string {
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }

        if ( is_null( $value ) ) {
            return 'null';
        }

        if ( is_numeric( $value ) ) {
            return (string) $value;
        }

        $str = (string) $value;
        return mb_strlen( $str ) > 50 ? mb_substr( $str, 0, 47 ) . '...' : $str;
    }
}
