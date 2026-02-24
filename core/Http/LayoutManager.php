<?php
declare(strict_types=1);

namespace USMS\Http;

/**
 * USMS\Http\LayoutManager
 * Handles module-specific layout loading with fallbacks.
 */
class LayoutManager {
    
    private string $context; // 'admin', 'member', 'public'
    private string $baseDir;

    public function __construct(string $context = 'public') {
        $this->context = $context;
        $this->baseDir = dirname(__DIR__, 2); // Root of the project
    }

    public static function create(string $context): self {
        return new self($context);
    }

    public function header(string $pageTitle = ''): void {
        global $conn;
        $title = $pageTitle; // Availability for required files
        $path = $this->getPath('header.php');
        
        if (file_exists($path)) {
            require $path;
        } else {
            // Default fallback to member portal if context-specific not found
            require $this->baseDir . '/member/layouts/header.php';
        }
    }

    public function sidebar(): void {
        global $conn;
        if ($this->context === 'public') return;

        $path = $this->getPath('sidebar.php');
        if (file_exists($path)) {
            require $path;
        } else {
             require $this->baseDir . '/member/layouts/sidebar.php';
        }
    }

    public function topbar(string $pageTitle = ''): void {
        global $conn;
        $path = $this->getPath('topbar.php');
        if (file_exists($path)) {
            require $path;
        } else {
             require $this->baseDir . '/member/layouts/topbar.php';
        }
    }

    public function footer(): void {
        global $conn;
        $path = $this->getPath('footer.php');
        if (file_exists($path)) {
            require $path;
        } else {
            require $this->baseDir . '/member/layouts/footer.php';
        }
    }

    private function getPath(string $file): string {
        return $this->baseDir . '/' . $this->context . '/layouts/' . $file;
    }
}
