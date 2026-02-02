-- Update support_tickets table to include category
ALTER TABLE support_tickets 
ADD COLUMN category ENUM('loan_issue', 'payment_issue', 'technical', 'general') DEFAULT 'general' AFTER message;

-- Index for faster filtering
CREATE INDEX idx_support_category ON support_tickets(category);
