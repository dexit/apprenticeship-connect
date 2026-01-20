/**
 * Import Settings Component
 *
 * @package ApprenticeshipConnect
 */

import { __ } from '@wordpress/i18n';
import { Button, TextControl, ToggleControl, SelectControl, __experimentalText as Text, __experimentalSpacer as Spacer } from '@wordpress/components';

const ImportSettings = ({ settings, updateSetting, onReset }) => {
	return (
		<div className="apprco-settings-category">
			<div className="apprco-settings-category-header">
				<h3>{__('Import Settings', 'apprenticeship-connect')}</h3>
				<Button variant="tertiary" isDestructive onClick={onReset}>
					{__('Reset to Defaults', 'apprenticeship-connect')}
				</Button>
			</div>

			<Text variant="muted">{__('Configure how vacancies are imported and processed.', 'apprenticeship-connect')}</Text>

			<Spacer marginY={4} />

			<TextControl
				label={__('Batch Size', 'apprenticeship-connect')}
				value={settings.batch_size}
				onChange={(value) => updateSetting('batch_size', parseInt(value, 10))}
				help={__('Number of items to fetch per API request (1-1000).', 'apprenticeship-connect')}
				type="number"
				min={1}
				max={1000}
			/>

			<TextControl
				label={__('Max Pages', 'apprenticeship-connect')}
				value={settings.max_pages}
				onChange={(value) => updateSetting('max_pages', parseInt(value, 10))}
				help={__('Maximum number of pages to fetch per import (1-1000).', 'apprenticeship-connect')}
				type="number"
				min={1}
				max={1000}
			/>

			<TextControl
				label={__('Rate Limit Delay (ms)', 'apprenticeship-connect')}
				value={settings.rate_limit_delay}
				onChange={(value) => updateSetting('rate_limit_delay', parseInt(value, 10))}
				help={__('Delay between API requests to avoid rate limiting.', 'apprenticeship-connect')}
				type="number"
				min={0}
				max={5000}
			/>

			<SelectControl
				label={__('Duplicate Action', 'apprenticeship-connect')}
				value={settings.duplicate_action}
				onChange={(value) => updateSetting('duplicate_action', value)}
				options={[
					{ label: __('Update existing', 'apprenticeship-connect'), value: 'update' },
					{ label: __('Skip duplicates', 'apprenticeship-connect'), value: 'skip' },
					{ label: __('Create new (duplicates allowed)', 'apprenticeship-connect'), value: 'create' },
				]}
				help={__('How to handle vacancies that already exist.', 'apprenticeship-connect')}
			/>

			<SelectControl
				label={__('Post Status', 'apprenticeship-connect')}
				value={settings.post_status}
				onChange={(value) => updateSetting('post_status', value)}
				options={[
					{ label: __('Published', 'apprenticeship-connect'), value: 'publish' },
					{ label: __('Draft', 'apprenticeship-connect'), value: 'draft' },
					{ label: __('Pending Review', 'apprenticeship-connect'), value: 'pending' },
				]}
				help={__('Status for imported vacancies.', 'apprenticeship-connect')}
			/>

			<ToggleControl
				label={__('Delete Expired Vacancies', 'apprenticeship-connect')}
				checked={settings.delete_expired}
				onChange={(value) => updateSetting('delete_expired', value)}
				help={__('Automatically delete vacancies that are no longer in the API.', 'apprenticeship-connect')}
			/>

			{settings.delete_expired && (
				<TextControl
					label={__('Expire After (days)', 'apprenticeship-connect')}
					value={settings.expire_after_days}
					onChange={(value) => updateSetting('expire_after_days', parseInt(value, 10))}
					help={__('Delete vacancies not updated in this many days (1-365).', 'apprenticeship-connect')}
					type="number"
					min={1}
					max={365}
				/>
			)}
		</div>
	);
};

export default ImportSettings;
