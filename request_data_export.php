<?php
require_once 'db.php';
require 'fpdf/fpdf.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// ============ EDIT THESE 2 LINES ============
$SMTP_GMAIL_ADDRESS  = 'sumathisstyle@gmail.com';   // your gmail address
$SMTP_GMAIL_APP_PASS = 'sumathisstyles@11223344';        // 16-char App Password
// ==============================================

$input = json_decode(file_get_contents('php://input'), true);

$phone = trim($input['phone'] ?? '');
$email = trim($input['email'] ?? '');
$name  = trim($input['name'] ?? 'Customer');

if (!$phone) {
    echo json_encode(['status' => 'error', 'message' => 'Phone number required']);
    exit();
}

if (!$email) {
    echo json_encode(['status' => 'error', 'message' => 'No email on file — please add an email in Edit Profile first.']);
    exit();
}

// Pull this user's orders from the same table used elsewhere on the site
$myOrders = [];
$result = $conn->query('SELECT * FROM orders ORDER BY id DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (trim($row['mobile'] ?? '') === $phone) {
            $myOrders[] = $row;
        }
    }
}

// Optional: log the request in DB so you have a record (safe even if table doesn't exist yet)
try {
    $stmt = $conn->prepare("INSERT INTO data_export_requests (name, phone, email, requested_at) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param('sss', $name, $phone, $email);
        $stmt->execute();
        $stmt->close();
    }
} catch (\Throwable $e) {
    // table might not exist yet — don't block the email from sending
}

// ---------- Build PDF ----------
function cleanText($text) {
    // FPDF core fonts only support Latin-1, so convert UTF-8 safely
    return @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$text) ?: (string)$text;
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(0, 128, 128);
$pdf->Cell(0, 12, cleanText("Sumathi's Style - My Data Export"), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 8, cleanText('Exported on: ' . date('d M Y, h:i A')), 0, 1, 'C');
$pdf->Ln(6);

// Profile section
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(0, 128, 128);
$pdf->Cell(0, 8, 'Profile Information', 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(40, 7, 'Name:', 0, 0);
$pdf->Cell(0, 7, cleanText($name), 0, 1);
$pdf->Cell(40, 7, 'Phone:', 0, 0);
$pdf->Cell(0, 7, cleanText($phone), 0, 1);
$pdf->Cell(40, 7, 'Email:', 0, 0);
$pdf->Cell(0, 7, cleanText($email), 0, 1);
$pdf->Ln(6);

// Orders section
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(0, 128, 128);
$pdf->Cell(0, 8, 'Order History (' . count($myOrders) . ')', 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 10);

if (empty($myOrders)) {
    $pdf->Cell(0, 7, 'No orders found.', 0, 1);
} else {
    foreach ($myOrders as $o) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 7, cleanText('Order #' . ($o['id'] ?? '') . ' - ' . ($o['product'] ?? '')), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, cleanText('Amount: Rs.' . ($o['amount'] ?? '0') . '   Status: ' . ($o['status'] ?? 'Ordered')), 0, 1);
        if (!empty($o['created_at'])) {
            $pdf->Cell(0, 6, cleanText('Placed on: ' . date('d M Y', strtotime($o['created_at']))), 0, 1);
        }
        $pdf->Ln(3);
    }
}

$pdfContent = $pdf->Output('S'); // get PDF as string
$tmpFile = sys_get_temp_dir() . '/sumathi_export_' . preg_replace('/[^0-9]/', '', $phone) . '.pdf';
file_put_contents($tmpFile, $pdfContent);

// ---------- Send Email with PDF attached ----------
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_GMAIL_ADDRESS;
    $mail->Password   = $SMTP_GMAIL_APP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom($SMTP_GMAIL_ADDRESS, "Sumathi's Style");
    $mail->addAddress($email, $name);
    $mail->addReplyTo($SMTP_GMAIL_ADDRESS, "Sumathi's Style");
    $mail->addAttachment($tmpFile, 'my_sumathi_style_data.pdf');

    $mail->isHTML(true);
    $mail->Subject = 'Your Data Export (PDF) — Sumathi\'s Style';
    $mail->Body    = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;'>
            <h2 style='color:#008080;'>Your Data Export is Ready</h2>
            <p>Hi " . htmlspecialchars($name) . ",</p>
            <p>As requested, please find attached a PDF copy of the personal data we hold about you at <strong>Sumathi's Style</strong> — your profile details and order history.</p>
            <p>If you have any questions, reach out to us on WhatsApp at <strong>86107 03658</strong>.</p>
            <br>
            <p style='color:#888;font-size:12px;'>This is an automated email from Sumathi's Style.</p>
        </div>
    ";
    $mail->AltBody = "Hi $name, please find attached a PDF copy of your personal data from Sumathi's Style. Contact us on WhatsApp at 86107 03658 for help.";

    $mail->send();
    @unlink($tmpFile);
    echo json_encode(['status' => 'success', 'message' => 'Email sent']);
} catch (Exception $e) {
    @unlink($tmpFile);
    echo json_encode(['status' => 'error', 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
}

$conn->close();
?>