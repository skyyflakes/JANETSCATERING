<?php
/**
 * Index Page - Janet's Quality Catering System
 * Redirects to login or dashboard based on session
 */
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
?>
