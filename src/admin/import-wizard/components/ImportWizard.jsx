/**
 * Import Wizard Component
 *
 * React component for the import wizard interface
 *
 * @package ApprenticeshipConnect
 */

import { useState } from '@wordpress/element';
import { Button, Card, CardBody, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Import Wizard Component
 */
export default function ImportWizard() {
	const [step, setStep] = useState('connect');
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const [connectionData, setConnectionData] = useState({
		apiBaseUrl: '',
		apiKey: '',
		ukprn: '',
	});

	/**
	 * Test API connection
	 */
	const testConnection = async () => {
		setLoading(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: '/apprco/v1/test-connection',
				method: 'POST',
				data: connectionData,
			});

			if (response.success) {
				setStep('review');
			} else {
				setError(__('Connection test failed. Please check your credentials.', 'apprenticeship-connect'));
			}
		} catch (err) {
			setError(err.message || __('An error occurred', 'apprenticeship-connect'));
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Render connection step
	 */
	const renderConnectStep = () => (
		<Card>
			<CardBody>
				<h2>{__('Connect to API', 'apprenticeship-connect')}</h2>

				{error && <Notice status="error" isDismissible={false}>{error}</Notice>}

				<div className="apprco-form-field">
					<label htmlFor="apiBaseUrl">
						{__('API Base URL', 'apprenticeship-connect')}
					</label>
					<input
						type="text"
						id="apiBaseUrl"
						value={connectionData.apiBaseUrl}
						onChange={(e) =>
							setConnectionData({ ...connectionData, apiBaseUrl: e.target.value })
						}
						placeholder="https://api.example.com"
					/>
				</div>

				<div className="apprco-form-field">
					<label htmlFor="apiKey">
						{__('API Key / Subscription Key', 'apprenticeship-connect')}
					</label>
					<input
						type="password"
						id="apiKey"
						value={connectionData.apiKey}
						onChange={(e) =>
							setConnectionData({ ...connectionData, apiKey: e.target.value })
						}
						placeholder="Enter your API key"
					/>
				</div>

				<div className="apprco-form-field">
					<label htmlFor="ukprn">
						{__('UKPRN (Optional)', 'apprenticeship-connect')}
					</label>
					<input
						type="text"
						id="ukprn"
						value={connectionData.ukprn}
						onChange={(e) =>
							setConnectionData({ ...connectionData, ukprn: e.target.value })
						}
						placeholder="10000000"
					/>
				</div>

				<Button
					isPrimary
					onClick={testConnection}
					isBusy={loading}
					disabled={!connectionData.apiBaseUrl || !connectionData.apiKey}
				>
					{loading ? __('Testing Connection...', 'apprenticeship-connect') : __('Test Connection', 'apprenticeship-connect')}
				</Button>
			</CardBody>
		</Card>
	);

	/**
	 * Render review step
	 */
	const renderReviewStep = () => (
		<Card>
			<CardBody>
				<h2>{__('Connection Successful!', 'apprenticeship-connect')}</h2>
				<Notice status="success" isDismissible={false}>
					{__('API connection test was successful.', 'apprenticeship-connect')}
				</Notice>

				<p>
					{__('You can now start importing apprenticeship data.', 'apprenticeship-connect')}
				</p>

				<Button isPrimary onClick={() => window.location.reload()}>
					{__('Complete Setup', 'apprenticeship-connect')}
				</Button>
			</CardBody>
		</Card>
	);

	return (
		<div className="apprco-import-wizard">
			{step === 'connect' && renderConnectStep()}
			{step === 'review' && renderReviewStep()}
		</div>
	);
}
