<?php
// usms/inc/functions.php

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Direct access forbidden.');
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If config not loaded, attempt to include
if (!defined('ASSET_BASE')) {
    @include_once __DIR__ . '/../config/app_config.php';
}

/* ==========================================================================
   SECURITY & SANITIZATION
   ========================================================================== */

/**
 * Validates that a date is not in the future.
 * Returns true if future, false if not future (i.e., past or present).
 * @param string $date The date string to validate.
 * @return bool True if the date is in the future, false otherwise.
 */
if (!function_exists('is_future_date')) {
    function is_future_date($date) {
        $d = new DateTime($date);
        $now = new DateTime();
        // Compare the date with the end of the current day to consider today as not future.
        return $d > $now->setTime(23, 59, 59);
    }
}

/**
 * Validates that a date is not in the future and redirects if it is.
 * @param string $date The date string to validate.
 * @param string $redirect_url The URL to redirect to if the date is in the future.
 */
if (!function_exists('validate_not_future')) {
    function validate_not_future($date, $redirect_url) {
        if (is_future_date($date)) {
            flash_set("Invalid Date: Transactions cannot be recorded for future dates.", "danger");
            redirect_to($redirect_url);
        }
    }
}

/**
 * Escape output for HTML (Prevent XSS)
 * @param string|null $str
 * @return string
 */
if (!function_exists('esc')) {
    function esc(?string $str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * Generate CSRF Token
 * Call this at the top of your forms
 */
if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Render a hidden CSRF input field
 */
if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        $token = csrf_token();
        echo '<input type="hidden" name="csrf_token" value="' . esc($token) . '">';
    }
}

/**
 * Verify CSRF Token
 * Call this at the top of POST processing
 */
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                die('Security Token Mismatch (CSRF). Please reload the page and try again.');
            }
        }
    }
}

/* ==========================================================================
   USER INTERFACE & FLASH MESSAGES
   ========================================================================== */

/**
 * Set flash message in session
 * @param string $message
 * @param string $type success|error|info|warning
 */
if (!function_exists('flash_set')) {
    function flash_set($message, $type = 'info')
    {
        $_SESSION['flash'][] = [
            'msg' => $message, 
            'type' => $type
        ];
    }
}

/**
 * Render flash messages with Bootstrap Icons
 */
