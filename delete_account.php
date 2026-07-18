<?php
// delete_account.php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['phone'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing phone number']);
    exit;
}

$phone = preg_replace('/[^0-9]/', '', $input['phone']);

$file = __DIR__ . '/data_deleted_accounts.json';
$list = [];
if (file_exists($file)) {
    $list = json_decode(file_get_contents($file), true) ?: [];
}
$list[] = [
    'phone' => $phone,
    'deleted_at' => date('c')
];
file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT));

// If you have a real users/orders database, this is where you'd permanently
// DELETE FROM users WHERE phone = ?
// DELETE FROM orders WHERE mobile = ?  (or anonymize instead of hard-delete, per your policy)

echo json_encode(['status' => 'success']);