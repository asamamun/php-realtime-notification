<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$conn = new mysqli("localhost", "root", "", "r64_notifications");
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed']));
}

$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$user_id = $_SESSION['user_id'];
$recipient_id = isset($_GET['recipient_id']) ? (int)$_GET['recipient_id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'unread_counts') {
    // Fetch unread message counts per sender
    $stmt = $conn->prepare("SELECT sender_id, COUNT(*) as unread_count FROM notifications WHERE recipient_id = ? AND is_read = 0 GROUP BY sender_id");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $unread_counts = [];
    while ($row = $result->fetch_assoc()) {
        $unread_counts[$row['sender_id']] = $row['unread_count'];
    }
    echo json_encode(['status' => 'success', 'unread_counts' => $unread_counts]);
    $stmt->close();
} elseif ($recipient_id) {
    // Fetch messages between user_id and recipient_id
    $stmt = $conn->prepare("SELECT id AS message_id, sender_id, recipient_id, message, created_at, is_read FROM notifications WHERE id > ? AND ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)) ORDER BY created_at ASC");
    $stmt->bind_param("iiiii", $last_id, $user_id, $recipient_id, $recipient_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    echo json_encode($notifications);
    $stmt->close();
} else {
    // Fallback: Fetch all messages for the user
    $stmt = $conn->prepare("SELECT id AS message_id, sender_id, recipient_id, message, created_at, is_read FROM notifications WHERE id > ? AND (recipient_id = ? OR sender_id = ?) ORDER BY created_at ASC");
    $stmt->bind_param("iii", $last_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    echo json_encode($notifications);
    $stmt->close();
}

$conn->close();
?>