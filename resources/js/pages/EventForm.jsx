import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../lib/api';

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
    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [pageLoading, setPageLoading] = useState(isEdit);

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
                    if (ev.image_url) {
                        setImagePreview(ev.image_url.startsWith('http') ? ev.image_url : `http://localhost:8000${ev.image_url}`);
                    }
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

    const handleImageChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setImageFile(file);
            setImagePreview(URL.createObjectURL(file));
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            let image_url = form.image_url;

            if (imageFile) {
                const formData = new FormData();
                formData.append('image', imageFile);
                const uploadResult = await api.post('/upload', formData);
                image_url = uploadResult.url;
            }

            const body = {
                ...form,
                category_id: form.category_id || null,
                image_url,
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
                            <label className="form-label">Photo</label>
                            <input type="file" accept="image/*" onChange={handleImageChange}
                                className="form-input" style={{ padding: '8px' }} />
                            {imagePreview && (
                                <div style={{ marginTop: 'var(--space-sm)', borderRadius: 'var(--border-radius-sm)', overflow: 'hidden', maxHeight: '200px' }}>
                                    <img src={imagePreview} alt="Preview" style={{ width: '100%', objectFit: 'cover' }} />
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
                            <button type="submit" className="btn btn-primary btn-lg" disabled={loading}>
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
        @media (max-width: 600px) {
          .visibility-options, .social-tier-options { grid-template-columns: 1fr; }
        }
      `}</style>
        </div>
    );
}
