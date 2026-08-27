import React, { ReactNode, ButtonHTMLAttributes } from 'react';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'tertiary' | 'danger' | 'success';
  size?: 'sm' | 'md' | 'lg';
  pill?: boolean;
  iconOnly?: boolean;
  children: ReactNode;
}

export const Button: React.FC<ButtonProps> = ({
  variant = 'primary',
  size = 'md',
  pill = false,
  iconOnly = false,
  className = '',
  children,
  disabled,
  ...props
}) => {
  const variantClass = `btn-sacco-${variant}`;
  const sizeClass = `btn-sacco-${size}`;
  const pillClass = pill ? 'btn-sacco-pill' : '';
  const iconClass = iconOnly ? (size === 'lg' ? 'btn-sacco-icon-lg' : 'btn-sacco-icon') : '';
  const disabledClass = disabled ? 'btn-sacco-disabled' : '';

  return (
    <button
      className={`btn-sacco ${variantClass} ${sizeClass} ${pillClass} ${iconClass} ${disabledClass} ${className}`.trim()}
      disabled={disabled}
      {...props}
    >
      {children}
    </button>
  );
};
