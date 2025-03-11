<?php
include 'header.php'; // Includes database connection and session start

$user_id = $_SESSION['user_id'] ?? $_SESSION['chat_id'];

// Check if user is blocked
$stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if ($user['is_blocked']) {
    echo "<div class='alert alert-danger text-center mt-3'>Your account has been blocked.</div>";
    include 'footer.php';
    exit;
}
$stmt->close();

// پاک کردن سشن
session_unset();
session_destroy();

// هدایت به صفحه لاگین
header("Location: login_web.php");
exit();
?>

<?php include 'footer.php'; ?>