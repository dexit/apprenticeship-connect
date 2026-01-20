/**
 * Settings Entry Point
 *
 * Renders the React settings interface on the admin page.
 *
 * @package ApprenticeshipConnect
 */

import { render } from '@wordpress/element';
import Settings from './pages/Settings';
import './settings.scss';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('apprco-settings-root');

	if (root) {
		render(<Settings />, root);
	}
});
