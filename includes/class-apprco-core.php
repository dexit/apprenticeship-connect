<?php
/**
 * Main plugin functionality class with improved sync logic
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main plugin functionality class
 */
class Apprco_Core {

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Plugin instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * API Importer instance
     *
     * @var Apprco_API_Importer|null
     */
    private $importer = null;

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger|null
     */
    private $logger = null;

    /**
     * Import statistics
     *
     * @var array
     */
    private $import_stats = array(
        'created'  => 0,
        'updated'  => 0,
        'skipped'  => 0,
        'deleted'  => 0,
        'errors'   => 0,
    );

    /**
     * Meta field mappings: API field => Post meta key
     * Based on Display Advert API v2 OpenAPI specification
     *
     * @var array
     */
    public const META_MAPPINGS = array(
        // Core vacancy fields (GetVacanciesListResponseItemV2)
        'vacancyReference'             => '_apprco_vacancy_reference',
        'title'                        => '_apprco_title',
        'description'                  => '_apprco_description',
        'numberOfPositions'            => '_apprco_number_of_positions',
        'postedDate'                   => '_apprco_posted_date',
        'closingDate'                  => '_apprco_closing_date',
        'startDate'                    => '_apprco_start_date',
        'hoursPerWeek'                 => '_apprco_hours_per_week',
        'expectedDuration'             => '_apprco_expected_duration',
        'distance'                     => '_apprco_distance',

        // Employer fields
        'employerName'                 => '_apprco_employer_name',
        'employerWebsiteUrl'           => '_apprco_employer_website_url',
        'employerContactName'          => '_apprco_employer_contact_name',
        'employerContactPhone'         => '_apprco_employer_contact_phone',
        'employerContactEmail'         => '_apprco_employer_contact_email',

        // Provider fields
        'providerName'                 => '_apprco_provider_name',
        'ukprn'                        => '_apprco_ukprn',

        // URLs
        'vacancyUrl'                   => '_apprco_vacancy_url',
        'applicationUrl'               => '_apprco_application_url',

        // Apprenticeship info
        'apprenticeshipLevel'          => '_apprco_apprenticeship_level',
        'isDisabilityConfident'        => '_apprco_is_disability_confident',

        // National vacancy
        'isNationalVacancy'            => '_apprco_is_national_vacancy',
        'isNationalVacancyDetails'     => '_apprco_is_national_vacancy_details',

        // Wage object (GetVacancyWageItem)
        'wage.wageType'                => '_apprco_wage_type',
        'wage.wageAmount'              => '_apprco_wage_amount',
        'wage.wageUnit'                => '_apprco_wage_unit',
        'wage.wageAdditionalInformation' => '_apprco_wage_additional_information',
        'wage.workingWeekDescription'  => '_apprco_working_week_description',

        // Address - V2 uses addresses array (GetVacancyAddressItem)
        'addresses.0.addressLine1'     => '_apprco_address_line_1',
        'addresses.0.addressLine2'     => '_apprco_address_line_2',
        'addresses.0.addressLine3'     => '_apprco_address_line_3',
        'addresses.0.addressLine4'     => '_apprco_address_line_4',
        'addresses.0.postcode'         => '_apprco_postcode',
        'addresses.0.latitude'         => '_apprco_latitude',
        'addresses.0.longitude'        => '_apprco_longitude',

        // Course object (GetVacancyCourseItem)
        'course.larsCode'              => '_apprco_course_lars_code',
        'course.title'                 => '_apprco_course_title',
        'course.level'                 => '_apprco_course_level',
        'course.route'                 => '_apprco_course_route',
        'course.type'                  => '_apprco_course_type',

        // Additional fields from single vacancy endpoint (GetVacancyResponseV2)
        'employerDescription'          => '_apprco_employer_description',
        'trainingDescription'          => '_apprco_training_description',
        'additionalTrainingDescription' => '_apprco_additional_training_description',
        'outcomeDescription'           => '_apprco_outcome_description',
        'fullDescription'              => '_apprco_full_description',
        'thingsToConsider'             => '_apprco_things_to_consider',
        'companyBenefitsInformation'   => '_apprco_company_benefits',
    );

