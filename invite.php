<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// گرفتن اطلاعات کاربر دعوت‌کننده
$stmt = $conn->prepare("SELECT telegram_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0 && !empty($result->fetch_assoc()['telegram_id'])) {
    // کاربر دعوت‌کننده معتبر هست
    if (isset($_GET['referral_code'])) {
        $referral_code = $_GET['referral_code'];
        $stmt = $conn->prepare("SELECT id, telegram_id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $referral_code);
        $stmt->execute();
        $referred_user = $stmt->get_result()->fetch_assoc();

        if ($referred_user && !empty($referred_user['telegram_id'])) {
            // کاربر رفرال‌شده معتبر هست، پاداش بده
            $update_stmt = $conn->prepare("UPDATE users SET invite_reward = invite_reward + 1 WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            $message = "Referral successful! You earned 1 referral point.";
        } else {
            $message = "Invalid or unverified referral code.";
        }
    }
} else {
    $message = "Please verify your Telegram ID to use referrals.";
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invite - Oil Drop Miner</title>
    <!-- لینک‌های CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center text-warning mb-4">Invite Friends</h2>
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo strpos($message, 'successful') !== false ? 'success' : 'danger'; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <p class="text-white">Share your referral link to earn rewards:</p>
        <p class="text-white">Your referral code: <?php echo generateReferralCode($user_id); ?></p>
        <p class="text-white">Link: http://localhost/proje/register.php?referral_code=<?php echo generateReferralCode($user_id); ?></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function generateReferralCode($user_id) {
    return substr(md5($user_id), 0, 8);
}