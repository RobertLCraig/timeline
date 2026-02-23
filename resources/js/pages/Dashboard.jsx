import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';
import './Dashboard.css';

export default function Dashboard() {
    const { user, refreshUser } = useAuth();
    const navigate = useNavigate();
    const [groups, setGroups] = useState(null); // null = not yet loaded
    const [loading, setLoading] = useState(true);
    const [joinCode, setJoinCode] = useState('');
    const [joinError, setJoinError] = useState('');
    const [joinLoading, setJoinLoading] = useState(false);

    useEffect(() => {
        api.get('/groups').then(data => {
            const userGroups = data.groups || [];
            setGroups(userGroups);

            if (userGroups.length > 0) {
                // Find active group or fall back to first
                const activeGroup = userGroups.find(g => g.id === user?.active_group_id) || userGroups[0];
                navigate(`/g/${activeGroup.slug}`, { replace: true });
            }
        }).catch(() => {
            setGroups([]);
        }).finally(() => setLoading(false));
    }, []);

    const handleJoin = async (e) => {
        e.preventDefault();
        setJoinError('');
        setJoinLoading(true);
        try {
            // We don't know the group slug yet, so use a different approach:
            // Try to join by posting to a general join endpoint — but our API requires slug.
            // Instead, redirect user: they need to get the group link from a member.
            // The join flow is: receive a link /g/{slug} -> enter code there.
            // For Dashboard join, we'll try a lookup endpoint or show helpful message.
            setJoinError('To join a group, open the group link shared with you, then enter your invite code there.');
        } catch (err) {
            setJoinError(err.data?.message || 'Failed to join group');
        } finally {
            setJoinLoading(false);
        }
    };

    if (loading) {
        return <div className="loading-screen"><div className="spinner" /></div>;
    }

    // If groups exist, we're redirecting — show nothing while that happens
    if (groups && groups.length > 0) {
        return <div className="loading-screen"><div className="spinner" /></div>;
    }

    // No groups: show join/create only
    return (
        <div className="page">
            <div className="container container-sm">
                <div className="dashboard-welcome fade-in">
                    <h1 className="page-title">Welcome, {user?.name?.split(' ')[0]}</h1>
                    <p className="page-subtitle">Get started by creating a group or joining one with an invite code.</p>
                </div>

                <div className="dashboard-actions fade-in">
                    <div className="card dashboard-action-card">
                        <div className="dashboard-action-icon">🏠</div>
                        <h2>Create a Group</h2>
                        <p className="text-muted text-sm">Start a new family or friends timeline.</p>
                        <Link to="/groups/new" className="btn btn-primary">
                            Create Group
                        </Link>
                    </div>

                    <div className="card dashboard-action-card">
                        <div className="dashboard-action-icon">🔗</div>
                        <h2>Join a Group</h2>
                        <p className="text-muted text-sm">
                            Open the invite link shared with you, or ask a group member for their group's URL and enter your invite code there.
                        </p>
                        {joinError && <div className="alert alert-error" style={{ marginTop: 'var(--space-sm)' }}>{joinError}</div>}
                    </div>
                </div>
            </div>
        </div>
    );
}
