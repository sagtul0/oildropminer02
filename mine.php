<?php
include 'header.php'; // Includes database connection as $conn and session start

header('Content-Type: application/json; charset=utf-8');

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token mismatch for user session: " . session_id());
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

// بررسی لاگین
if (!isset($_SESSION['user_id']) && !isset($_SESSION['chat_id'])) {
    error_log("User not logged in for mine request. Session ID: " . session_id());
    echo json_encode(["success" => false, "message" => "User not logged in."]);
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
    error_log("Blocked user attempted to mine. User ID: $user_id, Session ID: " . session_id());
    echo json_encode([
        "success" => false,
        "message" => "Your account has been blocked."
    ]);
    exit;
}
$stmt->close();

// گرفتن اطلاعات کاربر
$stmt = $conn->prepare("SELECT oil_drops, today_clicks, last_click_day, boost_multiplier FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    error_log("Database query failed for user $user_id: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database query failed: " . $conn->error]);
    exit;
}

$user = $result->fetch_assoc();

if (!$user) {
    error_log("User not found for ID: $user_id");
    echo json_encode(["success" => false, "message" => "User not found."]);
    exit;
}

$oil_drops = (int)$user['oil_drops'];
$today_clicks = (int)$user['today_clicks'];
$last_click_day = $user['last_click_day'] ? new DateTime($user['last_click_day']) : null;
$boost_multiplier = (float)$user['boost_multiplier'] ?? 1.0; // پیش‌فرض 1.0

error_log("User data before mine - User ID: $user_id, oil_drops: $oil_drops, today_clicks: $today_clicks, last_click_day: " . ($last_click_day ? $last_click_day->format('Y-m-d') : 'null') . ", boost_multiplier: $boost_multiplier");

// بررسی محدودیت روزانه و ریست هر 24 ساعت
$today = date('Y-m-d');
if (!$last_click_day || $last_click_day->format('Y-m-d') != $today) {
    $today_clicks = 0;
    $stmt = $conn->prepare("UPDATE users SET today_clicks = 0, last_click_day = ? WHERE id = ? OR chat_id = ?");
    $stmt->bind_param("sii", $today, $user_id, $user_id);
    if (!$stmt->execute()) {
        error_log("Database error resetting daily clicks for user $user_id: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Database error resetting daily clicks: " . $conn->error]);
        exit;
    }
    error_log("Daily reset performed for user $user_id - today_clicks: 0");
}

// محدودیت روزانه (حداکثر 1000 کلیک)
if ($today_clicks >= 1000) {
    error_log("Daily limit reached for user $user_id - today_clicks: $today_clicks");
    echo json_encode(["success" => false, "message" => "Daily limit (1000 clicks) reached."]);
    exit;
}

// محاسبه Oil Drops با ضریب بوست
$base_drops = 1; // مقدار پایه هر کلیک
$new_oil_drops = $oil_drops + ($base_drops * $boost_multiplier);
$new_today_clicks = $today_clicks + 1;

// به‌روزرسانی دیتابیس
$stmt = $conn->prepare("UPDATE users SET oil_drops = ?, today_clicks = ?, last_click_day = ? WHERE id = ? OR chat_id = ?");
$stmt->bind_param("iisii", $new_oil_drops, $new_today_clicks, $today, $user_id, $user_id);
if (!$stmt->execute()) {
    error_log("Database error updating mine for user $user_id: " . $conn->error);
    echo json_encode(["success" => false, "message" => "Database error updating mine: " . $conn->error]);
    exit;
}

// بررسی دوباره برای اطمینان
$stmt = $conn->prepare("SELECT today_clicks FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$check_result = $stmt->get_result();
if ($check_result) {
    $updated_user = $check_result->fetch_assoc();
    error_log("After update for user $user_id, today_clicks in DB: " . $updated_user['today_clicks']);
} else {
    error_log("Database check failed for user $user_id: " . $conn->error);
}

echo json_encode([
    "success" => true,
    "oil_drops" => $new_oil_drops,
    "clicks_left" => 1000 - $new_today_clicks,
    "today_clicks" => $new_today_clicks
]);
?>