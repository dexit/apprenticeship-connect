<?php
/**
 * API Importer class with paginated fetching, rate limiting, and transient caching
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles API data fetching with pagination, rate limiting, and caching
 */
class Apprco_API_Importer {

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger
     */
    private $logger;

    /**
     * Rate limit delay in microseconds (200ms between requests)
     *
     * @var int
     */
    private const RATE_LIMIT_DELAY = 200000;

    /**
     * Max retries for failed requests
     *
     * @var int
     */
    private const MAX_RETRIES = 3;

    /**
     * Transient cache duration in seconds (5 minutes for API responses)
     *
     * @var int
     */
    private const CACHE_DURATION = 300;

    /**
     * Page size for API requests
     *
     * @var int
     */
    private const PAGE_SIZE = 100;

    /**
     * API version header
     *
     * @var string
     */
    private const API_VERSION = '2';

    /**
     * Constructor
     *
     * @param array                $options Optional. Plugin options override.
     * @param Apprco_Import_Logger $logger  Optional. Logger instance.
     */
    public function __construct( array $options = array(), Apprco_Import_Logger $logger = null ) {
        $this->options = ! empty( $options ) ? $options : get_option( 'apprco_plugin_options', array() );
        $this->logger  = $logger ?? new Apprco_Import_Logger();
    }

    /**
     * Override options for sync
     *
     * @param array $overrides Options to override.
     */
    public function override_options( array $overrides ): void {
        foreach ( $overrides as $key => $value ) {
            if ( $value !== '' && $value !== null ) {
                $this->options[ $key ] = $value;
            }
        }
    }

    /**
     * Fetch all vacancies from API with pagination
     *
     * @param array $params Optional. Additional query parameters.
     * @return array|false Array of vacancies or false on failure.
     */
    public function fetch_all_vacancies( array $params = array() ) {
        if ( empty( $this->options['api_subscription_key'] ) || empty( $this->options['api_base_url'] ) ) {
            $this->logger->log( 'error', 'API credentials not configured.' );
            return false;
        }

        $import_id = $this->logger->start_import();
        $this->logger->log( 'info', 'Starting paginated API fetch...', $import_id );

        $all_vacancies    = array();
        $page_number      = 1;
        $total_pages      = 1;
        $total_fetched    = 0;
        $retry_count      = 0;
        $consecutive_empty = 0;

        // Build default params
        $default_params = array(
            'PageSize'             => self::PAGE_SIZE,
            'Sort'                 => 'AgeDesc',
            'FilterBySubscription' => 'true',
        );

        if ( ! empty( $this->options['api_ukprn'] ) ) {
            $default_params['Ukprn'] = $this->options['api_ukprn'];
        }

        $params = array_merge( $default_params, $params );

        do {
            $params['PageNumber'] = $page_number;

            // Check transient cache first
            $cache_key     = $this->get_cache_key( $params );
            $cached_result = get_transient( $cache_key );

            if ( false !== $cached_result ) {
                $this->logger->log( 'info', sprintf( 'Cache hit for page %d', $page_number ), $import_id );
                $page_data = $cached_result;
            } else {
                // Apply rate limiting
                if ( $page_number > 1 ) {
                    usleep( self::RATE_LIMIT_DELAY );
                }

                $page_data = $this->fetch_single_page( $params, $import_id );

                if ( false === $page_data ) {
                    $retry_count++;
                    if ( $retry_count >= self::MAX_RETRIES ) {
                        $this->logger->log( 'error', sprintf( 'Max retries (%d) reached on page %d. Stopping.', self::MAX_RETRIES, $page_number ), $import_id );
                        break;
                    }
                    $this->logger->log( 'warning', sprintf( 'Retry %d/%d for page %d', $retry_count, self::MAX_RETRIES, $page_number ), $import_id );
                    usleep( self::RATE_LIMIT_DELAY * ( $retry_count + 1 ) ); // Exponential backoff
                    continue;
                }

                // Cache successful response
                set_transient( $cache_key, $page_data, self::CACHE_DURATION );
                $retry_count = 0;
            }

            // Extract vacancies and pagination info
            if ( isset( $page_data['vacancies'] ) && is_array( $page_data['vacancies'] ) ) {
                $page_vacancies = $page_data['vacancies'];
                $count          = count( $page_vacancies );

                if ( $count === 0 ) {
                    $consecutive_empty++;
                    if ( $consecutive_empty >= 2 ) {
                        $this->logger->log( 'info', 'Two consecutive empty pages. Stopping pagination.', $import_id );
                        break;
                    }
                } else {
                    $consecutive_empty = 0;
                    $all_vacancies     = array_merge( $all_vacancies, $page_vacancies );
                    $total_fetched    += $count;
                }

                $this->logger->log( 'info', sprintf( 'Page %d: fetched %d vacancies (total: %d)', $page_number, $count, $total_fetched ), $import_id );
            }

            // Update total pages from response
            if ( isset( $page_data['totalPages'] ) ) {
                $total_pages = (int) $page_data['totalPages'];
            }

            $page_number++;

            // Safety limit to prevent infinite loops
            if ( $page_number > 500 ) {
                $this->logger->log( 'warning', 'Safety limit reached (500 pages). Stopping.', $import_id );
                break;
            }

        } while ( $page_number <= $total_pages );

        $this->logger->log( 'info', sprintf( 'Pagination complete. Total vacancies fetched: %d', $total_fetched ), $import_id );
        $this->logger->end_import( $import_id, $total_fetched );

        return $all_vacancies;
    }

