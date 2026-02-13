<?php
// member/welfare_situations.php
// Redirecting to the unified Welfare Hub (welfare.php)
require_once __DIR__ . '/../../config/app_config.php';
header("Location: " . BASE_URL . "/member/pages/welfare.php?tab=community");
exit;
?>
