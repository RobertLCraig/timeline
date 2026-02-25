import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';

export default function ForgotPassword() {
    const [email, setEmail]     = useState('');
    const [submitted, setSubmitted] = useState(false);
    const [error, setError]     = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            await api.post('/auth/forgot-password', { email });
            setSubmitted(true);
        } catch (err) {
            setError(err.data?.message || 'Something went wrong. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="page">
            <div className="container" style={{ maxWidth: '420px', paddingTop: '80px' }}>
                <div className="card fade-in" style={{ padding: 'var(--space-xl)' }}>
                    <h1 className="page-title" style={{ marginBottom: 'var(--space-xs)' }}>Reset Password</h1>

                    {submitted ? (
                        <div>
                            <div className="alert alert-success" style={{ marginBottom: 'var(--space-md)' }}>
                                If an account exists for that email, a password reset link has been sent. Check your inbox.
                            </div>
                            <Link to="/login" className="btn btn-secondary" style={{ width: '100%' }}>
                                Back to Login
                            </Link>
                        </div>
                    ) : (
                        <>
                            <p className="text-muted text-sm" style={{ marginBottom: 'var(--space-lg)' }}>
                                Enter your email address and we'll send you a link to reset your password.
                            </p>

                            {error && <div className="alert alert-error">{error}</div>}

                            <form onSubmit={handleSubmit} className="flex flex-col gap-md">
                                <div className="form-group">
                                    <label className="form-label">Email address</label>
                                    <input
                                        type="email"
                                        className="form-input"
                                        value={email}
                                        onChange={e => setEmail(e.target.value)}
                                        required
                                        autoFocus
                                    />
                                </div>

                                <button type="submit" className="btn btn-primary" disabled={loading}>
                                    {loading ? 'Sending...' : 'Send Reset Link'}
                                </button>

                                <Link to="/login" className="text-center text-sm text-muted">
                                    Back to Login
                                </Link>
                            </form>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
