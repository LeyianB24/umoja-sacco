import React, { ReactNode, HTMLAttributes } from 'react';

export interface ListItemProps extends HTMLAttributes<HTMLDivElement> {
  icon?: ReactNode;
  label: ReactNode;
  time?: ReactNode;
  amount?: string | number;
  type?: 'credit' | 'debit' | 'neutral';
  badge?: ReactNode;
  onClick?: () => void;
}

export const ListItem: React.FC<ListItemProps> = ({
  icon,
  label,
  time,
  amount,
  type = 'neutral',
  badge,
  onClick,
  className = '',
  style,
  ...props
}) => {
  const isCredit = type === 'credit';
  const isDebit = type === 'debit';

  return (
    <div
      onClick={onClick}
      style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '12px 14px',
        borderRadius: 'var(--radius-lg)',
        backgroundColor: 'var(--color-white)',
        border: '1px solid var(--color-gray-border)',
        cursor: onClick ? 'pointer' : 'default',
        transition: 'all 0.15s ease',
        ...style,
      }}
      className={className}
      {...props}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: '12px', minWidth: 0 }}>
        {icon && (
          <div
            style={{
              width: '40px',
              height: '40px',
              borderRadius: '50%',
              backgroundColor: 'var(--color-gray-light)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
              color: 'var(--color-charcoal)',
            }}
          >
            {icon}
          </div>
        )}
        <div style={{ minWidth: 0 }}>
          <div
            style={{
              fontSize: '14px',
              fontWeight: 600,
              color: 'var(--color-charcoal)',
              whiteSpace: 'nowrap',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
            }}
          >
            {label}
          </div>
          {time && (
            <div style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '2px' }}>
              {time}
            </div>
          )}
        </div>
      </div>

      <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexShrink: 0 }}>
        {badge}
        {amount !== undefined && (
          <div
            style={{
              fontSize: '15px',
              fontWeight: 600,
              color: isCredit ? '#16a34a' : isDebit ? 'var(--color-charcoal)' : 'var(--color-charcoal)',
            }}
          >
            {amount}
          </div>
        )}
      </div>
    </div>
  );
};
