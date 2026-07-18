<?php
// save_consent.php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['phone']) || empty($input['key'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing phone or key']);
    exit;
}

$phone = preg_replace('/[^0-9]/', '', $input['phone']);
$key   = $input['key'];
$value = $input['value'] ?? '0';

$file = __DIR__ . '/data_consents.json';
$all = [];
if (file_exists($file)) {
    $all = json_decode(file_get_contents($file), true) ?: [];
}
if (!isset($all[$phone])) $all[$phone] = [];
$all[$phone][$key] = $value;
$all[$phone]['updated_at'] = date('c');

file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT));

echo json_encode(['status' => 'success']);