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
    $user_id = $_SESSION['user_id'];
    $sender_id = $_POST['sender_id'];

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND sender_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $user_id, $sender_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success']);
}

$conn->close();
?>