<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chat_id = $_POST['chat_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = :chat_id");
    $stmt->execute(['chat_id' => $chat_id]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['chat_id'] = $chat_id;
        header("Location: oil_cards.php");
        exit;
    } else {
        $error = "User not found! Please register in the Telegram bot first with /start.";
    }
}
?>
<html>
<head>
    <title>Login to Oil Drop Miner</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: url('assets/images/backgrounds/auth_background_simple.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #ffffff;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .container {
            margin-top: 80px;
            padding: 20px;
        }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center text-warning mb-4">Login to Oil Drop Miner</h1>
        <?php if (isset($error)) echo "<p class='error text-center'>$error</p>"; ?>
        <form method="POST" class="text-center">
            <label>Enter your Telegram Chat ID:</label><br>
            <input type="text" name="chat_id" class="form-control w-50 mx-auto" required><br>
            <button type="submit" class="btn btn-warning mt-3">Login</button>
        </form>
        <p class="text-center mt-3">Don't know your Chat ID? Use /chatid in the Telegram bot to get it.</p>
    </div>
</body>
</html>