<?php
/**
 * Elementor Dynamic Tag for Vacancy Fields
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Vacancy_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {
	public function get_name() {
		return 'apprco-vacancy-field';
	}
	public function get_title() {
		return __( 'Vacancy Field', 'apprenticeship-connect' );
	}
	public function get_group() {
		return 'post';
	}
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
        $meta_fields = array(
            '_apprco_employer_name'           => 'Employer Name',
            '_apprco_wage_amount'             => 'Wage Amount',
            '_apprco_wage_type'               => 'Wage Type',
            '_apprco_wage_unit'               => 'Wage Unit',
            '_apprco_closing_date'            => 'Closing Date',
            '_apprco_postcode'                => 'Postcode',
            '_apprco_address_line_1'          => 'Address Line 1',
            '_apprco_apprenticeship_level'    => 'Apprenticeship Level',
            '_apprco_expected_duration'       => 'Duration',
            '_apprco_hours_per_week'          => 'Hours Per Week',
            '_apprco_vacancy_reference'       => 'Vacancy Reference',
            '_apprco_course_title'            => 'Course Title',
            '_apprco_course_route'            => 'Course Route',
            '_apprco_skills'                  => 'Skills Required',
            '_apprco_qualifications'          => 'Qualifications',
            '_apprco_outcome_description'     => 'Outcome',
            '_apprco_posted_date'             => 'Posted Date',
            '_apprco_start_date'              => 'Start Date',
        );

		$this->add_control(
			'field',
			array(
				'label'   => __( 'Select Field', 'apprenticeship-connect' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $meta_fields,
			)
		);
	}

	public function render() {
		$field = $this->get_settings( 'field' );
		if ( ! $field ) {
			return;
		}
		echo esc_html( get_post_meta( get_the_ID(), $field, true ) );
	}
}
