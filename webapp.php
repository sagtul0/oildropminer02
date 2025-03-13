<?php
ini_set('display_errors', 1); // برای نمایش خطاها در توسعه
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *'); // هدر CORS

include 'config.php';
session_start();

// دیباگ درخواست
error_log("Request Headers: " . print_r($_SERVER, true));
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Query String: " . $_SERVER['QUERY_STRING']);

// دریافت initData از تلگرام
$initData = $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? '';
error_log("Raw InitData: " . $initData);

if ($initData) {
    $data = [];
    parse_str($initData, $data);
    error_log("Parsed InitData: " . print_r($data, true));
    if (isset($data['user']['id'])) {
        $_SESSION['chat_id'] = $data['user']['id'];
        error_log("Chat ID set: " . $data['user']['id']);
    } else {
        error_log("No user ID in initData.");
    }
} elseif (isset($_GET['chat_id'])) {
    $_SESSION['chat_id'] = $_GET['chat_id'];
    error_log("Chat ID from GET: " . $_GET['chat_id']);
} else {
    error_log("No initData or chat_id received.");
}

if (!isset($_SESSION['chat_id'])) {
    die("Unauthorized access. Please open via Telegram WebApp. InitData: " . htmlspecialchars($initData));
}

$chat_id = $_SESSION['chat_id'];

try {
    $stmt = $pdo->prepare("SELECT oil_drops, balance, invite_reward FROM users WHERE chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $user = $stmt->fetch();
    if (!$user) {
        die("User not found. Please start the bot with /start. Chat ID: " . $chat_id);
    }

    $oil_drops = (int)$user['oil_drops'];
    $balance = (float)$user['balance'];
    $referrals = (int)$user['invite_reward'];

    $active_cards_stmt = $pdo->prepare("SELECT card_id, card_type, reward FROM user_cards WHERE chat_id = :chat_id");
    $active_cards_stmt->execute(['chat_id' => $chat_id]);
    $active_cards = $active_cards_stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oil Drop Miner Web App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: url('assets/images/backgrounds/auth_background_simple.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            font-family: Arial;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-color: #000;
        }
        .navbar {
            height: 60px;
            background-color: #1a1a1a;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }
        .navbar-brand {
            color: #ffcc00;
            font-weight: bold;
        }
        .container {
            margin-top: 80px;
            padding: 20px;
        }
        .card {
            background: rgba(30, 30, 30, 0.9);
            border: 2px solid #D4A017;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .disabled-btn {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Oil Drop Miner</a>
        </div>
    </nav>

    <div class="container">
        <h1 class="text-center text-warning mb-4">Oil Drop Miner Dashboard</h1>
        <div class="card">
            <h3>Your Stats</h3>
            <p>Oil Drops: <?php echo $oil_drops; ?></p>
            <p>TON Balance: <?php echo $balance; ?></p>
            <p>Referrals: <?php echo $referrals; ?></p>
        </div>

        <h2 class="text-center text-warning mb-4">Active Cards</h2>
        <?php if (count($active_cards) > 0): ?>
            <?php foreach ($active_cards as $card): ?>
                <div class="card">
                    <h4>Card ID: <?php echo $card['card_id']; ?> (Type: <?php echo $card['card_type']; ?>)</h4>
                    <p>Reward: <?php echo $card['reward']; ?> Oil Drops / 8h</p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center">You have no active cards.</p>
        <?php endif; ?>

        <p class="text-center text-warning">Note: Actions (e.g., buying cards) can only be done via Telegram bot commands.</p>
    </div>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();

        // دیباگ اطلاعات تلگرام
        if (tg.initDataUnsafe) {
            console.log("Telegram Init Data:", tg.initDataUnsafe);
        } else {
            console.error("No Telegram Init Data available.");
        }
    </script>
</body>
</html>