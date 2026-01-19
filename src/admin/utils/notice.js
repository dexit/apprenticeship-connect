/**
 * Notice utilities for displaying admin messages
 *
 * @package ApprenticeshipConnect
 */

/**
 * Show a notice message
 *
 * @param {string} message - Message to display
 * @param {string} type    - Notice type (success, error, warning, info)
 */
export function showNotice(message, type = 'success') {
	// Remove existing notices
	const existing = document.querySelectorAll('.apprco-notice');
	existing.forEach((notice) => notice.remove());

	// Create new notice
	const notice = document.createElement('div');
	notice.className = `apprco-notice apprco-notice-${type}`;
	notice.textContent = message;

	// Insert after the first h1 in .wrap
	const heading = document.querySelector('.wrap h1');
	if (heading) {
		heading.insertAdjacentElement('afterend', notice);
	}

	// Auto-hide after 5 seconds
	setTimeout(() => {
		notice.style.opacity = '0';
		notice.style.transition = 'opacity 0.3s ease';
		setTimeout(() => notice.remove(), 300);
	}, 5000);
}

/**
 * Show an error notice
 *
 * @param {string|Error} error - Error message or Error object
 */
export function showError(error) {
	const message = error instanceof Error ? error.message : error;
	showNotice(message, 'error');
}

/**
 * Show a success notice
 *
 * @param {string} message - Success message
 */
export function showSuccess(message) {
	showNotice(message, 'success');
}
