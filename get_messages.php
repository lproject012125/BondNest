<?php
session_start();

require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$last_message_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if ($selected_user_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit();
}

// Get only new messages that haven't been fetched before
$sql = "SELECT m.*, u.first_name, u.last_name, u.profile_picture
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.receiver_id = ? AND m.sender_id = ? AND m.id > ?
        ORDER BY m.created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$current_user_id, $selected_user_id, $last_message_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark messages as read
if (!empty($messages)) {
    $message_ids = array_column($messages, 'id');
    $placeholders = implode(',', array_fill(0, count($message_ids), '?'));
    
    $sql = "UPDATE messages SET is_read = TRUE WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($message_ids);
}

// Format messages for response
$formatted_messages = [];
$tz_manila = new DateTimeZone('Asia/Manila');
foreach ($messages as $message) {
    $dt = new DateTime($message['created_at'], new DateTimeZone('UTC'));
    $dt->setTimezone($tz_manila);
    $formatted_messages[] = [
        'id' => $message['id'],
        'sender_id' => $message['sender_id'],
        'content' => htmlspecialchars($message['content'] ?? ''),
        'time' => $dt->format('M j, Y \a\t g:i A'),
        'image_path' => !empty($message['image_path']) ? (json_decode($message['image_path'], true) ?? $message['image_path']) : null
    ];
}

header('Content-Type: application/json');
echo json_encode(['messages' => $formatted_messages]);
?>
