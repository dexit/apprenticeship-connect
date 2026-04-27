/**
 * Form validation functionality
 *
 * @package ApprenticeshipConnect
 */

import { showNotice } from '../utils/notice';

/**
 * Initialize form validation
 */
export function initFormValidation() {
	const forms = document.querySelectorAll('form');

	forms.forEach((form) => {
		form.addEventListener('submit', (event) => {
			const requiredFields = form.querySelectorAll('[required]');
			let isValid = true;

			requiredFields.forEach((field) => {
				const value = field.value.trim();

				if (!value) {
					field.classList.add('error');
					isValid = false;
				} else {
					field.classList.remove('error');
				}
			});

			if (!isValid) {
				event.preventDefault();
				showNotice('Please fill in all required fields.', 'error');
			}
		});
	});

	// Remove error class on input/change
	const fields = document.querySelectorAll('input, select, textarea');
	fields.forEach((field) => {
		field.addEventListener('input', () => {
			field.classList.remove('error');
		});
		field.addEventListener('change', () => {
			field.classList.remove('error');
		});
	});
}
