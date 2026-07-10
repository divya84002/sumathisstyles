
<?php
require_once 'db.php';
header('Content-Type: application/json');

$id = trim($_POST['id'] ?? '');
$visible = trim($_POST['visible'] ?? '');

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
    exit();
}

$stmt = $conn->prepare('SELECT visible, name FROM products WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit();
}

if ($visible !== '') {
    $newVisible = ($visible === 'yes' || $visible === '1' || $visible === 'true' || $visible === true || $visible === 1) ? 1 : 0;
} else {
    $newVisible = ((int) $product['visible']) ? 0 : 1;
}

$stmt = $conn->prepare('UPDATE products SET visible = ? WHERE id = ?');
$stmt->bind_param('ii', $newVisible, $id);

if ($stmt->execute()) {
    $statusText = $newVisible ? 'visible' : 'hidden';
    echo json_encode([
        'status' => 'success',
        'message' => "'{$product['name']}' is now $statusText",
        'visible' => $newVisible
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
