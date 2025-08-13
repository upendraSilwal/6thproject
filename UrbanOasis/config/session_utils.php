<?php
/**
 * Session Management Utility Functions
 * Centralized session handling to reduce redundancy
 */

/**
 * Check if user is logged in
 * @return bool True if user is logged in
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if admin is logged in
 * @return bool True if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

/**
 * Redirect to login if user is not logged in
 * @param string $loginPage Login page URL
 * @param string $redirectParam Parameter name for redirect URL
 */
function requireUserLogin($loginPage = 'login.php', $redirectParam = 'redirect') {
    if (!isUserLoggedIn()) {
        $currentPage = $_SERVER['REQUEST_URI'];
        header("Location: $loginPage?$redirectParam=" . urlencode($currentPage));
        exit();
    }
}

/**
 * Redirect to admin login if admin is not logged in
 * @param string $loginPage Admin login page URL
 */
function requireAdminLogin($loginPage = 'admin/login.php') {
    if (!isAdminLoggedIn()) {
        header("Location: $loginPage");
        exit();
    }
}

/**
 * Destroy user session and redirect
 * @param string $redirectPage Page to redirect after logout
 */
function logoutUser($redirectPage = 'index.php') {
    session_destroy();
    header("Location: $redirectPage");
    exit();
}

/**
 * Destroy admin session and redirect
 * @param string $redirectPage Page to redirect after logout
 */
function logoutAdmin($redirectPage = 'login.php') {
    session_destroy();
    header("Location: $redirectPage");
    exit();
}

/**
 * Set flash message in session
 * @param string $type Message type (success, error, info, warning)
 * @param string $message Message content
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message from session
 * @return array|null Flash message array or null if no message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Start secure session with proper configuration
 */
function startSecureSession() {
    if (session_status() == PHP_SESSION_NONE) {
        // Configure session security
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

/**
 * Initialize session with security headers
 */
function initializeSecureSession() {
    // Only set caching headers if session is not already started
    if (session_status() == PHP_SESSION_NONE) {
        // Prevent browser caching for authenticated pages
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    startSecureSession();
}

?>
