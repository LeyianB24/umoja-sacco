<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$consumer_key = 'YOUR_REAL_SANDBOX_KEY';
$consumer_secret = 'YOUR_REAL_SANDBOX_SECRET';
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . base64_encode($consumer_key . ':' . $consumer_secret)
]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);
if ($response === false) {
    echo 'cURL Error: ' . curl_error($curl);
} else {
    echo $response;
}
curl_close($curl);