<?php
session_start();

require_once 'db_connection.php';

date_default_timezone_set('Asia/Manila');

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}



// Pagination settings
$posts_per_page = 9;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $posts_per_page;

// Get total number of posts for pagination
$total_posts_query = "SELECT COUNT(*) as total FROM posts";
$total_stmt = $pdo->query($total_posts_query);
$total_posts = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Get all posts with user info and status with pagination
$posts_query = "
    SELECT p.*, u.username, u.first_name, u.last_name, u.profile_picture,
           COALESCE(p.status, 'posted') as status,
           COUNT(DISTINCT c.id) AS comment_count,
           p.likes,
           EXISTS(SELECT 1 FROM likes l WHERE l.user_id = ? AND l.post_id = p.id) AS user_has_liked
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN comments c ON p.id = c.post_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($posts_query);
$stmt->execute([$_SESSION['user_id'], $posts_per_page, $offset]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($posts)) {
    $posts = [];
}

// Format dates
foreach ($posts as &$post) {
    $post['formatted_date'] = date('Y-m-d H:i:s', strtotime($post['created_at']));
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'posts' => $posts,
    'pagination' => [
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'total_posts' => $total_posts,
        'posts_per_page' => $posts_per_page
    ]
]);
?> 
