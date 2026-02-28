import { useState } from 'react';
import { Link } from 'react-router-dom';

const STORAGE_KEY = 'cookie_consent';

/**
 * GDPR cookie consent banner.
 * Shown on first visit until the user accepts or declines.
 * Consent choice is persisted in localStorage so the banner doesn't reappear.
 */
export default function CookieBanner() {
    const [visible, setVisible] = useState(() => {
        try {
            return !localStorage.getItem(STORAGE_KEY);
        } catch {
            return false;
        }
    });

    if (!visible) return null;

    const accept = () => {
        try { localStorage.setItem(STORAGE_KEY, 'accepted'); } catch { /* */ }
        setVisible(false);
    };

    const decline = () => {
        try { localStorage.setItem(STORAGE_KEY, 'declined'); } catch { /* */ }
        setVisible(false);
    };

    return (
        <div style={{
            position: 'fixed',
            bottom: 0,
            left: 0,
            right: 0,
            zIndex: 9999,
            background: 'var(--bg-card)',
            borderTop: '1px solid var(--border-color)',
            padding: 'var(--space-lg) var(--space-xl)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: 'var(--space-lg)',
            flexWrap: 'wrap',
            boxShadow: '0 -4px 24px rgba(0,0,0,0.15)',
        }}>
            <p style={{ margin: 0, fontSize: 'var(--font-size-sm)', color: 'var(--text-secondary)', maxWidth: 600 }}>
                We use essential cookies to keep you logged in and to make the site work.
                By clicking <strong>Accept</strong> you consent to their use.{' '}
                <Link to="/privacy" style={{ color: 'var(--color-primary)' }}>Privacy Policy</Link>
                {' · '}
                <Link to="/terms" style={{ color: 'var(--color-primary)' }}>Terms of Service</Link>
            </p>
            <div style={{ display: 'flex', gap: 'var(--space-sm)', flexShrink: 0 }}>
                <button onClick={decline} className="btn btn-ghost btn-sm">
                    Decline
                </button>
                <button onClick={accept} className="btn btn-primary btn-sm">
                    Accept
                </button>
            </div>
        </div>
    );
}
