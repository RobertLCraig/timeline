import { useMemo, useState } from 'react';
import './views.css';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// year × month density grid. Cell shade scales with event count; clicking a
// populated cell opens a list of that month's events below the grid.
export default function CalendarHeatmapView({ events, onSelect }) {
    const [selected, setSelected] = useState(null); // { year, month }

    const { years, byCell, maxCount } = useMemo(() => {
        const cells = {}; // `${year}-${month}` -> events[]
        let min = Infinity, max = -Infinity;
        events.forEach(ev => {
            const d = new Date(ev.event_date);
            if (isNaN(d)) return;
            const yr = d.getFullYear();
            const key = `${yr}-${d.getMonth()}`;
            (cells[key] ||= []).push(ev);
            if (yr < min) min = yr;
            if (yr > max) max = yr;
        });
        let mc = 0;
        Object.values(cells).forEach(arr => { if (arr.length > mc) mc = arr.length; });
        const yrs = [];
        if (isFinite(min)) for (let y = max; y >= min; y--) yrs.push(y);
        return { years: yrs, byCell: cells, maxCount: mc };
    }, [events]);

    if (!years.length) {
        return (
            <div className="view-empty">
                <div className="view-empty-icon">🗓️</div>
                <div>No dated events to chart.</div>
            </div>
        );
    }

    const shade = (count) => {
        if (!count) return { background: 'var(--bg-surface)' };
        const alpha = 0.18 + 0.62 * (count / maxCount);
        return { background: `rgba(99, 102, 241, ${alpha})` };
    };

    const selectedEvents = selected
        ? (byCell[`${selected.year}-${selected.month}`] || [])
            .slice()
            .sort((a, b) => new Date(a.event_date) - new Date(b.event_date))
        : [];

    return (
        <>
            <div className="heatmap">
                <div className="heatmap-grid">
                    <div className="heatmap-corner" />
                    {MONTHS.map(m => <div key={m} className="heatmap-month-label">{m}</div>)}

                    {years.map(year => (
                        <div key={year} style={{ display: 'contents' }}>
                            <div className="heatmap-year-label">{year}</div>
                            {MONTHS.map((_, month) => {
                                const list = byCell[`${year}-${month}`] || [];
                                const count = list.length;
                                const isSel = selected && selected.year === year && selected.month === month;
                                return (
                                    <div
                                        key={month}
                                        className={`heatmap-cell${count ? ' has-events' : ''}${isSel ? ' selected' : ''}`}
                                        style={shade(count)}
                                        title={`${MONTHS[month]} ${year} — ${count} event${count !== 1 ? 's' : ''}`}
                                        onClick={count ? () => setSelected({ year, month }) : undefined}
                                    >
                                        {count > 0 && <span className="heatmap-cell-count">{count}</span>}
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                </div>

                <div className="heatmap-legend">
                    <span>Less</span>
                    <span className="heatmap-legend-cell" style={shade(0)} />
                    <span className="heatmap-legend-cell" style={shade(Math.ceil(maxCount * 0.34))} />
                    <span className="heatmap-legend-cell" style={shade(Math.ceil(maxCount * 0.67))} />
                    <span className="heatmap-legend-cell" style={shade(maxCount)} />
                    <span>More</span>
                </div>
            </div>

            {selected && (
                <div className="period-panel">
                    <div className="period-panel-title">
                        {MONTHS[selected.month]} {selected.year}
                        <button className="period-panel-close" onClick={() => setSelected(null)} aria-label="Close">✕</button>
                    </div>
                    {selectedEvents.map(ev => (
                        <div key={ev.id} className="period-item" onClick={() => onSelect(ev)}>
                            <div className="period-item-dot" style={{ background: ev.category?.color || 'var(--color-primary)' }}>
                                {ev.category?.icon || '📌'}
                            </div>
                            <div className="period-item-main">
                                <div className="period-item-title">{ev.title}</div>
                                <div className="period-item-date">
                                    {new Date(ev.event_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
                                </div>
                            </div>
                            {ev.image_url && <img className="period-item-thumb" src={ev.image_url} alt="" />}
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
