<?php
require_once __DIR__ . '/config/db_connect.php';

echo "=== Investment Target System Schema Update ===\n\n";

// Add target_amount column
$sql1 = "ALTER TABLE investments ADD COLUMN target_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Expected revenue target'";
if ($conn->query($sql1)) {
    echo "✓ Added target_amount column\n";
} else {
    echo "  target_amount column may already exist\n";
}

// Add target_period column
$sql2 = "ALTER TABLE investments ADD COLUMN target_period ENUM('daily', 'monthly', 'annually') DEFAULT 'monthly' COMMENT 'Target evaluation period'";
if ($conn->query($sql2)) {
    echo "✓ Added target_period column\n";
} else {
    echo "  target_period column may already exist\n";
}

// Add target_start_date column
$sql3 = "ALTER TABLE investments ADD COLUMN target_start_date DATE DEFAULT NULL COMMENT 'When target tracking begins'";
if ($conn->query($sql3)) {
    echo "✓ Added target_start_date column\n";
} else {
    echo "  target_start_date column may already exist\n";
}

// Add viability_status column
$sql4 = "ALTER TABLE investments ADD COLUMN viability_status ENUM('viable', 'underperforming', 'loss_making', 'pending') DEFAULT 'pending' COMMENT 'Auto-calculated economic status'";
if ($conn->query($sql4)) {
    echo "✓ Added viability_status column\n";
} else {
    echo "  viability_status column may already exist\n";
}

// Add last_viability_check column
$sql5 = "ALTER TABLE investments ADD COLUMN last_viability_check DATETIME DEFAULT NULL COMMENT 'Last time viability was calculated'";
if ($conn->query($sql5)) {
    echo "✓ Added last_viability_check column\n";
} else {
    echo "  last_viability_check column may already exist\n";
}

// Create indexes
$idx1 = "CREATE INDEX idx_investment_viability ON investments(viability_status, status)";
if ($conn->query($idx1)) {
    echo "✓ Created viability index\n";
} else {
    echo "  Viability index may already exist\n";
}

$idx2 = "CREATE INDEX idx_investment_targets ON investments(target_period, target_start_date)";
if ($conn->query($idx2)) {
    echo "✓ Created targets index\n";
} else {
    echo "  Targets index may already exist\n";
}

// Update existing records
$update = "UPDATE investments SET viability_status = 'pending' WHERE viability_status IS NULL OR viability_status = ''";
$conn->query($update);
echo "✓ Updated existing investments to pending status\n";

echo "\n=== Schema Update Complete ===\n";
?>
