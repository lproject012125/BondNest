<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connection.php';

ob_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    ob_end_clean();
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['post_id'])) {
    http_response_code(400);
    ob_end_clean();
    die(json_encode(['error' => 'Post ID is required']));
}

$post_id = intval($_GET['post_id']);

try {
    global $db_driver;
    if ($db_driver === 'pgsql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
            id SERIAL PRIMARY KEY,
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            post_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $sql = "SELECT c.id, c.post_id, c.user_id, c.content, c.created_at, c.updated_at,
                   u.first_name, u.last_name, u.profile_picture
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . implode(' ', $pdo->errorInfo()));
    }

    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();

    die(json_encode([
        'success' => true,
        'comments' => $comments
    ]));

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    die(json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]));
}
