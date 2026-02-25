import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useState } from 'react';
import './Navbar.css';

export default function Navbar() {
    const { user, isAuthenticated, isSuperAdmin, logout } = useAuth();
    const navigate = useNavigate();
    const [menuOpen, setMenuOpen] = useState(false);

    const handleLogout = async () => {
        await logout();
        navigate('/');
    };

    return (
        <nav className="navbar">
            <div className="navbar-inner container">
                <Link to={isAuthenticated ? '/dashboard' : '/'} className="navbar-brand">
                    <span className="navbar-logo">⏳</span>
                    <span className="navbar-title">Timeline</span>
                </Link>

                <button className="navbar-toggle" onClick={() => setMenuOpen(!menuOpen)}>
                    <span className={`hamburger ${menuOpen ? 'open' : ''}`}>
                        <span /><span /><span />
                    </span>
                </button>

                <div className={`navbar-menu ${menuOpen ? 'open' : ''}`}>
                    {isAuthenticated ? (
                        <>
                            <Link to="/dashboard" className="navbar-link" onClick={() => setMenuOpen(false)}>
                                Dashboard
                            </Link>
                            <Link to="/g/demo" className="navbar-link" onClick={() => setMenuOpen(false)}>
                                Demo
                            </Link>
                            {isSuperAdmin && (
                                <Link to="/admin" className="navbar-link navbar-link-admin" onClick={() => setMenuOpen(false)}>
                                    Admin
                                </Link>
                            )}
                            <div className="navbar-divider" />
                            <Link to="/profile" className="navbar-link" onClick={() => setMenuOpen(false)}>
                                <span className="navbar-avatar">
                                    {user?.avatar_url ? (
                                        <img src={user.avatar_url} alt="" />
                                    ) : (
                                        <span className="navbar-avatar-placeholder">
                                            {user?.name?.charAt(0)?.toUpperCase()}
                                        </span>
                                    )}
                                </span>
                                {user?.name}
                            </Link>
                            <button className="btn btn-ghost btn-sm" onClick={handleLogout}>Logout</button>
                        </>
                    ) : (
                        <>
                            <Link to="/login" className="navbar-link" onClick={() => setMenuOpen(false)}>Login</Link>
                            <Link to="/register" className="btn btn-primary btn-sm" onClick={() => setMenuOpen(false)}>Sign Up</Link>
                        </>
                    )}
                </div>
            </div>
        </nav>
    );
}
