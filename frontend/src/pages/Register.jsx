import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './AuthPages.css';

export default function Register() {
    const { register } = useAuth();
    const navigate = useNavigate();
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        referral_code: '',
    });
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});
    const [loading, setLoading] = useState(false);

    const handleChange = (e) => {
        setForm({ ...form, [e.target.name]: e.target.value });
        setFieldErrors({ ...fieldErrors, [e.target.name]: null });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setFieldErrors({});
        setLoading(true);
        try {
            await register(form.name, form.email, form.password, form.password_confirmation, form.referral_code);
            navigate('/dashboard');
        } catch (err) {
            if (err.data?.errors) {
                setFieldErrors(err.data.errors);
            }
            setError(err.data?.message || err.message || 'Registration failed');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="auth-page page">
            <div className="container container-sm">
                <div className="auth-card card fade-in">
                    <div className="auth-header">
                        <h1 className="auth-title">Create Account</h1>
                        <p className="auth-subtitle">Join your family's timeline</p>
                    </div>

                    {error && <div className="alert alert-error">{error}</div>}

                    <form onSubmit={handleSubmit}>
                        <div className="form-group">
                            <label className="form-label">Referral Code *</label>
                            <input
                                type="text"
                                name="referral_code"
                                className="form-input"
                                value={form.referral_code}
                                onChange={handleChange}
                                placeholder="Enter your referral code"
                                required
                            />
                            {fieldErrors.referral_code && (
                                <span className="form-error">{fieldErrors.referral_code[0]}</span>
                            )}
                            <span className="form-hint">You need a referral code from an admin to register.</span>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Full Name</label>
                            <input
                                type="text"
                                name="name"
                                className="form-input"
                                value={form.name}
                                onChange={handleChange}
                                placeholder="Your full name"
                                required
                            />
                            {fieldErrors.name && <span className="form-error">{fieldErrors.name[0]}</span>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Email</label>
                            <input
                                type="email"
                                name="email"
                                className="form-input"
                                value={form.email}
                                onChange={handleChange}
                                placeholder="you@example.com"
                                required
                            />
                            {fieldErrors.email && <span className="form-error">{fieldErrors.email[0]}</span>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Password</label>
                            <input
                                type="password"
                                name="password"
                                className="form-input"
                                value={form.password}
                                onChange={handleChange}
                                placeholder="Min 8 characters"
                                required
                                minLength={8}
                            />
                            {fieldErrors.password && <span className="form-error">{fieldErrors.password[0]}</span>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Confirm Password</label>
                            <input
                                type="password"
                                name="password_confirmation"
                                className="form-input"
                                value={form.password_confirmation}
                                onChange={handleChange}
                                placeholder="Confirm your password"
                                required
                            />
                        </div>
                        <button type="submit" className="btn btn-primary w-full btn-lg" disabled={loading}>
                            {loading ? 'Creating account...' : 'Create Account'}
                        </button>
                    </form>

                    <p className="auth-footer">
                        Already have an account? <Link to="/login">Sign in</Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
