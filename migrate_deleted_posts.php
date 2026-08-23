<?php
// This script migrates existing post_deleted notifications to the new deleted_posts table
session_start();

require_once 'db_connection.php';

date_default_timezone_set('Asia/Manila');

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die("Access denied. Admin privileges required.");
}

// Database connection


echo "<h1>Deleted Posts Migration Tool</h1>";
echo "<p>This script will migrate existing post_deleted notifications to the new deleted_posts table.</p>";

// Get all post_deleted notifications
$query = "SELECT * FROM notifications WHERE type = 'post_deleted'";
$result = $pdo->query($query);

$migrated = 0;
$skipped = 0;
$errors = 0;

$notifications = $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];

if (!empty($notifications)) {
    echo "<p>Found " . count($notifications) . " notifications to process.</p>";
    
    foreach ($notifications as $notification) {
        echo "<hr><p>Processing notification ID: " . $notification['id'] . "</p>";
        
        // Skip if reference_id already points to a deleted_posts entry
        $check_query = "SELECT COUNT(*) as count FROM deleted_posts WHERE id = " . intval($notification['reference_id']);
        $check_result = $pdo->query($check_query);
        $count = $check_result->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            echo "<p style='color:orange'>⚠️ Already migrated (reference_id points to deleted_posts). Skipping.</p>";
            $skipped++;
            continue;
        }
        
        // Extract the post data from the notification message
        $post_content = "";
        if (preg_match('/Post content: "(.*?)"/s', $notification['message'], $content_matches)) {
            $post_content = $content_matches[1];
        }
        
        // Extract the reason
        $reason = "";
        if (preg_match('/Reason: (.*?)(?=Post content:|$)/s', $notification['message'], $reason_matches)) {
            $reason = trim($reason_matches[1]);
        }
        
        // Extract the post data
        $post_data = [];
        if (preg_match('/Post data: (\{.*\})/s', $notification['message'], $data_matches)) {
            $json_string = $data_matches[1];
            $post_data = json_decode($json_string, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "<p style='color:red'>❌ Error parsing JSON data: " . json_last_error_msg() . "</p>";
                $errors++;
                continue;
            }
        } else {
            echo "<p style='color:red'>❌ No post data found in message.</p>";
            $errors++;
            continue;
        }
        
        try {
            // Insert into deleted_posts
            $insert_query = "INSERT INTO deleted_posts 
                (original_post_id, user_id, admin_id, content, image_path, likes, comment_count, 
                profile_picture, first_name, last_name, deletion_reason, created_at, deleted_at) 
                VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
            $stmt = $pdo->prepare($insert_query);
            
            // Use original reference_id as original_post_id
            $original_post_id = $notification['reference_id'];
            $user_id = $notification['user_id'];
            $image_path = $post_data['image_path'] ?? '';
            $likes = $post_data['likes'] ?? 0;
            $comment_count = $post_data['comment_count'] ?? 0;
            $profile_picture = $post_data['profile_picture'] ?? './web-images/default-avatar.jpg';
            $first_name = $post_data['first_name'] ?? '';
            $last_name = $post_data['last_name'] ?? '';
            $deleted_at = $notification['created_at'];
            // Estimate created_at as 1 day before deletion
            $created_at = date('Y-m-d H:i:s', strtotime($deleted_at) - 86400);
            
            $stmt->execute([
                $original_post_id, 
                $user_id, 
                $post_content, 
                $image_path, 
                $likes, 
                $comment_count, 
                $profile_picture, 
                $first_name, 
                $last_name, 
                $reason,
                $created_at,
                $deleted_at
            ]);
            $deleted_post_id = $pdo->lastInsertId();
            
            // Update the notification to reference the new deleted_posts entry
            $update_query = "UPDATE notifications SET reference_id = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([$deleted_post_id, $notification['id']]);
            
            echo "<p style='color:green'>✅ Successfully migrated! New deleted_post ID: " . $deleted_post_id . "</p>";
            $migrated++;
            
        } catch (Exception $e) {
            echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }
    
    echo "<hr><h2>Migration Complete</h2>";
    echo "<p>Successfully migrated: " . $migrated . "</p>";
    echo "<p>Skipped (already migrated): " . $skipped . "</p>";
    echo "<p>Errors: " . $errors . "</p>";
    
} else {
    echo "<p>No post_deleted notifications found.</p>";
}

?> 