<?php
session_start();
require_once 'db_connection.php';

// Make sure no output is sent before headers
ob_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['post_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Post ID is required']));
}

$post_id = intval($_GET['post_id']);

try {
    // Get comments with user info
    $sql = "SELECT c.*, u.first_name, u.last_name, u.profile_picture 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = ?
            ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }
    
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process comments to preserve newlines
    foreach ($comments as &$comment) {
        // Keep original line breaks intact without any special encoding
        // JavaScript will handle the line break conversion
        // This prevents double-encoding issues
        $comment['content'] = $comment['content'];
    }

    // Clear any output buffer
    ob_end_clean();
    
    die(json_encode([
        'success' => true, 
        'comments' => $comments
    ]));
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    die(json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]]));
}
