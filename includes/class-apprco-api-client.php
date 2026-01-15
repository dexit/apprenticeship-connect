<?php
/**
 * API Client - HTTP client with rate limiting, retry, and caching
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Apprco_API_Client
 *
 * Robust HTTP client that handles:
 * - Rate limiting (configurable requests per minute)
 * - Automatic retry with exponential backoff
 * - Transient caching for responses
 * - Request/response logging
 * - Pagination support
 */
class Apprco_API_Client {

    /**
     * Default configuration
     *
     * @var array
     */
    private const DEFAULTS = array(
        'timeout'              => 60,
        'retry_max'            => 3,
        'retry_delay_ms'       => 1000,
        'retry_multiplier'     => 2,
        'rate_limit_delay_ms'  => 200,
        'cache_duration'       => 300,
        'cache_enabled'        => true,
    );

    /**
     * Client configuration
     *
     * @var array
     */
    private $config;

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger
     */
    private $logger;

    /**
     * Base URL for requests
     *
     * @var string
     */
    private $base_url;

    /**
     * Default headers for all requests
     *
     * @var array
     */
    private $default_headers = array();

    /**
     * Last request timestamp (for rate limiting)
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
     * Cache hit counter for stats
     *
     * @var int
     */
    private $cache_hits = 0;

    /**
     * Current import ID for logging
     *
     * @var string|null
     */
    private $import_id = null;

    /**
     * Constructor
     *
     * @param string                    $base_url Base URL for API requests.
     * @param array                     $config   Configuration overrides.
     * @param Apprco_Import_Logger|null $logger   Logger instance.
     */
    public function __construct( string $base_url, array $config = array(), ?Apprco_Import_Logger $logger = null ) {
        $this->base_url = rtrim( $base_url, '/' );
        $this->config   = array_merge( self::DEFAULTS, $config );
        $this->logger   = $logger ?? Apprco_Import_Logger::get_instance();
    }

    /**
     * Set import ID for logging context
     *
     * @param string|null $import_id Import ID.
     */
    public function set_import_id( ?string $import_id ): void {
        $this->import_id = $import_id;
    }

    /**
     * Set default headers for all requests
     *
     * @param array $headers Headers array.
     */
    public function set_default_headers( array $headers ): void {
        $this->default_headers = $headers;
    }

    /**
     * Add a default header
     *
     * @param string $name  Header name.
     * @param string $value Header value.
     */
    public function add_header( string $name, string $value ): void {
        $this->default_headers[ $name ] = $value;
    }

    /**
     * Perform a GET request
     *
     * @param string $endpoint API endpoint (appended to base URL).
     * @param array  $params   Query parameters.
     * @param array  $headers  Additional headers.
     * @return array{success: bool, data?: array, status_code?: int, error?: string, cached?: bool}
     */
    public function get( string $endpoint, array $params = array(), array $headers = array() ): array {
        return $this->request( 'GET', $endpoint, $params, null, $headers );
    }

    /**
     * Perform a POST request
     *
     * @param string     $endpoint API endpoint.
     * @param array|null $body     Request body.
     * @param array      $headers  Additional headers.
     * @return array{success: bool, data?: array, status_code?: int, error?: string}
     */
    public function post( string $endpoint, ?array $body = null, array $headers = array() ): array {
        return $this->request( 'POST', $endpoint, array(), $body, $headers );
    }

