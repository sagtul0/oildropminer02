<?php
include 'header.php'; // Includes database connection as $conn and session start

// Check if user is logged in or needs registration
if (!isset($_SESSION['user_id']) && !isset($_SESSION['chat_id'])) {
    if (isset($_GET['tgWebAppData'])) {
        $tgData = json_decode($_GET['tgWebAppData'], true);
        $chat_id = $tgData['user']['id'] ?? null;
        if ($chat_id) {
            // Check if this chat_id is already registered
            $stmt = $conn->prepare("SELECT id FROM users WHERE chat_id = ?");
            $stmt->bind_param("i", $chat_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['chat_id'] = $chat_id;
            } else {
                // Register new user with is_blocked default to 0
                $stmt = $conn->prepare("INSERT INTO users (chat_id, oil_drops, today_clicks, balance, boost_multiplier, is_blocked) VALUES (?, 0, 0, 0.0, 1.0, 0)");
                $stmt->bind_param("i", $chat_id);
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['chat_id'] = $chat_id;
                    $message = "<div class='alert alert-success text-center mt-3'>Welcome! You have been registered successfully.</div>";
                } else {
                    error_log("Error registering user with chat_id $chat_id: " . $stmt->error);
                    $message = "<div class='alert alert-danger text-center mt-3'>Error registering user. Please try again later.</div>";
                    include 'footer.php';
                    exit;
                }
            }
            $stmt->close();
        } else {
            $message = "<div class='alert alert-danger text-center mt-3'>Please <a href='login_web.php'>login</a> with TON address.</div>";
            include 'footer.php';
            exit;
        }
    } else {
        $message = "<div class='alert alert-danger text-center mt-3'>Please <a href='login_web.php'>login</a> with TON address.</div>";
        include 'footer.php';
        exit;
    }
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['chat_id'];

// Check if user is blocked
$stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if ($user['is_blocked']) {
    $message = "<div class='alert alert-danger text-center mt-3'>Your account has been blocked.</div>";
    include 'footer.php';
    exit;
}
$stmt->close();

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate Limiting: Check number of attempts in the last 5 minutes
$stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM referral_attempts WHERE user_id = ? AND attempt_time > NOW() - INTERVAL '5 minutes'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
if ($result['attempts'] >= 5) {
    $message = "<div class='alert alert-warning text-center mt-3'>Too many attempts. Please try again later.</div>";
} else {
    // Log the current attempt
    $stmt = $conn->prepare("INSERT INTO referral_attempts (user_id, attempt_time) VALUES (?, NOW())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // Process referral if referral code is provided
    if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['referral_code'])) {
        if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
            $message = "<div class='alert alert-danger text-center mt-3'>Invalid request. Please try again.</div>";
        } else {
            $referral_code = filter_var($_GET['referral_code'], FILTER_SANITIZE_STRING);
            $stmt = $conn->prepare("SELECT id, chat_id FROM users WHERE referral_code = ?");
            $stmt->bind_param("s", $referral_code);
            $stmt->execute();
            $referred_user = $stmt->get_result()->fetch_assoc();

            if ($referred_user && $referred_user['chat_id'] && $referred_user['id'] != $user_id) {
                // Check if this chat_id has already been referred
                $check_stmt = $conn->prepare("SELECT id FROM referral_logs WHERE referred_user_id = ?");
                $check_stmt->bind_param("i", $referred_user['id']);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows == 0) {
                    // Award referral point and log it
                    $conn->begin_transaction();
                    try {
                        $update_stmt = $conn->prepare("UPDATE users SET invite_reward = invite_reward + 1 WHERE id = ?");
                        $update_stmt->bind_param("i", $user_id);
                        if (!$update_stmt->execute()) {
                            throw new Exception("Error updating invite_reward: " . $update_stmt->error);
                        }

                        $insert_stmt = $conn->prepare("INSERT INTO referral_logs (user_id, referred_user_id) VALUES (?, ?)");
                        $insert_stmt->bind_param("ii", $user_id, $referred_user['id']);
                        if (!$insert_stmt->execute()) {
                            throw new Exception("Error inserting referral log: " . $insert_stmt->error);
                        }

                        $conn->commit();
                        $message = "<div class='alert alert-success text-center mt-3'>Referral successful! You earned 1 referral point.</div>";
                    } catch (Exception $e) {
                        $conn->rollback();
                        error_log("Transaction error in invite.php for user $user_id: " . $e->getMessage());
                        $message = "<div class='alert alert-danger text-center mt-3'>Error processing referral. Please try again later.</div>";
                    }
                    $update_stmt->close();
                    $insert_stmt->close();
                } else {
                    $message = "<div class='alert alert-warning text-center mt-3'>This user has already been referred.</div>";
                }
                $check_stmt->close();
            } else {
                $message = "<div class='alert alert-danger text-center mt-3'>Invalid referral code or self-referral detected.</div>";
            }
        }
    }

    // Generate referral code if not exists
    $stmt = $conn->prepare("SELECT referral_code FROM users WHERE id = ? OR chat_id = ?");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if (!$result['referral_code']) {
        $referral_code = generateReferralCode($user_id);
        $update_stmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE id = ? OR chat_id = ?");
        $update_stmt->bind_param("sii", $referral_code, $user_id, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        $referral_code = $result['referral_code'];
    }
}
$stmt->close();

