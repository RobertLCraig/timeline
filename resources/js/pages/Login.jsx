import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './AuthPages.css';

export default function Login() {
    const { login, verifyMfa } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    // MFA step
    const [mfaRequired, setMfaRequired] = useState(false);
    const [mfaCode, setMfaCode] = useState('');

    const doRedirect = (data) => {
        const groups = data.user?.groups || [];
        if (groups.length > 0) {
            const activeGroup = groups.find(g => g.id === data.user?.active_group_id) || groups[0];
            navigate(`/g/${activeGroup.slug}`, { replace: true });
        } else {
            navigate('/dashboard', { replace: true });
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const data = await login(email, password);
            if (data.mfa_required) {
                setMfaRequired(true);
            } else {
                doRedirect(data);
            }
        } catch (err) {
            setError(err.data?.message || err.message || 'Login failed');
        } finally {
            setLoading(false);
        }
    };

    const handleMfaSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const data = await verifyMfa(mfaCode);
            doRedirect(data);
        } catch (err) {
            setError(err.data?.message || err.message || 'Invalid code');
            setMfaCode('');
        } finally {
            setLoading(false);
        }
    };

    if (mfaRequired) {
        return (
            <div className="auth-page page">
                <div className="container container-sm">
                    <div className="auth-card card fade-in">
                        <div className="auth-header">
                            <h1 className="auth-title">Two-Factor Auth</h1>
                            <p className="auth-subtitle">Enter the 6-digit code from your authenticator app</p>
                        </div>

                        {error && <div className="alert alert-error">{error}</div>}

                        <form onSubmit={handleMfaSubmit}>
                            <div className="form-group">
                                <label className="form-label">Authentication Code</label>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    className="form-input"
                                    value={mfaCode}
                                    onChange={(e) => setMfaCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                    placeholder="000000"
                                    autoFocus
                                    required
                                    maxLength={6}
                                    style={{ letterSpacing: '0.3em', fontSize: '1.5rem', textAlign: 'center' }}
                                />
                            </div>
                            <button type="submit" className="btn btn-primary w-full btn-lg" disabled={loading || mfaCode.length !== 6}>
                                {loading ? 'Verifying...' : 'Verify'}
                            </button>
                        </form>

                        <p className="auth-footer">
                            <button
                                className="btn btn-ghost btn-sm"
                                onClick={() => { setMfaRequired(false); setMfaCode(''); setError(''); }}
                            >
                                Back to login
                            </button>
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="auth-page page">
            <div className="container container-sm">
                <div className="auth-card card fade-in">
                    <div className="auth-header">
                        <h1 className="auth-title">Welcome Back</h1>
                        <p className="auth-subtitle">Sign in to your account</p>
                    </div>

                    {error && <div className="alert alert-error">{error}</div>}

                    <form onSubmit={handleSubmit}>
                        <div className="form-group">
                            <label className="form-label">Email</label>
                            <input
                                type="email"
                                className="form-input"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="you@example.com"
                                required
                            />
                        </div>
                        <div className="form-group">
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                                <label className="form-label">Password</label>
                                <Link to="/forgot-password" className="text-sm text-muted">Forgot password?</Link>
                            </div>
                            <input
                                type="password"
                                className="form-input"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="••••••••"
                                required
                            />
                        </div>
                        <button type="submit" className="btn btn-primary w-full btn-lg" disabled={loading}>
                            {loading ? 'Signing in...' : 'Sign In'}
                        </button>
                    </form>

                    <div style={{ margin: 'var(--space-lg) 0', display: 'flex', alignItems: 'center', gap: 'var(--space-md)' }}>
                        <hr style={{ flex: 1, borderColor: 'var(--border-color)' }} />
                        <span className="text-muted text-sm">or</span>
                        <hr style={{ flex: 1, borderColor: 'var(--border-color)' }} />
                    </div>
                    <a
                        href="/api/auth/oauth/google/redirect"
                        className="btn btn-secondary w-full btn-lg"
                        style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 'var(--space-sm)', textDecoration: 'none' }}
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        Continue with Google
                    </a>

                    <p className="auth-footer">
                        Don't have an account? <Link to="/register">Sign up</Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
