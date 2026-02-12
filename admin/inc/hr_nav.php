<?php
/**
 * admin/inc/hr_nav.php
 * Unified HR Navigation component
 */

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-0">
                <div class="d-flex align-items-center bg-white">
                    <a href="employees.php" class="flex-fill text-center py-3 px-4 text-decoration-none border-bottom border-4 <?= $current_page == 'employees.php' ? 'border-success text-success fw-bold' : 'border-transparent text-muted' ?> hover-bg-light transition-all">
                        <i class="bi bi-people-fill me-2"></i> Employee Directory
                    </a>
                    <a href="staff_mgmt.php" class="flex-fill text-center py-3 px-4 text-decoration-none border-bottom border-4 <?= $current_page == 'staff_mgmt.php' ? 'border-success text-success fw-bold' : 'border-transparent text-muted' ?> hover-bg-light transition-all">
                        <i class="bi bi-shield-lock-fill me-2"></i> Admin Access
                    </a>
                    <a href="payroll.php" class="flex-fill text-center py-3 px-4 text-decoration-none border-bottom border-4 <?= $current_page == 'payroll.php' ? 'border-success text-success fw-bold' : 'border-transparent text-muted' ?> hover-bg-light transition-all">
                        <i class="bi bi-cash-stack me-2"></i> Payroll Hub
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .border-transparent { border-bottom-color: transparent !important; }
    .hover-bg-light:hover { background-color: #f8f9fa; }
    .transition-all { transition: all 0.2s ease-in-out; }
    .fw-800 { font-weight: 800; }
</style>
