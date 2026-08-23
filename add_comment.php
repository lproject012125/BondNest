<?php
session_start();
require_once 'db_connection.php';


// Set content type to JSON
header('Content-Type: application/json');

// Log request data for debugging
error_log('Add comment request received: ' . json_encode($_POST));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('User not logged in');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check for required parameters
if (!isset($_POST['post_id']) || !isset($_POST['content']) || empty(trim($_POST['content']))) {
    error_log('Missing or empty parameters: ' . json_encode($_POST));
    echo json_encode(['success' => false, 'error' => 'Invalid request - missing parameters']);
    exit;
}

$post_id = (int)$_POST['post_id'];
$user_id = $_SESSION['user_id'];
$content = trim($_POST['content']);

// Sanitize content to prevent XSS
$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

try {
    // Verify post exists
    $check_post = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
    if (!$check_post) {
        error_log('Prepare failed');
        throw new Exception('Prepare failed');
    }
    
    $check_post->execute([$post_id]);
    
    if ($check_post->rowCount() === 0) {
        error_log("Post not found with ID: $post_id");
        throw new Exception("Post not found");
    }
    
    // Insert comment
    $insert_stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    if (!$insert_stmt) {
        error_log('Insert prepare failed');
        throw new Exception('Insert prepare failed');
    }
    
    $insert_stmt->execute([$post_id, $user_id, $content]);
    
    $comment_id = $pdo->lastInsertId();
    
    // Get the newly created comment with user information
    $get_comment = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.profile_picture 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    
    if (!$get_comment) {
        error_log('Get comment prepare failed');
        throw new Exception('Get comment prepare failed');
    }
    
    $get_comment->execute([$comment_id]);
    $comment = $get_comment->fetch();
    
    // Get updated comment count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) AS comment_count FROM comments WHERE post_id = ?");
    $count_stmt->execute([$post_id]);
    $count_data = $count_stmt->fetch();
    $comment_count = $count_data['comment_count'];
    
    error_log("Comment added successfully: $comment_id");
    
    echo json_encode([
        'success' => true,
        'comment' => $comment,
        'new_count' => $comment_count
    ]);
    
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    error_log('Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?> 
