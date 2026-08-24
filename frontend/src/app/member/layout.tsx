'use client';

import React, { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import { useRouter } from 'next/navigation';
import { MemberSidebar } from '@/components/layout/MemberSidebar';
import { MemberTopbar } from '@/components/layout/MemberTopbar';

export default function MemberLayout({ children }: { children: React.ReactNode }) {
  const { user, loading, isAuthenticated, isMember } = useAuth();
  const router = useRouter();
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    const saved = localStorage.getItem('hd_sidebar_collapsed') === 'true';
    setCollapsed(saved);
  }, []);

  const toggleCollapsed = () => {
    const next = !collapsed;
    setCollapsed(next);
    localStorage.setItem('hd_sidebar_collapsed', String(next));
  };

  useEffect(() => {
    if (!loading && (!isAuthenticated || !isMember)) {
      router.push('/login');
    }
  }, [loading, isAuthenticated, isMember, router]);

  if (loading) {
    return (
      <div
        style={{
          minHeight: '100vh',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: 'var(--bg-primary)',
        }}
      >
        <div style={{ textAlign: 'center' }}>
          <div
            style={{
              width: '48px',
              height: '48px',
              borderRadius: '50%',
              border: '3px solid rgba(208, 247, 100, 0.2)',
              borderTopColor: 'var(--brand-lime)',
              animation: 'spin 1s linear infinite',
              margin: '0 auto 16px',
            }}
          />
          <div style={{ fontWeight: 700, color: 'var(--text-main)', fontSize: '0.95rem' }}>
            Loading Member Portal...
          </div>
        </div>
      </div>
    );
  }

  if (!isAuthenticated || !isMember) {
    return null;
  }

  return (
    <div style={{ display: 'flex', minHeight: '100vh', backgroundColor: 'var(--bg-primary)' }}>
      <MemberSidebar
        collapsed={collapsed}
        mobileOpen={mobileOpen}
        onCloseMobile={() => setMobileOpen(false)}
      />

      <div
        style={{
          flex: 1,
          display: 'flex',
          flexDirection: 'column',
          minWidth: 0,
          marginLeft: collapsed ? 'var(--sb-collapsed)' : 'var(--sb-width)',
          transition: 'margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
        }}
        className="main-content-wrapper"
      >
        <MemberTopbar
          onToggleSidebar={toggleCollapsed}
          onToggleMobile={() => setMobileOpen(!mobileOpen)}
        />
        <main style={{ flex: 1, padding: '28px' }}>
          {children}
        </main>
      </div>
    </div>
  );
}
