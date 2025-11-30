<?php
/**
 * Role-Based Access Control (RBAC) Middleware
 * 
 * This middleware system provides secure role-based access control for PHP pages.
 * It checks if the current user's role is allowed to access a specific page.
 * 
 * Usage:
 *   require_once __DIR__ . '/role_middleware.php';
 *   allow_roles(['admin', 'employee']);
 * 
 * @package RBAC
 * @version 1.0
 */

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

/**
 * Get current user role
 * 
 * @return string|null User role or null if not set
 */
function get_user_role() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not set
 */
function get_user_id() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user full name
 * 
 * @return string|null User full name or null if not set
 */
function get_user_full_name() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['full_name'] ?? null;
}

/**
 * Get current user email
 * 
 * @return string|null User email or null if not set
 */
function get_user_email() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['user_email'] ?? $_SESSION['email'] ?? null;
}

/**
 * Check if user has a specific role
 * 
 * @param string $role Role to check
 * @return bool True if user has the role, false otherwise
 */
function has_role($role) {
    $userRole = get_user_role();
    return $userRole === $role;
}

/**
 * Check if user is admin
 * 
 * @return bool True if user is admin, false otherwise
 */
function is_admin() {
    return has_role('admin');
}

/**
 * Check if user is supplier
 * 
 * @return bool True if user is supplier, false otherwise
 */
function is_supplier() {
    return has_role('supplier');
}

/**
 * Check if user is employee
 * 
 * @return bool True if user is employee, false otherwise
 */
function is_user() {
    return has_role('employee');
}

/**
 * Allow access only to specified roles
 * 
 * This function checks if the current user's role is in the allowed roles list.
 * If not, it redirects to the no-access page.
 * 
 * Admin role always has access to everything (checked automatically).
 * 
 * @param array $allowed_roles Array of allowed roles (e.g., ['admin', 'employee'])
 * @param string $redirect_url URL to redirect to if access is denied (default: '../pages/no-access.php')
 * @return void
 * 
 * @example
 *   // Allow admin and user only
 *   allow_roles(['admin', 'employee']);
 * 
 * @example
 *   // Allow admin, employee, and supplier
 *   allow_roles(['admin', 'employee', 'supplier']);
 * 
 * @example
 *   // Admin only (explicit)
 *   allow_roles(['admin']);
 */
function allow_roles($allowed_roles = [], $redirect_url = '../pages/no-access.php') {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!is_logged_in()) {
        // Redirect to login page if not logged in
        header("Location: ../pages/login.html");
        exit;
    }
    
    // Get current user role
    $user_role = get_user_role();
    
    // Admin always has access to everything
    if ($user_role === 'admin') {
        return;
    }
    
    // Check if user role is in allowed roles
    if (!in_array($user_role, $allowed_roles)) {
        // Access denied - redirect to no-access page
        header("Location: " . $redirect_url);
        exit;
    }
}

/**
 * Require authentication (user must be logged in)
 * 
 * @param string $redirect_url URL to redirect to if not logged in
 * @return void
 */
function require_auth($redirect_url = '../pages/login.html') {
    if (!is_logged_in()) {
        header("Location: " . $redirect_url);
        exit;
    }
}

/**
 * Require admin role
 * 
 * @param string $redirect_url URL to redirect to if not admin (default: dashboard.php with error)
 * @return void
 */
function require_admin($redirect_url = null) {
    require_auth();
    
    if (!is_admin()) {
        // Default redirect to dashboard.php with access denied message
        if ($redirect_url === null) {
            redirect_access_denied('dashboard.php');
        } else {
            header("Location: " . $redirect_url);
            exit;
        }
    }
}

/**
 * Redirect user to dashboard with access denied message
 * Used when regular users try to access admin-only pages
 * 
 * @param string $redirect_url URL to redirect to (default: dashboard.php)
 * @return void
 */
function redirect_access_denied($redirect_url = 'dashboard.php') {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Redirect with error parameter
    $separator = strpos($redirect_url, '?') !== false ? '&' : '?';
    header("Location: " . $redirect_url . $separator . "error=access_denied");
    exit;
}

?>

