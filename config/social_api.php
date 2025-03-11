<?php
// config/social_api.php

// توکن‌ها و کلیدهای API
require_once 'config_telegram.php'; // برای TELEGRAM_BOT_TOKEN
define('TELEGRAM_CHAT_ID', 'YOUR_TELEGRAM_CHANNEL_CHAT_ID'); // ID کانال تلگرام
define('X_API_KEY', 'YOUR_X_API_KEY'); // کلید API برای X (Twitter)
define('YOUTUBE_API_KEY', 'YOUR_YOUTUBE_API_KEY'); // کلید API برای YouTube
define('INSTAGRAM_ACCESS_TOKEN', 'YOUR_INSTAGRAM_ACCESS_TOKEN'); // توکن دسترسی برای Instagram

// تابع بررسی عضویت در کانال تلگرام
function checkTelegramMembership($user_id) {
    $bot_token = TELEGRAM_BOT_TOKEN;
    $chat_id = TELEGRAM_CHAT_ID;
    $url = "https://api.telegram.org/bot$bot_token/getChatMember?chat_id=$chat_id&user_id=$user_id";
    
    $response = file_get_contents($url);
    if ($response === false) {
        error_log("Failed to fetch Telegram API response for user ID: $user_id");
        return false;
    }

    $data = json_decode($response, true);
    if (isset($data['ok']) && $data['ok'] === true) {
        $status = $data['result']['status'];
        return in_array($status, ['member', 'administrator', 'creator']);
    }
    error_log("Telegram API error for user ID: $user_id - " . print_r($data, true));
    return false;
}

// تابع بررسی فالو در X (Twitter)
function checkXFollow($user_id) {
    $headers = [
        'Authorization: Bearer ' . X_API_KEY,
        'Content-Type: application/json'
    ];
    $url = "https://api.twitter.com/2/users/$user_id/following";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        error_log("Failed to fetch X API response for user ID: $user_id");
        return false;
    }
    $data = json_decode($response, true);
    curl_close($ch);
    if (isset($data['data']) && !empty($data['data'])) {
        return true; // فرض می‌کنیم کاربر دنبال‌کننده است
    }
    error_log("X API error for user ID: $user_id - " . print_r($data, true));
    return false;
}

// تابع بررسی اشتراک در یوتیوب
function checkYouTubeSubscription($user_id) {
    $url = "https://www.googleapis.com/youtube/v3/subscriptions?part=snippet&forChannelId=YOUR_CHANNEL_ID&key=" . YOUTUBE_API_KEY;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        error_log("Failed to fetch YouTube API response for user ID: $user_id");
        return false;
    }
    $data = json_decode($response, true);
    curl_close($ch);
    if (isset($data['items']) && !empty($data['items'])) {
        return true;
    }
    error_log("YouTube API error for user ID: $user_id - " . print_r($data, true));
    return false;
}

// تابع بررسی فالو در اینستاگرام
function checkInstagramFollow($user_id) {
    $url = "https://graph.instagram.com/me/accounts?access_token=" . INSTAGRAM_ACCESS_TOKEN;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        error_log("Failed to fetch Instagram API response for user ID: $user_id");
        return false;
    }
    $data = json_decode($response, true);
    curl_close($ch);
    if (isset($data['data']) && !empty($data['data'])) {
        return true;
    }
    error_log("Instagram API error for user ID: $user_id - " . print_r($data, true));
    return false;
}
?>