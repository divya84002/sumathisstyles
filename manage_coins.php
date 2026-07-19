<?php
/* ============================================================
   manage_coins.php — Sumathi's Style Coins backend
   Rules: Earn = 2% of order amount (1 coin = ₹1), credited only
   when an order's status becomes "Delivered".
   Redeem = max 20% of the current order's amount, capped by
   the customer's available balance.
   ------------------------------------------------------------
   NOTE: This assumes you already have a db.php in the same
   folder that opens a mysqli connection into $conn, the same
   way your other PHP files (get_products.php etc.) do.
   If your connection variable/file is named differently,
   just change the require line below.
   ============================================================ */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/db.php'; // must define $conn (mysqli)

define('EARN_PERCENT', 0.02);      // 2% of order amount -> coins (1 coin = ₹1)
define('MAX_REDEEM_PERCENT', 0.20); // max 20% of order amount can be paid via coins

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function getBalance($conn, $mobile) {
    $stmt = $conn->prepare("SELECT balance FROM customer_coins WHERE mobile = ?");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) return (int)$row['balance'];
    return 0;
}

function ensureCustomerRow($conn, $mobile) {
    $stmt = $conn->prepare("INSERT IGNORE INTO customer_coins (mobile, balance) VALUES (?, 0)");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // ?action=balance&mobile=9999999999
    $mobile = trim($_GET['mobile'] ?? '');
    if (!$mobile) respond(['status' => 'error', 'message' => 'mobile is required'], 400);
    ensureCustomerRow($conn, $mobile);
    $balance = getBalance($conn, $mobile);
    respond(['status' => 'success', 'mobile' => $mobile, 'balance' => $balance, 'coin_value' => 1]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) respond(['status' => 'error', 'message' => 'Invalid JSON body'], 400);

    $action = $input['action'] ?? '';
    $mobile = trim($input['mobile'] ?? '');
    if (!$mobile) respond(['status' => 'error', 'message' => 'mobile is required'], 400);

    ensureCustomerRow($conn, $mobile);

    // -----------------------------------------------------
    // AWARD: called by admin dashboard when order -> Delivered
    // body: { action:'award', mobile, order_id, order_amount }
    // -----------------------------------------------------
    if ($action === 'award') {
        $orderId = trim($input['order_id'] ?? '');
        $amount  = (float)($input['order_amount'] ?? 0);
        if ($amount <= 0) respond(['status' => 'error', 'message' => 'order_amount must be > 0'], 400);

        // Prevent double-award for the same order_id
        if ($orderId) {
            $chk = $conn->prepare("SELECT id FROM coins_ledger WHERE order_id = ? AND type = 'earn' LIMIT 1");
            $chk->bind_param("s", $orderId);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                respond(['status' => 'success', 'message' => 'Coins already awarded for this order', 'balance' => getBalance($conn, $mobile)]);
            }
        }

        $coinsEarned = (int)floor($amount * EARN_PERCENT);
        if ($coinsEarned <= 0) {
            respond(['status' => 'success', 'message' => 'Order amount too low to earn coins', 'balance' => getBalance($conn, $mobile)]);
        }

        $conn->begin_transaction();
        try {
            $upd = $conn->prepare("UPDATE customer_coins SET balance = balance + ? WHERE mobile = ?");
            $upd->bind_param("is", $coinsEarned, $mobile);
            $upd->execute();

            $log = $conn->prepare("INSERT INTO coins_ledger (mobile, order_id, type, coins, order_amount, note) VALUES (?, ?, 'earn', ?, ?, ?)");
            $note = "Earned {$coinsEarned} coins (2% of ₹{$amount}) on delivery";
            $log->bind_param("ssids", $mobile, $orderId, $coinsEarned, $amount, $note);
            $log->execute();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            respond(['status' => 'error', 'message' => 'Failed to award coins: ' . $e->getMessage()], 500);
        }

        respond(['status' => 'success', 'coins_earned' => $coinsEarned, 'balance' => getBalance($conn, $mobile)]);
    }

    // -----------------------------------------------------
    // REDEEM: called at checkout when customer applies coins
    // body: { action:'redeem', mobile, order_id, order_amount, coins_to_use }
    // -----------------------------------------------------
    if ($action === 'redeem') {
        $orderId  = trim($input['order_id'] ?? '');
        $amount   = (float)($input['order_amount'] ?? 0);
        $wantCoins = (int)($input['coins_to_use'] ?? 0);
        if ($amount <= 0 || $wantCoins <= 0) respond(['status' => 'error', 'message' => 'order_amount and coins_to_use must be > 0'], 400);

        $balance = getBalance($conn, $mobile);
        $maxRedeemable = (int)floor($amount * MAX_REDEEM_PERCENT);
        $coinsUsed = min($wantCoins, $balance, $maxRedeemable);

        if ($coinsUsed <= 0) {
            respond(['status' => 'error', 'message' => 'No coins can be redeemed (check balance or 20% cap)', 'max_redeemable' => $maxRedeemable, 'balance' => $balance], 400);
        }

        $conn->begin_transaction();
        try {
            $upd = $conn->prepare("UPDATE customer_coins SET balance = balance - ? WHERE mobile = ? AND balance >= ?");
            $upd->bind_param("isi", $coinsUsed, $mobile, $coinsUsed);
            $upd->execute();
            if ($upd->affected_rows === 0) throw new Exception('Insufficient balance');

            $log = $conn->prepare("INSERT INTO coins_ledger (mobile, order_id, type, coins, order_amount, note) VALUES (?, ?, 'redeem', ?, ?, ?)");
            $note = "Redeemed {$coinsUsed} coins (₹{$coinsUsed} off ₹{$amount} order)";
            $log->bind_param("ssids", $mobile, $orderId, $coinsUsed, $amount, $note);
            $log->execute();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            respond(['status' => 'error', 'message' => 'Failed to redeem coins: ' . $e->getMessage()], 500);
        }

        respond([
            'status' => 'success',
            'coins_used' => $coinsUsed,
            'discount_value' => $coinsUsed, // 1 coin = ₹1
            'new_balance' => getBalance($conn, $mobile)
        ]);
    }

    respond(['status' => 'error', 'message' => 'Unknown action'], 400);
}

respond(['status' => 'error', 'message' => 'Method not allowed'], 405);