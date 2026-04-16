<?php
/**
 * Gutenberg block registration and server-side render callbacks.
 *
 * Registers two blocks:
 *  - apprenticeship-connector/vacancy-listing  – filterable vacancy list
 *  - apprenticeship-connector/vacancy-card     – single vacancy card
 *
 * Both blocks are server-side rendered; the JS side handles the editor UI
 * via ServerSideRender.
 *
 * @package ApprenticeshipConnector\Blocks
 */

namespace ApprenticeshipConnector\Blocks;

use ApprenticeshipConnector\Core\Settings;

class BlocksLoader {

	/**
	 * Register all blocks.  Hooked to init.
	 */
	public static function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // WordPress < 5.0 (should never happen given requirements).
		}

		// Build directory for compiled JS assets.
		$build = APPCON_DIR . 'build/blocks/';

		register_block_type(
			$build . 'vacancy-listing',
			[ 'render_callback' => [ self::class, 'render_vacancy_listing' ] ]
		);

		register_block_type(
			$build . 'vacancy-card',
			[ 'render_callback' => [ self::class, 'render_vacancy_card' ] ]
		);
	}

	// ── Render: Vacancy Listing ────────────────────────────────────────────

	/**
	 * Server-side render callback for apprenticeship-connector/vacancy-listing.
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public static function render_vacancy_listing( array $attributes ): string {
		$posts_per_page  = (int) ( $attributes['postsPerPage']  ?? Settings::get( 'vacancies_per_page', 10 ) );
		$order_by        = sanitize_key( $attributes['orderBy']     ?? 'date' );
		$order           = in_array( strtoupper( $attributes['order'] ?? 'DESC' ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $attributes['order'] ) : 'DESC';
		$filter_level    = sanitize_text_field( $attributes['filterLevel']  ?? '' );
		$filter_route    = sanitize_text_field( $attributes['filterRoute']  ?? '' );
		$show_expired    = ! empty( $attributes['showExpired'] );
		$layout          = sanitize_key( $attributes['layout']      ?? 'list' );
		$show_pagination = ! empty( $attributes['showPagination'] );
		$show_search     = ! empty( $attributes['showSearch'] );

		$paged = max( 1, (int) ( get_query_var( 'paged' ) ?: get_query_var( 'page' ) ) );

		$query_args = [
			'post_type'      => 'appcon_vacancy',
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'orderby'        => $order_by === 'closing_date' ? 'meta_value' : $order_by,
			'order'          => $order,
		];

		if ( $order_by === 'closing_date' ) {
			$query_args['meta_key'] = '_appcon_closing_date';
			$query_args['meta_type'] = 'DATE';
		}

		// Exclude expired if requested.
		if ( ! $show_expired ) {
			$query_args['meta_query'] = [ [
				'relation' => 'OR',
				[ 'key' => '_appcon_expired', 'compare' => 'NOT EXISTS' ],
				[ 'key' => '_appcon_expired', 'value' => '1', 'compare' => '!=' ],
			] ];
		}

		// Taxonomy filters.
		$tax_query = [];
		if ( $filter_level ) {
			$tax_query[] = [ 'taxonomy' => 'appcon_level', 'field' => 'term_id', 'terms' => (int) $filter_level ];
		}
		if ( $filter_route ) {
			$tax_query[] = [ 'taxonomy' => 'appcon_route', 'field' => 'term_id', 'terms' => (int) $filter_route ];
		}
		if ( ! empty( $tax_query ) ) {
			$tax_query['relation']      = 'AND';
			$query_args['tax_query']    = $tax_query;
		}

		$query = new \WP_Query( $query_args );

		ob_start();
		?>
		<div class="appcon-vacancy-listing appcon-layout-<?php echo esc_attr( $layout ); ?>">

			<?php if ( $show_search ) : ?>
			<form method="get" class="appcon-vacancy-search" role="search">
				<label class="screen-reader-text" for="appcon-search-<?php echo esc_attr( wp_unique_id() ); ?>">
					<?php esc_html_e( 'Search vacancies', 'apprenticeship-connector' ); ?>
				</label>
				<input
					type="search"
					id="appcon-search-<?php echo esc_attr( wp_unique_id() ); ?>"
					name="appcon_search"
					class="appcon-search-input"
					value="<?php echo esc_attr( get_query_var( 'appcon_search', '' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Search vacancies…', 'apprenticeship-connector' ); ?>"
				/>
				<button type="submit" class="appcon-search-btn">
					<?php esc_html_e( 'Search', 'apprenticeship-connector' ); ?>
				</button>
			</form>
			<?php endif; ?>

			<?php if ( $query->have_posts() ) : ?>
				<?php if ( $layout === 'table' ) : ?>
				<table class="appcon-vacancies-table widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title',          'apprenticeship-connector' ); ?></th>
							<th><?php esc_html_e( 'Employer',       'apprenticeship-connector' ); ?></th>
							<th><?php esc_html_e( 'Location',       'apprenticeship-connector' ); ?></th>
							<th><?php esc_html_e( 'Level',          'apprenticeship-connector' ); ?></th>
							<th><?php esc_html_e( 'Closing Date',   'apprenticeship-connector' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<tr>
							<td><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></td>
							<td><?php echo esc_html( get_post_meta( get_the_ID(), '_appcon_employer_name', true ) ); ?></td>
							<td><?php echo esc_html( get_post_meta( get_the_ID(), '_appcon_location_address_full', true ) ); ?></td>
							<td><?php echo esc_html( implode( ', ', wp_list_pluck( get_the_terms( get_the_ID(), 'appcon_level' ) ?: [], 'name' ) ) ); ?></td>
							<td><?php echo esc_html( get_post_meta( get_the_ID(), '_appcon_closing_date', true ) ); ?></td>
						</tr>
					<?php endwhile; ?>
					</tbody>
				</table>

				<?php else : ?>
				<ul class="appcon-vacancies-<?php echo esc_attr( $layout ); ?>">
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
					<li class="appcon-vacancy-item">
						<?php echo self::render_vacancy_card( [], null, (object) [ 'postId' => get_the_ID(), 'showEmployer' => true, 'showLocation' => true, 'showLevel' => true, 'showClosingDate' => true, 'showWage' => true, 'showExcerpt' => true, 'linkToPost' => true ] ); ?>
					</li>
					<?php endwhile; ?>
				</ul>
				<?php endif; ?>

			<?php else : ?>
			<p class="appcon-no-vacancies">
				<?php esc_html_e( 'No vacancies found matching your criteria.', 'apprenticeship-connector' ); ?>
			</p>
			<?php endif; ?>

			<?php wp_reset_postdata(); ?>

			<?php if ( $show_pagination && $query->max_num_pages > 1 ) : ?>
			<nav class="appcon-pagination" aria-label="<?php esc_attr_e( 'Vacancy pages', 'apprenticeship-connector' ); ?>">
				<?php
				echo wp_kses_post( paginate_links( [
					'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
					'format'    => '?paged=%#%',
					'current'   => $paged,
					'total'     => $query->max_num_pages,
					'prev_text' => __( '&laquo; Previous', 'apprenticeship-connector' ),
					'next_text' => __( 'Next &raquo;', 'apprenticeship-connector' ),
				] ) );
				?>
			</nav>
			<?php endif; ?>

		</div>
		<?php
		return ob_get_clean();
	}

	// ── Render: Vacancy Card ───────────────────────────────────────────────

	/**
	 * Server-side render callback for apprenticeship-connector/vacancy-card.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner block content (unused).
	 * @param \WP_Block $block     Block instance (provides context).
	 * @return string HTML output.
	 */
	public static function render_vacancy_card( array $attributes, ?string $content = null, ?\WP_Block $block = null ): string {
		// Resolve post ID from attribute, block context (Query Loop), or global post.
		$post_id = (int) ( $attributes['postId'] ?? ( $block->context['postId'] ?? get_the_ID() ) );

		if ( ! $post_id ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'appcon_vacancy' ) {
			return '';
		}

		$show_employer     = $attributes['showEmployer']    ?? true;
		$show_location     = $attributes['showLocation']    ?? true;
		$show_level        = $attributes['showLevel']       ?? true;
		$show_closing_date = $attributes['showClosingDate'] ?? true;
		$show_wage         = $attributes['showWage']        ?? true;
		$show_excerpt      = $attributes['showExcerpt']     ?? true;
		$link_to_post      = $attributes['linkToPost']      ?? true;

		$meta = [
			'employer'      => get_post_meta( $post_id, '_appcon_employer_name',         true ),
			'location'      => get_post_meta( $post_id, '_appcon_location_address_full', true ),
			'closing_date'  => get_post_meta( $post_id, '_appcon_closing_date',          true ),
			'wage'          => get_post_meta( $post_id, '_appcon_wage_description',      true ),
			'expired'       => (bool) get_post_meta( $post_id, '_appcon_expired',        true ),
		];

		$levels  = get_the_terms( $post_id, 'appcon_level' ) ?: [];
		$level   = implode( ', ', wp_list_pluck( $levels, 'name' ) );

		$title   = get_the_title( $post );
		$url     = get_permalink( $post_id );
		$excerpt = get_the_excerpt( $post );

		ob_start();
		?>
		<article class="appcon-vacancy-card<?php echo $meta['expired'] ? ' appcon-vacancy-card--expired' : ''; ?>">
			<header class="appcon-vacancy-card__header">
				<h3 class="appcon-vacancy-card__title">
					<?php if ( $link_to_post ) : ?>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</h3>
				<?php if ( $meta['expired'] ) : ?>
				<span class="appcon-badge appcon-badge--expired"><?php esc_html_e( 'Expired', 'apprenticeship-connector' ); ?></span>
				<?php endif; ?>
			</header>

			<dl class="appcon-vacancy-card__meta">
				<?php if ( $show_employer && $meta['employer'] ) : ?>
				<div class="appcon-vacancy-card__meta-item">
					<dt><?php esc_html_e( 'Employer', 'apprenticeship-connector' ); ?></dt>
					<dd><?php echo esc_html( $meta['employer'] ); ?></dd>
				</div>
				<?php endif; ?>

				<?php if ( $show_location && $meta['location'] ) : ?>
				<div class="appcon-vacancy-card__meta-item">
					<dt><?php esc_html_e( 'Location', 'apprenticeship-connector' ); ?></dt>
					<dd><?php echo esc_html( $meta['location'] ); ?></dd>
				</div>
				<?php endif; ?>

				<?php if ( $show_level && $level ) : ?>
				<div class="appcon-vacancy-card__meta-item">
					<dt><?php esc_html_e( 'Level', 'apprenticeship-connector' ); ?></dt>
					<dd><?php echo esc_html( $level ); ?></dd>
				</div>
				<?php endif; ?>

				<?php if ( $show_wage && $meta['wage'] ) : ?>
				<div class="appcon-vacancy-card__meta-item">
					<dt><?php esc_html_e( 'Wage', 'apprenticeship-connector' ); ?></dt>
					<dd><?php echo esc_html( $meta['wage'] ); ?></dd>
				</div>
				<?php endif; ?>

				<?php if ( $show_closing_date && $meta['closing_date'] ) : ?>
				<div class="appcon-vacancy-card__meta-item">
					<dt><?php esc_html_e( 'Closing Date', 'apprenticeship-connector' ); ?></dt>
					<dd>
						<?php
						$ts    = strtotime( $meta['closing_date'] );
						$today = strtotime( 'today' );
						$days  = (int) round( ( $ts - $today ) / DAY_IN_SECONDS );
						echo esc_html( wp_date( get_option( 'date_format' ), $ts ) );
						if ( $days > 0 && $days <= 7 ) {
							printf( ' <span class="appcon-expiry-soon">(%s)</span>',
								sprintf( esc_html( _n( '%d day left', '%d days left', $days, 'apprenticeship-connector' ) ), $days )
							);
						}
						?>
					</dd>
				</div>
				<?php endif; ?>
			</dl>

			<?php if ( $show_excerpt && $excerpt ) : ?>
			<div class="appcon-vacancy-card__excerpt">
				<?php echo wp_kses_post( $excerpt ); ?>
			</div>
			<?php endif; ?>

			<?php if ( $link_to_post ) : ?>
			<footer class="appcon-vacancy-card__footer">
				<a href="<?php echo esc_url( $url ); ?>" class="appcon-btn appcon-btn--primary">
					<?php esc_html_e( 'View vacancy', 'apprenticeship-connector' ); ?>
				</a>
			</footer>
			<?php endif; ?>
		</article>
		<?php
		return ob_get_clean();
	}
}
