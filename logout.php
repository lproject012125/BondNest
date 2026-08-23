<?php
// Start the session
session_start();



// Update user status to 'offline' before logout
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Connect to the database
  require_once 'db_connection.php';
    
    // Update user status to offline
    $sql = "UPDATE users SET user_status = 'offline' WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Clear any output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Set cache-control headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page
header("Location: process_login.php");
exit();
?> 