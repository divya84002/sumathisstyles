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

// Generates order IDs like SS2026-0001, SS2026-0002 ... SS2027-0560, SS2027-0561 ...
// The numeric counter NEVER resets when the year changes — it keeps increasing globally.
function generateNextOrderId($conn) {
    $yearNow = date('Y');
    $nextNum = 1;

    $result = $conn->query("SELECT order_id FROM orders WHERE order_id IS NOT NULL AND order_id != '' ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastOrderId = $row['order_id']; // e.g. SS2026-0550
        $parts = explode('-', $lastOrderId);
        if (count($parts) === 2 && is_numeric($parts[1])) {
            $nextNum = intval($parts[1]) + 1;
        }
    }

    $paddedNum = str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    return 'SS' . $yearNow . '-' . $paddedNum;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit();
}

$data = readJsonBody();
$type = $data['type'] ?? '';

if ($type === 'order') {
    $name = trim($data['name'] ?? '');
    $mobile = trim($data['mobile'] ?? '');
    $product = trim($data['product'] ?? '');
    $amount = floatval($data['amount'] ?? 0);
    $notes = trim($data['notes'] ?? '');
    $source = trim($data['source'] ?? 'website');
    $measurement = trim($data['measurement'] ?? '');
    $voice_note = trim($data['voice_note'] ?? '');

    if ($name === '' || $mobile === '') {
        echo json_encode(['status' => 'error', 'message' => 'Name and mobile are required']);
        exit();
    }

    $orderId = generateNextOrderId($conn);

    $stmt = $conn->prepare('INSERT INTO orders (order_id, name, mobile, product, amount, status, notes, source, measurement, voice_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }

    $status = 'Ordered';
    $stmt->bind_param('sssssssss', $orderId, $name, $mobile, $product, $amount, $status, $notes, $source, $measurement, $voice_note);
    $ok = $stmt->execute();
    if (!$ok) {
        echo json_encode(['status' => 'error', 'message' => 'Execute failed: ' . $stmt->error]);
        $stmt->close();
        exit();
    }
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Order saved', 'order_id' => $orderId]);
    exit();
}

if ($type === 'review') {
    $name = trim($data['name'] ?? '');
    $rating = intval($data['rating'] ?? 5);
    $reviewText = trim($data['review_text'] ?? '');
    $source = trim($data['source'] ?? 'website');

    if ($name === '' || $reviewText === '') {
        echo json_encode(['status' => 'error', 'message' => 'Name and review are required']);
        exit();
    }

    $stmt = $conn->prepare('INSERT INTO reviews (name, rating, review_text, source) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('siss', $name, $rating, $reviewText, $source);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => $ok ? 'success' : 'error', 'message' => $ok ? 'Review saved' : 'Failed to save review']);
    exit();
}

if ($type === 'contact') {
    $name = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');
    $service = trim($data['service'] ?? '');
    $message = trim($data['message'] ?? '');
    $source = trim($data['source'] ?? 'website');

    if ($name === '' || $message === '') {
        echo json_encode(['status' => 'error', 'message' => 'Name and message are required']);
        exit();
    }

    $stmt = $conn->prepare('INSERT INTO contacts (name, phone, email, service, message, source) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssss', $name, $phone, $email, $service, $message, $source);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => $ok ? 'success' : 'error', 'message' => $ok ? 'Contact saved' : 'Failed to save contact']);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Unknown form type']);
$conn->close();
?>