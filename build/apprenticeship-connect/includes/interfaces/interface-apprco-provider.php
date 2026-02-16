<?php
/**
 * Provider Interface - Contract for all vacancy data providers
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface Apprco_Provider_Interface
 *
 * All vacancy providers must implement this interface to integrate
 * with the unified import system.
 */
interface Apprco_Provider_Interface {

    /**
     * Get unique provider identifier
     *
     * @return string Unique slug (e.g., 'uk-gov-apprenticeships', 'recruitment-api')
     */
    public function get_id(): string;

    /**
     * Get human-readable provider name
     *
     * @return string Display name for admin UI
     */
    public function get_name(): string;

    /**
     * Get provider description
     *
     * @return string Short description of the data source
     */
    public function get_description(): string;

    /**
     * Get base API URL
     *
     * @return string Base URL for the provider's API
     */
    public function get_base_url(): string;

    /**
     * Get provider configuration schema
     *
     * Returns an array describing the configuration fields needed for this provider.
     * Each field should include: type, label, description, required, default
     *
     * @return array Configuration schema
     */
    public function get_config_schema(): array;

    /**
     * Set provider configuration
     *
     * @param array $config Configuration values.
     */
    public function set_config( array $config ): void;

    /**
     * Get current configuration
     *
     * @return array Current configuration values
     */
    public function get_config(): array;

    /**
     * Test connection to the provider's API
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function test_connection(): array;

    /**
     * Fetch vacancies from the provider
     *
     * @param array $params Query parameters (pagination, filters, etc.)
     * @return array{success: bool, vacancies?: array, total?: int, total_pages?: int, error?: string}
     */
    public function fetch_vacancies( array $params = array() ): array;

    /**
     * Fetch a single vacancy by reference
     *
     * @param string $reference Vacancy reference/ID.
     * @return array{success: bool, vacancy?: array, error?: string}
     */
    public function fetch_vacancy( string $reference ): array;

    /**
     * Normalize vacancy data to unified format
     *
     * Transforms provider-specific vacancy data into the common format
     * used by the plugin's CPT storage.
     *
     * @param array $vacancy Raw vacancy data from provider.
     * @return array Normalized vacancy data matching CPT meta structure.
     */
    public function normalize_vacancy( array $vacancy ): array;

    /**
     * Get supported endpoints
     *
     * @return array List of endpoint identifiers this provider supports
     */
    public function get_supported_endpoints(): array;

    /**
     * Check if provider is properly configured
     *
     * @return bool True if all required configuration is present
     */
    public function is_configured(): bool;

    /**
     * Get rate limit settings
     *
     * @return array{requests_per_minute: int, delay_ms: int}
     */
    public function get_rate_limits(): array;
}
