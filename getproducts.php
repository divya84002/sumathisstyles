
<?php
require_once 'db.php';
header('Content-Type: application/json');

$admin = isset($_GET['admin']) && $_GET['admin'] === '1';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

function normalizeProductRow($row) {
    $photos = json_decode($row['photos'] ?? '[]', true);
    if (!is_array($photos)) {
        $photos = [];
    }
    if (!empty($row['photo']) && !in_array($row['photo'], $photos, true)) {
        array_unshift($photos, $row['photo']);
    }

    $highlights = json_decode($row['highlights'] ?? '[]', true);
    if (!is_array($highlights)) {
        $highlights = [];
    }

    $priceTags = json_decode($row['price_tags'] ?? '[]', true);
    if (!is_array($priceTags)) {
        $priceTags = [];
    }

    $row['photos'] = array_values(array_filter($photos, 'strlen'));
    $row['photo'] = $row['photos'][0] ?? '';
    $row['highlights'] = $highlights;
    $row['price_tags'] = $priceTags;
    $row['price'] = (float) ($row['price'] ?? 0);
    $row['visible'] = ((int) ($row['visible'] ?? 1) === 1) ? 'yes' : 'no';
    return $row;
}

if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    echo json_encode(['status' => 'success', 'product' => normalizeProductRow($product)]);
} else {
    $sql = 'SELECT * FROM products';
    $conditions = [];
    $params = [];
    $types = '';

    if (!$admin) {
        $conditions[] = 'visible = 1';
    }

    if (!empty($_GET['category'])) {
        $conditions[] = 'category = ?';
        $params[] = $_GET['category'];
        $types .= 's';
    }

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY created_at DESC';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = normalizeProductRow($row);
    }
    $stmt->close();

    $catRes = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = [];
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row['category'];
    }

    echo json_encode([
        'status' => 'success',
        'products' => $products,
        'total' => count($products),
        'categories' => $categories
    ]);
}

$conn->close();
?>
