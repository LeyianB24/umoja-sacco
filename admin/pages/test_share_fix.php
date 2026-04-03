<?php
require 'c:/xampp/htdocs/usms/config/app.php';
require_once 'c:/xampp/htdocs/usms/inc/ShareValuationEngine.php';

$engine = new ShareValuationEngine($conn);

echo "--- SHARE LOGIC TEST ---\n";

try {
    $valuation = $engine->getValuation();
    echo "Current Authorized Units: " . $valuation['total_authorized_units'] . "\n";
    echo "Current Issued Units: " . $valuation['total_units'] . "\n";
    
    $excessAmount = ($valuation['total_authorized_units'] + 100) * $valuation['price'];
    echo "Attempting to issue shares worth KES " . number_format($excessAmount, 2) . " (Should Fail)\n";
    
    $engine->issueShares(2, $excessAmount, 'TEST-EXCESS');
    echo "FAIL: Issued shares exceeding authorized limit without exception.\n";
} catch (Exception $e) {
    echo "SUCCESS: Caught expected exception: " . $e->getMessage() . "\n";
}

try {
    $smallAmount = 1000.00;
    echo "Attempting to issue shares worth KES " . number_format($smallAmount, 2) . " (Should Succeed if under limit)\n";
    $engine->issueShares(2, $smallAmount, 'TEST-OK-' . time());
    echo "SUCCESS: Issued valid shares.\n";
    
    $newValuation = $engine->getValuation();
    echo "New Issued Units: " . $newValuation['total_units'] . "\n";
} catch (Exception $e) {
    echo "INFO: Could not issue small shares (maybe already at limit?): " . $e->getMessage() . "\n";
}
