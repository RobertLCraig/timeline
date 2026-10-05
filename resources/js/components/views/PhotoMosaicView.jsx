import { useMemo } from 'react';
import './views.css';

// Image-first masonry-style gallery, grouped by year (newest first).
// Every photo of every event is a tile; clicking one opens its event's modal.
export default function PhotoMosaicView({ events, onSelect }) {
    const groups = useMemo(() => {
        const byYear = {};
        events.forEach(ev => {
            const urls = ev.image_urls?.length ? ev.image_urls : (ev.image_url ? [ev.image_url] : []);
            const yr = new Date(ev.event_date).getFullYear();
            urls.forEach((url, i) => (byYear[yr] || (byYear[yr] = [])).push({ ev, url, key: `-` }));
        });
        return Object.keys(byYear)
            .map(Number)
            .sort((a, b) => b - a)
            .map(year => ({
                year,
                tiles: byYear[year].sort((a, b) => new Date(b.ev.event_date) - new Date(a.ev.event_date)),
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
            {groups.map(({ year, tiles }) => (
                <div key={year} className="mosaic-year">
                    <div className="mosaic-year-label">
                        {year}
                        <span className="mosaic-year-count">{tiles.length} photo{tiles.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div className="mosaic-grid">
                        {tiles.map(({ ev, url, key }) => (
                            <div key={key} className="mosaic-tile" onClick={() => onSelect(ev)}>
                                <img src={url} alt={ev.title} loading="lazy" />
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