if (!function_exists('flash_render')) {
    function flash_render()
    {
        // 1. Handle V12 Flash Messages (Array)
        if (!empty($_SESSION['flash'])) {
            foreach ($_SESSION['flash'] as $f) {
                $type = $f['type'];
                
                // Map types to Bootstrap classes and Icons
                $map = [
                    'success' => ['class' => 'success', 'icon' => 'check-circle-fill'],
                    'error'   => ['class' => 'danger',  'icon' => 'exclamation-triangle-fill'],
                    'danger'  => ['class' => 'danger',  'icon' => 'exclamation-triangle-fill'], // Support legacy 'danger'
                    'warning' => ['class' => 'warning', 'icon' => 'exclamation-circle-fill'],
                    'info'    => ['class' => 'info',    'icon' => 'info-circle-fill']
                ];
        
                $style = $map[$type] ?? $map['info'];
    
            echo '<div class="alert alert-' . $style['class'] . ' alert-dismissible fade show shadow-sm" role="alert">';
            echo '<i class="bi bi-' . $style['icon'] . ' me-2"></i>';
            echo esc($f['msg']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
        unset($_SESSION['flash']);
    }

    // 2. Handle Legacy Flash Messages (Strings)
    if (isset($_SESSION['flash_msg'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        
        $map = [
            'success' => ['class' => 'success', 'icon' => 'check-circle-fill'],
            'error'   => ['class' => 'danger',  'icon' => 'exclamation-triangle-fill'],
            'danger'  => ['class' => 'danger',  'icon' => 'exclamation-triangle-fill'],
            'warning' => ['class' => 'warning', 'icon' => 'exclamation-circle-fill'],
            'info'    => ['class' => 'info',    'icon' => 'info-circle-fill']
        ];

        $style = $map[$type] ?? $map['info'];

        echo '<div class="alert alert-' . $style['class'] . ' alert-dismissible fade show shadow-sm" role="alert">';
        echo '<i class="bi bi-' . $style['icon'] . ' me-2"></i>';
        echo esc($_SESSION['flash_msg']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';

        unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
    }
}
}

/**
 * Helper to repopulate form fields on error
 * Usage: value="<?= old('email') ?>"
 */
if (!function_exists('old')) {
    function old($key, $default = '')
    {
        return isset($_POST[$key]) ? esc($_POST[$key]) : esc($default);
    }
}

/* ==========================================================================
   NAVIGATION & HTTP
   ========================================================================== */

/**
 * Standard Redirect
 */
if (!function_exists('redirect_to')) {
    function redirect_to($url)
    {
        if (!headers_sent()) {
            header("Location: " . $url);
        } else {
            echo "<script>window.location.href='" . $url . "';</script>";
        }
        exit;
    }
}

/**
 * Redirect with a Flash Message (Shorthand)
 */
if (!function_exists('redirect_with')) {
    function redirect_with($url, $message, $type = 'success')
    {
        flash_set($message, $type);
        redirect_to($url);
    }
}

/* ==========================================================================
   DATA FORMATTING (SACCO SPECIFIC)
   ========================================================================== */

/**
 * Format Money (KES)
 * Usage: format_money(1500.50) -> "1,500.50"
 */
if (!function_exists('format_money')) {
    function format_money($amount, $decimals = 2)
    {
        return number_format((float)$amount, $decimals, '.', ',');
    }
}

/**
 * Format Date
 */
if (!function_exists('format_date')) {
    function format_date($date, $format = 'd M Y')
    {
        return date($format, strtotime($date));
    }
}

/**
 * Generate Sequential Member Number (YY/MM0001)
 */
/**
 * Generate Sequential Member Number (UDS-YYYY-XXXX)
 */
if (!function_exists('generate_member_no')) {
    function generate_member_no($conn) {
        $year = date('Y'); 
        $prefix = "UDS-$year-";
        $res = $conn->query("SELECT member_reg_no FROM members WHERE member_reg_no LIKE '$prefix%' ORDER BY member_reg_no DESC LIMIT 1");
        
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $last_no = $row['member_reg_no'];
            // Extract number after the prefix (UDS-YYYY-)
            $parts = explode('-', $last_no);
            $num = intval(end($parts)) + 1;
        } else {
            $num = 1;
        }
        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
}

/* ==========================================================================
   DEVELOPER TOOLS
   ========================================================================== */

/**
 * Format Currency (KSH) - Shorthand
 */
if (!function_exists('ksh')) {
    function ksh($v, $d = 2) { 
        return number_format((float)($v ?? 0), $d); 
    }
}

/**
 * Get Initials from name
 */
if (!function_exists('getInitials')) {
    function getInitials($name) {
        if (empty($name)) return "S";
        $words = explode(" ", $name);
        $initials = "";
        foreach ($words as $w) {
            $initials .= strtoupper(substr($w, 0, 1));
            if (strlen($initials) >= 2) break;
        }
        return $initials ?: "S";
    }
}

/**
 * Dump and Die (Debugging helper)
 */
if (!function_exists('dd')) {
    function dd($data)
    {
        echo '<pre style="background:#111; color:#bada55; padding:20px; z-index:9999; position:relative;">';
        var_dump($data);
        echo '</pre>';
        die();
    }
}
/**
 * Time Ago Helper
 */
if (!function_exists('time_ago')) {
    function time_ago($timestamp) {
        if (empty($timestamp)) return "unknown";
        $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
        $diff = time() - $time;
        if ($diff < 1) return 'just now';
        $intervals = [
            31536000 => 'year',
            2592000  => 'month',
            604800   => 'week',
            86400    => 'day',
            3600     => 'hour',
            60       => 'minute',
            1        => 'second'
        ];
        foreach ($intervals as $secs => $label) {
            $d = $diff / $secs;
            if ($d >= 1) {
                $r = round($d);
                return $r . ' ' . $label . ($r > 1 ? 's' : '') . ' ago';
            }
        }
        return "just now";
    }
}

/**
 * Calculate total savings for a member across all compatible tables.
 * Rule: Contributions (Active Savings) + Savings Table (Deposits) - Savings Table (Withdrawals)
 * @param int $member_id
 * @param mysqli $conn
 * @return float
 */
if (!function_exists('getMemberSavings')) {
    function getMemberSavings($member_id, $conn) {
        // 1. Deposits from contributions (M-Pesa, Cash deposits)
        $stmt = $conn->prepare("SELECT SUM(amount) FROM contributions WHERE member_id = ? AND contribution_type = 'savings' AND status = 'active'");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $contrib_savings = $stmt->get_result()->fetch_row()[0] ?? 0;
        $stmt->close();

        // 2. Manual deposits from savings table (legacy/system adjustments)
        $stmt = $conn->prepare("SELECT SUM(amount) FROM savings WHERE member_id = ? AND transaction_type = 'deposit'");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $manual_deposits = $stmt->get_result()->fetch_row()[0] ?? 0;
        $stmt->close();

        // 3. Withdrawals from savings table
        $stmt = $conn->prepare("SELECT SUM(amount) FROM savings WHERE member_id = ? AND transaction_type = 'withdrawal'");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $withdrawals = $stmt->get_result()->fetch_row()[0] ?? 0;
        $stmt->close();

        return (float)($contrib_savings + $manual_deposits - $withdrawals);
    }
}
?>