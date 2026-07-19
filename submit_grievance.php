<?php
// submit_grievance.php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['subject']) || empty($input['description'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing subject or description']);
    exit;
}

$name        = $input['name'] ?? 'Guest';
$phone       = $input['phone'] ?? '';
$email       = $input['email'] ?? '';
$subject     = $input['subject'];
$orderId     = $input['order_id'] ?? '';
$description = $input['description'];

try {
    $stmt = $conn->prepare("INSERT INTO grievances (name, phone, email, subject, order_id, description, status, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, 'Open', NOW())");
    $stmt->bind_param('ssssss', $name, $phone, $email, $subject, $orderId, $description);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
}