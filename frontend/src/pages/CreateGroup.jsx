import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../lib/api';

export default function CreateGroup() {
    const navigate = useNavigate();
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const data = await api.post('/groups', { name, description });
            navigate(`/g/${data.group.slug}`);
        } catch (err) {
            setError(err.data?.message || 'Failed to create group');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="page">
            <div className="container container-md">
                <div className="card fade-in" style={{ padding: 'var(--space-2xl)' }}>
                    <h1 className="page-title" style={{ marginBottom: 'var(--space-sm)' }}>Create a Group</h1>
                    <p className="text-muted mb-lg">Create a group for your family, friends, or any circle to share life events.</p>

                    {error && <div className="alert alert-error">{error}</div>}

                    <form onSubmit={handleSubmit}>
                        <div className="form-group">
                            <label className="form-label">Group Name *</label>
                            <input
                                type="text"
                                className="form-input"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="e.g. The Smith Family"
                                required
                                maxLength={255}
                            />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Description</label>
                            <textarea
                                className="form-textarea"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                placeholder="What's this group about?"
                                maxLength={1000}
                            />
                        </div>
                        <div className="flex gap-md">
                            <button type="submit" className="btn btn-primary btn-lg" disabled={loading}>
                                {loading ? 'Creating...' : 'Create Group'}
                            </button>
                            <button type="button" className="btn btn-secondary btn-lg" onClick={() => navigate(-1)}>
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
