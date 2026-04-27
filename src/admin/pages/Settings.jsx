/**
 * Settings Page Component
 *
 * Unified settings interface with all categories.
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
	Button,
	Spinner,
	Notice,
	TabPanel,
	TextControl,
	ToggleControl,
	SelectControl,
	RangeControl,
	__experimentalHeading as Heading,
} from '@wordpress/components';

import APISettings from '../components/settings/APISettings';
import ImportSettings from '../components/settings/ImportSettings';
import ScheduleSettings from '../components/settings/ScheduleSettings';
import DisplaySettings from '../components/settings/DisplaySettings';
import AdvancedSettings from '../components/settings/AdvancedSettings';

/**
 * Settings Component
 */
const Settings = () => {
	const [settings, setSettings] = useState(null);
	const [defaults, setDefaults] = useState(null);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState(null);
	const [success, setSuccess] = useState(null);

	useEffect(() => {
		loadSettings();
	}, []);

	/**
	 * Load settings from API
	 */
	const loadSettings = async () => {
		try {
			setLoading(true);
			const response = await apiFetch({
				path: '/apprco/v1/settings',
			});

			setSettings(response.settings);
			setDefaults(response.defaults);
			setError(null);
		} catch (err) {
			setError(err.message || __('Failed to load settings', 'apprenticeship-connect'));
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Save settings to API
	 */
	const saveSettings = async () => {
		setSaving(true);
		setError(null);
		setSuccess(null);

		try {
			const response = await apiFetch({
				path: '/apprco/v1/settings',
				method: 'POST',
				data: settings,
			});

			if (response.success) {
				setSuccess(__('Settings saved successfully!', 'apprenticeship-connect'));
				setSettings(response.settings);
			} else {
				setError(response.errors ? response.errors.join(', ') : __('Failed to save settings', 'apprenticeship-connect'));
			}
		} catch (err) {
			setError(err.message || __('Save request failed', 'apprenticeship-connect'));
		} finally {
			setSaving(false);
		}
	};

	/**
	 * Reset category to defaults
	 */
	const resetCategory = (category) => {
		if (!confirm(__('Are you sure you want to reset this category to defaults?', 'apprenticeship-connect'))) {
			return;
		}

		setSettings({
			...settings,
			[category]: { ...defaults[category] },
		});
		setSuccess(__('Category reset to defaults. Click "Save Settings" to apply.', 'apprenticeship-connect'));
	};

	/**
	 * Update a setting value
	 */
	const updateSetting = (category, key, value) => {
		setSettings({
			...settings,
			[category]: {
				...settings[category],
				[key]: value,
			},
		});
	};

	if (loading) {
		return (
			<div className="apprco-settings-loading">
				<Spinner />
			</div>
		);
	}

	const tabs = [
		{
			name: 'api',
			title: __('API', 'apprenticeship-connect'),
			className: 'apprco-tab-api',
		},
		{
			name: 'import',
			title: __('Import', 'apprenticeship-connect'),
			className: 'apprco-tab-import',
		},
		{
			name: 'schedule',
			title: __('Schedule', 'apprenticeship-connect'),
			className: 'apprco-tab-schedule',
		},
		{
			name: 'display',
			title: __('Display', 'apprenticeship-connect'),
			className: 'apprco-tab-display',
		},
		{
			name: 'advanced',
			title: __('Advanced', 'apprenticeship-connect'),
			className: 'apprco-tab-advanced',
		},
	];

	return (
		<div className="apprco-settings">
			<div className="apprco-settings-header">
				<Heading level={1}>{__('Plugin Settings', 'apprenticeship-connect')}</Heading>
			</div>

			{error && (
				<Notice status="error" isDismissible onRemove={() => setError(null)}>
					{error}
				</Notice>
			)}

			{success && (
				<Notice status="success" isDismissible onRemove={() => setSuccess(null)}>
					{success}
				</Notice>
			)}

			<Card>
				<CardBody>
					<TabPanel className="apprco-settings-tabs" tabs={tabs}>
						{(tab) => (
							<div className="apprco-settings-tab-content">
								{tab.name === 'api' && (
									<APISettings
										settings={settings.api}
										updateSetting={(key, value) => updateSetting('api', key, value)}
										onReset={() => resetCategory('api')}
									/>
								)}

								{tab.name === 'import' && (
									<ImportSettings
										settings={settings.import}
										updateSetting={(key, value) => updateSetting('import', key, value)}
										onReset={() => resetCategory('import')}
									/>
								)}

								{tab.name === 'schedule' && (
									<ScheduleSettings
										settings={settings.schedule}
										updateSetting={(key, value) => updateSetting('schedule', key, value)}
										onReset={() => resetCategory('schedule')}
									/>
								)}

								{tab.name === 'display' && (
									<DisplaySettings
										settings={settings.display}
										updateSetting={(key, value) => updateSetting('display', key, value)}
										onReset={() => resetCategory('display')}
									/>
								)}

								{tab.name === 'advanced' && (
									<AdvancedSettings
										settings={settings.advanced}
										updateSetting={(key, value) => updateSetting('advanced', key, value)}
										onReset={() => resetCategory('advanced')}
									/>
								)}
							</div>
						)}
					</TabPanel>
				</CardBody>
			</Card>

			<div className="apprco-settings-footer">
				<Button variant="primary" onClick={saveSettings} isBusy={saving} disabled={saving}>
					{saving ? __('Saving...', 'apprenticeship-connect') : __('Save Settings', 'apprenticeship-connect')}
				</Button>
			</div>
		</div>
	);
};

export default Settings;
