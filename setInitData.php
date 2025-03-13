<?php
session_start();
header('Content-Type: application/json');

// دریافت داده از جاوااسکریپت
$input = file_get_contents('php://input');
$data = json_decode($input, true);

error_log("Received InitData from client: " . print_r($data, true));

if (isset($data['user']) && isset($data['user']['id'])) {
    $_SESSION['chat_id'] = $data['user']['id'];
    error_log("Chat ID set from client InitData: " . $data['user']['id']);
    echo json_encode(['success' => true]);
} else {
    error_log("No user ID in client InitData.");
    echo json_encode(['success' => false, 'error' => 'No user ID found']);
}
?>