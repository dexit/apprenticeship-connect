/**
 * Field Mapper – visual interface for mapping API fields → WordPress fields.
 *
 * Uses Stage 2 sample to show the real API structure then lets the user
 * drag/type field paths into the mapping inputs.
 */
import { useState } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/* ── Default mappings ─────────────────────────────────────────────────── */
export function getDefaultMappings() {
	return {
		// Post
		post_title:   { api_path: 'title' },
		post_content: { api_path: 'description' },
		// Core
		_appcon_vacancy_reference:              { api_path: 'vacancyReference' },
		_appcon_vacancy_url:                    { api_path: 'vacancyUrl' },
		_appcon_application_url:                { api_path: 'applicationUrl' },
		// Dates
		_appcon_posted_date:                    { api_path: 'postedDate' },
		_appcon_closing_date:                   { api_path: 'closingDate' },
		_appcon_start_date:                     { api_path: 'startDate' },
		// Position
		_appcon_number_of_positions:            { api_path: 'numberOfPositions' },
		_appcon_hours_per_week:                 { api_path: 'hoursPerWeek' },
		_appcon_expected_duration:              { api_path: 'expectedDuration' },
		// Wage
		_appcon_wage_type:                      { api_path: 'wage.wageType' },
		_appcon_wage_amount:                    { api_path: 'wage.wageAmount' },
		_appcon_wage_unit:                      { api_path: 'wage.wageUnit' },
		_appcon_wage_additional_info:           { api_path: 'wage.wageAdditionalInformation' },
		_appcon_working_week_description:       { api_path: 'wage.workingWeekDescription' },
		// Employer (Stage 2)
		_appcon_employer_name:                  { api_path: 'employerName' },
		_appcon_employer_description:           { api_path: 'employerDescription' },
		_appcon_employer_website:               { api_path: 'employerWebsiteUrl' },
		_appcon_employer_contact_name:          { api_path: 'employerContactName' },
		_appcon_employer_contact_phone:         { api_path: 'employerContactPhone' },
		_appcon_employer_contact_email:         { api_path: 'employerContactEmail' },
		// Provider (Stage 2)
		_appcon_provider_name:                  { api_path: 'providerName' },
		_appcon_ukprn:                          { api_path: 'ukprn' },
		// Course
		_appcon_lars_code:                      { api_path: 'course.larsCode' },
		_appcon_course_title:                   { api_path: 'course.title' },
		_appcon_course_level:                   { api_path: 'course.level' },
		_appcon_course_route:                   { api_path: 'course.route' },
		_appcon_apprenticeship_level:           { api_path: 'apprenticeshipLevel' },
		// Descriptions (Stage 2)
		_appcon_training_description:           { api_path: 'trainingDescription' },
		_appcon_additional_training_description:{ api_path: 'additionalTrainingDescription' },
		_appcon_outcome_description:            { api_path: 'outcomeDescription' },
		_appcon_full_description:               { api_path: 'fullDescription' },
		_appcon_things_to_consider:             { api_path: 'thingsToConsider' },
		_appcon_company_benefits:               { api_path: 'companyBenefitsInformation' },
		// Flags
		_appcon_is_disability_confident:        { api_path: 'isDisabilityConfident' },
		_appcon_is_national_vacancy:            { api_path: 'isNationalVacancy' },
		// Arrays (Stage 2)
		_appcon_skills:                         { api_path: 'skills' },
		_appcon_qualifications:                 { api_path: 'qualifications' },
		// Taxonomies
		'taxonomy:appcon_level':                { api_path: 'course.level' },
		'taxonomy:appcon_route':                { api_path: 'course.route' },
		'taxonomy:appcon_lars_code':            { api_path: 'course.larsCode' },
		'taxonomy:appcon_skill':                { api_path: 'skills' },
		'taxonomy:appcon_employer':             { api_path: 'employerName' },
	};
}

