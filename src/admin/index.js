import { render } from '@wordpress/element';
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';
import './style.scss';

document.addEventListener('DOMContentLoaded', () => {
    const dashboardRoot = document.getElementById('apprco-dashboard-root');
    if (dashboardRoot) render(<Dashboard />, dashboardRoot);

    const settingsRoot = document.getElementById('apprco-settings-root');
    if (settingsRoot) render(<Settings />, settingsRoot);
});
