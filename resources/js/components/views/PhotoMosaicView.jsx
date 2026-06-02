import { useMemo } from 'react';
import './views.css';

// Image-first masonry-style gallery, grouped by year (newest first).
// Only events that have a photo are shown; clicking a tile opens the modal.
export default function PhotoMosaicView({ events, onSelect }) {
    const groups = useMemo(() => {
        const withPhotos = events.filter(ev => ev.image_url);
        const byYear = {};
        withPhotos.forEach(ev => {
            const yr = new Date(ev.event_date).getFullYear();
            (byYear[yr] || (byYear[yr] = [])).push(ev);
        });
        return Object.keys(byYear)
            .map(Number)
            .sort((a, b) => b - a)
            .map(year => ({
                year,
                events: byYear[year].sort((a, b) => new Date(b.event_date) - new Date(a.event_date)),
            }));
    }, [events]);

    if (!groups.length) {
        return (
            <div className="view-empty">
                <div className="view-empty-icon">📷</div>
                <div>No events with photos yet.</div>
                <div className="text-sm" style={{ marginTop: 8 }}>Add a photo to an event to see it here.</div>
            </div>
        );
    }

    return (
        <div>
            {groups.map(({ year, events: evs }) => (
                <div key={year} className="mosaic-year">
                    <div className="mosaic-year-label">
                        {year}
                        <span className="mosaic-year-count">{evs.length} photo{evs.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div className="mosaic-grid">
                        {evs.map(ev => (
                            <div key={ev.id} className="mosaic-tile" onClick={() => onSelect(ev)}>
                                <img src={ev.image_url} alt={ev.title} loading="lazy" />
                                <div className="mosaic-tile-overlay">
                                    <div className="mosaic-tile-title">{ev.title}</div>
                                    <div className="mosaic-tile-date">
                                        {new Date(ev.event_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
