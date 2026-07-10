<?php
require_once 'db.php';
header('Content-Type: application/json');

// Accepts id via POST (form-data) or GET (?id=) for convenience
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID required']);
    exit();
}

$stmt = $conn->prepare('SELECT id, name FROM orders WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit();
}

$stmt = $conn->prepare('DELETE FROM orders WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => "Order #{$order['id']} ({$order['name']}) deleted"]);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>