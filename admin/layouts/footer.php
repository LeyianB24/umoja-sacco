<?php
// admin/layouts/footer.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Calculate Page Execution Time (Super Enhancement for Admins)
$time_start = $_SERVER["REQUEST_TIME_FLOAT"] ?? microtime(true);
$time_end = microtime(true);
$execution_time = round($time_end - $time_start, 3);
$memory_usage = round(memory_get_usage() / 1024 / 1024, 2);
?>

    </div> 

    <button type="button" class="btn btn-primary rounded-circle shadow-lg" id="btn-back-to-top" 
            style="position: fixed; bottom: 80px; right: 20px; display: none; z-index: 999; width: 45px; height: 45px;">
        <i class="bi bi-arrow-up"></i>
    </button>

    <footer class="footer mt-auto py-3 border-top" style="background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border-top: 1px solid rgba(0,0,0,0.05);">
        <div class="container-fluid px-4">
            <div class="row align-items-center gy-2">
                
                <div class="col-md-4 text-center text-md-start">
                    <div class="d-flex align-items-center gap-2 justify-content-center justify-content-md-start">
                        <span class="text-muted small">&copy; <?= date('Y') ?> <strong><?= defined('SITE_NAME') ? SITE_NAME : 'Umoja Sacco' ?></strong>.</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2" style="font-size: 0.7rem;">v10.0 Pro</span>
                    </div>
                    <div class="small text-muted opacity-75 mt-1" style="font-size: 0.75rem;">
                        Empowering Financial Growth
                    </div>
                </div>

                <div class="col-md-4 text-center">
                    <div class="d-inline-flex align-items-center gap-3 px-3 py-1 rounded-pill bg-light border">
                        <div class="d-flex align-items-center gap-1" data-bs-toggle="tooltip" title="Database Connection Active">
                            <span class="position-relative d-flex bg-success rounded-circle" style="width: 8px; height: 8px;">
                                <span class="position-absolute d-inline-flex h-100 w-100 rounded-circle bg-success opacity-75 animate-ping"></span>
                            </span>
                            <span class="small fw-bold text-success" style="font-size: 0.7rem;">SYSTEM ONLINE</span>
                        </div>
                        
                        <div class="vr" style="height: 12px;"></div>

                        <span class="small text-muted" style="font-size: 0.7rem;" data-bs-toggle="tooltip" title="Page Execution Time">
                            <i class="bi bi-lightning-charge-fill text-warning"></i> <?= $execution_time ?>s
                        </span>
                        <span class="small text-muted" style="font-size: 0.7rem;" data-bs-toggle="tooltip" title="Server Memory Usage">
                            <i class="bi bi-cpu-fill text-secondary"></i> <?= $memory_usage ?>MB
                        </span>
                    </div>
                </div>

                <div class="col-md-4 text-center text-md-end">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-end gap-3">
                        <a href="#" class="text-decoration-none text-muted small hover-text-primary">Support</a>
                        <a href="#" class="text-decoration-none text-muted small hover-text-primary">Docs</a>
                        
                        <div class="vr" style="height: 14px;"></div>

                        <div class="d-flex gap-2">
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', defined('COMPANY_PHONE') ? COMPANY_PHONE : '') ?>" 
                               class="btn btn-sm btn-light rounded-circle shadow-sm text-success d-flex align-items-center justify-content-center" 
                               style="width: 32px; height: 32px;" target="_blank" data-bs-toggle="tooltip" title="WhatsApp Support">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                            <?php if (defined('SOCIAL_FACEBOOK')): ?>
                            <a href="<?= SOCIAL_FACEBOOK ?>" 
                               class="btn btn-sm btn-light rounded-circle shadow-sm text-primary d-flex align-items-center justify-content-center" 
                               style="width: 32px; height: 32px;" target="_blank">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </footer>

    <style>
        @keyframes ping {
            0% { transform: scale(1); opacity: 1; }
            75% { transform: scale(2); opacity: 0; }
            100% { transform: scale(2); opacity: 0; }
        }
        .animate-ping { animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite; }
        .hover-text-primary:hover { color: var(--bs-primary) !important; text-decoration: underline !important; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 1. Initialize Global Bootstrap Tooltips
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            // 2. Back to Top Button Logic
            let mybutton = document.getElementById("btn-back-to-top");
            window.onscroll = function () {
                if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
                    mybutton.style.display = "block";
                } else {
                    mybutton.style.display = "none";
                }
            };
            mybutton.addEventListener("click", function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    </script>

    <script src="<?= defined('ASSET_BASE') ? ASSET_BASE : '../public' ?>/assets/js/main.js"></script>
</body>
</html>