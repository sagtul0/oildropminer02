<?php
session_start();
header('Content-Type: application/json');

error_log("setInitData.php called");

// دریافت داده از جاوااسکریپت
$input = file_get_contents('php://input');
$data = json_decode($input, true);

error_log("Received InitData from client: " . print_r($data, true));

if ($data && isset($data['user']) && isset($data['user']['id'])) {
    $chat_id = $data['user']['id'];
    $_SESSION['chat_id'] = $chat_id;
    error_log("Chat ID set from client InitData: " . $chat_id);
    // ریدایرکت با پارامتر chat_id به‌صورت GET
    header('Location: https://oildropminer02-eay2.onrender.com/webapp.php?chat_id=' . urlencode($chat_id));
    exit;
} else {
    error_log("No user ID in client InitData or data is invalid.");
    echo json_encode(['success' => false, 'error' => 'No user ID found or invalid data']);
}
?>