/**
 * Dashboard page – simple status overview.
 */
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function DashboardPage() {
	const [ jobs, setJobs ] = useState( [] );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		apiFetch( { path: '/appcon/v1/import-jobs' } )
			.then( ( data ) => setJobs( data ?? [] ) )
			.finally( () => setLoading( false ) );
	}, [] );

	if ( loading ) {
		return <p>Loading…</p>;
	}

	const activeJobs = jobs.filter( ( j ) => j.status === 'active' );

	return (
		<div className="appcon-dashboard-react">
			<h2>Quick Stats</h2>
			<ul>
				<li>{ jobs.length } total import jobs ({ activeJobs.length } active)</li>
			</ul>
			<p>
				<a href="?page=appcon-import-jobs" className="button button-primary">
					Manage Import Jobs
				</a>
			</p>
		</div>
	);
}
