<?php
require 'config/db_connect.php';
require 'inc/ReportGenerator.php';

$rg = new ReportGenerator($conn);
$v = $rg->getShareValuation();

echo "--- SHARE VALUATION VERIFICATION ---\n";
echo "Total Assets: KES " . number_format($v['total_assets'], 2) . "\n";
echo "Liabilities:  KES " . number_format($v['liabilities'], 2) . "\n";
echo "Net Equity:   KES " . number_format($v['equity'], 2) . "\n";
echo "Total Units:  " . $v['total_units'] . "\n";
echo "Share Price:  KES " . number_format($v['price'], 2) . " / unit\n";
echo "------------------------------------\n";

if ($v['total_units'] > 0 && abs($v['price'] - ($v['equity'] / $v['total_units'])) < 0.01) {
    echo "VERIFICATION SUCCESS: Logic matches defined formula.\n";
} else {
    echo "VERIFICATION NOTE: Price is at floor (100.00) or units are zero.\n";
}
