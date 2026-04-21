<?php
/**
 * Post Types Manager - V3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) die;

class Apprco_Post_Types {

    private static $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
        add_action( 'init', array( $this, 'register_meta_fields' ) );
    }

    public function register_post_types(): void {
        register_post_type( 'apprco_vacancy', array(
            'labels' => array(
                'name' => __( 'Vacancies', 'apprenticeship-connect' ),
                'singular_name' => __( 'Vacancy', 'apprenticeship-connect' ),
                'add_new_item' => __( 'Add New Vacancy', 'apprenticeship-connect' ),
                'edit_item' => __( 'Edit Vacancy', 'apprenticeship-connect' ),
                'view_item' => __( 'View Vacancy', 'apprenticeship-connect' ),
                'search_items' => __( 'Search Vacancies', 'apprenticeship-connect' ),
            ),
            'public' => true,
            'show_in_menu' => 'apprco-dashboard',
            'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-id-alt',
            'has_archive' => true,
            'rewrite' => array( 'slug' => 'apprenticeship' ),
        ) );
    }

    public function register_taxonomies(): void {
        $taxonomies = array(
            'apprco_level'    => __( 'Level', 'apprenticeship-connect' ),
            'apprco_route'    => __( 'Route', 'apprenticeship-connect' ),
            'apprco_employer' => __( 'Employer', 'apprenticeship-connect' ),
        );

        foreach ( $taxonomies as $slug => $label ) {
            register_taxonomy( $slug, 'apprco_vacancy', array(
                'label' => $label,
                'hierarchical' => true,
                'show_in_rest' => true,
            ) );
        }
    }

    public function register_meta_fields(): void {
        $fields = array(
            // Core
            '_apprco_vacancy_reference'    => 'string',
            '_apprco_vacancy_url'          => 'string',
            '_apprco_closing_date'         => 'string',
            '_apprco_posted_date'          => 'string',
            '_apprco_start_date'           => 'string',
            '_apprco_number_of_positions'  => 'integer',

            // Employer
            '_apprco_employer_name'        => 'string',
            '_apprco_employer_description' => 'string',
            '_apprco_employer_website_url' => 'string',
            '_apprco_is_disability_confident' => 'boolean',

            // Location
            '_apprco_address_line_1'       => 'string',
            '_apprco_address_line_2'       => 'string',
            '_apprco_address_line_3'       => 'string',
            '_apprco_address_line_4'       => 'string',
            '_apprco_postcode'             => 'string',
            '_apprco_latitude'             => 'number',
            '_apprco_longitude'            => 'number',

            // Wage
            '_apprco_wage_type'            => 'string',
            '_apprco_wage_amount'          => 'string',
            '_apprco_wage_unit'            => 'string',
            '_apprco_wage_additional'      => 'string',
            '_apprco_hours_per_week'       => 'number',
            '_apprco_working_week_desc'    => 'string',
            '_apprco_expected_duration'    => 'string',

            // Course
            '_apprco_apprenticeship_level' => 'string',
            '_apprco_course_title'         => 'string',
            '_apprco_course_lars_code'     => 'integer',
            '_apprco_course_route'         => 'string',
            '_apprco_course_level'         => 'integer',

            // Requirements
            '_apprco_skills'               => 'string',
            '_apprco_qualifications'       => 'string',
            '_apprco_outcome_description'  => 'string',
            '_apprco_things_to_consider'   => 'string',
        );

        foreach ( $fields as $key => $type ) {
            register_post_meta( 'apprco_vacancy', $key, array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => $type,
            ) );
        }
    }
}
