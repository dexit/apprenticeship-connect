/**
 * APIStatus – tests live connectivity to the configured API endpoint.
 *
 * Uses POST /wp-json/appcon/v1/test/connectivity (no job_id required).
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';

const APIStatus = () => {
	const [ testing, setTesting ] = useState( false );
	const [ result, setResult ]   = useState( null );

	const testConnection = async () => {
		setTesting( true );
		setResult( null );
		try {
			const res = await apiFetch( {
				path:   '/appcon/v1/test/connectivity',
				method: 'POST',
			} );
			setResult( res );
		} catch ( e ) {
			setResult( {
				success: false,
				error:   e.message || __( 'Request failed.', 'apprenticeship-connector' ),
			} );
		} finally {
			setTesting( false );
		}
	};

	const statusBadge = () => {
		if ( ! result ) {
			return (
				<span style={ { color: '#6b7280', fontSize: 13 } }>
					{ __( 'Not yet tested', 'apprenticeship-connector' ) }
				</span>
			);
		}
		if ( result.success ) {
			return (
				<span style={ { color: '#15803d', fontWeight: 600, fontSize: 13 } }>
					&#10003;&nbsp;{ result.message ?? __( 'Connected', 'apprenticeship-connector' ) }
				</span>
			);
		}
		return (
			<span style={ { color: '#b91c1c', fontWeight: 600, fontSize: 13 } }>
				&#10007;&nbsp;{ result.error ?? __( 'Failed', 'apprenticeship-connector' ) }
			</span>
		);
	};

	return (
		<div style={ { display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' } }>
			<Button
				variant="secondary"
				isBusy={ testing }
				disabled={ testing }
				onClick={ testConnection }
			>
				{ testing
					? __( 'Testing…', 'apprenticeship-connector' )
					: __( 'Test Connection', 'apprenticeship-connector' ) }
			</Button>
			{ statusBadge() }
		</div>
	);
};

export default APIStatus;
