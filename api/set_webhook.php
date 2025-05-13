<?php
$botToken = "8064615716:AAEoSSxajme_NeIYuTEcobvBWobzDq_9H64"; // Замени на токен, который ты получила от BotFather
$website = "https://api.telegram.org/bot" . $botToken;
$webhookUrl = "http://localhost/volunteer_system/api/bot.php"; // URL твоего сервера

$response = file_get_contents($website . "/setWebhook?url=" . urlencode($webhookUrl));
echo $response;
?>