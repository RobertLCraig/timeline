import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import './GroupTimeline.css';

export default function GroupTimeline() {
    const { slug } = useParams();
    const { user, isAuthenticated } = useAuth();
    const navigate = useNavigate();
    const [group, setGroup] = useState(null);
    const [membership, setMembership] = useState(null);
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [categories, setCategories] = useState([]);
    const [filters, setFilters] = useState({ category_id: '', sort: 'desc' });
    const [joinCode, setJoinCode] = useState('');
    const [joinError, setJoinError] = useState('');
    const [joinLoading, setJoinLoading] = useState(false);

    useEffect(() => {
        loadData();
        api.get('/categories').then(d => setCategories(d.categories)).catch(() => { });
    }, [slug]);

    const loadData = async () => {
        setLoading(true);
        try {
            const [groupData, eventData] = await Promise.all([
                api.get(`/groups/${slug}`),
                api.get(`/groups/${slug}/events?sort=${filters.sort}${filters.category_id ? `&category_id=${filters.category_id}` : ''}`),
            ]);
            setGroup(groupData.group);
            setMembership(groupData.membership);
            setEvents(eventData.data || []);
        } catch (err) {
            if (err.status === 404) navigate('/dashboard');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (group) {
            api.get(`/groups/${slug}/events?sort=${filters.sort}${filters.category_id ? `&category_id=${filters.category_id}` : ''}`)
                .then(d => setEvents(d.data || []))
                .catch(() => { });
        }
    }, [filters]);

    const handleJoin = async (e) => {
        e.preventDefault();
        setJoinError('');
        setJoinLoading(true);
        try {
            await api.post(`/groups/${slug}/join`, { invite_code: joinCode });
            loadData();
            setJoinCode('');
        } catch (err) {
            setJoinError(err.data?.message || 'Invalid invite code');
        } finally {
            setJoinLoading(false);
        }
    };

    const handleDelete = async (eventId) => {
        if (!confirm('Delete this event?')) return;
        try {
            await api.delete(`/groups/${slug}/events/${eventId}`);
            setEvents(events.filter(e => e.id !== eventId));
        } catch { }
    };

    if (loading) {
        return <div className="loading-screen"><div className="spinner" /></div>;
    }

    if (!group) return null;

    const canManage = membership === 'owner' || membership === 'admin';
    const isMember = !!membership;

    return (
        <div className="page">
            <div className="container">
                {/* Group Header */}
                <div className="group-header fade-in">
                    <div className="group-header-info">
                        <h1 className="page-title">{group.name}</h1>
                        {group.description && <p className="page-subtitle">{group.description}</p>}
                        <div className="group-header-meta">
                            <span className="text-muted text-sm">{group.members_count} member{group.members_count !== 1 ? 's' : ''}</span>
                            <span className="text-muted text-sm">·</span>
                            <span className="text-muted text-sm">{group.events_count} event{group.events_count !== 1 ? 's' : ''}</span>
                            {membership && <span className="badge badge-role-{membership}">{membership}</span>}
                        </div>
                    </div>
                    <div className="group-header-actions">
                        {isMember && (
                            <Link to={`/g/${slug}/events/new`} className="btn btn-primary">+ Add Event</Link>
                        )}
                        {canManage && (
                            <Link to={`/g/${slug}/settings`} className="btn btn-secondary">⚙ Settings</Link>
                        )}
                    </div>
                </div>

                {/* Join prompt for non-members */}
                {isAuthenticated && !isMember && (
                    <div className="join-card card fade-in">
                        <h3>Join this group</h3>
                        <p className="text-muted text-sm mb-md">Enter an invite code to become a member.</p>
                        {joinError && <div className="alert alert-error">{joinError}</div>}
                        <form onSubmit={handleJoin} className="flex gap-md items-center">
                            <input
                                type="text"
                                className="form-input"
                                value={joinCode}
                                onChange={(e) => setJoinCode(e.target.value)}
                                placeholder="Invite code"
                                required
                                style={{ maxWidth: '240px' }}
                            />
                            <button type="submit" className="btn btn-primary" disabled={joinLoading}>
                                {joinLoading ? 'Joining...' : 'Join'}
                            </button>
                        </form>
                    </div>
                )}

                {/* Filters */}
                <div className="timeline-filters fade-in">
                    <select
                        className="form-select"
                        value={filters.category_id}
                        onChange={(e) => setFilters({ ...filters, category_id: e.target.value })}
                        style={{ maxWidth: '200px' }}
                    >
                        <option value="">All Categories</option>
                        {categories.map(cat => (
                            <option key={cat.id} value={cat.id}>{cat.icon} {cat.name}</option>
                        ))}
                    </select>
                    <select
                        className="form-select"
                        value={filters.sort}
                        onChange={(e) => setFilters({ ...filters, sort: e.target.value })}
                        style={{ maxWidth: '160px' }}
                    >
                        <option value="desc">Newest First</option>
                        <option value="asc">Oldest First</option>
                    </select>
                </div>

                {/* Timeline */}
                {events.length === 0 ? (
                    <div className="empty-state fade-in">
                        <div className="empty-state-icon">📅</div>
                        <div className="empty-state-text">No events yet</div>
                        {isMember && (
                            <Link to={`/g/${slug}/events/new`} className="btn btn-primary">Add the first event</Link>
                        )}
                    </div>
                ) : (
                    <div className="timeline fade-in">
                        {events.map((event, i) => (
                            <div key={event.id} className="timeline-item" style={{ animationDelay: `${i * 0.05}s` }}>
                                <div className="timeline-dot" style={{ background: event.category?.color || 'var(--color-primary)' }}>
                                    <span>{event.category?.icon || '📌'}</span>
                                </div>
                                <div className="timeline-card card">
                                    <div className="timeline-card-header">
                                        <div className="timeline-card-date">
                                            {new Date(event.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                                        </div>
                                        <div className="flex gap-xs items-center">
                                            <span className={`badge badge-${event.visibility}`}>
                                                {event.visibility === 'public' ? '🌍' : event.visibility === 'members' ? '👥' : '🔒'} {event.visibility}
                                            </span>
                                        </div>
                                    </div>
                                    <h3 className="timeline-card-title">{event.title}</h3>
                                    {event.description && (
                                        <p className="timeline-card-desc">{event.description}</p>
                                    )}
                                    {event.image_url && (
                                        <div className="timeline-card-image">
                                            <img src={event.image_url.startsWith('http') ? event.image_url : `http://localhost:8000${event.image_url}`} alt={event.title} />
                                        </div>
                                    )}
                                    {event.album_url && (
                                        <a href={event.album_url} target="_blank" rel="noopener noreferrer" className="timeline-card-album">
                                            📸 View Photo Album →
                                        </a>
                                    )}
                                    <div className="timeline-card-footer">
                                        <span className="text-muted text-sm">
                                            By {event.creator?.name || 'Unknown'}
                                        </span>
                                        {(event.created_by === user?.id || canManage) && (
                                            <div className="flex gap-xs">
                                                <Link to={`/g/${slug}/events/${event.id}/edit`} className="btn btn-ghost btn-sm">Edit</Link>
                                                <button onClick={() => handleDelete(event.id)} className="btn btn-ghost btn-sm" style={{ color: '#ef4444' }}>Delete</button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
