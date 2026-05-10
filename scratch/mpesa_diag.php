<?php
require_once 'config/app.php';
$config = require 'config/environment.php';

echo "APP_ENV: " . APP_ENV . "\n";
echo "Mpesa Env: " . (defined('MPESA_ENV') ? MPESA_ENV : 'unknown') . "\n";

$mpesa = $config['mpesa'];
echo "Base URL: " . $mpesa['base_url'] . "\n";
echo "Consumer Key Length: " . strlen($mpesa['consumer_key']) . "\n";
echo "Consumer Secret Length: " . strlen($mpesa['consumer_secret']) . "\n";
echo "Shortcode: " . $mpesa['shortcode'] . "\n";
echo "Passkey Length: " . strlen($mpesa['passkey']) . "\n";

if (strlen($mpesa['consumer_key']) < 5) {
    echo "WARNING: Consumer Key is too short or empty!\n";
}
if (strlen($mpesa['consumer_secret']) < 5) {
    echo "WARNING: Consumer Secret is too short or empty!\n";
}
?>
