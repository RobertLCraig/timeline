import { Link } from 'react-router-dom';

export default function Privacy() {
    return (
        <div className="page">
            <div className="container" style={{ maxWidth: '760px', paddingTop: '40px', paddingBottom: '80px' }}>
                <div className="fade-in">
                    <Link to="/" className="text-sm text-muted" style={{ display: 'inline-block', marginBottom: 'var(--space-lg)' }}>
                        ← Back
                    </Link>

                    <h1 className="page-title">Privacy Policy</h1>
                    <p className="text-muted text-sm" style={{ marginBottom: 'var(--space-xl)' }}>
                        Last updated: {new Date().getFullYear()}
                    </p>

                    <div className="card" style={{ padding: 'var(--space-xl)', display: 'flex', flexDirection: 'column', gap: 'var(--space-lg)' }}>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>1. What We Collect</h2>
                            <p className="text-sm text-muted" style={{ marginBottom: 'var(--space-sm)' }}>We collect only what is necessary to operate the service:</p>
                            <ul className="text-sm text-muted" style={{ paddingLeft: '1.5rem', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                <li><strong>Account data</strong>: name, email address, hashed password, optional date of birth</li>
                                <li><strong>Event data</strong>: titles, descriptions, dates, images, and album links you add to timelines</li>
                                <li><strong>Group membership</strong>: which groups you belong to and your role in each</li>
                                <li><strong>Technical data</strong>: IP address (for security rate limiting and audit logs), browser type</li>
                            </ul>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>2. How We Use Your Data</h2>
                            <ul className="text-sm text-muted" style={{ paddingLeft: '1.5rem', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                <li>To provide the family timeline service</li>
                                <li>To authenticate you and protect your account</li>
                                <li>To enforce visibility permissions you configure</li>
                                <li>To detect and prevent abuse (rate limiting, audit logging)</li>
                            </ul>
                            <p className="text-sm text-muted" style={{ marginTop: 'var(--space-sm)' }}>
                                We do <strong>not</strong> sell your data, use it for advertising, or share it with third parties
                                except where required by law.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>3. Data Sharing</h2>
                            <p className="text-sm text-muted">
                                Your data is visible only to group members you explicitly share it with, according to the
                                visibility settings you choose. Administrators can view all content within their group.
                                Super-admins can access all platform data for moderation purposes.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>4. Data Retention</h2>
                            <p className="text-sm text-muted">
                                We retain your data for as long as your account is active. Audit logs are retained for
                                12 months. If you delete your account, your personal data is permanently removed within
                                30 days. Events you created are anonymised (creator attribution removed) rather than deleted,
                                to preserve family history for other group members.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>5. Your Rights (GDPR)</h2>
                            <p className="text-sm text-muted" style={{ marginBottom: 'var(--space-sm)' }}>
                                If you are located in the European Economic Area, you have the right to:
                            </p>
                            <ul className="text-sm text-muted" style={{ paddingLeft: '1.5rem', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                <li><strong>Access</strong> your data — download a copy from your Profile page</li>
                                <li><strong>Rectify</strong> your data — update your name and profile details at any time</li>
                                <li><strong>Erase</strong> your data — delete your account from your Profile page</li>
                                <li><strong>Restrict processing</strong> — contact the administrator to discuss restrictions</li>
                                <li><strong>Data portability</strong> — export your data as JSON from your Profile page</li>
                            </ul>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>6. Cookies</h2>
                            <p className="text-sm text-muted">
                                We use session cookies strictly necessary for authentication. We do not use tracking
                                cookies, analytics cookies, or advertising cookies. No third-party cookies are set.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>7. Security</h2>
                            <p className="text-sm text-muted">
                                Passwords are stored as bcrypt hashes (never in plain text). Authentication tokens are
                                stored in HttpOnly cookies inaccessible to JavaScript. Rate limiting and account lockout
                                protect against brute-force attacks. All admin actions are audit-logged.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>8. Contact</h2>
                            <p className="text-sm text-muted">
                                For privacy questions or to exercise your rights, contact the platform administrator.
                                See also our <Link to="/terms">Terms of Service</Link>.
                            </p>
                        </section>

                    </div>
                </div>
            </div>
        </div>
    );
}
