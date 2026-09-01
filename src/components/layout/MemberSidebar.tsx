'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import {
  LayoutDashboard,
  PiggyBank,
  PieChart,
  Banknote,
  CalendarCheck,
  HeartPulse,
  PhoneCall,
  Wallet,
  ArrowLeftRight,
  Bell,
  User,
  Settings,
  Headphones,
  LogOut,
  TrendingUp,
  Briefcase,
} from 'lucide-react';

interface MemberSidebarProps {
  collapsed: boolean;
  mobileOpen: boolean;
  onCloseMobile: () => void;
}

export function MemberSidebar({ collapsed, mobileOpen, onCloseMobile }: MemberSidebarProps) {
  const pathname = usePathname();
  const { user, logout } = useAuth();

  const isActive = (path: string) => {
    if (path === '/member' && pathname === '/member') return true;
    if (path !== '/member' && pathname?.startsWith(path)) return true;
    return false;
  };

  const navItems = [
    { label: 'Dashboard', icon: <LayoutDashboard size={20} />, href: '/member' },
    
    { header: 'Personal Finances' },
    { label: 'Daily Income', icon: <TrendingUp size={20} />, href: '/member/income' },
    { label: 'Savings', icon: <PiggyBank size={20} />, href: '/member/savings' },
    { label: 'My Investments', icon: <Briefcase size={20} />, href: '/member/investments' },
    { label: 'Shares Portfolio', icon: <PieChart size={20} />, href: '/member/shares' },
    { label: 'My Loans', icon: <Banknote size={20} />, href: '/member/loans' },
    { label: 'Contributions', icon: <CalendarCheck size={20} />, href: '/member/contributions' },

    { header: 'Welfare & Solidarity' },
    { label: 'Welfare Hub', icon: <HeartPulse size={20} />, href: '/member/welfare' },

    { header: 'Utilities' },
    { label: 'Pay Via M-Pesa', icon: <PhoneCall size={20} />, href: '/member/mpesa' },
    { label: 'Withdraw Funds', icon: <Wallet size={20} />, href: '/member/withdraw' },
    { label: 'All Transactions', icon: <ArrowLeftRight size={20} />, href: '/member/transactions' },
    { label: 'Notifications', icon: <Bell size={20} />, href: '/member/notifications' },

    { header: 'Account' },
    { label: 'My Profile', icon: <User size={20} />, href: '/member/profile' },
    { label: 'Settings', icon: <Settings size={20} />, href: '/member/settings' },
  ];

  return (
    <>
      {/* Mobile Backdrop */}
      {mobileOpen && (
        <div
          onClick={onCloseMobile}
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(0,0,0,0.5)',
            zIndex: 1035,
          }}
        />
      )}

      <aside
        style={{
          width: collapsed ? 'var(--sb-collapsed)' : 'var(--sb-width)',
          height: '100vh',
          position: 'fixed',
          top: 0,
          left: 0,
          zIndex: 1040,
          backgroundColor: 'var(--sb-bg)',
          borderRight: '1px solid var(--sb-border)',
          display: 'flex',
          flexDirection: 'column',
          transition: 'width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s ease',
          transform: mobileOpen ? 'translateX(0)' : undefined,
          boxShadow: '5px 0 30px rgba(0,0,0,0.02)',
        }}
        className={`member-sidebar ${mobileOpen ? 'mobile-open' : ''}`}
      >
        {/* Brand */}
        <div
          style={{
            height: '80px',
            display: 'flex',
            alignItems: 'center',
            padding: collapsed ? '0' : '0 24px',
            justifyContent: collapsed ? 'center' : 'flex-start',
            borderBottom: '1px solid var(--sb-border)',
            gap: '12px',
          }}
        >
          <div
            style={{
              width: '42px',
              height: '42px',
              borderRadius: '12px',
              backgroundColor: '#FFFFFF',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              overflow: 'hidden',
              flexShrink: 0,
              padding: '2px',
              boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
            }}
          >
            <img
              src="/assets/images/people_logo.png"
              alt="Umoja Sacco Logo"
              style={{ width: '100%', height: '100%', objectFit: 'contain' }}
            />
          </div>
          {!collapsed && (
            <div>
              <div style={{ fontWeight: 800, fontSize: '0.95rem', color: 'var(--text-main)', letterSpacing: '-0.3px', lineHeight: 1.1 }}>
                UMOJA <span style={{ color: 'var(--brand-lime)' }}>SACCO</span>
              </div>
              <small style={{ fontSize: '0.65rem', textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px', fontWeight: 700 }}>
                {user?.reg_no || 'MEMBER PANEL'}
              </small>
            </div>
          )}
        </div>

        {/* Scrollable Nav items */}
        <div
          style={{
            flex: 1,
            overflowY: 'auto',
            overflowX: 'hidden',
            padding: '12px 14px',
          }}
        >
          {navItems.map((item, idx) => {
            if (item.header) {
              if (collapsed) return null;
              return (
                <div
                  key={idx}
                  style={{
                    fontSize: '0.68rem',
                    fontWeight: 800,
                    textTransform: 'uppercase',
                    letterSpacing: '1.2px',
                    color: 'var(--text-dim)',
                    margin: '20px 0 6px 12px',
                    whiteSpace: 'nowrap',
                  }}
                >
                  {item.header}
                </div>
              );
            }

            const active = isActive(item.href!);
            return (
              <Link
                key={idx}
                href={item.href!}
                onClick={onCloseMobile}
                title={collapsed ? item.label : undefined}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  padding: collapsed ? '12px 0' : '11px 16px',
                  justifyContent: collapsed ? 'center' : 'flex-start',
                  color: active ? '#FFFFFF' : 'var(--sb-text)',
                  backgroundColor: active ? 'var(--active-bg)' : 'transparent',
                  borderRadius: '50px',
                  marginBottom: '4px',
                  textDecoration: 'none',
                  fontWeight: active ? 700 : 500,
                  fontSize: '0.92rem',
                  boxShadow: active ? '0 4px 15px rgba(15, 57, 43, 0.25)' : 'none',
                  transition: 'all 0.2s',
                  whiteSpace: 'nowrap',
                }}
              >
                <span style={{ display: 'flex', color: active ? 'var(--accent-lime)' : 'inherit', marginRight: collapsed ? 0 : '12px' }}>
                  {item.icon}
                </span>
                {!collapsed && <span>{item.label}</span>}
              </Link>
            );
          })}

          {/* Support Widget */}
          {!collapsed && (
            <div
              style={{
                backgroundColor: 'var(--active-bg)',
                color: '#FFFFFF',
                padding: '18px',
                borderRadius: '18px',
                textAlign: 'center',
                margin: '24px 0 12px',
              }}
            >
              <Headphones size={24} style={{ color: 'var(--accent-lime)', marginBottom: '6px' }} />
              <h6 style={{ fontWeight: 700, fontSize: '0.88rem', marginBottom: '4px' }}>Need Help?</h6>
              <p style={{ fontSize: '0.75rem', opacity: 0.8, marginBottom: '10px' }}>Contact Support Desk</p>
              <Link
                href="/member/support"
                onClick={onCloseMobile}
                style={{
                  display: 'block',
                  backgroundColor: 'var(--accent-lime)',
                  color: '#0F392B',
                  fontWeight: 700,
                  fontSize: '0.8rem',
                  padding: '8px 14px',
                  borderRadius: '50px',
                }}
              >
                Open Ticket
              </Link>
            </div>
          )}
        </div>

        {/* Footer Logout */}
        <div style={{ padding: '16px', borderTop: '1px solid var(--sb-border)' }}>
          <button
            onClick={() => logout()}
            title="Logout"
            style={{
              width: '100%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '10px',
              padding: '10px',
              borderRadius: '50px',
              border: 'none',
              backgroundColor: 'rgba(239, 68, 68, 0.08)',
              color: '#dc2626',
              fontWeight: 700,
              fontSize: '0.9rem',
              cursor: 'pointer',
              transition: 'background 0.2s',
            }}
          >
            <LogOut size={18} />
            {!collapsed && <span>Logout</span>}
          </button>
        </div>
      </aside>
    </>
  );
}
