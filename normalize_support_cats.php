<?php
require_once __DIR__ . '/config/app.php';

$updates = [
    'loan' => 'loans',
    'withdrawal' => 'withdrawals',
    'saving' => 'savings',
    'share' => 'shares',
    'investment' => 'investments',
    'account' => 'profile',
    'accounts' => 'profile'
];

foreach ($updates as $old => $new) {
    $stmt = $conn->prepare("UPDATE support_tickets SET category = ? WHERE category = ?");
    $stmt->bind_param("ss", $new, $old);
    if ($stmt->execute()) {
        if ($conn->affected_rows > 0) {
            echo "Updated '$old' -> '$new' (" . $conn->affected_rows . " rows)\n";
        }
    }
}

echo "\nRemaining distinct categories in DB:\n";
$res = $conn->query("SELECT DISTINCT category FROM support_tickets");
while($row = $res->fetch_assoc()) {
    echo "- " . $row['category'] . "\n";
}
