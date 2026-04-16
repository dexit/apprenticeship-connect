<?php
/**
 * Elementor widget: Vacancy Listing.
 *
 * Renders a filterable, paginated list of appcon_vacancy posts.
 * Works with Elementor Free.
 *
 * @package ApprenticeshipConnector\Elementor
 */

namespace ApprenticeshipConnector\Elementor;

use ApprenticeshipConnector\Core\Settings;
use ApprenticeshipConnector\Blocks\BlocksLoader;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class VacancyListingWidget extends Widget_Base {

	public function get_name(): string {
		return 'appcon_vacancy_listing';
	}

	public function get_title(): string {
		return esc_html__( 'Vacancy Listing', 'apprenticeship-connector' );
	}

	public function get_icon(): string {
		return 'eicon-post-list';
	}

	public function get_categories(): array {
		return [ 'general' ];
	}

	public function get_keywords(): array {
		return [ 'apprenticeship', 'vacancy', 'listing', 'jobs' ];
	}

	// ── Controls ───────────────────────────────────────────────────────────

	protected function register_controls(): void {

		// ── Content: Query ─────────────────────────────────────────────────
		$this->start_controls_section( 'section_query', [
			'label' => esc_html__( 'Query', 'apprenticeship-connector' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'posts_per_page', [
			'label'   => esc_html__( 'Vacancies per page', 'apprenticeship-connector' ),
			'type'    => Controls_Manager::NUMBER,
			'default' => Settings::get( 'vacancies_per_page', 10 ),
			'min'     => 1,
			'max'     => 50,
		] );

		$this->add_control( 'orderby', [
			'label'   => esc_html__( 'Order by', 'apprenticeship-connector' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'date',
			'options' => [
				'date'         => esc_html__( 'Date',         'apprenticeship-connector' ),
				'title'        => esc_html__( 'Title',        'apprenticeship-connector' ),
				'closing_date' => esc_html__( 'Closing Date', 'apprenticeship-connector' ),
			],
		] );

		$this->add_control( 'order', [
			'label'   => esc_html__( 'Order', 'apprenticeship-connector' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'DESC',
			'options' => [
				'DESC' => esc_html__( 'Newest first', 'apprenticeship-connector' ),
				'ASC'  => esc_html__( 'Oldest first', 'apprenticeship-connector' ),
			],
		] );

		$this->add_control( 'show_expired', [
			'label'        => esc_html__( 'Include expired vacancies', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
		] );

		$this->end_controls_section();

		// ── Content: Filters ───────────────────────────────────────────────
		$this->start_controls_section( 'section_filters', [
			'label' => esc_html__( 'Filters', 'apprenticeship-connector' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'filter_level', [
			'label'   => esc_html__( 'Level', 'apprenticeship-connector' ),
			'type'    => Controls_Manager::SELECT,
			'default' => '',
			'options' => self::get_term_options( 'appcon_level' ),
		] );

		$this->add_control( 'filter_route', [
			'label'   => esc_html__( 'Route', 'apprenticeship-connector' ),
			'type'    => Controls_Manager::SELECT,
			'default' => '',
			'options' => self::get_term_options( 'appcon_route' ),
		] );

		$this->end_controls_section();

		// ── Content: Layout ────────────────────────────────────────────────
		$this->start_controls_section( 'section_layout', [
			'label' => esc_html__( 'Layout', 'apprenticeship-connector' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'layout', [
			'label'   => esc_html__( 'Layout style', 'apprenticeship-connector' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'list',
			'options' => [
				'list'  => esc_html__( 'List',  'apprenticeship-connector' ),
				'grid'  => esc_html__( 'Grid',  'apprenticeship-connector' ),
				'table' => esc_html__( 'Table', 'apprenticeship-connector' ),
			],
		] );

		$this->add_control( 'show_search', [
			'label'        => esc_html__( 'Show search bar', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_pagination', [
			'label'        => esc_html__( 'Show pagination', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->end_controls_section();
	}

	// ── Render ─────────────────────────────────────────────────────────────

	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$attributes = [
			'postsPerPage'   => (int) $settings['posts_per_page'],
			'orderBy'        => $settings['orderby'],
			'order'          => $settings['order'],
			'filterLevel'    => $settings['filter_level'],
			'filterRoute'    => $settings['filter_route'],
			'showExpired'    => $settings['show_expired'] === 'yes',
			'layout'         => $settings['layout'],
			'showSearch'     => $settings['show_search']     === 'yes',
			'showPagination' => $settings['show_pagination'] === 'yes',
		];

		echo BlocksLoader::render_vacancy_listing( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	// ── Helpers ────────────────────────────────────────────────────────────

	/**
	 * Build a [ '' => 'All' ] + terms array suitable for a Select control.
	 */
	private static function get_term_options( string $taxonomy ): array {
		$options = [ '' => esc_html__( '— All —', 'apprenticeship-connector' ) ];
		$terms   = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ (string) $term->term_id ] = $term->name;
			}
		}

		return $options;
	}
}
