<?php
/**
 * Import Jobs admin page.
 *
 * When the React build is available the page only provides the mount point
 * `<div id="appcon-admin-root">` and the React app handles everything.
 *
 * When the build is absent (development, missing composer step, etc.) a full
 * PHP-rendered table with basic CRUD actions is shown as a fallback.
 *
 * @package ApprenticeshipConnector\Admin
 */

namespace ApprenticeshipConnector\Admin;

use ApprenticeshipConnector\Core\Database;
use ApprenticeshipConnector\Import\ImportJob;
use ApprenticeshipConnector\Import\ImportRunner;
use ApprenticeshipConnector\Import\ActionSchedulerRunner;

class ImportJobsPage {

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'apprenticeship-connector' ) );
		}

		if ( AdminLoader::react_build_available() ) {
			self::render_react_mount();
		} else {
			self::render_php_fallback();
		}
	}

	// ── React mount ───────────────────────────────────────────────────────

	private static function render_react_mount(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Jobs', 'apprenticeship-connector' ); ?></h1>
			<div id="appcon-admin-root" data-page="import-jobs"></div>
		</div>
		<?php
	}

	// ── PHP fallback ──────────────────────────────────────────────────────

	private static function render_php_fallback(): void {
		global $wpdb;

		// ── Handle actions ───────────────────────────────────────────────
		$action = sanitize_key( $_GET['appcon_action'] ?? '' );
		$job_id = (int) ( $_GET['job_id'] ?? 0 );
		$notice = '';

		if ( $action && ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'appcon_job_action_' . $job_id ) ) {
			$action = '';
			$notice = '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed. Please try again.', 'apprenticeship-connector' ) . '</p></div>';
		}

		if ( $action === 'delete' && $job_id ) {
			ImportJob::delete( $job_id );
			$notice = '<div class="notice notice-success"><p>' . esc_html__( 'Import job deleted.', 'apprenticeship-connector' ) . '</p></div>';
		}

		if ( $action === 'run' && $job_id ) {
			$job = ImportJob::find( $job_id );
			if ( $job ) {
				try {
					if ( function_exists( 'as_enqueue_async_action' ) ) {
						$runner = new ActionSchedulerRunner();
						$run_id = $runner->enqueue( $job_id );
					} else {
						$runner = new ImportRunner();
						$run_id = $runner->trigger( $job_id );
					}
					$notice = '<div class="notice notice-info"><p>' .
						sprintf( esc_html__( 'Import started. Run ID: %s', 'apprenticeship-connector' ), esc_html( $run_id ) ) .
						'</p></div>';
				} catch ( \Throwable $e ) {
					$notice = '<div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div>';
				}
			}
		}

		// ── Fetch jobs ───────────────────────────────────────────────────
		$jobs = $wpdb->get_results( 'SELECT * FROM ' . Database::get_jobs_table() . ' ORDER BY created_at DESC', ARRAY_A );

		$page_url = admin_url( 'admin.php?page=appcon-import-jobs' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Import Jobs', 'apprenticeship-connector' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=appcon-import-jobs&appcon_action=new' ) ); ?>"
			   class="page-title-action"><?php esc_html_e( 'Add New', 'apprenticeship-connector' ); ?></a>

			<div class="notice notice-warning inline">
				<p>
					<?php esc_html_e( 'The React admin interface has not been built yet. Run', 'apprenticeship-connector' ); ?>
					<code>npm run build</code>
					<?php esc_html_e( 'in the plugin directory for the full UI.', 'apprenticeship-connector' ); ?>
				</p>
			</div>

			<?php echo wp_kses_post( $notice ); ?>

			<?php if ( empty( $jobs ) ) : ?>
				<p><?php esc_html_e( 'No import jobs found. Create one to get started.', 'apprenticeship-connector' ); ?></p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped posts">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'apprenticeship-connector' ); ?></th>
						<th><?php esc_html_e( 'Status', 'apprenticeship-connector' ); ?></th>
						<th><?php esc_html_e( 'Last Run', 'apprenticeship-connector' ); ?></th>
						<th><?php esc_html_e( 'Created', 'apprenticeship-connector' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'apprenticeship-connector' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $jobs as $job ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $job['name'] ); ?></strong></td>
					<td><?php echo esc_html( $job['status'] ); ?></td>
					<td>
						<?php if ( $job['last_run_at'] ) : ?>
							<?php echo esc_html( $job['last_run_at'] ); ?><br>
							<small><?php echo esc_html( sprintf(
								__( '%d created, %d updated, %d errors', 'apprenticeship-connector' ),
								$job['last_run_created'],
								$job['last_run_updated'],
								$job['last_run_errors']
							) ); ?></small>
						<?php else : ?>
							<?php esc_html_e( 'Never run', 'apprenticeship-connector' ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $job['created_at'] ); ?></td>
					<td>
						<?php
						$run_url    = esc_url( wp_nonce_url( add_query_arg( [ 'appcon_action' => 'run',    'job_id' => $job['id'] ], $page_url ), 'appcon_job_action_' . $job['id'] ) );
						$delete_url = esc_url( wp_nonce_url( add_query_arg( [ 'appcon_action' => 'delete', 'job_id' => $job['id'] ], $page_url ), 'appcon_job_action_' . $job['id'] ) );
						?>
						<a class="button button-small" href="<?php echo $run_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>"><?php esc_html_e( 'Run Now', 'apprenticeship-connector' ); ?></a>
						&nbsp;
						<a class="button button-small button-link-delete"
						   href="<?php echo $delete_url; // phpcs:ignore WordPress.Security.EscapeOutput ?>"
						   onclick="return confirm('<?php esc_attr_e( 'Delete this import job?', 'apprenticeship-connector' ); ?>')"
						><?php esc_html_e( 'Delete', 'apprenticeship-connector' ); ?></a>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
