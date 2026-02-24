<?php
ob_start();
// Load config FIRST — prevents 'constant already defined' errors from functions.php auto-include
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/Auth.php';

// Constants
if (!defined('REMEMBER_SECONDS')) define('REMEMBER_SECONDS', 30 * 24 * 60 * 60);
if (!defined('COOKIE_NAME'))      define('COOKIE_NAME', 'usms_rem');
$cookie_secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

// --- HELPER FUNCTIONS ---

function verifyAndUpgradePassword($conn, $table, $id_col, $id_val, $input_pass, $stored_hash) {
    $valid = false;
    $needs_rehash = false;

    // 1. Check modern Bcrypt hash
    if (!empty($stored_hash) && password_verify($input_pass, $stored_hash)) {
        $valid = true;
        if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
            $needs_rehash = true;
        }
    }
    // 2. Check SHA256 hash (Legacy Fallback)
    elseif (!empty($stored_hash) && hash('sha256', $input_pass) === $stored_hash) {
        $valid = true;
        $needs_rehash = true;
    }
    // 3. Check Plaintext (Legacy Fallback)
    elseif ($stored_hash === $input_pass) {
        $valid = true;
        $needs_rehash = true;
    }

    // Auto-upgrade legacy passwords
    if ($valid && $needs_rehash) {
        $new_hash = password_hash($input_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE $id_col = ?");
        $stmt->bind_param('si', $new_hash, $id_val);
        $stmt->execute();
        $stmt->close();
    }

    return $valid;
}

/**
 * --- MAIN LOGIN LOGIC ---
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') { 

    // CSRF Check
    verify_csrf_token();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if ($email === '' || $password === '') {
        flash_set('Please enter both email/username and password.', 'error');
    } else {
        $validator = new \USMS\Services\Validator($_POST);
        $validator->required('email')->required('password');

        if ($validator->passes()) {
            $user_found = false;

            // --- 1. Attempt Admin Login ---
            $stmt_admin = $conn->prepare("SELECT a.admin_id, a.full_name, a.username, a.role_id, r.name as role_name, a.password 
                                        FROM admins a 
                                        JOIN roles r ON a.role_id = r.id 
                                        WHERE a.email = ? OR a.username = ? LIMIT 1");
            $stmt_admin->bind_param('ss', $email, $email);
            $stmt_admin->execute();
            $res_admin = $stmt_admin->get_result();

            if ($res_admin && $res_admin->num_rows > 0) {
                $admin = $res_admin->fetch_assoc();
                
                if (verifyAndUpgradePassword($conn, 'admins', 'admin_id', $admin['admin_id'], $password, $admin['password'])) {
                    session_regenerate_id(true);

                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['admin_name'] = !empty($admin['full_name']) ? $admin['full_name'] : $admin['username'];
                    $_SESSION['role_id'] = $admin['role_id'];
                    $_SESSION['role'] = strtolower($admin['role_name']);
                    $_SESSION['role_name'] = $admin['role_name'];
                    
                    // Load Permissions into Session
                    Auth::loadPermissions($admin['role_id']);
                    
                    // Superadmin Rule: If role_id == 1, force load all permissions for session visibility
                    if ($_SESSION['role_id'] == 1) {
                        $res_all = $conn->query("SELECT slug FROM permissions");
                        $all_perms = [];
                        while($p = $res_all->fetch_assoc()) $all_perms[] = $p['slug'];
                        $_SESSION['permissions'] = $all_perms;
                    }
                    
                    $user_found = true;

                    // Handle 'Remember Me'
                    if ($remember) {
                        $rnd = bin2hex(random_bytes(32));
                        $token_hash = hash('sha256', $rnd);
                        $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                        $expires = date('Y-m-d H:i:s', time() + REMEMBER_SECONDS);

                        $up = $conn->prepare("UPDATE admins SET remember_token=?, remember_expires=?, remember_ua=? WHERE admin_id=?");
                        $up->bind_param('sssi', $token_hash, $expires, $ua_hash, $admin['admin_id']);
                        $up->execute();
                        $up->close();

                        setcookie(COOKIE_NAME, $rnd, time() + REMEMBER_SECONDS, '/', '', $cookie_secure, true);
                    }

                    flash_set('Welcome back, ' . $_SESSION['admin_name'] . '!', 'success');

                    // Redirect to Admin Dashboard
                    $dest = BASE_URL . '/admin/pages/dashboard.php';
                    header("Location: $dest");
                    exit;
                }
            }
            $stmt_admin->close();

            // --- 2. Attempt Member Login (If not Admin) ---
            if (!$user_found) {
                // Check by Email OR RegNo
                $stmt_member = $conn->prepare("SELECT member_id, full_name, member_reg_no, password, registration_fee_status FROM members WHERE email = ? OR member_reg_no = ? LIMIT 1");
                $stmt_member->bind_param('ss', $email, $email);
                $stmt_member->execute();
                $res_member = $stmt_member->get_result();

                if ($res_member && $res_member->num_rows > 0) {
                    $member = $res_member->fetch_assoc();

                    if (verifyAndUpgradePassword($conn, 'members', 'member_id', $member['member_id'], $password, $member['password'])) {
                        session_regenerate_id(true);
                        
                        $_SESSION['member_id'] = $member['member_id'];
                        $_SESSION['member_name'] = $member['full_name'];
                        $_SESSION['reg_no']      = $member['member_reg_no']; // Store RegNo in session
                        $_SESSION['role'] = 'member';

                        // PAY-GATE CHECK
                        if (($member['registration_fee_status'] ?? 'unpaid') === 'unpaid') {
                            $_SESSION['pending_pay'] = true;
                            flash_set('Payment Required: Please settle your registration fee to access the dashboard.', 'warning');
                            header("Location: " . BASE_URL . "/member/pages/pay_registration.php");
                            exit;
                        }
                        
                        flash_set('Welcome, ' . $_SESSION['member_name'] . '! Access your portal.', 'success');
                        header("Location: " . BASE_URL . "/member/pages/dashboard.php");
                        exit;
                    }
                }
                $stmt_member->close();
            }

            // --- 3. Failed Login ---
            flash_set('Invalid login credentials. Please double-check your details.', 'error');

        } else {
            flash_set('Validation failed: ' . implode(', ', $validator->getFirstErrors()), 'error');
        }
    }
}
?>
