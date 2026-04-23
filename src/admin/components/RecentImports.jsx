/**
 * RecentImports – lists import jobs with their last run summary.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';

const STATUS_COLORS = {
	completed: '#15803d',
	running:   '#1d4ed8',
	failed:    '#b91c1c',
	pending:   '#a16207',
};

const RecentImports = () => {
	const [ jobs, setJobs ]       = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ]     = useState( null );

	useEffect( () => {
		apiFetch( { path: '/appcon/v1/import-jobs' } )
			.then( ( data ) => setJobs( Array.isArray( data ) ? data.slice( 0, 8 ) : [] ) )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setLoading( false ) );
	}, [] );

	if ( loading ) {
		return (
			<div style={ { padding: 16 } }>
				<Spinner />
			</div>
		);
	}

	if ( error ) {
		return (
			<p style={ { color: '#b91c1c', margin: 0 } }>
				{ __( 'Could not load import jobs.', 'apprenticeship-connector' ) }
			</p>
		);
	}

	if ( ! jobs.length ) {
		return (
			<p style={ { color: '#6b7280', margin: 0 } }>
				{ __( 'No import jobs yet.', 'apprenticeship-connector' ) }
			</p>
		);
	}

	return (
		<table className="widefat striped" style={ { marginTop: 4 } }>
			<thead>
				<tr>
					<th>{ __( 'Name', 'apprenticeship-connector' ) }</th>
					<th>{ __( 'Status', 'apprenticeship-connector' ) }</th>
					<th>{ __( 'Last Run', 'apprenticeship-connector' ) }</th>
					<th>{ __( 'Processed', 'apprenticeship-connector' ) }</th>
					<th>{ __( 'Failed', 'apprenticeship-connector' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ jobs.map( ( job ) => {
					const statusColor = STATUS_COLORS[ job.last_run_status ] ?? '#6b7280';
					const completedAt = job.last_run_completed_at
						? new Date( job.last_run_completed_at ).toLocaleString()
						: '—';
					return (
						<tr key={ job.id }>
							<td>
								<a href={ `admin.php?page=appcon-import-jobs&job=${ job.id }` }>
									{ job.name }
								</a>
							</td>
							<td>
								<span style={ { color: statusColor, fontWeight: 600, textTransform: 'capitalize' } }>
									{ job.last_run_status ?? __( 'Never run', 'apprenticeship-connector' ) }
								</span>
							</td>
							<td>{ completedAt }</td>
							<td>{ job.last_run_stage2_processed ?? '—' }</td>
							<td style={ { color: job.last_run_stage2_failed > 0 ? '#b91c1c' : 'inherit' } }>
								{ job.last_run_stage2_failed ?? '—' }
							</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
};

export default RecentImports;
