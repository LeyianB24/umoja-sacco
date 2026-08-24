<?php
require 'c:/xampp/htdocs/usms/config/app.php';
require_once 'c:/xampp/htdocs/usms/core/Services/DividendService.php';

use USMS\Services\DividendService;

$ds = new DividendService();

echo "--- PRO-RATA DIVIDEND TEST ---\n";

$year = date('Y');
$startDate = "$year-01-01";
$endDate   = "$year-12-31";

// 1. Test Weighted Calculation for Member 2 (Existing member)
$weight2 = $ds->calculateWeightedUnits(2, $startDate, $endDate);
echo "Member 2 Weighted Units: " . number_format($weight2, 4) . "\n";

// 2. Test pool distribution (Dry Run)
try {
    $pool = 100000.00;
    echo "Testing Distribution of KES " . number_format($pool, 2) . " pool...\n";
    
    // We'll use a transaction to not actually persist if we want, 
    // but the service starts its own transaction. 
    // I'll just run it and we'll check the dividend_payouts table.
    
    $res = $ds->distributeFromPool($pool, $year, 1);
    echo "Distribution record created: Period ID " . $res['period_id'] . " with " . $res['count'] . " payouts.\n";
    
    // Verify One Payout
    $stmt = $conn->prepare("SELECT * FROM dividend_payouts WHERE period_id = ? LIMIT 1");
    $stmt->bind_param("i", $res['period_id']);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    
    if ($p) {
        echo "Sample Payout for Member " . $p['member_id'] . ":\n";
        echo "  Weighted Units: " . $p['weighted_units'] . "\n";
        echo "  Gross: KES " . $p['gross_amount'] . "\n";
        echo "  WHT (5%): KES " . $p['wht_tax'] . "\n";
        echo "  Net: KES " . $p['net_amount'] . "\n";
        
        $expectedWht = round($p['gross_amount'] * 0.05, 2);
        if (abs((float)$p['wht_tax'] - $expectedWht) < 0.01) {
            echo "SUCCESS: WHT calculation is accurate.\n";
        } else {
            echo "FAIL: WHT calculation mismatch. Expected $expectedWht, got " . $p['wht_tax'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
