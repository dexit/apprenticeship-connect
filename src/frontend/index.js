import { render, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const VacancySearch = () => {
    const [params, setParams] = useState({ Postcode: '', DistanceInMiles: 10, PageNumber: 1 });
    const [results, setResults] = useState(null);
    const [loading, setLoading] = useState(false);

    const search = async (e) => {
        if (e) e.preventDefault();
        setLoading(true);
        try {
            const res = await apiFetch({
                path: '/apprco/v1/proxy/vacancies?' + new URLSearchParams(params).toString()
            });
            setResults(res);
        } catch (e) {
            alert('Search failed');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="apprco-search-container">
            <form onSubmit={search} style={{ display: 'flex', gap: '10px', marginBottom: '20px' }}>
                <input
                    type="text"
                    placeholder="Postcode"
                    value={params.Postcode}
                    onChange={e => setParams({...params, Postcode: e.target.value})}
                    style={{ padding: '8px', borderRadius: '4px', border: '1px solid #ddd' }}
                />
                <button type="submit" disabled={loading} style={{ background: '#2271b1', color: '#fff', border: 'none', padding: '8px 16px', borderRadius: '4px', cursor: 'pointer' }}>
                    {loading ? 'Searching...' : 'Search Vacancies'}
                </button>
            </form>

            {results && (
                <div className="apprco-results">
                    {results.vacancies?.map(v => (
                        <div key={v.vacancyReference} style={{ padding: '15px', borderBottom: '1px solid #eee' }}>
                            <h3 style={{ margin: '0 0 5px 0' }}>{v.title}</h3>
                            <p style={{ margin: 0, fontSize: '14px' }}>{v.employerName} - {v.addresses?.[0]?.postcode}</p>
                        </div>
                    ))}
                    {results.vacancies?.length === 0 && <p>No vacancies found in this area.</p>}
                </div>
            )}
        </div>
    );
};

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('apprco-search-root');
    if (root) render(<VacancySearch />, root);
});
