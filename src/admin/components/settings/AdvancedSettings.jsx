/**
 * Advanced Settings Component
 *
 * @package ApprenticeshipConnect
 */

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

			<Text variant="muted">{__('Advanced options for power users. Change with caution.', 'apprenticeship-connect')}</Text>

			<Spacer marginY={4} />

			<ToggleControl
				label={__('Enable Geocoding', 'apprenticeship-connect')}
				checked={settings.enable_geocoding}
				onChange={(value) => updateSetting('enable_geocoding', value)}
				help={__('Automatically geocode locations using OpenStreetMap Nominatim.', 'apprenticeship-connect')}
			/>

			<ToggleControl
				label={__('Enable Employer Database', 'apprenticeship-connect')}
				checked={settings.enable_employers}
				onChange={(value) => updateSetting('enable_employers', value)}
				help={__('Store employer information in separate database table.', 'apprenticeship-connect')}
			/>

			<ToggleControl
				label={__('Enable Detailed Logging', 'apprenticeship-connect')}
				checked={settings.enable_logging}
				onChange={(value) => updateSetting('enable_logging', value)}
				help={__('Log all import activity for debugging and monitoring.', 'apprenticeship-connect')}
			/>

			{settings.enable_logging && (
				<TextControl
					label={__('Log Retention (days)', 'apprenticeship-connect')}
					value={settings.log_retention_days}
					onChange={(value) => updateSetting('log_retention_days', parseInt(value, 10))}
					help={__('Delete logs older than this many days (1-365).', 'apprenticeship-connect')}
					type="number"
					min={1}
					max={365}
				/>
			)}

			<ToggleControl
				label={__('Debug Mode', 'apprenticeship-connect')}
				checked={settings.debug_mode}
				onChange={(value) => updateSetting('debug_mode', value)}
				help={__('Enable verbose logging and error output. Use for troubleshooting only.', 'apprenticeship-connect')}
			/>
		</div>
	);
};

export default AdvancedSettings;
