/**
 * Issues a warning for a post and stores the warning details
 *
 * @param int $post_id The ID of the post to warn
 * @param int $admin_id The ID of the admin issuing the warning
 * @param string $reason The reason for the warning
 * @return bool|array Returns false on failure, or an array with success status and message
 */
function warnPost($post_id, $admin_id, $reason) {
    global $connection, $db_driver;
    
    // Step 1: Get the post data
    $stmt = $connection->prepare("
        SELECT p.*, u.first_name, u.last_name, u.profile_picture, 
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    
    if (!$stmt) {
        return ['success' => false, 'message' => 'Prepare failed: ' . print_r($connection->errorInfo(), true)];
    }
    
    if (!$stmt->execute([$post_id])) {
        return ['success' => false, 'message' => 'Execute failed: ' . print_r($stmt->errorInfo(), true)];
    }
    
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        return ['success' => false, 'message' => 'Post not found'];
    }
    $user_id = $post['user_id'];
    
    // Check if table exists, create if not
    if ($db_driver === 'pgsql') {
        $query = "CREATE TABLE IF NOT EXISTS warnings (
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
            is_active SMALLINT DEFAULT 1
        )";
    } else {
        $query = "CREATE TABLE IF NOT EXISTS warnings (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            original_post_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            admin_id INT(11) NOT NULL,
            content TEXT NOT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            likes INT(11) DEFAULT 0,
            comment_count INT(11) DEFAULT 0,
            profile_picture VARCHAR(255) DEFAULT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            warning_reason TEXT NOT NULL,
            created_at DATETIME DEFAULT NULL,
            warned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            INDEX (user_id),
            INDEX (original_post_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }
    
    if (!$connection->query($query)) {
        return ['success' => false, 'message' => 'Failed to create warnings table: ' . print_r($connection->errorInfo(), true)];
    }
    
    // Step 2: Insert into warnings table
    $stmt = $connection->prepare("
        INSERT INTO warnings 
        (original_post_id, user_id, admin_id, content, image_path, likes, comment_count, profile_picture, first_name, last_name, warning_reason, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        return ['success' => false, 'message' => 'Prepare failed for insert: ' . print_r($connection->errorInfo(), true)];
    }
    
    // Make sure all data values exist to avoid null binding issues
    $content = $post['content'] ?? '';
    $image_path = $post['image_path'] ?? NULL;
    $likes_count = $post['likes_count'] ?? 0;
    $comment_count = $post['comment_count'] ?? 0;
    $profile_picture = $post['profile_picture'] ?? NULL;
    $first_name = $post['first_name'] ?? '';
    $last_name = $post['last_name'] ?? '';
    $created_at = $post['created_at'] ?? date('Y-m-d H:i:s');
    
    if (!$stmt->execute([$post_id, $user_id, $admin_id, $content, $image_path, $likes_count, $comment_count, $profile_picture, $first_name, $last_name, $reason, $created_at])) {
        return ['success' => false, 'message' => 'Failed to save warning: ' . print_r($stmt->errorInfo(), true)];
    }
    
    $warning_id = $connection->lastInsertId();
    
    // Step 3: Create a notification for the user
    $post_data = json_encode([
        'image_path' => $post['image_path'],
        'likes' => $post['likes_count'],
        'comment_count' => $post['comment_count'],
        'profile_picture' => $post['profile_picture'],
        'first_name' => $post['first_name'],
        'last_name' => $post['last_name']
    ]);
    
    $notification_message = "Your post has received a warning from an administrator. Reason: $reason";
    
    $notification_stmt = $connection->prepare("
        INSERT INTO notifications (user_id, type, message, reference_id, is_read, created_at)
        VALUES (?, 'post_warning', ?, ?, 0, NOW())
    ");
    
    if (!$notification_stmt) {
        return ['success' => false, 'message' => 'Prepare failed for notification: ' . print_r($connection->errorInfo(), true)];
    }
    
    if (!$notification_stmt->execute([$user_id, $notification_message, $warning_id])) {
        return ['success' => false, 'message' => 'Warning created but notification failed: ' . print_r($notification_stmt->errorInfo(), true)];
    }
    
    // Step 4: Return success
    return [
        'success' => true, 
        'message' => 'Warning issued successfully',
        'warning_id' => $warning_id
    ];
} 