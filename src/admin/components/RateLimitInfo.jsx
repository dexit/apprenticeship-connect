import { __ } from '@wordpress/i18n';
import { Card, CardBody, Icon } from '@wordpress/components';
import { info, warning, check } from '@wordpress/icons';

const RateLimitInfo = ({ stats }) => {
    return (
        <Card className="apprco-rate-limit-card" style={{ marginBottom: '20px', borderLeft: '4px solid #2271b1' }}>
            <CardBody>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '10px' }}>
                    <Icon icon={info} />
                    <h3 style={{ margin: 0 }}>{__('API Resilience & Rate Limiting', 'apprenticeship-connect')}</h3>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))', gap: '15px' }}>
                    <div className="stat-item">
                        <span style={{ display: 'block', fontSize: '11px', color: '#646970' }}>{__('Total Requests', 'apprenticeship-connect')}</span>
                        <span style={{ fontSize: '18px', fontWeight: '600' }}>{stats?.requests || 0}</span>
                    </div>
                    <div className="stat-item">
                        <span style={{ display: 'block', fontSize: '11px', color: '#646970' }}>{__('Cache Hits', 'apprenticeship-connect')}</span>
                        <span style={{ fontSize: '18px', fontWeight: '600', color: '#00a32a' }}>{stats?.cache_hits || 0}</span>
                    </div>
                    <div className="stat-item">
                        <span style={{ display: 'block', fontSize: '11px', color: '#646970' }}>{__('Active Backoff', 'apprenticeship-connect')}</span>
                        <span style={{ fontSize: '18px', fontWeight: '600', color: stats?.backoff ? '#d63638' : '#00a32a' }}>
                            {stats?.backoff ? __('YES', 'apprenticeship-connect') : __('NO', 'apprenticeship-connect')}
                        </span>
                    </div>
                </div>
                <p style={{ fontSize: '12px', marginTop: '10px', color: '#646970' }}>
                    {__('The system automatically detects HTTP 429 (Rate Limit) and implements exponential backoff to ensure reliable imports.', 'apprenticeship-connect')}
                </p>
            </CardBody>
        </Card>
    );
};

export default RateLimitInfo;
