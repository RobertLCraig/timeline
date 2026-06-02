import { useMemo, useState } from 'react';
import './views.css';

const DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'];

// Familiar month grid. Defaults to the most recent month that has events.
export default function MonthCalendarView({ events, onSelect }) {
    const byDay = useMemo(() => {
        const map = {}; // 'YYYY-M-D' -> events[]
        let latest = null;
        events.forEach(ev => {
            const d = new Date(ev.event_date);
            if (isNaN(d)) return;
            const key = `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
            (map[key] ||= []).push(ev);
            if (!latest || d > latest) latest = d;
        });
        return { map, latest };
    }, [events]);

    const initial = byDay.latest || new Date(2026, 0, 1);
    const [cursor, setCursor] = useState({ year: initial.getFullYear(), month: initial.getMonth() });
    const [dayPanel, setDayPanel] = useState(null); // { year, month, day }

    const weeks = useMemo(() => {
        const first = new Date(cursor.year, cursor.month, 1);
        const startOffset = first.getDay();
        const daysInMonth = new Date(cursor.year, cursor.month + 1, 0).getDate();
        const cells = [];
        // leading days from previous month
        for (let i = 0; i < startOffset; i++) cells.push(null);
        for (let d = 1; d <= daysInMonth; d++) cells.push(d);
        while (cells.length % 7 !== 0) cells.push(null);
        const rows = [];
        for (let i = 0; i < cells.length; i += 7) rows.push(cells.slice(i, i + 7));
        return rows;
    }, [cursor]);

    const move = (delta) => {
        setDayPanel(null);
        setCursor(c => {
            const m = c.month + delta;
            return { year: c.year + Math.floor(m / 12), month: ((m % 12) + 12) % 12 };
        });
    };

    const jumpToLatest = () => {
        setDayPanel(null);
        const d = byDay.latest || new Date();
        setCursor({ year: d.getFullYear(), month: d.getMonth() });
    };

    const dayEvents = (day) => byDay.map[`${cursor.year}-${cursor.month}-${day}`] || [];

    const panelEvents = dayPanel
        ? (byDay.map[`${dayPanel.year}-${dayPanel.month}-${dayPanel.day}`] || [])
        : [];

    return (
        <>
            <div className="monthcal">
                <div className="monthcal-toolbar">
                    <div className="monthcal-title">{MONTH_NAMES[cursor.month]} {cursor.year}</div>
                    <div className="monthcal-nav">
                        <button className="monthcal-today-btn" onClick={jumpToLatest}>Latest</button>
                        <button className="monthcal-nav-btn" onClick={() => move(-1)} aria-label="Previous month">‹</button>
                        <button className="monthcal-nav-btn" onClick={() => move(1)} aria-label="Next month">›</button>
                    </div>
                </div>

                <div className="monthcal-grid">
                    {DOW.map(d => <div key={d} className="monthcal-dow">{d}</div>)}

                    {weeks.flat().map((day, i) => {
                        if (day === null) return <div key={i} className="monthcal-day outside" />;
                        const evs = dayEvents(day);
                        return (
                            <div key={i} className="monthcal-day">
                                <div className="monthcal-day-num">{day}</div>
                                {evs.slice(0, 3).map(ev => (
                                    <div
                                        key={ev.id}
                                        className="monthcal-chip"
                                        style={{ background: ev.category?.color || 'var(--color-primary)' }}
                                        title={ev.title}
                                        onClick={() => onSelect(ev)}
                                    >
                                        <span>{ev.category?.icon || '📌'}</span>
                                        <span className="monthcal-chip-title">{ev.title}</span>
                                    </div>
                                ))}
                                {evs.length > 3 && (
                                    <span
                                        className="monthcal-more"
                                        onClick={() => setDayPanel({ year: cursor.year, month: cursor.month, day })}
                                    >
                                        +{evs.length - 3} more
                                    </span>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {dayPanel && (
                <div className="period-panel">
                    <div className="period-panel-title">
                        {MONTH_NAMES[dayPanel.month]} {dayPanel.day}, {dayPanel.year}
                        <button className="period-panel-close" onClick={() => setDayPanel(null)} aria-label="Close">✕</button>
                    </div>
                    {panelEvents.map(ev => (
                        <div key={ev.id} className="period-item" onClick={() => onSelect(ev)}>
                            <div className="period-item-dot" style={{ background: ev.category?.color || 'var(--color-primary)' }}>
                                {ev.category?.icon || '📌'}
                            </div>
                            <div className="period-item-main">
                                <div className="period-item-title">{ev.title}</div>
                                {ev.description && <div className="period-item-date">{ev.description.slice(0, 80)}</div>}
                            </div>
                            {ev.image_url && <img className="period-item-thumb" src={ev.image_url} alt="" />}
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
