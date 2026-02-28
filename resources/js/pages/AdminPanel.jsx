import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import './AdminPanel.css';

// ── NSFW Settings tab ─────────────────────────────────────────────────────────

function NsfwSettingsTab() {
    const [settings, setSettings] = useState({ nsfw_checks_enabled: '0', nudity_threshold: '0.6' });
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [msg, setMsg] = useState('');

    useEffect(() => {
        api.get('/admin/settings')
            .then(d => setSettings(d.settings))
            .finally(() => setLoading(false));
    }, []);

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        setMsg('');
        try {
            const data = await api.put('/admin/settings', {
                nsfw_checks_enabled: settings.nsfw_checks_enabled,
                nudity_threshold: parseFloat(settings.nudity_threshold),
            });
            setSettings(data.settings);
            setMsg('Settings saved.');
            setTimeout(() => setMsg(''), 3000);
        } catch { setMsg('Save failed.'); }
        finally { setSaving(false); }
    };

    if (loading) return <div className="text-muted text-sm">Loading settings…</div>;

    const enabled = settings.nsfw_checks_enabled === '1';

    return (
        <div className="card fade-in" style={{ padding: 'var(--space-xl)' }}>
            <h3 style={{ marginBottom: 'var(--space-xs)' }}>NSFW Content Scanning</h3>
            <p className="text-muted text-sm" style={{ marginBottom: 'var(--space-lg)' }}>
                Uses the <strong>Sightengine</strong> API to scan images on upload.
                Requires <code>SIGHTENGINE_API_USER</code> and <code>SIGHTENGINE_API_SECRET</code> in your <code>.env</code>.
                Free tier: 500 checks/month.
            </p>

            {msg && <div className="alert alert-success" style={{ marginBottom: 'var(--space-md)' }}>{msg}</div>}

            <form onSubmit={save}>
                <div style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    padding: 'var(--space-md) var(--space-lg)',
                    background: 'var(--bg-secondary)', borderRadius: 'var(--border-radius)',
                    border: '1px solid var(--border-color)', marginBottom: 'var(--space-md)',
                }}>
                    <div>
                        <div style={{ fontWeight: 600 }}>Enable NSFW checks</div>
                        <div className="text-muted text-sm">Scan every uploaded image through Sightengine</div>
                    </div>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-sm)', cursor: 'pointer' }}>
                        <input
                            type="checkbox"
                            checked={enabled}
                            onChange={e => setSettings(s => ({ ...s, nsfw_checks_enabled: e.target.checked ? '1' : '0' }))}
                            style={{ width: 18, height: 18, cursor: 'pointer' }}
                        />
                        <span className="text-sm">{enabled ? 'On' : 'Off'}</span>
                    </label>
                </div>

                <div style={{
                    padding: 'var(--space-md) var(--space-lg)',
                    background: 'var(--bg-secondary)', borderRadius: 'var(--border-radius)',
                    border: '1px solid var(--border-color)', marginBottom: 'var(--space-lg)',
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 'var(--space-sm)' }}>
                        <div>
                            <div style={{ fontWeight: 600 }}>Nudity threshold</div>
                            <div className="text-muted text-sm">Uploads scoring above this are flagged for review</div>
                        </div>
                        <strong style={{ fontSize: 'var(--font-size-lg)', minWidth: 40, textAlign: 'right' }}>
                            {parseFloat(settings.nudity_threshold).toFixed(2)}
                        </strong>
                    </div>
                    <input
                        type="range"
                        min="0" max="1" step="0.05"
                        value={settings.nudity_threshold}
                        onChange={e => setSettings(s => ({ ...s, nudity_threshold: e.target.value }))}
                        style={{ width: '100%' }}
                    />
                    <div style={{ display: 'flex', justifyContent: 'space-between' }} className="text-muted text-sm">
                        <span>0.0 (flag everything)</span>
                        <span>1.0 (flag nothing)</span>
                    </div>
                </div>

                <button type="submit" className="btn btn-primary" disabled={saving}>
                    {saving ? 'Saving…' : 'Save Settings'}
                </button>
            </form>
        </div>
    );
}

