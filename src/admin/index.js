/**
 * Apprenticeship Connect Admin JavaScript
 *
 * Modern ES6+ implementation using @wordpress APIs
 *
 * @package ApprenticeshipConnect
 */

import './style.scss';
import { initManualSync } from './modules/sync';
import { initTestAPI } from './modules/api-test';
import { initClearCache } from './modules/cache';
import { initLogsPage } from './modules/logs';
import { initSetupWizard } from './modules/setup-wizard';
import { initFormValidation } from './modules/form-validation';
import { initShortcodeClipboard } from './modules/shortcode-clipboard';
import { initMediaUploader } from './modules/media-uploader';

/**
 * Initialize admin functionality
 */
document.addEventListener('DOMContentLoaded', () => {
	// Core admin functionality
	initManualSync();
	initTestAPI();
	initClearCache();

	// Logs page
	if (document.querySelector('.apprco-logs')) {
		initLogsPage();
	}

	// Setup wizard
	if (document.querySelector('.apprco-setup-progress')) {
		initSetupWizard();
	}

	// Form validation
	initFormValidation();

	// Shortcode clipboard
	initShortcodeClipboard();

	// Media uploader
	initMediaUploader();
});
