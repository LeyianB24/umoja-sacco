<?php
declare(strict_types=1);
/**
 * core/Middleware/SecureUpload.php
 * USMS\Middleware\SecureUpload — Safe file upload handler.
 *
 * Enforces allow-list validation, moves files outside the webroot
 * (or to a safe subfolder), generates unpredictable filenames,
 * and strips dangerous MIME types.
 *
 * Usage:
 *   $result = SecureUpload::handle(
 *       $_FILES['document'],
 *       'kyc',                    // subdirectory category
 *       ['pdf', 'jpg', 'png']     // allowed extensions
 *   );
 *
 *   if ($result['success']) {
 *       $savedPath = $result['path'];  // relative path for DB storage
 *   }
 */

namespace USMS\Middleware;

use RuntimeException;
use InvalidArgumentException;

class SecureUpload
{
    /**
     * Storage root — one level ABOVE the webroot for maximum safety.
     * Files here are NOT directly web-accessible.
     * Falls back to a `private_uploads/` directory inside the project if
     * the parent directory is not writable.
     */
    private static function getStorageRoot(): string
    {
        $aboveWebroot = dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/usms_uploads';

        if (@is_dir($aboveWebroot) || @mkdir($aboveWebroot, 0750, true)) {
            return rtrim($aboveWebroot, '/\\/');
        }

        // Fallback: protected folder inside project (htaccess blocks direct access)
        $fallback = dirname(__DIR__, 2) . '/private_uploads';
        if (!is_dir($fallback)) {
            mkdir($fallback, 0750, true);
        }
        return rtrim($fallback, '/\\/');
    }

    // ── MIME type allow-list ─────────────────────────────────────────────────
    private const ALLOWED_MIMES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
        'csv'  => 'text/csv',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        'txt'  => 'text/plain',
    ];

    // ── Max size per category (bytes) ─────────────────────────────────────────
    private const MAX_SIZES = [
        'kyc'     => 5  * 1024 * 1024,  // 5 MB  — ID docs
        'profile' => 2  * 1024 * 1024,  // 2 MB  — profile photos
        'reports' => 20 * 1024 * 1024,  // 20 MB — generated reports
        'default' => 8  * 1024 * 1024,  // 8 MB
    ];

    /**
     * Handle a single file upload.
     *
     * @param  array<string,mixed> $file       $_FILES['field'] entry
     * @param  string              $category   Storage subdirectory (e.g. 'kyc', 'profile')
     * @param  string[]            $allowed    Lowercase extensions to accept
     * @return array{ success: bool, path?: string, filename?: string, error?: string }
     */
    public static function handle(array $file, string $category = 'default', array $allowed = []): array
    {
        try {
            // ── 1. Upload error check ─────────────────────────────────────────
            if (!isset($file['error']) || is_array($file['error'])) {
                throw new RuntimeException('Invalid file upload parameter.');
            }

            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload directory missing.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'PHP extension blocked this upload.',
            ];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException($uploadErrors[$file['error']] ?? 'Unknown upload error.');
            }

            // ── 2. Validate the temp file is a real upload ────────────────────
            if (!is_uploaded_file($file['tmp_name'])) {
                throw new RuntimeException('Security: file was not uploaded via HTTP POST.');
            }

            // ── 3. Extension validation ───────────────────────────────────────
            $originalName = $file['name'];
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!empty($allowed) && !in_array($ext, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Extension '.{$ext}' not allowed. Accepted: " . implode(', ', $allowed)
                );
            }

            if (!array_key_exists($ext, self::ALLOWED_MIMES)) {
                throw new InvalidArgumentException("File type '.{$ext}' is not permitted.");
            }

            // ── 4. Real MIME check (finfo) ────────────────────────────────────
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($file['tmp_name']);
            $expected = self::ALLOWED_MIMES[$ext];

            // Allow text/plain for CSV variants
            $mimeOk = ($realMime === $expected)
                   || ($ext === 'csv' && in_array($realMime, ['text/plain', 'text/csv', 'application/csv'], true));

            if (!$mimeOk) {
                throw new RuntimeException("MIME mismatch: got '{$realMime}', expected '{$expected}'.");
            }

            // ── 5. File size ──────────────────────────────────────────────────
            $maxSize = self::MAX_SIZES[$category] ?? self::MAX_SIZES['default'];
            if ($file['size'] > $maxSize) {
                $mb = round($maxSize / 1024 / 1024, 1);
                throw new RuntimeException("File exceeds {$mb} MB size limit for '{$category}'.");
            }

            // ── 6. Generate safe filename ─────────────────────────────────────
            $safeName = self::generateFilename($category, $ext);

            // ── 7. Ensure target directory exists ─────────────────────────────
            $storageRoot = self::getStorageRoot();
            $targetDir   = $storageRoot . '/' . preg_replace('/[^a-z0-9_\-]/i', '', $category);

            if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true)) {
                throw new RuntimeException("Could not create upload directory: {$targetDir}");
            }

            // Drop a .htaccess in the folder just in case it ends up under webroot
            $htaccess = $targetDir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }

            $targetPath = $targetDir . '/' . $safeName;

            // ── 8. Move the file ──────────────────────────────────────────────
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new RuntimeException("Failed to move uploaded file to storage.");
            }

            // ── 9. Chmod to read-only ─────────────────────────────────────────
            chmod($targetPath, 0640);

            // Relative path for DB storage (relative to storage root)
            $relativePath = $category . '/' . $safeName;

            return [
                'success'  => true,
                'path'     => $relativePath,
                'filename' => $safeName,
                'size'     => $file['size'],
                'mime'     => $realMime,
            ];

        } catch (\Throwable $e) {
            error_log('[SecureUpload] Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Serve a stored file through PHP (controlled delivery).
     * Use this instead of direct URL access to private files.
     */
    public static function serve(string $relativePath): void
    {
        $fullPath = self::getStorageRoot() . '/' . ltrim($relativePath, '/');
        $realPath = realpath($fullPath);
        $root     = realpath(self::getStorageRoot());

        // Path traversal guard
        if ($realPath === false || $root === false || !str_starts_with($realPath, $root)) {
            http_response_code(403);
            die('Access denied.');
        }

        if (!file_exists($realPath)) {
            http_response_code(404);
            die('File not found.');
        }

        $ext  = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $mime = self::ALLOWED_MIMES[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($realPath));
        header('Content-Disposition: inline; filename="' . basename($realPath) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($realPath);
        exit;
    }

    /**
     * Delete a stored file.
     */
    public static function delete(string $relativePath): bool
    {
        $fullPath = self::getStorageRoot() . '/' . ltrim($relativePath, '/');
        $realPath = realpath($fullPath);
        $root     = realpath(self::getStorageRoot());

        if ($realPath && $root && str_starts_with($realPath, $root) && file_exists($realPath)) {
            return unlink($realPath);
        }
        return false;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function generateFilename(string $category, string $ext): string
    {
        $timestamp = date('Ymd_His');
        $random    = bin2hex(random_bytes(8));
        return "{$category}_{$timestamp}_{$random}.{$ext}";
    }
}
