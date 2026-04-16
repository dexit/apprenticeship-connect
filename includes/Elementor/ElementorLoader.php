<?php
/**
 * Elementor integration bootstrap.
 *
 * Registers the plugin's Elementor widgets only when Elementor is active,
 * and adds the appcon_vacancy CPT to Elementor's query type filter so it
 * works with the Loop Grid / Query Builder widgets.
 *
 * @package ApprenticeshipConnector\Elementor
 */

namespace ApprenticeshipConnector\Elementor;

class ElementorLoader {

	/**
	 * Register hooks.  Called from Plugin::define_public_hooks().
	 */
	public static function init(): void {
		// Bail early if Elementor is not loaded.
		add_action( 'elementor/init', [ self::class, 'on_elementor_init' ] );
	}

	/**
	 * Fires after Elementor is fully initialised.
	 */
	public static function on_elementor_init(): void {
		// Register custom widgets.
		add_action( 'elementor/widgets/register', [ self::class, 'register_widgets' ] );

		// Expose vacancy meta fields as Elementor dynamic tags.
		add_action( 'elementor/dynamic_tags/register', [ self::class, 'register_dynamic_tags' ] );

		// Allow appcon_vacancy in Loop Grid query types (Elementor Pro).
		add_filter( 'elementor/query/post_type_filter', [ self::class, 'add_vacancy_post_type' ] );

		// Allow the Query Widget to include our CPT in the post-type list.
		add_filter( 'elementor/query/query_control_post_type_filter', [ self::class, 'add_vacancy_post_type' ] );

		// Support "current post" meta in dynamic tags for single vacancy templates.
		add_filter( 'elementor_pro/dynamic_tags/post_meta', [ self::class, 'expose_vacancy_meta' ], 10, 2 );
	}

	/**
	 * Register widgets with Elementor's widget manager.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widget manager.
	 */
	public static function register_widgets( $widgets_manager ): void {
		require_once APPCON_DIR . 'includes/Elementor/VacancyListingWidget.php';
		require_once APPCON_DIR . 'includes/Elementor/VacancyCardWidget.php';

		$widgets_manager->register( new VacancyListingWidget() );
		$widgets_manager->register( new VacancyCardWidget() );
	}

	/**
	 * Register vacancy meta as Elementor dynamic tags (Elementor Pro).
	 *
	 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags Dynamic tags manager.
	 */
	public static function register_dynamic_tags( $dynamic_tags ): void {
		if ( ! class_exists( '\ElementorPro\Modules\DynamicTags\Module' ) ) {
			return; // Elementor Free only – skip dynamic tags.
		}

		require_once APPCON_DIR . 'includes/Elementor/VacancyDynamicTag.php';

		$dynamic_tags->register_group( 'apprenticeship-connector', [
			'title' => __( 'Apprenticeship Vacancy', 'apprenticeship-connector' ),
		] );

		$dynamic_tags->register( new VacancyDynamicTag() );
	}

	/**
	 * Add appcon_vacancy to Elementor's post-type selector (used in Loop Grid
	 * and Query Widget).
	 *
	 * @param  array $post_types Existing post types array [ slug => label ].
	 * @return array
	 */
	public static function add_vacancy_post_type( array $post_types ): array {
		$post_types['appcon_vacancy'] = __( 'Apprenticeship Vacancies', 'apprenticeship-connector' );
		return $post_types;
	}

	/**
	 * Expose vacancy-specific post meta for the "Post Meta" dynamic tag.
	 *
	 * @param  mixed  $value   Current meta value.
	 * @param  string $meta_key Meta key requested.
	 * @return mixed
	 */
	public static function expose_vacancy_meta( $value, string $meta_key ) {
		if ( get_post_type() !== 'appcon_vacancy' ) {
			return $value;
		}
		return get_post_meta( get_the_ID(), $meta_key, true );
	}
}
