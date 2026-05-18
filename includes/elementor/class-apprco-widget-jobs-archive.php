<?php
/**
 * Elementor v4.x Jobs Archive Widget
 *
 * Renders the self-hosted apprenticeship vacancy archive inside Elementor.
 * All output is delegated to Apprco_Archive::render() — the same render
 * engine used by the Gutenberg block and [apprco_jobs] shortcode.
 *
 * Controls are grouped into four tabs/sections:
 *  Layout   — columns, per-page, grid vs list
 *  Search   — toggle search bar, distance filter
 *  Filters  — toggle filter row, default level/route
 *  Style    — primary colour, card style
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Widget_Jobs_Archive
 */
class Apprco_Widget_Jobs_Archive extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'apprco-jobs-archive';
	}

	/**
	 * Widget display title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return esc_html__( 'Apprenticeship Jobs Archive', 'apprenticeship-connect' );
	}

	/**
	 * Widget icon (Elementor icon font class).
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-posts-grid';
	}

	/**
	 * Widget category.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'apprco' );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'apprenticeship', 'jobs', 'vacancies', 'archive', 'search' );
	}

	/**
	 * Register widget controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->register_layout_section();
		$this->register_search_section();
		$this->register_filters_section();
		$this->register_sorting_section();
		$this->register_style_section();
	}

	// ── Control sections ─────────────────────────────────────────────────────

	/** Layout section. */
	private function register_layout_section(): void {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_html__( 'Layout', 'apprenticeship-connect' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => esc_html__( 'Display style', 'apprenticeship-connect' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid' => esc_html__( 'Grid', 'apprenticeship-connect' ),
					'list' => esc_html__( 'List', 'apprenticeship-connect' ),
				),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'     => esc_html__( 'Columns', 'apprenticeship-connect' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'default'   => 3,
				'min'       => 1,
				'max'       => 4,
				'condition' => array( 'layout' => 'grid' ),
			)
		);

		$this->add_control(
			'per_page',
			array(
				'label'   => esc_html__( 'Vacancies per page', 'apprenticeship-connect' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 20,
				'min'     => 4,
				'max'     => 100,
				'step'    => 4,
			)
		);

		$this->add_control(
			'show_stats',
			array(
				'label'        => esc_html__( 'Show result count', 'apprenticeship-connect' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'apprenticeship-connect' ),
				'label_off'    => esc_html__( 'Hide', 'apprenticeship-connect' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_pagination',
			array(
				'label'        => esc_html__( 'Show pagination', 'apprenticeship-connect' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'apprenticeship-connect' ),
				'label_off'    => esc_html__( 'Hide', 'apprenticeship-connect' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/** Search section. */
	private function register_search_section(): void {
		$this->start_controls_section(
			'section_search',
			array(
				'label' => esc_html__( 'Search', 'apprenticeship-connect' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_search',
			array(
				'label'        => esc_html__( 'Show keyword search', 'apprenticeship-connect' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'apprenticeship-connect' ),
				'label_off'    => esc_html__( 'Hide', 'apprenticeship-connect' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_distance_filter',
			array(
				'label'        => esc_html__( 'Show postcode / distance filter', 'apprenticeship-connect' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'apprenticeship-connect' ),
				'label_off'    => esc_html__( 'Hide', 'apprenticeship-connect' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/** Filters section. */
	private function register_filters_section(): void {
		$this->start_controls_section(
			'section_filters',
			array(
				'label' => esc_html__( 'Filters', 'apprenticeship-connect' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_filters',
			array(
				'label'        => esc_html__( 'Show level / route filters', 'apprenticeship-connect' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'apprenticeship-connect' ),
				'label_off'    => esc_html__( 'Hide', 'apprenticeship-connect' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'filter_level',
			array(
				'label'       => esc_html__( 'Default level', 'apprenticeship-connect' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Leave empty for all levels', 'apprenticeship-connect' ),
				'default'     => '',
			)
		);

		$this->add_control(
			'filter_route',
			array(
				'label'       => esc_html__( 'Default route', 'apprenticeship-connect' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Leave empty for all routes', 'apprenticeship-connect' ),
				'default'     => '',
			)
		);

		$this->end_controls_section();
	}

	/** Sorting section. */
	private function register_sorting_section(): void {
		$this->start_controls_section(
			'section_sorting',
			array(
				'label' => esc_html__( 'Default sort', 'apprenticeship-connect' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'order_by',
			array(
				'label'   => esc_html__( 'Sort by', 'apprenticeship-connect' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'closing_date',
				'options' => array(
					'closing_date'  => esc_html__( 'Closing date', 'apprenticeship-connect' ),
					'posted_date'   => esc_html__( 'Posted date', 'apprenticeship-connect' ),
					'employer_name' => esc_html__( 'Employer name', 'apprenticeship-connect' ),
					'title'         => esc_html__( 'Title', 'apprenticeship-connect' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => esc_html__( 'Order', 'apprenticeship-connect' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'ASC',
				'options' => array(
					'ASC'  => esc_html__( 'Ascending', 'apprenticeship-connect' ),
					'DESC' => esc_html__( 'Descending', 'apprenticeship-connect' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/** Style section (TAB_STYLE). */
	private function register_style_section(): void {
		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Colours', 'apprenticeship-connect' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'color_primary',
			array(
				'label'   => esc_html__( 'Primary colour', 'apprenticeship-connect' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#1d70b8',
			)
		);

		$this->end_controls_section();
	}

	// ── Render ───────────────────────────────────────────────────────────────

	/**
	 * Render the widget output on the frontend.
	 *
	 * @return void
	 */
	protected function render(): void {
		if ( ! class_exists( 'Apprco_Archive' ) ) {
			echo '<p>' . esc_html__( 'Apprenticeship Connect archive not available.', 'apprenticeship-connect' ) . '</p>';
			return;
		}

		$s = $this->get_settings_for_display();

		// Map Elementor switcher 'yes'/'' to booleans.
		$atts = array(
			'per_page'             => (int) ( $s['per_page'] ?? 20 ),
			'columns'              => (int) ( $s['columns'] ?? 3 ),
			'layout'               => sanitize_key( $s['layout'] ?? 'grid' ),
			'show_search'          => ! empty( $s['show_search'] ),
			'show_filters'         => ! empty( $s['show_filters'] ),
			'show_distance_filter' => ! empty( $s['show_distance_filter'] ),
			'show_stats'           => ! empty( $s['show_stats'] ),
			'show_pagination'      => ! empty( $s['show_pagination'] ),
			'order_by'             => sanitize_key( $s['order_by'] ?? 'closing_date' ),
			'order'                => 'DESC' === ( $s['order'] ?? 'ASC' ) ? 'DESC' : 'ASC',
			'filter_level'         => sanitize_text_field( $s['filter_level'] ?? '' ),
			'filter_route'         => sanitize_text_field( $s['filter_route'] ?? '' ),
			'color_primary'        => sanitize_hex_color( $s['color_primary'] ?? '#1d70b8' ) ?? '#1d70b8',
		);

		echo Apprco_Archive::get_instance()->render( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render a plain-text content preview for the editor (no-JS fallback).
	 *
	 * @return void
	 */
	public function render_plain_content(): void {
		echo esc_html__( '[Apprenticeship Jobs Archive]', 'apprenticeship-connect' );
	}
}
