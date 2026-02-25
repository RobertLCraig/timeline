import { useState, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';

function MfaSection({ user, refreshUser }) {
    const [step, setStep] = useState('idle'); // idle | setup | disable
    const [qrSvg, setQrSvg] = useState('');
    const [secret, setSecret] = useState('');
    const [code, setCode] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [msg, setMsg] = useState('');
    const [loading, setLoading] = useState(false);

    const startSetup = async () => {
        setError(''); setMsg('');
        setLoading(true);
        try {
            const data = await api.post('/auth/mfa/enable');
            setSecret(data.secret);
            setQrSvg(atob(data.qr_svg));
            setStep('setup');
        } catch (err) {
            setError(err.data?.message || 'Failed to start 2FA setup');
        } finally {
            setLoading(false);
        }
    };

    const confirmSetup = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await api.post('/auth/mfa/confirm', { code });
            await refreshUser();
            setStep('idle');
            setMsg('Two-factor authentication enabled.');
            setCode('');
        } catch (err) {
            setError(err.data?.message || 'Invalid code');
        } finally {
            setLoading(false);
        }
    };

    const disableMfa = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await api.post('/auth/mfa/disable', { password });
            await refreshUser();
            setStep('idle');
            setMsg('Two-factor authentication disabled.');
            setPassword('');
        } catch (err) {
            setError(err.data?.message || 'Failed to disable 2FA');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="card fade-in" style={{ padding: 'var(--space-2xl)', marginBottom: 'var(--space-lg)' }}>
            <h2 style={{ marginBottom: 'var(--space-sm)' }}>Two-Factor Authentication</h2>
            <p className="text-muted text-sm" style={{ marginBottom: 'var(--space-md)' }}>
                Add an extra layer of security using an authenticator app (Google Authenticator, Authy, etc.).
            </p>

            {msg && <div className="alert alert-success" style={{ marginBottom: 'var(--space-md)' }}>{msg}</div>}
            {error && <div className="alert alert-error" style={{ marginBottom: 'var(--space-md)' }}>{error}</div>}

            {step === 'idle' && !user?.mfa_enabled && (
                <button className="btn btn-primary" onClick={startSetup} disabled={loading}>
                    {loading ? 'Starting...' : 'Enable 2FA'}
                </button>
            )}

            {step === 'idle' && user?.mfa_enabled && (
                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-md)', flexWrap: 'wrap' }}>
                    <span style={{ color: '#10b981', fontWeight: 600 }}>2FA is active</span>
                    <button className="btn btn-ghost btn-sm" style={{ color: '#ef4444' }}
                        onClick={() => { setStep('disable'); setError(''); }}>
                        Disable 2FA
                    </button>
                </div>
            )}

            {step === 'setup' && (
                <div>
                    <p className="text-sm text-muted" style={{ marginBottom: 'var(--space-md)' }}>
                        Scan this QR code with your authenticator app, then enter the 6-digit code to confirm.
                    </p>
                    <div
                        style={{ background: '#fff', padding: 12, display: 'inline-block', borderRadius: 8, marginBottom: 'var(--space-md)' }}
                        dangerouslySetInnerHTML={{ __html: qrSvg }}
                    />
                    <p className="text-xs text-muted" style={{ marginBottom: 'var(--space-md)' }}>
                        Or enter this key manually: <code style={{ fontFamily: 'monospace', letterSpacing: '0.1em' }}>{secret}</code>
                    </p>
                    <form onSubmit={confirmSetup} style={{ display: 'flex', gap: 'var(--space-sm)', alignItems: 'flex-end', flexWrap: 'wrap' }}>
                        <div className="form-group" style={{ margin: 0 }}>
                            <label className="form-label">Confirmation Code</label>
                            <input
                                type="text"
                                inputMode="numeric"
                                className="form-input"
                                value={code}
                                onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                placeholder="000000"
                                maxLength={6}
                                required
                                style={{ letterSpacing: '0.2em', width: 130 }}
                                autoFocus
                            />
                        </div>
                        <button type="submit" className="btn btn-primary" disabled={loading || code.length !== 6}>
                            {loading ? 'Verifying...' : 'Confirm'}
                        </button>
                        <button type="button" className="btn btn-ghost" onClick={() => { setStep('idle'); setError(''); }}>
                            Cancel
                        </button>
                    </form>
                </div>
            )}

            {step === 'disable' && (
                <form onSubmit={disableMfa}>
                    <div className="form-group">
                        <label className="form-label">Confirm your password to disable 2FA</label>
                        <input
                            type="password"
                            className="form-input"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Your current password"
                            required
                            autoFocus
                        />
                    </div>
                    <div style={{ display: 'flex', gap: 'var(--space-sm)' }}>
                        <button type="submit" className="btn btn-primary" disabled={loading}>
                            {loading ? 'Disabling...' : 'Disable 2FA'}
                        </button>
                        <button type="button" className="btn btn-ghost" onClick={() => { setStep('idle'); setError(''); }}>
                            Cancel
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function Profile() {
    const { user, updateProfile, setActiveGroup, refreshUser } = useAuth();
    const [searchParams] = useSearchParams();

    // Profile form
    const [form, setForm] = useState({
        name: user?.name || '',
        dob: user?.dob?.split('T')[0] || '',
    });
    const verifiedParam = searchParams.get('verified');
    const [msg, setMsg] = useState(verifiedParam === '1' ? 'Email verified successfully!' : '');
    const [error, setError] = useState(verifiedParam === 'invalid' ? 'Verification link is invalid or has expired.' : '');
    const [loading, setLoading] = useState(false);

    // Groups
    const [groups, setGroups] = useState([]);
    const [groupsLoading, setGroupsLoading] = useState(true);

    // Join by invite code
    const [joinCode, setJoinCode] = useState('');
    const [joinLoading, setJoinLoading] = useState(false);
    const [joinMsg, setJoinMsg] = useState('');
    const [joinError, setJoinError] = useState('');

    useEffect(() => {
        api.get('/groups')
            .then(data => setGroups(data.groups || []))
            .catch(() => { })
            .finally(() => setGroupsLoading(false));
    }, []);

    const handleProfileSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setMsg('');
        setLoading(true);
        try {
            await updateProfile({ name: form.name, dob: form.dob || null });
            setMsg('Profile updated successfully!');
            setTimeout(() => setMsg(''), 3000);
        } catch (err) {
            setError(err.data?.message || 'Failed to update profile');
        } finally {
            setLoading(false);
        }
    };

    const handleSwitchActive = async (groupId) => {
        setError('');
        try {
            await setActiveGroup(groupId);
            setMsg('Active group updated!');
            setTimeout(() => setMsg(''), 3000);
        } catch (err) {
            setError(err.data?.message || 'Failed to switch active group');
        }
    };

    const handleLeave = async (group) => {
        if (!confirm(`Leave "${group.name}"? You will lose access to this group's timeline.`)) return;
        setError('');
        try {
            await api.delete(`/groups/${group.slug}/leave`);
            setGroups(groups.filter(g => g.id !== group.id));
            await refreshUser();
            setMsg(`You have left "${group.name}".`);
            setTimeout(() => setMsg(''), 4000);
        } catch (err) {
            setError(err.data?.message || 'Failed to leave group');
        }
    };

    const handleJoinByCode = async (e) => {
        e.preventDefault();
        setJoinError('');
        setJoinMsg('');
        setJoinLoading(true);
        try {
            const data = await api.post('/groups/join-by-code', { invite_code: joinCode.trim().toUpperCase() });
            const newGroup = data.group;
            setGroups(prev => [...prev, { ...newGroup, pivot: { role: 'member' } }]);
            setJoinCode('');
            setJoinMsg(`You joined "${newGroup.name}"!`);
            await refreshUser();
            setTimeout(() => setJoinMsg(''), 4000);
        } catch (err) {
            setJoinError(err.data?.message || 'Invalid or expired invite code.');
        } finally {
            setJoinLoading(false);
        }
    };

    const roleColor = (role) => {
        if (role === 'owner') return '#f59e0b';
        if (role === 'admin') return '#6366f1';
        return '#64748b';
    };

    return (
        <div className="page">
            <div className="container container-md">

                {/* Profile Settings */}
                <div className="card fade-in" style={{ padding: 'var(--space-2xl)', marginBottom: 'var(--space-lg)' }}>
                    <h1 className="page-title" style={{ marginBottom: 'var(--space-sm)' }}>Profile</h1>
                    <p className="text-muted text-sm mb-lg">{user?.email}</p>

                    {msg && <div className="alert alert-success">{msg}</div>}
                    {error && <div className="alert alert-error">{error}</div>}

                    <form onSubmit={handleProfileSubmit}>
                        <div className="form-group">
                            <label className="form-label">Name</label>
                            <input
                                type="text"
                                className="form-input"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                                required
                            />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Date of Birth</label>
                            <input
                                type="date"
                                className="form-input"
                                value={form.dob}
                                onChange={(e) => setForm({ ...form, dob: e.target.value })}
                            />
                        </div>
                        <button type="submit" className="btn btn-primary btn-lg" disabled={loading}>
                            {loading ? 'Saving...' : 'Save Profile'}
                        </button>
                    </form>
                </div>

                {/* My Groups */}
                <div className="card fade-in" style={{ padding: 'var(--space-2xl)', marginBottom: 'var(--space-lg)' }}>
                    <div className="flex items-center justify-between flex-wrap gap-md mb-lg">
                        <h2 style={{ margin: 0 }}>My Groups</h2>
                        <Link to="/groups/new" className="btn btn-primary btn-sm">+ Create Group</Link>
                    </div>

                    {groupsLoading ? (
                        <div className="text-muted text-sm">Loading groups...</div>
                    ) : groups.length === 0 ? (
                        <p className="text-muted text-sm">You don't belong to any groups yet.</p>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-md)' }}>
                            {groups.map(group => {
                                const isActive = group.id === user?.active_group_id;
                                const role = group.pivot?.role || 'member';
                                return (
                                    <div key={group.id} style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'space-between',
                                        flexWrap: 'wrap',
                                        gap: 'var(--space-sm)',
                                        padding: 'var(--space-md) var(--space-lg)',
                                        background: 'var(--bg-secondary)',
                                        borderRadius: 'var(--border-radius)',
                                        border: isActive ? '1px solid var(--color-primary)' : '1px solid var(--border-color)',
                                    }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-md)' }}>
                                            <div style={{
                                                width: 40, height: 40, borderRadius: 8,
                                                background: `linear-gradient(135deg, hsl(${group.id * 45}, 70%, 50%), hsl(${group.id * 45 + 30}, 80%, 60%))`,
                                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                fontWeight: 800, color: 'white', fontSize: 'var(--font-size-lg)',
                                                flexShrink: 0,
                                            }}>
                                                {group.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-sm)' }}>
                                                    <Link to={`/g/${group.slug}`} style={{ fontWeight: 600, color: 'var(--text-primary)', textDecoration: 'none' }}>
                                                        {group.name}
                                                    </Link>
                                                    {isActive && (
                                                        <span className="badge badge-public" style={{ fontSize: '0.65rem' }}>Active</span>
                                                    )}
                                                </div>
                                                <div style={{ fontSize: 'var(--font-size-xs)', color: 'var(--text-muted)' }}>
                                                    <span style={{ color: roleColor(role), fontWeight: 600 }}>{role}</span>
                                                    {' · '}{group.members_count || 0} member{group.members_count !== 1 ? 's' : ''}
                                                </div>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', gap: 'var(--space-sm)', alignItems: 'center' }}>
                                            {!isActive && (
                                                <button
                                                    className="btn btn-secondary btn-sm"
                                                    onClick={() => handleSwitchActive(group.id)}
                                                >
                                                    Set Active
                                                </button>
                                            )}
                                            <Link to={`/g/${group.slug}`} className="btn btn-ghost btn-sm">View</Link>
                                            {role !== 'owner' && (
                                                <button
                                                    className="btn btn-ghost btn-sm"
                                                    style={{ color: '#ef4444' }}
                                                    onClick={() => handleLeave(group)}
                                                >
                                                    Leave
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Join a Group */}
                <div className="card fade-in" style={{ padding: 'var(--space-2xl)', marginBottom: 'var(--space-lg)' }}>
                    <h2 style={{ marginBottom: 'var(--space-sm)' }}>Join a Group</h2>
                    <p className="text-muted text-sm" style={{ marginBottom: 'var(--space-md)' }}>
                        Enter an invite code shared by a group member to join their timeline.
                    </p>

                    {joinMsg && <div className="alert alert-success" style={{ marginBottom: 'var(--space-md)' }}>{joinMsg}</div>}
                    {joinError && <div className="alert alert-error" style={{ marginBottom: 'var(--space-md)' }}>{joinError}</div>}

                    <form onSubmit={handleJoinByCode} style={{ display: 'flex', gap: 'var(--space-sm)', flexWrap: 'wrap' }}>
                        <input
                            type="text"
                            className="form-input"
                            placeholder="Invite code (e.g. ABCD1234)"
                            value={joinCode}
                            onChange={(e) => setJoinCode(e.target.value)}
                            style={{ flex: 1, minWidth: 200, textTransform: 'uppercase', letterSpacing: '0.1em' }}
                            maxLength={12}
                            required
                        />
                        <button type="submit" className="btn btn-primary" disabled={joinLoading || !joinCode.trim()}>
                            {joinLoading ? 'Joining...' : 'Join Group'}
                        </button>
                    </form>
                </div>

                {/* Two-Factor Authentication */}
                <MfaSection user={user} refreshUser={refreshUser} />

                {/* Visibility Settings */}
                <div className="card fade-in" style={{ padding: 'var(--space-2xl)' }}>
                    <h2 style={{ marginBottom: 'var(--space-md)' }}>Visibility Settings</h2>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-sm)' }}>
                        <Link to="/settings/category-visibility" style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            padding: 'var(--space-md) var(--space-lg)',
                            background: 'var(--bg-secondary)',
                            borderRadius: 'var(--border-radius)',
                            border: '1px solid var(--border-color)',
                            textDecoration: 'none',
                            color: 'var(--text-primary)',
                        }}>
                            <div>
                                <div style={{ fontWeight: 600 }}>Category Visibility Defaults</div>
                                <div style={{ fontSize: 'var(--font-size-xs)', color: 'var(--text-muted)' }}>
                                    Set the default social visibility for each event category
                                </div>
                            </div>
                            <span style={{ color: 'var(--text-muted)' }}>→</span>
                        </Link>
                        <Link to="/settings/group-visibility" style={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            padding: 'var(--space-md) var(--space-lg)',
                            background: 'var(--bg-secondary)',
                            borderRadius: 'var(--border-radius)',
                            border: '1px solid var(--border-color)',
                            textDecoration: 'none',
                            color: 'var(--text-primary)',
                        }}>
                            <div>
                                <div style={{ fontWeight: 600 }}>Group Visibility Settings</div>
                                <div style={{ fontSize: 'var(--font-size-xs)', color: 'var(--text-muted)' }}>
                                    Classify each group socially to control which events you see
                                </div>
                            </div>
                            <span style={{ color: 'var(--text-muted)' }}>→</span>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    );
}
