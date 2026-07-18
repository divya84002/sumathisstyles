<?php
// resolve_grievance.php
// Called from admin dashboard markGrievance() — updates grievance status (Open / Resolved)
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$id     = isset($_POST['id'])     ? (int) $_POST['id']         : 0;
$status = isset($_POST['status']) ? trim($_POST['status'])     : '';

if ($id <= 0 || !in_array($status, ['Open', 'Resolved'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid id or status']);
    exit;
}

$stmt = $conn->prepare("UPDATE grievances SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
$stmt->close();