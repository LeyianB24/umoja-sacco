<?php
require_once __DIR__ . '/../inc/auth.php';
logout_and_redirect(BASE_URL . '/public/login.php');

// usms/public/logout.php
session_start();

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/db_connect.php';

$cookie_name = 'usms_rem';
$token = $_COOKIE[$cookie_name] ?? '';

// remove DB token if present (hash)
if ($token) {
    $token_hash = hash('sha256', $token);
    $sqls = [
        "UPDATE members SET remember_token = NULL, remember_expires = NULL, remember_ua = NULL WHERE remember_token = ?",
        "UPDATE admins  SET remember_token = NULL, remember_expires = NULL, remember_ua = NULL WHERE remember_token = ?"
    ];
    foreach ($sqls as $s) {
        $st = $conn->prepare($s);
        if ($st) {
            $st->bind_param('s', $token_hash);
            $st->execute();
            $st->close();
        }
    }
    // clear cookie
    setcookie($cookie_name, '', time()-3600, '/', '', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
    unset($_COOKIE[$cookie_name]);
}

// Destroy session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// redirect to public login or index
header("Location: ../public/index.php");
exit;
