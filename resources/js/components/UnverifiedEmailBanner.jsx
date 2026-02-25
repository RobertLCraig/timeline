import { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import api from '../lib/api';

/**
 * Shows a dismissible banner when the authenticated user's email is not yet verified.
 * Includes a "Resend verification email" button.
 */
export default function UnverifiedEmailBanner() {
    const { user } = useAuth();
    const [sent, setSent] = useState(false);
    const [dismissed, setDismissed] = useState(false);

    if (!user || user.email_verified_at || dismissed) return null;

    const handleResend = async () => {
        try {
            await api.post('/auth/email/resend');
            setSent(true);
        } catch {
            // silently ignore — user can try again
        }
    };

    return (
        <div style={{
            background: 'var(--color-warning, #f59e0b)',
            color: '#1a1a1a',
            padding: 'var(--space-sm) var(--space-lg)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: 'var(--space-md)',
            flexWrap: 'wrap',
            fontSize: 'var(--font-size-sm)',
            fontWeight: 500,
        }}>
            <span>
                Please verify your email address. Check your inbox for a verification link.
            </span>
            {sent ? (
                <span style={{ opacity: 0.8 }}>Email sent!</span>
            ) : (
                <button
                    onClick={handleResend}
                    style={{
                        background: 'rgba(0,0,0,0.15)',
                        border: 'none',
                        borderRadius: 4,
                        padding: '2px 10px',
                        cursor: 'pointer',
                        fontWeight: 600,
                        color: '#1a1a1a',
                        fontSize: 'inherit',
                    }}
                >
                    Resend email
                </button>
            )}
            <button
                onClick={() => setDismissed(true)}
                style={{ background: 'none', border: 'none', cursor: 'pointer', opacity: 0.6, padding: '0 4px', fontSize: 16 }}
                aria-label="Dismiss"
            >
                ×
            </button>
        </div>
    );
}
