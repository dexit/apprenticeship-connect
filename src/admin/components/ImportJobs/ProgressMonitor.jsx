/**
 * Progress monitor – polls the REST API for run status and shows a progress bar + log.
 *
 * Improvements over the original stub:
 *  - AbortController on component unmount (no orphaned in-flight requests)
 *  - Adaptive polling interval (2 s → up to 10 s, resets on progress change)
 *  - Error state shown after 3 consecutive failures
 *  - Stage index summary panel (pending / processing / completed / failed)
 */
import { useEffect, useRef, useState, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const MIN_INTERVAL_MS  = 2_000;
const MAX_INTERVAL_MS  = 10_000;
const STEP_MS          = 1_000;
const TERMINAL_STATES  = new Set( [ 'completed', 'failed', 'cancelled' ] );
const MAX_ERRORS       = 3;

export default function ProgressMonitor( { runId, onComplete } ) {
	const [ run,         setRun         ] = useState( null );
	const [ logs,        setLogs        ] = useState( [] );
	const [ indexStats,  setIndexStats  ] = useState( null );
	const [ pollError,   setPollError   ] = useState( null );

	const abortRef      = useRef( null );
	const timerRef      = useRef( null );
	const intervalRef   = useRef( MIN_INTERVAL_MS );
	const prevPctRef    = useRef( -1 );
	const errorCountRef = useRef( 0 );
	const logsEndRef    = useRef( null );
	const isTerminated  = useRef( false );

	// ── Fetch helpers ─────────────────────────────────────────────────────

	const fetchAll = useCallback( async () => {
		if ( isTerminated.current ) return;

		// Cancel any previous in-flight requests.
		abortRef.current?.abort();
		const controller = new AbortController();
		abortRef.current = controller;

		try {
			const [ runRes, logsRes, indexRes ] = await Promise.all( [
				apiFetch( { path: `/appcon/v1/import-jobs/runs/${ runId }`,           signal: controller.signal } ),
				apiFetch( { path: `/appcon/v1/import-jobs/runs/${ runId }/logs?limit=100`, signal: controller.signal } ),
				apiFetch( { path: `/appcon/v1/import-jobs/runs/${ runId }/index`,     signal: controller.signal } ),
			] );

			errorCountRef.current = 0;
			setPollError( null );
			setRun( runRes );
			setLogs( logsRes ?? [] );
			setIndexStats( indexRes );

			// Scroll logs to bottom.
			requestAnimationFrame( () => logsEndRef.current?.scrollIntoView( { behavior: 'smooth' } ) );

			// Adaptive interval: if progress changed, reset to MIN; otherwise slow down.
			const pct = parseFloat( runRes.progress_pct ?? 0 );
			if ( pct !== prevPctRef.current ) {
				intervalRef.current = MIN_INTERVAL_MS;
				prevPctRef.current  = pct;
			} else {
				intervalRef.current = Math.min( intervalRef.current + STEP_MS, MAX_INTERVAL_MS );
			}

			if ( TERMINAL_STATES.has( runRes.status ) ) {
				isTerminated.current = true;
				onComplete?.();
				return;
			}
		} catch ( err ) {
			if ( err.name === 'AbortError' ) return;

			errorCountRef.current += 1;
			if ( errorCountRef.current >= MAX_ERRORS ) {
				setPollError( `Polling failed ${ errorCountRef.current } times: ${ err.message }` );
			}
		}

		// Schedule next poll.
		if ( ! isTerminated.current ) {
			timerRef.current = setTimeout( fetchAll, intervalRef.current );
		}
	}, [ runId, onComplete ] );

	// ── Lifecycle ─────────────────────────────────────────────────────────

	useEffect( () => {
		isTerminated.current  = false;
		intervalRef.current   = MIN_INTERVAL_MS;
		prevPctRef.current    = -1;
		errorCountRef.current = 0;

		fetchAll();

		return () => {
			isTerminated.current = true;
			clearTimeout( timerRef.current );
			abortRef.current?.abort();
		};
	}, [ fetchAll ] );

	// ── Render ────────────────────────────────────────────────────────────

	if ( ! run ) {
		return (
			<p style={ { display: 'flex', gap: 8, alignItems: 'center' } }>
				<Spinner /> Waiting for run status…
			</p>
		);
	}

	const pct       = parseFloat( run.progress_pct ?? 0 );
	const isRunning = ! TERMINAL_STATES.has( run.status );

	return (
		<div className="appcon-progress-monitor">

			{ /* ── Status row ── */ }
			<p style={ { display: 'flex', alignItems: 'center', gap: 8 } }>
				{ isRunning && <Spinner /> }
				<strong>Status:</strong>
				<span className={ `appcon-status appcon-status--${ run.status }` }>{ run.status }</span>
				{ run.current_stage && (
					<span style={ { color: '#646970' } }>
						Stage { run.current_stage } – item { run.current_item } / { run.total_items }
					</span>
				) }
				<span style={ { marginLeft: 'auto', color: '#646970', fontSize: '0.8rem' } }>
					polling every { ( intervalRef.current / 1000 ).toFixed( 0 ) }s
				</span>
			</p>

			{ /* ── Progress bar ── */ }
			<div className="appcon-progress" role="progressbar" aria-valuenow={ pct } aria-valuemin={ 0 } aria-valuemax={ 100 }>
				<div className="appcon-progress__bar" style={ { width: `${ pct }%` } } />
			</div>
			<p style={ { fontSize: '0.8rem', color: '#646970', marginTop: 4 } }>{ pct.toFixed( 1 ) }%</p>

			{ /* ── Index summary ── */ }
			{ indexStats && indexStats.total > 0 && (
				<div className="appcon-index-summary" style={ { display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 12, fontSize: '0.85rem' } }>
					{ [ 'pending', 'processing', 'completed', 'failed', 'skipped' ].map( ( s ) =>
						indexStats[ s ] > 0 ? (
							<span key={ s } className={ `appcon-index-badge appcon-index-badge--${ s }` }>
								{ s }: { indexStats[ s ] }
							</span>
						) : null
					) }
					<span style={ { color: '#646970' } }>/ { indexStats.total } total</span>
				</div>
			) }

			{ /* ── Completion summary ── */ }
			{ run.status === 'completed' && (
				<p className="appcon-notice appcon-notice--success">
					✓ Import complete — { run.stage2_created } created, { run.stage2_updated } updated,
					{ ' ' }{ run.stage2_skipped } skipped, { run.stage2_errors } errors
					{ run.duration ? ` — took ${ run.duration }s` : '' }
				</p>
			) }

			{ run.status === 'failed' && (
				<p className="appcon-notice appcon-notice--error">
					✗ Import failed{ run.error_message ? `: ${ run.error_message }` : '' }
				</p>
			) }

			{ pollError && (
				<p className="appcon-notice appcon-notice--warning">⚠ { pollError }</p>
			) }

			{ /* ── Log terminal ── */ }
			{ logs.length > 0 && (
				<>
					<h4 style={ { marginBottom: 4 } }>Log Output ({ logs.length } lines)</h4>
					<div className="appcon-logs-terminal" role="log" aria-live="polite">
						{ logs.map( ( log ) => (
							<div key={ log.id } className={ `log-line log-line--${ log.log_level }` }>
								<span className="log-level">{ log.log_level?.toUpperCase().padEnd( 8 ) }</span>
								{ log.context && <span className="log-context">[{ log.context }] </span> }
								{ log.message }
							</div>
						) ) }
						<div ref={ logsEndRef } />
					</div>
				</>
			) }
		</div>
	);
}
