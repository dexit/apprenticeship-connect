/**
 * Two-Stage API configuration panel.
 */
import { TextControl, SelectControl, RangeControl, ToggleControl } from '@wordpress/components';

export default function TwoStageConfig( { job, onChange } ) {
	return (
		<div className="appcon-tab-content">
			<h3>API Credentials</h3>
			<TextControl
				label="API Base URL"
				value={ job.api_base_url }
				onChange={ ( v ) => onChange( 'api_base_url', v ) }
				type="url"
			/>
			<TextControl
				label="Subscription Key (Ocp-Apim-Subscription-Key)"
				value={ job.api_subscription_key }
				onChange={ ( v ) => onChange( 'api_subscription_key', v ) }
				type="password"
				autoComplete="off"
			/>

			<hr />

			<h3>Stage 1 – Vacancy List</h3>
			<ToggleControl
				label="Enable Stage 1"
				checked={ job.stage1_enabled }
				onChange={ ( v ) => onChange( 'stage1_enabled', v ) }
			/>
			{ job.stage1_enabled && (
				<>
					<RangeControl
						label={ `Page Size: ${ job.stage1_page_size }` }
						value={ job.stage1_page_size }
						min={ 10 }
						max={ 100 }
						step={ 10 }
						onChange={ ( v ) => onChange( 'stage1_page_size', v ) }
					/>
					<RangeControl
						label={ `Max Pages: ${ job.stage1_max_pages }` }
						value={ job.stage1_max_pages }
						min={ 1 }
						max={ 500 }
						step={ 1 }
						onChange={ ( v ) => onChange( 'stage1_max_pages', v ) }
					/>
					<SelectControl
						label="Sort Order"
						value={ job.stage1_sort }
						options={ [
							{ label: 'Newest first (AgeDesc)',    value: 'AgeDesc' },
							{ label: 'Oldest first (AgeAsc)',     value: 'AgeAsc' },
							{ label: 'Start date ascending',      value: 'ExpectedStartDateAsc' },
							{ label: 'Start date descending',     value: 'ExpectedStartDateDesc' },
						] }
						onChange={ ( v ) => onChange( 'stage1_sort', v ) }
					/>
				</>
			) }

			<hr />

			<h3>Stage 2 – Full Details</h3>
			<ToggleControl
				label="Enable Stage 2 (fetch full details)"
				checked={ job.stage2_enabled }
				onChange={ ( v ) => onChange( 'stage2_enabled', v ) }
				help="Stage 2 fetches the complete vacancy data including employer info, descriptions, skills, and qualifications."
			/>
			{ job.stage2_enabled && (
				<>
					<RangeControl
						label={ `Rate Limit Delay: ${ job.stage2_delay_ms }ms` }
						value={ job.stage2_delay_ms }
						min={ 100 }
						max={ 2000 }
						step={ 50 }
						onChange={ ( v ) => onChange( 'stage2_delay_ms', v ) }
						help="Minimum delay between Stage 2 API requests to respect rate limits."
					/>
					<RangeControl
						label={ `Batch Size: ${ job.stage2_batch_size }` }
						value={ job.stage2_batch_size }
						min={ 1 }
						max={ 50 }
						step={ 1 }
						onChange={ ( v ) => onChange( 'stage2_batch_size', v ) }
					/>
				</>
			) }
		</div>
	);
}
