'use client';

import React from 'react';

interface MetricCardProps {
  title: string;
  value: string | number;
  subtitle?: string;
  icon?: React.ReactNode;
  variant?: 'default' | 'forest' | 'lime' | 'gold';
  action?: React.ReactNode;
}

export function MetricCard({
  title,
  value,
  subtitle,
  icon,
  variant = 'default',
  action,
}: MetricCardProps) {
  let bg = 'var(--bg-surface)';
  let textColor = 'var(--text-main)';
  let titleColor = 'var(--text-muted)';
  let iconBg = 'var(--surface-2)';
  let iconColor = 'var(--brand-forest)';

  if (variant === 'forest') {
    bg = 'linear-gradient(135deg, #0B1E17 0%, #0F392B 100%)';
    textColor = '#FFFFFF';
    titleColor = 'rgba(255, 255, 255, 0.7)';
    iconBg = 'rgba(208, 247, 100, 0.15)';
    iconColor = 'var(--brand-lime)';
  } else if (variant === 'lime') {
    bg = 'linear-gradient(135deg, #D0F764 0%, #BEEB4B 100%)';
    textColor = '#0F392B';
    titleColor = 'rgba(15, 57, 43, 0.8)';
    iconBg = 'rgba(15, 57, 43, 0.1)';
    iconColor = '#0F392B';
  } else if (variant === 'gold') {
    bg = 'linear-gradient(135deg, #E2B34A 0%, #D4A438 100%)';
    textColor = '#FFFFFF';
    titleColor = 'rgba(255, 255, 255, 0.8)';
    iconBg = 'rgba(255, 255, 255, 0.2)';
    iconColor = '#FFFFFF';
  }

  return (
    <div
      style={{
        background: bg,
        borderRadius: '20px',
        padding: '22px 24px',
        border: variant === 'default' ? '1px solid var(--border-color)' : 'none',
        boxShadow: 'var(--shadow-sm)',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'space-between',
        position: 'relative',
        overflow: 'hidden',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '14px' }}>
        <span style={{ fontSize: '0.85rem', fontWeight: 600, color: titleColor }}>
          {title}
        </span>
        {icon && (
          <div
            style={{
              width: '42px',
              height: '42px',
              borderRadius: '12px',
              backgroundColor: iconBg,
              color: iconColor,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            {icon}
          </div>
        )}
      </div>
      <div>
        <div
          style={{
            fontSize: typeof value === 'string' && value.length > 12 ? '1.45rem' : '1.8rem',
            fontWeight: 800,
            color: textColor,
            letterSpacing: '-0.5px',
            marginBottom: '4px',
          }}
        >
          {value}
        </div>
        {subtitle && (
          <div style={{ fontSize: '0.8rem', color: titleColor, display: 'flex', alignItems: 'center', gap: '6px' }}>
            {subtitle}
          </div>
        )}
      </div>
      {action && <div style={{ marginTop: '14px' }}>{action}</div>}
    </div>
  );
}
