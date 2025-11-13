<?php
// usms/inc/mpesa_lib.php
function mpesa_config() {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/mpesa_config.php';
    }
    return $config;
}


function mpesa_base_url() {
    $c = mpesa_config();
    return ($c['environment'] === 'live') ? $c['live_url'] : $c['sandbox_url'];
}

function mpesa_get_access_token($conn = null) {
    $c = mpesa_config();
    $url = mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
    $ck = $c['consumer_key'];
    $cs = $c['consumer_secret'];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($ck . ':' . $cs)
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error getting token: $err");
    }
    curl_close($ch);
    $j = json_decode($resp, true);
    if (!isset($j['access_token'])) throw new Exception("No access token in response: $resp");
    return $j['access_token'];
}
function mpesa_initiate_stk_push($phone, $amount, $conn = null) {
    $c = mpesa_config();
    $base_url = mpesa_base_url();

    $BusinessShortCode = $c['shortcode'];
    $Passkey = $c['passkey'];
    $callbackUrl = $c['callback_url'];
    $timestamp = date("YmdHis");
    $password = base64_encode($BusinessShortCode . $Passkey . $timestamp);

    $access_token = mpesa_get_access_token($conn);

    $stk_data = [
        "BusinessShortCode" => $BusinessShortCode,
        "Password" => $password,
        "Timestamp" => $timestamp,
        "TransactionType" => "CustomerPayBillOnline",
        "Amount" => $amount,
        "PartyA" => preg_replace('/^0/', '254', $phone),
        "PartyB" => $BusinessShortCode,
        "PhoneNumber" => preg_replace('/^0/', '254', $phone),
        "CallBackURL" => $callbackUrl,
        "AccountReference" => "USMS Payment",
        "TransactionDesc" => "Payment of membership fees"
    ];

    $ch = curl_init($base_url . "/mpesa/stkpush/v1/processrequest");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stk_data));

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error initiating STK Push: $err");
    }
    curl_close($ch);
    return json_decode($response, true);
}