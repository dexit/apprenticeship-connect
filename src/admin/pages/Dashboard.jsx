import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	Button,
	Spinner,
	Notice,
	__experimentalHeading as Heading,
} from '@wordpress/components';

// Simple components for the V3.1.0 Dashboard
const RateLimitInfo = ({ stats }) => (
    <Card style={{ marginBottom: '20px' }}>
        <CardBody>
            <Heading level={4}>{__('API Rate Limiting', 'apprenticeship-connect')}</Heading>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', marginTop: '10px' }}>
                <div>{__('Requests Remaining:', 'apprenticeship-connect')} <strong>{stats?.remaining || '---'}</strong></div>
                <div>{__('Reset Time:', 'apprenticeship-connect')} <strong>{stats?.reset_at || '---'}</strong></div>
            </div>
        </CardBody>
    </Card>
);

const FancyLogViewer = ({ importId, active }) => {
    const [logs, setLogs] = useState([]);

    useEffect(() => {
        if (!active || !importId) return;
        const interval = setInterval(async () => {
            try {
                const res = await apiFetch({ path: `/apprco/v1/import/logs/${importId}` });
                setLogs(res.logs || []);
            } catch (e) {}
        }, 2000);
        return () => clearInterval(interval);
    }, [importId, active]);

    return (
        <div style={{ maxHeight: '300px', overflowY: 'auto', background: '#1e1e1e', color: '#00ff00', padding: '15px', borderRadius: '4px', fontFamily: 'monospace', fontSize: '12px' }}>
            {logs.map((log, i) => (
                <div key={i} style={{ marginBottom: '4px' }}>
                    <span style={{ color: '#888' }}>[{log.created_at}]</span> [{log.level}] {log.message}
                </div>
            ))}
            {logs.length === 0 && <div>{__('Waiting for logs...', 'apprenticeship-connect')}</div>}
        </div>
    );
};

const Dashboard = () => {
    const [stats, setStats] = useState(null);
    const [tasks, setTasks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [runningTaskId, setRunningTaskId] = useState(null);
    const [lastImportId, setLastImportId] = useState(null);

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        try {
            setLoading(true);
            const [statsRes, tasksRes] = await Promise.all([
                apiFetch({ path: '/apprco/v1/stats' }),
                apiFetch({ path: '/apprco/v1/tasks' })
            ]);
            setStats(statsRes);
            setTasks(tasksRes.tasks || []);
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    const runTask = async (id) => {
        setRunningTaskId(id);
        try {
            const res = await apiFetch({
                path: `/apprco/v1/tasks/${id}/run`,
                method: 'POST'
            });
            if (res.success) {
                setLastImportId(res.import_id);
                loadData();
            }
        } catch (e) {
            alert('Failed to run task');
        } finally {
            setRunningTaskId(null);
        }
    };

    if (loading) return <Spinner />;

    return (
        <div style={{ padding: '20px' }}>
            <Heading level={1}>{__('Apprenticeship Connect V3.1.0', 'apprenticeship-connect')}</Heading>

            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '30px', marginTop: '20px' }}>
                <div>
                    <Heading level={2}>{__('Import Tasks', 'apprenticeship-connect')}</Heading>
                    <div style={{ marginTop: '15px' }}>
                        {tasks.map(task => (
                            <Card key={task.id} style={{ marginBottom: '15px' }}>
                                <CardBody>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <div>
                                            <Heading level={3} style={{ margin: 0 }}>{task.name}</Heading>
                                            <div style={{ fontSize: '13px', color: '#666', marginTop: '5px' }}>
                                                <span><strong>{__('Status:', 'apprenticeship-connect')}</strong> {task.status}</span>
                                                <span style={{ marginLeft: '15px' }}><strong>{__('Last Run:', 'apprenticeship-connect')}</strong> {task.last_run_at || __('Never', 'apprenticeship-connect')}</span>
                                                <span style={{ marginLeft: '15px' }}><strong>{__('Total Runs:', 'apprenticeship-connect')}</strong> {task.total_runs}</span>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', gap: '10px' }}>
                                            <Button
                                                variant="primary"
                                                onClick={() => runTask(task.id)}
                                                isBusy={runningTaskId === task.id}
                                                disabled={runningTaskId !== null}
                                            >
                                                {__('Sync Now', 'apprenticeship-connect')}
                                            </Button>
                                        </div>
                                    </div>
                                </CardBody>
                            </Card>
                        ))}
                        {tasks.length === 0 && (
                            <Notice status="info" isDismissible={false}>{__('No import tasks configured. Go to Import Tasks page to add one.', 'apprenticeship-connect')}</Notice>
                        )}
                    </div>
                </div>

                <div>
                    <Heading level={2}>{__('System Health', 'apprenticeship-connect')}</Heading>
                    <div style={{ marginTop: '15px' }}>
                        <RateLimitInfo stats={stats?.resilience} />

                        <Card>
                            <CardBody>
                                <ul style={{ margin: 0, padding: 0, listStyle: 'none' }}>
                                    <li style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f0f0f1' }}>
                                        <span>{__('Log Entries', 'apprenticeship-connect')}</span>
                                        <strong>{stats?.total_logs || 0}</strong>
                                    </li>
                                    <li style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0' }}>
                                        <span>{__('Import Runs', 'apprenticeship-connect')}</span>
                                        <strong>{stats?.total_runs || 0}</strong>
                                    </li>
                                </ul>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>

            {lastImportId && (
                <div style={{ marginTop: '40px' }}>
                    <Heading level={2}>{__('Real-Time Import Log', 'apprenticeship-connect')}</Heading>
                    <div style={{ marginTop: '15px' }}>
                        <FancyLogViewer importId={lastImportId} active={true} />
                    </div>
                </div>
            )}
        </div>
    );
};

export default Dashboard;
