import React, { ReactNode, HTMLAttributes } from 'react';

export interface CardProps extends HTMLAttributes<HTMLDivElement> {
  variant?: 'default' | 'elevated' | 'filled' | 'outlined';
  children: ReactNode;
}

export const Card: React.FC<CardProps> & {
  Header: React.FC<HTMLAttributes<HTMLDivElement>>;
  Body: React.FC<HTMLAttributes<HTMLDivElement>>;
  Footer: React.FC<HTMLAttributes<HTMLDivElement>>;
} = ({
  variant = 'default',
  className = '',
  children,
  ...props
}) => {
  const variantClass = `card-sacco-${variant}`;

  return (
    <div className={`${variantClass} ${className}`.trim()} {...props}>
      {children}
    </div>
  );
};

const CardHeader: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className = '', children, ...props }) => (
  <div
    style={{
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: '12px',
    }}
    className={className}
    {...props}
  >
    {children}
  </div>
);

const CardBody: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className = '', children, ...props }) => (
  <div className={className} {...props}>
    {children}
  </div>
);

const CardFooter: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className = '', children, ...props }) => (
  <div
    style={{
      marginTop: '16px',
      paddingTop: '12px',
      borderTop: '1px solid var(--color-gray-border)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
    }}
    className={className}
    {...props}
  >
    {children}
  </div>
);

Card.Header = CardHeader;
Card.Body = CardBody;
Card.Footer = CardFooter;
