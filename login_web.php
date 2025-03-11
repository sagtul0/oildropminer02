<?php
include 'header.php'; // Includes database connection as $conn and session start

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed!";
    } else {
        $ton_address = filter_var($_POST['ton_address'], FILTER_SANITIZE_STRING);
        if (!preg_match('/^EQ[a-zA-Z0-9]{47}$/', $ton_address)) {
            $error = "Invalid TON address format!";
        } else {
            $stmt = $conn->prepare("SELECT id, chat_id FROM users WHERE ton_address = ?");
            $stmt->bind_param("s", $ton_address);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['chat_id'] = $user['chat_id'];
                session_regenerate_id(true); // Regenerate session ID for security

                // Check if user is blocked
                $stmt_block = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? OR chat_id = ?");
                $stmt_block->bind_param("ii", $user['id'], $user['chat_id']);
                $stmt_block->execute();
                $result_block = $stmt_block->get_result();
                $blocked_user = $result_block->fetch_assoc();
                if ($blocked_user['is_blocked']) {
                    session_destroy();
                    $error = "Your account has been blocked!";
                } else {
                    // Check if this is admin
                    $admin_chat_id = "YOUR_ADMIN_CHAT_ID"; // جایگزین با chat_id ادمین (مثلاً 123456789)
                    if ($user['chat_id'] == $admin_chat_id) {
                        $_SESSION['admin'] = bin2hex(random_bytes(16)); // یه رشته رندوم برای امنیت
                    }
                    header("Location: index.php");
                    exit;
                }
                $stmt_block->close();
            } else {
                $error = "TON address not registered!";
            }
        }
    }
}
?>

<div class="container text-center mt-5">
    <h1 class="text-warning mb-4">Login with TON Address</h1>
    <?php if (isset($error)) echo "<p class='alert alert-danger'>$error</p>"; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="text" name="ton_address" class="form-control w-50 mx-auto" placeholder="Enter TON Address" required>
        <button type="submit" class="btn btn-warning mt-3">Login</button>
    </form>
</div>

<?php include 'footer.php'; ?>