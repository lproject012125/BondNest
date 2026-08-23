<?php
session_start();

require_once 'db_connection.php';

date_default_timezone_set('Asia/Manila');

// Only allow admin access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}



// Get current stats
$stats = [];
$current_stats_stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM posts) as total_posts,
        (SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURRENT_DATE) as today_posts,
        (SELECT COUNT(*) FROM admin_actions WHERE DATE(created_at) = CURRENT_DATE) as today_actions
");
if ($current_stats_stmt) {
    $stats = $current_stats_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get stats from last month for comparison
$last_month_stats = [];
$last_month_stats_stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) < CURRENT_DATE - INTERVAL '30 day') as last_month_users,
        (SELECT COUNT(*) FROM posts WHERE DATE(created_at) < CURRENT_DATE - INTERVAL '30 day') as last_month_posts,
        (SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURRENT_DATE - INTERVAL '1 day') as yesterday_posts,
        (SELECT COUNT(*) FROM admin_actions WHERE DATE(created_at) = CURRENT_DATE - INTERVAL '1 day') as yesterday_actions
");
if ($last_month_stats_stmt) {
    $last_month_stats = $last_month_stats_stmt->fetch(PDO::FETCH_ASSOC);
}

// Calculate percentage changes
$percent_changes = [];

// Users change (from last month to now)
$users_now = $stats['total_users'] ?? 0;
$users_last_month = $last_month_stats['last_month_users'] ?? 0;
$users_new = $users_now - $users_last_month;
$percent_changes['users'] = ($users_last_month > 0) ? round(($users_new / $users_last_month) * 100) : 0;

// Posts change (from last month to now)
$posts_now = $stats['total_posts'] ?? 0;
$posts_last_month = $last_month_stats['last_month_posts'] ?? 0;
$posts_new = $posts_now - $posts_last_month;
$percent_changes['posts'] = ($posts_last_month > 0) ? round(($posts_new / $posts_last_month) * 100) : 0;

// Today's posts vs yesterday
$today_posts = $stats['today_posts'] ?? 0;
$yesterday_posts = $last_month_stats['yesterday_posts'] ?? 0;
$percent_changes['today_posts'] = ($yesterday_posts > 0) ? round((($today_posts - $yesterday_posts) / $yesterday_posts) * 100) : 0;

// Today's actions vs yesterday
$today_actions = $stats['today_actions'] ?? 0;
$yesterday_actions = $last_month_stats['yesterday_actions'] ?? 0;
$percent_changes['today_actions'] = ($yesterday_actions > 0) ? round((($today_actions - $yesterday_actions) / $yesterday_actions) * 100) : 0;

// Prepare the response
$response = [
    'stats' => $stats,
    'percent_changes' => $percent_changes
];

// Send response as JSON
header('Content-Type: application/json');
echo json_encode($response); 
