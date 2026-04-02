<?php
// admin/layouts/footer.php
if (session_status() === PHP_SESSION_NONE) session_start();

$time_start     = $_SERVER["REQUEST_TIME_FLOAT"] ?? microtime(true);
$time_end       = microtime(true);
$execution_time = round($time_end - $time_start, 3);
$memory_usage   = round(memory_get_usage() / 1024 / 1024, 2);

// Performance colour thresholds
$perf_color = $execution_time < 0.5 ? '#16a34a' : ($execution_time < 1.5 ? '#d97706' : '#dc2626');
?>

    <!-- ─── Back to Top ─────────────────────────────────────── -->
    <button type="button" id="btn-back-to-top" aria-label="Back to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    <!-- ─── Footer ──────────────────────────────────────────── -->
    <footer class="site-footer">
        <div class="footer-inner">

            <!-- Left: Brand -->
            <a href="<?= BASE_URL ?>/public/index.php" class="footer-brand text-decoration-none">
                <div class="brand-line">
                    <img src="<?= SITE_LOGO ?>" alt="Logo" style="width: 24px; height: 24px; object-fit: contain; margin-right: 8px;">
                    <span class="brand-name"><?= defined('SITE_NAME') ? SITE_NAME : 'Umoja Sacco' ?></span>
                    <span class="version-chip">v1.0 Pro</span>
                </div>
                <div class="brand-tagline">Empowering Financial Growth &mdash; &copy; <?= date('Y') ?></div>
            </a>

            <!-- Centre: System Status -->
            <div class="footer-status">
                <div class="status-pill">
                    <span class="status-dot">
                        <span class="status-ping"></span>
                    </span>
                    <span class="status-label">System Online</span>

                    <span class="status-divider"></span>

                    <span class="metric" data-bs-toggle="tooltip" title="Page Execution Time">
                        <i class="bi bi-lightning-charge-fill" style="color:#f59e0b;"></i>
                        <span style="color:<?= $perf_color ?>;"><?= $execution_time ?>s</span>
                    </span>

                    <span class="status-divider"></span>

                    <span class="metric" data-bs-toggle="tooltip" title="Server Memory Usage">
                        <i class="bi bi-cpu-fill" style="color:#6b7280;"></i>
                        <span><?= $memory_usage ?>MB</span>
                    </span>
                </div>
            </div>

            <!-- Right: Links + Social -->
            <div class="footer-actions">
                <a href="#" class="footer-link">Support</a>
                <a href="#" class="footer-link">Docs</a>

                <span class="status-divider"></span>

                <div class="social-row">
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', defined('COMPANY_PHONE') ? COMPANY_PHONE : '') ?>"
                       class="social-btn whatsapp" target="_blank" data-bs-toggle="tooltip" title="WhatsApp Support">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <?php if (defined('SOCIAL_FACEBOOK')): ?>
                    <a href="<?= SOCIAL_FACEBOOK ?>"
                       class="social-btn facebook" target="_blank" data-bs-toggle="tooltip" title="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </footer>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        /* ── Back to Top ── */
        #btn-back-to-top {
            position: fixed;
            bottom: 28px;
            right: 24px;
            display: none;
            z-index: 999;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--forest, #0f2e25);
            color: #a3e635;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            box-shadow: 0 4px 16px rgba(15,46,37,0.25);
            transition: transform 0.22s cubic-bezier(0.16,1,0.3,1),
                        box-shadow 0.22s cubic-bezier(0.16,1,0.3,1);
            align-items: center;
            justify-content: center;
        }

        #btn-back-to-top:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(15,46,37,0.3);
        }

        /* ── Footer Shell ── */
        .site-footer {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #fff;
            border-top: 1px solid #f0f0f0;
            padding: 0;
            margin-top: auto;
        }

        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 14px 28px;
            flex-wrap: wrap;
        }

        /* ── Brand ── */
        .footer-brand {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .brand-line {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .brand-name {
            font-size: 0.875rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.2px;
        }

        .version-chip {
            display: inline-flex;
            align-items: center;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            letter-spacing: 0.3px;
        }

        .brand-tagline {
            font-size: 0.72rem;
            color: #9ca3af;
            font-weight: 400;
        }

        /* ── Status Pill ── */
        .footer-status {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fafafa;
            border: 1px solid #f0f0f0;
            border-radius: 50px;
            padding: 6px 16px;
        }

        .status-dot {
            position: relative;
            display: inline-flex;
            width: 8px;
            height: 8px;
            flex-shrink: 0;
        }

        .status-dot::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: #16a34a;
        }

        .status-ping {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: #16a34a;
            opacity: 0.6;
            animation: footerPing 2s ease-out infinite;
        }

        .status-label {
            font-size: 10.5px;
            font-weight: 700;
            color: #16a34a;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-divider {
            display: inline-block;
            width: 1px;
            height: 12px;
            background: #e5e7eb;
            flex-shrink: 0;
        }

        .metric {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            cursor: default;
        }

        /* ── Footer Actions ── */
        .footer-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .footer-link {
            font-size: 0.8rem;
            font-weight: 600;
            color: #6b7280;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .footer-link:hover { color: var(--forest, #0f2e25); }

        .social-row {
            display: flex;
            gap: 6px;
        }

        .social-btn {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            text-decoration: none;
            transition: transform 0.2s cubic-bezier(0.34,1.56,0.64,1),
                        box-shadow 0.2s ease;
            background: #f3f4f6;
            color: #6b7280;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .social-btn.whatsapp:hover { background: #dcfce7; color: #16a34a; }
        .social-btn.facebook:hover { background: #dbeafe; color: #2563eb; }

        /* ── Animation ── */
        @keyframes footerPing {
            0%   { transform: scale(1);   opacity: 0.6; }
            70%  { transform: scale(2.2); opacity: 0;   }
            100% { transform: scale(2.2); opacity: 0;   }
        }

        /* ── Dark Mode ── */
        [data-bs-theme="dark"] .site-footer {
            background: #161b22;
            border-top-color: #21262d;
        }

        [data-bs-theme="dark"] .status-pill {
            background: #0d1117;
            border-color: #21262d;
        }

        [data-bs-theme="dark"] .status-divider { background: #30363d; }
        [data-bs-theme="dark"] .brand-name { color: #f0f6fc; }
        [data-bs-theme="dark"] .brand-tagline,
        [data-bs-theme="dark"] .metric { color: #8b949e; }
        [data-bs-theme="dark"] .footer-link { color: #8b949e; }
        [data-bs-theme="dark"] .footer-link:hover { color: #34d399; }
        [data-bs-theme="dark"] .social-btn { background: #21262d; color: #8b949e; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .footer-inner {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 16px 20px;
                gap: 12px;
            }

            .footer-status { width: 100%; }
            .footer-actions { justify-content: center; }
            .footer-brand { align-items: center; }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Dark Mode Graph Sync
            if (window.syncChartsTheme) window.syncChartsTheme();

            // Bootstrap Tooltips
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });

            // Back to Top
            const btn = document.getElementById('btn-back-to-top');
            if (btn) {
                btn.style.display = 'none';
                window.addEventListener('scroll', function () {
                    btn.style.display =
                        (document.documentElement.scrollTop > 200) ? 'flex' : 'none';
                });
                btn.addEventListener('click', function () {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
        });
    </script>

    <script src="<?= ASSET_BASE ?>/js/sidebar.js"></script>
    <script src="<?= ASSET_BASE ?>/js/darkmode.js"></script>
    <script src="<?= ASSET_BASE ?>/js/main.js"></script>
    <script src="<?= ASSET_BASE ?>/js/live-refresh.js"></script>
    <?php if (isset($extraJs)): ?>
    <?= $extraJs ?>
    <?php endif; ?>
</body>
</html>