    /**
     * Array fields that need special handling (stored as JSON)
     *
     * @var array
     */
    public const ARRAY_FIELDS = array(
        'skills'         => '_apprco_skills',
        'qualifications' => '_apprco_qualifications',
        'addresses'      => '_apprco_addresses',
    );

    /**
     * Get plugin instance
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
     * Constructor
     */
    private function __construct() {
        $this->options  = get_option( 'apprco_plugin_options', array() );
        $this->logger   = new Apprco_Import_Logger();
        $this->importer = new Apprco_API_Importer( $this->options, $this->logger );
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_action( 'apprco_daily_fetch_vacancies', array( $this, 'handle_scheduled_fetch' ) );
        add_action( 'apprco_initial_sync', array( $this, 'handle_initial_sync' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts(): void {
        wp_enqueue_style( 'apprco-style', APPRCO_PLUGIN_URL . 'assets/css/apprco.css', array(), APPRCO_PLUGIN_VERSION );
    }

    /**
     * Handle scheduled fetch
     */
    public function handle_scheduled_fetch(): void {
        $this->fetch_and_save_vacancies( 'cron' );
    }

    /**
     * Handle initial sync after plugin activation
     */
    public function handle_initial_sync(): void {
        // Only run if API is configured
        if ( ! empty( $this->options['api_subscription_key'] ) ) {
            $this->fetch_and_save_vacancies( 'initial' );
        }
    }

    /**
     * Fetch and save vacancies from API
     *
     * @param string $trigger_type Type of trigger (manual, cron, scheduler, initial).
     * @return bool
     */
    public function fetch_and_save_vacancies( string $trigger_type = 'manual' ): bool {
        // Reset stats
        $this->import_stats = array(
            'created'  => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'deleted'  => 0,
            'errors'   => 0,
        );

        // Check if API credentials are configured
        if ( empty( $this->options['api_subscription_key'] ) || empty( $this->options['api_base_url'] ) ) {
            $this->logger->log( 'error', 'API credentials not configured.' );
            return false;
        }

        $import_id = $this->logger->start_import( $trigger_type );

        try {
            // Get existing vacancy references
            $existing_references = $this->get_existing_vacancy_references();
            $this->logger->log( 'info', sprintf( 'Found %d existing vacancies in database.', count( $existing_references ) ), $import_id );

            // Fetch all vacancies from API with pagination
            $vacancies = $this->importer->fetch_all_vacancies();

            if ( false === $vacancies ) {
                $this->logger->log( 'error', 'Failed to fetch vacancies from API.', $import_id );
                $this->logger->end_import( $import_id, 0, 0, 0, 0, 0, 1, 'failed' );
                return false;
            }

            $total_fetched = count( $vacancies );
            $this->logger->log( 'info', sprintf( 'Processing %d vacancies from API.', $total_fetched ), $import_id );

            // Track which references we've seen from the API
            $api_references = array();

            // Process each vacancy
            foreach ( $vacancies as $vacancy ) {
                $result = $this->process_single_vacancy( $vacancy, $import_id, $existing_references );
                if ( isset( $vacancy['vacancyReference'] ) ) {
                    $api_references[] = $vacancy['vacancyReference'];
                }
            }

            // Delete old vacancies not in the API response
            $options = $this->options;
            if ( ! empty( $options['delete_expired'] ) ) {
                $this->delete_old_vacancies( $existing_references, $api_references, $import_id );
            }

            // Update last sync time
            update_option( 'apprco_last_sync', current_time( 'timestamp' ) );

            // Clear object cache for vacancy references
            wp_cache_delete( 'apprco_existing_vacancy_references' );

            // End import with stats
            $this->logger->end_import(
                $import_id,
                $total_fetched,
                $this->import_stats['created'],
                $this->import_stats['updated'],
                $this->import_stats['deleted'],
                $this->import_stats['skipped'],
                $this->import_stats['errors'],
                'completed'
            );

            return true;

        } catch ( Exception $e ) {
            $this->logger->log( 'error', 'Import exception: ' . $e->getMessage(), $import_id );
            $this->logger->end_import( $import_id, 0, 0, 0, 0, 0, 1, 'failed' );
            return false;
        }
    }

    /**
     * Process a single vacancy from the API
     *
     * @param array       $vacancy             Vacancy data from API.
     * @param string      $import_id           Import ID for logging.
     * @param array       $existing_references Existing vacancy references.
     * @return bool
     */
    public function process_single_vacancy( array $vacancy, string $import_id, array $existing_references = array() ): bool {
        if ( ! isset( $vacancy['vacancyReference'] ) ) {
            $this->logger->log( 'warning', 'Vacancy missing reference, skipping.', $import_id );
            $this->import_stats['skipped']++;
            return false;
        }

        $reference = $vacancy['vacancyReference'];

        // Check if vacancy exists
        $existing_post_id = $existing_references[ $reference ] ?? 0;

        // Prepare post data
        $post_data = array(
            'post_title'   => isset( $vacancy['title'] ) ? wp_strip_all_tags( $vacancy['title'] ) : '',
            'post_content' => isset( $vacancy['fullDescription'] ) ? wp_kses_post( $vacancy['fullDescription'] ) : '',
            'post_excerpt' => isset( $vacancy['description'] ) ? wp_strip_all_tags( $vacancy['description'] ) : '',
            'post_status'  => 'publish',
            'post_type'    => 'apprco_vacancy',
            'post_author'  => 1,
        );

        // Set post date from API posted date if available
        if ( isset( $vacancy['postedDate'] ) ) {
            $posted_date = strtotime( $vacancy['postedDate'] );
            if ( $posted_date ) {
                $post_data['post_date']     = wp_date( 'Y-m-d H:i:s', $posted_date );
                $post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $posted_date );
            }
        }

        try {
            if ( $existing_post_id ) {
                // Check if we need to update (compare modified dates if available)
                $should_update = $this->should_update_vacancy( $existing_post_id, $vacancy );

                if ( $should_update ) {
                    $post_data['ID'] = $existing_post_id;
                    $result = wp_update_post( $post_data, true );

                    if ( is_wp_error( $result ) ) {
                        $this->logger->log( 'error', sprintf( 'Failed to update vacancy %s: %s', $reference, $result->get_error_message() ), $import_id );
                        $this->import_stats['errors']++;
                        return false;
                    }

                    $post_id = $existing_post_id;
                    $this->logger->log( 'debug', sprintf( 'Updated vacancy: %s (ID: %d)', $reference, $post_id ), $import_id );
                    $this->import_stats['updated']++;
                } else {
                    $this->logger->log( 'debug', sprintf( 'Skipped unchanged vacancy: %s', $reference ), $import_id );
                    $this->import_stats['skipped']++;
                    return true;
                }
            } else {
                // Create new vacancy
                $post_id = wp_insert_post( $post_data, true );

                if ( is_wp_error( $post_id ) ) {
                    $this->logger->log( 'error', sprintf( 'Failed to create vacancy %s: %s', $reference, $post_id->get_error_message() ), $import_id );
                    $this->import_stats['errors']++;
                    return false;
                }

                $this->logger->log( 'debug', sprintf( 'Created vacancy: %s (ID: %d)', $reference, $post_id ), $import_id );
                $this->import_stats['created']++;
            }

            // Save all meta data
            $this->save_vacancy_meta( $post_id, $vacancy );

            // Assign taxonomies
            $this->assign_vacancy_taxonomies( $post_id, $vacancy );

            return true;

        } catch ( Exception $e ) {
            $this->logger->log( 'error', sprintf( 'Exception processing vacancy %s: %s', $reference, $e->getMessage() ), $import_id );
            $this->import_stats['errors']++;
            return false;
        }
    }

    /**
     * Check if vacancy should be updated
     *
     * @param int   $post_id Post ID.
     * @param array $vacancy Vacancy data from API.
     * @return bool
     */
    private function should_update_vacancy( int $post_id, array $vacancy ): bool {
        // Always update if title changed
        $current_title = get_the_title( $post_id );
        if ( isset( $vacancy['title'] ) && $current_title !== $vacancy['title'] ) {
            return true;
        }

        // Check closing date - if it changed, update
        $current_closing = get_post_meta( $post_id, '_apprco_closing_date', true );
        if ( isset( $vacancy['closingDate'] ) && $current_closing !== $vacancy['closingDate'] ) {
            return true;
        }

        // Check positions - if changed, update
        $current_positions = get_post_meta( $post_id, '_apprco_number_of_positions', true );
        if ( isset( $vacancy['numberOfPositions'] ) && $current_positions != $vacancy['numberOfPositions'] ) {
            return true;
        }

        // Check description for changes
        $current_desc = get_post_meta( $post_id, '_apprco_vacancy_description_short', true );
        if ( isset( $vacancy['description'] ) && $current_desc !== $vacancy['description'] ) {
            return true;
        }

        // Default: don't update to save resources
        return false;
    }

    /**
     * Save vacancy meta data using the mapping
     *
     * @param int   $post_id Post ID.
     * @param array $vacancy Vacancy data from API.
     */
    private function save_vacancy_meta( int $post_id, array $vacancy ): void {
        // Save mapped scalar fields
        foreach ( self::META_MAPPINGS as $api_path => $meta_key ) {
            $value = $this->get_nested_value( $vacancy, $api_path );

            if ( $value !== null ) {
                $value = $this->sanitize_meta_value( $value, $meta_key );
                update_post_meta( $post_id, $meta_key, $value );
            }
        }

        // Save array fields (skills, qualifications, addresses) as JSON
        foreach ( self::ARRAY_FIELDS as $api_field => $meta_key ) {
            if ( isset( $vacancy[ $api_field ] ) && is_array( $vacancy[ $api_field ] ) ) {
                update_post_meta( $post_id, $meta_key, wp_json_encode( $vacancy[ $api_field ] ) );
            }
        }
    }

    /**
     * Sanitize meta value based on field type
     *
     * @param mixed  $value    Value to sanitize.
     * @param string $meta_key Meta key for context.
     * @return mixed Sanitized value.
     */
    private function sanitize_meta_value( $value, string $meta_key ) {
        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        if ( is_array( $value ) ) {
            return wp_json_encode( $value );
        }

        if ( is_numeric( $value ) ) {
            return $value;
        }

        if ( is_string( $value ) ) {
            // HTML content fields
            if ( strpos( $meta_key, 'description' ) !== false ||
                 strpos( $meta_key, 'benefits' ) !== false ||
                 strpos( $meta_key, 'consider' ) !== false ) {
                return wp_kses_post( $value );
            }

            // URL fields
            if ( strpos( $meta_key, 'url' ) !== false || strpos( $meta_key, 'Url' ) !== false ) {
                return esc_url_raw( $value );
            }

            // Default text sanitization
            return sanitize_text_field( $value );
        }

        return $value;
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array  $array Array to search.
     * @param string $path  Dot-notation path (e.g., 'address.postcode').
     * @return mixed|null
     */
    private function get_nested_value( array $array, string $path ) {
        $keys = explode( '.', $path );
        $value = $array;

        foreach ( $keys as $key ) {
            if ( is_array( $value ) && array_key_exists( $key, $value ) ) {
                $value = $value[ $key ];
            } elseif ( is_object( $value ) && isset( $value->$key ) ) {
                $value = $value->$key;
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Assign taxonomies to vacancy
     *
     * @param int   $post_id Post ID.
     * @param array $vacancy Vacancy data from API.
     */
    private function assign_vacancy_taxonomies( int $post_id, array $vacancy ): void {
        // Apprenticeship Level
        $level = $this->get_nested_value( $vacancy, 'apprenticeshipLevel' );
        if ( ! $level ) {
            $level = $this->get_nested_value( $vacancy, 'course.level' );
            if ( $level ) {
                $level = 'Level ' . $level;
            }
        }
        if ( $level ) {
            wp_set_object_terms( $post_id, $level, 'apprco_level' );
        }

        // Course Route
        $route = $this->get_nested_value( $vacancy, 'course.route' );
        if ( $route ) {
            wp_set_object_terms( $post_id, $route, 'apprco_route' );
        }

        // Employer
        $employer = $this->get_nested_value( $vacancy, 'employerName' );
        if ( $employer && empty( $vacancy['isEmployerAnonymous'] ) ) {
            wp_set_object_terms( $post_id, $employer, 'apprco_employer' );
        }
    }

    /**
     * Get existing vacancy references from database
     *
     * @return array Map of reference => post_id.
     */
    private function get_existing_vacancy_references(): array {
        $cache_key      = 'apprco_existing_vacancy_references';
        $cached_results = wp_cache_get( $cache_key );

        if ( false !== $cached_results ) {
            return $cached_results;
        }

        $results = array();

        $query = new WP_Query(
            array(
                'post_type'      => 'apprco_vacancy',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => '_apprco_vacancy_reference',
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post_id ) {
                $ref = get_post_meta( $post_id, '_apprco_vacancy_reference', true );
                if ( $ref ) {
                    $results[ $ref ] = $post_id;
                }
            }
        }

        wp_cache_set( $cache_key, $results, '', 3600 );

        return $results;
    }

    /**
     * Delete old vacancies not in API response
     *
     * @param array  $existing_references Existing vacancy references.
     * @param array  $api_references      References from API.
     * @param string $import_id           Import ID for logging.
     */
    private function delete_old_vacancies( array $existing_references, array $api_references, string $import_id ): void {
        $options         = $this->options;
        $expire_days     = $options['expire_after_days'] ?? 7;
        $api_ref_set     = array_flip( $api_references );
        $deleted_count   = 0;

        foreach ( $existing_references as $ref => $post_id ) {
            if ( isset( $api_ref_set[ $ref ] ) ) {
                continue; // Still in API
            }

            // Check if vacancy has expired (closing date passed)
            $closing_date = get_post_meta( $post_id, '_apprco_closing_date', true );
            $should_delete = false;

            if ( $closing_date ) {
                $closing_timestamp = strtotime( $closing_date );
                $expire_timestamp  = $closing_timestamp + ( $expire_days * DAY_IN_SECONDS );

                if ( time() > $expire_timestamp ) {
                    $should_delete = true;
                }
            } else {
                // No closing date and not in API - delete after expire_days from last sync
                $last_modified = get_post_modified_time( 'U', false, $post_id );
                if ( time() > $last_modified + ( $expire_days * DAY_IN_SECONDS ) ) {
                    $should_delete = true;
                }
            }

            if ( $should_delete ) {
                wp_delete_post( $post_id, true );
                $deleted_count++;
                $this->logger->log( 'debug', sprintf( 'Deleted expired vacancy: %s (ID: %d)', $ref, $post_id ), $import_id );
            }
        }

        $this->import_stats['deleted'] = $deleted_count;

        if ( $deleted_count > 0 ) {
            $this->logger->log( 'info', sprintf( 'Deleted %d expired vacancies.', $deleted_count ), $import_id );
        }
    }

    /**
     * Manual sync function
     *
     * @return bool
     */
    public function manual_sync(): bool {
        return $this->fetch_and_save_vacancies( 'manual' );
    }

    /**
     * Get sync status
     *
     * @return array
     */
    public function get_sync_status(): array {
        $last_sync       = get_option( 'apprco_last_sync' );
        $total_vacancies = wp_count_posts( 'apprco_vacancy' );
        $logger          = new Apprco_Import_Logger();
        $log_stats       = $logger->get_stats();

        return array(
            'last_sync'        => $last_sync,
            'last_sync_human'  => $last_sync ? human_time_diff( $last_sync ) . ' ago' : 'Never',
            'total_vacancies'  => $total_vacancies->publish ?? 0,
            'draft_vacancies'  => $total_vacancies->draft ?? 0,
            'is_configured'    => ! empty( $this->options['api_subscription_key'] ),
            'last_import'      => $log_stats['last_run'],
            'total_imports'    => $log_stats['total_runs'],
        );
    }

    /**
     * Allow overriding options for a one-off sync
     *
     * @param array $overrides Options to override.
     */
    public function override_options_for_sync( array $overrides ): void {
        foreach ( $overrides as $key => $value ) {
            if ( $value !== '' && $value !== null ) {
                $this->options[ $key ] = $value;
            }
        }

        // Update importer with new options
        $this->importer = new Apprco_API_Importer( $this->options, $this->logger );
    }

    /**
     * Get import statistics
     *
     * @return array
     */
    public function get_import_stats(): array {
        return $this->import_stats;
    }

    /**
     * Clear all transient caches
     */
    public function clear_cache(): void {
        $this->importer->clear_cache();
        wp_cache_delete( 'apprco_existing_vacancy_references' );
    }

    /**
     * Test API connection
     *
     * @return array
     */
    public function test_api_connection(): array {
        return $this->importer->test_connection();
    }
}
