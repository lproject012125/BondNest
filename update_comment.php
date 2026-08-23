<?php
session_start();
require_once 'db_connection.php';


// Set content type to JSON
header('Content-Type: application/json');

// Log the request for debugging
error_log('Update comment request received: ' . json_encode($_POST));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('User not logged in');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['comment_id']) || !isset($_POST['content'])) {
    error_log('Missing parameters: ' . json_encode($_POST));
    echo json_encode(['success' => false, 'error' => 'Invalid request - missing parameters']);
    exit;
}

$comment_id = (int)$_POST['comment_id'];
$content = trim($_POST['content']);

error_log("Processing comment ID: $comment_id, Content: $content");

if (empty($content)) {
    error_log('Empty content');
    echo json_encode(['success' => false, 'error' => 'Comment content cannot be empty']);
    exit;
}

try {
    // First verify the comment belongs to the user
    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
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

    if ($comment['user_id'] != $_SESSION['user_id']) {
        error_log("User ID mismatch: Comment user_id: {$comment['user_id']}, Session user_id: {$_SESSION['user_id']}");
        throw new Exception("You can only edit your own comments");
    }

    // Update the comment
    $update_stmt = $pdo->prepare("UPDATE comments SET content = ?, updated_at = NOW() WHERE id = ?");
    if (!$update_stmt) {
        error_log('Update prepare failed');
        throw new Exception('Update prepare failed');
    }

    $update_stmt->execute([$content, $comment_id]);

    if ($update_stmt->rowCount() === 0) {
        error_log("No rows were updated for comment ID: $comment_id");
    }

    error_log("Comment updated successfully: $comment_id");
    echo json_encode([
        'success' => true,
        'comment_id' => $comment_id,
        'content' => htmlspecialchars($content)
    ]);

} catch (Exception $e) {
    error_log('Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    error_log('Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
