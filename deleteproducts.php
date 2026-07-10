<?php
require_once 'db.php';
header('Content-Type: application/json');

$id = trim($_POST['id'] ?? $_GET['id'] ?? '');

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
    exit();
}

$stmt = $conn->prepare('SELECT photos, photo FROM products WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit();
}

$stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    $photos = json_decode($product['photos'] ?? '[]', true);
    if (!is_array($photos)) {
        $photos = [];
    }
    if (!empty($product['photo']) && !in_array($product['photo'], $photos, true)) {
        $photos[] = $product['photo'];
    }
    foreach ($photos as $photo) {
        if (is_string($photo) && strpos($photo, 'uploads/') === 0 && file_exists($photo)) {
            unlink($photo);
        }
    }
    echo json_encode(['status' => 'success', 'message' => 'Product deleted!']);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
