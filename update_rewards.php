<?php
include 'config.php';

$stmt = $conn->prepare("SELECT uc.id, uc.user_id, uc.card_name FROM user_cards uc WHERE TIMESTAMPDIFF(HOUR, uc.unlocked_at, NOW()) >= 8 AND uc.expires_at > NOW()");
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