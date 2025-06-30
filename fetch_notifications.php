<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "r64_php";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$stmt = $conn->prepare("SELECT id, user_id, message, created_at FROM notifications WHERE id > ? ORDER BY created_at DESC");
$stmt->bind_param("i", $last_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode($notifications);
$stmt->close();
$conn->close();
?>