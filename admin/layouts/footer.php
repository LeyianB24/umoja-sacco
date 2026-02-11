<?php
// admin/layouts/footer.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
    <!-- End Main Wrapper -->
    
    <!-- Footer could be simple for admin -->
    <footer class="mt-auto py-3 text-center text-muted small border-top bg-white">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    &copy; <?= date('Y') ?> <?= defined('SITE_NAME') ? SITE_NAME : 'Sacco' ?>. All rights reserved.
                    <span class="d-none d-sm-inline"> | System v10.0</span>
                </div>
                <div class="d-flex gap-2 admin-social-links">
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', COMPANY_PHONE) ?>" class="text-success text-decoration-none" target="_blank" title="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <?php if (defined('SOCIAL_FACEBOOK')): ?>
                    <a href="<?= SOCIAL_FACEBOOK ?>" class="text-primary text-decoration-none" target="_blank" title="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (defined('SOCIAL_TWITTER')): ?>
                    <a href="<?= SOCIAL_TWITTER ?>" class="text-info text-decoration-none" target="_blank" title="Twitter/X">
                        <i class="bi bi-twitter"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
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
