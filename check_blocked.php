<?php
include 'header.php'; // Includes session and database connection

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? $_SESSION['chat_id'];
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if user is blocked
$stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ? OR chat_id = ?");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    echo json_encode(['success' => true, 'is_blocked' => (bool)$user['is_blocked']]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
exit;
?>