'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useAuth } from '@/context/AuthContext';
import { useTheme } from '@/context/ThemeContext';
import { Menu, Sun, Moon, Bell, Shield, Sliders, LogOut, ChevronDown, Activity, Printer } from 'lucide-react';
import { getInitials } from '@/lib/utils';

interface AdminTopbarProps {
  onToggleSidebar: () => void;
  onToggleMobile: () => void;
}

export function AdminTopbar({ onToggleSidebar, onToggleMobile }: AdminTopbarProps) {
  const { user, topbar, logout } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const [profileOpen, setProfileOpen] = useState(false);
  const [notifOpen, setNotifOpen] = useState(false);

  const unreadNotifs = topbar?.unread_notifications ?? 0;

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

        {/* Live status badge */}
        <div
          style={{
            display: 'none',
            alignItems: 'center',
            gap: '8px',
            padding: '6px 14px',
            borderRadius: '50px',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            border: '1px solid rgba(34, 197, 94, 0.25)',
            fontSize: '0.78rem',
            fontWeight: 700,
            color: '#16a34a',
          }}
          className="d-md-flex"
        >
          <span
            style={{
              width: '8px',
              height: '8px',
              borderRadius: '50%',
              backgroundColor: '#16a34a',
              boxShadow: '0 0 8px #16a34a',
            }}
          />
          System Active • Database Connected
        </div>
      </div>

      {/* Right */}
      <div style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
        {/* Universal Admin Print Button */}
        <button
          onClick={() => window.print()}
          title="Print this Page / Report"
          style={{
            height: '38px',
            padding: '0 12px',
            borderRadius: '50px',
            backgroundColor: 'var(--surface-2)',
            border: '1px solid var(--border-color)',
            color: 'var(--text-main)',
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            cursor: 'pointer',
            fontSize: '0.8rem',
            fontWeight: 600,
          }}
        >
          <Printer size={16} />
          <span className="d-none d-sm-inline">Print</span>
        </button>

        {/* Role badge */}
        <span
          style={{
            padding: '4px 12px',
            borderRadius: '50px',
            fontSize: '0.72rem',
            fontWeight: 800,
            textTransform: 'uppercase',
            letterSpacing: '0.5px',
            backgroundColor: 'var(--brand-lime-soft)',
            color: 'var(--brand-forest)',
            border: '1px solid rgba(208, 247, 100, 0.3)',
          }}
          className="d-none d-sm-inline-flex"
        >
          {user?.role_name || 'Admin'}
        </span>

        {/* Theme toggle */}
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

        {/* Audit / Alerts */}
        <div style={{ position: 'relative' }}>
          <button
            onClick={() => setNotifOpen(!notifOpen)}
            title="System alerts"
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
                <span style={{ fontWeight: 700, fontSize: '0.9rem' }}>Activity & Alerts</span>
                <Link href="/admin/live-monitor" onClick={() => setNotifOpen(false)} style={{ fontSize: '0.75rem', color: 'var(--brand-forest)', fontWeight: 600 }}>
                  Live Monitor
                </Link>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                {topbar?.recent_notifications?.length ? (
                  topbar.recent_notifications.slice(0, 3).map((n, i) => (
                    <div key={i} style={{ padding: '8px 10px', borderRadius: '10px', backgroundColor: 'var(--surface-2)', fontSize: '0.8rem' }}>
                      <div style={{ fontWeight: 600 }}>{n.title || 'System Alert'}</div>
                      <div style={{ color: 'var(--text-muted)', fontSize: '0.75rem' }}>{n.message}</div>
                    </div>
                  ))
                ) : (
                  <div style={{ textAlign: 'center', color: 'var(--text-muted)', fontSize: '0.8rem', padding: '12px 0' }}>
                    All services operational
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        {/* Profile */}
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
                {user?.name}
              </span>
              <span style={{ fontSize: '0.7rem', color: 'var(--text-muted)' }}>{user?.username || 'Staff'}</span>
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
                href="/admin/settings"
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
                }}
                onMouseOver={(e) => (e.currentTarget.style.backgroundColor = 'var(--surface-2)')}
                onMouseOut={(e) => (e.currentTarget.style.backgroundColor = 'transparent')}
              >
                <Sliders size={16} /> Global Settings
              </Link>
              <Link
                href="/admin/live-monitor"
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
                }}
                onMouseOver={(e) => (e.currentTarget.style.backgroundColor = 'var(--surface-2)')}
                onMouseOut={(e) => (e.currentTarget.style.backgroundColor = 'transparent')}
              >
                <Activity size={16} /> Live Monitor
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
                <LogOut size={16} /> Sign Out
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
