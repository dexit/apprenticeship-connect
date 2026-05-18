<?php
/**
 * Training Provider CPT
 *
 * Registers the `apprco_provider` custom post type and syncs provider records
 * from import data keyed by UKPRN. Providers are managed entirely by the
 * import pipeline — not editable by hand.
 *
 * Each provider post stores:
 *   _apprco_ukprn          (bigint, unique identifier)
 *   _apprco_provider_name
 *   _apprco_provider_website
 *   _apprco_vacancy_count  (updated on each import run)
 *
 * Separate Workplace rows are stored in the `apprco_workplaces` DB table
 * and linked to vacancies via _apprco_workplace_ids (serialised array).
 *
 * @package ApprenticeshipConnect
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Apprco_Provider
 */
class Apprco_Provider {

	/** CPT slug. */
	public const POST_TYPE = 'apprco_provider';

	/** Workplace table name (no prefix). */
	private const WP_TABLE = 'apprco_workplaces';

	/** @var self|null */
	private static $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register the apprco_provider CPT.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Training Providers', 'apprenticeship-connect' ),
				'labels'              => array(
					'name'               => __( 'Training Providers', 'apprenticeship-connect' ),
					'singular_name'      => __( 'Training Provider', 'apprenticeship-connect' ),
					'add_new'            => __( 'Add Provider', 'apprenticeship-connect' ),
					'add_new_item'       => __( 'Add New Provider', 'apprenticeship-connect' ),
					'edit_item'          => __( 'View Provider', 'apprenticeship-connect' ),
					'view_item'          => __( 'View Provider', 'apprenticeship-connect' ),
					'all_items'          => __( 'All Providers', 'apprenticeship-connect' ),
					'search_items'       => __( 'Search Providers', 'apprenticeship-connect' ),
					'not_found'          => __( 'No providers found.', 'apprenticeship-connect' ),
					'not_found_in_trash' => __( 'No providers in trash.', 'apprenticeship-connect' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=apprco_vacancy',
				'show_in_rest'        => true,
				'has_archive'         => 'apprenticeship-providers',
				'rewrite'             => array( 'slug' => 'provider', 'with_front' => false ),
				'supports'            => array( 'title', 'thumbnail', 'excerpt' ),
				'menu_icon'           => 'dashicons-building',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Register provider-specific taxonomies (sector/region).
	 */
	public function register_taxonomies(): void {
		// Sector taxonomy (shared with vacancies).
		register_taxonomy(
			'apprco_sector',
			array( self::POST_TYPE, 'apprco_vacancy' ),
			array(
				'label'        => __( 'Sectors', 'apprenticeship-connect' ),
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'apprenticeship-sector' ),
			)
		);
	}

	// ── Sync from import data ────────────────────────────────────────────────

	/**
	 * Sync a training provider from Stage 2 vacancy data.
	 * Creates a new provider post if one doesn't exist for this UKPRN,
	 * otherwise updates the existing post's meta.
	 *
	 * @param array $data Vacancy data array (expects providerUkprn, providerName, etc.).
	 * @return int Post ID of the provider, or 0 on failure.
	 */
	public static function sync_from_vacancy( array $data ): int {
		$ukprn = isset( $data['providerUkprn'] ) ? (int) $data['providerUkprn'] : 0;
		if ( ! $ukprn ) {
			return 0;
		}

		$name    = $data['providerName'] ?? $data['employerName'] ?? '';
		$website = $data['employerWebsite'] ?? '';

		// Look up existing provider by UKPRN meta.
		$existing = self::find_by_ukprn( $ukprn );

		if ( $existing ) {
			$post_id = $existing;
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $name,
				)
			);
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_title'  => $name,
					'post_status' => 'publish',
					'post_name'   => sanitize_title( $name . '-' . $ukprn ),
				)
			);
			if ( is_wp_error( $post_id ) ) {
				return 0;
			}
		}

		update_post_meta( $post_id, '_apprco_ukprn', $ukprn );
		update_post_meta( $post_id, '_apprco_provider_name', sanitize_text_field( $name ) );
		if ( ! empty( $website ) ) {
			update_post_meta( $post_id, '_apprco_provider_website', esc_url_raw( $website ) );
		}

		return (int) $post_id;
	}

	/**
	 * Update vacancy count for a provider (called after each import run).
	 *
	 * @param int $provider_post_id Provider post ID.
	 * @return void
	 */
	public static function update_vacancy_count( int $provider_post_id ): void {
		$count = (int) ( new WP_Query(
			array(
				'post_type'      => 'apprco_vacancy',
				'meta_key'       => '_apprco_provider_post_id',
				'meta_value'     => $provider_post_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		) )->found_posts;

		update_post_meta( $provider_post_id, '_apprco_vacancy_count', $count );
	}

	/**
	 * Find a provider post ID by UKPRN.
	 *
	 * @param int $ukprn Provider UKPRN.
	 * @return int|null Post ID or null.
	 */
	public static function find_by_ukprn( int $ukprn ): ?int {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'meta_key'       => '_apprco_ukprn',
				'meta_value'     => $ukprn,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return ! empty( $posts ) ? (int) $posts[0] : null;
	}

	// ── Workplace DB table ───────────────────────────────────────────────────

	/**
	 * Create the apprco_workplaces table.
	 * Workplaces are deduplicated by postcode, linked to provider UKPRN.
	 *
	 * @return void
	 */
	public static function create_workplaces_table(): void {
		global $wpdb;
		$table           = $wpdb->prefix . self::WP_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider_ukprn bigint(20) unsigned NOT NULL,
			provider_post_id bigint(20) unsigned DEFAULT NULL,
			postcode varchar(20) NOT NULL DEFAULT '',
			lat decimal(10,7) DEFAULT NULL,
			lng decimal(10,7) DEFAULT NULL,
			town varchar(100) DEFAULT '',
			county varchar(100) DEFAULT '',
			address_line1 varchar(255) DEFAULT '',
			address_line2 varchar(255) DEFAULT '',
			address_line3 varchar(255) DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY postcode_ukprn (postcode, provider_ukprn),
			KEY provider_ukprn (provider_ukprn),
			KEY lat_lng (lat, lng)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Sync workplace locations from a vacancy's address array.
	 *
	 * @param int   $provider_ukprn Provider UKPRN.
	 * @param int   $provider_post_id Provider post ID.
	 * @param array $addresses Array of address objects from the API.
	 * @return int[] Array of workplace row IDs upserted.
	 */
	public static function sync_workplaces( int $provider_ukprn, int $provider_post_id, array $addresses ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::WP_TABLE;
		$ids   = array();
		$now   = current_time( 'mysql' );

		foreach ( $addresses as $addr ) {
			$postcode = strtoupper( preg_replace( '/\s+/', '', $addr['postcode'] ?? '' ) );
			if ( empty( $postcode ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE postcode = %s AND provider_ukprn = %d',
					$table,
					$postcode,
					$provider_ukprn
				)
			);

			$row = array(
				'provider_ukprn'   => $provider_ukprn,
				'provider_post_id' => $provider_post_id,
				'postcode'         => $postcode,
				'town'             => $addr['town'] ?? '',
				'address_line1'    => $addr['addressLine1'] ?? '',
				'address_line2'    => $addr['addressLine2'] ?? '',
				'address_line3'    => $addr['addressLine3'] ?? '',
				'updated_at'       => $now,
			);

			if ( $existing ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->update( $table, $row, array( 'id' => $existing ) );
				$ids[] = (int) $existing;
			} else {
				$row['created_at'] = $now;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert( $table, $row );
				$ids[] = (int) $wpdb->insert_id;

				// Enqueue geocoding for new workplace postcodes.
				Apprco_Geocoder::enqueue_for_vacancy(
					'workplace_' . $postcode . '_' . $provider_ukprn,
					$postcode
				);
			}
		}

		return $ids;
	}

	/**
	 * Get all workplaces for a provider.
	 *
	 * @param int $provider_ukprn UKPRN.
	 * @return array
	 */
	public static function get_workplaces( int $provider_ukprn ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::WP_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE provider_ukprn = %d ORDER BY town ASC', $table, $provider_ukprn ),
			ARRAY_A
		);
	}

	/**
	 * Get providers near a location (Haversine).
	 *
	 * @param float $lat        Search latitude.
	 * @param float $lng        Search longitude.
	 * @param float $radius_km  Radius in kilometres.
	 * @param int   $limit      Max results.
	 * @return array
	 */
	public static function get_near( float $lat, float $lng, float $radius_km = 16.0, int $limit = 20 ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::WP_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *, ( 6371 * acos( cos( radians(%f) ) * cos( radians(lat) ) * cos( radians(lng) - radians(%f) ) + sin( radians(%f) ) * sin( radians(lat) ) ) ) AS distance
				FROM %i
				WHERE lat IS NOT NULL
				HAVING distance <= %f
				ORDER BY distance ASC
				LIMIT %d",
				$lat, $lng, $lat, $table, $radius_km, $limit
			),
			ARRAY_A
		);
	}
}
