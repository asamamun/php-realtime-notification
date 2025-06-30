<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$conn = new mysqli("localhost", "root", "", "r64_notifications");
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id = $_POST['sender_id'];
    $recipient_id = $_POST['recipient_id'];
    $message = $_POST['message'];
    
    if ($sender_id != $_SESSION['user_id']) {
        die(json_encode(['status' => 'error', 'message' => 'Invalid sender ID']));
    }
    
    $stmt = $conn->prepare("INSERT INTO notifications (sender_id, recipient_id, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
    $stmt->bind_param("iis", $sender_id, $recipient_id, $message);
    $stmt->execute();
    $message_id = $conn->insert_id;
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message_id' => $message_id,
        'sender_id' => $sender_id,
        'recipient_id' => $recipient_id,
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s'),
        'is_read' => 0
    ]);
}

$conn->close();
?>