import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../lib/api';
import './GroupSettings.css';

export default function GroupSettings() {
    const { slug } = useParams();
    const navigate = useNavigate();
    const [group, setGroup] = useState(null);
    const [members, setMembers] = useState([]);
    const [invites, setInvites] = useState([]);
    const [loading, setLoading] = useState(true);
    const [groupForm, setGroupForm] = useState({ name: '', description: '' });
    const [inviteForm, setInviteForm] = useState({ max_uses: 1, email: '' });
    const [inviteMsg, setInviteMsg] = useState(null);
    const [msg, setMsg] = useState('');
    const [importing, setImporting] = useState(false);
    const [importMsg, setImportMsg] = useState(null);

    useEffect(() => {
        loadAll();
    }, [slug]);

    const loadAll = async () => {
        try {
            const [gData, mData, iData] = await Promise.all([
                api.get(`/groups/${slug}`),
                api.get(`/groups/${slug}/members`),
                api.get(`/groups/${slug}/invites`),
            ]);
            setGroup(gData.group);
            setGroupForm({ name: gData.group.name, description: gData.group.description || '' });
            setMembers(mData.members);
            setInvites(iData.invites);
        } catch {
            navigate('/dashboard');
        } finally {
            setLoading(false);
        }
    };

    const updateGroup = async (e) => {
        e.preventDefault();
        try {
            await api.put(`/groups/${slug}`, groupForm);
            setMsg('Group updated!');
            setTimeout(() => setMsg(''), 3000);
        } catch { }
    };

    const createInvite = async (e) => {
        e.preventDefault();
        setInviteMsg(null);
        try {
            const data = await api.post(`/groups/${slug}/invites`, inviteForm);
            setInvites([data.invite, ...invites]);
            if (data.email_sent === true) {
                setInviteMsg({ ok: true, text: `Invite emailed to ${inviteForm.email}.` });
                setInviteForm({ ...inviteForm, email: '' });
            } else if (data.email_sent === false) {
                setInviteMsg({ ok: false, text: `The code was made, but the email did not go. Send ${data.invite.code} to them by hand.` });
            }
        } catch (err) {
            setInviteMsg({ ok: false, text: err.data?.errors?.email?.[0] || err.data?.message || 'Could not create the invite.' });
        }
    };

    const deleteInvite = async (id) => {
        await api.delete(`/groups/${slug}/invites/${id}`);
        setInvites(invites.filter(i => i.id !== id));
    };

    const changeMemberRole = async (userId, role) => {
        await api.put(`/groups/${slug}/members/${userId}`, { role });
        setMembers(members.map(m => m.id === userId ? { ...m, role } : m));
    };

    const removeMember = async (userId) => {
        if (!confirm('Remove this member?')) return;
        await api.delete(`/groups/${slug}/members/${userId}`);
        setMembers(members.filter(m => m.id !== userId));
    };

    const importZip = async (e) => {
        const file = e.target.files[0];
        e.target.value = '';
        if (!file) return;
        setImporting(true);
        setImportMsg(null);
        try {
            const form = new FormData();
            form.append('file', file);
            const data = await api.post(`/groups/${slug}/import`, form);
            const skipped = data.rejected.length ? ` Skipped ${data.rejected.length}: ${data.rejected.join('; ')}` : '';
            setImportMsg({ ok: true, text: `Added ${data.created} events, updated ${data.updated}.${skipped}` });
        } catch (err) {
            setImportMsg({ ok: false, text: err.data?.errors?.file?.[0] || err.data?.message || 'Could not load that zip.' });
        } finally {
            setImporting(false);
        }
    };

    const deleteGroup = async () => {
        if (!confirm('This will permanently delete the group and all its events. Are you sure?')) return;
        await api.delete(`/groups/${slug}`);
        navigate('/dashboard');
    };

    if (loading) return <div className="loading-screen"><div className="spinner" /></div>;
    if (!group) return null;

    return (
        <div className="page">
            <div className="container container-md">
                <h1 className="page-title">Group Settings</h1>
                <p className="page-subtitle mb-lg">{group.name}</p>

                {msg && <div className="alert alert-success">{msg}</div>}

                {/* Group Info */}
                <section className="settings-section card fade-in">
                    <h2>General</h2>
                    <form onSubmit={updateGroup}>
                        <div className="form-group">
                            <label className="form-label">Name</label>
                            <input
                                type="text"
                                className="form-input"
                                value={groupForm.name}
                                onChange={(e) => setGroupForm({ ...groupForm, name: e.target.value })}
                                required
                            />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Description</label>
                            <textarea
                                className="form-textarea"
                                value={groupForm.description}
                                onChange={(e) => setGroupForm({ ...groupForm, description: e.target.value })}
                            />
                        </div>
                        <button type="submit" className="btn btn-primary">Save Changes</button>
                    </form>
                </section>

                {/* Invite Codes */}
                <section className="settings-section card fade-in">
                    <h2>Invite Codes</h2>
                    <p className="text-muted text-sm mb-md">Generate invite codes to let others join this group.</p>

                    <form onSubmit={createInvite} className="flex gap-md items-center mb-lg flex-wrap">
                        <div className="form-group" style={{ marginBottom: 0 }}>
                            <label className="form-label">Max Uses</label>
                            <input
                                type="number"
                                className="form-input"
                                value={inviteForm.max_uses}
                                onChange={(e) => setInviteForm({ ...inviteForm, max_uses: parseInt(e.target.value) || 1 })}
                                min={1}
                                max={100}
                                style={{ width: '100px' }}
                            />
                        </div>
                        <div className="form-group" style={{ marginBottom: 0 }}>
                            <label className="form-label">Email to <span className="text-muted">(optional)</span></label>
                            <input
                                type="email"
                                className="form-input"
                                value={inviteForm.email}
                                onChange={(e) => setInviteForm({ ...inviteForm, email: e.target.value })}
                                placeholder="cousin@example.com"
                            />
                        </div>
                        <button type="submit" className="btn btn-primary" style={{ marginTop: '18px' }}>
                            {inviteForm.email ? 'Generate & Email' : 'Generate Code'}
                        </button>
                    </form>

                    {inviteMsg && (
                        <div className={`alert ${inviteMsg.ok ? 'alert-success' : 'alert-error'}`} role="status">{inviteMsg.text}</div>
                    )}

                    {invites.length > 0 && (
                        <div className="invites-list">
                            {invites.map(inv => (
                                <div key={inv.id} className="invite-row">
                                    <code className="invite-code">{inv.code}</code>
                                    <span className="text-sm text-muted">
                                        {inv.current_uses}/{inv.max_uses} used
                                    </span>
                                    <span className={`badge ${inv.current_uses >= inv.max_uses ? 'badge-private' : 'badge-public'}`}>
                                        {inv.current_uses >= inv.max_uses ? 'Exhausted' : 'Active'}
                                    </span>
                                    <button onClick={() => deleteInvite(inv.id)} className="btn btn-ghost btn-sm" style={{ color: '#ef4444' }}>
                                        Revoke
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                {/* Members */}
                <section className="settings-section card fade-in">
                    <h2>Members ({members.length})</h2>
                    <div className="members-list">
                        {members.map(member => (
                            <div key={member.id} className="member-row">
                                <div className="member-info">
                                    <div className="member-avatar">
                                        {member.avatar_url ? (
                                            <img src={member.avatar_url} alt="" />
                                        ) : (
                                            <span>{member.name?.charAt(0)?.toUpperCase()}</span>
                                        )}
                                    </div>
                                    <div>
                                        <div className="member-name">{member.name}</div>
                                        <div className="text-sm text-muted">{member.email}</div>
                                    </div>
                                </div>
                                <div className="flex gap-sm items-center">
                                    {member.role === 'owner' ? (
                                        <span className="badge badge-role-owner">Owner</span>
                                    ) : (
                                        <>
                                            <select
                                                className="form-select"
                                                value={member.role}
                                                onChange={(e) => changeMemberRole(member.id, e.target.value)}
                                                style={{ width: '100px', padding: '4px 8px', fontSize: 'var(--font-size-xs)' }}
                                            >
                                                <option value="admin">Admin</option>
                                                <option value="member">Member</option>
                                            </select>
                                            <button onClick={() => removeMember(member.id)} className="btn btn-ghost btn-sm" style={{ color: '#ef4444' }}>
                                                Remove
                                            </button>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Export / Import */}
                <section className="settings-section card fade-in">
                    <h2>Download &amp; Restore</h2>
                    <p className="text-muted text-sm mb-md">
                        Download every event and photo as one zip. Load a zip back to restore it here;
                        events already loaded from the same zip are updated, not copied.
                    </p>
                    <div className="flex gap-md items-center flex-wrap">
                        <a href={`/api/groups/${slug}/export`} className="btn btn-primary" download>Download Timeline</a>
                        <label className="btn btn-secondary">
                            {importing ? 'Loading…' : 'Load a Timeline Zip'}
                            <input type="file" accept=".zip,application/zip" onChange={importZip} disabled={importing} hidden />
                        </label>
                    </div>
                    {importMsg && (
                        <div className={`alert ${importMsg.ok ? 'alert-success' : 'alert-error'} mt-md`} role="status">{importMsg.text}</div>
                    )}
                </section>

                {/* Danger Zone */}
                <section className="settings-section card fade-in" style={{ borderColor: 'rgba(239, 68, 68, 0.3)' }}>
                    <h2 style={{ color: '#ef4444' }}>Danger Zone</h2>
                    <p className="text-muted text-sm mb-md">Permanently delete this group and all its events.</p>
                    <button onClick={deleteGroup} className="btn btn-danger">Delete Group</button>
                </section>
            </div>
        </div>
    );
}
