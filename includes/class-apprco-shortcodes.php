<?php
/**
 * Shortcodes class for templating tags and vacancy display
 *
 * @package ApprenticeshipConnect
 * @version 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Shortcodes
 *
 * Handles all shortcodes and templating tags.
 */
class Apprco_Shortcodes {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Shortcodes|null
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
		add_shortcode( 'apprco_search', array( $this, 'render_search' ) );
		add_shortcode( 'apprco_vacancy', array( $this, 'render_vacancy' ) );
	}

	/**
	 * Renders the search root div.
	 *
	 * @return string
	 */
	public function render_search(): string {
		return '<div id="apprco-search-root"></div>';
	}

	/**
	 * Renders a single vacancy card.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_vacancy( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts
		);

		$post = get_post( $atts['id'] );
		if ( ! $post || 'apprco_vacancy' !== $post->post_type ) {
			return '';
		}

		ob_start();
		?>
		<div class="apprco-vacancy-card">
			<h3><?php echo esc_html( get_the_title( $post ) ); ?></h3>
			<div class="meta">
				<span><?php echo esc_html( get_post_meta( $post->ID, '_apprco_employer_name', true ) ); ?></span>
				<span><?php echo esc_html( get_post_meta( $post->ID, '_apprco_postcode', true ) ); ?></span>
			</div>
			<div class="content"><?php echo esc_html( get_the_excerpt( $post ) ); ?></div>
			<a href="<?php echo esc_url( get_post_meta( $post->ID, '_apprco_vacancy_url', true ) ); ?>" target="_blank" class="button"><?php esc_html_e( 'Apply Now', 'apprenticeship-connect' ); ?></a>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
