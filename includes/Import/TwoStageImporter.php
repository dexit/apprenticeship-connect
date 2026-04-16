<?php
/**
 * Two-Stage Import System.
 *
 * Stage 1 – Fetch list endpoint (paginated) → collect vacancyReferences.
 * Stage 2 – Fetch /vacancy/{ref} for FULL details → create/update posts.
 *
 * @package ApprenticeshipConnector\Import
 */

namespace ApprenticeshipConnector\Import;

use ApprenticeshipConnector\API\DisplayAdvertAPI;

class TwoStageImporter {

	public function __construct(
		private readonly DisplayAdvertAPI $api,
		private readonly FieldMapper      $mapper,
		private readonly Logger           $logger
	) {}

	// ── Public entry point ─────────────────────────────────────────────────

	/**
	 * Execute a full two-stage import.
	 *
	 * @param  ImportJob      $job      Configuration.
	 * @param  callable|null  $progress Optional progress callback:
	 *                                  fn(array $status): void
	 * @return array  Results summary.
	 */
	public function run( ImportJob $job, ?callable $progress = null ): array {
		$results = $this->init_results();

		$this->logger->info( 'Two-stage import starting', [
			'job_id'   => $job->id,
			'job_name' => $job->name,
		] );

		try {
			$references = $this->runStage1( $job, $results, $progress );
			$this->runStage2( $job, $references, $results, $progress );
			$results['status'] = 'completed';
		} catch ( \Throwable $e ) {
			$results['status'] = 'failed';
			$results['error']  = $e->getMessage();
			$this->logger->error( 'Import failed', [
				'error' => $e->getMessage(),
				'file'  => $e->getFile(),
				'line'  => $e->getLine(),
			] );
		}

		$results['duration'] = round( microtime( true ) - $results['start_time'], 2 );
		unset( $results['start_time'] );

		$this->logger->info( 'Two-stage import finished', $results );
		return $results;
	}

	// ── Stage 1 ────────────────────────────────────────────────────────────

	private function runStage1( ImportJob $job, array &$results, ?callable $cb ): array {
		$this->logger->info( 'Stage 1 starting' );

		$references = [];
		$page       = 1;
		$max_pages  = $job->stage1_max_pages;
		$has_more   = true;

		while ( $has_more && $page <= $max_pages ) {
			if ( $cb ) {
				$cb( [ 'stage' => 1, 'page' => $page, 'collected' => count( $references ) ] );
			}

			$params = array_merge( $job->stage1_filters, [
				'PageNumber' => $page,
				'PageSize'   => $job->stage1_page_size,
				'Sort'       => $job->stage1_sort,
			] );

			$this->logger->debug( 'Stage 1: fetching page', [ 'page' => $page, 'params' => $params ] );

			$response = $this->api->getVacancies( $params );

			if ( ! $response['success'] ) {
				$results['stage1']['errors']++;
				$this->logger->error( 'Stage 1 API error', [ 'page' => $page, 'error' => $response['error'] ] );
				break;
			}

			$vacancies   = $response['data']['vacancies'] ?? [];
			$total_pages = $response['data']['totalPages'] ?? 1;

			foreach ( $vacancies as $v ) {
				if ( ! empty( $v['vacancyReference'] ) ) {
					$references[] = $v['vacancyReference'];
				}
			}

			$results['stage1']['pages']   = $page;
			$results['stage1']['fetched'] = count( $references );

			$has_more = ! empty( $vacancies ) && $page < $total_pages;
			$page++;
		}

		$this->logger->info( 'Stage 1 finished', [
			'pages'      => $results['stage1']['pages'],
			'references' => count( $references ),
		] );

		return $references;
	}

	// ── Stage 2 ────────────────────────────────────────────────────────────

	private function runStage2(
		ImportJob $job,
		array     $references,
		array     &$results,
		?callable $cb
	): void {
		$total                   = count( $references );
		$results['stage2']['total'] = $total;

		$this->logger->info( 'Stage 2 starting', [ 'total_vacancies' => $total ] );

		foreach ( $references as $i => $reference ) {
			$current = $i + 1;

			if ( $cb ) {
				$cb( [
					'stage'        => 2,
					'current'      => $current,
					'total'        => $total,
					'reference'    => $reference,
					'progress_pct' => round( $current / max( $total, 1 ) * 100, 2 ),
				] );
			}

			try {
				$response = $this->api->getVacancy( $reference );

				if ( ! $response['success'] ) {
					$results['stage2']['errors']++;
					$this->logger->warning( 'Stage 2: failed to fetch vacancy', [
						'reference' => $reference,
						'error'     => $response['error'],
					] );
					continue;
				}

				$vacancy = $response['data'];
				$results['stage2']['fetched']++;

				$existing_id = $this->findExistingVacancy( $reference );
				$post_data   = $this->mapper->mapToPost( $vacancy );
				$meta_data   = $this->mapper->mapToMeta( $vacancy );
				$tax_data    = $this->mapper->mapToTaxonomies( $vacancy );

				if ( $existing_id ) {
					$this->updateVacancy( $existing_id, $post_data, $meta_data, $tax_data );
					$results['stage2']['updated']++;
					$this->logger->debug( 'Vacancy updated', [ 'reference' => $reference, 'post_id' => $existing_id ] );
				} else {
					$post_id = $this->createVacancy( $post_data, $meta_data, $tax_data );
					$results['stage2']['created']++;
					$this->logger->debug( 'Vacancy created', [ 'reference' => $reference, 'post_id' => $post_id ] );
				}
			} catch ( \Throwable $e ) {
				$results['stage2']['errors']++;
				$this->logger->error( 'Stage 2: exception processing vacancy', [
					'reference' => $reference,
					'error'     => $e->getMessage(),
				] );
			}
		}

		$this->logger->info( 'Stage 2 finished', $results['stage2'] );
	}

	// ── DB helpers ─────────────────────────────────────────────────────────

	private function findExistingVacancy( string $reference ): ?int {
		global $wpdb;

		$post_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_appcon_vacancy_reference'
			   AND meta_value = %s
			 LIMIT 1",
			$reference
		) );

		return $post_id ? (int) $post_id : null;
	}

	private function createVacancy( array $post_data, array $meta_data, array $tax_data ): int {
		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( 'wp_insert_post failed: ' . $post_id->get_error_message() );
		}

		$this->saveMetaAndTaxonomies( $post_id, $meta_data, $tax_data );
		return $post_id;
	}

	private function updateVacancy( int $post_id, array $post_data, array $meta_data, array $tax_data ): void {
		wp_update_post( array_merge( $post_data, [ 'ID' => $post_id ] ) );
		$this->saveMetaAndTaxonomies( $post_id, $meta_data, $tax_data );
	}

	private function saveMetaAndTaxonomies( int $post_id, array $meta_data, array $tax_data ): void {
		foreach ( $meta_data as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		foreach ( $tax_data as $taxonomy => $terms ) {
			wp_set_object_terms( $post_id, $terms, $taxonomy );
		}
	}

	// ── Utils ──────────────────────────────────────────────────────────────

	private function init_results(): array {
		return [
			'start_time' => microtime( true ),
			'status'     => 'running',
			'error'      => null,
			'duration'   => 0,
			'stage1'     => [ 'pages' => 0, 'fetched' => 0, 'errors' => 0 ],
			'stage2'     => [ 'total' => 0, 'fetched' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0 ],
		];
	}
}
