<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';

$user_logged_in = isset($_SESSION["user_id"]);

header('X-Frame-Options: SAMEORIGIN');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oil Drop Miner</title>
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* تنظیم ناوبر */
        .navbar {
            height: 60px !important; /* کاهش ارتفاع ناوبر */
            background-color: #1a1a1a !important; /* رنگ تیره‌تر برای هماهنگی */
        }

        .navbar-brand img {
            width: 50px !important; /* اندازه کوچیک‌تر لوگو */
            height: auto;
            margin-right: 5px;
        }

        .navbar-brand {
            font-size: 1.1rem !important; /* فونت کوچیک‌تر برای ناوبر */
            color: #ffcc00 !important;
            font-weight: bold;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7);
        }

        .nav-link {
            font-size: 1rem !important; /* فونت کوچیک‌تر برای لینک‌ها */
            color: #ffffff !important; /* سفید برای خوانایی */
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .nav-link:hover {
            color: #ffcc00 !important; /* طلایی روشن در هورور */
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/oil_drop_logo.png" alt="Oil Drop Miner Logo" class="d-inline-block align-text-top">
                Oil Drop Miner
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link home-text" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link home-text" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link home-text" href="earn.php">Earn</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link home-text" href="invite.php">Invite</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link home-text" href="plans.php">Plans</a> <!-- تغییر از Purchase به Plans و مسیر به plans.php -->
                    </li>
                    <li class="nav-item">
                        <a class="nav-link home-text" href="oil_cards.php">Oil Cards</a>
                    </li>
                    <?php if ($user_logged_in): ?>
                        <li class="nav-item">
                            <a class="nav-link home-text" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link home-text" href="login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- محتوای صفحه (اینجا توسط فایل‌های دیگر پر می‌شه) -->