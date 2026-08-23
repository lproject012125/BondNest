<?php
session_start();
require_once 'db_connection.php';
header('Content-Type: application/json');

// Log the request
$log_file = 'message_update_log.txt';
file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Request: ' . json_encode($_POST) . PHP_EOL, FILE_APPEND);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Error: Not authenticated' . PHP_EOL, FILE_APPEND);
    exit();
}



$action = $_POST['action'] ?? '';
$message_id = intval($_POST['message_id'] ?? 0);

file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Action: ' . $action . ', Message ID: ' . $message_id . PHP_EOL, FILE_APPEND);

// Handle case for temporary message IDs from newly sent messages
if ($message_id === 0 && isset($_POST['message_id']) && strpos($_POST['message_id'], 'temp-') === 0) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Temporary message ID detected: ' . $_POST['message_id'] . PHP_EOL, FILE_APPEND);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid message ID', 
        'is_temp' => true
    ]);
    exit();
}

if (!$message_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Error: Invalid message ID' . PHP_EOL, FILE_APPEND);
    exit();
}

// Verify message ownership
$sql = "SELECT sender_id FROM messages WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$message_id]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);

file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Message data: ' . json_encode($message) . ', User ID: ' . $_SESSION['user_id'] . PHP_EOL, FILE_APPEND);

if (!$message || $message['sender_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Error: Unauthorized access' . PHP_EOL, FILE_APPEND);
    exit();
}

switch ($action) {
    case 'edit':
        $new_content = trim($_POST['content'] ?? '');
        if (empty($new_content)) {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Error: Empty message content' . PHP_EOL, FILE_APPEND);
            exit();
        }

        file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Editing message. New content: ' . $new_content . PHP_EOL, FILE_APPEND);

        // Check if the messages table has the updated_at column
        $check_sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'messages' AND column_name = 'updated_at'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute();
        $has_updated_at = $check_stmt->fetch() !== false;

        if ($has_updated_at) {
            $sql = "UPDATE messages SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        } else {
            $sql = "UPDATE messages SET content = ? WHERE id = ?";
            file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Warning: updated_at column does not exist in messages table' . PHP_EOL, FILE_APPEND);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_content, $message_id]);
        
        if ($stmt->execute()) {
            // Get the updated message with timestamp
            $sql = "SELECT content, updated_at FROM messages WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$message_id]);
            $updated_message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Message updated successfully: ' . json_encode($updated_message) . PHP_EOL, FILE_APPEND);
            
            echo json_encode([
                'success' => true,
                'message' => 'Message updated successfully',
                'data' => [
                    'content' => $updated_message['content'],
                    'updated_at' => $updated_message['updated_at'] ?? null
                ]
            ]);
        } else {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Error updating message' . PHP_EOL, FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Failed to update message']);
        }
        break;

    case 'delete':
        file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Marking message as deleted with ID: ' . $message_id . PHP_EOL, FILE_APPEND);
        
        // Check if the messages table has a deleted column
        $check_sql = "SELECT column_name FROM information_schema.columns WHERE table_name = 'messages' AND column_name = 'deleted'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute();
        $has_deleted_column = $check_stmt->fetch() !== false;

        // If deleted column doesn't exist, add it
        if (!$has_deleted_column) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Adding deleted column to messages table' . PHP_EOL, FILE_APPEND);
            global $db_driver;
            if ($db_driver === 'pgsql') {
                $alter_sql = "ALTER TABLE messages ADD COLUMN deleted BOOLEAN NOT NULL DEFAULT FALSE";
            } else {
                $alter_sql = "ALTER TABLE messages ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0";
            }
            $pdo->exec($alter_sql);
        }

        // Mark the message as deleted instead of physically deleting it
        global $db_driver;
        if ($db_driver === 'pgsql') {
            $sql = "UPDATE messages SET deleted = TRUE, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        } else {
            $sql = "UPDATE messages SET deleted = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$message_id]);
        
        if ($stmt->execute()) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Message marked as deleted successfully' . PHP_EOL, FILE_APPEND);
            echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
        } else {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Error marking message as deleted' . PHP_EOL, FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Failed to delete message']);
        }
        break;

    default:
        file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Error: Invalid action: ' . $action . PHP_EOL, FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
} 