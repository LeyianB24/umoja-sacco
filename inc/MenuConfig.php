<?php
/**
 * inc/MenuConfig.php
 * Centralized Menu Configuration for Admin and Member Portals
 */

class MenuConfig {
    public static function getAdminMenu() {
        return [
            'Members' => [
                ['label' => 'Registry', 'icon' => 'bi-people-fill', 'slug' => 'members.php'],
                ['label' => 'Onboarding', 'icon' => 'bi-person-plus-fill', 'slug' => 'member_onboarding.php'],
            ],
            'Loans' => [
                ['label' => 'Management', 'icon' => 'bi-bank2', 'slug' => 'loans.php'],
                ['label' => 'Reviews', 'icon' => 'bi-check2-square', 'slug' => 'loans.php'],
                ['label' => 'Payouts', 'icon' => 'bi-cash-stack', 'slug' => 'loans.php'],
            ],
            'Finance' => [
                ['label' => 'Revenue', 'icon' => 'bi-graph-up-arrow', 'slug' => 'revenue.php'],
                ['label' => 'Payments', 'icon' => 'bi-credit-card-2-front-fill', 'slug' => 'payments.php'],
                ['label' => 'Expenses', 'icon' => 'bi-receipt', 'slug' => 'expenses.php'],
                ['label' => 'Investments', 'icon' => 'bi-building-up', 'slug' => 'investments.php'],
            ],
            'Operations' => [
                ['label' => 'Welfare Cases', 'icon' => 'bi-heart-pulse-fill', 'slug' => 'welfare_cases.php'],
                ['label' => 'Welfare Payouts', 'icon' => 'bi-shield-check', 'slug' => 'welfare_support.php'],
                ['label' => 'Analytics', 'icon' => 'bi-calculator', 'slug' => 'reports.php'],
                ['label' => 'Statements', 'icon' => 'bi-file-earmark-spreadsheet', 'slug' => 'statements.php'],
            ],
            'System' => [
                ['label' => 'Employees', 'icon' => 'bi-person-badge-fill', 'slug' => 'employees.php'],
                ['label' => 'Staff Admin', 'icon' => 'bi-people-fill', 'slug' => 'staff_mgmt.php'],
                ['label' => 'Permissions', 'icon' => 'bi-shield-lock-fill', 'slug' => 'roles.php'],
                ['label' => 'IT Help Desk', 'icon' => 'bi-headset', 'slug' => 'support.php'],
                ['label' => 'Settings', 'icon' => 'bi-gear-fill', 'slug' => 'settings.php'],
                ['label' => 'Backups', 'icon' => 'bi-cloud-arrow-down-fill', 'slug' => 'backups.php'],
                ['label' => 'Audit Trail', 'icon' => 'bi-activity', 'slug' => 'audit_logs.php'],
            ]
        ];
    }
    
    public static function getMemberMenu() {
        // Members typically have a flat list, but we can structure it if needed.
        // Based on existing sidebar code:
        return [
            ['label' => 'Dashboard', 'icon' => 'bi-grid-fill', 'slug' => 'dashboard.php'],
            ['label' => 'Pay via M-Pesa', 'icon' => 'bi-phone-vibrate-fill', 'slug' => 'mpesa_request.php'],
            ['label' => 'Withdraw Funds', 'icon' => 'bi-wallet2', 'slug' => 'withdraw.php'],
            
            'Finances' => [
                ['label' => 'My Savings', 'icon' => 'bi-piggy-bank', 'slug' => 'savings.php'],
                ['label' => 'My Loans', 'icon' => 'bi-cash-stack', 'slug' => 'loans.php'],
                ['label' => 'Shares', 'icon' => 'bi-pie-chart-fill', 'slug' => 'shares.php'],
                ['label' => 'Apply Loan', 'icon' => 'bi-plus-circle-fill', 'slug' => 'apply_loan.php'],
                ['label' => 'Repayment', 'icon' => 'bi-calendar-check-fill', 'slug' => 'repay_loan.php'],
            ],
            
            'Welfare' => [
                ['label' => 'Welfare Cases', 'icon' => 'bi-heart-pulse-fill', 'slug' => 'welfare_situations.php'],
                ['label' => 'Welfare Fund', 'icon' => 'bi-shield-check', 'slug' => 'welfare.php'],
            ],
            
            'History' => [
                ['label' => 'Transactions', 'icon' => 'bi-arrow-left-right', 'slug' => 'transactions.php'],
                ['label' => 'Contributions', 'icon' => 'bi-journal-text', 'slug' => 'contributions.php'],
            ],
            
            'Account' => [
                ['label' => 'My Profile', 'icon' => 'bi-person-circle', 'slug' => 'profile.php'],
                ['label' => 'Settings', 'icon' => 'bi-gear-wide-connected', 'slug' => 'settings.php'],
                ['label' => 'Notifications', 'icon' => 'bi-bell', 'slug' => 'notifications.php'],
                ['label' => 'Support Center', 'icon' => 'bi-headset', 'slug' => 'support.php'],
            ]
        ];
    }
}
?>


