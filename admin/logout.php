<?php
session_start();

// Log logout activity if admin was logged in
if (isset($_SESSION['admin_id'])) {
    require_once '../config/database.php';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs 
            (admin_id, action, description, ip_address, user_agent)
            VALUES (?, 'logout', 'Admin logged out', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (Exception $e) {
        // Continue with logout even if logging fails
    }
}

// Destroy all session data
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>