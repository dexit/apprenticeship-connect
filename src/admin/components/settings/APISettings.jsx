/**
 * API Settings Component
 *
 * @package ApprenticeshipConnect
 */

import { __ } from '@wordpress/i18n';
import { Button, TextControl, __experimentalText as Text, __experimentalSpacer as Spacer } from '@wordpress/components';

const APISettings = ({ settings, updateSetting, onReset }) => {
	return (
		<div className="apprco-settings-category">
			<div className="apprco-settings-category-header">
				<h3>{__('API Configuration', 'apprenticeship-connect')}</h3>
				<Button variant="tertiary" isDestructive onClick={onReset}>
					{__('Reset to Defaults', 'apprenticeship-connect')}
				</Button>
			</div>

			<Text variant="muted">
				{__('Configure connection to the UK Government Apprenticeships API.', 'apprenticeship-connect')}
			</Text>

			<Spacer marginY={4} />

			<TextControl
				label={__('API Base URL', 'apprenticeship-connect')}
				value={settings.base_url}
				onChange={(value) => updateSetting('base_url', value)}
				help={__('The base URL for the API endpoint.', 'apprenticeship-connect')}
				type="url"
			/>

			<TextControl
				label={__('Subscription Key', 'apprenticeship-connect')}
				value={settings.subscription_key}
				onChange={(value) => updateSetting('subscription_key', value)}
				help={__('Your API subscription key (Ocp-Apim-Subscription-Key).', 'apprenticeship-connect')}
				type="password"
			/>

			<TextControl
				label={__('UKPRN (Optional)', 'apprenticeship-connect')}
				value={settings.ukprn}
				onChange={(value) => updateSetting('ukprn', value)}
				help={__('Filter vacancies by UK Provider Reference Number.', 'apprenticeship-connect')}
			/>

			<TextControl
				label={__('API Version', 'apprenticeship-connect')}
				value={settings.version}
				onChange={(value) => updateSetting('version', value)}
				help={__('API version header (X-Version).', 'apprenticeship-connect')}
			/>

			<TextControl
				label={__('Request Timeout (seconds)', 'apprenticeship-connect')}
				value={settings.timeout}
				onChange={(value) => updateSetting('timeout', parseInt(value, 10))}
				help={__('Maximum time to wait for API response (10-300 seconds).', 'apprenticeship-connect')}
				type="number"
				min={10}
				max={300}
			/>

			<TextControl
				label={__('Max Retries', 'apprenticeship-connect')}
				value={settings.retry_max}
				onChange={(value) => updateSetting('retry_max', parseInt(value, 10))}
				help={__('Number of retry attempts for failed requests (0-10).', 'apprenticeship-connect')}
				type="number"
				min={0}
				max={10}
			/>

			<TextControl
				label={__('Retry Delay (seconds)', 'apprenticeship-connect')}
				value={settings.retry_delay}
				onChange={(value) => updateSetting('retry_delay', parseInt(value, 10))}
				help={__('Delay between retry attempts.', 'apprenticeship-connect')}
				type="number"
				min={1}
				max={60}
			/>
		</div>
	);
};

export default APISettings;
