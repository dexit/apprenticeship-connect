import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, Button, Spinner, Notice, __experimentalHeading as Heading } from '@wordpress/components';
import FancyLogViewer from '../components/FancyLogViewer';
import RateLimitInfo from '../components/RateLimitInfo';
import TaskEditor from '../components/TaskEditor';

const Dashboard = () => {
    const [tasks, setTasks] = useState([]);
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [runningTaskId, setRunningTaskId] = useState(null);
    const [lastImportId, setLastImportId] = useState(null);
    const [editingTaskId, setEditingTaskId] = useState(null);
    const [isCreating, setIsCreating] = useState(false);

    useEffect(() => {
        loadData();
        const interval = setInterval(loadStats, 5000);
        return () => clearInterval(interval);
    }, []);

    const loadData = async () => {
        setLoading(true);
        try {
            const [tRes, sRes] = await Promise.all([
                apiFetch({ path: '/apprco/v1/tasks' }),
                apiFetch({ path: '/apprco/v1/stats' })
            ]);
            setTasks(tRes);
            setStats(sRes);
        } catch (e) {
        } finally {
            setLoading(false);
        }
    };

    const loadStats = async () => {
        try {
            const sRes = await apiFetch({ path: '/apprco/v1/stats' });
            setStats(sRes);
        } catch (e) {}
    };

    const runTask = async (id) => {
        setRunningTaskId(id);
        setLastImportId(null);
        try {
            const res = await apiFetch({ path: `/apprco/v1/tasks/${id}/run`, method: 'POST' });
            if (res.import_id) setLastImportId(res.import_id);
            loadData();
        } catch (e) {
        } finally {
            setRunningTaskId(null);
        }
    };

    const handleTaskSaved = () => {
        setEditingTaskId(null);
        setIsCreating(false);
        loadData();
    };

    if (loading) return <div style={{ padding: '40px', textAlign: 'center' }}><Spinner /></div>;

    if (editingTaskId || isCreating) {
        return (
            <div style={{ padding: '20px' }}>
                <TaskEditor
                    taskId={editingTaskId}
                    onSave={handleTaskSaved}
                    onCancel={() => { setEditingTaskId(null); setIsCreating(false); }}
                />
            </div>
        );
    }

    return (
        <div className="apprco-dashboard" style={{ padding: '20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '30px' }}>
                <Heading level={1}>{__('Apprenticeship Connect Dashboard', 'apprenticeship-connect')}</Heading>
                <Button variant="primary" onClick={() => setIsCreating(true)}>{__('Add New Import Task', 'apprenticeship-connect')}</Button>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '30px' }}>
                <div>
                    <Heading level={2}>{__('Import Tasks', 'apprenticeship-connect')}</Heading>
                    <div className="apprco-task-grid" style={{ display: 'grid', gridTemplateColumns: '1fr', gap: '20px', marginTop: '15px' }}>
                        {tasks.map(task => (
                            <Card key={task.id}>
                                <CardBody>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                        <div>
                                            <Heading level={3} style={{ margin: '0 0 5px 0' }}>{task.name}</Heading>
                                            <p style={{ margin: '0 0 15px 0', color: '#646970' }}>{task.description}</p>
                                            <div style={{ display: 'flex', gap: '15px', fontSize: '12px' }}>
                                                <span style={{ textTransform: 'capitalize' }}><strong>{__('Status:', 'apprenticeship-connect')}</strong> {task.status}</span>
                                                <span><strong>{__('Frequency:', 'apprenticeship-connect')}</strong> {task.schedule_frequency}</span>
                                                <span><strong>{__('Total Runs:', 'apprenticeship-connect')}</strong> {task.total_runs}</span>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', gap: '10px' }}>
                                            <Button variant="secondary" onClick={() => setEditingTaskId(task.id)}>{__('Edit', 'apprenticeship-connect')}</Button>
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
                            <Notice status="info" isDismissible={false}>{__('No import tasks configured. Click "Add New Import Task" to get started.', 'apprenticeship-connect')}</Notice>
                        )}
                    </div>
                </div>

                <div>
                    <Heading level={2}>{__('Resilience Monitor', 'apprenticeship-connect')}</Heading>
                    <div style={{ marginTop: '15px' }}>
                        <RateLimitInfo stats={stats?.resilience} />

                        <Card>
                            <CardBody>
                                <Heading level={4}>{__('System Health', 'apprenticeship-connect')}</Heading>
                                <ul style={{ margin: '10px 0 0 0', padding: 0, listStyle: 'none' }}>
                                    <li style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f0f0f1' }}>
                                        <span>{__('Log Entries', 'apprenticeship-connect')}</span>
                                        <strong>{stats?.total_logs || 0}</strong>
                                    </li>
                                    <li style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f0f0f1' }}>
                                        <span>{__('Import Runs', 'apprenticeship-connect')}</span>
                                        <strong>{stats?.total_runs || 0}</strong>
                                    </li>
                                    <li style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0' }}>
                                        <span>{__('API Version', 'apprenticeship-connect')}</span>
                                        <strong>v2</strong>
                                    </li>
                                </ul>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>

            {lastImportId && (
                <div style={{ marginTop: '40px' }}>
                    <Heading level={2}>{__('Real-Time Deep-Fetch Log', 'apprenticeship-connect')}</Heading>
                    <div style={{ marginTop: '15px' }}>
                        <FancyLogViewer importId={lastImportId} active={true} />
                    </div>
                </div>
            )}
        </div>
    );
};

export default Dashboard;
