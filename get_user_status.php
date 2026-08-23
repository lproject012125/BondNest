<?php
session_start();

require_once 'db_connection.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_GET['user_id'])) {
    exit(json_encode(['status' => 'error', 'message' => 'User ID not provided']));
}



// Get user status based on last activity
$user_id = intval($_GET['user_id']);
$sql = "SELECT last_activity, user_status FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $last_activity = strtotime($row['last_activity']);
    $current_time = time();
    $diff_minutes = round(($current_time - $last_activity) / 60);
    $user_status = isset($row['user_status']) ? $row['user_status'] : 'offline';
    
    $status = [
        'user_id' => $user_id,
        'last_activity' => $row['last_activity'],
        'explicit_status' => $user_status
    ];
    
    // If user explicitly marked as offline, show that regardless of time
    if ($user_status === 'offline') {
        if ($diff_minutes < 1440) { // Less than a day
            $status['status_text'] = 'Last seen recently';
            $status['status_class'] = 'offline';
        } else {
            $days = floor($diff_minutes / 1440);
            $status['status_text'] = 'Last seen ' . $days . ' days ago';
            $status['status_class'] = 'offline';
        }
    }
    // If user is marked as inactive (tab in background)
    else if ($user_status === 'inactive') {
        if ($diff_minutes < 5) {
            $status['status_text'] = 'Away';
            $status['status_class'] = 'away';
        } else {
            // If inactive for too long, treat as offline
            $status['status_text'] = 'Last seen recently';
            $status['status_class'] = 'offline';
        }
    }
    // For active users, show status based on last activity time
    else {
        if ($diff_minutes < 1) {
            $status['status_text'] = 'Active now';
            $status['status_class'] = 'online';
        } else if ($diff_minutes < 5) {
            $status['status_text'] = 'Active ' . $diff_minutes . ' minutes ago';
            $status['status_class'] = 'recently';
        } else if ($diff_minutes < 60) {
            $status['status_text'] = 'Active ' . $diff_minutes . ' minutes ago';
            $status['status_class'] = 'away';
        } else if ($diff_minutes < 1440) { // less than a day
            $hours = floor($diff_minutes / 60);
            $status['status_text'] = 'Active ' . $hours . ' hours ago';
            $status['status_class'] = 'away';
        } else {
            $days = floor($diff_minutes / 1440);
            $status['status_text'] = 'Active ' . $days . ' days ago';
            $status['status_class'] = 'offline';
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $status]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
}

?> 
