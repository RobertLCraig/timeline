import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '../lib/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchUser = useCallback(async () => {
        const token = localStorage.getItem('auth_token');
        if (!token) {
            setLoading(false);
            return;
        }
        try {
            const data = await api.get('/auth/me');
            setUser(data.user);
        } catch {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUser();
    }, [fetchUser]);

    const login = async (email, password) => {
        const data = await api.post('/auth/login', { email, password });
        localStorage.setItem('auth_token', data.token);
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
        localStorage.setItem('auth_token', data.token);
        setUser(data.user);
        return data;
    };

    const logout = async () => {
        try {
            await api.post('/auth/logout');
        } catch {
            // ignore
        }
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        setUser(null);
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
