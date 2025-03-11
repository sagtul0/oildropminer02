<?php
include 'header.php'; // Includes database connection as $conn and session start

// بررسی دسترسی ادمین
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit();
}

// بلاک کردن کاربر
if (isset($_GET['block']) && is_numeric($_GET['block'])) {
    $user_id = (int)$_GET['block'];
    $stmt = $conn->prepare("UPDATE users SET is_blocked = TRUE WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $message = "<div class='alert alert-success text-center mt-3'>User blocked successfully.</div>";
}

// آنبلاک کردن کاربر
if (isset($_GET['unblock']) && is_numeric($_GET['unblock'])) {
    $user_id = (int)$_GET['unblock'];
    $stmt = $conn->prepare("UPDATE users SET is_blocked = FALSE WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $message = "<div class='alert alert-success text-center mt-3'>User unblocked successfully.</div>";
}

// گرفتن لیست کاربران
$stmt = $conn->prepare("SELECT u.id, u.chat_id, u.referral_code, u.invite_reward, u.is_blocked, COUNT(rl.id) as referral_count 
                        FROM users u 
                        LEFT JOIN referral_logs rl ON u.id = rl.user_id 
                        GROUP BY u.id");
$stmt->execute();
$users = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Oil Drop Miner</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 1200px; margin-top: 50px; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">Admin Panel</h2>
        <?php if (isset($message)): echo $message; endif; ?>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Chat ID</th>
                    <th>Referral Code</th>
                    <th>Invite Reward</th>
                    <th>Referral Count</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['chat_id']); ?></td>
                        <td><?php echo htmlspecialchars($user['referral_code']); ?></td>
                        <td><?php echo htmlspecialchars($user['invite_reward']); ?></td>
                        <td><?php echo htmlspecialchars($user['referral_count']); ?></td>
                        <td><?php echo $user['is_blocked'] ? 'Blocked' : 'Active'; ?></td>
                        <td>
                            <?php if ($user['is_blocked']): ?>
                                <a href="?unblock=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">Unblock</a>
                            <?php else: ?>
                                <a href="?block=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm">Block</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>