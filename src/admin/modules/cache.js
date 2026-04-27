/**
 * Cache management functionality
 *
 * @package ApprenticeshipConnect
 */

import { adminAjax } from '../utils/api';
import { showNotice, showError } from '../utils/notice';

/**
 * Initialize cache management functionality
 */
export function initClearCache() {
	const clearButton = document.getElementById('apprco-clear-cache');
	if (clearButton) {
		clearButton.addEventListener('click', handleClearCache);
	}
}

/**
 * Handle clear cache button click
 *
 * @param {Event} event - Click event
 */
async function handleClearCache(event) {
	event.preventDefault();

	const button = event.currentTarget;
	const originalText = button.textContent;

	try {
		button.disabled = true;
		button.textContent = 'Clearing...';

		const response = await adminAjax('apprco_clear_cache');
		showNotice(response, 'success');
	} catch (error) {
		showError(error);
	} finally {
		button.disabled = false;
		button.textContent = originalText;
	}
}
