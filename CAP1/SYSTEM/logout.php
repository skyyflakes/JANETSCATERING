<?php
/**
 * Logout Page - Janet's Quality Catering System
 */
session_start();
require_once 'config/database.php';

// Clear all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
