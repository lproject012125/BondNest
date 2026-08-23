<?php
session_start();

require_once 'db_connection.php';

date_default_timezone_set('Asia/Manila');


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Get the current user ID
$user_id = $_SESSION['user_id'];

// Get the timestamp of the last check (if provided)
$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : 0;

// Get specific post IDs if provided
$post_ids = isset($_GET['post_ids']) ? $_GET['post_ids'] : '';
$post_id_array = [];

if (!empty($post_ids)) {
    // Split the comma-separated list of post IDs
    $post_id_array = explode(',', $post_ids);
    // Sanitize the IDs to prevent SQL injection
    $post_id_array = array_map(function($id) {
        return (int)trim($id);
    }, $post_id_array);
}

// Build the query based on inputs
$query = "
    SELECT 
        p.id, 
        p.status,
        p.updated_at,
        (SELECT message FROM notifications WHERE reference_id = p.id AND type = 'post_on_hold' ORDER BY created_at DESC LIMIT 1) AS hold_message
    FROM posts p
    WHERE 1=1 
";

// Add post IDs condition if provided
if (!empty($post_id_array)) {
    $query .= " AND p.id IN (" . implode(',', $post_id_array) . ")";
} else {
    // If no specific post IDs, get posts that were updated since the last check
    if ($last_check > 0) {
        global $db_driver;
        if ($db_driver === 'pgsql') {
            $query .= " AND p.updated_at > to_timestamp(" . (int)$last_check . ")";
        } else {
            $query .= " AND p.updated_at > FROM_UNIXTIME(" . (int)$last_check . ")";
        }
    } else {
        // If no specific time either, limit to recent posts to avoid overload
        $query .= " ORDER BY p.updated_at DESC LIMIT 50";
    }
}

$stmt = $pdo->query($query);
$posts_status = [];

if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $posts_status[] = [
            'id' => $row['id'],
            'status' => $row['status'],
            'updated_at' => $row['updated_at'],
            'hold_message' => $row['hold_message'] ?? 'This post has been placed on hold by administrators. Please check your notifications for details.'
        ];
    }
}

// Return the response
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'posts' => $posts_status,
    'timestamp' => time()
]);
?> 
