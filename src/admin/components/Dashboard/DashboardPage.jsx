/**
 * DashboardPage – main admin overview panel.
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

import StatsWidget   from '../StatsWidget';
import QuickActions  from '../QuickActions';
import RecentImports from '../RecentImports';
import APIStatus     from '../APIStatus';

export default function DashboardPage() {
	const [ stats, setStats ]     = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ running, setRunning ] = useState( false );
	const [ notice, setNotice ]   = useState( null );

	const loadStats = useCallback( () => {
		setLoading( true );
		apiFetch( { path: '/appcon/v1/stats' } )
			.then( ( data ) => setStats( data ) )
			.catch( () => {} )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( loadStats, [ loadStats ] );

	const runExpiry = async () => {
		setRunning( true );
		setNotice( null );
		try {
			const res = await apiFetch( { path: '/appcon/v1/expiry/run', method: 'POST' } );
			setNotice( {
				type: 'success',
				/* translators: %d: number of vacancies expired */
				msg:  sprintf( __( 'Expiry complete – %d vacancies moved to draft.', 'apprenticeship-connector' ), res.expired ?? 0 ),
			} );
			loadStats();
		} catch ( e ) {
			setNotice( { type: 'error', msg: e.message } );
		} finally {
			setRunning( false );
		}
	};

	const v = stats?.vacancies ?? {};
	const j = stats?.jobs      ?? {};

	return (
		<div className="appcon-dashboard-react">
			{ notice && (
				<Notice status={ notice.type } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }

			{ ! stats?.api_key_set && ! loading && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'No API key configured. ', 'apprenticeship-connector' ) }
					<a href="admin.php?page=appcon-settings">
						{ __( 'Go to Settings → API', 'apprenticeship-connector' ) }
					</a>
				</Notice>
			) }

			{ /* Stats grid */ }
			<div style={ { display: 'flex', flexWrap: 'wrap', gap: 12, marginBottom: 20 } }>
				<StatsWidget
					label={ __( 'Published Vacancies', 'apprenticeship-connector' ) }
					value={ loading ? undefined : v.published }
					color="blue"
					href="edit.php?post_type=appcon_vacancy&post_status=publish"
					loading={ loading }
				/>
				<StatsWidget
					label={ __( 'Draft Vacancies', 'apprenticeship-connector' ) }
					value={ loading ? undefined : v.draft }
					color="grey"
					href="edit.php?post_type=appcon_vacancy&post_status=draft"
					loading={ loading }
				/>
				<StatsWidget
					label={ __( 'Expiring in 7 Days', 'apprenticeship-connector' ) }
					value={ loading ? undefined : v.expiring_7d }
					color="amber"
					loading={ loading }
				/>
				<StatsWidget
					label={ __( 'Expired Total', 'apprenticeship-connector' ) }
					value={ loading ? undefined : v.expired }
					color="red"
					loading={ loading }
				/>
				<StatsWidget
					label={ __( 'Import Jobs', 'apprenticeship-connector' ) }
					value={ loading ? undefined : j.total }
					color="grey"
					href="admin.php?page=appcon-import-jobs"
					loading={ loading }
				/>
				<StatsWidget
					label={ __( 'Active Jobs', 'apprenticeship-connector' ) }
					value={ loading ? undefined : j.active }
					color="green"
					href="admin.php?page=appcon-import-jobs"
					loading={ loading }
				/>
			</div>

			{ /* Quick action buttons */ }
			<QuickActions onRunExpiry={ runExpiry } expiryRunning={ running } />

			{ /* Last run summary */ }
			{ stats?.last_run && (
				<p style={ { color: '#6b7280', fontSize: 13, marginTop: 12 } }>
					{ sprintf(
						/* translators: 1: job name, 2: run status, 3: datetime */
						__( 'Last run: %1$s — %2$s at %3$s', 'apprenticeship-connector' ),
						stats.last_run.job_name ?? '—',
						stats.last_run.status   ?? '—',
						stats.last_run.completed_at
							? new Date( stats.last_run.completed_at ).toLocaleString()
							: '—'
					) }
				</p>
			) }

			{ /* Recent imports table */ }
			<h3 style={ { marginTop: 24, marginBottom: 8, fontSize: 14, fontWeight: 600 } }>
				{ __( 'Import Jobs', 'apprenticeship-connector' ) }
			</h3>
			<RecentImports />

			{ /* API connectivity */ }
			<h3 style={ { marginTop: 24, marginBottom: 8, fontSize: 14, fontWeight: 600 } }>
				{ __( 'API Status', 'apprenticeship-connector' ) }
			</h3>
			<APIStatus />
		</div>
	);
}
