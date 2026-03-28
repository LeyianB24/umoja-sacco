<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/Auth.php';

// Security Check
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

$file = $_GET['file'] ?? '';

// Path Traversal Protection: Only allow filenames, no slashes
if (strpos($file, '/') !== false || strpos($file, '\\') !== false || strpos($file, '..') !== false) {
    die("Invalid filename.");
}

// Only allow .sql files
if (!str_ends_with(strtolower($file), '.sql')) {
    die("Invalid file type.");
}

$filepath = BASE_PATH . '/backups/' . $file;

if (!file_exists($filepath)) {
    die("File not found.");
}

// Stream the file
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . basename($filepath) . "\"");
readfile($filepath);
exit;
