<?php
/**
 * api/v1/device-detection.php
 * Device detection and mobile responsiveness API
 * 
 * Endpoints:
 * - GET /device-detection.php?action=detect — Return device info
 * - POST /device-detection.php — Get/set pause state
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "device": {
 *     "type": "phone|tablet|desktop",
 *     "breakpoint": "mobile|tablet|desktop",
 *     "userAgent": "...",
 *     "viewport": { "width": 375, "height": 667 },
 *     "touchCapable": true,
 *     "orientation": "portrait|landscape"
 *   }
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'detect';

class DeviceDetectionAPI
{
    /**
     * Detect device type from User-Agent
     */
    public static function detectDeviceType(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $isPhone = preg_match('/iPhone|Android.*Mobile|Mobile.*Android|Windows Phone|BlackBerry/', $userAgent);
        $isTablet = preg_match('/iPad|Android(?!.*Mobile)|Tablet/', $userAgent) && !$isPhone;
        
        $type = 'desktop';
        if ($isPhone) $type = 'phone';
        elseif ($isTablet) $type = 'tablet';
        
        return [
            'type' => $type,
            'userAgent' => $userAgent,
            'isPhone' => $isPhone,
            'isTablet' => $isTablet,
            'isDesktop' => !($isPhone || $isTablet)
        ];
    }

    /**
     * Determine breakpoint based on viewport width
     */
    public static function getBreakpoint(int $viewportWidth): string
    {
        if ($viewportWidth <= 640) return 'mobile';
        if ($viewportWidth <= 1024) return 'tablet';
        return 'desktop';
    }

    /**
     * Get pause state from session
     */
    public static function getPauseState(): array
    {
        return [
            'isPaused' => $_SESSION['auto_refresh_paused'] ?? false,
            'pausedAt' => $_SESSION['auto_refresh_paused_at'] ?? null,
            'reason' => $_SESSION['auto_refresh_pause_reason'] ?? null
        ];
    }

    /**
     * Set pause state in session
     */
    public static function setPauseState(bool $paused, string $reason = ''): array
    {
        $_SESSION['auto_refresh_paused'] = $paused;
        $_SESSION['auto_refresh_paused_at'] = $paused ? date('Y-m-d H:i:s') : null;
        $_SESSION['auto_refresh_pause_reason'] = $reason;
        
        return self::getPauseState();
    }

    /**
     * Get device info from client
     */
    public static function getDeviceInfo(): array
    {
        $viewportWidth = (int)($_GET['vw'] ?? $_POST['vw'] ?? 1920);
        $viewportHeight = (int)($_GET['vh'] ?? $_POST['vh'] ?? 1080);
        $touchCapable = in_array(($_GET['touch'] ?? $_POST['touch'] ?? 'false'), ['true', '1', 'yes']);
        $isPortrait = $viewportHeight > $viewportWidth;
        
        $device = self::detectDeviceType();
        $breakpoint = self::getBreakpoint($viewportWidth);
        
        return [
            'type' => $device['type'],
            'breakpoint' => $breakpoint,
            'userAgent' => $device['userAgent'],
            'viewport' => [
                'width' => $viewportWidth,
                'height' => $viewportHeight
            ],
            'touchCapable' => $touchCapable,
            'orientation' => $isPortrait ? 'portrait' : 'landscape',
            'isPhone' => $device['isPhone'],
            'isTablet' => $device['isTablet'],
            'isDesktop' => $device['isDesktop']
        ];
    }

    /**
     * Recommend pause state based on device type
     */
    public static function recommendPauseState(array $deviceInfo): array
    {
        $shouldPause = false;
        $reason = '';
        
        // Pause on mobile phones
        if ($deviceInfo['type'] === 'phone') {
            $shouldPause = true;
            $reason = 'Mobile phone detected - reduced refresh frequency to save battery';
        }
        // Pause if on input/form (prevent auto-refresh while typing)
        elseif ($_GET['onInput'] ?? false) {
            $shouldPause = true;
            $reason = 'Form input detected - refresh paused to prevent interruption';
        }
        
        return [
            'shouldPause' => $shouldPause,
            'reason' => $reason,
            'recommendation' => $shouldPause ? 'pause' : 'resume'
        ];
    }
}

// ═════════════════════════════════════════════════════════════════════════
// ROUTE HANDLING
// ═════════════════════════════════════════════════════════════════════════

$response = [
    'success' => false,
    'action' => $action,
    'data' => null,
    'error' => null
];

try {
    switch ($action) {
        case 'detect':
            $deviceInfo = DeviceDetectionAPI::getDeviceInfo();
            $response['success'] = true;
            $response['data'] = [
                'device' => $deviceInfo,
                'pauseState' => DeviceDetectionAPI::getPauseState(),
                'recommendation' => DeviceDetectionAPI::recommendPauseState($deviceInfo)
            ];
            break;

        case 'pause':
            $reason = $_POST['reason'] ?? $_GET['reason'] ?? 'User initiated';
            $pauseState = DeviceDetectionAPI::setPauseState(true, $reason);
            $response['success'] = true;
            $response['data'] = $pauseState;
            break;

        case 'resume':
            $pauseState = DeviceDetectionAPI::setPauseState(false);
            $response['success'] = true;
            $response['data'] = $pauseState;
            break;

        case 'toggle':
            $currentState = DeviceDetectionAPI::getPauseState();
            $newState = !$currentState['isPaused'];
            $reason = $_POST['reason'] ?? $_GET['reason'] ?? ($newState ? 'Paused' : 'Resumed');
            $pauseState = DeviceDetectionAPI::setPauseState($newState, $reason);
            $response['success'] = true;
            $response['data'] = $pauseState;
            break;

        case 'getState':
            $response['success'] = true;
            $response['data'] = DeviceDetectionAPI::getPauseState();
            break;

        default:
            $response['error'] = "Unknown action: {$action}";
            http_response_code(400);
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code(500);
}

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
