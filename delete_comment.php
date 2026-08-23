<?php
session_start();
require_once 'db_connection.php';


// Set content type to JSON
header('Content-Type: application/json');

// Log the request for debugging
error_log('Delete comment request received: ' . json_encode($_POST));

if (!isset($_SESSION['user_id'])) {
    error_log('User not logged in');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (!isset($_POST['comment_id'])) {
    error_log('Missing comment_id parameter');
    echo json_encode(['success' => false, 'error' => 'Invalid request - missing comment_id']);
    exit;
}

$comment_id = (int)$_POST['comment_id'];
error_log("Processing delete for comment ID: $comment_id");

try {
    // First get the comment to verify ownership and get post_id
    $stmt = $pdo->prepare("SELECT user_id, post_id FROM comments WHERE id = ?");
    if (!$stmt) {
        error_log('Prepare failed');
        throw new Exception('Prepare failed');
    }
    
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        error_log("Comment not found with ID: $comment_id");
        throw new Exception("Comment not found");
    }
    
    // Check if comment belongs to user
    if ($comment['user_id'] != $_SESSION['user_id']) {
        error_log("User ID mismatch: Comment user_id: {$comment['user_id']}, Session user_id: {$_SESSION['user_id']}");
        throw new Exception("You can only delete your own comments");
    }
    
    // Store post_id for reference (might be needed for counts)
    $post_id = $comment['post_id'];
    error_log("Comment belongs to post ID: $post_id");
    
    // Delete the comment
    $delete_stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    if (!$delete_stmt) {
        error_log('Delete prepare failed');
        throw new Exception('Delete prepare failed');
    }
    
    $delete_stmt->execute([$comment_id]);
    
    if ($delete_stmt->rowCount() === 0) {
        error_log("No rows were deleted for comment ID: $comment_id");
        throw new Exception("Comment could not be deleted");
    }
    
    // Get updated comment count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) AS comment_count FROM comments WHERE post_id = ?");
    $count_stmt->execute([$post_id]);
    $count_data = $count_stmt->fetch();
    $comment_count = $count_data['comment_count'];
    
    error_log("Comment deleted successfully: $comment_id, New count: $comment_count");
    
    echo json_encode([
        'success' => true,
        'comment_id' => $comment_id,
        'post_id' => $post_id,
        'comment_count' => $comment_count
    ]);
    
} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    error_log('Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?> 
