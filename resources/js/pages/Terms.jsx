import { Link } from 'react-router-dom';

export default function Terms() {
    return (
        <div className="page">
            <div className="container" style={{ maxWidth: '760px', paddingTop: '40px', paddingBottom: '80px' }}>
                <div className="fade-in">
                    <Link to="/" className="text-sm text-muted" style={{ display: 'inline-block', marginBottom: 'var(--space-lg)' }}>
                        ← Back
                    </Link>

                    <h1 className="page-title">Terms of Service</h1>
                    <p className="text-muted text-sm" style={{ marginBottom: 'var(--space-xl)' }}>
                        Last updated: {new Date().getFullYear()}
                    </p>

                    <div className="card" style={{ padding: 'var(--space-xl)', display: 'flex', flexDirection: 'column', gap: 'var(--space-lg)' }}>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>1. Acceptance</h2>
                            <p className="text-sm text-muted">
                                By creating an account or using Family Timeline, you agree to these Terms of Service.
                                If you do not agree, do not use the service.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>2. Permitted Use</h2>
                            <p className="text-sm text-muted">
                                Family Timeline is a private platform for recording and sharing personal and family memories.
                                You may use it to store text, images, and links related to life events.
                                You must not use the platform for any commercial purpose without explicit written permission.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>3. Prohibited Content</h2>
                            <p className="text-sm text-muted" style={{ marginBottom: 'var(--space-sm)' }}>
                                The following content is <strong>strictly prohibited</strong> and will result in immediate account termination
                                and may be reported to relevant authorities:
                            </p>
                            <ul className="text-sm text-muted" style={{ paddingLeft: '1.5rem', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                <li>Child sexual abuse material (CSAM) or any sexual content involving minors</li>
                                <li>Content that promotes, glorifies, or facilitates violence, terrorism, or hate crimes</li>
                                <li>Content that infringes third-party intellectual property rights</li>
                                <li>Malware, spam, or any content intended to harm other users or systems</li>
                                <li>Any content that violates applicable law</li>
                            </ul>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>4. Your Content</h2>
                            <p className="text-sm text-muted">
                                You retain ownership of the content you upload. By uploading content, you grant Family Timeline
                                a limited licence to store and display it to the members you have authorised. We do not sell,
                                share, or use your content for advertising.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>5. Account Responsibility</h2>
                            <p className="text-sm text-muted">
                                You are responsible for maintaining the security of your account credentials and for all
                                activity that occurs under your account. You must notify us immediately of any unauthorised access.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>6. Termination</h2>
                            <p className="text-sm text-muted">
                                We reserve the right to suspend or terminate accounts that violate these terms, without notice,
                                at our sole discretion. You may delete your account at any time from your Profile page.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>7. Disclaimers</h2>
                            <p className="text-sm text-muted">
                                Family Timeline is provided "as is" without warranties of any kind. We are not liable for
                                loss of data, service interruptions, or any indirect damages arising from use of the platform.
                            </p>
                        </section>

                        <section>
                            <h2 style={{ fontSize: '1.1rem', marginBottom: 'var(--space-sm)' }}>8. Changes to Terms</h2>
                            <p className="text-sm text-muted">
                                We may update these terms from time to time. Continued use of the service after changes
                                are posted constitutes acceptance of the new terms.
                            </p>
                        </section>

                        <section>
                            <p className="text-sm text-muted">
                                Questions? See our <Link to="/privacy">Privacy Policy</Link> or contact the administrator.
                            </p>
                        </section>

                    </div>
                </div>
            </div>
        </div>
    );
}
