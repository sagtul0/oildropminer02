<?php
// telegram.php (به‌روز شده برای بات تلگرام بین‌المللی با لینک به سایت)
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_log("Script started at " . date('Y-m-d H:i:s'));

include 'config.php'; // اتصال به دیتابیس با PDO

// تست اتصال به دیتابیس
try {
    $pdo->query("SELECT 1");
    error_log("Database is working correctly at " . date('Y-m-d H:i:s'));
} catch (PDOException $e) {
    error_log("Error connecting to database: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
    die("Error connecting to database: " . $e->getMessage());
}

// گرفتن توکن از متغیر محیطی Render
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (!$bot_token) {
    error_log("TELEGRAM_BOT_TOKEN is not set in environment variables! at " . date('Y-m-d H:i:s'));
    die("Error: TELEGRAM_BOT_TOKEN not found. Check Render environment variables.");
}
error_log("Bot token retrieved: $bot_token at " . date('Y-m-d H:i:s'));

// دریافت اطلاعات از ربات تلگرام
$update_json = file_get_contents('php://input');
if ($update_json === false) {
    error_log("Error reading php://input: " . (error_get_last()['message'] ?? 'Unknown error') . " at " . date('Y-m-d H:i:s'));
    die("Error: Cannot read Webhook data. Check php.ini (allow_url_fopen) and server.");
}
error_log("Webhook data received: $update_json at " . date('Y-m-d H:i:s'));

$update = json_decode($update_json, true);

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $username = $update['message']['from']['username'] ?? '';
    $first_name = $update['message']['from']['first_name'] ?? '';
    $last_name = $update['message']['from']['last_name'] ?? '';
    $text = $update['message']['text'] ?? '';

    error_log("Message received - Chat ID: $chat_id, Text: $text, Username: $username at " . date('Y-m-d H:i:s'));

    // چک کن کاربر قبلاً ثبت شده یا نه
    $stmt = $pdo->prepare("SELECT chat_id FROM users WHERE chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // ثبت کاربر جدید
        $stmt = $pdo->prepare("INSERT INTO users (chat_id, username, first_name, last_name, created_at) VALUES (:chat_id, :username, :first_name, :last_name, NOW())");
        $stmt->execute([
            'chat_id' => $chat_id,
            'username' => $username,
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);
        error_log("New user registered with Chat ID: $chat_id at " . date('Y-m-d H:i:s'));
        sendTelegramMessage($bot_token, $chat_id, "Welcome to Oil Drop Miner! Your information has been registered. Use /start to begin.");
    } else {
        // به‌روزرسانی اطلاعات اگه نام تغییر کرده
        $stmt = $pdo->prepare("UPDATE users SET username = :username, first_name = :first_name, last_name = :last_name, updated_at = NOW() WHERE chat_id = :chat_id");
        $stmt->execute([
            'chat_id' => $chat_id,
            'username' => $username,
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);
        error_log("User updated with Chat ID: $chat_id at " . date('Y-m-d H:i:s'));
        sendTelegramMessage($bot_token, $chat_id, "You are already registered! Use /start to see commands.");
    }

    // پردازش دستورات
    if ($text === '/start') {
        sendTelegramMessage($bot_token, $chat_id, "Welcome to Oil Drop Miner! Your information has been registered. Use /help to see commands.");
    } elseif ($text === '/help') {
        sendTelegramMessage($bot_token, $chat_id, "Commands:\n/start - Start\n/help - Help\n/refer <chat_id> - Invite a friend\n/oilcards - View Oil Cards: https://oildropminer02-eay2.onrender.com/oil_cards.php\n/chatid - Get your Chat ID for website login");
    } elseif ($text === '/oilcards') {
        sendTelegramMessage($bot_token, $chat_id, "View and purchase Oil Cards here: https://oildropminer02-eay2.onrender.com/oil_cards.php");
    } elseif ($text === '/chatid') {
        sendTelegramMessage($bot_token, $chat_id, "Your Chat ID is: $chat_id\nUse this to log in to the website: https://oildropminer02-eay2.onrender.com/login.php");
    } elseif (preg_match('/^\/refer (\d+)$/', $text, $matches)) {
        $referred_id = $matches[1];
        $stmt = $pdo->prepare("INSERT INTO referrals (referrer_id, referred_id, created_at) VALUES (:chat_id, :referred_id, NOW())");
        $stmt->execute(['chat_id' => $chat_id, 'referred_id' => $referred_id]);
        sendTelegramMessage($bot_token, $chat_id, "Referral successfully registered!");
    }
} else {
    error_log("No message found in Webhook data at " . date('Y-m-d H:i:s'));
}

// تابع ارسال پیام به تلگرام با cURL
function sendTelegramMessage($bot_token, $chat_id, $message) {
    $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $message];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // برای تست (در محیط واقعی فعال کن)
    $response = curl_exec($ch);
    if (curl_error($ch)) {
        error_log("cURL Error: " . curl_error($ch) . " at " . date('Y-m-d H:i:s'));
    } else {
        error_log("cURL Response: $response at " . date('Y-m-d H:i:s'));
    }
    curl_close($ch);

    return $response;
}