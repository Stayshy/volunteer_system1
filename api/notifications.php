<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['user_id'] ?? '';
    $user_role = $_GET['user_role'] ?? '';

    if (empty($user_id) || empty($user_role)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID пользователя и роль']);
        exit;
    }

    $stmt = $conn->prepare('
        SELECT n.id, n.event_id, n.message, n.created_at, e.title as event_title
        FROM notifications n
        LEFT JOIN events e ON n.event_id = e.id
        WHERE n.user_id = ? AND n.user_role = ?
        ORDER BY n.created_at DESC
    ');
    $stmt->bind_param('is', $user_id, $user_role);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($notifications);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = $_GET['event_id'] ?? '';
    if (empty($event_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID мероприятия']);
        exit;
    }

    // Получаем информацию о мероприятии
    $stmt = $conn->prepare('
        SELECT e.title, e.event_date, e.organizer_id
        FROM events e
        WHERE e.id = ?
    ');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Мероприятие не найдено']);
        exit;
    }

    $message = "Напоминание: мероприятие \"{$event['title']}\" состоится {$event['event_date']}.";

    // Сохраняем уведомление для организатора
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, user_role, event_id, message) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isis', $event['organizer_id'], 'organizer', $event_id, $message);
    $stmt->execute();

    // Получаем участников мероприятия
    $stmt = $conn->prepare('
        SELECT v.id, v.email
        FROM registrations r
        JOIN volunteers v ON r.volunteer_id = v.id
        WHERE r.event_id = ?
    ');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Сохраняем уведомления для волонтёров и записываем в emails.log
    foreach ($results as $row) {
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, user_role, event_id, message) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isis', $row['id'], 'volunteer', $event_id, $message);
        $stmt->execute();

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