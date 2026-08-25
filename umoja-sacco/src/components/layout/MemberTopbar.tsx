'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useAuth } from '@/context/AuthContext';
import { useTheme } from '@/context/ThemeContext';
import { Menu, Sun, Moon, Bell, MessageSquare, User, Settings, LogOut, ChevronDown } from 'lucide-react';
import { getInitials } from '@/lib/utils';

interface MemberTopbarProps {
  onToggleSidebar: () => void;
  onToggleMobile: () => void;
}

export function MemberTopbar({ onToggleSidebar, onToggleMobile }: MemberTopbarProps) {
  const { user, topbar, logout } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const [profileOpen, setProfileOpen] = useState(false);
  const [notifOpen, setNotifOpen] = useState(false);

  const unreadNotifs = topbar?.unread_notifications ?? 0;
  const unreadMsgs = topbar?.unread_messages ?? 0;

  return (
    <header
      style={{
        height: '70px',
        backgroundColor: 'var(--bg-surface)',
        borderBottom: '1px solid var(--border-color)',
        position: 'sticky',
        top: 0,
        zIndex: 1030,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '0 24px',
        boxShadow: 'var(--shadow-sm)',
      }}
    >
      {/* Left */}
      <div style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
        <button
          onClick={onToggleSidebar}
          title="Toggle sidebar"
          style={{
            width: '38px',
            height: '38px',
            borderRadius: '10px',
            backgroundColor: 'var(--surface-2)',
            border: '1px solid var(--border-color)',
            color: 'var(--text-main)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
          }}
          className="d-none d-lg-flex"
        >
          <Menu size={20} />
        </button>

        <button
          onClick={onToggleMobile}
          style={{
            width: '38px',
            height: '38px',
            borderRadius: '10px',
            backgroundColor: 'var(--surface-2)',
            border: '1px solid var(--border-color)',
            color: 'var(--text-main)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
          }}
          className="d-lg-none"
        >
          <Menu size={20} />
        </button>

        <div style={{ display: 'flex', flexDirection: 'column' }}>
          <span style={{ fontSize: '0.82rem', fontWeight: 600, color: 'var(--text-muted)' }}>
            Welcome back,
          </span>
          <span style={{ fontSize: '1rem', fontWeight: 800, color: 'var(--text-main)' }}>
            {user?.name?.split(' ')[0] || 'Member'}
          </span>
        </div>
      </div>

      {/* Right */}
      <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
        {/* Theme Toggle */}
        <button
          onClick={toggleTheme}
          title="Toggle Theme"
          style={{
            width: '38px',
            height: '38px',
            borderRadius: '50%',
            backgroundColor: 'var(--surface-2)',
            border: '1px solid var(--border-color)',
            color: 'var(--text-main)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
          }}
        >
          {theme === 'dark' ? <Sun size={18} color="#D0F764" /> : <Moon size={18} />}
        </button>

        {/* Notifications */}
        <div style={{ position: 'relative' }}>
          <button
            onClick={() => setNotifOpen(!notifOpen)}
            title="Notifications"
            style={{
              width: '38px',
              height: '38px',
              borderRadius: '50%',
              backgroundColor: 'var(--surface-2)',
              border: '1px solid var(--border-color)',
              color: 'var(--text-main)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              cursor: 'pointer',
              position: 'relative',
            }}
          >
            <Bell size={18} />
            {unreadNotifs > 0 && (
              <span
                style={{
                  position: 'absolute',
                  top: '-2px',
                  right: '-2px',
                  backgroundColor: '#dc2626',
                  color: '#FFFFFF',
                  fontSize: '0.65rem',
                  fontWeight: 800,
                  width: '18px',
                  height: '18px',
                  borderRadius: '50%',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  border: '2px solid var(--bg-surface)',
                }}
              >
                {unreadNotifs}
              </span>
            )}
          </button>

          {notifOpen && (
            <div
              style={{
                position: 'absolute',
                right: 0,
                top: '48px',
                width: '320px',
                backgroundColor: 'var(--bg-surface)',
                border: '1px solid var(--border-color)',
                borderRadius: '16px',
                boxShadow: 'var(--shadow-xl)',
                padding: '16px',
                zIndex: 1050,
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                <span style={{ fontWeight: 700, fontSize: '0.9rem' }}>Notifications</span>
                <Link href="/member/notifications" onClick={() => setNotifOpen(false)} style={{ fontSize: '0.75rem', color: 'var(--brand-forest)', fontWeight: 600 }}>
                  View All
                </Link>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                {topbar?.recent_notifications?.length ? (
                  topbar.recent_notifications.slice(0, 3).map((n, i) => (
                    <div key={i} style={{ padding: '8px 10px', borderRadius: '10px', backgroundColor: 'var(--surface-2)', fontSize: '0.8rem' }}>
                      <div style={{ fontWeight: 600 }}>{n.title || 'Notification'}</div>
                      <div style={{ color: 'var(--text-muted)', fontSize: '0.75rem' }}>{n.message}</div>
                    </div>
                  ))
                ) : (
                  <div style={{ textAlign: 'center', color: 'var(--text-muted)', fontSize: '0.8rem', padding: '12px 0' }}>
                    No new notifications
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        {/* Profile Dropdown */}
        <div style={{ position: 'relative' }}>
          <button
            onClick={() => setProfileOpen(!profileOpen)}
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '10px',
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              padding: '4px 8px',
              borderRadius: '50px',
              backgroundColor: 'var(--surface-2)',
            }}
          >
            <div
              style={{
                width: '36px',
                height: '36px',
                borderRadius: '50%',
                background: 'linear-gradient(135deg, #0F392B 0%, #134e3b 100%)',
                color: 'var(--brand-lime)',
                fontWeight: 800,
                fontSize: '0.85rem',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              {getInitials(user?.name)}
            </div>
            <div style={{ display: 'none', flexDirection: 'column', textAlign: 'left' }} className="d-md-flex">
              <span style={{ fontSize: '0.85rem', fontWeight: 700, color: 'var(--text-main)', lineHeight: 1.1 }}>
                {user?.name?.split(' ')[0]}
              </span>
              <span style={{ fontSize: '0.7rem', color: 'var(--text-muted)' }}>{user?.reg_no}</span>
            </div>
            <ChevronDown size={14} style={{ color: 'var(--text-muted)' }} />
          </button>

          {profileOpen && (
            <div
              style={{
                position: 'absolute',
                right: 0,
                top: '48px',
                width: '220px',
                backgroundColor: 'var(--bg-surface)',
                border: '1px solid var(--border-color)',
                borderRadius: '16px',
                boxShadow: 'var(--shadow-xl)',
                padding: '8px',
                zIndex: 1050,
                display: 'flex',
                flexDirection: 'column',
                gap: '4px',
              }}
            >
              <Link
                href="/member/profile"
                onClick={() => setProfileOpen(false)}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '10px',
                  padding: '10px 14px',
                  borderRadius: '10px',
                  fontSize: '0.88rem',
                  fontWeight: 600,
                  color: 'var(--text-main)',
                  transition: 'background 0.2s',
                }}
                onMouseOver={(e) => (e.currentTarget.style.backgroundColor = 'var(--surface-2)')}
                onMouseOut={(e) => (e.currentTarget.style.backgroundColor = 'transparent')}
              >
                <User size={16} /> My Profile
              </Link>
              <Link
                href="/member/settings"
                onClick={() => setProfileOpen(false)}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '10px',
                  padding: '10px 14px',
                  borderRadius: '10px',
                  fontSize: '0.88rem',
                  fontWeight: 600,
                  color: 'var(--text-main)',
                  transition: 'background 0.2s',
                }}
                onMouseOver={(e) => (e.currentTarget.style.backgroundColor = 'var(--surface-2)')}
                onMouseOut={(e) => (e.currentTarget.style.backgroundColor = 'transparent')}
              >
                <Settings size={16} /> Settings
              </Link>
              <div style={{ height: '1px', backgroundColor: 'var(--border-color)', margin: '4px 0' }} />
              <button
                onClick={() => {
                  setProfileOpen(false);
                  logout();
                }}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '10px',
                  padding: '10px 14px',
                  borderRadius: '10px',
                  fontSize: '0.88rem',
                  fontWeight: 700,
                  color: '#dc2626',
                  background: 'none',
                  border: 'none',
                  cursor: 'pointer',
                  width: '100%',
                  textAlign: 'left',
                }}
                onMouseOver={(e) => (e.currentTarget.style.backgroundColor = 'rgba(239, 68, 68, 0.08)')}
                onMouseOut={(e) => (e.currentTarget.style.backgroundColor = 'transparent')}
              >
                <LogOut size={16} /> Logout
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
