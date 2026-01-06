<?php
/**
 * Main plugin functionality class
 *
 * @package ApprenticeshipConnect
 * @version 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Main plugin functionality class
 */
class Apprco_Core {
    
    /**
     * Plugin options
     */
    private $options;
    
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->options = get_option( 'apprco_plugin_options', array() );
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'apprco_daily_fetch_vacancies', array( $this, 'fetch_and_save_vacancies' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style( 'apprco-style', APPRCO_PLUGIN_URL . 'assets/css/apprco.css', array(), APPRCO_PLUGIN_VERSION );
    }
    
    /**
     * Fetch and save vacancies from API
     */
    public function fetch_and_save_vacancies() {
        // Check if API credentials are configured
        if ( empty( $this->options['api_subscription_key'] ) || empty( $this->options['api_base_url'] ) ) {
            // This was already fixed, no change needed here.
            return false;
        }
        
        // Get existing vacancy references
        $existing_vacancy_references = $this->get_existing_vacancy_references();
        
        // Fetch data from API
        $api_data = $this->fetch_api_data();
        
        // Process API data
        $api_current_references = $this->process_api_data( $api_data, $existing_vacancy_references );
        
        // Delete old vacancies (those not returned by API this run)
        $this->delete_old_vacancies( $existing_vacancy_references, $api_current_references );
        
        // Update last sync time
        update_option( 'apprco_last_sync', current_time( 'timestamp' ) );

        return true;
    }
    
    /**
     * Get existing vacancy references
     */
    private function get_existing_vacancy_references() {
        $cache_key = 'apprco_existing_vacancy_references';
        $cached_results = wp_cache_get( $cache_key );

        if ( false === $cached_results ) {
            $results = array();
            $query = new WP_Query(
                array(
                    'post_type'      => 'apprco_vacancy',
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'meta_query'     => array(
                        array(
                            'key'     => '_apprco_vacancy_reference',
                            'compare' => 'EXISTS',
                        ),
                    ),
                )
            );

            if ( $query->have_posts() ) {
                foreach ( $query->posts as $post ) {
                    $ref = get_post_meta( $post->ID, '_apprco_vacancy_reference', true );
                    if ( $ref ) {
                        $results[ $ref ] = $post->ID;
                    }
                }
            }
            wp_cache_set( $cache_key, $results, '', 3600 ); // Cache for 1 hour
        } else {
            $results = $cached_results;
        }
        return $results;
    }

    /**
     * Fetch data from API
     */
    private function fetch_api_data() {
        $api_url = $this->options['api_base_url'] . '/vacancy?PageNumber=1&PageSize=50&Sort=AgeDesc&FilterBySubscription=true';
        
        if ( ! empty( $this->options['api_ukprn'] ) ) {
            $api_url .= '&Ukprn=' . $this->options['api_ukprn'];
        }
        
        $headers = array(
            'X-Version'                 => '1',
            'Ocp-Apim-Subscription-Key' => $this->options['api_subscription_key'],
            'Content-Type'              => 'application/json',
        );
        
        $args = array(
            'headers' => $headers,
            'timeout' => 60,
        );
        
        $response = wp_remote_get( $api_url, $args );
        
        if ( is_wp_error( $response ) ) {
            // This was already fixed, no change needed here.
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            // This was already fixed, no change needed here.
            return false;
        }
        
        return $data;
    }
    
