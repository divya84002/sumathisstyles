<?php
header('Content-Type: application/json');

// ✅ FIX: Railway la 'localhost' MySQL kedaiyathu. Railway automatically
// inject pannura environment variables use pannanum — unga baakki PHP files
// (get_products.php / submit_forms.php) la enna variable names use panningalo,
// adha ithuvum match pannanum. Common Railway MySQL env var names keela kudukaren.

$host   = getenv('MYSQLHOST')     ?: getenv('DB_HOST');
$user   = getenv('MYSQLUSER')     ?: getenv('DB_USER');
$pass   = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD');
$dbname = getenv('MYSQLDATABASE') ?: getenv('DB_NAME');
$port   = getenv('MYSQLPORT')     ?: getenv('DB_PORT') ?: 3306;

if (!$host || !$user || !$dbname) {
    echo json_encode(['status' => 'error', 'message' => 'DB env vars missing. Check Railway variable names.']);
    exit;
}

$conn = new mysqli($host, $user, $pass, $dbname, $port);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit;
}

$target = $_POST['target'] ?? '';

// Table/column names - unga schema la vera peru irundha idha maathunga:
// orders table:   id, name, mobile, product, amount, status, created_at, source, measurement, voice_note, notes
// contacts table: id, name, phone, email, service, message, created_at
//
// ⚠️ Keela irukura 4 puthu cases (data_requests_all / grievances_all / deactivated_all /
// deleted_accounts_all) — table names naan "get_customer_requests.php" file pattern paathu
// GUESS pannirukken (data_requests, grievances, deactivated_accounts, deleted_accounts).
// Unga get_customer_requests.php la exact table name vera maadhiri irundha, keela irukura
// table name mattum maathi update pannunga.

try {
    switch ($target) {

        case 'orders_all':
            // Orders page + Revenue page "Clear" button idha call pannum
            $conn->query("DELETE FROM orders");
            break;

        case 'orders_custom':
            // Customized Order page "Clear" button
            $conn->query("DELETE FROM orders WHERE source = 'custom-order'");
            break;

        case 'contacts_boutique':
            // Boutique Contact Form page "Clear" button
            $conn->query("DELETE FROM contacts WHERE service IS NULL OR service NOT LIKE '%catering%'");
            break;

        case 'contacts_catering':
            // Catering Contact Form page "Clear" button
            $conn->query("DELETE FROM contacts WHERE service LIKE '%catering%'");
            break;

        case 'notifications_all':
            // Send Notification page "Clear All Notifications" button
            $conn->query("DELETE FROM notifications");
            break;

        case 'data_requests_all':
            // "Request My Data Submissions" section — Clear Data Requests button
            $conn->query("DELETE FROM data_requests");
            break;

        case 'grievances_all':
            // "Grievance Redressal Complaints" section — Clear Grievances button
            $conn->query("DELETE FROM grievances");
            break;

        case 'deactivated_all':
            // "De-activated Accounts" section — Clear De-activated button
            $conn->query("DELETE FROM deactivated_accounts");
            break;

        case 'deleted_accounts_all':
            // "Deleted Accounts" section — Clear Deleted button
            $conn->query("DELETE FROM deleted_accounts");
            break;

        case 'all_demo_data':
            // Dashboard "Clear All Demo Data" button — orders + contacts rendayum clear pannum
            // Products table touch pannadhu.
            $conn->query("DELETE FROM orders");
            $conn->query("DELETE FROM contacts");
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid target']);
            exit;
    }

    if ($conn->error) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    } else {
        echo json_encode(['status' => 'success']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();