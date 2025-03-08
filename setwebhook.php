<?php
$bot_token = 'YOUR_BOT_TOKEN'; // توکن باتت رو بذار
$webhook_url = 'https://oildropminer02-eay2.onrender.com/telegram.php'; // URL سرورت

$api_url = "https://api.telegram.org/bot$bot_token/setWebhook?url=" . urlencode($webhook_url);
$response = file_get_contents($api_url);
echo $response;
?>