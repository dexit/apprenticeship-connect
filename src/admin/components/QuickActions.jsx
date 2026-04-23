/**
 * QuickActions – navigation shortcuts for common admin tasks.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { upload, settings, list, download, update } from '@wordpress/icons';

/**
 * @param {Object}   props
 * @param {Function} props.onRunExpiry   Trigger expiry check.
 * @param {boolean}  props.expiryRunning Whether expiry check is in progress.
 */
const QuickActions = ( { onRunExpiry, expiryRunning } ) => {
	const base = 'admin.php?page=';

	return (
		<div style={ { display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 12 } }>
			<Button
				variant="primary"
				icon={ upload }
				href={ base + 'appcon-import-jobs' }
			>
				{ __( 'Import Jobs', 'apprenticeship-connector' ) }
			</Button>

			<Button
				variant="secondary"
				icon={ settings }
				href={ base + 'appcon-settings' }
			>
				{ __( 'Settings', 'apprenticeship-connector' ) }
			</Button>

			<Button
				variant="secondary"
				icon={ list }
				href={ 'edit.php?post_type=appcon_vacancy' }
			>
				{ __( 'All Vacancies', 'apprenticeship-connector' ) }
			</Button>

			<Button
				variant="secondary"
				icon={ download }
				href={ 'edit.php?post_type=appcon_employer' }
			>
				{ __( 'Employers', 'apprenticeship-connector' ) }
			</Button>

			<Button
				variant="secondary"
				icon={ update }
				isBusy={ expiryRunning }
				disabled={ expiryRunning }
				onClick={ onRunExpiry }
			>
				{ expiryRunning
					? __( 'Running…', 'apprenticeship-connector' )
					: __( 'Run Expiry Check', 'apprenticeship-connector' ) }
			</Button>
		</div>
	);
};

export default QuickActions;
