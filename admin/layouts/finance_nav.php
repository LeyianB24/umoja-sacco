<?php
// inc/finance_nav.php
// Unified Navigation for the Finance Suite

$current_page = basename($_SERVER['PHP_SELF']);

$nav_items = [
    ['icon' => 'bi-pie-chart', 'label' => 'Analytics', 'url' => 'reports.php'],
    ['icon' => 'bi-graph-up', 'label' => 'Revenue', 'url' => 'revenue.php'],
    ['icon' => 'bi-receipt', 'label' => 'Expenses', 'url' => 'expenses.php'],
    ['icon' => 'bi-credit-card', 'label' => 'Payments', 'url' => 'payments.php'],
    ['icon' => 'bi-journal-text', 'label' => 'Ledger', 'url' => 'transactions.php'],
    ['icon' => 'bi-file-earmark-spreadsheet', 'label' => 'Statements', 'url' => 'statements.php'],
    ['icon' => 'bi-calculator', 'label' => 'Trial Balance', 'url' => 'trial_balance.php'],
];
?>

<div class="row g-2 mb-5 no-print">
    <?php foreach ($nav_items as $item): 
        $active = ($current_page === $item['url']) ? 'active-finance' : '';
    ?>
    <div class="col">
        <a href="<?= $item['url'] ?>" class="finance-nav-item <?= $active ?>">
            <i class="bi <?= $item['icon'] ?>"></i>
            <span><?= $item['label'] ?></span>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<style>
    .finance-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 15px 10px;
        background: white;
        border-radius: 20px;
        text-decoration: none;
        color: #64748b;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 1px solid rgba(15, 46, 37, 0.05);
        transition: 0.3s;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    }
    .finance-nav-item i {
        font-size: 1.5rem;
        margin-bottom: 8px;
        color: #0F2E25;
        opacity: 0.6;
    }
    .finance-nav-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(15, 46, 37, 0.08);
        color: #0F2E25;
    }
    .finance-nav-item:hover i { opacity: 1; color: #1a4d3e; }

    .finance-nav-item.active-finance {
        background: #0F2E25;
        color: white;
        border-color: #0F2E25;
    }
    .finance-nav-item.active-finance i {
        color: #D0F35D;
        opacity: 1;
    }
</style>
