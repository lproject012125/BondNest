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
    // Ensure comments table exists with parent_id column
    if ($db_driver === 'pgsql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
            id SERIAL PRIMARY KEY,
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            content TEXT NOT NULL,
            parent_id INTEGER REFERENCES comments(id) ON DELETE CASCADE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("ALTER TABLE comments ADD COLUMN IF NOT EXISTS parent_id INTEGER REFERENCES comments(id) ON DELETE CASCADE");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            post_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            content TEXT NOT NULL,
            parent_id INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Add parent_id if missing
        $check = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='comments' AND COLUMN_NAME='parent_id'");
        $exists = $check && $check->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE comments ADD COLUMN parent_id INT(11) DEFAULT NULL");
        }
    }

    $sql = "SELECT c.id, c.post_id, c.user_id, c.content, c.parent_id, c.created_at, c.updated_at,
                   u.first_name, u.last_name, u.profile_picture
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at ASC";
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . implode(' ', $pdo->errorInfo()));
    }

    $stmt->execute([$post_id]);
    $all_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build parent lookup — cast everything to int to avoid string/int mismatches
    $parent_map = [];
    foreach ($all_comments as $c) {
        $id = (int)$c['id'];
        $parent_map[$id] = !empty($c['parent_id']) ? (int)$c['parent_id'] : null;
        $c['id'] = $id;
        $c['user_id'] = (int)$c['user_id'];
        $c['post_id'] = (int)$c['post_id'];
        if (!empty($c['parent_id'])) $c['parent_id'] = (int)$c['parent_id'];
    }

    // Find top-level ancestor for each comment using iterative loop
    $ancestor_cache = [];
    foreach ($all_comments as $c) {
        $id = (int)$c['id'];
        $pid = $parent_map[$id] ?? null;
        
        if ($pid === null) {
            $ancestor_cache[$id] = $id;
        } else {
            $current = $id;
            while (isset($parent_map[$current]) && $parent_map[$current] !== null) {
                $current = $parent_map[$current];
            }
            $ancestor_cache[$id] = $current;
        }
    }
    
    // Group ALL comments under their top-level ancestor (flatten nested replies)
    $top_level = [];
    $replies_map = [];

    foreach ($all_comments as $c) {
        $c['replies'] = [];
        $id = (int)$c['id'];
        $ancestor = $ancestor_cache[$id];
        
        if ($ancestor === $id && $parent_map[$id] === null) {
            $top_level[$id] = $c;
        } else {
            if (!isset($replies_map[$ancestor])) {
                $replies_map[$ancestor] = [];
            }
            $replies_map[$ancestor][] = $c;
        }
    }

    // Build author name lookup for reply-to indicators
    $author_names = [];
    foreach ($all_comments as $c) {
        $author_names[(int)$c['id']] = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    }

    // Attach replies to their parents (top-level ordered DESC, replies ordered ASC)
    $result = array_reverse($top_level);
    foreach ($result as &$comment) {
        $cid = (int)$comment['id'];
        if (isset($replies_map[$cid])) {
            $comment['replies'] = $replies_map[$cid];
        }
    }
    unset($comment);

    // Add reply_to_name for each reply
    foreach ($result as &$comment) {
        if (!empty($comment['replies'])) {
            foreach ($comment['replies'] as &$reply) {
                $pid = (int)($reply['parent_id'] ?? 0);
                $reply['reply_to_name'] = $author_names[$pid] ?? '';
            }
            unset($reply);
        }
    }
    unset($comment);

    ob_end_clean();

    $response = [
        'success' => true,
        'comments' => array_values($result),
    ];

    die(json_encode($response));

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    die(json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]));
}
