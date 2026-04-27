<?php
/**
 * Secure Custom Fields (SCF / ACF) field group registration.
 *
 * Uses the ACF PHP API to register all metaboxes programmatically.
 * SCF (Secure Custom Fields) is the WordPress.org maintained fork of ACF and
 * uses the exact same PHP API (functions prefixed with `acf_`).
 *
 * Field groups are registered on the `acf/init` hook so they work whether the
 * plugin is active in the DB or loaded from PHP (no DB rows needed).
 *
 * @package ApprenticeshipConnector\Admin
 */

namespace ApprenticeshipConnector\Admin;

use ApprenticeshipConnector\PostTypes\VacancyPostType;

class SCFFields {

	/**
	 * Register all field groups.  Called on `acf/init`.
	 */
	public static function register_field_groups(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return; // SCF / ACF not active.
		}

		self::vacancy_core_fields();
		self::vacancy_employer_fields();
		self::vacancy_provider_fields();
		self::vacancy_course_fields();
		self::vacancy_descriptions_fields();
		self::vacancy_location_fields();
		self::vacancy_flags_fields();
		self::vacancy_arrays_fields();
	}

	// ── Field groups ───────────────────────────────────────────────────────

	private static function vacancy_core_fields(): void {
		acf_add_local_field_group( [
			'key'      => 'group_appcon_core',
			'title'    => __( 'Core Vacancy Info', 'apprenticeship-connector' ),
			'fields'   => [
				self::text( 'field_appcon_vacancy_reference', 'Vacancy Reference', '_appcon_vacancy_reference', true ),
				self::url(  'field_appcon_vacancy_url',       'Vacancy URL',       '_appcon_vacancy_url' ),
				self::url(  'field_appcon_application_url',   'Application URL',   '_appcon_application_url' ),
				self::date( 'field_appcon_posted_date',       'Posted Date',       '_appcon_posted_date' ),
				self::date( 'field_appcon_closing_date',      'Closing Date',      '_appcon_closing_date' ),
				self::date( 'field_appcon_start_date',        'Start Date',        '_appcon_start_date' ),
				self::number( 'field_appcon_positions',       'Number of Positions', '_appcon_number_of_positions' ),
				self::number( 'field_appcon_hours',           'Hours Per Week',    '_appcon_hours_per_week' ),
				self::text(  'field_appcon_duration',         'Expected Duration', '_appcon_expected_duration' ),
			],
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'appcon_vacancy' ] ] ],
			'position' => 'normal',
			'style'    => 'default',
			'active'   => true,
		] );
	}

	private static function vacancy_employer_fields(): void {
		acf_add_local_field_group( [
			'key'    => 'group_appcon_employer',
			'title'  => __( 'Employer Details (Stage 2)', 'apprenticeship-connector' ),
			'fields' => [
				self::text(  'field_appcon_employer_name',          'Employer Name',          '_appcon_employer_name' ),
				self::textarea( 'field_appcon_employer_description','Employer Description',   '_appcon_employer_description' ),
				self::url(   'field_appcon_employer_website',       'Employer Website',       '_appcon_employer_website' ),
				self::text(  'field_appcon_employer_contact_name',  'Contact Name',           '_appcon_employer_contact_name' ),
				self::text(  'field_appcon_employer_contact_phone', 'Contact Phone',          '_appcon_employer_contact_phone' ),
				self::email( 'field_appcon_employer_contact_email', 'Contact Email',          '_appcon_employer_contact_email' ),
			],
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'appcon_vacancy' ] ] ],
			'position' => 'normal',
			'active'   => true,
		] );
	}

	private static function vacancy_provider_fields(): void {
		acf_add_local_field_group( [
			'key'    => 'group_appcon_provider',
			'title'  => __( 'Training Provider (Stage 2)', 'apprenticeship-connector' ),
			'fields' => [
				self::text(   'field_appcon_provider_name', 'Provider Name', '_appcon_provider_name' ),
				self::number( 'field_appcon_ukprn',         'UKPRN',         '_appcon_ukprn' ),
			],
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'appcon_vacancy' ] ] ],
			'position' => 'normal',
			'active'   => true,
		] );
	}

	private static function vacancy_course_fields(): void {
		acf_add_local_field_group( [
			'key'    => 'group_appcon_course',
			'title'  => __( 'Course / LARS Details', 'apprenticeship-connector' ),
			'fields' => [
				self::number( 'field_appcon_lars_code',         'LARS Code',            '_appcon_lars_code' ),
				self::text(   'field_appcon_course_title',      'Course Title',         '_appcon_course_title' ),
				self::number( 'field_appcon_course_level',      'Course Level',         '_appcon_course_level' ),
				self::text(   'field_appcon_course_route',      'Course Route',         '_appcon_course_route' ),
				self::text(   'field_appcon_course_type',       'Course Type',          '_appcon_course_type' ),
				self::text(   'field_appcon_app_level',         'Apprenticeship Level', '_appcon_apprenticeship_level' ),
			],
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'appcon_vacancy' ] ] ],
			'position' => 'normal',
			'active'   => true,
		] );
	}

	private static function vacancy_descriptions_fields(): void {
		acf_add_local_field_group( [
			'key'    => 'group_appcon_descriptions',
			'title'  => __( 'Descriptions (Stage 2)', 'apprenticeship-connector' ),
			'fields' => [
				self::textarea( 'field_appcon_training_desc',     'Training Description',            '_appcon_training_description' ),
				self::textarea( 'field_appcon_add_training_desc', 'Additional Training Description', '_appcon_additional_training_description' ),
				self::textarea( 'field_appcon_outcome_desc',      'Outcome Description',             '_appcon_outcome_description' ),
				self::wysiwyg(  'field_appcon_full_desc',         'Full Description',                '_appcon_full_description' ),
				self::textarea( 'field_appcon_things_to_consider','Things to Consider',              '_appcon_things_to_consider' ),
				self::textarea( 'field_appcon_benefits',          'Company Benefits',                '_appcon_company_benefits' ),
			],
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'appcon_vacancy' ] ] ],
			'position' => 'normal',
			'active'   => true,
		] );
	}

	private static function vacancy_location_fields(): void {
		acf_add_local_field_group( [
			'key'    => 'group_appcon_location',
			'title'  => __( 'Location', 'apprenticeship-connector' ),
			'fields' => [
				self::text(   'field_appcon_postcode',   'Postcode',   '_appcon_postcode' ),
				self::number( 'field_appcon_latitude',   'Latitude',   '_appcon_latitude' ),
				self::number( 'field_appcon_longitude',  'Longitude',  '_appcon_longitude' ),
				self::number( 'field_appcon_distance',   'Distance (miles)', '_appcon_distance' ),
			],
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'appcon_vacancy' ] ] ],
			'position' => 'side',
			'active'   => true,
		] );
	}

	private static function vacancy_flags_fields(): void {
		acf_add_local_field_group( [
			'key'    => 'group_appcon_flags',
			'title'  => __( 'Flags (Stage 2)', 'apprenticeship-connector' ),
			'fields' => [
				self::true_false( 'field_appcon_disability',   'Disability Confident',    '_appcon_is_disability_confident' ),
				self::true_false( 'field_appcon_national',     'National Vacancy',        '_appcon_is_national_vacancy' ),
				self::textarea(   'field_appcon_national_det', 'National Vacancy Details','_appcon_is_national_vacancy_details' ),
			],
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'appcon_vacancy' ] ] ],
			'position' => 'side',
			'active'   => true,
		] );
	}

	private static function vacancy_arrays_fields(): void {
		acf_add_local_field_group( [
			'key'    => 'group_appcon_arrays',
			'title'  => __( 'Skills & Qualifications (Stage 2)', 'apprenticeship-connector' ),
			'fields' => [
				[
					'key'          => 'field_appcon_skills',
					'label'        => __( 'Skills', 'apprenticeship-connector' ),
					'name'         => '_appcon_skills',
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => __( 'Add Skill', 'apprenticeship-connector' ),
					'sub_fields'   => [
						self::text( 'field_appcon_skill_name', 'Skill', 'skill_name' ),
					],
				],
				[
					'key'          => 'field_appcon_qualifications',
					'label'        => __( 'Qualifications', 'apprenticeship-connector' ),
					'name'         => '_appcon_qualifications',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => __( 'Add Qualification', 'apprenticeship-connector' ),
					'sub_fields'   => [
						self::text( 'field_appcon_qual_type',      'Type',      'qualificationType' ),
						self::text( 'field_appcon_qual_subject',   'Subject',   'subject' ),
						self::text( 'field_appcon_qual_grade',     'Grade',     'grade' ),
						self::select( 'field_appcon_qual_weight', 'Weighting', 'weighting', [
							'Essential' => 'Essential',
							'Desired'   => 'Desired',
						] ),
					],
				],
			],
			'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'appcon_vacancy' ] ] ],
			'position' => 'normal',
			'active'   => true,
		] );
	}

	// ── Field builder helpers ──────────────────────────────────────────────

	private static function text( string $key, string $label, string $name, bool $readonly = false ): array {
		return array_filter( [
			'key'      => $key,
			'label'    => __( $label, 'apprenticeship-connector' ),
			'name'     => $name,
			'type'     => 'text',
			'readonly' => $readonly ? 1 : 0,
		] );
	}

	private static function textarea( string $key, string $label, string $name ): array {
		return [
			'key'   => $key,
			'label' => __( $label, 'apprenticeship-connector' ),
			'name'  => $name,
			'type'  => 'textarea',
			'rows'  => 4,
		];
	}

	private static function wysiwyg( string $key, string $label, string $name ): array {
		return [
			'key'   => $key,
			'label' => __( $label, 'apprenticeship-connector' ),
			'name'  => $name,
			'type'  => 'wysiwyg',
		];
	}

	private static function url( string $key, string $label, string $name ): array {
		return [
			'key'   => $key,
			'label' => __( $label, 'apprenticeship-connector' ),
			'name'  => $name,
			'type'  => 'url',
		];
	}

	private static function email( string $key, string $label, string $name ): array {
		return [
			'key'   => $key,
			'label' => __( $label, 'apprenticeship-connector' ),
			'name'  => $name,
			'type'  => 'email',
		];
	}

	private static function number( string $key, string $label, string $name ): array {
		return [
			'key'   => $key,
			'label' => __( $label, 'apprenticeship-connector' ),
			'name'  => $name,
			'type'  => 'number',
		];
	}

	private static function date( string $key, string $label, string $name ): array {
		return [
			'key'            => $key,
			'label'          => __( $label, 'apprenticeship-connector' ),
			'name'           => $name,
			'type'           => 'date_picker',
			'display_format' => 'd/m/Y',
			'return_format'  => 'Y-m-d',
		];
	}

	private static function true_false( string $key, string $label, string $name ): array {
		return [
			'key'   => $key,
			'label' => __( $label, 'apprenticeship-connector' ),
			'name'  => $name,
			'type'  => 'true_false',
			'ui'    => 1,
		];
	}

	private static function select( string $key, string $label, string $name, array $choices ): array {
		return [
			'key'     => $key,
			'label'   => __( $label, 'apprenticeship-connector' ),
			'name'    => $name,
			'type'    => 'select',
			'choices' => $choices,
		];
	}
}
