<?php
/**
 * Frontend vacancy search and archive support.
 *
 * Responsibilities:
 *  1. Register custom query vars so WordPress doesn't strip them.
 *  2. Modify the main query on vacancy archives to apply:
 *       - Keyword search (appcon_search)
 *       - Level / Route taxonomy filters (appcon_level, appcon_route)
 *       - Show/hide expired vacancies (appcon_show_expired)
 *  3. Register a custom "Archived" post status so expired vacancies can be
 *     queried distinctly from hand-drafted posts.
 *
 * @package ApprenticeshipConnector\Frontend
 */

namespace ApprenticeshipConnector\Frontend;

class VacancySearch {

	// ── Registration ───────────────────────────────────────────────────────

	/**
	 * Register all query vars and hooks.  Called from Plugin::define_public_hooks().
	 */
	public static function register(): void {
		add_filter( 'query_vars',    [ self::class, 'add_query_vars' ] );
		add_action( 'pre_get_posts', [ self::class, 'modify_vacancy_query' ] );
		add_action( 'init',          [ self::class, 'register_archive_status' ] );

		// Admin: show expired/archived vacancies in the WP admin list table.
		add_action( 'restrict_manage_posts', [ self::class, 'admin_expiry_filter' ] );
		add_filter( 'parse_query',           [ self::class, 'admin_parse_expiry_filter' ] );
	}

	// ── Custom query vars ──────────────────────────────────────────────────

	/**
	 * Whitelist custom query vars so WP doesn't treat them as 404 signals.
	 *
	 * @param  array $vars Existing query vars.
	 * @return array
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = 'appcon_search';
		$vars[] = 'appcon_level';
		$vars[] = 'appcon_route';
		$vars[] = 'appcon_show_expired';
		return $vars;
	}

	// ── Main query modification ────────────────────────────────────────────

	/**
	 * Modify the main WP_Query on the vacancy archive page to apply filters.
	 *
	 * @param \WP_Query $query The main query object.
	 */
	public static function modify_vacancy_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Only apply to the vacancy archive or single vacancy.
		if ( ! $query->is_post_type_archive( 'appcon_vacancy' ) && ! $query->is_singular( 'appcon_vacancy' ) ) {
			return;
		}

		// ── Keyword search ────────────────────────────────────────────────
		$search = sanitize_text_field( $query->get( 'appcon_search' ) ?: get_query_var( 'appcon_search' ) );
		if ( $search ) {
			$query->set( 's', $search );
		}

		// ── Taxonomy filters ──────────────────────────────────────────────
		$level = sanitize_text_field( $query->get( 'appcon_level' ) ?: get_query_var( 'appcon_level' ) );
		$route = sanitize_text_field( $query->get( 'appcon_route' ) ?: get_query_var( 'appcon_route' ) );

		$tax_query = (array) $query->get( 'tax_query' );
		if ( $level ) {
			$tax_query[] = [
				'taxonomy' => 'appcon_level',
				'field'    => is_numeric( $level ) ? 'term_id' : 'slug',
				'terms'    => $level,
			];
		}
		if ( $route ) {
			$tax_query[] = [
				'taxonomy' => 'appcon_route',
				'field'    => is_numeric( $route ) ? 'term_id' : 'slug',
				'terms'    => $route,
			];
		}
		if ( ! empty( $tax_query ) ) {
			$query->set( 'tax_query', $tax_query );
		}

		// ── Expired filter ────────────────────────────────────────────────
		$show_expired = (bool) get_query_var( 'appcon_show_expired' );

		if ( ! $show_expired ) {
			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = [
				'relation' => 'OR',
				[ 'key' => '_appcon_expired', 'compare' => 'NOT EXISTS' ],
				[ 'key' => '_appcon_expired', 'value' => '1', 'compare' => '!=' ],
			];
			$query->set( 'meta_query', $meta_query );
		}
	}

	// ── Archive post status ────────────────────────────────────────────────

	/**
	 * Register a custom "appcon_archived" post status for expired vacancies.
	 *
	 * This allows archived vacancies to be distinguished from hand-drafted posts
	 * in queries and the WP admin.
	 */
	public static function register_archive_status(): void {
		register_post_status( 'appcon_archived', [
			'label'                     => _x( 'Archived', 'post status', 'apprenticeship-connector' ),
			'public'                    => false,
			'protected'                 => true,
			'private'                   => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of archived posts */
			'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'apprenticeship-connector' ),
		] );
	}

	// ── WP Admin: expiry status filter ────────────────────────────────────

	/**
	 * Add an "Expired" dropdown filter to the vacancy list table in WP admin.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function admin_expiry_filter( string $post_type ): void {
		if ( $post_type !== 'appcon_vacancy' ) {
			return;
		}

		$current = sanitize_key( $_GET['appcon_expiry_filter'] ?? '' );
		?>
		<select name="appcon_expiry_filter">
			<option value=""><?php esc_html_e( '— Expiry Status —', 'apprenticeship-connector' ); ?></option>
			<option value="active"  <?php selected( $current, 'active' ); ?>><?php esc_html_e( 'Active (not expired)', 'apprenticeship-connector' ); ?></option>
			<option value="expired" <?php selected( $current, 'expired' ); ?>><?php esc_html_e( 'Expired', 'apprenticeship-connector' ); ?></option>
			<option value="upcoming"<?php selected( $current, 'upcoming' ); ?>><?php esc_html_e( 'Expiring in 7 days', 'apprenticeship-connector' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Apply the expiry filter to WP admin queries.
	 *
	 * @param \WP_Query $query Admin query.
	 */
	public static function admin_parse_expiry_filter( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'post_type' ) !== 'appcon_vacancy' ) {
			return;
		}

		$filter = sanitize_key( $_GET['appcon_expiry_filter'] ?? '' );
		$today  = gmdate( 'Y-m-d' );

		if ( $filter === 'expired' ) {
			$query->set( 'meta_query', [ [
				'key'     => '_appcon_expired',
				'value'   => '1',
				'compare' => '=',
			] ] );
		} elseif ( $filter === 'active' ) {
			$query->set( 'meta_query', [ [
				'relation' => 'OR',
				[ 'key' => '_appcon_expired', 'compare' => 'NOT EXISTS' ],
				[ 'key' => '_appcon_expired', 'value' => '1', 'compare' => '!=' ],
			] ] );
		} elseif ( $filter === 'upcoming' ) {
			$seven_days = gmdate( 'Y-m-d', strtotime( '+7 days' ) );
			$query->set( 'meta_query', [ [
				'key'     => '_appcon_closing_date',
				'value'   => [ $today, $seven_days ],
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			] ] );
		}
	}
}
