<?php
require_once 'db.php';
header('Content-Type: application/json');

function readJsonBody() {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit();
}

// Accept both JSON body and normal form POST (FormData)
$data = readJsonBody();
if (empty($data)) {
    $data = $_POST;
}

$id            = intval($data['id'] ?? 0);
$status        = trim($data['status'] ?? '');
$cancel_reason = trim($data['cancel_reason'] ?? '');

$allowed = ['Ordered', 'Processing', 'Delivered', 'Cancelled', 'Pending'];

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Valid order id is required']);
    exit();
}

if ($status === '' || !in_array($status, $allowed, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status value']);
    exit();
}

// If cancelling and a reason was sent, save status + reason together.
// Otherwise (admin manually changing status from dropdown), just update status
// and leave any existing cancel_reason untouched.
if ($status === 'Cancelled' && $cancel_reason !== '') {
    $stmt = $conn->prepare('UPDATE orders SET status = ?, cancel_reason = ? WHERE id = ?');
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param('ssi', $status, $cancel_reason, $id);
} else {
    $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param('si', $status, $id);
}

$ok = $stmt->execute();

if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Execute failed: ' . $stmt->error]);
    $stmt->close();
    exit();
}

if ($stmt->affected_rows === 0) {
    $check = $conn->prepare('SELECT id FROM orders WHERE id = ?');
    $check->bind_param('i', $id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        $check->close();
        $stmt->close();
        exit();
    }
    $check->close();
}

$stmt->close();
echo json_encode(['status' => 'success', 'message' => 'Order status updated', 'id' => $id, 'new_status' => $status]);
$conn->close();
?>