/* ── Field groups for display ─────────────────────────────────────────── */
const FIELD_GROUPS = [
	{
		label: 'Post Fields',
		fields: [
			{ wp: 'post_title',   label: 'Post Title',   required: true },
			{ wp: 'post_content', label: 'Post Content' },
		],
	},
	{
		label: 'Core Vacancy',
		fields: [
			{ wp: '_appcon_vacancy_reference', label: 'Vacancy Reference', required: true },
			{ wp: '_appcon_vacancy_url',        label: 'Vacancy URL' },
			{ wp: '_appcon_application_url',    label: 'Application URL' },
			{ wp: '_appcon_posted_date',         label: 'Posted Date' },
			{ wp: '_appcon_closing_date',        label: 'Closing Date' },
			{ wp: '_appcon_start_date',          label: 'Start Date' },
			{ wp: '_appcon_number_of_positions', label: 'No. of Positions' },
			{ wp: '_appcon_hours_per_week',      label: 'Hours/Week' },
			{ wp: '_appcon_expected_duration',   label: 'Expected Duration' },
		],
	},
	{
		label: 'Wage',
		fields: [
			{ wp: '_appcon_wage_type',                label: 'Wage Type' },
			{ wp: '_appcon_wage_amount',              label: 'Wage Amount' },
			{ wp: '_appcon_wage_unit',                label: 'Wage Unit' },
			{ wp: '_appcon_wage_additional_info',     label: 'Wage Additional Info' },
			{ wp: '_appcon_working_week_description', label: 'Working Week' },
		],
	},
	{
		label: 'Employer (Stage 2)',
		fields: [
			{ wp: '_appcon_employer_name',          label: 'Employer Name',    badge: 'Stage 2' },
			{ wp: '_appcon_employer_description',   label: 'Description',      badge: 'Stage 2' },
			{ wp: '_appcon_employer_website',       label: 'Website',          badge: 'Stage 2' },
			{ wp: '_appcon_employer_contact_name',  label: 'Contact Name',     badge: 'Stage 2' },
			{ wp: '_appcon_employer_contact_phone', label: 'Contact Phone',    badge: 'Stage 2' },
			{ wp: '_appcon_employer_contact_email', label: 'Contact Email',    badge: 'Stage 2' },
		],
	},
	{
		label: 'Training Provider (Stage 2)',
		fields: [
			{ wp: '_appcon_provider_name', label: 'Provider Name', badge: 'Stage 2' },
			{ wp: '_appcon_ukprn',          label: 'UKPRN',         badge: 'Stage 2' },
		],
	},
	{
		label: 'Descriptions (Stage 2)',
		fields: [
			{ wp: '_appcon_training_description',            label: 'Training Description',            badge: 'Stage 2' },
			{ wp: '_appcon_additional_training_description', label: 'Additional Training Description',  badge: 'Stage 2' },
			{ wp: '_appcon_outcome_description',             label: 'Outcome Description',              badge: 'Stage 2' },
			{ wp: '_appcon_full_description',                label: 'Full Description',                 badge: 'Stage 2' },
			{ wp: '_appcon_things_to_consider',              label: 'Things to Consider',               badge: 'Stage 2' },
			{ wp: '_appcon_company_benefits',                label: 'Company Benefits',                 badge: 'Stage 2' },
		],
	},
	{
		label: 'Arrays (Stage 2)',
		fields: [
			{ wp: '_appcon_skills',        label: 'Skills',        badge: 'Stage 2 Array', isArray: true },
			{ wp: '_appcon_qualifications', label: 'Qualifications', badge: 'Stage 2 Array', isArray: true },
		],
	},
	{
		label: 'Taxonomies',
		fields: [
			{ wp: 'taxonomy:appcon_level',    label: 'Level Taxonomy' },
			{ wp: 'taxonomy:appcon_route',    label: 'Route Taxonomy' },
			{ wp: 'taxonomy:appcon_lars_code',label: 'LARS Code Taxonomy' },
			{ wp: 'taxonomy:appcon_skill',    label: 'Skill Taxonomy', isArray: true },
			{ wp: 'taxonomy:appcon_employer', label: 'Employer Taxonomy' },
		],
	},
];

