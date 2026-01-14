<?php
/**
 * Meta Box class for Vacancy edit form with dynamic fields
 *
 * @package ApprenticeshipConnect
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles vacancy meta boxes in the admin post editor
 */
class Apprco_Meta_Box {

    /**
     * Plugin instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Nonce action name
     *
     * @var string
     */
    const NONCE_ACTION = 'apprco_vacancy_meta_nonce';

    /**
     * Nonce field name
     *
     * @var string
     */
    const NONCE_NAME = 'apprco_vacancy_meta_nonce_field';

    /**
     * Meta field definitions organized by section
     *
     * @var array
     */
    private $field_sections = array();

    /**
     * Get singleton instance
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
        $this->init_field_sections();
        $this->init_hooks();
    }

    /**
     * Initialize field sections
     */
    private function init_field_sections(): void {
        $this->field_sections = array(
            'core' => array(
                'title'  => __( 'Core Details', 'apprenticeship-connect' ),
                'fields' => array(
                    '_apprco_vacancy_reference' => array(
                        'label'       => __( 'Vacancy Reference', 'apprenticeship-connect' ),
                        'type'        => 'text',
                        'description' => __( 'Unique vacancy reference from the API', 'apprenticeship-connect' ),
                        'readonly'    => true,
                    ),
                    '_apprco_vacancy_description_short' => array(
                        'label'       => __( 'Short Description', 'apprenticeship-connect' ),
                        'type'        => 'textarea',
                        'description' => __( 'Brief overview of the vacancy', 'apprenticeship-connect' ),
                        'rows'        => 3,
                    ),
                    '_apprco_full_description' => array(
                        'label'       => __( 'Full Description', 'apprenticeship-connect' ),
                        'type'        => 'wysiwyg',
                        'description' => __( 'Complete vacancy description', 'apprenticeship-connect' ),
                    ),
                    '_apprco_number_of_positions' => array(
                        'label'       => __( 'Number of Positions', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'min'         => 1,
                        'description' => __( 'Available positions', 'apprenticeship-connect' ),
                    ),
                    '_apprco_vacancy_url' => array(
                        'label'       => __( 'External Vacancy URL', 'apprenticeship-connect' ),
                        'type'        => 'url',
                        'description' => __( 'Link to apply on external site', 'apprenticeship-connect' ),
                    ),
                    '_apprco_external_vacancy_url' => array(
                        'label'       => __( 'Alternative Application URL', 'apprenticeship-connect' ),
                        'type'        => 'url',
                        'description' => __( 'Alternative application link', 'apprenticeship-connect' ),
                    ),
                ),
            ),
            'dates' => array(
                'title'  => __( 'Important Dates', 'apprenticeship-connect' ),
                'fields' => array(
                    '_apprco_posted_date' => array(
                        'label'       => __( 'Posted Date', 'apprenticeship-connect' ),
                        'type'        => 'date',
                        'description' => __( 'When the vacancy was posted', 'apprenticeship-connect' ),
                    ),
                    '_apprco_closing_date' => array(
                        'label'       => __( 'Closing Date', 'apprenticeship-connect' ),
                        'type'        => 'date',
                        'description' => __( 'Application deadline', 'apprenticeship-connect' ),
                    ),
                    '_apprco_start_date' => array(
                        'label'       => __( 'Expected Start Date', 'apprenticeship-connect' ),
                        'type'        => 'date',
                        'description' => __( 'When the apprenticeship starts', 'apprenticeship-connect' ),
                    ),
                ),
            ),
            'employer' => array(
                'title'  => __( 'Employer Information', 'apprenticeship-connect' ),
                'fields' => array(
                    '_apprco_employer_name' => array(
                        'label'       => __( 'Employer Name', 'apprenticeship-connect' ),
                        'type'        => 'text',
                        'description' => __( 'Name of the employer', 'apprenticeship-connect' ),
                    ),
                    '_apprco_employer_description' => array(
                        'label'       => __( 'Employer Description', 'apprenticeship-connect' ),
                        'type'        => 'textarea',
                        'description' => __( 'About the employer', 'apprenticeship-connect' ),
                        'rows'        => 3,
                    ),
                    '_apprco_employer_website_url' => array(
                        'label'       => __( 'Employer Website', 'apprenticeship-connect' ),
                        'type'        => 'url',
                        'description' => __( 'Employer website URL', 'apprenticeship-connect' ),
                    ),
                    '_apprco_employer_contact_name' => array(
                        'label'       => __( 'Contact Name', 'apprenticeship-connect' ),
                        'type'        => 'text',
                        'description' => __( 'Contact person name', 'apprenticeship-connect' ),
                    ),
                    '_apprco_employer_contact_email' => array(
                        'label'       => __( 'Contact Email', 'apprenticeship-connect' ),
                        'type'        => 'email',
                        'description' => __( 'Contact email address', 'apprenticeship-connect' ),
                    ),
                    '_apprco_employer_contact_phone' => array(
                        'label'       => __( 'Contact Phone', 'apprenticeship-connect' ),
                        'type'        => 'tel',
                        'description' => __( 'Contact phone number', 'apprenticeship-connect' ),
                    ),
                    '_apprco_is_employer_anonymous' => array(
                        'label'       => __( 'Anonymous Employer', 'apprenticeship-connect' ),
                        'type'        => 'checkbox',
                        'description' => __( 'Hide employer details from public', 'apprenticeship-connect' ),
                    ),
                ),
            ),
            'location' => array(
                'title'  => __( 'Location', 'apprenticeship-connect' ),
                'fields' => array(
                    '_apprco_address_line_1' => array(
                        'label'       => __( 'Address Line 1', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_address_line_2' => array(
                        'label'       => __( 'Address Line 2', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_address_line_3' => array(
                        'label'       => __( 'Address Line 3', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_address_line_4' => array(
                        'label'       => __( 'Address Line 4', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_town' => array(
                        'label'       => __( 'Town/City', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_county' => array(
                        'label'       => __( 'County', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_postcode' => array(
                        'label'       => __( 'Postcode', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_latitude' => array(
                        'label'       => __( 'Latitude', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'step'        => 'any',
                    ),
                    '_apprco_longitude' => array(
                        'label'       => __( 'Longitude', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'step'        => 'any',
                    ),
                    '_apprco_vacancy_location_type' => array(
                        'label'       => __( 'Location Type', 'apprenticeship-connect' ),
                        'type'        => 'select',
                        'options'     => array(
                            ''         => __( 'Select...', 'apprenticeship-connect' ),
                            'office'   => __( 'Office', 'apprenticeship-connect' ),
                            'remote'   => __( 'Remote', 'apprenticeship-connect' ),
                            'hybrid'   => __( 'Hybrid', 'apprenticeship-connect' ),
                            'multiple' => __( 'Multiple Locations', 'apprenticeship-connect' ),
                        ),
                    ),
                ),
            ),
            'wage' => array(
                'title'  => __( 'Wage & Hours', 'apprenticeship-connect' ),
                'fields' => array(
                    '_apprco_wage_type' => array(
                        'label'       => __( 'Wage Type', 'apprenticeship-connect' ),
                        'type'        => 'select',
                        'options'     => array(
                            ''                         => __( 'Select...', 'apprenticeship-connect' ),
                            'NationalMinimumWage'      => __( 'National Minimum Wage', 'apprenticeship-connect' ),
                            'NationalMinimumWageForApprentices' => __( 'Apprentice Minimum Wage', 'apprenticeship-connect' ),
                            'FixedWage'                => __( 'Fixed Wage', 'apprenticeship-connect' ),
                            'CompetitiveSalary'        => __( 'Competitive Salary', 'apprenticeship-connect' ),
                            'ToBeAgreedUponAppointment' => __( 'To Be Agreed', 'apprenticeship-connect' ),
                        ),
                    ),
                    '_apprco_wage_amount' => array(
                        'label'       => __( 'Wage Amount', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'step'        => '0.01',
                        'min'         => 0,
                        'description' => __( 'Amount in GBP', 'apprenticeship-connect' ),
                    ),
                    '_apprco_wage_amount_lower_bound' => array(
                        'label'       => __( 'Wage Lower Bound', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'step'        => '0.01',
                        'min'         => 0,
                    ),
                    '_apprco_wage_amount_upper_bound' => array(
                        'label'       => __( 'Wage Upper Bound', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'step'        => '0.01',
                        'min'         => 0,
                    ),
                    '_apprco_wage_unit' => array(
                        'label'       => __( 'Wage Unit', 'apprenticeship-connect' ),
                        'type'        => 'select',
                        'options'     => array(
                            ''         => __( 'Select...', 'apprenticeship-connect' ),
                            'Annually' => __( 'Per Year', 'apprenticeship-connect' ),
                            'Monthly'  => __( 'Per Month', 'apprenticeship-connect' ),
                            'Weekly'   => __( 'Per Week', 'apprenticeship-connect' ),
                            'Hourly'   => __( 'Per Hour', 'apprenticeship-connect' ),
                        ),
                    ),
                    '_apprco_wage_additional_information' => array(
                        'label'       => __( 'Additional Wage Info', 'apprenticeship-connect' ),
                        'type'        => 'textarea',
                        'rows'        => 2,
                    ),
                    '_apprco_hours_per_week' => array(
                        'label'       => __( 'Hours Per Week', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'min'         => 0,
                        'max'         => 168,
                        'step'        => '0.5',
                    ),
                    '_apprco_wage_weekly_hours' => array(
                        'label'       => __( 'Weekly Hours (from wage)', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'min'         => 0,
                        'max'         => 168,
                        'step'        => '0.5',
                    ),
                    '_apprco_working_week_description' => array(
                        'label'       => __( 'Working Week Description', 'apprenticeship-connect' ),
                        'type'        => 'textarea',
                        'rows'        => 2,
                    ),
                    '_apprco_expected_duration' => array(
                        'label'       => __( 'Expected Duration', 'apprenticeship-connect' ),
                        'type'        => 'text',
                        'description' => __( 'e.g., "18 months"', 'apprenticeship-connect' ),
                    ),
                ),
            ),
            'training' => array(
                'title'  => __( 'Training & Qualifications', 'apprenticeship-connect' ),
                'fields' => array(
                    '_apprco_apprenticeship_level' => array(
                        'label'       => __( 'Apprenticeship Level', 'apprenticeship-connect' ),
                        'type'        => 'select',
                        'options'     => array(
                            ''                => __( 'Select...', 'apprenticeship-connect' ),
                            'Intermediate'    => __( 'Level 2 - Intermediate', 'apprenticeship-connect' ),
                            'Advanced'        => __( 'Level 3 - Advanced', 'apprenticeship-connect' ),
                            'Higher'          => __( 'Level 4/5 - Higher', 'apprenticeship-connect' ),
                            'Degree'          => __( 'Level 6/7 - Degree', 'apprenticeship-connect' ),
                        ),
                    ),
                    '_apprco_provider_name' => array(
                        'label'       => __( 'Training Provider', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_provider_ukprn' => array(
                        'label'       => __( 'Provider UKPRN', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_course_title' => array(
                        'label'       => __( 'Course Title', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_course_level' => array(
                        'label'       => __( 'Course Level', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'min'         => 2,
                        'max'         => 7,
                    ),
                    '_apprco_course_route' => array(
                        'label'       => __( 'Course Route', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_course_lars_code' => array(
                        'label'       => __( 'LARS Code', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_framework_title' => array(
                        'label'       => __( 'Framework Title (Legacy)', 'apprenticeship-connect' ),
                        'type'        => 'text',
                    ),
                    '_apprco_framework_level' => array(
                        'label'       => __( 'Framework Level (Legacy)', 'apprenticeship-connect' ),
                        'type'        => 'number',
                    ),
                    '_apprco_training_to_be_provided' => array(
                        'label'       => __( 'Training to be Provided', 'apprenticeship-connect' ),
                        'type'        => 'wysiwyg',
                    ),
                ),
            ),
            'requirements' => array(
                'title'  => __( 'Requirements & Skills', 'apprenticeship-connect' ),
                'fields' => array(
                    '_apprco_qualifications_required' => array(
                        'label'       => __( 'Qualifications Required', 'apprenticeship-connect' ),
                        'type'        => 'wysiwyg',
                    ),
                    '_apprco_skills_required' => array(
                        'label'       => __( 'Skills Required', 'apprenticeship-connect' ),
                        'type'        => 'wysiwyg',
                    ),
                    '_apprco_things_to_consider' => array(
                        'label'       => __( 'Things to Consider', 'apprenticeship-connect' ),
                        'type'        => 'textarea',
                        'rows'        => 3,
                    ),
                    '_apprco_supplementary_question_1' => array(
                        'label'       => __( 'Supplementary Question 1', 'apprenticeship-connect' ),
                        'type'        => 'textarea',
                        'rows'        => 2,
                    ),
                    '_apprco_supplementary_question_2' => array(
                        'label'       => __( 'Supplementary Question 2', 'apprenticeship-connect' ),
                        'type'        => 'textarea',
                        'rows'        => 2,
                    ),
                ),
            ),
            'outcomes' => array(
                'title'  => __( 'Outcomes & Prospects', 'apprenticeship-connect' ),
                'fields' => array(
                    '_apprco_outcome_description' => array(
                        'label'       => __( 'Outcome Description', 'apprenticeship-connect' ),
                        'type'        => 'textarea',
                        'rows'        => 3,
                    ),
                    '_apprco_future_prospects' => array(
                        'label'       => __( 'Future Prospects', 'apprenticeship-connect' ),
                        'type'        => 'wysiwyg',
                    ),
                ),
            ),
            'flags' => array(
                'title'  => __( 'Additional Flags', 'apprenticeship-connect' ),
                'fields' => array(
                    '_apprco_is_positive_about_disability' => array(
                        'label'       => __( 'Positive About Disability', 'apprenticeship-connect' ),
                        'type'        => 'checkbox',
                        'description' => __( 'Employer supports disability employment', 'apprenticeship-connect' ),
                    ),
                    '_apprco_is_disability_confident' => array(
                        'label'       => __( 'Disability Confident', 'apprenticeship-connect' ),
                        'type'        => 'checkbox',
                        'description' => __( 'Disability Confident employer', 'apprenticeship-connect' ),
                    ),
                    '_apprco_is_recruit_vacancy' => array(
                        'label'       => __( 'Recruit Vacancy', 'apprenticeship-connect' ),
                        'type'        => 'checkbox',
                    ),
                    '_apprco_distance' => array(
                        'label'       => __( 'Distance (miles)', 'apprenticeship-connect' ),
                        'type'        => 'number',
                        'step'        => '0.1',
                        'min'         => 0,
                    ),
                ),
            ),
        );
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_apprco_vacancy', array( $this, 'save_meta_box_data' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_meta_box_scripts' ) );
    }

    /**
     * Register meta boxes
     */
    public function add_meta_boxes(): void {
        // Main vacancy details meta box with tabs
        add_meta_box(
            'apprco_vacancy_details',
            __( 'Vacancy Details', 'apprenticeship-connect' ),
            array( $this, 'render_main_meta_box' ),
            'apprco_vacancy',
            'normal',
            'high'
        );

        // Quick info sidebar meta box
        add_meta_box(
            'apprco_vacancy_quick_info',
            __( 'Quick Info', 'apprenticeship-connect' ),
            array( $this, 'render_quick_info_meta_box' ),
            'apprco_vacancy',
            'side',
            'high'
        );
    }

    /**
     * Enqueue scripts for meta boxes
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_meta_box_scripts( string $hook ): void {
        global $post_type;

        if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && 'apprco_vacancy' === $post_type ) {
            wp_enqueue_style(
                'apprco-meta-box',
                APPRCO_PLUGIN_URL . 'assets/css/meta-box.css',
                array(),
                APPRCO_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'apprco-meta-box',
                APPRCO_PLUGIN_URL . 'assets/js/meta-box.js',
                array( 'jquery', 'wp-editor' ),
                APPRCO_PLUGIN_VERSION,
                true
            );

            wp_localize_script( 'apprco-meta-box', 'apprcoMetaBox', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'apprco_meta_box_nonce' ),
                'strings' => array(
                    'saving'      => __( 'Saving...', 'apprenticeship-connect' ),
                    'saved'       => __( 'Saved', 'apprenticeship-connect' ),
                    'error'       => __( 'Error saving', 'apprenticeship-connect' ),
                    'confirm'     => __( 'Are you sure?', 'apprenticeship-connect' ),
                    'geocoding'   => __( 'Looking up coordinates...', 'apprenticeship-connect' ),
                ),
            ) );
        }
    }

    /**
     * Render the main meta box with tabs
     *
     * @param WP_Post $post Post object.
     */
    public function render_main_meta_box( WP_Post $post ): void {
        // Add nonce field
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

        echo '<div class="apprco-meta-box-wrapper">';

        // Tab navigation
        echo '<div class="apprco-meta-tabs">';
        $first = true;
        foreach ( $this->field_sections as $section_id => $section ) {
            $active_class = $first ? ' active' : '';
            echo '<button type="button" class="apprco-meta-tab' . esc_attr( $active_class ) . '" data-tab="' . esc_attr( $section_id ) . '">';
            echo esc_html( $section['title'] );
            echo '</button>';
            $first = false;
        }
        echo '</div>';

        // Tab content
        echo '<div class="apprco-meta-content">';
        $first = true;
        foreach ( $this->field_sections as $section_id => $section ) {
            $active_class = $first ? ' active' : '';
            echo '<div class="apprco-meta-panel' . esc_attr( $active_class ) . '" data-panel="' . esc_attr( $section_id ) . '">';
            echo '<div class="apprco-fields-grid">';

            foreach ( $section['fields'] as $meta_key => $field ) {
                $this->render_field( $post->ID, $meta_key, $field );
            }

            echo '</div>';
            echo '</div>';
            $first = false;
        }
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render the quick info sidebar meta box
     *
     * @param WP_Post $post Post object.
     */
    public function render_quick_info_meta_box( WP_Post $post ): void {
        $reference    = get_post_meta( $post->ID, '_apprco_vacancy_reference', true );
        $employer     = get_post_meta( $post->ID, '_apprco_employer_name', true );
        $closing_date = get_post_meta( $post->ID, '_apprco_closing_date', true );
        $posted_date  = get_post_meta( $post->ID, '_apprco_posted_date', true );
        $level        = get_post_meta( $post->ID, '_apprco_apprenticeship_level', true );
        $positions    = get_post_meta( $post->ID, '_apprco_number_of_positions', true );
        $vacancy_url  = get_post_meta( $post->ID, '_apprco_vacancy_url', true );

        echo '<div class="apprco-quick-info">';

        if ( $reference ) {
            echo '<div class="apprco-quick-item">';
            echo '<strong>' . esc_html__( 'Reference:', 'apprenticeship-connect' ) . '</strong>';
            echo '<span class="apprco-ref-badge">' . esc_html( $reference ) . '</span>';
            echo '</div>';
        }

        if ( $employer ) {
            echo '<div class="apprco-quick-item">';
            echo '<strong>' . esc_html__( 'Employer:', 'apprenticeship-connect' ) . '</strong> ';
            echo esc_html( $employer );
            echo '</div>';
        }

        if ( $level ) {
            echo '<div class="apprco-quick-item">';
            echo '<strong>' . esc_html__( 'Level:', 'apprenticeship-connect' ) . '</strong> ';
            echo '<span class="apprco-level-badge">' . esc_html( $level ) . '</span>';
            echo '</div>';
        }

        if ( $positions ) {
            echo '<div class="apprco-quick-item">';
            echo '<strong>' . esc_html__( 'Positions:', 'apprenticeship-connect' ) . '</strong> ';
            echo esc_html( $positions );
            echo '</div>';
        }

        if ( $posted_date ) {
            echo '<div class="apprco-quick-item">';
            echo '<strong>' . esc_html__( 'Posted:', 'apprenticeship-connect' ) . '</strong> ';
            echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $posted_date ) ) );
            echo '</div>';
        }

        if ( $closing_date ) {
            $is_expired = strtotime( $closing_date ) < current_time( 'timestamp' );
            $class = $is_expired ? 'apprco-date-expired' : 'apprco-date-active';
            echo '<div class="apprco-quick-item">';
            echo '<strong>' . esc_html__( 'Closes:', 'apprenticeship-connect' ) . '</strong> ';
            echo '<span class="' . esc_attr( $class ) . '">' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $closing_date ) ) ) . '</span>';
            if ( $is_expired ) {
                echo ' <span class="apprco-expired-label">' . esc_html__( '(Expired)', 'apprenticeship-connect' ) . '</span>';
            }
            echo '</div>';
        }

        if ( $vacancy_url ) {
            echo '<div class="apprco-quick-item apprco-quick-actions">';
            echo '<a href="' . esc_url( $vacancy_url ) . '" target="_blank" rel="noopener" class="button">';
            echo esc_html__( 'View on Gov.uk', 'apprenticeship-connect' ) . ' &raquo;';
            echo '</a>';
            echo '</div>';
        }

        // Shortcode helper
        echo '<div class="apprco-quick-item apprco-shortcode-helper">';
        echo '<strong>' . esc_html__( 'Shortcode:', 'apprenticeship-connect' ) . '</strong>';
        echo '<code>[apprco_vacancy id="' . esc_attr( $post->ID ) . '"]</code>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render a single field
     *
     * @param int    $post_id  Post ID.
     * @param string $meta_key Meta key.
     * @param array  $field    Field configuration.
     */
    private function render_field( int $post_id, string $meta_key, array $field ): void {
        $value      = get_post_meta( $post_id, $meta_key, true );
        $field_id   = 'apprco_field_' . sanitize_key( $meta_key );
        $field_name = 'apprco_meta[' . $meta_key . ']';
        $field_type = $field['type'] ?? 'text';
        $is_readonly = ! empty( $field['readonly'] );
        $css_class  = 'apprco-field apprco-field-' . $field_type;

        if ( $field_type === 'wysiwyg' ) {
            $css_class .= ' apprco-field-full-width';
        }

        echo '<div class="' . esc_attr( $css_class ) . '">';
        echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $field['label'] ) . '</label>';

        switch ( $field_type ) {
            case 'textarea':
                $rows = $field['rows'] ?? 4;
                echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" rows="' . esc_attr( $rows ) . '"';
                if ( $is_readonly ) {
                    echo ' readonly';
                }
                echo '>' . esc_textarea( $value ) . '</textarea>';
                break;

            case 'wysiwyg':
                wp_editor( $value, $field_id, array(
                    'textarea_name' => $field_name,
                    'media_buttons' => false,
                    'textarea_rows' => 6,
                    'teeny'         => true,
                    'quicktags'     => true,
                ) );
                break;

            case 'select':
                echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '"';
                if ( $is_readonly ) {
                    echo ' disabled';
                }
                echo '>';
                foreach ( $field['options'] as $opt_value => $opt_label ) {
                    $selected = selected( $value, $opt_value, false );
                    echo '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . esc_html( $opt_label ) . '</option>';
                }
                echo '</select>';
                if ( $is_readonly ) {
                    echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '" />';
                }
                break;

            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="1"';
                checked( $value, '1' );
                if ( $is_readonly ) {
                    echo ' disabled';
                }
                echo ' />';
                if ( $is_readonly ) {
                    echo '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '" />';
                }
                break;

            case 'number':
                $min  = isset( $field['min'] ) ? ' min="' . esc_attr( $field['min'] ) . '"' : '';
                $max  = isset( $field['max'] ) ? ' max="' . esc_attr( $field['max'] ) . '"' : '';
                $step = isset( $field['step'] ) ? ' step="' . esc_attr( $field['step'] ) . '"' : '';
                echo '<input type="number" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '"' . $min . $max . $step;
                if ( $is_readonly ) {
                    echo ' readonly';
                }
                echo ' />';
                break;

            case 'date':
                echo '<input type="date" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '"';
                if ( $is_readonly ) {
                    echo ' readonly';
                }
                echo ' />';
                break;

            case 'url':
                echo '<input type="url" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '" class="widefat"';
                if ( $is_readonly ) {
                    echo ' readonly';
                }
                echo ' />';
                break;

            case 'email':
                echo '<input type="email" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '" class="widefat"';
                if ( $is_readonly ) {
                    echo ' readonly';
                }
                echo ' />';
                break;

            case 'tel':
                echo '<input type="tel" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '" class="widefat"';
                if ( $is_readonly ) {
                    echo ' readonly';
                }
                echo ' />';
                break;

            default: // text
                echo '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '" class="widefat"';
                if ( $is_readonly ) {
                    echo ' readonly';
                }
                echo ' />';
                break;
        }

        if ( ! empty( $field['description'] ) ) {
            echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Save meta box data
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_meta_box_data( int $post_id, WP_Post $post ): void {
        // Verify nonce
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Check post type
        if ( 'apprco_vacancy' !== $post->post_type ) {
            return;
        }

        // Process meta fields
        if ( ! isset( $_POST['apprco_meta'] ) || ! is_array( $_POST['apprco_meta'] ) ) {
            return;
        }

        $meta_data = array_map( 'wp_unslash', $_POST['apprco_meta'] );

        foreach ( $this->field_sections as $section ) {
            foreach ( $section['fields'] as $meta_key => $field ) {
                // Skip readonly fields
                if ( ! empty( $field['readonly'] ) ) {
                    continue;
                }

                $value = isset( $meta_data[ $meta_key ] ) ? $meta_data[ $meta_key ] : '';

                // Sanitize based on field type
                $value = $this->sanitize_field_value( $value, $field );

                // Handle checkbox (unchecked = not in POST)
                if ( $field['type'] === 'checkbox' && empty( $meta_data[ $meta_key ] ) ) {
                    $value = '0';
                }

                update_post_meta( $post_id, $meta_key, $value );
            }
        }

        // Update taxonomies based on meta
        $this->update_taxonomies_from_meta( $post_id );
    }

    /**
     * Sanitize field value based on type
     *
     * @param mixed $value Field value.
     * @param array $field Field configuration.
     * @return mixed
     */
    private function sanitize_field_value( $value, array $field ) {
        $type = $field['type'] ?? 'text';

        switch ( $type ) {
            case 'wysiwyg':
            case 'textarea':
                return wp_kses_post( $value );

            case 'url':
                return esc_url_raw( $value );

            case 'email':
                return sanitize_email( $value );

            case 'number':
                return is_numeric( $value ) ? $value : '';

            case 'date':
                // Validate date format
                if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
                    return $value;
                }
                return '';

            case 'checkbox':
                return $value ? '1' : '0';

            case 'select':
                // Validate against options
                $valid_options = array_keys( $field['options'] ?? array() );
                return in_array( $value, $valid_options, true ) ? $value : '';

            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Update taxonomies based on meta values
     *
     * @param int $post_id Post ID.
     */
    private function update_taxonomies_from_meta( int $post_id ): void {
        // Update level taxonomy
        $level = get_post_meta( $post_id, '_apprco_apprenticeship_level', true );
        if ( $level ) {
            wp_set_object_terms( $post_id, $level, 'apprco_level' );
        }

        // Update route taxonomy
        $route = get_post_meta( $post_id, '_apprco_course_route', true );
        if ( $route ) {
            wp_set_object_terms( $post_id, $route, 'apprco_route' );
        }

        // Update employer taxonomy (if not anonymous)
        $employer = get_post_meta( $post_id, '_apprco_employer_name', true );
        $is_anonymous = get_post_meta( $post_id, '_apprco_is_employer_anonymous', true );
        if ( $employer && ! $is_anonymous ) {
            wp_set_object_terms( $post_id, $employer, 'apprco_employer' );
        }
    }

    /**
     * Get all meta field definitions
     *
     * @return array
     */
    public function get_all_fields(): array {
        $all_fields = array();
        foreach ( $this->field_sections as $section ) {
            $all_fields = array_merge( $all_fields, $section['fields'] );
        }
        return $all_fields;
    }

    /**
     * Get field sections
     *
     * @return array
     */
    public function get_field_sections(): array {
        return $this->field_sections;
    }
}
