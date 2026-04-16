<?php
/**
 * Elementor Pro dynamic tag for appcon_vacancy meta fields.
 *
 * Allows any Elementor Pro text/URL/image control to pull its value
 * from vacancy meta – enabling fully dynamic single-vacancy templates.
 *
 * @package ApprenticeshipConnector\Elementor
 */

namespace ApprenticeshipConnector\Elementor;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Controls_Manager;
use Elementor\Modules\DynamicTags\Module as TagsModule;

class VacancyDynamicTag extends Tag {

	public function get_name(): string {
		return 'appcon-vacancy-meta';
	}

	public function get_title(): string {
		return esc_html__( 'Vacancy Meta Field', 'apprenticeship-connector' );
	}

	public function get_group(): string {
		return 'apprenticeship-connector';
	}

	public function get_categories(): array {
		return [
			TagsModule::TEXT_CATEGORY,
			TagsModule::URL_CATEGORY,
			TagsModule::NUMBER_CATEGORY,
		];
	}

	protected function register_controls(): void {
		$this->add_control( 'meta_key', [
			'label'   => esc_html__( 'Vacancy field', 'apprenticeship-connector' ),
			'type'    => Controls_Manager::SELECT,
			'default' => '_appcon_employer_name',
			'options' => [
				'_appcon_vacancy_reference'      => esc_html__( 'Vacancy Reference',          'apprenticeship-connector' ),
				'_appcon_employer_name'          => esc_html__( 'Employer Name',              'apprenticeship-connector' ),
				'_appcon_employer_description'   => esc_html__( 'Employer Description',       'apprenticeship-connector' ),
				'_appcon_employer_website_url'   => esc_html__( 'Employer Website',           'apprenticeship-connector' ),
				'_appcon_location_address_full'  => esc_html__( 'Location',                   'apprenticeship-connector' ),
				'_appcon_closing_date'           => esc_html__( 'Closing Date',               'apprenticeship-connector' ),
				'_appcon_wage_description'       => esc_html__( 'Wage Description',           'apprenticeship-connector' ),
				'_appcon_wage_amount'            => esc_html__( 'Wage Amount',                'apprenticeship-connector' ),
				'_appcon_hours_per_week'         => esc_html__( 'Hours Per Week',             'apprenticeship-connector' ),
				'_appcon_training_provider_name' => esc_html__( 'Training Provider',          'apprenticeship-connector' ),
				'_appcon_apprenticeship_level'   => esc_html__( 'Apprenticeship Level',       'apprenticeship-connector' ),
				'_appcon_course_title'           => esc_html__( 'Course Title',               'apprenticeship-connector' ),
				'_appcon_expected_duration'      => esc_html__( 'Expected Duration',          'apprenticeship-connector' ),
				'_appcon_number_of_positions'    => esc_html__( 'Number of Positions',        'apprenticeship-connector' ),
				'_appcon_posted_date'            => esc_html__( 'Posted Date',                'apprenticeship-connector' ),
				'_appcon_description'            => esc_html__( 'Full Description',           'apprenticeship-connector' ),
				'_appcon_outcomes'               => esc_html__( 'Outcomes',                   'apprenticeship-connector' ),
			],
		] );
	}

	public function render(): void {
		$meta_key = $this->get_settings( 'meta_key' );
		$value    = get_post_meta( get_the_ID(), $meta_key, true );
		echo wp_kses_post( $value );
	}
}
