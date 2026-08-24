'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import {
  LayoutDashboard,
  UserPlus,
  Users,
  Briefcase,
  ShieldCheck,
  Shield,
  CreditCard,
  TrendingUp,
  Receipt,
  Wallet2,
  FileSpreadsheet,
  Activity,
  Scale,
  Banknote,
  Coins,
  HeartPulse,
  Building2,
  PieChart,
  BarChart3,
  FileText,
  Monitor,
  Database,
  Headphones,
  Sliders,
  LogOut,
} from 'lucide-react';

interface AdminSidebarProps {
  collapsed: boolean;
  mobileOpen: boolean;
  onCloseMobile: () => void;
}

export function AdminSidebar({ collapsed, mobileOpen, onCloseMobile }: AdminSidebarProps) {
  const pathname = usePathname();
  const { user, can, logout } = useAuth();

  const isActive = (path: string) => {
    if (path === '/admin' && pathname === '/admin') return true;
    if (path !== '/admin' && pathname?.startsWith(path)) return true;
    return false;
  };

  const isSuper = user?.role_id === 1 || user?.role === 'superadmin';

  const sections = [
    {
      header: 'Overview',
      items: [
        { label: 'Admin Dashboard', icon: <LayoutDashboard size={19} />, href: '/admin', perm: 'dashboard.php' },
      ],
    },
    {
      header: 'Member Management',
      items: [
        { label: 'Member Onboarding', icon: <UserPlus size={19} />, href: '/admin/members/onboarding', perm: 'member_onboarding.php' },
        { label: 'Members List', icon: <Users size={19} />, href: '/admin/members', perm: 'members.php' },
      ],
    },
    {
      header: 'People & Access',
      items: [
        { label: 'Employees', icon: <Briefcase size={19} />, href: '/admin/employees', perm: 'employees.php' },
        { label: 'System Users (Admins)', icon: <ShieldCheck size={19} />, href: '/admin/users', perm: 'users.php' },
        { label: 'Access Control (RBAC)', icon: <Shield size={19} />, href: '/admin/roles', perm: 'roles.php' },
      ],
    },
    {
      header: 'Financial Management',
      items: [
        { label: 'Cashier / Payments', icon: <CreditCard size={19} />, href: '/admin/payments', perm: 'payments.php' },
        { label: 'Revenue Inflow', icon: <TrendingUp size={19} />, href: '/admin/revenue', perm: 'revenue.php' },
        { label: 'Expense Tracker', icon: <Receipt size={19} />, href: '/admin/expenses', perm: 'expenses.php' },
        { label: 'Payroll Processing', icon: <Wallet2 size={19} />, href: '/admin/payroll', perm: 'payroll.php' },
        { label: 'Live Ledger View', icon: <FileSpreadsheet size={19} />, href: '/admin/transactions', perm: 'transactions.php' },
        { label: 'Transaction Monitor', icon: <Activity size={19} />, href: '/admin/monitor', perm: 'monitor.php' },
        { label: 'Trial Balance', icon: <Scale size={19} />, href: '/admin/trial-balance', perm: 'trial_balance.php' },
      ],
    },
    {
      header: 'Loans & Credit',
      items: [
        { label: 'Loan Reviews', icon: <Banknote size={19} />, href: '/admin/loans/reviews', perm: 'loans_reviews.php' },
        { label: 'Loan Payouts', icon: <Coins size={19} />, href: '/admin/loans/payouts', perm: 'loans_payouts.php' },
        { label: 'All Loans', icon: <Banknote size={19} />, href: '/admin/loans', perm: 'loans.php' },
      ],
    },
    {
      header: 'Welfare & Investments',
      items: [
        { label: 'Welfare Management', icon: <HeartPulse size={19} />, href: '/admin/welfare', perm: 'welfare.php' },
        { label: 'Asset Portfolio', icon: <Building2 size={19} />, href: '/admin/investments', perm: 'investments.php' },
        { label: 'Equity & Shares', icon: <PieChart size={19} />, href: '/admin/shares', perm: 'admin_shares.php' },
      ],
    },
    {
      header: 'Reports & Exports',
      items: [
        { label: 'Analytical Reports', icon: <BarChart3 size={19} />, href: '/admin/reports', perm: 'reports.php' },
        { label: 'Account Statements', icon: <FileText size={19} />, href: '/admin/statements', perm: 'statements.php' },
      ],
    },
    {
      header: 'System Maintenance',
      items: [
        { label: 'Live Monitor', icon: <Monitor size={19} />, href: '/admin/live-monitor', perm: 'live_monitor.php' },
        { label: 'System Health', icon: <Activity size={19} />, href: '/admin/system-health', perm: 'system_health.php' },
        { label: 'Database Backups', icon: <Database size={19} />, href: '/admin/backups', perm: 'backups.php' },
        { label: 'Tech Support', icon: <Headphones size={19} />, href: '/admin/support', perm: 'support.php' },
        { label: 'Global Settings', icon: <Sliders size={19} />, href: '/admin/settings', perm: 'settings.php' },
      ],
    },
  ];

  return (
    <>
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
        className={`admin-sidebar ${mobileOpen ? 'mobile-open' : ''}`}
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
                UMOJA <span style={{ color: 'var(--brand-lime)' }}>ADMIN</span>
              </div>
              <small style={{ fontSize: '0.65rem', textTransform: 'uppercase', color: 'var(--text-muted)', letterSpacing: '0.5px', fontWeight: 700 }}>
                {user?.role_name || 'STAFF PORTAL'}
              </small>
            </div>
          )}
        </div>

        {/* Navigation list */}
        <div
          style={{
            flex: 1,
            overflowY: 'auto',
            overflowX: 'hidden',
            padding: '12px 14px',
          }}
        >
          {sections.map((sec, sIdx) => {
            const visibleItems = sec.items.filter((it) => isSuper || can(it.perm) || it.perm === 'dashboard.php');
            if (visibleItems.length === 0) return null;

            return (
              <div key={sIdx}>
                {!collapsed && (
                  <div
                    style={{
                      fontSize: '0.68rem',
                      fontWeight: 800,
                      textTransform: 'uppercase',
                      letterSpacing: '1.2px',
                      color: 'var(--text-dim)',
                      margin: '18px 0 6px 12px',
                      whiteSpace: 'nowrap',
                    }}
                  >
                    {sec.header}
                  </div>
                )}
                {visibleItems.map((item, iIdx) => {
                  const active = isActive(item.href);
                  return (
                    <Link
                      key={iIdx}
                      href={item.href}
                      onClick={onCloseMobile}
                      title={collapsed ? item.label : undefined}
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        padding: collapsed ? '12px 0' : '10px 16px',
                        justifyContent: collapsed ? 'center' : 'flex-start',
                        color: active ? '#FFFFFF' : 'var(--sb-text)',
                        backgroundColor: active ? 'var(--active-bg)' : 'transparent',
                        borderRadius: '50px',
                        marginBottom: '3px',
                        textDecoration: 'none',
                        fontWeight: active ? 700 : 500,
                        fontSize: '0.9rem',
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
              </div>
            );
          })}
        </div>

        {/* Footer */}
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
            }}
          >
            <LogOut size={18} />
            {!collapsed && <span>Sign Out</span>}
          </button>
        </div>
      </aside>
    </>
  );
}
