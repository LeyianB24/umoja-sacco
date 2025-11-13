<?php
$config = require_once __DIR__ . '/../config/mpesa_config.php';

// select environment
$base_url = $config['environment'] === 'live' ? $config['live_url'] : $config['sandbox_url'];

$consumerKey = $config['consumer_key'];
$consumerSecret = $config['consumer_secret'];
$BusinessShortCode = $config['shortcode'];
$Passkey = $config['passkey'];
$callbackUrl = $config['callback_url'];

// member/mpesa_payment.php
session_start();
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

$member_id = (int) $_SESSION['member_id'];
$amount = $_POST['amount'] ?? 0;
$phone = $_POST['phone'] ?? '';

if ($amount <= 0 || empty($phone)) {
    die("Invalid payment details.");
}

// === M-PESA DARAJA CONFIG ===
$consumerKey = "YOUR_CONSUMER_KEY";
$consumerSecret = "YOUR_CONSUMER_SECRET";
$BusinessShortCode = "174379";
$Passkey = "YOUR_PASSKEY";
$callbackUrl = BASE_URL . "/member/mpesa_callback.php";
$timestamp = date("YmdHis");
$password = base64_encode($BusinessShortCode . $Passkey . $timestamp);

// === Get Access Token ===
// Get Access Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $base_url . "/oauth/v1/generate?grant_type=client_credentials");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response);
$access_token = $result->access_token ?? '';

if (!$access_token) {
    die("Failed to get M-Pesa token.");
}

// === Initiate STK Push ===
$stk_data = [
    "BusinessShortCode" => $BusinessShortCode,
    "Password" => $password,
    "Timestamp" => $timestamp,
    "TransactionType" => "CustomerPayBillOnline",
    "Amount" => $amount,
    "PartyA" => $phone,
    "PartyB" => $BusinessShortCode,
    "PhoneNumber" => $phone,
    "CallBackURL" => $callbackUrl,
    "AccountReference" => "UmojaDriversSacco",
    "TransactionDesc" => "Member Contribution Payment"
];

$ch = curl_init($base_url . "/mpesa/stkpush/v1/processrequest");

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $access_token"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>
