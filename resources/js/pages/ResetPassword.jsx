import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import api from '../lib/api';

export default function ResetPassword() {
    const [searchParams]        = useSearchParams();
    const navigate              = useNavigate();
    const [password, setPassword]       = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [error, setError]     = useState('');
    const [fieldErrors, setFieldErrors] = useState({});
    const [loading, setLoading] = useState(false);

    const token = searchParams.get('token') || '';
    const email = searchParams.get('email') || '';

    useEffect(() => {
        if (!token || !email) {
            setError('Invalid or missing reset link. Please request a new one.');
        }
    }, [token, email]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setFieldErrors({});
        setLoading(true);
        try {
            await api.post('/auth/reset-password', {
                token,
                email,
                password,
                password_confirmation: confirmation,
            });
            navigate('/login', { state: { message: 'Password reset successfully. Please log in.' } });
        } catch (err) {
            if (err.data?.errors) {
                setFieldErrors(err.data.errors);
            } else {
                setError(err.data?.message || 'Failed to reset password. The link may have expired.');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="page">
            <div className="container" style={{ maxWidth: '420px', paddingTop: '80px' }}>
                <div className="card fade-in" style={{ padding: 'var(--space-xl)' }}>
                    <h1 className="page-title" style={{ marginBottom: 'var(--space-xs)' }}>Set New Password</h1>

                    {error && <div className="alert alert-error">{error}</div>}

                    <form onSubmit={handleSubmit} className="flex flex-col gap-md">
                        <div className="form-group">
                            <label className="form-label">New password</label>
                            <input
                                type="password"
                                className="form-input"
                                value={password}
                                onChange={e => setPassword(e.target.value)}
                                minLength={8}
                                required
                                autoFocus
                            />
                            {fieldErrors.password && (
                                <span className="form-error">{fieldErrors.password[0]}</span>
                            )}
                        </div>

                        <div className="form-group">
                            <label className="form-label">Confirm new password</label>
                            <input
                                type="password"
                                className="form-input"
                                value={confirmation}
                                onChange={e => setConfirmation(e.target.value)}
                                required
                            />
                        </div>

                        <button type="submit" className="btn btn-primary" disabled={loading || !token}>
                            {loading ? 'Resetting...' : 'Reset Password'}
                        </button>

                        <Link to="/forgot-password" className="text-center text-sm text-muted">
                            Request a new reset link
                        </Link>
                    </form>
                </div>
            </div>
        </div>
    );
}
