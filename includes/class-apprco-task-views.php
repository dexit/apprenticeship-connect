<?php
/**
 * Task Views Class
 *
 * Handles UI rendering for Import Tasks (List and Editor).
 * Provides a clean separation between logic and presentation.
 *
 * @package ApprenticeshipConnect
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Apprco_Task_Views
 */
class Apprco_Task_Views {

	/**
	 * Render the tasks list view
	 *
	 * @param array $tasks         List of all tasks.
	 * @param array $scheduled_map Map of scheduled tasks by ID.
	 */
	public static function render_list( array $tasks, array $scheduled_map ): void {
		?>
		<div class="wrap apprco-import-tasks">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Import Tasks', 'apprenticeship-connect' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'apprenticeship-connect' ); ?></a>
			<hr class="wp-header-end">

			<p class="description"><?php esc_html_e( 'Configure import tasks to fetch apprenticeship vacancies from external APIs. Each task can have its own API configuration, field mappings, and schedule.', 'apprenticeship-connect' ); ?></p>

			<form id="apprco-bulk-actions-form" method="post">
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="bulk_action" id="bulk-action-selector-top">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'apprenticeship-connect' ); ?></option>
							<option value="activate"><?php esc_html_e( 'Activate', 'apprenticeship-connect' ); ?></option>
							<option value="deactivate"><?php esc_html_e( 'Deactivate', 'apprenticeship-connect' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'apprenticeship-connect' ); ?></option>
						</select>
						<button type="button" id="doaction" class="button action"><?php esc_html_e( 'Apply', 'apprenticeship-connect' ); ?></button>
					</div>
					<br class="clear">
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></td>
							<th scope="col" class="column-name" style="width: 20%;"><?php esc_html_e( 'Name', 'apprenticeship-connect' ); ?></th>
							<th scope="col" class="column-status" style="width: 10%;"><?php esc_html_e( 'Status', 'apprenticeship-connect' ); ?></th>
							<th scope="col" class="column-schedule" style="width: 15%;"><?php esc_html_e( 'Schedule', 'apprenticeship-connect' ); ?></th>
							<th scope="col" class="column-next-run" style="width: 15%;"><?php esc_html_e( 'Next Run', 'apprenticeship-connect' ); ?></th>
							<th scope="col" class="column-last-run" style="width: 15%;"><?php esc_html_e( 'Last Run', 'apprenticeship-connect' ); ?></th>
							<th scope="col" class="column-stats" style="width: 10%;"><?php esc_html_e( 'Results', 'apprenticeship-connect' ); ?></th>
							<th scope="col" class="column-actions" style="width: 15%;"><?php esc_html_e( 'Actions', 'apprenticeship-connect' ); ?></th>
						</tr>
					</thead>
					<tbody id="apprco-tasks-list">
						<?php if ( empty( $tasks ) ) : ?>
							<tr><td colspan="8"><?php esc_html_e( 'No import tasks found. Create one to get started.', 'apprenticeship-connect' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $tasks as $task ) : ?>
								<?php
								$task_id = $task['id'];
								$is_scheduled = $task['schedule_enabled'] && $task['status'] === 'active';
								$next_run = $scheduled_map[ $task_id ] ?? null;
								?>
								<tr data-task-id="<?php echo esc_attr( $task_id ); ?>">
									<th scope="row" class="check-column">
										<input type="checkbox" name="task_ids[]" value="<?php echo esc_attr( $task_id ); ?>">
									</th>
									<td class="column-name">
										<strong>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks&action=edit&task_id=' . $task_id ) ); ?>">
												<?php echo esc_html( $task['name'] ); ?>
											</a>
										</strong>
										<?php if ( $task['description'] ) : ?>
											<br><span class="description"><?php echo esc_html( wp_trim_words( $task['description'], 10 ) ); ?></span>
										<?php endif; ?>
									</td>
									<td class="column-status">
										<span class="apprco-badge apprco-badge-<?php echo esc_attr( $task['status'] ); ?>">
											<?php echo esc_html( ucfirst( $task['status'] ) ); ?>
										</span>
									</td>
									<td class="column-schedule">
										<?php if ( $task['schedule_enabled'] ) : ?>
											<span class="dashicons dashicons-calendar-alt" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
											<?php echo esc_html( ucfirst( $task['schedule_frequency'] ) ); ?>
											<br><small><?php echo esc_html( $task['schedule_time'] ); ?></small>
										<?php else : ?>
											<span class="description"><?php esc_html_e( 'Manual Only', 'apprenticeship-connect' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="column-next-run">
										<?php if ( $next_run ) : ?>
											<?php echo esc_html( $next_run['next_run_human'] ); ?>
											<br><small><?php echo esc_html( $next_run['method'] ); ?></small>
										<?php elseif ( $is_scheduled ) : ?>
											<span style="color: #dba617;"><?php esc_html_e( 'Pending...', 'apprenticeship-connect' ); ?></span>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
									<td class="column-last-run">
										<?php if ( $task['last_run_at'] ) : ?>
											<?php echo esc_html( human_time_diff( strtotime( $task['last_run_at'] ) ) ); ?> ago
											<br><span class="apprco-badge apprco-badge-<?php echo $task['last_run_status'] === 'success' ? 'success' : 'warning'; ?>">
												<?php echo esc_html( $task['last_run_status'] ); ?>
											</span>
										<?php else : ?>
											<?php esc_html_e( 'Never', 'apprenticeship-connect' ); ?>
										<?php endif; ?>
									</td>
									<td class="column-stats">
										<?php if ( $task['last_run_at'] ) : ?>
											<span title="<?php esc_attr_e( 'Fetched', 'apprenticeship-connect' ); ?>" style="color: #2271b1; font-weight: 600;"><?php echo esc_html( $task['last_run_fetched'] ); ?></span>
											<br>
											<span title="<?php esc_attr_e( 'Created', 'apprenticeship-connect' ); ?>" style="color: green;">+<?php echo esc_html( $task['last_run_created'] ); ?></span>
											/
											<span title="<?php esc_attr_e( 'Updated', 'apprenticeship-connect' ); ?>" style="color: #646970;">~<?php echo esc_html( $task['last_run_updated'] ); ?></span>
											<?php if ( $task['last_run_errors'] > 0 ) : ?>
												<br><span style="color: red;">! <?php echo esc_html( $task['last_run_errors'] ); ?></span>
											<?php endif; ?>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
									<td class="column-actions">
										<button type="button" class="button button-small apprco-run-task" data-task-id="<?php echo esc_attr( $task_id ); ?>" <?php echo $task['status'] !== 'active' ? 'disabled' : ''; ?>>
											<?php esc_html_e( 'Run Now', 'apprenticeship-connect' ); ?>
										</button>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks&action=edit&task_id=' . $task_id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'apprenticeship-connect' ); ?>
										</a>
										<button type="button" class="button button-small button-link-delete apprco-delete-task" data-task-id="<?php echo esc_attr( $task_id ); ?>">
											<?php esc_html_e( 'Delete', 'apprenticeship-connect' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Run task
			$('.apprco-run-task').on('click', function() {
				var $btn = $(this);
				var taskId = $btn.data('task-id');
				$btn.prop('disabled', true).text('Running...');

				$.post(apprcoAjax.ajaxurl, {
					action: 'apprco_run_task',
					nonce: apprcoAjax.nonce,
					task_id: taskId
				}, function(response) {
					if (response.success) {
						alert('Import completed!\n' + response.data.message);
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
					$btn.prop('disabled', false).text('Run Now');
				});
			});

			// Delete task
			$('.apprco-delete-task').on('click', function() {
				if (!confirm('Are you sure you want to delete this task?')) return;

				var taskId = $(this).data('task-id');
				$.post(apprcoAjax.ajaxurl, {
					action: 'apprco_delete_task',
					nonce: apprcoAjax.nonce,
					task_id: taskId
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
				});
			});

			// Select all checkbox
			$('#cb-select-all-1').on('change', function() {
				$('input[name="task_ids[]"]').prop('checked', $(this).is(':checked'));
			});

			// Bulk actions
			$('#doaction').on('click', function() {
				var action = $('#bulk-action-selector-top').val();
				if (action === '-1') return;

				var taskIds = [];
				$('input[name="task_ids[]"]:checked').each(function() {
					taskIds.push($(this).val());
				});

				if (taskIds.length === 0) {
					alert('Please select at least one task.');
					return;
				}

				if (action === 'delete' && !confirm('Are you sure you want to delete selected tasks?')) return;

				$.post(apprcoAjax.ajaxurl, {
					action: 'apprco_bulk_task_action',
					nonce: apprcoAjax.nonce,
					bulk_action: action,
					task_ids: taskIds
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the task editor view
	 *
	 * @param array $task   Task data.
	 * @param bool  $is_new Whether this is a new task.
	 */
	public static function render_editor( array $task, bool $is_new ): void {
		?>
		<div class="wrap apprco-task-editor">
			<h1>
				<?php echo $is_new ? esc_html__( 'Add New Import Task', 'apprenticeship-connect' ) : esc_html__( 'Edit Import Task', 'apprenticeship-connect' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=apprco-import-tasks' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to List', 'apprenticeship-connect' ); ?></a>
			</h1>

			<form id="apprco-task-form" method="post">
				<input type="hidden" name="task_id" value="<?php echo esc_attr( $task['id'] ); ?>">
				<?php wp_nonce_field( 'apprco_admin_nonce', 'apprco_nonce' ); ?>

				<div class="apprco-task-sections">
					<!-- Basic Info -->
					<div class="apprco-section">
						<h2><?php esc_html_e( 'Basic Information', 'apprenticeship-connect' ); ?></h2>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="task_name"><?php esc_html_e( 'Task Name', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="text" id="task_name" name="name" value="<?php echo esc_attr( $task['name'] ); ?>" class="regular-text" required>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="task_description"><?php esc_html_e( 'Description', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<textarea id="task_description" name="description" rows="2" class="large-text"><?php echo esc_textarea( $task['description'] ); ?></textarea>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="task_status"><?php esc_html_e( 'Status', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<select id="task_status" name="status">
										<option value="draft" <?php selected( $task['status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'apprenticeship-connect' ); ?></option>
										<option value="active" <?php selected( $task['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'apprenticeship-connect' ); ?></option>
										<option value="inactive" <?php selected( $task['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'apprenticeship-connect' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Only active tasks can be run or scheduled.', 'apprenticeship-connect' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<!-- API Configuration -->
					<div class="apprco-section apprco-section-api">
						<div class="apprco-section-header">
							<h2><?php esc_html_e( 'API Configuration', 'apprenticeship-connect' ); ?></h2>
							<span class="apprco-v2-badge">v2 API Ready</span>
						</div>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="api_base_url"><?php esc_html_e( 'API Base URL', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="url" id="api_base_url" name="api_base_url" value="<?php echo esc_attr( $task['api_base_url'] ); ?>" class="regular-text" required>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="api_endpoint"><?php esc_html_e( 'API Endpoint', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="text" id="api_endpoint" name="api_endpoint" value="<?php echo esc_attr( $task['api_endpoint'] ); ?>" class="regular-text">
									<p class="description"><?php esc_html_e( 'Path appended to base URL (e.g., /vacancy)', 'apprenticeship-connect' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="api_auth_key"><?php esc_html_e( 'Auth Header Name', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="text" id="api_auth_key" name="api_auth_key" value="<?php echo esc_attr( $task['api_auth_key'] ); ?>" class="regular-text">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="api_auth_value"><?php esc_html_e( 'API Key / Subscription Key', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="password" id="api_auth_value" name="api_auth_value" value="<?php echo esc_attr( $task['api_auth_value'] ); ?>" class="regular-text" autocomplete="new-password">
									<p class="description"><?php esc_html_e( 'Your API subscription key', 'apprenticeship-connect' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="api_headers"><?php esc_html_e( 'Additional Headers', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<textarea id="api_headers" name="api_headers" rows="3" class="large-text code"><?php echo esc_textarea( wp_json_encode( $task['api_headers'], JSON_PRETTY_PRINT ) ); ?></textarea>
									<p class="description"><?php esc_html_e( 'JSON object of headers (e.g., {"X-Version": "2"})', 'apprenticeship-connect' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="api_params"><?php esc_html_e( 'Query Parameters', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<textarea id="api_params" name="api_params" rows="3" class="large-text code"><?php echo esc_textarea( wp_json_encode( $task['api_params'], JSON_PRETTY_PRINT ) ); ?></textarea>
									<p class="description"><?php esc_html_e( 'JSON object of query params (e.g., {"Sort": "AgeDesc", "Ukprn": "12345678"})', 'apprenticeship-connect' ); ?></p>
								</td>
							</tr>
						</table>

						<div class="apprco-test-drive">
							<h3><?php esc_html_e( 'Connection Test Drive', 'apprenticeship-connect' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Verify your credentials and see live data before saving.', 'apprenticeship-connect' ); ?></p>
							<p>
								<button type="button" id="apprco-test-connection" class="button button-large"><?php esc_html_e( 'Test Connection & Fetch Sample', 'apprenticeship-connect' ); ?></button>
								<span id="apprco-test-status"></span>
							</p>
							<div id="apprco-test-result" class="apprco-test-result" style="display:none;">
								<h4><?php esc_html_e( 'API Response', 'apprenticeship-connect' ); ?></h4>
								<div id="apprco-test-summary"></div>
								<h4><?php esc_html_e( 'Available Fields (click to copy path)', 'apprenticeship-connect' ); ?></h4>
								<div id="apprco-available-fields"></div>
								<h4><?php esc_html_e( 'Sample Data (First 10 Records)', 'apprenticeship-connect' ); ?></h4>
								<div id="apprco-sample-data" style="max-height: 400px; overflow: auto;"></div>
							</div>
						</div>
					</div>

					<!-- Response Parsing -->
					<div class="apprco-section">
						<h2><?php esc_html_e( 'Response Parsing', 'apprenticeship-connect' ); ?></h2>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="data_path"><?php esc_html_e( 'Data Path', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="text" id="data_path" name="data_path" value="<?php echo esc_attr( $task['data_path'] ); ?>" class="regular-text">
									<p class="description"><?php esc_html_e( 'JSONPath to the array of items (e.g., "vacancies" or "data.items")', 'apprenticeship-connect' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="total_path"><?php esc_html_e( 'Total Count Path', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="text" id="total_path" name="total_path" value="<?php echo esc_attr( $task['total_path'] ); ?>" class="regular-text">
									<p class="description"><?php esc_html_e( 'JSONPath to total record count (e.g., "total")', 'apprenticeship-connect' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="unique_id_field"><?php esc_html_e( 'Unique ID Field', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="text" id="unique_id_field" name="unique_id_field" value="<?php echo esc_attr( $task['unique_id_field'] ); ?>" class="regular-text">
									<p class="description"><?php esc_html_e( 'Field to identify unique records (prevents duplicates)', 'apprenticeship-connect' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="page_param"><?php esc_html_e( 'Page Parameter', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="text" id="page_param" name="page_param" value="<?php echo esc_attr( $task['page_param'] ); ?>" class="regular-text">
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="page_size"><?php esc_html_e( 'Page Size', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="number" id="page_size" name="page_size" value="<?php echo esc_attr( $task['page_size'] ); ?>" min="10" max="500">
								</td>
							</tr>
						</table>
					</div>

					<!-- Field Mappings -->
					<div class="apprco-section">
						<h2><?php esc_html_e( 'Field Mappings', 'apprenticeship-connect' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Map API response fields to WordPress post fields and meta. Use dot notation for nested fields (e.g., addresses[0].postcode).', 'apprenticeship-connect' ); ?></p>

						<table class="wp-list-table widefat fixed" id="apprco-field-mappings">
							<thead>
								<tr>
									<th style="width: 40%;"><?php esc_html_e( 'Target Field (WordPress)', 'apprenticeship-connect' ); ?></th>
									<th style="width: 50%;"><?php esc_html_e( 'Source Field (API)', 'apprenticeship-connect' ); ?></th>
									<th style="width: 10%;"></th>
								</tr>
							</thead>
							<tbody id="apprco-mappings-body">
								<?php foreach ( $task['field_mappings'] as $target => $source ) : ?>
									<tr>
										<td><input type="text" name="mapping_target[]" value="<?php echo esc_attr( $target ); ?>" class="widefat"></td>
										<td><input type="text" name="mapping_source[]" value="<?php echo esc_attr( $source ); ?>" class="widefat"></td>
										<td><button type="button" class="button button-small apprco-remove-mapping">&times;</button></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p>
							<button type="button" id="apprco-add-mapping" class="button"><?php esc_html_e( 'Add Mapping', 'apprenticeship-connect' ); ?></button>
							<button type="button" id="apprco-reset-mappings" class="button"><?php esc_html_e( 'Reset to Defaults', 'apprenticeship-connect' ); ?></button>
						</p>
					</div>

					<!-- ETL Transforms -->
					<div class="apprco-section">
						<h2><?php esc_html_e( 'ETL Transforms (Advanced)', 'apprenticeship-connect' ); ?></h2>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="transforms_enabled"><?php esc_html_e( 'Enable Transforms', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="checkbox" id="transforms_enabled" name="transforms_enabled" value="1" <?php checked( $task['transforms_enabled'], 1 ); ?>>
									<label for="transforms_enabled"><?php esc_html_e( 'Apply custom PHP transforms to each record', 'apprenticeship-connect' ); ?></label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="transforms_code"><?php esc_html_e( 'Transform Code', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<textarea id="transforms_code" name="transforms_code" rows="10" class="large-text code" placeholder="// $item contains the API record array&#10;// Modify $item as needed&#10;// Example:&#10;// $item['customField'] = strtoupper($item['title']);"><?php echo esc_textarea( $task['transforms_code'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'PHP code to transform each record. The $item variable contains the API record.', 'apprenticeship-connect' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<!-- Schedule -->
					<div class="apprco-section">
						<h2><?php esc_html_e( 'Schedule', 'apprenticeship-connect' ); ?></h2>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="schedule_enabled"><?php esc_html_e( 'Enable Schedule', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<input type="checkbox" id="schedule_enabled" name="schedule_enabled" value="1" <?php checked( $task['schedule_enabled'], 1 ); ?>>
									<label for="schedule_enabled"><?php esc_html_e( 'Run this task automatically on a schedule', 'apprenticeship-connect' ); ?></label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="schedule_frequency"><?php esc_html_e( 'Frequency', 'apprenticeship-connect' ); ?></label></th>
								<td>
									<select id="schedule_frequency" name="schedule_frequency">
										<option value="hourly" <?php selected( $task['schedule_frequency'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'apprenticeship-connect' ); ?></option>
										<option value="twicedaily" <?php selected( $task['schedule_frequency'], 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'apprenticeship-connect' ); ?></option>
										<option value="daily" <?php selected( $task['schedule_frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'apprenticeship-connect' ); ?></option>
										<option value="weekly" <?php selected( $task['schedule_frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'apprenticeship-connect' ); ?></option>
									</select>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php echo $is_new ? esc_html__( 'Create Task', 'apprenticeship-connect' ) : esc_html__( 'Save Changes', 'apprenticeship-connect' ); ?></button>
					<?php if ( ! $is_new && $task['status'] === 'active' ) : ?>
						<button type="button" id="apprco-run-task-now" class="button"><?php esc_html_e( 'Run Task Now', 'apprenticeship-connect' ); ?></button>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<style>
			.apprco-task-sections { max-width: 1000px; }
			.apprco-section { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
			.apprco-section-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #eee; margin-bottom: 15px; padding-bottom: 10px; }
			.apprco-section h2 { margin: 0; }
			.apprco-v2-badge { background: #e7f5ec; color: #1e8a44; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; text-transform: uppercase; border: 1px solid #1e8a44; }
			.apprco-test-drive { background: #f0f6fb; border: 1px solid #c3d9ef; padding: 20px; margin-top: 20px; border-radius: 4px; }
			.apprco-test-drive h3 { margin-top: 0; color: #2271b1; }
			.apprco-test-result { background: #fff; padding: 15px; margin-top: 15px; border: 1px solid #ddd; border-radius: 4px; }
			#apprco-available-fields { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 15px; }
			#apprco-available-fields .field-tag { background: #f0f0f1; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-family: monospace; font-size: 12px; border: 1px solid #dcdcde; transition: all 0.2s; }
			#apprco-available-fields .field-tag:hover { background: #2271b1; color: #fff; border-color: #2271b1; }
			#apprco-sample-data table { font-size: 12px; }
			#apprco-field-mappings input { font-family: monospace; }
			.apprco-badge { padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; text-transform: uppercase; }
			.apprco-badge-active { background: #00a32a; color: #fff; }
			.apprco-badge-inactive { background: #dba617; color: #fff; }
			.apprco-badge-draft { background: #72777c; color: #fff; }
			.apprco-badge-success { background: #00a32a; color: #fff; }
			.apprco-badge-warning { background: #dba617; color: #fff; }
		</style>

		<script>
		jQuery(document).ready(function($) {
			var defaultMappings = <?php echo wp_json_encode( Apprco_Import_Tasks::get_default_field_mappings() ); ?>;

			// Test connection
			$('#apprco-test-connection').on('click', function() {
				var $btn = $(this);
				var $status = $('#apprco-test-status');
				var $result = $('#apprco-test-result');

				$btn.prop('disabled', true);
				$status.text('Testing connection...');
				$result.hide();

				// Gather current form values
				var formData = {
					action: 'apprco_test_task_connection',
					nonce: $('#apprco_nonce').val(),
					api_base_url: $('#api_base_url').val(),
					api_endpoint: $('#api_endpoint').val(),
					api_auth_key: $('#api_auth_key').val(),
					api_auth_value: $('#api_auth_value').val(),
					api_headers: $('#api_headers').val(),
					api_params: $('#api_params').val(),
					data_path: $('#data_path').val(),
					total_path: $('#total_path').val()
				};

				$.post(apprcoAjax.ajaxurl, formData, function(response) {
					$btn.prop('disabled', false);

					if (response.success) {
						$status.html('<span style="color: green;">Connected successfully!</span>');
						$result.show();

						var data = response.data;
						$('#apprco-test-summary').html(
							'<p><strong>Total Records:</strong> ' + data.total +
							' | <strong>Fetched:</strong> ' + data.fetched +
							' | <strong>Response Keys:</strong> ' + data.response_keys.join(', ') + '</p>'
						);

						// Show available fields
						var fieldsHtml = '';
						if (data.available_fields && data.available_fields.length > 0) {
							data.available_fields.forEach(function(field) {
								fieldsHtml += '<span class="field-tag" data-field="' + field + '">' + field + '</span>';
							});
						}
						$('#apprco-available-fields').html(fieldsHtml);

						// Show sample data as table
						if (data.sample && data.sample.length > 0) {
							var tableHtml = '<table class="wp-list-table widefat striped"><thead><tr><th>#</th>';
							var keys = Object.keys(data.sample[0]).slice(0, 8);
							keys.forEach(function(key) {
								tableHtml += '<th>' + key + '</th>';
							});
							tableHtml += '</tr></thead><tbody>';

							data.sample.forEach(function(item, idx) {
								tableHtml += '<tr><td>' + (idx + 1) + '</td>';
								keys.forEach(function(key) {
									var val = item[key];
									if (typeof val === 'object') val = JSON.stringify(val).substring(0, 50) + '...';
									if (typeof val === 'string' && val.length > 50) val = val.substring(0, 50) + '...';
									tableHtml += '<td>' + (val || '') + '</td>';
								});
								tableHtml += '</tr>';
							});
							tableHtml += '</tbody></table>';
							$('#apprco-sample-data').html(tableHtml);
						}
					} else {
						$status.html('<span style="color: red;">Error: ' + response.data.error + '</span>');
						if (response.data.raw_response) {
							$result.show();
							$('#apprco-test-summary').html('<pre>' + response.data.raw_response + '</pre>');
						}
					}
				}).fail(function() {
					$btn.prop('disabled', false);
					$status.html('<span style="color: red;">Request failed</span>');
				});
			});

			// Click field tag to copy
			$(document).on('click', '.field-tag', function() {
				var field = $(this).data('field');
				navigator.clipboard.writeText(field);
				$(this).css('background', '#00a32a').css('color', '#fff');
				setTimeout(function() {
					$('.field-tag').css('background', '').css('color', '');
				}, 500);
			});

			// Add mapping row
			$('#apprco-add-mapping').on('click', function() {
				$('#apprco-mappings-body').append(
					'<tr>' +
					'<td><input type="text" name="mapping_target[]" value="" class="widefat"></td>' +
					'<td><input type="text" name="mapping_source[]" value="" class="widefat"></td>' +
					'<td><button type="button" class="button button-small apprco-remove-mapping">&times;</button></td>' +
					'</tr>'
				);
			});

			// Remove mapping row
			$(document).on('click', '.apprco-remove-mapping', function() {
				$(this).closest('tr').remove();
			});

			// Reset mappings to defaults
			$('#apprco-reset-mappings').on('click', function() {
				if (!confirm('Reset all field mappings to defaults?')) return;

				var html = '';
				for (var target in defaultMappings) {
					html += '<tr>' +
						'<td><input type="text" name="mapping_target[]" value="' + target + '" class="widefat"></td>' +
						'<td><input type="text" name="mapping_source[]" value="' + defaultMappings[target] + '" class="widefat"></td>' +
						'<td><button type="button" class="button button-small apprco-remove-mapping">&times;</button></td>' +
						'</tr>';
				}
				$('#apprco-mappings-body').html(html);
			});

			// Save task via AJAX
			$('#apprco-task-form').on('submit', function(e) {
				e.preventDefault();

				var $form = $(this);
				var $submitBtn = $form.find('button[type="submit"]');

				// Build field mappings
				var mappings = {};
				$('input[name="mapping_target[]"]').each(function(idx) {
					var target = $(this).val().trim();
					var source = $('input[name="mapping_source[]"]').eq(idx).val().trim();
					if (target && source) {
						mappings[target] = source;
					}
				});

				var formData = {
					action: 'apprco_save_task',
					nonce: $('#apprco_nonce').val(),
					task_id: $('input[name="task_id"]').val(),
					name: $('#task_name').val(),
					description: $('#task_description').val(),
					status: $('#task_status').val(),
					api_base_url: $('#api_base_url').val(),
					api_endpoint: $('#api_endpoint').val(),
					api_auth_key: $('#api_auth_key').val(),
					api_auth_value: $('#api_auth_value').val(),
					api_headers: $('#api_headers').val(),
					api_params: $('#api_params').val(),
					data_path: $('#data_path').val(),
					total_path: $('#total_path').val(),
					unique_id_field: $('#unique_id_field').val(),
					page_param: $('#page_param').val(),
					page_size: $('#page_size').val(),
					field_mappings: JSON.stringify(mappings),
					transforms_enabled: $('#transforms_enabled').is(':checked') ? 1 : 0,
					transforms_code: $('#transforms_code').val(),
					schedule_enabled: $('#schedule_enabled').is(':checked') ? 1 : 0,
					schedule_frequency: $('#schedule_frequency').val()
				};

				$submitBtn.prop('disabled', true).text('Saving...');

				$.post(apprcoAjax.ajaxurl, formData, function(response) {
					if (response.success) {
						alert('Task saved successfully!');
						if (!formData.task_id || formData.task_id == '0') {
							window.location.href = 'admin.php?page=apprco-import-tasks&action=edit&task_id=' + response.data.task_id;
						} else {
							$submitBtn.prop('disabled', false).text('Save Changes');
						}
					} else {
						alert('Error: ' + response.data);
						$submitBtn.prop('disabled', false).text('Save Changes');
					}
				}).fail(function() {
					alert('Request failed');
					$submitBtn.prop('disabled', false).text('Save Changes');
				});
			});

			// Run task now
			$('#apprco-run-task-now').on('click', function() {
				var $btn = $(this);
				var taskId = $('input[name="task_id"]').val();

				if (!taskId || taskId == '0') {
					alert('Please save the task first.');
					return;
				}

				$btn.prop('disabled', true).text('Running...');

				$.post(apprcoAjax.ajaxurl, {
					action: 'apprco_run_task',
					nonce: apprcoAjax.nonce,
					task_id: taskId
				}, function(response) {
					if (response.success) {
						alert('Import completed!\n' + response.data.message);
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
					$btn.prop('disabled', false).text('Run Task Now');
				});
			});
		});
		</script>
		<?php
	}
}
