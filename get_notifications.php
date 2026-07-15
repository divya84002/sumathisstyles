<?php
header('Content-Type: application/json');
require 'db.php'; // uses $conn (mysqli)

$result = $conn->query("SELECT id, title, message, type, created_at FROM notifications ORDER BY created_at ASC");

$notifications = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

echo json_encode($notifications);

$conn->close();