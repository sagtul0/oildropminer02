<?php
include 'header.php'; // Includes database connection as $conn

$stmt = $conn->prepare("SELECT uc.id, uc.user_id, uc.card_name 
                        FROM user_cards uc 
                        WHERE EXTRACT(EPOCH FROM (NOW() - uc.unlocked_at))/3600 >= 8 
                        AND uc.expires_at > NOW()");
$stmt->execute();
$result = $stmt->get_result();

// تعریف پاداش برای هر کارت (بر اساس آرایه کارت‌ها در oil_cards.php)
$card_rewards = [
    'Oil Rig Booster' => 150,
    'Refinery Power' => 530,
    'Oil Tanker Boost' => 300,
    'Drill Site Energy' => 450
];

while ($card = $result->fetch_assoc()) {
    $user_id = $card['user_id'];
    $card_name = $card['card_name'];
    $reward = $card_rewards[$card_name] ?? 0; // پاداش کارت از آرایه

    // Check if user is blocked
    $stmt_block = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? OR chat_id = ?");
    $stmt_block->bind_param("ii", $user_id, $user_id);
    $stmt_block->execute();
    $result_block = $stmt_block->get_result();
    $user = $result_block->fetch_assoc();
    if ($user['is_blocked']) {
        continue; // Skip this user if blocked
    }
    $stmt_block->close();

    if ($reward > 0) {
        // به‌روزرسانی oil_drops کاربر
        $update_stmt = $conn->prepare("UPDATE users SET oil_drops = oil_drops + ? WHERE id = ?");
        $update_stmt->bind_param("ii", $reward, $user_id);
        $update_stmt->execute();
        $update_stmt->close();

        // به‌روزرسانی زمان آخرین پاداش (unlocked_at به‌عنوان زمان آخرین پاداش)
        $update_card_stmt = $conn->prepare("UPDATE user_cards SET unlocked_at = NOW() WHERE id = ?");
        $update_card_stmt->bind_param("i", $card['id']);
        $update_card_stmt->execute();
        $update_card_stmt->close();
    }
}

$stmt->close();
$conn->close();
?>