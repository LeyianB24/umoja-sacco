'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Home, Wallet, Activity, TrendingUp } from 'lucide-react';

export const BottomNav: React.FC = () => {
  const pathname = usePathname();

  const isHome = pathname === '/member';
  const isWallet = pathname?.includes('/member/savings') || pathname?.includes('/member/withdraw') || pathname?.includes('/member/mpesa');
  const isActivity = pathname?.includes('/member/transactions') || pathname?.includes('/member/contributions');
  const isInvest = pathname?.includes('/member/shares') || pathname?.includes('/member/loans') || pathname?.includes('/member/welfare');

  return (
    <nav className="sacco-bottom-nav" aria-label="Mobile Navigation">
      {/* Home Tab */}
      <Link
        href="/member"
        className={`sacco-nav-tab ${isHome ? 'sacco-nav-tab-active' : ''}`}
        id="sacco-nav-home"
      >
        <Home size={20} color={isHome ? '#ffffff' : 'var(--color-gray-medium)'} />
        <span className="sacco-nav-tab-label">Home</span>
      </Link>

      {/* Wallet Tab */}
      <Link
        href="/member/savings"
        className={`sacco-nav-tab ${isWallet ? 'sacco-nav-tab-active' : ''}`}
        id="sacco-nav-wallet"
      >
        <Wallet size={20} color={isWallet ? '#ffffff' : 'var(--color-gray-medium)'} />
        <span className="sacco-nav-tab-label">Wallet</span>
      </Link>

      {/* Activity Tab */}
      <Link
        href="/member/transactions"
        className={`sacco-nav-tab ${isActivity ? 'sacco-nav-tab-active' : ''}`}
        id="sacco-nav-activity"
      >
        <Activity size={20} color={isActivity ? '#ffffff' : 'var(--color-gray-medium)'} />
        <span className="sacco-nav-tab-label">Activity</span>
      </Link>

      {/* Invest Tab */}
      <Link
        href="/member/shares"
        className={`sacco-nav-tab ${isInvest ? 'sacco-nav-tab-active' : ''}`}
        id="sacco-nav-invest"
      >
        <TrendingUp size={20} color={isInvest ? '#ffffff' : 'var(--color-gray-medium)'} />
        <span className="sacco-nav-tab-label">Invest</span>
      </Link>
    </nav>
  );
};
