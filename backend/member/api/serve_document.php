<?php
/**
 * Serve KYC documents from database BLOB storage (Member side)
 * Usage: serve_document.php?id=<document_id>
 * 
 * Members can only view their own documents.
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/auth.php';

// Require member authentication
if (!isset($_SESSION['member_id'])) {
    http_response_code(403);
    echo "Unauthorized.";
    exit;
}

$member_id = $_SESSION['member_id'];
$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_id <= 0) {
    http_response_code(400);
    echo "Invalid document ID.";
    exit;
}

// Fetch document — only if it belongs to this member
$stmt = $conn->prepare("SELECT file_content, file_type, original_filename FROM member_documents WHERE document_id = ? AND member_id = ?");
$stmt->bind_param("ii", $doc_id, $member_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    echo "Document not found.";
    exit;
}

$stmt->bind_result($file_content, $file_type, $original_filename);
$stmt->fetch();
$stmt->close();

if (empty($file_content)) {
    http_response_code(404);
    echo "Document content not available.";
    exit;
}

// Serve inline
$content_type = $file_type ?: 'application/octet-stream';
$filename = $original_filename ?: 'document';

header("Content-Type: $content_type");
header("Content-Disposition: inline; filename=\"$filename\"");
header("Content-Length: " . strlen($file_content));
header("Cache-Control: private, max-age=3600");
header("X-Content-Type-Options: nosniff");

echo $file_content;
exit;
