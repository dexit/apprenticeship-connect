/**
 * API utilities for making WordPress AJAX requests
 *
 * @package ApprenticeshipConnect
 */

import apiFetch from '@wordpress/api-fetch';

/**
 * Make a WordPress admin-ajax request
 *
 * @param {string} action  - WordPress action hook
 * @param {Object} data    - Additional data to send
 * @return {Promise} Promise resolving to the response
 */
export async function adminAjax(action, data = {}) {
	const formData = new FormData();
	formData.append('action', action);
	formData.append('nonce', window.apprcoAjax?.nonce || '');

	// Append additional data
	Object.keys(data).forEach((key) => {
		formData.append(key, data[key]);
	});

	try {
		const response = await fetch(window.apprcoAjax?.ajaxurl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		});

		const json = await response.json();

		if (!json.success) {
			throw new Error(json.data || 'Request failed');
		}

		return json.data;
	} catch (error) {
		console.error('AJAX Error:', error);
		throw error;
	}
}

/**
 * Make a REST API request
 *
 * @param {string} path    - API endpoint path
 * @param {Object} options - Fetch options
 * @return {Promise} Promise resolving to the response
 */
export async function restApi(path, options = {}) {
	try {
		return await apiFetch({
			path: `/apprco/v1${path}`,
			...options,
		});
	} catch (error) {
		console.error('REST API Error:', error);
		throw error;
	}
}

/**
 * Get localized strings
 *
 * @return {Object} Localized strings
 */
export function getStrings() {
	return window.apprcoAjax?.strings || {};
}