// ── Content Flags tab ─────────────────────────────────────────────────────────

function ContentFlagsTab() {
    const [flags, setFlags] = useState([]);
    const [meta, setMeta] = useState({ total: 0, current_page: 1, last_page: 1 });
    const [statusFilter, setStatusFilter] = useState('pending');
    const [loading, setLoading] = useState(true);
    const [reviewingId, setReviewingId] = useState(null);

    const loadFlags = useCallback(async (status, page = 1) => {
        setLoading(true);
        try {
            const data = await api.get(`/admin/upload-flags?status=${status}&page=${page}`);
            setFlags(data.flags);
            setMeta(data.meta);
        } catch { }
        finally { setLoading(false); }
    }, []);

    useEffect(() => { loadFlags(statusFilter); }, [statusFilter, loadFlags]);

    const review = async (flagId, status) => {
        setReviewingId(flagId);
        try {
            const data = await api.put(`/admin/upload-flags/${flagId}`, { status });
            setFlags(prev => prev.map(f => f.id === flagId ? data.flag : f));
        } catch { }
        finally { setReviewingId(null); }
    };

    const statusBadge = (s) => {
        const map = { pending: 'badge-members', approved: 'badge-public', quarantined: 'badge-private' };
        return <span className={`badge ${map[s] || ''}`}>{s}</span>;
    };

    return (
        <div className="fade-in">
            <div style={{ display: 'flex', gap: 'var(--space-sm)', marginBottom: 'var(--space-lg)', flexWrap: 'wrap', alignItems: 'center' }}>
                {['pending', 'approved', 'quarantined'].map(s => (
                    <button
                        key={s}
                        className={`btn btn-sm ${statusFilter === s ? 'btn-primary' : 'btn-ghost'}`}
                        onClick={() => setStatusFilter(s)}
                        style={{ textTransform: 'capitalize' }}
                    >
                        {s}
                    </button>
                ))}
                <span className="text-muted text-sm" style={{ marginLeft: 'auto' }}>
                    {meta.total} result{meta.total !== 1 ? 's' : ''}
                </span>
            </div>

            {loading ? (
                <div className="text-muted text-sm">Loading…</div>
            ) : flags.length === 0 ? (
                <div className="card" style={{ padding: 'var(--space-xl)', textAlign: 'center' }}>
                    <p className="text-muted">No {statusFilter} flags.</p>
                </div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-md)' }}>
                    {flags.map(flag => (
                        <div key={flag.id} className="card" style={{
                            padding: 'var(--space-md) var(--space-lg)',
                            display: 'flex', gap: 'var(--space-lg)', alignItems: 'flex-start', flexWrap: 'wrap',
                        }}>
                            <a href={flag.url} target="_blank" rel="noopener noreferrer" style={{ flexShrink: 0 }}>
                                <img
                                    src={flag.url}
                                    alt="Flagged upload"
                                    style={{ width: 80, height: 80, objectFit: 'cover', borderRadius: 6, border: '1px solid var(--border-color)' }}
                                    onError={e => { e.target.style.display = 'none'; }}
                                />
                            </a>

                            <div style={{ flex: 1, minWidth: 200 }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-sm)', marginBottom: 'var(--space-xs)', flexWrap: 'wrap' }}>
                                    {statusBadge(flag.status)}
                                    <span className="text-muted text-sm">
                                        Score: <strong style={{ color: flag.top_score >= 0.8 ? '#ef4444' : '#f59e0b' }}>
                                            {(flag.top_score * 100).toFixed(0)}%
                                        </strong>
                                    </span>
                                    <span className="text-muted text-sm">·</span>
                                    <span className="text-muted text-sm">
                                        {flag.uploader?.name || 'deleted user'}
                                    </span>
                                    <span className="text-muted text-sm">·</span>
                                    <span className="text-muted text-sm">
                                        {new Date(flag.created_at).toLocaleString()}
                                    </span>
                                </div>

                                <div style={{ display: 'flex', gap: 'var(--space-md)', flexWrap: 'wrap', marginBottom: 'var(--space-xs)' }}>
                                    {flag.scores && Object.entries(flag.scores)
                                        .filter(([, v]) => typeof v === 'number' && v > 0.05)
                                        .sort(([, a], [, b]) => b - a)
                                        .slice(0, 4)
                                        .map(([k, v]) => (
                                            <span key={k} className="text-sm text-muted">
                                                {k.replace(/_/g, ' ')}: {(v * 100).toFixed(0)}%
                                            </span>
                                        ))
                                    }
                                </div>

                                {flag.reviewer && (
                                    <div className="text-muted text-sm">
                                        Reviewed by {flag.reviewer.name} · {new Date(flag.reviewed_at).toLocaleString()}
                                    </div>
                                )}
                            </div>

                            {flag.status === 'pending' && (
                                <div style={{ display: 'flex', gap: 'var(--space-sm)', flexShrink: 0 }}>
                                    <button
                                        className="btn btn-sm btn-secondary"
                                        disabled={reviewingId === flag.id}
                                        onClick={() => review(flag.id, 'approved')}
                                    >
                                        Approve
                                    </button>
                                    <button
                                        className="btn btn-sm"
                                        style={{ background: '#ef4444', color: '#fff' }}
                                        disabled={reviewingId === flag.id}
                                        onClick={() => review(flag.id, 'quarantined')}
                                    >
                                        Quarantine
                                    </button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {meta.last_page > 1 && (
                <div style={{ display: 'flex', justifyContent: 'center', gap: 'var(--space-sm)', marginTop: 'var(--space-lg)' }}>
                    {Array.from({ length: meta.last_page }, (_, i) => i + 1).map(p => (
                        <button
                            key={p}
                            className={`btn btn-sm ${meta.current_page === p ? 'btn-primary' : 'btn-ghost'}`}
                            onClick={() => loadFlags(statusFilter, p)}
                        >
                            {p}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Main AdminPanel ────────────────────────────────────────────────────────────

export default function AdminPanel() {
    const { isSuperAdmin } = useAuth();
    const [tab, setTab] = useState('codes');
    const [codes, setCodes] = useState([]);
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [codeForm, setCodeForm] = useState({ max_uses: 1 });

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        try {
            const [codesData, usersData] = await Promise.all([
                api.get('/admin/referral-codes'),
                api.get('/admin/users'),
            ]);
            setCodes(codesData.referral_codes);
            setUsers(usersData.users);
        } catch { }
        setLoading(false);
    };

    const createCode = async (e) => {
        e.preventDefault();
        const data = await api.post('/admin/referral-codes', codeForm);
        setCodes([data.referral_code, ...codes]);
    };

    const deleteCode = async (id) => {
        await api.delete(`/admin/referral-codes/${id}`);
        setCodes(codes.filter(c => c.id !== id));
    };

    const changeUserRole = async (userId, role) => {
        await api.put(`/admin/users/${userId}/role`, { platform_role: role });
        setUsers(users.map(u => u.id === userId ? { ...u, platform_role: role } : u));
    };

    if (loading) return <div className="loading-screen"><div className="spinner" /></div>;

    return (
        <div className="page">
            <div className="container">
                <h1 className="page-title">Admin Panel</h1>
                <p className="page-subtitle mb-lg">Manage platform referral codes, users, and content moderation</p>

                <div className="admin-tabs">
                    <button className={`admin-tab ${tab === 'codes' ? 'active' : ''}`} onClick={() => setTab('codes')}>
                        🎟 Referral Codes ({codes.length})
                    </button>
                    <button className={`admin-tab ${tab === 'users' ? 'active' : ''}`} onClick={() => setTab('users')}>
                        👥 Users ({users.length})
                    </button>
                    <button className={`admin-tab ${tab === 'flags' ? 'active' : ''}`} onClick={() => setTab('flags')}>
                        🚩 Content Flags
                    </button>
                    <button className={`admin-tab ${tab === 'nsfw' ? 'active' : ''}`} onClick={() => setTab('nsfw')}>
                        ⚙️ NSFW Settings
                    </button>
                </div>

                {tab === 'codes' && (
                    <div className="fade-in">
                        <div className="card" style={{ padding: 'var(--space-xl)', marginBottom: 'var(--space-lg)' }}>
                            <h3 style={{ marginBottom: 'var(--space-md)' }}>Generate Referral Code</h3>
                            <form onSubmit={createCode} className="flex gap-md items-center flex-wrap">
                                <div className="form-group" style={{ marginBottom: 0 }}>
                                    <label className="form-label">Max Uses</label>
                                    <input
                                        type="number"
                                        className="form-input"
                                        value={codeForm.max_uses}
                                        onChange={(e) => setCodeForm({ max_uses: parseInt(e.target.value) || 1 })}
                                        min={1}
                                        max={1000}
                                        style={{ width: '100px' }}
                                    />
                                </div>
                                <button type="submit" className="btn btn-primary" style={{ marginTop: '18px' }}>Generate</button>
                            </form>
                        </div>

                        <div className="card" style={{ padding: 'var(--space-xl)' }}>
                            <h3 style={{ marginBottom: 'var(--space-md)' }}>All Referral Codes</h3>
                            {codes.length === 0 ? (
                                <p className="text-muted">No codes generated yet.</p>
                            ) : (
                                <div className="admin-table-wrap">
                                    <table className="admin-table">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Created By</th>
                                                <th>Usage</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {codes.map(code => (
                                                <tr key={code.id}>
                                                    <td><code className="invite-code">{code.code}</code></td>
                                                    <td className="text-sm">{code.creator?.name || '—'}</td>
                                                    <td className="text-sm">{code.current_uses}/{code.max_uses}</td>
                                                    <td>
                                                        <span className={`badge ${code.current_uses >= code.max_uses ? 'badge-private' : 'badge-public'}`}>
                                                            {code.current_uses >= code.max_uses ? 'Exhausted' : 'Active'}
                                                        </span>
                                                    </td>
                                                    <td className="text-sm text-muted">
                                                        {new Date(code.created_at).toLocaleDateString()}
                                                    </td>
                                                    <td>
                                                        <button onClick={() => deleteCode(code.id)} className="btn btn-ghost btn-sm" style={{ color: '#ef4444' }}>
                                                            Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {tab === 'users' && (
                    <div className="card fade-in" style={{ padding: 'var(--space-xl)' }}>
                        <h3 style={{ marginBottom: 'var(--space-md)' }}>All Users</h3>
                        <div className="admin-table-wrap">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Groups</th>
                                        <th>Joined</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map(u => (
                                        <tr key={u.id}>
                                            <td className="text-sm font-semibold">{u.name}</td>
                                            <td className="text-sm text-muted">{u.email}</td>
                                            <td>
                                                <span className={`badge ${u.platform_role === 'super_admin' ? 'badge-role-owner' : 'badge-role-member'}`}>
                                                    {u.platform_role}
                                                </span>
                                            </td>
                                            <td className="text-sm">{u.groups_count}</td>
                                            <td className="text-sm text-muted">{new Date(u.created_at).toLocaleDateString()}</td>
                                            <td>
                                                {u.platform_role !== 'super_admin' ? (
                                                    <button onClick={() => changeUserRole(u.id, 'super_admin')} className="btn btn-ghost btn-sm">
                                                        Make Admin
                                                    </button>
                                                ) : (
                                                    <button onClick={() => changeUserRole(u.id, 'user')} className="btn btn-ghost btn-sm">
                                                        Remove Admin
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {tab === 'flags' && <ContentFlagsTab />}
                {tab === 'nsfw'  && <NsfwSettingsTab />}
            </div>
        </div>
    );
}
