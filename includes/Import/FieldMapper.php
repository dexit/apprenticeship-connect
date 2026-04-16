<?php
/**
 * Maps API response data to WordPress post / meta / taxonomy structures.
 *
 * @package ApprenticeshipConnector\Import
 */

namespace ApprenticeshipConnector\Import;

class FieldMapper {

	/**
	 * @param array $mappings  key => ['api_path' => 'dot.path', 'default' => mixed]
	 */
	public function __construct( private readonly array $mappings = [] ) {}

	// ── Post data ─────────────────────────────────────────────────────────

	/** Build wp_insert_post-compatible array. */
	public function mapToPost( array $vacancy ): array {
		return [
			'post_type'   => 'appcon_vacancy',
			'post_status' => 'publish',
			'post_title'  => $this->resolve( 'post_title', $vacancy, $vacancy['title'] ?? '' ),
			'post_content' => $this->resolve( 'post_content', $vacancy, $vacancy['description'] ?? '' ),
		];
	}

	// ── Post meta ─────────────────────────────────────────────────────────

	/** Build a flat key => value array of all meta to save. */
	public function mapToMeta( array $vacancy ): array {
		$meta_keys = [
			'_appcon_vacancy_reference'            => 'vacancyReference',
			'_appcon_vacancy_url'                  => 'vacancyUrl',
			'_appcon_application_url'              => 'applicationUrl',
			'_appcon_posted_date'                  => 'postedDate',
			'_appcon_closing_date'                 => 'closingDate',
			'_appcon_start_date'                   => 'startDate',
			'_appcon_number_of_positions'          => 'numberOfPositions',
			'_appcon_hours_per_week'               => 'hoursPerWeek',
			'_appcon_expected_duration'            => 'expectedDuration',
			'_appcon_wage_type'                    => 'wage.wageType',
			'_appcon_wage_amount'                  => 'wage.wageAmount',
			'_appcon_wage_unit'                    => 'wage.wageUnit',
			'_appcon_wage_additional_info'         => 'wage.wageAdditionalInformation',
			'_appcon_working_week_description'     => 'wage.workingWeekDescription',
			'_appcon_employer_name'                => 'employerName',
			'_appcon_employer_description'         => 'employerDescription',
			'_appcon_employer_website'             => 'employerWebsiteUrl',
			'_appcon_employer_contact_name'        => 'employerContactName',
			'_appcon_employer_contact_phone'       => 'employerContactPhone',
			'_appcon_employer_contact_email'       => 'employerContactEmail',
			'_appcon_provider_name'                => 'providerName',
			'_appcon_ukprn'                        => 'ukprn',
			'_appcon_lars_code'                    => 'course.larsCode',
			'_appcon_course_title'                 => 'course.title',
			'_appcon_course_level'                 => 'course.level',
			'_appcon_course_route'                 => 'course.route',
			'_appcon_course_type'                  => 'course.type',
			'_appcon_apprenticeship_level'         => 'apprenticeshipLevel',
			'_appcon_training_description'         => 'trainingDescription',
			'_appcon_additional_training_description' => 'additionalTrainingDescription',
			'_appcon_outcome_description'          => 'outcomeDescription',
			'_appcon_full_description'             => 'fullDescription',
			'_appcon_things_to_consider'           => 'thingsToConsider',
			'_appcon_company_benefits'             => 'companyBenefitsInformation',
			'_appcon_is_disability_confident'      => 'isDisabilityConfident',
			'_appcon_is_national_vacancy'          => 'isNationalVacancy',
			'_appcon_is_national_vacancy_details'  => 'isNationalVacancyDetails',
			'_appcon_skills'                       => 'skills',
			'_appcon_qualifications'               => 'qualifications',
			'_appcon_addresses'                    => 'addresses',
		];

		$meta = [];

		foreach ( $meta_keys as $meta_key => $default_path ) {
			// Check if the user has a custom mapping for this meta key.
			$path  = $this->mappings[ $meta_key ]['api_path'] ?? $default_path;
			$value = $this->dot_get( $vacancy, $path );

			if ( null !== $value ) {
				$meta[ $meta_key ] = $value;
			}
		}

		// Extract primary address fields.
		$first_address = $vacancy['addresses'][0] ?? [];
		if ( $first_address ) {
			$meta['_appcon_postcode']  = $first_address['postcode']  ?? '';
			$meta['_appcon_latitude']  = $first_address['latitude']  ?? null;
			$meta['_appcon_longitude'] = $first_address['longitude'] ?? null;
		}

		return $meta;
	}

	// ── Taxonomies ────────────────────────────────────────────────────────

	/**
	 * Build a taxonomy => terms[] map ready for wp_set_object_terms().
	 *
	 * @return array<string, string[]>
	 */
	public function mapToTaxonomies( array $vacancy ): array {
		$taxonomies = [];

		// Level
		$level = $vacancy['course']['level'] ?? null;
		if ( null !== $level ) {
			$taxonomies['appcon_level'] = [ 'Level ' . $level ];
		}

		// Route
		$route = $vacancy['course']['route'] ?? null;
		if ( $route ) {
			$taxonomies['appcon_route'] = [ $route ];
		}

		// LARS code
		$lars = $vacancy['course']['larsCode'] ?? null;
		if ( $lars ) {
			$taxonomies['appcon_lars_code'] = [ (string) $lars ];
		}

		// Skills (array)
		$skills = $vacancy['skills'] ?? [];
		if ( ! empty( $skills ) ) {
			$taxonomies['appcon_skill'] = array_map( 'strval', $skills );
		}

		// Employer
		$employer = $vacancy['employerName'] ?? null;
		if ( $employer ) {
			$taxonomies['appcon_employer'] = [ $employer ];
		}

		return $taxonomies;
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	/**
	 * Resolve a mapped or default value from the vacancy array.
	 */
	private function resolve( string $wp_field, array $vacancy, mixed $default ): mixed {
		if ( isset( $this->mappings[ $wp_field ]['api_path'] ) ) {
			$val = $this->dot_get( $vacancy, $this->mappings[ $wp_field ]['api_path'] );
			if ( null !== $val ) {
				return $val;
			}
		}
		return $default;
	}

	/**
	 * Get a value from a nested array using dot notation (e.g. "wage.wageType").
	 */
	private function dot_get( array $data, string $path ): mixed {
		$keys = explode( '.', $path );
		foreach ( $keys as $key ) {
			if ( ! is_array( $data ) || ! array_key_exists( $key, $data ) ) {
				return null;
			}
			$data = $data[ $key ];
		}
		return $data;
	}
}