    /**
     * Fetch all pages from a paginated endpoint
     *
     * @param string   $endpoint           API endpoint.
     * @param array    $params             Base query parameters.
     * @param string   $page_param         Parameter name for page number.
     * @param string   $data_key           Key in response containing items.
     * @param string   $total_pages_key    Key in response for total pages.
     * @param int      $max_pages          Maximum pages to fetch (safety limit).
     * @param callable $on_page            Optional callback for each page.
     * @return array{success: bool, items?: array, total?: int, pages_fetched?: int, error?: string}
     */
    public function fetch_all_pages(
        string $endpoint,
        array $params = array(),
        string $page_param = 'PageNumber',
        string $data_key = 'vacancies',
        string $total_pages_key = 'totalPages',
        int $max_pages = 500,
        ?callable $on_page = null
    ): array {
        $all_items      = array();
        $page           = 1;
        $total_pages    = 1;
        $total_items    = 0;
        $empty_pages    = 0;

        $this->log( 'info', sprintf( 'Starting paginated fetch from %s', $endpoint ), 'api' );

        do {
            $params[ $page_param ] = $page;

            $result = $this->get( $endpoint, $params );

            if ( ! $result['success'] ) {
                $this->log( 'error', sprintf( 'Paginated fetch failed on page %d: %s', $page, $result['error'] ?? 'Unknown error' ), 'api' );
                return array(
                    'success'       => false,
                    'error'         => $result['error'] ?? 'Request failed',
                    'items'         => $all_items,
                    'pages_fetched' => $page - 1,
                );
            }

            $data = $result['data'];

            // Extract items from response
            $page_items = $this->extract_items( $data, $data_key );

            if ( empty( $page_items ) ) {
                $empty_pages++;
                if ( $empty_pages >= 2 ) {
                    $this->log( 'info', 'Two consecutive empty pages, stopping pagination.', 'api' );
                    break;
                }
            } else {
                $empty_pages = 0;
                $all_items   = array_merge( $all_items, $page_items );

                $this->log( 'debug', sprintf(
                    'Page %d: fetched %d items (total: %d)',
                    $page,
                    count( $page_items ),
                    count( $all_items )
                ), 'api' );
            }

            // Update total pages from response
            if ( isset( $data[ $total_pages_key ] ) ) {
                $total_pages = (int) $data[ $total_pages_key ];
            } elseif ( isset( $data['total'] ) && isset( $params['PageSize'] ) ) {
                $total_pages = (int) ceil( $data['total'] / $params['PageSize'] );
            }

            // Update total items count
            if ( isset( $data['total'] ) ) {
                $total_items = (int) $data['total'];
            }

            // Call progress callback if provided
            if ( is_callable( $on_page ) ) {
                call_user_func( $on_page, array(
                    'page'         => $page,
                    'total_pages'  => $total_pages,
                    'items_count'  => count( $page_items ),
                    'total_so_far' => count( $all_items ),
                ) );
            }

            $page++;

        } while ( $page <= $total_pages && $page <= $max_pages );

        $this->log( 'info', sprintf(
            'Pagination complete: %d items from %d pages',
            count( $all_items ),
            $page - 1
        ), 'api' );

        return array(
            'success'       => true,
            'items'         => $all_items,
            'total'         => $total_items ?: count( $all_items ),
            'pages_fetched' => $page - 1,
        );
    }

    /**
     * Extract items from response data
     *
     * @param array  $data     Response data.
     * @param string $data_key Expected key for items.
     * @return array Items array.
     */
    private function extract_items( array $data, string $data_key ): array {
        // Try primary key
        if ( isset( $data[ $data_key ] ) && is_array( $data[ $data_key ] ) ) {
            return $data[ $data_key ];
        }

        // Try common alternatives
        $alternatives = array( 'results', 'data', 'items', 'records' );
        foreach ( $alternatives as $key ) {
            if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
                return $data[ $key ];
            }
        }

        // Check if response is directly an array of items
        if ( isset( $data[0] ) && is_array( $data[0] ) ) {
            return $data;
        }

