import { __ } from '@wordpress/i18n';
import { Button, TextControl, __experimentalHeading as Heading } from '@wordpress/components';

const APISettings = ({ settings, updateSetting, onReset }) => {
	return (
		<div className="apprco-settings-category">
			<div className="apprco-settings-category-header">
				<h3>{__('API Configuration', 'apprenticeship-connect')}</h3>
				<Button variant="tertiary" isDestructive onClick={onReset}>
					{__('Reset to Defaults', 'apprenticeship-connect')}
				</Button>
			</div>

			<TextControl
				label={__('API Base URL', 'apprenticeship-connect')}
				value={settings.base_url}
				onChange={(value) => updateSetting('base_url', value)}
				type="url"
			/>

			<TextControl
				label={__('Subscription Key', 'apprenticeship-connect')}
				value={settings.subscription_key}
				onChange={(value) => updateSetting('subscription_key', value)}
				type="password"
			/>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '15px' }}>
                <TextControl
                    label={__('Max Retries', 'apprenticeship-connect')}
                    value={settings.retry_max}
                    onChange={(v) => updateSetting('retry_max', parseInt(v, 10))}
                    type="number"
                />
                <TextControl
                    label={__('Retry Delay (ms)', 'apprenticeship-connect')}
                    value={settings.retry_delay_ms}
                    onChange={(v) => updateSetting('retry_delay_ms', parseInt(v, 10))}
                    type="number"
                />
                <TextControl
                    label={__('Backoff Multiplier', 'apprenticeship-connect')}
                    value={settings.retry_multiplier}
                    onChange={(v) => updateSetting('retry_multiplier', parseInt(v, 10))}
                    type="number"
                />
            </div>
		</div>
	);
};

export default APISettings;
