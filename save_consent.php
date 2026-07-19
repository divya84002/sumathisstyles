<?php
// save_consent.php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['phone']) || empty($input['key'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing phone or key']);
    exit;
}

$phone = preg_replace('/[^0-9]/', '', $input['phone']);
$key   = $input['key'];
$value = ($input['value'] ?? '0') === '1' ? 1 : 0;

// map frontend keys -> DB columns
$columnMap = [
    'consentMarketing'   => 'consent_marketing',
    'consentOrderNotif'  => 'consent_order_notif',
    'consentLocation'    => 'consent_location',
    'consentAnalytics'   => 'consent_analytics',
    'consentWhatsApp'    => 'consent_whatsapp',
];

if (!isset($columnMap[$key])) {
    echo json_encode(['status' => 'error', 'message' => 'Unknown consent key: ' . $key]);
    exit;
}

$column = $columnMap[$key];

try {
    // insert a row if phone doesn't exist yet, otherwise update just that column
    $stmt = $conn->prepare("INSERT INTO consents (phone, $column) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE $column = VALUES($column), updated_at = NOW()");
    $stmt->bind_param('si', $phone, $value);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success']);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
}