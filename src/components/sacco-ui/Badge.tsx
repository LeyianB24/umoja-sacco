import React, { ReactNode, HTMLAttributes } from 'react';

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: 'success' | 'warning' | 'error' | 'info' | 'neutral';
  status?: 'approved' | 'pending' | 'rejected' | 'active' | 'closed' | 'disbursed';
  children?: ReactNode;
}

export const Badge: React.FC<BadgeProps> = ({
  variant,
  status,
  className = '',
  children,
  ...props
}) => {
  // Map status to variant if status provided
  let computedVariant: 'success' | 'warning' | 'error' | 'info' | 'neutral' = variant || 'neutral';

  if (status) {
    if (['approved', 'active', 'disbursed', 'completed'].includes(status.toLowerCase())) {
      computedVariant = 'success';
    } else if (['pending', 'review', 'in_progress'].includes(status.toLowerCase())) {
      computedVariant = 'warning';
    } else if (['rejected', 'failed', 'defaulted'].includes(status.toLowerCase())) {
      computedVariant = 'error';
    } else if (['closed'].includes(status.toLowerCase())) {
      computedVariant = 'neutral';
    }
  }

  const variantClass = `sacco-badge-${computedVariant}`;
  const displayText = children || (status ? status.charAt(0).toUpperCase() + status.slice(1) : '');

  return (
    <span className={`sacco-badge ${variantClass} ${className}`.trim()} {...props}>
      {displayText}
    </span>
  );
};
