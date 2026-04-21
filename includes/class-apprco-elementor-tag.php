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
		$this->add_control(
			'field',
			array(
				'label'   => __( 'Select Field', 'apprenticeship-connect' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'_apprco_employer_name' => 'Employer Name',
					'_apprco_wage_amount'   => 'Wage Amount',
					'_apprco_closing_date'  => 'Closing Date',
					'_apprco_postcode'      => 'Postcode',
				),
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
