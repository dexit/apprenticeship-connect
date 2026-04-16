/**
 * Apprenticeship Connector – React Admin App.
 *
 * Mounts into #appcon-admin-root and routes by data-page attribute.
 */

import { createRoot } from '@wordpress/element';
import ImportJobsPage from './components/ImportJobs/ImportJobsPage';
import DashboardPage  from './components/Dashboard/DashboardPage';

import './style.scss';

const rootEl = document.getElementById( 'appcon-admin-root' );

if ( rootEl ) {
	const page = rootEl.dataset.page ?? 'dashboard';

	const App = () => {
		switch ( page ) {
			case 'import-jobs':
				return <ImportJobsPage />;
			default:
				return <DashboardPage />;
		}
	};

	createRoot( rootEl ).render( <App /> );
}