    /**
     * Process API data
     */
    private function process_api_data( $data, $existing_vacancy_references ) {
        $api_current_references = array();
        
        if ( ! empty( $data->vacancies ) && is_array( $data->vacancies ) ) {
            foreach ( $data->vacancies as $vacancy ) {
                if ( ! isset( $vacancy->vacancyReference ) ) {
                    continue;
                }
                
                $api_current_references[] = $vacancy->vacancyReference;
                
                $post_id = isset( $existing_vacancy_references[ $vacancy->vacancyReference ] ) 
                    ? $existing_vacancy_references[ $vacancy->vacancyReference ] 
                    : 0;
                
                $post_data = array(
                    'post_title'    => isset( $vacancy->title ) ? wp_strip_all_tags( $vacancy->title ) : '',
                    'post_content'  => isset( $vacancy->fullDescription ) ? wp_kses_post( $vacancy->fullDescription ) : '',
                    'post_status'   => 'publish',
                    'post_type'     => 'apprco_vacancy',
                    'post_author'   => 1,
                );
                
                if ( $post_id ) {
                    $post_data['ID'] = $post_id;
                    wp_update_post( $post_data );
                    unset( $existing_vacancy_references[ $vacancy->vacancyReference ] );
                } else {
                    $post_id = wp_insert_post( $post_data );
                }
                
                if ( $post_id && ! is_wp_error( $post_id ) ) {
                    $this->save_vacancy_meta( $post_id, $vacancy );
                }
            }
        }
        
        return $api_current_references;
    }
    
