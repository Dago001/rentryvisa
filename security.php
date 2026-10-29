<?php
// Ensure dedicated, writable application session storage directory
$session_dir = __DIR__ . '/storage/sessions';
if (!is_dir($session_dir)) {
    @mkdir($session_dir, 0777, true);
}
if (is_dir($session_dir) && is_writable($session_dir)) {
    session_save_path($session_dir);
}

// Secure Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    if (!@session_start()) {
        // Recover cleanly if browser sent an invalid or locked session cookie
        session_id(bin2hex(random_bytes(16)));
        @session_start();
    }
}

// Session Hijacking Protection
if (isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['ip_address']) || !isset($_SESSION['user_agent'])) {
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    } elseif ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_unset();
        session_destroy();
        header("Location: index");
        exit();
    }
}

// CSRF Token Generation
if (!isset($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
    }
}

// Global Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://challenges.cloudflare.com; frame-src 'self' https://challenges.cloudflare.com; connect-src 'self' https://challenges.cloudflare.com;");

// Global Date Formatting Helper (e.g. "21ST JUNE 2027")
function formatNISDate($dateStr) {
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return 'N/A';
    }
    $timestamp = strtotime($dateStr);
    if (!$timestamp) {
        return strtoupper($dateStr);
    }
    $day = date('j', $timestamp);
    $suffix = strtoupper(date('S', $timestamp));
    $month = strtoupper(date('F', $timestamp));
    $year = date('Y', $timestamp);
    return $day . $suffix . ' ' . $month . ' ' . $year;
}

/**
 * Resolves the client's real IP address, handling proxies and normalizing IPv6 loopback to 127.0.0.1
 */
function getClientIP() {
    $ipKeys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', $_SERVER[$key]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                if ($ip === '::1' || $ip === '0:0:0:0:0:0:0:1') {
                    return '127.0.0.1';
                }
                return $ip;
            }
        }
    }
    
    $rem = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($rem === '::1' || $rem === '0:0:0:0:0:0:0:1' || empty($rem)) {
        return '127.0.0.1';
    }
    return $rem;
}

/**
 * Standardized system activity logger recording operator, action, description, client IP, and browser agent
 */
function logActivity($conn, $action, $description, $userId = null) {
    if (!$conn) {
        return false;
    }
    
    $user = $userId ?: ($_SESSION['username'] ?? 'SYSTEM');
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssss", $user, $action, $description, $ip, $userAgent);
        $res = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $res;
    }
    return false;
}

/**
 * Parses user agent string into friendly browser and operating system summary
 */
function parseUserAgent($userAgent) {
    if (empty($userAgent)) {
        return [
            'browser' => 'System/CLI',
            'os' => 'Server',
            'device' => 'Server',
            'icon' => 'fa-server',
            'summary' => 'System Server'
        ];
    }
    
    $os = 'Unknown OS';
    if (preg_match('/windows nt 10/i', $userAgent)) $os = 'Windows 10/11';
    elseif (preg_match('/windows nt 6\.3/i', $userAgent)) $os = 'Windows 8.1';
    elseif (preg_match('/windows nt 6\.2/i', $userAgent)) $os = 'Windows 8';
    elseif (preg_match('/windows nt 6\.1/i', $userAgent)) $os = 'Windows 7';
    elseif (preg_match('/windows/i', $userAgent)) $os = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $os = 'macOS';
    elseif (preg_match('/android/i', $userAgent)) $os = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) $os = 'iOS';
    elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';
    
    $browser = 'Unknown Browser';
    if (preg_match('/edg/i', $userAgent)) $browser = 'Edge';
    elseif (preg_match('/chrome|crios/i', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/firefox|fxios/i', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/safari/i', $userAgent) && !preg_match('/chrome/i', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/opera|opr/i', $userAgent)) $browser = 'Opera';
    
    $device = 'Desktop';
    $icon = 'fa-desktop';
    if (preg_match('/mobile|iphone|ipod|android.*mobile/i', $userAgent)) {
        $device = 'Mobile';
        $icon = 'fa-mobile';
    } elseif (preg_match('/tablet|ipad|android(?!.*mobile)/i', $userAgent)) {
        $device = 'Tablet';
        $icon = 'fa-tablet';
    }
    
    return [
        'browser' => $browser,
        'os' => $os,
        'device' => $device,
        'icon' => $icon,
        'summary' => "$browser on $os"
    ];
}

/**
 * Return appropriate CSS badge class and icon for audit trail actions
 */
function getAuditBadgeClass($action) {
    $act = strtoupper(trim((string)$action));
    if (strpos($act, 'CREATE') !== false) {
        return ['class' => 'badge-create', 'icon' => 'fa-plus-circle'];
    }
    if (strpos($act, 'UPDATE') !== false || strpos($act, 'EDIT') !== false) {
        return ['class' => 'badge-update', 'icon' => 'fa-pencil'];
    }
    if (strpos($act, 'DELETE') !== false || strpos($act, 'REMOVE') !== false) {
        return ['class' => 'badge-delete', 'icon' => 'fa-trash'];
    }
    if (strpos($act, 'LOGIN_FAILED') !== false || strpos($act, 'FAILED') !== false) {
        return ['class' => 'badge-login-failed', 'icon' => 'fa-exclamation-triangle'];
    }
    if (strpos($act, 'LOGIN') !== false) {
        return ['class' => 'badge-login', 'icon' => 'fa-sign-in'];
    }
    if (strpos($act, 'LOGOUT') !== false) {
        return ['class' => 'badge-logout', 'icon' => 'fa-sign-out'];
    }
    if (strpos($act, 'RESET') !== false || strpos($act, 'PASSWORD') !== false || strpos($act, 'MFA') !== false || strpos($act, 'AUTH') !== false) {
        return ['class' => 'badge-security', 'icon' => 'fa-shield'];
    }
    if (strpos($act, 'PRINT') !== false) {
        return ['class' => 'badge-print', 'icon' => 'fa-print'];
    }
    if (strpos($act, 'VIEW') !== false || strpos($act, 'SEARCH') !== false) {
        return ['class' => 'badge-view', 'icon' => 'fa-eye'];
    }
    if (strpos($act, 'EMAIL') !== false) {
        return ['class' => 'badge-email', 'icon' => 'fa-envelope'];
    }
    return ['class' => 'badge-other', 'icon' => 'fa-circle-o'];
}
?>
