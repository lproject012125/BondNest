<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');



$response = ['status' => 'error', 'message' => ''];

try {
    // Check session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in');
    }

    // Get post content
    $content = trim($_POST['post-content'] ?? '');
    
    // Check if there's a file being uploaded
    $has_image = isset($_FILES['post-image']) && $_FILES['post-image']['error'] === UPLOAD_ERR_OK;
    
    // Ensure either content or image is provided
    if (empty($content) && !$has_image) {
        throw new Exception('Post must have either text or an image');
    }

    // Handle file upload
    $image_path = null;
    if ($has_image) {
        $posts_upload_dir = $upload_dir . '/posts/';
        if (!file_exists($posts_upload_dir)) {
            if (!mkdir($posts_upload_dir, 0777, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        // Validate image
        $file_info = getimagesize($_FILES['post-image']['tmp_name']);
        if (!$file_info) {
            throw new Exception('Uploaded file is not an image');
        }

        $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if (!in_array($file_info[2], $allowed_types)) {
            throw new Exception('Only JPG, PNG, and GIF images are allowed');
        }

        // Generate filename
        $file_ext = image_type_to_extension($file_info[2]);
        $filename = uniqid('post_', true) . $file_ext;
        $destination = $posts_upload_dir . $filename;

        if (!move_uploaded_file($_FILES['post-image']['tmp_name'], $destination)) {
            throw new Exception('Failed to move uploaded file');
        }

        $image_path = 'uploads/posts/' . $filename;
    }

    // Insert post
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, image_path, created_at, status) VALUES (?, ?, ?, ?, 'posted')");
    if (!$stmt) {
        throw new Exception('Prepare failed');
    }

    $stmt->execute([$_SESSION['user_id'], $content, $image_path, gmdate('Y-m-d H:i:s')]);

    // Get user info
    $user_stmt = $pdo->prepare("SELECT first_name, last_name, username, profile_picture FROM users WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user = $user_stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    $response = [
        'status' => 'success',
        'post' => [
            'id' => $pdo->lastInsertId(),
            'content' => htmlspecialchars($content),
            'image_path' => $image_path,
            'created_at' => date('Y-m-d H:i:s'),
            'user' => [
                'first_name' => htmlspecialchars($user['first_name']),
                'last_name' => htmlspecialchars($user['last_name']),
                'username' => htmlspecialchars($user['username']),
                'profile_picture' => $user['profile_picture'] ?: './web-images/default_pfp.jpg'
            ]
        ]
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Post creation error: ' . $e->getMessage());
}

echo json_encode($response);
?>
