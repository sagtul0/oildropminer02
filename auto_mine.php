<?php
include 'header.php'; // Includes database connection as $conn

// گرفتن کاربرانی که auto_clicker فعال دارن
$users_stmt = $conn->prepare("SELECT id, oil_drops, today_clicks, last_auto_mine_day, boost_multiplier, auto_clicker FROM users WHERE auto_clicker = TRUE");
$users_stmt->execute();
$users = $users_stmt->get_result();

$today = date('Y-m-d');

while ($user = $users->fetch_assoc()) {
    $user_id = $user['id'];
    $oil_drops = (int)$user['oil_drops'];
    $today_clicks = (int)$user['today_clicks'];
    $last_auto_mine_day = $user['last_auto_mine_day'] ? new DateTime($user['last_auto_mine_day']) : null;
    $boost_multiplier = (float)$user['boost_multiplier'] ?? 1.0; // پیش‌فرض 1.0
    $auto_clicker = (bool)$user['auto_clicker'];

    // Check if user is blocked
    $stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_blocked = $result->fetch_assoc();
    if ($user_blocked['is_blocked']) {
        continue; // Skip this user if blocked
    }
    $stmt->close();

    // بررسی ریست 24 ساعته برای ماینینگ خودکار
    if (!$last_auto_mine_day || $last_auto_mine_day->format('Y-m-d') != $today) {
        if ($auto_clicker && $today_clicks < 1000) { // محدودیت 1000 کلیک در روز
            $remaining_clicks = 1000 - $today_clicks; // کلیک‌های باقی‌مانده
            $clicks_to_add = min(1000, $remaining_clicks); // حداکثر 1000 کلیک یا باقی‌مانده

            // محاسبه مقدار ماینینگ خودکار با ضریب بوست
            $base_auto_mine = $clicks_to_add; // پایه 1000 کلیک، ولی محدود به باقی‌مانده
            $auto_mine_amount = $base_auto_mine * $boost_multiplier; // اعمال ضریب بوست (مثلاً 2x، 5x، 10x)

            $new_oil_drops = $oil_drops + $auto_mine_amount;
            $new_today_clicks = $today_clicks + $clicks_to_add;

            // به‌روزرسانی دیتابیس
            $update_stmt = $conn->prepare("UPDATE users SET oil_drops = ?, today_clicks = ?, last_auto_mine_day = ?, last_click_day = ? WHERE id = ?");
            $update_stmt->bind_param("iissi", $new_oil_drops, $new_today_clicks, $today, $today, $user_id);
            if ($update_stmt->execute()) {
                error_log("Auto mine successful for user ID $user_id - oil_drops: $new_oil_drops, today_clicks: $new_today_clicks, amount: $auto_mine_amount");
            } else {
                error_log("Database error in auto mine for user ID $user_id: " . $update_stmt->error);
            }
            $update_stmt->close();
        }
    }
}
$users_stmt->close();
?>