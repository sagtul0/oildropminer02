<?php
$bot_token = '7534598415:AAGL1ehLufaVTLZ_rsqOy3Li2nJTHJ28CoE'; // توکن باتت رو اینجا بذار
$webhook_url = 'https://oildropminer02-eay2.onrender.com/telegram.php'; // آدرس درست سرورت

$api_url = "https://api.telegram.org/bot$bot_token/setWebhook?url=" . urlencode($webhook_url);

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // برای تست (در محیط واقعی فعال کن)
$response = curl_exec($ch);

if (curl_error($ch)) {
    echo "خطا: " . curl_error($ch);
} else {
    echo $response;
}
curl_close($ch);
?>