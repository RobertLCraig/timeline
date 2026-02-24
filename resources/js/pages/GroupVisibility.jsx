import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';

const GROUP_TIERS = [
    { value: 'family',        label: 'Family',        emoji: '👨‍👩‍👧‍👦', desc: 'Sees family, close friends, friends, acquaintances & public events' },
    { value: 'close_friends', label: 'Close Friends',  emoji: '💛',        desc: 'Sees close friends, friends, acquaintances & public events' },
    { value: 'friends',       label: 'Friends',        emoji: '🤝',        desc: 'Sees friends, acquaintances & public events' },
    { value: 'acquaintances', label: 'Acquaintances',  emoji: '👋',        desc: 'Sees acquaintances & public events only' },
];

export default function GroupVisibility() {
    const [groups, setGroups] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState({});
    const [msg, setMsg] = useState('');

    useEffect(() => {
        api.get('/visibility/groups')
            .then(d => setGroups(d.groups || []))
            .catch(() => { })
            .finally(() => setLoading(false));
    }, []);

    const handleChange = async (groupId, tier) => {
        setSaving(s => ({ ...s, [groupId]: true }));
        try {
            await api.put(`/visibility/groups/${groupId}`, { visibility_tier: tier });
            setGroups(gs => gs.map(g =>
                g.group_id === groupId ? { ...g, visibility_tier: tier, is_customised: true } : g
            ));
            setMsg('Saved!');
            setTimeout(() => setMsg(''), 2000);
        } catch { }
        finally {
            setSaving(s => ({ ...s, [groupId]: false }));
        }
    };

    const tierInfo = (value) => GROUP_TIERS.find(t => t.value === value);

    if (loading) return <div className="loading-screen"><div className="spinner" /></div>;

    return (
        <div className="page">
            <div className="container container-md">
                <div className="card fade-in" style={{ padding: 'var(--space-2xl)' }}>
                    <div className="flex items-center justify-between flex-wrap gap-md mb-lg">
                        <div>
                            <h1 className="page-title" style={{ marginBottom: 'var(--space-xs)' }}>Group Visibility Settings</h1>
                            <p className="text-muted text-sm">
                                Classify each group socially. This determines which events you see in each group's timeline.
                            </p>
                        </div>
                        <Link to="/profile" className="btn btn-secondary btn-sm">← Back to Profile</Link>
                    </div>

                    {msg && <div className="alert alert-success" style={{ marginBottom: 'var(--space-md)' }}>{msg}</div>}

                    {groups.length === 0 ? (
                        <p className="text-muted text-sm">You don't belong to any groups yet.</p>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-md)' }}>
                            {groups.map(g => {
                                const info = tierInfo(g.visibility_tier);
                                return (
                                    <div key={g.group_id} style={{
                                        padding: 'var(--space-lg)',
                                        background: 'var(--bg-secondary)',
                                        borderRadius: 'var(--border-radius)',
                                        border: '1px solid var(--border-color)',
                                    }}>
                                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 'var(--space-sm)', marginBottom: 'var(--space-md)' }}>
                                            <div>
                                                <Link to={`/g/${g.group_slug}`} style={{ fontWeight: 600, color: 'var(--text-primary)', textDecoration: 'none' }}>
                                                    {g.group_name}
                                                </Link>
                                                <span style={{ marginLeft: 8, fontSize: 'var(--font-size-xs)', color: 'var(--text-muted)', textTransform: 'capitalize' }}>
                                                    ({g.role})
                                                </span>
                                                {g.is_customised && (
                                                    <span style={{ marginLeft: 8, fontSize: 'var(--font-size-xs)', color: 'var(--color-primary)' }}>customised</span>
                                                )}
                                            </div>
                                            <select
                                                className="form-select"
                                                value={g.visibility_tier}
                                                onChange={(e) => handleChange(g.group_id, e.target.value)}
                                                disabled={saving[g.group_id]}
                                                style={{ minWidth: '180px' }}
                                            >
                                                {GROUP_TIERS.map(t => (
                                                    <option key={t.value} value={t.value}>{t.emoji} {t.label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        {info && (
                                            <p style={{ fontSize: 'var(--font-size-xs)', color: 'var(--text-muted)', margin: 0 }}>
                                                {info.emoji} {info.desc}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {/* Hierarchy reference */}
                    <div style={{
                        marginTop: 'var(--space-xl)',
                        background: 'rgba(99,102,241,0.07)',
                        border: '1px solid rgba(99,102,241,0.2)',
                        borderRadius: 'var(--border-radius)',
                        padding: 'var(--space-md) var(--space-lg)',
                    }}>
                        <div style={{ fontWeight: 600, marginBottom: 'var(--space-sm)', fontSize: 'var(--font-size-sm)' }}>Visibility Hierarchy</div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                            {GROUP_TIERS.map(t => (
                                <div key={t.value} style={{ fontSize: 'var(--font-size-xs)', color: 'var(--text-secondary)' }}>
                                    <strong>{t.emoji} {t.label}:</strong> {t.desc}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
