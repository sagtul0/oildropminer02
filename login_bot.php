<?php
include 'header.php'; // Includes database connection as $conn and session start

if (isset($_GET['tgWebAppData'])) {
    $tgData = json_decode($_GET['tgWebAppData'], true);
    $chat_id = $tgData['user']['id'] ?? null;
    if ($chat_id) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE chat_id = ?");
        $stmt->bind_param("i", $chat_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['chat_id'] = $chat_id;
            session_regenerate_id(true); // Regenerate session ID for security

            // Check if user is blocked
            $stmt_block = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? OR chat_id = ?");
            $stmt_block->bind_param("ii", $user['id'], $chat_id);
            $stmt_block->execute();
            $result_block = $stmt_block->get_result();
            $blocked_user = $result_block->fetch_assoc();
            if ($blocked_user['is_blocked']) {
                session_destroy();
                echo "<div class='alert alert-danger text-center mt-3'>Your account has been blocked!</div>";
                include 'footer.php';
                exit;
            }
            $stmt_block->close();

            // Check if this is admin
            $admin_chat_id = "YOUR_ADMIN_CHAT_ID"; // جایگزین با chat_id ادمین (مثلاً 123456789)
            if ($chat_id == $admin_chat_id) {
                $_SESSION['admin'] = bin2hex(random_bytes(16)); // یه رشته رندوم برای امنیت
            }

            header("Location: index.php");
            exit;
        } else {
            echo "<div class='alert alert-danger text-center mt-3'>User not registered in bot!</div>";
            include 'footer.php';
            exit;
        }
    }
}
echo "<div class='alert alert-danger text-center mt-3'>Invalid access from bot!</div>";
include 'footer.php';
?>