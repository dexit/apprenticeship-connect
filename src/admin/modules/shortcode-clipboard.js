/**
 * Shortcode clipboard functionality
 *
 * @package ApprenticeshipConnect
 */

/**
 * Initialize shortcode clipboard functionality
 */
export function initShortcodeClipboard() {
	const codes = document.querySelectorAll('.apprco-shortcode-inline code');

	codes.forEach((code) => {
		code.addEventListener('click', async () => {
			const text = code.textContent;

			try {
				if (navigator.clipboard) {
					await navigator.clipboard.writeText(text);
				} else {
					// Fallback for older browsers
					const textarea = document.createElement('textarea');
					textarea.value = text;
					document.body.appendChild(textarea);
					textarea.select();
					document.execCommand('copy');
					document.body.removeChild(textarea);
				}

				showCopiedFeedback(code);
			} catch (error) {
				console.error('Failed to copy:', error);
			}
		});
	});
}

/**
 * Show copied feedback
 *
 * @param {HTMLElement} element - Code element
 */
function showCopiedFeedback(element) {
	const originalText = element.textContent;
	const originalBg = element.style.background;
	const originalColor = element.style.color;

	element.textContent = 'Copied!';
	element.style.background = '#46b450';
	element.style.color = '#fff';

	setTimeout(() => {
		element.textContent = originalText;
		element.style.background = originalBg;
		element.style.color = originalColor;
	}, 2000);
}
