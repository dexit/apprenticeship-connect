<?php
/**
 * Import Jobs admin page – React app mount point.
 *
 * @package ApprenticeshipConnector\Admin
 */

namespace ApprenticeshipConnector\Admin;

class ImportJobsPage {

	public static function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Jobs', 'apprenticeship-connector' ); ?></h1>
			<div id="appcon-admin-root" data-page="import-jobs"></div>
		</div>
		<?php
	}
}
