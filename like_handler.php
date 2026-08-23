<?php
session_start();

include 'db_connection.php';

// This must be the VERY FIRST output
header('Content-Type: application/json');

// Validate session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Validate input
if (!isset($_POST['post_id']) || !isset($_POST['action']) || 
    !in_array($_POST['action'], ['like', 'unlike'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$post_id = (int)$_POST['post_id'];
$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'];

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Check if like exists
    $check = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
    $check->execute([$user_id, $post_id]);
    $like_exists = $check->fetch() !== false;

    // Process like/unlike
    if ($action === 'like' && !$like_exists) {
        // Add like
        $insert = $pdo->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
        $insert->execute([$user_id, $post_id]);
        
        // Increment likes count
        $stmt = $pdo->prepare("UPDATE posts SET likes = likes + 1 WHERE id = ?");
        $stmt->execute([$post_id]);
    } elseif ($action === 'unlike' && $like_exists) {
        // Remove like
        $delete = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
        $delete->execute([$user_id, $post_id]);
        
        // Decrement likes count (but not below 0)
        $stmt = $pdo->prepare("UPDATE posts SET likes = GREATEST(likes - 1, 0) WHERE id = ?");
        $stmt->execute([$post_id]);
    }

    // Get updated like count
    $stmt = $pdo->prepare("SELECT likes FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $likes = $stmt->fetchColumn();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'action' => $action
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
