/**
 * Dashboard page – quick stats, expiry overview, and recent runs.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export default function DashboardPage() {
	const [ jobs, setJobs ]           = useState( [] );
	const [ expiryStats, setExpiry ]  = useState( null );
	const [ running, setRunning ]     = useState( false );
	const [ notice, setNotice ]       = useState( null );
	const [ loading, setLoading ]     = useState( true );

	const loadData = () => {
		Promise.all( [
			apiFetch( { path: '/appcon/v1/import-jobs' } ),
			apiFetch( { path: '/appcon/v1/expiry/stats' } ),
		] ).then( ( [ j, e ] ) => {
			setJobs( j ?? [] );
			setExpiry( e );
		} ).finally( () => setLoading( false ) );
	};

	useEffect( loadData, [] );

	const runExpiry = async () => {
		setRunning( true );
		setNotice( null );
		try {
			const res = await apiFetch( { path: '/appcon/v1/expiry/run', method: 'POST' } );
			setNotice( { type: 'success', msg: `Expiry complete – ${ res.expired } vacancies set to draft.` } );
			loadData();
		} catch ( e ) {
			setNotice( { type: 'error', msg: e.message } );
		} finally {
			setRunning( false );
		}
	};

	if ( loading ) return <p>Loading…</p>;

	const activeJobs = jobs.filter( ( j ) => j.status === 'active' );

	return (
		<div className="appcon-dashboard-react">
			{ notice && (
				<Notice status={ notice.type } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }

			<div className="appcon-stats">
				<div className="appcon-stat-card">
					<span className="appcon-stat-number">{ jobs.length }</span>
					<span className="appcon-stat-label">Import Jobs</span>
				</div>
				<div className="appcon-stat-card">
					<span className="appcon-stat-number">{ activeJobs.length }</span>
					<span className="appcon-stat-label">Active Jobs</span>
				</div>
				{ expiryStats && (
					<>
						<div className="appcon-stat-card">
							<span className="appcon-stat-number" style={ { color: '#d97706' } }>
								{ expiryStats.upcoming_7d }
							</span>
							<span className="appcon-stat-label">Expiring in 7 days</span>
						</div>
						<div className="appcon-stat-card">
							<span className="appcon-stat-number" style={ { color: '#6b7280' } }>
								{ expiryStats.total_expired_drafts }
							</span>
							<span className="appcon-stat-label">Expired (draft)</span>
						</div>
					</>
				) }
			</div>

			<div style={ { display: 'flex', gap: 12, marginTop: 8 } }>
				<a href="?page=appcon-import-jobs" className="button button-primary">
					Manage Import Jobs
				</a>
				<Button variant="secondary" isBusy={ running } onClick={ runExpiry } disabled={ running }>
					{ running ? 'Running…' : 'Run Expiry Check Now' }
				</Button>
			</div>

			{ expiryStats?.expired_today > 0 && (
				<Notice status="info" isDismissible={ false } style={ { marginTop: 16 } }>
					{ expiryStats.expired_today } vacancies were expired today.
				</Notice>
			) }
		</div>
	);
}
