/**
 * API testing functionality
 *
 * @package ApprenticeshipConnect
 */

import { adminAjax, getStrings } from '../utils/api';
import { showNotice, showError } from '../utils/notice';

/**
 * Initialize API testing functionality
 */
export function initTestAPI() {
	const testButton = document.getElementById('apprco-test-api');
	if (testButton) {
		testButton.addEventListener('click', handleTestAPI);
	}
}

/**
 * Handle test API button click
 *
 * @param {Event} event - Click event
 */
async function handleTestAPI(event) {
	event.preventDefault();

	const button = event.currentTarget;
	const originalText = button.textContent;
	const strings = getStrings();

	try {
		button.disabled = true;
		button.textContent = strings.testing || 'Testing...';

		const response = await adminAjax('apprco_test_api');
		showNotice(response, 'success');
	} catch (error) {
		showError(error);
	} finally {
		button.disabled = false;
		button.textContent = originalText;
	}
}
