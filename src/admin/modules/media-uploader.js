/**
 * WordPress media uploader functionality
 *
 * @package ApprenticeshipConnect
 */

/**
 * Initialize media uploader functionality
 */
export function initMediaUploader() {
	const uploadButton = document.getElementById('no_vacancy_image_button');
	if (!uploadButton) {
		return;
	}

	uploadButton.addEventListener('click', (event) => {
		event.preventDefault();

		// Check if wp.media is available
		if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
			alert('Media uploader not available');
			return;
		}

		const input = document.getElementById('no_vacancy_image');

		// Create media frame
		const mediaFrame = wp.media({
			title: 'Select No Vacancy Image',
			button: {
				text: 'Use this image',
			},
			multiple: false,
		});

		// Handle selection
		mediaFrame.on('select', () => {
			const attachment = mediaFrame.state().get('selection').first().toJSON();
			if (input) {
				input.value = attachment.url;
			}
		});

		// Open frame
		mediaFrame.open();
	});
}
