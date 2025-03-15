<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

include 'config.php'; // لود کردن فایل تنظیمات

// بررسی وجود توکن بات
if (!isset($botToken)) {
    error_log("Bot token not set in config.php");
    die(json_encode(['status' => 'error', 'message' => 'Bot token not configured']));
}

// دریافت داده‌های webhook از تلگرام
$update = json_decode(file_get_contents('php://input'), true);
error_log("Webhook data received: " . print_r($update, true));

// بررسی وجود پیام
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'];
    error_log("Message received - Chat ID: $chat_id, Text: $text");

    // ثبت کاربر در دیتابیس اگر وجود نداشته باشد
    try {
        $stmt = $conn->prepare("INSERT INTO users (chat_id, oil_drops, balance, created_at) VALUES (:chat_id, 0, 0, NOW()) ON CONFLICT (chat_id) DO NOTHING");
        $stmt->execute(['chat_id' => $chat_id]);
    } catch (PDOException $e) {
        error_log("Database error while registering user: " . $e->getMessage());
        die(json_encode(['status' => 'error', 'message' => 'Database error']));
    }

    // تنظیم منوهای بات
    $commands = [
        ['command' => 'start', 'description' => 'Start the bot'],
        ['command' => 'dashboard', 'description' => 'View your dashboard'],
        ['command' => 'mine', 'description' => 'Mine Oil Drops'],
        ['command' => 'plans', 'description' => 'View Boost Plans'],
        ['command' => 'deposit', 'description' => 'Deposit TON'],
        ['command' => 'referrals', 'description' => 'Invite Friends'],
        ['command' => 'boosts', 'description' => 'Manage Boosts'],
        ['command' => 'cards', 'description' => 'View Cards'],
        ['command' => 'withdraw', 'description' => 'Withdraw TON']
    ];
    $setCommandsUrl = "https://api.telegram.org/bot$botToken/setMyCommands";
    $commandsData = json_encode(['commands' => $commands]);
    $response = file_get_contents($setCommandsUrl . "?commands=" . urlencode($commandsData));
    error_log("Set commands response: " . $response);

    // مدیریت دستورات
    if ($text === '/start') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Open App 📱', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/webapp.php']]],
            ]
        ];
        $replyMarkup = json_encode($keyboard);

        // گرفتن اطلاعات کاربر برای نمایش
        $stmt = $conn->prepare("SELECT oil_drops, balance FROM users WHERE chat_id = :chat_id");
        $stmt->execute(['chat_id' => $chat_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $oil_drops = $user['oil_drops'] ?? 0;
        $balance = $user['balance'] ?? 0;

        $message = "Welcome to Oil Drop Miner! You have $oil_drops Oil Drops and $balance TON. Click below to open the app.";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=$replyMarkup";
        $response = file_get_contents($url);
        error_log("Message with keyboard sent: $response");
    } elseif ($text === '/dashboard') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Open Dashboard 📊', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/webapp.php']]],
            ]
        ];
        $replyMarkup = json_encode($keyboard);
        $message = "Click below to view your dashboard.";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=$replyMarkup";
        $response = file_get_contents($url);
        error_log("Dashboard link sent: $response");
    } elseif ($text === '/mine') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Go to Mining ⛏️', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/mine.php']]],
            ]
        ];
        $replyMarkup = json_encode($keyboard);
        $message = "Click below to start mining Oil Drops.";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=$replyMarkup";
        $response = file_get_contents($url);
        error_log("Mining link sent: $response");
    } elseif ($text === '/plans') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'View Plans 📋', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/plans.php']]],
            ]
        ];
        $replyMarkup = json_encode($keyboard);
        $message = "Click below to view available boost plans.";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=$replyMarkup";
        $response = file_get_contents($url);
        error_log("Plans link sent: $response");
    } elseif ($text === '/deposit') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Deposit TON 💰', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/deposit.php']]],
            ]
        ];
        $replyMarkup = json_encode($keyboard);
        $message = "Click below to deposit TON.";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=$replyMarkup";
        $response = file_get_contents($url);
        error_log("Deposit link sent: $response");
    } elseif ($text === '/referrals') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Invite Friends 👥', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/referrals.php']]],
            ]
        ];
        $replyMarkup = json_encode($keyboard);
        $message = "Click below to invite friends and earn rewards.";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=$replyMarkup";
        $response = file_get_contents($url);
        error_log("Referrals link sent: $response");
    } elseif ($text === '/boosts') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Manage Boosts 🚀', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/boosts.php']]],
            ]
        ];
        $replyMarkup = json_encode($keyboard);
        $message = "Click below to manage your boosts.";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=$replyMarkup";
        $response = file_get_contents($url);
        error_log("Boosts link sent: $response");
    } elseif ($text === '/cards') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'View Cards 🃏', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/cards.php']]],
            ]
        ];
        $replyMarkup = json_encode($keyboard);
        $message = "Click below to view your cards.";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=$replyMarkup";
        $response = file_get_contents($url);
        error_log("Cards link sent: $response");
    } elseif ($text === '/withdraw') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Withdraw TON 💸', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/withdraw.php']]],
            ]
        ];
        $replyMarkup = json_encode($keyboard);
        $message = "Click below to withdraw your TON.";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=$replyMarkup";
        $response = file_get_contents($url);
        error_log("Withdraw link sent: $response");
    } else {
        // پاسخ پیش‌فرض برای دستورات ناشناخته
        $message = "Please use one of the available commands: /start, /dashboard, /mine, /plans, /deposit, /referrals, /boosts, /cards, /withdraw";
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=" . urlencode($message);
        $response = file_get_contents($url);
        error_log("Unknown command response: $response");
    }
}

echo json_encode(['status' => 'ok']);
?>