<?php
include 'config.php';
include 'config/config-api.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// تعریف توکن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // ایجاد توکن تصادفی
}

// بررسی لاگین
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "User not logged in."
    ]);
    exit();
}

// بررسی توکن CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        "success" => false,
        "message" => "CSRF token mismatch."
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$amount = floatval($_POST['amount'] ?? 0);

if ($amount <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid amount."
    ]);
    exit();
}

// آدرس والت پروژه
$project_wallet = "UQDCy7GZFzZCUwM4_R7ZgqZW34aDfgV9CEY8BX-ucyQRxGfo";

// API Key TonCenter
$ton_api_key = TON_API_KEY;

// فراخوانی API TonCenter برای چک کردن تراکنش‌ها
$api_url = "https://toncenter.com/api/v2/getTransactions?address=" . urlencode($project_wallet) . "&limit=100";
$headers = [
    "Content-Type: application/json",
    "Authorization: Bearer " . $ton_api_key
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // برای تست لوکال، این رو می‌تونی فعال کنی

$response = curl_exec($ch);
if (curl_error($ch)) {
    echo json_encode([
        "success" => false,
        "message" => "API error: " . curl_error($ch)
    ]);
    curl_close($ch);
    exit();
}
curl_close($ch);

$transactions = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($transactions['result'])) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to decode TON API response."
    ]);
    exit();
}

// جستجو برای تراکنش‌های مرتبط با کاربر
$found_transaction = false;
foreach ($transactions['result'] as $tx) {
    if (isset($tx['out_msgs']) && !empty($tx['out_msgs'])) {
        foreach ($tx['out_msgs'] as $msg) {
            if (isset($msg['destination']) && $msg['destination'] === $project_wallet) {
                $tx_amount = floatval($tx['value']) / 1000000000; // تبدیل از nanoTON به TON
                if ($tx_amount >= $amount) {
                    $found_transaction = true;
                    break 2; // خروج از حلقه‌های تودرتو
                }
            }
        }
    }
}

if (!$found_transaction) {
    echo json_encode([
        "success" => false,
        "message" => "No matching transaction found. Please send $amount TON to $project_wallet and try again."
    ]);
    exit();
}

// گرفتن اطلاعات کاربر
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
    exit();
}

$current_balance = (float)$user['balance'];
$new_balance = $current_balance + $amount;

// به‌روزرسانی موجودی در دیتابیس
$update = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
$update->bind_param("di", $new_balance, $user_id);

if ($update->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Deposited $amount TON successfully!",
        "new_balance" => $new_balance,
        "wallet_address" => $project_wallet
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $update->error
    ]);
}