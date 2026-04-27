/**
 * Quick Actions Component
 *
 * Provides quick action buttons for common tasks.
 *
 * @package ApprenticeshipConnect
 */

import { Button, Card, CardBody, CardHeader, Flex, __experimentalHeading as Heading } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { cloudUpload, cog, list, chartBar, download } from '@wordpress/icons';

/**
 * QuickActions Component
 *
 * @param {Object} props         Component props.
 * @param {Function} props.onSync Manual sync handler.
 * @param {boolean} props.syncing Whether sync is in progress.
 */
const QuickActions = ({ onSync, syncing }) => {
	return (
		<Card className="apprco-quick-actions">
			<CardHeader>
				<Heading level={2}>{__('Quick Actions', 'apprenticeship-connect')}</Heading>
			</CardHeader>
			<CardBody>
				<Flex gap={3} wrap>
					<Button variant="secondary" icon={cloudUpload} onClick={onSync} isBusy={syncing} disabled={syncing}>
						{__('Manual Sync', 'apprenticeship-connect')}
					</Button>

					<Button variant="secondary" icon={cog} href="/wp-admin/admin.php?page=apprco-settings">
						{__('Settings', 'apprenticeship-connect')}
					</Button>

					<Button variant="secondary" icon={list} href="/wp-admin/admin.php?page=apprco-import-tasks">
						{__('Import Tasks', 'apprenticeship-connect')}
					</Button>

					<Button variant="secondary" icon={chartBar} href="/wp-admin/admin.php?page=apprco-logs">
						{__('View Logs', 'apprenticeship-connect')}
					</Button>

					<Button variant="secondary" icon={download} href="/wp-admin/edit.php?post_type=apprco_vacancy">
						{__('All Vacancies', 'apprenticeship-connect')}
					</Button>
				</Flex>
			</CardBody>
		</Card>
	);
};

export default QuickActions;
