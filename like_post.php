<?php
session_start();
require_once 'db_connection.php';


// Set content type to JSON
header('Content-Type: application/json');

// Log request for debugging
error_log('Like request received: ' . json_encode($_POST));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('User not logged in');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check for required parameters
if (!isset($_POST['post_id']) || !isset($_POST['action']) || 
    !in_array($_POST['action'], ['like', 'unlike'])) {
    error_log('Invalid request parameters: ' . json_encode($_POST));
    echo json_encode(['success' => false, 'error' => 'Invalid request parameters']);
    exit;
}

$post_id = (int)$_POST['post_id'];
$user_id = $_SESSION['user_id'];
$action = $_POST['action'];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Verify post exists
    $check_post = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
    $check_post->execute([$post_id]);
    
    if ($check_post->fetch() === false) {
        error_log("Post not found with ID: $post_id");
        throw new Exception("Post not found");
    }
    
    // Check if like exists
    $check_like = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
    $check_like->execute([$user_id, $post_id]);
    $like_exists = $check_like->fetch() !== false;
    
    // Process like/unlike action
    if ($action === 'like' && !$like_exists) {
        // Insert new like
        $insert = $pdo->prepare("INSERT INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())");
        $success = $insert->execute([$user_id, $post_id]);
        
        if (!$success) {
            error_log('Insert failed');
            throw new Exception('Failed to add like');
        }
        
        // Update post like count
        $update = $pdo->prepare("UPDATE posts SET likes = likes + 1 WHERE id = ?");
        $update->execute([$post_id]);
        
    } elseif ($action === 'unlike' && $like_exists) {
        // Delete existing like
        $delete = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
        $success = $delete->execute([$user_id, $post_id]);
        
        if (!$success) {
            error_log('Delete failed');
            throw new Exception('Failed to remove like');
        }
        
        // Update post like count (ensure it doesn't go below 0)
        $update = $pdo->prepare("UPDATE posts SET likes = GREATEST(likes - 1, 0) WHERE id = ?");
        $update->execute([$post_id]);
    }
    
    // Get updated likes count
    $count = $pdo->prepare("SELECT likes FROM posts WHERE id = ?");
    $count->execute([$post_id]);
    $likes_count = $count->fetchColumn();
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response with updated data
    echo json_encode([
        'success' => true,
        'post_id' => $post_id,
        'action' => $action,
        'likes' => $likes_count
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    
} catch (Error $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?> 
