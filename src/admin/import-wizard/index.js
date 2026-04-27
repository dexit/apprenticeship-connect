/**
 * Import Wizard
 *
 * React-based import wizard for setting up API connections
 *
 * @package ApprenticeshipConnect
 */

import { render } from '@wordpress/element';
import ImportWizard from './components/ImportWizard';

/**
 * Initialize the import wizard
 */
const rootElement = document.getElementById('apprco-import-wizard-root');

if (rootElement) {
	render(<ImportWizard />, rootElement);
}
