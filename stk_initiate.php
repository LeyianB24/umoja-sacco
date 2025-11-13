<?php
// stk_initiate.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/inc/mpesa_lib.php';

ob_start(); // Start output buffering to catch stray output

try {
    $phone = trim($_POST['phone'] ?? '');
    $amount = trim($_POST['amount'] ?? '');

    if ($phone === '' || $amount === '') {
        throw new Exception("Missing phone or amount.");
    }

    // Perform STK Push
    $response = mpesa_initiate_stk_push($phone, $amount);

    // Clear any unwanted buffered output before responding
    ob_end_clean();

    echo json_encode([
        "success" => true,
        "response" => $response
    ]);
    exit;

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
    exit;
}