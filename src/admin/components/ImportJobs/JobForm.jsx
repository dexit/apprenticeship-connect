/**
 * Job create/edit form with tabbed sections.
 */
import { useState } from '@wordpress/element';
import { Button, TextControl, SelectControl, ToggleControl, Notice, TabPanel } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import FieldMapper     from './FieldMapper';
import TwoStageConfig  from './TwoStageConfig';
import APITester       from './APITester';

const DEFAULT_JOB = {
	name:                 '',
	description:          '',
	status:               'draft',
	api_base_url:         'https://api.apprenticeships.education.gov.uk/vacancies',
	api_subscription_key: '',
	stage1_enabled:       true,
	stage1_page_size:     100,
	stage1_max_pages:     100,
	stage1_sort:          'AgeDesc',
	stage1_filters:       {},
	stage2_enabled:       true,
	stage2_delay_ms:      2000,
	stage2_batch_size:    10,
	field_mappings:       {},
	schedule_enabled:     false,
	schedule_frequency:   'daily',
};

export default function JobForm( { job = {}, onSave, onCancel } ) {
	const [ form, setForm ]     = useState( { ...DEFAULT_JOB, ...job } );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ]   = useState( null );

	const update = ( key, value ) => setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );

	const handleSave = async () => {
		setSaving( true );
		setError( null );
		try {
			const method = form.id ? 'PUT' : 'POST';
			const path   = form.id
				? `/appcon/v1/import-jobs/${ form.id }`
				: '/appcon/v1/import-jobs';

			await apiFetch( { path, method, data: form } );
			onSave();
		} catch ( err ) {
			setError( err.message );
		} finally {
			setSaving( false );
		}
	};

	const tabs = [
		{ name: 'general',     title: 'General',      className: '' },
		{ name: 'api',         title: 'API & Stage Config', className: '' },
		{ name: 'field-map',   title: 'Field Mapping', className: '' },
		{ name: 'schedule',    title: 'Schedule',      className: '' },
		{ name: 'test',        title: 'Test API',      className: '' },
	];

	return (
		<div className="appcon-job-form">
			<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 } }>
				<h2>{ form.id ? 'Edit Import Job' : 'New Import Job' }</h2>
				<Button variant="tertiary" onClick={ onCancel }>← Back</Button>
			</div>

			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( null ) }>{ error }</Notice>
			) }

			<TabPanel tabs={ tabs }>
				{ ( tab ) => {
					switch ( tab.name ) {
						case 'general':
							return (
								<div className="appcon-tab-content">
									<TextControl
										label="Job Name *"
										value={ form.name }
										onChange={ ( v ) => update( 'name', v ) }
									/>
									<TextControl
										label="Description"
										value={ form.description }
										onChange={ ( v ) => update( 'description', v ) }
									/>
									<SelectControl
										label="Status"
										value={ form.status }
										options={ [
											{ label: 'Draft',    value: 'draft' },
											{ label: 'Active',   value: 'active' },
											{ label: 'Inactive', value: 'inactive' },
										] }
										onChange={ ( v ) => update( 'status', v ) }
									/>
								</div>
							);

						case 'api':
							return (
								<TwoStageConfig job={ form } onChange={ ( key, val ) => update( key, val ) } />
							);

						case 'field-map':
							return (
								<FieldMapper
									job={ form }
									onChange={ ( mappings ) => update( 'field_mappings', mappings ) }
								/>
							);

						case 'schedule':
							return (
								<div className="appcon-tab-content">
									<ToggleControl
										label="Enable Schedule"
										checked={ form.schedule_enabled }
										onChange={ ( v ) => update( 'schedule_enabled', v ) }
									/>
									{ form.schedule_enabled && (
										<SelectControl
											label="Frequency"
											value={ form.schedule_frequency }
											options={ [
												{ label: 'Hourly',  value: 'hourly' },
												{ label: 'Daily',   value: 'daily' },
												{ label: 'Weekly',  value: 'weekly' },
											] }
											onChange={ ( v ) => update( 'schedule_frequency', v ) }
										/>
									) }
								</div>
							);

						case 'test':
							return form.id
								? <APITester job={ form } />
								: <p>Save the job first to test the API connection.</p>;

						default:
							return null;
					}
				} }
			</TabPanel>

			<div style={ { marginTop: 24, display: 'flex', gap: 8 } }>
				<Button variant="primary" isBusy={ saving } onClick={ handleSave } disabled={ saving }>
					{ saving ? 'Saving…' : 'Save Job' }
				</Button>
				<Button variant="tertiary" onClick={ onCancel }>Cancel</Button>
			</div>
		</div>
	);
}
