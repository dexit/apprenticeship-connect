<?php
/**
 * Import Wizard - Multi-step import process with preview
 *
 * @package ApprenticeshipConnect
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Apprco_Import_Wizard
 *
 * Provides a multi-step import wizard:
 * 1. Test Connection - Verify API credentials and connection
 * 2. Configure Job - Set up import parameters and mapping
 * 3. Preview - Show first 10 vacancies before import
 * 4. Execute - Run full import with progress tracking
 */
class Apprco_Import_Wizard {

    /**
     * Wizard steps
     */
    public const STEPS = array(
        'connect'   => 'Test Connection',
        'configure' => 'Configure Import',
        'preview'   => 'Preview Data',
        'execute'   => 'Execute Import',
    );

    /**
     * Singleton instance
     *
     * @var Apprco_Import_Wizard|null
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var Apprco_Import_Logger
     */
    private $logger;

    /**
     * Provider registry
     *
     * @var Apprco_Provider_Registry
     */
    private $registry;

    /**
     * Geocoder instance
     *
     * @var Apprco_Geocoder
     */
    private $geocoder;

    /**
     * Employer manager
     *
     * @var Apprco_Employer
     */
    private $employer;

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->logger   = Apprco_Import_Logger::get_instance();
        $this->registry = Apprco_Provider_Registry::get_instance();
        $this->geocoder = Apprco_Geocoder::get_instance();
        $this->employer = Apprco_Employer::get_instance();

