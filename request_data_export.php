<?php
// request_data_export.php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$phone = trim($input['phone'] ?? '');
$email = trim($input['email'] ?? '');
$name  = trim($input['name'] ?? 'Customer');

if (!$phone) {
    echo json_encode(['status' => 'error', 'message' => 'Phone number required']);
    exit();
}

// Log the request in DB so admin dashboard shows it
try {
    $stmt = $conn->prepare("INSERT INTO data_requests (name, phone, email, requested_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param('sss', $name, $phone, $email);
    $stmt->execute();
    $stmt->close();
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB insert failed: ' . $e->getMessage()]);
    exit();
}

// Optional: send an email copy ONLY if PHPMailer + fpdf libraries actually exist on the server.
// This never blocks or crashes the request logging above.
if ($email && file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php') && file_exists(__DIR__ . '/PHPMailer/src/SMTP.php') && file_exists(__DIR__ . '/PHPMailer/src/Exception.php')) {
    try {
        require_once __DIR__ . '/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/src/SMTP.php';

        $SMTP_GMAIL_ADDRESS  = 'sumathisstyle@gmail.com';
        $SMTP_GMAIL_APP_PASS = 'PUT_YOUR_16_CHAR_APP_PASSWORD_HERE'; // move to env var, see note below

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_GMAIL_ADDRESS;
        $mail->Password   = $SMTP_GMAIL_APP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($SMTP_GMAIL_ADDRESS, "Sumathi's Style");
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = "Your Data Export Request — Sumathi's Style";
        $mail->Body    = "<p>Hi " . htmlspecialchars($name) . ",</p><p>We've received your data export request. Our team will send your data shortly.</p>";
        $mail->send();
    } catch (\Throwable $e) {
        // email failed, but DB record already saved — don't fail the request
    }
}

echo json_encode(['status' => 'success', 'message' => 'Request logged']);