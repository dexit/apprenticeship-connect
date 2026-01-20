/**
 * Dashboard Page Component
 *
 * Main admin dashboard with stats, quick actions, and recent activity.
 *
 * @package ApprenticeshipConnect
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Spinner,
	Notice,
	Flex,
	FlexBlock,
	FlexItem,
	__experimentalHeading as Heading,
	__experimentalText as Text,
} from '@wordpress/components';
import { Icon, download, cloudUpload, settings, chartBar } from '@wordpress/icons';

import StatsWidget from '../components/StatsWidget';
import RecentImports from '../components/RecentImports';
import QuickActions from '../components/QuickActions';
import APIStatus from '../components/APIStatus';

/**
 * Dashboard Component
 */
const Dashboard = () => {
	const [stats, setStats] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [syncing, setSyncing] = useState(false);

	/**
	 * Load dashboard stats
	 */
	useEffect(() => {
		loadStats();
	}, []);

	/**
	 * Fetch stats from API
	 */
	const loadStats = async () => {
		try {
			setLoading(true);
			const response = await apiFetch({
				path: '/apprco/v1/stats',
			});

			setStats(response);
			setError(null);
		} catch (err) {
			setError(err.message || __('Failed to load stats', 'apprenticeship-connect'));
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Handle manual sync
	 */
	const handleManualSync = async () => {
		setSyncing(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: '/apprco/v1/import/manual',
				method: 'POST',
			});

			if (response.success) {
				alert(
					__('Import started successfully!', 'apprenticeship-connect') +
						'\n' +
						sprintf(
							__('Fetched: %d, Created: %d, Updated: %d', 'apprenticeship-connect'),
							response.fetched,
							response.created,
							response.updated
						)
				);
				loadStats(); // Refresh stats
			} else {
				setError(response.error || __('Import failed', 'apprenticeship-connect'));
			}
		} catch (err) {
			setError(err.message || __('Import request failed', 'apprenticeship-connect'));
		} finally {
			setSyncing(false);
		}
	};

	if (loading) {
		return (
			<div className="apprco-dashboard-loading">
				<Spinner />
				<Text>{__('Loading dashboard...', 'apprenticeship-connect')}</Text>
			</div>
		);
	}

	return (
		<div className="apprco-dashboard">
			<Flex justify="space-between" align="center" className="apprco-dashboard-header">
				<FlexBlock>
					<Heading level={1}>{__('Apprenticeship Connect Dashboard', 'apprenticeship-connect')}</Heading>
					<Text variant="muted">
						{__('Manage your apprenticeship vacancy imports and settings', 'apprenticeship-connect')}
					</Text>
				</FlexBlock>
				<FlexItem>
					<Button variant="primary" icon={cloudUpload} onClick={handleManualSync} isBusy={syncing} disabled={syncing}>
						{syncing ? __('Syncing...', 'apprenticeship-connect') : __('Manual Sync', 'apprenticeship-connect')}
					</Button>
				</FlexItem>
			</Flex>

			{error && (
				<Notice status="error" isDismissible onRemove={() => setError(null)}>
					{error}
				</Notice>
			)}

			{/* Quick Actions */}
			<QuickActions onSync={handleManualSync} syncing={syncing} />

			{/* Stats Grid */}
			<div className="apprco-stats-grid">
				<StatsWidget
					title={__('Total Vacancies', 'apprenticeship-connect')}
					value={stats?.total_vacancies || 0}
					icon={chartBar}
					color="blue"
				/>
				<StatsWidget
					title={__('Total Imports', 'apprenticeship-connect')}
					value={stats?.total_imports || 0}
					icon={download}
					color="green"
				/>
				<StatsWidget
					title={__('Last Import', 'apprenticeship-connect')}
					value={stats?.last_import || __('Never', 'apprenticeship-connect')}
					icon={cloudUpload}
					color="purple"
					isDate
				/>
				<StatsWidget
					title={__('API Status', 'apprenticeship-connect')}
					value={stats?.api_configured ? __('Configured', 'apprenticeship-connect') : __('Not Configured', 'apprenticeship-connect')}
					icon={settings}
					color={stats?.api_configured ? 'green' : 'red'}
				/>
			</div>

			{/* API Status Check */}
			<APIStatus />

			{/* Recent Imports */}
			<RecentImports />

			{/* Help & Documentation */}
			<Card>
				<CardHeader>
					<Heading level={2}>{__('Getting Started', 'apprenticeship-connect')}</Heading>
				</CardHeader>
				<CardBody>
					<ol>
						<li>
							{__('Configure your API credentials in', 'apprenticeship-connect')}{' '}
							<a href="/wp-admin/admin.php?page=apprco-settings">{__('Settings', 'apprenticeship-connect')}</a>
						</li>
						<li>{__('Click "Manual Sync" to import vacancies', 'apprenticeship-connect')}</li>
						<li>
							{__('Set up scheduled imports in', 'apprenticeship-connect')}{' '}
							<a href="/wp-admin/admin.php?page=apprco-import-tasks">{__('Import Tasks', 'apprenticeship-connect')}</a>
						</li>
						<li>
							{__('View imported vacancies on', 'apprenticeship-connect')}{' '}
							<a href="/wp-admin/edit.php?post_type=apprco_vacancy">{__('Vacancies page', 'apprenticeship-connect')}</a>
						</li>
					</ol>
				</CardBody>
			</Card>
		</div>
	);
};

export default Dashboard;
