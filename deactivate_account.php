<?php
// deactivate_account.php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['phone'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing phone number']);
    exit;
}

$phone  = preg_replace('/[^0-9]/', '', $input['phone']);
$reason = $input['reason'] ?? '';

$file = __DIR__ . '/data_deactivated_accounts.json';
$list = [];
if (file_exists($file)) {
    $list = json_decode(file_get_contents($file), true) ?: [];
}
$list[$phone] = [
    'reason' => $reason,
    'deactivated_at' => date('c')
];
file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT));

// If you have a real users table/database, this is where you'd set:
// UPDATE users SET status = 'deactivated' WHERE phone = ?

echo json_encode(['status' => 'success']);