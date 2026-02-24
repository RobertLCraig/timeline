import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';

const TIERS = [
    { value: 'family',        label: 'Family',        emoji: '👨‍👩‍👧‍👦' },
    { value: 'close_friends', label: 'Close Friends',  emoji: '💛' },
    { value: 'friends',       label: 'Friends',        emoji: '🤝' },
    { value: 'acquaintances', label: 'Acquaintances',  emoji: '👋' },
    { value: 'public',        label: 'Public',         emoji: '🌍' },
    { value: 'private',       label: 'Private',        emoji: '🔒' },
];

export default function CategoryVisibility() {
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState({});
    const [msg, setMsg] = useState('');

    useEffect(() => {
        api.get('/visibility/categories')
            .then(d => setCategories(d.categories || []))
            .catch(() => { })
            .finally(() => setLoading(false));
    }, []);

    const handleChange = async (categoryId, tier) => {
        setSaving(s => ({ ...s, [categoryId]: true }));
        try {
            await api.put(`/visibility/categories/${categoryId}`, { visibility_tier: tier });
            setCategories(cats => cats.map(c =>
                c.id === categoryId ? { ...c, visibility_tier: tier, is_customised: true } : c
            ));
            setMsg('Saved!');
            setTimeout(() => setMsg(''), 2000);
        } catch { }
        finally {
            setSaving(s => ({ ...s, [categoryId]: false }));
        }
    };

    if (loading) return <div className="loading-screen"><div className="spinner" /></div>;

    return (
        <div className="page">
            <div className="container container-md">
                <div className="card fade-in" style={{ padding: 'var(--space-2xl)' }}>
                    <div className="flex items-center justify-between flex-wrap gap-md mb-lg">
                        <div>
                            <h1 className="page-title" style={{ marginBottom: 'var(--space-xs)' }}>Category Visibility Defaults</h1>
                            <p className="text-muted text-sm">
                                Set the default social visibility for new events in each category.
                                You can always override this when creating an event.
                            </p>
                        </div>
                        <Link to="/profile" className="btn btn-secondary btn-sm">← Back to Profile</Link>
                    </div>

                    {msg && <div className="alert alert-success" style={{ marginBottom: 'var(--space-md)' }}>{msg}</div>}

                    <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-sm)' }}>
                        {categories.map(cat => (
                            <div key={cat.id} style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                flexWrap: 'wrap',
                                gap: 'var(--space-md)',
                                padding: 'var(--space-md) var(--space-lg)',
                                background: 'var(--bg-secondary)',
                                borderRadius: 'var(--border-radius)',
                                border: '1px solid var(--border-color)',
                            }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-md)' }}>
                                    <div style={{
                                        width: 36, height: 36, borderRadius: 8,
                                        background: cat.color || '#6366f1',
                                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        fontSize: '1.2rem',
                                    }}>
                                        {cat.icon}
                                    </div>
                                    <div>
                                        <div style={{ fontWeight: 600 }}>{cat.name}</div>
                                        {cat.is_customised && (
                                            <div style={{ fontSize: 'var(--font-size-xs)', color: 'var(--color-primary)' }}>customised</div>
                                        )}
                                    </div>
                                </div>
                                <select
                                    className="form-select"
                                    value={cat.visibility_tier}
                                    onChange={(e) => handleChange(cat.id, e.target.value)}
                                    disabled={saving[cat.id]}
                                    style={{ minWidth: '160px' }}
                                >
                                    {TIERS.map(t => (
                                        <option key={t.value} value={t.value}>{t.emoji} {t.label}</option>
                                    ))}
                                </select>
                            </div>
                        ))}
                    </div>

                    <div className="alert" style={{
                        marginTop: 'var(--space-xl)',
                        background: 'rgba(99,102,241,0.07)',
                        border: '1px solid rgba(99,102,241,0.2)',
                        borderRadius: 'var(--border-radius)',
                        padding: 'var(--space-md) var(--space-lg)',
                        fontSize: 'var(--font-size-sm)',
                        color: 'var(--text-secondary)',
                    }}>
                        Changes here only affect new events you create — existing events are not updated.
                    </div>
                </div>
            </div>
        </div>
    );
}
