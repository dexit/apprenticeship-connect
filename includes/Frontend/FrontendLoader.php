<?php
/**
 * Frontend asset enqueueing and shortcode registration.
 *
 * Responsibilities:
 *  - Enqueue build/frontend/index.js + index.css on public pages.
 *  - Localise the script with archive URL and i18n strings.
 *  - Register [appcon_search] shortcode (standalone search form).
 *  - Register [appcon_vacancies] shortcode (listing fallback without blocks).
 *
 * @package ApprenticeshipConnector\Frontend
 */

namespace ApprenticeshipConnector\Frontend;

use ApprenticeshipConnector\Blocks\BlocksLoader;

class FrontendLoader {

	/**
	 * Register all frontend hooks.  Called from Plugin::define_public_hooks().
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'init',               [ self::class, 'register_shortcodes' ] );
	}

	// ── Asset enqueueing ───────────────────────────────────────────────────

	/**
	 * Enqueue the compiled frontend bundle.
	 *
	 * The bundle is only enqueued when the compiled file actually exists so
	 * the plugin degrades gracefully if `npm run build` hasn't been run yet.
	 */
	public static function enqueue_assets(): void {
		$asset_file = APPCON_DIR . 'build/frontend/index.asset.php';
		$js_file    = APPCON_DIR . 'build/frontend/index.js';
		$css_file   = APPCON_DIR . 'build/frontend/index.css';

		if ( ! file_exists( $js_file ) ) {
			return; // Build hasn't been run yet – degrade gracefully.
		}

		$asset = file_exists( $asset_file )
			? require $asset_file
			: [ 'dependencies' => [], 'version' => APPCON_VERSION ];

		wp_enqueue_script(
			'appcon-frontend',
			APPCON_URL . 'build/frontend/index.js',
			$asset['dependencies'],
			$asset['version'],
			true // Load in footer.
		);

		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'appcon-frontend',
				APPCON_URL . 'build/frontend/index.css',
				[],
				$asset['version']
			);
		}

		// Localise with archive URL + i18n strings.
		wp_localize_script( 'appcon-frontend', 'appconFrontend', [
			'archiveUrl' => esc_url( get_post_type_archive_link( 'appcon_vacancy' ) ?: home_url( '/' ) ),
			'i18n'       => [
				'expired' => __( 'Expired', 'apprenticeship-connector' ),
				'oneDay'  => __( '1 day left', 'apprenticeship-connector' ),
				/* translators: %d: number of days remaining */
				'nDays'   => __( '%d days left', 'apprenticeship-connector' ),
			],
		] );
	}

	// ── Shortcodes ─────────────────────────────────────────────────────────

	/**
	 * Register shortcodes.
	 */
	public static function register_shortcodes(): void {
		add_shortcode( 'appcon_search',    [ self::class, 'shortcode_search' ] );
		add_shortcode( 'appcon_vacancies', [ self::class, 'shortcode_vacancies' ] );
	}

	// ── [appcon_search] ────────────────────────────────────────────────────

	/**
	 * Render a standalone vacancy search form.
	 *
	 * Usage: [appcon_search placeholder="Search apprenticeships…"]
	 *
	 * @param array  $atts Shortcode attributes.
	 * @return string HTML.
	 */
	public static function shortcode_search( array $atts ): string {
		$atts = shortcode_atts( [
			'placeholder'   => __( 'Search vacancies…', 'apprenticeship-connector' ),
			'button_label'  => __( 'Search', 'apprenticeship-connector' ),
			'archive_url'   => get_post_type_archive_link( 'appcon_vacancy' ) ?: home_url( '/' ),
		], $atts, 'appcon_search' );

		$archive_url = esc_url( $atts['archive_url'] );
		$placeholder = esc_attr( $atts['placeholder'] );
		$btn_label   = esc_html( $atts['button_label'] );
		$current_val = esc_attr( get_query_var( 'appcon_search', '' ) );

		ob_start();
		?>
		<form
			class="appcon-vacancy-search"
			method="get"
			action="<?php echo $archive_url; ?>"
			data-archive-url="<?php echo $archive_url; ?>"
			role="search"
		>
			<label class="screen-reader-text" for="appcon-sc-search">
				<?php esc_html_e( 'Search vacancies', 'apprenticeship-connector' ); ?>
			</label>
			<input
				type="search"
				id="appcon-sc-search"
				name="appcon_search"
				class="appcon-search-input"
				value="<?php echo $current_val; ?>"
				placeholder="<?php echo $placeholder; ?>"
			/>
			<button type="submit" class="appcon-search-btn"><?php echo $btn_label; ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	// ── [appcon_vacancies] ─────────────────────────────────────────────────

	/**
	 * Render a vacancy listing via shortcode.
	 *
	 * Delegates to BlocksLoader::render_vacancy_listing() so the output is
	 * identical to the Gutenberg block's server-side render.
	 *
	 * Usage: [appcon_vacancies posts_per_page="5" layout="grid" show_search="true"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML.
	 */
	public static function shortcode_vacancies( array $atts ): string {
		$atts = shortcode_atts( [
			'posts_per_page' => 10,
			'order_by'       => 'date',
			'order'          => 'DESC',
			'filter_level'   => '',
			'filter_route'   => '',
			'show_expired'   => false,
			'layout'         => 'list',
			'show_pagination'=> true,
			'show_search'    => true,
		], $atts, 'appcon_vacancies' );

		// Map snake_case shortcode atts to camelCase block attributes.
		$block_atts = [
			'postsPerPage'   => (int) $atts['posts_per_page'],
			'orderBy'        => sanitize_key( $atts['order_by'] ),
			'order'          => strtoupper( sanitize_key( $atts['order'] ) ),
			'filterLevel'    => sanitize_text_field( $atts['filter_level'] ),
			'filterRoute'    => sanitize_text_field( $atts['filter_route'] ),
			'showExpired'    => filter_var( $atts['show_expired'],    FILTER_VALIDATE_BOOLEAN ),
			'layout'         => sanitize_key( $atts['layout'] ),
			'showPagination' => filter_var( $atts['show_pagination'], FILTER_VALIDATE_BOOLEAN ),
			'showSearch'     => filter_var( $atts['show_search'],     FILTER_VALIDATE_BOOLEAN ),
		];

		return BlocksLoader::render_vacancy_listing( $block_atts );
	}
}
