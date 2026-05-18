<?php
/**
 * Frontend Archive & Search Class
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Archive
 *
 * Provides self-hosted vacancy archive with shortcodes and search.
 */
class Apprco_Archive {

	/**
	 * Singleton instance.
	 *
	 * @var Apprco_Archive|null
	 */
	private static $instance = null;

	/**
	 * Whether archive CSS has been output.
	 *
	 * @var bool
	 */
	private static $css_printed = false;

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
		add_shortcode( 'apprco_jobs', array( $this, 'render_archive' ) );
		add_shortcode( 'apprco_job_search', array( $this, 'render_search_form_only' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_styles' ) );
	}

	/**
	 * Enqueue styles when on a page that may use the archive shortcodes.
	 *
	 * @return void
	 */
	public function maybe_enqueue_styles(): void {
		// We add styles inline when rendering to avoid a separate HTTP request.
	}

	/**
	 * Render the full jobs archive shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Inner content (unused).
	 * @return string HTML output.
	 */
	public function render_archive( $atts = array(), $content = '' ): string {
		$atts = shortcode_atts(
			array(
				'per_page'            => 20,
				'columns'             => 3,
				'show_search'         => true,
				'show_filters'        => true,
				'show_distance_filter'=> true,
				'show_stats'          => true,
				'show_pagination'     => true,
				'order_by'            => 'closing_date',
				'order'               => 'ASC',
				'filter_level'        => '',
				'filter_route'        => '',
				'color_primary'       => '#1d70b8',
				'layout'              => 'grid',
			),
			$atts,
			'apprco_jobs'
		);
		return $this->render( $atts );
	}

	/**
	 * Unified render method — called by shortcode, Gutenberg block, and Elementor widget.
	 *
	 * Accepts a flat atts array (camelCase from block attributes OR snake_case
	 * from shortcode/Elementor — both are normalised internally).
	 *
	 * @param array $atts Render options (see render_archive defaults for keys).
	 * @return string HTML output.
	 */
	public function render( array $atts = array() ): string {
		// Normalise camelCase block attribute names to snake_case.
		$map = array(
			'perPage'           => 'per_page',
			'showSearch'        => 'show_search',
			'showFilters'       => 'show_filters',
			'showDistanceFilter'=> 'show_distance_filter',
			'showStats'         => 'show_stats',
			'showPagination'    => 'show_pagination',
			'orderBy'           => 'order_by',
			'filterLevel'       => 'filter_level',
			'filterRoute'       => 'filter_route',
			'colorPrimary'      => 'color_primary',
		);
		foreach ( $map as $camel => $snake ) {
			if ( isset( $atts[ $camel ] ) && ! isset( $atts[ $snake ] ) ) {
				$atts[ $snake ] = $atts[ $camel ];
			}
		}

		$defaults = array(
			'per_page'             => 20,
			'columns'              => 3,
			'show_search'          => true,
			'show_filters'         => true,
			'show_distance_filter' => true,
			'show_stats'           => true,
			'show_pagination'      => true,
			'order_by'             => 'closing_date',
			'order'                => 'ASC',
			'filter_level'         => '',
			'filter_route'         => '',
			'color_primary'        => '#1d70b8',
			'layout'               => 'grid',
		);
		$atts = wp_parse_args( $atts, $defaults );

		$args = $this->parse_query_args( (int) $atts['per_page'] );

		// Apply default level/route from block/widget config (URL params take precedence).
		if ( empty( $args['level'] ) && ! empty( $atts['filter_level'] ) ) {
			$args['level'] = sanitize_text_field( $atts['filter_level'] );
		}
		if ( empty( $args['route'] ) && ! empty( $atts['filter_route'] ) ) {
			$args['route'] = sanitize_text_field( $atts['filter_route'] );
		}
		$args['order_by'] = sanitize_key( $atts['order_by'] );
		$args['order']    = 'DESC' === strtoupper( $atts['order'] ) ? 'DESC' : 'ASC';

		// Geocode postcode for distance search.
		if ( ! empty( $args['postcode'] ) ) {
			$geo = Apprco_Geocoder::forward( $args['postcode'] );
			if ( $geo ) {
				$args['search_lat'] = $geo['lat'];
				$args['search_lng'] = $geo['lng'];
			}
		}

		$store   = Apprco_Vacancy_Store::get_instance();
		$results = $store->search( $args );
		$filters = $store->get_filters();

		ob_start();
		$this->print_styles( $atts['color_primary'] );
		if ( (bool) $atts['show_search'] || (bool) $atts['show_filters'] ) {
			$this->render_search_form( $args, $filters, '', $atts );
		}
		$this->render_results( $results, $args, $atts );
		return ob_get_clean();
	}

	/**
	 * Render just the search form shortcode (for use on non-archive pages).
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Inner content (unused).
	 * @return string HTML output.
	 */
	public function render_search_form_only( $atts = array(), $content = '' ): string {
		$atts = shortcode_atts(
			array(
				'action' => '',
			),
			$atts,
			'apprco_job_search'
		);

		$store   = Apprco_Vacancy_Store::get_instance();
		$filters = $store->get_filters();
		$args    = $this->parse_query_args();

		// Determine where the form should submit.
		$action = '';
		if ( ! empty( $atts['action'] ) ) {
			$action = esc_url( $atts['action'] );
		} else {
			$page_id = (int) Apprco_Settings_Manager::get_instance()->get( 'archive', 'jobs_archive_page_id', 0 );
			if ( $page_id ) {
				$action = esc_url( get_permalink( $page_id ) );
			}
		}

		ob_start();
		$this->print_styles();
		$this->render_search_form( $args, $filters, $action );
		return ob_get_clean();
	}

	/**
	 * Parse request query args for vacancy search.
	 *
	 * @param int $per_page Results per page.
	 * @return array
	 */
	private function parse_query_args( int $per_page = 20 ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return array(
			'keyword'        => isset( $_GET['apprco_q'] ) ? sanitize_text_field( wp_unslash( $_GET['apprco_q'] ) ) : '',
			'postcode'       => isset( $_GET['apprco_postcode'] ) ? sanitize_text_field( wp_unslash( $_GET['apprco_postcode'] ) ) : '',
			'distance_miles' => isset( $_GET['apprco_distance'] ) ? (int) $_GET['apprco_distance'] : 10,
			'level'          => isset( $_GET['apprco_level'] ) ? sanitize_text_field( wp_unslash( $_GET['apprco_level'] ) ) : '',
			'route'          => isset( $_GET['apprco_route'] ) ? sanitize_text_field( wp_unslash( $_GET['apprco_route'] ) ) : '',
			'page'           => isset( $_GET['apprco_page'] ) ? max( 1, (int) $_GET['apprco_page'] ) : 1,
			'per_page'       => $per_page,
			'order_by'       => isset( $_GET['apprco_order_by'] ) ? sanitize_text_field( wp_unslash( $_GET['apprco_order_by'] ) ) : 'closing_date',
			'order'          => 'ASC',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Render the search form.
	 *
	 * @param array  $args    Current search args.
	 * @param array  $filters Available filter options.
	 * @param string $action  Form action URL (empty for current page).
	 * @return void
	 */
	private function render_search_form( array $args, array $filters, string $action = '', array $atts = array() ): void {
		$action_attr = $action ? ' action="' . esc_url( $action ) . '"' : '';
		?>
		<div class="apprco-archive">
		<form class="apprco-search-form" method="get"<?php echo $action_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="apprco-search-row">
				<div class="apprco-search-field apprco-search-field--keyword">
					<label class="apprco-search-label" for="apprco_q"><?php esc_html_e( 'Keyword', 'apprenticeship-connect' ); ?></label>
					<input
						type="search"
						id="apprco_q"
						name="apprco_q"
						class="apprco-search-input"
						placeholder="<?php esc_attr_e( 'Job title or keyword', 'apprenticeship-connect' ); ?>"
						value="<?php echo esc_attr( $args['keyword'] ); ?>"
					>
				</div>
				<div class="apprco-search-field apprco-search-field--postcode">
					<label class="apprco-search-label" for="apprco_postcode"><?php esc_html_e( 'Postcode', 'apprenticeship-connect' ); ?></label>
					<input
						type="text"
						id="apprco_postcode"
						name="apprco_postcode"
						class="apprco-search-input"
						placeholder="<?php esc_attr_e( 'e.g. SW1A 1AA', 'apprenticeship-connect' ); ?>"
						value="<?php echo esc_attr( $args['postcode'] ); ?>"
					>
				</div>
				<div class="apprco-search-field apprco-search-field--distance">
					<label class="apprco-search-label" for="apprco_distance"><?php esc_html_e( 'Distance', 'apprenticeship-connect' ); ?></label>
					<select id="apprco_distance" name="apprco_distance" class="apprco-search-select">
						<?php
						$distances = array( 1, 5, 10, 25, 50 );
						foreach ( $distances as $d ) {
							printf(
								'<option value="%d"%s>%d %s</option>',
								$d,
								selected( $args['distance_miles'], $d, false ),
								$d,
								esc_html__( 'miles', 'apprenticeship-connect' )
							);
						}
						?>
					</select>
				</div>
				<div class="apprco-search-field apprco-search-field--level">
					<label class="apprco-search-label" for="apprco_level"><?php esc_html_e( 'Level', 'apprenticeship-connect' ); ?></label>
					<select id="apprco_level" name="apprco_level" class="apprco-search-select">
						<option value=""><?php esc_html_e( 'All levels', 'apprenticeship-connect' ); ?></option>
						<?php foreach ( $filters['levels'] as $level ) : ?>
							<option value="<?php echo esc_attr( $level ); ?>"<?php selected( $args['level'], $level ); ?>><?php echo esc_html( $level ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="apprco-search-field apprco-search-field--route">
					<label class="apprco-search-label" for="apprco_route"><?php esc_html_e( 'Route', 'apprenticeship-connect' ); ?></label>
					<select id="apprco_route" name="apprco_route" class="apprco-search-select">
						<option value=""><?php esc_html_e( 'All routes', 'apprenticeship-connect' ); ?></option>
						<?php foreach ( $filters['routes'] as $route ) : ?>
							<option value="<?php echo esc_attr( $route ); ?>"<?php selected( $args['route'], $route ); ?>><?php echo esc_html( $route ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="apprco-search-field apprco-search-field--submit">
					<label class="apprco-search-label apprco-search-label--hidden"><?php esc_html_e( 'Search', 'apprenticeship-connect' ); ?></label>
					<button type="submit" class="apprco-search-btn"><?php esc_html_e( 'Search', 'apprenticeship-connect' ); ?></button>
				</div>
			</div>
			<?php $this->render_active_filters( $args ); ?>
		</form>
		<?php
	}

	/**
	 * Render active filter pills.
	 *
	 * @param array $args Current search args.
	 * @return void
	 */
	private function render_active_filters( array $args ): void {
		$active = array();

		if ( ! empty( $args['keyword'] ) ) {
			$active[] = array( 'label' => esc_html__( 'Keyword', 'apprenticeship-connect' ) . ': ' . esc_html( $args['keyword'] ), 'param' => 'apprco_q' );
		}
		if ( ! empty( $args['postcode'] ) ) {
			$active[] = array( 'label' => esc_html( $args['postcode'] ) . ' (' . $args['distance_miles'] . 'mi)', 'param' => 'apprco_postcode' );
		}
		if ( ! empty( $args['level'] ) ) {
			$active[] = array( 'label' => esc_html__( 'Level', 'apprenticeship-connect' ) . ': ' . esc_html( $args['level'] ), 'param' => 'apprco_level' );
		}
		if ( ! empty( $args['route'] ) ) {
			$active[] = array( 'label' => esc_html__( 'Route', 'apprenticeship-connect' ) . ': ' . esc_html( $args['route'] ), 'param' => 'apprco_route' );
		}

		if ( empty( $active ) ) {
			return;
		}
		?>
		<div class="apprco-active-filters">
			<span class="apprco-active-filters__label"><?php esc_html_e( 'Active filters:', 'apprenticeship-connect' ); ?></span>
			<?php foreach ( $active as $filter ) : ?>
				<span class="apprco-active-filters__pill">
					<?php echo esc_html( $filter['label'] ); ?>
					<a href="<?php echo esc_url( $this->remove_query_arg( $filter['param'] ) ); ?>" class="apprco-active-filters__remove" aria-label="<?php esc_attr_e( 'Remove filter', 'apprenticeship-connect' ); ?>">&#215;</a>
				</span>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render search results grid + stats + pagination.
	 *
	 * @param array $results Search results from store.
	 * @param array $args    Current search args.
	 * @return void
	 */
	private function render_results( array $results, array $args, array $atts = array() ): void {
		$total    = $results['total'];
		$items    = $results['items'];
		$pages    = $results['pages'];
		$page     = $args['page'];
		$per_page = $args['per_page'];

		$from = $total > 0 ? ( ( $page - 1 ) * $per_page ) + 1 : 0;
		$to   = min( $page * $per_page, $total );
		?>
		<div class="apprco-results">
			<div class="apprco-results__stats">
				<?php if ( $total > 0 ) : ?>
					<?php
					printf(
						/* translators: 1: first result number, 2: last result number, 3: total results */
						esc_html__( 'Showing %1$d\u{2013}%2$d of %3$d apprenticeships', 'apprenticeship-connect' ),
						$from,
						$to,
						$total
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'No apprenticeships found matching your search.', 'apprenticeship-connect' ); ?>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $items ) ) : ?>
				<div class="apprco-grid">
					<?php foreach ( $items as $vacancy ) : ?>
						<?php $this->render_card( $vacancy ); ?>
					<?php endforeach; ?>
				</div>
				<?php $this->render_pagination( $page, $pages ); ?>
			<?php else : ?>
				<div class="apprco-no-results">
					<p><?php esc_html_e( 'Try broadening your search or removing some filters.', 'apprenticeship-connect' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		</div><!-- .apprco-archive -->
		<?php
	}

	/**
	 * Render a single vacancy card.
	 *
	 * @param array $vacancy Vacancy data array.
	 * @return void
	 */
	private function render_card( array $vacancy ): void {
		$title          = isset( $vacancy['title'] ) ? $vacancy['title'] : '';
		$employer       = isset( $vacancy['employer_name'] ) ? $vacancy['employer_name'] : '';
		$postcode       = isset( $vacancy['postcode'] ) ? $vacancy['postcode'] : '';
		$town           = isset( $vacancy['town'] ) ? $vacancy['town'] : '';
		$level          = isset( $vacancy['apprenticeship_level'] ) ? $vacancy['apprenticeship_level'] : '';
		$route          = isset( $vacancy['route'] ) ? $vacancy['route'] : '';
		$wage           = isset( $vacancy['wage_text'] ) ? $vacancy['wage_text'] : '';
		$closing        = isset( $vacancy['closing_date'] ) ? $vacancy['closing_date'] : '';
		$url            = isset( $vacancy['vacancy_url'] ) ? $vacancy['vacancy_url'] : '';
		$positions      = isset( $vacancy['number_of_positions'] ) ? (int) $vacancy['number_of_positions'] : 1;

		$location_parts = array_filter( array( $town, $postcode ) );
		$location       = implode( ', ', $location_parts );

		$closing_class = 'apprco-card__closing';
		$closing_label = '';
		if ( $closing ) {
			$closing_ts = strtotime( $closing );
			$days_left  = $closing_ts ? (int) round( ( $closing_ts - time() ) / DAY_IN_SECONDS ) : 999;
			if ( $days_left < 7 && $days_left >= 0 ) {
				$closing_class .= ' apprco-card__closing--urgent';
			}
			$closing_label = date_i18n( get_option( 'date_format', 'd M Y' ), $closing_ts ? $closing_ts : 0 );
		}
		?>
		<article class="apprco-card">
			<div class="apprco-card__header">
				<h3 class="apprco-card__title">
					<?php if ( $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="apprco-card__title-link">
							<?php echo esc_html( $title ); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</h3>
				<p class="apprco-card__employer"><?php echo esc_html( $employer ); ?></p>
			</div>

			<div class="apprco-card__meta">
				<?php if ( $location ) : ?>
					<span class="apprco-card__location">
						<svg class="apprco-icon" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
						<?php echo esc_html( $location ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $positions > 1 ) : ?>
					<span class="apprco-card__positions">
						<?php
						printf(
							/* translators: %d: number of positions */
							esc_html( _n( '%d position', '%d positions', $positions, 'apprenticeship-connect' ) ),
							$positions
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<div class="apprco-card__badges">
				<?php if ( $level ) : ?>
					<span class="apprco-badge apprco-badge--level apprco-badge--level-<?php echo esc_attr( sanitize_title( $level ) ); ?>">
						<?php echo esc_html( $level ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $route ) : ?>
					<span class="apprco-badge apprco-badge--route">
						<?php echo esc_html( $route ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="apprco-card__footer">
				<?php if ( $wage ) : ?>
					<span class="apprco-card__wage">
						<strong><?php esc_html_e( 'Wage:', 'apprenticeship-connect' ); ?></strong>
						<?php echo esc_html( $wage ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $closing_label ) : ?>
					<span class="<?php echo esc_attr( $closing_class ); ?>">
						<strong><?php esc_html_e( 'Closes:', 'apprenticeship-connect' ); ?></strong>
						<?php echo esc_html( $closing_label ); ?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( $url ) : ?>
				<div class="apprco-card__actions">
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="apprco-btn apprco-btn--primary">
						<?php esc_html_e( 'View Details', 'apprenticeship-connect' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</article>
		<?php
	}

	/**
	 * Render pagination controls.
	 *
	 * @param int $current_page Current page number.
	 * @param int $total_pages  Total number of pages.
	 * @return void
	 */
	private function render_pagination( int $current_page, int $total_pages ): void {
		if ( $total_pages <= 1 ) {
			return;
		}
		?>
		<nav class="apprco-pagination" aria-label="<?php esc_attr_e( 'Vacancy pagination', 'apprenticeship-connect' ); ?>">
			<?php if ( $current_page > 1 ) : ?>
				<a href="<?php echo esc_url( $this->page_url( $current_page - 1 ) ); ?>" class="apprco-pagination__btn apprco-pagination__btn--prev">
					&laquo; <?php esc_html_e( 'Previous', 'apprenticeship-connect' ); ?>
				</a>
			<?php endif; ?>

			<span class="apprco-pagination__pages">
				<?php
				$range = 2;
				for ( $i = 1; $i <= $total_pages; $i++ ) {
					if ( $i === 1 || $i === $total_pages || ( $i >= $current_page - $range && $i <= $current_page + $range ) ) {
						if ( $i === $current_page ) {
							printf( '<span class="apprco-pagination__page apprco-pagination__page--current">%d</span>', $i );
						} else {
							printf(
								'<a href="%s" class="apprco-pagination__page">%d</a>',
								esc_url( $this->page_url( $i ) ),
								$i
							);
						}
					} elseif ( $i === $current_page - $range - 1 || $i === $current_page + $range + 1 ) {
						echo '<span class="apprco-pagination__ellipsis">&hellip;</span>';
					}
				}
				?>
			</span>

			<?php if ( $current_page < $total_pages ) : ?>
				<a href="<?php echo esc_url( $this->page_url( $current_page + 1 ) ); ?>" class="apprco-pagination__btn apprco-pagination__btn--next">
					<?php esc_html_e( 'Next', 'apprenticeship-connect' ); ?> &raquo;
				</a>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * Build a URL for a given page number preserving current query string.
	 *
	 * @param int $page Page number.
	 * @return string URL.
	 */
	private function page_url( int $page ): string {
		return add_query_arg( 'apprco_page', $page );
	}

	/**
	 * Build a URL with a specific query param removed.
	 *
	 * @param string $param Parameter to remove.
	 * @return string URL.
	 */
	private function remove_query_arg( string $param ): string {
		return remove_query_arg( $param );
	}

	/**
	 * Geocode a UK postcode to lat/lng using the postcodes.io API.
	 *
	 * @param string $postcode Postcode string.
	 * @return array|null { lat: float, lng: float } or null on failure.
	 */
	private function geocode_postcode( string $postcode ): ?array {
		$postcode = preg_replace( '/\s+/', '', strtoupper( trim( $postcode ) ) );
		if ( empty( $postcode ) ) {
			return null;
		}

		$cache_key = 'apprco_geo_' . md5( $postcode );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.postcodes.io/postcodes/' . rawurlencode( $postcode ),
			array( 'timeout' => 5 )
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['result']['latitude'] ) || empty( $body['result']['longitude'] ) ) {
			return null;
		}

		$geo = array(
			'lat' => (float) $body['result']['latitude'],
			'lng' => (float) $body['result']['longitude'],
		);

		set_transient( $cache_key, $geo, 7 * DAY_IN_SECONDS );

		return $geo;
	}

	/**
	 * Print the archive CSS styles (once per page load).
	 *
	 * @return void
	 */
	private function print_styles( string $color_primary = '#1d70b8' ): void {
		if ( self::$css_printed ) {
			return;
		}
		self::$css_printed = true;
		// Ensure color is a valid hex value before injecting into CSS.
		if ( ! preg_match( '/^#[0-9a-fA-F]{3,6}$/', $color_primary ) ) {
			$color_primary = '#1d70b8';
		}
		?>
		<style id="apprco-archive-styles">
		/* =====================================================================
		   Apprenticeship Connect - Archive Styles
		   ===================================================================== */

		:root {
			--apprco-primary: <?php echo esc_attr( $color_primary ); ?>;
			--apprco-primary-dark: #144e87;
			--apprco-primary-light: #e8f1fb;
			--apprco-text: #0b0c0c;
			--apprco-text-secondary: #505a5f;
			--apprco-border: #b1b4b6;
			--apprco-bg: #f3f2f1;
			--apprco-white: #ffffff;
			--apprco-error: #d4351c;
			--apprco-success: #00703c;
			--apprco-warning: #f47738;
			--apprco-radius: 4px;
			--apprco-shadow: 0 2px 8px rgba(0,0,0,0.08);
		}

		/* Archive wrapper */
		.apprco-archive {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			color: var(--apprco-text);
			max-width: 1200px;
			margin: 0 auto;
			padding: 0 1rem;
		}

		/* ---- Search Form ---- */
		.apprco-search-form {
			background: var(--apprco-bg);
			border: 1px solid var(--apprco-border);
			border-radius: var(--apprco-radius);
			padding: 1.25rem 1.5rem;
			margin-bottom: 1.5rem;
		}

		.apprco-search-row {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
			align-items: flex-end;
		}

		.apprco-search-field {
			display: flex;
			flex-direction: column;
			flex: 1 1 160px;
			min-width: 130px;
		}

		.apprco-search-field--keyword {
			flex: 2 1 220px;
		}

		.apprco-search-field--submit {
			flex: 0 0 auto;
		}

		.apprco-search-label {
			font-size: 0.8rem;
			font-weight: 600;
			color: var(--apprco-text-secondary);
			margin-bottom: 0.25rem;
			text-transform: uppercase;
			letter-spacing: 0.03em;
		}

		.apprco-search-label--hidden {
			visibility: hidden;
		}

		.apprco-search-input,
		.apprco-search-select {
			height: 40px;
			padding: 0 0.75rem;
			border: 2px solid var(--apprco-border);
			border-radius: var(--apprco-radius);
			font-size: 1rem;
			color: var(--apprco-text);
			background: var(--apprco-white);
			transition: border-color 0.15s;
			width: 100%;
			box-sizing: border-box;
		}

		.apprco-search-input:focus,
		.apprco-search-select:focus {
			outline: 3px solid #ffdd00;
			outline-offset: 0;
			border-color: var(--apprco-primary);
		}

		.apprco-search-btn {
			height: 40px;
			padding: 0 1.25rem;
			background: var(--apprco-primary);
			color: var(--apprco-white);
			border: none;
			border-radius: var(--apprco-radius);
			font-size: 1rem;
			font-weight: 600;
			cursor: pointer;
			transition: background 0.15s;
			white-space: nowrap;
		}

		.apprco-search-btn:hover,
		.apprco-search-btn:focus {
			background: var(--apprco-primary-dark);
			outline: 3px solid #ffdd00;
			outline-offset: 0;
		}

		/* Active filters */
		.apprco-active-filters {
			display: flex;
			flex-wrap: wrap;
			gap: 0.5rem;
			align-items: center;
			margin-top: 0.75rem;
			font-size: 0.875rem;
		}

		.apprco-active-filters__label {
			color: var(--apprco-text-secondary);
			font-weight: 600;
		}

		.apprco-active-filters__pill {
			display: inline-flex;
			align-items: center;
			gap: 0.35rem;
			background: var(--apprco-primary-light);
			color: var(--apprco-primary-dark);
			border: 1px solid var(--apprco-primary);
			border-radius: 100px;
			padding: 0.2rem 0.6rem;
		}

		.apprco-active-filters__remove {
			color: var(--apprco-primary-dark);
			text-decoration: none;
			font-size: 1.1rem;
			line-height: 1;
		}

		.apprco-active-filters__remove:hover {
			color: var(--apprco-error);
		}

		/* ---- Stats bar ---- */
		.apprco-results__stats {
			font-size: 0.9rem;
			color: var(--apprco-text-secondary);
			margin-bottom: 1rem;
		}

		/* ---- Grid ---- */
		.apprco-grid {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 1.25rem;
		}

		@media (max-width: 900px) {
			.apprco-grid {
				grid-template-columns: repeat(2, 1fr);
			}
		}

		@media (max-width: 560px) {
			.apprco-grid {
				grid-template-columns: 1fr;
			}

			.apprco-search-row {
				flex-direction: column;
			}

			.apprco-search-field {
				flex: 1 1 100%;
			}
		}

		/* ---- Card ---- */
		.apprco-card {
			background: var(--apprco-white);
			border: 1px solid var(--apprco-border);
			border-radius: var(--apprco-radius);
			box-shadow: var(--apprco-shadow);
			padding: 1.25rem;
			display: flex;
			flex-direction: column;
			gap: 0.75rem;
			transition: box-shadow 0.15s, transform 0.15s;
		}

		.apprco-card:hover {
			box-shadow: 0 4px 16px rgba(0,0,0,0.12);
			transform: translateY(-1px);
		}

		.apprco-card__header {
			border-bottom: 1px solid #e8e8e8;
			padding-bottom: 0.75rem;
		}

		.apprco-card__title {
			font-size: 1rem;
			font-weight: 700;
			margin: 0 0 0.3rem;
			line-height: 1.3;
		}

		.apprco-card__title-link {
			color: var(--apprco-primary);
			text-decoration: none;
		}

		.apprco-card__title-link:hover {
			text-decoration: underline;
			color: var(--apprco-primary-dark);
		}

		.apprco-card__employer {
			font-size: 0.9rem;
			color: var(--apprco-text-secondary);
			margin: 0;
		}

		.apprco-card__meta {
			display: flex;
			flex-wrap: wrap;
			gap: 0.5rem;
			font-size: 0.85rem;
			color: var(--apprco-text-secondary);
		}

		.apprco-card__location,
		.apprco-card__positions {
			display: inline-flex;
			align-items: center;
			gap: 0.25rem;
		}

		.apprco-icon {
			flex-shrink: 0;
			vertical-align: middle;
		}

		/* ---- Badges ---- */
		.apprco-card__badges {
			display: flex;
			flex-wrap: wrap;
			gap: 0.4rem;
		}

		.apprco-badge {
			display: inline-block;
			font-size: 0.75rem;
			font-weight: 600;
			padding: 0.15rem 0.5rem;
			border-radius: 100px;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		/* Level badges by type */
		.apprco-badge--level { background: #d1e8ff; color: #003b6f; }
		.apprco-badge--level-degree-apprenticeship { background: #c8f5e0; color: #004e2a; }
		.apprco-badge--level-higher-apprenticeship { background: #d1e8ff; color: #003b6f; }
		.apprco-badge--level-advanced-apprenticeship { background: #fce8b3; color: #5c3b00; }
		.apprco-badge--level-intermediate-apprenticeship { background: #ffe0d2; color: #5a1800; }
		.apprco-badge--route { background: #f0edff; color: #3a0077; }

		/* ---- Card footer ---- */
		.apprco-card__footer {
			display: flex;
			flex-direction: column;
			gap: 0.3rem;
			font-size: 0.875rem;
			margin-top: auto;
		}

		.apprco-card__wage,
		.apprco-card__closing {
			display: block;
			color: var(--apprco-text-secondary);
		}

		.apprco-card__closing--urgent {
			color: var(--apprco-error);
			font-weight: 600;
		}

		/* ---- Actions ---- */
		.apprco-card__actions {
			margin-top: 0.5rem;
		}

		.apprco-btn {
			display: inline-block;
			padding: 0.5rem 1rem;
			border-radius: var(--apprco-radius);
			font-size: 0.9rem;
			font-weight: 600;
			text-decoration: none;
			transition: background 0.15s, color 0.15s;
		}

		.apprco-btn--primary {
			background: var(--apprco-primary);
			color: var(--apprco-white);
		}

		.apprco-btn--primary:hover {
			background: var(--apprco-primary-dark);
			color: var(--apprco-white);
		}

		/* ---- No results ---- */
		.apprco-no-results {
			text-align: center;
			padding: 3rem 1rem;
			color: var(--apprco-text-secondary);
		}

		/* ---- Pagination ---- */
		.apprco-pagination {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 0.5rem;
			margin-top: 2rem;
			flex-wrap: wrap;
		}

		.apprco-pagination__btn,
		.apprco-pagination__page {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 36px;
			height: 36px;
			padding: 0 0.5rem;
			border: 2px solid var(--apprco-border);
			border-radius: var(--apprco-radius);
			color: var(--apprco-primary);
			text-decoration: none;
			font-size: 0.9rem;
			font-weight: 600;
			transition: background 0.15s, border-color 0.15s;
		}

		.apprco-pagination__btn:hover,
		.apprco-pagination__page:hover {
			background: var(--apprco-primary-light);
			border-color: var(--apprco-primary);
		}

		.apprco-pagination__page--current {
			background: var(--apprco-primary);
			color: var(--apprco-white);
			border-color: var(--apprco-primary);
			cursor: default;
		}

		.apprco-pagination__ellipsis {
			color: var(--apprco-text-secondary);
			line-height: 36px;
		}
		</style>
		<?php
	}
}
