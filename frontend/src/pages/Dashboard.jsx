import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import './Dashboard.css';

export default function Dashboard() {
    const { user } = useAuth();
    const [groups, setGroups] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/groups').then(data => {
            setGroups(data.groups);
        }).catch(() => { }).finally(() => setLoading(false));
    }, []);

    if (loading) {
        return <div className="loading-screen"><div className="spinner" /></div>;
    }

    return (
        <div className="page">
            <div className="container">
                <div className="page-header flex items-center justify-between flex-wrap gap-md">
                    <div>
                        <h1 className="page-title">Welcome, {user?.name?.split(' ')[0]} 👋</h1>
                        <p className="page-subtitle">Your groups and recent activity</p>
                    </div>
                    <Link to="/groups/new" className="btn btn-primary">
                        + Create Group
                    </Link>
                </div>

                {groups.length === 0 ? (
                    <div className="empty-state fade-in">
                        <div className="empty-state-icon">🏠</div>
                        <div className="empty-state-text">No groups yet</div>
                        <p className="text-muted mb-lg">Create a group for your family or friends, then invite them to join.</p>
                        <Link to="/groups/new" className="btn btn-primary btn-lg">
                            Create Your First Group
                        </Link>
                    </div>
                ) : (
                    <div className="groups-grid fade-in">
                        {groups.map(group => (
                            <Link to={`/g/${group.slug}`} key={group.id} className="group-card card">
                                <div className="group-card-header">
                                    <div className="group-card-avatar" style={{
                                        background: `linear-gradient(135deg, hsl(${group.id * 45}, 70%, 50%), hsl(${group.id * 45 + 30}, 80%, 60%))`
                                    }}>
                                        {group.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div>
                                        <h3 className="group-card-name">{group.name}</h3>
                                        <span className="badge badge-role-{group.pivot?.role || 'member'}">
                                            {group.pivot?.role || 'member'}
                                        </span>
                                    </div>
                                </div>
                                {group.description && (
                                    <p className="group-card-desc">{group.description}</p>
                                )}
                                <div className="group-card-stats">
                                    <span>{group.members_count || 0} member{group.members_count !== 1 ? 's' : ''}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
