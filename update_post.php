<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (!isset($_POST['post_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$post_id = (int)$_POST['post_id'];
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === 'true';

try {
    // Verify the post belongs to the user
    $stmt = $pdo->prepare("SELECT user_id, image_path FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        throw new Exception("Post not found");
    }
    
    if ($post['user_id'] != $_SESSION['user_id']) {
        throw new Exception("You can only edit your own posts");
    }
    
    // Handle image upload/removal
    $image_path = $post['image_path'];
    $old_image_path = null;
    
    if ($remove_image && !empty($image_path)) {
        // Remove existing image
        $old_image_path = $image_path;
        $image_path = null;
    }
    
    $has_new_image = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;
    
    if ($has_new_image) {
        // If there's an old image, delete it first
        if (!empty($image_path)) {
            $old_image_path = $image_path;
        }
        
        // Upload new image
        $posts_upload_dir = $upload_dir . '/posts/';
        if (!file_exists($posts_upload_dir)) {
            mkdir($posts_upload_dir, 0777, true);
        }
        
        $file_info = getimagesize($_FILES['image']['tmp_name']);
        if (!$file_info) {
            throw new Exception('Uploaded file is not an image');
        }
        
        $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if (!in_array($file_info[2], $allowed_types)) {
            throw new Exception('Only JPG, PNG, and GIF images are allowed');
        }
        
        $file_ext = image_type_to_extension($file_info[2]);
        $filename = uniqid('post_', true) . $file_ext;
        $destination = $posts_upload_dir . $filename;
        
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        $image_path = 'uploads/posts/' . $filename;
    }
    
    // Ensure the post has either content or an image
    if (empty($content) && empty($image_path)) {
        throw new Exception('Post must have either text or an image');
    }
    
    // Update the post
    $stmt = $pdo->prepare("UPDATE posts SET content = ?, image_path = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$content, $image_path, $post_id]);
    
    // Delete old image file if it was replaced or removed
    if (!empty($old_image_path)) {
        $file_path = $upload_dir . '/posts/' . basename($old_image_path);
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Get updated post data
    $stmt = $pdo->prepare("SELECT p.*, u.first_name, u.last_name, u.profile_picture 
                                FROM posts p 
                                JOIN users u ON p.user_id = u.id 
                                WHERE p.id = ?");
    $stmt->execute([$post_id]);
    $updated_post = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'post' => $updated_post
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
