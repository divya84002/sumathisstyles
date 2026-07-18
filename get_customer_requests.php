<?php
// get_customer_requests.php
// Admin dashboard fetches: ?type=data_export | consents | grievances | deactivated | deleted_accounts
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$out = [];

switch ($type) {

    case 'data_export':
        $res = $conn->query("SELECT id, name, phone, email, requested_at FROM data_requests ORDER BY id ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) $out[] = $row;
        }
        break;

    case 'consents':
        // Admin JS expects camelCase keys: consentMarketing, consentOrderNotif, consentLocation, consentAnalytics, consentWhatsApp
        $res = $conn->query("SELECT
                phone,
                consent_marketing   AS consentMarketing,
                consent_order_notif AS consentOrderNotif,
                consent_location    AS consentLocation,
                consent_analytics   AS consentAnalytics,
                consent_whatsapp    AS consentWhatsApp,
                updated_at
            FROM consents ORDER BY updated_at ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                // Cast 0/1 to string '0'/'1' so admin JS onOff() check (v==='1') works
                foreach (['consentMarketing','consentOrderNotif','consentLocation','consentAnalytics','consentWhatsApp'] as $k) {
                    $row[$k] = (string) $row[$k];
                }
                $out[] = $row;
            }
        }
        break;

    case 'grievances':
        $res = $conn->query("SELECT id, name, phone, email, subject, order_id, description, status, created_at FROM grievances ORDER BY id ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) $out[] = $row;
        }
        break;

    case 'deactivated':
        $res = $conn->query("SELECT id, phone, reason, deactivated_at FROM deactivated_accounts ORDER BY id ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) $out[] = $row;
        }
        break;

    case 'deleted_accounts':
        $res = $conn->query("SELECT id, phone, deleted_at FROM deleted_accounts ORDER BY id ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) $out[] = $row;
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
        exit;
}

echo json_encode($out);