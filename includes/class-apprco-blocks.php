<?php
/**
 * Gutenberg Blocks Manager
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class Apprco_Blocks {
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	public function register_blocks(): void {
		register_block_type( 'apprco/vacancy-list', array(
			'render_callback' => array( $this, 'render_vacancy_list' ),
			'attributes'      => array(
				'limit' => array( 'type' => 'number', 'default' => 5 ),
			),
		) );

        register_block_type( 'apprco/vacancy-search', array(
            'render_callback' => array( $this, 'render_vacancy_search' ),
        ) );
	}

	public function render_vacancy_list( $attributes ): string {
		$q = new WP_Query( array(
			'post_type'      => 'apprco_vacancy',
			'posts_per_page' => $attributes['limit'],
		) );

		if ( ! $q->have_posts() ) {
			return '<p class="apprco-no-results">' . esc_html__( 'No vacancies found.', 'apprenticeship-connect' ) . '</p>';
		}

		ob_start();
		echo '<div class="apprco-vacancy-list-block">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$ref      = get_post_meta( get_the_ID(), '_apprco_vacancy_reference', true );
			$employer = get_post_meta( get_the_ID(), '_apprco_employer_name', true );
			$wage     = get_post_meta( get_the_ID(), '_apprco_wage_amount', true );
			?>
			<div class="apprco-vacancy-card" style="border:1px solid #ddd; padding:20px; margin-bottom:15px; border-radius:8px;">
				<h3 style="margin-top:0;"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<div style="display:flex; gap:15px; font-size:13px; color:#666; margin-bottom:10px;">
					<span><strong><?php esc_html_e( 'Employer:', 'apprenticeship-connect' ); ?></strong> <?php echo esc_html( $employer ); ?></span>
					<span><strong><?php esc_html_e( 'Wage:', 'apprenticeship-connect' ); ?></strong> <?php echo esc_html( $wage ); ?></span>
					<span><strong><?php esc_html_e( 'Ref:', 'apprenticeship-connect' ); ?></strong> <?php echo esc_html( $ref ); ?></span>
				</div>
				<div><?php the_excerpt(); ?></div>
			</div>
			<?php
		}
		echo '</div>';
		wp_reset_postdata();
		return ob_get_clean();
	}

    public function render_vacancy_search(): string {
        return '<div id="apprco-search-root" class="apprco-search-block"></div>';
    }
}
