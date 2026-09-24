'use client';

import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { api } from '@/lib/api';
import { useRouter } from 'next/navigation';

export interface User {
  id: number;
  name: string;
  username?: string;
  email: string;
  phone?: string;
  role: string;
  role_id?: number;
  role_name?: string;
  reg_no?: string;
  national_id?: string;
  gender?: string;
  dob?: string;
  occupation?: string;
  address?: string;
  next_of_kin_name?: string;
  next_of_kin_phone?: string;
  status?: string;
  kyc_status?: string;
  profile_pic_url?: string | null;
  user_type: 'admin' | 'member';
}

export interface Balances {
  wallet: number;
  savings: number;
  shares: number;
  loans: number;
  net_worth: number;
}

export interface TopbarData {
  unread_notifications: number;
  unread_messages: number;
  recent_notifications: any[];
  recent_messages: any[];
}

interface AuthContextType {
  user: User | null;
  permissions: string[];
  balances: Balances | null;
  topbar: TopbarData | null;
  loading: boolean;
  isAuthenticated: boolean;
  isAdmin: boolean;
  isMember: boolean;
  can: (permissionSlug: string) => boolean;
  login: (credentials: { identifier?: string; email?: string; password?: string; user_type?: string }) => Promise<any>;
  register: (data: any) => Promise<any>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [permissions, setPermissions] = useState<string[]>([]);
  const [balances, setBalances] = useState<Balances | null>(null);
  const [topbar, setTopbar] = useState<TopbarData | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const router = useRouter();

  const refreshUser = useCallback(async () => {
    try {
      const res = await api.get('/auth/me');
      if (res.status === 'success' && res.data) {
        setUser(res.data.user || null);
        setPermissions(res.data.permissions || []);
        if (res.data.balances) setBalances(res.data.balances);
        if (res.data.topbar) setTopbar(res.data.topbar);
      } else {
        setUser(null);
        setPermissions([]);
        setBalances(null);
      }
    } catch {
      setUser(null);
      setPermissions([]);
      setBalances(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refreshUser();
  }, [refreshUser]);

  const login = async (credentials: any) => {
    const res = await api.post('/auth/login', credentials);
    if (res.status === 'success' && res.data) {
      setUser(res.data.user);
      setPermissions(res.data.permissions || []);
      await refreshUser();
      return res.data;
    }
    throw new Error(res.message || 'Login failed');
  };

  const register = async (data: any) => {
    const res = await api.post('/auth/register', data);
    if (res.status === 'success' && res.data) {
      setUser(res.data.user);
      await refreshUser();
      return res.data;
    }
    throw new Error(res.message || 'Registration failed');
  };

  const logout = async () => {
    try {
      await api.post('/auth/logout');
    } catch {
      // ignore
    } finally {
      setUser(null);
      setPermissions([]);
      setBalances(null);
      setTopbar(null);
      router.push('/login');
    }
  };

  const can = (permissionSlug: string): boolean => {
    if (!user) return false;
    if (user.user_type !== 'admin') return false;
    if (user.role_id === 1 || user.role === 'superadmin') return true;
    return permissions.includes(permissionSlug);
  };

  const isAuthenticated = !!user;
  const isAdmin = user?.user_type === 'admin';
  const isMember = user?.user_type === 'member';

  return (
    <AuthContext.Provider
      value={{
        user,
        permissions,
        balances,
        topbar,
        loading,
        isAuthenticated,
        isAdmin,
        isMember,
        can,
        login,
        register,
        logout,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
