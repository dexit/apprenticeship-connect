<?php
/**
 * Admin Dashboard page.
 *
 * Renders a rich statistics summary regardless of whether the React build
 * is available.  The React app (when present) mounts into #appcon-admin-root
 * and overlays dynamic stats over the static PHP shell.
 *
 * @package ApprenticeshipConnector\Admin
 */

namespace ApprenticeshipConnector\Admin;

use ApprenticeshipConnector\Core\Database;
use ApprenticeshipConnector\Core\Settings;
use ApprenticeshipConnector\Import\ExpiryManager;

class Dashboard {

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'apprenticeship-connector' ) );
		}

		global $wpdb;

		// ── Stats ─────────────────────────────────────────────────────────
		$counts = wp_count_posts( 'appcon_vacancy' );
		$vacancy_published  = (int) ( $counts->publish      ?? 0 );
		$vacancy_draft      = (int) ( $counts->draft        ?? 0 );
		$vacancy_private    = (int) ( $counts->private      ?? 0 );

		$expired_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_appcon_expired' AND meta_value = '1'"
		);

		$job_count  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::get_jobs_table() );
		$active_jobs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . Database::get_jobs_table() . " WHERE status = 'active'" );

		$last_run = $wpdb->get_row(
			'SELECT * FROM ' . Database::get_runs_table() . ' ORDER BY created_at DESC LIMIT 1',
			ARRAY_A
		);

		$today        = gmdate( 'Y-m-d' );
		$seven_days   = gmdate( 'Y-m-d', strtotime( '+7 days' ) );
		$upcoming_exp = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			 WHERE pm.meta_key = '_appcon_closing_date'
			   AND pm.meta_value BETWEEN %s AND %s
			   AND p.post_type   = 'appcon_vacancy'
			   AND p.post_status = 'publish'",
			$today,
			$seven_days
		) );

		$last_expiry = Settings::get( 'last_expiry_run', [] );

		$api_key_set = (bool) Settings::get( 'api_key', '' );

		$react = AdminLoader::react_build_available();
		?>
		<div class="wrap appcon-dashboard">
			<h1><?php esc_html_e( 'Apprenticeship Connector', 'apprenticeship-connector' ); ?>
				<span style="font-size:0.6em; color:#646970; vertical-align:middle; margin-left:8px">
					v<?php echo esc_html( APPCON_VERSION ); ?>
				</span>
			</h1>

			<?php if ( ! $api_key_set ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php printf(
						/* translators: %s: settings page URL */
						wp_kses( __( 'No API subscription key configured. <a href="%s">Configure settings →</a>', 'apprenticeship-connector' ), [ 'a' => [ 'href' => [] ] ] ),
						esc_url( admin_url( 'admin.php?page=appcon-settings' ) )
					); ?>
				</p>
			</div>
			<?php endif; ?>

			<?php if ( ! $react ) : ?>
			<div class="notice notice-info">
				<p>
					<?php esc_html_e( 'React admin UI not built. Run', 'apprenticeship-connector' ); ?>
					<code>npm run build</code>
					<?php esc_html_e( 'for the full management interface.', 'apprenticeship-connector' ); ?>
				</p>
			</div>
			<?php endif; ?>

			<?php /* ── Stats grid ── */ ?>
			<div class="appcon-stats">

				<div class="appcon-stat-card">
					<span class="appcon-stat-number"><?php echo esc_html( number_format_i18n( $vacancy_published ) ); ?></span>
					<span class="appcon-stat-label"><?php esc_html_e( 'Published Vacancies', 'apprenticeship-connector' ); ?></span>
				</div>

				<div class="appcon-stat-card">
					<span class="appcon-stat-number"><?php echo esc_html( number_format_i18n( $expired_count ) ); ?></span>
					<span class="appcon-stat-label"><?php esc_html_e( 'Expired (Drafts)', 'apprenticeship-connector' ); ?></span>
				</div>

				<?php if ( $upcoming_exp > 0 ) : ?>
				<div class="appcon-stat-card appcon-stat-card--warning">
					<span class="appcon-stat-number"><?php echo esc_html( number_format_i18n( $upcoming_exp ) ); ?></span>
					<span class="appcon-stat-label"><?php esc_html_e( 'Expiring in 7 Days', 'apprenticeship-connector' ); ?></span>
				</div>
				<?php endif; ?>

				<div class="appcon-stat-card">
					<span class="appcon-stat-number"><?php echo esc_html( number_format_i18n( $job_count ) ); ?></span>
					<span class="appcon-stat-label">
						<?php
						echo esc_html( sprintf(
							/* translators: 1: total jobs 2: active jobs */
							__( 'Import Jobs (%d active)', 'apprenticeship-connector' ),
							$active_jobs
						) );
						?>
					</span>
				</div>

				<?php if ( $last_run ) : ?>
				<div class="appcon-stat-card">
					<span class="appcon-stat-number appcon-status appcon-status--<?php echo esc_attr( $last_run['status'] ); ?>">
						<?php echo esc_html( ucfirst( $last_run['status'] ) ); ?>
					</span>
					<span class="appcon-stat-label">
						<?php esc_html_e( 'Last Run', 'apprenticeship-connector' ); ?><br>
						<small><?php echo esc_html( $last_run['created_at'] ); ?></small>
					</span>
				</div>
				<?php endif; ?>

			</div>

			<?php /* ── Last run details ── */ ?>
			<?php if ( $last_run && $last_run['status'] === 'completed' ) : ?>
			<div class="appcon-panel" style="margin-top:16px;">
				<h3><?php esc_html_e( 'Last Import Summary', 'apprenticeship-connector' ); ?></h3>
				<table class="widefat striped" style="max-width:600px;">
					<tbody>
						<tr><th><?php esc_html_e( 'Run ID',      'apprenticeship-connector' ); ?></th><td><code><?php echo esc_html( $last_run['run_id'] ); ?></code></td></tr>
						<tr><th><?php esc_html_e( 'Created',     'apprenticeship-connector' ); ?></th><td><?php echo esc_html( $last_run['stage2_created'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Updated',     'apprenticeship-connector' ); ?></th><td><?php echo esc_html( $last_run['stage2_updated'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Errors',      'apprenticeship-connector' ); ?></th><td><?php echo esc_html( $last_run['stage2_errors'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Duration',    'apprenticeship-connector' ); ?></th><td><?php echo esc_html( $last_run['duration'] . 's' ); ?></td></tr>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<?php /* ── Last expiry run ── */ ?>
			<?php if ( ! empty( $last_expiry ) ) : ?>
			<div class="appcon-panel" style="margin-top:12px;">
				<h3><?php esc_html_e( 'Last Expiry Run', 'apprenticeship-connector' ); ?></h3>
				<p><?php printf(
					/* translators: 1: number of vacancies expired 2: date */
					esc_html__( '%1$d vacancies expired on %2$s', 'apprenticeship-connector' ),
					(int) ( $last_expiry['expired'] ?? 0 ),
					esc_html( $last_expiry['date']    ?? '—' )
				); ?></p>
			</div>
			<?php endif; ?>

			<?php /* ── React app mount (overlays dynamic content) ── */ ?>
			<?php if ( $react ) : ?>
			<div id="appcon-admin-root" data-page="dashboard" style="margin-top:24px;"></div>
			<?php endif; ?>

			<?php /* ── Quick links ── */ ?>
			<div class="appcon-quick-links" style="margin-top:20px;">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=appcon-import-jobs' ) ); ?>">
					<?php esc_html_e( 'Manage Import Jobs →', 'apprenticeship-connector' ); ?>
				</a>
				&nbsp;
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=appcon-settings' ) ); ?>">
					<?php esc_html_e( 'Settings →', 'apprenticeship-connector' ); ?>
				</a>
				&nbsp;
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=appcon_vacancy' ) ); ?>">
					<?php esc_html_e( 'View Vacancies →', 'apprenticeship-connector' ); ?>
				</a>
			</div>

		</div>
		<?php
	}
}
