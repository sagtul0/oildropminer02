<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_log("Script started at " . date('Y-m-d H:i:s'));

include 'config.php';

if (!$conn) {
    error_log("Connection is null after including config.php at " . date('Y-m-d H:i:s'));
    die("Error: Database connection not established.");
}

try {
    $conn->query("SELECT 1");
    error_log("Database is working correctly at " . date('Y-m-d H:i:s'));
} catch (PDOException $e) {
    error_log("Error connecting to database: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
    die("Error connecting to database.");
}

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (!$bot_token) {
    error_log("TELEGRAM_BOT_TOKEN is not set at " . date('Y-m-d H:i:s'));
    die("Error: TELEGRAM_BOT_TOKEN not found.");
}
error_log("Bot token retrieved: $bot_token");

$update_json = file_get_contents('php://input');
if ($update_json === false) {
    error_log("Error reading php://input: " . (error_get_last()['message'] ?? 'Unknown error') . " at " . date('Y-m-d H:i:s'));
    die("Error: Cannot read Webhook data.");
}
error_log("Webhook data received: $update_json");

$update = json_decode($update_json, true);

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $username = $update['message']['from']['username'] ?? '';
    $first_name = $update['message']['from']['first_name'] ?? '';
    $last_name = $update['message']['from']['last_name'] ?? '';
    $text = $update['message']['text'] ?? '';

    error_log("Message received - Chat ID: $chat_id, Text: $text");

    $stmt = $conn->prepare("SELECT chat_id, ton_address, oil_drops, balance, invite_reward FROM users WHERE chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $conn->prepare("INSERT INTO users (chat_id, username, first_name, last_name, created_at, oil_drops, balance, invite_reward) VALUES (:chat_id, :username, :first_name, :last_name, NOW(), 0, 0.0, 0)");
        $stmt->execute(['chat_id' => $chat_id, 'username' => $username, 'first_name' => $first_name, 'last_name' => $last_name]);
        error_log("New user registered with Chat ID: $chat_id");
        sendTelegramMessage($bot_token, $chat_id, "Welcome to Oil Drop Miner! Set your TON address with /setaddress and start with /openapp.");
    }

    $stmt = $conn->prepare("SELECT oil_drops, balance, invite_reward, ton_address FROM users WHERE chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $user = $stmt->fetch();
    $oil_drops = (int)$user['oil_drops'];
    $balance = (float)$user['balance'];
    $referrals = (int)$user['invite_reward'];
    $ton_address = $user['ton_address'] ?? '';

    if ($text === '/start') {
        $keyboard = [
            [
                ['text' => 'Open App 📱', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/webapp.php']]
            ]
        ];
        sendTelegramMessageWithKeyboard($bot_token, $chat_id, "Welcome! You have $oil_drops Oil Drops and $balance TON. Click below to open the app.", $keyboard);
    } elseif ($text === '/help') {
        sendTelegramMessage($bot_token, $chat_id, "Commands:\n/start - Start\n/help - Help\n/openapp - Open the app\n/setaddress <address> - Set TON address\n/myaddress - View TON address\n/buycard <card_id> - Buy a card\n/refer <chat_id> - Invite a friend");
    } elseif ($text === '/openapp') {
        $keyboard = [
            [
                ['text' => 'Open App 📱', 'web_app' => ['url' => 'https://oildropminer02-eay2.onrender.com/webapp.php']]
            ]
        ];
        sendTelegramMessageWithKeyboard($bot_token, $chat_id, "Open the Oil Drop Miner app:", $keyboard);
    } elseif (preg_match('/^\/setaddress (.+)$/', $text, $matches)) {
        $new_address = $matches[1];
        $stmt = $conn->prepare("UPDATE users SET ton_address = :ton_address WHERE chat_id = :chat_id");
        try {
            $stmt->execute(['ton_address' => $new_address, 'chat_id' => $chat_id]);
            sendTelegramMessage($bot_token, $chat_id, "Your TON address has been set to: $new_address");
        } catch (PDOException $e) {
            sendTelegramMessage($bot_token, $chat_id, "Error: This TON address is already used.");
        }
    } elseif ($text === '/myaddress') {
        sendTelegramMessage($bot_token, $chat_id, "Your TON address: " . ($ton_address ?: "Not set. Use /setaddress <address>"));
    } elseif (preg_match('/^\/buycard (\d+)$/', $text, $matches)) {
        $card_id = (int)$matches[1];
        $oil_cards = [1 => ["cost" => 450, "reward" => 150], 2 => ["cost" => 1590, "reward" => 530]];
        if (isset($oil_cards[$card_id])) {
            $cost = $oil_cards[$card_id]['cost'];
            if ($oil_drops >= $cost) {
                $new_oil = $oil_drops - $cost;
                $stmt = $conn->prepare("UPDATE users SET oil_drops = :new_oil WHERE chat_id = :chat_id");
                $stmt->execute(['new_oil' => $new_oil, 'chat_id' => $chat_id]);
                $stmt = $conn->prepare("INSERT INTO user_cards (chat_id, card_id, card_type, reward, last_reward_at) VALUES (:chat_id, :card_id, 'oil', :reward, NOW())");
                $stmt->execute(['chat_id' => $chat_id, 'card_id' => $card_id, 'reward' => $oil_cards[$card_id]['reward']]);
                sendTelegramMessage($bot_token, $chat_id, "Card $card_id purchased! Reward: " . $oil_cards[$card_id]['reward'] . " Oil Drops/8h");
            } else {
                sendTelegramMessage($bot_token, $chat_id, "Not enough Oil Drops. (Have: $oil_drops, Need: $cost)");
            }
        } else {
            sendTelegramMessage($bot_token, $chat_id, "Invalid card ID.");
        }
    } elseif (preg_match('/^\/refer (\d+)$/', $text, $matches)) {
        $referred_id = $matches[1];
        $stmt = $conn->prepare("INSERT INTO referrals (referrer_id, referred_id, created_at) VALUES (:chat_id, :referred_id, NOW())");
        $stmt->execute(['chat_id' => $chat_id, 'referred_id' => $referred_id]);
        sendTelegramMessage($bot_token, $chat_id, "Referral registered!");
    }
}

function sendTelegramMessage($bot_token, $chat_id, $message) {
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $message];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    error_log("Message sent: $response");
    return $response;
}

function sendTelegramMessageWithKeyboard($bot_token, $chat_id, $message, $keyboard) {
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    error_log("Message with keyboard sent: $response");
    return $response;
}