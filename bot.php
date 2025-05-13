<?php
// Устанавливаем заголовки
header('Content-Type: application/json');

// Получаем данные от Telegram
$update = json_decode(file_get_contents('php://input'), true);

// Логируем входящие данные для отладки
file_put_contents('../debug.log', "Bot update: " . json_encode($update) . "\n", FILE_APPEND);

// Проверяем, что это сообщение
if (!isset($update['message'])) {
    exit;
}

$chat_id = $update['message']['chat']['id'];
$message = $update['message']['text'];

// Токен бота
$botToken = "8064615716:AAEoSSxajme_NeIYuTEcobvBWobzDq_9H64"; // Замени на свой токен
$apiUrl = "https://api.telegram.org/bot" . $botToken;

// Подключение к базе данных
$conn = new mysqli('localhost', 'root', '', 'volunteer_system');
if ($conn->connect_error) {
    file_put_contents('../debug.log', "Ошибка подключения к базе данных: " . $conn->connect_error . "\n", FILE_APPEND);
    exit;
}

// Обработка команд
switch ($message) {
    case '/start':
        $reply = "Привет! Я бот Volunteer System. 😊\nЯ могу:\n- Показать ближайшие мероприятия (/events)\n- Помочь с другими задачами (/help)";
        sendMessage($chat_id, $reply, $apiUrl);
        break;

    case '/help':
        $reply = "Я могу помочь с:\n- Просмотром мероприятий (/events)\n- Уведомлениями о новых событиях\nЕсли что-то нужно, пиши!";
        sendMessage($chat_id, $reply, $apiUrl);
        break;

    case '/events':
        // Получаем ближайшие мероприятия
        $stmt = $conn->prepare('SELECT title, event_date FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 5');
        $stmt->execute();
        $result = $stmt->get_result();
        $events = $result->fetch_all(MYSQLI_ASSOC);

        if (empty($events)) {
            $reply = "Ближайших мероприятий нет. 😔";
        } else {
            $reply = "Ближайшие мероприятия:\n";
            foreach ($events as $event) {
                $reply .= "- " . $event['title'] . " (" . date('d.m.Y H:i', strtotime($event['event_date'])) . ")\n";
            }
        }
        sendMessage($chat_id, $reply, $apiUrl);
        break;

    default:
        $reply = "Я не понял команду. 😅 Попробуй /start или /help.";
        sendMessage($chat_id, $reply, $apiUrl);
        break;
}

// Функция отправки сообщения
function sendMessage($chat_id, $text, $apiUrl) {
    $url = $apiUrl . "/sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($text);
    file_get_contents($url);
    file_put_contents('../debug.log', "Сообщение отправлено: chat_id=$chat_id, text=$text\n", FILE_APPEND);
}

$conn->close();
?>