<?php
// submit_grievance.php
// Upload this in the SAME folder as your get_submissions.php / update_order_status.php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['subject']) || empty($input['description'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing subject or description']);
    exit;
}

$entry = [
    'id'          => uniqid('GRV'),
    'name'        => $input['name'] ?? 'Guest',
    'phone'       => $input['phone'] ?? '',
    'email'       => $input['email'] ?? '',
    'subject'     => $input['subject'],
    'order_id'    => $input['order_id'] ?? '',
    'description' => $input['description'],
    'status'      => 'Open',
    'submitted_at'=> $input['submitted_at'] ?? date('c')
];

$file = __DIR__ . '/data_grievances.json';
$list = [];
if (file_exists($file)) {
    $list = json_decode(file_get_contents($file), true) ?: [];
}
$list[] = $entry;
file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT));

// OPTIONAL: send yourself an email/WhatsApp notification here using mail() or an API

echo json_encode(['status' => 'success']);