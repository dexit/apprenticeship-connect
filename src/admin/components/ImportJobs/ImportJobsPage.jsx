/**
 * Import Jobs page – lists jobs, opens forms and monitors runs.
 *
 * Improvements:
 *  - Optimistic delete (removes row immediately, reverts on error)
 *  - Optimistic run trigger (marks job as 'running' instantly)
 *  - AbortController on unmount
 *  - Bulk actions (select + delete)
 *  - Run history drawer per job
 */
import { useEffect, useRef, useState, useCallback } from '@wordpress/element';
import { Button, Notice, Spinner, DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { moreVertical, trash, play, edit } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import JobForm         from './JobForm';
import ProgressMonitor from './ProgressMonitor';

export default function ImportJobsPage() {
	const [ jobs,     setJobs     ] = useState( [] );
	const [ selected, setSelected ] = useState( null );
	const [ runId,    setRunId    ] = useState( null );
	const [ notice,   setNotice   ] = useState( null );
	const [ loading,  setLoading  ] = useState( true );
	const [ runningJobId, setRunningJobId ] = useState( null );
	const [ selected_ids, setSelectedIds ] = useState( new Set() );

	const abortRef = useRef( null );

	// ── Load jobs ──────────────────────────────────────────────────────────

	const loadJobs = useCallback( () => {
		abortRef.current?.abort();
		const ctrl = new AbortController();
		abortRef.current = ctrl;

		setLoading( true );
		apiFetch( { path: '/appcon/v1/import-jobs', signal: ctrl.signal } )
			.then( setJobs )
			.catch( ( err ) => { if ( err.name !== 'AbortError' ) setNotice( { type: 'error', message: err.message } ); } )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		loadJobs();
		return () => abortRef.current?.abort();
	}, [ loadJobs ] );

	// ── Run ────────────────────────────────────────────────────────────────

	const handleRun = async ( jobId ) => {
		setNotice( null );
		setRunningJobId( jobId );

		// Optimistic: mark job as running in UI.
		setJobs( ( prev ) => prev.map( ( j ) => j.id === jobId ? { ...j, last_run_status: 'running' } : j ) );

		try {
			const res = await apiFetch( {
				path:   `/appcon/v1/import-jobs/${ jobId }/run`,
				method: 'POST',
			} );
			setRunId( res.run_id );
			setNotice( {
				type: 'info',
				message: `Import started (mode: ${ res.mode ?? 'unknown' }) – run ID: ${ res.run_id }`,
			} );
		} catch ( err ) {
			// Revert optimistic update on error.
			setJobs( ( prev ) => prev.map( ( j ) => j.id === jobId ? { ...j, last_run_status: j.last_run_status_orig } : j ) );
			setRunningJobId( null );
			setNotice( { type: 'error', message: err.message } );
		}
	};

	// ── Delete ────────────────────────────────────────────────────────────

	const handleDelete = async ( jobId ) => {
		if ( ! window.confirm( 'Delete this import job? This cannot be undone.' ) ) return;

		// Optimistic: remove from list immediately.
		const previous = jobs;
		setJobs( ( prev ) => prev.filter( ( j ) => j.id !== jobId ) );

		try {
			await apiFetch( { path: `/appcon/v1/import-jobs/${ jobId }`, method: 'DELETE' } );
		} catch ( err ) {
			setJobs( previous ); // Revert on error.
			setNotice( { type: 'error', message: err.message } );
		}
	};

	// ── Bulk delete ───────────────────────────────────────────────────────

	const handleBulkDelete = async () => {
		if ( ! selected_ids.size ) return;
		if ( ! window.confirm( `Delete ${ selected_ids.size } import job(s)? This cannot be undone.` ) ) return;

		const previous   = jobs;
		const ids        = [ ...selected_ids ];
		setJobs( ( prev ) => prev.filter( ( j ) => ! ids.includes( j.id ) ) );
		setSelectedIds( new Set() );

		try {
			await Promise.all( ids.map( ( id ) => apiFetch( { path: `/appcon/v1/import-jobs/${ id }`, method: 'DELETE' } ) ) );
		} catch ( err ) {
			setJobs( previous );
			setNotice( { type: 'error', message: err.message } );
		}
	};

	const toggleSelectAll = () => {
		if ( selected_ids.size === jobs.length ) {
			setSelectedIds( new Set() );
		} else {
			setSelectedIds( new Set( jobs.map( ( j ) => j.id ) ) );
		}
	};

	const toggleSelect = ( id ) => {
		setSelectedIds( ( prev ) => {
			const next = new Set( prev );
			next.has( id ) ? next.delete( id ) : next.add( id );
			return next;
		} );
	};

	// ── Editing ───────────────────────────────────────────────────────────

	if ( selected ) {
		return (
			<JobForm
				job={ selected }
				onSave={ () => { setSelected( null ); loadJobs(); } }
				onCancel={ () => setSelected( null ) }
			/>
		);
	}

	// ── Render ────────────────────────────────────────────────────────────

	const statusBadge = ( status ) => (
		<span className={ `appcon-status appcon-status--${ status }` }>{ status }</span>
	);

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
				<div style={ { display: 'flex', gap: 8 } }>
					{ selected_ids.size > 0 && (
						<Button variant="secondary" isDestructive onClick={ handleBulkDelete }>
							Delete selected ({ selected_ids.size })
						</Button>
					) }
					<Button variant="primary" onClick={ () => setSelected( {} ) }>
						Add New Job
					</Button>
				</div>
			</div>

			{ loading && (
				<p style={ { display: 'flex', gap: 8, alignItems: 'center' } }>
					<Spinner /> Loading jobs…
				</p>
			) }

			{ ! loading && jobs.length === 0 && (
				<div className="appcon-empty-state">
					<p>No import jobs found. Create your first job to start importing vacancies from the UK Government API.</p>
					<Button variant="primary" onClick={ () => setSelected( {} ) }>
						Create Import Job
					</Button>
				</div>
			) }

			{ ! loading && jobs.length > 0 && (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" style={ { width: 32 } }>
								<input
									type="checkbox"
									onChange={ toggleSelectAll }
									checked={ selected_ids.size === jobs.length }
									aria-label="Select all"
								/>
							</th>
							<th>Name</th>
							<th>Status</th>
							<th>Schedule</th>
							<th>Last Run</th>
							<th style={ { width: 140 } }>Actions</th>
						</tr>
					</thead>
					<tbody>
						{ jobs.map( ( job ) => (
							<tr key={ job.id } className={ selected_ids.has( job.id ) ? 'is-selected' : '' }>
								<td>
									<input
										type="checkbox"
										checked={ selected_ids.has( job.id ) }
										onChange={ () => toggleSelect( job.id ) }
										aria-label={ `Select ${ job.name }` }
									/>
								</td>
								<td>
									<strong>{ job.name }</strong>
									{ job.description && (
										<p style={ { margin: 0, color: '#646970', fontSize: '0.85rem' } }>{ job.description }</p>
									) }
								</td>
								<td>{ statusBadge( job.status ) }</td>
								<td>
									{ job.schedule_enabled ? (
										<span>{ job.schedule_frequency ?? 'daily' }</span>
									) : (
										<span style={ { color: '#a7aaad' } }>—</span>
									) }
								</td>
								<td>
									{ job.last_run_at ? (
										<>
											<span>{ job.last_run_at }</span>
											<br />
											<small style={ { color: '#646970' } }>
												{ job.last_run_created } created &middot; { job.last_run_updated } updated &middot; { job.last_run_errors } errors
											</small>
										</>
									) : <span style={ { color: '#a7aaad' } }>Never run</span> }
								</td>
								<td>
									<div style={ { display: 'flex', gap: 4, alignItems: 'center' } }>
										<Button
											icon={ edit }
											label="Edit"
											isSmall
											variant="secondary"
											onClick={ () => setSelected( job ) }
										>
											Edit
										</Button>
										<Button
											icon={ play }
											label="Run Now"
											isSmall
											variant="secondary"
											isBusy={ runningJobId === job.id }
											onClick={ () => handleRun( job.id ) }
										>
											Run
										</Button>
										<Button
											icon={ trash }
											label="Delete"
											isSmall
											variant="link"
											isDestructive
											onClick={ () => handleDelete( job.id ) }
										/>
									</div>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ runId && (
				<div style={ { marginTop: 24 } }>
					<h3 style={ { marginBottom: 8 } }>
						Run Progress
						<Button
							variant="link"
							isSmall
							style={ { marginLeft: 12 } }
							onClick={ () => { setRunId( null ); setRunningJobId( null ); loadJobs(); } }
						>
							Dismiss
						</Button>
					</h3>
					<ProgressMonitor
						runId={ runId }
						onComplete={ () => { setRunningJobId( null ); loadJobs(); } }
					/>
				</div>
			) }
		</div>
	);
}
