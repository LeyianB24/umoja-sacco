<?php
require_once 'config/app.php';
$config = require 'config/environment.php';
$mpesa = $config['mpesa'];

$url = $mpesa['base_url'] . '/oauth/v1/generate?grant_type=client_credentials';
echo "Requesting URL: $url\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($mpesa['consumer_key'] . ':' . $mpesa['consumer_secret'])],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_HEADER => true, // Include headers in output
]);

$resp = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Status: " . $info['http_code'] . "\n";
echo "Response:\n" . $resp . "\n";
?>
