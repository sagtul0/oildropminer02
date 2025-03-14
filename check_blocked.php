<?php
include 'config.php'; // Includes $conn as PDO

header('Content-Type: application/json; charset=utf-8');

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check if user is logged in
$chat_id = $_SESSION['chat_id'] ?? null;
if (!$chat_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if user is blocked
$stmt = $conn->prepare("SELECT is_blocked FROM users WHERE chat_id = :chat_id");
$stmt->execute(['chat_id' => $chat_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode(['success' => true, 'is_blocked' => (bool)$user['is_blocked']]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
exit;
?>