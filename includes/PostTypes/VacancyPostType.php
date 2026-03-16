<?php
/**
 * Vacancy custom post type.
 *
 * @package ApprenticeshipConnector\PostTypes
 */

namespace ApprenticeshipConnector\PostTypes;

class VacancyPostType {

	public static function register(): void {
		register_post_type( 'appcon_vacancy', [
			'labels' => [
				'name'               => __( 'Vacancies',        'apprenticeship-connector' ),
				'singular_name'      => __( 'Vacancy',          'apprenticeship-connector' ),
				'add_new'            => __( 'Add New',           'apprenticeship-connector' ),
				'add_new_item'       => __( 'Add New Vacancy',   'apprenticeship-connector' ),
				'edit_item'          => __( 'Edit Vacancy',      'apprenticeship-connector' ),
				'new_item'           => __( 'New Vacancy',       'apprenticeship-connector' ),
				'view_item'          => __( 'View Vacancy',      'apprenticeship-connector' ),
				'search_items'       => __( 'Search Vacancies',  'apprenticeship-connector' ),
				'not_found'          => __( 'No vacancies found','apprenticeship-connector' ),
				'not_found_in_trash' => __( 'No vacancies in trash', 'apprenticeship-connector' ),
				'menu_name'          => __( 'Vacancies',         'apprenticeship-connector' ),
			],
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => 'apprenticeship-connector',
			'show_in_rest'       => true,
			'has_archive'        => true,
			'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'taxonomies'         => [
				'appcon_level',
				'appcon_route',
				'appcon_lars_code',
				'appcon_skill',
				'appcon_employer',
			],
			'rewrite'            => [ 'slug' => 'vacancies', 'with_front' => false ],
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
		] );
	}

	/**
	 * Register all vacancy meta fields for REST + SCF fallback.
	 */
	public static function register_meta_fields(): void {
		foreach ( self::meta_schema() as $key => $args ) {
			register_post_meta( 'appcon_vacancy', $key, [
				'type'              => $args['type'],
				'description'       => $args['description'] ?? '',
				'single'            => $args['single'] ?? true,
				'show_in_rest'      => true,
				'sanitize_callback' => $args['sanitize'] ?? null,
			] );
		}
	}

	/**
	 * Full list of meta keys mapped to their types (used by both
	 * register_post_meta and SCF field registration).
	 *
	 * @return array<string, array{type:string, description:string, single?:bool, sanitize?:string}>
	 */
	public static function meta_schema(): array {
		return [
			// ── Core ────────────────────────────────────────────────────
			'_appcon_vacancy_reference'              => [ 'type' => 'string',  'description' => 'Unique vacancy reference',    'sanitize' => 'sanitize_text_field' ],
			'_appcon_vacancy_url'                    => [ 'type' => 'string',  'description' => 'Vacancy URL',                 'sanitize' => 'esc_url_raw' ],
			'_appcon_application_url'                => [ 'type' => 'string',  'description' => 'Application URL',             'sanitize' => 'esc_url_raw' ],
			// ── Dates ───────────────────────────────────────────────────
			'_appcon_posted_date'                    => [ 'type' => 'string',  'description' => 'Date posted' ],
			'_appcon_closing_date'                   => [ 'type' => 'string',  'description' => 'Closing date' ],
			'_appcon_start_date'                     => [ 'type' => 'string',  'description' => 'Start date' ],
			// ── Position ────────────────────────────────────────────────
			'_appcon_number_of_positions'            => [ 'type' => 'integer', 'description' => 'Number of positions' ],
			'_appcon_hours_per_week'                 => [ 'type' => 'number',  'description' => 'Hours per week' ],
			'_appcon_expected_duration'              => [ 'type' => 'string',  'description' => 'Expected duration' ],
			// ── Wage ────────────────────────────────────────────────────
			'_appcon_wage_type'                      => [ 'type' => 'string',  'description' => 'Wage type' ],
			'_appcon_wage_amount'                    => [ 'type' => 'number',  'description' => 'Wage amount' ],
			'_appcon_wage_unit'                      => [ 'type' => 'string',  'description' => 'Wage unit' ],
			'_appcon_wage_additional_info'           => [ 'type' => 'string',  'description' => 'Additional wage info' ],
			'_appcon_working_week_description'       => [ 'type' => 'string',  'description' => 'Working week description' ],
			// ── Employer (Stage 2) ───────────────────────────────────────
			'_appcon_employer_name'                  => [ 'type' => 'string',  'description' => 'Employer name' ],
			'_appcon_employer_description'           => [ 'type' => 'string',  'description' => 'Employer description (Stage 2)' ],
			'_appcon_employer_website'               => [ 'type' => 'string',  'description' => 'Employer website (Stage 2)', 'sanitize' => 'esc_url_raw' ],
			'_appcon_employer_contact_name'          => [ 'type' => 'string',  'description' => 'Employer contact name (Stage 2)' ],
			'_appcon_employer_contact_phone'         => [ 'type' => 'string',  'description' => 'Employer contact phone (Stage 2)' ],
			'_appcon_employer_contact_email'         => [ 'type' => 'string',  'description' => 'Employer contact email (Stage 2)', 'sanitize' => 'sanitize_email' ],
			// ── Training Provider (Stage 2) ──────────────────────────────
			'_appcon_provider_name'                  => [ 'type' => 'string',  'description' => 'Training provider (Stage 2)' ],
			'_appcon_ukprn'                          => [ 'type' => 'integer', 'description' => 'UKPRN (Stage 2)' ],
			// ── Course ──────────────────────────────────────────────────
			'_appcon_lars_code'                      => [ 'type' => 'integer', 'description' => 'LARS code' ],
			'_appcon_course_title'                   => [ 'type' => 'string',  'description' => 'Course title' ],
			'_appcon_course_level'                   => [ 'type' => 'integer', 'description' => 'Course level' ],
			'_appcon_course_route'                   => [ 'type' => 'string',  'description' => 'Course route' ],
			'_appcon_course_type'                    => [ 'type' => 'string',  'description' => 'Course type' ],
			'_appcon_apprenticeship_level'           => [ 'type' => 'string',  'description' => 'Apprenticeship level name (Stage 2)' ],
			// ── Descriptions (Stage 2) ──────────────────────────────────
			'_appcon_training_description'           => [ 'type' => 'string',  'description' => 'Training description (Stage 2)' ],
			'_appcon_additional_training_description'=> [ 'type' => 'string',  'description' => 'Additional training description (Stage 2)' ],
			'_appcon_outcome_description'            => [ 'type' => 'string',  'description' => 'Outcome description (Stage 2)' ],
			'_appcon_full_description'               => [ 'type' => 'string',  'description' => 'Full description (Stage 2)' ],
			'_appcon_things_to_consider'             => [ 'type' => 'string',  'description' => 'Things to consider (Stage 2)' ],
			'_appcon_company_benefits'               => [ 'type' => 'string',  'description' => 'Company benefits (Stage 2)' ],
			// ── Flags (Stage 2) ─────────────────────────────────────────
			'_appcon_is_disability_confident'        => [ 'type' => 'boolean', 'description' => 'Disability confident (Stage 2)' ],
			'_appcon_is_national_vacancy'            => [ 'type' => 'boolean', 'description' => 'National vacancy flag (Stage 2)' ],
			'_appcon_is_national_vacancy_details'    => [ 'type' => 'string',  'description' => 'National vacancy details (Stage 2)' ],
			// ── Arrays (Stage 2) ────────────────────────────────────────
			'_appcon_skills'                         => [ 'type' => 'array',   'description' => 'Skills (Stage 2)',          'single' => true ],
			'_appcon_qualifications'                 => [ 'type' => 'array',   'description' => 'Qualifications (Stage 2)', 'single' => true ],
			'_appcon_addresses'                      => [ 'type' => 'array',   'description' => 'Addresses',                'single' => true ],
			// ── Location ────────────────────────────────────────────────
			'_appcon_postcode'                       => [ 'type' => 'string',  'description' => 'Primary postcode' ],
			'_appcon_latitude'                       => [ 'type' => 'number',  'description' => 'Primary latitude' ],
			'_appcon_longitude'                      => [ 'type' => 'number',  'description' => 'Primary longitude' ],
			'_appcon_distance'                       => [ 'type' => 'number',  'description' => 'Distance from search (miles)' ],
		];
	}
}
