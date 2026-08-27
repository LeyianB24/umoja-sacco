'use client';

import React, { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import { useRouter } from 'next/navigation';
import { MemberSidebar } from '@/components/layout/MemberSidebar';
import { MemberTopbar } from '@/components/layout/MemberTopbar';
import { BottomNav } from '@/components/sacco-ui/BottomNav';

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
          backgroundColor: 'var(--color-gray-light)',
        }}
      >
        <div style={{ textAlign: 'center' }}>
          <div
            style={{
              width: '40px',
              height: '40px',
              borderRadius: '50%',
              border: '3px solid var(--color-gray-border)',
              borderTopColor: 'var(--color-forest)',
              animation: 'spin 0.8s linear infinite',
              margin: '0 auto 14px',
            }}
          />
          <div style={{ fontWeight: 600, color: 'var(--color-charcoal)', fontSize: '14px' }}>
            Loading Umoja SACCO...
          </div>
        </div>
      </div>
    );
  }

  if (!isAuthenticated || !isMember) {
    return null;
  }

  return (
    <div style={{ display: 'flex', minHeight: '100vh', backgroundColor: 'var(--color-gray-light)' }}>
      {/* Desktop Sidebar */}
      <MemberSidebar
        collapsed={collapsed}
        mobileOpen={mobileOpen}
        onCloseMobile={() => setMobileOpen(false)}
      />

      {/* Main Content Area */}
      <div
        style={{
          flex: 1,
          display: 'flex',
          flexDirection: 'column',
          minWidth: 0,
          marginLeft: collapsed ? 'var(--sb-collapsed)' : 'var(--sb-width)',
          transition: 'margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1)',
        }}
        className="main-content-wrapper"
      >
        <MemberTopbar
          onToggleSidebar={toggleCollapsed}
          onToggleMobile={() => setMobileOpen(!mobileOpen)}
        />

        <main
          style={{
            flex: 1,
            padding: '24px 28px 84px 28px',
            maxWidth: '1200px',
            width: '100%',
            margin: '0 auto',
          }}
          className="sacco-main-content"
        >
          {children}
        </main>

        {/* Mobile Fixed Bottom Navigation */}
        <div className="d-md-none">
          <BottomNav />
        </div>
      </div>
    </div>
  );
}
