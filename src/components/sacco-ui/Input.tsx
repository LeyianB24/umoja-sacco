import React, { InputHTMLAttributes, forwardRef } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  helperText?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, helperText, required, className = '', id, ...props }, ref) => {
    const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

    return (
      <div style={{ display: 'flex', flexDirection: 'column', width: '100%' }}>
        {label && (
          <label htmlFor={inputId} className="sacco-input-label">
            {label}
            {required && <span style={{ color: 'var(--color-error)', marginLeft: '4px' }}>*</span>}
          </label>
        )}
        <input
          ref={ref}
          id={inputId}
          className={`sacco-input-field ${error ? 'error' : ''} ${className}`.trim()}
          required={required}
          {...props}
        />
        {error && (
          <span style={{ fontSize: '12px', color: 'var(--color-error)', marginTop: '4px', lineHeight: '16px' }}>
            {error}
          </span>
        )}
        {!error && helperText && (
          <span style={{ fontSize: '12px', color: 'var(--color-gray-medium)', marginTop: '4px', lineHeight: '16px' }}>
            {helperText}
          </span>
        )}
      </div>
    );
  }
);

Input.displayName = 'Input';
