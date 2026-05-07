<?php
/**
 * Vacancy Postcode Tag Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Tag_Vacancy_Postcode
 */
class Apprco_Tag_Vacancy_Postcode extends Apprco_Tag_Base {

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'apprco-vacancy-postcode';
	}

	/**
	 * Get title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Postcode', 'apprenticeship-connect' );
	}

	/**
	 * Get meta key.
	 *
	 * @return string
	 */
	protected function get_meta_key(): string {
		return '_apprco_postcode';
	}
}
