<?php
// =====================================================================
// SESSION BOOTSTRAP + ROLE GUARDS
// =====================================================================

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Require any logged-in user.
 */
function require_login() {
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }
}

/**
 * Require a specific role ('admin' | 'teacher' | 'student').
 * Sends to their own dashboard if role mismatched.
 */
function require_role($role) {
    require_login();
    if (current_role() !== $role) {
        flash('error', 'You are not authorized to access that page.');
        redirect_dashboard();
    }
}

function require_any_role(array $roles) {
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        flash('error', 'You are not authorized to access that page.');
        redirect_dashboard();
    }
}

/**
 * Send user to the correct dashboard based on their role.
 */
function redirect_dashboard() {
    if (!is_logged_in()) {
        redirect('auth/login.php');
    }
    // Force password change first
    global $pdo;
    $u = $pdo->prepare("SELECT must_change_password FROM users WHERE id=?");
    $u->execute([user_id()]);
    if ($u->fetchColumn()) {
        redirect('auth/change_password.php');
    }
    switch (current_role()) {
        case 'admin':   redirect('admin/dashboard.php');   break;
        case 'teacher': redirect('teacher/dashboard.php'); break;
        case 'student': redirect('student/dashboard.php'); break;
        default:
            $_SESSION = [];
            session_destroy();
            redirect('auth/login.php');
    }
}

// Auto-logout after 2 hours of inactivity
if (is_logged_in()) {
    $timeout = 7200;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        $_SESSION = [];
        session_destroy();
        redirect('auth/login.php');
    }
    $_SESSION['last_activity'] = time();
}








// Enforce password change on protected pages (except change page itself)
if (is_logged_in()) {
    $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($currentScript !== 'change_password.php') {
        global $pdo;
        $u = $pdo->prepare("SELECT must_change_password FROM users WHERE id=?");
        $u->execute([user_id()]);
        if ($u->fetchColumn()) {
            redirect('auth/change_password.php');
        }
    }
}