<?php
/**
 * core/exports/UniversalExportEngine.php
 * BACKWARDS COMPATIBILITY STUB
 *
 * This class has been migrated to USMS\Services\UniversalExportEngine.
 * This stub provides a global alias for all legacy consumers.
 * @deprecated Use USMS\Services\UniversalExportEngine directly.
 */

// Autoloader will handle the real class resolving
if (!class_exists('UniversalExportEngine')) {
    class_alias(\USMS\Services\UniversalExportEngine::class, 'UniversalExportEngine');
}
