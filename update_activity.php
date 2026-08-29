<?php
session_start();

require_once 'db_connection.php';
date_default_timezone_set('UTC');

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['status' => 'error', 'message' => 'Not logged in']));
}



// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$status = isset($data['status']) ? $data['status'] : 'active';

// Update user's last activity timestamp
$user_id = $_SESSION['user_id'];

// Different SQL based on status
if ($status === 'offline') {
    // If offline, set the last_activity to now but also set a user_status field
    $sql = "UPDATE users SET last_activity = NOW(), user_status = 'offline' WHERE id = ?";
} else if ($status === 'inactive') {
    // If inactive (tab not focused), set a status but keep last_activity for timing
    $sql = "UPDATE users SET last_activity = NOW(), user_status = 'inactive' WHERE id = ?";
} else {
    // If active, update last_activity and status
    $sql = "UPDATE users SET last_activity = NOW(), user_status = 'active' WHERE id = ?";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);

// Return success status
echo json_encode([
    'status' => 'success', 
    'timestamp' => date('Y-m-d H:i:s'),
    'user_status' => $status
]);

?> 
