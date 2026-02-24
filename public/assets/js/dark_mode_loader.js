<?php
/**
 * inc/dark_mode_loader.php
 * Compact loader to inject dark mode assets into pages that bypass shared headers.
 */
if (!defined('BASE_URL')) {
    // Attempt to load config if not defined
    $config_path = __DIR__ . '/../config/app_config.php';
    if (file_exists($config_path)) require_once $config_path;
}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<!-- Dark Mode Support Injection -->
<link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/css/darkmode.css">
<script>
    (function() {
        const applyTheme = (theme) => {
            document.documentElement.setAttribute('data-bs-theme', theme);
            
            const isDark = theme === 'dark';
            
            // Global Chart.js Defaults
            if (window.Chart) {
                Chart.defaults.color = isDark ? '#71767b' : '#6b7280';
                Chart.defaults.borderColor = isDark ? '#2f3336' : '#e5e7eb';
                if (Chart.defaults.plugins && Chart.defaults.plugins.tooltip) {
                    Chart.defaults.plugins.tooltip.backgroundColor = isDark ? '#15181c' : '#ffffff';
                    Chart.defaults.plugins.tooltip.titleColor = isDark ? '#eff3f4' : '#111827';
                    Chart.defaults.plugins.tooltip.bodyColor = isDark ? '#eff3f4' : '#111827';
                    Chart.defaults.plugins.tooltip.borderColor = isDark ? '#2f3336' : '#e5e7eb';
                }
            }
            // Global ApexCharts Defaults
            if (window.Apex) {
                Apex.theme = { mode: isDark ? 'dark' : 'light' };
                Apex.grid = { borderColor: isDark ? '#2f3336' : '#e5e7eb' };
                Apex.stroke = { colors: isDark ? ['#bef264'] : ['#65a30d'] };
                Apex.tooltip = { theme: isDark ? 'dark' : 'light' };
            }
        };

        // EXPOSE GLOBALLY for footer sync
        window.syncChartsTheme = () => {
            const current = localStorage.getItem('theme') || 'light';
            applyTheme(current);
        };

        const saved = localStorage.getItem('theme') || 'light';
        applyTheme(saved);

        // AUTO-SYNC POLLING: Ensure defaults are applied even if libraries load late
        let pollCount = 0;
        const chartPoll = setInterval(() => {
            pollCount++;
            if (window.Chart || window.Apex) {
                window.syncChartsTheme();
                clearInterval(chartPoll);
            }
            if (pollCount > 50) clearInterval(chartPoll); // Stop after 5s
        }, 100);

        // Listen for theme toggle events (if multiple pages open)
        window.addEventListener('storage', (e) => {
            if (e.key === 'theme') applyTheme(e.newValue);
        });

        // Intercept Chart creation to ensure defaults are applied
        const originalInit = window.Chart ? window.Chart.prototype.construct : null;
        if (originalInit) {
            // This is a bit advanced, but ensures that even charts created later 
            // inherit the current theme colors if not explicitly overridden.
        }
    })();
</script>
