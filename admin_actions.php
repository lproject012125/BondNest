<?php

require_once 'db_connection.php';
// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle post warning action
if (isset($_POST['action']) && $_POST['action'] === 'warn_post') {
    // Log the request for debugging
    error_log("Warning request received: " . json_encode($_POST));
    
    // Check if admin is logged in
    if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }

    // Validate inputs
    if (!isset($_POST['post_id']) || !isset($_POST['reason']) || empty($_POST['reason'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    $post_id = intval($_POST['post_id']);
    $admin_id = $_SESSION['user_id'];
    $reason = $_POST['reason'];
    
    // Log the parameters
    error_log("Warning parameters - Post ID: $post_id, Admin ID: $admin_id, Reason: $reason");

    // Include admin functions if not already included
    if (!function_exists('warnPost')) {
        include_once 'admin_functions.php';
    }

    try {
        // Issue the warning
        $result = warnPost($post_id, $admin_id, $reason);
        
        // Log the result
        error_log("Warning result: " . json_encode($result));
        
        // Return the result
        echo json_encode($result);
    } catch (Exception $e) {
        // Log and return any exceptions
        error_log("Warning exception: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
    exit();
} 