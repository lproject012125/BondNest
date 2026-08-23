<?php
session_start();

require_once 'db_connection.php';

date_default_timezone_set('Asia/Manila');

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if post ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
    exit();
}

$post_id = intval($_GET['id']);



// Get post with user info
$post_query = "
    SELECT p.*, u.username, u.first_name, u.last_name, u.profile_picture,
           COALESCE(p.status, 'posted') as status,
           COUNT(DISTINCT c.id) AS comment_count,
           p.likes,
           EXISTS(SELECT 1 FROM likes l WHERE l.user_id = ? AND l.post_id = p.id) AS user_has_liked
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN comments c ON p.id = c.post_id
    WHERE p.id = ?
    GROUP BY p.id, u.username, u.first_name, u.last_name, u.profile_picture
";

$stmt = $pdo->prepare($post_query);
$stmt->execute([$_SESSION['user_id'], $post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit();
}

// Format date
$post['formatted_date'] = date('Y-m-d H:i:s', strtotime($post['created_at']));

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['success' => true, 'post' => $post]);
?>