/* ── Main component ───────────────────────────────────────────────────── */
export default function FieldMapper( { job, onChange } ) {
	const [ apiSample, setApiSample ] = useState( null );
	const [ loading, setLoading ]     = useState( false );
	const [ error, setError ]         = useState( null );
	const [ mappings, setMappings ]   = useState(
		Object.keys( job.field_mappings ?? {} ).length > 0
			? job.field_mappings
			: getDefaultMappings()
	);

	const fetchSample = async () => {
		if ( ! job.id ) return;
		setLoading( true );
		setError( null );
		try {
			const res = await apiFetch( {
				path:   '/appcon/v1/test/api',
				method: 'POST',
				data:   { job_id: job.id, test_type: 'stage2_sample' },
			} );
			if ( res.success && res.sample ) {
				setApiSample( res.sample );
			} else {
				setError( res.stage2_error ?? res.error ?? 'Failed to fetch sample' );
			}
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	};

	const updateMapping = ( wpField, apiPath ) => {
		const next = { ...mappings, [ wpField ]: { api_path: apiPath } };
		setMappings( next );
		onChange( next );
	};

	const resetDefaults = () => {
		const defaults = getDefaultMappings();
		setMappings( defaults );
		onChange( defaults );
	};

	return (
		<div className="appcon-field-mapper">
			<div className="mapper-header">
				<div>
					<h3 style={ { margin: 0 } }>Field Mapping Configuration</h3>
					<p style={ { margin: '4px 0 0', color: '#646970' } }>
						Map API response fields (Stage 2) to WordPress post/meta/taxonomy fields.
					</p>
				</div>
				<div style={ { display: 'flex', gap: 8 } }>
					{ job.id && (
						<Button
							variant="secondary"
							onClick={ fetchSample }
							disabled={ loading }
						>
							{ loading ? <><Spinner /> Loading Sample…</> : 'Load API Sample' }
						</Button>
					) }
					<Button variant="tertiary" onClick={ resetDefaults }>
						Reset to Defaults
					</Button>
				</div>
			</div>

			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<div className={ apiSample ? 'mapper-grid' : '' }>
				{ apiSample && (
					<div>
						<h4>API Structure (Stage 2 Sample)</h4>
						<div style={ { maxHeight: 600, overflow: 'auto', border: '1px solid #e2e8f0', padding: 8, borderRadius: 4 } }>
							<APIFieldTree
								data={ apiSample }
								onSelect={ ( path ) => {
									navigator.clipboard?.writeText( path );
								} }
							/>
						</div>
						<p style={ { fontSize: '0.8rem', color: '#646970' } }>
							Click a field to copy its path to clipboard.
						</p>
					</div>
				) }

				<div>
					{ FIELD_GROUPS.map( ( group ) => (
						<fieldset key={ group.label } className="mapping-section">
							<legend>{ group.label }</legend>
							{ group.fields.map( ( field ) => (
								<MappingRow
									key={ field.wp }
									label={ field.label }
									wpField={ field.wp }
									required={ field.required }
									isArray={ field.isArray }
									badge={ field.badge }
									currentPath={ mappings[ field.wp ]?.api_path ?? '' }
									suggestedPath={ getDefaultMappings()[ field.wp ]?.api_path ?? '' }
									onUpdate={ ( path ) => updateMapping( field.wp, path ) }
								/>
							) ) }
						</fieldset>
					) ) }
				</div>
			</div>
		</div>
	);
}

/* ── Mapping row ──────────────────────────────────────────────────────── */
function MappingRow( { label, wpField, required, isArray, badge, currentPath, suggestedPath, onUpdate } ) {
	const [ val, setVal ] = useState( currentPath );

	const handleChange = ( v ) => {
		setVal( v );
		onUpdate( v );
	};

	return (
		<div className="mapping-row">
			<label style={ { fontSize: '0.85rem', paddingTop: 4 } }>
				{ label }
				{ required && <span className="required">*</span> }
				{ badge && <span className="badge">{ badge }</span> }
				{ isArray && <span className="badge-array">[]</span> }
			</label>
			<div>
				<input
					type="text"
					className="widefat"
					value={ val }
					onChange={ ( e ) => handleChange( e.target.value ) }
					placeholder={ suggestedPath || 'field.path' }
					style={ { fontSize: '0.85rem' } }
				/>
				{ suggestedPath && val !== suggestedPath && (
					<button
						type="button"
						className="button button-small"
						style={ { marginTop: 4 } }
						onClick={ () => handleChange( suggestedPath ) }
					>
						Use: { suggestedPath }
					</button>
				) }
			</div>
			<code className="wp-field" style={ { fontSize: '0.7rem' } }>{ wpField }</code>
		</div>
	);
}

/* ── API field tree ───────────────────────────────────────────────────── */
function APIFieldTree( { data, onSelect, path = '' } ) {
	if ( typeof data !== 'object' || data === null ) {
		return (
			<span className="tree-value" onClick={ () => onSelect( path ) } title="Click to copy path">
				{ String( data ) }
			</span>
		);
	}

	return (
		<ul style={ { margin: 0, paddingLeft: 16, listStyle: 'none' } }>
			{ Object.entries( data ).map( ( [ key, value ] ) => {
				const fullPath = path ? `${ path }.${ key }` : key;
				const isArr    = Array.isArray( value );

				return (
					<li key={ key } style={ { margin: '2px 0' } }>
						<span
							className="tree-key"
							onClick={ () => onSelect( fullPath ) }
							title={ `Click to copy: ${ fullPath }` }
						>
							{ key }{ isArr ? <span className="badge-array">[]</span> : '' }
						</span>
						{ typeof value === 'object' && value !== null && ! isArr && (
							<APIFieldTree data={ value } onSelect={ onSelect } path={ fullPath } />
						) }
						{ ( typeof value !== 'object' || isArr ) && (
							<span className="tree-value">
								: { isArr ? `[${ value.length } items]` : String( value ) }
							</span>
						) }
					</li>
				);
			} ) }
		</ul>
	);
}
