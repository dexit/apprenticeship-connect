/**
 * StatsWidget – single stat card for the dashboard grid.
 *
 * @param {string}        label     Short label beneath the value.
 * @param {string|number} value     Value to display; undefined shows "—".
 * @param {string}        [color]   'blue'|'green'|'amber'|'red'|'grey'
 * @param {string}        [href]    Optional link the whole card navigates to.
 * @param {boolean}       [loading] Show skeleton placeholder while data loads.
 */
const StatsWidget = ( { label, value, color = 'blue', href, loading = false } ) => {
	const palette = {
		blue:  { bg: '#dbeafe', fg: '#1d4ed8' },
		green: { bg: '#dcfce7', fg: '#15803d' },
		amber: { bg: '#fef9c3', fg: '#a16207' },
		red:   { bg: '#fee2e2', fg: '#b91c1c' },
		grey:  { bg: '#f3f4f6', fg: '#374151' },
	};
	const { bg, fg } = palette[ color ] ?? palette.blue;

	const card = (
		<div className="appcon-stat-card" style={ { background: bg, borderRadius: 6, padding: '16px 20px', flex: '1 1 130px' } }>
			{ loading ? (
				<div style={ { height: 36, background: 'rgba(0,0,0,.07)', borderRadius: 4, marginBottom: 6 } } />
			) : (
				<span style={ { display: 'block', fontSize: 28, fontWeight: 700, color: fg, lineHeight: 1.2 } }>
					{ value ?? '—' }
				</span>
			) }
			<span style={ { display: 'block', fontSize: 12, color: '#6b7280', marginTop: 4, fontWeight: 500 } }>
				{ label }
			</span>
		</div>
	);

	return href ? <a href={ href } style={ { textDecoration: 'none' } }>{ card }</a> : card;
};

export default StatsWidget;
