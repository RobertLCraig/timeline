import { useEffect } from 'react';
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
    useEffect(() => {
        const onKey = (e) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);

    if (!event) return null;

    const canEdit = canManage || event.created_by === currentUserId;
    const dateLabel = new Date(event.event_date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric',
    });

    return (
        <div className="ev-modal-overlay" onClick={onClose}>
            <div className="ev-modal" onClick={(e) => e.stopPropagation()}>
                <button className="ev-modal-close" onClick={onClose} aria-label="Close">✕</button>

                {event.image_url && (
                    <div className="ev-modal-image">
                        <img src={event.image_url} alt={event.title} />
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
