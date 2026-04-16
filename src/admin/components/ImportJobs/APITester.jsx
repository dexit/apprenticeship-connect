/**
 * API Tester – test Stage 1 and Stage 2 connectivity for a job.
 */
import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export default function APITester( { job } ) {
	const [ result, setResult ]   = useState( null );
	const [ loading, setLoading ] = useState( false );

	const runTest = async ( testType ) => {
		setLoading( true );
		setResult( null );
		try {
			const res = await apiFetch( {
				path:   '/appcon/v1/test/api',
				method: 'POST',
				data:   { job_id: job.id, test_type: testType },
			} );
			setResult( res );
		} catch ( err ) {
			setResult( { success: false, error: err.message } );
		} finally {
			setLoading( false );
		}
	};

	return (
		<div className="appcon-api-tester" style={ { padding: '8px 0' } }>
			<p>
				Use these buttons to verify API connectivity and inspect a real Stage 2 response.
			</p>
			<div style={ { display: 'flex', gap: 8, marginBottom: 16 } }>
				<Button variant="secondary" onClick={ () => runTest( 'stage1' ) } disabled={ loading }>
					{ loading ? 'Testing…' : 'Test Stage 1 (List)' }
				</Button>
				<Button variant="secondary" onClick={ () => runTest( 'stage2_sample' ) } disabled={ loading }>
					{ loading ? 'Testing…' : 'Test Stage 2 (Full Sample)' }
				</Button>
			</div>

			{ result && (
				<>
					<Notice
						status={ result.success ? 'success' : 'error' }
						isDismissible={ false }
					>
						{ result.success
							? `API connected ✓  |  Total vacancies: ${ result.total }`
							: `Error: ${ result.error }` }
					</Notice>

					{ result.sample && (
						<details style={ { marginTop: 12 } }>
							<summary style={ { cursor: 'pointer', fontWeight: 600 } }>
								Stage 2 Sample Response
							</summary>
							<pre style={ { maxHeight: 400, overflow: 'auto', background: '#f6f7f7', padding: 12, borderRadius: 4, fontSize: '0.8rem' } }>
								{ JSON.stringify( result.sample, null, 2 ) }
							</pre>
						</details>
					) }
				</>
			) }
		</div>
	);
}