    /**
     * Fetch a single page from the API
     *
     * @param array  $params    Query parameters.
     * @param string $import_id Import ID for logging.
     * @return array|false Page data or false on failure.
     */
    private function fetch_single_page( array $params, string $import_id ) {
        $api_url = $this->options['api_base_url'] . '/vacancy?' . http_build_query( $params );

        $headers = array(
            'X-Version'                 => self::API_VERSION,
            'Ocp-Apim-Subscription-Key' => $this->options['api_subscription_key'],
            'Accept'                    => 'application/json',
            'Content-Type'              => 'application/json',
        );

        $args = array(
            'headers' => $headers,
            'timeout' => 60,
        );

        $response = wp_remote_get( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            $this->logger->log( 'error', 'API request failed: ' . $response->get_error_message(), $import_id );
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            $this->logger->log( 'error', sprintf( 'API returned HTTP %d', $status_code ), $import_id );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->logger->log( 'error', 'JSON decode error: ' . json_last_error_msg(), $import_id );
            return false;
        }

        return $data;
    }

    /**
     * Generate cache key for transients
     *
     * @param array $params Query parameters.
     * @return string Cache key.
     */
    private function get_cache_key( array $params ): string {
        ksort( $params );
        return 'apprco_api_' . md5( http_build_query( $params ) );
    }

    /**
     * Clear all API transient caches
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

        $this->logger->log( 'info', 'API cache cleared.' );
    }

    /**
     * Test API connection
     *
     * @return array{success: bool, message: string, vacancy_count?: int}
     */
    public function test_connection(): array {
        if ( empty( $this->options['api_subscription_key'] ) || empty( $this->options['api_base_url'] ) ) {
            return array(
                'success' => false,
                'message' => 'API credentials not configured.',
            );
        }

        $params = array(
            'PageNumber'           => 1,
            'PageSize'             => 10,
            'Sort'                 => 'AgeDesc',
            'FilterBySubscription' => 'true',
        );

        if ( ! empty( $this->options['api_ukprn'] ) ) {
            $params['Ukprn'] = $this->options['api_ukprn'];
        }

        $result = $this->fetch_single_page( $params, 'test' );

        if ( false === $result ) {
            return array(
                'success' => false,
                'message' => 'API connection failed. Check credentials and try again.',
            );
        }

        $total = isset( $result['total'] ) ? (int) $result['total'] : 0;

        return array(
            'success'       => true,
            'message'       => sprintf( 'API connection successful! Found %d total vacancies.', $total ),
            'vacancy_count' => $total,
        );
    }
}
