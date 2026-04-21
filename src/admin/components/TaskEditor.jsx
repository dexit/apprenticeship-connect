import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
    TextControl,
    TextareaControl,
    SelectControl,
    ToggleControl,
    Button,
    PanelBody,
    Notice,
    Card,
    CardBody,
    __experimentalHeading as Heading,
    __experimentalSpacer as Spacer
} from '@wordpress/components';

const TaskEditor = ({ taskId, onSave, onCancel }) => {
    const [task, setTask] = useState({
        name: '',
        description: '',
        status: 'active',
        provider_id: 'uk-gov-apprenticeships',
        api_base_url: 'https://api.apprenticeships.education.gov.uk/vacancies',
        api_endpoint: '/vacancy',
        api_headers: { 'X-Version': '2' },
        api_params: { 'PageSize': 100, 'Sort': 'AgeDesc', 'Routes': '', 'Levels': '' },
        page_param: 'PageNumber',
        data_path: 'vacancies',
        total_path: 'total',
        unique_id_field: 'vacancyReference',
        field_mappings: {
            'post_title': 'title',
            'post_content': 'fullDescription'
        },
        post_status: 'publish',
        schedule_enabled: true,
        schedule_frequency: 'daily',
        schedule_time: '03:00:00'
    });
    const [loading, setLoading] = useState(false);
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (taskId) loadTask();
    }, [taskId]);

    const loadTask = async () => {
        setLoading(true);
        try {
            const res = await apiFetch({ path: `/apprco/v1/tasks/${taskId}` });
            setTask(res);
        } catch (e) {
            setError(__('Failed to load task', 'apprenticeship-connect'));
        } finally {
            setLoading(false);
        }
    };

    const testConnection = async () => {
        setTesting(true);
        setTestResult(null);
        try {
            const res = await apiFetch({ path: '/apprco/v1/tasks/test', method: 'POST', data: task });
            setTestResult(res);
        } catch (e) {
            setError(__('Connection test failed', 'apprenticeship-connect'));
        } finally {
            setTesting(false);
        }
    };

    const saveTask = async () => {
        if (!task.name) { setError(__('Task name is required', 'apprenticeship-connect')); return; }
        setLoading(true);
        try {
            const path = taskId ? `/apprco/v1/tasks/${taskId}` : '/apprco/v1/tasks';
            await apiFetch({ path, method: 'POST', data: task });
            onSave();
        } catch (e) {
            setError(__('Failed to save task', 'apprenticeship-connect'));
        } finally {
            setLoading(false);
        }
    };

    if (loading) return <div style={{ padding: '20px' }}>{__('Loading task configuration...', 'apprenticeship-connect')}</div>;

    return (
        <div className="apprco-task-editor" style={{ background: '#fff', padding: '30px', borderRadius: '8px', border: '1px solid #ccd0d4' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '30px' }}>
                <Heading level={2}>{taskId ? __('Edit Import Task', 'apprenticeship-connect') : __('Create New Import Task', 'apprenticeship-connect')}</Heading>
                <div style={{ display: 'flex', gap: '10px' }}>
                    <Button variant="secondary" onClick={onCancel}>{__('Back to Dashboard', 'apprenticeship-connect')}</Button>
                    <Button variant="primary" onClick={saveTask}>{__('Save Configuration', 'apprenticeship-connect')}</Button>
                </div>
            </div>

            {error && <Notice status="error" onRemove={() => setError(null)}>{error}</Notice>}

            <div style={{ display: 'grid', gridTemplateColumns: '1.5fr 1fr', gap: '30px' }}>
                <div className="editor-main">
                    <PanelBody title={__('Core Information', 'apprenticeship-connect')} initialOpen={true}>
                        <TextControl label={__('Task Name', 'apprenticeship-connect')} value={task.name} onChange={v => setTask({...task, name: v})} placeholder="e.g., National Digital Vacancies" />
                        <TextareaControl label={__('Description', 'apprenticeship-connect')} value={task.description} onChange={v => setTask({...task, description: v})} />
                    </PanelBody>

                    <PanelBody title={__('UK Gov API V2 Parameters', 'apprenticeship-connect')} initialOpen={true}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px' }}>
                            <TextControl label={__('Postcode Filter', 'apprenticeship-connect')} value={task.api_params.Postcode || ''} onChange={v => setTask({...task, api_params: {...task.api_params, Postcode: v}})} help="e.g., SW1A 1AA" />
                            <TextControl label={__('Radius (Miles)', 'apprenticeship-connect')} value={task.api_params.DistanceInMiles || ''} onChange={v => setTask({...task, api_params: {...task.api_params, DistanceInMiles: v}})} type="number" />
                        </div>
                        <Spacer marginY={4} />
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '15px' }}>
                            <TextControl label={__('Routes (Comma Separated)', 'apprenticeship-connect')} value={task.api_params.Routes || ''} onChange={v => setTask({...task, api_params: {...task.api_params, Routes: v}})} help="Digital, Health, Construction" />
                            <TextControl label={__('Levels (Comma Separated)', 'apprenticeship-connect')} value={task.api_params.Levels || ''} onChange={v => setTask({...task, api_params: {...task.api_params, Levels: v}})} help="Intermediate, Advanced, Higher, Degree" />
                        </div>
                        <Spacer marginY={4} />
                        <SelectControl
                            label={__('Sorting', 'apprenticeship-connect')}
                            value={task.api_params.Sort}
                            options={[{label: 'Newest First', value: 'AgeDesc'}, {label: 'Oldest First', value: 'AgeAsc'}, {label: 'Closing Soonest', value: 'ClosingAsc'}, {label: 'Distance', value: 'DistanceAsc'}]}
                            onChange={v => setTask({...task, api_params: {...task.api_params, Sort: v}})}
                        />
                    </PanelBody>

                    <PanelBody title={__('Authentication', 'apprenticeship-connect')} initialOpen={false}>
                        <TextControl
                            label={__('Ocp-Apim-Subscription-Key', 'apprenticeship-connect')}
                            value={task.api_headers['Ocp-Apim-Subscription-Key'] || ''}
                            onChange={v => setTask({...task, api_headers: {...task.api_headers, 'Ocp-Apim-Subscription-Key': v}})}
                            type="password"
                        />
                    </PanelBody>
                </div>

                <div className="editor-sidebar">
                    <PanelBody title={__('Automation Settings', 'apprenticeship-connect')} initialOpen={true}>
                        <ToggleControl label={__('Enable Background Sync', 'apprenticeship-connect')} checked={task.schedule_enabled} onChange={v => setTask({...task, schedule_enabled: v})} />
                        {task.schedule_enabled && (
                            <SelectControl
                                label={__('Frequency', 'apprenticeship-connect')}
                                value={task.schedule_frequency}
                                options={[{label: 'Hourly', value: 'hourly'}, {label: 'Twice Daily', value: 'twicedaily'}, {label: 'Daily', value: 'daily'}, {label: 'Weekly', value: 'weekly'}]}
                                onChange={v => setTask({...task, schedule_frequency: v})}
                            />
                        )}
                        <SelectControl
                            label={__('Import Post Status', 'apprenticeship-connect')}
                            value={task.post_status}
                            options={[{label: 'Published', value: 'publish'}, {label: 'Draft', value: 'draft'}]}
                            onChange={v => setTask({...task, post_status: v})}
                        />
                    </PanelBody>

                    <div style={{ marginTop: '20px' }}>
                        <Button variant="secondary" isBusy={testing} onClick={testConnection} style={{ width: '100%', justifyContent: 'center', height: '40px' }}>
                            {__('Test API Connection', 'apprenticeship-connect')}
                        </Button>
                    </div>

                    {testResult && (
                        <Card style={{ marginTop: '20px', border: testResult.success ? '1px solid #00a32a' : '1px solid #d63638' }}>
                            <CardBody>
                                <Heading level={4}>{__('Live Preview', 'apprenticeship-connect')}</Heading>
                                {testResult.success ? (
                                    <div style={{ marginTop: '10px' }}>
                                        <p style={{ color: '#00a32a', fontSize: '13px' }}>✓ {__('Connection Successful', 'apprenticeship-connect')}</p>
                                        <p style={{ fontSize: '11px', color: '#646970' }}>{__('Found', 'apprenticeship-connect')} {testResult.data?.total || 0} {__('total vacancies matching filters.', 'apprenticeship-connect')}</p>
                                    </div>
                                ) : (
                                    <p style={{ color: '#d63638', fontSize: '13px' }}>✗ {testResult.error || __('Invalid API Key', 'apprenticeship-connect')}</p>
                                )}
                            </CardBody>
                        </Card>
                    )}
                </div>
            </div>
        </div>
    );
};

export default TaskEditor;
