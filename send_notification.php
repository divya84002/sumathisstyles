<?php
header('Content-Type: application/json');
require 'db.php'; // uses $conn (mysqli)

$title   = isset($_POST['title']) ? trim($_POST['title']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$type    = isset($_POST['type']) ? trim($_POST['type']) : 'general';

// Table's type column is ENUM('order','promotion','class','general')
$allowedTypes = ['order', 'promotion', 'class', 'general'];
if (!in_array($type, $allowedTypes)) {
    $type = 'general';
}

if ($title === '' || $message === '') {
    echo json_encode(['status' => 'error', 'message' => 'Title and Message are required']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO notifications (title, message, type, created_at) VALUES (?, ?, ?, NOW())");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("sss", $title, $message, $type);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();