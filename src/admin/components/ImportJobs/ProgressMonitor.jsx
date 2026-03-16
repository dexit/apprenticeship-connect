/**
 * Progress monitor – polls the REST API for run status and shows a progress bar + log.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function ProgressMonitor( { runId, onComplete } ) {
	const [ run, setRun ]       = useState( null );
	const [ logs, setLogs ]     = useState( [] );
	const intervalRef           = useRef( null );
	const logsEndRef            = useRef( null );

	const fetchStatus = async () => {
		try {
			const res = await apiFetch( {
				path: `/appcon/v1/import-jobs/runs/${ runId }`,
			} );
			setRun( res );

			if ( [ 'completed', 'failed', 'cancelled' ].includes( res.status ) ) {
				clearInterval( intervalRef.current );
				onComplete?.();
			}
		} catch {
			// Silently ignore transient errors during polling.
		}
	};

	const fetchLogs = async () => {
		try {
			const res = await apiFetch( {
				path: `/appcon/v1/import-jobs/runs/${ runId }/logs?limit=50`,
			} );
			setLogs( res ?? [] );
			logsEndRef.current?.scrollIntoView( { behavior: 'smooth' } );
		} catch {
			// ignore
		}
	};

	useEffect( () => {
		fetchStatus();
		fetchLogs();
		intervalRef.current = setInterval( () => {
			fetchStatus();
			fetchLogs();
		}, 3000 );

		return () => clearInterval( intervalRef.current );
	}, [ runId ] );

	if ( ! run ) return <p>Waiting for run status…</p>;

	const pct = parseFloat( run.progress_pct ?? 0 );

	return (
		<div className="appcon-progress-monitor">
			<p>
				<strong>Status:</strong>{ ' ' }
				<span className={ `appcon-status appcon-status--${ run.status }` }>{ run.status }</span>
				{ run.current_stage && (
					<span style={ { marginLeft: 12, color: '#646970' } }>
						Stage { run.current_stage } – { run.current_item } / { run.total_items }
					</span>
				) }
			</p>

			<div className="appcon-progress" style={ { marginBottom: 12 } }>
				<div className="appcon-progress__bar" style={ { width: `${ pct }%` } } />
			</div>
			<p style={ { fontSize: '0.8rem', color: '#646970', marginTop: -8 } }>{ pct }%</p>

			{ run.status === 'completed' && (
				<p style={ { color: '#065f46' } }>
					✓ Import complete – { run.stage2_created } created, { run.stage2_updated } updated,
					{ run.stage2_errors } errors — { run.duration }s
				</p>
			) }

			{ run.error_message && (
				<p style={ { color: '#991b1b' } }>⚠ { run.error_message }</p>
			) }

			{ logs.length > 0 && (
				<>
					<h4>Log Output</h4>
					<div className="appcon-logs-terminal">
						{ logs.map( ( log ) => (
							<div key={ log.id } className={ `log-line log-line--${ log.log_level }` }>
								[{ log.log_level?.toUpperCase() }] { log.message }
							</div>
						) ) }
						<div ref={ logsEndRef } />
					</div>
				</>
			) }
		</div>
	);
}
