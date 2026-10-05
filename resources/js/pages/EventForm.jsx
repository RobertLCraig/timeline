import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../lib/api';
import { scanImageFile, checksEnabledFrom } from '../lib/nsfwScan';

const TIER_LABELS = {
    family:        { emoji: '👨‍👩‍👧‍👦', label: 'Family',        desc: 'Only family members' },
    close_friends: { emoji: '💛',        label: 'Close Friends', desc: 'Close friends & family' },
    friends:       { emoji: '🤝',        label: 'Friends',       desc: 'Friends and above' },
    acquaintances: { emoji: '👋',        label: 'Acquaintances', desc: 'All group members' },
    public:        { emoji: '🌍',        label: 'Public',        desc: 'Anyone, including non-members' },
    private:       { emoji: '🔒',        label: 'Private',       desc: 'Only you' },
};

export default function EventForm() {
    const { slug, id } = useParams();
    const navigate = useNavigate();
    const isEdit = !!id;

    const [categories, setCategories] = useState([]);
    // Per-user category defaults loaded from API
    const [categoryDefaults, setCategoryDefaults] = useState({});

    const [form, setForm] = useState({
        title: '',
        description: '',
        event_date: '',
        category_id: '',
        visibility: 'members',
        social_visibility: 'friends',
        visibility_is_override: false,
        album_url: '',
    });
    // The event's photos in order; the first is the cover. Saved ones carry
    // a url, newly picked ones a file that is uploaded on submit.
    const [photos, setPhotos] = useState([]);
    const [error, setError] = useState('');
    const [scanning, setScanning] = useState(false);
    const [loading, setLoading] = useState(false);
    const [pageLoading, setPageLoading] = useState(isEdit);
    // The admin kill switch. Held as a promise so a photo picked before it
    // arrives waits for it; if it cannot be read, the server scan decides.
    const checksEnabled = useRef(Promise.resolve(false));

    useEffect(() => {
        checksEnabled.current = checksEnabledFrom(api.get('/upload/settings'));
    }, []);

    useEffect(() => {
        // Load categories and per-user category visibility defaults in parallel
        Promise.all([
            api.get('/categories'),
            api.get('/visibility/categories').catch(() => ({ categories: [] })),
        ]).then(([catData, visData]) => {
            setCategories(catData.categories || []);
            // Build lookup: category_id → visibility_tier
            const defaults = {};
            (visData.categories || []).forEach(c => {
                defaults[c.id] = c.visibility_tier;
            });
            setCategoryDefaults(defaults);
        });

        if (isEdit) {
            api.get(`/groups/${slug}/events/${id}`)
                .then(d => {
                    const ev = d.event;
                    setForm({
                        title: ev.title,
                        description: ev.description || '',
                        event_date: ev.event_date?.split('T')[0] || '',
                        category_id: ev.category_id || '',
                        visibility: ev.visibility,
                        social_visibility: ev.social_visibility || 'friends',
                        visibility_is_override: ev.visibility_is_override || false,
                        album_url: ev.album_url || '',
                    });
                    setPhotos((ev.image_urls || []).map(url => ({ url, preview: url })));
                })
                .catch(() => navigate(`/g/${slug}`))
                .finally(() => setPageLoading(false));
        }
    }, [slug, id]);

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        const newValue = type === 'checkbox' ? checked : value;

        if (name === 'category_id' && !form.visibility_is_override) {
            // When category changes and not overriding, auto-update social_visibility
            const defaultTier = categoryDefaults[value] || 'friends';
            setForm(f => ({ ...f, category_id: value, social_visibility: defaultTier }));
        } else {
            setForm(f => ({ ...f, [name]: newValue }));
        }
    };

    const handleOverrideToggle = (useOverride) => {
        if (!useOverride) {
            // Revert to category default
            const defaultTier = categoryDefaults[form.category_id] || 'friends';
            setForm(f => ({ ...f, visibility_is_override: false, social_visibility: defaultTier }));
        } else {
            setForm(f => ({ ...f, visibility_is_override: true }));
        }
    };

    const handleImageChange = async (e) => {
        const input = e.target;
        const files = [...input.files];
        input.value = ''; // so picking the same files again still fires change
        if (!files.length) return;

        // Pre-scan each file in the browser before anything is sent. A blocked
        // picture is never uploaded; a scan that cannot run allows it and the
        // server-side scan still has the last word.
        setError('');
        setScanning(true);
        const enabled = await checksEnabled.current;
        const added = [];
        const refused = [];
        for (const file of files) {
            const blocked = await scanImageFile(file, { enabled });
            if (blocked) refused.push(`"${file.name}" (${blocked})`);
            else added.push({ file, preview: URL.createObjectURL(file) });
        }
        setScanning(false);

        if (refused.length) {
            setError(`These photos look like adult content and were not added: ${refused.join(', ')}.`);
        }
        setPhotos(p => [...p, ...added]);
    };

    const removePhoto = (index) => setPhotos(p => p.filter((_, i) => i !== index));

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            // One upload call per new photo, in order, so each is scanned.
            const image_urls = [];
            for (const photo of photos) {
                if (photo.file) {
                    const formData = new FormData();
                    formData.append('image', photo.file);
                    image_urls.push((await api.post('/upload', formData)).url);
                } else {
                    image_urls.push(photo.url);
                }
            }

            const body = {
                ...form,
                category_id: form.category_id || null,
                image_urls,
            };

            if (isEdit) {
                await api.put(`/groups/${slug}/events/${id}`, body);
            } else {
                await api.post(`/groups/${slug}/events`, body);
            }

            navigate(`/g/${slug}`);
        } catch (err) {
            setError(err.data?.message || 'Failed to save event');
        } finally {
            setLoading(false);
        }
    };

    // Determine what social tier is currently shown (default from category or override)
    const effectiveSocialTier = form.social_visibility || 'friends';
    const categoryDefaultTier = categoryDefaults[form.category_id] || 'friends';

    if (pageLoading) {
        return <div className="loading-screen"><div className="spinner" /></div>;
    }

    return (
        <div className="page">
            <div className="container container-md">
                <div className="card fade-in" style={{ padding: 'var(--space-2xl)' }}>
                    <h1 className="page-title" style={{ marginBottom: 'var(--space-lg)' }}>
                        {isEdit ? 'Edit Event' : 'Add Event'}
                    </h1>

                    {error && <div className="alert alert-error">{error}</div>}

                    <form onSubmit={handleSubmit}>
                        <div className="form-group">
                            <label className="form-label">Title *</label>
                            <input
                                type="text"
                                name="title"
                                className="form-input"
                                value={form.title}
                                onChange={handleChange}
                                placeholder="What happened?"
                                required
                                maxLength={255}
                            />
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--space-md)' }}>
                            <div className="form-group">
                                <label className="form-label">Date *</label>
                                <input
                                    type="date"
                                    name="event_date"
                                    className="form-input"
                                    value={form.event_date}
                                    onChange={handleChange}
                                    required
                                />
                            </div>
                            <div className="form-group">
                                <label className="form-label">Category</label>
                                <select
                                    name="category_id"
                                    className="form-select"
                                    value={form.category_id}
                                    onChange={handleChange}
                                >
                                    <option value="">Select category</option>
                                    {categories.map(cat => (
                                        <option key={cat.id} value={cat.id}>{cat.icon} {cat.name}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="form-group">
                            <label className="form-label">Description</label>
                            <textarea
                                name="description"
                                className="form-textarea"
                                value={form.description}
                                onChange={handleChange}
                                placeholder="Tell the story behind this event..."
                                maxLength={5000}
                            />
                        </div>

                        {/* Access Visibility (who can see the post at all) */}
                        <div className="form-group">
                            <label className="form-label">Access</label>
                            <div className="visibility-options">
                                {[
                                    { value: 'public',  label: '🌍 Public',  desc: 'Anyone can see' },
                                    { value: 'members', label: '👥 Members', desc: 'Group members only' },
                                    { value: 'private', label: '🔒 Private', desc: 'Only you & admins' },
                                ].map(opt => (
                                    <label key={opt.value} className={`visibility-option ${form.visibility === opt.value ? 'active' : ''}`}>
                                        <input type="radio" name="visibility" value={opt.value}
                                            checked={form.visibility === opt.value} onChange={handleChange} hidden />
                                        <span className="visibility-label">{opt.label}</span>
                                        <span className="visibility-desc">{opt.desc}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        {/* Social Visibility Tier */}
                        <div className="form-group">
                            <label className="form-label">
                                Social Visibility
                                {!form.visibility_is_override && form.category_id && (
                                    <span className="form-hint" style={{ marginLeft: 8, fontWeight: 400 }}>
                                        — inheriting category default
                                    </span>
                                )}
                            </label>

                            {/* Toggle: Use category default vs Custom */}
                            {form.category_id && (
                                <div style={{ display: 'flex', gap: 'var(--space-sm)', marginBottom: 'var(--space-md)' }}>
                                    <button
                                        type="button"
                                        className={`btn btn-sm ${!form.visibility_is_override ? 'btn-primary' : 'btn-secondary'}`}
                                        onClick={() => handleOverrideToggle(false)}
                                    >
                                        Use category default
                                        {!form.visibility_is_override && ` (${TIER_LABELS[categoryDefaultTier]?.label})`}
                                    </button>
                                    <button
                                        type="button"
                                        className={`btn btn-sm ${form.visibility_is_override ? 'btn-primary' : 'btn-secondary'}`}
                                        onClick={() => handleOverrideToggle(true)}
                                    >
                                        Custom visibility
                                    </button>
                                </div>
                            )}

                            {/* Show tier options when override is active or no category selected */}
                            {(form.visibility_is_override || !form.category_id) && (
                                <div className="social-tier-options">
                                    {Object.entries(TIER_LABELS).map(([value, { emoji, label, desc }]) => (
                                        <label key={value} className={`visibility-option ${effectiveSocialTier === value ? 'active' : ''}`}>
                                            <input type="radio" name="social_visibility" value={value}
                                                checked={effectiveSocialTier === value} onChange={handleChange} hidden />
                                            <span className="visibility-label">{emoji} {label}</span>
                                            <span className="visibility-desc">{desc}</span>
                                        </label>
                                    ))}
                                </div>
                            )}

                            {/* Show the inherited tier when not overriding */}
                            {!form.visibility_is_override && form.category_id && (
                                <div style={{
                                    padding: 'var(--space-sm) var(--space-md)',
                                    background: 'var(--bg-secondary)',
                                    borderRadius: 'var(--border-radius-sm)',
                                    border: '1px solid var(--border-color)',
                                    fontSize: 'var(--font-size-sm)',
                                    color: 'var(--text-secondary)',
                                }}>
                                    {TIER_LABELS[categoryDefaultTier]?.emoji} {TIER_LABELS[categoryDefaultTier]?.label}
                                    {' — '}{TIER_LABELS[categoryDefaultTier]?.desc}
                                </div>
                            )}
                        </div>

                        <div className="form-group">
                            <label className="form-label">Photos</label>
                            <input type="file" accept="image/*" multiple onChange={handleImageChange}
                                className="form-input" style={{ padding: '8px' }} disabled={scanning} />
                            {scanning && <span className="form-hint">Checking these photos…</span>}
                            {photos.length > 0 && (
                                <div className="photo-thumbs">
                                    {photos.map((photo, i) => (
                                        <div key={photo.preview} className="photo-thumb">
                                            <img src={photo.preview} alt={`Photo ${i + 1}`} />
                                            {i === 0 && <span className="photo-thumb-cover">Cover</span>}
                                            <button type="button" className="photo-thumb-remove"
                                                onClick={() => removePhoto(i)} aria-label={`Remove photo ${i + 1}`}>✕</button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="form-group">
                            <label className="form-label">📸 Photo Album Link (optional)</label>
                            <input type="url" name="album_url" className="form-input"
                                value={form.album_url} onChange={handleChange}
                                placeholder="https://photos.google.com/album/..." />
                            <span className="form-hint">Link to a Google Photos, iCloud, or other photo album.</span>
                        </div>

                        <div className="flex gap-md mt-lg">
                            <button type="submit" className="btn btn-primary btn-lg" disabled={loading || scanning}>
                                {loading ? 'Saving...' : (isEdit ? 'Save Changes' : 'Create Event')}
                            </button>
                            <button type="button" className="btn btn-secondary btn-lg" onClick={() => navigate(`/g/${slug}`)}>
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <style>{`
        .visibility-options, .social-tier-options {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: var(--space-sm);
        }
        .social-tier-options {
          grid-template-columns: repeat(3, 1fr);
        }
        .visibility-option {
          display: flex;
          flex-direction: column;
          gap: 2px;
          padding: var(--space-md);
          background: var(--bg-input);
          border: 2px solid var(--border-color);
          border-radius: var(--border-radius-sm);
          cursor: pointer;
          transition: all var(--transition-fast);
          text-align: center;
        }
        .visibility-option:hover { border-color: var(--border-color-hover); }
        .visibility-option.active {
          border-color: var(--color-primary);
          background: rgba(99, 102, 241, 0.05);
        }
        .visibility-label { font-weight: 600; font-size: var(--font-size-sm); }
        .visibility-desc { font-size: var(--font-size-xs); color: var(--text-muted); }
        .photo-thumbs {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
          gap: var(--space-sm);
          margin-top: var(--space-sm);
        }
        .photo-thumb {
          position: relative;
          aspect-ratio: 1;
          border-radius: var(--border-radius-sm);
          overflow: hidden;
          border: 1px solid var(--border-color);
        }
        .photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .photo-thumb-cover {
          position: absolute; left: 4px; bottom: 4px;
          padding: 0 6px;
          font-size: var(--font-size-xs);
          background: var(--color-primary); color: #fff;
          border-radius: var(--border-radius-sm);
        }
        .photo-thumb-remove {
          position: absolute; top: 4px; right: 4px;
          width: 24px; height: 24px;
          border: none; border-radius: 50%;
          background: rgba(0, 0, 0, 0.6); color: #fff;
          cursor: pointer;
        }
        @media (max-width: 600px) {
          .visibility-options, .social-tier-options { grid-template-columns: 1fr; }
        }
      `}</style>
        </div>
    );
}
