<?php
/**
 * Vacancy expiry manager.
 *
 * Responsible for:
 *  1. Setting published vacancies to 'draft' when their closing date passes.
 *  2. (Optionally) deleting or archiving very old vacancies.
 *
 * Called via Action Scheduler daily action `appcon_as_expire_vacancies`.
 * Also exposes a manual trigger for the REST API.
 *
 * @package ApprenticeshipConnector\Import
 */

namespace ApprenticeshipConnector\Import;

class ExpiryManager {

	/**
	 * Process expiry in batches of 200 to avoid memory issues on large sites.
	 *
	 * @return array{ expired: int, already_draft: int }
	 */
	public function run(): array {
		$today   = gmdate( 'Y-m-d' );
		$expired = 0;

		do {
			$ids = $this->get_expired_published_ids( $today, 200 );

			foreach ( $ids as $post_id ) {
				wp_update_post( [
					'ID'          => $post_id,
					'post_status' => 'draft',
				] );
				update_post_meta( $post_id, '_appcon_expired', 1 );
				update_post_meta( $post_id, '_appcon_expired_on', $today );
				$expired++;
			}
		} while ( count( $ids ) === 200 );

		if ( $expired > 0 ) {
			\ApprenticeshipConnector\Core\Settings::set( 'last_expiry_run', [
				'date'    => $today,
				'expired' => $expired,
			] );
		}

		return [ 'expired' => $expired ];
	}

	/**
	 * Re-publish vacancies whose closing date is in the future (useful
	 * after a manual date correction).
	 *
	 * @return int  Number re-published.
	 */
	public function restore_not_yet_expired(): int {
		$today   = gmdate( 'Y-m-d' );
		$restored = 0;

		$query = new \WP_Query( [
			'post_type'      => 'appcon_vacancy',
			'post_status'    => 'draft',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'     => '_appcon_expired',
					'value'   => '1',
					'compare' => '=',
				],
				[
					'key'     => '_appcon_closing_date',
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				],
			],
		] );

		foreach ( $query->posts as $post_id ) {
			wp_update_post( [
				'ID'          => $post_id,
				'post_status' => 'publish',
			] );
			delete_post_meta( $post_id, '_appcon_expired' );
			delete_post_meta( $post_id, '_appcon_expired_on' );
			$restored++;
		}

		return $restored;
	}

	/**
	 * Return a summary of expiry statistics for the dashboard.
	 *
	 * @return array{ expired_today: int, total_expired_drafts: int, upcoming_7d: int }
	 */
	public function get_stats(): array {
		$today      = gmdate( 'Y-m-d' );
		$seven_days = gmdate( 'Y-m-d', strtotime( '+7 days' ) );

		$expired_today = ( new \WP_Query( [
			'post_type'      => 'appcon_vacancy',
			'post_status'    => 'draft',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ [
				'key'     => '_appcon_expired_on',
				'value'   => $today,
				'compare' => '=',
			] ],
		] ) )->found_posts;

		$total_expired = ( new \WP_Query( [
			'post_type'      => 'appcon_vacancy',
			'post_status'    => 'draft',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_appcon_expired',
			'meta_value'     => '1',
		] ) )->found_posts;

		$upcoming = ( new \WP_Query( [
			'post_type'      => 'appcon_vacancy',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ [
				'key'     => '_appcon_closing_date',
				'value'   => [ $today, $seven_days ],
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			] ],
		] ) )->found_posts;

		return [
			'expired_today'       => $expired_today,
			'total_expired_drafts' => $total_expired,
			'upcoming_7d'         => $upcoming,
		];
	}

	// ── Private ────────────────────────────────────────────────────────────

	private function get_expired_published_ids( string $today, int $limit ): array {
		$query = new \WP_Query( [
			'post_type'      => 'appcon_vacancy',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_query'     => [ [
				'key'     => '_appcon_closing_date',
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			] ],
		] );

		return $query->posts;
	}
}
