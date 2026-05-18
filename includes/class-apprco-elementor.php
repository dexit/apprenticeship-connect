<?php
/**
 * Elementor Integration — Dynamic Tags + Jobs Archive Widget
 *
 * Registers:
 *  1. Dynamic tags for vacancy CPT fields (title, employer, postcode).
 *  2. The `apprco-jobs-archive` widget (Elementor v4.x) — renders the full
 *     self-hosted apprenticeship archive via Apprco_Archive::render().
 *
 * Compatible with Elementor v4.x atomic widget architecture while maintaining
 * backwards compatibility with Elementor v3.x.
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Elementor
 */
class Apprco_Elementor {

	/** Dynamic tags group name. */
	public const TAG_GROUP = 'apprco-tags';

	/** @var self|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_tags' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
	}

	/**
	 * Register custom widget category "Apprenticeship Connect".
	 *
	 * @param object $manager Elementor categories manager.
	 * @return void
	 */
	public function register_category( $manager ): void {
		$manager->add_category(
			'apprco',
			array(
				'title' => __( 'Apprenticeship Connect', 'apprenticeship-connect' ),
				'icon'  => 'eicon-posts-grid',
			)
		);
	}

	/**
	 * Register Elementor widgets.
	 *
	 * @param object $manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( $manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once APPRCO_PLUGIN_DIR . 'includes/elementor/class-apprco-widget-jobs-archive.php';
		$manager->register( new Apprco_Widget_Jobs_Archive() );
	}

	/**
	 * Register dynamic tags.
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