        $this->init_ajax_handlers();
    }

    /**
     * Get singleton instance
     *
     * @return Apprco_Import_Wizard
     */
    public static function get_instance(): Apprco_Import_Wizard {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize AJAX handlers
     */
    private function init_ajax_handlers(): void {
        add_action( 'wp_ajax_apprco_wizard_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_apprco_wizard_preview', array( $this, 'ajax_preview' ) );
        add_action( 'wp_ajax_apprco_wizard_execute', array( $this, 'ajax_execute' ) );
        add_action( 'wp_ajax_apprco_wizard_get_status', array( $this, 'ajax_get_status' ) );
        add_action( 'wp_ajax_apprco_wizard_cancel', array( $this, 'ajax_cancel' ) );
    }

    /**
     * Step 1: Test connection to provider API
     *
     * @param string $provider_id Provider ID.
     * @param array  $config      Provider configuration.
     * @return array{success: bool, message: string, data?: array}
     */
    public function test_connection( string $provider_id, array $config = array() ): array {
        $this->logger->info( sprintf( 'Testing connection to provider: %s', $provider_id ), null, 'wizard' );

        $provider = $this->registry->get( $provider_id );

        if ( ! $provider ) {
            return array(
                'success' => false,
                'message' => sprintf( 'Provider "%s" not found.', $provider_id ),
            );
        }

        // Apply configuration if provided
        if ( ! empty( $config ) ) {
            $provider->set_config( $config );
        }

        if ( ! $provider->is_configured() ) {
            return array(
                'success' => false,
                'message' => 'Provider not fully configured. Please check all required fields.',
            );
        }

        // Run connection test
        $result = $provider->test_connection();

        if ( $result['success'] ) {
            $this->logger->info( 'Connection test successful', null, 'wizard', $result['data'] ?? array() );
        } else {
            $this->logger->warning( 'Connection test failed: ' . ( $result['message'] ?? 'Unknown error' ), null, 'wizard' );
        }

        return $result;
    }

    /**
     * Step 2: Get import configuration form
     *
     * @param string $provider_id Provider ID.
     * @return array Configuration schema and current values.
     */
    public function get_config_form( string $provider_id ): array {
        $provider = $this->registry->get( $provider_id );

        if ( ! $provider ) {
            return array(
                'success' => false,
                'error'   => 'Provider not found.',
            );
        }

        return array(
            'success' => true,
            'schema'  => $provider->get_config_schema(),
            'current' => $provider->get_config(),
            'endpoints' => $provider->get_supported_endpoints(),
        );
    }

    /**
     * Step 3: Preview first N vacancies
     *
     * @param string $provider_id Provider ID.
     * @param array  $params      Fetch parameters.
     * @param int    $limit       Number of vacancies to preview.
     * @return array{success: bool, vacancies?: array, total?: int, error?: string}
     */
    public function preview( string $provider_id, array $params = array(), int $limit = 10 ): array {
        $this->logger->info( sprintf( 'Previewing %d vacancies from %s', $limit, $provider_id ), null, 'wizard' );

        $provider = $this->registry->get( $provider_id );

        if ( ! $provider ) {
            return array(
                'success' => false,
                'error'   => 'Provider not found.',
            );
        }

        // Fetch limited set for preview
        $params['PageNumber'] = 1;
        $params['PageSize']   = $limit;

        $result = $provider->fetch_vacancies( $params );

        if ( ! $result['success'] ) {
            return $result;
        }

        // Normalize vacancies for display
        $vacancies = array();
        foreach ( $result['vacancies'] ?? array() as $raw_vacancy ) {
            $normalized = $provider->normalize_vacancy( $raw_vacancy );

            // Enrich with geocoding if needed
            $normalized = $this->geocoder->enrich_vacancy( $normalized );

            $vacancies[] = $this->format_for_preview( $normalized );
        }

        $this->logger->debug( sprintf( 'Preview returned %d vacancies', count( $vacancies ) ), null, 'wizard' );

        return array(
            'success'     => true,
            'vacancies'   => $vacancies,
            'total'       => $result['total'] ?? count( $vacancies ),
            'total_pages' => $result['total_pages'] ?? 1,
            'preview_count' => count( $vacancies ),
        );
    }

    /**
     * Format vacancy for preview display
     *
     * @param array $vacancy Normalized vacancy.
     * @return array Simplified vacancy for preview.
     */
    private function format_for_preview( array $vacancy ): array {
        return array(
            'reference'       => $vacancy['vacancy_reference'] ?? '',
            'title'           => $vacancy['title'] ?? '',
            'employer'        => $vacancy['employer_name'] ?? '',
            'location'        => $this->format_location( $vacancy ),
            'wage'            => $this->format_wage( $vacancy ),
            'closing_date'    => $vacancy['closing_date'] ?? '',
            'posted_date'     => $vacancy['posted_date'] ?? '',
            'level'           => $vacancy['apprenticeship_level'] ?? '',
            'course'          => $vacancy['course_title'] ?? '',
            'positions'       => $vacancy['positions_available'] ?? 1,
            'has_coordinates' => ! empty( $vacancy['latitude'] ) && ! empty( $vacancy['longitude'] ),
        );
    }

    /**
     * Format location for display
     *
     * @param array $vacancy Vacancy data.
     * @return string Formatted location.
     */
    private function format_location( array $vacancy ): string {
        $parts = array();

        $address = $vacancy['primary_address'] ?? array();

        if ( ! empty( $address['address_line1'] ) ) {
            $parts[] = $address['address_line1'];
        }
        if ( ! empty( $address['address_line3'] ) ) {
            $parts[] = $address['address_line3'];
        }
        if ( ! empty( $address['postcode'] ) ) {
            $parts[] = $address['postcode'];
        }

        return ! empty( $parts ) ? implode( ', ', $parts ) : 'Location not specified';
    }

    /**
     * Format wage for display
     *
     * @param array $vacancy Vacancy data.
     * @return string Formatted wage.
     */
    private function format_wage( array $vacancy ): string {
        if ( ! empty( $vacancy['wage_text'] ) ) {
            return $vacancy['wage_text'];
        }

        if ( ! empty( $vacancy['wage_amount'] ) ) {
            return sprintf( '£%s %s', number_format( $vacancy['wage_amount'], 2 ), $vacancy['wage_unit'] ?? '' );
        }

        return $vacancy['wage_type'] ?? 'Wage not specified';
    }

    /**
     * Step 4: Execute full import
     *
     * @param string   $provider_id Provider ID.
     * @param array    $params      Fetch parameters.
     * @param callable $on_progress Optional progress callback.
     * @return array{success: bool, imported?: int, skipped?: int, errors?: int, message?: string}
     */
    public function execute( string $provider_id, array $params = array(), ?callable $on_progress = null ): array {
        $import_id = $this->logger->start_import();
        $this->logger->info( sprintf( 'Starting import from %s', $provider_id ), $import_id, 'wizard' );

        $provider = $this->registry->get( $provider_id );

        if ( ! $provider ) {
            $this->logger->error( 'Provider not found: ' . $provider_id, $import_id, 'wizard' );
            return array(
                'success' => false,
                'error'   => 'Provider not found.',
            );
        }

        // Store import status for polling
        $status_key = 'apprco_import_status_' . $import_id;
        update_option( $status_key, array(
            'status'    => 'running',
            'phase'     => 'fetching',
            'imported'  => 0,
            'skipped'   => 0,
            'errors'    => 0,
            'total'     => 0,
            'started'   => time(),
            'provider'  => $provider_id,
        ), false );

        // Fetch all vacancies
        $fetch_result = $provider->fetch_vacancies( $params );

        if ( ! $fetch_result['success'] ) {
            $this->update_import_status( $status_key, array(
                'status' => 'failed',
                'error'  => $fetch_result['error'] ?? 'Fetch failed',
            ) );
            $this->logger->error( 'Import fetch failed', $import_id, 'wizard' );

            return array(
                'success' => false,
                'error'   => $fetch_result['error'] ?? 'Failed to fetch vacancies',
            );
        }

        $vacancies = $fetch_result['vacancies'] ?? array();
        $total     = count( $vacancies );

        $this->update_import_status( $status_key, array(
            'phase' => 'processing',
            'total' => $total,
        ) );

        $this->logger->info( sprintf( 'Processing %d vacancies', $total ), $import_id, 'wizard' );

        // Process each vacancy
        $imported = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ( $vacancies as $index => $raw_vacancy ) {
            try {
                $normalized = $provider->normalize_vacancy( $raw_vacancy );

                // Enrich with geocoding
                $normalized = $this->geocoder->enrich_vacancy( $normalized );

                // Save employer
                $this->employer->save_from_vacancy( $normalized, $provider_id );

                // Save vacancy as CPT
                $result = $this->save_vacancy( $normalized );

                if ( $result['success'] ) {
                    if ( $result['action'] === 'created' || $result['action'] === 'updated' ) {
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $errors++;
                }

            } catch ( Exception $e ) {
                $errors++;
                $this->logger->error( sprintf( 'Error processing vacancy: %s', $e->getMessage() ), $import_id, 'wizard' );
            }

            // Update status every 10 items
            if ( ( $index + 1 ) % 10 === 0 ) {
                $this->update_import_status( $status_key, array(
                    'imported' => $imported,
                    'skipped'  => $skipped,
                    'errors'   => $errors,
                    'current'  => $index + 1,
                ) );

                if ( is_callable( $on_progress ) ) {
                    call_user_func( $on_progress, array(
                        'current'  => $index + 1,
                        'total'    => $total,
                        'imported' => $imported,
                        'skipped'  => $skipped,
                        'errors'   => $errors,
                    ) );
                }
            }
        }

        // Final status update
        $this->update_import_status( $status_key, array(
            'status'    => 'completed',
            'phase'     => 'done',
            'imported'  => $imported,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'current'   => $total,
            'completed' => time(),
        ) );

        $this->logger->end_import( $import_id, $imported );

        $this->logger->info( sprintf(
            'Import complete: %d imported, %d skipped, %d errors',
            $imported,
            $skipped,
            $errors
        ), $import_id, 'wizard' );

        return array(
            'success'   => true,
            'imported'  => $imported,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'total'     => $total,
            'import_id' => $import_id,
        );
    }

    /**
     * Save vacancy to CPT
     *
     * @param array $vacancy Normalized vacancy data.
     * @return array{success: bool, action: string, post_id?: int, error?: string}
     */
    private function save_vacancy( array $vacancy ): array {
        $reference = $vacancy['vacancy_reference'] ?? '';

        if ( empty( $reference ) ) {
            return array(
                'success' => false,
                'action'  => 'error',
                'error'   => 'Missing vacancy reference',
            );
        }

        // Check if vacancy exists
        $existing = $this->find_existing_vacancy( $reference );

        // Prepare post data
        $post_data = array(
            'post_type'    => 'apprco_vacancy',
            'post_status'  => 'publish',
            'post_title'   => $vacancy['title'] ?? 'Untitled Vacancy',
            'post_content' => $vacancy['description'] ?? '',
            'post_excerpt' => $vacancy['short_description'] ?? '',
        );

        if ( $existing ) {
            // Update existing
            $post_data['ID'] = $existing->ID;
            $post_id = wp_update_post( $post_data, true );

            if ( is_wp_error( $post_id ) ) {
                return array(
                    'success' => false,
                    'action'  => 'error',
                    'error'   => $post_id->get_error_message(),
                );
            }

            $action = 'updated';
        } else {
            // Create new
            $post_id = wp_insert_post( $post_data, true );

            if ( is_wp_error( $post_id ) ) {
                return array(
                    'success' => false,
                    'action'  => 'error',
                    'error'   => $post_id->get_error_message(),
                );
            }

            $action = 'created';
        }

        // Save meta fields
        $this->save_vacancy_meta( $post_id, $vacancy );

        // Set taxonomies
        $this->set_vacancy_taxonomies( $post_id, $vacancy );

        return array(
            'success' => true,
            'action'  => $action,
            'post_id' => $post_id,
        );
    }

    /**
     * Find existing vacancy by reference
     *
     * @param string $reference Vacancy reference.
     * @return WP_Post|null
     */
    private function find_existing_vacancy( string $reference ): ?WP_Post {
        $query = new WP_Query( array(
            'post_type'      => 'apprco_vacancy',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'   => '_apprco_vacancy_reference',
                    'value' => $reference,
                ),
            ),
        ) );

        return $query->have_posts() ? $query->posts[0] : null;
    }

    /**
     * Save vacancy meta fields
     *
     * @param int   $post_id Post ID.
     * @param array $vacancy Vacancy data.
     */
    private function save_vacancy_meta( int $post_id, array $vacancy ): void {
        // Core fields
        $meta_mappings = array(
            'vacancy_reference'       => 'vacancy_reference',
            'vacancy_url'             => 'vacancy_url',
            'provider_id'             => 'provider_id',
            'employer_name'           => 'employer_name',
            'employer_website'        => 'employer_website',
            'employer_description'    => 'employer_description',
            'training_provider_name'  => 'training_provider_name',
            'training_provider_ukprn' => 'training_provider_ukprn',
            'course_title'            => 'course_title',
            'course_route'            => 'course_route',
            'course_level'            => 'course_level',
            'apprenticeship_level'    => 'apprenticeship_level',
            'wage_type'               => 'wage_type',
            'wage_amount'             => 'wage_amount',
            'wage_unit'               => 'wage_unit',
            'wage_text'               => 'wage_text',
            'working_week'            => 'working_week',
            'hours_per_week'          => 'hours_per_week',
            'expected_duration'       => 'expected_duration',
            'positions_available'     => 'positions_available',
            'things_to_consider'      => 'things_to_consider',
            'posted_date'             => 'posted_date',
            'closing_date'            => 'closing_date',
            'start_date'              => 'start_date',
            'apply_url'               => 'apply_url',
            'is_disability_confident' => 'is_disability_confident',
            'is_national'             => 'is_national',
        );

        foreach ( $meta_mappings as $meta_key => $vacancy_key ) {
            $value = $vacancy[ $vacancy_key ] ?? '';
            update_post_meta( $post_id, '_apprco_' . $meta_key, $value );
        }

        // Address fields from primary_address
        $address = $vacancy['primary_address'] ?? array();
        update_post_meta( $post_id, '_apprco_address_line1', $address['address_line1'] ?? '' );
        update_post_meta( $post_id, '_apprco_address_line2', $address['address_line2'] ?? '' );
        update_post_meta( $post_id, '_apprco_address_line3', $address['address_line3'] ?? '' );
        update_post_meta( $post_id, '_apprco_address_line4', $address['address_line4'] ?? '' );
        update_post_meta( $post_id, '_apprco_postcode', $address['postcode'] ?? '' );
        update_post_meta( $post_id, '_apprco_latitude', $address['latitude'] ?? '' );
        update_post_meta( $post_id, '_apprco_longitude', $address['longitude'] ?? '' );

        // Array fields
        update_post_meta( $post_id, '_apprco_skills', $vacancy['skills_required'] ?? array() );
        update_post_meta( $post_id, '_apprco_qualifications', $vacancy['qualifications_required'] ?? array() );
        update_post_meta( $post_id, '_apprco_addresses', $vacancy['addresses'] ?? array() );

        // Raw data for reference
        update_post_meta( $post_id, '_apprco_raw_data', $vacancy['raw_data'] ?? array() );
        update_post_meta( $post_id, '_apprco_imported_at', current_time( 'mysql' ) );
    }

    /**
     * Set vacancy taxonomies
     *
     * @param int   $post_id Post ID.
     * @param array $vacancy Vacancy data.
     */
    private function set_vacancy_taxonomies( int $post_id, array $vacancy ): void {
        // Apprenticeship Level
        if ( ! empty( $vacancy['apprenticeship_level'] ) ) {
            wp_set_object_terms( $post_id, $vacancy['apprenticeship_level'], 'apprco_level' );
        }

        // Course Route
        if ( ! empty( $vacancy['course_route'] ) ) {
            wp_set_object_terms( $post_id, $vacancy['course_route'], 'apprco_route' );
        }

        // Employer
        if ( ! empty( $vacancy['employer_name'] ) ) {
            wp_set_object_terms( $post_id, $vacancy['employer_name'], 'apprco_employer' );
        }
    }

    /**
     * Update import status option
     *
     * @param string $key    Status option key.
     * @param array  $update Values to update.
     */
    private function update_import_status( string $key, array $update ): void {
        $current = get_option( $key, array() );
        $updated = array_merge( $current, $update );
        update_option( $key, $updated, false );
    }

    /**
     * Get import status
     *
     * @param string $import_id Import ID.
     * @return array|null Import status or null.
     */
    public function get_import_status( string $import_id ): ?array {
        $status_key = 'apprco_import_status_' . $import_id;
        $status     = get_option( $status_key );

        return is_array( $status ) ? $status : null;
    }

    /**
     * Cancel running import
     *
     * @param string $import_id Import ID.
     * @return bool True if cancelled.
     */
    public function cancel_import( string $import_id ): bool {
        $status_key = 'apprco_import_status_' . $import_id;
        $status     = get_option( $status_key );

        if ( ! $status ) {
            return false;
        }

        $this->update_import_status( $status_key, array(
            'status'    => 'cancelled',
            'cancelled' => time(),
        ) );

        $this->logger->warning( sprintf( 'Import %s cancelled', $import_id ), $import_id, 'wizard' );

        return true;
    }

    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection(): void {
        check_ajax_referer( 'apprco_wizard_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $provider_id = isset( $_POST['provider_id'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_id'] ) ) : '';
        $config      = isset( $_POST['config'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['config'] ) ) : array();

        $result = $this->test_connection( $provider_id, $config );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] ?? 'Connection failed.' );
        }
    }

    /**
     * AJAX: Preview vacancies
     */
    public function ajax_preview(): void {
        check_ajax_referer( 'apprco_wizard_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $provider_id = isset( $_POST['provider_id'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_id'] ) ) : '';
        $params      = isset( $_POST['params'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['params'] ) ) : array();
        $limit       = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 10;

        $result = $this->preview( $provider_id, $params, $limit );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['error'] ?? 'Preview failed.' );
        }
    }

    /**
     * AJAX: Execute import
     */
    public function ajax_execute(): void {
        check_ajax_referer( 'apprco_wizard_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $provider_id = isset( $_POST['provider_id'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_id'] ) ) : '';
        $params      = isset( $_POST['params'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['params'] ) ) : array();

        // Execute import (this may take a while)
        $result = $this->execute( $provider_id, $params );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['error'] ?? 'Import failed.' );
        }
    }

    /**
     * AJAX: Get import status
     */
    public function ajax_get_status(): void {
        check_ajax_referer( 'apprco_wizard_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $import_id = isset( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : '';
        $status    = $this->get_import_status( $import_id );

        if ( $status ) {
            wp_send_json_success( $status );
        } else {
            wp_send_json_error( 'Import not found.' );
        }
    }

    /**
     * AJAX: Cancel import
     */
    public function ajax_cancel(): void {
        check_ajax_referer( 'apprco_wizard_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $import_id = isset( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : '';
        $result    = $this->cancel_import( $import_id );

        if ( $result ) {
            wp_send_json_success( 'Import cancelled.' );
        } else {
            wp_send_json_error( 'Could not cancel import.' );
        }
    }

    /**
     * Get registered providers for wizard
     *
     * @return array Provider info for display.
     */
    public function get_providers(): array {
        return $this->registry->get_providers_info();
    }

    /**
     * Get wizard JavaScript data for localization
     *
     * @return array Data for wp_localize_script.
     */
    public function get_js_data(): array {
        return array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'apprco_wizard_nonce' ),
            'providers' => $this->get_providers(),
            'steps'     => self::STEPS,
            'strings'   => array(
                'testing'      => __( 'Testing connection...', 'apprenticeship-connect' ),
                'connected'    => __( 'Connection successful!', 'apprenticeship-connect' ),
                'failed'       => __( 'Connection failed.', 'apprenticeship-connect' ),
                'loading'      => __( 'Loading...', 'apprenticeship-connect' ),
                'importing'    => __( 'Importing vacancies...', 'apprenticeship-connect' ),
                'complete'     => __( 'Import complete!', 'apprenticeship-connect' ),
                'cancelled'    => __( 'Import cancelled.', 'apprenticeship-connect' ),
                'error'        => __( 'An error occurred.', 'apprenticeship-connect' ),
                'noVacancies'  => __( 'No vacancies found.', 'apprenticeship-connect' ),
                'confirmStart' => __( 'Start import? This will fetch and save all vacancies.', 'apprenticeship-connect' ),
                'confirmCancel' => __( 'Are you sure you want to cancel the import?', 'apprenticeship-connect' ),
            ),
        );
    }
}
