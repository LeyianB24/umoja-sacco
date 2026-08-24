-- Umoja Drivers SACCO V7 - General Ledger Logic
-- Source of Truth: `transactions` table

-- 1. TOTAL INFLOW (Revenue, Savings, Shares, Repayments)
-- This query sums all positive movements into the SACCO's central pool.
SELECT 
    SUM(amount) as total_inflow
FROM transactions
WHERE transaction_type IN (
    'deposit',          -- Member Savings
    'share_capital',    -- Member Equity
    'income',           -- General Revenue
    'loan_repayment',   -- Principal + Interest Return
    'registration_fee', -- Mandatory Activation Fee
    'fine',             -- Penalty Income
    'welfare'          -- Benevolent Fund Deposits
);

-- 2. TOTAL OUTFLOW (Loans Issued, Expenses, Payouts)
-- This query sums all movements out of the SACCO pool.
SELECT 
    SUM(amount) as total_outflow
FROM transactions
WHERE transaction_type IN (
    'loan_disbursement', -- Asset creation (Money leaves bank to member)
    'withdrawal',        -- Liability reduction (Member takes their savings)
    'expense',           -- Operational costs
    'welfare_payout',    -- Benefit distribution
    'dividend_payout'    -- Profit distribution
);

-- 3. NET SACCO POSITION (Cash at Hand/Bank)
-- The literal cash available in the Sacco Account.
SELECT 
    (
        SELECT SUM(amount) FROM transactions 
        WHERE transaction_type IN ('deposit', 'share_capital', 'income', 'loan_repayment', 'registration_fee', 'fine', 'welfare')
    ) - (
        SELECT SUM(amount) FROM transactions 
        WHERE transaction_type IN ('loan_disbursement', 'withdrawal', 'expense', 'welfare_payout', 'dividend_payout')
    ) as net_cash_position;

-- 4. TOTAL ASSETS
-- Cash Position + Outstanding Loans
SELECT 
    (SELECT COALESCE(SUM(current_balance), 0) FROM loans WHERE status = 'disbursed') +
    (
        (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE transaction_type IN ('deposit', 'share_capital', 'income', 'loan_repayment', 'registration_fee', 'fine', 'welfare')) -
        (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE transaction_type IN ('loan_disbursement', 'withdrawal', 'expense', 'welfare_payout', 'dividend_payout'))
    ) as total_assets;
