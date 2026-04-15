<?php
/**
 * Authentication Check - Include at top of protected pages
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setFlash('Please login to access this page.', 'warning');
    redirect('login.php');
}

// Get current user info
$current_user = [
    'id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? 'Guest',
    'role' => $_SESSION['role'] ?? 'ADMIN'
];
?>
