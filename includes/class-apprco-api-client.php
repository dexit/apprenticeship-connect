<?php
/**
 * API Client - HTTP client with rate limiting, retry, and resilience monitoring
 *
 * @package ApprenticeshipConnect
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Apprco_API_Client {

    private const DEFAULTS = array(
        'timeout'              => 60,
        'retry_max'            => 5,
        'retry_delay_ms'       => 1000,
        'retry_multiplier'     => 2,
        'rate_limit_delay_ms'  => 500,
        'cache_duration'       => 300,
        'cache_enabled'        => true,
    );

    private $config;
    private $logger;
    private $base_url;
    private $default_headers = array();
    private $last_request_time = 0;
    private $request_count = 0;
    private $cache_hits = 0;
    private $retry_count = 0;
    private $backoff_active = false;
    private $import_id = null;

    public function __construct( string $base_url, array $config = array() ) {
        $this->base_url = rtrim( $base_url, '/' );
        $this->config   = array_merge( self::DEFAULTS, $config );
        $this->logger   = Apprco_Import_Logger::get_instance();
    }

    public function set_default_headers( array $headers ): void {
        $this->default_headers = $headers;
    }

    public function set_import_id( string $import_id ): void {
        $this->import_id = $import_id;
    }

    public function get( string $endpoint = '', array $params = array(), array $headers = array() ): array {
        return $this->request( 'GET', $endpoint, $params, null, $headers );
    }

    private function request( string $method, string $endpoint, array $params = array(), ?array $body = null, array $headers = array() ): array {
        $url = $this->build_url( $endpoint, $params );
        $cache_key = 'apprco_api_' . md5( $method . $endpoint . serialize( $params ) );

        if ( 'GET' === $method && $this->config['cache_enabled'] ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                $this->cache_hits++;
                return array( 'success' => true, 'data' => $cached, 'cached' => true );
            }
        }

        $all_headers = array_merge( $this->default_headers, $headers );
        $args = array(
            'method'  => $method,
            'headers' => $all_headers,
            'timeout' => $this->config['timeout'],
        );

        $attempt = 0;
        while ( $attempt <= $this->config['retry_max'] ) {
            $this->apply_rate_limit();
            $this->request_count++;
            $this->last_request_time = microtime( true );

            $response = wp_remote_request( $url, $args );

            if ( is_wp_error( $response ) ) {
                $attempt++;
                if ( $attempt <= $this->config['retry_max'] ) {
                    $this->retry_count++;
                    $delay = $this->calculate_retry_delay( $attempt );
                    $this->log( 'warning', "Retry $attempt: " . $response->get_error_message(), 'api' );
                    usleep( $delay * 1000 );
                    continue;
                }
                break;
            }

            $status_code = wp_remote_retrieve_response_code( $response );
            if ( $status_code === 429 || $status_code >= 500 ) {
                $attempt++;
                if ( $attempt <= $this->config['retry_max'] ) {
                    $this->backoff_active = true;
                    $this->retry_count++;
                    $delay = $this->calculate_retry_delay( $attempt );
                    $retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
                    if ( $retry_after && is_numeric( $retry_after ) ) $delay = (int) $retry_after * 1000;
                    $this->log( 'warning', "HTTP $status_code - Resilience Backoff Active. Retrying in {$delay}ms...", 'api' );
                    usleep( $delay * 1000 );
                    continue;
                }
            }

            $this->backoff_active = false;
            $body_raw = wp_remote_retrieve_body( $response );
            $data = json_decode( $body_raw, true );

            if ( $status_code < 200 || $status_code >= 300 ) {
                return array( 'success' => false, 'error' => "HTTP $status_code", 'data' => $data );
            }

            if ( 'GET' === $method && $this->config['cache_enabled'] ) {
                set_transient( $cache_key, $data, $this->config['cache_duration'] );
            }

            return array( 'success' => true, 'data' => $data, 'cached' => false );
        }

        return array( 'success' => false, 'error' => 'Max retries exceeded' );
    }

    public function fetch_all_pages( string $endpoint, array $params, string $page_param, string $data_path, ?string $total_path = null, int $max_pages = 0 ): array {
        $all_items = array();
        $page = 1;
        $total_pages = 1;

        do {
            $current_params = array_merge( $params, array( $page_param => $page ) );
            $response = $this->get( $endpoint, $current_params );
            if ( ! $response['success'] ) return array( 'success' => false, 'error' => $response['error'], 'items' => $all_items );

            $items = $this->get_nested_value( $response['data'], $data_path );
            if ( ! is_array( $items ) ) break;
            $all_items = array_merge( $all_items, $items );

            if ( $total_path && $page === 1 ) {
                $total_items = (int) $this->get_nested_value( $response['data'], $total_path );
                if ( count($items) > 0 ) $total_pages = ceil( $total_items / count($items) );
            }

            $page++;
            if ( $max_pages > 0 && $page > $max_pages ) break;
        } while ( $page <= $total_pages );

        return array( 'success' => true, 'items' => $all_items );
    }

    public function get_stats(): array {
        return array(
            'requests' => $this->request_count,
            'cache_hits' => $this->cache_hits,
            'retries' => $this->retry_count,
            'backoff' => $this->backoff_active
        );
    }

    private function apply_rate_limit(): void {
        if ( $this->last_request_time > 0 ) {
            $elapsed = ( microtime( true ) - $this->last_request_time ) * 1000;
            if ( $elapsed < $this->config['rate_limit_delay_ms'] ) usleep( (int)($this->config['rate_limit_delay_ms'] - $elapsed) * 1000 );
        }
    }

    private function calculate_retry_delay( int $attempt ): int {
        return (int)( $this->config['retry_delay_ms'] * pow( $this->config['retry_multiplier'], $attempt - 1 ) );
    }

    private function build_url( string $endpoint, array $params ): string {
        $url = $this->base_url . ( ! empty($endpoint) ? '/' . ltrim($endpoint, '/') : '' );
        return empty($params) ? $url : add_query_arg( $params, $url );
    }

    private function get_nested_value( array $array, string $path ) {
        $path = preg_replace( '/\[(\d+)\]/', '.$1', $path );
        $keys = explode( '.', $path );
        $val = $array;
        foreach ( $keys as $k ) {
            if ( is_array($val) && isset($val[$k]) ) $val = $val[$k];
            else return null;
        }
        return $val;
    }

    private function log( string $level, string $msg, string $comp ): void {
        if ( $this->logger ) $this->logger->$level( $msg, $this->import_id, $comp );
    }
}
