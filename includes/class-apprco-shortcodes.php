<?php
/**
 * Shortcodes Manager
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Shortcodes {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'apprco_vacancies', array( $this, 'vacancies_shortcode' ) );
	}

	public function vacancies_shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'limit' => 10 ), $atts );
		return Apprco_Blocks::get_instance()->render_vacancy_list( array( 'limit' => (int) $atts['limit'] ) );
	}
}
