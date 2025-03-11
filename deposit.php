<?php
include 'header.php'; // Includes database connection as $conn and session start
include 'config/config-api.php';

header('Content-Type: application/json; charset=utf-8');

// تعریف توکن CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // ایجاد توکن تصادفی
}

// بررسی لاگین
if (!isset($_SESSION['user_id']) && !isset($_SESSION['chat_id'])) {
    error_log("User not logged in for deposit request. Session ID: " . session_id());
    echo json_encode([
        "success" => false,
        "message" => "User not logged in."
    ]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['chat_id'];

// Check if user is blocked
$stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if ($user['is_blocked']) {
    error_log("Blocked user attempted to deposit. User ID: $user_id, Session ID: " . session_id());
    echo json_encode([
        "success" => false,
        "message" => "Your account has been blocked."
    ]);
    exit;
}
$stmt->close();

// بررسی توکن CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token mismatch for user $user_id, Session ID: " . session_id());
    echo json_encode([
        "success" => false,
        "message" => "CSRF token mismatch."
    ]);
    exit;
}

$amount = floatval($_POST['amount'] ?? 0);

if ($amount <= 0) {
    error_log("Invalid deposit amount ($amount) for user $user_id");
    echo json_encode([
        "success" => false,
        "message" => "Invalid amount."
    ]);
    exit;
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
    error_log("API error for user $user_id: " . curl_error($ch));
    echo json_encode([
        "success" => false,
        "message" => "Failed to connect to TON API. Please try again later."
    ]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$transactions = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($transactions['result'])) {
    error_log("Failed to decode TON API response for user $user_id: " . json_last_error_msg());
    echo json_encode([
        "success" => false,
        "message" => "Failed to process transaction data. Please try again."
    ]);
    exit;
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
    error_log("No matching transaction found for user $user_id with amount $amount");
    echo json_encode([
        "success" => false,
        "message" => "No matching transaction found. Please send $amount TON to $project_wallet and try again."
    ]);
    exit;
}

// گرفتن اطلاعات کاربر
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    error_log("User not found for deposit. User ID: $user_id");
    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
    exit;
}

$current_balance = (float)$user['balance'];
$new_balance = $current_balance + $amount;

// به‌روزرسانی موجودی در دیتابیس
$update = $conn->prepare("UPDATE users SET balance = ? WHERE id = ? OR chat_id = ?");
$update->bind_param("dii", $new_balance, $user_id, $user_id);

if ($update->execute()) {
    error_log("Successful deposit of $amount TON for user $user_id. New balance: $new_balance");
    echo json_encode([
        "success" => true,
        "message" => "Deposited $amount TON successfully!",
        "new_balance" => $new_balance,
        "wallet_address" => $project_wallet
    ]);
} else {
    error_log("Database error updating balance for user $user_id: " . $update->error);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $update->error
    ]);
}
?>