/**
 * Import Jobs page – lists jobs, opens forms and monitors runs.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import JobForm        from './JobForm';
import ProgressMonitor from './ProgressMonitor';

export default function ImportJobsPage() {
	const [ jobs, setJobs ]       = useState( [] );
	const [ selected, setSelected ] = useState( null );
	const [ runId, setRunId ]     = useState( null );
	const [ notice, setNotice ]   = useState( null );
	const [ loading, setLoading ] = useState( true );

	const loadJobs = () => {
		setLoading( true );
		apiFetch( { path: '/appcon/v1/import-jobs' } )
			.then( setJobs )
			.finally( () => setLoading( false ) );
	};

	useEffect( loadJobs, [] );

	const handleRun = async ( jobId ) => {
		setNotice( null );
		try {
			const res = await apiFetch( {
				path:   `/appcon/v1/import-jobs/${ jobId }/run`,
				method: 'POST',
			} );
			setRunId( res.run_id );
			setNotice( { type: 'info', message: `Import started – run ID: ${ res.run_id }` } );
		} catch ( err ) {
			setNotice( { type: 'error', message: err.message } );
		}
	};

	const handleDelete = async ( jobId ) => {
		if ( ! window.confirm( 'Delete this import job?' ) ) return;

		try {
			await apiFetch( { path: `/appcon/v1/import-jobs/${ jobId }`, method: 'DELETE' } );
			loadJobs();
		} catch ( err ) {
			setNotice( { type: 'error', message: err.message } );
		}
	};

	if ( selected ) {
		return (
			<JobForm
				job={ selected }
				onSave={ () => { setSelected( null ); loadJobs(); } }
				onCancel={ () => setSelected( null ) }
			/>
		);
	}

	return (
		<div className="appcon-import-jobs">
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 } }>
				<h2 style={ { margin: 0 } }>Import Jobs</h2>
				<Button variant="primary" onClick={ () => setSelected( {} ) }>
					Add New Job
				</Button>
			</div>

			{ loading && <p>Loading…</p> }

			{ ! loading && jobs.length === 0 && (
				<p>No import jobs found. Create one to get started.</p>
			) }

			{ ! loading && jobs.length > 0 && (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Name</th>
							<th>Status</th>
							<th>Last Run</th>
							<th>Created</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						{ jobs.map( ( job ) => (
							<tr key={ job.id }>
								<td><strong>{ job.name }</strong></td>
								<td>
									<span className={ `appcon-status appcon-status--${ job.status }` }>
										{ job.status }
									</span>
								</td>
								<td>
									{ job.last_run_at ? (
										<span>
											{ job.last_run_at }
											<br />
											<small>
												{ job.last_run_created } created, { job.last_run_updated } updated,
												{ job.last_run_errors } errors
											</small>
										</span>
									) : '—' }
								</td>
								<td>{ job.created_at }</td>
								<td>
									<Button variant="secondary" isSmall onClick={ () => setSelected( job ) }>
										Edit
									</Button>{ ' ' }
									<Button variant="secondary" isSmall onClick={ () => handleRun( job.id ) }>
										Run Now
									</Button>{ ' ' }
									<Button variant="link" isDestructive isSmall onClick={ () => handleDelete( job.id ) }>
										Delete
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ runId && (
				<div style={ { marginTop: 24 } }>
					<h3>Run Progress</h3>
					<ProgressMonitor runId={ runId } onComplete={ loadJobs } />
				</div>
			) }
		</div>
	);
}
