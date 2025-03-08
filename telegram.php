<?php
// telegram.php (به‌روز شده برای بات تلگرام)
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_log("Script started");

include 'config.php'; // اتصال به دیتابیس با PDO

// تست اتصال به دیتابیس
try {
    $pdo->query("SELECT 1");
    error_log("دیتابیس به درستی کار می‌کند");
} catch (PDOException $e) {
    error_log("خطا در اتصال به دیتابیس: " . $e->getMessage());
    die("خطا در اتصال به دیتابیس");
}

// گرفتن توکن از متغیر محیطی Render
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (!$bot_token) {
    error_log("TELEGRAM_BOT_TOKEN is not set in environment variables!");
    die("Error: TELEGRAM_BOT_TOKEN not found. Check Render environment variables.");
}
error_log("Bot token retrieved: " . $bot_token);

// دریافت اطلاعات از ربات تلگرام
$update_json = file_get_contents('php://input');
if ($update_json === false) {
    error_log("Error reading php://input: " . (error_get_last()['message'] ?? 'Unknown error'));
    die("Error: Cannot read Webhook data. Check php.ini (allow_url_fopen) and server.");
}
error_log("Webhook data received: " . $update_json);

$update = json_decode($update_json, true);

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $username = $update['message']['from']['username'] ?? '';
    $first_name = $update['message']['from']['first_name'] ?? '';
    $last_name = $update['message']['from']['last_name'] ?? '';
    $text = $update['message']['text'] ?? '';

    error_log("Message received - Chat ID: $chat_id, Text: $text");

    // چک کن کاربر قبلاً ثبت شده یا نه
    $stmt = $pdo->prepare("SELECT chat_id FROM users WHERE chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // ثبت کاربر جدید
        $stmt = $pdo->prepare("INSERT INTO users (chat_id, username, first_name, last_name) VALUES (:chat_id, :username, :first_name, :last_name)");
        $stmt->execute([
            'chat_id' => $chat_id,
            'username' => $username,
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);
        error_log("New user registered with Chat ID: $chat_id");
        sendTelegramMessage($bot_token, $chat_id, "خوش اومدی به Oil Drop Miner! اطلاعاتت ثبت شد. از /start استفاده کن.");
    } else {
        // به‌روزرسانی اطلاعات اگه نام تغییر کرده
        $stmt = $pdo->prepare("UPDATE users SET username = :username, first_name = :first_name, last_name = :last_name WHERE chat_id = :chat_id");
        $stmt->execute([
            'chat_id' => $chat_id,
            'username' => $username,
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);
        error_log("User updated with Chat ID: $chat_id");
        sendTelegramMessage($bot_token, $chat_id, "تو قبلاً ثبت شدی! از /start برای دیدن دستورات استفاده کن.");
    }

    // اگه پیام /refer <chat_id> بود، رفرال ثبت کن
    if (preg_match('/^\/refer (\d+)$/', $text, $matches)) {
        $referred_id = $matches[1];
        $stmt = $pdo->prepare("INSERT INTO referrals (referrer_id, referred_id) VALUES (:chat_id, :referred_id)");
        $stmt->execute(['chat_id' => $chat_id, 'referred_id' => $referred_id]);
        sendTelegramMessage($bot_token, $chat_id, "رفرال با موفقیت ثبت شد!");
    }
} else {
    error_log("No message found in Webhook data.");
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
        error_log("cURL Error: " . curl_error($ch));
    } else {
        error_log("cURL Response: " . $response);
    }
    curl_close($ch);

    return $response;
}
?>