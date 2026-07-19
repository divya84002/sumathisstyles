<?php
// deactivate_account.php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['phone'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing phone number']);
    exit;
}

$phone  = preg_replace('/[^0-9]/', '', $input['phone']);
$reason = $input['reason'] ?? '';

try {
    $stmt = $conn->prepare("INSERT INTO deactivated_accounts (phone, reason, deactivated_at) VALUES (?, ?, NOW())");
    $stmt->bind_param('ss', $phone, $reason);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
}