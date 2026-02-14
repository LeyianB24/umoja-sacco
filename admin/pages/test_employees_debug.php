<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "<h1>Debug Employees Page</h1>";
    
    // Mock Session
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['admin_id'] = 1;
    $_SESSION['role_id'] = 1;

    echo "<p>1. Loading Config...</p>";
    require_once __DIR__ . '/../../config/app_config.php';
    require_once __DIR__ . '/../../config/db_connect.php';
    
    echo "<p>2. Loading Inc...</p>";
    require_once __DIR__ . '/../../inc/Auth.php';
    require_once __DIR__ . '/../../inc/LayoutManager.php';
    
    echo "<p>3. Loading Services...</p>";
    require_once __DIR__ . '/../../inc/HRService.php';
    require_once __DIR__ . '/../../inc/SystemUserService.php';
    require_once __DIR__ . '/../../inc/PayrollService.php';

    echo "<p>4. Checking Auth...</p>";
    // require_permission(); // Commented out to isolate
    echo "<p>Skipped Auth Check</p>";

    echo "<p>5. Creating Layout...</p>";
    $layout = LayoutManager::create('admin');
    $db = $conn;

    echo "<p>6. Instantiating Services...</p>";
    $hrService = new HRService($db);
    $systemUserService = new SystemUserService($db);
    $payrollService = new PayrollService($db);

    echo "<p>7. Checking View Logic...</p>";
    $current_view = $_GET['view'] ?? 'hr';
    $defined_roles = [1 => ['label'=>'Admin', 'name'=>'admin'], 2 => ['label'=>'Staff', 'name'=>'staff']];
    $grades = [['id'=>1, 'grade_name'=>'Grade 1', 'min_salary'=>10000, 'max_salary'=>50000]];

    echo "<p>8. Fetching Data...</p>";
    if ($current_view === 'hr') {
        echo "Fetching employees... ";
        $employees = $hrService->getEmployees();
        echo "Count: " . count($employees);
    } else {
        echo "Fetching users... ";
        $users = $systemUserService->getSystemUsers();
        echo "Count: " . count($users);
    }
    
    echo "<h2 style='color:green'>SUCCESS: Logic Executed Without Fatal Error</h2>";

} catch (Throwable $e) {
    echo "<h2 style='color:red'>FATAL ERROR</h2>";
    echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
?>