    /**
     * Save vacancy meta data
     */
    private function save_vacancy_meta( $post_id, $vacancy ) {
        $meta_fields = array();
        
        if ( isset( $vacancy->vacancyReference ) ) {
            $meta_fields['_apprco_vacancy_reference'] = $vacancy->vacancyReference;
        }
        if ( isset( $vacancy->description ) ) {
            $meta_fields['_apprco_vacancy_description_short'] = wp_kses_post( $vacancy->description );
        }
        if ( isset( $vacancy->numberOfPositions ) ) {
            $meta_fields['_apprco_number_of_positions'] = $vacancy->numberOfPositions;
        }
        if ( isset( $vacancy->postedDate ) ) {
            $meta_fields['_apprco_posted_date'] = $vacancy->postedDate;
        }
        if ( isset( $vacancy->closingDate ) ) {
            $meta_fields['_apprco_closing_date'] = $vacancy->closingDate;
        }
        if ( isset( $vacancy->startDate ) ) {
            $meta_fields['_apprco_start_date'] = $vacancy->startDate;
        }
        if ( isset( $vacancy->hoursPerWeek ) ) {
            $meta_fields['_apprco_hours_per_week'] = $vacancy->hoursPerWeek;
        }
        if ( isset( $vacancy->expectedDuration ) ) {
            $meta_fields['_apprco_expected_duration'] = $vacancy->expectedDuration;
        }
        if ( isset( $vacancy->employerName ) ) {
            $meta_fields['_apprco_employer_name'] = wp_strip_all_tags( $vacancy->employerName );
        }
        if ( isset( $vacancy->vacancyUrl ) ) {
            $meta_fields['_apprco_vacancy_url'] = esc_url( $vacancy->vacancyUrl );
        }
        if ( isset( $vacancy->apprenticeshipLevel ) ) {
            $meta_fields['_apprco_apprenticeship_level'] = wp_strip_all_tags( $vacancy->apprenticeshipLevel );
        }
        if ( isset( $vacancy->providerName ) ) {
            $meta_fields['_apprco_provider_name'] = wp_strip_all_tags( $vacancy->providerName );
        }
        
        foreach ( $meta_fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
        
        // Save wage information
        if ( isset( $vacancy->wage ) && is_object( $vacancy->wage ) ) {
            if ( isset( $vacancy->wage->wageType ) ) {
                update_post_meta( $post_id, '_apprco_wage_type', wp_strip_all_tags( $vacancy->wage->wageType ) );
            }
            if ( isset( $vacancy->wage->wageAmount ) ) {
                update_post_meta( $post_id, '_apprco_wage_amount', $vacancy->wage->wageAmount );
            }
            if ( isset( $vacancy->wage->wageUnit ) ) {
                update_post_meta( $post_id, '_apprco_wage_unit', wp_strip_all_tags( $vacancy->wage->wageUnit ) );
            }
            if ( isset( $vacancy->wage->wageAdditionalInformation ) ) {
                update_post_meta( $post_id, '_apprco_wage_additional_information', wp_kses_post( $vacancy->wage->wageAdditionalInformation ) );
            }
        }
        
        // Save address information
        if ( isset( $vacancy->address ) && is_object( $vacancy->address ) ) {
            if ( isset( $vacancy->address->addressLine1 ) ) {
                update_post_meta( $post_id, '_apprco_address_line_1', wp_strip_all_tags( $vacancy->address->addressLine1 ) );
            }
            if ( isset( $vacancy->address->postcode ) ) {
                update_post_meta( $post_id, '_apprco_postcode', wp_strip_all_tags( $vacancy->address->postcode ) );
            }
            if ( isset( $vacancy->address->latitude ) ) {
                update_post_meta( $post_id, '_apprco_latitude', $vacancy->address->latitude );
            }
            if ( isset( $vacancy->address->longitude ) ) {
                update_post_meta( $post_id, '_apprco_longitude', $vacancy->address->longitude );
            }
        }
        
        // Save course information
        if ( isset( $vacancy->course ) && is_object( $vacancy->course ) ) {
            if ( isset( $vacancy->course->title ) ) {
                update_post_meta( $post_id, '_apprco_course_title', wp_strip_all_tags( $vacancy->course->title ) );
            }
            if ( isset( $vacancy->course->level ) ) {
                update_post_meta( $post_id, '_apprco_course_level', $vacancy->course->level );
            }
            if ( isset( $vacancy->course->route ) ) {
                update_post_meta( $post_id, '_apprco_course_route', wp_strip_all_tags( $vacancy->course->route ) );
            }
        }
    }
    
    /**
     * Delete old vacancies
     */
    private function delete_old_vacancies( $existing_vacancy_references, $api_current_references ) {
        if ( empty( $existing_vacancy_references ) ) {
            return;
        }
        $current_set = array_flip( (array) $api_current_references );
        foreach ( $existing_vacancy_references as $ref_to_delete => $post_id_to_delete ) {
            if ( ! isset( $current_set[ $ref_to_delete ] ) ) {
                wp_delete_post( $post_id_to_delete, true );
            }
        }
    }
    
    /**
     * Manual sync function
     */
    public function manual_sync() {
        return $this->fetch_and_save_vacancies();
    }
    
    /**
     * Get sync status
     */
    public function get_sync_status() {
        $last_sync = get_option( 'apprco_last_sync' );
        $total_vacancies = wp_count_posts( 'apprco_vacancy' );
        
        return array(
            'last_sync' => $last_sync,
            'total_vacancies' => $total_vacancies->publish,
            'is_configured' => ! empty( $this->options['api_subscription_key'] ),
        );
    }

    /**
     * Allow overriding options for a one-off sync (used by Test & Sync without saving)
     */
    public function override_options_for_sync( array $overrides ): void {
        if ( empty( $this->options ) || ! is_array( $this->options ) ) {
            $this->options = array();
        }
        foreach ( $overrides as $key => $value ) {
            if ( $value !== '' && $value !== null ) {
                $this->options[ $key ] = $value;
            }
        }
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        $completed = ! empty( $this->options['api_subscription_key'] ) && ! empty( $this->options['api_base_url'] );

        $force_show = isset( $_GET['page'], $_GET['step'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'apprco_setup_nonce' ) && $_GET['page'] === 'apprco-setup' && absint( $_GET['step'] ) === 5;
        if ( ! $completed || $force_show ) {
            add_submenu_page(
                'apprco-dashboard',
                __( 'Setup Wizard', 'apprenticeship-connect' ),
                __( 'Setup Wizard', 'apprenticeship-connect' ),
                'manage_options',
                'apprco-setup',
                array( $this, 'setup_page' )
            );
        }
    }
}