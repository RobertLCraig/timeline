import { useState, useEffect, useMemo, useRef } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import './GroupTimeline.css';

const SOCIAL_TIER_ICON = {
    family:        '👨‍👩‍👧‍👦',
    close_friends: '💛',
    friends:       '🤝',
    acquaintances: '👋',
    public:        '🌍',
    private:       '🔒',
};

// ── Vertical minimap-style year range slider ─────────────────────────────────
//
// Looks like the VSCode scrollbar/minimap panel:
//   • Left side: decade tick lines + year labels
//   • Right side: tiny event icons positioned at their year
//   • A draggable selection window shows the active year range,
//     with resize handles at top (startYear) and bottom (endYear)
//   • Click anywhere on the bar to teleport the window there

function YearMapSlider({ minYear, maxYear, events, startYear, endYear, onChange }) {
    const barRef  = useRef(null);
    // 'window' | 'top' | 'bottom' | null
    const [dragging, setDragging] = useState(null);
    // Captures values at the moment a drag begins so deltas are stable
    const dragOrigin = useRef({ pct: 0, startYear: 0, endYear: 0 });

    const totalSpan = Math.max(1, maxYear - minYear);

    // Convert year ↔ percentage of bar height
    const yp  = (year) => ((year - minYear) / totalSpan) * 100;
    const py  = (pct)  => Math.round(minYear + (pct / 100) * totalSpan);

    // Decade tick marks to display
    const decades = useMemo(() => {
        const marks = [];
        const first = Math.ceil(minYear / 10) * 10;
        for (let d = first; d <= maxYear; d += 10) marks.push(d);
        return marks;
    }, [minYear, maxYear]);

    // Group events by year for minimap dots
    const eventsByYear = useMemo(() => {
        const map = {};
        events.forEach(ev => {
            const y = parseInt(ev.event_date?.slice(0, 4) ?? '0', 10);
            if (y >= minYear && y <= maxYear) {
                if (!map[y]) map[y] = [];
                map[y].push(ev);
            }
        });
        return map;
    }, [events, minYear, maxYear]);

    // Attach/detach document-level drag listeners
    useEffect(() => {
        if (!dragging) return;

        const onMove = (e) => {
            // Resolve clientY for both mouse and touch
            const bar = barRef.current;
            if (!bar) return;
            const rect   = bar.getBoundingClientRect();
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            const pct    = Math.max(0, Math.min(100, ((clientY - rect.top) / rect.height) * 100));

            const { pct: originPct, startYear: os, endYear: oe } = dragOrigin.current;
            const deltaYears = Math.round(((pct - originPct) / 100) * totalSpan);

            if (dragging === 'window') {
                const winSpan = oe - os;
                let ns = os + deltaYears;
                let ne = ns + winSpan;
                if (ns < minYear) { ns = minYear; ne = minYear + winSpan; }
                if (ne > maxYear) { ne = maxYear; ns = maxYear - winSpan; }
                onChange(ns, ne);
            } else if (dragging === 'top') {
                const ns = Math.max(minYear, Math.min(os + deltaYears, oe - 1));
                onChange(ns, oe);
            } else if (dragging === 'bottom') {
                const ne = Math.min(maxYear, Math.max(oe + deltaYears, os + 1));
                onChange(os, ne);
            }

            // Prevent page scroll while dragging on touch
            if (e.cancelable) e.preventDefault();
        };

        const onUp = () => setDragging(null);

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup',   onUp);
        document.addEventListener('touchmove',  onMove, { passive: false });
        document.addEventListener('touchend',   onUp);
        return () => {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup',   onUp);
            document.removeEventListener('touchmove',  onMove);
            document.removeEventListener('touchend',   onUp);
        };
    }, [dragging, minYear, maxYear, totalSpan, onChange]);

    // Begin dragging one of the three zones
    const beginDrag = (e, type) => {
        e.preventDefault();
        e.stopPropagation();
        const bar  = barRef.current;
        const rect = bar.getBoundingClientRect();
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const pct = Math.max(0, Math.min(100, ((clientY - rect.top) / rect.height) * 100));
        dragOrigin.current = { pct, startYear, endYear };
        setDragging(type);
    };

    // Click on bar background: teleport window to clicked position then start drag
    const handleBarMouseDown = (e) => {
        if (e.target.closest('.year-map-window')) return; // let window handle it
        const bar  = barRef.current;
        const rect = bar.getBoundingClientRect();
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const pct = Math.max(0, Math.min(100, ((clientY - rect.top) / rect.height) * 100));

        const clickYear = py(pct);
        const winSpan   = endYear - startYear;
        let ns = Math.round(clickYear - winSpan / 2);
        let ne = ns + winSpan;
        if (ns < minYear) { ns = minYear; ne = minYear + winSpan; }
        if (ne > maxYear) { ne = maxYear; ns = maxYear - winSpan; }
        onChange(ns, ne);

        // Immediately enter window-drag mode from new position
        dragOrigin.current = { pct, startYear: ns, endYear: ne };
        setDragging('window');
    };

    const startPct  = yp(startYear);
    const windowPct = Math.max(yp(endYear) - startPct, 4); // min 4% so handles are reachable

    return (
        <div
            className="year-map"
            ref={barRef}
            onMouseDown={handleBarMouseDown}
            onTouchStart={handleBarMouseDown}
        >
            {/* Decade tick lines + year labels */}
            {decades.map(d => (
                <div key={d} className="year-map-tick" style={{ top: `${yp(d)}%` }}>
                    <span className="year-map-tick-label">{d}</span>
                    <div className="year-map-tick-line" />
                </div>
            ))}

            {/* Tiny event icon dots – the minimap content */}
            {Object.entries(eventsByYear).map(([year, evs]) => (
                <div key={year} className="year-map-dots" style={{ top: `${yp(Number(year))}%` }}>
                    {evs.slice(0, 5).map((ev, i) => (
                        <span key={i} className="year-map-icon">
                            {ev.category?.icon || '●'}
                        </span>
                    ))}
                    {evs.length > 5 && <span className="year-map-icon-more">+{evs.length - 5}</span>}
                </div>
            ))}

            {/* Draggable selection window */}
            <div
                className={`year-map-window${dragging === 'window' ? ' is-dragging' : ''}`}
                style={{ top: `${startPct}%`, height: `${windowPct}%` }}
                onMouseDown={e => beginDrag(e, 'window')}
                onTouchStart={e => beginDrag(e, 'window')}
            >
                {/* Top resize handle – shows startYear */}
                <div
                    className="year-map-handle year-map-handle-top"
                    onMouseDown={e => beginDrag(e, 'top')}
                    onTouchStart={e => beginDrag(e, 'top')}
                >
                    <span className="year-map-handle-label">{startYear}</span>
                </div>

                {/* Bottom resize handle – shows endYear */}
                <div
                    className="year-map-handle year-map-handle-bottom"
                    onMouseDown={e => beginDrag(e, 'bottom')}
                    onTouchStart={e => beginDrag(e, 'bottom')}
                >
                    <span className="year-map-handle-label">{endYear}</span>
                </div>
            </div>
        </div>
    );
}

