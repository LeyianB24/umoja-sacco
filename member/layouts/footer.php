<?php
// member/layouts/footer.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
    <!-- End Main Wrapper -->
    
    <footer class="mt-auto py-4 bg-white border-top">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
            <div class="mb-2 mb-md-0">
                &copy; <?= date('Y') ?> <?= defined('SITE_NAME') ? SITE_NAME : 'Sacco' ?>
            </div>
            <div class="d-flex gap-3">
                <a href="<?= BASE_URL ?>/public/index.php#contact" class="text-decoration-none text-muted hover-forest">Contact Support</a>
                <a href="<?= BASE_URL ?>/member/pages/terms.php" class="text-decoration-none text-muted hover-forest">Terms</a>
                <a href="<?= BASE_URL ?>/member/pages/privacy.php" class="text-decoration-none text-muted hover-forest">Privacy</a>
            </div>
        </div>
    </footer>

    <!-- BOOTSTRAP JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- GLOBAL JS -->
    <script src="<?= ASSET_BASE ?>/js/main.js"></script>
    <script src="<?= ASSET_BASE ?>/js/sidebar.js"></script>
    <script src="<?= ASSET_BASE ?>/js/darkmode.js"></script>

    <script>
        // Any member specific initialization
    </script>
</body>
</html>