// Dynamically generate the base URL for the referral link
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$base_url = strtok($base_url, '?'); // Remove query parameters if any
$referral_link = "$base_url?referral_code=" . urlencode($referral_code) . "&csrf_token=" . urlencode($_SESSION['csrf_token']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invite - Oil Drop Miner</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background: url('assets/images/backgrounds/auth_background_simple.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #ffffff !important;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .container {
            max-width: 1000px;
            margin-top: 80px !important;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        .alert { 
            color: #ffffff !important; 
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7) !important; 
            font-size: 1rem; 
            padding: 15px; 
            border-radius: 8px; 
        }
        .alert-success { background-color: #28a745 !important; border-color: #1e7e34 !important; }
        .alert-danger { background-color: #dc3545 !important; border-color: #bd2130 !important; }
        .alert-warning { background-color: #ffcc00 !important; border-color: #e6b800 !important; }
        #particles-js { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        .text-white { 
            font-size: 1.1rem; 
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7); 
            margin-bottom: 15px; 
        }
        .referral-code { 
            font-weight: bold; 
            color: #ffcc00; 
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7); 
        }
        .referral-link { 
            word-break: break-all; 
            color: #ffcc00; 
            text-decoration: none; 
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7); 
        }
        .referral-link:hover { 
            color: #daa520; 
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <div class="container mt-5">
        <h2 class="text-center text-warning mb-4" style="text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);">Invite Friends</h2>
        <?php if (isset($message)): echo $message; endif; ?>
        <p class="text-white">Share your referral link to earn rewards:</p>
        <p class="text-white">Your referral code: <span class="referral-code"><?php echo htmlspecialchars($referral_code); ?></span></p>
        <p class="text-white">Link: <a href="<?php echo htmlspecialchars($referral_link); ?>" class="referral-link"><?php echo htmlspecialchars($referral_link); ?></a></p>
        <p class="text-white">Note: Your friend must register with a unique Telegram account to earn your reward!</p>
    </div>
    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        particlesJS("particles-js", {
            "particles": { "number": { "value": 50, "density": { "enable": true, "value_area": 800 } }, "color": { "value": "#D4A017" }, "shape": { "type": "line", "stroke": { "width": 2, "color": "#D4A017" } }, "opacity": { "value": 0.8, "random": true, "anim": { "enable": true, "speed": 1, "opacity_min": 0.5 } }, "size": { "value": 0 }, "line_linked": { "enable": true, "distance": 150, "color": "#D4A017", "opacity": 0.8, "width": 2 }, "move": { "enable": true, "speed": 2, "direction": "random", "random": true, "straight": false, "out_mode": "out", "bounce": false, "attract": { "enable": false } } },
            "interactivity": { "detect_on": "canvas", "events": { "onhover": { "enable": true, "mode": "repulse" }, "onclick": { "enable": false } } },
            "retina_detect": true
        });

        const tg = window.Telegram.WebApp;
        tg.ready();
        tg.expand();
        const chatId = tg.initDataUnsafe?.user?.id;
        if (chatId && !$_SESSION['chat_id']) {
            window.location.href = `?chat_id=${chatId}`;
        }
    </script>
</body>
</html>

<?php
function generateReferralCode($user_id) {
    return substr(md5("oilminer" . $user_id . time()), 0, 8); // Added salt and time for uniqueness
}
?>