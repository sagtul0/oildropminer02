<?php
include 'config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $telegram_code = $_POST['telegram_code'] ?? ''; // کد تأیید از تلگرام

    // بررسی کد تأیید با ربات تلگرام
    $bot_token = "YOUR_BOT_TOKEN_HERE"; // توکن ربات
    $telegram_response = file_get_contents("https://api.telegram.org/bot" . $bot_token . "/getUpdates");
    $telegram_data = json_decode($telegram_response, true);

    $telegram_id = null;
    foreach ($telegram_data['result'] as $update) {
        if (isset($update['message']['text']) && $update['message']['text'] === $telegram_code) {
            $telegram_id = $update['message']['from']['id'];
            break;
        }
    }

    if ($telegram_id) {
        // ذخیره کاربر در دیتابیس با telegram_id
        $stmt = $conn->prepare("INSERT INTO users (username, password, telegram_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ssi", $username, $password, $telegram_id);
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $conn->insert_id;
            header("Location: index.php");
            exit();
        } else {
            $error = "Registration failed: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Invalid Telegram verification code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Oil Drop Miner</title>
    <!-- لینک‌های CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center text-warning mb-4">Register</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" class="mx-auto" style="max-width: 400px;">
            <div class="mb-3">
                <label for="username" class="form-label text-white">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label text-white">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="telegram_code" class="form-label text-white">Telegram Verification Code</label>
                <input type="text" class="form-control" id="telegram_code" name="telegram_code" required>
                <small class="text-white">Send /verify to @oildropminer_bot to get your code.</small>
            </div>
            <button type="submit" class="btn btn-warning w-100">Register</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>