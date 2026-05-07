<?php
/**
 * Vacancy Title Tag Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Tag_Vacancy_Title
 */
class Apprco_Tag_Vacancy_Title extends Apprco_Tag_Base {

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'apprco-vacancy-title';
	}

	/**
	 * Get title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Vacancy Title', 'apprenticeship-connect' );
	}

	/**
	 * Get meta key.
	 *
	 * @return string
	 */
	protected function get_meta_key(): string {
		return '';
	}

	/**
	 * Render the tag.
	 *
	 * @return void
	 */
	public function render(): void {
		echo esc_html( get_the_title() );
	}
}
