<?php
// inc/ExportHelper.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../core/exports/ExportManager.php';

class ExportHelper {
    
    /**
     * Generate Branded PDF from Data Array
     * Delegates to the central ExportManager.
     */
    public static function pdf($title, $headers, $data, $filename = 'report.pdf') {
        // Map to ExportManager
        ExportManager::export('pdf', $data, [
            'title' => $title,
            'module' => 'Standard Export',
            'headers' => $headers
        ]);
    }
    
    /**
     * Generate Excel/CSV
     * Upgrades legacy CSV calls to the new Excel engine.
     */
    public static function csv($filename, $headers, $data) {
        $title = str_replace(['.csv', '.xls', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME));
        
        ExportManager::export('excel', $data, [
            'title' => $title,
            'module' => 'Standard Export',
            'headers' => $headers
        ]);
    }

    public static function exportToCSV($filename, $headers, $data) {
        self::csv($filename, $headers, $data);
    }
}
?>
