<?php
/**
 * Elementor Integration Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Elementor
 *
 * Handles registration of Elementor widgets and dynamic tags.
 */
class Apprco_Elementor {

	/**
	 * Dynamic tags group name.
	 */
	public const TAG_GROUP = 'apprco-tags';

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Elementor|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_tags' ) );
	}

	/**
	 * Registers dynamic tags with Elementor.
	 *
	 * @param object $dynamic_tags Elementor dynamic tags manager.
	 * @return void
	 */
	public function register_tags( $dynamic_tags ): void {
		if ( ! class_exists( 'Apprco_Tag_Base' ) ) {
			require_once APPRCO_PLUGIN_DIR . 'includes/elementor/class-apprco-tag-base.php';
		}

		$tags = array(
			'Vacancy_Title',
			'Vacancy_Employer',
			'Vacancy_Postcode',
		);

		foreach ( $tags as $tag_class ) {
			$file = APPRCO_PLUGIN_DIR . 'includes/elementor/class-apprco-tag-' . str_replace( '_', '-', strtolower( $tag_class ) ) . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
				$full_class = 'Apprco_Tag_' . $tag_class;
				$dynamic_tags->register( new $full_class() );
			}
		}
	}
}
