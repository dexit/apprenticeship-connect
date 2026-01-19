/**
 * Logs page functionality
 *
 * @package ApprenticeshipConnect
 */

import { adminAjax, getStrings } from '../utils/api';
import { showNotice, showError } from '../utils/notice';

/**
 * Initialize logs page functionality
 */
export function initLogsPage() {
	initViewLogs();
	initCloseLogs();
	initRefreshLogs();
	initExportLogs();
	initClearLogs();
}

/**
 * Initialize view logs functionality
 */
function initViewLogs() {
	document.addEventListener('click', async (event) => {
		if (!event.target.classList.contains('apprco-view-logs')) {
			return;
		}

		event.preventDefault();

		const importId = event.target.dataset.importId;
		const detailsElement = document.getElementById('apprco-log-details');
		const entriesElement = document.getElementById('apprco-log-entries');
		const importIdElement = document.getElementById('apprco-log-import-id');
		const strings = getStrings();

		if (entriesElement) {
			entriesElement.innerHTML = `<p>${strings.loading || 'Loading...'}</p>`;
		}

		if (detailsElement) {
			detailsElement.style.display = 'block';
		}

		if (importIdElement) {
			importIdElement.textContent = importId;
		}

		try {
			const logs = await adminAjax('apprco_get_logs', { import_id: importId });

			if (logs && logs.length > 0 && entriesElement) {
				const html = logs
					.map(
						(log) => `
					<div class="apprco-log-entry ${log.log_level}">
						<span class="log-time">${log.created_at}</span>
						<span class="log-level">${log.log_level}</span>
						<span class="log-message">${log.message}</span>
					</div>
				`
					)
					.join('');
				entriesElement.innerHTML = html;
			} else if (entriesElement) {
				entriesElement.innerHTML = '<p>No log entries found for this import.</p>';
			}
		} catch (error) {
			if (entriesElement) {
				entriesElement.innerHTML = '<p>Error loading logs.</p>';
			}
		}
	});
}

/**
 * Initialize close logs functionality
 */
function initCloseLogs() {
	const closeButton = document.getElementById('apprco-close-logs');
	if (closeButton) {
		closeButton.addEventListener('click', () => {
			const detailsElement = document.getElementById('apprco-log-details');
			if (detailsElement) {
				detailsElement.style.display = 'none';
			}
		});
	}
}

/**
 * Initialize refresh logs functionality
 */
function initRefreshLogs() {
	const refreshButton = document.getElementById('apprco-refresh-logs');
	if (refreshButton) {
		refreshButton.addEventListener('click', () => {
			window.location.reload();
		});
	}
}

/**
 * Initialize export logs functionality
 */
function initExportLogs() {
	const exportButton = document.getElementById('apprco-export-logs');
	if (exportButton) {
		exportButton.addEventListener('click', async (event) => {
			event.preventDefault();

			try {
				const response = await adminAjax('apprco_export_logs');

				// Create download
				const blob = new Blob([response.csv], { type: 'text/csv' });
				const url = window.URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = `apprco-logs-${new Date().toISOString().slice(0, 10)}.csv`;
				document.body.appendChild(a);
				a.click();
				window.URL.revokeObjectURL(url);
				document.body.removeChild(a);
			} catch (error) {
				showError(error);
			}
		});
	}
}

/**
 * Initialize clear logs functionality
 */
function initClearLogs() {
	const clearButton = document.getElementById('apprco-clear-logs');
	if (clearButton) {
		clearButton.addEventListener('click', async (event) => {
			event.preventDefault();

			const strings = getStrings();
			if (!confirm(strings.confirm_clear || 'Are you sure?')) {
				return;
			}

			const button = event.currentTarget;
			button.disabled = true;

			try {
				const response = await adminAjax('apprco_clear_logs');
				showNotice(response, 'success');

				setTimeout(() => {
					window.location.reload();
				}, 1500);
			} catch (error) {
				showError(error);
				button.disabled = false;
			}
		});
	}
}
