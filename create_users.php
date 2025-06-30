<?php
$conn = new mysqli("localhost", "root", "", "r64_notifications");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create test users
$users = [
    ['username' => 'user1', 'password' => 'password1'],
    ['username' => 'user2', 'password' => 'password2'],
    ['username' => 'user3', 'password' => 'password3']
];

foreach ($users as $user) {
    $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $user['username'], $hashed_password);
    $stmt->execute();
    $stmt->close();
}

echo "Test users created successfully";
$conn->close();
?>