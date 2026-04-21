import { useEffect, useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const FancyLogViewer = ({ importId, active }) => {
    const [logs, setLogs] = useState([]);
    const scrollRef = useRef(null);

    useEffect(() => {
        let interval;
        if (active && importId) {
            fetchLogs();
            interval = setInterval(fetchLogs, 2000);
        }
        return () => clearInterval(interval);
    }, [active, importId]);

    useEffect(() => {
        if (scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [logs]);

    const fetchLogs = async () => {
        try {
            const res = await apiFetch({ path: `/apprco/v1/logs/${importId}` });
            setLogs(res.logs || []);
        } catch (e) {}
    };

    if (!active) return null;

    return (
        <div className="apprco-fancy-logs" ref={scrollRef} style={{ background: '#1e1e1e', color: '#d4d4d4', padding: '20px', borderRadius: '8px', fontFamily: 'monospace', maxHeight: '400px', overflowY: 'auto' }}>
            {logs.map((log) => (
                <div key={log.id} style={{ marginBottom: '5px', display: 'flex', gap: '10px' }}>
                    <span style={{ color: '#808080' }}>[{new Date(log.created_at).toLocaleTimeString()}]</span>
                    <span className={`log-level level-${log.log_level}`} style={{ fontWeight: 'bold' }}>{log.log_level.toUpperCase()}</span>
                    <span>{log.message}</span>
                </div>
            ))}
            {logs.length === 0 && <div>{__('Waiting for logs...', 'apprenticeship-connect')}</div>}
        </div>
    );
};

export default FancyLogViewer;
