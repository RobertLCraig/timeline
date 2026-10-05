import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../lib/api';
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
const POLL_MS = 12000;

// Comments under an event. Polls while mounted (i.e. while the modal is open)
// and the tab is visible, sending `since` so a quiet poll returns nothing.
// Bodies render as React text, never as HTML.
function Comments({ slug, eventId, canManage, currentUserId }) {
    const url = `/groups/${slug}/events/${eventId}/comments`;
    const [comments, setComments] = useState([]);
    const [body, setBody] = useState('');
    const [error, setError] = useState('');
    const [sending, setSending] = useState(false);
    const latest = useRef(null);

    const merge = (incoming) => {
        if (!incoming.length) return;
        setComments(prev => {
            const seen = new Set(prev.map(c => c.id));
            return [...prev, ...incoming.filter(c => !seen.has(c.id))];
        });
        latest.current = incoming[incoming.length - 1].created_at;
    };

    useEffect(() => {
        let stopped = false;
        setComments([]);
        latest.current = null;

        const poll = async () => {
            if (document.visibilityState !== 'visible') return;
            try {
                const q = latest.current ? `?since=${encodeURIComponent(latest.current)}` : '';
                const data = await api.get(url + q);
                if (!stopped) merge(data.comments);
            } catch { /* next poll retries */ }
        };

        poll();
        const timer = setInterval(poll, POLL_MS);
        document.addEventListener('visibilitychange', poll);
        return () => {
            stopped = true;
            clearInterval(timer);
            document.removeEventListener('visibilitychange', poll);
        };
    }, [url]);

    const send = async (e) => {
        e.preventDefault();
        if (!body.trim()) return;
        setSending(true);
        setError('');
        try {
            const data = await api.post(url, { body });
            merge([data.comment]);
            setBody('');
        } catch (err) {
            setError(err.status === 429 ? 'You are commenting too fast. Wait a minute.' : err.message);
        } finally {
            setSending(false);
        }
    };

    const remove = async (id) => {
        try {
            await api.delete(`${url}/${id}`);
            setComments(prev => prev.filter(c => c.id !== id));
        } catch (err) {
            setError(err.message);
        }
    };

    return (
        <div className="ev-comments">
            <h3 className="ev-comments-title">Comments</h3>
            {comments.length === 0 && <p className="text-muted text-sm">No comments yet.</p>}
            <ul className="ev-comments-list">
                {comments.map(c => (
                    <li key={c.id} className="ev-comment">
                        <div className="ev-comment-head">
                            <strong>{c.user?.name || 'Unknown'}</strong>
                            <span className="text-muted text-sm">{new Date(c.created_at).toLocaleString()}</span>
                            {(canManage || c.user_id === currentUserId) && (
                                <button onClick={() => remove(c.id)} className="btn btn-ghost btn-sm" aria-label="Delete comment">✕</button>
                            )}
                        </div>
                        <p className="ev-comment-body">{c.body}</p>
                    </li>
                ))}
            </ul>
            <form onSubmit={send} className="ev-comment-form">
                <textarea
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    maxLength={2000}
                    rows={2}
                    placeholder="Add a comment…"
                    aria-label="Add a comment"
                />
                <button type="submit" className="btn btn-primary btn-sm" disabled={sending || !body.trim()}>Post</button>
            </form>
            {error && <p className="ev-comment-error text-sm" role="alert">{error}</p>}
        </div>
    );
}

export default function EventModal({ event, slug, canManage, currentUserId, canComment, onClose, onDelete }) {
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

                    {canComment && (
                        <Comments slug={slug} eventId={event.id} canManage={canManage} currentUserId={currentUserId} />
                    )}
                </div>
            </div>
        </div>
    );
}
