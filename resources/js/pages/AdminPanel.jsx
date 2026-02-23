import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import './AdminPanel.css';

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
                <p className="page-subtitle mb-lg">Manage platform referral codes and users</p>

                <div className="admin-tabs">
                    <button
                        className={`admin-tab ${tab === 'codes' ? 'active' : ''}`}
                        onClick={() => setTab('codes')}
                    >
                        🎟 Referral Codes ({codes.length})
                    </button>
                    <button
                        className={`admin-tab ${tab === 'users' ? 'active' : ''}`}
                        onClick={() => setTab('users')}
                    >
                        👥 Users ({users.length})
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
                                                    <button
                                                        onClick={() => changeUserRole(u.id, 'super_admin')}
                                                        className="btn btn-ghost btn-sm"
                                                    >
                                                        Make Admin
                                                    </button>
                                                ) : (
                                                    <button
                                                        onClick={() => changeUserRole(u.id, 'user')}
                                                        className="btn btn-ghost btn-sm"
                                                    >
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
            </div>
        </div>
    );
}
