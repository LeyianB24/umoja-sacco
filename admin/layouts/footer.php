<?php
// admin/layouts/footer.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
    <!-- End Main Wrapper -->
    
    <!-- Footer could be simple for admin -->
    <footer class="mt-auto py-3 text-center text-muted small">
        <div class="container-fluid">
            &copy; <?= date('Y') ?> <?= defined('SITE_NAME') ? SITE_NAME : 'Sacco' ?>. All rights reserved.
            <span class="d-none d-sm-inline"> | System v10.0</span>
        </div>
    </footer>

    <!-- BOOTSTRAP JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- GLOBAL JS -->
    <script src="<?= ASSET_BASE ?>/js/main.js"></script>
    <script src="<?= ASSET_BASE ?>/js/sidebar.js"></script>
    <script src="<?= ASSET_BASE ?>/js/darkmode.js"></script>

    <!-- LAYOUT SPECIFIC JS -->
    <script>
        // Any admin specific initialization
    </script>
</body>
</html>
