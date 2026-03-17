<?php
/**
 * Elementor widget: Vacancy Card.
 *
 * Renders a single appcon_vacancy post as a styled card.
 * Designed for use inside Elementor Loop Grid templates (Elementor Pro)
 * or as a standalone widget pointing at a specific post.
 *
 * @package ApprenticeshipConnector\Elementor
 */

namespace ApprenticeshipConnector\Elementor;

use ApprenticeshipConnector\Blocks\BlocksLoader;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class VacancyCardWidget extends Widget_Base {

	public function get_name(): string {
		return 'appcon_vacancy_card';
	}

	public function get_title(): string {
		return esc_html__( 'Vacancy Card', 'apprenticeship-connector' );
	}

	public function get_icon(): string {
		return 'eicon-posts-grid';
	}

	public function get_categories(): array {
		return [ 'general' ];
	}

	public function get_keywords(): array {
		return [ 'apprenticeship', 'vacancy', 'card', 'job', 'loop' ];
	}

	// ── Controls ───────────────────────────────────────────────────────────

	protected function register_controls(): void {

		$this->start_controls_section( 'section_content', [
			'label' => esc_html__( 'Card Fields', 'apprenticeship-connector' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'show_employer', [
			'label'        => esc_html__( 'Show employer', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_location', [
			'label'        => esc_html__( 'Show location', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_level', [
			'label'        => esc_html__( 'Show level', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_closing_date', [
			'label'        => esc_html__( 'Show closing date', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_wage', [
			'label'        => esc_html__( 'Show wage', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'show_excerpt', [
			'label'        => esc_html__( 'Show excerpt', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->add_control( 'link_to_post', [
			'label'        => esc_html__( 'Link card to post', 'apprenticeship-connector' ),
			'type'         => Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->end_controls_section();
	}

	// ── Render ─────────────────────────────────────────────────────────────

	protected function render(): void {
		$settings = $this->get_settings_for_display();

		// In a Loop Grid context, get_the_ID() returns the current loop post.
		$post_id = get_the_ID();

		$attributes = [
			'postId'         => $post_id,
			'showEmployer'   => $settings['show_employer']    === 'yes',
			'showLocation'   => $settings['show_location']    === 'yes',
			'showLevel'      => $settings['show_level']       === 'yes',
			'showClosingDate'=> $settings['show_closing_date'] === 'yes',
			'showWage'       => $settings['show_wage']        === 'yes',
			'showExcerpt'    => $settings['show_excerpt']     === 'yes',
			'linkToPost'     => $settings['link_to_post']     === 'yes',
		];

		echo BlocksLoader::render_vacancy_card( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
