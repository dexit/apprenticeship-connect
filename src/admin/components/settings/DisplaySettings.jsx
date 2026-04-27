/**
 * Display Settings Component
 *
 * @package ApprenticeshipConnect
 */

import { __ } from '@wordpress/i18n';
import { Button, TextControl, ToggleControl, __experimentalText as Text, __experimentalSpacer as Spacer } from '@wordpress/components';

const DisplaySettings = ({ settings, updateSetting, onReset }) => {
	return (
		<div className="apprco-settings-category">
			<div className="apprco-settings-category-header">
				<h3>{__('Display Options', 'apprenticeship-connect')}</h3>
				<Button variant="tertiary" isDestructive onClick={onReset}>
					{__('Reset to Defaults', 'apprenticeship-connect')}
				</Button>
			</div>

			<Text variant="muted">{__('Configure how vacancies are displayed on the frontend.', 'apprenticeship-connect')}</Text>

			<Spacer marginY={4} />

			<TextControl
				label={__('Items Per Page', 'apprenticeship-connect')}
				value={settings.items_per_page}
				onChange={(value) => updateSetting('items_per_page', parseInt(value, 10))}
				help={__('Number of vacancies to show per page (1-100).', 'apprenticeship-connect')}
				type="number"
				min={1}
				max={100}
			/>

			<ToggleControl
				label={__('Show Employer Name', 'apprenticeship-connect')}
				checked={settings.show_employer}
				onChange={(value) => updateSetting('show_employer', value)}
			/>

			<ToggleControl
				label={__('Show Location', 'apprenticeship-connect')}
				checked={settings.show_location}
				onChange={(value) => updateSetting('show_location', value)}
			/>

			<ToggleControl
				label={__('Show Salary', 'apprenticeship-connect')}
				checked={settings.show_salary}
				onChange={(value) => updateSetting('show_salary', value)}
			/>

			<ToggleControl
				label={__('Show Closing Date', 'apprenticeship-connect')}
				checked={settings.show_closing_date}
				onChange={(value) => updateSetting('show_closing_date', value)}
			/>

			<ToggleControl
				label={__('Show Apply Button', 'apprenticeship-connect')}
				checked={settings.show_apply_button}
				onChange={(value) => updateSetting('show_apply_button', value)}
			/>

			<TextControl
				label={__('Date Format', 'apprenticeship-connect')}
				value={settings.date_format}
				onChange={(value) => updateSetting('date_format', value)}
				help={__(
					'PHP date format for displaying dates (e.g., F j, Y for "January 1, 2024"). See PHP date() documentation.',
					'apprenticeship-connect'
				)}
			/>
		</div>
	);
};

export default DisplaySettings;
