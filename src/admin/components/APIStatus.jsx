/**
 * API Status Component
 *
 * Tests and displays API connection status.
 *
 * @package ApprenticeshipConnect
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Notice,
	Flex,
	FlexBlock,
	FlexItem,
	__experimentalHeading as Heading,
	__experimentalText as Text,
} from '@wordpress/components';
import { Icon, check, cancelCircleFilled } from '@wordpress/icons';

/**
 * APIStatus Component
 */
const APIStatus = () => {
	const [testing, setTesting] = useState(false);
	const [result, setResult] = useState(null);

	const testConnection = async () => {
		setTesting(true);
		setResult(null);

		try {
			const response = await apiFetch({
				path: '/apprco/v1/api/test',
				method: 'POST',
			});

			setResult(response);
		} catch (err) {
			setResult({
				success: false,
				error: err.message || __('Connection test failed', 'apprenticeship-connect'),
			});
		} finally {
			setTesting(false);
		}
	};

	return (
		<Card className="apprco-api-status">
			<CardHeader>
				<Flex justify="space-between" align="center">
					<FlexBlock>
						<Heading level={2}>{__('API Connection', 'apprenticeship-connect')}</Heading>
					</FlexBlock>
					<FlexItem>
						<Button variant="secondary" onClick={testConnection} isBusy={testing} disabled={testing}>
							{testing ? __('Testing...', 'apprenticeship-connect') : __('Test Connection', 'apprenticeship-connect')}
						</Button>
					</FlexItem>
				</Flex>
			</CardHeader>
			<CardBody>
				{!result ? (
					<Text variant="muted">{__('Click "Test Connection" to verify your API credentials.', 'apprenticeship-connect')}</Text>
				) : result.success ? (
					<Notice status="success" isDismissible={false}>
						<Flex align="center" gap={2}>
							<FlexItem>
								<Icon icon={check} />
							</FlexItem>
							<FlexBlock>
								<strong>{__('Connection successful!', 'apprenticeship-connect')}</strong>
								<br />
								{result.message && <Text>{result.message}</Text>}
								{result.sample_count && (
									<Text>
										{__('Sample data: ', 'apprenticeship-connect')}
										{result.sample_count} {__('vacancies', 'apprenticeship-connect')}
									</Text>
								)}
							</FlexBlock>
						</Flex>
					</Notice>
				) : (
					<Notice status="error" isDismissible={false}>
						<Flex align="center" gap={2}>
							<FlexItem>
								<Icon icon={cancelCircleFilled} />
							</FlexItem>
							<FlexBlock>
								<strong>{__('Connection failed', 'apprenticeship-connect')}</strong>
								<br />
								<Text>{result.error}</Text>
							</FlexBlock>
						</Flex>
					</Notice>
				)}
			</CardBody>
		</Card>
	);
};

export default APIStatus;
