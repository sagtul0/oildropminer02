<?php
$bot_token = '7534508415:AAG1JehLuafVTLz_esq0yJLzqJInJTHJ28c';
$webhook_url = 'https://oildropminer02-eay2.onrender.com/telegram.php';

$api_url = "https://api.telegram.org/bot$bot_token/setWebhook?url=" . urlencode($webhook_url);
$response = file_get_contents($api_url);
echo $response;
?>