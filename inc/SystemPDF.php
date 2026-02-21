<?php
declare(strict_types=1);
/**
 * inc/SystemPDF.php
 * BACKWARDS COMPATIBILITY STUB
 *
 * SystemPDF has been migrated to USMS\Reports\SystemPDF (core/Reports/SystemPDF.php).
 * This stub provides a global alias so legacy consumers don't break.
 * @deprecated Use USMS\Reports\SystemPDF directly.
 */

// Autoloader will handle the real class resolving
if (!class_exists('SystemPDF')) {
    class_alias(\USMS\Reports\SystemPDF::class, 'SystemPDF');
}
