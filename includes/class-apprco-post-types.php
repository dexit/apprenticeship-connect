<?php
/**
 * Post Types and Taxonomies Manager
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Post_Types
 *
 * Handles registration of the 'apprco_vacancy' post type and related taxonomies.
 */
class Apprco_Post_Types {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Post_Types|null
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
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
	}

	/**
	 * Registers the 'apprco_vacancy' custom post type.
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		register_post_type(
			'apprco_vacancy',
			array(
				'labels'       => array(
					'name'          => __( 'Vacancies', 'apprenticeship-connect' ),
					'singular_name' => __( 'Vacancy', 'apprenticeship-connect' ),
					'add_new_item'  => __( 'Add New Vacancy', 'apprenticeship-connect' ),
					'edit_item'     => __( 'Edit Vacancy', 'apprenticeship-connect' ),
					'view_item'     => __( 'View Vacancy', 'apprenticeship-connect' ),
					'search_items'  => __( 'Search Vacancies', 'apprenticeship-connect' ),
				),
				'public'       => true,
				'show_in_menu' => 'apprco-dashboard',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-id-alt',
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'apprenticeship' ),
			)
		);
	}

	/**
	 * Registers taxonomies for vacancies.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		$taxonomies = array(
			'apprco_level'    => __( 'Level', 'apprenticeship-connect' ),
			'apprco_route'    => __( 'Route', 'apprenticeship-connect' ),
			'apprco_employer' => __( 'Employer', 'apprenticeship-connect' ),
		);

		foreach ( $taxonomies as $slug => $label ) {
			register_taxonomy(
				$slug,
				'apprco_vacancy',
				array(
					'label'        => $label,
					'hierarchical' => true,
					'show_in_rest' => true,
				)
			);
		}
	}

	/**
	 * Registers meta fields for REST API and Gutenberg compatibility.
	 *
	 * @return void
	 */
	public function register_meta_fields(): void {
		$fields = array(
			'_apprco_vacancy_reference'    => 'string',
			'_apprco_vacancy_url'          => 'string',
			'_apprco_closing_date'         => 'string',
			'_apprco_posted_date'          => 'string',
			'_apprco_employer_name'        => 'string',
			'_apprco_postcode'             => 'string',
			'_apprco_wage_amount'          => 'string',
			'_apprco_apprenticeship_level' => 'string',
			'_apprco_course_title'         => 'string',
		);

		foreach ( $fields as $key => $type ) {
			register_post_meta(
				'apprco_vacancy',
				$key,
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => $type,
				)
			);
		}
	}
}
