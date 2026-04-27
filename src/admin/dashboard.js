/**
 * Dashboard Entry Point
 *
 * Renders the React dashboard on the admin page.
 *
 * @package ApprenticeshipConnect
 */

import { render } from '@wordpress/element';
import Dashboard from './pages/Dashboard';
import './dashboard.scss';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('apprco-dashboard-root');

	if (root) {
		render(<Dashboard />, root);
	}
});
