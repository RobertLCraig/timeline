import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './Landing.css';

export default function Landing() {
    const { isAuthenticated } = useAuth();

    return (
        <div className="landing">
            <section className="hero">
                <div className="container">
                    <div className="hero-content fade-in">
                        <h1 className="hero-title">
                            Your Family Story,<br />
                            <span className="hero-highlight">Beautifully Told</span>
                        </h1>
                        <p className="hero-subtitle">
                            Capture and share life's greatest moments with the people who matter most.
                            Births, milestones, adventures — all in one beautiful timeline.
                        </p>
                        <div className="hero-actions">
                            {isAuthenticated ? (
                                <Link to="/dashboard" className="btn btn-primary btn-lg">
                                    Go to Dashboard →
                                </Link>
                            ) : (
                                <>
                                    <Link to="/register" className="btn btn-primary btn-lg">
                                        Get Started Free
                                    </Link>
                                    <Link to="/login" className="btn btn-secondary btn-lg">
                                        Sign In
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                    <div className="hero-visual fade-in">
                        <div className="hero-timeline-preview">
                            <div className="preview-event" style={{ '--delay': '0.1s', '--accent': 'var(--cat-birth)' }}>
                                <span className="preview-icon">👶</span>
                                <span className="preview-text">Baby Emma Born</span>
                                <span className="preview-date">2024</span>
                            </div>
                            <div className="preview-connector" />
                            <div className="preview-event" style={{ '--delay': '0.3s', '--accent': 'var(--cat-move)' }}>
                                <span className="preview-icon">🏠</span>
                                <span className="preview-text">New Home in Seattle</span>
                                <span className="preview-date">2023</span>
                            </div>
                            <div className="preview-connector" />
                            <div className="preview-event" style={{ '--delay': '0.5s', '--accent': 'var(--cat-graduation)' }}>
                                <span className="preview-icon">🎓</span>
                                <span className="preview-text">MBA Graduation</span>
                                <span className="preview-date">2022</span>
                            </div>
                            <div className="preview-connector" />
                            <div className="preview-event" style={{ '--delay': '0.7s', '--accent': 'var(--cat-wedding)' }}>
                                <span className="preview-icon">💒</span>
                                <span className="preview-text">Sarah & James Wedding</span>
                                <span className="preview-date">2020</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="features">
                <div className="container">
                    <h2 className="features-title">Everything Your Family Needs</h2>
                    <div className="features-grid">
                        <div className="feature-card card">
                            <div className="feature-icon">👨‍👩‍👧‍👦</div>
                            <h3>Family Groups</h3>
                            <p>Create private groups for your family, friends, or any circle. Share moments with the people you choose.</p>
                        </div>
                        <div className="feature-card card">
                            <div className="feature-icon">🔒</div>
                            <h3>Privacy Controls</h3>
                            <p>Control who sees what. Mark events as public, members-only, or private — you're always in control.</p>
                        </div>
                        <div className="feature-card card">
                            <div className="feature-icon">📸</div>
                            <h3>Photos & Albums</h3>
                            <p>Attach photos or link to your Google Photos or iCloud albums. Keep memories connected.</p>
                        </div>
                        <div className="feature-card card">
                            <div className="feature-icon">📅</div>
                            <h3>Beautiful Timeline</h3>
                            <p>See your family's story unfold chronologically with a stunning, interactive timeline view.</p>
                        </div>
                    </div>
                </div>
            </section>

            <footer className="landing-footer">
                <div className="container text-center">
                    <p className="text-muted text-sm">
                        © {new Date().getFullYear()} Family Timeline. Made with ❤️ for families everywhere.
                    </p>
                    <p className="text-muted text-sm" style={{ marginTop: 'var(--space-sm)' }}>
                        <Link to="/terms" style={{ color: 'var(--text-muted)', textDecoration: 'underline' }}>Terms of Service</Link>
                        {' · '}
                        <Link to="/privacy" style={{ color: 'var(--text-muted)', textDecoration: 'underline' }}>Privacy Policy</Link>
                    </p>
                </div>
            </footer>
        </div>
    );
}
