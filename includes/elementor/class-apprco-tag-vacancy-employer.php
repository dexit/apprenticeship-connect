<?php
/**
 * Vacancy Employer Tag Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Tag_Vacancy_Employer
 */
class Apprco_Tag_Vacancy_Employer extends Apprco_Tag_Base {

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'apprco-vacancy-employer';
	}

	/**
	 * Get title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Employer Name', 'apprenticeship-connect' );
	}

	/**
	 * Get meta key.
	 *
	 * @return string
	 */
	protected function get_meta_key(): string {
		return '_apprco_employer_name';
	}
}
