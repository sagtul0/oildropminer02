<?php
include 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token mismatch for user session: " . session_id());
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

// بررسی لاگین
if (!isset($_SESSION['chat_id'])) {
    error_log("User not logged in for mine request. Session ID: " . session_id());
    echo json_encode(["success" => false, "message" => "User not logged in."]);
    exit;
}

$chat_id = $_SESSION['chat_id'];

// Check if user is blocked
$stmt = $conn->prepare("SELECT is_blocked FROM users WHERE chat_id = :chat_id");
$stmt->execute(['chat_id' => $chat_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user['is_blocked']) {
    error_log("Blocked user attempted to mine. Chat ID: $chat_id, Session ID: " . session_id());
    echo json_encode([
        "success" => false,
        "message" => "Your account has been blocked."
    ]);
    exit;
}

// گرفتن اطلاعات کاربر
$stmt = $conn->prepare("SELECT oil_drops, today_clicks, last_click_day, boost_multiplier FROM users WHERE chat_id = :chat_id");
$stmt->execute(['chat_id' => $chat_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    error_log("User not found for Chat ID: $chat_id");
    echo json_encode(["success" => false, "message" => "User not found."]);
    exit;
}

$oil_drops = (int)$user['oil_drops'];
$today_clicks = (int)$user['today_clicks'];
$last_click_day = $user['last_click_day'] ? new DateTime($user['last_click_day']) : null;
$boost_multiplier = (float)($user['boost_multiplier'] ?? 1.0);

error_log("User data before mine - Chat ID: $chat_id, oil_drops: $oil_drops, today_clicks: $today_clicks, last_click_day: " . ($last_click_day ? $last_click_day->format('Y-m-d') : 'null') . ", boost_multiplier: $boost_multiplier");

// بررسی محدودیت روزانه و ریست هر 24 ساعت
$today = date('Y-m-d');
if (!$last_click_day || $last_click_day->format('Y-m-d') != $today) {
    $today_clicks = 0;
    $stmt = $conn->prepare("UPDATE users SET today_clicks = :clicks, last_click_day = :last_click_day WHERE chat_id = :chat_id");
    $stmt->execute(['clicks' => 0, 'last_click_day' => $today, 'chat_id' => $chat_id]);
    error_log("Daily reset performed for chat_id $chat_id - today_clicks: 0");
}

// محدودیت روزانه (حداکثر 1000 کلیک)
if ($today_clicks >= 1000) {
    error_log("Daily limit reached for chat_id $chat_id - today_clicks: $today_clicks");
    echo json_encode(["success" => false, "message" => "Daily limit (1000 clicks) reached."]);
    exit;
}

// محاسبه Oil Drops با ضریب بوست
$base_drops = 1;
$new_oil_drops = $oil_drops + ($base_drops * $boost_multiplier);
$new_today_clicks = $today_clicks + 1;

// به‌روزرسانی دیتابیس
$stmt = $conn->prepare("UPDATE users SET oil_drops = :oil_drops, today_clicks = :today_clicks, last_click_day = :last_click_day WHERE chat_id = :chat_id");
$stmt->execute(['oil_drops' => $new_oil_drops, 'today_clicks' => $new_today_clicks, 'last_click_day' => $today, 'chat_id' => $chat_id]);

echo json_encode([
    "success" => true,
    "oil_drops" => $new_oil_drops,
    "clicks_left" => 1000 - $new_today_clicks,
    "today_clicks" => $new_today_clicks
]);
?>