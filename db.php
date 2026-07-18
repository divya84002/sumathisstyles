<?php
mysqli_report(MYSQLI_REPORT_OFF);

define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'product_db');
define('DB_PORT', getenv('MYSQLPORT') ?: 3306);

function getConnection() {
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', (int)DB_PORT);

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
        exit();
    }

    $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`");
    if (!$conn->select_db(DB_NAME)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not select database: ' . $conn->error]);
        exit();
    }

    $conn->set_charset('utf8mb4');

    $conn->query("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) DEFAULT '',
        price DECIMAL(10,2) DEFAULT 0,
        description TEXT,
        stock VARCHAR(50) DEFAULT 'Available',
        visible TINYINT(1) DEFAULT 1,
        photo VARCHAR(500) DEFAULT '',
        photos TEXT,
        highlights TEXT,
        price_tags TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

     $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        type VARCHAR(50) DEFAULT 'general',
        target_phone VARCHAR(20) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        mobile VARCHAR(20) DEFAULT '',
        product VARCHAR(255) DEFAULT '',
        amount DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'Ordered',
        notes TEXT,
        source VARCHAR(100) DEFAULT 'website',
        measurement TEXT,
        voice_note LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        rating INT DEFAULT 5,
        review_text TEXT,
        source VARCHAR(100) DEFAULT 'website',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        service VARCHAR(255) DEFAULT '',
        message TEXT,
        source VARCHAR(100) DEFAULT 'website',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Auto-add missing columns on products table (safety net)
    $columns = [];
    $res = $conn->query('SHOW COLUMNS FROM products');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    $alterQueries = [];
    if (!in_array('price', $columns, true)) $alterQueries[] = "ALTER TABLE products ADD COLUMN price DECIMAL(10,2) DEFAULT 0";
    if (!in_array('description', $columns, true)) $alterQueries[] = "ALTER TABLE products ADD COLUMN description TEXT";
    if (!in_array('stock', $columns, true)) $alterQueries[] = "ALTER TABLE products ADD COLUMN stock VARCHAR(50) DEFAULT 'Available'";
    if (!in_array('visible', $columns, true)) $alterQueries[] = "ALTER TABLE products ADD COLUMN visible TINYINT(1) DEFAULT 1";
    if (!in_array('photo', $columns, true)) $alterQueries[] = "ALTER TABLE products ADD COLUMN photo VARCHAR(500) DEFAULT ''";
    if (!in_array('photos', $columns, true)) $alterQueries[] = "ALTER TABLE products ADD COLUMN photos TEXT";
    if (!in_array('highlights', $columns, true)) $alterQueries[] = "ALTER TABLE products ADD COLUMN highlights TEXT";
    if (!in_array('price_tags', $columns, true)) $alterQueries[] = "ALTER TABLE products ADD COLUMN price_tags TEXT";
    if (!in_array('created_at', $columns, true)) $alterQueries[] = "ALTER TABLE products ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    foreach ($alterQueries as $query) {
        $conn->query($query);
    }

    // Auto-add missing columns on orders table (safety net)
    $orderColumns = [];
    $res2 = $conn->query('SHOW COLUMNS FROM orders');
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $orderColumns[] = $row['Field'];
        }
    }
    $orderAlterQueries = [];
    if (!in_array('measurement', $orderColumns, true)) $orderAlterQueries[] = "ALTER TABLE orders ADD COLUMN measurement TEXT";
    if (!in_array('voice_note', $orderColumns, true)) $orderAlterQueries[] = "ALTER TABLE orders ADD COLUMN voice_note LONGTEXT";
    foreach ($orderAlterQueries as $query) {
        $conn->query($query);
    }

    return $conn;
}

$conn = getConnection();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>