<?php
ini_set('display_errors', 1); // برای نمایش خطاها در توسعه
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *'); // هدر CORS
header('Content-Type: text/html; charset=utf-8'); // اطمینان از کدنویسی UTF-8

include 'config.php';
session_start();

// دیباگ درخواست
error_log("Request Headers: " . print_r($_SERVER, true));
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Query String: " . (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : 'Not set'));

// بررسی سشن برای دسترسی
if (!isset($_SESSION['chat_id'])) {
    // اگر chat_id تو سشن نیست، منتظر می‌مونیم تا جاوااسکریپت اون رو بفرسته
    error_log("No chat_id in session yet. Waiting for client-side initData.");
} else {
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
        error_log("Database error: " . $e->getMessage());
        die("Database error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oil Drop Miner Web App</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: url('assets/images/backgrounds/auth_background_simple.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            font-family: Arial, sans-serif;
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
        <?php if (isset($_SESSION['chat_id'])): ?>
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
                        <h4>Card ID: <?php echo htmlspecialchars($card['card_id']); ?> (Type: <?php echo htmlspecialchars($card['card_type']); ?>)</h4>
                        <p>Reward: <?php echo htmlspecialchars($card['reward']); ?> Oil Drops / 8h</p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center">You have no active cards.</p>
            <?php endif; ?>

            <p class="text-center text-warning">Note: Actions (e.g., buying cards) can only be done via Telegram bot commands.</p>
        <?php else: ?>
            <p class="text-center text-warning">Loading... Please wait while we authenticate you via Telegram.</p>
        <?php endif; ?>
    </div>

    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();

        // دیباگ پیشرفته اطلاعات تلگرام
        console.log("Telegram WebApp Object:", tg);
        if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
            console.log("Telegram Init Data:", tg.initDataUnsafe);
            // ارسال initData به سرور
            fetch('/setInitData.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(tg.initDataUnsafe),
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('InitData sent to server:', data);
                if (data.success) {
                    window.location.reload();
                } else {
                    console.error('Server response error:', data.error);
                }
            })
            .catch(error => {
                console.error('Error sending initData to server:', error);
            });
        } else {
            console.error("No Telegram Init Data available or user data missing. Check if the page is opened via Telegram WebApp.");
            document.querySelector('.container').innerHTML = `
                <p class="text-center text-danger">Error: Unable to authenticate. Please open this page via the Telegram bot.</p>
            `;
        }
    </script>
</body>
</html>