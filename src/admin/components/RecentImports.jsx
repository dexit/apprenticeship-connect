/**
 * Recent Imports Component
 *
 * Displays recent import history with stats.
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
	Spinner,
	Notice,
	__experimentalHeading as Heading,
	__experimentalText as Text,
} from '@wordpress/components';

/**
 * RecentImports Component
 */
const RecentImports = () => {
	const [imports, setImports] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	useEffect(() => {
		loadRecentImports();
	}, []);

	const loadRecentImports = async () => {
		try {
			setLoading(true);
			const response = await apiFetch({
				path: '/apprco/v1/imports/recent?limit=5',
			});

			setImports(response.imports || []);
			setError(null);
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	if (loading) {
		return (
			<Card>
				<CardBody>
					<Spinner />
				</CardBody>
			</Card>
		);
	}

	if (error) {
		return (
			<Card>
				<CardBody>
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				</CardBody>
			</Card>
		);
	}

	return (
		<Card className="apprco-recent-imports">
			<CardHeader>
				<Heading level={2}>{__('Recent Imports', 'apprenticeship-connect')}</Heading>
			</CardHeader>
			<CardBody>
				{imports.length === 0 ? (
					<Text variant="muted">{__('No imports yet. Click "Manual Sync" to start.', 'apprenticeship-connect')}</Text>
				) : (
					<table className="widefat striped">
						<thead>
							<tr>
								<th>{__('Date', 'apprenticeship-connect')}</th>
								<th>{__('Type', 'apprenticeship-connect')}</th>
								<th>{__('Fetched', 'apprenticeship-connect')}</th>
								<th>{__('Created', 'apprenticeship-connect')}</th>
								<th>{__('Updated', 'apprenticeship-connect')}</th>
								<th>{__('Errors', 'apprenticeship-connect')}</th>
								<th>{__('Status', 'apprenticeship-connect')}</th>
							</tr>
						</thead>
						<tbody>
							{imports.map((imp) => (
								<tr key={imp.id}>
									<td>{new Date(imp.started_at).toLocaleString()}</td>
									<td>{imp.trigger_type}</td>
									<td>{imp.total_fetched}</td>
									<td>{imp.total_created}</td>
									<td>{imp.total_updated}</td>
									<td className={imp.total_errors > 0 ? 'apprco-error-count' : ''}>{imp.total_errors}</td>
									<td>
										<span className={`apprco-status apprco-status-${imp.status}`}>{imp.status}</span>
									</td>
								</tr>
							))}
						</tbody>
					</table>
				)}
			</CardBody>
		</Card>
	);
};

export default RecentImports;
