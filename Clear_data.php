<?php
header('Content-Type: application/json');

// ⚠️ IMPORTANT: idha unga get_submissions.php / save_product.php file la irukura
// $host / $user / $pass / $dbname values oda EXACT-a match pannunga.
// Neenga "product_db" vs "sumathis_styles" nu rendu db name try pannirundhinga,
// so ippo edhu ACTIVE-a use aaguthu nu confirm pannitu keela set pannunga.
$host   = 'localhost';
$user   = 'root';
$pass   = '';
$dbname = 'product_db'; // <-- unga actual active database

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit;
}

$target = $_POST['target'] ?? '';

// Table/column names - unga schema la vera peru irundha idha maathunga:
// orders table:   id, name, mobile, product, amount, status, created_at, source, measurement, voice_note, notes
// contacts table: id, name, phone, email, service, message, created_at

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