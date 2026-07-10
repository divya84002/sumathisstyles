<?php
require_once 'db.php';
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

if ($type === 'orders') {
    $result = $conn->query('SELECT * FROM orders ORDER BY id DESC');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    echo json_encode($rows);
    exit();
}

if ($type === 'reviews') {
    $result = $conn->query('SELECT * FROM reviews ORDER BY id DESC');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    echo json_encode($rows);
    exit();
}

if ($type === 'contacts') {
    $result = $conn->query('SELECT * FROM contacts ORDER BY id DESC');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    echo json_encode($rows);
    exit();
}

echo json_encode([]);
$conn->close();
?>