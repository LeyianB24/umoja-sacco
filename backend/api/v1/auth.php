<?php
declare(strict_types=1);

/**
 * api/v1/auth.php
 * Authentication Controller for USMS (Admin & Member)
 */

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../inc/functions.php';
require_once __DIR__ . '/../../inc/auth.php';

$action = $_GET['action'] ?? $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input  = get_json_input();

if ($action === 'login' && $method === 'POST') {
    $email    = trim($input['email'] ?? $input['username'] ?? $input['identifier'] ?? '');
    $password = $input['password'] ?? '';
    $userType = $input['user_type'] ?? 'auto'; // 'auto', 'admin', 'member'

    if (empty($email) || empty($password)) {
        api_error('Please provide both username/email and password.', 422);
    }

    $user_found = false;

    // 1. Try Admin Login (if type is auto or admin)
    if ($userType === 'auto' || $userType === 'admin') {
        $stmt_admin = $conn->prepare("SELECT a.admin_id, a.full_name, a.username, a.email, a.phone, a.role_id, r.name as role_name, a.password FROM admins a JOIN roles r ON a.role_id = r.id WHERE (a.email = ? OR a.username = ?) AND (a.is_active = 1 OR a.is_active IS NULL) LIMIT 1");
        if ($stmt_admin) {
            $stmt_admin->bind_param('ss', $email, $email);
            $stmt_admin->execute();
            $res_admin = $stmt_admin->get_result();

            if ($res_admin && $res_admin->num_rows > 0) {
                $admin = $res_admin->fetch_assoc();
                if (verifyAndUpgradePassword($conn, 'admins', 'admin_id', $admin['admin_id'], $password, $admin['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id']   = (int)$admin['admin_id'];
                    $_SESSION['admin_name'] = !empty($admin['full_name']) ? $admin['full_name'] : $admin['username'];
                    $_SESSION['role_id']    = (int)$admin['role_id'];
                    $_SESSION['role']       = strtolower($admin['role_name']);
                    $_SESSION['role_name']  = $admin['role_name'];

                    \USMS\Middleware\Auth::loadPermissions($admin['role_id']);
                    
                    if ((int)$admin['role_id'] === 1) {
                        $res_all = $conn->query("SELECT slug FROM permissions");
                        $all_perms = [];
                        if ($res_all) {
                            while($p = $res_all->fetch_assoc()) $all_perms[] = $p['slug'];
                        }
                        $_SESSION['permissions'] = $all_perms;
                    }

                    // Update last login
                    @$conn->query("UPDATE admins SET last_login = NOW() WHERE admin_id = " . (int)$admin['admin_id']);

                    $permissions = $_SESSION['permissions'] ?? [];

                    api_success([
                        'user' => [
                            'id' => (int)$admin['admin_id'],
                            'name' => $_SESSION['admin_name'],
                            'username' => $admin['username'],
                            'email' => $admin['email'],
                            'phone' => $admin['phone'] ?? '',
                            'role' => $_SESSION['role'],
                            'role_id' => (int)$admin['role_id'],
                            'role_name' => $admin['role_name'],
                            'user_type' => 'admin'
                        ],
                        'permissions' => $permissions,
                        'session_id' => session_id(),
                        'redirect_to' => '/admin'
                    ], 'Login successful.');
                }
            }
            $stmt_admin->close();
        }
    }

    // 2. Try Member Login (if type is auto or member)
    if ($userType === 'auto' || $userType === 'member') {
        $stmt_member = $conn->prepare("SELECT member_id, full_name, member_reg_no, national_id, phone, email, password, status, kyc_status, registration_fee_status FROM members WHERE email = ? OR member_reg_no = ? OR phone = ? OR national_id = ? LIMIT 1");
        if ($stmt_member) {
            $stmt_member->bind_param('ssss', $email, $email, $email, $email);
            $stmt_member->execute();
            $res_member = $stmt_member->get_result();

            if ($res_member && $res_member->num_rows > 0) {
                $member = $res_member->fetch_assoc();
                if (verifyAndUpgradePassword($conn, 'members', 'member_id', $member['member_id'], $password, $member['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['member_id']   = (int)$member['member_id'];
                    $_SESSION['member_name'] = $member['full_name'];
                    $_SESSION['reg_no']      = $member['member_reg_no'];
                    $_SESSION['role']        = 'member';

                    api_success([
                        'user' => [
                            'id' => (int)$member['member_id'],
                            'name' => $member['full_name'],
                            'reg_no' => $member['member_reg_no'],
                            'national_id' => $member['national_id'],
                            'email' => $member['email'],
                            'phone' => $member['phone'],
                            'status' => $member['status'],
                            'kyc_status' => $member['kyc_status'] ?? 'pending',
                            'role' => 'member',
                            'user_type' => 'member'
                        ],
                        'permissions' => [],
                        'session_id' => session_id(),
                        'redirect_to' => '/member'
                    ], 'Login successful.');
                }
            }
            $stmt_member->close();
        }
    }

    api_error('Invalid credentials. Please check your username/email and password.', 401);
}

if ($action === 'register' && $method === 'POST') {
    $full_name   = trim($input['full_name'] ?? '');
    $national_id = trim($input['national_id'] ?? '');
    $phone_raw   = trim($input['phone'] ?? '');
    $email       = trim($input['email'] ?? '');
    $password    = $input['password'] ?? '';
    $confirm     = $input['confirm_password'] ?? $input['password_confirmation'] ?? '';
    $gender      = $input['gender'] ?? 'male';
    $dob         = $input['dob'] ?? '';
    $occupation  = trim($input['occupation'] ?? '');
    $address     = trim($input['address'] ?? '');
    $nok_name    = trim($input['nok_name'] ?? $input['next_of_kin_name'] ?? '');
    $nok_phone   = trim($input['nok_phone'] ?? $input['next_of_kin_phone'] ?? '');

    if (empty($full_name) || empty($national_id) || empty($phone_raw) || empty($email) || empty($password)) {
        api_error('Please fill in all required fields (Full Name, National ID, Phone, Email, Password).', 422);
    }

    if (strlen($password) < 6) {
        api_error('Password must be at least 6 characters.', 422);
    }

    if (!empty($confirm) && $password !== $confirm) {
        api_error('Passwords do not match.', 422);
    }

    // Normalize phone
    $phone = preg_replace('/[^\d\+]/', '', $phone_raw);
    if (strpos($phone, '+') !== 0) {
        if (preg_match('/^0(\d{8,9})$/', $phone, $m)) {
            $phone = '+254' . $m[1];
        } elseif (preg_match('/^7(\d{8})$/', $phone, $m)) {
            $phone = '+254' . $phone;
        }
    }

    // Check duplicate
    $checkSql = "SELECT member_id FROM members WHERE email = ? OR phone = ? OR national_id = ? LIMIT 1";
    if ($stmt = $conn->prepare($checkSql)) {
        $stmt->bind_param("sss", $email, $phone, $national_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            api_error('A member with that email, phone, or national ID already exists.', 409);
        }
        $stmt->close();
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate Reg No
    $prefix = "USMS-" . date('Y');
    $last_res = $conn->query("SELECT member_reg_no FROM members WHERE member_reg_no LIKE '$prefix-%' ORDER BY member_id DESC LIMIT 1");
    $next_num = 1;
    if ($last_res && $row = $last_res->fetch_assoc()) {
        $parts = explode('-', $row['member_reg_no']);
        if (isset($parts[2])) {
            $next_num = (int)$parts[2] + 1;
        }
    }
    $reg_no = sprintf("%s-%04d", $prefix, $next_num);
    $status = 'active';
    $kyc_status = 'pending';

    $insertSql = "INSERT INTO members (member_reg_no, full_name, national_id, phone, email, password, join_date, status, reg_fee_paid, gender, dob, occupation, address, next_of_kin_name, next_of_kin_phone, kyc_status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 0, ?, ?, ?, ?, ?, ?, ?)";
    if ($ins = $conn->prepare($insertSql)) {
        $ins->bind_param("ssssssssssssss", $reg_no, $full_name, $national_id, $phone, $email, $hashed, $status, $gender, $dob, $occupation, $address, $nok_name, $nok_phone, $kyc_status);
        if ($ins->execute()) {
            $newMemberId = (int)$ins->insert_id;
            $ins->close();

            session_regenerate_id(true);
            $_SESSION['member_id']   = $newMemberId;
            $_SESSION['member_name'] = $full_name;
            $_SESSION['reg_no']      = $reg_no;
            $_SESSION['role']        = 'member';

            // In-app notification
            require_once __DIR__ . '/../../inc/notification_helpers.php';
            if (function_exists('add_admin_notification')) {
                add_admin_notification('New Member Registered', "New member registered: {$full_name} ({$reg_no}). National ID: {$national_id}.", 'manager');
            }

            api_success([
                'user' => [
                    'id' => $newMemberId,
                    'name' => $full_name,
                    'reg_no' => $reg_no,
                    'national_id' => $national_id,
                    'email' => $email,
                    'phone' => $phone,
                    'status' => $status,
                    'kyc_status' => $kyc_status,
                    'role' => 'member',
                    'user_type' => 'member'
                ],
                'session_id' => session_id(),
                'redirect_to' => '/member'
            ], 'Registration successful! Welcome to Umoja Sacco.');
        } else {
            $err = $ins->error;
            $ins->close();
            api_error("Registration failed: {$err}", 500);
        }
    } else {
        api_error("Failed to prepare registration statement: " . $conn->error, 500);
    }
}

if ($action === 'me' && $method === 'GET') {
    if (isset($_SESSION['admin_id'])) {
        $admin_id = (int)$_SESSION['admin_id'];
        $stmt = $conn->prepare("SELECT a.admin_id, a.full_name, a.username, a.email, a.phone, a.role_id, r.name as role_name, a.last_login FROM admins a JOIN roles r ON a.role_id = r.id WHERE a.admin_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $admin_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $admin = $res->fetch_assoc()) {
                $stmt->close();
                \USMS\Middleware\Auth::loadPermissions((int)$admin['role_id']);
                $permissions = $_SESSION['permissions'] ?? [];
                
                // Get unread counts
                require_once __DIR__ . '/../../inc/TopbarHelper.php';
                $topbar = TopbarHelper::getData($admin_id, 'admin');

                api_success([
                    'user' => [
                        'id' => (int)$admin['admin_id'],
                        'name' => !empty($admin['full_name']) ? $admin['full_name'] : $admin['username'],
                        'username' => $admin['username'],
                        'email' => $admin['email'],
                        'phone' => $admin['phone'] ?? '',
                        'role' => strtolower($admin['role_name']),
                        'role_id' => (int)$admin['role_id'],
                        'role_name' => $admin['role_name'],
                        'last_login' => $admin['last_login'],
                        'user_type' => 'admin',
                    ],
                    'permissions' => $permissions,
                    'topbar' => [
                        'unread_notifications' => $topbar['unread_notif_count'] ?? 0,
                        'unread_messages' => $topbar['unread_msgs_count'] ?? 0,
                        'recent_notifications' => $topbar['recent_notifs'] ?? [],
                        'recent_messages' => $topbar['recent_messages'] ?? []
                    ]
                ]);
            }
            $stmt->close();
        }
    }

    if (isset($_SESSION['member_id'])) {
        $member_id = (int)$_SESSION['member_id'];
        $stmt = $conn->prepare("SELECT member_id, full_name, member_reg_no, national_id, phone, email, gender, dob, occupation, address, next_of_kin_name, next_of_kin_phone, status, kyc_status, registration_fee_status, savings_balance, shares_balance, wallet_balance, created_at FROM members WHERE member_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $member = $res->fetch_assoc()) {
                $stmt->close();

                require_once __DIR__ . '/../../inc/FinancialEngine.php';
                $engine = new FinancialEngine($conn);
                $balances = $engine->getBalances($member_id);

                require_once __DIR__ . '/../../inc/TopbarHelper.php';
                $topbar = TopbarHelper::getData($member_id, 'member');

                api_success([
                    'user' => [
                        'id' => (int)$member['member_id'],
                        'name' => $member['full_name'],
                        'reg_no' => $member['member_reg_no'],
                        'national_id' => $member['national_id'],
                        'email' => $member['email'],
                        'phone' => $member['phone'],
                        'gender' => $member['gender'] ?? 'male',
                        'dob' => $member['dob'],
                        'occupation' => $member['occupation'],
                        'address' => $member['address'],
                        'next_of_kin_name' => $member['next_of_kin_name'],
                        'next_of_kin_phone' => $member['next_of_kin_phone'],
                        'status' => $member['status'],
                        'kyc_status' => $member['kyc_status'] ?? 'pending',
                        'role' => 'member',
                        'user_type' => 'member',
                        'created_at' => $member['created_at']
                    ],
                    'balances' => [
                        'wallet' => (float)$balances['wallet'],
                        'savings' => (float)$balances['savings'],
                        'shares' => (float)$balances['shares'],
                        'loans' => (float)$balances['loans'],
                        'net_worth' => (float)($balances['savings'] + $balances['shares'] - $balances['loans'])
                    ],
                    'permissions' => [],
                    'topbar' => [
                        'unread_notifications' => $topbar['unread_notif_count'] ?? 0,
                        'unread_messages' => $topbar['unread_msgs_count'] ?? 0,
                        'recent_notifications' => $topbar['recent_notifs'] ?? [],
                        'recent_messages' => $topbar['recent_messages'] ?? []
                    ]
                ]);
            }
            $stmt->close();
        }
    }

    api_error('Unauthenticated', 401);
}

if ($action === 'logout' && ($method === 'POST' || $method === 'GET')) {
    $_SESSION = [];
    if (session_id() !== '') {
        session_destroy();
    }
    api_success(null, 'Logged out successfully.');
}

if ($action === 'forgot_password' && $method === 'POST') {
    $email = trim($input['email'] ?? '');
    if (empty($email)) {
        api_error('Please provide your registered email address.', 422);
    }

    $token = bin2hex(random_bytes(24));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    // Check member or admin
    $found = false;
    $chk_mem = $conn->prepare("SELECT member_id FROM members WHERE email = ? LIMIT 1");
    if ($chk_mem) {
        $chk_mem->bind_param('s', $email);
        $chk_mem->execute();
        if ($chk_mem->get_result()->num_rows > 0) {
            $found = true;
            @$conn->query("UPDATE members SET reset_token = '$token', reset_expires = '$expires' WHERE email = '" . $conn->real_escape_string($email) . "'");
        }
        $chk_mem->close();
    }

    if (!$found) {
        $chk_adm = $conn->prepare("SELECT admin_id FROM admins WHERE email = ? LIMIT 1");
        if ($chk_adm) {
            $chk_adm->bind_param('s', $email);
            $chk_adm->execute();
            if ($chk_adm->get_result()->num_rows > 0) {
                $found = true;
                @$conn->query("UPDATE admins SET reset_token = '$token', reset_expires = '$expires' WHERE email = '" . $conn->real_escape_string($email) . "'");
            }
            $chk_adm->close();
        }
    }

    api_success([
        'token' => $found ? $token : null
    ], 'If the email exists in our records, a password reset link has been dispatched.');
}

if ($action === 'reset_password' && $method === 'POST') {
    $token    = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';
    $confirm  = $input['confirm_password'] ?? '';

    if (empty($token) || empty($password)) {
        api_error('Token and new password are required.', 422);
    }
    if ($password !== $confirm) {
        api_error('Passwords do not match.', 422);
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $updated = false;

    // Check members
    $res = $conn->query("SELECT member_id FROM members WHERE reset_token = '" . $conn->real_escape_string($token) . "' AND reset_expires >= NOW() LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $conn->query("UPDATE members SET password = '$newHash', reset_token = NULL, reset_expires = NULL WHERE member_id = " . (int)$row['member_id']);
        $updated = true;
    }

    if (!$updated) {
        $res = $conn->query("SELECT admin_id FROM admins WHERE reset_token = '" . $conn->real_escape_string($token) . "' AND reset_expires >= NOW() LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $conn->query("UPDATE admins SET password = '$newHash', reset_token = NULL, reset_expires = NULL WHERE admin_id = " . (int)$row['admin_id']);
            $updated = true;
        }
    }

    if ($updated) {
        api_success(null, 'Password has been reset successfully. You can now login.');
    } else {
        api_error('Invalid or expired reset token.', 400);
    }
}

api_error('Unknown auth endpoint: ' . htmlspecialchars($action), 404);