        return array();
    }

    /**
     * Perform HTTP request with retry and rate limiting
     *
     * @param string     $method   HTTP method.
     * @param string     $endpoint API endpoint.
     * @param array      $params   Query parameters.
     * @param array|null $body     Request body.
     * @param array      $headers  Additional headers.
     * @return array{success: bool, data?: array, status_code?: int, error?: string, cached?: bool}
     */
    private function request( string $method, string $endpoint, array $params = array(), ?array $body = null, array $headers = array() ): array {
        // Check cache for GET requests
        if ( 'GET' === $method && $this->config['cache_enabled'] ) {
            $cache_key = $this->get_cache_key( $method, $endpoint, $params );
            $cached    = get_transient( $cache_key );

            if ( false !== $cached ) {
                $this->cache_hits++;
                $this->log( 'trace', sprintf( 'Cache hit for %s', $endpoint ), 'api' );

                return array(
                    'success' => true,
                    'data'    => $cached,
                    'cached'  => true,
                );
            }
        }

        // Apply rate limiting
        $this->apply_rate_limit();

        // Build URL
        $url = $this->build_url( $endpoint, $params );

        // Merge headers
        $all_headers = array_merge(
            array( 'Accept' => 'application/json' ),
            $this->default_headers,
            $headers
        );

        // Build request args
        $args = array(
            'method'  => $method,
            'headers' => $all_headers,
            'timeout' => $this->config['timeout'],
        );

        if ( null !== $body ) {
            $args['body'] = wp_json_encode( $body );
            $args['headers']['Content-Type'] = 'application/json';
        }

        // Log request (mask sensitive headers)
        $this->log( 'debug', sprintf( '%s %s', $method, $this->mask_url( $url ) ), 'api' );

        // Perform request with retry
        $attempt = 0;
        $last_error = '';

        while ( $attempt <= $this->config['retry_max'] ) {
            $this->request_count++;
            $this->last_request_time = microtime( true );

            $response = wp_remote_request( $url, $args );

            if ( is_wp_error( $response ) ) {
                $last_error = $response->get_error_message();
                $attempt++;

                if ( $attempt <= $this->config['retry_max'] ) {
                    $delay = $this->calculate_retry_delay( $attempt );
                    $this->log( 'warning', sprintf(
                        'Request failed (attempt %d/%d): %s. Retrying in %dms...',
                        $attempt,
                        $this->config['retry_max'] + 1,
                        $last_error,
                        $delay
                    ), 'api' );
                    usleep( $delay * 1000 );
                }

                continue;
            }

            $status_code = wp_remote_retrieve_response_code( $response );
            $body_raw    = wp_remote_retrieve_body( $response );

            // Handle rate limiting (429) and server errors (5xx) with retry
            if ( $status_code === 429 || $status_code >= 500 ) {
                $attempt++;

                if ( $attempt <= $this->config['retry_max'] ) {
                    $delay = $this->calculate_retry_delay( $attempt );

                    // Check for Retry-After header
                    $retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
                    if ( $retry_after && is_numeric( $retry_after ) ) {
                        $delay = (int) $retry_after * 1000;
                    }

                    $this->log( 'warning', sprintf(
                        'HTTP %d (attempt %d/%d). Retrying in %dms...',
                        $status_code,
                        $attempt,
                        $this->config['retry_max'] + 1,
                        $delay
                    ), 'api' );

                    usleep( $delay * 1000 );
                    continue;
                }
            }

            // Parse response body
            $data = json_decode( $body_raw, true );

            if ( json_last_error() !== JSON_ERROR_NONE && ! empty( $body_raw ) ) {
                $this->log( 'error', sprintf( 'JSON decode error: %s', json_last_error_msg() ), 'api' );

                return array(
                    'success'     => false,
                    'error'       => 'Invalid JSON response: ' . json_last_error_msg(),
                    'status_code' => $status_code,
                );
            }

            // Handle non-2xx responses
            if ( $status_code < 200 || $status_code >= 300 ) {
                $error_message = $this->extract_error_message( $data, $body_raw, $status_code );
                $this->log( 'error', sprintf( 'HTTP %d: %s', $status_code, $error_message ), 'api' );

                return array(
                    'success'     => false,
                    'error'       => $error_message,
                    'status_code' => $status_code,
                    'data'        => $data,
                );
            }

            // Cache successful response
            if ( 'GET' === $method && $this->config['cache_enabled'] ) {
                set_transient( $cache_key, $data, $this->config['cache_duration'] );
            }

            $this->log( 'trace', sprintf( 'Request successful (HTTP %d)', $status_code ), 'api' );

            return array(
                'success'     => true,
                'data'        => $data ?? array(),
                'status_code' => $status_code,
                'cached'      => false,
            );
        }

        $this->log( 'error', sprintf( 'Request failed after %d attempts: %s', $this->config['retry_max'] + 1, $last_error ), 'api' );

        return array(
            'success' => false,
            'error'   => 'Max retries exceeded: ' . $last_error,
        );
    }

    /**
     * Apply rate limiting delay
     */
    private function apply_rate_limit(): void {
        if ( $this->last_request_time > 0 ) {
            $elapsed = ( microtime( true ) - $this->last_request_time ) * 1000;
            $delay   = $this->config['rate_limit_delay_ms'];

            if ( $elapsed < $delay ) {
                $wait = (int) ( $delay - $elapsed );
                $this->log( 'trace', sprintf( 'Rate limiting: waiting %dms', $wait ), 'api' );
                usleep( $wait * 1000 );
            }
        }
    }

    /**
     * Calculate retry delay with exponential backoff
     *
     * @param int $attempt Attempt number (1-based).
     * @return int Delay in milliseconds.
     */
    private function calculate_retry_delay( int $attempt ): int {
        $base  = $this->config['retry_delay_ms'];
        $multi = $this->config['retry_multiplier'];

        return (int) ( $base * pow( $multi, $attempt - 1 ) );
    }

    /**
     * Build full URL with query parameters
     *
     * @param string $endpoint Endpoint path.
     * @param array  $params   Query parameters.
     * @return string Full URL.
     */
    private function build_url( string $endpoint, array $params ): string {
        $url = $this->base_url;

        if ( ! empty( $endpoint ) ) {
            $url .= '/' . ltrim( $endpoint, '/' );
        }

        if ( ! empty( $params ) ) {
            $url .= '?' . http_build_query( $params );
        }

        return $url;
    }

    /**
     * Generate cache key for request
     *
     * @param string $method   HTTP method.
     * @param string $endpoint Endpoint.
     * @param array  $params   Query parameters.
     * @return string Cache key.
     */
    private function get_cache_key( string $method, string $endpoint, array $params ): string {
        ksort( $params );
        $hash = md5( $method . $endpoint . http_build_query( $params ) );

        return 'apprco_api_' . substr( $hash, 0, 16 );
    }

    /**
     * Mask sensitive values in URL for logging
     *
     * @param string $url URL to mask.
     * @return string Masked URL.
     */
    private function mask_url( string $url ): string {
        $patterns = array(
            '/Ocp-Apim-Subscription-Key=[^&]+/' => 'Ocp-Apim-Subscription-Key=***',
            '/api_key=[^&]+/'                   => 'api_key=***',
            '/apikey=[^&]+/i'                   => 'apikey=***',
            '/token=[^&]+/'                     => 'token=***',
            '/secret=[^&]+/'                    => 'secret=***',
        );

        return preg_replace( array_keys( $patterns ), array_values( $patterns ), $url );
    }

    /**
     * Extract error message from response
     *
     * @param array|null $data        Parsed response data.
     * @param string     $body_raw    Raw response body.
     * @param int        $status_code HTTP status code.
     * @return string Error message.
     */
    private function extract_error_message( ?array $data, string $body_raw, int $status_code ): string {
        if ( $data ) {
            $message_keys = array( 'message', 'error', 'error_message', 'detail', 'title' );

            foreach ( $message_keys as $key ) {
                if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
                    return $data[ $key ];
                }
            }

            // Check for nested errors
            if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
                return implode( '; ', array_slice( $data['errors'], 0, 3 ) );
            }
        }

        if ( ! empty( $body_raw ) && strlen( $body_raw ) < 200 ) {
            return $body_raw;
        }

        return sprintf( 'HTTP %d error', $status_code );
    }

    /**
     * Log message
     *
     * @param string $level     Log level.
     * @param string $message   Message.
     * @param string $component Component.
     */
    private function log( string $level, string $message, string $component ): void {
        if ( method_exists( $this->logger, $level ) ) {
            $this->logger->$level( $message, $this->import_id, $component );
        }
    }

    /**
     * Clear the response cache
     */
    public function clear_cache(): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_apprco_api_%',
                '_transient_timeout_apprco_api_%'
            )
        );

        $this->log( 'info', 'API cache cleared', 'api' );
    }

    /**
     * Get client statistics
     *
     * @return array{requests: int, cache_hits: int}
     */
    public function get_stats(): array {
        return array(
            'requests'   => $this->request_count,
            'cache_hits' => $this->cache_hits,
        );
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function get_base_url(): string {
        return $this->base_url;
    }

    /**
     * Update configuration
     *
     * @param array $config Configuration updates.
     */
    public function set_config( array $config ): void {
        $this->config = array_merge( $this->config, $config );
    }
}
