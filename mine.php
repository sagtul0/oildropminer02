<?php
include 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تنظیم هدر برای JSON
header('Content-Type: application/json; charset=utf-8');

// بررسی لاگین
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in for mine request. Session ID: " . session_id());
    echo json_encode(["success" => false, "message" => "User not logged in."]);
    exit();
}

// کاربر جاری
$user_id = $_SESSION['user_id'];

// گرفتن اطلاعات کاربر
$result = $conn->query("SELECT oil_drops, today_clicks, last_click_day, boost_multiplier FROM users WHERE id = $user_id");
if (!$result) {
    error_log("Database query failed: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database query failed: " . $conn->error]);
    exit();
}

$user = $result->fetch_assoc();

if (!$user) {
    error_log("User not found for ID: $user_id");
    echo json_encode(["success" => false, "message" => "User not found."]);
    exit();
}

$oil_drops = (int)$user['oil_drops'];
$today_clicks = (int)$user['today_clicks'];
$last_click_day = $user['last_click_day'] ? new DateTime($user['last_click_day']) : null;
$boost_multiplier = (float)$user['boost_multiplier'] ?? 1.0; // پیش‌فرض 1.0

error_log("User data before mine - oil_drops: $oil_drops, today_clicks: $today_clicks, last_click_day: " . ($last_click_day ? $last_click_day->format('Y-m-d') : 'null') . ", boost_multiplier: $boost_multiplier");

// بررسی محدودیت روزانه و ریست هر 24 ساعت
$today = date('Y-m-d');
if (!$last_click_day || $last_click_day->format('Y-m-d') != $today) {
    $today_clicks = 0;
    $reset_query = $conn->query("UPDATE users SET today_clicks = 0, last_click_day = '$today' WHERE id = $user_id");
    if (!$reset_query) {
        error_log("Database error resetting daily clicks: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Database error resetting daily clicks: " . $conn->error]);
        exit();
    }
    error_log("Daily reset performed - today_clicks: 0");
}

// محدودیت روزانه (حداکثر 1000 کلیک)
if ($today_clicks >= 1000) {
    echo json_encode(["success" => false, "message" => "Daily limit (1000 clicks) reached."]);
    exit();
}

// محاسبه Oil Drops با ضریب بوست
$base_drops = 1; // مقدار پایه هر کلیک
$new_oil_drops = $oil_drops + ($base_drops * $boost_multiplier);
$new_today_clicks = $today_clicks + 1;

// به‌روزرسانی دیتابیس
$update_query = $conn->query("UPDATE users SET oil_drops = $new_oil_drops, today_clicks = $new_today_clicks, last_click_day = '$today' WHERE id = $user_id");
if (!$update_query) {
    error_log("Database error updating mine: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database error updating mine: " . $conn->error]);
    exit();
}

// بررسی دوباره برای اطمینان
$check_result = $conn->query("SELECT today_clicks FROM users WHERE id = $user_id");
if ($check_result) {
    $updated_user = $check_result->fetch_assoc();
    error_log("After update, today_clicks in DB: " . $updated_user['today_clicks']);
} else {
    error_log("Database check failed: " . $conn->error);
}

echo json_encode([
    "success" => true,
    "oil_drops" => $new_oil_drops,
    "clicks_left" => 1000 - $new_today_clicks,
    "today_clicks" => $new_today_clicks
]);