<?php
// get_notifications.php
// If called with ?phone=9876543210, filters notifications based on that customer's
// consent preferences (Order Notifications / Marketing consent from Consent Management).
// If called without phone (or phone not found), returns everything (old behaviour) —
// useful for admin dashboard which shows ALL sent notifications regardless of consent.
header('Content-Type: application/json');
require 'db.php'; // uses $conn (mysqli)

$phone = isset($_GET['phone']) ? preg_replace('/[^0-9]/', '', $_GET['phone']) : '';

$result = $conn->query("SELECT id, title, message, type, created_at FROM notifications ORDER BY created_at ASC");

$notifications = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// No phone provided -> return everything unfiltered (admin dashboard use case)
if (!$phone) {
    echo json_encode($notifications);
    $conn->close();
    exit;
}

// Look up this customer's consent preferences
$consentOrderNotif = 1; // default ON if no record yet, matches settings.html default toggle state
$consentMarketing  = 1; // default ON if no record yet

$stmt = $conn->prepare("SELECT consent_order_notif, consent_marketing FROM consents WHERE phone = ? LIMIT 1");
$stmt->bind_param('s', $phone);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $consentOrderNotif = (int) $row['consent_order_notif'];
    $consentMarketing  = (int) $row['consent_marketing'];
}
$stmt->close();

// Filter notifications based on type + consent
$filtered = array_values(array_filter($notifications, function ($n) use ($consentOrderNotif, $consentMarketing) {
    switch ($n['type']) {
        case 'order':
            return $consentOrderNotif === 1;
        case 'promotion':
            return $consentMarketing === 1;
        case 'class':
        case 'general':
        default:
            return true; // no dedicated consent toggle yet, always shown
    }
}));

echo json_encode($filtered);
$conn->close();