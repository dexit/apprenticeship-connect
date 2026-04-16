<?php
/**
 * Provider Registry - Manages all available vacancy providers
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Apprco_Provider_Registry
 *
 * Singleton registry for managing vacancy data providers.
 * Allows registration, lookup, and iteration of providers.
 */
class Apprco_Provider_Registry {

    /**
     * Singleton instance
     *
     * @var Apprco_Provider_Registry|null
     */
    private static $instance = null;

    /**
     * Registered providers
     *
     * @var array<string, Apprco_Provider_Interface>
     */
    private $providers = array();

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger
     */
    private $logger;

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->logger = Apprco_Import_Logger::get_instance();
    }

    /**
     * Get singleton instance
     *
     * @return Apprco_Provider_Registry
     */
    public static function get_instance(): Apprco_Provider_Registry {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a provider
     *
     * @param Apprco_Provider_Interface $provider Provider instance.
     * @return bool True if registered successfully.
     */
    public function register( Apprco_Provider_Interface $provider ): bool {
        $id = $provider->get_id();

        if ( isset( $this->providers[ $id ] ) ) {
            $this->logger->warning(
                sprintf( 'Provider "%s" already registered. Skipping.', $id ),
                null,
                'provider'
            );
            return false;
        }

        $this->providers[ $id ] = $provider;

        $this->logger->debug(
            sprintf( 'Provider "%s" registered successfully.', $id ),
            null,
            'provider',
            array(
                'name'      => $provider->get_name(),
                'endpoints' => $provider->get_supported_endpoints(),
            )
        );

        return true;
    }

    /**
     * Unregister a provider
     *
     * @param string $id Provider ID.
     * @return bool True if unregistered successfully.
     */
    public function unregister( string $id ): bool {
        if ( ! isset( $this->providers[ $id ] ) ) {
            return false;
        }

        unset( $this->providers[ $id ] );

        $this->logger->debug(
            sprintf( 'Provider "%s" unregistered.', $id ),
            null,
            'provider'
        );

        return true;
    }

    /**
     * Get a provider by ID
     *
     * @param string $id Provider ID.
     * @return Apprco_Provider_Interface|null Provider instance or null.
     */
    public function get( string $id ): ?Apprco_Provider_Interface {
        return $this->providers[ $id ] ?? null;
    }

    /**
     * Check if a provider is registered
     *
     * @param string $id Provider ID.
     * @return bool True if provider exists.
     */
    public function has( string $id ): bool {
        return isset( $this->providers[ $id ] );
    }

    /**
     * Get all registered providers
     *
     * @return array<string, Apprco_Provider_Interface> All providers.
     */
    public function get_all(): array {
        return $this->providers;
    }

    /**
     * Get all configured providers
     *
     * @return array<string, Apprco_Provider_Interface> Configured providers only.
     */
    public function get_configured(): array {
        return array_filter(
            $this->providers,
            function ( Apprco_Provider_Interface $provider ) {
                return $provider->is_configured();
            }
        );
    }

    /**
     * Get provider IDs
     *
     * @return array List of provider IDs.
     */
    public function get_ids(): array {
        return array_keys( $this->providers );
    }

    /**
     * Get provider count
     *
     * @return int Number of registered providers.
     */
    public function count(): int {
        return count( $this->providers );
    }

    /**
     * Get providers as options array for select fields
     *
     * @param bool $configured_only Only include configured providers.
     * @return array Associative array of id => name.
     */
    public function get_options( bool $configured_only = false ): array {
        $providers = $configured_only ? $this->get_configured() : $this->providers;
        $options   = array();

        foreach ( $providers as $id => $provider ) {
            $options[ $id ] = $provider->get_name();
        }

        return $options;
    }

    /**
     * Get providers with metadata for admin display
     *
     * @return array Array of provider info.
     */
    public function get_providers_info(): array {
        $info = array();

        foreach ( $this->providers as $id => $provider ) {
            $info[ $id ] = array(
                'id'          => $id,
                'name'        => $provider->get_name(),
                'description' => $provider->get_description(),
                'base_url'    => $provider->get_base_url(),
                'configured'  => $provider->is_configured(),
                'endpoints'   => $provider->get_supported_endpoints(),
                'rate_limits' => $provider->get_rate_limits(),
            );
        }

        return $info;
    }

    /**
     * Load provider configurations from options
     *
     * @param array $configs Array of provider_id => config.
     */
    public function load_configs( array $configs ): void {
        foreach ( $configs as $provider_id => $config ) {
            $provider = $this->get( $provider_id );

            if ( $provider && is_array( $config ) ) {
                $provider->set_config( $config );
            }
        }
    }

    /**
     * Test connection for all configured providers
     *
     * @return array<string, array{success: bool, message: string}> Results per provider.
     */
    public function test_all_connections(): array {
        $results = array();

        foreach ( $this->get_configured() as $id => $provider ) {
            $results[ $id ] = $provider->test_connection();
        }

        return $results;
    }

    /**
     * Reset the registry (mainly for testing)
     */
    public function reset(): void {
        $this->providers = array();
    }
}
