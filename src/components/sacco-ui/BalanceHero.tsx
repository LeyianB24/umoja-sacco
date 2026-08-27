import React, { ReactNode } from 'react';
import { ChevronDown, Plus, ArrowUp, ArrowLeftRight, CreditCard } from 'lucide-react';
import { Button } from './Button';

export interface BalanceHeroProps {
  label?: string;
  accountNumber?: string;
  amount: number | string;
  currency?: string;
  onAccountClick?: () => void;
  onAddMoney?: () => void;
  onWithdraw?: () => void;
  onTransfer?: () => void;
  actions?: ReactNode;
}

export const BalanceHero: React.FC<BalanceHeroProps> = ({
  label = 'Total Available Balance',
  accountNumber = '•••• 4556',
  amount,
  currency = 'KES',
  onAccountClick,
  onAddMoney,
  onWithdraw,
  onTransfer,
  actions,
}) => {
  // Format whole and decimals
  const num = typeof amount === 'number' ? amount : parseFloat(String(amount).replace(/[^0-9.-]+/g, '')) || 0;
  const formatted = num.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const parts = formatted.split('.');
  const whole = parts[0];
  const decimal = parts[1] || '00';

  return (
    <div className="balance-hero-container">
      {/* Account Selector Pill */}
      {accountNumber && (
        <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '8px' }}>
          <button
            type="button"
            onClick={onAccountClick}
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
              padding: '4px 12px',
              borderRadius: 'var(--radius-full)',
              backgroundColor: 'var(--color-white)',
              border: '1px solid var(--color-gray-border)',
              color: 'var(--color-gray-dark)',
              fontSize: '13px',
              fontWeight: 500,
              cursor: onAccountClick ? 'pointer' : 'default',
              transition: 'all 0.15s ease',
            }}
          >
            <CreditCard size={14} color="var(--color-forest)" />
            <span>{accountNumber}</span>
            {onAccountClick && <ChevronDown size={14} />}
          </button>
        </div>
      )}

      {/* Label */}
      <div style={{ fontSize: '13px', color: 'var(--color-gray-dark)', fontWeight: 500, marginBottom: '4px' }}>
        {label}
      </div>

      {/* Amount (32px Bold Whole, Normal Decimals) */}
      <div className="balance-hero-amount">
        <span style={{ fontSize: '20px', marginRight: '4px', fontWeight: 600, color: 'var(--color-forest)' }}>
          {currency}
        </span>
        <span>{whole}.</span>
        <span className="balance-hero-decimals">{decimal}</span>
      </div>

      {/* Action Buttons (Pill buttons) */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          gap: '10px',
          marginTop: '16px',
          flexWrap: 'wrap',
        }}
      >
        {actions ? (
          actions
        ) : (
          <>
            {onAddMoney && (
              <Button variant="primary" size="md" pill onClick={onAddMoney}>
                <Plus size={16} strokeWidth={2.5} />
                <span>Add Money</span>
              </Button>
            )}
            {onWithdraw && (
              <Button variant="secondary" size="md" pill onClick={onWithdraw}>
                <ArrowUp size={16} strokeWidth={2.5} />
                <span>Withdraw</span>
              </Button>
            )}
            {onTransfer && (
              <Button variant="secondary" size="md" pill onClick={onTransfer}>
                <ArrowLeftRight size={16} strokeWidth={2.5} />
                <span>Transfer</span>
              </Button>
            )}
          </>
        )}
      </div>
    </div>
  );
};