// ── Main component ───────────────────────────────────────────────────────────

export default function GroupTimeline() {
    const { slug } = useParams();
    const { user, isAuthenticated, refreshUser } = useAuth();
    const navigate = useNavigate();
    const [group, setGroup] = useState(null);
    const [membership, setMembership] = useState(null);
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [categories, setCategories] = useState([]);
    const [sort, setSort] = useState('desc');
    const [activeCategory, setActiveCategory] = useState(null);
    const [yearRange, setYearRange] = useState(null); // null until events load
    const [joinCode, setJoinCode] = useState('');
    const [joinError, setJoinError] = useState('');
    const [joinLoading, setJoinLoading] = useState(false);

    // Reset filters when navigating to a different group
    useEffect(() => {
        setActiveCategory(null);
        setYearRange(null);
        loadData();
        api.get('/categories').then(d => setCategories(d.categories)).catch(() => { });
    }, [slug]);

    const { minEventYear, maxEventYear } = useMemo(() => {
        const years = events
            .map(ev => parseInt(ev.event_date?.slice(0, 4) ?? '0', 10))
            .filter(y => y > 0);
        if (years.length === 0) return { minEventYear: null, maxEventYear: null };
        return { minEventYear: Math.min(...years), maxEventYear: Math.max(...years) };
    }, [events]);

    // Initialise year range once event years are known (or after group navigation reset)
    useEffect(() => {
        if (yearRange === null && minEventYear !== null) {
            setYearRange({ start: minEventYear, end: maxEventYear });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [minEventYear, maxEventYear]);

    const loadData = async () => {
        setLoading(true);
        try {
            const [groupData, eventData] = await Promise.all([
                api.get(`/groups/${slug}`),
                api.get(`/groups/${slug}/events?per_page=500`),
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

    const handleJoin = async (e) => {
        e.preventDefault();
        setJoinError('');
        setJoinLoading(true);
        try {
            await api.post(`/groups/${slug}/join`, { invite_code: joinCode });
            await refreshUser();
            await loadData();
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

    // ── Derived sidebar data ─────────────────────────────────────────────────

    const categoryCounts = useMemo(() => {
        const counts = {};
        events.forEach(ev => {
            if (ev.category_id) counts[ev.category_id] = (counts[ev.category_id] || 0) + 1;
        });
        return categories
            .map(cat => ({ ...cat, count: counts[cat.id] || 0 }))
            .filter(cat => cat.count > 0);
    }, [events, categories]);

    // Slider shows only when events span more than one calendar year
    const showSlider = minEventYear !== null && maxEventYear > minEventYear;

    const isYearFiltered = showSlider && yearRange !== null &&
        (yearRange.start > minEventYear || yearRange.end < maxEventYear);

    const resetYearRange = () => {
        if (minEventYear !== null) setYearRange({ start: minEventYear, end: maxEventYear });
    };

    // ── Filtered + sorted events ─────────────────────────────────────────────

    const displayedEvents = useMemo(() => {
        let result = [...events];
        if (activeCategory) result = result.filter(ev => ev.category_id === activeCategory);
        if (isYearFiltered && yearRange) {
            result = result.filter(ev => {
                const y = parseInt(ev.event_date?.slice(0, 4) ?? '0', 10);
                return y >= yearRange.start && y <= yearRange.end;
            });
        }
        result.sort((a, b) => {
            const diff = new Date(a.event_date) - new Date(b.event_date);
            return sort === 'desc' ? -diff : diff;
        });
        return result;
    }, [events, activeCategory, yearRange, isYearFiltered, sort]);

    if (loading) return <div className="loading-screen"><div className="spinner" /></div>;
    if (!group) return null;

    const canManage = membership === 'owner' || membership === 'admin';
    const isMember = !!membership;
    const hasActiveFilter = activeCategory || isYearFiltered;

    // Shared slider props
    const sliderProps = showSlider && yearRange ? {
        minYear:   minEventYear,
        maxYear:   maxEventYear,
        events,
        startYear: yearRange.start,
        endYear:   yearRange.end,
        onChange:  (s, e) => setYearRange({ start: s, end: e }),
    } : null;

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
                            {membership && <span className={`badge badge-role-${membership}`}>{membership}</span>}
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

                {/* Join prompt */}
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

                {/* Two-column layout: sidebar + main */}
                <div className="timeline-layout fade-in">

                    {/* ── Desktop sticky sidebar ── */}
                    <aside className="sidebar-desktop">
                        {/* Category filter */}
                        <div className="sidebar-section">
                            <div className="sidebar-section-title">Category</div>
                            <button
                                className={`sidebar-filter-btn ${!activeCategory ? 'active' : ''}`}
                                onClick={() => setActiveCategory(null)}
                            >
                                <span>All events</span>
                                <span className="sidebar-count">{events.length}</span>
                            </button>
                            {categoryCounts.map(cat => (
                                <button
                                    key={cat.id}
                                    className={`sidebar-filter-btn ${activeCategory === cat.id ? 'active' : ''}`}
                                    onClick={() => setActiveCategory(activeCategory === cat.id ? null : cat.id)}
                                >
                                    <span>{cat.icon} {cat.name}</span>
                                    <span className="sidebar-count">{cat.count}</span>
                                </button>
                            ))}
                        </div>

                        {/* Vertical minimap year slider – desktop */}
                        {sliderProps && (
                            <div className="sidebar-section sidebar-map-section">
                                <div className="sidebar-section-title">
                                    Year Range
                                    {isYearFiltered && (
                                        <button className="sidebar-reset-btn" onClick={resetYearRange}>Reset</button>
                                    )}
                                </div>
                                <YearMapSlider {...sliderProps} />
                            </div>
                        )}
                    </aside>

                    {/* ── Main content ── */}
                    <div className="timeline-main">

                        {/* Mobile filters – always visible, no toggle button needed */}
                        <div className="mobile-filters">
                            {categoryCounts.length > 0 && (
                                <div className="mobile-cat-row">
                                    <button
                                        className={`mobile-cat-btn ${!activeCategory ? 'active' : ''}`}
                                        onClick={() => setActiveCategory(null)}
                                    >All</button>
                                    {categoryCounts.map(cat => (
                                        <button
                                            key={cat.id}
                                            className={`mobile-cat-btn ${activeCategory === cat.id ? 'active' : ''}`}
                                            onClick={() => setActiveCategory(activeCategory === cat.id ? null : cat.id)}
                                        >
                                            {cat.icon} {cat.name}
                                        </button>
                                    ))}
                                </div>
                            )}

                            {/* Vertical minimap year slider – mobile (full width) */}
                            {sliderProps && (
                                <div className="mobile-map-section">
                                    <div className="mobile-map-title">
                                        Year Range
                                        {isYearFiltered && (
                                            <button className="sidebar-reset-btn" onClick={resetYearRange}>Reset</button>
                                        )}
                                    </div>
                                    <YearMapSlider {...sliderProps} />
                                </div>
                            )}
                        </div>

                        {/* Filter / sort bar */}
                        <div className="timeline-filters">
                            <select
                                className="form-select"
                                value={sort}
                                onChange={(e) => setSort(e.target.value)}
                                style={{ maxWidth: '160px' }}
                            >
                                <option value="desc">Newest First</option>
                                <option value="asc">Oldest First</option>
                            </select>

                            {activeCategory && (
                                <button className="filter-pill" onClick={() => setActiveCategory(null)}>
                                    {categories.find(c => c.id === activeCategory)?.icon}{' '}
                                    {categories.find(c => c.id === activeCategory)?.name} ✕
                                </button>
                            )}
                            {isYearFiltered && (
                                <button className="filter-pill" onClick={resetYearRange}>
                                    {yearRange.start}–{yearRange.end} ✕
                                </button>
                            )}
                        </div>

                        {/* Timeline */}
                        {displayedEvents.length === 0 ? (
                            <div className="empty-state fade-in">
                                <div className="empty-state-icon">📅</div>
                                <div className="empty-state-text">
                                    {hasActiveFilter ? 'No events match these filters' : 'No events yet'}
                                </div>
                                {isMember && !hasActiveFilter && (
                                    <Link to={`/g/${slug}/events/new`} className="btn btn-primary">Add the first event</Link>
                                )}
                                {hasActiveFilter && (
                                    <button className="btn btn-secondary" onClick={() => { setActiveCategory(null); resetYearRange(); }}>
                                        Clear filters
                                    </button>
                                )}
                            </div>
                        ) : (
                            <div className="timeline">
                                {displayedEvents.map((event, i) => (
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
                                                    {event.social_visibility && (
                                                        <span className="badge badge-members" title={`Social visibility: ${event.social_visibility.replace('_', ' ')}`}>
                                                            {SOCIAL_TIER_ICON[event.social_visibility]} {event.social_visibility.replace('_', ' ')}
                                                        </span>
                                                    )}
                                                    <span className={`badge badge-${event.visibility}`}>
                                                        {event.visibility === 'public' ? '🌍' : event.visibility === 'members' ? '👥' : '🔒'} {event.visibility}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className={`timeline-card-body${event.image_url ? (i % 2 === 0 ? ' has-image image-right' : ' has-image image-left') : ''}`}>
                                                <div className="timeline-card-content">
                                                    <h3 className="timeline-card-title">{event.title}</h3>
                                                    {event.description && (
                                                        <p className="timeline-card-desc">{event.description}</p>
                                                    )}
                                                    {event.album_url && (
                                                        <a href={event.album_url} target="_blank" rel="noopener noreferrer" className="timeline-card-album">
                                                            📸 View Photo Album →
                                                        </a>
                                                    )}
                                                </div>
                                                {event.image_url && (
                                                    <div className="timeline-card-image">
                                                        <img src={event.image_url} alt={event.title} />
                                                    </div>
                                                )}
                                            </div>
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
            </div>
        </div>
    );
}
