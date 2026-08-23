<?php
// Set content type to JSON
require_once 'db_connection.php';

header('Content-Type: application/json');

// Enable full error reporting for debugging
try {
    // Start session
    session_start();
    
    // Check if user is logged in as admin
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
    
    // Get POST parameters
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
    $admin_id = $_SESSION['user_id'];
    
    // Validate inputs
    if ($post_id <= 0 || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
        exit;
    }
    
    
    // Get post data
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.last_name, u.profile_picture, 
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement failed']);
        exit;
    }
    
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    $user_id = $post['user_id'];
    
    // Create warnings table if not exists
    global $db_driver;
    if ($db_driver === 'pgsql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS warnings (
                id SERIAL PRIMARY KEY,
                original_post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                admin_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                image_path VARCHAR(255) DEFAULT NULL,
                likes INTEGER DEFAULT 0,
                comment_count INTEGER DEFAULT 0,
                profile_picture VARCHAR(255) DEFAULT NULL,
                first_name VARCHAR(100) DEFAULT NULL,
                last_name VARCHAR(100) DEFAULT NULL,
                warning_reason TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT NULL,
                warned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_warnings_user_id ON warnings (user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_warnings_original_post_id ON warnings (original_post_id)");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS warnings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                original_post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                admin_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                image_path VARCHAR(255) DEFAULT NULL,
                likes INTEGER DEFAULT 0,
                comment_count INTEGER DEFAULT 0,
                profile_picture VARCHAR(255) DEFAULT NULL,
                first_name VARCHAR(100) DEFAULT NULL,
                last_name VARCHAR(100) DEFAULT NULL,
                warning_reason TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT NULL,
                warned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_active TINYINT(1) DEFAULT 1,
                INDEX idx_warnings_user_id (user_id),
                INDEX idx_warnings_original_post_id (original_post_id)
            )
        ");
    }
    
    // Insert into warnings table
    $stmt = $pdo->prepare("
        INSERT INTO warnings 
        (original_post_id, user_id, admin_id, content, image_path, likes, comment_count, profile_picture, first_name, last_name, warning_reason, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement failed for insert']);
        exit;
    }
    
    // Default empty values to prevent null errors
    $content = $post['content'] ?? '';
    $image_path = $post['image_path'] ?? '';
    $likes = $post['likes_count'] ?? 0;
    $comments = $post['comment_count'] ?? 0;
    $profile_pic = $post['profile_picture'] ?? '';
    $first_name = $post['first_name'] ?? '';
    $last_name = $post['last_name'] ?? '';
    $created_at = $post['created_at'] ?? date('Y-m-d H:i:s');
    
    // Print all types and values for debugging
    error_log("Data types for bind_param: post_id:$post_id (int), user_id:$user_id (int), admin_id:$admin_id (int), content:$content (string), image_path:$image_path (string), likes:$likes (int), comments:$comments (int), profile_pic:$profile_pic (string), first_name:$first_name (string), last_name:$last_name (string), reason:$reason (string), created_at:$created_at (string)");
    
    $stmt->execute([
        $post_id, 
        $user_id, 
        $admin_id, 
        $content, 
        $image_path, 
        $likes, 
        $comments, 
        $profile_pic, 
        $first_name, 
        $last_name, 
        $reason, 
        $created_at
    ]);
    
    $warning_id = $pdo->lastInsertId();
    
    // IMPORTANT: Record this warning in the admin_actions table to update stats
    $action_type = 'warn';
    $stmt_admin_action = $pdo->prepare("
        INSERT INTO admin_actions (admin_id, post_id, action_type, comment) 
        VALUES (?, ?, ?, ?)
    ");
    
    if (!$stmt_admin_action) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement failed for admin action']);
        exit;
    }
    
    $stmt_admin_action->execute([$admin_id, $post_id, $action_type, $reason]);
    
    // Create notification
    $notification_message = "Your post has received a warning from an administrator. Reason: $reason";
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, reference_id, is_read, created_at)
        VALUES (?, 'post_warning', ?, ?, 0, NOW())
    ");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement failed for notification']);
        exit;
    }
    
    $stmt->execute([$user_id, $notification_message, $warning_id]);
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Warning issued successfully',
        'warning_id' => $warning_id
    ]);
    
} catch (Exception $e) {
    // Catch any exceptions and return as JSON error
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?> 