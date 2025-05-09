<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'volunteer_system');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных: ' . $conn->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = $_GET['event_id'] ?? '';
    if (empty($event_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID мероприятия']);
        exit;
    }

    // Получаем участников мероприятия
    $stmt = $conn->prepare('
        SELECT v.email, e.title, e.event_date 
        FROM registrations r 
        JOIN volunteers v ON r.volunteer_id = v.id 
        JOIN events e ON r.event_id = e.id 
        WHERE r.event_id = ?
    ');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($results as $row) {
        $message = "Напоминание: мероприятие \"{$row['title']}\" состоится {$row['event_date']}.";
        file_put_contents('../emails.log', "To: {$row['email']}\nSubject: Напоминание о мероприятии\n$message\n\n", FILE_APPEND);
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Уведомления отправлены']);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешен']);
}

$conn->close();
?>