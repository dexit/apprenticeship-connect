<?php
/**
 * Admin Dashboard page.
 *
 * @package ApprenticeshipConnector\Admin
 */

namespace ApprenticeshipConnector\Admin;

use ApprenticeshipConnector\Core\Database;

class Dashboard {

	public static function render(): void {
		global $wpdb;

		$vacancy_count = wp_count_posts( 'appcon_vacancy' )->publish ?? 0;
		$job_count     = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::get_jobs_table() );
		$last_run      = $wpdb->get_row(
			'SELECT * FROM ' . Database::get_runs_table() . ' ORDER BY created_at DESC LIMIT 1',
			ARRAY_A
		);

		?>
		<div class="wrap appcon-dashboard">
			<h1><?php esc_html_e( 'Apprenticeship Connector', 'apprenticeship-connector' ); ?></h1>

			<div class="appcon-stats">
				<div class="appcon-stat-card">
					<span class="appcon-stat-number"><?php echo esc_html( number_format_i18n( (int) $vacancy_count ) ); ?></span>
					<span class="appcon-stat-label"><?php esc_html_e( 'Published Vacancies', 'apprenticeship-connector' ); ?></span>
				</div>
				<div class="appcon-stat-card">
					<span class="appcon-stat-number"><?php echo esc_html( number_format_i18n( (int) $job_count ) ); ?></span>
					<span class="appcon-stat-label"><?php esc_html_e( 'Import Jobs', 'apprenticeship-connector' ); ?></span>
				</div>
				<?php if ( $last_run ) : ?>
				<div class="appcon-stat-card">
					<span class="appcon-stat-number appcon-status appcon-status--<?php echo esc_attr( $last_run['status'] ); ?>">
						<?php echo esc_html( ucfirst( $last_run['status'] ) ); ?>
					</span>
					<span class="appcon-stat-label"><?php esc_html_e( 'Last Run Status', 'apprenticeship-connector' ); ?></span>
				</div>
				<?php endif; ?>
			</div>

			<div id="appcon-admin-root" data-page="dashboard"></div>
		</div>
		<?php
	}
}
