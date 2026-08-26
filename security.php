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
header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");

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
?>
