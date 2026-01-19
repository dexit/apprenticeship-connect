/**
 * Setup wizard functionality
 *
 * @package ApprenticeshipConnect
 */

/**
 * Initialize setup wizard functionality
 */
export function initSetupWizard() {
	initAutoSave();
	initRestoreData();
	initPageToggle();
	initNoVacancyImageToggle();
	initBeforeUnload();
}

/**
 * Initialize auto-save form data to localStorage
 */
function initAutoSave() {
	const fields = document.querySelectorAll('.apprco-setup-step input, .apprco-setup-step select, .apprco-setup-step textarea');

	fields.forEach((field) => {
		field.addEventListener('change', () => {
			const form = field.closest('form');
			if (form) {
				const formData = new FormData(form);
				const data = Object.fromEntries(formData.entries());
				localStorage.setItem('apprco_setup_form_data', JSON.stringify(data));
			}
		});
	});
}

/**
 * Restore form data from localStorage
 */
function initRestoreData() {
	const savedData = localStorage.getItem('apprco_setup_form_data');
	if (!savedData) {
		return;
	}

	try {
		const data = JSON.parse(savedData);
		const form = document.querySelector('.apprco-setup-step form');

		if (form) {
			Object.entries(data).forEach(([key, value]) => {
				if (key === 'step' || key === 'apprco_setup_nonce' || key === '_wp_http_referer') {
					return;
				}

				const field = form.querySelector(`[name="${key}"]`);
				if (field) {
					if (field.type === 'checkbox') {
						field.checked = value === '1';
					} else {
						field.value = value;
					}
				}
			});
		}
	} catch (error) {
		console.error('Error restoring form data:', error);
	}

	// Clear saved data when complete
	if (window.location.search.includes('step=5')) {
		localStorage.removeItem('apprco_setup_form_data');
	}
}

/**
 * Initialize page creation toggle
 */
function initPageToggle() {
	const checkbox = document.getElementById('create_page');
	if (checkbox) {
		checkbox.addEventListener('change', (event) => {
			const checked = event.target.checked;
			const titleField = document.getElementById('page_title');
			const slugField = document.getElementById('page_slug');

			if (titleField) titleField.disabled = !checked;
			if (slugField) slugField.disabled = !checked;
		});

		// Trigger on load
		const checked = checkbox.checked;
		const titleField = document.getElementById('page_title');
		const slugField = document.getElementById('page_slug');

		if (titleField) titleField.disabled = !checked;
		if (slugField) slugField.disabled = !checked;
	}
}

/**
 * Initialize no vacancy image toggle
 */
function initNoVacancyImageToggle() {
	const checkbox = document.getElementById('show_no_vacancy_image');
	if (checkbox) {
		const toggle = () => {
			const input = document.getElementById('no_vacancy_image');
			const button = document.getElementById('no_vacancy_image_button');
			const checked = checkbox.checked;

			if (input) input.disabled = !checked;
			if (button) button.disabled = !checked;
		};

		checkbox.addEventListener('change', toggle);
		toggle();
	}
}

/**
 * Initialize before unload warning
 */
function initBeforeUnload() {
	const form = document.querySelector('.apprco-setup-step form');
	if (!form) {
		return;
	}

	window.onbeforeunload = () => {
		return 'Are you sure you want to leave? Your progress will be saved.';
	};

	form.addEventListener('submit', () => {
		window.onbeforeunload = null;
	});

	const actionLinks = document.querySelectorAll('.apprco-setup-actions a');
	actionLinks.forEach((link) => {
		link.addEventListener('click', () => {
			window.onbeforeunload = null;
		});
	});
}
