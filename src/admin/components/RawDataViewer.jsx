import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Modal, Button } from '@wordpress/components';

const RawDataViewer = ({ data }) => {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <>
            <Button variant="link" onClick={() => setIsOpen(true)}>
                {__('View Raw API Data', 'apprenticeship-connect')}
            </Button>
            {isOpen && (
                <Modal title={__('Raw API Response Data', 'apprenticeship-connect')} onRequestClose={() => setIsOpen(false)}>
                    <pre style={{ background: '#f0f0f1', padding: '15px', borderRadius: '4px', fontSize: '11px', overflow: 'auto' }}>
                        {JSON.stringify(data, null, 2)}
                    </pre>
                    <div style={{ marginTop: '15px', textAlign: 'right' }}>
                        <Button variant="primary" onClick={() => setIsOpen(false)}>{__('Close', 'apprenticeship-connect')}</Button>
                    </div>
                </Modal>
            )}
        </>
    );
};

export default RawDataViewer;
