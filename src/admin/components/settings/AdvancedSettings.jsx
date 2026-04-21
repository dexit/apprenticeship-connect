import { __ } from '@wordpress/i18n';
import { Button, ToggleControl, TextControl, __experimentalText as Text, __experimentalSpacer as Spacer } from '@wordpress/components';

const AdvancedSettings = ({ settings, updateSetting, onReset }) => {
	return (
		<div className="apprco-settings-category">
			<div className="apprco-settings-category-header">
				<h3>{__('Advanced Settings', 'apprenticeship-connect')}</h3>
				<Button variant="tertiary" isDestructive onClick={onReset}>
					{__('Reset to Defaults', 'apprenticeship-connect')}
				</Button>
			</div>

			<ToggleControl
				label={__('Enable Geocoding', 'apprenticeship-connect')}
				checked={settings.enable_geocoding}
				onChange={(val) => updateSetting('enable_geocoding', val)}
				help={__('Automatically resolve latitude/longitude for imported vacancies using OpenStreetMap.', 'apprenticeship-connect')}
			/>

			<ToggleControl
				label={__('Debug Mode', 'apprenticeship-connect')}
				checked={settings.debug_mode}
				onChange={(val) => updateSetting('debug_mode', val)}
				help={__('Enable verbose logging for troubleshooting.', 'apprenticeship-connect')}
			/>

            <TextControl
                label={__('Log Retention (Days)', 'apprenticeship-connect')}
                value={settings.log_retention_days}
                onChange={(val) => updateSetting('log_retention_days', parseInt(val, 10))}
                type="number"
            />
		</div>
	);
};

export default AdvancedSettings;
