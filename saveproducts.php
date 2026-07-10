<?php
require_once 'db.php';
header('Content-Type: application/json');

function normalizeJsonString($value, $fallback = '[]') {
    if (is_array($value)) {
        return json_encode($value);
    }

    $trimmed = trim((string) $value);
    if ($trimmed === '') {
        return $fallback;
    }

    if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
            return json_encode($decoded);
        }
    }

    $parts = preg_split('/\s*\|\|\s*/', $trimmed);
    $parts = array_values(array_filter(array_map('trim', $parts), 'strlen'));
    return json_encode($parts);
}

function saveUploadToDisk($file, $uploadDir = 'uploads/') {
    if (!is_array($file) || !isset($file['name']) || empty($file['name'])) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return '';
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return '';
    }

    $fileName = uniqid('product_', true) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        return $uploadDir . $fileName;
    }

    return '';
}

function saveBase64ToDisk($data, $uploadDir = 'uploads/') {
    if (!is_string($data) || strpos($data, 'data:image/') !== 0) {
        return '';
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $parts = explode(';', $data, 2);
    $meta = $parts[0] ?? '';
    $encoded = $parts[1] ?? '';
    if (strpos($encoded, ',') !== false) {
        $encoded = explode(',', $encoded, 2)[1] ?? '';
    }

    $type = str_replace('data:', '', $meta);
    $ext = 'jpg';
    if (strpos($type, '/webp') !== false) {
        $ext = 'webp';
    } elseif (strpos($type, '/png') !== false) {
        $ext = 'png';
    } elseif (strpos($type, '/gif') !== false) {
        $ext = 'gif';
    }

    $decoded = base64_decode($encoded, true);
    if ($decoded === false) {
        return '';
    }

    $fileName = uniqid('product_', true) . '.' . $ext;
    if (file_put_contents($uploadDir . $fileName, $decoded) !== false) {
        return $uploadDir . $fileName;
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST method required']);
    exit();
}

$id = intval($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$category = trim($_POST['cat'] ?? $_POST['category'] ?? '');
$price = floatval($_POST['price'] ?? $_POST['original_price'] ?? 0);
$description = trim($_POST['description'] ?? '');
$stock = trim($_POST['stock'] ?? $_POST['stock_status'] ?? 'Available');
$visible = trim($_POST['visible'] ?? 'yes');
$highlights = normalizeJsonString($_POST['highlights'] ?? '[]');
$priceTags = normalizeJsonString($_POST['price_tags'] ?? '[]');

if (empty($name)) {
    echo json_encode(['status' => 'error', 'message' => 'Product name required']);
    exit();
}

$uploadDir = 'uploads/';
$photos = [];

if (!empty($_POST['existing_photos'])) {
    $existing = json_decode($_POST['existing_photos'], true);
    if (is_array($existing)) {
        $photos = array_values(array_filter($existing, 'strlen'));
    }
}

if (!empty($_FILES['photos']['name'])) {
    foreach ($_FILES['photos']['name'] as $key => $nameValue) {
        if (empty($nameValue)) {
            continue;
        }
        $photoPath = saveUploadToDisk([
            'name' => $nameValue,
            'tmp_name' => $_FILES['photos']['tmp_name'][$key],
            'size' => $_FILES['photos']['size'][$key],
            'error' => $_FILES['photos']['error'][$key],
        ], $uploadDir);
        if ($photoPath !== '') {
            $photos[] = $photoPath;
        }
    }
}

if (!empty($_FILES['photo']['name'])) {
    $photoPath = saveUploadToDisk($_FILES['photo'], $uploadDir);
    if ($photoPath !== '') {
        $photos[] = $photoPath;
    }
}

if (!empty($_POST['photo']) && is_string($_POST['photo'])) {
    $photoPath = saveBase64ToDisk($_POST['photo'], $uploadDir);
    if ($photoPath !== '') {
        $photos[] = $photoPath;
    }
}

$photos = array_values(array_unique(array_filter($photos, 'strlen')));
$photosJson = json_encode($photos);
$primaryPhoto = $photos[0] ?? '';
$visibleValue = ($visible === 'yes' || $visible === '1' || $visible === 'true' || $visible === true || $visible === 1) ? 1 : 0;

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price=?, description=?, stock=?, visible=?, photo=?, photos=?, highlights=?, price_tags=? WHERE id=?");
    $stmt->bind_param('ssdssisssii', $name, $category, $price, $description, $stock, $visibleValue, $primaryPhoto, $photosJson, $highlights, $priceTags, $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Product updated!', 'id' => $id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    $stmt->close();
} else {
    $stmt = $conn->prepare("INSERT INTO products (name, category, price, description, stock, visible, photo, photos, highlights, price_tags) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('ssdssissss', $name, $category, $price, $description, $stock, $visibleValue, $primaryPhoto, $photosJson, $highlights, $priceTags);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Product added!', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    $stmt->close();
}

$conn->close();
?>