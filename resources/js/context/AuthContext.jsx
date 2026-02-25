import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '../lib/api';

const AuthContext = createContext(null);

async function initCsrf() {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchUser = useCallback(async () => {
        try {
            const data = await api.get('/auth/me');
            setUser(data.user);
        } catch {
            setUser(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        // Initialise CSRF cookie, then check if already logged in
        initCsrf().then(fetchUser);

        const onLogout = () => setUser(null);
        window.addEventListener('auth:logout', onLogout);
        return () => window.removeEventListener('auth:logout', onLogout);
    }, [fetchUser]);

    const login = async (email, password) => {
        const data = await api.post('/auth/login', { email, password });
        // If MFA is required, the backend returns { mfa_required: true } without logging in
        if (!data.mfa_required) {
            setUser(data.user);
        }
        return data;
    };

    const verifyMfa = async (code) => {
        const data = await api.post('/auth/mfa/verify', { code });
        setUser(data.user);
        return data;
    };

    const register = async (name, email, password, passwordConfirmation, referralCode, inviteCode) => {
        const data = await api.post('/auth/register', {
            name,
            email,
            password,
            password_confirmation: passwordConfirmation,
            referral_code: referralCode || undefined,
            invite_code: inviteCode || undefined,
        });
        setUser(data.user);
        return data;
    };

    const logout = async () => {
        try {
            await api.post('/auth/logout');
        } catch {
            // ignore network errors
        }
        setUser(null);
        // Re-initialise CSRF so the next login attempt works
        await initCsrf();
    };

    const updateProfile = async (profileData) => {
        const data = await api.put('/auth/profile', profileData);
        setUser(data.user);
        return data;
    };

    const setActiveGroup = async (groupId) => {
        const data = await api.put('/auth/active-group', { group_id: groupId });
        setUser(data.user);
        return data;
    };

    const value = {
        user,
        loading,
        login,
        verifyMfa,
        register,
        logout,
        updateProfile,
        setActiveGroup,
        refreshUser: fetchUser,
        isAuthenticated: !!user,
        isSuperAdmin: user?.platform_role === 'super_admin',
    };

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}
