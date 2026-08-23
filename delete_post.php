<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (!isset($_POST['post_id'])) {
    echo json_encode(['success' => false, 'error' => 'Post ID is required']);
    exit;
}

$post_id = (int)$_POST['post_id'];

try {
    // First verify the post belongs to the user
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        throw new Exception("Post not found");
    }
    
    if ($post['user_id'] != $_SESSION['user_id']) {
        throw new Exception("You can only delete your own posts");
    }
    
    // Delete associated likes and comments first
    $likes_stmt = $pdo->prepare("DELETE FROM likes WHERE post_id = ?");
    $likes_stmt->execute([$post_id]);
    
    $comments_stmt = $pdo->prepare("DELETE FROM comments WHERE post_id = ?");
    $comments_stmt->execute([$post_id]);
    
    // Get image path before deleting if we need to delete the file
    $stmt = $pdo->prepare("SELECT image_path FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    // Delete the post
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    
    // Delete the image file if it exists
    if (!empty($post['image_path'])) {
        $file_path = __DIR__ . '/' . $post['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
