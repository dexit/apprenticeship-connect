/**
 * Schedule Settings Component
 *
 * @package ApprenticeshipConnect
 */

import { __ } from '@wordpress/i18n';
import { Button, ToggleControl, SelectControl, TextControl, __experimentalText as Text, __experimentalSpacer as Spacer } from '@wordpress/components';

const ScheduleSettings = ({ settings, updateSetting, onReset }) => {
	return (
		<div className="apprco-settings-category">
			<div className="apprco-settings-category-header">
				<h3>{__('Scheduling', 'apprenticeship-connect')}</h3>
				<Button variant="tertiary" isDestructive onClick={onReset}>
					{__('Reset to Defaults', 'apprenticeship-connect')}
				</Button>
			</div>

			<Text variant="muted">{__('Configure automated import scheduling.', 'apprenticeship-connect')}</Text>

			<Spacer marginY={4} />

			<ToggleControl
				label={__('Enable Scheduled Imports', 'apprenticeship-connect')}
				checked={settings.enabled}
				onChange={(value) => updateSetting('enabled', value)}
				help={__('Run imports automatically on a schedule.', 'apprenticeship-connect')}
			/>

			{settings.enabled && (
				<>
					<SelectControl
						label={__('Frequency', 'apprenticeship-connect')}
						value={settings.frequency}
						onChange={(value) => updateSetting('frequency', value)}
						options={[
							{ label: __('Hourly', 'apprenticeship-connect'), value: 'hourly' },
							{ label: __('Twice Daily', 'apprenticeship-connect'), value: 'twicedaily' },
							{ label: __('Daily', 'apprenticeship-connect'), value: 'daily' },
							{ label: __('Weekly', 'apprenticeship-connect'), value: 'weekly' },
						]}
						help={__('How often to run automatic imports.', 'apprenticeship-connect')}
					/>

					<TextControl
						label={__('Schedule Time', 'apprenticeship-connect')}
						value={settings.time}
						onChange={(value) => updateSetting('time', value)}
						help={__('Time of day to run imports (HH:MM format, 24-hour).', 'apprenticeship-connect')}
						type="time"
					/>

					<ToggleControl
						label={__('Use Action Scheduler', 'apprenticeship-connect')}
						checked={settings.use_action_scheduler}
						onChange={(value) => updateSetting('use_action_scheduler', value)}
						help={__(
							'Use Action Scheduler if available (recommended). Falls back to WP-Cron if disabled or not available.',
							'apprenticeship-connect'
						)}
					/>
				</>
			)}
		</div>
	);
};

export default ScheduleSettings;
