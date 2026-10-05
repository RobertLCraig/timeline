import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import './views.css';

const SOCIAL_TIER_ICON = {
    family:        '👨‍👩‍👧‍👦',
    close_friends: '💛',
    friends:       '🤝',
    acquaintances: '👋',
    public:        '🌍',
    private:       '🔒',
};

// Shared detail dialog used by the calendar / heatmap / mosaic views.
// (The vertical timeline view renders full cards inline and doesn't use this.)
export default function EventModal({ event, slug, canManage, currentUserId, onClose, onDelete }) {
    const photos = event?.image_urls?.length ? event.image_urls : (event?.image_url ? [event.image_url] : []);
    const [index, setIndex] = useState(0);
    const step = (d) => setIndex(i => (i + d + photos.length) % photos.length);

    useEffect(() => setIndex(0), [event?.id]);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
            else if (photos.length > 1 && e.key === 'ArrowLeft') step(-1);
            else if (photos.length > 1 && e.key === 'ArrowRight') step(1);
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [onClose, photos.length]);

    if (!event) return null;

    const canEdit = canManage || event.created_by === currentUserId;
    const dateLabel = new Date(event.event_date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric',
    });

    return (
        <div className="ev-modal-overlay" onClick={onClose}>
            <div className="ev-modal" onClick={(e) => e.stopPropagation()}>
                <button className="ev-modal-close" onClick={onClose} aria-label="Close">✕</button>

                {photos.length > 0 && (
                    <div className="ev-modal-image">
                        <img src={photos[index]} alt={` - photo  of `} />
                        {photos.length > 1 && (
                            <>
                                <button className="ev-modal-step ev-modal-prev" onClick={() => step(-1)} aria-label="Previous photo">‹</button>
                                <button className="ev-modal-step ev-modal-next" onClick={() => step(1)} aria-label="Next photo">›</button>
                                <span className="ev-modal-counter">{index + 1} / {photos.length}</span>
                            </>
                        )}
                    </div>
                )}

                <div className="ev-modal-body">
                    <div className="ev-modal-date">{dateLabel}</div>
                    <h2 className="ev-modal-title">{event.title}</h2>

                    <div className="ev-modal-badges">
                        {event.category && (
                            <span className="badge" style={{ background: event.category.color || 'var(--color-primary)', color: '#fff' }}>
                                {event.category.icon} {event.category.name}
                            </span>
                        )}
                        {event.social_visibility && (
                            <span className="badge badge-members" title={`Social visibility: ${event.social_visibility.replace('_', ' ')}`}>
                                {SOCIAL_TIER_ICON[event.social_visibility]} {event.social_visibility.replace('_', ' ')}
                            </span>
                        )}
                        <span className={`badge badge-${event.visibility}`}>
                            {event.visibility === 'public' ? '🌍' : event.visibility === 'members' ? '👥' : '🔒'} {event.visibility}
                        </span>
                    </div>

                    {event.description && <p className="ev-modal-desc">{event.description}</p>}

                    {event.album_url && (
                        <a href={event.album_url} target="_blank" rel="noopener noreferrer" className="ev-modal-album">
                            📸 View Photo Album →
                        </a>
                    )}

                    <div className="ev-modal-footer">
                        <span className="text-muted text-sm">By {event.creator?.name || 'Unknown'}</span>
                        {canEdit && (
                            <div className="flex gap-xs">
                                <Link to={`/g/${slug}/events/${event.id}/edit`} className="btn btn-ghost btn-sm">Edit</Link>
                                <button onClick={() => onDelete(event.id)} className="btn btn-ghost btn-sm" style={{ color: '#ef4444' }}>Delete</button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
