<?php
declare(strict_types=1);
// inc/LayoutManager.php

class LayoutManager {
    
    private $context; // 'admin', 'member', 'public'
    private $baseDir;

    public function __construct($context = 'public') {
        $this->context = $context;
        $this->baseDir = dirname(__DIR__); // Root of the project (c:/xampp/htdocs/usms)
    }

    public static function create($context) {
        return new self($context);
    }

    public function header($pageTitle = '') {
        global $conn;
        $title = $pageTitle; // Make available to view
        $path = $this->getPath('header.php');
        if (file_exists($path)) {
            require $path;
        } else {
            // Fallback to shared header in inc if exists, or basic html
            require $this->baseDir . '/inc/header.php';
        }
    }

    public function sidebar() {
        global $conn;
        if ($this->context === 'public') return; // No sidebar for public normally

        $path = $this->getPath('sidebar.php');
        if (file_exists($path)) {
            require $path;
        } else {
            // Check legacy location or shared location
            // Using the dispatcher sidebar in inc/sidebar.php which handles context logic internally 
            // might be better if we want to stick to the "dispatcher" pattern I just built.
            // But the USER requested specific layout files in /admin/layouts.
            
            // Let's defer to the specific layout file if it exists, otherwise use the shared dispatcher.
             require $this->baseDir . '/inc/sidebar.php';
        }
    }

    public function topbar($pageTitle = '') {
        global $conn;
        $path = $this->getPath('topbar.php');
        if (file_exists($path)) {
            require $path;
        } else {
             require $this->baseDir . '/inc/topbar.php';
        }
    }

    public function footer() {
        global $conn;
        $path = $this->getPath('footer.php');
        if (file_exists($path)) {
            require $path;
        } else {
            require $this->baseDir . '/inc/footer.php';
        }
    }

    private function getPath($file) {
        // e.g. /admin/layouts/header.php
        return $this->baseDir . '/' . $this->context . '/layouts/' . $file;
    }
}
?>
