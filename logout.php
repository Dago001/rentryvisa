<?php
require_once('security.php');
require_once('db/db.php');

// Log the logout action if user was logged in
if (isset($_SESSION['username'])) {
    $logoutUser = $_SESSION['username'];
    $logoutName = $_SESSION['full_name'] ?? $logoutUser;
    $logoutRole = $_SESSION['role'] ?? 'User';
    logActivity($conn, "LOGOUT", "User $logoutName ($logoutUser) logged out of the system", $logoutUser);
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: index");
exit();
?>
