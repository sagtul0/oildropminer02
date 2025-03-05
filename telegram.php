<?php
// telegram.php (بروزرسانی‌شده برای رفع خطای cURL)
include 'config.php'; // اتصال به دیتابیس
include 'config/config_telegram.php'; // شامل توکن ربات (مسیر به‌روز شده)

$bot_token = TELEGRAM_BOT_TOKEN;

// دریافت اطلاعات از ربات تلگرام با file_get_contents
$update_json = file_get_contents('php://input');
if ($update_json === false) {
    error_log("Error reading php://input: " . error_get_last()['message']);
    die("Error: Cannot read Webhook data.");
}

$update = json_decode($update_json, true);

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $username = $update['message']['from']['username'] ?? '';
    $first_name = $update['message']['from']['first_name'] ?? '';
    $text = $update['message']['text'] ?? '';

    // ذخیره اطلاعات کاربر در دیتابیس (اگر وجود نداره)
    $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        // کاربر جدید هست، اطلاعات رو ذخیره کن
        $stmt = $conn->prepare("INSERT INTO users (telegram_id, username, first_name, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $user_id, $username, $first_name);
        if ($stmt->execute()) {
            $new_user_id = $conn->insert_id;
            // ارسال پیام خوش‌آمدگویی با cURL
            sendTelegramMessage($bot_token, $chat_id, "Welcome to Oil Drop Miner! Your Telegram ID has been registered. Use /start to begin.");
        } else {
            error_log("Error registering Telegram user: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // کاربر قبلاً ثبت شده، پیام بده
        sendTelegramMessage($bot_token, $chat_id, "You are already registered with Oil Drop Miner! Use /start to see commands.");
    }
}

// تابع ارسال پیام به تلگرام با cURL
function sendTelegramMessage($bot_token, $chat_id, $message) {
    $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // غیرفعال کردن تأیید SSL (برای تست لوکال)
    $response = curl_exec($ch);
    if (curl_error($ch)) {
        error_log("cURL Error: " . curl_error($ch));
    }
    curl_close($ch);

    return $response;
}