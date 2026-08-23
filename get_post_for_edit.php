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

// Check if post ID is provided
if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
    exit();
}

$post_id = intval($_GET['post_id']);
$user_id = $_SESSION['user_id'];



// Get post with user info and verify ownership
$post_query = "
    SELECT p.*, u.username, u.first_name, u.last_name, u.profile_picture
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ? AND p.user_id = ?
";

$stmt = $pdo->prepare($post_query);
$stmt->execute([$post_id, $user_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    // Check if the post exists but belongs to someone else
    $check_query = "SELECT id FROM posts WHERE id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$post_id]);
    $check_result = $check_stmt->fetch();
    
    if ($check_result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'You can only edit your own posts']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Post not found']);
    }
    exit();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['success' => true, 'post' => $post]);
?> 
