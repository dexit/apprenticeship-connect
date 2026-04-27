/**
 * Manual sync functionality
 *
 * @package ApprenticeshipConnect
 */

import { adminAjax, getStrings } from '../utils/api';
import { showNotice, showError } from '../utils/notice';

/**
 * Initialize manual sync functionality
 */
export function initManualSync() {
	// Main sync button
	const syncButton = document.getElementById('apprco-manual-sync');
	if (syncButton) {
		syncButton.addEventListener('click', handleManualSync);
	}

	// Dashboard sync button
	const dashboardSyncButton = document.getElementById('apprco-dashboard-sync');
	if (dashboardSyncButton) {
		dashboardSyncButton.addEventListener('click', handleDashboardSync);
	}

	// Test & Sync button
	const testSyncButton = document.getElementById('apprco-test-and-sync');
	if (testSyncButton) {
		testSyncButton.addEventListener('click', handleTestAndSync);
	}
}

/**
 * Handle manual sync button click
 *
 * @param {Event} event - Click event
 */
async function handleManualSync(event) {
	event.preventDefault();

	const button = event.currentTarget;
	const originalText = button.textContent;
	const strings = getStrings();

	try {
		button.disabled = true;
		button.textContent = strings.syncing || 'Syncing...';

		const response = await adminAjax('apprco_manual_sync');
		showNotice(response.message, 'success');

		setTimeout(() => {
			window.location.reload();
		}, 2000);
	} catch (error) {
		showError(error);
	} finally {
		button.disabled = false;
		button.textContent = originalText;
	}
}

/**
 * Handle dashboard sync button click
 *
 * @param {Event} event - Click event
 */
async function handleDashboardSync(event) {
	event.preventDefault();

	const button = event.currentTarget;
	const originalText = button.textContent;
	const resultElement = document.getElementById('apprco-dashboard-result');
	const strings = getStrings();

	try {
		button.disabled = true;
		button.textContent = strings.syncing || 'Syncing...';

		if (resultElement) {
			resultElement.innerHTML = `<span class="apprco-loading">${strings.loading || 'Loading...'}</span>`;
		}

		const response = await adminAjax('apprco_manual_sync');

		if (resultElement) {
			resultElement.className = 'success';
			resultElement.innerHTML = response.message;
		}

		setTimeout(() => {
			window.location.reload();
		}, 2000);
	} catch (error) {
		if (resultElement) {
			resultElement.className = 'error';
			resultElement.innerHTML = error.message;
		}
	} finally {
		button.disabled = false;
		button.textContent = originalText;
	}
}

/**
 * Handle test and sync button click
 *
 * @param {Event} event - Click event
 */
async function handleTestAndSync(event) {
	event.preventDefault();

	const button = event.currentTarget;
	const originalText = button.textContent;
	const resultElement = document.getElementById('apprco-test-sync-result');

	const apiBaseUrl = document.getElementById('api_base_url')?.value;
	const apiKey = document.getElementById('api_subscription_key')?.value;
	const ukprn = document.getElementById('api_ukprn')?.value;

	try {
		button.disabled = true;
		button.textContent = 'Testing & Syncing...';

		if (resultElement) {
			resultElement.innerHTML = '<p style="color: #2271b1;">Testing API connection and syncing vacancies...</p>';
		}

		const response = await adminAjax('apprco_test_and_sync', {
			api_base_url: apiBaseUrl,
			api_subscription_key: apiKey,
			api_ukprn: ukprn,
		});

		if (resultElement) {
			resultElement.className = 'success';
			resultElement.innerHTML = `<p>${response.message}</p>`;
		}

		// Update stats
		const lastSyncElement = document.getElementById('apprco-last-sync');
		const totalVacanciesElement = document.getElementById('apprco-total-vacancies');

		if (lastSyncElement) {
			lastSyncElement.textContent = response.last_sync;
		}
		if (totalVacanciesElement) {
			totalVacanciesElement.textContent = response.total_vacancies;
		}

		// Save API settings
		await adminAjax('apprco_save_api_settings', {
			api_base_url: apiBaseUrl,
			api_subscription_key: apiKey,
			api_ukprn: ukprn,
		});
	} catch (error) {
		if (resultElement) {
			resultElement.className = 'error';
			resultElement.innerHTML = `<p>Error: ${error.message}</p>`;
		}
	} finally {
		button.disabled = false;
		button.textContent = originalText;
	}
}
